<?php
/**
 * Clase para manejar todas las operaciones de base de datos
 * 
 * Archivo: includes/class-cp-database.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_Database {
    
    private static $instance = null;
    private $connection = null;
    private $connection_method = null;
    private $last_error = null;
    
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
     * Constructor privado
     */
    private function __construct() {
        // Constructor vacío para singleton
    }
    
    /**
     * Obtener configuración de conexión
     */
    private function get_connection_config() {
        return array(
            'server' => get_option('cp_db_server'),
            'database' => get_option('cp_db_database'),
            'username' => get_option('cp_db_username'),
            'password' => get_option('cp_db_password'),
            'port' => get_option('cp_db_port', '1433')
        );
    }
    
    /**
     * Validar configuración de conexión
     */
    private function validate_config($config) {
        $required = array('server', 'database', 'username');
        foreach ($required as $field) {
            if (empty($config[$field])) {
                return array(
                    'valid' => false,
                    'error' => "Falta configurar: {$field}"
                );
            }
        }
        return array('valid' => true);
    }
    
    /**
     * Conectar a la base de datos
     */
    public function connect() {
        $config = $this->get_connection_config();
        
        // Validar configuración
        $validation = $this->validate_config($config);
        if (!$validation['valid']) {
            $this->last_error = $validation['error'];
            return array('success' => false, 'error' => $validation['error']);
        }
        
        // Log para debugging
        error_log("CP_Database: Intentando conectar a {$config['server']},{$config['port']} - DB: {$config['database']}");
        
        try {
            // Verificar extensiones disponibles
            $extensions_info = $this->get_available_extensions();
            error_log("CP_Database: Extensiones disponibles: " . json_encode($extensions_info));
            
            // Intentar conexión con PDO SQLSRV
            if (extension_loaded('pdo_sqlsrv')) {
                $result = $this->connect_pdo($config);
                if ($result['success']) {
                    return $result;
                }
            }
            
            // Intentar conexión con SQLSRV nativo
            if (extension_loaded('sqlsrv')) {
                $result = $this->connect_sqlsrv($config);
                if ($result['success']) {
                    return $result;
                }
            }
            
            $error = 'No hay extensiones de SQL Server disponibles o todas las conexiones fallaron';
            $this->last_error = $error;
            return array('success' => false, 'error' => $error);
            
        } catch (Exception $e) {
            $error = 'Error general de conexión: ' . $e->getMessage();
            $this->last_error = $error;
            error_log('CP_Database: ' . $error);
            return array('success' => false, 'error' => $error);
        }
    }
    
    /**
     * Conectar usando PDO SQLSRV
     */
    private function connect_pdo($config) {
        try {
            error_log("CP_Database: Intentando conexión con PDO SQLSRV");
            
            // DSN con configuración optimizada
            $dsn = "sqlsrv:server={$config['server']},{$config['port']};Database={$config['database']};TrustServerCertificate=1;ConnectionPooling=0";
            
            // Opciones PDO compatibles con SQL Server
            $options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,
                PDO::SQLSRV_ATTR_QUERY_TIMEOUT => 30
            );
            
            $conn = new PDO($dsn, $config['username'], $config['password'], $options);
            
            // Probar la conexión
            $stmt = $conn->query("SELECT 1 as test");
            $result = $stmt->fetch();
            if ($result['test'] != 1) {
                throw new Exception("Prueba de conexión falló");
            }
            
            $this->connection = $conn;
            $this->connection_method = 'PDO SQLSRV';
            
            error_log("CP_Database: Conexión PDO exitosa");
            return array(
                'success' => true, 
                'connection' => $conn, 
                'method' => 'PDO SQLSRV'
            );
            
        } catch (PDOException $e) {
            // Si falla por atributos incompatibles, reintentar con configuración mínima
            if (strpos($e->getMessage(), 'unsupported attribute') !== false) {
                try {
                    $dsn_simple = "sqlsrv:server={$config['server']},{$config['port']};Database={$config['database']};TrustServerCertificate=1";
                    $options_simple = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);
                    $conn = new PDO($dsn_simple, $config['username'], $config['password'], $options_simple);
                    
                    $this->connection = $conn;
                    $this->connection_method = 'PDO SQLSRV (modo simple)';
                    
                    error_log("CP_Database: Conexión PDO con configuración mínima exitosa");
                    return array(
                        'success' => true, 
                        'connection' => $conn, 
                        'method' => 'PDO SQLSRV (modo simple)'
                    );
                } catch (Exception $e2) {
                    $error = 'Error PDO en reintento: ' . $e2->getMessage();
                    error_log("CP_Database: " . $error);
                    return array('success' => false, 'error' => $error);
                }
            }
            
            $error = 'Error PDO: ' . $e->getMessage() . ' | Código: ' . $e->getCode();
            error_log("CP_Database: " . $error);
            return array('success' => false, 'error' => $error);
        }
    }
    
    /**
     * Conectar usando SQLSRV nativo
     */
    private function connect_sqlsrv($config) {
        try {
            error_log("CP_Database: Intentando conexión con SQLSRV nativo");
            
            $connectionInfo = array(
                "Database" => $config['database'],
                "UID" => $config['username'],
                "PWD" => $config['password'],
                "TrustServerCertificate" => 1,
                "ConnectionPooling" => 0,
                "LoginTimeout" => 30,
                "CharacterSet" => "UTF-8"
            );
            
            $conn = sqlsrv_connect("{$config['server']},{$config['port']}", $connectionInfo);
            
            if ($conn === false) {
                $errors = sqlsrv_errors();
                $error_msg = "Error SQLSRV: ";
                foreach ($errors as $error) {
                    $error_msg .= "[{$error['SQLSTATE']}] {$error['message']} ";
                }
                error_log("CP_Database: " . $error_msg);
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
            
            $this->connection = $conn;
            $this->connection_method = 'SQLSRV nativo';
            
            error_log("CP_Database: Conexión SQLSRV exitosa");
            return array(
                'success' => true, 
                'connection' => $conn, 
                'method' => 'SQLSRV nativo'
            );
            
        } catch (Exception $e) {
            $error = 'Error SQLSRV: ' . $e->getMessage();
            error_log("CP_Database: " . $error);
            return array('success' => false, 'error' => $error);
        }
    }
    
    /**
     * Obtener la conexión actual
     */
    public function get_connection() {
        if ($this->connection === null) {
            $result = $this->connect();
            if (!$result['success']) {
                return false;
            }
        }
        return $this->connection;
    }
    
    /**
     * Obtener método de conexión usado
     */
    public function get_connection_method() {
        return $this->connection_method;
    }
    
    /**
     * Probar conexión
     */
    public function test_connection() {
        $result = $this->connect();
        
        if ($result['success']) {
            return array(
                'success' => true,
                'message' => 'Conexión exitosa usando ' . $result['method'],
                'method' => $result['method']
            );
        } else {
            return array(
                'success' => false,
                'error' => $result['error'],
                'suggestions' => $this->get_connection_suggestions()
            );
        }
    }
    
    /**
     * Obtener lista de tablas
     */
    public function get_tables() {
        $connection = $this->get_connection();
        
        if (!$connection) {
            return array(
                'success' => false,
                'error' => 'No hay conexión disponible: ' . $this->last_error
            );
        }
        
        try {
            $tables = array();
            $query = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME";
            
            if ($this->connection_method && strpos($this->connection_method, 'PDO') !== false) {
                // Usar PDO
                $stmt = $connection->prepare($query);
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($results as $row) {
                    $tables[] = $row['TABLE_NAME'];
                }
            } else {
                // Usar SQLSRV nativo
                $stmt = sqlsrv_query($connection, $query);
                
                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    $error_msg = 'Error en consulta de tablas: ';
                    foreach ($errors as $error) {
                        $error_msg .= $error['message'] . ' ';
                    }
                    throw new Exception($error_msg);
                }
                
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $tables[] = $row['TABLE_NAME'];
                }
                sqlsrv_free_stmt($stmt);
            }
            
            return array(
                'success' => true,
                'tables' => $tables,
                'count' => count($tables),
                'method' => $this->connection_method
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Error al obtener tablas: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Ejecutar consulta personalizada
     */
    public function execute_query($sql, $params = array()) {
        $connection = $this->get_connection();
        
        if (!$connection) {
            return array(
                'success' => false,
                'error' => 'No hay conexión disponible'
            );
        }
        
        try {
            if ($this->connection_method && strpos($this->connection_method, 'PDO') !== false) {
                // Usar PDO
                $stmt = $connection->prepare($sql);
                $stmt->execute($params);
                
                // Detectar tipo de consulta
                $sql_type = strtoupper(trim(substr($sql, 0, 6)));
                
                if ($sql_type == 'SELECT') {
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    return array(
                        'success' => true,
                        'data' => $results,
                        'rows' => count($results),
                        'type' => 'SELECT'
                    );
                } else {
                    $affected = $stmt->rowCount();
                    return array(
                        'success' => true,
                        'affected_rows' => $affected,
                        'type' => $sql_type
                    );
                }
            } else {
                // Usar SQLSRV nativo
                $stmt = sqlsrv_query($connection, $sql, $params);
                
                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    $error_msg = 'Error en consulta: ';
                    foreach ($errors as $error) {
                        $error_msg .= $error['message'] . ' ';
                    }
                    throw new Exception($error_msg);
                }
                
                $sql_type = strtoupper(trim(substr($sql, 0, 6)));
                
                if ($sql_type == 'SELECT') {
                    $results = array();
                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        $results[] = $row;
                    }
                    
                    sqlsrv_free_stmt($stmt);
                    return array(
                        'success' => true,
                        'data' => $results,
                        'rows' => count($results),
                        'type' => 'SELECT'
                    );
                } else {
                    $affected = sqlsrv_rows_affected($stmt);
                    sqlsrv_free_stmt($stmt);
                    return array(
                        'success' => true,
                        'affected_rows' => $affected,
                        'type' => $sql_type
                    );
                }
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Error ejecutando consulta: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Obtener información de extensiones disponibles
     */
    public function get_available_extensions() {
        $extensions = array();
        $sql_extensions = array('pdo_sqlsrv', 'sqlsrv', 'pdo', 'openssl');
        
        foreach ($sql_extensions as $ext) {
            $extensions[$ext] = extension_loaded($ext);
        }
        
        return $extensions;
    }
    
    /**
     * Diagnosticar sistema
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
        
        // Verificar si estamos en Docker
        $diagnosis['docker'] = file_exists('/.dockerenv') || file_exists('/proc/1/cgroup');
        
        // Verificar configuración de PHP relevante
        $diagnosis['allow_url_fopen'] = ini_get('allow_url_fopen');
        $diagnosis['max_execution_time'] = ini_get('max_execution_time');
        
        return $diagnosis;
    }
    
    /**
     * Obtener sugerencias de conexión
     */
    public function get_connection_suggestions() {
        $diagnosis = $this->diagnose_system();
        $suggestions = array();
        
        if (!$diagnosis['extensions']['pdo_sqlsrv'] && !$diagnosis['extensions']['sqlsrv']) {
            $suggestions[] = '❌ No tienes las extensiones de SQL Server instaladas.';
        } elseif ($diagnosis['extensions']['pdo_sqlsrv'] || $diagnosis['extensions']['sqlsrv']) {
            $suggestions[] = '✅ Las extensiones están disponibles. El problema puede ser de conectividad:';
            
            if ($diagnosis['docker']) {
                $suggestions[] = '🐳 <strong>DOCKER DETECTADO:</strong> Estás ejecutando WordPress en Docker.';
                $suggestions[] = '🔧 En lugar de localhost o 127.0.0.1, usa: <code>host.docker.internal</code>';
                $suggestions[] = '🔧 Si tu SQL Server está en Windows, usa la IP interna: <code>172.17.0.1</code>';
                $suggestions[] = '🔧 O la IP de tu máquina Windows en la red local (ej: 192.168.x.x)';
                $suggestions[] = '🛡️ Verifica que Windows Firewall permita conexiones en puerto 1433';
            } else {
                $suggestions[] = '🔍 Verifica que SQL Server permita conexiones remotas';
                $suggestions[] = '🔍 Confirma que el puerto 1433 esté abierto';
            }
            
            $suggestions[] = '🔍 Verifica las credenciales de usuario';
            $suggestions[] = '🔍 Confirma que la base de datos existe';
        }
        
        return $suggestions;
    }
    
    /**
     * Cerrar conexión
     */
    public function close_connection() {
        if ($this->connection) {
            if ($this->connection_method && strpos($this->connection_method, 'PDO') !== false) {
                $this->connection = null;
            } else {
                sqlsrv_close($this->connection);
                $this->connection = null;
            }
            $this->connection_method = null;
        }
    }
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->close_connection();
    }
}