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
require_once '../ApiCommon.php';
require_once '../includes/inventory_check_auth.php';

// 创建API实例
$api = new ApiCommon();

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
        default:
            $api->response(400, '不支持的操作类型');
    }
    
} catch (Exception $e) {
    $api->response(500, '服务器错误', ['error' => $e->getMessage()]);
}

/**
 * 获取当前用户的盘点任务列表
 */
function handleListTasks() {
    global $api;
    
    if (!validateInventoryCheckAPI()) {
        return;
    }
    
    $user = $api->getCurrentUser();
    
    $baseId = $user['base_id'];
    
    $sql = "SELECT id, task_name, task_type, status, total_packages, checked_packages, 
                   difference_count, created_at, started_at, completed_at
            FROM inventory_check_tasks 
            WHERE base_id = ? AND status IN ('created', 'in_progress')
            ORDER BY created_at DESC";
    
    $tasks = fetchAll($sql, [$baseId]);
    
    foreach ($tasks as &$task) {
        $task['completion_rate'] = $task['total_packages'] > 0 
            ? round(($task['checked_packages'] / $task['total_packages']) * 100, 2) 
            : 0;
        $task['status_text'] = getTaskStatusText($task['status']);
        $task['task_type_text'] = getTaskTypeText($task['task_type']);
    }
    
    $api->response(200, '获取成功', $tasks);
}

/**
 * 获取单个任务详情
 */
