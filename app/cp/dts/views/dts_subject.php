<?php
/**
 * DTS 主体管理页面
 * 用于管理主体（个人/公司/其他实体）
 */

declare(strict_types=1);

// 加载 DTS 函数库
require_once APP_PATH_CP . '/dts/dts_lib.php';

// 获取反馈消息
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

// 获取所有主体列表
global $pdo;
$stmt = $pdo->prepare("
    SELECT s.*,
           COUNT(DISTINCT o.id) as object_count
    FROM cp_dts_subject s
    LEFT JOIN cp_dts_object o ON s.id = o.subject_id
    GROUP BY s.id
    ORDER BY s.subject_status DESC, s.id DESC
");
$stmt->execute();
$subjects = $stmt->fetchAll();

?>

<link rel="stylesheet" href="/cp/dts/css/dts_style.css">

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1><i class="fas fa-users"></i> 主体管理 <small>（DTS 模块）</small></h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_main">DTS 总览</a></li>
        <li class="active">主体管理</li>
    </ol>
</section>

<section class="content">

    <div id="feedback-container">
        <?php echo $feedback_message; ?>
    </div>

    <!-- 新增/编辑主体表单 -->
    <div class="row">
        <div class="col-md-12">
            <div class="card box-primary">
                <div class="card-header with-border">
                    <h3 class="box-title" id="form-title">
                        <i class="fas fa-plus-circle"></i> 新增主体
                    </h3>
                </div>
                <form id="subject-form" class="form-horizontal" action="<?php echo CP_BASE_URL; ?>dts_subject_save" method="post">
                    <input type="hidden" id="subject_id" name="subject_id">

                    <div class="card-body">
                        <div class="form-group">
                            <label class="col-sm-2 control-label">主体名称 *</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" id="subject_name" name="subject_name" required placeholder="例如：A1、A1公司、B2">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label">主体类型 *</label>
                            <div class="col-sm-10">
                                <select class="form-control" id="subject_type" name="subject_type" required>
                                    <option value="person">个人</option>
                                    <option value="company">公司</option>
                                    <option value="other">其他</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label">状态</label>
                            <div class="col-sm-10">
                                <select class="form-control" id="subject_status" name="subject_status">
                                    <option value="1">启用</option>
                                    <option value="0">停用</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-2 control-label">备注</label>
                            <div class="col-sm-10">
                                <textarea class="form-control" id="remark" name="remark" rows="3" placeholder="可选"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="button" class="btn btn-default" id="cancel-btn" style="display:none;">
                            <i class="fas fa-times"></i> 取消
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <span id="submit-text">保存</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 主体列表 -->
    <div class="row">
        <div class="col-md-12">
            <div class="card box-default">
                <div class="card-header with-border">
                    <h3 class="box-title">
                        <i class="fas fa-list"></i> 主体列表
                    </h3>
                </div>
                <div class="card-body">
                    <?php if (empty($subjects)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 暂无主体数据。请点击上方"新增主体"按钮添加。
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th width="60">ID</th>
                                        <th>主体名称</th>
                                        <th width="100">类型</th>
                                        <th width="80">状态</th>
                                        <th width="100">对象数量</th>
                                        <th>备注</th>
                                        <th width="180">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subjects as $subject): ?>
                                        <tr class="<?php echo $subject['subject_status'] == 0 ? 'disabled-row' : ''; ?>">
                                            <td><?php echo (int)$subject['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                                            </td>
                                            <td>
                                                <?php
                                                $type_map = ['person' => '个人', 'company' => '公司', 'other' => '其他'];
                                                echo htmlspecialchars($type_map[$subject['subject_type']] ?? $subject['subject_type']);
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($subject['subject_status'] == 1): ?>
                                                    <span class="badge badge-success">启用</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">停用</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo CP_BASE_URL; ?>dts_object&subject_id=<?php echo (int)$subject['id']; ?>" class="badge badge-info">
                                                    <?php echo (int)$subject['object_count']; ?> 个对象
                                                </a>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars((string)($subject['remark'] ?? '')); ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary edit-btn"
                                                        data-id="<?php echo $subject['id']; ?>">
                                                    <i class="fas fa-edit"></i> 编辑
                                                </button>
                                                <a href="<?php echo CP_BASE_URL; ?>dts_object&subject_id=<?php echo $subject['id']; ?>"
                                                   class="btn btn-sm btn-info">
                                                    <i class="fas fa-folder"></i> 对象
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</section>

<script src="/cp/dts/js/dts_subject.js"></script>