<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
// 要求用户登录
requireLogin();
// 检查是否为viewer角色
requireRole(['viewer', 'admin', 'manager']);
// 获取当前用户信息
$currentUser = getCurrentUser();
// 获取基地信息
$baseName = '所有基地';
if ($currentUser['base_id']) {
    $baseInfo = fetchRow("SELECT name FROM bases WHERE id = ?", [$currentUser['base_id']]);
    $baseName = $baseInfo ? $baseInfo['name'] : '未知基地';
}
if ($currentUser['base_id']) {
    $baseInfo = fetchRow("SELECT name FROM bases WHERE id = ?", [$currentUser['base_id']]);
    $baseName = $baseInfo ? $baseInfo['name'] : '未知基地';
}

// 获取颜色和厚度的唯一值用于下拉框
$colorOptions = fetchAll("SELECT DISTINCT color FROM glass_types WHERE color IS NOT NULL AND color != '' ORDER BY color");
$thicknessOptions = fetchAll("SELECT DISTINCT thickness FROM glass_types WHERE thickness IS NOT NULL ORDER BY thickness");

// 处理导出请求
if (isset($_GET['export'])) {
    // 构建查询条件（与显示逻辑相同）
    $whereConditions = [];
    $params = [];

    // 基地限制
    if ($currentUser['base_id']) {
        $whereConditions[] = "sr.base_id = ?";
        $params[] = $currentUser['base_id'];
    }

    // 搜索条件
    $search = $_GET['search'] ?? '';
    if ($search) {
        $whereConditions[] = "(gp.package_code LIKE ? OR gt.name LIKE ? OR gt.short_name LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    // 筛选条件
    $shortName = $_GET['short_name'] ?? '';
    if ($shortName) {
        $whereConditions[] = "gt.short_name LIKE ?";
        $params[] = "%$shortName%";
    }

    $specification = $_GET['specification'] ?? '';
    if ($specification) {
        $whereConditions[] = "CONCAT(gp.width, '×', gp.height) LIKE ?";
        $params[] = "%$specification%";
    }

    $color = $_GET['color'] ?? '';
    if ($color) {
        $whereConditions[] = "gt.color LIKE ?";
        $params[] = "%$color%";
    }

    $thickness = $_GET['thickness'] ?? '';
    if ($thickness) {
        $whereConditions[] = "gt.thickness = ?";
        $params[] = $thickness;
    }

    // 修改查询条件，只显示库存区的包
    $whereConditions[] = "sr.area_type = 'storage'";
    $whereConditions[] = "gp.status = 'in_storage'";

    $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // 获取导出数据（不分页）
    $exportSql = "SELECT gp.*, gt.name as glass_name, gt.short_name, gt.color, gt.thickness,
                   sr.code as rack_code, b.name as base_name,
                   gp.width, gp.height, gp.pieces, gp.entry_date
            FROM glass_packages gp 
            LEFT JOIN glass_types gt ON gp.glass_type_id = gt.id 
            LEFT JOIN storage_racks sr ON gp.current_rack_id = sr.id 
            LEFT JOIN bases b ON sr.base_id = b.id 
            $whereClause
            ORDER BY gp.entry_date DESC, gp.package_code";

    $exportData = fetchAll($exportSql, $params);

    // 创建Excel文件
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // 设置标题
    $headers = ['包号', '原片名称', '原片简称', '规格', '片数', '颜色', '厚度', '库位', '基地', '入库日期', '状态'];
    $sheet->fromArray($headers, null, 'A1');

    // 设置数据
    $row = 2;
    $totalPieces = 0;
    $totalPackages = count($exportData);

    foreach ($exportData as $package) {
        $specification = '';
        if ($package['width'] && $package['height']) {
            $specification = $package['width'] . '×' . $package['height'] . 'mm';
        }

        $statusMap = [
            'in_storage' => '在库',
            'in_processing' => '加工中',
            'scrapped' => '报废'
        ];

        $data = [
            $package['package_code'],
            $package['glass_name'],
            $package['short_name'],
            $specification,
            $package['pieces'],
            $package['color'],
            $package['thickness'] . 'mm',
            $package['rack_code'] ?: '-',
            $package['base_name'] ?: '-',
            $package['entry_date'] ? date('Y-m-d', strtotime($package['entry_date'])) : '-',
            $statusMap[$package['status']] ?? $package['status']
        ];

        $sheet->fromArray($data, null, 'A' . $row);
        $totalPieces += $package['pieces'];
        $row++;
    }

    // 添加汇总行
    $summaryData = ['汇总', '', '', '', $totalPieces, '', '', '', '', '总包数: ' . $totalPackages, ''];
    $sheet->fromArray($summaryData, null, 'A' . $row);

    // 设置样式
    $headerRange = 'A1:K1';
    $sheet->getStyle($headerRange)->getFont()->setBold(true);
    $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setRGB('E3F2FD');

    // 设置边框
    $dataRange = 'A1:K' . $row;
    $sheet->getStyle($dataRange)->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);

    // 自动调整列宽
    foreach (range('A', 'K') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // 输出文件
    $exportType = $_GET['export'];
    $filename = ($exportType === 'all' ? '库存全部数据' : '库存筛选数据') . '_' . date('Y-m-d_H-i-s') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? max(10, min(100, intval($_GET['limit']))) : 20; // 可变分页数量
$offset = ($page - 1) * $limit;

// 构建查询条件
$whereConditions = [];
$params = [];

// 基地限制
if ($currentUser['base_id']) {
    $whereConditions[] = "sr.base_id = ?";
    $params[] = $currentUser['base_id'];
}

// 搜索条件
$search = $_GET['search'] ?? '';
if ($search) {
    // 检查搜索词是否像库位代码（数字+字母格式）
    if (preg_match('/^\d+[A-Z]$/i', $search)) {
        // 对库位代码使用精确匹配，对其他字段使用模糊匹配
        $whereConditions[] = "(gp.package_code LIKE ? OR gt.name LIKE ? OR gt.short_name LIKE ? OR sr.name = ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $search; // 库位代码精确匹配
    } else {
        // 普通搜索，所有字段都使用模糊匹配
        $whereConditions[] = "(gp.package_code LIKE ? OR gt.name LIKE ? OR gt.short_name LIKE ? OR sr.name LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
}

// 新增筛选条件
$shortName = $_GET['short_name'] ?? '';
if ($shortName) {
    $whereConditions[] = "gt.short_name LIKE ?";
    $params[] = "%$shortName%";
}

$specification = $_GET['specification'] ?? '';
if ($specification) {
    $whereConditions[] = "CONCAT(gp.width, '×', gp.height) LIKE ?";
    $params[] = "%$specification%";
}

$color = $_GET['color'] ?? '';
if ($color) {
    $whereConditions[] = "gt.color LIKE ?";
    $params[] = "%$color%";
}

$thickness = $_GET['thickness'] ?? '';
if ($thickness) {
    $whereConditions[] = "gt.thickness = ?";
    $params[] = $thickness;
}

// 修改查询条件，只显示库存区的包
$whereConditions[] = "sr.area_type = 'storage'";
$whereConditions[] = "gp.status = 'in_storage'";

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// 获取总记录数和汇总数据
$totalSql = "SELECT COUNT(*) as total_count, SUM(gp.pieces) as total_pieces FROM glass_packages gp 
             LEFT JOIN glass_types gt ON gp.glass_type_id = gt.id 
             LEFT JOIN storage_racks sr ON gp.current_rack_id = sr.id 
             LEFT JOIN bases b ON sr.base_id = b.id 
             $whereClause";
$totalResult = fetchRow($totalSql, $params);
$total = $totalResult['total_count'];
$totalPieces = $totalResult['total_pieces'] ?: 0;

// 获取库存数据
$sql = "SELECT gp.*, gt.name as glass_name, gt.short_name, gt.color, gt.thickness,
               sr.code as rack_code, b.name as base_name,
               gp.width, gp.height, gp.pieces, gp.entry_date
        FROM glass_packages gp 
        LEFT JOIN glass_types gt ON gp.glass_type_id = gt.id 
        LEFT JOIN storage_racks sr ON gp.current_rack_id = sr.id 
        LEFT JOIN bases b ON sr.base_id = b.id 
        $whereClause
        ORDER BY gp.entry_date DESC, gp.package_code 
        LIMIT $limit OFFSET $offset";

$packages = fetchAll($sql, $params);

// 计算当前页面的汇总数据
$currentPagePieces = 0;
foreach ($packages as $package) {
    $currentPagePieces += $package['pieces'];
}

// 计算分页信息
$totalPages = ceil($total / $limit);

// 构建URL参数
function buildUrlParams($excludeParams = []) {
    $params = [];
    $allowedParams = ['search', 'short_name', 'specification', 'color', 'thickness', 'limit', 'page'];
    
    foreach ($allowedParams as $param) {
        if (!in_array($param, $excludeParams) && isset($_GET[$param]) && $_GET[$param] !== '') {
            // 确保每个参数只添加一次
            $params[$param] = $_GET[$param];
        }
    }
    
    return http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>库存查询 - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .viewer-layout {
            min-height: 100vh;
            background-color: #f5f5f5;
        }

        /* 统一头部样式 */
        .viewer-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .nav-links {
            display: flex;
            gap: 10px;
        }

        .nav-link {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
        }

        /* 内容区域样式 */
        .viewer-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .search-form {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 500;
            margin-bottom: 5px;
            color: #333;
        }

        .filter-group input,
        .filter-group select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 14px;
        }

        .search-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .export-buttons {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #545b62;
        }

        .btn-success {
            background-color: #28a745;
            color: white;
        }

        .btn-success:hover {
            background-color: #1e7e34;
        }

        .btn-info {
            background-color: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background-color: #117a8b;
        }

        .inventory-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 25px;
        }

        .inventory-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .inventory-table th,
        .inventory-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .inventory-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .inventory-table tr:hover {
            background-color: #f8f9fa;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-in_storage {
            background-color: #d4edda;
            color: #155724;
        }

        .status-in_processing {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-scrapped {
            background-color: #f8d7da;
            color: #721c24;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            text-decoration: none;
            border: 1px solid #ddd;
            border-radius: 3px;
            color: #333;
        }

        .pagination a:hover {
            background-color: #f8f9fa;
        }

        .pagination .current {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }

        .summary-info {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .summary-item {
            text-align: center;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
        }

        .summary-item .number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }

        .summary-item .label {
            color: #666;
            font-size: 14px;
        }

        .page-size-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-size-selector select {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }

        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }

            .search-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .export-buttons {
                margin-left: 0;
                margin-top: 10px;
            }

            .inventory-table {
                overflow-x: auto;
            }

            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* 筛选表单样式 - 单行显示 */
        .filter-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .filter-row {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: nowrap;
        }

        .filter-group {
            flex: 1;
            min-width: 120px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            height: 38px;
            box-sizing: border-box;
        }

        .search-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 15px;
            flex-wrap: nowrap;
        }

        .page-size-selector {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
        }

        .page-size-selector label {
            font-size: 14px;
            color: #666;
            white-space: nowrap;
        }

        .page-size-selector select {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            min-width: 80px;
        }

        /* 汇总信息样式 - 单行显示 */
        .summary-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .summary-grid {
            display: flex;
            justify-content: space-around;
            align-items: center;
            gap: 20px;
        }

        .summary-item {
            text-align: center;
            flex: 1;
        }

        .summary-item .number {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        .summary-item .label {
            font-size: 14px;
            opacity: 0.9;
            font-weight: 500;
        }

         /* 响应式适配 - 1080P到1440P */
        @media (min-width: 1920px) {
            .header-content,
            .viewer-container {
                max-width: 1600px;
            }
            
            .form-filter {
                min-width: 160px;
                max-width: 220px;
            }
        }

        @media (max-width: 1440px) {
            .filter-row {
                gap: 12px;
            }

            .form-filter {
                min-width: 120px;
                max-width: 180px;
            }
        }
        @media (max-width: 1440px) {
            .filter-row {
                gap: 12px;
            }

            .filter-group {
                min-width: 100px;
            }

            .summary-item .number {
                font-size: 24px;
            }
        }

        @media (max-width: 1200px) {
            .filter-row {
                gap: 10px;
            }

            .filter-group {
                min-width: 90px;
            }

            .summary-item .number {
                font-size: 22px;
            }

            .summary-grid {
                gap: 15px;
            }
        }

        /* 导出按钮样式 */
        .export-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .export-actions .btn {
            padding: 8px 16px;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="viewer-layout">
        <header class="viewer-header">
            <div class="header-content">
                <div class="header-left">
                    <h1><?php echo APP_NAME; ?></h1>
                </div>
                <div class="header-right">
                    <nav class="nav-links">
                        <a href="index.php" class="nav-link">首页</a>
                        <a href="inventory.php" class="nav-link active">库存查询</a>
                        <a href="processing_inventory.php" class="nav-link">加工区库存</a>
                    </nav>
                    <div class="user-info">
                        <div class="user-avatar"><?php echo mb_substr($currentUser['name'], 0, 1); ?></div>
                        <div>
                            <div><?php echo htmlspecialchars($currentUser['name']); ?></div>
                            <div style="font-size: 12px; opacity: 0.8;"><?php echo htmlspecialchars($baseName); ?></div>
                        </div>
                    </div>
                    <a href="../logout.php" class="nav-link">退出</a>
                </div>
            </div>
        </header>

        <!-- 内容区域 -->
        <div class="viewer-container">
            <div class="search-form">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>库存详情</h2>
                    <div class="export-buttons">
                        <a href="?export=all<?php echo buildUrlParams(['export']); ?>" class="btn btn-success">导出全部</a>
                        <a href="?export=filtered<?php echo buildUrlParams(['export']); ?>" class="btn btn-info">导出筛选结果</a>
                    </div>
                </div>

                <form method="GET">
                    <!-- 主搜索框 -->

                    <!-- 筛选条件 -->
                    <div class="filter-row">
                    <div class="filter-group">
                        <label>关键词搜索</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="搜索包号、原片名称..." style="width: 100%;">
                    </div>
                        <div class="filter-group">
                            <label>原片简称</label>
                            <input type="text" name="short_name" value="<?php echo htmlspecialchars($shortName); ?>"
                                placeholder="输入原片简称">
                        </div>
                        <div class="filter-group">
                            <label>规格</label>
                            <input type="text" name="specification" value="<?php echo htmlspecialchars($specification); ?>"
                                placeholder="如：1000×800">
                        </div>
                        <div class="filter-group">
                            <label>颜色</label>
                            <select name="color">
                                <option value="">全部颜色</option>
                                <?php foreach ($colorOptions as $colorOption): ?>
                                    <option value="<?php echo htmlspecialchars($colorOption['color']); ?>" 
                                        <?php echo $color === $colorOption['color'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($colorOption['color']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>厚度(mm)</label>
                            <select name="thickness">
                                <option value="">全部厚度</option>
                                <?php foreach ($thicknessOptions as $thicknessOption): ?>
                                    <option value="<?php echo $thicknessOption['thickness']; ?>" 
                                        <?php echo $thickness == $thicknessOption['thickness'] ? 'selected' : ''; ?>>
                                        <?php echo number_format($thicknessOption['thickness'], 0); ?>mm
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    
                    <div class="search-actions">
                        <button type="submit" class="btn btn-primary">搜索筛选</button>
                        <a href="inventory.php" class="btn btn-secondary">清除条件</a>
                        <div class="page-size-selector">
                            <label>每页显示:</label>
                            <select name="limit" onchange="this.form.submit()">
                                <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10条</option>
                                <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20条</option>
                                <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50条</option>
                                <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100条</option>
                            </select>
                        </div>
                    </div>

                </div>                
                </form>

                <div style="margin-top: 15px; color: #666;">
                    共找到 <?php echo $total; ?> 条记录
                </div>
            </div>

            <div class="inventory-table">
                <table>
                    <thead>
                        <tr>
                            <th>包号</th>
                            <th>原片名称</th>
                            <th>规格</th>
                            <th>片数</th>
                            <th>颜色</th>
                            <th>厚度</th>
                            <th>库位</th>
                            <th>基地</th>
                            <th>入库日期</th>
                            <th>状态</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($packages)): ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 40px; color: #999;">
                                    暂无库存数据
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($packages as $package): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($package['package_code']); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($package['glass_name']); ?></div>
                                        <small style="color: #666;"><?php echo htmlspecialchars($package['short_name']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($package['width'] && $package['height']): ?>
                                            <?php echo $package['width']; ?>×<?php echo $package['height']; ?>mm
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $package['pieces']; ?>
                                        <?php if ($package['quantity'] > 0 && $package['quantity'] != $package['pieces']): ?>
                                            <small class="text-muted">（标准：<?php echo $package['quantity']; ?>）</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($package['color']); ?></td>
                                    <td><?php echo $package['thickness']; ?>mm</td>
                                    <td><?php echo htmlspecialchars($package['rack_code'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($package['base_name'] ?: '-'); ?></td>
                                    <td><?php echo $package['entry_date'] ? date('Y-m-d', strtotime($package['entry_date'])) : '-'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $package['status']; ?>">
                                            <?php
                                            $statusMap = [
                                                'in_storage' => '在库',
                                                'in_processing' => '加工中',
                                                'scrapped' => '报废'
                                            ];
                                            echo $statusMap[$package['status']] ?? $package['status'];
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- 汇总信息 -->
            <div class="summary-info">
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="number"><?php echo $total; ?></div>
                        <div class="label">总包数</div>
                    </div>
                    <div class="summary-item">
                        <div class="number"><?php echo number_format($totalPieces); ?></div>
                        <div class="label">总片数</div>
                    </div>
                    <div class="summary-item">
                        <div class="number"><?php echo count($packages); ?></div>
                        <div class="label">当前页包数</div>
                    </div>
                    <div class="summary-item">
                        <div class="number"><?php echo number_format($currentPagePieces); ?></div>
                        <div class="label">当前页片数</div>
                    </div>
                </div>
            </div>
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo buildUrlParams(); ?>">&laquo; 上一页</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo buildUrlParams(); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo buildUrlParams(); ?>">下一页 &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>
</