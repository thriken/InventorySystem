<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/admin_layout.php';

requireLogin();
requireRole(['admin', 'manager']);
$areaTypes = [
    'storage' => '库存区',
    'processing' => '加工区',
    'scrap' => '报废区',
    'temporary' => '临时区'
];
$transactionTypes = [
    'usage_out' => '领用出库',
    'scrap' => '报废'
];
$currentUser = getCurrentUser();
$message = '';
$error = '';

// 获取基地列表
$bases = fetchAll("SELECT id, name, code FROM bases ORDER BY name");

// 获取查询参数
$reportDate = $_GET['report_date'] ?? date('Y-m-d');
$baseId = $_GET['base_id'] ?? '';

// 计算时间范围：当日8点到次日8点
$startTime = $reportDate . ' 08:00:00';
$endTime = date('Y-m-d H:i:s', strtotime($reportDate . ' +1 day 08:00:00'));

// 获取每日领用总表数据 - 直接使用 inventory_transactions 表
// 修改第28-43行的SQL查询
$sql = "SELECT 
            gt.name as glass_name,
            gt.color,
            gt.thickness,
            gp.width,
            gp.height,
            'processing' as target_area,
            COALESCE(b.name, '未知基地') as target_base_name,
            SUM(CASE WHEN it.transaction_type = 'usage_out' THEN it.quantity ELSE 0 END) as usage_out_pieces,
            SUM(CASE WHEN it.transaction_type = 'return_in' THEN it.quantity ELSE 0 END) as return_in_pieces,
            SUM(CASE WHEN it.transaction_type = 'usage_out' THEN it.quantity ELSE 0 END) - 
            SUM(CASE WHEN it.transaction_type = 'return_in' THEN it.quantity ELSE 0 END) as actual_usage,
            SUM(CASE WHEN it.transaction_type = 'partial_usage' THEN COALESCE(it.actual_usage, it.quantity) ELSE 0 END) as partial_usage_pieces,
            SUM(CASE WHEN it.transaction_type = 'scrap' THEN it.quantity ELSE 0 END) as scrap_pieces,
            COUNT(DISTINCT it.package_id) as package_count
        FROM inventory_transactions it
        LEFT JOIN glass_packages gp ON it.package_id = gp.id
        LEFT JOIN glass_types gt ON gp.glass_type_id = gt.id
        LEFT JOIN storage_racks sr ON gp.current_rack_id = sr.id
        LEFT JOIN bases b ON sr.base_id = b.id
        WHERE it.transaction_time >= ?
        AND it.transaction_time < ?
        AND it.transaction_type IN ('usage_out', 'partial_usage', 'return_in', 'scrap')";

$params = [$startTime, $endTime];
$sql .= " GROUP BY gt.id, gt.name, gt.color, gt.thickness, gp.width, gp.height, b.name
         HAVING (usage_out_pieces > 0 OR partial_usage_pieces > 0 OR scrap_pieces > 0)
         ORDER BY gt.name";

$dailyUsageData = fetchAll($sql, $params);

// 计算面积数据
foreach ($dailyUsageData as &$row) {
    // 计算单片面积（平方米）
    if ($row['width'] && $row['height']) {
        $row['single_area'] = round(($row['width'] / 1000) * ($row['height'] / 1000), 2);
        $totalPieces = $row['actual_usage'] + $row['partial_usage_pieces'] + $row['scrap_pieces'];
        $row['total_area'] = round($row['single_area'] * $totalPieces, 2);
        $row['specification'] = $row['width'] . 'x' . $row['height'];
    } else {
        $row['single_area'] = 0;
        $row['total_area'] = 0;
        $row['specification'] = '未设置';
    }
}
unset($row);
$summarySql = "SELECT 
                SUM(CASE WHEN it.transaction_type = 'usage_out' THEN it.quantity ELSE 0 END) as total_usage_out,
                SUM(CASE WHEN it.transaction_type = 'return_in' THEN it.quantity ELSE 0 END) as total_return_in,
                SUM(CASE WHEN it.transaction_type = 'usage_out' THEN it.quantity ELSE 0 END) - 
                SUM(CASE WHEN it.transaction_type = 'return_in' THEN it.quantity ELSE 0 END) as total_actual_usage,
                SUM(CASE WHEN it.transaction_type = 'partial_usage' THEN COALESCE(it.actual_usage, it.quantity) ELSE 0 END) as total_partial_usage,
                SUM(CASE WHEN it.transaction_type = 'scrap' THEN it.quantity ELSE 0 END) as total_scrap,
                SUM(CASE 
                    WHEN gp.width > 0 AND gp.height > 0 
                    THEN ROUND((gp.width / 1000) * (gp.height / 1000) * 
                        CASE 
                            WHEN it.transaction_type = 'usage_out' THEN it.quantity
                            WHEN it.transaction_type = 'return_in' THEN -it.quantity
                            WHEN it.transaction_type = 'partial_usage' THEN COALESCE(it.actual_usage, it.quantity)
                            WHEN it.transaction_type = 'scrap' THEN it.quantity
                            ELSE 0
                        END, 2)
                    ELSE 0
                END) as total_area,
                COUNT(DISTINCT it.package_id) as total_packages
               FROM inventory_transactions it
               LEFT JOIN glass_packages gp ON it.package_id = gp.id
               WHERE it.transaction_time >= ?
               AND it.transaction_time < ?
               AND it.transaction_type IN ('usage_out', 'partial_usage', 'return_in', 'scrap')";

