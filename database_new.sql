CREATE DATABASE IF NOT EXISTS glass_inventory DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE glass_inventory;

-- 基地表
CREATE TABLE bases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL COMMENT '基地名称',
    code VARCHAR(20) NOT NULL COMMENT '基地编码',
    address VARCHAR(255) COMMENT '基地地址',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) COMMENT='基地信息表';

-- 库位架表
CREATE TABLE storage_racks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    base_id INT NOT NULL COMMENT '所属基地ID',
    code VARCHAR(50) NOT NULL COMMENT '库位架编码',
    name VARCHAR(100) COMMENT '库位架名称',
    area_type ENUM('storage', 'processing', 'scrap', 'temporary') NOT NULL DEFAULT 'storage' COMMENT '区域类型：库存区、加工区、报废区、临时区',
    capacity INT COMMENT '容量',
    status ENUM('normal', 'maintenance', 'full') DEFAULT 'normal' COMMENT '状态：正常、维护中、已满',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (base_id) REFERENCES bases(id) ON DELETE CASCADE
) COMMENT='库位架信息表';

-- 原片基础信息表
CREATE TABLE glass_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    custom_id VARCHAR(50) NOT NULL UNIQUE COMMENT '原片ID（自定义，唯一，采购使用）',
    name VARCHAR(100) NOT NULL COMMENT '原片名称（完整名称）',
    short_name VARCHAR(50) NOT NULL COMMENT '原片简称（如：5白、4白、5白南玻）',
    finance_name VARCHAR(100) COMMENT '财务核算名',
    product_series VARCHAR(100) COMMENT '原片商色系（如：5lowe XETB0160）',
    brand VARCHAR(50) COMMENT '原片品牌（如：信义、台玻、金晶、旗滨）',
    manufacturer VARCHAR(100) COMMENT '原片生产商（如：重庆信义、德阳信义、成都台玻）',
    color VARCHAR(50) COMMENT '原片颜色（如：白玻、玉沙、LOWE、超白等）',
    thickness DECIMAL(10,2) COMMENT '原片厚度(mm)（如：4、5、6、8、10、12、15、19）',
    silver_layers ENUM('单银', '双银', '三银', '无银','') COMMENT '银层类型',
    substrate ENUM('普白', '超白','') COMMENT '基片类型',
    transmittance ENUM('高透', '中透', '低透','') COMMENT '透光性',
    status TINYINT(1) DEFAULT 1 COMMENT '状态：1启用，0禁用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_custom_id (custom_id),
    INDEX idx_short_name (short_name),
    INDEX idx_brand (brand),
    INDEX idx_manufacturer (manufacturer),
    INDEX idx_color (color)
) COMMENT='原片基础信息表';
ALTER TABLE glass_types 
ADD INDEX idx_silver_layers (silver_layers),
ADD INDEX idx_substrate (substrate),
ADD INDEX idx_transmittance (transmittance);

-- 原片包表
CREATE TABLE glass_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_code VARCHAR(100) NOT NULL COMMENT '包号/二维码',
    glass_type_id INT NOT NULL COMMENT '原片类型ID',
    width DECIMAL(10,2) COMMENT '宽度(mm)',
    height DECIMAL(10,2) COMMENT '高度(mm)',
    pieces INT NOT NULL DEFAULT 0 COMMENT '实际片数（库存操作基准）',
    quantity INT NOT NULL DEFAULT 0 COMMENT '默认包装数量（参考值）',
    entry_date DATE COMMENT '入库日期',
    initial_rack_id INT COMMENT '起始库区ID',
    current_rack_id INT COMMENT '当前库位架ID',
    position_order INT DEFAULT 1 COMMENT '在架中的位置顺序(1-x)',
    status ENUM( 'in_storage', 'in_processing', 'scrapped', 'used_up') DEFAULT 'in_storage' COMMENT '包状态：in_storage=库存中，in_processing=加工中，scrapped=已报废，used_up=已用完',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (glass_type_id) REFERENCES glass_types(id),
    FOREIGN KEY (current_rack_id) REFERENCES storage_racks(id) ON DELETE SET NULL,
    FOREIGN KEY (initial_rack_id) REFERENCES storage_racks(id) ON DELETE SET NULL
) COMMENT='原片包信息表';

