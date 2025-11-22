<?php
// [DEBUG] 确认 dts_ev_add action 是否被执行
$debug_log = dirname(__DIR__, 3) . '/logs/debug.log';
error_log('[' . date('Y-m-d H:i:s') . '] DTS_EV_ADD reached' . PHP_EOL, 3, $debug_log);

/**
 * DTS v2.1.2 - 对象追加事件专用 Action
 *
 * 功能：为现有对象添加新事件
 * GET: 显示追加事件表单
 * POST: 保存新事件并更新对象状态
 *
 * 设计目标：
 * - 完全独立的链路，不依赖 dts_quick / dts_ev_edit
 * - 避免使用 mode=append 等容易触发 WAF 的参数
 * - 使用统一的 dts_save_event() 保存逻辑
 */

declare(strict_types=1);
require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

// ========================================
// GET 请求：显示追加事件表单
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $object_id = dts_get('object_id');

    if (!$object_id) {
        dts_set_feedback('danger', '缺少对象 ID');
        header('Location: ' . CP_BASE_URL . 'dts_object');
        exit();
    }

    // 查询对象信息（包含主体信息）
    $stmt = $pdo->prepare("
        SELECT o.*, s.subject_name, s.subject_type
        FROM cp_dts_object o
        LEFT JOIN cp_dts_subject s ON o.subject_id = s.id
        WHERE o.id = ? AND o.is_deleted = 0
    ");
    $stmt->execute([$object_id]);
    $object = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$object) {
        // 对象不存在或已删除，显示错误提示（不用 header 403）
        $error_message = '对象不存在或已删除，无法新增事件。';
        $view_file = APP_PATH_CP . '/dts/views/dts_ev_add.php';
        require APP_PATH_CP . '/includes/header.php';
        echo '<section class="content">';
        echo '<div class="alert alert-danger">';
        echo '<i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($error_message);
        echo '<br><br><a href="' . CP_BASE_URL . 'dts_object" class="btn btn-primary">返回对象列表</a>';
        echo '</div>';
        echo '</section>';
        require APP_PATH_CP . '/includes/footer.php';
        exit();
    }

    // 查询所有启用的规则（供用户选择）
    $stmt = $pdo->prepare("SELECT * FROM cp_dts_rule WHERE rule_status = 1 ORDER BY cat_main, cat_sub, rule_name");
    $stmt->execute();
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 加载视图
    $view_file = APP_PATH_CP . '/dts/views/dts_ev_add.php';
    require APP_PATH_CP . '/includes/header.php';
    require $view_file;
    require APP_PATH_CP . '/includes/footer.php';
    exit();
}

// ========================================
// POST 请求：保存新事件
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $object_id = (int)dts_post('object_id');

        if (!$object_id) {
            throw new Exception('对象 ID 缺失');
        }

        // 验证对象是否存在
        $stmt = $pdo->prepare("
            SELECT id, subject_id, object_name, object_type_main, object_type_sub
            FROM cp_dts_object
            WHERE id = ? AND is_deleted = 0
        ");
        $stmt->execute([$object_id]);
        $object = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$object) {
            throw new Exception('对象不存在或已删除');
        }

        // 整理事件参数（与 dts_action_quick_save.php 保持一致）
        $event_params = [
            'subject_id' => (int)$object['subject_id'],
            'event_type' => trim(dts_post('event_type', '办理')),
            'event_date' => dts_post('event_date'),
            'rule_id' => dts_post('rule_id') ?: null,
            'expiry_date_new' => dts_post('expiry_date_new') ?: null,
            'mileage_now' => dts_post('mileage_now') ?: null,
            'note' => trim(dts_post('note', '')),
            // 提供分类信息用于默认规则匹配
            'cat_main' => $object['object_type_main'],
            'cat_sub' => $object['object_type_sub'] ?? null
        ];

        // 验证必填字段
        if (empty($event_params['event_date'])) {
            throw new Exception('事件日期为必填项');
        }

        // 调用统一事件保存入口
        $saved_event_id = dts_save_event($pdo, $object_id, $event_params);

        if (!$saved_event_id) {
            throw new Exception('事件保存失败');
        }

        // 设置成功反馈消息
        dts_set_feedback('success', "事件已成功添加到对象【{$object['object_name']}】！");

        $pdo->commit();

        // 跳转回对象详情页
        header('Location: ' . CP_BASE_URL . 'dts_object_detail&id=' . $object_id);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("DTS v2.1.2 Event Add Error: " . $e->getMessage());
        dts_set_feedback('danger', '保存失败：' . $e->getMessage());

        // 如果有 object_id，返回对象详情页；否则返回对象列表
        $object_id = (int)dts_post('object_id');
        if ($object_id) {
            header('Location: ' . CP_BASE_URL . 'dts_object_detail&id=' . $object_id);
        } else {
            header('Location: ' . CP_BASE_URL . 'dts_object');
        }
        exit();
    }
}

// 其他请求方法不允许
exit('Method Not Allowed');
