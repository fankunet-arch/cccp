<?php
// /app/cp/views/som_view_add.php
// (最终重构: 实现紧凑的多列布局，解决页面空旷和样式出错问题)

declare(strict_types=1);

// [MODIFIED] 引入 $pdo
global $pdo;
// 强制加载 bootstrap 以确保 $pdo 全局变量可用
if (!isset($pdo)) {
    require_once dirname(dirname(__DIR__)) . '/bootstrap.php';
    global $pdo;
}

// 检查是否有来自 save action 的反馈信息
$feedback_message = '';
if (isset($_SESSION['sushisom_feedback'])) {
    $feedback = $_SESSION['sushisom_feedback'];
    $alert_type = $feedback['type'] === 'success' ? 'alert-success' : 'alert-danger';
    $icon = $feedback['type'] === 'success' ? 'check' : 'ban';
    
    $feedback_message = <<<HTML
    <div id="feedback-bar" class="feedback-bar {$feedback['type']}">
        <i class="fas fa-{$icon} me-2"></i> {$feedback['message']}
    </div>
HTML;
    unset($_SESSION['sushisom_feedback']);
}

// 确定默认日期：取数据库中最后一条记录的下一天；无记录时回退为今天
$default_date = date('Y-m-d');
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT MAX(ss_daily_date) AS last_date FROM sushisom_daily_operations");
        $stmt->execute();
        $last_date = $stmt->fetchColumn();
        if ($last_date) {
            $default_date = (new DateTimeImmutable($last_date))->modify('+1 day')->format('Y-m-d');
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch last date: " . $e->getMessage());
    }
}

// 获取数据库中的最早日期，并设置今天为最大日期（保留原逻辑）
$min_date = '2024-06-01'; // Fallback
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT MIN(ss_daily_date) as min_date FROM sushisom_daily_operations");
        $stmt->execute();
        $db_min_date = $stmt->fetchColumn();
        if ($db_min_date) {
            $min_date = $db_min_date; 
        }
    } catch (PDOException $e) {
        error_log("Failed to fetch minimum date: " . $e->getMessage());
    }
}
$today = date('Y-m-d');

// 静态映射：专项财务字段到表单名称
$static_financial_fields = [
    'total_dividend'       => '总分红',
    'dividend_cash'        => '现金分红',
    'salary_bank_z'        => '工资银行Z',
    'salary_bank_c'        => '工资银行C',
    'salary_cash_z'        => '工资现金Z',
    'salary_cash_c'        => '工资现金C',
    'dividend_deduction'   => '分红抵扣', // 必须存在，以兼容旧数据和保存逻辑
    'project_payment'      => '工程款',
    'investment'           => '投资款',
];

// 定义紧凑字段渲染函数
function render_input_unit(string $label, string $name, string $type = 'text', string $value = '0.00', string $placeholder = '0.00', bool $required = false, string $min_date = '', string $max_date = ''): string {
    $req = $required ? 'required' : '';
    $min_attr = '';
    $max_attr = '';
    $custom_class = '';
    $input_type_modifier = '';

    if ($type === 'date') { 
        $placeholder = ''; 
        // [MODIFIED] Request 1: 添加日期全行样式类
        $custom_class = ' date-field-full-row'; 
        if ($min_date) {
            $min_attr = "min=\"{$min_date}\"";
        }
        if ($max_date) {
            $max_attr = "max=\"{$max_date}\"";
        }
    }
    
    // [MODIFIED] Request 4: Match fields default to empty string, not '0.00'
    else if (strpos($name, 'match_') !== false || $name === 'match_time') { 
        $placeholder = ($name === 'match_time' ? '比赛时间 (如 19:00)' : '比赛名称 (可选)'); 
        $value = ''; // 初始值为空
    } 
    // [MODIFIED] Request 3: Handle all monetary text inputs for JS
    else if ($type === 'text' && !empty($label) && $label !== '球赛' && $label !== '比赛时间') {
        // [MODIFIED] Request 3: Add attribute for real-time decimal control
        $input_type_modifier .= ' data-is-money="true"';
        // 如果初始值是 '0'，统一显示为 '0.00'
        $value = (string)($value === '0' || $value === '0.00' ? '0.00' : $value);
    } 
    // [MODIFIED] Request 1: 强制人数输入为整数，限制3位，并添加跳转标识
    else if ($type === 'number') { 
        // [MODIFIED] Request 1: 强制整数，最大长度 3，用于 JS 识别和跳转
        $input_type_modifier .= ' step="1" maxlength="3" data-is-count="true"'; 
        $placeholder = '0'; 
        $value = (string)($value === '0.00' ? '0' : $value); 
    }

    // 返回带 compact-field-unit 样式的单个字段
    return <<<HTML
    <div class="compact-field-unit{$custom_class}">
        <label for="{$name}">{$label}</label>
        <input type="{$type}" class="form-control" id="{$name}" name="{$name}" placeholder="{$placeholder}" value="{$value}" {$req} {$min_attr} {$max_attr}{$input_type_modifier}>
    </div>
HTML;
}

