<?php
/**
 * DTS 极速录入 (Smart Quick Entry)
 * 核心理念：一页完成 主体+对象+事件 的录入，后台自动处理关联。
 */

declare(strict_types=1);
require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

// 1. 预加载数据用于“智能联想”
// 获取所有启用主体，用于输入时的自动补全
$subjects_stmt = $pdo->query("SELECT id, subject_name, subject_type FROM cp_dts_subject WHERE subject_status = 1 ORDER BY subject_name");
$subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取所有分类，用于联动
$categories = dts_load_categories();

// 预设事件类型
$event_types = [
    'submit' => '递交材料', 'issue' => '签发/获批', 'renew' => '续期',
    'maintain' => '保养', 'replace_part' => '更换部件', 'follow_up' => '跟进',
    'other' => '其他'
];

// 检查反馈
$feedback = dts_get_feedback();
$feedback_message = '';
if ($feedback) {
    $alert_type = $feedback['type'] === 'success' ? 'success' : 'danger';
    $icon = $feedback['type'] === 'success' ? 'check' : 'ban';
    $feedback_message = "<div class='feedback-bar {$alert_type}'><i class='fas fa-{$icon}'></i> {$feedback['message']}</div>";
}
?>

<link rel="stylesheet" href="/cp/dts/css/dts_style.css">
<style>
    /* 极速录入特有样式 */
    .quick-card { border-left: 4px solid var(--primary-color); }
    .section-label { font-weight: 700; color: #555; margin-bottom: 10px; display: block; border-bottom: 1px dashed #eee; padding-bottom: 5px; }
    .dynamic-field { display: none; background: #f9fbfd; padding: 10px; border-radius: 6px; border: 1px dashed #dceefc; margin-top: 10px; }
</style>

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
    <div id="feedback-container"><?php echo $feedback_message; ?></div>

    <form action="<?php echo CP_BASE_URL; ?>dts_quick_save" method="post" class="form-horizontal" autocomplete="off">
        
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="card box-primary quick-card">
                    <div class="card-body" style="padding: 30px;">

                        <span class="section-label"><i class="fas fa-user"></i> 1. 归属主体 (Who)</span>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">主体名称 *</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control input-lg" name="subject_name_input" id="subject_name_input" 
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
                                <p class="help-block" id="subject_help_text">如果是新主体，系统将自动创建。</p>
                            </div>
                        </div>
                        
                        <div class="form-group" id="new_subject_type_group" style="display:none;">
                            <label class="col-sm-3 control-label">主体类型</label>
                            <div class="col-sm-9">
                                <label class="radio-inline"><input type="radio" name="new_subject_type" value="person" checked> 个人</label>
                                <label class="radio-inline"><input type="radio" name="new_subject_type" value="company"> 公司</label>
                                <p class="help-block text-warning"><i class="fas fa-exclamation-circle"></i> 检测到这是一个新名字，请确认其类型。</p>
                            </div>
                        </div>

                        <hr>

                        <span class="section-label"><i class="fas fa-box-open"></i> 2. 对象/事项 (What)</span>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">对象名称 *</label>
                            <div class="col-sm-9">
                                <input type="text" class="form-control" name="object_name" id="object_name" 
                                       placeholder="例如：护照、车辆Q3、临时罚单" required>
                                <p class="help-block">如果是该主体下已存在的同名对象，系统会自动合并；否则自动创建新对象。</p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">分类归属 *</label>
                            <div class="col-sm-4">
                                <select class="form-control" name="cat_main" id="cat_main" required>
                                    <option value="">-- 选择大类 --</option>
                                    <?php foreach (array_keys($categories) as $main): ?>
                                        <option value="<?php echo htmlspecialchars($main); ?>"><?php echo htmlspecialchars($main); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-5">
                                <select class="form-control" name="cat_sub" id="cat_sub">
                                    <option value="">(先选大类)</option>
                                </select>
                            </div>
                        </div>

                        <hr>

                        <span class="section-label"><i class="fas fa-calendar-check"></i> 3. 事件记录 (Event)</span>
                        
                        <div class="form-group">
                            <label class="col-sm-3 control-label">日期与类型 *</label>
                            <div class="col-sm-5">
                                <input type="date" class="form-control" name="event_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-sm-4">
                                <select class="form-control" name="event_type" required>
                                    <?php foreach ($event_types as $k => $v): ?>
                                        <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group dynamic-field" id="mileage_area">
                            <label class="col-sm-3 control-label" style="color:#d35400;">当前公里数</label>
                            <div class="col-sm-9">
                                <div class="input-group">
                                    <input type="number" class="form-control" name="mileage_now" placeholder="仪表盘读数">
                                    <span class="input-group-addon">KM</span>
                                </div>
                                <p class="help-block">您选择了“车辆”类，建议记录当前里程（非必填）。</p>
                            </div>
                        </div>
                        
                        <div class="form-group dynamic-field" id="expiry_area">
                             <label class="col-sm-3 control-label" style="color:#2980b9;">新过期日</label>
                             <div class="col-sm-9">
                                 <input type="date" class="form-control" name="expiry_date_new">
                                 <p class="help-block">您选择了“证件”类，如果是签发/续期，请填写新的有效期。</p>
                             </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">备注</label>
                            <div class="col-sm-9">
                                <textarea class="form-control" name="note" rows="2" placeholder="补充说明..."></textarea>
                            </div>
                        </div>

                    </div>
                    <div class="card-footer text-center">
                        <button type="submit" class="btn btn-success btn-lg" style="padding: 10px 40px;">
                            <i class="fas fa-check-circle"></i> 保存记录
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</section>

<script>
(function(){
    // 数据源
    const subjects = <?php echo json_encode($subjects); ?>;
    const categories = <?php echo json_encode($categories); ?>;
    
    // 元素
    const inputSubj = document.getElementById('subject_name_input');
    const hiddenSubjId = document.getElementById('subject_id_hidden');
    const newSubjGroup = document.getElementById('new_subject_type_group');
    const helpText = document.getElementById('subject_help_text');
    
    const catMain = document.getElementById('cat_main');
    const catSub = document.getElementById('cat_sub');
    const mileageArea = document.getElementById('mileage_area');
    const expiryArea = document.getElementById('expiry_area');

    // 1. 智能联想逻辑：监听输入，判断是否为库内老主体
    inputSubj.addEventListener('input', function() {
        const val = this.value.trim();
        // 尝试在 subjects 数组中找到匹配项 (完全匹配名称)
        const match = subjects.find(s => s.subject_name === val);
        
        if (match) {
            // 找到了：设置隐藏ID，隐藏类型选择
            hiddenSubjId.value = match.id;
            newSubjGroup.style.display = 'none';
            helpText.innerHTML = '<span class="text-success"><i class="fas fa-check"></i> 已关联现有主体：' + match.subject_name + '</span>';
        } else {
            // 没找到：清空ID，显示类型选择（视为新建）
            hiddenSubjId.value = '';
            if (val.length > 0) {
                newSubjGroup.style.display = 'block'; // 弹出类型选择
                helpText.innerHTML = '<span class="text-info">新主体，将自动创建。</span>';
            } else {
                newSubjGroup.style.display = 'none';
                helpText.innerText = '如果是新主体，系统将自动创建。';
            }
        }
    });

    // 2. 分类联动逻辑
    catMain.addEventListener('change', function(){
        const main = this.value;
        
        // A. 更新小类下拉
        catSub.innerHTML = '<option value="">(可选)</option>';
        if (categories[main]) {
            categories[main].forEach(sub => {
                const opt = document.createElement('option');
                opt.value = sub;
                opt.innerText = sub;
                catSub.appendChild(opt);
            });
        }
        
        // B. 动态字段显示
        // 逻辑：如果是“车辆”，跳出公里数
        if (main === '车辆') {
            $(mileageArea).slideDown(200);
        } else {
            $(mileageArea).slideUp(200);
            mileageArea.querySelector('input').value = ''; // 清空以防误存
        }

        // 逻辑：如果是“证件”，跳出过期日
        if (main === '证件') {
            $(expiryArea).slideDown(200);
        } else {
            $(expiryArea).slideUp(200);
        }
    });

})();
</script>