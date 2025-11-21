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

    // --- 1. 处理主体 (Subject) ---
    $subject_id = dts_post('subject_id');
    $subject_name_input = trim(dts_post('subject_name_input', ''));

    if (empty($subject_id)) {
        // 前端没ID，说明是新名字。双重检查数据库是否真有同名（防止前端没加载全）
        $stmt = $pdo->prepare("SELECT id FROM cp_dts_subject WHERE subject_name = ? LIMIT 1");
        $stmt->execute([$subject_name_input]);
        $exist_subj = $stmt->fetch();

        if ($exist_subj) {
            $subject_id = $exist_subj['id'];
        } else {
            // 真没有，创建新主体
            $new_type = dts_post('new_subject_type', 'person');
            $stmt_new_s = $pdo->prepare("INSERT INTO cp_dts_subject (subject_name, subject_type, subject_status, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())");
            $stmt_new_s->execute([$subject_name_input, $new_type]);
            $subject_id = $pdo->lastInsertId();
        }
    }

    // --- 2. 处理对象 (Object) ---
    $object_name = trim(dts_post('object_name', ''));
    $cat_main = trim(dts_post('cat_main', ''));
    $cat_sub = trim(dts_post('cat_sub', ''));

    // 在该主体下查找是否已有该对象
    $stmt_obj = $pdo->prepare("SELECT id FROM cp_dts_object WHERE subject_id = ? AND object_name = ? LIMIT 1");
    $stmt_obj->execute([$subject_id, $object_name]);
    $exist_obj = $stmt_obj->fetch();

    $object_id = null;
    if ($exist_obj) {
        $object_id = $exist_obj['id'];
        // 可选：如果老对象没有分类，顺便更新一下分类？这里暂不覆盖，以老数据为准
    } else {
        // 创建新对象
        $stmt_new_o = $pdo->prepare("INSERT INTO cp_dts_object (subject_id, object_name, object_type_main, object_type_sub, active_flag, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())");
        // cat_sub 为空则存 NULL
        $stmt_new_o->execute([$subject_id, $object_name, $cat_main, $cat_sub ?: null]);
        $object_id = $pdo->lastInsertId();
    }

    // --- 3. 保存事件 (Event) ---
    $event_id = dts_post('event_id');
    $event_date = dts_post('event_date');
    $event_type = dts_post('event_type');
    $mileage = dts_post('mileage_now') ?: null;
    $expiry = dts_post('expiry_date_new') ?: null;
    $note = trim(dts_post('note', ''));
    $rule_id = dts_post('rule_id') ?: null;

    if ($event_id) {
        // UPDATE
        $stmt_ev = $pdo->prepare("UPDATE cp_dts_event SET object_id=?, subject_id=?, rule_id=?, event_type=?, event_date=?, mileage_now=?, expiry_date_new=?, note=?, updated_at=NOW() WHERE id=?");
        $stmt_ev->execute([$object_id, $subject_id, $rule_id, $event_type, $event_date, $mileage, $expiry, $note, $event_id]);
        dts_set_feedback('success', "记录已更新！(主体: {$subject_name_input} - 对象: {$object_name})");
    } else {
        // INSERT
        $stmt_ev = $pdo->prepare("INSERT INTO cp_dts_event (object_id, subject_id, rule_id, event_type, event_date, mileage_now, expiry_date_new, note, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW(), NOW())");
        $stmt_ev->execute([$object_id, $subject_id, $rule_id, $event_type, $event_date, $mileage, $expiry, $note]);
        dts_set_feedback('success', "记录已保存！(主体: {$subject_name_input} - 对象: {$object_name})");
    }

    // --- 4. 触发状态计算 ---
    dts_update_object_state($pdo, (int)$object_id);

    $pdo->commit();

    // --- 5. 跳转 ---
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
