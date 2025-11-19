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

    // --- 2. 核心验证 ---
    if (empty($object_id) || empty($subject_id) || empty($event_type) || empty($event_date)) {
        dts_set_feedback('danger', '保存失败：缺少核心信息（对象、主体、事件类型或日期）。');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? CP_BASE_URL . 'dts_main'));
        exit();
    }

    // --- 3. 获取可选高级属性 ---
    $rule_id = dts_post('rule_id') ?: null;
    $expiry_date_new = dts_post('expiry_date_new') ?: null;
    $mileage_now = dts_post('mileage_now') ?: null;
    $note = trim(dts_post('note', ''));

    $is_edit_mode = !empty($event_id);

    if ($is_edit_mode) {
        // --- 更新 (UPDATE) ---
        $stmt = $pdo->prepare("
            UPDATE cp_dts_event SET
                object_id = ?,
                subject_id = ?,
                rule_id = ?,
                event_type = ?,
                event_date = ?,
                expiry_date_new = ?,
                mileage_now = ?,
                note = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $object_id, $subject_id, $rule_id, $event_type, $event_date,
            $expiry_date_new, $mileage_now, $note,
            $event_id
        ]);
        dts_set_feedback('success', '事件已成功更新。');
    } else {
        // --- 新增 (INSERT) ---
        $stmt = $pdo->prepare("
            INSERT INTO cp_dts_event
            (object_id, subject_id, rule_id, event_type, event_date, expiry_date_new, mileage_now, note, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW(), NOW())
        ");
        $stmt->execute([
            $object_id, $subject_id, $rule_id, $event_type, $event_date,
            $expiry_date_new, $mileage_now, $note
        ]);
        dts_set_feedback('success', '新事件已成功创建。');
    }

    // --- 4. 更新对象状态 ---
    dts_update_object_state($pdo, $object_id);

    // --- 5. 跳转 ---
    header("Location: " . CP_BASE_URL . "dts_object_detail&id=$object_id");
    exit();

} catch (Exception $e) {
    error_log("DTS Event Save Error (Refactored): " . $e->getMessage());
    dts_set_feedback('danger', '数据库操作失败：' . $e->getMessage());
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? CP_BASE_URL . 'dts_main'));
    exit();
}
