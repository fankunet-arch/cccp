<?php
/**
 * DTS 总览页面
 * 显示未来一定时间范围内的所有重要节点
 */

declare(strict_types=1);

require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

// 获取筛选参数
$filter_days = (int)dts_get('days', 90); // 默认显示未来 90 天
$filter_subject_id = dts_get('subject_id');
$filter_type = dts_get('type'); // deadline, cycle, follow_up

// 获取所有主体
$subjects_stmt = $pdo->query("SELECT * FROM cp_dts_subject WHERE subject_status = 1 ORDER BY subject_name");
$subjects = $subjects_stmt->fetchAll();

// 构建查询：获取所有对象及其状态
$where_clauses = ["o.active_flag = 1"];
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
$today = new DateTime();
$future_date = (clone $today)->modify("+{$filter_days} days");

// 收集所有需要提醒的节点
$nodes = [];

foreach ($objects as $obj) {
    // 截止日
    if ($obj['next_deadline_date']) {
        $node_date = new DateTime($obj['next_deadline_date']);
        if ($node_date <= $future_date) {
            $nodes[] = [
                'date' => $obj['next_deadline_date'],
                'type' => 'deadline',
                'type_name' => '截止日',
                'urgency' => dts_get_urgency_class($obj['next_deadline_date']),
                'urgency_text' => dts_get_urgency_text($obj['next_deadline_date']),
                'object_id' => $obj['id'],
                'object_name' => $obj['object_name'],
                'subject_name' => $obj['subject_name'],
                'category' => $obj['object_type_main'] . ' / ' . $obj['object_type_sub'],
                'window_start' => $obj['next_window_start_date'] ?? null
            ];
        }
    }

    // 周期日
    if ($obj['next_cycle_date']) {
        $node_date = new DateTime($obj['next_cycle_date']);
        if ($node_date <= $future_date) {
            $nodes[] = [
                'date' => $obj['next_cycle_date'],
                'type' => 'cycle',
                'type_name' => '周期日',
                'urgency' => dts_get_urgency_class($obj['next_cycle_date']),
                'urgency_text' => dts_get_urgency_text($obj['next_cycle_date']),
                'object_id' => $obj['id'],
                'object_name' => $obj['object_name'],
                'subject_name' => $obj['subject_name'],
                'category' => $obj['object_type_main'] . ' / ' . $obj['object_type_sub']
            ];
        }
    }

    // 跟进日
    if ($obj['next_follow_up_date']) {
        $node_date = new DateTime($obj['next_follow_up_date']);
        if ($node_date <= $future_date) {
            $nodes[] = [
                'date' => $obj['next_follow_up_date'],
                'type' => 'follow_up',
                'type_name' => '跟进日',
                'urgency' => dts_get_urgency_class($obj['next_follow_up_date']),
                'urgency_text' => dts_get_urgency_text($obj['next_follow_up_date']),
                'object_id' => $obj['id'],
                'object_name' => $obj['object_name'],
                'subject_name' => $obj['subject_name'],
                'category' => $obj['object_type_main'] . ' / ' . $obj['object_type_sub']
            ];
        }
    }

    // 窗口开始日
    if ($obj['next_window_start_date']) {
        $node_date = new DateTime($obj['next_window_start_date']);
        if ($node_date <= $future_date) {
            $nodes[] = [
                'date' => $obj['next_window_start_date'],
                'type' => 'window_start',
                'type_name' => '可办理开始日',
                'urgency' => 'info',
                'urgency_text' => dts_get_urgency_text($obj['next_window_start_date']),
                'object_id' => $obj['id'],
                'object_name' => $obj['object_name'],
                'subject_name' => $obj['subject_name'],
                'category' => $obj['object_type_main'] . ' / ' . $obj['object_type_sub']
            ];
        }
    }
}

// 按日期排序
usort($nodes, function($a, $b) {
    return strcmp($a['date'], $b['date']);
});

// 按类型筛选
if ($filter_type) {
    $nodes = array_filter($nodes, function($node) use ($filter_type) {
        return $node['type'] === $filter_type;
    });
}

// 准备日历数据
$calendar_events = [];
foreach ($nodes as $node) {
    $color = '#3788d8'; // default
    if ($node['type'] === 'deadline') $color = '#dc3545'; // red
    if ($node['type'] === 'window_start') $color = '#28a745'; // green
    if ($node['type'] === 'cycle') $color = '#17a2b8'; // cyan
    if ($node['type'] === 'follow_up') $color = '#17a2b8'; // cyan

    $calendar_events[] = [
        'title' => $node['subject_name'] . ' ' . $node['object_name'] . ' (' . $node['type_name'] . ')',
        'start' => $node['date'],
        'color' => $color,
        'url' => CP_BASE_URL . 'dts_object_detail&id=' . $node['object_id']
    ];
}

