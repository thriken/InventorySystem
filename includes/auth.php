<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * 验证用户登录
 */
function login($username, $password) {
    $sql = "SELECT id, username, password, real_name, role, base_id FROM users WHERE username = ?";
    $user = fetchRow($sql, [$username]);
    
    if (!$user) {
        return false;
    }
    
    if (password_verify($password, $user['password'])) {
        // 登录成功，设置会话
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['name'] = $user['real_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['base_id'] = $user['base_id'];
        $_SESSION['logged_in'] = true;
        
        return true;
    }
    
    return false;
}

/**
 * 检查用户是否已登录
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * 检查用户是否为管理员
 */
function isAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

/**
 * 获取当前登录用户ID
 */
function getCurrentUserId() {
    return isLoggedIn() ? $_SESSION['user_id'] : null;
}

/**
 * 获取当前登录用户信息
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'name' => $_SESSION['name'],
        'role' => $_SESSION['role'],
        'base_id' => $_SESSION['base_id'] ?? null
    ];
}

/**
 * 注销登录
 */
function logout() {
    // 清除所有会话变量
    $_SESSION = [];
    
    // 如果使用了会话cookie，则清除会话cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // 销毁会话
    session_destroy();
}

/**
 * 要求用户登录
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/login.php');
    }
}

/**
 * 要求管理员权限
 */
function requireAdmin() {
    requireLogin();
    
    if (!isAdmin()) {
        redirect(BASE_URL . '/index.php?error=unauthorized');
    }
}

/**
 * 要求特定角色权限
 * @param array|string $roles 允许的角色数组或单个角色
 */
function requireRole($roles) {
    requireLogin();
    
    // 如果传入的是字符串，转换为数组
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    // 检查当前用户角色是否在允许的角色列表中
    if (!in_array($_SESSION['role'], $roles)) {
        redirect(BASE_URL . '/index.php?error=unauthorized');
    }
}

/**
 * 创建新用户
 */
function createUser($username, $password, $name, $role) {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $data = [
        'username' => $username,
        'password' => $hashedPassword,
        'name' => $name,
        'role' => $role
    ];
    
    return insert('users', $data);
}

/**
 * 更新用户密码
 */
function updatePassword($userId, $newPassword) {
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $data = ['password' => $hashedPassword];
    $where = 'id = ?';
    $params = [$userId];
    
    return update('users', $data, $where, $params);
}
?>