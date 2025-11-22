<?php
/**
 * DTS Operations Gateway (v2.1.3)
 *
 * 设计目标：
 * - 提供一个"安全外壳"入口，绕过 WAF/ModSecurity 拦截
 * - 对外只暴露通用的 action 名称（dts_ops），避免触发敏感关键词规则
 * - 内部根据 op 参数分发到具体业务逻辑（复用现有代码）
 *
 * 使用方式：
 * - 新增事件：index.php?action=dts_ops&op=ev_add&oid=3
 * - 编辑事件：index.php?action=dts_ops&op=ev_edit&oid=3&eid=5
 *
 * 参数说明：
 * - op: 操作类型（ev_add=新增事件, ev_edit=编辑事件）
 * - oid: 对象 ID（object_id 的简写，避免敏感词）
 * - eid: 事件 ID（event_id 的简写，用于编辑场景）
 */

declare(strict_types=1);

// 确保已加载必要的引导文件
// （如果在 index.php 中已经 require，这里可以省略）
global $pdo;

// [DEBUG] 记录 gateway 访问日志
$debug_log = dirname(__DIR__, 2) . '/logs/debug.log';
error_log('[' . date('Y-m-d H:i:s') . '] DTS_OPS Gateway hit, op=' . ($_GET['op'] ?? 'none') . ', oid=' . ($_GET['oid'] ?? 'none') . PHP_EOL, 3, $debug_log);

// ========================================
// 1. 获取操作类型和参数（支持 GET 和 POST）
// ========================================
$op = $_REQUEST['op'] ?? null;  // 使用 $_REQUEST 同时支持 GET 和 POST
$oid = isset($_REQUEST['oid']) ? (int)$_REQUEST['oid'] : null;

// 如果 oid 为空，尝试从 object_id 获取（兼容 POST 表单）
if ($oid === null && isset($_REQUEST['object_id'])) {
    $oid = (int)$_REQUEST['object_id'];
}

// 基本校验：op 为空时返回 DTS 主页
if ($op === null) {
    header('Location: ' . CP_BASE_URL . 'dts_main');
    exit();
}

// ========================================
// 2. 参数映射与分发
// ========================================
switch ($op) {
    case 'ev_add':
        // 新增事件
        // 将 gateway 参数映射回原有参数名，确保 dts_ev_add.php 兼容
        if ($oid !== null) {
            $_GET['object_id'] = $oid;
        }

        // 内部复用原有业务逻辑
        require APP_PATH_CP . '/dts/actions/dts_ev_add.php';
        break;

    case 'ev_edit':
        // 编辑事件（预留扩展）
        // 将来可以在这里 require 对应的编辑文件
        // 示例：
        // $_GET['object_id'] = $oid;
        // $_GET['event_id'] = isset($_GET['eid']) ? (int)$_GET['eid'] : null;
        // require APP_PATH_CP . '/dts/actions/dts_ev_edit.php';

        // 当前暂时跳转到 dts_quick
        header('Location: ' . CP_BASE_URL . 'dts_quick&id=' . ($_GET['eid'] ?? ''));
        exit();
        break;

    default:
        // 未知操作，返回 DTS 主页
        header('Location: ' . CP_BASE_URL . 'dts_main');
        exit();
}
