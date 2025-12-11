<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/inventory_check_auth.php';
require_once '../includes/inventory_operations.php';

// 检查权限：只有库管和管理员可以使用盘点功能
requireInventoryCheckPermission();

$pageTitle = '库存盘点管理';
$user = getCurrentUser();
$baseId = $user['base_id'];

$action = $_GET['action'] ?? $_POST['action'] ?? 'redirect';

// 处理各种操作
switch ($action) {
    case 'create':
        handleCreateTask();
        break;
    case 'start':
        handleStartTask();
        break;
    case 'complete':
        handleCompleteTask();
        break;
    case 'cancel':
        handleCancelTask();
        break;
    case 'view':
        showTaskDetails();
        break;
    case 'report':
        showTaskReport();
        break;
    case 'export':
        exportTaskData();
        break;
    case 'save_manual':
        handleManualInput();
        break;
    case 'get_package_info':
        handleGetPackageInfo();
        break;
    case 'get_rack_options':
        handleGetRackOptions();
        break;
    case 'redirect':
    default:
        // 默认重定向到列表页面，因为列表页面已经是完整的了
        redirect('inventory_check_list.php');
}

/**
 * 处理创建任务
 */
function handleCreateTask() {
    // 验证创建权限
    requireInventoryCheckCreatePermission();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $taskName = $_POST['task_name'];
        $baseId = $_POST['base_id'];
        $taskType = $_POST['task_type'];
        $description = $_POST['description'] ?? '';
        $createdBy = getCurrentUserId();
        
        // 开始事务
        beginTransaction();
        
        try {
            // 创建盘点任务
            $taskData = [
                'task_name' => $taskName,
                'base_id' => $baseId,
                'task_type' => $taskType,
                'description' => $description,
                'created_by' => $createdBy,
                'status' => 'created'
            ];
            
            $taskId = insert('inventory_check_tasks', $taskData);
            
            // 根据盘点类型添加需要盘点的包
            $packages = getCheckPackages($baseId, $taskType);
            
            foreach ($packages as $package) {
                $cacheData = [
                    'task_id' => $taskId,
                    'package_code' => $package['package_code'],
                    'package_id' => $package['id'],
                    'system_quantity' => $package['pieces'],
                    'check_quantity' => 0,
                    'difference' => 0,
                    'check_method' => 'manual_input'
                ];
                insert('inventory_check_cache', $cacheData);
            }
            
            // 更新任务包总数
            $updateData = ['total_packages' => count($packages)];
            update('inventory_check_tasks', $updateData, 'id = ?', [$taskId]);
            
            commitTransaction();
            
            redirect("inventory_check.php?action=view&id=$taskId&success=task_created");
            
        } catch (Exception $e) {
            rollbackTransaction();
            $error = "创建任务失败：" . $e->getMessage();
            include 'inventory_check_create.php';
        }
        
    } else {
        // 验证创建权限
        requireInventoryCheckCreatePermission();
        include 'inventory_check_create.php';
    }
}

/**
 * 开始盘点任务
 */
function handleStartTask() {
    $taskId = $_GET['id'];
    $userId = getCurrentUserId();
    
    // 验证任务权限和状态
    $task = requireTaskPermission($taskId, 'start');
    
    $updateData = [
        'status' => 'in_progress',
        'started_at' => date('Y-m-d H:i:s')
    ];
    
    // 管理员可以更新任何任务，库管只能更新自己创建的任务
    $whereClause = "id = ?";
    $params = [$taskId];
    if ($userId) {
        $whereClause .= " AND created_by = ?";
        $params[] = $userId;
    }
    
    update('inventory_check_tasks', $updateData, $whereClause, $params);
    
    redirect("inventory_check.php?action=view&id=$taskId&success=task_started");
}

/**
 * 完成盘点任务
 */
