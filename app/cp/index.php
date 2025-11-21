<?php
// /app/cp/index.php
// (修复：确保所有视图都加载 AdminLTE 布局，并修复路由)

declare(strict_types=1);

// ---------- 引导与鉴权（修正版：如果 APP_PATH_CP 未定义，则尝试加载 bootstrap）----------
// 无论如何，确保 $pdo 和核心常量被加载，否则路由将失败。
if (!defined('APP_PATH_CP')) {
    define('APP_PATH_CP', dirname(__DIR__)); // 推测 APP_PATH_CP 为当前文件的父目录
}
if (!isset($pdo) && file_exists(APP_PATH_CP . '/bootstrap.php')) {
    require_once APP_PATH_CP . '/bootstrap.php'; // 确保定义 BASE_PATH, CP_BASE_URL
}
if (!function_exists('require_login') && file_exists(APP_PATH_CP . '/src/auth.php')) {
    require_once APP_PATH_CP . '/src/auth.php'; // 确保鉴权函数存在
}
global $pdo;

// 1. 获取路由 (action)
$action = $_GET['action'] ?? 'login_page'; // 修复: 默认 action 必须是 'login_page'

// 2. 定义路由白名单 (哪些 action 不需要登录即可访问)
$public_actions = [
    'login_page'    => 'display_login_page', // 显示登录页的函数
    'login_process' => 'process_login'       // 处理登录表单的函数
];

// 3. 处理公开路由
if (array_key_exists($action, $public_actions)) {
    if ($action === 'login_process') {
        if (function_exists('process_login') && isset($pdo)) process_login($pdo);
    } else {
        if (function_exists('display_login_page')) display_login_page();
    }
    exit(); // 公开路由执行完毕后退出
}

// 4. ---- (安全门) ----
// 从这里开始，所有路由都需要登录
if (function_exists('require_login')) {
    require_login();
}

