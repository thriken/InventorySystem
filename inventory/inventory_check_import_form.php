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
                    <i class="glyphicon glyphicon-import"></i> Excel导入盘点数据
                </h2>
                <div class="pull-right">
                    <a href="inventory_check.php?action=view&id=<?php echo $taskId; ?>" class="btn btn-default">
                        <i class="glyphicon glyphicon-arrow-left"></i> 返回任务
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 任务信息 -->
    <div class="panel panel-info">
        <div class="panel-heading">
            <h4>任务信息</h4>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>任务名称：</strong><?php echo htmlspecialchars($task['task_name']); ?>
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
                    <strong>总包数：</strong><?php echo $task['total_packages']; ?>
                </div>
                <div class="col-md-3">
                    <strong>已盘数量：</strong><?php echo $task['checked_packages']; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <strong>错误：</strong><?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- 上传表单 -->
    <div class="row">
        <div class="col-md-8">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h4>上传Excel文件</h4>
                </div>
                <div class="panel-body">
                    <form method="POST" enctype="multipart/form-data" class="form-horizontal">
                        <input type="hidden" name="action" value="upload">
                        
                        <div class="form-group">
                            <label class="col-sm-3 control-label">选择文件 <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="file" name="excel_file" class="form-control" 
                                       accept=".xlsx,.xls,.csv" required>
                                <p class="help-block">支持Excel文件(.xlsx, .xls)和CSV文件，最大5MB</p>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="col-sm-offset-3 col-sm-9">
                                <button type="submit" class="btn btn-primary">
                                    <i class="glyphicon glyphicon-upload"></i> 上传并预览
                                </button>
                                <a href="inventory_check.php?action=view&id=<?php echo $taskId; ?>" class="btn btn-link">
                                    取消
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- 文件格式说明 -->
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h4>文件格式要求</h4>
                </div>
                <div class="panel-body">
                    <p><strong>Excel文件格式：</strong></p>
                    <ol>
                        <li>第一行为标题行</li>
                        <li>包含"包号"和"盘点数量"列</li>
                        <li>可选择包含"备注"列</li>
                    </ol>
                    
                    <p><strong>示例格式：</strong></p>
                    <table class="table table-bordered table-condensed">
                        <thead>
                            <tr>
                                <th>包号</th>
                                <th>盘点数量</th>
                                <th>备注（可选）</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>L250831002</td>
                                <td>48</td>
                                <td>包装轻微破损</td>
                            </tr>
                            <tr>
                                <td>L250831003</td>
                                <td>50</td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 操作提示 -->
            <div class="panel panel-warning">
                <div class="panel-heading">
                    <h4>操作提示</h4>
                </div>
                <div class="panel-body">
                    <ul>
                        <li>请确保文件中的包号存在于当前盘点任务中</li>
                        <li>已盘点的包不会被重复导入</li>
                        <li>盘点数量必须为正整数</li>
                        <li>系统会自动计算与系统库存的差异</li>
                        <li>上传后可预览数据，确认无误后导入</li>
                    </ul>
                </div>
            </div>

            <!-- 下载模板 -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4>模板下载</h4>
                </div>
                <div class="panel-body text-center">
                    <p>如果您不确定格式，可以下载标准模板：</p>
                    <a href="inventory_check_template.xlsx" class="btn btn-sm btn-success">
                        <i class="glyphicon glyphicon-download"></i> 下载Excel模板
                    </a>
                    <a href="inventory_check_template.csv" class="btn btn-sm btn-info">
                        <i class="glyphicon glyphicon-download"></i> 下载CSV模板
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 文件类型验证
    $('input[type="file"]').change(function() {
        var file = this.files[0];
        var allowedTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'];
        
        if (!allowedTypes.includes(file.type)) {
            alert('请选择Excel文件(.xlsx, .xls)或CSV文件！');
            this.value = '';
            return;
        }
        
        // 文件大小验证（5MB）
        if (file.size > 5 * 1024 * 1024) {
            alert('文件大小不能超过5MB！');
            this.value = '';
            return;
        }
    });
    
    // 表单提交确认
    $('form').submit(function() {
        var fileInput = $('input[type="file"]');
        if (!fileInput.val()) {
            alert('请选择要上传的文件！');
            return false;
        }
        
        // 显示加载提示
        var submitBtn = $('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<i class="glyphicon glyphicon-hourglass"></i> 正在上传...');
    });
});
</script>

<?php include 'footer.php'; ?>