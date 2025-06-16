<?php
/**
 * Vista: Logs y Debugging - MEJORADA CON SISTEMA COMPLETO DE LOGS
 * Archivo: admin/views/logs.php
 */

if (!defined('ABSPATH')) {
    exit;
}

$admin = CP_Admin::get_instance();
?>

<div class="wrap">
    <h1><?php _e('Logs y Debugging', 'consulta-procesos'); ?></h1>
    
    <div class="cp-logs-container">
        
        <!-- NUEVO: Estadísticas de Logs del Frontend -->
        <div class="cp-card cp-frontend-stats">
            <h2><span class="dashicons dashicons-chart-bar"></span> <?php _e('Estadísticas de Consultas Frontend', 'consulta-procesos'); ?></h2>
            
            <div class="frontend-stats-grid" id="frontend-stats-grid">
                <div class="frontend-stat-item">
                    <strong id="total-searches-stat">-</strong>
                    <span><?php _e('Total Búsquedas', 'consulta-procesos'); ?></span>
                </div>
                <div class="frontend-stat-item">
                    <strong id="successful-searches-stat">-</strong>
                    <span><?php _e('Exitosas', 'consulta-procesos'); ?></span>
                </div>
                <div class="frontend-stat-item">
                    <strong id="failed-searches-stat">-</strong>
                    <span><?php _e('Con Errores', 'consulta-procesos'); ?></span>
                </div>
                <div class="frontend-stat-item">
                    <strong id="success-rate-stat">-</strong>
                    <span><?php _e('Tasa de Éxito', 'consulta-procesos'); ?></span>
                </div>
                <div class="frontend-stat-item">
                    <strong id="entidades-searches-stat">-</strong>
                    <span><?php _e('Búsquedas Entidades', 'consulta-procesos'); ?></span>
                </div>
                <div class="frontend-stat-item">
                    <strong id="proveedores-searches-stat">-</strong>
                    <span><?php _e('Búsquedas Proveedores', 'consulta-procesos'); ?></span>
                </div>
            </div>
            
            <div class="stats-controls">
                <button id="refresh-frontend-stats" class="button button-secondary">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Actualizar Estadísticas', 'consulta-procesos'); ?>
                </button>
            </div>
        </div>

        <!-- Logs de Búsquedas del Frontend - MEJORADO -->
        <div class="cp-card">
            <h2><span class="dashicons dashicons-search"></span> <?php _e('Logs de Búsquedas Frontend', 'consulta-procesos'); ?></h2>
            
            <div class="logs-controls">
                <button id="refresh-frontend-logs" class="button button-secondary">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Actualizar Logs', 'consulta-procesos'); ?>
                </button>
                
                <button id="clear-frontend-logs" class="button button-secondary" style="margin-left: 10px;">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Limpiar Logs', 'consulta-procesos'); ?>
                </button>
                
                <div class="logs-filters" style="display: inline-flex; margin-left: 20px; gap: 10px;">
                    <select id="filter-status" style="max-width: 150px;">
                        <option value=""><?php _e('Todos los estados', 'consulta-procesos'); ?></option>
                        <option value="success"><?php _e('Exitosas', 'consulta-procesos'); ?></option>
                        <option value="error"><?php _e('Con errores', 'consulta-procesos'); ?></option>
                        <option value="partial_success"><?php _e('Parcialmente exitosas', 'consulta-procesos'); ?></option>
                    </select>
                    
                    <select id="filter-profile" style="max-width: 150px;">
                        <option value=""><?php _e('Todos los perfiles', 'consulta-procesos'); ?></option>
                        <option value="entidades"><?php _e('Entidades', 'consulta-procesos'); ?></option>
                        <option value="proveedores"><?php _e('Proveedores', 'consulta-procesos'); ?></option>
                    </select>
                    
                    <input type="date" id="filter-date" style="max-width: 150px;" placeholder="<?php _e('Filtrar por fecha', 'consulta-procesos'); ?>">
                    
                    <button id="apply-filters" class="button button-secondary">
                        <span class="dashicons dashicons-filter"></span>
                        <?php _e('Filtrar', 'consulta-procesos'); ?>
                    </button>
                    
                    <button id="clear-filters" class="button button-secondary">
                        <span class="dashicons dashicons-dismiss"></span>
                        <?php _e('Limpiar', 'consulta-procesos'); ?>
                    </button>
                </div>
                
                <span class="logs-info" id="frontend-logs-count" style="margin-left: auto;">-</span>
            </div>
            
            <div id="frontend-logs" class="logs-container frontend-logs-container">
                <p class="description"><?php _e('Búsquedas realizadas desde el formulario público. Los datos se actualizan automáticamente.', 'consulta-procesos'); ?></p>
            </div>
            
            <!-- NUEVO: Paginación -->
            <div class="logs-pagination" id="logs-pagination" style="display: none;">
                <button id="prev-page" class="button button-secondary" disabled>
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    <?php _e('Anterior', 'consulta-procesos'); ?>
                </button>
                
                <span id="pagination-info" style="margin: 0 15px; line-height: 28px;">
                    <?php _e('Página 1 de 1', 'consulta-procesos'); ?>
                </span>
                
                <button id="next-page" class="button button-secondary" disabled>
                    <?php _e('Siguiente', 'consulta-procesos'); ?>
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
                
                <select id="page-size" style="margin-left: 20px;">
                    <option value="25">25 por página</option>
                    <option value="50" selected>50 por página</option>
                    <option value="100">100 por página</option>
                </select>
            </div>
        </div>

        <!-- Prueba de Stored Procedures -->
        <div class="cp-card">
            <h2><span class="dashicons dashicons-admin-tools"></span> <?php _e('Probar Stored Procedures', 'consulta-procesos'); ?></h2>
            
            <form id="cp-test-sp-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sp-name"><?php _e('Stored Procedure', 'consulta-procesos'); ?></label>
                        </th>
                        <td>
                            <select id="sp-name" name="sp_name" class="regular-text">
                                <option value="">-- Seleccionar SP --</option>
                                <option value="IDI.ConsultaContratosPorProveedor_TVEC">TVEC - Proveedores</option>
                                <option value="IDI.ConsultaContratosPorEntidad_TVEC">TVEC - Entidades</option>
                                <option value="IDI.ConsultaContratosPorProveedor_SECOPI">SECOPI - Proveedores</option>
                                <option value="IDI.ConsultaContratosPorEntidad_SECOPI">SECOPI - Entidades</option>
                                <option value="IDI.ConsultaContratosPorProveedor_SECOPII">SECOPII - Proveedores</option>
                                <option value="IDI.ConsultaContratosPorEntidad_SECOPII">SECOPII - Entidades</option>
                            </select>
                            <p class="description"><?php _e('Selecciona el stored procedure a probar', 'consulta-procesos'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sp-param1"><?php _e('Parámetro 1 (Documento/NIT)', 'consulta-procesos'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="sp-param1" name="param1" class="regular-text" placeholder="12345678" />
                            <p class="description"><?php _e('Número de documento o NIT', 'consulta-procesos'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sp-param2"><?php _e('Fecha Inicio', 'consulta-procesos'); ?></label>
                        </th>
                        <td>
                            <input type="date" id="sp-param2" name="param2" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sp-param3"><?php _e('Fecha Fin', 'consulta-procesos'); ?></label>
                        </th>
                        <td>
                            <input type="date" id="sp-param3" name="param3" class="regular-text" />
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="button" id="test-stored-procedure" class="button button-primary">
                        <span class="dashicons dashicons-database"></span>
                        <?php _e('Ejecutar Stored Procedure', 'consulta-procesos'); ?>
                    </button>
                </p>
            </form>
            
            <div id="sp-results" class="cp-results-section"></div>
        </div>

        <!-- Consulta Libre (Mejorada) -->
        <div class="cp-card">
            <h2><span class="dashicons dashicons-editor-code"></span> <?php _e('Consulta Libre (Admin)', 'consulta-procesos'); ?></h2>
            
            <div class="query-editor-wrapper">
                <textarea id="admin-sql-query" name="admin_sql_query" rows="8" placeholder="<?php _e('Escribe tu consulta SQL aquí...\n\nEjemplos permitidos:\nSELECT * FROM tabla\nEXEC stored_procedure param1, param2\nSP_HELP \'procedure_name\'', 'consulta-procesos'); ?>"></textarea>
            </div>
            
            <div class="query-controls">
                <button id="execute-admin-query" class="button button-primary">
                    <span class="dashicons dashicons-controls-play"></span>
                    <?php _e('Ejecutar', 'consulta-procesos'); ?>
                </button>
                
                <button id="clear-admin-query" class="button button-secondary">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Limpiar', 'consulta-procesos'); ?>
                </button>
            </div>
            
            <div id="admin-query-results" class="cp-results-section"></div>
        </div>

        <!-- Logs del Sistema -->
        <div class="cp-card">
            <h2><span class="dashicons dashicons-media-text"></span> <?php _e('Logs del Sistema WordPress', 'consulta-procesos'); ?></h2>
            
            <div class="logs-controls">
                <button id="refresh-logs" class="button button-secondary">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Actualizar Logs', 'consulta-procesos'); ?>
                </button>
                
                <button id="clear-logs" class="button button-secondary">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Limpiar Logs', 'consulta-procesos'); ?>
                </button>
                
                <span class="logs-info">
                    <?php 
                    $log_file = WP_CONTENT_DIR . '/debug.log';
                    if (file_exists($log_file)) {
                        echo 'Archivo: ' . basename($log_file) . ' (' . size_format(filesize($log_file)) . ')';
                    } else {
                        echo 'Archivo de logs no encontrado';
                    }
                    ?>
                </span>
            </div>
            
            <div id="system-logs" class="logs-container">
                <p class="description"><?php _e('Haz clic en "Actualizar Logs" para ver los logs más recientes del sistema WordPress', 'consulta-procesos'); ?></p>
            </div>
        </div>
    </div>
</div>

<style>
.cp-logs-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    margin-top: 20px;
}

.cp-results-section {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    max-height: 400px;
    overflow-y: auto;
}

.logs-container {
    background: #1e1e1e;
    color: #00ff00;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    padding: 15px;
    border-radius: 4px;
    max-height: 500px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-wrap: break-word;
}

/* NUEVO: Estilos específicos para logs del frontend */
.frontend-logs-container {
    background: #ffffff;
    color: #333333;
    font-family: system-ui, -apple-system, sans-serif;
    font-size: 13px;
    padding: 0;
    max-height: 600px;
    overflow-x: auto;
}

.logs-controls {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e9ecef;
    flex-wrap: wrap;
}

.logs-info {
    margin-left: auto;
    font-size: 12px;
    color: #666;
}

.logs-filters {
    display: flex;
    align-items: center;
    gap: 10px;
}

#admin-sql-query {
    width: 100%;
    font-family: 'Courier New', monospace;
    font-size: 14px;
    line-height: 1.4;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    background-color: #fafafa;
}

