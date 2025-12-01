<?php
require_once __DIR__ . '/auth.php';
require_once '../api/ApiCommon.php';
/**
 * 验证盘点功能查看权限
 * admin（只读）和 manager（完整权限）可以访问
 */
function requireInventoryCheckPermission() {
    requireLogin();
    
    $user = getCurrentUser();
    $allowedRoles = ['admin', 'manager'];
    
    if (!in_array($user['role'], $allowedRoles)) {
        // 如果是其他角色，显示权限不足页面
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>权限不足</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
            <style>
                .error-container {
                    text-align: center;
                    padding: 100px 20px;
                }
                .error-icon {
                    font-size: 80px;
                    color: #e74c3c;
                    margin-bottom: 30px;
                }
                .error-title {
                    font-size: 36px;
                    margin-bottom: 20px;
                    color: #2c3e50;
                }
                .error-message {
                    font-size: 18px;
                    color: #7f8c8d;
                    margin-bottom: 40px;
                    line-height: 1.6;
                }
                .btn-home {
                    font-size: 16px;
                    padding: 12px 30px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="error-container">
                    <div class="error-icon">
                        <i class="fa fa-lock"></i>
                    </div>
                    <h1 class="error-title">权限不足</h1>
                    <p class="error-message">
                        您当前的角色（<strong><?php echo getRoleDisplayName($user['role']); ?></strong>）没有权限访问库存盘点功能。<br>
                        库存盘点功能仅对<strong>系统管理员</strong>和<strong>库管</strong>角色开放。
                    </p>
                    <div class="action-buttons">
                        <a href="../index.php" class="btn btn-primary btn-home">
                            <i class="fa fa-home"></i> 返回首页
                        </a>
                        <a href="../admin/" class="btn btn-default btn-home">
                            <i class="fa fa-dashboard"></i> 管理后台
                        </a>
                    </div>
                    <hr style="margin: 50px 0;">
                    <div class="row">
                        <div class="col-md-8 col-md-offset-2">
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h4>权限说明</h4>
                                </div>
                                <div class="panel-body text-left">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>角色</th>
                                                <th>盘点权限</th>
                                                <th>说明</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><span class="label label-primary">系统管理员</span></td>
                                                <td><i class="fa fa-eye text-info"></i> 只读权限</td>
                                                <td>只能查看所有盘点任务，不能创建和修改</td>
                                            </tr>
                                            <tr>
                                                <td><span class="label label-info">库管</span></td>
                                                <td><i class="fa fa-check text-success"></i> 完全权限</td>
                                                <td>可以创建、管理自己基地的盘点任务</td>
                                            </tr>
                                            <tr>
                                                <td><span class="label label-warning">操作员</span></td>
                                                <td><i class="fa fa-times text-danger"></i> 无权限</td>
                                                <td>无法使用盘点功能</td>
                                            </tr>
                                            <tr>
                                                <td><span class="label label-default">查看者</span></td>
                                                <td><i class="fa fa-times text-danger"></i> 无权限</td>
                                                <td>无法使用盘点功能</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

/**
 * 验证盘点功能创建权限
 * 只有manager（库管）可以创建盘点任务
 */
function requireInventoryCheckCreatePermission() {
    requireInventoryCheckPermission();
    
    $user = getCurrentUser();
    
    if ($user['role'] !== 'manager') {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>创建权限不足</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
            <style>
                .error-container {
                    text-align: center;
                    padding: 100px 20px;
                }
                .error-icon {
                    font-size: 80px;
                    color: #f39c12;
                    margin-bottom: 30px;
                }
                .error-title {
                    font-size: 36px;
                    margin-bottom: 20px;
                    color: #2c3e50;
                }
                .error-message {
                    font-size: 18px;
                    color: #7f8c8d;
                    margin-bottom: 40px;
                    line-height: 1.6;
                }
                .btn-home {
                    font-size: 16px;
                    padding: 12px 30px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="error-container">
                    <div class="error-icon">
                        <i class="fa fa-exclamation-triangle"></i>
                    </div>
                    <h1 class="error-title">创建权限不足</h1>
                    <p class="error-message">
                        您当前的角色（<strong><?php echo getRoleDisplayName($user['role']); ?></strong>）没有权限创建盘点任务。<br>
                        只有<strong>库管</strong>角色才能创建和管理盘点任务。
                    </p>
                    <p><strong>系统管理员</strong>只能查看盘点任务，不能进行创建和修改操作。</p>
                    <div class="action-buttons">
                        <a href="index.php" class="btn btn-primary btn-home">
                            <i class="fa fa-list"></i> 查看盘点任务
                        </a>
                        <a href="../admin/" class="btn btn-default btn-home">
                            <i class="fa fa-arrow-left"></i> 返回后台
                        </a>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    // 检查是否有base_id（库管必须有基地）
    if (empty($user['base_id'])) {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>基地信息缺失</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
            <style>
                .error-container {
                    text-align: center;
                    padding: 100px 20px;
                }
                .error-icon {
                    font-size: 80px;
                    color: #e74c3c;
                    margin-bottom: 30px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="error-container">
                    <div class="error-icon">
                        <i class="fa fa-building"></i>
                    </div>
                    <h1>基地信息缺失</h1>
                    <p>您的账户没有分配基地，无法使用盘点功能。请联系系统管理员分配基地。</p>
                    <a href="../admin/" class="btn btn-primary">返回后台</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

/**
 * 验证基地权限
 * 确保用户只能访问自己基地的盘点任务
 */
function requireBasePermission($baseId) {
    requireInventoryCheckPermission();
    
    $user = getCurrentUser();
    
    // 管理员可以访问所有基地
    if ($user['role'] === 'admin') {
        return true;
    }
    
    // 库管只能访问自己的基地
    if ($user['role'] === 'manager' && $user['base_id'] == $baseId) {
        return true;
    }
    
    // 权限不足
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>基地权限不足</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
        <style>
            .error-container {
                text-align: center;
                padding: 100px 20px;
            }
            .error-icon {
                font-size: 80px;
                color: #e74c3c;
                margin-bottom: 30px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="error-container">
                <div class="error-icon">
                    <i class="fa fa-exclamation-triangle"></i>
                </div>
                <h1>基地权限不足</h1>
                <p>您只能管理自己基地的盘点任务。</p>
                <a href="inventory_check.php" class="btn btn-primary">返回盘点列表</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * 验证任务权限
 * 确保用户只能访问和操作自己基地的任务
 */
function requireTaskPermission($taskId, $action = 'view') {
    requireInventoryCheckPermission();
    
    $user = getCurrentUser();
    
    // 获取任务信息
    $task = fetchRow("SELECT * FROM inventory_check_tasks WHERE id = ?", [$taskId]);
    
    if (!$task) {
        http_response_code(404);
        ?>
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>任务不存在</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
        </head>
        <body>
            <div class="container text-center" style="padding: 100px 20px;">
                <h1><i class="fa fa-exclamation-triangle"></i> 任务不存在</h1>
                <p>指定的盘点任务不存在或已被删除。</p>
                <a href="index.php" class="btn btn-primary">返回首页</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    // 管理员可以访问所有任务
    if ($user['role'] === 'admin') {
        return $task;
    }
    
    // 库管只能访问自己基地的任务
    if ($user['role'] === 'manager' && $user['base_id'] == $task['base_id']) {
        
        // 对于修改操作，还需要检查任务状态
        if (in_array($action, ['start', 'cancel', 'complete'])) {
            switch ($action) {
                case 'start':
                    if ($task['status'] !== 'created') {
                        http_response_code(400);
                        ?>
                        <!DOCTYPE html>
                        <html lang="zh-CN">
                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <title>任务状态错误</title>
                            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
                        </head>
                        <body>
                            <div class="container text-center" style="padding: 100px 20px;">
                                <h1><i class="fa fa-exclamation-triangle"></i> 任务状态错误</h1>
                                <p>只有已创建的任务才能开始。</p>
                                <a href="inventory_check_view.php?id=<?php echo $taskId; ?>" class="btn btn-primary">返回任务</a>
                            </div>
                        </body>
                        </html>
                        <?php
                        exit;
                    }
                    break;
                    
                case 'cancel':
                    if (!in_array($task['status'], ['created', 'in_progress'])) {
                        http_response_code(400);
                        ?>
                        <!DOCTYPE html>
                        <html lang="zh-CN">
                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <title>任务状态错误</title>
                            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
                        </head>
                        <body>
                            <div class="container text-center" style="padding: 100px 20px;">
                                <h1><i class="fa fa-exclamation-triangle"></i> 任务状态错误</h1>
                                <p>只有创建或进行中的任务才能取消。</p>
                                <a href="inventory_check_view.php?id=<?php echo $taskId; ?>" class="btn btn-primary">返回任务</a>
                            </div>
                        </body>
                        </html>
                        <?php
                        exit;
                    }
                    break;
                    
                case 'complete':
                    if ($task['status'] !== 'in_progress') {
                        http_response_code(400);
                        ?>
                        <!DOCTYPE html>
                        <html lang="zh-CN">
                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <title>任务状态错误</title>
                            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
                        </head>
                        <body>
                            <div class="container text-center" style="padding: 100px 20px;">
                                <h1><i class="fa fa-exclamation-triangle"></i> 任务状态错误</h1>
                                <p>只有进行中的任务才能完成。</p>
                                <a href="inventory_check_view.php?id=<?php echo $taskId; ?>" class="btn btn-primary">返回任务</a>
                            </div>
                        </body>
                        </html>
                        <?php
                        exit;
                    }
                    break;
            }
        }
        
        return $task;
    }
    
    // 权限不足
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>权限不足</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
    </head>
    <body>
        <div class="container text-center" style="padding: 100px 20px;">
            <h1><i class="fa fa-lock"></i> 权限不足</h1>
            <p>您无权访问此盘点任务。</p>
            <a href="index.php" class="btn btn-primary">返回首页</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * 验证API请求权限
 */
function validateInventoryCheckAPI() {

    $user = ApiCommon::authenticate();
    if (!$user) {
        ApiCommon::sendResponse(401, '未认证');
        return false;
    }
    
    $allowedRoles = ['admin', 'manager'];
    if (!in_array($user['role'], $allowedRoles)) {
        ApiCommon::sendResponse(403, '权限不足');
        return false;
    }
    
    return true;
}

/**
 * 获取角色显示名称
 */
function getRoleDisplayName($role) {
    $roleNames = [
        'admin' => '系统管理员',
        'manager' => '库管',
        'operator' => '操作员',
        'viewer' => '查看者'
    ];
    return $roleNames[$role] ?? $role;
}
?>