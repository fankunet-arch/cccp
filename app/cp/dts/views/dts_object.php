<?php
/**
 * DTS 对象管理页面
 * 用于管理对象（证件/车辆/健康等）
 */

declare(strict_types=1);

// 加载 DTS 函数库
require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

// 获取筛选参数
$filter_subject_id = dts_get('subject_id');
$filter_main_cat = dts_get('cat_main');
$filter_active = dts_get('active', '1'); // 默认只显示启用的

// 获取反馈消息
$feedback = dts_get_feedback();
$feedback_message = '';
if ($feedback) {
    $alert_type = $feedback['type'] === 'success' ? 'success' : ($feedback['type'] === 'info' ? 'info' : 'danger');
    $icon = $feedback['type'] === 'success' ? 'check' : 'ban';
    $feedback_message = <<<HTML
    <div id="feedback-bar" class="feedback-bar {$alert_type}">
        <i class="fas fa-{$icon} me-2"></i> {$feedback['message']}
    </div>
HTML;
}

// 加载分类
$categories = dts_load_categories();

// 获取所有主体（用于下拉选择）
$subjects_stmt = $pdo->query("SELECT * FROM cp_dts_subject WHERE subject_status = 1 ORDER BY subject_name");
$subjects = $subjects_stmt->fetchAll();

// 构建查询
$where_clauses = [];
$params = [];

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

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// 查询对象列表
$sql = "
    SELECT o.*,
           s.subject_name,
           s.subject_type,
           COUNT(DISTINCT e.id) as event_count
    FROM cp_dts_object o
    LEFT JOIN cp_dts_subject s ON o.subject_id = s.id
    LEFT JOIN cp_dts_event e ON o.id = e.object_id
    {$where_sql}
    GROUP BY o.id
    ORDER BY o.active_flag DESC, o.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$objects = $stmt->fetchAll();

?>

<link rel="stylesheet" href="/cp/dts/css/dts_style.css">

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1><i class="fas fa-folder-open"></i> 对象管理 <small>（DTS 模块）</small></h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_main">DTS 总览</a></li>
        <li class="active">对象管理</li>
    </ol>
</section>

<section class="content">

    <div id="feedback-container">
        <?php echo $feedback_message; ?>
    </div>

    <!-- 筛选和新增按钮 -->
    <div class="row">
        <div class="col-md-12">
            <div class="card box-default">
                <div class="card-body">
                    <form method="get" action="/cp/index.php" class="form-inline" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <input type="hidden" name="action" value="dts_object">

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
                            <label style="margin-right:5px;">大类：</label>
                            <select name="cat_main" class="form-control">
                                <option value="">全部</option>
                                <?php foreach (array_keys($categories) as $main_cat): ?>
                                    <option value="<?php echo htmlspecialchars($main_cat); ?>"
                                            <?php echo $filter_main_cat === $main_cat ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($main_cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="margin-right:5px;">状态：</label>
                            <select name="active" class="form-control">
                                <option value="all" <?php echo $filter_active === 'all' ? 'selected' : ''; ?>>全部</option>
                                <option value="1" <?php echo $filter_active === '1' ? 'selected' : ''; ?>>当前使用</option>
                                <option value="0" <?php echo $filter_active === '0' ? 'selected' : ''; ?>>历史对象</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> 筛选
                        </button>

                        <a href="<?php echo CP_BASE_URL; ?>dts_object_form" class="btn btn-success" style="margin-left:auto;">
                            <i class="fas fa-plus-circle"></i> 新增对象
                        </a>

						<a href="<?php echo CP_BASE_URL; ?>dts_category_manage" class="btn btn-default">
                            <i class="fas fa-tags"></i> 管理分类
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 对象列表 -->
    <div class="row">
        <div class="col-md-12">
            <div class="card box-default">
                <div class="card-header with-border">
                    <h3 class="box-title">
                        <i class="fas fa-list"></i> 对象列表（共 <?php echo count($objects); ?> 个）
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($objects)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 暂无对象数据。请点击"新增对象"按钮添加。
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th width="60">ID</th>
                                        <th width="120">主体</th>
                                        <th>对象名称</th>
                                        <th width="100">大类</th>
                                        <th width="100">小类</th>
                                        <th width="150">标识</th>
                                        <th width="80">状态</th>
                                        <th width="80">事件数</th>
                                        <th width="200">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($objects as $obj): ?>
                                        <tr class="<?php echo $obj['active_flag'] == 0 ? 'disabled-row' : ''; ?>">
                                            <td><?php echo (int)$obj['id']; ?></td>
                                            <td>
                                                <a href="<?php echo CP_BASE_URL; ?>dts_object&subject_id=<?php echo (int)$obj['subject_id']; ?>">
                                                    <?php echo htmlspecialchars($obj['subject_name']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($obj['object_name']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($obj['object_type_main']); ?></td>
                                            <td><?php echo $obj['object_type_sub'] ? htmlspecialchars($obj['object_type_sub']) : '<span style="color:#999;">—</span>'; ?></td>
                                            <td><?php echo htmlspecialchars((string)($obj['identifier'] ?? '')) ?: '—'; ?></td>
                                            <td>
                                                <?php if ($obj['active_flag'] == 1): ?>
                                                    <span class="badge badge-success">使用中</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">历史</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo (int)$obj['event_count']; ?></span>
                                            </td>
                                            <td>
                                                <a href="<?php echo CP_BASE_URL; ?>dts_object_detail&id=<?php echo $obj['id']; ?>"
                                                   class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> 详情
                                                </a>
                                                <a href="<?php echo CP_BASE_URL; ?>dts_object_form&id=<?php echo $obj['id']; ?>"
                                                   class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i> 编辑
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

<script src="/cp/dts/js/dts_object.js"></script>
