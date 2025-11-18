<?php
/**
 * DTS 对象详情页（时间线视图）
 */

declare(strict_types=1);

// 加载 DTS 函数库
require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

// 获取对象 ID
$object_id = dts_get('id');

if (!$object_id) {
    dts_set_feedback('danger', '缺少对象 ID');
    header('Location: /cp/index.php?action=dts_object');
    exit();
}

// 获取对象信息
$stmt = $pdo->prepare("
    SELECT o.*, s.subject_name, s.subject_type
    FROM cp_dts_object o
    LEFT JOIN cp_dts_subject s ON o.subject_id = s.id
    WHERE o.id = ?
");
$stmt->execute([$object_id]);
$object = $stmt->fetch();

if (!$object) {
    dts_set_feedback('danger', '对象不存在');
    header('Location: /cp/index.php?action=dts_object');
    exit();
}

// 获取对象的当前状态
$state = dts_get_object_state($pdo, $object_id);

// 获取对象的事件列表（按日期倒序）
$stmt = $pdo->prepare("
    SELECT e.*, r.rule_name
    FROM cp_dts_event e
    LEFT JOIN cp_dts_rule r ON e.rule_id = r.id
    WHERE e.object_id = ?
    ORDER BY e.event_date DESC, e.id DESC
");
$stmt->execute([$object_id]);
$events = $stmt->fetchAll();

?>

<link rel="stylesheet" href="/cp/dts/dts_style.css">

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

    <!-- 对象基本信息 -->
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
                        <a href="<?php echo CP_BASE_URL; ?>dts_event_form&object_id=<?php echo $object['id']; ?>"
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

    <!-- 当前状态 -->
    <?php if ($state): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card box-warning">
                <div class="card-header with-border">
                    <h3 class="box-title"><i class="fas fa-bell"></i> 当前状态与提醒</h3>
                </div>
                <div class="card-body">
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

    <!-- 事件时间线 -->
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
                            <a href="<?php echo CP_BASE_URL; ?>dts_event_form&object_id=<?php echo $object['id']; ?>">点击这里</a>
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
                                            <a href="<?php echo CP_BASE_URL; ?>dts_event_form&id=<?php echo $event['id']; ?>&object_id=<?php echo $object['id']; ?>"
                                               class="btn btn-xs btn-primary">
                                                <i class="fas fa-edit"></i> 编辑
                                            </a>
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

<style>
.timeline {
    position: relative;
    padding: 20px 0;
}
.timeline-item {
    position: relative;
    padding-left: 40px;
    margin-bottom: 30px;
}
.timeline-marker {
    position: absolute;
    left: 0;
    top: 0;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background-color: #3b82f6;
    border: 3px solid #fff;
    box-shadow: 0 0 0 2px #3b82f6;
}
.timeline-item::before {
    content: '';
    position: absolute;
    left: 7px;
    top: 16px;
    bottom: -30px;
    width: 2px;
    background-color: #e0e0e0;
}
.timeline-item:last-child::before {
    display: none;
}
.timeline-content {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 15px;
}
.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f0f0f0;
}
.timeline-date {
    font-weight: 600;
    color: #333;
}
.timeline-body p {
    margin: 5px 0;
}
.timeline-footer {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #f0f0f0;
}
.alert-danger {
    background-color: #fee;
    border-color: #fcc;
    color: #c00;
}
.alert-warning {
    background-color: #ffc;
    border-color: #fc0;
    color: #860;
}
.alert-info {
    background-color: #e7f3ff;
    border-color: #b3d9ff;
    color: #0066cc;
}
</style>
