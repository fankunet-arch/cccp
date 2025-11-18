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

<style>
:root{
  --c-bg: #0e1116; --c-panel:#171b22; --c-surface:#1d232f; --c-border:#242a36; --c-text:#eaf0f6; --c-muted:#9aa5b5;
  --c-primary:#3b82f6; --c-primary-weak:rgba(59,130,246,.12);
  --c-good:#16a34a; --c-warn:#f59e0b; --c-bad:#ef4444;
  --c-table-head:#121826; --c-grid:rgba(255,255,255,.08);
  --radius-lg:14px; --radius-md:10px;
}
[data-theme="light"]{
  --c-bg:#f6f7fb; --c-panel:#fff; --c-surface:#fff; --c-border:#e8ecf2; --c-text:#0f172a; --c-muted:#657089;
  --c-table-head:#eef2f8; --c-grid:rgba(0,0,0,.08);
}
#report-invest{ color: var(--c-text); }
#report-invest .page-header-title h1 { font-weight:700; }

#report-invest .card{ background:var(--c-panel); border:1px solid var(--c-border); border-radius:var(--radius-lg); box-shadow:0 6px 18px rgba(0,0,0,.18); margin-bottom:18px; overflow:hidden; }
#report-invest .card-header{ background:linear-gradient(90deg, var(--c-primary-weak), transparent 55%); padding:14px 18px; border-bottom:1px solid var(--c-border); }
#report-invest .box-title{ font-weight:700; color:var(--c-text); }
#report-invest .card-body{ padding:16px 18px 18px; }

#report-invest .btn-primary{ background:var(--c-primary); border:0; color:#fff; font-weight:700; border-radius:var(--radius-md); padding:10px 14px; }
#report-invest .btn-default{ background:var(--c-surface); border:1px solid var(--c-border); color:var(--c-text); border-radius:var(--radius-md); padding:10px 14px; }

#report-invest .seg { display:inline-flex; gap:6px; padding:4px; border:1px solid var(--c-border); border-radius:999px; background: var(--c-surface); }
#report-invest .seg button{ border:0; padding:6px 10px; border-radius:999px; font-weight:600; color: var(--c-text); background: transparent; }
#report-invest .seg button.active{ background: var(--c-primary-weak); color: var(--c-primary); }

#report-invest .kpi-grid{ display:grid; grid-template-columns: repeat(4,1fr); gap:14px; }
#report-invest .kpi{ border:1px solid var(--c-border); border-radius:16px; padding:14px 16px; background:var(--c-surface); }
#report-invest .kpi p{ margin:0; color:var(--c-muted); font-size:12px; }
#report-invest .kpi h3{ margin:6px 0 0; font-weight:800; font-size:22px; }
#report-invest .badge-pill{ display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; }
#report-invest .pill-ok{ background:rgba(22,163,74,.12); color:var(--c-good); }
#report-invest .pill-warn{ background:rgba(245,158,11,.12); color:var(--c-warn); }

#report-invest table{ width:100%; border-collapse:separate; border-spacing:0; background:var(--c-panel); }
#report-invest thead th{ position:sticky; top:0; z-index:2; background:var(--c-table-head); color:var(--c-text); border-bottom:1px solid var(--c-border); padding:10px; font-weight:700; }
#report-invest tbody td{ border-bottom:1px solid var(--c-border); padding:10px; color:var(--c-text); }
#report-invest tbody tr:nth-child(odd){ background: rgba(127,127,127,.02); }
#report-invest tbody tr:hover{ background: rgba(127,127,127,.06); }

#report-invest .well{ background:var(--c-surface); border:1px solid var(--c-border); border-radius:12px; padding:12px 14px; color:var(--c-text); }

/* Table wrap for history */
#report-invest .table-wrap {
    border: 1px solid var(--c-border);
    border-radius: 12px;
    overflow: hidden;
    max-height: 400px; /* Limit height */
    overflow-y: auto;
}

