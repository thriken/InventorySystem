<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// 要求用户登录
requireLogin();

// 检查是否为viewer角色
requireRole(['viewer']);

// 获取当前用户信息
$currentUser = getCurrentUser();

// 获取用户所属基地的库存统计
$baseCondition = $currentUser['base_id'] ? "AND sr.base_id = ?" : "";
$baseParams = $currentUser['base_id'] ? [$currentUser['base_id']] : [];

$stats = [
    'total_packages' => fetchOne("SELECT COUNT(*) FROM glass_packages gp 
                                 LEFT JOIN storage_racks sr ON gp.current_rack_id = sr.id 
                                 WHERE 1=1 $baseCondition", $baseParams),
    'in_storage' => fetchOne("SELECT COUNT(*) FROM glass_packages gp 
                             LEFT JOIN storage_racks sr ON gp.current_rack_id = sr.id 
                             WHERE gp.status = 'in_storage' $baseCondition", $baseParams),
    'in_processing' => fetchOne("SELECT COUNT(*) FROM glass_packages gp 
                               LEFT JOIN storage_racks sr ON gp.current_rack_id = sr.id 
                               WHERE gp.status = 'in_processing' $baseCondition", $baseParams),
];

// 获取基地信息
$baseName = '所有基地';
if ($currentUser['base_id']) {
    $baseInfo = fetchRow("SELECT name FROM bases WHERE id = ?", [$currentUser['base_id']]);
    $baseName = $baseInfo ? $baseInfo['name'] : '未知基地';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>库存查看 - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .viewer-layout {
            min-height: 100vh;
            background-color: #f5f5f5;
        }
        
        /* 统一头部样式 */
        .viewer-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .system-title {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }
        
        .page-title {
            font-size: 18px;
            margin: 0;
            opacity: 0.9;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.1);
            padding: 8px 15px;
            border-radius: 20px;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .nav-links {
            display: flex;
            gap: 10px;
        }
        
        .nav-link {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .nav-link.active {
            background: rgba(255,255,255,0.2);
        }
        
        /* 内容区域 */
        .viewer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 16px;
            font-weight: 500;
        }
        
        .quick-actions {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .quick-actions h3 {
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .action-btn {
            display: block;
            padding: 15px 20px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            text-align: center;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .action-btn.secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
        }
        
        .action-btn.secondary:hover {
            box-shadow: 0 5px 15px rgba(149, 165, 166, 0.4);
        }
    </style>
</head>
<body>
    <div class="viewer-layout">
        <!-- 统一头部 -->
        <header class="viewer-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="system-title"><?php echo APP_NAME; ?></h1>
                    <h2 class="page-title">库存查看系统</h2>
                </div>
                <div class="header-right">
                    <nav class="nav-links">
                        <a href="index.php" class="nav-link active">首页</a>
                        <a href="inventory.php" class="nav-link">库存查询</a>
                        <a href="processing_inventory.php" class="nav-link">加工区库存</a>
                    </nav>
                    <div class="user-info">
                        <div class="user-avatar"><?php echo mb_substr($currentUser['name'], 0, 1); ?></div>
                        <div>
                            <div><?php echo htmlspecialchars($currentUser['name']); ?></div>
                            <div style="font-size: 12px; opacity: 0.8;"><?php echo htmlspecialchars($baseName); ?></div>
                        </div>
                    </div>
                    <a href="../logout.php" class="nav-link">退出</a>
                </div>
            </div>
        </header>
        
        <!-- 内容区域 -->
        <div class="viewer-container">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total_packages']); ?></div>
                    <div class="stat-label">总包数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['in_storage']); ?></div>
                    <div class="stat-label">库存中</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['in_processing']); ?></div>
                    <div class="stat-label">加工中</div>
                </div>
            </div>
            
            <div class="quick-actions">
                <h3>快速操作</h3>
                <div class="action-buttons">
                    <a href="inventory.php" class="action-btn">
                        📦 查看库存原片
                    </a>
                    <a href="processing_inventory.php" class="action-btn secondary">
                        🔧 查看加工区库存
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>