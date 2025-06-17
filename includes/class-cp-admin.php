<?php
/**
 * Clase para manejar todas las vistas y funcionalidades del panel de administración
 * 
 * Archivo: includes/class-cp-admin.php (actualizado con configuración de APIs)
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_Admin {
    
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
        $this->register_logs_ajax_hooks();
    }
    
    /**
     * Inicializar hooks de WordPress
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        
        // Hooks AJAX existentes
        add_action('wp_ajax_cp_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_cp_get_tables', array($this, 'ajax_get_tables'));
        add_action('wp_ajax_cp_diagnose_system', array($this, 'ajax_diagnose_system'));
        
        // Nuevos hooks AJAX para testing
        add_action('wp_ajax_cp_test_stored_procedure', array($this, 'ajax_test_stored_procedure'));
        add_action('wp_ajax_cp_execute_admin_query', array($this, 'ajax_execute_admin_query'));
        add_action('wp_ajax_cp_get_system_logs', array($this, 'ajax_get_system_logs'));
        add_action('wp_ajax_cp_clear_system_logs', array($this, 'ajax_clear_system_logs'));
        
        // NUEVO: Hooks para caché
        add_action('wp_ajax_cp_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_cp_get_cache_stats', array($this, 'ajax_get_cache_stats'));
        
        // Debug: Verificar que los hooks se registren
        add_action('wp_loaded', function() {
            error_log('CP Admin: Hooks AJAX registrados correctamente');
        });
    }
    
    /**
     * Registrar hooks AJAX para logs del frontend
     */
    private function register_logs_ajax_hooks() {
        // Logs del frontend
        add_action('wp_ajax_cp_get_frontend_logs', array($this, 'ajax_get_frontend_logs'));
        add_action('wp_ajax_cp_clear_frontend_logs', array($this, 'ajax_clear_frontend_logs'));
        add_action('wp_ajax_cp_export_frontend_logs', array($this, 'ajax_export_frontend_logs'));
    }
    
    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Consulta Procesos', 'consulta-procesos'),
            __('Consulta Procesos', 'consulta-procesos'),
            'manage_options',
            'consulta-procesos',
            array($this, 'render_dashboard_page'),
            'dashicons-database-view',
            30
        );
        
        add_submenu_page(
            'consulta-procesos',
            __('Panel Principal', 'consulta-procesos'),
            __('Panel Principal', 'consulta-procesos'),
            'manage_options',
            'consulta-procesos',
            array($this, 'render_dashboard_page')
        );
        
        add_submenu_page(
            'consulta-procesos',
            __('Configuración de Conexión', 'consulta-procesos'),
            __('Conexión BD', 'consulta-procesos'),
            'manage_options',
            'consulta-procesos-config',
            array($this, 'render_config_page')
        );
        
        // Nueva página de configuración de parámetros
        add_submenu_page(
            'consulta-procesos',
            __('Configuración de Parámetros', 'consulta-procesos'),
            __('Parámetros', 'consulta-procesos'),
            'manage_options',
            'consulta-procesos-settings',
            array($this, 'render_settings_page')
        );
        
        // Página para consultas (futura funcionalidad)
        add_submenu_page(
            'consulta-procesos',
            __('Nueva Consulta', 'consulta-procesos'),
            __('Nueva Consulta', 'consulta-procesos'),
            'manage_options',
            'consulta-procesos-query',
            array($this, 'render_query_page')
        );
        
        // Nueva página de logs y debugging
        add_submenu_page(
            'consulta-procesos',
            __('Logs y Debugging', 'consulta-procesos'),
            __('Logs', 'consulta-procesos'),
            'manage_options',
            'consulta-procesos-logs',
            array($this, 'render_logs_page')
        );
    }
    
    /**
     * Inicializar configuraciones de administración - ACTUALIZADO con APIs
     */
    public function admin_init() {
        // Registrar configuraciones de conexión
        register_setting('cp_settings_group', 'cp_db_server');
        register_setting('cp_settings_group', 'cp_db_database');
        register_setting('cp_settings_group', 'cp_db_username');
        register_setting('cp_settings_group', 'cp_db_password');
        register_setting('cp_settings_group', 'cp_db_port');
        
        // Registrar configuraciones de parámetros del frontend
        register_setting('cp_frontend_settings_group', 'cp_terms_content');
        
        // TVEC
        register_setting('cp_frontend_settings_group', 'cp_tvec_active');
        register_setting('cp_frontend_settings_group', 'cp_tvec_method');
        register_setting('cp_frontend_settings_group', 'cp_tvec_api_url_proveedores');
        register_setting('cp_frontend_settings_group', 'cp_tvec_api_url_entidades');
        register_setting('cp_frontend_settings_group', 'cp_tvec_api_date_field');
        
        // SECOPI
        register_setting('cp_frontend_settings_group', 'cp_secopi_active');
        register_setting('cp_frontend_settings_group', 'cp_secopi_method');
        register_setting('cp_frontend_settings_group', 'cp_secopi_api_url_proveedores');
        register_setting('cp_frontend_settings_group', 'cp_secopi_api_url_entidades');
        register_setting('cp_frontend_settings_group', 'cp_secopi_api_date_field');
        
        // SECOPII
        register_setting('cp_frontend_settings_group', 'cp_secopii_active');
        register_setting('cp_frontend_settings_group', 'cp_secopii_method');
        register_setting('cp_frontend_settings_group', 'cp_secopii_api_url_proveedores');
        register_setting('cp_frontend_settings_group', 'cp_secopii_api_url_entidades');
        register_setting('cp_frontend_settings_group', 'cp_secopii_api_date_field');
        
        // NUEVO: Configuraciones de rendimiento
        register_setting('cp_frontend_settings_group', 'cp_enable_cache');
        register_setting('cp_frontend_settings_group', 'cp_cache_duration');
        register_setting('cp_frontend_settings_group', 'cp_use_stored_procedures');
        register_setting('cp_frontend_settings_group', 'cp_max_results_per_source');
        
        // Secciones de configuración de conexión
        add_settings_section(
            'cp_db_section',
            __('Configuración de Base de Datos', 'consulta-procesos'),
            array($this, 'db_section_callback'),
            'consulta-procesos-config'
        );
        
        // Campos de configuración de conexión
        $this->add_connection_settings_fields();
    }
    
    /**
     * Agregar campos de configuración de conexión
     */
    private function add_connection_settings_fields() {
        $fields = array(
            'cp_db_server' => array(
                'title' => __('Servidor', 'consulta-procesos'),
                'callback' => 'server_field_callback',
                'description' => __('Dirección IP o nombre del servidor SQL Server (usa host.docker.internal si estás en Docker)', 'consulta-procesos')
            ),
            'cp_db_database' => array(
                'title' => __('Base de Datos', 'consulta-procesos'),
                'callback' => 'database_field_callback',
                'description' => __('Nombre de la base de datos', 'consulta-procesos')
            ),
            'cp_db_username' => array(
                'title' => __('Usuario', 'consulta-procesos'),
                'callback' => 'username_field_callback',
                'description' => __('Usuario de la base de datos', 'consulta-procesos')
            ),
            'cp_db_password' => array(
                'title' => __('Contraseña', 'consulta-procesos'),
                'callback' => 'password_field_callback',
                'description' => __('Contraseña del usuario', 'consulta-procesos')
            ),
            'cp_db_port' => array(
                'title' => __('Puerto', 'consulta-procesos'),
                'callback' => 'port_field_callback',
                'description' => __('Puerto del servidor (por defecto: 1433)', 'consulta-procesos')
            )
        );
        
        foreach ($fields as $field_id => $field) {
            add_settings_field(
                $field_id,
                $field['title'],
                array($this, $field['callback']),
                'consulta-procesos-config',
                'cp_db_section'
            );
        }
    }
    
    /**
     * Cargar scripts y estilos de administración
     */
    public function admin_scripts($hook) {
        if (strpos($hook, 'consulta-procesos') !== false) {
            wp_enqueue_script('cp-admin-js', CP_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), CP_PLUGIN_VERSION, true);
            wp_enqueue_style('cp-admin-css', CP_PLUGIN_URL . 'assets/css/admin.css', array(), CP_PLUGIN_VERSION);
            
            // Localizar script para AJAX
            wp_localize_script('cp-admin-js', 'cp_ajax', array(
                'url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cp_admin_nonce'),
                'messages' => array(
                    'testing' => __('Probando conexión...', 'consulta-procesos'),
                    'success' => __('Conexión exitosa', 'consulta-procesos'),
                    'error' => __('Error de conexión', 'consulta-procesos'),
                    'loading_tables' => __('Cargando tablas...', 'consulta-procesos'),
                    'loading' => __('Cargando...', 'consulta-procesos')
                )
            ));
        }
    }
    
    /**
     * Renderizar página principal del dashboard
     */
    public function render_dashboard_page() {
        include_once CP_PLUGIN_PATH . 'admin/views/dashboard.php';
    }
    
    /**
     * Renderizar página de configuración de conexión
     */
    public function render_config_page() {
        include_once CP_PLUGIN_PATH . 'admin/views/config.php';
    }
    
    /**
     * Renderizar página de configuración de parámetros
     */
    public function render_settings_page() {
        include_once CP_PLUGIN_PATH . 'admin/views/settings.php';
    }
    
    /**
     * Renderizar página de nueva consulta
     */
    public function render_query_page() {
        include_once CP_PLUGIN_PATH . 'admin/views/query.php';
    }
    
    /**
     * Renderizar página de logs y debugging
     */
    public function render_logs_page() {
        include_once CP_PLUGIN_PATH . 'admin/views/logs.php';
    }
    
    /**
     * Callbacks para campos de configuración de conexión
     */
    public function db_section_callback() {
        echo '<p>' . __('Configura los parámetros de conexión a tu servidor SQL Server.', 'consulta-procesos') . '</p>';
        
        // Mostrar estado de conexión actual
        $test_result = $this->db->test_connection();
        if ($test_result['success']) {
            echo '<div class="notice notice-success inline">';
            echo '<p><strong>✅ Conexión actual: </strong>' . esc_html($test_result['message']) . '</p>';
            echo '</div>';
        } else {
            echo '<div class="notice notice-warning inline">';
            echo '<p><strong>⚠️ Estado: </strong>No hay conexión configurada o hay problemas</p>';
            echo '</div>';
        }
    }
    
    public function server_field_callback() {
        $value = get_option('cp_db_server', '');
        echo '<input type="text" name="cp_db_server" value="' . esc_attr($value) . '" class="regular-text" placeholder="host.docker.internal" />';
        echo '<p class="description">' . __('Dirección IP o nombre del servidor SQL Server (usa host.docker.internal si estás en Docker)', 'consulta-procesos') . '</p>';
    }
    
    public function database_field_callback() {
        $value = get_option('cp_db_database', '');
        echo '<input type="text" name="cp_db_database" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Nombre de la base de datos', 'consulta-procesos') . '</p>';
    }
    
    public function username_field_callback() {
        $value = get_option('cp_db_username', '');
        echo '<input type="text" name="cp_db_username" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Usuario de la base de datos', 'consulta-procesos') . '</p>';
    }
    
    public function password_field_callback() {
        $value = get_option('cp_db_password', '');
        echo '<input type="password" name="cp_db_password" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Contraseña del usuario', 'consulta-procesos') . '</p>';
    }
    
    public function port_field_callback() {
        $value = get_option('cp_db_port', '1433');
        echo '<input type="number" name="cp_db_port" value="' . esc_attr($value) . '" class="small-text" />';
        echo '<p class="description">' . __('Puerto del servidor (por defecto: 1433)', 'consulta-procesos') . '</p>';
    }
    
    /**
     * AJAX: Probar conexión
     */
    public function ajax_test_connection() {
        check_ajax_referer('cp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        $result = $this->db->test_connection();
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => $result['message'],
                'method' => $result['method']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['error'],
                'suggestions' => $result['suggestions']
            ));
        }
    }
    
    /**
     * AJAX: Obtener tablas disponibles
     */
    public function ajax_get_tables() {
        check_ajax_referer('cp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        $result = $this->db->get_tables();
        
        if ($result['success']) {
            wp_send_json_success(array(
                'tables' => $result['tables'],
                'count' => $result['count'],
                'method' => $result['method']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['error']
            ));
        }
    }
    
    /**
     * AJAX: Diagnosticar sistema
     */
    public function ajax_diagnose_system() {
        check_ajax_referer('cp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        $diagnosis = $this->db->diagnose_system();
        $suggestions = $this->db->get_connection_suggestions();
        
        wp_send_json_success(array(
            'diagnosis' => $diagnosis,
            'suggestions' => $suggestions
        ));
    }
    
    /**
     * AJAX: Probar stored procedure
     */
    public function ajax_test_stored_procedure() {
        check_ajax_referer('cp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        $sp_name = sanitize_text_field($_POST['sp_name'] ?? '');
        $param1 = sanitize_text_field($_POST['param1'] ?? '');
        $param2 = sanitize_text_field($_POST['param2'] ?? '');
        $param3 = sanitize_text_field($_POST['param3'] ?? '');
        
        if (empty($sp_name) || empty($param1) || empty($param2) || empty($param3)) {
            wp_send_json_error(array('message' => 'Faltan parámetros requeridos'));
        }
        
        // Log del intento
        error_log("CP Admin: Probando SP {$sp_name} con parámetros: {$param1}, {$param2}, {$param3}");
        
        $connection_result = $this->db->connect();
        if (!$connection_result['success']) {
            wp_send_json_error(array('message' => 'Error de conexión: ' . $connection_result['error']));
        }
        
        $connection = $connection_result['connection'];
        $method = $connection_result['method'];
        
        try {
            $start_time = microtime(true);
            
            if (strpos($method, 'PDO') !== false) {
                $sql = "SET NOCOUNT ON; EXEC {$sp_name} ?, ?, ?";
                $stmt = $connection->prepare($sql);
                $success = $stmt->execute(array($param1, $param2, $param3));
                
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
                
            } else {
                $sql = "SET NOCOUNT ON; EXEC {$sp_name} ?, ?, ?";
                $stmt = sqlsrv_prepare($connection, $sql, array($param1, $param2, $param3));
                
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
            }
            
            $execution_time = microtime(true) - $start_time;
            
            wp_send_json_success(array(
                'results' => $results,
                'total_rows' => count($results),
                'execution_time' => round($execution_time, 4),
                'method' => $method,
                'sql' => $sql
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX: Ejecutar consulta de admin (permite EXEC)
     */
    public function ajax_execute_admin_query() {
        check_ajax_referer('cp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        $sql = stripslashes($_POST['sql'] ?? '');
        
        if (empty($sql)) {
            wp_send_json_error(array('message' => 'Consulta SQL requerida'));
        }
        
        // Validación más permisiva para admin
        if (!$this->validate_admin_query($sql)) {
            wp_send_json_error(array('message' => 'Consulta no permitida'));
        }
        
        error_log("CP Admin: Ejecutando consulta admin: " . substr($sql, 0, 100) . "...");
        
        $connection_result = $this->db->connect();
        if (!$connection_result['success']) {
            wp_send_json_error(array('message' => 'Error de conexión: ' . $connection_result['error']));
        }
        
        $connection = $connection_result['connection'];
        $method = $connection_result['method'];
        
        try {
            $start_time = microtime(true);
            
            if (strpos($method, 'PDO') !== false) {
                $stmt = $connection->prepare($sql);
                $success = $stmt->execute();
                
                if (!$success) {
                    $errorInfo = $stmt->errorInfo();
                    throw new Exception("Error PDO: " . $errorInfo[2]);
                }
                
                $results = array();
                if ($stmt->columnCount() > 0) {
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } else {
                $stmt = sqlsrv_query($connection, $sql);
                
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
                    foreach ($row as $key => $value) {
                        if ($value instanceof DateTime) {
                            $row[$key] = $value->format('Y-m-d H:i:s');
                        }
                    }
                    $results[] = $row;
                }
                sqlsrv_free_stmt($stmt);
            }
            
            $execution_time = microtime(true) - $start_time;
            
            error_log("CP Admin: Consulta admin ejecutada - " . count($results) . " resultados en " . round($execution_time, 4) . "s");
            
            wp_send_json_success(array(
                'results' => $results,
                'total_rows' => count($results),
                'execution_time' => round($execution_time, 4),
                'method' => $method
            ));
            
        } catch (Exception $e) {
            error_log("CP Admin: Error en consulta admin - " . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * Validar consulta de admin (más permisiva)
     */
    private function validate_admin_query($sql) {
        $sql = trim(strtoupper($sql));
        
        // Permitir SELECT, EXEC, SP_HELP, etc.
        $allowed_starts = array('SELECT', 'EXEC', 'SP_HELP', 'SP_HELPDB', 'SP_COLUMNS');
        
        foreach ($allowed_starts as $start) {
            if (strpos($sql, $start) === 0) {
                return true;
            }
        }
        
        // Bloquear comandos peligrosos
        $dangerous = array('DROP', 'DELETE', 'UPDATE', 'INSERT', 'TRUNCATE', 'ALTER', 'CREATE');
        foreach ($dangerous as $cmd) {
            if (strpos($sql, $cmd) === 0) {
                return false;
            }
        }
        
        return false;
    }
    
    /**
     * AJAX: Obtener logs del sistema
     */
    public function ajax_get_system_logs() {
        check_ajax_referer('cp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        try {
            $log_file = WP_CONTENT_DIR . '/debug.log';
            
            if (!file_exists($log_file)) {
                wp_send_json_success(array(
                    'logs' => 'Archivo debug.log no encontrado.',
                    'file_size' => 0
                ));
            }
            
            $file_size = filesize($log_file);
            
            // Leer las últimas 100KB del archivo para no sobrecargar
            $max_size = 100 * 1024; // 100KB
            
            if ($file_size > $max_size) {
                $handle = fopen($log_file, 'r');
                fseek($handle, -$max_size, SEEK_END);
                $logs = fread($handle, $max_size);
                fclose($handle);
                
                // Buscar el primer salto de línea para evitar líneas cortadas
                $first_newline = strpos($logs, "\n");
                if ($first_newline !== false) {
                    $logs = substr($logs, $first_newline + 1);
                }
                
                $logs = "... (mostrando últimos " . size_format($max_size) . ")\n\n" . $logs;
            } else {
                $logs = file_get_contents($log_file);
            }
            
            // Filtrar logs relacionados con el plugin si es posible
            $lines = explode("\n", $logs);
            $cp_lines = array();
            $other_lines = array();
            
            foreach ($lines as $line) {
                if (strpos($line, 'CP Frontend') !== false || 
                    strpos($line, 'CP Admin') !== false || 
                    strpos($line, 'consulta-procesos') !== false) {
                    $cp_lines[] = $line;
                } else {
                    $other_lines[] = $line;
                }
            }
            
            // Mostrar logs del plugin primero si los hay
            if (!empty($cp_lines)) {
                $formatted_logs = "=== LOGS DEL PLUGIN CONSULTA PROCESOS ===\n";
                $formatted_logs .= implode("\n", array_slice($cp_lines, -50)); // Últimas 50 líneas del plugin
                $formatted_logs .= "\n\n=== OTROS LOGS DEL SISTEMA ===\n";
                $formatted_logs .= implode("\n", array_slice($other_lines, -50)); // Últimas 50 líneas del sistema
                $logs = $formatted_logs;
            }
            
            wp_send_json_success(array(
                'logs' => $logs,
                'file_size' => $file_size
            ));
            
        } catch (Exception $e) {
            error_log('CP Admin: Error obteniendo logs del sistema - ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error obteniendo logs del sistema: ' . $e->getMessage()));
        }
    }
    
    /**
     * AJAX: Limpiar logs del sistema
     */
    public function ajax_clear_system_logs() {
        check_ajax_referer('cp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        try {
            $log_file = WP_CONTENT_DIR . '/debug.log';
            
            if (!file_exists($log_file)) {
                wp_send_json_success(array('message' => 'Archivo debug.log no existe'));
            }
            
            // Hacer backup antes de limpiar
            $backup_file = WP_CONTENT_DIR . '/debug.log.backup.' . date('Y-m-d-H-i-s');
            if (copy($log_file, $backup_file)) {
                error_log('CP Admin: Backup de debug.log creado en: ' . $backup_file);
            }
            
            // Limpiar el archivo
            $result = file_put_contents($log_file, '');
            
            if ($result !== false) {
                wp_send_json_success(array('message' => 'Logs del sistema limpiados exitosamente'));
            } else {
                wp_send_json_error(array('message' => 'Error limpiando logs del sistema'));
            }
            
        } catch (Exception $e) {
            error_log('CP Admin: Error limpiando logs del sistema - ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error limpiando logs del sistema: ' . $e->getMessage()));
        }
    }
    
    /**
     * AJAX para obtener logs del frontend para admin
     */
    public function ajax_get_frontend_logs() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cp_admin_nonce')) {
            wp_send_json_error(array('message' => 'Token de seguridad inválido'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        try {
            // Verificar que la tabla existe
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                wp_send_json_error(array('message' => 'Tabla de logs no encontrada'));
            }
            
            // Obtener parámetros de filtrado y paginación
            $page = intval($_POST['page'] ?? 1);
            $page_size = intval($_POST['page_size'] ?? 50);
            $status_filter = sanitize_text_field($_POST['status'] ?? '');
            $profile_filter = sanitize_text_field($_POST['profile'] ?? '');
            $date_filter = sanitize_text_field($_POST['date'] ?? '');
            $stats_only = isset($_POST['stats_only']) && $_POST['stats_only'];
            
            // Construir WHERE clause para filtros
            $where_conditions = array();
            $where_params = array();
            
            if (!empty($status_filter)) {
                $where_conditions[] = "status = %s";
                $where_params[] = $status_filter;
            }
            
            if (!empty($profile_filter)) {
                $where_conditions[] = "profile_type = %s";
                $where_params[] = $profile_filter;
            }
            
            if (!empty($date_filter)) {
                $where_conditions[] = "DATE(created_at) = %s";
                $where_params[] = $date_filter;
            }
            
            $where_clause = '';
            if (!empty($where_conditions)) {
                $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
            }
            
            // Obtener estadísticas generales
            $stats = array();
            
            $stats['total'] = intval($wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} {$where_clause}",
                $where_params
            )));
            
            $successful_query = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";
            if (!empty($where_clause)) {
                $successful_query .= " AND status = 'success'";
                $successful_params = array_merge($where_params, array('success'));
            } else {
                $successful_query .= " WHERE status = 'success'";
                $successful_params = array('success');
            }
            
            $stats['successful'] = intval($wpdb->get_var($wpdb->prepare($successful_query, $successful_params)));
            
            $failed_query = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";
            if (!empty($where_clause)) {
                $failed_query .= " AND status = 'error'";
                $failed_params = array_merge($where_params, array('error'));
            } else {
                $failed_query .= " WHERE status = 'error'";
                $failed_params = array('error');
            }
            
            $stats['failed'] = intval($wpdb->get_var($wpdb->prepare($failed_query, $failed_params)));
            
            // Estadísticas por perfil
            $entidades_query = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";
            if (!empty($where_clause)) {
                $entidades_query .= " AND profile_type = 'entidades'";
                $entidades_params = array_merge($where_params, array('entidades'));
            } else {
                $entidades_query .= " WHERE profile_type = 'entidades'";
                $entidades_params = array('entidades');
            }
            
            $stats['entidades'] = intval($wpdb->get_var($wpdb->prepare($entidades_query, $entidades_params)));
            
            $proveedores_query = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";
            if (!empty($where_clause)) {
                $proveedores_query .= " AND profile_type = 'proveedores'";
                $proveedores_params = array_merge($where_params, array('proveedores'));
            } else {
                $proveedores_query .= " WHERE profile_type = 'proveedores'";
                $proveedores_params = array('proveedores');
            }
            
            $stats['proveedores'] = intval($wpdb->get_var($wpdb->prepare($proveedores_query, $proveedores_params)));
            
            // Si solo se solicitan estadísticas, devolver solo eso
            if ($stats_only) {
                wp_send_json_success(array('stats' => $stats));
            }
            
            // Obtener logs con paginación
            $offset = ($page - 1) * $page_size;
            
            $logs_query = "SELECT * FROM {$table_name} {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
            $logs_params = array_merge($where_params, array($page_size, $offset));
            
            $logs = $wpdb->get_results($wpdb->prepare($logs_query, $logs_params), ARRAY_A);
            
            wp_send_json_success(array(
                'logs' => $logs,
                'stats' => $stats,
                'pagination' => array(
                    'current_page' => $page,
                    'page_size' => $page_size,
                    'total_pages' => ceil($stats['total'] / $page_size)
                )
            ));
            
        } catch (Exception $e) {
            error_log('CP Admin: Error obteniendo logs del frontend - ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error obteniendo logs: ' . $e->getMessage()));
        }
    }
    
    /**
     * AJAX para limpiar logs del frontend
     */
    public function ajax_clear_frontend_logs() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cp_admin_nonce')) {
            wp_send_json_error(array('message' => 'Token de seguridad inválido'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        try {
            // Verificar que la tabla existe
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                wp_send_json_error(array('message' => 'Tabla de logs no encontrada'));
            }
            
            // Contar registros antes de eliminar
            $count_before = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table_name}"));
            
            // Eliminar todos los registros
            $result = $wpdb->query("DELETE FROM {$table_name}");
            
            if ($result !== false) {
                wp_send_json_success(array(
                    'message' => 'Logs del frontend limpiados exitosamente',
                    'deleted_rows' => $count_before
                ));
            } else {
                wp_send_json_error(array('message' => 'Error limpiando logs: ' . $wpdb->last_error));
            }
            
        } catch (Exception $e) {
            error_log('CP Admin: Error limpiando logs del frontend - ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error limpiando logs: ' . $e->getMessage()));
        }
    }
    
    /**
     * AJAX para exportar logs del frontend
     */
    public function ajax_export_frontend_logs() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permisos insuficientes'));
        }
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cp_admin_nonce')) {
            wp_send_json_error(array('message' => 'Token de seguridad inválido'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        try {
            // Verificar que la tabla existe
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                wp_send_json_error(array('message' => 'Tabla de logs no encontrada'));
            }
            
            // Obtener todos los logs
            $logs = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC", ARRAY_A);
            
            // Configurar headers para descarga
            $filename = 'consulta_procesos_logs_' . date('Y-m-d_H-i-s') . '.csv';
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // Crear CSV
            $output = fopen('php://output', 'w');
            
            // Headers CSV
            if (!empty($logs)) {
                fputcsv($output, array_keys($logs[0]));
                
                // Datos
                foreach ($logs as $log) {
                    fputcsv($output, $log);
                }
            }
            
            fclose($output);
            exit;
            
        } catch (Exception $e) {
            error_log('CP Admin: Error exportando logs del frontend - ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error exportando logs: ' . $e->getMessage()));
        }
    }
    
    /**
     * NUEVO: AJAX para limpiar caché
     */
    public function ajax_clear_cache() {
        check_ajax_referer('cp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        if (class_exists('CP_Frontend')) {
            $frontend = CP_Frontend::get_instance();
            $result = $frontend->clear_search_cache();
            
            if ($result) {
                wp_send_json_success(array('message' => 'Caché limpiado exitosamente'));
            } else {
                wp_send_json_error(array('message' => 'Error al limpiar caché'));
            }
        } else {
            wp_send_json_error(array('message' => 'Clase frontend no disponible'));
        }
    }

    /**
     * NUEVO: AJAX para obtener estadísticas de caché
     */
    public function ajax_get_cache_stats() {
        check_ajax_referer('cp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        if (class_exists('CP_Frontend')) {
            $frontend = CP_Frontend::get_instance();
            $stats = $frontend->get_cache_stats();
            
            wp_send_json_success($stats);
        } else {
            wp_send_json_error(array('message' => 'Clase frontend no disponible'));
        }
    }
    
    /**
     * Obtener información del plugin para las vistas
     */
    public function get_plugin_info() {
        return array(
            'version' => CP_PLUGIN_VERSION,
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'wordpress_version' => get_bloginfo('version'),
            'extensions' => $this->db->get_available_extensions()
        );
    }
    
    /**
     * Obtener estadísticas del frontend
     */
    public function get_frontend_stats() {
        $stats = array(
            'shortcode_usage' => $this->count_shortcode_usage(),
            'active_searches' => $this->get_active_search_methods(),
            'terms_configured' => !empty(get_option('cp_terms_content')),
            'last_search' => get_option('cp_last_frontend_search', 'Nunca')
        );
        
        return $stats;
    }
    
    /**
     * Contar uso del shortcode
     */
    private function count_shortcode_usage() {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} 
             WHERE post_status = 'publish' 
             AND post_content LIKE %s",
            '%[consulta_procesos%'
        ));
        
        return intval($count);
    }
    
    /**
     * Obtener métodos de búsqueda activos - ACTUALIZADO con APIs
     */
    private function get_active_search_methods() {
        $methods = array();
        
        if (get_option('cp_tvec_active', 1)) {
            $methods['tvec'] = get_option('cp_tvec_method', 'database');
        }
        
        if (get_option('cp_secopi_active', 1)) {
            $methods['secopi'] = get_option('cp_secopi_method', 'database');
        }
        
        if (get_option('cp_secopii_active', 1)) {
            $methods['secopii'] = get_option('cp_secopii_method', 'database');
        }
        
        return $methods;
    }
    
    /**
     * Generar nonce para formularios
     */
    public function get_nonce_field($action = 'cp_admin_nonce') {
        return wp_nonce_field($action, '_wpnonce', true, false);
    }
    
    /**
     * Verificar permisos del usuario
     */
    public function current_user_can_manage() {
        return current_user_can('manage_options');
    }
    
    /**
     * Mostrar notificación en admin
     */
    public function show_admin_notice($message, $type = 'info') {
        $class = 'notice notice-' . $type;
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }
    
    /**
     * Obtener opciones de configuración por defecto - ACTUALIZADO con APIs
     */
    public function get_default_options() {
        return array(
            // Configuración de conexión
            'cp_db_server' => '',
            'cp_db_database' => '',
            'cp_db_username' => '',
            'cp_db_password' => '',
            'cp_db_port' => '1433',
            
            // Configuración del frontend
            'cp_terms_content' => $this->get_default_terms_content(),
            
            // TVEC
            'cp_tvec_active' => 1,
            'cp_tvec_method' => 'database',
            'cp_tvec_api_url_proveedores' => '',
            'cp_tvec_api_url_entidades' => '',
            'cp_tvec_api_date_field' => '',
            
            // SECOPI
            'cp_secopi_active' => 1,
            'cp_secopi_method' => 'database',
            'cp_secopi_api_url_proveedores' => '',
            'cp_secopi_api_url_entidades' => '',
            'cp_secopi_api_date_field' => '',
            
            // SECOPII
            'cp_secopii_active' => 1,
            'cp_secopii_method' => 'database',
            'cp_secopii_api_url_proveedores' => '',
            'cp_secopii_api_url_entidades' => '',
            'cp_secopii_api_date_field' => '',
            
            // Rendimiento
            'cp_enable_cache' => true,
            'cp_cache_duration' => 300,
            'cp_use_stored_procedures' => false,
            'cp_max_results_per_source' => 1000
        );
    }
    
    /**
     * Obtener contenido por defecto de términos de uso
     */
    private function get_default_terms_content() {
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
     * Resetear configuración a valores por defecto
     */
    public function reset_to_defaults() {
        $defaults = $this->get_default_options();
        
        foreach ($defaults as $option => $value) {
            update_option($option, $value);
        }
        
        return true;
    }
    
    /**
     * Exportar configuración - ACTUALIZADO con APIs
     */
    public function export_configuration() {
        $config = array();
        $options = array_keys($this->get_default_options());
        
        foreach ($options as $option) {
            $config[$option] = get_option($option);
        }
        
        $config['export_date'] = current_time('mysql');
        $config['plugin_version'] = CP_PLUGIN_VERSION;
        
        return $config;
    }
    
    /**
     * Importar configuración
     */
    public function import_configuration($config) {
        if (!is_array($config)) {
            return false;
        }
        
        $defaults = $this->get_default_options();
        $imported = 0;
        
        foreach ($config as $option => $value) {
            if (array_key_exists($option, $defaults)) {
                update_option($option, $value);
                $imported++;
            }
        }
        
        return $imported;
    }
}