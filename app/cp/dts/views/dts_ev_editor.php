<?php
/**
 * DTS 事件编辑器 (Event Editor) - 新增与修改的统一入口
 * 基于极速录入的逻辑进行重构，确保数据一致性。
 */
declare(strict_types=1);
require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

// --- 1. 初始化变量与模式判断 ---
$event_id = dts_get('id');
$object_id_from_url = dts_get('object_id');
$is_edit_mode = !empty($event_id);

$event = null;
$object = null;
$subject = null;

// --- 2. 数据加载 ---
if ($is_edit_mode) {
    // 【编辑模式】: 以 event_id 为准，连接查询所有信息
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

    if (!$event) {
        dts_set_feedback('danger', '错误：找不到要编辑的事件。');
        header('Location: ' . CP_BASE_URL . 'dts_main');
        exit();
    }
    // 从事件数据中填充对象和主体信息
    $object_id = $event['object_id'];
    $object = [
        'id' => $event['object_id'],
        'object_name' => $event['object_name'],
        'object_type_main' => $event['object_type_main'],
        'object_type_sub' => $event['object_type_sub']
    ];
    $subject = ['id' => $event['subject_id'], 'subject_name' => $event['subject_name']];

} elseif ($object_id_from_url) {
    // 【新增模式-有对象】: 从 object_id 加载对象和主体信息
    $stmt = $pdo->prepare("
        SELECT o.*, s.subject_name FROM cp_dts_object o
        JOIN cp_dts_subject s ON o.subject_id = s.id
        WHERE o.id = ?
    ");
    $stmt->execute([$object_id_from_url]);
    $object = $stmt->fetch();

    if ($object) {
        $object_id = $object['id'];
        $subject = ['id' => $object['subject_id'], 'subject_name' => $object['subject_name']];
    } else {
        dts_set_feedback('danger', '错误：找不到指定对象，无法新增事件。');
        header('Location: ' . CP_BASE_URL . 'dts_object');
        exit();
    }
}
// 【新增模式-无对象】的情况在此页面不被允许，直接显示表单即可。

// 预加载分类用于联动下拉菜单
$categories = dts_load_categories();
$event_types = [
    'submit' => '递交材料', 'issue' => '签发/获批', 'renew' => '续期',
    'maintain' => '保养', 'replace_part' => '更换部件', 'follow_up' => '跟进',
    'other' => '其他'
];
$rules = dts_get_rules_for_object($pdo, $object['object_type_main'] ?? '', $object['object_type_sub'] ?? '');

?>

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1>
            <i class="fas fa-calendar-plus"></i> <?php echo $is_edit_mode ? '编辑事件' : '新增事件'; ?>
        </h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_object">对象管理</a></li>
        <?php if ($object_id): ?>
            <li><a href="<?php echo CP_BASE_URL; ?>dts_object_detail&id=<?php echo $object_id; ?>">对象详情</a></li>
        <?php endif; ?>
        <li class="active"><?php echo $is_edit_mode ? '编辑' : '新增'; ?></li>
    </ol>
</section>

<section class="content">
    <form action="<?php echo CP_BASE_URL; ?>dts_ev_save" method="post" class="form-horizontal">
        <input type="hidden" name="event_id" value="<?php echo $event['id'] ?? ''; ?>">

        <div class="row">
            <div class="col-md-12">

                <?php if ($is_edit_mode || $object_id_from_url): ?>
                    <!-- 对于编辑或指定了对象的新增，主体和对象信息是固定的 -->
                    <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                    <input type="hidden" name="object_id" value="<?php echo $object['id']; ?>">
                    <div class="alert alert-info">
                        <strong>归属:</strong> <?php echo htmlspecialchars($subject['subject_name']); ?> /
                        <strong>对象:</strong> <?php echo htmlspecialchars($object['object_name']); ?>
                    </div>
                <?php else: ?>
                    <!-- 【重要】对于全新的事件（无预设对象），需要让用户选择或创建主体和对象 -->
                    <!-- (此处简化，实际应复用极速录入的主体/对象选择逻辑) -->
                    <div class="alert alert-danger">
                        错误：未指定对象，无法创建事件。请返回对象列表操作。
                    </div>
                <?php endif; ?>

                <div class="card box-primary">
                    <div class="card-header with-border">
                        <h3 class="box-title">事件详情</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="col-sm-2 control-label">事件类型 *</label>
                            <div class="col-sm-10">
                                <select class="form-control" name="event_type" required>
                                    <option value="">-- 请选择 --</option>
                                    <?php foreach ($event_types as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo (($event['event_type'] ?? '') === $key) ? 'selected' : ''; ?>>
                                            <?php echo $value; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label">事件日期 *</label>
                            <div class="col-sm-10">
                                <input type="date" class="form-control" name="event_date"
                                       value="<?php echo $event['event_date'] ?? date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label">备注</label>
                            <div class="col-sm-10">
                                <textarea class="form-control" name="note" rows="3"><?php echo htmlspecialchars($event['note'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card box-warning">
                    <div class="card-header with-border">
                        <h3 class="box-title">可选高级属性</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="col-sm-2 control-label">关联规则</label>
                            <div class="col-sm-10">
                                <select class="form-control" name="rule_id">
                                    <option value="">-- 不使用规则 --</option>
                                    <?php foreach ($rules as $rule): ?>
                                        <option value="<?php echo $rule['id']; ?>" <?php echo (($event['rule_id'] ?? '') == $rule['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($rule['rule_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="help-block">选择规则后，系统将根据规则自动计算提醒日期。</p>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-2 control-label">新过期日</label>
                            <div class="col-sm-10">
                                <input type="date" class="form-control" name="expiry_date_new"
                                       value="<?php echo $event['expiry_date_new'] ?? ''; ?>">
                                <p class="help-block">如果此事件产生了新的证件或有效期，请在此填写。</p>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-2 control-label">当前里程</label>
                            <div class="col-sm-10">
                                <input type="number" class="form-control" name="mileage_now"
                                       value="<?php echo $event['mileage_now'] ?? ''; ?>" placeholder="例如: 88000">
                                <p class="help-block">仅适用于车辆类对象，记录事件发生时的公里数。</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="<?php echo CP_BASE_URL; ?>dts_object_detail&id=<?php echo $object_id; ?>" class="btn btn-default">
                            <i class="fas fa-times"></i> 取消
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> 保存事件
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </form>
</section>
