# 🚀 原片管理系统 API 文档

## 📋 概述

原片管理系统提供完整的 RESTful API 接口服务，支持玻璃库存管理的全流程操作。系统采用 Bearer Token 认证机制，支持跨域请求(CORS)，为移动端和 Web 前端提供统一的数据接口。

## 🌟 主要特性

- **🔐 安全认证**：基于 Bearer Token 的无状态认证
- **📱 移动友好**：专为安卓 APP 优化的 API 接口
- **🔄 实时同步**：支持实时数据同步和状态更新
- **🛡️ 权限控制**：基于角色的细粒度权限管理
- **📊 完整覆盖**：涵盖入库、出库、盘点、查询等全流程
- **🎯 RESTful 设计**：遵循 RESTful API 设计原则
- **🌐 跨域支持**：完整的 CORS 配置

## 🔐 认证机制

### Token 认证流程
1. **用户登录**：通过认证接口获取访问令牌
2. **令牌使用**：在请求头中添加 `Authorization: Bearer {token}`
3. **自动过期**：令牌 24 小时后自动失效
4. **无状态验证**：每次请求都会验证令牌有效性

### 统一响应格式
```json
{
  "code": 200,
  "message": "操作成功",
  "timestamp": 1701300000,
  "data": { ... }
}
```

## 📊 API 接口总览

| 接口模块 | 文档 | 端点数量 | 主要功能 |
|---------|------|----------|----------|
| 🔐 **认证系统** | [auth.md](auth.md) | 2 | 用户登录、令牌验证、权限检查 |
| 📱 **扫码操作** | [scan.md](scan.md) | 3 | 移动扫码、包信息查询、库存操作 |
| 📦 **包管理** | [packages.md](packages.md) | 4 | 包信息管理、状态查询、批量操作 |
| 🏗️ **库位管理** | [racks.md](racks.md) | 3 | 库位查询、容量统计、位置管理 |
| 📚 **数据字典** | [dictionary.md](dictionary.md) | 3 | 基础数据查询、系统配置 |
| 📊 **盘点系统** | [inventory_check_api.md](inventory_check_api.md) | 8 | 盘点任务、扫码盘点、数据同步 |
| 🔧 **工具类** | [apicommon.md](apicommon.md) | 5 | 通用工具、认证辅助、响应处理 |

## 🚀 快速开始

### 1. 获取访问令牌
```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"password123"}' \
  https://your-domain.com/api/auth.php
```

**响应示例：**
```json
{
  "code": 200,
  "message": "登录成功",
  "data": {
    "token": "eyJ1c2VyX2lkIjoxLCJjcmVhdGVkX2F0IjoxNzAxMzAwMDAwfQ==",
    "user": {
      "id": 1,
      "username": "admin",
      "name": "系统管理员",
      "role": "admin"
    }
  }
}
```

### 2. 使用令牌调用 API
```bash
curl -X GET \
  -H "Authorization: Bearer your-token-here" \
  -H "Content-Type: application/json" \
  "https://your-domain.com/api/packages.php?package_code=G20241101001"
```

## 📋 错误处理

### 标准错误码
| 状态码 | 含义 | 处理建议 |
|--------|------|----------|
| 200 | 请求成功 | 正常处理返回数据 |
| 400 | 参数错误 | 检查请求参数格式和完整性 |
| 401 | 认证失败 | 重新登录获取新令牌 |
| 403 | 权限不足 | 检查用户角色权限 |
| 404 | 资源不存在 | 验证资源 ID 或查询条件 |
| 405 | 方法不允许 | 使用正确的 HTTP 方法 |
| 500 | 服务器错误 | 联系系统管理员 |

## 🔧 开发工具

### 🌐 在线文档
- **HTML 文档**：访问 `index.html` 查看完整的交互式文档
- **API 测试**：使用 `test.html` 进行在线接口测试
- **示例代码**：每个接口都包含 JavaScript 和 PHP 调用示例

