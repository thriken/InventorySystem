<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/app_info.php';

// 检查用户是否已登录
$currentUser = getCurrentUser();
$base_id = 1; // 默认基地ID为1
if ($currentUser && isset($currentUser['base_id']) && $currentUser['base_id']) {
    $base_id = $currentUser['base_id'];
}
// 如果URL中有base_id参数，则覆盖默认值
if(isset($_GET['base_id'])) {
    $base_id = $_GET['base_id'];
} elseif(isset($_POST['base_id'])) {
    $base_id = $_POST['base_id'];
}

// 验证基地ID是否有效
$validBases = fetchAll("SELECT id, name FROM bases ORDER BY id");
$baseIds = array_column($validBases, 'id');
if (!in_array($base_id, $baseIds)) {
    $base_id = 1; // 默认回到信义基地
}

// 获取基地信息
$baseInfo = fetchRow("SELECT * FROM bases WHERE id = ?", [$base_id]);

// 处理AJAX搜索请求
if (isset($_GET['ajax']) && $_GET['ajax'] == '1' && isset($_GET['search'])) {
    $searchQuery = trim($_GET['search']);
    
    if (!empty($searchQuery)) {
        $searchSql = "SELECT DISTINCT short_name,name, color, brand 
                      FROM glass_types 
                      WHERE short_name LIKE ? OR name LIKE ? OR color LIKE ? OR brand LIKE ?
                      ORDER BY short_name, name, color, brand";
        
        $searchParam = "%$searchQuery%";
        $suggestions = fetchAll($searchSql, [$searchParam, $searchParam, $searchParam, $searchParam]);
        
        header('Content-Type: application/json');
        echo json_encode($suggestions);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }
}

/**
 * 获取指定基地的库位库存数据
 * @param int $baseId 基地ID
 * @return array 库位库存数据数组
 */
function getBaseInventoryData($baseId) {
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
    LEFT JOIN glass_packages gp ON sr.id = gp.current_rack_id 
        AND (
            (sr.area_type = 'storage' AND gp.status = 'in_storage') OR
            (sr.area_type = 'processing' AND gp.status = 'in_processing')
        )
    LEFT JOIN glass_types gt ON gp.glass_type_id = gt.id
    WHERE sr.base_id = ?
    GROUP BY sr.id, sr.name, sr.area_type
    ORDER BY sr.name";
    
    $inventoryData = fetchAll($sql, [$baseId]);
    
    // 组织数据按库位编码
    $rackInventory = [];
    foreach ($inventoryData as $item) {
        $rackInventory[$item['rack_code']] = $item;
    }
    
    return $rackInventory;
}

/**
 * 获取搜索高亮的库位
 * @param int $baseId 基地ID
 * @param string $searchType 搜索类型
 * @return array 高亮库位数组
 */
function getHighlightRacks($baseId, $searchType) {
    if (!$searchType) return [];
    
    $searchSql = "SELECT DISTINCT sr.name 
                  FROM storage_racks sr
                  JOIN glass_packages gp ON sr.id = gp.current_rack_id
                  JOIN glass_types gt ON gp.glass_type_id = gt.id
                  WHERE sr.base_id = ? AND (gp.status = 'in_storage' OR  gp.status = 'in_processing')
                  AND (gt.short_name LIKE ? OR gt.name LIKE ? OR gt.color LIKE ? OR gt.thickness LIKE ?)";
    
    $searchParam = '%' . $searchType . '%';
    $results = fetchAll($searchSql, [$baseId, $searchParam, $searchParam, $searchParam, $searchParam]);
    
    return array_column($results, 'name');
}

/**
 * 渲染库位HTML
 * @param string $rackCode 库位编码
 * @param array $rackInventory 库位库存数据
 * @param array $highlightRacks 高亮库位数组
 * @return string 库位HTML
 */
