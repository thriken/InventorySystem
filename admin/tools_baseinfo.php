<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/admin_layout.php';
require '../vendor/autoload.php';

// 要求用户登录
requireLogin();

// 检查是否为管理员或经理
requireRole(['admin', 'manager']);

// 获取当前用户信息
$currentUser = getCurrentUser();

// 处理表单提交
$message = '';
$messageType = '';
$importResults = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'import' && isset($_FILES['excel_file'])) {
            $uploadedFile = $_FILES['excel_file'];

            // 验证文件
            if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('文件上传失败');
            }

            $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExtension, ['xlsx', 'xls'])) {
                throw new Exception('请上传Excel文件（.xlsx或.xls格式）');
            }

            // 处理Excel文件
            $importResults = processExcelImport($uploadedFile['tmp_name'], $currentUser);
            $message = '导入完成！';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = '错误：' . $e->getMessage();
        $messageType = 'error';
    }
}

/**
 * 处理Excel导入
 */
function processExcelImport($filePath, $currentUser)
{
    $results = [
        'bases' => ['success' => 0, 'error' => 0, 'errors' => []],
        'racks' => ['success' => 0, 'error' => 0, 'errors' => []],
        'glass_types' => ['success' => 0, 'error' => 0, 'errors' => []]
    ];

    try {
        // 使用PhpSpreadsheet读取Excel文件
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $spreadsheet = $reader->load($filePath);

        // 导入基地数据
        if ($spreadsheet->getSheetCount() > 0) {
            $sheet = $spreadsheet->getSheet(0);
            $results['bases'] = importBases($sheet);
        }

        // 导入库位数据
        if ($spreadsheet->getSheetCount() > 1) {
            $sheet = $spreadsheet->getSheet(1);
            $results['racks'] = importRacks($sheet);
        }

        // 导入原片类型数据
        if ($spreadsheet->getSheetCount() > 2) {
            $sheet = $spreadsheet->getSheet(2);
            $results['glass_types'] = importGlassTypes($sheet);
        }
        return $results;
    } catch (Exception $e) {
        throw new Exception('Excel文件处理失败：' . $e->getMessage());
    }
}

/**
 * 导入基地数据
 */
function importBases($sheet)
{
    $result = ['success' => 0, 'error' => 0, 'errors' => []];
    $highestRow = $sheet->getHighestRow();

    for ($row = 2; $row <= $highestRow; $row++) {
        try {
            $name = trim($sheet->getCell('A' . $row)->getValue());
            $code = trim($sheet->getCell('B' . $row)->getValue());
            $address = trim($sheet->getCell('C' . $row)->getValue());

            if (empty($name) || empty($code)) {
                $result['errors'][] = "第{$row}行：基地名称和编码不能为空";
                $result['error']++;
                continue;
            }

            // 检查是否已存在
            $existing = fetchRow("SELECT id FROM bases WHERE code = ?", [$code]);
            if ($existing) {
                $result['errors'][] = "第{$row}行：基地编码 {$code} 已存在";
                $result['error']++;
                continue;
            }

            query(
                "INSERT INTO bases (name, code, address) VALUES (?, ?, ?)",
                [$name, $code, $address]
            );
            $result['success']++;
        } catch (Exception $e) {
            $result['errors'][] = "第{$row}行：" . $e->getMessage();
            $result['error']++;
        }
    }

    return $result;
}

/**
 * 导入库位数据
 */
