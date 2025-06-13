<?php
/**
 * Plugin Name: Consulta Procesos
 * Plugin URI: https://tu-sitio.com/consulta-procesos
 * Description: Plugin para consultar procesos desde una base de datos SQL Server externa con formulario frontend
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
 * Versión modular y organizada con soporte para frontend
 */
class ConsultaProcesos {
    
    private static $instance = null;
    private $db;
    private $admin;
    private $frontend;
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
            CP_PLUGIN_PATH . 'includes/class-cp-frontend.php',
            CP_PLUGIN_PATH . 'includes/class-cp-export-advanced.php',
            CP_PLUGIN_PATH . 'includes/class-cp-results-page.php',
            CP_PLUGIN_PATH . 'includes/class-cp-logs-admin.php'
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
        
        // Inicializar ejecutor de consultas
        if (class_exists('CP_Query_Executor')) {
            $this->query_executor = CP_Query_Executor::get_instance();
        }
        
        // Inicializar sistema de exportación avanzado
        if (class_exists('CP_Export_Advanced')) {
            $this->export_advanced = CP_Export_Advanced::get_instance();
        }
        
        // Inicializar página de resultados
        if (class_exists('CP_Results_Page')) {
            $this->results_page = CP_Results_Page::get_instance();
        }
        
        // Inicializar logs de administración
        if (class_exists('CP_Logs_Admin')) {
            $this->logs_admin = CP_Logs_Admin::get_instance();
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
        
        error_log("CP: Migración a 1.2.0 completada - Frontend habilitado");
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
        );
        
        return array_merge($action_links, $links);
    }
    
    /**
     * Activación del plugin
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
        $default_options = array(
            'cp_db_port' => '1433',
            'cp_plugin_version' => CP_PLUGIN_VERSION,
            'cp_activation_date' => current_time('mysql'),
            'cp_settings_version' => '1.2',
            // Nuevas opciones del frontend
            'cp_terms_content' => $this->get_default_terms_content(),
            'cp_tvec_active' => 1,
            'cp_tvec_method' => 'database',
            'cp_secopi_active' => 1,
            'cp_secopi_method' => 'database',
            'cp_secopii_active' => 1,
            'cp_secopii_method' => 'database'
        );
        
        foreach ($default_options as $option => $value) {
            add_option($option, $value);
        }
        
        // Crear tabla de logs si es necesario
        $this->create_tables();
        
        // Flush rewrite rules si es necesario
        flush_rewrite_rules();
        
        // Log de activación
        error_log('CP: Plugin activado - Versión ' . CP_PLUGIN_VERSION);
        
        // Hook de activación para extensiones
        do_action('cp_plugin_activated');
    }
    
    /**
     * Crear tablas necesarias
     */
    private function create_tables() {
        global $wpdb;
        
        // Tabla para logs de consultas
        $table_name = $wpdb->prefix . 'cp_query_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
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
            KEY created_at (created_at)
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
        
        // Nueva tabla para logs de búsquedas del frontend
        $frontend_logs_table = $wpdb->prefix . 'cp_frontend_logs';
        
        $sql3 = "CREATE TABLE $frontend_logs_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            profile_type varchar(20) NOT NULL,
            fecha_inicio date NOT NULL,
            fecha_fin date NOT NULL,
            numero_documento varchar(50) NOT NULL,
            search_sources text NOT NULL,
            results_found int DEFAULT 0,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($sql3);
    }
    
    /**
     * Desactivación del plugin
     */
    public function deactivate() {
        // Limpiar tareas programadas
        wp_clear_scheduled_hook('cp_cleanup_logs');
        wp_clear_scheduled_hook('cp_cleanup_temp_files');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log de desactivación
        error_log('CP: Plugin desactivado');
        
        // Hook de desactivación
        do_action('cp_plugin_deactivated');
    }
    
    /**
     * Desinstalación del plugin
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
            'cp_secopii_method'
        );
        
        foreach ($options_to_delete as $option) {
            delete_option($option);
        }
        
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

// Hook para desarrolladores - se ejecuta cuando el plugin está completamente cargado
do_action('cp_plugin_init');