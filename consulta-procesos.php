<?php
/**
 * Plugin Name: Consulta Procesos
 * Plugin URI: https://tu-sitio.com/consulta-procesos
 * Description: Plugin para consultar procesos desde una base de datos SQL Server externa con formulario frontend y sistema de logs completo
 * Version: 1.2.0
 * Author: Tu Nombre
 * License: GPL v2 or later
 * Text Domain: consulta-procesos
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('CP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CP_PLUGIN_VERSION', '1.2.0');
define('CP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Clase principal del plugin Consulta Procesos
 * Versión modular y organizada con soporte para frontend y logs
 */
class ConsultaProcesos {
    
    private static $instance = null;
    private $db;
    private $admin;
    private $frontend;
    private $logs;
    private $query_executor;
    
    /**
     * Obtener instancia única (Singleton)
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
        $this->init();
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Inicialización básica
     */
    private function init() {
        // Cargar archivos de idioma
        add_action('init', array($this, 'load_textdomain'));
        
        // Hooks de activación y desactivación
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('ConsultaProcesos', 'uninstall'));
    }
    
    /**
     * Cargar dependencias del plugin
     */
    private function load_dependencies() {
        // Verificar que los archivos existan antes de cargarlos
        $required_files = array(
            CP_PLUGIN_PATH . 'includes/class-cp-database.php',
            CP_PLUGIN_PATH . 'includes/class-cp-admin.php'
        );
        
        foreach ($required_files as $file) {
            if (file_exists($file)) {
                require_once $file;
            } else {
                // Si faltan archivos críticos, mostrar error
                add_action('admin_notices', function() use ($file) {
                    echo '<div class="notice notice-error"><p>';
                    echo '<strong>Error en Consulta Procesos:</strong> Archivo requerido no encontrado: ' . basename($file);
                    echo '</p></div>';
                });
                return;
            }
        }
        
        // Cargar archivos opcionales
        $optional_files = array(
            CP_PLUGIN_PATH . 'includes/class-cp-utils.php',
            CP_PLUGIN_PATH . 'includes/class-cp-security.php',
            CP_PLUGIN_PATH . 'includes/class-cp-export.php',
            CP_PLUGIN_PATH . 'includes/class-cp-query-executor.php',
            CP_PLUGIN_PATH . 'includes/class-cp-frontend.php', // Frontend con logs
            CP_PLUGIN_PATH . 'includes/class-cp-logs.php' // NUEVO: Clase de logs
        );
        
        foreach ($optional_files as $file) {
            if (file_exists($file)) {
                require_once $file;
            }
        }
        
        // Inicializar clases principales
        if (class_exists('CP_Database')) {
            $this->db = CP_Database::get_instance();
        }
        
        if (class_exists('CP_Admin')) {
            $this->admin = CP_Admin::get_instance();
        }
        
        // Inicializar frontend
        if (class_exists('CP_Frontend')) {
            $this->frontend = CP_Frontend::get_instance();
        }
        
        // NUEVO: Inicializar logs
        if (class_exists('CP_Logs')) {
            $this->logs = CP_Logs::get_instance();
        }
        
        // Inicializar ejecutor de consultas
        if (class_exists('CP_Query_Executor')) {
            $this->query_executor = CP_Query_Executor::get_instance();
        }
    }
    
    /**
     * Inicializar hooks de WordPress
     */
    private function init_hooks() {
        // Hook para cuando WordPress esté completamente cargado
        add_action('wp_loaded', array($this, 'wp_loaded'));
        
        // Hook para scripts y estilos del frontend
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        
        // Hook para verificar actualizaciones
        add_action('plugins_loaded', array($this, 'check_version'));
        
        // Hook para agregar enlaces en la página de plugins
        add_filter('plugin_action_links_' . CP_PLUGIN_BASENAME, array($this, 'add_action_links'));
        
        // Hook para notificaciones de admin si faltan dependencias
        add_action('admin_notices', array($this, 'check_dependencies_notice'));
        
        // Hook para agregar meta box en editor de posts/páginas
        add_action('add_meta_boxes', array($this, 'add_shortcode_meta_box'));
        
        // NUEVO: Hook para limpieza automática de logs
        add_action('cp_weekly_cleanup', array($this, 'cleanup_old_logs'));
    }
    
    /**
     * Cargar textdomain para traducciones
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'consulta-procesos', 
            false, 
            dirname(CP_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Cuando WordPress esté completamente cargado
     */
    public function wp_loaded() {
        // Verificar compatibilidad
        if (!$this->check_compatibility()) {
            add_action('admin_notices', array($this, 'compatibility_notice'));
            return;
        }
        
        // Plugin completamente cargado
        do_action('cp_plugin_loaded');
    }
    
    /**
     * Scripts y estilos del frontend
     */
    public function frontend_scripts() {
        // Los scripts del frontend se cargan desde la clase CP_Frontend
        // Solo agregar scripts globales aquí si es necesario
    }
    
    /**
     * Verificar compatibilidad del sistema
     */
    private function check_compatibility() {
        // Verificar versión de PHP
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            return false;
        }
        
        // Verificar versión de WordPress
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Mostrar aviso de compatibilidad
     */
    public function compatibility_notice() {
        $class = 'notice notice-error';
        $message = sprintf(
            __('Consulta Procesos requiere PHP 7.4+ y WordPress 5.0+. Versión actual: PHP %s, WordPress %s', 'consulta-procesos'),
            PHP_VERSION,
            get_bloginfo('version')
        );
        
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }
    
    /**
     * Verificar dependencias y mostrar avisos
     */
    public function check_dependencies_notice() {
        // Solo mostrar en páginas del plugin
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'consulta-procesos') === false) {
            return;
        }
        
        // Verificar extensiones SQL Server
        if (!extension_loaded('pdo_sqlsrv') && !extension_loaded('sqlsrv')) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>⚠️ Consulta Procesos:</strong> No se detectaron extensiones de SQL Server. ';
            echo 'El plugin necesita <code>pdo_sqlsrv</code> o <code>sqlsrv</code> para funcionar correctamente.</p>';
            echo '</div>';
        }
        
        // Verificar si faltan archivos críticos
        if (!class_exists('CP_Database') || !class_exists('CP_Admin')) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>❌ Error:</strong> Faltan archivos críticos del plugin. ';
            echo 'Por favor, reinstala el plugin o verifica que todos los archivos estén presentes.</p>';
            echo '</div>';
        }
        
        // Mostrar información sobre el shortcode
        if (class_exists('CP_Frontend')) {
            $this->show_shortcode_info_notice();
        }
    }
    
    /**
     * Mostrar información sobre el shortcode
     */
    private function show_shortcode_info_notice() {
        $screen = get_current_screen();
        
        // Solo mostrar en la página principal del plugin
        if ($screen && $screen->id === 'toplevel_page_consulta-procesos') {
            $shortcode_count = $this->count_shortcode_usage();
            
            if ($shortcode_count === 0) {
                echo '<div class="notice notice-info">';
                echo '<p><strong>💡 Tip:</strong> Para mostrar el formulario de consulta en tu sitio, ';
                echo 'usa el shortcode <code>[consulta_procesos]</code> en cualquier página o entrada. ';
                echo '<a href="' . admin_url('admin.php?page=consulta-procesos-settings') . '">Configurar parámetros</a></p>';
                echo '</div>';
            }
        }
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
     * Verificar versión y migraciones
     */
    public function check_version() {
        $installed_version = get_option('cp_plugin_version', '0.0.0');
        
        if (version_compare($installed_version, CP_PLUGIN_VERSION, '<')) {
            $this->upgrade($installed_version, CP_PLUGIN_VERSION);
            update_option('cp_plugin_version', CP_PLUGIN_VERSION);
        }
        
        // NUEVO: Verificar versión de base de datos
        $this->check_database_version();
    }
    
    /**
     * Proceso de actualización
     */
    private function upgrade($from_version, $to_version) {
        // Log de actualización
        error_log("CP: Actualizando de {$from_version} a {$to_version}");
        
        // Migraciones específicas por versión
        if (version_compare($from_version, '1.1.0', '<')) {
            $this->upgrade_to_1_1_0();
        }
        
        if (version_compare($from_version, '1.2.0', '<')) {
            $this->upgrade_to_1_2_0();
        }
        
        // Ejecutar hook de actualización
        do_action('cp_plugin_upgraded', $from_version, $to_version);
    }
    
    /**
     * Actualización específica a versión 1.1.0
     */
    private function upgrade_to_1_1_0() {
        // Migrar configuraciones si es necesario
        error_log("CP: Migración a 1.1.0 completada");
    }
    
    /**
     * Actualización específica a versión 1.2.0
     */
    private function upgrade_to_1_2_0() {
        // Establecer valores por defecto para nuevas opciones del frontend
        $default_options = array(
            'cp_terms_content' => $this->get_default_terms_content(),
            'cp_tvec_active' => 1,
            'cp_tvec_method' => 'database',
            'cp_secopi_active' => 1,
            'cp_secopi_method' => 'database',
            'cp_secopii_active' => 1,
            'cp_secopii_method' => 'database'
        );
        
        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
        
        // NUEVO: Crear tabla de logs del frontend
        $this->create_frontend_logs_table();
        
        error_log("CP: Migración a 1.2.0 completada - Frontend y logs habilitados");
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
     * Agregar meta box para shortcode en editor
     */
    public function add_shortcode_meta_box() {
        $screens = array('post', 'page');
        
        foreach ($screens as $screen) {
            add_meta_box(
                'cp-shortcode-info',
                __('Consulta Procesos - Shortcode', 'consulta-procesos'),
                array($this, 'shortcode_meta_box_callback'),
                $screen,
                'side',
                'low'
            );
        }
    }
    
    /**
     * Callback para meta box del shortcode
     */
    public function shortcode_meta_box_callback($post) {
        echo '<p>' . __('Para agregar el formulario de consulta, usa:', 'consulta-procesos') . '</p>';
        echo '<code>[consulta_procesos]</code>';
        echo '<p class="description">' . __('También puedes personalizar el título:', 'consulta-procesos') . '</p>';
        echo '<code>[consulta_procesos title="Mi Título"]</code>';
        
        // Mostrar si ya tiene el shortcode
        if (has_shortcode($post->post_content, 'consulta_procesos')) {
            echo '<div style="margin-top: 10px; padding: 8px; background: #e7f3ff; border-left: 3px solid #0073aa;">';
            echo '<strong>✅ Esta página ya contiene el shortcode</strong>';
            echo '</div>';
        }
    }
    
    /**
     * Agregar enlaces de acción en la página de plugins
     */
    public function add_action_links($links) {
        $action_links = array(
            'settings' => sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=consulta-procesos-settings'),
                __('Configurar', 'consulta-procesos')
            ),
            'dashboard' => sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=consulta-procesos'),
                __('Panel', 'consulta-procesos')
            ),
            'logs' => sprintf(
                '<a href="%s">%s</a>',
                admin_url('admin.php?page=consulta-procesos-logs'),
                __('Logs', 'consulta-procesos')
            ),
        );
        
        return array_merge($action_links, $links);
    }
    
    /**
     * Activación del plugin - MEJORADA CON LOGS
     */
    public function activate() {
        // Verificar requisitos del sistema
        if (!$this->check_compatibility()) {
            wp_die(
                __('Este plugin requiere PHP 7.4+ y WordPress 5.0+', 'consulta-procesos'),
                __('Error de Activación', 'consulta-procesos'),
                array('back_link' => true)
            );
        }
        
        // Crear opciones por defecto
        $this->create_default_options();
        
        // NUEVO: Crear tablas necesarias incluyendo logs del frontend
        $this->create_tables();
        
        // Programar limpieza automática de logs
        $this->schedule_log_cleanup();
        
        // Flush rewrite rules si es necesario
        flush_rewrite_rules();
        
        // Log de activación
        error_log('CP: Plugin activado - Versión ' . CP_PLUGIN_VERSION);
        
        // Hook de activación para extensiones
        do_action('cp_plugin_activated');
    }
    
    /**
     * NUEVO: Crear opciones por defecto del plugin
     */
    private function create_default_options() {
        $default_options = array(
            'cp_db_port' => '1433',
            'cp_plugin_version' => CP_PLUGIN_VERSION,
            'cp_activation_date' => current_time('mysql'),
            'cp_settings_version' => '1.2',
            // Opciones del frontend
            'cp_terms_content' => $this->get_default_terms_content(),
            'cp_tvec_active' => 1,
            'cp_tvec_method' => 'database',
            'cp_secopi_active' => 1,
            'cp_secopi_method' => 'database',
            'cp_secopii_active' => 1,
            'cp_secopii_method' => 'database',
            // Opciones técnicas
            'cp_use_stored_procedures' => 1,
            'cp_enable_cache' => 1,
            'cp_cache_duration' => 300, // 5 minutos
            'cp_db_version' => CP_PLUGIN_VERSION
        );
        
        foreach ($default_options as $option => $value) {
            add_option($option, $value);
        }
        
        error_log('CP: Opciones por defecto creadas');
    }
    
    /**
     * Crear tablas necesarias - MEJORADA
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabla para logs de consultas (admin)
        $table_name = $wpdb->prefix . 'cp_query_logs';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            query_text longtext NOT NULL,
            execution_time float DEFAULT 0,
            rows_returned int DEFAULT 0,
            status varchar(20) DEFAULT 'success',
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY created_at (created_at),
            KEY status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Tabla para consultas guardadas
        $saved_queries_table = $wpdb->prefix . 'cp_saved_queries';
        
        $sql2 = "CREATE TABLE $saved_queries_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            query_text longtext NOT NULL,
            is_public tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY name (name)
        ) $charset_collate;";
        
        dbDelta($sql2);
        
        // NUEVA: Tabla para logs de búsquedas del frontend
        $this->create_frontend_logs_table();
        
        error_log('CP: Tablas creadas correctamente');
    }
    
    /**
     * NUEVO: Crear tabla de logs del frontend
     */
    private function create_frontend_logs_table() {
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
            KEY idx_numero_documento (numero_documento),
            KEY idx_session_id (session_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Verificar que la tabla se creó correctamente
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            error_log('CP: Tabla de logs del frontend creada correctamente: ' . $table_name);
            
            // Crear índices adicionales si es necesario
            $this->create_additional_indexes($table_name);
        } else {
            error_log('CP: ERROR - No se pudo crear la tabla de logs: ' . $table_name);
        }
    }
    
    /**
     * NUEVO: Crear índices adicionales para optimización
     */
    private function create_additional_indexes($table_name) {
        global $wpdb;
        
        // Verificar si ya existen los índices antes de crearlos
        $indexes = array(
            'idx_status_created' => "CREATE INDEX idx_status_created ON {$table_name} (status, created_at)",
            'idx_profile_created' => "CREATE INDEX idx_profile_created ON {$table_name} (profile_type, created_at)",
            'idx_documento_status' => "CREATE INDEX idx_documento_status ON {$table_name} (numero_documento, status)"
        );
        
        foreach ($indexes as $index_name => $sql) {
            // Verificar si el índice ya existe
            $index_exists = $wpdb->get_var(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                 WHERE table_schema = DATABASE() 
                 AND table_name = '{$table_name}' 
                 AND index_name = '{$index_name}'"
            );
            
            if (!$index_exists) {
                $result = $wpdb->query($sql);
                if ($result !== false) {
                    error_log("CP: Índice {$index_name} creado en tabla {$table_name}");
                }
            }
        }
    }
    
    /**
     * NUEVO: Programar limpieza automática de logs
     */
    private function schedule_log_cleanup() {
        if (!wp_next_scheduled('cp_weekly_cleanup')) {
            wp_schedule_event(time(), 'weekly', 'cp_weekly_cleanup');
            error_log('CP: Limpieza automática de logs programada');
        }
    }
    
    /**
     * NUEVO: Limpieza automática de logs antiguos
     */
    public function cleanup_old_logs() {
        if ($this->logs) {
            $deleted = $this->logs->cleanup_old_logs(180); // 180 días
            if ($deleted > 0) {
                error_log("CP: Limpieza automática - {$deleted} logs eliminados");
            }
        }
    }
    
    /**
     * NUEVO: Verificar y actualizar esquema de base de datos
     */
    public function check_database_version() {
        $current_version = get_option('cp_db_version', '1.0.0');
        $plugin_version = CP_PLUGIN_VERSION;
        
        if (version_compare($current_version, $plugin_version, '<')) {
            // Actualizar esquema si es necesario
            $this->update_database_schema($current_version, $plugin_version);
            update_option('cp_db_version', $plugin_version);
        }
    }
    
    /**
     * NUEVO: Actualizar esquema de base de datos según versión
     */
    private function update_database_schema($from_version, $to_version) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        // Actualización de versión 1.0.0 a 1.1.0 (ejemplo)
        if (version_compare($from_version, '1.1.0', '<')) {
            // Agregar nuevas columnas si no existen
            $columns_to_add = array(
                'execution_time' => "ADD COLUMN execution_time decimal(8,3) DEFAULT NULL",
                'user_agent' => "ADD COLUMN user_agent text"
            );
            
            foreach ($columns_to_add as $column => $sql) {
                $column_exists = $wpdb->get_var(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                     WHERE table_schema = DATABASE() 
                     AND table_name = '{$table_name}' 
                     AND column_name = '{$column}'"
                );
                
                if (!$column_exists) {
                    $wpdb->query("ALTER TABLE {$table_name} {$sql}");
                    error_log("CP: Columna {$column} agregada a {$table_name}");
                }
            }
        }
        
        // Actualización específica a 1.2.0
        if (version_compare($from_version, '1.2.0', '<')) {
            // Verificar que la tabla de logs frontend existe
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                $this->create_frontend_logs_table();
            }
        }
        
        error_log("CP: Base de datos actualizada de v{$from_version} a v{$to_version}");
    }
    
    /**
     * Desactivación del plugin - MEJORADA
     */
    public function deactivate() {
        // Limpiar tareas programadas
        wp_clear_scheduled_hook('cp_cleanup_logs');
        wp_clear_scheduled_hook('cp_cleanup_temp_files');
        wp_clear_scheduled_hook('cp_weekly_cleanup');
        
        // Limpiar transients de búsquedas
        $this->cleanup_search_transients();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log de desactivación
        error_log('CP: Plugin desactivado');
        
        // Hook de desactivación
        do_action('cp_plugin_deactivated');
    }
    
    /**
     * Desinstalación del plugin - MEJORADA
     */
    public static function uninstall() {
        // Solo ejecutar si realmente se está desinstalando
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }
        
        // Eliminar opciones
        $options_to_delete = array(
            'cp_db_server',
            'cp_db_database', 
            'cp_db_username',
            'cp_db_password',
            'cp_db_port',
            'cp_plugin_version',
            'cp_activation_date',
            'cp_settings_version',
            'cp_terms_content',
            'cp_tvec_active',
            'cp_tvec_method',
            'cp_secopi_active',
            'cp_secopi_method',
            'cp_secopii_active',
            'cp_secopii_method',
            'cp_use_stored_procedures',
            'cp_enable_cache',
            'cp_cache_duration',
            'cp_db_version'
        );
        
        foreach ($options_to_delete as $option) {
            delete_option($option);
        }
        
        // Limpiar transients
        self::cleanup_search_transients();
        
        // Eliminar tablas (opcional - comentado por seguridad)
        /*
        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cp_query_logs");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cp_saved_queries");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cp_frontend_logs");
        */
        
        // Log de desinstalación
        error_log('CP: Plugin desinstalado completamente');
        
        // Hook de desinstalación
        do_action('cp_plugin_uninstalled');
    }
    
    /**
     * NUEVO: Limpiar transients de búsquedas
     */
    private static function cleanup_search_transients() {
        global $wpdb;
        
        // Eliminar todos los transients de búsquedas del plugin
        $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_cp_search_%' 
            OR option_name LIKE '_transient_timeout_cp_search_%'
            OR option_name LIKE '_transient_cp_query_%'
            OR option_name LIKE '_transient_timeout_cp_query_%'
        ");
        
        error_log('CP: Transients de búsqueda limpiados');
    }
    
    /**
     * Obtener instancia de la base de datos
     */
    public function get_database() {
        return $this->db;
    }
    
    /**
     * Obtener instancia del admin
     */
    public function get_admin() {
        return $this->admin;
    }
    
    /**
     * Obtener instancia del frontend
     */
    public function get_frontend() {
        return $this->frontend;
    }
    
    /**
     * NUEVO: Obtener instancia de logs
     */
    public function get_logs() {
        return $this->logs;
    }
    
    /**
     * Obtener instancia del ejecutor de consultas
     */
    public function get_query_executor() {
        return $this->query_executor;
    }
    
    /**
     * Información del plugin
     */
    public function get_plugin_info() {
        return array(
            'version' => CP_PLUGIN_VERSION,
            'path' => CP_PLUGIN_PATH,
            'url' => CP_PLUGIN_URL,
            'basename' => CP_PLUGIN_BASENAME,
            'has_frontend' => class_exists('CP_Frontend'),
            'has_logs' => class_exists('CP_Logs'),
            'shortcode_count' => $this->count_shortcode_usage()
        );
    }
}

