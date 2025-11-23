<?php
// /app/cp/tea/actions/tea_action_report_investor_get_data.php
// <tea> Project Investor Report Data (pool-level)
// [FINAL REVISION] 遵循用户指令：不进行任何数值干预或排除，KPI即为原始净额累加。
// [FIXED] 2025-11-14: “最近6个月投资/回报记录”现在使用主筛选器的日期范围。
// [MODIFIED] 2025-11-14 (v4): 移除 investor_return_cash 字段，修改总回报口径，计算月数。
// [MODIFIED] 2025-11-15: ADDED SUPPLIES, SHIPPING to outflow/KPI categories.

declare(strict_types=1);
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// [FIX] 引入 bootstrap
require_once dirname(__DIR__, 2) . '/bootstrap.php'; 

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit();
}

// 定义精度
const SCALE = 4; // 使用更高的精度进行内部计算
const DISPLAY_SCALE = 2; // 最终显示使用 2 位小数

// 定义所有需要被视为资金流出（负值）的交易类型
const OUTFLOW_TYPES = [
    'INVESTMENT_OUT', 'DIVIDEND_DEDUCTION', 'PROJECT_EXPENSE', 'RENT', 
    'DEPOSIT', 'SUPPLIES', 'SHIPPING', 'EQUIPMENT', 'TAX', 'GESTOR', 
    'DECORATION'
];

// 定义所有参与 KPI 计算的类型
const KPI_CATEGORIES = [
    'INVESTMENT_IN', 'INVESTMENT_OUT', 'DIVIDEND_CASH', 'DIVIDEND_DEDUCTION',
    'PROJECT_EXPENSE', 'RENT', 'DEPOSIT', 'SUPPLIES', 'SHIPPING', 
    'EQUIPMENT', 'TAX', 'GESTOR', 'DECORATION'
];