function handleCompleteTask() {
    $taskId = $_GET['id'];
    $userId = getCurrentUserId();
    
    // 验证任务权限和状态
    $task = requireTaskPermission($taskId, 'complete');
    
    beginTransaction();
    
    try {
        // 第一步：处理盘点期间的出库回滚
        $rollbackCount = handleCheckPeriodRollback($taskId);
        
        // 第二步：计算汇总数据
        $summary = calculateTaskSummary($taskId);
        
        // 第三步：更新任务状态
        $updateData = [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
            'checked_packages' => $summary['checked_packages'],
            'difference_count' => $summary['difference_count']
        ];
        
        // 管理员可以更新任何任务，库管只能更新自己创建的任务
        $whereClause = "id = ?";
        $params = [$taskId];
        if ($userId) {
            $whereClause .= " AND created_by = ?";
            $params[] = $userId;
        }
        
        update('inventory_check_tasks', $updateData, $whereClause, $params);
        
        // 第四步：生成盘点结果汇总
        generateTaskResults($taskId);
        
        // 第五步：可选：自动生成库存流转记录
        $autoAdjust = $_POST['auto_adjust'] ?? '0';
        $completeNotes = $_POST['complete_notes'] ?? '';
        
        if ($autoAdjust === '1') {
            generateInventoryTransactions($taskId);
        }
        
        // 第六步：保存完成备注
        $finalNotes = $completeNotes;
        if ($rollbackCount > 0) {
            $finalNotes .= ($finalNotes ? "\n" : '') . "已自动处理{$rollbackCount}个盘点期间的出库记录";
        }
        
        if ($finalNotes) {
            update('inventory_check_tasks', 
                   ['description' => "完成备注: " . $finalNotes], 
                   'id = ?', 
                   [$taskId]);
        }
        
        commitTransaction();
        
        redirect("inventory_check.php?action=report&id=$taskId&success=task_completed");
        
    } catch (Exception $e) {
        rollbackTransaction();
        $error = "完成任务失败：" . $e->getMessage();
        showTaskDetails();
    }
}

/**
 * 取消盘点任务
 */
function handleCancelTask() {
    $taskId = $_GET['id'];
    $userId = getCurrentUserId();
    
    // 验证任务权限和状态
    $task = requireTaskPermission($taskId, 'cancel');
    
    $updateData = ['status' => 'cancelled'];
    // 管理员可以更新任何任务，库管只能更新自己创建的任务
    $whereClause = "id = ?";
    $params = [$taskId];
    if ($userId) {
        $whereClause .= " AND created_by = ?";
        $params[] = $userId;
    }
    
    update('inventory_check_tasks', $updateData, $whereClause, $params);
    
    redirect("inventory_check.php?action=list&success=task_cancelled");
}

/**
 * 显示任务详情
 */
function showTaskDetails() {
    $taskId = $_GET['id'];
    
    // 验证任务权限
    requireTaskPermission($taskId, 'view');
    
    // 获取完整的任务信息，包括基地名称和创建人姓名
    $sql = "SELECT t.*, b.name AS base_name, u.real_name AS created_by_name
            FROM inventory_check_tasks t
            LEFT JOIN bases b ON t.base_id = b.id
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.id = ?";
    $task = fetchRow($sql, [$taskId]);
    
    if (!$task) {
        redirect('inventory_check_list.php?error=task_not_found');
        return;
    }
    
    // 计算完成率
    $task['completion_rate'] = 0;
    if ($task['total_packages'] > 0) {
        $task['completion_rate'] = round(($task['checked_packages'] * 100.0 / $task['total_packages']), 2);
    }
    
    // 计算持续时间
    $task['duration'] = null;
    if ($task['started_at']) {
        if ($task['status'] == 'completed' && $task['completed_at']) {
            $startTime = new DateTime($task['started_at']);
            $endTime = new DateTime($task['completed_at']);
            $task['duration'] = $endTime->diff($startTime)->format('%H:%I:%S');
        } elseif ($task['status'] == 'in_progress') {
            $startTime = new DateTime($task['started_at']);
            $now = new DateTime();
            $task['duration'] = $now->diff($startTime)->format('%H:%I:%S');
        }
    }
    
    // 获取盘点明细
    $sql = "SELECT c.*, g.name AS glass_name, 
                   r.code AS rack_code, r_current.code AS current_rack_code,
                   u.real_name AS operator_name
            FROM inventory_check_cache c
            LEFT JOIN glass_packages p ON c.package_id = p.id
            LEFT JOIN glass_types g ON p.glass_type_id = g.id
            LEFT JOIN storage_racks r ON c.rack_id = r.id
            LEFT JOIN storage_racks r_current ON p.current_rack_id = r_current.id
            LEFT JOIN users u ON c.operator_id = u.id
            WHERE c.task_id = ?
            ORDER BY c.check_time DESC";
    
    $details = fetchAll($sql, [$taskId]);
    
    include 'inventory_check_view.php';
}