function renderRack($rackCode, $rackInventory, $highlightRacks, $rackType = 'storage') {
    $hasInventory = isset($rackInventory[$rackCode]) && $rackInventory[$rackCode]['package_count'] > 0;
    $isHighlighted = in_array($rackCode, $highlightRacks);
    // 确定实际的库位类型
    $actualRackType = $rackType;
    if (isset($rackInventory[$rackCode]) && $rackInventory[$rackCode]['area_type']) {
        $actualRackType = $rackInventory[$rackCode]['area_type'];
    }
    $html = '<div class="rack ' . $actualRackType . ($hasInventory ? ' has-inventory' : '') . ($isHighlighted ? ' highlighted' : '') . '" ';
    $html .= 'data-rack="' . $rackCode . '" ';
    $html .= 'data-area-type="' . $actualRackType . '" ';
    if ($hasInventory) {
        $data = $rackInventory[$rackCode];
        $html .= 'data-packages="' . $data['package_count'] . '" ';
        $html .= 'data-pieces="' . $data['total_pieces'] . '" ';
        $html .= 'data-types="' . htmlspecialchars($data['glass_types']) . '" ';
        $html .= 'data-colors="' . htmlspecialchars($data['colors']) . '" ';
        $html .= 'data-thicknesses="' . htmlspecialchars($data['thicknesses']) . '" ';
        $html .= 'data-dimensions="' . htmlspecialchars($data['dimensions']) . '" ';
        $html .= 'data-codes="' . htmlspecialchars($data['package_codes']) . '" ';
        $html .= 'data-area="' . number_format($data['total_area_sqm'], 2) . '" ';
    }
    $html .= '>' . $rackCode . '</div>';
    return $html;
}

/**
 * 渲染垂直库位HTML
 * @param string $rackCode 库位编码
 * @param array $rackInventory 库位库存数据
 * @param array $highlightRacks 高亮库位数组
 * @return string 垂直库位HTML
 */
function renderVerticalRack($rackCode, $rackInventory, $highlightRacks, $rackType = 'storage') {
    $hasInventory = isset($rackInventory[$rackCode]) && $rackInventory[$rackCode]['package_count'] > 0;
    $isHighlighted = in_array($rackCode, $highlightRacks);
    // 确定实际的库位类型
    $actualRackType = $rackType;
    if (isset($rackInventory[$rackCode]) && $rackInventory[$rackCode]['area_type']) {
        $actualRackType = $rackInventory[$rackCode]['area_type'];
    }
    $html = '<div class="rack ' . $actualRackType . ' rack-vertical' . ($hasInventory ? ' has-inventory' : '') . ($isHighlighted ? ' highlighted' : '') . '" ';
    $html .= 'data-rack="' . $rackCode . '" ';
    $html .= 'data-area-type="' . $actualRackType . '" ';
    if ($hasInventory) {
        $data = $rackInventory[$rackCode];
        $html .= 'data-packages="' . $data['package_count'] . '" ';
        $html .= 'data-pieces="' . $data['total_pieces'] . '" ';
        $html .= 'data-types="' . htmlspecialchars($data['glass_types']) . '" ';
        $html .= 'data-colors="' . htmlspecialchars($data['colors']) . '" ';
        $html .= 'data-thicknesses="' . htmlspecialchars($data['thicknesses']) . '" ';
        $html .= 'data-dimensions="' . htmlspecialchars($data['dimensions']) . '" ';
        $html .= 'data-codes="' . htmlspecialchars($data['package_codes']) . '" ';
        $html .= 'data-area="' . number_format($data['total_area_sqm'], 2) . '" ';
    }
    $html .= '>' . $rackCode . '</div>';
    return $html;
}

/**
 * 渲染垂直库位对HTML
 * @param string $rackCodeA A库位编码
 * @param string $rackCodeB B库位编码
 * @param array $rackInventory 库位库存数据
 * @param array $highlightRacks 高亮库位数组
 * @return string 垂直库位对HTML
 */
function renderVerticalRackPair($rackCodeA, $rackCodeB, $rackInventory, $highlightRacks, $rackType = 'storage') {
    $html = '<div class="vertical-rack-pair">';
    $html .= renderVerticalRack($rackCodeA, $rackInventory, $highlightRacks, $rackType);
    $html .= renderVerticalRack($rackCodeB, $rackInventory, $highlightRacks, $rackType);
    $html .= '</div>';
    return $html;
}

