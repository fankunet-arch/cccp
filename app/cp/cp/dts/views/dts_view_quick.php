<?php
/**
 * DTS 极速录入 (Smart Quick Entry) - Refactored to System Standard
 */

declare(strict_types=1);
require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

// 1. 预加载数据
$subjects_stmt = $pdo->query("SELECT id, subject_name, subject_type FROM cp_dts_subject WHERE subject_status = 1 ORDER BY subject_name");
$subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = dts_load_categories();

$event_types = [
    'submit' => '递交材料', 'issue' => '签发/获批', 'renew' => '续期',
    'maintain' => '保养', 'replace_part' => '更换部件', 'follow_up' => '跟进',
    'other' => '其他'
];

// 检查反馈 (保持原逻辑，渲染到隐藏的 div 中，由 JS 提取显示)
$feedback = dts_get_feedback();
$feedback_html = '';
if ($feedback) {
    // 注意：这里使用 data-type 属性方便 JS 读取
    $type = $feedback['type'] === 'success' ? 'success' : 'error';
    $feedback_html = "<div id='server-feedback' data-type='{$type}' style='display:none;'>{$feedback['message']}</div>";
}
?>

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1><i class="fas fa-bolt" style="color:#f39c12;"></i> DTS 极速录入 <small>一页搞定</small></h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_main">DTS 时间线</a></li>
        <li class="active">极速录入</li>
    </ol>
</section>

<section class="content">
    <?php echo $feedback_html; ?>

    <form action="<?php echo CP_BASE_URL; ?>dts_quick_save" method="post" class="form-horizontal" autocomplete="off">
        
        <div class="row">
            <div class="col-md-12">
                
                <div class="card box-primary">
                    <div class="card-header with-border">
                        <h3 class="box-title"><i class="fas fa-user"></i> 1. 归属主体 (Who)</h3>
                    </div>
                    <div class="card-body">
                        <div class="row compact-grid-row">
                            <div class="compact-field-unit" style="grid-column: 1 / -1;">
                                <label for="subject_name_input">主体名称 *</label>
                                <input type="text" class="form-control" name="subject_name_input" id="subject_name_input" 
                                       list="subject_list" placeholder="输入名字（如'张'），列表自动跳出..." required>
                                <datalist id="subject_list">
                                    <?php foreach ($subjects as $subj): ?>
                                        <option value="<?php echo htmlspecialchars($subj['subject_name']); ?>" 
                                                data-id="<?php echo $subj['id']; ?>" 
                                                data-type="<?php echo $subj['subject_type']; ?>">
                                            <?php echo $subj['subject_type'] === 'company' ? '【公司】' : '【个人】'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </datalist>
                                <input type="hidden" name="subject_id" id="subject_id_hidden">
                            </div>
                            <div class="field-hint" id="subject_help_text" style="grid-column: 1 / -1; margin-top:-10px; margin-bottom:10px; padding-left: 135px;">
                                如果是新主体，系统将自动创建。
                            </div>

                            <div class="compact-field-unit" id="new_subject_type_group" style="display:none; grid-column: 1 / -1;">
                                <label>主体类型</label>
                                <div style="flex: 1; display:flex; align-items:center; gap:15px;">
                                    <label style="width:auto; min-width:0; text-align:left; font-weight:normal;">
                                        <input type="radio" name="new_subject_type" value="person" checked> 个人
                                    </label>
                                    <label style="width:auto; min-width:0; text-align:left; font-weight:normal;">
                                        <input type="radio" name="new_subject_type" value="company"> 公司
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card box-info">
                    <div class="card-header with-border">
                        <h3 class="box-title"><i class="fas fa-box-open"></i> 2. 对象/事项 (What)</h3>
                    </div>
                    <div class="card-body">
                        <div class="row compact-grid-row">
                            <div class="compact-field-unit" style="grid-column: 1 / -1;">
                                <label for="object_name">对象名称 *</label>
                                <input type="text" class="form-control" name="object_name" id="object_name" 
                                       placeholder="例如：护照、车辆Q3、临时罚单" required>
                            </div>
                            <div class="field-hint" style="grid-column: 1 / -1; margin-top:-10px; margin-bottom:15px; padding-left: 135px;">
                                若主体下已有同名对象则自动合并；否则创建新对象。
                            </div>

                            <div class="compact-field-unit">
                                <label for="cat_main">大类 *</label>
                                <select class="form-control" name="cat_main" id="cat_main" required>
                                    <option value="">-- 请选择 --</option>
                                    <?php foreach (array_keys($categories) as $main): ?>
                                        <option value="<?php echo htmlspecialchars($main); ?>"><?php echo htmlspecialchars($main); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="compact-field-unit">
                                <label for="cat_sub">小类</label>
                                <select class="form-control" name="cat_sub" id="cat_sub">
                                    <option value="">(先选大类)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card box-warning">
                    <div class="card-header with-border">
                        <h3 class="box-title"><i class="fas fa-calendar-check"></i> 3. 事件记录 (Event)</h3>
                    </div>
                    <div class="card-body">
                        <div class="row compact-grid-row">
                            <div class="compact-field-unit date-field-full-row">
                                <label for="event_date">日期 *</label>
                                <input type="date" class="form-control" name="event_date" id="event_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="compact-field-unit">
                                <label for="event_type">类型 *</label>
                                <select class="form-control" name="event_type" id="event_type" required>
                                    <?php foreach ($event_types as $k => $v): ?>
                                        <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="compact-field-unit">
                                <label for="note">备注</label>
                                <input type="text" class="form-control" name="note" id="note" placeholder="补充说明...">
                            </div>

                            <div class="compact-field-unit" id="mileage_area" style="display: none;">
                                <label for="mileage_now" style="color:#d35400;">当前公里数</label>
                                <div style="flex:1; display:flex;">
                                    <input type="number" class="form-control" name="mileage_now" id="mileage_now" placeholder="仪表盘读数">
                                    <span style="padding: 10px; background: #eee; border:1px solid #ccc; border-left:0; border-radius:0 8px 8px 0;">KM</span>
                                </div>
                            </div>

                            <div class="compact-field-unit" id="expiry_area" style="display: none;">
                                <label for="expiry_date_new" style="color:#2980b9;">新过期日</label>
                                <input type="date" class="form-control" name="expiry_date_new" id="expiry_date_new">
                            </div>
                        </div>
                    </div>

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
                            <i class="fas fa-check-circle" style="margin-right:8px;"></i> 保存记录
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </form>
</section>

