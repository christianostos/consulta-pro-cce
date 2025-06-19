<?php
/**
 * Clase para manejar la funcionalidad del frontend del plugin
 * 
 * Archivo: includes/class-cp-frontend.php
 * ACTUALIZADO: Con implementación completa de APIs
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_Frontend {
    
    private static $instance = null;
    private $db;
    
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
        $this->db = CP_Database::get_instance();
        $this->init_hooks();
        $this->create_logs_table();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Registrar shortcode
        add_shortcode('consulta_procesos', array($this, 'render_shortcode'));
        
        // Scripts y estilos del frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // Hook para procesar formulario
        add_action('wp_ajax_cp_process_search_form', array($this, 'ajax_process_search_form'));
        add_action('wp_ajax_nopriv_cp_process_search_form', array($this, 'ajax_process_search_form'));
        
        // Hook para obtener progreso de búsqueda
        add_action('wp_ajax_cp_get_search_progress', array($this, 'ajax_get_search_progress'));
        add_action('wp_ajax_nopriv_cp_get_search_progress', array($this, 'ajax_get_search_progress'));
        
        // Hooks para logs del frontend (admin)
        add_action('wp_ajax_cp_get_frontend_logs', array($this, 'ajax_get_frontend_logs'));
        add_action('wp_ajax_cp_clear_frontend_logs', array($this, 'ajax_clear_frontend_logs'));

        // Hook para exportar resultados del frontend
        add_action('wp_ajax_cp_export_frontend_results', array($this, 'ajax_export_frontend_results'));
        add_action('wp_ajax_nopriv_cp_export_frontend_results', array($this, 'ajax_export_frontend_results'));

        // Hook para descarga automática de resultados del frontend
        add_action('wp_ajax_cp_auto_export_frontend_results', array($this, 'ajax_auto_export_frontend_results'));
        add_action('wp_ajax_nopriv_cp_auto_export_frontend_results', array($this, 'ajax_auto_export_frontend_results'));

        // NUEVO: Hooks para exportación de PDF sin resultados
        add_action('wp_ajax_cp_export_no_results_pdf', array($this, 'ajax_export_no_results_pdf'));
        add_action('wp_ajax_nopriv_cp_export_no_results_pdf', array($this, 'ajax_export_no_results_pdf'));

        // NUEVO: Hook para descarga automática de PDF sin resultados  
        add_action('wp_ajax_cp_auto_export_no_results_pdf', array($this, 'ajax_auto_export_no_results_pdf'));
        add_action('wp_ajax_nopriv_cp_auto_export_no_results_pdf', array($this, 'ajax_auto_export_no_results_pdf'));
    }
    
    /**
     * Crear tabla de logs si no existe
     */
    private function create_logs_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL DEFAULT '',
            profile_type varchar(50) NOT NULL,
            fecha_inicio date NOT NULL,
            fecha_fin date NOT NULL,
            numero_documento varchar(100) NOT NULL,
            search_sources text,
            status varchar(20) NOT NULL DEFAULT 'success',
            error_message text,
            results_found int(11) NOT NULL DEFAULT 0,
            execution_time decimal(8,3) DEFAULT NULL,
            ip_address varchar(45) NOT NULL DEFAULT '',
            user_agent text,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_created_at (created_at),
            KEY idx_profile_type (profile_type),
            KEY idx_status (status),
            KEY idx_numero_documento (numero_documento)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Verificar que la tabla se creó correctamente
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            error_log('CP Frontend: Error creando tabla de logs: ' . $table_name);
        } else {
            error_log('CP Frontend: Tabla de logs verificada: ' . $table_name);
        }
    }
    
    /**
     * Cargar assets del frontend
     */
    public function enqueue_frontend_assets() {
        // Solo cargar en páginas que contengan el shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'consulta_procesos')) {
            
            wp_enqueue_style(
                'cp-frontend-css', 
                CP_PLUGIN_URL . 'assets/css/frontend.css', 
                array(), 
                CP_PLUGIN_VERSION
            );
            
            wp_enqueue_script(
                'cp-frontend-js', 
                CP_PLUGIN_URL . 'assets/js/frontend.js', 
                array('jquery'), 
                CP_PLUGIN_VERSION, 
                true
            );
            
            // Localizar script
            wp_localize_script('cp-frontend-js', 'cp_frontend_ajax', array(
                'url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cp_frontend_nonce'),
                'messages' => array(
                    'loading' => __('Buscando...', 'consulta-procesos'),
                    'error' => __('Error en la búsqueda', 'consulta-procesos'),
                    'no_results' => __('No se encontraron resultados', 'consulta-procesos'),
                    'accept_terms' => __('Debe aceptar los términos de uso', 'consulta-procesos'),
                    'select_profile' => __('Debe seleccionar un perfil', 'consulta-procesos'),
                    'fill_dates' => __('Debe completar las fechas', 'consulta-procesos'),
                    'auto_download_success' => __('Descarga automática iniciada', 'consulta-procesos'),
                    'manual_download_available' => __('También puede descargar manualmente', 'consulta-procesos'),
                    'progress' => array(
                        'initializing' => __('Inicializando búsqueda...', 'consulta-procesos'),
                        'connecting' => __('Conectando a base de datos...', 'consulta-procesos'),
                        'searching_tvec' => __('Consultando TVEC...', 'consulta-procesos'),
                        'searching_secopi' => __('Consultando SECOPI...', 'consulta-procesos'),
                        'searching_secopii' => __('Consultando SECOPII...', 'consulta-procesos'),
                        'processing_results' => __('Procesando resultados...', 'consulta-procesos'),
                        'completed' => __('Búsqueda completada', 'consulta-procesos'),
                        'error' => __('Error en la consulta', 'consulta-procesos')
                    )
                )
            ));
        }
    }
    
    /**
     * Renderizar shortcode principal
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Consulta de Procesos', 'consulta-procesos'),
            'show_title' => 'true'
        ), $atts);
        
        ob_start();
        ?>
        <div class="cp-frontend-container" id="cp-frontend-form">
            <?php if ($atts['show_title'] === 'true'): ?>
                <h2 class="cp-frontend-title"><?php echo esc_html($atts['title']); ?></h2>
            <?php endif; ?>
            
            <!-- Progress Bar -->
            <div class="cp-progress-bar">
                <div class="cp-progress-step active" data-step="1">
                    <span class="cp-step-number">1</span>
                    <span class="cp-step-label"><?php _e('Términos', 'consulta-procesos'); ?></span>
                </div>
                <div class="cp-progress-step" data-step="2">
                    <span class="cp-step-number">2</span>
                    <span class="cp-step-label"><?php _e('Perfil', 'consulta-procesos'); ?></span>
                </div>
                <div class="cp-progress-step" data-step="3">
                    <span class="cp-step-number">3</span>
                    <span class="cp-step-label"><?php _e('Búsqueda', 'consulta-procesos'); ?></span>
                </div>
            </div>
            
            <!-- Etapa 1: Términos de Uso -->
            <div class="cp-form-step cp-step-1 active">
                <?php $this->render_terms_step(); ?>
            </div>
            
            <!-- Etapa 2: Selección de Perfil -->
            <div class="cp-form-step cp-step-2">
                <?php $this->render_profile_step(); ?>
            </div>
            
            <!-- Etapa 3: Formulario de Búsqueda -->
            <div class="cp-form-step cp-step-3">
                <?php $this->render_search_step(); ?>
            </div>
            
            <!-- Indicador de progreso de búsqueda -->
            <div class="cp-search-progress-container" style="display: none;">
                <div class="cp-search-progress-header">
                    <h3><?php _e('Procesando Búsqueda', 'consulta-procesos'); ?></h3>
                    <div class="cp-overall-progress">
                        <div class="cp-progress-bar-fill" style="width: 0%"></div>
                    </div>
                    <div class="cp-progress-percentage">0%</div>
                </div>
                
                <div class="cp-search-sources-progress">
                    <!-- Estos se generarán dinámicamente via JavaScript -->
                </div>
                
                <div class="cp-progress-actions">
                    <button type="button" class="cp-btn cp-btn-secondary" id="cp-cancel-search" style="display: none;">
                        <?php _e('Cancelar', 'consulta-procesos'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Resultados -->
            <div class="cp-results-container" style="display: none;">
                <h3><?php _e('Resultados de la Búsqueda', 'consulta-procesos'); ?></h3>
                <div class="cp-results-content"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renderizar etapa 1: Términos de uso
     */
    private function render_terms_step() {
        $terms_content = get_option('cp_terms_content', $this->get_default_terms());
        ?>
        <div class="cp-terms-container">
            <h3><?php _e('Términos de Uso', 'consulta-procesos'); ?></h3>
            
            <div class="cp-terms-content">
                <?php echo wp_kses_post($terms_content); ?>
            </div>
            
            <div class="cp-terms-acceptance">
                <label class="cp-checkbox-label">
                    <input type="checkbox" id="cp-accept-terms" name="accept_terms" value="1">
                    <span class="cp-checkbox-text">
                        <?php _e('He leído y acepto los términos de uso', 'consulta-procesos'); ?>
                    </span>
                </label>
            </div>
            
            <div class="cp-form-actions">
                <button type="button" class="cp-btn cp-btn-primary" id="cp-continue-to-profile">
                    <?php _e('Continuar', 'consulta-procesos'); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderizar etapa 2: Selección de perfil
     */
    private function render_profile_step() {
        ?>
        <div class="cp-profile-container">
            <h3 style="color: #233A79;"><b><?php _e('Seleccione su Perfil', 'consulta-procesos'); ?></b></h3>
            
            <div class="cp-profile-options">
                <div class="cp-profile-option">
                    <label class="cp-radio-card">
                        <input type="radio" name="profile_type" value="entidades">
                        <div class="cp-radio-content">
                            <div class="cp-radio-icon">
                                <img src="<?php echo plugin_dir_url(dirname(__FILE__)) . 'images/IconoEntidad.png'; ?>" alt="Icono Entidad" class="cp-icon-perfil" />
                            </div>
                            <h4><?php _e('Entidades', 'consulta-procesos'); ?></h4>
                            <p><?php _e('Consultas para entidades públicas y organizaciones', 'consulta-procesos'); ?></p>
                        </div>
                    </label>
                </div>
                
                <div class="cp-profile-option">
                    <label class="cp-radio-card">
                        <input type="radio" name="profile_type" value="proveedores">
                        <div class="cp-radio-content">
                            <div class="cp-radio-icon">
                                <img src="<?php echo plugin_dir_url(dirname(__FILE__)) . 'images/IconoProveedor.png'; ?>" alt="Icono Entidad" class="cp-icon-perfil" />
                            </div>
                            <h4><?php _e('Proveedores', 'consulta-procesos'); ?></h4>
                            <p><?php _e('Consultas para proveedores y contratistas', 'consulta-procesos'); ?></p>
                        </div>
                    </label>
                </div>
            </div>
            
            <div class="cp-form-actions">
                <button type="button" class="cp-btn cp-btn-secondary" id="cp-back-to-terms">
                    <?php _e('Atrás', 'consulta-procesos'); ?>
                </button>
                <button type="button" class="cp-btn cp-btn-primary" id="cp-continue-to-search">
                    <?php _e('Continuar', 'consulta-procesos'); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderizar etapa 3: Formulario de búsqueda
     */
    private function render_search_step() {
        ?>
        <div class="cp-search-container">
            <h3 style="color: #233A79; margin-bottom: 5px;"><b><?php _e('Formulario de Búsqueda', 'consulta-procesos'); ?></b></h3>
            <p><?php _e('Formulario de búsqueda de procesos de contratación por Entidad', 'consulta-procesos'); ?></p>
            
            <form id="cp-search-form" class="cp-search-form">
                <h3><b><?php _e('Datos de búsqueda', 'consulta-procesos'); ?></b></h3>
                <div class="cp-form-grid">
                    <div class="cp-form-group">
                        <label for="cp-fecha-inicio">
                            <?php _e('Fecha de Inicio', 'consulta-procesos'); ?> <span class="required">*</span>
                        </label>
                        <input type="date" id="cp-fecha-inicio" name="fecha_inicio" required>
                    </div>
                    
                    <div class="cp-form-group">
                        <label for="cp-fecha-fin">
                            <?php _e('Fecha de Fin', 'consulta-procesos'); ?> <span class="required">*</span>
                        </label>
                        <input type="date" id="cp-fecha-fin" name="fecha_fin" required>
                    </div>
                    
                    <div class="cp-form-group cp-form-group-full">
                        <label for="cp-numero-documento">
                            <?php _e('Número de Documento', 'consulta-procesos'); ?> <span class="required">*</span>
                        </label>
                        <input type="text" id="cp-numero-documento" name="numero_documento" placeholder="<?php _e('Ingrese el número', 'consulta-procesos'); ?>" required>
                        <small class="cp-help-text">
                            <?php _e('Por favor ingrese el número de identificación sin puntos, guión, coma y sin digito de verificación. Ej: 900514813', 'consulta-procesos'); ?>
                        </small>
                    </div>
                </div>
                
                <!-- Información del perfil seleccionado -->
                <div class="cp-profile-info">
                    <p><strong><?php _e('Perfil seleccionado:', 'consulta-procesos'); ?></strong> 
                    <span id="cp-selected-profile"></span></p>
                </div>
                
                <div class="cp-form-actions">
                    <button type="button" class="cp-btn cp-btn-secondary" id="cp-back-to-profile">
                        <?php _e('Atrás', 'consulta-procesos'); ?>
                    </button>
                    <button type="submit" class="cp-btn cp-btn-primary" id="cp-search-submit">
                        <span class="cp-btn-text"><?php _e('Buscar', 'consulta-procesos'); ?></span>
                        <span class="cp-btn-spinner" style="display: none;">
                            <span class="dashicons dashicons-update-alt"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Obtener términos por defecto
     */
    private function get_default_terms() {
        return '<p><strong>Términos y Condiciones de Uso</strong></p>
        <p>Al utilizar este sistema de consulta de procesos, usted acepta los siguientes términos:</p>
        <ul>
            <li>La información proporcionada será utilizada únicamente para consultas oficiales</li>
            <li>No está permitido el uso indebido de los datos obtenidos</li>
            <li>El sistema está sujeto a disponibilidad y mantenimiento</li>
            <li>Los resultados mostrados son de carácter informativo</li>
        </ul>
        <p>Para más información, consulte nuestra <a href="#" target="_blank">política de privacidad</a>.</p>';
    }
    
    /**
     * AJAX: Procesar búsqueda del formulario - MEJORADO CON LOGS COMPLETOS
     */
    public function ajax_process_search_form() {
        $start_time = microtime(true);
        $log_data = array();
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cp_frontend_nonce')) {
            error_log('CP Frontend: Nonce inválido');
            $this->log_search_error('nonce_invalid', 'Token de seguridad inválido', null, null, null, null, $start_time);
            wp_send_json_error(array('message' => 'Token de seguridad inválido'));
        }
        
        // Obtener y sanitizar datos
        $profile_type = sanitize_text_field($_POST['profile_type'] ?? '');
        $fecha_inicio = sanitize_text_field($_POST['fecha_inicio'] ?? '');
        $fecha_fin = sanitize_text_field($_POST['fecha_fin'] ?? '');
        $numero_documento = sanitize_text_field($_POST['numero_documento'] ?? '');
        
        $log_data = array(
            'profile_type' => $profile_type,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'numero_documento' => $numero_documento
        );
        
        // Log para debugging
        error_log("CP Frontend: Búsqueda iniciada - Perfil: {$profile_type}, Documento: {$numero_documento}, Fechas: {$fecha_inicio} a {$fecha_fin}");
        
        // Validar datos
        if (empty($profile_type) || empty($fecha_inicio) || empty($fecha_fin) || empty($numero_documento)) {
            error_log('CP Frontend: Faltan datos requeridos');
            $this->log_search_error('missing_data', 'Faltan datos requeridos', $profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $start_time);
            wp_send_json_error(array('message' => 'Faltan datos requeridos'));
        }
        
        // Validar fechas
        if (!$this->validate_date_range($fecha_inicio, $fecha_fin)) {
            error_log('CP Frontend: Rango de fechas inválido');
            $this->log_search_error('invalid_date_range', 'Rango de fechas inválido', $profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $start_time);
            wp_send_json_error(array('message' => 'Rango de fechas inválido'));
        }
        
        try {
            // Generar ID único para esta búsqueda
            $search_id = 'cp_search_' . uniqid();
            
            // Inicializar progreso con parámetros de búsqueda
            $this->init_search_progress($search_id, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
            
            // Iniciar búsqueda en background inmediatamente
            $this->start_actual_search($search_id, $start_time);
            
            // Enviar ID de búsqueda al cliente para que pueda hacer seguimiento
            wp_send_json_success(array(
                'search_started' => true,
                'search_id' => $search_id,
                'message' => __('Búsqueda iniciada', 'consulta-procesos')
            ));
            
        } catch (Exception $e) {
            error_log('CP Frontend Search Error: ' . $e->getMessage());
            $this->log_search_error('system_error', $e->getMessage(), $profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $start_time);
            wp_send_json_error(array('message' => 'Error interno del servidor: ' . $e->getMessage()));
        }
    }
    
    /**
     * AJAX para obtener progreso de búsqueda
     */
    public function ajax_get_search_progress() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cp_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Token de seguridad inválido'));
        }
        
        $search_id = sanitize_text_field($_POST['search_id'] ?? '');
        
        if (empty($search_id)) {
            wp_send_json_error(array('message' => 'ID de búsqueda requerido'));
        }
        
        // Ejecutar siguiente paso de búsqueda y obtener progreso
        $progress = $this->execute_next_search_step($search_id);
        
        if ($progress === false) {
            wp_send_json_error(array('message' => 'Búsqueda no encontrada o expirada'));
        }
        
        wp_send_json_success($progress);
    }
    
    /**
     * AJAX para obtener logs del frontend (para admin)
     */
    public function ajax_get_frontend_logs() {
        // Verificar permisos de administrador
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        try {
            // Obtener logs más recientes (últimos 1000)
            $logs = $wpdb->get_results("
                SELECT * FROM {$table_name} 
                ORDER BY created_at DESC 
                LIMIT 1000
            ", ARRAY_A);
            
            // Obtener estadísticas
            $total_searches = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            $successful_searches = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'success'");
            $failed_searches = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'error'");
            
            // Estadísticas por perfil
            $entidades_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE profile_type = 'entidades'");
            $proveedores_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE profile_type = 'proveedores'");
            
            wp_send_json_success(array(
                'logs' => $logs,
                'stats' => array(
                    'total' => intval($total_searches),
                    'successful' => intval($successful_searches),
                    'failed' => intval($failed_searches),
                    'entidades' => intval($entidades_count),
                    'proveedores' => intval($proveedores_count)
                )
            ));
            
        } catch (Exception $e) {
            error_log('CP Frontend: Error obteniendo logs - ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error obteniendo logs: ' . $e->getMessage()));
        }
    }
    
    /**
     * AJAX para limpiar logs del frontend (para admin)
     */
    public function ajax_clear_frontend_logs() {
        // Verificar permisos de administrador
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        try {
            $result = $wpdb->query("DELETE FROM {$table_name}");
            
            if ($result !== false) {
                wp_send_json_success(array(
                    'message' => 'Logs limpiados exitosamente',
                    'deleted_rows' => $result
                ));
            } else {
                wp_send_json_error(array('message' => 'Error limpiando logs'));
            }
            
        } catch (Exception $e) {
            error_log('CP Frontend: Error limpiando logs - ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error limpiando logs: ' . $e->getMessage()));
        }
    }
    
    /**
     * Inicializar progreso de búsqueda
     */
    private function init_search_progress($search_id, $profile_type = '', $fecha_inicio = '', $fecha_fin = '', $numero_documento = '') {
        // Obtener configuración de búsquedas activas
        $search_config = $this->get_search_configuration();
        $active_sources = array();
        
        foreach ($search_config as $source => $config) {
            if ($config['active']) {
                $active_sources[] = $source;
            }
        }
        
        $progress_data = array(
            'search_id' => $search_id,
            'status' => 'initializing',
            'overall_progress' => 0,
            'current_step' => 'initializing',
            'message' => __('Inicializando búsqueda...', 'consulta-procesos'),
            'active_sources' => $active_sources,
            'sources_progress' => array(),
            'total_sources' => count($active_sources),
            'completed_sources' => 0,
            'results' => array(),
            'total_records' => 0,
            'start_time' => time(),
            'last_update' => time(),
            // Almacenar parámetros de búsqueda
            'search_params' => array(
                'profile_type' => $profile_type,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
                'numero_documento' => $numero_documento
            )
        );
        
        // Inicializar progreso de cada fuente
        foreach ($active_sources as $source) {
            $progress_data['sources_progress'][$source] = array(
                'status' => 'pending',
                'progress' => 0,
                'message' => __('Pendiente', 'consulta-procesos'),
                'records_found' => 0,
                'error' => null
            );
        }
        
        // Guardar en transient (expira en 10 minutos)
        set_transient($search_id, $progress_data, 600);
        
        return $progress_data;
    }
    
    /**
     * Obtener progreso de búsqueda
     */
    private function get_search_progress($search_id) {
        return get_transient($search_id);
    }
    
    /**
     * Actualizar progreso de búsqueda
     */
    private function update_search_progress($search_id, $updates) {
        $progress = get_transient($search_id);
        
        if ($progress === false) {
            return false;
        }
        
        // Actualizar datos
        foreach ($updates as $key => $value) {
            $progress[$key] = $value;
        }
        
        $progress['last_update'] = time();
        
        // Calcular progreso general
        if (isset($progress['sources_progress'])) {
            $total_progress = 0;
            $completed_count = 0;
            
            foreach ($progress['sources_progress'] as $source_progress) {
                $total_progress += $source_progress['progress'];
                if ($source_progress['status'] === 'completed' || $source_progress['status'] === 'error') {
                    $completed_count++;
                }
            }
            
            $progress['overall_progress'] = count($progress['sources_progress']) > 0 
                ? round($total_progress / count($progress['sources_progress'])) 
                : 0;
            $progress['completed_sources'] = $completed_count;
            
            // Si todas las fuentes están completadas
            if ($completed_count >= $progress['total_sources']) {
                $progress['status'] = 'completed';
                $progress['overall_progress'] = 100;
                $progress['message'] = __('Búsqueda completada', 'consulta-procesos');
            }
        }
        
        // Guardar progreso actualizado
        set_transient($search_id, $progress, 600);
        
        return $progress;
    }
    
    /**
     * Iniciar búsqueda real con seguimiento de progreso
     */
    private function start_actual_search($search_id, $start_time = null) {
        if ($start_time === null) {
            $start_time = microtime(true);
        }
        
        // No ejecutar búsqueda completa aquí, solo marcar como iniciada
        $this->update_search_progress($search_id, array(
            'status' => 'running',
            'message' => __('Búsqueda iniciada...', 'consulta-procesos')
        ));
        
        error_log("CP Frontend: Búsqueda marcada como iniciada para search_id: " . $search_id);
    }
    
    /**
     * Ejecutar siguiente paso de búsqueda - MEJORADO CON LOGS
     */
    private function execute_next_search_step($search_id) {
        $progress = get_transient($search_id);
        
        if ($progress === false) {
            error_log('CP Frontend: Progreso no encontrado para search_id: ' . $search_id);
            return false;
        }
        
        if ($progress['status'] === 'completed') {
            error_log('CP Frontend: Búsqueda ya completada para search_id: ' . $search_id);
            return $progress;
        }
        
        if ($progress['status'] === 'error') {
            error_log('CP Frontend: Búsqueda en error para search_id: ' . $search_id);
            return $progress;
        }
        
        // Verificar que tenemos los parámetros de búsqueda
        if (!isset($progress['search_params']) || empty($progress['search_params']['profile_type'])) {
            error_log('CP Frontend: Parámetros de búsqueda no encontrados');
            $this->update_search_progress($search_id, array(
                'status' => 'error',
                'message' => 'Error: parámetros de búsqueda no encontrados'
            ));
            return $this->get_search_progress($search_id);
        }
        
        $search_params = $progress['search_params'];
        $profile_type = $search_params['profile_type'];
        $fecha_inicio = $search_params['fecha_inicio'];
        $fecha_fin = $search_params['fecha_fin'];
        $numero_documento = $search_params['numero_documento'];
        
        // Calcular tiempo de ejecución para logs
        $search_start_time = isset($progress['search_start_time']) ? $progress['search_start_time'] : microtime(true);
        if (!isset($progress['search_start_time'])) {
            $progress['search_start_time'] = $search_start_time;
            set_transient($search_id, $progress, 600);
        }
        
        // Buscar siguiente fuente pendiente (no running)
        $next_source = null;
        $pending_sources = array();
        $running_sources = array();
        $completed_sources = array();
        
        foreach ($progress['sources_progress'] as $source => $source_progress) {
            if ($source_progress['status'] === 'pending') {
                $pending_sources[] = $source;
                if ($next_source === null) {
                    $next_source = $source;
                }
            } elseif ($source_progress['status'] === 'running') {
                $running_sources[] = $source;
            } elseif ($source_progress['status'] === 'completed' || $source_progress['status'] === 'error') {
                $completed_sources[] = $source;
            }
        }
        
        error_log("CP Frontend: Estado de fuentes - Pendientes: " . implode(',', $pending_sources) . 
                  " | En progreso: " . implode(',', $running_sources) . 
                  " | Completadas: " . implode(',', $completed_sources));
        
        // Si hay fuentes en "running", NO ejecutar nuevas hasta que terminen
        if (!empty($running_sources)) {
            error_log("CP Frontend: Hay fuentes en progreso (" . implode(',', $running_sources) . "), esperando...");
            // Solo devolver el progreso actual sin ejecutar nada nuevo
            return $progress;
        }
        
        // Solo ejecutar si no hay nada "running" y hay algo "pending"
        if ($next_source && empty($running_sources)) {
            // Ejecutar búsqueda para esta fuente
            error_log("CP Frontend: Ejecutando búsqueda para fuente: " . $next_source);
            
            // Marcar como en progreso INMEDIATAMENTE
            $this->update_source_progress($search_id, $next_source, 'running', 10, __('Consultando...', 'consulta-procesos'));
            
            // Usar try-catch para asegurar que siempre se marca como completada
            try {
                $search_config = $this->get_search_configuration();
                $method = $search_config[$next_source]['method'];
                
                $results = array();
                $start_time = microtime(true);
                
                switch ($next_source) {
                    case 'tvec':
                        error_log("CP Frontend: Ejecutando consulta TVEC...");
                        $results = $this->search_tvec($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $method);
                        break;
                    case 'secopi':
                        error_log("CP Frontend: Ejecutando consulta SECOPI...");
                        $results = $this->search_secopi($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $method);
                        break;
                    case 'secopii':
                        error_log("CP Frontend: Ejecutando consulta SECOPII...");
                        $results = $this->search_secopii($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $method);
                        break;
                }
                
                $end_time = microtime(true);
                $execution_time = round(($end_time - $start_time), 2);
                $records_count = count($results);
                
                error_log("CP Frontend: Consulta {$next_source} terminada en {$execution_time}s con {$records_count} registros");
                
                // CRÍTICO: Marcar como completada SIEMPRE
                $this->update_source_progress($search_id, $next_source, 'completed', 100, 
                    $records_count > 0 ? "{$records_count} registros encontrados" : __('Sin resultados', 'consulta-procesos'), 
                    $records_count);
                
                // Guardar resultados si hay
                if ($records_count > 0) {
                    $current_progress = get_transient($search_id);
                    if (!isset($current_progress['results'])) {
                        $current_progress['results'] = array();
                    }
                    $current_progress['results'][$next_source] = $results;
                    set_transient($search_id, $current_progress, 600);
                    error_log("CP Frontend: Resultados de {$next_source} guardados");
                }
                
            } catch (Exception $e) {
                error_log("CP Frontend: ERROR en fuente {$next_source}: " . $e->getMessage());
                // CRÍTICO: Marcar como error para que no se reinicie
                $this->update_source_progress($search_id, $next_source, 'error', 100, 'Error: ' . $e->getMessage());
            }
        }
        
        // Obtener progreso actualizado y verificar completitud
        $current_progress = get_transient($search_id);
        
        if (!$current_progress) {
            error_log("CP Frontend: Error obteniendo progreso actualizado");
            return false;
        }
        
        // Verificar si TODAS las fuentes están completadas (completed o error)
        $all_completed = true;
        $total_records = 0;
        $status_summary = array();
        $has_errors = false;
        $error_messages = array();
        
        foreach ($current_progress['sources_progress'] as $source => $source_progress) {
            $status_summary[] = "{$source}: {$source_progress['status']}";
            
            if ($source_progress['status'] !== 'completed' && $source_progress['status'] !== 'error') {
                $all_completed = false;
            }
            if ($source_progress['status'] === 'completed') {
                $total_records += $source_progress['records_found'];
            }
            if ($source_progress['status'] === 'error') {
                $has_errors = true;
                $error_messages[] = $source_progress['message'];
            }
        }
        
        error_log("CP Frontend: Estado actual - " . implode(', ', $status_summary));
        
        if ($all_completed) {
            $execution_time = round((microtime(true) - $search_start_time), 3);
            
            // Registrar búsqueda en logs con resultado final
            if ($has_errors && $total_records === 0) {
                // Todas las fuentes fallaron
                $this->log_search_error('all_sources_failed', implode('; ', $error_messages), 
                    $profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $search_start_time, $execution_time);
            } else {
                // Al menos una fuente funcionó o hay resultados
                $this->log_frontend_search($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, 
                    $total_records, $execution_time, $has_errors ? 'partial_success' : 'success');
            }
            
            $this->update_search_progress($search_id, array(
                'status' => 'completed',
                'total_records' => $total_records,
                'overall_progress' => 100,
                'execution_time' => $execution_time,
                'message' => __('Búsqueda completada', 'consulta-procesos')
            ));
            
            error_log("CP Frontend: *** BÚSQUEDA COMPLETADA *** search_id: {$search_id} con {$total_records} registros totales en {$execution_time}s");
        }
        
        return get_transient($search_id);
    }
    
    /**
     * Obtener configuración de búsquedas - ACTUALIZADO con APIs
     */
    private function get_search_configuration() {
        return array(
            'tvec' => array(
                'active' => get_option('cp_tvec_active', true),
                'method' => get_option('cp_tvec_method', 'database'),
                'api_url_proveedores' => get_option('cp_tvec_api_url_proveedores', ''),
                'api_url_entidades' => get_option('cp_tvec_api_url_entidades', ''),
                'api_date_field' => get_option('cp_tvec_api_date_field', '')
            ),
            'secopi' => array(
                'active' => get_option('cp_secopi_active', true),
                'method' => get_option('cp_secopi_method', 'database'),
                'api_url_proveedores' => get_option('cp_secopi_api_url_proveedores', ''),
                'api_url_entidades' => get_option('cp_secopi_api_url_entidades', ''),
                'api_date_field' => get_option('cp_secopi_api_date_field', '')
            ),
            'secopii' => array(
                'active' => get_option('cp_secopii_active', true),
                'method' => get_option('cp_secopii_method', 'database'),
                'api_url_proveedores' => get_option('cp_secopii_api_url_proveedores', ''),
                'api_url_entidades' => get_option('cp_secopii_api_url_entidades', ''),
                'api_date_field' => get_option('cp_secopii_api_date_field', '')
            )
        );
    }
    
    /**
     * Actualizar progreso de una fuente específica
     */
    private function update_source_progress($search_id, $source, $status, $progress, $message, $records_found = 0) {
        $current_progress = get_transient($search_id);
        
        if ($current_progress === false) {
            error_log("CP Frontend: No se pudo obtener progreso para actualizar fuente {$source}");
            return false;
        }
        
        if (!isset($current_progress['sources_progress'][$source])) {
            error_log("CP Frontend: Fuente {$source} no existe en el progreso");
            return false;
        }
        
        // CRÍTICO: Solo permitir transiciones válidas para evitar regresiones
        $current_status = $current_progress['sources_progress'][$source]['status'];
        
        // Si ya está completada o en error, NO permitir cambios (excepto de pending a cualquier cosa)
        if (($current_status === 'completed' || $current_status === 'error') && $status !== $current_status) {
            error_log("CP Frontend: BLOQUEANDO cambio inválido en {$source}: {$current_status} -> {$status}");
            return false;
        }
        
        // Log del cambio de estado
        if ($current_status !== $status) {
            error_log("CP Frontend: Cambiando estado de {$source}: {$current_status} -> {$status}");
        }
        
        // Actualizar solo esta fuente específica
        $current_progress['sources_progress'][$source]['status'] = $status;
        $current_progress['sources_progress'][$source]['progress'] = $progress;
        $current_progress['sources_progress'][$source]['message'] = $message;
        $current_progress['sources_progress'][$source]['records_found'] = $records_found;
        $current_progress['last_update'] = time();
        
        error_log("CP Frontend: Fuente {$source} actualizada - Status: {$status}, Progress: {$progress}%, Registros: {$records_found}");
        
        // Recalcular progreso general basado en fuentes completadas
        $total_sources = count($current_progress['sources_progress']);
        $completed_sources = 0;
        $total_progress = 0;
        
        foreach ($current_progress['sources_progress'] as $src => $src_progress) {
            if ($src_progress['status'] === 'completed' || $src_progress['status'] === 'error') {
                $completed_sources++;
                $total_progress += 100; // Cada fuente completada contribuye 100%
            } else if ($src_progress['status'] === 'running') {
                $total_progress += $src_progress['progress']; // Progreso parcial
            }
            // Las fuentes 'pending' contribuyen 0%
        }
        
        $overall_progress = $total_sources > 0 ? round($total_progress / $total_sources) : 0;
        
        // Solo actualizar el progreso general si cambió
        if ($current_progress['overall_progress'] !== $overall_progress) {
            $current_progress['overall_progress'] = $overall_progress;
            $current_progress['completed_sources'] = $completed_sources;
            error_log("CP Frontend: Progreso general actualizado: {$overall_progress}% ({$completed_sources}/{$total_sources} fuentes)");
        }
        
        // Guardar progreso actualizado
        $success = set_transient($search_id, $current_progress, 600);
        
        if (!$success) {
            error_log("CP Frontend: ERROR guardando progreso para {$search_id}");
            return false;
        }
        
        return true;
    }
    
    /**
     * Buscar en tabla TVEC
     */
    private function search_tvec($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $method) {
        if ($method === 'api') {
            return $this->search_tvec_api($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
        } else {
            return $this->search_tvec_database($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
        }
    }
    
    /**
     * Buscar en tabla SECOPI
     */
    private function search_secopi($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $method) {
        if ($method === 'api') {
            return $this->search_secopi_api($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
        } else {
            return $this->search_secopi_database($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
        }
    }
    
    /**
     * Buscar en tabla SECOPII
     */
    private function search_secopii($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $method) {
        if ($method === 'api') {
            return $this->search_secopii_api($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
        } else {
            return $this->search_secopii_database($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
        }
    }
    
    /**
     * NUEVO: Buscar TVEC vía API
     */
    private function search_tvec_api($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        $config = $this->get_search_configuration()['tvec'];
        
        // Obtener URL según el perfil
        $api_url = $profile_type === 'entidades' 
            ? $config['api_url_entidades'] 
            : $config['api_url_proveedores'];
            
        if (empty($api_url)) {
            error_log('CP Frontend: URL de API TVEC no configurada para perfil: ' . $profile_type);
            return array();
        }
        
        // Construir URL completa
        $full_url = $api_url . urlencode($numero_documento);
        
        error_log("CP Frontend: Consultando TVEC API - URL: {$full_url}");
        
        try {
            // Realizar petición HTTP
            $response = wp_remote_get($full_url, array(
                'timeout' => 30,
                'headers' => array(
                    'Accept' => 'application/json',
                    'User-Agent' => 'WordPress/ConsultaProcesos'
                )
            ));
            
            if (is_wp_error($response)) {
                error_log('CP Frontend: Error en petición TVEC API - ' . $response->get_error_message());
                return array();
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code !== 200) {
                error_log("CP Frontend: Respuesta HTTP no exitosa de TVEC API - Código: {$response_code}");
                return array();
            }
            
            $data = json_decode($response_body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('CP Frontend: Error decodificando JSON de TVEC API - ' . json_last_error_msg());
                return array();
            }
            
            if (!is_array($data)) {
                error_log('CP Frontend: Respuesta de TVEC API no es un array');
                return array();
            }
            
            // Filtrar por fechas si se especifica campo de fecha
            if (!empty($config['api_date_field']) && !empty($fecha_inicio) && !empty($fecha_fin)) {
                $data = $this->filter_api_results_by_date($data, $config['api_date_field'], $fecha_inicio, $fecha_fin);
            }
            
            // Limitar resultados
            $max_results = get_option('cp_max_results_per_source', 1000);
            if (count($data) > $max_results) {
                $data = array_slice($data, 0, $max_results);
            }
            
            error_log("CP Frontend: TVEC API - " . count($data) . " resultados obtenidos y filtrados");
            
            return $data;
            
        } catch (Exception $e) {
            error_log('CP Frontend: Excepción en TVEC API - ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * NUEVO: Buscar SECOPI vía API
     */
    private function search_secopi_api($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        $config = $this->get_search_configuration()['secopi'];
        
        // Obtener URL según el perfil
        $api_url = $profile_type === 'entidades' 
            ? $config['api_url_entidades'] 
            : $config['api_url_proveedores'];
            
        if (empty($api_url)) {
            error_log('CP Frontend: URL de API SECOPI no configurada para perfil: ' . $profile_type);
            return array();
        }
        
        // Construir URL completa
        $full_url = $api_url . urlencode($numero_documento);
        
        error_log("CP Frontend: Consultando SECOPI API - URL: {$full_url}");
        
        try {
            // Realizar petición HTTP
            $response = wp_remote_get($full_url, array(
                'timeout' => 30,
                'headers' => array(
                    'Accept' => 'application/json',
                    'User-Agent' => 'WordPress/ConsultaProcesos'
                )
            ));
            
            if (is_wp_error($response)) {
                error_log('CP Frontend: Error en petición SECOPI API - ' . $response->get_error_message());
                return array();
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code !== 200) {
                error_log("CP Frontend: Respuesta HTTP no exitosa de SECOPI API - Código: {$response_code}");
                return array();
            }
            
            $data = json_decode($response_body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('CP Frontend: Error decodificando JSON de SECOPI API - ' . json_last_error_msg());
                return array();
            }
            
            if (!is_array($data)) {
                error_log('CP Frontend: Respuesta de SECOPI API no es un array');
                return array();
            }
            
            // Filtrar por fechas si se especifica campo de fecha
            if (!empty($config['api_date_field']) && !empty($fecha_inicio) && !empty($fecha_fin)) {
                $data = $this->filter_api_results_by_date($data, $config['api_date_field'], $fecha_inicio, $fecha_fin);
            }
            
            // Limitar resultados
            $max_results = get_option('cp_max_results_per_source', 1000);
            if (count($data) > $max_results) {
                $data = array_slice($data, 0, $max_results);
            }
            
            error_log("CP Frontend: SECOPI API - " . count($data) . " resultados obtenidos y filtrados");
            
            return $data;
            
        } catch (Exception $e) {
            error_log('CP Frontend: Excepción en SECOPI API - ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * NUEVO: Buscar SECOPII vía API
     */
    private function search_secopii_api($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        $config = $this->get_search_configuration()['secopii'];
        
        // Obtener URL según el perfil
        $api_url = $profile_type === 'entidades' 
            ? $config['api_url_entidades'] 
            : $config['api_url_proveedores'];
            
        if (empty($api_url)) {
            error_log('CP Frontend: URL de API SECOPII no configurada para perfil: ' . $profile_type);
            return array();
        }
        
        // Construir URL completa
        $full_url = $api_url . urlencode($numero_documento);
        
        error_log("CP Frontend: Consultando SECOPII API - URL: {$full_url}");
        
        try {
            // Realizar petición HTTP
            $response = wp_remote_get($full_url, array(
                'timeout' => 30,
                'headers' => array(
                    'Accept' => 'application/json',
                    'User-Agent' => 'WordPress/ConsultaProcesos'
                )
            ));
            
            if (is_wp_error($response)) {
                error_log('CP Frontend: Error en petición SECOPII API - ' . $response->get_error_message());
                return array();
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code !== 200) {
                error_log("CP Frontend: Respuesta HTTP no exitosa de SECOPII API - Código: {$response_code}");
                return array();
            }
            
            $data = json_decode($response_body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('CP Frontend: Error decodificando JSON de SECOPII API - ' . json_last_error_msg());
                return array();
            }
            
            if (!is_array($data)) {
                error_log('CP Frontend: Respuesta de SECOPII API no es un array');
                return array();
            }
            
            // Filtrar por fechas si se especifica campo de fecha
            if (!empty($config['api_date_field']) && !empty($fecha_inicio) && !empty($fecha_fin)) {
                $data = $this->filter_api_results_by_date($data, $config['api_date_field'], $fecha_inicio, $fecha_fin);
            }
            
            // Limitar resultados
            $max_results = get_option('cp_max_results_per_source', 1000);
            if (count($data) > $max_results) {
                $data = array_slice($data, 0, $max_results);
            }
            
            error_log("CP Frontend: SECOPII API - " . count($data) . " resultados obtenidos y filtrados");
            
            return $data;
            
        } catch (Exception $e) {
            error_log('CP Frontend: Excepción en SECOPII API - ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * NUEVO: Filtrar resultados de API por fecha
     */
    private function filter_api_results_by_date($data, $date_field, $fecha_inicio, $fecha_fin) {
        if (empty($date_field) || empty($data)) {
            return $data;
        }
        
        $filtered = array();
        $inicio_timestamp = strtotime($fecha_inicio);
        $fin_timestamp = strtotime($fecha_fin);
        
        foreach ($data as $item) {
            if (!isset($item[$date_field])) {
                continue; // Saltar si no tiene el campo de fecha
            }
            
            $item_date = $item[$date_field];
            
            // Intentar parsear diferentes formatos de fecha
            $item_timestamp = false;
            
            // Formatos comunes: YYYY-MM-DD, DD/MM/YYYY, MM/DD/YYYY, ISO 8601, etc.
            $date_formats = array(
                'Y-m-d',
                'Y-m-d H:i:s',
                'd/m/Y',
                'm/d/Y',
                'Y-m-d\TH:i:s',
                'Y-m-d\TH:i:s\Z',
                'Y-m-d\TH:i:s.u\Z'
            );
            
            foreach ($date_formats as $format) {
                $parsed_date = DateTime::createFromFormat($format, $item_date);
                if ($parsed_date !== false) {
                    $item_timestamp = $parsed_date->getTimestamp();
                    break;
                }
            }
            
            // Si no se pudo parsear con formatos específicos, intentar strtotime
            if ($item_timestamp === false) {
                $item_timestamp = strtotime($item_date);
            }
            
            // Si aún no se pudo parsear, saltar este elemento
            if ($item_timestamp === false) {
                error_log("CP Frontend: No se pudo parsear fecha: {$item_date}");
                continue;
            }
            
            // Verificar si está en el rango
            if ($item_timestamp >= $inicio_timestamp && $item_timestamp <= $fin_timestamp) {
                $filtered[] = $item;
            }
        }
        
        error_log("CP Frontend: Filtrado por fechas - Original: " . count($data) . ", Filtrado: " . count($filtered));
        
        return $filtered;
    }
    
    /**
     * Buscar TVEC en base de datos (código existente sin cambios)
     */
    private function search_tvec_database($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        if (!$this->db) {
            error_log('CP Frontend: Base de datos no disponible');
            return array();
        }
        
        $connection_result = $this->db->connect();
        if (!$connection_result['success']) {
            error_log('CP Frontend: Error de conexión TVEC - ' . $connection_result['error']);
            return array();
        }
        
        $connection = $connection_result['connection'];
        $method = $connection_result['method'];
        
        try {
            // PRIORIDAD: Usar stored procedures
            if ($this->stored_procedures_available()) {
                if ($profile_type === 'entidades') {
                    return $this->execute_stored_procedure($connection, $method, 'IDI.ConsultaContratosPorEntidad_TVEC', 
                        array($numero_documento, $fecha_inicio, $fecha_fin), 'TVEC Entidades SP');
                } else {
                    return $this->execute_stored_procedure($connection, $method, 'IDI.ConsultaContratosPorProveedor_TVEC', 
                        array($numero_documento, $fecha_inicio, $fecha_fin), 'TVEC Proveedores SP');
                }
            } else {
                // Fallback a consulta SQL simplificada
                return $this->query_tvec_fallback($connection, $method, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
            }
        } catch (Exception $e) {
            error_log('CP Frontend: Error en consulta TVEC - ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Buscar SECOPI en base de datos (código existente sin cambios)
     */
    private function search_secopi_database($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        if (!$this->db) {
            error_log('CP Frontend: Base de datos no disponible');
            return array();
        }
        
        $connection_result = $this->db->connect();
        if (!$connection_result['success']) {
            error_log('CP Frontend: Error de conexión SECOPI - ' . $connection_result['error']);
            return array();
        }
        
        $connection = $connection_result['connection'];
        $method = $connection_result['method'];
        
        try {
            // PRIORIDAD: Usar stored procedures
            if ($this->stored_procedures_available()) {
                if ($profile_type === 'entidades') {
                    return $this->execute_stored_procedure($connection, $method, 'IDI.ConsultaContratosPorEntidad_SECOPI', 
                        array($numero_documento, $fecha_inicio, $fecha_fin), 'SECOPI Entidades SP');
                } else {
                    return $this->execute_stored_procedure($connection, $method, 'IDI.ConsultaContratosPorProveedor_SECOPI', 
                        array($numero_documento, $fecha_inicio, $fecha_fin), 'SECOPI Proveedores SP');
                }
            } else {
                // Fallback a consulta SQL simplificada
                return $this->query_secopi_fallback($connection, $method, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
            }
        } catch (Exception $e) {
            error_log('CP Frontend: Error en consulta SECOPI - ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Buscar SECOPII en base de datos (código existente sin cambios)
     */
    private function search_secopii_database($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        if (!$this->db) {
            error_log('CP Frontend: Base de datos no disponible');
            return array();
        }
        
        $connection_result = $this->db->connect();
        if (!$connection_result['success']) {
            error_log('CP Frontend: Error de conexión SECOPII - ' . $connection_result['error']);
            return array();
        }
        
        $connection = $connection_result['connection'];
        $method = $connection_result['method'];
        
        try {
            // PRIORIDAD: Usar stored procedures
            if ($this->stored_procedures_available()) {
                if ($profile_type === 'entidades') {
                    // Para entidades usa @NIT como primer parámetro
                    return $this->execute_stored_procedure($connection, $method, 'IDI.ConsultaContratosPorEntidad_SECOPII', 
                        array($numero_documento, $fecha_inicio, $fecha_fin), 'SECOPII Entidades SP');
                } else {
                    // Para proveedores usa @DOC como primer parámetro
                    return $this->execute_stored_procedure($connection, $method, 'IDI.ConsultaContratosPorProveedor_SECOPII', 
                        array($numero_documento, $fecha_inicio, $fecha_fin), 'SECOPII Proveedores SP');
                }
            } else {
                // Fallback a consulta SQL simplificada
                return $this->query_secopii_fallback($connection, $method, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
            }
        } catch (Exception $e) {
            error_log('CP Frontend: Error en consulta SECOPII - ' . $e->getMessage());
            return array();
        }
    }
    
    // RESTO DE MÉTODOS SIN CAMBIOS (execute_stored_procedure, stored_procedures_available, query_*_fallback, etc.)
    
    /**
     * Ejecutar stored procedure - COMPLETAMENTE CORREGIDO
     */
    private function execute_stored_procedure($connection, $method, $procedure_name, $params, $source_name) {
        try {
            if (strpos($method, 'PDO') !== false) {
                // Para PDO - Agregar SET NOCOUNT ON y manejar múltiples resultsets
                $sql = "SET NOCOUNT ON; EXEC {$procedure_name} ?, ?, ?";
                
                $stmt = $connection->prepare($sql);
                $success = $stmt->execute($params);
                
                if (!$success) {
                    $errorInfo = $stmt->errorInfo();
                    throw new Exception("Error PDO: " . $errorInfo[2]);
                }
                
                $results = array();
                
                // Buscar en todos los conjuntos de resultados
                do {
                    if ($stmt->columnCount() > 0) {
                        $resultSet = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        if (!empty($resultSet)) {
                            $results = $resultSet;
                            break; // Usar el primer conjunto no vacío
                        }
                    }
                } while ($stmt->nextRowset());
                
                return $results;
                
            } else {
                // Para SQLSRV - Agregar SET NOCOUNT ON y manejar múltiples resultsets
                $sql = "SET NOCOUNT ON; EXEC {$procedure_name} ?, ?, ?";
                
                $stmt = sqlsrv_prepare($connection, $sql, $params);
                
                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    $error_msg = 'Error preparando SP: ';
                    foreach ($errors as $error) {
                        $error_msg .= "[{$error['SQLSTATE']}] {$error['message']} ";
                    }
                    throw new Exception($error_msg);
                }
                
                $success = sqlsrv_execute($stmt);
                
                if ($success === false) {
                    $errors = sqlsrv_errors();
                    $error_msg = 'Error ejecutando SP: ';
                    foreach ($errors as $error) {
                        $error_msg .= "[{$error['SQLSTATE']}] {$error['message']} ";
                    }
                    throw new Exception($error_msg);
                }
                
                $results = array();
                
                // Buscar en todos los conjuntos de resultados
                do {
                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        // Convertir objetos DateTime a strings
                        foreach ($row as $key => $value) {
                            if ($value instanceof DateTime) {
                                $row[$key] = $value->format('Y-m-d H:i:s');
                            }
                        }
                        $results[] = $row;
                    }
                    
                    if (!empty($results)) {
                        break; // Usar el primer conjunto no vacío
                    }
                    
                } while (sqlsrv_next_result($stmt));
                
                sqlsrv_free_stmt($stmt);
                return $results;
            }
        } catch (Exception $e) {
            return array();
        }
    }
    
    /**
     * Verificar si los stored procedures están disponibles
     */
    private function stored_procedures_available() {
        // Verificar configuración
        if (!get_option('cp_use_stored_procedures', true)) {
            error_log("CP Frontend: Stored procedures deshabilitados en configuración");
            return false;
        }
        
        // Verificar si podemos conectar a la base de datos
        if (!$this->db) {
            error_log("CP Frontend: DB class no disponible");
            return false;
        }
        
        $connection_result = $this->db->connect();
        if (!$connection_result['success']) {
            error_log("CP Frontend: No se puede conectar para verificar SPs");
            return false;
        }
        
        $connection = $connection_result['connection'];
        $method = $connection_result['method'];
        
        try {
            // Probar un stored procedure específico para verificar disponibilidad
            $test_procedures = array(
                'IDI.ConsultaContratosPorProveedor_SECOPII',
                'IDI.ConsultaContratosPorEntidad_SECOPII'
            );
            
            $available_count = 0;
            
            if (strpos($method, 'PDO') !== false) {
                foreach ($test_procedures as $proc_name) {
                    $stmt = $connection->prepare("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_NAME = ? AND ROUTINE_SCHEMA = 'IDI'");
                    $stmt->execute(array(str_replace('IDI.', '', $proc_name)));
                    $result = $stmt->fetch();
                    if ($result['count'] > 0) {
                        $available_count++;
                    }
                }
            } else {
                foreach ($test_procedures as $proc_name) {
                    $stmt = sqlsrv_query($connection, "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_NAME = ? AND ROUTINE_SCHEMA = 'IDI'", 
                        array(str_replace('IDI.', '', $proc_name)));
                    if ($stmt !== false) {
                        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                        if ($row && $row['count'] > 0) {
                            $available_count++;
                        }
                        sqlsrv_free_stmt($stmt);
                    }
                }
            }
            
            $sp_available = $available_count >= 2;
            error_log("CP Frontend: Stored procedures disponibles: " . ($sp_available ? 'SÍ' : 'NO') . " (encontrados: {$available_count}/2)");
            
            return $sp_available;
            
        } catch (Exception $e) {
            error_log("CP Frontend: Error verificando stored procedures - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Consultas de fallback SQL simplificadas - TVEC
     */
    private function query_tvec_fallback($connection, $method, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        error_log("CP Frontend: Usando fallback SQL para TVEC");
        
        if ($profile_type === 'entidades') {
            $sql = "SELECT TOP 1000 * FROM TVEC.V_Ordenes WHERE Fecha BETWEEN ? AND ? AND ID_Entidad LIKE ? ORDER BY Fecha DESC";
            $params = array($fecha_inicio, $fecha_fin, '%' . $numero_documento . '%');
        } else {
            $sql = "SELECT TOP 1000 * FROM TVEC.V_Ordenes WHERE Fecha BETWEEN ? AND ? AND ID_Proveedor LIKE ? ORDER BY Fecha DESC";
            $params = array($fecha_inicio, $fecha_fin, '%' . $numero_documento . '%');
        }
        
        return $this->execute_query_with_error_handling($connection, $method, $sql, $params, 'TVEC Fallback');
    }
    
    /**
     * Consultas de fallback SQL simplificadas - SECOPI  
     */
    private function query_secopi_fallback($connection, $method, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        error_log("CP Frontend: Usando fallback SQL para SECOPI");
        
        if ($profile_type === 'entidades') {
            $sql = "SELECT TOP 1000 * FROM SECOPI.T_PTC_Adjudicaciones WHERE FECHA_FIRMA_CONTRATO BETWEEN ? AND ? AND ID_ADJUDICACION LIKE ? ORDER BY FECHA_FIRMA_CONTRATO DESC";
            $params = array($fecha_inicio, $fecha_fin, '%' . $numero_documento . '%');
        } else {
            $sql = "SELECT TOP 1000 * FROM SECOPI.T_PTC_Adjudicaciones WHERE FECHA_FIRMA_CONTRATO BETWEEN ? AND ? AND NUMERO_DOC LIKE ? ORDER BY FECHA_FIRMA_CONTRATO DESC";
            $params = array($fecha_inicio, $fecha_fin, '%' . $numero_documento . '%');
        }
        
        return $this->execute_query_with_error_handling($connection, $method, $sql, $params, 'SECOPI Fallback');
    }
    
    /**
     * Consultas de fallback SQL simplificadas - SECOPII
     */
    private function query_secopii_fallback($connection, $method, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        error_log("CP Frontend: Usando fallback SQL para SECOPII");
        
        if ($profile_type === 'entidades') {
            $sql = "SELECT TOP 1000 * FROM SECOPII.V_HistoricoContratos_Depurado WHERE AprovalDate BETWEEN ? AND ? AND [Código Entidad] LIKE ? ORDER BY AprovalDate DESC";
            $params = array($fecha_inicio, $fecha_fin, '%' . $numero_documento . '%');
        } else {
            $sql = "SELECT TOP 1000 * FROM SECOPII.V_HistoricoContratos_Depurado WHERE AprovalDate BETWEEN ? AND ? AND [Documento Proveedor] LIKE ? ORDER BY AprovalDate DESC";
            $params = array($fecha_inicio, $fecha_fin, '%' . $numero_documento . '%');
        }
        
        return $this->execute_query_with_error_handling($connection, $method, $sql, $params, 'SECOPII Fallback');
    }
    
    /**
     * Ejecutar consulta con manejo de errores
     */
    private function execute_query_with_error_handling($connection, $method, $sql, $params, $source_name) {
        try {
            error_log("CP Frontend: Ejecutando {$source_name} - SQL: {$sql}");
            error_log("CP Frontend: Parámetros: " . json_encode($params));
            
            if (strpos($method, 'PDO') !== false) {
                $stmt = $connection->prepare($sql);
                $success = $stmt->execute($params);
                
                if (!$success) {
                    $errorInfo = $stmt->errorInfo();
                    throw new Exception("Error PDO: " . $errorInfo[2]);
                }
                
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("CP Frontend: {$source_name} - " . count($results) . " registros encontrados via PDO");
                return $results;
            } else {
                $stmt = sqlsrv_query($connection, $sql, $params);
                
                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    $error_msg = 'Error en consulta: ';
                    foreach ($errors as $error) {
                        $error_msg .= "[{$error['SQLSTATE']}] {$error['message']} ";
                    }
                    throw new Exception($error_msg);
                }
                
                $results = array();
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    // Convertir objetos DateTime a strings
                    foreach ($row as $key => $value) {
                        if ($value instanceof DateTime) {
                            $row[$key] = $value->format('Y-m-d H:i:s');
                        }
                    }
                    $results[] = $row;
                }
                
                sqlsrv_free_stmt($stmt);
                
                error_log("CP Frontend: {$source_name} - " . count($results) . " registros encontrados via SQLSRV");
                return $results;
            }
        } catch (Exception $e) {
            error_log("CP Frontend: Error en {$source_name} - " . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Registrar búsqueda exitosa en el log del frontend
     */
    private function log_frontend_search($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $results_count, $execution_time = null, $status = 'success') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        // Verificar que la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            error_log('CP Frontend: Tabla de logs no existe: ' . $table_name);
            return false;
        }
        
        $search_sources = array();
        $search_config = $this->get_search_configuration();
        
        foreach ($search_config as $source => $config) {
            if ($config['active']) {
                $search_sources[] = $source . ':' . $config['method'];
            }
        }
        
        $log_data = array(
            'session_id' => session_id() ?: 'no-session',
            'profile_type' => $profile_type,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'numero_documento' => $numero_documento,
            'search_sources' => implode(',', $search_sources),
            'status' => $status,
            'error_message' => null,
            'results_found' => intval($results_count),
            'execution_time' => $execution_time,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table_name, $log_data, array(
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s'
        ));
        
        if ($result === false) {
            error_log('CP Frontend: Error insertando log de búsqueda exitosa: ' . $wpdb->last_error);
            return false;
        }
        
        error_log("CP Frontend: Log de búsqueda registrado - ID: {$wpdb->insert_id}, Status: {$status}, Resultados: {$results_count}");
        return $wpdb->insert_id;
    }
    
    /**
     * Registrar error en el log del frontend
     */
    private function log_search_error($error_type, $error_message, $profile_type = '', $fecha_inicio = '', $fecha_fin = '', $numero_documento = '', $start_time = null, $execution_time = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        // Verificar que la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            error_log('CP Frontend: Tabla de logs no existe para error: ' . $table_name);
            return false;
        }
        
        // Calcular tiempo de ejecución si se proporcionó start_time
        if ($execution_time === null && $start_time !== null) {
            $execution_time = round((microtime(true) - $start_time), 3);
        }
        
        $search_sources = array();
        if (!empty($profile_type)) {
            $search_config = $this->get_search_configuration();
            foreach ($search_config as $source => $config) {
                if ($config['active']) {
                    $search_sources[] = $source . ':' . $config['method'];
                }
            }
        }
        
        $log_data = array(
            'session_id' => session_id() ?: 'no-session',
            'profile_type' => $profile_type ?: 'unknown',
            'fecha_inicio' => $fecha_inicio ?: '0000-00-00',
            'fecha_fin' => $fecha_fin ?: '0000-00-00', 
            'numero_documento' => $numero_documento ?: '',
            'search_sources' => implode(',', $search_sources),
            'status' => 'error',
            'error_message' => $error_type . ': ' . $error_message,
            'results_found' => 0,
            'execution_time' => $execution_time,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table_name, $log_data, array(
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s'
        ));
        
        if ($result === false) {
            error_log('CP Frontend: Error insertando log de error: ' . $wpdb->last_error);
            return false;
        }
        
        error_log("CP Frontend: Log de error registrado - ID: {$wpdb->insert_id}, Tipo: {$error_type}, Mensaje: {$error_message}");
        return $wpdb->insert_id;
    }
    
    /**
     * Obtener IP del cliente
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Validar rango de fechas
     */
    private function validate_date_range($fecha_inicio, $fecha_fin) {
        $inicio = DateTime::createFromFormat('Y-m-d', $fecha_inicio);
        $fin = DateTime::createFromFormat('Y-m-d', $fecha_fin);
        
        if (!$inicio || !$fin) {
            return false;
        }
        
        // La fecha de inicio no puede ser mayor que la fecha de fin
        if ($inicio > $fin) {
            return false;
        }
        
        // No permitir fechas futuras
        $hoy = new DateTime();
        if ($fin > $hoy) {
            return false;
        }
        
        // No permitir rangos muy amplios (máximo 5 años)
        $diff = $inicio->diff($fin);
        if ($diff->y >= 5 && ($diff->m > 0 || $diff->d > 0)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Sistema de caché para consultas
     */
    private function get_cached_results($cache_key) {
        $cache_duration = get_option('cp_cache_duration', 300);
        return get_transient($cache_key);
    }
    
    private function set_cached_results($cache_key, $results) {
        $cache_duration = get_option('cp_cache_duration', 300);
        if ($cache_duration > 0) {
            set_transient($cache_key, $results, $cache_duration);
        }
    }
    
    /**
     * Limpiar caché manualmente
     */
    public function clear_search_cache() {
        global $wpdb;
        
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cp_query_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_cp_query_%'");
        
        return true;
    }
    
    /**
     * Obtener estadísticas de uso del caché
     */
    public function get_cache_stats() {
        global $wpdb;
        
        $cache_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_cp_query_%'");
        
        return array(
            'cached_queries' => intval($cache_count),
            'cache_enabled' => get_option('cp_enable_cache', true),
            'cache_duration' => get_option('cp_cache_duration', 300)
        );
    }
    
    /**
     * Obtener estadísticas de logs para el admin
     */
    public function get_logs_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array(
                'total_searches' => 0,
                'successful_searches' => 0,
                'failed_searches' => 0,
                'last_search' => null
            );
        }
        
        $stats = array();
        $stats['total_searches'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table_name}"));
        $stats['successful_searches'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'success'"));
        $stats['failed_searches'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'error'"));
        $stats['last_search'] = $wpdb->get_var("SELECT created_at FROM {$table_name} ORDER BY created_at DESC LIMIT 1");
        
        return $stats;
    }

    /**
     * AJAX: Exportar resultados del frontend a Excel
     */
    public function ajax_auto_export_frontend_results() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cp_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Token de seguridad inválido'));
        }
        
        // Obtener search_id para recuperar resultados del transient
        $search_id = sanitize_text_field($_POST['search_id'] ?? '');
        
        if (empty($search_id)) {
            wp_send_json_error(array('message' => 'ID de búsqueda requerido'));
        }
        
        // Recuperar datos de la búsqueda completada
        $search_data = get_transient($search_id);
        
        if (!$search_data || $search_data['status'] !== 'completed') {
            wp_send_json_error(array('message' => 'Búsqueda no encontrada o no completada'));
        }
        
        // Verificar que hay resultados
        if (empty($search_data['results']) || $search_data['total_records'] === 0) {
            wp_send_json_error(array('message' => 'No hay resultados para exportar'));
        }
        
        try {
            // Preparar datos para exportación
            $export_ready_data = $this->prepare_frontend_data_for_export(
                $search_data['results'], 
                $search_data['search_params']
            );
            
            // Generar nombre de archivo descriptivo
            $filename = $this->generate_export_filename(
                $search_data['search_params']['profile_type'], 
                $search_data['search_params'], 
                'excel'
            );
            
            // Obtener instancia de la clase de exportación
            $exporter = CP_Export::get_instance();
            
            // Validar datos antes de exportar
            $validation = $exporter->validate_export_data($export_ready_data, 50000);
            if (!$validation['valid']) {
                wp_send_json_error(array('message' => $validation['error']));
            }
            
            // Exportar datos
            $export_result = $exporter->export_data($export_ready_data, 'excel', $filename);
            
            if ($export_result['success']) {
                // Registrar exportación para estadísticas
                $exporter->record_export('excel');
                
                // Log de exportación automática exitosa
                error_log("CP Frontend: Auto-exportación exitosa - {$filename}, " . count($export_ready_data) . " registros");
                
                wp_send_json_success(array(
                    'message' => 'Descarga automática iniciada',
                    'download_url' => $export_result['download_url'],
                    'filename' => $export_result['filename'],
                    'file_size' => $export_result['file_size'],
                    'records_count' => $export_result['records_count'],
                    'download_token' => $export_result['download_token'],
                    'auto_download' => true
                ));
            } else {
                error_log("CP Frontend: Error en auto-exportación - " . $export_result['error']);
                wp_send_json_error(array('message' => $export_result['error']));
            }
            
        } catch (Exception $e) {
            error_log('CP Frontend Auto Export Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error interno del servidor: ' . $e->getMessage()));
        }
    }

    /**
     * AJAX: Exportar resultados del frontend a Excel (DESCARGA MANUAL)
     */
    public function ajax_export_frontend_results() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cp_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Token de seguridad inválido'));
        }
        
        // Obtener y sanitizar datos
        $export_data = $_POST['export_data'] ?? '';
        $format = sanitize_text_field($_POST['format'] ?? 'excel');
        $profile_type = sanitize_text_field($_POST['profile_type'] ?? '');
        $search_params = $_POST['search_params'] ?? array();
        
        if (empty($export_data)) {
            wp_send_json_error(array('message' => 'No hay datos para exportar'));
        }
        
        // Decodificar datos JSON
        $results_data = json_decode(stripslashes($export_data), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => 'Error decodificando datos de exportación'));
        }
        
        try {
            // Preparar datos para exportación
            $export_ready_data = $this->prepare_frontend_data_for_export($results_data, $search_params);
            
            // Generar nombre de archivo descriptivo
            $filename = $this->generate_export_filename($profile_type, $search_params, $format);
            
            // Obtener instancia de la clase de exportación
            $exporter = CP_Export::get_instance();
            
            // Validar datos antes de exportar
            $validation = $exporter->validate_export_data($export_ready_data, 50000); // Máximo 50k registros
            if (!$validation['valid']) {
                wp_send_json_error(array('message' => $validation['error']));
            }
            
            // Exportar datos
            $export_result = $exporter->export_data($export_ready_data, $format, $filename);
            
            if ($export_result['success']) {
                // Registrar exportación para estadísticas
                $exporter->record_export($format);
                
                // Log de exportación exitosa
                error_log("CP Frontend: Exportación manual exitosa - {$filename}, " . count($export_ready_data) . " registros");
                
                wp_send_json_success(array(
                    'message' => 'Exportación completada exitosamente',
                    'download_url' => $export_result['download_url'],
                    'filename' => $export_result['filename'],
                    'file_size' => $export_result['file_size'],
                    'records_count' => $export_result['records_count'],
                    'download_token' => $export_result['download_token']
                ));
            } else {
                error_log("CP Frontend: Error en exportación manual - " . $export_result['error']);
                wp_send_json_error(array('message' => $export_result['error']));
            }
            
        } catch (Exception $e) {
            error_log('CP Frontend Manual Export Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error interno del servidor: ' . $e->getMessage()));
        }
    }
    
    /**
     * Preparar datos del frontend para exportación
     */
    private function prepare_frontend_data_for_export($results_data, $search_params) {
        $prepared_data = array();
        $row_counter = 1;
        
        // Agregar información de búsqueda como metadatos en las primeras filas
        $search_info = array(
            'INFORMACIÓN DE BÚSQUEDA' => '',
            'Perfil' => $search_params['profile_type'] ?? '',
            'Fecha Inicio' => $search_params['fecha_inicio'] ?? '',
            'Fecha Fin' => $search_params['fecha_fin'] ?? '',
            'Número Documento' => $search_params['numero_documento'] ?? '',
            'Fecha Exportación' => current_time('Y-m-d H:i:s'),
            'Total Fuentes' => count($results_data),
            '' => ''
        );
        
        $prepared_data[] = $search_info;
        
        // Procesar cada fuente de datos
        foreach ($results_data as $source => $records) {
            if (!is_array($records) || empty($records)) {
                continue;
            }
            
            // Agregar separador de fuente
            $source_header = array(
                'FUENTE' => strtoupper($this->get_source_title($source)),
                'Registros' => count($records),
                'Fuente_Datos' => $source
            );
            
            // Rellenar columnas faltantes con valores vacíos
            $first_record = $records[0];
            $all_columns = is_array($first_record) ? array_keys($first_record) : array_keys(get_object_vars($first_record));
            
            foreach ($all_columns as $col) {
                if (!isset($source_header[$col])) {
                    $source_header[$col] = '';
                }
            }
            
            $prepared_data[] = $source_header;
            
            // Agregar encabezados de columnas
            if (!empty($records)) {
                $headers = is_array($first_record) ? array_keys($first_record) : array_keys(get_object_vars($first_record));
                $header_row = array();
                foreach ($headers as $header) {
                    $header_row[$header] = strtoupper(str_replace('_', ' ', $header));
                }
                $prepared_data[] = $header_row;
            }
            
            // Agregar registros de datos
            foreach ($records as $record) {
                if (is_object($record)) {
                    $record = get_object_vars($record);
                }
                
                // Limpiar y formatear datos
                $clean_record = array();
                foreach ($record as $key => $value) {
                    // Formatear valores especiales
                    if (is_null($value)) {
                        $clean_record[$key] = '';
                    } elseif (is_bool($value)) {
                        $clean_record[$key] = $value ? 'SÍ' : 'NO';
                    } elseif (is_array($value) || is_object($value)) {
                        $clean_record[$key] = json_encode($value);
                    } elseif (is_string($value) && strlen($value) > 1000) {
                        // Truncar textos muy largos
                        $clean_record[$key] = substr($value, 0, 1000) . '...';
                    } else {
                        $clean_record[$key] = $value;
                    }
                }
                
                // Agregar número de fila
                $clean_record['NUM_FILA'] = $row_counter++;
                
                $prepared_data[] = $clean_record;
            }
            
            // Agregar fila vacía como separador
            $prepared_data[] = array();
        }
        
        return $prepared_data;
    }
    
    /**
     * Generar nombre de archivo para exportación
     */
    private function generate_export_filename($profile_type, $search_params, $format) {
        $date_str = date('Y-m-d_H-i-s');
        $profile_str = $profile_type ? ucfirst($profile_type) : 'Consulta';
        
        $documento = '';
        if (!empty($search_params['numero_documento'])) {
            $documento = '_Doc-' . substr($search_params['numero_documento'], 0, 8);
        }
        
        $fecha_range = '';
        if (!empty($search_params['fecha_inicio']) && !empty($search_params['fecha_fin'])) {
            $fecha_range = '_' . $search_params['fecha_inicio'] . '_a_' . $search_params['fecha_fin'];
        }
        
        $extension = ($format === 'excel') ? 'xlsx' : $format;
        
        return "ConsultaProcesos_{$profile_str}{$documento}{$fecha_range}_{$date_str}.{$extension}";
    }
    
    /**
     * Obtener título de fuente
     */
    private function get_source_title($source) {
        $titles = array(
            'tvec' => 'TVEC - Catálogo de Proveedores',
            'secopi' => 'SECOPI - Sistema de Información',
            'secopii' => 'SECOPII - Sistema Extendido'
        );
        
        return $titles[$source] ?? strtoupper($source);
    }

    /**
     * AJAX: Exportar PDF sin resultados (descarga manual)
     */
    public function ajax_export_no_results_pdf() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cp_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Token de seguridad inválido'));
        }
        
        // Obtener y sanitizar parámetros de búsqueda
        $search_params = $_POST['search_params'] ?? array();
        
        // Sanitizar parámetros
        $clean_params = array(
            'profile_type' => sanitize_text_field($search_params['profile_type'] ?? ''),
            'fecha_inicio' => sanitize_text_field($search_params['fecha_inicio'] ?? ''),
            'fecha_fin' => sanitize_text_field($search_params['fecha_fin'] ?? ''),
            'numero_documento' => sanitize_text_field($search_params['numero_documento'] ?? '')
        );
        
        if (empty($clean_params['profile_type']) || empty($clean_params['numero_documento'])) {
            wp_send_json_error(array('message' => 'Faltan parámetros de búsqueda requeridos'));
        }
        
        try {
            // Obtener instancia de la clase de exportación
            $exporter = CP_Export::get_instance();
            
            // Verificar que dompdf esté disponible
            if (!$exporter->is_dompdf_available()) {
                wp_send_json_error(array('message' => 'PDF no está disponible. Librería dompdf no encontrada.'));
            }
            
            // Exportar PDF sin resultados
            $export_result = $exporter->export_no_results_pdf($clean_params);
            
            if ($export_result['success']) {
                // Log de exportación manual exitosa
                error_log("CP Frontend: Exportación manual de PDF sin resultados exitosa - {$export_result['filename']}");
                
                wp_send_json_success(array(
                    'message' => 'PDF generado exitosamente',
                    'download_url' => $export_result['download_url'],
                    'filename' => $export_result['filename'],
                    'file_size' => $export_result['file_size'],
                    'download_token' => $export_result['download_token'],
                    'document_type' => 'PDF'
                ));
            } else {
                error_log("CP Frontend: Error en exportación manual de PDF sin resultados - " . $export_result['error']);
                wp_send_json_error(array('message' => $export_result['error']));
            }
            
        } catch (Exception $e) {
            error_log('CP Frontend Manual PDF Export Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error interno del servidor: ' . $e->getMessage()));
        }
    }
    
    /**
     * AJAX: Descarga automática de PDF sin resultados
     */
    public function ajax_auto_export_no_results_pdf() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cp_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Token de seguridad inválido'));
        }
        
        // Obtener parámetros de búsqueda
        $search_params = $_POST['search_params'] ?? array();
        
        // Sanitizar parámetros
        $clean_params = array(
            'profile_type' => sanitize_text_field($search_params['profile_type'] ?? ''),
            'fecha_inicio' => sanitize_text_field($search_params['fecha_inicio'] ?? ''),
            'fecha_fin' => sanitize_text_field($search_params['fecha_fin'] ?? ''),
            'numero_documento' => sanitize_text_field($search_params['numero_documento'] ?? '')
        );
        
        if (empty($clean_params['profile_type']) || empty($clean_params['numero_documento'])) {
            wp_send_json_error(array('message' => 'Faltan parámetros de búsqueda requeridos'));
        }
        
        try {
            // Obtener instancia de la clase de exportación
            $exporter = CP_Export::get_instance();
            
            // Verificar que dompdf esté disponible
            if (!$exporter->is_dompdf_available()) {
                wp_send_json_error(array('message' => 'PDF no está disponible'));
            }
            
            // Exportar PDF sin resultados
            $export_result = $exporter->export_no_results_pdf($clean_params);
            
            if ($export_result['success']) {
                // Log de exportación automática exitosa
                error_log("CP Frontend: Exportación automática de PDF sin resultados exitosa - {$export_result['filename']}");
                
                wp_send_json_success(array(
                    'message' => 'Descarga automática de PDF iniciada',
                    'download_url' => $export_result['download_url'],
                    'filename' => $export_result['filename'],
                    'file_size' => $export_result['file_size'],
                    'download_token' => $export_result['download_token'],
                    'document_type' => 'PDF',
                    'auto_download' => true
                ));
            } else {
                error_log("CP Frontend: Error en exportación automática de PDF sin resultados - " . $export_result['error']);
                wp_send_json_error(array('message' => $export_result['error']));
            }
            
        } catch (Exception $e) {
            error_log('CP Frontend Auto PDF Export Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error interno del servidor: ' . $e->getMessage()));
        }
    }
}