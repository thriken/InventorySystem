<?php
/**
 * 库存操作公共业务逻辑
 * 
 * 本文件包含以下主要功能模块：
 * 1. 包信息查询和验证
 * 2. 目标库位信息获取和操作类型判断
 * 3. 库存流转操作执行
 * 4. 各种流转类型的具体处理函数
 * 5. 交易验证和业务规则检查
 * 
 * 主要函数：
 * - getPackageInfo(): 获取包信息
 * - getTargetRackInfo(): 获取目标库位信息并判断操作类型
 * - executeInventoryTransaction(): 执行库存流转操作
 * - processTransaction(): 处理具体的流转业务逻辑
 * - validateTransactionType(): 验证交易类型
 * - processPurchaseIn(): 处理采购入库
 * - processUsageOut(): 处理领用出库
 * - processReturnIn(): 处理归还入库
 * - processScrap(): 处理报废操作
 * - processInventoryCheck(): 处理盘点操作
 */

/**
 * 获取包信息（AJAX接口通用函数）
 * @param string $packageCode 包号
 * @return array 包含success和data/message的数组
 */
function getPackageInfo($packageCode) {
    if (empty($packageCode)) {
        return ['success' => false, 'message' => '包号不能为空'];
    }
    
    $sql = "SELECT gp.id, gp.package_code, gp.pieces, gp.quantity, gp.width, gp.height, gp.entry_date, gp.current_rack_id, gp.status,
               gt.name as glass_name, gt.short_name, gt.thickness, gt.color,
               sr.code as current_rack_code, sr.id as current_rack_id,
               sr.area_type as current_area_type, sr.base_id,
               b.name as base_name
        FROM glass_packages gp 
        LEFT JOIN glass_types gt ON gp.glass_type_id = gt.id 
        LEFT JOIN storage_racks sr ON gp.current_rack_id = sr.id 
        LEFT JOIN bases b ON sr.base_id = b.id 
        WHERE gp.package_code = ?";
    
    $package = fetchRow($sql, [$packageCode]);
    
    if (!$package) {
        return ['success' => false, 'message' => '未找到该包号'];
    }
    $status = [
        'in_storage' => '库存中',
        'in_processing' => '加工中',
        'scrapped' => '已报废',
        'used_up' => '已用完'
    ];
    // 构建规格字符串
    $specification = '';
    if ($package['width'] && $package['height']) {
        $specification = (int)$package['width'] . '×' . (int)$package['height'] . 'mm';
    }
    
    return [
        'success' => true,
        'data' => [
            'id' => $package['id'],
            'package_code' => $package['package_code'],
            'glass_name' => $package['glass_name'],
            'short_name' => $package['short_name'],
            'pieces' => (int)$package['pieces'],
            'quantity' => (int)$package['quantity'],
            'specification' => $specification,
            'entry_date' => $package['entry_date'] ? $package['entry_date'] : null,
            'current_rack_code' => $package['current_rack_code'] ?? '未分配',
            'current_rack_id' => $package['current_rack_id'],
            'current_area_type' => $package['current_area_type'],
            'base_id' => $package['base_id'],
            'base_name' => $package['base_name'] ?? '未分配',
            'status' => $status[$package['status']]
        ]
    ];
}

/**
 * 获取目标库位架信息并判断操作类型
 * @param string $targetRackCode 目标库位架编码
 * @param string $currentAreaType 当前区域类型
 * @param string $baseName 基地名
 * @return array 包含success和data/message的数组
 */
function getTargetRackInfo($targetRackCode, $currentAreaType,$baseName) {
    if (empty($targetRackCode)) {
        return ['success' => false, 'message' => '目标架号不能为空'];
    }

    if (empty($baseName)) {
           $sql = "SELECT r.*, b.name as base_name
        FROM storage_racks r 
        LEFT JOIN bases b ON r.base_id = b.id 
        WHERE r.code = ?";
        $targetRack = fetchRow($sql, [$targetRackCode]);
    }else{
        $sql = "SELECT r.*, b.name as base_name
        FROM storage_racks r 
        LEFT JOIN bases b ON r.base_id = b.id 
        WHERE (r.code = ? OR r.name = ?) AND b.name = ?";
        $targetRack = fetchRow($sql, [$targetRackCode, $targetRackCode, $baseName]);
    }
    if (!$targetRack) {
		
        return ['success' => false, 'message' => '未找到目标架号'];
    }
    
    $transactionType = determineTransactionType($currentAreaType, $targetRack['area_type']);
    
    return [
        'success' => true,
        'data' => [
            'rack_code' => $targetRack['code'],
            'rack_name' => $targetRack['name'],
            'area_type' => $targetRack['area_type'],
            'base_name' => $targetRack['base_name'],
            'transaction_type' => $transactionType['type'],
            'transaction_name' => $transactionType['name']
        ]
    ];
}

/**
 * 根据库位名称获取目标库位信息并判断操作类型（支持基地ID限制）
 * @param string $targetRackName 目标库位名称
 * @param string $currentAreaType 当前区域类型
 * @param int $baseId 用户基地ID
 * @return array 包含success和data/message的数组
 */