// 获取最近操作记录（Timeline）
$timeline_stmt = $pdo->query("
    SELECT e.*, o.object_name, s.subject_name
    FROM cp_dts_event e
    LEFT JOIN cp_dts_object o ON e.object_id = o.id
    LEFT JOIN cp_dts_subject s ON e.subject_id = s.id
    ORDER BY e.created_at DESC
    LIMIT 50
");
$timeline_events = $timeline_stmt->fetchAll();

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

    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
          initialView: 'dayGridMonth',
          locale: 'zh-cn',
          headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listMonth'
          },
          buttonText: {
            today: '今天',
            month: '月',
            week: '周',
            day: '日',
            list: '列表'
          },
          events: <?php echo json_encode($calendar_events); ?>,
          eventClick: function(info) {
              if (info.event.url) {
                  window.location.href = info.event.url;
                  info.jsEvent.preventDefault();
              }
          },
          height: 'auto'
        });

        // 延迟渲染，解决 Tab 切换不显示问题
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            if (e.target.getAttribute('href') === '#calendar-view') {
                calendar.render();
            }
        });

        // 初始化也渲染一次
        setTimeout(function() { calendar.render(); }, 200);
      });
    </script>

    <!-- 视图切换 Tabs -->
    <div class="nav-tabs-custom">
        <ul class="nav nav-tabs" role="tablist" style="margin-bottom: 15px; border-bottom: 1px solid #ddd;">
            <li role="presentation" class="active"><a href="#calendar-view" aria-controls="calendar-view" role="tab" data-toggle="tab" style="padding: 10px 15px; display:block;">日历视图</a></li>
            <li role="presentation"><a href="#list-view" aria-controls="list-view" role="tab" data-toggle="tab" style="padding: 10px 15px; display:block;">列表视图</a></li>
        </ul>

        <div class="tab-content">
            <!-- 日历视图 -->
            <div role="tabpanel" class="tab-pane active" id="calendar-view">
                 <div class="card box-primary">
                    <div class="card-body">
                        <div id='calendar'></div>
                    </div>
                 </div>
            </div>

            <!-- 列表视图 -->
            <div role="tabpanel" class="tab-pane" id="list-view">
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
                                            <th width="100">类型</th>
                                            <th width="120">紧急程度</th>
                                            <th width="120">主体</th>
                                            <th>对象</th>
                                            <th width="150">分类</th>
                                            <th width="100">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($nodes as $node): ?>
                                            <tr class="urgency-row urgency-<?php echo $node['urgency']; ?>">
                                                <td>
                                                    <strong><?php echo dts_format_date($node['date'], 'Y-m-d'); ?></strong>
                                                    <?php if ($node['type'] == 'deadline' && !empty($node['window_start'])): ?>
                                                       <!-- 进度条 -->
                                                       <?php
                                                          try {
                                                              $start = new DateTime($node['window_start']);
                                                              $end = new DateTime($node['date']);
                                                              $now = new DateTime();
                                                              if ($end > $start) {
                                                                  $total = $end->getTimestamp() - $start->getTimestamp();
                                                                  $elapsed = $now->getTimestamp() - $start->getTimestamp();
                                                                  $percent = ($elapsed / $total) * 100;
                                                                  $percent = max(0, min(100, $percent));
                                                              } else {
                                                                  $percent = 100;
                                                              }
                                                          } catch (Exception $e) { $percent = 0; }
                                                       ?>
                                                       <?php if ($percent > 0 && $percent < 100): ?>
                                                       <div class="progress" style="height: 6px; margin-top: 5px; margin-bottom:0; background-color: #e9ecef; border-radius: 3px;">
                                                         <div class="progress-bar" role="progressbar" style="width: <?php echo $percent; ?>%; background-color: #28a745; height:100%; border-radius: 3px;" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                       </div>
                                                       <div style="font-size:10px; color:#666;">窗口期: <?php echo round($percent); ?>%</div>
                                                       <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $node['type']; ?>">
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
    </div>

    <!-- 全局操作日志 -->
    <div class="row" style="margin-top: 30px;">
        <div class="col-md-12">
            <div class="card box-info">
                 <div class="card-header with-border">
                    <h3 class="box-title">
                        <i class="fas fa-history"></i> 最近操作记录 (Global Timeline)
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($timeline_events)): ?>
                        <div class="alert alert-info">暂无历史记录</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th width="150">时间</th>
                                        <th width="120">主体</th>
                                        <th width="200">对象</th>
                                        <th width="150">事件类型</th>
                                        <th>详情/备注</th>
                                        <th width="80">状态</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($timeline_events as $ev): ?>
                                        <tr>
                                            <td><?php echo substr($ev['created_at'], 0, 16); ?></td>
                                            <td><?php echo htmlspecialchars($ev['subject_name']); ?></td>
                                            <td><?php echo htmlspecialchars($ev['object_name']); ?></td>
                                            <td><?php echo htmlspecialchars($ev['event_type']); ?></td>
                                            <td>
                                                <?php
                                                    $details = [];
                                                    if ($ev['mileage_now']) $details[] = "里程: {$ev['mileage_now']}km";
                                                    if ($ev['note']) $details[] = $ev['note'];
                                                    echo implode(' | ', $details);
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-success">完成</span>
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
