# 🏗️ 库位架信息接口文档

## 📋 接口概述

`racks.php` 提供库位架信息的查询功能，支持按基地、区域类型和状态筛选。

**文件路径**: `/api/racks.php`  
**认证方式**: Bearer Token  
**支持方法**: GET

## 🚀 接口功能

### GET /api/racks.php - 查询库位架信息

获取库位架信息列表，支持基地筛选、区域类型查询、库位状态监控等功能。

#### 请求参数

**请求头**:
```http
Authorization: Bearer your-token-here
```

**查询参数**:

| 参数名 | 类型 | 必填 | 描述 | 示例 |
|--------|------|------|------|------|
| base_id | int | 否 | 基地ID精确查询 | 2 |
| area_type | string | 否 | 区域类型查询 | "storage" |
| status | string | 否 | 库位状态筛选 | "normal" |
| page | int | 否 | 页码，默认1 | 1 |
| page_size | int | 否 | 每页数量，默认20，最大100 | 20 |

**区域类型可选值**:
- `storage`: 库存区
- `processing`: 加工区  
- `scrap`: 报废区
- `temporary`: 临时区

**状态可选值**:
- `normal`: 正常
- `maintenance`: 维护中  
- `full`: 已满

#### 请求示例

```http
GET /api/racks.php?base_id=2&page=1&page_size=20
```

```http
GET /api/racks.php?area_type=storage&status=normal
```

```http
GET /api/racks.php?base_id=1&area_type=processing
```

#### 响应示例

**成功响应 (200)**:
```json
{
    "code": 200,
    "message": "获取成功",
    "timestamp": 1698765432,
    "data": {
        "racks": [
            {
                "id": 1,
                "base_id": 2,
                "base_name": "总部基地",
                "code": "R001",
                "name": "A区货架",
                "area_type": "storage",
                "area_type_name": "库存区",
                "capacity": 100,
                "status": "normal",
                "status_name": "正常",
                "package_count": 5,
                "total_pieces": 500,
                "created_at": "2024-01-15 10:00:00",
                "updated_at": "2024-01-15 10:00:00"
            }
        ],
        "pagination": {
            "page": 1,
            "page_size": 20,
            "total": 50,
            "total_pages": 3
        }
    }
}
```

## 📊 数据结构

### Rack 对象

| 字段名 | 类型 | 描述 | 示例 |
|--------|------|------|------|
| id | int | 库位架唯一标识符 | 1 |
| base_id | int | 所属基地ID | 2 |
| base_name | string | 基地名称 | "总部基地" |
| code | string | 库位架编号 | "R001" |
| name | string | 库位架名称 | "A区货架" |
| area_type | string | 区域类型代码 | "storage" |
| area_type_name | string | 区域类型名称 | "库存区" |
| capacity | int | 容量(可选) | 100 |
| status | string | 状态代码 | "normal" |
| status_name | string | 状态名称 | "正常" |
| package_count | int | 当前包数量 | 5 |
| total_pieces | int | 总片数 | 500 |
| created_at | string | 创建时间 | "2024-01-15 10:00:00" |
| updated_at | string | 更新时间 | "2024-01-15 10:00:00" |

### Pagination 对象

| 字段名 | 类型 | 描述 | 示例 |
|--------|------|------|------|
| page | int | 当前页码 | 1 |
| page_size | int | 每页数量 | 20 |
| total | int | 总记录数 | 50 |
| total_pages | int | 总页数 | 3 |

## 🔧 技术实现

### SQL 查询结构

```sql
SELECT 
    sr.id, sr.base_id, b.name as base_name, sr.code, sr.name,
    sr.area_type,
    CASE sr.area_type 
        WHEN 'storage' THEN '库存区'
        WHEN 'processing' THEN '加工区'
        WHEN 'scrap' THEN '报废区'
        WHEN 'temporary' THEN '临时区'
        ELSE sr.area_type
    END as area_type_name,
    sr.capacity, sr.status,
    CASE sr.status 
        WHEN 'normal' THEN '正常'
        WHEN 'maintenance' THEN '维护中'
        WHEN 'full' THEN '已满'
        ELSE sr.status
    END as status_name,
    sr.created_at, sr.updated_at,
    (SELECT COUNT(*) FROM glass_packages gp 
     WHERE gp.current_rack_id = sr.id AND gp.status = 'in_storage') as package_count,
    (SELECT COALESCE(SUM(gp.pieces), 0) FROM glass_packages gp 
     WHERE gp.current_rack_id = sr.id AND gp.status = 'in_storage') as total_pieces
FROM storage_racks sr
LEFT JOIN bases b ON sr.base_id = b.id
WHERE [查询条件]
ORDER BY sr.base_id, sr.code
LIMIT [分页参数]
```

