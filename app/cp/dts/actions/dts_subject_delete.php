<?php
/**
 * DTS 主体删除 - v2.1.1
 * [v2.1.1] 软删除主体(可选级联删除对象和事件)
 */
declare(strict_types=1);
require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

try {
    $subject_id = (int)dts_post('subject_id');
    $mode = dts_post('delete_mode', 'subject_only'); // 'subject_only' 或 'cascade'

    if (empty($subject_id)) {
        dts_set_feedback('danger', '缺少主体ID');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? CP_BASE_URL . 'dts_subject'));
        exit();
    }

    // 调用软删除函数
    $result = dts_soft_delete_subject($pdo, $subject_id, $mode);

    if ($result['success']) {
        $msg = $result['message'];
        if ($mode === 'cascade') {
            $obj_count = $result['stats']['deleted_objects'];
            $evt_count = $result['stats']['deleted_events'];
            if ($obj_count > 0 || $evt_count > 0) {
                $msg .= " (同时删除了 {$obj_count} 个对象, {$evt_count} 条事件记录)";
            }
        }
        dts_set_feedback('success', $msg);
        header("Location: " . CP_BASE_URL . "dts_subject");
    } else {
        dts_set_feedback('danger', $result['message']);
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? CP_BASE_URL . 'dts_subject'));
    }
    exit();

} catch (Exception $e) {
    error_log("DTS Subject Delete Error (v2.1.1): " . $e->getMessage());
    dts_set_feedback('danger', '删除失败：' . $e->getMessage());
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? CP_BASE_URL . 'dts_subject'));
    exit();
}
