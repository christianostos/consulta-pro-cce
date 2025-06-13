<?php
/**
 * Manejo de página de resultados en nueva pestaña
 * Archivo: includes/class-cp-results-page.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_Results_Page {
    
    private static $instance = null;
    private $export_handler;
    
    /**
     * Singleton pattern
     */
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->export_handler = CP_Export_Advanced::get_instance();
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        add_action('wp', array($this, 'handle_results_page'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_results_assets'));
        add_filter('document_title_parts', array($this, 'modify_results_page_title'));
    }
    
    /**
     * Manejar página de resultados
     */
    public function handle_results_page() {
        // Verificar si es una página de resultados
        if (!isset($_GET['cp_results'])) {
            return;
        }
        
        $search_id = sanitize_text_field($_GET['cp_results']);
        
        if (empty($search_id)) {
            wp_die('ID de búsqueda inválido');
        }
        
        // Obtener resultados
        $results_data = $this->get_results_data($search_id);
        
        if (!$results_data) {
            wp_die('Resultados no encontrados o han expirado');
        }
        
        // Renderizar página de resultados
        $this->render_results_page($results_data);
        exit;
    }
    
    /**
     * Obtener datos de resultados
     */
    private function get_results_data($search_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_search_progress';
        
        $search_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE search_id = %s",
            $search_id
        ), ARRAY_A);
        
        if (!$search_data) {
            return false;
        }
        
        // Verificar que la búsqueda esté completada
        if (!in_array($search_data['status'], ['completed', 'no_results'])) {
            return false;
        }
        
        $results = json_decode($search_data['results_data'], true);
        
        return array(
            'search_id' => $search_id,
            'status' => $search_data['status'],
            'profile_type' => $search_data['profile_type'],
            'fecha_inicio' => $search_data['fecha_inicio'],
            'fecha_fin' => $search_data['fecha_fin'],
            'numero_documento' => $search_data['numero_documento'],
            'total_records' => intval($search_data['total_records']),
            'results' => $results ?: array(),
            'created_at' => $search_data['created_at'],
            'active_sources' => json_decode($search_data['active_sources'], true)
        );
    }
    
    /**
     * Renderizar página de resultados
     */
    private function render_results_page($data) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Resultados de Consulta de Procesos - <?php bloginfo('name'); ?></title>
            
            <?php wp_head(); ?>
            
            <style>
                body { 
                    margin: 0; 
                    padding: 0; 
                    background: #f5f5f5; 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                }
                .admin-bar { margin-top: 0 !important; }
            </style>
        </head>
        <body <?php body_class('cp-results-page'); ?>>
            
            <div class="cp-results-container">
                <!-- Header -->
                <div class="cp-results-header">
                    <h1>
                        <span class="dashicons dashicons-analytics"></span>
                        Resultados de Consulta de Procesos
                    </h1>
                    
                    <?php $this->render_search_summary($data); ?>
                </div>
                
                <!-- Acciones de exportación -->
                <?php if ($data['status'] === 'completed' && $data['total_records'] > 0): ?>
                    <div class="cp-export-actions">
                        <h3>
                            <span class="dashicons dashicons-download"></span>
                            Exportar Resultados
                        </h3>
                        <div class="cp-export-buttons">
                            <button class="cp-export-btn" data-format="excel" data-search-id="<?php echo esc_attr($data['search_id']); ?>">
                                <span class="dashicons dashicons-media-spreadsheet"></span>
                                Descargar Excel
                            </button>
                            <button class="cp-export-btn" data-format="pdf" data-search-id="<?php echo esc_attr($data['search_id']); ?>">
                                <span class="dashicons dashicons-pdf"></span>
                                Descargar PDF
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Resultados por fuente -->
                <?php if ($data['status'] === 'completed' && !empty($data['results'])): ?>
                    <?php $this->render_results_by_source($data); ?>
                <?php else: ?>
                    <?php $this->render_no_results($data); ?>
                <?php endif; ?>
                
            </div>
            
            <!-- Loading overlay para exportaciones -->
            <div id="cp-export-loading" class="cp-loading-overlay" style="display: none;">
                <div class="cp-loading-content">
                    <div class="cp-spinner"></div>
                    <p>Preparando descarga...</p>
                </div>
            </div>
            
            <?php wp_footer(); ?>
            
            <script>
                // Variables para JavaScript
                window.cpResultsData = {
                    searchId: '<?php echo esc_js($data['search_id']); ?>',
                    nonce: '<?php echo wp_create_nonce('cp_frontend_nonce'); ?>',
                    ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>'
                };
            </script>
        </body>
        </html>
        <?php
    }
    
    /**
     * Renderizar resumen de búsqueda
     */
    private function render_search_summary($data) {
        ?>
        <div class="cp-search-summary">
            <h3>Información de la Búsqueda</h3>
            
            <div class="cp-summary-grid">
                <div class="cp-summary-item">
                    <strong><?php echo ucfirst($data['profile_type']); ?></strong>
                    <span>Perfil</span>
                </div>
                
                <div class="cp-summary-item">
                    <strong><?php echo esc_html($data['numero_documento']); ?></strong>
                    <span>Documento</span>
                </div>
                
                <div class="cp-summary-item">
                    <strong><?php echo esc_html($data['fecha_inicio']); ?></strong>
                    <span>Fecha Inicio</span>
                </div>
                
                <div class="cp-summary-item">
                    <strong><?php echo esc_html($data['fecha_fin']); ?></strong>
                    <span>Fecha Fin</span>
                </div>
                
                <div class="cp-summary-item">
                    <strong><?php echo number_format($data['total_records']); ?></strong>
                    <span>Total Registros</span>
                </div>
                
                <div class="cp-summary-item">
                    <strong><?php echo date('H:i:s', strtotime($data['created_at'])); ?></strong>
                    <span>Hora Consulta</span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderizar resultados por fuente
     */
    private function render_results_by_source($data) {
        $source_names = array(
            'tvec' => array(
                'name' => 'TVEC - Tienda Virtual del Estado Colombiano',
                'icon' => 'store'
            ),
            'secopi' => array(
                'name' => 'SECOPI - Sistema Electrónico de Contratación Pública I',
                'icon' => 'database'
            ),
            'secopii' => array(
                'name' => 'SECOPII - Sistema Electrónico de Contratación Pública II',
                'icon' => 'database-view'
            )
        );
        
        foreach ($data['active_sources'] as $source) {
            $records = $data['results'][$source] ?? array();
            $source_info = $source_names[$source] ?? array('name' => strtoupper($source), 'icon' => 'database');
            
            ?>
            <div class="cp-source-results">
                <div class="cp-source-results-header">
                    <h3>
                        <span class="dashicons dashicons-<?php echo esc_attr($source_info['icon']); ?>"></span>
                        <?php echo esc_html($source_info['name']); ?>
                    </h3>
                    <div class="cp-records-count">
                        <?php echo count($records); ?> registros
                    </div>
                </div>
                
                <div class="cp-source-results-content">
                    <?php if (!empty($records)): ?>
                        <?php $this->render_results_table($records, $source); ?>
                    <?php else: ?>
                        <div class="cp-no-results-section">
                            <span class="dashicons dashicons-info"></span>
                            <p>No se encontraron registros en <?php echo esc_html($source_info['name']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Renderizar tabla de resultados
     */
    private function render_results_table($records, $source) {
        if (empty($records)) {
            return;
        }
        
        $headers = array_keys($records[0]);
        
        ?>
        <div class="cp-results-table-container">
            <table class="cp-results-table">
                <thead>
                    <tr>
                        <?php foreach ($headers as $header): ?>
                            <th><?php echo esc_html($this->format_header($header)); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <?php foreach ($record as $key => $value): ?>
                                <td class="<?php echo $this->get_cell_class($key, $value); ?>">
                                    <?php echo $this->format_cell_value($key, $value); ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Renderizar página sin resultados
     */
    private function render_no_results($data) {
        ?>
        <div class="cp-no-results-section">
            <span class="dashicons dashicons-search"></span>
            <h3>No se encontraron resultados</h3>
            <p>
                No se encontraron registros que coincidan con los criterios de búsqueda especificados 
                para el período del <?php echo esc_html($data['fecha_inicio']); ?> 
                al <?php echo esc_html($data['fecha_fin']); ?>.
            </p>
            
            <div class="cp-no-results-actions">
                <button onclick="window.close()" class="cp-btn cp-btn-secondary">
                    <span class="dashicons dashicons-no-alt"></span>
                    Cerrar Ventana
                </button>
                <button onclick="window.history.back()" class="cp-btn cp-btn-primary">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    Nueva Búsqueda
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Formatear header de tabla
     */
    private function format_header($header) {
        // Mapeo de headers específicos para mejor presentación
        $header_map = array(
            'ID Contrato' => 'ID Contrato',
            'Fecha cargue' => 'Fecha Cargue',
            'NIT de la Entidad' => 'NIT Entidad',
            'Nombre de la Entidad' => 'Entidad',
            'Valor con adiciones' => 'Valor Total',
            'Nom Raz Social Contratista' => 'Contratista',
            'Identificacion del Contratista' => 'NIT/CC Contratista',
            'Link' => 'Enlace'
        );
        
        return $header_map[$header] ?? str_replace('_', ' ', ucwords(str_replace(array('_', '-'), ' ', $header)));
    }
    
    /**
     * Obtener clase CSS para celda
     */
    private function get_cell_class($key, $value) {
        $classes = array();
        
        // Clase para contenido largo
        if (strlen($value) > 50) {
            $classes[] = 'cp-table-cell-long';
        }
        
        // Clase para números
        if (is_numeric($value) || preg_match('/valor|total|precio/i', $key)) {
            $classes[] = 'cp-table-number';
            
            if (preg_match('/valor|total|precio/i', $key)) {
                $classes[] = 'cp-table-currency';
            }
        }
        
        return implode(' ', $classes);
    }
    
    /**
     * Formatear valor de celda
     */
    private function format_cell_value($key, $value) {
        if (is_null($value) || $value === '') {
            return '<em>-</em>';
        }
        
        // Formatear URLs como enlaces
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return '<a href="' . esc_url($value) . '" target="_blank" rel="noopener">Ver Proceso</a>';
        }
        
        // Formatear valores monetarios
        if (preg_match('/valor|total|precio/i', $key) && is_numeric($value)) {
            return '$' . number_format($value, 0, ',', '.');
        }
        
        // Formatear fechas
        if (preg_match('/fecha/i', $key) && strtotime($value)) {
            return date('d/m/Y', strtotime($value));
        }
        
        // Truncar texto largo
        if (strlen($value) > 100) {
            return '<span title="' . esc_attr($value) . '">' . 
                   esc_html(substr($value, 0, 100)) . '...</span>';
        }
        
        return esc_html($value);
    }
    
    /**
     * Cargar assets para página de resultados
     */
    public function enqueue_results_assets() {
        if (!isset($_GET['cp_results'])) {
            return;
        }
        
        wp_enqueue_style(
            'cp-results-css', 
            CP_PLUGIN_URL . 'assets/css/frontend.css', 
            array(), 
            CP_PLUGIN_VERSION
        );
        
        wp_enqueue_script(
            'cp-results-js', 
            CP_PLUGIN_URL . 'assets/js/results.js', 
            array('jquery'), 
            CP_PLUGIN_VERSION, 
            true
        );
        
        // Localizar script
        wp_localize_script('cp-results-js', 'cpResults', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cp_frontend_nonce'),
            'messages' => array(
                'exporting' => __('Preparando descarga...', 'consulta-procesos'),
                'error' => __('Error en la exportación', 'consulta-procesos'),
                'success' => __('Descarga iniciada', 'consulta-procesos')
            )
        ));
    }
    
    /**
     * Modificar título de página de resultados
     */
    public function modify_results_page_title($title_parts) {
        if (isset($_GET['cp_results'])) {
            $title_parts['title'] = 'Resultados de Consulta de Procesos';
        }
        return $title_parts;
    }
}