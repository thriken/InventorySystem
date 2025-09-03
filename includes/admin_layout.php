<?php
require_once __DIR__ . '/admin_sidebar.php';

/**
 * 渲染管理后台页面布局
 * @param string $pageTitle 页面标题
 * @param string $content 页面内容
 * @param array $currentUser 当前用户信息
 * @param string $currentPage 当前页面文件名
 * @param array $additionalCSS 额外的CSS文件
 * @param array $additionalJS 额外的JS文件
 * @param string $message 消息内容
 * @param string $messageType 消息类型 (success, error, warning, info)
 */
function renderAdminLayout($pageTitle, $content, $currentUser, $currentPage = '', $additionalCSS = [], $additionalJS = [], $message = '', $messageType = 'info') {
    $sidebar = renderAdminSidebar($currentUser, $currentPage);
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo APP_NAME; ?></title>
        <link rel="stylesheet" href="../assets/css/main.css">
        <link rel="stylesheet" href="../assets/css/admin.css">
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
        <script src="../assets/js/datatable-config.js"></script>
        <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
        <link rel="stylesheet" href="../assets/css/datatable-theme.css">
        <?php foreach ($additionalCSS as $css): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
        <?php endforeach; ?>
    </head>
    <body>
        <div class="admin-container">
            <?php echo $sidebar; ?>
            <div class="admin-content">
                <div class="admin-header">
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="admin-header-right">
                        当前时间：<span class="current-time" id="current-time"></span>
                    </div>
                </div>
                
                <!-- 全局消息提示区域 -->
                <?php if ($message): ?>
                <div id="messageContainer" style="margin-bottom: 20px;">
                    <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="close" onclick="this.parentElement.parentElement.style.display='none'">&times;</button>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php echo $content; ?>
            </div>
        </div>
        
        <script>
            // 显示当前时间
            function updateTime() {
                const now = new Date();
                document.getElementById('current-time').textContent = now.toLocaleString('zh-CN');
            }
            updateTime();
            setInterval(updateTime, 1000);
            
            // 自动隐藏消息提示（可选）
            setTimeout(function() {
                const messageContainer = document.getElementById('messageContainer');
                if (messageContainer) {
                    messageContainer.style.transition = 'opacity 0.5s ease';
                    messageContainer.style.opacity = '0';
                    setTimeout(function() {
                        messageContainer.style.display = 'none';
                    }, 500);
                }
            }, 5000); // 5秒后自动隐藏
        </script>
        
        <?php foreach ($additionalJS as $js): ?>
        <script src="<?php echo htmlspecialchars($js); ?>"></script>
        <?php endforeach; ?>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>