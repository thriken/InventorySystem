<?php
// 安全检查：确保 $task 变量存在
if (!isset($task) || $task === null) {
    // 如果直接访问此文件，重定向到任务列表
    header('Location: inventory_check_list.php?error=direct_access_not_allowed');
    exit;
}

include 'header.php'; 
?>

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
                        <button class="btn btn-success" onclick="console.log('=== Button Clicked ==='); showManualInputDialog()">
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
            
            <!-- 库位变更统计 -->
            <?php 
            $rackChangeCount = countRackChanges($details);
            if ($rackChangeCount > 0): 
            ?>
            <div class="alert alert-info" style="margin-top: 15px;">
                <i class="glyphicon glyphicon-info-sign"></i>
                <strong>库位变更统计：</strong>已发现 <?php echo $rackChangeCount; ?> 个包的实际库位与系统记录不符，已在盘点时记录了实际位置。
                <?php if (countSyncedRacks($details) > 0): ?>
                <br>其中 <?php echo countSyncedRacks($details); ?> 个已同步更新到系统。
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 盘点期间出库处理统计 -->
    <div class="panel panel-warning no-print" id="rollback-info-panel" style="display: none;">
        <div class="panel-heading">
            <h4><i class="glyphicon glyphicon-time"></i> 盘点期间处理</h4>
        </div>
        <div class="panel-body">
            <div class="alert alert-info">
                <i class="glyphicon glyphicon-info-sign"></i>
                <div id="rollback-message">
                    <strong>正在加载回滚信息...</strong><br>
                </div>
            </div>
        </div>
    </div>

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
                    <button class="btn btn-warning btn-block" onclick="console.log('=== Quick Button Clicked ==='); showManualInputDialog()">
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
                            <th>系统库位</th>
                            <th>盘点库位</th>
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
                            <td class="lead"><span class="label label-primary"><?php echo htmlspecialchars($detail['package_code']); ?></span></td>
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
                            <td class="text-center">
                                <small class="text-muted">
                                    <?php echo $detail['current_rack_code'] ?: getPackageCurrentRack($detail['package_code']); ?>
                                </small>
                            </td>
                            <td class="text-center">
                                <?php if ($detail['rack_id']): ?>
                                    <?php 
                                    $rackCode = getRackCodeById($detail['rack_id']);
                                    $isDifferent = $detail['current_rack_code'] && $rackCode && $detail['current_rack_code'] !== $rackCode;
                                    ?>
                                    <span class="label <?php echo $isDifferent ? 'label-warning' : 'label-info'; ?>">
                                        <?php echo $rackCode; ?>
                                    </span>
                                    <?php if ($isDifferent): ?>
                                        <br><small class="text-warning">库位变更</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
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
                    <input type="hidden" id="currentRackId" value="">
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
                        <label class="col-sm-3 control-label">系统库位</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" id="currentRack" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">
                            盘点库位
                            <small class="text-muted">（如与系统不符可修改）</small>
                        </label>
                        <div class="col-sm-6">
                            <select class="form-control" id="rackSelect">
                                <option value="">请选择库位</option>
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" id="syncRackCheckbox">
                                    同步更新系统库位
                                </label>
                            </div>
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
    console.log('=== Page Ready ===');
    console.log('jQuery loaded:', typeof $ !== 'undefined');
    console.log('Bootstrap loaded:', typeof $.fn.modal !== 'undefined');
    
    // 加载回滚统计信息
    loadRollbackCount();
    
    // 包号自动查询
    $('#packageCode').on('blur', function() {
        var packageCode = $(this).val();
        console.log('=== Package Code Input ===');
        console.log('Package Code entered:', packageCode);
        
        if (!packageCode) {
            console.log('Package Code is empty, skipping query');
            return;
        }
        
        // 通过AJAX调用后端PHP函数
        $.ajax({
            url: 'inventory_check.php',
            method: 'POST',
            data: {
                action: 'get_package_info',
                package_code: packageCode
            },
            dataType: 'json',
            success: function(response) {
                // 调试输出：查看完整的响应数据
                console.log('=== Package Info Response ===');
                console.log('Full response:', response);
                console.log('Success status:', response.success);
                console.log('Data:', response.data);
                
                if (response.success) {
                    // 调试输出：查看具体字段值
                    console.log('=== Package Details ===');
                    console.log('Pieces:', response.data.pieces);
                    console.log('Glass Name:', response.data.glass_name);
                    console.log('Current Rack Code:', response.data.current_rack_code);
                    console.log('Current Rack ID:', response.data.current_rack_id);
                    console.log('Base ID:', response.data.base_id);
                    console.log('Base Name:', response.data.base_name);
                    
                    $('#systemQuantity').val(response.data.pieces);
                    $('#glassName').val(response.data.glass_name);
                    $('#currentRack').val(response.data.current_rack_code || '未分配');
                    $('#currentRackId').val(response.data.current_rack_id || '');
                    
                    // 调试输出：查看字段更新情况
                    console.log('=== Field Updates ===');
                    console.log('System Quantity set to:', $('#systemQuantity').val());
                    console.log('Glass Name set to:', $('#glassName').val());
                    console.log('Current Rack set to:', $('#currentRack').val());
                    console.log('Current Rack ID set to:', $('#currentRackId').val());
                    
                    // 加载可选库位
                    loadRackOptions(response.data.base_id, response.data.current_rack_id);
                    
                    $('#checkQuantity').focus();
                } else {
                    console.log('=== Error Response ===');
                    console.log('Error message:', response.message);
                    $('#systemQuantity').val('');
                    $('#glassName').val('');
                    $('#currentRack').val('');
                    alert('包不存在：' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.log('=== AJAX Error ===');
                console.log('Status:', status);
                console.log('Error:', error);
                console.log('Response Text:', xhr.responseText);
                console.log('Ready State:', xhr.readyState);
                console.log('Status Code:', xhr.status);
                
                $('#systemQuantity').val('');
                $('#glassName').val('');
                alert('查询失败，请检查网络连接');
            }
        });
    });
});

