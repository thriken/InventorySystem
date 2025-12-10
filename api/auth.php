<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/app_info.php';

// 设置响应头为JSON格式
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

// API路由
switch ($method) {
    case 'POST':
        handleLogin();
        break;
    case 'GET':
        // 检查是否有特定的GET参数
        if (isset($_GET['action']) && $_GET['action'] === 'appname') {
            handleGetAppName();
        } else {
            handleCheckLogin();
        }
        break;
    default:
        sendResponse(405, '方法不允许');
        break;
}

/**
 * 处理用户登录
 */
function handleLogin() {
    // 获取POST数据
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['username']) || !isset($input['password'])) {
        sendResponse(400, '缺少必要参数');
        return;
    }
    
    $username = trim($input['username']);
    $password = trim($input['password']);
    
    // 验证输入
    if (empty($username) || empty($password)) {
        sendResponse(400, '用户名和密码不能为空');
        return;
    }
    
    // 验证登录（不使用会话机制）
    $user = validateLogin($username, $password);
    
    if ($user) {
        
        // 生成API令牌（简化版，实际项目中应该使用更安全的令牌机制）
        $token = generateApiToken($user['id']);
        
        sendResponse(200, '登录成功', [
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'role' => $user['role'],
                'base_id' => $user['base_id']
            ]
        ]);
    } else {
        sendResponse(401, '用户名或密码错误');
    }
}

/**
 * 获取应用名称
 */
function handleGetAppName() {
    // 从数据库获取应用名称和版本
    $appName = getAppName();
    $appVersion = getAppVersion();
    
    sendResponse(200, '获取成功', [
        'app_name' => $appName,
        'version' => $appVersion,
        'description' => '玻璃仓储管理系统'
    ]);
}

/**
 * 检查登录状态
 */
function handleCheckLogin() {
    // 获取令牌
    $token = getBearerToken();
    
    if (!$token) {
        sendResponse(401, '未提供认证令牌');
        return;
    }
    
    // 验证令牌（简化版）
    $userId = validateApiToken($token);
    
    if ($userId) {
        // 获取用户信息
        $user = fetchRow("SELECT id, username, real_name as name, role, base_id FROM users WHERE id = ?", [$userId]);
        
        if ($user) {
            sendResponse(200, '用户已登录', [
                'user' => $user
            ]);
        } else {
            sendResponse(404, '用户不存在');
        }
    } else {
        sendResponse(401, '令牌无效或已过期');
    }
}

/**
 * 生成API令牌（简化版）
 * 实际项目中应该使用JWT或其他安全的令牌机制
 */
function generateApiToken($userId) {
    $tokenData = [
        'user_id' => $userId,
        'created_at' => time(),
        'expires_at' => time() + (24 * 60 * 60) // 24小时过期
    ];
    
    return base64_encode(json_encode($tokenData));
}

/**
 * 验证API令牌
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
 * 验证用户登录（无会话版本）
 */
function validateLogin($username, $password) {
    // 查询用户信息
    $user = fetchRow("SELECT id, username, real_name as name, role, base_id, password FROM users WHERE username = ? AND status = 1", [$username]);
    
    if (!$user) {
        return false;
    }
    
    // 验证密码（假设使用password_hash加密）
    if (password_verify($password, $user['password'])) {
        // 移除密码字段，不返回给客户端
        unset($user['password']);
        return $user;
    }
    
    return false;
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