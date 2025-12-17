<?php
/**
 * 库位信息获取API
 * 为安卓APP提供库位架信息查询接口
 * 
 * 支持的接口：
 * 1. GET /api/racks.php - 获取库位列表（默认）
 * 2. GET /api/racks.php?action=get_rack_id&rack_code=xxx - 模糊查找库位（支持8A或XF-N-8A格式）
 * 3. GET /api/racks.php?action=get_rack_id&rack_name=xxx - 模糊查找库名称
 * 
 * 注意：get_rack_id接口支持模糊匹配，可能返回单个结果或多个匹配结果
 * 统一使用 ApiCommon 类进行认证、响应处理和工具函数调用
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../api/ApiCommon.php';

// 设置响应头和处理预检请求
ApiCommon::setHeaders();
ApiCommon::handlePreflight();

// 验证令牌并获取用户信息
$token = ApiCommon::getBearerToken();
$userId = ApiCommon::validateApiToken($token);
if (!$userId) {
    ApiCommon::sendResponse(401, '认证失败');
    return;
}

// 获取用户信息（包含base_id）
$user = fetchRow("SELECT id, username, real_name as name, role, base_id FROM users WHERE id = ?", [$userId]);
if (!$user) {
    ApiCommon::sendResponse(404, '用户不存在');
    return;
}

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

// API路由
switch ($method) {
    case 'GET':
        $action = $_GET['action'] ?? 'list';
        if ($action === 'get_rack_id') {
            handleGetRackId();
        } else {
            handleGetRacks();
        }
        break;
    default:
        ApiCommon::sendResponse(405, '方法不允许');
        break;
}

/**
 * 获取库位架信息
 */
function handleGetRacks() {
    global $user;
    
    // 获取查询参数
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    $baseId = isset($_GET['base_id']) ? intval($_GET['base_id']) : null;
    $rackName = isset($_GET['rack_name']) ? trim($_GET['rack_name']) : null;
    $areaType = $_GET['area_type'] ?? null;
    $status = $_GET['status'] ?? null;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, intval($_GET['page_size']))) : 20;
    
    // 如果没有提供base_id，则使用当前用户的base_id
    if (!$baseId && $user && isset($user['base_id'])) {
        $baseId = intval($user['base_id']);
    }
    
    // 如果提供了rack_name参数，则必须提供base_id
    if ($rackName && !$baseId) {
        ApiCommon::sendResponse(400, '使用库位架名称查询时必须提供base_id参数');
        return;
    }
    
    // 构建查询条件
    $where = [];
    $params = [];
    
    if ($id) {
        $where[] = "sr.id = ?";
        $params[] = $id;
    }
    
    if ($baseId) {
        $where[] = "sr.base_id = ?";
        $params[] = $baseId;
    }
    
    if ($areaType && in_array($areaType, ['storage', 'processing', 'scrap', 'temporary'])) {
        $where[] = "sr.area_type = ?";
        $params[] = $areaType;
    }
    
    if ($status && in_array($status, ['normal', 'maintenance', 'full'])) {
        $where[] = "sr.status = ?";
        $params[] = $status;
    }
    
    if ($rackName) {
        $where[] = "(sr.name LIKE ? OR sr.code LIKE ?)";
        $params[] = "%{$rackName}%";
        $params[] = "%{$rackName}%";
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // 获取总数
    $countSql = "SELECT COUNT(*) as total FROM storage_racks sr $whereClause";
    $total = fetchOne($countSql, $params);
    
    // 计算分页
    $offset = ($page - 1) * $pageSize;
    
    // 获取库位架列表
    $sql = "
        SELECT 
            sr.id,
            sr.base_id,
            b.name as base_name,
            sr.code,
            sr.name,
            sr.area_type,
            CASE sr.area_type 
                WHEN 'storage' THEN '库存区'
                WHEN 'processing' THEN '加工区'
                WHEN 'scrap' THEN '报废区'
                WHEN 'temporary' THEN '临时区'
                ELSE sr.area_type
            END as area_type_name,
            sr.capacity,
            sr.status,
            CASE sr.status 
                WHEN 'normal' THEN '正常'
                WHEN 'maintenance' THEN '维护中'
                WHEN 'full' THEN '已满'
                ELSE sr.status
            END as status_name,
            sr.created_at,
            sr.updated_at,
            (SELECT COUNT(*) FROM glass_packages gp WHERE gp.current_rack_id = sr.id AND gp.status = 'in_storage') as package_count,
            (SELECT COALESCE(SUM(gp.pieces), 0) FROM glass_packages gp WHERE gp.current_rack_id = sr.id AND gp.status = 'in_storage') as total_pieces
        FROM storage_racks sr
        LEFT JOIN bases b ON sr.base_id = b.id
        $whereClause
        ORDER BY sr.base_id, sr.code
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $pageSize;
    $params[] = $offset;
    
    $racks = fetchAll($sql, $params);
    
    // 格式化返回数据
    $formattedRacks = [];
    foreach ($racks as $rack) {
        $formattedRacks[] = [
            'id' => intval($rack['id']),
            'base_id' => intval($rack['base_id']),
            'base_name' => $rack['base_name'],
            'code' => $rack['code'],
            'name' => $rack['name'],
            'area_type' => $rack['area_type'],
            'area_type_name' => $rack['area_type_name'],
            'capacity' => $rack['capacity'] ? intval($rack['capacity']) : null,
            'status' => $rack['status'],
            'status_name' => $rack['status_name'],
            'package_count' => intval($rack['package_count']),
            'total_pieces' => intval($rack['total_pieces']),
            'created_at' => $rack['created_at'],
            'updated_at' => $rack['updated_at']
        ];
    }
    
    ApiCommon::sendResponse(200, '获取成功', [
        'racks' => $formattedRacks,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => intval($total),
            'total_pages' => ceil($total / $pageSize)
        ]
    ]);
}

