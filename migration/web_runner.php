<?php
/**
 * 数据迁移 Web API
 * inventory_transactions → inventory_operation_records
 */

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只允许POST请求']);
    exit;
}

$action = $_POST['action'] ?? '';
if (empty($action)) {
    echo json_encode(['success' => false, 'message' => '缺少操作参数']);
    exit;
}

try {
    switch ($action) {
        case 'migrate':
            echo json_encode(executeMigrate());
            break;
        case 'truncate':
            echo json_encode(executeTruncate());
            break;
        case 'status':
            echo json_encode(checkStatus());
            break;
        default:
            throw new Exception('不支持的操作: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

/**
 * 执行数据迁移
 */
function executeMigrate() {
    $pdo = $GLOBALS['pdo'];
    $pdo->beginTransaction();
    
    try {
        // 执行迁移SQL
        $migrateSql = "
            INSERT INTO inventory_operation_records (
                record_no, operation_type, package_id, glass_type_id, base_id,
                operation_quantity, before_quantity, after_quantity, 
                from_rack_id, to_rack_id, unit_area, total_area,
                operator_id, operation_date, operation_time, notes, scrap_reason, created_at
            )
            SELECT 
                CONCAT(
                    CASE transaction_type
                        WHEN 'purchase_in' THEN 'CG'
                        WHEN 'usage_out' THEN 'LY'
                        WHEN 'partial_usage' THEN 'BF'
                        WHEN 'return_in' THEN 'GH'
                        WHEN 'scrap' THEN 'BF'
                        WHEN 'check_in' THEN 'PY'
                        WHEN 'check_out' THEN 'PK'
                        WHEN 'location_adjust' THEN 'KW'
                        ELSE 'OP'
                    END,
                    DATE(transaction_time),
                    LPAD(it.id, 4, '0')
                ) as record_no,
                transaction_type as operation_type,
                package_id,
                (SELECT glass_type_id FROM glass_packages WHERE id = it.package_id LIMIT 1) as glass_type_id,
                COALESCE(
                    (SELECT base_id FROM storage_racks WHERE id = it.to_rack_id LIMIT 1),
                    (SELECT base_id FROM storage_racks WHERE id = it.from_rack_id LIMIT 1)
                ) as base_id,
                it.quantity as operation_quantity,
                (SELECT pieces FROM glass_packages WHERE id = it.package_id LIMIT 1) as before_quantity,
                CASE 
                    WHEN it.transaction_type IN ('purchase_in', 'return_in', 'check_in') 
                    THEN (SELECT pieces FROM glass_packages WHERE id = it.package_id LIMIT 1) + it.quantity
                    WHEN it.transaction_type IN ('usage_out', 'scrap', 'check_out', 'partial_usage')
                    THEN GREATEST(0, (SELECT pieces FROM glass_packages WHERE id = it.package_id LIMIT 1) - it.quantity)
                    ELSE (SELECT pieces FROM glass_packages WHERE id = it.package_id LIMIT 1)
                END as after_quantity,
                from_rack_id,
                to_rack_id,
                CASE 
                    WHEN gp.width > 0 AND gp.height > 0 THEN (gp.width * gp.height) / 1000000
                    ELSE NULL
                END as unit_area,
                CASE 
                    WHEN gp.width > 0 AND gp.height > 0 AND it.quantity > 0 
                    THEN (gp.width * gp.height * it.quantity) / 1000000
                    ELSE NULL
                END as total_area,
                IFNULL(it.operator_id, 1) as operator_id,
                DATE(transaction_time) as operation_date,
                TIME(transaction_time) as operation_time,
                IFNULL(it.notes, '') as notes,
                IFNULL(it.scrap_reason, '') as scrap_reason,
                transaction_time as created_at
            FROM inventory_transactions it
            LEFT JOIN glass_packages gp ON it.package_id = gp.id
            ORDER BY transaction_time ASC
        ";
        
        $stmt = $pdo->query($migrateSql);
        $migratedCount = $stmt->rowCount();
        
        // 统计错误记录
        $errorCount = fetchOne("
            SELECT COUNT(*) FROM inventory_operation_records 
            WHERE package_id IS NULL OR package_id = 0
        ");
        
        $pdo->commit();
        
        return [
            'success' => true,
            'migrated' => $migratedCount,
            'error_count' => $errorCount,
            'message' => "迁移完成，共处理 {$migratedCount} 条记录"
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * 清空目标表
 */
function executeTruncate() {
    query("TRUNCATE TABLE inventory_operation_records");
    
    return [
        'success' => true,
        'message' => '目标表已清空'
    ];
}

/**
 * 检查状态
 */
function checkStatus() {
    $sourceCount = 0;
    $targetCount = 0;
    $sourceExists = false;
    $targetExists = false;
    $ready = false;
    $message = '';
    
    try {
        $sourceCount = fetchOne("SELECT COUNT(*) FROM inventory_transactions");
        $sourceExists = true;
    } catch (Exception $e) {
        $sourceExists = false;
    }
    
    try {
        $targetCount = fetchOne("SELECT COUNT(*) FROM inventory_operation_records");
        $targetExists = true;
    } catch (Exception $e) {
        $targetExists = false;
    }
    
    if (!$sourceExists) {
        $message = '源表 inventory_transactions 不存在';
    } elseif (!$targetExists) {
        $message = '目标表 inventory_operation_records 不存在';
    } elseif ($sourceCount === 0) {
        $message = '源表无数据';
    } else {
        $ready = true;
        $message = '环境准备就绪';
    }
    
    return [
        'success' => true,
        'source_count' => $sourceCount,
        'target_count' => $targetCount,
        'ready' => $ready && $sourceCount > 0,
        'message' => $message
    ];
}
?>