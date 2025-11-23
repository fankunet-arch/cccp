<?php
// /app/cp/tea/views/tea_view_add.php
// <tea> Project Investment Entry View (T1) (REWRITE: Single Transaction Model)

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) { exit('Access Denied.'); }

global $pdo; 
if (!isset($pdo)) {
    require_once dirname(dirname(__DIR__)) . '/bootstrap.php';
    global $pdo;
}

// 1. 获取店铺列表
$stores = [];
try {
    $stmt = $pdo->query("SELECT id, store_name FROM tea_stores ORDER BY store_name ASC");
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $stores_error = '无法加载店铺列表，请在“店铺管理”中检查数据库表 `tea_stores`。';
}

// 2. 定义交易类型
$transaction_types = [
    'DECORATION'        => '装修费',
    'RENT'              => '房租',
    'DEPOSIT'           => '押金',
    'SUPPLIES'          => '物料',
    'SHIPPING'          => '运输费',
    'EQUIPMENT'         => '设备',
    'TAX'               => '税费',
    'GESTOR'            => 'Gestor 费用',
    'PROJECT_EXPENSE'   => '项目支出 (出)',
    'INVESTMENT_IN'     => '投资款 (入)',
    'INVESTMENT_OUT'    => '投资款 (出)',
    'DIVIDEND_CASH'     => '现金分红 (入)',
    'DIVIDEND_DEDUCTION'=> '分红抵扣 (出)',
];
$all_types = array_keys($transaction_types);

// 定义需要取绝对值的类型 (支出类)
$outflow_types = [
    'DECORATION', 'RENT', 'DEPOSIT', 'SUPPLIES', 'SHIPPING', 'EQUIPMENT', 
    'TAX', 'GESTOR', 'PROJECT_EXPENSE', 'INVESTMENT_OUT', 'DIVIDEND_DEDUCTION'
];
$outflow_types_js = json_encode($outflow_types); // 供 JS 使用

// 3. 检查是否为编辑模式
$edit_data = null;
$edit_id = $_GET['id'] ?? null;
if ($edit_id && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM tea_financial_transactions WHERE tea_fin_id = ?");
        $stmt->execute([$edit_id]);
        $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        // Log error
    }
}

// 4. 设置默认值和表单动作
$is_edit_mode = $edit_data !== false && $edit_data !== null;
$mode_text = $is_edit_mode ? '编辑' : '添加';

$default_date = $edit_data['tea_date'] ?? ($_GET['date'] ?? date('Y-m-d'));
$default_store = $edit_data['tea_store'] ?? '';
$default_currency = $edit_data['tea_currency'] ?? '';
$default_type = $edit_data['tea_type'] ?? '';
$default_notes = $edit_data['tea_notes'] ?? '';
$default_exchange_rate = $edit_data['tea_exchange_rate'] ?? '';
$default_is_equity = $edit_data['tea_is_equity'] ?? 0;

// 关键：金额显示绝对值
$default_amount = $edit_data['tea_amount'] ?? '0.00';
if ($default_amount !== '0.00' && is_numeric($default_amount)) {
    // 确保显示为绝对值，因为后端会根据类型自动修正符号
    $default_amount = number_format(abs((float)$default_amount), 2, '.', '');
}


// 5. 渲染辅助函数
function render_compact_input(string $label, string $name, string $type = 'text', string $value = '', string $placeholder = '', bool $disabled = false): string {
    $money_attr = ($type === 'text' && $name !== 'tea_store' && $name !== 'tea_notes') ? 'data-is-money="true"' : '';
    $required   = ($name === 'tea_date' || $name === 'tea_currency' || $name === 'tea_type' || $name === 'tea_amount') ? 'required' : '';
    $class      = ($type === 'date') ? 'form-control date-input' : 'form-control';
    $disabled_attr = $disabled ? 'disabled' : '';
    
    return <<<HTML
    <div class="compact-field-unit">
        <label for="{$name}">{$label}</label>
        <input type="{$type}" class="{$class}" id="{$name}" name="{$name}" placeholder="{$placeholder}" value="{$value}" {$required} {$money_attr} {$disabled_attr}>
    </div>
HTML;
}


// 6. 反馈信息
$feedback_message = '';
if (isset($_SESSION['tea_feedback'])) {
    $feedback = $_SESSION['tea_feedback'];
    $alert_type = $feedback['type'] === 'success' ? 'alert-success' : 'alert-danger';
    $icon = $feedback['type'] === 'success' ? 'check' : 'ban';
    
    $feedback_message = <<<HTML
    <div id="feedback-bar" class="feedback-bar {$feedback['type']}">
        <i class="fas fa-{$icon} me-2"></i> {$feedback['message']}
    </div>
HTML;
    unset($_SESSION['tea_feedback']);
}

?>

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1><tea> 投资录入 <small>T1 (<?php echo $mode_text; ?>)</small></h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li class="active"><tea> 投资录入</li>
    </ol>
</section>

