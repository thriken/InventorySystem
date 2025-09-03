<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/admin_layout.php';

requireLogin();
requireRole(['admin', 'manager']);
$currentUser = getCurrentUser();
$areaTypes = [
    'storage' => '库存区',
    'processing' => '加工区',
    'scrap' => '报废区',
    'temporary' => '临时区'
];
$areaTypeCodes = [
    'storage' => 'N',      // 库存区：N (normal)
    'processing' => 'P',   // 加工区：P (process)
    'temporary' => 'T',    // 临时区：T (temp)
    'scrap' => 'B'         // 报废区：B (broken)
];

function generateRackCode($baseId, $areaType, $rackName)
{
    global $areaTypeCodes;
    $base = fetchRow("SELECT code FROM bases WHERE id = ?", [$baseId]);
    if (!$base) {
        throw new Exception('基地不存在');
    }
    $baseCode = $base['code'];
    $areaCode = $areaTypeCodes[$areaType] ?? 'X';
    return $baseCode . '-' . $areaCode . '-' . $rackName;
}
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $baseId = (int)($_POST['base_id'] ?? 0);
            $areaType = $_POST['area_type'] ?? '';
            $status = $_POST['status'] ?? 'normal';
            if (empty($name) || $baseId <= 0 || empty($areaType)) {
                throw new Exception('请填写所有必填字段');
            }
            // 自动生成编码
            $code = generateRackCode($baseId, $areaType, $name);
            // 检查编码是否已存在
            $existing = fetchRow("SELECT id FROM storage_racks WHERE code = ?", [$code]);
            if ($existing) {
                throw new Exception('该库位架编码已存在，请使用不同的库位名称');
            }
            insert('storage_racks', [
                'code' => $code,
                'name' => $name,
                'base_id' => $baseId,
                'area_type' => $areaType,
                'status' => $status,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $message = '库位架添加成功！编码：' . $code;
            $messageType = 'success';
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $baseId = (int)($_POST['base_id'] ?? 0);
            $areaType = $_POST['area_type'] ?? '';
            $status = $_POST['status'] ?? 'normal';

            if (empty($name) || $baseId <= 0 || empty($areaType) || $id <= 0) {
                throw new Exception('参数错误');
            }

            // 重新生成编码
            $code = generateRackCode($baseId, $areaType, $name);

            // 检查编码是否已存在（排除当前记录）
            $existing = fetchRow("SELECT id FROM storage_racks WHERE code = ? AND id != ?", [$code, $id]);
            if ($existing) {
                throw new Exception('该库位架编码已存在，请使用不同的库位名称');
            }

            query(
                "UPDATE storage_racks SET code = ?, name = ?, base_id = ?, area_type = ?, status = ?, updated_at = ? WHERE id = ?",
                [$code, $name, $baseId, $areaType, $status, date('Y-m-d H:i:s'), $id]
            );

            $message = '库位架更新成功！编码：' . $code;
            $messageType = 'success';
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('参数错误');
            }

            // 检查是否有关联的原片包
            $packages = fetchRow("SELECT COUNT(*) as count FROM glass_packages WHERE current_rack_id = ?", [$id]);
            if ($packages['count'] > 0) {
                throw new Exception('该库位架下还有原片包，无法删除');
            }

            query("DELETE FROM storage_racks WHERE id = ?", [$id]);

            $message = '库位架删除成功！';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = '错误：' . $e->getMessage();
        $messageType = 'error';
    }
}