### 状态映射

**区域类型映射**:
- `storage` → "库存区"
- `processing` → "加工区"
- `scrap` → "报废区"
- `temporary` → "临时区"

**状态映射**:
- `normal` → "正常"
- `maintenance` → "维护中"
- `full` → "已满"

## 💡 使用示例

### JavaScript 示例

```javascript
// 获取库位架列表
async function getRacks(params = {}) {
    const token = localStorage.getItem('token');
    const queryParams = new URLSearchParams(params).toString();
    
    const response = await fetch(`/api/racks.php?${queryParams}`, {
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
const racks = await getRacks({
    base_id: 2,
    area_type: 'storage',
    page: 1,
    page_size: 20
});
console.log('获取到的库位架列表:', racks.racks);
```

### Python 示例

```python
import requests

def get_racks(token, **params):
    headers = {'Authorization': f'Bearer {token}'}
    response = requests.get(
        'http://your-domain.com/api/racks.php',
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
racks = get_racks(token, base_id=2, area_type='storage', page=1, page_size=20)
```

### cURL 示例

```bash
# 获取库位架列表
curl -X GET \
  -H "Authorization: Bearer your-token-here" \
  "http://your-domain.com/api/racks.php?base_id=2&area_type=storage&page=1&page_size=20"
```

## ⚠️ 错误处理

| 错误码 | 错误信息 | 原因 | 解决方案 |
|--------|----------|------|----------|
| 200 | 获取成功 | - | - |
| 401 | 认证失败 | Token无效 | 重新登录获取新Token |
| 405 | 方法不允许 | 使用了非GET方法 | 使用GET方法 |
| 500 | 服务器错误 | 数据库查询失败 | 联系系统管理员 |

## 💡 使用建议

### 1. 查询场景

**库存管理**:
```javascript
// 查询库存区的正常库位架
const storageRacks = await getRacks({
    area_type: 'storage',
    status: 'normal'
});
```

**库位监控**:
```javascript
// 监控库位使用情况
const racks = await getRacks({
    base_id: user.base_id
});

const fullRacks = racks.filter(rack => rack.status === 'full');
const maintenanceRacks = racks.filter(rack => rack.status === 'maintenance');
```

**容量规划**:
```javascript
// 分析库位容量使用率
const racks = await getRacks({ base_id: 2 });
racks.forEach(rack => {
    const usageRate = rack.capacity ? (rack.total_pieces / rack.capacity) * 100 : 0;
    console.log(`${rack.name}: ${usageRate.toFixed(1)}%`);
});
```

### 2. 性能优化

- **分页查询**: 大数据量时使用分页参数
- **条件筛选**: 使用base_id缩小查询范围
- **缓存策略**: 静态库位信息可以适当缓存

### 3. 数据应用

**库位状态监控**:
- 实时显示库位使用情况
- 预警满仓和维护状态
- 统计各区域库位数量

**库存分布分析**:
- 分析各基地库位分布
- 统计各区域库存量
- 容量使用率分析

## 🔒 权限控制

- **基地权限**: 用户只能查询所属基地的库位信息
- **数据隔离**: 自动根据用户权限过滤数据
- **操作限制**: 某些库位状态可能限制操作

## 📈 业务价值

### 1. 库存可视化
- 实时显示库位状态
- 库存分布一目了然
- 容量使用率监控

### 2. 操作指导
- 指导入库操作选择合适库位
- 避免选择维护中或已满库位
- 优化库存分布

### 3. 管理决策
- 库位容量规划依据
- 库存分布优化建议
- 库位维护计划

---

*最后更新: 2025-11-01*  
*版本: 2.0*