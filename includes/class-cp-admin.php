<?php
/**
 * Clase para manejar todas las vistas y funcionalidades del panel de administración
 * 
 * Archivo: includes/class-cp-admin.php
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
    }
    
    /**
     * Inicializar hooks de WordPress
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        
        // Hooks AJAX
        add_action('wp_ajax_cp_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_cp_get_tables', array($this, 'ajax_get_tables'));
        add_action('wp_ajax_cp_diagnose_system', array($this, 'ajax_diagnose_system'));
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
            __('Configuración', 'consulta-procesos'),
            __('Configuración', 'consulta-procesos'),
            'manage_options',
            'consulta-procesos-config',
            array($this, 'render_config_page')
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
    }
    
    /**
     * Inicializar configuraciones de administración
     */
    public function admin_init() {
        // Registrar configuraciones
        register_setting('cp_settings_group', 'cp_db_server');
        register_setting('cp_settings_group', 'cp_db_database');
        register_setting('cp_settings_group', 'cp_db_username');
        register_setting('cp_settings_group', 'cp_db_password');
        register_setting('cp_settings_group', 'cp_db_port');
        
        // Secciones de configuración
        add_settings_section(
            'cp_db_section',
            __('Configuración de Base de Datos', 'consulta-procesos'),
            array($this, 'db_section_callback'),
            'consulta-procesos-config'
        );
        
        // Campos de configuración
        $this->add_settings_fields();
    }
    
    /**
     * Agregar campos de configuración
     */
    private function add_settings_fields() {
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
                'nonce' => wp_create_nonce('cp_nonce'),
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
     * Renderizar página de configuración
     */
    public function render_config_page() {
        include_once CP_PLUGIN_PATH . 'admin/views/config.php';
    }
    
    /**
     * Renderizar página de nueva consulta
     */
    public function render_query_page() {
        include_once CP_PLUGIN_PATH . 'admin/views/query.php';
    }
    
    /**
     * Callbacks para campos de configuración
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
        check_ajax_referer('cp_nonce', 'nonce');
        
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
        check_ajax_referer('cp_nonce', 'nonce');
        
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
        check_ajax_referer('cp_nonce', 'nonce');
        
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
     * Generar nonce para formularios
     */
    public function get_nonce_field($action = 'cp_nonce') {
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
}