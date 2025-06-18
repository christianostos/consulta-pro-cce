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
        $this->temp_dir = wp_upload_dir()['basedir'] . '/cp-exports/';
        $this->init_hooks();
        $this->create_temp_directory();
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_cp_export_data', array($this, 'ajax_export_data'));
        add_action('wp_ajax_cp_download_export', array($this, 'ajax_download_export'));
        
        // Limpiar archivos temporales diariamente
        add_action('cp_cleanup_temp_files', array($this, 'cleanup_temp_files'));
        if (!wp_next_scheduled('cp_cleanup_temp_files')) {
            wp_schedule_event(time(), 'daily', 'cp_cleanup_temp_files');
        }
    }
    
    /**
     * Crear directorio temporal
     */
    private function create_temp_directory() {
        if (!file_exists($this->temp_dir)) {
            wp_mkdir_p($this->temp_dir);
            
            // Crear archivo .htaccess para proteger el directorio
            $htaccess_content = "deny from all\n";
            file_put_contents($this->temp_dir . '.htaccess', $htaccess_content);
        }
    }
    
    /**
     * Exportar datos en formato especificado
     */
    public function export_data($data, $format = 'csv', $filename = null, $headers = null) {
        if (!in_array($format, $this->allowed_formats)) {
            return array(
                'success' => false,
                'error' => __('Formato de exportación no válido.', 'consulta-procesos')
            );
        }
        
        if (empty($data)) {
            return array(
                'success' => false,
                'error' => __('No hay datos para exportar.', 'consulta-procesos')
            );
        }
        
        // Generar nombre de archivo si no se proporciona
        if (!$filename) {
            $filename = CP_Utils::generate_unique_filename('export', $format);
        }
        
        $filepath = $this->temp_dir . $filename;
        
        try {
            switch ($format) {
                case 'csv':
                    $result = $this->export_to_csv($data, $filepath, $headers);
                    break;
                    
                case 'excel':
                    $result = $this->export_to_excel($data, $filepath, $headers);
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
                // Crear token de descarga temporal
                $download_token = $this->create_download_token($filename);
                
                return array(
                    'success' => true,
                    'filename' => $filename,
                    'download_token' => $download_token,
                    'download_url' => $this->get_download_url($download_token),
                    'file_size' => $this->format_file_size(filesize($filepath)),
                    'records_count' => count($data)
                );
            } else {
                return array(
                    'success' => false,
                    'error' => __('Error al generar el archivo de exportación.', 'consulta-procesos')
                );
            }
            
        } catch (Exception $e) {
            CP_Utils::log('Error en exportación: ' . $e->getMessage(), 'error');
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Exportar a CSV
     */
    private function export_to_csv($data, $filepath, $headers = null) {
        $file = fopen($filepath, 'w');
        
        if (!$file) {
            throw new Exception('No se pudo crear el archivo CSV');
        }
        
        // BOM para UTF-8
        fwrite($file, "\xEF\xBB\xBF");
        
        // Escribir headers
        if ($headers) {
            fputcsv($file, $headers);
        } elseif (!empty($data) && is_array($data[0])) {
            // Usar keys del primer registro como headers
            $first_row = is_object($data[0]) ? get_object_vars($data[0]) : $data[0];
            fputcsv($file, array_keys($first_row));
        }
        
        // Escribir datos
        foreach ($data as $row) {
            if (is_object($row)) {
                $row = get_object_vars($row);
            }
            
            // Convertir valores a string y manejar valores nulos
            $row = array_map(function($value) {
                if (is_null($value)) {
                    return '';
                } elseif (is_bool($value)) {
                    return $value ? 'true' : 'false';
                } elseif (is_array($value) || is_object($value)) {
                    return json_encode($value);
                } else {
                    return (string) $value;
                }
            }, $row);
            
            fputcsv($file, $row);
        }
        
        fclose($file);
        return true;
    }
    
    /**
     * Exportar a Excel (usando formato CSV con configuración para Excel)
     */
    private function export_to_excel($data, $filepath, $headers = null) {
        // Crear archivo Excel usando formato CSV con configuración específica para Excel
        $file = fopen($filepath, 'w');
        
        if (!$file) {
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
        
        // Escribir headers personalizados o automáticos
        if ($headers) {
            $put_csv_excel($file, $headers);
        } elseif (!empty($data)) {
            $first_row = is_object($data[0]) ? get_object_vars($data[0]) : $data[0];
            if (is_array($first_row)) {
                // Crear headers más legibles
                $readable_headers = array();
                foreach (array_keys($first_row) as $key) {
                    $readable_headers[] = $this->format_header_name($key);
                }
                $put_csv_excel($file, $readable_headers);
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
            }
        }
        
        fclose($file);
        
        // Cambiar extensión a .xlsx para que Excel lo reconozca mejor
        $new_filepath = str_replace('.csv', '.xlsx', $filepath);
        if ($filepath !== $new_filepath) {
            rename($filepath, $new_filepath);
        }
        
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
            'plugin_version' => CP_PLUGIN_VERSION,
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
        $metadata->addChild('plugin_version', CP_PLUGIN_VERSION);
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
                // Limpiar nombre de elemento XML
                $element_name = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
                $element_name = preg_replace('/^[^a-zA-Z_]/', '_', $element_name);
                
                if (is_null($value)) {
                    $element = $record->addChild($element_name);
                    $element->addAttribute('null', 'true');
                } elseif (is_bool($value)) {
                    $record->addChild($element_name, $value ? 'true' : 'false');
                } elseif (is_array($value) || is_object($value)) {
                    $record->addChild($element_name, htmlspecialchars(json_encode($value)));
                } else {
                    $record->addChild($element_name, htmlspecialchars($value));
                }
            }
        }
        
        // Formatear XML
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        
        return file_put_contents($filepath, $dom->saveXML()) !== false;
    }
    
    /**
     * Crear token de descarga temporal
     */
    private function create_download_token($filename) {
        $token = CP_Utils::generate_secure_token();
        
        set_transient('cp_download_' . $token, array(
            'filename' => $filename,
            'user_id' => get_current_user_id(),
            'created_at' => time()
        ), 3600); // 1 hora de validez
        
        return $token;
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
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        
        check_ajax_referer('cp_nonce', 'nonce');
        
        // Obtener parámetros
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $query_id = intval($_POST['query_id'] ?? 0);
        
        // Por ahora, datos de ejemplo
        // En implementación real, estos vendrían de la consulta ejecutada
        $sample_data = array(
            array('id' => 1, 'nombre' => 'Juan Pérez', 'email' => 'juan@email.com', 'fecha' => '2024-01-15'),
            array('id' => 2, 'nombre' => 'María García', 'email' => 'maria@email.com', 'fecha' => '2024-01-16'),
            array('id' => 3, 'nombre' => 'Carlos López', 'email' => 'carlos@email.com', 'fecha' => '2024-01-17')
        );
        
        $result = $this->export_data($sample_data, $format);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Descargar archivo exportado
     */
    public function ajax_download_export() {
        $token = sanitize_text_field($_GET['token'] ?? '');
        
        if (empty($token)) {
            wp_die('Token requerido');
        }
        
        $download_data = get_transient('cp_download_' . $token);
        
        if (!$download_data) {
            wp_die('Token inválido o expirado');
        }
        
        // Verificar que el usuario actual pueda descargar este archivo
        if ($download_data['user_id'] != get_current_user_id() && !current_user_can('manage_options')) {
            wp_die('No autorizado para descargar este archivo');
        }
        
        $filepath = $this->temp_dir . $download_data['filename'];
        
        if (!file_exists($filepath)) {
            wp_die('Archivo no encontrado');
        }
        
        // Eliminar el token después del uso
        delete_transient('cp_download_' . $token);
        
        // Preparar descarga
        $this->send_file_download($filepath, $download_data['filename']);
    }
    
    /**
     * Enviar archivo para descarga
     */
    private function send_file_download($filepath, $filename) {
        $extension = CP_Utils::get_file_extension($filename);
        
        // Determinar content type
        $content_types = array(
            'csv' => 'text/csv',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel'
        );
        
        $content_type = $content_types[$extension] ?? 'application/octet-stream';
        
        // Headers para descarga
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        // Limpiar buffer de salida
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Enviar archivo
        readfile($filepath);
        
        // Eliminar archivo temporal después de la descarga
        unlink($filepath);
        
        exit;
    }
    
    /**
     * Formatear tamaño de archivo
     */
    private function format_file_size($bytes) {
        return CP_Utils::format_bytes($bytes);
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
        
        CP_Utils::log('Limpieza de archivos temporales completada', 'info');
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
        // Estadísticas por día
        $exports = get_option('cp_export_stats', array());
        $today = date('Y-m-d');
        $exports[$today] = ($exports[$today] ?? 0) + 1;
        
        // Mantener solo los últimos 30 días
        $cutoff_date = date('Y-m-d', strtotime('-30 days'));
        $exports = array_filter($exports, function($date) use ($cutoff_date) {
            return $date >= $cutoff_date;
        }, ARRAY_FILTER_USE_KEY);
        
        update_option('cp_export_stats', $exports);
        
        // Estadísticas por formato
        $formats = get_option('cp_export_formats', array());
        $formats[$format] = ($formats[$format] ?? 0) + 1;
        update_option('cp_export_formats', $formats);
        
        // Última exportación
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
        
        // Verificar estructura de datos
        if (!is_array($data[0]) && !is_object($data[0])) {
            return array(
                'valid' => false,
                'error' => __('Formato de datos no válido para exportación.', 'consulta-procesos')
            );
        }
        
        return array('valid' => true);
    }
}