/**
 * 显示盘点报告
 */
function showTaskReport() {
    $taskId = $_GET['id'];
    
    // 验证任务权限
    requireTaskPermission($taskId, 'view');
    
    // 获取完整的任务信息，包括基地名称和创建人姓名
    $sql = "SELECT t.*, b.name AS base_name, u.real_name AS created_by_name
            FROM inventory_check_tasks t
            LEFT JOIN bases b ON t.base_id = b.id
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.id = ?";
    $task = fetchRow($sql, [$taskId]);
    
    if (!$task) {
        redirect('inventory_check_list.php?error=task_not_found');
        return;
    }
    
    // 计算完成率
    $task['completion_rate'] = 0;
    if ($task['total_packages'] > 0) {
        $task['completion_rate'] = round(($task['checked_packages'] * 100.0 / $task['total_packages']), 2);
    }
    
    // 计算持续时间
    $task['duration'] = null;
    if ($task['started_at']) {
        if ($task['status'] == 'completed' && $task['completed_at']) {
            $startTime = new DateTime($task['started_at']);
            $endTime = new DateTime($task['completed_at']);
            $task['duration'] = $endTime->diff($startTime)->format('%H:%I:%S');
        } elseif ($task['status'] == 'in_progress') {
            $startTime = new DateTime($task['started_at']);
            $now = new DateTime();
            $task['duration'] = $now->diff($startTime)->format('%H:%I:%S');
        }
    }
    
    // 获取差异明细
    $differences = fetchAll("SELECT * FROM inventory_check_difference_details WHERE task_id = ?", [$taskId]);
    
    // 获取汇总统计
    $summary = fetchRow("SELECT * FROM inventory_check_results WHERE task_id = ?", [$taskId]);
    
    include 'inventory_check_report.php';
}

/**
 * 处理手动录入盘点数据
 */
function handleManualInput() {
    $taskId = $_POST['task_id'] ?? 0;
    $packageCodes = $_POST['package_code'] ?? [];
    $checkQuantities = $_POST['check_quantity'] ?? [];
    $rackIds = $_POST['rack_id'] ?? [];
    $syncRacks = $_POST['sync_rack'] ?? [];
    $notes = $_POST['notes'] ?? [];
    
    // 验证任务权限
    requireTaskPermission($taskId, 'edit');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($packageCodes)) {
        redirect("inventory_check.php?action=view&id=$taskId&error=no_data");
        return;
    }
    
    $userId = getCurrentUserId();
    $currentTime = date('Y-m-d H:i:s');
    $updatedCount = 0;
    $rackUpdateCount = 0;
    
    beginTransaction();
    
    try {
        foreach ($packageCodes as $index => $packageCode) {
            $checkQuantity = intval($checkQuantities[$index] ?? 0);
            $rackId = intval($rackIds[$index] ?? 0);
            $syncRack = ($syncRacks[$index] ?? 0) == 1;
            $note = $notes[$index] ?? '';
            
            // 获取系统数量
            $systemQty = fetchRow("SELECT system_quantity FROM inventory_check_cache 
                                  WHERE task_id = ? AND package_code = ?", [$taskId, $packageCode]);
            
            if ($systemQty) {
                $difference = $checkQuantity - $systemQty['system_quantity'];
                
                // 更新盘点数据
                $updateData = [
                    'check_quantity' => $checkQuantity,
                    'difference' => $difference,
                    'rack_id' => $rackId ?: null,
                    'check_method' => 'manual_input',
                    'check_time' => $currentTime,
                    'operator_id' => $userId,
                    'notes' => $note
                ];
                
                if ($rackId && $syncRack) {
                    // 如果选择了同步库位，在备注中记录
                    $rackInfo = fetchRow("SELECT code FROM storage_racks WHERE id = ?", [$rackId]);
                    $updateData['notes'] = ($note ? $note . "\n" : '') . "盘点时同步更新库位到：" . $rackInfo['code'];
                    
                    // 同步更新包的实际库位
                    $packageUpdate = update('glass_packages', 
                        ['current_rack_id' => $rackId], 
                        'package_code = ?', 
                        [$packageCode]);
                    
                    if ($packageUpdate > 0) {
                        $rackUpdateCount++;
                    }
                }
                
                update('inventory_check_cache', $updateData, 
                       'task_id = ? AND package_code = ?', [$taskId, $packageCode]);
                
                $updatedCount++;
            }
        }
        
        // 更新任务已盘包数量
        $summary = calculateTaskSummary($taskId);
        $updateData = [
            'checked_packages' => $summary['checked_packages']
        ];
        update('inventory_check_tasks', $updateData, 'id = ?', [$taskId]);
        
        commitTransaction();
        
        $successMsg = "manual_saved&count=$updatedCount";
        if ($rackUpdateCount > 0) {
            $successMsg .= "&rack_updates=$rackUpdateCount";
        }
        
        redirect("inventory_check.php?action=view&id=$taskId&success=$successMsg");
        
    } catch (Exception $e) {
        rollbackTransaction();
        redirect("inventory_check.php?action=view&id=$taskId&error=save_failed&msg=" . urlencode($e->getMessage()));
    }
}

