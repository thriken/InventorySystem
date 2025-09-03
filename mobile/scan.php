<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/inventory_operations.php'; // 引入公共业务逻辑

// 要求用户登录
requireLogin();

// 获取当前用户信息
$currentUser = getCurrentUser();

// 获取所有基地
$bases = fetchAll("SELECT id, name FROM bases ORDER BY name");

// 处理AJAX请求 - 获取包信息
if (isset($_GET['action']) && $_GET['action'] === 'get_package_info') {
    $packageCode = trim($_GET['package_code'] ?? '');
    $baseName = trim($_GET['base_name'] ?? '');
    $result = getPackageInfo($packageCode); // 使用公共函数
    jsonResponse($result);
}

// 处理AJAX请求 - 获取目标架信息并判断操作类型
if (isset($_GET['action']) && $_GET['action'] === 'get_target_info') {
    $targetRackCode = trim($_GET['target_rack_code'] ?? '');
    $currentAreaType = $_GET['current_area_type'] ?? '';
    $baseName = $_GET['base_name'] ?? '';
    $result = getTargetRackInfo($targetRackCode, $currentAreaType,$baseName);
    jsonResponse($result);
}
// 处理表单提交
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $packageCode = trim($_POST['package_code'] ?? '');
        $base_name = trim($_POST['base_name'] ?? '');
        $RackCode = trim($_POST['target_rack_code'] ?? '');
        // 优先使用完整的rack_code，如果没有则使用用户输入的简化代码
        $fullRackCode = trim($_POST['full_rack_code'] ?? '');
        $targetRackCode = !empty($fullRackCode) ? $fullRackCode : $RackCode;

        $quantity = intval($_POST['quantity'] ?? 0);
        $transactionType = $_POST['transaction_type'] ?? '';
        $scrapReason = trim($_POST['scrap_reason'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (empty($packageCode) || empty($targetRackCode) || $quantity < 0 || empty($transactionType)) {
            throw new Exception('请填写所有必填字段');
        }

        if ($transactionType === 'scrap' && empty($scrapReason)) {
            throw new Exception('报废操作必须填写报废原因');
        }

        $result = executeInventoryTransaction(
            $packageCode,
            $targetRackCode,
            $quantity,
            $transactionType,
            $currentUser,    // 移到第5位
            $scrapReason,    // 移到第6位（可选参数）
            $notes           // 移到第7位（可选参数）
        );

        $message = $result;
        $messageType = 'success';
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
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
    <title>扫描操作 - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/mobile.css">
    <!-- 添加二维码扫描库 -->
    <script src="https://unpkg.com/@zxing/library@latest/umd/index.min.js"></script>
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
    /* 只保留scan.php特有的样式 */
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
        font-size: 18px;
    }

    .content {
        padding: 80px 15px 20px;
        margin-bottom: 57px;
    }

    .scan-form {
        background: white;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    /* 重写表单控件样式以适应移动端 */
    input[type="text"],
    input[type="number"],
    select,
    textarea {
        font-size: 16px; /* 防止iOS缩放 */
        padding: 10px; /* 比main.css更大的padding */
    }

    .scan-button {
        background: #2196F3;
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 4px;
        margin-left: 5px;
        font-size: 14px;
    }

    .input-with-scan {
        display: flex;
    }

    .input-with-scan input {
        flex: 1;
    }

    .submit-button {
        background: #4CAF50;
        color: white;
        border: none;
        padding: 12px;
        border-radius: 4px;
        width: 100%;
        font-size: 16px;
        font-weight: bold;
        margin-top: 10px;
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
</head>

<body>
    <div class="header">
        <a href="index.php" class="back-button">←</a>
        <h1>扫描操作</h1>
    </div>

    <div class="content">
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form class="scan-form" method="post">
            <div class="form-group">
                <label for="package_code">包号/二维码</label>
                <div class="input-with-scan">
                    <input type="text" id="package_code" name="package_code" required onchange="getPackageInfo()">
                    <button type="button" class="scan-button" onclick="scanBarcode('package_code')">扫描</button>
                </div>
            </div>
            <!-- 包信息显示区域 -->
            <div class="package-info hidden" id="package-info">
                <div class="info-row">
                    <span class="info-label">原片名称:</span>
                    <span class="info-value" id="glass-name">-</span>
                </div>
                <div class="info-row">
                    <span class="info-label">当前数量:</span>
                    <span class="info-value" id="current-quantity">-</span>
                </div>
                <div class="info-row">
                    <span class="info-label">当前架号:</span>
                    <span class="info-value" id="current-rack">-</span>
                </div>
                <div class="info-row">
                    <span class="info-label">当前区域:</span>
                    <span class="info-value" id="current-area">-</span>
                </div>
                <div class="info-row">
                    <span class="info-label">所属基地:</span>
                    <span class="info-value" id="current-base">-</span>
                </div>
            </div>

            <div class="form-group">
                <label for="target_rack_code">目标架号</label>
                <div class="input-with-scan">
                    <input type="text" id="base_name" name="base_name" value="" required readonly hidden >
                    <input type="text" id="target_rack_code" name="target_rack_code" autocomplete=“new-password” required onchange="getTargetInfo()">
                    <button type="button" class="scan-button" onclick="scanBarcode('target_rack_code')">扫描</button>
                </div>
            </div>

            <div class="auto-detected" id="auto-detected" style="display: block;">
                <strong>自动检测操作类型:</strong> <span id="detected-operation"></span>
            </div>

            <div class="form-group">
                <label for="quantity">数量</label>
                <input type="number" id="quantity" name="quantity" min="0" required autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="transaction_type">操作类型</label>
                <select id="transaction_type" name="transaction_type" required onchange="toggleScrapReason()">
                    <option value="">请选择操作类型</option>
                    <option value="purchase_in">采购入库</option>
                    <option value="usage_out">领用出库</option>
                    <option value="return_in">归还入库</option>
                    <option value="scrap">报废</option>
                    <option value="location_adjust">库区转移</option>
                </select>
            </div>

            <div class="form-group hidden" id="scrap_reason_group">
                <label for="scrap_reason">报废原因</label>
                <textarea id="scrap_reason" name="scrap_reason" rows="3"></textarea>
            </div>

            <div class="form-group hidden" id="notes_group">
                <label for="notes">备注</label>
                <textarea id="notes" name="notes" rows="3" placeholder="请输入备注信息（可选）"></textarea>
            </div>

            <button type="submit" class="submit-button">提交</button>
        </form>
    </div>
    <div class="mobile-footer">
        <a href="index.php">🏠<br>首页</a>
        <a href="scan.php">📷<br>扫描</a>
        <a href="history.php">📋<br>记录</a>
        <a href="../logout.php">🚪<br>退出</a>
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
                <strong>扫描结果：</strong>
                <span id="scan-result-text" onclick="confirmScanResult()" style="cursor: pointer; color: #007bff; text-decoration: underline;"></span>
                <br><small style="color: #666;">点击结果确认使用</small>
            </div>
        </div>
    </div>
    <script src="../assets/js/constants.js"></script>
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

        // 切换报废原因和备注显示
        function toggleScrapReason() {
            const transactionType = document.getElementById('transaction_type').value;
            const scrapReasonGroup = document.getElementById('scrap_reason_group');
            const notesGroup = document.getElementById('notes_group');

            if (transactionType === 'scrap') {
                if (scrapReasonGroup) {
                    scrapReasonGroup.classList.remove('hidden');
                }
            } else {
                if (scrapReasonGroup) {
                    scrapReasonGroup.classList.add('hidden');
                }
            }

            // 只有当notes_group元素存在时才操作
            if (notesGroup) {
                if (transactionType === 'return_in') {
                    notesGroup.classList.remove('hidden');
                } else {
                    notesGroup.classList.add('hidden');
                }
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
        function scanBarcode(fieldId) {
            currentFieldId = fieldId;
            const deviceInfo = getDeviceInfo();

            // 检查是否支持摄像头
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                fallbackToManualInput(fieldId, '您的浏览器不支持摄像头功能');
                return;
            }

            // 特殊处理小米浏览器和其他可能有问题的浏览器
            if (deviceInfo.isMiui || deviceInfo.isWeChat || deviceInfo.isQQ) {
                // 先尝试摄像头，如果失败则降级
                tryCamera(fieldId, true);
            } else if (deviceInfo.isAndroid || deviceInfo.isMobile) {
                // 其他移动设备直接尝试摄像头
                tryCamera(fieldId, false);
            } else {
                // PC设备降级到手动输入
                fallbackToManualInput(fieldId, '请使用移动设备进行扫码');
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
                    }, // 更高分辨率有助于条形码识别
                    height: {
                        min: 480,
                        ideal: 720,
                        max: 1080
                    },
                    frameRate: {
                        ideal: 10,
                        max: 30
                    }, // 更高帧率
                    focusMode: 'continuous' // 连续对焦
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

        function getTargetInfo() {
            const targetRackPrefix = document.getElementById('base_name');
            const targetRackCode = document.getElementById('target_rack_code').value.trim();
            const currentAreaType = window.currentPackageInfo ? window.currentPackageInfo.current_area_type : '';
            const operationNames = {
                                'purchase_in': '采购入库',
                                'usage_out': '领用出库',
                                'return_in': '归还入库',
                                'scrap': '报废',
                                'location_adjust': '库位转移'
                            };
            if (!targetRackCode) {
                document.getElementById('auto-detected').style.display = 'none';
                return;
            }
            const url = `scan.php?action=get_target_info&base_name=${encodeURIComponent(targetRackPrefix.value.trim())}&target_rack_code=${encodeURIComponent(targetRackCode)}&current_area_type=${encodeURIComponent(currentAreaType)}`;
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    console.log('目标库位信息:', data);
                    if (data.success) {
                        const autoDetected = document.getElementById('auto-detected');
                        const detectedOperation = document.getElementById('detected-operation');

                        let fullRackCodeInput = document.getElementById('full_rack_code');
                        if (!fullRackCodeInput) {
                            fullRackCodeInput = document.createElement('input');
                            fullRackCodeInput.type = 'hidden';
                            fullRackCodeInput.id = 'full_rack_code';
                            fullRackCodeInput.name = 'full_rack_code';
                            document.querySelector('.scan-form').appendChild(fullRackCodeInput);
                        }
                        fullRackCodeInput.value = data.data.rack_code; // 保存完整的code
                        
                        if (data.data && data.data.transaction_type) {
                            detectedOperation.textContent = operationNames[data.data.transaction_type] || data.data.transaction_type;
                            autoDetected.style.display = 'block';
                            const transactionTypeSelect = document.getElementById('transaction_type');
                            if (transactionTypeSelect) {
                                transactionTypeSelect.value = data.data.transaction_type;
                                transactionTypeSelect.dispatchEvent(new Event('change'));
                            }
                        } else {
                            autoDetected.style.display = 'none';
                        }
                    } else {
                        const autoDetected = document.getElementById('auto-detected');
                        const detectedOperation = document.getElementById('detected-operation');
                        detectedOperation.textContent = data.message || '获取目标库位信息失败';
                        autoDetected.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('获取目标库位信息失败:', error);
                    const autoDetected = document.getElementById('auto-detected');
                    const detectedOperation = document.getElementById('detected-operation');
                    detectedOperation.textContent = '获取目标库位信息失败，请重试';
                    autoDetected.style.display = 'block';
                });
        }
        // 获取包信息函数
        function getPackageInfo() {
            const targetRackPrefix = document.getElementById('base_name');

            const packageCode = document.getElementById('package_code').value.trim();
            const packageInfo = document.getElementById('package-info');
            if (!packageCode) {
                packageInfo.classList.add('hidden');
                return;
            }
            packageInfo.classList.remove('hidden');
            packageInfo.innerHTML = '<p>正在查询包信息...</p>';
            packageInfo.style.display = 'block';
            fetch(`scan.php?action=get_package_info&package_code=${encodeURIComponent(packageCode)}`)
                .then(response => response.json())
                .then(data => {
                    console.log('AJAX响应数据:', data); // 调试信息
                    if (data.success) {
                        const pkg = data.data;
                        packageInfo.innerHTML = `
                                <h3>包信息</h3>
                                <p><strong>包号:</strong> ${pkg.package_code}</p>
                                <p><strong>玻璃类型:</strong> ${pkg.glass_name || '未知'}</p>
                                <p><strong>片数:</strong> ${pkg.pieces} </p>
                                <p><strong>当前架号:</strong> ${pkg.current_rack_code}</p>
                                <!-- <p><strong>包装数量:</strong> ${pkg.quantity}</p>>
                                <p><strong>基地:</strong> ${pkg.base_name}</p -->
                                <p><strong>状态:</strong> ${getStatusName(pkg.status)}</p>
                            `;
                        window.currentPackageInfo = pkg;
                        document.getElementById('quantity').value= pkg.pieces;
                        targetRackPrefix.value = pkg.base_name;
                    } else {
                        packageInfo.innerHTML = `<p class="error">${data.message}</p>`;
                    }
                })
                .catch(error => {
                    console.error('获取包信息失败:', error);
                    packageInfo.innerHTML = '<p class="error">获取包信息失败，请重试</p>';
                });
        }

        // 获取区域类型名称
        function getAreaTypeName(areaType) {
            const areaTypes = {
                'purchase': '采购入库区',
                'storage': '存储区',
                'usage': '领用出库区',
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
        // 确认扫码结果
        function confirmScanResult() {
            if (window.currentScanResult && window.currentScanResult.fieldId) {
                const targetElement = document.getElementById(window.currentScanResult.fieldId);
                if (targetElement) {
                    // 设置值
                    targetElement.value = window.currentScanResult.text;
                    
                    // 手动触发change事件
                    targetElement.dispatchEvent(new Event('change'));
                    
                    // 如果是target_rack_code字段，还需要触发input事件
                    if (window.currentScanResult.fieldId === 'target_rack_code') {
                        targetElement.dispatchEvent(new Event('input'));
                    }
                }
                // 清理临时数据
                window.currentScanResult = null;
                // 关闭modal
                closeCameraModal();
            }
        }
        // 页面加载完成后初始化
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                initializeCodeReader();
            }, 500);

            // 检测安卓扫码枪
            let scanBuffer = '';
            let scanTimeout = null;

            document.addEventListener('keydown', function(e) {
                const activeElement = document.activeElement;

                // 检测扫码枪输入（快速连续输入字符）
                if (activeElement && (activeElement.id === 'package_code' || activeElement.id === 'target_rack_code')) {
                    if (e.key === 'Enter') {
                        // 扫码枪输入完成
                        e.preventDefault();
                        activeElement.blur();
                        scanBuffer = '';
                    } else if (e.key && e.key.length === 1) {
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

            // 页面可见性变化处理
            document.addEventListener('visibilitychange', function() {
                if (document.hidden && currentStream) {
                    // 页面隐藏时停止摄像头
                    stopCamera();
                }
            });
            // 获取目标库位信息函数
            // 页面卸载时清理资源
            window.addEventListener('beforeunload', function() {
                stopCamera();
            });
        });
        
    </script>
    </div>
</body>

</html>

