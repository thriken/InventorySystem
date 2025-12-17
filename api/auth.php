<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/app_info.php';
require_once __DIR__ . '/ApiCommon.php';

// 设置响应头和处理预检请求
ApiCommon::setHeaders();
ApiCommon::handlePreflight();

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
        ApiCommon::sendResponse(405, '方法不允许');
        break;
}

/**
 * 处理用户登录
 */
function handleLogin() {
    // 获取POST数据
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['username']) || !isset($input['password'])) {
        ApiCommon::sendResponse(400, '缺少必要参数');
        return;
    }
    
    $username = trim($input['username']);
    $password = trim($input['password']);
    
    // 验证输入
    if (empty($username) || empty($password)) {
        ApiCommon::sendResponse(400, '用户名和密码不能为空');
        return;
    }
    
    // 验证登录（不使用会话机制）
    $user = validateLogin($username, $password);
    
    if ($user) {
        
        // 生成API令牌（简化版，实际项目中应该使用更安全的令牌机制）
        $token = generateApiToken($user['id']);
        
        ApiCommon::sendResponse(200, '登录成功', [
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
        ApiCommon::sendResponse(401, '用户名或密码错误');
    }
}

/**
 * 获取应用名称
 */
function handleGetAppName() {
    // 从数据库获取应用名称和版本
    $appName = getAppName();
    $appVersion = getAppVersion();
    
    ApiCommon::sendResponse(200, '获取成功', [
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
    $token = ApiCommon::getBearerToken();
    
    if (!$token) {
        ApiCommon::sendResponse(401, '未提供认证令牌');
        return;
    }
    
    // 验证令牌（简化版）
    $userId = ApiCommon::validateApiToken($token);
    
    if ($userId) {
        // 获取用户信息
        $user = fetchRow("SELECT id, username, real_name as name, role, base_id FROM users WHERE id = ?", [$userId]);
        
        if ($user) {
            ApiCommon::sendResponse(200, '用户已登录', [
                'user' => $user
            ]);
        } else {
            ApiCommon::sendResponse(404, '用户不存在');
        }
    } else {
        ApiCommon::sendResponse(401, '令牌无效或已过期');
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
        'expires_at' => time() + 24 * 60 * 60 // 24小时过期
    ];
    
    return base64_encode(json_encode($tokenData));
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