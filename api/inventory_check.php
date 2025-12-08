<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/db.php';
require_once '../api/ApiCommon.php';
require_once '../includes/inventory_check_auth.php';

// 确保会话启动
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 设置API响应头
ApiCommon::setHeaders();
ApiCommon::handlePreflight();

// 处理请求
try {
    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            handleListTasks();
            break;
        case 'get':
            handleGetTask();
            break;
        case 'scan':
            handleScanPackage();
            break;
        case 'batch_scan':
            handleBatchScan();
            break;
        case 'sync':
            handleSyncData();
            break;
        case 'get_package_info':
            handleGetPackageInfo();
            break;
        case 'submit_check':
            handleSubmitCheck();
            break;
        case 'get_rollback_count':
            handleGetRollbackCount();
            break;
        default:
            ApiCommon::sendResponse(400, '不支持的操作类型');
    }
    
} catch (Exception $e) {
    ApiCommon::sendResponse(500, '服务器错误', ['error' => $e->getMessage()]);
}

/**
 * 获取当前用户的盘点任务列表
 */
function handleListTasks() {
    $user = ApiCommon::authenticate();
    if (!$user || !validateInventoryCheckPermissions($user)) {
        return;
    }
    
    $baseId = $user['base_id'];
    
    $sql = "SELECT id, task_name, task_type, status, total_packages, checked_packages, 
                   difference_count, created_at, started_at, completed_at
            FROM inventory_check_tasks 
            WHERE base_id = ? AND status IN ('created', 'in_progress')
            ORDER BY created_at DESC";
    
    $tasks = fetchAll($sql, [$baseId]);
    var_dump($tasks);
    foreach ($tasks as &$task) {
        $task['completion_rate'] = $task['total_packages'] > 0 
            ? round(($task['checked_packages'] / $task['total_packages']) * 100, 2) 
            : 0;
        $task['status_text'] = getTaskStatusText($task['status']);
        $task['task_type_text'] = getTaskTypeText($task['task_type']);
    }
    
    ApiCommon::sendResponse(200, '获取成功', $tasks);
}

/**
 * 获取单个任务详情
 */
function handleGetTask() {
    $user = ApiCommon::authenticate();
    if (!$user || !validateInventoryCheckPermissions($user)) {
        return;
    }
    
    $taskId = $_GET['task_id'] ?? 0;
    if (!$taskId) {
        ApiCommon::sendResponse(400, '缺少任务ID');
    }
    
    // 获取任务信息
    $task = fetchRow("SELECT * FROM inventory_check_tasks WHERE id = ? AND base_id = ?", [$taskId, $user['base_id']]);
    if (!$task) {
        ApiCommon::sendResponse(404, '任务不存在');
    }
    
    // 获取待盘点包列表
    $sql = "SELECT c.id as cache_id, c.package_code, c.package_id, c.system_quantity, 
                   c.check_quantity, c.check_time, p.glass_type_id, g.short_name as glass_name
            FROM inventory_check_cache c
            JOIN glass_packages p ON c.package_id = p.id
            JOIN glass_types g ON p.glass_type_id = g.id
            WHERE c.task_id = ? AND c.check_quantity = 0
            ORDER BY c.package_code";
    
    $packages = fetchAll($sql, [$taskId]);
    
    $result = [
        'task' => $task,
        'packages' => $packages,
        'total_packages' => count($packages),
        'checked_packages' => $task['checked_packages'],
        'completion_rate' => $task['total_packages'] > 0 
            ? round(($task['checked_packages'] / $task['total_packages']) * 100, 2) 
            : 0
    ];
    
    ApiCommon::sendResponse(200, '获取成功', $result);
}

/**
 * 扫码处理单个包
 */
