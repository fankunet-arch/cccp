<?php
/**
 * Reports Page
 * 文件路径: app/mrs/views/reports.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 默认查询当前月份
$month = $_GET['month'] ?? date('Y-m');

// 获取月度统计
$summary = mrs_get_monthly_summary($pdo, $month);
$inbound_data = mrs_get_monthly_inbound($pdo, $month);
$outbound_data = mrs_get_monthly_outbound($pdo, $month);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>统计报表 - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>统计报表</h1>
        </div>

        <div class="content-wrapper">
            <!-- 月份选择 -->
            <div class="form-group" style="max-width: 300px;">
                <label for="month_select">选择月份</label>
                <input type="month" id="month_select" class="form-control"
                       value="<?= htmlspecialchars($month) ?>"
                       onchange="window.location.href='/mrs/ap/index.php?action=reports&month=' + this.value">
            </div>

            <!-- 汇总统计 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $summary['inbound_total'] ?? 0 ?></div>
                    <div class="stat-label">入库总数 (箱)</div>
                </div>
                <div class="stat-card stat-success">
                    <div class="stat-number"><?= $summary['outbound_total'] ?? 0 ?></div>
                    <div class="stat-label">出库总数 (箱)</div>
                </div>
            </div>

            <!-- 入库明细 -->
            <h2 style="margin-top: 30px; margin-bottom: 15px;">入库明细</h2>

            <?php if (empty($inbound_data)): ?>
                <div class="empty-state">
                    <div class="empty-state-text">本月暂无入库记录</div>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>物料名称</th>
                            <th class="text-center">入库数量</th>
                            <th class="text-center">批次数</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inbound_data as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['sku_name']) ?></td>
                                <td class="text-center"><strong><?= $item['package_count'] ?></strong> 箱</td>
                                <td class="text-center"><?= $item['batch_count'] ?> 批次</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- 出库明细 -->
            <h2 style="margin-top: 40px; margin-bottom: 15px;">出库明细</h2>

            <?php if (empty($outbound_data)): ?>
                <div class="empty-state">
                    <div class="empty-state-text">本月暂无出库记录</div>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>物料名称</th>
                            <th class="text-center">出库数量</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($outbound_data as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['sku_name']) ?></td>
                                <td class="text-center"><strong><?= $item['package_count'] ?></strong> 箱</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
