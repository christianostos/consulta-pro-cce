jQuery(document).ready(function($) {
    
    // Variables globales
    var currentTables = [];
    var queryResults = null;
    var savedQueries = [];
    
    // Inicialización
    init();
    
    function init() {
        initDashboard();
        initConfigPage();
        initQueryPage();
        initModals();
        bindGlobalEvents();
    }
    
    // ========================================
    // DASHBOARD PRINCIPAL
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
    // PÁGINA DE CONFIGURACIÓN
    // ========================================
    
    function initConfigPage() {
        // Validación en tiempo real mejorada
        $('form#cp-config-form').on('submit', function(e) {
            if (!validateConfigForm()) {
                e.preventDefault();
                return false;
            }
        });
        
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
    // PÁGINA DE CONSULTAS
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
        
        // Validar que sea una consulta SELECT
        if (!query.toLowerCase().match(/^\s*select\s+/i)) {
            alert('Por seguridad, solo se permiten consultas SELECT');
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
        
        // Validación mejorada en campos de configuración
        $('input[name="cp_db_server"]').on('blur', function() {
            validateServerField($(this));
        });
        
        $('input[name="cp_db_port"]').on('input', function() {
            validatePortField($(this));
        });
        
        // Prevenir envío accidental de formularios con Enter
        $('input[type="text"], input[type="password"]').on('keypress', function(e) {
            if (e.which === 13 && !$(this).closest('form').find('input[type="submit"]').is(':focus')) {
                e.preventDefault();
            }
        });
        
        // Validación en tiempo real de campos de configuración (original)
        $('form').on('submit', function(e) {
            var server = $('input[name="cp_db_server"]').val();
            var database = $('input[name="cp_db_database"]').val();
            var username = $('input[name="cp_db_username"]').val();
            
            if (!server || !database || !username) {
                alert('Por favor, completa todos los campos obligatorios (Servidor, Base de Datos y Usuario).');
                e.preventDefault();
                return false;
            }
            
            // Validar formato de IP
            var ipRegex = /^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/;
            var hostnameRegex = /^[a-zA-Z0-9.-]+$/;
            
            if (!ipRegex.test(server) && !hostnameRegex.test(server)) {
                alert('El formato del servidor no parece válido. Usa una IP (ej: 192.168.1.100) o nombre de host como host.docker.internal');
                e.preventDefault();
                return false;
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
        
        $.ajax({
            url: cp_ajax.url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (typeof successCallback === 'function') {
                    successCallback(response);
                }
            },
            error: function() {
                if (typeof errorCallback === 'function') {
                    errorCallback();
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
    
    // ========================================
    // INICIALIZACIÓN FINAL
    // ========================================
    
    // Configurar tooltips si están disponibles
    if (typeof $.fn.tooltip === 'function') {
        $('[title]').tooltip();
    }
    
    // Mensaje de bienvenida en consola
    console.log('Consulta Procesos v' + (cp_ajax.version || '1.1.0') + ' cargado correctamente');
    
    // Debug: mostrar variables globales en consola
    if (typeof cp_ajax.debug !== 'undefined' && cp_ajax.debug) {
        console.log('Variables globales:', {
            currentTables: currentTables,
            queryResults: queryResults,
            savedQueries: savedQueries
        });
    }
});