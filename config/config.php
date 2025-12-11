<?php
/**
 * Version: v1.1.0 
 * Author: <Wangxin github.com/thriken>
 * Project: 库存管理系统
 * description: 此版本主要是增加盘点功能
 * Summary: 系统配置文件
 */
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST']);
define('MOBILE_BREAKPOINT', 768); // 移动设备断点

// 系统路径
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('API_PATH', ROOT_PATH . '/api');
define('ASSETS_PATH', ROOT_PATH . '/assets');

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 错误报告设置
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 会话设置
session_start();

// 设备分辨率配置
define('PDA_RESOLUTION', '720x1280');
define('PC_RESOLUTION', '1920x1080');

// 区域类型定义
define('AREA_TEMPORARY', 'temporary'); // 采购区
define('AREA_STORAGE', 'storage');   // 库存区
define('AREA_SCRAP', 'scrap');       // 报废区
define('AREA_PROCESSING', 'processing'); // 加工区

// 区域类型中文映射
define('AREA_TYPE_NAMES', [
    'storage' => '存储区', 
    'temporary' => '临时区',
    'processing' => '加工区',
    'scrap' => '报废区'
]);

// 区域类型简称映射
define('AREATYPENAMES', [
    'storage' => 'N', 
    'temporary' => 'T',
    'processing' => 'P',
    'scrap' => 'B'
]);
// 包状态中文映射
define('PACKAGE_STATUS_NAMES', [
    'in_stock' => '在库',
    'out_stock' => '出库',
    'scrapped' => '已报废',
    'in_storage' => '库存中', 
    'in_processing' => '加工中',
    'used_up' => '已用完'
]);

// 交易类型中文映射
define('TRANSACTION_TYPE_NAMES', [
    'purchase_in' => '采购入库',
    'usage_out' => '领用出库',
    'return_in' => '归还入库',
    'scrap' => '报废出库',
    'partial_usage' => '部分领用',
    'location_adjust' => '库位调整',
    'check_in' => '盘盈入库',
    'check_out' => '盘亏出库'
]);
define('ROLE_NAMES',[
    'admin' => '管理员',
    'viewer' => '查看者',
    'operator' => '操作员',
    'manager' => '库管'
]);

?>