<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/admin_layout.php';

// 要求用户登录
requireLogin();

// 检查是否为管理员
requireRole(['admin', 'manager']);

// 获取当前用户信息
$currentUser = getCurrentUser();

// 处理表单提交
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $category = trim($_POST['category'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
            $sortOrder = intval($_POST['sort_order'] ?? 0);
            
            // 验证必填字段
            if (empty($category)) {
                throw new Exception('分类不能为空');
            }
            if (empty($code)) {
                throw new Exception('代码不能为空');
            }
            if (empty($name)) {
                throw new Exception('名称不能为空');
            }
            
            // 检查代码是否已存在
            $existing = fetchRow("SELECT id FROM dictionary_items WHERE category = ? AND code = ?", [$category, $code]);
            if ($existing) {
                throw new Exception('该分类下的代码已存在');
            }
            
            // 插入新记录
            insert('dictionary_items', [
                'category' => $category,
                'code' => $code,
                'name' => $name,
                'parent_id' => $parentId,
                'sort_order' => $sortOrder,
                'status' => 1
            ]);
            
            $message = '字典项添加成功';
            $messageType = 'success';
            
        } elseif ($action === 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $category = trim($_POST['category'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
            $sortOrder = intval($_POST['sort_order'] ?? 0);
            $status = intval($_POST['status'] ?? 1);
            
            if ($id <= 0) {
                throw new Exception('无效的记录ID');
            }
            
            // 验证必填字段
            if (empty($category)) {
                throw new Exception('分类不能为空');
            }
            if (empty($code)) {
                throw new Exception('代码不能为空');
            }
            if (empty($name)) {
                throw new Exception('名称不能为空');
            }
            
            // 检查代码是否已存在（排除当前记录）
            $existing = fetchRow("SELECT id FROM dictionary_items WHERE category = ? AND code = ? AND id != ?", [$category, $code, $id]);
            if ($existing) {
                throw new Exception('该分类下的代码已存在');
            }
            
            // 更新记录
            update('dictionary_items', [
                'category' => $category,
                'code' => $code,
                'name' => $name,
                'parent_id' => $parentId,
                'sort_order' => $sortOrder,
                'status' => $status
            ], 'id = ?', [$id]);
            
            $message = '字典项更新成功';
            $messageType = 'success';
            
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                throw new Exception('无效的记录ID');
            }
            
            // 检查是否有子项
            $hasChildren = fetchRow("SELECT COUNT(*) as count FROM dictionary_items WHERE parent_id = ?", [$id]);
            if ($hasChildren['count'] > 0) {
                throw new Exception('该项目下还有子项，无法删除');
            }
            
            // 软删除（设置状态为0）
            update('dictionary_items', ['status' => 0], 'id = ?', [$id]);
            
            $message = '字典项删除成功';
            $messageType = 'success';
        }
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// 获取字典项列表
$sql = "SELECT d.*, p.name as parent_name 
        FROM dictionary_items d 
        LEFT JOIN dictionary_items p ON d.parent_id = p.id 
        ORDER BY d.category, d.sort_order, d.name";
$dictionaries = fetchAll($sql);

// 获取父级选项（用于添加/编辑表单）
$parentOptions = fetchAll("SELECT id, category, name FROM dictionary_items WHERE parent_id IS NULL ORDER BY category, sort_order, name");

// 页面内容
ob_start();
?>

<div class="admin-page">
    <!-- 添加按钮 -->
    <div class="action-section">
        <button type="button" class="btn btn-success" onclick="showAddModal()">添加字典项</button>
    </div>

    <!-- 字典项列表 -->
    <div class="table-section">
        <table id="dictionaryTable" class="admin-table display" data-table="dictionary" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>分类</th>
                    <th>代码</th>
                    <th>名称</th>
                    <th>父级</th>
                    <th>排序</th>
                    <th>状态</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dictionaries as $dict): ?>
                <tr>
                    <td><?php echo $dict['id']; ?></td>
                    <td>
                        <?php 
                        $categoryNames = [
                            'brand' => '品牌',
                            'manufacturer' => '生产商',
                            'color' => '颜色'
                        ];
                        echo $categoryNames[$dict['category']] ?? $dict['category'];
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($dict['code']); ?></td>
                    <td><?php echo htmlspecialchars($dict['name']); ?></td>
                    <td><?php echo htmlspecialchars($dict['parent_name'] ?? '-'); ?></td>
                    <td><?php echo $dict['sort_order']; ?></td>
                    <td>
                        <span class="status-badge <?php echo $dict['status'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $dict['status'] ? '启用' : '禁用'; ?>
                        </span>
                    </td>
                    <td><?php echo date('Y-m-d H:i', strtotime($dict['created_at'])); ?></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($dict)); ?>)">编辑</button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteDictionary(<?php echo $dict['id']; ?>)">删除</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 添加/编辑模态框 -->
