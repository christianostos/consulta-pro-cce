/**
 * JavaScript del frontend para el plugin Consulta Procesos
 * Archivo: assets/js/frontend.js
 * AGREGADO: Indicador de progreso para consultas
 */

jQuery(document).ready(function($) {
    
    // Variables globales
    var currentStep = 1;
    var maxSteps = 3;
    var selectedProfile = '';
    var formData = {};
    var searchInProgress = false;
    var currentSearchId = null;
    var progressInterval = null;
    
    // Inicialización
    init();
    
    function init() {
        bindEvents();
        updateProgressBar();
        initializeDateValidation();
        setupProgressContainer();
    }
    
    /**
     * Vincular eventos
     */
    function bindEvents() {
        // Botones de navegación
        $('#cp-continue-to-profile').on('click', continueToProfile);
        $('#cp-back-to-terms').on('click', backToTerms);
        $('#cp-continue-to-search').on('click', continueToSearch);
        $('#cp-back-to-profile').on('click', backToProfile);
        
        // Selección de perfil
        $('input[name="profile_type"]').on('change', handleProfileSelection);
        
        // Envío del formulario de búsqueda
        $('#cp-search-form').on('submit', handleSearchSubmit);
        
        // NUEVO: Cancelar búsqueda
        $('#cp-cancel-search').on('click', cancelSearch);
        
        // Validación en tiempo real
        $('#cp-accept-terms').on('change', validateTermsStep);
        $('#cp-fecha-inicio, #cp-fecha-fin').on('change', validateDateRange);
        $('#cp-numero-documento').on('input', validateDocumentNumber);
        
        // Navegación por pasos (clickeable)
        $('.cp-progress-step').on('click', handleStepClick);
    }
    
    /**
     * Continuar a la selección de perfil
     */
    function continueToProfile() {
        if (validateTermsStep()) {
            goToStep(2);
        }
    }
    
    /**
     * Volver a términos
     */
    function backToTerms() {
        goToStep(1);
    }
    
    /**
     * Continuar a búsqueda
     */
    function continueToSearch() {
        if (validateProfileStep()) {
            goToStep(3);
            updateSelectedProfileInfo();
        }
    }
    
    /**
     * Volver a perfil
     */
    function backToProfile() {
        goToStep(2);
    }
    
    /**
     * Navegar a un paso específico
     */
    function goToStep(step) {
        if (step < 1 || step > maxSteps) {
            return false;
        }
        
        // Validar que se puede ir al paso solicitado
        if (!canGoToStep(step)) {
            return false;
        }
        
        // Ocultar paso actual
        $('.cp-form-step').removeClass('active');
        $('.cp-progress-step').removeClass('active');
        
        // Mostrar nuevo paso
        $('.cp-step-' + step).addClass('active');
        $('.cp-progress-step[data-step="' + step + '"]').addClass('active');
        
        // Marcar pasos completados
        for (let i = 1; i < step; i++) {
            $('.cp-progress-step[data-step="' + i + '"]').addClass('completed');
        }
        
        currentStep = step;
        
        // Scroll al top del formulario
        $('html, body').animate({
            scrollTop: $('#cp-frontend-form').offset().top - 50
        }, 500);
        
        return true;
    }
    
    /**
     * Verificar si se puede ir a un paso específico
     */
    function canGoToStep(step) {
        switch (step) {
            case 1:
                return true;
            case 2:
                return validateTermsStep();
            case 3:
                return validateTermsStep() && validateProfileStep();
            default:
                return false;
        }
    }
    
    /**
     * Manejar click en pasos del progreso
     */
    function handleStepClick(e) {
        var targetStep = parseInt($(this).data('step'));
        
        if (targetStep && targetStep !== currentStep) {
            goToStep(targetStep);
        }
    }
    
    /**
     * Actualizar barra de progreso
     */
    function updateProgressBar() {
        $('.cp-progress-step').each(function() {
            var stepNumber = parseInt($(this).data('step'));
            
            $(this).removeClass('active completed');
            
            if (stepNumber === currentStep) {
                $(this).addClass('active');
            } else if (stepNumber < currentStep) {
                $(this).addClass('completed');
            }
        });
    }
    
    /**
     * Validar paso de términos
     */
    function validateTermsStep() {
        var accepted = $('#cp-accept-terms').is(':checked');
        
        if (!accepted) {
            showError(cp_frontend_ajax.messages.accept_terms);
            $('#cp-accept-terms').focus();
            return false;
        }
        
        return true;
    }
    
    /**
     * Validar paso de perfil
     */
    function validateProfileStep() {
        var selected = $('input[name="profile_type"]:checked').val();
        
        if (!selected) {
            showError(cp_frontend_ajax.messages.select_profile);
            return false;
        }
        
        selectedProfile = selected;
        return true;
    }
    
    /**
     * Manejar selección de perfil
     */
    function handleProfileSelection() {
        selectedProfile = $(this).val();
        
        // Habilitar botón continuar
        $('#cp-continue-to-search').prop('disabled', false);
        
        // Efecto visual
        $('.cp-radio-card').removeClass('selected');
        $(this).closest('.cp-radio-card').addClass('selected');
    }
    
    /**
     * Actualizar información del perfil seleccionado
     */
    function updateSelectedProfileInfo() {
        var profileText = selectedProfile === 'entidades' ? 'Entidades' : 'Proveedores';
        $('#cp-selected-profile').text(profileText);
    }
    
    /**
     * Inicializar validación de fechas
     */
    function initializeDateValidation() {
        // Establecer fecha máxima como hoy
        var today = new Date().toISOString().split('T')[0];
        $('#cp-fecha-inicio, #cp-fecha-fin').attr('max', today);
        
        // Establecer fecha mínima como hace 5 años
        var fiveYearsAgo = new Date();
        fiveYearsAgo.setFullYear(fiveYearsAgo.getFullYear() - 5);
        var minDate = fiveYearsAgo.toISOString().split('T')[0];
        $('#cp-fecha-inicio, #cp-fecha-fin').attr('min', minDate);
    }
    
    /**
     * Validar rango de fechas
     */
    function validateDateRange() {
        var fechaInicio = $('#cp-fecha-inicio').val();
        var fechaFin = $('#cp-fecha-fin').val();
        
        if (fechaInicio && fechaFin) {
            var inicio = new Date(fechaInicio);
            var fin = new Date(fechaFin);
            
            if (inicio > fin) {
                showError('La fecha de inicio no puede ser mayor que la fecha de fin');
                $('#cp-fecha-fin').focus();
                return false;
            }
            
            // Verificar que no sea un rango muy amplio (máximo 5 años)
            var diffTime = Math.abs(fin - inicio);
            var maxYears = 5;
            var maxDiff = maxYears * 365 * 24 * 60 * 60 * 1000; // 5 años en milisegundos
            if (diffTime > maxDiff) {
                console.warn("El rango de fechas no puede ser mayor a 5 años.");
            }
        }
        
        return true;
    }
    
    /**
     * Validar número de documento
     */
    function validateDocumentNumber() {
        var numero = $(this).val();
        
        // Validar que sea un número positivo
        if (numero && (isNaN(numero) || parseInt(numero) <= 0)) {
            $(this).addClass('invalid');
            return false;
        } else {
            $(this).removeClass('invalid');
            return true;
        }
    }
    
    /**
     * Manejar envío del formulario de búsqueda
     */
    function handleSearchSubmit(e) {
        e.preventDefault();
        
        if (!validateSearchForm()) {
            return false;
        }
        
        if (searchInProgress) {
            return false;
        }
        
        var formData = {
            action: 'cp_process_search_form',
            nonce: cp_frontend_ajax.nonce,
            profile_type: selectedProfile,
            fecha_inicio: $('#cp-fecha-inicio').val(),
            fecha_fin: $('#cp-fecha-fin').val(),
            numero_documento: $('#cp-numero-documento').val()
        };
        
        performSearch(formData);
    }
    
    /**
     * Validar formulario de búsqueda
     */
    function validateSearchForm() {
        var fechaInicio = $('#cp-fecha-inicio').val();
        var fechaFin = $('#cp-fecha-fin').val();
        var numeroDocumento = $('#cp-numero-documento').val();
        
        // Validar campos requeridos
        if (!fechaInicio || !fechaFin || !numeroDocumento) {
            showError(cp_frontend_ajax.messages.fill_dates);
            return false;
        }
        
        // Validar rango de fechas
        if (!validateDateRange()) {
            return false;
        }
        
        // Validar número de documento
        if (!validateDocumentNumber.call($('#cp-numero-documento')[0])) {
            showError('El número de documento debe ser un número válido');
            return false;
        }
        
        return true;
    }
    
    /**
     * NUEVO: Configurar contenedor de progreso
     */
    function setupProgressContainer() {
        // Ya está en el HTML, no necesita configuración adicional
    }
    
    /**
     * MODIFICADO: Realizar búsqueda con indicador de progreso
     */
    function performSearch(data) {
        var $button = $('#cp-search-submit');
        var $buttonText = $button.find('.cp-btn-text');
        var $buttonSpinner = $button.find('.cp-btn-spinner');
        var $resultsContainer = $('.cp-results-container');
        
        // Verificar si ya hay una búsqueda en progreso
        if (searchInProgress) {
            log('Búsqueda ya en progreso, ignorando nueva solicitud');
            return false;
        }
        
        // Marcar búsqueda en progreso
        searchInProgress = true;
        
        // Estado de carga del botón
        $button.prop('disabled', true);
        $buttonText.hide();
        $buttonSpinner.show();
        
        // Ocultar resultados anteriores
        $resultsContainer.hide();
        
        log('Iniciando nueva búsqueda...');
        
        // Realizar petición AJAX inicial
        $.ajax({
            url: cp_frontend_ajax.url,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data.search_started) {
                    // Búsqueda iniciada, comenzar seguimiento de progreso
                    currentSearchId = response.data.search_id;
                    log('Búsqueda iniciada con ID: ' + currentSearchId);
                    showProgressIndicator();
                    startProgressPolling();
                } else {
                    // Error en el inicio
                    showError(response.data.message || cp_frontend_ajax.messages.error);
                    resetSearchState();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error iniciando búsqueda:', error);
                showError(cp_frontend_ajax.messages.error + ': ' + error);
                resetSearchState();
            }
        });
    }
    
    /**
     * NUEVO: Mostrar indicador de progreso
     */
    function showProgressIndicator() {
        // Ocultar formulario de búsqueda
        $('.cp-form-step.active').hide();
        
        // Mostrar contenedor de progreso
        $('.cp-search-progress-container').show();
        
        // Scroll al indicador de progreso
        $('html, body').animate({
            scrollTop: $('.cp-search-progress-container').offset().top - 50
        }, 500);
        
        log('Mostrando indicador de progreso para búsqueda: ' + currentSearchId);
    }
    
    /**
     * NUEVO: Iniciar polling de progreso
     */
    function startProgressPolling() {
        if (!currentSearchId) {
            log('No hay search ID, cancelando polling');
            return;
        }
        
        // Detener cualquier polling anterior
        if (progressInterval) {
            clearInterval(progressInterval);
            progressInterval = null;
        }
        
        log('Iniciando polling de progreso para ID: ' + currentSearchId);
        
        // Crear las fuentes de datos dinámicamente
        setupProgressSources();
        
        var pollCount = 0;
        var maxPolls = 360; // Máximo 2 minutos (120 segundos)
        
        // Comenzar polling cada 1 segundo
        progressInterval = setInterval(function() {
            pollCount++;
            
            if (pollCount > maxPolls) {
                log('Timeout de polling alcanzado, obteniendo resultados parciales');
                
                // Obtener el último estado antes del timeout
                $.ajax({
                    url: cp_frontend_ajax.url,
                    type: 'POST',
                    data: {
                        action: 'cp_get_search_progress',
                        nonce: cp_frontend_ajax.nonce,
                        search_id: currentSearchId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.data) {
                            handleSearchTimeout(response.data);
                        } else {
                            handleSearchError({
                                message: 'Timeout: La búsqueda tomó demasiado tiempo'
                            });
                        }
                    },
                    error: function() {
                        handleSearchError({
                            message: 'Timeout: La búsqueda tomó demasiado tiempo'
                        });
                    }
                });
                
                return;
            }
            
            if (!searchInProgress || !currentSearchId) {
                log('Búsqueda ya no está en progreso, deteniendo polling');
                stopProgressPolling();
                return;
            }
            
            checkSearchProgress();
        }, 1000);
        
        // También verificar inmediatamente
        checkSearchProgress();
    }
    
    /**
     * NUEVO: Configurar fuentes de progreso dinámicamente
     */
    function setupProgressSources() {
        // Las fuentes se configurarán dinámicamente cuando recibamos la primera respuesta
        // del servidor con los datos de las fuentes activas
    }
    
    /**
     * NUEVO: Detener polling de progreso
     */
    function stopProgressPolling() {
        if (progressInterval) {
            clearInterval(progressInterval);
            progressInterval = null;
            log('Polling de progreso detenido');
        }
    }
    
    /**
     * NUEVO: Verificar progreso de búsqueda
     */
    function checkSearchProgress() {
        if (!currentSearchId || !searchInProgress) {
            log('No hay búsqueda activa, deteniendo verificación');
            stopProgressPolling();
            return;
        }
        
        // Evitar múltiples llamadas AJAX simultáneas
        if (window.cpCheckInProgress) {
            log('Verificación de progreso ya en curso, saltando...');
            return;
        }
        
        window.cpCheckInProgress = true;
        
        $.ajax({
            url: cp_frontend_ajax.url,
            type: 'POST',
            data: {
                action: 'cp_get_search_progress',
                nonce: cp_frontend_ajax.nonce,
                search_id: currentSearchId
            },
            dataType: 'json',
            timeout: 10000, // 10 segundos de timeout para la llamada AJAX
            success: function(response) {
                window.cpCheckInProgress = false;
                
                if (response.success && response.data) {
                    updateProgressDisplay(response.data);
                    
                    // Si la búsqueda está completada, detener polling
                    if (response.data.status === 'completed') {
                        stopProgressPolling();
                        completeSearch(response.data);
                    } else if (response.data.status === 'error') {
                        stopProgressPolling();
                        handleSearchError(response.data);
                    }
                } else {
                    console.error('Error obteniendo progreso:', response.data ? response.data.message : 'Respuesta inválida');
                    // No detener el polling aquí, podría ser un error temporal
                }
            },
            error: function(xhr, status, error) {
                window.cpCheckInProgress = false;
                console.error('Error en polling de progreso:', error);
                
                // Si hay muchos errores consecutivos, detener polling
                if (!window.cpErrorCount) {
                    window.cpErrorCount = 0;
                }
                window.cpErrorCount++;
                
                if (window.cpErrorCount > 5) {
                    log('Demasiados errores consecutivos, deteniendo polling');
                    handleSearchError({
                        message: 'Error de conectividad: No se puede obtener el progreso de la búsqueda'
                    });
                }
            }
        });
    }
    
    /**
     * NUEVO: Actualizar visualización de progreso
     */
    function updateProgressDisplay(progressData) {
        // Actualizar barra de progreso general
        $('.cp-progress-bar-fill').css('width', progressData.overall_progress + '%');
        $('.cp-progress-percentage').text(progressData.overall_progress + '%');
        
        // Actualizar mensaje principal
        $('.cp-search-progress-header h3').text(progressData.message || 'Procesando...');
        
        // Log detallado del estado actual
        log('Progreso actualizado: ' + progressData.overall_progress + '% - ' + (progressData.message || 'Sin mensaje'));
        
        if (progressData.sources_progress) {
            Object.keys(progressData.sources_progress).forEach(function(source) {
                var sourceData = progressData.sources_progress[source];
                log(`  ${source}: ${sourceData.status} (${sourceData.progress}%) - ${sourceData.records_found} registros - ${sourceData.message}`);
            });
        }
        
        // Crear o actualizar indicadores de fuentes
        updateSourcesProgress(progressData.sources_progress || {}, progressData.active_sources || []);
    }
    
    /**
     * NUEVO: Actualizar progreso de fuentes de datos
     */
    function updateSourcesProgress(sourcesProgress, activeSources) {
        var $container = $('.cp-search-sources-progress');
        
        // Si es la primera vez, crear los elementos
        if ($container.children().length === 0) {
            activeSources.forEach(function(source) {
                var sourceTitle = getSourceTitle(source);
                var sourceHtml = `
                    <div class="cp-source-progress" data-source="${source}">
                        <div class="cp-source-header">
                            <span class="cp-source-icon">
                                <span class="dashicons dashicons-database"></span>
                            </span>
                            <span class="cp-source-title">${sourceTitle}</span>
                            <span class="cp-source-status">Pendiente</span>
                        </div>
                        <div class="cp-source-progress-bar">
                            <div class="cp-source-progress-fill" style="width: 0%"></div>
                        </div>
                        <div class="cp-source-details">
                            <span class="cp-source-message">Preparando consulta...</span>
                            <span class="cp-source-records">0 registros</span>
                        </div>
                    </div>
                `;
                $container.append(sourceHtml);
            });
        }
        
        // Actualizar progreso de cada fuente SIN resetear las que no cambiaron
        Object.keys(sourcesProgress).forEach(function(source) {
            var sourceData = sourcesProgress[source];
            var $sourceElement = $container.find('[data-source="' + source + '"]');
            
            if ($sourceElement.length > 0) {
                // Obtener estado actual para evitar actualizaciones innecesarias
                var currentStatus = $sourceElement.attr('data-current-status') || '';
                var currentProgress = parseInt($sourceElement.attr('data-current-progress') || '0');
                var currentRecords = parseInt($sourceElement.attr('data-current-records') || '0');
                
                // Solo actualizar si realmente cambió algo
                if (currentStatus !== sourceData.status || 
                    currentProgress !== sourceData.progress || 
                    currentRecords !== sourceData.records_found) {
                    
                    // Actualizar atributos de estado actual
                    $sourceElement.attr('data-current-status', sourceData.status);
                    $sourceElement.attr('data-current-progress', sourceData.progress);
                    $sourceElement.attr('data-current-records', sourceData.records_found);
                    
                    // Actualizar estado visual solo si cambió
                    if (currentStatus !== sourceData.status) {
                        $sourceElement.removeClass('pending running completed error')
                                   .addClass(sourceData.status);
                        
                        // Actualizar icono según estado
                        var iconClass = getStatusIcon(sourceData.status);
                        $sourceElement.find('.cp-source-icon .dashicons')
                                     .removeClass('dashicons-database dashicons-update-alt dashicons-yes-alt dashicons-warning')
                                     .addClass(iconClass);
                        
                        // Actualizar texto de estado
                        $sourceElement.find('.cp-source-status').text(getStatusText(sourceData.status));
                    }
                    
                    // Actualizar mensaje
                    $sourceElement.find('.cp-source-message').text(sourceData.message || '');
                    
                    // Actualizar contador de registros
                    $sourceElement.find('.cp-source-records').text(sourceData.records_found + ' registros');
                    
                    // Actualizar barra de progreso con animación suave
                    var $progressFill = $sourceElement.find('.cp-source-progress-fill');
                    $progressFill.css('width', sourceData.progress + '%');
                    
                    log(`Fuente ${source} actualizada: ${sourceData.status} (${sourceData.progress}%) - ${sourceData.records_found} registros`);
                }
            }
        });
    }
    
    /**
     * NUEVO: Obtener texto de estado
     */
    function getStatusText(status) {
        var statusTexts = {
            'pending': 'Pendiente',
            'running': 'Consultando...',
            'completed': 'Completado',
            'error': 'Error'
        };
        return statusTexts[status] || status;
    }
    
    /**
     * NUEVO: Obtener icono de estado
     */
    function getStatusIcon(status) {
        var statusIcons = {
            'pending': 'dashicons-database',
            'running': 'dashicons-update-alt',
            'completed': 'dashicons-yes-alt',
            'error': 'dashicons-warning'
        };
        return statusIcons[status] || 'dashicons-database';
    }
    
    /**
     * NUEVO: Completar búsqueda
     */
    function completeSearch(progressData) {
        log('Búsqueda completada con ' + (progressData.total_records || 0) + ' registros');
        
        // Detener polling
        stopProgressPolling();
        
        // Mostrar resultados después de una pequeña pausa para mostrar el 100%
        setTimeout(function() {
            hideProgressIndicator();
            
            if (progressData.results && progressData.total_records > 0) {
                displayResults({
                    has_results: true,
                    results: progressData.results,
                    total_records: progressData.total_records
                });
                
                // NUEVO: Almacenar resultados para exportación
                storeResultsForExport(progressData.results, progressData.search_params || {
                    profile_type: selectedProfile,
                    fecha_inicio: $('#cp-fecha-inicio').val(),
                    fecha_fin: $('#cp-fecha-fin').val(),
                    numero_documento: $('#cp-numero-documento').val()
                });
                
                // NUEVO: Agregar botón de exportación
                addExportButtonToResults();
                
            } else {
                displayResults({
                    has_results: false,
                    message: 'No se encontraron resultados para los criterios especificados'
                });
            }
            
            resetSearchState();
        }, 1500); // Pausa de 1.5 segundos para mostrar el progreso completo
    }
    
    /**
     * NUEVO: Manejar timeout con resultados parciales
     */
    function handleSearchTimeout(progressData) {
        log('Timeout de búsqueda con resultados parciales');
        
        // Detener polling
        stopProgressPolling();
        
        // Contar registros encontrados hasta el momento
        var totalRecords = 0;
        var completedSources = 0;
        var totalSources = 0;
        var partialResults = {};
        
        if (progressData.sources_progress) {
            totalSources = Object.keys(progressData.sources_progress).length;
            
            Object.keys(progressData.sources_progress).forEach(function(source) {
                var sourceData = progressData.sources_progress[source];
                if (sourceData.status === 'completed') {
                    completedSources++;
                    totalRecords += sourceData.records_found || 0;
                }
            });
        }
        
        // Si hay resultados en los datos de progreso, usarlos
        if (progressData.results) {
            partialResults = progressData.results;
            // Recalcular total de registros de los resultados reales
            totalRecords = 0;
            Object.keys(partialResults).forEach(function(source) {
                if (partialResults[source] && Array.isArray(partialResults[source])) {
                    totalRecords += partialResults[source].length;
                }
            });
        }
        
        hideProgressIndicator();
        
        if (totalRecords > 0) {
            // Mostrar resultados parciales con mensaje de timeout
            var timeoutMessage = `Búsqueda interrumpida por timeout (${completedSources}/${totalSources} fuentes completadas). Se muestran los resultados encontrados hasta el momento.`;
            
            showError(timeoutMessage);
            
            // Mostrar resultados parciales
            setTimeout(function() {
                displayResults({
                    has_results: true,
                    results: partialResults,
                    total_records: totalRecords,
                    is_partial: true,
                    timeout_message: timeoutMessage
                });
            }, 2000); // Esperar 2 segundos para que se vea el mensaje de error
            
        } else {
            // No hay resultados, solo mostrar error de timeout
            showError('Timeout: La búsqueda tomó demasiado tiempo y no se encontraron resultados');
        }
        
        resetSearchState();
    }
    
    /**
     * NUEVO: Manejar error de búsqueda
     */
    function handleSearchError(progressData) {
        log('Error en búsqueda: ' + (progressData.message || 'Error desconocido'));
        
        // Detener polling
        stopProgressPolling();
        
        // Mostrar error
        hideProgressIndicator();
        showError(progressData.message || 'Error en la búsqueda');
        resetSearchState();
    }
    
    /**
     * NUEVO: Ocultar indicador de progreso
     */
    function hideProgressIndicator() {
        $('.cp-search-progress-container').hide();
        $('.cp-form-step.cp-step-3').show();
    }
    
    /**
     * NUEVO: Cancelar búsqueda
     */
    function cancelSearch() {
        if (!searchInProgress) {
            return;
        }
        
        log('Cancelando búsqueda ID: ' + currentSearchId);
        
        // Detener polling
        stopProgressPolling();
        
        // Ocultar progreso y mostrar formulario
        hideProgressIndicator();
        resetSearchState();
        
        showSuccess('Búsqueda cancelada');
    }
    
    /**
     * NUEVO: Resetear estado de búsqueda
     */
    function resetSearchState() {
        log('Reseteando estado de búsqueda');
        
        // Detener cualquier polling
        stopProgressPolling();
        
        // Limpiar variables globales
        searchInProgress = false;
        currentSearchId = null;
        
        // Limpiar variables de control AJAX
        window.cpCheckInProgress = false;
        window.cpErrorCount = 0;
        
        // Restaurar botón
        var $button = $('#cp-search-submit');
        var $buttonText = $button.find('.cp-btn-text');
        var $buttonSpinner = $button.find('.cp-btn-spinner');
        
        $button.prop('disabled', false);
        $buttonText.show();
        $buttonSpinner.hide();
        
        // Limpiar contenedor de progreso
        $('.cp-search-sources-progress').empty();
        
        // Resetear barra de progreso
        $('.cp-progress-bar-fill').css('width', '0%');
        $('.cp-progress-percentage').text('0%');
        
        // NUEVO: Limpiar resultados almacenados para exportación
        clearStoredResults();
    }
    
    /**
     * Mostrar resultados de búsqueda
     */
    function displayResults(data) {
        var $resultsContainer = $('.cp-results-container');
        var $resultsContent = $('.cp-results-content');
        
        if (!data.has_results) {
            $resultsContent.html(createNoResultsHTML(data.message));
        } else {
            $resultsContent.html(createResultsHTML(data.results, data.total_records, data.is_partial, data.timeout_message));
        }
        
        // Mostrar contenedor de resultados
        $resultsContainer.show();
        
        // Scroll a los resultados
        $('html, body').animate({
            scrollTop: $resultsContainer.offset().top - 50
        }, 500);
        
        // NUEVO: Si hay resultados, agregar funcionalidad de exportación
        if (data.has_results && data.results) {
            storeResultsForExport(data.results, window.currentSearchParams || {
                profile_type: selectedProfile,
                fecha_inicio: $('#cp-fecha-inicio').val(),
                fecha_fin: $('#cp-fecha-fin').val(),
                numero_documento: $('#cp-numero-documento').val()
            });
            addExportButtonToResults();
        }
        
        // Si es resultado parcial, mostrar mensaje adicional
        if (data.is_partial && data.timeout_message) {
            setTimeout(function() {
                showTimeoutInfo(data.timeout_message);
            }, 500);
        }
    }
    
    /**
     * NUEVO: Mostrar información de timeout
     */
    function showTimeoutInfo(message) {
        var timeoutHtml = `
            <div class="cp-timeout-info">
                <span class="dashicons dashicons-clock"></span>
                <span>${escapeHtml(message)}</span>
            </div>
        `;
        
        $('.cp-results-summary').after(timeoutHtml);
        
        // Auto-remover después de 10 segundos
        setTimeout(function() {
            $('.cp-timeout-info').fadeOut(300, function() {
                $(this).remove();
            });
        }, 10000);
    }
    
    /**
     * Crear HTML para cuando no hay resultados
     */
    function createNoResultsHTML(message) {
        return `
            <div class="cp-no-results">
                <span class="dashicons dashicons-search"></span>
                <h4>No se encontraron resultados</h4>
                <p>${message || cp_frontend_ajax.messages.no_results}</p>
            </div>
        `;
    }
    
    /**
     * Crear HTML para mostrar resultados
     */
    function createResultsHTML(results, totalRecords, isPartial, timeoutMessage) {
        var statusText = isPartial ? 'Resultados parciales encontrados' : 'Total de registros encontrados';
        var statusClass = isPartial ? 'cp-partial-results' : '';
        
        var html = `<div class="cp-results-summary ${statusClass}">
            <p><strong>${statusText}: ${totalRecords}</strong></p>
        </div>`;
        
        // Iterar por cada fuente de datos
        $.each(results, function(source, records) {
            if (records && records.length > 0) {
                html += createSourceResultsHTML(source, records);
            }
        });
        
        // Si no hay resultados pero es búsqueda parcial
        if (totalRecords === 0 && isPartial) {
            html += `
                <div class="cp-partial-no-results">
                    <span class="dashicons dashicons-info"></span>
                    <p>Las fuentes completadas antes del timeout no encontraron resultados.</p>
                </div>
            `;
        }
        
        return html;
    }
    
    /**
     * Crear HTML para resultados de una fuente específica
     */
    function createSourceResultsHTML(source, records) {
        var sourceTitle = getSourceTitle(source);
        var html = `
            <div class="cp-result-section">
                <h4>
                    <span class="dashicons dashicons-database"></span>
                    ${sourceTitle} (${records.length} registros)
                </h4>
                <div class="table-responsive">
                    <table class="cp-result-table">
        `;
        
        if (records.length > 0) {
            // Headers
            html += '<thead><tr>';
            var firstRecord = records[0];
            $.each(firstRecord, function(key, value) {
                html += `<th>${escapeHtml(key.replace(/_/g, ' ').toUpperCase())}</th>`;
            });
            html += '</tr></thead>';
            
            // Datos (limitar a 50 registros para performance)
            html += '<tbody>';
            var displayRecords = records.slice(0, 50);
            $.each(displayRecords, function(index, record) {
                html += '<tr>';
                $.each(record, function(key, value) {
                    var displayValue = value !== null && value !== undefined ? escapeHtml(String(value)) : '-';
                    html += `<td>${displayValue}</td>`;
                });
                html += '</tr>';
            });
            html += '</tbody>';
            
            // Mensaje si hay más registros
            if (records.length > 50) {
                html += `<tfoot><tr><td colspan="100%" class="cp-more-records">
                    Mostrando 50 de ${records.length} registros encontrados
                </td></tr></tfoot>`;
            }
        }
        
        html += '</table></div></div>';
        
        return html;
    }
    
    /**
     * Obtener título de fuente de datos
     */
    function getSourceTitle(source) {
        var titles = {
            'tvec': 'TVEC - Catálogo de Proveedores',
            'secopi': 'SECOPI - Sistema de Información',
            'secopii': 'SECOPII - Sistema Extendido'
        };
        
        return titles[source] || source.toUpperCase();
    }
    
    /**
     * Mostrar mensaje de error
     */
    function showError(message) {
        // Remover errores anteriores
        $('.cp-error-message').remove();
        
        var errorHtml = `
            <div class="cp-error-message">
                <span class="dashicons dashicons-warning"></span>
                <span>${escapeHtml(message)}</span>
            </div>
        `;
        
        // Agregar error después del elemento activo
        if ($('.cp-search-progress-container').is(':visible')) {
            $('.cp-search-progress-container').prepend(errorHtml);
        } else {
            $('.cp-form-step.active').prepend(errorHtml);
        }
        
        // Auto-remover después de 5 segundos
        setTimeout(function() {
            $('.cp-error-message').fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    /**
     * Mostrar mensaje de éxito
     */
    function showSuccess(message) {
        // Remover mensajes anteriores
        $('.cp-success-message, .cp-error-message').remove();
        
        var successHtml = `
            <div class="cp-success-message">
                <span class="dashicons dashicons-yes-alt"></span>
                <span>${escapeHtml(message)}</span>
            </div>
        `;
        
        if ($('.cp-search-progress-container').is(':visible')) {
            $('.cp-search-progress-container').prepend(successHtml);
        } else {
            $('.cp-form-step.active').prepend(successHtml);
        }
        
        // Auto-remover después de 3 segundos
        setTimeout(function() {
            $('.cp-success-message').fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    /**
     * Escapar HTML
     */
    function escapeHtml(text) {
        if (typeof text !== 'string') {
            return text;
        }
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Utilidad para logging
     */
    function log(message, data) {
        if (console && console.log) {
            console.log('[CP Frontend] ' + message, data || '');
        }
    }
    
    // Eventos adicionales para mejorar UX
    
    /**
     * Prevenir múltiples envíos del formulario
     */
    $('#cp-search-form').on('submit', function(e) {
        e.preventDefault();
        
        // Verificar si ya hay una búsqueda en progreso
        if (searchInProgress) {
            log('Formulario enviado pero búsqueda ya en progreso, ignorando');
            return false;
        }
        
        handleSearchSubmit(e);
    });
    
    /**
     * Prevenir doble clic en botón de búsqueda
     */
    $('#cp-search-submit').on('click', function(e) {
        if (searchInProgress) {
            e.preventDefault();
            e.stopImmediatePropagation();
            return false;
        }
    });
    
    /**
     * Manejar Enter en campos de fecha
     */
    $('#cp-fecha-inicio, #cp-fecha-fin').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#cp-numero-documento').focus();
        }
    });
    
    /**
     * Manejar Enter en número de documento
     */
    $('#cp-numero-documento').on('keypress', function(e) {
        if (e.which === 13 && !searchInProgress) {
            e.preventDefault();
            $('#cp-search-form').submit();
        }
    });
    
    /**
     * Validación visual en tiempo real
     */
    $('.cp-form-group input').on('blur', function() {
        $(this).removeClass('invalid');
        
        if ($(this).attr('required') && !$(this).val()) {
            $(this).addClass('invalid');
        }
    });
    
    // Limpiar cuando se sale de la página
    $(window).on('beforeunload', function() {
        if (searchInProgress) {
            // Limpiar búsqueda al salir
            stopProgressPolling();
            return '¿Está seguro de que desea salir? La búsqueda se cancelará.';
        }
    });
    
    // Limpiar al cambiar de página
    $(window).on('pagehide', function() {
        if (searchInProgress) {
            stopProgressPolling();
            resetSearchState();
        }
    });

    /**
     * NUEVO: Exportar resultados a Excel
     */
    function exportResultsToExcel() {
        // Verificar que hay resultados para exportar
        if (!window.currentSearchResults || Object.keys(window.currentSearchResults).length === 0) {
            showError('No hay resultados para exportar');
            return;
        }
        
        // Verificar que hay datos en los resultados
        var totalRecords = 0;
        Object.keys(window.currentSearchResults).forEach(function(source) {
            if (window.currentSearchResults[source] && Array.isArray(window.currentSearchResults[source])) {
                totalRecords += window.currentSearchResults[source].length;
            }
        });
        
        if (totalRecords === 0) {
            showError('No hay registros para exportar');
            return;
        }
        
        // Mostrar indicador de carga
        var $exportButton = $('.cp-export-excel');
        var originalText = $exportButton.html();
        
        $exportButton.prop('disabled', true).html(
            '<span class="dashicons dashicons-update-alt cp-spin"></span> Generando Excel...'
        );
        
        log('Iniciando exportación a Excel de ' + totalRecords + ' registros');
        
        // Preparar datos para envío
        var exportData = {
            action: 'cp_export_frontend_results',
            nonce: cp_frontend_ajax.nonce,
            export_data: JSON.stringify(window.currentSearchResults),
            format: 'excel',
            profile_type: window.currentSearchProfile || selectedProfile,
            search_params: window.currentSearchParams || {
                profile_type: selectedProfile,
                fecha_inicio: $('#cp-fecha-inicio').val(),
                fecha_fin: $('#cp-fecha-fin').val(),
                numero_documento: $('#cp-numero-documento').val()
            }
        };
        
        // Realizar solicitud AJAX
        $.ajax({
            url: cp_frontend_ajax.url,
            type: 'POST',
            data: exportData,
            dataType: 'json',
            timeout: 60000, // 60 segundos de timeout
            success: function(response) {
                if (response.success) {
                    log('Exportación exitosa: ' + response.data.filename);
                    
                    // Mostrar mensaje de éxito
                    showExportSuccess(response.data);
                    
                    // Iniciar descarga automática
                    setTimeout(function() {
                        window.location.href = response.data.download_url;
                    }, 1000);
                    
                } else {
                    log('Error en exportación: ' + response.data.message);
                    showError('Error al generar Excel: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                log('Error AJAX en exportación: ' + error);
                
                if (status === 'timeout') {
                    showError('La exportación está tomando demasiado tiempo. Por favor, intente con menos registros.');
                } else {
                    showError('Error de conexión al generar el archivo Excel');
                }
            },
            complete: function() {
                // Restaurar botón
                $exportButton.prop('disabled', false).html(originalText);
            }
        });
    }
    
    /**
     * NUEVO: Mostrar mensaje de éxito de exportación
     */
    function showExportSuccess(exportData) {
        var message = `
            Excel generado exitosamente:<br>
            <strong>${escapeHtml(exportData.filename)}</strong><br>
            ${exportData.records_count} registros • ${exportData.file_size}<br>
            <small>La descarga comenzará automáticamente...</small>
        `;
        
        // Crear mensaje personalizado para exportación
        $('.cp-export-success').remove();
        
        var successHtml = `
            <div class="cp-export-success">
                <span class="dashicons dashicons-yes-alt"></span>
                <div class="cp-export-message">${message}</div>
            </div>
        `;
        
        $('.cp-results-summary').after(successHtml);
        
        // Auto-remover después de 8 segundos
        setTimeout(function() {
            $('.cp-export-success').fadeOut(300, function() {
                $(this).remove();
            });
        }, 8000);
    }
    
    /**
     * NUEVO: Agregar botón de exportación a los resultados
     */
    function addExportButtonToResults() {
        // Verificar si ya existe el botón
        if ($('.cp-export-actions').length > 0) {
            return;
        }
        
        var exportButtonsHtml = `
            <div class="cp-export-actions">
                <button type="button" class="cp-btn cp-btn-secondary cp-export-excel">
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    Exportar a Excel
                </button>
                <small class="cp-export-help">
                    Se exportarán todos los resultados encontrados en las diferentes fuentes
                </small>
            </div>
        `;
        
        // Agregar después del resumen de resultados
        $('.cp-results-summary').after(exportButtonsHtml);
        
        // Vincular evento click
        $('.cp-export-excel').on('click', function(e) {
            e.preventDefault();
            exportResultsToExcel();
        });
        
        log('Botón de exportación agregado a los resultados');
    }
    
    /**
     * NUEVO: Almacenar resultados globalmente para exportación
     */
    function storeResultsForExport(results, searchParams) {
        window.currentSearchResults = results;
        window.currentSearchParams = searchParams;
        window.currentSearchProfile = selectedProfile;
        
        log('Resultados almacenados para exportación: ' + Object.keys(results).length + ' fuentes');
    }
    
    /**
     * NUEVO: Limpiar resultados almacenados
     */
    function clearStoredResults() {
        window.currentSearchResults = null;
        window.currentSearchParams = null;
        window.currentSearchProfile = null;
        
        // Remover botones de exportación
        $('.cp-export-actions').remove();
        $('.cp-export-success').remove();
    }
    
    // Log de inicialización
    log('Frontend con indicador de progreso inicializado correctamente');
    
    // Exponer funciones públicas para debugging
    window.cpFrontend = {
        goToStep: goToStep,
        validateForm: validateSearchForm,
        currentStep: function() { return currentStep; },
        selectedProfile: function() { return selectedProfile; },
        searchInProgress: function() { return searchInProgress; },
        currentSearchId: function() { return currentSearchId; },
        cancelSearch: cancelSearch
    };
});