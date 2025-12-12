<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/inventory_operations.php';
require_once '../includes/app_info.php';

// 要求用户登录
requireLogin();

// 获取当前用户信息
$currentUser = getCurrentUser();

// 处理AJAX请求 - 获取包信息
if (isset($_GET['action']) && $_GET['action'] === 'get_package_info') {
    $packageCode = trim($_GET['package_code'] ?? '');
    $result = getPackageInfo($packageCode);
    jsonResponse($result);
}

// 处理表单提交 - 基地间流转
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $packageCode = trim($_POST['package_code'] ?? '');
        $targetBaseId = intval($_POST['target_base_id'] ?? 0);
        $transactionType = $_POST['transaction_type'] ?? '';
        $rackCode = trim($_POST['rack_code'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        // 验证必填字段
        if (empty($packageCode)) {
            throw new Exception('请输入包号');
        }

        if ($targetBaseId <= 0) {
            throw new Exception('请选择目标基地');
        }

        if (empty($transactionType)) {
            throw new Exception('请选择流转类型');
        }

        // 获取包信息
        $packageInfo = getPackageInfo($packageCode);
        if (!$packageInfo['success']) {
            throw new Exception($packageInfo['message']);
        }

        $package = $packageInfo['data'];

        // 检查是否为整包流转
        $currentPieces = intval($_POST['current_pieces'] ?? 0);
        if ($currentPieces != $package['pieces']) {
            throw new Exception('基地间流转只支持整包操作');
        }


        // 流转操作 - 需要目标货架
        if (empty($rackCode)) {
            throw new Exception('请输入目标货架代码');
        }

        // 验证目标货架是否属于目标基地
        $targetRack = fetchRow(
            "SELECT r.*, b.name as base_name FROM storage_racks r 
                 LEFT JOIN bases b ON r.base_id = b.id 
                 WHERE r.code = ? AND r.base_id = ?",
            [$rackCode, $targetBaseId]
        );

        if (!$targetRack) {
            throw new Exception('目标货架不存在或不属于选择的基地');
        }

        // 执行流转操作
        $result = executeInventoryTransaction(
            $packageCode,
            $rackCode,
            $package['pieces'],
            'location_adjust', // 基地间流转使用库位调整
            $currentUser,
            '',
            $notes
        );

        $message = $result;
        $messageType = 'success';

        // 成功后返回JSON响应（用于AJAX提交）
        if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
            jsonResponse(['success' => true, 'message' => $result]);
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';

        // 错误时返回JSON响应（用于AJAX提交）
        if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
            jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}

// 获取当前用户的基地信息
$currentUserBase = fetchRow("SELECT base_id FROM users WHERE id = ?", [$currentUser['id']]);
$currentBaseId = $currentUserBase['base_id'];

// 获取当前用户的基地信息
$currentUserBase = fetchRow("SELECT base_id FROM users WHERE id = ?", [$currentUser['id']]);
$currentBaseId = $currentUserBase['base_id'];

// 获取基地列表
if ($currentUser['role'] === 'admin') {
    // 管理员可以看到所有基地
    $bases = fetchAll("SELECT id, name FROM bases ORDER BY name");
} elseif ($currentUser['role'] === 'manager') {
    // 管理者排除自己的基地
    if ($currentBaseId) {
        $bases = fetchAll("SELECT id, name FROM bases WHERE id != ? ORDER BY name", [$currentBaseId]);
    } else {
        $bases = fetchAll("SELECT id, name FROM bases ORDER BY name");
    }
} else {
    // 操作员排除自己的基地
    if ($currentBaseId) {
        $bases = fetchAll("SELECT id, name FROM bases WHERE id != ? ORDER BY name", [$currentBaseId]);
    } else {
        $bases = fetchAll("SELECT id, name FROM bases ORDER BY name");
    }
}

// 获取交易类型
$transactionTypes = [
    'location_adjust' => '基地流转',
];
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>流转操作 - <?php echo getAppName(); ?></title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/mobile.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }

        .header {
            background-color: #4CAF50;
            color: white;
            padding: 15px;
            text-align: center;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            margin: 0;
            font-size: 20px;
        }

        .back-button {
            position: absolute;
            left: 15px;
            top: 15px;
            color: white;
            text-decoration: none;
            font-size: 16px;
        }

        .content {
            margin-top: 80px;
            padding: 15px;
            margin-bottom: 60px;
        }

        .transfer-container {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .scan-button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 15px;
            width: 100%;
            border-radius: 4px;
            font-size: 16px;
            margin-bottom: 20px;
            cursor: pointer;
        }

        .scan-button:hover {
            background-color: #45a049;
        }

        .submit-button {
            background-color: #2196F3;
            color: white;
            border: none;
            padding: 15px;
            width: 100%;
            border-radius: 4px;
            font-size: 16px;
            margin-top: 20px;
            cursor: pointer;
        }

        .submit-button:hover {
            background-color: #0b7dda;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #4CAF50;
            outline: none;
        }

        .footer {
            background-color: #f1f1f1;
            padding: 15px;
            text-align: center;
            font-size: 12px;
            color: #666;
            position: fixed;
            bottom: 0;
            width: 100%;
        }

        .hidden {
            display: none;
        }

        .package-info {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .package-info h3 {
            margin-top: 0;
            color: #4CAF50;
        }

        .package-info p {
            margin: 5px 0;
        }

        /* 摄像头扫描相关样式 */
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
        }

        .camera-controls {
            margin-top: 15px;
            text-align: center;
        }

        .camera-button {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            margin: 0 5px;
            font-size: 14px;
        }

        .scan-result {
            margin-top: 10px;
            padding: 10px;
            background: #e8f5e9;
            border-radius: 4px;
            display: none;
        }
    </style>
    <!-- 添加二维码扫描库 -->
    <script src="https://cdn.bootcdn.net/ajax/libs/@zxing/library/latest/umd/index.min.js"></script>
