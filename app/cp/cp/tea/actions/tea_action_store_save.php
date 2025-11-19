<?php
// /app/cp/tea/actions/tea_action_store_save.php
// <tea> Project Store Save Action

declare(strict_types=1);

// $pdo 变量和 session 均已由 /app/cp/bootstrap.php 初始化
// require_login() 已在 /app/cp/index.php 中执行

$redirect_url = CP_BASE_URL . "tea_store_manage";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['tea_feedback'] = ['type' => 'danger', 'message' => '无效的请求方式。'];
    header("Location: " . $redirect_url);
    exit();
}

$store_name = trim($_POST['store_name'] ?? '');
$user_id = (int)($_SESSION['user_id'] ?? 0);

if (empty($store_name)) {
    $_SESSION['tea_feedback'] = ['type' => 'danger', 'message' => '店铺名称不能为空。'];
    header("Location: " . $redirect_url);
    exit();
}

try {
    global $pdo;

    // 检查店铺是否已存在
    $stmt_check = $pdo->prepare("SELECT id FROM tea_stores WHERE store_name = :store_name");
    $stmt_check->execute([':store_name' => $store_name]);
    if ($stmt_check->fetch()) {
        $_SESSION['tea_feedback'] = ['type' => 'danger', 'message' => '店铺名称已存在：' . htmlspecialchars($store_name)];
        header("Location: " . $redirect_url);
        exit();
    }

    // 插入新店铺
    $stmt_insert = $pdo->prepare("INSERT INTO tea_stores (store_name) VALUES (:store_name)");
    $stmt_insert->execute([':store_name' => $store_name]);
    
    $_SESSION['tea_feedback'] = ['type' => 'success', 'message' => '店铺 "' . htmlspecialchars($store_name) . '" 已成功添加。'];

} catch (\PDOException $e) {
    error_log("Tea Store Save Error: " . $e->getMessage());
    $_SESSION['tea_feedback'] = ['type' => 'danger', 'message' => '数据库操作失败：' . $e->getMessage()];
} catch (\Throwable $e) {
    error_log("Tea Store Save Error: " . $e->getMessage());
    $_SESSION['tea_feedback'] = ['type' => 'danger', 'message' => '服务器错误，保存失败。'];
}

header("Location: " . $redirect_url);
exit();