function importRacks($sheet)
{
    $result = ['success' => 0, 'error' => 0, 'errors' => []];
    $highestRow = $sheet->getHighestRow();

    for ($row = 2; $row <= $highestRow; $row++) {
        try {
            $baseCode = trim($sheet->getCell('A' . $row)->getValue());
            $name = trim($sheet->getCell('B' . $row)->getValue());
            $areaType = trim($sheet->getCell('C' . $row)->getValue());
            $capacity = (int)$sheet->getCell('D' . $row)->getValue();
            $status = trim($sheet->getCell('E' . $row)->getValue()) ?: 'normal'; // 新增状态字段

            if (empty($baseCode) || empty($name) || empty($areaType)) {
                $result['errors'][] = "第{$row}行：基地编码、库位名称和区域类型不能为空";
                $result['error']++;
                continue;
            }
            // 验证状态值
            $validStatuses = ['normal', 'maintenance', 'full'];
            if (!in_array($status, $validStatuses)) {
                $status = 'normal'; // 默认为正常状态
            }
            // 获取基地ID
            $base = fetchRow("SELECT id FROM bases WHERE code = ?", [$baseCode]);
            if (!$base) {
                $result['errors'][] = "第{$row}行：基地编码 {$baseCode} 不存在";
                $result['error']++;
                continue;
            }
            // 生成库位编码
            $code = $baseCode . '-' . strtoupper(AREATYPENAMES[$areaType]) . '-' . $name;
            // 检查是否已存在
            $existing = fetchRow("SELECT id FROM storage_racks WHERE code = ?", [$code]);
            if ($existing) {
                $result['errors'][] = "第{$row}行：库位编码 {$code} 已存在";
                $result['error']++;
                continue;
            }
            // 修改插入语句，添加status和created_at字段
            query(
                "INSERT INTO storage_racks (base_id, code, name, area_type, capacity, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$base['id'], $code, $name, $areaType, $capacity, $status, date('Y-m-d H:i:s')]
            );
            $result['success']++;
        } catch (Exception $e) {
            $result['errors'][] = "第{$row}行：" . $e->getMessage();
            $result['error']++;
        }
    }
    return $result;
}

/**
 * 导入原片类型数据
 */
function importGlassTypes($sheet)
{
    $result = ['success' => 0, 'error' => 0, 'errors' => []];
    $highestRow = $sheet->getHighestRow();

    for ($row = 2; $row <= $highestRow; $row++) {
        try {
            $customId = trim($sheet->getCell('A' . $row)->getValue());
            $name = trim($sheet->getCell('B' . $row)->getValue());
            $shortName = trim($sheet->getCell('C' . $row)->getValue());
            $financeName = trim($sheet->getCell('D' . $row)->getValue());
            $productSeries = trim($sheet->getCell('E' . $row)->getValue());
            $brand = trim($sheet->getCell('F' . $row)->getValue());
            $manufacturer = trim($sheet->getCell('G' . $row)->getValue());
            $color = trim($sheet->getCell('H' . $row)->getValue());
            $thickness = floatval($sheet->getCell('I' . $row)->getValue());
            $silverLayers = trim($sheet->getCell('J' . $row)->getValue());
            $substrate = trim($sheet->getCell('K' . $row)->getValue());
            $transmittance = trim($sheet->getCell('L' . $row)->getValue());

            // 在原片类型导入函数中，将错误信息改为更具体的格式
            if (empty($customId)) {
                $result['errors'][] = "第{$row}行：原片ID '{$customId}' 不能为空";
                $result['error']++;
                continue;
            }
            if (empty($name)) {
                $result['errors'][] = "第{$row}行：原片名称 '{$name}' 不能为空";
                $result['error']++;
                continue;
            }

            if (empty($shortName)) {
                $result['errors'][] = "第{$row}行：原片简称不能为空";
                $result['error']++;
                continue;
            }
            if (empty($financeName)) {
                $result['errors'][] = "第{$row}行：财务核算名不能为空";
                $result['error']++;
                continue;
            }
            if (empty($productSeries)) {
                $result['errors'][] = "第{$row}行：商色系不能为空";
                $result['error']++;
                continue;
            }
            if (empty($brand)) {
                $result['errors'][] = "第{$row}行：品牌不能为空";
                $result['error']++;
                continue;
            }
            if (empty($color)) {
                $result['errors'][] = "第{$row}行：颜色不能为空";
                $result['error']++;
                continue;
            }
            if ($thickness <= 0) {
                $result['errors'][] = "第{$row}行：厚度必须大于0";
                $result['error']++;
                continue;
            }
            
            // 处理允许为空的字段，确保它们不是 null
            $manufacturer = $manufacturer ?: '';
            $silverLayers = $silverLayers ?: '';
            $substrate = $substrate ?: '';
            $transmittance = $transmittance ?: '';

            // 检查是否已存在
            $existing = fetchRow("SELECT * FROM glass_types WHERE custom_id = ?", [$customId]);
            if ($existing) {
                // 检查数据是否需要更新
                $needUpdate = false;
                $updateFields = [];
                $updateValues = [];
                
                if ($existing['name'] !== $name) {
                    $needUpdate = true;
                    $updateFields[] = 'name = ?';
                    $updateValues[] = $name;
                }
                if ($existing['short_name'] !== $shortName) {
                    $needUpdate = true;
                    $updateFields[] = 'short_name = ?';
                    $updateValues[] = $shortName;
                }
                if ($existing['finance_name'] !== $financeName) {
                    $needUpdate = true;
                    $updateFields[] = 'finance_name = ?';
                    $updateValues[] = $financeName;
                }
                if ($existing['product_series'] !== $productSeries) {
                    $needUpdate = true;
                    $updateFields[] = 'product_series = ?';
                    $updateValues[] = $productSeries;
                }
                if ($existing['brand'] !== $brand) {
                    $needUpdate = true;
                    $updateFields[] = 'brand = ?';
                    $updateValues[] = $brand;
                }
                if ($existing['manufacturer'] !== $manufacturer) {
                    $needUpdate = true;
                    $updateFields[] = 'manufacturer = ?';
                    $updateValues[] = $manufacturer;
                }
                if ($existing['color'] !== $color) {
                    $needUpdate = true;
                    $updateFields[] = 'color = ?';
                    $updateValues[] = $color;
                }
                if (floatval($existing['thickness']) !== $thickness) {
                    $needUpdate = true;
                    $updateFields[] = 'thickness = ?';
                    $updateValues[] = $thickness;
                }
                if ($existing['silver_layers'] !== $silverLayers) {
                    $needUpdate = true;
                    $updateFields[] = 'silver_layers = ?';
                    $updateValues[] = $silverLayers;
                }
                if ($existing['substrate'] !== $substrate) {
                    $needUpdate = true;
                    $updateFields[] = 'substrate = ?';
                    $updateValues[] = $substrate;
                }
                if ($existing['transmittance'] !== $transmittance) {
                    $needUpdate = true;
                    $updateFields[] = 'transmittance = ?';
                    $updateValues[] = $transmittance;
                }
                
                if ($needUpdate) {
                    // 执行更新
                    $updateFields[] = 'updated_at = ?';
                    $updateValues[] = date('Y-m-d H:i:s');
                    $updateValues[] = $customId; // WHERE条件的参数
                    
                    query(
                        "UPDATE glass_types SET " . implode(', ', $updateFields) . " WHERE custom_id = ?",
                        $updateValues
                    );
                    $result['success']++;
                } else {
                    if ($existing) {
                        $result['errors'][] = "第{$row}行：原片ID {$customId} 已存在";
                        $result['error']++;
                        continue;
                    }
                    continue;
                }
            } else {
                // 不存在，执行插入
                query(
                    "INSERT INTO glass_types (custom_id, name, short_name, finance_name, product_series, brand, manufacturer, color, thickness, silver_layers, substrate, transmittance, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$customId, $name, $shortName, $financeName, $productSeries, $brand, $manufacturer, $color, $thickness, $silverLayers, $substrate, $transmittance, date('Y-m-d H:i:s')]
                );
                $result['success']++;
            }
        } catch (Exception $e) {
            $result['errors'][] = "第{$row}行：" . $e->getMessage();
            $result['error']++;
        }
    }

    return $result;
}
// 页面内容
ob_start();
?>

