-- 盘点功能相关数据表设计
-- 原片实时库存系统盘点模块
-- 兼容MySQL 5.7+
-- 创建时间: 2025-11-29

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 检查并删除已存在的表
DROP TABLE IF EXISTS `inventory_check_cache`;
DROP TABLE IF EXISTS `inventory_check_results`;
DROP TABLE IF EXISTS `inventory_check_settings`;
DROP TABLE IF EXISTS `inventory_check_tasks`;
DROP VIEW IF EXISTS `inventory_check_task_summary`;
DROP VIEW IF EXISTS `inventory_check_difference_details`;

-- 1. 盘点任务表
CREATE TABLE `inventory_check_tasks` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `task_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '盘点任务名称',
    `base_id` int(11) NOT NULL COMMENT '盘点基地ID',
    `task_type` enum('full','partial','random') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full' COMMENT '盘点类型：full=全盘，partial=部分盘点，random=抽盘',
    `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '盘点说明',
    `status` enum('created','in_progress','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'created' COMMENT '任务状态',
    `created_by` int(11) NOT NULL COMMENT '创建人ID',
    `started_at` timestamp NULL DEFAULT NULL COMMENT '开始时间',
    `completed_at` timestamp NULL DEFAULT NULL COMMENT '完成时间',
    `total_packages` int(11) NOT NULL DEFAULT 0 COMMENT '应盘包总数',
    `checked_packages` int(11) NOT NULL DEFAULT 0 COMMENT '已盘包数量',
    `difference_count` int(11) NOT NULL DEFAULT 0 COMMENT '差异数量',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    PRIMARY KEY (`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '盘点任务表' ROW_FORMAT = DYNAMIC;

-- 2. 盘点明细缓存表
CREATE TABLE `inventory_check_cache` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `task_id` int(11) NOT NULL COMMENT '盘点任务ID',
    `package_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '包号',
    `package_id` int(11) DEFAULT NULL COMMENT '包ID（关联查询得到）',
    `system_quantity` int(11) NOT NULL DEFAULT 0 COMMENT '系统数量',
    `check_quantity` int(11) NOT NULL DEFAULT 0 COMMENT '盘点数量',
    `difference` int(11) NOT NULL DEFAULT 0 COMMENT '差异数量 = 盘点数量 - 系统数量',
    `rack_id` int(11) DEFAULT NULL COMMENT '盘点时的库位架ID',
    `check_method` enum('pda_scan','manual_input','excel_import','auto_rollback') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual_input' COMMENT '盘点方式',
    `check_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '盘点时间',
    `operator_id` int(11) DEFAULT NULL COMMENT '操作员ID',
    `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '备注信息',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    UNIQUE KEY `uk_task_package`(`task_id`, `package_code`) USING BTREE,
    INDEX `idx_package_code`(`package_code`) USING BTREE,
    INDEX `idx_task_id`(`task_id`) USING BTREE
) ENGINE = MyISAM CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '盘点明细缓存表' ROW_FORMAT = DYNAMIC;

-- 3. 盘点结果汇总表
CREATE TABLE `inventory_check_results` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `task_id` int(11) NOT NULL COMMENT '盘点任务ID',
    `glass_type_id` int(11) DEFAULT NULL COMMENT '原片类型ID',
    `total_system_quantity` int(11) NOT NULL DEFAULT 0 COMMENT '系统总数',
    `total_check_quantity` int(11) NOT NULL DEFAULT 0 COMMENT '盘点总数',
    `total_difference` int(11) NOT NULL DEFAULT 0 COMMENT '总差异',
    `profit_packages` int(11) NOT NULL DEFAULT 0 COMMENT '盘盈包数',
    `loss_packages` int(11) NOT NULL DEFAULT 0 COMMENT '盘亏包数',
    `normal_packages` int(11) NOT NULL DEFAULT 0 COMMENT '正常包数',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    UNIQUE KEY `uk_task_glass`(`task_id`, `glass_type_id`) USING BTREE,
    INDEX `idx_task_id`(`task_id`) USING BTREE
) ENGINE = MyISAM CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '盘点结果汇总表' ROW_FORMAT = DYNAMIC;