?>

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1>Sushisom 日常经营 <small id="op-mode-text">添加 / 编辑</small></h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li class="active">日常录入 (A1)</li>
    </ol>
</section>

<section class="content">

    <div id="feedback-container">
        <?php echo $feedback_message; ?>
    </div>

    <form id="sushisom-form" class="form-horizontal" action="<?php echo CP_BASE_URL; ?>som_save" method="post">
        
        <input type="hidden" id="ss_daily_id" name="ss_daily_id">
        <input type="hidden" id="ss_daily_uuid" name="ss_daily_uuid">

        <div class="row">
            <div class="col-md-12">

                <div class="card box-primary">
                    <div class="card-header with-border">
                        <h3 class="box-title">I. 日常运营</h3>
                    </div>
                    <div class="card-body">
                        <div class="row compact-grid-row">
                            <?php echo render_input_unit('日期 *', 'entry_date', 'date', $default_date, '', true, $min_date, $today); ?>
                            <?php echo render_input_unit('上午人数', 'morning_count', 'number', '0', '0'); ?>
                            
                            <?php echo render_input_unit('下午人数', 'afternoon_count', 'number', '0', '0'); ?>
                            <?php echo render_input_unit('现金收入', 'cash_income', 'text', '0.00'); ?>
                            
                            <?php echo render_input_unit('现金支出', 'cash_expense', 'text', '0.00'); ?>
                            <?php echo render_input_unit('现金余额', 'cash_balance', 'text', '0.00'); ?>

                            <?php echo render_input_unit('银行余额', 'bank_balance', 'text', '0.00'); ?>
                            <?php echo render_input_unit('银行支出', 'bank_expense', 'text', '0.00'); ?>
                            
                            <?php echo render_input_unit('银行收入', 'bank_income', 'text', '0.00'); ?>
                        </div>
                    </div>
                </div><div class="card box-warning">
                    <div class="card-header with-border">
                        <h3 class="box-title">II. 投资回报 (分红 / 工资 / 投资款)</h3>
                    </div>
                    <div class="card-body">
                        <div class="row compact-grid-row">
                            <?php echo render_input_unit($static_financial_fields['total_dividend'], 'total_dividend', 'text', '0.00'); ?>
                            <?php echo render_input_unit($static_financial_fields['dividend_cash'], 'dividend_cash', 'text', '0.00'); ?>
                            
                            <?php echo render_input_unit($static_financial_fields['salary_bank_z'], 'salary_bank_z', 'text', '0.00'); ?>
                            <?php echo render_input_unit($static_financial_fields['salary_bank_c'], 'salary_bank_c', 'text', '0.00'); ?>
                            
                            <?php echo render_input_unit($static_financial_fields['salary_cash_z'], 'salary_cash_z', 'text', '0.00'); ?>
                            <?php echo render_input_unit($static_financial_fields['salary_cash_c'], 'salary_cash_c', 'text', '0.00'); ?>
                            
                            <?php echo render_input_unit($static_financial_fields['dividend_deduction'], 'dividend_deduction', 'text', '0.00'); ?>
                            <?php echo render_input_unit($static_financial_fields['project_payment'], 'project_payment', 'text', '0.00'); ?>
                            
                            <?php echo render_input_unit('投资款', 'investment', 'text', '0.00'); ?>
                        </div>
                    </div>
                </div><div class="card box-info">
                    <div class="card-header with-border">
                        <h3 class="box-title">III. 活动信息 (球赛)</h3>
                    </div>
                    <div class="card-body">
                        <div class="row compact-grid-row">
                            <?php echo render_input_unit('球赛', 'match_name', 'text'); ?>
                            <?php echo render_input_unit('比赛时间', 'match_time', 'text'); ?>
                        </div>
                    </div>
                </div></div>
        </div>
        
        <div class="row">
            <div class="col-md-12">


