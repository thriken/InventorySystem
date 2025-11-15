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
    
    $sql = "SELECT gp.id, gp.package_code, gp.pieces, gp.quantity, gp.current_rack_id, gp.status,
               gt.name as glass_name, gt.thickness, gt.color,
               sr.code as current_rack_code, sr.id as current_rack_id,
               sr.area_type as current_area_type,
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
    return [
        'success' => true,
        'data' => [
            'id' => $package['id'],
            'package_code' => $package['package_code'],
            'glass_name' => $package['glass_name'],
            'pieces' => (int)$package['pieces'],
            'quantity' => (int)$package['quantity'],
            'current_rack_code' => $package['current_rack_code'] ?? '未分配',
            'current_rack_id' => $package['current_rack_id'],
            'current_area_type' => $package['current_area_type'],
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
        WHERE r.name = ? AND b.name = ?";
        $targetRack = fetchRow($sql, [$targetRackCode, $baseName]);
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

function determineTransactionType($fromAreaType, $toAreaType) {
    if (empty($fromAreaType)) {
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
 * 执行库存流转操作（从admin/transactions.php移植）
 * @param string $packageCode 包号
 * @param string $targetRackCode 目标库位架编码
 * @param int $quantity 数量
 * @param string $transactionType 交易类型
 * @param array $currentUser 当前用户信息
 * @param string $scrapReason 报废原因（可选）
 * @param string $notes 备注（可选）
 * @return string 操作结果消息
 */
function executeInventoryTransaction($packageCode, $targetRackCode, $quantity, $transactionType, $currentUser, $scrapReason = '', $notes = '') {
    return executeInTransaction(function () use (
        $packageCode,
        $targetRackCode,
        $quantity,
        $transactionType,
        $scrapReason,
        $notes,
        $currentUser
    ) {
        return processTransaction(
            $packageCode,
            $targetRackCode,
            $quantity,
            $transactionType,
            $scrapReason,
            $notes,
            $currentUser
        );
    });
}

// 将业务逻辑拆分为独立函数
function processTransaction($packageCode, $targetRackCode, $quantity, $transactionType, $scrapReason, $notes, $currentUser)
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
    
    // ===== 新增：基地权限验证逻辑 =====
    validateBasePermissions($currentUser, $package, $fromRack, $targetRack, $transactionType);
    
    // 检查是否为跨基地操作，在备注中添加标识
    if ($fromRack && $fromRack['base_id'] !== $targetRack['base_id']) {
        $baseFromName = $fromRack['base_name'] ?? '未知基地';
        $baseToName = $targetRack['base_name'];
        
        // 根据目标区域类型判断是否为基地间转移
        if ($targetRack['area_type'] === 'temporary') {
            $notes = "[基地间转移] {$baseFromName} → {$baseToName}" . ($notes ? " | {$notes}" : '');
        } else {
            $notes = "[基地间流转] {$baseFromName} → {$baseToName}" . ($notes ? " | {$notes}" : '');
        }
    } else if ($targetRack['area_type'] === 'temporary' && $transactionType === 'purchase_in') {
        // 采购入库到临时区的标识
        $notes = "[采购入库]" . ($notes ? " | {$notes}" : '');
    }
    
    // 验证交易类型
    validateTransactionType($transactionType, $package, $fromRack, $targetRack, $quantity);
    
    // 根据交易类型执行相应操作
    switch ($transactionType) {
        case 'purchase_in':
            return processPurchaseIn($package, $targetRack, $notes, $currentUser);
        case 'usage_out':
            return processUsageOut($package, $targetRack, $quantity, $scrapReason, $notes, $currentUser);
        case 'return_in':
            return processReturnIn($package, $targetRack, $quantity, $notes, $currentUser);
        case 'scrap':
            return processScrap($package, $targetRack, $quantity, $scrapReason, $notes, $currentUser);
        case 'check_in':
        case 'check_out':
            return processInventoryCheck($transactionType, $package, $targetRack, $quantity, $notes, $currentUser);
        case 'location_adjust':
            // 库位调整逻辑
            $sql = "INSERT INTO inventory_transactions (package_id, transaction_type, from_rack_id, to_rack_id, 
                    quantity, actual_usage, notes, operator_id, transaction_time) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            query($sql, [
                $package['id'],
                'location_adjust',
                $package['current_rack_id'],
                $targetRack['id'],
                $quantity,
                0, // 库位调整不涉及实际使用量
                $notes,
                $currentUser['id']
            ]);

            // 更新包的当前库位
            $sql = "UPDATE glass_packages SET current_rack_id = ?, updated_at = ? WHERE id = ?";
            query($sql, [$targetRack['id'], date('Y-m-d H:i:s'), $package['id']]);
            
            // 移除原库位中的包并重新整理顺序
            if ($package['current_rack_id']) {
                removePackageAndReorder($package['current_rack_id'], $package['id']);
            }
            
            // 为包在新库位中分配位置顺序号
            assignPackagePosition($package['id'], $targetRack['id']);
            return '库位调整操作成功完成！';
        default:
            throw new Exception('不支持的交易类型：' . $transactionType);
    }
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

function processPurchaseIn($package, $targetRack, $notes, $currentUser)
{
    // 采购入库：整包入库
    $sql = "INSERT INTO inventory_transactions (package_id, transaction_type, from_rack_id, to_rack_id, 
            quantity, actual_usage, notes, operator_id, transaction_time) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    query($sql, [
        $package['id'],
        'purchase_in',
        $package['current_rack_id'],
        $targetRack['id'],
        $package['pieces'], // 整包数量
        0, // 采购入库不消耗，actual_usage为0
        $notes, // 添加 notes 参数
        $currentUser['id']
    ]);

    // 更新包状态和位置
    $sql = "UPDATE glass_packages SET current_rack_id = ?, status = 'in_storage', updated_at = ? WHERE id = ?";
    query($sql, [$targetRack['id'], date('Y-m-d H:i:s'), $package['id']]);

    return '采购入库操作成功完成！';
}

function processUsageOut($package, $targetRack, $quantity, $scrapReason, $notes, $currentUser)
{
    // 处理"全部用完"逻辑：数量为0表示完全使用
    if ($quantity == 0) {
        // 完全使用 - 类似部分领用但使用全部片数
        $quantity = $package['pieces']; // 使用包的全部片数
        
        // 插入交易记录
        $sql = "INSERT INTO inventory_transactions (package_id, transaction_type, from_rack_id, to_rack_id, 
                quantity, actual_usage, notes, operator_id, transaction_time) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        query($sql, [
            $package['id'],
            'partial_usage',
            $package['current_rack_id'],
            $targetRack['id'],
            $quantity,
            $quantity, // 实际使用量等于领用量
            $notes . ' (完全使用)',
            $currentUser['id']
        ]);

        // 使用原子操作更新片数，将片数设为0
        $sql = "UPDATE glass_packages SET pieces = 0, status = 'used_up', current_rack_id = ?, updated_at = ? 
                WHERE id = ? AND pieces >= ?";
        $affectedRows = query($sql, [
            $targetRack['id'],
            date('Y-m-d H:i:s'),
            $package['id'],
            $quantity
        ]);

        if ($affectedRows === 0) {
            throw new Exception('更新失败：包的片数不足或包不存在');
        }

        return '完全使用操作成功完成！该包已标记为已用完状态。';
    }
    
    if ($quantity == $package['pieces']) {
        // 整包领用出库
        if ($targetRack['area_type'] === 'scrap') {
            // 直接报废
            $sql = "INSERT INTO inventory_transactions (package_id, transaction_type, from_rack_id, to_rack_id, 
                    quantity, actual_usage, scrap_reason, notes, operator_id, transaction_time) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            query($sql, [
                $package['id'],
                'usage_out',
                $package['current_rack_id'],
                $targetRack['id'],
                $quantity,
                $quantity, // 整包报废，actual_usage等于quantity
                $scrapReason,
                $notes, // 添加 notes 参数
                $currentUser['id']
            ]);

            // 更新包状态为报废，片数为0
            $sql = "UPDATE glass_packages SET current_rack_id = ?, status = 'scrapped', pieces = 0, updated_at = ? WHERE id = ?";
            query($sql, [$targetRack['id'], date('Y-m-d H:i:s'), $package['id']]);

            return '整包报废操作成功完成！';
        } else {
            // 正常领用出库到加工区
            $sql = "INSERT INTO inventory_transactions (package_id, transaction_type, from_rack_id, to_rack_id, 
                    quantity, actual_usage, notes, operator_id, transaction_time) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            query($sql, [
                $package['id'],
                'usage_out',
                $package['current_rack_id'],
                $targetRack['id'],
                $quantity,
                0, // 领用到加工区，actual_usage暂时为0，等归还时确定
                $notes, // 添加 notes 参数
                $currentUser['id']
            ]);

            // 更新包状态为加工中
            $sql = "UPDATE glass_packages SET current_rack_id = ?, status = 'in_processing', updated_at = ? WHERE id = ?";
            query($sql, [$targetRack['id'], date('Y-m-d H:i:s'), $package['id']]);

            return '整包领用出库操作成功完成！';
        }
    } else {
        // 部分领用出库 - 使用原子操作
        // 1. 先检查片数是否足够（在事务中）
        $currentPieces = fetchOne(
            "SELECT pieces FROM glass_packages WHERE id = ?",
            [$package['id']]
        );

        if ($currentPieces < $quantity) {
            throw new Exception('当前包的片数不足，无法完成领用操作');
        }

        // 2. 插入交易记录
        $sql = "INSERT INTO inventory_transactions (package_id, transaction_type, from_rack_id, to_rack_id, 
                quantity, actual_usage, notes, operator_id, transaction_time) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        query($sql, [
            $package['id'],
            'partial_usage',
            $package['current_rack_id'],
            $targetRack['id'],
            $quantity,
            $quantity, // 部分领用，actual_usage等于领用量
            $notes, // 添加 notes 参数
            $currentUser['id']
        ]);

        // 3. 使用原子操作更新片数，同时检查约束
        $sql = "UPDATE glass_packages SET pieces = pieces - ?, updated_at = ? 
                WHERE id = ? AND pieces >= ?";
        $affectedRows = query($sql, [
            $quantity,
            date('Y-m-d H:i:s'),
            $package['id'],
            $quantity
        ]);

        if ($affectedRows === 0) {
            throw new Exception('更新失败：包的片数不足或包不存在');
        }

        // 4. 获取更新后的片数
        $remainingPieces = fetchOne(
            "SELECT pieces FROM glass_packages WHERE id = ?",
            [$package['id']]
        );

        return '部分领用出库操作成功完成！剩余片数：' . $remainingPieces;
    }
}

