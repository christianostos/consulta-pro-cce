<?php
/**
 * Utilidades para el sistema de logs del plugin
 * 
 * Archivo: includes/class-cp-logs.php (NUEVO ARCHIVO)
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_Logs {
    
    private static $instance = null;
    
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
     * Inicializar hooks
     */
    private function init_hooks() {
        // Hook para limpiar logs antiguos semanalmente
        add_action('cp_weekly_cleanup', array($this, 'cleanup_old_logs'));
        
        // Programar evento de limpieza si no existe
        if (!wp_next_scheduled('cp_weekly_cleanup')) {
            wp_schedule_event(time(), 'weekly', 'cp_weekly_cleanup');
        }
    }
    
    /**
     * Registrar una búsqueda exitosa
     */
    public function log_search_success($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $results_found, $execution_time = null, $sources = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        $log_data = array(
            'session_id' => $this->get_session_id(),
            'profile_type' => sanitize_text_field($profile_type),
            'fecha_inicio' => sanitize_text_field($fecha_inicio),
            'fecha_fin' => sanitize_text_field($fecha_fin),
            'numero_documento' => sanitize_text_field($numero_documento),
            'search_sources' => is_array($sources) ? implode(',', $sources) : $sources,
            'status' => 'success',
            'error_message' => null,
            'results_found' => intval($results_found),
            'execution_time' => $execution_time ? floatval($execution_time) : null,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $this->get_user_agent(),
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table_name, $log_data, array(
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s'
        ));
        
        if ($result === false) {
            error_log('CP Logs: Error registrando búsqueda exitosa: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Registrar un error de búsqueda
     */
    public function log_search_error($error_type, $error_message, $profile_type = '', $fecha_inicio = '', $fecha_fin = '', $numero_documento = '', $execution_time = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        $log_data = array(
            'session_id' => $this->get_session_id(),
            'profile_type' => sanitize_text_field($profile_type) ?: 'unknown',
            'fecha_inicio' => sanitize_text_field($fecha_inicio) ?: '0000-00-00',
            'fecha_fin' => sanitize_text_field($fecha_fin) ?: '0000-00-00',
            'numero_documento' => sanitize_text_field($numero_documento),
            'search_sources' => '',
            'status' => 'error',
            'error_message' => $error_type . ': ' . $error_message,
            'results_found' => 0,
            'execution_time' => $execution_time ? floatval($execution_time) : null,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $this->get_user_agent(),
            'created_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table_name, $log_data, array(
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s'
        ));
        
        if ($result === false) {
            error_log('CP Logs: Error registrando error de búsqueda: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Obtener estadísticas generales
     */
    public function get_stats($date_from = null, $date_to = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return $this->get_empty_stats();
        }
        
        $where_clause = '';
        $where_params = array();
        
        if ($date_from && $date_to) {
            $where_clause = 'WHERE DATE(created_at) BETWEEN %s AND %s';
            $where_params = array($date_from, $date_to);
        } elseif ($date_from) {
            $where_clause = 'WHERE DATE(created_at) >= %s';
            $where_params = array($date_from);
        }
        
        $stats = array();
        
        // Estadísticas básicas
        $stats['total'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} {$where_clause}",
            $where_params
        )));
        
        $stats['successful'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} {$where_clause}" . ($where_clause ? ' AND' : 'WHERE') . " status = 'success'",
            array_merge($where_params, array('success'))
        )));
        
        $stats['failed'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} {$where_clause}" . ($where_clause ? ' AND' : 'WHERE') . " status = 'error'",
            array_merge($where_params, array('error'))
        )));
        
        $stats['partial'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} {$where_clause}" . ($where_clause ? ' AND' : 'WHERE') . " status = 'partial_success'",
            array_merge($where_params, array('partial_success'))
        )));
        
        // Estadísticas por perfil
        $stats['entidades'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} {$where_clause}" . ($where_clause ? ' AND' : 'WHERE') . " profile_type = 'entidades'",
            array_merge($where_params, array('entidades'))
        )));
        
        $stats['proveedores'] = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} {$where_clause}" . ($where_clause ? ' AND' : 'WHERE') . " profile_type = 'proveedores'",
            array_merge($where_params, array('proveedores'))
        )));
        
        // Calcular promedios
        $stats['avg_results'] = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT AVG(results_found) FROM {$table_name} {$where_clause}" . ($where_clause ? ' AND' : 'WHERE') . " status = 'success'",
            array_merge($where_params, array('success'))
        )));
        
        $stats['avg_execution_time'] = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT AVG(execution_time) FROM {$table_name} {$where_clause} AND execution_time IS NOT NULL",
            $where_params
        )));
        
        // Calcular tasa de éxito
        $stats['success_rate'] = $stats['total'] > 0 ? round(($stats['successful'] / $stats['total']) * 100, 2) : 0;
        
        return $stats;
    }
    
    /**
     * Obtener logs con filtros y paginación
     */
    public function get_logs($filters = array(), $page = 1, $per_page = 50) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array('logs' => array(), 'total' => 0);
        }
        
        $where_conditions = array();
        $where_params = array();
        
        // Aplicar filtros
        if (!empty($filters['status'])) {
            $where_conditions[] = "status = %s";
            $where_params[] = $filters['status'];
        }
        
        if (!empty($filters['profile_type'])) {
            $where_conditions[] = "profile_type = %s";
            $where_params[] = $filters['profile_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(created_at) >= %s";
            $where_params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(created_at) <= %s";
            $where_params[] = $filters['date_to'];
        }
        
        if (!empty($filters['numero_documento'])) {
            $where_conditions[] = "numero_documento LIKE %s";
            $where_params[] = '%' . $filters['numero_documento'] . '%';
        }
        
        if (!empty($filters['ip_address'])) {
            $where_conditions[] = "ip_address = %s";
            $where_params[] = $filters['ip_address'];
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // Contar total
        $total = intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} {$where_clause}",
            $where_params
        )));
        
        // Obtener logs paginados
        $offset = ($page - 1) * $per_page;
        $logs_query = "SELECT * FROM {$table_name} {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $logs_params = array_merge($where_params, array($per_page, $offset));
        
        $logs = $wpdb->get_results($wpdb->prepare($logs_query, $logs_params), ARRAY_A);
        
        return array(
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        );
    }
    
    /**
     * Limpiar logs antiguos
     */
    public function cleanup_old_logs($days = 180) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return 0;
        }
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE created_at < %s",
            $cutoff_date
        ));
        
        if ($deleted > 0) {
            error_log("CP Logs: Limpieza automática - {$deleted} logs antiguos eliminados (más de {$days} días)");
        }
        
        return $deleted;
    }
    
    /**
     * Obtener top IPs con más búsquedas
     */
    public function get_top_ips($limit = 10, $days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT ip_address, COUNT(*) as search_count, 
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_searches,
                    MAX(created_at) as last_search
             FROM {$table_name} 
             WHERE created_at >= %s 
             GROUP BY ip_address 
             ORDER BY search_count DESC 
             LIMIT %d",
            $cutoff_date, $limit
        ), ARRAY_A);
    }
    
    /**
     * Obtener errores más frecuentes
     */
    public function get_frequent_errors($limit = 10, $days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT error_message, COUNT(*) as error_count, MAX(created_at) as last_occurrence
             FROM {$table_name} 
             WHERE status = 'error' AND created_at >= %s 
             GROUP BY error_message 
             ORDER BY error_count DESC 
             LIMIT %d",
            $cutoff_date, $limit
        ), ARRAY_A);
    }
    
    /**
     * Exportar logs a CSV
     */
    public function export_to_csv($filters = array(), $filename = null) {
        if (!$filename) {
            $filename = 'consulta_procesos_logs_' . date('Y-m-d_H-i-s') . '.csv';
        }
        
        $logs_data = $this->get_logs($filters, 1, 10000); // Máximo 10,000 registros
        $logs = $logs_data['logs'];
        
        if (empty($logs)) {
            return false;
        }
        
        // Configurar headers para descarga
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Crear output
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, array(
            'ID',
            'Fecha/Hora',
            'Perfil',
            'Documento',
            'Fecha Inicio',
            'Fecha Fin',
            'Estado',
            'Resultados',
            'Tiempo Ejecución',
            'Fuentes',
            'IP',
            'Error'
        ));
        
        // Datos
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log['id'],
                $log['created_at'],
                $log['profile_type'],
                $log['numero_documento'],
                $log['fecha_inicio'],
                $log['fecha_fin'],
                $log['status'],
                $log['results_found'],
                $log['execution_time'],
                $log['search_sources'],
                $log['ip_address'],
                $log['error_message']
            ));
        }
        
        fclose($output);
        return true;
    }
    
    /**
     * Obtener estadísticas por día (para gráficos)
     */
    public function get_daily_stats($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }
        
        $cutoff_date = date('Y-m-d', strtotime("-{$days} days"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date,
                    COUNT(*) as total_searches,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_searches,
                    SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed_searches,
                    SUM(results_found) as total_results,
                    AVG(execution_time) as avg_execution_time
             FROM {$table_name} 
             WHERE DATE(created_at) >= %s 
             GROUP BY DATE(created_at) 
             ORDER BY date ASC",
            $cutoff_date
        ), ARRAY_A);
    }
    
    /**
     * Obtener ID de sesión
     */
    private function get_session_id() {
        if (!session_id()) {
            return 'no-session-' . uniqid();
        }
        return session_id();
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
     * Obtener User Agent
     */
    private function get_user_agent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    /**
     * Obtener estadísticas vacías
     */
    private function get_empty_stats() {
        return array(
            'total' => 0,
            'successful' => 0,
            'failed' => 0,
            'partial' => 0,
            'entidades' => 0,
            'proveedores' => 0,
            'avg_results' => 0,
            'avg_execution_time' => 0,
            'success_rate' => 0
        );
    }
    
    /**
     * Validar integridad de la tabla de logs
     */
    public function validate_table_integrity() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        // Verificar que la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array('status' => 'error', 'message' => 'Tabla de logs no existe');
        }
        
        // Verificar estructura de la tabla
        $columns = $wpdb->get_results("DESCRIBE {$table_name}", ARRAY_A);
        $required_columns = array('id', 'profile_type', 'numero_documento', 'status', 'created_at');
        
        $existing_columns = array_column($columns, 'Field');
        $missing_columns = array_diff($required_columns, $existing_columns);
        
        if (!empty($missing_columns)) {
            return array(
                'status' => 'error', 
                'message' => 'Faltan columnas: ' . implode(', ', $missing_columns)
            );
        }
        
        // Verificar índices
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}", ARRAY_A);
        $has_primary = false;
        
        foreach ($indexes as $index) {
            if ($index['Key_name'] === 'PRIMARY') {
                $has_primary = true;
                break;
            }
        }
        
        if (!$has_primary) {
            return array('status' => 'warning', 'message' => 'Falta clave primaria');
        }
        
        return array('status' => 'ok', 'message' => 'Tabla en buen estado');
    }
}