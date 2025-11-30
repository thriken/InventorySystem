<?php include 'header.php'; ?>

<div class="container-fluid">
    <!-- 页面头部 -->
    <div class="row">
        <div class="col-md-12">
            <div class="page-header clearfix">
                <h2 class="pull-left">
                    <i class="glyphicon glyphicon-list"></i> 盘点任务详情
                </h2>
                <div class="pull-right no-print">
                    <a href="inventory_check.php" class="btn btn-default">
                        <i class="glyphicon glyphicon-arrow-left"></i> 返回列表
                    </a>
                    
                    <?php if ($task['status'] === 'in_progress'): ?>
                        <a href="inventory_check_import.php?task_id=<?php echo $task['id']; ?>" class="btn btn-warning">
                            <i class="glyphicon glyphicon-import"></i> Excel导入
                        </a>
                        <button class="btn btn-success" onclick="showManualInputDialog()">
                            <i class="glyphicon glyphicon-edit"></i> 手动录入
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($task['status'] === 'created'): ?>
                        <a href="inventory_check.php?action=start&id=<?php echo $task['id']; ?>" 
                           class="btn btn-primary" onclick="return confirm('确定要开始这个盘点任务吗？')">
                            <i class="glyphicon glyphicon-play"></i> 开始盘点
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($task['status'] === 'in_progress' && $task['checked_packages'] > 0): ?>
                        <button class="btn btn-danger" onclick="showCompleteDialog()">
                            <i class="glyphicon glyphicon-ok"></i> 完成盘点
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 任务状态信息 -->
    <div class="row">
        <div class="col-md-3">
            <div class="panel panel-<?php echo getStatusColor($task['status']); ?>">
                <div class="panel-heading text-center">
                    <h4>任务状态</h4>
                </div>
                <div class="panel-body text-center">
                    <h3><?php echo getStatusText($task['status']); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-info">
                <div class="panel-heading text-center">
                    <h4>盘点进度</h4>
                </div>
                <div class="panel-body text-center">
                    <h3><?php echo $task['completion_rate']; ?>%</h3>
                    <small><?php echo $task['checked_packages']; ?>/<?php echo $task['total_packages']; ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-warning">
                <div class="panel-heading text-center">
                    <h4>差异数量</h4>
                </div>
                <div class="panel-body text-center">
                    <h3><?php echo $task['difference_count']; ?></h3>
                    <small>已发现差异</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-default">
                <div class="panel-heading text-center">
                    <h4>盘点类型</h4>
                </div>
                <div class="panel-body text-center">
                    <h3><?php echo getTaskTypeText($task['task_type']); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- 任务基本信息 -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h4>任务信息</h4>
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
                    <strong>创建人：</strong><?php echo htmlspecialchars($task['created_by_name']); ?>
                </div>
                <div class="col-md-3">
                    <strong>创建时间：</strong><?php echo date('Y-m-d H:i', strtotime($task['created_at'])); ?>
                </div>
            </div>
            <?php if ($task['started_at']): ?>
            <div class="row">
                <div class="col-md-3">
                    <strong>开始时间：</strong><?php echo date('Y-m-d H:i', strtotime($task['started_at'])); ?>
                </div>
                <?php if ($task['duration']): ?>
                <div class="col-md-3">
                    <strong>已用时间：</strong><?php echo $task['duration']; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($task['description']): ?>
            <div class="row">
                <div class="col-md-12">
                    <strong>说明：</strong><?php echo htmlspecialchars($task['description']); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 盘点期间出库处理统计 -->
    <?php 
    $rollbackCount = fetchColumn("
        SELECT COUNT(*) FROM inventory_check_cache 
        WHERE task_id = ? AND check_method = 'auto_rollback'
    ", [$task['id']]);
    
    if ($rollbackCount > 0): ?>
    <div class="panel panel-warning no-print">
        <div class="panel-heading">
            <h4><i class="glyphicon glyphicon-time"></i> 盘点期间处理</h4>
        </div>
        <div class="panel-body">
            <div class="alert alert-info">
                <i class="glyphicon glyphicon-info-sign"></i>
                <strong>已检测到<?php echo $rollbackCount; ?>个盘点期间的出库记录</strong><br>
                <small>这些记录已自动回滚到盘点数据中，避免误判为盘亏。</small>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 快速操作（仅进行中状态） -->
    <?php if ($task['status'] === 'in_progress'): ?>
    <div class="panel panel-success no-print">
        <div class="panel-heading">
            <h4>快速操作</h4>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-4">
                    <button class="btn btn-primary btn-block" onclick="showScanDialog()">
                        <i class="glyphicon glyphicon-barcode"></i> 扫码盘点
                    </button>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-warning btn-block" onclick="showManualInputDialog()">
                        <i class="glyphicon glyphicon-edit"></i> 手动录入
                    </button>
                </div>
                <div class="col-md-4">
                    <a href="inventory_check_import.php?task_id=<?php echo $task['id']; ?>" class="btn btn-info btn-block">
                        <i class="glyphicon glyphicon-import"></i> Excel导入
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 盘点明细列表 -->
    <div class="panel panel-primary">
        <div class="panel-heading">
            <h4>盘点明细</h4>
            <div class="pull-right no-print">
                <button class="btn btn-xs btn-default" onclick="toggleFilter()">
                    <i class="glyphicon glyphicon-filter"></i> 筛选
                </button>
                <button class="btn btn-xs btn-default" onclick="refreshData()">
                    <i class="glyphicon glyphicon-refresh"></i> 刷新
                </button>
            </div>
        </div>
        <div class="panel-body">
            <!-- 筛选表单 -->
            <div id="filterPanel" class="well" style="display: none;">
                <div class="row">
                    <div class="col-md-3">
                        <select class="form-control" id="statusFilter">
                            <option value="">所有状态</option>
                            <option value="0">未盘点</option>
                            <option value="1">已盘点</option>
                            <option value="2">有差异</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-control" id="methodFilter">
                            <option value="">所有方式</option>
                            <option value="pda_scan">PDA扫码</option>
                            <option value="manual_input">手动录入</option>
                            <option value="excel_import">Excel导入</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control" id="searchInput" placeholder="搜索包号...">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary" onclick="applyFilter()">
                            <i class="glyphicon glyphicon-search"></i> 搜索
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-bordered table-hover" id="detailsTable">
                    <thead>
                        <tr>
                            <th>序号</th>
                            <th>包号</th>
                            <th>原片名称</th>
                            <th>系统数量</th>
                            <th>盘点数量</th>
                            <th>差异</th>
                            <th>盘点方式</th>
                            <th>操作员</th>
                            <th>盘点时间</th>
                            <th class="no-print">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rowIndex = 0;
                        foreach ($details as $detail):
                            $rowIndex++;
                            $difference = $detail['check_quantity'] - $detail['system_quantity'];
                            $status = $detail['check_quantity'] > 0 ? 'checked' : 'pending';
                            if ($difference != 0) $status = 'difference';
                        ?>
                        <tr data-status="<?php echo $status; ?>" data-method="<?php echo $detail['check_method']; ?>" data-package="<?php echo htmlspecialchars($detail['package_code']); ?>">
                            <td><?php echo $rowIndex; ?></td>
                            <td><code><?php echo htmlspecialchars($detail['package_code']); ?></code></td>
                            <td><?php echo htmlspecialchars($detail['glass_name']); ?></td>
                            <td class="text-center">
                                <span class="badge"><?php echo $detail['system_quantity']; ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($detail['check_quantity'] > 0): ?>
                                    <span class="badge badge-primary"><?php echo $detail['check_quantity']; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
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
                            <td>
                                <?php
                                switch ($detail['check_method']) {
                                    case 'pda_scan':
                                        echo '<span class="label label-info">PDA扫码</span>';
                                        break;
                                    case 'manual_input':
                                        echo '<span class="label label-default">手动录入</span>';
                                        break;
                                    case 'excel_import':
                                        echo '<span class="label label-warning">Excel导入</span>';
                                        break;
                                    default:
                                        echo '<span class="text-muted">-</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($detail['operator_name'] ?? '-'); ?></td>
                            <td><?php echo $detail['check_time'] ? date('m-d H:i', strtotime($detail['check_time'])) : '-'; ?></td>
                            <td class="no-print">
                                <?php if ($task['status'] === 'in_progress' && $detail['check_quantity'] == 0): ?>
                                    <button class="btn btn-xs btn-primary" onclick="editPackage('<?php echo $detail['package_code']; ?>', <?php echo $detail['system_quantity']; ?>)">
                                        <i class="glyphicon glyphicon-edit"></i> 盘点
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-xs btn-default" onclick="viewDetail('<?php echo $detail['package_code']; ?>')">
                                        <i class="glyphicon glyphicon-eye-open"></i> 查看
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (empty($details)): ?>
                <div class="text-center text-muted" style="padding: 50px;">
                    <i class="glyphicon glyphicon-inbox" style="font-size: 48px;"></i>
                    <h4>暂无盘点数据</h4>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 手动录入对话框 -->
<div class="modal fade" id="manualInputModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">手动录入盘点数据</h4>
            </div>
            <div class="modal-body">
                <form id="manualInputForm" class="form-horizontal">
                    <input type="hidden" id="taskId" value="<?php echo $task['id']; ?>">
                    <div class="form-group">
                        <label class="col-sm-3 control-label">包号 <span class="text-danger">*</span></label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" id="packageCode" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">盘点数量 <span class="text-danger">*</span></label>
                        <div class="col-sm-9">
                            <input type="number" class="form-control" id="checkQuantity" min="0" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">系统数量</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" id="systemQuantity" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">原片名称</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" id="glassName" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">备注</label>
                        <div class="col-sm-9">
                            <textarea class="form-control" id="notes" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" onclick="submitManualInput()">提交</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 包号自动查询
    $('#packageCode').on('blur', function() {
        var packageCode = $(this).val();
        if (!packageCode) return;
        
        $.ajax({
            url: '../api/inventory_check.php',
            method: 'GET',
            data: {
                action: 'get_package_info',
                package_code: packageCode
            },
            dataType: 'json',
            success: function(response) {
                if (response.code === 200) {
                    $('#systemQuantity').val(response.data.pieces);
                    $('#glassName').val(response.data.glass_name);
                    $('#checkQuantity').focus();
                } else {
                    $('#systemQuantity').val('');
                    $('#glassName').val('');
                    alert('包不存在或无权限访问：' + response.message);
                }
            },
            error: function() {
                $('#systemQuantity').val('');
                $('#glassName').val('');
                alert('查询失败，请检查网络连接');
            }
        });
    });
});

