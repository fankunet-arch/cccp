<?php
// /app/cp/actions/som_action_sushisom_save.php
// (修正: 匹配 som_view_add.php 的静态字段输入，并将其转换为交易流水)

declare(strict_types=1);

// $pdo 变量和 session 均已由 /app/cp/bootstrap.php 初始化
// require_login() 已在 /app/cp/index.php 中执行

$redirect_url = CP_BASE_URL . "som_add";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['sushisom_feedback'] = ['type' => 'danger', 'message' => '无效的请求方式。'];
    header("Location: " . $redirect_url);
    exit();
}

/**
 * 货币清理函数
 */
function sanitize_currency($value): float
{
    if ($value === null || $value === '') return 0.00;
    $value = str_replace(',', '.', (string)$value);
    $value = preg_replace('/[^0-9.-]/', '', $value);
    return is_numeric($value) ? (float)$value : 0.00;
}

/**
 * 生成 UUID v4
 */
function generate_uuid_v4(): string
{
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// 接收数据
$daily_op_id = !empty($_POST['ss_daily_id']) ? (int)$_POST['ss_daily_id'] : null;
$daily_op_uuid = !empty($_POST['ss_daily_uuid']) ? (string)$_POST['ss_daily_uuid'] : null;
$entry_date = $_POST['entry_date'] ?? date('Y-m-d');
$user_id = (int)$_SESSION['user_id'];

// [修正] 从静态 POST 字段中收集财务流水
// 这些字段直接对应于 som_view_add.php 中的 Section II 输入
$static_fields = [
    'total_dividend', 'dividend_cash', 'salary_bank_z', 'salary_bank_c', 
    'salary_cash_z', 'salary_cash_c', 'dividend_deduction', 'project_payment', 'investment'
];
$financial_data = [];
foreach ($static_fields as $field) {
    // 从 $_POST 中安全获取数据并清理
    $amount = sanitize_currency($_POST[$field] ?? 0);
    if ($amount != 0) {
        // 将每一个非零的静态字段作为一个单独的交易流水记录
        $financial_data[] = ['type' => $field, 'amount' => $amount];
    }
}
// [修正结束]

// 设置重定向上携带的日期
// (修复：确保重定向回 *当天*，以便用户查看结果)
$redirect_url .= '&date=' . $entry_date;
$_SESSION['sushisom_next_date'] = date('Y-m-d', strtotime($entry_date . ' +1 day'));


try {
    $pdo->beginTransaction();

    if ($daily_op_id && $daily_op_uuid) {
        // ----- 更新 (UPDATE) 逻辑 -----
        
        // 1. 更新 sushisom_daily_operations 表
        $stmt_update_daily = $pdo->prepare(
            "UPDATE `sushisom_daily_operations` SET
                `ss_daily_date` = ?, `ss_daily_morning_count` = ?, `ss_daily_afternoon_count` = ?,
                `ss_daily_cash_income` = ?, `ss_daily_cash_expense` = ?, `ss_daily_cash_balance` = ?,
                `ss_daily_bank_balance` = ?, `ss_daily_bank_expense` = ?, `ss_daily_bank_income` = ?,
                `ss_daily_match_name` = ?, `ss_daily_match_time` = ?
            WHERE `ss_daily_id` = ? AND `ss_daily_uuid` = ?"
        );
        $stmt_update_daily->execute([
            $entry_date,
            (int)($_POST['morning_count'] ?? 0),
            (int)($_POST['afternoon_count'] ?? 0),
            sanitize_currency($_POST['cash_income']),
            sanitize_currency($_POST['cash_expense']),
            sanitize_currency($_POST['cash_balance']),
            sanitize_currency($_POST['bank_balance']),
            sanitize_currency($_POST['bank_expense']),
            sanitize_currency($_POST['bank_income']),
            $_POST['match_name'] ?: null,
            $_POST['match_time'] ?: null,
            $daily_op_id,
            $daily_op_uuid
        ]);

        // 2. 清理旧的 sushisom_financial_transactions 记录
        $stmt_delete_fin = $pdo->prepare("DELETE FROM `sushisom_financial_transactions` WHERE `ss_fin_op_uuid` = ?");
        $stmt_delete_fin->execute([$daily_op_uuid]);

        // 3. 插入新的 sushisom_financial_transactions 记录 (循环数组)
        $stmt_insert_fin = $pdo->prepare(
            "INSERT INTO `sushisom_financial_transactions` 
                (`ss_fin_op_id`, `ss_fin_op_uuid`, `ss_fin_date`, `ss_fin_category`, `ss_fin_amount`, `ss_fin_created_by`) 
            VALUES (?, ?, ?, ?, ?, ?)"
        );
        
        $inserted_count = 0;
        foreach ($financial_data as $tx) {
            $stmt_insert_fin->execute([$daily_op_id, $daily_op_uuid, $entry_date, $tx['type'], $tx['amount'], $user_id]);
            $inserted_count++;
        }
        
        $feedback_message = '记录已于 ' . date('H:i:s') . ' 成功更新！ (更新了 ' . $inserted_count . ' 条财务记录)';

    } else {
        // ----- 新增 (INSERT) 逻辑 -----
        
        $new_uuid = generate_uuid_v4();
        
        // 1. 插入 sushisom_daily_operations 表
        $stmt_insert_daily = $pdo->prepare(
            "INSERT INTO `sushisom_daily_operations` (
                `ss_daily_uuid`, `ss_daily_date`, `ss_daily_morning_count`, `ss_daily_afternoon_count`,
                `ss_daily_cash_income`, `ss_daily_cash_expense`, `ss_daily_cash_balance`,
                `ss_daily_bank_balance`, `ss_daily_bank_expense`, `ss_daily_bank_income`,
                `ss_daily_match_name`, `ss_daily_match_time`, `ss_daily_created_by`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt_insert_daily->execute([
            $new_uuid,
            $entry_date,
            (int)($_POST['morning_count'] ?? 0),
            (int)($_POST['afternoon_count'] ?? 0),
            sanitize_currency($_POST['cash_income']),
            sanitize_currency($_POST['cash_expense']),
            sanitize_currency($_POST['cash_balance']),
            sanitize_currency($_POST['bank_balance']),
            sanitize_currency($_POST['bank_expense']),
            sanitize_currency($_POST['bank_income']),
            $_POST['match_name'] ?: null,
            $_POST['match_time'] ?: null,
            $user_id
        ]);

        $new_daily_op_id = $pdo->lastInsertId();

        // 2. 插入 sushisom_financial_transactions 记录 (循环数组)
        $stmt_insert_fin = $pdo->prepare(
            "INSERT INTO `sushisom_financial_transactions` 
                (`ss_fin_op_id`, `ss_fin_op_uuid`, `ss_fin_date`, `ss_fin_category`, `ss_fin_amount`, `ss_fin_created_by`) 
            VALUES (?, ?, ?, ?, ?, ?)"
        );
        
        $inserted_count = 0;
        foreach ($financial_data as $tx) {
            $stmt_insert_fin->execute([$new_daily_op_id, $new_uuid, $entry_date, $tx['type'], $tx['amount'], $user_id]);
            $inserted_count++;
        }
        $feedback_message = '新记录已于 ' . date('H:i:s') . ' 成功保存！ (新增了 ' . $inserted_count . ' 条财务记录。)';
    }

    $pdo->commit();
    $_SESSION['sushisom_feedback'] = ['type' => 'success', 'message' => $feedback_message];
    // (修复：重定向到 *下一天*，以便连续录入)
    header("Location: " . CP_BASE_URL . "som_add&date=" . date('Y-m-d', strtotime($entry_date . ' +1 day')));

} catch (PDOException $e) {
    $pdo->rollBack();
    // 检查是否为唯一键冲突 (重复日期)
    if ($e->errorInfo[1] == 1062) {
        $_SESSION['sushisom_feedback'] = ['type' => 'danger', 'message' => '错误：该日期 ('.$entry_date.') 的记录已存在，无法重复添加。请刷新页面以编辑该日期。'];
    } else {
        $_SESSION['sushisom_feedback'] = ['type' => 'danger', 'message' => '数据库错误，操作已回滚: ' . $e->getMessage()];
        error_log("Sushisom Save Error: " . $e->getMessage());
    }
    // (操作失败，重定向回 *当天*)
    header("Location: " . $redirect_url);
}

exit();
?>