function processReturnIn($package, $targetRack, $quantity, $notes, $currentUser)
{
    if ($quantity == 0) {
        // 归还数量为0，表示完全使用
        $actualUsage = $package['pieces']; // 实际使用量等于原有片数
        
        $sql = "INSERT INTO inventory_transactions (package_id, transaction_type, from_rack_id, to_rack_id, 
                quantity, actual_usage, notes, operator_id, transaction_time) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        query($sql, [
            $package['id'],
            'return_in',
            $package['current_rack_id'],
            $targetRack['id'],
            0, // 归还数量为0
            $actualUsage, // 实际使用量
            $notes . ' (完全使用)',
            $currentUser['id']
        ]);

        // 更新包状态为已用完，片数为0
        $sql = "UPDATE glass_packages SET current_rack_id = ?, status = 'used_up', pieces = 0, updated_at = ? WHERE id = ?";
        query($sql, [$targetRack['id'], date('Y-m-d H:i:s'), $package['id']]);

        return '归还入库操作成功完成！该包已完全使用。';
    } else {
        // 正常归还逻辑
        $sql = "INSERT INTO inventory_transactions (package_id, transaction_type, from_rack_id, to_rack_id, 
                quantity, actual_usage, notes, operator_id, transaction_time) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        query($sql, [
            $package['id'],
            'return_in',
            $package['current_rack_id'],
            $targetRack['id'],
            $quantity,
            $package['pieces'] - $quantity, // 实际领用量
            $notes,
            $currentUser['id']
        ]);

        // 更新包状态为库存中，更新剩余片数
        $sql = "UPDATE glass_packages SET current_rack_id = ?, status = 'in_storage', pieces = ?, updated_at = ? WHERE id = ?";
        query($sql, [$targetRack['id'], $quantity, date('Y-m-d H:i:s'), $package['id']]);

        return '归还入库操作成功完成！';
    }
}

