<?php
/**
 * DTS 极速录入 (Smart Quick Entry) - v2.1.3 Refactored
 *
 * 职责：
 * 1. 新建主体 + 对象 + 首次事件（无参数）
 * 2. 编辑已有事件（id=...）
 * 3. [v2.1.3] 为已有对象新增事件（mode=ev_add&object_id=X）
 *
 * [v2.1.3] 注意：对象追加事件通过安全网关 dts_ops&op=ev_add&oid=X 访问
 */

declare(strict_types=1);
require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

// --- 1. 模式判断与数据加载 ---
$mode = $_GET['mode'] ?? null;
$event_id = dts_get('id');
$object_id_from_url = isset($_GET['object_id']) ? (int)$_GET['object_id'] : null;

$is_edit_mode = !empty($event_id);
$is_ev_add_mode = ($mode === 'ev_add' && $object_id_from_url > 0);

// 初始化默认值
$form_data = [
    'subject_name' => '',
    'subject_id' => '',
    'object_name' => '',
    'cat_main' => '',
    'cat_sub' => '',
    'event_date' => date('Y-m-d'),
    'event_type' => '',
    'note' => '',
    'mileage_now' => '',
    'expiry_date_new' => '',
    'rule_id' => ''
];

$page_title = '极速录入';
$rules = []; // Store available rules for the object

