<?php
/**
 * DTS 主体获取数据动作（用于编辑）
 */

declare(strict_types=1);

// 设置 JSON 响应头
header('Content-Type: application/json; charset=utf-8');

global $pdo;

try {
    // 获取主体 ID
    $subject_id = $_GET['id'] ?? null;

    if (empty($subject_id)) {
        echo json_encode([
            'success' => false,
            'message' => '缺少主体 ID'
        ]);
        exit();
    }

    // 查询主体数据
    $stmt = $pdo->prepare("SELECT * FROM cp_dts_subject WHERE id = ?");
    $stmt->execute([$subject_id]);
    $subject = $stmt->fetch();

    if (!$subject) {
        echo json_encode([
            'success' => false,
            'message' => '主体不存在'
        ]);
        exit();
    }

    // 返回成功响应
    echo json_encode([
        'success' => true,
        'data' => $subject
    ]);

} catch (PDOException $e) {
    error_log("DTS Subject Get Data Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '数据库错误'
    ]);
} catch (Exception $e) {
    error_log("DTS Subject Get Data Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
