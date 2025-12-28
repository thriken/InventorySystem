# 操作记录查询API文档

## 基本信息

- **API名称**: 操作记录查询API
- **版本**: v1.0.0
- **描述**: 查询玻璃仓储管理系统中的操作记录，支持权限控制和时间范围限制
- **接口地址**: `/api/history.php`
- **请求方法**: `GET`
- **内容类型**: `application/json`

## 认证方式

```http
Authorization: Bearer {token}
```

所有请求都需要在请求头中包含有效的Bearer Token认证令牌。

## 请求参数

| 参数名 | 类型 | 必填 | 格式 | 描述 | 示例 |
|--------|------|------|------|------|------|
| start_date | string | 否 | YYYY-MM-DD | 开始日期，如果不提供则根据用户角色自动设置默认值 | 2024-12-01 |
| end_date | string | 否 | YYYY-MM-DD | 结束日期，如果不提供则根据用户角色自动设置默认值 | 2024-12-27 |
| package_code | string | 否 | - | 包号筛选，支持模糊查询 | GP202412 |
| operation_type | string | 否 | 枚举值 | 操作类型筛选 | purchase_in |
| base_id | integer | 否 | - | 基地ID筛选，仅管理员可使用此参数 | 1 |
| page | integer | 否 | >=1 | 页码，默认为1 | 1 |
| page_size | integer | 否 | 1-100 | 每页记录数，默认为20 | 20 |

### 操作类型枚举

| 值 | 中文名称 | 描述 |
|----|----------|------|
| purchase_in | 采购入库 | 新玻璃包入库 |
| usage_out | 领用出库 | 整包领用到加工区 |
| partial_usage | 部分领用 | 从包中领取部分片数 |
| return_in | 归还入库 | 加工完成后归还剩余片数 |
| scrap | 报废 | 玻璃报废处理 |
| check_in | 盘盈 | 盘点发现多出片数 |
| check_out | 盘亏 | 盘点发现缺少片数 |

## 权限控制

### 角色时间限制

| 角色 | 时间限制 | 默认范围 | 可查所有基地 | 基地限制 |
|------|----------|----------|-------------|----------|
| admin | 无限制 | 最近7天 | ✅ | 无限制 |
| manager | 最多365天 | 最近7天 | ❌ | 只能查询所属基地 |
| viewer | 最多7天 | 最近7天 | ❌ | 只能查询所属基地 |
| operator | 最多7天 | 最近7天 | ❌ | 只能查询所属基地 |

### 权限说明

- **管理员(admin)**: 可查询所有基地的所有记录，时间无限制，可指定基地筛选
- **库管(manager)**: 可查询最多365天内的本基地记录
- **查看者(viewer)**: 最多查询7天内的本基地记录
- **操作员(operator)**: 最多查询7天内的本基地记录

## 响应格式

### 成功响应

```json
{
  "code": 200,
  "message": "查询成功",
  "timestamp": 1735276800,
  "data": {
    "records": [
      {
        "id": 1,
        "record_no": "CG202412270001",
        "operation_type": "purchase_in",
        "package_id": 160,
        "glass_type_id": 67,
        "base_id": 2,
        "operation_quantity": 35,
        "before_quantity": 35,
        "after_quantity": 35,
        "from_rack_id": null,
        "to_rack_id": 140,
        "unit_area": 8.93,
        "total_area": 312.56,
        "operator_id": 5,
        "operation_date": "2025-12-28",
        "operation_time": "09:58:53",
        "status": "completed",
        "scrap_reason": null,
        "notes": null,
        "related_record_id": null,
        "created_at": "2025-12-28 09:58:53",
        "updated_at": "2025-12-28 09:58:53",
        "package_code": "NT251226001",
        "glass_name": "4mm台玻白玻",
        "thickness": 4.00,
        "color": "白玻",
        "brand": "台玻",
        "base_name": "新丰基地",
        "operator_name": "新丰库管",
        "from_rack_code": null,
        "to_rack_code": "XF-N-8A"
      }
    ],
    "pagination": {
      "page": 1,
      "page_size": 20,
      "total": 1,
      "total_pages": 1
    },
    "filters": {
      "operation_types": [
        {"value": "purchase_in", "label": "采购入库"},
        {"value": "usage_out", "label": "领用出库"},
        {"value": "partial_usage", "label": "部分领用"},
        {"value": "return_in", "label": "归还入库"},
        {"value": "scrap", "label": "报废"},
        {"value": "check_in", "label": "盘盈"},
        {"value": "check_out", "label": "盘亏"}
      ],
      "bases": [
        {"value": 1, "label": "总部基地"},
        {"value": 2, "label": "分部基地"}
      ],
      "current_time_range": {
        "start_date": "2024-12-01",
        "end_date": "2024-12-27"
      },
      "user_permissions": {
        "role": "manager",
        "can_query_all_bases": false,
        "max_days_allowed": 365
      }
    }
  }
}
```

