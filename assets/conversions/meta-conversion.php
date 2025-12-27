<?php

function sendConversionsAndRedirect() {
    // Meta Conversions API Configuration
    global $metaPixelId;
    $metaPixelId = '2283474082174153';
    $metaAccessToken = 'EAAaKpZArkem4BQQdwYb2gBlWb3Nqgkb44T3F6CF36C3ZAfNuJMyi08gOVHRCUuHLLCv11FAozLZBfP0B2rgEjbvdsmi12WlCnSW6pLFshJgWTbYbA8MYb4MTaLOVmuKnFZBQqrJIaFtGbvdUxXVT25ksY0lPGucqw0AoZAzcLyZCjRIM4ztJTAS9NYhygmzwZDZD';
    
    // WhatsApp redirect URL
    $whatsappUrl = 'https://wa.me/5493516217424?text=' . urlencode('Hola! Quiero información del Miami Women Trip...');
    
    // Get user data
    $clientIpAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $clientUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $eventTime = time();
    $eventId = uniqid('event_', true);
    
    // Get Facebook Browser ID (fbp) and Facebook Click ID (fbc) from cookies
    $fbp = $_COOKIE['_fbp'] ?? null;
    $fbc = $_COOKIE['_fbc'] ?? null;
    
    // If fbc is not in cookie, try to get it from URL parameter
    if (!$fbc && isset($_GET['fbclid'])) {
        $fbc = 'fb.1.' . time() . '.' . $_GET['fbclid'];
    }
    
    // Send Meta Conversions API Event
    sendMetaConversion($metaPixelId, $metaAccessToken, $clientIpAddress, $clientUserAgent, $eventTime, $eventId, $fbp, $fbc);
    
    // Redirect to WhatsApp with Meta Pixel tracking
    redirectToWhatsApp($whatsappUrl, $eventId);
}

function sendMetaConversion($pixelId, $accessToken, $ipAddress, $userAgent, $eventTime, $eventId, $fbp = null, $fbc = null) {
    $url = "https://graph.facebook.com/v18.0/{$pixelId}/events";
    
    // Build user_data array
    $userData = [
        'client_ip_address' => $ipAddress,
        'client_user_agent' => $userAgent,
    ];
    
    // Add fbp (Facebook Browser ID) if available
    if ($fbp) {
        $userData['fbp'] = $fbp;
    }
    
    // Add fbc (Facebook Click ID) if available
    if ($fbc) {
        $userData['fbc'] = $fbc;
    }
    
    $eventData = [
        'data' => [
            [
                'event_name' => 'Contact',
                'event_time' => $eventTime,
                'event_id' => $eventId,
                'event_source_url' => $_SERVER['HTTP_REFERER'] ?? '',
                'action_source' => 'website',
                'user_data' => $userData
            ]
        ],
        'access_token' => $accessToken
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($eventData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log response for debugging (optional)
    if ($error) {
        error_log("Meta CAPI Error: " . $error);
    }
    
    return $response;
}

function redirectToWhatsApp($redirectUrl, $eventId) {
    global $metaPixelId;
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Abriendo WhatsApp...</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <!-- Meta Pixel Code -->
        <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        
        fbq('init', '<?php echo $metaPixelId; ?>');
        fbq('track', 'PageView');
        
        // Track Contact event with same event ID for deduplication
        fbq('track', 'Contact', {}, {
            eventID: '<?php echo $eventId; ?>'
        });
        
        // Redirect to WhatsApp after tracking
        setTimeout(function() {
            window.location.href = '<?php echo htmlspecialchars($redirectUrl); ?>';
        }, 1000);
        </script>
        <noscript>
            <img height="1" width="1" style="display:none"
            src="https://www.facebook.com/tr?id=<?php echo $metaPixelId; ?>&ev=PageView&noscript=1"/>
        </noscript>
        <!-- End Meta Pixel Code -->
        <style>
            body {
                font-family: Arial, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                background: linear-gradient(135deg, #25D366, #128C7E);
                color: white;
                text-align: center;
            }
            .container {
                padding: 40px;
                border-radius: 20px;
                background: rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(10px);
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            }
            .whatsapp-icon {
                font-size: 48px;
                margin-bottom: 20px;
                animation: pulse 1.5s infinite;
            }
            .message {
                font-size: 18px;
                margin-bottom: 10px;
                font-weight: 500;
            }
            .loading {
                display: inline-block;
                animation: dots 1.5s infinite;
            }
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.1); }
                100% { transform: scale(1); }
            }
            @keyframes dots {
                0%, 20% { content: '.'; }
                40% { content: '..'; }
                60% { content: '...'; }
                80%, 100% { content: ''; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="whatsapp-icon">💬</div>
            <div class="message">Abriendo WhatsApp<span class="loading">...</span></div>
            <div style="font-size: 14px; opacity: 0.8; margin-top: 10px;">Te redirigiremos en un momento</div>
        </div>
        <script>
            // Fallback redirect in case the timeout doesn't work
            window.addEventListener('load', function() {
                setTimeout(function() {
                    if (window.location.href.indexOf('wa.me') === -1) {
                        window.location.href = '<?php echo htmlspecialchars($redirectUrl); ?>';
                    }
                }, 2000);
            });
        </script>
    </body>
    </html>
    <?php
}

// Execute the main function if this file is accessed directly
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    sendConversionsAndRedirect();
}

?>