/**
 * Función de acceso global al plugin
 */
function consulta_procesos() {
    return ConsultaProcesos::get_instance();
}

/**
 * Inicializar el plugin
 */
add_action('plugins_loaded', function() {
    consulta_procesos();
});

/**
 * Funciones de utilidad globales
 */

/**
 * Obtener instancia de la base de datos
 */
function cp_get_database() {
    return consulta_procesos()->get_database();
}

/**
 * Obtener instancia del admin
 */
function cp_get_admin() {
    return consulta_procesos()->get_admin();
}

/**
 * Obtener instancia del frontend
 */
function cp_get_frontend() {
    return consulta_procesos()->get_frontend();
}

/**
 * NUEVO: Obtener instancia de logs
 */
function cp_get_logs() {
    return consulta_procesos()->get_logs();
}

/**
 * Obtener instancia del ejecutor de consultas
 */
function cp_get_query_executor() {
    return consulta_procesos()->get_query_executor();
}

/**
 * Verificar si el usuario puede usar el plugin
 */
function cp_current_user_can() {
    return current_user_can('manage_options');
}

/**
 * Log personalizado para el plugin
 */
function cp_log($message, $level = 'info') {
    if (WP_DEBUG && WP_DEBUG_LOG) {
        error_log("CP[{$level}]: {$message}");
    }
}

