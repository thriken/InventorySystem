<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/admin_layout.php';
// 要求用户登录
requireLogin();

// 检查是否为管理员或经理
requireRole(['admin', 'manager']);

// 获取当前用户信息
$currentUser = getCurrentUser();

// 获取数据字典
function getDictionaryItems($category)
{
    return fetchAll("SELECT code, name FROM dictionary_items WHERE category = ? AND status = 1 AND parent_id IS NULL ORDER BY sort_order, name", [$category]);
}

// 根据品牌获取生产商
function getManufacturersByBrand($brandName)
{
    $brand = fetchRow("SELECT id FROM dictionary_items WHERE category = 'brand' AND name = ? AND status = 1", [$brandName]);
    if (!$brand) {
        return [];
    }
    return fetchAll("SELECT code, name FROM dictionary_items WHERE category = 'manufacturer' AND parent_id = ? AND status = 1 ORDER BY sort_order, name", [$brand['id']]);
}

$brands = getDictionaryItems('brand');
$colors = getDictionaryItems('color');

// 获取所有生产商（用于编辑时显示）
$allManufacturers = fetchAll("SELECT m.code, m.name, b.name as brand_name FROM dictionary_items m LEFT JOIN dictionary_items b ON m.parent_id = b.id WHERE m.category = 'manufacturer' AND m.status = 1 ORDER BY b.sort_order, m.sort_order");
$manufacturers = getDictionaryItems('manufacturer');
$colors = getDictionaryItems('color');

