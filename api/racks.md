# 🏗️ 库位架信息接口文档

## 📋 接口概述

`racks.php` 提供库位架信息的查询功能，支持按基地、区域类型和状态筛选。

**文件路径**: `/api/racks.php`  
**认证方式**: Bearer Token  
**支持方法**: GET

## 🚀 接口功能

### 1. GET /api/racks.php - 查询库位架信息列表

获取库位架信息列表，支持基地筛选、区域类型查询、库位状态监控等功能。

#### 请求参数

**请求头**:
```http
Authorization: Bearer your-token-here
```

**查询参数**:

| 参数名 | 类型 | 必填 | 描述 | 示例 |
|--------|------|------|------|------|
| id | int | 否 | 库位架ID精确查询 | 1 |
| base_id | int | 否 | 基地ID精确查询 | 2 |
| rack_name | string | 否 | 库位架名称模糊查询（需配合base_id） | "A区" |
| area_type | string | 否 | 区域类型查询 | "storage" |
| status | string | 否 | 库位状态筛选 | "normal" |
| page | int | 否 | 页码，默认1 | 1 |
| page_size | int | 否 | 每页数量，默认20，最大100 | 20 |

### 2. GET /api/racks.php?action=get_rack_id - 模糊查找库位ID

根据库位编码或名称模糊查找库位ID，支持智能匹配格式。

### 3. GET /api/racks.php?action=get_rack_packages - 查询库位所有原片信息

根据库位ID获取该库位的所有原片信息，包括原片类型和详细库存统计。

#### 请求参数

**请求头**:
```http
Authorization: Bearer your-token-here
```

**查询参数**:

|| 参数名 | 类型 | 必填 | 描述 | 示例 |
||--------|------|------|------|------|
|| action | string | 是 | 固定值：get_rack_packages | get_rack_packages |
|| rack_id | int | 否 | 库位ID精确查询 | 15 |
|| rack_code | string | 否 | 库位编码精确查询 | "XF-N-8A" |

**注意**：`rack_id` 和 `rack_code` 必须提供其中一个。如果都提供，优先使用 `rack_id`。

#### 请求参数

**请求头**:
```http
Authorization: Bearer your-token-here
```

**查询参数**:

| 参数名 | 类型 | 必填 | 描述 | 示例 |
|--------|------|------|------|------|
| action | string | 是 | 固定值：get_rack_id | get_rack_id |
| rack_code | string | 否 | 库位编码（支持精确和模糊匹配） | "8A" |
| rack_name | string | 否 | 库位名称（支持模糊匹配） | "新丰" |
| base_id | int | 否 | 基地ID（不提供则使用用户默认基地） | 2 |

**注意**：`rack_code` 和 `rack_name` 必须提供其中一个。

**智能匹配示例**：
- 输入 "8A" 可以匹配 "8A"、"XF-N-8A"、"A-8A" 等格式
- 输入 "XF" 可以匹配所有包含 "XF" 的库位编码

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

**查询库位架列表**:
```http
GET /api/racks.php?base_id=2&page=1&page_size=20
```

```http
GET /api/racks.php?area_type=storage&status=normal
```

```http
GET /api/racks.php?base_id=1&area_type=processing
```

```http
GET /api/racks.php?rack_name=A区&base_id=1
```

**模糊查找库位ID**:
```http
GET /api/racks.php?action=get_rack_id&rack_code=8A
```

```http
GET /api/racks.php?action=get_rack_id&rack_code=XF-N-8A
```

```http
GET /api/racks.php?action=get_rack_id&rack_name=新丰&base_id=2
```

**查询库位原片信息**:
```http
GET /api/racks.php?action=get_rack_packages&rack_id=15
```

```http
GET /api/racks.php?action=get_rack_packages&rack_code=XF-N-8A
```

#### 响应示例

**库位列表查询成功响应 (200)**:
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