<div class="card box-default">
  <div class="card-footer"
       style="display:flex;align-items:center;justify-content:flex-end;
              padding:16px 24px;border-top:1px solid #e9eef5;
              background:linear-gradient(180deg,#fff,#f8fafc);
              border-radius:0 0 12px 12px;">
    <a href="<?php echo CP_BASE_URL; ?>dashboard"
       style="display:inline-flex;align-items:center;justify-content:center;
              height:44px;padding:0 18px;margin-right:12px;
              border:1px solid #d6dbe3;border-radius:10px;background:#fff;
              color:#34495e;text-decoration:none;font-weight:600;">
      取消
    </a>
    <button type="submit"
            style="display:inline-flex;align-items:center;justify-content:center;
                   height:44px;padding:0 26px;border:0;border-radius:12px;
                   background:#3b82f6;color:#fff;font-weight:700;
                   box-shadow:0 8px 18px rgba(59,130,246,.28);">
      <i class="fas fa-save" style="margin-right:8px;"></i> 保存
    </button>
  </div>
</div>



            </div>
        </div>

    </form>
</section>
<script>
$(document).ready(function() {
    
    const dateField = $('#entry_date');
    const form = $('#sushisom-form');
    const feedbackContainer = $('#feedback-container');
    const opModeText = $('#op-mode-text');
    const staticFinancialFields = ['total_dividend', 'dividend_cash', 'salary_bank_z', 'salary_bank_c', 'salary_cash_z', 'salary_cash_c', 'dividend_deduction', 'project_payment', 'investment'];

    // 页面加载时，自动检查当前日期
    checkDateAndLoadData(dateField.val());

// Date Picker：只在点击右侧输入框时打开；点击左侧“日期*”不弹出
(function bindDatePickerOnlyInput(){
  const row   = $('.date-field-full-row');
  const input = row.find('input[type="date"]');
  if (!row.length || !input.length) return;

  // 清理上一版可能的绑定
  row.off('mousedown touchstart keydown').removeAttr('role tabindex');
  row.find('label').off('click');
  input.off('click mousedown keydown');

  // 1) 点击输入框：强制打开（优先 showPicker，兼容浏览器）
  input.on('click', function(e){
    const el = this;
    if (typeof el.showPicker === 'function') {
      e.preventDefault();
      el.showPicker();
    }
  });

  // 2) 某些浏览器点到输入框右侧空白不触发 click，用 mousedown 兜底
  input.on('mousedown', function(){
    const el = this;
    setTimeout(function(){
      if (document.activeElement !== el) el.focus();
      if (typeof el.showPicker === 'function') el.showPicker();
    }, 0);
  });

  // 3) 点击左侧标签：只聚焦到输入框，不弹出选择器
  row.find('label').on('click', function(e){
    e.preventDefault();
    input.focus();
  });

  // 4) 键盘：仅当焦点在输入框内时，Enter/Space 打开
  input.on('keydown', function(e){
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      const el = this;
      if (typeof el.showPicker === 'function') el.showPicker();
      else el.click();
    }
  });
})();


    // New helper function to jump focus to the next visible, non-disabled input
    function jumpToNextInput(currentInput) {
        const inputs = form.find('input:visible, button:visible').not(':disabled');
        const currentIndex = inputs.index(currentInput);
        const nextInput = inputs.eq(currentIndex + 1);
        
        if (nextInput.length) {
            nextInput.focus();
        }
    }


    // [MODIFIED] Request 3: Monetary Field Behavior - 实时限制小数点后2位
    form.on('keypress', 'input[data-is-money="true"]', function(e) {
        const input = $(this);
        // 获取当前输入框中的值
        const value = input.val();
        const start = input[0].selectionStart; // 获取光标开始位置
        const end = input[0].selectionEnd;   // 获取光标结束位置
        // 如果选中了文本，则不进行限制（允许替换）
        if (start !== end) return; 

        // 允许的键：数字(0-9)，小数点(.)，负号(-) (用于支出)
        if (e.key === '0' || e.key === '1' || e.key === '2' || e.key === '3' || e.key === '4' || e.key === '5' || e.key === '6' || e.key === '7' || e.key === '8' || e.key === '9') {
            // 检查小数点后的位数
            if (value.indexOf('.') > -1 && value.substring(value.indexOf('.') + 1).length >= 2) {
                e.preventDefault(); // 阻止输入第3位小数
                return false;
            }
        } else if (e.key === '.') {
            // 阻止输入第二个小数点
            if (value.indexOf('.') > -1) {
                e.preventDefault();
                return false;
            }
            // 如果输入框为空，允许输入小数点，但在下一轮按键时需要检查
        } else if (e.key === '-') {
            // 负号只允许在开头且只允许一个 (仅用于支出项)
            if (value.indexOf('-') > -1 || start !== 0) {
                 e.preventDefault();
                 return false;
            }
        } else if (e.keyCode === 8 || e.keyCode === 46) { // 允许 Backspace 和 Delete
            return true;
        } else {
            // 阻止其他非数字/非小数点/非负号的输入 (例如字母)
            e.preventDefault();
            return false;
        }
    });

    // On focus in: Clear '0.00' to empty string for easy input
    form.on('focusin', 'input[data-is-money="true"]', function() {
        const input = $(this);
        // Only clear if the current value is exactly "0.00" or "0"
        if (input.val() === '0.00' || input.val() === '0') {
            input.val('');
        }
    });

    // On focus out: Re-add '0.00' if left empty/non-numeric
    form.on('focusout', 'input[data-is-money="true"]', function() {
        formatCurrency(this);
    });

    // [MODIFIED] Request 2: 人数输入满3位后跳转下一项
    form.on('keyup', 'input[data-is-count="true"]', function(e) {
        const input = $(this);
        // 如果值达到3位，跳转到下一个输入框
        if (input.val().length >= 3) {
            // 使用辅助函数跳转
            jumpToNextInput(input);
        }
    });

    // [NEW] Request: 金额输入满2位小数后跳转下一项 (使用 keyup 确保值已更新)
    form.on('keyup', 'input[data-is-money="true"]', function(e) {
        const input = $(this);
        const value = input.val();
        
        // 只在输入数字键时触发跳转检测 (48-57: 0-9, 96-105: Numpad 0-9)
        if ((e.keyCode >= 48 && e.keyCode <= 57) || (e.keyCode >= 96 && e.keyCode <= 105)) {
            const decimalIndex = value.indexOf('.');
            
            // 确保小数点存在
            if (decimalIndex > -1) {
                // 计算小数点后的字符数
                const currentDecimalLength = value.length - (decimalIndex + 1);
                
                // 如果小数点后有 2 位数字，且光标在末尾，则跳转
                if (currentDecimalLength === 2 && input[0].selectionStart === value.length) {
                    jumpToNextInput(input);
                }
            }
        }
    });


    // 当日期改变时，自动加载数据
    dateField.on('change', function() {
        if ($(this).val()) {
            // 增加日期范围检查
            const minDate = dateField.attr('min');
            const maxDate = dateField.attr('max');
            const selectedDate = $(this).val();

            if ((minDate && selectedDate < minDate) || (maxDate && selectedDate > maxDate)) {
                 showFeedback('danger', '选择的日期超出了允许的范围 (' + minDate + ' 至 ' + maxDate + ')。');
                 // 阻止加载，保留旧日期或设置默认日期
                 $(this).val(dateField.attr('value') || minDate);
                 return;
            }

            checkDateAndLoadData($(this).val());
            feedbackContainer.empty(); // 切换日期时隐藏旧消息
        }
    });
    
    function formatCurrency(input) {
        let value = $(input).val().replace(/,/g, '.');
        
        if (value === '' || value === '-' || isNaN(parseFloat(value))) {
            // 如果输入为空，确保它默认为 0.00，以便后端记录为 0
            $(input).val('0.00'); 
        } else {
            // 格式化为 2 位小数
            $(input).val(parseFloat(value).toFixed(2));
        }
    }
    
    /**
     * AJAX 检查并加载指定日期的数据
     */
    function checkDateAndLoadData(date) {
        resetForm(false); // 先清空（保留日期）
        dateField.val(date); // 确保日期被设置
        opModeText.text('添加 / 编辑'); 

        // AJAX 请求后端
        $.ajax({
            url: '<?php echo CP_BASE_URL; ?>som_get_data&date=' + date,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                // 检查 response.data 是否存在且包含 daily 键
                if (response.success && response.data && response.data.daily) {
                    // 如果找到数据，填充表单
                    populateForm(response.data);
                    opModeText.text('编辑'); // 切换模式文本
                } else if (response.success && !response.data) {
                     opModeText.text('添加'); 
                    // 如果 data 为 null，保持表单空白 (重置逻辑已执行)
                } else if (!response.success) {
                    // API 返回错误
                    showFeedback('danger', '加载数据失败: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Fetch Error:', error);
                showFeedback('danger', '加载数据时发生客户端错误。');
            }
        });
    }

    /**
     * 填充表单
     */
    function populateForm(data) {
        
        const daily = data.daily;
        const financialTransactions = data.financial_transactions || [];
        
        // I. 基础信息
        $('#ss_daily_id').val(daily.ss_daily_id || '');
        $('#ss_daily_uuid').val(daily.ss_daily_uuid || '');
        $('#entry_date').val(daily.ss_daily_date || dateField.val());
        
        // I. 日常运营 & III. 活动信息
        $('#morning_count').val(daily.ss_daily_morning_count || '0');
        $('#afternoon_count').val(daily.ss_daily_afternoon_count || '0');
        
        // [MODIFIED] 确保球赛字段加载空字符串
        $('#match_name').val(daily.ss_daily_match_name || '');
        $('#match_time').val(daily.ss_daily_match_time || '');

        $('#cash_income').val(daily.ss_daily_cash_income || '0.00');
        $('#cash_expense').val(daily.ss_daily_cash_expense || '0.00');
        $('#cash_balance').val(daily.ss_daily_cash_balance || '0.00');
        
        $('#bank_income').val(daily.ss_daily_bank_income || '0.00');
        $('#bank_expense').val(daily.ss_daily_bank_expense || '0.00');
        $('#bank_balance').val(daily.ss_daily_bank_balance || '0.00');

        // II. 投资回报 (静态字段) - 聚合 financial_transactions 流水
        
        // 1. 初始化聚合器
        const aggregatedFinancials = {};
        staticFinancialFields.forEach(key => {
            aggregatedFinancials[key] = 0;
        });

        // 2. 遍历流水，聚合金额
        financialTransactions.forEach(function(tx) {
            const category = tx.ss_fin_category;
            const amount = parseFloat(tx.ss_fin_amount);
            
            if (staticFinancialFields.includes(category) && !isNaN(amount)) {
                // 累加金额
                aggregatedFinancials[category] += amount;
            }
        });

        // 3. 填充表单
        staticFinancialFields.forEach(function(key) {
            // 确保显示两位小数
            $('#' + key).val(aggregatedFinancials[key].toFixed(2));
        });
    }

    /**
     * 重置表单
     */
    function resetForm(resetDate = true) {
        form[0].reset();
        
        // 重置所有输入框为 0 或默认
        form.find('input[type="text"], input[type="number"], input[type="hidden"]').each(function() {
            const input = $(this);
            const inputName = input.attr('name');
            
            // Monetary fields reset to 0.00
            if (input.attr('data-is-money') === 'true') {
                input.val('0.00');
            }
            // Number inputs (count) reset to 0
            else if (input.attr('data-is-count') === 'true') {
                input.val('0');
            }
            // Match fields clear to empty string
            if (inputName && (inputName.includes('match_'))) {
                input.val('');
            }
        });
        
        // 清空隐藏 ID
        $('#ss_daily_id').val('');
        $('#ss_daily_uuid').val('');
        
        if (resetDate) {
            dateField.val('<?php echo $default_date; ?>');
        }
        
        // 重置模式文本
        $('#op-mode-text').text('添加 / 编辑');
    }
    
    /**
     * 显示动态反馈
     */
    function showFeedback(type, message) {
        const icon = (type === 'success') ? 'check' : 'ban';
        const html = `
        <div id="feedback-bar" class="feedback-bar ${type}">
             <i class="fas fa-${icon} me-2"></i> ${message}
        </div>`;
        feedbackContainer.html(html);
    }

});

