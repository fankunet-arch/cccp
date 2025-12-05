<?php
/**
 * Shared Sidebar Component
 * 文件路径: app/mrs/views/shared/sidebar.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

$current_action = $_GET['action'] ?? 'inventory_list';
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h2>MRS 后台</h2>
        <p>欢迎, <?= htmlspecialchars($_SESSION['user_display_name'] ?? $_SESSION['user_login'] ?? 'Admin') ?></p>
    </div>

    <nav class="sidebar-nav">
        <a href="/mrs/ap/index.php?action=inventory_list"
           class="nav-link <?= $current_action === 'inventory_list' ? 'active' : '' ?>">
            库存总览
        </a>
        <a href="/mrs/ap/index.php?action=inbound"
           class="nav-link <?= $current_action === 'inbound' ? 'active' : '' ?>">
            入库录入
        </a>
        <a href="/mrs/ap/index.php?action=outbound"
           class="nav-link <?= $current_action === 'outbound' ? 'active' : '' ?>">
            出库核销
        </a>
        <a href="/mrs/ap/index.php?action=destination_manage"
           class="nav-link <?= $current_action === 'destination_manage' ? 'active' : '' ?>">
            去向管理
        </a>
        <a href="/mrs/ap/index.php?action=batch_print"
           class="nav-link <?= $current_action === 'batch_print' ? 'active' : '' ?>">
            箱贴打印
        </a>
        <a href="/mrs/ap/index.php?action=reports"
           class="nav-link <?= $current_action === 'reports' ? 'active' : '' ?>">
            统计报表
        </a>
        <a href="/express/exp/" class="nav-link">
            转Express系统
        </a>
        <a href="/mrs/ap/index.php?action=logout" class="nav-link">
            退出登录
        </a>
    </nav>

    <!-- 数据收集API -->
    <img src="https://dc.abcabc.net/wds/api/auto_collect.php?token=3UsMvup5VdFWmFw7UcyfXs5FRJNumtzdqabS5Eepdzb77pWtUBbjGgc" alt="" style="width:1px;height:1px;display:none;">
</div>