**模糊查找 - 单个匹配结果 (200)**:
```json
{
    "code": 200,
    "message": "库位查找成功",
    "timestamp": 1698765432,
    "data": {
        "rack_id": 15,
        "rack_code": "XF-N-8A",
        "rack_name": "新丰8A库位",
        "base_id": 2,
        "area_type": "storage",
        "status": "normal",
        "match_type": "exact"
    }
}
```

**模糊查找 - 多个匹配结果 (200)**:
```json
{
    "code": 200,
    "message": "找到多个匹配的库位，请选择",
    "timestamp": 1698765432,
    "data": {
        "matches": [
            {
                "rack_id": 15,
                "rack_code": "XF-N-8A",
                "rack_name": "新丰8A库位",
                "base_id": 2,
                "area_type": "storage",
                "status": "normal"
            },
            {
                "rack_id": 28,
                "rack_code": "A-8A",
                "rack_name": "A区8A库位",
                "base_id": 1,
                "area_type": "storage",
                "status": "normal"
            }
        ],
        "total_matches": 2,
        "match_type": "multiple"
    }
}
```

**未找到匹配库位 (404)**:
```json
{
    "code": 404,
    "message": "未找到匹配的库位",
    "timestamp": 1698765432
}
```

**库位原片信息查询成功响应 (200)**:
```json
{
    "code": 200,
    "message": "查询成功",
    "timestamp": 1698765432,
    "data": {
        "rack_info": {
            "id": 15,
            "base_id": 2,
            "base_name": "分部基地",
            "code": "XF-N-8A",
            "name": "新丰8A库位",
            "area_type": "storage",
            "area_type_name": "库存区",
            "capacity": 100,
            "status": "normal",
            "status_name": "正常"
        },
        "summary": {
            "total_packages": 5,
            "total_pieces": 500,
            "total_quantity": 5000,
            "glass_type_count": 3
        },
        "glass_types": [
            {
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
                "packages": [
                    {
                        "id": 101,
                        "package_code": "YP20240501",
                        "width": 1200.0,
                        "height": 2400.0,
                        "pieces": 100,
                        "quantity": 1000,
                        "entry_date": "2024-05-15",
                        "position_order": 1,
                        "status": "in_storage",
                        "status_name": "库存中",
                        "created_at": "2024-05-15 10:00:00",
                        "updated_at": "2024-05-15 10:00:00"
                    },
                    {
                        "id": 102,
                        "package_code": "YP20240502",
                        "width": 1200.0,
                        "height": 2400.0,
                        "pieces": 80,
                        "quantity": 800,
                        "entry_date": "2024-05-16",
                        "position_order": 2,
                        "status": "in_storage",
                        "status_name": "库存中",
                        "created_at": "2024-05-16 10:00:00",
                        "updated_at": "2024-05-16 10:00:00"
                    }
                ],
                "summary": {
                    "package_count": 2,
                    "pieces": 180,
                    "quantity": 1800
                }
            },
            {
                "glass_type": {
                    "id": 2,
                    "custom_id": "GT002",
                    "name": "钢化玻璃",
                    "short_name": "钢化",
                    "brand": "南玻",
                    "manufacturer": "南玻集团",
                    "color": "透明",
                    "thickness": 6.0,
                    "silver_layers": "双层",
                    "substrate": "强化",
                    "transmittance": "88%"
                },
                "packages": [
                    {
                        "id": 103,
                        "package_code": "YP20240503",
                        "width": 1000.0,
                        "height": 2000.0,
                        "pieces": 120,
                        "quantity": 1200,
                        "entry_date": "2024-05-17",
                        "position_order": 1,
                        "status": "in_storage",
                        "status_name": "库存中",
                        "created_at": "2024-05-17 10:00:00",
                        "updated_at": "2024-05-17 10:00:00"
                    }
                ],
                "summary": {
                    "package_count": 1,
                    "pieces": 120,
                    "quantity": 1200
                }
            }
        ]
    }
}
```

