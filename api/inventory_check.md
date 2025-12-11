# 库存盘点 API 文档

## 概述

`inventory_check.php` 提供了完整的库存盘点功能 API，支持盘点任务管理、数据录入、同步查询等功能。

## 基础信息

- **文件路径**: `api/inventory_check.php`
- **Content-Type**: `application/json; charset=utf-8`
- **认证方式**: Bearer Token
- **权限要求**: admin（只读）、manager（完整权限）

## 请求头要求

```http
Content-Type: application/json
Authorization: Bearer {token}
```

## API 端点

### 1. 获取任务列表
**GET/POST** `?action=list`

获取当前用户的盘点任务列表。

**响应示例:**
```json
{
  "code": 200,
  "message": "获取成功",
  "data": [
    {
      "id": 1,
      "task_name": "2024年11月全盘",
      "task_type": "full",
      "status": "in_progress",
      "total_packages": 150,
      "checked_packages": 75,
      "difference_count": 2,
      "completion_rate": 50.0,
      "status_text": "进行中",
      "task_type_text": "全盘",
      "created_at": "2024-11-01 09:00:00",
      "started_at": "2024-11-01 10:00:00"
    }
  ]
}
```

### 2. 获取任务详情
**GET** `?action=get&task_id={id}`

获取指定任务的详细信息和待盘点包列表。

**响应示例:**
```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "task": {
      "id": 1,
      "task_name": "2024年11月全盘",
      "status": "in_progress"
    },
    "packages": [
      {
        "cache_id": 123,
        "package_code": "G20241101001",
        "package_id": 456,
        "system_quantity": 50,
        "check_quantity": 0,
        "glass_name": "6mm白玻"
      }
    ],
    "total_packages": 150,
    "checked_packages": 75,
    "completion_rate": 50.0
  }
}
```

### 3. 扫码盘点单个包
**POST** `?action=scan`

通过扫码方式盘点单个包，支持库位调整。

**请求参数:**
```json
{
  "task_id": 1,
  "package_code": "G20241101001",
  "check_quantity": 48,
  "rack_id": 123
}
```

**参数说明:**
- `task_id` (int, 必需): 盘点任务ID
- `package_code` (string, 必需): 包号
- `check_quantity` (int, 必需): 盘点数量
- `rack_id` (int, 可选): 库位ID，如果提供且与原库位不同，则同步更新包的库位

**响应示例:**
```json
{
  "code": 200,
  "message": "盘点成功",
  "data": {
    "package_code": "G20241101001",
    "glass_name": "6mm白玻",
    "system_quantity": 50,
    "check_quantity": 48,
    "difference": -2,
    "checked_packages": 76,
    "rack_updates": 1
  }
}
```

### 4. 批量扫码盘点
**POST** `?action=batch_scan`

批量处理多个包的盘点数据，支持库位调整。

**请求参数:**
```json
{
  "task_id": 1,
  "sync_racks": true,
  "batch_data": [
    {
      "package_code": "G20241101001",
      "check_quantity": 48,
      "rack_id": 123
    },
    {
      "package_code": "G20241101002",
      "check_quantity": 50
    }
  ]
}
```

**参数说明:**
- `task_id` (int, 必需): 盘点任务ID
- `sync_racks` (boolean, 可选): 是否同步库位，默认为false
- `batch_data` (array, 必需): 批量数据数组
  - `package_code` (string, 必需): 包号
  - `check_quantity` (int, 必需): 盘点数量
  - `rack_id` (int, 可选): 库位ID，仅在sync_racks为true时有效

**响应示例:**
```json
{
  "code": 200,
  "message": "批量盘点完成",
  "data": {
    "success_count": 2,
    "total_count": 2,
    "errors": [],
    "checked_packages": 77
  }
}
```

### 5. 同步盘点数据
**GET** `?action=sync&task_id={id}&last_sync={timestamp}`

获取指定时间后的盘点数据更新。

**响应示例:**
```json
{
  "code": 200,
  "message": "同步成功",
  "data": {
    "data": [
      {
        "package_code": "G20241101001",
        "check_quantity": 48,
        "system_quantity": 50,
        "difference": -2,
        "check_time": "2024-11-30 10:30:00",
        "operator_name": "张三",
        "glass_name": "6mm白玻"
      }
    ],
    "stats": {
      "checked_packages": 77,
      "total_packages": 150
    },
    "completion_rate": 51.33
  }
}
```

### 6. 获取包信息
**GET** `?action=get_package_info&package_code={code}`

获取指定包的详细信息。

**响应示例:**
```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "id": 456,
    "package_code": "G20241101001",
    "pieces": 50,
    "width": 2440,
    "height": 1830,
    "entry_date": "2024-11-01",
    "glass_name": "6mm白玻",
    "thickness": 6,
    "brand": "信义",
    "color": "白玻",
    "rack_code": "A01-01",
    "base_name": "总基地"
  }
}
```

### 7. 手动提交盘点数据
**POST** `?action=submit_check`

