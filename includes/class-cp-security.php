<?php
/**
 * Clase de seguridad para el plugin Consulta Procesos
 * 
 * Archivo: includes/class-cp-security.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_Security {
    
    private static $instance = null;
    private $failed_attempts = array();
    private $max_attempts = 5;
    private $lockout_duration = 300; // 5 minutos
    
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
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks de seguridad
     */
    private function init_hooks() {
        // Verificar permisos en cada petición AJAX
        add_action('wp_ajax_cp_test_connection', array($this, 'verify_permissions'), 1);
        add_action('wp_ajax_cp_get_tables', array($this, 'verify_permissions'), 1);
        add_action('wp_ajax_cp_diagnose_system', array($this, 'verify_permissions'), 1);
        
        // Sanitizar datos de entrada
        add_filter('cp_sanitize_connection_data', array($this, 'sanitize_connection_data'));
        add_filter('cp_validate_sql_query', array($this, 'validate_sql_query'));
        
        // Logs de seguridad
        add_action('cp_security_violation', array($this, 'log_security_violation'), 10, 3);
    }
    
    /**
     * Verificar permisos del usuario
     */
    public function verify_permissions() {
        if (!current_user_can('manage_options')) {
            CP_Utils::log('Intento de acceso sin permisos desde IP: ' . $this->get_client_ip(), 'warning');
            wp_die(__('No tienes permisos para realizar esta acción.', 'consulta-procesos'), 403);
        }
        
        // Verificar nonce
        if (!check_ajax_referer('cp_nonce', 'nonce', false)) {
            CP_Utils::log('Nonce inválido desde IP: ' . $this->get_client_ip(), 'warning');
            wp_die(__('Token de seguridad inválido.', 'consulta-procesos'), 403);
        }
    }
    
    /**
     * Verificar límite de intentos de conexión
     */
    public function check_connection_attempts() {
        $ip = $this->get_client_ip();
        $current_time = time();
        
        // Limpiar intentos antiguos
        if (isset($this->failed_attempts[$ip])) {
            $this->failed_attempts[$ip] = array_filter(
                $this->failed_attempts[$ip],
                function($timestamp) use ($current_time) {
                    return ($current_time - $timestamp) < $this->lockout_duration;
                }
            );
        }
        
        // Verificar si está bloqueado
        if (isset($this->failed_attempts[$ip]) && count($this->failed_attempts[$ip]) >= $this->max_attempts) {
            $this->log_security_violation('connection_rate_limit', $ip, array(
                'attempts' => count($this->failed_attempts[$ip])
            ));
            
            return array(
                'allowed' => false,
                'message' => sprintf(
                    __('Demasiados intentos fallidos. Inténtalo de nuevo en %d minutos.', 'consulta-procesos'),
                    ceil($this->lockout_duration / 60)
                )
            );
        }
        
        return array('allowed' => true);
    }
    
    /**
     * Registrar intento fallido de conexión
     */
    public function record_failed_connection() {
        $ip = $this->get_client_ip();
        
        if (!isset($this->failed_attempts[$ip])) {
            $this->failed_attempts[$ip] = array();
        }
        
        $this->failed_attempts[$ip][] = time();
        
        CP_Utils::log("Intento de conexión fallido desde IP: {$ip}", 'warning');
    }
    
    /**
     * Sanitizar datos de conexión
     */
    public function sanitize_connection_data($data) {
        $sanitized = array();
        
        // Sanitizar cada campo
        $sanitized['server'] = sanitize_text_field($data['server'] ?? '');
        $sanitized['database'] = sanitize_text_field($data['database'] ?? '');
        $sanitized['username'] = sanitize_text_field($data['username'] ?? '');
        $sanitized['password'] = $data['password'] ?? ''; // No sanitizar contraseña
        $sanitized['port'] = intval($data['port'] ?? 1433);
        
        // Validaciones específicas
        if (!empty($sanitized['server'])) {
            if (!CP_Utils::is_valid_ip($sanitized['server']) && 
                !CP_Utils::is_valid_hostname($sanitized['server'])) {
                
                $this->log_security_violation('invalid_server_format', $this->get_client_ip(), array(
                    'server' => $sanitized['server']
                ));
                
                $sanitized['server'] = '';
            }
        }
        
        if (!CP_Utils::is_valid_port($sanitized['port'])) {
            $sanitized['port'] = 1433;
        }
        
        return $sanitized;
    }
    
    /**
     * Validar consulta SQL
     */
    public function validate_sql_query($query) {
        $result = array(
            'valid' => false,
            'query' => '',
            'errors' => array()
        );
        
        // Sanitizar consulta
        $query = CP_Utils::sanitize_sql_query($query);
        
        if (empty($query)) {
            $result['errors'][] = __('La consulta no puede estar vacía.', 'consulta-procesos');
            return $result;
        }
        
        // Verificar longitud máxima
        if (strlen($query) > 10000) {
            $result['errors'][] = __('La consulta es demasiado larga (máximo 10,000 caracteres).', 'consulta-procesos');
            return $result;
        }
        
        // Verificar que sea solo SELECT
        if (!CP_Utils::is_select_query($query)) {
            $result['errors'][] = __('Solo se permiten consultas SELECT.', 'consulta-procesos');
            
            $this->log_security_violation('non_select_query', $this->get_client_ip(), array(
                'query_start' => substr($query, 0, 100)
            ));
            
            return $result;
        }
        
        // Verificar comandos peligrosos
        if (CP_Utils::has_dangerous_sql($query)) {
            $result['errors'][] = __('La consulta contiene comandos no permitidos.', 'consulta-procesos');
            
            $this->log_security_violation('dangerous_sql_detected', $this->get_client_ip(), array(
                'query_start' => substr($query, 0, 100)
            ));
            
            return $result;
        }
        
        // Verificar patrones sospechosos
        $suspicious_patterns = array(
            '/\bxp_cmdshell\b/i',
            '/\bsp_configure\b/i',
            '/\bopenrowset\b/i',
            '/\bopendatasource\b/i',
            '/\bbulk\s+insert\b/i',
            '/\bwaitfor\s+delay\b/i',
            '/\bunion\s+.*\bselect\b.*\bfrom\b.*\binformation_schema\b/i'
        );
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $query)) {
                $result['errors'][] = __('La consulta contiene patrones sospechosos.', 'consulta-procesos');
                
                $this->log_security_violation('suspicious_sql_pattern', $this->get_client_ip(), array(
                    'pattern' => $pattern,
                    'query_start' => substr($query, 0, 100)
                ));
                
                return $result;
            }
        }
        
        // Si llegamos aquí, la consulta es válida
        $result['valid'] = true;
        $result['query'] = $query;
        
        return $result;
    }
    
    /**
     * Encriptar datos sensibles
     */
    public function encrypt_sensitive_data($data) {
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($data); // Fallback básico
        }
        
        $method = 'AES-256-CBC';
        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
        
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Desencriptar datos sensibles
     */
    public function decrypt_sensitive_data($encrypted_data) {
        if (!function_exists('openssl_decrypt')) {
            return base64_decode($encrypted_data); // Fallback básico
        }
        
        $method = 'AES-256-CBC';
        $key = $this->get_encryption_key();
        $data = base64_decode($encrypted_data);
        
        $iv_length = openssl_cipher_iv_length($method);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        return openssl_decrypt($encrypted, $method, $key, 0, $iv);
    }
    
    /**
     * Obtener clave de encriptación
     */
    private function get_encryption_key() {
        $key = get_option('cp_encryption_key');
        
        if (!$key) {
            $key = wp_generate_password(32, true, true);
            update_option('cp_encryption_key', $key);
        }
        
        return hash('sha256', $key . wp_salt());
    }
    
    /**
     * Obtener IP del cliente
     */
    public function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Verificar token CSRF personalizado
     */
    public function verify_csrf_token($token) {
        $stored_token = get_transient('cp_csrf_token_' . get_current_user_id());
        
        return hash_equals($stored_token, $token);
    }
    
    /**
     * Generar token CSRF personalizado
     */
    public function generate_csrf_token() {
        $token = CP_Utils::generate_secure_token();
        set_transient('cp_csrf_token_' . get_current_user_id(), $token, 3600); // 1 hora
        
        return $token;
    }
    
    /**
     * Sanitizar entrada JSON
     */
    public function sanitize_json_input($json_string) {
        $data = json_decode($json_string, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        return $this->sanitize_recursive($data);
    }
    
    /**
     * Sanitizar datos recursivamente
     */
    private function sanitize_recursive($data) {
        if (is_array($data)) {
            return array_map(array($this, 'sanitize_recursive'), $data);
        } elseif (is_string($data)) {
            return sanitize_text_field($data);
        }
        
        return $data;
    }
    
    /**
     * Verificar permisos de archivo
     */
    public function verify_file_permissions($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }
        
        $perms = fileperms($file_path);
        
        // Verificar que no sea ejecutable por otros
        if ($perms & 0x0001) {
            return false;
        }
        
        // Verificar que no sea escribible por otros
        if ($perms & 0x0002) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Limpiar logs antiguos
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_query_logs';
        
        // Eliminar logs de más de 30 días
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
    }
    
    /**
     * Verificar integridad de archivos del plugin
     */
    public function verify_plugin_integrity() {
        $critical_files = array(
            CP_PLUGIN_PATH . 'consulta-procesos.php',
            CP_PLUGIN_PATH . 'includes/class-cp-database.php',
            CP_PLUGIN_PATH . 'includes/class-cp-admin.php'
        );
        
        $integrity_issues = array();
        
        foreach ($critical_files as $file) {
            if (!file_exists($file)) {
                $integrity_issues[] = "Archivo faltante: " . basename($file);
            } elseif (!$this->verify_file_permissions($file)) {
                $integrity_issues[] = "Permisos inseguros: " . basename($file);
            }
        }
        
        return array(
            'secure' => empty($integrity_issues),
            'issues' => $integrity_issues
        );
    }
    
    /**
     * Log de violaciones de seguridad
     */
    public function log_security_violation($type, $ip, $context = array()) {
        $log_data = array(
            'type' => $type,
            'ip' => $ip,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
            'context' => $context
        );
        
        // Log en archivo
        CP_Utils::log("Violación de seguridad: {$type}", 'error', $log_data);
        
        // Almacenar en transient para revisión
        $violations = get_transient('cp_security_violations') ?: array();
        $violations[] = $log_data;
        
        // Mantener solo las últimas 100 violaciones
        if (count($violations) > 100) {
            $violations = array_slice($violations, -100);
        }
        
        set_transient('cp_security_violations', $violations, 24 * HOUR_IN_SECONDS);
        
        // Trigger action para notificaciones externas
        do_action('cp_security_violation', $type, $ip, $context);
    }
    
    /**
     * Obtener estadísticas de seguridad
     */
    public function get_security_stats() {
        $violations = get_transient('cp_security_violations') ?: array();
        
        $stats = array(
            'total_violations' => count($violations),
            'violation_types' => array(),
            'top_ips' => array(),
            'recent_violations' => array_slice($violations, -10)
        );
        
        // Contar tipos de violaciones
        foreach ($violations as $violation) {
            $type = $violation['type'];
            $stats['violation_types'][$type] = ($stats['violation_types'][$type] ?? 0) + 1;
            
            $ip = $violation['ip'];
            $stats['top_ips'][$ip] = ($stats['top_ips'][$ip] ?? 0) + 1;
        }
        
        // Ordenar por frecuencia
        arsort($stats['violation_types']);
        arsort($stats['top_ips']);
        
        return $stats;
    }
    
    /**
     * Generar reporte de seguridad
     */
    public function generate_security_report() {
        $stats = $this->get_security_stats();
        $integrity = $this->verify_plugin_integrity();
        $environment = CP_Utils::get_environment_info();
        
        return array(
            'generated_at' => current_time('mysql'),
            'plugin_integrity' => $integrity,
            'security_stats' => $stats,
            'environment_info' => $environment,
            'recommendations' => $this->get_security_recommendations($stats, $integrity)
        );
    }
    
    /**
     * Obtener recomendaciones de seguridad
     */
    private function get_security_recommendations($stats, $integrity) {
        $recommendations = array();
        
        if (!$integrity['secure']) {
            $recommendations[] = array(
                'level' => 'high',
                'message' => 'Se detectaron problemas de integridad en archivos del plugin.',
                'action' => 'Reinstalar el plugin o verificar permisos de archivos.'
            );
        }
        
        if ($stats['total_violations'] > 10) {
            $recommendations[] = array(
                'level' => 'medium',
                'message' => 'Alto número de violaciones de seguridad detectadas.',
                'action' => 'Revisar logs y considerar medidas de seguridad adicionales.'
            );
        }
        
        if (!CP_Utils::is_debug_mode() && WP_DEBUG) {
            $recommendations[] = array(
                'level' => 'low',
                'message' => 'Modo debug activado en producción.',
                'action' => 'Desactivar WP_DEBUG en el archivo wp-config.php.'
            );
        }
        
        return $recommendations;
    }
}