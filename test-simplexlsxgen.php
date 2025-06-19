<?php
/**
 * Archivo de prueba para verificar SimpleXLSXGen
 * Guardar como: wp-content/plugins/consulta-procesos/test-simplexlsxgen.php
 * Ejecutar desde navegador: tu-sitio.com/wp-content/plugins/consulta-procesos/test-simplexlsxgen.php
 */

// Definir constantes necesarias
define('CP_PLUGIN_DIR', __DIR__ . '/');

echo "<h1>🧪 Test SimpleXLSXGen</h1>";
echo "<p><strong>Directorio del plugin:</strong> " . CP_PLUGIN_DIR . "</p>";

// Verificar estructura de directorios
echo "<h2>📁 1. Verificando estructura de directorios</h2>";

$lib_path = CP_PLUGIN_DIR . 'lib/SimpleXLSXGen.php';
echo "<strong>Buscando archivo en:</strong> " . $lib_path . "<br>";

if (file_exists($lib_path)) {
    echo "✅ Archivo SimpleXLSXGen.php encontrado<br>";
    echo "📊 Tamaño del archivo: " . number_format(filesize($lib_path)) . " bytes<br>";
    
    if (is_readable($lib_path)) {
        echo "✅ Archivo es legible<br>";
        
        // Cargar el archivo
        echo "<h2>📚 2. Cargando SimpleXLSXGen</h2>";
        try {
            require_once $lib_path;
            echo "✅ Archivo cargado exitosamente<br>";
            
            // Verificar que la clase existe
            if (class_exists('Shuchkin\SimpleXLSXGen')) {
                echo "✅ Clase Shuchkin\\SimpleXLSXGen disponible<br>";
                
                // Test básico
                echo "<h2>🎯 3. Test de funcionalidad básica</h2>";
                try {
                    // Crear datos de prueba
                    $test_data = array(
                        array('ID', 'Nombre', 'Fecha', 'Valor'),
                        array(1, 'Prueba 1', '2024-01-01', 1000.50),
                        array(2, 'Prueba 2', '2024-01-02', 2000.75),
                        array(3, 'Consulta Procesos Test', '2024-01-03', 3000.25)
                    );
                    
                    // Crear archivo Excel
                    $xlsx = \Shuchkin\SimpleXLSXGen::fromArray($test_data);
                    
                    echo "✅ Objeto SimpleXLSXGen creado exitosamente<br>";
                    
                    // Configurar propiedades
                    $xlsx->setDefaultFont('Calibri')
                         ->setDefaultFontSize(11);
                    
                    echo "✅ Propiedades configuradas exitosamente<br>";
                    
                    // Intentar crear archivo temporal
                    $temp_file = CP_PLUGIN_DIR . 'test_excel_' . time() . '.xlsx';
                    $result = $xlsx->saveAs($temp_file);
                    
                    if ($result && file_exists($temp_file)) {
                        $file_size = filesize($temp_file);
                        echo "✅ Archivo Excel temporal creado exitosamente<br>";
                        echo "📊 Tamaño del archivo Excel: " . number_format($file_size) . " bytes<br>";
                        
                        if ($file_size > 1000) { // Archivo Excel válido debe tener al menos 1KB
                            echo "<h3>🎉 ¡SimpleXLSXGen está funcionando perfectamente!</h3>";
                            echo "<p style='color: green; font-weight: bold;'>Tu plugin puede crear archivos Excel (.xlsx) reales.</p>";
                            
                            // Ofrecer descarga del archivo de prueba
                            echo "<p><a href='" . basename($temp_file) . "' download>📥 Descargar archivo Excel de prueba</a></p>";
                        } else {
                            echo "⚠️ Archivo creado pero muy pequeño (posible error)<br>";
                        }
                        
                        // Limpiar archivo temporal después de 1 minuto
                        echo "<p><small>El archivo de prueba se eliminará automáticamente.</small></p>";
                        echo "<script>setTimeout(function(){ 
                            fetch('?delete_test=1'); 
                        }, 60000);</script>";
                        
                    } else {
                        echo "❌ Error: No se pudo crear el archivo Excel<br>";
                        echo "Método saveAs() retornó: " . ($result ? 'true' : 'false') . "<br>";
                    }
                    
                } catch (Exception $e) {
                    echo "❌ Error en test básico: " . $e->getMessage() . "<br>";
                    echo "📋 Detalles: " . $e->getTraceAsString() . "<br>";
                }
                
            } else {
                echo "❌ Clase Shuchkin\\SimpleXLSXGen NO disponible después de cargar<br>";
                
                // Mostrar clases disponibles
                echo "<h3>🔍 Clases disponibles que empiecen con 'Shuchkin':</h3>";
                $classes = get_declared_classes();
                $shuchkin_classes = array_filter($classes, function($class) {
                    return stripos($class, 'shuchkin') !== false;
                });
                
                if (empty($shuchkin_classes)) {
                    echo "No se encontraron clases Shuchkin<br>";
                } else {
                    foreach ($shuchkin_classes as $class) {
                        echo "- " . $class . "<br>";
                    }
                }
            }
            
        } catch (Exception $e) {
            echo "❌ Error cargando archivo: " . $e->getMessage() . "<br>";
        }
        
    } else {
        echo "❌ Archivo no es legible (verificar permisos)<br>";
    }
    
} else {
    echo "❌ Archivo SimpleXLSXGen.php NO encontrado<br>";
    echo "<div style='background: #ffe6e6; padding: 15px; border: 1px solid #ff0000; margin: 10px 0;'>";
    echo "<strong>📋 Instrucciones para instalar:</strong><br>";
    echo "1. Crear directorio: <code>" . CP_PLUGIN_DIR . "lib/</code><br>";
    echo "2. Descargar SimpleXLSXGen.php desde: <a href='https://raw.githubusercontent.com/shuchkin/simplexlsxgen/master/src/SimpleXLSXGen.php' target='_blank'>GitHub</a><br>";
    echo "3. Copiar el archivo en: <code>" . $lib_path . "</code><br>";
    echo "4. Refrescar esta página<br>";
    echo "</div>";
    
    // Verificar si existe el directorio lib
    $lib_dir = CP_PLUGIN_DIR . 'lib/';
    if (is_dir($lib_dir)) {
        echo "✅ Directorio lib/ existe<br>";
        $lib_contents = scandir($lib_dir);
        echo "📁 Contenido del directorio lib/: " . implode(', ', array_slice($lib_contents, 2)) . "<br>";
    } else {
        echo "❌ Directorio lib/ NO existe<br>";
        echo "<strong>Crear directorio:</strong> " . $lib_dir . "<br>";
    }
}

