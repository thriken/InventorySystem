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
        $action = $_GET['action'] ?? null;
        if ($action === 'glass_type_summary') {
            handleGetGlassTypeSummary();
        } elseif ($action === 'get_dropdown_options') {
            handleGetDropdownOptions();
        } elseif ($action === 'fuzzy_search') {
            handleFuzzySearch();
        } else {
            handleGetPackages();
        }
        break;
    default:
        ApiCommon::sendResponse(405, '方法不允许');
        break;
}

/**
 * 获取原片包信息 - 基础查询方法
 * 作用：获取玻璃包的详细信息，支持常规筛选和分页
 * 用途：日常库存查看，保持向后兼容性
 */
function handleGetPackages() {
    global $currentUser;
    
    // 获取查询参数
    $packageCode = isset($_GET['package_code']) ? trim($_GET['package_code']) : null;
    $glassTypeId = isset($_GET['glass_type_id']) ? intval($_GET['glass_type_id']) : null;
    $rackId = isset($_GET['rack_id']) ? intval($_GET['rack_id']) : null;
    $baseId = isset($_GET['base_id']) ? intval($_GET['base_id']) : null;
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    
    // 如果没有指定base_all=true，且用户有基地权限，则默认只显示该用户基地的数据
    $baseAll = isset($_GET['base_all']) && filter_var($_GET['base_all'], FILTER_VALIDATE_BOOLEAN);
    if (!$baseAll && !$baseId && isset($currentUser['base_id']) && $currentUser['base_id']) {
        $baseId = intval($currentUser['base_id']);
    }

    // 获取分页参数
    $pagination = getPaginationParams(20, 100);

    // 构建查询条件
    $conditions = [];
    $params = [];

    if ($packageCode) {
        $conditions[] = "p.package_code LIKE ?";
        $params[] = "%{$packageCode}%";
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

    $whereClause = '';
    if (!empty($conditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
    }

    // 主查询SQL
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
        {$whereClause}
        ORDER BY p.created_at DESC
        LIMIT {$pagination['offset']}, {$pagination['pageSize']}
    ";

    try {
        $packages = fetchAll($sql, $params);

        // 获取总记录数
        $countSql = "
            SELECT COUNT(*) as total 
            FROM glass_packages p
            LEFT JOIN glass_types gt ON p.glass_type_id = gt.id
            LEFT JOIN storage_racks r ON p.current_rack_id = r.id
            LEFT JOIN bases b ON r.base_id = b.id
            {$whereClause}
        ";
        $totalResult = fetchRow($countSql, $params);
        $paginationInfo = buildPaginationInfo($totalResult['total'], $pagination['page'], $pagination['pageSize']);

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
            'pagination' => $paginationInfo
        ]);
        
    } catch (Exception $e) {
        ApiCommon::sendResponse(500, '服务器错误: ' . $e->getMessage());
    }
}

// ==================== 公共工具函数 ====================

/**
 * 构建基地过滤条件和JOIN
 * @param array $currentUser 当前用户信息
 * @param bool $baseAll 是否查询所有基地
 * @param string $packageAlias 包表别名
 * @param string $rackAlias 架表别名
 * @return array 包含baseJoin, baseWhere, inventoryJoin的数组
 */
function buildBaseFilters($currentUser, $baseAll, $packageAlias = 'p', $rackAlias = 'sr') {
    if (!$baseAll && isset($currentUser['base_id']) && $currentUser['base_id']) {
        $baseId = intval($currentUser['base_id']);
        return [
            'baseJoin' => "INNER JOIN glass_packages {$packageAlias} ON gt.id = {$packageAlias}.glass_type_id 
                          INNER JOIN storage_racks {$rackAlias} ON {$packageAlias}.current_rack_id = {$rackAlias}.id",
            'baseWhere' => "AND {$rackAlias}.base_id = {$baseId} AND {$packageAlias}.status = 'in_storage'",
            'inventoryJoin' => "INNER JOIN glass_packages p_inv ON gt.id = p_inv.glass_type_id 
                             INNER JOIN storage_racks sr_inv ON p_inv.current_rack_id = sr_inv.id 
                             WHERE sr_inv.base_id = {$baseId} 
                             AND p_inv.status = 'in_storage'",
            'packageFilter' => "{$packageAlias}.status = 'in_storage' AND {$rackAlias}.base_id = {$baseId}"
        ];
    } else {
        // base_all=true 时，仍然使用 INNER JOIN 来确保只显示有库存的原片类型
        // 但不限制基地范围，显示所有基地的库存
        return [
            'baseJoin' => "INNER JOIN glass_packages {$packageAlias} ON gt.id = {$packageAlias}.glass_type_id 
                          INNER JOIN storage_racks {$rackAlias} ON {$packageAlias}.current_rack_id = {$rackAlias}.id",
            'baseWhere' => "AND {$packageAlias}.status = 'in_storage'",
            'inventoryJoin' => "INNER JOIN glass_packages p_inv ON gt.id = p_inv.glass_type_id 
                             INNER JOIN storage_racks sr_inv ON p_inv.current_rack_id = sr_inv.id 
                             WHERE p_inv.status = 'in_storage'",
            'packageFilter' => "{$packageAlias}.status = 'in_storage'"
        ];
    }
}

