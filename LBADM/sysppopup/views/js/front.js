/**
 * SYSPROVIDER Popup - Frontend JavaScript
 *
 * @author    SYSPROVIDER S.L.
 * @copyright 2024 SYSPROVIDER S.L.
 */

document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('sysp-popup-overlay');
    const container = document.getElementById('sysp-popup-container');
    const closeBtn = document.getElementById('sysp-popup-close');
    
    if (!overlay || !container) {
        return;
    }
    
    const frequency = container.getAttribute('data-frequency');
    const frequencyValue = parseInt(container.getAttribute('data-frequency-value')) || 24;
    const delay = parseInt(container.getAttribute('data-delay')) || 2;
    
    /**
     * Gestión de cookies
     */
    function setCookie(name, value, hours) {
        const d = new Date();
        d.setTime(d.getTime() + (hours * 60 * 60 * 1000));
        const expires = "expires=" + d.toUTCString();
        document.cookie = name + "=" + value + ";" + expires + ";path=/";
    }
    
    function getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) == ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }
    
    /**
     * Verificar si el popup debe mostrarse
     */
    function shouldShowPopup() {
        const cookieName = 'sysp_popup_shown';
        const cookieValue = getCookie(cookieName);
        
        switch (frequency) {
            case 'once':
                // Mostrar solo una vez, cookie permanente
                if (cookieValue) {
                    return false;
                }
                break;
                
            case 'hours':
                // Mostrar cada X horas
                if (cookieValue) {
                    const lastShown = parseInt(cookieValue);
                    const now = Date.now();
                    const hoursSinceLastShown = (now - lastShown) / (1000 * 60 * 60);
                    
                    if (hoursSinceLastShown < frequencyValue) {
                        return false;
                    }
                }
                break;
                
            case 'days':
                // Mostrar cada X días
                if (cookieValue) {
                    const lastShown = parseInt(cookieValue);
                    const now = Date.now();
                    const daysSinceLastShown = (now - lastShown) / (1000 * 60 * 60 * 24);
                    
                    if (daysSinceLastShown < frequencyValue) {
                        return false;
                    }
                }
                break;
                
            case 'always':
            default:
                // Siempre mostrar
                return true;
        }
        
        return true;
    }
    
    /**
     * Mostrar el popup
     */
    function showPopup() {
        if (!shouldShowPopup()) {
            return;
        }
        
        // Aplicar delay
        setTimeout(function() {
            // Aplicar clase de animación justo antes de mostrar
            const animation = container.getAttribute('data-animation');
            if (animation) {
                container.classList.add('sysp-animation-' + animation);
            }

            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Guardar cookie según frecuencia
            const cookieName = 'sysp_popup_shown';
            
            switch (frequency) {
                case 'once':
                    // Cookie permanente (10 años)
                    setCookie(cookieName, '1', 87600);
                    break;
                    
                case 'hours':
                    setCookie(cookieName, Date.now().toString(), frequencyValue);
                    break;
                    
                case 'days':
                    setCookie(cookieName, Date.now().toString(), frequencyValue * 24);
                    break;
            }
        }, delay * 1000);
    }
    
    /**
     * Cerrar el popup
     */
    function closePopup() {
        overlay.classList.add('closing');
        
        setTimeout(function() {
            overlay.classList.remove('active', 'closing');
            document.body.style.overflow = '';
        }, 300);
    }
    
    /**
     * Event Listeners
     */
    
    // Botón de cerrar
    closeBtn.addEventListener('click', function(e) {
        e.preventDefault();
        closePopup();
    });
    
    // Cerrar al hacer clic fuera del popup
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            closePopup();
        }
    });
    
    // Cerrar con tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && overlay.classList.contains('active')) {
            closePopup();
        }
    });
    
    // Prevenir scroll del body cuando el popup está abierto
    overlay.addEventListener('touchmove', function(e) {
        if (e.target === overlay) {
            e.preventDefault();
        }
    }, { passive: false });
    
    // Inicializar popup
    showPopup();
});
