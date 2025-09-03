<?php
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// 检查用户是否已登录
if (!isLoggedIn()) {
    redirect('login.php');
}

// 获取当前用户信息
$currentUser = getCurrentUser();

// 根据设备类型和用户角色重定向到相应界面
if (isMobile()) {
    redirect('mobile/index.php');
} else {
    // 根据用户角色重定向到不同界面
    if ($currentUser['role'] === 'viewer') {
        redirect('viewer/index.php');
    } else {
        redirect('admin/index.php');
    }
}
?>