.query-controls {
    margin-top: 15px;
    display: flex;
    gap: 10px;
}

.results-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.results-table th,
.results-table td {
    padding: 8px;
    text-align: left;
    border-bottom: 1px solid #ddd;
    white-space: nowrap;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.results-table th {
    background: #f1f1f1;
    font-weight: 600;
    position: sticky;
    top: 0;
}

/* NUEVO: Tabla de logs del frontend mejorada */
.frontend-logs-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    background: white;
    color: black;
}

.frontend-logs-table th,
.frontend-logs-table td {
    padding: 10px 8px;
    text-align: left;
    border-bottom: 1px solid #ddd;
    vertical-align: top;
}

.frontend-logs-table th {
    background: #f8f9fa;
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 2;
    border-bottom: 2px solid #ddd;
}

/* Estados de búsqueda */
.status-success {
    color: #155724;
    font-weight: bold;
}

.status-error {
    color: #721c24;
    font-weight: bold;
}

.status-partial_success {
    color: #856404;
    font-weight: bold;
}

.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-badge.success {
    background-color: #d4edda;
    color: #155724;
}

.status-badge.error {
    background-color: #f8d7da;
    color: #721c24;
}

.status-badge.partial_success {
    background-color: #fff3cd;
    color: #856404;
}

/* Estilos para la paginación */
.logs-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 15px;
    border-top: 1px solid #e9ecef;
    background: #f8f9fa;
    margin-top: 10px;
}

