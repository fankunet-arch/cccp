<?php
// /app/cp/actions/profile/save.php

declare(strict_types=1);

// $pdo 和 session 已由 index.php 和 bootstrap.php 加载
// require_login() 已在 index.php 中执行

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . CP_BASE_URL . "profile");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// 1. 验证输入
if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    $_SESSION['profile_feedback'] = ['type' => 'danger', 'message' => '所有字段均为必填项。'];
    header("Location: " . CP_BASE_URL . "profile");
    exit();
}

if ($new_password !== $confirm_password) {
    $_SESSION['profile_feedback'] = ['type' => 'danger', 'message' => '两次输入的新密码不一致。'];
    header("Location: " . CP_BASE_URL . "profile");
    exit();
}

try {
    // 2. 验证当前密码
    $stmt = $pdo->prepare("SELECT user_secret_hash FROM sys_users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current_password, $user['user_secret_hash'])) {
        $_SESSION['profile_feedback'] = ['type' => 'danger', 'message' => '当前密码不正确。'];
        header("Location: " . CP_BASE_URL . "profile");
        exit();
    }

    // 3. 更新新密码
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt_update = $pdo->prepare("UPDATE sys_users SET user_secret_hash = ? WHERE user_id = ?");
    $stmt_update->execute([$new_hash, $user_id]);

    $_SESSION['profile_feedback'] = ['type' => 'success', 'message' => '密码修改成功。'];

} catch (PDOException $e) {
    error_log("Profile Save Error: " . $e->getMessage());
    $_SESSION['profile_feedback'] = ['type' => 'danger', 'message' => '数据库错误，修改失败。'];
}

header("Location: " . CP_BASE_URL . "profile");
exit();