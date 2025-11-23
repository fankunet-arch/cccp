<?php
/**
 * DTS 事件删除动作
 */

declare(strict_types=1);

require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

// 必须是 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

// 检查登录状态
require_login();

try {
    $event_id = dts_post('event_id');
    $object_id = dts_post('object_id'); // 用于跳转回对象详情页

    if (empty($event_id) || empty($object_id)) {
        dts_set_feedback('danger', '无效的请求：缺少事件 ID 或对象 ID。');
        header("Location: " . CP_BASE_URL . "dts_object");
        exit();
    }

    // [安全] 在删除前，先验证事件是否存在
    $stmt = $pdo->prepare("SELECT id FROM cp_dts_event WHERE id = ?");
    $stmt->execute([$event_id]);
    if ($stmt->fetch() === false) {
        dts_set_feedback('warning', '事件不存在或已被删除。');
        header("Location: " . CP_BASE_URL . "dts_object_detail&id=$object_id");
        exit();
    }

    // 执行删除
    $stmt_del = $pdo->prepare("DELETE FROM cp_dts_event WHERE id = ?");
    $stmt_del->execute([$event_id]);

    // [重要] 删除事件后，必须重新计算并更新对象的状态
    dts_update_object_state($pdo, (int)$object_id);

    dts_set_feedback('success', '事件已成功删除。');
    header("Location: " . CP_BASE_URL . "dts_object_detail&id=$object_id");
    exit();

} catch (Exception $e) {
    error_log("DTS Event Deletion Error: " . $e->getMessage());
    dts_set_feedback('danger', '删除失败：' . $e->getMessage());
    // 尽可能跳转回原页面
    if (!empty($object_id)) {
        header("Location: " . CP_BASE_URL . "dts_object_detail&id=$object_id");
    } else {
        header("Location: " . CP_BASE_URL . "dts_main");
    }
    exit();
}
