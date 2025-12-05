<?php
/**
 * Batch Label Print Page
 * 文件路径: app/mrs/views/batch_print.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 获取在库批次及可打印包裹
$batches = mrs_get_instock_batches($pdo);
$selected_batch = $_GET['batch'] ?? '';
$packages = [];

if (!empty($selected_batch)) {
    $packages = mrs_get_packages_by_batch($pdo, $selected_batch, 'in_stock');
}

function mrs_tracking_tail($tracking_number)
{
    if (!$tracking_number) {
        return '----';
    }

    $tracking_number = trim((string) $tracking_number);

    if ($tracking_number === '') {
        return '----';
    }

    return substr($tracking_number, -4);
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>批次箱贴打印 - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
    <style>
        body {
            background: #f5f5f5;
        }

        .print-actions {
            display: flex;
            gap: 10px;
        }

        .print-actions .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .batch-form {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 16px;
        }

        .batch-summary {
            margin: 12px 0 20px;
            padding: 12px;
            border-radius: 6px;
            background: #e8f5e9;
            color: #1b5e20;
        }

        .print-canvas {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 18px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
        }

        .label-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(60mm, 1fr));
            gap: 12mm 10mm;
        }

        .label-card {
            border: 1.6px solid #111;
            border-radius: 6px;
            padding: 10mm 7mm;
            min-height: 54mm;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            page-break-inside: avoid;
        }

        .label-title {
            font-size: 42pt;
            font-weight: 800;
            text-align: center;
            line-height: 1.1;
            word-break: break-all;
            white-space: nowrap;
        }

        .label-meta {
            margin-top: 8mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: nowrap;
            gap: 1.5mm 3mm;
            font-size: 24pt;
            font-weight: 800;
            line-height: 1.05;
            white-space: nowrap;
        }

        .label-meta span {
            white-space: nowrap;
        }

        .label-spec {
            margin-top: 4mm;
            font-size: 14pt;
            text-align: right;
            color: #333;
        }

        @media print {
            body {
                background: white;
            }

            .sidebar,
            .page-header,
            .info-box,
            .batch-form,
            .batch-summary,
            .message,
            .print-actions button:not(.print-only) {
                display: none !important;
            }

            .main-content {
                margin: 0;
                padding: 0;
                width: auto;
            }

            .content-wrapper {
                box-shadow: none;
                border: none;
                padding: 0;
            }

            .print-canvas {
                border: none;
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>批次箱贴打印</h1>
            <div class="print-actions">
                <a href="/mrs/ap/index.php?action=inventory_list" class="btn btn-secondary">返回库存</a>
                <?php if (!empty($packages)): ?>
                    <button class="btn btn-primary print-only" onclick="window.print()">打印当前批次</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="info-box">
                选择一个已经入库的批次，生成该批次所有在库箱子的箱贴打印页。打印时系统会自动隐藏导航栏和操作按钮。
            </div>

            <?php if (empty($batches)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <div class="empty-state-text">暂无可打印的批次</div>
                    <p style="color: #666;">请先完成入库，再回到此处打印箱贴。</p>
                </div>
            <?php else: ?>
                <div class="batch-form">
                    <label for="batch_select">选择批次</label>
                    <select id="batch_select" class="form-control" onchange="onBatchChange(this.value)">
                        <option value="">-- 请选择需要打印的批次 --</option>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?= htmlspecialchars($batch['batch_name']) ?>"
                                <?= $selected_batch === $batch['batch_name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($batch['batch_name']) ?> （在库: <?= $batch['in_stock_boxes'] ?> 箱）
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!empty($selected_batch)): ?>
                    <div class="batch-summary">
                        当前批次：<strong><?= htmlspecialchars($selected_batch) ?></strong>，在库箱数：<strong><?= count($packages) ?></strong>
                    </div>

                    <?php if (empty($packages)): ?>
                        <div class="empty-state">
                            <div class="empty-state-text">该批次暂无在库箱子可打印</div>
                        </div>
                    <?php else: ?>
                        <div class="print-canvas">
                            <div class="label-grid">
                                <?php foreach ($packages as $package): ?>
                                    <?php
                                    $content = trim($package['content_note'] ?? '');
                                    $content = $content !== '' ? $content : '未填写物料';
                                    $spec = trim($package['spec_info'] ?? '');
                                    $tail = mrs_tracking_tail($package['tracking_number'] ?? '');
                                    ?>
                                    <div class="label-card">
                                        <div class="label-title"><?= htmlspecialchars($content) ?></div>
                                        <div class="label-meta">
                                            <span>箱号 <?= htmlspecialchars($package['box_number']) ?></span>
                                            <span>尾号 <?= htmlspecialchars($tail) ?></span>
                                        </div>
                                        <?php if (!empty($spec)): ?>
                                            <div class="label-spec">规格：<?= htmlspecialchars($spec) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function onBatchChange(batch) {
            const url = new URL(window.location.href);
            if (batch) {
                url.searchParams.set('batch', batch);
            } else {
                url.searchParams.delete('batch');
            }
            window.location.href = url.toString();
        }

        document.addEventListener('DOMContentLoaded', () => {
            const fitText = (el, { max = 42, min = 16, step = 0.5 } = {}) => {
                let size = max;
                el.style.fontSize = `${size}pt`;

                while (el.scrollWidth > el.clientWidth && size > min) {
                    size -= step;
                    el.style.fontSize = `${size}pt`;
                }
            };

            document.querySelectorAll('.label-title').forEach((title) => {
                fitText(title, { max: 42, min: 18, step: 0.5 });
            });

            document.querySelectorAll('.label-meta').forEach((meta) => {
                fitText(meta, { max: 24, min: 16, step: 0.5 });
            });
        });
    </script>
</body>
</html>
