<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据迁移 - inventory_transactions → inventory_operation_records</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        header p {
            opacity: 0.9;
            font-size: 14px;
        }

        main {
            padding: 30px;
        }

        .status-section {
            margin-bottom: 30px;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .status-item {
            text-align: center;
            padding: 20px;
            border: 2px solid #f0f0f0;
            border-radius: 8px;
            background: #fafafa;
        }

        .status-number {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }

        .status-label {
            font-size: 12px;
            color: #666;
        }

        .actions {
            text-align: center;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 24px;
            margin: 0 10px 10px 0;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        .log-box {
            margin-top: 20px;
            padding: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #f9fafb;
            height: 200px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
            line-height: 1.4;
            display: none;
        }

        .log-box div {
            margin-bottom: 5px;
        }

        .note {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .note strong {
            color: #d97706;
        }

        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .success { color: #16a34a; }
        .error { color: #dc2626; }
        .warning { color: #d97706; }
        .info { color: #0891b2; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>📊 数据迁移</h1>
            <p>将 inventory_transactions 表数据迁移到 inventory_operation_records 表</p>
        </header>

        <main>
            <div class="note">
                <strong>⚠️ 重要提醒：</strong>
                <br>• 迁移前请备份数据库
                <br>• 确保目标表结构已创建
                <br>• 建议在测试环境先验证
            </div>

            <div class="status-section">
                <div class="status-grid">
                    <div class="status-item">
                        <div class="status-number" id="sourceCount">-</div>
                        <div class="status-label">源表记录数</div>
                    </div>
                    <div class="status-item">
                        <div class="status-number" id="targetCount">-</div>
                        <div class="status-label">目标表记录数</div>
                    </div>
                    <div class="status-item">
                        <div class="status-number" id="statusCheck">-</div>
                        <div class="status-label">状态检查</div>
                    </div>
                </div>
            </div>

            <div class="actions">
                <button class="btn btn-primary" onclick="migrateData()">
                    🚀 开始迁移
                </button>
                <button class="btn btn-warning" onclick="checkStatus()">
                    🔍 检查状态
                </button>
                <button class="btn btn-danger" onclick="resetTarget()">
                    🔄 重置目标表
                </button>
            </div>

            <div id="logBox" class="log-box"></div>
        </main>
    </div>

    <script>
        // 页面加载时检查状态
        document.addEventListener('DOMContentLoaded', function() {
            checkStatus();
        });

        function showLog(message, type = 'info') {
            const logBox = document.getElementById('logBox');
            logBox.style.display = 'block';
            
            const timestamp = new Date().toLocaleTimeString();
            const div = document.createElement('div');
            div.className = type;
            div.innerHTML = `[${timestamp}] ${message}`;
            
            logBox.appendChild(div);
            logBox.scrollTop = logBox.scrollHeight;
        }

        function setLoading(elementId, loading = true) {
            const element = document.getElementById(elementId);
            if (loading) {
                element.innerHTML = '<span class="loading"></span>';
            } else {
                element.innerHTML = '✓';
            }
        }

        async function checkStatus() {
            try {
                setLoading('statusCheck', true);
                showLog('正在检查数据库状态...', 'info');
                
                const response = await fetch('web_runner.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=status'
                });
                
                const data = await response.json();
                
                document.getElementById('sourceCount').textContent = data.source_count || 0;
                document.getElementById('targetCount').textContent = data.target_count || 0;
                document.getElementById('statusCheck').innerHTML = data.ready ? 
                    '<span style="color: #16a34a">✓</span>' : 
                    '<span style="color: #dc2626">✗</span>';
                
                if (data.ready) {
                    showLog('数据库状态正常，可以开始迁移', 'success');
                } else {
                    showLog('数据库状态异常：' + data.message, 'error');
                }
                
            } catch (error) {
                showLog('状态检查失败：' + error.message, 'error');
                document.getElementById('statusCheck').innerHTML = '<span style="color: #dc2626">✗</span>';
            }
        }

        async function migrateData() {
            if (!confirm('确定要开始数据迁移吗？\n\n此操作将把 inventory_transactions 表的所有数据迁移到 inventory_operation_records 表。')) {
                return;
            }

            try {
                showLog('开始数据迁移...', 'info');
                
                const response = await fetch('web_runner.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=migrate'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showLog(`迁移完成！成功迁移 ${data.migrated} 条记录`, 'success');
                    if (data.error_count > 0) {
                        showLog(`警告：有 ${data.error_count} 条记录存在关联问题`, 'warning');
                    }
                    setTimeout(checkStatus, 1000);
                } else {
                    showLog('迁移失败：' + data.message, 'error');
                }
                
            } catch (error) {
                showLog('迁移过程出错：' + error.message, 'error');
            }
        }

        async function resetTarget() {
            if (!confirm('确定要重置目标表吗？\n\n此操作将删除 inventory_operation_records 表中的所有数据！')) {
                return;
            }

            try {
                showLog('正在重置目标表...', 'warning');
                
                const response = await fetch('web_runner.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=truncate'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showLog('目标表重置成功', 'success');
                    setTimeout(checkStatus, 1000);
                } else {
                    showLog('重置失败：' + data.message, 'error');
                }
                
            } catch (error) {
                showLog('重置过程出错：' + error.message, 'error');
            }
        }
    </script>
</body>
</html>