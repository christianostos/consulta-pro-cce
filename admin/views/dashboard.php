<?php
/**
 * Vista: Dashboard Principal
 * Archivo: admin/views/dashboard.php
 */

if (!defined('ABSPATH')) {
    exit;
}

$admin = CP_Admin::get_instance();
$plugin_info = $admin->get_plugin_info();
?>

<div class="wrap">
    <h1><?php _e('Consulta Procesos - Panel Principal', 'consulta-procesos'); ?></h1>
    
    <div class="cp-dashboard">
        <!-- Diagnóstico del Sistema -->
        <div class="cp-card">
            <h2><span class="dashicons dashicons-admin-tools"></span> <?php _e('Diagnóstico del Sistema', 'consulta-procesos'); ?></h2>
            <div id="system-diagnosis">
                <button id="diagnose-system" class="button">
                    <?php _e('Ejecutar Diagnóstico', 'consulta-procesos'); ?>
                </button>
                <div id="diagnosis-result"></div>
            </div>
        </div>
        
        <!-- Estado de Conexión -->
        <div class="cp-card">
            <h2><span class="dashicons dashicons-database"></span> <?php _e('Estado de Conexión', 'consulta-procesos'); ?></h2>
            <div id="connection-status">
                <button id="test-connection" class="button button-primary">
                    <?php _e('Probar Conexión', 'consulta-procesos'); ?>
                </button>
                <div id="connection-result"></div>
            </div>
        </div>
        
        <!-- Tablas Disponibles -->
        <div class="cp-card">
            <h2><span class="dashicons dashicons-list-view"></span> <?php _e('Tablas Disponibles', 'consulta-procesos'); ?></h2>
            <div id="tables-container">
                <button id="load-tables" class="button">
                    <?php _e('Cargar Tablas', 'consulta-procesos'); ?>
                </button>
                <div id="tables-list"></div>
            </div>
        </div>
        
        <!-- Información del Plugin -->
        <div class="cp-card">
            <h2><span class="dashicons dashicons-info"></span> <?php _e('Información del Plugin', 'consulta-procesos'); ?></h2>
            <div class="plugin-info">
                <table class="widefat fixed striped">
                    <tbody>
                        <tr>
                            <td><strong>Versión del Plugin:</strong></td>
                            <td><?php echo esc_html($plugin_info['version']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>PHP:</strong></td>
                            <td><?php echo esc_html($plugin_info['php_version']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Sistema:</strong></td>
                            <td><?php echo esc_html($plugin_info['os']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>WordPress:</strong></td>
                            <td><?php echo esc_html($plugin_info['wordpress_version']); ?></td>
                        </tr>
                    </tbody>
                </table>
                
                <h4><?php _e('Extensiones SQL Server:', 'consulta-procesos'); ?></h4>
                <ul class="extensions-list">
                    <li>PDO SQLSRV: <?php echo $plugin_info['extensions']['pdo_sqlsrv'] ? '<span class="status-ok">✅ Disponible</span>' : '<span class="status-error">❌ No disponible</span>'; ?></li>
                    <li>SQLSRV: <?php echo $plugin_info['extensions']['sqlsrv'] ? '<span class="status-ok">✅ Disponible</span>' : '<span class="status-error">❌ No disponible</span>'; ?></li>
                    <li>OpenSSL: <?php echo $plugin_info['extensions']['openssl'] ? '<span class="status-ok">✅ Disponible</span>' : '<span class="status-error">❌ No disponible</span>'; ?></li>
                </ul>
                
                <?php if (!$plugin_info['extensions']['pdo_sqlsrv'] && !$plugin_info['extensions']['sqlsrv']): ?>
                <div class="notice notice-error inline">
                    <p><strong>⚠️ Atención:</strong> No tienes las extensiones de SQL Server instaladas. 
                    <a href="#" id="show-install-instructions">Ver instrucciones de instalación</a></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Acciones Rápidas -->
        <div class="cp-card">
            <h2><span class="dashicons dashicons-lightbulb"></span> <?php _e('Acciones Rápidas', 'consulta-procesos'); ?></h2>
            <div class="quick-actions">
                <a href="<?php echo admin_url('admin.php?page=consulta-procesos-config'); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php _e('Configurar Conexión', 'consulta-procesos'); ?>
                </a>
                
                <a href="<?php echo admin_url('admin.php?page=consulta-procesos-query'); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-editor-code"></span>
                    <?php _e('Nueva Consulta', 'consulta-procesos'); ?>
                </a>
                
                <button id="refresh-dashboard" class="button button-secondary">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Actualizar Todo', 'consulta-procesos'); ?>
                </button>
            </div>
        </div>
        
        <!-- Estadísticas Rápidas -->
        <div class="cp-card">
            <h2><span class="dashicons dashicons-chart-area"></span> <?php _e('Resumen', 'consulta-procesos'); ?></h2>
            <div id="quick-stats">
                <div class="stat-item">
                    <span class="stat-number" id="tables-count">-</span>
                    <span class="stat-label"><?php _e('Tablas', 'consulta-procesos'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number" id="connection-status-indicator">-</span>
                    <span class="stat-label"><?php _e('Estado', 'consulta-procesos'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $plugin_info['extensions']['pdo_sqlsrv'] || $plugin_info['extensions']['sqlsrv'] ? '✅' : '❌'; ?></span>
                    <span class="stat-label"><?php _e('Extensiones', 'consulta-procesos'); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Instrucciones de Instalación -->
    <div id="install-instructions" style="display: none;" class="cp-modal">
        <div class="cp-modal-content">
            <span class="cp-modal-close">&times;</span>
            <h2><?php _e('Instrucciones de Instalación de Extensiones', 'consulta-procesos'); ?></h2>
            <div class="install-tabs">
                <button class="tab-button active" data-tab="ubuntu">Ubuntu/Debian</button>
                <button class="tab-button" data-tab="centos">CentOS/RHEL</button>
                <button class="tab-button" data-tab="windows">Windows</button>
            </div>
            
            <div id="ubuntu-tab" class="tab-content active">
                <h4>Para Ubuntu/Debian:</h4>
                <pre><code># Instalar drivers de Microsoft
curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add -
curl https://packages.microsoft.com/config/ubuntu/20.04/prod.list > /etc/apt/sources.list.d/mssql-release.list
apt-get update
ACCEPT_EULA=Y apt-get install -y msodbcsql17 unixodbc-dev

# Instalar extensiones PHP
apt-get install -y php-dev php-pear
pecl install sqlsrv pdo_sqlsrv

# Agregar a php.ini
echo "extension=pdo_sqlsrv.so" >> /etc/php/8.0/apache2/php.ini
echo "extension=sqlsrv.so" >> /etc/php/8.0/apache2/php.ini

# Reiniciar Apache
systemctl restart apache2</code></pre>
            </div>
            
            <div id="centos-tab" class="tab-content">
                <h4>Para CentOS/RHEL:</h4>
                <pre><code># Instalar repositorio de Microsoft
curl https://packages.microsoft.com/config/rhel/8/prod.repo > /etc/yum.repos.d/mssql-release.repo
yum remove unixODBC-utf16 unixODBC-utf16-devel
ACCEPT_EULA=Y yum install -y msodbcsql17 unixODBC-devel

# Instalar extensiones
yum install -y php-devel php-pear
pecl install sqlsrv pdo_sqlsrv

# Reiniciar servidor web
systemctl restart httpd</code></pre>
            </div>
            
            <div id="windows-tab" class="tab-content">
                <h4>Para Windows:</h4>
                <ol>
                    <li>Descarga los drivers desde <a href="https://docs.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server" target="_blank">Microsoft</a></li>
                    <li>Copia los archivos .dll a la carpeta ext/ de PHP</li>
                    <li>Agrega estas líneas a php.ini:</li>
                </ol>
                <pre><code>extension=php_sqlsrv.dll
extension=php_pdo_sqlsrv.dll</code></pre>
                <p>Reinicia el servidor web después de los cambios.</p>
            </div>
        </div>
    </div>
</div>
