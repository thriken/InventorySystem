    </div> <!-- /.container-fluid -->

    <!-- 底部信息 -->
    <footer class="footer">
        <div class="container-fluid">
            <p class="text-muted text-center">
                © <?php echo date('Y'); ?> 原片实时库存系统 - 盘点模块
                <span class="pull-right">
                    <i class="glyphicon glyphicon-user"></i> 
                    当前用户：<?php echo htmlspecialchars($user['username']); ?> |
                    <i class="glyphicon glyphicon-time"></i> 
                    <?php echo date('Y-m-d H:i:s'); ?>
                </span>
            </p>
        </div>
    </footer>

    <!-- 通用JavaScript -->
    <script>
        // 确认操作
        function confirmAction(message, callback) {
            if (confirm(message)) {
                callback();
            }
        }
        
        // 显示加载状态
        function showLoading(button) {
            var originalText = $(button).html();
            $(button).html('<i class="glyphicon glyphicon-refresh glyphicon-spin"></i> 处理中...');
            $(button).prop('disabled', true);
            return originalText;
        }
        
        // 恢复按钮状态
        function restoreButton(button, originalText) {
            $(button).html(originalText);
            $(button).prop('disabled', false);
        }
        
        // 显示消息提示
        function showMessage(type, message) {
            var alertClass = 'alert-' + type;
            var html = '<div class="alert ' + alertClass + ' alert-dismissible" style="position: fixed; top: 70px; right: 20px; z-index: 9999; min-width: 300px;">' +
                       '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
                       '<strong>' + (type === 'success' ? '成功！' : type === 'error' ? '错误！' : '提示！') + '</strong> ' + message +
                       '</div>';
            $('body').append(html);
            
            // 自动消失
            setTimeout(function() {
                $('.alert').fadeOut();
            }, 3000);
        }
        
        // 表格行高亮
        $(document).ready(function() {
            $('table tbody tr').hover(
                function() { $(this).addClass('highlight'); },
                function() { $(this).removeClass('highlight'); }
            );
        });
    </script>
</body>
</html>