// 处理表单提交
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $customId = trim($_POST['custom_id'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $shortName = trim($_POST['short_name'] ?? '');
            $financeName = trim($_POST['finance_name'] ?? '');
            $productSeries = trim($_POST['product_series'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $manufacturer = trim($_POST['manufacturer'] ?? '');
            $color = trim($_POST['color'] ?? '');
            $thickness = floatval($_POST['thickness'] ?? 0);

            // 完整的字段验证
            if (empty($customId)) {
                throw new Exception('原片ID不能为空');
            }
            if (empty($name)) {
                throw new Exception('原片名称不能为空');
            }
            if (empty($shortName)) {
                throw new Exception('原片简称不能为空');
            }
            if (empty($financeName)) {
                throw new Exception('财务核算名不能为空');
            }
            if (empty($productSeries)) {
                throw new Exception('商色系不能为空');
            }
            if (empty($brand)) {
                throw new Exception('品牌不能为空');
            }
            if (empty($manufacturer)) {
                throw new Exception('生产商不能为空');
            }
            if (empty($color)) {
                throw new Exception('颜色不能为空');
            }
            if ($thickness <= 0) {
                throw new Exception('厚度必须大于0');
            }

            // 检查原片ID是否已存在
            $existing = fetchRow("SELECT id FROM glass_types WHERE custom_id = ?", [$customId]);
            if ($existing) {
                throw new Exception('该原片ID已存在');
            }

            insert('glass_types', [
                'custom_id' => $customId,
                'name' => $name,
                'short_name' => $shortName,
                'finance_name' => $financeName,
                'product_series' => $productSeries,
                'brand' => $brand,
                'manufacturer' => $manufacturer,
                'color' => $color,
                'thickness' => $thickness,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $message = '原片类型添加成功！';
            $messageType = 'success';
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $customId = trim($_POST['custom_id'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $shortName = trim($_POST['short_name'] ?? '');
            $financeName = trim($_POST['finance_name'] ?? '');
            $productSeries = trim($_POST['product_series'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $manufacturer = trim($_POST['manufacturer'] ?? '');
            $color = trim($_POST['color'] ?? '');
            $thickness = floatval($_POST['thickness'] ?? 0);

            if ($id <= 0) {
                throw new Exception('参数错误');
            }

            // 完整的字段验证
            if (empty($customId)) {
                throw new Exception('原片ID不能为空');
            }
            if (empty($name)) {
                throw new Exception('原片名称不能为空');
            }
            if (empty($shortName)) {
                throw new Exception('原片简称不能为空');
            }
            if (empty($financeName)) {
                throw new Exception('财务核算名不能为空');
            }
            if (empty($productSeries)) {
                throw new Exception('商色系不能为空');
            }
            if (empty($brand)) {
                throw new Exception('品牌不能为空');
            }
            if (empty($manufacturer)) {
                throw new Exception('生产商不能为空');
            }
            if (empty($color)) {
                throw new Exception('颜色不能为空');
            }
            if ($thickness <= 0) {
                throw new Exception('厚度必须大于0');
            }

            // 检查原片ID是否已存在（排除当前记录）
            $existing = fetchRow("SELECT id FROM glass_types WHERE custom_id = ? AND id != ?", [$customId, $id]);
            if ($existing) {
                throw new Exception('该原片ID已存在');
            }

            query(
                "UPDATE glass_types SET custom_id = ?, name = ?, short_name = ?, finance_name = ?, product_series = ?, brand = ?, manufacturer = ?, color = ?, thickness = ?, updated_at = ? WHERE id = ?",
                [
                    $customId,
                    $name,
                    $shortName,
                    $financeName,
                    $productSeries,
                    $brand,
                    $manufacturer,
                    $color,
                    $thickness,
                    date('Y-m-d H:i:s'),
                    $id
                ]
            );

            $message = '原片类型更新成功！';
            $messageType = 'success';
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('参数错误');
            }

            // 检查是否有关联的包
            $packages = fetchRow("SELECT COUNT(*) as count FROM glass_packages WHERE glass_type_id = ?", [$id]);
            if ($packages['count'] > 0) {
                throw new Exception('该原片类型下还有包记录，无法删除');
            }

            query("DELETE FROM glass_types WHERE id = ?", [$id]);

            $message = '原片类型删除成功！';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = '错误：' . $e->getMessage();
        $messageType = 'error';
    }
}

// 获取原片类型列表
$glassTypes = fetchAll("SELECT * FROM glass_types ORDER BY created_at DESC");

// 获取编辑的记录
$editRecord = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editRecord = fetchRow("SELECT * FROM glass_types WHERE id = ?", [$editId]);
}
$typeNums = fetchOne("SELECT count(*) FROM glass_types");
ob_start();
?>
<div class="admin-header">
    <button type="button" class="btn btn-success" onclick="showAddForm()">添加原片类型</button>
    <span>当前共 <?php echo $typeNums; ?> 种原片类型</span>
</div>
<!-- 添加/编辑表单 -->
<div class="form-container" id="formContainer" style="display: <?php echo $editRecord ? 'block' : 'none'; ?>">
    <div class="form-header">
        <h3><?php echo $editRecord ? '编辑原片类型' : '添加原片类型'; ?></h3>
        <button class="close-btn" onclick="hideForm()">&times;</button>
    </div>
    <form method="POST" action="">
        <input type="hidden" name="action" value="<?php echo $editRecord ? 'edit' : 'add'; ?>">
        <?php if ($editRecord): ?>
            <input type="hidden" name="id" value="<?php echo $editRecord['id']; ?>">
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label for="custom_id">原片ID *</label>
                <input type="text" id="custom_id" name="custom_id" value="<?php echo htmlspecialchars($editRecord['custom_id'] ?? ''); ?>" required placeholder="自定义唯一ID，采购使用">
            </div>

            <div class="form-group">
                <label for="short_name">原片简称 *</label>
                <input type="text" id="short_name" name="short_name" value="<?php echo htmlspecialchars($editRecord['short_name'] ?? ''); ?>" required placeholder="如：5白、4白、5白南玻">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="name">原片名称 *</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($editRecord['name'] ?? ''); ?>" required placeholder="完整的原片名称">
            </div>

            <div class="form-group">
                <label for="finance_name">财务核算名</label>
                <input type="text" id="finance_name" name="finance_name" value="<?php echo htmlspecialchars($editRecord['finance_name'] ?? ''); ?>" placeholder="财务核算使用的名称">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="product_series">原片商色系</label>
                <input type="text" id="product_series" name="product_series" value="<?php echo htmlspecialchars($editRecord['product_series'] ?? ''); ?>" placeholder="如：5lowe XETB0160、XETB0060、PLE60">
            </div>

            <div class="form-group">
                <label for="thickness">原片厚度(mm)</label>
                <select id="thickness" name="thickness">
                    <option value="">请选择厚度</option>
                    <?php
                    $thicknesses = [4, 5, 6, 8, 10, 12, 15, 19];
                    foreach ($thicknesses as $t):
                    ?>
                        <option value="<?php echo $t; ?>" <?php echo ($editRecord['thickness'] ?? '') == $t ? 'selected' : ''; ?>><?php echo $t; ?>mm</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="brand">原片品牌</label>
                <select id="brand" name="brand">
                    <option value="">请选择品牌</option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?php echo htmlspecialchars($brand['name']); ?>" <?php echo ($editRecord['brand'] ?? '') === $brand['name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($brand['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="manufacturer">原片生产商</label>
                <select id="manufacturer" name="manufacturer">
                    <option value="">请选择生产商</option>
                    <?php foreach ($manufacturers as $mfr): ?>
                        <option value="<?php echo htmlspecialchars($mfr['name']); ?>" <?php echo ($editRecord['manufacturer'] ?? '') === $mfr['name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mfr['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="color">原片颜色</label>
                <select id="color" name="color">
                    <option value="">请选择颜色</option>
                    <?php foreach ($colors as $color): ?>
                        <option value="<?php echo htmlspecialchars($color['name']); ?>" <?php echo ($editRecord['color'] ?? '') === $color['name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($color['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
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
                <th>原片ID</th>
                <th>原片名称</th>
                <th>原片简称</th>
                <th>财务核算名</th>
                <th>色系</th>
                <th>厚度</th>
                <th>品牌</th>
                <th>颜色</th>
                <th>银层</th>
                <th>基片</th>
                <th>透光</th>
                <th>创建时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($glassTypes as $type): ?>
                <tr>
                    <td><?php echo htmlspecialchars($type['custom_id']); ?></td> <!-- 原片ID-->
                    <td><?php echo htmlspecialchars($type['name']); ?></td> <!-- 原片名称-->
                    <td><?php echo htmlspecialchars($type['short_name']); ?></td> <!-- 原片简称-->
                    <td><?php echo htmlspecialchars($type['finance_name']); ?></td> <!-- 财务核算名-->
                    <td><?php echo htmlspecialchars($type['product_series']); ?></td> 
                    <td><?php echo $type['thickness'] ? number_format($type['thickness'], 0) . 'mm' : '-'; ?></td>
                    <td><?php echo htmlspecialchars($type['brand']); ?></td>
                    <td><?php echo htmlspecialchars($type['color']); ?></td>
                    <td><?php echo htmlspecialchars($type['silver_layers']); ?></td>
                    <td><?php echo htmlspecialchars($type['substrate']); ?></td>
                    <td><?php echo htmlspecialchars($type['transmittance']); ?></td>
                    <td><?php echo formatDateTime($type['created_at']); ?></td>
                    <td><a href="?edit=<?php echo $type['id']; ?>" class="btn btn-sm btn-info">编辑</a><button onclick="deleteRecord(<?php echo $type['id']; ?>)" class="btn btn-sm btn-danger">删除</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>
</div>

<script>
    function showAddForm() {
        document.getElementById('formContainer').style.display = 'block';
        document.querySelector('input[name="action"]').value = 'add';
        document.querySelector('.form-header h3').textContent = '添加原片类型';
        document.querySelector('form').reset();
        // 移除隐藏的id字段
        const idField = document.querySelector('input[name="id"]');
        if (idField) idField.remove();
    }

    function hideForm() {
        document.getElementById('formContainer').style.display = 'none';
    }

    function deleteRecord(id) {
        if (confirm('确定要删除这个原片类型吗？此操作不可恢复。')) {
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
    // 添加到 glass_types.php 的 script 部分
    function loadManufacturersByBrand(brandName) {
        const manufacturerSelect = document.getElementById('manufacturer');

        // 清空现有选项
        manufacturerSelect.innerHTML = '<option value="">请选择生产商</option>';

        if (!brandName) {
            return;
        }

        // 发送AJAX请求获取对应品牌的生产商
        fetch('get_manufacturers.php?brand=' + encodeURIComponent(brandName))
            .then(response => response.json())
            .then(data => {
                data.forEach(manufacturer => {
                    const option = document.createElement('option');
                    option.value = manufacturer.name;
                    option.textContent = manufacturer.name;
                    manufacturerSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('加载生产商失败:', error);
            });
    }

    // 品牌选择变化时触发
    document.getElementById('brand').addEventListener('change', function() {
        loadManufacturersByBrand(this.value);
    });
</script>
</body>

</html>
<?php
$content = ob_get_clean();
// 渲染页面
echo renderAdminLayout('原片类型', $content, $currentUser, 'glass_types.php', [], [], $message ?? '', $messageType ?? 'info');
?>