// 加载库位选项
function loadRackOptions(baseId, currentRackId) {
    console.log('=== Load Rack Options ===');
    console.log('Base ID:', baseId);
    console.log('Current Rack ID:', currentRackId);
    
    if (!baseId) {
        console.log('No base ID provided, showing empty options');
        $('#rackSelect').html('<option value="">请选择库位</option>');
        return;
    }
    
    $.ajax({
        url: 'inventory_check.php',
        method: 'POST',
        data: {
            action: 'get_rack_options',
            base_id: baseId
        },
        dataType: 'json',
        success: function(response) {
            console.log('=== Rack Options Response ===');
            console.log('Full response:', response);
            console.log('Success status:', response.success);
            console.log('Data length:', response.data ? response.data.length : 'null');
            
            var options = '<option value="">请选择库位</option>';
            if (response.success && response.data) {
                console.log('Processing rack data:');
                response.data.forEach(function(rack, index) {
                    console.log('Rack ' + index + ':', rack);
                    var selected = rack.id == currentRackId ? 'selected' : '';
                    var areaTypeName = getAreaTypeName(rack.area_type);
                    var optionText = rack.code + ' (' + areaTypeName + ')';
                    console.log('Creating option:', optionText, 'selected:', selected);
                    options += '<option value="' + rack.id + '" ' + selected + '>' + optionText + '</option>';
                });
                console.log('Final options HTML:', options);
            } else {
                console.log('No rack data received or response failed');
                console.log('Error message:', response.message);
            }
            $('#rackSelect').html(options);
            console.log('Rack select updated');
        },
        error: function(xhr, status, error) {
            console.log('=== Rack Options AJAX Error ===');
            console.log('Status:', status);
            console.log('Error:', error);
            console.log('Response Text:', xhr.responseText);
            $('#rackSelect').html('<option value="">加载失败</option>');
        }
    });
}

// 获取区域类型名称
function getAreaTypeName(areaType) {
    var names = {
        'storage': '存储区',
        'temporary': '临时区',
        'processing': '加工区',
        'scrap': '报废区'
    };
    return names[areaType] || areaType;
}

