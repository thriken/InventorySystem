// 区域类型映射（完整版本，包含所有可能的区域类型）
const AREA_TYPES = {
    'storage': '存储区',
    'temporary': '临时区',
    'processing': '加工区',
    'scrap': '报废区'
};

// 包状态映射
const PACKAGE_STATUSES = {
    'in_stock': '在库',
    'out_stock': '出库', 
    'scrapped': '已报废',
    'in_storage': '库存中',
    'in_processing': '加工中',
    'used_up': '已用完'
};

// 交易类型映射
const TRANSACTION_TYPES = {
    'purchase_in': '采购入库',
    'usage_out': '领用出库',
    'return_in': '归还入库',
    'scrap': '报废出库',
    'partial_usage': '部分领用',
    'location_adjust': '基地流转',
    'check_in': '盘盈入库',
    'check_out': '盘亏出库'
};

// 工具函数
function getAreaTypeName(areaType) {
    return AREA_TYPES[areaType] || areaType || '未知';
}

function getStatusName(status) {
    return PACKAGE_STATUSES[status] || status || '未知';
}

function getTransactionTypeName(transactionType) {
    return TRANSACTION_TYPES[transactionType] || transactionType || '未知';
}