/**
 * 构建玻璃类型选择器选项
 * @param array $glassTypes 玻璃类型数据数组
 * @return array 格式化后的选择器选项数组
 */
function buildGlassTypeOptions($glassTypes) {
    $options = [];
    foreach ($glassTypes as $glassType) {
        $options[] = [
            'id' => intval($glassType['id']),
            'custom_id' => $glassType['custom_id'],
            'name' => $glassType['name'],
            'short_name' => $glassType['short_name'],
            'display_name' => $glassType['name'] . ($glassType['custom_id'] ? " ({$glassType['custom_id']})" : ''),
            'brand' => $glassType['brand'],
            'manufacturer' => $glassType['manufacturer'],
            'color' => $glassType['color'],
            'thickness' => floatval($glassType['thickness']),
            'has_inventory' => intval($glassType['total_packages'] ?? 0) > 0,
            'total_packages' => intval($glassType['total_packages'] ?? 0)
        ];
    }
    return $options;
}

/**
 * 获取分页参数
 * @param int $defaultPageSize 默认页面大小
 * @param int $maxPageSize 最大页面大小
 * @return array 包含分页信息的数组
 */
function getPaginationParams($defaultPageSize = 20, $maxPageSize = 100) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $pageSize = isset($_GET['page_size']) ? min($maxPageSize, max(1, intval($_GET['page_size']))) : $defaultPageSize;
    $offset = ($page - 1) * $pageSize;
    
    return compact('page', 'pageSize', 'offset');
}

/**
 * 构建分页响应信息
 * @param int $totalRecords 总记录数
 * @param int $page 当前页
 * @param int $pageSize 页面大小
 * @return array 分页信息
 */
function buildPaginationInfo($totalRecords, $page, $pageSize) {
    $totalPages = ceil($totalRecords / $pageSize);
    return [
        'page' => $page,
        'page_size' => $pageSize,
        'total' => $totalRecords,
        'total_pages' => $totalPages
    ];
}

// ==================== API处理函数 ====================

/**
 * 获取原片类型汇总信息 - 详情分析方法
 * 作用：根据原片类型ID，获取详细库存统计和库位分布
 * 输入：glass_type_id 参数（必填）
 * 基地过滤：默认查询当前用户基地，base_all=true 查询全部基地
 * 用途：查看某种原片的完整库存情况和分布详情
 */
