<?php include 'header.php'; ?>

<div class="container-fluid">
    <!-- 页面头部 -->
    <div class="row">
        <div class="col-md-12">
            <div class="page-header clearfix">
                <h2 class="pull-left">
                    <i class="glyphicon glyphicon-plus"></i> 创建盘点任务
                </h2>
                <div class="pull-right">
                    <a href="inventory_check.php" class="btn btn-default">
                        <i class="glyphicon glyphicon-arrow-left"></i> 返回列表
                    </a>
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

    <div class="row">
        <div class="col-md-8">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h4>基本信息</h4>
                </div>
                <div class="panel-body">
                    <form method="POST" action="inventory_check.php?action=create" class="form-horizontal">
                        <div class="form-group">
                            <label class="col-sm-3 control-label">任务名称 <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <input type="text" name="task_name" class="form-control" required
                                       placeholder="例如：2025年11月全盘盘点" maxlength="100">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">盘点基地 <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select name="base_id" class="form-control" required>
                                    <option value="">请选择基地</option>
                                    <?php
                                    $user = getCurrentUser();
                                    $bases = fetchAll("SELECT id, name, code FROM bases WHERE id = ? ORDER BY name", [$user['base_id']]);
                                    
                                    foreach ($bases as $base):
                                    ?>
                                        <option value="<?php echo $base['id']; ?>">
                                            <?php echo htmlspecialchars($base['name']); ?> (<?php echo htmlspecialchars($base['code']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">盘点类型 <span class="text-danger">*</span></label>
                            <div class="col-sm-9">
                                <select name="task_type" class="form-control" required id="taskType">
                                    <option value="">请选择盘点类型</option>
                                    <option value="full">全盘 - 盘点所有库存</option>
                                    <option value="partial">部分盘点 - 指定范围盘点</option>
                                    <option value="random">抽盘 - 随机选择部分包</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">盘点说明</label>
                            <div class="col-sm-9">
                                <textarea name="description" class="form-control" rows="3" 
                                          placeholder="请输入盘点说明或注意事项（选填）"></textarea>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="col-sm-offset-3 col-sm-9">
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <i class="glyphicon glyphicon-ok"></i> 创建任务
                                </button>
                                <button type="reset" class="btn btn-default">
                                    <i class="glyphicon glyphicon-refresh"></i> 重置
                                </button>
                                <a href="inventory_check.php" class="btn btn-link">
                                    取消
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- 盘点类型说明 -->
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h4>盘点类型说明</h4>
                </div>
                <div class="panel-body">
                    <dl>
                        <dt><strong>全盘</strong></dt>
                        <dd>盘点指定基地内的所有库存包，适合月度或季度盘点。</dd>
                        
                        <dt><strong>部分盘点</strong></dt>
                        <dd>随机选择30%的包进行盘点，适合日常抽查。</dd>
                        
                        <dt><strong>抽盘</strong></dt>
                        <dd>随机选择10%的包进行盘点，适合快速抽查。</dd>
                    </dl>
                </div>
            </div>

            <!-- 操作提示 -->
            <div class="panel panel-warning">
                <div class="panel-heading">
                    <h4>操作提示</h4>
                </div>
                <div class="panel-body">
                    <ul>
                        <li>盘点任务创建后处于"已创建"状态</li>
                        <li>需要点击"开始盘点"后才能进行实际盘点操作</li>
                        <li>支持PDA扫码和手动录入两种方式</li>
                        <li>可以导入Excel文件批量录入盘点数据</li>
                        <li>任务完成后可生成详细的盘点报告</li>
                    </ul>
                </div>
            </div>

            <!-- 预估包数 -->
            <div class="panel panel-default" id="packageEstimate" style="display: none;">
                <div class="panel-heading">
                    <h4>预估盘点包数</h4>
                </div>
                <div class="panel-body text-center">
                    <h2 id="estimatedCount">-</h2>
                    <p class="text-muted">基于当前库存数据计算</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 监听盘点类型变化，显示预估包数
    $('#taskType').change(function() {
        var taskType = $(this).val();
        if (!taskType) {
            $('#packageEstimate').hide();
            return;
        }
        
        var baseId = $('select[name="base_id"]').val();
        if (!baseId) {
            $('#packageEstimate').hide();
            return;
        }
        
        // 获取预估包数
        $.ajax({
            url: 'inventory_check_ajax.php',
            method: 'GET',
            data: {
                action: 'estimate_packages',
                base_id: baseId,
                task_type: taskType
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#estimatedCount').text(response.count);
                    $('#packageEstimate').show();
                } else {
                    $('#packageEstimate').hide();
                }
            },
            error: function() {
                $('#packageEstimate').hide();
            }
        });
    });
    
    // 监听基地变化，重新计算预估
    $('select[name="base_id"]').change(function() {
        $('#taskType').change();
    });
    
    // 表单提交前确认
    $('form').submit(function(e) {
        var taskName = $('input[name="task_name"]').val();
        var baseId = $('select[name="base_id"]').val();
        var taskType = $('#taskType').val();
        
        if (!taskName || !baseId || !taskType) {
            e.preventDefault();
            alert('请填写所有必填字段！');
            return false;
        }
        
        var confirmMsg = '确定要创建盘点任务吗？\n\n';
        confirmMsg += '任务名称：' + taskName + '\n';
        confirmMsg += '盘点类型：' + $('#taskType option:selected').text() + '\n';
        
        if (!confirm(confirmMsg)) {
            e.preventDefault();
            return false;
        }
    });
});
</script>

<?php include 'footer.php'; ?>