function showManualInputDialog(packageCode, systemQuantity) {
    console.log('=== Show Manual Input Dialog ===');
    console.log('Package Code param:', packageCode);
    console.log('System Quantity param:', systemQuantity);
    
    // 先关闭任何已打开的模态框，防止重复 backdrop
    $('.modal').modal('hide');
    
    // 延迟显示新的模态框，确保 backdrop 被正确清理
    setTimeout(function() {
        console.log('=== Showing Modal ===');
        console.log('jQuery:', typeof $ !== 'undefined');
        console.log('Bootstrap modal function:', typeof $.fn.modal);
        console.log('Modal element exists:', $('#manualInputModal').length > 0);
        
        if (typeof $ === 'undefined') {
            console.error('jQuery not loaded!');
            alert('JavaScript库加载失败，请刷新页面重试');
            return;
        }
        
        if (typeof $.fn.modal === 'undefined') {
            console.error('Bootstrap modal not loaded!');
            alert('Bootstrap模态框功能加载失败，请刷新页面重试');
            return;
        }
        
        if ($('#manualInputModal').length === 0) {
            console.error('Modal element not found!');
            alert('模态框元素不存在，请刷新页面重试');
            return;
        }
        
        console.log('All checks passed, showing modal...');
        $('#manualInputModal').modal('show');
        
        if (packageCode) {
            console.log('=== Package Code Provided ===');
            console.log('Setting package code to readonly:', packageCode);
            console.log('Setting system quantity:', systemQuantity);
            
            $('#packageCode').val(packageCode).prop('readonly', true);
            $('#systemQuantity').val(systemQuantity);
            $('#checkQuantity').val(systemQuantity);
            
            // 自动获取包的详细信息
            setTimeout(function() {
                console.log('=== Auto-fetching Package Info ===');
                $.ajax({
                    url: './inventory_check.php',
                    method: 'POST',
                    data: {
                        action: 'get_package_info',
                        package_code: packageCode
                    },
                    dataType: 'json',
                    beforeSend: function(xhr) {
                        console.log('=== AJAX Request Details ===');
                        console.log('URL: inventory_check.php');
                        console.log('Method: POST');
                        console.log('Data:', {
                            action: 'get_package_info',
                            package_code: packageCode
                        });
                        console.log('Current page URL:', window.location.href);
                    },
                    success: function(response) {
                        console.log('=== Auto Package Info Response ===');
                        console.log('Full response:', response);
                        console.log('Success status:', response.success);
                        console.log('Data:', response.data);
                        
                        if (response.success) {
                            // 调试输出：查看具体字段值
                            console.log('=== Auto Package Details ===');
                            console.log('Pieces:', response.data.pieces);
                            console.log('Glass Name:', response.data.glass_name);
                            console.log('Current Rack Code:', response.data.current_rack_code);
                            console.log('Current Rack ID:', response.data.current_rack_id);
                            console.log('Base ID:', response.data.base_id);
                            console.log('Base Name:', response.data.base_name);
                            
                            $('#glassName').val(response.data.glass_name);
                            $('#currentRack').val(response.data.current_rack_code || '未分配');
                            $('#currentRackId').val(response.data.current_rack_id || '');
                            
                            // 调试输出：查看字段更新情况
                            console.log('=== Auto Field Updates ===');
                            console.log('Glass Name set to:', $('#glassName').val());
                            console.log('Current Rack set to:', $('#currentRack').val());
                            console.log('Current Rack ID set to:', $('#currentRackId').val());
                            
                            // 加载可选库位
                            loadRackOptions(response.data.base_id, response.data.current_rack_id);
                            
                            $('#checkQuantity').focus();
                        } else {
                            console.log('=== Auto Error Response ===');
                            console.log('Error message:', response.message);
                            $('#glassName').val('');
                            $('#currentRack').val('');
                            alert('包不存在：' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('=== Auto AJAX Error ===');
                        console.log('Status:', status);
                        console.log('Error:', error);
                        console.log('Response Text (first 200 chars):', xhr.responseText.substring(0, 200));
                        console.log('Ready State:', xhr.readyState);
                        console.log('Status Code:', xhr.status);
                        console.log('Response Headers:', xhr.getAllResponseHeaders());
                        
                        // 如果返回HTML，说明是路径或权限问题
                        if (xhr.responseText && xhr.responseText.trim().startsWith('<!DOCTYPE')) {
                            console.log('=== HTML Response Detected ===');
                            console.log('Possible issues:');
                            console.log('1. Wrong URL path');
                            console.log('2. PHP error occurred');
                            console.log('3. Permission denied');
                            
                            // 尝试使用绝对路径
                            console.log('=== Trying Absolute Path ===');
                            $.ajax({
                                url: '../inventory/inventory_check.php',
                                method: 'POST',
                                data: {
                                    action: 'get_package_info',
                                    package_code: packageCode
                                },
                                dataType: 'json',
                                success: function(response) {
                                    console.log('=== Absolute Path Success ===');
                                    console.log('Response:', response);
                                    if (response.success) {
                                        $('#glassName').val(response.data.glass_name);
                                        $('#currentRack').val(response.data.current_rack_code || '未分配');
                                        $('#currentRackId').val(response.data.current_rack_id || '');
                                        loadRackOptions(response.data.base_id, response.data.current_rack_id);
                                        $('#checkQuantity').focus();
                                    }
                                },
                                error: function(xhr2, status2, error2) {
                                    console.log('=== Absolute Path Also Failed ===');
                                    console.log('Error:', error2);
                                    console.log('Response:', xhr2.responseText.substring(0, 200));
                                }
                            });
                        }
                        
                        $('#glassName').val('');
                        $('#currentRack').val('');
                        alert('查询失败，请检查网络连接');
                    }
                });
            }, 200); // 给模态框一点时间完全显示
        } else {
            console.log('=== No Package Code Provided ===');
            console.log('Setting up empty form...');
            
            $('#packageCode').val('').prop('readonly', false);
            $('#systemQuantity').val('');
            $('#glassName').val('');
            $('#checkQuantity').val('');
            $('#notes').val('');
            $('#packageCode').focus();
            
            console.log('=== Package Code Field Focused ===');
            console.log('Package Code element exists:', $('#packageCode').length > 0);
            console.log('Package Code current value:', $('#packageCode').val());
        }
    }, 100);
}

function submitManualInput() {
    var taskId = $('#taskId').val();
    var packageCode = $('#packageCode').val();
    var checkQuantity = $('#checkQuantity').val();
    var rackId = $('#rackSelect').val();
    var syncRack = $('#syncRackCheckbox').is(':checked') ? 1 : 0;
    var notes = $('#notes').val();
    
    if (!packageCode || !checkQuantity) {
        alert('请填写包号和盘点数量');
        return;
    }
    
    var confirmMessage = '确认提交盘点数据吗？\n\n';
    confirmMessage += '包号：' + packageCode + '\n';
    confirmMessage += '盘点数量：' + checkQuantity + '\n';
    
    if (rackId) {
        var rackText = $('#rackSelect option:selected').text();
        confirmMessage += '盘点库位：' + rackText + '\n';
        if (syncRack) {
            confirmMessage += '✅ 将同步更新系统库位\n';
        }
    }
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    // 创建表单并提交到内部方法
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'inventory_check.php?action=save_manual';
    
    // 添加隐藏字段
    var fields = [
        {name: 'task_id', value: taskId},
        {name: 'package_code[]', value: packageCode},
        {name: 'check_quantity[]', value: checkQuantity},
        {name: 'rack_id[]', value: rackId},
        {name: 'sync_rack[]', value: syncRack},
        {name: 'notes[]', value: notes}
    ];
    
    fields.forEach(function(field) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = field.name;
        input.value = field.value;
        form.appendChild(input);
    });
    
    // 提交表单
    document.body.appendChild(form);
    form.submit();
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

// 全局变量存储回滚计数
var currentRollbackCount = 0;

// 加载回滚统计信息
function loadRollbackCount() {
    $.ajax({
        url: '../api/inventory_check.php',
        method: 'POST',
        data: {
            action: 'get_rollback_count',
            task_id: <?php echo $task['id']; ?>
        },
        dataType: 'json',
        success: function(response) {
            if (response.code === 200) {
                var rollbackCount = response.data.count;
                currentRollbackCount = rollbackCount; // 存储到全局变量
                if (rollbackCount > 0) {
                    $('#rollback-message').html(
                        '<strong>已检测到' + rollbackCount + '个盘点期间的出库记录</strong><br>' +
                        '<small>这些记录已自动回滚到盘点数据中，避免误判为盘亏。</small>'
                    );
                    $('#rollback-info-panel').show();
                }
            }
        },
        error: function() {
            // 加载失败时静默处理，不影响页面其他功能
            console.log('加载回滚统计失败');
        }
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
    // 获取包详细信息
    $.ajax({
        url: 'inventory_check.php',
        method: 'POST',
        data: {
            action: 'get_package_info',
            package_code: packageCode
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showPackageDetailModal(packageCode, response.data);
            } else {
                alert('获取包信息失败：' + response.message);
            }
        },
        error: function() {
            alert('获取包信息失败，请检查网络连接');
        }
    });
}

function showPackageDetailModal(packageCode, packageData) {
    // 填充模态框数据
    $('#detailPackageCode').text(packageCode);
    $('#detailGlassName').text(packageData.glass_name || '-');
    $('#detailThickness').text(packageData.thickness ? packageData.thickness + 'mm' : '-');
    $('#detailBrand').text(packageData.brand || '-');
    $('#detailColor').text(packageData.color || '-');
    $('#detailQuantity').text(packageData.pieces || '0');
    $('#detailDimensions').text(
        (packageData.width && packageData.height) ? 
        packageData.width + 'mm × ' + packageData.height + 'mm' : 
        '-'
    );
    $('#detailEntryDate').text(packageData.entry_date || '-');
    $('#detailRackCode').text(packageData.rack_code || '-');
    $('#detailBaseName').text(packageData.base_name || '-');
    
    // 显示模态框
    $('.modal').modal('hide');
    setTimeout(function() {
        $('#packageDetailModal').modal('show');
    }, 100);
}

function showCompleteDialog() {
    console.log('=== Show Complete Dialog ===');
    console.log('Task ID:', <?php echo $task['id']; ?>);
    
    // 先关闭任何已打开的模态框，防止重复 backdrop
    $('.modal').modal('hide');
    
    // 延迟加载预览数据，确保模态框完全关闭
    setTimeout(function() {
        // 加载预览数据
        $.ajax({
            url: 'inventory_check.php?action=complete&id=<?php echo $task['id']; ?>&preview=1',
            type: 'GET',
            dataType: 'json',
            beforeSend: function(xhr) {
                console.log('=== Loading Preview Data ===');
                console.log('URL:', 'inventory_check.php?action=complete&id=<?php echo $task['id']; ?>&preview=1');
            },
            success: function(response) {
                console.log('=== Preview Response ===');
                console.log('Response:', response);
                console.log('Response type:', typeof response);
                console.log('Response success:', response.success);
                console.log('Response data:', response.data);
                
                if (response && response.success) {
                    console.log('=== Updating Preview ===');
                    updateCompletionPreview(response.data);
                    // 立即显示模态框
                    $('#completeTaskModal').modal('show');
                } else {
                    console.log('=== Preview Failed ===');
                    console.log('Error message:', response.message);
                    alert('加载预览数据失败：' + (response.message || '未知错误'));
                    // 即使失败也显示模态框，但显示错误信息
                    $('#completeTaskModal').modal('show');
                }
            },
            error: function(xhr, status, error) {
                console.log('=== AJAX Error ===');
                console.log('Status:', status);
                console.log('Error:', error);
                console.log('Response Text:', xhr.responseText);
                console.log('Status Code:', xhr.status);
                
                // 尝试解析响应
                if (xhr.responseText) {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        console.log('Parsed response:', data);
                        if (data.success) {
                            updateCompletionPreview(data.data);
                            $('#completeTaskModal').modal('show');
                            return;
                        }
                    } catch (e) {
                        console.log('Failed to parse response as JSON:', e);
                    }
                }
                
                alert('加载预览数据失败：' + error);
                // 即使失败也显示模态框
                $('#completeTaskModal').modal('show');
            }
        });
    }, 100);
}

function updateCompletionPreview(data) {
    console.log('=== Update Completion Preview ===');
    console.log('Data:', data);
    console.log('Data type:', typeof data);
    
    // 检查数据是否存在
    if (!data) {
        console.log('=== No Data Provided ===');
        $('#rackAdjustmentPreview').html('<div class="alert alert-muted">没有调整数据</div>');
        $('#profitLossPreview').html('<div class="alert alert-muted">没有差异数据</div>');
        return;
    }
    
    // 更新库位调整预览
    var rackHtml = '';
    if (data.rack_adjustments && data.rack_adjustments.length > 0) {
        console.log('=== Rack Adjustments Found ===');
        console.log('Count:', data.rack_adjustments.length);
        
        rackHtml = '<div class="alert alert-info"><strong>需要调整库位的包 (' + data.rack_adjustments.length + ' 个)：</strong><br>';
        for (var i = 0; i < Math.min(5, data.rack_adjustments.length); i++) {
            var item = data.rack_adjustments[i];
            rackHtml += item.package_code + ': ' + (item.original_rack_code || '未分配') + ' → ' + (item.new_rack_code || '未分配') + '<br>';
        }
        if (data.rack_adjustments.length > 5) {
            rackHtml += '...还有 ' + (data.rack_adjustments.length - 5) + ' 个包';
        }
        rackHtml += '</div>';
    } else {
        console.log('=== No Rack Adjustments ===');
        rackHtml = '<div class="alert alert-muted">没有库位调整</div>';
    }
    
    // 更新盘盈盘亏预览
    var profitLossHtml = '';
    if (data.profit_loss && data.profit_loss.length > 0) {
        console.log('=== Profit/Loss Found ===');
        console.log('Count:', data.profit_loss.length);
        
        profitLossHtml = '<div class="alert alert-warning"><strong>盘盈盘亏情况 (' + data.profit_loss.length + ' 个)：</strong><br>';
        for (var i = 0; i < Math.min(5, data.profit_loss.length); i++) {
            var item = data.profit_loss[i];
            profitLossHtml += item.package_code + ': ' + item.difference_type + ' ' + item.difference + ' 片<br>';
        }
        if (data.profit_loss.length > 5) {
            profitLossHtml += '...还有 ' + (data.profit_loss.length - 5) + ' 个包';
        }
        profitLossHtml += '</div>';
    } else {
        console.log('=== No Profit/Loss ===');
        profitLossHtml = '<div class="alert alert-muted">没有盘盈盘亏</div>';
    }
    
    console.log('=== Updating DOM ===');
    console.log('Rack HTML:', rackHtml);
    console.log('Profit/Loss HTML:', profitLossHtml);
    
    // 更新模态框内容
    $('#rackAdjustmentPreview').html(rackHtml);
    $('#profitLossPreview').html(profitLossHtml);
    
    console.log('=== DOM Updated ===');
}

function submitCompleteTask() {
    console.log('=== Submit Complete Task ===');
    
    var adjustInventory = $('input[name="adjust_inventory"]:checked').val();
    var notes = $('#complete_notes').val();
    
    console.log('Adjust Inventory:', adjustInventory);
    console.log('Notes:', notes);
    
    // 确认信息
    var confirmMessage = '确定要完成盘点任务吗？\n\n';
    confirmMessage += '应盘包数：' + <?php echo $task['total_packages']; ?> + '\n';
    confirmMessage += '已盘包数：' + <?php echo $task['checked_packages']; ?> + '\n';
    confirmMessage += '差异数量：' + <?php echo $task['difference_count']; ?> + '\n\n';
    
    if (currentRollbackCount > 0) {
        confirmMessage += '⚠️ 已检测到' + currentRollbackCount + '个盘点期间的出库记录\n';
        confirmMessage += '✅ 这些记录已自动回滚到盘点数据中\n\n';
    }
    
    if (adjustInventory === '1') {
        confirmMessage += '√ 将根据盘点结果自动调整库存数量\n';
        confirmMessage += '√ 盘盈增加库存，盘亏减少库存\n\n';
        confirmMessage += '√ 自动调整库位位置\n\n';
        confirmMessage += '此操作不可撤销，请再次确认盘点数据！';
    } else {
        confirmMessage += '√ 仅生成盘点报告，不调整库存\n\n';
        confirmMessage += '后续可以通过其他方式调整库存差异。';
    }
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    // 通过选择器获取按钮，避免使用 event.target
    var submitBtn = $('button[onclick="submitCompleteTask()"]');
    
    // 禁用按钮，防止重复提交
    submitBtn.prop('disabled', true);
    submitBtn.html('<i class="glyphicon glyphicon-refresh glyphicon-spin"></i> 处理中...');
    
    console.log('=== Sending AJAX Request ===');
    
    // AJAX提交
    $.ajax({
        url: 'inventory_check.php?action=complete&id=<?php echo $task['id']; ?>',
        type: 'POST',
        dataType: 'json',
        data: {
            'auto_adjust': adjustInventory === '1' ? '1' : '0',
            'complete_notes': notes
        },
        beforeSend: function(xhr) {
            console.log('=== Before Send ===');
            console.log('URL:', 'inventory_check.php?action=complete&id=<?php echo $task['id']; ?>');
            console.log('Data:', {
                'auto_adjust': adjustInventory === '1' ? '1' : '0',
                'complete_notes': notes
            });
        },
        success: function(response) {
            console.log('=== AJAX Success ===');
            console.log('Response:', response);
            console.log('Response type:', typeof response);
            console.log('Response success:', response.success);
            console.log('Response redirect:', response.redirect);
            
            if (response && response.success) {
                alert('盘点任务已完成！');
                if (response.redirect) {
                    window.location.href = response.redirect;
                } else {
                    // 如果没有重定向地址，刷新页面
                    window.location.reload();
                }
            } else {
                console.log('=== Task Completion Failed ===');
                console.log('Error message:', response.message);
                alert('完成任务失败：' + (response.message || '未知错误'));
                submitBtn.prop('disabled', false);
                submitBtn.html('<i class="glyphicon glyphicon-ok"></i> 确认完成');
            }
        },
        error: function(xhr, status, error) {
            console.log('=== AJAX Error ===');
            console.log('Status:', status);
            console.log('Error:', error);
            console.log('Response Text:', xhr.responseText);
            console.log('Status Code:', xhr.status);
            
            // 尝试解析响应
            var errorMessage = '请求失败，请重试';
            if (xhr.responseText) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.message) {
                        errorMessage = '完成任务失败：' + data.message;
                    }
                } catch (e) {
                    console.log('Failed to parse response as JSON:', e);
                    if (xhr.responseText.indexOf('错误') !== -1 || xhr.responseText.indexOf('Error') !== -1) {
                        errorMessage = '服务器错误：' + xhr.responseText.substring(0, 100);
                    }
                }
            }
            
            alert(errorMessage);
            submitBtn.prop('disabled', false);
            submitBtn.html('<i class="glyphicon glyphicon-ok"></i> 确认完成');
        }
    });
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
 * 获取包的当前库位
 */
