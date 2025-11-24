<?php
/**
 * DTS 总览页面
 * 显示未来一定时间范围内的所有重要节点
 *
 * [Audit Fix v2.3]
 * 1. 修复逻辑严重错误：窗口开始日过期不应显示为"危险/过期"，而应视为"窗口已开启"并切换为截止日视图。
 * 2. 合并节点：每个对象仅显示一个最优先的节点，避免列表冗余。
 * 3. [New] 强制显示已开启窗口的任务，即使截止日超出默认筛选范围。
 * 4. [New] 过滤软删除对象 (is_deleted = 0)
 */

declare(strict_types=1);

require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

// 获取筛选参数
$filter_days = (int)dts_get('days', 90); // 默认显示未来 90 天
$filter_subject_id = dts_get('subject_id');
$filter_type = dts_get('type'); // deadline, cycle, follow_up

// 获取所有主体
$subjects_stmt = $pdo->query("SELECT * FROM cp_dts_subject WHERE subject_status = 1 AND is_deleted = 0 ORDER BY subject_name");
$subjects = $subjects_stmt->fetchAll();

// 构建查询：获取所有对象及其状态
// [Audit Fix] 增加 o.is_deleted = 0 过滤
$where_clauses = ["o.active_flag = 1", "o.is_deleted = 0"];
$params = [];

if ($filter_subject_id) {
    $where_clauses[] = "o.subject_id = ?";
    $params[] = $filter_subject_id;
}

$where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

$sql = "
    SELECT o.*, s.subject_name, s.subject_type, st.*
    FROM cp_dts_object o
    LEFT JOIN cp_dts_subject s ON o.subject_id = s.id
    LEFT JOIN cp_dts_object_state st ON o.id = st.object_id
    {$where_sql}
    ORDER BY o.subject_id, o.id
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$objects = $stmt->fetchAll();

// 计算今天和未来 N 天的日期
$today_dt = new DateTime('today');
$future_dt = (clone $today_dt)->modify("+{$filter_days} days");

// 收集所有需要提醒的节点
$nodes = [];