function handleScanPackage() {
    $user = ApiCommon::authenticate();
    if (!$user || !validateInventoryCheckPermissions($user)) {
        return;
    }
    
    $taskId = $_POST['task_id'] ?? 0;
    $packageCode = $_POST['package_code'] ?? '';
    $checkQuantity = $_POST['check_quantity'] ?? 0;
    
    if (!$taskId || !$packageCode || !$checkQuantity) {
        ApiCommon::sendResponse(400, '缺少必要参数');
    }
    
    // 验证任务权限
    $task = fetchRow("SELECT * FROM inventory_check_tasks WHERE id = ? AND base_id = ? AND status = 'in_progress'", 
                     [$taskId, $user['base_id']]);
    if (!$task) {
        ApiCommon::sendResponse(404, '任务不存在或未开始');
    }
    
    // 查找包信息
    $package = fetchRow("SELECT p.id, p.pieces, p.current_rack_id, g.short_name 
                        FROM glass_packages p 
                        JOIN glass_types g ON p.glass_type_id = g.id 
                        WHERE p.package_code = ? AND p.status = 'in_storage'", 
                        [$packageCode]);
    
    if (!$package) {
        ApiCommon::sendResponse(404, '包不存在或不在库存状态');
    }
    
    beginTransaction();
    
    try {
        // 检查是否已盘点
        $existing = fetchRow("SELECT id FROM inventory_check_cache WHERE task_id = ? AND package_code = ? AND check_quantity > 0", 
                           [$taskId, $packageCode]);
        
        if ($existing) {
            rollbackTransaction();
            ApiCommon::sendResponse(400, '该包已盘点');
        }
        
        // 更新盘点缓存
        $difference = $checkQuantity - $package['pieces'];
        
        $updateData = [
            'check_quantity' => $checkQuantity,
            'difference' => $difference,
            'rack_id' => $package['current_rack_id'],
            'check_method' => 'pda_scan',
            'check_time' => date('Y-m-d H:i:s'),
            'operator_id' => $user['id']
        ];
        
        update('inventory_check_cache', $updateData, 'task_id = ? AND package_code = ?', [$taskId, $packageCode]);
        
        // 更新任务进度
        $newCount = fetchOne("SELECT COUNT(*) FROM inventory_check_cache WHERE task_id = ? AND check_quantity > 0", [$taskId]);
        update('inventory_check_tasks', ['checked_packages' => $newCount], 'id = ?', [$taskId]);
        
        commitTransaction();
        
        $result = [
            'package_code' => $packageCode,
            'glass_name' => $package['short_name'],
            'system_quantity' => $package['pieces'],
            'check_quantity' => $checkQuantity,
            'difference' => $difference,
            'checked_packages' => $newCount
        ];
        
        ApiCommon::sendResponse(200, '盘点成功', $result);
        
    } catch (Exception $e) {
        rollbackTransaction();
        ApiCommon::sendResponse(500, '盘点失败', ['error' => $e->getMessage()]);
    }
}

/**
 * 批量扫码处理
 */
function handleBatchScan() {
    $user = ApiCommon::authenticate();
    if (!$user || !validateInventoryCheckPermissions($user)) {
        return;
    }
    
    $taskId = $_POST['task_id'] ?? 0;
    $batchData = $_POST['batch_data'] ?? '';
    
    if (!$taskId || !$batchData) {
        ApiCommon::sendResponse(400, '缺少必要参数');
    }
    
    $data = json_decode($batchData, true);
    if (!$data || !is_array($data)) {
        ApiCommon::sendResponse(400, '批量数据格式错误');
    }
    
    // 验证任务权限
    $task = fetchRow("SELECT * FROM inventory_check_tasks WHERE id = ? AND base_id = ? AND status = 'in_progress'", 
                     [$taskId, $user['base_id']]);
    if (!$task) {
        ApiCommon::sendResponse(404, '任务不存在或未开始');
    }
    
    $successCount = 0;
    $errors = [];
    
    beginTransaction();
    
    try {
        foreach ($data as $item) {
            $packageCode = $item['package_code'] ?? '';
            $checkQuantity = $item['check_quantity'] ?? 0;
            
            if (!$packageCode || !$checkQuantity) {
                $errors[] = "包 {$packageCode} 数据不完整";
                continue;
            }
            
            // 查找包信息
            $package = fetchRow("SELECT p.id, p.pieces, p.current_rack_id 
                                FROM glass_packages p 
                                WHERE p.package_code = ? AND p.status = 'in_storage'", 
                                [$packageCode]);
            
            if (!$package) {
                $errors[] = "包 {$packageCode} 不存在或不在库存状态";
                continue;
            }
            
            // 检查是否已盘点
            $existing = fetchRow("SELECT id FROM inventory_check_cache WHERE task_id = ? AND package_code = ? AND check_quantity > 0", 
                               [$taskId, $packageCode]);
            
            if ($existing) {
                $errors[] = "包 {$packageCode} 已盘点";
                continue;
            }
            
            // 更新盘点缓存
            $difference = $checkQuantity - $package['pieces'];
            
            $updateData = [
                'check_quantity' => $checkQuantity,
                'difference' => $difference,
                'rack_id' => $package['current_rack_id'],
                'check_method' => 'pda_scan',
                'check_time' => date('Y-m-d H:i:s'),
                'operator_id' => $user['id']
            ];
            
            update('inventory_check_cache', $updateData, 'task_id = ? AND package_code = ?', [$taskId, $packageCode]);
            $successCount++;
        }
        
        // 更新任务进度
        $newCount = fetchOne("SELECT COUNT(*) FROM inventory_check_cache WHERE task_id = ? AND check_quantity > 0", [$taskId]);
        update('inventory_check_tasks', ['checked_packages' => $newCount], 'id = ?', [$taskId]);
        
        commitTransaction();
        
        $result = [
            'success_count' => $successCount,
            'total_count' => count($data),
            'errors' => $errors,
            'checked_packages' => $newCount
        ];
        
        ApiCommon::sendResponse(200, '批量盘点完成', $result);
        
    } catch (Exception $e) {
        rollbackTransaction();
        ApiCommon::sendResponse(500, '批量盘点失败', ['error' => $e->getMessage()]);
    }
}

