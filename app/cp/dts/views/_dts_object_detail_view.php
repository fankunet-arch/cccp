<?php
/**
 * DTS 对象详情页（纯视图）
 * 注意：此文件不应包含重定向逻辑
 * 所有数据准备和验证在 controller 中完成
 */

// 确保必要的变量已由 controller 设置
if (!isset($object, $events)) {
    echo '<div class="alert alert-danger">系统错误：缺少必要数据</div>';
    return;
}
?>

<link rel="stylesheet" href="/cp/dts/css/dts_style.css">

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1><i class="fas fa-clock"></i> 对象时间线 <small>（<?php echo htmlspecialchars($object['object_name']); ?>）</small></h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_main">DTS 总览</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_object">对象管理</a></li>
        <li class="active">对象详情</li>
    </ol>
</section>

<section class="content">

    <div class="row">
        <div class="col-md-12">
            <div class="card box-info">
                <div class="card-header with-border">
                    <h3 class="box-title"><i class="fas fa-info-circle"></i> 对象信息</h3>
                    <div class="box-tools">
                        <a href="<?php echo CP_BASE_URL; ?>dts_object_form&id=<?php echo $object['id']; ?>"
                           class="btn btn-sm btn-primary">
                            <i class="fas fa-edit"></i> 编辑对象
                        </a>
                        <a href="<?php echo CP_BASE_URL; ?>dts_ops&op=ev_add&oid=<?php echo $object['id']; ?>"
                           class="btn btn-sm btn-success">
                           <i class="fas fa-plus"></i> 新增事件
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>对象名称：</strong><?php echo htmlspecialchars($object['object_name']); ?>
                        </div>
                        <div class="col-md-2">
                            <strong>所属主体：</strong><?php echo htmlspecialchars($object['subject_name']); ?>
                        </div>
                        <div class="col-md-2">
                            <strong>大类：</strong><?php echo htmlspecialchars($object['object_type_main']); ?>
                        </div>
                        <div class="col-md-2">
                            <strong>小类：</strong><?php echo $object['object_type_sub'] ? htmlspecialchars($object['object_type_sub']) : '—'; ?>
                        </div>
                        <div class="col-md-3">
                            <strong>标识：</strong><?php echo htmlspecialchars((string)($object['identifier'] ?? '')) ?: '—'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($state): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card box-warning">
                <div class="card-header with-border">
                    <h3 class="box-title"><i class="fas fa-bell"></i> 当前状态与提醒</h3>
                </div>
                <div class="card-body">
                    <?php
                    // [v2.1] 检查锁定状态
                    $is_locked = false;
                    $lock_status_text = '';
                    if (!empty($state['locked_until_date'])) {
                        $today = new DateTime('today');
                        $locked_date = new DateTime($state['locked_until_date']);
                        $is_locked = $locked_date >= $today;
                        if ($is_locked) {
                            $days_left = $today->diff($locked_date)->days;
                            $lock_status_text = "锁定中，解锁日期：" . dts_format_date($state['locked_until_date']) . " (剩余 {$days_left} 天)";
                        } else {
                            $lock_status_text = "已解锁";
                        }
                    }
                    ?>
                    <?php if (!empty($state['locked_until_date'])): ?>
                    <div class="row" style="margin-bottom: 15px;">
                        <div class="col-md-12">
                            <div class="alert alert-<?php echo $is_locked ? 'warning' : 'success'; ?>" style="display:flex; align-items:center; gap:10px;">
                                <i class="fas fa-<?php echo $is_locked ? 'lock' : 'unlock'; ?>" style="font-size:24px;"></i>
                                <div>
                                    <strong><?php echo $is_locked ? '🔒 锁定状态' : '✓ 已解锁'; ?></strong><br>
                                    <span><?php echo $lock_status_text; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="row">
                        <?php if ($state['next_deadline_date']): ?>
                        <div class="col-md-4">
                            <div class="alert alert-<?php echo dts_get_urgency_class($state['next_deadline_date']); ?>">
                                <strong>截止日期：</strong><br>
                                <?php echo dts_format_date($state['next_deadline_date']); ?><br>
                                <small><?php echo dts_get_urgency_text($state['next_deadline_date']); ?></small>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($state['next_cycle_date']): ?>
                        <div class="col-md-4">
                            <div class="alert alert-<?php echo dts_get_urgency_class($state['next_cycle_date']); ?>">
                                <strong>下次周期日期：</strong><br>
                                <?php echo dts_format_date($state['next_cycle_date']); ?><br>
                                <small><?php echo dts_get_urgency_text($state['next_cycle_date']); ?></small>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($state['next_follow_up_date']): ?>
                        <div class="col-md-4">
                            <div class="alert alert-<?php echo dts_get_urgency_class($state['next_follow_up_date']); ?>">
                                <strong>下次跟进日期：</strong><br>
                                <?php echo dts_format_date($state['next_follow_up_date']); ?><br>
                                <small><?php echo dts_get_urgency_text($state['next_follow_up_date']); ?></small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($state['next_mileage_suggest']): ?>
                    <div class="row">
                        <div class="col-md-12">
                            <p><strong>建议下次里程：</strong><?php echo number_format($state['next_mileage_suggest']); ?> 公里</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-12">
            <div class="card box-default">
                <div class="card-header with-border">
                    <h3 class="box-title"><i class="fas fa-history"></i> 事件时间线（共 <?php echo count($events); ?> 条）</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($events)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 暂无事件记录。
                            <a href="<?php echo CP_BASE_URL; ?>dts_ops&op=ev_add&oid=<?php echo $object['id']; ?>">点击这里</a>
                            添加第一条事件。
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($events as $event): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-content">
                                        <div class="timeline-header">
                                            <span class="timeline-date"><?php echo dts_format_date($event['event_date']); ?></span>
                                            <span class="timeline-type badge badge-info"><?php echo htmlspecialchars($event['event_type']); ?></span>
                                        </div>
                                        <div class="timeline-body">
                                            <?php if ($event['rule_name']): ?>
                                                <p><strong>使用规则：</strong><?php echo htmlspecialchars($event['rule_name']); ?></p>
                                            <?php endif; ?>

                                            <?php if ($event['expiry_date_new']): ?>
                                                <p><strong>新过期日：</strong><?php echo dts_format_date($event['expiry_date_new']); ?></p>
                                            <?php endif; ?>

                                            <?php if ($event['mileage_now']): ?>
                                                <p><strong>当时里程：</strong><?php echo number_format($event['mileage_now']); ?> 公里</p>
                                            <?php endif; ?>

                                            <?php if ($event['note']): ?>
                                                <p><strong>备注：</strong><?php echo nl2br(htmlspecialchars($event['note'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="timeline-footer">
                                            <a href="<?php echo CP_BASE_URL; ?>dts_quick&id=<?php echo $event['id']; ?>" class="btn btn-xs btn-primary">
                                                <i class="fas fa-edit"></i> 编辑
                                            </a>
                                            <!-- [v2.1.1] 使用新的软删除action -->
                                            <form action="<?php echo CP_BASE_URL; ?>dts_timeline_delete" method="post" style="display:inline;" onsubmit="return confirm('确定删除此事件吗？\n删除后将重新计算对象状态。');">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <button type="submit" class="btn btn-xs btn-danger">
                                                    <i class="fas fa-trash"></i> 删除
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</section>
