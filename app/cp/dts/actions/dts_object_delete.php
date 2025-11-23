<?php
/**
 * DTS 对象删除 - v2.1.1
 * [v2.1.1] 软删除对象(可选级联删除事件)
 */
declare(strict_types=1);
require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

try {
    $object_id = (int)dts_post('object_id');
    $mode = dts_post('delete_mode', 'object_only'); // 'object_only' 或 'cascade'

    if (empty($object_id)) {
        dts_set_feedback('danger', '缺少对象ID');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? CP_BASE_URL . 'dts_object'));
        exit();
    }

    // 调用软删除函数
    $result = dts_soft_delete_object($pdo, $object_id, $mode);

    if ($result['success']) {
        $msg = $result['message'];
        if ($mode === 'cascade' && $result['stats']['deleted_events'] > 0) {
            $msg .= " (同时删除了 {$result['stats']['deleted_events']} 条事件记录)";
        }
        dts_set_feedback('success', $msg);
        header("Location: " . CP_BASE_URL . "dts_object");
    } else {
        dts_set_feedback('danger', $result['message']);
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? CP_BASE_URL . 'dts_object'));
    }
    exit();

} catch (Exception $e) {
    error_log("DTS Object Delete Error (v2.1.1): " . $e->getMessage());
    dts_set_feedback('danger', '删除失败：' . $e->getMessage());
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? CP_BASE_URL . 'dts_object'));
    exit();
}
