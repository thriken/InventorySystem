# 🔐 用户认证接口文档

## 📋 接口概述

`auth.php` 提供用户认证和令牌管理功能，支持用户登录、Token验证和登录状态检查。

**文件路径**: `/api/auth.php`  
**认证方式**: Bearer Token  
**支持方法**: GET, POST

## 🚀 接口功能

### 1. POST /api/auth.php - 用户登录

用户登录接口，验证用户身份并返回认证令牌。

#### 请求参数

**请求头**:
```http
Content-Type: application/json
```

**请求体 (JSON)**:
```json
{
    "username": "0030025",
    "password": "123456"
}
```

| 参数名 | 类型 | 必填 | 描述 | 示例 |
|--------|------|------|------|------|
| username | string | 是 | 用户名 | "0030025" |
| password | string | 是 | 密码 | "123456" |

#### 响应示例

**成功响应 (200)**:
```json
{
    "code": 200,
    "message": "登录成功",
    "timestamp": 1698758400,
    "data": {
        "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "user": {
            "id": 1,
            "username": "0030025",
            "name": "张三",
            "role": "operator",
            "base_id": 2
        }
    }
}
```

**错误响应 (401)**:
```json
{
    "code": 401,
    "message": "用户名或密码错误",
    "timestamp": 1698758400
}
```

### 2. GET /api/auth.php - 检查登录状态

验证令牌有效性并返回当前用户信息。

#### 请求参数

**请求头**:
```http
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

#### 响应示例

**成功响应 (200)**:
```json
{
    "code": 200,
    "message": "用户已登录",
    "timestamp": 1698758400,
    "data": {
        "user": {
            "id": 1,
            "username": "0030025",
            "name": "张三",
            "role": "operator",
            "base_id": 2
        }
    }
}
```

**错误响应 (401)**:
```json
{
    "code": 401,
    "message": "令牌无效或已过期",
    "timestamp": 1698758400
}
```

## 🔧 技术实现

### Token 生成机制

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

### Token 验证流程

1. 从请求头提取Bearer Token
2. Base64解码并JSON解析
3. 验证用户ID和过期时间
4. 返回用户信息或发送错误响应

## 📊 数据结构

### User 对象

| 字段名 | 类型 | 描述 | 示例 |
|--------|------|------|------|
| id | int | 用户唯一标识 | 1 |
| username | string | 用户名 | "0030025" |
| name | string | 真实姓名 | "张三" |
| role | string | 用户角色 | "operator" |
| base_id | int | 所属基地ID | 2 |

### Token 信息

| 字段名 | 类型 | 描述 |
|--------|------|------|
| token | string | 认证令牌 |
| expires_at | int | 过期时间戳 |

## 💡 使用示例

### JavaScript 示例

```javascript
// 用户登录
async function login(username, password) {
    const response = await fetch('/api/auth.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ username, password })
    });
    
    const data = await response.json();
    if (data.code === 200) {
        localStorage.setItem('token', data.data.token);
        return data.data.user;
    } else {
        throw new Error(data.message);
    }
}

// 检查登录状态
async function checkLogin() {
    const token = localStorage.getItem('token');
    if (!token) return null;
    
    const response = await fetch('/api/auth.php', {
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + token
        }
    });
    
    const data = await response.json();
    if (data.code === 200) {
        return data.data.user;
    } else {
        localStorage.removeItem('token');
        return null;
    }
}
```

### Python 示例

```python
import requests

def login(username, password):
    response = requests.post(
        'http://your-domain.com/api/auth.php',
        json={'username': username, 'password': password}
    )
    data = response.json()
    if data['code'] == 200:
        return data['data']
    else:
        raise Exception(data['message'])

def check_login(token):
    headers = {'Authorization': f'Bearer {token}'}
    response = requests.get(
        'http://your-domain.com/api/auth.php',
        headers=headers
    )
    data = response.json()
    if data['code'] == 200:
        return data['data']['user']
    else:
        return None
```

### cURL 示例

```bash
# 用户登录
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"username":"0030025","password":"123456"}' \
  http://your-domain.com/api/auth.php

# 检查登录状态
curl -X GET \
  -H "Authorization: Bearer your-token-here" \
  http://your-domain.com/api/auth.php
```

## ⚠️ 错误处理

| 错误码 | 错误信息 | 原因 | 解决方案 |
|--------|----------|------|----------|
| 200 | 登录成功 | - | - |
| 400 | 缺少必要参数 | 请求参数不完整 | 检查username和password参数 |
| 400 | 用户名和密码不能为空 | 参数值为空 | 填写有效的用户名和密码 |
| 401 | 用户名或密码错误 | 认证失败 | 检查用户名密码是否正确 |
| 401 | 未提供认证令牌 | 缺少Authorization头 | 在请求头中添加Bearer Token |
| 401 | 令牌无效或已过期 | Token验证失败 | 重新登录获取新Token |
| 404 | 用户不存在 | 用户ID无效 | 检查用户状态 |
| 405 | 方法不允许 | 使用了不支持的HTTP方法 | 使用GET或POST方法 |

## 🔒 安全说明

1. **密码安全**: 使用password_hash加密存储
2. **Token安全**: Token包含用户ID和过期时间
3. **HTTPS推荐**: 生产环境建议使用HTTPS
4. **定期更换**: Token有效期为24小时

## 📈 性能优化

1. **Token缓存**: 客户端缓存Token避免重复登录
2. **错误重试**: 实现合理的错误重试机制
3. **连接复用**: 使用HTTP连接复用减少开销

---

*最后更新: 2025-11-01*  
*版本: 2.0*