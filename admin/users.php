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
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $realName = trim($_POST['real_name'] ?? '');
            $role = $_POST['role'] ?? '';
            $baseId = (int)($_POST['base_id'] ?? 0);
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (empty($username) || empty($password) || empty($realName) || empty($role)) {
                throw new Exception('请填写所有必填字段');
            }

            if (strlen($password) < 6) {
                throw new Exception('密码长度不能少于6位');
            }

            // 检查用户名是否已存在
            $existing = fetchRow("SELECT id FROM users WHERE username = ?", [$username]);
            if ($existing) {
                throw new Exception('该用户名已存在');
            }

            // 检查邮箱是否已存在
            if (!empty($email)) {
                $existing = fetchRow("SELECT id FROM users WHERE email = ?", [$email]);
                if ($existing) {
                    throw new Exception('该邮箱已被使用');
                }
            }

            // 插入新用户
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            query(
                "INSERT INTO users (username, password, real_name, role, base_id, phone, email, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
                [$username, $hashedPassword, $realName, $role, $baseId > 0 ? $baseId : null, $phone, $email]
            );

            $message = '用户添加成功！';
            $messageType = 'success';
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $realName = trim($_POST['real_name'] ?? '');
            $role = $_POST['role'] ?? '';
            $baseId = (int)($_POST['base_id'] ?? 0);
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $status = (int)($_POST['status'] ?? 1);

            if ($id <= 0 || empty($username) || empty($realName) || empty($role)) {
                throw new Exception('请填写所有必填字段');
            }

            // 检查用户名是否已被其他用户使用
            $existing = fetchRow("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $id]);
            if ($existing) {
                throw new Exception('该用户名已存在');
            }

            // 检查邮箱是否已被其他用户使用
            if (!empty($email)) {
                $existing = fetchRow("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $id]);
                if ($existing) {
                    throw new Exception('该邮箱已被使用');
                }
            }

            // 更新用户信息
            $updateData = [
                'username' => $username,
                'real_name' => $realName,
                'role' => $role,
                'base_id' => $baseId > 0 ? $baseId : null,
                'phone' => $phone,
                'email' => $email,
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            // 如果提供了新密码，则更新密码
            $newPassword = $_POST['new_password'] ?? '';
            if (!empty($newPassword)) {
                if (strlen($newPassword) < 6) {
                    throw new Exception('密码长度不能少于6位');
                }
                $updateData['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }

            $setParts = [];
            $params = [];
            foreach ($updateData as $key => $value) {
                $setParts[] = "$key = ?";
                $params[] = $value;
            }
            $params[] = $id;

            query("UPDATE users SET " . implode(', ', $setParts) . " WHERE id = ?", $params);

            $message = '用户更新成功！';
            $messageType = 'success';
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('参数错误');
            }

            // 不能删除自己
            if ($id == $currentUser['id']) {
                throw new Exception('不能删除自己的账户');
            }

            // 检查是否有关联的交易记录
            $transactions = fetchRow("SELECT COUNT(*) as count FROM inventory_transactions WHERE operator_id = ?", [$id]);
            if ($transactions['count'] > 0) {
                throw new Exception('该用户有交易记录，无法删除');
            }

            query("DELETE FROM users WHERE id = ?", [$id]);

            $message = '用户删除成功！';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = '错误：' . $e->getMessage();
        $messageType = 'error';
    }
}

// 获取筛选参数
$roleFilter = $_GET['role'] ?? '';
$baseFilter = $_GET['base'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// 构建查询条件
$whereConditions = [];
$params = [];

if (!empty($roleFilter)) {
    $whereConditions[] = "u.role = ?";
    $params[] = $roleFilter;
}

if (!empty($baseFilter)) {
    $whereConditions[] = "u.base_id = ?";
    $params[] = $baseFilter;
}

if (!empty($statusFilter)) {
    $whereConditions[] = "u.status = ?";
    $params[] = $statusFilter;
}

if (!empty($search)) {
    $whereConditions[] = "(u.username LIKE ? OR u.real_name LIKE ? OR u.email LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// 获取用户列表
$users = fetchAll("SELECT u.*, b.name as base_name 
                  FROM users u 
                  LEFT JOIN bases b ON u.base_id = b.id 
                  $whereClause 
                  ORDER BY u.created_at DESC", $params);

// 获取基地列表
$bases = fetchAll("SELECT id, name FROM bases ORDER BY name");

// 角色选项
$roleOptions = [
    'admin' => '管理员',
    'manager' => '库管',
    'operator' => '操作员',
    'viewer' => '查看者'
];

// 状态选项
$statusOptions = [
    1 => '正常',
    0 => '禁用'
];

// 获取编辑的记录
$editRecord = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editRecord = fetchRow("SELECT * FROM users WHERE id = ?", [$editId]);
}

// 统计信息
$totalUsers = count($users);
$operatorCount = count(array_filter($users, function ($user) {
    return $user['role'] == 'operator';
}));
$adminCount = count(array_filter($users, function ($user) {
    return $user['role'] == 'admin';
}));
$managerCount = count(array_filter($users, function ($user) {
    return $user['role'] == 'manager';
}));

ob_start();
?>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?php echo $totalUsers; ?></div>
        <div class="stat-label">用户总数</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $adminCount; ?></div>
        <div class="stat-label">管理员</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $managerCount; ?></div>
        <div class="stat-label">库管</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $operatorCount; ?></div>
        <div class="stat-label">操作员</div>
    </div>
</div>
<div class="users-container">
    <h3>
        <button onclick="openAddModal()" class="btn btn-primary">添加用户</button>
    </h3>
    <!-- 添加用户模态框 -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>添加用户</h3>
                <button class="modal-close" onclick="closeAddModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addUserForm" method="POST" action="" class="user-form">
                    <input type="hidden" name="action" value="add">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_username">用户名 *</label>
                            <input type="text" id="add_username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="add_real_name">真实姓名 *</label>
                            <input type="text" id="add_real_name" name="real_name" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_password">密码 *</label>
                            <input type="password" id="add_password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="add_role">角色 *</label>
                            <select id="add_role" name="role" required>
                                <option value="">请选择角色</option>
                                <?php foreach ($roleOptions as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_base_id">所属基地</label>
                            <select id="add_base_id" name="base_id">
                                <option value="">请选择基地</option>
                                <?php foreach ($bases as $base): ?>
                                    <option value="<?php echo $base['id']; ?>"><?php echo htmlspecialchars($base['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="add_phone">联系电话</label>
                            <input type="text" id="add_phone" name="phone">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="add_email">邮箱</label>
                            <input type="email" id="add_email" name="email">
                        </div>
                        <div class="form-group"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="submit" form="addUserForm" class="btn btn-primary">添加用户</button>
                <button type="button" class="btn btn-secondary" onclick="closeAddModal()">取消</button>
            </div>
        </div>
    </div>
    <div class="dashboard-card">
        <!-- 筛选和搜索 --><h3>🔍 筛选和搜索</h3>
        
            <form method="GET" action="" class="filter-form">
                <div class="stats-grid">
                <div class="stat-card">
                    <label for="search">搜索</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="用户名、姓名或邮箱">
                </div>

                <div class="stat-card">
                    <label for="role">角色</label>
                    <select id="role" name="role">
                        <option value="">全部角色</option>
                        <?php foreach ($roleOptions as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $roleFilter === $value ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="stat-card">
                    <label for="base">基地</label>
                    <select id="base" name="base">
                        <option value="">全部基地</option>
                        <?php foreach ($bases as $base): ?>
                            <option value="<?php echo $base['id']; ?>" <?php echo $baseFilter == $base['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($base['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="stat-card">
                    <label for="status">状态</label>
                    <select id="status" name="status">
                        <option value="">全部状态</option>
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $statusFilter === $value ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">筛选</button>
                    <a href="users.php" class="btn btn-secondary">重置</a>
                </div>
             </div></form>
       
        <!-- 用户列表 -->

            <h3>👤 用户列表 (共 <?php echo $totalUsers; ?> 个用户)</h3>
            <?php if (empty($users)): ?>
                <div class="no-data">
                    <div class="no-data-content">
                        <i>👤</i>
                        <p>暂无用户数据</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="users-grid">
                    <?php foreach ($users as $user): ?>
                        <div class="user-card">
                            <div class="user-header">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['real_name'] ?: $user['username'], 0, 1)); ?>
                                </div>
                                <div class="user-info">
                                    <div class="user-name"><?php echo htmlspecialchars($user['real_name']); ?></div>
                                    <div class="user-username">@<?php echo htmlspecialchars($user['username']); ?></div>
                                </div>
                                <div class="user-status">
                                    <?php if ($user['status'] == 1): ?>
                                        <span class="label label-success">正常</span>
                                    <?php else: ?>
                                        <span class="label label-danger">禁用</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="user-details">
                                <div class="detail-item">
                                    <span class="detail-label">角色:</span>
                                    <span class="detail-value"><?php echo $roleOptions[$user['role']] ?? $user['role']; ?></span>
                                </div>

                                <?php if ($user['base_name']): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">基地:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($user['base_name']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($user['phone']): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">电话:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($user['phone']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($user['email']): ?>
                                    <div class="detail-item">
                                        <span class="detail-label">邮箱:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($user['email']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="detail-item">
                                    <span class="detail-label">最后登录:</span>
                                    <span class="detail-value"><?php echo $user['last_login'] ? formatDateTime($user['last_login']) : '从未登录'; ?></span>
                                </div>
                            </div>

                            <div class="user-actions">
                                <a href="?edit=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">编辑</a>
                                <?php if ($user['id'] != $currentUser['id']): ?>
                                    <button onclick="deleteUser(<?php echo $user['id']; ?>)" class="btn btn-sm btn-danger">删除</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    <!-- 编辑用户模态框 -->
    <?php if ($editRecord): ?>
        <div class="modal-overlay" id="editModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>编辑用户</h3>
                    <button class="modal-close" onclick="closeEditModal()">&times;</button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo $editRecord['id']; ?>">

                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_username">用户名 *</label>
                                <input type="text" id="edit_username" name="username" value="<?php echo htmlspecialchars($editRecord['username']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="edit_real_name">真实姓名 *</label>
                                <input type="text" id="edit_real_name" name="real_name" value="<?php echo htmlspecialchars($editRecord['real_name']); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_new_password">新密码（留空不修改）</label>
                                <input type="password" id="edit_new_password" name="new_password">
                            </div>

                            <div class="form-group">
                                <label for="edit_role">角色 *</label>
                                <select id="edit_role" name="role" required>
                                    <option value="">请选择角色</option>
                                    <?php foreach ($roleOptions as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" <?php echo $editRecord['role'] === $value ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_base_id">所属基地</label>
                                <select id="edit_base_id" name="base_id">
                                    <option value="">请选择基地</option>
                                    <?php foreach ($bases as $base): ?>
                                        <option value="<?php echo $base['id']; ?>" <?php echo $editRecord['base_id'] == $base['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($base['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="edit_status">状态</label>
                                <select id="edit_status" name="status">
                                    <?php foreach ($statusOptions as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" <?php echo $editRecord['status'] == $value ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_phone">联系电话</label>
                                <input type="text" id="edit_phone" name="phone" value="<?php echo htmlspecialchars($editRecord['phone']); ?>">
                            </div>

                            <div class="form-group">
                                <label for="edit_email">邮箱</label>
                                <input type="email" id="edit_email" name="email" value="<?php echo htmlspecialchars($editRecord['email']); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">更新</button>
                        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">取消</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
<script>
    // 关闭编辑模态框
    function closeEditModal() {
        const modal = document.getElementById('editModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    // 打开编辑模态框
    function openEditModal() {
        const modal = document.getElementById('editModal');
        if (modal) {
            modal.style.display = 'block';
        }
    }

    // 关闭添加用户模态框
    function closeAddModal() {
        const modal = document.getElementById('addModal');
        if (modal) {
            modal.style.display = 'none';
            // 重置表单
            const form = modal.querySelector('form');
            if (form) {
                form.reset();
            }
        }
    }

    // 打开添加用户模态框
    function openAddModal() {
        const modal = document.getElementById('addModal');
        if (modal) {
            modal.style.display = 'block';
        }
    }

    // 删除用户函数
    function deleteRecord(id) {
        if (confirm('确定要删除这个用户吗？此操作不可恢复。')) {
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

    // 点击模态框外部关闭
    window.onclick = function(event) {
        const editModal = document.getElementById('editModal');
        const addModal = document.getElementById('addModal');

        if (event.target == editModal) {
            closeEditModal();
        }
        if (event.target == addModal) {
            closeAddModal();
        }
    }

    // DOM加载完成后的初始化
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        
        // 检查是否有成功消息显示，如果有则不自动打开模态框
        const hasSuccessMessage = document.querySelector('.alert-success') !== null;
        
        // 如果URL中有edit参数且没有成功消息，自动显示编辑modal
        if (urlParams.has('edit') && !hasSuccessMessage) {
            const modal = document.getElementById('editModal');
            if (modal) {
                modal.style.display = 'block';
            }
        }
        // 如果URL中有add参数且没有成功消息，自动显示添加modal
        if (urlParams.has('add') && !hasSuccessMessage) {
            const modal = document.getElementById('addModal');
            if (modal) {
                modal.style.display = 'block';
            }
        }
        // 表单验证增强
        const form = document.querySelector('.modern-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        field.style.borderColor = '#e74c3c';
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
        }
    });
</script>
<?php
$content = ob_get_clean();
// 渲染页面
echo renderAdminLayout('用户管理', $content, $currentUser, 'users.php', [], [], $message ?? '', $messageType ?? 'info');
?>