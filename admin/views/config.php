<?php
/**
 * Vista: Configuración
 * Archivo: admin/views/config.php
 */


?>

<?php
if (!defined('ABSPATH')) {
    exit;
}

$admin = CP_Admin::get_instance();
?>

<div class="wrap">
    <h1><?php _e('Configuración - Consulta Procesos', 'consulta-procesos'); ?></h1>
    
    <div class="cp-config-container">
        <div class="cp-config-main">
            <form method="post" action="options.php" id="cp-config-form">
                <?php
                settings_fields('cp_settings_group');
                do_settings_sections('consulta-procesos-config');
                ?>
                
                <div class="cp-form-actions">
                    <?php submit_button(__('Guardar Configuración', 'consulta-procesos'), 'primary', 'submit', false); ?>
                    <button type="button" id="test-connection-config" class="button button-secondary">
                        <?php _e('Probar Conexión', 'consulta-procesos'); ?>
                    </button>
                    <button type="button" id="reset-config" class="button button-link-delete">
                        <?php _e('Limpiar Configuración', 'consulta-procesos'); ?>
                    </button>
                </div>
                
                <div id="config-test-result"></div>
            </form>
        </div>
        
        <div class="cp-config-sidebar">
            <div class="cp-card">
                <h3><?php _e('Ayuda de Configuración', 'consulta-procesos'); ?></h3>
                <div class="config-help">
                    <h4><?php _e('Para Docker:', 'consulta-procesos'); ?></h4>
                    <p><?php _e('Si WordPress está en Docker, usa:', 'consulta-procesos'); ?></p>
                    <code>host.docker.internal</code>
                    
                    <h4><?php _e('Para red local:', 'consulta-procesos'); ?></h4>
                    <p><?php _e('Usa la IP de tu máquina:', 'consulta-procesos'); ?></p>
                    <code>192.168.x.x</code>
                    
                    <h4><?php _e('Puerto estándar:', 'consulta-procesos'); ?></h4>
                    <p><?php _e('SQL Server usa por defecto:', 'consulta-procesos'); ?></p>
                    <code>1433</code>
                </div>
            </div>
            
            <div class="cp-card">
                <h3><?php _e('Configuración de Seguridad', 'consulta-procesos'); ?></h3>
                <div class="security-info">
                    <p><?php _e('Recomendaciones:', 'consulta-procesos'); ?></p>
                    <ul>
                        <li><?php _e('Usa un usuario específico con permisos limitados', 'consulta-procesos'); ?></li>
                        <li><?php _e('Solo permisos de lectura (SELECT)', 'consulta-procesos'); ?></li>
                        <li><?php _e('Contraseña segura', 'consulta-procesos'); ?></li>
                        <li><?php _e('Firewall configurado correctamente', 'consulta-procesos'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>