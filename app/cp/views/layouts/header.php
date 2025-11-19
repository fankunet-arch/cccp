<?php
// /app/cp/views/layouts/header.php
// (优化：菜单结构精简/美化，移除多余 AdminLTE 兼容代码)

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
    // [注意] 仅用于标记，实际展开依赖 JS 或纯 CSS (目前 style.css 是纯 CSS)
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
    'tea_report_investor', // 报表路由
	'tea_store_manage', //
    'tea_store_save',   //
];

// DTS 模块的 action 列表
$dts_actions = [
    'dts_main',
    'dts_subject',
    'dts_subject_save',
    'dts_subject_get_data',
    'dts_object',
    'dts_object_form',
    'dts_object_detail',
    'dts_object_save',
    'dts_rule',
    'dts_event_form',
    'dts_event_save',
    'dts_category_manage',
    'dts_category_save',
];

$is_som_active = isActive($som_actions, $current_action);
$is_tea_active = isActive($tea_actions, $current_action);
$is_dts_active = isActive($dts_actions, $current_action);

// 根据当前模块确定项目名称（用于蓝色大标题）
$project_name = 'ABCABC CP'; // 默认
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
    <title>Sushisom CP | 控制面板</title>
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

    <link rel="stylesheet" href="/cp/style.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer" />

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

</head>
<body class="hold-transition">
<div class="wrapper">

    <aside class="main-sidebar">
        <div class="sidebar-logo">ABCABC CP</div>
        <section class="sidebar">

        <ul class="sidebar-menu">
            <li class="nav-header">主导航</li>

            <li class="nav-item">
                <a href="<?php echo CP_BASE_URL; ?>dashboard" class="nav-link <?php echo isActive('dashboard', $current_action); ?>">
                    <i class="fas fa-tachometer-alt"></i> <span>仪表盘</span>
                </a>
            </li>

            <li class="nav-header">财务管理</li>

            <li class="nav-item treeview <?php echo $is_som_active; ?>">
                <a href="#" class="nav-link menu-toggle <?php echo $is_som_active; ?>">
                    <i class="fas fa-cutlery"></i> <span>Sushisom 财务</span>
                    <i class="fas fa-angle-right pull-right"></i>
                </a>

                <ul class="treeview-menu" style="display: <?php echo $is_som_active ? 'block' : 'none'; ?>;">
                    <li class="nav-item">
                        <a href="<?php echo CP_BASE_URL; ?>som_add" class="nav-link <?php echo isActive('som_add', $current_action); ?>">
                            <i class="far fa-circle"></i> 日常录入 (A1)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo CP_BASE_URL; ?>som_salary_add" class="nav-link <?php echo isActive('som_salary_add', $current_action); ?>">
                            <i class="far fa-circle"></i> 月度工资
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo CP_BASE_URL; ?>som_report_store" class="nav-link <?php echo isActive('som_report_store', $current_action); ?>">
                            <i class="far fa-circle"></i> 店铺报表
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo CP_BASE_URL; ?>som_report_investor" class="nav-link <?php echo isActive('som_report_investor', $current_action); ?>">
                            <i class="far fa-circle"></i> 投资人报表
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-item treeview <?php echo $is_tea_active; ?>">
                <a href="#" class="nav-link menu-toggle <?php echo $is_tea_active; ?>">
                    <i class="fas fa-mug-hot"></i> <span><tea> TEA投资</span>
                    <i class="fas fa-angle-right pull-right"></i>
                </a>

                <ul class="treeview-menu" style="display: <?php echo $is_tea_active ? 'block' : 'none'; ?>;">
                    <li class="nav-item">
                        <a href="<?php echo CP_BASE_URL; ?>tea_dashboard" class="nav-link <?php echo isActive('tea_dashboard', $current_action); ?>">
                            <i class="far fa-circle"></i> 概览 (T0)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo CP_BASE_URL; ?>tea_add" class="nav-link <?php echo isActive('tea_add', $current_action); ?>">
                            <i class="far fa-circle"></i> 投资录入 (T1)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo CP_BASE_URL; ?>tea_report_investor" class="nav-link <?php echo isActive('tea_report_investor', $current_action); ?>">
                            <i class="far fa-circle"></i> 投资报表 (T2)
                        </a>
                    </li>
					<li class="nav-item">
                        <a href="<?php echo CP_BASE_URL; ?>tea_store_manage" class="nav-link <?php echo isActive('tea_store_manage', $current_action); ?>">
                            <i class="far fa-circle"></i> **店铺管理**
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-header">日期管理</li>

            <li class="nav-item treeview <?php echo $is_dts_active; ?>">
                <a href="#" class="nav-link menu-toggle <?php echo $is_dts_active; ?>">
                    <i class="fas fa-calendar-alt"></i> <span>DTS 时间线</span>
                    <i class="fas fa-angle-right pull-right"></i>
                </a>

                <ul class="treeview-menu" style="display: <?php echo $is_dts_active ? 'block' : 'none'; ?>;">
                    <li class="nav-item">
                        <a href="<?php echo CP_BASE_URL; ?>dts_main" class="nav-link <?php echo isActive('dts_main', $current_action); ?>">
                            <i class="far fa-circle"></i> DTS 总览
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo CP_BASE_URL; ?>dts_subject" class="nav-link <?php echo isActive('dts_subject', $current_action); ?>">
                            <i class="far fa-circle"></i> 主体管理
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo CP_BASE_URL; ?>dts_object" class="nav-link <?php echo isActive('dts_object', $current_action); ?>">
                            <i class="far fa-circle"></i> 对象管理
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo CP_BASE_URL; ?>dts_rule" class="nav-link <?php echo isActive('dts_rule', $current_action); ?>">
                            <i class="far fa-circle"></i> 规则管理
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo CP_BASE_URL; ?>dts_category_manage" class="nav-link <?php echo isActive('dts_category_manage', $current_action); ?>">
                            <i class="far fa-circle"></i> 分类管理
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-header">系统</li>

            <li class="nav-item">
                <a href="<?php echo CP_BASE_URL; ?>profile" class="nav-link <?php echo isActive('profile', $current_action); ?>">
                    <i class="fas fa-user"></i> <span>个人资料</span>
                </a>
            </li>

        </ul>
        </section>
    </aside>
    <div class="sidebar-backdrop"></div>

    <div class="main-content">
        <header class="main-header">
			<button type="button" class="sidebar-toggle-btn" aria-label="打开菜单">
				<span class="icon-bars" aria-hidden="true"></span>
			</button>
            <h1 class="logo-lg"><?php echo htmlspecialchars($project_name); ?></h1>
            <div class="user-info">
                <span><?php echo htmlspecialchars($_SESSION['user_display_name']); ?></span>
                <a href="/cp/index.php?action=logout"><i class="fas fa-sign-out-alt"></i> 退出</a>
            </div>
        </header>


        <div class="view-content-wrapper">
<script src="/cp/js/main.js"></script>
