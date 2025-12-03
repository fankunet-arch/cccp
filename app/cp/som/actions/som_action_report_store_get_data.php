<?php
// /app/cp/som/actions/som_action_report_store_get_data.php
// ABCABC-CP | Store Report Data
// [MODIFIED] 2025-11-14 (v5.1): Now fetches AND returns monthly_salaries alongside daily_rows.

declare(strict_types=1);

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit();
}

// [FIX] Ensure bootstrap is loaded, making $pdo and $config available
require_once dirname(__DIR__, 2) . '/bootstrap.php'; 

try {
    $start_date = $_GET['start_date'] ?? null;
    $end_date   = $_GET['end_date'] ?? null;

    // Default to last 12 months range [first day of month N-11 .. last day of current month]
    $tz = new DateTimeZone($config['timezone'] ?? 'Europe/Madrid');
    $today = new DateTimeImmutable('today', $tz);
    if (!$start_date || !$end_date) {
        $first_of_this_month = $today->modify('first day of this month');
        $first_of_12m_ago = $first_of_this_month->modify('-11 months');
        $last_of_this_month = $today->modify('last day of this month');
        $start_date = $first_of_12m_ago->format('Y-m-d');
        $end_date   = $last_of_this_month->format('Y-m-d');
    }

    // 1. Fetch all daily operations within range
    $sql_daily = "
        SELECT 
            ss_daily_date,
            ss_daily_morning_count,
            ss_daily_afternoon_count,
            ss_daily_cash_income,
            ss_daily_bank_income,
            ss_daily_cash_expense,
            ss_daily_bank_expense
        FROM sushisom_daily_operations
        WHERE ss_daily_date BETWEEN :start_date AND :end_date
        ORDER BY ss_daily_date ASC
    ";
    $stmt = $pdo->prepare($sql_daily);
    $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
    $daily_rows_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch daily total_dividend from financial_transactions within range
    $sql_div = "
        SELECT ss_fin_date, SUM(ss_fin_amount) AS amt
        FROM sushisom_financial_transactions
        WHERE ss_fin_date BETWEEN :start_date AND :end_date
          AND ss_fin_category = 'total_dividend'
        GROUP BY ss_fin_date
    ";
    $stmt2 = $pdo->prepare($sql_div);
    $stmt2->execute([':start_date' => $start_date, ':end_date' => $end_date]);

    $dividend_by_date = [];
    foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dividend_by_date[$row['ss_fin_date']] = (float)$row['amt'];
    }

    // 3. [NEW] Fetch monthly salaries within the *month range*
    $start_month = substr($start_date, 0, 7); // YYYY-MM
    $end_month = substr($end_date, 0, 7);   // YYYY-MM
    
    $sql_salary = "
        SELECT 
            salary_month, 
            ss_ms_sushi_salary, 
            ss_ms_kitchen_salary, 
            ss_ms_waitstaff_salary
        FROM sushisom_monthly_salaries
        WHERE salary_month >= :start_month AND salary_month <= :end_month
    ";
    $stmt_salary = $pdo->prepare($sql_salary);
    $stmt_salary->execute([':start_month' => $start_month, ':end_month' => $end_month]);
    $salary_rows = $stmt_salary->fetchAll(PDO::FETCH_ASSOC);


    // 4. Process raw daily rows into the final daily_rows array
    $processed_daily_rows = [];
    foreach ($daily_rows_raw as $r) {
        $dateStr = $r['ss_daily_date'];
        $mkey = substr($dateStr, 0, 7); // YYYY-MM

        $people_d = (int)$r['ss_daily_morning_count'] + (int)$r['ss_daily_afternoon_count'];
        $cash_in  = (float)$r['ss_daily_cash_income'];
        $bank_in  = (float)$r['ss_daily_bank_income'];
        $cash_ex  = (float)$r['ss_daily_cash_expense'];
        $bank_ex  = (float)$r['ss_daily_bank_expense'];

        $total_income = $cash_in + $bank_in;
        $total_expense = $cash_ex + $bank_ex; // This is *operational* expense
        $net = $total_income - $total_expense; // This is *operational* net

        // [FIX] Simplified avg_spend calculation: total_income / people
        // This matches the definition: "月营业额=当月现金+银行收入"
        $avg_spend_num = $total_income;
        $avg_spend_den = $people_d;

        $processed_daily_rows[] = [
            'date' => $dateStr,
            'month' => $mkey,
            'people' => $people_d,
            'cash_income' => $cash_in,
            'bank_income' => $bank_in,
            'total_income' => $total_income,
            'cash_expense' => $cash_ex,
            'bank_expense' => $bank_ex,
            'total_expense' => $total_expense, // Operational expense
            'net' => $net, // Operational net
            'monthly_dividend_total' => $dividend_by_date[$dateStr] ?? 0.0,

            // Components for avg spend calculation
            'avg_spend_numerator' => $avg_spend_num,
            'avg_spend_denominator' => $avg_spend_den,
        ];
    }

    $result = [
        'range' => ['start_date' => $start_date, 'end_date' => $end_date],
        'daily_rows' => $processed_daily_rows,
        'monthly_salaries' => $salary_rows // [NEW] Add salaries to the response
    ];

    echo json_encode(['success' => true, 'data' => $result]);

} catch (Throwable $e) {
    // [FIX] Restore error handling
    error_log("som_report_store_get_data error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '服务器错误: ' . $e->getMessage()]);
}

?>