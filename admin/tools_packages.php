<?php
/**
 * 原片包导入页面 库管员可用
 */
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/admin_layout.php';
require '../vendor/autoload.php';

// 要求用户登录
requireLogin();

// 检查权限：只允许库管员导入原片包
requireRole(['manager']);

// 获取当前用户信息
$currentUser = getCurrentUser();

// 处理表单提交
$message = '';
$messageType = '';
$importResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_FILES['excel_file'])) {
            $uploadedFile = $_FILES['excel_file'];

            // 验证文件
            if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('文件上传失败');
            }

            $fileExtension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExtension, ['xlsx', 'xls'])) {
                throw new Exception('请上传Excel文件（.xlsx或.xls格式）');
            }

            // 处理原片包导入
            $importResult = importPackagesOnly($uploadedFile['tmp_name'], $currentUser);
            $message = '原片包导入完成！';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = '错误：' . $e->getMessage();
        $messageType = 'error';
    }
}

/**
 * 专门处理原片包导入
 */
function importPackagesOnly($filePath, $currentUser)
{
    $result = ['success' => 0, 'error' => 0, 'errors' => []];

    try {
        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $spreadsheet = $reader->load($filePath);

        // 只处理第一个Sheet作为原片包数据
        $sheet = $spreadsheet->getSheet(0);
        $highestRow = $sheet->getHighestRow();

        for ($row = 2; $row <= $highestRow; $row++) {
            try {
                $packageCode = trim($sheet->getCell('A' . $row)->getValue());
                $glassTypeId = trim($sheet->getCell('B' . $row)->getValue());
                $width = floatval($sheet->getCell('C' . $row)->getValue());
                $height = floatval($sheet->getCell('D' . $row)->getValue());
                $pieces = (int)$sheet->getCell('E' . $row)->getValue();
                $entryDate = $sheet->getCell('F' . $row)->getValue();
                $rackName = trim($sheet->getCell('G' . $row)->getValue());

                if (empty($packageCode) || empty($glassTypeId)) {
                    $result['errors'][] = "第{$row}行：包号和原片类型ID不能为空";
                    $result['error']++;
                    continue;
                }

                // 获取原片类型ID
                $glassType = fetchRow("SELECT id FROM glass_types WHERE custom_id = ?", [$glassTypeId]);
                if (!$glassType) {
                    $result['errors'][] = "第{$row}行：原片类型ID {$glassTypeId} 不存在";
                    $result['error']++;
                    continue;
                }

                // 获取库位ID（基于当前用户的基地）
                $rackId = null;
                if (!empty($rackName)) {
                    $rack = fetchRow(
                        "SELECT sr.id FROM storage_racks sr 
                         LEFT JOIN bases b ON sr.base_id = b.id 
                         WHERE sr.name = ? AND b.id = ?", 
                        [$rackName, $currentUser['base_id']]
                    );
                    if (!$rack) {
                        $result['errors'][] = "第{$row}行：库位号 {$rackName} 在当前基地不存在";
                        $result['error']++;
                        continue;
                    }
                    $rackId = $rack['id'];
                }

                // 检查包号是否已存在
                $existing = fetchRow("SELECT id FROM glass_packages WHERE package_code = ?", [$packageCode]);
                if ($existing) {
                    $result['errors'][] = "第{$row}行：包号 {$packageCode} 已存在";
                    $result['error']++;
                    continue;
                }

                // 处理日期
                if ($entryDate instanceof DateTime) {
                    $entryDate = $entryDate->format('Y-m-d');
                } elseif (is_numeric($entryDate)) {
                    $entryDate = date('Y-m-d', ($entryDate - 25569) * 86400);
                } else {
                    $entryDate = date('Y-m-d');
                }

                // 插入原片包数据
                query(
                    "INSERT INTO glass_packages (package_code, glass_type_id, width, height, pieces, entry_date, current_rack_id, initial_rack_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [$packageCode, $glassType['id'], $width, $height, $pieces, $entryDate, $rackId, $rackId]
                );
                $result['success']++;
            } catch (Exception $e) {
                $result['errors'][] = "第{$row}行：" . $e->getMessage();
                $result['error']++;
            }
        }

        return $result;
    } catch (Exception $e) {
        throw new Exception('Excel文件处理失败：' . $e->getMessage());
    }
}
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
    <div class="import-container">
        <!-- 模板下载区域 -->
        <div class="template-section">
            <h3>1. 下载Excel模板</h3>
            <p>请先下载对应的Excel模板，按照模板格式填写原片包数据：</p>
            <div class="template-buttons">
                <a href="../assets/template/packages.xlsx" class="btn btn-primary" download>
                    <i class="icon-download"></i> 下载导入模板和示例数据
                </a>
            </div>

            <div class="template-info">
                <h4>模板说明：</h4>
                <ul>
                    <li><strong>包号：</strong>原片包的唯一标识码</li>
                    <li><strong>原片类型ID：</strong>对应原片类型表中的custom_id</li>
                    <li><strong>宽度：</strong>原片宽度（毫米）</li>
                    <li><strong>高度：</strong>原片高度（毫米）</li>
                    <li><strong>片数：</strong>包内原片数量</li>
                    <li><strong>入库日期：</strong>格式：YYYY-MM-DD</li>
                    <li><strong>库位名称：</strong>存放的库位名称,如17A</li>
                </ul>
                <p class="note">注意：请严格按照模板格式填写，第一行为标题行，数据从第二行开始。</p>
            </div>
        </div>

        <!-- 文件上传区域 -->
        <div class="upload-section">
            <h3>2. 上传Excel文件</h3>
            <form method="post" enctype="multipart/form-data" class="upload-form">
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
        <?php if (!empty($importResult)): ?>
            <div class="results-section">
                <h3>3. 导入结果</h3>
                <div class="result-item">
                    <h4>原片包信息</h4>
                    <div class="result-stats">
                        <span class="success-count">成功：<?php echo $importResult['success']; ?>条</span>
                        <span class="error-count">失败：<?php echo $importResult['error']; ?>条</span>
                    </div>

                    <?php if (!empty($importResult['errors'])): ?>
                        <div class="error-details">
                            <h5>错误详情：</h5>
                            <ul>
                                <?php foreach ($importResult['errors'] as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
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

        if (!confirm('确定要导入原片包数据吗？导入过程可能需要一些时间。')) {
            e.preventDefault();
        }
    });
</script>

<?php
$content = ob_get_clean();

// 渲染页面
echo renderAdminLayout('原片包数据导入', $content, $currentUser, 'tools_packages.php', [], [], $message, $messageType);
?>