-- 库存流转记录表
CREATE TABLE inventory_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_id INT NOT NULL COMMENT '原片包ID',
    transaction_type ENUM('purchase_in', 'usage_out', 'return_in', 'scrap', 'partial_usage', 'location_adjust', 'check_in', 'check_out') NOT NULL COMMENT '交易类型：采购入库、领用出库、归还入库、报废、部分领用、基地流转、盘盈入库、盘亏出库',
    from_rack_id INT COMMENT '来源库位架ID',
    to_rack_id INT COMMENT '目标库位架ID',
    quantity INT NOT NULL COMMENT '数量',
    operator_id INT COMMENT '操作员ID',
    scrap_reason TEXT COMMENT '报废原因',
    transaction_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '交易时间',
    actual_usage INT DEFAULT 0 COMMENT '实际领用量',
    notes TEXT COMMENT '备注信息',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES glass_packages(id),
    FOREIGN KEY (from_rack_id) REFERENCES storage_racks(id) ON DELETE SET NULL,
    FOREIGN KEY (to_rack_id) REFERENCES storage_racks(id) ON DELETE SET NULL
) COMMENT='库存流转记录表';

-- 用户表
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL COMMENT '用户名',
    password VARCHAR(255) NOT NULL COMMENT '密码',
    real_name VARCHAR(50) COMMENT '真实姓名',
    role ENUM('admin', 'manager', 'operator', 'viewer') NOT NULL DEFAULT 'operator' COMMENT '角色：管理员、库管、操作员、查看者',
    base_id INT COMMENT '所属基地ID',
    phone VARCHAR(20) COMMENT '电话',
    email VARCHAR(100) COMMENT '邮箱',
    last_login TIMESTAMP COMMENT '最后登录时间',
    status TINYINT(1) DEFAULT 1 COMMENT '状态：1启用，0禁用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (base_id) REFERENCES bases(id) ON DELETE SET NULL
) COMMENT='用户表';

-- 系统设置表
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE COMMENT '设置键',
    value TEXT COMMENT '设置值',
    description VARCHAR(255) COMMENT '设置描述',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) COMMENT='系统设置表';

-- 交易日志表
CREATE TABLE transaction_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT COMMENT '操作用户ID',
    action VARCHAR(100) NOT NULL COMMENT '操作类型',
    table_name VARCHAR(50) COMMENT '操作表名',
    record_id INT COMMENT '记录ID',
    old_data JSON COMMENT '旧数据',
    new_data JSON COMMENT '新数据',
    ip_address VARCHAR(45) COMMENT 'IP地址',
    user_agent TEXT COMMENT '用户代理',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) COMMENT='操作日志表';

-- 插入默认系统设置
INSERT INTO settings (setting_key, value, description) VALUES
('system_name', '玻璃包裹管理系统', '系统名称'),
('system_version', '1.0.0', '系统版本'),
('maintenance_mode', '0', '维护模式：1开启，0关闭'),
('session_timeout', '30', '会话超时时间（分钟）'),
('backup_retention_days', '30', '备份文件保留天数'),
('log_retention_days', '90', '日志保留天数');

-- 插入品牌数据
INSERT INTO dictionary_items (category, code, name, sort_order) VALUES
-- 品牌（顶级）
('brand', 'xinyi', '信义', 1),
('brand', 'taibo', '台玻', 2),
('brand', 'jinjing', '金晶', 3),
('brand', 'qibin', '旗滨', 4);

-- 获取品牌ID并插入对应的生产商
SET @xinyi_id = (SELECT id FROM dictionary_items WHERE category = 'brand' AND code = 'xinyi');
SET @taibo_id = (SELECT id FROM dictionary_items WHERE category = 'brand' AND code = 'taibo');
SET @jinjing_id = (SELECT id FROM dictionary_items WHERE category = 'brand' AND code = 'jinjing');
SET @qibin_id = (SELECT id FROM dictionary_items WHERE category = 'brand' AND code = 'qibin');

-- 生产商（关联到对应品牌）
INSERT INTO dictionary_items (category, code, name, parent_id, sort_order) VALUES
-- 信义品牌下的生产商
('manufacturer', 'cq_xinyi', '重庆信义', @xinyi_id, 1),
('manufacturer', 'dy_xinyi', '德阳信义', @xinyi_id, 2),
('manufacturer', 'tj_xinyi', '天津信义', @xinyi_id, 3),
('manufacturer', 'wh_xinyi', '芜湖信义', @xinyi_id, 4),

-- 台玻品牌下的生产商
('manufacturer', 'cd_taibo', '成都台玻', @taibo_id, 1),
('manufacturer', 'cq_taibo', '重庆台玻', @taibo_id, 2),

-- 金晶品牌下的生产商
('manufacturer', 'zb_jinjing', '淄博金晶', @jinjing_id, 1),
('manufacturer', 'mz_jinjing', '马鞍山金晶', @jinjing_id, 2),

-- 旗滨品牌下的生产商
('manufacturer', 'sz_qibin', '绍兴旗滨', @qibin_id, 1),
('manufacturer', 'cz_qibin', '郴州旗滨', @qibin_id, 2);