/**
 * 导出任务数据
 */
function exportTaskData() {
    $taskId = $_GET['id'];
    
    // 验证任务权限
    $task = requireTaskPermission($taskId, 'view');
    
    // 设置响应头
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory_check_'.$taskId.'.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    // CSV 头部
    fputcsv($output, ['包号', '原片名称', '系统数量', '盘点数量', '差异', '盘点方式', '操作员', '盘点时间']);
    
    // 数据
    $sql = "SELECT c.package_code, g.short_name, c.system_quantity, c.check_quantity, 
                   CASE WHEN c.difference > 0 THEN CONCAT('+', c.difference) ELSE CAST(c.difference AS CHAR) END AS diff_display,
                   CASE 
                       WHEN c.check_method = 'pda_scan' THEN 'PDA扫码'
                       WHEN c.check_method = 'manual_input' THEN '手动录入'
                       WHEN c.check_method = 'excel_import' THEN 'Excel导入'
                   END AS method_name,
                   u.real_name, c.check_time
            FROM inventory_check_cache c
            LEFT JOIN glass_packages p ON c.package_id = p.id
            LEFT JOIN glass_types g ON p.glass_type_id = g.id
            LEFT JOIN users u ON c.operator_id = u.id
            WHERE c.task_id = ?
            ORDER BY c.check_time";
    
    $rows = fetchAll($sql, [$taskId]);
    
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['package_code'],
            $row['short_name'],
            $row['system_quantity'],
            $row['check_quantity'],
            $row['diff_display'],
            $row['method_name'],
            $row['real_name'],
            $row['check_time']
        ]);
    }
    
    fclose($output);
    exit;
}


/**
 * 根据盘点类型获取需要盘量的包列表
 */
function getCheckPackages($baseId, $taskType) {
    $sql = "SELECT p.id, p.package_code, p.pieces, p.glass_type_id, p.current_rack_id
            FROM glass_packages p
            LEFT JOIN storage_racks r ON p.current_rack_id = r.id
            WHERE p.status = 'in_storage' AND r.base_id = ?";
    
    $params = [$baseId];
    
    // 根据盘点类型调整查询
    switch ($taskType) {
        case 'full':
            // 全盘：获取该基地所有库存中的包
            break;
        case 'partial':
            // 部分盘点：可以添加其他筛选条件，比如按日期、按区域等
            // 目前默认为全盘逻辑
            break;
        case 'random':
            // 抽盘：随机选择30%的包
            $sql .= " ORDER BY RAND()";
            break;
    }
    
    return fetchAll($sql, $params);
}

/**
 * 计算任务汇总数据
 */
