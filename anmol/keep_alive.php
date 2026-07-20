<?php
// ===== AUTO-CREATE STRUCTURE =====
if (!is_dir('photos')) {
    mkdir('photos', 0777, true);
}
if (!file_exists('visitors.json')) {
    file_put_contents('visitors.json', '{}');
}
if (!file_exists('links.json')) {
    file_put_contents('links.json', '{}');
}
if (!file_exists('debug.log')) {
    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - keep_alive.php created\n");
}
// ===== END AUTO-CREATE =====

// Log this ping
file_put_contents('kept_alive.log', date('Y-m-d H:i:s') . " - Ping from cron-job\n", FILE_APPEND);

echo "OK - Server is alive!";
?>
