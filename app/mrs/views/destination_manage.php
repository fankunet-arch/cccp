<?php
/**
 * Destination Management Page
 * 文件路径: app/mrs/views/destination_manage.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 获取所有去向类型
$destination_types = mrs_get_destination_types($pdo);

// 获取所有去向
$destinations = mrs_get_destinations($pdo);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>去向管理 - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
    <link rel="stylesheet" href="/mrs/ap/css/modal.css">
    <style>
        .destination-grid {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }

        .type-section {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .type-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }

        .type-title {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
        }

        .destination-list {
            display: grid;
            gap: 10px;
        }

        .destination-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f9fafb;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            transition: all 0.2s;
        }

        .destination-item:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }

        .destination-info {
            flex: 1;
        }

        .destination-name {
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .destination-details {
            font-size: 13px;
            color: #6b7280;
        }

        .destination-actions {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            padding: 6px 12px;
            font-size: 13px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>去向管理</h1>
            <div class="header-actions">
                <button type="button" class="btn btn-primary" onclick="showAddDestination()">
                    ➕ 添加去向
                </button>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="info-box">
                <strong>说明:</strong> 管理出库去向，支持退回、仓库调仓、发往门店等类型。出库时可选择具体去向，便于追踪货物流向。
            </div>

            <div class="destination-grid">
                <?php foreach ($destination_types as $type): ?>
                    <div class="type-section">
                        <div class="type-header">
                            <div class="type-title"><?= htmlspecialchars($type['type_name']) ?></div>
                            <button type="button" class="btn btn-sm btn-primary"
                                    onclick="showAddDestination('<?= $type['type_code'] ?>')">
                                添加
                            </button>
                        </div>

                        <div class="destination-list">
                            <?php
                            $type_destinations = array_filter($destinations, function($d) use ($type) {
                                return $d['type_code'] === $type['type_code'];
                            });

                            if (empty($type_destinations)):
                            ?>
                                <div class="empty-state">
                                    <div style="font-size: 32px; margin-bottom: 8px;">📦</div>
                                    <div>暂无去向</div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($type_destinations as $dest): ?>
                                    <div class="destination-item">
                                        <div class="destination-info">
                                            <div class="destination-name">
                                                <?= htmlspecialchars($dest['destination_name']) ?>
                                                <?php if ($dest['destination_code']): ?>
                                                    <span style="color: #6b7280; font-size: 13px;">
                                                        (<?= htmlspecialchars($dest['destination_code']) ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="destination-details">
                                                <?php if ($dest['contact_person']): ?>
                                                    联系人: <?= htmlspecialchars($dest['contact_person']) ?>
                                                <?php endif; ?>
                                                <?php if ($dest['contact_phone']): ?>
                                                    | 电话: <?= htmlspecialchars($dest['contact_phone']) ?>
                                                <?php endif; ?>
                                                <?php if ($dest['address']): ?>
                                                    | 地址: <?= htmlspecialchars($dest['address']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="destination-actions">
                                            <button type="button" class="btn btn-sm btn-secondary btn-icon"
                                                    onclick="editDestination(<?= $dest['destination_id'] ?>)">
                                                ✏️ 编辑
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger btn-icon"
                                                    onclick="deleteDestination(<?= $dest['destination_id'] ?>)">
                                                🗑️ 删除
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="/mrs/ap/js/modal.js"></script>
    <script>
    const destinationTypes = <?= json_encode($destination_types) ?>;
    const destinations = <?= json_encode($destinations) ?>;

    function showAddDestination(typeCode = '') {
        const typeOptions = destinationTypes.map(t =>
            `<option value="${t.type_code}" ${t.type_code === typeCode ? 'selected' : ''}>${t.type_name}</option>`
        ).join('');

        const formHtml = `
            <form id="destinationForm" style="padding: 20px;">
                <div class="modal-form-group">
                    <label class="modal-form-label">去向类型 *</label>
                    <select name="type_code" class="modal-form-control" required>
                        ${typeOptions}
                    </select>
                </div>
                <div class="modal-form-group">
                    <label class="modal-form-label">去向名称 *</label>
                    <input type="text" name="destination_name" class="modal-form-control"
                           placeholder="如：北京仓库、门店001" required>
                </div>
                <div class="modal-form-group">
                    <label class="modal-form-label">去向编码</label>
                    <input type="text" name="destination_code" class="modal-form-control"
                           placeholder="如：WH_BJ、STORE_001">
                </div>
                <div class="modal-form-group">
                    <label class="modal-form-label">联系人</label>
                    <input type="text" name="contact_person" class="modal-form-control">
                </div>
                <div class="modal-form-group">
                    <label class="modal-form-label">联系电话</label>
                    <input type="text" name="contact_phone" class="modal-form-control">
                </div>
                <div class="modal-form-group">
                    <label class="modal-form-label">地址</label>
                    <textarea name="address" class="modal-form-control" rows="2"></textarea>
                </div>
                <div class="modal-form-group">
                    <label class="modal-form-label">备注</label>
                    <textarea name="remark" class="modal-form-control" rows="2"></textarea>
                </div>
            </form>
        `;

        window.showDrawer({
            title: '添加去向',
            content: formHtml,
            footer: `
                <div class="modal-footer">
                    <button class="modal-btn modal-btn-secondary" data-action="cancel">取消</button>
                    <button class="modal-btn modal-btn-primary" onclick="submitDestination()">保存</button>
                </div>
            `
        });
    }

    function editDestination(destinationId) {
        const dest = destinations.find(d => d.destination_id == destinationId);
        if (!dest) return;

        const typeOptions = destinationTypes.map(t =>
            `<option value="${t.type_code}" ${t.type_code === dest.type_code ? 'selected' : ''}>${t.type_name}</option>`
        ).join('');

        const formHtml = `
            <form id="destinationForm" style="padding: 20px;">
                <input type="hidden" name="destination_id" value="${dest.destination_id}">
                <div class="modal-form-group">
                    <label class="modal-form-label">去向类型 *</label>
                    <select name="type_code" class="modal-form-control" required>
                        ${typeOptions}
                    </select>
                </div>
                <div class="modal-form-group">
                    <label class="modal-form-label">去向名称 *</label>
                    <input type="text" name="destination_name" class="modal-form-control"
                           value="${dest.destination_name || ''}" required>
                </div>
                <div class="modal-form-group">
                    <label class="modal-form-label">去向编码</label>
                    <input type="text" name="destination_code" class="modal-form-control"
                           value="${dest.destination_code || ''}">
                </div>
                <div class="modal-form-group">
                    <label class="modal-form-label">联系人</label>
                    <input type="text" name="contact_person" class="modal-form-control"
                           value="${dest.contact_person || ''}">
                </div>
                <div class="modal-form-group">
                    <label class="modal-form-label">联系电话</label>
                    <input type="text" name="contact_phone" class="modal-form-control"
                           value="${dest.contact_phone || ''}">
                </div>
                <div class="modal-form-group">
                    <label class="modal-form-label">地址</label>
                    <textarea name="address" class="modal-form-control" rows="2">${dest.address || ''}</textarea>
                </div>
                <div class="modal-form-group">
                    <label class="modal-form-label">备注</label>
                    <textarea name="remark" class="modal-form-control" rows="2">${dest.remark || ''}</textarea>
                </div>
            </form>
        `;

        window.showDrawer({
            title: '编辑去向',
            content: formHtml,
            footer: `
                <div class="modal-footer">
                    <button class="modal-btn modal-btn-secondary" data-action="cancel">取消</button>
                    <button class="modal-btn modal-btn-primary" onclick="submitDestination()">保存</button>
                </div>
            `
        });
    }

    async function submitDestination() {
        const form = document.getElementById('destinationForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        if (!data.destination_name || !data.type_code) {
            await showAlert('请填写必填项', '提示', 'warning');
            return;
        }

        try {
            const response = await fetch('/mrs/ap/index.php?action=destination_save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                await showAlert(result.message, '成功', 'success');
                location.reload();
            } else {
                await showAlert(result.message, '错误', 'error');
            }
        } catch (error) {
            await showAlert('网络错误: ' + error.message, '错误', 'error');
        }
    }

    async function deleteDestination(destinationId) {
        const confirmed = await showConfirm('确定要删除这个去向吗？', '确认删除', {
            confirmText: '删除',
            cancelText: '取消'
        });

        if (!confirmed) return;

        try {
            const response = await fetch('/mrs/ap/index.php?action=destination_save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    destination_id: destinationId,
                    action: 'delete'
                })
            });

            const result = await response.json();

            if (result.success) {
                await showAlert(result.message, '成功', 'success');
                location.reload();
            } else {
                await showAlert(result.message, '错误', 'error');
            }
        } catch (error) {
            await showAlert('网络错误: ' + error.message, '错误', 'error');
        }
    }
    </script>
</body>
</html>
