<?php
/**
 * DTS 极速录入保存 (Smart Save) - Unified Action (v2.1)
 * 使用统一的 dts_save_object 和 dts_save_event 函数
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
        // 检查是否存在
        $stmt = $pdo->prepare("SELECT id FROM cp_dts_subject WHERE subject_name = ? LIMIT 1");
        $stmt->execute([$subject_name_input]);
        $exist_subj = $stmt->fetch();

        if ($exist_subj) {
            $subject_id = $exist_subj['id'];
        } else {
            // 创建新主体
            $new_type = dts_post('new_subject_type', 'person');
            $stmt_new_s = $pdo->prepare("INSERT INTO cp_dts_subject (subject_name, subject_type, subject_status, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())");
            $stmt_new_s->execute([$subject_name_input, $new_type]);
            $subject_id = $pdo->lastInsertId();
        }
    }

    // --- 2. 处理对象 (Object) - 使用统一函数 ---
    $object_name = trim(dts_post('object_name', ''));
    $cat_main = trim(dts_post('cat_main', ''));
    $cat_sub = trim(dts_post('cat_sub', ''));

    $object_id = dts_save_object($pdo, $subject_id, $object_name, [
        'cat_main' => $cat_main,
        'cat_sub' => $cat_sub
    ]);

    // --- 3. 保存事件 (Event) - 使用统一函数 ---
    // dts_save_event 会自动处理规则匹配和状态更新
    $event_params = [
        'event_id' => dts_post('event_id'),
        'subject_id' => $subject_id,
        'event_type' => dts_post('event_type'),
        'event_date' => dts_post('event_date'),
        'rule_id' => dts_post('rule_id'),
        'expiry_date_new' => dts_post('expiry_date_new'),
        'mileage_now' => dts_post('mileage_now'),
        'note' => trim(dts_post('note', ''))
    ];

    dts_save_event($pdo, $object_id, $event_params);

    $pdo->commit();

    $msg_type = dts_post('event_id') ? '更新' : '保存';
    dts_set_feedback('success', "记录已{$msg_type}！(主体: {$subject_name_input} - 对象: {$object_name})");

    // --- 4. 跳转 ---
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