// —— 工具：通过中文标签文本找对应输入 —— //
function findInputByLabel(labelText){
  // 找到第一个匹配该文字的 <label>
  var $label = $('label').filter(function(){
    return $(this).text().trim().indexOf(labelText) > -1;
  }).first();
  if(!$label.length) return $();

  // 1) 同级向后查找
  var $input = $label.nextAll('input,select,textarea').first();
  if ($input.length) return $input;

  // 2) 在父容器内查找
  $input = $label.parent().find('input,select,textarea').first();
  if ($input.length) return $input;

  // 3) 容错：在常见分组容器内查找
  return $label.closest('.form-group, .form-item, .form-row, .grid-col, div')
               .find('input,select,textarea').first();
}

// —— 绑定“0 友好”行为 —— //
function bindZeroFriendly($input){
  if(!$input || !$input.length) return;

  // 聚焦：如果当前值为"0"或"0.00"，则清空以便直接输入
  $input.on('focus', function(){
    var v = String(this.value).trim();
    if (v === '0' || v === '0.00') this.value = '';
  });

  // 失焦：如果留空则恢复为"0"
  $input.on('blur', function(){
    var v = String(this.value).trim();
    if (v === '') this.value = '0';
  });
}

// 仅对“上午人数 / 下午人数”启用
$(function(){
  var $am = findInputByLabel('上午人数');
  var $pm = findInputByLabel('下午人数');
  bindZeroFriendly($am);
  bindZeroFriendly($pm);

  // 提交时兜底：若仍为空则写回 0
  $('form').on('submit', function(){
    [$am, $pm].forEach(function($el){
      if ($el && $el.length){
        var v = String($el.val() || '').trim();
        if (v === '') $el.val('0');
      }
    });
  });
});