/**
 * 同步盘点数据
 */
function handleSyncData() {
    $user = ApiCommon::authenticate();
    if (!$user || !validateInventoryCheckPermissions($user)) {
        return;
    }
    
    $taskId = $_GET['task_id'] ?? 0;
    $lastSync = $_GET['last_sync'] ?? '1970-01-01 00:00:00';
    
    if (!$taskId) {
        ApiCommon::sendResponse(400, '缺少任务ID');
    }
    
    // 验证任务权限
    $task = fetchRow("SELECT * FROM inventory_check_tasks WHERE id = ? AND base_id = ?", [$taskId, $user['base_id']]);
    if (!$task) {
        ApiCommon::sendResponse(404, '任务不存在');
    }
    
    // 获取更新的数据
    $sql = "SELECT c.package_code, c.check_quantity, c.system_quantity, c.difference,
                   c.check_time, u.real_name as operator_name,
                   g.short_name as glass_name
            FROM inventory_check_cache c
            JOIN users u ON c.operator_id = u.id
            JOIN glass_packages p ON c.package_id = p.id
            JOIN glass_types g ON p.glass_type_id = g.id
            WHERE c.task_id = ? AND c.check_quantity > 0 AND c.check_time > ?
            ORDER BY c.check_time";
    
    $data = fetchAll($sql, [$taskId, $lastSync]);
    
    // 获取任务统计
    $stats = fetchRow("SELECT checked_packages, total_packages FROM inventory_check_tasks WHERE id = ?", [$taskId]);
    
    $result = [
        'data' => $data,
        'stats' => $stats,
        'completion_rate' => $stats['total_packages'] > 0 
            ? round(($stats['checked_packages'] / $stats['total_packages']) * 100, 2) 
            : 0
    ];
    
    ApiCommon::sendResponse(200, '同步成功', $result);
}

/**
 * 获取包信息
 */
function handleGetPackageInfo() {
    $user = ApiCommon::authenticate();
    if (!$user || !validateInventoryCheckPermissions($user)) {
        return;
    }
    
    $packageCode = $_GET['package_code'] ?? '';
    if (!$packageCode) {
        ApiCommon::sendResponse(400, '缺少包号');
    }
    
    $sql = "SELECT p.id, p.package_code, p.pieces, p.width, p.height, p.entry_date,
                   g.short_name as glass_name, g.thickness, g.brand, g.color,
                   r.code as rack_code, b.name as base_name
            FROM glass_packages p
            JOIN glass_types g ON p.glass_type_id = g.id
            JOIN storage_racks r ON p.current_rack_id = r.id
            JOIN bases b ON r.base_id = b.id
            WHERE p.package_code = ? AND p.status = 'in_storage'";
    
    $package = fetchRow($sql, [$packageCode]);
    
    if (!$package) {
        ApiCommon::sendResponse(404, '包不存在或不在库存状态');
    }
    
    // 检查是否属于用户基地
    if ($package['base_name'] != $user['base_name']) {
        ApiCommon::sendResponse(403, '无权限访问此包');
    }
    
    ApiCommon::sendResponse(200, '获取成功', $package);
}

/**
 * 提交盘点数据（手动录入）
 */
