<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/db.php';
require_once '../includes/inventory_check_auth.php';

// 创建API实例
$api = new class {
    public function response($code, $message, $data = null) {
        http_response_code($code);
        echo json_encode([
            'code' => $code,
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public function getCurrentUser() {
        // 这里应该根据实际的用户认证系统来实现
        return $_SESSION['user'] ?? null;
    }
};

try {
    $taskId = $_POST['task_id'] ?? 0;
    
    if (!$taskId) {
        $api->response(400, '缺少任务ID');
    }
    
    // 验证权限
    $user = $api->getCurrentUser();
    if (!$user || !hasInventoryCheckPermission($user)) {
        $api->response(403, '无权限访问');
    }
    
    // 验证任务存在
    $task = fetchRow("SELECT * FROM inventory_check_tasks WHERE id = ?", [$taskId]);
    if (!$task) {
        $api->response(404, '任务不存在');
    }
    
    // 获取盘点期间的回滚记录数量
    $count = fetchColumn("
        SELECT COUNT(*) FROM inventory_check_cache 
        WHERE task_id = ? AND check_method = 'auto_rollback'
    ", [$taskId]);
    
    $api->response(200, '获取成功', [
        'count' => intval($count),
        'task_id' => $taskId
    ]);
    
} catch (Exception $e) {
    $api->response(500, '服务器错误', ['error' => $e->getMessage()]);
}

/**
 * 检查用户是否有盘点权限
 */
function hasInventoryCheckPermission($user) {
    // 管理员和库管都有权限
    return in_array($user['role'], ['admin', 'manager']);
}

/**
 * 执行查询并返回单列结果
 */
function fetchColumn($sql, $params = []) {
    $conn = getDbConnection();
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}
?>