// 处理AJAX请求
if (isset($_POST['ajax']) && $_POST['ajax'] === 'get_base_data') {
    header('Content-Type: application/json');
    
    $requestedBaseId = $_POST['base_id'] ?? 1;
    $searchType = $_POST['search_type'] ?? '';
    
    // 验证基地ID
    if (!in_array($requestedBaseId, $baseIds)) {
        echo json_encode(['error' => '无效的基地ID']);
        exit;
    }
    
    $rackInventory = getBaseInventoryData($requestedBaseId);
    $highlightRacks = getHighlightRacks($requestedBaseId, $searchType);
    $baseInfo = fetchRow("SELECT * FROM bases WHERE id = ?", [$requestedBaseId]);
    
    // 渲染布局HTML
    ob_start();
    include 'warehouse_layout.php';
    $layoutHtml = ob_get_clean();
    
    echo json_encode([
        'success' => true,
        'base_info' => $baseInfo,
        'layout_html' => $layoutHtml
    ]);
    exit;
}

// 获取当前基地的数据
$rackInventory = getBaseInventoryData($base_id);
$searchType = $_GET['search_type'] ?? '';
$highlightRacks = getHighlightRacks($base_id, $searchType);

// 获取原片类型用于搜索
$glassTypes = fetchAll("SELECT DISTINCT short_name, color, thickness FROM glass_types ORDER BY short_name");

