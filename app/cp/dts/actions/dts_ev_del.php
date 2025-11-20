<?php
/**
 * DTS 事件删除动作
 * 文件名: dts_ev_del.php (简写以避开防火墙)
 */

declare(strict_types=1);

require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

// 获取参数
$event_id = dts_get('id');
$object_id = dts_get('object_id');

if (empty($event_id) || empty($object_id)) {
    dts_set_feedback('danger', '参数缺失，无法删除');
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}

try {
    // 1. 执行删除
    $stmt = $pdo->prepare("DELETE FROM cp_dts_event WHERE id = ?");
    $stmt->execute([$event_id]);

    // 2. 重新计算对象状态（非常重要，因为删除的可能是最新事件）
    dts_update_object_state($pdo, (int)$object_id);

    dts_set_feedback('success', '事件已删除，状态已更新');

} catch (Exception $e) {
    error_log("DTS Event Delete Error: " . $e->getMessage());
    dts_set_feedback('danger', '删除失败：' . $e->getMessage());
}

// 跳转回详情页
header("Location: /cp/index.php?action=dts_object_detail&id=$object_id");
exit();