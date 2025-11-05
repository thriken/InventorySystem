<?php
/**
 * 字典查询API
 * 提供系统基础字典数据的统一查询接口
 * 包括品牌、厂家、原片类型等基础数据
 */

require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once 'ApiCommon.php';

// 设置响应头和处理预检请求
ApiCommon::setHeaders();
ApiCommon::handlePreflight();

// 验证Token认证
$currentUser = ApiCommon::authenticate();

try {
    // 验证请求方法
    ApiCommon::validateMethod(['GET']);
    
    // 获取查询参数
    $action = $_GET['action'] ?? 'items';
    $category = $_GET['category'] ?? '';
    $parentId = $_GET['parent_id'] ?? null;
    $search = $_GET['search'] ?? '';
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 100);
    
    // 处理不同的操作类型
    switch ($action) {
        case 'categories':
            // 获取所有字典分类
            $data = getDictionaryCategories();
            break;
            
        case 'items':
            // 获取字典项
            $data = getDictionaryItems($category, $parentId, $search, $page, $limit);
            break;
            
        case 'glass_types':
            // 获取原片类型（特殊处理）
            $data = getGlassTypes($search, $page, $limit);
            break;
            
        default:
            ApiCommon::sendResponse(400, '无效的操作类型');
    }
    
    // 返回成功响应
    ApiCommon::sendResponse(200, '获取成功', $data);
    
} catch (Exception $e) {
    ApiCommon::sendResponse(500, '服务器错误: ' . $e->getMessage());
}

/**
 * 获取字典分类列表
 */
function getDictionaryCategories() {
    $categories = [
        ['code' => 'brand', 'name' => '品牌', 'description' => '玻璃品牌'],
        ['code' => 'manufacturer', 'name' => '厂家', 'description' => '生产厂家'],
        ['code' => 'color', 'name' => '颜色', 'description' => '玻璃颜色类型']
    ];
    
    return ['categories' => $categories];
}

/**
 * 获取字典项
 */
function getDictionaryItems($category = '', $parentId = null, $search = '', $page = 1, $limit = 100) {
    $offset = ($page - 1) * $limit;
    $params = [];
    $whereConditions = [];
    
    // 构建查询条件
    if (!empty($category)) {
        $whereConditions[] = "category = ?";
        $params[] = $category;
    }
    
    if ($parentId !== null) {
        $whereConditions[] = "parent_id = ?";
        $params[] = $parentId;
    } else {
        $whereConditions[] = "parent_id IS NULL";
    }
    
    if (!empty($search)) {
        $whereConditions[] = "(name LIKE ? OR code LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    // 构建WHERE子句
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }
    
    // 查询数据
    $sql = "SELECT id, code, name, category, parent_id, sort_order, created_at 
            FROM dictionary_items 
            {$whereClause} 
            ORDER BY sort_order ASC, name ASC 
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $items = fetchAll($sql, $params);
    
    // 获取总数用于分页
    $countSql = "SELECT COUNT(*) as total FROM dictionary_items {$whereClause}";
    $countParams = array_slice($params, 0, count($params) - 2); // 移除LIMIT和OFFSET参数
    $totalResult = fetchRow($countSql, $countParams);
    $total = $totalResult ? $totalResult['total'] : 0;
    
    return [
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ];
}

/**
 * 获取原片类型
 */
function getGlassTypes($search = '', $page = 1, $limit = 100) {
    $offset = ($page - 1) * $limit;
    $params = [];
    $whereConditions = [];
    
    // 构建查询条件
    if (!empty($search)) {
        $whereConditions[] = "(name LIKE ? OR short_name LIKE ? OR brand LIKE ? OR manufacturer LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    // 构建WHERE子句
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }
    
    // 查询数据
    $sql = "SELECT 
                id, name, short_name, brand, manufacturer, color, 
                thickness, silver_layers, substrate, transmittance,
                created_at, updated_at
            FROM glass_types 
            {$whereClause} 
            ORDER BY brand ASC, name ASC 
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $items = fetchAll($sql, $params);
    
    // 获取总数用于分页
    $countSql = "SELECT COUNT(*) as total FROM glass_types {$whereClause}";
    $countParams = array_slice($params, 0, count($params) - 2);
    $totalResult = fetchRow($countSql, $countParams);
    $total = $totalResult ? $totalResult['total'] : 0;
    
    return [
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ];
}

?>