**库位不存在 (404)**:
```json
{
    "code": 404,
    "message": "库位不存在",
    "timestamp": 1698765432
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

### 1. 库位列表查询SQL结构

```sql
SELECT 
    sr.id,
    sr.base_id,
    b.name as base_name,
    sr.code,
    sr.name,
    sr.area_type,
    CASE sr.area_type 
        WHEN 'storage' THEN '库存区'
        WHEN 'processing' THEN '加工区'
        WHEN 'scrap' THEN '报废区'
        WHEN 'temporary' THEN '临时区'
        ELSE sr.area_type
    END as area_type_name,
    sr.capacity,
    sr.status,
    CASE sr.status 
        WHEN 'normal' THEN '正常'
        WHEN 'maintenance' THEN '维护中'
        WHEN 'full' THEN '已满'
        ELSE sr.status
    END as status_name,
    sr.created_at,
    sr.updated_at,
    (SELECT COUNT(*) FROM glass_packages gp 
     WHERE gp.current_rack_id = sr.id AND gp.status = 'in_storage') as package_count,
    (SELECT COALESCE(SUM(gp.pieces), 0) FROM glass_packages gp 
     WHERE gp.current_rack_id = sr.id AND gp.status = 'in_storage') as total_pieces
FROM storage_racks sr
LEFT JOIN bases b ON sr.base_id = b.id
WHERE [查询条件]
ORDER BY sr.base_id, sr.code
LIMIT ? OFFSET ?
```

### 2. 模糊查找SQL结构

```sql
-- 库位编码模糊查找
SELECT id, code, name, base_id, area_type, status 
FROM storage_racks 
WHERE (code LIKE ? OR code LIKE ?) AND base_id = ?
LIMIT 10

-- 库位名称模糊查找  
SELECT id, code, name, base_id, area_type, status 
FROM storage_racks 
WHERE name LIKE ? AND base_id = ?
LIMIT 10
```

### 3. 模糊匹配算法

**编码匹配逻辑**：
- 精确匹配：`code = ?`（完全匹配输入值）
- 模糊匹配：`code LIKE ?`（包含输入值）
  
这样设计支持以下场景：
- 输入 "8A" 可以匹配 "8A"、"XF-N-8A"、"A-8A" 等
- 输入 "XF" 可以匹配 "XF-N-8A"、"XF-S-9B" 等

### 4. 库位原片信息查询SQL结构

```sql
-- 库位原片信息查询
SELECT 
    sr.id, sr.base_id, sr.code, sr.name, sr.area_type,
    sr.capacity, sr.status, sr.created_at, sr.updated_at,
    b.name as base_name,
    
    gt.id as glass_type_id, gt.custom_id, gt.name as glass_type_name,
    gt.short_name, gt.brand, gt.manufacturer, gt.color, gt.thickness,
    gt.silver_layers, gt.substrate, gt.transmittance,
    
    p.id as package_id, p.package_code, p.width, p.height,
    p.pieces, p.quantity, p.entry_date, p.position_order,
    p.status, p.created_at as package_created_at, p.updated_at as package_updated_at
    
FROM storage_racks sr
LEFT JOIN bases b ON sr.base_id = b.id
LEFT JOIN glass_packages p ON sr.id = p.current_rack_id AND p.status = 'in_storage'
LEFT JOIN glass_types gt ON p.glass_type_id = gt.id
WHERE sr.id = ? OR sr.code = ?
ORDER BY gt.custom_id, p.position_order, p.created_at
```

### 5. 响应处理逻辑

**模糊查找逻辑**:
```php
if (count($results) === 0) {
    // 未找到匹配的库位
    ApiCommon::sendResponse(404, '未找到匹配的库位');
} elseif (count($results) === 1) {
    // 单个匹配结果，直接返回库位信息
    ApiCommon::sendResponse(200, '库位查找成功', $singleResult);
} else {
    // 多个匹配结果，返回列表供用户选择
    ApiCommon::sendResponse(200, '找到多个匹配的库位，请选择', $multipleResults);
}
```

**库位原片信息处理逻辑**:
```php
// 数据分组处理
$glassTypes = [];
$totalPackages = 0;
$totalPieces = 0;
$totalQuantity = 0;

