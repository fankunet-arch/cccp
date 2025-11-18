<?php
// /app/cp/tea/views/tea_view_store_manage.php
// <tea> Project Store Management View

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { exit('Access Denied.'); }

global $pdo; // 假设 PDO 已全局可用

// 1. 获取现有店铺列表
$stores = [];
try {
    $stmt = $pdo->query("SELECT id, store_name FROM tea_stores ORDER BY store_name ASC");
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    // 忽略错误，假设表可能不存在或权限不足，将在页面上提示
    error_log("Failed to fetch tea_stores: " . $e->getMessage());
    $_SESSION['tea_feedback'] = ['type' => 'danger', 'message' => '无法加载店铺列表，请检查数据库表 `tea_stores`。'];
}

// 2. 检查反馈消息
$feedback_html = '';
if (isset($_SESSION['tea_feedback'])) {
    $feedback = $_SESSION['tea_feedback'];
    $class = $feedback['type'] === 'success' ? 'success' : 'danger';
    // 使用新的 #feedback-bar 结构，与 som 保持一致
    $feedback_html = '<div id="feedback-bar" class="alert alert-' . $class . '">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                        ' . htmlspecialchars($feedback['message']) . '
                      </div>';
    unset($_SESSION['tea_feedback']);
}
?>

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1><tea> 店铺管理</h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li class="active"><tea> 店铺管理</li>
    </ol>
</section>

<section class="content">
    
    <?php echo $feedback_html; ?>

    <div class="row">
        
        <div class="col-md-5">
            <div class="card box-primary">
                <div class="card-header with-border">
                    <h3 class="box-title">新增店铺</h3>
                </div>
                <form action="<?php echo CP_BASE_URL; ?>tea_store_save" method="post" class="form-horizontal">
                    <div class="card-body">
                        <div class="form-group" style="padding: 10px 15px;">
                            <label for="store_name">店铺名称</label>
                            <input type="text" class="form-control" id="store_name" name="store_name" required placeholder="例如: Madrid-C, Barcelona-D" style="border-radius: 8px;">
                            <p class="help-block">店铺名称必须唯一。</p>
                        </div>
                    </div>
                    <div class="card-footer" style="text-align: right;">
                        <button type="submit" class="btn btn-primary" style="border-radius: 12px;"><i class="fas fa-plus"></i> 添加店铺</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="col-md-7">
            <div class="card box-info">
                <div class="card-header with-border">
                    <h3 class="box-title">现有店铺列表 (<?php echo count($stores); ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($stores)): ?>
                        <p class="text-muted">当前没有店铺记录。请在左侧添加。</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($stores as $store): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($store['store_name']); ?>
                                    <button class="btn btn-xs btn-danger" disabled style="pointer-events: none; border-radius: 8px;">删除</button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    </div>
</section>