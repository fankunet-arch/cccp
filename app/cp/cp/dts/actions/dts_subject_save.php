<?php
/**
 * DTS 主体保存动作
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
    $subject_id = dts_post('subject_id');
    $subject_name = trim(dts_post('subject_name', ''));
    $subject_type = dts_post('subject_type', 'person');
    $subject_status = (int)dts_post('subject_status', 1);
    $remark = trim(dts_post('remark', ''));

    // 验证必填字段
    if (empty($subject_name)) {
        dts_set_feedback('danger', '主体名称不能为空');
        header('Location: /cp/index.php?action=dts_subject');
        exit();
    }

    if (!in_array($subject_type, ['person', 'company', 'other'])) {
        dts_set_feedback('danger', '无效的主体类型');
        header('Location: /cp/index.php?action=dts_subject');
        exit();
    }

    // 判断是新增还是更新
    if (!empty($subject_id)) {
        // 更新
        $stmt = $pdo->prepare("
            UPDATE cp_dts_subject SET
                subject_name = ?,
                subject_type = ?,
                subject_status = ?,
                remark = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $subject_name,
            $subject_type,
            $subject_status,
            $remark,
            $subject_id
        ]);

        dts_set_feedback('success', "主体「{$subject_name}」更新成功");
    } else {
        // 新增
        $stmt = $pdo->prepare("
            INSERT INTO cp_dts_subject
            (subject_name, subject_type, subject_status, remark, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $subject_name,
            $subject_type,
            $subject_status,
            $remark
        ]);

        dts_set_feedback('success', "主体「{$subject_name}」创建成功");
    }

    // 跳转回主体管理页面
    header('Location: /cp/index.php?action=dts_subject');
    exit();

} catch (PDOException $e) {
    error_log("DTS Subject Save Error: " . $e->getMessage());
    dts_set_feedback('danger', '保存失败：数据库错误');
    header('Location: /cp/index.php?action=dts_subject');
    exit();
} catch (Exception $e) {
    error_log("DTS Subject Save Error: " . $e->getMessage());
    dts_set_feedback('danger', '保存失败：' . $e->getMessage());
    header('Location: /cp/index.php?action=dts_subject');
    exit();
}
