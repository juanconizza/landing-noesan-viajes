/**
 * Meta Conversions Tracker
 * 
 * Maneja el tracking de conversiones con Meta Pixel + CAPI
 * siguiendo las mejores prácticas de atribución.
 */

const MetaConversions = {
    // Configuración
    config: {
        pixelId: '2283474082174153',
        endpointUrl: '/miami-women-trip/assets/conversions/meta-google-conversion.php',  // Ruta completa considerando subdirectorio
        whatsappUrl: 'https://wa.me/5493516217424?text=' + encodeURIComponent('Hola! Quiero información del Miami Women Trip...'),
        debug: false
    },

    /**
     * Lee las cookies de Facebook (_fbp y _fbc)
     */
    getCookie: function(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) {
            return parts.pop().split(';').shift();
        }
        return null;
    },

    /**
     * Obtiene los parámetros de tracking de Facebook
     */
    getTrackingParams: function() {
        const fbp = this.getCookie('_fbp');
        const fbc = this.getCookie('_fbc');
        
        if (this.config.debug) {
            console.log('Meta Tracking Params:', { fbp, fbc });
        }
        
        return { fbp, fbc };
    },

    /**
     * Genera un event_id único para deduplicación
     */
    generateEventId: function() {
        return 'event_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    },

    /**
     * Envía el evento a Meta CAPI vía backend
     */
    sendToCAPI: async function(eventId, eventSourceUrl) {
        const { fbp, fbc } = this.getTrackingParams();
        
        const payload = {
            event_id: eventId,
            event_source_url: eventSourceUrl,
            fbp: fbp,
            fbc: fbc
        };

        try {
            const response = await fetch(this.config.endpointUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();
            
            if (this.config.debug) {
                console.log('CAPI Response:', result);
            }

            return result;
        } catch (error) {
            console.error('Error sending to CAPI:', error);
            return { status: 'error', message: error.message };
        }
    },

    /**
     * Trackea el evento Contact con Pixel + CAPI
     */
    trackContact: async function(buttonId = null) {
        const eventId = this.generateEventId();
        const eventSourceUrl = window.location.href;

        if (this.config.debug) {
            console.log('Tracking Contact Event:', { eventId, buttonId, eventSourceUrl });
        }

        // 1. Enviar a CAPI (backend)
        await this.sendToCAPI(eventId, eventSourceUrl);

        // 2. Trackear con Pixel (deduplicado con mismo event_id)
        if (typeof fbq !== 'undefined') {
            fbq('track', 'Contact', {}, {
                eventID: eventId
            });
            
            if (this.config.debug) {
                console.log('Pixel Contact tracked with eventID:', eventId);
            }
        } else {
            console.warn('Meta Pixel (fbq) not loaded');
        }

        return eventId;
    },

    /**
     * Maneja el click en botones de contacto
     */
    handleContactClick: async function(e, buttonId) {
        e.preventDefault();

        if (this.config.debug) {
            console.log('Contact button clicked:', buttonId);
        }

        // Trackear la conversión
        await this.trackContact(buttonId);

        // Esperar un momento para asegurar que el tracking se complete
        setTimeout(() => {
            window.location.href = this.config.whatsappUrl;
        }, 30000000);
    },

    /**
     * Inicializa los listeners en todos los botones de contacto
     */
    init: function() {
        if (this.config.debug) {
            console.log('Meta Conversions Tracker initialized');
            console.log('Tracking params:', this.getTrackingParams());
        }

        // Buscar todos los botones por ID específico (no por href)
        const buttonIds = [
            'cupos-limitados-button',
            'deseo-info-completa',
            'chatear-asesora',
            'ver-itinerario-completo',
            'reservar-mi-lugar',
            'lo-quiero-deseo-mas-info',
            'quiero-reservar-mi-lugar',
            'whatsapp-button'
        ];

        buttonIds.forEach(buttonId => {
            const element = document.getElementById(buttonId);
            if (element) {
                // Prevenir el comportamiento por defecto
                element.addEventListener('click', (e) => {
                    this.handleContactClick(e, buttonId);
                });

                if (this.config.debug) {
                    console.log('Listener attached to button:', buttonId);
                }
            } else if (this.config.debug) {
                console.warn('Button not found:', buttonId);
            }
        });
    }
};

// Auto-inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        MetaConversions.init();
    });
} else {
    MetaConversions.init();
}

// Exportar para uso global
window.MetaConversions = MetaConversions;