<script>
(function(){
    // 1. 定义 Toast 函数 (使用系统中 style.css 已有的 .cp-toast 样式)
    window.cpToast = function (text, type = 'success', timeout = 2500) {
      var $toast = $('<div class="cp-toast ' + type + '"><div class="cp-toast-inner"></div></div>');
      var $inner = $toast.find('.cp-toast-inner');
      // 成功时给个图标
      if (type === 'success') $inner.append('<span class="cp-toast-icon" aria-hidden="true"></span>');
      $inner.append($('<span/>').text(text));

      $('body').append($toast);
      // 动画入场
      requestAnimationFrame(function(){ $toast.addClass('in'); });
      // 定时消失
      setTimeout(function(){
        $toast.removeClass('in');
        setTimeout(function(){ $toast.remove(); }, 220);
      }, timeout);
    };

    // 2. 检查并显示反馈
    var $fb = $('#server-feedback');
    if ($fb.length) {
        var msg = $fb.text().trim();
        var type = $fb.data('type') || 'success'; // 'success' or 'error'
        if (msg) {
            cpToast(msg, type, 3000);
        }
    }

    // --- 以下为原有业务逻辑 (联想与联动) ---
    const subjects = <?php echo json_encode($subjects); ?>;
    const categories = <?php echo json_encode($categories); ?>;
    
    const inputSubj = document.getElementById('subject_name_input');
    const hiddenSubjId = document.getElementById('subject_id_hidden');
    const newSubjGroup = document.getElementById('new_subject_type_group');
    const helpText = document.getElementById('subject_help_text');
    
    const catMain = document.getElementById('cat_main');
    const catSub = document.getElementById('cat_sub');
    const mileageArea = document.getElementById('mileage_area');
    const expiryArea = document.getElementById('expiry_area');

    inputSubj.addEventListener('input', function() {
        const val = this.value.trim();
        const match = subjects.find(s => s.subject_name === val);
        
        if (match) {
            hiddenSubjId.value = match.id;
            $(newSubjGroup).slideUp(150);
            helpText.innerHTML = '<span class="text-success"><i class="fas fa-check"></i> 已关联现有主体：' + match.subject_name + '</span>';
        } else {
            hiddenSubjId.value = '';
            if (val.length > 0) {
                $(newSubjGroup).slideDown(150);
                helpText.innerHTML = '<span class="text-info">新主体，将自动创建。</span>';
            } else {
                $(newSubjGroup).slideUp(150);
                helpText.innerText = '如果是新主体，系统将自动创建。';
            }
        }
    });

    catMain.addEventListener('change', function(){
        const main = this.value;
        catSub.innerHTML = '<option value="">(可选)</option>';
        if (categories[main]) {
            categories[main].forEach(sub => {
                const opt = document.createElement('option');
                opt.value = sub;
                opt.innerText = sub;
                catSub.appendChild(opt);
            });
        }
        
        if (main === '车辆') {
            $(mileageArea).css('display', 'flex').hide().slideDown(200);
        } else {
            $(mileageArea).slideUp(200);
            mileageArea.querySelector('input').value = '';
        }

        if (main === '证件') {
            $(expiryArea).css('display', 'flex').hide().slideDown(200);
        } else {
            $(expiryArea).slideUp(200);
        }
    });

})();
</script>