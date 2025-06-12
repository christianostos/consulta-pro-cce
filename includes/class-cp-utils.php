<?php
/**
 * Clase de utilidades para el plugin Consulta Procesos
 * 
 * Archivo: includes/class-cp-utils.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_Utils {
    
    /**
     * Formatear tiempo de ejecución
     */
    public static function format_execution_time($seconds) {
        if ($seconds < 1) {
            return round($seconds * 1000, 2) . ' ms';
        } elseif ($seconds < 60) {
            return round($seconds, 3) . ' s';
        } else {
            $minutes = floor($seconds / 60);
            $remaining_seconds = $seconds % 60;
            return $minutes . 'm ' . round($remaining_seconds, 1) . 's';
        }
    }
    
    /**
     * Formatear tamaño de archivo/memoria
     */
    public static function format_bytes($size, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Validar dirección IP
     */
    public static function is_valid_ip($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    
    /**
     * Validar hostname
     */
    public static function is_valid_hostname($hostname) {
        // Permitir host.docker.internal y otros nombres especiales
        $special_hosts = array('host.docker.internal', 'localhost');
        
        if (in_array($hostname, $special_hosts)) {
            return true;
        }
        
        // Validar hostname normal
        return filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }
    
    /**
     * Validar puerto
     */
    public static function is_valid_port($port) {
        $port = intval($port);
        return $port >= 1 && $port <= 65535;
    }
    
    /**
     * Sanitizar consulta SQL (básico)
     */
    public static function sanitize_sql_query($query) {
        // Remover comentarios SQL
        $query = preg_replace('/--.*$/m', '', $query);
        $query = preg_replace('/\/\*.*?\*\//s', '', $query);
        
        // Normalizar espacios
        $query = preg_replace('/\s+/', ' ', $query);
        
        return trim($query);
    }
    
    /**
     * Validar que una consulta sea solo SELECT
     */
    public static function is_select_query($query) {
        $query = self::sanitize_sql_query($query);
        $query = strtoupper(trim($query));
        
        // Solo permitir SELECT y WITH (para CTEs)
        $allowed_starts = array('SELECT', 'WITH');
        
        foreach ($allowed_starts as $start) {
            if (strpos($query, $start) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Detectar comandos peligrosos en SQL
     */
    public static function has_dangerous_sql($query) {
        $dangerous_keywords = array(
            'INSERT', 'UPDATE', 'DELETE', 'DROP', 'CREATE', 'ALTER', 
            'TRUNCATE', 'EXEC', 'EXECUTE', 'MERGE', 'CALL', 'DO',
            'LOAD', 'OUTFILE', 'DUMPFILE', 'INTO OUTFILE', 'INTO DUMPFILE',
            'LOAD_FILE', 'BENCHMARK', 'SLEEP'
        );
        
        $query = strtoupper(self::sanitize_sql_query($query));
        
        foreach ($dangerous_keywords as $keyword) {
            if (strpos($query, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generar hash seguro para cache
     */
    public static function generate_cache_key($data) {
        if (is_array($data) || is_object($data)) {
            $data = serialize($data);
        }
        
        return 'cp_' . hash('sha256', $data . wp_salt());
    }
    
    /**
     * Escapar HTML de manera segura
     */
    public static function esc_html_deep($data) {
        if (is_array($data)) {
            return array_map(array(__CLASS__, 'esc_html_deep'), $data);
        } elseif (is_object($data)) {
            $vars = get_object_vars($data);
            foreach ($vars as $key => $value) {
                $data->$key = self::esc_html_deep($value);
            }
            return $data;
        } else {
            return esc_html($data);
        }
    }
    
    /**
     * Convertir array a CSV
     */
    public static function array_to_csv($data, $headers = null) {
        if (empty($data)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Agregar headers si se proporcionan
        if ($headers) {
            fputcsv($output, $headers);
        } elseif (is_array($data[0])) {
            // Usar keys del primer elemento como headers
            fputcsv($output, array_keys($data[0]));
        }
        
        // Agregar datos
        foreach ($data as $row) {
            if (is_object($row)) {
                $row = get_object_vars($row);
            }
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);
        
        return $csv_content;
    }
    
    /**
     * Generar nombre de archivo único
     */
    public static function generate_unique_filename($prefix = 'cp', $extension = 'csv') {
        $timestamp = date('Y-m-d_H-i-s');
        $random = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        
        return "{$prefix}_{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * Verificar si el usuario actual puede usar el plugin
     */
    public static function current_user_can_use_plugin() {
        return current_user_can('manage_options');
    }
    
    /**
     * Logging personalizado para el plugin
     */
    public static function log($message, $level = 'info', $context = array()) {
        if (!WP_DEBUG || !WP_DEBUG_LOG) {
            return;
        }
        
        $levels = array('debug', 'info', 'warning', 'error');
        if (!in_array($level, $levels)) {
            $level = 'info';
        }
        
        $log_message = sprintf(
            '[CP][%s] %s',
            strtoupper($level),
            $message
        );
        
        if (!empty($context)) {
            $log_message .= ' | Context: ' . wp_json_encode($context);
        }
        
        error_log($log_message);
    }
    
    /**
     * Obtener información del entorno
     */
    public static function get_environment_info() {
        return array(
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'plugin_version' => CP_PLUGIN_VERSION,
            'os' => PHP_OS,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'is_docker' => file_exists('/.dockerenv') || file_exists('/proc/1/cgroup'),
            'timezone' => wp_timezone_string(),
            'locale' => get_locale()
        );
    }
    
    /**
     * Verificar dependencias del plugin
     */
    public static function check_dependencies() {
        $requirements = array(
            'php_version' => array(
                'required' => '7.4',
                'current' => PHP_VERSION,
                'met' => version_compare(PHP_VERSION, '7.4', '>=')
            ),
            'wordpress_version' => array(
                'required' => '5.0',
                'current' => get_bloginfo('version'),
                'met' => version_compare(get_bloginfo('version'), '5.0', '>=')
            ),
            'pdo_sqlsrv' => array(
                'required' => true,
                'current' => extension_loaded('pdo_sqlsrv'),
                'met' => extension_loaded('pdo_sqlsrv')
            ),
            'sqlsrv' => array(
                'required' => true,
                'current' => extension_loaded('sqlsrv'),
                'met' => extension_loaded('sqlsrv')
            )
        );
        
        // Al menos una extensión SQL Server debe estar disponible
        $requirements['sql_server_extension'] = array(
            'required' => true,
            'current' => $requirements['pdo_sqlsrv']['met'] || $requirements['sqlsrv']['met'],
            'met' => $requirements['pdo_sqlsrv']['met'] || $requirements['sqlsrv']['met']
        );
        
        return $requirements;
    }
    
    /**
     * Formatear número con separadores
     */
    public static function format_number($number, $decimals = 0) {
        return number_format($number, $decimals, '.', ',');
    }
    
    /**
     * Truncar texto con elipsis
     */
    public static function truncate_text($text, $length = 100, $ellipsis = '...') {
        if (strlen($text) <= $length) {
            return $text;
        }
        
        return rtrim(substr($text, 0, $length)) . $ellipsis;
    }
    
    /**
     * Convertir objeto a array recursivamente
     */
    public static function object_to_array($obj) {
        if (is_object($obj)) {
            $obj = get_object_vars($obj);
        }
        
        if (is_array($obj)) {
            return array_map(array(__CLASS__, 'object_to_array'), $obj);
        }
        
        return $obj;
    }
    
    /**
     * Verificar si una URL es válida
     */
    public static function is_valid_url($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Obtener extensión de archivo
     */
    public static function get_file_extension($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
    
    /**
     * Generar token aleatorio seguro
     */
    public static function generate_secure_token($length = 32) {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length / 2));
        } else {
            // Fallback menos seguro
            return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
        }
    }
    
    /**
     * Parsear DSN de conexión
     */
    public static function parse_dsn($dsn) {
        $parts = array();
        
        if (preg_match('/^(\w+):(.+)$/', $dsn, $matches)) {
            $parts['driver'] = $matches[1];
            $params_string = $matches[2];
            
            // Parsear parámetros
            $params = explode(';', $params_string);
            foreach ($params as $param) {
                if (strpos($param, '=') !== false) {
                    list($key, $value) = explode('=', $param, 2);
                    $parts[trim($key)] = trim($value);
                }
            }
        }
        
        return $parts;
    }
    
    /**
     * Verificar si estamos en modo debug
     */
    public static function is_debug_mode() {
        return defined('WP_DEBUG') && WP_DEBUG;
    }
    
    /**
     * Obtener memoria usada
     */
    public static function get_memory_usage() {
        return array(
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => self::parse_ini_size(ini_get('memory_limit'))
        );
    }
    
    /**
     * Convertir valores ini (como 256M) a bytes
     */
    public static function parse_ini_size($size) {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }
        
        return round($size);
    }
}