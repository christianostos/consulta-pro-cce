<?php
/**
 * Vista: Configuración de Parámetros del Frontend
 * Archivo: admin/views/settings.php
 * ACTUALIZADO: Con configuración de APIs
 */

if (!defined('ABSPATH')) {
    exit;
}

$admin = CP_Admin::get_instance();

// Procesar formulario si se envió
if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'cp_settings_nonce')) {
    // Guardar términos de uso
    $terms_content = wp_kses_post($_POST['cp_terms_content']);
    update_option('cp_terms_content', $terms_content);
    
    // Guardar configuraciones de búsqueda TVEC
    update_option('cp_tvec_active', isset($_POST['cp_tvec_active']) ? 1 : 0);
    update_option('cp_tvec_method', sanitize_text_field($_POST['cp_tvec_method']));
    update_option('cp_tvec_api_url_proveedores', esc_url_raw($_POST['cp_tvec_api_url_proveedores']));
    update_option('cp_tvec_api_url_entidades', esc_url_raw($_POST['cp_tvec_api_url_entidades']));
    update_option('cp_tvec_api_date_field', sanitize_text_field($_POST['cp_tvec_api_date_field']));
    
    // Guardar configuraciones de búsqueda SECOPI
    update_option('cp_secopi_active', isset($_POST['cp_secopi_active']) ? 1 : 0);
    update_option('cp_secopi_method', sanitize_text_field($_POST['cp_secopi_method']));
    update_option('cp_secopi_api_url_proveedores', esc_url_raw($_POST['cp_secopi_api_url_proveedores']));
    update_option('cp_secopi_api_url_entidades', esc_url_raw($_POST['cp_secopi_api_url_entidades']));
    update_option('cp_secopi_api_date_field', sanitize_text_field($_POST['cp_secopi_api_date_field']));
    
    // Guardar configuraciones de búsqueda SECOPII
    update_option('cp_secopii_active', isset($_POST['cp_secopii_active']) ? 1 : 0);
    update_option('cp_secopii_method', sanitize_text_field($_POST['cp_secopii_method']));
    update_option('cp_secopii_api_url_proveedores', esc_url_raw($_POST['cp_secopii_api_url_proveedores']));
    update_option('cp_secopii_api_url_entidades', esc_url_raw($_POST['cp_secopii_api_url_entidades']));
    update_option('cp_secopii_api_date_field', sanitize_text_field($_POST['cp_secopii_api_date_field']));
    
    // Guardar configuraciones de rendimiento
    update_option('cp_enable_cache', isset($_POST['cp_enable_cache']) ? 1 : 0);
    update_option('cp_cache_duration', intval($_POST['cp_cache_duration']));
    update_option('cp_use_stored_procedures', isset($_POST['cp_use_stored_procedures']) ? 1 : 0);
    update_option('cp_max_results_per_source', intval($_POST['cp_max_results_per_source']));
    
    echo '<div class="notice notice-success"><p>' . __('Configuración guardada exitosamente.', 'consulta-procesos') . '</p></div>';
}

// Obtener valores actuales
$terms_content = get_option('cp_terms_content', '');

// TVEC
$tvec_active = get_option('cp_tvec_active', 1);
$tvec_method = get_option('cp_tvec_method', 'database');
$tvec_api_url_proveedores = get_option('cp_tvec_api_url_proveedores', '');
$tvec_api_url_entidades = get_option('cp_tvec_api_url_entidades', '');
$tvec_api_date_field = get_option('cp_tvec_api_date_field', '');

// SECOPI
$secopi_active = get_option('cp_secopi_active', 1);
$secopi_method = get_option('cp_secopi_method', 'database');
$secopi_api_url_proveedores = get_option('cp_secopi_api_url_proveedores', '');
$secopi_api_url_entidades = get_option('cp_secopi_api_url_entidades', '');
$secopi_api_date_field = get_option('cp_secopi_api_date_field', '');

// SECOPII
$secopii_active = get_option('cp_secopii_active', 1);
$secopii_method = get_option('cp_secopii_method', 'database');
$secopii_api_url_proveedores = get_option('cp_secopii_api_url_proveedores', '');
$secopii_api_url_entidades = get_option('cp_secopii_api_url_entidades', '');
$secopii_api_date_field = get_option('cp_secopii_api_date_field', '');
?>

