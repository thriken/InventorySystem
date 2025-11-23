# 📱 扫描操作接口文档

## 📋 接口概述

`scan.php` 为移动设备提供扫描操作功能，移植自mobile/scan.php的完整功能，支持获取包信息、库位信息判断和执行库存流转操作。

**文件路径**: `/api/scan.php`  
**认证方式**: Bearer Token  
**支持方法**: GET, POST

## 🚀 接口功能

### 1. GET /api/scan.php?action=get_package_info - 获取包信息

根据包号获取原片包的详细信息。

#### 请求参数

**请求头**:
```http
Authorization: Bearer your-token-here
```

**查询参数**:

| 参数名 | 类型 | 必填 | 描述 | 示例 |
|--------|------|------|------|------|
| action | string | 是 | 操作类型，固定为"get_package_info" | "get_package_info" |
| package_code | string | 是 | 包编号 | "YP20240001" |

#### 请求示例

```http
GET /api/scan.php?action=get_package_info&package_code=YP20240001
```

#### 响应示例

**成功响应 (200)**:
```json
{
    "code": 200,
    "message": "获取成功",
    "timestamp": 1698765432,
    "data": {
        "package": {
            "id": 1,
            "package_code": "YP20240001",
            "glass_type": "浮法玻璃",
            "dimensions": "1200×2400mm",
            "quantity": 100,
            "current_rack": "A区货架",
            "status": "库存中"
        }
    }
}
```

### 2. GET /api/scan.php?action=get_target_info - 获取目标库位信息

获取目标库位信息并自动判断操作类型。

#### 请求参数

**请求头**:
```http
Authorization: Bearer your-token-here
```

**查询参数**:

| 参数名 | 类型 | 必填 | 描述 | 示例 |
|--------|------|------|------|------|
| action | string | 是 | 操作类型，固定为"get_target_info" | "get_target_info" |
| target_rack_code | string | 是 | 目标架号 | "R001" |
| current_area_type | string | 否 | 当前区域类型 | "storage" |
| base_name | string | 否 | 基地名称 | "总部基地" |

#### 请求示例

```http
GET /api/scan.php?action=get_target_info&target_rack_code=R001&current_area_type=storage
```

#### 响应示例

**成功响应 (200)**:
```json
{
    "code": 200,
    "message": "获取成功",
    "timestamp": 1698765432,
    "data": {
        "target_rack": {
            "code": "R001",
            "name": "A区货架",
            "area_type": "storage",
            "area_type_name": "库存区",
            "status": "正常"
        },
        "operation_type": "transfer",
        "operation_name": "库内转移"
    }
}
```

### 3. POST /api/scan.php - 执行库存流转操作

执行库存流转操作，支持入库、出库、转移、报废等操作。

#### 请求参数

**请求头**:
```http
Authorization: Bearer your-token-here
Content-Type: application/json
```

**请求体 (JSON)**:

| 参数名 | 类型 | 必填 | 描述 | 示例 |
|--------|------|------|------|------|
| package_code | string | 是 | 包编号 | "YP20240001" |
| target_rack_code | string | 是 | 目标架号 | "R001" |
| quantity | int | 是 | 操作数量 | 100 |
| transaction_type | string | 是 | 交易类型 | "usage_out" |
| scrap_reason | string | 否 | 报废原因(报废操作时必填) | "破损" |
| notes | string | 否 | 备注信息 | "领用出库" |
| all_use | boolean | 否 | 是否全部用完（勾选时数量自动设为0表示完全使用） | true |

**交易类型可选值**:
- `warehouse_in`: 入库
- `usage_out`: 领用出库
- `transfer`: 库内转移
- `scrap`: 报废
- `return`: 退库

#### 请求示例

```http
POST /api/scan.php
Content-Type: application/json
Authorization: Bearer your-token-here

{
    "package_code": "YP20240001",
    "target_rack_code": "R001",
    "quantity": 100,
    "transaction_type": "usage_out",
    "scrap_reason": "",
    "notes": "领用出库",
    "all_use": false
}
```

#### 全部用完示例

```json
{
    "package_code": "YP20240001",
    "target_rack_code": "R001",
    "quantity": 0,
    "transaction_type": "usage_out",
    "scrap_reason": "",
    "notes": "整包领用",
    "all_use": true
}
```

#### 响应示例

**成功响应 (200)**:
```json
{
    "code": 200,
    "message": "操作成功",
    "timestamp": 1698765432,
    "data": {
        "package_code": "YP20240001",
        "target_rack_code": "R001",
        "quantity": 100,
        "transaction_type": "usage_out",
        "operator": "张三"
    }
}
```

## 📊 操作类型说明

### 操作类型判断逻辑

| 当前区域 | 目标区域 | 操作类型 | 操作名称 |
|----------|----------|----------|----------|
| 任何区域 | 库存区 | `purchase_in` | 入库 |
| 库存区 | 加工区 | `usage_out` | 出库到加工 |
| 库存区 | 报废区 | `scrap` | 报废 |
| 加工区 | 库存区 | `return_in` | 退库 |
| 加工区 | 报废区 | `scrap` | 报废 |
| 相同区域 | 相同区域 | `location_adjust` | 库内转移 |

### 交易类型说明

| 交易类型 | 描述 | 适用场景 |
|----------|------|----------|
| `purchase_in` | 入库操作 | 新原片入库 |
| `usage_out` | 领用出库 | 生产领用 |
| `transfer` | 库内转移 | 库位调整 |
| `scrap` | 报废操作 | 原片报废 |
| `return_in` | 退库操作 | 加工剩余退库 |
| `location_adjust` | 库区转移 | 库区调整 |



