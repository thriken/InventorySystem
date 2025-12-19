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
        $rackUpdates = 0;
        
        foreach ($data as $index => $row) {
            $packageCode = trim($row['package_code'] ?? '');
            $checkQuantity = intval($row['check_quantity'] ?? 0);
            $rackCode = trim($row['rack_code'] ?? '');
            $notes = trim($row['notes'] ?? '');
            
            if (!$packageCode || $checkQuantity <= 0) {
                $errorMsg = "包号 {$packageCode} 数据无效";
                $errors[] = $errorMsg;
                continue;
            }
            
            // 检查包是否存在
            $package = fetchRow("SELECT p.id, p.pieces, p.current_rack_id 
                                FROM glass_packages p 
                                WHERE p.package_code = ? AND p.status = 'in_storage'", 
                                [$packageCode]);
            
            if (!$package) {
                $errorMsg = "包 {$packageCode} 不存在或不在库存状态";
                debug_log("错误: $errorMsg");
                $errors[] = $errorMsg;
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
            
            // 处理库位号
            $rackId = $package['current_rack_id'];
            $rackUpdateCount = 0;
            
            if ($rackCode) {
                // 根据库位编码或名称查找库位ID（支持模糊匹配）
                $rack = fetchRow("SELECT id, code, name FROM storage_racks 
                                WHERE (code = ? OR name = ?) AND base_id = ? 
                                LIMIT 1", 
                                [$rackCode, $rackCode, getCurrentUser()['base_id']]);
                
                if ($rack) {
                    $rackId = $rack['id'];
                    
                    // 如果新库位与原库位不同，同步更新包的实际库位
                    if ($rackId != $package['current_rack_id']) {
                        $packageUpdate = update('glass_packages', 
                            ['current_rack_id' => $rackId], 
                            'id = ?', 
                            [$package['id']]);
                        
                        if ($packageUpdate > 0) {
                            $rackUpdateCount = 1;
                            $rackUpdates++;
                            $rackDisplay = $rack['code'] != $rackCode ? "{$rack['code']}({$rack['name']})" : $rack['code'];
                            $notes = ($notes ? "$notes\n" : '') . "盘点时同步更新库位到：{$rackDisplay}";
                        }
                    }
                } else {
                    $errorMsg = "包 {$packageCode} 的库位号 {$rackCode} 不存在";
                    $errors[] = $errorMsg;
                    continue;
                }
            }
            
            // 更新盘点缓存
            $difference = $checkQuantity - $package['pieces'];
            
            $updateData = [
                'check_quantity' => $checkQuantity,
                'difference' => $difference,
                'rack_id' => $rackId,
                'check_method' => 'excel_import',
                'check_time' => date('Y-m-d H:i:s'),
                'operator_id' => getCurrentUserId(),
                'notes' => $notes
            ];
            
            $cacheUpdate = update('inventory_check_cache', $updateData, 'task_id = ? AND package_code = ?', [$taskId, $packageCode]);
            
            if ($cacheUpdate > 0) {
                $successCount++;
            }
        }
        
        // 更新任务进度
        $newCount = fetchOne("SELECT COUNT(*) FROM inventory_check_cache WHERE task_id = ? AND check_quantity > 0", [$taskId]);
        $differenceCount = fetchOne("SELECT SUM(ABS(difference)) FROM inventory_check_cache WHERE task_id = ? AND check_quantity > 0", [$taskId]);
        
        $taskUpdate = update('inventory_check_tasks', [
            'checked_packages' => $newCount,
            'difference_count' => $differenceCount ?: 0
        ], 'id = ?', [$taskId]);
        
        commitTransaction();
        
        // 删除临时文件
        @unlink($filePath);
        
        // 显示结果
        showImportResult($successCount, $errors, $duplicates, $rackUpdates, count($data));
        
    } catch (Exception $e) {
        rollbackTransaction();
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
        '库位号' => 'rack_code',
        'rack_code' => 'rack_code',
        '库位' => 'rack_code',
        '库位编码' => 'rack_code',
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
        '库位号' => 'rack_code',
        'rack_code' => 'rack_code',
        '库位' => 'rack_code',
        '库位编码' => 'rack_code',
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
function showImportResult($successCount, $errors, $duplicates, $rackUpdates, $totalCount) {
    global $taskId;
    
    include 'inventory_check_import_result.php';
}
?>