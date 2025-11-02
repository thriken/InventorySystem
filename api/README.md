# 原片管理系统 API 文档

## 📋 概述

原片管理系统为安卓APP提供完整的RESTful API接口服务，采用Bearer Token认证机制，支持跨域请求(CORS)。

## 🔐 认证机制

### Token 认证流程
1. 用户通过 `/api/auth.php` POST请求登录获取Token
2. 在后续请求头中添加：`Authorization: Bearer {token}`
3. Token有效期为24小时

### 统一响应格式
```json
{
    "code": 200,
    "message": "操作成功",
    "timestamp": 1698765432,
    "data": { ... }
}
```

## 📊 API 接口列表

| 接口名称 | 文件路径 | HTTP方法 | 主要功能 |
|---------|---------|----------|----------|
| [用户认证接口](auth.md) | `/api/auth.php` | GET/POST | 用户登录、Token验证 |
| [原片包信息接口](packages.md) | `/api/packages.php` | GET | 原片包信息查询 |
| [库位架信息接口](racks.md) | `/api/racks.php` | GET | 库位架信息查询 |
| [扫描操作接口](scan.md) | `/api/scan.php` | GET/POST | 移动设备扫描操作 |
| [通用工具类](apicommon.md) | `/api/ApiCommon.php` | - | API通用工具函数 |

## 🚀 快速开始

### 1. 用户登录
```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"username":"0030025","password":"123456"}' \
  http://your-domain.com/api/auth.php
```

### 2. 查询包信息
```bash
curl -X GET \
  -H "Authorization: Bearer your-token" \
  "http://your-domain.com/api/packages.php?package_code=L250831002"
```

## 🔧 开发工具

### API测试工具
访问 `/api/test_package_query.html` 进行完整的API测试流程。

### 错误码说明

| 错误码 | 描述 | 解决方案 |
|--------|------|----------|
| 200 | 请求成功 | - |
| 400 | 请求参数错误 | 检查请求参数 |
| 401 | 认证失败 | 重新登录获取新Token |
| 404 | 资源不存在 | 检查查询条件 |
| 405 | 请求方法不允许 | 使用正确的HTTP方法 |
| 500 | 服务器内部错误 | 联系系统管理员 |

## 📁 文件结构

```
api/
├── README.md                 # 主文档
├── auth.md                   # 认证接口文档
├── packages.md              # 包信息接口文档
├── racks.md                 # 库位架接口文档
├── scan.md                  # 扫描操作接口文档
├── apicommon.md             # 工具类文档
├── auth.php                 # 认证接口实现
├── packages.php             # 包信息接口实现
├── racks.php                # 库位架接口实现
├── scan.php                 # 扫描操作接口实现
├── ApiCommon.php            # 通用工具类
├── index.html               # API文档首页
├── auth.html                # 认证接口HTML文档
├── packages.html            # 包信息接口HTML文档
├── racks.html               # 库位架接口HTML文档
├── scan.html                # 扫描操作接口HTML文档
├── apicommon.html           # 工具类HTML文档
```

## 💡 使用建议

1. **Token管理**: 每次会话获取新Token，避免使用过期Token
2. **错误处理**: 实现完整的错误处理机制
3. **数据缓存**: 合理缓存静态数据减少API调用
4. **权限验证**: 前端实现权限检查，避免无效请求
5. **分页查询**: 大数据量时使用分页参数

## 📞 技术支持

如有问题请联系系统管理员或查看详细的接口文档。

---

*最后更新: 2025-11-01*  
*版本: 2.0*