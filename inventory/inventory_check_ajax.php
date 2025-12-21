<?php
/**
 * 盘点模块 AJAX 接口
 * 处理盘点任务相关的 AJAX 请求
 */

require_once '../includes/database.php';

header('Content-Type: application/json; charset=utf-8');

// 获取请求参数
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'estimate_packages':
            handleEstimatePackages();
            break;
        default:
            throw new Exception('未知的操作');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * 预估包数
 */
function handleEstimatePackages() {
    $baseId = intval($_GET['base_id'] ?? 0);
    $taskType = $_GET['task_type'] ?? 'full';
    
    if ($baseId <= 0) {
        throw new Exception('无效的基地ID');
    }
    
    $count = 0;
    
    // 通过库位架关联基地
    switch ($taskType) {
        case 'full':
            // 全盘：获取该基地所有在库的包
            $count = fetchOne("
                SELECT COUNT(*) 
                FROM glass_packages gp
                INNER JOIN storage_racks sr ON gp.current_rack_id = sr.id
                WHERE sr.base_id = ? AND gp.status = 'in_storage'
            ", [$baseId]);
            break;
            
        case 'partial':
            // 部分盘点：获取该基地所有在库的包（用户可以自己选择）
            $count = fetchOne("
                SELECT COUNT(*) 
                FROM glass_packages gp
                INNER JOIN storage_racks sr ON gp.current_rack_id = sr.id
                WHERE sr.base_id = ? AND gp.status = 'in_storage'
            ", [$baseId]);
            break;
            
        case 'random':
            // 抽盘：抽取10%的包
            $totalCount = fetchOne("
                SELECT COUNT(*) 
                FROM glass_packages gp
                INNER JOIN storage_racks sr ON gp.current_rack_id = sr.id
                WHERE sr.base_id = ? AND gp.status = 'in_storage'
            ", [$baseId]);
            $count = max(1, intval($totalCount * 0.1));
            break;
            
        default:
            throw new Exception('无效的盘点类型');
    }
    
    echo json_encode([
        'success' => true,
        'count' => $count,
        'message' => "预计需要盘点 {$count} 个包"
    ]);
}
?>