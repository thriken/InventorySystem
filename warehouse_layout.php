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
                    for ($num = 38; $num <= 52; $num++) {
                        echo '<div class="rack-pair">';
                        echo renderRack($num . 'A', $rackInventory, $highlightRacks);
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
                        echo renderRack($num . 'A', $rackInventory, $highlightRacks);
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
                        echo renderRack($num . 'A', $rackInventory, $highlightRacks);
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
            <div class="left-bottom">
                <div class="storage-grid">
                    <?php
                    echo '<div class="rack-pair">';
                    echo renderRack('57B', $rackInventory, $highlightRacks);
                    echo renderRack('57A', $rackInventory, $highlightRacks);
                    echo '</div>';
                    for ($num = 5; $num >= 3; $num--) {
                        echo '<div class="rack-pair">';
                        echo renderRack($num . 'B', $rackInventory, $highlightRacks);
                        echo renderRack($num . 'A', $rackInventory, $highlightRacks);
                        echo '</div>';
                    }
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
                    echo renderRack('B1', $rackInventory, $highlightRacks);
                    echo renderRack('A1', $rackInventory, $highlightRacks);
                    ?>
                </div>
            </div>
            
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
    <div class="warehouse-layout" style="padding: 50px 10px;border:#ffc107 solid 3px;border-radius:15px">
        <!-- 上方库位区域 -->
        <div id="stockarea2" class="top-storage-area">
            <div class="storage-row" style="display: flex; gap: 8px; margin-bottom: 20px; justify-content: center;">
                <!-- 左侧区域：24A24B 到 12A12B -->
                <div style="display: flex; gap: 20px; padding: 5px; border: 1px solid #ddd; border-radius: 3px;">
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
                <div id="stockarea3" style="display: flex; gap: 20px; padding: 5px; border: 1px solid #ddd; border-radius: 3px;">
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
                        echo renderRack('B', $rackInventory, $highlightRacks);
                        ?>
                    </div>
                    <div style="transform: rotate(90deg);margin-right: 30px;margin-top: -50px;">
                        <?php
                        echo renderRack('A', $rackInventory, $highlightRacks);
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
                <div id="stockarea1" class="middle-storage" style="display: flex; gap: 20px; padding: 10px; border: 2px solid #ddd; border-radius: 5px;">
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
                        echo renderRack((string)$num, $rackInventory, $highlightRacks);
                    }
                    ?>
                    <div style="width: 100px;"></div>
                    <?php
                    for ($num = 2; $num >= 1; $num--) {
                        echo renderRack((string)$num, $rackInventory, $highlightRacks);
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


/**
 * 渲染金鱼基地布局 (base_id = 3)
 */
function renderJinyuBaseLayout( $rackInventory, $highlightRacks) {
    ob_start();
    ?>
    <div class="warehouse-layout" style="padding: 20px; border: #ffc107 solid 3px; border-radius: 15px; position: relative; min-width: 1400px;">
        
        <div style="display: flex; justify-content: space-between;">
            
            <!-- 左侧：加工位1-4 -->
            <div style="width: 200px; display: flex; flex-direction: column; gap: 40px; padding-top: 100px;">
                <div class="processing-group" style="display: flex; flex-direction: column; gap: 20px;">
                    <?php
                    echo renderRack('1', $rackInventory, $highlightRacks);
                    echo renderRack('2', $rackInventory, $highlightRacks);
                    ?>
                </div>
                
                <div style="height: 60px;"></div> <!-- 间隔 -->
                
                <div class="processing-group" style="display: flex; flex-direction: column; gap: 20px;">
                    <?php
                    echo renderRack('3', $rackInventory, $highlightRacks);
                    echo renderRack('4', $rackInventory, $highlightRacks);
                    ?>
                </div>
            </div>

            <!-- 中间区域：A+B 库位和 B+A 库位 -->
            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; padding: 0 40px;">
                
                <!-- 上方 A+B 区域 -->
                <div class="top-racks" style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 40px;">
                    <!-- 第一行: 24-21 -->
                    <div style="display: flex; gap: 20px; justify-content: center;">
                        <?php
                        for ($i = 20; $i <= 22; $i++) {
                             echo '<div class="rack-pair" style="display: flex; gap: 2px;">';
                             echo renderRack($i . 'A', $rackInventory, $highlightRacks);
                             echo renderRack($i . 'B', $rackInventory, $highlightRacks);
                             echo '</div>';
                        }
                        ?>
                    </div>
                    <!-- 第二行: 20-18 -->
                    <div style="display: flex; gap: 20px; justify-content: center;">
                        <?php
                        for ($i = 16; $i <= 19; $i++) {
                             echo '<div class="rack-pair" style="display: flex; gap: 2px;">';
                             echo renderRack($i . 'A', $rackInventory, $highlightRacks);
                             echo renderRack($i . 'B', $rackInventory, $highlightRacks);
                             echo '</div>';
                        }
                        ?>
                    </div>
                </div>

                <!-- 上方过道箭头 -->
                <div style="width: 100%; display: flex; align-items: center; margin: 20px 0;">
                        <div style="width:100%; border: 1px solid #666; height: 40px; display: flex; align-items: center; justify-content: center; position: relative;">
                             <span style="font-weight: bold; font-size: 18px;">过道</span>
                             <div style="position: absolute; right: -20px; top: 50%; transform: translateY(-50%); width: 0; height: 0; border-top: 20px solid transparent; border-bottom: 20px solid transparent; border-left: 20px solid #fff; border-left-color: inherit;"></div>
                        </div>
                        <div style="width: 0; height: 0; border-top: 20px solid transparent; border-bottom: 20px solid transparent; border-left: 20px solid #666;"></div>
                    </div>

                <!-- 下方 B+A 区域 -->
                <div class="bottom-racks" style="display: flex; flex-direction: column; gap: 15px; margin-top: 40px;">
                    <!-- 第一组: 17-14 -->
                    <div style="display: flex; gap: 20px; justify-content: center;">
                        <?php
                        for ($i = 15; $i >= 12; $i--) {
                             echo '<div class="rack-pair" style="display: flex; gap: 2px;">';
                             echo renderRack($i . 'B', $rackInventory, $highlightRacks);
                             echo renderRack($i . 'A', $rackInventory, $highlightRacks);
                             echo '</div>';
                        }
                        ?>
                    </div>
                    
                    <!-- 立柱隔断 -->
                    <div style="border: 1px solid #666;  text-align: center;  background:rgb(19, 12, 12);color:#fff">立柱隔断</div>

                    <!-- 下方过道箭头 -->
                    <div style="width: 100%; display: flex; align-items: center; margin: 20px 0;">
                        <div style="flex: 1; border: 1px solid #666; height: 40px; display: flex; align-items: center; justify-content: center; position: relative;">
                             <span style="font-weight: bold; font-size: 18px;">过道</span>
                             <div style="position: absolute; right: -20px; top: 50%; transform: translateY(-50%); width: 0; height: 0; border-top: 20px solid transparent; border-bottom: 20px solid transparent; border-left: 20px solid #fff; border-left-color: inherit;"></div>
                        </div>
                        <div style="width: 0; height: 0; border-top: 20px solid transparent; border-bottom: 20px solid transparent; border-left: 20px solid #666;"></div>
                    </div>

                    <!-- 第二组: 13-10 -->
                    <div style="display: flex; gap: 20px; justify-content: center;">
                        <?php
                        for ($i = 11; $i >= 8; $i--) {
                             echo '<div class="rack-pair" style="display: flex; gap: 2px;">';
                             echo renderRack($i . 'B', $rackInventory, $highlightRacks);
                             echo renderRack($i . 'A', $rackInventory, $highlightRacks);
                             echo '</div>';
                        }
                        ?>
                    </div>
                    <!-- 第三组: 9-7 -->
                    <div style="display: flex; gap: 20px; justify-content: center;">
                         <?php
                        for ($i = 7; $i >= 5; $i--) {
                             echo '<div class="rack-pair" style="display: flex; gap: 2px;">';
                             echo renderRack($i . 'B', $rackInventory, $highlightRacks);
                             echo renderRack($i . 'A', $rackInventory, $highlightRacks);
                             echo '</div>';
                        }
                        ?>
                    </div>
                </div>

            </div>

            <!-- 右侧通道及纵向库位 -->
            <div style="display: flex; gap: 20px;">
                <!-- 垂直通道箭头 -->
                 <div style="width: 60px; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative;">
                    <div style="position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 20px solid transparent; border-right: 20px solid transparent; border-bottom: 20px solid #666;"></div>
                    <div style="width: 2px; height: 100%; background: #666;"></div>
                    <div style="background: #fff; padding: 10px 0; z-index: 1; writing-mode: vertical-rl; font-weight: bold; font-size: 18px;">通道</div>
                    <div style="position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); width: 0; height: 0; border-left: 20px solid transparent; border-right: 20px solid transparent; border-top: 20px solid #666;"></div>
                 </div>

                 <!-- 纵向库位 A+B (Vertical) -->
                 <div class="vertical-rack-group">
                    <?php
                    for ($i = 24; $i < 27; $i++) {
                         $code = ($i < 10) ? '0' . $i : $i;
                        echo renderVerticalRackPair($code . 'A', $code . 'B', $rackInventory, $highlightRacks);
                    }
                    ?>
                 </div>
                 <div class="vertical-rack-group">
                    <?php
                    for ($i = 27; $i <= 30; $i++) {
                         $code = ($i < 10) ? '0' . $i : $i;
                        echo renderVerticalRackPair($code . 'A', $code . 'B', $rackInventory, $highlightRacks);
                    }
                    ?>
                 </div>
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
} elseif ($base_id == 3) {
    echo renderJinyuBaseLayout($rackInventory, $highlightRacks);
} else {
    echo renderOtherBaseLayout($base_id, $rackInventory, $highlightRacks);
}
?>

