<?php
// ABCABC-CP main entry (dc.abcabc.net/cp/)
// (修正：将控制权委托给中央路由器 app/cp/index.php)
declare(strict_types=1);

// ---------- 路径常量 (必须保留，用于定位核心应用目录) ----------
define('BASE_PATH', dirname(__DIR__, 2));          // /.../abcabc_net
define('CP_APP_DIR', BASE_PATH . '/app/cp');       // /.../abcabc_net/app/cp

// ---------- 引导 & 鉴权 ----------
// 加载引导文件，确保 $pdo、配置和 CP_BASE_URL 已定义
require_once CP_APP_DIR . '/bootstrap.php';
// 加载鉴权函数
require_once CP_APP_DIR . '/src/auth.php';

// ----------------------------------------------------
// --- 核心修改: 移交控制权到中央路由器 (app/cp/index.php) ---
// ----------------------------------------------------
// 无论请求了什么 action，都由中央路由器接管处理。
if (is_file(CP_APP_DIR . '/index.php')) {
    require CP_APP_DIR . '/index.php';
} else {
    // 紧急回退 404
    http_response_code(500);
    echo '<!doctype html><html lang="zh"><meta charset="utf-8"><title>500 Error</title><body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:2rem"><h1>500 Internal Error</h1><p>中央路由器文件丢失：'
        . htmlspecialchars(CP_APP_DIR . '/index.php') 
        . '</p></body></html>';
}

// 阻止任何其他代码执行
exit();