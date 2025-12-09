<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/admin_layout.php';
require_once '../includes/inventory_operations.php';
// 要求用户登录
requireLogin();
// 检查是否为管理员或经理
requireRole(['admin', 'manager']);
// 获取当前用户信息
$currentUser = getCurrentUser();
// 处理表单提交
$message = '';
$messageType = '';

// 处理URL中的消息参数
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'add':
            $message = '包添加成功！';
            $messageType = 'success';
            break;
        case 'edit':
            $message = '包更新成功！';
            $messageType = 'success';
            break;
        case 'delete':
            $message = '包删除成功！';
            $messageType = 'success';
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $packageCode = trim($_POST['package_code'] ?? '');
            $glassTypeId = (int)($_POST['glass_type_id'] ?? 0);
            $width = floatval($_POST['width'] ?? 0);
            $height = floatval($_POST['height'] ?? 0);
            $pieces = (int)($_POST['pieces'] ?? 0);
            $entryDate = $_POST['entry_date'] ?? date('Y-m-d');
            $initialRackId = (int)($_POST['initial_rack_id'] ?? 0);
            $status = $_POST['status'] ?? 'in_storage';

            // 完整的字段验证
            if (empty($packageCode)) {
                throw new Exception('包号不能为空');
            }
            if ($glassTypeId <= 0) {
                throw new Exception('请选择原片类型');
            }
            if ($width <= 0) {
                throw new Exception('宽度必须大于0');
            }
            if ($height <= 0) {
                throw new Exception('高度必须大于0');
            }

            // 检查包号是否已存在
            $existing = fetchRow("SELECT id FROM glass_packages WHERE package_code = ?", [$packageCode]);
            if ($existing) {
                throw new Exception('该包号已存在，包号必须唯一');
            }

            // 验证片数必须大于0
            if ($pieces <= 0) {
                throw new Exception('库存片数必须大于0');
            }
            if (empty($entryDate)) {
                throw new Exception('入库日期不能为空');
            }
            if ($initialRackId <= 0) {
                throw new Exception('请选择起始库区');
            }
            $newPackageId = insert('glass_packages', [
                'package_code' => $packageCode,
                'glass_type_id' => $glassTypeId,
                'width' => $width,
                'height' => $height,
                'pieces' => $pieces,
                'entry_date' => $entryDate,
                'initial_rack_id' => $initialRackId,
                'current_rack_id' => $initialRackId,
                'status' => $status,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            // 为新包分配位置顺序号
            assignPackagePosition($newPackageId, $initialRackId);
            
            // 添加成功后重定向到列表页面
            header("Location: packages.php?success=add");
            exit;
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $packageCode = trim($_POST['package_code'] ?? '');
            $glassTypeId = (int)($_POST['glass_type_id'] ?? 0);
            $width = floatval($_POST['width'] ?? 0);
            $height = floatval($_POST['height'] ?? 0);
            $pieces = (int)($_POST['pieces'] ?? 0);
            $entryDate = $_POST['entry_date'] ?? '';
            $initialRackId = (int)($_POST['initial_rack_id'] ?? 0);
            $status = $_POST['status'] ?? 'in_storage';

            if ($id <= 0) {
                throw new Exception('参数错误');
            }

            // 完整的字段验证
            if (empty($packageCode)) {
                throw new Exception('包号不能为空');
            }
            if ($glassTypeId <= 0) {
                throw new Exception('请选择原片类型');
            }
            if ($width <= 0) {
                throw new Exception('宽度必须大于0');
            }
            if ($height <= 0) {
                throw new Exception('高度必须大于0');
            }
            if ($pieces <= 0) {
                throw new Exception('片数必须大于0');
            }
            if (empty($entryDate)) {
                throw new Exception('入库日期不能为空');
            }
            if ($initialRackId <= 0) {
                throw new Exception('请选择起始库区');
            }

            // 检查包号是否已存在（排除当前记录）
            $existing = fetchRow("SELECT id FROM glass_packages WHERE package_code = ? AND id != ?", [$packageCode, $id]);
            if ($existing) {
                throw new Exception('该包号已存在');
            }

            query(
                "UPDATE glass_packages SET package_code = ?, glass_type_id = ?, width = ?, height = ?, pieces = ?, entry_date = ?, initial_rack_id = ?, status = ?, updated_at = ? WHERE id = ?",
                [$packageCode, $glassTypeId, $width, $height, $pieces, $entryDate, $initialRackId, $status, date('Y-m-d H:i:s'), $id]
            );

            // 编辑成功后重定向到列表页面
            header("Location: packages.php?success=edit");
            exit;
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('参数错误');
            }

            // 检查是否有关联的交易记录
            $transactions = fetchRow("SELECT COUNT(*) as count FROM inventory_transactions WHERE package_id = ?", [$id]);
            if ($transactions['count'] > 0) {
                throw new Exception('该包下还有交易记录，无法删除');
            }

            query("DELETE FROM glass_packages WHERE id = ?", [$id]);

            // 删除成功后重定向到列表页面
            header("Location: packages.php?success=delete");
            exit;
        }
    } catch (Exception $e) {
        $message = '错误：' . $e->getMessage();
        $messageType = 'error';
    }
}

