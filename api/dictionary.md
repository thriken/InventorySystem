# 字典查询API文档

## 📋 概述

字典查询API提供系统基础字典数据的统一查询接口，包括品牌、厂家、原片类型等基础数据。该API采用统一的认证机制和响应格式，确保数据的一致性和安全性。

## 🔐 认证方式

- **认证机制**：Bearer Token认证
- **认证流程**：与系统其他API保持一致
- **权限要求**：所有认证用户均可访问

## 🚀 API端点

```
GET /api/dictionary.php
```

## 📊 请求参数

### 通用参数
| 参数名 | 类型 | 必填 | 默认值 | 说明 |
|--------|------|------|--------|------|
| `action` | string | 否 | `items` | 操作类型：`categories`, `items`, `glass_types` |
| `page` | int | 否 | 1 | 页码，从1开始 |
| `limit` | int | 否 | 100 | 每页数量，最大1000 |

### 字典项查询参数（action=items）
| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| `category` | string | 否 | 字典分类：`brand`, `manufacturer`, `color` |
| `parent_id` | int | 否 | 父级ID，用于层级字典 |
| `search` | string | 否 | 搜索关键词，支持名称和代码搜索 |

### 原片类型查询参数（action=glass_types）
| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| `search` | string | 否 | 搜索关键词，支持类型名、品牌、厂家搜索 |

## 📝 响应格式

### 成功响应
```json
{
    "code": 200,
    "message": "获取成功",
    "timestamp": 1698765432,
    "data": {
        // 具体数据内容
    }
}
```

### 错误响应
```json
{
    "code": 400,
    "message": "参数错误",
    "timestamp": 1698765432
}
```

## 🔧 使用示例

### 1. 获取所有字典分类

**请求**
```http
GET /api/dictionary.php?action=categories
```

**响应**
```json
{
    "code": 200,
    "message": "获取成功",
    "timestamp": 1698765432,
    "data": {
        "categories": [
            {
                "code": "brand",
                "name": "品牌",
                "description": "玻璃品牌"
            },
            {
                "code": "manufacturer",
                "name": "厂家",
                "description": "生产厂家"
            },
            {
                "code": "color",
                "name": "颜色",
                "description": "玻璃颜色类型"
            }
        ]
    }
}
```

### 2. 查询品牌字典

**请求**
```http
GET /api/dictionary.php?action=items&category=brand
```

**响应**
```json
{
    "code": 200,
    "message": "获取成功",
    "timestamp": 1698765432,
    "data": {
        "items": [
            {
                "id": 1,
                "code": "xinyi",
                "name": "信义",
                "category": "brand",
                "parent_id": null,
                "sort_order": 1,
                "created_at": "2023-01-01 00:00:00"
            },
            {
                "id": 2,
                "code": "taibo",
                "name": "台玻",
                "category": "brand",
                "parent_id": null,
                "sort_order": 2,
                "created_at": "2023-01-01 00:00:00"
            }
        ],
        "pagination": {
            "page": 1,
            "limit": 100,
            "total": 2,
            "pages": 1
        }
    }
}
```

### 3. 搜索原片品牌

**请求**
```http
GET /api/dictionary.php?action=glass_types&search=信义
```

**响应**
```json
{
    "code": 200,
    "message": "获取成功",
    "timestamp": 1698765432,
    "data": {
        "items": [
            {
                "id": 1,
                "type_name": "信义白玻5mm",
                "brand": "信义",
                "manufacturer": "重庆信义",
                "color": "白玻",
                "thickness": "5",
                "silver_layers": "0",
                "specification": "常规规格",
                "created_at": "2023-01-01 00:00:00",
                "updated_at": "2023-01-01 00:00:00"
            },
            {
                "id": 2,
                "type_name": "信义LOWE6mm",
                "brand": "信义",
                "manufacturer": "德阳信义",
                "color": "LOWE",
                "thickness": "6",
                "silver_layers": "1",
                "specification": "节能玻璃",
                "created_at": "2023-01-01 00:00:00",
                "updated_at": "2023-01-01 00:00:00"
            }
        ],
        "pagination": {
            "page": 1,
            "limit": 100,
            "total": 2,
            "pages": 1
        }
    }
}
```

## 💻 代码示例

### JavaScript (前端)