-- 4. 盘点任务配置表（可选）
CREATE TABLE `inventory_check_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `base_id` int(11) NOT NULL COMMENT '基地ID',
    `auto_task_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '自动任务编号前缀',
    `check_frequency` enum('daily','weekly','monthly','quarterly','yearly','manual') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual' COMMENT '盘点频率',
    `tolerance_percent` decimal(5,2) NOT NULL DEFAULT 5.00 COMMENT '容差百分比',
    `require_approval` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否需要审批',
    `approval_role` enum('admin','manager') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin' COMMENT '审批角色',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    UNIQUE KEY `uk_base`(`base_id`) USING BTREE
) ENGINE = MyISAM CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '盘点配置表' ROW_FORMAT = DYNAMIC;

-- 创建索引优化查询性能
CREATE INDEX `idx_check_cache_task_status` ON `inventory_check_cache`(`task_id`, `check_quantity`, `system_quantity`) USING BTREE;
CREATE INDEX `idx_results_task_glass` ON `inventory_check_results`(`task_id`, `glass_type_id`) USING BTREE;
CREATE INDEX `idx_tasks_base_created` ON `inventory_check_tasks`(`base_id`, `created_at`) USING BTREE;

-- MySQL 5.7 兼容的视图创建
CREATE VIEW `inventory_check_task_summary` AS
SELECT 
    t.`id`,
    t.`task_name`,
    t.`base_id`,
    b.`name` AS `base_name`,
    t.`task_type`,
    t.`status`,
    t.`total_packages`,
    t.`checked_packages`,
    CASE 
        WHEN t.`total_packages` > 0 THEN ROUND((t.`checked_packages` * 100.0 / t.`total_packages`), 2)
        ELSE 0 
    END AS `completion_rate`,
    t.`difference_count`,
    u.`real_name` AS `created_by_name`,
    t.`created_at`,
    t.`started_at`,
    t.`completed_at`,
    CASE 
        WHEN t.`status` = 'completed' THEN TIMEDIFF(t.`completed_at`, t.`started_at`)
        WHEN t.`status` = 'in_progress' THEN TIMEDIFF(NOW(), t.`started_at`)
        ELSE NULL
    END AS `duration`
FROM `inventory_check_tasks` t
LEFT JOIN `bases` b ON t.`base_id` = b.`id`
LEFT JOIN `users` u ON t.`created_by` = u.`id`;

CREATE VIEW `inventory_check_difference_details` AS
SELECT 
    c.`task_id`,
    c.`package_code`,
    g.`short_name` AS `glass_name`,
    c.`system_quantity`,
    c.`check_quantity`,
    c.`difference`,
    CASE 
        WHEN c.`difference` > 0 THEN CONCAT('+', c.`difference`)
        WHEN c.`difference` = 0 THEN '0'
        ELSE CAST(c.`difference` AS CHAR)
    END AS `difference_display`,
    CASE 
        WHEN c.`difference` > 0 THEN '盘盈'
        WHEN c.`difference` < 0 THEN '盘亏'
        ELSE '正常'
    END AS `difference_type`,
    r.`code` AS `rack_code`,
    c.`check_method`,
    c.`check_time`,
    u.`real_name` AS `operator_name`
FROM `inventory_check_cache` c
LEFT JOIN `glass_packages` p ON c.`package_id` = p.`id`
LEFT JOIN `glass_types` g ON p.`glass_type_id` = g.`id`
LEFT JOIN `storage_racks` r ON c.`rack_id` = r.`id`
LEFT JOIN `users` u ON c.`operator_id` = u.`id`
WHERE c.`difference` != 0;

-- 插入默认配置数据（MySQL 5.7 兼容语法）
INSERT INTO `inventory_check_settings` (`base_id`, `auto_task_number`, `check_frequency`, `tolerance_percent`, `require_approval`, `approval_role`)
SELECT 
    `id`, 
    CONCAT('PC', DATE_FORMAT(NOW(), '%Y%m%d'), '_', LPAD((@row:=@row+1), 3, '0')), 
    'manual', 
    5.00, 
    0, 
    'admin'
FROM `bases`, (SELECT @row:=0) AS r;

-- 启用外键检查
SET FOREIGN_KEY_CHECKS = 1;

-- 提交事务
COMMIT;