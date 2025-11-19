<?php
// /app/cp/som/views/som_view_report_store.php
// ABCABC-CP | Store Report View (Monthly Macro, Refined Palette + Theme Switch)
// [MODIFIED] 2025-11-18 (v9):
// 1. Tables sorted Descending.
// 2. Added "Same Period Comparison".
// 3. [CRITICAL FIX] Total Expense & Net logic simplified.
//    Daily expenses ALREADY include salary. No extra addition/subtraction of salary is performed on totals.

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { exit('Access Denied.'); }
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-daterangepicker/3.0.5/daterangepicker.css" />
<link rel="stylesheet" href="/cp/som/css/som_style.css">

<section id="report-store" class="content-header-replacement">
  <div class="page-header-title">
    <h1>店铺报表 <small>最近 12 个月 · 宏观视图</small></h1>
  </div>
  <ol class="breadcrumb">
    <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
    <li class="active">店铺报表</li>
  </ol>
</section>

<section id="report-store" class="content">
  <div class="card">
    <div class="card-header">
      <h3 class="box-title"><i class="fa fa-sliders"></i> 筛选</h3>
    </div>
    <div class="card-body">
      <div class="row" style="align-items:center; row-gap:12px;">
        <div class="col-md-4">
          <label style="color:var(--c-muted)">日期范围（默认最近 12 个月）</label><br/>
          <button type="button" class="btn btn-default" id="daterange-btn">
            <span><i class="fa fa-calendar"></i> 选择区间</span>
            <i class="fa fa-caret-down"></i>
          </button>
        </div>
        <div class="col-md-4">
          <div class="subtle" style="color:var(--c-muted); margin-bottom:6px">月营业额=当月现金+银行收入；次月 1 号的银行收入归次月。</div>
          <button class="btn btn-primary" id="btn-generate"><i class="fa fa-rocket"></i> 生成报表</button>
          <button class="btn btn-default" id="btn-last12"><i class="fa fa-history"></i> 最近 12 个月</button>
          <button class="btn btn-default" id="btn-export"><i class="fa fa-download"></i> 导出 CSV</button>
        </div>
        <div class="col-md-4" style="text-align:right">
          <div class="seg" id="theme-seg">
            <button data-theme="auto"  class="active">系统</button>
            <button data-theme="light">浅色</button>
            <button data-theme="dark">深色</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="kpi-grid" id="kpis" style="display:none;">
    <div class="kpi">
        <p>总营业额 (当月)</p>
        <h3 id="kpi-total-rev">—</h3>
        <small class="comparison" id="kpi-total-rev-comp">上月: —</small>
    </div>
    <div class="kpi">
        <p>总人数 (当月)</p>
        <h3 id="kpi-people">—</h3>
        <small class="comparison" id="kpi-people-comp">上月: —</small>
    </div>
    <div class="kpi">
        <p>人均消费 (当月)</p>
        <h3 id="kpi-avg">—</h3>
        <small class="comparison" id="kpi-avg-comp">上月: —</small>
    </div>
    <div class="kpi">
        <p>总分红 (当月)</p>
        <h3 id="kpi-dividend">—</h3>
        <small class="comparison" id="kpi-dividend-comp">上月: —</small>
    </div>
    <div class="kpi">
        <p>经营净额 (当月)</p>
        <h3 id="kpi-net">—</h3>
        <small class="comparison" id="kpi-net-comp">上月: —</small>
    </div>
  </div>

  <div class="card" id="report-card" style="display:none;">
    <div class="card-header">
      <h3 class="box-title" id="report-title"><i class="fa fa-table"></i> 店铺报表 (月度)</h3>
    </div>
    <div class="card-body">
      <div class="table-wrap" style="margin-top:0; max-height:540px; overflow:auto;">
        <table class="table table-striped table-hover" id="tbl-monthly">
          <thead>
            <tr>
              <th>月份</th><th>人数</th><th>现金收入</th><th>银行收入</th><th>总收入</th>
              <th>现金支出</th><th>银行支出</th><th>总支出 (含工资)</th><th>人均消费</th><th>月总分红</th><th>经营净额 (含工资)</th>
            </tr>
          </thead>
          <tbody><tr><td colspan="11"><div class="skeleton" style="width:96%; margin:8px auto"></div></td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card" id="comparison-card" style="display:none;">
    <div class="card-header">
      <h3 class="box-title"><i class="fa fa-balance-scale"></i> 同期对比 <small id="comparison-title-date" style="color:var(--c-text); opacity:0.7; font-size:13px;">(截至每月 X 日)</small></h3>
    </div>
    <div class="card-body">
      <div class="subtle" style="color:var(--c-muted); margin-bottom:10px; font-size:12px;">
        * 此表仅对比每月 1号 至 截止日（数据库最新记录日期）的累计数据。<br/>
        * <b>经营净额</b> = 区间总收入 - 区间运营支出 (已含工资)。
      </div>
      <div class="table-wrap" style="margin-top:0; max-height:540px; overflow:auto;">
        <table class="table table-striped table-hover" id="tbl-comparison">
          <thead>
            <tr>
              <th>月份</th>
              <th>对比天数</th>
              <th>人数</th>
              <th>总收入</th>
              <th>人均消费</th>
              <th>经营净额 (含工资)</th>
            </tr>
          </thead>
          <tbody><tr><td colspan="6"><div class="skeleton" style="width:96%; margin:8px auto"></div></td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card" id="salary-card" style="display:none;">
    <div class="card-header">
        <h3 class="box-title"><i class="fa fa-users"></i> 工资概要 (月度)</h3>
    </div>
    <div class="card-body">
        <div class="table-wrap" style="margin-top:0; max-height:540px; overflow:auto;">
            <table class="table table-striped table-hover" id="tbl-salary-summary">
                <thead>
                    <tr>
                        <th>月份</th>
                        <th>寿司房工资</th>
                        <th>占比</th>
                        <th>厨房工资</th>
                        <th>占比</th>
                        <th>跑堂工资</th>
                        <th>占比</th>
                        <th>总工资</th>
                        <th>占总支出 %</th>
                        <th>占总收入 %</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="10"><div class="skeleton" style="width:96%; margin:8px auto"></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
  </div>

</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-daterangepicker/3.0.5/daterangepicker.min.js"></script>
<script>
    const CP_BASE_URL = "<?php echo CP_BASE_URL; ?>";
</script>
<script src="/cp/som/js/som_report_store.js"></script>