foreach ($objects as $obj) {
    // [v2.1] 获取锁定状态
    $locked_until = $obj['locked_until_date'] ?? null;
    $is_locked = false;
    if (!empty($locked_until)) {
        try {
            $locked_dt = new DateTime($locked_until);
            if ($locked_dt >= $today_dt) {
                $is_locked = true;
            }
        } catch (Exception $e) {}
    }

    // --- 核心逻辑修复：合并节点决策 ---

    $node_data = null;

    // 提取关键日期
    $date_window_start = $obj['next_window_start_date'] ?? null;
    $date_deadline = $obj['next_deadline_date'] ?? null;
    $date_cycle = $obj['next_cycle_date'] ?? null;
    $date_follow_up = $obj['next_follow_up_date'] ?? null;

    // 决策树：确定显示哪个日期
    // 优先级 1: 窗口期逻辑 (如果有 Window Start)
    if (!empty($date_window_start)) {
        try {
            $ws_dt = new DateTime($date_window_start);

            if ($ws_dt > $today_dt) {
                // 情况 A: 窗口尚未开启 -> 显示"即将开始"
                // 仅当在筛选范围内时显示
                if ($ws_dt <= $future_dt) {
                    $days_wait = $today_dt->diff($ws_dt)->days;
                    $node_data = [
                        'date' => $date_window_start,
                        'type' => 'window_start',
                        'type_name' => '即将开始',
                        'urgency' => 'info', // 蓝色，正常提示
                        'urgency_text' => "还有 {$days_wait} 天开始",
                        'remark' => $date_deadline ? "截止日: " . $date_deadline : ''
                    ];
                }
            } else {
                // 情况 B: 窗口已开启 (Today >= Window Start) -> 切换为显示"截止日"
                // [Audit Fix] 只要窗口开了且没结束，就必须显示，无论截止日是否在筛选范围内
                // 这样避免了 "长窗口期任务进入窗口期后消失" 的问题
                if (!empty($date_deadline)) {
                    $dl_dt = new DateTime($date_deadline);

                    // 计算剩余天数
                    $diff = $today_dt->diff($dl_dt);
                    $days = $diff->invert ? -$diff->days : $diff->days;

                    // 只要没过期很久（比如超过365天），或者还在未来，就应该显示
                    // 或者更严格一点：只要窗口开了，就显示，除非已经过期并被删除了。
                    // 这里我们放宽显示条件，忽略 $filter_days

                    $urgency = 'info';
                    $urgency_text = '';

                    if ($days < 0) {
                        $urgency = 'danger';
                        $urgency_text = "已过期 " . abs($days) . " 天";
                    } else {
                        // 窗口期内的正常状态
                        if ($days <= 7) $urgency = 'danger';
                        elseif ($days <= 30) $urgency = 'warning';
                        else $urgency = 'success'; // 绿色：正常进行中，时间充裕

                        $urgency_text = "剩 {$days} 天";
                    }

                    $node_data = [
                        'date' => $date_deadline,
                        'type' => 'deadline', // 归类为截止日
                        'type_name' => '窗口期进行中',
                        'urgency' => $urgency,
                        'urgency_text' => $urgency_text,
                        'remark' => "窗口已于 {$date_window_start} 开启"
                    ];

                } else {
                    // 有开始日但没有截止日
                    $node_data = [
                        'date' => $date_window_start,
                        'type' => 'window_open',
                        'type_name' => '已开始',
                        'urgency' => 'success',
                        'urgency_text' => '进行中',
                        'remark' => "开启于 {$date_window_start}"
                    ];
                }
            }
        } catch (Exception $e) {}
    }
    // 优先级 2: 无窗口期，只有截止日/周期日/跟进日
    else {
        // 选择最近的一个日期作为主要显示
        // 这里简化逻辑：如果有 deadline 优先显示 deadline
        $target_date = $date_deadline ?: ($date_cycle ?: $date_follow_up);
        $target_type = $date_deadline ? 'deadline' : ($date_cycle ? 'cycle' : 'follow_up');
        $type_map = ['deadline' => '截止日', 'cycle' => '周期日', 'follow_up' => '跟进日'];

        if ($target_date) {
            try {
                $t_dt = new DateTime($target_date);
                // 显示条件：(在未来N天内) 或 (已过期)
                if ($t_dt <= $future_dt) {
                    $node_data = [
                        'date' => $target_date,
                        'type' => $target_type,
                        'type_name' => $type_map[$target_type] ?? '节点',
                        'urgency' => dts_get_urgency_class($target_date),
                        'urgency_text' => dts_get_urgency_text($target_date),
                        'remark' => ''
                    ];
                }
            } catch (Exception $e) {}
        }
    }

    // 如果生成了节点数据，添加到列表
    if ($node_data) {
        // 应用类型筛选
        if ($filter_type) {
             if ($filter_type === 'window_start') {
                 // 如果筛选"即将开始"，只显示 window_start 类型
                 if ($node_data['type'] !== 'window_start') continue;
             } else {
                 if ($node_data['type'] !== $filter_type) continue;
             }
        }

        $nodes[] = array_merge($node_data, [
            'object_id' => $obj['id'],
            'object_name' => $obj['object_name'],
            'subject_name' => $obj['subject_name'],
            'category' => $obj['object_type_main'] . ' / ' . $obj['object_type_sub'],
            'locked_until' => $locked_until,
            'is_locked' => $is_locked
        ]);
    }
}

// 按日期排序
usort($nodes, function($a, $b) {
    return strcmp($a['date'], $b['date']);
});

?>

<link rel="stylesheet" href="/cp/dts/css/dts_style.css">

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1><i class="fas fa-calendar-alt"></i> DTS 总览 <small>（Date Timeline System）</small></h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li class="active">DTS 总览</li>
    </ol>
</section>