/**
 * 根据库位编码或名称获取库位ID
 */
function handleGetRackId() {
    global $user;
    
    // 获取查询参数
    $rackCode = isset($_GET['rack_code']) ? trim($_GET['rack_code']) : null;
    $rackName = isset($_GET['rack_name']) ? trim($_GET['rack_name']) : null;
    $baseId = isset($_GET['base_id']) ? intval($_GET['base_id']) : null;
    
    // 必须提供rack_code或rack_name其中一个
    if (!$rackCode && !$rackName) {
        ApiCommon::sendResponse(400, '必须提供rack_code或rack_name参数');
        return;
    }
    
    // 如果没有提供base_id，则使用当前用户的base_id
    if (!$baseId && $user && isset($user['base_id'])) {
        $baseId = intval($user['base_id']);
    }
    
    // 构建查询条件
    $where = [];
    $params = [];
    
    if ($baseId) {
        $where[] = "base_id = ?";
        $params[] = $baseId;
    }
    
    if ($rackCode) {
        // 支持模糊匹配库位编码（如：8A 可以匹配 XF-N-8A）
        $where[] = "(code LIKE ? OR code LIKE ?)";
        $params[] = $rackCode;                    // 精确匹配（如：8A）
        $params[] = "%{$rackCode}";               // 模糊匹配（如：%8A% 可以匹配 XF-N-8A）
    }
    
    if ($rackName) {
        // 支持模糊匹配库名称
        $where[] = "name LIKE ?";
        $params[] = "%{$rackName}%";
    }
    
    if (empty($where)) {
        ApiCommon::sendResponse(400, '缺少查询条件');
        return;
    }
    
    // 构建SQL查询 - 限制返回10个结果，避免数据过多
    $sql = "SELECT id, code, name, base_id, area_type, status FROM storage_racks WHERE " . implode(' AND ', $where) . " LIMIT 10";
    
    try {
        $results = fetchAll($sql, $params);
        
        if (count($results) === 0) {
            ApiCommon::sendResponse(404, '未找到匹配的库位');
        } elseif (count($results) === 1) {
            // 只有一个结果，直接返回库位ID
            $result = $results[0];
            ApiCommon::sendResponse(200, '库位查找成功', [
                'rack_id' => intval($result['id']),
                'rack_code' => $result['code'],
                'rack_name' => $result['name'],
                'base_id' => intval($result['base_id']),
                'area_type' => $result['area_type'],
                'status' => $result['status'],
                'match_type' => 'exact'  // 单个结果标记为精确匹配
            ]);
        } else {
            // 多个结果，返回匹配列表供用户选择
            $formattedResults = [];
            foreach ($results as $result) {
                $formattedResults[] = [
                    'rack_id' => intval($result['id']),
                    'rack_code' => $result['code'],
                    'rack_name' => $result['name'],
                    'base_id' => intval($result['base_id']),
                    'area_type' => $result['area_type'],
                    'status' => $result['status']
                ];
            }
            
            ApiCommon::sendResponse(200, '找到多个匹配的库位，请选择', [
                'matches' => $formattedResults,
                'total_matches' => count($results),
                'match_type' => 'multiple'  // 标记为多匹配结果
            ]);
        }
    } catch (Exception $e) {
        ApiCommon::sendResponse(500, '查询失败：' . $e->getMessage());
    }
}