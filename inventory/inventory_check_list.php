<?php 
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/inventory_check_auth.php';

// 检查权限
requireInventoryCheckPermission();

$pageTitle = '库存盘点管理';
$user = getCurrentUser();
$baseId = $user['base_id'] ?? null;
$isManager = ($user['role'] === 'manager');
$isAdmin = ($user['role'] === 'admin');

// 初始化变量
$tasks = [];
$stats = [
    'total_tasks' => 0,
    'in_progress_count' => 0,
    'completed_count' => 0,
    'difference_tasks' => 0
];

// 检查表是否存在
$tableExists = false;
$checkSql = "SHOW TABLES LIKE 'inventory_check_tasks'";
$checkResult = query($checkSql);
if ($checkResult && $checkResult->num_rows > 0) {
    $tableExists = true;
}

// 获取任务数据
if ($tableExists) {
    try {
        if ($isAdmin) {
            // 管理员：查看所有任务
            $sql = "SELECT t.*, u.username as created_by_name, b.name as base_name,
                           CASE 
                               WHEN t.total_packages > 0 THEN ROUND((t.checked_packages / t.total_packages) * 100, 1)
                               ELSE 0 
                           END as completion_rate
                    FROM inventory_check_tasks t 
                    LEFT JOIN users u ON t.created_by = u.id 
                    LEFT JOIN bases b ON t.base_id = b.id 
                    ORDER BY t.created_at DESC";
            $result = query($sql);
        } else {
            // 库管：查看自己基地的任务
            $sql = "SELECT t.*, u.username as created_by_name, b.name as base_name,
                           CASE 
                               WHEN t.total_packages > 0 THEN ROUND((t.checked_packages / t.total_packages) * 100, 1)
                               ELSE 0 
                           END as completion_rate
                    FROM inventory_check_tasks t 
                    LEFT JOIN users u ON t.created_by = u.id 
                    LEFT JOIN bases b ON t.base_id = b.id 
                    WHERE t.base_id = ?
                    ORDER BY t.created_at DESC";
            $result = query($sql, [$baseId]);
        }
        
        if ($result && $result !== false) {
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
            $result->free();
        }
        
        // 获取统计数据
        if ($isAdmin) {
            // 管理员统计所有任务
            $sql = "SELECT COUNT(*) as count FROM inventory_check_tasks";
            $result = query($sql);
            if ($result && $row = $result->fetch_assoc()) {
                $stats['total_tasks'] = $row['count'];
            }
            if ($result) $result->free();
            
            $sql = "SELECT COUNT(*) as count FROM inventory_check_tasks WHERE status = 'in_progress'";
            $result = query($sql);
            if ($result && $row = $result->fetch_assoc()) {
                $stats['in_progress_count'] = $row['count'];
            }
            if ($result) $result->free();
            
            $sql = "SELECT COUNT(*) as count FROM inventory_check_tasks WHERE status = 'completed'";
            $result = query($sql);
            if ($result && $row = $result->fetch_assoc()) {
                $stats['completed_count'] = $row['count'];
            }
            if ($result) $result->free();
            
            $sql = "SELECT COUNT(*) as count FROM inventory_check_tasks WHERE status = 'completed' AND difference_count > 0";
            $result = query($sql);
            if ($result && $row = $result->fetch_assoc()) {
                $stats['difference_tasks'] = $row['count'];
            }
            if ($result) $result->free();
        } else {
            // 库管统计自己基地的任务
            $sql = "SELECT COUNT(*) as count FROM inventory_check_tasks WHERE base_id = ?";
            $result = query($sql, [$baseId]);
            if ($result && $row = $result->fetch_assoc()) {
                $stats['total_tasks'] = $row['count'];
            }
            if ($result) $result->free();
            
            $sql = "SELECT COUNT(*) as count FROM inventory_check_tasks WHERE base_id = ? AND status = 'in_progress'";
            $result = query($sql, [$baseId]);
            if ($result && $row = $result->fetch_assoc()) {
                $stats['in_progress_count'] = $row['count'];
            }
            if ($result) $result->free();
            
            $sql = "SELECT COUNT(*) as count FROM inventory_check_tasks WHERE base_id = ? AND status = 'completed'";
            $result = query($sql, [$baseId]);
            if ($result && $row = $result->fetch_assoc()) {
                $stats['completed_count'] = $row['count'];
            }
            if ($result) $result->free();
            
            $sql = "SELECT COUNT(*) as count FROM inventory_check_tasks WHERE base_id = ? AND status = 'completed' AND difference_count > 0";
            $result = query($sql, [$baseId]);
            if ($result && $row = $result->fetch_assoc()) {
                $stats['difference_tasks'] = $row['count'];
            }
            if ($result) $result->free();
        }
    } catch (Exception $e) {
        // 查询失败，保持默认值
        $tasks = [];
        $stats = [
            'total_tasks' => 0,
            'in_progress_count' => 0,
            'completed_count' => 0,
            'difference_tasks' => 0
        ];
    }
}

