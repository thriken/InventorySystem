<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/admin_layout.php';
require_once '../includes/inventory_operations.php';
require_once '../vendor/autoload.php'; // 添加PHPSpreadsheet支持

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// 要求用户登录
requireLogin();

// 检查是否为管理员或经理
requireRole(['admin', 'manager']);

// 获取当前用户信息
$currentUser = getCurrentUser();

// 处理Excel导出
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    try {
        // 构建查询条件（与显示逻辑相同）
        $whereConditions = [];
        $params = [];
        
        // 管理员可以查看所有基地的包，其他角色只能查看所属基地的包
        if ($currentUser['role'] !== 'admin' && $currentUser['base_id']) {
            $whereConditions[] = "r.base_id = ?";
            $params[] = $currentUser['base_id'];
            // 排除已用完的包
            $whereConditions[] = "p.status != ?";
            $params[] = 'used_up';
        }
        
        // 应用筛选条件
        $statusFilter = $_GET['status'] ?? '';
        $glassTypeFilter = $_GET['glass_type'] ?? '';
        $search = $_GET['search'] ?? '';
        
        if (!empty($statusFilter)) {
            $whereConditions[] = "p.status = ?";
            $params[] = $statusFilter;
        }
        
        if (!empty($glassTypeFilter)) {
            $whereConditions[] = "p.glass_type_id = ?";
            $params[] = $glassTypeFilter;
        }
        
        if (!empty($search)) {
            $whereConditions[] = "p.package_code LIKE ?";
            $params[] = '%' . $search . '%';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // 获取导出数据
        $exportData = fetchAll("SELECT p.*, g.name as glass_name, g.short_name as glass_short_name, 
                                    r.code as rack_code, r.area_type, ir.code as initial_rack_code, 
                                    g.brand as glass_brand, g.color as glass_color,
                                    CONCAT(p.width, 'x', p.height) as specification, p.position_order,
                                    b.name as base_name
                             FROM glass_packages p
                             LEFT JOIN glass_types g ON p.glass_type_id = g.id
                             LEFT JOIN storage_racks r ON p.current_rack_id = r.id
                             LEFT JOIN storage_racks ir ON p.initial_rack_id = ir.id
                             LEFT JOIN bases b ON r.base_id = b.id
                             $whereClause
                             ORDER BY r.code ASC, p.position_order ASC", $params);
        
        // 创建Excel文件
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // 设置表头
        $headers = [
            'A1' => '包号',
            'B1' => '原片名',
            'C1' => '原片品牌', 
            'D1' => '原片颜色',
            'E1' => '规格(宽x高)',
            'F1' => '片数',
            'G1' => '位置顺序',
            'H1' => '入库日期',
            'I1' => '起始库区',
            'J1' => '当前位置',
            'K1' => '状态'
        ];
        
        // 如果是管理员，添加基地列
        if ($currentUser['role'] === 'admin') {
            $headers['L1'] = '所属基地';
        }
        
        // 设置表头样式
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        
        // 设置表头样式
        $headerRange = 'A1:' . ($currentUser['role'] === 'admin' ? 'L1' : 'K1');
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        
        // 填充数据
        $row = 2;
        foreach ($exportData as $package) {
            $statusLabels = [
                'in_storage' => '库存中',
                'in_processing' => '加工中',
                'scrapped' => '已报废',
                'used_up' => '已用完'
            ];
            
            $sheet->setCellValue('A' . $row, $package['package_code']);
            $sheet->setCellValue('B' . $row, $package['glass_name']);
            $sheet->setCellValue('C' . $row, $package['glass_brand']);
            $sheet->setCellValue('D' . $row, $package['glass_color']);
            $sheet->setCellValue('E' . $row, $package['specification']);
            $sheet->setCellValue('F' . $row, $package['pieces']);
            $sheet->setCellValue('G' . $row, $package['position_order'] ?? '-');
            $sheet->setCellValue('H' . $row, $package['entry_date']);
            $sheet->setCellValue('I' . $row, $package['initial_rack_code']);
            $sheet->setCellValue('J' . $row, $package['rack_code']);
            $sheet->setCellValue('K' . $row, $statusLabels[$package['status']] ?? $package['status']);
            
            // 如果是管理员，添加基地信息
            if ($currentUser['role'] === 'admin') {
                $sheet->setCellValue('L' . $row, $package['base_name'] ?? '-');
            }
            
            $row++;
        }
        
        // 设置列宽自适应
        foreach (range('A', ($currentUser['role'] === 'admin' ? 'L' : 'K')) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // 设置数据区域边框
        $dataRange = 'A1:' . ($currentUser['role'] === 'admin' ? 'L' : 'K') . ($row - 1);
        $sheet->getStyle($dataRange)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ]);
        
        // 生成文件名
        $baseName = '';
        if ($currentUser['role'] !== 'admin' && $currentUser['base_id']) {
            $baseInfo = fetchRow("SELECT name FROM bases WHERE id = ?", [$currentUser['base_id']]);
            $baseName = $baseInfo ? '_' . $baseInfo['name'] : '';
        }
        $filename = '库存原片包信息' . $baseName . '_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        // 输出Excel文件
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
        
    } catch (Exception $e) {
        $message = '导出失败：' . $e->getMessage();
        $messageType = 'error';
    }
}

