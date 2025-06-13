<?php
/**
 * Sistema de logs y administración de consultas
 * Archivo: includes/class-cp-logs-admin.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_Logs_Admin {
    
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
        add_action('admin_menu', array($this, 'add_logs_menu'));
        add_action('wp_ajax_cp_get_logs_data', array($this, 'ajax_get_logs_data'));
        add_action('wp_ajax_cp_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_cp_export_logs', array($this, 'ajax_export_logs'));
        
        // Programar limpieza automática
        if (!wp_next_scheduled('cp_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'cp_cleanup_logs');
        }
        add_action('cp_cleanup_logs', array($this, 'cleanup_old_logs'));
    }
    
    /**
     * Agregar menú de logs
     */
    public function add_logs_menu() {
        add_submenu_page(
            'consulta-procesos',
            __('Logs de Consultas', 'consulta-procesos'),
            __('Logs', 'consulta-procesos'),
            'manage_options',
            'consulta-procesos-logs',
            array($this, 'render_logs_page')
        );
    }
    
    /**
     * Renderizar página de logs
     */
    public function render_logs_page() {
        ?>
        <div class="wrap">
            <h1>
                <span class="dashicons dashicons-list-view"></span>
                <?php _e('Logs de Consultas', 'consulta-procesos'); ?>
            </h1>
            
            <div class="cp-logs-container">
                <!-- Estadísticas generales -->
                <div class="cp-card">
                    <h2>
                        <span class="dashicons dashicons-chart-area"></span>
                        <?php _e('Estadísticas Generales', 'consulta-procesos'); ?>
                    </h2>
                    
                    <div id="cp-logs-stats" class="cp-stats-grid">
                        <div class="cp-stat-item">
                            <div class="cp-stat-number" id="total-searches">-</div>
                            <div class="cp-stat-label"><?php _e('Total Búsquedas', 'consulta-procesos'); ?></div>
                        </div>
                        
                        <div class="cp-stat-item">
                            <div class="cp-stat-number" id="successful-searches">-</div>
                            <div class="cp-stat-label"><?php _e('Exitosas', 'consulta-procesos'); ?></div>
                        </div>
                        
                        <div class="cp-stat-item">
                            <div class="cp-stat-number" id="failed-searches">-</div>
                            <div class="cp-stat-label"><?php _e('Fallidas', 'consulta-procesos'); ?></div>
                        </div>
                        
                        <div class="cp-stat-item">
                            <div class="cp-stat-number" id="avg-records">-</div>
                            <div class="cp-stat-label"><?php _e('Promedio Registros', 'consulta-procesos'); ?></div>
                        </div>
                        
                        <div class="cp-stat-item">
                            <div class="cp-stat-number" id="today-searches">-</div>
                            <div class="cp-stat-label"><?php _e('Hoy', 'consulta-procesos'); ?></div>
                        </div>
                        
                        <div class="cp-stat-item">
                            <div class="cp-stat-number" id="week-searches">-</div>
                            <div class="cp-stat-label"><?php _e('Esta Semana', 'consulta-procesos'); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Filtros -->
                <div class="cp-card">
                    <h2>
                        <span class="dashicons dashicons-filter"></span>
                        <?php _e('Filtros', 'consulta-procesos'); ?>
                    </h2>
                    
                    <div class="cp-filters">
                        <div class="cp-filter-group">
                            <label for="date-from"><?php _e('Desde:', 'consulta-procesos'); ?></label>
                            <input type="date" id="date-from" class="cp-filter-input">
                        </div>
                        
                        <div class="cp-filter-group">
                            <label for="date-to"><?php _e('Hasta:', 'consulta-procesos'); ?></label>
                            <input type="date" id="date-to" class="cp-filter-input">
                        </div>
                        
                        <div class="cp-filter-group">
                            <label for="profile-filter"><?php _e('Perfil:', 'consulta-procesos'); ?></label>
                            <select id="profile-filter" class="cp-filter-input">
                                <option value=""><?php _e('Todos', 'consulta-procesos'); ?></option>
                                <option value="entidades"><?php _e('Entidades', 'consulta-procesos'); ?></option>
                                <option value="proveedores"><?php _e('Proveedores', 'consulta-procesos'); ?></option>
                            </select>
                        </div>
                        
                        <div class="cp-filter-group">
                            <label for="status-filter"><?php _e('Estado:', 'consulta-procesos'); ?></label>
                            <select id="status-filter" class="cp-filter-input">
                                <option value=""><?php _e('Todos', 'consulta-procesos'); ?></option>
                                <option value="1"><?php _e('Exitosas', 'consulta-procesos'); ?></option>
                                <option value="0"><?php _e('Fallidas', 'consulta-procesos'); ?></option>
                            </select>
                        </div>
                        
                        <div class="cp-filter-actions">
                            <button id="apply-filters" class="button button-primary">
                                <?php _e('Aplicar Filtros', 'consulta-procesos'); ?>
                            </button>
                            <button id="clear-filters" class="button button-secondary">
                                <?php _e('Limpiar', 'consulta-procesos'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Tabla de logs -->
                <div class="cp-card">
                    <h2>
                        <span class="dashicons dashicons-list-view"></span>
                        <?php _e('Registro de Consultas', 'consulta-procesos'); ?>
                    </h2>
                    
                    <div class="cp-logs-actions">
                        <button id="refresh-logs" class="button button-secondary">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Actualizar', 'consulta-procesos'); ?>
                        </button>
                        
                        <button id="export-logs" class="button button-secondary">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Exportar', 'consulta-procesos'); ?>
                        </button>
                        
                        <button id="clear-logs" class="button button-link-delete">
                            <span class="dashicons dashicons-trash"></span>
                            <?php _e('Limpiar Logs', 'consulta-procesos'); ?>
                        </button>
                    </div>
                    
                    <div id="cp-logs-table-container">
                        <div class="cp-loading">
                            <span class="spinner is-active"></span>
                            <?php _e('Cargando logs...', 'consulta-procesos'); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Análisis por fuentes -->
                <div class="cp-card">
                    <h2>
                        <span class="dashicons dashicons-chart-pie"></span>
                        <?php _e('Análisis por Fuentes', 'consulta-procesos'); ?>
                    </h2>
                    
                    <div id="cp-sources-analysis">
                        <div class="cp-sources-grid">
                            <div class="cp-source-stat">
                                <h4>TVEC</h4>
                                <div class="cp-source-numbers">
                                    <span class="cp-source-searches" id="tvec-searches">-</span>
                                    <span class="cp-source-records" id="tvec-records">- registros</span>
                                </div>
                            </div>
                            
                            <div class="cp-source-stat">
                                <h4>SECOPI</h4>
                                <div class="cp-source-numbers">
                                    <span class="cp-source-searches" id="secopi-searches">-</span>
                                    <span class="cp-source-records" id="secopi-records">- registros</span>
                                </div>
                            </div>
                            
                            <div class="cp-source-stat">
                                <h4>SECOPII</h4>
                                <div class="cp-source-numbers">
                                    <span class="cp-source-searches" id="secopii-searches">-</span>
                                    <span class="cp-source-records" id="secopii-records">- registros</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .cp-logs-container {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }
        
        .cp-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .cp-stat-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        
        .cp-stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #007cba;
            margin-bottom: 5px;
        }
        
        .cp-stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .cp-filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .cp-filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .cp-filter-group label {
            font-weight: 600;
            color: #555;
        }
        
        .cp-filter-input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .cp-filter-actions {
            display: flex;
            gap: 10px;
        }
        
        .cp-logs-actions {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
        }
        
        .cp-logs-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .cp-logs-table th,
        .cp-logs-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .cp-logs-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .cp-logs-table tr:hover {
            background: #f8f9fa;
        }
        
        .cp-status-success {
            color: #28a745;
            font-weight: bold;
        }
        
        .cp-status-failed {
            color: #dc3545;
            font-weight: bold;
        }
        
        .cp-sources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        
        .cp-source-stat {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        
        .cp-source-stat h4 {
            margin: 0 0 15px 0;
            color: #007cba;
        }
        
        .cp-source-numbers {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .cp-source-searches {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        
        .cp-source-records {
            font-size: 12px;
            color: #666;
        }
        
        .cp-loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .cp-pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding: 15px 0;
            border-top: 1px solid #e9ecef;
        }
        
        .cp-pagination-info {
            color: #666;
            font-size: 14px;
        }
        
        .cp-pagination-buttons {
            display: flex;
            gap: 5px;
        }
        
        @media (max-width: 768px) {
            .cp-filters {
                grid-template-columns: 1fr;
            }
            
            .cp-filter-actions {
                flex-direction: column;
            }
            
            .cp-logs-actions {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .cp-logs-table {
                font-size: 14px;
            }
            
            .cp-logs-table th,
            .cp-logs-table td {
                padding: 8px;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Variables
            var currentPage = 1;
            var itemsPerPage = 20;
            var currentFilters = {};
            
            // Inicializar
            loadStats();
            loadLogs();
            
            // Eventos
            $('#apply-filters').on('click', function() {
                currentFilters = {
                    date_from: $('#date-from').val(),
                    date_to: $('#date-to').val(),
                    profile: $('#profile-filter').val(),
                    status: $('#status-filter').val()
                };
                currentPage = 1;
                loadLogs();
            });
            
            $('#clear-filters').on('click', function() {
                $('#date-from, #date-to').val('');
                $('#profile-filter, #status-filter').val('');
                currentFilters = {};
                currentPage = 1;
                loadLogs();
            });
            
            $('#refresh-logs').on('click', function() {
                loadStats();
                loadLogs();
            });
            
            $('#clear-logs').on('click', function() {
                if (confirm('¿Está seguro de que desea eliminar todos los logs? Esta acción no se puede deshacer.')) {
                    clearLogs();
                }
            });
            
            $('#export-logs').on('click', function() {
                exportLogs();
            });
            
            // Funciones
            function loadStats() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cp_get_logs_data',
                        nonce: '<?php echo wp_create_nonce('cp_logs_nonce'); ?>',
                        type: 'stats'
                    },
                    success: function(response) {
                        if (response.success) {
                            updateStats(response.data);
                        }
                    }
                });
            }
            
            function loadLogs() {
                $('#cp-logs-table-container').html('<div class="cp-loading"><span class="spinner is-active"></span> Cargando logs...</div>');
                
                var data = {
                    action: 'cp_get_logs_data',
                    nonce: '<?php echo wp_create_nonce('cp_logs_nonce'); ?>',
                    type: 'logs',
                    page: currentPage,
                    per_page: itemsPerPage
                };
                
                // Agregar filtros
                $.extend(data, currentFilters);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        if (response.success) {
                            renderLogsTable(response.data);
                        } else {
                            $('#cp-logs-table-container').html('<div class="notice notice-error"><p>Error cargando logs</p></div>');
                        }
                    },
                    error: function() {
                        $('#cp-logs-table-container').html('<div class="notice notice-error"><p>Error de comunicación</p></div>');
                    }
                });
            }
            
            function updateStats(stats) {
                $('#total-searches').text(stats.total_searches || 0);
                $('#successful-searches').text(stats.successful_searches || 0);
                $('#failed-searches').text(stats.failed_searches || 0);
                $('#avg-records').text(stats.avg_records || 0);
                $('#today-searches').text(stats.today_searches || 0);
                $('#week-searches').text(stats.week_searches || 0);
                
                // Estadísticas por fuente
                if (stats.sources) {
                    $('#tvec-searches').text(stats.sources.tvec?.searches || 0);
                    $('#tvec-records').text((stats.sources.tvec?.records || 0) + ' registros');
                    $('#secopi-searches').text(stats.sources.secopi?.searches || 0);
                    $('#secopi-records').text((stats.sources.secopi?.records || 0) + ' registros');
                    $('#secopii-searches').text(stats.sources.secopii?.searches || 0);
                    $('#secopii-records').text((stats.sources.secopii?.records || 0) + ' registros');
                }
            }
            
            function renderLogsTable(data) {
                var html = '<table class="cp-logs-table">';
                html += '<thead><tr>';
                html += '<th>Fecha</th>';
                html += '<th>Perfil</th>';
                html += '<th>Documento</th>';
                html += '<th>Fuentes</th>';
                html += '<th>Registros</th>';
                html += '<th>Estado</th>';
                html += '<th>Tiempo</th>';
                html += '</tr></thead>';
                html += '<tbody>';
                
                if (data.logs && data.logs.length > 0) {
                    data.logs.forEach(function(log) {
                        html += '<tr>';
                        html += '<td>' + log.created_at + '</td>';
                        html += '<td>' + (log.profile_type || '-') + '</td>';
                        html += '<td>' + (log.numero_documento || '-') + '</td>';
                        html += '<td>' + (log.sources_queried || '-') + '</td>';
                        html += '<td>' + (log.total_records_found || 0) + '</td>';
                        html += '<td><span class="cp-status-' + (log.success ? 'success' : 'failed') + '">' + 
                                (log.success ? 'Exitosa' : 'Fallida') + '</span></td>';
                        html += '<td>' + (log.execution_time || 0) + 's</td>';
                        html += '</tr>';
                    });
                } else {
                    html += '<tr><td colspan="7" style="text-align: center; padding: 40px;">No hay logs disponibles</td></tr>';
                }
                
                html += '</tbody></table>';
                
                // Agregar paginación
                if (data.total_pages > 1) {
                    html += '<div class="cp-pagination">';
                    html += '<div class="cp-pagination-info">Página ' + currentPage + ' de ' + data.total_pages + '</div>';
                    html += '<div class="cp-pagination-buttons">';
                    
                    if (currentPage > 1) {
                        html += '<button class="button" onclick="changePage(' + (currentPage - 1) + ')">Anterior</button>';
                    }
                    
                    if (currentPage < data.total_pages) {
                        html += '<button class="button" onclick="changePage(' + (currentPage + 1) + ')">Siguiente</button>';
                    }
                    
                    html += '</div></div>';
                }
                
                $('#cp-logs-table-container').html(html);
            }
            
            function clearLogs() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cp_clear_logs',
                        nonce: '<?php echo wp_create_nonce('cp_logs_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Logs eliminados correctamente');
                            loadStats();
                            loadLogs();
                        } else {
                            alert('Error eliminando logs');
                        }
                    }
                });
            }
            
            function exportLogs() {
                window.location.href = ajaxurl + '?action=cp_export_logs&nonce=<?php echo wp_create_nonce('cp_logs_nonce'); ?>';
            }
            
            // Función global para paginación
            window.changePage = function(page) {
                currentPage = page;
                loadLogs();
            };
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Obtener datos de logs
     */
    public function ajax_get_logs_data() {
        check_ajax_referer('cp_logs_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        $type = sanitize_text_field($_POST['type'] ?? 'logs');
        
        if ($type === 'stats') {
            $stats = $this->get_logs_statistics();
            wp_send_json_success($stats);
        } else {
            $page = intval($_POST['page'] ?? 1);
            $per_page = intval($_POST['per_page'] ?? 20);
            $filters = array(
                'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
                'date_to' => sanitize_text_field($_POST['date_to'] ?? ''),
                'profile' => sanitize_text_field($_POST['profile'] ?? ''),
                'status' => sanitize_text_field($_POST['status'] ?? '')
            );
            
            $logs_data = $this->get_logs_data($page, $per_page, $filters);
            wp_send_json_success($logs_data);
        }
    }
    
    /**
     * Obtener estadísticas de logs
     */
    private function get_logs_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_search_logs';
        
        // Estadísticas básicas
        $stats = array();
        
        // Total de búsquedas
        $stats['total_searches'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        // Búsquedas exitosas
        $stats['successful_searches'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE success = 1");
        
        // Búsquedas fallidas
        $stats['failed_searches'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE success = 0");
        
        // Promedio de registros
        $stats['avg_records'] = round($wpdb->get_var("SELECT AVG(total_records_found) FROM {$table_name} WHERE success = 1"));
        
        // Búsquedas de hoy
        $stats['today_searches'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE DATE(created_at) = %s",
            current_time('Y-m-d')
        ));
        
        // Búsquedas de esta semana
        $stats['week_searches'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE created_at >= %s",
            date('Y-m-d', strtotime('-7 days'))
        ));
        
        // Estadísticas por fuente
        $stats['sources'] = $this->get_sources_statistics();
        
        return $stats;
    }
    
    /**
     * Obtener estadísticas por fuente
     */
    private function get_sources_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_search_logs';
        
        $sources = array('tvec', 'secopi', 'secopii');
        $sources_stats = array();
        
        foreach ($sources as $source) {
            $searches = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE sources_queried LIKE %s",
                '%' . $source . '%'
            ));
            
            $records = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(total_records_found) FROM {$table_name} 
                 WHERE sources_queried LIKE %s AND success = 1",
                '%' . $source . '%'
            ));
            
            $sources_stats[$source] = array(
                'searches' => intval($searches),
                'records' => intval($records)
            );
        }
        
        return $sources_stats;
    }
    
    /**
     * Obtener datos de logs con paginación
     */
    private function get_logs_data($page, $per_page, $filters) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_search_logs';
        
        // Construir WHERE clause
        $where_conditions = array();
        $where_values = array();
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(created_at) >= %s";
            $where_values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(created_at) <= %s";
            $where_values[] = $filters['date_to'];
        }
        
        if (!empty($filters['profile'])) {
            $where_conditions[] = "profile_type = %s";
            $where_values[] = $filters['profile'];
        }
        
        if ($filters['status'] !== '') {
            $where_conditions[] = "success = %d";
            $where_values[] = intval($filters['status']);
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // Contar total de registros
        $count_query = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";
        if (!empty($where_values)) {
            $count_query = $wpdb->prepare($count_query, $where_values);
        }
        $total_records = $wpdb->get_var($count_query);
        
        // Obtener logs
        $offset = ($page - 1) * $per_page;
        $logs_query = "SELECT * FROM {$table_name} {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        
        $query_values = array_merge($where_values, array($per_page, $offset));
        $logs_query = $wpdb->prepare($logs_query, $query_values);
        
        $logs = $wpdb->get_results($logs_query, ARRAY_A);
        
        return array(
            'logs' => $logs,
            'total_records' => intval($total_records),
            'total_pages' => ceil($total_records / $per_page),
            'current_page' => $page,
            'per_page' => $per_page
        );
    }
    
    /**
     * AJAX: Limpiar logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer('cp_logs_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'cp_search_logs';
        $progress_table = $wpdb->prefix . 'cp_search_progress';
        
        // Eliminar logs
        $wpdb->query("TRUNCATE TABLE {$logs_table}");
        
        // Eliminar registros de progreso antiguos
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$progress_table} WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime('-1 day'))
        ));
        
        wp_send_json_success(array('message' => 'Logs eliminados correctamente'));
    }
    
    /**
     * AJAX: Exportar logs
     */
    public function ajax_export_logs() {
        check_ajax_referer('cp_logs_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_search_logs';
        
        // Obtener todos los logs
        $logs = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC", ARRAY_A);
        
        // Crear CSV
        $filename = 'consulta_procesos_logs_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fwrite($output, "\xEF\xBB\xBF");
        
        // Headers
        if (!empty($logs)) {
            fputcsv($output, array_keys($logs[0]));
            
            // Datos
            foreach ($logs as $log) {
                fputcsv($output, $log);
            }
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Limpiar logs antiguos (tarea automática)
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'cp_search_logs';
        $progress_table = $wpdb->prefix . 'cp_search_progress';
        
        // Eliminar logs de más de 90 días
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$logs_table} WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime('-90 days'))
        ));
        
        // Eliminar registros de progreso de más de 7 días
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$progress_table} WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime('-7 days'))
        ));
        
        CP_Utils::log('Limpieza automática de logs completada', 'info');
    }
}