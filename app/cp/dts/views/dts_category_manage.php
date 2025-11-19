<?php
/**
 * DTS 分类管理页面
 */

declare(strict_types=1);

// 加载 DTS 函数库
require_once APP_PATH_CP . '/dts/dts_lib.php';

$categories = dts_load_categories();
$page_title = '分类管理';

// 读取原始配置内容，便于用户确认是否写入成功
$config_file = APP_PATH_CP . '/dts/dts_category.conf';
$categories_raw = '';
if (is_readable($config_file)) {
    $categories_raw = file_get_contents($config_file) ?: '';
} elseif (!empty($categories)) {
    foreach ($categories as $main_cat => $sub_cats) {
        $categories_raw .= $main_cat . ';' . implode(',', $sub_cats) . ";\n";
    }
}

$feedback = dts_get_feedback();
$feedback_message = '';
if ($feedback) {
    $alert_type = $feedback['type'] === 'success' ? 'success' : ($feedback['type'] === 'info' ? 'info' : 'danger');
    $icon = $feedback['type'] === 'success' ? 'check' : 'ban';
    $feedback_message = <<<HTML
    <div id="feedback-bar" class="feedback-bar {$alert_type}">
        <i class="fas fa-{$icon} me-2"></i> {$feedback['message']}
    </div>
HTML;
}

?>

<link rel="stylesheet" href="/cp/dts/css/dts_style.css">

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1><i class="fas fa-sitemap"></i> <?php echo $page_title; ?> <small>（DTS 模块）</small></h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_main">DTS 总览</a></li>
        <li class="active"><?php echo $page_title; ?></li>
    </ol>
</section>

<section class="content">

    <div class="row">
        <div class="col-md-12">
            <div class="card box-primary">
                <div class="card-header with-border">
                    <h3 class="box-title">
                        <i class="fas fa-sitemap"></i> <?php echo $page_title; ?>
                    </h3>
                </div>
                <div id="feedback-container">
                    <?php echo $feedback_message; ?>
                </div>
                <form id="category-form" class="form-horizontal" action="/cp/index.php?action=dts_category_save" method="post">
                    <div class="card-body">
                        <div class="form-group">
                            <label class="col-sm-2 control-label">分类配置</label>
                            <div class="col-sm-10">
                                <textarea class="form-control" name="categories" rows="20"><?php echo htmlspecialchars($categories_raw); ?></textarea>
                                <p class="help-block">格式：大类;小类1,小类2,小类3;</p>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> 保存
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</section>

