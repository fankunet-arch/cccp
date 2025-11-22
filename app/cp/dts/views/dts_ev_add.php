<?php
/**
 * DTS v2.1.2 - 追加事件视图
 * 为现有对象添加新事件的专用表单
 */

// 检查反馈
$feedback = dts_get_feedback();
$feedback_html = '';
if ($feedback) {
    $type = $feedback['type'] === 'success' ? 'success' : 'error';
    $feedback_html = "<div id='server-feedback' data-type='{$type}' style='display:none;'>{$feedback['message']}</div>";
}

// 预定义事件类型
$event_types = [
    '办理' => '办理',
    '递交材料' => '递交材料',
    '签发/获批' => '签发/获批',
    '续期' => '续期',
    '保养' => '保养',
    '更换部件' => '更换部件',
    '跟进' => '跟进',
    '其他' => '其他'
];
?>

<link rel="stylesheet" href="/cp/dts/css/dts_style.css">

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1><i class="fas fa-plus-circle"></i> 新增事件 <small>（追加到现有对象）</small></h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_main">DTS 总览</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_object">对象管理</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_object_detail&id=<?php echo $object['id']; ?>">对象详情</a></li>
        <li class="active">新增事件</li>
    </ol>
</section>

<section class="content">
    <?php echo $feedback_html; ?>

    <div class="alert alert-info" style="margin-bottom: 20px;">
        <i class="fas fa-info-circle"></i>
        <strong>追加事件模式：</strong>
        正在为对象【<?php echo htmlspecialchars($object['object_name']); ?>】添加新事件。
    </div>

    <form action="<?php echo CP_BASE_URL; ?>dts_event_form" method="post" class="form-horizontal" autocomplete="off">
        <!-- 隐藏字段：对象 ID -->
        <input type="hidden" name="object_id" value="<?php echo $object['id']; ?>">

        <div class="row">
            <div class="col-md-12">

                <!-- 1. 对象信息展示（只读） -->
                <div class="card box-info">
                    <div class="card-header with-border">
                        <h3 class="box-title"><i class="fas fa-info-circle"></i> 对象信息</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>所属主体：</strong>
                                <?php echo htmlspecialchars($object['subject_name']); ?>
                            </div>
                            <div class="col-md-3">
                                <strong>对象名称：</strong>
                                <?php echo htmlspecialchars($object['object_name']); ?>
                            </div>
                            <div class="col-md-3">
                                <strong>大类：</strong>
                                <?php echo htmlspecialchars($object['object_type_main']); ?>
                            </div>
                            <div class="col-md-3">
                                <strong>小类：</strong>
                                <?php echo $object['object_type_sub'] ? htmlspecialchars($object['object_type_sub']) : '—'; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. 事件表单 -->
                <div class="card box-warning">
                    <div class="card-header with-border">
                        <h3 class="box-title"><i class="fas fa-calendar-check"></i> 事件信息</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="event_date" class="col-sm-2 control-label">事件日期 *</label>
                            <div class="col-sm-10">
                                <input type="date" class="form-control" name="event_date" id="event_date"
                                       value="<?php echo date('Y-m-d'); ?>" required
                                       style="max-width: 300px;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="event_type" class="col-sm-2 control-label">事件类型 *</label>
                            <div class="col-sm-10">
                                <select class="form-control" name="event_type" id="event_type" required
                                        style="max-width: 300px;">
                                    <?php foreach ($event_types as $value => $label): ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>">
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="rule_id" class="col-sm-2 control-label">关联规则</label>
                            <div class="col-sm-10">
                                <select class="form-control" name="rule_id" id="rule_id"
                                        style="max-width: 500px;">
                                    <option value="">（不选择，使用默认规则）</option>
                                    <?php foreach ($rules as $rule): ?>
                                        <option value="<?php echo $rule['id']; ?>">
                                            <?php echo htmlspecialchars($rule['rule_name']); ?>
                                            <?php if ($rule['cat_main']): ?>
                                                - <?php echo htmlspecialchars($rule['cat_main']); ?>
                                                <?php if ($rule['cat_sub']): ?>
                                                    / <?php echo htmlspecialchars($rule['cat_sub']); ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($rule['lock_days']): ?>
                                                <span class="text-warning">(锁定<?php echo $rule['lock_days']; ?>天)</span>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="help-block">留空则系统根据对象分类自动匹配规则</p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="expiry_date_new" class="col-sm-2 control-label">新过期日</label>
                            <div class="col-sm-10">
                                <input type="date" class="form-control" name="expiry_date_new" id="expiry_date_new"
                                       style="max-width: 300px;">
                                <p class="help-block">适用于证件换发等场景（如新护照的过期日）</p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="mileage_now" class="col-sm-2 control-label">当前里程</label>
                            <div class="col-sm-10">
                                <input type="number" class="form-control" name="mileage_now" id="mileage_now"
                                       placeholder="公里数"
                                       style="max-width: 300px;">
                                <p class="help-block">适用于车辆保养等场景</p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="note" class="col-sm-2 control-label">备注</label>
                            <div class="col-sm-10">
                                <textarea class="form-control" name="note" id="note" rows="4"
                                          placeholder="记录事件的详细信息、注意事项等..."
                                          style="max-width: 600px;"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. 操作按钮 -->
                <div class="card box-default">
                    <div class="card-body" style="text-align: center;">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-save"></i> 保存事件
                        </button>
                        <a href="<?php echo CP_BASE_URL; ?>dts_object_detail&id=<?php echo $object['id']; ?>"
                           class="btn btn-default btn-lg">
                            <i class="fas fa-times"></i> 取消
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </form>

</section>

<script>
// Flash 消息提示
document.addEventListener('DOMContentLoaded', function() {
    const feedbackDiv = document.getElementById('server-feedback');
    if (feedbackDiv) {
        const type = feedbackDiv.getAttribute('data-type');
        const message = feedbackDiv.textContent;
        if (type === 'success') {
            toastr.success(message);
        } else {
            toastr.error(message);
        }
    }
});
</script>
