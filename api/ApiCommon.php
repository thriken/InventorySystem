<?php
/**
 * API公共工具类
 * 提供统一的认证、响应和工具函数
 */

class ApiCommon {
    
    /**
     * 从请求头获取Bearer令牌
     */
    public static function getBearerToken() {
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
     * 验证API令牌（简化版）
     */
    public static function validateApiToken($token) {
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
     * 发送JSON响应
     */
    public static function sendResponse($code, $message, $data = null) {
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
    
    /**
     * 验证请求方法
     */
    public static function validateMethod($allowedMethods) {
        if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
            self::sendResponse(405, '方法不允许');
        }
    }
    
    /**
     * 验证Token认证
     */
    public static function authenticate() {
        // 优先尝试会话认证（用于内部页面调用）
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user_id'])) {
            // 使用会话认证
            $user = fetchRow("SELECT id, username, real_name as name, role, base_id FROM users WHERE id = ?", [$_SESSION['user_id']]);
            if ($user) {
                return $user;
            }
        }
        
        // 备用：Bearer Token认证（用于外部API调用）
        $token = self::getBearerToken();
        if ($token) {
            $userId = self::validateApiToken($token);
            if ($userId) {
                $user = fetchRow("SELECT id, username, real_name as name, role, base_id FROM users WHERE id = ?", [$userId]);
                if ($user) {
                    return $user;
                }
            }
        }
        
        self::sendResponse(401, '认证失败，请重新登录');
    }
    
    /**
     * 处理预检请求
     */
    public static function handlePreflight() {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
    
    /**
     * 设置响应头
     */
    public static function setHeaders() {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }
}
?>