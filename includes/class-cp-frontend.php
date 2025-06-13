<?php
/**
 * Clase para manejar la funcionalidad del frontend del plugin
 * 
 * Archivo: includes/class-cp-frontend.php
 * CORREGIDO: Ahora usa stored procedures como método principal
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
                    'fill_dates' => __('Debe completar las fechas', 'consulta-procesos')
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
            <h3><?php _e('Seleccione su Perfil', 'consulta-procesos'); ?></h3>
            
            <div class="cp-profile-options">
                <div class="cp-profile-option">
                    <label class="cp-radio-card">
                        <input type="radio" name="profile_type" value="entidades">
                        <div class="cp-radio-content">
                            <div class="cp-radio-icon">
                                <span class="dashicons dashicons-building"></span>
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
                                <span class="dashicons dashicons-businessman"></span>
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
            <h3><?php _e('Formulario de Búsqueda', 'consulta-procesos'); ?></h3>
            
            <form id="cp-search-form" class="cp-search-form">
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
                        <input type="number" id="cp-numero-documento" name="numero_documento" placeholder="<?php _e('Ingrese el número', 'consulta-procesos'); ?>" required>
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
     * AJAX: Procesar búsqueda del formulario
     */
    public function ajax_process_search_form() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cp_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Token de seguridad inválido'));
        }
        
        // Obtener y sanitizar datos
        $profile_type = sanitize_text_field($_POST['profile_type'] ?? '');
        $fecha_inicio = sanitize_text_field($_POST['fecha_inicio'] ?? '');
        $fecha_fin = sanitize_text_field($_POST['fecha_fin'] ?? '');
        $numero_documento = sanitize_text_field($_POST['numero_documento'] ?? '');
        
        // Validar datos
        if (empty($profile_type) || empty($fecha_inicio) || empty($fecha_fin) || empty($numero_documento)) {
            wp_send_json_error(array('message' => 'Faltan datos requeridos'));
        }
        
        // Validar fechas
        if (!$this->validate_date_range($fecha_inicio, $fecha_fin)) {
            wp_send_json_error(array('message' => 'Rango de fechas inválido'));
        }
        
        try {
            // Realizar búsquedas según configuración
            $results = $this->perform_searches($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
            
            if (empty($results)) {
                wp_send_json_success(array(
                    'has_results' => false,
                    'message' => __('No se encontraron resultados para los criterios especificados', 'consulta-procesos')
                ));
            } else {
                wp_send_json_success(array(
                    'has_results' => true,
                    'results' => $results,
                    'total_records' => $this->count_total_records($results)
                ));
            }
            
        } catch (Exception $e) {
            error_log('CP Frontend Search Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error interno del servidor'));
        }
    }
    
    /**
     * Obtener configuración de búsquedas
     */
    private function get_search_configuration() {
        return array(
            'tvec' => array(
                'active' => get_option('cp_tvec_active', true),
                'method' => get_option('cp_tvec_method', 'database')
            ),
            'secopi' => array(
                'active' => get_option('cp_secopi_active', true),
                'method' => get_option('cp_secopi_method', 'database')
            ),
            'secopii' => array(
                'active' => get_option('cp_secopii_active', true),
                'method' => get_option('cp_secopii_method', 'database')
            )
        );
    }
    
    /**
     * Realizar búsquedas principales
     */
    private function perform_searches($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        $results = array();
        $total_results = 0;
        
        // Obtener configuración de búsquedas activas
        $search_config = $this->get_search_configuration();
        
        // TVEC
        if ($search_config['tvec']['active']) {
            $tvec_results = $this->search_tvec($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $search_config['tvec']['method']);
            if (!empty($tvec_results)) {
                $results['tvec'] = $tvec_results;
                $total_results += count($tvec_results);
            }
        }
        
        // SECOPI
        if ($search_config['secopi']['active']) {
            $secopi_results = $this->search_secopi($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $search_config['secopi']['method']);
            if (!empty($secopi_results)) {
                $results['secopi'] = $secopi_results;
                $total_results += count($secopi_results);
            }
        }
        
        // SECOPII
        if ($search_config['secopii']['active']) {
            $secopii_results = $this->search_secopii($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $search_config['secopii']['method']);
            if (!empty($secopii_results)) {
                $results['secopii'] = $secopii_results;
                $total_results += count($secopii_results);
            }
        }
        
        // Registrar búsqueda en el log
        $this->log_frontend_search($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $total_results);
        
        return $results;
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
     * Buscar TVEC en base de datos - CORREGIDO PARA USAR STORED PROCEDURES
     */
    private function search_tvec_database($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        if (!$this->db) {
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
     * Buscar SECOPI en base de datos - CORREGIDO PARA USAR STORED PROCEDURES
     */
    private function search_secopi_database($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        if (!$this->db) {
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
     * Buscar SECOPII en base de datos - CORREGIDO PARA USAR STORED PROCEDURES
     */
    private function search_secopii_database($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        if (!$this->db) {
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
                    return $this->execute_stored_procedure($connection, $method, 'IDI.ConsultaContratosPorEntidad_SECOPII', 
                        array($numero_documento, $fecha_inicio, $fecha_fin), 'SECOPII Entidades SP');
                } else {
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
    
    /**
     * Ejecutar stored procedure - MEJORADO
     */
    private function execute_stored_procedure($connection, $method, $procedure_name, $params, $source_name) {
        try {
            error_log("CP Frontend: Ejecutando SP {$procedure_name} con parámetros: " . implode(', ', $params));
            
            if (strpos($method, 'PDO') !== false) {
                // Para PDO
                $placeholders = str_repeat('?,', count($params) - 1) . '?';
                $sql = "EXEC {$procedure_name} {$placeholders}";
                
                $stmt = $connection->prepare($sql);
                $stmt->execute($params);
                
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("CP Frontend: {$source_name} - " . count($results) . " registros encontrados via PDO SP");
                return $results;
                
            } else {
                // Para SQLSRV - Usar sintaxis de llamada correcta
                $placeholders = str_repeat('?,', count($params) - 1) . '?';
                $sql = "{ call {$procedure_name}({$placeholders}) }";
                
                $stmt = sqlsrv_query($connection, $sql, $params);
                
                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    $error_msg = 'Error ejecutando SP: ';
                    foreach ($errors as $error) {
                        $error_msg .= $error['message'] . ' ';
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
                
                error_log("CP Frontend: {$source_name} - " . count($results) . " registros encontrados via SQLSRV SP");
                return $results;
            }
        } catch (Exception $e) {
            error_log("CP Frontend: Error en SP {$procedure_name} - " . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Verificar si los stored procedures están disponibles - MEJORADO
     */
    private function stored_procedures_available() {
        // Verificar configuración
        if (!get_option('cp_use_stored_procedures', true)) {
            return false;
        }
        
        // Verificar si podemos conectar a la base de datos
        if (!$this->db) {
            return false;
        }
        
        $connection_result = $this->db->connect();
        if (!$connection_result['success']) {
            return false;
        }
        
        $connection = $connection_result['connection'];
        $method = $connection_result['method'];
        
        try {
            // Probar un stored procedure simple para verificar disponibilidad
            if (strpos($method, 'PDO') !== false) {
                $stmt = $connection->prepare("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_NAME LIKE 'ConsultaContratosPor%' AND ROUTINE_SCHEMA = 'IDI'");
                $stmt->execute();
                $result = $stmt->fetch();
                $sp_count = $result['count'];
            } else {
                $stmt = sqlsrv_query($connection, "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_NAME LIKE 'ConsultaContratosPor%' AND ROUTINE_SCHEMA = 'IDI'");
                if ($stmt === false) {
                    return false;
                }
                $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                $sp_count = $row['count'];
                sqlsrv_free_stmt($stmt);
            }
            
            // Si encontramos al menos los 6 stored procedures esperados
            $sp_available = $sp_count >= 6;
            error_log("CP Frontend: Stored procedures disponibles: " . ($sp_available ? 'SÍ' : 'NO') . " (encontrados: {$sp_count})");
            
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
            if (strpos($method, 'PDO') !== false) {
                $stmt = $connection->prepare($sql);
                $stmt->execute($params);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                error_log("CP Frontend: {$source_name} - " . count($results) . " registros encontrados");
                return $results;
            } else {
                $stmt = sqlsrv_query($connection, $sql, $params);
                
                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    $error_msg = 'Error en consulta: ';
                    foreach ($errors as $error) {
                        $error_msg .= $error['message'] . ' ';
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
                
                error_log("CP Frontend: {$source_name} - " . count($results) . " registros encontrados");
                return $results;
            }
        } catch (Exception $e) {
            error_log("CP Frontend: Error en {$source_name} - " . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Buscar TVEC vía API (placeholder)
     */
    private function search_tvec_api($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        // Placeholder para API TVEC
        return array();
    }
    
    /**
     * Buscar SECOPI vía API (placeholder)
     */
    private function search_secopi_api($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        // Placeholder para API SECOPI
        return array();
    }
    
    /**
     * Buscar SECOPII vía API (placeholder)
     */
    private function search_secopii_api($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        // Placeholder para API SECOPII
        return array();
    }
    
    /**
     * Registrar búsqueda en el log del frontend
     */
    private function log_frontend_search($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $results_count) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        // Verificar que la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return;
        }
        
        $search_sources = array();
        $search_config = $this->get_search_configuration();
        
        foreach ($search_config as $source => $config) {
            if ($config['active']) {
                $search_sources[] = $source . ':' . $config['method'];
            }
        }
        
        $wpdb->insert(
            $table_name,
            array(
                'session_id' => session_id() ?: 'no-session',
                'profile_type' => $profile_type,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
                'numero_documento' => $numero_documento,
                'search_sources' => implode(',', $search_sources),
                'results_found' => $results_count,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
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
        
        // No permitir rangos muy amplios (máximo 1 año)
        $diff = $inicio->diff($fin);
        if ($diff->days > 365) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Contar total de registros
     */
    private function count_total_records($results) {
        $total = 0;
        foreach ($results as $source => $records) {
            $total += count($records);
        }
        return $total;
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
}