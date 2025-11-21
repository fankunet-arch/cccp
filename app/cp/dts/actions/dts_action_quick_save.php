<?php
/**
 * DTS 极速录入保存 (Smart Save) - Unified Action
 * 逻辑：
 * 1. 检查主体：有ID用ID；无ID则按名字创建新主体。
 * 2. 检查对象：在主体下查找同名对象。有则用；无则创建新对象。
 * 3. 保存事件：如果是更新则 UPDATE，否则 INSERT。
 */

declare(strict_types=1);
require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Method Not Allowed');
}

try {
    $pdo->beginTransaction();

    // --- 1. 处理主体与对象 (Unified Object Save) ---
    $subject_id = !empty(dts_post('subject_id')) ? (int)dts_post('subject_id') : null;

    $subject_params = [
        'subject_name' => trim(dts_post('subject_name_input', '')),
        'subject_type' => dts_post('new_subject_type', 'person'),
        'cat_main' => trim(dts_post('cat_main', '')),
        'cat_sub' => trim(dts_post('cat_sub', ''))
    ];

    $object_name = trim(dts_post('object_name', ''));

    // 调用统一入口
    $result = dts_save_object($pdo, $subject_id, $object_name, $subject_params);

    $final_subject_id = $result['subject_id'];
    $final_object_id = $result['object_id'];

    // --- 2. 保存事件 (Unified Event Save) ---
    $event_id = !empty(dts_post('event_id')) ? (int)dts_post('event_id') : null;

    $event_params = [
        'event_id' => $event_id,
        'subject_id' => $final_subject_id,
        'event_type' => dts_post('event_type'),
        'event_date' => dts_post('event_date'),
        'mileage_now' => dts_post('mileage_now') ?: null,
        'expiry_date_new' => dts_post('expiry_date_new') ?: null,
        'note' => trim(dts_post('note', '')),
        'rule_id' => dts_post('rule_id') ?: null
    ];

    // 调用统一入口
    dts_save_event($pdo, $final_object_id, $event_params);

    // 设置反馈
    if ($event_id) {
        dts_set_feedback('success', "记录已更新！(主体: {$subject_params['subject_name']} - 对象: {$object_name})");
    } else {
        dts_set_feedback('success', "记录已保存！(主体: {$subject_params['subject_name']} - 对象: {$object_name})");
    }

    $pdo->commit();

    // --- 3. 跳转 ---
    $redirect_url = dts_post('redirect_url');
    if ($redirect_url) {
        header('Location: ' . $redirect_url);
    } else {
        header('Location: ' . CP_BASE_URL . 'dts_quick');
    }
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("DTS Quick Save Error: " . $e->getMessage());
    dts_set_feedback('danger', '保存失败：' . $e->getMessage());

    $redirect_url = dts_post('redirect_url');
    if ($redirect_url) {
         header('Location: ' . $redirect_url);
    } else {
         header('Location: ' . CP_BASE_URL . 'dts_quick');
    }
    exit();
}