function getPackageCurrentRack($packageCode) {
    static $cache = [];
    
    if (!isset($cache[$packageCode])) {
        $sql = "SELECT r.code 
                FROM glass_packages p 
                LEFT JOIN storage_racks r ON p.current_rack_id = r.id 
                WHERE p.package_code = ?";
        $result = fetchRow($sql, [$packageCode]);
        $cache[$packageCode] = $result ? $result['code'] : '未分配';
    }
    
    return $cache[$packageCode];
}

/**
 * 根据库位ID获取库位代码
 */
function getRackCodeById($rackId) {
    static $cache = [];
    
    if (!isset($cache[$rackId])) {
        $result = fetchRow("SELECT code FROM storage_racks WHERE id = ?", [$rackId]);
        $cache[$rackId] = $result ? $result['code'] : '';
    }
    
    return $cache[$rackId];
}

/**
 * 统计库位变更数量
 */
function countRackChanges($details) {
    $count = 0;
    foreach ($details as $detail) {
        if ($detail['rack_id'] && $detail['current_rack_code']) {
            $rackCode = getRackCodeById($detail['rack_id']);
            if ($rackCode && $detail['current_rack_code'] !== $rackCode) {
                $count++;
            }
        }
    }
    return $count;
}

/**
 * 统计已同步库位的数量
 */