foreach ($packages as $package) {
    $glassTypeId = $package['glass_type_id'];
    if (!isset($glassTypes[$glassTypeId])) {
        $glassTypes[$glassTypeId] = [
            'glass_type' => $glassTypeData,
            'packages' => [],
            'summary' => ['package_count' => 0, 'pieces' => 0, 'quantity' => 0]
        ];
    }
    
    $glassTypes[$glassTypeId]['packages'][] = $packageData;
    $glassTypes[$glassTypeId]['summary']['package_count']++;
    $glassTypes[$glassTypeId]['summary']['pieces'] += $package['pieces'];
    $glassTypes[$glassTypeId]['summary']['quantity'] += $package['quantity'];
    
    $totalPackages++;
    $totalPieces += $package['pieces'];
    $totalQuantity += $package['quantity'];
}
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

**库位列表查询**:
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

**模糊查找库位**:
```javascript
// 模糊查找库位ID
async function findRackId(rackCode, baseId = null) {
    const token = localStorage.getItem('token');
    const params = new URLSearchParams({
        action: 'get_rack_id',
        rack_code: rackCode
    });
    
    if (baseId) params.append('base_id', baseId);
    
    const response = await fetch(`/api/racks.php?${params}`, {
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + token
        }
    });
    
    const data = await response.json();
    if (data.code === 200) {
        if (data.data.match_type === 'exact') {
            // 单个匹配结果
            return data.data.rack_id;
        } else {
            // 多个匹配结果，需要用户选择
            return data.data.matches;
        }
    } else {
        throw new Error(data.message);
    }
}
```

**查询库位原片信息**:
```javascript
// 查询库位所有原片信息
async function getRackPackages(rackId = null, rackCode = null) {
    const token = localStorage.getItem('token');
    const params = new URLSearchParams({
        action: 'get_rack_packages'
    });
    
    if (rackId) params.append('rack_id', rackId);
    if (rackCode) params.append('rack_code', rackCode);
    
    const response = await fetch(`/api/racks.php?${params}`, {
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
try {
    const result = await findRackId('8A');
    if (Array.isArray(result)) {
        console.log('找到多个匹配的库位:', result);
        // 显示选择界面让用户选择
    } else {
        console.log('找到库位ID:', result);
        // 直接使用找到的库位ID
    }
} catch (error) {
    console.error('查找库位失败:', error.message);
}
```

### Python 示例

**库位列表查询**:
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

**模糊查找库位**:
```python
def find_rack_id(token, rack_code=None, rack_name=None, base_id=None):
    headers = {'Authorization': f'Bearer {token}'}
    params = {'action': 'get_rack_id'}
    
    if rack_code:
        params['rack_code'] = rack_code
    if rack_name:
        params['rack_name'] = rack_name
    if base_id:
        params['base_id'] = base_id
    
    response = requests.get(
        'http://your-domain.com/api/racks.php',
        headers=headers,
        params=params
    )
    
    data = response.json()
    if data['code'] == 200:
        result_data = data['data']
        if result_data['match_type'] == 'exact':
            return result_data['rack_id']
        else:
            return result_data['matches']
    else:
        raise Exception(data['message'])

# 使用示例
try:
    result = find_rack_id(token, rack_code='8A')
    if isinstance(result, list):
        print(f"找到多个匹配库位: {result}")
        # 处理多选择逻辑
    else:
        print(f"找到库位ID: {result}")
        # 使用找到的库位ID
except Exception as e:
    print(f"查找库位失败: {e}")
```

