<?php
// /app/cp/views/profile/index.php
// (重写: 匹配 [2025-10-17] 样式)

// 检查是否有来自 save action 的反馈信息
$feedback_message = '';
if (isset($_SESSION['profile_feedback'])) {
    $feedback = $_SESSION['profile_feedback'];
    $alert_type = $feedback['type']; // 'success' or 'danger'
    
    $feedback_message = <<<HTML
    <div id="feedback-bar" class="feedback-bar {$alert_type}">
        {$feedback['message']}
    </div>
HTML;
    unset($_SESSION['profile_feedback']);
}
?>

<div class="page-header">
    <h1>个人资料</h1>
</div>

<div id="feedback-container">
    <?php echo $feedback_message; ?>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3>修改密码</h3>
            </div>
            
            <form class="form-horizontal" action="<?php echo CP_BASE_URL; ?>profile_save" method="post">
                <div class="card-body">
                    
                    <div class="form-group">
                        <label>用户名</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['user_login']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="current_password">当前密码</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">新密码</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">确认新密码</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary btn-lg">提交修改</button>
                </div>
            </form>
        </div>
    </div>
</div>