function showManualInputDialog(packageCode, systemQuantity) {
    $('#manualInputModal').modal('show');
    if (packageCode) {
        $('#packageCode').val(packageCode).prop('readonly', true);
        $('#systemQuantity').val(systemQuantity);
        $('#checkQuantity').val(systemQuantity);
        $('#checkQuantity').focus();
    } else {
        $('#packageCode').val('').prop('readonly', false);
        $('#systemQuantity').val('');
        $('#glassName').val('');
        $('#checkQuantity').val('');
        $('#notes').val('');
        $('#packageCode').focus();
    }
}

function submitManualInput() {
    var taskId = $('#taskId').val();
    var packageCode = $('#packageCode').val();
    var checkQuantity = $('#checkQuantity').val();
    var notes = $('#notes').val();
    
    if (!packageCode || !checkQuantity) {
        alert('请填写包号和盘点数量');
        return;
    }
    
    $.ajax({
        url: '../api/inventory_check.php',
        method: 'POST',
        data: {
            action: 'submit_check',
            task_id: taskId,
            package_code: packageCode,
            check_quantity: checkQuantity,
            notes: notes
        },
        dataType: 'json',
        success: function(response) {
            if (response.code === 200) {
                alert('盘点数据提交成功！');
                location.reload();
            } else {
                alert('提交失败：' + response.message);
            }
        },
        error: function() {
            alert('提交失败，请检查网络连接');
        }
    });
}