function handleGetGlassTypeSummary() {
    global $currentUser;
    
    // 获取查询参数
    $glassTypeId = isset($_GET['glass_type_id']) ? intval($_GET['glass_type_id']) : null;
    $baseAll = isset($_GET['base_all']) && filter_var($_GET['base_all'], FILTER_VALIDATE_BOOLEAN);
    
    // 验证必填参数
    if (!$glassTypeId) {
        ApiCommon::sendResponse(400, '请提供 glass_type_id 参数');
        return;
    }
    
    try {
        // 构建查询条件
        $conditions = ["gt.id = ?"];
        $params = [$glassTypeId];
        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        
        // 构建基地过滤条件
        if (!$baseAll && isset($currentUser['base_id']) && $currentUser['base_id']) {
            $baseCondition = "INNER JOIN storage_racks sr ON p.current_rack_id = sr.id AND sr.base_id = " . intval($currentUser['base_id']);
        } else {
            $baseCondition = "";
        }
        
        // 主查询SQL - 获取原片类型信息和库存汇总
        $sql = "
            SELECT 
                gt.id,
                gt.custom_id,
                gt.name,
                gt.short_name,
                gt.brand,
                gt.manufacturer,
                gt.color,
                gt.thickness,
                gt.silver_layers,
                gt.substrate,
                gt.transmittance,
                gt.created_at,
                gt.updated_at,
                
                -- 库存汇总统计
                pkg_summary.total_packages,
                pkg_summary.total_pieces,
                pkg_summary.total_quantity,
                pkg_summary.avg_pieces,
                pkg_summary.rack_count
                
            FROM glass_types gt
            LEFT JOIN (
                SELECT 
                    p.glass_type_id,
                    COUNT(*) as total_packages,
                    COALESCE(SUM(p.pieces), 0) as total_pieces,
                    COALESCE(SUM(p.quantity), 0) as total_quantity,
                    COALESCE(AVG(p.pieces), 0) as avg_pieces,
                    COUNT(DISTINCT p.current_rack_id) as rack_count
                FROM glass_packages p
                {$baseCondition}
                WHERE p.status = 'in_storage'
                GROUP BY p.glass_type_id
            ) pkg_summary ON gt.id = pkg_summary.glass_type_id
            {$whereClause}
            ORDER BY gt.name
        ";
        
        $glassTypes = fetchAll($sql, $params);
        
        if (empty($glassTypes)) {
            ApiCommon::sendResponse(404, '未找到匹配的原片类型');
            return;
        }
        
        // 构建库位分布的基地过滤条件
        if (!$baseAll && isset($currentUser['base_id']) && $currentUser['base_id']) {
            $baseLocationCondition = "AND r.base_id = " . intval($currentUser['base_id']);
        } else {
            $baseLocationCondition = "";
        }
        
        // 获取详细的库位分布信息
        $locationSql = "
            SELECT 
                b.id as base_id,
                b.name as base_name,
                r.id as rack_id,
                r.code as rack_code,
                r.name as rack_name,
                r.area_type as rack_area_type,
                COUNT(*) as package_count,
                COALESCE(SUM(p.pieces), 0) as total_pieces,
                COALESCE(SUM(p.quantity), 0) as total_quantity
            FROM glass_packages p
            INNER JOIN glass_types gt ON p.glass_type_id = gt.id
            INNER JOIN storage_racks r ON p.current_rack_id = r.id
            INNER JOIN bases b ON r.base_id = b.id
            WHERE p.status = 'in_storage'
            AND p.glass_type_id = ?
            {$baseLocationCondition}
            GROUP BY b.id, r.id
            ORDER BY b.name, r.code
        ";
        
        $locationResult = fetchAll($locationSql, [$glassTypeId]);
        
        // 按基地分组库位信息
        $baseDistribution = [];
        $totalRacks = 0;
        $totalBasePackages = 0;
        
        foreach ($locationResult as $location) {
            $baseId = $location['base_id'];
            
            if (!isset($baseDistribution[$baseId])) {
                $baseDistribution[$baseId] = [
                    'base_id' => intval($location['base_id']),
                    'base_name' => $location['base_name'],
                    'racks' => [],
                    'total_packages' => 0,
                    'total_pieces' => 0,
                    'total_quantity' => 0
                ];
            }
            
            $baseDistribution[$baseId]['racks'][] = [
                'rack_id' => intval($location['rack_id']),
                'rack_code' => $location['rack_code'],
                'rack_name' => $location['rack_name'],
                'area_type' => $location['rack_area_type'],
                'package_count' => intval($location['package_count']),
                'total_pieces' => intval($location['total_pieces']),
                'total_quantity' => intval($location['total_quantity'])
            ];
            
            $baseDistribution[$baseId]['total_packages'] += intval($location['package_count']);
            $baseDistribution[$baseId]['total_pieces'] += intval($location['total_pieces']);
            $baseDistribution[$baseId]['total_quantity'] += intval($location['total_quantity']);
            $totalRacks++;
            $totalBasePackages += intval($location['package_count']);
        }
        
        // 格式化响应数据
        $glassType = $glassTypes[0];
        $responseData = [
            'glass_type' => [
                'id' => intval($glassType['id']),
                'custom_id' => $glassType['custom_id'],
                'name' => $glassType['name'],
                'short_name' => $glassType['short_name'],
                'attributes' => [
                    'brand' => $glassType['brand'],
                    'manufacturer' => $glassType['manufacturer'],
                    'color' => $glassType['color'],
                    'thickness' => floatval($glassType['thickness']),
                    'silver_layers' => $glassType['silver_layers'],
                    'substrate' => $glassType['substrate'],
                    'transmittance' => $glassType['transmittance']
                ],
                'created_at' => $glassType['created_at'],
                'updated_at' => $glassType['updated_at']
            ],
            'inventory_summary' => [
                'total_packages' => intval($glassType['total_packages']),
                'total_pieces' => intval($glassType['total_pieces']),
                'total_quantity' => intval($glassType['total_quantity']),
                'avg_pieces_per_package' => floatval($glassType['avg_pieces']),
                'total_racks_used' => intval($glassType['rack_count']),
                'bases_involved' => count($baseDistribution),
                'total_base_packages' => $totalBasePackages
            ],
            'base_distribution' => array_values($baseDistribution)
        ];
        
        ApiCommon::sendResponse(200, '获取原片类型信息成功', $responseData);
        
    } catch (Exception $e) {
        ApiCommon::sendResponse(500, '服务器错误: ' . $e->getMessage());
    }
}




