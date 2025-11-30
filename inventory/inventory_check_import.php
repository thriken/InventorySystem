<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/inventory_check_auth.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// 检查权限
requireInventoryCheckCreatePermission();

$taskId = $_GET['task_id'] ?? 0;

if (!$taskId) {
    header('Location: inventory_check.php?error=missing_task_id');
    exit;
}

// 获取任务信息
$task = fetchRow("SELECT * FROM inventory_check_tasks WHERE id = ? AND base_id = ?", 
                 [$taskId, getCurrentUser()['base_id']]);

if (!$task) {
    header('Location: inventory_check.php?error=task_not_found');
    exit;
}

if ($task['status'] !== 'in_progress') {
    header('Location: inventory_check.php?error=task_not_in_progress');
    exit;
}

$action = $_POST['action'] ?? 'upload';

switch ($action) {
    case 'upload':
        handleFileUpload();
        break;
    case 'preview':
        handlePreview();
        break;
    case 'confirm':
        handleConfirmImport();
        break;
    default:
        showImportForm();
}

/**
 * 显示导入表单
 */
function showImportForm() {
    global $task, $taskId;
    
    include 'inventory_check_import_form.php';
}

/**
 * 处理文件上传
 */
function handleFileUpload() {
    global $taskId, $task;
    
    if (!isset($_FILES['excel_file'])) {
        $error = '请选择要上传的Excel文件';
        showImportForm();
        return;
    }
    
    $file = $_FILES['excel_file'];
    $allowedTypes = [
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv'
    ];
    
    // 验证文件类型
    if (!in_array($file['type'], $allowedTypes)) {
        $error = '请上传Excel文件(.xls, .xlsx)或CSV文件';
        showImportForm();
        return;
    }
    
    // 验证文件大小 (5MB限制)
    if ($file['size'] > 5 * 1024 * 1024) {
        $error = '文件大小不能超过5MB';
        showImportForm();
        return;
    }
    
    // 创建上传目录
    $uploadDir = '../temp/imports/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // 生成唯一文件名
    $fileName = 'inventory_check_' . $taskId . '_' . time() . '_' . uniqid();
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $uploadPath = $uploadDir . $fileName . '.' . $extension;
    
    // 移动上传文件
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        $error = '文件上传失败';
        showImportForm();
        return;
    }
    
    try {
        // 解析Excel文件
        $data = parseExcelFile($uploadPath, $extension);
        
        // 验证数据格式
        $validation = validateImportData($data, $taskId);
        
        if (!$validation['valid']) {
            $error = $validation['message'];
            showImportForm();
            return;
        }
        
        // 显示预览
        showPreview($data, $uploadPath, $fileName . '.' . $extension);
        
    } catch (Exception $e) {
        $error = '文件解析失败：' . $e->getMessage();
        showImportForm();
    }
}

/**
 * 处理预览
 */
function handlePreview() {
    $filePath = $_POST['file_path'] ?? '';
    $fileName = $_POST['file_name'] ?? '';
    
    if (!$filePath || !file_exists($filePath)) {
        $error = '文件不存在';
        showImportForm();
        return;
    }
    
    try {
        $data = parseExcelFile($filePath, pathinfo($fileName, PATHINFO_EXTENSION));
        showPreview($data, $filePath, $fileName);
    } catch (Exception $e) {
        $error = '文件解析失败：' . $e->getMessage();
        showImportForm();
    }
}

/**
 * 处理确认导入
 */
function handleConfirmImport() {
    global $taskId;
    
    $filePath = $_POST['file_path'] ?? '';
    $fileName = $_POST['file_name'] ?? '';
    
    if (!$filePath || !file_exists($filePath)) {
        $error = '文件不存在';
        showImportForm();
        return;
    }
    
    try {
        $data = parseExcelFile($filePath, pathinfo($fileName, PATHINFO_EXTENSION));
        
        beginTransaction();
        
        $successCount = 0;
        $errors = [];
        $duplicates = [];
        
        foreach ($data as $row) {
            $packageCode = trim($row['package_code'] ?? '');
            $checkQuantity = intval($row['check_quantity'] ?? 0);
            $notes = trim($row['notes'] ?? '');
            
            if (!$packageCode || $checkQuantity <= 0) {
                $errors[] = "包号 {$packageCode} 数据无效";
                continue;
            }
            
            // 检查包是否存在
            $package = fetchRow("SELECT p.id, p.pieces, p.current_rack_id 
                                FROM glass_packages p 
                                WHERE p.package_code = ? AND p.status = 'in_storage'", 
                                [$packageCode]);
            
            if (!$package) {
                $errors[] = "包 {$packageCode} 不存在或不在库存状态";
                continue;
            }
            
            // 检查是否已盘点
            $existing = fetchRow("SELECT id FROM inventory_check_cache 
                                WHERE task_id = ? AND package_code = ? AND check_quantity > 0", 
                                [$taskId, $packageCode]);
            
            if ($existing) {
                $duplicates[] = $packageCode;
                continue;
            }
            
            // 更新盘点缓存
            $difference = $checkQuantity - $package['pieces'];
            
            $updateData = [
                'check_quantity' => $checkQuantity,
                'difference' => $difference,
                'rack_id' => $package['current_rack_id'],
                'check_method' => 'excel_import',
                'check_time' => date('Y-m-d H:i:s'),
                'operator_id' => getCurrentUserId(),
                'notes' => $notes
            ];
            
            update('inventory_check_cache', $updateData, 'task_id = ? AND package_code = ?', [$taskId, $packageCode]);
            $successCount++;
        }
        
        // 更新任务进度
        $newCount = fetchColumn("SELECT COUNT(*) FROM inventory_check_cache WHERE task_id = ? AND check_quantity > 0", [$taskId]);
        update('inventory_check_tasks', ['checked_packages' => $newCount], 'id = ?', [$taskId]);
        
        commit();
        
        // 删除临时文件
        @unlink($filePath);
        
        // 显示结果
        showImportResult($successCount, $errors, $duplicates, count($data));
        
    } catch (Exception $e) {
        rollback();
        $error = '导入失败：' . $e->getMessage();
        showImportForm();
    }
}

