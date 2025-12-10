<?php
/**
 * 应用信息获取辅助函数
 * 从数据库获取应用名称和版本信息
 */

require_once __DIR__ . '/db.php';

/**
 * 获取应用名称
 */
function getAppName() {
    try {
        // 从数据库设置表获取应用名称
        $setting = fetchRow("SELECT value FROM settings WHERE setting_key = 'system_name' LIMIT 1");
        
        if ($setting && !empty($setting['value'])) {
            return $setting['value'];
        }
        
        // 默认应用名称
        return '玻璃仓储管理系统';
        
    } catch (Exception $e) {
        // 如果数据库连接失败，返回默认名称
        return '玻璃仓储管理系统';
    }
}

/**
 * 获取应用版本
 */
function getAppVersion() {
    try {
        // 从数据库设置表获取应用版本
        $setting = fetchRow("SELECT value FROM settings WHERE setting_key = 'system_version' LIMIT 1");
        
        if ($setting && !empty($setting['value'])) {
            return $setting['value'];
        }
        
        // 默认版本
        return '1.0.0';
        
    } catch (Exception $e) {
        // 如果数据库连接失败，返回默认版本
        return '1.0.0';
    }
}

/**
 * 获取应用完整信息
 */
function getAppInfo() {
    return [
        'name' => getAppName(),
        'version' => getAppVersion()
    ];
}
?>