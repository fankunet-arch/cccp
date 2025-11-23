<?php
// /app/actions/profile/save.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 身份验证
if (!isset($_SESSION['user_id'])) {
    header("Location: /cp/index.php");
    exit();
}

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['profile_feedback'] = ['type' => 'danger', 'message' => '无效的请求方式。'];
    header("Location: /cp/index.php?action=profile");
    exit();
}
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$user_id = $_SESSION['user_id'];

// 基础验证
if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    $_SESSION['profile_feedback'] = ['type' => 'danger', 'message' => '所有密码字段均为必填项。'];
    header("Location: /cp/index.php?action=profile");
    exit();
}

if ($new_password !== $confirm_password) {
    $_SESSION['profile_feedback'] = ['type' => 'danger', 'message' => '新密码与确认密码不匹配。'];
    header("Location: /cp/index.php?action=profile");
    exit();
}

if (strlen($new_password) < 8) {
    $_SESSION['profile_feedback'] = ['type' => 'danger', 'message' => '为了安全，新密码长度不能少于8个字符。'];
    header("Location: /cp/index.php?action=profile");
    exit();
}

try {
    // 1. 验证当前密码是否正确
    $stmt = $pdo->prepare("SELECT user_secret_hash FROM sys_users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current_password, $user['user_secret_hash'])) {
        $_SESSION['profile_feedback'] = ['type' => 'danger', 'message' => '当前密码不正确。'];
        header("Location: /cp/index.php?action=profile");
        exit();
    }

    // 2. 将新密码哈希化并更新到数据库
    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt_update = $pdo->prepare("UPDATE sys_users SET user_secret_hash = ? WHERE user_id = ?");
    $stmt_update->execute([$new_password_hash, $user_id]);

    $_SESSION['profile_feedback'] = ['type' => 'success', 'message' => '密码已成功修改！'];

} catch (PDOException $e) {
    error_log("Profile save error: " . $e->getMessage());
    $_SESSION['profile_feedback'] = ['type' => 'danger', 'message' => '数据库操作失败，请联系管理员。'];
}

header("Location: /cp/index.php?action=profile");
exit();