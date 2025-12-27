<?php
/**
 * Endpoint POST para recibir datos JSON y enviar conversiones a Meta
 * 
 * Acepta peticiones POST con contenido JSON y extrae los datos relevantes
 * para formatear y enviar como conversiones a la API de Meta Conversions
 */

// Permitir CORS si es necesario
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Solo se acepta POST.']);
    exit();
}

// Configuración de Meta
$metaPixelId = '2283474082174153';
$metaAccessToken = 'EAAaKpZArkem4BQQdwYb2gBlWb3Nqgkb44T3F6CF36C3ZAfNuJMyi08gOVHRCUuHLLCv11FAozLZBfP0B2rgEjbvdsmi12WlCnSW6pLFshJgWTbYbA8MYb4MTaLOVmuKnFZBQqrJIaFtGbvdUxXVT25ksY0lPGucqw0AoZAzcLyZCjRIM4ztJTAS9NYhygmzwZDZD';

// Configuración del log
$logDir = __DIR__ . '/logs';
$logFile = $logDir . '/meta-endpoint-' . date('Y-m-d') . '.log';

// Crear directorio de logs si no existe
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

try {
    // Información básica de la petición para logging
    $requestInfo = [
        'timestamp' => date('Y-m-d H:i:s'),
        'method' => $_SERVER['REQUEST_METHOD'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ];
    
    // Leer el contenido JSON del cuerpo de la petición
    $jsonData = file_get_contents('php://input');
    
    // Log de la petición recibida
    $logEntry = [
        'request_info' => $requestInfo,
        'raw_input' => $jsonData,
        'input_length' => strlen($jsonData)
    ];
    
    if (empty($jsonData)) {
        $logEntry['error'] = 'No JSON data received';
        file_put_contents($logFile, "=== ERROR LOG ===\n" . json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n", FILE_APPEND | LOCK_EX);
        throw new Exception('No se recibieron datos JSON');
    }
    
    // Decodificar el JSON
    $data = json_decode($jsonData, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $logEntry['error'] = 'JSON decode error: ' . json_last_error_msg();
        $logEntry['json_error_code'] = json_last_error();
        file_put_contents($logFile, "=== ERROR LOG ===\n" . json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n", FILE_APPEND | LOCK_EX);
        throw new Exception('Error al decodificar JSON: ' . json_last_error_msg());
    }
    
    // Log de datos decodificados exitosamente
    $logEntry['decoded_data'] = $data;
    file_put_contents($logFile, "=== REQUEST LOG ===\n" . json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n", FILE_APPEND | LOCK_EX);
    
    // Verificar si es un webhook de CRM con estructura específica
    // Puede venir en formato directo o con wrapper data.json
    $webhookData = null;
    
    if (isset($data['data']['json']['eventDetails'])) {
        // Formato con wrapper: {"data": {"json": {...}}}
        $webhookData = $data['data']['json'];
        $logEntry['webhook_format'] = 'wrapped';
    } elseif (isset($data['eventDetails'])) {
        // Formato directo: {...}
        $webhookData = $data;
        $logEntry['webhook_format'] = 'direct';
    }
    
    if ($webhookData) {
        $eventDetails = $webhookData['eventDetails'];
        
        // Verificar que el tipo sea "NewUser" y el nombre sea "Potencial Cliente"
        if (isset($eventDetails['type']) && $eventDetails['type'] === 'NewUser' &&
            isset($eventDetails['name']) && $eventDetails['name'] === 'Potencial Cliente') {
            
            // Log del procesamiento exitoso
            $logEntry['webhook_processing'] = 'success';
            $logEntry['contact_data'] = [
                'name' => $webhookData['name'] ?? 'unknown',
                'number' => $webhookData['number'] ?? 'unknown'
            ];
            file_put_contents($logFile, "=== WEBHOOK PROCESSED ===\n" . json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n", FILE_APPEND | LOCK_EX);
            
            if (!isset($webhookData['name']) || !isset($webhookData['number'])) {
                throw new Exception('Faltan datos del contacto (name o number)');
            }
            
            // Procesar como evento Lead
            $processedData = [
                'event_name' => 'Lead',
                'event_time' => isset($webhookData['lastMessage']['timestamp']) 
                    ? $webhookData['lastMessage']['timestamp'] 
                    : time(),
                'event_id' => isset($eventDetails['id']) ? $eventDetails['id'] : uniqid('lead_', true),
                'first_name' => $webhookData['name'],
                'phone' => $webhookData['number'],
                'source_url' => 'webhook_crm',
                'custom_data' => [
                    'lead_source' => 'crm_webhook',
                    'event_type' => $eventDetails['type'],
                    'event_name_detail' => $eventDetails['name'],
                    'unread_messages' => $webhookData['unreadMessages'] ?? 0,
                    'last_message' => $webhookData['lastMessage']['text'] ?? '',
                    'message_type' => $webhookData['lastMessage']['type'] ?? '',
                    'webhook_format' => $logEntry['webhook_format']
                ]
            ];
            
            // Usar los datos procesados
            $data = $processedData;
            
        } else {
            $logEntry['webhook_processing'] = 'rejected';
            $logEntry['rejection_reason'] = [
                'expected_type' => 'NewUser',
                'received_type' => $eventDetails['type'] ?? 'unknown',
                'expected_name' => 'Potencial Cliente',
                'received_name' => $eventDetails['name'] ?? 'unknown'
            ];
            file_put_contents($logFile, "=== WEBHOOK REJECTED ===\n" . json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n", FILE_APPEND | LOCK_EX);
            throw new Exception('El webhook no cumple con los criterios requeridos (type: NewUser, name: Potencial Cliente)');
        }
    }
    
    // Validar que se recibieron los datos mínimos requeridos
    if (!isset($data['event_name'])) {
        throw new Exception('El campo event_name es requerido');
    }
    
    // Extraer y formatear los datos del JSON
    $eventName = sanitizeString($data['event_name']);
    $eventTime = isset($data['event_time']) ? intval($data['event_time']) : time();
    $eventId = isset($data['event_id']) ? sanitizeString($data['event_id']) : uniqid('event_', true);
    
    // Datos del usuario
    $userData = [];
    
    // IP del cliente (priorizar la IP del JSON si existe, sino usar la del servidor)
    $userData['client_ip_address'] = isset($data['client_ip']) 
        ? sanitizeString($data['client_ip']) 
        : $_SERVER['REMOTE_ADDR'] ?? '';
    
    // User Agent (priorizar el del JSON si existe, sino usar el del servidor)
    $userData['client_user_agent'] = isset($data['user_agent']) 
        ? sanitizeString($data['user_agent']) 
        : $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Email (si está presente)
    if (isset($data['email']) && !empty($data['email'])) {
        $userData['em'] = [hash('sha256', strtolower(trim($data['email'])))];
    }
    
    // Teléfono (si está presente)
    if (isset($data['phone']) && !empty($data['phone'])) {
        $userData['ph'] = [hash('sha256', preg_replace('/[^0-9]/', '', $data['phone']))];
    }
    
    // Nombre (si está presente)
    if (isset($data['first_name']) && !empty($data['first_name'])) {
        $userData['fn'] = [hash('sha256', strtolower(trim($data['first_name'])))];
    }
    
    // Apellido (si está presente)
    if (isset($data['last_name']) && !empty($data['last_name'])) {
        $userData['ln'] = [hash('sha256', strtolower(trim($data['last_name'])))];
    }
    
    // Facebook Browser ID (fbp)
    if (isset($data['fbp'])) {
        $userData['fbp'] = sanitizeString($data['fbp']);
    } elseif (isset($_COOKIE['_fbp'])) {
        $userData['fbp'] = $_COOKIE['_fbp'];
    }
    
    // Facebook Click ID (fbc)
    if (isset($data['fbc'])) {
        $userData['fbc'] = sanitizeString($data['fbc']);
    } elseif (isset($_COOKIE['_fbc'])) {
        $userData['fbc'] = $_COOKIE['_fbc'];
    }
    
    // URL de origen
    $eventSourceUrl = isset($data['source_url']) 
        ? sanitizeString($data['source_url']) 
        : ($_SERVER['HTTP_REFERER'] ?? '');
    
    // Datos personalizados del evento
    $customData = [];
    if (isset($data['custom_data']) && is_array($data['custom_data'])) {
        $customData = $data['custom_data'];
    }
    
    // Valor de la conversión (si está presente)
    if (isset($data['value'])) {
        $customData['value'] = floatval($data['value']);
    }
    
    // Moneda (si está presente)
    if (isset($data['currency'])) {
        $customData['currency'] = strtoupper(sanitizeString($data['currency']));
    }
    
    // Enviar la conversión a Meta
    $response = sendMetaConversion(
        $metaPixelId, 
        $metaAccessToken, 
        $eventName, 
        $eventTime, 
        $eventId, 
        $eventSourceUrl, 
        $userData, 
        $customData
    );
    
    // Responder con éxito
    http_response_code(200);
    
    $response = [
        'success' => true,
        'message' => 'Conversión enviada exitosamente',
        'event_id' => $eventId,
        'event_name' => $eventName,
        'meta_response' => $response ? json_decode($response, true) : null
    ];
    
    // Si fue procesado desde un webhook CRM, agregar información adicional
    if (isset($processedData)) {
        $response['webhook_processed'] = true;
        $response['contact_info'] = [
            'name' => $processedData['first_name'],
            'phone' => $processedData['phone'],
            'lead_source' => 'crm_webhook'
        ];
        $response['original_event'] = [
            'id' => $processedData['event_id'],
            'type' => $processedData['custom_data']['event_type'],
            'name' => $processedData['custom_data']['event_name_detail']
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log del error
    $errorLog = [
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'request_info' => $requestInfo ?? [],
        'raw_input' => $jsonData ?? '',
        'decoded_data' => $data ?? null
    ];
    
    file_put_contents($logFile, "=== ERROR LOG ===\n" . json_encode($errorLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n", FILE_APPEND | LOCK_EX);
    
    // Manejar errores
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'debug_info' => [
            'received_data_length' => strlen($jsonData ?? ''),
            'json_valid' => isset($data),
            'error_line' => $e->getLine()
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Función para enviar conversión a Meta Conversions API
 */
function sendMetaConversion($pixelId, $accessToken, $eventName, $eventTime, $eventId, $eventSourceUrl, $userData, $customData = []) {
    $url = "https://graph.facebook.com/v18.0/{$pixelId}/events";
    
    // Construir el evento
    $eventData = [
        'event_name' => $eventName,
        'event_time' => $eventTime,
        'event_id' => $eventId,
        'event_source_url' => $eventSourceUrl,
        'action_source' => 'website',
        'user_data' => $userData
    ];
    
    // Agregar datos personalizados si existen
    if (!empty($customData)) {
        $eventData['custom_data'] = $customData;
    }
    
    $payload = [
        'data' => [$eventData],
        'access_token' => $accessToken,
        'test_event_code' => 'TEST56305'
    ];
    
    // Realizar la petición cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log de errores
    if ($error) {
        error_log("Meta CAPI cURL Error: " . $error);
        throw new Exception("Error de conexión con Meta API: " . $error);
    }
    
    if ($httpCode >= 400) {
        error_log("Meta CAPI HTTP Error: " . $httpCode . " - " . $response);
        throw new Exception("Error HTTP de Meta API: " . $httpCode);
    }
    
    return $response;
}

/**
 * Función para sanitizar strings
 */
function sanitizeString($string) {
    return htmlspecialchars(trim($string), ENT_QUOTES, 'UTF-8');
}

?>