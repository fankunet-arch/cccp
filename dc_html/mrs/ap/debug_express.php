<?php
/**
 * Debug page for Express batch data
 * 访问: /mrs/ap/debug_express.php
 */

define('PROJECT_ROOT', dirname(dirname(dirname(__DIR__))));
define('MRS_ENTRY', true);

require_once PROJECT_ROOT . '/app/mrs/config_mrs/env_mrs.php';
require_once MRS_LIB_PATH . '/mrs_lib.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Express 批次调试</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 30px; border-bottom: 2px solid #ddd; padding-bottom: 5px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; background: white; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #007bff; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .info-box { background: #e3f2fd; padding: 15px; margin: 15px 0; border-left: 4px solid #2196f3; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔍 Express 批次调试信息</h1>

    <?php
    try {
        // 1. 测试 Express 数据库连接
        echo "<h2>1. Express 数据库连接测试</h2>";
        $express_pdo = get_express_db_connection();

        $db_host = getenv('EXPRESS_DB_HOST') ?: (getenv('MRS_DB_HOST') ?: 'mhdlmskp2kpxguj.mysql.db');
        $db_name = getenv('EXPRESS_DB_NAME') ?: (getenv('MRS_DB_NAME') ?: 'mhdlmskp2kpxguj');

        echo "<div class='info-box'>";
        echo "<strong>✅ 连接成功！</strong><br>";
        echo "数据库主机: <code>{$db_host}</code><br>";
        echo "数据库名称: <code>{$db_name}</code>";
        echo "</div>";

        // 2. 检查 express_batch 表是否存在
        echo "<h2>2. 检查 express_batch 表</h2>";
        $stmt = $express_pdo->query("SHOW TABLES LIKE 'express_batch'");
        $table_exists = $stmt->fetch();

        if (!$table_exists) {
            echo "<div class='error'>";
            echo "❌ express_batch 表不存在！<br>";
            echo "可用的表：<br>";
            $tables = $express_pdo->query("SHOW TABLES")->fetchAll();
            foreach ($tables as $table) {
                echo "  - " . array_values($table)[0] . "<br>";
            }
            echo "</div>";
            exit;
        }

        echo "<div class='success'>✅ express_batch 表存在</div>";

        // 3. 显示所有批次
        echo "<h2>3. 所有 Express 批次</h2>";
        $stmt = $express_pdo->query("
            SELECT batch_id, batch_name, status, total_count, verified_count, counted_count, created_at
            FROM express_batch
            ORDER BY created_at DESC
            LIMIT 20
        ");

        $batches = $stmt->fetchAll();

        if (empty($batches)) {
            echo "<div class='warning'>⚠️ express_batch 表中没有数据</div>";
        } else {
            echo "<p>找到 <strong>" . count($batches) . "</strong> 个批次：</p>";
            echo "<table>";
            echo "<tr><th>ID</th><th>批次名称</th><th>状态</th><th>总数</th><th>已核验</th><th>已清点</th><th>创建时间</th></tr>";
            foreach ($batches as $batch) {
                echo "<tr>";
                echo "<td>{$batch['batch_id']}</td>";
                echo "<td>{$batch['batch_name']}</td>";
                echo "<td><strong>{$batch['status']}</strong></td>";
                echo "<td>{$batch['total_count']}</td>";
                echo "<td>{$batch['verified_count']}</td>";
                echo "<td>{$batch['counted_count']}</td>";
                echo "<td>{$batch['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }

        // 4. 统计各状态的批次数量
        echo "<h2>4. 批次状态统计</h2>";
        $stmt = $express_pdo->query("
            SELECT status, COUNT(*) as count
            FROM express_batch
            GROUP BY status
        ");

        $statuses = $stmt->fetchAll();

        echo "<table>";
        echo "<tr><th>状态值</th><th>数量</th></tr>";
        foreach ($statuses as $status) {
            echo "<tr>";
            echo "<td><code>{$status['status']}</code></td>";
            echo "<td>{$status['count']}</td>";
            echo "</tr>";
        }
        echo "</table>";

        // 5. MRS 当前查询条件
        echo "<h2>5. MRS 查询条件测试</h2>";
        echo "<div class='info-box'>";
        echo "当前 MRS 查询条件: <code>WHERE status IN ('counting', 'completed')</code>";
        echo "</div>";

        $stmt = $express_pdo->query("
            SELECT batch_id, batch_name, status, total_count, counted_count
            FROM express_batch
            WHERE status IN ('counting', 'completed')
            ORDER BY created_at DESC
        ");

        $matched_batches = $stmt->fetchAll();

        if (empty($matched_batches)) {
            echo "<div class='error'>";
            echo "❌ 没有批次匹配查询条件！<br><br>";
            echo "<strong>这就是为什么 MRS 看不到批次的原因。</strong><br><br>";
            echo "建议：检查上面的「批次状态统计」，看看实际使用的状态值是什么。<br>";
            echo "可能需要修改查询条件，比如改为:<br>";
            echo "<code>WHERE status IN ('counted', 'verified', 'completed')</code>";
            echo "</div>";
        } else {
            echo "<div class='success'>";
            echo "✅ 找到 " . count($matched_batches) . " 个匹配的批次：<br><br>";
            echo "<table>";
            echo "<tr><th>批次名称</th><th>状态</th><th>已清点</th></tr>";
            foreach ($matched_batches as $batch) {
                echo "<tr>";
                echo "<td>{$batch['batch_name']}</td>";
                echo "<td>{$batch['status']}</td>";
                echo "<td>{$batch['counted_count']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "</div>";
        }

        // 6. 检查包裹数据
        echo "<h2>6. Express 包裹统计</h2>";
        $stmt = $express_pdo->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN package_status = 'counted' THEN 1 ELSE 0 END) as counted,
                SUM(CASE WHEN package_status = 'adjusted' THEN 1 ELSE 0 END) as adjusted,
                SUM(CASE WHEN content_note IS NOT NULL AND content_note != '' THEN 1 ELSE 0 END) as with_content
            FROM express_package
        ");

        $pkg_stats = $stmt->fetch();

        echo "<div class='info-box'>";
        echo "总包裹数: <strong>{$pkg_stats['total']}</strong><br>";
        echo "已清点 (counted): <strong>{$pkg_stats['counted']}</strong><br>";
        echo "已调整 (adjusted): <strong>{$pkg_stats['adjusted']}</strong><br>";
        echo "有内容备注的: <strong>{$pkg_stats['with_content']}</strong>";
        echo "</div>";

    } catch (PDOException $e) {
        echo "<div class='error'>";
        echo "<h2>❌ 数据库错误</h2>";
        echo "<p><strong>错误信息:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>错误代码:</strong> " . $e->getCode() . "</p>";
        echo "</div>";
    } catch (Exception $e) {
        echo "<div class='error'>";
        echo "<h2>❌ 系统错误</h2>";
        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
    ?>

    <hr>
    <p style="color: #666; margin-top: 30px;">
        <a href="/mrs/ap/index.php?action=inbound">← 返回 MRS 入库页面</a>
    </p>
</body>
</html>
