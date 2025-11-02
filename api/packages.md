# 📦 原片包信息接口文档

## 📋 接口概述

`packages.php` 提供原片包信息的查询功能，支持多种查询条件和分页功能。

**文件路径**: `/api/packages.php`  
**认证方式**: Bearer Token  
**支持方法**: GET

## 🚀 接口功能

### GET /api/packages.php - 查询原片包信息

获取原片包信息列表，支持包号模糊查询、原片类型筛选、货架位置查询等多种条件。

#### 请求参数

**请求头**:
```http
Authorization: Bearer your-token-here
```

**查询参数**:

| 参数名 | 类型 | 必填 | 描述 | 示例 |
|--------|------|------|------|------|
| package_code | string | 否 | 包编号模糊查询 | "YP2024" |
| glass_type_id | int | 否 | 原片类型ID精确查询 | 1 |
| rack_id | int | 否 | 货架ID精确查询 | 5 |
| base_id | int | 否 | 基地ID精确查询 | 2 |
| status | string | 否 | 状态查询 | "in_storage" |
| page | int | 否 | 页码，默认1 | 1 |
| page_size | int | 否 | 每页数量，默认20，最大100 | 20 |

**状态可选值**:
- `in_storage`: 库存中
- `in_processing`: 加工中  
- `scrapped`: 已报废
- `used_up`: 已用完

#### 请求示例

```http
GET /api/packages.php?package_code=YP2024&page=1&page_size=20
```

```http
GET /api/packages.php?rack_id=5&status=in_storage
```

```http
GET /api/packages.php?base_id=2&page=1&page_size=50
```

#### 响应示例

**成功响应 (200)**:
```json
{
    "code": 200,
    "message": "获取成功",
    "timestamp": 1698765432,
    "data": {
        "packages": [
            {
                "id": 1,
                "package_code": "YP20240001",
                "dimensions": {
                    "width": 1200.0,
                    "height": 2400.0
                },
                "quantity": {
                    "pieces": 100,
                    "quantity": 1000
                },
                "entry_date": "2024-01-15",
                "position_order": 1,
                "glass_type": {
                    "id": 1,
                    "custom_id": "GT001",
                    "name": "浮法玻璃",
                    "short_name": "浮法",
                    "brand": "信义",
                    "manufacturer": "信义玻璃",
                    "color": "透明",
                    "thickness": 5.0,
                    "silver_layers": "单层",
                    "substrate": "普通",
                    "transmittance": "85%"
                },
                "rack_info": {
                    "id": 1,
                    "code": "R001",
                    "name": "A区货架",
                    "area_type": "storage",
                    "base_id": 1,
                    "base_name": "总部基地"
                },
                "status": "in_storage",
                "status_name": "库存中",
                "created_at": "2024-01-15 10:00:00",
                "updated_at": "2024-01-15 10:00:00"
            }
        ],
        "pagination": {
            "page": 1,
            "page_size": 20,
            "total": 150,
            "total_pages": 8
        }
    }
}
```

## 📊 数据结构

### Package 对象

| 字段名 | 类型 | 描述 | 示例 |
|--------|------|------|------|
| id | int | 包的唯一标识符 | 1 |
| package_code | string | 包编号，唯一标识 | "YP20240001" |
| dimensions | object | 尺寸信息 | - |
| ↳ width | float | 宽度(mm) | 1200.0 |
| ↳ height | float | 高度(mm) | 2400.0 |
| quantity | object | 数量信息 | - |
| ↳ pieces | int | 片数 | 100 |
| ↳ quantity | int | 总量 | 1000 |
| entry_date | string | 入库日期 | "2024-01-15" |
| position_order | int | 位置顺序 | 1 |
| glass_type | object | 原片类型信息 | - |
| rack_info | object | 货架位置信息 | - |
| status | string | 状态代码 | "in_storage" |
| status_name | string | 状态名称 | "库存中" |
| created_at | string | 创建时间 | "2024-01-15 10:00:00" |
| updated_at | string | 更新时间 | "2024-01-15 10:00:00" |

### GlassType 对象

| 字段名 | 类型 | 描述 | 示例 |
|--------|------|------|------|
| id | int | 原片类型ID | 1 |
| custom_id | string | 自定义编号 | "GT001" |
| name | string | 类型名称 | "浮法玻璃" |
| short_name | string | 简称 | "浮法" |
| brand | string | 品牌 | "信义" |
| manufacturer | string | 生产厂家 | "信义玻璃" |
| color | string | 颜色 | "透明" |
| thickness | float | 厚度(mm) | 5.0 |
| silver_layers | string | 银层 | "单层" |
| substrate | string | 基材 | "普通" |
| transmittance | string | 透光率 | "85%" |

### RackInfo 对象

