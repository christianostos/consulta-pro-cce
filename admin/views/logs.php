<?php
/**
 * Vista: Logs y Debugging
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
            <h2><span class="dashicons dashicons-media-text"></span> <?php _e('Logs del Sistema', 'consulta-procesos'); ?></h2>
            
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
                        echo 'Archivo: ' . $log_file . ' (' . size_format(filesize($log_file)) . ')';
                    } else {
                        echo 'Archivo de logs no encontrado';
                    }
                    ?>
                </span>
            </div>
            
            <div id="system-logs" class="logs-container">
                <p class="description"><?php _e('Haz clic en "Actualizar Logs" para ver los logs más recientes', 'consulta-procesos'); ?></p>
            </div>
        </div>

        <!-- Logs de Búsquedas del Frontend -->
        <div class="cp-card">
            <h2><span class="dashicons dashicons-search"></span> <?php _e('Logs de Búsquedas Frontend', 'consulta-procesos'); ?></h2>
            
            <div class="logs-controls">
                <button id="refresh-frontend-logs" class="button button-secondary">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Actualizar', 'consulta-procesos'); ?>
                </button>
                
                <span class="logs-info" id="frontend-logs-count">-</span>
            </div>
            
            <div id="frontend-logs" class="logs-container">
                <p class="description"><?php _e('Búsquedas realizadas desde el formulario público', 'consulta-procesos'); ?></p>
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
    max-height: 400px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.logs-controls {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e9ecef;
}

.logs-info {
    margin-left: auto;
    font-size: 12px;
    color: #666;
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

.frontend-logs-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    background: white;
    color: black;
}

.frontend-logs-table th,
.frontend-logs-table td {
    padding: 8px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.frontend-logs-table th {
    background: #f1f1f1;
    font-weight: 600;
}

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
</style>