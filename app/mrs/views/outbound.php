<?php
/**
 * Outbound Page
 * 文件路径: app/mrs/views/outbound.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 获取库存汇总供选择
$inventory = mrs_get_inventory_summary($pdo);

// 获取所有有效去向
$destinations = mrs_get_destinations($pdo);

// 获取搜索参数
$search_type = $_GET['search_type'] ?? '';
$search_value = $_GET['search_value'] ?? '';
$selected_sku = $_GET['sku'] ?? '';

$packages = [];
$search_mode = false;

// 如果有搜索条件，使用搜索
if (!empty($search_type) && !empty($search_value)) {
    $packages = mrs_search_instock_packages($pdo, $search_type, $search_value);
    $search_mode = true;
} elseif (!empty($selected_sku)) {
    // 如果选择了物料，加载库存明细
    $packages = mrs_get_inventory_detail($pdo, $selected_sku, 'fifo');
}

// 格式化快递单号：末尾4位红色加粗
function format_tracking_number($tracking_number) {
    $tracking_number = htmlspecialchars($tracking_number);
    if (strlen($tracking_number) <= 4) {
        return '<span style="color: #dc3545; font-weight: bold;">' . $tracking_number . '</span>';
    }
    $prefix = substr($tracking_number, 0, -4);
    $tail = substr($tracking_number, -4);
    return $prefix . '<span style="color: #dc3545; font-weight: bold;">' . $tail . '</span>';
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>出库核销 - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
    <link rel="stylesheet" href="/mrs/ap/css/modal.css">
    <style>
        .checkbox-cell {
            width: 40px;
            text-align: center;
        }
        tr.selected {
            background-color: #dbeafe !important;
        }
        .destination-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .destination-group {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 15px;
            align-items: start;
        }
        @media (max-width: 768px) {
            .destination-group {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>出库核销</h1>
            <div class="header-actions">
                <a href="/mrs/ap/index.php?action=inventory_list" class="btn btn-secondary">返回库存</a>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="info-box">
                <strong>操作说明:</strong> 可以按物料选择或使用快速搜索功能查找包裹。系统按先进先出(FIFO)排序,建议优先出库库存天数较长的包裹。
            </div>

            <!-- 步骤1: 选择物料 -->
            <div class="form-group">
                <label for="sku_select">方式1: 按物料选择</label>
                <select id="sku_select" class="form-control" onchange="loadPackages(this.value)">
                    <option value="">-- 请选择要出库的物料 --</option>
                    <?php foreach ($inventory as $item): ?>
                        <option value="<?= htmlspecialchars($item['sku_name']) ?>"
                                <?= $selected_sku === $item['sku_name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($item['sku_name']) ?> (在库: <?= $item['total_boxes'] ?> 箱)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 快速搜索 -->
            <div class="form-group" style="margin-top: 30px; padding-top: 30px; border-top: 2px solid #e9ecef;">
                <label>方式2: 快速搜索</label>
                <div style="display: flex; gap: 10px; align-items: flex-end;">
                    <div style="flex: 0 0 150px;">
                        <label for="search_type" style="font-size: 12px; color: #666;">搜索类型</label>
                        <select id="search_type" class="form-control">
                            <option value="content_note" <?= $search_type === 'content_note' ? 'selected' : '' ?>>品名</option>
                            <option value="box_number" <?= $search_type === 'box_number' ? 'selected' : '' ?>>箱号</option>
                            <option value="tracking_tail" <?= $search_type === 'tracking_tail' ? 'selected' : '' ?>>快递单尾号</option>
                            <option value="batch_name" <?= $search_type === 'batch_name' ? 'selected' : '' ?>>批次号</option>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label for="search_value" style="font-size: 12px; color: #666;">搜索内容</label>
                        <input type="text" id="search_value" class="form-control"
                               placeholder="输入搜索内容..."
                               value="<?= htmlspecialchars($search_value) ?>">
                    </div>
                    <button type="button" class="btn btn-primary" onclick="performSearch()" style="height: 38px;">
                        🔍 搜索
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="clearSearch()" style="height: 38px;">
                        清除
                    </button>
                </div>
                <?php if ($search_mode): ?>
                    <div style="margin-top: 10px; padding: 8px; background: #e3f2fd; border-radius: 4px; font-size: 14px;">
                        📌 当前搜索: <strong><?= ['content_note'=>'品名', 'box_number'=>'箱号', 'tracking_tail'=>'快递单尾号', 'batch_name'=>'批次号'][$search_type] ?></strong> = "<?= htmlspecialchars($search_value) ?>" (找到 <?= count($packages) ?> 个结果)
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($packages)): ?>
                <!-- 步骤2: 选择包裹 -->
                <h3 style="margin-top: 30px; margin-bottom: 15px;">步骤2: 选择要出库的包裹</h3>

                <div style="margin-bottom: 15px;">
                    <button type="button" class="btn btn-sm btn-secondary" onclick="selectAll()">全选</button>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="selectNone()">取消全选</button>
                    <span style="margin-left: 20px; color: #666;">
                        已选择: <strong id="selectedCount">0</strong> 箱
                    </span>
                </div>

                <form id="outboundForm">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="checkbox-cell">
                                    <input type="checkbox" id="checkAll" onchange="toggleAll(this)">
                                </th>
                                <th>批次名称</th>
                                <th>快递单号</th>
                                <th>箱号</th>
                                <th>内容备注</th>
                                <th>规格</th>
                                <th>入库时间</th>
                                <th>库存天数</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($packages as $pkg): ?>
                                <tr onclick="toggleRow(this)">
                                    <td class="checkbox-cell">
                                        <input type="checkbox" name="ledger_ids[]"
                                               value="<?= $pkg['ledger_id'] ?>"
                                               onchange="updateCount()">
                                    </td>
                                    <td><?= htmlspecialchars($pkg['batch_name']) ?></td>
                                    <td><?= format_tracking_number($pkg['tracking_number']) ?></td>
                                    <td><?= htmlspecialchars($pkg['box_number']) ?></td>
                                    <td><strong><?= htmlspecialchars($pkg['content_note']) ?></strong></td>
                                    <td><?= htmlspecialchars($pkg['spec_info']) ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($pkg['inbound_time'])) ?></td>
                                    <td><?= $pkg['days_in_stock'] ?> 天</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- 去向选择 -->
                    <div class="destination-section">
                        <h3 style="margin-top: 0; margin-bottom: 15px;">步骤3: 选择出库去向</h3>
                        <div class="destination-group">
                            <div class="form-group" style="margin: 0;">
                                <label for="destination_select">出库去向 *</label>
                                <select id="destination_select" class="form-control" required>
                                    <option value="">-- 请选择去向 --</option>
                                    <?php
                                    $grouped = [];
                                    foreach ($destinations as $dest) {
                                        $grouped[$dest['type_name']][] = $dest;
                                    }
                                    foreach ($grouped as $typeName => $dests):
                                    ?>
                                        <optgroup label="<?= htmlspecialchars($typeName) ?>">
                                            <?php foreach ($dests as $dest): ?>
                                                <option value="<?= $dest['destination_id'] ?>">
                                                    <?= htmlspecialchars($dest['destination_name']) ?>
                                                    <?php if ($dest['destination_code']): ?>
                                                        (<?= htmlspecialchars($dest['destination_code']) ?>)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="margin: 0;">
                                <label for="destination_note">去向备注（可选）</label>
                                <input type="text" id="destination_note" class="form-control"
                                       placeholder="如：退货单号、调拨单号等">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-success" onclick="submitOutbound()">
                            确认出库
                        </button>
                    </div>
                </form>

                <div id="resultMessage"></div>
            <?php elseif (!empty($selected_sku)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <div class="empty-state-text">该物料暂无库存</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="/mrs/ap/js/modal.js"></script>
    <script>
    function loadPackages(sku) {
        if (sku) {
            window.location.href = '/mrs/ap/index.php?action=outbound&sku=' + encodeURIComponent(sku);
        } else {
            window.location.href = '/mrs/ap/index.php?action=outbound';
        }
    }

    async function performSearch() {
        const searchType = document.getElementById('search_type').value;
        const searchValue = document.getElementById('search_value').value.trim();

        if (!searchValue) {
            await showAlert('请输入搜索内容', '提示', 'warning');
            return;
        }

        window.location.href = '/mrs/ap/index.php?action=outbound&search_type=' +
                                encodeURIComponent(searchType) +
                                '&search_value=' + encodeURIComponent(searchValue);
    }

    function clearSearch() {
        window.location.href = '/mrs/ap/index.php?action=outbound';
    }

    // 支持回车搜索
    document.getElementById('search_value')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            performSearch();
        }
    });

    function toggleRow(row) {
        const checkbox = row.querySelector('input[type="checkbox"]');
        if (event.target.tagName !== 'INPUT') {
            checkbox.checked = !checkbox.checked;
        }
        row.classList.toggle('selected', checkbox.checked);
        updateCount();
    }

    function toggleAll(checkAll) {
        const checkboxes = document.querySelectorAll('input[name="ledger_ids[]"]');
        checkboxes.forEach(cb => {
            cb.checked = checkAll.checked;
            cb.closest('tr').classList.toggle('selected', checkAll.checked);
        });
        updateCount();
    }

    function selectAll() {
        document.getElementById('checkAll').checked = true;
        toggleAll(document.getElementById('checkAll'));
    }

    function selectNone() {
        document.getElementById('checkAll').checked = false;
        toggleAll(document.getElementById('checkAll'));
    }

    function updateCount() {
        const count = document.querySelectorAll('input[name="ledger_ids[]"]:checked').length;
        document.getElementById('selectedCount').textContent = count;
    }

    async function submitOutbound() {
        const selected = Array.from(document.querySelectorAll('input[name="ledger_ids[]"]:checked'))
            .map(cb => cb.value);

        if (selected.length === 0) {
            await showAlert('请至少选择一个包裹', '提示', 'warning');
            return;
        }

        const destinationId = document.getElementById('destination_select').value;
        if (!destinationId) {
            await showAlert('请选择出库去向', '提示', 'warning');
            return;
        }

        const destinationNote = document.getElementById('destination_note').value.trim();

        const confirmed = await showConfirm(
            `确认出库 ${selected.length} 个包裹?`,
            '确认出库',
            {
                confirmText: '确认出库',
                cancelText: '取消',
                confirmClass: 'modal-btn-success'
            }
        );

        if (!confirmed) return;

        try {
            const response = await fetch('/mrs/ap/index.php?action=outbound_save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    ledger_ids: selected,
                    destination_id: destinationId,
                    destination_note: destinationNote
                })
            });

            const result = await response.json();

            if (result.success) {
                await showAlert(result.message, '成功', 'success');
                window.location.href = '/mrs/ap/index.php?action=inventory_list';
            } else {
                await showAlert('出库失败: ' + result.message, '错误', 'error');
            }
        } catch (error) {
            await showAlert('网络错误: ' + error.message, '错误', 'error');
        }
    }
    </script>
</body>
</html>
