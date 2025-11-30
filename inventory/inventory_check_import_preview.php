<?php
require_once '../includes/inventory_check_auth.php';
requireInventoryCheckCreatePermission();
include 'header.php'; ?>

<div class="container-fluid">
    <!-- 页面头部 -->
    <div class="row">
        <div class="col-md-12">
            <div class="page-header clearfix">
                <h2 class="pull-left">
                    <i class="glyphicon glyphicon-eye-open"></i> 导入数据预览
                </h2>
                <div class="pull-right">
                    <a href="inventory_check.php?action=view&id=<?php echo $taskId; ?>" class="btn btn-default">
                        <i class="glyphicon glyphicon-arrow-left"></i> 返回任务
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 文件信息 -->
    <div class="panel panel-info">
        <div class="panel-heading">
            <h4>文件信息</h4>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>文件名：</strong><?php echo htmlspecialchars($fileName); ?>
                </div>
                <div class="col-md-3">
                    <strong>总行数：</strong><?php echo count($data); ?>
                </div>
                <div class="col-md-3">
                    <strong>预览行数：</strong><?php echo count($previewData); ?>
                </div>
                <div class="col-md-3">
                    <strong>任务名称：</strong><?php echo htmlspecialchars($task['task_name']); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 数据预览 -->
    <div class="panel panel-primary">
        <div class="panel-heading">
            <h4>数据预览（前20行）</h4>
        </div>
        <div class="panel-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>序号</th>
                            <th>包号</th>
                            <th>原片名称</th>
                            <th>系统数量</th>
                            <th>盘点数量</th>
                            <th>差异</th>
                            <th>备注</th>
                            <th>状态</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rowIndex = 0;
                        foreach ($previewData as $row):
                            $rowIndex++;
                            $packageCode = trim($row['package_code'] ?? '');
                            $checkQuantity = intval($row['check_quantity'] ?? 0);
                            $notes = trim($row['notes'] ?? '');
                            
                            // 获取包信息
                            $package = fetchRow("SELECT p.pieces, g.short_name 
                                               FROM glass_packages p 
                                               JOIN glass_types g ON p.glass_type_id = g.id 
                                               WHERE p.package_code = ? AND p.status = 'in_storage'", 
                                               [$packageCode]);
                            
                            $systemQuantity = $package ? $package['pieces'] : 0;
                            $glassName = $package ? $package['short_name'] : '未知';
                            $difference = $checkQuantity - $systemQuantity;
                            
                            // 检查是否已盘点
                            $alreadyChecked = fetchRow("SELECT id FROM inventory_check_cache 
                                                      WHERE task_id = ? AND package_code = ? AND check_quantity > 0", 
                                                      [$taskId, $packageCode]);
                            
                            $statusClass = 'default';
                            $statusText = '待导入';
                            
                            if (!$package) {
                                $statusClass = 'danger';
                                $statusText = '包不存在';
                            } elseif ($alreadyChecked) {
                                $statusClass = 'warning';
                                $statusText = '已盘点';
                            } elseif ($checkQuantity <= 0) {
                                $statusClass = 'danger';
                                $statusText = '数量无效';
                            }
                        ?>
                        <tr>
                            <td><?php echo $rowIndex; ?></td>
                            <td><code><?php echo htmlspecialchars($packageCode); ?></code></td>
                            <td><?php echo htmlspecialchars($glassName); ?></td>
                            <td class="text-center">
                                <?php if ($systemQuantity > 0): ?>
                                    <span class="badge"><?php echo $systemQuantity; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?php echo $checkQuantity > 0 ? 'badge-primary' : 'badge-danger'; ?>">
                                    <?php echo $checkQuantity; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($difference != 0): ?>
                                    <span class="label <?php echo $difference > 0 ? 'label-success' : 'label-danger'; ?>">
                                        <?php echo ($difference > 0 ? '+' : '') . $difference; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="label label-default">0</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($notes); ?></td>
                            <td>
                                <span class="label label-<?php echo $statusClass; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (count($data) > 20): ?>
                <div class="alert alert-info">
                    <i class="glyphicon glyphicon-info-sign"></i>
                    仅显示前20行数据，总共有<?php echo count($data); ?>行数据需要导入。
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 确认表单 -->
    <div class="panel panel-warning">
        <div class="panel-heading">
            <h4>确认导入</h4>
        </div>
        <div class="panel-body">
            <form method="POST" action="inventory_check_import.php" class="form-horizontal">
                <input type="hidden" name="action" value="confirm">
                <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($filePath); ?>">
                <input type="hidden" name="file_name" value="<?php echo htmlspecialchars($fileName); ?>">
                
                <div class="form-group">
                    <div class="col-sm-12">
                        <div class="alert alert-warning">
                            <strong>请确认以下信息：</strong>
                            <ul style="margin-top: 10px; margin-bottom: 0;">
                                <li>本次将导入 <?php echo count($data); ?> 行数据</li>
                                <li>已盘点的包将被跳过</li>
                                <li>不存在的包将被忽略</li>
                                <li>导入后可在任务详情中查看结果</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="col-sm-offset-2 col-sm-10">
                        <button type="submit" class="btn btn-success" onclick="return confirmImport();">
                            <i class="glyphicon glyphicon-ok"></i> 确认导入
                        </button>
                        <button type="button" class="btn btn-default" onclick="goBack();">
                            <i class="glyphicon glyphicon-arrow-left"></i> 返回修改
                        </button>
                        <a href="inventory_check.php?action=view&id=<?php echo $taskId; ?>" class="btn btn-link">
                            取消导入
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmImport() {
    return confirm('确定要导入这些盘点数据吗？\n\n注意：已盘点的包将被跳过，无效数据将被忽略。');
}

function goBack() {
    history.back();
}

$(document).ready(function() {
    // 为状态标签添加颜色提示
    $('.label-danger').parent().parent().addClass('danger-row');
    $('.label-warning').parent().parent().addClass('warning-row');
});
</script>

<style>
.danger-row {
    background-color: #f2dede !important;
}
.warning-row {
    background-color: #fcf8e3 !important;
}
</style>

<?php include 'footer.php'; ?>