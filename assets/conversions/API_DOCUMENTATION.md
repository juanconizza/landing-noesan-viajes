# Meta Conversions API Endpoint

## Descripción
Este endpoint permite recibir datos JSON vía POST y los formatea para enviarlos como conversiones a la Meta Conversions API.

## URL del Endpoint
```
POST /assets/conversions/meta-endpoint.php
```

## Estructura del JSON de Entrada

### Campos Requeridos
- `event_name`: Nombre del evento (ej: "Contact", "Purchase", "Lead", etc.)

### Campos Opcionales
- `event_time`: Timestamp del evento (si no se proporciona, usa el tiempo actual)
- `event_id`: ID único del evento (si no se proporciona, se genera automáticamente)
- `client_ip`: IP del cliente (si no se proporciona, usa la IP del servidor)
- `user_agent`: User Agent del navegador (si no se proporciona, usa el del servidor)
- `email`: Email del usuario (se hashea automáticamente)
- `phone`: Teléfono del usuario (se hashea automáticamente)
- `first_name`: Nombre del usuario (se hashea automáticamente)
- `last_name`: Apellido del usuario (se hashea automáticamente)
- `fbp`: Facebook Browser ID
- `fbc`: Facebook Click ID
- `source_url`: URL de origen del evento
- `value`: Valor monetario de la conversión
- `currency`: Moneda de la conversión (ej: "USD", "EUR")
- `custom_data`: Objeto con datos personalizados adicionales

## Ejemplos de Uso

### 1. Evento de Contacto Básico
```json
{
    "event_name": "Contact",
    "email": "usuario@ejemplo.com",
    "phone": "+1234567890",
    "source_url": "https://miwebsite.com/contacto"
}
```

### 2. Evento de Compra con Valor
```json
{
    "event_name": "Purchase",
    "email": "cliente@ejemplo.com",
    "phone": "+1234567890",
    "first_name": "Juan",
    "last_name": "Pérez",
    "value": 299.99,
    "currency": "USD",
    "source_url": "https://miwebsite.com/checkout",
    "custom_data": {
        "product_id": "TRIP_MIAMI_2024",
        "product_name": "Miami Women Trip",
        "category": "Travel"
    }
}
```

### 3. Evento de Lead con IDs de Facebook
```json
{
    "event_name": "Lead",
    "email": "lead@ejemplo.com",
    "fbp": "fb.1.1234567890.1234567890",
    "fbc": "fb.1.1234567890.AbCdEfGhIjKlMnOpQrStUvWxYz",
    "source_url": "https://miwebsite.com/landing",
    "custom_data": {
        "lead_source": "organic",
        "form_name": "newsletter_signup"
    }
}
```

### 4. Evento Personalizado Completo
```json
{
    "event_name": "ViewContent",
    "event_time": 1703680800,
    "event_id": "custom_event_123",
    "client_ip": "192.168.1.1",
    "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
    "email": "usuario@ejemplo.com",
    "phone": "+5493516217424",
    "first_name": "María",
    "last_name": "González",
    "fbp": "fb.1.1703680800.1234567890",
    "source_url": "https://miwebsite.com/viajes/miami",
    "custom_data": {
        "content_ids": ["TRIP_001", "TRIP_002"],
        "content_type": "product",
        "content_category": "Travel"
    }
}
```

## Respuesta del Endpoint

### Respuesta Exitosa (200)
```json
{
    "success": true,
    "message": "Conversión enviada exitosamente",
    "event_id": "event_677123456789",
    "event_name": "Contact",
    "meta_response": {
        "events_received": 1,
        "messages": [],
        "fbtrace_id": "ABC123XYZ"
    }
}
```

### Respuesta de Error (400)
```json
{
    "success": false,
    "error": "El campo event_name es requerido"
}
```

## Eventos Meta Comunes
- `Contact`: Cuando un usuario llena un formulario de contacto
- `Lead`: Cuando se genera un lead cualificado
- `Purchase`: Cuando se completa una compra
- `ViewContent`: Cuando se visualiza contenido importante
- `AddToCart`: Cuando se agrega algo al carrito
- `InitiateCheckout`: Cuando se inicia el proceso de compra
- `Subscribe`: Cuando se suscribe a un newsletter o servicio

## Seguridad y Consideraciones
- Los emails, teléfonos y nombres se hashean automáticamente usando SHA256
- Se valida el JSON de entrada antes del procesamiento
- Se sanitizan todos los strings para prevenir XSS
- Se incluye manejo de errores y logging
- Compatible con CORS para peticiones desde otros dominios

## Integración con JavaScript

```javascript
// Ejemplo de envío desde JavaScript
async function sendMetaConversion(eventData) {
    try {
        const response = await fetch('/assets/conversions/meta-endpoint.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(eventData)
        });
        
        const result = await response.json();
        console.log('Conversión enviada:', result);
        return result;
    } catch (error) {
        console.error('Error al enviar conversión:', error);
        throw error;
    }
}

// Uso
sendMetaConversion({
    event_name: 'Contact',
    email: 'usuario@ejemplo.com',
    phone: '+1234567890'
});
```

## Integración con PHP

```php
// Ejemplo de envío desde PHP
function sendConversion($eventData) {
    $jsonData = json_encode($eventData);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://tudominio.com/assets/conversions/meta-endpoint.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Uso
$result = sendConversion([
    'event_name' => 'Contact',
    'email' => 'usuario@ejemplo.com',
    'phone' => '+1234567890'
]);
```