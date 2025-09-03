<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/inventory_operations.php'; // 引入公共业务逻辑
require_once '../includes/admin_layout.php'; // 引入领用单业务逻辑


// 生成领用单号函数
function generateOrderNo($baseId)
{
    $date = date('Ymd');
    $prefix = "UO{$date}";

    // 获取当天该基地的最大序号
    $sql = "SELECT order_no FROM usage_orders WHERE base_id = ? AND DATE(order_date) = CURDATE() ORDER BY order_no DESC LIMIT 1";
    $lastOrder = fetchRow($sql, [$baseId]);

    if ($lastOrder) {
        // 提取序号并加1
        $lastNo = $lastOrder['order_no'];
        $seq = (int)substr($lastNo, -3) + 1;
    } else {
        $seq = 1;
    }

    return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
}

requireLogin();
requireRole(['admin', 'manager', 'operator']);

$currentUser = getCurrentUser();
$message = '';
$error = '';

// 处理AJAX请求 - 获取包信息
if (isset($_GET['action']) && $_GET['action'] === 'get_package_info') {
    $packageCode = trim($_GET['package_code'] ?? '');
    $result = getPackageInfo($packageCode); // 使用公共函数
    jsonResponse($result);
}

// 获取交易类型 - 根据用户角色过滤
$allTransactionTypes = [
    'purchase_in' => '采购入库',   //操作员、库管、管理员可见
    'usage_out' => '领用出库',     //操作员、库管、管理员可见
    'partial_usage' => '部分领用',  //均不可见，系统处理和领用单需要字段
    'return_in' => '归还入库',      //操作员、库管、管理员可见
    'scrap' => '报废出库',          //库管、管理员可见
    'check_in' => '盘盈入库',       //库管、管理员可见
    'check_out' => '盘亏出库',       //库管、管理员可见
    'location_adjust' => '库位流转'  //库管、管理员可见
];

// 根据用户角色过滤可见的交易类型
$transactionTypes = [];
foreach ($allTransactionTypes as $key => $label) {
    switch ($key) {
        case 'purchase_in':
        case 'usage_out':
        case 'scrap':
        case 'check_in':
        case 'partial_usage':
        case 'check_out':
            // 库管、管理员可见
            if (in_array($currentUser['role'], ['manager', 'admin'])) {
                $transactionTypes[$key] = $label;
            }
            break;
        case 'location_adjust':
            // 操作员、库管、管理员可见
            if (in_array($currentUser['role'], ['operator', 'manager', 'admin'])) {
                $transactionTypes[$key] = $label;
            }
            break;
        case 'return_in':
            // 操作员、库管、管理员可见
            if (in_array($currentUser['role'], ['operator', 'manager', 'admin'])) {
                $transactionTypes[$key] = $label;
            }
            break;
    }
}


// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_transaction') {
        try {
            $packageCode = trim($_POST['package_code']);
            $targetRackCode = trim($_POST['target_rack_code']);
            $quantity = intval($_POST['quantity']);
            $transactionType = $_POST['transaction_type'];
            $scrapReason = trim($_POST['scrap_reason'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            // 验证输入
            if (empty($packageCode) || empty($targetRackCode) || $quantity <= 0 || empty($transactionType)) {
                throw new Exception('请填写所有必填字段');
            }

            // 验证交易类型权限
            if (!array_key_exists($transactionType, $transactionTypes)) {
                throw new Exception('无权限执行此类型的操作');
            }

            // 使用公共业务逻辑函数
            $message = executeInventoryTransaction(
                $packageCode,
                $targetRackCode,
                $quantity,
                $transactionType,
                $currentUser,
                $scrapReason,
                $notes
            );
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
// 获取搜索参数
$search = $_GET['search'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 8;
$offset = ($page - 1) * $limit;

// 构建查询条件
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(gp.package_code LIKE ? OR gt.name LIKE ? OR u.real_name LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($typeFilter)) {
    $whereConditions[] = "t.transaction_type = ?";
    $params[] = $typeFilter;
}

if (!empty($dateFrom)) {
    $whereConditions[] = "DATE(t.transaction_time) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = "DATE(t.transaction_time) <= ?";
    $params[] = $dateTo;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// 获取交易记录总数 - 修正表名
$countSql = "SELECT COUNT(*) FROM inventory_transactions t 
             LEFT JOIN glass_packages gp ON t.package_id = gp.id 
             LEFT JOIN glass_types gt ON gp.glass_type_id = gt.id 
             LEFT JOIN users u ON t.operator_id = u.id 
             $whereClause";
$totalRecords = fetchOne($countSql, $params);
$totalPages = ceil($totalRecords / $limit);

// 获取交易记录 - 移除areas相关的JOIN和字段
$sql = "SELECT t.*, gp.package_code, gt.name as glass_name, gt.thickness, gt.color,
               u.real_name as operator_name, u.username as operator_username,
               fr.code as from_rack_code,
               tr.code as to_rack_code
        FROM inventory_transactions t
        LEFT JOIN glass_packages gp ON t.package_id = gp.id
        LEFT JOIN glass_types gt ON gp.glass_type_id = gt.id
        LEFT JOIN users u ON t.operator_id = u.id
        LEFT JOIN storage_racks fr ON t.from_rack_id = fr.id
        LEFT JOIN storage_racks tr ON t.to_rack_id = tr.id
        $whereClause
        ORDER BY t.transaction_time DESC
        LIMIT $limit OFFSET $offset";
$transactions = fetchAll($sql, $params);

// 获取统计数据 - 修正表名
$stats = [
    'today_transactions' => fetchOne("SELECT COUNT(*) FROM inventory_transactions WHERE DATE(transaction_time) = CURDATE()"),
    'total_transactions' => fetchOne("SELECT COUNT(*) FROM inventory_transactions"),
    'purchase_in_today' => fetchOne("SELECT COUNT(*) FROM inventory_transactions WHERE transaction_type = 'purchase_in' AND DATE(transaction_time) = CURDATE()"),
    'usage_out_today' => fetchOne("SELECT COUNT(*) FROM inventory_transactions WHERE transaction_type = 'usage_out' AND DATE(transaction_time) = CURDATE()")
];
ob_start();
?>
<!-- 统计数据 -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['today_transactions']; ?></div>
        <div class="stat-label">今日记录</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['total_transactions']; ?></div>
        <div class="stat-label">总记录数</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['purchase_in_today']; ?></div>
        <div class="stat-label">今日入库</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['usage_out_today']; ?></div>
        <div class="stat-label">今日出库</div>
    </div>
</div>

<!-- 新增交易按钮 -->
<div style="margin-bottom: 20px;">
    <button type="button" class="btn btn-success" onclick="openTransactionModal()">新增流转记录</button>
</div>

<!-- 搜索表单 -->
<div class="search-form">
    <form method="GET">
        <div class="search-row-compact">
            <div class="form-group-compact">
                <label for="search">搜索:</label>
                <input type="text" id="search" name="search" class="form-control-compact"
                    value="<?php echo htmlspecialchars($search); ?>" placeholder="包号、玻璃名称、操作员">
            </div>
            <div class="form-group-compact">
                <label for="type">类型:</label>
                <select id="type" name="type" class="form-control-compact">
                    <option value="">全部类型</option>
                    <?php foreach ($transactionTypes as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $typeFilter === $value ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group-compact">
                <label for="date_from">开始:</label>
                <input type="date" id="date_from" name="date_from" class="form-control-compact"
                    value="<?php echo htmlspecialchars($dateFrom); ?>">
            </div>
            <div class="form-group-compact">
                <label for="date_to">结束:</label>
                <input type="date" id="date_to" name="date_to" class="form-control-compact"
                    value="<?php echo htmlspecialchars($dateTo); ?>">
            </div>
            <div class="form-group-compact">
                <button type="submit" class="btn btn-primary btn-compact">搜索</button>
                <a href="transactions.php" class="btn btn-secondary btn-compact">重置</a>
            </div>
        </div>
    </form>
</div>

<!-- 交易记录表格 -->
<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>交易时间</th>
                <th>包号</th>
                <th>玻璃信息</th>
                <th>交易类型</th>
                <th>来源位置</th>
                <th>目标位置</th>
                <th>数量</th>
                <th>操作员</th>
                <th>备注</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($transactions)): ?>
                <tr>
                    <td colspan="10" style="text-align: center; color: #666;">暂无记录</td>
                </tr>
            <?php else: ?>
                <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($transaction['transaction_time'])); ?></td>
                        <td><?php echo htmlspecialchars($transaction['package_code']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($transaction['glass_name']); ?><br>
                            <small><?php echo htmlspecialchars($transaction['thickness'] . 'mm ' . $transaction['color']); ?></small>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $transaction['transaction_type']; ?>">
                                <?php echo htmlspecialchars($transactionTypes[$transaction['transaction_type']]); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($transaction['from_rack_code']): ?>
                                <?php echo htmlspecialchars($transaction['from_rack_code']); ?>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($transaction['to_rack_code']); ?></td>
                        <td><?php echo $transaction['quantity']; ?></td>
                        <td><?php echo htmlspecialchars($transaction['operator_name'] ?: $transaction['operator_username']); ?></td>
                        <td>
                            <?php if ($transaction['scrap_reason']): ?>
                                <strong>报废原因:</strong> <?php echo htmlspecialchars($transaction['scrap_reason']); ?>
                            <?php else: ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 分页 -->
<?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($typeFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>">&laquo; 上一页</a>
        <?php endif; ?>

        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <?php if ($i == $page): ?>
                <span class="current"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($typeFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($typeFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>">下一页 &raquo;</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>

<!-- 新增交易模态框 -->
<div id="transactionModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeTransactionModal()">&times;</span>
        <h2>新增流转记录</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_transaction">

            <div class="form-group">
                <label for="transaction_type">操作类型:</label>
                <select id="transaction_type" name="transaction_type" class="form-control" required onchange="toggleScrapReason()">
                    <option value="">请选择操作类型</option>
                    <?php foreach ($transactionTypes as $value => $label): ?>
                        <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="package_code">原片包号:</label>
                <input type="text" id="package_code" name="package_code" class="form-control" required onchange="loadPackageInfo()">
            </div>

            <!-- 包信息显示区域 -->
            <div id="package-info" style="display: none; background: #f5f5f5; padding: 10px; margin: 10px 0; border-radius: 4px;">
                <h4>包信息</h4>
                <div id="package-details"></div>
            </div>

            <div class="form-group">
                <label for="target_rack_code">目标库位架:</label>
                <input type="text" id="target_rack_code" name="target_rack_code" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="quantity">数量:</label>
                <input type="number" id="quantity" name="quantity" class="form-control" min="1" required>
            </div>

            <div class="form-group scrap-reason" style="display: none;">
                <label for="scrap_reason">报废原因:</label>
                <textarea id="scrap_reason" name="scrap_reason" class="form-control" rows="3"></textarea>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">确认提交</button>
                <button type="button" class="btn btn-secondary" onclick="closeTransactionModal()">取消</button>
            </div>
        </form>
    </div>
</div>

<script>
    // 模态框控制
    function openTransactionModal() {
        document.getElementById('transactionModal').style.display = 'block';
    }

    function closeTransactionModal() {
        document.getElementById('transactionModal').style.display = 'none';
        // 重置表单
        document.querySelector('#transactionModal form').reset();
        document.querySelector('.scrap-reason').style.display = 'none';
        document.getElementById('scrap_reason').removeAttribute('required');
        document.getElementById('package-info').style.display = 'none';
    }

    // 点击模态框外部关闭
    window.onclick = function(event) {
        var modal = document.getElementById('transactionModal');
        if (event.target == modal) {
            closeTransactionModal();
        }
    }

    // 根据操作类型显示/隐藏报废原因
    function toggleScrapReason() {
        const transactionType = document.getElementById('transaction_type').value;
        const scrapReasonDiv = document.querySelector('.scrap-reason');
        const scrapReasonField = document.getElementById('scrap_reason');

        if (transactionType === 'scrap') {
            scrapReasonDiv.style.display = 'block';
            scrapReasonField.setAttribute('required', 'required');
        } else {
            scrapReasonDiv.style.display = 'none';
            scrapReasonField.removeAttribute('required');
        }
    }

    // 自动刷新时间显示
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleString('zh-CN');
        document.getElementById('current-time').textContent = timeString;
        document.title = '流转记录管理 - ' + timeString;
    }

    setInterval(updateTime, 1000);
    updateTime();

    // 表单验证
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#dc3545';
                        isValid = false;
                    } else {
                        field.style.borderColor = '#ddd';
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    alert('请填写所有必填字段');
                }
            });
        });
    });

    // 获取包信息函数
    function loadPackageInfo() {
        const packageCode = document.getElementById('package_code').value.trim();
        if (!packageCode) {
            document.getElementById('package-info').style.display = 'none';
            return;
        }
        // 显示加载状态
        const packageInfo = document.getElementById('package-info');
        const packageDetails = document.getElementById('package-details');
        packageDetails.innerHTML = '<p>正在查询包信息...</p>';
        packageInfo.style.display = 'block';

        // 发送AJAX请求
        fetch(`transactions.php?action=get_package_info&package_code=${encodeURIComponent(packageCode)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const pkg = data.data;
                    packageDetails.innerHTML = `
                                <p><strong>包号:</strong> ${pkg.package_code}</p>
                                <p><strong>玻璃类型:</strong> ${pkg.glass_name || '未知'}</p>
                                <p><strong>当前片数:</strong> ${pkg.pieces}</p>
                                <p><strong>当前库位:</strong> ${pkg.current_rack_code || '未分配'}</p>
                                <p><strong>当前区域:</strong> ${getAreaTypeName(pkg.current_area_type)}</p>
                                <p><strong>状态:</strong> ${getStatusName(pkg.status)}</p>
                            `;
                    // 根据交易类型自动设置数量
                    const transactionType = document.getElementById('transaction_type').value;
                    const quantityInput = document.getElementById('quantity');
                    if (transactionType === 'return_in') {
                        // 归还入库时，用户需要输入实际剩余片数
                        quantityInput.placeholder = `请输入剩余片数（当前：${pkg.pieces}）`;
                    } else {
                        // 其他操作为整包流转
                        quantityInput.value = pkg.pieces;
                    }
                    // 存储包信息供后续使用
                    window.currentPackageInfo = pkg;
                } else {
                    packageDetails.innerHTML = `<p class="error">${data.message}</p>`;
                }
            })
            .catch(error => {
                console.error('获取包信息失败:', error);
                packageDetails.innerHTML = '<p class="error">获取包信息失败，请重试</p>';
            });
    }

    // 获取区域类型名称
    function getAreaTypeName(areaType) {
        const areaTypes = {
            'temporary': '临时区',  //采购未到库的就在临时区
            'storage': '存储区',    //基本区域，出库入库的基本库对象就是他
            'processing': '加工区',
            'scrap': '报废区'
        };
        return areaTypes[areaType] || areaType || '未知';
    }

    // 获取状态名称
    function getStatusName(status) {
        const statuses = {
            'in_stock': '在库',
            'out_stock': '出库',
            'scrapped': '已报废'
        };
        return statuses[status] || status || '未知';
    }
</script>
</div>
</body>

</html>
<?php
$content = ob_get_clean();
echo renderAdminLayout('流转记录管理', $content, $currentUser, 'transactions.php', [], [], $message ?? '', $messageType ?? 'info');
?>