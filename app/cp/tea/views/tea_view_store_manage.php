<?php
// /app/cp/tea/views/tea_view_store_manage.php
// <tea> Project Store Management View - Enhanced UI

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { exit('Access Denied.'); }

global $pdo;

// 1. 获取现有店铺列表
$stores = [];
try {
    $stmt = $pdo->query("SELECT id, store_name FROM tea_stores ORDER BY store_name ASC");
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    error_log("Failed to fetch tea_stores: " . $e->getMessage());
    $_SESSION['tea_feedback'] = ['type' => 'danger', 'message' => '无法加载店铺列表，请检查数据库表 `tea_stores`。'];
}

// 2. 检查反馈消息
$feedback_html = '';
if (isset($_SESSION['tea_feedback'])) {
    $feedback = $_SESSION['tea_feedback'];
    $class = $feedback['type'] === 'success' ? 'success' : 'danger';
    $icon = $feedback['type'] === 'success' ? 'check-circle' : 'exclamation-triangle';
    $feedback_html = '<div id="feedback-bar" class="feedback-bar ' . $feedback['type'] . '">
                        <i class="fas fa-' . $icon . '"></i> ' . htmlspecialchars($feedback['message']) . '
                      </div>';
    unset($_SESSION['tea_feedback']);
}
?>

<style>
/* 页面专用样式 - 现代化设计 */
.store-manage-container {
    max-width: 1200px;
    margin: 0 auto;
}

.store-manage-grid {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 24px;
    margin-top: 24px;
}

@media (max-width: 968px) {
    .store-manage-grid {
        grid-template-columns: 1fr;
    }
}

.store-card {
    background: var(--c-panel, #fff);
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    overflow: hidden;
    border: 1px solid var(--c-border, #e8ecf2);
    transition: all 0.3s ease;
}

.store-card:hover {
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}

.store-card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px 24px;
    color: white;
}

.store-card-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.store-card-body {
    padding: 24px;
}

.form-group-modern {
    margin-bottom: 20px;
}

.form-group-modern label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--c-text, #334155);
    font-size: 14px;
}

.form-control-modern {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid var(--c-border, #e2e8f0);
    border-radius: 10px;
    font-size: 15px;
    transition: all 0.2s ease;
    background: var(--c-surface, #fff);
    color: var(--c-text, #1e293b);
}

.form-control-modern:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.help-text {
    font-size: 13px;
    color: var(--c-muted, #64748b);
    margin-top: 6px;
}

.btn-modern {
    padding: 12px 24px;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-primary-modern:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
}

.store-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.store-list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid var(--c-border, #e8ecf2);
    transition: background 0.2s ease;
}

.store-list-item:hover {
    background: var(--c-surface, #f8fafc);
}

.store-list-item:last-child {
    border-bottom: none;
}

.store-name {
    font-weight: 600;
    color: var(--c-text, #1e293b);
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.store-name i {
    color: #667eea;
}

.store-count-badge {
    display: inline-block;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--c-muted, #94a3b8);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.3;
}

.feedback-bar {
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
    animation: slideInDown 0.3s ease;
}

.feedback-bar.success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #6ee7b7;
}

.feedback-bar.danger {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.page-title {
    font-size: 28px;
    font-weight: 800;
    color: var(--c-text, #1e293b);
    margin-bottom: 8px;
}

.page-subtitle {
    color: var(--c-muted, #64748b);
    font-size: 15px;
}
</style>

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1 class="page-title"><i class="fas fa-store"></i> 店铺管理</h1>
        <p class="page-subtitle">管理 TEA 项目的店铺信息</p>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li class="active">店铺管理</li>
    </ol>
</section>

<section class="content">
    <div class="store-manage-container">

        <?php echo $feedback_html; ?>

        <div class="store-manage-grid">

            <!-- 新增店铺表单 -->
            <div class="store-card">
                <div class="store-card-header">
                    <h3><i class="fas fa-plus-circle"></i> 新增店铺</h3>
                </div>
                <form action="<?php echo CP_BASE_URL; ?>tea_store_save" method="post">
                    <div class="store-card-body">
                        <div class="form-group-modern">
                            <label for="store_name">
                                <i class="fas fa-tag"></i> 店铺名称
                            </label>
                            <input
                                type="text"
                                class="form-control-modern"
                                id="store_name"
                                name="store_name"
                                required
                                placeholder="例如: Madrid-C, Barcelona-D"
                                autocomplete="off"
                            >
                            <p class="help-text">
                                <i class="fas fa-info-circle"></i> 店铺名称必须唯一
                            </p>
                        </div>
                        <button type="submit" class="btn-modern btn-primary-modern">
                            <i class="fas fa-plus"></i> 添加店铺
                        </button>
                    </div>
                </form>
            </div>

            <!-- 现有店铺列表 -->
            <div class="store-card">
                <div class="store-card-header">
                    <h3>
                        <i class="fas fa-list"></i>
                        现有店铺
                        <span class="store-count-badge"><?php echo count($stores); ?></span>
                    </h3>
                </div>
                <div class="store-card-body" style="padding: 0;">
                    <?php if (empty($stores)): ?>
                        <div class="empty-state">
                            <i class="fas fa-store-slash"></i>
                            <p>当前没有店铺记录</p>
                            <p style="font-size: 13px;">请使用左侧表单添加新店铺</p>
                        </div>
                    <?php else: ?>
                        <ul class="store-list">
                            <?php foreach ($stores as $index => $store): ?>
                                <li class="store-list-item">
                                    <div class="store-name">
                                        <i class="fas fa-store"></i>
                                        <span><?php echo htmlspecialchars($store['store_name']); ?></span>
                                    </div>
                                    <span style="color: var(--c-muted, #94a3b8); font-size: 13px;">
                                        #<?php echo str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</section>

<script>
// 自动隐藏反馈消息
document.addEventListener('DOMContentLoaded', function() {
    const feedbackBar = document.getElementById('feedback-bar');
    if (feedbackBar) {
        setTimeout(() => {
            feedbackBar.style.opacity = '0';
            setTimeout(() => feedbackBar.remove(), 300);
        }, 4000);
    }
});
</script>