function getTargetRackInfoByName($targetRackName, $currentAreaType, $baseId) {
    if (empty($targetRackName)) {
        return ['success' => false, 'message' => '目标库位名称不能为空'];
    }
    
    if (empty($baseId)) {
        return ['success' => false, 'message' => '用户基地信息无效'];
    }
    
    // 根据库位名称和用户基地ID查询
    $sql = "SELECT r.*, b.name as base_name
            FROM storage_racks r 
            LEFT JOIN bases b ON r.base_id = b.id 
            WHERE r.name = ? AND r.base_id = ?";
    
    $targetRack = fetchRow($sql, [$targetRackName, $baseId]);
    
    if (!$targetRack) {
        return ['success' => false, 'message' => '未找到指定基地的目标库位'];
    }
    
    $transactionType = determineTransactionType($currentAreaType, $targetRack['area_type']);
    
    return [
        'success' => true,
        'data' => [
            'rack_code' => $targetRack['code'],
            'rack_name' => $targetRack['name'],
            'area_type' => $targetRack['area_type'],
            'base_name' => $targetRack['base_name'],
            'transaction_type' => $transactionType['type'],
            'transaction_name' => $transactionType['name']
        ]
    ];
}

function determineTransactionType($fromAreaType, $toAreaType) {
    if (empty($fromAreaType) or $fromAreaType =='null') {
        // 从未分配到任何区域（新包入库）
        switch ($toAreaType) {
            case 'storage':
                return ['type' => 'purchase_in', 'name' => '采购入库'];
            case 'temporary':
                return ['type' => 'purchase_in', 'name' => '采购入库（临时）'];
            default:
                return ['type' => '', 'name' => '无法确定操作类型'];
        }
    }
    
    // 从当前区域到目标区域
    $transition = $fromAreaType . '_to_' . $toAreaType;
    
    switch ($transition) {
        // 库存区相关流转
        case 'storage_to_processing':
            return ['type' => 'usage_out', 'name' => '领用出库'];
        case 'storage_to_temporary':
            return ['type' => 'location_adjust', 'name' => '库位调整'];
        case 'storage_to_storage':
            return ['type' => 'location_adjust', 'name' => '库位调整'];
                
        // 加工区相关流转
        case 'processing_to_storage':
            return ['type' => 'return_in', 'name' => '归还入库'];
        case 'processing_to_scrap':
            return ['type' => 'scrap', 'name' => '报废'];
            
        // 其他流转
        case 'temporary_to_storage':
            return ['type' => 'location_adjust', 'name' => '库位调整'];
        case 'temporary_to_processing':
            return ['type' => 'usage_out', 'name' => '领用出库'];
            
        default:
            return ['type' => '', 'name' => '不支持的流转方向'];
    }
}

/**
 * 执行库存流转操作（使用新的inventory_operation_records表）
 * @param string $packageCode 包号
 * @param string $targetRackCode 目标库位架编码
 * @param int $quantity 数量
 * @param string $transactionType 交易类型
 * @param array $currentUser 当前用户信息
 * @param string $scrapReason 报废原因（可选）
 * @param string $notes 备注（可选）
 * @param bool $allowCrossBase 是否允许跨基地操作（可选，默认false）
 * @return string 操作结果消息
 */
function executeInventoryTransaction($packageCode, $targetRackCode, $quantity, $transactionType, $currentUser, $scrapReason = '', $notes = '', $allowCrossBase = false) {
    return executeInTransaction(function () use (
        $packageCode,
        $targetRackCode,
        $quantity,
        $transactionType,
        $scrapReason,
        $notes,
        $currentUser,
        $allowCrossBase
    ) {
        return processTransaction(
            $packageCode,
            $targetRackCode,
            $quantity,
            $transactionType,
            $scrapReason,
            $notes,
            $currentUser,
            $allowCrossBase
        );
    });
}

/**
 * 处理库存流转业务逻辑（新版 - 使用inventory_operation_records表）
 * @param string $packageCode 包号
 * @param string $targetRackCode 目标库位架编码
 * @param int $quantity 数量
 * @param string $transactionType 交易类型
 * @param string $scrapReason 报废原因
 * @param string $notes 备注
 * @param array $currentUser 当前用户信息
 * @param bool $allowCrossBase 是否允许跨基地操作
 * @return string 操作结果消息
 */
