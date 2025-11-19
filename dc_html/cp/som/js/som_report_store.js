(function(){
  /* ===== Theme handling ===== */
  const pref = localStorage.getItem('cp_theme') || 'auto';
  applyTheme(pref); setSeg(pref);
  document.getElementById('theme-seg').addEventListener('click', function(e){
    if (e.target.tagName !== 'BUTTON') return;
    const mode = e.target.dataset.theme;
    localStorage.setItem('cp_theme', mode);
    applyTheme(mode); setSeg(mode);
  });
  function setSeg(mode){
    [...document.querySelectorAll('#theme-seg button')].forEach(b=>b.classList.toggle('active', b.dataset.theme===mode));
  }
  function applyTheme(mode){
    const el = document.documentElement;
    if (mode === 'auto') el.removeAttribute('data-theme');
    else el.setAttribute('data-theme', mode);
  }

  /* ===== Date range init ===== */
  var start = moment().startOf('month').subtract(11, 'months');
  var end   = moment().endOf('month');

  function setLabel(s, e){
    const btn = document.getElementById('daterange-btn');
    btn.dataset.start = s.format('YYYY-MM-DD');
    btn.dataset.end   = e.format('YYYY-MM-DD');
    btn.querySelector('span').innerHTML =
      '<i class="fa fa-calendar"></i> ' + s.format('YYYY-MM-DD') + ' ~ ' + e.format('YYYY-MM-DD');
  }

  $('#daterange-btn').daterangepicker({
    startDate: start, endDate: end,
    ranges: {
      '最近12个月': [moment().startOf('month').subtract(11, 'months'), moment().endOf('month')],
      '今年': [moment().startOf('year'), moment().endOf('year')],
      '去年': [moment().subtract(1,'year').startOf('year'), moment().subtract(1,'year').endOf('year')]
    }
  }, setLabel);
  setLabel(start, end);

  $('#btn-last12').on('click', function(){
    start = moment().startOf('month').subtract(11, 'months'); end = moment().endOf('month');
    $('#daterange-btn').data('daterangepicker').setStartDate(start);
    $('#daterange-btn').data('daterangepicker').setEndDate(end);
    setLabel(start, end);
  });

  /* ===== Table & KPI Logic ===== */
  function fmt(n){ if (n==null || n==='—' || isNaN(n) || n == 0) return '0.00'; let v=Number(n); return v.toLocaleString('en-US',{minimumFractionDigits:2, maximumFractionDigits:2}); }
  function fmtInt(n){ if (n==null || isNaN(n)) return '0'; let v=Number(n); return v.toLocaleString('en-US'); }
  function fmtPerc(n) { if (n==null || isNaN(n) || !isFinite(n) || n == 0) return '0.0%'; return (n * 100).toFixed(1) + '%'; }

  window.__lastDailyRows = [];
  window.__lastSalaries = [];

  /**
   * Aggregates daily data AND merges monthly salary data.
   * Returns data structured for BOTH tables.
   * [UPDATED] Sorts DESCENDING (Newest Month First)
   */
  function aggregateAndMerge(dailyRows, monthlySalaries) {
      const months = {};

      // 1. Create Salary Map for easy lookup (Used for breakdown table only)
      const salaryMap = {};
      monthlySalaries.forEach(s => {
          salaryMap[s.salary_month] = {
              sushi: parseFloat(s.ss_ms_sushi_salary) || 0,
              kitchen: parseFloat(s.ss_ms_kitchen_salary) || 0,
              waitstaff: parseFloat(s.ss_ms_waitstaff_salary) || 0,
              total: (parseFloat(s.ss_ms_sushi_salary) || 0) +
                     (parseFloat(s.ss_ms_kitchen_salary) || 0) +
                     (parseFloat(s.ss_ms_waitstaff_salary) || 0)
          };
      });

      // 2. Aggregate Daily Operations
      dailyRows.forEach(r => {
          const mkey = r.month;
          if (!months[mkey]) {
              months[mkey] = {
                  month: mkey,
                  people: 0,
                  cash_income: 0,
                  bank_income: 0,
                  total_income: 0,
                  cash_expense: 0,     // Operational (Already includes Salary per user requirement)
                  bank_expense: 0,     // Operational (Already includes Salary per user requirement)
                  total_expense: 0,    // Operational
                  net: 0,              // Operational Net (Income - Expense)
                  monthly_dividend_total: 0,
                  avg_spend_numerator: 0,
                  avg_spend_denominator: 0
              };
          }

          months[mkey].people += r.people;
          months[mkey].cash_income += r.cash_income;
          months[mkey].bank_income += r.bank_income;
          months[mkey].total_income += r.total_income;
          months[mkey].cash_expense += r.cash_expense;
          months[mkey].bank_expense += r.bank_expense;
          months[mkey].total_expense += r.total_expense;
          months[mkey].net += r.net;
          months[mkey].monthly_dividend_total += r.monthly_dividend_total;
          months[mkey].avg_spend_numerator += r.avg_spend_numerator;
          months[mkey].avg_spend_denominator += r.avg_spend_denominator;
      });

      // 3. Convert to array and sort DESCENDING (b - a)
      const sortedMonths = Object.values(months).sort((a, b) => b.month.localeCompare(a.month));

      // 4. Final processing
      const tableData = [];
      const salaryTableData = [];

      sortedMonths.forEach(m => {
          const avg_spend = (m.avg_spend_denominator > 0) ? (m.avg_spend_numerator / m.avg_spend_denominator) : null;

          // Get salary data for breakdown (display only)
          const salaryData = salaryMap[m.month] || { sushi: 0, kitchen: 0, waitstaff: 0, total: 0 };
          const totalSalaryBreakdown = salaryData.total;

          // [LOGIC FIX]:
          // User confirmed that daily expenses ALREADY include salary.
          // Therefore, final_total_expense IS simply m.total_expense.
          // And final_net IS simply m.net (Income - Expense).
          // We do NOT add/subtract totalSalary again.
          const final_total_expense = m.total_expense;
          const final_net = m.net;

          // Data for Main Table
          tableData.push({
              month: m.month,
              people: m.people,
              cash_income: m.cash_income.toFixed(2),
              bank_income: m.bank_income.toFixed(2),
              total_income: m.total_income.toFixed(2),
              cash_expense: m.cash_expense.toFixed(2),
              bank_expense: m.bank_expense.toFixed(2),
              total_expense: final_total_expense.toFixed(2),
              avg_spend: (avg_spend === null) ? '—' : avg_spend.toFixed(2),
              monthly_dividend_total: m.monthly_dividend_total.toFixed(2),
              net: final_net.toFixed(2)
          });

          // Data for Salary Table
          salaryTableData.push({
              month: m.month,
              sushi: salaryData.sushi,
              sushi_pct: (totalSalaryBreakdown > 0) ? (salaryData.sushi / totalSalaryBreakdown) : 0,
              kitchen: salaryData.kitchen,
              kitchen_pct: (totalSalaryBreakdown > 0) ? (salaryData.kitchen / totalSalaryBreakdown) : 0,
              waitstaff: salaryData.waitstaff,
              waitstaff_pct: (totalSalaryBreakdown > 0) ? (salaryData.waitstaff / totalSalaryBreakdown) : 0,
              total_salary: totalSalaryBreakdown,
              pct_of_expense: (final_total_expense > 0) ? (totalSalaryBreakdown / final_total_expense) : 0,
              pct_of_income: (m.total_income > 0) ? (totalSalaryBreakdown / m.total_income) : 0
          });
      });

      return { tableData, salaryTableData };
  }

  /**
   * [NEW] Builds the Same Period Comparison data
   * Logic: Finds the day number of the LAST record in the DB (e.g., 14th).
   * Then sums up 1st-14th for every month in the list.
   */
  function buildComparisonData(dailyRows) {
      if (!dailyRows || dailyRows.length === 0) return [];

      // 1. Find the cutoff day from the very last record
      const lastRecord = dailyRows[dailyRows.length - 1];
      const lastDate = moment(lastRecord.date);
      const cutoffDay = lastDate.date(); // e.g., 10

      $('#comparison-title-date').text('(截至每月 ' + cutoffDay + ' 日)');

      // 2. Group aggregation
      const months = {};
      dailyRows.forEach(r => {
          const rowDate = moment(r.date);
          const dayOfRow = rowDate.date();

          // Only include if day is within range [1, cutoffDay]
          if (dayOfRow <= cutoffDay) {
              const mkey = r.month;
              if (!months[mkey]) {
                  months[mkey] = {
                      month: mkey,
                      count_days: 0,
                      people: 0,
                      total_income: 0,
                      // Daily Net already includes salary expenses
                      net_op: 0,
                      avg_spend_numerator: 0,
                      avg_spend_denominator: 0
                  };
              }
              months[mkey].count_days++;
              months[mkey].people += r.people;
              months[mkey].total_income += r.total_income;
              months[mkey].net_op += r.net;
              months[mkey].avg_spend_numerator += r.avg_spend_numerator;
              months[mkey].avg_spend_denominator += r.avg_spend_denominator;
          }
      });

      // 3. Convert to array and Sort DESCENDING
      const sorted = Object.values(months).sort((a, b) => b.month.localeCompare(a.month));

      // 4. Format
      return sorted.map(m => {
          const avg = (m.avg_spend_denominator > 0) ? (m.avg_spend_numerator / m.avg_spend_denominator) : 0;
          return {
              month: m.month,
              count_days: m.count_days,
              people: m.people,
              total_income: m.total_income,
              avg_spend: avg,
              net_op: m.net_op
          };
      });
  }

  /**
   * Helper function to calculate stats for a specific period from daily rows
   */
  function calculatePeriodStats(dailyRows, startDate, endDate) {
      let stats = {
          total_income: 0,
          people: 0,
          avg_spend_numerator: 0,
          avg_spend_denominator: 0,
          monthly_dividend_total: 0,
          net: 0
      };

      dailyRows.forEach(r => {
          if (r.date >= startDate && r.date <= endDate) {
              stats.total_income += r.total_income;
              stats.people += r.people;
              stats.avg_spend_numerator += r.avg_spend_numerator;
              stats.avg_spend_denominator += r.avg_spend_denominator;
              stats.monthly_dividend_total += r.monthly_dividend_total;
              stats.net += r.net; // Daily net already accounts for all expenses (incl salary)
          }
      });

      stats.avg_spend = (stats.avg_spend_denominator > 0) ? (stats.avg_spend_numerator / stats.avg_spend_denominator) : null;
      return stats;
  }

  /**
   * fillKPIs
   */
  function fillKPIs(dailyRows, monthlySalaries) {
    if (!dailyRows || dailyRows.length === 0) {
        $('#kpis').hide();
        return;
    }

    const latestDateStr = dailyRows[dailyRows.length - 1].date;
    const latestDate = moment(latestDateStr);
    const dayOfMonth = latestDate.date();
    const daysInMonth = latestDate.daysInMonth();
    const isMonthComplete = (dayOfMonth === daysInMonth);

    const currentMonthMoment = latestDate.clone();
    const prevMonthMoment = latestDate.clone().subtract(1, 'month');
    const prevMonthDays = prevMonthMoment.daysInMonth();

    let currentMonthStart, currentMonthEnd, prevMonthStart, prevMonthEnd, comparisonLabel;

    if (isMonthComplete) {
        currentMonthStart = currentMonthMoment.startOf('month').format('YYYY-MM-DD');
        currentMonthEnd = latestDateStr;
        prevMonthStart = prevMonthMoment.startOf('month').format('YYYY-MM-DD');
        prevMonthEnd = prevMonthMoment.endOf('month').format('YYYY-MM-DD');
        comparisonLabel = `上月 (${prevMonthMoment.format('YYYY-MM')}): `;
    } else {
        currentMonthStart = currentMonthMoment.startOf('month').format('YYYY-MM-DD');
        currentMonthEnd = latestDateStr;
        prevMonthStart = prevMonthMoment.startOf('month').format('YYYY-MM-DD');
        const prevMonthEndDay = Math.min(dayOfMonth, prevMonthDays);
        prevMonthEnd = prevMonthMoment.date(prevMonthEndDay).format('YYYY-MM-DD');
        comparisonLabel = `上月同期 (${prevMonthStart} ~ ${prevMonthEnd}): `;
    }

    const currentStats = calculatePeriodStats(dailyRows, currentMonthStart, currentMonthEnd);
    const prevStats = calculatePeriodStats(dailyRows, prevMonthStart, prevMonthEnd);

    // [FIX] No need to subtract salary manually, as daily expenses already include it.
    const finalCurrentNet = currentStats.net;
    const finalPrevNet = prevStats.net;

    $('#kpi-total-rev').text(fmt(currentStats.total_income));
    $('#kpi-total-rev-comp').text(comparisonLabel + fmt(prevStats.total_income));

    $('#kpi-people').text(fmtInt(currentStats.people));
    $('#kpi-people-comp').text(comparisonLabel.split('(')[0] + fmtInt(prevStats.people));

    $('#kpi-avg').text(fmt(currentStats.avg_spend));
    $('#kpi-avg-comp').text(comparisonLabel.split('(')[0] + fmt(prevStats.avg_spend));

    $('#kpi-dividend').text(fmt(currentStats.monthly_dividend_total));
    $('#kpi-dividend-comp').text(comparisonLabel.split('(')[0] + fmt(prevStats.monthly_dividend_total));

    $('#kpi-net').text(fmt(finalCurrentNet));
    $('#kpi-net-comp').text(comparisonLabel.split('(')[0] + fmt(finalPrevNet));
    $('#kpi-net').css('color', finalCurrentNet >= 0 ? 'var(--c-good)' : 'var(--c-bad)');

    $('#kpis').show();
  }

  function fillTable(tableData){
    const $tbody = $('#tbl-monthly tbody'); $tbody.empty();

    tableData.forEach(function(r){
      const tr = $('<tr/>');
      const net = Number(r.net||0);
      tr.append($('<td/>').text(r.month));
      tr.append($('<td/>').text(fmtInt(r.people)));
      tr.append($('<td/>').text(fmt(r.cash_income)));
      tr.append($('<td/>').text(fmt(r.bank_income)));
      tr.append($('<td/>').text(fmt(r.total_income)));
      tr.append($('<td/>').text(fmt(r.cash_expense)));
      tr.append($('<td/>').text(fmt(r.bank_expense)));
      tr.append($('<td/>').text(fmt(r.total_expense)));
      tr.append($('<td/>').text(r.avg_spend));
      tr.append($('<td/>').text(fmt(r.monthly_dividend_total)));
      const badge = $('<span class="td-badge"/>').addClass(net>=0?'badge-ok':'badge-bad').text(fmt(net));
      tr.append($('<td/>').append(badge));
      $tbody.append(tr);
    });
  }

  /**
   * [NEW] Fills the Same Period Comparison Table
   */
  function fillComparisonTable(compData) {
      const $tbody = $('#tbl-comparison tbody'); $tbody.empty();
      if (!compData || compData.length === 0) {
          $tbody.html('<tr><td colspan="6" style="text-align:center; color:var(--c-muted);">无数据</td></tr>');
          return;
      }

      compData.forEach(function(r){
          const tr = $('<tr/>');
          const net = Number(r.net_op||0);

          tr.append($('<td/>').text(r.month));
          tr.append($('<td/>').text(r.count_days + ' 天'));
          tr.append($('<td/>').text(fmtInt(r.people)));
          tr.append($('<td/>').text(fmt(r.total_income)));
          tr.append($('<td/>').text(fmt(r.avg_spend)));

          const badge = $('<span class="td-badge"/>')
                .addClass(net>=0 ? 'badge-ok' : 'badge-bad')
                .text(fmt(net));
          tr.append($('<td/>').append(badge));

          $tbody.append(tr);
      });
  }

  function fillSalaryTable(salaryTableData) {
      const $tbody = $('#tbl-salary-summary tbody'); $tbody.empty();
      if (!salaryTableData || salaryTableData.length === 0) {
          $tbody.html('<tr><td colspan="10" style="text-align:center; color:var(--c-muted);">所选范围内无工资数据</td></tr>');
          return;
      }

      salaryTableData.forEach(function(r) {
          const tr = $('<tr/>');
          tr.append($('<td/>').text(r.month));
          tr.append($('<td/>').text(fmt(r.sushi)));
          tr.append($('<td/>').addClass('salary-perc').text(fmtPerc(r.sushi_pct)));
          tr.append($('<td/>').text(fmt(r.kitchen)));
          tr.append($('<td/>').addClass('salary-perc').text(fmtPerc(r.kitchen_pct)));
          tr.append($('<td/>').text(fmt(r.waitstaff)));
          tr.append($('<td/>').addClass('salary-perc').text(fmtPerc(r.waitstaff_pct)));
          tr.append($('<td/>').css('font-weight', 700).text(fmt(r.total_salary)));
          tr.append($('<td/>').addClass('salary-perc').text(fmtPerc(r.pct_of_expense)));
          tr.append($('<td/>').addClass('salary-perc').text(fmtPerc(r.pct_of_income)));
          $tbody.append(tr);
      });
  }

  function loadData(){
    const s = document.getElementById('daterange-btn').dataset.start;
    const e = document.getElementById('daterange-btn').dataset.end;
    $('#report-card, #salary-card, #kpis, #comparison-card').hide();

    $.getJSON(CP_BASE_URL + 'som_report_store_get_data', { start_date: s, end_date: e }, function(resp){
      if (!resp || !resp.success || !resp.data.daily_rows) {
          alert(resp && resp.message ? resp.message : '加载失败');
          return;
      }

      window.__lastDailyRows = resp.data.daily_rows;
      window.__lastSalaries = resp.data.monthly_salaries || [];

      // 1. Aggregate daily data AND merge salaries (Main Monthly Table)
      // Note: aggregateAndMerge now sorts DESCENDING internally
      const { tableData, salaryTableData } = aggregateAndMerge(window.__lastDailyRows, window.__lastSalaries);

      // 2. Build Same Period Comparison Data (New Table)
      const compData = buildComparisonData(window.__lastDailyRows);

      // 3. Fill Tables
      fillTable(tableData);
      fillComparisonTable(compData);
      fillSalaryTable(salaryTableData);

      // 4. Fill KPIs
      fillKPIs(window.__lastDailyRows, window.__lastSalaries);

      $('#report-title').text('店铺报表（' + s + ' ~ ' + e + '）');
      $('#report-card, #comparison-card, #salary-card').show();
    });
  }

  $('#btn-generate').on('click', loadData);
  $('#btn-export').on('click', function(){
    const head = []; $('#tbl-monthly thead th').each(function(){ head.push($(this).text().trim()); });
    let csv = head.join(',') + '\n';
    $('#tbl-monthly tbody tr').each(function(){
      const cols = []; $(this).find('td').each(function(){ cols.push($(this).text().trim().replace(/,/g,'')); });
      csv += cols.join(',') + '\n';
    });
    const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob); const a = document.createElement('a');
    a.href = url; a.download = 'store_report_monthly.csv'; a.click(); URL.revokeObjectURL(url);
  });

  // auto
  loadData();
})();