<section class="content">

    <!-- 快捷导航 -->
    <div class="row">
        <div class="col-md-12">
            <div class="card box-default">
                <div class="card-body">
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <a href="<?php echo CP_BASE_URL; ?>dts_subject" class="btn btn-default">
                            <i class="fas fa-users"></i> 主体管理
                        </a>
                        <a href="<?php echo CP_BASE_URL; ?>dts_object" class="btn btn-default">
                            <i class="fas fa-folder-open"></i> 对象管理
                        </a>
                        <a href="<?php echo CP_BASE_URL; ?>dts_rule" class="btn btn-default">
                            <i class="fas fa-cogs"></i> 规则管理
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 筛选器 -->
    <div class="row">
        <div class="col-md-12">
            <div class="card box-default">
                <div class="card-body">
                    <form method="get" action="/cp/index.php" class="form-inline" style="display:flex;gap:10px;flex-wrap:wrap;">
                        <input type="hidden" name="action" value="dts_main">

                        <div class="form-group">
                            <label style="margin-right:5px;">时间范围：</label>
                            <select name="days" class="form-control">
                                <option value="30" <?php echo $filter_days == 30 ? 'selected' : ''; ?>>未来 30 天</option>
                                <option value="60" <?php echo $filter_days == 60 ? 'selected' : ''; ?>>未来 60 天</option>
                                <option value="90" <?php echo $filter_days == 90 ? 'selected' : ''; ?>>未来 90 天</option>
                                <option value="180" <?php echo $filter_days == 180 ? 'selected' : ''; ?>>未来 180 天</option>
                                <option value="365" <?php echo $filter_days == 365 ? 'selected' : ''; ?>>未来 1 年</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="margin-right:5px;">主体：</label>
                            <select name="subject_id" class="form-control">
                                <option value="">全部</option>
                                <?php foreach ($subjects as $subj): ?>
                                    <option value="<?php echo $subj['id']; ?>"
                                            <?php echo $filter_subject_id == $subj['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subj['subject_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="margin-right:5px;">类型：</label>
                            <select name="type" class="form-control">
                                <option value="">全部</option>
                                <option value="deadline" <?php echo $filter_type === 'deadline' ? 'selected' : ''; ?>>截止日</option>
                                <option value="cycle" <?php echo $filter_type === 'cycle' ? 'selected' : ''; ?>>周期日</option>
                                <option value="follow_up" <?php echo $filter_type === 'follow_up' ? 'selected' : ''; ?>>跟进日</option>
                                <option value="window_start" <?php echo $filter_type === 'window_start' ? 'selected' : ''; ?>>即将开始</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> 筛选
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 节点列表 -->
    <div class="row">
        <div class="col-md-12">
            <div class="card box-primary">
                <div class="card-header with-border">
                    <h3 class="box-title">
                        <i class="fas fa-bell"></i> 即将到来的节点（共 <?php echo count($nodes); ?> 个）
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($nodes)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 暂无即将到来的节点。
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th width="120">日期</th>
                                        <th width="120">类型</th>
                                        <th width="120">紧急程度</th>
                                        <th width="120">主体</th>
                                        <th>对象</th>
                                        <th width="150">分类</th>
                                        <th width="100">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($nodes as $node):
                                        // 动态 badge 样式映射
                                        $badge_class = 'default';
                                        switch($node['type']) {
                                            case 'window_start': $badge_class = 'info'; break;
                                            case 'deadline': $badge_class = 'danger'; break;
                                            case 'window_open': $badge_class = 'success'; break;
                                            case 'cycle': $badge_class = 'warning'; break;
                                            case 'follow_up': $badge_class = 'primary'; break;
                                        }
                                    ?>
                                        <tr class="urgency-row urgency-<?php echo $node['urgency']; ?> <?php echo $node['is_locked'] ? 'dts-locked' : ''; ?>">
                                            <td>
                                                <strong><?php echo dts_format_date($node['date'], 'Y-m-d'); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $badge_class; ?>">
                                                    <?php echo $node['type_name']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="urgency-badge urgency-<?php echo $node['urgency']; ?>">
                                                    <?php echo $node['urgency_text']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($node['subject_name']); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($node['object_name']); ?></strong>
                                                <?php if ($node['is_locked']): ?>
                                                    <span class="label label-default" style="margin-left:5px;" title="锁定至 <?php echo $node['locked_until']; ?>">
                                                        <i class="fas fa-lock"></i> 锁定中
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($node['remark'])): ?>
                                                    <div class="text-muted small" style="margin-top:2px;">
                                                        <?php echo htmlspecialchars($node['remark']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($node['category']); ?></td>
                                            <td>
                                                <a href="<?php echo CP_BASE_URL; ?>dts_object_detail&id=<?php echo $node['object_id']; ?>"
                                                   class="btn btn-xs btn-primary">
                                                    <i class="fas fa-eye"></i> 查看
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</section>
