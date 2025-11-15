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
    <script src="../assets/js/datatable-config.js"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../assets/css/datatable-theme.css">
    <style>
        .viewer-layout {
            min-height: 100vh;
            background-color: #f5f5f5;
        }
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
        .viewer-container {
            max-width: 1700px;
            margin: 0 auto;
            padding: 30px 20px;
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
        .summary-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px;
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
    </style>
</head>
<body class="viewer-layout">
    <header class="viewer-header">
        <div class="header-content">
            <div class="header-left">
                <h1>库存查询 - <?php echo $baseName; ?></h1>
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
    <script>
$(document).ready(function() {
    // 初始化DataTable
    const table = $('#inventoryTable').DataTable();
    
    // 获取URL中的search参数
    const urlParams = new URLSearchParams(window.location.search);
    const searchValue = urlParams.get('search');
    
    // 如果search参数存在，自动应用到DataTable搜索框
    if (searchValue) {
        table.search(searchValue).draw();
    }
});
</script>
</body>
</html>