/**
 * 解析Excel文件
 */
function parseExcelFile($filePath, $extension) {
    require_once '../vendor/autoload.php';
    
    if ($extension === 'csv') {
        // 处理CSV文件
        return parseCSVFile($filePath);
    } else {
        // 处理Excel文件
        return parseExcelFileWithPhpSpreadsheet($filePath);
    }
}

/**
 * 解析CSV文件
 */
function parseCSVFile($filePath) {
    $data = [];
    $handle = fopen($filePath, 'r');
    
    if (!$handle) {
        throw new Exception('无法打开CSV文件');
    }
    
    // 读取标题行
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        throw new Exception('CSV文件格式错误');
    }
    
    // 标准化列名
    $headerMap = [
        '包号' => 'package_code',
        'package_code' => 'package_code',
        '包编号' => 'package_code',
        '盘点数量' => 'check_quantity',
        'check_quantity' => 'check_quantity',
        '数量' => 'check_quantity',
        '备注' => 'notes',
        'notes' => 'notes',
        '说明' => 'notes'
    ];
    
    $mappedHeaders = [];
    foreach ($headers as $header) {
        $header = trim($header);
        $mappedHeaders[] = $headerMap[$header] ?? $header;
    }
    
    // 读取数据行
    $rowIndex = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $rowIndex++;
        if (count($row) < count($mappedHeaders)) {
            continue; // 跳过不完整的行
        }
        
        $rowData = [];
        foreach ($mappedHeaders as $index => $fieldName) {
            $rowData[$fieldName] = $row[$index] ?? '';
        }
        
        if (!empty($rowData['package_code'])) {
            $data[] = $rowData;
        }
    }
    
    fclose($handle);
    return $data;
}

/**
 * 使用PhpSpreadsheet解析Excel文件
 */
function parseExcelFileWithPhpSpreadsheet($filePath) {
    $spreadsheet = IOFactory::load($filePath);
    $worksheet = $spreadsheet->getActiveSheet();
    $data = [];
    
    // 读取数据
    $highestRow = $worksheet->getHighestDataRow();
    $highestColumn = $worksheet->getHighestDataColumn();
    
    // 获取标题行
    $headers = [];
    for ($col = 'A'; $col <= $highestColumn; $col++) {
        $cellValue = $worksheet->getCell($col . '1')->getValue();
        if ($cellValue) {
            $headers[$col] = trim($cellValue);
        }
    }
    
    // 标准化列名
    $headerMap = [
        '包号' => 'package_code',
        'package_code' => 'package_code',
        '包编号' => 'package_code',
        '盘点数量' => 'check_quantity',
        'check_quantity' => 'check_quantity',
        '数量' => 'check_quantity',
        '备注' => 'notes',
        'notes' => 'notes',
        '说明' => 'notes'
    ];
    
    $mappedHeaders = [];
    foreach ($headers as $col => $header) {
        $mappedHeaders[$col] = $headerMap[$header] ?? $header;
    }
    
    // 读取数据行
    for ($row = 2; $row <= $highestRow; $row++) {
        $rowData = [];
        $hasData = false;
        
        foreach ($mappedHeaders as $col => $fieldName) {
            $cellValue = $worksheet->getCell($col . $row)->getValue();
            $rowData[$fieldName] = $cellValue;
            
            if ($fieldName === 'package_code' && !empty($cellValue)) {
                $hasData = true;
            }
        }
        
        if ($hasData) {
            $data[] = $rowData;
        }
    }
    
    return $data;
}

/**
 * 验证导入数据
 */
function validateImportData($data, $taskId) {
    if (empty($data)) {
        return ['valid' => false, 'message' => '文件中没有有效数据'];
    }
    
    // 检查必需列
    $firstRow = $data[0];
    $requiredColumns = ['package_code', 'check_quantity'];
    
    foreach ($requiredColumns as $col) {
        if (!isset($firstRow[$col])) {
            return ['valid' => false, 'message' => "缺少必需列：{$col}"];
        }
    }
    
    return ['valid' => true, 'message' => '数据格式正确'];
}

/**
 * 显示预览
 */
function showPreview($data, $filePath, $fileName) {
    global $task, $taskId;
    
    // 限制预览行数
    $previewData = array_slice($data, 0, 20);
    
    include 'inventory_check_import_preview.php';
}

/**
 * 显示导入结果
 */
function showImportResult($successCount, $errors, $duplicates, $totalCount) {
    global $taskId;
    
    include 'inventory_check_import_result.php';
}
?>