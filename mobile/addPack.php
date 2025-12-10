<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/inventory_operations.php'; // 引入公共业务逻辑
// 要求用户登录
requireLogin();

// 检查是否为库管权限
requireRole(['manager']);

// 获取当前用户信息
$currentUser = getCurrentUser();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 检查是否为批量模式
        $is_batch_mode = !empty($_POST['package_codes_batch']);

        if ($is_batch_mode) {
            // 批量处理
            $package_codes_text = trim($_POST['package_codes_batch']);
            $package_codes = array_filter(array_map('trim', explode("\n", $package_codes_text)));

            if (empty($package_codes)) {
                throw new Exception('请输入至少一个包号');
            }

            $success_count = 0;
            $failed_packages = [];
            $success_packages = [];

            // 获取其他表单数据
            $glass_type_id = (int)$_POST['glass_type_id'];
            $width = !empty($_POST['width']) ? (float)$_POST['width'] : null;
            $height = !empty($_POST['height']) ? (float)$_POST['height'] : null;
            $pieces = (int)$_POST['pieces'];
            $quantity = (int)$_POST['quantity'];
            $entry_date = $_POST['entry_date'];
            $rack_name = trim($_POST['rack_name'] ?? '');
            $base_id = $currentUser['base_id'];

            // 验证公共字段
            if ($glass_type_id <= 0) {
                throw new Exception('请选择原片类型');
            }
            if ($pieces <= 0) {
                throw new Exception('片数必须大于0');
            }
            if (empty($entry_date)) {
                throw new Exception('请选择入库日期');
            }
            if (empty($rack_name)) {
                throw new Exception('库位名称不能为空，请输入库位名称');
            }

            // 查找库位ID
            $target_rack_id = 0;
            $rack = fetchOne("SELECT id FROM storage_racks WHERE base_id = ? and name = ?", [$base_id, $rack_name]);
            if (!$rack) {
                throw new Exception('库位号不存在，请检查输入的库位号');
            }
            $target_rack_id = $rack;

            // 开始事务
            global $pdo;
            $pdo->beginTransaction();

            try {
                foreach ($package_codes as $package_code) {
                    if (empty($package_code)) continue;

                    // 检查包号是否已存在
                    $existing = fetchOne("SELECT id FROM glass_packages WHERE package_code = ?", [$package_code]);
                    if ($existing) {
                        $failed_packages[] = $package_code . ' (包号已存在)';
                        continue;
                    }

                    // 插入新记录 - 直接使用PDO prepare和execute
                    $sql = "
                        INSERT INTO glass_packages 
                        (package_code, glass_type_id, width, height, pieces, quantity, entry_date, initial_rack_id, current_rack_id, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'in_storage')
                    ";

                    $stmt = $pdo->prepare($sql);
                    $insert_result = $stmt->execute([
                        $package_code,
                        $glass_type_id,
                        $width,
                        $height,
                        $pieces,
                        $quantity,
                        $entry_date,
                        $target_rack_id,
                        $target_rack_id
                    ]);

                    // 获取新插入的包ID
                    $new_package_id = $pdo->lastInsertId();
                    
                    // 如果lastInsertId()返回0，尝试通过包号查询获取ID
                    if ($new_package_id == 0) {
                        $new_package_id = fetchOne("SELECT id FROM glass_packages WHERE package_code = ?", [$package_code]);
                    }
                    
                    // 验证是否获取到有效的包ID
                    if ($new_package_id > 0) {
                        $success_packages[] = $package_code;
                        $success_count++;
                    } else {
                        $failed_packages[] = $package_code . ' (无法获取包ID)';
                    }
                }

                $pdo->commit();
                
                // 重新整理该库位的包顺序号，确保从1开始连续编号
                if ($success_count > 0) {
                    reorderPackagePositions($target_rack_id);
                }

                // 生成结果消息
                $result_message = "批量添加完成！成功添加 {$success_count} 个包：" . implode(', ', $success_packages);
                if (!empty($failed_packages)) {
                    $result_message .= "\n失败的包号：" . implode(', ', $failed_packages);
                }
                $success_message = $result_message;

                // 清空表单数据
                $_POST = [];
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        } else {
            // 单包处理（原有逻辑）
            $package_code = trim($_POST['package_code']);
            $glass_type_id = (int)$_POST['glass_type_id'];
            $width = !empty($_POST['width']) ? (float)$_POST['width'] : null;
            $height = !empty($_POST['height']) ? (float)$_POST['height'] : null;
            $pieces = (int)$_POST['pieces'];
            $quantity = (int)$_POST['quantity'];
            $entry_date = $_POST['entry_date'];
            $rack_name = trim($_POST['rack_name'] ?? ''); // 改为库位号输入
            $base_id = $currentUser['base_id'];

            // 验证必填字段
            if (empty($package_code)) {
                throw new Exception('包号不能为空');
            }
            if ($glass_type_id <= 0) {
                throw new Exception('请选择原片类型');
            }
            if ($pieces <= 0) {
                throw new Exception('片数必须大于0');
            }
            if (empty($entry_date)) {
                throw new Exception('请选择入库日期');
            }
            if (empty($rack_name)) {
                throw new Exception('库位名称不能为空，请输入库位名称');
            }

            // 根据库位号查找库位ID
            $target_rack_id = null;
            $rack = fetchOne("SELECT id FROM storage_racks WHERE base_id = ? and name = ?", [$base_id, $rack_name]);
            if (!$rack) {
                throw new Exception('库位号不存在，请检查输入的库位号');
            }
            $target_rack_id = $rack;

            // 检查包号是否已存在
            $existing = fetchOne("SELECT id FROM glass_packages WHERE package_code = ?", [$package_code]);
            if ($existing) {
                throw new Exception('包号已存在，请使用其他包号');
            }

            // 插入新记录
            $sql = "
                INSERT INTO glass_packages 
                (package_code, glass_type_id, width, height, pieces, quantity, entry_date, initial_rack_id, current_rack_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'in_storage')
            ";

            // 直接使用PDO执行插入，而不是execute()函数
            global $pdo;
            $stmt = $pdo->prepare($sql);
            $insert_result = $stmt->execute([
                $package_code,
                $glass_type_id,
                $width,
                $height,
                $pieces,
                $quantity,
                $entry_date,
                $target_rack_id,
                $target_rack_id
            ]);

            // 获取刚插入的ID
            $new_package_id = $pdo->lastInsertId();
            
            // 如果lastInsertId()仍然返回0，则通过包号查询ID
            if ($new_package_id == 0) {
                $new_package_id = fetchOne("SELECT id FROM glass_packages WHERE package_code = ?", [$package_code]);
            }
            
            // 使用公共方法为包分配位置顺序号
            if ($new_package_id > 0) {
                assignPackagePosition($new_package_id, $target_rack_id);
                // 重新整理该库位的包顺序号，确保连续编号
                reorderPackagePositions($target_rack_id);
            }
            
            $success_message = '原片包添加成功！';

            // 清空表单数据
            $_POST = [];
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// 获取原片类型列表
$glass_types = [];
$thickness_options = [];
$color_options = [];
$brand_options = [];
try {
    $sql = "
        SELECT id, name, short_name, brand, color, thickness ,silver_layers,substrate,transmittance
        FROM glass_types 
        WHERE status = 1 ";
    $sql .= " ORDER BY color, brand, thickness, name";
    $glass_types = fetchAll($sql);

    // 提取所有可选的厚度、颜色、品牌选项
    foreach ($glass_types as $type) {
        if (!empty($type['thickness']) && !in_array($type['thickness'], $thickness_options)) {
            $thickness_options[] = $type['thickness'];
        }
        if (!empty($type['color']) && !in_array($type['color'], $color_options)) {
            $color_options[] = $type['color'];
        }
        if (!empty($type['brand']) && !in_array($type['brand'], $brand_options)) {
            $brand_options[] = $type['brand'];
        }
    }

    // 排序选项
    sort($thickness_options, SORT_NUMERIC);
    sort($color_options);
    sort($brand_options);
} catch (Exception $e) {
    $error_message = '获取原片类型失败：' . $e->getMessage();
}

// 获取库区列表
$storage_racks = [];
try {
    $sql = "
        SELECT sr.id, sr.code, sr.name, b.name as base_name 
        FROM storage_racks sr 
        LEFT JOIN bases b ON sr.base_id = b.id 
        WHERE sr.status = 'normal' 
        ORDER BY b.name, sr.code
    ";
    if ($currentUser['role'] != 'admin') {
        $sql .= " and base_id = {$currentUser['base_id']}";
    }
    $storage_racks = fetchAll($sql);
} catch (Exception $e) {
    $error_message = '获取库区列表失败：' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>新增原片包 - <?php echo getAppName(); ?></title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/mobile.css">
    <!-- 添加二维码扫描库 -->
    <script src="https://cdn.bootcdn.net/ajax/libs/@zxing/library/latest/umd/index.min.js"></script>
    <style>
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
            resize: vertical;
        }

        .form-container {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .form-group select,
        .form-group input[type="date"] {
            width: 100%;
        }

        .form-group input[type="radio"] {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #4CAF50;
            outline: none;
        }

        .required {
            color: #f44336;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .alert {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .scan-input {
            display: flex;
            gap: 5px;
        }

        .scan-input input {
            flex: 1;
        }

        .scan-btn {
            background: #2196F3;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            font-size: 14px;
            white-space: nowrap;
        }

        /* PDA适配 */
        @media screen and (max-width: 720px) {

            .form-group input,
            .form-group select {
                font-size: 15px;
                padding: 8px;
            }

            .btn {
                padding: 10px;
                font-size: 15px;
            }
        }

        /* 超小屏幕适配 */
        @media screen and (max-width: 320px) {
            .form-container {
                padding: 10px;
                margin-bottom: 10px;
            }

            .form-group {
                margin-bottom: 10px;
            }

            .form-group label {
                font-size: 12px;
            }

            .form-group input,
            .form-group select {
                font-size: 12px;
                padding: 6px;
            }

            .btn {
                padding: 8px;
                font-size: 12px;
            }

            .scan-btn {
                padding: 6px 10px;
                font-size: 11px;
            }
        }

        /* 新增筛选器样式 */
        .filter-row {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            gap: 10px;
        }

        .filter-row label {
            min-width: 80px;
            margin: 0;
            font-weight: normal;
            font-size: 14px;
        }

        .filter-row select {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .filter-row select:focus {
            border-color: #007bff;
            outline: none;
        }

        @media (max-width: 480px) {
            .filter-row {
                flex-direction: column;
                align-items: stretch;
                gap: 5px;
            }

            .filter-row label {
                min-width: auto;
            }
        }

        .camera-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 2000;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .camera-container {
            position: relative;
            width: 90%;
            max-width: 400px;
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-sizing: border-box;
        }

        .camera-header {
            text-align: center;
            margin-bottom: 15px;
        }

        .camera-header h3 {
            margin: 0;
            color: #333;
        }

        .close-camera {
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            color: #666;
            cursor: pointer;
        }

        #camera-video {
            width: 100%;
            height: 250px;
            background: #000;
            border-radius: 4px;
            object-fit: cover;
            display: block;
        }

        .camera-controls {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
        }

        .camera-button {
            padding: 8px 16px;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .camera-button:hover {
            background: #45a049;
        }

        .scan-result {
            margin-top: 10px;
            padding: 10px;
            background: #e8f5e9;
            border-radius: 4px;
            display: none;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="camera-modal" id="camera-modal" style="display: none;">
        <div class="camera-container">
            <button class="close-camera" onclick="closeCameraModal()">&times;</button>
            <div class="camera-header">
                <h3>扫描二维码</h3>
            </div>
            <video id="camera-video" autoplay playsinline></video>
            <div class="camera-controls">
                <button type="button" class="camera-button" onclick="switchCamera()">切换摄像头</button>
                <button type="button" class="camera-button" onclick="closeCameraModal()">取消</button>
            </div>
            <div class="scan-result" id="scan-result">
                <strong>扫描结果：</strong>
                <span id="scan-result-text" onclick="confirmScanResult()" style="cursor: pointer; color: #007bff; text-decoration: underline;"></span>
                <br><small style="color: #666;">点击结果确认使用</small>
            </div>
        </div>
    </div>
    <div class="mobile-header">
        <h1>新增原片包</h1>
    </div>

    <div class="mobile-container">
        <div class="mobile-content">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="form-container">


                <div class="form-group">
                    <label>原片类型筛选 <span class="required">*</span></label>

                    <!-- 厚度选择 -->
                    <div class="filter-row">
                        <label for="thickness_filter">厚度(mm):</label>
                        <select id="thickness_filter" name="thickness_filter">
                            <option value="">全部厚度</option>
                            <?php foreach ($thickness_options as $thickness): ?>
                                <option value="<?php echo $thickness; ?>"
                                    <?php echo (isset($_POST['thickness_filter']) && $_POST['thickness_filter'] == $thickness) ? 'selected' : ''; ?>>
                                    <?php echo number_format($thickness, 0); ?> mm
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 颜色选择 -->
                    <div class="filter-row">
                        <label for="color_filter">颜色:</label>
                        <select id="color_filter" name="color_filter">
                            <option value="">全部颜色</option>
                            <?php foreach ($color_options as $color): ?>
                                <option value="<?php echo htmlspecialchars($color); ?>"
                                    <?php echo (isset($_POST['color_filter']) && $_POST['color_filter'] == $color) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($color); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 品牌选择 -->
                    <div class="filter-row">
                        <label for="brand_filter">品牌:</label>
                        <select id="brand_filter" name="brand_filter">
                            <option value="">全部品牌</option>
                            <?php foreach ($brand_options as $brand): ?>
                                <option value="<?php echo htmlspecialchars($brand); ?>"
                                    <?php echo (isset($_POST['brand_filter']) && $_POST['brand_filter'] == $brand) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($brand); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 最终原片类型选择 -->
                    <div class="filter-row">
                        <label for="glass_type_id">选择原片:</label>
                        <select id="glass_type_id" name="glass_type_id" required>
                            <option value="">请先选择筛选条件</option>
                            <?php foreach ($glass_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>"
                                    data-thickness="<?php echo $type['thickness']; ?>"
                                    data-color="<?php echo htmlspecialchars($type['color']); ?>"
                                    data-brand="<?php echo htmlspecialchars($type['brand']); ?>"
                                    <?php echo (isset($_POST['glass_type_id']) && $_POST['glass_type_id'] == $type['id']) ? 'selected' : ''; ?>
                                    style="display: none;">
                                    <?php
                                    if (strtoupper($type['color']) == 'LOWE') {
                                        echo htmlspecialchars($type['name'] . '(' . $type['brand'] . '-' . $type['silver_layers']  . $type['substrate']  . $type['transmittance'] . ')');
                                    } else {
                                        echo htmlspecialchars($type['name']);
                                    } ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="width">宽度(mm)<span class="required">*</span></label>
                    <input type="number" id="width" name="width" step="0.01"
                        value="<?php echo htmlspecialchars($_POST['width'] ?? ''); ?>"
                        placeholder="请输入宽度">
                </div>

                <div class="form-group">
                    <label for="height">高度(mm)<span class="required">*</span></label>
                    <input type="number" id="height" name="height" step="0.01"
                        value="<?php echo htmlspecialchars($_POST['height'] ?? ''); ?>"
                        placeholder="请输入高度">
                </div>

                <div class="form-group">
                    <label for="pieces">实际片数 <span class="required">*</span></label>
                    <input type="number" id="pieces" name="pieces" min="1"
                        value="<?php echo htmlspecialchars($_POST['pieces'] ?? ''); ?>"
                        placeholder="请输入实际片数" required>
                </div>

                <div class="form-group">
                    <label for="quantity">原包数量</label>
                    <input type="tel" id="quantity" name="quantity"
                        pattern="[0-9]*"
                        inputmode="numeric"
                        min="0" max="999999"
                        value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>"
                        placeholder="请输入包装数量">
                </div>

                <div class="form-group">
                    <label for="entry_date">入库日期 <span class="required">*</span></label>
                    <input type="date" id="entry_date" name="entry_date"
                        value="<?php echo htmlspecialchars($_POST['entry_date'] ?? date('Y-m-d')); ?>"
                        required>
                </div>
                <div class="form-group">
                    <label>添加模式</label>
                    <div style="display: flex; margin-bottom: 10px;">
                        <label style="margin-right: 15px;">
                            <input type="radio" name="input_mode" value="single" checked onclick="toggleInputMode('single')"> 单包添加
                        </label>
                        <label>
                            <input type="radio" name="input_mode" value="batch" onclick="toggleInputMode('batch')"> 批量添加
                        </label>
                    </div>
                </div>

                <div id="single_input_mode">
                    <div class="form-group">
                        <label for="package_code">包号/二维码 <span class="required">*</span></label>
                        <div class="scan-input">
                            <input type="text" id="package_code" name="package_code"
                                value="<?php echo htmlspecialchars($_POST['package_code'] ?? ''); ?>"
                                placeholder="请输入或扫描包号">
                            <button type="button" class="scan-btn" onclick="scanCode()">扫描</button>
                        </div>
                    </div>
                </div>

                <div id="batch_input_mode" style="display: none;">
                    <div class="form-group">
                        <label for="package_codes_batch">批量包号 <span class="required">*</span></label>
                        <div class="input-group">
                            <textarea id="package_codes_batch" name="package_codes_batch" rows="5"
                                placeholder="每行输入一个包号，可粘贴Excel列数据"><?php echo htmlspecialchars($_POST['package_codes_batch'] ?? ''); ?></textarea>
                            <button type="button" class="scan-btn" onclick="scanCodeBatch()">扫描添加</button>
                        </div>
                        <small class="form-text">每行输入一个包号，可直接从Excel复制粘贴，或点击扫描按钮逐个添加</small>
                    </div>
                </div>
                <div class="form-group">
                    <label for="rack_name">起始库区<span class="required">*</span></label>
                    <input type="text" id="rack_name" name="rack_name"
                        value="<?php echo htmlspecialchars($_POST['rack_name'] ?? ''); ?>"
                        placeholder="请输入库位号（如：1A）"
                        maxlength="50" required>
                    <small class="form-text">输入库位明，A代表左位，B代表右位，格式如：1A</small>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">保存</button>
                    <a href="index.php" class="btn btn-secondary">返回</a>
                </div>
            </form>
        </div>
    </div>

    <div class="mobile-footer">
        <a href="index.php">🏠<br>首页</a>
        <a href="scan.php">📷<br>扫描</a>
        <a href="history.php">📋<br>记录</a>
        <a href="../logout.php">🚪<br>退出</a>
    </div>

    <script>
        let currentStream = null;
        let currentFieldId = null;
        let codeReader = null;
        let currentFacingMode = 'environment'; // 默认后置摄像头

        // 初始化二维码扫描器
        function initializeCodeReader() {
            if (typeof ZXing !== 'undefined') {
                codeReader = new ZXing.BrowserMultiFormatReader();
                console.log('Code reader initialized successfully');
            } else {
                console.error('ZXing library not loaded');
            }
        }

        // 检测设备和浏览器类型
        function getDeviceInfo() {
            const userAgent = navigator.userAgent.toLowerCase();
            return {
                isMiui: userAgent.includes('miuibrowser') || userAgent.includes('xiaomi'),
                isAndroid: userAgent.includes('android'),
                isMobile: /android|webos|iphone|ipad|ipod|blackberry|iemobile|opera mini/i.test(userAgent),
                isChrome: userAgent.includes('chrome'),
                isWeChat: userAgent.includes('micromessenger'),
                isQQ: userAgent.includes('qq/')
            };
        }

        // 扫描条码主函数
        function scanCode() {
            currentFieldId = 'package_code';
            const deviceInfo = getDeviceInfo();

            // 检查是否支持摄像头
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                fallbackToManualInput('package_code', '您的浏览器不支持摄像头功能');
                return;
            }

            // 特殊处理小米浏览器和其他可能有问题的浏览器
            if (deviceInfo.isMiui || deviceInfo.isWeChat || deviceInfo.isQQ) {
                // 先尝试摄像头，如果失败则降级
                tryCamera('package_code', true);
            } else if (deviceInfo.isAndroid || deviceInfo.isMobile) {
                // 其他移动设备直接尝试摄像头
                tryCamera('package_code', false);
            } else {
                // PC设备降级到手动输入
                fallbackToManualInput('package_code', '请使用移动设备进行扫码');
            }
        }

        // 尝试调用摄像头
        function tryCamera(fieldId, allowFallback = true) {
            // 先检查权限
            if (navigator.permissions) {
                navigator.permissions.query({
                        name: 'camera'
                    })
                    .then(function(result) {
                        if (result.state === 'granted') {
                            openCameraModal();
                        } else if (result.state === 'prompt') {
                            // 需要用户授权
                            openCameraModal();
                        } else {
                            // 权限被拒绝
                            if (allowFallback) {
                                fallbackToManualInput(fieldId, '摄像头权限被拒绝，请手动输入');
                            } else {
                                alert('请在浏览器设置中允许摄像头权限');
                            }
                        }
                    })
                    .catch(function() {
                        // 权限API不支持，直接尝试
                        openCameraModal();
                    });
            } else {
                // 不支持权限API，直接尝试
                openCameraModal();
            }
        }

        // 降级到手动输入
        function fallbackToManualInput(fieldId, message) {
            const result = prompt(message + '\n\n请手动输入二维码内容：');
            if (result && result.trim()) {
                document.getElementById(fieldId).value = result.trim();
            }
        }

        // 打开摄像头模态框
        function openCameraModal() {
            const modal = document.getElementById('camera-modal');
            modal.style.display = 'flex';
            // 初始化扫描器
            if (!codeReader) {
                initializeCodeReader();
            }
            // 延迟启动摄像头，给模态框时间渲染
            setTimeout(() => {
                startCamera();
            }, 100);
        }

        // 关闭摄像头模态框
        function closeCameraModal() {
            const modal = document.getElementById('camera-modal');
            modal.style.display = 'none';
            // 停止摄像头
            stopCamera();
            // 隐藏扫描结果
            document.getElementById('scan-result').style.display = 'none';
        }

        // 启动摄像头
        function startCamera() {
            const video = document.getElementById('camera-video');

            // 更严格的约束条件，提高兼容性
            const constraints = {
                video: {
                    facingMode: currentFacingMode,
                    width: {
                        min: 640,
                        ideal: 1280,
                        max: 1920
                    },
                    height: {
                        min: 480,
                        ideal: 720,
                        max: 1080
                    },
                    frameRate: {
                        ideal: 10,
                        max: 30
                    },
                    focusMode: 'continuous'
                },
                audio: false
            };

            navigator.mediaDevices.getUserMedia(constraints)
                .then(function(stream) {
                    currentStream = stream;
                    video.srcObject = stream;
                    // 等待视频加载完成后开始扫描
                    video.onloadedmetadata = function() {
                        video.play().then(() => {
                            if (codeReader) {
                                startScanning();
                            }
                        }).catch(err => {
                            console.error('视频播放失败:', err);
                            handleCameraError('视频播放失败');
                        });
                    };
                })
                .catch(function(err) {
                    console.error('摄像头访问失败:', err);
                    handleCameraError(err.name || '摄像头访问失败');
                });
        }

        // 处理摄像头错误
        function handleCameraError(errorType) {
            let message = '';

            switch (errorType) {
                case 'NotAllowedError':
                    message = '摄像头权限被拒绝，请在浏览器设置中允许摄像头权限';
                    break;
                case 'NotFoundError':
                    message = '未找到摄像头设备';
                    break;
                case 'NotSupportedError':
                    message = '浏览器不支持摄像头功能';
                    break;
                case 'NotReadableError':
                    message = '摄像头被其他应用占用';
                    break;
                default:
                    message = '摄像头启动失败: ' + errorType;
            }
            closeCameraModal();
            // 提供手动输入选项
            if (confirm(message + '\n\n是否手动输入二维码内容？')) {
                fallbackToManualInput(currentFieldId, '');
            }
        }

        // 停止摄像头
        function stopCamera() {
            if (currentStream) {
                currentStream.getTracks().forEach(track => {
                    track.stop();
                });
                currentStream = null;
            }
            if (codeReader) {
                codeReader.reset();
            }
            const video = document.getElementById('camera-video');
            if (video) {
                video.srcObject = null;
            }
        }

        // 切换摄像头
        function switchCamera() {
            currentFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';
            stopCamera();
            setTimeout(() => {
                startCamera();
            }, 200);
        }

        // 开始扫描
        function startScanning() {
            const video = document.getElementById('camera-video');
            if (codeReader && video.readyState === video.HAVE_ENOUGH_DATA) {
                codeReader.decodeFromVideoDevice(null, video, (result, err) => {
                    if (result) {
                        const scannedText = result.text;
                        document.getElementById('scan-result-text').textContent = scannedText;
                        document.getElementById('scan-result').style.display = 'block';
                        if (codeReader) {
                            codeReader.reset();
                        }
                        window.currentScanResult = {
                            text: scannedText,
                            fieldId: currentFieldId
                        };
                    }
                    if (err && !(err instanceof ZXing.NotFoundException)) {
                        console.error('扫描错误:', err);
                    }
                });
            } else {
                // 视频还未准备好，稍后重试
                setTimeout(() => {
                    startScanning();
                }, 100);
            }
        }

        // 确认扫码结果
        function confirmScanResult() {
            if (window.currentScanResult && window.currentScanResult.fieldId) {
                const fieldId = window.currentScanResult.fieldId;
                const scannedText = window.currentScanResult.text;

                if (fieldId === 'package_codes_batch') {
                    // 批量模式：添加到文本框
                    addCodeToBatchTextarea(scannedText);
                } else {
                    // 单包模式：填入输入框
                    const targetElement = document.getElementById(fieldId);
                    if (targetElement) {
                        // 设置值
                        targetElement.value = scannedText;
                        // 手动触发change事件
                        targetElement.dispatchEvent(new Event('change'));
                    }
                }

                // 清理临时数据
                window.currentScanResult = null;
                // 关闭modal
                closeCameraModal();
            }
        }

        function scanCodeBatch() {
            currentFieldId = 'package_codes_batch';
            const deviceInfo = getDeviceInfo();

            // 检查是否支持摄像头
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                fallbackToManualInputBatch('您的浏览器不支持摄像头功能');
                return;
            }

            // 特殊处理小米浏览器和其他可能有问题的浏览器
            if (deviceInfo.isMiui || deviceInfo.isWeChat || deviceInfo.isQQ) {
                // 先尝试摄像头，如果失败则降级
                tryCameraBatch(true);
            } else if (deviceInfo.isAndroid || deviceInfo.isMobile) {
                // 其他移动设备直接尝试摄像头
                tryCameraBatch(false);
            } else {
                // PC设备降级到手动输入
                fallbackToManualInputBatch('请使用移动设备进行扫码');
            }
        }

        // 批量模式尝试调用摄像头
        function tryCameraBatch(allowFallback = true) {
            // 先检查权限
            if (navigator.permissions) {
                navigator.permissions.query({
                        name: 'camera'
                    })
                    .then(function(result) {
                        if (result.state === 'granted') {
                            startCameraBatch();
                        } else if (result.state === 'prompt') {
                            startCameraBatch();
                        } else {
                            if (allowFallback) {
                                fallbackToManualInputBatch('摄像头权限被拒绝');
                            }
                        }
                    })
                    .catch(function(error) {
                        console.log('权限查询失败:', error);
                        if (allowFallback) {
                            fallbackToManualInputBatch('权限查询失败');
                        } else {
                            startCameraBatch();
                        }
                    });
            } else {
                startCameraBatch();
            }
        }

        // 批量模式启动摄像头
        function startCameraBatch() {
            openCameraModal();
            startCamera()
                .then(function() {
                    console.log('批量扫描摄像头启动成功');
                })
                .catch(function(error) {
                    console.error('批量扫描摄像头启动失败:', error);
                    closeCameraModal();
                    fallbackToManualInputBatch('摄像头启动失败: ' + error.message);
                });
        }

        // 批量模式手动输入降级
        function fallbackToManualInputBatch(reason) {
            console.log('批量扫描降级到手动输入:', reason);
            const code = prompt('摄像头不可用(' + reason + ')，请手动输入包号:');
            if (code && code.trim()) {
                addCodeToBatchTextarea(code.trim());
            }
        }

        // 将扫描结果添加到批量文本框
        function addCodeToBatchTextarea(code) {
            const textarea = document.getElementById('package_codes_batch');
            const currentValue = textarea.value.trim();

            // 检查是否已存在该包号
            const lines = currentValue.split('\n').map(line => line.trim()).filter(line => line);
            if (lines.includes(code)) {
                alert('包号 "' + code + '" 已存在，请勿重复添加');
                return;
            }

            // 添加新包号
            if (currentValue) {
                textarea.value = currentValue + '\n' + code;
            } else {
                textarea.value = code;
            }

            // 滚动到底部
            textarea.scrollTop = textarea.scrollHeight;

            // 提示添加成功
            showTempMessage('已添加包号: ' + code);
        }

        // 显示临时提示消息
        function showTempMessage(message) {
            // 创建提示元素
            const msgDiv = document.createElement('div');
            msgDiv.textContent = message;
            msgDiv.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #4CAF50;
                color: white;
                padding: 10px 20px;
                border-radius: 5px;
                z-index: 10000;
                font-size: 14px;
            `;

            document.body.appendChild(msgDiv);

            // 2秒后移除
            setTimeout(() => {
                if (msgDiv.parentNode) {
                    msgDiv.parentNode.removeChild(msgDiv);
                }
            }, 2000);
        }

        function onScanSuccess(decodedText, decodedResult) {
            console.log('扫描成功:', decodedText);
            stopCamera();
            closeCameraModal();

            if (currentFieldId === 'package_codes_batch') {
                // 批量模式：添加到文本框
                addCodeToBatchTextarea(decodedText);
            } else {
                // 单包模式：填入输入框
                const targetField = document.getElementById(currentFieldId);
                if (targetField) {
                    targetField.value = decodedText;
                    targetField.focus();
                }
            }
        }
        // 自动填充包装数量
        document.getElementById('pieces').addEventListener('input', function() {
            const pieces = parseInt(this.value);
            const quantityInput = document.getElementById('quantity');
            if (pieces > 0 && !quantityInput.value) {
                quantityInput.value = pieces;
            }
        });
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                initializeCodeReader();
            }, 500);

            // 页面可见性变化处理
            document.addEventListener('visibilitychange', function() {
                if (document.hidden && currentStream) {
                    // 页面隐藏时停止摄像头
                    stopCamera();
                }
            });

            // 页面卸载时清理资源
            window.addEventListener('beforeunload', function() {
                stopCamera();
            });
            const thicknessFilter = document.getElementById('thickness_filter');
            const colorFilter = document.getElementById('color_filter');
            const brandFilter = document.getElementById('brand_filter');
            const glassTypeSelect = document.getElementById('glass_type_id');

            // 获取所有原片类型选项
            const allOptions = Array.from(glassTypeSelect.options).slice(1); // 排除第一个提示选项

            function updateFilters() {
                const selectedThickness = thicknessFilter.value;
                const selectedColor = colorFilter.value;
                const selectedBrand = brandFilter.value;

                // 筛选符合条件的选项
                const filteredOptions = allOptions.filter(option => {
                    const thickness = option.dataset.thickness;
                    const color = option.dataset.color;
                    const brand = option.dataset.brand;

                    return (!selectedThickness || thickness == selectedThickness) &&
                        (!selectedColor || color == selectedColor) &&
                        (!selectedBrand || brand == selectedBrand);
                });

                // 隐藏所有选项
                allOptions.forEach(option => {
                    option.style.display = 'none';
                    option.selected = false;
                });

                // 显示符合条件的选项
                filteredOptions.forEach(option => {
                    option.style.display = 'block';
                });

                // 更新提示文本
                const firstOption = glassTypeSelect.options[0];
                if (filteredOptions.length === 0) {
                    firstOption.textContent = '无符合条件的原片类型';
                } else {
                    firstOption.textContent = `请选择原片类型 (${filteredOptions.length}个选项)`;
                }

                // 如果只有一个选项，自动选中
                if (filteredOptions.length === 1) {
                    filteredOptions[0].selected = true;
                    glassTypeSelect.dispatchEvent(new Event('change'));
                }

                // 更新其他筛选器的可选项
                updateFilterOptions();
            }

            function updateFilterOptions() {
                const selectedThickness = thicknessFilter.value;
                const selectedColor = colorFilter.value;
                const selectedBrand = brandFilter.value;

                // 获取当前筛选条件下可用的选项
                const availableOptions = allOptions.filter(option => {
                    const thickness = option.dataset.thickness;
                    const color = option.dataset.color;
                    const brand = option.dataset.brand;

                    return (!selectedThickness || thickness == selectedThickness) &&
                        (!selectedColor || color == selectedColor) &&
                        (!selectedBrand || brand == selectedBrand);
                });

                // 更新厚度选项
                const availableThickness = [...new Set(availableOptions.map(opt => opt.dataset.thickness))];
                updateSelectOptions(thicknessFilter, availableThickness, selectedThickness, 'mm');

                // 更新颜色选项
                const availableColors = [...new Set(availableOptions.map(opt => opt.dataset.color))];
                updateSelectOptions(colorFilter, availableColors, selectedColor);

                // 更新品牌选项
                const availableBrands = [...new Set(availableOptions.map(opt => opt.dataset.brand))];
                updateSelectOptions(brandFilter, availableBrands, selectedBrand);
            }

            function updateSelectOptions(selectElement, availableValues, selectedValue, suffix = '') {
                const options = Array.from(selectElement.options).slice(1); // 排除第一个"全部"选项

                options.forEach(option => {
                    const isAvailable = availableValues.includes(option.value);
                    option.style.display = isAvailable ? 'block' : 'none';
                    option.disabled = !isAvailable;

                    if (!isAvailable && option.selected) {
                        option.selected = false;
                        selectElement.value = '';
                    }
                });
            }

            // 绑定事件监听器
            thicknessFilter.addEventListener('change', updateFilters);
            colorFilter.addEventListener('change', updateFilters);
            brandFilter.addEventListener('change', updateFilters);

            // 初始化筛选
            updateFilters();

            // 重置筛选器
            window.resetGlassTypeFilters = function() {
                thicknessFilter.value = '';
                colorFilter.value = '';
                brandFilter.value = '';
                updateFilters();
            };
        });
        // 切换输入模式
        function toggleInputMode(mode) {
            if (mode === 'single') {
                document.getElementById('single_input_mode').style.display = 'block';
                document.getElementById('batch_input_mode').style.display = 'none';
                document.getElementById('package_code').setAttribute('required', 'required');
                document.getElementById('package_codes_batch').removeAttribute('required');
            } else {
                document.getElementById('single_input_mode').style.display = 'none';
                document.getElementById('batch_input_mode').style.display = 'block';
                document.getElementById('package_code').removeAttribute('required');
                document.getElementById('package_codes_batch').setAttribute('required', 'required');
            }
        }

        // 防止表单重复提交
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = '保存中...';

            setTimeout(function() {
                submitBtn.disabled = false;
                submitBtn.textContent = '保存';
            }, 3000);
        });
    </script>
</body>

</html>