<style>
    .import-container {
        max-width: 1000px;
        margin: 0 auto;
    }

    .template-section,
    .upload-section,
    .results-section {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .template-buttons {
        margin: 15px 0;
    }

    .template-buttons .btn {
        margin-right: 10px;
        margin-bottom: 10px;
    }

    .template-info {
        background: #e9ecef;
        border-radius: 5px;
        padding: 15px;
        margin-top: 15px;
    }

    .template-info ul {
        margin: 10px 0;
        padding-left: 20px;
    }

    .template-info .note {
        color: #6c757d;
        font-style: italic;
        margin-top: 10px;
    }

    .file-input-container {
        position: relative;
        display: inline-block;
        margin-bottom: 15px;
    }

    .file-input-container input[type="file"] {
        position: absolute;
        opacity: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
    }

    .file-input-label {
        display: inline-block;
        padding: 10px 20px;
        background: #007bff;
        color: white;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    .file-input-label:hover {
        background: #0056b3;
    }

    .result-item {
        border: 1px solid #dee2e6;
        border-radius: 5px;
        padding: 15px;
        margin-bottom: 15px;
        background: white;
    }

    .result-stats {
        margin: 10px 0;
    }

    .success-count {
        color: #28a745;
        font-weight: bold;
        margin-right: 15px;
    }

    .error-count {
        color: #dc3545;
        font-weight: bold;
    }

    .error-details {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        border-radius: 5px;
        padding: 10px;
        margin-top: 10px;
    }

    .error-details ul {
        margin: 5px 0;
        padding-left: 20px;
    }

    .error-details li {
        color: #721c24;
        margin-bottom: 5px;
    }
</style>

<div class="content-section">
    <div class="section-header">
        <p class="section-description">基础数据导入</p>
    </div>
    <div class="import-container">
        <!-- 模板下载区域 -->
        <div class="template-section">
            <h3>1. 下载Excel模板</h3>
            <p>请先下载对应的Excel模板，按照模板格式填写数据：</p>
            <div class="template-buttons">
                <a href="../assets/template/import_template.xlsx" class="btn btn-primary" download>
                    <i class="icon-download"></i> 下载导入模板
                </a>
                <a href="../assets/template/import_example.xlsx" class="btn btn-secondary" download>
                    <i class="icon-file"></i> 下载示例数据
                </a>
            </div>

            <div class="template-info">
                <h4>模板说明：</h4>
                <ul>
                    <li><strong>Sheet1 - 基地信息：</strong>基地名称、基地编码、基地地址</li>
                    <li><strong>Sheet2 - 库位信息：</strong>基地编码、库位名称、区域类型、容量、状态</li>
                    <li><strong>Sheet3 - 原片类型：</strong>原片ID、名称、简称、财务核算名、色系、品牌、生产商、颜色、厚度</li>
                </ul>
                <p class="note">注意：请严格按照模板格式填写，第一行为标题行，数据从第二行开始。</p>
                <div class="field-details">
                    <h5>字段说明：</h5>
                    <ul>
                        <li><strong>区域类型：</strong>storage（库存区）、processing（加工区）、scrap（报废区）、temporary（临时区）</li>
                        <li><strong>状态：</strong>normal（正常）、maintenance（维护中）、full（已满），可选字段，默认为normal</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- 文件上传区域 -->
        <div class="upload-section">
            <h3>2. 上传Excel文件</h3>
            <form method="post" enctype="multipart/form-data" class="upload-form">
                <input type="hidden" name="action" value="import">

                <div class="file-input-container">
                    <input type="file" name="excel_file" id="excel_file" accept=".xlsx,.xls" required>
                    <label for="excel_file" class="file-input-label">
                        <i class="icon-upload"></i>
                        <span>选择Excel文件</span>
                    </label>
                </div>

                <div class="upload-actions">
                    <button type="submit" class="btn btn-success">
                        <i class="icon-import"></i> 开始导入
                    </button>
                </div>
            </form>
        </div>

        <!-- 导入结果显示 -->
        <?php if (!empty($importResults)): ?>
            <div class="results-section">
                <h3>3. 导入结果</h3>

                <?php foreach ($importResults as $type => $result): ?>
                    <?php if ($result['success'] > 0 || $result['error'] > 0): ?>
                        <div class="result-item">
                            <h4><?php
                                $typeNames = [
                                    'bases' => '基地信息',
                                    'racks' => '库位信息',
                                    'glass_types' => '原片类型',
                                    'packages' => '原片包信息'
                                ];
                                echo $typeNames[$type] ?? $type;
                                ?></h4>

                            <div class="result-stats">
                                <span class="success-count">成功：<?php echo $result['success']; ?>条</span>
                                <span class="error-count">失败：<?php echo $result['error']; ?>条</span>
                            </div>

                            <?php if (!empty($result['errors'])): ?>
                                <div class="error-details">
                                    <h5>错误详情：</h5>
                                    <ul>
                                        <?php foreach ($result['errors'] as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // 文件选择提示
    document.getElementById('excel_file').addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name || '选择Excel文件';
        const label = document.querySelector('.file-input-label span');
        label.textContent = fileName;
    });

    // 表单提交确认
    document.querySelector('.upload-form').addEventListener('submit', function(e) {
        const fileInput = document.getElementById('excel_file');
        if (!fileInput.files.length) {
            e.preventDefault();
            alert('请选择要导入的Excel文件');
            return;
        }

        if (!confirm('确定要导入数据吗？导入过程可能需要一些时间。')) {
            e.preventDefault();
        }
    });
</script>

<?php
$content = ob_get_clean();

// 渲染页面
echo renderAdminLayout('基础数据导入工具', $content, $currentUser, 'tools_baseinfo.php', [], [], $message, $messageType);
?>