function countSyncedRacks($details) {
    $count = 0;
    foreach ($details as $detail) {
        if ($detail['notes'] && strpos($detail['notes'], '盘点时同步更新库位') !== false) {
            $count++;
        }
    }
    return $count;
}

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


?>

<style>
.difference-row {
    background-color: #fcf8e3 !important;
}
.pending-row {
    color: #999;
}

/* 库位相关样式 */
.rack-changed {
    background-color: #fff3cd !important;
}

.checkbox label {
    font-size: 12px;
    color: #666;
}

#rackSelect {
    font-size: 12px;
}

.label-warning {
    background-color: #f0ad4e;
}

/* 响应式调整 */
@media (max-width: 768px) {
    .table th:nth-child(7),
    .table th:nth-child(8),
    .table td:nth-child(7),
    .table td:nth-child(8) {
        display: none;
    }
}

/* 修复模态框 z-index 问题 */
.modal {
    z-index: 1050 !important;
}

.modal-backdrop {
    z-index: 1040 !important;
}

.modal-dialog {
    z-index: 1060 !important;
}

.modal-content {
    z-index: 1070 !important;
}

/* 确保模态框在所有元素之上 */
.modal-open .modal {
    z-index: 1050 !important;
}

.modal-open .modal-backdrop {
    z-index: 1040 !important;
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
                
                <!-- 预览区域 -->
                <div id="completionPreview">
                    <h5><i class="glyphicon glyphicon-eye-open"></i> 调整预览</h5>
                    <div id="rackAdjustmentPreview"></div>
                    <div id="profitLossPreview"></div>
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
                                <small class="text-muted">根据盘点结果自动调整库存数量和库位。盘盈增加库存，盘亏减少库存，库位变更自动同步。</small>
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

<!-- 包详情模态框 -->
<div class="modal fade" id="packageDetailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">包详细信息</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered table-condensed">
                            <tr>
                                <td width="40%"><strong>包号：</strong></td>
                                <td id="detailPackageCode">-</td>
                            </tr>
                            <tr>
                                <td><strong>原片名称：</strong></td>
                                <td id="detailGlassName">-</td>
                            </tr>
                            <tr>
                                <td><strong>厚度：</strong></td>
                                <td id="detailThickness">-</td>
                            </tr>
                            <tr>
                                <td><strong>品牌：</strong></td>
                                <td id="detailBrand">-</td>
                            </tr>
                            <tr>
                                <td><strong>颜色：</strong></td>
                                <td id="detailColor">-</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered table-condensed">
                            <tr>
                                <td width="40%"><strong>数量：</strong></td>
                                <td id="detailQuantity">-</td>
                            </tr>
                            <tr>
                                <td><strong>尺寸：</strong></td>
                                <td id="detailDimensions">-</td>
                            </tr>
                            <tr>
                                <td><strong>入库日期：</strong></td>
                                <td id="detailEntryDate">-</td>
                            </tr>
                            <tr>
                                <td><strong>库位：</strong></td>
                                <td id="detailRackCode">-</td>
                            </tr>
                            <tr>
                                <td><strong>基地：</strong></td>
                                <td id="detailBaseName">-</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="glyphicon glyphicon-remove"></i> 关闭
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>