-- 颜色（独立分类，无父级关系）
INSERT INTO dictionary_items (category, code, name, sort_order) VALUES
('color', 'white', '白玻', 1),
('color', 'yusha', '玉沙', 2),
('color', 'lowe', 'LOWE', 3),
('color', 'ultra_white', '超白', 4),
('color', 'coated', '镀膜', 5),
('color', 'colored', '色玻', 6),
('color', 'one_way', '单向透视', 7),
('color', 'changlong', '长虹', 8);

-- 增强的库存操作记录表
CREATE TABLE inventory_operation_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    record_no VARCHAR(50) NOT NULL UNIQUE COMMENT '记录单号（自动生成）',
    operation_type ENUM('purchase_in', 'usage_out', 'partial_usage', 'return_in', 'scrap', 'check_in', 'check_out') NOT NULL COMMENT '操作类型',
    package_id INT NOT NULL COMMENT '原片包ID',
    glass_type_id INT NOT NULL COMMENT '原片类型ID',
    base_id INT NOT NULL COMMENT '操作基地ID',
    operation_quantity INT NOT NULL COMMENT '操作数量',
    before_quantity INT NOT NULL COMMENT '操作前数量',
    after_quantity INT NOT NULL COMMENT '操作后数量',
    from_rack_id INT COMMENT '来源库位架ID',
    to_rack_id INT COMMENT '目标库位架ID',
    unit_area DECIMAL(10,2) COMMENT '单片面积（平方米）',
    total_area DECIMAL(10,2) GENERATED ALWAYS AS (operation_quantity * unit_area) STORED COMMENT '操作总面积',
    operator_id INT NOT NULL COMMENT '操作员ID',
    operation_date DATE NOT NULL COMMENT '操作日期',
    operation_time TIME NOT NULL DEFAULT '08:00:00' COMMENT '操作时间',
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'completed' COMMENT '记录状态',
    scrap_reason TEXT COMMENT '报废原因（报废操作必填）',
    notes TEXT COMMENT '备注信息',
    related_record_id INT COMMENT '关联记录ID（如归还时关联原领用记录）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES glass_packages(id),
    FOREIGN KEY (glass_type_id) REFERENCES glass_types(id),
    FOREIGN KEY (base_id) REFERENCES bases(id),
    FOREIGN KEY (from_rack_id) REFERENCES storage_racks(id) ON DELETE SET NULL,
    FOREIGN KEY (to_rack_id) REFERENCES storage_racks(id) ON DELETE SET NULL,
    FOREIGN KEY (operator_id) REFERENCES users(id),
    FOREIGN KEY (related_record_id) REFERENCES inventory_operation_records(id) ON DELETE SET NULL,
    INDEX idx_record_no (record_no),
    INDEX idx_package_operation (package_id, operation_type),
    INDEX idx_base_date (base_id, operation_date),
    INDEX idx_operation_time (operation_date, operation_time),
    INDEX idx_status (status),
    INDEX idx_glass_type (glass_type_id),
    INDEX idx_operator (operator_id)
) COMMENT='库存操作记录表';

-- 操作记录汇总视图（按日期和基地汇总）
CREATE VIEW daily_operation_summary AS
SELECT 
    base_id,
    b.name as base_name,
    operation_date,
    operation_type,
    COUNT(*) as record_count,
    SUM(operation_quantity) as total_quantity,
    SUM(total_area) as total_area,
    COUNT(DISTINCT package_id) as package_count,
    COUNT(DISTINCT glass_type_id) as glass_type_count
FROM inventory_operation_records ior
LEFT JOIN bases b ON ior.base_id = b.id
WHERE status = 'completed'
GROUP BY base_id, operation_date, operation_type
ORDER BY operation_date DESC, base_id, operation_type;

-- 包操作历史视图
CREATE VIEW package_operation_history AS
SELECT 
    ior.*,
    gp.package_code,
    gt.name as glass_name,
    gt.thickness,
    gt.color,
    gt.brand,
    b.name as base_name,
    u.real_name as operator_name,
    fr.code as from_rack_code,
    tr.code as to_rack_code
FROM inventory_operation_records ior
LEFT JOIN glass_packages gp ON ior.package_id = gp.id
LEFT JOIN glass_types gt ON ior.glass_type_id = gt.id
LEFT JOIN bases b ON ior.base_id = b.id
LEFT JOIN users u ON ior.operator_id = u.id
LEFT JOIN storage_racks fr ON ior.from_rack_id = fr.id
LEFT JOIN storage_racks tr ON ior.to_rack_id = tr.id
ORDER BY ior.operation_date DESC, ior.operation_time DESC;


