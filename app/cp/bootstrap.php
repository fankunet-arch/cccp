<?php
// /app/cp/bootstrap.php
// (修复：[2025-11-10] 恢复常量定义，并使用 if(!defined) 保护，以解决 A2.png 的 "Undefined constant" 错误)

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 定义常量
// (修复：使用 if (!defined(...)) 来防止 "Constant already defined" 冲突)
// (这些定义是必需的，因为此文件可能被 som_bootstrap.php 独立加载)

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2)); // 指向 /abcabc_net
}
if (!defined('APP_PATH_CP')) {
    define('APP_PATH_CP', BASE_PATH . '/app/cp');
}
if (!defined('CP_BASE_URL')) {
    define('CP_BASE_URL', '/index.php?action=');
}


// 1. 加载配置
// (修复：APP_PATH_CP 现在已在此文件 中定义，Line 19 不会再失败)
$config = require_once APP_PATH_CP . '/config_cp/env_cp.php';

// 2. 设置时区
date_default_timezone_set($config['timezone']);

// 3. 错误处理
if ($config['app_debug']) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
    // 生产环境中应记录日志
}

// 4. 创建全局 PDO 连接
$db_config = $config['db'];
$dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['name']};charset={$db_config['charset']}";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // 将 $pdo 定义为全局变量，以便在后续的 action 和 view 中使用
    $pdo = new PDO($dsn, $db_config['user'], $db_config['pass'], $options);
} catch (\PDOException $e) {
    error_log('Database Connection Error: ' . $e->getMessage());
    die('数据库连接失败，请联系管理员。');
}