/**
 * 三联动下拉选项 - 级联选择器
 * 作用：提供厚度、颜色、品牌的三级联动筛选，用于逐步缩小原片类型选择范围
 * 特点：支持选择1个、2个或3个条件，返回符合条件的原片类型列表
 * 基地过滤：默认查询当前用户基地，base_all=true 查询全部基地
 * 参数说明：
 *   submit=true  - 执行搜索，返回完整的筛选结果列表
 *   submit=false - 联动更新，仅返回级联选项数据（轻量级）
 * 返回格式：根据submit参数返回不同格式的数据
 */
function handleGetDropdownOptions() {
    global $currentUser;
    
    // 获取当前筛选条件
    $thickness = isset($_GET['thickness']) ? floatval($_GET['thickness']) : null;
    $color = isset($_GET['color']) ? trim($_GET['color']) : null;
    $brand = isset($_GET['brand']) ? trim($_GET['brand']) : null;
    $baseAll = isset($_GET['base_all']) && filter_var($_GET['base_all'], FILTER_VALIDATE_BOOLEAN);
    $submit = isset($_GET['submit']) && filter_var($_GET['submit'], FILTER_VALIDATE_BOOLEAN);
    
    try {
        // 构建基地过滤条件 - 只显示有库存的原片类型
        $baseFilters = buildBaseFilters($currentUser, $baseAll, 'p_filtered', 'sr');
        $baseFilter = $baseFilters['baseJoin'];
        $packageFilter = $baseFilters['packageFilter'];
        
        if ($submit) {
            // ==================== 提交搜索模式 ====================
            // 返回完整的筛选结果列表，格式与模糊搜索一致
            
            // 构建查询条件
            $conditions = [];
            $params = [];
            
            if ($thickness) {
                $conditions[] = "gt.thickness = ?";
                $params[] = $thickness;
            }
            
            if ($color) {
                $conditions[] = "gt.color = ?";
                $params[] = $color;
            }
            
            if ($brand) {
                $conditions[] = "gt.brand = ?";
                $params[] = $brand;
            }
            
            // 构建WHERE子句
            $whereClause = '';
            if (!empty($conditions)) {
                $whereClause = 'WHERE ' . implode(' AND ', $conditions);
            }
            
            // 获取符合条件的原片类型列表
            $sql = "
                SELECT DISTINCT
                    gt.id,
                    gt.custom_id,
                    gt.name,
                    gt.short_name,
                    gt.brand,
                    gt.manufacturer,
                    gt.color,
                    gt.thickness,
                    gt.silver_layers,
                    gt.substrate,
                    gt.transmittance,
                    
                    -- 库存统计
                    COALESCE(pkg_summary.total_packages, 0) as total_packages,
                    COALESCE(pkg_summary.total_pieces, 0) as total_pieces,
                    COALESCE(pkg_summary.total_quantity, 0) as total_quantity
                    
                FROM glass_types gt
                {$baseFilter}
                " . (!empty($conditions) ? "AND " . implode(' AND ', $conditions) : "") . "
                LEFT JOIN (
                    SELECT 
                        p.glass_type_id,
                        COUNT(*) as total_packages,
                        COALESCE(SUM(p.pieces), 0) as total_pieces,
                        COALESCE(SUM(p.quantity), 0) as total_quantity
                    FROM glass_packages p
                    INNER JOIN storage_racks sr ON p.current_rack_id = sr.id
                    WHERE p.status = 'in_storage'
                    " . (!$baseAll && isset($currentUser['base_id']) && $currentUser['base_id'] ? "AND sr.base_id = " . intval($currentUser['base_id']) : "") . "
                    GROUP BY p.glass_type_id
                ) pkg_summary ON gt.id = pkg_summary.glass_type_id
                ORDER BY gt.thickness, gt.color, gt.brand, gt.name
            ";
            
            $glassTypes = fetchAll($sql, $params);
            
            // 格式化为统一的选择器格式
            $selectionOptions = buildGlassTypeOptions($glassTypes);
            
            // 构建与模糊搜索一致的返回格式
            $brandCount = count(array_unique(array_column($selectionOptions, 'brand')));
            $paginationInfo = buildPaginationInfo(count($selectionOptions), 1, count($selectionOptions));
            
            // 返回搜索结果格式，与模糊搜索保持一致
            ApiCommon::sendResponse(200, '级联筛选成功', [
                'search_info' => [
                    'search_type' => 'cascade_filter',
                    'keyword' => '',
                    'search_fields' => ['thickness', 'color', 'brand'],
                    'filters_applied' => count(array_filter([$thickness, $color, $brand])),
                    'current_filters' => [
                        'thickness' => $thickness,
                        'color' => $color,
                        'brand' => $brand
                    ]
                ],
                'selection_options' => $selectionOptions,
                'selection_summary' => [
                    'total_options' => count($selectionOptions),
                    'brand_count' => $brandCount,
                    'with_inventory' => count(array_filter($selectionOptions, fn($opt) => $opt['has_inventory']))
                ],
                'pagination' => $paginationInfo
            ]);
            
        } else {
            // ==================== 联动更新模式 ====================
            // 返回基于当前筛选条件的级联选项，轻量级格式
            
            // 构建当前筛选条件的WHERE部分
            $currentConditions = [];
            $currentParams = [];
            
            if ($thickness) {
                $currentConditions[] = "gt.thickness = ?";
                $currentParams[] = $thickness;
            }
            if ($color) {
                $currentConditions[] = "gt.color = ?";
                $currentParams[] = $color;
            }
            if ($brand) {
                $currentConditions[] = "gt.brand = ?";
                $currentParams[] = $brand;
            }
            
            $whereClause = !empty($currentConditions) ? "WHERE " . implode(' AND ', $currentConditions) : "";
            
            // 分别获取每个维度在当前筛选条件下的可用选项
            
            // 1. 获取厚度选项（如果未选择厚度，显示所有可用厚度；如果已选择，显示当前厚度）
            if (!$thickness) {
                $thicknessSql = "
                    SELECT DISTINCT gt.thickness
                    FROM glass_types gt
                    {$baseFilter}
                    {$baseFilters['baseWhere']}
                    ORDER BY gt.thickness
                ";
                $thicknessResults = fetchAll($thicknessSql);
                $thicknessOptions = [];
                foreach ($thicknessResults as $result) {
                    $value = floatval($result['thickness']);
                    if ($value > 0) {
                        $thicknessOptions[] = ['value' => $value, 'label' => $value . 'mm'];
                    }
                }
            } else {
                $thicknessOptions = [['value' => $thickness, 'label' => $thickness . 'mm']];
            }
            
            // 2. 获取颜色选项（基于已选择的厚度，显示兼容的颜色）
            if (!$color) {
                $colorSql = "
                    SELECT DISTINCT gt.color
                    FROM glass_types gt
                    {$baseFilter}
                    {$baseFilters['baseWhere']}
                    " . ($thickness ? "AND gt.thickness = ?" : "") . "
                    AND gt.color IS NOT NULL AND gt.color != ''
                    ORDER BY gt.color
                ";
                $colorParams = $thickness ? [$thickness] : [];
                $colorResults = fetchAll($colorSql, $colorParams);
                $colorOptions = [];
                foreach ($colorResults as $result) {
                    $value = trim($result['color']);
                    if ($value) {
                        $colorOptions[] = ['value' => $value, 'label' => $value];
                    }
                }
            } else {
                $colorOptions = [['value' => $color, 'label' => $color]];
            }
            
            // 3. 获取品牌选项（基于已选择的厚度和颜色，显示兼容的品牌）
            if (!$brand) {
                $brandSql = "
                    SELECT DISTINCT gt.brand
                    FROM glass_types gt
                    {$baseFilter}
                    {$baseFilters['baseWhere']}
                    " . ($thickness ? "AND gt.thickness = ?" : "") . "
                    " . ($color ? "AND gt.color = ?" : "") . "
                    AND gt.brand IS NOT NULL AND gt.brand != ''
                    ORDER BY gt.brand
                ";
                $brandParams = [];
                if ($thickness) $brandParams[] = $thickness;
                if ($color) $brandParams[] = $color;
                $brandResults = fetchAll($brandSql, $brandParams);
                $brandOptions = [];
                foreach ($brandResults as $result) {
                    $value = trim($result['brand']);
                    if ($value) {
                        $brandOptions[] = ['value' => $value, 'label' => $value];
                    }
                }
            } else {
                $brandOptions = [['value' => $brand, 'label' => $brand]];
            }
            
            // 计算当前筛选条件下的匹配数量
            $matchCount = 0;
            if (!empty($currentConditions)) {
                $countSql = "
                    SELECT COUNT(DISTINCT gt.id) as match_count
                    FROM glass_types gt
                    {$baseFilter}
                    {$baseFilters['baseWhere']}
                    AND " . implode(' AND ', $currentConditions) . "
                ";
                $countResult = fetchRow($countSql, $currentParams);
                $matchCount = intval($countResult['match_count']);
            } else {
                // 如果没有选择任何条件，显示所有有库存的原片类型数量
                $totalCountSql = "
                    SELECT COUNT(DISTINCT gt.id) as total_count
                    FROM glass_types gt
                    {$baseFilter}
                    {$baseFilters['baseWhere']}
                ";
                $totalResult = fetchRow($totalCountSql);
                $matchCount = intval($totalResult['total_count']);
            }
            
            // 返回级联数据
            ApiCommon::sendResponse(200, '级联选项更新成功', [
                'filter_status' => [
                    'current_filters' => [
                        'thickness' => $thickness,
                        'color' => $color,
                        'brand' => $brand
                    ],
                    'filters_applied' => count(array_filter([$thickness, $color, $brand])),
                    'match_count' => $matchCount
                ],
                'available_options' => [
                    'thicknesses' => $thicknessOptions,
                    'colors' => $colorOptions,
                    'brands' => $brandOptions
                ],
                'next_step' => [
                    'action' => 'continue_or_submit',
                    'message' => count(array_filter([$thickness, $color, $brand])) === 0
                        ? '请选择筛选条件'
                        : ($matchCount === 0 
                            ? '无匹配结果，请调整筛选条件'
                            : ($matchCount === 1 
                                ? '已找到唯一匹配，可提交搜索查看详情'
                                : '已找到 ' . $matchCount . ' 个匹配项，可继续筛选或提交搜索')),
                    'can_submit' => $matchCount > 0
                ]
            ]);
        }
        
    } catch (Exception $e) {
        ApiCommon::sendResponse(500, '服务器错误: ' . $e->getMessage());
    }
}

