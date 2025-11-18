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