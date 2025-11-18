<?php
// /app/cp/src/auth.php
// (函数 process_login, process_logout, is_logged_in, require_login 保持不变)
// (我们只重写 display_login_page)

// 确保函数存在 (如果这是单独执行)
if (!function_exists('process_login')) {
    
    function is_logged_in(): bool {
        return isset($_SESSION['user_id']);
    }
    
    function require_login(): void {
        if (!is_logged_in()) {
            header("Location: /cp/index.php");
            exit();
        }
    }
    
    function process_login(PDO $pdo): void {
        $login = $_POST['user_login'] ?? '';
        $secret = $_POST['user_secret'] ?? '';

        if (empty($login) || empty($secret)) {
            $_SESSION['login_error'] = "请输入用户名和密码。";
            header("Location: /cp/index.php");
            exit();
        }

        $stmt = $pdo->prepare("SELECT user_id, user_login, user_secret_hash, user_display_name, user_status FROM sys_users WHERE user_login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if ($user && $user['user_status'] === 'active' && password_verify($secret, $user['user_secret_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_login'] = $user['user_login'];
            $_SESSION['user_display_name'] = $user['user_display_name'];
            header("Location: /cp/index.php?action=dashboard");
            exit();
        } else {
            $_SESSION['login_error'] = "用户名或密码错误，或账户未激活。";
            header("Location: /cp/index.php");
            exit();
        }
    }

    function process_logout(): void {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        header("Location: /cp/index.php");
        exit();
    }
}

/**
 * 显示登录页面的HTML (Bootstrap 5 现代版)
 */
function display_login_page(): void
{
    $error_message = '';
    if (isset($_SESSION['login_error'])) {
        $error_message = '<div class="alert alert-danger" role="alert">' . htmlspecialchars($_SESSION['login_error']) . '</div>';
        unset($_SESSION['login_error']); // 显示后立即销毁
    }

    echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统登录</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            background-color: #f0f2f5;
        }
        .login-card {
            max-width: 450px;
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center h-100">
    
    <main class="w-100 m-auto p-4">
        <div class="card login-card mx-auto">
            <div class="card-body p-4 p-sm-5">
                <h3 class="card-title text-center mb-4 fw-bold">Sushisom CP</h3>
                
                {$error_message}

                <form action="/cp/index.php?action=login_process" method="POST">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="user_login" name="user_login" placeholder="用户名" required>
                        <label for="user_login"><i class="fa-regular fa-user me-2"></i>用户名</label>
                    </div>
                    <div class="form-floating mb-3">
                        <input type="password" class="form-control" id="user_secret" name="user_secret" placeholder="密码" required>
                        <label for="user_secret"><i class="fa-solid fa-lock me-2"></i>密码</label>
                    </div>
                    
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg fw-semibold">登 录</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
HTML;
}
?>