/* Specific styles for breakdown table */
/* [MODIFIED] Adjust column widths after removing '明细' */
#report-invest .table-breakdown td:nth-child(1) { width: 70%; }
#report-invest .table-breakdown td:nth-child(2) { width: 30%; text-align: right; font-weight: 600; }
#report-invest .table-breakdown tr.total-row td {
    background: rgba(127,127,127,.05);
    font-weight: 700;
}
#report-invest .table-breakdown td.val-pos { color: var(--c-good); }
#report-invest .table-breakdown td.val-neg { color: var(--c-bad); }


@media (max-width:1200px){ #report-invest .kpi-grid{ grid-template-columns: repeat(2,1fr); } }
@media (max-width:768px){ #report-invest .kpi-grid{ grid-template-columns: repeat(1,1fr); } }
</style>

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
(function(){
  /* Theme */
  const pref = localStorage.getItem('cp_theme') || 'auto';
  applyTheme(pref); setSeg(pref);
  document.getElementById('theme-seg').addEventListener('click', function(e){
    if (e.target.tagName !== 'BUTTON') return;
    const mode = e.target.dataset.theme;
    localStorage.setItem('cp_theme', mode);
    applyTheme(mode); setSeg(mode);
  });
  function setSeg(mode){ [...document.querySelectorAll('#theme-seg button')].forEach(b=>b.classList.toggle('active', b.dataset.theme===mode)); }
  function applyTheme(mode){ const el=document.documentElement; if(mode==='auto') el.removeAttribute('data-theme'); else el.setAttribute('data-theme', mode); }

  /* [MODIFIED] Date */
  // 使用 PHP 注入的日期作为约束
  const MIN_DATE = '<?php echo $min_date; ?>';
  const MAX_DATE = '<?php echo $max_date; ?>';
  
  var start = moment().subtract(24, 'months').startOf('day');
  var end   = moment(MAX_DATE).endOf('day'); // 默认结束日期为今天

  // 确保默认开始日期不早于 min_date
  if (start.isBefore(MIN_DATE)) {
      start = moment(MIN_DATE).startOf('day');
  }

  function setLabel(s, e){
    var btn = document.getElementById('daterange-btn-investor');
    btn.dataset.start = s.format('YYYY-MM-DD');
    btn.dataset.end   = e.format('YYYY-MM-DD');
    btn.querySelector('span').innerHTML =
      '<i class="fa fa-calendar"></i> ' + s.format('YYYY-MM-DD') + ' ~ ' + e.format('YYYY-MM-DD');
  }

  $('#daterange-btn-investor').daterangepicker({
    startDate: start, 
    endDate: end,
    // [NEW] 需求 1: 添加 min/max 约束
    minDate: MIN_DATE,
    maxDate: MAX_DATE,
    
    // [MODIFIED] 需求 3: 添加 "所有日期"
    ranges: {
      '所有日期': [moment(MIN_DATE), moment(MAX_DATE)],
      '近12个月': [moment().startOf('month').subtract(11,'months'), moment(MAX_DATE).endOf('month')],
      '近24个月': [moment().startOf('month').subtract(23,'months'), moment(MAX_DATE).endOf('month')],
      '今年': [moment().startOf('year'), moment(MAX_DATE).endOf('year')]
    }
  }, setLabel);
  setLabel(start, end);


  function nf(x){ if (x==null || x==='—') return '—'; var v=Number(x); 
      // [FIX] 确保负数显示
      if (isNaN(v)) return '—'; return v.toLocaleString('en-US',{minimumFractionDigits:2, maximumFractionDigits:2}); 
  }
  function pf(x){ if (x==null || isNaN(x)) return '—'; return (x*100).toFixed(2)+'%'; }

  
  /**
   * Function to populate the recent transactions table
   */
  function fillRecentTxs(transactions, rangeStart, rangeEnd) {
      const $tbody = $('#tbl-recent-txs-body');
      const $title = $('#recent-txs-title'); // Select the title element
      $tbody.empty();
      
      $title.html('<i class="fa fa-history"></i> 投资/回报记录 (' + rangeStart + ' ~ ' + rangeEnd + ')'); // Dynamic Title Update
      
      if (!transactions || transactions.length === 0) {
          $tbody.html('<tr><td colspan="3" style="text-align:center; color:var(--c-muted);">所选范围内无相关记录</td></tr>');
          $('#card-recent-txs').show();
          return;
      }
      
      transactions.forEach(function(tx) {
          let category_label = tx.ss_fin_category;
          // Optional: simple translation map for readability
          const labels = {
              'investment': '投资',
              'investor_investment_out': '追加投资',
              'dividend_deduction': '分红抵扣',
              'dividend_cash': '现金分红',
              'salary_cash_z': '工资: 现金Z',
              'salary_cash_c': '工资: 现金C',
              'salary_bank_z': '工资: 银行Z',
              'salary_bank_c': '工资: 银行C',
              'total_dividend': '总分红(日营数据)' // 即使被移除，也确保流水显示正常
          };
          if (labels[tx.ss_fin_category]) {
              category_label = labels[tx.ss_fin_category];
          }
          
          const amount = parseFloat(tx.ss_fin_amount);
          let amount_color = 'var(--c-text)';
          if (amount < 0) {
              amount_color = 'var(--c-bad)'; // Negative (e.g., investment out)
          } else if (category_label.includes('工资') || category_label.includes('分红') || category_label.includes('追加投资')) {
              amount_color = 'var(--c-good)'; // Positive (returns/investments)
          }

          const tr = $('<tr/>');
          tr.append($('<td/>').text(tx.ss_fin_date));
          tr.append($('<td/>').text(category_label));
          tr.append($('<td/>').css({ 'color': amount_color, 'text-align': 'right' }).text(nf(amount)));
          $tbody.append(tr);
      });
      
      $('#card-recent-txs').show();
  }

  function load(){
    var btn = document.getElementById('daterange-btn-investor');
    var s = btn.dataset.start; var e = btn.dataset.end;
    var include_wage = $('#chk-include-wage').is(':checked') ? 1 : 0;

    $('#card-result, #kpi-row, #card-recent-txs').hide(); // Hide all results

    $.getJSON('<?php echo CP_BASE_URL; ?>som_report_investor_get_data', { start_date: s, end_date: e, include_wage: include_wage }, function(resp){
      if (!resp || !resp.success) { alert(resp && resp.message ? resp.message : '加载失败'); return; }
      var d = resp.data;
      
      // Get the actual range used in the query for breakdown/txs
      const actualRangeStart = d.range.start_date;
      const actualRangeEnd = d.range.end_date;

      $('#res-title').text('投资回报（' + actualRangeStart + ' ~ ' + actualRangeEnd + '）' + (include_wage ? '（含工资）' : '（不含工资）'));

      // 1. KPI Box 1 (总投资) - Uses Custom KPI Value (Full History)
      const totalPrincipalKpi = Number(d.summary.total_principal_kpi) || 0;
      // 2. Secondary KPI (总投资 - 总回报(不含工资)) (Full History)
      const secondaryKpiNet = Number(d.summary.secondary_kpi_net) || 0;

      $('#v-total-principal').text(nf(totalPrincipalKpi));
      $('#v-total-principal-net-comparison').text('净额: ' + nf(secondaryKpiNet)); // Request A.2
      
      // Update color based on principal Kpi value
      if (totalPrincipalKpi < 0) {
          $('#v-total-principal').css('color', 'var(--c-bad)');
      } else if (totalPrincipalKpi > 0) {
          $('#v-total-principal').css('color', 'var(--c-good)');
      } else {
          $('#v-total-principal').css('color', 'var(--c-text)');
      }
      
      // Update secondary color
      if (secondaryKpiNet < 0) {
          $('#v-total-principal-net-comparison').css('color', 'var(--c-bad)');
      } else if (secondaryKpiNet > 0) {
          $('#v-total-principal-net-comparison').css('color', 'var(--c-good)');
      } else {
          $('#v-total-principal-net-comparison').css('color', 'var(--c-muted)');
      }


      // Other KPI Boxes (Always Full History, using the new KPI summary keys)
      $('#v-total-returns-excl').text(nf(d.summary.total_returns_excl_kpi));
      $('#v-roi-excl').text(pf(d.summary.roi_excl_kpi));
      $('#v-annual-excl').text(pf(d.summary.annualized_excl_kpi));
      $('#v-roi-incl').text(pf(d.summary.roi_incl_kpi));
      $('#v-annual-incl').text(pf(d.summary.annualized_incl_kpi));

      // Info Table Updates
      // Combine dates for "统计日期" (Uses earliest transaction date)
      $('#v-start-end').text((d.summary.invest_start || actualRangeStart) + ' ~ ' + (d.summary.invest_end || actualRangeEnd));
      // Display Total months in the full period for consistency
      $('#v-invest-months').text(d.summary.invest_months); 
      // Display total returns excl wage (Full History) for matching KPI
      $('#v-pure-cash-dividend').text(nf(d.display.pure_dividend_cash));


      // Breakdown (Uses Filtered Data)
      // [修改] 只设置 Net 值
      $('#b-investor-investment-out-net').text(nf(d.breakdown.investor_investment_out.net)); 
      
      $('#b-dividend-deduction').text(nf(d.breakdown.dividend_deduction));

      // Fill return details (Filtered data)
      $('#b-dividend-cash').text(nf(d.breakdown.dividend_cash));
      $('#b-salary-cash-z').text(nf(d.breakdown.salary_cash_z));
      $('#b-salary-cash-c').text(nf(d.breakdown.salary_cash_c));
      $('#b-salary-bank-z').text(nf(d.breakdown.salary_bank_z));
      $('#b-salary-bank-c').text(nf(d.breakdown.salary_bank_c));

      // Calculate and fill salary totals
      const total_cash_salary = (Number(d.breakdown.salary_cash_z) || 0) + (Number(d.breakdown.salary_cash_c) || 0);
      const total_bank_salary = (Number(d.breakdown.salary_bank_z) || 0) + (Number(d.breakdown.salary_bank_c) || 0);
      $('#b-total-salary-cash').text(nf(total_cash_salary));
      $('#b-total-salary-bank').text(nf(total_bank_salary));

      // Fill recent transactions table using the filtered dates
      fillRecentTxs(d.recent_transactions, actualRangeStart, actualRangeEnd);

      $('#kpi-row, #card-result').show();
    });
  }

  $('#btn-go').on('click', load);
  $('#chk-include-wage').on('change', load);
  
  // [MODIFIED] Export CSV
  $('#btn-export-invest').on('click', function(){
    const rows = [];
    const kv = (k,v)=>rows.push([k,v].join(','));
    kv('总投资（含抵扣）',$('#v-total-principal').text());
    kv('净额(总投资-总回报不含工资)',$('#v-total-principal-net-comparison').text().replace('净额: ','')); // NEW LINE
    kv('总回报（不含工资）',$('#v-total-returns-excl').text());
    kv('ROI（不含）',$('#v-roi-excl').text());
    kv('年化（不含）',$('#v-annual-excl').text());
    kv('ROI（含）',$('#v-roi-incl').text());
    kv('年化（含）',$('#v-annual-incl').text());
    kv('统计日期',$('#v-start-end').text());
    kv('投资月数',$('#v-invest-months').text()); // NEW LABEL
    kv('展示：总回报（不含工资）',$('#v-pure-cash-dividend').text());

    rows.push('', '投资类,金额'); // MODIFIED HEADER
    rows.push('投资款(investor_investment_out),'+ $('#b-investor-investment-out-net').text()); // MODIFIED LINE
    rows.push('分红抵扣(dividend_deduction),'+ $('#b-dividend-deduction').text());


    rows.push('', '回报类,金额');
    rows.push('现金分红(dividend_cash),' + $('#b-dividend-cash').text());
    rows.push('工资：现金Z,' + $('#b-salary-cash-z').text());
    rows.push('工资：现金C,' + $('#b-salary-cash-c').text());
    rows.push('工资：银行Z,' + $('#b-salary-bank-z').text());
    rows.push('工资：银行C,' + $('#b-salary-bank-c').text());
    rows.push('总现金工资,' + $('#b-total-salary-cash').text());
    rows.push('总银行工资,' + $('#b-total-salary-bank').text());

    const csv = rows.join('\n');
    const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob); const a = document.createElement('a');
    a.href = url; a.download = 'investor_report.csv'; a.click(); URL.revokeObjectURL(url);
  });

  // auto
  load();
})();
</script>