function editPackage(packageCode, systemQuantity) {
    showManualInputDialog(packageCode, systemQuantity);
}

function toggleFilter() {
    $('#filterPanel').slideToggle();
}

function applyFilter() {
    var statusFilter = $('#statusFilter').val();
    var methodFilter = $('#methodFilter').val();
    var searchInput = $('#searchInput').val().toLowerCase();
    
    $('#detailsTable tbody tr').each(function() {
        var row = $(this);
        var show = true;
        
        if (statusFilter) {
            if (statusFilter === '0' && !row.hasClass('pending')) show = false;
            if (statusFilter === '1' && !row.hasClass('checked')) show = false;
            if (statusFilter === '2' && !row.hasClass('difference')) show = false;
        }
        
        if (methodFilter && row.data('method') !== methodFilter) show = false;
        
        if (searchInput && row.data('package').indexOf(searchInput) === -1) show = false;
        
        row.toggle(show);
    });
}

function refreshData() {
    location.reload();
}

function confirmComplete() {
    var message = '确定要完成这个盘点任务吗？\n\n';
    message += '完成后将生成盘点报告，无法再修改盘点数据。\n';
    message += '差异数量：' + <?php echo $task['difference_count']; ?> + '\n';
    
    return confirm(message);
}

function viewDetail(packageCode) {
    alert('查看包详情：' + packageCode);
    // 这里可以打开详情对话框或跳转到详情页面
}

