<?php
/**
 * 移动设备扫描操作API
 * 为移动设备提供扫描操作功能，移植自mobile/scan.php
 * 
 * 主要功能：
 * 1. 获取包信息
 * 2. 获取目标库位信息并判断操作类型
 * 3. 执行库存流转操作
 * 
 * 认证方式：Bearer Token认证
 */

require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/inventory_operations.php';
require_once 'ApiCommon.php';

// 设置响应头和处理预检请求
ApiCommon::setHeaders();
ApiCommon::handlePreflight();

// 验证Token认证
$currentUser = ApiCommon::authenticate();

/**
 * 获取包信息接口
 * GET /api/scan.php?action=get_package_info&package_code=YP20240001
 */
if (isset($_GET['action']) && $_GET['action'] === 'get_package_info') {
    try {
        $packageCode = trim($_GET['package_code'] ?? '');
        
        if (empty($packageCode)) {
            ApiCommon::sendResponse(400, '包号不能为空');
        }
        
        $result = getPackageInfo($packageCode);
        
        if ($result['success']) {
            ApiCommon::sendResponse(200, '获取成功', $result['data']);
        } else {
            ApiCommon::sendResponse(404, $result['message']);
        }
        
    } catch (Exception $e) {
        ApiCommon::sendResponse(500, '服务器错误: ' . $e->getMessage());
    }
}

/**
 * 获取目标库位信息接口
 * GET /api/scan.php?action=get_target_info&target_rack_code=R001&current_area_type=storage&base_name=总部基地
 */
if (isset($_GET['action']) && $_GET['action'] === 'get_target_info') {
    try {
        $targetNO = trim($_GET['target_rack_code'] ?? '');
        $currentAreaType = $_GET['current_area_type'] ?? '';
        $baseName = $_GET['base_name'] ?? '';
        
        if (empty($targetNO)) {
            ApiCommon::sendResponse(400, '目标架号不能为空');
        }
        
        if (strpos($targetNO, '-') !== false) {
            $result = getTargetRackInfo($targetNO, $currentAreaType, $baseName);
        } else {
            $result = getTargetRackInfoByName($targetNO, $currentAreaType, $currentUser['base_id']);
        }
        
        if ($result['success']) {
            ApiCommon::sendResponse(200, '获取成功', $result['data']);
        } else {
            ApiCommon::sendResponse(404, $result['message']);
        }
        
    } catch (Exception $e) {
        ApiCommon::sendResponse(500, '服务器错误: ' . $e->getMessage());
    }
}

/**
 * 执行库存流转操作接口
 * POST /api/scan.php
 * Content-Type: application/json
 * 
 * 请求体示例：
 * {
 *   "package_code": "YP20240001",
 *   "target_rack_code": "R001",
 *   "quantity": 100,
 *   "transaction_type": "usage_out",
 *   "scrap_reason": "",
 *   "notes": "领用出库"
 * }
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_GET['action']) || $_GET['action'] === 'execute_transaction')) {
    try {
        // 获取请求数据
        $input = getPostData();
        
        // 验证必填字段
        $requiredFields = ['package_code', 'target_rack_code', 'quantity', 'transaction_type'];
        validateRequiredFields($input, $requiredFields);
        
        $packageCode = trim($input['package_code'] ?? '');
        $targetRackCode = trim($input['target_rack_code'] ?? '');
        $quantity = intval($input['quantity'] ?? 0);
        $transactionType = $input['transaction_type'] ?? '';
        $scrapReason = trim($input['scrap_reason'] ?? '');
        $notes = trim($input['notes'] ?? '');
        $allUse = isset($input['all_use']) ? boolval($input['all_use']) : false;
        
        // 处理"全部用完"逻辑
        if ($allUse) {
            // 勾选全部用完时，数量设为0，表示完全使用
            $quantity = 0;
        }
        
        // 验证数据
        if (empty($packageCode) || empty($targetRackCode) || $quantity < 0 || empty($transactionType)) {
            ApiCommon::sendResponse(400, '请填写所有必填字段');
        }
        
        if ($transactionType === 'scrap' && empty($scrapReason)) {
            ApiCommon::sendResponse(400, '报废操作必须填写报废原因');
        }
        
        // 执行库存流转操作
        $result = executeInventoryTransaction(
            $packageCode,
            $targetRackCode,
            $quantity,
            $transactionType,
            $currentUser,
            $scrapReason,
            $notes
        );
        
        ApiCommon::sendResponse(200, $result, [
            'package_code' => $packageCode,
            'target_rack_code' => $targetRackCode,
            'quantity' => $quantity,
            'transaction_type' => $transactionType,
            'operator' => $currentUser['real_name'] ?? $currentUser['username']
        ]);
        
    } catch (Exception $e) {
        ApiCommon::sendResponse(500, $e->getMessage());
    }
}

/**
 * 跨基地转移接口（简化版 - 只需包号和目标基地）
 * POST /api/scan.php?action=location_adjust
 * Content-Type: application/json
 * 
 * 请求体示例：
 * {
 *   "package_code": "YP20240001",
 *   "target_base_id": 2
 * }
 */