function processTransaction($packageCode, $targetRackCode, $quantity, $transactionType, $scrapReason, $notes, $currentUser, $allowCrossBase = false)
{
    // 查询包信息
    $sql = "SELECT gp.*, gt.name as glass_name, gt.thickness, gt.color, gt.brand, gt.manufacturer,
                   r.code as current_rack_code, r.area_type as current_area_type, b.name as base_name, r.base_id as current_base_id
            FROM glass_packages gp
            LEFT JOIN glass_types gt ON gp.glass_type_id = gt.id
            LEFT JOIN storage_racks r ON gp.current_rack_id = r.id
            LEFT JOIN bases b ON r.base_id = b.id
            WHERE gp.package_code = ?";
    $package = fetchRow($sql, [$packageCode]);

    if (!$package) {
        throw new Exception('找不到包号为 ' . $packageCode . ' 的包');
    }

    // 查询目标库位架信息
    $sql = "SELECT r.*, b.name as base_name FROM storage_racks r 
            LEFT JOIN bases b ON r.base_id = b.id 
            WHERE r.code = ?";
    $targetRack = fetchRow($sql, [$targetRackCode]);

    if (!$targetRack) {
        throw new Exception('找不到目标库位架，请检查库位架编码');
    }

    // 获取当前库位架信息
    $fromRack = null;
    if ($package['current_rack_id']) {
        $sql = "SELECT r.*, r.area_type, b.name as base_name 
               FROM storage_racks r 
               LEFT JOIN bases b ON r.base_id = b.id 
               WHERE r.id = ?";
        $fromRack = fetchRow($sql, [$package['current_rack_id']]);
    }
    
    // 基地权限验证
    validateBasePermissions($currentUser, $package, $fromRack, $targetRack, $transactionType, $allowCrossBase);
    
    // 检查是否为跨基地操作，在备注中添加标识
    if ($fromRack && $fromRack['base_id'] !== $targetRack['base_id']) {
        $baseFromName = $fromRack['base_name'] ?? '未知基地';
        $baseToName = $targetRack['base_name'];
        
        if ($targetRack['area_type'] === 'temporary') {
            $notes = "[基地间转移] {$baseFromName} → {$baseToName}" . ($notes ? " | {$notes}" : '');
        } else {
            $notes = "[基地间流转] {$baseFromName} → {$baseToName}" . ($notes ? " | {$notes}" : '');
        }
    } else if ($targetRack['area_type'] === 'temporary' && $transactionType === 'purchase_in') {
        $notes = "[采购入库]" . ($notes ? " | {$notes}" : '');
    } else if ($targetRack['area_type'] === 'storage' && $transactionType === 'return_in') {
        $notes = "[实际使用]" . ($notes ? " | {$notes}" : '');
    }
    
    // 验证交易类型
    validateTransactionType($transactionType, $package, $fromRack, $targetRack, $quantity);
    
    // 生成记录单号
    $recordNo = generateOperationRecordNo($transactionType);
    
    // 计算面积信息
    $unitArea = null;
    $totalArea = null;
    if ($package['width'] && $package['height']) {
        $unitArea = ($package['width'] * $package['height']) / 1000000; // 转换为平方米
        $totalArea = $unitArea * $quantity;
    }
    
    // 根据交易类型执行相应操作
    switch ($transactionType) {
        case 'purchase_in':
            return processPurchaseIn($package, $targetRack, $quantity, $recordNo, $unitArea, $totalArea, $notes, $currentUser);
        case 'usage_out':
            return processUsageOut($package, $targetRack, $quantity, $recordNo, $unitArea, $totalArea, $scrapReason, $notes, $currentUser);
        case 'return_in':
            return processReturnIn($package, $targetRack, $quantity, $recordNo, $unitArea, $totalArea, $notes, $currentUser);
        case 'scrap':
            return processScrap($package, $targetRack, $quantity, $recordNo, $unitArea, $totalArea, $scrapReason, $currentUser);
        case 'check_in':
        case 'check_out':
            return processInventoryCheck($transactionType, $package, $targetRack, $quantity, $recordNo, $unitArea, $totalArea, $notes, $currentUser);
        case 'location_adjust':
            return processLocationAdjust($package, $targetRack, $quantity, $recordNo, $unitArea, $totalArea, $notes, $currentUser);
        default:
            throw new Exception('不支持的交易类型：' . $transactionType);
    }
}

/**
 * 生成操作记录单号
 * @param string $operationType 操作类型
 * @return string 记录单号
 */
