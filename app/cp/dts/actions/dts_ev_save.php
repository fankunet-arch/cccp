<?php
/**
 * DTS 事件保存动作 (Event Save Action) - 重构版
 * 逻辑与新的 dts_ev_editor.php 对应。
 */
declare(strict_types=1);
require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

try {
    // --- 1. 获取核心数据 ---
    $event_id = dts_post('event_id');
    $object_id = (int)dts_post('object_id');
    $subject_id = (int)dts_post('subject_id');

    $event_type = trim(dts_post('event_type', ''));
    $event_date = trim(dts_post('event_date', ''));

    // (Core validation is now handled inside dts_save_event, but we check here for feedback redirection)
    if (empty($object_id) || empty($subject_id) || empty($event_type) || empty($event_date)) {
        dts_set_feedback('danger', '保存失败：缺少核心信息（对象、主体、事件类型或日期）。');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? CP_BASE_URL . 'dts_main'));
        exit();
    }

    // --- 2. 构建参数 ---
    $params = [
        'event_id' => $event_id,
        'subject_id' => $subject_id,
        'event_type' => $event_type,
        'event_date' => $event_date,
        'rule_id' => dts_post('rule_id') ?: null,
        'mileage_now' => dts_post('mileage_now') ?: null,
        'expiry_date_new' => dts_post('expiry_date_new') ?: null,
        'note' => trim(dts_post('note', ''))
    ];

    // --- 3. 调用统一入口 ---
    dts_save_event($pdo, $object_id, $params);

    // --- 4. 设置反馈 ---
    if (!empty($event_id)) {
        dts_set_feedback('success', '事件已成功更新。');
    } else {
        dts_set_feedback('success', '新事件已成功创建。');
    }

    // --- 5. 跳转 ---
    header("Location: " . CP_BASE_URL . "dts_object_detail&id=$object_id");
    exit();

} catch (Exception $e) {
    error_log("DTS Event Save Error (Refactored): " . $e->getMessage());
    dts_set_feedback('danger', '数据库操作失败：' . $e->getMessage());
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? CP_BASE_URL . 'dts_main'));
    exit();
}