function handleGetTask() {
    global $api;
    
    if (!validateInventoryCheckAPI()) {
        return;
    }
    
    $user = $api->getCurrentUser();
    
    $taskId = $_GET['task_id'] ?? 0;
    if (!$taskId) {
        $api->response(400, '缺少任务ID');
    }
    
    // 获取任务信息
    $task = fetchRow("SELECT * FROM inventory_check_tasks WHERE id = ? AND base_id = ?", [$taskId, $user['base_id']]);
    if (!$task) {
        $api->response(404, '任务不存在');
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
    
    $api->response(200, '获取成功', $result);
}

/**
 * 扫码处理单个包
 */
function handleScanPackage() {
    global $api;
    
    if (!validateInventoryCheckAPI()) {
        return;
    }
    
    $user = $api->getCurrentUser();
    
    $taskId = $_POST['task_id'] ?? 0;
    $packageCode = $_POST['package_code'] ?? '';
    $checkQuantity = $_POST['check_quantity'] ?? 0;
    
    if (!$taskId || !$packageCode || !$checkQuantity) {
        $api->response(400, '缺少必要参数');
    }
    
    // 验证任务权限
    $task = fetchRow("SELECT * FROM inventory_check_tasks WHERE id = ? AND base_id = ? AND status = 'in_progress'", 
                     [$taskId, $user['base_id']]);
    if (!$task) {
        $api->response(404, '任务不存在或未开始');
    }
    
    // 查找包信息
    $package = fetchRow("SELECT p.id, p.pieces, p.current_rack_id, g.short_name 
                        FROM glass_packages p 
                        JOIN glass_types g ON p.glass_type_id = g.id 
                        WHERE p.package_code = ? AND p.status = 'in_storage'", 
                        [$packageCode]);
    
    if (!$package) {
        $api->response(404, '包不存在或不在库存状态');
    }
    
    beginTransaction();
    
    try {
        // 检查是否已盘点
        $existing = fetchRow("SELECT id FROM inventory_check_cache WHERE task_id = ? AND package_code = ? AND check_quantity > 0", 
                           [$taskId, $packageCode]);
        
        if ($existing) {
            rollback();
            $api->response(400, '该包已盘点');
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
        $newCount = fetchColumn("SELECT COUNT(*) FROM inventory_check_cache WHERE task_id = ? AND check_quantity > 0", [$taskId]);
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
        
        $api->response(200, '盘点成功', $result);
        
    } catch (Exception $e) {
        rollback();
        $api->response(500, '盘点失败', ['error' => $e->getMessage()]);
    }
}

/**
 * 批量扫码处理
 */
function handleBatchScan() {
    global $api;
    
    if (!validateInventoryCheckAPI()) {
        return;
    }
    
    $user = $api->getCurrentUser();
    
    $taskId = $_POST['task_id'] ?? 0;
    $batchData = $_POST['batch_data'] ?? '';
    
    if (!$taskId || !$batchData) {
        $api->response(400, '缺少必要参数');
    }
    
    $data = json_decode($batchData, true);
    if (!$data || !is_array($data)) {
        $api->response(400, '批量数据格式错误');
    }
    
    // 验证任务权限
    $task = fetchRow("SELECT * FROM inventory_check_tasks WHERE id = ? AND base_id = ? AND status = 'in_progress'", 
                     [$taskId, $user['base_id']]);
    if (!$task) {
        $api->response(404, '任务不存在或未开始');
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
        $newCount = fetchColumn("SELECT COUNT(*) FROM inventory_check_cache WHERE task_id = ? AND check_quantity > 0", [$taskId]);
        update('inventory_check_tasks', ['checked_packages' => $newCount], 'id = ?', [$taskId]);
        
        commitTransaction();
        
        $result = [
            'success_count' => $successCount,
            'total_count' => count($data),
            'errors' => $errors,
            'checked_packages' => $newCount
        ];
        
        $api->response(200, '批量盘点完成', $result);
        
    } catch (Exception $e) {
        rollback();
        $api->response(500, '批量盘点失败', ['error' => $e->getMessage()]);
    }
}

/**
 * 同步盘点数据
 */
function handleSyncData() {
    global $api;
    
    if (!validateInventoryCheckAPI()) {
        return;
    }
    
    $user = $api->getCurrentUser();
    
    $taskId = $_GET['task_id'] ?? 0;
    $lastSync = $_GET['last_sync'] ?? '1970-01-01 00:00:00';
    
    if (!$taskId) {
        $api->response(400, '缺少任务ID');
    }
    
    // 验证任务权限
    $task = fetchRow("SELECT * FROM inventory_check_tasks WHERE id = ? AND base_id = ?", [$taskId, $user['base_id']]);
    if (!$task) {
        $api->response(404, '任务不存在');
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
    
    $api->response(200, '同步成功', $result);
}

/**
 * 获取包信息
 */
function handleGetPackageInfo() {
    global $api;
    
    if (!validateInventoryCheckAPI()) {
        return;
    }
    
    $user = $api->getCurrentUser();
    
    $packageCode = $_GET['package_code'] ?? '';
    if (!$packageCode) {
        $api->response(400, '缺少包号');
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
        $api->response(404, '包不存在或不在库存状态');
    }
    
    // 检查是否属于用户基地
    if ($package['base_name'] != $user['base_name']) {
        $api->response(403, '无权限访问此包');
    }
    
    $api->response(200, '获取成功', $package);
}

/**
 * 提交盘点数据（手动录入）
 */
function handleSubmitCheck() {
    global $api;
    
    if (!validateInventoryCheckAPI()) {
        return;
    }
    
    $user = $api->getCurrentUser();
    
    $taskId = $_POST['task_id'] ?? 0;
    $packageCode = $_POST['package_code'] ?? '';
    $checkQuantity = $_POST['check_quantity'] ?? 0;
    $notes = $_POST['notes'] ?? '';
    
    if (!$taskId || !$packageCode || !$checkQuantity) {
        $api->response(400, '缺少必要参数');
    }
    
    // 验证任务权限
    $task = fetchRow("SELECT * FROM inventory_check_tasks WHERE id = ? AND base_id = ? AND status = 'in_progress'", 
                     [$taskId, $user['base_id']]);
    if (!$task) {
        $api->response(404, '任务不存在或未开始');
    }
    
    // 查找包信息
    $package = fetchRow("SELECT p.id, p.pieces, p.current_rack_id 
                        FROM glass_packages p 
                        WHERE p.package_code = ? AND p.status = 'in_storage'", 
                        [$packageCode]);
    
    if (!$package) {
        $api->response(404, '包不存在或不在库存状态');
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
        $newCount = fetchColumn("SELECT COUNT(*) FROM inventory_check_cache WHERE task_id = ? AND check_quantity > 0", [$taskId]);
        update('inventory_check_tasks', ['checked_packages' => $newCount], 'id = ?', [$taskId]);
        
        commitTransaction();
        
        $result = [
            'package_code' => $packageCode,
            'system_quantity' => $package['pieces'],
            'check_quantity' => $checkQuantity,
            'difference' => $difference,
            'checked_packages' => $newCount
        ];
        
        $api->response(200, '提交成功', $result);
        
    } catch (Exception $e) {
        rollbackTransaction();
        $api->response(500, '提交失败', ['error' => $e->getMessage()]);
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

/**
 * 验证盘点API权限
 */
function validateInventoryCheckAPI() {
    global $api;
    
    // 验证用户登录
    $user = $api->getCurrentUser();
    if (!$user) {
        $api->response(401, '未登录');
        return false;
    }
    
    // 验证盘点权限
    if (!hasInventoryCheckPermission($user)) {
        $api->response(403, '无权限访问盘点功能');
        return false;
    }
    
    return true;
}

/**
 * 检查用户是否有盘点权限
 */
function hasInventoryCheckPermission($user) {
    // 管理员有只读权限，库管有全权限
    return in_array($user['role'], ['admin', 'manager']);
}
?>