// Mostrar versión de PHP y extensiones
echo "<h2>⚙️ 4. Información del sistema</h2>";
echo "<strong>PHP Version:</strong> " . PHP_VERSION . "<br>";
echo "<strong>ZipArchive disponible:</strong> " . (class_exists('ZipArchive') ? '✅ Sí' : '❌ No') . "<br>";
echo "<strong>XMLWriter disponible:</strong> " . (class_exists('XMLWriter') ? '✅ Sí' : '❌ No') . "<br>";

// Verificar permisos de escritura
$upload_dir = wp_upload_dir();
$temp_dir = $upload_dir['basedir'] . '/cp-exports/';
echo "<strong>Directorio de exportación:</strong> " . $temp_dir . "<br>";
echo "<strong>Directorio escribible:</strong> " . (is_writable(dirname($temp_dir)) ? '✅ Sí' : '❌ No') . "<br>";

// Limpiar archivo de prueba si se solicita
if (isset($_GET['delete_test'])) {
    $test_files = glob(CP_PLUGIN_DIR . 'test_excel_*.xlsx');
    foreach ($test_files as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
    echo "🗑️ Archivos de prueba eliminados<br>";
    exit();
}

echo "<br><hr>";
echo "<strong>🚀 Próximos pasos:</strong><br>";
echo "1. Si ves '✅ SimpleXLSXGen está funcionando perfectamente!' → Procede con la implementación<br>";
echo "2. Si hay errores → Sigue las instrucciones de instalación<br>";
echo "3. Elimina este archivo después de la prueba<br>";
?>