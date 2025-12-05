<?php
/**
 * MRS Package Management System - Backend Router
 * 文件路径: dc_html/mrs/ap/index.php
 * 说明: 后台管理中央路由入口 (网络可访问)
 */

// 定义系统入口标识
define('MRS_ENTRY', true);

// 定义项目根目录 (dc_html的上级目录)
define('PROJECT_ROOT', dirname(dirname(dirname(__DIR__))));

// 加载bootstrap (在app目录中)
require_once PROJECT_ROOT . '/app/mrs/bootstrap.php';

// 获取action参数
$action = $_GET['action'] ?? 'inventory_list';
$action = basename($action); // 防止路径遍历

// 身份验证: 所有非登录操作必须经过会话校验
if ($action !== 'login' && $action !== 'do_login') {
    mrs_require_login();
}

// 后台允许的action列表
$allowed_actions = [
    'login',                // 登录页面
    'do_login',             // 处理登录
    'logout',               // 登出
    'inventory_list',       // 库存列表
    'inventory_detail',     // 库存明细
    'inbound',              // 入库页面
    'inbound_save',         // 保存入库
    'outbound',             // 出库页面
    'outbound_save',        // 保存出库
    'reports',              // 统计报表
    'sku_manage',           // 物料管理
    'sku_save',             // 保存物料
    'status_change',        // 状态变更
    'batch_print',          // 批次箱贴打印
    'destination_manage',   // 去向管理
    'destination_save',     // 保存去向
];

// 验证action是否允许
if (!in_array($action, $allowed_actions)) {
    $accepts_json = isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($accepts_json || $is_ajax) {
        mrs_json_response(false, null, 'Invalid action');
    }

    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>404 - Page Not Found</title>';
    echo '<style>body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:40px;}';
    echo '.card{max-width:520px;margin:0 auto;background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,0.08);}';
    echo '.card h1{margin-top:0;font-size:22px;color:#c62828;} .card p{color:#444;line-height:1.6;} .card a{color:#1565c0;text-decoration:none;font-weight:600;}</style>';
    echo '</head><body><div class="card"><h1>404 - 无效的后台入口</h1><p>请求的操作未被允许或链接已失效。</p>';
    echo '<p><a href="/mrs/ap/index.php?action=inventory_list">返回后台首页</a></p></div></body></html>';
    exit;
}

// API action (返回JSON)
$api_actions = [
    'do_login',
    'inbound_save',
    'outbound_save',
    'logout',
    'sku_save',
    'status_change',
    'destination_save'
];

// 路由到对应的action或API文件 (在app目录中)
if (in_array($action, $api_actions)) {
    // API路由
    $api_file = MRS_API_PATH . '/' . $action . '.php';
    if (file_exists($api_file)) {
        require_once $api_file;
    } else {
        mrs_json_response(false, null, 'API not found');
    }
} else {
    // 页面路由
    $view_file = MRS_VIEW_PATH . '/' . $action . '.php';
    if (file_exists($view_file)) {
        require_once $view_file;
    } else {
        http_response_code(404);
        die('Page not found');
    }
}