// 处理表单提交
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $packageCode = trim($_POST['package_code'] ?? '');
            $glassTypeId = (int)($_POST['glass_type_id'] ?? 0);
            $width = floatval($_POST['width'] ?? 0);
            $height = floatval($_POST['height'] ?? 0);
            $pieces = (int)($_POST['pieces'] ?? 0);
            $entryDate = $_POST['entry_date'] ?? date('Y-m-d');
            $initialRackId = (int)($_POST['initial_rack_id'] ?? 0);
            $status = $_POST['status'] ?? 'in_storage';

            // 完整的字段验证
            if (empty($packageCode)) {
                throw new Exception('包号不能为空');
            }
            if ($glassTypeId <= 0) {
                throw new Exception('请选择原片类型');
            }
            if ($width <= 0) {
                throw new Exception('宽度必须大于0');
            }
            if ($height <= 0) {
                throw new Exception('高度必须大于0');
            }

            // 检查包号是否已存在
            $existing = fetchRow("SELECT id FROM glass_packages WHERE package_code = ?", [$packageCode]);
            if ($existing) {
                throw new Exception('该包号已存在，包号必须唯一');
            }

            // 验证片数必须大于0
            if ($pieces <= 0) {
                throw new Exception('库存片数必须大于0');
            }
            if (empty($entryDate)) {
                throw new Exception('入库日期不能为空');
            }
            if ($initialRackId <= 0) {
                throw new Exception('请选择起始库区');
            }
            $newPackageId = insert('glass_packages', [
                'package_code' => $packageCode,
                'glass_type_id' => $glassTypeId,
                'width' => $width,
                'height' => $height,
                'pieces' => $pieces,
                'entry_date' => $entryDate,
                'initial_rack_id' => $initialRackId,
                'current_rack_id' => $initialRackId,
                'status' => $status,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            // 为新包分配位置顺序号
            assignPackagePosition($newPackageId, $initialRackId);
            $message = '包添加成功！';
            $messageType = 'success';
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $packageCode = trim($_POST['package_code'] ?? '');
            $glassTypeId = (int)($_POST['glass_type_id'] ?? 0);
            $width = floatval($_POST['width'] ?? 0);
            $height = floatval($_POST['height'] ?? 0);
            $pieces = (int)($_POST['pieces'] ?? 0);
            $entryDate = $_POST['entry_date'] ?? '';
            $initialRackId = (int)($_POST['initial_rack_id'] ?? 0);
            $status = $_POST['status'] ?? 'in_storage';

            if ($id <= 0) {
                throw new Exception('参数错误');
            }

            // 完整的字段验证
            if (empty($packageCode)) {
                throw new Exception('包号不能为空');
            }
            if ($glassTypeId <= 0) {
                throw new Exception('请选择原片类型');
            }
            if ($width <= 0) {
                throw new Exception('宽度必须大于0');
            }
            if ($height <= 0) {
                throw new Exception('高度必须大于0');
            }
            if ($pieces <= 0) {
                throw new Exception('片数必须大于0');
            }
            if (empty($entryDate)) {
                throw new Exception('入库日期不能为空');
            }
            if ($initialRackId <= 0) {
                throw new Exception('请选择起始库区');
            }

            // 检查包号是否已存在（排除当前记录）
            $existing = fetchRow("SELECT id FROM glass_packages WHERE package_code = ? AND id != ?", [$packageCode, $id]);
            if ($existing) {
                throw new Exception('该包号已存在');
            }

            query(
                "UPDATE glass_packages SET package_code = ?, glass_type_id = ?, width = ?, height = ?, pieces = ?, entry_date = ?, initial_rack_id = ?, status = ?, updated_at = ? WHERE id = ?",
                [$packageCode, $glassTypeId, $width, $height, $pieces, $entryDate, $initialRackId, $status, date('Y-m-d H:i:s'), $id]
            );

            $message = '包更新成功！';
            $messageType = 'success';
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('参数错误');
            }

            // 检查是否有关联的交易记录
            $transactions = fetchRow("SELECT COUNT(*) as count FROM inventory_transactions WHERE package_id = ?", [$id]);
            if ($transactions['count'] > 0) {
                throw new Exception('该包下还有交易记录，无法删除');
            }

            query("DELETE FROM glass_packages WHERE id = ?", [$id]);

            $message = '包删除成功！';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = '错误：' . $e->getMessage();
        $messageType = 'error';
    }
}