## 🔧 技术实现

### 操作流程

1. **扫描包条码** → 获取包信息
2. **扫描目标库位** → 判断操作类型
3. **确认操作** → 执行库存流转
4. **记录日志** → 生成操作记录

### 核心函数

```php
// 执行库存流转操作
function executeInventoryTransaction(
    $packageCode,
    $targetRackCode,
    $quantity,
    $transactionType,
    $currentUser,
    $scrapReason = '',
    $notes = ''
) {
    // 验证包信息
    // 验证目标库位
    // 执行库存操作
    // 记录操作日志
    // 返回操作结果
}
```

## 💡 使用示例

### JavaScript 示例

```javascript
// 获取包信息
async function getPackageInfo(packageCode) {
    const token = localStorage.getItem('token');
    const response = await fetch(`/api/scan.php?action=get_package_info&package_code=${packageCode}`, {
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + token
        }
    });
    return await response.json();
}

// 获取目标库位信息
async function getTargetInfo(targetRackCode, currentAreaType) {
    const token = localStorage.getItem('token');
    const params = new URLSearchParams({
        action: 'get_target_info',
        target_rack_code: targetRackCode,
        current_area_type: currentAreaType
    });
    
    const response = await fetch(`/api/scan.php?${params}`, {
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + token
        }
    });
    return await response.json();
}

// 执行库存操作
async function executeTransaction(transactionData) {
    const token = localStorage.getItem('token');
    const response = await fetch('/api/scan.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(transactionData)
    });
    return await response.json();
}

// 全部用完操作示例
async function executeFullUseTransaction(packageCode, targetRackCode) {
    const data = {
        package_code: packageCode,
        target_rack_code: targetRackCode,
        quantity: 0,
        transaction_type: 'usage_out',
        all_use: true,
        notes: '整包领用'
    };
    
    const token = localStorage.getItem('token');
    const response = await fetch('/api/scan.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    });
    return await response.json();
}

// 完整操作流程示例
async function completeScanOperation(packageCode, targetRackCode, quantity, transactionType) {
    try {
        // 1. 获取包信息
        const packageInfo = await getPackageInfo(packageCode);
        
        // 2. 获取目标库位信息
        const currentAreaType = packageInfo.data.package.current_rack_area_type;
        const targetInfo = await getTargetInfo(targetRackCode, currentAreaType);
        
        // 3. 执行操作
        const transactionData = {
            package_code: packageCode,
            target_rack_code: targetRackCode,
            quantity: quantity,
            transaction_type: transactionType,
            notes: '移动端扫描操作'
        };
        
        const result = await executeTransaction(transactionData);
        return result;
        
    } catch (error) {
        console.error('操作失败:', error);
        throw error;
    }
}
```

### 移动端集成示例

```javascript
// 扫码后自动处理
function handleScanResult(scanData) {
    const { packageCode, targetRackCode } = parseScanData(scanData);
    
    // 显示加载状态
    showLoading('正在处理扫描数据...');
    
    completeScanOperation(packageCode, targetRackCode, 0, 'transfer')
        .then(result => {
            hideLoading();
            showSuccess('操作成功');
            updateUI(result.data);
        })
        .catch(error => {
            hideLoading();
            showError('操作失败: ' + error.message);
        });
}
```

## ⚠️ 错误处理

| 错误码 | 错误信息 | 原因 | 解决方案 |
|--------|----------|------|----------|
| 200 | 操作成功 | - | - |
| 400 | 包号不能为空 | 缺少包号参数 | 提供有效的包号 |
| 400 | 目标架号不能为空 | 缺少目标架号 | 提供有效的架号 |
| 400 | 请填写所有必填字段 | 参数不完整 | 检查所有必填字段 |
| 400 | 报废操作必须填写报废原因 | 报废原因为空 | 填写报废原因 |
| 401 | 认证失败 | Token无效 | 重新登录 |
| 404 | 包号不存在 | 包号无效 | 检查包号是否正确 |
| 404 | 目标架号不存在 | 架号无效 | 检查架号是否正确 |
| 500 | 服务器错误 | 操作执行失败 | 联系系统管理员 |

## 🔒 安全与权限

### 操作权限控制
- **用户权限**: 不同角色有不同的操作权限
- **库位权限**: 用户只能操作所属基地的库位
- **操作验证**: 每次操作前验证权限

### 数据完整性
- **事务处理**: 使用数据库事务确保数据一致性
- **操作日志**: 记录所有操作日志便于追溯
- **数据验证**: 操作前验证数据有效性

## 📱 移动端适配

### 扫码集成
- 支持一维码、二维码扫描
- 自动识别包号和库位号
- 错误提示和重试机制

### 用户体验
- 实时反馈操作状态
- 离线操作支持(需同步)
- 操作历史记录

### 性能优化
- 图片压缩和缓存
- 请求合并和批量处理
- 本地数据缓存

## 🔄 业务流程

### 入库流程
1. 扫描新原片包条码
2. 扫描目标库存区库位
3. 确认入库数量和信息
4. 执行入库操作
5. 生成入库记录

### 出库流程
1. 扫描原片包条码
2. 扫描目标区域库位
3. 确认出库数量和类型
4. 执行出库操作
5. 生成出库记录

### 转移流程
1. 扫描原片包条码
2. 扫描目标库位
3. 确认转移信息
4. 执行转移操作
5. 生成转移记录

---

*最后更新: 2025-11-01*  
*版本: 2.0*