### 错误响应

| 错误码 | 错误信息 | 描述 |
|--------|----------|------|
| 400 | 参数错误 | 请求参数格式或值不正确 |
| 401 | 认证失败 | Token无效或已过期 |
| 403 | 权限不足 | 用户权限不够执行该操作 |
| 405 | 方法不允许 | HTTP方法不正确 |
| 500 | 服务器错误 | 内部服务器错误 |

## 数据结构

### 记录字段说明

| 字段名 | 类型 | 描述 |
|--------|------|------|
| id | integer | 记录唯一标识 |
| record_no | string | 记录单号 |
| operation_type | string | 操作类型代码 |
| package_id | integer | 包ID |
| glass_type_id | integer | 玻璃类型ID |
| base_id | integer | 基地ID |
| operation_quantity | integer | 操作数量 |
| before_quantity | integer | 操作前数量 |
| after_quantity | integer | 操作后数量 |
| from_rack_id | integer | 来源库位ID |
| to_rack_id | integer | 目标库位ID |
| unit_area | float | 单片面积(平方米) |
| total_area | float | 总面积(平方米) |
| operator_id | integer | 操作员ID |
| operation_date | string | 操作日期(YYYY-MM-DD) |
| operation_time | string | 操作时间(HH:mm:ss) |
| status | string | 操作状态 |
| scrap_reason | string | 报废原因 |
| notes | string | 备注信息 |
| related_record_id | integer | 关联记录ID |
| created_at | string | 创建时间 |
| updated_at | string | 更新时间 |
| package_code | string | 包号 |
| glass_name | string | 玻璃名称 |
| thickness | float | 厚度(mm) |
| color | string | 颜色 |
| brand | string | 品牌 |
| base_name | string | 基地名称 |
| operator_name | string | 操作员姓名 |
| from_rack_code | string | 来源库位编码 |
| to_rack_code | string | 目标库位编码 |

## 使用示例

### cURL示例

#### 基本查询
```bash
curl -X GET 'http://your-domain/api/history.php' \
     -H 'Authorization: Bearer your-token'
```

#### 指定时间范围
```bash
curl -X GET 'http://your-domain/api/history.php?start_date=2024-12-01&end_date=2024-12-27' \
     -H 'Authorization: Bearer your-token'
```

#### 包号筛选
```bash
curl -X GET 'http://your-domain/api/history.php?package_code=GP202412' \
     -H 'Authorization: Bearer your-token'
```

#### 操作类型筛选
```bash
curl -X GET 'http://your-domain/api/history.php?operation_type=purchase_in' \
     -H 'Authorization: Bearer your-token'
```

#### 分页查询
```bash
curl -X GET 'http://your-domain/api/history.php?page=2&page_size=10' \
     -H 'Authorization: Bearer your-token'
```

#### 管理员指定基地查询
```bash
curl -X GET 'http://your-domain/api/history.php?base_id=1' \
     -H 'Authorization: Bearer admin-token'
```

### JavaScript示例

```javascript
async function getOperationHistory(params = {}) {
  const queryString = new URLSearchParams(params).toString();
  const response = await fetch(`/api/history.php?${queryString}`, {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${localStorage.getItem('token')}`,
      'Content-Type': 'application/json'
    }
  });
  
  if (!response.ok) {
    const error = await response.json();
    throw new Error(error.message || '查询失败');
  }
  
  const result = await response.json();
  return result.data;
}

// 使用示例
try {
  const history = await getOperationHistory({
    start_date: '2024-12-01',
    end_date: '2024-12-27',
    operation_type: 'purchase_in',
    page: 1,
    page_size: 20
  });
  
  console.log('查询结果:', history.records);
  console.log('分页信息:', history.pagination);
} catch (error) {
  console.error('查询失败:', error.message);
}
```

## 注意事项

1. **时间格式**: 所有日期参数必须使用 `YYYY-MM-DD` 格式
2. **工作日间隔**: 工作日时间范围为当日8:00至次日8:00
3. **分页限制**: 每页最大100条记录
4. **模糊查询**: 包号查询支持模糊匹配，不区分大小写
5. **权限验证**: 每次请求都会验证用户权限和时间范围限制
6. **时区**: 所有时间均为北京时间

## 更新日志

### v1.0.0 (2024-12-27)

- ✅ 初始版本发布
- ✅ 支持基本查询和筛选功能
- ✅ 实现基于角色的权限控制
- ✅ 支持时间范围限制
- ✅ 提供分页和排序功能
- ✅ 完整的API文档

---

**技术支持**: 如有技术问题，请联系开发团队