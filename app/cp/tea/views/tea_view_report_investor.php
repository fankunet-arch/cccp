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

<style>
/* 样式与 som_view_report_investor.php 保持一致，确保了 UI 标准 */
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

/* 修正 KPI 布局为 3 栏 */
#report-invest .kpi-grid{ display:grid; grid-template-columns: repeat(3,1fr); gap:14px; } 
@media (max-width:768px){ #report-invest .kpi-grid{ grid-template-columns: repeat(1,1fr); } }

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
#report-invest .table-wrap {
    border: 1px solid var(--c-border);
    border-radius: 12px;
    overflow: hidden;
    max-height: 400px;
    overflow-y: auto;
}
/* 调整 breakdown table 列宽 */
#report-invest .table-breakdown td:nth-child(1) { width: 70%; }
#report-invest .table-breakdown td:nth-child(2) { width: 30%; text-align: right; font-weight: 600; }
#report-invest .table-breakdown tr.total-row td {
    background: rgba(127,127,127,.05);
    font-weight: 700;
}
#report-invest .table-breakdown td.val-pos { color: var(--c-good); }
#report-invest .table-breakdown td.val-neg { color: var(--c-bad); }

</style>

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
(function(){
  
  const API_ENDPOINT = '<?php echo CP_BASE_URL; ?>tea_report_investor_get_data';

  /* --- Theme initialization (Robust Rewrite) --- */
  function setSeg(mode){
      [...document.querySelectorAll('#theme-seg button')].forEach(b => b.classList.toggle('active', b.dataset.theme === mode));
  }
  function applyTheme(mode){
      const el = document.documentElement;
      if(mode === 'auto') {
          el.removeAttribute('data-theme');
      } else {
          el.setAttribute('data-theme', mode);
      }
  }

  const pref = localStorage.getItem('cp_theme') || 'auto';
  applyTheme(pref); setSeg(pref);
  
  /* --- Data Formatting Helpers --- */
  function nf(x){ 
      if (x==null || x==='—' || isNaN(x)) return '0.00'; 
      var v=Number(x); 
      return v.toLocaleString('en-US',{minimumFractionDigits:2, maximumFractionDigits:2}); 
  }
  function pf(x){ 
      if (x==null || isNaN(x) || !isFinite(x)) return '—'; 
      return (x*100).toFixed(2)+'%'; 
  }

  const LABELS = {
    'INVESTMENT_IN': '投资款 (入)',
    'INVESTMENT_OUT': '投资款 (出)',
    'DIVIDEND_CASH': '现金分红 (入)',
    'DIVIDEND_DEDUCTION': '分红抵扣 (出)',
    'PROJECT_EXPENSE': '项目支出 (出)',
    'RENT': '房租',
    'DEPOSIT': '押金',
    'SUPPLIES': '物料', // <<< ADDED
    'SHIPPING': '运输费', // <<< ADDED
    'EQUIPMENT': '设备',
    'TAX': '税费',
    'GESTOR': 'Gestor 费用',
    'DECORATION': '装修费'
  };


  /* --- Date range Calculation --- */
  const MIN_DATE = '<?php echo $min_date; ?>';
  const MAX_DATE = '<?php echo $max_date; ?>';
  
  const start_default = moment().subtract(24, 'months').startOf('day');
  const start_final = moment.max(start_default, moment(MIN_DATE).startOf('day'));
  const end_final = moment(MAX_DATE).endOf('day');


  function setLabel(s, e){
    var btn = document.getElementById('daterange-btn-tea-investor');
    btn.dataset.start = s.format('YYYY-MM-DD');
    btn.dataset.end   = e.format('YYYY-MM-DD');

    const isFullRange = s.isSame(moment(MIN_DATE).startOf('day')) && e.isSame(moment(MAX_DATE).endOf('day'));

    if (isFullRange) {
        btn.querySelector('span').innerHTML = '<i class="fa fa-calendar"></i> 所有日期 (' + s.format('YYYY-MM-DD') + ' ~ ' + e.format('YYYY-MM-DD') + ')';
    } else {
        btn.querySelector('span').innerHTML =
        '<i class="fa fa-calendar"></i> ' + s.format('YYYY-MM-DD') + ' ~ ' + e.format('YYYY-MM-DD');
    }
  }
  
  // ---------------------------------------------------
  // --- Core Application Logic (DOM Ready) ---
  // ---------------------------------------------------
  $(function(){
      
      // 1. Daterange Picker Initialization
      $('#daterange-btn-tea-investor').daterangepicker({
        startDate: start_final,
        endDate: end_final,
        minDate: MIN_DATE,
        maxDate: MAX_DATE,
        
        ranges: {
          '所有日期': [moment(MIN_DATE), moment(MAX_DATE)],
          '近12个月': [moment().startOf('month').subtract(11,'months'), moment(MAX_DATE).endOf('month')],
          '近24个月': [moment().startOf('month').subtract(23,'months'), moment(MAX_DATE).endOf('month')],
          '今年': [moment().startOf('year'), moment(MAX_DATE).endOf('year')]
        }
      }, setLabel);
      setLabel(start_final, end_final); // Set initial label immediately

      // 2. Event Binding
      document.getElementById('theme-seg').addEventListener('click', function(e){
        if (e.target.tagName !== 'BUTTON') return;
        const mode = e.target.dataset.theme;
        localStorage.setItem('cp_theme', mode);
        applyTheme(mode); setSeg(mode);
      });

      $('#btn-go').on('click', loadData);
      
      // 3. Initial Data Load
      loadData(); 

      // ---------------------------------------------------
      // --- Functional Definitions (Inside DOM Ready) ---
      // ---------------------------------------------------

      function loadData(){
        const btn = document.getElementById('daterange-btn-tea-investor');
        const s = btn.dataset.start;
        const e = btn.dataset.end;

        $('#card-result, #kpi-row, #card-recent-txs').hide();

        $.getJSON(API_ENDPOINT, { start_date: s, end_date: e }, function(resp){
          if (!resp || !resp.success) { 
              alert(resp && resp.message ? resp.message : '加载失败'); 
              return; 
          }
          
          const d = resp.data || {};
          const summary = d.summary || {}; 
          const range = d.range || {}; 
          
          const principal = Number(summary.total_principal_kpi) || 0;
          const returns = Number(summary.total_returns_kpi) || 0;
          const total_net_kpi = Number(summary.total_net_kpi) || 0;
          const net_return_period = Number(summary.total_net_return_breakdown) || 0;
          const totalExpenseFull = Number(summary.total_expense_full) || 0;
          const totalExpenseFiltered = Number(summary.total_expense_filtered) || 0;


          const startDisplay = range.start_date || '—';
          const endDisplay = range.end_date || '—';

          const actualRangeStart = summary.invest_start || startDisplay;
          const actualRangeEnd = summary.invest_end || endDisplay;

          $('#res-title').text('<tea> 投资回报明细（' + startDisplay + ' ~ ' + endDisplay + '）');


          // 1. Fill KPIs
          $('#v-total-principal').text(nf(principal));
          $('#v-net-kpi-comparison').text('总净回报: ' + nf(total_net_kpi));
          
          $('#v-total-returns').text(nf(returns));
          
          $('#v-roi').text(pf(summary.roi_kpi));
          $('#v-annual').text(pf(summary.annualized_kpi));
          
          
          // Set KPI colors (Ensuring zero values are neutral/muted)
          const totalPrincipal = Number(summary.total_principal_kpi) || 0;
          const netKpi = Number(summary.total_net_kpi) || 0;
          const totalReturns = Number(summary.total_returns_kpi) || 0;
          const roiKpi = Number(summary.roi_kpi) || 0;
          const annualizedKpi = Number(summary.annualized_kpi) || 0;
            
          // 1. Total Principal (总投资)
          $('#v-total-principal').css('color', '');
          if (totalPrincipal > 0) {
              $('#v-total-principal').css('color', 'var(--c-good)');
          } else if (totalPrincipal < 0) {
              $('#v-total-principal').css('color', 'var(--c-bad)'); 
          } else {
              $('#v-total-principal').css('color', 'var(--c-text)');
          }
            
          // 2. Total Net KPI (总净回报 - comparison)
          $('#v-net-kpi-comparison').css('color', '');
          if (netKpi > 0) {
              $('#v-net-kpi-comparison').css('color', 'var(--c-good)');
          } else if (netKpi < 0) {
              $('#v-net-kpi-comparison').css('color', 'var(--c-bad)');
          } else {
              $('#v-net-kpi-comparison').css('color', 'var(--c-muted)');
          }
            
          // 3. Total Returns (总回报)
          $('#v-total-returns').css('color', '');
          if (totalReturns > 0) {
              $('#v-total-returns').css('color', 'var(--c-good)');
          } else if (totalReturns < 0) {
              $('#v-total-returns').css('color', 'var(--c-bad)');
          } else {
              $('#v-total-returns').css('color', 'var(--c-text)');
          }
            
          // 4. ROI (ROI)
          $('#v-roi').css('color', '');
          if (roiKpi > 0) {
              $('#v-roi').css('color', 'var(--c-good)');
          } else if (roiKpi < 0) {
              $('#v-roi').css('color', 'var(--c-bad)');
          } else {
              $('#v-roi').css('color', 'var(--c-text)');
          }

          // 5. Annualized Pill (年化)
          $('#v-annual').removeClass('pill-ok pill-warn');
          if (annualizedKpi > 0) {
              $('#v-annual').addClass('pill-ok'); 
          } else {
              $('#v-annual').addClass('pill-warn'); 
          }


          // 2. Fill Info Table
          $('#v-start-end').text((actualRangeStart || '—') + ' ~ ' + (actualRangeEnd || '—'));
          $('#v-invest-months').text(summary.invest_months || '—'); 
          $('#v-net-return-period').text(nf(net_return_period)).removeClass('pill-ok pill-warn').addClass(net_return_period >= 0 ? 'pill-ok' : 'pill-warn');
          
          // Fill Total Expense Metrics
          $('#v-total-expense-full').text(nf(totalExpenseFull));
          $('#v-total-expense-filtered').text(nf(totalExpenseFiltered));


          // 3. Fill Breakdown Table (Filtered data)
          fillBreakdownTbl(d.breakdown);
          $('#b-total-net').text(nf(net_return_period));

          // 4. Fill recent transactions table (Filtered data)
          fillRecentTxs(d.recent_transactions, startDisplay, endDisplay);

          $('#kpi-row, #card-result').show(); 
        }).fail(function(xhr) {
             console.error("AJAX Error:", xhr.statusText, xhr.responseText);
             alert("服务器请求失败或返回格式错误，请检查后端日志。"); 
             $('#kpi-row, #card-result').hide(); 
        });
      }
      
      /**
       * Populate the recent transactions table
       */
      function fillRecentTxs(transactions, rangeStart, rangeEnd) {
          const $tbody = $('#tbl-recent-txs-body');
          const $title = $('#recent-txs-title');
          $tbody.empty();
          
          $title.html('<i class="fa fa-history"></i> 投资交易记录 (' + rangeStart + ' ~ ' + rangeEnd + ')');
          
          if (!transactions || transactions.length === 0) {
              $tbody.html('<tr><td colspan="6" style="text-align:center; color:var(--c-muted);">所选范围内无相关交易记录</td></tr>');
              $('#card-recent-txs').show();
              return;
          }
          
          transactions.forEach(function(tx) {
              const category_label = LABELS[tx.tea_type] || tx.tea_type;
              const amount = tx.tea_amount; // Now signed base currency (EUR)
              const currency = tx.tea_currency;
              const rate = tx.tea_exchange_rate;
              const is_equity = tx.tea_is_equity;
              
              let amount_color = 'var(--c-text)';
              if (amount > 0) {
                  amount_color = 'var(--c-good)'; 
              } else if (amount < 0) {
                  amount_color = 'var(--c-bad)'; 
              }

              const rate_display = (rate && rate !== 1.0) ? ' @' + rate.toFixed(4) : '';
              const amount_str = nf(amount) + ' EUR (' + tx.tea_currency + rate_display + ')';
              const is_equity_str = is_equity ? '是' : '否';

              const editUrl = '<?php echo CP_BASE_URL; ?>tea_add&id=' + tx.tea_fin_id;

              const dateCell = $('<td>').append($('<a>')
                  .attr('href', editUrl)
                  .css({ 'text-decoration': 'underline', 'font-weight': '600', 'color': 'var(--c-primary)' })
                  .text(tx.tea_date));


              const tr = $('<tr/>');
              tr.append(dateCell); 
              tr.append($('<td/>').text(tx.tea_store || '—'));
              tr.append($('<td/>').text(category_label));
              tr.append($('<td/>').css({ 'color': amount_color, 'text-align': 'right', 'font-weight': 600 }).text(amount_str)); 
              tr.append($('<td/>').text(is_equity_str));
              tr.append($('<td/>').text(tx.tea_notes || '—'));
              $tbody.append(tr);
          });
          
          $('#card-recent-txs').show();
      }

      /**
       * Populate the breakdown table
       */
      function fillBreakdownTbl(breakdownData) {
          breakdownData = breakdownData || {};
          const $tbody = $('#tbl-breakdown-body'); $tbody.empty();
          
          const positiveItems = [];
          const negativeItems = [];
          
          for (const [key, value] of Object.entries(breakdownData)) {
              const amount = Number(value);
              if (amount === 0) continue; 
              
              const item = {
                  label: LABELS[key] || key,
                  amount: amount
              };
              
              if (amount > 0) {
                  positiveItems.push(item);
              } else {
                  negativeItems.push(item);
              }
          }
          
          // Sort and display returns first (positive values)
          if (positiveItems.length > 0) {
              $tbody.append($('<tr><td colspan="2" style="font-weight:700; background:rgba(22,163,74,.05);">收入/回报类 (+)</td></tr>'));
              // 按照金额降序排序
              positiveItems.sort((a, b) => b.amount - a.amount).forEach(item => {
                  const tr = $('<tr/>');
                  tr.append($('<td/>').text(item.label));
                  tr.append($('<td/>').addClass('val-pos').text(nf(item.amount)));
                  $tbody.append(tr);
              });
          }
          
          // Sort and display expenses (negative values)
          if (negativeItems.length > 0) {
              $tbody.append($('<tr><td colspan="2" style="font-weight:700; background:rgba(239,68,68,.05); border-top: 1px solid var(--c-border);">支出/投资类 (-)</td></tr>'));
              // 按照金额升序排序（即绝对值降序）
              negativeItems.sort((a, b) => a.amount - b.amount).forEach(item => {
                  const tr = $('<tr/>');
                  tr.append($('<td/>').text(item.label));
                  tr.append($('<td/>').addClass('val-neg').text(nf(item.amount)));
                  $tbody.append(tr);
              });
          }
      }

      // [MODIFIED] Export CSV logic
      $('#btn-export-invest').off('click').on('click', function(){
        const rows = [];
        const kv = (k,v)=>rows.push([k,v].join(','));
        
        kv('总投资（计股本金）',$('#v-total-principal').text());
        kv('总净回报（全周期）',$('#v-net-kpi-comparison').text().replace('总净回报: ','')); 
        kv('总回报（现金分红）',$('#v-total-returns').text());
        kv('ROI',$('#v-roi').text());
        kv('年化',$('#v-annual').text());
        kv('统计日期',$('#v-start-end').text());
        kv('投资周期（月）',$('#v-invest-months').text());
        kv('交易净回报（筛选期）',$('#v-net-return-period').text());
        kv('总支出（全周期）',$('#v-total-expense-full').text());
        kv('总支出（筛选期）',$('#v-total-expense-filtered').text());


        rows.push('', '交易分类汇总,金额');
        $('#tbl-breakdown-body tr').each(function(){
          const cols = []; $(this).find('td').each(function(){ 
              const text = $(this).text().trim();
              cols.push(text.replace(/,/g,'')); 
          });
          if (cols.length === 2 && cols[0].indexOf('收入/回报类') === -1 && cols[0].indexOf('支出/投资类') === -1) {
              rows.push(cols.join(','));
          }
        });
        rows.push('总交易净额,' + $('#b-total-net').text());

        rows.push('', '交易记录');
        rows.push('日期,店铺,类型,金额(EUR),原币种,汇率,是否计股,备注');
        $('#tbl-recent-txs-body tr').each(function(){
            const cols = [];
            // Skip header/summary row for the actual data parsing
            if ($(this).find('td').length === 1) return;

            $(this).find('td').each(function(i){
                let text = $(this).text().trim();
                
                if (i === 3) {
                    // Extract Base Amount (EUR)
                    const baseMatch = text.match(/([\d.,-]+)\sEUR/); 
                    cols.push(baseMatch ? baseMatch[1].replace(/,/g,'') : text.replace(/,/g,''));
                    
                    // Extract Original Currency and Rate from parentheses
                    const currencyRateMatch = text.match(/\(([\w]+)\s?@?([\d.]+)?\)/); 
                    
                    cols.push(currencyRateMatch ? currencyRateMatch[1] : 'EUR'); // Original Currency
                    cols.push(currencyRateMatch && currencyRateMatch[2] ? currencyRateMatch[2] : '1.0000'); // Rate
                } else {
                    cols.push(text.replace(/,/g,''));
                }
            });
            
            if (cols.length >= 6) { 
                rows.push(cols.join(','));
            }
        });

        const csv = rows.join('\n');
        const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
        const url = URL.createObjectURL(blob); const a = document.createElement('a');
        a.href = url; a.download = 'tea_investor_report.csv'; a.click(); URL.revokeObjectURL(url);
      });
  });

})();
</script>