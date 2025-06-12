jQuery(document).ready(function($) {
    
    // Ejecutar diagnóstico del sistema
    $('#diagnose-system').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var resultDiv = $('#diagnosis-result');
        
        // Deshabilitar botón y mostrar estado de carga
        button.prop('disabled', true).text('Diagnosticando...');
        resultDiv.html('<div class="loading"><span class="spinner is-active"></span> Analizando sistema...</div>');
        
        // Realizar petición AJAX
        $.ajax({
            url: cp_ajax.url,
            type: 'POST',
            data: {
                action: 'cp_diagnose_system',
                nonce: cp_ajax.nonce
            },
            success: function(response) {
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
            },
            error: function() {
                button.prop('disabled', false).text('Ejecutar Diagnóstico');
                resultDiv.html('<div class="error">Error de comunicación con el servidor</div>');
            }
        });
    });
    
    // Probar conexión (actualizado para mostrar más información)
    $('#test-connection').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var resultDiv = $('#connection-result');
        
        // Deshabilitar botón y mostrar estado de carga
        button.prop('disabled', true).text(cp_ajax.messages.testing);
        resultDiv.removeClass('success error').html('');
        
        // Realizar petición AJAX
        $.ajax({
            url: cp_ajax.url,
            type: 'POST',
            data: {
                action: 'cp_test_connection',
                nonce: cp_ajax.nonce
            },
            success: function(response) {
                button.prop('disabled', false).text('Probar Conexión');
                
                if (response.success) {
                    resultDiv.addClass('success').html(
                        '<span class="dashicons dashicons-yes-alt"></span> ' + response.data.message
                    );
                } else {
                    var html = '<span class="dashicons dashicons-dismiss"></span> ' + response.data.message;
                    
                    // Mostrar diagnóstico si está disponible
                    if (response.data.diagnosis) {
                        html += '<div class="error-details">';
                        html += '<h4>Diagnóstico:</h4>';
                        
                        if (response.data.suggestions && response.data.suggestions.length > 0) {
                            html += '<ul class="error-suggestions">';
                            response.data.suggestions.forEach(function(suggestion) {
                                html += '<li>' + suggestion + '</li>';
                            });
                            html += '</ul>';
                        }
                        html += '</div>';
                    }
                    
                    resultDiv.addClass('error').html(html);
                }
            },
            error: function() {
                button.prop('disabled', false).text('Probar Conexión');
                resultDiv.addClass('error').html(
                    '<span class="dashicons dashicons-dismiss"></span> Error de comunicación con el servidor'
                );
            }
        });
    });
    
    // Cargar tablas disponibles (sin cambios significativos)
    $('#load-tables').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var tablesList = $('#tables-list');
        
        // Deshabilitar botón y mostrar estado de carga
        button.prop('disabled', true).text(cp_ajax.messages.loading_tables);
        tablesList.html('<div class="loading"><span class="spinner is-active"></span> Cargando...</div>');
        
        // Realizar petición AJAX
        $.ajax({
            url: cp_ajax.url,
            type: 'POST',
            data: {
                action: 'cp_get_tables',
                nonce: cp_ajax.nonce
            },
            success: function(response) {
                button.prop('disabled', false).text('Actualizar Tablas');
                
                if (response.success) {
                    var tables = response.data.tables;
                    var html = '<div class="tables-info">';
                    html += '<p><strong>Tablas encontradas: ' + response.data.count + '</strong>';
                    if (response.data.method) {
                        html += ' <em>(usando ' + response.data.method + ')</em>';
                    }
                    html += '</p>';
                    
                    if (tables.length > 0) {
                        html += '<div class="tables-grid">';
                        tables.forEach(function(table) {
                            html += '<div class="table-item">';
                            html += '<span class="dashicons dashicons-database"></span>';
                            html += '<span class="table-name">' + table + '</span>';
                            html += '</div>';
                        });
                        html += '</div>';
                    } else {
                        html += '<p>No se encontraron tablas en la base de datos.</p>';
                    }
                    html += '</div>';
                    
                    tablesList.html(html);
                } else {
                    tablesList.html(
                        '<div class="error"><span class="dashicons dashicons-dismiss"></span> ' + 
                        response.data.message + '</div>'
                    );
                }
            },
            error: function() {
                button.prop('disabled', false).text('Cargar Tablas');
                tablesList.html(
                    '<div class="error"><span class="dashicons dashicons-dismiss"></span> ' +
                    'Error de comunicación con el servidor</div>'
                );
            }
        });
    });
    
    // Mostrar/ocultar instrucciones de instalación
    $('#show-install-instructions').on('click', function(e) {
        e.preventDefault();
        $('#install-instructions').slideToggle();
    });
    
    // Manejo de tabs en instrucciones de instalación
    $('.tab-button').on('click', function(e) {
        e.preventDefault();
        
        var targetTab = $(this).data('tab');
        
        // Remover clase activa de todos los botones y contenidos
        $('.tab-button').removeClass('active');
        $('.tab-content').removeClass('active');
        
        // Agregar clase activa al botón clickeado y su contenido
        $(this).addClass('active');
        $('#' + targetTab + '-tab').addClass('active');
    });
    
    // Validación en tiempo real de campos de configuración (mejorada)
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
            alert('El formato del servidor no parece válido. Usa una IP (ej: 192.168.1.100) o nombre de host.');
            e.preventDefault();
            return false;
        }
    });
    
    // Mejorar experiencia de usuario en campos de configuración
    $('input[name="cp_db_server"]').on('blur', function() {
        var value = $(this).val();
        var ipRegex = /^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/;
        var hostnameRegex = /^[a-zA-Z0-9.-]+$/;
        
        $(this).parent().find('.field-error').remove();
        
        if (value && !ipRegex.test(value) && !hostnameRegex.test(value)) {
            $(this).css('border-color', '#dc3232');
            $(this).next('.description').after('<span class="field-error">Formato de servidor inválido. Usa IP o nombre de host.</span>');
        } else {
            $(this).css('border-color', '');
        }
    });
    
    $('input[name="cp_db_port"]').on('input', function() {
        var value = parseInt($(this).val());
        $(this).parent().find('.field-error').remove();
        
        if (value < 1 || value > 65535) {
            $(this).css('border-color', '#dc3232');
            $(this).next('.description').after('<span class="field-error">El puerto debe estar entre 1 y 65535.</span>');
        } else {
            $(this).css('border-color', '');
        }
    });
    
    // Auto-ejecutar diagnóstico al cargar la página si no hay extensiones
    if ($('.notice-error').length > 0) {
        setTimeout(function() {
            $('#diagnose-system').trigger('click');
        }, 1000);
    }
});