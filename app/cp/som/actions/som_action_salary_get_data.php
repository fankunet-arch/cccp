<?php
// /app/cp/som/actions/som_action_salary_get_data.php
// (新文件: 用于 AJAX 获取指定月份的工资数据)

declare(strict_types=1);

// $pdo 变量和 session 均已由 /app/cp/bootstrap.php 初始化
header('Content-Type: application/json');

if (!function_exists('require_login')) {
     // 确保 bootstrap 被正确加载
    require_once dirname(__DIR__, 2) . '/bootstrap.php'; 
    require_once APP_PATH_CP . '/src/auth.php';
}
require_login();

$month = $_GET['month'] ?? null; // 格式: YYYY-MM

if (empty($month)) {
    echo json_encode(['success' => false, 'message' => '未提供月份']);
    exit();
}

try {
    
    $stmt = $pdo->prepare("
        SELECT ss_ms_id, salary_month, ss_ms_sushi_salary, ss_ms_kitchen_salary, ss_ms_waitstaff_salary 
        FROM sushisom_monthly_salaries 
        WHERE salary_month = ?
    ");
    $stmt->execute([$month]);
    $salary_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$salary_data) {
        echo json_encode(['success' => true, 'data' => null, 'message' => '该月份无记录']);
        exit();
    }

    echo json_encode(['success' => true, 'data' => $salary_data]);

} catch (PDOException $e) {
    error_log("Sushisom Get Salary Data Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '数据库查询失败: ' . $e->getMessage()]);
}
?>