<section class="content">

    <div id="feedback-container">
        <?php echo $feedback_message; ?>
    </div>
    
    <?php if (isset($stores_error)): ?>
        <div id="feedback-bar" class="alert alert-danger" data-flash-type="error">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
            <?php echo $stores_error; ?>
        </div>
    <?php endif; ?>

    <form id="tea-investment-form" class="form-horizontal" action="<?php echo CP_BASE_URL; ?>tea_save" method="post">
        
        <input type="hidden" id="tea_fin_id" name="tea_fin_id" value="<?php echo $edit_id; ?>">

        <div class="row">
            <div class="col-md-12">

                <div class="card box-primary">
                    <div class="card-header with-border">
                        <h3 class="box-title">I. 交易信息</h3>
                    </div>
                    <div class="card-body">
                        <div class="row compact-grid-row">
                            
                            <?php echo render_compact_input('交易日期 *', 'tea_date', 'date', $default_date); ?>
                            
                            <div class="compact-field-unit">
                                <label for="tea_store">关联店铺</label>
                                <select class="form-control" id="tea_store" name="tea_store" style="flex:1; padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; max-width: none;">
                                    <option value="">(无店铺关联)</option>
                                    <?php foreach ($stores as $store): ?>
                                        <option value="<?php echo htmlspecialchars($store['store_name']); ?>" <?php echo ($default_store === $store['store_name']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($store['store_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="compact-field-unit">
                                <label for="tea_currency">币种 *</label>
                                <select class="form-control" id="tea_currency" name="tea_currency" required style="flex:1; padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; max-width: none;">
                                    <option value="">请选择</option>
                                    <option value="EUR" <?php echo ($default_currency === 'EUR') ? 'selected' : ''; ?>>欧元 (EUR)</option>
                                    <option value="CNY" <?php echo ($default_currency === 'CNY') ? 'selected' : ''; ?>>人民币 (CNY)</option>
                                    <option value="USD" <?php echo ($default_currency === 'USD') ? 'selected' : ''; ?>>美元 (USD)</option>
                                </select>
                            </div>
                            
                            <?php echo render_compact_input('交易金额 *', 'tea_amount', 'text', $default_amount, '0.00'); ?>
                            
                            <?php echo render_compact_input('当日汇率', 'tea_exchange_rate', 'text', $default_exchange_rate, '例如: 7.80'); ?>
                            <div class="field-hint" style="grid-column: 2 / -1; margin-top: -10px; margin-bottom: 10px;">非必填。仅当币种非基础币种时，建议填写。</div>

                            <div class="compact-field-unit">
                                <label for="tea_type">交易类型 *</label>
                                <select class="form-control" id="tea_type" name="tea_type" required style="flex:1; padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; max-width: none;">
                                    <option value="">请选择</option>
                                    <?php foreach ($transaction_types as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" <?php echo ($default_type === $value) ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="compact-field-unit">
                                <label for="tea_is_equity">是否计入本金</label>
                                <div style="flex: 1; text-align: left; display: flex; align-items: center; height: 38px;">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="tea_is_equity" name="tea_is_equity" value="1" <?php echo $default_is_equity ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="tea_is_equity" style="text-align: left; min-width: auto; padding-right: 0; font-weight: 400; color: var(--text-color);">是 (作为股东资本投入/支出)</label>
                                    </div>
                                </div>
                            </div>
                            <div class="field-hint" id="equity-hint" style="grid-column: 2 / -1; margin-top: -10px; margin-bottom: 10px;">
                                勾选此项表示这笔支出/流入是由股东资金贡献的。
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card box-info">
                    <div class="card-header with-border">
                        <h3 class="box-title">II. 备注</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="tea_notes" class="sr-only">备注</label>
                            <textarea class="form-control" id="tea_notes" name="tea_notes" rows="3" placeholder="填写交易详细说明或摘要。" style="width: 100%; border-radius: 8px; border-color: var(--border-color); padding: 10px 15px;"><?php echo htmlspecialchars($default_notes); ?></textarea>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card box-default">
                    <div class="card-footer"
                         style="display:flex;align-items:center;justify-content:flex-end;
                                padding:16px 24px;border-top:1px solid #e9eef5;
                                background:linear-gradient(180deg,#fff,#f8fafc);
                                border-radius:0 0 12px 12px;">
                        <button type="submit"
                                style="display:inline-flex;align-items:center;justify-content:center;
                                       height:44px;padding:0 26px;border:0;border-radius:12px;
                                       background:#3b82f6;color:#fff;font-weight:700;
                                       box-shadow:0 8px 18px rgba(59,130,246,.28);">
                            <i class="fas fa-save" style="margin-right:8px;"></i> <?php echo $mode_text; ?>交易
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </form>
</section>
<script>
$(document).ready(function() {
    const form = $('#tea-investment-form');
    const equityCheckbox = $('#tea_is_equity');
    const typeSelect = $('#tea_type');
    const equityHint = $('#equity-hint');

    // 定义需要取负号的支出类型 (必须与后端保持一致)
    const OUTFLOW_TYPES = <?php echo $outflow_types_js; ?>;

    // 根据交易类型更新提示和复选框状态
    function updateEquityState() {
        const selectedType = typeSelect.val();
        const isEditMode = $('#tea_fin_id').val() !== '';
        
        equityCheckbox.prop('disabled', false); // 默认启用

        if (OUTFLOW_TYPES.includes(selectedType)) {
            // 支出类：允许用户勾选（即视为股东支付）
            equityHint.html('这是**支出类**交易。勾选表示这笔费用由股东支付，并计入本金。');
            equityCheckbox.prop('checked', <?php echo $default_is_equity ? 'true' : 'false'; ?>); // 重置为默认值
        } else if (selectedType === 'INVESTMENT_IN') {
            // 投资款 (入)：强制为股本
            equityCheckbox.prop('checked', true).prop('disabled', true);
            equityHint.html('这是**股东投资注入**。它必须计入本金。');
        } else if (selectedType === 'DIVIDEND_CASH') {
             // 现金分红 (入)：通常不是股本，禁用并取消勾选
            equityCheckbox.prop('checked', false).prop('disabled', true);
            equityHint.html('这是**回报类**交易，不属于本金投入。');
        } else if (selectedType === 'INVESTMENT_OUT' || selectedType === 'DIVIDEND_DEDUCTION') {
            // 投资撤出 / 分红抵扣：强制为非股本
            equityCheckbox.prop('checked', false).prop('disabled', true);
            equityHint.html('这是**资金调整**，不属于净投入资本。');
        } else {
             // 默认状态：不限制
            equityCheckbox.prop('checked', <?php echo $default_is_equity ? 'true' : 'false'; ?>);
            equityHint.html('勾选此项表示这笔支出/流入是由股东资金贡献的。');
        }

        // 在编辑模式下，如果类型不是 INVESTMENT_IN (它必须是 equity=1)，则允许用户修改 equity 状态
        if (isEditMode && selectedType !== 'INVESTMENT_IN') {
             equityCheckbox.prop('disabled', false);
        }
    }

    // 绑定事件
    typeSelect.on('change', updateEquityState);
    
    // 初始化状态 (处理默认值或页面加载)
    updateEquityState(); 


    // ========== 金额格式化与输入限制逻辑 (保持不变) ==========
    
    function formatCurrency(input) {
        let value = $(input).val().replace(/,/g, '');
        if (value === '' || value === '-' || isNaN(parseFloat(value))) {
            $(input).val('0.00'); 
        } else {
            $(input).val(parseFloat(value).toFixed(2));
        }
    }

    form.on('keypress', 'input[data-is-money="true"]', function(e) {
        const input = $(this);
        const value = input.val();
        const start = input[0].selectionStart; 
        const end = input[0].selectionEnd;
        if (start !== end) return; 

        if (e.key >= '0' && e.key <= '9') {
            if (value.indexOf('.') > -1 && value.substring(value.indexOf('.') + 1).length >= 2 && start > value.indexOf('.')) {
                e.preventDefault();
                return false;
            }
        } else if (e.key === '.') {
            if (value.indexOf('.') > -1) {
                e.preventDefault();
                return false;
            }
        } else if (e.key === '-') {
            if (value.indexOf('-') > -1 || start !== 0) {
                 e.preventDefault();
                 return false;
            }
        } else if (e.keyCode === 8 || e.keyCode === 46) {
            return true;
        } else {
            e.preventDefault();
            return false;
        }
    });

    form.on('focusin', 'input[data-is-money="true"]', function() {
        if ($(this).val() === '0.00' || $(this).val() === '0') {
            $(this).val('');
        }
    });

    form.on('focusout', 'input[data-is-money="true"]', function() {
        formatCurrency(this);
    });

    // ========== 轻提示函数 (保持不变) ==========
    window.cpToast = function (text, type = 'success', timeout = 2500) {
        var $toast = $('<div class="cp-toast ' + type + '"><div class="cp-toast-inner"></div></div>');
        var $inner = $toast.find('.cp-toast-inner');
        if (type === 'success') $inner.append('<span class="cp-toast-icon" aria-hidden="true"></span>');
        $inner.append($('<span/>').text(text));

        $('body').append($toast);
        requestAnimationFrame(function(){ $toast.addClass('in'); });
        setTimeout(function(){
            $toast.removeClass('in');
            setTimeout(function(){ $toast.remove(); }, 220);
        }, timeout);
    };

    function checkFlashMessage() {
        var $marked = $('#feedback-bar:visible').first();
        if ($marked.length){
            const type = $marked.hasClass('alert-success') ? 'success' : 'error';
            const text = $marked.clone().find('.close').remove().end().text().trim(); 
            
            cpToast(text, type, 2600);
            $marked.remove();
            return;
        }
    }
    checkFlashMessage();
});
</script>