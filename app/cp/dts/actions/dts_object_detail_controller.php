<?php
/**
 * DTS 对象详情页 Controller
 * 处理参数验证和重定向逻辑（在视图加载前执行）
 */

declare(strict_types=1);

// 加载 DTS 函数库
require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

// 获取对象 ID
$object_id = dts_get('id');

if (!$object_id) {
    dts_set_feedback('danger', '缺少对象 ID');
    header('Location: ' . CP_BASE_URL . 'dts_object');
    exit();
}

// 获取对象信息
$stmt = $pdo->prepare("
    SELECT o.*, s.subject_name, s.subject_type
    FROM cp_dts_object o
    LEFT JOIN cp_dts_subject s ON o.subject_id = s.id
    WHERE o.id = ?
");
$stmt->execute([$object_id]);
$object = $stmt->fetch();

if (!$object) {
    dts_set_feedback('danger', '对象不存在');
    header('Location: ' . CP_BASE_URL . 'dts_object');
    exit();
}

// [修复] 获取对象的当前状态 (强制转换为 int)
$state = dts_get_object_state($pdo, (int)$object_id);

// 获取对象的事件列表（按日期倒序）
// [v2.1.1] 增强：只显示未删除的事件
$stmt = $pdo->prepare("
    SELECT e.*, r.rule_name
    FROM cp_dts_event e
    LEFT JOIN cp_dts_rule r ON e.rule_id = r.id
    WHERE e.object_id = ? AND e.is_deleted = 0
    ORDER BY e.event_date DESC, e.id DESC
");
$stmt->execute([$object_id]);
$events = $stmt->fetchAll();

// 数据准备完成，现在加载视图
// 因为这是action文件，需要手动加载布局
require_once APP_PATH_CP . '/views/layouts/header.php';
require_once APP_PATH_CP . '/dts/views/_dts_object_detail_view.php';
require_once APP_PATH_CP . '/views/layouts/footer.php';
