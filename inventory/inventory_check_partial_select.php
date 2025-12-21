<?php require_once '../includes/auth.php'; ?>
<?php require_once '../includes/db.php'; ?>
<?php require_once '../includes/functions.php'; ?>
<?php require_once '../includes/inventory_check_auth.php'; ?>
<?php require_once 'header.php'; ?>

<?php
// 检查权限：只有库管和管理员可以使用盘点功能
requireInventoryCheckPermission();

$taskId = $_GET['task_id'] ?? 0;

// 验证任务存在和权限
$task = fetchRow("
    SELECT t.*, b.name AS base_name, u.real_name AS created_by_name
    FROM inventory_check_tasks t
    LEFT JOIN bases b ON t.base_id = b.id
    LEFT JOIN users u ON t.created_by = u.id
    WHERE t.id = ?
", [$taskId]);

if (!$task) {
    redirect('inventory_check_list.php?error=task_not_found');
    exit;
}

// 验证任务状态必须是 'created'
if ($task['status'] !== 'created') {
    redirect("inventory_check.php?action=view&id=$taskId&error=task_already_started");
    exit;
}

// 验证任务类型必须是 'partial'
if ($task['task_type'] !== 'partial') {
    redirect("inventory_check.php?action=view&id=$taskId&error=invalid_task_type");
    exit;
}

// 验证权限（只能操作自己创建的任务或管理员）
$user = getCurrentUser();
if ($user['role'] !== 'admin' && $task['created_by'] != $user['id']) {
    redirect('inventory_check_list.php?error=no_permission');
    exit;
}

// 获取该基地所有可选择的包
$packages = fetchAll("
    SELECT p.id, p.package_code, p.pieces, 
           g.short_name AS glass_name,
           r.code AS current_rack_code,
           sr.area_type
    FROM glass_packages p
    INNER JOIN glass_types g ON p.glass_type_id = g.id
    LEFT JOIN storage_racks r ON p.current_rack_id = r.id
    LEFT JOIN storage_racks sr ON p.current_rack_id = sr.id
    WHERE sr.base_id = ? AND p.status = 'in_storage'
    ORDER BY sr.code, p.package_code
", [$task['base_id']]);

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedPackages = $_POST['selected_packages'] ?? [];
    
    if (empty($selectedPackages)) {
        $error = '请至少选择一个包进行盘点';
    } else {
        try {
            // 开始任务
            update('inventory_check_tasks', [
                'status' => 'in_progress',
                'started_at' => date('Y-m-d H:i:s'),
                'total_packages' => count($selectedPackages)
            ], 'id = ?', [$taskId]);
            
            // 为选中的包创建缓存记录
            foreach ($selectedPackages as $packageId) {
                $packageInfo = fetchRow("
                    SELECT p.id, p.package_code, p.pieces, p.glass_type_id, p.current_rack_id,
                           g.short_name AS glass_name
                    FROM glass_packages p
                    INNER JOIN glass_types g ON p.glass_type_id = g.id
                    WHERE p.id = ?
                ", [$packageId]);
                
                if ($packageInfo) {
                    insert('inventory_check_cache', [
                        'task_id' => $taskId,
                        'package_code' => $packageInfo['package_code'],
                        'package_id' => $packageInfo['id'],
                        'system_quantity' => $packageInfo['pieces'],
                        'check_quantity' => 0,
                        'difference' => -$packageInfo['pieces'],
                        'rack_id' => $packageInfo['current_rack_id'], // 保存原始库位作为初始值
                        'check_method' => 'manual_input',
                        'operator_id' => getCurrentUserId(),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            
            redirect("inventory_check.php?action=view&id=$taskId&success=partial_selection_completed");
            
        } catch (Exception $e) {
            $error = '操作失败：' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <!-- 页面头部 -->
    <div class="row">
        <div class="col-md-12">
            <div class="page-header clearfix">
                <h2 class="pull-left">
                    <i class="glyphicon glyphicon-list-alt"></i> 部分盘点 - 选择包
                </h2>
                <div class="pull-right">
                    <a href="inventory_check_list.php" class="btn btn-default">
                        <i class="glyphicon glyphicon-arrow-left"></i> 返回列表
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
                    <strong>基地：</strong><?php echo htmlspecialchars($task['base_name']); ?>
                </div>
                <div class="col-md-3">
                    <strong>盘点类型：</strong>
                    <span class="label label-warning">部分盘点</span>
                </div>
                <div class="col-md-3">
                    <strong>创建人：</strong><?php echo htmlspecialchars($task['created_by_name']); ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="glyphicon glyphicon-exclamation-sign"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- 选择表单 -->
    <form method="POST" id="selectionForm">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h4>
                    选择要盘点的包
                    <small class="pull-right">
                        总共 <?php echo count($packages); ?> 个包可选
                        <span id="selectedCount" class="label label-info">已选择 0 个</span>
                    </small>
                </h4>
            </div>
            <div class="panel-body">
                <!-- 筛选工具 -->
                <div class="row" style="margin-bottom: 15px;">
                    <div class="col-md-4">
                        <input type="text" id="searchInput" class="form-control" placeholder="搜索包号或原片类型...">
                    </div>
                    <div class="col-md-3">
                        <select id="rackFilter" class="form-control">
                            <option value="">所有库位</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-info btn-block" onclick="selectAll()">全选</button>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-warning btn-block" onclick="selectNone()">全不选</button>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-success btn-block">
                            <i class="glyphicon glyphicon-play"></i> 开始
                        </button>
                    </div>
                </div>

                <!-- 包列表 -->
                <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                    <table class="table table-striped table-hover" id="packagesTable">
                        <thead style="position: sticky; top: 0; background: #f5f5f5;">
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()">
                                </th>
                                <th>包号</th>
                                <th>原片类型</th>
                                <th>数量</th>
                                <th>当前库位</th>
                                <th>区域类型</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($packages)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    该基地暂无可盘点的包
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($packages as $package): ?>
                                <tr data-rack="<?php echo htmlspecialchars($package['current_rack_code']); ?>" 
                                    data-search="<?php echo htmlspecialchars($package['package_code'] . ' ' . $package['glass_name']); ?>">
                                    <td>
                                        <input type="checkbox" name="selected_packages[]" 
                                               value="<?php echo $package['id']; ?>"
                                               class="package-checkbox"
                                               onchange="updateSelectedCount()">
                                    </td>
                                    <td><code><?php echo htmlspecialchars($package['package_code']); ?></code></td>
                                    <td><?php echo htmlspecialchars($package['glass_name']); ?></td>
                                    <td class="text-center">
                                        <span class="badge badge-primary"><?php echo $package['pieces']; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($package['current_rack_code']): ?>
                                            <span class="label label-default"><?php echo htmlspecialchars($package['current_rack_code']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">未分配</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $areaTypes = [
                                            'storage' => '库存区',
                                            'processing' => '加工区', 
                                            'scrap' => '报废区',
                                            'temporary' => '临时区'
                                        ];
                                        $areaType = $package['area_type'] ?? 'storage';
                                        $areaColors = [
                                            'storage' => 'success',
                                            'processing' => 'warning',
                                            'scrap' => 'danger',
                                            'temporary' => 'info'
                                        ];
                                        ?>
                                        <span class="label label-<?php echo $areaColors[$areaType]; ?>">
                                            <?php echo $areaTypes[$areaType]; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    // 初始化库位筛选下拉框
    var racks = [];
    $('table tbody tr[data-rack]').each(function() {
        var rack = $(this).data('rack');
        if (rack && racks.indexOf(rack) === -1) {
            racks.push(rack);
        }
    });
    
    racks.sort();
    racks.forEach(function(rack) {
        $('#rackFilter').append('<option value="' + rack + '">' + rack + '</option>');
    });
    
    // 搜索功能
    $('#searchInput').on('input', function() {
        var search = $(this).val().toLowerCase();
        $('table tbody tr').each(function() {
            var text = $(this).data('search').toLowerCase();
            $(this).toggle(text.indexOf(search) !== -1);
        });
    });
    
    // 库位筛选
    $('#rackFilter').on('change', function() {
        var rack = $(this).val();
        $('table tbody tr').each(function() {
            if (rack === '') {
                $(this).show();
            } else {
                $(this).toggle($(this).data('rack') === rack);
            }
        });
    });
    
    updateSelectedCount();
});

function updateSelectedCount() {
    var count = $('.package-checkbox:checked').length;
    $('#selectedCount').text('已选择 ' + count + ' 个');
}

function toggleSelectAll() {
    var checked = $('#selectAllCheckbox').prop('checked');
    $('.package-checkbox').prop('checked', checked);
    updateSelectedCount();
}

function selectAll() {
    $('.package-checkbox:visible').prop('checked', true);
    updateSelectedCount();
}

function selectNone() {
    $('.package-checkbox').prop('checked', false);
    updateSelectedCount();
}
</script>

<?php include 'footer.php'; ?>