**查询库位原片信息**:
```python
def get_rack_packages(token, rack_id=None, rack_code=None):
    headers = {'Authorization': f'Bearer {token}'}
    params = {'action': 'get_rack_packages'}
    
    if rack_id:
        params['rack_id'] = rack_id
    if rack_code:
        params['rack_code'] = rack_code
    
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
try:
    # 按库位ID查询
    rack_info = get_rack_packages(token, rack_id=15)
    print(f"库位: {rack_info['rack_info']['name']}")
    print(f"总包数: {rack_info['summary']['total_packages']}")
    print(f"原片类型数: {rack_info['summary']['glass_type_count']}")
    
    # 遍历各原片类型
    for glass_type_data in rack_info['glass_types']:
        glass_type = glass_type_data['glass_type']
        summary = glass_type_data['summary']
        print(f"- {glass_type['name']}: {summary['package_count']}包, {summary['pieces']}片")
        
    # 按库位编码查询
    rack_info2 = get_rack_packages(token, rack_code='XF-N-8A')
    print(f"查询结果2: {rack_info2}")
    
except Exception as e:
    print(f"查询库位原片信息失败: {e}")
```

### cURL 示例

**库位列表查询**:
```bash
# 获取库位架列表
curl -X GET \
  -H "Authorization: Bearer your-token-here" \
  "http://your-domain.com/api/racks.php?base_id=2&area_type=storage&page=1&page_size=20"
```

**模糊查找库位**:
```bash
# 模糊查找库位 - 单个匹配
curl -X GET \
  -H "Authorization: Bearer your-token-here" \
  "http://your-domain.com/api/racks.php?action=get_rack_id&rack_code=8A"

# 模糊查找库位 - 多个匹配
curl -X GET \
  -H "Authorization: Bearer your-token-here" \
  "http://your-domain.com/api/racks.php?action=get_rack_id&rack_code=XF&base_id=2"

# 按名称模糊查找
curl -X GET \
  -H "Authorization: Bearer your-token-here" \
  "http://your-domain.com/api/racks.php?action=get_rack_id&rack_name=新丰&base_id=2"

# 查询库位原片信息
curl -X GET \
  -H "Authorization: Bearer your-token-here" \
  "http://your-domain.com/api/racks.php?action=get_rack_packages&rack_id=15"

curl -X GET \
  -H "Authorization: Bearer your-token-here" \
  "http://your-domain.com/api/racks.php?action=get_rack_packages&rack_code=XF-N-8A"
```

## ⚠️ 错误处理

| 错误码 | 错误信息 | 原因 | 解决方案 |
|--------|----------|------|----------|
| 200 | 请求成功 | - | - |
| 400 | 请求参数错误 | 缺少必需参数或参数不合法 | 检查请求参数 |
| 401 | 认证失败 | Token无效 | 重新登录获取新Token |
| 404 | 未找到匹配的库位 | 模糊查找无结果 | 检查输入的库位编码或名称 |
| 405 | 方法不允许 | 使用了非GET方法 | 使用GET方法 |
| 500 | 服务器错误 | 数据库查询失败 | 联系系统管理员 |

### 常见错误场景

**参数错误示例**:
```json
{
    "code": 400,
    "message": "必须提供rack_code或rack_name参数",
    "timestamp": 1698765432
}
```

```json
{
    "code": 400,
    "message": "使用库位架名称查询时必须提供base_id参数",
    "timestamp": 1698765432
}
```

**认证失败示例**:
```json
{
    "code": 401,
    "message": "认证失败",
    "timestamp": 1698765432
}
```

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

### 2. 模糊查找场景

**快速定位库位**:
```javascript
// 用户输入库位编码进行快速定位
const userInput = '8A'; // 用户输入
const rackResult = await findRackId(userInput);

if (typeof rackResult === 'number') {
    // 找到唯一库位，直接使用
    console.log(`找到库位ID: ${rackResult}`);
    await moveToRack(rackResult);
} else if (Array.isArray(rackResult)) {
    // 多个匹配，让用户选择
    showRackSelectionDialog(rackResult);
}
```

