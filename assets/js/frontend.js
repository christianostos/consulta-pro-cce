/**
 * JavaScript del frontend para el plugin Consulta Procesos
 * Archivo: assets/js/frontend.js
 */

jQuery(document).ready(function($) {
    
    // Variables globales
    var currentStep = 1;
    var maxSteps = 3;
    var selectedProfile = '';
    var formData = {};
    
    // Inicialización
    init();
    
    function init() {
        bindEvents();
        updateProgressBar();
        initializeDateValidation();
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
            
            // Verificar que no sea un rango muy amplio (máximo 1 año)
            var diffTime = Math.abs(fin - inicio);
            var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            if (diffDays > 365) {
                showError('El rango de fechas no puede ser mayor a 1 año');
                return false;
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
     * Realizar búsqueda
     */
    function performSearch(data) {
        var $button = $('#cp-search-submit');
        var $buttonText = $button.find('.cp-btn-text');
        var $buttonSpinner = $button.find('.cp-btn-spinner');
        var $resultsContainer = $('.cp-results-container');
        
        // Estado de carga
        $button.prop('disabled', true);
        $buttonText.hide();
        $buttonSpinner.show();
        
        // Ocultar resultados anteriores
        $resultsContainer.hide();
        
        // Realizar petición AJAX
        $.ajax({
            url: cp_frontend_ajax.url,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    displayResults(response.data);
                } else {
                    showError(response.data.message || cp_frontend_ajax.messages.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error en búsqueda:', error);
                showError(cp_frontend_ajax.messages.error + ': ' + error);
            },
            complete: function() {
                // Restaurar botón
                $button.prop('disabled', false);
                $buttonText.show();
                $buttonSpinner.hide();
            }
        });
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
            $resultsContent.html(createResultsHTML(data.results, data.total_records));
        }
        
        // Mostrar contenedor de resultados
        $resultsContainer.show();
        
        // Scroll a los resultados
        $('html, body').animate({
            scrollTop: $resultsContainer.offset().top - 50
        }, 500);
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
    function createResultsHTML(results, totalRecords) {
        var html = `<div class="cp-results-summary">
            <p><strong>Total de registros encontrados: ${totalRecords}</strong></p>
        </div>`;
        
        // Iterar por cada fuente de datos
        $.each(results, function(source, records) {
            if (records && records.length > 0) {
                html += createSourceResultsHTML(source, records);
            }
        });
        
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
            
            // Datos
            html += '<tbody>';
            $.each(records, function(index, record) {
                html += '<tr>';
                $.each(record, function(key, value) {
                    var displayValue = value !== null && value !== undefined ? escapeHtml(String(value)) : '-';
                    html += `<td>${displayValue}</td>`;
                });
                html += '</tr>';
            });
            html += '</tbody>';
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
        
        // Agregar error después del paso actual
        $('.cp-form-step.active').prepend(errorHtml);
        
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
        
        $('.cp-form-step.active').prepend(successHtml);
        
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
        if (e.which === 13) {
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
    
    // Log de inicialización
    log('Frontend inicializado correctamente');
    
    // Exponer funciones públicas para debugging
    window.cpFrontend = {
        goToStep: goToStep,
        validateForm: validateSearchForm,
        currentStep: function() { return currentStep; },
        selectedProfile: function() { return selectedProfile; }
    };
});