<?php
// warehouse_layout.php - 仓库布局渲染文件
// 根据不同基地ID渲染对应的库位布局

/**
 * 渲染信义基地布局 (base_id = 1)
 */
function renderXinyiBaseLayout($rackInventory, $highlightRacks) {
    ob_start();
    ?>
    <div class="warehouse-layout">
        <!-- 上方三行库位区域 -->
        <div class="top-storage-area">
            <!-- 第一行：38-52（从左到右） -->
            <div class="storage-row">
                <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                    <?php
                    // 第一行：38B 38A 39B 39A ... 52B 52A
                    for ($num = 38; $num <= 52; $num++) {
                        echo '<div class="rack-pair">';
                        
                        // B架
                        echo renderRack($num . 'A', $rackInventory, $highlightRacks);
                        
                        // A架
                        echo renderRack($num . 'B', $rackInventory, $highlightRacks);
                        
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
            
            <!-- 第二行：23-37（从左到右） -->
            <div class="storage-row">
                <div style="display: flex; gap: 15px; margin-bottom: 10px;">
                    <?php
                    for ($num = 37; $num >= 23; $num--) {
                        echo '<div class="rack-pair">';
                        
                        // B架
                        echo renderRack($num . 'A', $rackInventory, $highlightRacks);
                        
                        // A架
                        echo renderRack($num . 'B', $rackInventory, $highlightRacks);
                        
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
            
            <!-- 第三行：10-22（从右到左） -->
            <div class="storage-row">
                <div style="display: flex; gap: 15px;">
                    <?php
                    for ($num = 10; $num < 23; $num++) {
                        echo '<div class="rack-pair">';
                        
                        // B架
                        echo renderRack($num . 'A', $rackInventory, $highlightRacks);
                        
                        // A架
                        echo renderRack($num . 'B', $rackInventory, $highlightRacks);
                        
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- 中间过道区域 -->
        <div class="middle-area">
            
            <div class="aisle">
                <div class="door">🚪 前门</div>    
                    <div class="road">← 主通道 →</div>
                <div class="door">🚪 后门</div>
            </div>
            
        </div>
        
        <!-- 下方区域 -->
        <div class="bottom-area">
            <!-- 左下角：3-9号库位架（2行×4列）+ 加工区 -->
            <div class="left-bottom">
                <div class="storage-grid">
                    <?php
                    // 第一行：5B 5A 4B 4A 3B 3A
                    for ($num = 5; $num >= 3; $num--) {
                        echo '<div class="rack-pair">';
                        echo renderRack($num . 'B', $rackInventory, $highlightRacks);
                        echo renderRack($num . 'A', $rackInventory, $highlightRacks);
                        echo '</div>';
                    }
                    echo "<div style=\"display: flex; gap: 15px;\"></div>";
                    // 第二行：6B 6A 7B 7A 8B 8A 9B 9A
                    for ($num = 6; $num <= 9; $num++) {
                        echo '<div class="rack-pair">';
                        echo renderRack($num . 'B', $rackInventory, $highlightRacks);
                        echo renderRack($num . 'A', $rackInventory, $highlightRacks);
                        echo '</div>';
                    }
                    ?>
                </div>
                
                <!-- 加工区 -->
                <div class="processing-area">
                    <?php
                    echo '<div class="rack processing" data-rack="B1">B1</div>';
                    echo '<div class="rack processing" data-rack="A1">A1</div>';
                    ?>
                </div>
            </div>
            
            <!-- 右下角：53-56号库位架（垂直排列） -->
            <div class="right-bottom">
                <div class="vertical-racks">
                    <?php
                    for ($num = 53; $num <= 56; $num++) {
                        echo '<div class="rack-pair">';
                        echo renderRack($num . 'B', $rackInventory, $highlightRacks);
                        echo renderRack($num . 'A', $rackInventory, $highlightRacks);
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * 渲染新丰基地布局 (base_id = 2)
 */
function renderXinfengBaseLayout($rackInventory, $highlightRacks) {
    ob_start();
    ?>
    <div class="warehouse-layout" style="padding: 50px 10px;width: 1800px;border:#ffc107 solid 3px;border-radius:15px">
        <!-- 上方库位区域 -->
        <div id="stockarea2" class="top-storage-area">
            <div class="storage-row" style="display: flex; gap: 8px; margin-bottom: 20px; justify-content: center;">
                <!-- 左侧区域：24A24B 到 12A12B -->
                <div style="display: flex; gap: 3px; padding: 5px; border: 1px solid #ddd; border-radius: 3px;">
                    <?php
                    for ($num = 24; $num >= 12; $num--) {
                        echo '<div class="rack-pair" style="display: flex; gap: 2px;">';
                        echo renderRack($num . 'A', $rackInventory, $highlightRacks);
                        echo renderRack($num . 'B', $rackInventory, $highlightRacks);
                        echo '</div>';
                    }
                    ?>
                </div>
                
                <!-- 侧门 -->
                <div style="display: flex; align-items: center; padding: 8px 12px; background-color: #e3f2fd; border: 1px solid #2196f3; border-radius: 3px;height:30px; font-weight: bold; color: #1976d2; min-width: 50px; justify-content: center; font-size: 12px;">
                    侧门
                </div>
                
                <!-- 右侧区域：11A11B 到 8A8B -->
                <div id="stockarea3" style="display: flex; gap: 3px; padding: 5px; border: 1px solid #ddd; border-radius: 3px;">
                    <?php
                    for ($num = 11; $num >= 8; $num--) {
                        echo '<div class="rack-pair" style="display: flex; gap: 2px;">';
                        echo renderRack($num . 'A', $rackInventory, $highlightRacks);
                        echo renderRack($num . 'B', $rackInventory, $highlightRacks);
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- 中间通道区域 -->
        <div class="middle-area" style="margin: 30px 0;width: 1780px;">
            <div class="aisle" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; background-color: #f5f5f5; border-radius: 5px;">
                <div class="door" style="padding: 10px; background-color: #fff3cd; border: 2px solid #ffc107; border-radius: 5px; font-weight: bold;">厕所</div>
                <div class="road" style="flex: 1; text-align: center; font-size: 18px; font-weight: bold; color: #666;">← 通道 →</div>
                <div class="door" style="padding: 10px; background-color: #d4edda; border: 2px solid #28a745; border-radius: 5px; font-weight: bold;">大门</div>
            </div>
        </div>
        
        <!-- 下方区域 -->
        <div class="bottom-area">
            <div style="display: flex; gap: 30px; justify-content: flex-end; align-items: flex-start;">
                <div style="width: 500px;"></div> 
                <!-- 左侧加工区：A -->
                <div class="processing-left" style="display: flex; flex-direction: column; gap: 10px;">
                    <div style="margin-right: 30px;margin-top: -30px;transform: rotate(90deg);">
                        <?php
                        echo renderRack('A', $rackInventory, $highlightRacks, 'processing');
                        ?>
                    </div>
                    <div style="transform: rotate(90deg);margin-right: 30px;margin-top: -50px;">
                        <?php
                        echo renderRack('B', $rackInventory, $highlightRacks, 'processing');
                        ?>
                    </div>
                </div>
                <div id="stockarea4" class="middle-storage" style="width: 120px;display: flex; gap: 10px; padding: 10px; border: 2px solid #ddd; border-radius: 5px;">
                    <div class="rack-pair">
                        <?php
                            echo renderRack('25B', $rackInventory, $highlightRacks);
                            echo renderRack('25A', $rackInventory, $highlightRacks);
                        ?>
                    </div>
                </div>  
                    
                <!-- 中间库存区：7B7A 到 5B5A -->
                <div id="stockarea1" class="middle-storage" style="display: flex; gap: 10px; padding: 10px; border: 2px solid #ddd; border-radius: 5px;">
                    <?php
                    for ($num = 7; $num >= 5; $num--) {
                        echo '<div class="rack-pair">';
                        echo renderRack($num . 'B', $rackInventory, $highlightRacks);
                        echo renderRack($num . 'A', $rackInventory, $highlightRacks);
                        echo '</div>';
                    }
                    ?>
                </div>
                
                <!-- 右侧加工区：4、3、2、1 -->
                <div class="processing-right" style="display: flex; gap: 10px;">
                    <?php
                    for ($num = 4; $num >= 3; $num--) {
                        echo renderRack((string)$num, $rackInventory, $highlightRacks, 'processing');
                    }
                    ?>
                    <div style="width: 100px;"></div>
                    <?php
                    for ($num = 2; $num >= 1; $num--) {
                        echo renderRack((string)$num, $rackInventory, $highlightRacks, 'processing');
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * 渲染其他基地布局的占位函数
 */
function renderOtherBaseLayout($baseId, $rackInventory, $highlightRacks) {
    ob_start();
    ?>
    <div class="warehouse-layout">
        <div style="text-align: center; padding: 60px 20px; color: #666;">
            <h2>基地 <?php echo $baseId; ?> 的布局</h2>
            <p style="margin-top: 20px; font-size: 16px;">该基地的库位布局正在开发中...</p>
            <p style="margin-top: 10px; color: #999;">当前共有 <?php echo count($rackInventory); ?> 个库位</p>
            
            <!-- 临时显示所有库位 -->
            <div style="margin-top: 30px; display: flex; flex-wrap: wrap; gap: 10px; justify-content: center;">
                <?php
                foreach ($rackInventory as $rackCode => $data) {
                    echo renderRack($rackCode, $rackInventory, $highlightRacks);
                }
                ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// 根据当前基地ID渲染对应布局
if ($base_id == 1) {
    echo renderXinyiBaseLayout($rackInventory, $highlightRacks);
} elseif ($base_id == 2) {
    echo renderXinfengBaseLayout($rackInventory, $highlightRacks);
} else {
    echo renderOtherBaseLayout($base_id, $rackInventory, $highlightRacks);
}
?>

