<?php
/**
 * 库位信息获取API
 * 为安卓APP提供库位架信息查询接口
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// 设置响应头为JSON格式
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 验证令牌并获取用户信息
$token = getBearerToken();
$userId = validateApiToken($token);
if (!$userId) {
    sendResponse(401, '认证失败');
    return;
}

// 获取用户信息（包含base_id）
$user = fetchRow("SELECT id, username, real_name as name, role, base_id FROM users WHERE id = ?", [$userId]);
if (!$user) {
    sendResponse(404, '用户不存在');
    return;
}

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

// API路由
switch ($method) {
    case 'GET':
        handleGetRacks();
        break;
    default:
        sendResponse(405, '方法不允许');
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
    $areaType = isset($_GET['area_type']) ? $_GET['area_type'] : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, intval($_GET['page_size']))) : 20;
    
    // 如果没有提供base_id，则使用当前用户的base_id
    if (!$baseId && $user && isset($user['base_id'])) {
        $baseId = intval($user['base_id']);
    }
    
    // 如果提供了rack_name参数，则必须提供base_id
    if ($rackName && !$baseId) {
        sendResponse(400, '使用库位架名称查询时必须提供base_id参数');
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
    
    sendResponse(200, '获取成功', [
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
 * 从请求头获取Bearer令牌
 */
function getBearerToken() {
    $headers = null;
    
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER['Authorization']);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    
    if ($headers && preg_match('/Bearer\s(.*)/', $headers, $matches)) {
        return $matches[1];
    }
    
    return null;
}

/**
 * 验证API令牌（简化版）
 */
function validateApiToken($token) {
    try {
        $tokenData = json_decode(base64_decode($token), true);
        
        if (!$tokenData || !isset($tokenData['user_id']) || !isset($tokenData['expires_at'])) {
            return false;
        }
        
        // 检查是否过期
        if (time() > $tokenData['expires_at']) {
            return false;
        }
        
        return $tokenData['user_id'];
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 发送JSON响应
 */
function sendResponse($code, $message, $data = null) {
    http_response_code($code);
    
    $response = [
        'code' => $code,
        'message' => $message,
        'timestamp' => time()
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}
?>