<?php
/**
 * Vista: Nueva Consulta
 * Archivo: admin/views/query.php
 */

// Para mostrar en el mismo artifact, agrego separador
echo "\n\n" . str_repeat('=', 80) . "\n";
echo "// ARCHIVO: admin/views/query.php\n";
echo str_repeat('=', 80) . "\n\n";
?>

<?php
if (!defined('ABSPATH')) {
    exit;
}

$admin = CP_Admin::get_instance();
?>

<div class="wrap">
    <h1><?php _e('Nueva Consulta SQL', 'consulta-procesos'); ?></h1>
    
    <div class="cp-query-container">
        <div class="cp-query-editor">
            <div class="cp-card">
                <h2><span class="dashicons dashicons-editor-code"></span> <?php _e('Editor de Consultas', 'consulta-procesos'); ?></h2>
                
                <div class="query-controls">
                    <button id="load-tables-query" class="button button-secondary">
                        <span class="dashicons dashicons-database"></span>
                        <?php _e('Ver Tablas', 'consulta-procesos'); ?>
                    </button>
                    
                    <button id="execute-query" class="button button-primary">
                        <span class="dashicons dashicons-controls-play"></span>
                        <?php _e('Ejecutar Consulta', 'consulta-procesos'); ?>
                    </button>
                    
                    <button id="save-query" class="button button-secondary">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e('Guardar Consulta', 'consulta-procesos'); ?>
                    </button>
                    
                    <button id="clear-query" class="button button-link">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Limpiar', 'consulta-procesos'); ?>
                    </button>
                </div>
                
                <div class="query-editor-wrapper">
                    <textarea id="sql-query" name="sql_query" rows="10" placeholder="<?php _e('Escribe tu consulta SQL aquí...\n\nEjemplo:\nSELECT TOP 10 * FROM tabla_ejemplo\nWHERE fecha >= \'2024-01-01\'', 'consulta-procesos'); ?>"></textarea>
                </div>
                
                <div class="query-info">
                    <small><?php _e('Tip: Solo se permiten consultas SELECT por seguridad', 'consulta-procesos'); ?></small>
                </div>
            </div>
        </div>
        
        <div class="cp-query-sidebar">
            <div class="cp-card">
                <h3><span class="dashicons dashicons-list-view"></span> <?php _e('Tablas Disponibles', 'consulta-procesos'); ?></h3>
                <div id="tables-browser">
                    <div class="tables-loading" style="display: none;">
                        <span class="spinner is-active"></span>
                        <?php _e('Cargando tablas...', 'consulta-procesos'); ?>
                    </div>
                    <div id="tables-tree"></div>
                </div>
            </div>
            
            <div class="cp-card">
                <h3><span class="dashicons dashicons-saved"></span> <?php _e('Consultas Guardadas', 'consulta-procesos'); ?></h3>
                <div id="saved-queries">
                    <p><?php _e('No hay consultas guardadas', 'consulta-procesos'); ?></p>
                    <!-- Aquí se cargarán las consultas guardadas vía AJAX -->
                </div>
            </div>
            
            <div class="cp-card">
                <h3><span class="dashicons dashicons-lightbulb"></span> <?php _e('Ejemplos', 'consulta-procesos'); ?></h3>
                <div class="query-examples">
                    <button class="example-query" data-query="SELECT TOP 10 * FROM INFORMATION_SCHEMA.TABLES">
                        <?php _e('Listar Tablas', 'consulta-procesos'); ?>
                    </button>
                    <button class="example-query" data-query="SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tu_tabla'">
                        <?php _e('Ver Columnas', 'consulta-procesos'); ?>
                    </button>
                    <button class="example-query" data-query="SELECT COUNT(*) as total FROM tu_tabla">
                        <?php _e('Contar Registros', 'consulta-procesos'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="cp-query-results">
        <div class="cp-card">
            <h2><span class="dashicons dashicons-analytics"></span> <?php _e('Resultados', 'consulta-procesos'); ?></h2>
            
            <div class="results-controls" style="display: none;">
                <button id="export-csv" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Exportar CSV', 'consulta-procesos'); ?>
                </button>
                
                <button id="export-excel" class="button button-secondary">
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    <?php _e('Exportar Excel', 'consulta-procesos'); ?>
                </button>
                
                <span class="results-info"></span>
            </div>
            
            <div id="query-results-container">
                <div class="no-results">
                    <span class="dashicons dashicons-database"></span>
                    <p><?php _e('Ejecuta una consulta para ver los resultados aquí', 'consulta-procesos'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>