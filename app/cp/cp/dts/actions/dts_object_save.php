<?php
/**
 * DTS 对象保存动作
 */

declare(strict_types=1);

// 加载 DTS 函数库
require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

try {
    // 获取表单数据
    $object_id = dts_post('object_id');
    $subject_id = (int)dts_post('subject_id');
    $object_name = trim(dts_post('object_name', ''));
    $object_type_main = trim(dts_post('object_type_main', ''));
    $object_type_sub = trim(dts_post('object_type_sub', ''));
    $identifier = trim(dts_post('identifier', ''));
    $active_flag = (int)dts_post('active_flag', 1);
    $remark = trim(dts_post('remark', ''));

    // 处理小类为空的情况：空字符串转换为 NULL
    if ($object_type_sub === '') {
        $object_type_sub = null;
    }

    // 验证必填字段
    if (empty($subject_id)) {
        dts_set_feedback('danger', '请选择所属主体');
        header('Location: /cp/index.php?action=dts_object_form' . ($object_id ? "&id=$object_id" : ''));
        exit();
    }

    if (empty($object_name)) {
        dts_set_feedback('danger', '对象名称不能为空');
        header('Location: /cp/index.php?action=dts_object_form' . ($object_id ? "&id=$object_id" : ''));
        exit();
    }

    if (empty($object_type_main)) {
        dts_set_feedback('danger', '请选择大类');
        header('Location: /cp/index.php?action=dts_object_form' . ($object_id ? "&id=$object_id" : ''));
        exit();
    }

    // 注意：小类不是必填字段，可以为 NULL

    // 验证主体是否存在
    $stmt = $pdo->prepare("SELECT id FROM cp_dts_subject WHERE id = ? AND subject_status = 1");
    $stmt->execute([$subject_id]);
    if (!$stmt->fetch()) {
        dts_set_feedback('danger', '主体不存在或已停用');
        header('Location: /cp/index.php?action=dts_object_form' . ($object_id ? "&id=$object_id" : ''));
        exit();
    }

    // 判断是新增还是更新
    if (!empty($object_id)) {
        // 更新
        $stmt = $pdo->prepare("
            UPDATE cp_dts_object SET
                subject_id = ?,
                object_name = ?,
                object_type_main = ?,
                object_type_sub = ?,
                identifier = ?,
                active_flag = ?,
                remark = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $subject_id,
            $object_name,
            $object_type_main,
            $object_type_sub,
            $identifier,
            $active_flag,
            $remark,
            $object_id
        ]);

        dts_set_feedback('success', "对象「{$object_name}」更新成功");
    } else {
        // 新增
        $stmt = $pdo->prepare("
            INSERT INTO cp_dts_object
            (subject_id, object_name, object_type_main, object_type_sub, identifier, active_flag, remark, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $subject_id,
            $object_name,
            $object_type_main,
            $object_type_sub,
            $identifier,
            $active_flag,
            $remark
        ]);

        dts_set_feedback('success', "对象「{$object_name}」创建成功");
    }

    // 跳转回对象管理页面
    header('Location: /cp/index.php?action=dts_object');
    exit();

} catch (PDOException $e) {
    error_log("DTS Object Save Error: " . $e->getMessage());
    dts_set_feedback('danger', '保存失败：数据库错误');
    header('Location: /cp/index.php?action=dts_object_form' . (isset($object_id) && $object_id ? "&id=$object_id" : ''));
    exit();
} catch (Exception $e) {
    error_log("DTS Object Save Error: " . $e->getMessage());
    dts_set_feedback('danger', '保存失败：' . $e->getMessage());
    header('Location: /cp/index.php?action=dts_object_form' . (isset($object_id) && $object_id ? "&id=$object_id" : ''));
    exit();
}