function showCompleteDialog() {
    $('#completeTaskModal').modal('show');
}

function submitCompleteTask() {
    var adjustInventory = $('input[name="adjust_inventory"]:checked').val();
    var notes = $('#complete_notes').val();
    
    // 确认信息
    var confirmMessage = '确定要完成盘点任务吗？\n\n';
    if (adjustInventory === '1') {
        confirmMessage += '√ 将根据盘点结果自动调整库存数量\n';
        confirmMessage += '√ 盘盈将增加库存，盘亏将减少库存\n\n';
        confirmMessage += '此操作不可撤销，请再次确认盘点数据！';
    } else {
        confirmMessage += '√ 仅生成盘点报告，不调整库存\n\n';
        confirmMessage += '后续可以通过其他方式调整库存差异。';
    }
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    // 创建表单并提交
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'inventory_check.php?action=complete&id=<?php echo $task['id']; ?>';
    
    // 添加隐藏字段
    var autoAdjustField = document.createElement('input');
    autoAdjustField.type = 'hidden';
    autoAdjustField.name = 'auto_adjust';
    autoAdjustField.value = adjustInventory === '1' ? '1' : '0';
    form.appendChild(autoAdjustField);
    
    var notesField = document.createElement('input');
    notesField.type = 'hidden';
    notesField.name = 'complete_notes';
    notesField.value = notes;
    form.appendChild(notesField);
    
    // 提交表单
    document.body.appendChild(form);
    form.submit();
}

