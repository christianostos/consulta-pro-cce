<?php
/**
 * Clase para manejar la funcionalidad del frontend del plugin
 * 
 * Archivo: includes/class-cp-frontend.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_Frontend {
    
    private static $instance = null;
    private $db;
    
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
        $this->db = CP_Database::get_instance();
        $this->init_hooks();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Registrar shortcode
        add_shortcode('consulta_procesos', array($this, 'render_shortcode'));
        
        // Scripts y estilos del frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        
        // Hooks AJAX para frontend
        add_action('wp_ajax_cp_frontend_search', array($this, 'ajax_frontend_search'));
        add_action('wp_ajax_nopriv_cp_frontend_search', array($this, 'ajax_frontend_search'));
        
        // Hook para procesar formulario
        add_action('wp_ajax_cp_process_search_form', array($this, 'ajax_process_search_form'));
        add_action('wp_ajax_nopriv_cp_process_search_form', array($this, 'ajax_process_search_form'));
    }
    
    /**
     * Cargar assets del frontend
     */
    public function enqueue_frontend_assets() {
        // Solo cargar en páginas que contengan el shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'consulta_procesos')) {
            
            wp_enqueue_style(
                'cp-frontend-css', 
                CP_PLUGIN_URL . 'assets/css/frontend.css', 
                array(), 
                CP_PLUGIN_VERSION
            );
            
            wp_enqueue_script(
                'cp-frontend-js', 
                CP_PLUGIN_URL . 'assets/js/frontend.js', 
                array('jquery'), 
                CP_PLUGIN_VERSION, 
                true
            );
            
            // Localizar script
            wp_localize_script('cp-frontend-js', 'cp_frontend_ajax', array(
                'url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cp_frontend_nonce'),
                'messages' => array(
                    'loading' => __('Buscando...', 'consulta-procesos'),
                    'error' => __('Error en la búsqueda', 'consulta-procesos'),
                    'no_results' => __('No se encontraron resultados', 'consulta-procesos'),
                    'accept_terms' => __('Debe aceptar los términos de uso', 'consulta-procesos'),
                    'select_profile' => __('Debe seleccionar un perfil', 'consulta-procesos'),
                    'fill_dates' => __('Debe completar las fechas', 'consulta-procesos')
                )
            ));
        }
    }
    
    /**
     * Renderizar shortcode principal
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Consulta de Procesos', 'consulta-procesos'),
            'show_title' => 'true'
        ), $atts);
        
        ob_start();
        ?>
        <div class="cp-frontend-container" id="cp-frontend-form">
            <?php if ($atts['show_title'] === 'true'): ?>
                <h2 class="cp-frontend-title"><?php echo esc_html($atts['title']); ?></h2>
            <?php endif; ?>
            
            <!-- Progress Bar -->
            <div class="cp-progress-bar">
                <div class="cp-progress-step active" data-step="1">
                    <span class="cp-step-number">1</span>
                    <span class="cp-step-label"><?php _e('Términos', 'consulta-procesos'); ?></span>
                </div>
                <div class="cp-progress-step" data-step="2">
                    <span class="cp-step-number">2</span>
                    <span class="cp-step-label"><?php _e('Perfil', 'consulta-procesos'); ?></span>
                </div>
                <div class="cp-progress-step" data-step="3">
                    <span class="cp-step-number">3</span>
                    <span class="cp-step-label"><?php _e('Búsqueda', 'consulta-procesos'); ?></span>
                </div>
            </div>
            
            <!-- Etapa 1: Términos de Uso -->
            <div class="cp-form-step cp-step-1 active">
                <?php $this->render_terms_step(); ?>
            </div>
            
            <!-- Etapa 2: Selección de Perfil -->
            <div class="cp-form-step cp-step-2">
                <?php $this->render_profile_step(); ?>
            </div>
            
            <!-- Etapa 3: Formulario de Búsqueda -->
            <div class="cp-form-step cp-step-3">
                <?php $this->render_search_step(); ?>
            </div>
            
            <!-- Resultados -->
            <div class="cp-results-container" style="display: none;">
                <h3><?php _e('Resultados de la Búsqueda', 'consulta-procesos'); ?></h3>
                <div class="cp-results-content"></div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Renderizar etapa 1: Términos de uso
     */
    private function render_terms_step() {
        $terms_content = get_option('cp_terms_content', $this->get_default_terms());
        ?>
        <div class="cp-terms-container">
            <h3><?php _e('Términos de Uso', 'consulta-procesos'); ?></h3>
            
            <div class="cp-terms-content">
                <?php echo wp_kses_post($terms_content); ?>
            </div>
            
            <div class="cp-terms-acceptance">
                <label class="cp-checkbox-label">
                    <input type="checkbox" id="cp-accept-terms" name="accept_terms" value="1">
                    <span class="cp-checkbox-text">
                        <?php _e('He leído y acepto los términos de uso', 'consulta-procesos'); ?>
                    </span>
                </label>
            </div>
            
            <div class="cp-form-actions">
                <button type="button" class="cp-btn cp-btn-primary" id="cp-continue-to-profile">
                    <?php _e('Continuar', 'consulta-procesos'); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderizar etapa 2: Selección de perfil
     */
    private function render_profile_step() {
        ?>
        <div class="cp-profile-container">
            <h3><?php _e('Seleccione su Perfil', 'consulta-procesos'); ?></h3>
            
            <div class="cp-profile-options">
                <div class="cp-profile-option">
                    <label class="cp-radio-card">
                        <input type="radio" name="profile_type" value="entidades">
                        <div class="cp-radio-content">
                            <div class="cp-radio-icon">
                                <span class="dashicons dashicons-building"></span>
                            </div>
                            <h4><?php _e('Entidades', 'consulta-procesos'); ?></h4>
                            <p><?php _e('Consultas para entidades públicas y organizaciones', 'consulta-procesos'); ?></p>
                        </div>
                    </label>
                </div>
                
                <div class="cp-profile-option">
                    <label class="cp-radio-card">
                        <input type="radio" name="profile_type" value="proveedores">
                        <div class="cp-radio-content">
                            <div class="cp-radio-icon">
                                <span class="dashicons dashicons-businessman"></span>
                            </div>
                            <h4><?php _e('Proveedores', 'consulta-procesos'); ?></h4>
                            <p><?php _e('Consultas para proveedores y contratistas', 'consulta-procesos'); ?></p>
                        </div>
                    </label>
                </div>
            </div>
            
            <div class="cp-form-actions">
                <button type="button" class="cp-btn cp-btn-secondary" id="cp-back-to-terms">
                    <?php _e('Atrás', 'consulta-procesos'); ?>
                </button>
                <button type="button" class="cp-btn cp-btn-primary" id="cp-continue-to-search">
                    <?php _e('Continuar', 'consulta-procesos'); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderizar etapa 3: Formulario de búsqueda
     */
    private function render_search_step() {
        ?>
        <div class="cp-search-container">
            <h3><?php _e('Formulario de Búsqueda', 'consulta-procesos'); ?></h3>
            
            <form id="cp-search-form" class="cp-search-form">
                <div class="cp-form-grid">
                    <div class="cp-form-group">
                        <label for="cp-fecha-inicio">
                            <?php _e('Fecha de Inicio', 'consulta-procesos'); ?> <span class="required">*</span>
                        </label>
                        <input type="date" id="cp-fecha-inicio" name="fecha_inicio" required>
                    </div>
                    
                    <div class="cp-form-group">
                        <label for="cp-fecha-fin">
                            <?php _e('Fecha de Fin', 'consulta-procesos'); ?> <span class="required">*</span>
                        </label>
                        <input type="date" id="cp-fecha-fin" name="fecha_fin" required>
                    </div>
                    
                    <div class="cp-form-group cp-form-group-full">
                        <label for="cp-numero-documento">
                            <?php _e('Número de Documento', 'consulta-procesos'); ?> <span class="required">*</span>
                        </label>
                        <input type="number" id="cp-numero-documento" name="numero_documento" placeholder="<?php _e('Ingrese el número', 'consulta-procesos'); ?>" required>
                    </div>
                </div>
                
                <!-- Información del perfil seleccionado -->
                <div class="cp-profile-info">
                    <p><strong><?php _e('Perfil seleccionado:', 'consulta-procesos'); ?></strong> 
                    <span id="cp-selected-profile"></span></p>
                </div>
                
                <div class="cp-form-actions">
                    <button type="button" class="cp-btn cp-btn-secondary" id="cp-back-to-profile">
                        <?php _e('Atrás', 'consulta-procesos'); ?>
                    </button>
                    <button type="submit" class="cp-btn cp-btn-primary" id="cp-search-submit">
                        <span class="cp-btn-text"><?php _e('Buscar', 'consulta-procesos'); ?></span>
                        <span class="cp-btn-spinner" style="display: none;">
                            <span class="dashicons dashicons-update-alt"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Obtener términos por defecto
     */
    private function get_default_terms() {
        return '<p><strong>Términos y Condiciones de Uso</strong></p>
        <p>Al utilizar este sistema de consulta de procesos, usted acepta los siguientes términos:</p>
        <ul>
            <li>La información proporcionada será utilizada únicamente para consultas oficiales</li>
            <li>No está permitido el uso indebido de los datos obtenidos</li>
            <li>El sistema está sujeto a disponibilidad y mantenimiento</li>
            <li>Los resultados mostrados son de carácter informativo</li>
        </ul>
        <p>Para más información, consulte nuestra <a href="#" target="_blank">política de privacidad</a>.</p>';
    }
    
    /**
     * AJAX: Procesar búsqueda del formulario
     */
    public function ajax_process_search_form() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cp_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Token de seguridad inválido'));
        }
        
        // Obtener y sanitizar datos
        $profile_type = sanitize_text_field($_POST['profile_type'] ?? '');
        $fecha_inicio = sanitize_text_field($_POST['fecha_inicio'] ?? '');
        $fecha_fin = sanitize_text_field($_POST['fecha_fin'] ?? '');
        $numero_documento = sanitize_text_field($_POST['numero_documento'] ?? '');
        
        // Validar datos
        if (empty($profile_type) || empty($fecha_inicio) || empty($fecha_fin) || empty($numero_documento)) {
            wp_send_json_error(array('message' => 'Faltan datos requeridos'));
        }
        
        // Validar fechas
        if (!$this->validate_date_range($fecha_inicio, $fecha_fin)) {
            wp_send_json_error(array('message' => 'Rango de fechas inválido'));
        }
        
        try {
            // Realizar búsquedas según configuración
            $results = $this->perform_searches($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
            
            if (empty($results)) {
                wp_send_json_success(array(
                    'has_results' => false,
                    'message' => __('No se encontraron resultados para los criterios especificados', 'consulta-procesos')
                ));
            } else {
                wp_send_json_success(array(
                    'has_results' => true,
                    'results' => $results,
                    'total_records' => $this->count_total_records($results)
                ));
            }
            
        } catch (Exception $e) {
            error_log('CP Frontend Search Error: ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error interno del servidor'));
        }
    }
    
    /**
     * Obtener configuración de búsquedas
     */
    private function get_search_configuration() {
        return array(
            'tvec' => array(
                'active' => get_option('cp_tvec_active', true),
                'method' => get_option('cp_tvec_method', 'database')
            ),
            'secopi' => array(
                'active' => get_option('cp_secopi_active', true),
                'method' => get_option('cp_secopi_method', 'database')
            ),
            'secopii' => array(
                'active' => get_option('cp_secopii_active', true),
                'method' => get_option('cp_secopii_method', 'database')
            )
        );
    }
    
    /**
     * Buscar en tabla TVEC
     */
    private function search_tvec($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $method) {
        if ($method === 'api') {
            return $this->search_tvec_api($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
        } else {
            return $this->search_tvec_database($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
        }
    }
    
    /**
     * Buscar en tabla SECOPI
     */
    private function search_secopi($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $method) {
        if ($method === 'api') {
            return $this->search_secopi_api($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
        } else {
            return $this->search_secopi_database($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
        }
    }
    
    /**
     * Buscar en tabla SECOPII
     */
    private function search_secopii($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $method) {
        if ($method === 'api') {
            return $this->search_secopii_api($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
        } else {
            return $this->search_secopii_database($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
        }
    }
    
    /**
     * Buscar TVEC en base de datos (placeholder)
     */
    private function search_tvec_database($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        if (!$this->db) {
            return array();
        }
        
        $connection_result = $this->db->connect();
        if (!$connection_result['success']) {
            error_log('CP Frontend: Error de conexión TVEC - ' . $connection_result['error']);
            return array();
        }
        
        $connection = $connection_result['connection'];
        $method = $connection_result['method'];
        
        try {
            if ($profile_type === 'entidades') {
                return $this->query_tvec_entidades($connection, $method, $fecha_inicio, $fecha_fin, $numero_documento);
            } else {
                return $this->query_tvec_proveedores($connection, $method, $fecha_inicio, $fecha_fin, $numero_documento);
            }
        } catch (Exception $e) {
            error_log('CP Frontend: Error en consulta TVEC - ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Buscar SECOPI en base de datos (placeholder)
     */
    private function search_secopi_database($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        if (!$this->db) {
            return array();
        }
        
        $connection_result = $this->db->connect();
        if (!$connection_result['success']) {
            error_log('CP Frontend: Error de conexión SECOPI - ' . $connection_result['error']);
            return array();
        }
        
        $connection = $connection_result['connection'];
        $method = $connection_result['method'];
        
        try {
            if ($profile_type === 'entidades') {
                return $this->query_secopi_entidades($connection, $method, $fecha_inicio, $fecha_fin, $numero_documento);
            } else {
                return $this->query_secopi_proveedores($connection, $method, $fecha_inicio, $fecha_fin, $numero_documento);
            }
        } catch (Exception $e) {
            error_log('CP Frontend: Error en consulta SECOPI - ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Buscar SECOPII en base de datos (placeholder)
     */
    private function search_secopii_database($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
    if (!$this->db) {
        return array();
    }
    
    $connection_result = $this->db->connect();
        if (!$connection_result['success']) {
            error_log('CP Frontend: Error de conexión SECOPII - ' . $connection_result['error']);
            return array();
        }
        
        $connection = $connection_result['connection'];
        $method = $connection_result['method'];
        
        try {
            if ($profile_type === 'entidades') {
                return $this->query_secopii_entidades($connection, $method, $fecha_inicio, $fecha_fin, $numero_documento);
            } else {
                return $this->query_secopii_proveedores($connection, $method, $fecha_inicio, $fecha_fin, $numero_documento);
            }
        } catch (Exception $e) {
            error_log('CP Frontend: Error en consulta SECOPII - ' . $e->getMessage());
            return array();
        }
    }


    /**
     * Consulta TVEC por entidades
     */
    private function query_tvec_entidades($connection, $method, $fecha_inicio, $fecha_fin, $nit_entidad) {
        // Usar stored procedure si está disponible
        if ($this->stored_procedures_available()) {
            return $this->query_tvec_entidades_sp($connection, $method, $fecha_inicio, $fecha_fin, $nit_entidad);
        }
        
        // Consulta SQL optimizada basada en el código Java
        $sql = "
            SELECT 
                ord.ID as ID_Contrato,
                ord.ID_Entidad,
                ent.NIT as NIT_Entidad,
                ent.Entidad,
                ord.Fecha as Fecha_cargue,
                'Compra por TVEC' as Modalidad,
                ord.Agregacion as Tipo_Contrato,
                '0' as UNSPSC_Clase,
                ord.Proveedor as Prov_nombre,
                ord.ID_Proveedor as Prov_documento,
                pro.NIT as NIT_Proveedor,
                ord.Items as Detalle,
                ord.Estado,
                ord.Total as Valor_Total,
                CONCAT('https://www.colombiacompra.gov.co/tienda-virtual-del-estado-colombiano/ordenes-compra/', ord.ID) as Link,
                ord.Fecha as Fecha_firma,
                'TVEC' as Fuente
            FROM TVEC.V_Ordenes ord 
            LEFT JOIN TVEC.Entidades ent ON ord.ID_Entidad = ent.ID 
            LEFT JOIN TVEC.Proveedores pro ON ord.ID_Proveedor = pro.ID
            WHERE ord.Fecha BETWEEN CONVERT(datetime, ? + ' 00:00:00') AND CONVERT(datetime, ? + ' 23:59:59')
            AND REPLACE(REPLACE(REPLACE(ent.NIT, '.', ''), ',', ''), ' ', '') LIKE ?
            ORDER BY ord.Fecha DESC
        ";
        
        $nit_param = '%' . str_replace(['.', ',', ' '], '', $nit_entidad) . '%';
        $params = array($fecha_inicio, $fecha_fin, $nit_param);
        
        return $this->execute_query_with_error_handling($connection, $method, $sql, $params, 'TVEC Entidades');
    }


    /**
     * Consulta TVEC por proveedores
     */
    private function query_tvec_proveedores($connection, $method, $fecha_inicio, $fecha_fin, $doc_proveedor) {
        // Usar stored procedure si está disponible
        if ($this->stored_procedures_available()) {
            return $this->query_tvec_proveedores_sp($connection, $method, $fecha_inicio, $fecha_fin, $doc_proveedor);
        }
        
        // Consulta SQL optimizada
        $sql = "
            SELECT 
                ord.ID as ID_Contrato,
                ord.ID_Entidad,
                ent.NIT as NIT_Entidad,
                ent.Entidad,
                ord.Fecha as Fecha_cargue,
                'Compra por TVEC' as Modalidad,
                ord.Agregacion as Tipo_Contrato,
                '0' as UNSPSC_Clase,
                ord.Proveedor as Prov_nombre,
                ord.ID_Proveedor as Prov_documento,
                pro.NIT as NIT_Proveedor,
                ord.Items as Detalle,
                ord.Estado,
                ord.Total as Valor_Total,
                CONCAT('https://www.colombiacompra.gov.co/tienda-virtual-del-estado-colombiano/ordenes-compra/', ord.ID) as Link,
                ord.Fecha as Fecha_firma,
                'TVEC' as Fuente
            FROM TVEC.V_Ordenes ord 
            LEFT JOIN TVEC.Entidades ent ON ord.ID_Entidad = ent.ID 
            LEFT JOIN TVEC.Proveedores pro ON ord.ID_Proveedor = pro.ID
            WHERE ord.Fecha BETWEEN CONVERT(datetime, ? + ' 00:00:00') AND CONVERT(datetime, ? + ' 23:59:59')
            AND REPLACE(REPLACE(REPLACE(pro.NIT, '.', ''), ',', ''), ' ', '') LIKE ?
            ORDER BY ord.Fecha DESC
        ";
        
        $doc_param = '%' . str_replace(['.', ',', ' '], '', $doc_proveedor) . '%';
        $params = array($fecha_inicio, $fecha_fin, $doc_param);
        
        return $this->execute_query_with_error_handling($connection, $method, $sql, $params, 'TVEC Proveedores');
    }

    /**
     * Consulta SECOPI por entidades
     */
    private function query_secopi_entidades($connection, $method, $fecha_inicio, $fecha_fin, $nit_entidad) {
        // Usar stored procedure si está disponible
        if ($this->stored_procedures_available()) {
            return $this->query_secopi_entidades_sp($connection, $method, $fecha_inicio, $fecha_fin, $nit_entidad);
        }
        
        // Consulta SQL completa optimizada basada en el código Java
        $sql = "
            SELECT 
                CONCAT(PRO.NUM_CONSTANCIA, '-', ADJ.ID_ADJUDICACION) AS ID_Contrato,
                YEAR(PRO.FECHA_CARGUE) AS Anno_Cargue,
                MONTH(PRO.FECHA_CARGUE) AS Mes_Cargue,
                PRO.FECHA_CARGUE AS Fecha_cargue,
                ADJ.FECHA_FIRMA_CONTRATO AS Fecha_Firma,
                ADJ.FECHA_INICIO_EJECUCION AS Fecha_Inicio_Contrato,
                CASE ADJ.rango_plazo_ejecucion 
                    WHEN 'M' THEN CONVERT(VARCHAR(20), DATEADD(MM, ISNULL(ADJ.PLAZO_EJECUCION,0) + ISNULL(ADD_MESES, 0) + ISNULL(ADD_DIAS, 0), ADJ.fecha_inicio_ejecucion), 20)
                    WHEN 'D' THEN CONVERT(VARCHAR(20), DATEADD(DD, ISNULL(ADD_MESES, 0) + ISNULL(ADJ.plazo_ejecucion, 0) + ISNULL(ADD_DIAS, 0), ADJ.fecha_inicio_ejecucion), 20)
                    ELSE NULL 
                END AS Fecha_Fin_Contrato,
                TPORN.DESC_NIVEL AS Orden_Entidad,
                GEO5.DESCRIPCION AS Departamento_Entidad,
                GEO4.DESCRIPCION AS Municipio_Entidad,
                ENTI.NIT_ENTI AS NIT_Entidad,
                ENTI.NOMB_ENTI AS Nombre_Entidad,
                TPRO.NOMBRE AS Modalidad_Contratacion,
                TCONT.DESCRIPCION AS Tipo_Contrato,
                CD.DESCRIPCION AS Justificacion_modalidad,
                CLASE.COD_CLASE AS ID_Clase,
                PRO.NUM_CONSTANCIA AS Numero_Constancia,
                PRO.NUMERO_PROCESO AS Numero_Proceso,
                ADJ.ID_ADJUDICACION AS ID_Adjudicacion,
                ADJ.NUMERO_CONTRATO AS Numero_Contrato,
                ADJ.OBJETO_CONTRATO AS Objeto_contractual,
                ISNULL(ADJ.valor_contrato, 0) + ISNULL(ADD_VAL, 0) AS Valor_con_adiciones,
                ADJ.RAZON_SOCIAL AS Nom_Raz_Social_Contratista,
                TDOC.DESCRIPCION AS Tipo_Identifi_Contratista,
                ADJ.NUMERO_DOC AS Identificacion_Contratista,
                ESTPRO.DESCRIPCION AS Estado_Proceso,
                TIPODES.ORIG_REC_NOMBRE AS Origen_Recursos,
                '' as Destino_Gasto,
                'https://www.contratos.gov.co/consultas/detalleProceso.do?numConstancia=' + PRO.num_constancia AS Link,
                ENTI.CODI_ENTI AS ID_Entidad,
                'SECOP_I' as Fuente
            FROM [SECOPI].[T_PTC_Adjudicaciones] ADJ 
            LEFT JOIN [SECOPI].[T_PTC_Procesos] PRO ON ADJ.id_proceso = PRO.num_constancia
            LEFT JOIN [SECOPI].TB_UsuarioLDAP USU ON PRO.USUARIO = USU.USUARIO
            LEFT JOIN [SECOPI].[TPOR_Enti] ENTI ON USU.IDENTIDAD = ENTI.CODI_ENTI
            LEFT JOIN SECOPI.TB_ESTADO_PROCESO ESTPRO ON PRO.ESTADO_PROCESO = ESTPRO.OID
            LEFT JOIN SECOPI.TB_Tipo_Proceso TPRO ON PRO.ID_TIPO_PROCESO = TPRO.OID
            LEFT JOIN SECOPI.TPOR_Nivel TPORN ON ENTI.NIVEL = TPORN.ID_NIVEL
            LEFT JOIN SECOPI.T_PTC_Causales_Directa CD ON PRO.ID_CAUSAL_DIRECTA = CD.IDENTIFICADOR
            LEFT JOIN SECOPI.T_PTC_Tipos_Contratos TCONT ON PRO.ID_TIPO_CONTRATO = TCONT.IDENTIFICADOR
            LEFT JOIN SECOPI.UNSPSC_Clases CLASE ON PRO.UNSPSC_CLASE = CLASE.COD_CLASE
            LEFT JOIN SECOPI.T_PTC_Tipos_Documentos TDOC ON ADJ.TIPO_DOC_CONTRATISTA = TDOC.IDENTIFICACION
            LEFT JOIN (
                SELECT 
                    SUM(ISNULL(ADI.valor, 0)) AS ADD_VAL,
                    SUM(CASE WHEN ADI.rango IN('1','D') AND ADI.tipo_adicion <> 2 THEN ADI.tiempo ELSE 0 END) AS ADD_DIAS,
                    SUM(CASE WHEN ADI.rango IN('2','M') AND ADI.tipo_adicion <> 2 THEN ADI.tiempo ELSE 0 END) AS ADD_MESES,
                    ADI.id_adjudicacion
                FROM SECOPI.t_ptc_adiciones ADI 
                GROUP BY ADI.id_adjudicacion
            ) ADDS ON ADJ.id_adjudicacion = ADDS.id_adjudicacion
            LEFT JOIN SECOPI.t_ptc_destino_gasto DESGAS ON ADJ.id_adjudicacion = DESGAS.id_adjudicacion
            LEFT JOIN SECOPI.t_ptc_orig_rec TIPODES ON TIPODES.orig_rec_oid = DESGAS.orig_rec_oid
            LEFT JOIN SECOPI.T_PTC_Ubicaciones_Geo GEO4 ON ENTI.UBICACION_GEO = GEO4.IDENTIFICADOR
            LEFT JOIN SECOPI.T_PTC_Ubicaciones_Geo GEO5 ON GEO4.IDENTIFICADOR_PADRE = GEO5.IDENTIFICADOR
            WHERE PRO.FECHA_CARGUE BETWEEN CONVERT(datetime, ? + ' 00:00:00') AND CONVERT(datetime, ? + ' 23:59:59')
            AND REPLACE(REPLACE(REPLACE(ENTI.NIT_ENTI, '.', ''), ',', ''), ' ', '') LIKE ?
            AND ENTI.CODI_ENTI NOT IN (199999999, 201101072, 295200018, 201101065)
            ORDER BY PRO.FECHA_CARGUE DESC
        ";
        
        $nit_param = '%' . str_replace(['.', ',', ' '], '', $nit_entidad) . '%';
        $params = array($fecha_inicio, $fecha_fin, $nit_param);
        
        return $this->execute_query_with_error_handling($connection, $method, $sql, $params, 'SECOPI Entidades');
    }

    /**
     * Consulta SECOPI por proveedores
     */
    private function query_secopi_proveedores($connection, $method, $fecha_inicio, $fecha_fin, $doc_proveedor) {
        // Usar stored procedure si está disponible
        if ($this->stored_procedures_available()) {
            return $this->query_secopi_proveedores_sp($connection, $method, $fecha_inicio, $fecha_fin, $doc_proveedor);
        }
        
        // Misma consulta base pero con filtro por proveedor
        $sql = "
            SELECT 
                CONCAT(PRO.NUM_CONSTANCIA, '-', ADJ.ID_ADJUDICACION) AS ID_Contrato,
                YEAR(PRO.FECHA_CARGUE) AS Anno_Cargue,
                MONTH(PRO.FECHA_CARGUE) AS Mes_Cargue,
                PRO.FECHA_CARGUE AS Fecha_cargue,
                ADJ.FECHA_FIRMA_CONTRATO AS Fecha_Firma,
                ADJ.FECHA_INICIO_EJECUCION AS Fecha_Inicio_Contrato,
                CASE ADJ.rango_plazo_ejecucion 
                    WHEN 'M' THEN CONVERT(VARCHAR(20), DATEADD(MM, ISNULL(ADJ.PLAZO_EJECUCION,0) + ISNULL(ADD_MESES, 0) + ISNULL(ADD_DIAS, 0), ADJ.fecha_inicio_ejecucion), 20)
                    WHEN 'D' THEN CONVERT(VARCHAR(20), DATEADD(DD, ISNULL(ADD_MESES, 0) + ISNULL(ADJ.plazo_ejecucion, 0) + ISNULL(ADD_DIAS, 0), ADJ.fecha_inicio_ejecucion), 20)
                    ELSE NULL 
                END AS Fecha_Fin_Contrato,
                TPORN.DESC_NIVEL AS Orden_Entidad,
                GEO5.DESCRIPCION AS Departamento_Entidad,
                GEO4.DESCRIPCION AS Municipio_Entidad,
                ENTI.NIT_ENTI AS NIT_Entidad,
                ENTI.NOMB_ENTI AS Nombre_Entidad,
                TPRO.NOMBRE AS Modalidad_Contratacion,
                TCONT.DESCRIPCION AS Tipo_Contrato,
                CD.DESCRIPCION AS Justificacion_modalidad,
                CLASE.COD_CLASE AS ID_Clase,
                PRO.NUM_CONSTANCIA AS Numero_Constancia,
                PRO.NUMERO_PROCESO AS Numero_Proceso,
                ADJ.ID_ADJUDICACION AS ID_Adjudicacion,
                ADJ.NUMERO_CONTRATO AS Numero_Contrato,
                ADJ.OBJETO_CONTRATO AS Objeto_contractual,
                ISNULL(ADJ.valor_contrato, 0) + ISNULL(ADD_VAL, 0) AS Valor_con_adiciones,
                ADJ.RAZON_SOCIAL AS Nom_Raz_Social_Contratista,
                TDOC.DESCRIPCION AS Tipo_Identifi_Contratista,
                ADJ.NUMERO_DOC AS Identificacion_Contratista,
                ESTPRO.DESCRIPCION AS Estado_Proceso,
                TIPODES.ORIG_REC_NOMBRE AS Origen_Recursos,
                '' as Destino_Gasto,
                'https://www.contratos.gov.co/consultas/detalleProceso.do?numConstancia=' + PRO.num_constancia AS Link,
                ENTI.CODI_ENTI AS ID_Entidad,
                'SECOP_I' as Fuente
            FROM [SECOPI].[T_PTC_Adjudicaciones] ADJ 
            LEFT JOIN [SECOPI].[T_PTC_Procesos] PRO ON ADJ.id_proceso = PRO.num_constancia
            LEFT JOIN [SECOPI].TB_UsuarioLDAP USU ON PRO.USUARIO = USU.USUARIO
            LEFT JOIN [SECOPI].[TPOR_Enti] ENTI ON USU.IDENTIDAD = ENTI.CODI_ENTI
            LEFT JOIN SECOPI.TB_ESTADO_PROCESO ESTPRO ON PRO.ESTADO_PROCESO = ESTPRO.OID
            LEFT JOIN SECOPI.TB_Tipo_Proceso TPRO ON PRO.ID_TIPO_PROCESO = TPRO.OID
            LEFT JOIN SECOPI.TPOR_Nivel TPORN ON ENTI.NIVEL = TPORN.ID_NIVEL
            LEFT JOIN SECOPI.T_PTC_Causales_Directa CD ON PRO.ID_CAUSAL_DIRECTA = CD.IDENTIFICADOR
            LEFT JOIN SECOPI.T_PTC_Tipos_Contratos TCONT ON PRO.ID_TIPO_CONTRATO = TCONT.IDENTIFICADOR
            LEFT JOIN SECOPI.UNSPSC_Clases CLASE ON PRO.UNSPSC_CLASE = CLASE.COD_CLASE
            LEFT JOIN SECOPI.T_PTC_Tipos_Documentos TDOC ON ADJ.TIPO_DOC_CONTRATISTA = TDOC.IDENTIFICACION
            LEFT JOIN (
                SELECT 
                    SUM(ISNULL(ADI.valor, 0)) AS ADD_VAL,
                    SUM(CASE WHEN ADI.rango IN('1','D') AND ADI.tipo_adicion <> 2 THEN ADI.tiempo ELSE 0 END) AS ADD_DIAS,
                    SUM(CASE WHEN ADI.rango IN('2','M') AND ADI.tipo_adicion <> 2 THEN ADI.tiempo ELSE 0 END) AS ADD_MESES,
                    ADI.id_adjudicacion
                FROM SECOPI.t_ptc_adiciones ADI 
                GROUP BY ADI.id_adjudicacion
            ) ADDS ON ADJ.id_adjudicacion = ADDS.id_adjudicacion
            LEFT JOIN SECOPI.t_ptc_destino_gasto DESGAS ON ADJ.id_adjudicacion = DESGAS.id_adjudicacion
            LEFT JOIN SECOPI.t_ptc_orig_rec TIPODES ON TIPODES.orig_rec_oid = DESGAS.orig_rec_oid
            LEFT JOIN SECOPI.T_PTC_Ubicaciones_Geo GEO4 ON ENTI.UBICACION_GEO = GEO4.IDENTIFICADOR
            LEFT JOIN SECOPI.T_PTC_Ubicaciones_Geo GEO5 ON GEO4.IDENTIFICADOR_PADRE = GEO5.IDENTIFICADOR
            WHERE PRO.FECHA_CARGUE BETWEEN CONVERT(datetime, ? + ' 00:00:00') AND CONVERT(datetime, ? + ' 23:59:59')
            AND REPLACE(REPLACE(REPLACE(ADJ.NUMERO_DOC, '.', ''), ',', ''), ' ', '') LIKE ?
            AND ENTI.CODI_ENTI NOT IN (199999999, 201101072, 295200018, 201101065)
            ORDER BY PRO.FECHA_CARGUE DESC
        ";
        
        $doc_param = '%' . str_replace(['.', ',', ' '], '', $doc_proveedor) . '%';
        $params = array($fecha_inicio, $fecha_fin, $doc_param);
        
        return $this->execute_query_with_error_handling($connection, $method, $sql, $params, 'SECOPI Proveedores');
    }

    /**
     * Consulta SECOPII por entidades
     */
    private function query_secopii_entidades($connection, $method, $fecha_inicio, $fecha_fin, $nit_entidad) {
        // Usar stored procedure si está disponible
        if ($this->stored_procedures_available()) {
            return $this->query_secopii_entidades_sp($connection, $method, $fecha_inicio, $fecha_fin, $nit_entidad);
        }
        
        // Consulta SQL optimizada basada en el código Java
        $sql = "
            SELECT 
                CAST((ContractReference COLLATE SQL_Latin1_General_CP1_CI_AI) as varchar) as ID_Contrato,
                YEAR(CAST(AprovalDate as date)) as Anno_Cargue,
                MONTH(CAST(AprovalDate as date)) as Mes_cargue,
                CAST(AprovalDate as date) as Fecha_cargue,
                CAST(AprovalDate as date) as Fecha_Firma,
                CAST(ContractStartDate as date) as Fecha_Inicio_Contrato,
                CAST(ContractEndDate as date) as Fecha_Fin_Contrato,
                Orden COLLATE SQL_Latin1_General_CP1_CI_AI as Orden_Entidad,
                [Departamento Entidad] COLLATE SQL_Latin1_General_CP1_CI_AI as Departamento_Entidad,
                [Ciudad Entidad] COLLATE SQL_Latin1_General_CP1_CI_AI as Municipio_Entidad,
                [NIT Entidad] as NIT_Entidad,
                [Nombre Entidad] COLLATE SQL_Latin1_General_CP1_CI_AI as Nombre_Entidad,
                ProcedureProfileLabel COLLATE SQL_Latin1_General_CP1_CI_AI as Modalidad_Contratacion,
                TypeContract COLLATE SQL_Latin1_General_CP1_CI_AI as Tipo_Contrato,
                JustificationTypeOfContractCode COLLATE SQL_Latin1_General_CP1_CI_AI as Justificacion_modalidad,
                CAST(SUBSTRING((DimMainCategoryCode COLLATE SQL_Latin1_General_CP1_CI_AI), 4, 6) as varchar) as ID_Clase,
                RequestReference COLLATE SQL_Latin1_General_CP1_CI_AI as Numero_Proceso,
                ContractReference COLLATE SQL_Latin1_General_CP1_CI_AI as Numero_Contrato,
                Description COLLATE SQL_Latin1_General_CP1_CI_AI as Objeto_contractual,
                CAST(ContractValue as float) as Valor_con_adiciones,
                [Nombre Proveedor] COLLATE SQL_Latin1_General_CP1_CI_AI as Nom_Raz_Social_Contratista,
                TipoDocProveedor COLLATE SQL_Latin1_General_CP1_CI_AI as Tipo_Identifi_Contratista,
                [Documento Proveedor] COLLATE SQL_Latin1_General_CP1_CI_AI as Identificacion_Contratista,
                ContractState COLLATE SQL_Latin1_General_CP1_CI_AI as Estado_Proceso,
                [Origen de los Recursos] COLLATE SQL_Latin1_General_CP1_CI_AI as Origen_Recursos,
                [Destino Gasto] COLLATE SQL_Latin1_General_CP1_CI_AI as Destino_Gasto,
                URLProceso COLLATE SQL_Latin1_General_CP1_CI_AI as Link,
                CAST(([Código Entidad] COLLATE SQL_Latin1_General_CP1_CI_AI) as varchar) as ID_Entidad,
                'SECOP_II' COLLATE SQL_Latin1_General_CP1_CI_AI as Fuente
            FROM SECOPII.V_HistoricoContratos_Depurado
            WHERE AprovalDate BETWEEN CONVERT(datetime, ? + ' 00:00:00') AND CONVERT(datetime, ? + ' 23:59:59')
            AND REPLACE(REPLACE(REPLACE([NIT Entidad], '.', ''), ',', ''), ' ', '') LIKE ?
            ORDER BY AprovalDate DESC
        ";
        
        $nit_param = '%' . str_replace(['.', ',', ' '], '', $nit_entidad) . '%';
        $params = array($fecha_inicio, $fecha_fin, $nit_param);
        
        return $this->execute_query_with_error_handling($connection, $method, $sql, $params, 'SECOPII Entidades');
    }

    /**
     * Consulta SECOPII por proveedores
     */
    private function query_secopii_proveedores($connection, $method, $fecha_inicio, $fecha_fin, $doc_proveedor) {
        // Usar stored procedure si está disponible
        if ($this->stored_procedures_available()) {
            return $this->query_secopii_proveedores_sp($connection, $method, $fecha_inicio, $fecha_fin, $doc_proveedor);
        }
        
        // Consulta con filtro por proveedor 
        $sql = "
            SELECT 
                CAST((ContractReference COLLATE SQL_Latin1_General_CP1_CI_AI) as varchar) as ID_Contrato,
                YEAR(CAST(AprovalDate as date)) as Anno_Cargue,
                MONTH(CAST(AprovalDate as date)) as Mes_cargue,
                CAST(AprovalDate as date) as Fecha_cargue,
                CAST(AprovalDate as date) as Fecha_Firma,
                CAST(ContractStartDate as date) as Fecha_Inicio_Contrato,
                CAST(ContractEndDate as date) as Fecha_Fin_Contrato,
                Orden COLLATE SQL_Latin1_General_CP1_CI_AI as Orden_Entidad,
                [Departamento Entidad] COLLATE SQL_Latin1_General_CP1_CI_AI as Departamento_Entidad,
                [Ciudad Entidad] COLLATE SQL_Latin1_General_CP1_CI_AI as Municipio_Entidad,
                [NIT Entidad] as NIT_Entidad,
                [Nombre Entidad] COLLATE SQL_Latin1_General_CP1_CI_AI as Nombre_Entidad,
                ProcedureProfileLabel COLLATE SQL_Latin1_General_CP1_CI_AI as Modalidad_Contratacion,
                TypeContract COLLATE SQL_Latin1_General_CP1_CI_AI as Tipo_Contrato,
                JustificationTypeOfContractCode COLLATE SQL_Latin1_General_CP1_CI_AI as Justificacion_modalidad,
                CAST(SUBSTRING((DimMainCategoryCode COLLATE SQL_Latin1_General_CP1_CI_AI), 4, 6) as varchar) as ID_Clase,
                RequestReference COLLATE SQL_Latin1_General_CP1_CI_AI as Numero_Proceso,
                ContractReference COLLATE SQL_Latin1_General_CP1_CI_AI as Numero_Contrato,
                Description COLLATE SQL_Latin1_General_CP1_CI_AI as Objeto_contractual,
                CAST(ContractValue as float) as Valor_con_adiciones,
                [Nombre Proveedor] COLLATE SQL_Latin1_General_CP1_CI_AI as Nom_Raz_Social_Contratista,
                TipoDocProveedor COLLATE SQL_Latin1_General_CP1_CI_AI as Tipo_Identifi_Contratista,
                [Documento Proveedor] COLLATE SQL_Latin1_General_CP1_CI_AI as Identificacion_Contratista,
                ContractState COLLATE SQL_Latin1_General_CP1_CI_AI as Estado_Proceso,
                [Origen de los Recursos] COLLATE SQL_Latin1_General_CP1_CI_AI as Origen_Recursos,
                [Destino Gasto] COLLATE SQL_Latin1_General_CP1_CI_AI as Destino_Gasto,
                URLProceso COLLATE SQL_Latin1_General_CP1_CI_AI as Link,
                CAST(([Código Entidad] COLLATE SQL_Latin1_General_CP1_CI_AI) as varchar) as ID_Entidad,
                'SECOP_II' COLLATE SQL_Latin1_General_CP1_CI_AI as Fuente
            FROM SECOPII.V_HistoricoContratos_Depurado
            WHERE AprovalDate BETWEEN CONVERT(datetime, ? + ' 00:00:00') AND CONVERT(datetime, ? + ' 23:59:59')
            AND REPLACE(REPLACE(REPLACE([Documento Proveedor], '.', ''), ',', ''), ' ', '') LIKE ?
            ORDER BY AprovalDate DESC
        ";
        
        $doc_param = '%' . str_replace(['.', ',', ' '], '', $doc_proveedor) . '%';
        $params = array($fecha_inicio, $fecha_fin, $doc_param);
        
        return $this->execute_query_with_error_handling($connection, $method, $sql, $params, 'SECOPII Proveedores');
    }

    /**
     * Métodos para Stored Procedures (cuando estén disponibles)
     */
    private function query_tvec_entidades_sp($connection, $method, $fecha_inicio, $fecha_fin, $nit_entidad) {
        return $this->execute_stored_procedure($connection, $method, 'IDI.ConsultaContratosPorEntidad_TVEC', 
            array($nit_entidad, $fecha_inicio, $fecha_fin), 'TVEC Entidades SP');
    }

    private function query_tvec_proveedores_sp($connection, $method, $fecha_inicio, $fecha_fin, $doc_proveedor) {
        return $this->execute_stored_procedure($connection, $method, 'IDI.ConsultaContratosPorProveedor_TVEC', 
            array($doc_proveedor, $fecha_inicio, $fecha_fin), 'TVEC Proveedores SP');
    }

    private function query_secopi_entidades_sp($connection, $method, $fecha_inicio, $fecha_fin, $nit_entidad) {
        return $this->execute_stored_procedure($connection, $method, 'IDI.ConsultaContratosPorEntidad_SECOPI', 
            array($nit_entidad, $fecha_inicio, $fecha_fin), 'SECOPI Entidades SP');
    }

    private function query_secopi_proveedores_sp($connection, $method, $fecha_inicio, $fecha_fin, $doc_proveedor) {
        return $this->execute_stored_procedure($connection, $method, 'IDI.ConsultaContratosPorProveedor_SECOPI', 
            array($doc_proveedor, $fecha_inicio, $fecha_fin), 'SECOPI Proveedores SP');
    }

    private function query_secopii_entidades_sp($connection, $method, $fecha_inicio, $fecha_fin, $nit_entidad) {
        return $this->execute_stored_procedure($connection, $method, 'IDI.ConsultaContratosPorEntidad_SECOPII', 
            array($nit_entidad, $fecha_inicio, $fecha_fin), 'SECOPII Entidades SP');
    }

    private function query_secopii_proveedores_sp($connection, $method, $fecha_inicio, $fecha_fin, $doc_proveedor) {
        return $this->execute_stored_procedure($connection, $method, 'IDI.ConsultaContratosPorProveedor_SECOPII', 
            array($doc_proveedor, $fecha_inicio, $fecha_fin), 'SECOPII Proveedores SP');
    }

    /**
     * Ejecutar stored procedure
     */
    private function execute_stored_procedure($connection, $method, $procedure_name, $params, $source_name) {
        try {
            if (strpos($method, 'PDO') !== false) {
                // Para PDO necesitamos construir la llamada manualmente
                $placeholders = str_repeat('?,', count($params) - 1) . '?';
                $sql = "EXEC {$procedure_name} {$placeholders}";
                
                $stmt = $connection->prepare($sql);
                $stmt->execute($params);
                
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                return $results;
            } else {
                // Para SQLSRV usamos la función nativa
                $placeholders = str_repeat('?,', count($params) - 1) . '?';
                $sql = "{ call {$procedure_name}({$placeholders}) }";
                
                $stmt = sqlsrv_query($connection, $sql, $params);
                
                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    $error_msg = 'Error ejecutando SP: ';
                    foreach ($errors as $error) {
                        $error_msg .= $error['message'] . ' ';
                    }
                    throw new Exception($error_msg);
                }
                
                $results = array();
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    // Convertir objetos DateTime a strings
                    foreach ($row as $key => $value) {
                        if ($value instanceof DateTime) {
                            $row[$key] = $value->format('Y-m-d H:i:s');
                        }
                    }
                    $results[] = $row;
                }
                
                sqlsrv_free_stmt($stmt);
                return $results;
            }
        } catch (Exception $e) {
            error_log("CP Frontend: Error en SP {$procedure_name} - " . $e->getMessage());
            return array();
        }
    }

    /**
     * Ejecutar consulta con manejo de errores
     */
    private function execute_query_with_error_handling($connection, $method, $sql, $params, $source_name) {
        try {
            if (strpos($method, 'PDO') !== false) {
                $stmt = $connection->prepare($sql);
                $stmt->execute($params);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Log de consulta exitosa
                error_log("CP Frontend: {$source_name} - " . count($results) . " registros encontrados");
                
                return $results;
            } else {
                $stmt = sqlsrv_query($connection, $sql, $params);
                
                if ($stmt === false) {
                    $errors = sqlsrv_errors();
                    $error_msg = 'Error en consulta: ';
                    foreach ($errors as $error) {
                        $error_msg .= $error['message'] . ' ';
                    }
                    throw new Exception($error_msg);
                }
                
                $results = array();
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    // Convertir objetos DateTime a strings
                    foreach ($row as $key => $value) {
                        if ($value instanceof DateTime) {
                            $row[$key] = $value->format('Y-m-d H:i:s');
                        }
                    }
                    $results[] = $row;
                }
                
                sqlsrv_free_stmt($stmt);
                
                // Log de consulta exitosa
                error_log("CP Frontend: {$source_name} - " . count($results) . " registros encontrados");
                
                return $results;
            }
        } catch (Exception $e) {
            error_log("CP Frontend: Error en {$source_name} - " . $e->getMessage());
            return array();
        }
    }

    /**
     * Sistema de caché para consultas (agregar a class-cp-frontend.php)
     */
    private function get_cached_results($cache_key) {
        // Usar transients de WordPress para caché
        $cache_duration = get_option('cp_cache_duration', 300); // 5 minutos por defecto
        return get_transient($cache_key);
    }

    private function set_cached_results($cache_key, $results) {
        $cache_duration = get_option('cp_cache_duration', 300);
        if ($cache_duration > 0) {
            set_transient($cache_key, $results, $cache_duration);
        }
    }

    private function generate_cache_key($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $source) {
        return 'cp_query_' . md5($profile_type . $fecha_inicio . $fecha_fin . $numero_documento . $source);
    }

    /**
     * Método optimizado con caché para búsquedas
     */
    private function search_with_cache($source, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $method) {
        // Verificar si el caché está habilitado
        if (!get_option('cp_enable_cache', true)) {
            return $this->execute_search_direct($source, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $method);
        }
        
        // Generar clave de caché
        $cache_key = $this->generate_cache_key($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $source);
        
        // Intentar obtener del caché
        $cached_results = $this->get_cached_results($cache_key);
        if ($cached_results !== false) {
            error_log("CP Frontend: Usando caché para {$source}");
            return $cached_results;
        }
        
        // Ejecutar búsqueda
        $results = $this->execute_search_direct($source, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $method);
        
        // Guardar en caché
        if (!empty($results)) {
            $this->set_cached_results($cache_key, $results);
        }
        
        return $results;
    }

    /**
     * Ejecutar búsqueda directa sin caché
     */
    private function execute_search_direct($source, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $method) {
        switch ($source) {
            case 'tvec':
                return ($method === 'api') ? 
                    $this->search_tvec_api($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) :
                    $this->search_tvec_database($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
                    
            case 'secopi':
                return ($method === 'api') ? 
                    $this->search_secopi_api($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) :
                    $this->search_secopi_database($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
                    
            case 'secopii':
                return ($method === 'api') ? 
                    $this->search_secopii_api($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) :
                    $this->search_secopii_database($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
                    
            default:
                return array();
        }
    }

    /**
     * Validar entrada de datos mejorada
     */
    private function validate_search_input($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        $errors = array();
        
        // Validar perfil
        if (!in_array($profile_type, array('entidades', 'proveedores'))) {
            $errors[] = 'Tipo de perfil inválido';
        }
        
        // Validar fechas
        $inicio = DateTime::createFromFormat('Y-m-d', $fecha_inicio);
        $fin = DateTime::createFromFormat('Y-m-d', $fecha_fin);
        
        if (!$inicio || !$fin) {
            $errors[] = 'Formato de fecha inválido';
        } else {
            // Validar rango de fechas más específico
            $hoy = new DateTime();
            $hace_10_anos = new DateTime('-10 years');
            
            if ($inicio < $hace_10_anos || $fin < $hace_10_anos) {
                $errors[] = 'Las fechas no pueden ser anteriores a 10 años';
            }
            
            if ($inicio > $hoy || $fin > $hoy) {
                $errors[] = 'Las fechas no pueden ser futuras';
            }
            
            if ($inicio > $fin) {
                $errors[] = 'La fecha de inicio debe ser anterior a la fecha de fin';
            }
            
            // Verificar rango máximo
            $diff = $inicio->diff($fin);
            if ($diff->days > 730) { // 2 años máximo
                $errors[] = 'El rango de fechas no puede ser mayor a 2 años';
            }
        }
        
        // Validar número de documento
        if (empty($numero_documento) || !is_numeric($numero_documento)) {
            $errors[] = 'Número de documento inválido';
        }
        
        // Validar longitud del documento
        if (strlen($numero_documento) < 6 || strlen($numero_documento) > 15) {
            $errors[] = 'El número de documento debe tener entre 6 y 15 dígitos';
        }
        
        return $errors;
    }

    /**
     * Limpiar caché manualmente
     */
    public function clear_search_cache() {
        global $wpdb;
        
        // Eliminar todos los transients de consultas
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cp_query_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_cp_query_%'");
        
        return true;
    }

    /**
     * Obtener estadísticas de uso del caché
     */
    public function get_cache_stats() {
        global $wpdb;
        
        $cache_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_cp_query_%'");
        
        return array(
            'cached_queries' => intval($cache_count),
            'cache_enabled' => get_option('cp_enable_cache', true),
            'cache_duration' => get_option('cp_cache_duration', 300)
        );
    }

    /**
     * Método mejorado perform_searches con validación y caché
     */
    private function perform_searches_optimized($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        // Validar entrada
        $validation_errors = $this->validate_search_input($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
        if (!empty($validation_errors)) {
            throw new Exception('Datos de entrada inválidos: ' . implode(', ', $validation_errors));
        }
        
        $results = array();
        $total_results = 0;
        $search_start_time = microtime(true);
        
        // Obtener configuración de búsquedas activas
        $search_config = $this->get_search_configuration();
        
        // Ejecutar búsquedas con caché
        foreach ($search_config as $source => $config) {
            if ($config['active']) {
                $source_start_time = microtime(true);
                
                $source_results = $this->search_with_cache(
                    $source, 
                    $profile_type, 
                    $fecha_inicio, 
                    $fecha_fin, 
                    $numero_documento, 
                    $config['method']
                );
                
                if (!empty($source_results)) {
                    $results[$source] = $source_results;
                    $total_results += count($source_results);
                }
                
                $source_time = microtime(true) - $source_start_time;
                error_log("CP Frontend: {$source} completado en " . round($source_time, 3) . "s - " . count($source_results) . " resultados");
            }
        }
        
        $total_time = microtime(true) - $search_start_time;
        
        // Registrar búsqueda en el log con métricas de rendimiento
        $this->log_frontend_search_detailed(
            $profile_type, 
            $fecha_inicio, 
            $fecha_fin, 
            $numero_documento, 
            $total_results,
            $total_time
        );
        
        return $results;
    }

    /**
     * Log detallado con métricas de rendimiento
     */
    private function log_frontend_search_detailed($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $results_count, $execution_time) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return;
        }
        
        $search_sources = array();
        $search_config = $this->get_search_configuration();
        
        foreach ($search_config as $source => $config) {
            if ($config['active']) {
                $search_sources[] = $source . ':' . $config['method'];
            }
        }
        
        $wpdb->insert(
            $table_name,
            array(
                'session_id' => session_id() ?: 'no-session',
                'profile_type' => $profile_type,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
                'numero_documento' => $numero_documento,
                'search_sources' => implode(',', $search_sources),
                'results_found' => $results_count,
                'execution_time' => $execution_time,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s')
        );
    }



    /**
     * Verificar si los stored procedures están disponibles
     */
    private function stored_procedures_available() {
        // Por ahora retornamos false para usar las consultas SQL directas
        // Esto se puede configurar como una opción del plugin
        return get_option('cp_use_stored_procedures', false);
    }

    /**
     * Registrar búsqueda en el log del frontend
     */
    private function log_frontend_search($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $results_count) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_frontend_logs';
        
        // Verificar que la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return;
        }
        
        $search_sources = array();
        $search_config = $this->get_search_configuration();
        
        foreach ($search_config as $source => $config) {
            if ($config['active']) {
                $search_sources[] = $source . ':' . $config['method'];
            }
        }
        
        $wpdb->insert(
            $table_name,
            array(
                'session_id' => session_id() ?: 'no-session',
                'profile_type' => $profile_type,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin,
                'numero_documento' => $numero_documento,
                'search_sources' => implode(',', $search_sources),
                'results_found' => $results_count,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
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
     * Actualizar el método perform_searches para incluir logging
     */
    private function perform_searches($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        $results = array();
        $total_results = 0;
        
        // Obtener configuración de búsquedas activas
        $search_config = $this->get_search_configuration();
        
        // TVEC
        if ($search_config['tvec']['active']) {
            $tvec_results = $this->search_tvec($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $search_config['tvec']['method']);
            if (!empty($tvec_results)) {
                $results['tvec'] = $tvec_results;
                $total_results += count($tvec_results);
            }
        }
        
        // SECOPI
        if ($search_config['secopi']['active']) {
            $secopi_results = $this->search_secopi($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $search_config['secopi']['method']);
            if (!empty($secopi_results)) {
                $results['secopi'] = $secopi_results;
                $total_results += count($secopi_results);
            }
        }
        
        // SECOPII
        if ($search_config['secopii']['active']) {
            $secopii_results = $this->search_secopii($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $search_config['secopii']['method']);
            if (!empty($secopii_results)) {
                $results['secopii'] = $secopii_results;
                $total_results += count($secopii_results);
            }
        }
        
        // Registrar búsqueda en el log
        $this->log_frontend_search($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $total_results);
        
        return $results;
    }


    
    /**
     * Buscar TVEC vía API (placeholder)
     */
    private function search_tvec_api($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        // Placeholder para API TVEC
        return array();
    }
    
    /**
     * Buscar SECOPI vía API (placeholder)
     */
    private function search_secopi_api($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        // Placeholder para API SECOPI
        return array();
    }
    
    /**
     * Buscar SECOPII vía API (placeholder)
     */
    private function search_secopii_api($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        // Placeholder para API SECOPII
        return array();
    }
    
    /**
     * Validar rango de fechas
     */
    private function validate_date_range($fecha_inicio, $fecha_fin) {
        $inicio = DateTime::createFromFormat('Y-m-d', $fecha_inicio);
        $fin = DateTime::createFromFormat('Y-m-d', $fecha_fin);
        
        if (!$inicio || !$fin) {
            return false;
        }
        
        // La fecha de inicio no puede ser mayor que la fecha de fin
        if ($inicio > $fin) {
            return false;
        }
        
        // No permitir fechas futuras
        $hoy = new DateTime();
        if ($fin > $hoy) {
            return false;
        }
        
        // No permitir rangos muy amplios (máximo 1 año)
        $diff = $inicio->diff($fin);
        if ($diff->days > 365) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Contar total de registros
     */
    private function count_total_records($results) {
        $total = 0;
        foreach ($results as $source => $records) {
            $total += count($records);
        }
        return $total;
    }
}