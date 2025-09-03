-- Database: glass_inventory Backup
-- Date: 2025-08-25 17:50:57

CREATE TABLE `bases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '基地名称',
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '基地编码',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '基地地址',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='基地信息表';

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daily_operation_summary` AS select `ior`.`base_id` AS `base_id`,`b`.`name` AS `base_name`,`ior`.`operation_date` AS `operation_date`,`ior`.`operation_type` AS `operation_type`,count(0) AS `record_count`,sum(`ior`.`operation_quantity`) AS `total_quantity`,sum(`ior`.`total_area`) AS `total_area`,count(distinct `ior`.`package_id`) AS `package_count`,count(distinct `ior`.`glass_type_id`) AS `glass_type_count` from (`inventory_operation_records` `ior` left join `bases` `b` on((`ior`.`base_id` = `b`.`id`))) where (`ior`.`status` = 'completed') group by `ior`.`base_id`,`ior`.`operation_date`,`ior`.`operation_type` order by `ior`.`operation_date` desc,`ior`.`base_id`,`ior`.`operation_type`;

CREATE TABLE `dictionary_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '分类（brand/manufacturer/color）',
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '代码',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '名称',
  `parent_id` int(11) DEFAULT NULL COMMENT '父级ID（用于品牌-生产商关系）',
  `sort_order` int(11) DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态：1启用，0禁用',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_category_code` (`category`,`code`) USING BTREE,
  KEY `idx_category` (`category`) USING BTREE,
  KEY `idx_parent_id` (`parent_id`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='数据字典表（支持层级关系）';

CREATE TABLE `glass_packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '包号/二维码',
  `glass_type_id` int(11) NOT NULL COMMENT '原片类型ID',
  `width` decimal(10,2) DEFAULT NULL COMMENT '宽度(mm)',
  `height` decimal(10,2) DEFAULT NULL COMMENT '高度(mm)',
  `pieces` int(11) NOT NULL DEFAULT '0' COMMENT '实际片数（库存操作基准）',
  `quantity` int(11) NOT NULL DEFAULT '0' COMMENT '默认包装数量（参考值）',
  `entry_date` date DEFAULT NULL COMMENT '入库日期',
  `initial_rack_id` int(11) DEFAULT NULL COMMENT '起始库区ID',
  `current_rack_id` int(11) DEFAULT NULL COMMENT '当前库位架ID',
  `position_order` int(11) DEFAULT '1' COMMENT '在架中的位置顺序(1-x)',
  `status` enum('in_storage','in_processing','scrapped','used_up') COLLATE utf8mb4_unicode_ci DEFAULT 'in_storage' COMMENT '包状态：in_storage=库存中，in_processing=加工中，scrapped=已报废，used_up=已用完',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `glass_type_id` (`glass_type_id`),
  KEY `current_rack_id` (`current_rack_id`),
  KEY `initial_rack_id` (`initial_rack_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='原片包信息表';

CREATE TABLE `glass_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `custom_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '原片ID（自定义，唯一，采购使用）',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '原片名称（完整名称）',
  `short_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '原片简称（如：5白、4白、5白南玻）',
  `finance_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '财务核算名',
  `product_series` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '原片商色系（如：5lowe XETB0160）',
  `brand` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '原片品牌（如：信义、台玻、金晶、旗滨）',
  `manufacturer` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '原片生产商（如：重庆信义、德阳信义、成都台玻）',
  `color` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '原片颜色（如：白玻、玉沙、LOWE、超白等）',
  `thickness` decimal(10,2) DEFAULT NULL COMMENT '原片厚度(mm)（如：4、5、6、8、10、12、15、19）',
  `width` decimal(10,2) DEFAULT NULL COMMENT '宽度(mm)',
  `height` decimal(10,2) DEFAULT NULL COMMENT '高度(mm)',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '备注描述',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态：1启用，0禁用',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `custom_id` (`custom_id`),
  KEY `idx_custom_id` (`custom_id`),
  KEY `idx_short_name` (`short_name`),
  KEY `idx_brand` (`brand`),
  KEY `idx_manufacturer` (`manufacturer`),
  KEY `idx_color` (`color`)
) ENGINE=MyISAM AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='原片基础信息表';