### 📱 移动端集成
```javascript
// API 请求封装示例
class ApiClient {
    constructor(baseURL, token) {
        this.baseURL = baseURL;
        this.token = token;
    }
    
    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;
        const config = {
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.token}`,
                ...options.headers
            },
            ...options
        };
        
        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            if (data.code !== 200) {
                throw new Error(data.message);
            }
            
            return data.data;
        } catch (error) {
            console.error('API 请求失败:', error);
            throw error;
        }
    }
}

// 使用示例
const api = new ApiClient('/api', localStorage.getItem('token'));
const packages = await api.request('packages.php?action=list');
```

## 📁 项目结构

```
api/
├── 📄 文档文件
│   ├── README.md                   # 主文档（本文件）
│   ├── inventory_check_api.md       # 盘点 API 文档
│   ├── auth.md                      # 认证接口文档
│   ├── scan.md                      # 扫码接口文档
│   ├── packages.md                 # 包管理接口文档
│   ├── racks.md                     # 库位接口文档
│   ├── dictionary.md                # 字典接口文档
│   └── apicommon.md                 # 工具类文档
│
├── 🌐 HTML 文档
│   ├── index.html                   # 文档首页
│   ├── inventory_check.html         # 盘点 API HTML 文档
│   ├── auth.html                    # 认证接口 HTML 文档
│   ├── scan.html                    # 扫码接口 HTML 文档
│   ├── packages.html                # 包管理接口 HTML 文档
│   ├── racks.html                   # 库位接口 HTML 文档
│   ├── dictionary.html              # 字典接口 HTML 文档
│   ├── apicommon.html               # 工具类 HTML 文档
│   └── test.html                    # API 测试工具
│
├── ⚙️ API 实现
│   ├── auth.php                     # 认证接口
│   ├── scan.php                     # 扫码接口
│   ├── packages.php                 # 包管理接口
│   ├── racks.php                    # 库位接口
│   ├── dictionary.php               # 字典接口
│   ├── inventory_check.php           # 盘点接口
│   └── ApiCommon.php                # 通用工具类
│
└── 🔧 配置文件
    └── .htaccess                    # Apache 配置
```

## 💡 最佳实践

### 🔒 安全建议
1. **HTTPS 必需**：生产环境必须使用 HTTPS 传输
2. **令牌保护**：客户端安全存储访问令牌
3. **参数验证**：前后端双重参数验证
4. **权限检查**：实现完整的权限验证机制

### 📈 性能优化
1. **数据缓存**：合理缓存字典等静态数据
2. **批量操作**：尽量使用批量接口减少请求次数
3. **分页查询**：大数据量查询使用分页参数
4. **按需加载**：只请求必要的数据字段

### 🛠️ 开发建议
1. **错误处理**：实现完整的异常处理机制
2. **日志记录**：记录关键操作和错误信息
3. **版本管理**：API 版本控制和兼容性处理
4. **文档维护**：及时更新 API 文档

## 🔄 版本历史

| 版本 | 日期 | 更新内容 |
|------|------|----------|
| v2.0 | 2024-11-30 | 新增盘点 API，统一文档风格，优化接口设计 |
| v1.5 | 2024-10-15 | 扫码接口优化，增加批量操作支持 |
| v1.2 | 2024-08-20 | 新增 CORS 支持，优化移动端体验 |
| v1.0 | 2024-06-01 | 初始版本，基础 CRUD 功能 |

## 📞 技术支持

如有技术问题，请按以下顺序处理：

1. **查阅文档**：首先查看相关接口的详细文档
2. **检查参数**：确认请求参数格式和完整性
3. **查看日志**：检查服务器端错误日志
4. **联系支持**：联系系统管理员获取帮助

---

## 📄 许可证

本文档和 API 接口遵循项目内部使用许可协议。

---

*最后更新: 2024-11-30*  
*当前版本: v2.0*  
*维护者: 原片管理系统开发团队*