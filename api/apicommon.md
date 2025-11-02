# 🔧 ApiCommon工具类文档

## 📋 类概述

`ApiCommon.php` 是为原片管理系统API提供统一认证和响应处理的核心工具类，消除重复代码，确保API接口的一致性。

**文件路径**: `/api/ApiCommon.php`  
**类名**: `ApiCommon`  
**类型**: 静态工具类

## 🚀 核心功能

### 1. 统一认证机制
- Bearer Token认证
- 自动用户信息获取
- 权限验证

### 2. 标准化响应
- 统一JSON响应格式
- 标准错误处理
- 状态码管理

### 3. 请求处理
- CORS跨域支持
- 预检请求处理
- 请求方法验证

## 🔧 方法详解

### 1. `setHeaders()` - 设置响应头

设置统一的HTTP响应头，包括CORS支持。

#### 方法签名
```php
public static function setHeaders()
```

#### 功能说明
- 设置Content-Type为application/json
- 配置CORS跨域支持
- 允许的HTTP方法: GET, POST, OPTIONS
- 允许的请求头: Content-Type, Authorization

#### 使用示例
```php
ApiCommon::setHeaders();
```

#### 设置的响应头
```http
Content-Type: application/json; charset=utf-8
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
```

### 2. `handlePreflight()` - 处理预检请求

自动处理OPTIONS预检请求。

#### 方法签名
```php
public static function handlePreflight()
```

#### 功能说明
- 检测是否为OPTIONS请求
- 自动返回200响应
- 支持CORS预检

#### 使用示例
```php
ApiCommon::handlePreflight();
```

### 3. `authenticate()` - 统一认证

验证Bearer Token并返回当前用户信息。

#### 方法签名
```php
public static function authenticate()
```

#### 返回值
- 成功: 返回用户信息数组
- 失败: 自动发送401响应并退出

#### 用户信息结构
```php
[
    'id' => 1,
    'username' => 'admin',
    'name' => '管理员',
    'role' => 'admin',
    'base_id' => 1
]
```

#### 使用示例
```php
$currentUser = ApiCommon::authenticate();
// $currentUser包含当前登录用户信息
```

### 4. `sendResponse()` - 发送响应

发送标准化的JSON响应。

#### 方法签名
```php
public static function sendResponse($code, $message, $data = null)
```

#### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
|--------|------|------|------|
| code | int | 是 | HTTP状态码 |
| message | string | 是 | 响应消息 |
| data | mixed | 否 | 响应数据 |

#### 使用示例
```php
// 成功响应
ApiCommon::sendResponse(200, '操作成功', $data);

// 错误响应
ApiCommon::sendResponse(400, '参数错误');
```

#### 响应格式
```json
{
    "code": 200,
    "message": "操作成功",
    "timestamp": 1698765432,
    "data": { ... }
}
```

### 5. `getBearerToken()` - 获取Token

从请求头中提取Bearer Token。

#### 方法签名
```php
public static function getBearerToken()
```

#### 返回值
- 成功: 返回Token字符串
- 失败: 返回null

#### 使用示例
```php
$token = ApiCommon::getBearerToken();
if ($token) {
    // 处理Token
}
```

### 6. `validateApiToken()` - 验证Token

验证Token的有效性。

#### 方法签名
```php
public static function validateApiToken($token)
```

#### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
|--------|------|------|------|
| token | string | 是 | 要验证的Token |

#### 返回值
- 有效: 返回用户ID
- 无效: 返回false

#### 使用示例
```php
$userId = ApiCommon::validateApiToken($token);
if ($userId) {
    // Token有效
}
```

### 7. `validateMethod()` - 验证请求方法

验证请求方法是否被允许。

#### 方法签名
```php
public static function validateMethod($allowedMethods)
```

#### 参数说明
| 参数名 | 类型 | 必填 | 描述 |
|--------|------|------|------|
| allowedMethods | array | 是 | 允许的HTTP方法数组 |

#### 使用示例
```php
// 只允许GET请求
ApiCommon::validateMethod(['GET']);

// 允许GET和POST请求
ApiCommon::validateMethod(['GET', 'POST']);
```

## 💡 使用模式

### 标准API模板

