<?php
/**
 * 盘点功能头部文件
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/inventory_check_auth.php';
require_once '../includes/inventory_operations.php';

// 检查权限：只有库管和管理员可以使用盘点功能
requireInventoryCheckPermission();

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? '库存盘点系统'; ?></title>
    
    <!-- Bootstrap CSS -->
     <!-- 本地 Bootstrap CSS 
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/font-awesome.min.css">
    -->
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css" integrity="sha384-HSMxcRTRxnN+Bdg0JdbxYKrThecOKuH5zCYotlSAcp1+c8xmyTe9GYg1l9a69psu" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.bootcdn.net/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" />
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/inventory_check.css">
    <script src="../assets/js/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/bootstrap-3.4.1.min.js"></script>
    <!-- jQuery -->
    <!--script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.6.0/jquery.min.js" crossorigin="anonymous"></script-->
    <!-- Bootstrap JS -->
    <!--script src="https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/3.4.1/js/bootstrap.min.js" crossorigin="anonymous"></script-->
</head>
<body>
    
    <!-- 顶部导航栏 -->
    <nav class="navbar navbar-default navbar-fixed-top">
        <div class="container-fluid">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar">
                    <span class="sr-only">切换导航</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="index.php">
                    <i class="glyphicon glyphicon-check"></i> 库存盘点系统
                </a>
            </div>
            
            <div class="navbar-collapse collapse" id="navbar">
                <ul class="nav navbar-nav">
                    <li><a href="index.php"><i class="glyphicon glyphicon-home"></i> 首页</a></li>
                    <li><a href="inventory_check.php"><i class="glyphicon glyphicon-list-alt"></i> 任务管理</a></li>
                    <li><a href="inventory_check_import.php"><i class="glyphicon glyphicon-import"></i> Excel导入</a></li>
                </ul>
                
                <ul class="nav navbar-nav navbar-right">
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                            <i class="glyphicon glyphicon-user"></i> <?php echo htmlspecialchars($user['username']); ?>
                            <span class="caret"></span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="../profile.php"><i class="glyphicon glyphicon-cog"></i> 个人设置</a></li>
                            <li role="separator" class="divider"></li>
                            <li><a href="../admin/"><i class="glyphicon glyphicon-arrow-left"></i> 返回后台</a></li>
                            <li><a href="../logout.php"><i class="glyphicon glyphicon-log-out"></i> 退出登录</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- 主内容区域 -->
    <div class="container-fluid" style="margin-top: 60px;">