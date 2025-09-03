<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/admin_layout.php';

// 要求用户登录
requireLogin();

// 检查是否为管理员或经理
requireRole(['admin', 'manager', 'operator']);

$sql_storage_packages = "SELECT COUNT(*) FROM glass_packages gp
  left join storage_racks sr on gp.current_rack_id = sr.id WHERE gp.status = 'in_storage'";
$sql_storage_area = "SELECT COALESCE(SUM(width * height * pieces / 1000000), 0) FROM glass_packages gp
  left join storage_racks sr on gp.current_rack_id = sr.id WHERE gp.status = 'in_storage'";
$sql_processing_packages = "SELECT COUNT(*) FROM glass_packages gp
  left join storage_racks sr on gp.current_rack_id = sr.id WHERE gp.status = 'in_processing'";
$sql_processing_area = "SELECT COALESCE(SUM(width * height * pieces / 1000000), 0) FROM glass_packages gp
  left join storage_racks sr on gp.current_rack_id = sr.id WHERE gp.status = 'in_processing'";

// 获取当前用户信息
$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    $sql_storage_packages .= " AND sr.base_id = {$currentUser['base_id']}";
    $sql_storage_area .= " AND sr.base_id = {$currentUser['base_id']}";
    $sql_processing_packages .= " AND sr.base_id = {$currentUser['base_id']}";
    $sql_processing_area .= " AND sr.base_id = {$currentUser['base_id']}";
} else {
    
}
$systemStats = [
        // 库存包数和面积
        'storage_packages' => fetchOne($sql_storage_packages),
        'storage_area' => fetchOne($sql_storage_area),
        'processing_packages' => fetchOne($sql_processing_packages),
        'processing_area' => fetchOne($sql_processing_area),
    ];
