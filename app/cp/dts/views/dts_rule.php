<?php
/**
 * DTS 规则管理页面（简化版 - 只读列表）
 */

declare(strict_types=1);

require_once APP_PATH_CP . '/dts/dts_lib.php';

global $pdo;

// 查询所有规则
$stmt = $pdo->query("
    SELECT * FROM cp_dts_rule
    ORDER BY rule_status DESC, cat_main, cat_sub, id DESC
");
$rules = $stmt->fetchAll();

?>

<link rel="stylesheet" href="/cp/dts/dts_style.css">

<section class="content-header-replacement">
    <div class="page-header-title">
        <h1><i class="fas fa-cogs"></i> 规则模板管理 <small>（DTS 模块）</small></h1>
    </div>
    <ol class="breadcrumb">
        <li><a href="<?php echo CP_BASE_URL; ?>dashboard"><i class="fas fa-home"></i> 首页</a></li>
        <li><a href="<?php echo CP_BASE_URL; ?>dts_main">DTS 总览</a></li>
        <li class="active">规则管理</li>
    </ol>
</section>

<section class="content">

    <div class="row">
        <div class="col-md-12">
            <div class="card box-default">
                <div class="card-header with-border">
                    <h3 class="box-title"><i class="fas fa-list"></i> 规则列表</h3>
                    <p class="help-block">规则模板由系统预设，如需修改请联系管理员或直接修改数据库。</p>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>规则名称</th>
                                    <th>类型</th>
                                    <th>适用分类</th>
                                    <th>参数</th>
                                    <th>状态</th>
                                    <th>备注</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rules as $rule): ?>
                                    <tr>
                                        <td><?php echo $rule['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($rule['rule_name']); ?></strong></td>
                                        <td>
                                            <?php
                                            $type_map = [
                                                'expiry_based' => '过期日',
                                                'last_done_based' => '周期',
                                                'submit_based' => '跟进'
                                            ];
                                            echo $type_map[$rule['rule_type']] ?? $rule['rule_type'];
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($rule['cat_main'] ?? '-'); ?> /
                                            <?php echo htmlspecialchars($rule['cat_sub'] ?? '-'); ?>
                                        </td>
                                        <td style="font-size:12px;">
                                            <?php if ($rule['earliest_offset_days']): ?>
                                                最早: <?php echo $rule['earliest_offset_days']; ?>天<br>
                                            <?php endif; ?>
                                            <?php if ($rule['cycle_interval_months']): ?>
                                                周期: <?php echo $rule['cycle_interval_months']; ?>月<br>
                                            <?php endif; ?>
                                            <?php if ($rule['cycle_interval_days']): ?>
                                                周期: <?php echo $rule['cycle_interval_days']; ?>天<br>
                                            <?php endif; ?>
                                            <?php if ($rule['mileage_interval']): ?>
                                                里程: <?php echo number_format($rule['mileage_interval']); ?>km<br>
                                            <?php endif; ?>
                                            <?php if ($rule['follow_up_offset_months']): ?>
                                                跟进: <?php echo $rule['follow_up_offset_months']; ?>月<br>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($rule['rule_status'] == 1): ?>
                                                <span class="badge badge-success">启用</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">禁用</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:12px;"><?php echo htmlspecialchars($rule['remark'] ?? ''); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</section>
