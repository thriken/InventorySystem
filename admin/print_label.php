<?php
require_once '../config/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/inventory_operations.php';

// 要求用户登录
requireLogin();
// 检查是否为管理员或经理
requireRole(['admin', 'manager']);

// 获取当前用户信息（用于日志记录）
$currentUser = getCurrentUser();

// 获取要打印的包ID
$packageIds = $_GET['package_ids'] ?? '';
if (empty($packageIds)) {
    header('Location: packages.php');
    exit;
}

$packageIdArray = explode(',', $packageIds);
$packages = [];

foreach ($packageIdArray as $packageId) {
    $packageId = (int)trim($packageId);
    if ($packageId > 0) {
        $package = fetchRow("
            SELECT p.*, g.name as glass_name, g.short_name as glass_short_name, 
                   g.brand as glass_brand, g.color as glass_color,
                   r.code as rack_code, ir.code as initial_rack_code,
                   CONCAT(ROUND(p.width,0), 'x', ROUND(p.height,0)) as specification
            FROM glass_packages p
            LEFT JOIN glass_types g ON p.glass_type_id = g.id
            LEFT JOIN storage_racks r ON p.current_rack_id = r.id
            LEFT JOIN storage_racks ir ON p.initial_rack_id = ir.id
            WHERE p.id = ?
        ", [$packageId]);
        
        if ($package) {
            $packages[] = $package;
        }
    }
}

if (empty($packages)) {
    header('Location: packages.php');
    exit;
}

ob_start();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>打印包标签</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        /* 移除打印样式，改用C-LODOP控制 */
        
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 5mm;
            background: #f5f5f5;
        }
        
        .label-container {
            width: 78mm;
            height: 38mm;
            border: 1px solid #ccc;
            background: white;
            page-break-inside: avoid;
            margin-bottom: 2mm;
            display: flex;
            padding: 1mm;
            box-sizing: border-box;
        }
        
        .label-left {
            width: 30mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            border-right: 1px dashed #ccc;
            padding-right: 2mm;
        }
        
        .label-right {
            flex: 1;
            padding-left: 2mm;
            display: flex;
            flex-direction: column;
            justify-content: space-evenly;
            font-family: "幼圆", "YouYuan", "微软雅黑", "Microsoft YaHei", sans-serif;
        }
        
        .qrcode {
            width: 25mm;
            height: 25mm;
            border: 1px solid #ddd;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1mm;
            position: relative;
        }
        
        .qrcode-container {
            width: 25mm;
            height: 25mm;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1mm;
        }
        
        #qrcode {
            width: 20mm !important;
            height: 20mm !important;
        }
        
        #qrcode img {
            width: 100% !important;
            height: 100% !important;
        }
        
        .print-date {
            font-size: 6px;
            color: #666;
            text-align: center;
        }
        
        .info-row {
            margin-bottom: 1mm;
            font-size: 9px;
            line-height: 1.1;
        }
        
        .info-label {
            font-weight: bold;
            color: #333;
            display: inline-block;
            min-width: 12mm;
        }
        
        .info-value {
            color: #000;
            font-weight: normal;
        }
        
        .package-code {
            font-size: 11px;
            font-weight: bold;
            color: #000;
        }
        
        .glass-name {
            font-size: 9px;
            color: #333;
            word-wrap: break-word;
            word-break: break-all;
            line-height: 1.2;
            max-width: 100%;
        }
        
        .control-panel {
            background: white;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .btn {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="control-panel no-print">
        <h3>打印标签预览</h3>
        <p>共 <?php echo count($packages); ?> 个标签</p>
        <?php if ($currentUser): ?>
            <p>操作员: <?php echo htmlspecialchars($currentUser['name'] ?: $currentUser['username']); ?></p>
        <?php endif; ?>
        <button class="btn btn-primary" onclick="printWithLodop()">C-LODOP打印</button>
        <button class="btn btn-secondary" onclick="printPreview()">打印预览</button>
        <button class="btn btn-secondary" onclick="window.close()">关闭</button>
    </div>

    <?php foreach ($packages as $index => $package): ?>
        <?php if ($index > 0): ?>
            <div style="page-break-before: always;"></div>
        <?php endif; ?>
        
        <div class="label-container">
            <div class="label-left">
                <div class="qrcode-container">
                    <div id="qrcode-<?php echo $package['id']; ?>"></div>
                </div>
                <div class="print-date">
                    <?php echo date('Y-m-d H:i'); ?>
                </div>
            </div>
            
            <div class="label-right">
                <div class="info-row">
                    <span class="package-code">包号：<?php echo htmlspecialchars($package['package_code']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="glass-name">原片：<?php echo htmlspecialchars($package['glass_name']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">品牌:</span>
                    <span class="info-value"><?php echo htmlspecialchars($package['glass_brand'] ?: '-'); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">规格:</span>
                    <span class="info-value"><?php echo htmlspecialchars($package['specification']); ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">入库:</span>
                    <span class="info-value"><?php echo $package['entry_date']; ?></span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- 引入C-LODOP打印控件 -->
    <script src="http://localhost:8000/CLodopfuncs.js"></script>
    
    <script>
        // 页面加载完成后生成二维码
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($packages as $package): ?>
                // 生成每个包的二维码
                new QRCode(document.getElementById("qrcode-<?php echo $package['id']; ?>"), {
                    text: "<?php echo htmlspecialchars($package['package_code']); ?>",
                    width: 80,
                    height: 80,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            <?php endforeach; ?>
            
            // 等待二维码生成完成后转换为Base64
            setTimeout(function() {
                convertQRToBase64();
            }, 2000);
        });
        
        // 存储二维码Base64数据
        let qrCodeData = {};
        
        // 将二维码转换为Base64
        function convertQRToBase64() {
            <?php foreach ($packages as $package): ?>
                const qrCanvas<?php echo $package['id']; ?> = document.querySelector('#qrcode-<?php echo $package['id']; ?> canvas');
                if (qrCanvas<?php echo $package['id']; ?>) {
                    qrCodeData[<?php echo $package['id']; ?>] = qrCanvas<?php echo $package['id']; ?>.toDataURL('image/png');
                }
            <?php endforeach; ?>
        }
        
        // 检查C-LODOP是否加载成功
        function checkLodop() {
            if (typeof getCLodop === 'undefined') {
                alert('C-LODOP打印控件未加载成功，请确保安装了C-LODOP服务');
                return false;
            }
            
            try {
                const LODOP = getCLodop();
                if (LODOP && LODOP.VERSION) {
                    return LODOP;
                } else {
                    alert('C-LODOP服务未运行，请启动C-LODOP服务');
                    return false;
                }
            } catch (e) {
                alert('C-LODOP连接失败：' + e.message);
                return false;
            }
        }
        
        // 使用C-LODOP打印
        function printWithLodop() {
            // 先检查二维码数据是否准备好了
            let qrReady = true;
            <?php foreach ($packages as $package): ?>
                if (!qrCodeData[<?php echo $package['id']; ?>]) {
                    qrReady = false;
                }
            <?php endforeach; ?>
            
            if (!qrReady) {
                // 如果二维码还没准备好，等待一下再转换
                convertQRToBase64();
                setTimeout(function() {
                    printWithLodop();
                }, 1000);
                return;
            }
            
            const LODOP = checkLodop();
            if (!LODOP) return;
            
            try {
                // 设置打印机
                LODOP.PRINT_INIT("玻璃包标签打印");
                
                // 设置纸张尺寸 80mm x 40mm
                LODOP.SET_PRINT_PAGESIZE(1, 800, 400, "Label80x40");
                
                // 设置打印方向为横向
                LODOP.SET_PRINT_MODE("PRINT_DEGREE", 90);
                
                <?php foreach ($packages as $index => $package): ?>
                    <?php if ($index > 0): ?>
                        LODOP.NEWPAGE();
                    <?php endif; ?>
                    
                    // 获取二维码数据
                    const qrData<?php echo $package['id']; ?> = qrCodeData[<?php echo $package['id']; ?>];
                    
                    // 添加二维码（左侧）
                    if (qrData<?php echo $package['id']; ?>) {
                        LODOP.ADD_PRINT_IMAGE(5, 5, 80, 80, qrData<?php echo $package['id']; ?>);
                    }
                    
                    // 添加包编码
                    LODOP.ADD_PRINT_TEXT(8, 100, 200, 20, "包号：<?php echo addslashes($package['package_code']); ?>");
                    LODOP.SET_PRINT_STYLEA(0, "FontSize", 13);
                    LODOP.SET_PRINT_STYLEA(0, "Bold", 1);
                    LODOP.SET_PRINT_STYLEA(0, "FontName", "幼圆");
                    
                    // 添加玻璃名称（支持换行）
                    const glassName<?php echo $package['id']; ?> = "<?php echo addslashes($package['glass_short_name'] ?: $package['glass_name']); ?>";
                    // 长名称处理：如果超过10个字符则分成两行
                    let displayName<?php echo $package['id']; ?> = glassName<?php echo $package['id']; ?>;
                    if (glassName<?php echo $package['id']; ?>.length > 10) {
                        const mid = Math.ceil(glassName<?php echo $package['id']; ?>.length / 2);
                        displayName<?php echo $package['id']; ?> = glassName<?php echo $package['id']; ?>.substring(0, mid) + "\n" + glassName<?php echo $package['id']; ?>.substring(mid);
                    }
                    LODOP.ADD_PRINT_TEXT(32, 100, 200, 30, "名称：" + displayName<?php echo $package['id']; ?>);
                    LODOP.SET_PRINT_STYLEA(0, "FontSize", 13);
                    LODOP.SET_PRINT_STYLEA(0, "FontName", "幼圆");
                    
                    // 添加品牌
                    LODOP.ADD_PRINT_TEXT(68, 100, 200, 18, "品牌: <?php echo addslashes($package['glass_brand'] ?: '-'); ?>");
                    LODOP.SET_PRINT_STYLEA(0, "FontSize", 13);
                    LODOP.SET_PRINT_STYLEA(0, "FontName", "幼圆");
                    
                    // 添加规格
                    LODOP.ADD_PRINT_TEXT(90, 100, 200, 18, "规格: <?php echo addslashes($package['specification']); ?>");
                    LODOP.SET_PRINT_STYLEA(0, "FontSize", 13);
                    LODOP.SET_PRINT_STYLEA(0, "FontName", "幼圆");
                    
                    // 添加入库日期
                    LODOP.ADD_PRINT_TEXT(112, 100, 200, 18, "入库: <?php echo $package['entry_date']; ?>");
                    LODOP.SET_PRINT_STYLEA(0, "FontSize", 13);
                    LODOP.SET_PRINT_STYLEA(0, "FontName", "幼圆");
                    
                    // 添加打印时间（在二维码下方居中）
                    LODOP.ADD_PRINT_TEXT(110, 5, 80, 10, "<?php echo date('Y-m-d H:i'); ?>");
                    LODOP.SET_PRINT_STYLEA(0, "FontSize", 8);
                    LODOP.SET_PRINT_STYLEA(0, "FontColor", "#666666");
                    LODOP.SET_PRINT_STYLEA(0, "Alignment", 2); // 居中对齐
                <?php endforeach; ?>
                
                // 直接打印
                LODOP.PRINT();
                
            } catch (e) {
                alert('打印失败：' + e.message);
            }
        }
        
        // 打印预览
        function printPreview() {
            // 先检查二维码数据是否准备好了
            let qrReady = true;
            <?php foreach ($packages as $package): ?>
                if (!qrCodeData[<?php echo $package['id']; ?>]) {
                    qrReady = false;
                }
            <?php endforeach; ?>
            
            if (!qrReady) {
                // 如果二维码还没准备好，等待一下再试
                setTimeout(function() {
                    convertQRToBase64();
                    setTimeout(function() {
                        printPreview();
                    }, 1000);
                }, 500);
                return;
            }
            
            const LODOP = checkLodop();
            if (!LODOP) return;
            
            try {
                // 使用与打印相同的设置
                LODOP.PRINT_INIT("玻璃包标签打印");
                LODOP.SET_PRINT_PAGESIZE(1, 800, 400, "Label80x40");
                LODOP.SET_PRINT_MODE("PRINT_DEGREE", 90);
                
                <?php foreach ($packages as $index => $package): ?>
                    <?php if ($index > 0): ?>
                        LODOP.NEWPAGE();
                    <?php endif; ?>
                    
                    const qrData<?php echo $package['id']; ?> = qrCodeData[<?php echo $package['id']; ?>];
                    
                    if (qrData<?php echo $package['id']; ?>) {
                        LODOP.ADD_PRINT_IMAGE(5, 5, 80, 80, qrData<?php echo $package['id']; ?>);
                    }
                    
                    LODOP.ADD_PRINT_TEXT(8, 100, 200, 20, "包号：<?php echo addslashes($package['package_code']); ?>");
                    LODOP.SET_PRINT_STYLEA(0, "FontSize", 13);
                    LODOP.SET_PRINT_STYLEA(0, "Bold", 1);
                    LODOP.SET_PRINT_STYLEA(0, "FontName", "幼圆");
                    
                    // 添加玻璃名称（支持换行）
                    const glassNamePreview<?php echo $package['id']; ?> = "<?php echo addslashes($package['glass_short_name'] ?: $package['glass_name']); ?>";
                    // 长名称处理：如果超过10个字符则分成两行
                    let displayNamePreview<?php echo $package['id']; ?> = glassNamePreview<?php echo $package['id']; ?>;
                    if (glassNamePreview<?php echo $package['id']; ?>.length > 10) {
                        const mid = Math.ceil(glassNamePreview<?php echo $package['id']; ?>.length / 2);
                        displayNamePreview<?php echo $package['id']; ?> = glassNamePreview<?php echo $package['id']; ?>.substring(0, mid) + "\n" + glassNamePreview<?php echo $package['id']; ?>.substring(mid);
                    }
                    LODOP.ADD_PRINT_TEXT(32, 100, 200, 30, "名称：" + displayNamePreview<?php echo $package['id']; ?>);
                    LODOP.SET_PRINT_STYLEA(0, "FontSize", 13);
                    LODOP.SET_PRINT_STYLEA(0, "FontName", "幼圆");
                    
                    LODOP.ADD_PRINT_TEXT(68, 100, 200, 18, "品牌: <?php echo addslashes($package['glass_brand'] ?: '-'); ?>");
                    LODOP.SET_PRINT_STYLEA(0, "FontSize", 13);
                    LODOP.SET_PRINT_STYLEA(0, "FontName", "幼圆");
                    
                    LODOP.ADD_PRINT_TEXT(90, 100, 200, 18, "规格: <?php echo addslashes($package['specification']); ?>");
                    LODOP.SET_PRINT_STYLEA(0, "FontSize", 13);
                    LODOP.SET_PRINT_STYLEA(0, "FontName", "幼圆");
                    
                    LODOP.ADD_PRINT_TEXT(112, 100, 200, 18, "入库: <?php echo $package['entry_date']; ?>");
                    LODOP.SET_PRINT_STYLEA(0, "FontSize", 13);
                    LODOP.SET_PRINT_STYLEA(0, "FontName", "幼圆");
                    
                    // 添加打印时间（在二维码下方居中）
                    LODOP.ADD_PRINT_TEXT(110, 5, 80, 10, "<?php echo date('Y-m-d H:i'); ?>");
                    LODOP.SET_PRINT_STYLEA(0, "FontSize", 8);
                    LODOP.SET_PRINT_STYLEA(0, "FontColor", "#666666");
                    LODOP.SET_PRINT_STYLEA(0, "Alignment", 2); // 居中对齐
                <?php endforeach; ?>
                
                // 预览
                LODOP.PREVIEW();
                
            } catch (e) {
                alert('预览失败：' + e.message);
            }
        }
    </script>
</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
?>