function showConfirmDialog(adjustInventory, notes, rollbackCount) {
    var confirmMessage = '确定要完成盘点任务吗？\n\n';
    confirmMessage += '应盘包数：' + <?php echo $task['total_packages']; ?> + '\n';
    confirmMessage += '已盘包数：' + <?php echo $task['checked_packages']; ?> + '\n';
    confirmMessage += '差异数量：' + <?php echo $task['difference_count']; ?> + '\n\n';
    
    if (rollbackCount > 0) {
        confirmMessage += '⚠️ 已检测到' + rollbackCount + '个盘点期间的出库记录\n';
        confirmMessage += '✅ 这些记录已自动回滚到盘点数据中\n\n';
    }
    
    if (adjustInventory === '1') {
        confirmMessage += '√ 将根据盘点结果自动调整库存数量\n';
        confirmMessage += '√ 盘盈增加库存，盘亏减少库存\n\n';
        confirmMessage += '此操作不可撤销，请再次确认盘点数据！';
    } else {
        confirmMessage += '√ 仅生成盘点报告，不调整库存\n\n';
        confirmMessage += '后续可以通过其他方式调整库存差异。';
    }
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    // 创建表单并提交
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'inventory_check.php?action=complete&id=<?php echo $task['id']; ?>';
    
    // 添加隐藏字段
    var autoAdjustField = document.createElement('input');
    autoAdjustField.type = 'hidden';
    autoAdjustField.name = 'auto_adjust';
    autoAdjustField.value = adjustInventory === '1' ? '1' : '0';
    form.appendChild(autoAdjustField);
    
    var notesField = document.createElement('input');
    notesField.type = 'hidden';
    notesField.name = 'complete_notes';
    notesField.value = notes;
    form.appendChild(notesField);
    
    // 提交表单
    document.body.appendChild(form);
    form.submit();
}
</script>

<?php
/**
 * 获取状态颜色
 */
function getStatusColor($status) {
    $colors = [
        'created' => 'default',
        'in_progress' => 'info',
        'completed' => 'success',
        'cancelled' => 'danger'
    ];
    return $colors[$status] ?? 'default';
}

/**
 * 获取状态文本
 */
function getStatusText($status) {
    $texts = [
        'created' => '已创建',
        'in_progress' => '进行中',
        'completed' => '已完成',
        'cancelled' => '已取消'
    ];
    return $texts[$status] ?? $status;
}

/**
 * 获取任务类型文本
 */
function getTaskTypeText($type) {
    $types = [
        'full' => '全盘',
        'partial' => '部分盘点',
        'random' => '抽盘'
    ];
    return $types[$type] ?? $type;
}
?>

<style>
.difference-row {
    background-color: #fcf8e3 !important;
}
.pending-row {
    color: #999;
}
</style>

<!-- 完成盘点确认对话框 -->
<div class="modal fade" id="completeTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">完成盘点确认</h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <h5><i class="glyphicon glyphicon-info-sign"></i> 盘点统计</h5>
                    <ul class="list-unstyled">
                        <li><strong>应盘包数：</strong><?php echo $task['total_packages']; ?></li>
                        <li><strong>已盘包数：</strong><?php echo $task['checked_packages']; ?></li>
                        <li><strong>差异数量：</strong><?php echo $task['difference_count']; ?></li>
                        <li><strong>完成率：</strong><?php echo $task['completion_rate']; ?>%</li>
                    </ul>
                </div>
                
                <div class="panel panel-warning">
                    <div class="panel-heading">
                        <h5><i class="glyphicon glyphicon-cog"></i> 库存调整选项</h5>
                    </div>
                    <div class="panel-body">
                        <div class="radio">
                            <label>
                                <input type="radio" name="adjust_inventory" value="0" checked>
                                <strong>仅生成盘点报告</strong><br>
                                <small class="text-muted">生成盘点报告和统计，但不修改实际库存数量。适合审计盘点。</small>
                            </label>
                        </div>
                        <div class="radio">
                            <label>
                                <input type="radio" name="adjust_inventory" value="1">
                                <strong>自动调整库存</strong><br>
                                <small class="text-muted">根据盘点结果自动调整库存数量。盘盈增加库存，盘亏减少库存。</small>
                            </label>
                        </div>
                        <div class="alert alert-warning" style="margin-top: 15px;">
                            <i class="glyphicon glyphicon-warning-sign"></i>
                            <strong>注意：</strong>库存调整将立即生效，请确保盘点数据准确无误。双人盘点已确认的情况下推荐选择此项。
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="complete_notes">完成备注</label>
                    <textarea class="form-control" id="complete_notes" rows="3" placeholder="请输入盘点完成备注（选填）"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="glyphicon glyphicon-remove"></i> 取消
                </button>
                <button type="button" class="btn btn-danger" onclick="submitCompleteTask()">
                    <i class="glyphicon glyphicon-ok"></i> 确认完成
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>