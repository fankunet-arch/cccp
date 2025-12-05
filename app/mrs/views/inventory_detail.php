<?php
/**
 * Inventory Detail Page
 * 文件路径: app/mrs/views/inventory_detail.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

$content_note = $_GET['sku'] ?? '';

if (empty($content_note)) {
    header('Location: /mrs/ap/index.php?action=inventory_list');
    exit;
}

// 获取库存明细
$packages = mrs_get_inventory_detail($pdo, $content_note, 'fifo');
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>库存明细 - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
    <link rel="stylesheet" href="/mrs/ap/css/modal.css">
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>库存明细: <?= htmlspecialchars($content_note) ?></h1>
            <div class="header-actions">
                <a href="/mrs/ap/index.php?action=inventory_list" class="btn btn-secondary">返回</a>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="info-box">
                <strong>当前在库数量:</strong> <?= count($packages) ?> 箱
            </div>

            <?php if (empty($packages)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <div class="empty-state-text">该物料暂无库存</div>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>批次名称</th>
                            <th>快递单号</th>
                            <th>箱号</th>
                            <th>规格</th>
                            <th>入库时间</th>
                            <th>库存天数</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($packages as $pkg): ?>
                            <tr>
                                <td><?= htmlspecialchars($pkg['batch_name']) ?></td>
                                <td><?= htmlspecialchars($pkg['tracking_number']) ?></td>
                                <td><?= htmlspecialchars($pkg['box_number']) ?></td>
                                <td><?= htmlspecialchars($pkg['spec_info']) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($pkg['inbound_time'])) ?></td>
                                <td><?= $pkg['days_in_stock'] ?> 天</td>
                                <td><span class="badge badge-in-stock">在库</span></td>
                                <td>
                                    <button class="btn btn-sm btn-danger"
                                            onclick="markVoid(<?= $pkg['ledger_id'] ?>)">标记损耗</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script src="/mrs/ap/js/modal.js"></script>
    <script>
    async function markVoid(ledgerId) {
        const confirmed = await showConfirm(
            '确定要将此包裹标记为损耗/作废吗?',
            '确认标记损耗',
            {
                type: 'warning',
                confirmText: '确认',
                cancelText: '取消'
            }
        );

        if (!confirmed) return;

        // 显示输入框让用户输入损耗原因
        const formHtml = `
            <form id="voidReasonForm" style="padding: 20px;">
                <div class="modal-form-group">
                    <label class="modal-form-label">损耗原因 *</label>
                    <textarea name="reason" class="modal-form-control" rows="3"
                              placeholder="请描述损耗原因..." required></textarea>
                </div>
            </form>
        `;

        const reasonConfirmed = await showModal({
            title: '输入损耗原因',
            content: formHtml,
            footer: `
                <div class="modal-footer">
                    <button class="modal-btn modal-btn-secondary" data-action="cancel">取消</button>
                    <button class="modal-btn modal-btn-primary" onclick="submitVoid(${ledgerId})">提交</button>
                </div>
            `
        });
    }

    async function submitVoid(ledgerId) {
        const form = document.getElementById('voidReasonForm');
        const reason = form.querySelector('[name="reason"]').value.trim();

        if (!reason) {
            await showAlert('请输入损耗原因', '提示', 'warning');
            return;
        }

        try {
            const response = await fetch('/mrs/ap/index.php?action=status_change', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    ledger_id: ledgerId,
                    new_status: 'void',
                    reason: reason
                })
            });

            const data = await response.json();

            if (data.success) {
                await showAlert('操作成功', '成功', 'success');
                location.reload();
            } else {
                await showAlert('操作失败: ' + data.message, '错误', 'error');
            }
        } catch (error) {
            await showAlert('网络错误: ' + error.message, '错误', 'error');
        }

        // 关闭模态框
        window.modal.close(true);
    }
    </script>
</body>
</html>
