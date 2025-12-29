<?php
/**
 * Meta Conversions API Endpoint (Headless)
 * 
 * Este endpoint NO debe ser accedido directamente por el usuario.
 * Debe ser llamado vía fetch() desde el front-end.
 * 
 * Acepta: POST con JSON
 * Retorna: JSON con status
 */

// Configuración
define('META_PIXEL_ID', '2283474082174153');
define('META_ACCESS_TOKEN', 'EAAaKpZArkem4BQQdwYb2gBlWb3Nqgkb44T3F6CF36C3ZAfNuJMyi08gOVHRCUuHLLCv11FAozLZBfP0B2rgEjbvdsmi12WlCnSW6pLFshJgWTbYbA8MYb4MTaLOVmuKnFZBQqrJIaFtGbvdUxXVT25ksY0lPGucqw0AoZAzcLyZCjRIM4ztJTAS9NYhygmzwZDZD');
define('ENABLE_LOGGING', false);

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Leer el payload JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validar datos requeridos
if (!$data || !isset($data['event_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload - event_id required']);
    exit;
}

// Extraer datos del payload
$eventId = $data['event_id'];
$eventSourceUrl = $data['event_source_url'] ?? '';
$fbp = $data['fbp'] ?? null;
$fbc = $data['fbc'] ?? null;

// Datos del servidor
$clientIpAddress = getClientIpAddress();
$clientUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$eventTime = time();

// Log inicial
if (ENABLE_LOGGING) {
    logDebug("Received CAPI request", [
        'event_id' => $eventId,
        'event_source_url' => $eventSourceUrl,
        'fbp' => $fbp ?: 'null',
        'fbc' => $fbc ?: 'null',
        'ip' => $clientIpAddress
    ]);
}

// Enviar a Meta CAPI
$result = sendMetaConversion(
    META_PIXEL_ID,
    META_ACCESS_TOKEN,
    $clientIpAddress,
    $clientUserAgent,
    $eventTime,
    $eventId,
    $eventSourceUrl,
    $fbp,
    $fbc
);

// Responder
if ($result['success']) {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'event_id' => $eventId]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $result['error']]);
}

/**
 * Obtiene la IP real del cliente considerando proxies
 */
function getClientIpAddress() {
    $ipHeaders = [
        'HTTP_CF_CONNECTING_IP',  // Cloudflare
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR'
    ];
    
    foreach ($ipHeaders as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            // Si es una lista separada por comas, tomar la primera
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            // Validar que sea una IP válida
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/**
 * Envía el evento a Meta Conversions API
 */
function sendMetaConversion($pixelId, $accessToken, $ipAddress, $userAgent, $eventTime, $eventId, $eventSourceUrl, $fbp = null, $fbc = null) {
    $url = "https://graph.facebook.com/v18.0/{$pixelId}/events";
    
    // Construir user_data
    $userData = [
        'client_ip_address' => $ipAddress,
        'client_user_agent' => $userAgent,
    ];
    
    // Solo agregar fbp y fbc si existen y no están vacíos
    if ($fbp && $fbp !== 'null' && $fbp !== '') {
        $userData['fbp'] = $fbp;
    }
    
    if ($fbc && $fbc !== 'null' && $fbc !== '') {
        $userData['fbc'] = $fbc;
    }
    
    // Payload del evento
    $eventData = [
        'data' => [
            [
                'event_name' => 'Contact',
                'event_time' => $eventTime,
                'event_id' => $eventId,
                'event_source_url' => $eventSourceUrl,
                'action_source' => 'website',
                'user_data' => $userData
            ]
        ],
        'access_token' => $accessToken,
        'test_event_code' => 'TEST77145'
    ];
    
    // Enviar con cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($eventData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log resultado
    if (ENABLE_LOGGING) {
        if ($error) {
            logDebug("CAPI Error", ['error' => $error]);
        } else {
            logDebug("CAPI Success", [
                'http_code' => $httpCode,
                'response' => $response,
                'sent_data' => [
                    'event_name' => 'Contact',
                    'event_id' => $eventId,
                    'fbp' => $fbp ?: 'null',
                    'fbc' => $fbc ?: 'null',
                    'event_source_url' => $eventSourceUrl
                ]
            ]);
        }
    }
    
    // Retornar resultado
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    $responseData = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'response' => $responseData];
    } else {
        return ['success' => false, 'error' => $response];
    }
}

/**
 * Función auxiliar para logging
 */
function logDebug($message, $data = []) {
    $logFile = __DIR__ . '/logs/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "$timestamp - $message\n";
    
    if (!empty($data)) {
        $logEntry .= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
    
    $logEntry .= "---\n";
    
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}