<div id="dictionaryModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">添加字典项</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form id="dictionaryForm" method="POST">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId" value="">
            
            <div class="form-group">
                <label for="formCategory">分类 <span class="required">*</span></label>
                <select name="category" id="formCategory" required>
                    <option value="">请选择分类</option>
                    <option value="brand">品牌</option>
                    <option value="manufacturer">生产商</option>
                    <option value="color">颜色</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="formCode">代码 <span class="required">*</span></label>
                <input type="text" name="code" id="formCode" required maxlength="50">
            </div>
            
            <div class="form-group">
                <label for="formName">名称 <span class="required">*</span></label>
                <input type="text" name="name" id="formName" required maxlength="100">
            </div>
            
            <div class="form-group">
                <label for="formParentId">父级</label>
                <select name="parent_id" id="formParentId">
                    <option value="">无父级</option>
                    <?php foreach ($parentOptions as $parent): ?>
                    <option value="<?php echo $parent['id']; ?>" data-category="<?php echo $parent['category']; ?>">
                        <?php echo htmlspecialchars($parent['name']); ?> (<?php echo $parent['category']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="formSortOrder">排序</label>
                <input type="number" name="sort_order" id="formSortOrder" value="0" min="0">
            </div>
            
            <div class="form-group" id="statusGroup" style="display: none;">
                <label for="formStatus">状态</label>
                <select name="status" id="formStatus">
                    <option value="1">启用</option>
                    <option value="0">禁用</option>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">保存</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">取消</button>
            </div>
        </form>
    </div>
</div>

<script>
// 显示添加模态框
function showAddModal() {
    document.getElementById('modalTitle').textContent = '添加字典项';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('dictionaryForm').reset();
    document.getElementById('statusGroup').style.display = 'none';
    document.getElementById('dictionaryModal').style.display = 'block';
}

// 显示编辑模态框
function showEditModal(dict) {
    document.getElementById('modalTitle').textContent = '编辑字典项';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = dict.id;
    document.getElementById('formCategory').value = dict.category;
    document.getElementById('formCode').value = dict.code;
    document.getElementById('formName').value = dict.name;
    document.getElementById('formParentId').value = dict.parent_id || '';
    document.getElementById('formSortOrder').value = dict.sort_order;
    document.getElementById('formStatus').value = dict.status;
    document.getElementById('statusGroup').style.display = 'block';
    
    // 更新父级选项
    updateParentOptions(dict.category);
    
    document.getElementById('dictionaryModal').style.display = 'block';
}

// 关闭模态框
function closeModal() {
    document.getElementById('dictionaryModal').style.display = 'none';
}

// 删除字典项
function deleteDictionary(id) {
    if (confirm('确定要删除这个字典项吗？此操作不可恢复！')) {
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

// 根据分类更新父级选项
function updateParentOptions(category) {
    const parentSelect = document.getElementById('formParentId');
    const options = parentSelect.querySelectorAll('option[data-category]');
    
    options.forEach(option => {
        if (category === 'manufacturer' && option.dataset.category === 'brand') {
            option.style.display = 'block';
        } else if (category === 'brand' || category === 'color') {
            option.style.display = 'none';
        } else {
            option.style.display = 'block';
        }
    });
}

// 分类变化时更新父级选项
document.getElementById('formCategory').addEventListener('change', function() {
    updateParentOptions(this.value);
    document.getElementById('formParentId').value = '';
});

// 点击模态框外部关闭
window.onclick = function(event) {
    const modal = document.getElementById('dictionaryModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

<?php
$content = ob_get_clean();
// 额外的CSS和JS文件
$additionalCSS = [];
$additionalJS = [];
// 渲染页面
echo renderAdminLayout('字典管理', $content, $currentUser, 'dictionary.php', $additionalCSS, $additionalJS, $message, $messageType);
?>