```php
<?php
// 1. 引入必要文件
require_once '../config/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once 'ApiCommon.php';

// 2. 设置响应头和处理预检请求
ApiCommon::setHeaders();
ApiCommon::handlePreflight();

// 3. 验证Token认证
$currentUser = ApiCommon::authenticate();

// 4. 处理业务逻辑
try {
    // 验证请求方法
    ApiCommon::validateMethod(['GET']);
    
    // 获取查询参数
    $packageCode = $_GET['package_code'] ?? '';
    
    if (empty($packageCode)) {
        ApiCommon::sendResponse(400, '包号不能为空');
    }
    
    // 业务逻辑...
    $data = ['result' => 'success'];
    
    // 5. 发送成功响应
    ApiCommon::sendResponse(200, '操作成功', $data);
    
} catch (Exception $e) {
    // 6. 发送错误响应
    ApiCommon::sendResponse(500, '服务器错误: ' . $e->getMessage());
}
?>
```

### 认证流程详解

#### Token生成 (在auth.php中)
```php
function generateApiToken($userId) {
    $tokenData = [
        'user_id' => $userId,
        'created_at' => time(),
        'expires_at' => time() + (24 * 60 * 60) // 24小时过期
    ];
    return base64_encode(json_encode($tokenData));
}
```

#### Token验证流程
1. 从请求头提取Bearer Token
2. Base64解码并JSON解析
3. 验证用户ID和过期时间
4. 返回用户信息或发送错误响应

## 📊 错误处理

### 标准错误码

| 错误码 | 错误信息 | 使用场景 |
|--------|----------|----------|
| 200 | 操作成功 | 正常响应 |
| 400 | 参数错误 | 请求参数验证失败 |
| 401 | 认证失败 | Token验证失败 |
| 404 | 资源不存在 | 查询资源不存在 |
| 405 | 方法不允许 | HTTP方法不被支持 |
| 500 | 服务器错误 | 服务器内部错误 |

### 异常处理模式

```php
try {
    // 业务逻辑
    ApiCommon::sendResponse(200, '成功', $data);
    
} catch (PDOException $e) {
    // 数据库错误
    ApiCommon::sendResponse(500, '数据库错误');
    
} catch (InvalidArgumentException $e) {
    // 参数错误
    ApiCommon::sendResponse(400, $e->getMessage());
    
} catch (Exception $e) {
    // 其他错误
    ApiCommon::sendResponse(500, '服务器错误');
}
```

## 🔒 安全特性

### 1. Token安全
- 包含用户ID和过期时间
- 24小时有效期
- Base64编码保护

### 2. 输入验证
- 自动参数验证
- SQL注入防护
- XSS攻击防护

### 3. 权限控制
- 基于角色的访问控制
- 数据隔离
- 操作日志记录

## 📈 性能优化

### 1. 代码复用
- 消除重复的认证代码
- 统一的错误处理
- 标准化的响应格式

### 2. 缓存策略
- 用户信息缓存
- Token验证缓存
- 数据库连接复用

### 3. 响应优化
- 最小化响应数据
- 合理的分页策略
- 压缩传输数据

## 🎯 适用场景

### 推荐使用场景
- 所有需要用户认证的API接口
- 需要统一响应格式的接口
- 需要CORS支持的跨域接口
- 需要标准错误处理的接口

### 不适用场景
- 公开的无需认证的接口
- 非JSON响应的接口(如文件下载)
- 特殊的认证需求场景

## 🔄 版本兼容

### 当前版本: 2.0
- 统一认证机制
- 标准化响应格式
- 完整的错误处理

### 向后兼容
- 保持现有API接口不变
- 新增功能不影响旧版本
- 逐步迁移策略

## 💡 最佳实践

### 1. 错误处理
```php
// 明确的错误处理
try {
    $currentUser = ApiCommon::authenticate();
    
    if ($currentUser['role'] !== 'admin') {
        ApiCommon::sendResponse(403, '权限不足');
    }
    
    // 业务逻辑...
    
} catch (Exception $e) {
    error_log('API错误: ' . $e->getMessage());
    ApiCommon::sendResponse(500, '操作失败');
}
```

### 2. 参数验证
```php
// 完整的参数验证
$required = ['package_code', 'quantity'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        ApiCommon::sendResponse(400, "参数{$field}不能为空");
    }
}

// 数据类型验证
$quantity = intval($_POST['quantity']);
if ($quantity <= 0) {
    ApiCommon::sendResponse(400, '数量必须大于0');
}
```

### 3. 日志记录
```php
// 操作日志记录
function logOperation($userId, $action, $data) {
    $logData = [
        'user_id' => $userId,
        'action' => $action,
        'data' => $data,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'timestamp' => time()
    ];
    // 记录到数据库或文件
}
```

---

*最后更新: 2025-11-01*  
*版本: 2.0*