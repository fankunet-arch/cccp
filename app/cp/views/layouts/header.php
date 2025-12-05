<?php
// /app/cp/views/layouts/header.php
// MRS-Inspired Redesign - Modern Menu Structure (Fixed Menu Toggle)

// 确保已登录
if (!function_exists('is_logged_in') || !is_logged_in()) {
    exit('Access Denied.');
}

$current_action = $_GET['action'] ?? 'dashboard';

// 定义导航项的辅助函数
function isActive($actions, $current_action) {
    if (in_array($current_action, (array)$actions)) {
        return 'active';
    }
    return '';
}

function isMenuOpen($actions, $current_action) {
    if (in_array($current_action, (array)$actions)) {
        return 'menu-open';
    }
    return '';
}

// Sushisom 模块的 action 列表
$som_actions = [
    'som_add',
    'som_salary_add',
    'som_report_store',
    'som_report_investor'
];

// <tea> 模块的 action 列表
$tea_actions = [
    'tea_dashboard',
    'tea_add',
    'tea_save',
    'tea_report_investor',
    'tea_store_manage',
    'tea_store_save',
];

// DTS 模块的 action 列表
$dts_actions = [
    'dts_main',
    'dts_quick',
    'dts_subject',
    'dts_subject_save',
    'dts_subject_get_data',
    'dts_object',
    'dts_object_form',
    'dts_object_detail',
    'dts_object_save',
    'dts_rule',
    'dts_ev_edit',
    'dts_ev_save',
    'dts_ev_del',
    'dts_category_manage',
    'dts_category_save',
];

$is_som_active = isActive($som_actions, $current_action);
$is_tea_active = isActive($tea_actions, $current_action);
$is_dts_active = isActive($dts_actions, $current_action);