include 'header.php'; 
?>

<div class="container-fluid">
    <!-- 页面头部 -->
    <div class="row">
        <div class="col-md-12">
            <div class="page-header clearfix">
                <h2 class="pull-left">
                    <i class="glyphicon glyphicon-list-alt"></i> 库存盘点管理
                </h2>
                <div class="pull-right">
                    <?php if ($isManager): ?>
                    <a href="inventory_check.php?action=create" class="btn btn-primary">
                        <i class="glyphicon glyphicon-plus"></i> 创建盘点任务
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 统计卡片 -->
    <div class="row">
        <div class="col-md-3">
            <div class="panel panel-primary">
                <div class="panel-heading"><h4>总任务数</h4></div>
                <div class="panel-body text-center">
                    <h2><?php echo $stats['total_tasks']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h4>进行中</h4>
                </div>
                <div class="panel-body text-center">
                    <h2><?php echo $stats['in_progress_count']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h4>已完成</h4>
                </div>
                <div class="panel-body text-center">
                    <h2><?php echo $stats['completed_count']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-warning">
                <div class="panel-heading">
                    <h4>有差异任务</h4>
                </div>
                <div class="panel-body text-center">
                    <h2><?php echo $stats['difference_tasks']; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- 消息提示 -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?php
            switch ($_GET['success']) {
                case 'task_created':
                    echo '盘点任务创建成功！';
                    break;
                case 'task_started':
                    echo '盘点任务已开始！';
                    break;
                case 'task_completed':
                    echo '盘点任务已完成！';
                    break;
                case 'task_cancelled':
                    echo '盘点任务已取消！';
                    break;
                default:
                    echo '操作成功！';
            }
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?php
            switch ($_GET['error']) {
                case 'task_not_found':
                    echo '任务不存在或无权限访问！';
                    break;
                default:
                    echo '操作失败！';
            }
            ?>
        </div>
    <?php endif; ?>

    <!-- 任务列表 -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h4>盘点任务列表</h4>
        </div>
        <div class="panel-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>任务ID</th>
                            <th>任务名称</th>
                            <th>基地</th>
                            <th>盘点类型</th>
                            <th>状态</th>
                            <th>完成进度</th>
                            <th>差异数</th>
                            <th>创建人</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td><?php echo $task['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($task['task_name']); ?></strong>
                                    <?php if ($task['description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($task['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($task['base_name']); ?></td>
                                <td>
                                    <?php
                                    switch ($task['task_type']) {
                                        case 'full':
                                            echo '<span class="label label-primary">全盘</span>';
                                            break;
                                        case 'partial':
                                            echo '<span class="label label-warning">部分盘点</span>';
                                            break;
                                        case 'random':
                                            echo '<span class="label label-info">抽盘</span>';
                                            break;
                                        default:
                                            echo htmlspecialchars($task['task_type']);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    switch ($task['status']) {
                                        case 'created':
                                            echo '<span class="label label-default">已创建</span>';
                                            break;
                                        case 'in_progress':
                                            echo '<span class="label label-info">进行中</span>';
                                            break;
                                        case 'completed':
                                            echo '<span class="label label-success">已完成</span>';
                                            break;
                                        case 'cancelled':
                                            echo '<span class="label label-danger">已取消</span>';
                                            break;
                                        default:
                                            echo htmlspecialchars($task['status']);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="progress" style="height: 20px; margin-bottom: 0;">
                                        <div class="progress-bar progress-bar-<?php echo $task['completion_rate'] >= 90 ? 'success' : ($task['completion_rate'] >= 50 ? 'warning' : 'danger'); ?>" 
                                             role="progressbar" 
                                             style="width: <?php echo $task['completion_rate']; ?>%;">
                                            <?php echo $task['completion_rate']; ?>%
                                        </div>
                                    </div>
                                    <small><?php echo $task['checked_packages']; ?>/<?php echo $task['total_packages']; ?></small>
                                </td>
                                <td>
                                    <?php if ($task['difference_count'] > 0): ?>
                                        <span class="badge badge-warning"><?php echo $task['difference_count']; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($task['created_by_name']); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($task['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="inventory_check.php?action=view&id=<?php echo $task['id']; ?>" 
                                           class="btn btn-default" title="查看详情">
                                            <i class="glyphicon glyphicon-eye-open" style="font-size: 16px;"></i>
                                        </a>
                                        <a href="inventory_check.php?action=report&id=<?php echo $task['id']; ?>" 
                                           class="btn btn-default" title="查看盘点报告">
                                            <i class="glyphicon glyphicon-file" style="font-size: 16px;"></i>
                                        </a>
                                        <?php if ($isManager): ?>
                                            <?php if ($task['status'] === 'created'): ?>
                                                <a href="inventory_check.php?action=start&id=<?php echo $task['id']; ?>" 
                                                   class="btn btn-success" title="开始盘点"
                                                   onclick="return confirm('确定要开始这个盘点任务吗？')">
                                                    <i class="glyphicon glyphicon-play" style="font-size: 16px;"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($task['status'] === 'in_progress'): ?>
                                                <a href="inventory_check.php?action=complete&id=<?php echo $task['id']; ?>" 
                                                   class="btn btn-primary" title="完成盘点"
                                                   onclick="return confirm('确定要完成这个盘点任务吗？')">
                                                    <i class="glyphicon glyphicon-ok" style="font-size: 16px;"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($task['status'] === 'completed'): ?>
                                                <a href="inventory_check.php?action=report&id=<?php echo $task['id']; ?>" 
                                                   class="btn btn-info" title="查看报告">
                                                    <i class="glyphicon glyphicon-stats" style="font-size: 16px;"></i>
                                                </a>
                                                <a href="inventory_check.php?action=export&id=<?php echo $task['id']; ?>" 
                                                   class="btn btn-warning" title="导出数据">
                                                    <i class="glyphicon glyphicon-download-alt" style="font-size: 16px;"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (in_array($task['status'], ['created', 'in_progress'])): ?>
                                                <a href="inventory_check.php?action=cancel&id=<?php echo $task['id']; ?>" 
                                                   class="btn btn-danger" title="取消任务"
                                                   onclick="return confirm('确定要取消这个盘点任务吗？')">
                                                    <i class="glyphicon glyphicon-remove" style="font-size: 16px;"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <!-- Admin用户只能查看，不能操作 -->
                                            <button class="btn btn-default" disabled title="只读权限">
                                                <i class="glyphicon glyphicon-lock" style="font-size: 16px;"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (empty($tasks)): ?>
                <div class="text-center text-muted" style="padding: 50px;">
                    <i class="glyphicon glyphicon-inbox" style="font-size: 48px;"></i>
                    <h4>暂无盘点任务</h4>
                    <?php if ($isManager): ?>
                        <p>点击"创建盘点任务"开始您的第一次盘点</p>
                    <?php else: ?>
                        <p>当前还没有盘点任务记录</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* 增强操作按钮的样式 */
.btn-group-sm .btn {
    padding: 8px 10px;
    min-width: 36px;
}

.btn-group-sm .btn i {
    font-size: 16px;
    line-height: 1;
}

/* 按钮悬停效果 */
.btn-group .btn {
    transition: all 0.2s ease-in-out;
}

.btn-group .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* 确保操作列有足够宽度 */
td:last-child {
    min-width: 200px;
}
</style>

<script>
// 自动刷新进行中的任务
$(document).ready(function() {
    // 每30秒自动刷新页面（仅当有进行中任务时且为库管时）
    var hasInProgress = <?php echo $stats['in_progress_count'] > 0 && $isManager ? 'true' : 'false'; ?>;
    
    if (hasInProgress) {
        setTimeout(function() {
            location.reload();
        }, 30000);
    }
});
</script>

<?php include 'footer.php'; ?>