<?php
/**
 * DTS 事件保存动作 (Event Save Action) - v2.1 Refactored
 * [v2.1] 使用统一事件保存入口 + 默认规则自动匹配
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

    // --- 3. [v2.1] 获取对象的分类信息（用于默认规则匹配） ---
    $stmt = $pdo->prepare("SELECT object_type_main, object_type_sub FROM cp_dts_object WHERE id = ?");
    $stmt->execute([$object_id]);
    $object_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$object_info) {
        throw new Exception('对象不存在');
    }

    // --- 4. [v2.1] 使用统一事件保存入口（含默认规则匹配+状态更新） ---
    $event_params = [
        'subject_id' => $subject_id,
        'event_type' => $event_type,
        'event_date' => $event_date,
        'rule_id' => dts_post('rule_id') ?: null,
        'expiry_date_new' => dts_post('expiry_date_new') ?: null,
        'mileage_now' => dts_post('mileage_now') ?: null,
        'note' => trim(dts_post('note', '')),
        'event_id' => $event_id ?: null, // 编辑模式
        // [v2.1] 提供分类信息用于默认规则匹配
        'cat_main' => $object_info['object_type_main'],
        'cat_sub' => $object_info['object_type_sub'] ?: null
    ];

    $saved_event_id = dts_save_event($pdo, $object_id, $event_params);

    if (!$saved_event_id) {
        throw new Exception('事件保存失败');
    }

    // 设置反馈消息
    if (!empty($event_id)) {
        dts_set_feedback('success', '事件已成功更新。');
    } else {
        dts_set_feedback('success', '新事件已成功创建。');
    }

    // --- 5. 跳转 ---
    header("Location: " . CP_BASE_URL . "dts_object_detail&id=$object_id");
    exit();

} catch (Exception $e) {
    error_log("DTS Event Save Error (v2.1): " . $e->getMessage());
    dts_set_feedback('danger', '数据库操作失败：' . $e->getMessage());
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? CP_BASE_URL . 'dts_main'));
    exit();
}