// 获取筛选参数
$statusFilter = $_GET['status'] ?? '';
$glassTypeFilter = $_GET['glass_type'] ?? '';
$search = $_GET['search'] ?? '';

// 构建查询条件
$whereConditions = [];
$params = [];

// 管理员可以查看所有基地的包，其他角色只能查看所属基地的包
if ($currentUser['role'] !== 'admin' && $currentUser['base_id']) {
    // 库管可以查看未入库的包（current_rack_id 为 NULL）和已入库在所属基地的包
    $whereConditions[] = "(p.current_rack_id IS NULL OR (r.base_id = ? AND p.current_rack_id IS NOT NULL))";
    $params[] = $currentUser['base_id'];
    // 排除已用完的包
    $whereConditions[] = "p.status != ?";
    $params[] = 'used_up';
}

if (!empty($statusFilter)) {
    $whereConditions[] = "p.status = ?";
    $params[] = $statusFilter;
}

if (!empty($glassTypeFilter)) {
    $whereConditions[] = "p.glass_type_id = ?";
    $params[] = $glassTypeFilter;
}

if (!empty($search)) {
    $whereConditions[] = "p.package_code LIKE ?";
    $params[] = '%' . $search . '%';
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
// 获取包列表
$packages = fetchAll("SELECT p.*, g.name as glass_name, g.short_name as glass_short_name, 
                            r.code as rack_code, r.area_type, ir.code as initial_rack_code , g.brand as glass_brand,g.color as glass_color,
                            CONCAT(ROUND(p.width,0), 'x', ROUND(p.height,0)) as specification, p.position_order
                     FROM glass_packages p
                     LEFT JOIN glass_types g ON p.glass_type_id = g.id
                     LEFT JOIN storage_racks r ON p.current_rack_id = r.id
                     LEFT JOIN storage_racks ir ON p.initial_rack_id = ir.id
                     $whereClause
                     ORDER BY r.code ASC, p.position_order ASC, p.package_code ASC", $params);

// 获取原片类型列表
$glassTypes = fetchAll("SELECT * FROM glass_types WHERE status = 1 ORDER BY name");

// 获取当前用户所属基地的库位架列表
$racks = [];
if ($currentUser['role'] === 'admin') {
    // 管理员可以看到所有基地的库位架
    $racks = fetchAll("SELECT r.id, r.code, r.area_type, b.name as base_name
                      FROM storage_racks r
                      LEFT JOIN bases b ON r.base_id = b.id
                      WHERE r.status = 'normal'
                      ORDER BY b.name, r.area_type, r.code");
} elseif ($currentUser['base_id']) {
    // 其他角色只能看到所属基地的库位架
    $racks = fetchAll("SELECT r.id, r.code, r.area_type, b.name as base_name
                      FROM storage_racks r
                      LEFT JOIN bases b ON r.base_id = b.id
                      WHERE r.base_id = ? AND r.status = 'normal'
                      ORDER BY r.area_type, r.code", [$currentUser['base_id']]);
}

// 获取编辑的记录
$editRecord = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editRecord = fetchRow("SELECT * FROM glass_packages WHERE id = ?", [$editId]);
}
ob_start();
?>
<div>
    <button type="button" class="btn btn-success" onclick="showAddForm()">添加包</button>
    <button type="button" class="btn btn-info" onclick="printSelectedLabels()">打印选中标签</button>
    <button type="button" class="btn btn-primary" onclick="exportToExcel('packagesTable', '包列表')">导出Excel</button>
    <button type="button" class="btn btn-warning" onclick="exportToPDF('packagesTable', '包列表')">导出PDF</button>
</div>
<div class="modal" id="formContainer" style="display: <?php echo $editRecord ? 'block' : 'none'; ?>">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php echo $editRecord ? '编辑包' : '添加包'; ?></h3>
            <button class="close" onclick="hideForm()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="<?php echo $editRecord ? 'edit' : 'add'; ?>">
            <?php if ($editRecord): ?>
                <input type="hidden" name="id" value="<?php echo $editRecord['id']; ?>">
            <?php endif; ?>
            <div class="form-group">
                <label for="package_code">包号 *</label>
                <input type="text" id="package_code" name="package_code" value="<?php echo htmlspecialchars($editRecord['package_code'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="glass_type_id">原片类型 *</label>
                <div class="multi-filter-container">
                    <!-- 筛选条件区域 -->
                    <div class="filter-section">
                        <div class="filter-row">
                            <div class="filter-item">
                                <label>厚度(mm):</label>
                                <select id="thickness_filter">
                                    <option value="">全部厚度</option>
                                    <?php
                                    $thicknesses = array_unique(array_column($glassTypes, 'thickness'));
                                    sort($thicknesses);
                                    foreach ($thicknesses as $thickness):
                                        if (!empty($thickness)): ?>
                                            <option value="<?php echo $thickness; ?>"><?php echo $thickness; ?>mm</option>
                                    <?php endif;
                                    endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-item">
                                <label>颜色:</label>
                                <select id="color_filter">
                                    <option value="">全部颜色</option>
                                    <?php
                                    $colors = array_unique(array_filter(array_column($glassTypes, 'color')));
                                    sort($colors);
                                    foreach ($colors as $color):
                                        if (!empty($color)): ?>
                                            <option value="<?php echo htmlspecialchars($color); ?>"><?php echo htmlspecialchars($color); ?></option>
                                    <?php endif;
                                    endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-item">
                                <label>品牌:</label>
                                <select id="brand_filter">
                                    <option value="">全部品牌</option>
                                    <?php
                                    $brands = array_unique(array_filter(array_column($glassTypes, 'brand')));
                                    sort($brands);
                                    foreach ($brands as $brand):
                                        if (!empty($brand)): ?>
                                            <option value="<?php echo htmlspecialchars($brand); ?>"><?php echo htmlspecialchars($brand); ?></option>
                                    <?php endif;
                                    endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-item">
                                <button type="button" id="clear_filters" class="btn btn-secondary btn-sm">清空筛选</button>
                            </div>
                        </div>
                    </div>

                    <!-- 原片类型选择下拉框 -->
                    <div class="glass-type-select-container">
                        <div id="filter_info" class="filter-info">显示全部 <?php echo count($glassTypes); ?> 种原片类型</div>
                        <select id="glass_type_id" name="glass_type_id" required class="form-control">
                            <option value="">请选择原片类型</option>
                            <?php foreach ($glassTypes as $type): ?>
                                <option value="<?php echo $type['id']; ?>"
                                    data-thickness="<?php echo $type['thickness']; ?>"
                                    data-color="<?php echo htmlspecialchars($type['color'] ?? ''); ?>"
                                    data-brand="<?php echo htmlspecialchars($type['brand'] ?? ''); ?>"
                                    <?php echo ($editRecord['glass_type_id'] ?? '') == $type['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['name'] . ' ' . $type['thickness'] . 'mm' .
                                        (!empty($type['color']) ? ' ' . $type['color'] : '') .
                                        (!empty($type['brand']) ? ' ' . $type['brand'] : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <style>
                .size-input-group {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }

                .size-input-group input {
                    flex: 1;
                    min-width: 0;
                }

                .size-separator {
                    font-size: 16px;
                    font-weight: bold;
                    color: #666;
                    user-select: none;
                }
            </style>
            <div class="form-row">
                <div class="form-group">
                    <label>尺寸(mm) *</label>
                    <div class="size-input-group">
                        <input type="number" id="width" name="width" value="<?php echo $editRecord['width'] ?? ''; ?>" min="1" step="0.01" required placeholder="宽度">
                        <span class="size-separator">×</span>
                        <input type="number" id="height" name="height" value="<?php echo $editRecord['height'] ?? ''; ?>" min="1" step="0.01" required placeholder="高度">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="pieces">当前库存片数 * <small class="text-muted">（实际可用片数）</small></label>
                    <input type="number" id="pieces" name="pieces" value="<?php echo $editRecord['pieces'] ?? ''; ?>" min="0" required>
                    <small class="form-text text-muted">此字段为包的实际库存片数，所有库存操作以此为准</small>
                </div>

                <div class="form-group" style="display:flex;justify-content: space-between;">
                    <label for="entry_date">入库日期 *</label>
                    <input type="date" id="entry_date" name="entry_date" value="<?php echo $editRecord['entry_date'] ?? date('Y-m-d'); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="initial_rack_id">起始库区 *</label>
                    <select id="initial_rack_id" name="initial_rack_id" required>
                        <option value="">请选择库区</option>
                        <?php foreach ($racks as $rack): ?>
                            <option value="<?php echo $rack['id']; ?>" <?php echo ($editRecord['initial_rack_id'] ?? '') == $rack['id'] ? 'selected' : ''; ?>>
                                <?php
                                if ($currentUser['role'] === 'admin') {
                                    echo htmlspecialchars($rack['base_name'] . ' - ' . $rack['code'] . ' - ' . $rack['area_type']);
                                } else {
                                    echo htmlspecialchars($rack['code'] . ' - ' . $rack['area_type']);
                                }
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status">状态</label>
                    <select id="status" name="status">
                        <option value="in_storage" <?php echo ($editRecord['status'] ?? 'in_storage') === 'in_storage' ? 'selected' : ''; ?>>库存中</option>
                        <option value="in_processing" <?php echo ($editRecord['status'] ?? '') === 'in_processing' ? 'selected' : ''; ?>>加工中</option>
                        <option value="scrapped" <?php echo ($editRecord['status'] ?? '') === 'scrapped' ? 'selected' : ''; ?>>已报废</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?php echo $editRecord ? '更新' : '添加'; ?></button>
                <button type="button" class="btn btn-secondary" onclick="hideForm()">取消</button>
            </div>
        </form>
    </div>
</div>

<!-- 数据表格 -->
<div class="table-container">
    <table class="table table-striped table-bordered data-table" id="packagesTable" data-table="packages">
        <thead>
            <tr>
                <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                <th>包号</th>
                <th>原片名</th>
                <th>原片品牌</th>
                <th>原片颜色</th>
                <th>规格(宽x高)</th>
                <th>片数</th>
                <th>位置顺序</th>
                <th>入库日期</th>
                <th>起始库区</th>
                <th>当前位置</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
        </thead>
        <?php foreach ($packages as $package): ?>
            <tr>
                <td><input type="checkbox" class="package-checkbox" value="<?php echo $package['id']; ?>"></td>
                <td><?php echo htmlspecialchars($package['package_code']); ?></td>
                <td><?php echo htmlspecialchars($package['glass_name']); ?></td>
                <td><?php echo htmlspecialchars($package['glass_brand']); ?></td>
                <td><?php echo htmlspecialchars($package['glass_color']); ?></td>
                <td><?php echo htmlspecialchars($package['specification']); ?></td>
                <td><?php echo $package['pieces']; ?></td>
                <td><?php echo $package['position_order'] ?? '-'; ?></td>
                <td><?php echo $package['entry_date']; ?></td>
                <td><?php echo htmlspecialchars($package['initial_rack_code']); ?></td>
                <td>
                    <?php 
                    if (empty($package['rack_code'])) {
                        echo '<span class="status-badge inactive">未入库</span>';
                    } else {
                        echo htmlspecialchars($package['rack_code']);
                    }
                    ?>
                </td>
                <td>
                    <?php
                    $statusLabels = [
                        'in_storage' => '<span class="status-badge active">库存中</span>',
                        'in_processing' => '<span class="status-badge processing">加工中</span>',
                        'scrapped' => '<span class="status-badge inactive">已报废</span>',
                        'used_up' => '<span class="status-badge completed">已用完</span>'
                    ];
                    echo $statusLabels[$package['status']] ?? $package['status'];
                    ?>
                </td>
                <td>
                    <a href="?edit=<?php echo $package['id']; ?>" class="btn btn-sm btn-info">编辑</a>
                    <button onclick="printSingleLabel(<?php echo $package['id']; ?>)" class="btn btn-sm btn-warning" title="打印标签">🏷️</button>
                    <button onclick="deleteRecord(<?php echo $package['id']; ?>)" class="btn btn-sm btn-danger">删除</button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    $(document).ready(function() {
    // 初始化返回顶部按钮
        BackToTop.init({
            threshold: 200,    // 滚动200px后显示
            duration: 400,     // 动画400ms
            icon: '↑'         // 向上箭头
        });
    });

    function showAddForm() {
        document.getElementById('formContainer').style.display = 'block';
        document.querySelector('input[name="action"]').value = 'add';

        // 修复：使用正确的选择器
        const headerElement = document.querySelector('.modal-header h3');
        if (headerElement) {
            headerElement.textContent = '添加包';
        }

        const formElement = document.querySelector('form');
        if (formElement) {
            formElement.reset();
        }

        const entryDateElement = document.querySelector('input[name="entry_date"]');
        if (entryDateElement) {
            entryDateElement.value = new Date().toISOString().split('T')[0];
        }

        const idField = document.querySelector('input[name="id"]');
        if (idField) idField.remove();

        // 清除原片类型选择状态
        const glassTypeList = document.getElementById('glass_type_list');
        if (glassTypeList) {
            glassTypeList.querySelectorAll('.glass-type-item').forEach(item => {
                item.classList.remove('selected');
            });
        }

        const hiddenSelect = document.getElementById('glass_type_id');
        if (hiddenSelect) {
            hiddenSelect.value = '';
        }
    }

    function hideForm() {
        document.getElementById('formContainer').style.display = 'none';
    }

    function deleteRecord(id) {
        if (confirm('确定要删除这个包吗？此操作不可恢复。')) {
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

    // 全选/取消全选
    function toggleSelectAll() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.package-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAll.checked;
        });
    }

    // 打印选中的标签
    function printSelectedLabels() {
        const selectedIds = [];
        const checkboxes = document.querySelectorAll('.package-checkbox:checked');
        
        if (checkboxes.length === 0) {
            alert('请先选择要打印标签的包');
            return;
        }
        
        checkboxes.forEach(checkbox => {
            selectedIds.push(checkbox.value);
        });
        
        const url = `print_label.php?package_ids=${selectedIds.join(',')}`;
        window.open(url, '_blank');
    }

    // 打印单个标签
    function printSingleLabel(packageId) {
        const url = `print_label.php?package_ids=${packageId}`;
        window.open(url, '_blank');
    }
    // 多条件筛选功能
    function initMultiFilter() {
        const thicknessFilter = document.getElementById('thickness_filter');
        const colorFilter = document.getElementById('color_filter');
        const brandFilter = document.getElementById('brand_filter');
        const clearFiltersBtn = document.getElementById('clear_filters');
        const glassTypeSelect = document.getElementById('glass_type_id');
        const filterInfo = document.getElementById('filter_info');

        // 获取所有原始选项
        const allOptions = Array.from(glassTypeSelect.options).slice(1); // 排除第一个空选项

        // 获取所有可能的筛选值
        const allThickness = [...new Set(allOptions.map(opt => opt.dataset.thickness).filter(Boolean))];
        const allColors = [...new Set(allOptions.map(opt => opt.dataset.color).filter(Boolean))];
        const allBrands = [...new Set(allOptions.map(opt => opt.dataset.brand).filter(Boolean))];

        // 更新筛选器选项
        function updateFilterOptions() {
            const selectedThickness = thicknessFilter.value;
            const selectedColor = colorFilter.value;
            const selectedBrand = brandFilter.value;

            // 根据当前选择获取可用的选项
            let availableOptions = allOptions.filter(option => {
                const thickness = option.dataset.thickness;
                const color = option.dataset.color;
                const brand = option.dataset.brand;

                let match = true;

                if (selectedThickness && selectedThickness !== thickness) {
                    match = false;
                }
                if (selectedColor && selectedColor !== color) {
                    match = false;
                }
                if (selectedBrand && selectedBrand !== brand) {
                    match = false;
                }

                return match;
            });

            // 获取可用的厚度、颜色、品牌
            const availableThickness = [...new Set(availableOptions.map(opt => opt.dataset.thickness).filter(Boolean))];
            const availableColors = [...new Set(availableOptions.map(opt => opt.dataset.color).filter(Boolean))];
            const availableBrands = [...new Set(availableOptions.map(opt => opt.dataset.brand).filter(Boolean))];

            // 更新厚度筛选器
            updateSelectOptions(thicknessFilter, availableThickness, selectedThickness, '选择厚度');

            // 更新颜色筛选器
            updateSelectOptions(colorFilter, availableColors, selectedColor, '选择颜色');

            // 更新品牌筛选器
            updateSelectOptions(brandFilter, availableBrands, selectedBrand, '选择品牌');
        }

        // 更新下拉框选项的通用函数
        function updateSelectOptions(selectElement, availableValues, selectedValue, placeholder) {
            // 保存当前选中值
            const currentValue = selectElement.value;

            // 清空选项
            selectElement.innerHTML = `<option value="">${placeholder}</option>`;

            // 添加可用选项
            availableValues.sort().forEach(value => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                if (value === currentValue) {
                    option.selected = true;
                }
                selectElement.appendChild(option);
            });

            // 如果当前选中的值不在可用选项中，清空选择
            if (currentValue && !availableValues.includes(currentValue)) {
                selectElement.value = '';
            }
        }

        // 应用筛选
        function applyFilters() {
            const selectedThickness = thicknessFilter.value;
            const selectedColor = colorFilter.value;
            const selectedBrand = brandFilter.value;

            // 清空当前选项（保留第一个空选项）
            glassTypeSelect.innerHTML = '<option value="">请选择原片类型</option>';

            let visibleCount = 0;
            let lastValidOption = null;

            // 筛选并添加符合条件的选项
            allOptions.forEach(option => {
                const thickness = option.dataset.thickness;
                const color = option.dataset.color;
                const brand = option.dataset.brand;

                let show = true;

                // 厚度筛选
                if (selectedThickness && selectedThickness !== thickness) {
                    show = false;
                }

                // 颜色筛选
                if (selectedColor && selectedColor !== color) {
                    show = false;
                }

                // 品牌筛选
                if (selectedBrand && selectedBrand !== brand) {
                    show = false;
                }

                if (show) {
                    const clonedOption = option.cloneNode(true);
                    glassTypeSelect.appendChild(clonedOption);
                    lastValidOption = clonedOption;
                    visibleCount++;
                }
            });

            // 如果筛选后只有一种有效原片类型，自动选中
            if (visibleCount === 1 && lastValidOption) {
                glassTypeSelect.value = lastValidOption.value;
                // 触发change事件，以便其他依赖此选择的功能能够响应
                const changeEvent = new Event('change', {
                    bubbles: true
                });
                glassTypeSelect.dispatchEvent(changeEvent);
            }

            // 更新筛选信息
            const totalCount = allOptions.length;
            if (visibleCount === totalCount) {
                filterInfo.textContent = `显示全部 ${totalCount} 种原片类型`;
            } else if (visibleCount === 1) {
                filterInfo.textContent = `筛选结果：${visibleCount} / ${totalCount} 种原片类型（已自动选中）`;
            } else {
                filterInfo.textContent = `筛选结果：${visibleCount} / ${totalCount} 种原片类型`;
            }
        }

        // 筛选器变化处理函数
        function handleFilterChange() {
            updateFilterOptions(); // 先更新筛选器选项
            applyFilters(); // 再应用筛选
        }

        // 清空筛选
        clearFiltersBtn.addEventListener('click', function() {
            thicknessFilter.value = '';
            colorFilter.value = '';
            brandFilter.value = '';
            updateFilterOptions(); // 重置所有筛选器选项
            applyFilters();
        });

        // 绑定筛选事件
        [thicknessFilter, colorFilter, brandFilter].forEach(filter => {
            filter.addEventListener('change', handleFilterChange);
        });

        // 初始化筛选器选项
        updateFilterOptions();
    }

    // 页面加载完成后初始化
    document.addEventListener('DOMContentLoaded', function() {
        initMultiFilter();
    });
</script>
</body>

</html>
<?php
$content = ob_get_clean();
echo renderAdminLayout('包管理', $content, $currentUser, 'packages.php', [], [], $message, $messageType);
?>