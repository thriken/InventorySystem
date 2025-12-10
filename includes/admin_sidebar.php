<?php
require_once __DIR__ . '/admin_nav_config.php';
require_once __DIR__ . '/../includes/app_info.php';
/**
 * 渲染管理后台左侧边栏
 * @param array $currentUser 当前用户信息
 * @param string $currentPage 当前页面文件名
 */
function renderAdminSidebar($currentUser, $currentPage = '') {
    // 获取当前页面文件名
    if (empty($currentPage)) {
        $currentPage = basename($_SERVER['PHP_SELF']);
    }
    
    // 获取导航配置
    $navItems = getAdminNavConfig($currentUser['role']);
    $currentNavId = getCurrentNavId($currentPage);
    
    ob_start();
    ?>
    <div class="admin-sidebar">
        <div class="logo">
            <h2><?php echo getAppName(); ?></h2>
        </div>
        <nav class="admin-nav">
            <ul>
                <?php foreach ($navItems as $item): ?>
                <li<?php echo ($item['id'] === $currentNavId) ? ' class="active"' : ''; ?>>
                    <a href="<?php echo htmlspecialchars($item['url']); ?>">
                        <i class="<?php echo htmlspecialchars($item['icon']); ?>"></i> 
                        <?php echo htmlspecialchars($item['title']); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <div class="admin-sidebar-footer">
            <p>当前用户: <?php echo htmlspecialchars($currentUser['name'] ?: $currentUser['username']); ?></p>
            <p>角色: <?php echo getRoleDisplayName($currentUser['role']); ?></p>
            <p>版本: <?php echo getAppVersion(); ?></p>
            <a href="../logout.php" class="logout-btn">退出登录</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * 获取角色显示名称
 * @param string $role 角色代码
 * @return string 角色显示名称
 */
function getRoleDisplayName($role) {
    $roleNames = [
        'admin' => '系统管理员',
        'manager' => '库管',
        'operator' => '操作员',
        'viewer' => '查看者'
    ];
    return $roleNames[$role] ?? $role;
}
?>