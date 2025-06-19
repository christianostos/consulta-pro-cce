<?php
/**
 * Clase de exportación para el plugin Consulta Procesos
 * 
 * Archivo: includes/class-cp-export.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_Export {
    
    private static $instance = null;
    private $temp_dir;
    private $allowed_formats = array('csv', 'excel', 'json', 'xml');
    
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
        $upload_dir = wp_upload_dir();
        $this->temp_dir = $upload_dir['basedir'] . '/cp-exports/';
        
        error_log('CP Export: Inicializando clase');
        error_log('CP Export: Upload dir: ' . print_r($upload_dir, true));
        error_log('CP Export: Temp dir configurado: ' . $this->temp_dir);
        
        // Cargar SimpleXLSXGen si está disponible
        $this->load_simplexlsxgen();
        
        $this->init_hooks();
        $this->create_temp_directory();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        error_log('CP Export: Registrando hooks AJAX');
        
        // CRÍTICO: Registrar hooks tanto para usuarios logueados como no logueados
        add_action('wp_ajax_cp_export_data', array($this, 'ajax_export_data'));
        add_action('wp_ajax_cp_download_export', array($this, 'ajax_download_export'));
        add_action('wp_ajax_nopriv_cp_download_export', array($this, 'ajax_download_export'));
        
        // Limpiar archivos temporales diariamente
        add_action('cp_cleanup_temp_files', array($this, 'cleanup_temp_files'));
        if (!wp_next_scheduled('cp_cleanup_temp_files')) {
            wp_schedule_event(time(), 'daily', 'cp_cleanup_temp_files');
        }
        
        error_log('CP Export: Hooks AJAX registrados exitosamente');
    }
    
    /**
     * Crear directorio temporal
     */
    private function create_temp_directory() {
        error_log('CP Export: Verificando directorio temporal: ' . $this->temp_dir);
        
        if (!file_exists($this->temp_dir)) {
            error_log('CP Export: Creando directorio temporal');
            $result = wp_mkdir_p($this->temp_dir);
            
            if (!$result) {
                error_log('CP Export: ERROR - No se pudo crear directorio');
                return false;
            }
        }
        
        // Verificar permisos
        if (!is_writable($this->temp_dir)) {
            error_log('CP Export: ERROR - Directorio no escribible');
            return false;
        }
        
        // Crear .htaccess para protección
        $htaccess_file = $this->temp_dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "deny from all\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }
        
        error_log('CP Export: Directorio temporal verificado OK');
        return true;
    }
    
    /**
     * Exportar datos en formato especificado
     */
    public function export_data($data, $format = 'csv', $filename = null, $headers = null) {
        error_log('CP Export: export_data iniciada - Formato: ' . $format . ', Registros: ' . count($data));
        
        if (!in_array($format, $this->allowed_formats)) {
            error_log('CP Export: Error - Formato no válido: ' . $format);
            return array(
                'success' => false,
                'error' => __('Formato de exportación no válido.', 'consulta-procesos')
            );
        }
        
        if (empty($data)) {
            error_log('CP Export: Error - No hay datos para exportar');
            return array(
                'success' => false,
                'error' => __('No hay datos para exportar.', 'consulta-procesos')
            );
        }
        
        // Generar nombre de archivo si no se proporciona
        if (!$filename) {
            $filename = $this->generate_filename($format);
        }
        
        $filepath = $this->temp_dir . $filename;
        error_log('CP Export: Archivo a crear: ' . $filepath);
        
        try {
            $result = false;
            
            switch ($format) {
                case 'csv':
                    $result = $this->export_to_csv($data, $filepath, $headers);
                    break;
                    
                case 'excel':
                    // Intentar SimpleXLSXGen primero, fallback a CSV si falla
                    $result = $this->export_to_excel_simplexlsxgen($data, $filepath, $headers);
                    break;
                    
                case 'json':
                    $result = $this->export_to_json($data, $filepath);
                    break;
                    
                case 'xml':
                    $result = $this->export_to_xml($data, $filepath);
                    break;
                    
                default:
                    throw new Exception('Formato no implementado: ' . $format);
            }
            
            if ($result) {
                // Verificar que el archivo se creó correctamente
                if (!file_exists($filepath)) {
                    throw new Exception('El archivo no se creó correctamente');
                }
                
                $file_size = filesize($filepath);
                if ($file_size === 0) {
                    throw new Exception('El archivo generado está vacío');
                }
                
                // Crear token de descarga temporal
                $download_token = $this->create_download_token($filename);
                
                error_log('CP Export: Archivo creado exitosamente - ' . $filename . ' (' . $file_size . ' bytes)');
                
                return array(
                    'success' => true,
                    'filename' => $filename,
                    'download_token' => $download_token,
                    'download_url' => $this->get_download_url($download_token),
                    'file_size' => $this->format_file_size($file_size),
                    'records_count' => count($data)
                );
            } else {
                throw new Exception('Error al generar el archivo de exportación');
            }
            
        } catch (Exception $e) {
            error_log('CP Export: Error en exportación - ' . $e->getMessage());
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Generar nombre de archivo único
     */
    private function generate_filename($format) {
        $timestamp = date('Y-m-d_H-i-s');
        $random = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        
        if ($format === 'excel') {
            // Si tenemos SimpleXLSXGen, usar .xlsx, sino .csv como fallback
            $extension = class_exists('Shuchkin\SimpleXLSXGen') ? 'xlsx' : 'csv';
        } else {
            $extension = $format;
        }
        
        return "consulta_procesos_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Exportar a CSV optimizado para Excel - SIMPLIFICADO
     */
    private function export_to_excel_csv($data, $filepath, $headers = null) {
        error_log('CP Export: export_to_excel_csv iniciada - ' . count($data) . ' registros');
        
        $file = fopen($filepath, 'w');
        
        if (!$file) {
            throw new Exception('No se pudo crear el archivo CSV');
        }
        
        // BOM para UTF-8 (crítico para Excel y caracteres especiales)
        fwrite($file, "\xEF\xBB\xBF");
        
        $rows_written = 0;
        
        // Escribir headers
        if ($headers && is_array($headers)) {
            fputcsv($file, $headers, ',', '"');
            $rows_written++;
        } elseif (!empty($data)) {
            $first_row = is_object($data[0]) ? get_object_vars($data[0]) : $data[0];
            if (is_array($first_row)) {
                // Crear headers más legibles
                $readable_headers = array();
                foreach (array_keys($first_row) as $key) {
                    $header = str_replace('_', ' ', $key);
                    $header = ucwords(strtolower($header));
                    $readable_headers[] = $header;
                }
                fputcsv($file, $readable_headers, ',', '"');
                $rows_written++;
            }
        }
        
        // Escribir datos
        foreach ($data as $row) {
            if (is_object($row)) {
                $row = get_object_vars($row);
            }
            
            if (is_array($row)) {
                // Limpiar valores para Excel
                $clean_row = array();
                foreach ($row as $value) {
                    if (is_null($value)) {
                        $clean_row[] = '';
                    } elseif (is_bool($value)) {
                        $clean_row[] = $value ? 'SÍ' : 'NO';
                    } elseif (is_array($value) || is_object($value)) {
                        $clean_row[] = json_encode($value, JSON_UNESCAPED_UNICODE);
                    } else {
                        // Convertir a string y limpiar saltos de línea
                        $str = (string) $value;
                        $str = str_replace(array("\r\n", "\r", "\n"), ' | ', $str);
                        $clean_row[] = $str;
                    }
                }
                
                fputcsv($file, $clean_row, ',', '"');
                $rows_written++;
            }
        }
        
        fclose($file);
        
        error_log('CP Export: CSV creado exitosamente con ' . $rows_written . ' filas');
        return true;
    }
    
    /**
     * Exportar a CSV
     */
    private function export_to_csv($data, $filepath, $headers = null) {
        error_log('CP Export: export_to_csv iniciada');
        
        $file = fopen($filepath, 'w');
        
        if (!$file) {
            throw new Exception('No se pudo crear el archivo CSV');
        }
        
        // BOM para UTF-8
        fwrite($file, "\xEF\xBB\xBF");
        
        $rows_written = 0;
        
        // Escribir headers
        if ($headers) {
            fputcsv($file, $headers);
            $rows_written++;
        } elseif (!empty($data) && is_array($data[0])) {
            // Usar keys del primer registro como headers
            $first_row = is_object($data[0]) ? get_object_vars($data[0]) : $data[0];
            fputcsv($file, array_keys($first_row));
            $rows_written++;
        }
        
        // Escribir datos
        foreach ($data as $row) {
            if (is_object($row)) {
                $row = get_object_vars($row);
            }
            
            // Limpiar valores
            $clean_row = array();
            foreach ($row as $value) {
                if (is_null($value)) {
                    $clean_row[] = '';
                } elseif (is_bool($value)) {
                    $clean_row[] = $value ? 'true' : 'false';
                } elseif (is_array($value) || is_object($value)) {
                    $clean_row[] = json_encode($value);
                } else {
                    $clean_row[] = (string) $value;
                }
            }
            
            fputcsv($file, $clean_row);
            $rows_written++;
        }
        
        fclose($file);
        
        error_log('CP Export: CSV creado con ' . $rows_written . ' filas');
        return true;
    }
    
    /**
     * Exportar a Excel (usando formato CSV con configuración para Excel)
     */
    private function export_to_excel($data, $filepath, $headers = null) {
        error_log('CP Export: export_to_excel iniciada');
        error_log('CP Export: Filepath: ' . $filepath);
        error_log('CP Export: Data count: ' . count($data));
        
        // Crear archivo Excel usando formato CSV con configuración específica para Excel
        $file = fopen($filepath, 'w');
        
        if (!$file) {
            error_log('CP Export: Error - No se pudo crear el archivo');
            throw new Exception('No se pudo crear el archivo Excel');
        }
        
        // BOM para UTF-8 (importante para caracteres especiales)
        fwrite($file, "\xEF\xBB\xBF");
        
        // Función personalizada para CSV compatible con Excel
        $put_csv_excel = function($file, $fields, $delimiter = ',') {
            $line = '';
            $first = true;
            
            foreach ($fields as $field) {
                if (!$first) {
                    $line .= $delimiter;
                }
                $first = false;
                
                // Formatear campo para Excel
                if (is_null($field)) {
                    $field = '';
                } elseif (is_bool($field)) {
                    $field = $field ? 'VERDADERO' : 'FALSO';
                } elseif (is_numeric($field)) {
                    // Para números grandes que pueden ser interpretados como científicos
                    if (strlen((string)$field) > 10 && ctype_digit((string)$field)) {
                        $field = "=\"{$field}\""; // Forzar como texto
                    }
                } elseif (is_string($field)) {
                    // Escapar comillas dobles
                    $field = str_replace('"', '""', $field);
                    
                    // Envolver en comillas si contiene caracteres especiales
                    if (strpos($field, $delimiter) !== false || 
                        strpos($field, '"') !== false || 
                        strpos($field, "\n") !== false ||
                        strpos($field, "\r") !== false) {
                        $field = '"' . $field . '"';
                    }
                    
                    // Manejar saltos de línea dentro de celdas
                    $field = str_replace(array("\r\n", "\r", "\n"), ' | ', $field);
                }
                
                $line .= $field;
            }
            
            fwrite($file, $line . "\r\n");
        };
        
        $rows_written = 0;
        
        // Escribir headers personalizados o automáticos
        if ($headers) {
            $put_csv_excel($file, $headers);
            $rows_written++;
        } elseif (!empty($data)) {
            $first_row = is_object($data[0]) ? get_object_vars($data[0]) : $data[0];
            if (is_array($first_row)) {
                // Crear headers más legibles
                $readable_headers = array();
                foreach (array_keys($first_row) as $key) {
                    $readable_headers[] = $this->format_header_name($key);
                }
                $put_csv_excel($file, $readable_headers);
                $rows_written++;
            }
        }
        
        // Escribir datos
        foreach ($data as $row) {
            if (is_object($row)) {
                $row = get_object_vars($row);
            }
            
            if (is_array($row)) {
                // Formatear valores para mejor visualización en Excel
                $formatted_row = array();
                foreach ($row as $key => $value) {
                    $formatted_row[] = $this->format_cell_value($value);
                }
                $put_csv_excel($file, $formatted_row);
                $rows_written++;
            }
        }
        
        fclose($file);
        
        error_log('CP Export: Archivo CSV base creado con ' . $rows_written . ' filas');
        
        // Verificar que el archivo se creó y tiene contenido
        if (!file_exists($filepath)) {
            error_log('CP Export: Error - El archivo no se creó');
            throw new Exception('El archivo no se creó correctamente');
        }
        
        $file_size = filesize($filepath);
        error_log('CP Export: Tamaño del archivo: ' . $file_size . ' bytes');
        
        if ($file_size === 0) {
            error_log('CP Export: Error - El archivo está vacío');
            throw new Exception('El archivo generado está vacío');
        }
        
        // Cambiar extensión a .xlsx para que Excel lo reconozca mejor
        $new_filepath = $filepath;//str_replace('.csv', '.xlsx', $filepath);
        if ($filepath !== $new_filepath) {
            if (rename($filepath, $new_filepath)) {
                error_log('CP Export: Archivo renombrado de .csv a .xlsx');
            } else {
                error_log('CP Export: Warning - No se pudo renombrar a .xlsx, manteniendo .csv');
            }
        }
        
        error_log('CP Export: export_to_excel completada exitosamente');
        return true;
    }
    
    /**
     * Formatear nombre de header para mejor legibilidad
     */
    private function format_header_name($header) {
        // Convertir snake_case a Title Case
        $formatted = str_replace('_', ' ', $header);
        $formatted = ucwords(strtolower($formatted));
        
        // Mapeo de nombres específicos
        $mappings = array(
            'Id' => 'ID',
            'Nit' => 'NIT',
            'Url' => 'URL',
            'Num Fila' => 'Núm. Fila',
            'Fecha Inicio' => 'Fecha de Inicio',
            'Fecha Fin' => 'Fecha de Fin',
            'Numero Documento' => 'Número de Documento'
        );
        
        return $mappings[$formatted] ?? $formatted;
    }
    
    /**
     * Formatear valor de celda para Excel
     */
    private function format_cell_value($value) {
        if (is_null($value)) {
            return '';
        } elseif (is_bool($value)) {
            return $value ? 'SÍ' : 'NO';
        } elseif (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        } elseif (is_string($value)) {
            // Limpiar caracteres problemáticos
            $value = trim($value);
            
            // Limitar longitud máxima por celda (Excel tiene límites)
            if (strlen($value) > 32767) {
                $value = substr($value, 0, 32760) . '...';
            }
            
            return $value;
        } else {
            return (string) $value;
        }
    }
    
    /**
     * Exportar a JSON
     */
    private function export_to_json($data, $filepath) {
        $json_data = array(
            'exported_at' => current_time('mysql'),
            'plugin_version' => defined('CP_PLUGIN_VERSION') ? CP_PLUGIN_VERSION : '1.0.0',
            'total_records' => count($data),
            'data' => $data
        );
        
        $json_content = wp_json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if ($json_content === false) {
            throw new Exception('Error al generar JSON');
        }
        
        return file_put_contents($filepath, $json_content) !== false;
    }
    
    /**
     * Exportar a XML
     */
    private function export_to_xml($data, $filepath) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><export></export>');
        
        // Metadatos
        $metadata = $xml->addChild('metadata');
        $metadata->addChild('exported_at', current_time('mysql'));
        $metadata->addChild('plugin_version', defined('CP_PLUGIN_VERSION') ? CP_PLUGIN_VERSION : '1.0.0');
        $metadata->addChild('total_records', count($data));
        
        // Datos
        $records = $xml->addChild('records');
        
        foreach ($data as $index => $row) {
            $record = $records->addChild('record');
            $record->addAttribute('index', $index);
            
            if (is_object($row)) {
                $row = get_object_vars($row);
            }
            
            foreach ($row as $key => $value) {
                $element_name = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
                $element_name = preg_replace('/^[^a-zA-Z_]/', '_', $element_name);
                
                if (is_null($value)) {
                    $element = $record->addChild($element_name);
                    $element->addAttribute('null', 'true');
                } else {
                    $record->addChild($element_name, htmlspecialchars((string)$value));
                }
            }
        }
        
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        
        return file_put_contents($filepath, $dom->saveXML()) !== false;
    }
    
    /**
     * Crear token de descarga temporal
     */
    private function create_download_token($filename) {
        $token = wp_generate_password(32, false);
        
        $download_data = array(
            'filename' => $filename,
            'user_id' => get_current_user_id(),
            'created_at' => time(),
            'user_ip' => $this->get_client_ip()
        );
        
        $transient_key = 'cp_download_' . $token;
        $result = set_transient($transient_key, $download_data, 3600);
        
        error_log('CP Export: Token creado - ' . $token . ' (' . ($result ? 'OK' : 'FAILED') . ')');
        
        return $token;
    }

    /**
     * Obtener IP del cliente
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Obtener URL de descarga
     */
    private function get_download_url($token) {
        return admin_url('admin-ajax.php?action=cp_download_export&token=' . $token);
    }
    
    /**
     * AJAX: Exportar datos
     */
    public function ajax_export_data() {
        // Esta función es para el admin, no para el frontend
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        wp_send_json_error(array('message' => 'Función no implementada'));
    }
    
    /**
     * AJAX: Descargar archivo exportado
     */
    public function ajax_download_export() {
        error_log('CP Export: ajax_download_export iniciada');
        error_log('CP Export: GET params: ' . print_r($_GET, true));
        
        // Obtener token
        $token = sanitize_text_field($_GET['token'] ?? '');
        
        if (empty($token)) {
            error_log('CP Export: Error - Token vacío');
            wp_die('Token requerido', 'Error', array('response' => 400));
        }
        
        // Obtener datos del transient
        $transient_key = 'cp_download_' . $token;
        $download_data = get_transient($transient_key);
        
        error_log('CP Export: Buscando transient: ' . $transient_key);
        error_log('CP Export: Datos encontrados: ' . print_r($download_data, true));
        
        if (!$download_data || !is_array($download_data)) {
            error_log('CP Export: Error - Token inválido o datos no encontrados');
            wp_die('Token inválido o expirado', 'Error', array('response' => 400));
        }
        
        // Construir ruta del archivo
        $filename = $download_data['filename'] ?? '';
        if (empty($filename)) {
            error_log('CP Export: Error - Filename vacío en datos');
            wp_die('Archivo no válido', 'Error', array('response' => 400));
        }
        
        $filepath = $this->temp_dir . $filename;
        error_log('CP Export: Buscando archivo en: ' . $filepath);
        
        if (!file_exists($filepath)) {
            error_log('CP Export: Error - Archivo no encontrado: ' . $filepath);
            
            // Listar archivos en el directorio para debug
            if (is_dir($this->temp_dir)) {
                $files = scandir($this->temp_dir);
                error_log('CP Export: Archivos en directorio: ' . print_r($files, true));
            } else {
                error_log('CP Export: Directorio no existe: ' . $this->temp_dir);
            }
            
            wp_die('Archivo no encontrado', 'Error', array('response' => 404));
        }
        
        $file_size = filesize($filepath);
        if ($file_size === 0) {
            error_log('CP Export: Error - Archivo vacío');
            wp_die('Archivo vacío', 'Error', array('response' => 400));
        }
        
        error_log('CP Export: Preparando descarga - ' . $filename . ' (' . $file_size . ' bytes)');
        
        // Eliminar token
        delete_transient($transient_key);
        
        // Limpiar output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Determinar content type y headers específicos
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        if ($extension === 'xlsx') {
            // Headers específicos para archivos Excel reales (.xlsx)
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
            header('X-Content-Type-Options: nosniff');
            header('Content-Transfer-Encoding: binary');
        } elseif ($extension === 'csv') {
            // Headers para CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        } else {
            // Headers genéricos para otros formatos
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        }
        
        header('Content-Length: ' . $file_size);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Enviar archivo
        readfile($filepath);
        
        // Limpiar archivo temporal
        unlink($filepath);
        
        error_log('CP Export: Descarga completada y archivo eliminado');
        exit();
    }
    
    /**
     * Enviar archivo para descarga
     */
    private function send_file_download($filepath, $filename) {
        error_log('CP Export: Enviando archivo - ' . $filename);
        
        // Determinar content type
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $content_types = array(
            'csv' => 'text/csv',
            'json' => 'application/json',
            'xml' => 'application/xml'
        );
        
        $content_type = $content_types[$extension] ?? 'application/octet-stream';
        
        // Limpiar cualquier output previo
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Headers para descarga
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Enviar archivo
        readfile($filepath);
        
        // Eliminar archivo temporal
        unlink($filepath);
        
        error_log('CP Export: Archivo enviado y eliminado');
        exit();
    }
    
    /**
     * Formatear tamaño de archivo
     */
    private function format_file_size($bytes) {
        $units = array('B', 'KB', 'MB', 'GB');
        $factor = floor((strlen($bytes) - 1) / 3);
        
        return sprintf("%.2f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
    }
    
    /**
     * Limpiar archivos temporales antiguos
     */
    public function cleanup_temp_files() {
        if (!is_dir($this->temp_dir)) {
            return;
        }
        
        $files = glob($this->temp_dir . '*');
        $cutoff_time = time() - (24 * 3600); // 24 horas
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
    
    /**
     * Obtener estadísticas de exportación
     */
    public function get_export_stats() {
        $exports = get_option('cp_export_stats', array());
        
        $today = date('Y-m-d');
        $stats = array(
            'total_exports' => array_sum($exports),
            'today_exports' => $exports[$today] ?? 0,
            'formats_used' => get_option('cp_export_formats', array()),
            'last_export' => get_option('cp_last_export_date')
        );
        
        return $stats;
    }
    
    /**
     * Registrar exportación
     */
    public function record_export($format) {
        $exports = get_option('cp_export_stats', array());
        $today = date('Y-m-d');
        $exports[$today] = ($exports[$today] ?? 0) + 1;
        
        // Mantener solo los últimos 30 días
        $cutoff_date = date('Y-m-d', strtotime('-30 days'));
        $exports = array_filter($exports, function($date) use ($cutoff_date) {
            return $date >= $cutoff_date;
        }, ARRAY_FILTER_USE_KEY);
        
        update_option('cp_export_stats', $exports);
        update_option('cp_last_export_date', current_time('mysql'));
    }
    
    /**
     * Validar datos antes de exportar
     */
    public function validate_export_data($data, $max_records = 10000) {
        if (empty($data)) {
            return array(
                'valid' => false,
                'error' => __('No hay datos para exportar.', 'consulta-procesos')
            );
        }
        
        if (count($data) > $max_records) {
            return array(
                'valid' => false,
                'error' => sprintf(
                    __('Demasiados registros para exportar. Máximo permitido: %d', 'consulta-procesos'),
                    $max_records
                )
            );
        }
        
        return array('valid' => true);
    }

    /**
     * Cargar SimpleXLSXGen si está disponible
     */
    private function load_simplexlsxgen() {
        // Obtener la ruta del plugin de manera dinámica
        $plugin_dir = plugin_dir_path(dirname(__FILE__)); // Esto nos da la ruta del plugin
        $lib_path = $plugin_dir . 'lib/SimpleXLSXGen.php';
        
        error_log('CP Export: Intentando cargar SimpleXLSXGen desde: ' . $lib_path);
        error_log('CP Export: Plugin dir es: ' . $plugin_dir);
        error_log('CP Export: Archivo existe: ' . (file_exists($lib_path) ? 'SÍ' : 'NO'));
        
        if (file_exists($lib_path)) {
            require_once $lib_path;
            error_log('CP Export: SimpleXLSXGen cargado desde: ' . $lib_path);
            
            // Verificar diferentes posibles nombres de clase
            $class_variations = array(
                'Shuchkin\SimpleXLSXGen',
                'SimpleXLSXGen', 
                '\SimpleXLSXGen'
            );
            
            $class_found = false;
            foreach ($class_variations as $class_name) {
                if (class_exists($class_name)) {
                    error_log('CP Export: ✅ Clase encontrada: ' . $class_name);
                    $class_found = true;
                    break;
                }
            }
            
            if (!$class_found) {
                error_log('CP Export: ❌ Ninguna variación de clase SimpleXLSXGen encontrada');
                // Mostrar todas las clases disponibles para debug
                $all_classes = get_declared_classes();
                $xlsx_classes = array_filter($all_classes, function($class) {
                    return stripos($class, 'xlsx') !== false || stripos($class, 'shuchkin') !== false;
                });
                error_log('CP Export: Clases Excel/Shuchkin disponibles: ' . implode(', ', $xlsx_classes));
            } else {
                error_log('CP Export: ✅ SimpleXLSXGen está disponible y listo para usar');
            }
        } else {
            error_log('CP Export: ❌ SimpleXLSXGen no encontrado en: ' . $lib_path);
            
            // Verificar estructura de directorios
            $plugin_dir_contents = is_dir($plugin_dir) ? scandir($plugin_dir) : array();
            error_log('CP Export: Contenido del plugin dir: ' . implode(', ', $plugin_dir_contents));
            
            $lib_dir = $plugin_dir . 'lib/';
            if (is_dir($lib_dir)) {
                $lib_contents = scandir($lib_dir);
                error_log('CP Export: Contenido de lib/: ' . implode(', ', $lib_contents));
            } else {
                error_log('CP Export: Directorio lib/ no existe en: ' . $lib_dir);
            }
            
            error_log('CP Export: Se usará fallback a CSV cuando se exporte a Excel');
        }
    }

    /**
     * Exportar usando SimpleXLSXGen
     */
    private function export_to_excel_simplexlsxgen($data, $filepath, $headers = null) {
        error_log('CP Export: export_to_excel_simplexlsxgen iniciada - ' . count($data) . ' registros');
        
        // Verificar diferentes variaciones de la clase
        $xlsx_class = null;
        $class_variations = array(
            'Shuchkin\SimpleXLSXGen',
            'SimpleXLSXGen', 
            '\SimpleXLSXGen'
        );
        
        foreach ($class_variations as $class_name) {
            if (class_exists($class_name)) {
                $xlsx_class = $class_name;
                error_log('CP Export: Usando clase: ' . $class_name);
                break;
            }
        }
        
        if (!$xlsx_class) {
            error_log('CP Export: SimpleXLSXGen no disponible, usando fallback CSV');
            // Cambiar extensión y usar CSV como fallback
            $csv_filepath = str_replace('.xlsx', '.csv', $filepath);
            $result = $this->export_to_excel_csv($data, $csv_filepath, $headers);
            if ($result && file_exists($csv_filepath)) {
                // Mover archivo CSV al path original para mantener el nombre esperado
                rename($csv_filepath, $filepath);
            }
            return $result;
        }
        
        try {
            // Preparar datos para SimpleXLSXGen - FORMATO CORRECTO
            $excel_data = array();
            
            // Agregar headers
            if ($headers && is_array($headers)) {
                $excel_data[] = $headers;
            } elseif (!empty($data)) {
                $first_row = is_object($data[0]) ? get_object_vars($data[0]) : $data[0];
                if (is_array($first_row)) {
                    $header_row = array();
                    foreach (array_keys($first_row) as $key) {
                        $header_row[] = $this->format_header_name($key);
                    }
                    $excel_data[] = $header_row;
                }
            }
            
            // Agregar datos procesados
            foreach ($data as $row) {
                if (is_object($row)) {
                    $row = get_object_vars($row);
                }
                
                if (is_array($row)) {
                    $clean_row = array();
                    foreach ($row as $key => $value) {
                        $clean_row[] = $this->format_cell_value_for_simplexlsxgen($value, $key);
                    }
                    $excel_data[] = $clean_row;
                }
            }
            
            error_log('CP Export: Datos preparados para SimpleXLSXGen - ' . count($excel_data) . ' filas');
            
            // Crear archivo Excel con SimpleXLSXGen - MÉTODO CORRECTO
            if ($xlsx_class === 'Shuchkin\SimpleXLSXGen') {
                $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($excel_data);
            } else {
                $xlsx = $xlsx_class::fromArray($excel_data);
            }
            
            if (!$xlsx) {
                throw new Exception('No se pudo crear objeto SimpleXLSXGen');
            }
            
            error_log('CP Export: Objeto SimpleXLSXGen creado exitosamente');
            
            // Guardar archivo - MÉTODO SIMPLIFICADO
            $success = $xlsx->saveAs($filepath);
            
            if (!$success) {
                throw new Exception('SimpleXLSXGen::saveAs() retornó false');
            }
            
            // Verificar que el archivo se creó y tiene contenido
            if (!file_exists($filepath)) {
                throw new Exception('El archivo no se creó en el sistema de archivos');
            }
            
            $file_size = filesize($filepath);
            if ($file_size === 0) {
                throw new Exception('El archivo se creó pero está vacío (0 bytes)');
            }
            
            // Verificar que es un archivo Excel válido (debe tener al menos 1KB)
            if ($file_size < 1024) {
                error_log('CP Export: ADVERTENCIA - Archivo muy pequeño (' . $file_size . ' bytes), puede estar corrupto');
            }
            
            error_log('CP Export: ✅ SimpleXLSXGen archivo Excel (.xlsx) creado exitosamente: ' . $filepath . ' (' . $file_size . ' bytes)');
            return true;
            
        } catch (Exception $e) {
            error_log('CP Export: ❌ Error con SimpleXLSXGen: ' . $e->getMessage());
            error_log('CP Export: Stack trace: ' . $e->getTraceAsString());
            
            // Fallback a CSV en caso de error
            error_log('CP Export: Usando fallback a CSV...');
            $csv_filepath = str_replace('.xlsx', '.csv', $filepath);
            $result = $this->export_to_excel_csv($data, $csv_filepath, $headers);
            if ($result && file_exists($csv_filepath)) {
                rename($csv_filepath, $filepath);
            }
            return $result;
        }
    }

    /**
     * Formatear valor de celda específicamente para SimpleXLSXGen
     */
    private function format_cell_value_for_simplexlsxgen($value, $field_name = '') {
        if (is_null($value)) {
            return '';
        } elseif (is_bool($value)) {
            return $value ? 'SÍ' : 'NO';
        } elseif (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        } elseif (is_numeric($value)) {
            // Para números muy largos (como NITs), preservar como string
            if (strlen((string)$value) > 10 && ctype_digit((string)$value)) {
                return (string)$value; // SimpleXLSXGen maneja esto automáticamente
            }
            return $value;
        } else {
            // Limpiar string
            $str = trim((string) $value);
            
            // Reemplazar saltos de línea problemáticos para Excel
            $str = str_replace(array("\r\n", "\r", "\n"), ' | ', $str);
            
            // Reemplazar tabulaciones
            $str = str_replace("\t", ' ', $str);
            
            // Limpiar caracteres de control
            $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $str);
            
            // Limitar longitud para Excel (SimpleXLSXGen maneja esto, pero es buena práctica)
            if (strlen($str) > 32000) {
                $str = substr($str, 0, 31990) . '... (truncado)';
            }
            
            return $str;
        }
    }

    /**
     * Verificar si SimpleXLSXGen está disponible (método público para debugging)
     */
    public function is_simplexlsxgen_available() {
        $class_variations = array(
            'Shuchkin\SimpleXLSXGen',
            'SimpleXLSXGen', 
            '\SimpleXLSXGen'
        );
        
        foreach ($class_variations as $class_name) {
            if (class_exists($class_name)) {
                return $class_name; // Retornar el nombre de la clase encontrada
            }
        }
        
        return false;
    }

    /**
     * Obtener información de SimpleXLSXGen para debugging
     */
    public function get_simplexlsxgen_info() {
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        $lib_path = $plugin_dir . 'lib/SimpleXLSXGen.php';
        
        $info = array(
            'plugin_dir' => $plugin_dir,
            'lib_path' => $lib_path,
            'file_exists' => file_exists($lib_path),
            'file_size' => file_exists($lib_path) ? filesize($lib_path) : 0,
            'file_readable' => file_exists($lib_path) ? is_readable($lib_path) : false,
            'classes_available' => array(),
            'class_found' => false
        );
        
        // Verificar diferentes variaciones de clase
        $class_variations = array(
            'Shuchkin\SimpleXLSXGen',
            'SimpleXLSXGen', 
            '\SimpleXLSXGen'
        );
        
        foreach ($class_variations as $class_name) {
            $exists = class_exists($class_name);
            $info['classes_available'][$class_name] = $exists;
            if ($exists && !$info['class_found']) {
                $info['class_found'] = $class_name;
            }
        }
        
        return $info;
    }

    /**
     * Cargar dompdf si está disponible
     */
    private function load_dompdf() {
        $dompdf_path = CP_PLUGIN_PATH . 'lib/dompdf/autoload.inc.php';
        
        error_log('CP Export: Intentando cargar dompdf desde: ' . $dompdf_path);
        
        if (file_exists($dompdf_path)) {
            require_once $dompdf_path;
            error_log('CP Export: dompdf cargado exitosamente');
            return true;
        } else {
            error_log('CP Export: dompdf no encontrado en: ' . $dompdf_path);
            return false;
        }
    }
    
    /**
     * Verificar si dompdf está disponible
     */
    public function is_dompdf_available() {
        if (!$this->load_dompdf()) {
            return false;
        }
        
        return class_exists('Dompdf\Dompdf');
    }
    
    /**
     * Exportar PDF sin resultados
     */
    public function export_no_results_pdf($search_params) {
        error_log('CP Export: export_no_results_pdf iniciada');
        
        if (!$this->load_dompdf()) {
            return array(
                'success' => false,
                'error' => 'dompdf no está disponible'
            );
        }
        
        if (!class_exists('Dompdf\Dompdf')) {
            return array(
                'success' => false,
                'error' => 'Clase Dompdf no encontrada'
            );
        }
        
        try {
            // Generar nombre de archivo
            $filename = $this->generate_no_results_pdf_filename($search_params);
            $filepath = $this->temp_dir . $filename;
            
            // Generar HTML del PDF
            $html = $this->generate_no_results_pdf_html($search_params);
            
            // Crear instancia de dompdf
            $dompdf = new \Dompdf\Dompdf();
            
            // Configurar opciones
            $options = $dompdf->getOptions();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $dompdf->setOptions($options);
            
            // Cargar HTML
            $dompdf->loadHtml($html);
            
            // Configurar tamaño de papel
            $dompdf->setPaper('A4', 'portrait');
            
            // Renderizar PDF
            $dompdf->render();
            
            // Obtener contenido del PDF
            $pdf_content = $dompdf->output();
            
            // Guardar archivo
            $result = file_put_contents($filepath, $pdf_content);
            
            if ($result === false) {
                throw new Exception('No se pudo guardar el archivo PDF');
            }
            
            // Verificar que el archivo se creó
            if (!file_exists($filepath)) {
                throw new Exception('El archivo PDF no se creó correctamente');
            }
            
            $file_size = filesize($filepath);
            if ($file_size === 0) {
                throw new Exception('El archivo PDF generado está vacío');
            }
            
            // Crear token de descarga
            $download_token = $this->create_download_token($filename);
            
            error_log('CP Export: PDF sin resultados creado exitosamente - ' . $filename . ' (' . $file_size . ' bytes)');
            
            return array(
                'success' => true,
                'filename' => $filename,
                'download_token' => $download_token,
                'download_url' => $this->get_download_url($download_token),
                'file_size' => $this->format_file_size($file_size),
                'document_type' => 'PDF'
            );
            
        } catch (Exception $e) {
            error_log('CP Export: Error generando PDF sin resultados - ' . $e->getMessage());
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Generar nombre de archivo para PDF sin resultados
     */
    private function generate_no_results_pdf_filename($search_params) {
        $timestamp = date('Y-m-d_H-i-s');
        $profile_str = isset($search_params['profile_type']) ? ucfirst($search_params['profile_type']) : 'Consulta';
        
        $documento = '';
        if (!empty($search_params['numero_documento'])) {
            $documento = '_Doc-' . substr($search_params['numero_documento'], 0, 8);
        }
        
        $fecha_range = '';
        if (!empty($search_params['fecha_inicio']) && !empty($search_params['fecha_fin'])) {
            $fecha_range = '_' . $search_params['fecha_inicio'] . '_a_' . $search_params['fecha_fin'];
        }
        
        return "SinResultados_ConsultaProcesos_{$profile_str}{$documento}{$fecha_range}_{$timestamp}.pdf";
    }
    
    /**
     * Generar HTML para PDF sin resultados
     */
    private function generate_no_results_pdf_html($search_params) {
        $numero_documento = $search_params['numero_documento'] ?? '';
        $fecha_inicio = $search_params['fecha_inicio'] ?? '';
        $fecha_fin = $search_params['fecha_fin'] ?? '';
        $profile_type = $search_params['profile_type'] ?? '';
        
        // Formatear fechas para mostrar
        $fecha_inicio_display = !empty($fecha_inicio) ? date('Y-m-d', strtotime($fecha_inicio)) : 'N/A';
        $fecha_fin_display = !empty($fecha_fin) ? date('Y-m-d', strtotime($fecha_fin)) : 'N/A';
        $fecha_consulta = date('Y-m-d H:i');
        
        // Determinar tipo de identificación según el perfil
        $tipo_identificacion = ($profile_type === 'entidades') ? 'NIT' : 'Documento';

        // Obtener logo
        $logo_id = get_theme_mod('custom_logo');
        $logo_path = get_attached_file($logo_id);

        if ($logo_path){
            $base64 = base64_encode(file_get_contents($logo_path));
            $mime = mime_content_type($logo_path);
        }

        $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Consulta de Procesos - Sin Resultados</title>
    <style>
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2c5a84;
            padding-bottom: 20px;
        }
        
        .logo-section {
            margin-bottom: 15px;
        }
        
        .title {
            font-size: 14px;
            font-weight: bold;
            color: #2c5a84;
            margin-bottom: 10px;
            line-height: 1.3;
        }
        
        .subtitle {
            font-size: 12px;
            font-weight: bold;
            color: #2c5a84;
            margin-bottom: 20px;
        }
        
        .search-criteria {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .search-criteria h3 {
            font-size: 12px;
            margin: 0 0 10px 0;
            color: #2c5a84;
        }
        
        .criteria-item {
            margin-bottom: 5px;
        }
        
        .no-results {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-left: 4px solid #f39c12;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
            border-radius: 5px;
        }
        
        .no-results h2 {
            color: #856404;
            font-size: 14px;
            margin: 0 0 10px 0;
        }
        
        .no-results p {
            color: #856404;
            margin: 5px 0;
            font-size: 11px;
        }
        
        .legal-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 9px;
            line-height: 1.3;
        }
        
        .legal-info h4 {
            font-size: 10px;
            margin: 15px 0 8px 0;
            color: #2c5a84;
        }
        
        .legal-info p {
            margin: 5px 0;
            text-align: justify;
        }
        
        .legal-info ol {
            padding-left: 15px;
        }
        
        .legal-info li {
            margin-bottom: 8px;
            text-align: justify;
        }
        
        .footer {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            text-align: center;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #dee2e6;
            padding-top: 10px;
        }
        
        .highlight {
            background: #e7f3ff;
            padding: 2px 4px;
            border-radius: 3px;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-justify {
            text-align: justify;
        }
        
        .mb-10 {
            margin-bottom: 10px;
        }
        
        .mb-15 {
            margin-bottom: 15px;
        }
        
        .generated-info {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 5px;
            padding: 10px;
            margin-top: 20px;
            font-size: 10px;
        }
        
        .generated-info strong {
            color: #004085;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-section">
            <div class="cp-radio-icon">
                <img src="data:' . $mime . ';base64,' . $base64 . '" style="height:80px; display:block; margin:auto;" />
                <p>' . esc_url($logo_url) . ' </p>
            </div>
        </div>
        
        <div class="title">
            Consulta en línea de contratos suscritos en el<br>
            Sistema Electrónico de Contratación Pública
        </div>
        
        <div class="subtitle">
            LA AGENCIA NACIONAL DE CONTRATACIÓN PÚBLICA<br>
            COLOMBIA COMPRA EFICIENTE – ANCP - CCE
        </div>
    </div>
    
    <div class="search-criteria">
        <h3>CRITERIOS DE BÚSQUEDA UTILIZADOS:</h3>
        <div class="criteria-item"><strong>' . $tipo_identificacion . ':</strong> <span class="highlight">' . esc_html($numero_documento) . '</span></div>
        <div class="criteria-item"><strong>Fecha de inicio búsqueda:</strong> <span class="highlight">' . esc_html($fecha_inicio_display) . '</span></div>
        <div class="criteria-item"><strong>Fecha de fin búsqueda:</strong> <span class="highlight">' . esc_html($fecha_fin_display) . '</span></div>
        <div class="criteria-item"><strong>Fecha de la consulta:</strong> <span class="highlight">' . esc_html($fecha_consulta) . '</span></div>
        <div class="criteria-item"><strong>Tipo de consulta:</strong> <span class="highlight">' . esc_html(ucfirst($profile_type)) . '</span></div>
    </div>
    
    <div class="no-results">
        <h2>🔍 NO SE ENCONTRARON RESULTADOS</h2>
        <p><strong>No se encontraron resultados de procesos de contratación para los criterios de búsqueda especificados.</strong></p>
        <p>Se consultaron las siguientes fuentes de información:</p>
        <ul style="text-align: left; display: inline-block; margin: 10px 0;">
            <li>SECOPI - Sistema Electrónico de Contratación Pública</li>
            <li>SECOPII - Sistema Extendido de Contratación Pública</li>
            <li>TVEC - Tienda Virtual del Estado Colombiano</li>
        </ul>
    </div>
    
    <div class="legal-info">
        <p class="text-justify">
            Es importante resaltar que de acuerdo con el <strong>artículo 3 de la Ley 1712 de 2014</strong>, sobre el 
            principio de calidad de la información, las Entidades Estatales son responsables de la 
            oportunidad, objetividad y veracidad de la información que publican.
        </p>
        
        <p class="text-justify mb-15">
            Finalmente, se le informa que los procesos de contratación y los Planes Anuales de Adquisiciones 
            adelantados por las Entidades Estatales pueden ser consultados en cualquier momento a través 
            de la plataforma de Datos Abiertos del Estado Colombiano (<strong>www.datos.gov.co</strong>), los cuales son 
            actualizados diariamente.
        </p>
        
        <h4>** LA AGENCIA NACIONAL DE CONTRATACIÓN PÚBLICA -COLOMBIA COMPRA EFICIENTE- INFORMA:</h4>
        
        <ol>
            <li class="text-justify">
                La calidad de la información del SECOP es responsabilidad de las Entidades Estatales y por lo tanto las 
                inconsistencias encontradas o la ausencia de información es responsabilidad de estas.
            </li>
            
            <li class="text-justify">
                La información de los Procesos de Contratación comprendida en el Sistema Electrónico de Contratación 
                Pública (SECOP) es publicada por las Entidades Estatales y puede contener errores de digitación en la 
                identificación del contratista (cédula de ciudadanía o cédula de extranjería), nombre, objeto, valor del 
                contrato, lugar de ejecución; o puede presentar inconsistencias, por duplicidad de nombre, número de 
                identificación o ausencia de datos.
            </li>
            
            <li class="text-justify">
                Colombia Compra Eficiente no es responsable por la información publicada en el SECOP si requiere 
                información o Documentos del Proceso debe solicitarlos a la Entidad Estatal que adelantó el Proceso 
                de Contratación respectivo.
            </li>
            
            <li class="text-justify">
                Colombia Compra Eficiente en línea con la política pública de datos abiertos de Colombia liderada por el 
                Ministerio de las Tecnologías de la Información y las Comunicaciones (MinTic) y con la iniciativa de la 
                Alianza para las Contrataciones Abiertas (Open Contracting Partnership) publica los datos consolidados 
                del Sistema Electrónico para la Contratación Pública -SECOP a través del portal de Datos Abiertos 
                disponible en: <strong>https://www.datos.gov.co/</strong>
            </li>
        </ol>
    </div>
    
    <div class="generated-info">
        <p class="text-center">
            <strong>Documento generado automáticamente por el Sistema de Consulta de Procesos</strong><br>
            Fecha de generación: ' . date('Y-m-d H:i:s') . '<br>
            Este documento certifica que se realizó una búsqueda exhaustiva en las bases de datos oficiales.
        </p>
    </div>
    
    <div class="footer">
        <p>
            <strong>Agencia Nacional de Contratación Pública - Colombia Compra Eficiente</strong><br>
            Tel. (601)7956600 • Carrera 7 No. 26 – 20 Piso 17 • Bogotá - Colombia<br>
            <strong>www.colombiacompra.gov.co</strong>
        </p>
    </div>
</body>
</html>';
        
        return $html;
    }
}