$summaryParams = [$startTime, $endTime];

$summaryResult = fetchAll($summarySql, $summaryParams);
$summaryData = !empty($summaryResult) ? $summaryResult[0] : [];
// 添加专用CSS文件
$additionalCSS = ['../assets/css/admin/report.css'];
ob_start();

?>
<div class="content-card">  
    <div class="search-container">
        <form method="GET" class="filter-form">
            <div class="search-grid">
                <div class="form-group">
                    <label for="report_date">报表日期</label>
                    <input type="date" id="report_date" name="report_date" value="<?php echo htmlspecialchars($reportDate); ?>">
                </div>
                
                <div class="form-group">
                    <label for="base_id">基地筛选</label>
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
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">查询</button>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => '1'])); ?>" class="btn btn-secondary export-btn">导出Excel</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <!-- 统计汇总卡片 -->
    <div class="summary-cards">
        <?php 
        $totalActualUsage = $summaryData['total_actual_usage'] ?? 0;
        $totalPartialUsage = $summaryData['total_partial_usage'] ?? 0;
        $totalScrap = $summaryData['total_scrap'] ?? 0;
        $totalArea = $summaryData['total_area'] ?? 0;
        $totalPackages = $summaryData['total_packages'] ?? 0;
        ?>
        
        <div class="summary-card">
            <div class="value"><?php echo $totalActualUsage; ?></div>
            <div class="label">实际领用（片）</div>
        </div>
        
        <div class="summary-card">
            <div class="value"><?php echo $totalPartialUsage; ?></div>
            <div class="label">部分领用（片）</div>
        </div>
        
        <div class="summary-card">
            <div class="value"><?php echo $totalScrap; ?></div>
            <div class="label">报废（片）</div>
        </div>
        
        <div class="summary-card">
            <div class="value"><?php echo round($totalArea, 2); ?></div>
            <div class="label">总面积（㎡）</div>
        </div>
        
        <div class="summary-card">
            <div class="value"><?php echo $totalActualUsage + $totalPartialUsage + $totalScrap; ?></div>
            <div class="label">总计出库（片）</div>
        </div>
        
        <div class="summary-card">
            <div class="value"><?php echo $totalPackages; ?></div>
            <div class="label">涉及包数</div>
        </div>
    </div>
    
    <!-- 详细数据表格 -->
    <div class="table-container">
        <div class="table-responsive">
            <table class="table table-striped data-table" data-table="reports">
                <thead>
                    <tr>
                        <th>原片名称</th>
                        <th>颜色</th>
                        <th>厚度</th>
                        <th>规格</th>
                        <th>单片面积</th>
                        <th>基地</th>
                        <th>领用出库</th>
                        <th>归还入库</th>
                        <th>实际领用</th>
                        <th>部分领用</th>
                        <th>直接报废</th>
                        <th>总计消耗</th>
                        <th>总面积</th>
                        <th>包数</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dailyUsageData)): ?>
                        <tr>
                            <td colspan="14" class="text-center no-data">暂无数据</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dailyUsageData as $row): ?>
                            <?php 
                            // 总计消耗 = 实际领用 + 部分领用 + 报废
                            $totalConsumption = $row['actual_usage'] + $row['partial_usage_pieces'] + $row['scrap_pieces'];
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['glass_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['color']); ?></td>
                                <td><?php echo $row['thickness']; ?>mm</td>
                                <td><?php echo $row['specification']; ?></td>
                                <td><?php echo $row['single_area']; ?>㎡</td>
                                <td><?php echo htmlspecialchars($row['target_base_name']); ?></td>
                                <td><?php echo $row['usage_out_pieces']; ?></td>
                                <td><?php echo $row['return_in_pieces']; ?></td>
                                <td><strong><?php echo $row['actual_usage']; ?></strong></td>
                                <td><?php echo $row['partial_usage_pieces']; ?></td>
                                <td><?php echo $row['scrap_pieces']; ?></td>
                                <td><strong><?php echo $totalConsumption; ?></strong></td>
                                <td><strong><?php echo $row['total_area']; ?>㎡</strong></td>
                                <td><?php echo $row['package_count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// 自动刷新时间显示
function updateTime() {
    const now = new Date();
    const timeString = now.toLocaleString('zh-CN');
    document.title = '每日领用总表 - ' + timeString;
}

setInterval(updateTime, 1000);
updateTime();
</script>
</body>
</html>
<?php
$content = ob_get_clean();
// 渲染页面
echo renderAdminLayout('每日领用总表', $content, $currentUser, 'reports.php',$additionalCSS, [], $message ?? '', $messageType ?? 'info');

?>