function processScrap($package, $targetRack, $quantity, $scrapReason, $currentUser)
{
    // 报废操作
    $sql = "INSERT INTO inventory_transactions (package_id, transaction_type, from_rack_id, to_rack_id, 
            quantity, actual_usage, scrap_reason, operator_id, transaction_time) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    query($sql, [
        $package['id'],
        'scrap',
        $package['current_rack_id'],
        $targetRack['id'],
        $quantity,
        $quantity, // 报废操作，actual_usage等于报废量
        $scrapReason,
        $currentUser['id']
    ]);

    if ($quantity == $package['pieces']) {
        // 整包报废 - 修改状态为used_up而不是scrapped
        $sql = "UPDATE glass_packages SET current_rack_id = ?, status = 'used_up', pieces = 0, updated_at = ? WHERE id = ?";
        query($sql, [$targetRack['id'], date('Y-m-d H:i:s'), $package['id']]);
        return '整包报废操作成功完成！该包已完全使用。';
    } else {
        // 部分报废
        $remainingPieces = $package['pieces'] - $quantity;
        $sql = "UPDATE glass_packages SET pieces = ?, updated_at = ? WHERE id = ?";
        query($sql, [$remainingPieces, date('Y-m-d H:i:s'), $package['id']]);
        return '部分报废操作成功完成！剩余片数：' . $remainingPieces;
    }
}

