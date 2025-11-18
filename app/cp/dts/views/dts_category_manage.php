<?php
/**
 * DTS 分类管理页面
 * 用于修改 dts_category.conf 文件中的小类配置
 */

declare(strict_types=1);

// 加载 DTS 函数库
require_once APP_PATH_CP . '/dts/dts_lib.php';

// 加载当前分类数据
$categories = dts_load_categories();

?>

<link rel="stylesheet" href="/cp/dts/dts_style.css">

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1><i class="fas fa-tags"></i> 分类管理 <small>（DTS 模块）</small></h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_main">DTS 总览</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_object">对象管理</a></li>
        <li class="active">分类配置</li>
    </ol>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="card box-primary">
                <div class="card-header with-border">
                    <h3 class="box-title">分类配置编辑器</h3>
                    <div class="box-tools pull-right">
                        <a href="<?php echo CP_BASE_URL; ?>dts_object" class="btn btn-sm btn-default">
                            <i class="fa fa-reply"></i> 返回对象列表
                        </a>
                    </div>
                </div>

                <form class="form-horizontal" action="<?php echo CP_BASE_URL; ?>dts_category_save" method="post">
                    <div class="card-body">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>注意：</strong>
                            <ul>
                                <li>此处修改将直接写入配置文件 (<code>dts_category.conf</code>)。</li>
                                <li><strong>大类</strong>为系统预设，不可在此页面修改（如需新增大类请联系管理员修改源码或手动编辑文件）。</li>
                                <li><strong>小类</strong>请使用<strong>逗号</strong>或<strong>中文逗号</strong>分隔。</li>
                            </ul>
                        </div>

                        <?php if (empty($categories)): ?>
                            <div class="alert alert-danger">未找到分类配置或配置文件为空。</div>
                        <?php else: ?>
                            <?php foreach ($categories as $main_cat => $sub_cats): ?>
                                <div class="form-group" style="border-bottom: 1px dashed #eee; padding-bottom: 15px;">
                                    <label class="col-sm-2 control-label" style="font-size: 15px; padding-top: 10px;">
                                        <?php echo htmlspecialchars($main_cat); ?>
                                    </label>
                                    <div class="col-sm-10">
                                        <label class="text-muted" style="font-weight:normal; margin-bottom:5px; display:block; font-size:12px;">
                                            该大类下的小类（用逗号分隔）：
                                        </label>
                                        <textarea class="form-control" 
                                                  name="cats[<?php echo htmlspecialchars($main_cat); ?>]" 
                                                  rows="2"
                                                  placeholder="例如：项目A, 项目B, 项目C"><?php echo htmlspecialchars(implode(', ', $sub_cats)); ?></textarea>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> 保存配置
                        </button>
                        <a href="<?php echo CP_BASE_URL; ?>dts_object" class="btn btn-default">取消</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>