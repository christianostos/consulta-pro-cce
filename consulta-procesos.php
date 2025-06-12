<?php
/**
 * Plugin Name: Consulta Procesos
 * Plugin URI: https://tu-sitio.com/consulta-procesos
 * Description: Plugin para consultar procesos desde una base de datos SQL Server externa
 * Version: 1.0.0
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
define('CP_PLUGIN_VERSION', '1.0.0');

/**
 * Clase principal del plugin Consulta Procesos
 */
class ConsultaProcesos {
    
    private static $instance = null;
    
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
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_cp_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_cp_get_tables', array($this, 'ajax_get_tables'));
        add_action('wp_ajax_cp_diagnose_system', array($this, 'ajax_diagnose_system'));
        
        // Hooks de activación y desactivación
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Inicialización del plugin
     */
    public function init() {
        // Cargar archivos de idioma
        load_plugin_textdomain('consulta-procesos', false, dirname(plugin_basename(__FILE__)) . '/languages');
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
            array($this, 'admin_page'),
            'dashicons-database-view',
            30
        );
        
        add_submenu_page(
            'consulta-procesos',
            __('Configuración', 'consulta-procesos'),
            __('Configuración', 'consulta-procesos'),
            'manage_options',
            'consulta-procesos-config',
            array($this, 'config_page')
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
        add_settings_field(
            'cp_db_server',
            __('Servidor', 'consulta-procesos'),
            array($this, 'server_field_callback'),
            'consulta-procesos-config',
            'cp_db_section'
        );
        
        add_settings_field(
            'cp_db_database',
            __('Base de Datos', 'consulta-procesos'),
            array($this, 'database_field_callback'),
            'consulta-procesos-config',
            'cp_db_section'
        );
        
        add_settings_field(
            'cp_db_username',
            __('Usuario', 'consulta-procesos'),
            array($this, 'username_field_callback'),
            'consulta-procesos-config',
            'cp_db_section'
        );
        
        add_settings_field(
            'cp_db_password',
            __('Contraseña', 'consulta-procesos'),
            array($this, 'password_field_callback'),
            'consulta-procesos-config',
            'cp_db_section'
        );
        
        add_settings_field(
            'cp_db_port',
            __('Puerto', 'consulta-procesos'),
            array($this, 'port_field_callback'),
            'consulta-procesos-config',
            'cp_db_section'
        );
        
        // Agregar estilos y scripts de admin
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
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
                    'loading_tables' => __('Cargando tablas...', 'consulta-procesos')
                )
            ));
        }
    }
    
    /**
     * Página principal de administración
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Consulta Procesos', 'consulta-procesos'); ?></h1>
            
            <div class="cp-dashboard">
                <div class="cp-card">
                    <h2><?php _e('Diagnóstico del Sistema', 'consulta-procesos'); ?></h2>
                    <div id="system-diagnosis">
                        <button id="diagnose-system" class="button">
                            <?php _e('Ejecutar Diagnóstico', 'consulta-procesos'); ?>
                        </button>
                        <div id="diagnosis-result"></div>
                    </div>
                </div>
                
                <div class="cp-card">
                    <h2><?php _e('Estado de Conexión', 'consulta-procesos'); ?></h2>
                    <div id="connection-status">
                        <button id="test-connection" class="button button-primary">
                            <?php _e('Probar Conexión', 'consulta-procesos'); ?>
                        </button>
                        <div id="connection-result"></div>
                    </div>
                </div>
                
                <div class="cp-card">
                    <h2><?php _e('Tablas Disponibles', 'consulta-procesos'); ?></h2>
                    <div id="tables-container">
                        <button id="load-tables" class="button">
                            <?php _e('Cargar Tablas', 'consulta-procesos'); ?>
                        </button>
                        <div id="tables-list"></div>
                    </div>
                </div>
                
                <div class="cp-card">
                    <h2><?php _e('Información del Plugin', 'consulta-procesos'); ?></h2>
                    <div class="plugin-info">
                        <p><strong>Versión:</strong> <?php echo CP_PLUGIN_VERSION; ?></p>
                        <p><strong>PHP:</strong> <?php echo PHP_VERSION; ?></p>
                        <p><strong>Sistema:</strong> <?php echo PHP_OS; ?></p>
                        <p><strong>WordPress:</strong> <?php echo get_bloginfo('version'); ?></p>
                        
                        <h4>Extensiones Disponibles:</h4>
                        <ul class="extensions-list">
                            <li>PDO SQLSRV: <?php echo extension_loaded('pdo_sqlsrv') ? '✅ Disponible' : '❌ No disponible'; ?></li>
                            <li>SQLSRV: <?php echo extension_loaded('sqlsrv') ? '✅ Disponible' : '❌ No disponible'; ?></li>
                            <li>OpenSSL: <?php echo extension_loaded('openssl') ? '✅ Disponible' : '❌ No disponible'; ?></li>
                        </ul>
                        
                        <?php if (!extension_loaded('pdo_sqlsrv') && !extension_loaded('sqlsrv')): ?>
                        <div class="notice notice-error inline">
                            <p><strong>⚠️ Atención:</strong> No tienes las extensiones de SQL Server instaladas. 
                            <a href="#" id="show-install-instructions">Ver instrucciones de instalación</a></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div id="install-instructions" style="display: none;" class="cp-card">
                <h2>Instrucciones de Instalación de Extensiones</h2>
                <div class="install-tabs">
                    <button class="tab-button active" data-tab="ubuntu">Ubuntu/Debian</button>
                    <button class="tab-button" data-tab="centos">CentOS/RHEL</button>
                    <button class="tab-button" data-tab="windows">Windows</button>
                </div>
                
                <div id="ubuntu-tab" class="tab-content active">
                    <h4>Para Ubuntu/Debian:</h4>
                    <pre><code># Instalar drivers de Microsoft
curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add -
curl https://packages.microsoft.com/config/ubuntu/20.04/prod.list > /etc/apt/sources.list.d/mssql-release.list
apt-get update
ACCEPT_EULA=Y apt-get install -y msodbcsql17 unixodbc-dev

# Instalar extensiones PHP
apt-get install -y php-dev php-pear
pecl install sqlsrv pdo_sqlsrv

# Agregar a php.ini
echo "extension=pdo_sqlsrv.so" >> /etc/php/8.0/apache2/php.ini
echo "extension=sqlsrv.so" >> /etc/php/8.0/apache2/php.ini

# Reiniciar Apache
systemctl restart apache2</code></pre>
                </div>
                
                <div id="centos-tab" class="tab-content">
                    <h4>Para CentOS/RHEL:</h4>
                    <pre><code># Instalar repositorio de Microsoft
curl https://packages.microsoft.com/config/rhel/8/prod.repo > /etc/yum.repos.d/mssql-release.repo
yum remove unixODBC-utf16 unixODBC-utf16-devel
ACCEPT_EULA=Y yum install -y msodbcsql17 unixODBC-devel

# Instalar extensiones
yum install -y php-devel php-pear
pecl install sqlsrv pdo_sqlsrv

# Reiniciar servidor web
systemctl restart httpd</code></pre>
                </div>
                
                <div id="windows-tab" class="tab-content">
                    <h4>Para Windows:</h4>
                    <ol>
                        <li>Descarga los drivers desde <a href="https://docs.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server" target="_blank">Microsoft</a></li>
                        <li>Copia los archivos .dll a la carpeta ext/ de PHP</li>
                        <li>Agrega estas líneas a php.ini:</li>
                    </ol>
                    <pre><code>extension=php_sqlsrv.dll
extension=php_pdo_sqlsrv.dll</code></pre>
                    <p>Reinicia el servidor web después de los cambios.</p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Página de configuración
     */
    public function config_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Configuración - Consulta Procesos', 'consulta-procesos'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('cp_settings_group');
                do_settings_sections('consulta-procesos-config');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Callbacks para campos de configuración
     */
    public function db_section_callback() {
        echo '<p>' . __('Configura los parámetros de conexión a tu servidor SQL Server.', 'consulta-procesos') . '</p>';
    }
    
    public function server_field_callback() {
        $value = get_option('cp_db_server', '');
        echo '<input type="text" name="cp_db_server" value="' . esc_attr($value) . '" class="regular-text" placeholder="192.168.1.100" />';
        echo '<p class="description">' . __('Dirección IP o nombre del servidor SQL Server', 'consulta-procesos') . '</p>';
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
     * Método para conectar a SQL Server
     */
    private function get_db_connection() {
        $server = get_option('cp_db_server');
        $database = get_option('cp_db_database');
        $username = get_option('cp_db_username');
        $password = get_option('cp_db_password');
        $port = get_option('cp_db_port', '1433');
        
        if (empty($server) || empty($database) || empty($username)) {
            return array('success' => false, 'error' => 'Faltan parámetros de conexión requeridos');
        }
        
        // Log para debugging
        error_log("CP: Intentando conectar a {$server},{$port} - DB: {$database} - User: {$username}");
        
        try {
            // Verificar extensiones disponibles
            $extensions_info = $this->get_available_extensions();
            error_log("CP: Extensiones disponibles: " . json_encode($extensions_info));
            
            // Intentar conexión con PDO SQLSRV
            if (extension_loaded('pdo_sqlsrv')) {
                error_log("CP: Intentando conexión con PDO SQLSRV");
                
                // DSN con configuración específica para Docker/Windows
                $dsn = "sqlsrv:server={$server},{$port};Database={$database};TrustServerCertificate=1;ConnectionPooling=0";
                
                // Opciones PDO compatibles con SQL Server
                $options = array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    // Removido PDO::ATTR_TIMEOUT ya que no es compatible con SQLSRV
                    // Configuración de encoding específica para SQLSRV
                    PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
                    // Configuración de timeout específica para SQLSRV
                    PDO::SQLSRV_ATTR_QUERY_TIMEOUT => 30
                );
                
                $conn = new PDO($dsn, $username, $password, $options);
                error_log("CP: Conexión PDO exitosa");
                
                // Probar la conexión con una consulta simple
                $stmt = $conn->query("SELECT 1 as test");
                $result = $stmt->fetch();
                if ($result['test'] != 1) {
                    throw new Exception("Prueba de conexión falló");
                }
                
                return array('success' => true, 'connection' => $conn, 'method' => 'PDO SQLSRV');
            }
            // Intentar conexión con SQLSRV nativo
            elseif (extension_loaded('sqlsrv')) {
                error_log("CP: Intentando conexión con SQLSRV nativo");
                
                $connectionInfo = array(
                    "Database" => $database,
                    "UID" => $username,
                    "PWD" => $password,
                    "TrustServerCertificate" => 1,
                    "ConnectionPooling" => 0,
                    "LoginTimeout" => 30,
                    "CharacterSet" => "UTF-8"
                );
                
                $conn = sqlsrv_connect("{$server},{$port}", $connectionInfo);
                
                if ($conn === false) {
                    $errors = sqlsrv_errors();
                    $error_msg = "Error SQLSRV: ";
                    foreach ($errors as $error) {
                        $error_msg .= "[{$error['SQLSTATE']}] {$error['message']} ";
                    }
                    error_log("CP: " . $error_msg);
                    return array('success' => false, 'error' => $error_msg);
                }
                
                // Probar la conexión
                $stmt = sqlsrv_query($conn, "SELECT 1 as test");
                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    $error_msg = "Error en prueba de conexión: ";
                    foreach ($errors as $error) {
                        $error_msg .= $error['message'] . " ";
                    }
                    sqlsrv_close($conn);
                    return array('success' => false, 'error' => $error_msg);
                }
                
                sqlsrv_free_stmt($stmt);
                error_log("CP: Conexión SQLSRV exitosa");
                return array('success' => true, 'connection' => $conn, 'method' => 'SQLSRV nativo');
            }
            else {
                $error = 'No hay extensiones de SQL Server disponibles. Extensiones PHP encontradas: ' . implode(', ', get_loaded_extensions());
                error_log("CP: " . $error);
                return array('success' => false, 'error' => $error);
            }
        } catch (PDOException $e) {
            $error_msg = 'Error PDO: ' . $e->getMessage() . ' | Código: ' . $e->getCode();
            
            // Proporcionar sugerencias específicas basadas en el error
            if (strpos($e->getMessage(), 'unsupported attribute') !== false) {
                $error_msg .= ' | SUGERENCIA: Problema de compatibilidad PDO corregido. Reintentando...';
                error_log('CP: ' . $error_msg);
                
                // Reintentar con configuración mínima
                try {
                    $dsn_simple = "sqlsrv:server={$server},{$port};Database={$database};TrustServerCertificate=1";
                    $options_simple = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);
                    $conn = new PDO($dsn_simple, $username, $password, $options_simple);
                    error_log("CP: Conexión PDO con configuración mínima exitosa");
                    return array('success' => true, 'connection' => $conn, 'method' => 'PDO SQLSRV (modo simple)');
                } catch (Exception $e2) {
                    $error_msg = 'Error en reintento: ' . $e2->getMessage();
                }
            } elseif (strpos($e->getMessage(), 'could not find driver') !== false) {
                $error_msg .= ' | SUGERENCIA: Driver PDO SQLSRV no encontrado o mal configurado';
            } elseif (strpos($e->getMessage(), 'Login timeout') !== false || strpos($e->getMessage(), 'timeout') !== false) {
                $error_msg .= ' | SUGERENCIA: Problema de conectividad. Verifica firewall y que SQL Server esté ejecutándose';
            } elseif (strpos($e->getMessage(), 'Login failed') !== false) {
                $error_msg .= ' | SUGERENCIA: Credenciales incorrectas o usuario sin permisos';
            }
            
            error_log('CP: ' . $error_msg);
            return array('success' => false, 'error' => $error_msg);
        } catch (Exception $e) {
            $error_msg = 'Error general de conexión: ' . $e->getMessage() . ' | Código: ' . $e->getCode();
            error_log('CP: ' . $error_msg);
            return array('success' => false, 'error' => $error_msg);
        }
    }
    
    /**
     * Obtener información de extensiones disponibles
     */
    private function get_available_extensions() {
        $extensions = array();
        $sql_extensions = array('pdo_sqlsrv', 'sqlsrv', 'pdo', 'odbc', 'pdo_odbc');
        
        foreach ($sql_extensions as $ext) {
            $extensions[$ext] = extension_loaded($ext);
        }
        
        return $extensions;
    }
    
    /**
     * Diagnosticar sistema para SQL Server
     */
    public function diagnose_system() {
        $diagnosis = array();
        
        // Verificar extensiones
        $diagnosis['extensions'] = $this->get_available_extensions();
        
        // Verificar versión de PHP
        $diagnosis['php_version'] = PHP_VERSION;
        
        // Verificar sistema operativo
        $diagnosis['os'] = PHP_OS;
        
        // Verificar si OpenSSL está disponible
        $diagnosis['openssl'] = extension_loaded('openssl');
        
        // Verificar configuración de PHP relevante
        $diagnosis['allow_url_fopen'] = ini_get('allow_url_fopen');
        $diagnosis['max_execution_time'] = ini_get('max_execution_time');
        
        return $diagnosis;
    }
    
    /**
     * AJAX: Probar conexión
     */
    public function ajax_test_connection() {
        check_ajax_referer('cp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        $result = $this->get_db_connection();
        
        if ($result['success']) {
            wp_send_json_success(array(
                'message' => __('Conexión exitosa a la base de datos', 'consulta-procesos') . ' (' . $result['method'] . ')',
                'method' => $result['method']
            ));
        } else {
            // Obtener diagnóstico del sistema
            $diagnosis = $this->diagnose_system();
            
            wp_send_json_error(array(
                'message' => $result['error'],
                'diagnosis' => $diagnosis,
                'suggestions' => $this->get_connection_suggestions($diagnosis)
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
        
        $result = $this->get_db_connection();
        
        if (!$result['success']) {
            wp_send_json_error(array(
                'message' => __('No hay conexión a la base de datos: ', 'consulta-procesos') . $result['error']
            ));
        }
        
        $connection = $result['connection'];
        
        try {
            $tables = array();
            
            if ($connection instanceof PDO) {
                // Consulta para PDO
                $query = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME";
                $stmt = $connection->prepare($query);
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($results as $row) {
                    $tables[] = $row['TABLE_NAME'];
                }
            } else {
                // Consulta para SQLSRV
                $query = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME";
                $stmt = sqlsrv_query($connection, $query);
                
                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    $error_msg = '';
                    foreach ($errors as $error) {
                        $error_msg .= $error['message'] . ' ';
                    }
                    throw new Exception('Error en la consulta: ' . $error_msg);
                }
                
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $tables[] = $row['TABLE_NAME'];
                }
                sqlsrv_free_stmt($stmt);
            }
            
            wp_send_json_success(array(
                'tables' => $tables,
                'count' => count($tables),
                'method' => $result['method']
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => __('Error al obtener las tablas: ', 'consulta-procesos') . $e->getMessage()
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
        
        $diagnosis = $this->diagnose_system();
        
        wp_send_json_success(array(
            'diagnosis' => $diagnosis,
            'suggestions' => $this->get_connection_suggestions($diagnosis)
        ));
    }
    
    /**
     * Obtener sugerencias basadas en el diagnóstico
     */
    private function get_connection_suggestions($diagnosis) {
        $suggestions = array();
        
        if (!$diagnosis['extensions']['pdo_sqlsrv'] && !$diagnosis['extensions']['sqlsrv']) {
            $suggestions[] = '❌ No tienes las extensiones de SQL Server instaladas. Necesitas instalar pdo_sqlsrv o sqlsrv.';
            
            if (strpos($diagnosis['os'], 'Linux') !== false) {
                $suggestions[] = '💡 En Linux, ejecuta: sudo pecl install sqlsrv pdo_sqlsrv';
            } elseif (strpos($diagnosis['os'], 'WIN') !== false) {
                $suggestions[] = '💡 En Windows, descarga los drivers desde Microsoft y agrega las extensiones a php.ini';
            }
        } elseif ($diagnosis['extensions']['pdo_sqlsrv'] || $diagnosis['extensions']['sqlsrv']) {
            $suggestions[] = '✅ Las extensiones están disponibles. El problema puede ser de conectividad:';
            
            // Detectar si estamos en Docker
            if (file_exists('/.dockerenv') || file_exists('/proc/1/cgroup')) {
                $suggestions[] = '🐳 <strong>DOCKER DETECTADO:</strong> Estás ejecutando WordPress en Docker.';
                $suggestions[] = '🔧 En lugar de localhost o 127.0.0.1, usa: <code>host.docker.internal</code>';
                $suggestions[] = '🔧 Si tu SQL Server está en Windows, usa la IP interna: <code>172.17.0.1</code>';
                $suggestions[] = '🔧 O la IP de tu máquina Windows en la red local (ej: 192.168.x.x)';
                $suggestions[] = '🛡️ Verifica que Windows Firewall permita conexiones en puerto 1433';
                $suggestions[] = '📖 Ejecuta <code>ipconfig</code> en Windows para obtener tu IP local';
            } else {
                $suggestions[] = '🔍 Verifica que SQL Server permita conexiones remotas';
                $suggestions[] = '🔍 Confirma que el puerto 1433 esté abierto';
                $suggestions[] = '🔍 Verifica las credenciales de usuario';
                $suggestions[] = '🔍 Confirma que la base de datos existe';
            }
        }
        
        if (!$diagnosis['openssl']) {
            $suggestions[] = '⚠️ OpenSSL no está disponible. Esto puede causar problemas con conexiones seguras.';
        }
        
        if (version_compare($diagnosis['php_version'], '7.4', '<')) {
            $suggestions[] = '⚠️ Tu versión de PHP (' . $diagnosis['php_version'] . ') es muy antigua. Considera actualizar.';
        }
        
        return $suggestions;
    }
    
    /**
     * Activación del plugin
     */
    public function activate() {
        // Crear opciones por defecto
        add_option('cp_db_port', '1433');
        
        // Crear tabla de log si es necesario
        // $this->create_log_table();
    }
    
    /**
     * Desactivación del plugin
     */
    public function deactivate() {
        // Limpiar tareas programadas si las hay
    }
}

// Inicializar el plugin
ConsultaProcesos::get_instance();