CREATE TABLE `inventory_operation_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `record_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '记录单号（自动生成）',
  `operation_type` enum('purchase_in','usage_out','partial_usage','return_in','scrap','check_in','check_out') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '操作类型',
  `package_id` int(11) NOT NULL COMMENT '原片包ID',
  `glass_type_id` int(11) NOT NULL COMMENT '原片类型ID',
  `base_id` int(11) NOT NULL COMMENT '操作基地ID',
  `operation_quantity` int(11) NOT NULL COMMENT '操作数量',
  `before_quantity` int(11) NOT NULL COMMENT '操作前数量',
  `after_quantity` int(11) NOT NULL COMMENT '操作后数量',
  `from_rack_id` int(11) DEFAULT NULL COMMENT '来源库位架ID',
  `to_rack_id` int(11) DEFAULT NULL COMMENT '目标库位架ID',
  `unit_area` decimal(10,2) DEFAULT NULL COMMENT '单片面积（平方米）',
  `total_area` decimal(10,2) GENERATED ALWAYS AS ((`operation_quantity` * `unit_area`)) STORED COMMENT '操作总面积',
  `operator_id` int(11) NOT NULL COMMENT '操作员ID',
  `operation_date` date NOT NULL COMMENT '操作日期',
  `operation_time` time NOT NULL DEFAULT '08:00:00' COMMENT '操作时间',
  `status` enum('pending','completed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'completed' COMMENT '记录状态',
  `scrap_reason` text COLLATE utf8mb4_unicode_ci COMMENT '报废原因（报废操作必填）',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注信息',
  `related_record_id` int(11) DEFAULT NULL COMMENT '关联记录ID（如归还时关联原领用记录）',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `record_no` (`record_no`),
  KEY `from_rack_id` (`from_rack_id`),
  KEY `to_rack_id` (`to_rack_id`),
  KEY `related_record_id` (`related_record_id`),
  KEY `idx_record_no` (`record_no`),
  KEY `idx_package_operation` (`package_id`,`operation_type`),
  KEY `idx_base_date` (`base_id`,`operation_date`),
  KEY `idx_operation_time` (`operation_date`,`operation_time`),
  KEY `idx_status` (`status`),
  KEY `idx_glass_type` (`glass_type_id`),
  KEY `idx_operator` (`operator_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库存操作记录表';

CREATE TABLE `inventory_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_id` int(11) NOT NULL COMMENT '原片包ID',
  `transaction_type` enum('purchase_in','usage_out','return_in','scrap','partial_usage','location_adjust','check_in','check_out') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '交易类型：采购入库、领用出库、归还入库、报废、部分领用、基地流转、盘盈入库、盘亏出库',
  `from_rack_id` int(11) DEFAULT NULL COMMENT '来源库位架ID',
  `to_rack_id` int(11) DEFAULT NULL COMMENT '目标库位架ID',
  `quantity` int(11) NOT NULL COMMENT '数量',
  `operator_id` int(11) DEFAULT NULL COMMENT '操作员ID',
  `scrap_reason` text COLLATE utf8mb4_unicode_ci COMMENT '报废原因',
  `transaction_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '交易时间',
  `actual_usage` int(11) DEFAULT '0' COMMENT '实际领用量',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注信息',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `package_id` (`package_id`),
  KEY `from_rack_id` (`from_rack_id`),
  KEY `to_rack_id` (`to_rack_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库存流转记录表';

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `package_operation_history` AS select `ior`.`id` AS `id`,`ior`.`record_no` AS `record_no`,`ior`.`operation_type` AS `operation_type`,`ior`.`package_id` AS `package_id`,`ior`.`glass_type_id` AS `glass_type_id`,`ior`.`base_id` AS `base_id`,`ior`.`operation_quantity` AS `operation_quantity`,`ior`.`before_quantity` AS `before_quantity`,`ior`.`after_quantity` AS `after_quantity`,`ior`.`from_rack_id` AS `from_rack_id`,`ior`.`to_rack_id` AS `to_rack_id`,`ior`.`unit_area` AS `unit_area`,`ior`.`total_area` AS `total_area`,`ior`.`operator_id` AS `operator_id`,`ior`.`operation_date` AS `operation_date`,`ior`.`operation_time` AS `operation_time`,`ior`.`status` AS `status`,`ior`.`scrap_reason` AS `scrap_reason`,`ior`.`notes` AS `notes`,`ior`.`related_record_id` AS `related_record_id`,`ior`.`created_at` AS `created_at`,`ior`.`updated_at` AS `updated_at`,`gp`.`package_code` AS `package_code`,`gt`.`name` AS `glass_name`,`gt`.`thickness` AS `thickness`,`gt`.`color` AS `color`,`gt`.`brand` AS `brand`,`b`.`name` AS `base_name`,`u`.`real_name` AS `operator_name`,`fr`.`code` AS `from_rack_code`,`tr`.`code` AS `to_rack_code` from ((((((`inventory_operation_records` `ior` left join `glass_packages` `gp` on((`ior`.`package_id` = `gp`.`id`))) left join `glass_types` `gt` on((`ior`.`glass_type_id` = `gt`.`id`))) left join `bases` `b` on((`ior`.`base_id` = `b`.`id`))) left join `users` `u` on((`ior`.`operator_id` = `u`.`id`))) left join `storage_racks` `fr` on((`ior`.`from_rack_id` = `fr`.`id`))) left join `storage_racks` `tr` on((`ior`.`to_rack_id` = `tr`.`id`))) order by `ior`.`operation_date` desc,`ior`.`operation_time` desc;

CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '设置键',
  `value` text COLLATE utf8mb4_unicode_ci COMMENT '设置值',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '设置描述',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统设置表';

CREATE TABLE `storage_racks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `base_id` int(11) NOT NULL COMMENT '所属基地ID',
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '库位架编码',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '库位架名称',
  `area_type` enum('storage','processing','scrap','temporary') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'storage' COMMENT '区域类型：库存区、加工区、报废区、临时区',
  `capacity` int(11) DEFAULT NULL COMMENT '容量',
  `status` enum('normal','maintenance','full') COLLATE utf8mb4_unicode_ci DEFAULT 'normal' COMMENT '状态：正常、维护中、已满',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `base_id` (`base_id`)
) ENGINE=MyISAM AUTO_INCREMENT=193 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库位架信息表';

CREATE TABLE `transaction_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT '操作用户ID',
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '操作类型',
  `table_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作表名',
  `record_id` int(11) DEFAULT NULL COMMENT '记录ID',
  `old_data` json DEFAULT NULL COMMENT '旧数据',
  `new_data` json DEFAULT NULL COMMENT '新数据',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP地址',
  `user_agent` text COLLATE utf8mb4_unicode_ci COMMENT '用户代理',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志表';

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '用户名',
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '密码',
  `real_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '真实姓名',
  `role` enum('admin','manager','operator','viewer') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'operator',
  `base_id` int(11) DEFAULT NULL COMMENT '所属基地ID',
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '电话',
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '邮箱',
  `last_login` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最后登录时间',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态：1启用，0禁用',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `base_id` (`base_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

