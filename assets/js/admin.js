jQuery(document).ready(function($) {
    
    // Variables globales
    var currentTables = [];
    var queryResults = null;
    var savedQueries = [];
    
    // Variables para logs del frontend
    var currentPage = 1;
    var pageSize = 50;
    var currentFilters = {};
    var frontendLogsData = [];
    
    // Inicialización
    init();
    
    function init() {
        initDashboard();
        initConfigPage();
        initQueryPage();
        initSettingsPage();
        initLogsPage();
        initModals();
        bindGlobalEvents();
    }
    
    // ========================================
    // PÁGINA DE CONFIGURACIÓN DE PARÁMETROS - ACTUALIZADA
    // ========================================
    
    function initSettingsPage() {
        // Verificar que estamos en la página correcta
        if (window.location.href.indexOf('consulta-procesos-settings') === -1) {
            return;
        }
        
        console.log('CP: Inicializando página de configuración de parámetros...');
        
        // Validación específica para la página de parámetros
        var $settingsForm = $('#cp-settings-form');
        if ($settingsForm.length > 0) {
            $settingsForm.on('submit', function(e) {
                if (!validateSettingsForm()) {
                    e.preventDefault();
                    return false;
                }
                return true;
            });
        }
        
        // NUEVO: Funcionalidad para mostrar/ocultar configuración de API
        initApiConfiguration();
        
        // Alternar habilitación de método según activación
        $('input[name^="cp_"][name$="_active"]').on('change', function() {
            var baseName = $(this).attr('name').replace('_active', '');
            var methodRadios = $('input[name="' + baseName + '_method"]');
            
            if ($(this).is(':checked')) {
                methodRadios.prop('disabled', false);
                methodRadios.closest('fieldset').removeClass('disabled');
            } else {
                methodRadios.prop('disabled', true);
                methodRadios.closest('fieldset').addClass('disabled');
                // También ocultar configuración de API si se desactiva
                $('#cp-' + baseName.replace('cp_', '') + '-api-config').slideUp(300);
            }
        });
        
        // Inicializar estado de métodos
        $('input[name^="cp_"][name$="_active"]').trigger('change');
        
        // Preview en tiempo real de términos
        if (typeof tinymce !== 'undefined') {
            // Si TinyMCE está disponible, escuchar cambios
            $(document).on('tinymce-editor-init', function(event, editor) {
                if (editor.id === 'cp_terms_content') {
                    editor.on('change keyup', function() {
                        updateTermsPreview();
                    });
                }
            });
        }
        
        // Fallback para textarea normal
        $('#cp_terms_content').on('input', function() {
            updateTermsPreview();
        });
        
        // NUEVO: Funcionalidad de caché
        initCacheControls();
    }
    
    /**
     * NUEVO: Inicializar configuración de API
     */
    function initApiConfiguration() {
        console.log('CP: Inicializando configuración de API...');
        
        // Función para mostrar/ocultar configuración de API
        function toggleApiConfig() {
            $('.cp-method-radio').each(function() {
                var target = $(this).data('target');
                var method = $(this).val();
                var apiConfig = $('#cp-' + target + '-api-config');
                
                if ($(this).is(':checked') && method === 'api') {
                    apiConfig.slideDown(300);
                    // Marcar campos como requeridos cuando API está seleccionada
                    apiConfig.find('input[type="url"], input[type="text"]').attr('required', true);
                } else if ($(this).is(':checked') && method === 'database') {
                    apiConfig.slideUp(300);
                    // Quitar requerimiento cuando no es API
                    apiConfig.find('input[type="url"], input[type="text"]').attr('required', false);
                }
            });
        }
        
        // Inicializar estado al cargar la página
        toggleApiConfig();
        
        // Escuchar cambios en los radio buttons
        $('.cp-method-radio').on('change', function() {
            toggleApiConfig();
            
            // Log del cambio
            var target = $(this).data('target');
            var method = $(this).val();
            console.log('CP: Método cambiado para ' + target + ': ' + method);
        });
        
        // Validación de URLs en tiempo real
        $('input[type="url"]').on('blur', function() {
            validateApiUrl($(this));
        });
        
        // Validación de campos de fecha
        $('input[name$="_api_date_field"]').on('blur', function() {
            validateDateField($(this));
        });
    }
    
    /**
     * NUEVO: Validar URL de API
     */
    function validateApiUrl($field) {
        var url = $field.val().trim();
        
        // Limpiar errores previos
        $field.removeClass('error-field');
        $field.siblings('.field-error').remove();
        
        if (url === '') {
            // Campo vacío - solo marcar como error si es requerido
            if ($field.attr('required')) {
                showFieldError($field, 'URL requerida cuando API está seleccionada');
            }
            return false;
        }
        
        // Validar formato de URL
        if (!url.match(/^https?:\/\/.+/)) {
            showFieldError($field, 'URL debe comenzar con http:// o https://');
            return false;
        }
        
        // Validar que termine con parámetro
        if (!url.includes('=')) {
            showFieldError($field, 'URL debe terminar con un parámetro (ej: documento_proveedor=)');
            return false;
        }
        
        // Validar que termine con =
        if (!url.endsWith('=')) {
            showFieldError($field, 'URL debe terminar con = para agregar el número de documento');
            return false;
        }
        
        // URL válida
        $field.addClass('valid-field');
        return true;
    }
    
    /**
     * NUEVO: Validar campo de fecha
     */
    function validateDateField($field) {
        var value = $field.val().trim();
        
        // Limpiar errores previos
        $field.removeClass('error-field');
        $field.siblings('.field-error').remove();
        
        if (value === '') {
            // Campo vacío - solo advertir
            showFieldWarning($field, 'Campo de fecha vacío - no se aplicarán filtros de fecha');
            return true;
        }
        
        // Validar que no contenga espacios o caracteres especiales problemáticos
        if (!value.match(/^[a-zA-Z0-9_-]+$/)) {
            showFieldError($field, 'Campo debe contener solo letras, números, guiones y guiones bajos');
            return false;
        }
        
        // Campo válido
        $field.addClass('valid-field');
        return true;
    }
    
    /**
     * NUEVO: Mostrar error en campo
     */
    function showFieldError($field, message) {
        $field.addClass('error-field');
        $field.after('<span class="field-error" style="color: #d63638; font-size: 12px; display: block; margin-top: 5px;">' + message + '</span>');
    }
    
    /**
     * NUEVO: Mostrar advertencia en campo
     */
    function showFieldWarning($field, message) {
        $field.addClass('warning-field');
        $field.after('<span class="field-warning" style="color: #dba617; font-size: 12px; display: block; margin-top: 5px;">' + message + '</span>');
    }
    
    /**
     * NUEVO: Validar formulario de configuración completo
     */
    function validateSettingsForm() {
        var isValid = true;
        var errors = [];
        
        // Validar configuraciones de API para sistemas activos
        $('.cp-method-radio:checked').each(function() {
            var method = $(this).val();
            var target = $(this).data('target');
            
            if (method === 'api') {
                var systemActive = $('input[name="cp_' + target + '_active"]').is(':checked');
                
                if (systemActive) {
                    // Validar URLs de API
                    var urlProveedores = $('#cp_' + target + '_api_url_proveedores');
                    var urlEntidades = $('#cp_' + target + '_api_url_entidades');
                    
                    if (!validateApiUrl(urlProveedores)) {
                        errors.push('URL de API para proveedores de ' + target.toUpperCase() + ' es inválida');
                        isValid = false;
                    }
                    
                    if (!validateApiUrl(urlEntidades)) {
                        errors.push('URL de API para entidades de ' + target.toUpperCase() + ' es inválida');
                        isValid = false;
                    }
                    
                    // Validar campo de fecha
                    var dateField = $('#cp_' + target + '_api_date_field');
                    if (!validateDateField(dateField)) {
                        errors.push('Campo de fecha de ' + target.toUpperCase() + ' es inválido');
                        isValid = false;
                    }
                }
            }
        });
        
        // Validar configuraciones de rendimiento
        var cacheEnabled = $('#cp_enable_cache').is(':checked');
        if (cacheEnabled) {
            var cacheDuration = parseInt($('#cp_cache_duration').val());
            if (isNaN(cacheDuration) || cacheDuration < 60 || cacheDuration > 3600) {
                errors.push('Duración de caché debe estar entre 60 y 3600 segundos');
                isValid = false;
            }
        }
        
        var maxResults = parseInt($('#cp_max_results_per_source').val());
        if (isNaN(maxResults) || maxResults < 100 || maxResults > 5000) {
            errors.push('Máximo de resultados debe estar entre 100 y 5000');
            isValid = false;
        }
        
        // Mostrar errores si los hay
        if (!isValid) {
            alert('Por favor corrija los siguientes errores:\n\n• ' + errors.join('\n• '));
        }
        
        return isValid;
    }
    
    /**
     * NUEVO: Inicializar controles de caché
     */
    function initCacheControls() {
        // Limpiar caché
        $('#cp-clear-cache').on('click', function() {
            var button = $(this);
            var originalText = button.html();
            
            if (!confirm('¿Está seguro de que desea limpiar todo el caché de consultas?')) {
                return;
            }
            
            button.prop('disabled', true).html('<span class="spinner is-active"></span> Limpiando...');
            
            ajaxRequest('cp_clear_cache', {}, function(response) {
                button.prop('disabled', false).html(originalText);
                
                if (response.success) {
                    alert('Caché limpiado exitosamente');
                    // Actualizar estadísticas si están visibles
                    if ($('#cp-cache-info').is(':visible')) {
                        $('#cp-cache-stats').trigger('click');
                    }
                } else {
                    alert('Error al limpiar caché: ' + (response.data ? response.data.message : 'Error desconocido'));
                }
            }, function() {
                button.prop('disabled', false).html(originalText);
                alert('Error de comunicación al limpiar caché');
            });
        });
        
        // Ver estadísticas de caché
        $('#cp-cache-stats').on('click', function() {
            var button = $(this);
            var originalText = button.html();
            var infoDiv = $('#cp-cache-info');
            
            button.prop('disabled', true).html('<span class="spinner is-active"></span> Cargando...');
            
            ajaxRequest('cp_get_cache_stats', {}, function(response) {
                button.prop('disabled', false).html(originalText);
                
                if (response.success) {
                    var stats = response.data;
                    var html = '<div class="cp-cache-stats" style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-top: 10px;">';
                    html += '<h4 style="margin-top: 0;">Estadísticas de Caché</h4>';
                    html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
                    
                    html += '<div><strong>Consultas en caché:</strong><br><span style="font-size: 24px; color: #0073aa;">' + stats.cached_queries + '</span></div>';
                    html += '<div><strong>Caché habilitado:</strong><br><span style="font-size: 18px; color: ' + (stats.cache_enabled ? '#00a32a' : '#d63638') + ';">' + (stats.cache_enabled ? 'Sí' : 'No') + '</span></div>';
                    html += '<div><strong>Duración:</strong><br><span style="font-size: 18px; color: #666;">' + stats.cache_duration + ' segundos</span></div>';
                    
                    html += '</div>';
                    html += '<button type="button" class="button button-secondary" style="margin-top: 10px;" onclick="$(this).closest(\'.cp-cache-stats\').parent().slideUp();">Cerrar</button>';
                    html += '</div>';
                    
                    infoDiv.html(html).slideDown();
                } else {
                    alert('Error al obtener estadísticas: ' + (response.data ? response.data.message : 'Error desconocido'));
                }
            }, function() {
                button.prop('disabled', false).html(originalText);
                alert('Error de comunicación al obtener estadísticas');
            });
        });
    }
    
    function updateTermsPreview() {
        // Esta función podría mostrar una vista previa de los términos
        // Por ahora, solo un placeholder
        console.log('CP: Términos actualizados');
    }
    
    // ========================================
    // PÁGINA DE LOGS - MEJORADA
    // ========================================
    
    function initLogsPage() {
        // Verificar que estamos en la página correcta
        if (window.location.href.indexOf('consulta-procesos-logs') === -1) {
            return;
        }
        
        console.log('CP: Inicializando página de logs...');
        
        // Probar stored procedure
        $('#test-stored-procedure').on('click', function(e) {
            e.preventDefault();
            testStoredProcedure();
        });
        
        // Ejecutar consulta de admin
        $('#execute-admin-query').on('click', function(e) {
            e.preventDefault();
            executeAdminQuery();
        });
        
        // Limpiar consulta de admin
        $('#clear-admin-query').on('click', function() {
            $('#admin-sql-query').val('').focus();
            $('#admin-query-results').html('');
        });
        
        // Actualizar logs del sistema
        $('#refresh-logs').on('click', function(e) {
            e.preventDefault();
            refreshSystemLogs();
        });
        
        // Limpiar logs del sistema
        $('#clear-logs').on('click', function(e) {
            e.preventDefault();
            if (confirm('¿Estás seguro de que quieres limpiar todos los logs del sistema?')) {
                clearSystemLogs();
            }
        });
        
        // Eventos para logs del frontend
        $('#refresh-frontend-logs').on('click', function(e) {
            e.preventDefault();
            refreshFrontendLogs();
        });
        
        // Limpiar logs del frontend
        $('#clear-frontend-logs').on('click', function(e) {
            e.preventDefault();
            if (confirm('¿Estás seguro de que quieres limpiar todos los logs de búsquedas del frontend? Esta acción no se puede deshacer.')) {
                clearFrontendLogs();
            }
        });
        
        // Actualizar estadísticas del frontend
        $('#refresh-frontend-stats').on('click', function(e) {
            e.preventDefault();
            refreshFrontendStats();
        });
        
        // Filtros de logs
        $('#apply-filters').on('click', function() {
            applyLogsFilters();
        });
        
        $('#clear-filters').on('click', function() {
            clearLogsFilters();
        });
        
        // Paginación
        $('#prev-page').on('click', function() {
            if (currentPage > 1) {
                currentPage--;
                refreshFrontendLogs();
            }
        });
        
        $('#next-page').on('click', function() {
            currentPage++;
            refreshFrontendLogs();
        });
        
        $('#page-size').on('change', function() {
            pageSize = parseInt($(this).val());
            currentPage = 1;
            refreshFrontendLogs();
        });
        
        // Filtros en tiempo real
        $('#filter-status, #filter-profile, #filter-date').on('change', function() {
            currentPage = 1;
            refreshFrontendLogs();
        });
        
        // Auto-cargar datos si estamos en la página de logs
        setTimeout(function() {
            refreshSystemLogs();
            refreshFrontendLogs();
            refreshFrontendStats();
        }, 1000);
    }
    
    function testStoredProcedure() {
        var spName = $('#sp-name').val();
        var param1 = $('#sp-param1').val();
        var param2 = $('#sp-param2').val();
        var param3 = $('#sp-param3').val();
        
        if (!spName || !param1 || !param2 || !param3) {
            alert('Por favor, completa todos los campos');
            return;
        }
        
        var button = $('#test-stored-procedure');
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Ejecutando...');
        $('#sp-results').html('<div class="loading">Ejecutando stored procedure...</div>');
        
        ajaxRequest('cp_test_stored_procedure', {
            sp_name: spName,
            param1: param1,
            param2: param2,
            param3: param3
        }, function(response) {
            button.prop('disabled', false).html(originalText);
            
            if (response.success) {
                displayStoredProcedureResults(response.data);
            } else {
                var errorMsg = response.data ? response.data.message : 'Error desconocido';
                $('#sp-results').html('<div class="error"><strong>Error:</strong> ' + errorMsg + '</div>');
            }
        }, function(xhr, status, error) {
            button.prop('disabled', false).html(originalText);
            $('#sp-results').html('<div class="error">Error de comunicación con el servidor: ' + error + '</div>');
        });
    }
    
    function displayStoredProcedureResults(data) {
        var html = '<div class="sp-results-success">';
        html += '<h4>✅ Stored Procedure ejecutado exitosamente</h4>';
        html += '<p><strong>Resultados:</strong> ' + data.total_rows + ' registros';
        html += ' | <strong>Tiempo:</strong> ' + data.execution_time + 's';
        html += ' | <strong>Método:</strong> ' + data.method + '</p>';
        
        if (data.sql) {
            html += '<p><strong>SQL:</strong> <code>' + escapeHtml(data.sql) + '</code></p>';
        }
        
        if (data.results && data.results.length > 0) {
            html += '<div class="table-responsive" style="max-height: 300px; overflow-y: auto;">';
            html += '<table class="results-table">';
            
            // Headers
            html += '<thead><tr>';
            Object.keys(data.results[0]).forEach(function(key) {
                html += '<th>' + escapeHtml(key) + '</th>';
            });
            html += '</tr></thead>';
            
            // Datos (máximo 20 filas para performance)
            html += '<tbody>';
            data.results.slice(0, 20).forEach(function(row) {
                html += '<tr>';
                Object.values(row).forEach(function(value) {
                    var displayValue = value !== null ? escapeHtml(String(value)) : '<em>NULL</em>';
                    html += '<td>' + displayValue + '</td>';
                });
                html += '</tr>';
            });
            html += '</tbody></table>';
            html += '</div>';
            
            if (data.results.length > 20) {
                html += '<p><em>Mostrando las primeras 20 filas de ' + data.total_rows + ' resultados</em></p>';
            }
        } else {
            html += '<p><em>El stored procedure se ejecutó pero no devolvió resultados</em></p>';
        }
        
        html += '</div>';
        $('#sp-results').html(html);
    }
    
    function executeAdminQuery() {
        var sql = $('#admin-sql-query').val().trim();
        
        if (!sql) {
            alert('Por favor, escribe una consulta SQL');
            return;
        }
        
        var button = $('#execute-admin-query');
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Ejecutando...');
        $('#admin-query-results').html('');
        
        ajaxRequest('cp_execute_admin_query', {
            sql: sql
        }, function(response) {
            button.prop('disabled', false).html(originalText);
            
            if (response.success) {
                displayAdminQueryResults(response.data);
            } else {
                $('#admin-query-results').html('<div class="error"><strong>Error:</strong> ' + response.data.message + '</div>');
            }
        }, function() {
            button.prop('disabled', false).html(originalText);
            $('#admin-query-results').html('<div class="error">Error de comunicación con el servidor</div>');
        });
    }
    
    function displayAdminQueryResults(data) {
        var html = '<div class="admin-results-success">';
        html += '<h4>✅ Consulta ejecutada exitosamente</h4>';
        html += '<p><strong>Resultados:</strong> ' + data.total_rows + ' registros';
        html += ' | <strong>Tiempo:</strong> ' + data.execution_time + 's';
        html += ' | <strong>Método:</strong> ' + data.method + '</p>';
        
        if (data.results && data.results.length > 0) {
            html += '<div class="table-responsive" style="max-height: 400px; overflow-y: auto;">';
            html += '<table class="results-table">';
            
            // Headers
            html += '<thead><tr>';
            Object.keys(data.results[0]).forEach(function(key) {
                html += '<th>' + escapeHtml(key) + '</th>';
            });
            html += '</tr></thead>';
            
            // Datos
            html += '<tbody>';
            data.results.forEach(function(row) {
                html += '<tr>';
                Object.values(row).forEach(function(value) {
                    var displayValue = value !== null ? escapeHtml(String(value)) : '<em>NULL</em>';
                    html += '<td>' + displayValue + '</td>';
                });
                html += '</tr>';
            });
            html += '</tbody></table>';
            html += '</div>';
        } else {
            html += '<p><em>La consulta se ejecutó pero no devolvió resultados</em></p>';
        }
        
        html += '</div>';
        $('#admin-query-results').html(html);
    }
    
    function refreshSystemLogs() {
        var button = $('#refresh-logs');
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Cargando...');
        $('#system-logs').html('<div class="loading">Cargando logs...</div>');
        
        ajaxRequest('cp_get_system_logs', {}, function(response) {
            button.prop('disabled', false).html(originalText);
            
            if (response.success) {
                var logs = response.data.logs || 'No hay logs disponibles';
                $('#system-logs').text(logs);
                
                if (response.data.file_size) {
                    $('.logs-info').text('Archivo: debug.log (' + formatBytes(response.data.file_size) + ')');
                }
            } else {
                $('#system-logs').text('Error al cargar logs: ' + (response.data ? response.data.message : 'Error desconocido'));
            }
        }, function(xhr, status, error) {
            button.prop('disabled', false).html(originalText);
            $('#system-logs').text('Error de comunicación: ' + error);
        });
    }
    
    function clearSystemLogs() {
        var button = $('#clear-logs');
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Limpiando...');
        
        ajaxRequest('cp_clear_system_logs', {}, function(response) {
            button.prop('disabled', false).html(originalText);
            
            if (response.success) {
                $('#system-logs').text('Logs limpiados exitosamente');
                alert('Logs del sistema limpiados exitosamente');
            } else {
                alert('Error al limpiar logs del sistema: ' + response.data.message);
            }
        }, function() {
            button.prop('disabled', false).html(originalText);
            alert('Error de comunicación');
        });
    }
    
    // Refrescar logs del frontend
    function refreshFrontendLogs() {
        var button = $('#refresh-frontend-logs');
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Cargando...');
        $('#frontend-logs').html('<div class="loading-logs"><span class="spinner is-active"></span> Cargando logs del frontend...</div>');
        
        // Obtener filtros actuales
        var filters = {
            status: $('#filter-status').val(),
            profile: $('#filter-profile').val(),
            date: $('#filter-date').val(),
            page: currentPage,
            page_size: pageSize
        };
        
        ajaxRequest('cp_get_frontend_logs', filters, function(response) {
            button.prop('disabled', false).html(originalText);
            
            if (response.success) {
                frontendLogsData = response.data.logs || [];
                displayFrontendLogs(response.data);
                updatePagination(response.data);
            } else {
                $('#frontend-logs').html('<div class="no-logs-message"><span class="dashicons dashicons-warning"></span><br>Error al cargar logs del frontend</div>');
            }
        }, function(xhr, status, error) {
            button.prop('disabled', false).html(originalText);
            $('#frontend-logs').html('<div class="no-logs-message"><span class="dashicons dashicons-warning"></span><br>Error de comunicación</div>');
        });
    }
    
    // Mostrar logs del frontend
    function displayFrontendLogs(data) {
        var html = '';
        
        if (data.logs && data.logs.length > 0) {
            html += '<table class="frontend-logs-table">';
            html += '<thead><tr>';
            html += '<th style="width: 130px;">Fecha/Hora</th>';
            html += '<th style="width: 80px;">Estado</th>';
            html += '<th style="width: 90px;">Perfil</th>';
            html += '<th style="width: 120px;">Documento</th>';
            html += '<th style="width: 200px;">Rango de Fechas</th>';
            html += '<th style="width: 150px;">Fuentes</th>';
            html += '<th style="width: 80px;">Resultados</th>';
            html += '<th style="width: 80px;">Tiempo</th>';
            html += '<th style="width: 100px;">IP</th>';
            html += '<th>Error</th>';
            html += '</tr></thead>';
            html += '<tbody>';
            
            data.logs.forEach(function(log) {
                html += '<tr>';
                
                // Fecha
                var fecha = new Date(log.created_at);
                html += '<td>' + fecha.toLocaleString('es-ES') + '</td>';
                
                // Estado con badge
                var statusClass = 'status-' + log.status;
                var statusText = getStatusText(log.status);
                html += '<td><span class="status-badge ' + log.status + '">' + statusText + '</span></td>';
                
                // Perfil
                html += '<td>' + capitalizeFirst(log.profile_type) + '</td>';
                
                // Documento
                html += '<td class="truncated-cell" title="' + escapeHtml(log.numero_documento) + '">' + escapeHtml(log.numero_documento) + '</td>';
                
                // Fechas
                html += '<td>' + log.fecha_inicio + ' a ' + log.fecha_fin + '</td>';
                
                // Fuentes
                html += '<td class="truncated-cell" title="' + escapeHtml(log.search_sources) + '">' + escapeHtml(log.search_sources || '-') + '</td>';
                
                // Resultados
                html += '<td style="text-align: right;">' + (log.results_found || 0) + '</td>';
                
                // Tiempo de ejecución
                var execTime = log.execution_time ? log.execution_time + 's' : '-';
                html += '<td style="text-align: right;">' + execTime + '</td>';
                
                // IP
                html += '<td class="truncated-cell" title="' + escapeHtml(log.ip_address) + '">' + escapeHtml(log.ip_address) + '</td>';
                
                // Error (si hay)
                var errorMsg = log.error_message ? log.error_message : '-';
                html += '<td class="truncated-cell" title="' + escapeHtml(errorMsg) + '">' + (log.error_message ? '<span class="log-error">' + escapeHtml(errorMsg.substring(0, 50)) + (errorMsg.length > 50 ? '...' : '') + '</span>' : '-') + '</td>';
                
                html += '</tr>';
            });
            
            html += '</tbody></table>';
        } else {
            html = '<div class="no-logs-message">';
            html += '<span class="dashicons dashicons-search"></span><br>';
            html += 'No hay logs de búsquedas aún';
            html += '</div>';
        }
        
        $('#frontend-logs').html(html);
        $('#frontend-logs-count').text('Total: ' + (data.stats ? data.stats.total : 0) + ' búsquedas');
    }
    
    // Actualizar paginación
    function updatePagination(data) {
        if (!data.logs || data.logs.length === 0) {
            $('#logs-pagination').hide();
            return;
        }
        
        $('#logs-pagination').show();
        
        var totalPages = Math.ceil((data.stats ? data.stats.total : 0) / pageSize);
        
        $('#prev-page').prop('disabled', currentPage <= 1);
        $('#next-page').prop('disabled', currentPage >= totalPages);
        
        $('#pagination-info').text('Página ' + currentPage + ' de ' + totalPages);
    }
    
    // Limpiar logs del frontend
    function clearFrontendLogs() {
        var button = $('#clear-frontend-logs');
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Limpiando...');
        
        ajaxRequest('cp_clear_frontend_logs', {}, function(response) {
            button.prop('disabled', false).html(originalText);
            
            if (response.success) {
                alert('Logs del frontend limpiados exitosamente (' + (response.data.deleted_rows || 0) + ' registros eliminados)');
                refreshFrontendLogs();
                refreshFrontendStats();
            } else {
                alert('Error al limpiar logs del frontend: ' + (response.data ? response.data.message : 'Error desconocido'));
            }
        }, function(xhr, status, error) {
            button.prop('disabled', false).html(originalText);
            alert('Error de comunicación al limpiar logs');
        });
    }
    
    // Refrescar estadísticas del frontend
    function refreshFrontendStats() {
        var button = $('#refresh-frontend-stats');
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Cargando...');
        
        ajaxRequest('cp_get_frontend_logs', {stats_only: true}, function(response) {
            button.prop('disabled', false).html(originalText);
            
            if (response.success && response.data.stats) {
                updateFrontendStats(response.data.stats);
            }
        }, function(xhr, status, error) {
            button.prop('disabled', false).html(originalText);
        });
    }
    
    // Actualizar estadísticas en el UI
    function updateFrontendStats(stats) {
        $('#total-searches-stat').text(stats.total || 0);
        $('#successful-searches-stat').text(stats.successful || 0);
        $('#failed-searches-stat').text(stats.failed || 0);
        
        // Calcular tasa de éxito
        var successRate = stats.total > 0 ? Math.round((stats.successful / stats.total) * 100) : 0;
        $('#success-rate-stat').text(successRate + '%');
        
        $('#entidades-searches-stat').text(stats.entidades || 0);
        $('#proveedores-searches-stat').text(stats.proveedores || 0);
    }
    
    // Aplicar filtros
    function applyLogsFilters() {
        currentFilters = {
            status: $('#filter-status').val(),
            profile: $('#filter-profile').val(),
            date: $('#filter-date').val()
        };
        
        currentPage = 1;
        refreshFrontendLogs();
    }
    
    // Limpiar filtros
    function clearLogsFilters() {
        $('#filter-status').val('');
        $('#filter-profile').val('');
        $('#filter-date').val('');
        
        currentFilters = {};
        currentPage = 1;
        refreshFrontendLogs();
    }
    
    // Obtener texto de estado
    function getStatusText(status) {
        var statusTexts = {
            'success': 'Exitosa',
            'error': 'Error',
            'partial_success': 'Parcial'
        };
        return statusTexts[status] || status;
    }
    
    // Capitalizar primera letra
    function capitalizeFirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    // ========================================
    // FUNCIONES EXISTENTES (dashboard, config, query)
    // ========================================
    
    function initDashboard() {
        // Auto-ejecutar diagnóstico si no hay extensiones
        if ($('.notice-error').length > 0) {
            setTimeout(function() {
                $('#diagnose-system').trigger('click');
            }, 1000);
        }
        
        // Actualizar estadísticas al cargar
        updateQuickStats();
    }
    
    // Ejecutar diagnóstico del sistema
    $('#diagnose-system').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var resultDiv = $('#diagnosis-result');
        
        button.prop('disabled', true).text('Diagnosticando...');
        resultDiv.html('<div class="loading"><span class="spinner is-active"></span> Analizando sistema...</div>');
        
        ajaxRequest('cp_diagnose_system', {}, function(response) {
            button.prop('disabled', false).text('Ejecutar Diagnóstico');
            
            if (response.success) {
                var diagnosis = response.data.diagnosis;
                var suggestions = response.data.suggestions;
                
                var html = '<div class="diagnosis-report">';
                
                // Mostrar información del sistema
                html += '<h4>Información del Sistema:</h4>';
                html += '<ul>';
                html += '<li><strong>PHP:</strong> ' + diagnosis.php_version + '</li>';
                html += '<li><strong>OS:</strong> ' + diagnosis.os + '</li>';
                html += '<li><strong>OpenSSL:</strong> ' + (diagnosis.openssl ? '✅' : '❌') + '</li>';
                if (diagnosis.docker) {
                    html += '<li><strong>Entorno:</strong> 🐳 Docker detectado</li>';
                }
                html += '</ul>';
                
                // Mostrar extensiones
                html += '<h4>Extensiones SQL Server:</h4>';
                html += '<ul>';
                html += '<li><strong>PDO SQLSRV:</strong> ' + (diagnosis.extensions.pdo_sqlsrv ? '✅ Disponible' : '❌ No disponible') + '</li>';
                html += '<li><strong>SQLSRV:</strong> ' + (diagnosis.extensions.sqlsrv ? '✅ Disponible' : '❌ No disponible') + '</li>';
                html += '</ul>';
                
                // Mostrar sugerencias
                if (suggestions.length > 0) {
                    html += '<h4>Sugerencias:</h4>';
                    html += '<ul class="suggestions-list">';
                    suggestions.forEach(function(suggestion) {
                        html += '<li>' + suggestion + '</li>';
                    });
                    html += '</ul>';
                }
                
                html += '</div>';
                resultDiv.html(html);
            } else {
                resultDiv.html('<div class="error">Error al ejecutar diagnóstico</div>');
            }
        }, function() {
            button.prop('disabled', false).text('Ejecutar Diagnóstico');
            resultDiv.html('<div class="error">Error de comunicación con el servidor</div>');
        });
    });
    
    // Probar conexión (mejorado)
    $('#test-connection, #test-connection-config').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var resultDiv = button.closest('.cp-card').find('[id$="-result"]');
        if (resultDiv.length === 0) {
            resultDiv = $('#connection-result, #config-test-result').first();
        }
        
        button.prop('disabled', true).text(cp_ajax.messages.testing);
        resultDiv.removeClass('success error').html('');
        
        ajaxRequest('cp_test_connection', {}, function(response) {
            button.prop('disabled', false).text('Probar Conexión');
            
            if (response.success) {
                resultDiv.addClass('success').html(
                    '<span class="dashicons dashicons-yes-alt"></span> ' + response.data.message
                );
                
                // Actualizar indicador de estado
                updateConnectionStatus(true, response.data.method);
                
                // Auto-cargar tablas si es desde el dashboard
                if (button.attr('id') === 'test-connection') {
                    setTimeout(function() {
                        $('#load-tables').trigger('click');
                    }, 500);
                }
                
            } else {
                var html = '<span class="dashicons dashicons-dismiss"></span> ' + response.data.message;
                
                if (response.data.suggestions && response.data.suggestions.length > 0) {
                    html += '<div class="error-details">';
                    html += '<h4>Sugerencias:</h4>';
                    html += '<ul class="error-suggestions">';
                    response.data.suggestions.forEach(function(suggestion) {
                        html += '<li>' + suggestion + '</li>';
                    });
                    html += '</ul>';
                    html += '</div>';
                }
                
                resultDiv.addClass('error').html(html);
                updateConnectionStatus(false);
            }
        }, function() {
            button.prop('disabled', false).text('Probar Conexión');
            resultDiv.addClass('error').html(
                '<span class="dashicons dashicons-dismiss"></span> Error de comunicación con el servidor'
            );
            updateConnectionStatus(false);
        });
    });
    
    // Cargar tablas disponibles
    $('#load-tables, #load-tables-query').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var isQueryPage = button.attr('id') === 'load-tables-query';
        var container = isQueryPage ? '#tables-tree' : '#tables-list';
        
        button.prop('disabled', true).text(cp_ajax.messages.loading_tables);
        $(container).html('<div class="loading"><span class="spinner is-active"></span> Cargando...</div>');
        
        ajaxRequest('cp_get_tables', {}, function(response) {
            button.prop('disabled', false).text(isQueryPage ? 'Actualizar Tablas' : 'Actualizar Tablas');
            
            if (response.success) {
                currentTables = response.data.tables;
                var html = '';
                
                if (isQueryPage) {
                    // Vista para página de consultas
                    if (currentTables.length > 0) {
                        currentTables.forEach(function(table) {
                            html += '<div class="table-tree-item" data-table="' + table + '">';
                            html += '<span class="dashicons dashicons-database"></span>';
                            html += '<span class="table-name">' + table + '</span>';
                            html += '<span class="table-actions">';
                            html += '<button class="button-link view-structure" data-table="' + table + '" title="Ver estructura">';
                            html += '<span class="dashicons dashicons-visibility"></span>';
                            html += '</button>';
                            html += '</span>';
                            html += '</div>';
                        });
                    } else {
                        html = '<div class="no-results"><p>No se encontraron tablas</p></div>';
                    }
                } else {
                    // Vista para dashboard
                    html = '<div class="tables-info">';
                    html += '<p><strong>Tablas encontradas: ' + response.data.count + '</strong>';
                    if (response.data.method) {
                        html += ' <em>(usando ' + response.data.method + ')</em>';
                    }
                    html += '</p>';
                    
                    if (currentTables.length > 0) {
                        html += '<div class="tables-grid">';
                        currentTables.forEach(function(table) {
                            html += '<div class="table-item">';
                            html += '<span class="dashicons dashicons-database"></span>';
                            html += '<span class="table-name">' + table + '</span>';
                            html += '</div>';
                        });
                        html += '</div>';
                    }
                    html += '</div>';
                }
                
                $(container).html(html);
                
                // Actualizar estadísticas
                updateQuickStats();
                
            } else {
                $(container).html(
                    '<div class="error"><span class="dashicons dashicons-dismiss"></span> ' + 
                    response.data.message + '</div>'
                );
            }
        }, function() {
            button.prop('disabled', false).text('Cargar Tablas');
            $(container).html(
                '<div class="error"><span class="dashicons dashicons-dismiss"></span> ' +
                'Error de comunicación con el servidor</div>'
            );
        });
    });
    
    // ========================================
    // PÁGINA DE CONFIGURACIÓN DE CONEXIÓN
    // ========================================
    
    function initConfigPage() {
        // Solo aplicar validación en la página de configuración de conexión
        var $configForm = $('form#cp-config-form');
        if ($configForm.length > 0) {
            $configForm.on('submit', function(e) {
                if (!validateConfigForm()) {
                    e.preventDefault();
                    return false;
                }
            });
        }
        
        // Limpiar configuración
        $('#reset-config').on('click', function(e) {
            e.preventDefault();
            
            if (confirm('¿Estás seguro de que quieres limpiar toda la configuración?')) {
                $('input[name^="cp_db_"]').val('');
                $('input[name="cp_db_port"]').val('1433');
                $('#config-test-result').removeClass('success error').html('');
            }
        });
    }
    
    function validateConfigForm() {
        var server = $('input[name="cp_db_server"]').val();
        var database = $('input[name="cp_db_database"]').val();
        var username = $('input[name="cp_db_username"]').val();
        
        if (!server || !database || !username) {
            alert('Por favor, completa todos los campos obligatorios (Servidor, Base de Datos y Usuario).');
            return false;
        }
        
        // Validar formato de servidor
        var ipRegex = /^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/;
        var hostnameRegex = /^[a-zA-Z0-9.-]+$/;
        
        if (!ipRegex.test(server) && !hostnameRegex.test(server)) {
            alert('El formato del servidor no parece válido. Usa una IP (ej: 192.168.1.100) o nombre de host como host.docker.internal');
            return false;
        }
        
        return true;
    }
    
    // ========================================
    // PÁGINA DE CONSULTAS (funciones existentes sin cambios)
    // ========================================
    
    function initQueryPage() {
        // Cargar consultas guardadas al inicializar
        loadSavedQueries();
        
        // Insertar tabla al hacer clic
        $(document).on('click', '.table-tree-item .table-name', function() {
            var tableName = $(this).closest('.table-tree-item').data('table');
            if (tableName) {
                insertTableName(tableName);
            }
        });
        
        // Ver estructura de tabla
        $(document).on('click', '.view-structure', function(e) {
            e.stopPropagation();
            var tableName = $(this).data('table');
            if (tableName) {
                showTableStructure(tableName);
            }
        });
        
        // Ejemplos de consultas
        $('.example-query').on('click', function() {
            var query = $(this).data('query');
            $('#sql-query').val(query).focus();
        });
        
        // Ejecutar consulta
        $('#execute-query').on('click', executeQuery);
        
        // Limpiar editor
        $('#clear-query').on('click', function() {
            if ($('#sql-query').val().trim() !== '') {
                if (confirm('¿Estás seguro de que quieres limpiar el editor?')) {
                    $('#sql-query').val('').focus();
                    clearResults();
                }
            }
        });
        
        // Guardar consulta
        $('#save-query').on('click', function() {
            var sql = $('#sql-query').val().trim();
            if (!sql) {
                alert('No hay consulta para guardar');
                return;
            }
            
            var name = prompt('Nombre para la consulta:');
            if (name) {
                saveQuery(name, sql);
            }
        });
        
        // Exportar resultados
        $(document).on('click', '#export-csv', function() {
            exportResults('csv');
        });
        
        $(document).on('click', '#export-excel', function() {
            exportResults('excel');
        });
        
        $(document).on('click', '#export-json', function() {
            exportResults('json');
        });
        
        // Atajos de teclado
        $('#sql-query').on('keydown', function(e) {
            // Ctrl+Enter o Cmd+Enter para ejecutar
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
                e.preventDefault();
                executeQuery();
            }
            
            // Tab para indentación
            if (e.keyCode === 9) {
                e.preventDefault();
                var textarea = this;
                var start = textarea.selectionStart;
                var end = textarea.selectionEnd;
                var value = textarea.value;
                
                textarea.value = value.substring(0, start) + '    ' + value.substring(end);
                textarea.selectionStart = textarea.selectionEnd = start + 4;
            }
        });
    }
    
    function insertTableName(tableName) {
        var textarea = $('#sql-query')[0];
        if (!textarea) return;
        
        var cursorPos = textarea.selectionStart;
        var textBefore = textarea.value.substring(0, cursorPos);
        var textAfter = textarea.value.substring(cursorPos);
        
        // Insertar nombre de tabla
        textarea.value = textBefore + tableName + textAfter;
        
        // Posicionar cursor después del nombre de tabla
        textarea.selectionStart = textarea.selectionEnd = cursorPos + tableName.length;
        textarea.focus();
    }
    
    function showTableStructure(tableName) {
        ajaxRequest('cp_get_table_structure', {table_name: tableName}, function(response) {
            if (response.success) {
                var html = '<div class="table-structure-modal">';
                html += '<h3>Estructura de la tabla: ' + tableName + '</h3>';
                html += '<table class="widefat">';
                html += '<thead><tr>';
                html += '<th>Columna</th>';
                html += '<th>Tipo</th>';
                html += '<th>Nulo</th>';
                html += '<th>Defecto</th>';
                html += '</tr></thead>';
                html += '<tbody>';
                
                response.data.columns.forEach(function(column) {
                    html += '<tr>';
                    html += '<td><strong>' + column.COLUMN_NAME + '</strong></td>';
                    html += '<td>' + column.DATA_TYPE;
                    if (column.CHARACTER_MAXIMUM_LENGTH) {
                        html += '(' + column.CHARACTER_MAXIMUM_LENGTH + ')';
                    }
                    html += '</td>';
                    html += '<td>' + (column.IS_NULLABLE === 'YES' ? 'Sí' : 'No') + '</td>';
                    html += '<td>' + (column.COLUMN_DEFAULT || '-') + '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                html += '<button class="button" onclick="$(this).closest(\'.table-structure-modal\').remove()">Cerrar</button>';
                html += '</div>';
                
                $('body').append('<div class="cp-modal" style="display: block;"><div class="cp-modal-content">' + html + '</div></div>');
            } else {
                alert('Error al obtener estructura: ' + response.data.message);
            }
        });
    }
    
    function executeQuery() {
        var query = $('#sql-query').val().trim();
        
        if (!query) {
            alert('Por favor, escribe una consulta SQL');
            return;
        }
        
        var button = $('#execute-query');
        var originalHtml = button.html();
        
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Ejecutando...');
        
        clearResults();
        
        ajaxRequest('cp_execute_query', {sql: query}, function(response) {
            button.prop('disabled', false).html(originalHtml);
            
            if (response.success) {
                displayResults(response.data);
            } else {
                displayError(response.data);
            }
        }, function() {
            button.prop('disabled', false).html(originalHtml);
            displayError({
                message: 'Error de comunicación con el servidor',
                execution_time: 0
            });
        });
    }
    
    function displayResults(data) {
        var container = $('#query-results-container');
        
        if (data.data && data.data.length > 0) {
            queryResults = data;
            
            var html = '<div class="results-controls" style="display: flex;">';
            html += '<button id="export-csv" class="button button-secondary">';
            html += '<span class="dashicons dashicons-download"></span> Exportar CSV</button>';
            html += '<button id="export-excel" class="button button-secondary">';
            html += '<span class="dashicons dashicons-media-spreadsheet"></span> Exportar Excel</button>';
            html += '<button id="export-json" class="button button-secondary">';
            html += '<span class="dashicons dashicons-media-code"></span> Exportar JSON</button>';
            html += '<span class="results-info">';
            html += data.total_rows + ' registros en ' + data.execution_time + 's';
            if (data.limited) {
                html += ' <em>(limitado a ' + data.total_rows + ' filas)</em>';
            }
            html += ' <em>(' + data.method + ')</em>';
            html += '</span>';
            html += '</div>';
            
            // Crear tabla de resultados
            html += '<div class="table-responsive">';
            html += '<table class="results-table">';
            
            // Headers
            html += '<thead><tr>';
            data.columns.forEach(function(column) {
                html += '<th>' + escapeHtml(column) + '</th>';
            });
            html += '</tr></thead>';
            
            // Datos
            html += '<tbody>';
            data.data.forEach(function(row, index) {
                html += '<tr>';
                data.columns.forEach(function(column) {
                    var value = row[column];
                    if (value === null || value === undefined) {
                        value = '<em>NULL</em>';
                    } else {
                        value = escapeHtml(String(value));
                    }
                    html += '<td>' + value + '</td>';
                });
                html += '</tr>';
            });
            html += '</tbody></table>';
            html += '</div>';
            
            container.html(html);
            
        } else {
            container.html('<div class="no-results"><span class="dashicons dashicons-info"></span><p>La consulta se ejecutó correctamente pero no devolvió resultados</p><p><small>Tiempo de ejecución: ' + data.execution_time + 's</small></p></div>');
        }
    }
    
    function displayError(data) {
        var container = $('#query-results-container');
        var html = '<div class="error-result">';
        html += '<span class="dashicons dashicons-warning"></span>';
        html += '<h3>Error en la consulta</h3>';
        html += '<p><strong>Mensaje:</strong> ' + escapeHtml(data.message) + '</p>';
        if (data.execution_time) {
            html += '<p><small>Tiempo transcurrido: ' + data.execution_time + 's</small></p>';
        }
        html += '</div>';
        container.html(html);
    }
    
    function clearResults() {
        $('#query-results-container').html('<div class="no-results"><span class="dashicons dashicons-database"></span><p>Ejecuta una consulta para ver los resultados aquí</p></div>');
        $('.results-controls').hide();
        queryResults = null;
    }
    
    function saveQuery(name, sql, description) {
        description = description || '';
        
        ajaxRequest('cp_save_query', {
            name: name,
            sql: sql,
            description: description
        }, function(response) {
            if (response.success) {
                alert('Consulta guardada exitosamente');
                loadSavedQueries();
            } else {
                alert('Error al guardar: ' + response.data.message);
            }
        });
    }
    
    function loadSavedQueries() {
        ajaxRequest('cp_load_saved_queries', {}, function(response) {
            if (response.success) {
                savedQueries = response.data.queries;
                displaySavedQueries();
            }
        });
    }
    
    function displaySavedQueries() {
        var container = $('#saved-queries');
        
        if (savedQueries.length === 0) {
            container.html('<p class="description">No hay consultas guardadas aún.</p>');
            return;
        }
        
        var html = '<div class="saved-queries-list">';
        savedQueries.forEach(function(query) {
            html += '<div class="saved-query-item">';
            html += '<h4>' + escapeHtml(query.name) + '</h4>';
            if (query.description) {
                html += '<p class="description">' + escapeHtml(query.description) + '</p>';
            }
            html += '<div class="query-actions">';
            html += '<button class="button-link load-query" data-sql="' + escapeHtml(query.query_text) + '">Cargar</button>';
            html += '<small> | ' + query.created_at + '</small>';
            html += '</div>';
            html += '</div>';
        });
        html += '</div>';
        
        container.html(html);
        
        // Event handler para cargar consultas
        $('.load-query').on('click', function() {
            var sql = $(this).data('sql');
            $('#sql-query').val(sql);
        });
    }
    
    function exportResults(format) {
        if (!queryResults || !queryResults.data.length) {
            alert('No hay resultados para exportar');
            return;
        }
        
        // Crear datos para exportar
        var exportData = {
            columns: queryResults.columns,
            data: queryResults.data,
            format: format,
            total_rows: queryResults.total_rows,
            execution_time: queryResults.execution_time
        };
        
        // Por ahora, convertir a JSON y descargar
        var dataStr = JSON.stringify(exportData, null, 2);
        var dataBlob = new Blob([dataStr], {type: 'application/json'});
        
        var link = document.createElement('a');
        link.href = URL.createObjectURL(dataBlob);
        link.download = 'consulta_resultados_' + new Date().toISOString().slice(0, 19).replace(/:/g, '-') + '.json';
        link.click();
        
        URL.revokeObjectURL(link.href);
    }
    
    // ========================================
    // MODALES Y UI
    // ========================================
    
    function initModals() {
        // Mostrar/ocultar instrucciones de instalación
        $('#show-install-instructions').on('click', function(e) {
            e.preventDefault();
            $('#install-instructions').show();
        });
        
        // Cerrar modal
        $('.cp-modal-close, .cp-modal').on('click', function(e) {
            if (e.target === this) {
                $('.cp-modal').hide();
            }
        });
        
        // Manejo de tabs
        $('.tab-button').on('click', function(e) {
            e.preventDefault();
            
            var targetTab = $(this).data('tab');
            var tabContainer = $(this).closest('.install-tabs').parent();
            
            // Remover clase activa
            tabContainer.find('.tab-button').removeClass('active');
            tabContainer.find('.tab-content').removeClass('active');
            
            // Agregar clase activa
            $(this).addClass('active');
            tabContainer.find('#' + targetTab + '-tab').addClass('active');
        });
    }
    
    // ========================================
    // EVENTOS GLOBALES
    // ========================================
    
    function bindGlobalEvents() {
        // Actualizar todo el dashboard
        $('#refresh-dashboard').on('click', function(e) {
            e.preventDefault();
            location.reload();
        });
        
        // Validación mejorada en campos de configuración - SOLO EN PÁGINA DE CONFIGURACIÓN
        if ($('input[name="cp_db_server"]').length > 0) {
            $('input[name="cp_db_server"]').on('blur', function() {
                validateServerField($(this));
            });
            
            $('input[name="cp_db_port"]').on('input', function() {
                validatePortField($(this));
            });
        }
        
        // Prevenir envío accidental de formularios con Enter
        $('input[type="text"], input[type="password"]').on('keypress', function(e) {
            if (e.which === 13 && !$(this).closest('form').find('input[type="submit"]').is(':focus')) {
                e.preventDefault();
            }
        });
    }
    
    function validateServerField($field) {
        var value = $field.val();
        var ipRegex = /^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/;
        var hostnameRegex = /^[a-zA-Z0-9.-]+$/;
        
        $field.parent().find('.field-error').remove();
        
        if (value && !ipRegex.test(value) && !hostnameRegex.test(value)) {
            $field.css('border-color', '#dc3232');
            $field.next('.description').after('<span class="field-error">Formato de servidor inválido. Usa IP o nombre de host como host.docker.internal</span>');
        } else {
            $field.css('border-color', '');
        }
    }
    
    function validatePortField($field) {
        var value = parseInt($field.val());
        $field.parent().find('.field-error').remove();
        
        if (value < 1 || value > 65535) {
            $field.css('border-color', '#dc3232');
            $field.next('.description').after('<span class="field-error">El puerto debe estar entre 1 y 65535.</span>');
        } else {
            $field.css('border-color', '');
        }
    }
    
    // ========================================
    // UTILIDADES
    // ========================================
    
    function ajaxRequest(action, data, successCallback, errorCallback) {
        data = data || {};
        data.action = action;
        data.nonce = cp_ajax.nonce;
        
        // Verificar que tenemos nonce
        if (!cp_ajax.nonce) {
            console.error('CP: No hay nonce disponible!');
            if (typeof errorCallback === 'function') {
                errorCallback();
            }
            return;
        }
        
        $.ajax({
            url: cp_ajax.url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (typeof successCallback === 'function') {
                    successCallback(response);
                }
            },
            error: function(xhr, status, error) {
                console.error('CP: Error en petición AJAX:', action, {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                if (typeof errorCallback === 'function') {
                    errorCallback(xhr, status, error);
                }
            }
        });
    }
    
    function updateConnectionStatus(connected, method) {
        var indicator = $('#connection-status-indicator');
        if (connected) {
            indicator.text('✅').attr('title', 'Conectado: ' + (method || ''));
        } else {
            indicator.text('❌').attr('title', 'Desconectado');
        }
    }
    
    function updateQuickStats() {
        var tablesCount = currentTables.length || '-';
        $('#tables-count').text(tablesCount);
    }
    
    function escapeHtml(text) {
        if (typeof text !== 'string') {
            return text;
        }
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // ========================================
    // INICIALIZACIÓN FINAL
    // ========================================
    
    // Configurar tooltips si están disponibles
    if (typeof $.fn.tooltip === 'function') {
        $('[title]').tooltip();
    }
    
    // Mensaje de bienvenida en consola
    console.log('CP: Consulta Procesos v' + (cp_ajax.version || '1.2.0') + ' cargado correctamente');
    console.log('CP: Variables disponibles:', {
        cp_ajax: typeof cp_ajax !== 'undefined' ? cp_ajax : 'NO DISPONIBLE',
        jquery: typeof $ !== 'undefined' ? 'DISPONIBLE' : 'NO DISPONIBLE',
        url_actual: window.location.href
    });
    
    // Verificar que cp_ajax esté disponible
    if (typeof cp_ajax === 'undefined') {
        console.error('CP: ¡CRÍTICO! cp_ajax no está definido. Los botones AJAX no funcionarán.');
        alert('Error: Scripts de administración no cargados correctamente. Por favor, recarga la página.');
        return;
    }
    
    // CSS dinámico para campos de validación
    var dynamicCSS = `
        <style>
        .error-field {
            border-color: #d63638 !important;
            box-shadow: 0 0 0 1px #d63638;
        }
        
        .valid-field {
            border-color: #00a32a !important;
            box-shadow: 0 0 0 1px #00a32a;
        }
        
        .warning-field {
            border-color: #dba617 !important;
            box-shadow: 0 0 0 1px #dba617;
        }
        
        .field-error {
            color: #d63638;
            font-size: 12px;
            display: block;
            margin-top: 5px;
        }
        
        .field-warning {
            color: #dba617;
            font-size: 12px;
            display: block;
            margin-top: 5px;
        }
        
        .cp-api-config {
            transition: all 0.3s ease;
        }
        
        .cp-method-radio {
            margin-bottom: 5px;
        }
        
        .status-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-badge.success {
            background-color: #00a32a;
            color: white;
        }
        
        .status-badge.error {
            background-color: #d63638;
            color: white;
        }
        
        .status-badge.partial_success {
            background-color: #dba617;
            color: white;
        }
        
        .frontend-logs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .frontend-logs-table th,
        .frontend-logs-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            font-size: 12px;
        }
        
        .frontend-logs-table th {
            background-color: #f1f1f1;
            font-weight: bold;
        }
        
        .truncated-cell {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .log-error {
            color: #d63638;
            font-style: italic;
        }
        
        .loading-logs {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        .no-logs-message {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .no-logs-message .dashicons {
            font-size: 48px;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .results-table th,
        .results-table td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }
        
        .results-table th {
            background-color: #f1f1f1;
            font-weight: bold;
        }
        
        .table-responsive {
            overflow-x: auto;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
        }
        
        .sp-results-success,
        .admin-results-success {
            background: #f0f6fc;
            border: 1px solid #0073aa;
            border-radius: 4px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .sp-results-success h4,
        .admin-results-success h4 {
            margin-top: 0;
            color: #0073aa;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        .error {
            background: #fbeaea;
            border: 1px solid #d63638;
            border-radius: 4px;
            padding: 15px;
            margin-top: 10px;
            color: #d63638;
        }
        
        #logs-pagination {
            margin-top: 15px;
            text-align: center;
        }
        
        #logs-pagination button {
            margin: 0 5px;
        }
        
        .cp-cache-stats {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .cp-cache-stats h4 {
            margin-top: 0;
        }
        </style>
    `;
    
    // Inyectar CSS dinámico
    $('head').append(dynamicCSS);
});