<div class="wrap">
    <h1><?php _e('Configuración de Parámetros', 'consulta-procesos'); ?></h1>
    
    <div class="cp-settings-container">
        <form method="post" action="" id="cp-settings-form">
            <?php wp_nonce_field('cp_settings_nonce'); ?>
            
            <!-- Términos de Uso -->
            <div class="cp-card">
                <h2><span class="dashicons dashicons-media-text"></span> <?php _e('Términos de Uso', 'consulta-procesos'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cp_terms_content"><?php _e('Contenido de Términos', 'consulta-procesos'); ?></label>
                        </th>
                        <td>
                            <?php
                            wp_editor($terms_content, 'cp_terms_content', array(
                                'textarea_name' => 'cp_terms_content',
                                'media_buttons' => false,
                                'textarea_rows' => 10,
                                'teeny' => true,
                                'quicktags' => array(
                                    'buttons' => 'strong,em,link,ul,ol,li'
                                )
                            ));
                            ?>
                            <p class="description">
                                <?php _e('Contenido que se mostrará en la primera etapa del formulario. Puedes usar HTML básico como negritas, cursivas, listas y enlaces.', 'consulta-procesos'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Configuración de Búsquedas -->
            <div class="cp-card">
                <h2><span class="dashicons dashicons-search"></span> <?php _e('Configuración de Búsquedas', 'consulta-procesos'); ?></h2>
                
                <p class="description">
                    <?php _e('Configure qué tipos de búsquedas estarán activas y el método de consulta para cada una.', 'consulta-procesos'); ?>
                </p>
                
                <!-- TVEC -->
                <div class="cp-search-config-section">
                    <h3><?php _e('TVEC (Catálogo de Proveedores)', 'consulta-procesos'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Estado', 'consulta-procesos'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="cp_tvec_active" value="1" <?php checked($tvec_active, 1); ?>>
                                    <?php _e('Activar búsqueda en TVEC', 'consulta-procesos'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Marque esta opción para habilitar las consultas en la tabla TVEC.', 'consulta-procesos'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Método de Consulta', 'consulta-procesos'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="radio" name="cp_tvec_method" value="database" <?php checked($tvec_method, 'database'); ?> class="cp-method-radio" data-target="tvec">
                                        <?php _e('Base de Datos SQL Server', 'consulta-procesos'); ?>
                                    </label><br>
                                    <label>
                                        <input type="radio" name="cp_tvec_method" value="api" <?php checked($tvec_method, 'api'); ?> class="cp-method-radio" data-target="tvec">
                                        <?php _e('API Externa', 'consulta-procesos'); ?>
                                    </label>
                                </fieldset>
                                <p class="description">
                                    <?php _e('Seleccione si las consultas TVEC se realizarán mediante la base de datos configurada o una API externa.', 'consulta-procesos'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- Configuración de API para TVEC -->
                    <div class="cp-api-config" id="cp-tvec-api-config" style="<?php echo $tvec_method === 'api' ? '' : 'display: none;'; ?>">
                        <h4><span class="dashicons dashicons-cloud"></span> <?php _e('Configuración de API - TVEC', 'consulta-procesos'); ?></h4>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="cp_tvec_api_url_proveedores"><?php _e('URL API Proveedores', 'consulta-procesos'); ?></label>
                                </th>
                                <td>
                                    <input type="url" name="cp_tvec_api_url_proveedores" id="cp_tvec_api_url_proveedores" 
                                           value="<?php echo esc_attr($tvec_api_url_proveedores); ?>" class="regular-text" 
                                           placeholder="https://www.datos.gov.co/resource/jbjy-vk9h.json?documento_proveedor=">
                                    <p class="description">
                                        <?php _e('URL de la API para consultar proveedores. Debe terminar con el parámetro de búsqueda (ej: documento_proveedor=). El número de documento se agregará automáticamente.', 'consulta-procesos'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="cp_tvec_api_url_entidades"><?php _e('URL API Entidades', 'consulta-procesos'); ?></label>
                                </th>
                                <td>
                                    <input type="url" name="cp_tvec_api_url_entidades" id="cp_tvec_api_url_entidades" 
                                           value="<?php echo esc_attr($tvec_api_url_entidades); ?>" class="regular-text" 
                                           placeholder="https://www.datos.gov.co/resource/jbjy-vk9h.json?nit_entidad=">
                                    <p class="description">
                                        <?php _e('URL de la API para consultar entidades. Debe terminar con el parámetro de búsqueda (ej: nit_entidad=). El número de documento se agregará automáticamente.', 'consulta-procesos'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="cp_tvec_api_date_field"><?php _e('Campo de Fecha en API', 'consulta-procesos'); ?></label>
                                </th>
                                <td>
                                    <input type="text" name="cp_tvec_api_date_field" id="cp_tvec_api_date_field" 
                                           value="<?php echo esc_attr($tvec_api_date_field); ?>" class="regular-text" 
                                           placeholder="fecha_de_inicio_del_contrato">
                                    <p class="description">
                                        <?php _e('Nombre del campo de fecha en la respuesta de la API que se usará para filtrar por rango de fechas.', 'consulta-procesos'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- SECOPI -->
                <div class="cp-search-config-section">
                    <h3><?php _e('SECOPI (Sistema de Información)', 'consulta-procesos'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Estado', 'consulta-procesos'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="cp_secopi_active" value="1" <?php checked($secopi_active, 1); ?>>
                                    <?php _e('Activar búsqueda en SECOPI', 'consulta-procesos'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Marque esta opción para habilitar las consultas en la tabla SECOPI.', 'consulta-procesos'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Método de Consulta', 'consulta-procesos'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="radio" name="cp_secopi_method" value="database" <?php checked($secopi_method, 'database'); ?> class="cp-method-radio" data-target="secopi">
                                        <?php _e('Base de Datos SQL Server', 'consulta-procesos'); ?>
                                    </label><br>
                                    <label>
                                        <input type="radio" name="cp_secopi_method" value="api" <?php checked($secopi_method, 'api'); ?> class="cp-method-radio" data-target="secopi">
                                        <?php _e('API Externa', 'consulta-procesos'); ?>
                                    </label>
                                </fieldset>
                                <p class="description">
                                    <?php _e('Seleccione si las consultas SECOPI se realizarán mediante la base de datos configurada o una API externa.', 'consulta-procesos'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- Configuración de API para SECOPI -->
                    <div class="cp-api-config" id="cp-secopi-api-config" style="<?php echo $secopi_method === 'api' ? '' : 'display: none;'; ?>">
                        <h4><span class="dashicons dashicons-cloud"></span> <?php _e('Configuración de API - SECOPI', 'consulta-procesos'); ?></h4>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="cp_secopi_api_url_proveedores"><?php _e('URL API Proveedores', 'consulta-procesos'); ?></label>
                                </th>
                                <td>
                                    <input type="url" name="cp_secopi_api_url_proveedores" id="cp_secopi_api_url_proveedores" 
                                           value="<?php echo esc_attr($secopi_api_url_proveedores); ?>" class="regular-text" 
                                           placeholder="https://www.datos.gov.co/resource/secopi.json?documento_proveedor=">
                                    <p class="description">
                                        <?php _e('URL de la API para consultar proveedores. Debe terminar con el parámetro de búsqueda (ej: documento_proveedor=). El número de documento se agregará automáticamente.', 'consulta-procesos'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="cp_secopi_api_url_entidades"><?php _e('URL API Entidades', 'consulta-procesos'); ?></label>
                                </th>
                                <td>
                                    <input type="url" name="cp_secopi_api_url_entidades" id="cp_secopi_api_url_entidades" 
                                           value="<?php echo esc_attr($secopi_api_url_entidades); ?>" class="regular-text" 
                                           placeholder="https://www.datos.gov.co/resource/secopi.json?nit_entidad=">
                                    <p class="description">
                                        <?php _e('URL de la API para consultar entidades. Debe terminar con el parámetro de búsqueda (ej: nit_entidad=). El número de documento se agregará automáticamente.', 'consulta-procesos'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="cp_secopi_api_date_field"><?php _e('Campo de Fecha en API', 'consulta-procesos'); ?></label>
                                </th>
                                <td>
                                    <input type="text" name="cp_secopi_api_date_field" id="cp_secopi_api_date_field" 
                                           value="<?php echo esc_attr($secopi_api_date_field); ?>" class="regular-text" 
                                           placeholder="fecha_firma_contrato">
                                    <p class="description">
                                        <?php _e('Nombre del campo de fecha en la respuesta de la API que se usará para filtrar por rango de fechas.', 'consulta-procesos'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- SECOPII -->
                <div class="cp-search-config-section">
                    <h3><?php _e('SECOPII (Sistema Extendido)', 'consulta-procesos'); ?></h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Estado', 'consulta-procesos'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="cp_secopii_active" value="1" <?php checked($secopii_active, 1); ?>>
                                    <?php _e('Activar búsqueda en SECOPII', 'consulta-procesos'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Marque esta opción para habilitar las consultas en la tabla SECOPII.', 'consulta-procesos'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Método de Consulta', 'consulta-procesos'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="radio" name="cp_secopii_method" value="database" <?php checked($secopii_method, 'database'); ?> class="cp-method-radio" data-target="secopii">
                                        <?php _e('Base de Datos SQL Server', 'consulta-procesos'); ?>
                                    </label><br>
                                    <label>
                                        <input type="radio" name="cp_secopii_method" value="api" <?php checked($secopii_method, 'api'); ?> class="cp-method-radio" data-target="secopii">
                                        <?php _e('API Externa', 'consulta-procesos'); ?>
                                    </label>
                                </fieldset>
                                <p class="description">
                                    <?php _e('Seleccione si las consultas SECOPII se realizarán mediante la base de datos configurada o una API externa.', 'consulta-procesos'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <!-- Configuración de API para SECOPII -->
                    <div class="cp-api-config" id="cp-secopii-api-config" style="<?php echo $secopii_method === 'api' ? '' : 'display: none;'; ?>">
                        <h4><span class="dashicons dashicons-cloud"></span> <?php _e('Configuración de API - SECOPII', 'consulta-procesos'); ?></h4>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="cp_secopii_api_url_proveedores"><?php _e('URL API Proveedores', 'consulta-procesos'); ?></label>
                                </th>
                                <td>
                                    <input type="url" name="cp_secopii_api_url_proveedores" id="cp_secopii_api_url_proveedores" 
                                           value="<?php echo esc_attr($secopii_api_url_proveedores); ?>" class="regular-text" 
                                           placeholder="https://www.datos.gov.co/resource/secopii.json?documento_proveedor=">
                                    <p class="description">
                                        <?php _e('URL de la API para consultar proveedores. Debe terminar con el parámetro de búsqueda (ej: documento_proveedor=). El número de documento se agregará automáticamente.', 'consulta-procesos'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="cp_secopii_api_url_entidades"><?php _e('URL API Entidades', 'consulta-procesos'); ?></label>
                                </th>
                                <td>
                                    <input type="url" name="cp_secopii_api_url_entidades" id="cp_secopii_api_url_entidades" 
                                           value="<?php echo esc_attr($secopii_api_url_entidades); ?>" class="regular-text" 
                                           placeholder="https://www.datos.gov.co/resource/secopii.json?nit_entidad=">
                                    <p class="description">
                                        <?php _e('URL de la API para consultar entidades. Debe terminar con el parámetro de búsqueda (ej: nit_entidad=). El número de documento se agregará automáticamente.', 'consulta-procesos'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="cp_secopii_api_date_field"><?php _e('Campo de Fecha en API', 'consulta-procesos'); ?></label>
                                </th>
                                <td>
                                    <input type="text" name="cp_secopii_api_date_field" id="cp_secopii_api_date_field" 
                                           value="<?php echo esc_attr($secopii_api_date_field); ?>" class="regular-text" 
                                           placeholder="approval_date">
                                    <p class="description">
                                        <?php _e('Nombre del campo de fecha en la respuesta de la API que se usará para filtrar por rango de fechas.', 'consulta-procesos'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Vista Previa del Shortcode -->
            <div class="cp-card">
                <h2><span class="dashicons dashicons-shortcode"></span> <?php _e('Uso del Shortcode', 'consulta-procesos'); ?></h2>
                
                <div class="cp-shortcode-info">
                    <p><?php _e('Para mostrar el formulario de consulta en cualquier página o entrada, utilice el siguiente shortcode:', 'consulta-procesos'); ?></p>
                    
                    <div class="cp-shortcode-examples">
                        <h4><?php _e('Uso básico:', 'consulta-procesos'); ?></h4>
                        <code>[consulta_procesos]</code>
                        
                        <h4><?php _e('Con título personalizado:', 'consulta-procesos'); ?></h4>
                        <code>[consulta_procesos title="Mi Título Personalizado"]</code>
                        
                        <h4><?php _e('Sin mostrar título:', 'consulta-procesos'); ?></h4>
                        <code>[consulta_procesos show_title="false"]</code>
                    </div>
                    
                    <div class="cp-shortcode-preview">
                        <h4><?php _e('Estado actual de las búsquedas:', 'consulta-procesos'); ?></h4>
                        <ul class="cp-status-list">
                            <li>
                                <span class="cp-status-indicator <?php echo $tvec_active ? 'active' : 'inactive'; ?>"></span>
                                <strong>TVEC:</strong> 
                                <?php echo $tvec_active ? __('Activo', 'consulta-procesos') : __('Inactivo', 'consulta-procesos'); ?>
                                (<?php echo $tvec_method === 'database' ? __('Base de Datos', 'consulta-procesos') : __('API', 'consulta-procesos'); ?>)
                            </li>
                            <li>
                                <span class="cp-status-indicator <?php echo $secopi_active ? 'active' : 'inactive'; ?>"></span>
                                <strong>SECOPI:</strong> 
                                <?php echo $secopi_active ? __('Activo', 'consulta-procesos') : __('Inactivo', 'consulta-procesos'); ?>
                                (<?php echo $secopi_method === 'database' ? __('Base de Datos', 'consulta-procesos') : __('API', 'consulta-procesos'); ?>)
                            </li>
                            <li>
                                <span class="cp-status-indicator <?php echo $secopii_active ? 'active' : 'inactive'; ?>"></span>
                                <strong>SECOPII:</strong> 
                                <?php echo $secopii_active ? __('Activo', 'consulta-procesos') : __('Inactivo', 'consulta-procesos'); ?>
                                (<?php echo $secopii_method === 'database' ? __('Base de Datos', 'consulta-procesos') : __('API', 'consulta-procesos'); ?>)
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Configuración de Rendimiento -->
            <div class="cp-card">
                <h2><span class="dashicons dashicons-performance"></span> <?php _e('Configuración de Rendimiento', 'consulta-procesos'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="cp_enable_cache"><?php _e('Habilitar Caché', 'consulta-procesos'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="cp_enable_cache" id="cp_enable_cache" value="1" 
                                    <?php checked(get_option('cp_enable_cache', true)); ?>>
                                <?php _e('Activar caché de resultados de consultas', 'consulta-procesos'); ?>
                            </label>
                            <p class="description">
                                <?php _e('El caché mejora el rendimiento almacenando temporalmente los resultados de consultas frecuentes.', 'consulta-procesos'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cp_cache_duration"><?php _e('Duración del Caché (segundos)', 'consulta-procesos'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="cp_cache_duration" id="cp_cache_duration" 
                                value="<?php echo esc_attr(get_option('cp_cache_duration', 300)); ?>" 
                                min="60" max="3600" class="small-text">
                            <p class="description">
                                <?php _e('Tiempo en segundos que se mantendrán los resultados en caché (60-3600 segundos).', 'consulta-procesos'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cp_use_stored_procedures"><?php _e('Usar Stored Procedures', 'consulta-procesos'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="cp_use_stored_procedures" id="cp_use_stored_procedures" value="1" 
                                    <?php checked(get_option('cp_use_stored_procedures', false)); ?>>
                                <?php _e('Usar stored procedures cuando estén disponibles', 'consulta-procesos'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Los stored procedures pueden mejorar el rendimiento pero requieren permisos especiales en la base de datos.', 'consulta-procesos'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cp_max_results_per_source"><?php _e('Máx. Resultados por Fuente', 'consulta-procesos'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="cp_max_results_per_source" id="cp_max_results_per_source" 
                                value="<?php echo esc_attr(get_option('cp_max_results_per_source', 1000)); ?>" 
                                min="100" max="5000" class="small-text">
                            <p class="description">
                                <?php _e('Número máximo de resultados que se obtendrán de cada fuente (TVEC, SECOPI, SECOPII).', 'consulta-procesos'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <div class="cp-cache-actions">
                    <h4><?php _e('Gestión de Caché', 'consulta-procesos'); ?></h4>
                    
                    <button type="button" id="cp-clear-cache" class="button button-secondary">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Limpiar Caché', 'consulta-procesos'); ?>
                    </button>
                    
                    <button type="button" id="cp-cache-stats" class="button button-secondary">
                        <span class="dashicons dashicons-chart-area"></span>
                        <?php _e('Ver Estadísticas', 'consulta-procesos'); ?>
                    </button>
                    
                    <div id="cp-cache-info" style="margin-top: 15px; display: none;">
                        <!-- Las estadísticas se cargarán aquí vía AJAX -->
                    </div>
                </div>
            </div>
            
            <?php submit_button(__('Guardar Configuración', 'consulta-procesos'), 'primary'); ?>
        </form>
    </div>
    
    <!-- Información de Ayuda -->
    <div class="cp-help-sidebar">
        <div class="cp-card">
            <h3><?php _e('Ayuda', 'consulta-procesos'); ?></h3>
            <div class="cp-help-content">
                <h4><?php _e('¿Cómo funciona?', 'consulta-procesos'); ?></h4>
                <p><?php _e('El formulario funciona en tres etapas:', 'consulta-procesos'); ?></p>
                <ol>
                    <li><?php _e('El usuario acepta los términos de uso', 'consulta-procesos'); ?></li>
                    <li><?php _e('Selecciona su perfil (Entidades o Proveedores)', 'consulta-procesos'); ?></li>
                    <li><?php _e('Completa el formulario de búsqueda', 'consulta-procesos'); ?></li>
                </ol>
                
                <h4><?php _e('Métodos de Búsqueda', 'consulta-procesos'); ?></h4>
                <ul>
                    <li><strong><?php _e('Base de Datos:', 'consulta-procesos'); ?></strong> <?php _e('Usa la conexión SQL Server configurada', 'consulta-procesos'); ?></li>
                    <li><strong><?php _e('API:', 'consulta-procesos'); ?></strong> <?php _e('Consulta servicios web externos', 'consulta-procesos'); ?></li>
                </ul>
                
                <h4><?php _e('Configuración de APIs', 'consulta-procesos'); ?></h4>
                <p><?php _e('Para cada sistema (TVEC, SECOPI, SECOPII) puede configurar:', 'consulta-procesos'); ?></p>
                <ul>
                    <li><?php _e('URL específica para consultar proveedores', 'consulta-procesos'); ?></li>
                    <li><?php _e('URL específica para consultar entidades', 'consulta-procesos'); ?></li>
                    <li><?php _e('Campo de fecha para filtros temporales', 'consulta-procesos'); ?></li>
                </ul>
                
                <div class="cp-help-example">
                    <h4><?php _e('Ejemplo de URL de API:', 'consulta-procesos'); ?></h4>
                    <code>https://www.datos.gov.co/resource/jbjy-vk9h.json?documento_proveedor=</code>
                    <p class="description"><?php _e('El número de documento del formulario se agregará automáticamente al final.', 'consulta-procesos'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.cp-settings-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.cp-search-config-section {
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 20px;
    margin-bottom: 20px;
    background: #fafafa;
}

.cp-search-config-section h3 {
    margin-top: 0;
    color: #0073aa;
    border-bottom: 1px solid #e0e0e0;
    padding-bottom: 10px;
}

.cp-api-config {
    margin-top: 20px;
    padding: 15px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    border-left: 4px solid #0073aa;
}

.cp-api-config h4 {
    margin-top: 0;
    color: #0073aa;
}

.cp-api-config .form-table th {
    width: 200px;
}

.cp-api-config input[type="url"],
.cp-api-config input[type="text"] {
    width: 100%;
}

.cp-shortcode-examples {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin: 15px 0;
}

.cp-shortcode-examples h4 {
    margin-top: 15px;
    margin-bottom: 5px;
}

.cp-shortcode-examples h4:first-child {
    margin-top: 0;
}

.cp-shortcode-examples code {
    background: #fff;
    padding: 8px;
    border-radius: 3px;
    display: block;
    margin: 5px 0 15px 0;
    border: 1px solid #ddd;
}

.cp-status-list {
    list-style: none;
    padding: 0;
}

.cp-status-list li {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 4px;
}

.cp-status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 10px;
    flex-shrink: 0;
}

.cp-status-indicator.active {
    background-color: #00a32a;
}

.cp-status-indicator.inactive {
    background-color: #d63638;
}

.cp-help-sidebar {
    grid-column: 2;
}

.cp-help-example {
    background: #f0f6fc;
    padding: 10px;
    border-radius: 4px;
    margin-top: 15px;
}

.cp-help-example code {
    background: #fff;
    padding: 5px 8px;
    border-radius: 3px;
    border: 1px solid #ddd;
    display: block;
    margin: 5px 0;
    word-break: break-all;
}

.cp-cache-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}

.cp-cache-actions button {
    margin-right: 10px;
}

@media (max-width: 1200px) {
    .cp-settings-container {
        grid-template-columns: 1fr;
    }
    
    .cp-help-sidebar {
        grid-column: 1;
    }
}

/* Animación para mostrar/ocultar configuración de API */
.cp-api-config {
    transition: all 0.3s ease;
}

.cp-method-radio {
    margin-bottom: 5px;
}

/* Estilos para campos requeridos */
.cp-api-config input:required {
    border-left: 3px solid #d63638;
}

.cp-api-config input:required:valid {
    border-left: 3px solid #00a32a;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Función para mostrar/ocultar configuración de API
    function toggleApiConfig() {
        $('.cp-method-radio').each(function() {
            var target = $(this).data('target');
            var method = $(this).val();
            var apiConfig = $('#cp-' + target + '-api-config');
            
            if ($(this).is(':checked') && method === 'api') {
                apiConfig.slideDown(300);
            } else if ($(this).is(':checked') && method === 'database') {
                apiConfig.slideUp(300);
            }
        });
    }
    
    // Inicializar estado al cargar la página
    toggleApiConfig();
    
    // Escuchar cambios en los radio buttons
    $('.cp-method-radio').on('change', toggleApiConfig);
    
    // Validación de URLs
    $('input[type="url"]').on('blur', function() {
        var url = $(this).val();
        if (url && !url.match(/^https?:\/\/.+/)) {
            $(this).css('border-color', '#d63638');
            $(this).next('.description').after('<span class="url-error" style="color: #d63638;">URL debe comenzar con http:// o https://</span>');
        } else {
            $(this).css('border-color', '');
            $(this).siblings('.url-error').remove();
        }
    });
    
    // Limpiar caché
    $('#cp-clear-cache').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Limpiando...');
        
        $.post(ajaxurl, {
            action: 'cp_clear_cache',
            nonce: '<?php echo wp_create_nonce("cp_admin_nonce"); ?>'
        }, function(response) {
            button.prop('disabled', false).html(originalText);
            
            if (response.success) {
                alert('Caché limpiado exitosamente');
            } else {
                alert('Error al limpiar caché: ' + (response.data ? response.data.message : 'Error desconocido'));
            }
        }).fail(function() {
            button.prop('disabled', false).html(originalText);
            alert('Error de comunicación');
        });
    });
    
    // Ver estadísticas de caché
    $('#cp-cache-stats').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        var infoDiv = $('#cp-cache-info');
        
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Cargando...');
        
        $.post(ajaxurl, {
            action: 'cp_get_cache_stats',
            nonce: '<?php echo wp_create_nonce("cp_admin_nonce"); ?>'
        }, function(response) {
            button.prop('disabled', false).html(originalText);
            
            if (response.success) {
                var stats = response.data;
                var html = '<div class="cp-cache-stats">';
                html += '<h4>Estadísticas de Caché</h4>';
                html += '<p><strong>Consultas en caché:</strong> ' + stats.cached_queries + '</p>';
                html += '<p><strong>Caché habilitado:</strong> ' + (stats.cache_enabled ? 'Sí' : 'No') + '</p>';
                html += '<p><strong>Duración:</strong> ' + stats.cache_duration + ' segundos</p>';
                html += '</div>';
                
                infoDiv.html(html).slideDown();
            } else {
                alert('Error al obtener estadísticas: ' + (response.data ? response.data.message : 'Error desconocido'));
            }
        }).fail(function() {
            button.prop('disabled', false).html(originalText);
            alert('Error de comunicación');
        });
    });
});
</script>