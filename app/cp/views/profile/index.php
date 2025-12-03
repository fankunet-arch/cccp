<?php
// /app/cp/views/profile/index.php
// Enhanced Modern Design

// 检查是否有来自 save action 的反馈信息
$feedback_message = '';
if (isset($_SESSION['profile_feedback'])) {
    $feedback = $_SESSION['profile_feedback'];
    $alert_type = $feedback['type'];
    $icon = $feedback['type'] === 'success' ? 'check-circle' : 'exclamation-circle';

    $feedback_message = <<<HTML
    <div id="feedback-bar" class="profile-feedback {$alert_type}">
        <i class="fas fa-{$icon}"></i>
        <span>{$feedback['message']}</span>
    </div>
HTML;
    unset($_SESSION['profile_feedback']);
}
?>

<style>
/* Profile Page Modern Styling */
.profile-container {
    max-width: 800px;
    margin: 40px auto;
    padding: 0 20px;
}

.profile-header {
    text-align: center;
    margin-bottom: 40px;
}

.profile-header h1 {
    font-size: 32px;
    font-weight: 800;
    color: var(--c-text, #1e293b);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.profile-header p {
    color: var(--c-muted, #64748b);
    font-size: 16px;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 36px;
    color: white;
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
}

.profile-card {
    background: var(--c-panel, #fff);
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
    overflow: hidden;
    border: 1px solid var(--c-border, #e8ecf2);
}

.profile-card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 24px 32px;
    color: white;
}

.profile-card-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
}

.profile-card-body {
    padding: 32px;
}

.profile-form-group {
    margin-bottom: 24px;
}

.profile-form-group label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    color: var(--c-text, #334155);
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.profile-form-control {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid var(--c-border, #e2e8f0);
    border-radius: 12px;
    font-size: 15px;
    transition: all 0.2s ease;
    background: var(--c-surface, #fff);
    color: var(--c-text, #1e293b);
}

.profile-form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.profile-form-control:disabled {
    background: var(--c-surface, #f1f5f9);
    color: var(--c-muted, #94a3b8);
    cursor: not-allowed;
}

.profile-username-display {
    display: flex;
    align-items: center;
    padding: 14px 18px;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
    border: 2px dashed var(--c-border, #e2e8f0);
    border-radius: 12px;
    color: var(--c-text, #1e293b);
    font-weight: 600;
}

.profile-username-display i {
    margin-right: 10px;
    color: #667eea;
}

.profile-card-footer {
    padding: 24px 32px;
    background: linear-gradient(180deg, transparent, rgba(102, 126, 234, 0.02));
    border-top: 1px solid var(--c-border, #e8ecf2);
    display: flex;
    justify-content: flex-end;
}

.profile-btn-submit {
    padding: 14px 32px;
    border: none;
    border-radius: 12px;
    font-weight: 700;
    font-size: 16px;
    cursor: pointer;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 14px rgba(102, 126, 234, 0.4);
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.profile-btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
}

.profile-btn-submit:active {
    transform: translateY(0);
}

.profile-feedback {
    padding: 16px 24px;
    border-radius: 14px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
    animation: slideInDown 0.4s ease;
}

.profile-feedback.success {
    background: #d1fae5;
    color: #065f46;
    border: 2px solid #6ee7b7;
}

.profile-feedback.danger {
    background: #fee2e2;
    color: #991b1b;
    border: 2px solid #fca5a5;
}

.profile-feedback i {
    font-size: 20px;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.password-strength-indicator {
    height: 6px;
    background: var(--c-border, #e2e8f0);
    border-radius: 3px;
    margin-top: 8px;
    overflow: hidden;
}

.password-strength-bar {
    height: 100%;
    width: 0;
    transition: all 0.3s ease;
    border-radius: 3px;
}

.password-strength-text {
    font-size: 12px;
    margin-top: 6px;
    font-weight: 600;
}

.info-box {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(147, 51, 234, 0.05) 100%);
    border-left: 4px solid #667eea;
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 24px;
}

.info-box p {
    margin: 0;
    color: var(--c-text, #475569);
    font-size: 14px;
    line-height: 1.6;
}

.info-box i {
    color: #667eea;
    margin-right: 8px;
}
</style>

<div class="profile-container">

    <div class="profile-header">
        <div class="profile-avatar">
            <i class="fas fa-user"></i>
        </div>
        <h1>
            <i class="fas fa-user-circle"></i>
            个人资料
        </h1>
        <p>管理您的账户安全设置</p>
    </div>

    <?php echo $feedback_message; ?>

    <div class="profile-card">
        <div class="profile-card-header">
            <h3>
                <i class="fas fa-key"></i>
                修改密码
            </h3>
        </div>

        <form action="<?php echo CP_BASE_URL; ?>profile_save" method="post">
            <div class="profile-card-body">

                <div class="info-box">
                    <p>
                        <i class="fas fa-shield-alt"></i>
                        为了保护您的账户安全，请定期更新密码，并使用强密码（至少8位，包含字母和数字）
                    </p>
                </div>

                <div class="profile-form-group">
                    <label>
                        <i class="fas fa-user"></i>
                        用户名
                    </label>
                    <div class="profile-username-display">
                        <i class="fas fa-id-badge"></i>
                        <?php echo htmlspecialchars($_SESSION['user_login']); ?>
                    </div>
                </div>

                <div class="profile-form-group">
                    <label for="current_password">
                        <i class="fas fa-lock"></i>
                        当前密码
                    </label>
                    <input
                        type="password"
                        class="profile-form-control"
                        id="current_password"
                        name="current_password"
                        required
                        placeholder="请输入您的当前密码"
                        autocomplete="current-password"
                    >
                </div>

                <div class="profile-form-group">
                    <label for="new_password">
                        <i class="fas fa-key"></i>
                        新密码
                    </label>
                    <input
                        type="password"
                        class="profile-form-control"
                        id="new_password"
                        name="new_password"
                        required
                        placeholder="请输入新密码"
                        autocomplete="new-password"
                    >
                    <div class="password-strength-indicator">
                        <div class="password-strength-bar" id="strength-bar"></div>
                    </div>
                    <div class="password-strength-text" id="strength-text"></div>
                </div>

                <div class="profile-form-group">
                    <label for="confirm_password">
                        <i class="fas fa-check-double"></i>
                        确认新密码
                    </label>
                    <input
                        type="password"
                        class="profile-form-control"
                        id="confirm_password"
                        name="confirm_password"
                        required
                        placeholder="请再次输入新密码"
                        autocomplete="new-password"
                    >
                </div>

            </div>

            <div class="profile-card-footer">
                <button type="submit" class="profile-btn-submit">
                    <i class="fas fa-save"></i>
                    保存修改
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// 密码强度检测
document.addEventListener('DOMContentLoaded', function() {
    const newPasswordInput = document.getElementById('new_password');
    const strengthBar = document.getElementById('strength-bar');
    const strengthText = document.getElementById('strength-text');

    function checkPasswordStrength(password) {
        let strength = 0;
        if (password.length >= 8) strength++;
        if (password.length >= 12) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^a-zA-Z0-9]/.test(password)) strength++;

        return strength;
    }

    newPasswordInput.addEventListener('input', function() {
        const password = this.value;
        const strength = checkPasswordStrength(password);

        let width = 0;
        let color = '';
        let text = '';

        if (strength === 0) {
            width = 0;
        } else if (strength <= 2) {
            width = 33;
            color = '#ef4444';
            text = '弱';
        } else if (strength <= 4) {
            width = 66;
            color = '#f59e0b';
            text = '中等';
        } else {
            width = 100;
            color = '#22c55e';
            text = '强';
        }

        strengthBar.style.width = width + '%';
        strengthBar.style.background = color;
        strengthText.textContent = text ? '密码强度: ' + text : '';
        strengthText.style.color = color;
    });

    // 自动隐藏反馈消息
    const feedbackBar = document.getElementById('feedback-bar');
    if (feedbackBar) {
        setTimeout(() => {
            feedbackBar.style.opacity = '0';
            setTimeout(() => feedbackBar.remove(), 300);
        }, 5000);
    }
});
</script>