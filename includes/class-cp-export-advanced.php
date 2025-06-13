<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Sistema de exportación avanzado para resultados de búsqueda
 * Archivo: includes/class-cp-export-advanced.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_Export_Advanced {
    
    private static $instance = null;
    private $temp_dir;
    
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
        add_action('wp_ajax_cp_export_search_results', array($this, 'ajax_export_search_results'));
        add_action('wp_ajax_nopriv_cp_export_search_results', array($this, 'ajax_export_search_results'));
        add_action('wp_ajax_cp_download_export_file', array($this, 'ajax_download_export_file'));
        add_action('wp_ajax_nopriv_cp_download_export_file', array($this, 'ajax_download_export_file'));
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
     * AJAX: Exportar resultados de búsqueda
     */
    public function ajax_export_search_results() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cp_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Token de seguridad inválido'));
        }
        
        $search_id = sanitize_text_field($_POST['search_id'] ?? '');
        $format = sanitize_text_field($_POST['format'] ?? 'excel');
        
        if (empty($search_id)) {
            wp_send_json_error(array('message' => 'ID de búsqueda requerido'));
        }
        
        // Obtener resultados de búsqueda
        $results_data = $this->get_search_results_data($search_id);
        
        if (!$results_data) {
            wp_send_json_error(array('message' => 'Resultados no encontrados'));
        }
        
        try {
            if ($format === 'pdf') {
                $export_result = $this->export_to_pdf($results_data, $search_id);
            } else {
                $export_result = $this->export_to_excel($results_data, $search_id);
            }
            
            if ($export_result['success']) {
                wp_send_json_success($export_result);
            } else {
                wp_send_json_error($export_result);
            }
            
        } catch (Exception $e) {
            CP_Utils::log('Error en exportación: ' . $e->getMessage(), 'error');
            wp_send_json_error(array('message' => 'Error interno en la exportación'));
        }
    }
    
    /**
     * Obtener datos de resultados de búsqueda
     */
    private function get_search_results_data($search_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cp_search_progress';
        
        $search_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE search_id = %s AND status = 'completed'",
            $search_id
        ), ARRAY_A);
        
        if (!$search_data) {
            return false;
        }
        
        $results = json_decode($search_data['results_data'], true);
        
        if (empty($results)) {
            return false;
        }
        
        return array(
            'search_id' => $search_id,
            'profile_type' => $search_data['profile_type'],
            'fecha_inicio' => $search_data['fecha_inicio'],
            'fecha_fin' => $search_data['fecha_fin'],
            'numero_documento' => $search_data['numero_documento'],
            'total_records' => $search_data['total_records'],
            'results' => $results,
            'created_at' => $search_data['created_at']
        );
    }
    
    /**
     * Exportar a Excel usando PhpSpreadsheet
     */
    private function export_to_excel($data, $search_id) {
        // Verificar si PhpSpreadsheet está disponible
        if (!$this->is_phpspreadsheet_available()) {
            return $this->export_to_csv_as_excel($data, $search_id);
        }
        
        require_once $this->get_phpspreadsheet_path();
        
        $spreadsheet = new Spreadsheet();
        
        // Hoja de resumen
        $this->create_summary_sheet($spreadsheet, $data);
        
        // Hojas por fuente de datos
        $sheet_index = 1;
        foreach ($data['results'] as $source => $records) {
            if (!empty($records)) {
                $this->create_source_sheet($spreadsheet, $source, $records, $sheet_index);
                $sheet_index++;
            }
        }
        
        // Configurar hoja activa
        $spreadsheet->setActiveSheetIndex(0);
        
        // Generar archivo
        $filename = $this->generate_filename($search_id, 'xlsx');
        $filepath = $this->temp_dir . $filename;
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        
        // Crear token de descarga
        $download_token = $this->create_download_token($filename, $search_id);
        
        return array(
            'success' => true,
            'filename' => $filename,
            'download_token' => $download_token,
            'download_url' => $this->get_download_url($download_token),
            'file_size' => $this->format_file_size(filesize($filepath)),
            'records_count' => $data['total_records']
        );
    }
    
    /**
     * Crear hoja de resumen
     */
    private function create_summary_sheet($spreadsheet, $data) {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumen');
        
        // Título principal
        $sheet->setCellValue('A1', 'CONSULTA DE PROCESOS CONTRACTUALES');
        $sheet->mergeCells('A1:F1');
        
        // Información de búsqueda
        $row = 3;
        $sheet->setCellValue('A' . $row, 'Información de Búsqueda');
        $sheet->mergeCells('A' . $row . ':B' . $row);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Perfil:');
        $sheet->setCellValue('B' . $row, ucfirst($data['profile_type']));
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Fecha Inicio:');
        $sheet->setCellValue('B' . $row, $data['fecha_inicio']);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Fecha Fin:');
        $sheet->setCellValue('B' . $row, $data['fecha_fin']);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Documento:');
        $sheet->setCellValue('B' . $row, $data['numero_documento']);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Fecha Consulta:');
        $sheet->setCellValue('B' . $row, date('Y-m-d H:i:s', strtotime($data['created_at'])));
        $row += 2;
        
        // Resumen por fuente
        $sheet->setCellValue('A' . $row, 'Resultados por Fuente');
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $row++;
        
        $sheet->setCellValue('A' . $row, 'Fuente');
        $sheet->setCellValue('B' . $row, 'Registros');
        $sheet->setCellValue('C' . $row, 'Estado');
        $row++;
        
        $source_names = array(
            'tvec' => 'TVEC - Tienda Virtual del Estado',
            'secopi' => 'SECOPI - Sistema Electrónico I',
            'secopii' => 'SECOPII - Sistema Electrónico II'
        );
        
        foreach ($source_names as $source => $name) {
            $count = isset($data['results'][$source]) ? count($data['results'][$source]) : 0;
            $status = $count > 0 ? 'Con resultados' : 'Sin resultados';
            
            $sheet->setCellValue('A' . $row, $name);
            $sheet->setCellValue('B' . $row, $count);
            $sheet->setCellValue('C' . $row, $status);
            $row++;
        }
        
        // Total
        $sheet->setCellValue('A' . $row, 'TOTAL');
        $sheet->setCellValue('B' . $row, $data['total_records']);
        $sheet->setCellValue('C' . $row, $data['total_records'] > 0 ? 'Exitosa' : 'Sin resultados');
        
        // Aplicar estilos
        $this->apply_summary_styles($sheet, $row);
    }
    
    /**
     * Crear hoja por fuente de datos
     */
    private function create_source_sheet($spreadsheet, $source, $records, $sheet_index) {
        $source_names = array(
            'tvec' => 'TVEC',
            'secopi' => 'SECOPI', 
            'secopii' => 'SECOPII'
        );
        
        $sheet_name = $source_names[$source] ?? strtoupper($source);
        
        $sheet = $spreadsheet->createSheet($sheet_index);
        $sheet->setTitle($sheet_name);
        
        if (empty($records)) {
            $sheet->setCellValue('A1', 'No hay datos para ' . $sheet_name);
            return;
        }
        
        // Headers
        $headers = array_keys($records[0]);
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $this->format_header($header));
            $col++;
        }
        
        // Datos
        $row = 2;
        foreach ($records as $record) {
            $col = 'A';
            foreach ($record as $value) {
                $formatted_value = $this->format_cell_value($value);
                $sheet->setCellValue($col . $row, $formatted_value);
                $col++;
            }
            $row++;
        }
        
        // Aplicar estilos
        $this->apply_data_sheet_styles($sheet, count($headers), $row - 1);
        
        // Auto-ajustar columnas
        foreach (range('A', $this->get_column_letter(count($headers) - 1)) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
    
    /**
     * Exportar a PDF usando TCPDF o similar
     */
    private function export_to_pdf($data, $search_id) {
        // Verificar si TCPDF está disponible
        if (!$this->is_tcpdf_available()) {
            return $this->export_to_html_pdf($data, $search_id);
        }
        
        require_once $this->get_tcpdf_path();
        
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Configurar documento
        $pdf->SetCreator('Consulta Procesos Plugin');
        $pdf->SetAuthor('Sistema de Consulta de Procesos');
        $pdf->SetTitle('Resultados de Consulta de Procesos');
        $pdf->SetSubject('Reporte de Contratos');
        
        // Configurar márgenes
        $pdf->SetMargins(15, 27, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        
        // Auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 25);
        
        // Configurar fuente
        $pdf->SetFont('helvetica', '', 9);
        
        // Página de portada
        $this->create_pdf_cover_page($pdf, $data);
        
        // Páginas por fuente
        foreach ($data['results'] as $source => $records) {
            if (!empty($records)) {
                $this->create_pdf_source_page($pdf, $source, $records);
            }
        }
        
        // Generar archivo
        $filename = $this->generate_filename($search_id, 'pdf');
        $filepath = $this->temp_dir . $filename;
        
        $pdf->Output($filepath, 'F');
        
        // Crear token de descarga
        $download_token = $this->create_download_token($filename, $search_id);
        
        return array(
            'success' => true,
            'filename' => $filename,
            'download_token' => $download_token,
            'download_url' => $this->get_download_url($download_token),
            'file_size' => $this->format_file_size(filesize($filepath)),
            'records_count' => $data['total_records']
        );
    }
    
    /**
     * Exportar como CSV con formato Excel (fallback)
     */
    private function export_to_csv_as_excel($data, $search_id) {
        $filename = $this->generate_filename($search_id, 'xlsx');
        $filepath = $this->temp_dir . $filename;
        
        $file = fopen($filepath, 'w');
        
        // BOM para UTF-8
        fwrite($file, "\xEF\xBB\xBF");
        
        // Hoja de resumen
        fputcsv($file, array('CONSULTA DE PROCESOS CONTRACTUALES'));
        fputcsv($file, array(''));
        fputcsv($file, array('Información de Búsqueda'));
        fputcsv($file, array('Perfil:', ucfirst($data['profile_type'])));
        fputcsv($file, array('Fecha Inicio:', $data['fecha_inicio']));
        fputcsv($file, array('Fecha Fin:', $data['fecha_fin']));
        fputcsv($file, array('Documento:', $data['numero_documento']));
        fputcsv($file, array(''));
        
        // Datos por fuente
        foreach ($data['results'] as $source => $records) {
            if (!empty($records)) {
                $source_name = strtoupper($source);
                fputcsv($file, array('=== ' . $source_name . ' ==='));
                
                // Headers
                $headers = array_keys($records[0]);
                fputcsv($file, $headers);
                
                // Datos
                foreach ($records as $record) {
                    $row_data = array();
                    foreach ($record as $value) {
                        $row_data[] = $this->format_csv_value($value);
                    }
                    fputcsv($file, $row_data);
                }
                
                fputcsv($file, array(''));
            }
        }
        
        fclose($file);
        
        // Crear token de descarga
        $download_token = $this->create_download_token($filename, $search_id);
        
        return array(
            'success' => true,
            'filename' => $filename,
            'download_token' => $download_token,
            'download_url' => $this->get_download_url($download_token),
            'file_size' => $this->format_file_size(filesize($filepath)),
            'records_count' => $data['total_records']
        );
    }
    
    /**
     * Crear PDF HTML (fallback)
     */
    private function export_to_html_pdf($data, $search_id) {
        // Generar HTML
        $html = $this->generate_pdf_html($data);
        
        // Convertir a PDF usando wkhtmltopdf si está disponible
        if ($this->is_wkhtmltopdf_available()) {
            return $this->convert_html_to_pdf_wkhtmltopdf($html, $search_id);
        }
        
        // Fallback: guardar como HTML
        $filename = $this->generate_filename($search_id, 'html');
        $filepath = $this->temp_dir . $filename;
        
        file_put_contents($filepath, $html);
        
        $download_token = $this->create_download_token($filename, $search_id);
        
        return array(
            'success' => true,
            'filename' => $filename,
            'download_token' => $download_token,
            'download_url' => $this->get_download_url($download_token),
            'file_size' => $this->format_file_size(filesize($filepath)),
            'records_count' => $data['total_records'],
            'note' => 'Exportado como HTML. Para PDF instale wkhtmltopdf o TCPDF.'
        );
    }
    
    /**
     * Generar HTML para PDF
     */
    private function generate_pdf_html($data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Consulta de Procesos Contractuales</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #007cba; text-align: center; }
                h2 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 5px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f8f9fa; font-weight: bold; }
                .summary { background-color: #f8f9fa; padding: 15px; margin-bottom: 20px; }
                .no-break { page-break-inside: avoid; }
                @media print { body { margin: 0; } }
            </style>
        </head>
        <body>
            <h1>CONSULTA DE PROCESOS CONTRACTUALES</h1>
            
            <div class="summary">
                <h2>Información de Búsqueda</h2>
                <p><strong>Perfil:</strong> <?php echo ucfirst($data['profile_type']); ?></p>
                <p><strong>Período:</strong> <?php echo $data['fecha_inicio']; ?> al <?php echo $data['fecha_fin']; ?></p>
                <p><strong>Documento:</strong> <?php echo $data['numero_documento']; ?></p>
                <p><strong>Total de Registros:</strong> <?php echo $data['total_records']; ?></p>
                <p><strong>Fecha de Consulta:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
            
            <?php foreach ($data['results'] as $source => $records): ?>
                <?php if (!empty($records)): ?>
                    <div class="no-break">
                        <h2><?php echo $this->get_source_display_name($source); ?> (<?php echo count($records); ?> registros)</h2>
                        
                        <table>
                            <thead>
                                <tr>
                                    <?php foreach (array_keys($records[0]) as $header): ?>
                                        <th><?php echo esc_html($this->format_header($header)); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $record): ?>
                                    <tr>
                                        <?php foreach ($record as $value): ?>
                                            <td><?php echo esc_html($this->format_display_value($value)); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generar nombre de archivo único
     */
    private function generate_filename($search_id, $extension) {
        $timestamp = date('Y-m-d_H-i-s');
        $short_id = substr($search_id, 0, 8);
        return "consulta_procesos_{$timestamp}_{$short_id}.{$extension}";
    }
    
    /**
     * Crear token de descarga
     */
    private function create_download_token($filename, $search_id) {
        $token = CP_Utils::generate_secure_token();
        
        set_transient('cp_download_' . $token, array(
            'filename' => $filename,
            'search_id' => $search_id,
            'created_at' => time()
        ), 3600); // 1 hora de validez
        
        return $token;
    }
    
    /**
     * Obtener URL de descarga
     */
    private function get_download_url($token) {
        return admin_url('admin-ajax.php?action=cp_download_export_file&token=' . $token);
    }
    
    /**
     * AJAX: Descargar archivo exportado
     */
    public function ajax_download_export_file() {
        $token = sanitize_text_field($_GET['token'] ?? '');
        
        if (empty($token)) {
            wp_die('Token requerido');
        }
        
        $download_data = get_transient('cp_download_' . $token);
        
        if (!$download_data) {
            wp_die('Token inválido o expirado');
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
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Determinar content type
        $content_types = array(
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            'pdf' => 'application/pdf',
            'html' => 'text/html'
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
     * Verificar disponibilidad de PhpSpreadsheet
     */
    private function is_phpspreadsheet_available() {
        return class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet');
    }
    
    /**
     * Verificar disponibilidad de TCPDF
     */
    private function is_tcpdf_available() {
        return class_exists('TCPDF');
    }
    
    /**
     * Verificar disponibilidad de wkhtmltopdf
     */
    private function is_wkhtmltopdf_available() {
        return !empty(shell_exec('which wkhtmltopdf'));
    }
    
    /**
     * Formatear header de columna
     */
    private function format_header($header) {
        return str_replace('_', ' ', ucwords(str_replace(array('_', '-'), ' ', $header)));
    }
    
    /**
     * Formatear valor de celda
     */
    private function format_cell_value($value) {
        if (is_null($value)) {
            return '';
        }
        
        if (is_bool($value)) {
            return $value ? 'Sí' : 'No';
        }
        
        if (is_numeric($value) && strlen($value) > 10) {
            return number_format($value, 2, '.', ',');
        }
        
        return (string) $value;
    }
    
    /**
     * Formatear valor para CSV
     */
    private function format_csv_value($value) {
        if (is_null($value)) {
            return '';
        }
        
        return (string) $value;
    }
    
    /**
     * Formatear valor para display
     */
    private function format_display_value($value) {
        if (is_null($value)) {
            return '';
        }
        
        if (strlen($value) > 100) {
            return substr($value, 0, 100) . '...';
        }
        
        return (string) $value;
    }
    
    /**
     * Obtener nombre de fuente para display
     */
    private function get_source_display_name($source) {
        $names = array(
            'tvec' => 'TVEC - Tienda Virtual del Estado Colombiano',
            'secopi' => 'SECOPI - Sistema Electrónico de Contratación Pública I',
            'secopii' => 'SECOPII - Sistema Electrónico de Contratación Pública II'
        );
        
        return $names[$source] ?? strtoupper($source);
    }
    
    /**
     * Obtener letra de columna de Excel
     */
    private function get_column_letter($index) {
        $letters = '';
        while ($index >= 0) {
            $letters = chr($index % 26 + 65) . $letters;
            $index = intval($index / 26) - 1;
        }
        return $letters;
    }
}