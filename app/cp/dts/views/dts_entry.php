<?php
/**
 * DTS 条目管理（CP 基线）
 */

declare(strict_types=1);

require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

$keyword = trim((string) dts_get('q', ''));
$filter_type = trim((string) dts_get('type', ''));
$filter_status = dts_get('status');
$filter_date_mode = trim((string) dts_get('date_mode', ''));
$filter_show = dts_get('show');

$where = ['source = "CP"'];
$params = [];

if ($keyword !== '') {
    $where[] = '(dts_code LIKE ? OR name_zh LIKE ? OR name_en LIKE ? OR short_title LIKE ?)';
    $kw = "%{$keyword}%";
    $params = array_merge($params, [$kw, $kw, $kw, $kw]);
}

if ($filter_type !== '') {
    $where[] = 'entry_type = ?';
    $params[] = $filter_type;
}

if ($filter_status !== null && $filter_status !== '') {
    $where[] = 'status = ?';
    $params[] = (int) $filter_status;
}

if ($filter_date_mode !== '') {
    $where[] = 'date_mode = ?';
    $params[] = $filter_date_mode;
}

if ($filter_show !== null && $filter_show !== '') {
    $where[] = 'show_to_front = ?';
    $params[] = (int) $filter_show;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT * FROM cp_dts_entry {$where_sql} ORDER BY priority DESC, COALESCE(date_value, start_date) ASC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

$feedback = dts_get_feedback();
?>

<link rel="stylesheet" href="/cp/dts/css/dts_style.css">

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1><i class="fas fa-tags"></i> DTS 条目 <small>CP 基线</small></h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li class="active">DTS 条目</li>
    </ol>
</section>

<section class="content">
    <?php if ($feedback): ?>
        <div class="alert alert-<?php echo htmlspecialchars($feedback['type']); ?>">
            <?php echo htmlspecialchars($feedback['message']); ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-12">
            <div class="card box-primary">
                <div class="card-header with-border" style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                    <h3 class="box-title"><i class="fas fa-filter"></i> 筛选</h3>
                    <a class="btn btn-success btn-sm" href="<?php echo CP_BASE_URL; ?>dts_entry_form">
                        <i class="fas fa-plus-circle"></i> 新增条目
                    </a>
                </div>
                <div class="card-body">
                    <form class="form-inline" action="" method="get">
                        <input type="hidden" name="action" value="dts_entry">
                        <div class="form-group" style="margin-right:10px;">
                            <label for="q">关键词：</label>
                            <input type="text" class="form-control" name="q" id="q" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="code / 名称 / 短标题">
                        </div>
                        <div class="form-group" style="margin-right:10px;">
                            <label for="type">类型：</label>
                            <select class="form-control" name="type" id="type">
                                <option value="">全部</option>
                                <option value="holiday" <?php echo $filter_type === 'holiday' ? 'selected' : ''; ?>>节假日</option>
                                <option value="promotion" <?php echo $filter_type === 'promotion' ? 'selected' : ''; ?>>促销活动</option>
                                <option value="system" <?php echo $filter_type === 'system' ? 'selected' : ''; ?>>系统日期</option>
                                <option value="custom" <?php echo $filter_type === 'custom' ? 'selected' : ''; ?>>自定义</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-right:10px;">
                            <label for="date_mode">日期模式：</label>
                            <select class="form-control" name="date_mode" id="date_mode">
                                <option value="">全部</option>
                                <option value="single" <?php echo $filter_date_mode === 'single' ? 'selected' : ''; ?>>单日</option>
                                <option value="range" <?php echo $filter_date_mode === 'range' ? 'selected' : ''; ?>>区间</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-right:10px;">
                            <label for="status">状态：</label>
                            <select class="form-control" name="status" id="status">
                                <option value="">全部</option>
                                <option value="1" <?php echo $filter_status === '1' ? 'selected' : ''; ?>>启用</option>
                                <option value="0" <?php echo $filter_status === '0' ? 'selected' : ''; ?>>停用</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-right:10px;">
                            <label for="show">展示：</label>
                            <select class="form-control" name="show" id="show">
                                <option value="">全部</option>
                                <option value="1" <?php echo $filter_show === '1' ? 'selected' : ''; ?>>展示</option>
                                <option value="0" <?php echo $filter_show === '0' ? 'selected' : ''; ?>>不展示</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> 查询</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card box-default">
                <div class="card-header with-border">
                    <h3 class="box-title"><i class="fas fa-list"></i> 条目列表</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>code</th>
                                    <th>名称</th>
                                    <th>类型</th>
                                    <th>日期</th>
                                    <th>状态</th>
                                    <th>展示</th>
                                    <th>优先级</th>
                                    <th>语言</th>
                                    <th>端</th>
                                    <th>更新于</th>
                                    <th width="90">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($entries)): ?>
                                    <tr><td colspan="11" class="text-center text-muted">暂无数据</td></tr>
                                <?php else: ?>
                                    <?php foreach ($entries as $row): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($row['dts_code']); ?></code></td>
                                            <td>
                                                <div><strong><?php echo htmlspecialchars($row['name_zh']); ?></strong></div>
                                                <?php if (!empty($row['name_en'])): ?>
                                                    <div class="text-muted" style="font-size:12px;">EN: <?php echo htmlspecialchars($row['name_en']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($row['short_title'])): ?>
                                                    <div class="label label-default" style="margin-top:4px; display:inline-block;">短：<?php echo htmlspecialchars($row['short_title']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['entry_type']); ?></td>
                                            <td>
                                                <?php if ($row['date_mode'] === 'single'): ?>
                                                    <div><i class="far fa-calendar"></i> <?php echo htmlspecialchars($row['date_value'] ?? '-'); ?></div>
                                                <?php else: ?>
                                                    <div><i class="far fa-calendar-alt"></i> <?php echo htmlspecialchars($row['start_date'] ?? '-'); ?> ~ <?php echo htmlspecialchars($row['end_date'] ?? '-'); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ((int)$row['status'] === 1): ?>
                                                    <span class="label label-success">启用</span>
                                                <?php else: ?>
                                                    <span class="label label-default">停用</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ((int)$row['show_to_front'] === 1): ?>
                                                    <span class="label label-info">展示</span>
                                                <?php else: ?>
                                                    <span class="label label-warning">不展示</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo (int) $row['priority']; ?></td>
                                            <td style="max-width:140px;">
                                                <div class="text-muted" style="font-size:12px; white-space:normal;">
                                                    <?php echo htmlspecialchars($row['languages'] ?? '全部'); ?>
                                                </div>
                                            </td>
                                            <td style="max-width:120px;">
                                                <div class="text-muted" style="font-size:12px; white-space:normal;">
                                                    <?php echo htmlspecialchars($row['platforms'] ?? '全部'); ?>
                                                </div>
                                            </td>
                                            <td><span style="font-size:12px;" class="text-muted"><?php echo htmlspecialchars($row['updated_at']); ?></span></td>
                                            <td>
                                                <a class="btn btn-xs btn-primary" href="<?php echo CP_BASE_URL; ?>dts_entry_form&id=<?php echo $row['id']; ?>">
                                                    <i class="fas fa-edit"></i> 编辑
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
        </div>
    </div>
</section>
