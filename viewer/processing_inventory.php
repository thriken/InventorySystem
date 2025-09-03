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

$whereConditions[] = "it.transaction_type = 'usage_out'";
$whereConditions[] = "sr_to.area_type = 'processing'";

// 基地筛选
if (!empty($baseId)) {
    $whereConditions[] = "sr_to.base_id = ?";
    $params[] = $baseId;
}

// 原片类型筛选
if (!empty($glassTypeId)) {
    $whereConditions[] = "gp.glass_type_id = ?";
    $params[] = $glassTypeId;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// 修改查询逻辑，显示当前在加工区的所有包（未归还的）
$sql = "SELECT 
            gp.package_code,
            gt.name as glass_name,
            gt.color,
            gt.thickness,
            gp.width,
            gp.height,
            gp.pieces as current_pieces,
            sr.code as current_rack_code,
            b.name as base_name,
            gp.updated_at as last_update_time,
            u.real_name as last_operator_name
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
        WHERE sr.area_type = 'processing'
        AND gp.status = 'in_processing'
        ORDER BY gp.updated_at DESC";

$processingInventory = fetchAll($sql);

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

        .page-title {
            font-size: 18px;
            margin: 0;
            opacity: 0.9;
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

        .form-group {
            flex: 1;
            min-width: 140px;
            max-width: 200px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            height: 38px;
            box-sizing: border-box;
        }

        .inventory-table {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #f8f9fa;
            font-weight: bold;
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
</head>

<body>
    <div class="viewer-layout">
        <!-- 统一头部 -->
        <header class="viewer-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="system-title"><?php echo APP_NAME; ?></h1>
                </div>
                <div class="header-right">
                    <nav class="nav-links">
                        <a href="index.php" class="nav-link">首页</a>
                        <a href="inventory.php" class="nav-link">库存查询</a>
                        <a href="processing_inventory.php" class="nav-link active">加工区库存</a>
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

                            <div class="form-group">
                                <label for="glass_type_id">原片类型</label>
                                <select id="glass_type_id" name="glass_type_id">
                                    <option value="">全部类型</option>
                                    <?php foreach ($glassTypes as $type): ?>
                                        <option value="<?php echo $type['id']; ?>" <?php echo $glassTypeId == $type['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['name']); ?>
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

                <div class="inventory-table">
                    <table>
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
</body>

</html>