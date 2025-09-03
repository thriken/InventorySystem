<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// 直接设置基地ID为1（信义基地），无需登录
$base_id = 1;

// 获取基地信息
$baseInfo = fetchRow("SELECT * FROM bases WHERE id = ?", [$base_id]);

// 获取所有库位的库存信息
$rackInventory = [];
$sql = "SELECT 
    sr.name as rack_code,
    sr.area_type,
    COUNT(gp.id) as package_count,
    SUM(gp.pieces) as total_pieces,
    GROUP_CONCAT(DISTINCT gt.short_name) as glass_types,
    GROUP_CONCAT(DISTINCT gt.color) as colors,
    GROUP_CONCAT(DISTINCT gt.thickness) as thicknesses,
    GROUP_CONCAT(DISTINCT CONCAT(gp.width, 'x', gp.height) ORDER BY gp.width, gp.height) as dimensions,
    GROUP_CONCAT(DISTINCT gp.package_code ORDER BY gp.package_code) as package_codes,
    SUM(gp.width * gp.height * gp.pieces / 1000000) as total_area_sqm
FROM storage_racks sr
LEFT JOIN glass_packages gp ON sr.id = gp.current_rack_id AND gp.status = 'in_storage'
LEFT JOIN glass_types gt ON gp.glass_type_id = gt.id
WHERE sr.base_id = ?
GROUP BY sr.id, sr.code, sr.area_type
ORDER BY sr.code";

$inventoryData = fetchAll($sql, [$base_id]);

// 组织数据按库位编码
foreach ($inventoryData as $item) {
    $rackInventory[$item['rack_code']] = $item;
}

// 获取原片类型用于搜索
$glassTypes = fetchAll("SELECT DISTINCT short_name, color, thickness FROM glass_types ORDER BY short_name");

// 处理搜索请求
$searchType = $_GET['search_type'] ?? '';
$highlightRacks = [];
if ($searchType) {
    $searchSql = "SELECT DISTINCT sr.code 
                  FROM storage_racks sr
                  JOIN glass_packages gp ON sr.id = gp.current_rack_id
                  JOIN glass_types gt ON gp.glass_type_id = gt.id
                  WHERE sr.base_id = ? AND gp.status = 'in_storage'
                  AND (gt.short_name LIKE ? OR gt.color LIKE ? OR gt.thickness = ?)";
    
    $searchParam = "%$searchType%";
    $searchResults = fetchAll($searchSql, [$base_id, $searchParam, $searchParam, $searchType]);
    $highlightRacks = array_column($searchResults, 'code');
}