```javascript
// 获取字典分类
async function getDictionaryCategories() {
    const response = await fetch('/api/dictionary.php?action=categories', {
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        }
    });
    
    const result = await response.json();
    if (result.code === 200) {
        return result.data.categories;
    } else {
        throw new Error(result.message);
    }
}

// 获取品牌列表
async function getBrands() {
    const response = await fetch('/api/dictionary.php?action=items&category=brand', {
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        }
    });
    
    const result = await response.json();
    if (result.code === 200) {
        return result.data.items;
    } else {
        throw new Error(result.message);
    }
}

// 搜索原片类型
async function searchGlassTypes(keyword) {
    const response = await fetch(`/api/dictionary.php?action=glass_types&search=${encodeURIComponent(keyword)}`, {
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        }
    });
    
    const result = await response.json();
    if (result.code === 200) {
        return result.data.items;
    } else {
        throw new Error(result.message);
    }
}
```

### Python

```python
import requests

def get_dictionary_categories(token):
    headers = {
        'Authorization': f'Bearer {token}',
        'Content-Type': 'application/json'
    }
    
    response = requests.get(
        'http://your-domain.com/api/dictionary.php?action=categories',
        headers=headers
    )
    
    result = response.json()
    if result['code'] == 200:
        return result['data']['categories']
    else:
        raise Exception(result['message'])

def get_brands(token):
    headers = {
        'Authorization': f'Bearer {token}',
        'Content-Type': 'application/json'
    }
    
    response = requests.get(
        'http://your-domain.com/api/dictionary.php?action=items&category=brand',
        headers=headers
    )
    
    result = response.json()
    if result['code'] == 200:
        return result['data']['items']
    else:
        raise Exception(result['message'])
```

### cURL

```bash
# 获取字典分类
curl -X GET \
  -H "Authorization: Bearer your-token" \
  "http://your-domain.com/api/dictionary.php?action=categories"

# 获取品牌列表
curl -X GET \
  -H "Authorization: Bearer your-token" \
  "http://your-domain.com/api/dictionary.php?action=items&category=brand"

# 搜索原片类型
curl -X GET \
  -H "Authorization: Bearer your-token" \
  "http://your-domain.com/api/dictionary.php?action=glass_types&search=信义"
```

## 🎯 业务应用场景

### 1. 移动端下拉选择
- 扫码入库时选择原片类型
- 表单填写时选择品牌、厂家
- 提供统一的选项数据源

### 2. Web管理端
- 数据筛选条件
- 表单选项
- 报表统计维度

### 3. 第三方集成
- 提供标准化的基础数据
- 便于系统间数据对接
- 统一的数据格式

## 🔧 技术实现细节

### 数据库查询优化
- 使用参数化查询防止SQL注入
- 支持分页查询，避免大数据量传输
- 合理的索引设计

### 缓存策略
- 字典数据变化不频繁，适合缓存
- 可考虑Redis缓存机制
- 缓存失效时间设置

### 错误处理
- 统一的错误响应格式
- 详细的错误信息
- 适当的HTTP状态码

## 📊 性能考虑

### 查询优化
- 默认限制每页100条记录
- 支持搜索过滤，减少数据传输
- 合理的数据库索引

### 缓存建议
- 品牌、厂家等基础字典可缓存24小时
- 原片类型数据可缓存1小时
- 支持缓存清除机制

## 🔒 安全考虑

### 输入验证
- 所有参数进行类型验证
- 搜索关键词长度限制
- SQL注入防护

### 权限控制
- 基于Token的认证机制
- 统一的权限验证流程
- 数据访问权限控制

## 💡 最佳实践

### 1. 前端使用
```javascript
// 缓存字典数据，避免重复请求
let brandCache = null;

async function getBrandsCached() {
    if (!brandCache) {
        brandCache = await getBrands();
    }
    return brandCache;
}
```

### 2. 错误处理
```javascript
// 统一的错误处理
try {
    const brands = await getBrands();
} catch (error) {
    if (error.message.includes('认证')) {
        // 重新登录
        window.location.href = '/login';
    } else {
        // 显示错误提示
        showError(error.message);
    }
}
```

### 3. 性能优化
```javascript
// 批量获取多个字典
async function getMultipleDictionaries() {
    const [brands, manufacturers, colors] = await Promise.all([
        getBrands(),
        getManufacturers(),
        getColors()
    ]);
    
    return { brands, manufacturers, colors };
}
```

## 🚨 错误码说明

| 错误码 | 说明 | 处理建议 |
|--------|------|----------|
| 200 | 成功 | 正常处理 |
| 400 | 参数错误 | 检查请求参数 |
| 401 | 认证失败 | 重新获取Token |
| 404 | 资源不存在 | 检查查询条件 |
| 500 | 服务器错误 | 联系管理员 |

## 📈 扩展性考虑

### 未来扩展
- 支持更多字典分类
- 层级字典支持
- 多语言支持
- 自定义字典字段

### 接口兼容性
- 保持向后兼容
- 新增参数不影响现有功能
- 版本管理机制

---

**文档版本**: 1.0  
**最后更新**: 2024-01-01  
**维护者**: 系统开发团队