<?php
/**
 * DTS 时间线事件删除 - v2.1.1
 * [v2.1.1] 软删除事件并重新计算对象状态
 */
declare(strict_types=1);
require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

try {
    $event_id = (int)dts_post('event_id');

    if (empty($event_id)) {
        dts_set_feedback('danger', '缺少事件ID');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? CP_BASE_URL . 'dts_main'));
        exit();
    }

    // 调用软删除函数
    $result = dts_soft_delete_event($pdo, $event_id);

    if ($result['success']) {
        dts_set_feedback('success', $result['message']);

        // 跳转回对象详情页
        if ($result['object_id']) {
            header("Location: " . CP_BASE_URL . "dts_object_detail&id={$result['object_id']}");
        } else {
            header("Location: " . CP_BASE_URL . "dts_main");
        }
    } else {
        dts_set_feedback('danger', $result['message']);
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? CP_BASE_URL . 'dts_main'));
    }
    exit();

} catch (Exception $e) {
    error_log("DTS Timeline Delete Error (v2.1.1): " . $e->getMessage());
    dts_set_feedback('danger', '删除失败：' . $e->getMessage());
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? CP_BASE_URL . 'dts_main'));
    exit();
}
