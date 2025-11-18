<?php
// /app/cp/tea/views/tea_view_report_investor.php
// <tea> Project Investor Report View (T2) - FINALIZED FIXES for Date Display
// [MODIFIED] 2025-11-15: 调整 KPI 布局为 3 列，添加 SUPPLIES/SHIPPING 到 breakdown。

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { exit('Access Denied.'); }

// 确保 $pdo 可用
global $pdo;
if (!isset($pdo)) {
    require_once dirname(__DIR__, 2) . '/bootstrap.php';
}

$min_date = '2023-01-01';
$max_date = date('Y-m-d');

try {
    // 查找最早的交易日期以设置最小可选日期
    $stmt = $pdo->prepare("SELECT MIN(tea_date) FROM tea_financial_transactions WHERE tea_amount != 0");
    $stmt->execute();
    $db_min_date = $stmt->fetchColumn();
    if ($db_min_date) {
        $min_date = $db_min_date;
    }
} catch (Exception $e) {
    // Ignore error
}

?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-daterangepicker/3.0.5/daterangepicker.css" />
<link rel="stylesheet" href="/cp/tea/css/tea_style.css">

<section id="report-invest" class="content-header-replacement">
  <div class="page-header-title">
    <h1><tea> 投资人报表 <small>T2</small></h1>
  </div>
  <ol class="breadcrumb">
    <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
    <li class="active"><tea> 投资人报表</li>
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
          <button id="daterange-btn-tea-investor" class="btn btn-default">
            <span><i class="fa fa-calendar"></i> 选择区间</span>
            <i class="fa fa-caret-down"></i>
          </button>
        </div>

        <div class="col-md-5">
          <button class="btn btn-primary" id="btn-go"><i class="fa fa-rocket"></i> 生成报表</button>
          <button class="btn btn-default" id="btn-export-invest"><i class="fa fa-download"></i> 导出 CSV</button>
        </div>

        <div class="col-md-3" style="text-align:right">
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
        <p>总投资 (计股本金)</p>
        <h3 id="v-total-principal">—</h3>
        <small class="comparison" id="v-net-kpi-comparison" style="margin-top: 5px; display: block;">总净回报: —</small>
    </div>
    <div class="kpi"><p>总回报 (现金分红)</p><h3 id="v-total-returns">—</h3></div>
    <div class="kpi"><p>ROI / 年化</p><h3><span id="v-roi">—</span> <span class="badge-pill pill-ok" id="v-annual">—</span></h3></div>
  </div>

  <div class="card" id="card-result" style="display:none;">
    <div class="card-header">
      <h3 class="box-title" id="res-title"><i class="fa fa-chart-pie"></i> 投资回报明细</h3>
    </div>
    <div class="card-body">

      <div class="row" style="row-gap:14px;">
        <div class="col-md-12">
          <div class="well">
            <table class="table table-bordered">
              <tbody>
                <tr><th>统计日期</th><td id="v-start-end">— ~ —</td></tr>
                <tr><th>投资周期 (月)</th><td id="v-invest-months">—</td></tr>
                <tr><th>交易净回报 (筛选期)</th><td><span class="badge-pill pill-ok" id="v-net-return-period">—</span></td></tr>

                <tr><th>总支出 (全周期)</th><td id="v-total-expense-full" class="text-red">—</td></tr>
                <tr><th>总支出 (筛选期)</th><td id="v-total-expense-filtered" class="text-red">—</td></tr>

              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="row" style="row-gap:14px;">
        <div class="col-md-12">
          <h4>交易分类汇总 (筛选期内)</h4>
          <table class="table table-striped table-hover table-breakdown">
            <thead>
                <tr><th>类别</th><th style="text-align: right;">金额</th></tr>
            </thead>
            <tbody id="tbl-breakdown-body">
                </tbody>
            <tfoot id="tbl-breakdown-tfoot">
                <tr class="total-row">
                    <td>总交易净额</td>
                    <td id="b-total-net" style="text-align: right;">0.00</td>
                </tr>
            </tfoot>
          </table>
        </div>
      </div>

       <div class="card" id="card-recent-txs" style="display:none; margin-top: 20px;">
          <div class="card-header" style="background:transparent; border-bottom:1px solid var(--c-border);">
            <h3 class="box-title" id="recent-txs-title"><i class="fa fa-history"></i> 投资交易记录 (日期范围)</h3>
          </div>
          <div class="card-body" style="padding-top: 0;">
              <div class="table-wrap">
                   <table class="table table-striped table-hover">
                      <thead>
                          <tr>
                              <th>日期</th>
                              <th>店铺</th>
                              <th>类型</th>
                              <th style="text-align: right;">金额/币种/汇率 (EUR)</th>
                              <th>是否计股</th>
                              <th>备注</th>
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
<script src="/cp/tea/js/tea_report_investor.js"></script>