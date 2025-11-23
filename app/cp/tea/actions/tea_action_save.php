<?php
// /app/cp/tea/actions/tea_action_save.php
// <tea> Project Investment Save Action (REWRITE: FINAL Single-Transaction Model)
// [MODIFIED] 2025-11-16: 确保成功消息的类型（'type' => 'success'）被正确设置。

declare(strict_types=1);

// $pdo 变量和 session 均已由 /app/cp/bootstrap.php 初始化
$redirect_url = CP_BASE_URL . "tea_add";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['tea_feedback'] = ['type' => 'danger', 'message' => '无效的请求方式。'];
    header("Location: " . $redirect_url);
    exit();
}

/**
 * 货币清理函数 (用于数据库 DECIMAL 字段)
 */
function sanitize_money($value): float
{
    if ($value === null || $value === '') return 0.00;
    // 允许负号，逗号作为小数点 (兼容欧洲习惯)，并清理非数字/点/负号字符
    $value = str_replace(',', '.', (string)$value);
    $value = preg_replace('/[^0-9.-]/', '', $value);
    return is_numeric($value) ? (float)$value : 0.00;
}

// 接收数据
$fin_id = !empty($_POST['tea_fin_id']) ? (int)$_POST['tea_fin_id'] : null;
$date = $_POST['tea_date'] ?? date('Y-m-d');
$store = $_POST['tea_store'] ?? null;
$currency = $_POST['tea_currency'] ?? null;
// 核心：无论用户输入正负，都取其绝对值，由程序决定符号
$amount_raw = sanitize_money($_POST['tea_amount'] ?? 0);
$exchange_rate = empty($_POST['tea_exchange_rate']) ? 1.00 : sanitize_money($_POST['tea_exchange_rate']);
$type = $_POST['tea_type'] ?? null;
$is_equity = isset($_POST['tea_is_equity']) && $_POST['tea_is_equity'] === '1'; // Checkbox status
$notes = $_POST['tea_notes'] ?? null;
$user_id = (int)($_SESSION['user_id'] ?? 0);

// 定义需要取负号的支出类型 (所有非流入/回报的类型)
const OUTFLOW_TYPES = [
    'INVESTMENT_OUT', 'DIVIDEND_DEDUCTION', 'PROJECT_EXPENSE', 'RENT', 
    'DEPOSIT', 'SUPPLIES', 'SHIPPING', 'EQUIPMENT', 'TAX', 'GESTOR', 
    'DECORATION'
];


// 1. 业务逻辑校验
if (empty($date) || empty($currency) || empty($type) || abs($amount_raw) == 0.00) {
    $_SESSION['tea_feedback'] = ['type' => 'danger', 'message' => '交易日期、币种、类型和金额为必填项且金额不能为 0。'];
    // 如果是编辑模式，重定向回编辑页
    $redirect_url = $fin_id ? (CP_BASE_URL . "tea_add&id=" . $fin_id) : $redirect_url;
    header("Location: " . $redirect_url);
    exit();
}

// 2. 金额符号修正 (单笔交易的核心逻辑)
$amount = abs($amount_raw); // 首先取绝对值

// 支出类（房租、装修等）：金额存入负值
$is_outflow_type = in_array($type, OUTFLOW_TYPES, true);

if ($is_outflow_type) {
    $amount = $amount * -1;
}
// 注意：流入类（INVESTMENT_IN, DIVIDEND_CASH）保持正值。


// 3. 准备数据库操作
try {
    global $pdo;

    if ($fin_id) {
        // ----- 更新 (UPDATE) 逻辑 -----
        $sql = "
            UPDATE tea_financial_transactions SET
                tea_date = :date, tea_store = :store, tea_currency = :currency, 
                tea_amount = :amount, tea_exchange_rate = :exchange_rate, tea_type = :type, 
                tea_is_equity = :is_equity, tea_notes = :notes, tea_updated_at = NOW()
            WHERE tea_fin_id = :fin_id
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':fin_id', $fin_id, PDO::PARAM_INT);
        $message = "交易 ID {$fin_id} 已成功更新。";
        $redirect_url = CP_BASE_URL . "tea_add&id=" . $fin_id; 

    } else {
        // ----- 新增 (INSERT) 逻辑 -----
        $sql = "
            INSERT INTO tea_financial_transactions (
                tea_date, tea_store, tea_currency, tea_amount, 
                tea_exchange_rate, tea_type, tea_is_equity, tea_notes, 
                tea_created_by
            ) VALUES (
                :date, :store, :currency, :amount, 
                :exchange_rate, :type, :is_equity, :notes, 
                :user_id
            )";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $message = "新交易已成功记录：{$date} - {$type} " . number_format($amount, 2) . " {$currency}。";
        $redirect_url = CP_BASE_URL . "tea_add&date=" . $date; 
    }
    
    // 绑定公共参数
    $stmt->bindValue(':date', $date, PDO::PARAM_STR);
    $stmt->bindValue(':store', $store ?: null, PDO::PARAM_STR);
    $stmt->bindValue(':currency', $currency, PDO::PARAM_STR);
    $stmt->bindValue(':amount', $amount, PDO::PARAM_STR); // 使用修正后的金额 (单笔记录)
    $stmt->bindValue(':exchange_rate', $exchange_rate, PDO::PARAM_STR);
    $stmt->bindValue(':type', $type, PDO::PARAM_STR);
    $stmt->bindValue(':is_equity', $is_equity ? 1 : 0, PDO::PARAM_INT);
    $stmt->bindValue(':notes', $notes ?: null, PDO::PARAM_STR);

    $stmt->execute();
    
    // 关键修正：确保成功时使用 'success' 类型
    $_SESSION['tea_feedback'] = ['type' => 'success', 'message' => $message];

} catch (\PDOException $e) {
    error_log("Tea Save PDO Error: " . $e->getMessage());
    $_SESSION['tea_feedback'] = ['type' => 'danger', 'message' => '数据库错误，保存失败: ' . $e->getMessage()];
} catch (\Throwable $e) {
    error_log("Tea Save General Error: " . $e->getMessage());
    $_SESSION['tea_feedback'] = ['type' => 'danger', 'message' => '服务器错误，保存失败。'];
}

header("Location: " . $redirect_url);
exit();