# Meta Conversions API - Nueva Implementación

## 📋 Descripción General

Sistema refactorizado para tracking de conversiones con Meta (Facebook) siguiendo las mejores prácticas actuales. Separa responsabilidades entre front-end y back-end, elimina generación manual de parámetros de tracking, y mejora la calidad de atribución.

---

## 🏗️ Arquitectura

### **Antes (❌ Vieja implementación)**
```
Usuario click → PHP (meta-conversion.php)
  ↓
  - Genera fbc manualmente
  - Lee cookies en backend
  - Renderiza HTML intermedio
  - Trackea con Pixel
  - Redirecciona a WhatsApp
```

### **Ahora (✅ Nueva implementación)**
```
Usuario click → JavaScript (meta-tracking.js)
  ↓
  - Lee cookies (_fbp, _fbc) en front-end
  - Trackea con Pixel (fbq)
  - Envía a CAPI vía fetch()
  ↓
Backend (meta-endpoint.php)
  - Recibe JSON con datos
  - Valida y reenvía a Meta API
  - No renderiza HTML
  - Solo responde JSON
```

---

## 📁 Estructura de Archivos

```
landing-noesan-viajes/
├── index.php                              # Landing page (incluye meta-tracking.js)
├── assets/
│   ├── js/
│   │   └── meta-tracking.js              # ✨ NUEVO: Maneja tracking desde front-end
│   └── conversions/
│       ├── meta-endpoint.php              # ✨ REFACTORIZADO: Endpoint headless CAPI
│       ├── meta-conversion.php            # ⚠️  DEPRECADO: Ya no se usa
│       └── logs/
│           └── debug.log                  # Logs de debugging
```

---

## 🔧 Componentes

### 1. **meta-tracking.js** (Front-end)

**Responsabilidades:**
- ✅ Leer cookies `_fbp` y `_fbc` del navegador
- ✅ Generar `event_id` único para deduplicación
- ✅ Trackear evento con Meta Pixel (fbq)
- ✅ Enviar datos a CAPI vía fetch()
- ✅ Manejar clicks en botones de contacto
- ✅ Redirigir a WhatsApp después del tracking

**Uso:**
```javascript
// Auto-inicializado al cargar la página
// Busca automáticamente todos los botones de contacto

// También se puede usar manualmente:
await MetaConversions.trackContact('custom-button-id');
```

**Botones detectados automáticamente:**
- `#cupos-limitados-button`
- `#deseo-info-completa`
- `#chatear-asesora`
- `#ver-itinerario-completo`
- `#reservar-mi-lugar`
- `#lo-quiero-deseo-mas-info`
- `#quiero-reservar-mi-lugar`
- `#whatsapp-button`
- Cualquier `a[href*="meta-conversion.php"]`
- Cualquier `a[href*="wa.me"]`

---

### 2. **meta-endpoint.php** (Back-end)

**Responsabilidades:**
- ✅ Recibir POST con JSON
- ✅ Validar payload
- ✅ Obtener IP real (considerando proxies/Cloudflare)
- ✅ Enviar evento a Meta Conversions API
- ✅ Logging estructurado
- ✅ Responder con JSON

**Acepta:**
```json
POST /assets/conversions/meta-endpoint.php
Content-Type: application/json

{
  "event_id": "event_1234567890_abc123",
  "event_source_url": "https://midominio.com/",
  "fbp": "fb.1.1234567890.1234567890",
  "fbc": "fb.1.1234567890.IwAR123..."
}
```

**Responde:**
```json
// Éxito
{
  "status": "ok",
  "event_id": "event_1234567890_abc123"
}

// Error
{
  "status": "error",
  "message": "Error description"
}
```

---

## 🚀 Flujo de Conversión

### **Paso a Paso:**

1. **Usuario carga la página**
   - Meta Pixel se carga e inicializa
   - Pixel setea cookies `_fbp` (Browser ID) automáticamente
   - Si viene de anuncio de FB, cookie `_fbc` (Click ID) se setea

2. **Usuario hace click en botón de contacto**
   - `meta-tracking.js` intercepta el click
   - Lee cookies `_fbp` y `_fbc`
   - Genera `event_id` único

3. **Tracking Dual (Pixel + CAPI)**
   - **Pixel**: `fbq('track', 'Contact', {}, { eventID: 'xxx' })`
   - **CAPI**: `fetch('/meta-endpoint.php', { ... })`
   - Ambos usan el mismo `event_id` para deduplicación

4. **Backend procesa CAPI**
   - Valida datos recibidos
   - Obtiene IP del servidor
   - Envía a `graph.facebook.com/v18.0/{PIXEL_ID}/events`
   - Log del resultado

5. **Redirección**
   - Después de 300ms, usuario es redirigido a WhatsApp
   - Meta tiene tiempo suficiente para registrar el evento

---

## ✅ Mejoras vs Implementación Anterior

| Aspecto | ❌ Antes | ✅ Ahora |
|---------|---------|---------|
| **Separación de responsabilidades** | Todo en un PHP | Front-end + Back-end separados |
| **Generación de fbc** | Manual en PHP | Solo cookies reales del navegador |
| **event_source_url** | HTTP_REFERER (poco confiable) | window.location.href (exacta) |
| **Navegación** | Usuario ve página intermedia | Invisible, solo tracking |
| **Seguridad** | Access token expuesto | Solo en backend |
| **Deduplicación** | Posible duplicación | event_id único compartido |
| **Debugging** | Logs mezclados | Logs estructurados en JSON |
| **Mantenibilidad** | Código acoplado | Modular y reutilizable |

