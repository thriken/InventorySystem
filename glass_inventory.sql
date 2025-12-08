SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
-- glass_inventory DDL
CREATE DATABASE `glass_inventory`
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;;
use `glass_inventory`;
-- glass_inventory.bases DDL
CREATE TABLE `glass_inventory`.`bases` (`id` INT(11) NOT NULL AUTO_INCREMENT,
`name` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL Comment "基地名称",
`code` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL Comment "基地编码",
`address` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "基地地址",
`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0),
PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci AUTO_INCREMENT = 4 ROW_FORMAT = Dynamic COMMENT = "基地信息表";
-- glass_inventory.dictionary_items DDL
CREATE TABLE `glass_inventory`.`dictionary_items` (`id` INT(11) NOT NULL AUTO_INCREMENT,
`category` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL Comment "分类（brand/manufacturer/color）",
`code` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL Comment "代码",
`name` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL Comment "名称",
`parent_id` INT(11) NULL Comment "父级ID（用于品牌-生产商关系）",
`sort_order` INT(11) NULL DEFAULT 0 Comment "排序",
`status` TINYINT(1) NULL DEFAULT 1 Comment "状态：1启用，0禁用",
`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0),
INDEX `idx_category`(`category` ASC) USING BTREE,
INDEX `idx_parent_id`(`parent_id` ASC) USING BTREE,
UNIQUE INDEX `uk_category_code`(`category` ASC,`code` ASC) USING BTREE,
PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci AUTO_INCREMENT = 27 ROW_FORMAT = Dynamic COMMENT = "数据字典表（支持层级关系）";
-- glass_inventory.glass_packages DDL
CREATE TABLE `glass_inventory`.`glass_packages` (`id` INT(11) NOT NULL AUTO_INCREMENT,
`package_code` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL Comment "包号/二维码",
`glass_type_id` INT(11) NOT NULL Comment "原片类型ID",
`width` DECIMAL(10,2) NULL Comment "宽度(mm)",
`height` DECIMAL(10,2) NULL Comment "高度(mm)",
`pieces` INT(11) NOT NULL DEFAULT 0 Comment "实际片数（库存操作基准）",
`quantity` INT(11) NOT NULL DEFAULT 0 Comment "默认包装数量（参考值）",
`entry_date` DATE NULL Comment "入库日期",
`initial_rack_id` INT(11) NULL Comment "起始库区ID",
`current_rack_id` INT(11) NULL Comment "当前库位架ID",
`position_order` INT(11) NULL DEFAULT 1 Comment "在架中的位置顺序(1-x)",
`status` ENUM("in_storage","in_processing","scrapped","used_up") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'in_storage' Comment "包状态：in_storage=库存中，in_processing=加工中，scrapped=已报废，used_up=已用完",
`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0),
INDEX `current_rack_id`(`current_rack_id` ASC) USING BTREE,
INDEX `glass_type_id`(`glass_type_id` ASC) USING BTREE,
INDEX `initial_rack_id`(`initial_rack_id` ASC) USING BTREE,
PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci AUTO_INCREMENT = 160 ROW_FORMAT = Dynamic COMMENT = "原片包信息表";
-- glass_inventory.glass_types DDL
CREATE TABLE `glass_inventory`.`glass_types` (`id` INT(11) NOT NULL AUTO_INCREMENT,
`custom_id` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL Comment "原片ID（自定义，唯一，采购使用）",
`name` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL Comment "原片名称（完整名称）",
`short_name` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL Comment "原片简称（如：5白、4白、5白南玻）",
`finance_name` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "财务核算名",
`product_series` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "原片商色系（如：5lowe XETB0160）",
`brand` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "原片品牌（如：信义、台玻、金晶、旗滨）",
`manufacturer` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "原片生产商（如：重庆信义、德阳信义、成都台玻）",
`color` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "原片颜色（如：白玻、玉沙、LOWE、超白等）",
`thickness` DECIMAL(10,2) NULL Comment "原片厚度(mm)（如：4、5、6、8、10、12、15、19）",
`silver_layers` ENUM("单银","双银","三银","无银","") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "银层类型",
`substrate` ENUM("普白","超白","") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "基片类型",
`transmittance` ENUM("高透","中透","低透","") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "透光性",
`status` TINYINT(1) NULL DEFAULT 1 Comment "状态：1启用，0禁用",
`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0),
UNIQUE INDEX `custom_id`(`custom_id` ASC) USING BTREE,
INDEX `idx_brand`(`brand` ASC) USING BTREE,
INDEX `idx_color`(`color` ASC) USING BTREE,
INDEX `idx_custom_id`(`custom_id` ASC) USING BTREE,
INDEX `idx_manufacturer`(`manufacturer` ASC) USING BTREE,
INDEX `idx_short_name`(`short_name` ASC) USING BTREE,
INDEX `idx_silver_layers`(`silver_layers` ASC) USING BTREE,
INDEX `idx_substrate`(`substrate` ASC) USING BTREE,
INDEX `idx_transmittance`(`transmittance` ASC) USING BTREE,
PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci AUTO_INCREMENT = 91 ROW_FORMAT = Dynamic COMMENT = "原片基础信息表";
-- glass_inventory.inventory_check_cache DDL
CREATE TABLE `glass_inventory`.`inventory_check_cache` (`id` INT(11) NOT NULL AUTO_INCREMENT,
`task_id` INT(11) NOT NULL Comment "盘点任务ID",
`package_code` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL Comment "包号",
`package_id` INT(11) NULL Comment "包ID（关联查询得到）",
`system_quantity` INT(11) NOT NULL DEFAULT 0 Comment "系统数量",
`check_quantity` INT(11) NOT NULL DEFAULT 0 Comment "盘点数量",
`difference` INT(11) NOT NULL DEFAULT 0 Comment "差异数量 = 盘点数量 - 系统数量",
`rack_id` INT(11) NULL Comment "盘点时的库位架ID",
`check_method` ENUM("pda_scan","manual_input","excel_import") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual_input' Comment "盘点方式",
`check_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP Comment "盘点时间",
`operator_id` INT(11) NULL Comment "操作员ID",
`notes` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "备注信息",
`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP Comment "创建时间",
INDEX `idx_check_cache_task_status`(`task_id` ASC,`check_quantity` ASC,`system_quantity` ASC) USING BTREE,
INDEX `idx_package_code`(`package_code` ASC) USING BTREE,
INDEX `idx_task_id`(`task_id` ASC) USING BTREE,
UNIQUE INDEX `uk_task_package`(`task_id` ASC,`package_code` ASC) USING BTREE,
PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci AUTO_INCREMENT = 132 ROW_FORMAT = Dynamic COMMENT = "盘点明细缓存表";
-- glass_inventory.inventory_check_results DDL
CREATE TABLE `glass_inventory`.`inventory_check_results` (`id` INT(11) NOT NULL AUTO_INCREMENT,
`task_id` INT(11) NOT NULL Comment "盘点任务ID",
`glass_type_id` INT(11) NULL Comment "原片类型ID",
`total_system_quantity` INT(11) NOT NULL DEFAULT 0 Comment "系统总数",
`total_check_quantity` INT(11) NOT NULL DEFAULT 0 Comment "盘点总数",
`total_difference` INT(11) NOT NULL DEFAULT 0 Comment "总差异",
`profit_packages` INT(11) NOT NULL DEFAULT 0 Comment "盘盈包数",
`loss_packages` INT(11) NOT NULL DEFAULT 0 Comment "盘亏包数",
`normal_packages` INT(11) NOT NULL DEFAULT 0 Comment "正常包数",
`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP Comment "创建时间",
INDEX `idx_results_task_glass`(`task_id` ASC,`glass_type_id` ASC) USING BTREE,
INDEX `idx_task_id`(`task_id` ASC) USING BTREE,
UNIQUE INDEX `uk_task_glass`(`task_id` ASC,`glass_type_id` ASC) USING BTREE,
PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci AUTO_INCREMENT = 1 ROW_FORMAT = Dynamic COMMENT = "盘点结果汇总表";
-- glass_inventory.inventory_check_settings DDL
CREATE TABLE `glass_inventory`.`inventory_check_settings` (`id` INT(11) NOT NULL AUTO_INCREMENT,
`base_id` INT(11) NOT NULL Comment "基地ID",
`auto_task_number` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "自动任务编号前缀",
`check_frequency` ENUM("daily","weekly","monthly","quarterly","yearly","manual") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual' Comment "盘点频率",
`tolerance_percent` DECIMAL(5,2) NOT NULL DEFAULT 5.00 Comment "容差百分比",
`require_approval` TINYINT(1) NOT NULL DEFAULT 0 Comment "是否需要审批",
`approval_role` ENUM("admin","manager") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin' Comment "审批角色",
`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP Comment "创建时间",
`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0) Comment "更新时间",
UNIQUE INDEX `uk_base`(`base_id` ASC) USING BTREE,
PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci AUTO_INCREMENT = 5 ROW_FORMAT = Dynamic COMMENT = "盘点配置表";
-- glass_inventory.inventory_check_tasks DDL
CREATE TABLE `glass_inventory`.`inventory_check_tasks` (`id` INT(11) NOT NULL AUTO_INCREMENT,
`task_name` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL Comment "盘点任务名称",
`base_id` INT(11) NOT NULL Comment "盘点基地ID",
`task_type` ENUM("full","partial","random") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full' Comment "盘点类型：full=全盘，partial=部分盘点，random=抽盘",
`description` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "盘点说明",
`status` ENUM("created","in_progress","completed","cancelled") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'created' Comment "任务状态",
`created_by` INT(11) NOT NULL Comment "创建人ID",
`started_at` TIMESTAMP NULL Comment "开始时间",
`completed_at` TIMESTAMP NULL Comment "完成时间",
`total_packages` INT(11) NOT NULL DEFAULT 0 Comment "应盘包总数",
`checked_packages` INT(11) NOT NULL DEFAULT 0 Comment "已盘包数量",
`difference_count` INT(11) NOT NULL DEFAULT 0 Comment "差异数量",
`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP Comment "创建时间",
`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0) Comment "更新时间",
INDEX `idx_tasks_base_created`(`base_id` ASC,`created_at` ASC) USING BTREE,
PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci AUTO_INCREMENT = 3 ROW_FORMAT = Dynamic COMMENT = "盘点任务表";
-- glass_inventory.inventory_operation_records DDL
CREATE TABLE `glass_inventory`.`inventory_operation_records` (`id` INT(11) NOT NULL AUTO_INCREMENT,
`record_no` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL Comment "记录单号（自动生成）",
`operation_type` ENUM("purchase_in","usage_out","partial_usage","return_in","scrap","check_in","check_out") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL Comment "操作类型",
`package_id` INT(11) NOT NULL Comment "原片包ID",
`glass_type_id` INT(11) NOT NULL Comment "原片类型ID",
`base_id` INT(11) NOT NULL Comment "操作基地ID",
`operation_quantity` INT(11) NOT NULL Comment "操作数量",
`before_quantity` INT(11) NOT NULL Comment "操作前数量",
`after_quantity` INT(11) NOT NULL Comment "操作后数量",
`from_rack_id` INT(11) NULL Comment "来源库位架ID",
`to_rack_id` INT(11) NULL Comment "目标库位架ID",
`unit_area` DECIMAL(10,2) NULL Comment "单片面积（平方米）",
`total_area` DECIMAL(10,2) NULL Comment "操作总面积",
`operator_id` INT(11) NOT NULL Comment "操作员ID",
`operation_date` DATE NOT NULL Comment "操作日期",
`operation_time` TIME NOT NULL DEFAULT '08:00:00' Comment "操作时间",
`status` ENUM("pending","completed","cancelled") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'completed' Comment "记录状态",
`scrap_reason` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "报废原因（报废操作必填）",
`notes` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "备注信息",
`related_record_id` INT(11) NULL Comment "关联记录ID（如归还时关联原领用记录）",
`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0),
INDEX `from_rack_id`(`from_rack_id` ASC) USING BTREE,
INDEX `idx_base_date`(`base_id` ASC,`operation_date` ASC) USING BTREE,
INDEX `idx_glass_type`(`glass_type_id` ASC) USING BTREE,
INDEX `idx_operation_time`(`operation_date` ASC,`operation_time` ASC) USING BTREE,
INDEX `idx_operator`(`operator_id` ASC) USING BTREE,
INDEX `idx_package_operation`(`package_id` ASC,`operation_type` ASC) USING BTREE,
INDEX `idx_record_no`(`record_no` ASC) USING BTREE,
INDEX `idx_status`(`status` ASC) USING BTREE,
UNIQUE INDEX `record_no`(`record_no` ASC) USING BTREE,
INDEX `related_record_id`(`related_record_id` ASC) USING BTREE,
INDEX `to_rack_id`(`to_rack_id` ASC) USING BTREE,
PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci AUTO_INCREMENT = 1 ROW_FORMAT = Dynamic COMMENT = "库存操作记录表";
-- glass_inventory.inventory_transactions DDL
CREATE TABLE `glass_inventory`.`inventory_transactions` (`id` INT(11) NOT NULL AUTO_INCREMENT,
`package_id` INT(11) NOT NULL Comment "原片包ID",
`transaction_type` ENUM("purchase_in","usage_out","return_in","scrap","partial_usage","location_adjust","check_in","check_out") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL Comment "交易类型：采购入库、领用出库、归还入库、报废、部分领用、基地流转、盘盈入库、盘亏出库",
`from_rack_id` INT(11) NULL Comment "来源库位架ID",
`to_rack_id` INT(11) NULL Comment "目标库位架ID",
`quantity` INT(11) NOT NULL Comment "数量",
`operator_id` INT(11) NULL Comment "操作员ID",
`scrap_reason` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "报废原因",
`transaction_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP Comment "交易时间",
`actual_usage` INT(11) NULL DEFAULT 0 Comment "实际领用量",
`notes` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "备注信息",
`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
INDEX `from_rack_id`(`from_rack_id` ASC) USING BTREE,
INDEX `package_id`(`package_id` ASC) USING BTREE,
INDEX `to_rack_id`(`to_rack_id` ASC) USING BTREE,
PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci AUTO_INCREMENT = 15 ROW_FORMAT = Dynamic COMMENT = "库存流转记录表";
-- glass_inventory.settings DDL
CREATE TABLE `glass_inventory`.`settings` (`id` INT(11) NOT NULL AUTO_INCREMENT,
`setting_key` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL Comment "设置键",
`value` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "设置值",
`description` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "设置描述",
`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0),
UNIQUE INDEX `setting_key`(`setting_key` ASC) USING BTREE,
PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci AUTO_INCREMENT = 7 ROW_FORMAT = Dynamic COMMENT = "系统设置表";
-- glass_inventory.storage_racks DDL
CREATE TABLE `glass_inventory`.`storage_racks` (`id` INT(11) NOT NULL AUTO_INCREMENT,
`base_id` INT(11) NOT NULL Comment "所属基地ID",
`code` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL Comment "库位架编码",
`name` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "库位架名称",
`area_type` ENUM("storage","processing","scrap","temporary") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'storage' Comment "区域类型：库存区、加工区、报废区、临时区",
`capacity` INT(11) NULL Comment "容量",
`status` ENUM("normal","maintenance","full") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'normal' Comment "状态：正常、维护中、已满",
`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0),
INDEX `base_id`(`base_id` ASC) USING BTREE,
PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4 COLLATE = utf8mb4_unicode_ci AUTO_INCREMENT = 193 ROW_FORMAT = Dynamic COMMENT = "库位架信息表";
-- glass_inventory.transaction_logs DDL
CREATE TABLE `glass_inventory`.`transaction_logs` (`id` INT(11) NOT NULL AUTO_INCREMENT,
`user_id` INT(11) NULL Comment "操作用户ID",
`action` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL Comment "操作类型",
`table_name` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "操作表名",
`record_id` INT(11) NULL Comment "记录ID",
`old_data` JSON NULL Comment "旧数据",
`new_data` JSON NULL Comment "新数据",
`ip_address` VARCHAR(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "IP地址",
`user_agent` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "用户代理",
`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
INDEX `user_id`(`user_id` ASC) USING BTREE,
PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci AUTO_INCREMENT = 1 ROW_FORMAT = Dynamic COMMENT = "操作日志表";
-- glass_inventory.users DDL
CREATE TABLE `glass_inventory`.`users` (`id` INT(11) NOT NULL AUTO_INCREMENT,
`username` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL Comment "用户名",
`password` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL Comment "密码",
`real_name` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "真实姓名",
`role` ENUM("admin","manager","operator","viewer") CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'operator',
`base_id` INT(11) NULL Comment "所属基地ID",
`phone` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "电话",
`email` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL Comment "邮箱",
`last_login` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0) Comment "最后登录时间",
`status` TINYINT(1) NULL DEFAULT 1 Comment "状态：1启用，0禁用",
`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP(0),
INDEX `base_id`(`base_id` ASC) USING BTREE,
PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci AUTO_INCREMENT = 8 ROW_FORMAT = Dynamic COMMENT = "用户表";
-- glass_inventory.inventory_check_difference_details DDL
CREATE  ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `glass_inventory`.`inventory_check_difference_details` AS select `c`.`task_id` AS `task_id`,`c`.`package_code` AS `package_code`,`g`.`short_name` AS `glass_name`,`c`.`system_quantity` AS `system_quantity`,`c`.`check_quantity` AS `check_quantity`,`c`.`difference` AS `difference`,(case when (`c`.`difference` > 0) then concat('+',`c`.`difference`) when (`c`.`difference` = 0) then '0' else cast(`c`.`difference` as char charset utf8mb4) end) AS `difference_display`,(case when (`c`.`difference` > 0) then '盘盈' when (`c`.`difference` < 0) then '盘亏' else '正常' end) AS `difference_type`,`r`.`code` AS `rack_code`,`c`.`check_method` AS `check_method`,`c`.`check_time` AS `check_time`,`u`.`real_name` AS `operator_name` from ((((`glass_inventory`.`inventory_check_cache` `c` left join `glass_inventory`.`glass_packages` `p` on((`c`.`package_id` = `p`.`id`))) left join `glass_inventory`.`glass_types` `g` on((`p`.`glass_type_id` = `g`.`id`))) left join `glass_inventory`.`storage_racks` `r` on((`c`.`rack_id` = `r`.`id`))) left join `glass_inventory`.`users` `u` on((`c`.`operator_id` = `u`.`id`))) where (`c`.`difference` <> 0);
-- glass_inventory.inventory_check_task_summary DDL
CREATE  ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `glass_inventory`.`inventory_check_task_summary` AS select `t`.`id` AS `id`,`t`.`task_name` AS `task_name`,`t`.`base_id` AS `base_id`,`b`.`name` AS `base_name`,`t`.`task_type` AS `task_type`,`t`.`status` AS `status`,`t`.`total_packages` AS `total_packages`,`t`.`checked_packages` AS `checked_packages`,(case when (`t`.`total_packages` > 0) then round(((`t`.`checked_packages` * 100.0) / `t`.`total_packages`),2) else 0 end) AS `completion_rate`,`t`.`difference_count` AS `difference_count`,`u`.`real_name` AS `created_by_name`,`t`.`created_at` AS `created_at`,`t`.`started_at` AS `started_at`,`t`.`completed_at` AS `completed_at`,(case when (`t`.`status` = 'completed') then timediff(`t`.`completed_at`,`t`.`started_at`) when (`t`.`status` = 'in_progress') then timediff(now(),`t`.`started_at`) else NULL end) AS `duration` from ((`glass_inventory`.`inventory_check_tasks` `t` left join `glass_inventory`.`bases` `b` on((`t`.`base_id` = `b`.`id`))) left join `glass_inventory`.`users` `u` on((`t`.`created_by` = `u`.`id`)));
-- glass_inventory.AddForeignKeySafely DDL
DROP PROCEDURE IF EXISTS `glass_inventory`.`AddForeignKeySafely`;
DELIMITER $$
CREATE DEFINER=`root`@`%` PROCEDURE `AddForeignKeySafely`()
BEGIN
    DECLARE table_exists INT;
    
    -- 添加 inventory_check_tasks 表的外键
    SELECT COUNT(*) INTO table_exists 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'bases';
    
    IF table_exists > 0 THEN
        SET @sql = 'ALTER TABLE inventory_check_tasks ADD CONSTRAINT fk_check_tasks_base_id FOREIGN KEY (base_id) REFERENCES bases(id)';
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
    
    SELECT COUNT(*) INTO table_exists 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'users';
    
    IF table_exists > 0 THEN
        SET @sql = 'ALTER TABLE inventory_check_tasks ADD CONSTRAINT fk_check_tasks_created_by FOREIGN KEY (created_by) REFERENCES users(id)';
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
    
    -- 添加 inventory_check_cache 表的外键
    SET @sql = 'ALTER TABLE inventory_check_cache ADD CONSTRAINT fk_check_cache_task_id FOREIGN KEY (task_id) REFERENCES inventory_check_tasks(id) ON DELETE CASCADE';
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    
    SELECT COUNT(*) INTO table_exists 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'glass_packages';
    
    IF table_exists > 0 THEN
        SET @sql = 'ALTER TABLE inventory_check_cache ADD CONSTRAINT fk_check_cache_package_id FOREIGN KEY (package_id) REFERENCES glass_packages(id)';
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
    
    SELECT COUNT(*) INTO table_exists 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'storage_racks';
    
    IF table_exists > 0 THEN
        SET @sql = 'ALTER TABLE inventory_check_cache ADD CONSTRAINT fk_check_cache_rack_id FOREIGN KEY (rack_id) REFERENCES storage_racks(id)';
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
    
    SELECT COUNT(*) INTO table_exists 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'users';
    
    IF table_exists > 0 THEN
        SET @sql = 'ALTER TABLE inventory_check_cache ADD CONSTRAINT fk_check_cache_operator_id FOREIGN KEY (operator_id) REFERENCES users(id)';
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
    
    -- 添加 inventory_check_results 表的外键
    SET @sql = 'ALTER TABLE inventory_check_results ADD CONSTRAINT fk_check_results_task_id FOREIGN KEY (task_id) REFERENCES inventory_check_tasks(id) ON DELETE CASCADE';
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    
    SELECT COUNT(*) INTO table_exists 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'glass_types';
    
    IF table_exists > 0 THEN
        SET @sql = 'ALTER TABLE inventory_check_results ADD CONSTRAINT fk_check_results_glass_type_id FOREIGN KEY (glass_type_id) REFERENCES glass_types(id)';
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
    
    -- 添加 inventory_check_settings 表的外键
    SELECT COUNT(*) INTO table_exists 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'bases';
    
    IF table_exists > 0 THEN
        SET @sql = 'ALTER TABLE inventory_check_settings ADD CONSTRAINT fk_check_settings_base_id FOREIGN KEY (base_id) REFERENCES bases(id)';
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
    
END$$
DELIMITER ;
SET FOREIGN_KEY_CHECKS = 1;