ob_start();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($baseInfo['name']); ?> - 库区可视化</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            min-width: 1900px;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 24px;
            margin: 0;
        }
        
        .base-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .base-selector label {
            font-weight: bold;
            color: #34495e;
        }
        
        .base-selector select {
            padding: 8px 12px;
            border: 2px solid #3498db;
            border-radius: 5px;
            background: white;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .base-selector select:hover {
            border-color: #2980b9;
            box-shadow: 0 2px 5px rgba(52, 152, 219, 0.3);
        }
        
        .search-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-container select,
        .search-container button {
            padding: 8px 12px;
            border: 2px solid #27ae60;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .search-container select {
            background: white;
            cursor: pointer;
        }
        
        .search-container button {
            background: #27ae60;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .search-container button:hover {
            background: #219a52;
            transform: translateY(-1px);
        }
        .search-input-container {
            position: relative;
            display: inline-block;
        }
        
        .search-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 300px;
            font-size: 14px;
        }
        
        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .suggestion-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        
        .suggestion-item:hover {
            background-color: #f5f5f5;
        }
        
        .suggestion-item:last-child {
            border-bottom: none;
        }
        .loading {
            text-align: center;
            padding: 40px;
            font-size: 18px;
            color: #7f8c8d;
        }
        
        .warehouse-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 80px 30px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            margin: 0 auto;
        }
        
        .warehouse-layout {
            display: flex;
            flex-direction: column;
            gap: 30px;
            align-items: center;
        }
        
        .top-storage-area {
            display: flex;
            flex-direction: column;
            gap: 15px;
            width: 100%;
        }
        
        .storage-row {
            display: flex;
            justify-content: center;
        }
        .rack.highlighted {
            background: #ff6b6b !important;
            border-color: #dc3545 !important;
            color: white !important;
            animation: pulse 1.5s infinite;
        }
        
        
        .rack {
            width: 40px;
            height: 55px;
            border: 2px solid #ddd;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            user-select: none;
        }
        
        .rack.storage {
            background-color: #f8f9fa;
            border-color: #6c757d;
            color: #495057;
        }
        
        .rack.storage:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .rack.unused {
            background-color: #f8f9fa;
            border-color: #6c757d;
            color: #6c757d;
            opacity: 0.6;
        }

        .rack.processing {
            background: rgb(255,255,200);
            border: 2px solid #ffc107;
            color: #856404;
            height: 100px;
        }        
        .rack.processing:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .rack.processing.has-inventory {
            background: linear-gradient(135deg,rgb(255, 255, 100),rgb(252, 123, 18));
            color: black;
            font-size: 0.9rem;
            font-weight: bolder;
            box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
        }

        .rack.storage.has-inventory {
            background: linear-gradient(135deg,rgb(13, 161, 172),rgb(30, 206, 14));
            color: white;
            font-size:  0.9rem;
            font-weight: bolder;
            box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
        }
        /* 垂直库位样式 */
        .rack-vertical {
            width: 55px;
            height: 40px;
            display: flex;
            flex-direction: column;
            writing-mode: vertical-rl;
            text-orientation: mixed;
            border: 2px solid #ddd;
            border-radius: 6px;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            user-select: none;
        }
        
        .vertical-rack-pair {
            display: flex;
            flex-direction: column;
            gap: 2px;
            align-items: center;
        }
        
        .vertical-rack-pair .rack {
            writing-mode: vertical-rl;
            text-orientation: mixed;
        }
        
        /* 垂直库位组样式 */
        .vertical-rack-group {
            display: flex;
            flex-direction: column;
            gap: 15px;
            padding: 10px;
            background-color: #f9f9f9;
        }
        
        .vertical-rack-group-horizontal {
            display: flex;
            flex-direction: row;
            gap: 15px;
            align-items: flex-start;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .middle-area {
            display: flex;
            justify-content: space-between;
            width: 1440px;
            align-items: center;
        }
        
        .door {
            width: 60px;
            padding: 10px 20px;
            background: linear-gradient(45deg, #3498db, #2980b9);
            color: white;
            border-radius: 8px;
            font-weight: bold;
            box-shadow: 0 3px 10px rgba(52, 152, 219, 0.3); 
        }
        .road{
            display: flex;
            width: -webkit-fill-available;
            justify-content: center;
            align-items: center;
        }
        .aisle {
            display: flex;
            flex: 1;
            text-align: center;
            padding: 20px;
            color: #7f8c8d;
            font-size: 18px;    
            font-weight: bold;
            background: rgba(255, 255, 255, 0.9);
            padding: 10px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);  
            
        }
        
        .bottom-area {
            display: flex;
            justify-content: space-between;
            width: 100%;
            max-width: 1440px;
            align-items: flex-start;
        }
        
        .right-bottom {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .left-bottom {
            display: flex;
            gap: 80px;
            align-items: flex-start;
        }
        .storage-grid {
            display: grid;
            grid-template-columns: repeat(4, auto);
            grid-template-rows: repeat(2, auto);
            gap: 10px;
        }
        .rack-pair {
            display: flex;
            gap: 2px;
            align-items: center;
        }
        .processing-area {
            display: inline-flex;
            align-items: flex-start;
            justify-content: flex-start;
            flex-direction: row;
            gap: 10px;
            height: 140px;
        }
        
        .vertical-racks {
            display: flex;
            flex-direction: row;
            gap: 10px;
        }
        
        .tooltip {
            position: absolute;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-size: 12px;
            pointer-events: none;
            z-index: 1000;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            border: 1px solid #444;
            max-width: 400px;
        }
        
        .tooltip.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        .legend {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 2px solid;
        }
        
        @media (max-width: 768px) {
            .warehouse-container {
                padding: 15px;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .rack {
                width: 35px;
                height: 28px;
                font-size: 9px;
            }
            
            .legend {
                flex-direction: column;
                align-items: center;
            }
        }
        footer{
            text-align: center;
            padding: 10px 0;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="/viewer/inventory.php" class="nav-link">返回库存查询</a>
        <h1 id="page-title"><?php echo htmlspecialchars($baseInfo['name']); ?> - 库区可视化</h1>
        <div class="base-selector">
            <label for="base-select">选择基地:</label>
            <select id="base-select">
                <?php foreach ($validBases as $base): ?>
                    <option value="<?php echo $base['id']; ?>" <?php echo $base['id'] == $base_id ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($base['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="search-container">
            <div class="search-input-container">
                <input type="text" id="search-input" class="search-input" 
                       placeholder="输入原片类型、颜色或厚度进行搜索" 
                       value="<?php echo htmlspecialchars($searchType); ?>">
                <div id="search-suggestions" class="search-suggestions"></div>
            </div>
            <button type="button" id="search-btn" class="search-btn">🔍 搜索</button>
            <button type="button" id="clear-btn" class="clear-btn">清除</button>
        </div>
    </div>
    
    <div id="loading" class="loading" style="display: none;">
        <div>正在加载基地数据...</div>
    </div>
    
    <div id="warehouse-content" class="warehouse-container">
        <?php include 'warehouse_layout.php'; ?>
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
    <footer>
        <p>© 2025 <?php echo getAppName(); ?>版本 <?php echo getAppVersion(); ?> 版权所有</p>
    </footer>
    <!-- 悬浮提示框 -->
    <div class="tooltip" id="tooltip"></div>
    
    <script>
        const searchInput = document.getElementById('search-input');
        const searchSuggestions = document.getElementById('search-suggestions');
        const searchBtn = document.getElementById('search-btn');
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length === 0) {
                searchSuggestions.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                fetch('warehouse.php?ajax=1&search=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        displaySuggestions(data);
                    })
                    .catch(error => {
                        console.error('搜索错误:', error);
                    });
            }, 300);
        });
        
        function displaySuggestions(suggestions) {
            if (suggestions.length === 0) {
                searchSuggestions.style.display = 'none';
                return;
            }
            
            let html = '';
            suggestions.forEach(item => {
                html += `<div class="suggestion-item" data-value="${item.short_name}">
                    ${item.short_name} - ${item.color} - ${item.brand}
                </div>`;
            });
            
            searchSuggestions.innerHTML = html;
            searchSuggestions.style.display = 'block';
            
            // 添加点击事件
            searchSuggestions.querySelectorAll('.suggestion-item').forEach(item => {
                item.addEventListener('click', function() {
                    searchInput.value = this.dataset.value;
                    searchSuggestions.style.display = 'none';
                    performSearch();
                });
            });
        }
        
        // 点击外部隐藏建议列表
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
                searchSuggestions.style.display = 'none';
            }
        });
        
        // 搜索按钮点击事件
        searchBtn.addEventListener('click', performSearch);
        
        // 回车键搜索
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
        
        // 清除按钮点击事件
        document.getElementById('clear-btn').addEventListener('click', function() {
            // 清除搜索框内容
            searchInput.value = '';
            // 隐藏搜索建议
            searchSuggestions.style.display = 'none';
            
            // 保持当前base_id，只是清除搜索内容
            const baseId = document.getElementById('base-select').value;
            if (baseId) {
                window.location.href = 'warehouse.php?base_id=' + baseId;
            } else {
                window.location.href = 'warehouse.php';
            }
        });
        
        function performSearch() {
            const query = searchInput.value.trim();
            const baseId = document.getElementById('base-select').value;
            
            if (query && baseId) {
                window.location.href = 'warehouse.php?base_id=' + baseId + '&search_type=' + encodeURIComponent(query);
            } else if (query) {
                window.location.href = 'warehouse.php?search_type=' + encodeURIComponent(query);
            } else if (baseId) {
                window.location.href = 'warehouse.php?base_id=' + baseId;
            } else {
                window.location.href = 'warehouse.php';
            }
        }
        let currentBaseId = <?php echo $base_id; ?>;
        let currentSearchType = '<?php echo addslashes($searchType); ?>';
        
        // 基地切换功能
        document.getElementById('base-select').addEventListener('change', function() {
            const newBaseId = this.value;
            if (newBaseId !== currentBaseId.toString()) {
                switchBase(newBaseId);
            }
        });
        
        // 切换基地函数
        function switchBase(baseId) {
            showLoading(true);
            
            const formData = new FormData();
            formData.append('ajax', 'get_base_data');
            formData.append('base_id', baseId);
            formData.append('search_type', currentSearchType);
            
            fetch('warehouse.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentBaseId = parseInt(baseId);
                    updatePageContent(data);
                    updateURL(baseId);
                } else {
                    alert('加载基地数据失败: ' + (data.error || '未知错误'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('网络错误，请重试');
            })
            .finally(() => {
                showLoading(false);
            });
        }
        
        // 显示/隐藏加载状态
        function showLoading(show) {
            document.getElementById('loading').style.display = show ? 'block' : 'none';
            document.getElementById('warehouse-content').style.display = show ? 'none' : 'block';
        }
        
        // 更新页面内容
        function updatePageContent(data) {
            // 更新页面标题
            document.getElementById('page-title').textContent = data.base_info.name + ' - 库区可视化';
            document.title = data.base_info.name + ' - 库区可视化';
            
            // 更新仓库布局内容
            document.getElementById('warehouse-content').innerHTML = data.layout_html;
            
            // 重新绑定事件
            bindRackEvents();
        }
        
        // 更新URL（不刷新页面）
        function updateURL(baseId) {
            const url = new URL(window.location);
            url.searchParams.set('base_id', baseId);
            if (currentSearchType) {
                url.searchParams.set('search_type', currentSearchType);
            } else {
                url.searchParams.delete('search_type');
            }
            window.history.pushState({}, '', url);
        }
        
        // 搜索玻璃类型
        function searchGlassType() {
            const searchType = document.getElementById('search_type').value;
            currentSearchType = searchType;
            
            if (searchType) {
                switchBase(currentBaseId); // 重新加载当前基地数据以应用搜索
            } else {
                clearSearch();
            }
        }
        
        // 清除搜索
        function clearSearch() {
            document.getElementById('search_type').value = '';
            currentSearchType = '';
            switchBase(currentBaseId); // 重新加载当前基地数据以清除搜索
        }
        
        // 绑定库位事件
        function bindRackEvents() {
            const tooltip = document.getElementById('tooltip');
            const racks = document.querySelectorAll('.rack[data-rack]');
            
            racks.forEach(rack => {
                rack.addEventListener('mouseenter', function(e) {
                    showTooltip(this, tooltip);
                });
                
                rack.addEventListener('mouseleave', function() {
                    tooltip.classList.remove('show');
                });
                
                rack.addEventListener('click', function() {
                    const rackCode = this.dataset.rack;
                    const areaType = this.dataset.areaType || 'storage';
                    
                    // 根据库位类型决定跳转页面
                    if (areaType === 'processing') {
                        // 加工区库位跳转到加工区库存页面
                        window.open(`viewer/processing_inventory.php?search=${rackCode}`, '_blank');
                    } else {
                        // 普通库位跳转到库存查询页面
                        window.open(`viewer/inventory.php?search=${rackCode}`, '_blank');
                    }
                });
            });
        }
        
        // 显示悬浮提示
        function showTooltip(rack, tooltip) {
            const rackCode = rack.dataset.rack;
            const packages = rack.dataset.packages || '0';
            const pieces = rack.dataset.pieces || '0';
            const types = rack.dataset.types || '';
            const colors = rack.dataset.colors || '';
            const thicknesses = rack.dataset.thicknesses || '';
            const dimensions = rack.dataset.dimensions || '';
            const codes = rack.dataset.codes || '';
            const area = rack.dataset.area || '0';
            
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
            
            // 定位提示框 - 添加边界检测
            const rect = rack.getBoundingClientRect();
            const tooltipRect = tooltip.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            
            // 计算初始位置
            let left = rect.left + rect.width / 2 - tooltipRect.width / 2;
            let top = rect.top - tooltipRect.height - 10;
            
            // 左右边界检测
            if (left < 10) {
                left = 10; // 距离左边界至少10px
            } else if (left + tooltipRect.width > viewportWidth - 10) {
                left = viewportWidth - tooltipRect.width - 10; // 距离右边界至少10px
            }
            
            // 上下边界检测
            if (top < 10) {
                // 如果上方空间不足，显示在库位下方
                top = rect.bottom + 10;
            }
            
            // 确保不超出底部边界
            if (top + tooltipRect.height > viewportHeight - 10) {
                top = viewportHeight - tooltipRect.height - 10;
            }
            
            tooltip.style.left = left + 'px';
            tooltip.style.top = top + 'px';
        }
        
        // 鼠标移动时更新提示框位置
        document.addEventListener('mousemove', function(e) {
            const tooltip = document.getElementById('tooltip');
            if (tooltip.classList.contains('show')) {
                const tooltipRect = tooltip.getBoundingClientRect();
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;
                
                // 计算初始位置（跟随鼠标）
                let left = e.clientX - tooltipRect.width / 2;
                let top = e.clientY - tooltipRect.height - 10;
                
                // 左右边界检测
                if (left < 10) {
                    left = 10;
                } else if (left + tooltipRect.width > viewportWidth - 10) {
                    left = viewportWidth - tooltipRect.width - 10;
                }
                
                // 上下边界检测
                if (top < 10) {
                    // 如果上方空间不足，显示在鼠标下方
                    top = e.clientY + 10;
                }
                
                // 确保不超出底部边界
                if (top + tooltipRect.height > viewportHeight - 10) {
                    top = viewportHeight - tooltipRect.height - 10;
                }
                
                tooltip.style.left = left + 'px';
                tooltip.style.top = top + 'px';
            }
        });
        
        // 页面加载完成后绑定事件
        document.addEventListener('DOMContentLoaded', function() {
            bindRackEvents();
        });
    </script>
</body>
</html>
<?php
$content = ob_get_clean();
echo $content;
?>
