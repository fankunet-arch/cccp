<?php
/**
 * DTS 事件保存动作
 */

declare(strict_types=1);

require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

try {
    // 获取表单数据
    $event_id = dts_post('event_id');
    $object_id = (int)dts_post('object_id');
    $subject_id = (int)dts_post('subject_id');
    $rule_id = dts_post('rule_id') ?: null;
    $event_type = trim(dts_post('event_type', ''));
    $event_date = trim(dts_post('event_date', ''));
    $expiry_date_new = dts_post('expiry_date_new') ?: null;
    $mileage_now = dts_post('mileage_now') ?: null;
    $note = trim(dts_post('note', ''));

    // 验证
    if (empty($object_id) || empty($event_type) || empty($event_date)) {
        dts_set_feedback('danger', '请填写必填字段');
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }

    // 判断是新增还是更新
    if (!empty($event_id)) {
        // 更新
        $stmt = $pdo->prepare("
            UPDATE cp_dts_event SET
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
            $rule_id,
            $event_type,
            $event_date,
            $expiry_date_new,
            $mileage_now,
            $note,
            $event_id
        ]);

        dts_set_feedback('success', '事件更新成功');
    } else {
        // 新增
        $stmt = $pdo->prepare("
            INSERT INTO cp_dts_event
            (object_id, subject_id, rule_id, event_type, event_date, expiry_date_new, mileage_now, note, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW(), NOW())
        ");
        $stmt->execute([
            $object_id,
            $subject_id,
            $rule_id,
            $event_type,
            $event_date,
            $expiry_date_new,
            $mileage_now,
            $note
        ]);

        dts_set_feedback('success', '事件创建成功');
    }

    // 更新对象状态
    dts_update_object_state($pdo, $object_id);

    // 跳转回对象详情页
    header("Location: /cp/index.php?action=dts_object_detail&id=$object_id");
    exit();

} catch (Exception $e) {
    error_log("DTS Event Save Error: " . $e->getMessage());
    dts_set_feedback('danger', '保存失败：' . $e->getMessage());
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit();
}
