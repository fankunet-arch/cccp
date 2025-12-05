<?php
/**
 * Inbound Page (从 Express 批次入库)
 * 文件路径: app/mrs/views/inbound.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 获取 Express 批次列表
$express_batches = mrs_get_express_batches();

// 过滤批次：只显示有可入库包裹的批次（已清点且未全部入库）
$available_batches = [];
foreach ($express_batches as $batch) {
    // 跳过没有清点包裹的批次
    if ($batch['counted_count'] == 0) {
        continue;
    }

    // 检查是否还有可入库的包裹
    $available_pkgs = mrs_get_express_counted_packages($pdo, $batch['batch_name']);
    if (count($available_pkgs) > 0) {
        $batch['available_count'] = count($available_pkgs);
        $available_batches[] = $batch;
    }
}

// 选中的批次名称
$selected_batch = $_GET['batch'] ?? '';
$available_packages = [];

if (!empty($selected_batch)) {
    // 获取该批次中可入库的包裹（已清点但未入库）
    $available_packages = mrs_get_express_counted_packages($pdo, $selected_batch);
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>入库录入 - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
    <style>
        .package-list {
            margin-top: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        .package-item {
            padding: 10px;
            border: 1px solid #ddd;
            margin-bottom: 5px;
            background: #f9f9f9;
            display: flex;
            align-items: center;
        }
        .package-item input[type="checkbox"] {
            margin-right: 10px;
        }
        .select-all-container {
            margin: 15px 0;
            padding: 10px;
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
        }
    </style>
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>入库录入（从 Express 批次）</h1>
            <div class="header-actions">
                <a href="/mrs/ap/index.php?action=inventory_list" class="btn btn-secondary">返回库存</a>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="info-box">
                <strong>操作流程:</strong><br>
                1. 选择 Express 批次（已清点的批次）<br>
                2. 勾选要入库的包裹<br>
                3. 填写规格信息（可选）<br>
                4. 系统自动分配箱号并完成入库
            </div>

            <!-- 第一步：选择批次 -->
            <div class="form-group">
                <label for="batch_select">选择 Express 批次 <span class="required">*</span></label>
                <select id="batch_select" class="form-control" onchange="window.location.href='/mrs/ap/index.php?action=inbound&batch=' + this.value">
                    <option value="">-- 请选择批次 --</option>
                    <?php if (empty($available_batches)): ?>
                        <option value="" disabled>暂无可入库的批次</option>
                    <?php else: ?>
                        <?php foreach ($available_batches as $batch): ?>
                            <option value="<?= htmlspecialchars($batch['batch_name']) ?>"
                                    <?= $batch['batch_name'] === $selected_batch ? 'selected' : '' ?>>
                                <?= htmlspecialchars($batch['batch_name']) ?>
                                (可入库: <?= $batch['available_count'] ?> 个)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <small class="form-text" style="color: #666;">
                    只显示有已清点且未全部入库的批次
                </small>
            </div>

            <?php if (!empty($selected_batch)): ?>
                <?php if (empty($available_packages)): ?>
                    <div class="empty-state">
                        <div class="empty-state-text">批次 "<?= htmlspecialchars($selected_batch) ?>" 中没有可入库的包裹</div>
                        <small>（可能已全部入库或尚未清点）</small>
                    </div>
                <?php else: ?>
                    <!-- 第二步：选择包裹 -->
                    <form id="inboundForm" class="form-horizontal">
                        <input type="hidden" name="batch_name" value="<?= htmlspecialchars($selected_batch) ?>">

                        <h3 style="margin-top: 30px;">可入库包裹列表 (共 <?= count($available_packages) ?> 个)</h3>

                        <div class="select-all-container">
                            <label>
                                <input type="checkbox" id="selectAll">
                                全选 / 全不选
                            </label>
                        </div>

                        <div class="package-list">
                            <?php foreach ($available_packages as $pkg): ?>
                                <div class="package-item">
                                    <input type="checkbox" name="selected_packages[]"
                                           value="<?= htmlspecialchars(json_encode([
                                               'batch_name' => $pkg['batch_name'],
                                               'tracking_number' => $pkg['tracking_number'],
                                               'content_note' => $pkg['content_note']
                                           ])) ?>"
                                           class="package-checkbox">
                                    <div>
                                        <strong>单号:</strong> <?= htmlspecialchars($pkg['tracking_number']) ?> |
                                        <strong>物料:</strong> <?= htmlspecialchars($pkg['content_note'] ?? '') ?: '未填写' ?> |
                                        <strong>清点时间:</strong> <?= date('Y-m-d H:i', strtotime($pkg['counted_at'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="form-group" style="margin-top: 20px;">
                            <label for="spec_info">规格信息（可选）</label>
                            <input type="text" id="spec_info" name="spec_info" class="form-control"
                                   placeholder="例如: 20斤">
                            <small class="form-text">为所选包裹统一填写规格</small>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">确认入库</button>
                            <button type="reset" class="btn btn-secondary">重置</button>
                        </div>
                    </form>

                    <div id="resultMessage"></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // 全选功能
    document.getElementById('selectAll')?.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.package-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
    });

    // 提交表单
    document.getElementById('inboundForm')?.addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const selectedPackages = formData.getAll('selected_packages[]');

        if (selectedPackages.length === 0) {
            document.getElementById('resultMessage').innerHTML =
                '<div class="message error">请至少选择一个包裹</div>';
            return;
        }

        // 解析选中的包裹数据
        const packages = selectedPackages.map(p => JSON.parse(p));

        const data = {
            batch_name: formData.get('batch_name'),
            packages: packages,
            spec_info: formData.get('spec_info')
        };

        fetch('/mrs/ap/index.php?action=inbound_save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            const messageDiv = document.getElementById('resultMessage');

            if (result.success) {
                let msg = `<div class="message success">入库成功! 创建了 ${result.created} 个包裹记录。`;
                if (result.errors && result.errors.length > 0) {
                    msg += `<br>部分错误: ${result.errors.join(', ')}`;
                }
                msg += '</div>';
                messageDiv.innerHTML = msg;

                // 2秒后刷新页面
                setTimeout(() => {
                    window.location.href = '/mrs/ap/index.php?action=inbound&batch=' + encodeURIComponent(data.batch_name);
                }, 2000);
            } else {
                messageDiv.innerHTML = `<div class="message error">入库失败: ${result.message}</div>`;
            }
        })
        .catch(error => {
            document.getElementById('resultMessage').innerHTML =
                `<div class="message error">网络错误: ${error}</div>`;
        });
    });
    </script>
</body>
</html>