/**
 * 模糊搜索 - 快速选择原片类型
 * 作用：根据关键词快速搜索原片类型，用于选择器功能
 * 基地过滤：默认查询当前用户基地，base_all=true 查询全部基地
 * 返回格式：轻量级，突出选择信息
 */
function handleFuzzySearch() {
    global $currentUser;
    
    // 获取搜索关键词
    $keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : null;
    $baseAll = isset($_GET['base_all']) && filter_var($_GET['base_all'], FILTER_VALIDATE_BOOLEAN);
    
    // 获取搜索范围
    $searchFields = isset($_GET['fields']) ? explode(',', trim($_GET['fields'])) : ['name', 'custom_id', 'brand', 'manufacturer'];
    
    // 分页参数
    $pagination = getPaginationParams(10, 50); // 选择器使用较小的默认值
    
    // 验证搜索关键词
    if (!$keyword || strlen($keyword) < 1) {
        ApiCommon::sendResponse(400, '请提供搜索关键词，长度至少1个字符');
        return;
    }
    
    // 验证搜索字段
    $validFields = ['name', 'custom_id', 'short_name', 'brand', 'manufacturer', 'color', 'substrate', 'silver_layers', 'transmittance'];
    $sanitizedFields = [];
    foreach ($searchFields as $field) {
        $field = trim($field);
        if (in_array($field, $validFields)) {
            $sanitizedFields[] = $field;
        }
    }
    
    if (empty($sanitizedFields)) {
        $sanitizedFields = ['name', 'custom_id', 'brand', 'manufacturer'];
    }
    
    try {
        // 构建搜索条件
        $searchConditions = [];
        $params = [];
        
        foreach ($sanitizedFields as $field) {
            $searchConditions[] = "gt.{$field} LIKE ?";
            $params[] = "%{$keyword}%";
        }
        
        $whereClause = 'WHERE (' . implode(' OR ', $searchConditions) . ')';
        
        // 如果没有指定base_all=true，且用户有基地权限，则默认只显示该用户基地的数据
        if (!$baseAll && isset($currentUser['base_id']) && $currentUser['base_id']) {
            $baseAll = false;
        }
        
        // 构建基地过滤条件
        $baseFilters = buildBaseFilters($currentUser, $baseAll, 'p_filtered', 'sr');
        $baseFilter = $baseFilters['baseJoin'];
        
        // 使用与buildBaseFilters一致的包过滤条件
        $packageFilter = $baseFilters['packageFilter'];
        
        // 主查询SQL - 优化为选择器格式
        $sql = "
            SELECT DISTINCT
                gt.id,
                gt.custom_id,
                gt.name,
                gt.short_name,
                gt.brand,
                gt.manufacturer,
                gt.color,
                gt.thickness,
                
                -- 简化的库存信息（仅用于选择参考）
                COALESCE(pkg_summary.total_packages, 0) as total_packages
                
            FROM glass_types gt
            {$baseFilter}
            LEFT JOIN (
                SELECT 
                    p.glass_type_id,
                    COUNT(*) as total_packages
                FROM glass_packages p
                INNER JOIN storage_racks sr ON p.current_rack_id = sr.id
                WHERE p.status = 'in_storage' " . (!$baseAll && isset($currentUser['base_id']) && $currentUser['base_id'] ? "AND sr.base_id = " . intval($currentUser['base_id']) : "") . "
                GROUP BY p.glass_type_id
            ) pkg_summary ON gt.id = pkg_summary.glass_type_id
            {$whereClause}
            ORDER BY 
                CASE 
                    WHEN gt.name LIKE ? THEN 1
                    WHEN gt.custom_id LIKE ? THEN 2
                    ELSE 3
                END,
                gt.name ASC
            LIMIT {$pagination['offset']}, {$pagination['pageSize']}
        ";
        
        // 添加排序参数到参数列表末尾
        $finalParams = array_merge($params, ["%{$keyword}%", "%{$keyword}%"]);
        
        $glassTypes = fetchAll($sql, $finalParams);
        
        // 获取总记录数
        $countSql = "
            SELECT COUNT(DISTINCT gt.id) as total
            FROM glass_types gt
            {$whereClause}
        ";
        $totalResult = fetchRow($countSql, $params);
        $paginationInfo = buildPaginationInfo($totalResult['total'], $pagination['page'], $pagination['pageSize']);
        
        // 格式化为选择器格式
        $selectionOptions = buildGlassTypeOptions($glassTypes);
        
        // 搜索统计
        $summarySql = "
            SELECT 
                COUNT(DISTINCT gt.id) as total_types,
                COUNT(DISTINCT gt.brand) as brand_count
            FROM glass_types gt
            {$whereClause}
        ";
        $summary = fetchRow($summarySql, $params);
        
        ApiCommon::sendResponse(200, '搜索成功', [
            'search_info' => [
                'keyword' => $keyword,
                'search_fields' => $sanitizedFields
            ],
            'selection_options' => $selectionOptions,
            'selection_summary' => [
                'total_options' => intval($summary['total_types']),
                'brand_count' => intval($summary['brand_count']),
                'with_inventory' => count(array_filter($selectionOptions, fn($opt) => $opt['has_inventory']))
            ],
            'pagination' => $paginationInfo
        ]);
        
    } catch (Exception $e) {
        ApiCommon::sendResponse(500, '服务器错误: ' . $e->getMessage());
    }
}

/**
 * 获取状态名称 - 辅助方法
 * 作用：将状态码转换为中文显示名称
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