// 获取筛选参数
$statusFilter = $_GET['status'] ?? '';
$glassTypeFilter = $_GET['glass_type'] ?? '';
$search = $_GET['search'] ?? '';

// 构建查询条件
$whereConditions = [];
$params = [];

// 管理员可以查看所有基地的包，其他角色只能查看所属基地的包
if ($currentUser['role'] !== 'admin' && $currentUser['base_id']) {
    $whereConditions[] = "r.base_id = ?";
    $params[] = $currentUser['base_id'];
    // 排除已用完的包
    $whereConditions[] = "p.status != ?";
    $params[] = 'used_up';
}

if (!empty($statusFilter)) {
    $whereConditions[] = "p.status = ?";
    $params[] = $statusFilter;
}

if (!empty($glassTypeFilter)) {
    $whereConditions[] = "p.glass_type_id = ?";
    $params[] = $glassTypeFilter;
}

if (!empty($search)) {
    $whereConditions[] = "p.package_code LIKE ?";
    $params[] = '%' . $search . '%';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
// 获取包列表
$packages = fetchAll("SELECT p.*, g.name as glass_name, g.short_name as glass_short_name, 
                            r.code as rack_code, r.area_type, ir.code as initial_rack_code , g.brand as glass_brand,g.color as glass_color,
                            CONCAT(p.width, 'x', p.height) as specification, p.position_order
                     FROM glass_packages p
                     LEFT JOIN glass_types g ON p.glass_type_id = g.id
                     LEFT JOIN storage_racks r ON p.current_rack_id = r.id
                     LEFT JOIN storage_racks ir ON p.initial_rack_id = ir.id
                     $whereClause
                     ORDER BY r.code ASC, p.position_order ASC", $params);

// 获取原片类型列表
$glassTypes = fetchAll("SELECT * FROM glass_types WHERE status = 1 ORDER BY name");

// 获取当前用户所属基地的库位架列表
$racks = [];
if ($currentUser['role'] === 'admin') {
    // 管理员可以看到所有基地的库位架
    $racks = fetchAll("SELECT r.id, r.code, r.area_type, b.name as base_name
                      FROM storage_racks r
                      LEFT JOIN bases b ON r.base_id = b.id
                      WHERE r.status = 'normal'
                      ORDER BY b.name, r.area_type, r.code");
} elseif ($currentUser['base_id']) {
    // 其他角色只能看到所属基地的库位架
    $racks = fetchAll("SELECT r.id, r.code, r.area_type, b.name as base_name
                      FROM storage_racks r
                      LEFT JOIN bases b ON r.base_id = b.id
                      WHERE r.base_id = ? AND r.status = 'normal'
                      ORDER BY r.area_type, r.code", [$currentUser['base_id']]);
}

// 获取编辑的记录
$editRecord = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editRecord = fetchRow("SELECT * FROM glass_packages WHERE id = ?", [$editId]);
}
ob_start();
?>
<style>
    .multi-filter-container {
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 15px;
        background: #f9f9f9;
    }
    
    .filter-section {
        margin-bottom: 15px;
    }
    
    .filter-row {
        display: flex;
        gap: 15px;
        align-items: flex-end;
        flex-wrap: wrap;
    }
    
    .filter-item {
        flex: 1;
        min-width: 150px;
    }
    
    /* 移除重复的 label 和 select 基础样式，只保留特殊样式 */
    .filter-item label {
        font-size: 12px;
        color: #666;
    }
    
    .filter-item select {
        font-size: 12px;
    }
    
    .filter-info {
        margin-bottom: 10px;
        font-size: 14px;
        color: #666;
        font-weight: bold;
    }
    
    .glass-type-list {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 4px;
        background: white;
    }
    
    .glass-type-item {
        padding: 10px;
        border-bottom: 1px solid #eee;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    .glass-type-item:hover {
        background-color: #f0f8ff;
    }
    
    .glass-type-item.selected {
        background-color: #e3f2fd;
        border-left: 4px solid #2196f3;
    }
    
    .glass-type-item:last-child {
        border-bottom: none;
    }
    
    .type-name {
        font-weight: bold;
        font-size: 14px;
        margin-bottom: 4px;
        color: #333;
    }
    
    .type-details {
        display: flex;
        gap: 8px;
        margin-bottom: 4px;
    }
    
    .type-details span {
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
    }
    
    .thickness {
        background-color: #e8f5e8;
        color: #2e7d32;
    }
    
    .color {
        background-color: #fff3e0;
        color: #f57c00;
    }
    
    .brand {
        background-color: #f3e5f5;
        color: #7b1fa2;
    }
    
    .type-code {
        font-size: 12px;
        color: #666;
        font-family: monospace;
    }
    
    /* 移除冗余的 .btn-sm 样式定义 */
</style>
<div class="admin-header">
    <button type="button" class="btn btn-success" onclick="showAddForm()">添加包</button><a href="?export=excel<?php 
        // 保持当前筛选条件
        $params = [];
        if (!empty($_GET['status'])) $params[] = 'status=' . urlencode($_GET['status']);
        if (!empty($_GET['glass_type'])) $params[] = 'glass_type=' . urlencode($_GET['glass_type']);
        if (!empty($_GET['search'])) $params[] = 'search=' . urlencode($_GET['search']);
        echo !empty($params) ? '&' . implode('&', $params) : '';
    ?>" class="btn btn-primary" style="margin-left: 10px;">导出Excel</a>
</div>
<!-- 添加/编辑表单 -->
<div class="form-container" id="formContainer" style="display: <?php echo $editRecord ? 'block' : 'none'; ?>">
    <div class="form-header">
        <h3><?php echo $editRecord ? '编辑包' : '添加包'; ?></h3>
        <button class="close-btn" onclick="hideForm()">&times;</button>
    </div>
    <form method="POST" action="">
        <input type="hidden" name="action" value="<?php echo $editRecord ? 'edit' : 'add'; ?>">
        <?php if ($editRecord): ?>
            <input type="hidden" name="id" value="<?php echo $editRecord['id']; ?>">
        <?php endif; ?>

        
        <div class="form-group">
            <label for="package_code">包号 *</label>
            <input type="text" id="package_code" name="package_code" value="<?php echo htmlspecialchars($editRecord['package_code'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label for="glass_type_id">原片类型 *</label>
            <div class="multi-filter-container">
                <!-- 筛选条件区域 -->
                <div class="filter-section">
                    <div class="filter-row">
                        <div class="filter-item">
                            <label>厚度(mm):</label>
                            <select id="thickness_filter" >
                                <option value="">全部厚度</option>
                                <?php 
                                $thicknesses = array_unique(array_column($glassTypes, 'thickness'));
                                sort($thicknesses);
                                foreach ($thicknesses as $thickness): 
                                    if (!empty($thickness)): ?>
                                        <option value="<?php echo $thickness; ?>"><?php echo $thickness; ?>mm</option>
                                    <?php endif;
                                endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-item">
                            <label>颜色:</label>
                            <select id="color_filter" >
                                <option value="">全部颜色</option>
                                <?php 
                                $colors = array_unique(array_filter(array_column($glassTypes, 'color')));
                                sort($colors);
                                foreach ($colors as $color): 
                                    if (!empty($color)): ?>
                                        <option value="<?php echo htmlspecialchars($color); ?>"><?php echo htmlspecialchars($color); ?></option>
                                    <?php endif;
                                endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-item">
                            <label>品牌:</label>
                            <select id="brand_filter" >
                                <option value="">全部品牌</option>
                                <?php 
                                $brands = array_unique(array_filter(array_column($glassTypes, 'brand')));
                                sort($brands);
                                foreach ($brands as $brand): 
                                    if (!empty($brand)): ?>
                                        <option value="<?php echo htmlspecialchars($brand); ?>"><?php echo htmlspecialchars($brand); ?></option>
                                    <?php endif;
                                endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-item">
                            <button type="button" id="clear_filters" class="btn btn-secondary btn-sm">清空筛选</button>
                        </div>
                    </div>
                </div>
                
                <!-- 筛选结果显示区域 -->
                <div class="filtered-results">
                    <div id="filter_info" class="filter-info">显示全部 <?php echo count($glassTypes); ?> 种原片类型</div>
                    <div id="glass_type_list" class="glass-type-list">
                        <?php foreach ($glassTypes as $type): ?>
                            <div class="glass-type-item" 
                                 data-id="<?php echo $type['id']; ?>"
                                 data-thickness="<?php echo $type['thickness']; ?>"
                                 data-color="<?php echo htmlspecialchars($type['color'] ?? ''); ?>"
                                 data-brand="<?php echo htmlspecialchars($type['brand'] ?? ''); ?>"
                                 onclick="selectGlassType(this)"
                                 <?php echo ($editRecord['glass_type_id'] ?? '') == $type['id'] ? 'class="glass-type-item selected"' : ''; ?>>
                                <div class="type-name"><?php echo htmlspecialchars($type['name']); ?></div>
                                <div class="type-details">
                                    <span class="thickness"><?php echo $type['thickness']; ?>mm</span>
                                    <?php if (!empty($type['color'])): ?>
                                        <span class="color"><?php echo htmlspecialchars($type['color']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($type['brand'])): ?>
                                        <span class="brand"><?php echo htmlspecialchars($type['brand']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="type-code"><?php echo htmlspecialchars($type['short_name']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- 隐藏的原始select -->
                <select id="glass_type_id" name="glass_type_id" required style="display: none;">
                    <option value="">请选择原片类型</option>
                    <?php foreach ($glassTypes as $type): ?>
                        <option value="<?php echo $type['id']; ?>" <?php echo ($editRecord['glass_type_id'] ?? '') == $type['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['name'] . ' ' . $type['thickness'] . 'mm' . ($type['color'] ?? '') . ($type['brand'] ?? '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        

        <div class="form-row">
            <div class="form-group">
                <label for="width">宽度(mm) *</label>
                <input type="number" id="width" name="width" value="<?php echo $editRecord['width'] ?? ''; ?>" min="1" step="0.01" required>
            </div>

            <div class="form-group">
                <label for="height">高度(mm) *</label>
                <input type="number" id="height" name="height" value="<?php echo $editRecord['height'] ?? ''; ?>" min="1" step="0.01" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="pieces">当前库存片数 * <small class="text-muted">（实际可用片数）</small></label>
                <input type="number" id="pieces" name="pieces" value="<?php echo $editRecord['pieces'] ?? ''; ?>" min="0" required>
                <small class="form-text text-muted">此字段为包的实际库存片数，所有库存操作以此为准</small>
            </div>

            <div class="form-group">
                <label for="entry_date">入库日期 *</label>
                <input type="date" id="entry_date" name="entry_date" value="<?php echo $editRecord['entry_date'] ?? date('Y-m-d'); ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="initial_rack_id">起始库区 *</label>
                <select id="initial_rack_id" name="initial_rack_id" required>
                    <option value="">请选择库区</option>
                    <?php foreach ($racks as $rack): ?>
                        <option value="<?php echo $rack['id']; ?>" <?php echo ($editRecord['initial_rack_id'] ?? '') == $rack['id'] ? 'selected' : ''; ?>>
                            <?php
                            if ($currentUser['role'] === 'admin') {
                                echo htmlspecialchars($rack['base_name'] . ' - ' . $rack['code'] . ' - ' . $rack['area_type']);
                            } else {
                                echo htmlspecialchars($rack['code'] . ' - ' . $rack['area_type']);
                            }
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="status">状态</label>
                <select id="status" name="status">
                    <option value="in_storage" <?php echo ($editRecord['status'] ?? 'in_storage') === 'in_storage' ? 'selected' : ''; ?>>库存中</option>
                    <option value="in_processing" <?php echo ($editRecord['status'] ?? '') === 'in_processing' ? 'selected' : ''; ?>>加工中</option>
                    <option value="scrapped" <?php echo ($editRecord['status'] ?? '') === 'scrapped' ? 'selected' : ''; ?>>已报废</option>
                </select>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo $editRecord ? '更新' : '添加'; ?></button>
            <button type="button" class="btn btn-secondary" onclick="hideForm()">取消</button>
        </div>
    </form>
    <h3></h3>
</div>

<!-- 数据表格 -->
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>包号</th>
                <th>原片名</th>
                <th>原片品牌</th>
                <th>原片颜色</th>
                <th>规格(宽x高)</th>
                <th>片数</th>
                <th>位置顺序</th>
                <th>入库日期</th>
                <th>起始库区</th>
                <th>当前位置</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($packages as $package): ?>
                <tr>
                    <td><?php echo htmlspecialchars($package['package_code']); ?></td>
                    <td><?php echo htmlspecialchars($package['glass_name']); ?></td>
                    <td><?php echo htmlspecialchars($package['glass_brand']); ?></td>
                    <td><?php echo htmlspecialchars($package['glass_color']); ?></td>

                    <td><?php echo htmlspecialchars($package['specification']); ?></td>
                    <td><?php echo $package['pieces']; ?></td>
                    <td><?php echo $package['position_order'] ?? '-'; ?></td>
                    <td><?php echo $package['entry_date']; ?></td>
                    <td><?php echo htmlspecialchars($package['initial_rack_code']); ?></td>
                    <td><?php echo htmlspecialchars($package['rack_code']); ?></td>
                    <td>
                        <?php
                        $statusLabels = [
                            'in_storage' => '<span class="label label-success">库存中</span>',
                            'in_processing' => '<span class="label label-warning">加工中</span>',
                            'scrapped' => '<span class="label label-danger">已报废</span>',
                            'used_up' => '<span class="label label-info">已用完</span>'
                        ];
                        echo $statusLabels[$package['status']] ?? $package['status'];
                        ?>
                    </td>
                    <td>
                        <a href="?edit=<?php echo $package['id']; ?>" class="btn btn-sm btn-info">编辑</a>
                        <button onclick="deleteRecord(<?php echo $package['id']; ?>)" class="btn btn-sm btn-danger">删除</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
    function showAddForm() {
        document.getElementById('formContainer').style.display = 'block';
        document.querySelector('input[name="action"]').value = 'add';
        document.querySelector('.form-header h3').textContent = '添加包';
        document.querySelector('form').reset();
        document.querySelector('input[name="entry_date"]').value = new Date().toISOString().split('T')[0];
        const idField = document.querySelector('input[name="id"]');
        if (idField) idField.remove();
    }

    function hideForm() {
        document.getElementById('formContainer').style.display = 'none';
    }

    function deleteRecord(id) {
        if (confirm('确定要删除这个包吗？此操作不可恢复。')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    // 多条件筛选功能
    function initMultiFilter() {
        const thicknessFilter = document.getElementById('thickness_filter');
        const colorFilter = document.getElementById('color_filter');
        const brandFilter = document.getElementById('brand_filter');
        const clearFiltersBtn = document.getElementById('clear_filters');
        const glassTypeList = document.getElementById('glass_type_list');
        const filterInfo = document.getElementById('filter_info');
        const hiddenSelect = document.getElementById('glass_type_id');
        
        // 应用筛选
        function applyFilters() {
            const selectedThicknesses = Array.from(thicknessFilter.selectedOptions)
                .map(option => option.value).filter(v => v !== '');
            const selectedColors = Array.from(colorFilter.selectedOptions)
                .map(option => option.value).filter(v => v !== '');
            const selectedBrands = Array.from(brandFilter.selectedOptions)
                .map(option => option.value).filter(v => v !== '');
            
            const items = glassTypeList.querySelectorAll('.glass-type-item');
            let visibleCount = 0;
            
            items.forEach(item => {
                const thickness = item.dataset.thickness;
                const color = item.dataset.color;
                const brand = item.dataset.brand;
                
                let show = true;
                
                // 厚度筛选
                if (selectedThicknesses.length > 0 && !selectedThicknesses.includes(thickness)) {
                    show = false;
                }
                
                // 颜色筛选
                if (selectedColors.length > 0 && !selectedColors.includes(color)) {
                    show = false;
                }
                
                // 品牌筛选
                if (selectedBrands.length > 0 && !selectedBrands.includes(brand)) {
                    show = false;
                }
                
                item.style.display = show ? 'block' : 'none';
                if (show) visibleCount++;
            });
            
            // 更新筛选信息
            const totalCount = items.length;
            if (visibleCount === totalCount) {
                filterInfo.textContent = `显示全部 ${totalCount} 种原片类型`;
            } else {
                filterInfo.textContent = `筛选结果：${visibleCount} / ${totalCount} 种原片类型`;
            }
        }
        
        // 选择原片类型
        window.selectGlassType = function(element) {
            // 移除之前的选中状态
            glassTypeList.querySelectorAll('.glass-type-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // 添加选中状态
            element.classList.add('selected');
            
            // 设置隐藏select的值
            hiddenSelect.value = element.dataset.id;
        };
        
        // 清空筛选
        clearFiltersBtn.addEventListener('click', function() {
            thicknessFilter.selectedIndex = -1;
            colorFilter.selectedIndex = -1;
            brandFilter.selectedIndex = -1;
            applyFilters();
        });
        
        // 绑定筛选事件
        [thicknessFilter, colorFilter, brandFilter].forEach(filter => {
            filter.addEventListener('change', applyFilters);
        });
        
        // 初始化多选框样式
        [thicknessFilter, colorFilter, brandFilter].forEach(select => {
            select.size = Math.min(select.options.length, 1);
        });
    }
    // 页面加载完成后初始化
    document.addEventListener('DOMContentLoaded', initMultiFilter);
</script>
</body>

</html>
<?php
$content = ob_get_clean();
echo renderAdminLayout('包管理', $content, $currentUser, 'packages.php', [], [], $message, $messageType);
?>
