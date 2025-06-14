<?php
/**
 * Clase para ejecutar consultas SQL de manera segura
 * 
 * Archivo: includes/class-cp-query-executor.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_Query_Executor {
    
    private static $instance = null;
    private $db;
    private $security;
    private $max_execution_time = 360;
    private $max_rows = 1000;
    
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
        if (class_exists('CP_Database')) {
            $this->db = CP_Database::get_instance();
        }
        
        if (class_exists('CP_Security')) {
            $this->security = CP_Security::get_instance();
        }
        
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_cp_execute_query', array($this, 'ajax_execute_query'));
        add_action('wp_ajax_cp_get_table_structure', array($this, 'ajax_get_table_structure'));
        add_action('wp_ajax_cp_save_query', array($this, 'ajax_save_query'));
        add_action('wp_ajax_cp_load_saved_queries', array($this, 'ajax_load_saved_queries'));
    }
    
    /**
     * Ejecutar consulta SQL
     */
    public function execute_query($sql, $params = array()) {
        $start_time = microtime(true);
        
        // Validar la consulta
        $validation = $this->validate_query($sql);
        if (!$validation['valid']) {
            return array(
                'success' => false,
                'error' => implode(', ', $validation['errors']),
                'execution_time' => 0
            );
        }
        
        // Obtener conexión
        if (!$this->db) {
            return array(
                'success' => false,
                'error' => 'Clase de base de datos no disponible',
                'execution_time' => 0
            );
        }
        
        $connection_result = $this->db->connect();
        if (!$connection_result['success']) {
            return array(
                'success' => false,
                'error' => 'Error de conexión: ' . $connection_result['error'],
                'execution_time' => 0
            );
        }
        
        $connection = $connection_result['connection'];
        $method = $connection_result['method'];
        
        try {
            // Configurar timeout
            set_time_limit($this->max_execution_time);
            
            // Ejecutar consulta según el método de conexión
            if (strpos($method, 'PDO') !== false) {
                $result = $this->execute_pdo_query($connection, $sql, $params);
            } else {
                $result = $this->execute_sqlsrv_query($connection, $sql, $params);
            }
            
            $execution_time = microtime(true) - $start_time;
            
            if ($result['success']) {
                // Log de consulta exitosa
                $this->log_query($sql, $execution_time, count($result['data']), 'success');
                
                return array(
                    'success' => true,
                    'data' => $result['data'],
                    'columns' => $result['columns'],
                    'total_rows' => count($result['data']),
                    'execution_time' => $execution_time,
                    'method' => $method,
                    'query' => $validation['query']
                );
            } else {
                // Log de error
                $this->log_query($sql, $execution_time, 0, 'error', $result['error']);
                
                return array(
                    'success' => false,
                    'error' => $result['error'],
                    'execution_time' => $execution_time
                );
            }
            
        } catch (Exception $e) {
            $execution_time = microtime(true) - $start_time;
            $error_message = 'Error en ejecución: ' . $e->getMessage();
            
            // Log de excepción
            $this->log_query($sql, $execution_time, 0, 'exception', $error_message);
            
            return array(
                'success' => false,
                'error' => $error_message,
                'execution_time' => $execution_time
            );
        }
    }
    
    /**
     * Ejecutar consulta con PDO
     */
    private function execute_pdo_query($connection, $sql, $params) {
        try {
            $stmt = $connection->prepare($sql);
            $stmt->execute($params);
            
            // Obtener resultados
            $data = array();
            $columns = array();
            
            if ($stmt->columnCount() > 0) {
                // Es una consulta SELECT
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($data)) {
                    $columns = array_keys($data[0]);
                }
                
                // Limitar número de filas
                if (count($data) > $this->max_rows) {
                    $data = array_slice($data, 0, $this->max_rows);
                }
            } else {
                // Es una consulta de modificación (aunque no debería llegar aquí)
                $affected_rows = $stmt->rowCount();
                return array(
                    'success' => true,
                    'data' => array(),
                    'columns' => array(),
                    'affected_rows' => $affected_rows
                );
            }
            
            return array(
                'success' => true,
                'data' => $data,
                'columns' => $columns
            );
            
        } catch (PDOException $e) {
            return array(
                'success' => false,
                'error' => 'Error PDO: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Ejecutar consulta con SQLSRV
     */
    private function execute_sqlsrv_query($connection, $sql, $params) {
        try {
            $stmt = sqlsrv_query($connection, $sql, $params);
            
            if ($stmt === false) {
                $errors = sqlsrv_errors();
                $error_msg = 'Error SQLSRV: ';
                foreach ($errors as $error) {
                    $error_msg .= $error['message'] . ' ';
                }
                return array(
                    'success' => false,
                    'error' => $error_msg
                );
            }
            
            // Obtener resultados
            $data = array();
            $columns = array();
            $row_count = 0;
            
            while (($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) && $row_count < $this->max_rows) {
                if (empty($columns)) {
                    $columns = array_keys($row);
                }
                
                // Convertir objetos DateTime a strings
                foreach ($row as $key => $value) {
                    if ($value instanceof DateTime) {
                        $row[$key] = $value->format('Y-m-d H:i:s');
                    }
                }
                
                $data[] = $row;
                $row_count++;
            }
            
            sqlsrv_free_stmt($stmt);
            
            return array(
                'success' => true,
                'data' => $data,
                'columns' => $columns
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Error SQLSRV: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Validar consulta SQL
     */
    private function validate_query($sql) {
        // Usar la clase de seguridad si está disponible
        if ($this->security && method_exists($this->security, 'validate_sql_query')) {
            return $this->security->validate_sql_query($sql);
        }
        
        // Validación básica si no tenemos la clase de seguridad
        $result = array(
            'valid' => false,
            'query' => '',
            'errors' => array()
        );
        
        // Sanitizar consulta
        $sql = trim($sql);
        
        if (empty($sql)) {
            $result['errors'][] = 'La consulta no puede estar vacía.';
            return $result;
        }
        
        // Verificar longitud máxima
        if (strlen($sql) > 10000) {
            $result['errors'][] = 'La consulta es demasiado larga (máximo 10,000 caracteres).';
            return $result;
        }
        
        // Verificar que sea solo SELECT
        if (!preg_match('/^\s*SELECT\s+/i', $sql)) {
            $result['errors'][] = 'Solo se permiten consultas SELECT.';
            return $result;
        }
        
        // Verificar comandos peligrosos
        $dangerous_keywords = array(
            'INSERT', 'UPDATE', 'DELETE', 'DROP', 'CREATE', 'ALTER', 
            'TRUNCATE', 'EXEC', 'EXECUTE', 'MERGE', 'CALL', 'DO'
        );
        
        $sql_upper = strtoupper($sql);
        foreach ($dangerous_keywords as $keyword) {
            if (strpos($sql_upper, $keyword) !== false) {
                $result['errors'][] = 'La consulta contiene comandos no permitidos: ' . $keyword;
                return $result;
            }
        }
        
        $result['valid'] = true;
        $result['query'] = $sql;
        
        return $result;
    }
    
    /**
     * Obtener estructura de tabla
     */
    public function get_table_structure($table_name) {
        if (!$this->db) {
            return array(
                'success' => false,
                'error' => 'Base de datos no disponible'
            );
        }
        
        $connection_result = $this->db->connect();
        if (!$connection_result['success']) {
            return array(
                'success' => false,
                'error' => 'Error de conexión: ' . $connection_result['error']
            );
        }
        
        $connection = $connection_result['connection'];
        $method = $connection_result['method'];
        
        // Sanitizar nombre de tabla
        $table_name = preg_replace('/[^a-zA-Z0-9_]/', '', $table_name);
        
        $query = "
            SELECT 
                COLUMN_NAME,
                DATA_TYPE,
                IS_NULLABLE,
                COLUMN_DEFAULT,
                CHARACTER_MAXIMUM_LENGTH,
                NUMERIC_PRECISION,
                NUMERIC_SCALE,
                ORDINAL_POSITION
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = ? 
            ORDER BY ORDINAL_POSITION
        ";
        
        try {
            if (strpos($method, 'PDO') !== false) {
                $stmt = $connection->prepare($query);
                $stmt->execute(array($table_name));
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stmt = sqlsrv_query($connection, $query, array($table_name));
                if ($stmt === false) {
                    throw new Exception('Error al obtener estructura de tabla');
                }
                
                $columns = array();
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $columns[] = $row;
                }
                sqlsrv_free_stmt($stmt);
            }
            
            return array(
                'success' => true,
                'table_name' => $table_name,
                'columns' => $columns
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Error al obtener estructura: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Guardar consulta
     */
    public function save_query($name, $sql, $description = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_saved_queries';
        
        // Verificar que la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array(
                'success' => false,
                'error' => 'Tabla de consultas guardadas no disponible'
            );
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => get_current_user_id(),
                'name' => sanitize_text_field($name),
                'description' => sanitize_textarea_field($description),
                'query_text' => $sql,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return array(
                'success' => false,
                'error' => 'Error al guardar consulta: ' . $wpdb->last_error
            );
        }
        
        return array(
            'success' => true,
            'id' => $wpdb->insert_id,
            'message' => 'Consulta guardada exitosamente'
        );
    }
    
    /**
     * Cargar consultas guardadas
     */
    public function load_saved_queries($user_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_saved_queries';
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $queries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, name, description, query_text, created_at, updated_at 
                 FROM $table_name 
                 WHERE user_id = %d OR is_public = 1 
                 ORDER BY updated_at DESC",
                $user_id
            ),
            ARRAY_A
        );
        
        return array(
            'success' => true,
            'queries' => $queries ?: array()
        );
    }
    
    /**
     * Log de consulta
     */
    private function log_query($sql, $execution_time, $rows_returned, $status, $error_message = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_query_logs';
        
        // Verificar que la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return;
        }
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => get_current_user_id(),
                'query_text' => substr($sql, 0, 1000), // Limitar tamaño
                'execution_time' => $execution_time,
                'rows_returned' => $rows_returned,
                'status' => $status,
                'error_message' => $error_message,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%f', '%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * AJAX: Ejecutar consulta
     */
    public function ajax_execute_query() {
        check_ajax_referer('cp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        $sql = isset($_POST['sql']) ? stripslashes($_POST['sql']) : '';
        
        if (empty($sql)) {
            wp_send_json_error(array(
                'message' => 'Consulta SQL requerida'
            ));
        }
        
        $result = $this->execute_query($sql);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'data' => $result['data'],
                'columns' => $result['columns'],
                'total_rows' => $result['total_rows'],
                'execution_time' => round($result['execution_time'], 4),
                'method' => $result['method'],
                'limited' => $result['total_rows'] >= $this->max_rows
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['error'],
                'execution_time' => round($result['execution_time'], 4)
            ));
        }
    }
    
    /**
     * AJAX: Obtener estructura de tabla
     */
    public function ajax_get_table_structure() {
        check_ajax_referer('cp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        $table_name = sanitize_text_field($_POST['table_name'] ?? '');
        
        if (empty($table_name)) {
            wp_send_json_error(array(
                'message' => 'Nombre de tabla requerido'
            ));
        }
        
        $result = $this->get_table_structure($table_name);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Guardar consulta
     */
    public function ajax_save_query() {
        check_ajax_referer('cp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        $sql = stripslashes($_POST['sql'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        
        if (empty($name) || empty($sql)) {
            wp_send_json_error(array(
                'message' => 'Nombre y consulta SQL son requeridos'
            ));
        }
        
        $result = $this->save_query($name, $sql, $description);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Cargar consultas guardadas
     */
    public function ajax_load_saved_queries() {
        check_ajax_referer('cp_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        $result = $this->load_saved_queries();
        
        wp_send_json_success($result);
    }
    
    /**
     * Configurar límites
     */
    public function set_limits($max_execution_time = null, $max_rows = null) {
        if ($max_execution_time !== null) {
            $this->max_execution_time = max(5, min(300, intval($max_execution_time)));
        }
        
        if ($max_rows !== null) {
            $this->max_rows = max(10, min(10000, intval($max_rows)));
        }
    }
    
    /**
     * Obtener límites actuales
     */
    public function get_limits() {
        return array(
            'max_execution_time' => $this->max_execution_time,
            'max_rows' => $this->max_rows
        );
    }
}