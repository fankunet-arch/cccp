<?php
/**
 * SKU Management Page
 * 文件路径: app/mrs/views/sku_manage.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 获取所有物料
$skus = mrs_get_all_skus($pdo);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>物料管理 - MRS 系统</title>
    <link rel="stylesheet" href="/mrs/ap/css/backend.css">
</head>
<body>
    <?php include MRS_VIEW_PATH . '/shared/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>物料管理</h1>
        </div>

        <div class="content-wrapper">
            <div class="info-box">
                <strong>说明:</strong> 添加常用物料名称,方便入库时快速选择。
            </div>

            <!-- 添加物料表单 -->
            <h3 style="margin-bottom: 15px;">添加新物料</h3>

            <form id="skuForm" class="form-horizontal">
                <div class="form-inline">
                    <div class="form-group" style="flex: 1; max-width: 400px;">
                        <input type="text" id="sku_name" name="sku_name" class="form-control"
                               placeholder="输入物料名称,例如: 香蕉、苹果" required>
                    </div>
                    <button type="submit" class="btn btn-primary">添加</button>
                </div>
            </form>

            <div id="resultMessage"></div>

            <!-- 物料列表 -->
            <h3 style="margin-top: 40px; margin-bottom: 15px;">物料列表 (共 <?= count($skus) ?> 种)</h3>

            <?php if (empty($skus)): ?>
                <div class="empty-state">
                    <div class="empty-state-text">暂无物料数据</div>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>物料名称</th>
                            <th>创建时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($skus as $sku): ?>
                            <tr>
                                <td><?= htmlspecialchars($sku['sku_name']) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($sku['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.getElementById('skuForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const data = {
            sku_name: formData.get('sku_name')
        };

        fetch('/mrs/ap/index.php?action=sku_save', {
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
                messageDiv.innerHTML = '<div class="message success">物料添加成功!</div>';

                // 清空表单
                document.getElementById('skuForm').reset();

                // 刷新页面
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                messageDiv.innerHTML = `<div class="message error">添加失败: ${result.message}</div>`;
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