if (isset($_GET['action']) && $_GET['action'] === 'location_adjust') {
    try {
        // 获取请求数据
        $input = getPostData();
        
        // 验证必填字段
        $requiredFields = ['package_code', 'target_base_id'];
        validateRequiredFields($input, $requiredFields);
        
        $packageCode = trim($input['package_code'] ?? '');
        $targetBaseId = intval($input['target_base_id'] ?? 0);
        
        // 验证数据
        if (empty($packageCode) || empty($targetBaseId)) {
            ApiCommon::sendResponse(400, '请填写包号和目标基地');
        }
        
        // 验证用户权限（只有管理员可以进行跨基地转移）
        if ($currentUser['role'] !== 'manager') {
            ApiCommon::sendResponse(403, '只有库管可以进行跨基地转移操作');
        }
        
        // 获取包信息
        $packageResult = getPackageInfo($packageCode);
        if (!$packageResult['success']) {
            ApiCommon::sendResponse(404, $packageResult['message']);
        }
        
        $packageInfo = $packageResult['data'];
        $currentPieces = $packageInfo['pieces'];
        
        // 获取当前包的基地信息
        $currentBaseId = $packageInfo['base_id'] ?? null;
        $currentBaseName = $packageInfo['base_name'] ?? '未知基地';
        $currentRackId = $packageInfo['current_rack_id'] ?? null;
        
        // 验证是否为跨基地操作
        if ($currentBaseId == $targetBaseId) {
            ApiCommon::sendResponse(400, '目标基地与当前基地相同，无需跨基地转移');
        }
        
        // 获取目标基地信息
        $targetBaseQuery = fetchOne("SELECT name FROM bases WHERE id = ?", [$targetBaseId]);
        if (!$targetBaseQuery) {
            ApiCommon::sendResponse(404, '目标基地不存在');
        }
        $targetBaseName = $targetBaseQuery;
        
        // 自动获取目标基地的临时库位信息
        $targetRackQuery = fetchRow("SELECT * FROM storage_racks WHERE base_id = ? AND area_type = 'temporary' ORDER BY id LIMIT 1", 
            [$targetBaseId]);
        if (!$targetRackQuery) {
            ApiCommon::sendResponse(404, "目标基地（{$targetBaseName}）没有可用的临时库位");
        }
        $targetTempRackCode = $targetRackQuery['code'];
        
        // 构建备注信息
        $notes = "从{$currentBaseName}转来";
        
        // 执行跨基地转移操作
        $result = executeInventoryTransaction(
            $packageCode,
            $targetTempRackCode,
            $currentPieces,  // 使用当前片数
            'location_adjust',
            $currentUser,
            '',  // 报废原因
            $notes,
            true  // 允许跨基地操作
        );
        
        ApiCommon::sendResponse(200, $result, [
            'package_code' => $packageCode,
            'current_pieces' => $currentPieces,
            'from_base' => [
                'id' => $currentBaseId,
                'name' => $currentBaseName
            ],
            'to_base' => [
                'id' => $targetBaseId,
                'name' => $targetBaseName
            ],
            'target_temp_rack' => [
                'id' => $targetRackQuery['id'],
                'code' => $targetTempRackCode,
                'name' => $targetRackQuery['name']
            ],
            'transaction_type' => 'location_adjust',
            'operator' => $currentUser['real_name'] ?? $currentUser['username']
        ]);
        
    } catch (Exception $e) {
        ApiCommon::sendResponse(500, $e->getMessage());
    }
}

/**
 * 获取基地列表
 * GET /api/scan.php?action=get_bases
 */
if (isset($_GET['action']) && $_GET['action'] === 'get_bases') {
    try {
        // 其他用户只能查看自己所属的基地
        if (!empty($currentUser['base_id'])) {
            $bases = fetchAll("SELECT id, name, code,address FROM bases  ORDER BY name");
        } else {
            $bases = [];
        }
        
        // 格式化为下拉选择格式
        $formattedBases = [];
        foreach ($bases as $base) {
            $formattedBases[] = [
                'id' => $base['id'],
                'name' => $base['name'],
                'code' => $base['code'] ?? '',
                'address' => $base['address'],
            ];
        }
        
        ApiCommon::sendResponse(200, '获取成功', $formattedBases);
        
    } catch (Exception $e) {
        ApiCommon::sendResponse(500, '服务器错误: ' . $e->getMessage());
    }
}  
// 默认响应
ApiCommon::sendResponse(400, '无效的API请求');
?>