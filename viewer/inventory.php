<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// 要求用户登录
requireLogin();
// 检查是否为viewer角色
requireRole(['viewer', 'admin', 'manager', 'operator']);
// 获取当前用户信息
$currentUser = getCurrentUser();
// 获取基地信息
$baseName = '所有基地';
if ($currentUser['base_id']) {
    $baseInfo = fetchRow("SELECT name FROM bases WHERE id = ?", [$currentUser['base_id']]);
    $baseName = $baseInfo ? $baseInfo['name'] : '未知基地';
}

// 获取颜色和厚度的唯一值用于下拉框
$colorOptions = fetchAll("SELECT DISTINCT color FROM glass_types WHERE color IS NOT NULL AND color != '' ORDER BY color");
$thicknessOptions = fetchAll("SELECT DISTINCT thickness FROM glass_types WHERE thickness IS NOT NULL ORDER BY thickness");

// 构建查询条件
$whereConditions = [];
$params = [];

// 基地限制
if ($currentUser['base_id']) {
    $whereConditions[] = "sr.base_id = ?";
    $params[] = $currentUser['base_id'];
}

// 修改查询条件，只显示库存区的包
$whereConditions[] = "sr.area_type = 'storage'";
$whereConditions[] = "gp.status = 'in_storage'";

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// 获取库存数据（DataTables将处理分页和搜索）
$sql = "SELECT gp.*, gt.name as glass_name, gt.short_name, gt.color, gt.thickness,
               sr.code as rack_code, b.name as base_name,
               gp.width, gp.height, gp.pieces, gp.entry_date
        FROM glass_packages gp 
        LEFT JOIN glass_types gt ON gp.glass_type_id = gt.id 
        LEFT JOIN storage_racks sr ON gp.current_rack_id = sr.id 
        LEFT JOIN bases b ON sr.base_id = b.id 
        $whereClause
        ORDER BY gp.entry_date DESC, gp.package_code";

$packages = fetchAll($sql, $params);

// 计算汇总数据
$totalPackages = count($packages);
$totalPieces = 0;
foreach ($packages as $package) {
    $totalPieces += $package['pieces'];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>库存查询 - <?php echo APP_NAME; ?></title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="../assets/js/datatable-config.js"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/viewer.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="../assets/css/datatable-theme.css">
</head>
<body class="viewer-layout">
    <header class="viewer-header">
        <div class="header-content">
            <div class="header-left">
                <h1><?php echo APP_NAME; ?> - 库存查询</h1>
            </div>
            <div class="header-right">
                <div class="nav-links">
                    <a href="index.php" class="nav-link">首页</a>
                    <a href="/warehouse.php" class="nav-link">可视化库区</a>
                    <a href="/viewer/inventory.php" class="nav-link active">库存查询</a>
                    <a href="/viewer/processing_inventory.php" class="nav-link">加工库存</a>
                    <a href="/admin/index.php" class="nav-link">进入后台</a>
                </div>
                <div class="user-info">
                    <div class="user-avatar"><?php echo mb_substr($currentUser['name'], 0, 1); ?></div>
                    <span><?php echo htmlspecialchars($currentUser['name']); ?></span>
                    <a href="../logout.php" class="nav-link">退出</a>
                </div>
            </div>
        </div>
    </header>

    <div class="viewer-container">
        <!-- 汇总信息 -->
        <div class="summary-info">
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="number"><?php echo number_format($totalPackages); ?></div>
                    <div class="label">总包数</div>
                </div>
                <div class="summary-item">
                    <div class="number"><?php echo number_format($totalPieces); ?></div>
                    <div class="label">总片数</div>
                </div>
                <div class="summary-item">
                    <div class="number"><?php echo $baseName; ?></div>
                    <div class="label">当前基地</div>
                </div>
            </div>
        </div>

        <!-- 库存表格 -->
        <div class="card">
            <div class="card-body">
                <table id="inventoryTable" class="table table-striped table-hover" data-table="inventory">
                    <thead>
                        <tr>
                            <th>包号</th>
                            <th>原片名称</th>
                            <th>原片简称</th>
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
                        <?php foreach ($packages as $package): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($package['package_code']); ?></td>
                            <td><?php echo htmlspecialchars($package['glass_name']); ?></td>
                            <td><?php echo htmlspecialchars($package['short_name']); ?></td>
                            <td>
                                <?php 
                                if ($package['width'] && $package['height']) {
                                    echo $package['width'] . '×' . $package['height'] . 'mm';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td><?php echo number_format($package['pieces']); ?></td>
                            <td><?php echo htmlspecialchars($package['color'] ?: '-'); ?></td>
                            <td><?php echo $package['thickness'] ? $package['thickness'] . 'mm' : '-'; ?></td>
                            <td><?php echo htmlspecialchars($package['rack_code'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($package['base_name'] ?: '-'); ?></td>
                            <td><?php echo $package['entry_date'] ? date('Y-m-d', strtotime($package['entry_date'])) : '-'; ?></td>
                            <td>
                                <?php
                                $statusMap = [
                                    'in_storage' => '在库',
                                    'in_processing' => '加工中',
                                    'scrapped' => '报废'
                                ];
                                $statusClass = 'status-' . $package['status'];
                                $statusText = $statusMap[$package['status']] ?? $package['status'];
                                ?>
                                <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 导出表单 -->
    <form id="exportForm" method="POST" style="display: none;">
        <input type="hidden" name="export" value="inventory">
    </form>
    

</body>
</html>