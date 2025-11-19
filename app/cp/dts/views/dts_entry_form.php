<?php
/**
 * DTS 条目新增/编辑（CP 基线）
 */

declare(strict_types=1);

require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

$entry_id = dts_get('id');
$entry = null;

if ($entry_id) {
    $stmt = $pdo->prepare('SELECT * FROM cp_dts_entry WHERE id = ? AND source = "CP"');
    $stmt->execute([$entry_id]);
    $entry = $stmt->fetch();

    if (!$entry) {
        dts_set_feedback('danger', '未找到条目');
        header('Location: /cp/index.php?action=dts_entry');
        exit();
    }
}

$is_edit = !empty($entry);
$page_title = $is_edit ? '编辑 DTS 条目' : '新增 DTS 条目';

?>

<link rel="stylesheet" href="/cp/dts/css/dts_style.css">

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1><i class="fas fa-<?php echo $is_edit ? 'edit' : 'plus-circle'; ?>"></i> <?php echo $page_title; ?></h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_entry">DTS 条目</a></li>
        <li class="active"><?php echo $page_title; ?></li>
    </ol>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="card box-primary">
                <div class="card-header with-border">
                    <h3 class="box-title"><i class="fas fa-<?php echo $is_edit ? 'edit' : 'plus-circle'; ?>"></i> <?php echo $page_title; ?></h3>
                </div>
                <form class="form-horizontal" action="<?php echo CP_BASE_URL; ?>dts_entry_save" method="post">
                    <input type="hidden" name="entry_id" value="<?php echo $entry['id'] ?? ''; ?>">

                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>说明：</strong>本表用于维护 CP 基线的 DTS 条目，包含 code、日期模式、展示信息与适用范围，为后续 SOM 层继承/覆写提供基础。
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label">系统标识 code *</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" name="dts_code" maxlength="100" required
                                       value="<?php echo htmlspecialchars($entry['dts_code'] ?? ''); ?>"
                                       placeholder="如 holiday_cny_2025">
                                <p class="help-block">全局唯一，业务调用和 SOM 继承的关键字。</p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label">类型 *</label>
                            <div class="col-sm-4">
                                <select class="form-control" name="entry_type" required>
                                    <?php
                                    $type = $entry['entry_type'] ?? 'custom';
                                    $options = [
                                        'holiday' => '节假日',
                                        'promotion' => '促销活动',
                                        'system' => '系统日期',
                                        'custom' => '自定义',
                                    ];
                                    foreach ($options as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $type === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <label class="col-sm-2 control-label">优先级</label>
                            <div class="col-sm-4">
                                <input type="number" class="form-control" name="priority" min="0" step="1" value="<?php echo htmlspecialchars((string) ($entry['priority'] ?? 100)); ?>">
                                <p class="help-block">越大越靠前，用于同日多条记录排序。</p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label">日期模式 *</label>
                            <div class="col-sm-4">
                                <select class="form-control" name="date_mode" id="date_mode" required>
                                    <?php $mode = $entry['date_mode'] ?? 'single'; ?>
                                    <option value="single" <?php echo $mode === 'single' ? 'selected' : ''; ?>>单日</option>
                                    <option value="range" <?php echo $mode === 'range' ? 'selected' : ''; ?>>日期区间</option>
                                </select>
                                <p class="help-block">单日使用“日期”；区间需填写“开始-结束”。</p>
                            </div>
                            <div class="col-sm-6">
                                <div class="date-single" style="display: <?php echo ($mode === 'range') ? 'none' : 'block'; ?>;">
                                    <label>日期</label>
                                    <input type="date" class="form-control" name="date_value" value="<?php echo htmlspecialchars($entry['date_value'] ?? ''); ?>">
                                </div>
                                <div class="date-range" style="display: <?php echo ($mode === 'range') ? 'block' : 'none'; ?>;">
                                    <div class="row">
                                        <div class="col-sm-6">
                                            <label>开始日期</label>
                                            <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($entry['start_date'] ?? ''); ?>">
                                        </div>
                                        <div class="col-sm-6">
                                            <label>结束日期</label>
                                            <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($entry['end_date'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label">状态 *</label>
                            <div class="col-sm-4">
                                <label class="checkbox-inline">
                                    <input type="checkbox" name="status" value="1" <?php echo ($entry['status'] ?? 1) ? 'checked' : ''; ?>> 启用
                                </label>
                                <p class="help-block">停用后不参与匹配，SOM 也无法启用。</p>
                            </div>
                            <label class="col-sm-2 control-label">展示开关 *</label>
                            <div class="col-sm-4">
                                <label class="checkbox-inline">
                                    <input type="checkbox" name="show_to_front" value="1" <?php echo ($entry['show_to_front'] ?? 1) ? 'checked' : ''; ?>> 向前端展示
                                </label>
                                <p class="help-block">仅影响前端显式展示，不影响内部逻辑判断。</p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label">名称（中文） *</label>
                            <div class="col-sm-4">
                                <input type="text" class="form-control" name="name_zh" required
                                       value="<?php echo htmlspecialchars($entry['name_zh'] ?? ''); ?>">
                            </div>
                            <label class="col-sm-2 control-label">名称（英文）</label>
                            <div class="col-sm-4">
                                <input type="text" class="form-control" name="name_en" value="<?php echo htmlspecialchars($entry['name_en'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label">前端短标题</label>
                            <div class="col-sm-4">
                                <input type="text" class="form-control" name="short_title" value="<?php echo htmlspecialchars($entry['short_title'] ?? ''); ?>" placeholder="用于小标签或卡片标题">
                            </div>
                            <label class="col-sm-2 control-label">标签色 / 样式</label>
                            <div class="col-sm-4">
                                <div class="row" style="gap:10px;">
                                    <div class="col-sm-6" style="padding-left:0;">
                                        <input type="color" class="form-control" name="color_hex" value="<?php echo htmlspecialchars($entry['color_hex'] ?? '#ff8c00'); ?>">
                                        <p class="help-block">颜色值</p>
                                    </div>
                                    <div class="col-sm-6" style="padding-left:0;">
                                        <input type="text" class="form-control" name="tag_class" value="<?php echo htmlspecialchars($entry['tag_class'] ?? ''); ?>" placeholder="自定义 CSS 类">
                                        <p class="help-block">如 badge-holiday、tag-important</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label">适用语言</label>
                            <div class="col-sm-4">
                                <input type="text" class="form-control" name="languages" value="<?php echo htmlspecialchars($entry['languages'] ?? ''); ?>" placeholder="空=全部，或用逗号分隔：zh,en,es">
                            </div>
                            <label class="col-sm-2 control-label">适用端</label>
                            <div class="col-sm-4">
                                <input type="text" class="form-control" name="platforms" value="<?php echo htmlspecialchars($entry['platforms'] ?? 'PC,M,APP'); ?>" placeholder="PC,M,APP 等">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label">适用模块</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" name="modules" value="<?php echo htmlspecialchars($entry['modules'] ?? ''); ?>" placeholder="如 list,calendar,banner，逗号分隔">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label">外部关联</label>
                            <div class="col-sm-5">
                                <input type="text" class="form-control" name="external_id" value="<?php echo htmlspecialchars($entry['external_id'] ?? ''); ?>" placeholder="活动/业务 ID">
                            </div>
                            <div class="col-sm-5">
                                <input type="url" class="form-control" name="external_url" value="<?php echo htmlspecialchars($entry['external_url'] ?? ''); ?>" placeholder="活动链接（可选）">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label">备注</label>
                            <div class="col-sm-10">
                                <textarea class="form-control" name="remark" rows="3" placeholder="业务说明、使用注意事项等"><?php echo htmlspecialchars($entry['remark'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer" style="text-align:right;">
                        <a class="btn btn-default" href="<?php echo CP_BASE_URL; ?>dts_entry"><i class="fas fa-arrow-left"></i> 返回</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> 保存</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
    (function() {
        const modeSelect = document.getElementById('date_mode');
        const singleBox = document.querySelector('.date-single');
        const rangeBox = document.querySelector('.date-range');

        function toggleDate() {
            if (!modeSelect) return;
            const mode = modeSelect.value;
            if (mode === 'range') {
                rangeBox.style.display = 'block';
                singleBox.style.display = 'none';
            } else {
                rangeBox.style.display = 'none';
                singleBox.style.display = 'block';
            }
        }

        if (modeSelect) {
            modeSelect.addEventListener('change', toggleDate);
            toggleDate();
        }
    })();
</script>
