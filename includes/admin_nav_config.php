<?php
/**
 * 管理后台导航配置
 * 根据用户角色显示不同的导航项
 */

/**
 * 获取导航配置
 * @param string $userRole 用户角色
 * @return array 导航配置数组
 */
function getAdminNavConfig($userRole) {
    $navItems = [
        [
            'id' => 'dashboard',
            'title' => '仪表盘',
            'url' => 'index.php',
            'icon' => 'icon-dashboard',
            'roles' => ['admin', 'manager', 'operator'], // 所有角色都可访问
            'order' => 0
        ],
        [
            'id' => 'bases',
            'title' => '基地管理',
            'url' => 'bases.php',
            'icon' => 'icon-building',
            'roles' => ['admin'], // 仅管理员可访问
            'order' => 2
        ],
        [
            'id' => 'racks',
            'title' => '库位管理',
            'url' => 'racks.php',
            'icon' => 'icon-rack',
            'roles' => ['admin', 'manager'], // 管理员和经理可访问
            'order' => 3
        ],
        [
            'id' => 'glass_types',
            'title' => '原片类型',
            'url' => 'glass_types.php',
            'icon' => 'icon-glass',
            'roles' => ['admin', 'manager'],
            'order' => 4
        ],
        [
            'id' => 'dictionary',
            'title' => '字典管理',
            'url' => 'dictionary.php',
            'icon' => 'icon-dictionary',
            'roles' => ['admin', 'manager'], // 仅管理员可访问
            'order' => 1
        ],
        [
            'id' => 'packages',
            'title' => '原片包管理',
            'url' => 'packages.php',
            'icon' => 'icon-package',
            'roles' => ['admin', 'manager', 'operator'],
            'order' => 5
        ],
        [
            'id' => 'transactions',
            'title' => '流转记录',
            'url' => 'transactions.php',
            'icon' => 'icon-transfer',
            'roles' => ['admin', 'manager', 'operator','viewer'],
            'order' => 6
        ],
        [
            'id' => 'reports',
            'title' => '每日领用',
            'url' => 'reports.php',
            'icon' => 'icon-report',
            'roles' => ['admin', 'manager','viewer'],
            'order' => 7
        ],
        [
            'id' => 'users',
            'title' => '用户管理',
            'url' => 'users.php',
            'icon' => 'icon-users',
            'roles' => ['admin'], // 仅管理员可访问
            'order' => 8
        ],
        [
            'id' => 'settings',
            'title' => '系统设置',
            'url' => 'settings.php',
            'icon' => 'icon-settings',
            'roles' => ['admin'], // 仅管理员可访问
            'order' => 9
        ],
        [
            'id' => 'tools',
            'title' => '基地信息导入',
            'url' => 'tools_baseinfo.php',
            'icon' => 'icon-tools',
            'roles' => ['admin'], // 仅管理员可访问
            'order' => 10
        ],
                [
            'id' => 'tools',
            'title' => '原片包导入',
            'url' => 'tools_packages.php',
            'icon' => 'icon-tools',
            'roles' => ['manager'], // 仅管理员可访问
            'order' => 10
        ],
        [
            'id' => 'inventory',
            'title' => '查看库存',
            'url' => '../viewer/inventory.php',
            'icon' => 'icon-tools',
            'roles' => ['admin', 'manager'], // 仅管理员可访问
            'order' => 11
        ],

    ];
    
    // 根据用户角色过滤导航项
    $filteredItems = array_filter($navItems, function($item) use ($userRole) {
        return in_array($userRole, $item['roles']);
    });
    
    // 按order排序
    usort($filteredItems, function($a, $b) {
        return $a['order'] - $b['order'];
    });
    
    return $filteredItems;
}

/**
 * 获取当前激活的导航项ID
 * @param string $currentPage 当前页面文件名
 * @return string 导航项ID
 */
function getCurrentNavId($currentPage) {
    $pageNavMap = [
        'index.php' => 'dashboard',
        'bases.php' => 'bases',
        'racks.php' => 'racks',
        'glass_types.php' => 'glass_types',
        'dictionary.php' => 'dictionary',
        'packages.php' => 'packages',
        'transactions.php' => 'transactions',
        'reports.php' => 'reports',
        'users.php' => 'users',
        'settings.php' => 'settings'
    ];
    
    return $pageNavMap[$currentPage] ?? '';
}
?>