ob_start();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>信义基地可视化库存</title>
    <style>
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .search-container {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .search-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 10px;
            width: 200px;
        }
        
        .search-btn {
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .clear-btn {
            padding: 8px 16px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
            text-decoration: none;
        }
        
        .warehouse-container {
            margin: 0 auto;
            background: white;
            padding: 30px 80px;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .warehouse-layout {
            display: grid;
            grid-template-rows: auto auto auto 60px;
            gap: 20px;
        }
        
        /* 上方三行库位区域 */
        .top-storage-area {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: center;
        }
        
        .storage-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        /* 库位配对容器 - 用于同数字A、B架 */
        .rack-pair {
            display: flex;
            gap: 1px;
            align-items: center;
        }
        
        .storage-row.reverse {
            flex-direction: row-reverse;
        }
        
        /* 中间过道 */
        .middle-aisle {
            display: flex;
            justify-content: center;
            align-items: center;
            background: rgba(240,240,240,0.5);
            border: 2px dashed #ccc;
            border-radius: 8px;
            position: relative;
            height: 100px;
        }
        
        .aisle-text {
            font-size: 24px;
            font-weight: bold;
            color: #666;
            background: white;
            padding: 10px 700px;
            border-radius: 8px;
            border: 2px solid #ddd;
        }
        
        /* 下方区域 */
        .bottom-area {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 40px;
            padding: 20px 100px;
        }
        
        .left-bottom {
            display: flex;
            gap: 30px;
            align-items: flex-start;
        }
        
        .cutting-storage-area {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: center;
        }
        
        .cutting-storage-grid {
            display: grid;
            grid-template-columns: repeat(4, 50px);
            grid-template-rows: repeat(2, 40px);
            gap: 5px;
        }
        
        .processing-area {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: center;
        }
        
        .processing-racks {
            display: flex;
            gap: 10px;
        }
        
        .right-bottom {
            display: flex;
            flex-direction: column;
            gap: 5px;
            align-items: center;
        }
        
        .right-storage-pair {
            display: flex;
            gap: 5px;
        }
        
        /* 库位样式 */
        .rack {
            width: 30px;
            height: 50px;
            border: 2px solid #ddd;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            background-color: #f8f9fa;
        }
        
        .rack.storage {
            background-color: #f8f9fa;
            border-color: gray;
        }

        .rack.processing {
            background-color: #fff3cd;
            border-color: #ffc107;
        }
        
        .rack.unused {   
            background-color: #e9ecef;
            border-color: #6c757d;
            color: #6c757d;
        }
        
        .rack.has-inventory {
            background-color: #d4edda;
            border-color: #155724;
            color: #155724;
        }
        
        .rack.highlighted {
            background-color: #ff6b6b !important;
            border-color: #dc3545 !important;
            color: white !important;
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .rack:hover {
            transform: scale(1.1);
            z-index: 10;
        }
        
        /* 门标识 */
        .door-label {
            position: absolute;
            font-size: 14px;
            font-weight: bold;
            color: #333;
            background: rgba(255,255,255,0.9);
            padding: 8px 15px;
            border-radius: 6px;
            border: 2px solid #007bff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .front-door {
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .back-door {
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .section-title {
            font-weight: bold;
            color: #333;
            font-size: 14px;
            text-align: center;
            margin-bottom: 5px;
        }
        
        .tooltip {
            position: absolute;
            background: rgba(0,0,0,0.9);
            color: white;
            padding: 10px;
            border-radius: 6px;
            font-size: 16px;
            white-space: nowrap;
            z-index: 1000;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s;
            max-width: 400px;
            white-space: normal;
        }
        
        .tooltip.show {
            opacity: 1;
        }
        
        .legend {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 2px solid;
        }
    </style>
</head>
<body>
    <div class="header">
        <h4>📦 <?php echo htmlspecialchars($baseInfo['name'] ?? '信义基地'); ?> 可视化库存</h4>
    </div>
    
    <div class="search-container">
        <form method="GET" style="display: inline;">
            <input type="text" name="search_type" class="search-input" 
                   placeholder="搜索原片类型、颜色或厚度" 
                   value="<?php echo htmlspecialchars($searchType); ?>">
            <button type="submit" class="search-btn">🔍 搜索</button>
            <a href="warehouse.php" class="clear-btn">清除</a>
        </form>
    </div>
    
    <div class="warehouse-container">

        <div class="warehouse-layout">
            <!-- 上方三行库位区域 -->
            <div class="top-storage-area">
                <!-- 第一行：38-52（从左到右） -->
                <div class="storage-row">
                    <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                        <?php
                        // 第一行：52B 52A 51B 51A ... 38B 38A
                        for ($num = 52; $num >= 38; $num--) {
                            echo '<div class="rack-pair">';
                            
                            // B架
                            $rackCode = $num . 'B';
                            $hasInventory = isset($rackInventory[$rackCode]) && $rackInventory[$rackCode]['package_count'] > 0;
                            $isHighlighted = in_array($rackCode, $highlightRacks);
                            
                            echo '<div class="rack storage' . ($hasInventory ? ' has-inventory' : '') . ($isHighlighted ? ' highlighted' : '') . '" ';
                            echo 'data-rack="' . $rackCode . '" ';
                            if ($hasInventory) {
                                echo 'data-packages="' . $rackInventory[$rackCode]['package_count'] . '" ';
                                echo 'data-pieces="' . $rackInventory[$rackCode]['total_pieces'] . '" ';
                                echo 'data-types="' . htmlspecialchars($rackInventory[$rackCode]['glass_types']) . '" ';
                                echo 'data-colors="' . htmlspecialchars($rackInventory[$rackCode]['colors']) . '" ';
                                echo 'data-thicknesses="' . htmlspecialchars($rackInventory[$rackCode]['thicknesses']) . '" ';
                            }
                            echo '>' . $rackCode . '</div>';
                            
                            // A架
                            $rackCode = $num . 'A';
                            $hasInventory = isset($rackInventory[$rackCode]) && $rackInventory[$rackCode]['package_count'] > 0;
                            $isHighlighted = in_array($rackCode, $highlightRacks);
                            
                            echo '<div class="rack storage' . ($hasInventory ? ' has-inventory' : '') . ($isHighlighted ? ' highlighted' : '') . '" ';
                            echo 'data-rack="' . $rackCode . '" ';
                            if ($hasInventory) {
                                echo 'data-packages="' . $rackInventory[$rackCode]['package_count'] . '" ';
                                echo 'data-pieces="' . $rackInventory[$rackCode]['total_pieces'] . '" ';
                                echo 'data-types="' . htmlspecialchars($rackInventory[$rackCode]['glass_types']) . '" ';
                                echo 'data-colors="' . htmlspecialchars($rackInventory[$rackCode]['colors']) . '" ';
                                echo 'data-thicknesses="' . htmlspecialchars($rackInventory[$rackCode]['thicknesses']) . '" ';
                            }
                            echo '>' . $rackCode . '</div>';
                            
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
                
                <!-- 第二行：23-37（从左到右） -->
                <div class="storage-row">
                    <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                        <?php
                        // 第二行：23B 23A 24B 24A ... 37B 37A
                        for ($num = 37; $num >= 23; $num--) {
                            echo '<div class="rack-pair">';
                            
                            // B架
                            $rackCode = $num . 'B';
                            $hasInventory = isset($rackInventory[$rackCode]) && $rackInventory[$rackCode]['package_count'] > 0;
                            $isHighlighted = in_array($rackCode, $highlightRacks);
                            
                            echo '<div class="rack storage' . ($hasInventory ? ' has-inventory' : '') . ($isHighlighted ? ' highlighted' : '') . '" ';
                            echo 'data-rack="' . $rackCode . '" ';
                            if ($hasInventory) {
                                echo 'data-packages="' . $rackInventory[$rackCode]['package_count'] . '" ';
                                echo 'data-pieces="' . $rackInventory[$rackCode]['total_pieces'] . '" ';
                                echo 'data-types="' . htmlspecialchars($rackInventory[$rackCode]['glass_types']) . '" ';
                                echo 'data-colors="' . htmlspecialchars($rackInventory[$rackCode]['colors']) . '" ';
                                echo 'data-thicknesses="' . htmlspecialchars($rackInventory[$rackCode]['thicknesses']) . '" ';
                            }
                            echo '>' . $rackCode . '</div>';
                            
                            // A架
                            $rackCode = $num . 'A';
                            $hasInventory = isset($rackInventory[$rackCode]) && $rackInventory[$rackCode]['package_count'] > 0;
                            $isHighlighted = in_array($rackCode, $highlightRacks);
                            
                            echo '<div class="rack storage' . ($hasInventory ? ' has-inventory' : '') . ($isHighlighted ? ' highlighted' : '') . '" ';
                            echo 'data-rack="' . $rackCode . '" ';
                            if ($hasInventory) {
                                echo 'data-packages="' . $rackInventory[$rackCode]['package_count'] . '" ';
                                echo 'data-pieces="' . $rackInventory[$rackCode]['total_pieces'] . '" ';
                                echo 'data-types="' . htmlspecialchars($rackInventory[$rackCode]['glass_types']) . '" ';
                                echo 'data-colors="' . htmlspecialchars($rackInventory[$rackCode]['colors']) . '" ';
                                echo 'data-thicknesses="' . htmlspecialchars($rackInventory[$rackCode]['thicknesses']) . '" ';
                            }
                            echo '>' . $rackCode . '</div>';
                            
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
                
                <!-- 第三行：10-22（从左到右） -->
                <div class="storage-row">
                    <div style="display: flex; gap: 15px;">
                        <?php
                        // 第三行：22B 22A 21B 21A ... 10B 10A
                        for ($num = 10; $num <= 22; $num++) {
                            echo '<div class="rack-pair">';
                            
                            // B架
                            $rackCode = $num . 'B';
                            $hasInventory = isset($rackInventory[$rackCode]) && $rackInventory[$rackCode]['package_count'] > 0;
                            $isHighlighted = in_array($rackCode, $highlightRacks);
                            
                            echo '<div class="rack storage' . ($hasInventory ? ' has-inventory' : '') . ($isHighlighted ? ' highlighted' : '') . '" ';
                            echo 'data-rack="' . $rackCode . '" ';
                            if ($hasInventory) {
                                echo 'data-packages="' . $rackInventory[$rackCode]['package_count'] . '" ';
                                echo 'data-pieces="' . $rackInventory[$rackCode]['total_pieces'] . '" ';
                                echo 'data-types="' . htmlspecialchars($rackInventory[$rackCode]['glass_types']) . '" ';
                                echo 'data-colors="' . htmlspecialchars($rackInventory[$rackCode]['colors']) . '" ';
                                echo 'data-thicknesses="' . htmlspecialchars($rackInventory[$rackCode]['thicknesses']) . '" ';
                            }
                            echo '>' . $rackCode . '</div>';
                            
                            // A架
                            $rackCode = $num . 'A';
                            $hasInventory = isset($rackInventory[$rackCode]) && $rackInventory[$rackCode]['package_count'] > 0;
                            $isHighlighted = in_array($rackCode, $highlightRacks);
                            
                            echo '<div class="rack storage' . ($hasInventory ? ' has-inventory' : '') . ($isHighlighted ? ' highlighted' : '') . '" ';
                            echo 'data-rack="' . $rackCode . '" ';
                            if ($hasInventory) {
                                echo 'data-packages="' . $rackInventory[$rackCode]['package_count'] . '" ';
                                echo 'data-pieces="' . $rackInventory[$rackCode]['total_pieces'] . '" ';
                                echo 'data-types="' . htmlspecialchars($rackInventory[$rackCode]['glass_types']) . '" ';
                                echo 'data-colors="' . htmlspecialchars($rackInventory[$rackCode]['colors']) . '" ';
                                echo 'data-thicknesses="' . htmlspecialchars($rackInventory[$rackCode]['thicknesses']) . '" ';
                                echo 'data-dimensions="' . htmlspecialchars($rackInventory[$rackCode]['dimensions']) . '" ';
                                echo 'data-codes="' . htmlspecialchars($rackInventory[$rackCode]['package_codes']) . '" ';
                                echo 'data-area="' . number_format($rackInventory[$rackCode]['total_area_sqm'], 2) . '" ';
                            }
                            echo '>' . $rackCode . '</div>';
                            
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- 中间过道 -->
            <div class="middle-aisle">
                <div class="aisle-text">过道</div>
                        <!-- 门标识 -->
        <div class="door-label front-door">前门</div>
        <div class="door-label back-door">后门</div>
        
            </div>
            
            <!-- 下方区域 -->
            <div class="bottom-area">
                <!-- 左下角区域 -->
                <div class="left-bottom">
                    <!-- 3-9号库位架 -->
                    <div class="cutting-storage-area">                     
                        <!-- 第一行：3-5号库位架 -->
                        <div style="display: grid; grid-template-columns: repeat(3, 70px); grid-template-rows: 1fr; gap: 15px; margin-bottom: 10px;"> 
                            <?php
                        for ($num = 5; $num >= 3; $num--) {
                            echo '<div class="rack-pair">';
                            
                            // B架
                            $rackCode = $num . 'B';
                            $hasInventory = isset($rackInventory[$rackCode]) && $rackInventory[$rackCode]['package_count'] > 0;
                            $isHighlighted = in_array($rackCode, $highlightRacks);
                            
                            echo '<div class="rack storage' . ($hasInventory ? ' has-inventory' : '') . ($isHighlighted ? ' highlighted' : '') . '" ';
                            echo 'data-rack="' . $rackCode . '" ';
                            if ($hasInventory) {
                                echo 'data-packages="' . $rackInventory[$rackCode]['package_count'] . '" ';
                                echo 'data-pieces="' . $rackInventory[$rackCode]['total_pieces'] . '" ';
                                echo 'data-types="' . htmlspecialchars($rackInventory[$rackCode]['glass_types']) . '" ';
                                echo 'data-colors="' . htmlspecialchars($rackInventory[$rackCode]['colors']) . '" ';
                                echo 'data-thicknesses="' . htmlspecialchars($rackInventory[$rackCode]['thicknesses']) . '" ';
                            }
                            echo '>' . $rackCode . '</div>';
                            
                            // A架
                            $rackCode = $num . 'A';
                            $hasInventory = isset($rackInventory[$rackCode]) && $rackInventory[$rackCode]['package_count'] > 0;
                            $isHighlighted = in_array($rackCode, $highlightRacks);
                            
                            echo '<div class="rack storage' . ($hasInventory ? ' has-inventory' : '') . ($isHighlighted ? ' highlighted' : '') . '" ';
                            echo 'data-rack="' . $rackCode . '" ';
                            if ($hasInventory) {
                                echo 'data-packages="' . $rackInventory[$rackCode]['package_count'] . '" ';
                                echo 'data-pieces="' . $rackInventory[$rackCode]['total_pieces'] . '" ';
                                echo 'data-types="' . htmlspecialchars($rackInventory[$rackCode]['glass_types']) . '" ';
                                echo 'data-colors="' . htmlspecialchars($rackInventory[$rackCode]['colors']) . '" ';
                                echo 'data-thicknesses="' . htmlspecialchars($rackInventory[$rackCode]['thicknesses']) . '" ';
                            }
                            echo '>' . $rackCode . '</div>';
                            
                            echo '</div>';
                        }
                        ?>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(5, 70px); grid-template-rows: 1fr; gap: 15px;">
                            <?php
                        for ($num = 6; $num <= 9; $num++) {
                            echo '<div class="rack-pair">';
                            
                            // B架
                            $rackCode = $num . 'B';
                            $hasInventory = isset($rackInventory[$rackCode]) && $rackInventory[$rackCode]['package_count'] > 0;
                            $isHighlighted = in_array($rackCode, $highlightRacks);
                            
                            echo '<div class="rack storage' . ($hasInventory ? ' has-inventory' : '') . ($isHighlighted ? ' highlighted' : '') . '" ';
                            echo 'data-rack="' . $rackCode . '" ';
                            if ($hasInventory) {
                                echo 'data-packages="' . $rackInventory[$rackCode]['package_count'] . '" ';
                                echo 'data-pieces="' . $rackInventory[$rackCode]['total_pieces'] . '" ';
                                echo 'data-types="' . htmlspecialchars($rackInventory[$rackCode]['glass_types']) . '" ';
                                echo 'data-colors="' . htmlspecialchars($rackInventory[$rackCode]['colors']) . '" ';
                                echo 'data-thicknesses="' . htmlspecialchars($rackInventory[$rackCode]['thicknesses']) . '" ';
                            }
                            echo '>' . $rackCode . '</div>';
                            
                            // A架
                            $rackCode = $num . 'A';
                            $hasInventory = isset($rackInventory[$rackCode]) && $rackInventory[$rackCode]['package_count'] > 0;
                            $isHighlighted = in_array($rackCode, $highlightRacks);
                            
                            echo '<div class="rack storage' . ($hasInventory ? ' has-inventory' : '') . ($isHighlighted ? ' highlighted' : '') . '" ';
                            echo 'data-rack="' . $rackCode . '" ';
                            if ($hasInventory) {
                                echo 'data-packages="' . $rackInventory[$rackCode]['package_count'] . '" ';
                                echo 'data-pieces="' . $rackInventory[$rackCode]['total_pieces'] . '" ';
                                echo 'data-types="' . htmlspecialchars($rackInventory[$rackCode]['glass_types']) . '" ';
                                echo 'data-colors="' . htmlspecialchars($rackInventory[$rackCode]['colors']) . '" ';
                                echo 'data-thicknesses="' . htmlspecialchars($rackInventory[$rackCode]['thicknesses']) . '" ';
                            }
                            echo '>' . $rackCode . '</div>';
                            
                            echo '</div>';
                        }
                        ?>
                        </div>
                    </div>
                    
                    <!-- B1、A1加工区 -->
                    <div class="processing-area">
                        <div class="processing-racks">
                            <?php
                            $processingRacks = ['B1', 'A1'];
                            foreach ($processingRacks as $rackCode) {
                                $hasInventory = isset($rackInventory[$rackCode]) && $rackInventory[$rackCode]['package_count'] > 0;
                                $isHighlighted = in_array($rackCode, $highlightRacks);
                                
                                echo '<div class="rack processing' . ($hasInventory ? ' has-inventory' : '') . ($isHighlighted ? ' highlighted' : '') . '" ';
                                echo 'data-rack="' . $rackCode . '" ';
                                if ($hasInventory) {
                                    echo 'data-packages="' . $rackInventory[$rackCode]['package_count'] . '" ';
                                    echo 'data-pieces="' . $rackInventory[$rackCode]['total_pieces'] . '" ';
                                    echo 'data-types="' . htmlspecialchars($rackInventory[$rackCode]['glass_types']) . '" ';
                                    echo 'data-colors="' . htmlspecialchars($rackInventory[$rackCode]['colors']) . '" ';
                                    echo 'data-thicknesses="' . htmlspecialchars($rackInventory[$rackCode]['thicknesses']) . '" ';
                                }
                                echo '>' . $rackCode . '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- 右下角区域：53-56号库位架 -->
                <div class="right-bottom">
                    <div style="display: grid; grid-template-columns: repeat(4, 70px); grid-template-rows: 1fr; gap: 15px;">
                        <?php
                        for ($num = 56; $num >= 53; $num--) {
                            echo '<div class="rack-pair">';
                            
                            // B架
                            $rackCode = $num . 'B';
                            $hasInventory = isset($rackInventory[$rackCode]) && $rackInventory[$rackCode]['package_count'] > 0;
                            $isHighlighted = in_array($rackCode, $highlightRacks);
                            
                            echo '<div class="rack storage' . ($hasInventory ? ' has-inventory' : '') . ($isHighlighted ? ' highlighted' : '') . '" ';
                            echo 'data-rack="' . $rackCode . '" ';
                            if ($hasInventory) {
                                echo 'data-packages="' . $rackInventory[$rackCode]['package_count'] . '" ';
                                echo 'data-pieces="' . $rackInventory[$rackCode]['total_pieces'] . '" ';
                                echo 'data-types="' . htmlspecialchars($rackInventory[$rackCode]['glass_types']) . '" ';
                                echo 'data-colors="' . htmlspecialchars($rackInventory[$rackCode]['colors']) . '" ';
                                echo 'data-thicknesses="' . htmlspecialchars($rackInventory[$rackCode]['thicknesses']) . '" ';
                            }
                            echo '>' . $rackCode . '</div>';
                            
                            // A架
                            $rackCode = $num . 'A';
                            $hasInventory = isset($rackInventory[$rackCode]) && $rackInventory[$rackCode]['package_count'] > 0;
                            $isHighlighted = in_array($rackCode, $highlightRacks);
                            
                            echo '<div class="rack storage' . ($hasInventory ? ' has-inventory' : '') . ($isHighlighted ? ' highlighted' : '') . '" ';
                            echo 'data-rack="' . $rackCode . '" ';
                            if ($hasInventory) {
                                echo 'data-packages="' . $rackInventory[$rackCode]['package_count'] . '" ';
                                echo 'data-pieces="' . $rackInventory[$rackCode]['total_pieces'] . '" ';
                                echo 'data-types="' . htmlspecialchars($rackInventory[$rackCode]['glass_types']) . '" ';
                                echo 'data-colors="' . htmlspecialchars($rackInventory[$rackCode]['colors']) . '" ';
                                echo 'data-thicknesses="' . htmlspecialchars($rackInventory[$rackCode]['thicknesses']) . '" ';
                            }
                            echo '>' . $rackCode . '</div>';
                            
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 图例 -->
    <div class="legend">
        <div class="legend-item">
            <div class="legend-color" style="background-color: #f8f9fa; border-color: #6c757d;"></div>
            <span>库存区（空）</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background-color: #28a745; border-color: #1e7e34;"></div>
            <span>库存区（有货）</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background-color: #fff3cd; border-color: #ffc107;"></div>
            <span>加工区</span>
        </div>
        <div class="legend-item">
            <div class="legend-color" style="background-color: #ff6b6b; border-color: #dc3545;"></div>
            <span>搜索匹配</span>
        </div>
    </div>
    
    <!-- 悬浮提示框 -->
    <div class="tooltip" id="tooltip"></div>
    
    <script>
        const tooltip = document.getElementById('tooltip');
        const racks = document.querySelectorAll('.rack[data-rack]');
        
        racks.forEach(rack => {
            rack.addEventListener('mouseenter', function(e) {
                const rackCode = this.dataset.rack;
                const packages = this.dataset.packages || '0';
                const pieces = this.dataset.pieces || '0';
                const types = this.dataset.types || '';
                const colors = this.dataset.colors || '';
                const thicknesses = this.dataset.thicknesses || '';
                const dimensions = this.dataset.dimensions || '';
                const codes = this.dataset.codes || '';
                const area = this.dataset.area || '0';
                
                let content = `<div style="font-size: 13px; line-height: 1.5; max-width: 400px;">`;
                content += `<div style="font-weight: bold; color: #ffd700; margin-bottom: 8px; border-bottom: 1px solid #444; padding-bottom: 4px; font-size: 14px;">📦 库位: ${rackCode}</div>`;
                
                if (packages > 0) {
                    // 库存统计
                    content += `<div style="margin-bottom: 8px;"><span style="color: #90EE90; font-weight: bold;">📊 库存统计</span></div>`;
                    content += `<div style="margin-left: 15px; margin-bottom: 6px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">`;
                    content += `<div>包数: <span style="color: #87CEEB; font-weight: bold;">${packages}</span></div>`;
                    content += `<div>片数: <span style="color: #87CEEB; font-weight: bold;">${pieces}</span></div>`;
                    content += `</div>`;
                    content += `<div style="margin-left: 15px; margin-bottom: 8px;">总面积: <span style="color: #87CEEB; font-weight: bold;">${area} m²</span></div>`;
                    
                    // 原片详细信息
                    content += `<div style="margin-bottom: 6px;"><span style="color: #90EE90; font-weight: bold;">🔍 原片详情</span></div>`;
                    
                    // 处理多种类型显示
                    if (types) {
                        const typeList = types.split(',').map(t => t.trim()).filter(t => t);
                        content += `<div style="margin-left: 15px; margin-bottom: 4px;">类型: `;
                        if (typeList.length > 3) {
                            content += `<span style="color: #FFB6C1;">${typeList.slice(0, 3).join(', ')} 等${typeList.length}种</span>`;
                        } else {
                            content += `<span style="color: #FFB6C1;">${typeList.join(', ')}</span>`;
                        }
                        content += `</div>`;
                    }
                    
                    // 处理多种颜色显示
                    if (colors) {
                        const colorList = colors.split(',').map(c => c.trim()).filter(c => c);
                        content += `<div style="margin-left: 15px; margin-bottom: 4px;">颜色: `;
                        if (colorList.length > 3) {
                            content += `<span style="color: #FFB6C1;">${colorList.slice(0, 3).join(', ')} 等${colorList.length}种</span>`;
                        } else {
                            content += `<span style="color: #FFB6C1;">${colorList.join(', ')}</span>`;
                        }
                        content += `</div>`;
                    }
                    
                    // 处理多种厚度显示
                    if (thicknesses) {
                        const thicknessList = thicknesses.split(',').map(t => t.trim()).filter(t => t);
                        content += `<div style="margin-left: 15px; margin-bottom: 4px;">厚度: `;
                        if (thicknessList.length > 3) {
                            content += `<span style="color: #FFB6C1;">${thicknessList.slice(0, 3).join(', ')} 等${thicknessList.length}种 mm</span>`;
                        } else {
                            content += `<span style="color: #FFB6C1;">${thicknessList.join(', ')} mm</span>`;
                        }
                        content += `</div>`;
                    }
                    
                    // 处理多种尺寸显示
                    if (dimensions && dimensions.trim() !== '') {
                        const dimList = dimensions.split(',').map(d => d.trim()).filter(d => d);
                        content += `<div style="margin-left: 15px; margin-bottom: 6px;">尺寸: `;
                        if (dimList.length > 3) {
                            content += `<span style="color: #FFB6C1;">${dimList.slice(0, 3).join(', ')} 等${dimList.length}种</span>`;
                        } else {
                            content += `<span style="color: #FFB6C1;">${dimList.join(', ')}</span>`;
                        }
                        content += `</div>`;
                    }
                    
                    // 显示部分包号
                    if (codes && codes.trim() !== '') {
                        const codeList = codes.split(',').map(c => c.trim()).filter(c => c);
                        content += `<div style="margin-bottom: 6px;"><span style="color: #90EE90; font-weight: bold;">📋 包号</span></div>`;
                        content += `<div style="margin-left: 15px; font-size: 11px; color: #DDA0DD; line-height: 1.3;">`;
                        if (codeList.length > 4) {
                            content += `${codeList.slice(0, 4).join(', ')}<br><span style="color: #999;">等共${codeList.length}个包</span>`;
                        } else {
                            content += `${codeList.join(', ')}`;
                        }
                        content += `</div>`;
                    }
                    
                    content += `<div style="margin-top: 10px; padding-top: 6px; border-top: 1px solid #444; font-size: 11px; color: #DDD; text-align: center;">💡 点击查看详细库存</div>`;
                } else {
                    content += `<div style="color: #999; font-style: italic; text-align: center; padding: 10px;">📭 暂无库存</div>`;
                    content += `<div style="margin-top: 8px; font-size: 11px; color: #DDD; text-align: center;">💡 点击查看库位详情</div>`;
                }
                
                content += `</div>`;
                
                tooltip.innerHTML = content;
                tooltip.classList.add('show');
                
                // 定位提示框
                const rect = this.getBoundingClientRect();
                tooltip.style.left = (rect.left + rect.width / 2) + 'px';
                tooltip.style.top = (rect.top - tooltip.offsetHeight - 10) + 'px';
            });
            
            rack.addEventListener('mouseleave', function() {
                tooltip.classList.remove('show');
            });
            
            // 点击跳转到详细库存页面
            rack.addEventListener('click', function() {
                const rackCode = this.dataset.rack;
                window.open(`viewer/inventory.php?search=${rackCode}`, '_blank');
            });
        });
        
        // 鼠标移动时更新提示框位置
        document.addEventListener('mousemove', function(e) {
            if (tooltip.classList.contains('show')) {
                tooltip.style.left = (e.clientX - tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = (e.clientY - tooltip.offsetHeight - 10) + 'px';
            }
        });
    </script>
</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
?>