// 获取最近流转记录（最近5条）
$recentTransactions = fetchAll("SELECT t.*, p.package_code, u.username, g.name as glass_name,
                                      sr_from.code as from_rack_code, sr_to.code as to_rack_code
                              FROM inventory_transactions t 
                              LEFT JOIN glass_packages p ON t.package_id = p.id 
                              LEFT JOIN users u ON t.operator_id = u.id 
                              LEFT JOIN glass_types g ON p.glass_type_id = g.id 
                              LEFT JOIN storage_racks sr_from ON t.from_rack_id = sr_from.id
                              LEFT JOIN storage_racks sr_to ON t.to_rack_id = sr_to.id
                              WHERE t.transaction_type NOT IN ('purchase_in')
                              ORDER BY t.transaction_time DESC LIMIT 10");

// 获取最近采购入库记录（最近5条）
$recentPurchases = fetchAll("SELECT t.*, p.package_code, u.username, g.name as glass_name, g.color, g.thickness,
                                    p.width, p.height, p.pieces
                            FROM inventory_transactions t 
                            LEFT JOIN glass_packages p ON t.package_id = p.id 
                            LEFT JOIN users u ON t.operator_id = u.id 
                            LEFT JOIN glass_types g ON p.glass_type_id = g.id 
                            WHERE t.transaction_type = 'purchase_in'
                            ORDER BY t.transaction_time DESC LIMIT 10");

// 获取本月消耗统计（按原片类型）
$monthStart = date('Y-m-01 00:00:00');
$monthEnd = date('Y-m-t 23:59:59');
$monthlyConsumption = fetchAll("SELECT g.name as glass_name, g.color, g.thickness,
                                      SUM(CASE WHEN t.transaction_type IN ('usage_out', 'partial_usage') THEN t.quantity ELSE 0 END) as total_usage,
                                      SUM(CASE WHEN t.transaction_type = 'scrap' THEN t.quantity ELSE 0 END) as total_scrap,
                                      SUM(CASE WHEN t.transaction_type IN ('usage_out', 'partial_usage', 'scrap') THEN t.quantity ELSE 0 END) as total_consumption
                              FROM inventory_transactions t
                              LEFT JOIN glass_packages p ON t.package_id = p.id
                              LEFT JOIN glass_types g ON p.glass_type_id = g.id
                              WHERE t.transaction_time >= ? AND t.transaction_time <= ?
                              AND t.transaction_type IN ('usage_out', 'partial_usage', 'scrap')
                              GROUP BY g.id, g.name, g.color, g.thickness
                              HAVING total_consumption > 0
                              ORDER BY total_consumption DESC LIMIT 10", [$monthStart, $monthEnd]);

// 获取本月消耗统计（按颜色）
$monthlyColorConsumption = fetchAll("SELECT g.color,
                                           SUM(CASE WHEN t.transaction_type IN ('usage_out', 'partial_usage', 'scrap') THEN t.quantity ELSE 0 END) as total_consumption,
                                           COUNT(DISTINCT p.id) as package_count
                                   FROM inventory_transactions t
                                   LEFT JOIN glass_packages p ON t.package_id = p.id
                                   LEFT JOIN glass_types g ON p.glass_type_id = g.id
                                   WHERE t.transaction_time >= ? AND t.transaction_time <= ?
                                   AND t.transaction_type IN ('usage_out', 'partial_usage', 'scrap')
                                   AND g.color IS NOT NULL AND g.color != ''
                                   GROUP BY g.color
                                   HAVING total_consumption > 0
                                   ORDER BY total_consumption DESC LIMIT 10", [$monthStart, $monthEnd]);

// 页面内容
ob_start();
?>
<div class="dashboard-container">
    <!-- 系统状态概览 - 独占一行 -->
    <div class="dashboard-overview">
            <h3>📊 系统状态概览</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($systemStats['storage_packages']); ?></div>
                    <div class="stat-label">库存包数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($systemStats['storage_area'], 1); ?></div>
                    <div class="stat-label">库存面积(㎡)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($systemStats['processing_packages']); ?></div>
                    <div class="stat-label">加工中包数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($systemStats['processing_area'], 1); ?></div>
                    <div class="stat-label">加工中面积(㎡)</div>
                </div>
            </div>
    </div>

    <div class="dashboard-grid">
        <!-- 最近流转记录 -->
        <div class="dashboard-card">
            <h3>🔄 最近流转记录</h3>
            <div class="recent-list">
                <?php if (empty($recentTransactions)): ?>
                    <div style="text-align: center; color: #7f8c8d; padding: 20px;">暂无流转记录</div>
                <?php else: ?>
                    <?php foreach ($recentTransactions as $transaction): ?>
                        <div class="recent-item">
                            <div class="item-info">
                                <div class="item-title"><?php echo htmlspecialchars($transaction['glass_name']); ?></div>
                                <div class="item-detail">
                                    <?php echo htmlspecialchars($transaction['package_code']); ?> | 
                                    <?php
                                    $typeLabels = [
                                        'usage_out' => '<span class="label label-warning">领用出库</span>',
                                        'return_in' => '<span class="label label-info">归还入库</span>',
                                        'scrap' => '<span class="label label-danger">报废出库</span>',
                                        'partial_usage' => '<span class="label label-warning">部分领用</span>',
                                        'check_in' => '<span class="label label-success">盘点入库</span>',
                                        'check_out' => '<span class="label label-danger">盘点出库</span>',
                                        'location_adjust' => '<span class="label label-info">位置调整</span>',
                                    ];
                                    echo $typeLabels[$transaction['transaction_type']] ?? $transaction['transaction_type'];
                                    ?> | 
                                    <?php echo $transaction['quantity']; ?>片
                                </div>
                            </div>
                            <div class="item-time"><?php echo date('m-d H:i', strtotime($transaction['transaction_time'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div style="text-align: center; margin-top: 15px;">
                <a href="transactions.php" class="btn btn-sm">查看全部记录</a>
            </div>
        </div>

        <!-- 最近采购入库 -->
        <div class="dashboard-card">
            <h3>📦 最近采购入库</h3>
            <div class="recent-list">
                <?php if (empty($recentPurchases)): ?>
                    <div style="text-align: center; color: #7f8c8d; padding: 20px;">暂无采购记录</div>
                <?php else: ?>
                    <?php foreach ($recentPurchases as $purchase): ?>
                        <div class="recent-item">
                            <div class="item-info">
                                <div class="item-title"><?php echo htmlspecialchars($purchase['package_code']); ?></div>
                                <div class="item-detail">
                                    <?php echo htmlspecialchars($purchase['glass_name']); ?> | 
                                    <?php echo $purchase['color']; ?> | 
                                    <?php echo $purchase['thickness']; ?>mm | 
                                    <?php echo $purchase['pieces']; ?>片
                                </div>
                            </div>
                            <div class="item-time"><?php echo date('m-d H:i', strtotime($purchase['transaction_time'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div style="text-align: center; margin-top: 15px;">
                <a href="transactions.php?type=purchase_in" class="btn btn-sm">查看全部采购</a>
            </div>
        </div>

        <!-- 本月消耗统计 -->
        <div class="dashboard-card">
            <h3>📈 本月原片消耗统计</h3>
            <div style="margin-bottom: 15px;">
                <?php if (empty($monthlyConsumption)): ?>
                    <div style="text-align: center; color: #7f8c8d; padding: 10px;">本月暂无消耗</div>
                <?php else: ?>
                    <?php foreach ($monthlyConsumption as $item): ?>
                        <div class="consumption-item">
                            <div class="consumption-name">
                                <?php echo htmlspecialchars($item['glass_name']); ?>
                                <small style="color: #7f8c8d;">(<?php echo $item['color']; ?> <?php echo $item['thickness']; ?>mm)</small>
                            </div>
                            <div class="consumption-value"><?php echo number_format($item['total_consumption']); ?>片</div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center; margin-top: 15px;">
                <a href="reports.php" class="btn btn-sm">查看详细报表</a>
            </div>
        </div>

        <!-- 本月颜色消耗统计 -->
        <div class="dashboard-card">
            <h3>🎨 本月色系消耗</h3>
            <div>
                <?php if (empty($monthlyColorConsumption)): ?>
                    <div style="text-align: center; color: #7f8c8d; padding: 20px;">本月暂无消耗</div>
                <?php else: ?>
                    <?php foreach ($monthlyColorConsumption as $item): ?>
                        <div class="consumption-item">
                            <div class="consumption-name">
                                <?php echo htmlspecialchars($item['color']); ?>
                                <small style="color: #7f8c8d;">(<?php echo $item['package_count']; ?>包)</small>
                            </div>
                            <div class="consumption-value"><?php echo number_format($item['total_consumption']); ?>片</div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center; margin-top: 15px;">
                <a href="reports.php" class="btn btn-sm">查看详细报表</a>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
// 渲染页面
echo renderAdminLayout('仪表盘', $content, $currentUser, 'index.php', [], [], $message ?? '', $messageType ?? 'info');
?>