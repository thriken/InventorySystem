<?php
/**
 * 包信息API
 * 为安卓APP提供原片包信息查询接口
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

// 验证令牌
$token = getBearerToken();
if (!$token || !validateApiToken($token)) {
    sendResponse(401, '认证失败');
    return;
}

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

// API路由
switch ($method) {
    case 'GET':
        handleGetPackages();
        break;
    default:
        sendResponse(405, '方法不允许');
        break;
}

/**
 * 获取原片包信息
 */
function handleGetPackages() {
    // 获取查询参数
    $packageCode = isset($_GET['package_code']) ? trim($_GET['package_code']) : null;
    $glassTypeId = isset($_GET['glass_type_id']) ? intval($_GET['glass_type_id']) : null;
    $rackId = isset($_GET['rack_id']) ? intval($_GET['rack_id']) : null;
    $baseId = isset($_GET['base_id']) ? intval($_GET['base_id']) : null;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, intval($_GET['page_size']))) : 20;
    
    // 构建查询条件
    $where = [];
    $params = [];
    
    if ($packageCode) {
        $where[] = "gp.package_code LIKE ?";
        $params[] = "%{$packageCode}%";
    }
    
    if ($glassTypeId) {
        $where[] = "gp.glass_type_id = ?";
        $params[] = $glassTypeId;
    }
    
    if ($rackId) {
        $where[] = "gp.current_rack_id = ?";
        $params[] = $rackId;
    }
    
    if ($baseId) {
        $where[] = "sr.base_id = ?";
        $params[] = $baseId;
    }
    
    if ($status && in_array($status, ['in_storage', 'in_processing', 'scrapped', 'used_up'])) {
        $where[] = "gp.status = ?";
        $params[] = $status;
    }
    
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // 获取总数
    $countSql = "
        SELECT COUNT(*) as total 
        FROM glass_packages gp
        LEFT JOIN storage_racks sr ON gp.current_rack_id = sr.id
        $whereClause
    ";
    $total = fetchOne($countSql, $params);
    
    // 计算分页
    $offset = ($page - 1) * $pageSize;
    
    // 获取包列表
    $sql = "
        SELECT 
            gp.id,
            gp.package_code,
            gp.glass_type_id,
            gt.custom_id as glass_type_custom_id,
            gt.name as glass_type_name,
            gt.short_name as glass_type_short_name,
            gt.brand as glass_brand,
            gt.manufacturer as glass_manufacturer,
            gt.color as glass_color,
            gt.thickness as glass_thickness,
            gt.silver_layers as glass_silver_layers,
            gt.substrate as glass_substrate,
            gt.transmittance as glass_transmittance,
            gp.width,
            gp.height,
            gp.pieces,
            gp.quantity,
            gp.entry_date,
            gp.current_rack_id,
            sr.code as rack_code,
            sr.name as rack_name,
            sr.base_id,
            b.name as base_name,
            gp.position_order,
            gp.status,
            CASE gp.status 
                WHEN 'in_storage' THEN '库存中'
                WHEN 'in_processing' THEN '加工中'
                WHEN 'scrapped' THEN '已报废'
                WHEN 'used_up' THEN '已用完'
                ELSE gp.status
            END as status_name,
            gp.created_at,
            gp.updated_at
        FROM glass_packages gp
        LEFT JOIN glass_types gt ON gp.glass_type_id = gt.id
        LEFT JOIN storage_racks sr ON gp.current_rack_id = sr.id
        LEFT JOIN bases b ON sr.base_id = b.id
        $whereClause
        ORDER BY gp.entry_date DESC, gp.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $pageSize;
    $params[] = $offset;
    
    $packages = fetchAll($sql, $params);
    
    // 格式化返回数据
    $formattedPackages = [];
    foreach ($packages as $package) {
        $formattedPackages[] = [
            'id' => intval($package['id']),
            'package_code' => $package['package_code'],
            'glass_type' => [
                'id' => intval($package['glass_type_id']),
                'custom_id' => $package['glass_type_custom_id'],
                'name' => $package['glass_type_name'],
                'short_name' => $package['glass_type_short_name'],
                'brand' => $package['glass_brand'],
                'manufacturer' => $package['glass_manufacturer'],
                'color' => $package['glass_color'],
                'thickness' => $package['glass_thickness'] ? floatval($package['glass_thickness']) : null,
                'silver_layers' => $package['glass_silver_layers'],
                'substrate' => $package['glass_substrate'],
                'transmittance' => $package['glass_transmittance']
            ],
            'dimensions' => [
                'width' => $package['width'] ? floatval($package['width']) : null,
                'height' => $package['height'] ? floatval($package['height']) : null
            ],
            'quantity' => [
                'pieces' => intval($package['pieces']),
                'quantity' => intval($package['quantity'])
            ],
            'entry_date' => $package['entry_date'],
            'rack_info' => $package['current_rack_id'] ? [
                'id' => intval($package['current_rack_id']),
                'code' => $package['rack_code'],
                'name' => $package['rack_name'],
                'base_id' => intval($package['base_id']),
                'base_name' => $package['base_name']
            ] : null,
            'position_order' => intval($package['position_order']),
            'status' => $package['status'],
            'status_name' => $package['status_name'],
            'created_at' => $package['created_at'],
            'updated_at' => $package['updated_at']
        ];
    }
    
    sendResponse(200, '获取成功', [
        'packages' => $formattedPackages,
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
        
        return true;
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