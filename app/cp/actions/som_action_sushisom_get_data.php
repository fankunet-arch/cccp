<?php
// /app/cp/actions/som_action_sushisom_get_data.php
// (重写，以支持动态 A1.png 页面)

declare(strict_types=1);

// $pdo 变量和 session 均已由 /app/cp/bootstrap.php 初始化
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '用户未登录']);
    exit();
}

$date = $_GET['date'] ?? null;
$uuid = $_GET['uuid'] ?? null; // 允许通过 UUID 获取 (用于编辑)

try {
    
    $daily_data = null;
    
    if ($uuid) {
        $stmt_daily = $pdo->prepare("SELECT * FROM sushisom_daily_operations WHERE ss_daily_uuid = ?");
        $stmt_daily->execute([$uuid]);
        $daily_data = $stmt_daily->fetch(PDO::FETCH_ASSOC);
    } 
    elseif ($date) {
        $stmt_daily = $pdo->prepare("SELECT * FROM sushisom_daily_operations WHERE ss_daily_date = ?");
        $stmt_daily->execute([$date]);
        $daily_data = $stmt_daily->fetch(PDO::FETCH_ASSOC);
    } 
    else {
        echo json_encode(['success' => false, 'message' => '未提供日期或UUID']);
        exit();
    }

    if (!$daily_data) {
        echo json_encode(['success' => true, 'data' => null, 'message' => '该日期无记录']);
        exit();
    }

    // (正确逻辑)
    // 提取所有相关的“专项财务”流水，作为数组
    $stmt_fin = $pdo->prepare("
        SELECT ss_fin_id, ss_fin_category, ss_fin_amount
        FROM sushisom_financial_transactions
        WHERE ss_fin_op_uuid = ?
        ORDER BY ss_fin_id ASC
    ");
    $stmt_fin->execute([$daily_data['ss_daily_uuid']]);
    $financial_transactions = $stmt_fin->fetchAll(PDO::FETCH_ASSOC);

    // [FIXED] 返回包含 'daily' (主数据) 和 'financial_transactions' (流水数据) 的正确结构
    echo json_encode(['success' => true, 'data' => [
        'daily' => $daily_data, 
        'financial_transactions' => $financial_transactions
    ]]);

} catch (PDOException $e) {
    error_log("Sushisom Get Data Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '数据库查询失败: ' . $e->getMessage()]);
}