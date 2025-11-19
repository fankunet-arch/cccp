<?php
/**
 * DTS 事件表单页面
 */

declare(strict_types=1);

require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

// 获取参数
$event_id = dts_get('id');
$object_id = dts_get('object_id');
$event = null;

// 如果是编辑模式，获取事件详情
if ($event_id) {
    $stmt = $pdo->prepare("SELECT * FROM cp_dts_event WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if ($event) {
        $object_id = $event['object_id'];
    }
}

// 获取对象信息
if ($object_id) {
    $stmt = $pdo->prepare("
        SELECT o.*, s.subject_name
        FROM cp_dts_object o
        LEFT JOIN cp_dts_subject s ON o.subject_id = s.id
        WHERE o.id = ?
    ");
    $stmt->execute([$object_id]);
    $object = $stmt->fetch();
} else {
    dts_set_feedback('danger', '缺少对象 ID');
    header('Location: /cp/index.php?action=dts_object');
    exit();
}

// 如果对象不存在
if (!$object) {
    dts_set_feedback('danger', '对象不存在或已被删除');
    header('Location: /cp/index.php?action=dts_object');
    exit();
}

// 获取可用规则（根据对象类别筛选）
$stmt = $pdo->prepare("
    SELECT * FROM cp_dts_rule
    WHERE rule_status = 1
      AND (cat_main = ? OR cat_main IS NULL)
      AND (cat_sub = ? OR cat_sub IS NULL)
    ORDER BY rule_name
");
$stmt->execute([$object['object_type_main'], $object['object_type_sub'] ?? '']);
$rules = $stmt->fetchAll();

$is_edit = !empty($event);

?>

<link rel="stylesheet" href="/cp/dts/css/dts_style.css">

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1><i class="fas fa-calendar-plus"></i> <?php echo $is_edit ? '编辑事件' : '新增事件'; ?></h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_object">对象管理</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_object_detail&id=<?php echo $object['id']; ?>">对象详情</a></li>
        <li class="active"><?php echo $is_edit ? '编辑事件' : '新增事件'; ?></li>
    </ol>
</section>

<section class="content">

    <div class="row">
        <div class="col-md-12">
            <div class="alert alert-info">
                <strong>对象：</strong><?php echo htmlspecialchars($object['object_name']); ?>
                （<?php echo htmlspecialchars($object['subject_name']); ?> /
                <?php echo htmlspecialchars($object['object_type_main']); ?> /
                <?php echo htmlspecialchars($object['object_type_sub'] ?? '-'); ?>）
            </div>

            <div class="card box-primary">
                <div class="card-header with-border">
                    <h3 class="box-title"><?php echo $is_edit ? '编辑事件' : '新增事件'; ?></h3>
                </div>
                <form class="form-horizontal" action="<?php echo CP_BASE_URL; ?>dts_ev_save" method="post">
                    <input type="hidden" name="event_id" value="<?php echo $event['id'] ?? ''; ?>">
                    <input type="hidden" name="object_id" value="<?php echo $object['id']; ?>">
                    <input type="hidden" name="subject_id" value="<?php echo $object['subject_id']; ?>">

                    <div class="card-body">

                        <div class="form-group compact-field-unit">
                            <label class="col-sm-2 control-label">事件类型 *</label>
                            <div class="col-sm-10">
                                <select class="form-control" name="event_type" required>
                                    <option value="">请选择</option>
                                    <option value="submit" <?php echo ($event['event_type'] ?? '') === 'submit' ? 'selected' : ''; ?>>递交材料</option>
                                    <option value="issue" <?php echo ($event['event_type'] ?? '') === 'issue' ? 'selected' : ''; ?>>签发/获批</option>
                                    <option value="renew" <?php echo ($event['event_type'] ?? '') === 'renew' ? 'selected' : ''; ?>>续期</option>
                                    <option value="maintain" <?php echo ($event['event_type'] ?? '') === 'maintain' ? 'selected' : ''; ?>>保养</option>
                                    <option value="replace_part" <?php echo ($event['event_type'] ?? '') === 'replace_part' ? 'selected' : ''; ?>>更换部件</option>
                                    <option value="follow_up" <?php echo ($event['event_type'] ?? '') === 'follow_up' ? 'selected' : ''; ?>>跟进</option>
                                    <option value="other" <?php echo ($event['event_type'] ?? '') === 'other' ? 'selected' : ''; ?>>其他</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group compact-field-unit">
                            <label class="col-sm-2 control-label">事件日期 *</label>
                            <div class="col-sm-10">
                                <input type="date" class="form-control" name="event_date"
                                       value="<?php echo $event['event_date'] ?? date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="form-group compact-field-unit">
                            <label class="col-sm-2 control-label">使用规则</label>
                            <div class="col-sm-10">
                                <select class="form-control" name="rule_id">
                                    <option value="">不使用规则</option>
                                    <?php foreach ($rules as $rule): ?>
                                        <option value="<?php echo $rule['id']; ?>"
                                                <?php echo ($event['rule_id'] ?? '') == $rule['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($rule['rule_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="help-block">选择规则后，系统将自动计算下一个时间节点</p>
                            </div>
                        </div>

                        <div class="form-group compact-field-unit">
                            <label class="col-sm-2 control-label">新过期日</label>
                            <div class="col-sm-10">
                                <input type="date" class="form-control" name="expiry_date_new"
                                       value="<?php echo $event['expiry_date_new'] ?? ''; ?>">
                                <p class="help-block">如本事件导致新证件签发，请填写新证件的过期日</p>
                            </div>
                        </div>

                        <div class="form-group compact-field-unit">
                            <label class="col-sm-2 control-label">当前里程</label>
                            <div class="col-sm-10">
                                <input type="number" class="form-control" name="mileage_now"
                                       value="<?php echo $event['mileage_now'] ?? ''; ?>" placeholder="例如：55000">
                                <p class="help-block">车辆类事件，请填写当时的里程数（公里）</p>
                            </div>
                        </div>

                        <div class="form-group compact-field-unit">
                            <label class="col-sm-2 control-label">备注</label>
                            <div class="col-sm-10">
                                <textarea class="form-control" name="note" rows="4"><?php echo htmlspecialchars($event['note'] ?? ''); ?></textarea>
                            </div>
                        </div>

                    </div>

                    <div class="card-footer">
                        <a href="<?php echo CP_BASE_URL; ?>dts_object_detail&id=<?php echo $object['id']; ?>"
                           class="btn btn-default">
                            <i class="fas fa-times"></i> 取消
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> 保存
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</section>