| 字段名 | 类型 | 描述 | 示例 |
|--------|------|------|------|
| id | int | 货架ID | 1 |
| code | string | 货架编号 | "R001" |
| name | string | 货架名称 | "A区货架" |
| area_type | string | 区域类型 | "storage" |
| base_id | int | 基地ID | 1 |
| base_name | string | 基地名称 | "总部基地" |

### Pagination 对象

| 字段名 | 类型 | 描述 | 示例 |
|--------|------|------|------|
| page | int | 当前页码 | 1 |
| page_size | int | 每页数量 | 20 |
| total | int | 总记录数 | 150 |
| total_pages | int | 总页数 | 8 |

## 🔧 技术实现

### SQL 查询结构

```sql
SELECT 
    p.id, p.package_code, p.width, p.height, p.pieces, p.quantity,
    p.entry_date, p.position_order, p.status, p.created_at, p.updated_at,
    
    gt.id as glass_type_id, gt.custom_id, gt.name as glass_type_name,
    gt.short_name, gt.brand, gt.manufacturer, gt.color, gt.thickness,
    gt.silver_layers, gt.substrate, gt.transmittance,
    
    r.id as rack_id, r.code as rack_code, r.name as rack_name,
    r.area_type, b.id as base_id, b.name as base_name
    
FROM glass_packages p
LEFT JOIN glass_types gt ON p.glass_type_id = gt.id
LEFT JOIN storage_racks r ON p.current_rack_id = r.id
LEFT JOIN bases b ON r.base_id = b.id
WHERE [查询条件]
ORDER BY p.created_at DESC
LIMIT [分页参数]
```

### 状态映射函数

```php
function getStatusName($status) {
    $statusMap = [
        'in_storage' => '库存中',
        'in_processing' => '加工中',
        'scrapped' => '已报废',
        'used_up' => '已用完'
    ];
    return $statusMap[$status] ?? '未知状态';
}
```

## 💡 使用示例

### JavaScript 示例

```javascript
// 获取原片包列表
async function getPackages(params = {}) {
    const token = localStorage.getItem('token');
    const queryParams = new URLSearchParams(params).toString();
    
    const response = await fetch(`/api/packages.php?${queryParams}`, {
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + token
        }
    });
    
    const data = await response.json();
    if (data.code === 200) {
        return data.data;
    } else {
        throw new Error(data.message);
    }
}

// 使用示例
const packages = await getPackages({
    package_code: 'YP2024',
    page: 1,
    page_size: 20
});
console.log('获取到的包列表:', packages.packages);
```

### Python 示例

```python
import requests

def get_packages(token, **params):
    headers = {'Authorization': f'Bearer {token}'}
    response = requests.get(
        'http://your-domain.com/api/packages.php',
        headers=headers,
        params=params
    )
    data = response.json()
    if data['code'] == 200:
        return data['data']
    else:
        raise Exception(data['message'])

# 使用示例
token = "your-token-here"
packages = get_packages(token, package_code='YP2024', page=1, page_size=20)
```

### cURL 示例

```bash
# 获取原片包列表
curl -X GET \
  -H "Authorization: Bearer your-token-here" \
  "http://your-domain.com/api/packages.php?package_code=YP2024&page=1&page_size=20"
```

## ⚠️ 错误处理

| 错误码 | 错误信息 | 原因 | 解决方案 |
|--------|----------|------|----------|
| 200 | 获取成功 | - | - |
| 401 | 认证失败 | Token无效 | 重新登录获取新Token |
| 405 | 方法不允许 | 使用了非GET方法 | 使用GET方法 |
| 500 | 服务器错误 | 数据库查询失败 | 联系系统管理员 |

## 💡 使用建议

### 1. 查询优化
- **包号查询优先**: 包号是唯一的，建议优先使用包号进行精确查询
- **合理分页**: 数据量较大时，建议设置合理的page_size避免性能问题
- **组合查询**: 支持多个查询条件组合使用，满足复杂查询需求

### 2. 性能考虑
- **分页查询**: 大数据量时务必使用分页参数
- **字段筛选**: 只查询需要的字段，避免不必要的数据传输
- **缓存策略**: 静态数据可以适当缓存

### 3. 数据完整性
- **关联查询**: 自动关联原片类型、货架、基地等信息
- **状态映射**: 自动将状态代码转换为可读名称
- **数据格式化**: 数值字段自动转换为正确的数据类型

## 🔒 权限控制

- **基地权限**: 用户只能查询所属基地的数据
- **角色权限**: 不同角色有不同的数据访问权限
- **数据过滤**: 自动根据用户权限过滤数据

## 📈 性能指标

- **响应时间**: < 500ms (正常网络条件下)
- **并发支持**: 支持多用户并发查询
- **数据量**: 单页最大支持100条记录
- **缓存**: 支持数据库查询缓存

---

*最后更新: 2025-11-01*  
*版本: 2.0*