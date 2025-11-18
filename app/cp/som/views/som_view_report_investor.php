<?php
// /app/cp/som/som/som/views/som_view_report_investor.php
// ABCABC-CP | Investor Report View (Refined Palette + Theme Switch)
// [MODIFIED] 2025-11-14 (v6): 响应用户要求，移除口径说明、总分红行、投资明细列和子行。

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { exit('Access Denied.'); }

// [NEW] Query for min/max dates to constrain the picker
global $pdo;
if (!isset($pdo)) {
    // 确保 $pdo 可用, 引用 bootstrap.php
    require_once dirname(__DIR__, 2) . '/bootstrap.php';
}

$min_date = '2023-01-01'; // 默认回退值
$max_date = date('Y-m-d'); // 今天

try {
    // 查找最早的交易日期以设置最小可选日期
    $stmt = $pdo->prepare("SELECT MIN(ss_fin_date) FROM sushisom_financial_transactions WHERE ss_fin_amount != 0");
    $stmt->execute();
    $db_min_date = $stmt->fetchColumn();
    if ($db_min_date) {
        $min_date = $db_min_date;
    }
} catch (Exception $e) {
    error_log("Failed to get min_date for investor report: " . $e->getMessage());
}

?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-daterangepicker/3.0.5/daterangepicker.css" />
<link rel="stylesheet" href="/cp/som/css/som_style.css">

<section id="report-invest" class="content-header-replacement">
  <div class="page-header-title">
    <h1>投资回报 <small>池级别</small></h1>
  </div>
  <ol class="breadcrumb">
    <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
    <li class="active">投资回报</li>
  </ol>
</section>

<section id="report-invest" class="content">
  <div class="card">
    <div class="card-header">
      <h3 class="box-title"><i class="fa fa-sliders"></i> 筛选</h3>
    </div>
    <div class="card-body">
      <div class="row" style="align-items:center; row-gap:12px;">
        <div class="col-md-4">
          <label style="color:var(--c-muted)">日期范围</label><br/>
          <button id="daterange-btn-investor" class="btn btn-default">
            <span><i class="fa fa-calendar"></i> 选择区间</span>
            <i class="fa fa-caret-down"></i>
          </button>
        </div>
        <div class="col-md-3">
          <label style="color:var(--c-muted)">包含工资</label><br/>
          <input type="checkbox" id="chk-include-wage" />
          <small style="color:var(--c-muted)">开启后，总回报/ROI/年化将纳入工资四类</small>
        </div>
        <div class="col-md-3">
          <button class="btn btn-primary" id="btn-go"><i class="fa fa-rocket"></i> 生成报表</button>
          <button class="btn btn-default" id="btn-export-invest"><i class="fa fa-download"></i> 导出 CSV</button>
        </div>
        <div class="col-md-2" style="text-align:right">
          <div class="seg" id="theme-seg">
            <button data-theme="auto"  class="active">系统</button>
            <button data-theme="light">浅色</button>
            <button data-theme="dark">深色</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="kpi-grid" id="kpi-row" style="display:none;">
    <div class="kpi">
        <p>总投资（含抵扣）</p>
        <h3 id="v-total-principal">—</h3>
        <small class="comparison" id="v-total-principal-net-comparison" style="margin-top: 5px; display: block;">净额: —</small>
    </div>
    <div class="kpi"><p>总回报（不含工资）</p><h3 id="v-total-returns-excl">—</h3></div>
    <div class="kpi"><p>ROI / 年化（不含工资）</p><h3><span id="v-roi-excl">—</span> <span class="badge-pill pill-ok" id="v-annual-excl">—</span></h3></div>
    <div class="kpi"><p>ROI / 年化（含工资）</p><h3><span id="v-roi-incl">—</span> <span class="badge-pill pill-warn" id="v-annual-incl">—</span></h3></div>
  </div>

  <div class="card" id="card-result" style="display:none;">
    <div class="card-header">
      <h3 class="box-title" id="res-title"><i class="fa fa-chart-pie"></i> 投资回报</h3>
    </div>
    <div class="card-body">

      <div class="row" style="row-gap:14px;">
        <div class="col-md-12">
          <div class="well">
            <table class="table table-bordered">
              <tbody>
                <tr><th>投资日期</th><td id="v-start-end">— ~ —</td></tr>
                <tr><th>投资月数</th><td id="v-invest-months">—</td></tr>
                <tr><th>总回报（不含工资）</th><td><span class="badge-pill pill-ok" id="v-pure-cash-dividend">—</span></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="row" style="row-gap:14px;">
        <div class="col-md-6">
          <h4>投资类明细（计入本金）</h4>
          <table class="table table-striped table-hover table-breakdown">
            <thead><tr><th>类别</th><th style="text-align: right;">金额</th></tr></thead>
            <tbody>
              <tr class="total-row">
                <td>投资款 (investor_investment_out)</td>
                <td id="b-investor-investment-out-net">0.00</td>
              </tr>

              <tr class="total-row">
                <td>分红抵扣 (dividend_deduction)</td>
                <td id="b-dividend-deduction" style="text-align: right;">0.00</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="col-md-6">
          <h4>回报类明细</h4>
          <table class="table table-striped table-hover" id="tbl-return">
            <thead><tr><th>类别</th><th style="text-align: right;">金额</th></tr></thead>
            <tbody>
              <tr><td>现金分红（dividend_cash）</td><td id="b-dividend-cash" style="text-align: right;">0.00</td></tr>
              <tr><td>工资：现金Z</td><td id="b-salary-cash-z" style="text-align: right;">0.00</td></tr>
              <tr><td>工资：现金C</td><td id="b-salary-cash-c" style="text-align: right;">0.00</td></tr>
              <tr><td>工资：银行Z</td><td id="b-salary-bank-z" style="text-align: right;">0.00</td></tr>
              <tr><td>工资：银行C</td><td id="b-salary-bank-c" style="text-align: right;">0.00</td></tr>
              <tr style="background:var(--c-surface);"><td style="font-weight:bold;">总现金工资</td><td style="font-weight:bold; text-align: right;" id="b-total-salary-cash">0.00</td></tr>
              <tr style="background:var(--c-surface);"><td style="font-weight:bold;">总银行工资</td><td style="font-weight:bold; text-align: right;" id="b-total-salary-bank">0.00</td></tr>
            </tbody>
          </table>
        </div>
      </div>

       <div class="card" id="card-recent-txs" style="display:none; margin-top: 20px;">
          <div class="card-header" style="background:transparent; border-bottom:1px solid var(--c-border);">
            <h3 class="box-title" id="recent-txs-title"><i class="fa fa-history"></i> 投资/回报记录 (日期范围)</h3>
          </div>
          <div class="card-body" style="padding-top: 0;">
              <div class="table-wrap">
                   <table class="table table-striped table-hover">
                      <thead>
                          <tr>
                              <th>日期</th>
                              <th>类别</th>
                              <th style="text-align: right;">金额</th>
                          </tr>
                      </thead>
                      <tbody id="tbl-recent-txs-body">
                          </tbody>
                  </table>
              </div>
          </div>
        </div>


    </div>
  </div>

</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-daterangepicker/3.0.5/daterangepicker.min.js"></script>
<script>
    const CP_BASE_URL = "<?php echo CP_BASE_URL; ?>";
</script>
<script src="/cp/som/js/som_report_investor.js"></script>