if ($is_edit_mode) {
    $page_title = '编辑事件';
    // Fetch event data + subject + object
    $stmt = $pdo->prepare("
        SELECT
            e.*,
            o.object_name, o.object_type_main, o.object_type_sub,
            s.id AS subject_id, s.subject_name
        FROM cp_dts_event e
        JOIN cp_dts_object o ON e.object_id = o.id
        JOIN cp_dts_subject s ON e.subject_id = s.id
        WHERE e.id = ?
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if ($event) {
        $form_data['subject_name'] = $event['subject_name'];
        $form_data['subject_id'] = $event['subject_id'];
        $form_data['object_name'] = $event['object_name'];
        $form_data['cat_main'] = $event['object_type_main'];
        $form_data['cat_sub'] = $event['object_type_sub'];
        $form_data['event_date'] = $event['event_date'];
        $form_data['event_type'] = $event['event_type'];
        $form_data['note'] = $event['note'];
        $form_data['mileage_now'] = $event['mileage_now'];
        $form_data['expiry_date_new'] = $event['expiry_date_new'];
        $form_data['rule_id'] = $event['rule_id'];

        // Load rules for this object type
        $rules = dts_get_rules_for_view($pdo, $event['object_type_main'], $event['object_type_sub']);
    }
} elseif ($is_ev_add_mode) {
    // [v2.1.3] ev_add 模式：为已有对象新增事件
    $page_title = '新增事件';

    // 查询对象信息（包含主体信息）
    $stmt = $pdo->prepare("
        SELECT
            o.id AS object_id,
            o.object_name,
            o.object_type_main,
            o.object_type_sub,
            s.id AS subject_id,
            s.subject_name,
            s.subject_type
        FROM cp_dts_object o
        LEFT JOIN cp_dts_subject s ON o.subject_id = s.id
        WHERE o.id = ? AND o.is_deleted = 0
    ");
    $stmt->execute([$object_id_from_url]);
    $object = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($object) {
        // 预填充表单数据
        $form_data['subject_name'] = $object['subject_name'];
        $form_data['subject_id'] = $object['subject_id'];
        $form_data['object_name'] = $object['object_name'];
        $form_data['cat_main'] = $object['object_type_main'];
        $form_data['cat_sub'] = $object['object_type_sub'];
        $form_data['object_id'] = $object['object_id']; // 用于隐藏字段

        // 加载该对象类型的规则列表
        $rules = dts_get_rules_for_view($pdo, $object['object_type_main'], $object['object_type_sub']);
    } else {
        // 对象不存在或已删除，显示错误并退出
        echo '<div class="content-wrapper"><section class="content">';
        echo '<div class="alert alert-danger">';
        echo '<i class="fas fa-exclamation-triangle"></i> 对象不存在或已删除，无法新增事件。';
        echo '<br><br><a href="' . CP_BASE_URL . 'dts_object" class="btn btn-primary">返回对象列表</a>';
        echo '</div></section></div>';
        return; // 退出脚本
    }
}


// --- 2. 预加载基础数据 ---
$subjects_stmt = $pdo->query("SELECT id, subject_name, subject_type FROM cp_dts_subject WHERE subject_status = 1 ORDER BY subject_name");
$subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);

$categories = dts_load_categories();

$event_types = [
    'submit' => '递交材料', 'issue' => '签发/获批', 'renew' => '续期',
    'maintain' => '保养', 'replace_part' => '更换部件', 'follow_up' => '跟进',
    'other' => '其他'
];

// Helper to get rules (since we couldn't find the original function, we implement a local one)
function dts_get_rules_for_view($pdo, $main_cat, $sub_cat) {
    try {
        $sql = "SELECT id, rule_name FROM cp_dts_rule WHERE (cat_main = ? OR cat_main = 'ALL')";
        $params = [$main_cat];

        if ($sub_cat) {
            $sql .= " AND (cat_sub IS NULL OR cat_sub = '' OR cat_sub = ?)";
            $params[] = $sub_cat;
        } else {
             $sql .= " AND (cat_sub IS NULL OR cat_sub = '')";
        }
        $sql .= " ORDER BY rule_name";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}


// 检查反馈
$feedback = dts_get_feedback();
$feedback_html = '';
if ($feedback) {
    $type = $feedback['type'] === 'success' ? 'success' : 'error';
    $feedback_html = "<div id='server-feedback' data-type='{$type}' style='display:none;'>{$feedback['message']}</div>";
}
?>

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1><i class="fas fa-bolt" style="color:#f39c12;"></i> DTS <?php echo htmlspecialchars($page_title); ?> </h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_main">DTS 时间线</a></li>
        <?php if ($is_edit_mode || $is_ev_add_mode): ?>
             <!-- If coming from edit/ev_add mode, show link back to object list -->
             <li><a href="<?php echo CP_BASE_URL; ?>dts_object">对象管理</a></li>
        <?php endif; ?>
        <li class="active"><?php echo htmlspecialchars($page_title); ?></li>
    </ol>
</section>

<section class="content">
    <?php echo $feedback_html; ?>

    <form action="<?php echo CP_BASE_URL; ?>dts_quick_save" method="post" class="form-horizontal" autocomplete="off">
        <!-- Hidden fields for Edit Mode / Redirect -->
        <input type="hidden" name="event_id" value="<?php echo htmlspecialchars((string)$event_id); ?>">

        <?php if ($is_ev_add_mode): ?>
            <!-- ev_add 模式的隐藏字段 -->
            <input type="hidden" name="mode" value="ev_add">
            <input type="hidden" name="object_id" value="<?php echo htmlspecialchars((string)($form_data['object_id'] ?? '')); ?>">
        <?php endif; ?>

        <?php
            // 设置redirect_url：优先级：URL参数 > HTTP_REFERER > 默认空
            $redirect_url = dts_get('redirect_url', '');

            // 编辑模式或 ev_add 模式：尝试返回到来源页面
            if (($event_id || $is_ev_add_mode) && empty($redirect_url)) {
                if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'dts_object_detail') !== false) {
                    $redirect_url = $_SERVER['HTTP_REFERER'];
                } elseif ($is_ev_add_mode && isset($form_data['object_id'])) {
                    // ev_add 模式：默认返回到对象详情页
                    $redirect_url = CP_BASE_URL . 'dts_object_detail&id=' . $form_data['object_id'];
                }
            }

            echo '<input type="hidden" name="redirect_url" value="'. htmlspecialchars($redirect_url) . '">';
        ?>

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
                                       list="subject_list" placeholder="输入名字（如'张'），列表自动跳出..."
                                       value="<?php echo htmlspecialchars($form_data['subject_name']); ?>" required>
                                <datalist id="subject_list">
                                    <?php foreach ($subjects as $subj): ?>
                                        <option value="<?php echo htmlspecialchars($subj['subject_name']); ?>"
                                                data-id="<?php echo $subj['id']; ?>"
                                                data-type="<?php echo $subj['subject_type']; ?>">
                                            <?php echo $subj['subject_type'] === 'company' ? '【公司】' : '【个人】'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </datalist>
                                <input type="hidden" name="subject_id" id="subject_id_hidden" value="<?php echo htmlspecialchars((string)$form_data['subject_id']); ?>">
                            </div>
                            <div class="field-hint" id="subject_help_text" style="grid-column: 1 / -1; margin-top:-10px; margin-bottom:10px; padding-left: 135px;">
                                <?php echo (!empty($form_data['subject_id'])) ? '<span class="text-success"><i class="fas fa-check"></i> 已关联现有主体</span>' : '如果是新主体，系统将自动创建。'; ?>
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
                                       placeholder="例如：护照、车辆Q3、临时罚单"
                                       value="<?php echo htmlspecialchars($form_data['object_name']); ?>" required>
                            </div>
                            <div class="field-hint" style="grid-column: 1 / -1; margin-top:-10px; margin-bottom:15px; padding-left: 135px;">
                                若主体下已有同名对象则自动合并；否则创建新对象。
                            </div>

                            <div class="compact-field-unit">
                                <label for="cat_main">大类 *</label>
                                <select class="form-control" name="cat_main" id="cat_main" required>
                                    <option value="">-- 请选择 --</option>
                                    <?php foreach (array_keys($categories) as $main): ?>
                                        <option value="<?php echo htmlspecialchars($main); ?>"
                                            <?php echo ($main === $form_data['cat_main']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($main); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="compact-field-unit">
                                <label for="cat_sub">小类</label>
                                <select class="form-control" name="cat_sub" id="cat_sub" data-selected="<?php echo htmlspecialchars((string)$form_data['cat_sub']); ?>">
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
                                <input type="date" class="form-control" name="event_date" id="event_date"
                                       value="<?php echo htmlspecialchars($form_data['event_date']); ?>" required>
                            </div>

                            <div class="compact-field-unit">
                                <label for="event_type">类型 *</label>
                                <select class="form-control" name="event_type" id="event_type" required>
                                    <?php foreach ($event_types as $k => $v): ?>
                                        <option value="<?php echo $k; ?>"
                                            <?php echo ($k === $form_data['event_type']) ? 'selected' : ''; ?>>
                                            <?php echo $v; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="compact-field-unit">
                                <label for="note">备注</label>
                                <input type="text" class="form-control" name="note" id="note"
                                       value="<?php echo htmlspecialchars((string)$form_data['note']); ?>" placeholder="补充说明...">
                            </div>

                            <div class="compact-field-unit" id="mileage_area" style="display: none;">
                                <label for="mileage_now" style="color:#d35400;">当前公里数</label>
                                <div style="flex:1; display:flex;">
                                    <input type="number" class="form-control" name="mileage_now" id="mileage_now"
                                           value="<?php echo htmlspecialchars((string)$form_data['mileage_now']); ?>" placeholder="仪表盘读数">
                                    <span style="padding: 10px; background: #eee; border:1px solid #ccc; border-left:0; border-radius:0 8px 8px 0;">KM</span>
                                </div>
                            </div>

                            <div class="compact-field-unit" id="expiry_area" style="display: none;">
                                <label for="expiry_date_new" style="color:#2980b9;">新过期日</label>
                                <input type="date" class="form-control" name="expiry_date_new" id="expiry_date_new"
                                       value="<?php echo htmlspecialchars((string)$form_data['expiry_date_new']); ?>">
                            </div>

                            <!-- Rule Selector (Hidden by default unless rules exist or user wants to see it) -->
                            <?php if (!empty($rules) || $is_edit_mode): ?>
                            <div class="compact-field-unit" style="grid-column: 1 / -1; margin-top: 10px; padding-top:10px; border-top:1px dashed #eee;">
                                <label for="rule_id" style="color:#555;">关联规则</label>
                                <select class="form-control" name="rule_id" id="rule_id">
                                    <option value="">-- 不使用规则 --</option>
                                    <?php foreach ($rules as $rule): ?>
                                        <option value="<?php echo $rule['id']; ?>"
                                            <?php echo ($rule['id'] == $form_data['rule_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($rule['rule_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="field-hint" style="margin-top:5px; padding-left:0;">
                                    <span class="text-info">
                                        <i class="fas fa-info-circle"></i>
                                        [v2.1] 如未手动选择规则，系统将根据大类/小类自动匹配默认规则（如存在）
                                    </span>
                                </div>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>

                    <div class="card-footer"
                         style="display:flex;align-items:center;justify-content:flex-end;
                                padding:16px 24px;border-top:1px solid #e9eef5;
                                background:linear-gradient(180deg,#fff,#f8fafc);
                                border-radius:0 0 12px 12px;">

                        <?php if ($is_edit_mode): ?>
                            <a href="<?php echo CP_BASE_URL; ?>dts_ev_del&id=<?php echo $event_id; ?>&confirm=1"
                               onclick="return confirm('确定要删除此事件吗？');"
                               class="btn btn-danger btn-sm" style="margin-right:auto;">
                                <i class="fas fa-trash"></i> 删除
                            </a>
                        <?php endif; ?>

                        <button type="submit"
                                style="display:inline-flex;align-items:center;justify-content:center;
                                       height:44px;padding:0 26px;border:0;border-radius:12px;
                                       background:#3b82f6;color:#fff;font-weight:700;
                                       box-shadow:0 8px 18px rgba(59,130,246,.28);">
                            <i class="fas fa-check-circle" style="margin-right:8px;"></i> <?php echo $is_edit_mode ? '保存修改' : '保存记录'; ?>
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

    // Helper to trigger event
    function triggerEvent(el, type) {
        if ('createEvent' in document) {
            var e = document.createEvent('HTMLEvents');
            e.initEvent(type, false, true);
            el.dispatchEvent(e);
        } else {
            el.fireEvent('on' + type);
        }
    }

    catMain.addEventListener('change', function(){
        const main = this.value;
        const selectedSub = catSub.dataset.selected || '';

        catSub.innerHTML = '<option value="">(可选)</option>';
        if (categories[main]) {
            categories[main].forEach(sub => {
                const opt = document.createElement('option');
                opt.value = sub;
                opt.innerText = sub;
                if (sub === selectedSub) opt.selected = true;
                catSub.appendChild(opt);
            });
        }

        if (main === '车辆') {
            $(mileageArea).css('display', 'flex').hide().slideDown(200);
        } else {
            $(mileageArea).slideUp(200);
            // Don't clear value if in edit mode and value exists? No, business logic says mileage only for cars.
            // But if I mistakenly changed catMain and change back, value is lost.
            // mileageArea.querySelector('input').value = '';
        }

        if (main === '证件') {
            $(expiryArea).css('display', 'flex').hide().slideDown(200);
        } else {
            $(expiryArea).slideUp(200);
        }
    });

    // Initial Trigger
    if (catMain.value) {
        triggerEvent(catMain, 'change');
    }

})();
</script>