function handleSubmitCheck() {
    $user = ApiCommon::authenticate();
    if (!$user || !validateInventoryCheckPermissions($user)) {
        return;
    }
    
    $taskId = $_POST['task_id'] ?? 0;
    $packageCode = $_POST['package_code'] ?? '';
    $checkQuantity = $_POST['check_quantity'] ?? 0;
    $notes = $_POST['notes'] ?? '';
    
    if (!$taskId || !$packageCode || !$checkQuantity) {
        ApiCommon::sendResponse(400, '缺少必要参数');
    }
    
    // 验证任务权限
    $task = fetchRow("SELECT * FROM inventory_check_tasks WHERE id = ? AND base_id = ? AND status = 'in_progress'", 
                     [$taskId, $user['base_id']]);
    if (!$task) {
        ApiCommon::sendResponse(404, '任务不存在或未开始');
    }
    
    // 查找包信息
    $package = fetchRow("SELECT p.id, p.pieces, p.current_rack_id 
                        FROM glass_packages p 
                        WHERE p.package_code = ? AND p.status = 'in_storage'", 
                        [$packageCode]);
    
    if (!$package) {
        ApiCommon::sendResponse(404, '包不存在或不在库存状态');
    }
    
    beginTransaction();
    
    try {
        // 更新盘点缓存
        $difference = $checkQuantity - $package['pieces'];
        
        $updateData = [
            'check_quantity' => $checkQuantity,
            'difference' => $difference,
            'rack_id' => $package['current_rack_id'],
            'check_method' => 'manual_input',
            'check_time' => date('Y-m-d H:i:s'),
            'operator_id' => $user['id'],
            'notes' => $notes
        ];
        
        update('inventory_check_cache', $updateData, 'task_id = ? AND package_code = ?', [$taskId, $packageCode]);
        
        // 更新任务进度
        $newCount = fetchOne("SELECT COUNT(*) FROM inventory_check_cache WHERE task_id = ? AND check_quantity > 0", [$taskId]);
        update('inventory_check_tasks', ['checked_packages' => $newCount], 'id = ?', [$taskId]);
        
        commitTransaction();
        
        $result = [
            'package_code' => $packageCode,
            'system_quantity' => $package['pieces'],
            'check_quantity' => $checkQuantity,
            'difference' => $difference,
            'checked_packages' => $newCount
        ];
        
        ApiCommon::sendResponse(200, '提交成功', $result);
        
    } catch (Exception $e) {
        rollbackTransaction();
        ApiCommon::sendResponse(500, '提交失败', ['error' => $e->getMessage()]);
    }
}

/**
 * 获取任务状态文本
 */
function getTaskStatusText($status) {
    $map = [
        'created' => '已创建',
        'in_progress' => '进行中',
        'completed' => '已完成',
        'cancelled' => '已取消'
    ];
    return $map[$status] ?? $status;
}

/**
 * 获取回滚记录数量
 */
function handleGetRollbackCount() {
    $user = ApiCommon::authenticate();
    if (!$user || !validateInventoryCheckPermissions($user)) {
        return;
    }
    
    $taskId = $_POST['task_id'] ?? 0;
    
    if (!$taskId) {
        ApiCommon::sendResponse(400, '缺少任务ID');
    }
    
    // 验证任务存在且属于用户基地
    $task = fetchRow("SELECT * FROM inventory_check_tasks WHERE id = ? AND base_id = ?", [$taskId, $user['base_id']]);
    if (!$task) {
        ApiCommon::sendResponse(404, '任务不存在');
    }
    
    // 获取盘点期间的回滚记录数量
    $count = fetchOne("
        SELECT COUNT(*) FROM inventory_check_cache 
        WHERE task_id = ? AND check_method = 'auto_rollback'
    ", [$taskId]);
    
    ApiCommon::sendResponse(200, '获取成功', [
        'count' => intval($count),
        'task_id' => $taskId
    ]);
}

/**
 * 获取盘点类型文本
 */
function getTaskTypeText($type) {
    $map = [
        'full' => '全盘',
        'partial' => '部分盘点',
        'random' => '抽盘'
    ];
    return $map[$type] ?? $type;
}

function validateInventoryCheckPermissions($user) {
    // 验证盘点权限
    $allowedRoles = ['admin', 'manager'];
    if (!in_array($user['role'], $allowedRoles)) {
        ApiCommon::sendResponse(403, '无权限访问盘点功能');
        return false;
    }
    return true;
}

?>