// 获取筛选参数
$baseFilter = $_GET['base'] ?? '';
$areaTypeFilter = $_GET['area_type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$sortBy = $_GET['sort'] ?? 'id';
$sortOrder = $_GET['order'] ?? 'asc';

// 验证排序参数
$allowedSortFields = ['id', 'code', 'name', 'base_name', 'area_type'];
if (!in_array($sortBy, $allowedSortFields)) {
    $sortBy = 'id';
}
if (!in_array($sortOrder, ['asc', 'desc'])) {
    $sortOrder = 'asc';
}

// 构建查询条件
$whereConditions = [];
$params = [];

if (!empty($baseFilter)) {
    $whereConditions[] = "r.base_id = ?";
    $params[] = $baseFilter;
}

if (!empty($areaTypeFilter)) {
    $whereConditions[] = "r.area_type = ?";
    $params[] = $areaTypeFilter;
}

if (!empty($statusFilter)) {
    $whereConditions[] = "r.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $whereConditions[] = "(r.code LIKE ? OR r.name LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// 构建排序子句
$orderClause = "ORDER BY ";
if ($sortBy === 'base_name') {
    $orderClause .= "b.name $sortOrder";
} else {
    $orderClause .= "r.$sortBy $sortOrder";
}

// 获取库位架列表
$racks = fetchAll("SELECT r.*, b.name as base_name,
                         (SELECT COUNT(*) FROM glass_packages WHERE current_rack_id = r.id) as package_count
                  FROM storage_racks r 
                  LEFT JOIN bases b ON r.base_id = b.id 
                  $whereClause 
                  $orderClause", $params);

// 获取基地列表（包含代号）
$bases = fetchAll("SELECT id, name, code FROM bases ORDER BY name");

// 获取基地和区域的树形数据
$baseAreaTree = [];
foreach ($bases as $base) {
    $baseAreaTree[$base['id']] = [
        'name' => $base['name'],
        'code' => $base['code'],
        'areas' => []
    ];

    foreach ($areaTypes as $areaKey => $areaName) {
        // 获取该基地该区域下的库位架数量
        $rackCount = fetchRow("SELECT COUNT(*) as count FROM storage_racks WHERE base_id = ? AND area_type = ?", [$base['id'], $areaKey]);
        $baseAreaTree[$base['id']]['areas'][$areaKey] = [
            'name' => $areaName,
            'rack_count' => $rackCount['count']
        ];
    }
}

// 状态选项
$statusOptions = [
    'normal' => '正常',
    'maintenance' => '维护中',
    'full' => '已满'
];

// 获取编辑的记录
$editRecord = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editId = (int)$_GET['id'];
    $editRecord = fetchRow("SELECT * FROM storage_racks WHERE id = ?", [$editId]);
}

// 排序链接生成函数
function getSortUrl($field, $currentSort, $currentOrder)
{
    $params = $_GET;
    $params['sort'] = $field;

    if ($currentSort === $field) {
        $params['order'] = $currentOrder === 'asc' ? 'desc' : 'asc';
    } else {
        $params['order'] = 'asc';
    }

    return '?' . http_build_query($params);
}

// 排序图标函数
function getSortIcon($field, $currentSort, $currentOrder)
{
    if ($currentSort !== $field) {
        return '<i class="sort-icon">⇅</i>';
    }
    return $currentOrder === 'asc' ? '<i class="sort-icon sort-asc">↑</i>' : '<i class="sort-icon sort-desc">↓</i>';
}

ob_start();
?>

<style>
    /* 简化的排序样式 */
    .sortable-header {
        cursor: pointer;
        user-select: none;
        text-decoration: none;
        color: inherit;
    }

    .sortable-header:hover {
        background-color: #f8f9fa;
        text-decoration: none;
        color: inherit;
    }

    .sort-icon {
        margin-left: 5px;
        font-size: 12px;
        opacity: 0.6;
    }

    .sort-asc {
        color: #007bff;
        opacity: 1;
    }

    .sort-desc {
        color: #007bff;
        opacity: 1;
    }

    .tree-container {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 10px;
        background: #f9f9f9;
    }

    .base-node {
        margin-bottom: 5px;
    }

    .tree-node {
        padding: 8px 12px;
        cursor: pointer;
        border-radius: 4px;
        transition: background-color 0.2s;
    }

    .tree-node:hover {
        background-color: #e9ecef;
    }

    .base-node.active .tree-node {
        background-color: #007bff;
        color: white;
    }

    .tree-toggle {
        display: inline-block;
        width: 20px;
        font-weight: bold;
    }

    .base-name {
        font-weight: bold;
        margin-right: 8px;
    }

    .base-code {
        color: #666;
        font-size: 0.9em;
    }

    .area-list {
        margin-left: 20px;
        margin-top: 5px;
        display: none;
        transition: all 0.3s ease;
    }

    .area-list.show {
        display: block;
    }

    .area-node {
        padding: 6px 12px;
        margin: 2px 0;
        cursor: pointer;
        border-radius: 4px;
        background: white;
        border: 1px solid #e9ecef;
        transition: all 0.2s;
    }

    .area-node:hover {
        background-color: #f8f9fa;
        border-color: #007bff;
    }

    .area-node.selected {
        background-color: #007bff;
        color: white;
        border-color: #0056b3;
    }

    .area-name {
        font-weight: 500;
    }

    .area-count {
        color: #666;
        font-size: 0.85em;
        margin-left: 8px;
    }

    .area-node.selected .area-count {
        color: rgba(255, 255, 255, 0.8);
    }

    .selected-info {
        display: none;
        padding: 15px;
        background: #e8f5e8;
        border: 1px solid #c3e6c3;
        border-radius: 4px;
        margin: 15px 0;
    }

    .selected-info.show {
        display: block;
    }

    .code-preview {
        padding: 10px;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 4px;
        font-family: 'Courier New', monospace;
    }

    .required {
        color: #e74c3c;
    }
</style>
<div style="margin-bottom: 0px;margin-top:35px;position: absolute;right: 100px;">
    <button type="button" class="btn btn-success" onclick="showAddForm()">新增库位架</button>
</div>
<div class="search-form">
    <form method="GET" action="">
        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortBy); ?>">
        <input type="hidden" name="order" value="<?php echo htmlspecialchars($sortOrder); ?>">
        <!-- 新增库位架按钮 -->
        <div class="search-row-compact">
            <div class="form-group-compact">
                <label for="base">基地:</label>
                <select id="base" name="base" class="form-control-compact">
                    <option value="">全部基地</option>
                    <?php foreach ($bases as $base): ?>
                        <option value="<?php echo $base['id']; ?>" <?php echo $baseFilter == $base['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($base['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group-compact">
                <label for="area_type">区域类型:</label>
                <select id="area_type" name="area_type" class="form-control-compact">
                    <option value="">全部类型</option>
                    <?php foreach ($areaTypes as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $areaTypeFilter === $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group-compact">
                <label for="status">状态:</label>
                <select id="status" name="status" class="form-control-compact">
                    <option value="">全部状态</option>
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?php echo $value; ?>" <?php echo $statusFilter === $value ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group-compact">
                <label for="search">搜索:</label>
                <input type="text" id="search" name="search" class="form-control-compact"
                    value="<?php echo htmlspecialchars($search); ?>" placeholder="编码或名称">
            </div>

            <div class="form-group-compact">
                <button type="submit" class="btn btn-primary btn-compact">搜索</button>
                <a href="racks.php" class="btn btn-secondary btn-compact">重置</a>
            </div>
        </div>
    </form>
</div>

<!-- 库位架数据表格 -->
<div class="table-container">
    <table class="table" data-table="racks">
        <thead>
            <tr>
                <th><a href="<?php echo getSortUrl('id', $sortBy, $sortOrder); ?>" class="sortable-header">
                        ID <?php echo getSortIcon('id', $sortBy, $sortOrder); ?>
                    </a></th>
                <th>编码</th>
                <th><a href="<?php echo getSortUrl('name', $sortBy, $sortOrder); ?>" class="sortable-header">
                        名称 <?php echo getSortIcon('name', $sortBy, $sortOrder); ?>
                    </a></th>
                <th><a href="<?php echo getSortUrl('base_name', $sortBy, $sortOrder); ?>" class="sortable-header">
                        基地 <?php echo getSortIcon('base_name', $sortBy, $sortOrder); ?>
                    </a></th>
                <th><a href="<?php echo getSortUrl('area_type', $sortBy, $sortOrder); ?>" class="sortable-header">
                        区域类型 <?php echo getSortIcon('area_type', $sortBy, $sortOrder); ?>
                    </a></th>
                <th>状态</th>
                <th>包数量</th>
                <th>创建时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($racks)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; color: #666;">暂无记录</td>
                </tr>
            <?php else: ?>
                <?php foreach ($racks as $rack): ?>
                    <tr>
                        <td><?php echo $rack['id']; ?></td>
                        <td><?php echo htmlspecialchars($rack['code']); ?></td>
                        <td><?php echo htmlspecialchars($rack['name']); ?></td>
                        <td><?php echo htmlspecialchars($rack['base_name']); ?></td>
                        <td><?php echo htmlspecialchars($areaTypes[$rack['area_type']]); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $rack['status']; ?>">
                                <?php echo htmlspecialchars($statusOptions[$rack['status']]); ?>
                            </span>
                        </td>
                        <td><?php echo $rack['package_count']; ?></td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($rack['created_at'])); ?></td>
                        <td>
                            <a href="racks.php?action=edit&id=<?php echo $rack['id']; ?>" class="btn btn-sm btn-primary">编辑</a>
                            <button onclick="deleteRecord(<?php echo $rack['id']; ?>)" class="btn btn-sm btn-danger">删除</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<!-- 表单模态框 -->
<div id="formContainer" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="formTitle">新增库位架</h3>
            <button type="button" class="modal-close" onclick="hideForm()">&times;</button>

        </div>
        <div id="formMessage">
            <?php if ($message): ?>
                <div id="messageContainer" style="margin-bottom: 20px;">
                    <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="close" onclick="this.parentElement.parentElement.style.display='none'">&times;</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="modal-body">
            <!-- 树形选择器 -->
            <div id="treeSelector" class="tree-selector">
                <h4>选择基地和区域类型</h4>
                <div class="tree-container">
                    <?php foreach ($bases as $base): ?>
                        <div class="base-node" data-base-id="<?php echo $base['id']; ?>" data-base-name="<?php echo htmlspecialchars($base['name']); ?>" data-base-code="<?php echo htmlspecialchars($base['code']); ?>">
                            <div class="tree-node">
                                <span class="tree-toggle">▼</span>
                                <span class="base-name"><?php echo htmlspecialchars($base['name']); ?></span>
                                <span class="base-code">(<?php echo htmlspecialchars($base['code']); ?>)</span>
                            </div>
                            <div class="area-list">
                                <?php foreach ($areaTypes as $areaType => $areaLabel): ?>
                                    <div class="area-node" data-area-type="<?php echo $areaType; ?>" data-area-label="<?php echo htmlspecialchars($areaLabel); ?>">
                                        <span class="area-name"><?php echo htmlspecialchars($areaLabel); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="selectedInfo" class="selected-info">
                    <p><strong>已选择：</strong><span id="selectedText"></span></p>
                    <button type="button" class="btn btn-secondary" onclick="backToTreeSelector()">重新选择</button>
                </div>
            </div>

            <!-- 表单区域 -->
            <div id="formArea" class="form-area" style="display: none;">
                <form id="rackForm" method="POST" action="">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="recordId" value="">
                    <input type="hidden" name="base_id" id="baseId" value="">
                    <input type="hidden" name="area_type" id="areaType" value="">

                    <div class="form-group">
                        <label for="name" class="required">名称:</label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>

                    <div class="form-group" style="display: none;">
                        <label for="description">描述:</label>
                        <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="status">状态:</label>
                        <select id="statusSelect" name="status" class="form-control">
                            <?php foreach ($statusOptions as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="codePreview" class="code-preview" style="display: none;">
                        <label>预览编码:</label>
                        <div id="previewCode" class="code-display"></div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="hideForm()">取消</button>
                        <button type="submit" class="btn btn-primary">保存</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 检查是否有编辑记录，如果有则自动弹出编辑表单 -->
<?php if ($editRecord): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 设置表单为编辑模式
            document.getElementById('formTitle').textContent = '编辑库位架';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('recordId').value = '<?php echo $editRecord['id']; ?>';

            // 预填充表单数据
            document.getElementById('name').value = '<?php echo htmlspecialchars($editRecord['name']); ?>';
            document.getElementById('description').value = '<?php echo htmlspecialchars($editRecord['description'] ?? ''); ?>';
            document.getElementById('statusSelect').value = '<?php echo $editRecord['status']; ?>';
            document.getElementById('baseId').value = '<?php echo $editRecord['base_id']; ?>';
            document.getElementById('areaType').value = '<?php echo $editRecord['area_type']; ?>';

            // 跳过树形选择器，直接显示表单
            document.getElementById('treeSelector').style.display = 'none';
            document.getElementById('formArea').style.display = 'block';

            // 显示模态框
            document.getElementById('formContainer').style.display = 'flex';
        });
    </script>
<?php endif; ?>
<script>
    let selectedBaseId = null;
    let selectedAreaType = null;
    let selectedBaseName = '';
    let selectedBaseCode = '';

    // 基地数据映射
    const basesData = <?php echo json_encode(array_column($bases, null, 'id')); ?>;
    // 区域类型代码映射
    const areaTypeCodes = {
        'storage': 'N', // 库存区
        'processing': 'P', // 加工区
        'temporary': 'T', // 临时区
        'scrap': 'B' // 报废区
    };
    <?php if ($editRecord): ?>
        // 页面加载时自动显示编辑表单
        document.addEventListener('DOMContentLoaded', function() {
            showEditForm(<?php echo json_encode($editRecord); ?>);
        });
    <?php endif; ?>

    function backToTreeSelector() {
        document.getElementById('treeSelector').style.display = 'block';
        document.getElementById('formArea').style.display = 'none';
        document.getElementById('selectedInfo').classList.remove('show');
        document.getElementById('codePreview').style.display = 'none';
    }

    function updateRackCode() {
    const name = document.getElementById('name').value.trim();
    if (name && selectedBaseId && selectedAreaType) {
        const areaTypeCodes = {
            'storage': 'N',
            'processing': 'P', 
            'temporary': 'T',
            'scrap': 'B'
        };
        const areaCode = areaTypeCodes[selectedAreaType] || 'X';
        const code = `${selectedBaseCode}-${areaCode}-${name}`;
        document.getElementById('previewCode').textContent = code;
        document.getElementById('codePreview').style.display = 'block';
    } else {
        document.getElementById('codePreview').style.display = 'none';
    }
}

    function selectArea(baseId, areaType, baseName, areaName, baseCode) {
        selectedBaseId = baseId;
        selectedAreaType = areaType;
        selectedBaseName = baseName;
        selectedBaseCode = baseCode;
        document.querySelectorAll('.area-node').forEach(node => {
            node.classList.remove('selected');
        });
        event.target.classList.add('selected');
        // 修正：selectedPath -> selectedText
        document.getElementById('selectedText').textContent = baseName + ' > ' + areaName;
        document.getElementById('selectedInfo').classList.add('show');
        // 修正：form_base_id -> baseId
        document.getElementById('baseId').value = baseId;
        // 修正：form_area_type -> areaType
        document.getElementById('areaType').value = areaType;
        setTimeout(() => {
            document.getElementById('treeSelector').style.display = 'none';
            // 修正：rackForm -> formArea
            document.getElementById('formArea').style.display = 'block';
            document.getElementById('name').focus();
            updateRackCode();
        }, 300);
    }


    function showAddForm() {
        clearMessages();
        document.getElementById('formTitle').textContent = '新增库位架';
        document.getElementById('formAction').value = 'add';
        document.getElementById('recordId').value = '';
        document.getElementById('treeSelector').style.display = 'block';
        document.getElementById('formArea').style.display = 'none';
        document.getElementById('formContainer').style.display = 'flex';
        resetSelection();
    }

    function showEditForm(record) {
        isEditMode = true;
        document.getElementById('formContainer').style.display = 'flex';
        document.querySelector('input[name="action"]').value = 'edit';
        document.getElementById('formTitle').textContent = '编辑库位架';
        let idField = document.querySelector('input[name="id"]');
        if (!idField) {
            idField = document.createElement('input');
            idField.type = 'hidden';
            idField.name = 'id';
            document.getElementById('rackForm').appendChild(idField);
        }
        idField.value = record.id;
        selectedBaseId = record.base_id;
        selectedAreaType = record.area_type;
        const baseInfo = basesData[record.base_id];
        if (baseInfo) {
            selectedBaseName = baseInfo.name;
            selectedBaseCode = baseInfo.code;
        }
        document.getElementById('treeSelector').style.display = 'none';
        document.getElementById('formArea').style.display = 'block';
        document.getElementById('selectedInfo').classList.add('show');
        const areaTypes = {
            'storage': '库存区',
            'processing': '加工区',
            'temporary': '临时区',
            'scrap': '报废区'
        };
        document.getElementById('selectedText').textContent = selectedBaseName + ' > ' + areaTypes[selectedAreaType];
        document.getElementById('baseId').value = record.base_id;
        document.getElementById('areaType').value = record.area_type;
        document.getElementById('name').value = record.name;
        document.getElementById('statusSelect').value = record.status;
        if (record.description) {
            document.getElementById('description').value = record.description;
        }
        document.getElementById('previewCode').textContent = record.code;
        document.getElementById('codePreview').style.display = 'block';
    }
    function hideForm() {
        clearMessages();
        document.getElementById('formContainer').style.display = 'none';
        document.getElementById('treeSelector').style.display = 'block';
        document.getElementById('formArea').style.display = 'none';
        document.getElementById('formTitle').textContent = '新增库位架';
        document.getElementById('formAction').value = 'add';
        document.getElementById('recordId').value = '';
        document.getElementById('rackForm').reset();
        resetSelection();
        const url = new URL(window.location);
        url.searchParams.delete('action');
        url.searchParams.delete('id');
        window.history.replaceState({}, document.title, url.pathname + url.search);
    }

    function toggleBase(baseId) {
        const areaList = document.getElementById('areas-' + baseId);
        const toggle = document.getElementById('toggle-' + baseId);
        const baseNode = toggle.parentElement;
        document.querySelectorAll('.area-list').forEach(list => {
            if (list.id !== 'areas-' + baseId) {
                list.classList.remove('show');
            }
        });
        document.querySelectorAll('.base-node').forEach(node => {
            if (node !== baseNode) {
                node.classList.remove('active');
                const otherToggle = node.querySelector('.tree-toggle');
                if (otherToggle) otherToggle.textContent = '▼';
            }
        });
        if (areaList.classList.contains('show')) {
            areaList.classList.remove('show');
            baseNode.classList.remove('active');
            toggle.textContent = '▼';
        } else {
            areaList.classList.add('show');
            baseNode.classList.add('active');
            toggle.textContent = '▲';
        }
    }

    // 修正backToTreeSelector函数中的DOM元素引用错误
    function backToTreeSelector() {
        document.getElementById('treeSelector').style.display = 'block';
        document.getElementById('formArea').style.display = 'none';
        document.getElementById('rackForm').reset();
        document.getElementById('codePreview').style.display = 'none';
    }

    function resetSelection() {
        selectedBaseId = null;
        selectedAreaType = null;
        selectedBaseName = '';
        selectedBaseCode = '';

        document.querySelectorAll('.base-node').forEach(node => {
            node.classList.remove('active');
        });

        document.querySelectorAll('.area-list').forEach(list => {
            list.classList.remove('show');
        });

        document.querySelectorAll('.area-node').forEach(node => {
            node.classList.remove('selected');
        });

        document.querySelectorAll('.tree-toggle').forEach(toggle => {
            toggle.textContent = '▼';
        });

        document.getElementById('selectedInfo').classList.remove('show');
        document.getElementById('codePreview').style.display = 'none';
    }

    function editRecord(id) {
        // 通过 AJAX 获取记录数据
        fetch(`?action=get_record&id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const record = data.record;
                    // 设置表单为编辑模式
                    document.getElementById('formTitle').textContent = '编辑库位架';
                    document.getElementById('formAction').value = 'edit';
                    document.getElementById('recordId').value = record.id;

                    // 预填充表单数据
                    document.getElementById('name').value = record.name;
                    document.getElementById('description').value = record.description || '';
                    document.getElementById('statusSelect').value = record.status;
                    document.getElementById('baseId').value = record.base_id;
                    document.getElementById('areaType').value = record.area_type;

                    // 跳过树形选择器，直接显示表单
                    document.getElementById('treeSelector').style.display = 'none';
                    document.getElementById('formArea').style.display = 'block';

                    // 显示模态框
                    document.getElementById('formContainer').style.display = 'flex';

                    // 清理 URL 参数
                    const url = new URL(window.location);
                    url.searchParams.delete('action');
                    url.searchParams.delete('id');
                    window.history.replaceState({}, document.title, url.pathname + url.search);
                } else {
                    alert('获取记录失败：' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('获取记录时发生错误');
            });
    }

    function deleteRecord(id) {
        if (confirm('确定要删除这个库位架吗？此操作不可恢复。')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    // 新增清除消息的函数
    function clearMessages() {
        const messageContainer = document.getElementById('messageContainer');
        if (messageContainer) {
            messageContainer.style.display = 'none';
        }

        const formMessage = document.getElementById('formMessage');
        if (formMessage) {
            formMessage.innerHTML = '';
        }
    }
    // 添加事件监听器
    document.addEventListener('DOMContentLoaded', function() {
        // 基地节点点击事件
        document.querySelectorAll('.base-node').forEach(node => {
            const toggle = node.querySelector('.tree-toggle');
            const areaList = node.querySelector('.area-list');

            node.addEventListener('click', function(e) {
                if (e.target === toggle || e.target.closest('.tree-node')) {
                    e.stopPropagation();
                    const isExpanded = areaList.classList.contains('show');

                    // 关闭所有其他展开的节点
                    document.querySelectorAll('.area-list').forEach(list => {
                        list.classList.remove('show');
                    });
                    document.querySelectorAll('.tree-toggle').forEach(t => {
                        t.textContent = '▼';
                    });

                    if (!isExpanded) {
                        areaList.classList.add('show');
                        toggle.textContent = '▲';
                    }
                }
            });
        });

        // 区域节点点击事件
        document.querySelectorAll('.area-node').forEach(node => {
            node.addEventListener('click', function() {
                const baseNode = this.closest('.base-node');
                const baseId = baseNode.dataset.baseId;
                const baseName = baseNode.dataset.baseName;
                const baseCode = baseNode.dataset.baseCode;
                const areaType = this.dataset.areaType;
                const areaLabel = this.dataset.areaLabel;
                selectArea(baseId, areaType, baseName, areaLabel, baseCode);
            });
        });
        const successMessage = document.querySelector('.alert-success');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.opacity = '0';
                setTimeout(() => {
                    successMessage.style.display = 'none';
                }, 300);
            }, 3000);
        }
        // 名称输入框变化事件
        document.getElementById('name').addEventListener('input', updateRackCode);
    });
</script>
</body>

</html>
<?php
$content = ob_get_clean();
echo renderAdminLayout('库位架管理', $content, $currentUser, 'racks.php', [], [], $message ?? '', $messageType ?? 'info');
?>