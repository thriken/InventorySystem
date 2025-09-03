<?php
require_once 'config/config.php';
require_once 'includes/auth.php';

// 启动会话
session_start();

// 手动清除所有会话变量
$_SESSION = [];

// 如果使用了会话cookie，则清除会话cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 销毁会话
session_destroy();

// 重新启动一个新的会话以确保完全清除
session_start();
session_regenerate_id(true);

// 重定向到登录页面
header('Location: login.php');

exit();
?>