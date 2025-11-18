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

    $.getJSON(CP_BASE_URL + 'som_report_investor_get_data', { start_date: s, end_date: e, include_wage: include_wage }, function(resp){
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