// 5. 定义登录后才能访问的路由 (action => '文件路径')
$protected_routes = [
    // 仪表盘
    'dashboard' => APP_PATH_CP . '/views/dashboard/index.php',

    // 个人资料
    'profile'       => APP_PATH_CP . '/views/profile/index.php',
    'profile_save'  => APP_PATH_CP . '/actions/profile/save.php',

    // Sushisom (A1.png)
    'som_add'       => APP_PATH_CP . '/views/som_view_add.php',
    'som_save'      => APP_PATH_CP . '/actions/som_action_sushisom_save.php',
    'som_get_data'  => APP_PATH_CP . '/actions/som_action_sushisom_get_data.php',

    // 工资
    'som_salary_add'      => APP_PATH_CP . '/som/views/som_view_salary_add.php',
    'som_salary_save'     => APP_PATH_CP . '/som/actions/som_action_salary_save.php',
    'som_salary_get_data' => APP_PATH_CP . '/som/actions/som_action_salary_get_data.php',

    // 报表
    'som_report_store'              => APP_PATH_CP . '/som/views/som_view_report_store.php',
    'som_report_store_get_data'     => APP_PATH_CP . '/som/actions/som_action_report_store_get_data.php',
    'som_report_investor'           => APP_PATH_CP . '/som/views/som_view_report_investor.php',
    'som_report_investor_get_data'  => APP_PATH_CP . '/som/actions/som_action_report_investor_get_data.php',

    // <tea> 项目路由
    'tea_dashboard'                 => APP_PATH_CP . '/tea/views/tea_view_dashboard.php',
    'tea_add'                       => APP_PATH_CP . '/tea/views/tea_view_add.php',
    'tea_save'                      => APP_PATH_CP . '/tea/actions/tea_action_save.php',
    'tea_report_investor'           => APP_PATH_CP . '/tea/views/tea_view_report_investor.php',

    // 【已修复】新增缺失的路由，用于 AJAX 获取数据
    'tea_report_investor_get_data'  => APP_PATH_CP . '/tea/actions/tea_action_report_investor_get_data.php',

    // 【新增】店铺管理路由
    'tea_store_manage'              => APP_PATH_CP . '/tea/views/tea_view_store_manage.php', // 店铺管理视图
    'tea_store_save'                => APP_PATH_CP . '/tea/actions/tea_action_store_save.php', // 店铺保存/修改动作

    // <DTS> 模块路由（Date Timeline System - 日期时间线系统）
    // 总览
    'dts_main'                      => APP_PATH_CP . '/dts/views/dts_main.php',

    // 主体管理
    'dts_subject'                   => APP_PATH_CP . '/dts/views/dts_subject.php',
    'dts_subject_save'              => APP_PATH_CP . '/dts/actions/dts_subject_save.php',
    'dts_subject_get_data'          => APP_PATH_CP . '/dts/actions/dts_subject_get_data.php',

    // 对象管理
    'dts_object'                    => APP_PATH_CP . '/dts/views/dts_object.php',
    'dts_object_form'               => APP_PATH_CP . '/dts/views/dts_object_form.php',
    'dts_object_detail'             => APP_PATH_CP . '/dts/views/dts_object_detail.php',
    'dts_object_save'               => APP_PATH_CP . '/dts/actions/dts_object_save.php',

    // 规则管理
    'dts_rule'                      => APP_PATH_CP . '/dts/views/dts_rule.php',

    // 事件管理
    'dts_ev_edit'                   => APP_PATH_CP . '/dts/views/dts_view_quick.php', // Use Quick View as Unified Editor
    'dts_ev_save'                   => APP_PATH_CP . '/dts/actions/dts_ev_save.php', // Deprecated but kept for legacy
    'dts_ev_del'                    => APP_PATH_CP . '/dts/actions/dts_ev_del.php',

    // 极速录入 (Smart Quick Entry)
    'dts_quick'                     => APP_PATH_CP . '/dts/views/dts_view_quick.php', // 允许 dts_quick 访问
    'dts_quick_add'                 => APP_PATH_CP . '/dts/views/dts_view_quick.php', // 重定向目标
    'dts_quick_save'                => APP_PATH_CP . '/dts/actions/dts_action_quick_save.php', // 表单提交动作

    // 分类管理
    'dts_category_manage'           => APP_PATH_CP . '/dts/views/dts_category_manage.php',
    'dts_category_save'             => APP_PATH_CP . '/dts/actions/dts_category_save.php',

    // DTS 基线条目管理
    'dts_entry'                     => APP_PATH_CP . '/dts/views/dts_entry.php',
    'dts_entry_form'                => APP_PATH_CP . '/dts/views/dts_entry_form.php',
    'dts_entry_save'                => APP_PATH_CP . '/dts/actions/dts_entry_save.php',

    // 退出登录
    'logout' => 'process_logout' // 特殊：函数调用
];

// 6. 路由分发
if (isset($protected_routes[$action])) {
    $route = $protected_routes[$action];

    if ($action === 'logout') {
        if (function_exists('process_logout')) process_logout();
        exit();
    }

    // 检查是 Action 还是 View
    $is_action = strpos($route, '/actions/') !== false || (strpos($route, 'save.php') !== false) || (strpos($route, 'get_data.php') !== false);

    if ($is_action) {
        // 如果是 Action (例如保存、获取数据)，则直接包含它
        require_once $route;
    }
    // 所有 View (视图页面)，加载标准 AdminLTE 布局
    else {
        require_once APP_PATH_CP . '/views/layouts/header.php';
        require_once $route;
        require_once APP_PATH_CP . '/views/layouts/footer.php';
    }

} else {
    // 默认或未找到路由，尝试加载 404 视图
    http_response_code(404);
    $not_found_view = APP_PATH_CP . '/views/errors/404.php';

    // 404 页面的最低要求是加载 header 和 footer
    if (file_exists(APP_PATH_CP . '/views/layouts/header.php') && file_exists(APP_PATH_CP . '/views/layouts/footer.php')) {
        require_once APP_PATH_CP . '/views/layouts/header.php';

        // 渲染简易 404 内容
        echo '<div class="content-wrapper"><section class="content" style="padding:20px;">
                <h1>404 Not Found</h1>
                <p>页面未找到：' . htmlspecialchars($action) . '</p>
              </section></div>';

        require_once APP_PATH_CP . '/views/layouts/footer.php';
    } else {
        // 极端回退（无布局）
        echo '<!doctype html><html lang="zh"><meta charset="utf-8"><title>404</title><body style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;padding:2rem"><h1>404 Not Found</h1><p>页面未找到：<code>'
            . htmlspecialchars((string)$action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</code></p></body></html>';
    }
}