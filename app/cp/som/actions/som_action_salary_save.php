<?php
// /app/cp/som/actions/som_action_salary_save.php
// (重写: 专门用于保存 sushisom_monthly_salaries 表)

declare(strict_types=1);

// $pdo 变量和 session 均已由 /app/cp/bootstrap.php 初始化
// require_login() 已在 /app/cp/index.php 中执行

$redirect_url = CP_BASE_URL . "som_salary_add";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['salary_feedback'] = ['type' => 'danger', 'message' => '无效的请求方式。'];
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

// 接收数据
$salary_month = $_POST['salary_month'] ?? null;
$sushi_salary = sanitize_currency($_POST['ss_ms_sushi_salary'] ?? 0);
$kitchen_salary = sanitize_currency($_POST['ss_ms_kitchen_salary'] ?? 0);
$waitstaff_salary = sanitize_currency($_POST['ss_ms_waitstaff_salary'] ?? 0);
$user_id = (int)$_SESSION['user_id'];

if (empty($salary_month)) {
    $_SESSION['salary_feedback'] = ['type' => 'danger', 'message' => '必须选择工资月份。'];
    header("Location: " . $redirect_url);
    exit();
}

// 记录当前录入的日期 (例如 2025-11-14)，工资月份 (salary_month) 是 (2025-10)
$record_date = date('Y-m-d');

// 设置重定向回当前选择的月份
$redirect_url .= '&month=' . $salary_month;


try {
    // 使用 INSERT ... ON DUPLICATE KEY UPDATE 来实现“存在则更新，不存在则插入”
    $sql = "
        INSERT INTO sushisom_monthly_salaries (
            salary_month, 
            ss_ms_sushi_salary, 
            ss_ms_kitchen_salary, 
            ss_ms_waitstaff_salary, 
            ss_ms_record_date,
            ss_ms_created_by
        ) VALUES (
            :salary_month, 
            :sushi_salary, 
            :kitchen_salary, 
            :waitstaff_salary,
            :record_date,
            :user_id
        )
        ON DUPLICATE KEY UPDATE
            ss_ms_sushi_salary = VALUES(ss_ms_sushi_salary),
            ss_ms_kitchen_salary = VALUES(ss_ms_kitchen_salary),
            ss_ms_waitstaff_salary = VALUES(ss_ms_waitstaff_salary),
            ss_ms_record_date = VALUES(ss_ms_record_date)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':salary_month' => $salary_month,
        ':sushi_salary' => $sushi_salary,
        ':kitchen_salary' => $kitchen_salary,
        ':waitstaff_salary' => $waitstaff_salary,
        ':record_date' => $record_date,
        ':user_id' => $user_id
    ]);

    $affected_rows = $stmt->rowCount();

    if ($affected_rows > 0) {
        // rowCount() 返回 1 代表 INSERT，返回 2 代表 UPDATE (在 MySQL 中)
        $message = ($affected_rows == 1) ? '新工资记录已保存！' : '工资记录已更新！';
        $_SESSION['salary_feedback'] = ['type' => 'success', 'message' => $message];
    } else {
        $_SESSION['salary_feedback'] = ['type' => 'info', 'message' => '数据未发生变化。'];
    }


} catch (PDOException $e) {
    error_log("Salary Save Error: " . $e->getMessage());
    $_SESSION['salary_feedback'] = ['type' => 'danger', 'message' => '数据库错误，保存失败: ' . $e->getMessage()];
}

header("Location: " . $redirect_url);
exit();
?>