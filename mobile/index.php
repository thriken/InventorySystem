<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/app_info.php';

// 要求用户登录
requireLogin();

// 获取当前用户信息
$currentUser = getCurrentUser();
$baseName = '';
if($currentUser['role'] !== 'admin'){
    $baseName = "[";
    $baseName .=  fetchOne("SELECT name FROM bases WHERE id = {$currentUser['base_id']}"); 
    $baseName .= "]";
}
$role =  ROLE_NAMES[$currentUser['role']];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>移动端 - <?php echo getAppName(); ?></title>
    <meta name="format-detection" content="telephone=no">
    <meta name="msapplication-tap-highlight" content="no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/mobile.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        
        .header {
            background-color: #4CAF50;
            color: white;
            padding: 15px;
            text-align: center;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            margin: 0;
            font-size: 20px;
        }
        
        .user-info {
            font-size: 14px;
            margin-top: 5px;
        }
        
        .content {
            margin-top: 80px;
            padding: 15px;
            margin-bottom: 47px;
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .menu-item {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-decoration: none;
            color: #333;
        }
        
        .menu-item i {
            font-size: 24px;
            margin-bottom: 10px;
            color: #4CAF50;
        }
        
        .menu-item h3 {
            margin: 0;
            font-size: 16px;
        }
        
        .footer {
            background-color: #f1f1f1;
            padding: 15px;
            text-align: center;
            font-size: 12px;
            color: #666;
            position: fixed;
            bottom: 0;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo getAppName(); ?> - 移动端</h1>
        <div class="user-info">
            <?php echo htmlspecialchars($baseName); ?>
            <?php echo htmlspecialchars($role); ?>
            <?php echo htmlspecialchars($currentUser['name']); ?>
        </div>
    </div>
    
    <div class="content">
        <div class="menu-grid">
            <a href="scan.php" class="menu-item">
                <i>📷</i>
                <h2>扫描操作</h2>
                扫码操作快速出入库
            </a>
            <a href="transfer.php" class="menu-item">
                <i>🔄</i>
                <h2>流转操作</h2>
                跨基地原片包流转操作
            </a>
            <?php
            if($currentUser['role'] === 'manager'){
                echo '
                        <a href="addPack.php" class="menu-item">
                            <i>➕</i>
                            <h2>添加原片包</h2>
                            新增原片
                        </a>
                ';
            }
            ?>
            <a href="history.php" class="menu-item">
                <i>📋</i>
                <h2>历史记录</h2>
                操作记录
            </a>
            <a href="../logout.php" class="menu-item">
                <i>🚪</i>
                <h2>退出登录</h2>
            </a>
        </div>
    </div>
    <div class="footer">
        <?php echo getAppName(); ?> v<?php echo getAppVersion(); ?> &copy; <?php echo date('Y'); ?>
    </div>
</body>
</html>