// 在指定字段下方插入提示
function insertHintUnder(labelText, html){
  var $input = findInputByLabel(labelText);
  if(!$input.length) return;
  // 避免重复插入
  if ($input.next('.field-hint').length) return;
  $input.after($('<div class="field-hint" />').html(html));
}

$(function(){
  insertHintUnder('现金支出', '如有分红需要扣除');
  insertHintUnder('银行支出', '如有分红需要扣除');
});

// ========== 轻提示 ==========
window.cpToast = function (text, type = 'success', timeout = 2500) {
  var $toast = $('<div class="cp-toast ' + type + '"><div class="cp-toast-inner"></div></div>');
  var $inner = $toast.find('.cp-toast-inner');
  // 成功时给个小旋转图标；其他类型可去掉
  if (type === 'success') $inner.append('<span class="cp-toast-icon" aria-hidden="true"></span>');
  $inner.append($('<span/>').text(text));

  $('body').append($toast);
  requestAnimationFrame(function(){ $toast.addClass('in'); });
  setTimeout(function(){
    $toast.removeClass('in');
    setTimeout(function(){ $toast.remove(); }, 220);
  }, timeout);
};


// Toast 触发 v3：统一隐藏行内提示；新增/编辑均弹；失败为红色
$(function(){
  function toastByType(textAll){
    var isUpdate = /更新/.test(textAll);
    var isError  = /失败/.test(textAll);
    var type     = isError ? 'error' : 'success';
    var msg      = isError ? (isUpdate ? '更新失败' : '保存失败')
                           : (isUpdate ? '更新成功' : '保存成功');
    cpToast(msg, type, 2600);
  }

  // 安全隐藏：尽量只移除那条行内提示，不影响页面结构
  function removeInlineTip($node){
    if (!$node || !$node.length) return;
    // 纯文本小块：直接移除元素
    if ($node.is('p,div,span') &&
        $node.find('input,select,textarea,button,form,table').length === 0 &&
        $node.text().trim().length <= 200){
      $node.remove();
      return;
    }
    // 复杂结构：只清理匹配文案，保留其他内容
    var html = $node.html();
    if (html){
      // 去掉“记录已于 … 成功更新/保存！(新增/更新了 … 条财务记录)”之类文案
      html = html.replace(/记录已于.*?(成功保存|保存成功|成功更新|更新成功).*?(（?新增了.*?财务记录。?）?)/g, '');
      html = html.replace(/新记录已于.*?(成功保存|保存成功).*?(（?新增了.*?财务记录。?）?)/g, '');
      $node.html(html);
    }
  }

  // 1) 优先识别显式标记：<div data-flash-type="success|error">...</div>
  var $marked = $('[data-flash-type]:visible').first();
  if ($marked.length){
    toastByType($marked.text());
    removeInlineTip($marked);
    return;
  }

  // 2) 通用扫描：找包含关键字的“小文本块”（新增也会命中，编辑也会命中）
  var $hit = $('body *:visible').filter(function(){
    var ownText = $(this).clone().children().remove().end().text();
    var t = ownText.replace(/\s+/g,'').trim();
    if (!t) return false;
    return /(成功保存|保存成功|成功更新|更新成功|保存失败|更新失败)/.test(t);
  }).first();

  if ($hit.length){
    toastByType($hit.text());
    removeInlineTip($hit);  // 把那一行行内提示隐藏/清理掉
  }
});
</script>