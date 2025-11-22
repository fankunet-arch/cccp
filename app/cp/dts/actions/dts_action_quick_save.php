<?php
/**
 * DTS 极速录入保存 (Smart Save) - v2.1.2 Refactored
 * [v2.1.2] 移除 mode=append 逻辑，专注于两个职责：
 * 1. 新建主体 + 对象 + 首次事件
 * 2. 编辑已有事件（通过 event_id）
 *
 * 核心逻辑：
 * 1. 检查主体：有ID用ID；无ID则按名字创建新主体
 * 2. 调用 dts_save_object() 统一对象保存入口
 * 3. 调用 dts_save_event() 统一事件保存入口（内含默认规则匹配+状态更新）
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

    // 使用统一对象保存入口
    $object_id = dts_save_object($pdo, (int)$subject_id, $object_name, [
        'cat_main' => $cat_main,
        'cat_sub' => $cat_sub ?: null,
        'identifier' => null,
        'remark' => null
    ]);

    if (!$object_id) {
        throw new Exception('对象保存失败');
    }

    // --- 3. [v2.1] 使用统一事件保存入口（含默认规则匹配） ---
    $event_params = [
        'subject_id' => (int)$subject_id,
        'event_type' => dts_post('event_type'),
        'event_date' => dts_post('event_date'),
        'rule_id' => dts_post('rule_id') ?: null,
        'expiry_date_new' => dts_post('expiry_date_new') ?: null,
        'mileage_now' => dts_post('mileage_now') ?: null,
        'note' => trim(dts_post('note', '')),
        'event_id' => dts_post('event_id') ?: null, // 编辑模式
        // [v2.1] 提供分类信息用于默认规则匹配
        'cat_main' => $cat_main,
        'cat_sub' => $cat_sub ?: null
    ];

    $saved_event_id = dts_save_event($pdo, (int)$object_id, $event_params);

    if (!$saved_event_id) {
        throw new Exception('事件保存失败');
    }

    // 设置反馈消息
    if (!empty($event_params['event_id'])) {
        dts_set_feedback('success', "记录已更新！(主体: {$subject_name_input} - 对象: {$object_name})");
    } else {
        dts_set_feedback('success', "记录已保存！(主体: {$subject_name_input} - 对象: {$object_name})");
    }

    $pdo->commit();

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
    error_log("DTS Quick Save Error (v2.1.2): " . $e->getMessage());
    dts_set_feedback('danger', '保存失败：' . $e->getMessage());

    $redirect_url = dts_post('redirect_url');
    if ($redirect_url) {
         header('Location: ' . $redirect_url);
    } else {
         header('Location: ' . CP_BASE_URL . 'dts_quick');
    }
    exit();
}
