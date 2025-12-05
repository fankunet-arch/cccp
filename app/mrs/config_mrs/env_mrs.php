<?php
/**
 * MRS System Configuration
 * 文件路径: app/mrs/config_mrs/env_mrs.php
 * 说明: MRS 系统配置文件
 */

// 防止直接访问
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 数据库配置
define('MRS_DB_HOST', 'mhdlmskp2kpxguj.mysql.db');
define('MRS_DB_PORT', '3306');
define('MRS_DB_NAME', 'mhdlmskp2kpxguj');
define('MRS_DB_USER', 'mhdlmskp2kpxguj');
define('MRS_DB_PASS', 'BWNrmksqMEqgbX37r3QNDJLGRrUka');
define('MRS_DB_CHARSET', 'utf8mb4');

// 路径常量
define('MRS_APP_PATH', PROJECT_ROOT . '/app/mrs');
define('MRS_CONFIG_PATH', MRS_APP_PATH . '/config_mrs');
define('MRS_LIB_PATH', MRS_APP_PATH . '/lib');
define('MRS_VIEW_PATH', MRS_APP_PATH . '/views');
define('MRS_API_PATH', MRS_APP_PATH . '/api');

// 会话配置（与 Express 保持一致）
// Express 默认使用 PHP 的默认会话名，直接复用即可
define('MRS_SESSION_NAME', ini_get('session.name') ?: 'PHPSESSID');
define('MRS_SESSION_TIMEOUT', 1800); // 30分钟
define('MRS_SESSION_SAMESITE', 'Strict');

/**
 * 获取数据库连接
 * @return PDO
 * @throws PDOException
 */
function get_mrs_db_connection() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            MRS_DB_HOST,
            MRS_DB_PORT,
            MRS_DB_NAME,
            MRS_DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, MRS_DB_USER, MRS_DB_PASS, $options);

        return $pdo;
    } catch (PDOException $e) {
        error_log('MRS Database connection error: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * 启动安全会话
 */
function mrs_start_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // 参考 Express 的默认设置，仅调整必要参数
        $is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';

        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', $is_https ? 1 : 0);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', MRS_SESSION_SAMESITE);

        $params = session_get_cookie_params();
        session_name(MRS_SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => $params['lifetime'],
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $is_https,
            'httponly' => true,
            'samesite' => MRS_SESSION_SAMESITE,
        ]);

        session_start();

        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
    }
}

/**
 * 日志记录函数
 * @param string $message
 * @param string $level
 * @param array $context
 */
function mrs_log($message, $level = 'INFO', $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $context_str = !empty($context) ? json_encode($context) : '';
    $log_message = sprintf("[%s] [%s] %s %s\n", $timestamp, $level, $message, $context_str);
    error_log($log_message);
}

/**
 * JSON响应输出
 * @param bool $success
 * @param mixed $data
 * @param string $message
 */
function mrs_json_response($success, $data = null, $message = '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 获取JSON输入
 * @return array|null
 */
function mrs_get_json_input() {
    $input = file_get_contents('php://input');
    if (empty($input)) {
        return null;
    }
    return json_decode($input, true);
}