function calculateTaskSummary($taskId) {
    $summary = [
        'checked_packages' => 0,
        'difference_count' => 0
    ];
    
    // 获取已盘包数量
    $sql = "SELECT COUNT(*) as count 
            FROM inventory_check_cache 
            WHERE task_id = ? AND check_quantity > 0";
    $result = fetchRow($sql, [$taskId]);
    if ($result) {
        $summary['checked_packages'] = $result['count'];
    }
    
    // 获取差异数量
    $sql = "SELECT COUNT(*) as count 
            FROM inventory_check_cache 
            WHERE task_id = ? AND difference != 0";
    $result = fetchRow($sql, [$taskId]);
    if ($result) {
        $summary['difference_count'] = $result['count'];
    }
    
    return $summary;
}

/**
 * 生成盘点结果汇总
 */
function generateTaskResults($taskId) {
    // 先删除已有的汇总数据
    execute("DELETE FROM inventory_check_results WHERE task_id = ?", [$taskId]);
    
    // 生成新的汇总数据
    $sql = "INSERT INTO inventory_check_results 
            (task_id, glass_type_id, total_system_quantity, total_check_quantity, total_difference,
             profit_packages, loss_packages, normal_packages)
            SELECT 
                task_id,
                p.glass_type_id,
                SUM(system_quantity) as total_system_quantity,
                SUM(check_quantity) as total_check_quantity,
                SUM(difference) as total_difference,
                SUM(CASE WHEN difference > 0 THEN 1 ELSE 0 END) as profit_packages,
                SUM(CASE WHEN difference < 0 THEN 1 ELSE 0 END) as loss_packages,
                SUM(CASE WHEN difference = 0 THEN 1 ELSE 0 END) as normal_packages
            FROM inventory_check_cache c
            LEFT JOIN glass_packages p ON c.package_id = p.id
            WHERE c.task_id = ?
            GROUP BY task_id, p.glass_type_id";
    
    execute($sql, [$taskId]);
}

/**
 * 生成库存流转记录（可选）
 */
