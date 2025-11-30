<?php
/**
 * 盘点功能主页
 * 库存盘点系统入口页面
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/inventory_check_auth.php';

// 检查权限：只有库管和管理员可以使用盘点功能
requireInventoryCheckPermission();
$base_name = '';
// 获取用户信息和基地信息
$user = getCurrentUser();
$baseId = $user['base_id'] ?? null;
$isManager = ($user['role'] === 'manager');
$isAdmin = ($user['role'] === 'admin');
if($baseId){
    $base_name = query('SELECT name FROM bases WHERE id = ?', [$baseId])->fetch_assoc()['name'];
}
// 检查盘点表是否存在
$tableExists = false;
$checkSql = "SHOW TABLES LIKE 'inventory_check_tasks'";
$checkResult = query($checkSql);
if ($checkResult && $checkResult->num_rows > 0) {
    $tableExists = true;
}

// 初始化变量
$recentTasks = [];
$stats = [
    'total_tasks' => 0,
    'active_tasks' => 0,
    'completed_today' => 0,
    'total_packages' => 0
];



// 只有表存在才执行查询
if ($tableExists) {
    // 获取最近的盘点任务
    if ($isAdmin) {
        // 管理员：查看所有任务
        $sql = "SELECT t.*, u.username as creator_name, b.name as base_name 
                FROM inventory_check_tasks t 
                LEFT JOIN users u ON t.created_by = u.id 
                LEFT JOIN bases b ON t.base_id = b.id 
                ORDER BY t.created_at DESC 
                LIMIT 5";
        try {
            $result = query($sql);
        } catch (Exception $e) {
            $result = false;
        }
    } else {
        // 库管：查看自己基地的任务
        $sql = "SELECT t.*, u.username as creator_name, b.name as base_name 
                FROM inventory_check_tasks t 
                LEFT JOIN users u ON t.created_by = u.id 
                LEFT JOIN bases b ON t.base_id = b.id 
                WHERE t.base_id = ?
                ORDER BY t.created_at DESC 
                LIMIT 5";
        try {
            $result = query($sql, [$baseId]);
        } catch (Exception $e) {
            $result = false;
        }
    }
    if ($result && $result !== false) {
        while ($row = $result->fetch_assoc()) {
            $recentTasks[] = $row;
        }
        $result->free(); // 释放结果
    }

    // 统计数据
    if ($isAdmin) {
        // 管理员：统计所有任务
        $sql = "SELECT COUNT(*) as count FROM inventory_check_tasks";
        try {
            $result = query($sql);
            if ($result && $row = $result->fetch_assoc()) {
                $stats['total_tasks'] = $row['count'];
            }
            if ($result) $result->free();
        } catch (Exception $e) {
            // 忽略错误，保持默认值0
        }

        $sql = "SELECT COUNT(*) as count FROM inventory_check_tasks WHERE status IN ('created', 'in_progress')";
        try {
            $result = query($sql);
            if ($result && $row = $result->fetch_assoc()) {
                $stats['active_tasks'] = $row['count'];
            }
            if ($result) $result->free();
        } catch (Exception $e) {
            // 忽略错误，保持默认值0
        }

        $sql = "SELECT COUNT(*) as count FROM inventory_check_tasks WHERE status = 'completed' AND DATE(completed_at) = CURDATE()";
        try {
            $result = query($sql);
            if ($result && $row = $result->fetch_assoc()) {
                $stats['completed_today'] = $row['count'];
            }
            if ($result) $result->free();
        } catch (Exception $e) {
            // 忽略错误，保持默认值0
        }
    } else {
        // 库管：统计自己基地的任务
        $sql = "SELECT COUNT(*) as count FROM inventory_check_tasks WHERE base_id = ?";
        try {
            $result = query($sql, [$baseId]);
            if ($result && $row = $result->fetch_assoc()) {
                $stats['total_tasks'] = $row['count'];
            }
            if ($result) $result->free();
        } catch (Exception $e) {
            // 忽略错误，保持默认值0
        }

        $sql = "SELECT COUNT(*) as count FROM inventory_check_tasks WHERE base_id = ? AND status IN ('created', 'in_progress')";
        try {
            $result = query($sql, [$baseId]);
            if ($result && $row = $result->fetch_assoc()) {
                $stats['active_tasks'] = $row['count'];
            }
            if ($result) $result->free();
        } catch (Exception $e) {
            // 忽略错误，保持默认值0
        }

        $sql = "SELECT COUNT(*) as count FROM inventory_check_tasks WHERE base_id = ? AND status = 'completed' AND DATE(completed_at) = CURDATE()";
        try {
            $result = query($sql, [$baseId]);
            if ($result && $row = $result->fetch_assoc()) {
                $stats['completed_today'] = $row['count'];
            }
            if ($result) $result->free();
        } catch (Exception $e) {
            // 忽略错误，保持默认值0
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>库存盘点系统</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/inventory_check.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="inventory-check-container">
        <!-- 头部 -->
        <header class="inventory-check-header">
            <div class="header-left">
                <h1><i class="fas fa-clipboard-check"></i> 库存盘点系统</h1>
                <div class="user-info">
                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($user['username']); ?> (<?php echo getRoleDisplayName($user['role']); ?>)</span>
                    <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($base_name ?: ($isAdmin ? '所有基地' : '未分配基地')); ?></span>
                    <?php if ($isManager): ?>
                    <span><i class="fas fa-check-circle" style="color: #28a745;"></i> 可创建盘点任务</span>
                    <?php elseif ($isAdmin): ?>
                    <span><i class="fas fa-eye" style="color: #17a2b8;"></i> 只读权限</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="header-right">
                <a href="../admin/" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> 返回后台
                </a>
                <a href="../logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> 退出
                </a>
            </div>
        </header>

        <!-- 统计卡片 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['total_tasks']; ?></h3>
                    <p>总盘点任务</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['active_tasks']; ?></h3>
                    <p>进行中任务</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $stats['completed_today']; ?></h3>
                    <p>今日完成</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="stat-content">
                    <h3>-</h3>
                    <p>库存包数</p>
                </div>
            </div>
        </div>

        <!-- 快速操作 -->
        <div class="quick-actions">
            <h2><i class="fas fa-bolt"></i> 快速操作</h2>
            <div class="actions-grid">
                <?php if ($isManager): ?>
                <a href="inventory_check.php" class="action-card primary">
                    <div class="action-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="action-content">
                        <h3>创建盘点任务</h3>
                        <p>新建一个盘点任务</p>
                    </div>
                </a>
                <?php endif; ?>
                <a href="inventory_check_list.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="action-content">
                        <h3>任务列表</h3>
                        <p><?php echo $isAdmin ? '查看所有盘点任务' : '查看我的盘点任务'; ?></p>
                    </div>
                </a>
                <?php if ($isManager): ?>
                <a href="inventory_check_import.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-file-import"></i>
                    </div>
                    <div class="action-content">
                        <h3>Excel导入</h3>
                        <p>批量导入盘点数据</p>
                    </div>
                </a>
                <?php endif; ?>
                <a href="../api/inventory_check.html" class="action-card" target="_blank">
                    <div class="action-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div class="action-content">
                        <h3>PDA盘点</h3>
                        <p>扫码盘点接口</p>
                    </div>
                </a>
            </div>
        </div>

        <!-- 最近任务 -->
        <div class="recent-tasks">
            <h2><i class="fas fa-history"></i> 最近任务</h2>
            <?php if (!$tableExists): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    盘点功能数据表尚未创建，请先
                    <a href="inventory_check_install.php" class="btn btn-sm btn-primary">安装数据库表</a>
                </div>
            <?php elseif (!empty($recentTasks)): ?>
                <div class="tasks-table">
                    <table>
                        <thead>
                            <tr>
                                <th>任务名称</th>
                                <th>基地</th>
                                <th>类型</th>
                                <th>状态</th>
                                <th>进度</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTasks as $task): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($task['task_name']); ?></td>
                                <td><?php echo htmlspecialchars($task['base_name']); ?></td>
                                <td><?php echo getTaskTypeText($task['task_type']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $task['status']; ?>">
                                        <?php echo getStatusText($task['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($task['total_packages'] > 0): ?>
                                        <?php echo round(($task['checked_packages'] / $task['total_packages']) * 100, 1); ?>%
                                    <?php else: ?>
                                        0%
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($task['created_at'])); ?></td>
                                <td>
                                    <a href="inventory_check_view.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-primary">
                                        查看
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>暂无盘点任务</p>
                    <a href="inventory_check.php" class="btn btn-primary">创建第一个任务</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 页面交互效果
        document.addEventListener('DOMContentLoaded', function() {
            // 添加卡片点击效果
            const cards = document.querySelectorAll('.action-card, .stat-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>

<?php
/**
 * 获取任务类型文本
 */
function getTaskTypeText($type) {
    $types = [
        'full' => '全盘',
        'partial' => '部分盘点',
        'random' => '抽盘'
    ];
    return $types[$type] ?? $type;
}

/**
 * 获取状态文本
 */
function getStatusText($status) {
    $statuses = [
        'created' => '已创建',
        'in_progress' => '进行中',
        'completed' => '已完成',
        'cancelled' => '已取消'
    ];
    return $statuses[$status] ?? $status;
}
?>