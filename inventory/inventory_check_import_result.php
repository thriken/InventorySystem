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
                    <i class="glyphicon glyphicon-ok"></i> 导入完成
                </h2>
                <div class="pull-right">
                    <a href="inventory_check.php?action=view&id=<?php echo $taskId; ?>" class="btn btn-primary">
                        <i class="glyphicon glyphicon-arrow-left"></i> 返回任务
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 导入结果统计 -->
    <div class="row">
        <div class="col-md-3">
            <div class="panel panel-green">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="glyphicon glyphicon-ok-circle fa-5x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo $successCount; ?></div>
                            <div>成功导入</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-red">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="glyphicon glyphicon-remove-circle fa-5x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo count($errors); ?></div>
                            <div>导入失败</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-yellow">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="glyphicon glyphicon-warning-sign fa-5x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo count($duplicates); ?></div>
                            <div>重复数据</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-blue">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <i class="glyphicon glyphicon-home fa-5x"></i>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo $rackUpdates; ?></div>
                            <div>库位更新</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 总体统计 -->
    <div class="panel panel-info">
        <div class="panel-heading">
            <h4>导入统计</h4>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>总记录数：</strong><?php echo $totalCount; ?>
                </div>
                <div class="col-md-3">
                    <strong>成功率：</strong>
                    <?php 
                    $successRate = $totalCount > 0 ? round(($successCount / $totalCount) * 100, 2) : 0;
                    echo $successRate . '%';
                    ?>
                </div>
                <div class="col-md-3">
                    <strong>库位更新数：</strong><?php echo $rackUpdates; ?>
                </div>
                <div class="col-md-3">
                    <strong>导入时间：</strong><?php echo date('Y-m-d H:i:s'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 错误信息 -->
    <?php if (!empty($errors)): ?>
    <div class="panel panel-danger">
        <div class="panel-heading">
            <h4><i class="glyphicon glyphicon-exclamation-sign"></i> 错误信息</h4>
        </div>
        <div class="panel-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>序号</th>
                            <th>错误信息</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($errors as $index => $error): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($error); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 重复数据 -->
    <?php if (!empty($duplicates)): ?>
    <div class="panel panel-warning">
        <div class="panel-heading">
            <h4><i class="glyphicon glyphicon-warning-sign"></i> 重复数据（已盘点）</h4>
        </div>
        <div class="panel-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>序号</th>
                            <th>包号</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($duplicates as $index => $packageCode): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><code><?php echo htmlspecialchars($packageCode); ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 操作按钮 -->
    <div class="row">
        <div class="col-md-12 text-center">
            <a href="inventory_check.php?action=view&id=<?php echo $taskId; ?>" class="btn btn-primary btn-lg">
                <i class="glyphicon glyphicon-eye-open"></i> 查看盘点结果
            </a>
            <a href="inventory_check.php" class="btn btn-default btn-lg">
                <i class="glyphicon glyphicon-list"></i> 返回任务列表
            </a>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>