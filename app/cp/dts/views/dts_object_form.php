<?php
/**
 * DTS 对象新增/编辑表单页面
 */

declare(strict_types=1);

// 加载 DTS 函数库
require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

// 获取对象 ID（如果是编辑）
$object_id = dts_get('id');
$object = null;

if ($object_id) {
    $stmt = $pdo->prepare("
        SELECT o.*, s.subject_name
        FROM cp_dts_object o
        LEFT JOIN cp_dts_subject s ON o.subject_id = s.id
        WHERE o.id = ?
    ");
    $stmt->execute([$object_id]);
    $object = $stmt->fetch();

    if (!$object) {
        dts_set_feedback('danger', '对象不存在');
        header('Location: /cp/index.php?action=dts_object');
        exit();
    }
}

// 加载分类
$categories = dts_load_categories();

// 获取所有启用的主体
$subjects_stmt = $pdo->query("SELECT * FROM cp_dts_subject WHERE subject_status = 1 ORDER BY subject_name");
$subjects = $subjects_stmt->fetchAll();

$is_edit = !empty($object);
$page_title = $is_edit ? '编辑对象' : '新增对象';

?>

<link rel="stylesheet" href="/cp/dts/css/dts_style.css">

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1><i class="fas fa-<?php echo $is_edit ? 'edit' : 'plus-circle'; ?>"></i> <?php echo $page_title; ?> <small>（DTS 模块）</small></h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_main">DTS 总览</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_object">对象管理</a></li>
        <li class="active"><?php echo $page_title; ?></li>
    </ol>
</section>

<section class="content">

    <div class="row">
        <div class="col-md-12">
            <div class="card box-primary">
                <div class="card-header with-border">
                    <h3 class="box-title">
                        <i class="fas fa-<?php echo $is_edit ? 'edit' : 'plus-circle'; ?>"></i> <?php echo $page_title; ?>
                    </h3>
                </div>
                <form id="object-form" class="form-horizontal" action="<?php echo CP_BASE_URL; ?>dts_object_save" method="post">
                    <input type="hidden" name="object_id" value="<?php echo $object['id'] ?? ''; ?>">

                    <div class="card-body">

                        <!-- 引导说明 -->
                        <div class="alert alert-info" style="margin-bottom:20px;">
                            <i class="fas fa-info-circle"></i>
                            <strong>填写提示：</strong>本表单仅用于定义对象的基本信息（这是谁的什么东西）。
                            所有关于日期、周期、公里数、规则等的管理，将在后续的"事件记录"中自动处理。
                        </div>

                        <!-- 主体选择 -->
                        <div class="form-group">
                            <label class="col-sm-2 control-label">所属主体 *</label>
                            <div class="col-sm-10">
                                <select class="form-control" name="subject_id" id="subject_id" required>
                                    <option value="">请选择主体</option>
                                    <?php foreach ($subjects as $subj): ?>
                                        <option value="<?php echo $subj['id']; ?>"
                                                <?php echo ($object['subject_id'] ?? '') == $subj['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subj['subject_name']); ?>
                                            （<?php echo $subj['subject_type'] === 'person' ? '个人' : ($subj['subject_type'] === 'company' ? '公司' : '其他'); ?>）
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="help-block">① 这是谁的东西？选择对象的所属人或公司。</p>
                            </div>
                        </div>

                        <!-- 对象名称 -->
                        <div class="form-group">
                            <label class="col-sm-2 control-label">对象名称 *</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" name="object_name" id="object_name"
                                       value="<?php echo htmlspecialchars($object['object_name'] ?? ''); ?>"
                                       placeholder="例如：A1的车辆Q3、中国护照、T8证件"
                                       required>
                                <p class="help-block">② 这是什么东西？给它起个容易识别的名字。</p>
                            </div>
                        </div>

                        <!-- 大类 -->
                        <div class="form-group">
                            <label class="col-sm-2 control-label">大类 *</label>
                            <div class="col-sm-10">
                                <select class="form-control" name="object_type_main" id="object_type_main" required>
                                    <option value="">请选择大类</option>
                                    <?php foreach ($categories as $main_cat => $sub_cats): ?>
                                        <option value="<?php echo htmlspecialchars($main_cat); ?>"
                                                <?php echo ($object['object_type_main'] ?? '') === $main_cat ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($main_cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="help-block">③ 它大概属于哪一类？选择对象的大类别（如证件、车辆、健康等）。</p>
                            </div>
                        </div>

                        <!-- 小类 -->
                        <div class="form-group">
                            <label class="col-sm-2 control-label">小类</label>
                            <div class="col-sm-10">
                                <select class="form-control" name="object_type_sub" id="object_type_sub">
                                    <option value="">请先选择大类</option>
                                </select>
                                <p class="help-block">③ 续：如需进一步细分类别可选择小类，不选也可以。</p>
                            </div>
                        </div>

                        <!-- 标识信息 -->
                        <div class="form-group">
                            <label class="col-sm-2 control-label">标识信息</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" name="identifier" id="identifier"
                                       value="<?php echo htmlspecialchars($object['identifier'] ?? ''); ?>"
                                       placeholder="例如：车牌号、证件号等（可选）">
                                <p class="help-block">④ 有无必要的识别信息？如车牌号、证件号等，用于区分同类对象（可选）。</p>
                            </div>
                        </div>

                        <!-- 状态 -->
                        <div class="form-group">
                            <label class="col-sm-2 control-label">状态</label>
                            <div class="col-sm-10">
                                <select class="form-control" name="active_flag" id="active_flag">
                                    <option value="1" <?php echo ($object['active_flag'] ?? 1) == 1 ? 'selected' : ''; ?>>当前使用</option>
                                    <option value="0" <?php echo ($object['active_flag'] ?? 1) == 0 ? 'selected' : ''; ?>>历史对象</option>
                                </select>
                                <p class="help-block">⑤ 现在还在用吗？新增对象一般选"当前使用"；如物品已卖出/过期不再使用，可标记为"历史对象"。</p>
                            </div>
                        </div>

                        <!-- 备注 -->
                        <div class="form-group">
                            <label class="col-sm-2 control-label">备注</label>
                            <div class="col-sm-10">
                                <textarea class="form-control" name="remark" id="remark" rows="3"
                                          placeholder="可选"><?php echo htmlspecialchars($object['remark'] ?? ''); ?></textarea>
                                <p class="help-block">其他补充说明（可选）。</p>
                            </div>
                        </div>

                    </div>

                    <div class="card-footer">
                        <a href="<?php echo CP_BASE_URL; ?>dts_object" class="btn btn-default">
                            <i class="fas fa-times"></i> 取消
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $is_edit ? '更新' : '保存'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</section>

<script>
$(document).ready(function() {
    // 分类数据（从 PHP 传递）
    const categories = <?php echo json_encode($categories, JSON_UNESCAPED_UNICODE); ?>;
    const currentSubCat = <?php echo json_encode($object['object_type_sub'] ?? '', JSON_UNESCAPED_UNICODE); ?>;

    // 大类改变时，更新小类
    $('#object_type_main').on('change', function() {
        updateSubCategories($(this).val(), '');
    });

    // 页面加载时，如果有已选择的大类，更新小类
    const selectedMainCat = $('#object_type_main').val();
    if (selectedMainCat) {
        updateSubCategories(selectedMainCat, currentSubCat);
    }

    // 更新小类下拉选项
    function updateSubCategories(mainCat, selectedSubCat) {
        const subCatSelect = $('#object_type_sub');
        subCatSelect.empty();

        if (!mainCat) {
            subCatSelect.append('<option value="">请先选择大类</option>');
            subCatSelect.prop('disabled', true);
            return;
        }

        const subCats = categories[mainCat];

        if (!Array.isArray(subCats)) {
            subCatSelect.append('<option value="">当前大类暂无可选小类</option>');

            // 如果已有历史小类，仍然展示，便于用户调整
            if (selectedSubCat) {
                subCatSelect.append(
                    $('<option></option>').val(selectedSubCat).text(selectedSubCat).attr('selected', 'selected')
                );
                subCatSelect.prop('disabled', false);
            } else {
                subCatSelect.prop('disabled', true);
            }
            return;
        }

        // 始终添加"不选择小类"选项
        subCatSelect.append('<option value="">（不选择小类）</option>');

        if (subCats.length === 0) {
            // 如果该大类没有小类，禁用下拉但保留"不选择小类"选项
            subCatSelect.prop('disabled', true);
            return;
        }

        // 启用下拉并添加所有小类选项
        subCatSelect.prop('disabled', false);

        subCats.forEach(function(subCat) {
            const option = $('<option></option>')
                .val(subCat)
                .text(subCat);

            if (subCat === selectedSubCat) {
                option.attr('selected', 'selected');
            }

            subCatSelect.append(option);
        });
    }
});
</script>
