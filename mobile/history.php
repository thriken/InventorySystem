<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// 检查登录状态
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$currentUser = getCurrentUser();

// 获取筛选参数
$search = trim($_GET['search'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$transactionType = $_GET['transaction_type'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// 构建查询条件
$whereConditions = [];
$params = [];

// 权限控制：普通用户只能查看自己的记录
if ($currentUser['role'] == 'opereator') {
    $whereConditions[] = "it.operator_id = ?";
    $params[] = $currentUser['id'];
}

if (!empty($search)) {
    $whereConditions[] = "(gp.package_code LIKE ? OR gt.name LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if (!empty($dateFrom)) {
    $whereConditions[] = "DATE(it.operation_date) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = "DATE(it.operation_date) <= ?";
    $params[] = $dateTo;
}

if (!empty($transactionType)) {
    $whereConditions[] = "it.operation_type = ?";
    $params[] = $transactionType;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// 获取总记录数
$countSql = "SELECT COUNT(*) as total 
             FROM inventory_operation_records it 
             LEFT JOIN glass_packages gp ON it.package_id = gp.id 
             LEFT JOIN glass_types gt ON gp.glass_type_id = gt.id 
             $whereClause";
$totalResult = fetchRow($countSql, $params);
$totalRecords = $totalResult['total'];
$totalPages = ceil($totalRecords / $limit);

// 获取历史记录
$sql = "SELECT it.*, gp.package_code, gt.name as glass_name, gt.color, gt.thickness,
               sr_from.code as from_rack_code, sr_from.area_type as from_area_type,
               sr_to.code as to_rack_code, sr_to.area_type as to_area_type,
               b_from.name as from_base_name, b_to.name as to_base_name,
               u.real_name as operator_name
        FROM inventory_operation_records it 
        LEFT JOIN glass_packages gp ON it.package_id = gp.id 
        LEFT JOIN glass_types gt ON gp.glass_type_id = gt.id 
        LEFT JOIN storage_racks sr_from ON it.from_rack_id = sr_from.id 
        LEFT JOIN storage_racks sr_to ON it.to_rack_id = sr_to.id 
        LEFT JOIN bases b_from ON sr_from.base_id = b_from.id 
        LEFT JOIN bases b_to ON sr_to.base_id = b_to.id 
        LEFT JOIN users u ON it.operator_id = u.id 
        $whereClause 
        ORDER BY it.created_at DESC 
        LIMIT $limit OFFSET $offset";

$transactions = fetchAll($sql, $params);

// 定义操作类型和区域类型的中文名称
$transactionTypes = [
    'purchase_in' => '采购入库',
    'usage_out' => '领用出库',
    'return_in' => '归还入库',
    'scrap' => '报废出库',
    'partial_usage' => '部分领用',
    'check_in' => '盘点入库',
    'check_out' => '盘点出库',
    'location_adjust' => '库位转移'
];

$areaTypes = [
    'temporary' => '临时区',
    'storage' => '存储区',
    'processing' => '加工区',
    'scrap' => '报废区'
];
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>历史记录 - 玻璃库存管理系统</title>
    <link rel="stylesheet" href="../assets/css/mobile.css">
    <style>
        .history-container {
            padding: 20px;
            max-width: 100%;
            margin-bottom: 57px;
        }

        .search-form {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .form-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .form-group {
            flex: 1;
            min-width: 120px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .search-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .transaction-list {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .transaction-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .transaction-item:last-child {
            border-bottom: none;
        }

        .transaction-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .transaction-type {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }

        .type-purchase_in {
            background: #28a745;
        }

        .type-usage_out {
            background: #dc3545;
        }

        .type-return_in {
            background: #17a2b8;
        }

        .type-scrap {
            background: #6c757d;
        }
        .type-partial_usage {
            background: #ffc107;
        }
        .type-check_in {
            background: #28a745;
        }
        .type-check_out {
            background: #dc3545;
        }
        .type-location_adjust {
            background: #007bff;
        }

        .transaction-time {
            font-size: 12px;
            color: #666;
        }

        .transaction-details {
            font-size: 14px;
            line-height: 1.4;
        }

        .package-info {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .flow-info {
            color: #666;
            margin-bottom: 5px;
        }

        .quantity-info {
            color: #007bff;
            font-weight: bold;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            gap: 10px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }

        .pagination .current {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .stats {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stats-item {
            display: inline-block;
            margin: 0 15px;
        }

        .stats-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }

        .stats-label {
            font-size: 12px;
            color: #666;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            text-decoration: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
            z-index: 1000;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .back-icon {
            font-size: 24px;
            font-weight: bold;
            line-height: 1;
        }

        .back-button:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
        }

        .back-button:active {
            transform: translateY(0) scale(0.98);
        }
    </style>
</head>

<body>
    <div class="history-container">
        <h1>操作历史记录</h1>
        <!-- 统计信息 -->
        <div class="stats">
            <div class="stats-item">
                <div class="stats-number"><?php echo $totalRecords; ?></div>
                <div class="stats-label">总记录数</div>
            </div>
            <div class="stats-item">
                <div class="stats-number"><?php echo $totalPages; ?></div>
                <div class="stats-label">总页数</div>
            </div>
        </div>
        <!-- 搜索表单 -->
        <form class="search-form" method="GET">
            <div class="form-row">
                <div class="form-group">
                    <label for="search">包号/原片</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="输入包号或原片名称">
                </div>
                <div class="form-group">
                    <label for="transaction_type">操作类型</label>
                    <select id="transaction_type" name="transaction_type">
                        <option value="">全部类型</option>
                        <?php foreach ($transactionTypes as $type => $name): ?>
                            <option value="<?php echo $type; ?>" <?php echo $transactionType === $type ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="date_from">开始日期</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>
                <div class="form-group">
                    <label for="date_to">结束日期</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
            </div>

            <div class="search-buttons">
                <button type="submit" class="btn btn-primary">搜索</button>
                <a href="history.php" class="btn btn-secondary">重置</a>
            </div>
        </form>

        <!-- 历史记录列表 -->
        <?php if (empty($transactions)): ?>
            <div class="no-data">
                <p>暂无历史记录</p>
            </div>
        <?php else: ?>
            <div class="transaction-list">
                <?php foreach ($transactions as $transaction): ?>
                    <div class="transaction-item">
                        <div class="transaction-header">
                            <span class="transaction-type type-<?php echo $transaction['operation_type']; ?>">
                                <?php echo $transactionTypes[$transaction['operation_type']] ?? $transaction['operation_type']; ?>
                            </span>
                            <span class="transaction-time">
                                <?php echo date('Y-m-d H:i:s', strtotime($transaction['created_at'])); ?>
                            </span>
                        </div>

                        <div class="transaction-details">
                            <div class="package-info">
                                包号: <?php echo htmlspecialchars($transaction['package_code']); ?>
                                <?php if ($transaction['glass_name']): ?>
                                    - <?php echo htmlspecialchars($transaction['glass_name']); ?>
                                    (<?php echo $transaction['thickness']; ?>mm, <?php echo htmlspecialchars($transaction['color']); ?>)
                                <?php endif; ?>
                            </div>

                            <div class="flow-info">
                                <?php if ($transaction['from_rack_code']): ?>
                                    从: <?php echo htmlspecialchars($transaction['from_rack_code']); ?>
                                    (<?php echo $areaTypes[$transaction['from_area_type']] ?? $transaction['from_area_type']; ?>)
                                    <?php if ($transaction['from_base_name']): ?>
                                        - <?php echo htmlspecialchars($transaction['from_base_name']); ?>
                                    <?php endif; ?>
                                    <br>
                                <?php endif; ?>

                                到: <?php echo htmlspecialchars($transaction['to_rack_code']); ?>
                                (<?php echo $areaTypes[$transaction['to_area_type']] ?? $transaction['to_area_type']; ?>)
                                <?php if ($transaction['to_base_name']): ?>
                                    - <?php echo htmlspecialchars($transaction['to_base_name']); ?>
                                <?php endif; ?>
                            </div>

                            <div class="quantity-info">
                                数量: <?php echo $transaction['operation_quantity']; ?> 片
                                <?php if ($transaction['operator_name']): ?>
                                    | 操作员: <?php echo htmlspecialchars($transaction['operator_name']); ?>
                                <?php endif; ?>
                            </div>

                            <?php if ($transaction['scrap_reason']): ?>
                                <div style="color: #dc3545; margin-top: 5px;">
                                    报废原因: <?php echo htmlspecialchars($transaction['scrap_reason']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- 分页 -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">上一页</a>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);

                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">下一页</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="mobile-footer">
        <a href="index.php">🏠<br>首页</a>
        <a href="scan.php">📷<br>扫描</a>
        <a href="history.php">📋<br>记录</a>
        <a href="../logout.php">🚪<br>退出</a>
    </div>

</body>

</html>