function processInventoryCheck($transactionType, $package, $targetRack, $quantity, $notes, $currentUser)
{
    // 盘点操作
    $sql = "INSERT INTO inventory_transactions (package_id, transaction_type, from_rack_id, to_rack_id, 
            quantity, actual_usage, notes, operator_id, transaction_time) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    query($sql, [
        $package['id'],
        $transactionType,
        $package['current_rack_id'],
        $targetRack['id'],
        $quantity,
        $quantity, // 修复：无论盘盈还是盘亏，actual_usage都应该是变化的数量
        $notes,
        $currentUser['id']
    ]);

    // 更新包的片数和位置
    if ($transactionType === 'check_in') {
        $newPieces = $package['pieces'] + $quantity;
        $message = '盘盈入库操作成功完成！新增片数：' . $quantity;
    } else {
        $newPieces = $package['pieces'] - $quantity;
        $message = '盘亏出库操作成功完成！减少片数：' . $quantity;
    }

    $sql = "UPDATE glass_packages SET current_rack_id = ?, pieces = ?, updated_at = ? WHERE id = ?";
    query($sql, [$targetRack['id'], $newPieces, date('Y-m-d H:i:s'), $package['id']]);

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
 * @param bool $reorder 是否重新整理所有包的顺序
 * @return int 分配的位置顺序号
 */
function assignPackagePosition($packageId, $rackId, $reorder = false) {
    if ($reorder) {
        // 先重新整理现有包的顺序
        reorderPackagePositions($rackId);
    }
    
    // 获取下一个可用位置
    $positionOrder = getNextPositionOrder($rackId);
    
    // 更新包的位置顺序号
    $sql = "UPDATE glass_packages SET position_order = ?, updated_at = ? WHERE id = ?";
    query($sql, [$positionOrder, date('Y-m-d H:i:s'), $packageId]);
    
    return $positionOrder;
}

/**
 * 移除包时重新整理位置顺序号
 * @param int $rackId 库位架ID
 * @param int $removedPackageId 被移除的包ID
 * @return bool 操作是否成功
 */
function removePackageAndReorder($rackId, $removedPackageId) {
    if (empty($rackId)) {
        return false;
    }
    
    // 先将被移除的包的位置设为NULL或0
    $sql = "UPDATE glass_packages SET position_order = NULL WHERE id = ?";
    query($sql, [$removedPackageId]);
    
    // 重新整理剩余包的位置顺序
    return reorderPackagePositions($rackId);
}

/**
 * 验证基地权限
 * @param array $currentUser 当前用户信息
 * @param array $package 包信息
 * @param array|null $fromRack 来源库位信息
 * @param array $targetRack 目标库位信息
 * @param string $transactionType 交易类型
 * @throws Exception 权限不足时抛出异常
 */
function validateBasePermissions($currentUser, $package, $fromRack, $targetRack, $transactionType) {
    $userRole = $currentUser['role'];
    $userBaseId = $currentUser['base_id'];
    
    // 管理员拥有所有权限，跳过检查
    if ($userRole === 'admin') {
        return;
    }
    
    // 获取包当前所在基地ID
    $packageCurrentBaseId = $package['current_base_id'];
    $targetBaseId = $targetRack['base_id'];
    
    // 检查用户是否有权限操作当前包
    if ($packageCurrentBaseId && $packageCurrentBaseId != $userBaseId) {
        throw new Exception('权限不足：您只能操作本基地内的原片包');
    }
    
    // 检查目标库位权限
    if ($userRole === 'operator' || $userRole === 'viewer') {
        // 操作员和查看者只能在本基地内操作
        if ($targetBaseId != $userBaseId) {
            throw new Exception('权限不足：您只能将原片包转移到本基地内的库位');
        }
    } else if ($userRole === 'manager') {
        // 库管可以进行跨基地转移，但只能转移到其他基地的临时区
        if ($targetBaseId != $userBaseId) {
            // 跨基地操作，检查目标是否为临时区
            if ($targetRack['area_type'] !== 'temporary') {
                throw new Exception('权限不足：跨基地转移只能将原片包转移到目标基地的临时区');
            }
            
            // 检查是否为支持的跨基地操作类型
            $allowedCrossBaseTypes = ['location_adjust', 'purchase_in'];
            if (!in_array($transactionType, $allowedCrossBaseTypes)) {
                throw new Exception('权限不足：不支持的跨基地操作类型');
            }
        }
    }
    
    // 特殊检查：从临时区转移到库存区只能由目标基地的库管执行
    if ($fromRack && $fromRack['area_type'] === 'temporary' && 
        $targetRack['area_type'] === 'storage' && 
        $targetBaseId != $userBaseId) {
        throw new Exception('权限不足：只有目标基地的库管才能将临时区的原片包转移到库存区');
    }
}