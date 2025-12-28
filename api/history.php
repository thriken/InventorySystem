<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/inventory_operations.php';
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
        handleGetHistory();
        break;
    default:
        ApiCommon::sendResponse(405, '方法不允许');
        break;
}

/**
 * 处理操作记录查询
 */
function handleGetHistory() {
    global $currentUser;
    
    try {
        // 获取查询参数
        $params = [
            'start_date' => $_GET['start_date'] ?? null,
            'end_date' => $_GET['end_date'] ?? null,
            'package_code' => $_GET['package_code'] ?? null,
            'operation_type' => $_GET['operation_type'] ?? null,
            'base_id' => $_GET['base_id'] ?? null,
            'page' => $_GET['page'] ?? null,
            'page_size' => $_GET['page_size'] ?? null
        ];
        // 如果没有指定日期范围，默认查询最近7天的记录
        if (empty($params['start_date']) && empty($params['end_date'])) {
            $params['start_date'] = date('Y-m-d', strtotime('-7 days'));
            $params['end_date'] = date('Y-m-d');
        }
        // 参数验证
        validateHistoryParams($params, $currentUser);
        
        // 获取操作记录
        $result = getOperationHistory($currentUser, $params);
        
        // 获取可用操作类型列表（用于前端筛选）
        $operationTypes = getAvailableOperationTypes();
        
        // 获取可用基地列表（用于前端筛选）
        $bases = getAvailableBases($currentUser);
        
        // 构建响应数据
        $responseData = [
            'records' => $result['records'],
            'pagination' => $result['pagination'],
            'filters' => [
                'operation_types' => $operationTypes,
                'bases' => $bases,
                'current_time_range' => $result['time_range'],
                'user_permissions' => [
                    'role' => $currentUser['role'],
                    'can_query_all_bases' => $currentUser['role'] === 'admin',
                    'max_days_allowed' => getMaxDaysAllowed($currentUser['role'])
                ]
            ]
        ];
        
        ApiCommon::sendResponse(200, '查询成功', $responseData);
        
    } catch (Exception $e) {
        ApiCommon::sendResponse(500, '服务器错误: ' . $e->getMessage());
    }
}

/**
 * 验证查询参数
 * @param array $params 查询参数
 * @param array $currentUser 当前用户信息
 * @throws Exception 验证失败时抛出异常
 */
function validateHistoryParams($params, $currentUser) {
    // 验证日期格式
    if ($params['start_date'] && !isValidDate($params['start_date'])) {
        throw new Exception('开始日期格式无效，请使用YYYY-MM-DD格式');
    }
    
    if ($params['end_date'] && !isValidDate($params['end_date'])) {
        throw new Exception('结束日期格式无效，请使用YYYY-MM-DD格式');
    }
    
    // 验证日期范围
    if ($params['start_date'] && $params['end_date']) {
        $start = strtotime($params['start_date']);
        $end = strtotime($params['end_date']);
        
        if ($start > $end) {
            throw new Exception('开始日期不能晚于结束日期');
        }
        
        // 检查日期范围是否超过用户权限限制
        $maxDays = getMaxDaysAllowed($currentUser['role']);
        if ($maxDays > 0) {
            $daysDiff = ($end - $start) / (24 * 60 * 60);
            if ($daysDiff > $maxDays) {
                throw new Exception("查询范围不能超过{$maxDays}天");
            }
        }
    }
    
    // 验证操作类型
    if ($params['operation_type']) {
        $validTypes = ['purchase_in', 'usage_out', 'partial_usage', 'return_in', 'scrap', 'check_in', 'check_out'];
        if (!in_array($params['operation_type'], $validTypes)) {
            throw new Exception('无效的操作类型');
        }
    }
    
    // 验证基地权限（非管理员用户不能指定其他基地）
    if ($params['base_id'] && $currentUser['role'] !== 'admin') {
        throw new Exception('只有管理员可以指定基地查询');
    }
}

/**
 * 验证日期格式
 * @param string $date 日期字符串
 * @return bool 是否有效
 */
function isValidDate($date) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date);
}

/**
 * 获取用户角色允许的最大查询天数
 * @param string $role 用户角色
 * @return int 最大天数，0表示无限制
 */
function getMaxDaysAllowed($role) {
    switch ($role) {
        case 'admin':
            return 0; // 无限制
        case 'manager':
            return 365; // 最多一年
        case 'viewer':
        case 'operator':
            return 7; // 最多7天
        default:
            return 1; // 默认1天
    }
}

/**
 * 获取可用的操作类型列表
 * @return array 操作类型列表
 */
function getAvailableOperationTypes() {
    return [
        ['value' => 'purchase_in', 'label' => '采购入库'],
        ['value' => 'usage_out', 'label' => '领用出库'],
        ['value' => 'partial_usage', 'label' => '部分领用'],
        ['value' => 'return_in', 'label' => '归还入库'],
        ['value' => 'scrap', 'label' => '报废'],
        ['value' => 'check_in', 'label' => '盘盈'],
        ['value' => 'check_out', 'label' => '盘亏']
    ];
}

/**
 * 获取可用的基地列表（根据用户权限过滤）
 * @param array $currentUser 当前用户信息
 * @return array 基地列表
 */
function getAvailableBases($currentUser) {
    // 管理员可以查看所有基地
    if ($currentUser['role'] === 'admin') {
        $sql = "SELECT id, name FROM bases ORDER BY name";
        $bases = fetchAll($sql);
    } else {
        // 其他用户只能查看自己所属的基地
        if (!empty($currentUser['base_id'])) {
            $sql = "SELECT id, name FROM bases WHERE id = ? ORDER BY name";
            $bases = fetchAll($sql, [$currentUser['base_id']]);
        } else {
            // 如果用户没有分配基地，返回空数组
            return [];
        }
    }
    
    $result = [];
    foreach ($bases as $base) {
        $result[] = [
            'value' => $base['id'],
            'label' => $base['name']
        ];
    }
    
    return $result;
}
?>