// 根据当前模块确定项目名称
$project_name = 'ABCABC CP';
if ($is_som_active) {
    $project_name = 'Sushisom';
} elseif ($is_tea_active) {
    $project_name = 'TEA';
} elseif ($is_dts_active) {
    $project_name = 'DTS 时间线';
} elseif ($current_action === 'dashboard') {
    $project_name = 'ABCABC CP';
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?php echo htmlspecialchars($project_name); ?> | 控制面板</title>
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

    <!-- 样式表 -->
    <link rel="stylesheet" href="/cp/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer" />

    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

</head>
<body class="hold-transition">
<div class="wrapper">

    <!-- 侧边栏 - MRS风格 -->
    <aside class="main-sidebar">
        <div class="sidebar-logo">
            <i class="fas fa-cube"></i>
            ABCABC CP
        </div>

        <section class="sidebar">
            <ul class="sidebar-menu">
                <!-- 主导航 -->
                <li class="nav-header">
                    <i class="fas fa-home"></i> 主导航
                </li>

                <li class="nav-item">
                    <a href="/cp/index.php?action=dashboard" class="nav-link <?php echo isActive('dashboard', $current_action); ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>仪表盘</span>
                    </a>
                </li>

                <!-- 财务管理 -->
                <li class="nav-header">
                    <i class="fas fa-chart-line"></i> 财务管理
                </li>

                <!-- Sushisom 财务 -->
                <li class="nav-item treeview <?php echo isMenuOpen($som_actions, $current_action); ?>">
                    <a href="javascript:void(0);" class="nav-link menu-toggle <?php echo $is_som_active; ?>">
                        <i class="fas fa-utensils"></i>
                        <span>Sushisom 财务</span>
                        <i class="fas fa-angle-right pull-right"></i>
                    </a>

                    <ul class="treeview-menu" style="display: <?php echo $is_som_active ? 'block' : 'none'; ?>;">
                        <li class="nav-item">
                            <a href="/cp/index.php?action=som_add" class="nav-link <?php echo isActive('som_add', $current_action); ?>">
                                <i class="fas fa-plus-circle"></i>
                                <span>日常录入</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/cp/index.php?action=som_salary_add" class="nav-link <?php echo isActive('som_salary_add', $current_action); ?>">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>月度工资</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/cp/index.php?action=som_report_store" class="nav-link <?php echo isActive('som_report_store', $current_action); ?>">
                                <i class="fas fa-store"></i>
                                <span>店铺报表</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/cp/index.php?action=som_report_investor" class="nav-link <?php echo isActive('som_report_investor', $current_action); ?>">
                                <i class="fas fa-chart-pie"></i>
                                <span>投资人报表</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- TEA投资 -->
                <li class="nav-item treeview <?php echo isMenuOpen($tea_actions, $current_action); ?>">
                    <a href="javascript:void(0);" class="nav-link menu-toggle <?php echo $is_tea_active; ?>">
                        <i class="fas fa-mug-hot"></i>
                        <span>TEA投资</span>
                        <i class="fas fa-angle-right pull-right"></i>
                    </a>

                    <ul class="treeview-menu" style="display: <?php echo $is_tea_active ? 'block' : 'none'; ?>;">
                        <li class="nav-item">
                            <a href="/cp/index.php?action=tea_dashboard" class="nav-link <?php echo isActive('tea_dashboard', $current_action); ?>">
                                <i class="fas fa-chart-area"></i>
                                <span>概览</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/cp/index.php?action=tea_add" class="nav-link <?php echo isActive('tea_add', $current_action); ?>">
                                <i class="fas fa-plus-square"></i>
                                <span>投资录入</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/cp/index.php?action=tea_report_investor" class="nav-link <?php echo isActive('tea_report_investor', $current_action); ?>">
                                <i class="fas fa-file-invoice-dollar"></i>
                                <span>投资报表</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/cp/index.php?action=tea_store_manage" class="nav-link <?php echo isActive('tea_store_manage', $current_action); ?>">
                                <i class="fas fa-building"></i>
                                <span>店铺管理</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- 日期管理 -->
                <li class="nav-header">
                    <i class="fas fa-clock"></i> 日期管理
                </li>

                <!-- DTS 时间线 -->
                <li class="nav-item treeview <?php echo isMenuOpen($dts_actions, $current_action); ?>">
                    <a href="javascript:void(0);" class="nav-link menu-toggle <?php echo $is_dts_active; ?>">
                        <i class="fas fa-calendar-alt"></i>
                        <span>DTS 时间线</span>
                        <i class="fas fa-angle-right pull-right"></i>
                    </a>

                    <ul class="treeview-menu" style="display: <?php echo $is_dts_active ? 'block' : 'none'; ?>;">
                        <li class="nav-item">
                            <a href="/cp/index.php?action=dts_quick" class="nav-link <?php echo isActive('dts_quick', $current_action); ?>">
                                <i class="fas fa-bolt"></i>
                                <span>极速录入</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/cp/index.php?action=dts_main" class="nav-link <?php echo isActive('dts_main', $current_action); ?>">
                                <i class="fas fa-list"></i>
                                <span>DTS 总览</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/cp/index.php?action=dts_subject" class="nav-link <?php echo isActive('dts_subject', $current_action); ?>">
                                <i class="fas fa-user-tag"></i>
                                <span>主体管理</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/cp/index.php?action=dts_object" class="nav-link <?php echo isActive('dts_object', $current_action); ?>">
                                <i class="fas fa-cubes"></i>
                                <span>对象管理</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/cp/index.php?action=dts_rule" class="nav-link <?php echo isActive('dts_rule', $current_action); ?>">
                                <i class="fas fa-cogs"></i>
                                <span>规则管理</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="/cp/index.php?action=dts_category_manage" class="nav-link <?php echo isActive('dts_category_manage', $current_action); ?>">
                                <i class="fas fa-tags"></i>
                                <span>分类管理</span>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- 系统 -->
                <li class="nav-header">
                    <i class="fas fa-cog"></i> 系统
                </li>

                <li class="nav-item">
                    <a href="/cp/index.php?action=profile" class="nav-link <?php echo isActive('profile', $current_action); ?>">
                        <i class="fas fa-user-circle"></i>
                        <span>个人资料</span>
                    </a>
                </li>

            </ul>
        </section>
    </aside>

    <!-- 侧边栏遮罩 -->
    <div class="sidebar-backdrop"></div>

    <!-- 主内容区 -->
    <div class="main-content">
        <!-- 顶部导航栏 -->
        <header class="main-header">
            <button type="button" class="sidebar-toggle-btn" aria-label="打开菜单">
                <span class="icon-bars" aria-hidden="true"></span>
            </button>

            <h1 class="logo-lg">
                <?php echo htmlspecialchars($project_name); ?>
            </h1>

            <div class="user-info">
                <span>
                    <i class="fas fa-user"></i>
                    <?php echo htmlspecialchars($_SESSION['user_display_name']); ?>
                </span>
                <a href="/cp/index.php?action=logout">
                    <i class="fas fa-sign-out-alt"></i>
                    退出
                </a>
            </div>
        </header>

        <!-- 内容区域 -->
        <div class="view-content-wrapper">

<script>
// 侧边栏控制
(function() {
    'use strict';

    // 侧边栏切换功能
    window.toggleSidebar = function() {
        document.body.classList.toggle('sidebar-open');
    };

    // DOM加载完成后初始化
    document.addEventListener('DOMContentLoaded', function() {
        // 侧边栏遮罩点击关闭
        const backdrop = document.querySelector('.sidebar-backdrop');
        if (backdrop) {
            backdrop.addEventListener('click', function() {
                document.body.classList.remove('sidebar-open');
            });
        }

        // 侧边栏切换按钮
        const toggleBtn = document.querySelector('.sidebar-toggle-btn');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleSidebar);
        }
    });
})();
</script>
