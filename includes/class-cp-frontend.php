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
     * Realizar búsquedas en las tablas configuradas
     */
    private function perform_searches($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        $results = array();
        
        // Obtener configuración de búsquedas activas
        $search_config = $this->get_search_configuration();
        
        // TVEC
        if ($search_config['tvec']['active']) {
            $tvec_results = $this->search_tvec($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $search_config['tvec']['method']);
            if (!empty($tvec_results)) {
                $results['tvec'] = $tvec_results;
            }
        }
        
        // SECOPI
        if ($search_config['secopi']['active']) {
            $secopi_results = $this->search_secopi($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $search_config['secopi']['method']);
            if (!empty($secopi_results)) {
                $results['secopi'] = $secopi_results;
            }
        }
        
        // SECOPII
        if ($search_config['secopii']['active']) {
            $secopii_results = $this->search_secopii($profile_type, $fecha_inicio, $fecha_fin, $numero_documento, $search_config['secopii']['method']);
            if (!empty($secopii_results)) {
                $results['secopii'] = $secopii_results;
            }
        }
        
        return $results;
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
            return array();
        }
        
        $connection = $connection_result['connection'];
        $method = $connection_result['method'];
        
        // Optimización: Usar stored procedure si está disponible, sino consulta directa
        if ($this->use_stored_procedures()) {
            return $this->search_tvec_with_sp($connection, $method, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
        }
        
        // Consulta optimizada para TVEC
        $sql = "
            SELECT 
                ord.ID as 'ID_Contrato',
                ord.ID_Entidad as 'ID_Entidad', 
                ent.NIT as 'NIT_Entidad',
                ent.Entidad as 'Entidad',
                CONVERT(varchar, ord.Fecha, 23) as 'Fecha_cargue',
                'Compra por TVEC' as 'Modalidad',
                ord.Agregacion as 'Tipo_Contrato', 
                '0' as 'UNSPSC_Clase',
                ord.Proveedor as 'Prov_nombre',
                ord.ID_Proveedor as 'Prov_documento', 
                pro.NIT as 'NIT_Proveedor',
                ord.Items as 'Detalle',
                ord.Estado as 'Estado',
                CAST(ord.Total as decimal(18,2)) as 'Valor_Total',
                CONCAT('https://www.colombiacompra.gov.co/tienda-virtual-del-estado-colombiano/ordenes-compra/', ord.ID) as 'Link',
                CONVERT(varchar, ord.Fecha, 23) as 'Fecha_firma', 
                'TVEC' as 'Fuente'
            FROM TVEC.V_Ordenes ord WITH (NOLOCK)
            INNER JOIN TVEC.Entidades ent WITH (NOLOCK) ON ord.ID_Entidad = ent.ID
            INNER JOIN TVEC.Proveedores pro WITH (NOLOCK) ON ord.ID_Proveedor = pro.ID
            WHERE ord.Fecha >= CAST(? as datetime) 
            AND ord.Fecha <= CAST(? as datetime)
        ";
        
        $params = array($fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59');
        
        if ($profile_type === 'entidades') {
            $sql .= " AND REPLACE(REPLACE(REPLACE(ent.NIT, '.', ''), ',', ''), ' ', '') LIKE ?";
            $params[] = '%' . preg_replace('/[^0-9]/', '', $numero_documento) . '%';
        } else {
            $sql .= " AND REPLACE(REPLACE(REPLACE(pro.NIT, '.', ''), ',', ''), ' ', '') LIKE ?";
            $params[] = '%' . preg_replace('/[^0-9]/', '', $numero_documento) . '%';
        }
        
        // Optimización: Agregar índices sugeridos en comentario
        $sql .= " ORDER BY ord.Fecha DESC";
        
        return $this->execute_optimized_query($connection, $method, $sql, $params, 'TVEC');
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
            return array();
        }
        
        $connection = $connection_result['connection'];
        $method = $connection_result['method'];
        
        if ($this->use_stored_procedures()) {
            return $this->search_secopi_with_sp($connection, $method, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
        }
        
        // Consulta optimizada para SECOPI con CTEs para mejor rendimiento
        $sql = "
            WITH AdicionesCalculadas AS (
                SELECT 
                    id_adjudicacion,
                    SUM(ISNULL(valor, 0)) AS ADD_VAL,
                    SUM(CASE WHEN rango IN('1','D') AND tipo_adicion <> 2 THEN tiempo ELSE 0 END) AS ADD_DIAS,
                    SUM(CASE WHEN rango IN('2','M') AND tipo_adicion <> 2 THEN tiempo ELSE 0 END) AS ADD_MESES
                FROM SECOPI.t_ptc_adiciones WITH (NOLOCK)
                GROUP BY id_adjudicacion
            ),
            FechaFinCalculada AS (
                SELECT 
                    ADJ.id_adjudicacion,
                    CASE ADJ.rango_plazo_ejecucion 
                        WHEN 'M' THEN DATEADD(MM, ISNULL(ADJ.PLAZO_EJECUCION,0) + ISNULL(AC.ADD_MESES, 0) + ISNULL(AC.ADD_DIAS, 0), ADJ.fecha_inicio_ejecucion)
                        WHEN 'D' THEN DATEADD(DD, ISNULL(AC.ADD_MESES, 0) + ISNULL(ADJ.plazo_ejecucion, 0) + ISNULL(AC.ADD_DIAS, 0), ADJ.fecha_inicio_ejecucion)
                        ELSE NULL 
                    END AS fecha_fin_contrato,
                    AC.ADD_VAL
                FROM SECOPI.T_PTC_Adjudicaciones ADJ WITH (NOLOCK)
                LEFT JOIN AdicionesCalculadas AC ON ADJ.id_adjudicacion = AC.id_adjudicacion
            )
            SELECT 
                CONCAT(PRO.NUM_CONSTANCIA, '-', ADJ.ID_ADJUDICACION) AS 'ID Contrato',
                YEAR(PRO.FECHA_CARGUE) AS 'Anno Cargue',
                MONTH(PRO.FECHA_CARGUE) AS 'Mes Cargue', 
                CONVERT(varchar, PRO.FECHA_CARGUE, 23) AS 'Fecha cargue',
                CONVERT(varchar, ADJ.FECHA_FIRMA_CONTRATO, 23) AS 'Fecha Firma',
                CONVERT(varchar, ADJ.FECHA_INICIO_EJECUCION, 23) AS 'Fecha Inicio del Contrato',
                CONVERT(varchar, FC.fecha_fin_contrato, 23) AS 'Fecha Fin del Contrato',
                TPORN.DESC_NIVEL AS 'Orden Entidad',
                GEO5.DESCRIPCION AS 'Departamento Entidad', 
                GEO4.DESCRIPCION AS 'Municipio Entidad',
                ENTI.NIT_ENTI AS 'NIT de la Entidad',
                ENTI.NOMB_ENTI AS 'Nombre de la Entidad',
                TPRO.NOMBRE AS 'Modalidad Contratacion',
                TCONT.DESCRIPCION AS 'Tipo de Contrato',
                CD.DESCRIPCION AS 'Justificacion modalidad',
                CLASE.COD_CLASE AS 'ID Clase', 
                PRO.NUM_CONSTANCIA AS 'Numero de Constancia',
                PRO.NUMERO_PROCESO AS 'Numero de Proceso',
                ADJ.ID_ADJUDICACION AS 'ID Adjudicacion',
                ADJ.NUMERO_CONTRATO AS 'Numero del Contrato',
                ADJ.OBJETO_CONTRATO AS 'Objeto contractual',
                CAST((ISNULL(ADJ.valor_contrato,0) + ISNULL(FC.ADD_VAL, 0)) as decimal(18,2)) AS 'Valor con adiciones',
                ADJ.RAZON_SOCIAL AS 'Nom Raz Social Contratista',
                TDOC.DESCRIPCION AS 'Tipo Identifi del Contratista',
                ADJ.NUMERO_DOC AS 'Identificacion del Contratista', 
                ESTPRO.DESCRIPCION AS 'Estado del Proceso',
                TIPODES.ORIG_REC_NOMBRE AS 'Origen de los Recursos',
                '' as 'Destino Gasto',
                CONCAT('https://www.contratos.gov.co/consultas/detalleProceso.do?numConstancia=', PRO.num_constancia) AS 'Link',
                ENTI.CODI_ENTI AS 'ID Entidad',
                'SECOP_I' as 'Fuente'
            FROM SECOPI.T_PTC_Adjudicaciones ADJ WITH (NOLOCK)
            INNER JOIN SECOPI.T_PTC_Procesos PRO WITH (NOLOCK) ON ADJ.id_proceso = PRO.num_constancia  
            INNER JOIN SECOPI.TB_UsuarioLDAP USU WITH (NOLOCK) ON PRO.USUARIO = USU.USUARIO
            INNER JOIN SECOPI.TPOR_Enti ENTI WITH (NOLOCK) ON USU.IDENTIDAD = ENTI.CODI_ENTI
            LEFT JOIN FechaFinCalculada FC ON ADJ.id_adjudicacion = FC.id_adjudicacion
            LEFT JOIN SECOPI.TB_ESTADO_PROCESO ESTPRO WITH (NOLOCK) ON PRO.ESTADO_PROCESO = ESTPRO.OID
            LEFT JOIN SECOPI.TB_Tipo_Proceso TPRO WITH (NOLOCK) ON PRO.ID_TIPO_PROCESO = TPRO.OID
            LEFT JOIN SECOPI.TPOR_Nivel TPORN WITH (NOLOCK) ON ENTI.NIVEL = TPORN.ID_NIVEL
            LEFT JOIN SECOPI.T_PTC_Causales_Directa CD WITH (NOLOCK) ON PRO.ID_CAUSAL_DIRECTA = CD.IDENTIFICADOR
            LEFT JOIN SECOPI.T_PTC_Tipos_Contratos TCONT WITH (NOLOCK) ON PRO.ID_TIPO_CONTRATO = TCONT.IDENTIFICADOR
            LEFT JOIN SECOPI.UNSPSC_Clases CLASE WITH (NOLOCK) ON PRO.UNSPSC_CLASE = CLASE.COD_CLASE
            LEFT JOIN SECOPI.T_PTC_Tipos_Documentos TDOC WITH (NOLOCK) ON ADJ.TIPO_DOC_CONTRATISTA = TDOC.IDENTIFICACION
            LEFT JOIN SECOPI.t_ptc_destino_gasto DESGAS WITH (NOLOCK) ON ADJ.id_adjudicacion = DESGAS.id_adjudicacion
            LEFT JOIN SECOPI.t_ptc_orig_rec TIPODES WITH (NOLOCK) ON TIPODES.orig_rec_oid = DESGAS.orig_rec_oid
            LEFT JOIN SECOPI.T_PTC_Ubicaciones_Geo GEO4 WITH (NOLOCK) ON ENTI.UBICACION_GEO = GEO4.IDENTIFICADOR
            LEFT JOIN SECOPI.T_PTC_Ubicaciones_Geo GEO5 WITH (NOLOCK) ON GEO4.IDENTIFICADOR_PADRE = GEO5.IDENTIFICADOR
            WHERE PRO.FECHA_CARGUE >= CAST(? as datetime)
            AND PRO.FECHA_CARGUE <= CAST(? as datetime)
            AND ENTI.CODI_ENTI NOT IN (199999999,201101072,295200018,201101065)
        ";
        
        $params = array($fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59');
        
        if ($profile_type === 'entidades') {
            $sql .= " AND REPLACE(REPLACE(REPLACE(ENTI.NIT_ENTI, '.', ''), ',', ''), ' ', '') LIKE ?";
            $params[] = '%' . preg_replace('/[^0-9]/', '', $numero_documento) . '%';
        } else {
            // Para proveedores, necesitamos filtrar por tipo de documento
            $sql .= " AND REPLACE(REPLACE(REPLACE(ADJ.NUMERO_DOC, '.', ''), ',', ''), ' ', '') LIKE ?";
            $params[] = '%' . preg_replace('/[^0-9]/', '', $numero_documento) . '%';
        }
        
        $sql .= " ORDER BY PRO.FECHA_CARGUE DESC";
        
        return $this->execute_optimized_query($connection, $method, $sql, $params, 'SECOPI');
    }

    /**
     * Buscar SECOPII en base de datos - OPTIMIZADO
     */
    private function search_secopii_database($profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        if (!$this->db) {
            return array();
        }
        
        $connection_result = $this->db->connect();
        if (!$connection_result['success']) {
            return array();
        }
        
        $connection = $connection_result['connection'];
        $method = $connection_result['method'];
        
        if ($this->use_stored_procedures()) {
            return $this->search_secopii_with_sp($connection, $method, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
        }
        
        // Consulta optimizada para SECOPII
        $sql = "
            SELECT 
                CAST(ContractReference COLLATE SQL_Latin1_General_CP1_CI_AI as varchar) as 'ID Contrato',
                YEAR(CAST(AprovalDate as date)) as 'Anno Cargue',
                MONTH(CAST(AprovalDate as date)) as 'Mes Cargue',
                CONVERT(varchar, CAST(AprovalDate as date), 23) as 'Fecha cargue',
                CONVERT(varchar, CAST(AprovalDate as date), 23) as 'Fecha Firma',
                CONVERT(varchar, CAST(ContractStartDate as date), 23) as 'Fecha Inicio del Contrato',
                CONVERT(varchar, CAST(ContractEndDate as date), 23) as 'Fecha Fin del Contrato',
                Orden COLLATE SQL_Latin1_General_CP1_CI_AI as 'Orden Entidad',
                [Departamento Entidad] COLLATE SQL_Latin1_General_CP1_CI_AI as 'Departamento Entidad',
                [Ciudad Entidad] COLLATE SQL_Latin1_General_CP1_CI_AI as 'Municipio Entidad',
                [NIT Entidad] as 'NIT de la Entidad',
                [Nombre Entidad] COLLATE SQL_Latin1_General_CP1_CI_AI as 'Nombre de la Entidad',
                ProcedureProfileLabel COLLATE SQL_Latin1_General_CP1_CI_AI as 'Modalidad Contratacion',
                TypeContract COLLATE SQL_Latin1_General_CP1_CI_AI as 'Tipo de Contrato',
                JustificationTypeOfContractCode COLLATE SQL_Latin1_General_CP1_CI_AI as 'Justificacion modalidad',
                CAST(SUBSTRING(DimMainCategoryCode COLLATE SQL_Latin1_General_CP1_CI_AI, 4, 6) as varchar) as 'ID Clase',
                RequestReference COLLATE SQL_Latin1_General_CP1_CI_AI as 'Numero de Proceso',
                ContractReference COLLATE SQL_Latin1_General_CP1_CI_AI as 'Numero del Contrato',
                Description COLLATE SQL_Latin1_General_CP1_CI_AI as 'Objeto contractual',
                CAST(ContractValue as decimal(18,2)) as 'Valor con adiciones',
                [Nombre Proveedor] COLLATE SQL_Latin1_General_CP1_CI_AI as 'Nom Raz Social Contratista',
                TipoDocProveedor COLLATE SQL_Latin1_General_CP1_CI_AI as 'Tipo Identifi del Contratista',
                [Documento Proveedor] COLLATE SQL_Latin1_General_CP1_CI_AI as 'Identificacion del Contratista',
                ContractState COLLATE SQL_Latin1_General_CP1_CI_AI as 'Estado del Proceso',
                [Origen de los Recursos] COLLATE SQL_Latin1_General_CP1_CI_AI as 'Origen de los Recursos',
                [Destino Gasto] COLLATE SQL_Latin1_General_CP1_CI_AI as 'Destino Gasto',
                URLProceso COLLATE SQL_Latin1_General_CP1_CI_AI as 'Link',
                CAST([Código Entidad] COLLATE SQL_Latin1_General_CP1_CI_AI as varchar) as 'ID Entidad',
                'SECOP_II' as 'Fuente'
            FROM SECOPII.V_HistoricoContratos_Depurado WITH (NOLOCK)
            WHERE AprovalDate >= CAST(? as datetime)
            AND AprovalDate <= CAST(? as datetime)
        ";
        
        $params = array($fecha_inicio . ' 00:00:00', $fecha_fin . ' 23:59:59');
        
        if ($profile_type === 'entidades') {
            $sql .= " AND REPLACE(REPLACE(REPLACE([NIT Entidad], '.', ''), ',', ''), ' ', '') LIKE ?";
            $params[] = '%' . preg_replace('/[^0-9]/', '', $numero_documento) . '%';
        } else {
            $sql .= " AND REPLACE(REPLACE(REPLACE([Documento Proveedor], '.', ''), ',', ''), ' ', '') LIKE ?";
            $params[] = '%' . preg_replace('/[^0-9]/', '', $numero_documento) . '%';
        }
        
        $sql .= " ORDER BY AprovalDate DESC";
        
        return $this->execute_optimized_query($connection, $method, $sql, $params, 'SECOPII');
    }

    /**
     * Ejecutar consulta optimizada
     */
    private function execute_optimized_query($connection, $method, $sql, $params, $source) {
        try {
            $start_time = microtime(true);
            
            if (strpos($method, 'PDO') !== false) {
                $stmt = $connection->prepare($sql);
                $stmt->execute($params);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stmt = sqlsrv_query($connection, $sql, $params);
                if ($stmt === false) {
                    throw new Exception('Error en consulta ' . $source);
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
            }
            
            $execution_time = microtime(true) - $start_time;
            
            // Log de rendimiento
            $this->log_query_performance($source, count($results), $execution_time);
            
            return $results;
            
        } catch (Exception $e) {
            CP_Utils::log("Error en consulta {$source}: " . $e->getMessage(), 'error');
            return array();
        }
    }

    /**
     * Verificar si usar stored procedures
     */
    private function use_stored_procedures() {
        return get_option('cp_use_stored_procedures', true);
    }

    /**
     * Buscar con stored procedure - TVEC
     */
    private function search_tvec_with_sp($connection, $method, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        try {
            if (strpos($method, 'PDO') !== false) {
                $sp_name = $profile_type === 'entidades' ? 
                    'IDI.ConsultaContratosPorEntidad_TVEC' : 
                    'IDI.ConsultaContratosPorProveedor_TVEC';
                
                $stmt = $connection->prepare("EXEC {$sp_name} ?, ?, ?");
                $stmt->execute(array($numero_documento, $fecha_inicio, $fecha_fin));
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $sp_name = $profile_type === 'entidades' ? 
                    '{ call IDI.ConsultaContratosPorEntidad_TVEC(?,?,?) }' : 
                    '{ call IDI.ConsultaContratosPorProveedor_TVEC(?,?,?) }';
                
                $callStat = $connection->prepare($sp_name);
                sqlsrv_execute($callStat, array($numero_documento, $fecha_inicio, $fecha_fin));
                
                $results = array();
                do {
                    while ($row = sqlsrv_fetch_array($callStat, SQLSRV_FETCH_ASSOC)) {
                        $results[] = $row;
                    }
                } while (sqlsrv_next_result($callStat));
                
                return $results;
            }
        } catch (Exception $e) {
            CP_Utils::log("Error en SP TVEC: " . $e->getMessage(), 'error');
            return array();
        }
    }

    /**
     * Buscar con stored procedure - SECOPI
     */
    private function search_secopi_with_sp($connection, $method, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        try {
            if (strpos($method, 'PDO') !== false) {
                $sp_name = $profile_type === 'entidades' ? 
                    'IDI.ConsultaContratosPorEntidad_SECOPI' : 
                    'IDI.ConsultaContratosPorProveedor_SECOPI';
                
                $stmt = $connection->prepare("EXEC {$sp_name} ?, ?, ?");
                $stmt->execute(array($numero_documento, $fecha_inicio, $fecha_fin));
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $sp_name = $profile_type === 'entidades' ? 
                    '{ call IDI.ConsultaContratosPorEntidad_SECOPI(?,?,?) }' : 
                    '{ call IDI.ConsultaContratosPorProveedor_SECOPI(?,?,?) }';
                
                $callStat = $connection->prepare($sp_name);
                sqlsrv_execute($callStat, array($numero_documento, $fecha_inicio, $fecha_fin));
                
                $results = array();
                do {
                    while ($row = sqlsrv_fetch_array($callStat, SQLSRV_FETCH_ASSOC)) {
                        foreach ($row as $key => $value) {
                            if ($value instanceof DateTime) {
                                $row[$key] = $value->format('Y-m-d H:i:s');
                            }
                        }
                        $results[] = $row;
                    }
                } while (sqlsrv_next_result($callStat));
                
                return $results;
            }
        } catch (Exception $e) {
            CP_Utils::log("Error en SP SECOPI: " . $e->getMessage(), 'error');
            return array();
        }
    }

    /**
     * Buscar con stored procedure - SECOPII
     */
    private function search_secopii_with_sp($connection, $method, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        try {
            if (strpos($method, 'PDO') !== false) {
                $sp_name = $profile_type === 'entidades' ? 
                    'IDI.ConsultaContratosPorEntidad_SECOPII' : 
                    'IDI.ConsultaContratosPorProveedor_SECOPII';
                
                $stmt = $connection->prepare("EXEC {$sp_name} ?, ?, ?");
                $stmt->execute(array($numero_documento, $fecha_inicio, $fecha_fin));
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $sp_name = $profile_type === 'entidades' ? 
                    '{ call IDI.ConsultaContratosPorEntidad_SECOPII(?,?,?) }' : 
                    '{ call IDI.ConsultaContratosPorProveedor_SECOPII(?,?,?) }';
                
                $callStat = $connection->prepare($sp_name);
                sqlsrv_execute($callStat, array($numero_documento, $fecha_inicio, $fecha_fin));
                
                $results = array();
                do {
                    while ($row = sqlsrv_fetch_array($callStat, SQLSRV_FETCH_ASSOC)) {
                        foreach ($row as $key => $value) {
                            if ($value instanceof DateTime) {
                                $row[$key] = $value->format('Y-m-d H:i:s');
                            }
                        }
                        $results[] = $row;
                    }
                } while (sqlsrv_next_result($callStat));
                
                return $results;
            }
        } catch (Exception $e) {
            CP_Utils::log("Error en SP SECOPII: " . $e->getMessage(), 'error');
            return array();
        }
    }

    /**
     * Log de rendimiento de consultas
     */
    private function log_query_performance($source, $records_count, $execution_time) {
        $performance_data = array(
            'source' => $source,
            'records_count' => $records_count,
            'execution_time' => round($execution_time, 4),
            'timestamp' => current_time('mysql')
        );
        
        CP_Utils::log("Query Performance - {$source}: {$records_count} records in {$execution_time}s", 'info', $performance_data);
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

    /**
     * AJAX: Iniciar búsqueda con seguimiento de progreso
     */
    public function ajax_start_search_with_progress() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cp_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Token de seguridad inválido'));
        }
        
        // Generar ID único para esta búsqueda
        $search_id = wp_generate_uuid4();
        
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
        
        // Crear registro de progreso
        $this->create_search_progress($search_id, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
        
        // Programar la búsqueda en background (usando wp_schedule_single_event o processing directo)
        $this->schedule_background_search($search_id);
        
        // Retornar ID de búsqueda para seguimiento
        wp_send_json_success(array(
            'search_id' => $search_id,
            'message' => 'Búsqueda iniciada correctamente'
        ));
    }

    /**
     * AJAX: Obtener estado de progreso de búsqueda
     */
    public function ajax_get_search_progress() {
        check_ajax_referer('cp_frontend_nonce', 'nonce');
        
        $search_id = sanitize_text_field($_POST['search_id'] ?? '');
        
        if (empty($search_id)) {
            wp_send_json_error(array('message' => 'ID de búsqueda requerido'));
        }
        
        $progress = $this->get_search_progress($search_id);
        
        if (!$progress) {
            wp_send_json_error(array('message' => 'Búsqueda no encontrada'));
        }
        
        wp_send_json_success($progress);
    }

    /**
     * AJAX: Obtener resultados finales de búsqueda
     */
    public function ajax_get_search_results() {
        check_ajax_referer('cp_frontend_nonce', 'nonce');
        
        $search_id = sanitize_text_field($_POST['search_id'] ?? '');
        
        if (empty($search_id)) {
            wp_send_json_error(array('message' => 'ID de búsqueda requerido'));
        }
        
        $results = $this->get_search_results($search_id);
        
        if (!$results) {
            wp_send_json_error(array('message' => 'Resultados no encontrados'));
        }
        
        wp_send_json_success($results);
    }

    /**
     * Crear registro de progreso de búsqueda
     */
    private function create_search_progress($search_id, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_search_progress';
        
        // Crear tabla si no existe
        $this->create_progress_table();
        
        $search_config = $this->get_search_configuration();
        $active_sources = array();
        
        if ($search_config['tvec']['active']) $active_sources[] = 'tvec';
        if ($search_config['secopi']['active']) $active_sources[] = 'secopi';
        if ($search_config['secopii']['active']) $active_sources[] = 'secopii';
        
        $progress_data = array(
            'search_id' => $search_id,
            'profile_type' => $profile_type,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'numero_documento' => $numero_documento,
            'active_sources' => json_encode($active_sources),
            'status' => 'started',
            'progress_percent' => 0,
            'current_source' => '',
            'sources_completed' => json_encode(array()),
            'total_records' => 0,
            'results_data' => '',
            'error_message' => '',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $wpdb->insert($table_name, $progress_data);
    }

    /**
     * Programar búsqueda en background
     */
    private function schedule_background_search($search_id) {
        // Opción 1: Procesar inmediatamente (para respuesta rápida)
        $this->process_search_background($search_id);
        
        // Opción 2: Programar con wp-cron (para cargas pesadas)
        // wp_schedule_single_event(time() + 5, 'cp_process_background_search', array($search_id));
    }

    /**
     * Procesar búsqueda en background
     */
    public function process_search_background($search_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_search_progress';
        
        // Obtener datos de búsqueda
        $search_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE search_id = %s",
            $search_id
        ), ARRAY_A);
        
        if (!$search_data) {
            return;
        }
        
        $active_sources = json_decode($search_data['active_sources'], true);
        $total_sources = count($active_sources);
        $completed_sources = array();
        $all_results = array();
        $total_records = 0;
        
        try {
            foreach ($active_sources as $index => $source) {
                // Actualizar progreso - fuente actual
                $this->update_search_progress($search_id, array(
                    'current_source' => $source,
                    'progress_percent' => floor(($index / $total_sources) * 100),
                    'status' => 'processing'
                ));
                
                // Simular delay para mejor UX (opcional)
                sleep(1);
                
                // Ejecutar búsqueda según la fuente
                $source_results = $this->execute_source_search(
                    $source, 
                    $search_data['profile_type'],
                    $search_data['fecha_inicio'], 
                    $search_data['fecha_fin'],
                    $search_data['numero_documento']
                );
                
                if (!empty($source_results)) {
                    $all_results[$source] = $source_results;
                    $total_records += count($source_results);
                }
                
                $completed_sources[] = $source;
                
                // Actualizar progreso - fuente completada
                $this->update_search_progress($search_id, array(
                    'sources_completed' => json_encode($completed_sources),
                    'progress_percent' => floor((($index + 1) / $total_sources) * 100),
                    'total_records' => $total_records
                ));
            }
            
            // Búsqueda completada
            $final_status = empty($all_results) ? 'no_results' : 'completed';
            
            $this->update_search_progress($search_id, array(
                'status' => $final_status,
                'progress_percent' => 100,
                'current_source' => '',
                'results_data' => json_encode($all_results),
                'total_records' => $total_records
            ));
            
            // Crear log de consulta
            $this->create_search_log($search_data, $all_results, true);
            
        } catch (Exception $e) {
            // Error en búsqueda
            $this->update_search_progress($search_id, array(
                'status' => 'error',
                'error_message' => $e->getMessage()
            ));
            
            // Crear log de error
            $this->create_search_log($search_data, array(), false, $e->getMessage());
            
            CP_Utils::log("Error en búsqueda background: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Ejecutar búsqueda de una fuente específica
     */
    private function execute_source_search($source, $profile_type, $fecha_inicio, $fecha_fin, $numero_documento) {
        switch ($source) {
            case 'tvec':
                return $this->search_tvec($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
            case 'secopi':
                return $this->search_secopi($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
            case 'secopii':
                return $this->search_secopii($profile_type, $fecha_inicio, $fecha_fin, $numero_documento);
            default:
                return array();
        }
    }

    /**
     * Actualizar progreso de búsqueda
     */
    private function update_search_progress($search_id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_search_progress';
        $data['updated_at'] = current_time('mysql');
        
        $wpdb->update(
            $table_name,
            $data,
            array('search_id' => $search_id)
        );
    }

    /**
     * Obtener progreso de búsqueda
     */
    private function get_search_progress($search_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_search_progress';
        
        $progress = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE search_id = %s",
            $search_id
        ), ARRAY_A);
        
        if (!$progress) {
            return false;
        }
        
        // Procesar datos para el frontend
        $active_sources = json_decode($progress['active_sources'], true);
        $completed_sources = json_decode($progress['sources_completed'], true);
        
        return array(
            'search_id' => $progress['search_id'],
            'status' => $progress['status'],
            'progress_percent' => intval($progress['progress_percent']),
            'current_source' => $progress['current_source'],
            'active_sources' => $active_sources,
            'completed_sources' => $completed_sources,
            'total_records' => intval($progress['total_records']),
            'error_message' => $progress['error_message'],
            'created_at' => $progress['created_at']
        );
    }

    /**
     * Obtener resultados de búsqueda
     */
    private function get_search_results($search_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_search_progress';
        
        $search_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE search_id = %s",
            $search_id
        ), ARRAY_A);
        
        if (!$search_data || $search_data['status'] !== 'completed') {
            return false;
        }
        
        $results_data = json_decode($search_data['results_data'], true);
        
        if (empty($results_data)) {
            return array(
                'has_results' => false,
                'message' => 'No se encontraron resultados para los criterios especificados',
                'search_id' => $search_id
            );
        }
        
        return array(
            'has_results' => true,
            'results' => $results_data,
            'total_records' => intval($search_data['total_records']),
            'search_id' => $search_id,
            'profile_type' => $search_data['profile_type'],
            'fecha_inicio' => $search_data['fecha_inicio'],
            'fecha_fin' => $search_data['fecha_fin'],
            'numero_documento' => $search_data['numero_documento']
        );
    }

    /**
     * Crear tabla de progreso
     */
    private function create_progress_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_search_progress';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            search_id varchar(40) NOT NULL,
            profile_type varchar(20) NOT NULL,
            fecha_inicio date NOT NULL,
            fecha_fin date NOT NULL,
            numero_documento varchar(50) NOT NULL,
            active_sources text NOT NULL,
            status varchar(20) DEFAULT 'started',
            progress_percent int DEFAULT 0,
            current_source varchar(20),
            sources_completed text,
            total_records int DEFAULT 0,
            results_data longtext,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY search_id (search_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Crear log de consulta
     */
    private function create_search_log($search_data, $results, $success, $error_message = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_search_logs';
        
        // Crear tabla si no existe
        $this->create_search_logs_table();
        
        $sources_queried = json_decode($search_data['active_sources'], true);
        $sources_with_results = array();
        $total_records = 0;
        
        if ($success && !empty($results)) {
            foreach ($results as $source => $data) {
                if (!empty($data)) {
                    $sources_with_results[] = $source . ' (' . count($data) . ')';
                    $total_records += count($data);
                }
            }
        }
        
        $log_data = array(
            'search_id' => $search_data['search_id'],
            'user_session' => $this->get_user_session_id(),
            'ip_address' => $this->get_client_ip(),
            'profile_type' => $search_data['profile_type'],
            'fecha_inicio' => $search_data['fecha_inicio'],
            'fecha_fin' => $search_data['fecha_fin'],
            'numero_documento' => $search_data['numero_documento'],
            'sources_queried' => implode(', ', $sources_queried),
            'sources_with_results' => implode(', ', $sources_with_results),
            'total_records_found' => $total_records,
            'success' => $success ? 1 : 0,
            'error_message' => $error_message,
            'execution_time' => $this->calculate_execution_time($search_data['created_at']),
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert($table_name, $log_data);
    }

    /**
     * Crear tabla de logs de búsqueda
     */
    private function create_search_logs_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_search_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            search_id varchar(40) NOT NULL,
            user_session varchar(64) NOT NULL,
            ip_address varchar(45) NOT NULL,
            profile_type varchar(20) NOT NULL,
            fecha_inicio date NOT NULL,
            fecha_fin date NOT NULL,
            numero_documento varchar(50) NOT NULL,
            sources_queried text NOT NULL,
            sources_with_results text,
            total_records_found int DEFAULT 0,
            success tinyint(1) DEFAULT 0,
            error_message text,
            execution_time float DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY search_id (search_id),
            KEY user_session (user_session),
            KEY created_at (created_at),
            KEY success (success)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Obtener ID de sesión del usuario
     */
    private function get_user_session_id() {
        if (!session_id()) {
            session_start();
        }
        return session_id();
    }

    /**
     * Obtener IP del cliente
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
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
     * Calcular tiempo de ejecución
     */
    private function calculate_execution_time($start_time) {
        $start = strtotime($start_time);
        $end = current_time('timestamp');
        return $end - $start;
    }

    /**
     * Limpiar registros antiguos de progreso (llamar diariamente)
     */
    public function cleanup_old_progress_records() {
        global $wpdb;
        
        $progress_table = $wpdb->prefix . 'cp_search_progress';
        
        // Eliminar registros de más de 24 horas
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$progress_table} WHERE created_at < %s",
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));
    }
}