手动录入盘点数据，支持库位调整。

**请求参数:**
```json
{
  "task_id": 1,
  "package_code": "G20241101001",
  "check_quantity": 48,
  "rack_id": 123,
  "notes": "包装破损2片"
}
```

**参数说明:**
- `task_id` (int, 必需): 盘点任务ID
- `package_code` (string, 必需): 包号
- `check_quantity` (int, 必需): 盘点数量
- `rack_id` (int, 可选): 库位ID，如果提供且与原库位不同，则同步更新包的库位
- `notes` (string, 可选): 备注

**响应示例:**
```json
{
  "code": 200,
  "message": "提交成功",
  "data": {
    "package_code": "G20241101001",
    "system_quantity": 50,
    "check_quantity": 48,
    "difference": -2,
    "checked_packages": 76,
    "rack_updates": 1
  }
}
```

### 8. 获取回滚记录数量
**POST** `?action=get_rollback_count`

获取指定任务期间自动回滚的记录数量。

**请求参数:**
```json
{
  "task_id": 1
}
```

**响应示例:**
```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "count": 5,
    "task_id": 1
  }
}
```

## 错误响应格式

所有错误响应都遵循统一格式：

```json
{
  "code": 400,
  "message": "错误描述",
  "timestamp": 1701300000
}
```

### 常见错误码

- `400`: 请求参数错误
- `401`: 未认证或令牌无效
- `403`: 权限不足
- `404`: 资源不存在
- `500`: 服务器内部错误

## 权限说明

### 角色权限

| 角色 | 权限 | 说明 |
|------|------|------|
| admin | 只读 | 只能查看盘点任务，不能创建和修改 |
| manager | 完全权限 | 可以创建、管理自己基地的盘点任务 |
| operator | 无权限 | 无法使用盘点功能 |
| viewer | 无权限 | 无法使用盘点功能 |

### 基地权限限制

- **admin**: 可以访问所有基地的任务
- **manager**: 只能访问自己分配基地的任务

## 数据库表结构

### 主要表

1. **inventory_check_tasks** - 盘点任务表
2. **inventory_check_cache** - 盘点缓存表
3. **glass_packages** - 玻璃包表
4. **glass_types** - 玻璃类型表
5. **storage_racks** - 库位架表
6. **bases** - 基地表

## 事务处理

涉及数据修改的操作（如盘点、批量盘点）都使用事务确保数据一致性：

```php
beginTransaction();
try {
    // 执行操作
    commitTransaction();
} catch (Exception $e) {
    rollbackTransaction();
}
```

## 使用示例

### JavaScript 调用示例

```javascript
// 获取任务列表
const response = await fetch('/api/inventory_check.php?action=list', {
    headers: {
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/json'
    }
});

const result = await response.json();
if (result.code === 200) {
    console.log('任务列表:', result.data);
}
```

### PHP 调用示例

```php
// 扫码盘点
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'http://example.com/api/inventory_check.php?action=scan',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'task_id' => 1,
        'package_code' => 'G20241101001',
        'check_quantity' => 48
    ]),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ],
    CURLOPT_RETURNTRANSFER => true
]);

$result = curl_exec($ch);
$data = json_decode($result, true);
```

## 库位调整功能

### 功能说明

盘点API支持在盘点过程中同步调整包的库位，实现盘库和库位校准的一体化操作。

### 使用规则

1. **可选功能**: 库位调整是可选的，不提供`rack_id`时仅执行盘点操作
2. **条件判断**: 只有当新库位与原库位不同时才执行库位更新
3. **权限验证**: 确保用户有权限操作目标库位
4. **审计记录**: 库位变更会记录在盘点备注中，便于追踪

### 响应字段

- `rack_updates`: 返回成功更新的库位数量，可用于前端提示

### 应用场景

- **盘点发现库位错误**: 盘点时发现包实际位置与系统记录不符
- **库位整理**: 盘点同时进行库位规范化整理
- **数据校准**: 结合实物盘点修正系统库位数据

## 注意事项

1. **认证**: 所有 API 调用都需要有效的 Bearer Token
2. **权限**: 确保用户具有相应的角色权限
3. **数据验证**: 提交前验证必要参数的完整性
4. **错误处理**: 正确处理各种错误响应
5. **时间格式**: 时间戳使用 ISO 8601 格式
6. **字符编码**: 统一使用 UTF-8 编码
7. **库位权限**: 库位调整需要确保目标库位属于用户权限范围内

## 版本历史

- **v1.1** (2024-12-11): 新增库位调整功能
  - 所有盘点接口支持库位同步更新
  - 新增库位权限验证和审计记录
  - 完善库位调整的响应统计

- **v1.0** (2024-11-30): 初始版本，支持基本盘点功能
- 支持扫码盘点、批量盘点、数据同步等功能
- 完善的权限控制和错误处理机制

---

*本文档随 API 更新而维护，最后更新时间: 2024-12-11*