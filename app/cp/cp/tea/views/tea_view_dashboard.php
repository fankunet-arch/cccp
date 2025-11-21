<?php
// /app/cp/tea/views/tea_view_dashboard.php
// <tea> Project Dashboard/Entry View (T0)

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { exit('Access Denied.'); }
?>

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1><tea> 投资概览 <small>T0</small></h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li class="active"><tea> 概览</li>
    </ol>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="card box-primary">
                <div class="card-header with-border">
                    <h3 class="box-title">欢迎使用 <tea> 投资分析模块</h3>
                </div>
                <div class="card-body">
                    <p>这里是 <tea> 项目的投资概览仪表盘，该模块只关注投资和回报的金融流水。</p>
                    <p>请在左侧导航栏中选择 **投资录入 (T1)** 开始记录交易。</p>
                </div>
            </div>
        </div>
    </div>
</section>