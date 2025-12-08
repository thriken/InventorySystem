<?php include 'header.php'; ?>

<div class="container-fluid">
    <!-- 页面头部 -->
    <div class="row">
        <div class="col-md-12">
            <div class="page-header clearfix">
                <h2 class="pull-left">
                    <i class="glyphicon glyphicon-stats"></i> 盘点报告
                </h2>
                <div class="pull-right">
                    <a href="inventory_check.php" class="btn btn-default">
                        <i class="glyphicon glyphicon-arrow-left"></i> 返回列表
                    </a>
                    <a href="inventory_check.php?action=export&id=<?php echo $task['id']; ?>" class="btn btn-success">
                        <i class="glyphicon glyphicon-download"></i> 导出Excel
                    </a>
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="glyphicon glyphicon-print"></i> 打印报告
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 基本信息 -->
    <div class="panel panel-info">
        <div class="panel-heading">
            <h4>任务基本信息</h4>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>任务名称：</strong><?php echo htmlspecialchars($task['task_name']); ?>
                </div>
                <div class="col-md-3">
                    <strong>基地：</strong><?php echo htmlspecialchars($task['base_name']); ?>
                </div>
                <div class="col-md-3">
                    <strong>盘点类型：</strong>
                    <?php
                    switch ($task['task_type']) {
                        case 'full':
                            echo '<span class="label label-primary">全盘</span>';
                            break;
                        case 'partial':
                            echo '<span class="label label-warning">部分盘点</span>';
                            break;
                        case 'random':
                            echo '<span class="label label-info">抽盘</span>';
                            break;
                    }
                    ?>
                </div>
                <div class="col-md-3">
                    <strong>状态：</strong>
                    <span class="label label-success">已完成</span>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <strong>创建时间：</strong><?php echo date('Y-m-d H:i', strtotime($task['created_at'])); ?>
                </div>
                <div class="col-md-3">
                    <strong>开始时间：</strong><?php echo date('Y-m-d H:i', strtotime($task['started_at'])); ?>
                </div>
                <div class="col-md-3">
                    <strong>完成时间：</strong><?php echo date('Y-m-d H:i', strtotime($task['completed_at'])); ?>
                </div>
                <div class="col-md-3">
                    <strong>耗时：</strong><?php echo $task['duration']; ?>
                </div>
            </div>
            <?php if ($task['description']): ?>
            <div class="row">
                <div class="col-md-12">
                    <strong>盘点说明：</strong><?php echo htmlspecialchars($task['description']); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 统计概览 -->
    <div class="row">
        <div class="col-md-3">
            <div class="panel panel-primary">
                <div class="panel-heading text-center">
                    <h4>总包数</h4>
                </div>
                <div class="panel-body text-center">
                    <h2><?php echo $task['total_packages']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-success">
                <div class="panel-heading text-center">
                    <h4>已盘点</h4>
                </div>
                <div class="panel-body text-center">
                    <h2><?php echo $task['checked_packages']; ?></h2>
                    <small>完成率：<?php echo $task['completion_rate']; ?>%</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-info">
                <div class="panel-heading text-center">
                    <h4>差异包数</h4>
                </div>
                <div class="panel-body text-center">
                    <h2><?php echo $task['difference_count']; ?></h2>
                    <small>差异率：<?php echo $task['total_packages'] > 0 ? round(($task['difference_count'] / $task['total_packages']) * 100, 2) : 0; ?>%</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-warning">
                <div class="panel-heading text-center">
                    <h4>正常包数</h4>
                </div>
                <div class="panel-body text-center">
                    <h2><?php echo $task['checked_packages'] - $task['difference_count']; ?></h2>
                    <small>正常率：<?php echo $task['checked_packages'] > 0 ? round((($task['checked_packages'] - $task['difference_count']) / $task['checked_packages']) * 100, 2) : 0; ?>%</small>
                </div>
            </div>
        </div>
    </div>

    <!-- 差异明细 -->
    <?php if (!empty($differences)): ?>
    <div class="panel panel-warning">
        <div class="panel-heading">
            <h4>差异明细</h4>
        </div>
        <div class="panel-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>包号</th>
                            <th>原片名称</th>
                            <th>系统数量</th>
                            <th>盘点数量</th>
                            <th>差异</th>
                            <th>差异类型</th>
                            <th>库位</th>
                            <th>盘点方式</th>
                            <th>操作员</th>
                            <th>盘点时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($differences as $diff): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($diff['package_code']); ?></code></td>
                            <td><?php echo htmlspecialchars($diff['glass_name']); ?></td>
                            <td class="text-center">
                                <span class="badge"><?php echo $diff['system_quantity']; ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-primary"><?php echo $diff['check_quantity']; ?></span>
                            </td>
                            <td class="text-center">
                                <span class="label <?php echo $diff['difference'] > 0 ? 'label-success' : 'label-danger'; ?>">
                                    <?php echo htmlspecialchars($diff['difference_display']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="label <?php echo $diff['difference'] > 0 ? 'label-success' : 'label-danger'; ?>">
                                    <?php echo htmlspecialchars($diff['difference_type']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($diff['rack_code']); ?></td>
                            <td>
                                <?php
                                switch ($diff['check_method']) {
                                    case 'pda_scan':
                                        echo '<span class="label label-info">PDA扫码</span>';
                                        break;
                                    case 'manual_input':
                                        echo '<span class="label label-default">手动录入</span>';
                                        break;
                                    case 'excel_import':
                                        echo '<span class="label label-warning">Excel导入</span>';
                                        break;
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($diff['operator_name']); ?></td>
                            <td><?php echo date('m-d H:i', strtotime($diff['check_time'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="panel panel-success">
        <div class="panel-body text-center" style="padding: 50px;">
            <i class="glyphicon glyphicon-ok-circle" style="font-size: 48px; color: #5cb85c;"></i>
            <h3>恭喜！盘点无差异</h3>
            <p>所有盘点包的数量与系统库存一致</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- 原片类型汇总 -->
    <?php if ($summary): ?>
    <div class="panel panel-info">
        <div class="panel-heading">
            <h4>原片类型汇总</h4>
        </div>
        <div class="panel-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>原片类型</th>
                            <th>系统总数</th>
                            <th>盘点总数</th>
                            <th>总差异</th>
                            <th>盘盈包数</th>
                            <th>盘亏包数</th>
                            <th>正常包数</th>
                            <th>准确率</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summary as $item): ?>
                        <tr>
                            <td><?php echo getGlassTypeName($item['glass_type_id']); ?></td>
                            <td class="text-center"><?php echo $item['total_system_quantity']; ?></td>
                            <td class="text-center"><?php echo $item['total_check_quantity']; ?></td>
                            <td class="text-center">
                                <?php if ($item['total_difference'] != 0): ?>
                                    <span class="label <?php echo $item['total_difference'] > 0 ? 'label-success' : 'label-danger'; ?>">
                                        <?php echo ($item['total_difference'] > 0 ? '+' : '') . $item['total_difference']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="label label-default">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($item['profit_packages'] > 0): ?>
                                    <span class="badge badge-success"><?php echo $item['profit_packages']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($item['loss_packages'] > 0): ?>
                                    <span class="badge badge-danger"><?php echo $item['loss_packages']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge"><?php echo $item['normal_packages']; ?></span>
                            </td>
                            <td class="text-center">
                                <?php
                                $totalPackages = $item['profit_packages'] + $item['loss_packages'] + $item['normal_packages'];
                                $accuracy = $totalPackages > 0 ? round(($item['normal_packages'] / $totalPackages) * 100, 2) : 100;
                                ?>
                                <div class="progress" style="height: 20px; margin-bottom: 0;">
                                    <div class="progress-bar progress-bar-<?php echo $accuracy >= 95 ? 'success' : ($accuracy >= 80 ? 'warning' : 'danger'); ?>" 
                                         role="progressbar" style="width: <?php echo $accuracy; ?>%;">
                                        <?php echo $accuracy; ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 图表分析 -->
    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4>差异类型分布</h4>
                </div>
                <div class="panel-body text-center">
                    <canvas id="differenceChart" width="400" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4>盘点方式分布</h4>
                </div>
                <div class="panel-body text-center">
                    <canvas id="methodChart" width="400" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- 操作建议 -->
    <?php if ($task['difference_count'] > 0): ?>
    <div class="panel panel-warning">
        <div class="panel-heading">
            <h4>处理建议</h4>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="glyphicon glyphicon-exclamation-sign"></i> 盘亏处理</h5>
                    <ul>
                        <li>检查是否为正常领用未及时记录</li>
                        <li>确认是否有破损、丢失情况</li>
                        <li>核实原片入库记录是否准确</li>
                        <li>如需要，填写盘亏说明并调整库存</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h5><i class="glyphicon glyphicon-plus-sign"></i> 盘盈处理</h5>
                    <ul>
                        <li>检查是否有重复盘点</li>
                        <li>确认是否有未记录的入库</li>
                        <li>核实原片包装数量记录</li>
                        <li>如需要，填写盘盈说明并调整库存</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- 图表脚本 -->
<script src="https://cdn.bootcdn.net/ajax/libs/Chart.js/4.4.0/chart.min.js"></script>
<script>
$(document).ready(function() {
    // 差异类型分布图
    var differenceCtx = document.getElementById('differenceChart').getContext('2d');
    new Chart(differenceCtx, {
        type: 'pie',
        data: {
            labels: ['正常', '盘盈', '盘亏'],
            datasets: [{
                data: [
                    <?php echo $task['checked_packages'] - $task['difference_count']; ?>,
                    <?php echo getProfitCount($differences); ?>,
                    <?php echo getLossCount($differences); ?>
                ],
                backgroundColor: ['#5cb85c', '#f0ad4e', '#d9534f']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    
    // 盘点方式分布图
    var methodCtx = document.getElementById('methodChart').getContext('2d');
    new Chart(methodCtx, {
        type: 'doughnut',
        data: {
            labels: ['PDA扫码', '手动录入', 'Excel导入'],
            datasets: [{
                data: [
                    <?php echo getMethodCount($differences, 'pda_scan'); ?>,
                    <?php echo getMethodCount($differences, 'manual_input'); ?>,
                    <?php echo getMethodCount($differences, 'excel_import'); ?>
                ],
                backgroundColor: ['#5bc0de', '#777', '#f0ad4e']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
});
</script>

<?php
/**
 * 获取原片类型名称
 */
function getGlassTypeName($glassTypeId) {
    $glass = fetchRow("SELECT short_name FROM glass_types WHERE id = ?", [$glassTypeId]);
    return $glass ? $glass['short_name'] : '未知类型';
}

/**
 * 获取盘盈数量
 */
function getProfitCount($differences) {
    $count = 0;
    foreach ($differences as $diff) {
        if ($diff['difference'] > 0) $count++;
    }
    return $count;
}

/**
 * 获取盘亏数量
 */
function getLossCount($differences) {
    $count = 0;
    foreach ($differences as $diff) {
        if ($diff['difference'] < 0) $count++;
    }
    return $count;
}

/**
 * 获取盘点方式数量
 */
function getMethodCount($differences, $method) {
    $count = 0;
    foreach ($differences as $diff) {
        if ($diff['check_method'] === $method) $count++;
    }
    return $count;
}
?>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    
    .panel {
        page-break-inside: avoid;
    }
    
    .table {
        font-size: 12px;
    }
}
</style>

<?php include 'footer.php'; ?>