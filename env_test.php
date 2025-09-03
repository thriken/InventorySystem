<?php
// 环境测试页面 - 用于检查服务器环境差异
// 访问地址：http://your-domain/env_test.php

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 安全检查 - 可以设置访问密码
$access_key = 'test123'; // 修改为您的访问密码
if (!isset($_GET['key']) || $_GET['key'] !== $access_key) {
    die('Access denied. Please provide correct access key.');
}

?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>服务器环境测试页面</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .section { background: white; margin: 20px 0; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section h2 { color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .info-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
        .info-label { font-weight: bold; color: #555; }
        .info-value { color: #333; word-break: break-all; }
        .status-ok { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
        .code-block { background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: monospace; white-space: pre-wrap; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        .highlight { background-color: #fff3cd; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 服务器环境测试报告</h1>
        <p><strong>测试时间：</strong><?php echo date('Y-m-d H:i:s'); ?></p>
        <p><strong>服务器IP：</strong><?php echo $_SERVER['SERVER_ADDR'] ?? 'Unknown'; ?></p>
        <p><strong>访问IP：</strong><?php echo $_SERVER['REMOTE_ADDR'] ?? 'Unknown'; ?></p>

        <!-- PHP基本信息 -->
        <div class="section">
            <h2>📋 PHP基本信息</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">PHP版本：</span>
                    <span class="info-value"><?php echo PHP_VERSION; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">PHP SAPI：</span>
                    <span class="info-value"><?php echo php_sapi_name(); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">操作系统：</span>
                    <span class="info-value"><?php echo PHP_OS; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">服务器软件：</span>
                    <span class="info-value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">文档根目录：</span>
                    <span class="info-value"><?php echo $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">当前脚本路径：</span>
                    <span class="info-value"><?php echo __FILE__; ?></span>
                </div>
            </div>
        </div>

        <!-- PHP扩展检查 -->
        <div class="section">
            <h2>🔌 PHP扩展检查</h2>
            <?php
            $required_extensions = [
                'pdo' => 'PDO数据库抽象层',
                'pdo_mysql' => 'MySQL PDO驱动',
                'mysqli' => 'MySQLi扩展',
                'json' => 'JSON支持',
                'mbstring' => '多字节字符串',
                'openssl' => 'OpenSSL加密',
                'curl' => 'cURL支持',
                'gd' => 'GD图像处理',
                'zip' => 'ZIP压缩',
                'xml' => 'XML解析',
                'session' => 'Session支持'
            ];
            ?>
            <table>
                <tr><th>扩展名</th><th>描述</th><th>状态</th><th>版本</th></tr>
                <?php foreach ($required_extensions as $ext => $desc): ?>
                <tr>
                    <td><?php echo $ext; ?></td>
                    <td><?php echo $desc; ?></td>
                    <td>
                        <?php if (extension_loaded($ext)): ?>
                            <span class="status-ok">✅ 已安装</span>
                        <?php else: ?>
                            <span class="status-error">❌ 未安装</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        if (extension_loaded($ext)) {
                            $version = phpversion($ext);
                            echo $version ?: 'N/A';
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- Composer和第三方库检查 -->
        <div class="section">
            <h2>📦 Composer和第三方库</h2>
            <?php
            $vendor_path = __DIR__ . '/vendor';
            $composer_json = __DIR__ . '/composer.json';
            $composer_lock = __DIR__ . '/composer.lock';
            ?>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">vendor目录：</span>
                    <span class="info-value <?php echo is_dir($vendor_path) ? 'status-ok' : 'status-error'; ?>">
                        <?php echo is_dir($vendor_path) ? '✅ 存在' : '❌ 不存在'; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">composer.json：</span>
                    <span class="info-value <?php echo file_exists($composer_json) ? 'status-ok' : 'status-error'; ?>">
                        <?php echo file_exists($composer_json) ? '✅ 存在' : '❌ 不存在'; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">composer.lock：</span>
                    <span class="info-value <?php echo file_exists($composer_lock) ? 'status-ok' : 'status-error'; ?>">
                        <?php echo file_exists($composer_lock) ? '✅ 存在' : '❌ 不存在'; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">autoload.php：</span>
                    <span class="info-value <?php echo file_exists($vendor_path . '/autoload.php') ? 'status-ok' : 'status-error'; ?>">
                        <?php echo file_exists($vendor_path . '/autoload.php') ? '✅ 存在' : '❌ 不存在'; ?>
                    </span>
                </div>
            </div>

            <?php if (file_exists($vendor_path . '/autoload.php')): ?>
                <h3>已安装的包：</h3>
                <?php
                try {
                    require_once $vendor_path . '/autoload.php';
                    if (class_exists('Composer\\InstalledVersions')) {
                        $packages = Composer\InstalledVersions::getInstalledPackages();
                        echo '<table><tr><th>包名</th><th>版本</th></tr>';
                        foreach ($packages as $package) {
                            $version = Composer\InstalledVersions::getVersion($package);
                            echo '<tr><td>' . htmlspecialchars($package) . '</td><td>' . htmlspecialchars($version) . '</td></tr>';
                        }
                        echo '</table>';
                    }
                } catch (Exception $e) {
                    echo '<p class="status-error">无法获取包信息: ' . htmlspecialchars($e->getMessage()) . '</p>';
                }
                ?>
            <?php endif; ?>
        </div>

        <!-- 数据库连接测试 -->
        <div class="section">
            <h2>🗄️ 数据库连接测试</h2>
            <?php
            try {
                // 尝试包含数据库配置
                $config_files = [
                    __DIR__ . '/config/database.php',
                    __DIR__ . '/config/config.php',
                    __DIR__ . '/includes/db.php'
                ];
                
                $db_config = null;
                foreach ($config_files as $config_file) {
                    if (file_exists($config_file)) {
                        echo '<p>找到配置文件: ' . $config_file . '</p>';
                        include_once $config_file;
                        break;
                    }
                }
                
                // 尝试连接数据库
                if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
                    echo '<div class="info-grid">';
                    echo '<div class="info-item"><span class="info-label">数据库主机:</span><span class="info-value">' . DB_HOST . '</span></div>';
                    echo '<div class="info-item"><span class="info-label">数据库名:</span><span class="info-value">' . DB_NAME . '</span></div>';
                    echo '<div class="info-item"><span class="info-label">用户名:</span><span class="info-value">' . DB_USER . '</span></div>';
                    echo '</div>';
                    
                    try {
                        $pdo = new PDO(
                            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                            DB_USER,
                            DB_PASS,
                            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                        );
                        echo '<p class="status-ok">✅ 数据库连接成功</p>';
                        
                        // 获取MySQL版本
                        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
                        echo '<p>MySQL版本: ' . $version . '</p>';
                        
                        // 检查关键表是否存在
                        $tables = ['glass_packages', 'glass_types', 'storage_racks', 'users'];
                        echo '<h4>数据表检查:</h4><table><tr><th>表名</th><th>状态</th><th>记录数</th></tr>';
                        foreach ($tables as $table) {
                            try {
                                $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                                echo '<tr><td>' . $table . '</td><td class="status-ok">✅ 存在</td><td>' . $count . '</td></tr>';
                            } catch (Exception $e) {
                                echo '<tr><td>' . $table . '</td><td class="status-error">❌ 不存在</td><td>-</td></tr>';
                            }
                        }
                        echo '</table>';
                        
                    } catch (PDOException $e) {
                        echo '<p class="status-error">❌ 数据库连接失败: ' . htmlspecialchars($e->getMessage()) . '</p>';
                    }
                } else {
                    echo '<p class="status-warning">⚠️ 未找到数据库配置常量</p>';
                }
            } catch (Exception $e) {
                echo '<p class="status-error">❌ 数据库测试出错: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            ?>
        </div>

        <!-- 文件权限检查 -->
        <div class="section">
            <h2>📁 文件权限检查</h2>
            <?php
            $check_paths = [
                __DIR__ => '项目根目录',
                __DIR__ . '/temp' => '临时目录',
                __DIR__ . '/backups' => '备份目录',
                __DIR__ . '/assets' => '资源目录',
                __DIR__ . '/config' => '配置目录'
            ];
            ?>
            <table>
                <tr><th>路径</th><th>描述</th><th>存在</th><th>可读</th><th>可写</th><th>权限</th></tr>
                <?php foreach ($check_paths as $path => $desc): ?>
                <tr>
                    <td><?php echo $path; ?></td>
                    <td><?php echo $desc; ?></td>
                    <td><?php echo file_exists($path) ? '<span class="status-ok">✅</span>' : '<span class="status-error">❌</span>'; ?></td>
                    <td><?php echo is_readable($path) ? '<span class="status-ok">✅</span>' : '<span class="status-error">❌</span>'; ?></td>
                    <td><?php echo is_writable($path) ? '<span class="status-ok">✅</span>' : '<span class="status-error">❌</span>'; ?></td>
                    <td><?php echo file_exists($path) ? substr(sprintf('%o', fileperms($path)), -4) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- PHP配置检查 -->
        <div class="section">
            <h2>⚙️ PHP重要配置</h2>
            <?php
            $important_settings = [
                'memory_limit' => '内存限制',
                'max_execution_time' => '最大执行时间',
                'upload_max_filesize' => '上传文件大小限制',
                'post_max_size' => 'POST最大大小',
                'max_input_vars' => '最大输入变量数',
                'display_errors' => '显示错误',
                'error_reporting' => '错误报告级别',
                'date.timezone' => '时区设置',
                'session.save_path' => 'Session保存路径'
            ];
            ?>
            <table>
                <tr><th>配置项</th><th>描述</th><th>当前值</th></tr>
                <?php foreach ($important_settings as $setting => $desc): ?>
                <tr>
                    <td><?php echo $setting; ?></td>
                    <td><?php echo $desc; ?></td>
                    <td><?php echo ini_get($setting) ?: '未设置'; ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- 环境变量 -->
        <div class="section">
            <h2>🌍 环境变量</h2>
            <h4>$_SERVER变量 (部分):</h4>
            <div class="code-block"><?php
            $server_vars = ['HTTP_HOST', 'SERVER_NAME', 'REQUEST_URI', 'SCRIPT_NAME', 'QUERY_STRING', 'REQUEST_METHOD', 'HTTP_USER_AGENT'];
            foreach ($server_vars as $var) {
                if (isset($_SERVER[$var])) {
                    echo $var . ' = ' . $_SERVER[$var] . "\n";
                }
            }
            ?></div>
        </div>

        <!-- 错误测试 -->
        <div class="section">
            <h2>🧪 错误测试</h2>
            <p>测试各种可能导致500错误的情况：</p>
            
            <h4>1. PhpSpreadsheet测试:</h4>
            <?php
            try {
                if (class_exists('PhpOffice\\PhpSpreadsheet\\Calculation\\TextData\\Format')) {
                    echo '<p class="status-ok">✅ PhpOffice\\PhpSpreadsheet\\Calculation\\TextData\\Format 类存在</p>';
                } else {
                    echo '<p class="status-error">❌ PhpOffice\\PhpSpreadsheet\\Calculation\\TextData\\Format 类不存在</p>';
                }
                
                if (class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
                    echo '<p class="status-ok">✅ PhpSpreadsheet 主类存在</p>';
                } else {
                    echo '<p class="status-error">❌ PhpSpreadsheet 主类不存在</p>';
                }
            } catch (Exception $e) {
                echo '<p class="status-error">❌ PhpSpreadsheet 测试出错: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            ?>
            
            <h4>2. 自动加载测试:</h4>
            <?php
            try {
                // 测试一个不存在的类
                if (class_exists('NonExistentClass\\Test')) {
                    echo '<p class="status-warning">⚠️ 意外找到了不存在的类</p>';
                } else {
                    echo '<p class="status-ok">✅ 自动加载正常处理不存在的类</p>';
                }
            } catch (Exception $e) {
                echo '<p class="status-error">❌ 自动加载测试出错: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            ?>
        </div>

        <!-- 建议和总结 -->
        <div class="section">
            <h2>💡 环境对比建议</h2>
            <div class="highlight">
                <h4>使用方法：</h4>
                <ol>
                    <li>在测试服务器访问：<code>http://yp.win7e.com/env_test.php?key=test123</code></li>
                    <li>在生产服务器访问：<code>http://your-production-domain/env_test.php?key=test123</code></li>
                    <li>对比两个页面的输出，重点关注：
                        <ul>
                            <li>PHP版本差异</li>
                            <li>扩展安装情况</li>
                            <li>Composer包版本</li>
                            <li>数据库连接状态</li>
                            <li>文件权限设置</li>
                            <li>PhpSpreadsheet相关测试结果</li>
                        </ul>
                    </li>
                </ol>
                
                <h4>安全提醒：</h4>
                <p><strong>⚠️ 测试完成后请立即删除此文件，避免泄露服务器信息！</strong></p>
            </div>
        </div>
    </div>
</body>
</html>