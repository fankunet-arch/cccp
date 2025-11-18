<?php
// /app/cp/som/views/som_view_salary_add.php
// (重写: 匹配“月度工资”录入需求，并使用 [2025-10-17] 紧凑布局)

declare(strict_types=1);

// 检查是否有来自 save action 的反馈信息
$feedback_message = '';
if (isset($_SESSION['salary_feedback'])) {
    $feedback = $_SESSION['salary_feedback'];
    $alert_type = $feedback['type'] === 'success' ? 'success' : ($feedback['type'] === 'info' ? 'info' : 'danger');
    $icon = $feedback['type'] === 'success' ? 'check' : 'ban';
    
    $feedback_message = <<<HTML
    <div id="feedback-bar" class="feedback-bar {$alert_type}">
        <i class="fas fa-{$icon} me-2"></i> {$feedback['message']}
    </div>
HTML;
    unset($_SESSION['salary_feedback']);
}

// 确定默认月份：优先使用 URL 参数，其次是今天
$default_month = $_GET['month'] ?? date('Y-m');

?>

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1>月度工资录入 <small id="op-mode-text">（日营分析）</small></h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li><a href="#">Sushisom 财务</a></li>
        <li class="active">月度工资</li>
    </ol>
</section>

<section class="content">

    <div id="feedback-container">
        <?php echo $feedback_message; ?>
    </div>

    <form id="salary-form" class="form-horizontal" action="<?php echo CP_BASE_URL; ?>som_salary_save" method="post">
        
        <input type="hidden" id="ss_ms_id" name="ss_ms_id">

        <div class="row">
            <div class="col-md-12">

                <div class="card box-primary">
                    <div class="card-header with-border">
                        <h3 class="box-title">工资数据 (门店支出)</h3>
                    </div>
                    <div class="card-body">
                        <div class="row compact-grid-row">
                            
                            <div class="compact-field-unit date-field-full-row">
                                <label for="salary_month">工资月份 *</label>
                                <input type="month" class="form-control" id="salary_month" name="salary_month" value="<?php echo $default_month; ?>" required style="font-size: 18px; font-weight: 600;">
                            </div>

                            <div class="compact-field-unit">
                                <label for="ss_ms_sushi_salary">寿司房工资</label>
                                <input type="text" class="form-control" id="ss_ms_sushi_salary" name="ss_ms_sushi_salary" placeholder="0.00" value="0.00" data-is-money="true">
                            </div>

                            <div class="compact-field-unit">
                                <label for="ss_ms_kitchen_salary">厨房工资</label>
                                <input type="text" class="form-control" id="ss_ms_kitchen_salary" name="ss_ms_kitchen_salary" placeholder="0.00" value="0.00" data-is-money="true">
                            </div>

                            <div class="compact-field-unit">
                                <label for="ss_ms_waitstaff_salary">跑堂工资</label>
                                <input type="text" class="form-control" id="ss_ms_waitstaff_salary" name="ss_ms_waitstaff_salary" placeholder="0.00" value="0.00" data-is-money="true">
                            </div>

                        </div>
                    </div>
                    
                    <div class="card-footer"
                        style="display:flex;align-items:center;justify-content:flex-end;
                                padding:16px 24px;border-top:1px solid #e9eef5;
                                background:linear-gradient(180deg,#fff,#f8fafc);
                                border-radius:0 0 12px 12px;">
                        <button type="submit" class="btn btn-primary btn-lg" id="save-button"
                                style="display:inline-flex;align-items:center;justify-content:center;
                                    height:44px;padding:0 26px;border:0;border-radius:12px;
                                    background:#3b82f6;color:#fff;font-weight:700;
                                    box-shadow:0 8px 18px rgba(59,130,246,.28);">
                            <i class="fas fa-save" style="margin-right:8px;"></i> 保存本月工资
                        </button>
                    </div>
                </div>
                
            </div>
        </div>

    </form>
</section>

<script>
$(document).ready(function() {
    
    const monthField = $('#salary_month');
    const form = $('#salary-form');
    const feedbackContainer = $('#feedback-container');

    // New helper function to jump focus to the next visible, non-disabled input
    function jumpToNextInput(currentInput) {
        const inputs = form.find('input:visible, button:visible').not(':disabled');
        const currentIndex = inputs.index(currentInput);
        const nextInput = inputs.eq(currentIndex + 1);
        
        if (nextInput.length) {
            nextInput.focus();
        }
    }


    // Date Picker：月选择器也支持点击任意位置弹出
    (function bindMonthPickerOnlyInput(){
      const row   = form.find('.date-field-full-row');
      const input = row.find('input[type="month"]');
      if (!row.length || !input.length) return;

      // 1) 点击输入框：强制打开
      input.on('click', function(e){
        const el = this;
        if (typeof el.showPicker === 'function') {
          e.preventDefault();
          el.showPicker();
        }
      });

      // 2) 点击左侧标签（或整个容器）：只聚焦到输入框，并弹出选择器
      row.on('click', function(e){
          // 如果点击的不是输入框本身，则模拟点击输入框
          if (e.target !== input[0] && e.target.tagName !== 'INPUT') {
              e.preventDefault();
              input.focus();
              if (typeof input[0].showPicker === 'function') input[0].showPicker();
              else input[0].click();
          }
      });
    })();


    /**
     * [MODIFIED] 货币格式化函数 (失焦时：最终清理和定精度)
     */
    function formatCurrency(input) {
        let value = $(input).val();
        
        // 1. 清理：移除所有非数字、非点、非逗号、非负号的符号（包括货币符号）
        let cleanedValue = String(value).replace(/[^\d.,-]/g, '');
        
        // 2. [FIX] 规范化：移除所有逗号（作为千位分隔符），保留点作为小数点。
        cleanedValue = cleanedValue.replace(/,/g, '');
        
        // 3. 限制为单个负号在开头
        let minus = cleanedValue.startsWith('-') ? '-' : '';
        cleanedValue = cleanedValue.replace(/-/g, '');
        
        // 4. 限制小数点后两位精度
        let parts = cleanedValue.split('.');
        if (parts.length > 1) {
            // 确保小数点后只有两位，并忽略多余的小数点
            parts[1] = parts[1].substring(0, 2);
            cleanedValue = parts[0] + '.' + parts[1];
        } else {
            cleanedValue = parts[0];
        }
        
        cleanedValue = minus + cleanedValue;

        let v = parseFloat(cleanedValue);
        
        // 5. 最终格式化和赋值
        if (isNaN(v)) {
            $(input).val('0.00'); 
        } else {
            $(input).val(v.toFixed(2));
        }
    }


    // 1. [MODIFIED] INPUT 事件：用于处理粘贴和拖放的数据清洗（非最终格式化，只为保持输入合法）
    form.on('input', 'input[data-is-money="true"]', function() {
        const input = $(this);
        const raw_value = input.val();
        
        // 立即执行清理：移除所有非数字、点、逗号、非负号的字符（包括货币符号）
        let cleanedValue = String(raw_value).replace(/[^\d.,-]/g, '');
        
        // [FIX] 规范化: 移除所有逗号 (千位符)，然后处理小数点和负号。
        let tempValue = cleanedValue.replace(/,/g, ''); 

        // 1. 确保只有一个负号且在开头
        let minus = tempValue.startsWith('-') ? '-' : '';
        tempValue = tempValue.replace(/-/g, '');
        
        // 2. 确保只有一个小数点
        let parts = tempValue.split('.');
        if (parts.length > 1) {
            // 只保留第一个点作为小数点，后面的都移除
            tempValue = parts[0] + '.' + parts.slice(1).join(''); 
        }

        // 3. 重新组装负号并限制小数位数（如果超过2位）
        tempValue = minus + tempValue;
        const dotIndex = tempValue.indexOf('.');
        if (dotIndex > -1) {
            const decimalLength = tempValue.length - dotIndex - 1;
            if (decimalLength > 2) {
                tempValue = tempValue.substring(0, dotIndex + 3);
            }
        }
        
        // 仅在值发生变化时更新
        if (raw_value !== tempValue) {
             input.val(tempValue);
        }
    });
    
    // 2. 聚焦时清空 0.00
    form.on('focusin', 'input[data-is-money="true"]', function() {
        if ($(this).val() === '0.00') $(this).val('');
    });
    
    // 3. 失焦时格式化 (使用增强的 formatCurrency 进行最终清理和定精度)
    form.on('focusout', 'input[data-is-money="true"]', function() {
        formatCurrency(this);
    });

    // 4. 金额输入满2位小数后跳转下一项 (依赖 keyup)
    form.on('keyup', 'input[data-is-money="true"]', function(e) {
        const input = $(this);
        const value = input.val();
        
        // 只在输入数字键时触发跳转检测 (48-57: 0-9, 96-105: Numpad 0-9)
        if ((e.keyCode >= 48 && e.keyCode <= 57) || (e.keyCode >= 96 && e.keyCode <= 105)) {
            const decimalIndex = value.indexOf('.');
            
            if (decimalIndex > -1) {
                const currentDecimalLength = value.length - (decimalIndex + 1);
                
                // 如果小数点后有 2 位数字，且光标在末尾，则跳转
                if (currentDecimalLength === 2 && input[0].selectionStart === value.length) {
                    jumpToNextInput(input);
                }
            }
        }
    });

    // 页面加载时，自动检查当前月份的数据
    if (monthField.val()) {
        checkMonthAndLoadData(monthField.val());
    }

    // 当月份改变时，自动加载数据
    monthField.on('change', function() {
        if ($(this).val()) {
            checkMonthAndLoadData($(this).val());
            feedbackContainer.empty(); // 切换时隐藏旧消息
        }
    });

    /**
     * AJAX 检查并加载指定月份的数据
     */
    function checkMonthAndLoadData(month) {
        resetForm(false); // 先清空（保留月份）
        monthField.val(month);

        $.ajax({
            url: '<?php echo CP_BASE_URL; ?>som_salary_get_data&month=' + month,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    // 如果找到数据，填充表单
                    populateForm(response.data);
                } else if (!response.success) {
                    showFeedback('danger', '加载数据失败: ' + response.message);
                }
                // (如果 data 为 null，保持表单空白)
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
        $('#ss_ms_id').val(data.ss_ms_id || '');
        // 确保加载的数据也是格式化后的两位小数
        $('#ss_ms_sushi_salary').val(Number(data.ss_ms_sushi_salary || 0).toFixed(2));
        $('#ss_ms_kitchen_salary').val(Number(data.ss_ms_kitchen_salary || 0).toFixed(2));
        $('#ss_ms_waitstaff_salary').val(Number(data.ss_ms_waitstaff_salary || 0).toFixed(2));
    }

    /**
     * 重置表单
     */
    function resetForm(resetMonth = true) {
        form[0].reset();
        
        form.find('input[data-is-money="true"]').val('0.00');
        $('#ss_ms_id').val('');
        
        if (resetMonth) {
            monthField.val('<?php echo $default_month; ?>');
        }
    }
    
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

    /**
     * 显示动态反馈 (将行内提示转换为 Toast)
     */
    function showFeedback(type, message) {
        // [MODIFIED] 直接调用 toast，不显示行内反馈
        
        let msg;
        if (type === 'danger' || type === 'error') {
            msg = '保存失败';
        } else if (/更新/.test(message)) {
            msg = '更新成功';
            type = 'success';
        } else if (/已保存/.test(message)) {
            msg = '保存成功';
            type = 'success';
        } else if (/未发生变化/.test(message)) {
            msg = '数据未发生变化';
            type = 'info';
        } else {
             msg = message; // Fallback
        }
        
        window.cpToast(msg, type, 2600);
        
        // 清除行内反馈容器
        feedbackContainer.empty();
    }
    
    // Toast 触发 v3：统一隐藏行内提示；新增/编辑均弹；失败为红色
    $(function(){
      // 1) 扫描：找包含关键字的“小文本块”（即 #feedback-bar 的内容）
      var $hit = $('#feedback-bar:visible').first();

      if ($hit.length){
        // 提取消息内容和类型
        const type = $hit.hasClass('success') ? 'success' : ($hit.hasClass('info') ? 'info' : 'danger');
        const text = $hit.clone().children().remove().end().text().trim();
        
        // 转换为 Toast
        showFeedback(type, text); 
        
        // 清除行内提示
        $hit.remove(); 
      }
    });

});
</script>