/**
 * Obtener configuración de conexión
 */
function cp_get_connection_config() {
    return array(
        'server' => get_option('cp_db_server'),
        'database' => get_option('cp_db_database'),
        'username' => get_option('cp_db_username'),
        'password' => get_option('cp_db_password'),
        'port' => get_option('cp_db_port', '1433')
    );
}

/**
 * Obtener configuración del frontend
 */
function cp_get_frontend_config() {
    return array(
        'terms_content' => get_option('cp_terms_content'),
        'tvec' => array(
            'active' => get_option('cp_tvec_active', 1),
            'method' => get_option('cp_tvec_method', 'database')
        ),
        'secopi' => array(
            'active' => get_option('cp_secopi_active', 1),
            'method' => get_option('cp_secopi_method', 'database')
        ),
        'secopii' => array(
            'active' => get_option('cp_secopii_active', 1),
            'method' => get_option('cp_secopii_method', 'database')
        )
    );
}

/**
 * Verificar si una fuente de búsqueda está activa
 */
function cp_is_search_source_active($source) {
    return get_option("cp_{$source}_active", 1) == 1;
}

/**
 * Obtener método de búsqueda para una fuente
 */
function cp_get_search_method($source) {
    return get_option("cp_{$source}_method", 'database');
}

/**
 * NUEVAS FUNCIONES DE UTILIDAD PARA LOGS
 */

