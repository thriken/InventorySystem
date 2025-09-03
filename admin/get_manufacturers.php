<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

// 要求用户登录
requireLogin();

header('Content-Type: application/json');

$brandName = $_GET['brand'] ?? '';

if (empty($brandName)) {
    echo json_encode([]);
    exit;
}

try {
    // 获取品牌ID
    $brand = fetchRow("SELECT id FROM dictionary_items WHERE category = 'brand' AND name = ? AND status = 1", [$brandName]);
    
    if (!$brand) {
        echo json_encode([]);
        exit;
    }
    
    // 获取该品牌下的生产商
    $manufacturers = fetchAll("SELECT code, name FROM dictionary_items WHERE category = 'manufacturer' AND parent_id = ? AND status = 1 ORDER BY sort_order, name", [$brand['id']]);
    
    echo json_encode($manufacturers);
    
} catch (Exception $e) {
    echo json_encode([]);
}
?>