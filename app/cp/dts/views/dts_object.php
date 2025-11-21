<?php
/**
 * DTS 事项总表 (Master List)
 * 提供全视角的查询、排序、筛选和状态展示。
 */

declare(strict_types=1);

require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

// --- 1. 获取筛选与排序参数 ---
$filter_subject_id = dts_get('subject_id');
$filter_main_cat   = dts_get('cat_main');
$filter_active     = dts_get('active', '1'); // 默认只看生效中
$filter_sort       = dts_get('sort', 'deadline_asc'); // 默认按截止日升序 (急->缓)
$date_start        = dts_get('date_start');
$date_end          = dts_get('date_end');

// --- 2. 构建查询逻辑 ---
$where_clauses = [];
$params = [];

// 基础筛选
if ($filter_subject_id) {
    $where_clauses[] = "o.subject_id = ?";
    $params[] = $filter_subject_id;
}
if ($filter_main_cat) {
    $where_clauses[] = "o.object_type_main = ?";
    $params[] = $filter_main_cat;
}
if ($filter_active !== 'all') {
    $where_clauses[] = "o.active_flag = ?";
    $params[] = $filter_active;
}

// 日期范围筛选 (筛选截止日 next_deadline_date)
if ($date_start && $date_end) {
    $where_clauses[] = "st.next_deadline_date BETWEEN ? AND ?";
    $params[] = $date_start;
    $params[] = $date_end;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// 排序逻辑映射
// 注意：NULL 值在 MySQL 排序中的位置可能因版本而异，通常 ASC 时 NULL 在最前。
// 我们希望把 NULL (无计划) 放到最后，所以使用 -next_deadline_date DESC 或类似技巧，这里简化处理。
$sort_sql = '';
switch ($filter_sort) {
    case 'subject_asc':   $sort_sql = 's.subject_name ASC, st.next_deadline_date ASC'; break;
    case 'deadline_asc':  $sort_sql = 'st.next_deadline_date IS NULL, st.next_deadline_date ASC, o.id DESC'; break; // 有日期的排前面
    case 'deadline_desc': $sort_sql = 'st.next_deadline_date DESC, o.id DESC'; break;
    case 'window_asc':    $sort_sql = 'st.next_window_start_date IS NULL, st.next_window_start_date ASC, st.next_deadline_date ASC'; break;
    case 'created_desc':  $sort_sql = 'o.id DESC'; break;
    default:              $sort_sql = 'st.next_deadline_date IS NULL, st.next_deadline_date ASC';
}

// --- 3. 执行查询 (关联 State 表以获取动态数据) ---
$sql = "
    SELECT o.*,
           s.subject_name, s.subject_type,
           st.next_deadline_date, 
           st.next_window_start_date, 
           st.next_window_end_date,
           st.next_cycle_date,
           st.next_mileage_suggest,
           st.last_updated_at
    FROM cp_dts_object o
    LEFT JOIN cp_dts_subject s ON o.subject_id = s.id
    LEFT JOIN cp_dts_object_state st ON o.id = st.object_id
    {$where_sql}
    ORDER BY {$sort_sql}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$objects = $stmt->fetchAll();

// 预加载下拉数据
$categories = dts_load_categories();
$subjects = $pdo->query("SELECT id, subject_name FROM cp_dts_subject WHERE subject_status = 1 ORDER BY subject_name")->fetchAll();

?>

<link rel="stylesheet" href="/cp/dts/css/dts_style.css">

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1><i class="fas fa-list-alt"></i> DTS 事项总表 <small>全局台账</small></h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_main">DTS 总览</a></li>
        <li class="active">事项列表</li>
    </ol>
</section>

<section class="content">
    
    <div class="card box-default" style="margin-bottom: 20px;">
        <div class="card-body" style="padding: 15px;">
            <form method="get" action="/cp/index.php" class="form-inline" style="display:flex; gap:15px; flex-wrap:wrap; align-items:flex-end;">
                <input type="hidden" name="action" value="dts_object">
                
                <div class="form-group">
                    <label style="display:block; font-size:12px; color:#666; margin-bottom:4px;">主体</label>
                    <select name="subject_id" class="form-control input-sm" style="width:150px;">
                        <option value="">(全部)</option>
                        <?php foreach ($subjects as $subj): ?>
                            <option value="<?php echo $subj['id']; ?>" <?php echo $filter_subject_id == $subj['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subj['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label style="display:block; font-size:12px; color:#666; margin-bottom:4px;">大类</label>
                    <select name="cat_main" class="form-control input-sm" style="width:120px;">
                        <option value="">(全部)</option>
                        <?php foreach (array_keys($categories) as $main): ?>
                            <option value="<?php echo $main; ?>" <?php echo $filter_main_cat === $main ? 'selected' : ''; ?>>
                                <?php echo $main; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label style="display:block; font-size:12px; color:#666; margin-bottom:4px;">到期日范围</label>
                    <div style="display:flex; align-items:center; gap:5px;">
                        <input type="date" name="date_start" class="form-control input-sm" value="<?php echo $date_start; ?>" style="width:140px;">
                        <span style="color:#999;">-</span>
                        <input type="date" name="date_end" class="form-control input-sm" value="<?php echo $date_end; ?>" style="width:140px;">
                    </div>
                </div>

                <div class="form-group">
                    <label style="display:block; font-size:12px; color:#666; margin-bottom:4px;">排序方式</label>
                    <select name="sort" class="form-control input-sm" style="border-color:#3c8dbc; font-weight:bold; width:180px;">
                        <option value="deadline_asc" <?php echo $filter_sort === 'deadline_asc' ? 'selected' : ''; ?>>按 到期日 (近->远)</option>
                        <option value="deadline_desc" <?php echo $filter_sort === 'deadline_desc' ? 'selected' : ''; ?>>按 到期日 (远->近)</option>
                        <option value="window_asc" <?php echo $filter_sort === 'window_asc' ? 'selected' : ''; ?>>按 窗口开启日</option>
                        <option value="subject_asc" <?php echo $filter_sort === 'subject_asc' ? 'selected' : ''; ?>>按 主体名称</option>
                        <option value="created_desc" <?php echo $filter_sort === 'created_desc' ? 'selected' : ''; ?>>按 创建时间 (新->旧)</option>
                    </select>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-sm" style="margin-bottom:2px;">
                        <i class="fas fa-filter"></i> 过滤
                    </button>
                    <a href="<?php echo CP_BASE_URL; ?>dts_object" class="btn btn-default btn-sm" style="margin-left:5px; margin-bottom:2px;">重置</a>
                </div>

                <div style="margin-left:auto; display:flex; gap:10px; margin-bottom:2px;">
                    <a href="<?php echo CP_BASE_URL; ?>dts_quick" class="btn btn-success btn-sm">
                        <i class="fas fa-bolt"></i> 极速录入
                    </a>
                    <a href="<?php echo CP_BASE_URL; ?>dts_object_form" class="btn btn-default btn-sm">
                        <i class="fas fa-plus"></i> 普通新增
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card box-default">
        <div class="card-body" style="padding:0;">
            <div class="table-responsive">
                <table class="table table-hover table-bordered" style="margin-bottom:0;">
                    <thead style="background:#f9fafc;">
                        <tr>
                            <th width="100">主体</th>
                            <th width="120">类别</th>
                            <th>事项名称 / 备注</th>
                            <th width="130" class="text-center">窗口开启日</th>
                            <th width="130" class="text-center">到期/节点日</th>
                            <th width="120" class="text-center">状态</th>
                            <th width="80" class="text-center">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($objects)): ?>
                            <tr><td colspan="7" class="text-center text-muted" style="padding:30px;">无符合条件的记录</td></tr>
                        <?php else: ?>
                            <?php foreach ($objects as $row): ?>
                                <?php
                                    // 计算状态样式
                                    $deadline = $row['next_deadline_date'] ?? $row['next_cycle_date'];
                                    $urgency_class = '';
                                    $urgency_text = '正常';
                                    
                                    if ($deadline) {
                                        $days = dts_days_from_today($deadline);
                                        if ($days < 0) {
                                            $urgency_class = 'danger'; // 过期
                                            $urgency_text = '已过期 ' . abs($days) . ' 天';
                                        } elseif ($days == 0) {
                                            $urgency_class = 'danger';
                                            $urgency_text = '今天到期';
                                        } elseif ($days <= 30) {
                                            $urgency_class = 'warning'; // 临近
                                            $urgency_text = '剩 ' . $days . ' 天';
                                        } else {
                                            $urgency_class = 'success'; // 远期
                                            $urgency_text = '待办';
                                        }
                                    } else {
                                        $urgency_text = '无计划';
                                        $urgency_class = 'default';
                                    }
                                    
                                    // 行高亮：如果非常紧急，给整行加淡红背景
                                    $row_style = ($urgency_class === 'danger') ? 'background-color:#fff5f5;' : '';
                                ?>
                                <tr style="<?php echo $row_style; ?>">
                                    <td style="vertical-align:middle;">
                                        <strong><?php echo htmlspecialchars($row['subject_name']); ?></strong>
                                        <div style="font-size:11px; color:#999;">
                                            <?php echo $row['subject_type'] === 'company' ? '<i class="fas fa-building"></i> 公司' : '<i class="fas fa-user"></i> 个人'; ?>
                                        </div>
                                    </td>

                                    <td style="vertical-align:middle;">
                                        <span class="label label-default" style="background:#eee; color:#555; border:1px solid #ddd;"><?php echo htmlspecialchars($row['object_type_main']); ?></span>
                                        <?php if (!empty($row['object_type_sub'])): ?>
                                            <div style="font-size:12px; margin-top:4px; color:#666;"><?php echo htmlspecialchars($row['object_type_sub']); ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <td style="vertical-align:middle;">
                                        <div style="font-size:15px; font-weight:600; color:#333;">
                                            <a href="<?php echo CP_BASE_URL; ?>dts_object_detail&id=<?php echo $row['id']; ?>" style="color:inherit; text-decoration:none;">
                                                <?php echo htmlspecialchars($row['object_name']); ?>
                                            </a>
                                        </div>
                                        
                                        <?php if (!empty($row['identifier'])): ?>
                                            <span style="font-size:12px; color:#888; margin-right:5px;">
                                                ID: <?php echo htmlspecialchars($row['identifier']); ?>
                                            </span>
                                        <?php endif; ?>

                                        <div style="font-size:12px; color:#777; margin-top:2px;">
                                            <?php 
                                            $notes = [];
                                            if (!empty($row['remark'])) $notes[] = htmlspecialchars($row['remark']);
                                            
                                            // [特色功能] 汽车类建议里程
                                            // 注意：仅当关联了里程规则并计算出 next_mileage_suggest 时显示
                                            if ($row['object_type_main'] === '车辆' && !empty($row['next_mileage_suggest'])) {
                                                $notes[] = '<span style="color:#d35400;"><i class="fas fa-tachometer-alt"></i> 建议下次保养里程: <strong>' . number_format($row['next_mileage_suggest']) . ' km</strong></span>';
                                            }
                                            
                                            echo implode(' | ', $notes);
                                            ?>
                                        </div>
                                    </td>

                                    <td class="text-center" style="vertical-align:middle;">
                                        <?php if (!empty($row['next_window_start_date'])): ?>
                                            <div style="color:#0073b7; font-weight:bold;">
                                                <?php echo dts_format_date($row['next_window_start_date'], 'Y-m-d'); ?>
                                            </div>
                                            <div style="font-size:11px; color:#999;">窗口开启</div>
                                        <?php else: ?>
                                            <span style="color:#ccc;">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center" style="vertical-align:middle;">
                                        <?php if ($deadline): ?>
                                            <div style="font-size:15px; font-weight:bold; color:<?php echo $urgency_class === 'danger' ? '#dd4b39' : '#333'; ?>;">
                                                <?php echo dts_format_date($deadline, 'Y-m-d'); ?>
                                            </div>
                                            <div style="font-size:11px; color:#666;">
                                                <?php echo !empty($row['next_deadline_date']) ? '截止日期' : '周期日期'; ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:#ccc;">无计划</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center" style="vertical-align:middle;">
                                        <?php if ($deadline): ?>
                                            <span class="badge badge-<?php echo $urgency_class; ?>" style="font-size:12px; padding:5px 8px; display:inline-block; width:100%;">
                                                <?php echo $urgency_text; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary" style="opacity:0.5; font-size:12px; padding:5px 8px; display:inline-block; width:100%;">闲置</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center" style="vertical-align:middle;">
                                        <a href="<?php echo CP_BASE_URL; ?>dts_object_detail&id=<?php echo $row['id']; ?>" 
                                           class="btn btn-default btn-sm" title="查看详情">
                                            <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</section>