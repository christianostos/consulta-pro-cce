/**
 * JavaScript para página de resultados
 * Archivo: assets/js/results.js
 */

jQuery(document).ready(function($) {
    
    // Variables globales
    var exportInProgress = false;
    
    // Inicialización
    init();
    
    function init() {
        bindEvents();
        enhanceTable();
        setupKeyboardShortcuts();
    }
    
    /**
     * Vincular eventos
     */
    function bindEvents() {
        // Botones de exportación
        $('.cp-export-btn').on('click', handleExportClick);
        
        // Mejorar experiencia de tabla
        $('.cp-results-table tr').on('mouseenter', highlightRow);
        $('.cp-results-table tr').on('mouseleave', unhighlightRow);
        
        // Enlaces externos
        $('a[target="_blank"]').on('click', handleExternalLink);
        
        // Cerrar ventana con Escape
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27) { // Escape
                if (confirm('¿Desea cerrar esta ventana?')) {
                    window.close();
                }
            }
        });
        
        // Prevenir cierre accidental
        $(window).on('beforeunload', function(e) {
            if (exportInProgress) {
                return '¿Está seguro de que desea salir? Su descarga puede cancelarse.';
            }
        });
    }
    
    /**
     * Manejar click en botón de exportación
     */
    function handleExportClick(e) {
        e.preventDefault();
        
        if (exportInProgress) {
            showMessage('Ya hay una exportación en proceso', 'warning');
            return;
        }
        
        var $button = $(this);
        var format = $button.data('format');
        var searchId = $button.data('search-id');
        
        if (!searchId) {
            showMessage('Error: ID de búsqueda no encontrado', 'error');
            return;
        }
        
        startExport(format, searchId, $button);
    }
    
    /**
     * Iniciar exportación
     */
    function startExport(format, searchId, $button) {
        exportInProgress = true;
        
        // Mostrar estado de carga
        showLoadingOverlay('Preparando ' + (format === 'pdf' ? 'PDF' : 'Excel') + '...');
        
        // Deshabilitar botón
        $button.addClass('downloading').prop('disabled', true);
        
        // Realizar petición AJAX
        $.ajax({
            url: cpResults.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cp_export_search_results',
                nonce: cpResults.nonce,
                search_id: searchId,
                format: format
            },
            dataType: 'json',
            timeout: 300000, // 5 minutos timeout
            success: function(response) {
                if (response.success) {
                    handleExportSuccess(response.data, format);
                } else {
                    handleExportError(response.data.message || 'Error en la exportación');
                }
            },
            error: function(xhr, status, error) {
                handleExportError('Error de comunicación: ' + error);
            },
            complete: function() {
                exportInProgress = false;
                hideLoadingOverlay();
                
                // Re-habilitar botón
                $button.removeClass('downloading').prop('disabled', false);
            }
        });
    }
    
    /**
     * Manejar éxito en exportación
     */
    function handleExportSuccess(data, format) {
        showMessage('Descarga iniciada correctamente', 'success');
        
        // Iniciar descarga automática
        if (data.download_url) {
            var link = document.createElement('a');
            link.href = data.download_url;
            link.download = data.filename || 'consulta_procesos.' + format;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Mostrar información adicional
        if (data.file_size) {
            showMessage('Archivo listo (' + data.file_size + ')', 'info');
        }
    }
    
    /**
     * Manejar error en exportación
     */
    function handleExportError(message) {
        showMessage('Error: ' + message, 'error');
        console.error('Export error:', message);
    }
    
    /**
     * Mostrar overlay de carga
     */
    function showLoadingOverlay(message) {
        var $overlay = $('#cp-export-loading');
        
        if ($overlay.length === 0) {
            $overlay = $('<div id="cp-export-loading" class="cp-loading-overlay">' +
                '<div class="cp-loading-content">' +
                '<div class="cp-spinner"></div>' +
                '<p class="cp-loading-message">Cargando...</p>' +
                '</div>' +
                '</div>');
            $('body').append($overlay);
        }
        
        $overlay.find('.cp-loading-message').text(message || 'Cargando...');
        $overlay.fadeIn(300);
    }
    
    /**
     * Ocultar overlay de carga
     */
    function hideLoadingOverlay() {
        $('#cp-export-loading').fadeOut(300);
    }
    
    /**
     * Mostrar mensaje
     */
    function showMessage(message, type) {
        type = type || 'info';
        
        // Remover mensajes anteriores
        $('.cp-message').remove();
        
        var iconClass = {
            'success': 'yes-alt',
            'error': 'dismiss',
            'warning': 'warning',
            'info': 'info'
        };
        
        var $message = $('<div class="cp-message cp-message-' + type + '">' +
            '<span class="dashicons dashicons-' + iconClass[type] + '"></span>' +
            '<span>' + escapeHtml(message) + '</span>' +
            '<button class="cp-message-close">&times;</button>' +
            '</div>');
        
        $('.cp-results-header').after($message);
        
        // Auto-remover después de 5 segundos
        setTimeout(function() {
            $message.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Permitir cerrar manualmente
        $message.find('.cp-message-close').on('click', function() {
            $message.fadeOut(300, function() {
                $(this).remove();
            });
        });
    }
    
    /**
     * Mejorar tablas
     */
    function enhanceTable() {
        // Agregar numeración de filas
        $('.cp-results-table tbody tr').each(function(index) {
            $(this).attr('data-row', index + 1);
        });
        
        // Habilitar ordenamiento simple (opcional)
        $('.cp-results-table th').on('click', function() {
            var $th = $(this);
            var columnIndex = $th.index();
            var $table = $th.closest('table');
            var $tbody = $table.find('tbody');
            var $rows = $tbody.find('tr');
            
            // Alternar orden
            var ascending = !$th.hasClass('sorted-desc');
            
            // Remover clases de ordenamiento anteriores
            $table.find('th').removeClass('sorted-asc sorted-desc');
            
            // Agregar clase actual
            $th.addClass(ascending ? 'sorted-asc' : 'sorted-desc');
            
            // Ordenar filas
            var sortedRows = $rows.sort(function(a, b) {
                var aVal = $(a).find('td').eq(columnIndex).text().trim();
                var bVal = $(b).find('td').eq(columnIndex).text().trim();
                
                // Intentar ordenar como números si es posible
                if (!isNaN(aVal) && !isNaN(bVal)) {
                    return ascending ? 
                        parseFloat(aVal) - parseFloat(bVal) : 
                        parseFloat(bVal) - parseFloat(aVal);
                }
                
                // Ordenar como texto
                return ascending ? 
                    aVal.localeCompare(bVal) : 
                    bVal.localeCompare(aVal);
            });
            
            $tbody.append(sortedRows);
        });
        
        // Agregar indicadores de ordenamiento
        $('.cp-results-table th').append('<span class="sort-indicator"></span>');
    }
    
    /**
     * Resaltar fila
     */
    function highlightRow() {
        $(this).addClass('highlighted');
    }
    
    /**
     * Quitar resaltado de fila
     */
    function unhighlightRow() {
        $(this).removeClass('highlighted');
    }
    
    /**
     * Manejar enlaces externos
     */
    function handleExternalLink(e) {
        // Agregar confirmación para enlaces externos (opcional)
        var href = $(this).attr('href');
        if (href && href.indexOf('http') === 0) {
            // Opcional: confirmar navegación externa
            // return confirm('¿Desea abrir este enlace en una nueva ventana?');
        }
    }
    
    /**
     * Configurar atajos de teclado
     */
    function setupKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            // Ctrl/Cmd + E = Exportar Excel  
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 69) {
                e.preventDefault();
                $('.cp-export-btn[data-format="excel"]').first().click();
            }
            
            // Ctrl/Cmd + P = Exportar PDF o imprimir
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 80) {
                e.preventDefault();
                var $pdfBtn = $('.cp-export-btn[data-format="pdf"]').first();
                if ($pdfBtn.length > 0) {
                    $pdfBtn.click();
                } else {
                    window.print();
                }
            }
            
            // Ctrl/Cmd + W = Cerrar ventana
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 87) {
                if (confirm('¿Desea cerrar esta ventana?')) {
                    window.close();
                }
            }
        });
    }
    
    /**
     * Funciones de utilidad
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Mejorar experiencia visual
     */
    function enhanceVisualExperience() {
        // Animación de entrada para elementos
        $('.cp-source-results').each(function(index) {
            $(this).css({
                opacity: 0,
                transform: 'translateY(20px)'
            }).delay(index * 100).animate({
                opacity: 1
            }, 500).queue(function() {
                $(this).css('transform', 'translateY(0)');
                $(this).dequeue();
            });
        });
        
        // Contador animado para totales
        $('.cp-summary-item strong').each(function() {
            var $this = $(this);
            var finalValue = $this.text();
            
            if (!isNaN(finalValue)) {
                var currentValue = 0;
                var increment = Math.ceil(finalValue / 20);
                
                var timer = setInterval(function() {
                    currentValue += increment;
                    if (currentValue >= finalValue) {
                        currentValue = finalValue;
                        clearInterval(timer);
                    }
                    $this.text(currentValue.toLocaleString());
                }, 50);
            }
        });
    }
    
    /**
     * Funcionalidad de búsqueda en tabla
     */
    function addTableSearch() {
        if ($('.cp-results-table').length === 0) {
            return;
        }
        
        // Agregar campo de búsqueda
        var searchHtml = '<div class="cp-table-search">' +
            '<input type="text" id="cp-table-search-input" placeholder="Buscar en resultados...">' +
            '<span class="dashicons dashicons-search"></span>' +
            '</div>';
        
        $('.cp-results-table-container').before(searchHtml);
        
        // Funcionalidad de búsqueda
        $('#cp-table-search-input').on('input', function() {
            var searchTerm = $(this).val().toLowerCase();
            
            $('.cp-results-table tbody tr').each(function() {
                var rowText = $(this).text().toLowerCase();
                if (rowText.indexOf(searchTerm) === -1) {
                    $(this).hide();
                } else {
                    $(this).show();
                }
            });
            
            // Mostrar/ocultar mensaje si no hay resultados
            var visibleRows = $('.cp-results-table tbody tr:visible').length;
            $('.cp-no-search-results').remove();
            
            if (visibleRows === 0 && searchTerm !== '') {
                $('.cp-results-table').after(
                    '<div class="cp-no-search-results">' +
                    '<p>No se encontraron resultados para: <strong>' + escapeHtml(searchTerm) + '</strong></p>' +
                    '</div>'
                );
            }
        });
    }
    
    /**
     * Estadísticas de tabla
     */
    function showTableStats() {
        $('.cp-source-results').each(function() {
            var $container = $(this);
            var $table = $container.find('.cp-results-table');
            
            if ($table.length === 0) {
                return;
            }
            
            var totalRows = $table.find('tbody tr').length;
            var totalColumns = $table.find('thead th').length;
            
            var statsHtml = '<div class="cp-table-stats">' +
                '<small>' + totalRows + ' registros, ' + totalColumns + ' columnas</small>' +
                '</div>';
            
            $container.find('.cp-source-results-header').append(statsHtml);
        });
    }
    
    // Ejecutar mejoras adicionales
    setTimeout(function() {
        enhanceVisualExperience();
        addTableSearch();
        showTableStats();
    }, 500);
    
    // Log de inicialización
    console.log('CP Results page initialized');
    
    // Exponer funciones para debugging
    window.cpResultsDebug = {
        exportInProgress: function() { return exportInProgress; },
        showMessage: showMessage,
        enhanceTable: enhanceTable
    };
});