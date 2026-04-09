/**
 * SYSPROVIDER Popup - Backoffice JavaScript
 *
 * @author    SYSPROVIDER S.L.
 * @copyright 2024 SYSPROVIDER S.L.
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Preview de imagen seleccionada
    const imageSelect = document.querySelector('select[name="SYSPPOPUP_IMAGE"]');
    const imageUpload = document.querySelector('input[name="SYSPPOPUP_NEW_IMAGE"]');
    
    if (imageSelect) {
        // Crear contenedor para preview si no existe
        let previewContainer = document.getElementById('image-preview-container');
        if (!previewContainer) {
            previewContainer = document.createElement('div');
            previewContainer.id = 'image-preview-container';
            previewContainer.style.display = 'none';
            
            const previewLabel = document.createElement('div');
            previewLabel.id = 'image-preview-label';
            previewLabel.textContent = 'Vista previa:';
            previewContainer.appendChild(previewLabel);
            
            const previewImg = document.createElement('img');
            previewImg.id = 'image-preview';
            previewImg.alt = 'Preview';
            previewContainer.appendChild(previewImg);
            
            imageSelect.parentElement.appendChild(previewContainer);
        }
        
        const previewImg = document.getElementById('image-preview');
        
        // Mostrar preview al seleccionar imagen
        imageSelect.addEventListener('change', function() {
            if (this.value) {
                const base = (typeof baseUri !== 'undefined') ? baseUri : '/';
                const imgUrl = base + 'img/popups/' + this.value;
                previewImg.src = imgUrl;
                previewContainer.style.display = 'block';
            } else {
                previewContainer.style.display = 'none';
            }
        });
        
        // Mostrar preview inicial si hay imagen seleccionada
        if (imageSelect.value) {
            const base = (typeof baseUri !== 'undefined') ? baseUri : '/';
            const imgUrl = base + 'img/popups/' + imageSelect.value;
            previewImg.src = imgUrl;
            previewContainer.style.display = 'block';
        }
    }
    
    // Preview de imagen nueva subida
    if (imageUpload) {
        imageUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validar tipo de archivo
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!validTypes.includes(file.type)) {
                    alert('Por favor, selecciona un archivo de imagen válido (JPG, PNG, GIF)');
                    this.value = '';
                    return;
                }
                
                // Validar tamaño (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('El archivo es demasiado grande. Máximo 2MB.');
                    this.value = '';
                    return;
                }
                
                // Mostrar nombre del archivo
                const fileName = file.name;
                let fileLabel = imageUpload.parentElement.querySelector('.file-label');
                if (!fileLabel) {
                    fileLabel = document.createElement('div');
                    fileLabel.className = 'file-label';
                    fileLabel.style.marginTop = '10px';
                    fileLabel.style.color = '#28a745';
                    fileLabel.style.fontWeight = 'bold';
                    imageUpload.parentElement.appendChild(fileLabel);
                }
                fileLabel.textContent = '✓ Archivo seleccionado: ' + fileName;
            }
        });
    }
    
    // Mostrar/ocultar campo de frecuencia según selección
    const frequencySelect = document.querySelector('select[name="SYSPPOPUP_FREQUENCY"]');
    const frequencyValueInput = document.querySelector('input[name="SYSPPOPUP_FREQUENCY_VALUE"]');
    
    if (frequencySelect && frequencyValueInput) {
        const frequencyValueGroup = frequencyValueInput.closest('.form-group');
        
        function toggleFrequencyValue() {
            const value = frequencySelect.value;
            if (value === 'hours' || value === 'days') {
                frequencyValueGroup.style.display = 'block';
                
                // Cambiar label según selección
                const label = frequencyValueGroup.querySelector('label');
                if (label) {
                    if (value === 'hours') {
                        label.textContent = 'Número de horas';
                    } else {
                        label.textContent = 'Número de días';
                    }
                }
            } else {
                frequencyValueGroup.style.display = 'none';
            }
        }
        
        frequencySelect.addEventListener('change', toggleFrequencyValue);
        toggleFrequencyValue(); // Ejecutar al cargar
    }
    
    // Validación del formulario antes de enviar
    const form = document.querySelector('form[name="configuration_form"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            const frequency = frequencySelect ? frequencySelect.value : null;
            const frequencyValue = frequencyValueInput ? frequencyValueInput.value : null;
            
            // Validar que si se selecciona "hours" o "days", se ingrese un valor
            if ((frequency === 'hours' || frequency === 'days') && (!frequencyValue || frequencyValue <= 0)) {
                e.preventDefault();
                alert('Por favor, ingresa un valor válido para la frecuencia.');
                frequencyValueInput.focus();
                return false;
            }
            
            const linkInput = document.querySelector('input[name="SYSPPOPUP_LINK"]');
            if (linkInput && linkInput.value) {
                try {
                    new URL(linkInput.value);
                } catch (_) {
                    e.preventDefault();
                    alert('Por favor, ingresa una URL válida (ej: https://ejemplo.com)');
                    linkInput.focus();
                    return false;
                }
            }
            
            return true;
        });
    }
    
    // Tooltip informativo
    const tooltips = document.querySelectorAll('.help-box');
    tooltips.forEach(function(tooltip) {
        tooltip.style.cursor = 'help';
        tooltip.title = tooltip.textContent;
    });
    
    // Añadir información de ayuda dinámica
    const moduleFooter = document.querySelector('.panel-footer');
    if (moduleFooter) {
        const helpDiv = document.createElement('div');
        helpDiv.className = 'sysp-help-section';
        helpDiv.innerHTML = `
            <h4>💡 Consejos de uso:</h4>
            <ul>
                <li><strong>Frecuencia "Siempre":</strong> El popup se mostrará en cada visita a la página</li>
                <li><strong>Frecuencia "Una vez":</strong> El popup se mostrará solo la primera vez que el usuario visite la tienda</li>
                <li><strong>Frecuencia "Cada X horas/días":</strong> El popup se mostrará de nuevo después del tiempo especificado</li>
                <li><strong>Delay:</strong> Espera en segundos antes de mostrar el popup (recomendado: 2-5 segundos)</li>
                <li><strong>Formatos de imagen recomendados:</strong> JPG para fotografías, PNG para imágenes con transparencia</li>
            </ul>
        `;
        form.parentElement.insertBefore(helpDiv, moduleFooter);
    }
    
    // Confirmación al guardar
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        const originalText = submitBtn.textContent;
        form.addEventListener('submit', function() {
            submitBtn.textContent = 'Guardando...';
            submitBtn.disabled = true;
        });
    }
});