/**
 * Obtener estadísticas rápidas de logs
 */
function cp_get_logs_summary() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cp_frontend_logs';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return array(
            'total' => 0,
            'today' => 0,
            'successful' => 0,
            'failed' => 0
        );
    }
    
    $today = date('Y-m-d');
    
    return array(
        'total' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table_name}")),
        'today' => intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE DATE(created_at) = %s", $today))),
        'successful' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'success'")),
        'failed' => intval($wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'error'"))
    );
}

/**
 * Limpiar logs antiguos automáticamente
 */
function cp_cleanup_old_logs($days = 180) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cp_frontend_logs';
    
    // Eliminar logs más antiguos de X días
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table_name} WHERE created_at < %s",
        $cutoff_date
    ));
    
    if ($deleted > 0) {
        error_log("CP: Limpieza automática - {$deleted} logs antiguos eliminados");
    }
    
    return $deleted;
}

/**
 * Registrar búsqueda del frontend
 */
function cp_log_frontend_search($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $results_found, $execution_time = null, $status = 'success', $error_message = null) {
    $logs_instance = cp_get_logs();
    if ($logs_instance) {
        if ($status === 'success') {
            return $logs_instance->log_search_success($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $results_found, $execution_time);
        } else {
            return $logs_instance->log_search_error($error_message ?: 'Error desconocido', $error_message, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $execution_time);
        }
    }
    return false;
}

/**
 * Hook para limpieza automática semanal
 */
add_action('wp_scheduled_delete', 'cp_cleanup_old_logs');

// Hook para desarrolladores - se ejecuta cuando el plugin está completamente cargado
do_action('cp_plugin_init');