try {
    $user_filter_start_date = $_GET['start_date'] ?? null;
    $user_filter_end_date   = $_GET['end_date'] ?? null;
    
    // Default to a 24-month filter range if user hasn't specified
    if (!$user_filter_start_date || !$user_filter_end_date) {
        global $config;
        $tz = new DateTimeZone($config['timezone'] ?? 'Europe/Madrid');
        $today = new DateTimeImmutable('today', $tz);
        $user_filter_start_date = $today->modify('-24 months')->format('Y-m-d');
        $user_filter_end_date   = $today->format('Y-m-d');
    }
    
    // -----------------------------------------------------------
    // --- 1. Fetch ALL relevant data for KPI and Breakdown Calc ---
    // -----------------------------------------------------------
    
    // [MODIFIED] 从查询最小日期中移除 investor_return_cash
    $kpi_categories_str = implode("', '", KPI_CATEGORIES);
    $stmt_min_date = $pdo->prepare("SELECT MIN(tea_date) FROM tea_financial_transactions WHERE tea_type IN ('{$kpi_categories_str}')");
    $stmt_min_date->execute();
    $min_date_for_kpi = $stmt_min_date->fetchColumn() ?: $user_filter_start_date; // Use min date or fallback to filter start

    // Fetch ALL records from the earliest known date up to the filter end date
    $sql_all = "
        SELECT tea_fin_id, tea_date, tea_store, tea_currency, tea_amount, tea_exchange_rate, tea_type, tea_is_equity, tea_notes
        FROM tea_financial_transactions
        WHERE tea_date BETWEEN :start AND :end
        ORDER BY tea_date ASC, tea_fin_id ASC
    ";
    $stmt_all = $pdo->prepare($sql_all);
    $stmt_all->execute([':start' => $min_date_for_kpi, ':end' => $user_filter_end_date]);
    $all_rows_raw = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

    // -----------------------------------------------------------
    // --- 2. Process Data: Currency Conversion and Sign Correction ---
    // -----------------------------------------------------------
    $all_rows_processed = [];
    foreach ($all_rows_raw as $r) {
        $cat = $r['tea_type'];
        $amount_raw = (string)$r['tea_amount'];
        $rate = (string)$r['tea_exchange_rate'];
        $currency = $r['tea_currency'];
        
        // 1. Convert to Base Currency (EUR assumed)
        // FIX: 外币需要除以汇率进行转换。如果汇率为0或空，则视为错误或使用原金额。
        $rate_float = (float)$rate;
        if ($currency !== 'EUR' && $rate_float > 0.0) {
            $base_amount_str = bcdiv($amount_raw, $rate, SCALE);
        } else {
            $base_amount_str = $amount_raw;
        }
        
        // 2. Apply Sign Correction (ONLY for Expense/Outflow Types)
        // Note: tea_amount in DB is already signed by tea_action_save.php.
        // We ensure the sign of the base_amount matches the expected direction.
        $base_amt_signed_str = $base_amount_str;
        $is_db_positive = bccomp($base_amount_str, '0.00', SCALE) > 0;
        
        // 如果是支出类，但数据库里存的是正值，则取反
        if (in_array($cat, OUTFLOW_TYPES) && $is_db_positive) {
            $base_amt_signed_str = bcmul($base_amount_str, '-1', SCALE);
        }
        // 如果是收入类，但数据库里存的是负值，则取反 (例如：INVESTMENT_IN 必须是正的)
        elseif (!in_array($cat, OUTFLOW_TYPES) && !$is_db_positive) {
            $base_amt_signed_str = bcmul($base_amount_str, '-1', SCALE);
        }
        
        // 存储所有原始数据和 base/signed 结果
        $r['tea_amount_base_str'] = $base_amt_signed_str;
        $all_rows_processed[] = $r;
    }


    // -----------------------------------------------------------
    // --- 3. Calculate KPI Values (Full History) and Breakdown (Filtered) ---
    // -----------------------------------------------------------
    
    // Accumulators for Full History KPI (Base Currency)
    $total_principal_kpi_str = '0.00';     
    $total_returns_kpi_str = '0.00';        
    $total_expense_full_str = '0.00';     
    $first_principal_date = null;
    
    // Accumulators for Filtered Breakdown (Base Currency)
    $breakdown = [];
    foreach (KPI_CATEGORIES as $cat) {
        $breakdown[$cat] = '0.00';
    }
    $total_net_return_breakdown_str = '0.00'; 
    $total_expense_filtered_str = '0.00'; 
    $first_kpi_start_date = null; 
    
    $principal_categories = ['INVESTMENT_IN', 'DIVIDEND_DEDUCTION', 'INVESTMENT_OUT'];
    
    foreach ($all_rows_processed as $r) {
        $cat = $r['tea_type'];
        $base_amt_signed_str = $r['tea_amount_base_str']; 
        $date = $r['tea_date'];
        $is_equity = (bool)$r['tea_is_equity'];
        
        $is_within_filter = ($date >= $user_filter_start_date && $date <= $user_filter_end_date);
        
        // --- KPI Calculation (Full History) ---
        
        // 1. Total Principal KPI (只计算计股本金的投入/流出)
        // 投入：INVESTMENT_IN 且 tea_is_equity=1
        if ($cat === 'INVESTMENT_IN' && $is_equity) {
            $total_principal_kpi_str = bcadd($total_principal_kpi_str, $base_amt_signed_str, DISPLAY_SCALE);
        }
        // 减少：DIVIDEND_DEDUCTION (分红抵扣)
        elseif ($cat === 'DIVIDEND_DEDUCTION') {
             $total_principal_kpi_str = bcadd($total_principal_kpi_str, $base_amt_signed_str, DISPLAY_SCALE);
        }
        // 减少：INVESTMENT_OUT (投资款出) 且 tea_is_equity=1
        elseif ($cat === 'INVESTMENT_OUT' && $is_equity) {
             $total_principal_kpi_str = bcadd($total_principal_kpi_str, $base_amt_signed_str, DISPLAY_SCALE);
        }
        
        // 2. Total Returns KPI (只计算现金分红流入)
        if ($cat === 'DIVIDEND_CASH' && bccomp($base_amt_signed_str, '0.00', DISPLAY_SCALE) > 0) {
            $total_returns_kpi_str = bcadd($total_returns_kpi_str, $base_amt_signed_str, DISPLAY_SCALE);
        }
        
        // 3. Total Expense Accumulation (Absolute magnitude of outflow types)
        if (in_array($cat, OUTFLOW_TYPES)) {
            // 计算支出的绝对值 (Expense magnitude is always positive)
            $expense_magnitude_str = bcmul($base_amt_signed_str, '-1', DISPLAY_SCALE); 
            
            // 3.1. Full History Total Expense
            $total_expense_full_str = bcadd($total_expense_full_str, $expense_magnitude_str, DISPLAY_SCALE);

            // 3.2. Filtered Range Total Expense
            if ($is_within_filter) {
                $total_expense_filtered_str = bcadd($total_expense_filtered_str, $expense_magnitude_str, DISPLAY_SCALE);
            }
        }

        // Record earliest date for Info Table
        if (in_array($cat, KPI_CATEGORIES) && bccomp($base_amt_signed_str, '0.00', DISPLAY_SCALE) != 0) {
            if ($first_kpi_start_date === null || $date < $first_kpi_start_date) {
                $first_kpi_start_date = $date;
            }
        }
        
        // Record earliest principal date
        if (bccomp($total_principal_kpi_str, '0.00', DISPLAY_SCALE) != 0 && $first_principal_date === null) {
             $first_principal_date = $date;
        }


        // --- Breakdown (User Filtered Range only) ---
        if ($is_within_filter) {
            // [FIXED] 确保所有 KPI_CATEGORIES 都被计入 Breakdown
            if (in_array($cat, KPI_CATEGORIES)) {
                // 确保未在 $breakdown 中初始化的类别能被正确处理
                if (!isset($breakdown[$cat])) {
                     $breakdown[$cat] = '0.00';
                }
                $breakdown[$cat] = bcadd($breakdown[$cat], $base_amt_signed_str, DISPLAY_SCALE);
                
                // 累计净值 (所有交易都影响净值)
                $total_net_return_breakdown_str = bcadd($total_net_return_breakdown_str, $base_amt_signed_str, DISPLAY_SCALE);
            }
        }
    }
    
    // Final KPI calculations
    $final_principal_str = number_format((float)$total_principal_kpi_str, DISPLAY_SCALE, '.', '');
    $total_principal_kpi = (float)$final_principal_str; 

    $final_returns_str = number_format((float)$total_returns_kpi_str, DISPLAY_SCALE, '.', '');
    $total_returns_kpi = (float)$final_returns_str;
    
    // Secondary KPI: Total Net Return (Full History)
    $total_net_kpi_str = bcadd($final_principal_str, $final_returns_str, DISPLAY_SCALE);
    $total_net_kpi = (float)$total_net_kpi_str;

    // ROI / Annualized Calculations
    $roi_base = ($total_principal_kpi != 0.0) ? $total_principal_kpi : null;
    $roi_kpi = ($roi_base !== null && $roi_base != 0.0) ? ($total_returns_kpi / $roi_base) : null;
    
    $end_dt   = new DateTimeImmutable($user_filter_end_date);
    $start_dt = $first_principal_date ? new DateTimeImmutable($first_principal_date) : null;
    $months = null;
    if ($start_dt) {
        $diff = $start_dt->diff($end_dt);
        $months = $diff->y * 12 + $diff->m + 1;
    }

    // ROI 公式: (1 + ROI) ^ (12/months) - 1
    $annual_kpi = ($roi_kpi !== null && $months && $months > 0) ? (pow(1 + $roi_kpi, 12/$months) - 1) : null;
    
    
    // -----------------------------------------------------------
    // --- 4. Recent Transactions (Use processed strings for display) ---
    // -----------------------------------------------------------
    
    $recent_transactions = [];
    foreach($all_rows_processed as $r) {
        if ($r['tea_date'] >= $user_filter_start_date && $r['tea_date'] <= $user_filter_end_date) {
            $tx = [
                'tea_fin_id' => $r['tea_fin_id'], // <<< MUST BE INCLUDED
                'tea_date' => $r['tea_date'],
                'tea_store' => $r['tea_store'],
                'tea_currency' => $r['tea_currency'],
                'tea_exchange_rate' => (float)$r['tea_exchange_rate'], 
                'tea_type' => $r['tea_type'],
                // FIX: 转换为 float，但确保它已格式化到 DISPLAY_SCALE (使用 base_str)
                'tea_amount' => (float)number_format((float)$r['tea_amount_base_str'], DISPLAY_SCALE, '.', ''),
                'tea_is_equity' => (bool)$r['tea_is_equity'],
                'tea_notes' => $r['tea_notes']
            ];
            $recent_transactions[] = $tx;
        }
    }
    
    // 按照日期倒序排列
    usort($recent_transactions, function($a, $b) {
        if ($a['tea_date'] == $b['tea_date']) {
            return 0;
        }
        return ($a['tea_date'] > $b['tea_date']) ? -1 : 1;
    });


    // --- 5. Build Final Payload ---
    $float_breakdown = [];
    foreach ($breakdown as $key => $value) {
        $float_breakdown[$key] = (float)number_format((float)$value, DISPLAY_SCALE, '.', '');
    }

    $payload = [
        'range' => ['start_date' => $user_filter_start_date, 'end_date' => $user_filter_end_date],
        'summary' => [
            'total_principal_kpi' => $total_principal_kpi,      
            'total_returns_kpi' => $total_returns_kpi, 
            'total_net_kpi' => $total_net_kpi,
            'roi_kpi' => $roi_kpi,
            'annualized_kpi' => $annual_kpi,
            'invest_months' => $months, 
            'invest_start' => $first_kpi_start_date,
            'invest_end'   => $user_filter_end_date,
            'total_net_return_breakdown' => (float)number_format((float)$total_net_return_breakdown_str, DISPLAY_SCALE, '.', ''),
            'total_expense_full' => (float)number_format((float)$total_expense_full_str, DISPLAY_SCALE, '.', ''),
            'total_expense_filtered' => (float)number_format((float)$total_expense_filtered_str, DISPLAY_SCALE, '.', '')
        ],
        'breakdown' => $float_breakdown,
        'recent_transactions' => $recent_transactions, 
    ];

    echo json_encode(['success' => true, 'data' => $payload]);
} catch (Throwable $e) {
    error_log("tea_report_investor_get_data error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '服务器错误: ' . $e->getMessage()]);
}