**兼容不同编码格式**:
```javascript
// 支持不同用户的输入习惯
const inputFormats = [
    '8A',           // 简短格式
    'XF-N-8A',      // 完整格式  
    '新丰8A',       // 名称格式
    'XF8A'          // 无分隔符格式
];

// 统一使用模糊查找接口
for (const format of inputFormats) {
    try {
        const result = await findRackId(format);
        console.log(`${format} -> ${JSON.stringify(result)}`);
    } catch (error) {
        console.log(`${format} 未找到: ${error.message}`);
    }
}
```

**移动端扫码处理**:
```javascript
// 扫码枪识别到库位码后快速查找
async function handleScanResult(scannedCode) {
    try {
        const result = await findRackId(scannedCode);
        
        if (typeof result === 'number') {
            // 精确匹配，直接操作
            await showRackDetails(result);
        } else if (result.length === 1) {
            // 只有一个匹配项，自动选择
            await showRackDetails(result[0].rack_id);
        } else {
            // 多个匹配，显示选择列表
            showRackSelection(result);
        }
    } catch (error) {
        showMessage(`未找到库位: ${scannedCode}`, 'error');
    }
}
```

### 3. 性能优化

- **分页查询**: 大数据量时使用分页参数
- **条件筛选**: 使用base_id缩小查询范围
- **缓存策略**: 静态库位信息可以适当缓存
- **模糊查找限制**: 模糊查找限制返回10个结果，避免数据过多

### 4. 数据应用

**库位状态监控**:
- 实时显示库位使用情况
- 预警满仓和维护状态
- 统计各区域库位数量

**库存分布分析**:
- 分析各基地库位分布
- 统计各区域库存量
- 容量使用率分析

**智能推荐库位**:
```javascript
// 根据用户输入智能推荐库位
async function recommendRacks(userInput, baseId) {
    try {
        const matches = await findRackId(userInput, baseId);
        
        if (typeof matches === 'number') {
            return [matches]; // 单个结果转为数组格式
        }
        
        // 多个结果按优先级排序
        return matches.sort((a, b) => {
            // 优先推荐正常状态的库位
            if (a.status === 'normal' && b.status !== 'normal') return -1;
            if (a.status !== 'normal' && b.status === 'normal') return 1;
            
            // 其次按编码相似度排序
            const aSimilarity = calculateSimilarity(userInput, a.rack_code);
            const bSimilarity = calculateSimilarity(userInput, b.rack_code);
            return bSimilarity - aSimilarity;
        });
    } catch (error) {
        return [];
    }
}
```

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

## 🆕 更新日志

### v4.0 (2025-12-21)
- ✨ **新增库位原片查询功能**: 支持查询库位所有原片信息
- 📊 **详细统计信息**: 提供总包数、总片数、原片类型数等统计
- 🗂️ **按类型分组**: 原片信息按类型分组，清晰展示库存分布
- 📱 **灵活查询方式**: 支持按库位ID或编码查询
- 🔗 **完整关联信息**: 包含原片类型详细信息和包信息

### v3.0 (2025-12-17)
- ✨ **新增模糊查找功能**: 支持根据库位编码或名称进行模糊匹配
- 🎯 **智能匹配算法**: 支持 `8A` 匹配 `XF-N-8A` 等不同格式
- 🔍 **多结果处理**: 单个匹配直接返回ID，多个匹配返回选择列表
- 📱 **移动端优化**: 专为扫码枪和移动端输入场景设计
- 🛡️ **统一响应格式**: 使用 `ApiCommon` 类统一所有接口响应

### v2.0 (2025-11-01)
- 📊 **分页功能**: 支持分页查询库位列表
- 🔧 **状态管理**: 完善的库位状态查询功能
- 📈 **统计信息**: 包含包数量和总片数统计
- 🏭 **基地筛选**: 支持按基地ID筛选库位

### v1.0 (初始版本)
- 🏗️ **基础功能**: 库位架信息查询
- 🗂️ **区域类型**: 支持 storage、processing、scrap、temporary 区域分类

---

*最后更新: 2025-12-21*  
*版本: 4.0*  
*维护团队: 原片管理系统开发组*