function generateOperationRecordNo($operationType) {
    $date = date('Ymd');
    $prefixes = [
        'purchase_in' => 'CG',
        'usage_out' => 'LY',
        'partial_usage' => 'BF',
        'return_in' => 'GH',
        'scrap' => 'BF',
        'check_in' => 'PY',
        'check_out' => 'PK',
        'location_adjust' => 'KW'
    ];
    
    $prefix = $prefixes[$operationType] ?? 'OP';
    
    // 获取当天该类型的最大序号
    $sql = "SELECT COUNT(*) as count FROM inventory_operation_records 
            WHERE operation_type = ? AND DATE(created_at) = CURDATE()";
    $result = fetchRow($sql, [$operationType]);
    $sequence = ($result ? $result['count'] : 0) + 1;
    
    return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

function validateTransactionType($transactionType, $package, $fromRack, $targetRack, $quantity)
{
    switch ($transactionType) {
        case 'purchase_in':
            if ($targetRack['area_type'] !== 'storage') {
                throw new Exception('采购入库的目标区域必须是库存区');
            }
            break;

        case 'usage_out':
            if ($fromRack && $fromRack['area_type'] !== 'storage') {
                throw new Exception('领用出库的来源区域必须是库存区');
            }
            if ($targetRack['area_type'] !== 'processing' && $targetRack['area_type'] !== 'scrap') {
                throw new Exception('领用出库的目标区域必须是加工区或报废区');
            }
            break;

        case 'return_in':
            if ($fromRack && $fromRack['area_type'] !== 'processing') {
                throw new Exception('归还入库的来源区域必须是加工区');
            }
            if ($targetRack['area_type'] !== 'storage') {
                throw new Exception('归还入库的目标区域必须是库存区');
            }
            break;

        case 'scrap':
            if ($targetRack['area_type'] !== 'scrap') {
                throw new Exception('报废操作的目标区域必须是报废区');
            }
            break;
    }

    // 验证数量
    if ($quantity > $package['pieces']) {
        throw new Exception('操作数量不能超过包裹现有片数：' . $package['pieces']);
    }
}

function processPurchaseIn($package, $targetRack, $quantity, $recordNo, $unitArea, $totalArea, $notes, $currentUser)
{
    // 采购入库：插入新的操作记录
    $sql = "INSERT INTO inventory_operation_records (
                record_no, operation_type, package_id, glass_type_id, base_id,
                operation_quantity, before_quantity, after_quantity, 
                from_rack_id, to_rack_id, unit_area, total_area,
                operator_id, operation_date, operation_time, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    query($sql, [
        $recordNo,
        'purchase_in',
        $package['id'],
        $package['glass_type_id'],
        $targetRack['base_id'],
        $quantity,
        $package['pieces'], // 操作前数量
        $package['pieces'], // 采购入库数量不变
        $package['current_rack_id'],
        $targetRack['id'],
        $unitArea,
        $totalArea,
        $currentUser['id'],
        date('Y-m-d'),
        date('H:i:s'),
        $notes
    ]);

    // 更新包状态和位置
    $sql = "UPDATE glass_packages SET current_rack_id = ?, status = 'in_storage', updated_at = ? WHERE id = ?";
    query($sql, [$targetRack['id'], date('Y-m-d H:i:s'), $package['id']]);
    
    // 采购入库位置号处理：新包放在最外面（位置1），现有包位置号递增
    addToRack($package['id'], $targetRack['id']);

    return '采购入库操作成功完成！';
}

function processUsageOut($package, $targetRack, $quantity, $recordNo, $unitArea, $totalArea, $scrapReason, $notes, $currentUser)
{
    if ($quantity == 0) {
        $quantity = $package['pieces']; // 完全使用
    }
    
    if ($quantity == $package['pieces']) {
        // 整包领用出库
        $afterQuantity = 0;
        
        // 插入操作记录
        $sql = "INSERT INTO inventory_operation_records (
                    record_no, operation_type, package_id, glass_type_id, base_id,
                    operation_quantity, before_quantity, after_quantity, 
                    from_rack_id, to_rack_id, unit_area, total_area,
                    operator_id, operation_date, operation_time, notes, scrap_reason
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        query($sql, [
            $recordNo,
            'usage_out',
            $package['id'],
            $package['glass_type_id'],
            $targetRack['base_id'],
            $quantity,
            $package['pieces'],
            $afterQuantity,
            $package['current_rack_id'],
            $targetRack['id'],
            $unitArea,
            $totalArea,
            $currentUser['id'],
            date('Y-m-d'),
            date('H:i:s'),
            $notes,
            $scrapReason
        ]);

        // 更新包状态
        $newStatus = $targetRack['area_type'] === 'scrap' ? 'used_up' : 'in_processing';
        $sql = "UPDATE glass_packages SET current_rack_id = ?, status = ?, pieces = ?, updated_at = ? WHERE id = ?";
        query($sql, [$targetRack['id'], $newStatus, $afterQuantity, date('Y-m-d H:i:s'), $package['id']]);
        
        // 离开库存区：重新整理原库位位置号
        removeFromRack($package['current_rack_id'], $package['id']);

        return '整包领用出库操作成功完成！';
    } else {
        // 部分领用出库
        $currentPieces = fetchOne("SELECT pieces FROM glass_packages WHERE id = ?", [$package['id']]);
        
        if ($currentPieces < $quantity) {
            throw new Exception('当前包的片数不足，无法完成领用操作');
        }

        $afterQuantity = $currentPieces - $quantity;
        
        // 插入操作记录
        $sql = "INSERT INTO inventory_operation_records (
                    record_no, operation_type, package_id, glass_type_id, base_id,
                    operation_quantity, before_quantity, after_quantity, 
                    from_rack_id, to_rack_id, unit_area, total_area,
                    operator_id, operation_date, operation_time, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        query($sql, [
            $recordNo,
            'partial_usage',
            $package['id'],
            $package['glass_type_id'],
            $targetRack['base_id'],
            $quantity,
            $currentPieces,
            $afterQuantity,
            $package['current_rack_id'],
            $targetRack['id'],
            $unitArea,
            $totalArea,
            $currentUser['id'],
            date('Y-m-d'),
            date('H:i:s'),
            $notes
        ]);

        // 更新片数
        $sql = "UPDATE glass_packages SET pieces = pieces - ?, updated_at = ? WHERE id = ?";
        query($sql, [$quantity, date('Y-m-d H:i:s'), $package['id']]);

        return '部分领用出库操作成功完成！剩余片数：' . $afterQuantity;
    }
}

function processLocationAdjust($package, $targetRack, $quantity, $recordNo, $unitArea, $totalArea, $notes, $currentUser)
{
    // 库位调整逻辑
    $sql = "INSERT INTO inventory_operation_records (
                record_no, operation_type, package_id, glass_type_id, base_id,
                operation_quantity, before_quantity, after_quantity, 
                from_rack_id, to_rack_id, unit_area, total_area,
                operator_id, operation_date, operation_time, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    query($sql, [
        $recordNo,
        'location_adjust',
        $package['id'],
        $package['glass_type_id'],
        $targetRack['base_id'],
        $quantity,
        $package['pieces'],
        $package['pieces'],
        $package['current_rack_id'],
        $targetRack['id'],
        $unitArea,
        $totalArea,
        $currentUser['id'],
        date('Y-m-d'),
        date('H:i:s'),
        $notes
    ]);

    // 更新包的当前库位
    $sql = "UPDATE glass_packages SET current_rack_id = ?, updated_at = ? WHERE id = ?";
    query($sql, [$targetRack['id'], date('Y-m-d H:i:s'), $package['id']]);
    
    // 移除原库位中的包并重新整理顺序
    if ($package['current_rack_id']) {
        removeFromRack($package['current_rack_id'], $package['id']);
    }
    
    // 为包在新库位中分配位置顺序号
    assignPackagePosition($package['id'], $targetRack['id']);
    return '库位调整操作成功完成！';
}

function processReturnIn($package, $targetRack, $quantity, $recordNo, $unitArea, $totalArea, $notes, $currentUser)
{
    if ($quantity == 0) {
        // 归还数量为0，表示完全使用
        $actualUsage = $package['pieces'];
        $afterQuantity = 0;
        
        $sql = "INSERT INTO inventory_operation_records (
                    record_no, operation_type, package_id, glass_type_id, base_id,
                    operation_quantity, before_quantity, after_quantity, 
                    from_rack_id, to_rack_id, unit_area, total_area,
                    operator_id, operation_date, operation_time, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        query($sql, [
            $recordNo,
            'return_in',
            $package['id'],
            $package['glass_type_id'],
            $targetRack['base_id'],
            0, // 归还数量为0
            $package['pieces'],
            $afterQuantity,
            $package['current_rack_id'],
            $targetRack['id'],
            $unitArea,
            $totalArea,
            $currentUser['id'],
            date('Y-m-d'),
            date('H:i:s'),
            $notes . ' (完全使用)' . '(' . $actualUsage . '片)'
        ]);

        // 更新包状态为已用完，片数为0
        $sql = "UPDATE glass_packages SET current_rack_id = ?, status = 'used_up', pieces = 0, updated_at = ? WHERE id = ?";
        query($sql, [$targetRack['id'], date('Y-m-d H:i:s'), $package['id']]);

        return '归还入库操作成功完成！该包已完全使用。';
    } else {
        // 正常归还逻辑
        $actualUsage = $package['pieces'] - $quantity;
        
        $sql = "INSERT INTO inventory_operation_records (
                    record_no, operation_type, package_id, glass_type_id, base_id,
                    operation_quantity, before_quantity, after_quantity, 
                    from_rack_id, to_rack_id, unit_area, total_area,
                    operator_id, operation_date, operation_time, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        query($sql, [
            $recordNo,
            'return_in',
            $package['id'],
            $package['glass_type_id'],
            $targetRack['base_id'],
            $quantity,
            $package['pieces'],
            $quantity,
            $package['current_rack_id'],
            $targetRack['id'],
            $unitArea,
            $totalArea,
            $currentUser['id'],
            date('Y-m-d'),
            date('H:i:s'),
            $notes . ' (' . $actualUsage . ')'
        ]);

        // 更新包状态为库存中，更新剩余片数
        $sql = "UPDATE glass_packages SET current_rack_id = ?, status = 'in_storage', pieces = ?, updated_at = ? WHERE id = ?";
        query($sql, [$targetRack['id'], $quantity, date('Y-m-d H:i:s'), $package['id']]);
        
        // 归还入库位置号处理：归还的包放在最外面（位置1），现有包位置号递增
        addToRack($package['id'], $targetRack['id']);

        return '归还入库操作成功完成！';
    }
}

function processScrap($package, $targetRack, $quantity, $recordNo, $unitArea, $totalArea, $scrapReason, $currentUser)
{
    $afterQuantity = $package['pieces'] - $quantity;
    
    // 插入报废操作记录
    $sql = "INSERT INTO inventory_operation_records (
                record_no, operation_type, package_id, glass_type_id, base_id,
                operation_quantity, before_quantity, after_quantity, 
                from_rack_id, to_rack_id, unit_area, total_area,
                operator_id, operation_date, operation_time, notes, scrap_reason
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    query($sql, [
        $recordNo,
        'scrap',
        $package['id'],
        $package['glass_type_id'],
        $targetRack['base_id'],
        $quantity,
        $package['pieces'],
        $afterQuantity,
        $package['current_rack_id'],
        $targetRack['id'],
        $unitArea,
        $totalArea,
        $currentUser['id'],
        date('Y-m-d'),
        date('H:i:s'),
        $scrapReason,
        $scrapReason
    ]);

    if ($quantity == $package['pieces']) {
        // 整包报废 - 修改状态为used_up而不是scrapped
        $sql = "UPDATE glass_packages SET current_rack_id = ?, status = 'used_up', pieces = 0, updated_at = ? WHERE id = ?";
        query($sql, [$targetRack['id'], date('Y-m-d H:i:s'), $package['id']]);
        return '整包报废操作成功完成！该包已完全使用。';
    } else {
        // 部分报废
        $sql = "UPDATE glass_packages SET pieces = ?, updated_at = ? WHERE id = ?";
        query($sql, [$afterQuantity, date('Y-m-d H:i:s'), $package['id']]);
        return '部分报废操作成功完成！剩余片数：' . $afterQuantity;
    }
}

function processInventoryCheck($transactionType, $package, $targetRack, $quantity, $recordNo, $unitArea, $totalArea, $notes, $currentUser)
{
    $beforeQuantity = $package['pieces'];
    
    if ($transactionType === 'check_in') {
        $afterQuantity = $beforeQuantity + $quantity;
        $message = '盘盈入库操作成功完成！新增片数：' . $quantity;
    } else {
        $afterQuantity = $beforeQuantity - $quantity;
        $message = '盘亏出库操作成功完成！减少片数：' . $quantity;
    }

    // 插入盘点操作记录
    $sql = "INSERT INTO inventory_operation_records (
                record_no, operation_type, package_id, glass_type_id, base_id,
                operation_quantity, before_quantity, after_quantity, 
                from_rack_id, to_rack_id, unit_area, total_area,
                operator_id, operation_date, operation_time, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    query($sql, [
        $recordNo,
        $transactionType,
        $package['id'],
        $package['glass_type_id'],
        $targetRack['base_id'],
        $quantity,
        $beforeQuantity,
        $afterQuantity,
        $package['current_rack_id'],
        $targetRack['id'],
        $unitArea,
        $totalArea,
        $currentUser['id'],
        date('Y-m-d'),
        date('H:i:s'),
        $notes
    ]);

    // 更新包的片数和位置
    $sql = "UPDATE glass_packages SET current_rack_id = ?, pieces = ?, updated_at = ? WHERE id = ?";
    query($sql, [$targetRack['id'], $afterQuantity, date('Y-m-d H:i:s'), $package['id']]);
    
    // 盘点操作位置号处理：只有盘盈入库（check_in）才需要调整位置号
    if ($transactionType === 'check_in') {
        // 盘盈入库：将包放在最外面（位置1），现有包位置号递增
        addToRack($package['id'], $targetRack['id']);
    }

    return $message;
}

/**
 * 获取指定库位架的下一个可用位置顺序号
 * @param int $rackId 库位架ID
 * @return int 下一个可用的位置顺序号
 */
function getNextPositionOrder($rackId) {
    if (empty($rackId)) {
        return 1;
    }
    
    $sql = "SELECT MAX(position_order) as max_order FROM glass_packages WHERE current_rack_id = ?";
    $result = fetchRow($sql, [$rackId]);
    
    return ($result['max_order'] ?? 0) + 1;
}

/**
 * 重新整理指定库位架中包的位置顺序号（去除空隙，从1开始连续排列） 
 * 1是最外面的包，2是第二个包，以此类推，最里面的包顺序号最大
 * @param int $rackId 库位架ID
 * @return bool 操作是否成功
 */
function reorderPackagePositions($rackId) {
    if (empty($rackId)) {
        return false;
    }
    
    // 只获取有效状态的包（排除已用完和已报废的包）
    $sql = "SELECT id FROM glass_packages 
            WHERE current_rack_id = ? 
            AND status NOT IN ('used_up', 'scrapped') 
            ORDER BY position_order ASC, created_at ASC";
    $packages = fetchAll($sql, [$rackId]);
    
    // 重新分配顺序号
    $position = 1;
    foreach ($packages as $package) {
        $updateSql = "UPDATE glass_packages SET position_order = ?, updated_at = ? WHERE id = ?";
        query($updateSql, [$position, date('Y-m-d H:i:s'), $package['id']]);
        $position++;
    }
    
    return true;
}

/**
 * 为包分配新的位置顺序号
 * @param int $packageId 包ID
 * @param int $rackId 库位架ID
 * @param bool $isRearrange 是否重新整理现有包的顺序（默认true）
 * @return bool 操作是否成功
 */
function assignPackagePosition($packageId, $rackId, $isRearrange = true) {
    if (empty($packageId) || empty($rackId)) {
        return false;
    }
    
    // 更新包的位置顺序号为1（放在最外面）
    $sql = "UPDATE glass_packages SET position_order = 1, updated_at = ? WHERE id = ?";
    query($sql, [date('Y-m-d H:i:s'), $packageId]);
    
    // 重新整理库位中所有包的顺序号
    if ($isRearrange) {
        reorderPackagePositions($rackId);
    }
    
    return true;
}

/**
 * 将包添加到指定库位架（新包放在最外面，现有包位置号递增）
 * @param int $packageId 包ID
 * @param int $rackId 库位架ID
 * @return bool 操作是否成功
 */
function addToRack($packageId, $rackId) {
    if (empty($packageId) || empty($rackId)) {
        return false;
    }
    
    return assignPackagePosition($packageId, $rackId, true);
}

/**
 * 将包从指定库位架移除（重新整理位置顺序号）
 * @param int $rackId 库位架ID
 * @param int $packageId 要移除的包ID（可选）
 * @return bool 操作是否成功
 */
function removeFromRack($rackId, $packageId = null) {
    if (empty($rackId)) {
        return false;
    }
    
    if ($packageId) {
        // 移除指定包
        $sql = "UPDATE glass_packages SET position_order = 0, updated_at = ? 
                WHERE id = ? AND current_rack_id = ?";
        query($sql, [date('Y-m-d H:i:s'), $packageId, $rackId]);
    }
    
    // 重新整理库位中剩余包的顺序号
    reorderPackagePositions($rackId);
    
    return true;
}

/**
 * 验证用户对指定基地的操作权限
 * @param array $currentUser 当前用户信息
 * @param array $package 包信息
 * @param array $fromRack 源库位信息
 * @param array $toRack 目标库位信息
 * @param string $transactionType 交易类型
 * @param bool $allowCrossBase 是否允许跨基地操作
 * @throws Exception 如果权限不足则抛出异常
 */
function validateBasePermissions($currentUser, $package, $fromRack, $toRack, $transactionType, $allowCrossBase = false) {
    if ($currentUser['role'] === 'admin') {
        return;
    }
    
    $userBaseId = $currentUser['base_id'];
    $userBaseName = $currentUser['base_name'] ?? '';
    
    // 检查用户是否有基地权限
    if (empty($userBaseId) || $userBaseId === 0) {
        throw new Exception('当前用户没有分配基地权限，请联系管理员');
    }
    
    // 获取包所在基地ID
    $packageBaseId = null;
    if ($fromRack && $fromRack['base_id']) {
        $packageBaseId = $fromRack['base_id'];
    } else if ($package['current_rack_id']) {
        $sql = "SELECT base_id FROM storage_racks WHERE id = ?";
        $result = fetchRow($sql, [$package['current_rack_id']]);
        $packageBaseId = $result['base_id'] ?? null;
    }
    
    // 获取目标基地ID
    $targetBaseId = $toRack['base_id'] ?? null;
    
    // 权限验证逻辑
    switch ($transactionType) {
        case 'purchase_in':
            // 采购入库：只能在当前用户基地内操作
            if ($targetBaseId !== $userBaseId) {
                throw new Exception('采购入库操作只能在您所属的基地（' . $userBaseName . '）内进行');
            }
            break;
            
        case 'usage_out':
            // 领用出库：包必须在当前用户基地内
            if ($packageBaseId !== $userBaseId) {
                throw new Exception('只能从您所属的基地（' . $userBaseName . '）领用玻璃包');
            }
            if ($targetBaseId !== $userBaseId) {
                throw new Exception('领用的目标库位必须在您所属的基地（' . $userBaseName . '）内');
            }
            break;
            
        case 'return_in':
            // 归还入库：只能在当前用户基地内操作
            if ($packageBaseId !== $userBaseId) {
                throw new Exception('只能归还到您所属的基地（' . $userBaseName . '）');
            }
            if ($targetBaseId !== $userBaseId) {
                throw new Exception('归还的目标库位必须在您所属的基地（' . $userBaseName . '）内');
            }
            break;
            
        case 'scrap':
            // 报废操作：包必须在当前用户基地内
            if ($packageBaseId !== $userBaseId) {
                throw new Exception('只能报废您所属的基地（' . $userBaseName . '）内的玻璃包');
            }
            if ($targetBaseId !== $userBaseId) {
                throw new Exception('报废的目标库位必须在您所属的基地（' . $userBaseName . '）内');
            }
            break;
            
        case 'location_adjust':
            // 库位调整：默认要求包和目标库位都必须在当前用户基地内
            // 如果允许跨基地操作，则跳过基地限制
            if (!$allowCrossBase) {
                if ($packageBaseId !== $userBaseId) {
                    throw new Exception('只能调整您所属的基地（' . $userBaseName . '）内包的位置');
                }
                if ($targetBaseId !== $userBaseId) {
                    throw new Exception('目标库位必须在您所属的基地（' . $userBaseName . '）内');
                }
            }
            break;
            
        case 'check_in':
        case 'check_out':
            // 盘点操作：包和目标库位都必须在当前用户基地内
            if ($packageBaseId !== $userBaseId) {
                throw new Exception('只能盘点您所属的基地（' . $userBaseName . '）内的玻璃包');
            }
            if ($targetBaseId !== $userBaseId) {
                throw new Exception('盘点操作的目标库位必须在您所属的基地（' . $userBaseName . '）内');
            }
            break;
            
        default:
            throw new Exception('未知的交易类型：' . $transactionType);
    }
}

/**
 * 获取盘点类型文本
 * @param string $type 盘点类型
 * @return string 中文描述
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
 * 获取任务状态文本
 * @param string $status 任务状态
 * @return string 中文描述
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
 * 获取包ID通过包号
 * @param string $packageCode 包号
 * @return int|null 包ID
 */
function getPackageId($packageCode) {
    $package = fetchRow("SELECT id FROM glass_packages WHERE package_code = ?", [$packageCode]);
    return $package ? $package['id'] : null;
}

/**
 * 生成盘点相关的记录编号
 * @param string $type 类型 IN为盘盈，OUT为盘亏
 * @return string 记录编号
 */
function generateCheckRecordNo($type) {
    $prefix = $type == 'IN' ? 'PD' : 'PC';
    $date = date('Ymd');
    $sequence = getNextCheckSequence($type);
    return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

/**
 * 获取下一个盘点序号
 * @param string $type 类型
 * @return int 序号
 */
function getNextCheckSequence($type) {
    $sql = "SELECT COUNT(*) as count FROM inventory_operation_records 
            WHERE operation_type = ? AND DATE(created_at) = CURDATE()";
    $operationType = $type == 'IN' ? 'check_in' : 'check_out';
    $result = fetchRow($sql, [$operationType]);
    return ($result ? $result['count'] : 0) + 1;
}
/**
 * 获取操作记录（分页查询）
 * @param array $currentUser 当前用户信息
 * @param array $params 查询参数
 * @return array 查询结果
 */
function getOperationHistory($currentUser, $params) {
    // 构建查询条件
    $conditions = [];
    $queryParams = [];
    
    // 日期范围条件
    if ($params['start_date']) {
        $conditions[] = "ior.operation_date >= ?";
        $queryParams[] = $params['start_date'];
    }
    
    if ($params['end_date']) {
        $conditions[] = "ior.operation_date <= ?";
        $queryParams[] = $params['end_date'];
    }
    
    // 包号条件
    if ($params['package_code']) {
        $conditions[] = "gp.package_code LIKE ?";
        $queryParams[] = '%' . $params['package_code'] . '%';
    }
    
    // 操作类型条件
    if ($params['operation_type']) {
        $conditions[] = "ior.operation_type = ?";
        $queryParams[] = $params['operation_type'];
    }
    
    // 基地权限控制
    if ($currentUser['role'] !== 'admin') {
        // 非管理员根据用户的base_id来查询
        if (!empty($currentUser['base_id'])) {
            // 如果用户有base_id，则只查询该base_id的记录
            $conditions[] = "ior.base_id = ?";
            $queryParams[] = $currentUser['base_id'];
        }
        // 如果用户没有base_id，则查询全部记录（不添加base_id条件）
    } elseif ($params['base_id']) {
        $conditions[] = "ior.base_id = ?";
        $queryParams[] = $params['base_id'];
    }
    
    // 分页参数
    $page = max(1, intval($params['page'] ?? 1));
    $pageSize = max(1, min(100, intval($params['page_size'] ?? 20))); // 限制每页最多100条
    $offset = ($page - 1) * $pageSize;
    
    // 获取总记录数
    $countSql = "
        SELECT COUNT(*) as total
        FROM inventory_operation_records ior
        LEFT JOIN glass_packages gp ON ior.package_id = gp.id
    ";
    
    if (!empty($conditions)) {
        $countSql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $totalResult = fetchRow($countSql, $queryParams);
    $totalRecords = $totalResult['total'] ?? 0;
    $totalPages = ceil($totalRecords / $pageSize);
    
    // 查询记录
    $sql = "
        SELECT 
            ior.id, ior.record_no, ior.operation_type, ior.package_id,
            ior.glass_type_id, ior.base_id, ior.operation_quantity,
            ior.before_quantity, ior.after_quantity, ior.from_rack_id,
            ior.to_rack_id, ior.unit_area, ior.total_area, ior.operator_id,
            ior.operation_date, ior.operation_time, ior.status,
            ior.scrap_reason, ior.notes, ior.related_record_id,
            ior.created_at, ior.updated_at,
            gp.package_code, gt.name as glass_name, gt.thickness,
            gt.color, gt.brand, b.name as base_name, u.real_name as operator_name,
            fr.code as from_rack_code, tr.code as to_rack_code
        FROM inventory_operation_records ior
        LEFT JOIN glass_packages gp ON ior.package_id = gp.id
        LEFT JOIN glass_types gt ON ior.glass_type_id = gt.id
        LEFT JOIN bases b ON ior.base_id = b.id
        LEFT JOIN users u ON ior.operator_id = u.id
        LEFT JOIN storage_racks fr ON ior.from_rack_id = fr.id
        LEFT JOIN storage_racks tr ON ior.to_rack_id = tr.id
    ";
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $sql .= " ORDER BY ior.operation_date DESC, ior.operation_time DESC LIMIT ? OFFSET ?";
    $queryParams[] = $pageSize;
    $queryParams[] = $offset;
    
    $result = query($sql, $queryParams);
    $records = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
    }
    
    return [
        'records' => $records,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords,
            'page_size' => $pageSize
        ],
        'time_range' => [
            'start_date' => $params['start_date'],
            'end_date' => $params['end_date']
        ]
    ];
}

?>