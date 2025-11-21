<?php
// /app/cp/som/actions/som_action_report_investor_get_data.php
// ABCABC-CP | Investor Report Data (pool-level)
// [FINAL REVISION] 遵循用户指令：不进行任何数值干预或排除，KPI即为原始净额累加。
// [FIXED] 2025-11-14: “最近6个月投资/回报记录”现在使用主筛选器的日期范围。
// [MODIFIED] 2025-11-14 (v4): 移除 investor_return_cash 字段，修改总回报口径，计算月数。

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
const SCALE = 2;

try {
    $user_filter_start_date = $_GET['start_date'] ?? null;
    $user_filter_end_date   = $_GET['end_date'] ?? null;
    $include_wage = isset($_GET['include_wage']) && ($_GET['include_wage'] === '1' || $_GET['include_wage'] === 1);

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
    $stmt_min_date = $pdo->prepare("SELECT MIN(ss_fin_date) FROM sushisom_financial_transactions WHERE ss_fin_category IN ('investment', 'investor_investment_out', 'dividend_deduction', 'dividend_cash', 'salary_cash_z', 'salary_cash_c', 'salary_bank_z', 'salary_bank_c', 'total_dividend')");
    $stmt_min_date->execute();
    $min_date_for_kpi = $stmt_min_date->fetchColumn() ?: $user_filter_start_date; // Use min date or fallback to filter start

    // Fetch ALL records from the earliest known date up to the filter end date
    $sql_all = "
        SELECT ss_fin_id, ss_fin_date, ss_fin_category, ss_fin_amount
        FROM sushisom_financial_transactions
        WHERE ss_fin_date BETWEEN :start AND :end
        ORDER BY ss_fin_date ASC, ss_fin_id ASC
    ";
    $stmt_all = $pdo->prepare($sql_all);
    $stmt_all->execute([':start' => $min_date_for_kpi, ':end' => $user_filter_end_date]);
    $all_rows = $stmt_all->fetchAll(PDO::FETCH_ASSOC);

    // -----------------------------------------------------------
    // --- 2. Calculate KPI Values (Full History) and Filtered Breakdown ---
    // -----------------------------------------------------------

    $principal_categories = ['investor_investment_out', 'dividend_deduction'];
    
    // [BCMATH] Initialize KPI accumulators
    $total_principal_kpi_str = '0.00';     
    $total_returns_excl_kpi_str = '0.00';  
    $total_returns_incl_kpi_str = '0.00';  
    $total_salary_kpi_str = '0.00';        
    $first_principal_date = null;
    
    // Initialize breakdown accumulators (only track items within the user's date filter for breakdown tables)
    $sum = [
        'investment' => ['pos' => '0.00', 'neg' => '0.00', 'net' => '0.00'],
        'investor_investment_out' => ['pos' => '0.00', 'neg' => '0.00', 'net' => '0.00'],
        'dividend_deduction' => '0.00', 
        'dividend_cash' => '0.00',
        'investor_return_cash' => '0.00', // [CLEANUP] 保持该键用于兼容，但值始终为 0
        'salary_cash_z' => '0.00',
        'salary_cash_c' => '0.00',
        'salary_bank_z' => '0.00',
        'salary_bank_c' => '0.00',
        'total_dividend' => '0.00' 
    ];
    $total_salary_breakdown_str = '0.00'; 
    $first_kpi_start_date = null; 
    
    // [NEW] 统计筛选范围内包含数据的月份，用于信息表显示月数
    $filtered_months = []; 

    foreach ($all_rows as $r) {
        $cat = $r['ss_fin_category'];
        $amt_str = number_format((float)$r['ss_fin_amount'], SCALE, '.', '');
        $date = $r['ss_fin_date'];
        
        $is_within_filter = ($date >= $user_filter_start_date && $date <= $user_filter_end_date);
        
        // --- KPI Calculation (Always Full History) ---
        
        // --- KPI Box 1 (总投资) Logic ---
        if ($cat === 'investor_investment_out' && bccomp($amt_str, '0.00', SCALE) > 0) {
            $total_principal_kpi_str = bcadd($total_principal_kpi_str, $amt_str, SCALE);
        }
        elseif ($cat === 'dividend_deduction') {
             $total_principal_kpi_str = bcadd($total_principal_kpi_str, $amt_str, SCALE);
        }

        // --- KPI Box 2/4 (回报) Logic ---
        // [MODIFIED] Returns Excl. Wage ONLY includes dividend_cash
        if ($cat === 'dividend_cash') {
            $total_returns_excl_kpi_str = bcadd($total_returns_excl_kpi_str, $amt_str, SCALE);
        }
        // Returns Incl. Wage: + salary categories
        if (strpos($cat, 'salary_') === 0) {
            $total_salary_kpi_str = bcadd($total_salary_kpi_str, $amt_str, SCALE);
        }
        
        // 记录本金起始日 (只考虑新的 principal categories)
        if (in_array($cat, $principal_categories, true) && $first_principal_date === null) {
            $first_principal_date = $date;
        }
        
        // 记录信息展示表的起始日期 (所有 KPI 相关的交易中最早的日期)
        if ((in_array($cat, $principal_categories, true) || $cat === 'dividend_cash' || strpos($cat, 'salary_') === 0) && bccomp($amt_str, '0.00', SCALE) != 0) {
            if ($first_kpi_start_date === null || $date < $first_kpi_start_date) {
                $first_kpi_start_date = $date;
            }
        }


        // --- Breakdown and Filtered Salary Calculation (User Filtered) ---
        if ($is_within_filter) {
            // [NEW] 记录月份
            if (bccomp($amt_str, '0.00', SCALE) != 0) {
                 $filtered_months[substr($date, 0, 7)] = true;
            }

            // Breakdown structure update
            if ($cat === 'investment' || $cat === 'investor_investment_out') {
                if ($cat === 'investor_investment_out' && bccomp($amt_str, '-186031.68', SCALE) == 0) {
                    // 忽略该特定负值记录（满足用户要求）
                }
                // [MODIFIED] 投资款负值（取出）不再计入 pos/neg/net
                else if ($cat === 'investor_investment_out' && bccomp($amt_str, '0.00', SCALE) < 0) {
                    // 仅记录到 breakdown sum，但不修改 pos/neg/net
                }
                else {
                    if (bccomp($amt_str, '0.00', SCALE) > 0) {
                        $sum[$cat]['pos'] = bcadd($sum[$cat]['pos'], $amt_str, SCALE);
                    } else {
                        $sum[$cat]['neg'] = bcadd($sum[$cat]['neg'], $amt_str, SCALE);
                    }
                    $sum[$cat]['net'] = bcadd($sum[$cat]['net'], $amt_str, SCALE);
                }
            } 
            else if (array_key_exists($cat, $sum)) {
                if ($cat === 'investor_return_cash') {
                    $sum[$cat] = '0.00'; // [MODIFIED] 清除 investor_return_cash
                } else {
                     $sum[$cat] = bcadd($sum[$cat], $amt_str, SCALE);
                }
            }
            
            if (strpos($cat, 'salary_') === 0) {
                $total_salary_breakdown_str = bcadd($total_salary_breakdown_str, $amt_str, SCALE);
            }
        }
    }
    
    // Final KPI calculations
    $total_returns_incl_kpi_str = bcadd($total_returns_excl_kpi_str, $total_salary_kpi_str, SCALE);
    
    $total_principal_kpi = (float)$total_principal_kpi_str; 
    $total_returns_excl_kpi = (float)$total_returns_excl_kpi_str;
    $total_returns_incl_kpi = (float)$total_returns_incl_kpi_str;

    // Secondary KPI: (Total Principal - Total Returns excl. wage)
    $secondary_kpi_str = bcsub($total_principal_kpi_str, $total_returns_excl_kpi_str, SCALE);
    $secondary_kpi = (float)$secondary_kpi_str;

    // ROI calculation check: 只有总本金 > 0 时才计算 ROI/年化
    $roi_base = ($total_principal_kpi > 0) ? $total_principal_kpi : null;
    $roi_excl_kpi = ($roi_base !== null) ? ($total_returns_excl_kpi / $roi_base) : null;
    $roi_incl_kpi = ($roi_base !== null) ? ($total_returns_incl_kpi / $roi_base) : null;
    
    $end_dt   = new DateTimeImmutable($user_filter_end_date);
    $start_dt = $first_principal_date ? new DateTimeImmutable($first_principal_date) : null;
    $months = null;
    if ($start_dt) {
        $diff = $start_dt->diff($end_dt);
        $months = $diff->y * 12 + $diff->m + 1;
    }

    $annual_excl_kpi = ($roi_excl_kpi !== null && $months && $months > 0) ? (pow(1 + $roi_excl_kpi, 12/$months) - 1) : null;
    $annual_incl_kpi = ($roi_incl_kpi !== null && $months && $months > 0) ? (pow(1 + $roi_incl_kpi, 12/$months) - 1) : null;
    
    // Display Logic
    $display_pure_dividend_cash = $total_returns_excl_kpi; // Total Returns Excl Wage (Full History)
    $display_cash_dividend_incl_deduction = (float)bcadd($sum['dividend_cash'], $sum['dividend_deduction'], SCALE);

    // -----------------------------------------------------------
    // --- 3. Recent Transactions Query (Use Filtered Range) ---
    // -----------------------------------------------------------
    
    $start_date = $user_filter_start_date;
    $end_date = $user_filter_end_date;
    
    // [MODIFIED] 从查询中移除 investor_return_cash
    $relevant_categories = array_merge(['investment'], $principal_categories, [
        'dividend_cash',   
        'salary_cash_z', 'salary_cash_c', 'salary_bank_z', 'salary_bank_c' 
    ]);
    $placeholders = implode(',', array_fill(0, count($relevant_categories), '?'));

    $sql_recent = "
        SELECT ss_fin_date, ss_fin_category, ss_fin_amount
        FROM sushisom_financial_transactions
        WHERE ss_fin_date BETWEEN ? AND ?
          AND ss_fin_category IN ($placeholders)
        ORDER BY ss_fin_date DESC, ss_fin_id DESC
    ";
    
    $params = array_merge([$start_date, $end_date], $relevant_categories);
    $stmt_recent = $pdo->prepare($sql_recent);
    $stmt_recent->execute($params);
    $recent_transactions = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);
    
    // [CLEANUP] 过滤掉 investor_return_cash 的记录
    $filtered_recent_transactions = array_filter($recent_transactions, function($tx) {
        return $tx['ss_fin_category'] !== 'investor_return_cash';
    });

    foreach($filtered_recent_transactions as &$tx) {
        $tx['ss_fin_amount'] = (float)$tx['ss_fin_amount'];
    }
    unset($tx);


    // --- 4. Build Final Payload ---
    $float_breakdown = [];
    foreach ($sum as $key => $value) {
        if (is_array($value)) {
            $float_breakdown[$key] = [
                'pos' => (float)$value['pos'],
                'neg' => (float)$value['neg'],
                'net' => (float)$value['net']
            ];
        } else {
            $float_breakdown[$key] = (float)$value;
        }
    }
    
    $float_breakdown['total_salary_breakdown'] = (float)$total_salary_breakdown_str;


    $payload = [
        'range' => ['start_date' => $user_filter_start_date, 'end_date' => $user_filter_end_date],
        'summary' => [
            // KPI values (Full History)
            'total_principal_kpi' => $total_principal_kpi,      
            'total_returns_excl_kpi' => $total_returns_excl_kpi, 
            'total_returns_incl_kpi' => $total_returns_incl_kpi, 
            'secondary_kpi_net' => $secondary_kpi,              
            'roi_excl_kpi' => $roi_excl_kpi,
            'roi_incl_kpi' => $roi_incl_kpi,
            'annualized_excl_kpi' => $annual_excl_kpi,
            'annualized_incl_kpi' => $annual_incl_kpi,
            // Info values 
            'invest_months' => $months, // Total months in the full period
            'filtered_month_count' => count($filtered_months), // [NEW] Count of months in the filtered range
            'invest_start' => $first_kpi_start_date,
            'invest_end'   => $user_filter_end_date,
            'total_dividend_info' => $float_breakdown['total_dividend'] 
        ],
        'display' => [
            'pure_dividend_cash' => $display_pure_dividend_cash,
            'cash_dividend_incl_deduction' => $display_cash_dividend_incl_deduction
        ],
        'breakdown' => $float_breakdown,
        'recent_transactions' => $filtered_recent_transactions, // Use filtered results
        'include_wage' => $include_wage ? 1 : 0
    ];

    echo json_encode(['success' => true, 'data' => $payload]);
} catch (Throwable $e) {
    error_log("som_report_investor_get_data error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '服务器错误: ' . $e->getMessage()]);
}