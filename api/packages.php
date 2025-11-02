<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once 'ApiCommon.php';

// 设置响应头和处理预检请求
ApiCommon::setHeaders();
ApiCommon::handlePreflight();

// 验证Token认证
$currentUser = ApiCommon::authenticate();

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

// API路由
switch ($method) {
    case 'GET':
        handleGetPackages();
        break;
    default:
        ApiCommon::sendResponse(405, '方法不允许');
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
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;

    // 获取分页参数
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $pageSize = isset($_GET['page_size']) ? min(100, max(1, intval($_GET['page_size']))) : 20;
    $offset = ($page - 1) * $pageSize;

    // 构建查询条件
    $conditions = [];
    $params = [];

    if ($packageCode) {
        $conditions[] = "p.package_code LIKE ?";
        $params[] = "%$packageCode%";
    }

    if ($glassTypeId) {
        $conditions[] = "p.glass_type_id = ?";
        $params[] = $glassTypeId;
    }

    if ($rackId) {
        $conditions[] = "p.current_rack_id = ?";
        $params[] = $rackId;
    }

    if ($baseId) {
        $conditions[] = "r.base_id = ?";
        $params[] = $baseId;
    }

    if ($status) {
        $conditions[] = "p.status = ?";
        $params[] = $status;
    }

    // 构建SQL查询
    $whereClause = '';
    if (!empty($conditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
    }

    $sql = "
        SELECT 
            p.id,
            p.package_code,
            p.width,
            p.height,
            p.pieces,
            p.quantity,
            p.entry_date,
            p.position_order,
            p.status,
            p.created_at,
            p.updated_at,
            
            gt.id as glass_type_id,
            gt.custom_id as glass_type_custom_id,
            gt.name as glass_type_name,
            gt.short_name as glass_type_short_name,
            gt.brand as glass_type_brand,
            gt.manufacturer as glass_type_manufacturer,
            gt.color as glass_type_color,
            gt.thickness as glass_type_thickness,
            gt.silver_layers as glass_type_silver_layers,
            gt.substrate as glass_type_substrate,
            gt.transmittance as glass_type_transmittance,
            
            r.id as rack_id,
            r.code as rack_code,
            r.name as rack_name,
            r.area_type as rack_area_type,
            
            b.id as base_id,
            b.name as base_name
            
        FROM glass_packages p
        LEFT JOIN glass_types gt ON p.glass_type_id = gt.id
        LEFT JOIN storage_racks r ON p.current_rack_id = r.id
        LEFT JOIN bases b ON r.base_id = b.id
        $whereClause
        ORDER BY p.created_at DESC
        LIMIT $offset, $pageSize
    ";

    try {
        $packages = fetchAll($sql, $params);
        
        // 获取总记录数用于分页
        $countSql = "SELECT COUNT(*) as total 
                     FROM glass_packages p
                     LEFT JOIN storage_racks r ON p.current_rack_id = r.id
                     $whereClause";
        $totalResult = fetchRow($countSql, $params);
        $totalRecords = $totalResult['total'];
        $totalPages = ceil($totalRecords / $pageSize);

        // 格式化响应数据
        $formattedPackages = [];
        foreach ($packages as $package) {
            $formattedPackages[] = [
                'id' => intval($package['id']),
                'package_code' => $package['package_code'],
                'dimensions' => [
                    'width' => floatval($package['width']),
                    'height' => floatval($package['height'])
                ],
                'quantity' => [
                    'pieces' => intval($package['pieces']),
                    'quantity' => intval($package['quantity'])
                ],
                'entry_date' => $package['entry_date'],
                'position_order' => intval($package['position_order']),
                'glass_type' => [
                    'id' => intval($package['glass_type_id']),
                    'custom_id' => $package['glass_type_custom_id'],
                    'name' => $package['glass_type_name'],
                    'short_name' => $package['glass_type_short_name'],
                    'brand' => $package['glass_type_brand'],
                    'manufacturer' => $package['glass_type_manufacturer'],
                    'color' => $package['glass_type_color'],
                    'thickness' => floatval($package['glass_type_thickness']),
                    'silver_layers' => $package['glass_type_silver_layers'],
                    'substrate' => $package['glass_type_substrate'],
                    'transmittance' => $package['glass_type_transmittance']
                ],
                'rack_info' => [
                    'id' => intval($package['rack_id']),
                    'code' => $package['rack_code'],
                    'name' => $package['rack_name'],
                    'area_type' => $package['rack_area_type'],
                    'base_id' => intval($package['base_id']),
                    'base_name' => $package['base_name']
                ],
                'status' => $package['status'],
                'status_name' => getStatusName($package['status']),
                'created_at' => $package['created_at'],
                'updated_at' => $package['updated_at']
            ];
        }
        
        ApiCommon::sendResponse(200, '获取成功', [
            'packages' => $formattedPackages,
            'pagination' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $totalRecords,
                'total_pages' => $totalPages
            ]
        ]);
        
    } catch (Exception $e) {
        ApiCommon::sendResponse(500, '服务器错误: ' . $e->getMessage());
    }
}

/**
 * 获取状态名称
 */
function getStatusName($status) {
    $statusMap = [
        'in_storage' => '库存中',
        'in_processing' => '加工中',
        'scrapped' => '已报废',
        'used_up' => '已用完'
    ];
    
    return $statusMap[$status] ?? '未知状态';
}

?>