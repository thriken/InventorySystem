<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/admin_layout.php';

// 要求用户登录
requireLogin();

// 检查是否为管理员
requireRole(['admin']);

// 获取当前用户信息
$currentUser = getCurrentUser();

// 处理表单提交
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if (empty($name)) {
                throw new Exception('基地名称不能为空');
            }

            if (empty($code)) {
                throw new Exception('基地编码不能为空');
            }

            // 检查名称是否已存在
            $existing = fetchRow("SELECT id FROM bases WHERE name = ?", [$name]);
            if ($existing) {
                throw new Exception('该基地名称已存在');
            }

            // 检查编码是否已存在
            $existing = fetchRow("SELECT id FROM bases WHERE code = ?", [$code]);
            if ($existing) {
                throw new Exception('该基地编码已存在');
            }

            insert('bases', [
                'name' => $name,
                'code' => $code,
                'address' => $address,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $message = '基地添加成功！';
            $messageType = 'success';
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $address = trim($_POST['address'] ?? '');

            if (empty($name) || empty($code) || $id <= 0) {
                throw new Exception('参数错误');
            }

            // 检查名称是否已存在（排除当前记录）
            $existing = fetchRow("SELECT id FROM bases WHERE name = ? AND id != ?", [$name, $id]);
            if ($existing) {
                throw new Exception('该基地名称已存在');
            }

            // 检查编码是否已存在（排除当前记录）
            $existing = fetchRow("SELECT id FROM bases WHERE code = ? AND id != ?", [$code, $id]);
            if ($existing) {
                throw new Exception('该基地编码已存在');
            }

            query(
                "UPDATE bases SET name = ?, code = ?, address = ?, updated_at = ? WHERE id = ?",
                [$name, $code, $address, date('Y-m-d H:i:s'), $id]
            );

            $message = '基地更新成功！';
            $messageType = 'success';
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('参数错误');
            }

            // 检查是否有关联的库位架
            $racks = fetchRow("SELECT COUNT(*) as count FROM storage_racks WHERE base_id = ?", [$id]);
            if ($racks['count'] > 0) {
                throw new Exception('该基地下还有库位架，无法删除');
            }

            // 检查是否有关联的用户
            $users = fetchRow("SELECT COUNT(*) as count FROM users WHERE base_id = ?", [$id]);
            if ($users['count'] > 0) {
                throw new Exception('该基地下还有用户，无法删除');
            }

            query("DELETE FROM bases WHERE id = ?", [$id]);

            $message = '基地删除成功！';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = '错误：' . $e->getMessage();
        $messageType = 'error';
    }
}

// 获取基地列表
$bases = fetchAll("SELECT b.*, 
                         (SELECT COUNT(*) FROM glass_packages gp 
                          JOIN storage_racks sr ON gp.current_rack_id = sr.id 
                          WHERE sr.base_id = b.id) as package_count,
                         (SELECT COALESCE(SUM(gp.quantity * gp.width * gp.height / 1000000), 0) 
                          FROM glass_packages gp 
                          JOIN storage_racks sr ON gp.current_rack_id = sr.id 
                          JOIN glass_types gt ON gp.glass_type_id = gt.id 
                          WHERE sr.base_id = b.id) as total_area,
                         (SELECT COUNT(*) FROM users WHERE base_id = b.id) as user_count
                  FROM bases b 
                  ORDER BY b.created_at DESC");

// 获取编辑的记录
$editRecord = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editRecord = fetchRow("SELECT * FROM bases WHERE id = ?", [$editId]);
}
ob_start();
?>
<div class="admin-header">
    <button type="button" class="btn btn-success" onclick="showAddForm()">添加基地</button>
</div>
<!-- 添加/编辑表单 -->
<div class="form-container" id="formContainer" style="display: <?php echo $editRecord ? 'block' : 'none'; ?>">
    <div class="form-header">
        <h3><?php echo $editRecord ? '编辑基地' : '添加基地'; ?></h3>
        <button class="close-btn" onclick="hideForm()">&times;</button>
    </div>
    <form method="POST" action="">
        <input type="hidden" name="action" value="<?php echo $editRecord ? 'edit' : 'add'; ?>">
        <?php if ($editRecord): ?>
            <input type="hidden" name="id" value="<?php echo $editRecord['id']; ?>">
        <?php endif; ?>

        <div class="form-group">
            <label for="name">基地名称 *</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($editRecord['name'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label for="code">基地编码 *</label>
            <input type="text" id="code" name="code" value="<?php echo htmlspecialchars($editRecord['code'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label for="address">地址</label>
            <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($editRecord['address'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="description">描述</label>
            <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($editRecord['description'] ?? ''); ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?php echo $editRecord ? '更新' : '添加'; ?></button>
            <button type="button" class="btn btn-secondary" onclick="hideForm()">取消</button>
        </div>
    </form>
</div>

<!-- 数据表格 -->
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>名称</th>
                <th>编码</th>
                <th>地址</th>
                <th>原片包数</th>
                <th>总面积(㎡)</th>
                <th>用户数</th>
                <th>创建时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bases as $base): ?>
                <tr>
                    <td><?php echo $base['id']; ?></td>
                    <td><?php echo htmlspecialchars($base['name']); ?></td>
                    <td><?php echo htmlspecialchars($base['code']); ?></td>
                    <td><?php echo htmlspecialchars($base['address']); ?></td>
                    <td><?php echo $base['package_count']; ?></td>
                    <td><?php echo number_format($base['total_area'], 2); ?></td>
                    <td><?php echo $base['user_count']; ?></td>
                    <td><?php echo formatDateTime($base['created_at']); ?></td>
                    <td>
                        <a href="?edit=<?php echo $base['id']; ?>" class="btn btn-sm btn-info">编辑</a>
                        <button onclick="deleteRecord(<?php echo $base['id']; ?>)" class="btn btn-sm btn-danger">删除</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
    function showAddForm() {
        document.getElementById('formContainer').style.display = 'block';
        document.querySelector('input[name="action"]').value = 'add';
        document.querySelector('.form-header h3').textContent = '添加基地';
        document.querySelector('form').reset();
        // 移除隐藏的id字段
        const idField = document.querySelector('input[name="id"]');
        if (idField) idField.remove();
    }

    function hideForm() {
        document.getElementById('formContainer').style.display = 'none';
    }

    function deleteRecord(id) {
        if (confirm('确定要删除这个基地吗？此操作不可恢复。')) {
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
</script>
</body>

</html>
<?php
$content = ob_get_clean();
// 渲染页面
echo renderAdminLayout('基地管理', $content, $currentUser, 'bases.php', [], [], $message ?? '', $messageType ?? 'info');

?>