/* Estadísticas mejoradas */
.cp-frontend-stats {
    background: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%);
    border: 1px solid #28a745;
}

.cp-frontend-stats h2 {
    color: #155724;
}

.frontend-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.frontend-stat-item {
    background: rgba(255,255,255,0.8);
    padding: 20px 15px;
    border-radius: 8px;
    text-align: center;
    border: 1px solid rgba(40, 167, 69, 0.2);
    transition: all 0.3s ease;
}

.frontend-stat-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
}

.frontend-stat-item strong {
    display: block;
    font-size: 28px;
    font-weight: bold;
    color: #155724;
    margin-bottom: 8px;
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.frontend-stat-item span {
    font-size: 12px;
    color: #495057;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.stats-controls {
    text-align: center;
    margin-top: 15px;
}

/* Mensajes de log con color */
.log-error {
    color: #ff4444;
    font-weight: bold;
}

.log-warning {
    color: #ff8800;
}

.log-info {
    color: #00ff00;
}

.log-debug {
    color: #88ccff;
}

/* Tooltip para celdas truncadas */
.truncated-cell {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    cursor: help;
}

/* Responsive */
@media (max-width: 1200px) {
    .logs-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .logs-filters {
        justify-content: center;
        margin-top: 10px;
    }
    
    .frontend-stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
}

@media (max-width: 768px) {
    .frontend-logs-table {
        font-size: 11px;
    }
    
    .frontend-logs-table th,
    .frontend-logs-table td {
        padding: 6px 4px;
    }
    
    .logs-pagination {
        flex-direction: column;
        gap: 10px;
    }
    
    .frontend-stats-grid {
        grid-template-columns: 1fr;
    }
    
    .logs-filters {
        flex-direction: column;
        width: 100%;
    }
    
    .logs-filters select,
    .logs-filters input,
    .logs-filters button {
        width: 100%;
    }
}

/* Loading states */
.loading-logs {
    text-align: center;
    padding: 40px;
    color: #666;
}

.loading-logs .spinner {
    float: none;
    margin-right: 10px;
}

/* Empty state */
.no-logs-message {
    text-align: center;
    padding: 40px;
    color: #666;
    font-style: italic;
}

.no-logs-message .dashicons {
    font-size: 48px;
    opacity: 0.3;
    margin-bottom: 15px;
}
</style>