</head>

<body>
    <div class="header">
        <a href="index.php" class="back-button">返回</a>
        <h1>流转操作</h1>
    </div>

    <div class="content">
        <div class="transfer-container">
            <form id="transferForm" action="transfer.php" method="post">

                <input type="hidden" name="action" value="transfer">
                <input type="hidden" id="packageId" name="package_id" value="">
                <div class="form-group">
                    <label for="package_code">包号/二维码</label>
                    <input type="text" id="package_code" name="package_code" required onchange="getPackageInfo()">
                    <button type="button" id="scanPackageButton" class="scan-button" onclick="scanQRCode('package_code')">扫描</button>
                </div>
                <div id="packageInfo" class="package-info hidden">
                </div>
                <div class="form-group">
                    <label for="transactionType">流转类型</label>
                    <select id="transactionType" name="transaction_type" required>
                        <option value="">请选择流转类型</option>
                        <?php foreach ($transactionTypes as $value => $label): ?>
                            <option selected value="<?php echo $value; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="baseId">目标基地</label>
                    <select id="baseId" name="target_base_id" required>
                        <option value="">请选择基地</option>
                        <?php foreach ($bases as $base): ?>
                            <option value="<?php echo $base['id']; ?>"><?php echo $base['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group hidden" id="rackGroup">
                    <label for="rackCode">目标货架</label>
                    <input type="text" id="rackCode" name="rack_code" placeholder="请输入或扫描货架代码">
                    <button type="button" id="scanRackButton" class="scan-button" style="margin-top: 10px;">扫描货架二维码</button>
                </div>

                <div class="form-group">
                    <label for="notes">备注</label>
                    <textarea id="notes" name="notes" rows="2" placeholder="自动生成流转备注信息" readonly style="background-color: #f5f5f5;"></textarea>
                </div>
                <button type="submit" class="submit-button">确认流转</button>
            </form>
        </div>
    </div>
    <!-- 摄像头扫描模态框 -->
    <div class="camera-modal" id="camera-modal">
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
                <strong>扫描结果：</strong><span id="scan-result-text"></span>
            </div>
        </div>
    </div>
    <div class="footer">
        <p>&copy; 2025 <?php echo getAppName(); ?>. 基地间流转操作</p>
    </div>

    <script>
        let currentStream = null;
        let currentFieldId = null;
        let codeReader = null;
        let currentFacingMode = 'environment'; // 默认后置摄像头
        let scanTimeout = null;
        let scanBuffer = '';

        // 初始化二维码扫描器
        function initializeCodeReader() {
            if (typeof ZXing !== 'undefined') {
                codeReader = new ZXing.BrowserQRCodeReader();
            } else {
                console.error('ZXing library not loaded');
            }
        }

        // 扫描二维码主函数
        function scanQRCode(fieldId) {
            currentFieldId = fieldId;
            const deviceInfo = getDeviceInfo();

            // 检查是否支持摄像头
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                fallbackToManualInput(fieldId, '您的浏览器不支持摄像头功能');
                return;
            }

            // 特殊处理小米浏览器和其他可能有问题的浏览器
            if (deviceInfo.isMiui || deviceInfo.isWeChat || deviceInfo.isQQ) {
                tryCamera(fieldId, true);
            } else if (deviceInfo.isAndroid || deviceInfo.isMobile) {
                tryCamera(fieldId, false);
            } else {
                fallbackToManualInput(fieldId, '请使用移动设备进行扫码');
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

        // 尝试调用摄像头
        function tryCamera(fieldId, allowFallback = true) {
            if (navigator.permissions) {
                navigator.permissions.query({
                        name: 'camera'
                    })
                    .then(function(result) {
                        if (result.state === 'granted') {
                            openCameraModal();
                        } else if (result.state === 'prompt') {
                            openCameraModal();
                        } else {
                            if (allowFallback) {
                                fallbackToManualInput(fieldId, '摄像头权限被拒绝，请手动输入');
                            } else {
                                alert('请在浏览器设置中允许摄像头权限');
                            }
                        }
                    })
                    .catch(function() {
                        openCameraModal();
                    });
            } else {
                openCameraModal();
            }
        }

        // 降级到手动输入
        function fallbackToManualInput(fieldId, message) {
            const result = prompt(message + '\n\n请手动输入二维码内容：');
            if (result && result.trim()) {
                document.getElementById(fieldId).value = result.trim();
                if (fieldId === 'package_code') {
                    getPackageInfo();
                }
            }
        }

        // 打开摄像头模态框
        function openCameraModal() {
            const modal = document.getElementById('camera-modal');
            modal.style.display = 'flex';
            if (!codeReader) {
                initializeCodeReader();
            }
            setTimeout(() => {
                startCamera();
            }, 100);
        }

        // 关闭摄像头模态框
        function closeCameraModal() {
            const modal = document.getElementById('camera-modal');
            modal.style.display = 'none';
            stopCamera();
            document.getElementById('scan-result').style.display = 'none';
        }

        // 启动摄像头
        function startCamera() {
            const video = document.getElementById('camera-video');

            const constraints = {
                video: {
                    facingMode: currentFacingMode,
                    width: {
                        min: 320,
                        ideal: 640,
                        max: 1280
                    },
                    height: {
                        min: 240,
                        ideal: 480,
                        max: 720
                    },
                    frameRate: {
                        ideal: 15,
                        max: 30
                    }
                },
                audio: false
            };

            navigator.mediaDevices.getUserMedia(constraints)
                .then(function(stream) {
                    currentStream = stream;
                    video.srcObject = stream;
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
                codeReader.decodeFromVideoDevice(undefined, video, function(result, err) {
                    if (result) {
                        handleScanSuccess(result.text);
                    }
                });
            }
        }

        // 处理扫描成功
        function handleScanSuccess(text) {
            document.getElementById('scan-result-text').textContent = text;
            document.getElementById('scan-result').style.display = 'block';

            // 填入对应字段
            document.getElementById(currentFieldId).value = text;

            // 如果是包号字段，自动获取包信息
            if (currentFieldId === 'package_code') {
                getPackageInfo();
            }

            // 延迟关闭模态框
            setTimeout(() => {
                closeCameraModal();
            }, 1000);
        }

        // 获取包信息函数
        function getPackageInfo() {
            const packageCode = document.getElementById('package_code').value.trim();

            if (!packageCode) {
                document.getElementById('package-info').style.display = 'none';
                return;
            }
            const packageInfo = document.getElementById('packageInfo');
            packageInfo.innerHTML = '<p>正在查询包信息...</p>';
            packageInfo.style.display = 'block';

            // 修改为当前页面处理
            fetch(`transfer.php?action=get_package_info&package_code=${encodeURIComponent(packageCode)}`)
                .then(response => response.json())
                .then(data => {
                    console.log('AJAX响应数据:', data);
                    if (data.success) {
                        const pkg = data.data;
                        console.log('包片数:', pkg.pieces, '类型:', typeof pkg.pieces);
                        packageInfo.innerHTML = `
                            <h3>包信息</h3>
                            <p><strong>包号:</strong> ${pkg.package_code}</p>
                            <p><strong>玻璃类型:</strong> ${pkg.glass_name || '未知'}</p>
                            <p><strong>片数:</strong> ${pkg.pieces}</p>
                            <p><strong>当前架号:</strong> ${pkg.current_rack_code}</p>
                            <p><strong>基地:</strong> ${pkg.base_name}</p>
                        `;
                        window.currentPackageInfo = pkg;
                    } else {
                        packageInfo.innerHTML = `<p class="error">${data.message}</p>`;
                    }
                })
                .catch(error => {
                    console.error('获取包信息失败:', error);
                    packageInfo.innerHTML = '<p class="error">获取包信息失败，请重试</p>';
                });
        }

        // 修改表单提交函数
        function submitTransfer() {
            const form = document.getElementById('transferForm');
            const formData = new FormData(form);

            // 添加AJAX标识
            formData.append('ajax', '1');

            // 如果不是报废，需要货架代码
            if (transactionType !== 'scrap') {
                const rackCode = document.getElementById('rackCode').value.trim();
                if (!rackCode) {
                    alert('请输入目标货架代码');
                    return;
                }
            }
            // 添加当前包信息到表单数据
            if (window.currentPackageInfo) {
                formData.append('current_pieces', window.currentPackageInfo.pieces);
                formData.append('current_base_id', window.currentPackageInfo.base_id);
                formData.append('current_rack_code', window.currentPackageInfo.current_rack_code);
            }

            // 提交到当前页面
            fetch('transfer.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    // 检查响应是否为JSON
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        throw new Error('服务器返回了非JSON响应，可能发生了错误');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('流转操作成功！');
                        // 重置表单
                        form.reset();
                        document.getElementById('packageInfo').style.display = 'none';
                        document.getElementById('rackGroup').classList.add('hidden');
                        window.currentPackageInfo = null;
                    } else {
                        console.log('流转失败:', data.message);
                        alert('流转操作失败：' + data.message);
                    }
                })
                .catch(error => {
                    console.error('提交失败:', error);
                    alert('提交失败，请重试：' + error.message);
                });
        }
        // 处理流转类型变化
        function handleTransactionTypeChange() {
            const transactionType = document.getElementById('transactionType').value;
            const rackGroup = document.getElementById('rackGroup');
            if (transactionType && transactionType !== 'scrap') {
                rackGroup.classList.remove('hidden');
            } else {
                rackGroup.classList.add('hidden');
            }
        }

        // 处理区域变化
        function handleAreaChange() {
            const rackGroup = document.getElementById('rackGroup');
            const transactionType = document.getElementById('transactionType').value;

            if (transactionType && transactionType !== 'scrap') {
                rackGroup.classList.remove('hidden');
            } else {
                rackGroup.classList.add('hidden');
            }
        }
        // 更新流转备注信息
        function updateTransferNotes() {
            const baseSelect = document.getElementById('baseId');
            const rackInput = document.getElementById('rackCode');
            const notesTextarea = document.getElementById('notes');

            if (window.sourceBaseInfo && baseSelect.value && rackInput.value) {
                const targetBaseName = baseSelect.options[baseSelect.selectedIndex].text;
                const targetLocation = rackInput.value;

                const notes = `流转原片: ${window.sourceBaseInfo.baseName}${window.sourceBaseInfo.location} -> ${targetBaseName}${targetLocation}`;
                notesTextarea.value = notes;
            } else if (window.sourceBaseInfo) {
                // 如果只有源信息，显示部分备注
                notesTextarea.value = `流转原片: ${window.sourceBaseInfo.baseName}${window.sourceBaseInfo.location} -> 待选择目标位置`;
            }
        }
        // 新增函数：更新基地选择框
        function updateBaseOptions(currentBaseName) {
            const baseSelect = document.getElementById('baseId');
            const options = baseSelect.querySelectorAll('option');

            options.forEach(option => {
                if (option.textContent === currentBaseName) {
                    option.style.display = 'none'; // 隐藏当前基地选项
                    option.disabled = true;
                } else {
                    option.style.display = 'block';
                    option.disabled = false;
                }
            });
        }
        document.addEventListener('DOMContentLoaded', function() {
            // 延迟初始化二维码扫描器
            setTimeout(() => {
                initializeCodeReader();
            }, 500);

            document.getElementById('scanRackButton').addEventListener('click', function() {
                scanQRCode('rackCode');
            });

            // 绑定表单提交事件
            document.getElementById('transferForm').addEventListener('submit', function(e) {
                e.preventDefault();
                submitTransfer();
            });

            // 绑定流转类型变化事件
            document.getElementById('transactionType').addEventListener('change', handleTransactionTypeChange);

            // 绑定目标基地变化事件
            document.getElementById('baseId').addEventListener('change', function() {
                handleAreaChange();
                updateTransferNotes();
            });

            // 绑定货架代码变化事件
            document.getElementById('rackCode').addEventListener('input', updateTransferNotes);

            document.addEventListener('keydown', function(e) {
                const activeElement = document.activeElement;

                // 检测扫码枪输入（快速连续输入字符）
                if (activeElement && (activeElement.id === 'package_code' || activeElement.id === 'rackCode')) {
                    if (e.key === 'Enter') {
                        // 扫码枪输入完成
                        e.preventDefault();
                        activeElement.blur();
                        scanBuffer = '';

                        // 如果是包号字段，自动获取包信息
                        if (activeElement.id === 'package_code') {
                            getPackageInfo();
                        }
                        // 如果是货架代码，更新备注
                        if (activeElement.id === 'rackCode') {
                            updateTransferNotes();
                        }
                    } else if (e.key && e.key.length === 1) { // 添加 e.key 存在性检查
                        // 累积字符
                        scanBuffer += e.key;
                        // 清除之前的超时
                        if (scanTimeout) {
                            clearTimeout(scanTimeout);
                        }
                        // 设置新的超时，如果500ms内没有新输入，认为不是扫码枪
                        scanTimeout = setTimeout(() => {
                            scanBuffer = '';
                        }, 500);
                    }
                }
            });
            document.addEventListener('visibilitychange', function() {
                if (document.hidden && currentStream) {
                    stopCamera();
                }
            });
            window.addEventListener('beforeunload', function() {
                stopCamera();
            });
        });
    </script>

</body>

</html>