function generateInventoryTransactions($taskId) {
    // 获取差异记录，需要关联获取glass_type_id
    $differences = fetchAll("SELECT c.*, p.glass_type_id 
                             FROM inventory_check_cache c 
                             LEFT JOIN glass_packages p ON c.package_id = p.id
                             WHERE c.task_id = ? AND c.difference != 0", [$taskId]);
    
    foreach ($differences as $diff) {
        if ($diff['difference'] > 0) {
            // 盘盈：生成入库记录
            $transactionData = [
                'record_no' => generateCheckRecordNo('IN'),
                'operation_type' => 'check_in',
                'package_id' => $diff['package_id'],
                'glass_type_id' => $diff['glass_type_id'],
                'base_id' => getTaskBaseId($taskId),
                'operation_quantity' => $diff['difference'],
                'before_quantity' => $diff['system_quantity'],
                'after_quantity' => $diff['check_quantity'],
                'operator_id' => $diff['operator_id'],
                'operation_date' => date('Y-m-d'),
                'operation_time' => date('H:i:s'),
                'status' => 'completed',
                'notes' => "盘点盈余，任务ID：{$taskId}"
            ];
            insert('inventory_operation_records', $transactionData);
        } elseif ($diff['difference'] < 0) {
            // 盘亏：生成出库记录
            $transactionData = [
                'record_no' => generateCheckRecordNo('OUT'),
                'operation_type' => 'check_out',
                'package_id' => $diff['package_id'],
                'glass_type_id' => $diff['glass_type_id'],
                'base_id' => getTaskBaseId($taskId),
                'operation_quantity' => abs($diff['difference']),
                'before_quantity' => $diff['system_quantity'],
                'after_quantity' => $diff['check_quantity'],
                'operator_id' => $diff['operator_id'],
                'operation_date' => date('Y-m-d'),
                'operation_time' => date('H:i:s'),
                'status' => 'completed',
                'notes' => "盘点亏损，任务ID：{$taskId}"
            ];
            insert('inventory_operation_records', $transactionData);
        }
    }
}

/**
 * 获取任务所属基地ID
 */
function getTaskBaseId($taskId) {
    $task = fetchRow("SELECT base_id FROM inventory_check_tasks WHERE id = ?", [$taskId]);
    return $task ? $task['base_id'] : null;
}



/**
 * 处理盘点期间的出库回滚
 */
function handleCheckPeriodRollback($taskId) {
    // 获取任务结束时间（当前时间）
    $taskEndTime = date('Y-m-d H:i:s');
    
    // 获取盘点开始时间
    $task = fetchRow("SELECT started_at FROM inventory_check_tasks WHERE id = ?", [$taskId]);
    if (!$task || !$task['started_at']) {
        return 0; // 任务未开始
    }
    
    // 获取盘点期间的出库记录
    $outboundRecords = fetchAll("
        SELECT ior.package_code, ior.operation_quantity, 
               p.pieces as system_quantity, ior.operation_date, ior.notes,
               p.id as package_id
        FROM inventory_operation_records ior
        INNER JOIN glass_packages p ON ior.package_id = p.id
        WHERE ior.operation_type IN ('out', 'scrap')
        AND ior.status = 'completed'
        AND ior.operation_date >= ?
        AND ior.operation_date < ?
        ORDER BY ior.operation_date ASC
    ", [$task['started_at'], $taskEndTime]);
    
    $rollbackCount = 0;
    foreach ($outboundRecords as $record) {
        // 检查是否已在盘点缓存中
        $existing = fetchRow("
            SELECT check_quantity FROM inventory_check_cache 
            WHERE task_id = ? AND package_code = ?
        ", [$taskId, $record['package_code']]);
        
        if ($existing && $existing['check_quantity'] > 0) {
            // 已经盘点过，需要调整数量
            $newCheckQuantity = $existing['check_quantity'] + $record['operation_quantity'];
            $difference = $newCheckQuantity - $existing['system_quantity'];
            
            update('inventory_check_cache', [
                'check_quantity' => $newCheckQuantity,
                'difference' => $difference,
                'notes' => ($existing['notes'] ? $existing['notes'] . "\n" : '') . '盘点期间出库回滚：' . $record['operation_quantity'],
                'check_time' => $taskEndTime
            ], 'task_id = ? AND package_code = ?', [$taskId, $record['package_code']]);
            
        } elseif (!$existing) {
            // 还没盘点过，将出库"预盘点"为系统数量
            insert('inventory_check_cache', [
                'task_id' => $taskId,
                'package_code' => $record['package_code'],
                'package_id' => $record['package_id'],
                'system_quantity' => $record['system_quantity'],
                'check_quantity' => $record['operation_quantity'],
                'difference' => 0, // 出库数量等于系统数量，无差异
                'check_method' => 'auto_rollback',
                'check_time' => $taskEndTime,
                'operator_id' => getCurrentUserId(),
                'notes' => '盘点期间出库自动回滚：' . $record['operation_quantity']
            ]);
        } else {
            // 已存在但未盘点，更新为出库数量
            update('inventory_check_cache', [
                'check_quantity' => $record['operation_quantity'],
                'notes' => ($existing['notes'] ? $existing['notes'] . "\n" : '') . '盘点期间出库回滚：' . $record['operation_quantity'],
                'check_time' => $taskEndTime
            ], 'task_id = ? AND package_code = ?', [$taskId, $record['package_code']]);
        }
        $rollbackCount++;
    }
    
    return $rollbackCount;
}

/**
 * 处理获取包信息请求
 */
function handleGetPackageInfo() {
    $packageCode = $_POST['package_code'] ?? '';
    
    if (empty($packageCode)) {
        echo json_encode(['success' => false, 'message' => '包号不能为空']);
        exit;
    }
    
    // 直接调用inventory_operations.php中已有的getPackageInfo函数
    $result = getPackageInfo($packageCode);
    
    echo json_encode($result);
    exit;
}

/**
 * 处理获取库位选项请求
 */
function handleGetRackOptions() {
    $baseId = $_POST['base_id'] ?? '';
    
    if (!$baseId) {
        // 如果没有传base_id，则使用当前用户的基地ID
        $user = getCurrentUser();
        $baseId = $user['base_id'];
    }
    
    if (!$baseId) {
        echo json_encode(['success' => false, 'message' => '基地ID不能为空']);
        exit;
    }
    
    try {
        $sql = "SELECT sr.id, sr.code, sr.name, sr.area_type 
                FROM storage_racks sr 
                WHERE sr.base_id = ? 
                ORDER BY sr.code";
        
        $racks = fetchAll($sql, [$baseId]);
        
        echo json_encode(['success' => true, 'data' => $racks]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '获取库位失败：' . $e->getMessage()]);
        exit;
    }
}

?>
