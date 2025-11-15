<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

requireLogin();

// 获取当前用户信息
$currentUser = getCurrentUser();

// 获取基地信息
$baseName = '所有基地';
if ($currentUser['base_id']) {
    $baseInfo = fetchRow("SELECT name FROM bases WHERE id = ?", [$currentUser['base_id']]);
    $baseName = $baseInfo ? $baseInfo['name'] : '未知基地';
}

// 获取查询参数
$baseId = $_GET['base_id'] ?? '';
$glassTypeId = $_GET['glass_type_id'] ?? '';
$packageCode = $_GET['package_code'] ?? '';

// 构建查询条件
$whereConditions = [];
$params = [];

$whereConditions[] = "sr.area_type = 'processing'";
$whereConditions[] = "gp.status = 'in_processing'";

// 基地筛选 - 管理员可以手动筛选，非管理员只能看到自己基地的数据
if (!empty($baseId)) {
    // 如果有选择基地，则按选择的基地筛选
    $whereConditions[] = "sr.base_id = ?";
    $params[] = $baseId;
} elseif (!isAdmin($currentUser) && $currentUser['base_id']) {
    // 非管理员且有基地限制，则只能看到自己基地的数据
    $whereConditions[] = "sr.base_id = ?";
    $params[] = $currentUser['base_id'];
}
// 构建SQL查询
$sql = "SELECT 
            gp.package_code,
            gt.name as glass_name,
            gt.color,
            gt.thickness,
            gp.width,
            gp.height,
            gp.pieces as usage_pieces,
            sr.name as current_rack_code,
            b.name as base_name,
            gp.updated_at as usage_time,
            u.real_name as operator_name
        FROM glass_packages gp
        LEFT JOIN glass_types gt ON gp.glass_type_id = gt.id
        LEFT JOIN storage_racks sr ON gp.current_rack_id = sr.id
        LEFT JOIN bases b ON sr.base_id = b.id
        LEFT JOIN (
            SELECT it1.package_id, it1.operator_id
            FROM inventory_transactions it1
            WHERE it1.transaction_type IN ('usage_out', 'partial_usage')
            AND it1.transaction_time = (
                SELECT MAX(it2.transaction_time)
                FROM inventory_transactions it2
                WHERE it2.package_id = it1.package_id
                AND it2.transaction_type IN ('usage_out', 'partial_usage')
            )
        ) latest_op ON gp.id = latest_op.package_id
        LEFT JOIN users u ON latest_op.operator_id = u.id
        WHERE " . implode(' AND ', $whereConditions);

// 添加排序
$sql .= " ORDER BY gp.updated_at DESC";

$processingInventory = fetchAll($sql, $params);

// 获取基地列表
$bases = fetchAll("SELECT id, name FROM bases ORDER BY name");

// 获取原片类型列表
$glassTypes = fetchAll("SELECT id, name FROM glass_types ORDER BY name");
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>加工区库存 - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        /* 特定页面布局 */
        .viewer-layout {
            min-height: 100vh;
        }

        /* 统一头部样式 */
        .viewer-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .system-title {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 15px;
            border-radius: 20px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
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

        /* 内容区域样式 - 适配1080P-1440P */
        .viewer-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .search-form {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }

        /* 表单行样式 - 单行显示 */
        .form-row {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }

        /* 覆盖 main.css 中的 form-group 样式 */
        .form-group {
            flex: 1;
            min-width: 140px;
            max-width: 200px;
            margin-bottom: 0;
        }

        .inventory-table {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .processing-badge {
            background: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.8em;
            font-weight: bold;
        }

        /* 响应式适配 - 1080P到1440P */
        @media (min-width: 1920px) {
            .header-content,
            .viewer-container {
                max-width: 1600px;
            }
            
            .form-group {
                min-width: 160px;
                max-width: 220px;
            }
        }

        @media (max-width: 1440px) {
            .form-row {
                gap: 12px;
            }

            .form-group {
                min-width: 120px;
                max-width: 180px;
            }
        }

        @media (max-width: 1200px) {
            .form-row {
                gap: 10px;
            }

            .form-group {
                min-width: 100px;
                max-width: 160px;
            }
        }

        @media (max-width: 1080px) {
            .form-row {
                flex-wrap: wrap;
            }
            
            .form-group {
                min-width: 200px;
                max-width: none;
            }
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
    <script src="../assets/js/datatable-config.js"></script>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../assets/css/datatable-theme.css">
</head>

<body>
    <div class="viewer-layout">
        <!-- 统一头部 -->
        <header class="viewer-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="system-title"><?php echo APP_NAME; ?> - 加工区库存</h1>
                </div>
                <div class="header-right">
                    <nav class="nav-links">
                        <a href="index.php" class="nav-link">首页</a>
                        <a href="/warehouse.php" class="nav-link">可视化库区</a>
                        <a href="/viewer/inventory.php" class="nav-link">库存查询</a>
                        <a href="/viewer/processing_inventory.php" class="nav-link active">加工区库存</a>
                        <a href="/admin/index.php" class="nav-link">进入后台</a>
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

            <div class="processing-container">
                <div class="search-form">
                    <form method="GET">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="base_id">基地</label>
                                <select id="base_id" name="base_id">
                                    <option value="">全部基地</option>
                                    <?php foreach ($bases as $base): ?>
                                        <option value="<?php echo $base['id']; ?>" <?php echo $baseId == $base['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($base['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>


                            <div class="form-group" style="display: flex; align-items: end;">
                                <button type="submit" class="btn btn-primary">查询</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="inventory-table" id="processingInventoryTableContainer">
                    <table id="processingInventoryTable" data-table="processingInventoryTable">
                        <thead>
                            <tr>
                                <th>包号</th>
                                <th>原片名称</th>
                                <th>颜色</th>
                                <th>厚度</th>
                                <th>尺寸</th>
                                <th>领用片数</th>
                                <th>当前架号</th>
                                <th>所属基地</th>
                                <th>领用时间</th>
                                <th>操作员</th>
                                <th>状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($processingInventory)): ?>
                                <tr>
                                    <td colspan="11" style="text-align: center; color: #666;">暂无数据</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($processingInventory as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['package_code']); ?></td>
                                        <td><?php echo htmlspecialchars($item['glass_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['color']); ?></td>
                                        <td><?php echo $item['thickness']; ?>mm</td>
                                        <td>
                                            <?php if ($item['width'] && $item['height']): ?>
                                                <?php echo $item['width']; ?>×<?php echo $item['height']; ?>mm
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo $item['usage_pieces']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($item['current_rack_code']); ?></td>
                                        <td><?php echo htmlspecialchars($item['base_name']); ?></td>
                                        <td><?php echo date('m-d H:i', strtotime($item['usage_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($item['operator_name']); ?></td>
                                        <td><span class="processing-badge">加工中</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script>
$(document).ready(function() {
    // 初始化DataTable
    const table = $('#processingInventoryTable').DataTable();
    
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