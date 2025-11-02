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
        $targetRackCode = trim($_GET['target_rack_code'] ?? '');
        $currentAreaType = $_GET['current_area_type'] ?? '';
        $baseName = $_GET['base_name'] ?? '';
        
        if (empty($targetRackCode)) {
            ApiCommon::sendResponse(400, '目标架号不能为空');
        }
        
        $result = getTargetRackInfo($targetRackCode, $currentAreaType, $baseName);
        
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

// 默认响应
ApiCommon::sendResponse(400, '无效的API请求');
?>