---

## 🔍 Debugging

### **Ver logs del servidor:**
```bash
tail -f assets/conversions/logs/debug.log
```

### **Ver logs del navegador:**
Abre la consola del navegador (F12) y verás:
```
Meta Conversions Tracker initialized
Tracking params: {fbp: "fb.1...", fbc: "fb.1..."}
Listener attached to: #cupos-limitados-button
Contact button clicked: cupos-limitados-button
Tracking Contact Event: {...}
CAPI Response: {status: "ok", event_id: "..."}
Pixel Contact tracked with eventID: event_...
```

### **Verificar en Meta Events Manager:**
1. Ve a [Meta Events Manager](https://business.facebook.com/events_manager)
2. Selecciona tu Pixel (2283474082174153)
3. Pestaña "Test Events"
4. Deberías ver eventos duplicados (Pixel + CAPI) con mismo `event_id`

---

## ⚙️ Configuración

### **Cambiar URL de WhatsApp:**
Edita `meta-tracking.js`:
```javascript
config: {
    whatsappUrl: 'https://wa.me/TU_NUMERO?text=...'
}
```

### **Deshabilitar debug logs:**
Edita `meta-tracking.js`:
```javascript
config: {
    debug: false  // Cambiar a false
}
```

Y en `meta-endpoint.php`:
```php
define('ENABLE_LOGGING', false);  // Cambiar a false
```

### **Actualizar Access Token:**
Edita `meta-endpoint.php`:
```php
define('META_ACCESS_TOKEN', 'TU_NUEVO_TOKEN');
```

---

## 🧪 Testing

### **Test Local:**
1. Abre la landing en tu navegador
2. Abre la consola (F12)
3. Haz click en cualquier botón de contacto
4. Verifica logs en consola
5. Verifica que se abre WhatsApp

### **Test en Producción:**
1. Usa un anuncio de Facebook real con `fbclid`
2. Haz click en el anuncio
3. Verifica que `_fbc` cookie existe (DevTools → Application → Cookies)
4. Haz click en botón de contacto
5. Verifica en Meta Events Manager que el evento llegó con `fbc`

---

## 📊 Datos Enviados a Meta

### **user_data:**
- `client_ip_address`: IP del usuario (desde servidor)
- `client_user_agent`: User agent del navegador
- `fbp`: Facebook Browser ID (cookie `_fbp`)
- `fbc`: Facebook Click ID (cookie `_fbc`) - **Solo si existe**

### **event_data:**
- `event_name`: "Contact"
- `event_time`: Timestamp Unix
- `event_id`: ID único para deduplicación
- `event_source_url`: URL exacta de la página
- `action_source`: "website"

---

## 🔒 Seguridad

### **Implementado:**
- ✅ Access Token solo en backend (no expuesto al cliente)
- ✅ Validación de payload en PHP
- ✅ CORS configurado correctamente
- ✅ Solo método POST aceptado
- ✅ Detección de IP considerando proxies

### **Recomendaciones adicionales:**
- 🔐 Implementar rate limiting en el endpoint
- 🔐 Agregar token CSRF si es necesario
- 🔐 Filtrar IPs sospechosas
- 🔐 Rotar el Access Token regularmente

---

## 🐛 Troubleshooting

### **Problema: "fbq is not defined"**
**Causa:** Meta Pixel no se cargó correctamente
**Solución:** Verifica que el script del Pixel esté en el `<head>` del HTML

### **Problema: "_fbc cookie no existe"**
**Causa:** Usuario no viene desde un anuncio de Facebook
**Solución:** Normal. Solo usuarios que vienen de anuncios FB tendrán `_fbc`

### **Problema: "CORS error"**
**Causa:** Política de CORS bloqueando el fetch
**Solución:** Verifica headers CORS en `meta-endpoint.php`

### **Problema: "Event no aparece en Meta"**
**Causa:** Puede tardar unos minutos, o hay error en CAPI
**Solución:** Revisa `debug.log` para ver respuesta de Meta API

---

## 📝 Migración desde Sistema Anterior

### **Para migrar:**

1. ✅ Ya está hecho - El nuevo sistema está implementado
2. ⚠️ Los enlaces antiguos a `meta-conversion.php` siguen funcionando gracias a que `meta-tracking.js` intercepta todos los links
3. 🔄 Opcional: Puedes actualizar los hrefs en `index.php` para que apunten directamente a WhatsApp (pero no es necesario)

### **Para remover el sistema antiguo completamente:**
```bash
# Puedes eliminar estos archivos después de verificar que todo funciona:
rm assets/conversions/meta-conversion.php
```

---

## 📈 Monitoreo

### **Métricas a revisar:**

1. **Tasa de match en Meta Events Manager**
   - Objetivo: >70% de eventos con `fbp` y `fbc`

2. **Eventos deduplicados correctamente**
   - Deberías ver 1 evento por conversión (no 2)

3. **Tiempo de respuesta del endpoint**
   - Objetivo: <500ms

4. **Errores en logs**
   - Monitorear `debug.log` regularmente

---

## 🆘 Soporte

Si encuentras problemas:

1. Revisa los logs en `assets/conversions/logs/debug.log`
2. Abre la consola del navegador para ver errores JS
3. Verifica en Meta Events Manager
4. Revisa esta documentación

---

**Última actualización:** 29 de diciembre de 2025
**Versión:** 2.0.0
