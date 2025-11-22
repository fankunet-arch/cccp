<?php
// ABCABC-CP main entry (dc.abcabc.net/)
declare(strict_types=1);

// [DEBUG] 记录所有请求到达入口
$debug_log = dirname(dirname(__DIR__)) . '/logs/debug.log';
error_log('[' . date('Y-m-d H:i:s') . '] CP index hit, action=' . ($_GET['action'] ?? 'none') . PHP_EOL, 3, $debug_log);

// ---------- 路径常量 ----------
// [修复] BASE_PATH 应该指向项目根目录，而不是 /dc_html
define('BASE_PATH', dirname(dirname(__DIR__)));
define('CP_APP_DIR', BASE_PATH . '/app/cp');

// ---------- 更新 CP_BASE_URL ----------
if (!defined('CP_BASE_URL')) {
    define('CP_BASE_URL', '/index.php?action=');
}

// ---------- 引导 & 鉴权 ----------
require_once CP_APP_DIR . '/bootstrap.php';
require_once CP_APP_DIR . '/src/auth.php';

// ---------- 移交控制权到中央路由器 ----------
if (is_file(CP_APP_DIR . '/index.php')) {
    require CP_APP_DIR . '/index.php';
} else {
    // 紧急回退 500
    http_response_code(500);
    echo '<!doctype html><html lang="zh"><meta charset="utf-8"><title>500 Error</title><body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:2rem"><h1>500 Internal Error</h1><p>中央路由器文件丢失：'
        . htmlspecialchars(CP_APP_DIR . '/index.php')
        . '</p></body></html>';
}

// 阻止任何其他代码执行
exit();