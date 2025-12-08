/*
 Navicat Premium Data Transfer

 Source Server         : OSP
 Source Server Type    : MySQL
 Source Server Version : 50744
 Source Host           : 127.127.126.3:3306
 Source Schema         : glass_inventory

 Target Server Type    : MySQL
 Target Server Version : 50744
 File Encoding         : 65001

 Date: 29/11/2025 17:59:48
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for bases
-- ----------------------------
DROP TABLE IF EXISTS `bases`;
CREATE TABLE `bases`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '基地名称',
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '基地编码',
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '基地地址',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '基地信息表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of bases
-- ----------------------------
INSERT INTO `bases` VALUES (1, '信义基地', 'XY', '德阳信义工业园区8号厂房', '2025-08-13 13:09:51', '2025-08-13 13:09:51');
INSERT INTO `bases` VALUES (2, '新丰基地', 'XF', '台北路西三段10号良木道集团1车间', '2025-08-13 13:12:12', '2025-08-13 13:12:12');
INSERT INTO `bases` VALUES (3, '金鱼基地', 'JY', '广汉市飞宇路11号', '2025-08-13 13:12:57', '2025-08-13 13:12:57');

-- ----------------------------
-- Table structure for dictionary_items
-- ----------------------------
DROP TABLE IF EXISTS `dictionary_items`;
CREATE TABLE `dictionary_items`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '分类（brand/manufacturer/color）',
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '代码',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '名称',
  `parent_id` int(11) NULL DEFAULT NULL COMMENT '父级ID（用于品牌-生产商关系）',
  `sort_order` int(11) NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态：1启用，0禁用',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_category_code`(`category`, `code`) USING BTREE,
  INDEX `idx_category`(`category`) USING BTREE,
  INDEX `idx_parent_id`(`parent_id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 27 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '数据字典表（支持层级关系）' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of dictionary_items
-- ----------------------------
INSERT INTO `dictionary_items` VALUES (1, 'brand', 'xinyi', '信义', NULL, 1, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (2, 'brand', 'taibo', '台玻', NULL, 2, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (3, 'brand', 'jinjing', '金晶', NULL, 3, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (4, 'brand', 'qibin', '旗滨', NULL, 4, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (5, 'manufacturer', 'cq_xinyi', '重庆信义', 1, 1, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (6, 'manufacturer', 'dy_xinyi', '德阳信义', 1, 2, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (7, 'manufacturer', 'tj_xinyi', '天津信义', 1, 3, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (8, 'manufacturer', 'wh_xinyi', '芜湖信义', 1, 4, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (9, 'manufacturer', 'cd_taibo', '成都台玻', 2, 1, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (10, 'manufacturer', 'cq_taibo', '重庆台玻', 2, 2, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (11, 'manufacturer', 'zb_jinjing', '淄博金晶', 3, 1, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (12, 'manufacturer', 'mz_jinjing', '马鞍山金晶', 3, 2, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (13, 'manufacturer', 'sz_qibin', '绍兴旗滨', 4, 1, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (14, 'manufacturer', 'cz_qibin', '郴州旗滨', 4, 2, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (15, 'color', 'white', '白玻', NULL, 1, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (16, 'color', 'yusha', '玉砂', NULL, 2, 1, '2025-08-13 14:24:07', '2025-09-03 19:17:36');
INSERT INTO `dictionary_items` VALUES (17, 'color', 'lowe', 'LOWE', NULL, 3, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (18, 'color', 'ultra_white', '超白', NULL, 4, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (19, 'color', 'coated', '镀膜', NULL, 5, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (20, 'color', 'colored', '色玻', NULL, 6, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (21, 'color', 'one_way', '单向透视', NULL, 7, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (22, 'color', 'changlong', '长虹', NULL, 8, 1, '2025-08-13 14:24:07', '2025-08-13 14:24:07');
INSERT INTO `dictionary_items` VALUES (23, 'brand', 'zhongbo', '中玻', NULL, 3, 1, '2025-09-03 18:48:31', '2025-09-03 19:17:50');
INSERT INTO `dictionary_items` VALUES (24, 'brand', 'others', '其他', NULL, 99, 1, '2025-09-03 19:04:31', '2025-09-03 22:29:07');
INSERT INTO `dictionary_items` VALUES (25, 'brand', 'nanbo', '南玻', NULL, 0, 1, '2025-10-31 10:23:18', '2025-10-31 10:23:18');
INSERT INTO `dictionary_items` VALUES (26, 'manufacturer', 'cd_nanbo', '成都南玻', 25, 0, 1, '2025-10-31 10:23:59', '2025-10-31 10:23:59');

-- ----------------------------
-- Table structure for glass_packages
-- ----------------------------
DROP TABLE IF EXISTS `glass_packages`;
CREATE TABLE `glass_packages`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '包号/二维码',
  `glass_type_id` int(11) NOT NULL COMMENT '原片类型ID',
  `width` decimal(10, 0) NULL DEFAULT NULL COMMENT '宽度(mm)',
  `height` decimal(10, 0) NULL DEFAULT NULL COMMENT '高度(mm)',
  `pieces` int(11) NOT NULL DEFAULT 0 COMMENT '实际片数（库存操作基准）',
  `quantity` int(11) NOT NULL DEFAULT 0 COMMENT '默认包装数量（参考值）',
  `entry_date` date NULL DEFAULT NULL COMMENT '入库日期',
  `initial_rack_id` int(11) NULL DEFAULT NULL COMMENT '起始库区ID',
  `current_rack_id` int(11) NULL DEFAULT NULL COMMENT '当前库位架ID',
  `position_order` int(11) NULL DEFAULT 1 COMMENT '在架中的位置顺序(1-x)',
  `status` enum('in_storage','in_processing','scrapped','used_up') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'in_storage' COMMENT '包状态：in_storage=库存中，in_processing=加工中，scrapped=已报废，used_up=已用完',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `current_rack_id`(`current_rack_id`) USING BTREE,
  INDEX `glass_type_id`(`glass_type_id`) USING BTREE,
  INDEX `initial_rack_id`(`initial_rack_id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 157 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '原片包信息表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of glass_packages
-- ----------------------------
INSERT INTO `glass_packages` VALUES (1, '250808', 28, 3660, 2440, 10, 0, '2025-08-30', 6, 16, 1, 'in_storage', '2025-08-30 18:09:40', '2025-08-30 18:12:43');
INSERT INTO `glass_packages` VALUES (11, 'L25081604', 2, 3660, 2440, 32, 0, '2025-08-31', 29, 29, 2, 'in_storage', '2025-08-31 22:40:57', '2025-08-31 22:40:57');
INSERT INTO `glass_packages` VALUES (31, 'C2025011', 1, 3660, 2440, 25, 25, '2025-09-02', 140, 140, 8, 'in_storage', '2025-09-02 22:19:57', '2025-09-02 22:19:57');
INSERT INTO `glass_packages` VALUES (4, 'L250831002', 27, 3660, 2600, 38, 0, '2025-08-31', 26, 26, 1, 'in_storage', '2025-08-31 13:02:37', '2025-08-31 13:02:37');
INSERT INTO `glass_packages` VALUES (5, 'L250831003', 26, 3660, 2440, 13, 0, '2025-08-31', 88, 88, 1, 'in_storage', '2025-08-31 13:02:37', '2025-08-31 18:49:44');
INSERT INTO `glass_packages` VALUES (6, 'C250831001', 28, 3660, 2440, 20, 0, '2025-08-31', 26, 6, 1, 'in_storage', '2025-08-31 13:02:37', '2025-09-05 15:47:53');
INSERT INTO `glass_packages` VALUES (7, 'C250831002', 28, 3660, 2440, 63, 0, '2025-08-31', 27, 27, 1, 'in_storage', '2025-08-31 13:02:37', '2025-08-31 13:02:37');
INSERT INTO `glass_packages` VALUES (8, 'C250831003', 28, 3660, 2440, 18, 0, '2025-08-31', 89, 89, 1, 'in_storage', '2025-08-31 13:02:37', '2025-08-31 13:02:37');
INSERT INTO `glass_packages` VALUES (9, 'C250831004', 3, 3660, 2800, 24, 0, '2025-08-31', 29, 29, 1, 'in_storage', '2025-08-31 13:02:37', '2025-08-31 13:02:37');
INSERT INTO `glass_packages` VALUES (10, 'C250831005', 3, 3660, 2440, 17, 0, '2025-08-31', 29, 29, 1, 'in_storage', '2025-08-31 13:02:37', '2025-08-31 13:02:37');
INSERT INTO `glass_packages` VALUES (30, 'C2025003', 1, 3660, 2440, 25, 25, '2025-09-02', 140, 140, 7, 'in_storage', '2025-09-02 22:19:17', '2025-09-02 22:19:17');
INSERT INTO `glass_packages` VALUES (15, 'C2025004', 1, 3660, 2880, 28, 0, '2025-09-02', 140, 140, 2, 'in_storage', '2025-09-02 21:23:20', '2025-09-02 21:23:20');
INSERT INTO `glass_packages` VALUES (16, 'C2025006', 1, 3660, 2800, 25, 25, '2025-09-02', 140, 140, 1, 'in_storage', '2025-09-02 21:23:55', '2025-09-02 21:23:55');
INSERT INTO `glass_packages` VALUES (40, 'C2025042', 70, 3660, 2440, 28, 28, '2025-09-02', 143, 143, 1, 'in_storage', '2025-09-02 22:33:52', '2025-09-02 22:33:52');
INSERT INTO `glass_packages` VALUES (39, 'C2025041', 70, 3660, 2440, 28, 28, '2025-09-02', 143, 143, 2, 'in_storage', '2025-09-02 22:33:52', '2025-09-02 22:33:52');
INSERT INTO `glass_packages` VALUES (38, 'C2025019', 1, 3660, 2800, 24, 24, '2025-09-02', 147, 147, 6, 'in_storage', '2025-09-02 22:20:51', '2025-09-02 22:20:51');
INSERT INTO `glass_packages` VALUES (37, 'C2025018', 1, 3660, 2800, 24, 24, '2025-09-02', 147, 147, 5, 'in_storage', '2025-09-02 22:20:51', '2025-09-02 22:20:51');
INSERT INTO `glass_packages` VALUES (36, 'C2025017', 1, 3660, 2800, 24, 24, '2025-09-02', 147, 147, 4, 'in_storage', '2025-09-02 22:20:51', '2025-09-02 22:20:51');
INSERT INTO `glass_packages` VALUES (35, 'C2025016', 1, 3660, 2800, 24, 24, '2025-09-02', 147, 147, 3, 'in_storage', '2025-09-02 22:20:51', '2025-09-02 22:20:51');
INSERT INTO `glass_packages` VALUES (23, 'C2025013', 1, 3660, 2880, 24, 24, '2025-09-02', 140, 140, 3, 'in_storage', '2025-09-02 22:01:38', '2025-09-02 22:01:38');
INSERT INTO `glass_packages` VALUES (34, 'C2025015', 1, 3660, 2800, 24, 24, '2025-09-02', 147, 147, 2, 'in_storage', '2025-09-02 22:20:51', '2025-09-02 22:20:51');
INSERT INTO `glass_packages` VALUES (33, 'C2025014', 1, 3660, 2440, 25, 25, '2025-09-02', 140, 140, 10, 'in_storage', '2025-09-02 22:19:57', '2025-09-02 22:19:57');
INSERT INTO `glass_packages` VALUES (32, 'C2025012', 1, 3660, 2440, 25, 25, '2025-09-02', 140, 140, 9, 'in_storage', '2025-09-02 22:19:57', '2025-09-02 22:19:57');
INSERT INTO `glass_packages` VALUES (27, 'C2025021', 1, 3660, 2440, 25, 25, '2025-09-02', 140, 140, 4, 'in_storage', '2025-09-02 22:05:10', '2025-09-02 22:05:10');
INSERT INTO `glass_packages` VALUES (28, 'C2025022', 1, 3660, 2440, 25, 25, '2025-09-02', 140, 140, 5, 'in_storage', '2025-09-02 22:05:10', '2025-09-02 22:05:10');
INSERT INTO `glass_packages` VALUES (29, 'C2025023', 1, 3660, 2440, 25, 25, '2025-09-02', 140, 140, 6, 'in_storage', '2025-09-02 22:05:10', '2025-09-02 22:05:10');
INSERT INTO `glass_packages` VALUES (41, 'C43', 70, 3660, 2440, 28, 28, '2025-09-02', 143, 143, 3, 'in_storage', '2025-09-02 22:33:52', '2025-09-02 22:33:52');
INSERT INTO `glass_packages` VALUES (42, 'C44', 70, 3660, 2440, 28, 28, '2025-09-02', 143, 143, 4, 'in_storage', '2025-09-02 22:33:52', '2025-09-02 22:33:52');
INSERT INTO `glass_packages` VALUES (43, 'C45', 70, 3660, 2440, 28, 28, '2025-09-02', 143, 143, 5, 'in_storage', '2025-09-02 22:33:52', '2025-09-02 22:33:52');
INSERT INTO `glass_packages` VALUES (44, 'C46', 70, 3660, 2440, 28, 28, '2025-09-02', 143, 143, 6, 'in_storage', '2025-09-02 22:33:52', '2025-09-02 22:33:52');
INSERT INTO `glass_packages` VALUES (45, 'C47', 70, 3660, 2440, 28, 28, '2025-09-02', 143, 143, 7, 'in_storage', '2025-09-02 22:33:52', '2025-09-02 22:33:52');
INSERT INTO `glass_packages` VALUES (46, 'C48', 70, 3660, 2440, 28, 28, '2025-09-02', 143, 143, 8, 'in_storage', '2025-09-02 22:33:52', '2025-09-02 22:33:52');
INSERT INTO `glass_packages` VALUES (47, 'C49', 70, 3660, 2440, 28, 28, '2025-09-02', 143, 143, 9, 'in_storage', '2025-09-02 22:33:52', '2025-09-02 22:33:52');
INSERT INTO `glass_packages` VALUES (48, 'C50', 70, 3660, 2440, 28, 28, '2025-09-02', 143, 143, 10, 'in_storage', '2025-09-02 22:33:52', '2025-09-02 22:33:52');
INSERT INTO `glass_packages` VALUES (49, 'C51', 70, 3660, 2440, 28, 28, '2025-09-02', 143, 143, 11, 'in_storage', '2025-09-02 22:33:52', '2025-09-02 22:33:52');
INSERT INTO `glass_packages` VALUES (50, 'C52', 70, 3660, 2440, 28, 28, '2025-09-02', 143, 143, 12, 'in_storage', '2025-09-02 22:33:52', '2025-09-02 22:33:52');
INSERT INTO `glass_packages` VALUES (51, 'C53', 70, 3660, 2440, 28, 28, '2025-09-02', 143, 143, 13, 'in_storage', '2025-09-02 22:33:52', '2025-09-02 22:33:52');
INSERT INTO `glass_packages` VALUES (52, 'C54', 70, 3660, 2440, 28, 28, '2025-09-02', 143, 143, 14, 'in_storage', '2025-09-02 22:33:52', '2025-09-02 22:33:52');
INSERT INTO `glass_packages` VALUES (53, 'C55', 70, 3660, 2440, 28, 28, '2025-09-02', 143, 143, 15, 'in_storage', '2025-09-02 22:33:52', '2025-09-02 22:33:52');
INSERT INTO `glass_packages` VALUES (54, 'D19-2', 89, 3300, 2600, 14, 23, '2025-09-03', 137, 137, 1, 'in_storage', '2025-09-03 11:17:33', '2025-09-03 11:17:33');
INSERT INTO `glass_packages` VALUES (55, 'NT1114001', 1, 3660, 2800, 24, 0, '2025-07-24', 166, 166, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (56, 'NT1114002', 67, 3660, 2740, 31, 0, '2025-08-02', 141, 141, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (57, 'NT1114003', 1, 3660, 2800, 24, 0, '2025-11-08', 168, 168, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (58, 'NT1114004', 1, 3660, 2800, 24, 0, '2025-11-08', 168, 168, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (59, 'NT1114005', 1, 3660, 2800, 24, 0, '2025-11-08', 168, 168, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (60, 'NT1114006', 1, 3660, 2800, 24, 0, '2025-11-08', 168, 168, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (61, 'NT1114007', 1, 3660, 2800, 24, 0, '2025-11-08', 168, 168, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (62, 'NT1114008', 1, 3660, 2800, 24, 0, '2025-11-08', 168, 168, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (63, 'NT1114009', 1, 3660, 2800, 5, 0, '2025-11-08', 168, 168, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (64, 'NT1114010', 1, 3660, 2800, 24, 0, '2025-11-09', 142, 142, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (65, 'NT1114011', 1, 3660, 2800, 24, 0, '2025-11-09', 142, 142, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (66, 'NT1114012', 1, 3660, 2800, 24, 0, '2025-11-09', 142, 142, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (67, 'NT1114013', 1, 3660, 2800, 24, 0, '2025-11-09', 142, 142, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (68, 'NT1114014', 1, 3660, 2800, 24, 0, '2025-11-09', 142, 142, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (69, 'NT1114015', 1, 3660, 2800, 24, 0, '2025-11-09', 142, 142, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (70, 'NT1114016', 7, 3660, 2440, 21, 0, '2025-02-18', 143, 143, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (71, 'NT1114017', 7, 3660, 2440, 26, 0, '2025-03-14', 143, 143, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (72, 'NT1114018', 67, 36690, 2740, 31, 0, '2025-10-12', 169, 169, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (73, 'NT1114019', 67, 36690, 2740, 31, 0, '2025-10-12', 169, 169, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (74, 'NT1114020', 67, 36690, 2740, 31, 0, '2025-10-12', 169, 169, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (75, 'NT1114021', 67, 36690, 2740, 31, 0, '2025-10-12', 169, 169, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (76, 'NT1114022', 67, 36690, 2740, 31, 0, '2025-10-12', 169, 169, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (77, 'NT1114023', 67, 36690, 2740, 31, 0, '2025-10-12', 169, 169, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (78, 'NT1114024', 67, 36690, 2740, 31, 0, '2025-10-12', 169, 169, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (79, 'NT1114025', 67, 36690, 2740, 31, 0, '2025-10-12', 169, 169, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (80, 'NT1114026', 11, 3660, 2680, 19, 0, '2025-11-14', 169, 169, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (81, 'NT1114031', 90, 3660, 2600, 25, 0, '2025-11-14', 171, 171, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (82, 'NT1114032', 90, 3660, 2600, 19, 0, '2025-06-29', 171, 171, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (83, 'NT1114033', 86, 3660, 2800, 28, 0, '2025-11-14', 171, 171, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (84, 'NT1114034', 86, 3660, 2800, 28, 0, '2025-11-14', 171, 171, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (85, 'NT1114036', 10, 3300, 2600, 4, 0, '2020-01-01', 145, 145, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (86, 'NT1114038', 29, 3300, 2600, 6, 0, '2020-01-01', 145, 145, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (87, 'NT1114037', 46, 3050, 2440, 1, 0, '2020-01-01', 145, 145, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (88, 'NT1114040', 1, 3660, 2440, 7, 0, '2023-04-25', 145, 145, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (89, 'NT1114042', 46, 3300, 2440, 13, 0, '2020-01-01', 145, 145, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (90, 'NT1114043', 3, 3660, 2440, 18, 0, '2023-04-25', 145, 145, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (91, 'NT1114044', 76, 3660, 2440, 29, 0, '2025-07-02', 145, 145, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (92, 'NT1114045', 76, 3660, 2440, 32, 0, '2025-07-02', 145, 145, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (93, 'NT1114046', 75, 3300, 2740, 28, 0, '2025-11-14', 145, 145, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (94, 'NT1114047', 73, 3660, 2440, 26, 0, '2025-11-14', 172, 172, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (95, 'NT1114048', 74, 3300, 2440, 31, 0, '2025-11-14', 172, 172, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (96, 'NT1114049', 11, 3300, 2440, 30, 0, '2024-12-12', 172, 172, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (97, 'NT1114050', 2, 3300, 2740, 32, 0, '2025-11-02', 146, 146, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (98, 'NT1114051', 2, 3300, 2740, 32, 0, '2025-11-02', 146, 146, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (99, 'NT1114052', 2, 3300, 2740, 32, 0, '2025-11-02', 146, 146, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (100, 'NT1114053', 2, 3300, 2740, 32, 0, '2025-11-02', 146, 146, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (101, 'NT1114054', 2, 3300, 2740, 32, 0, '2025-11-02', 146, 146, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (102, 'NT1114055', 74, 3300, 2440, 36, 0, '2023-06-02', 173, 173, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (103, 'NT1114057', 68, 3660, 2440, 36, 0, '2025-09-18', 147, 147, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (104, 'NT1114058', 68, 3660, 2440, 8, 0, '2025-09-18', 147, 147, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (105, 'NT1114059', 68, 3660, 2440, 23, 0, '2025-10-17', 147, 147, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (106, 'NT1114060', 2, 3300, 2740, 32, 0, '2025-11-02', 174, 174, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (107, 'NT1114061', 2, 3300, 2740, 32, 0, '2025-11-02', 174, 174, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (108, 'NT1114062', 2, 3300, 2740, 32, 0, '2025-11-02', 174, 174, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (109, 'NT1114063', 2, 3300, 2740, 32, 0, '2025-11-02', 174, 174, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (110, 'NT1114064', 2, 3300, 2740, 32, 0, '2025-11-02', 174, 174, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (111, 'NT1114065', 2, 3300, 2740, 32, 0, '2025-11-02', 174, 174, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (112, 'NT1114066', 2, 3300, 2740, 13, 0, '2025-07-19', 174, 174, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (113, 'NT1114067', 70, 3660, 2600, 30, 0, '2025-07-04', 148, 148, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (114, 'NT1114068', 70, 3660, 2600, 29, 0, '2025-07-04', 148, 148, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (115, 'NT1114069', 70, 3660, 2600, 33, 0, '2025-07-06', 148, 148, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (116, 'NT1114070', 70, 3660, 2600, 3, 0, '2025-07-06', 148, 148, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (117, 'NT1114071', 71, 3660, 2440, 28, 0, '2025-11-05', 174, 174, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (118, 'NT1114072', 71, 3660, 2440, 20, 0, '2025-11-05', 174, 174, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (119, 'NT1114075', 11, 4880, 2600, 19, 0, '2025-11-14', 176, 176, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (120, 'NT1114077', 9, 4880, 3050, 16, 0, '2025-11-14', 150, 150, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (121, 'NT1114078', 9, 4880, 3050, 16, 0, '2025-11-14', 150, 150, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (122, 'NT1114079', 9, 4880, 3050, 16, 0, '2025-11-14', 150, 150, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (123, 'NT1114080', 9, 4880, 3050, 16, 0, '2025-11-14', 150, 150, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (124, 'NT1114081', 9, 4880, 3050, 16, 0, '2025-11-14', 150, 150, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (125, 'NT1114082', 9, 4880, 3050, 16, 0, '2025-11-14', 150, 150, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (126, 'NT1114083', 9, 4880, 3050, 16, 0, '2025-11-14', 150, 150, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (127, 'NT1114084', 9, 4880, 2600, 16, 0, '2025-11-14', 150, 150, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (128, 'NT1114087', 75, 3660, 2440, 11, 0, '2025-11-14', 177, 177, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (129, 'NT1114088', 10, 3300, 2740, 7, 0, '2025-03-06', 151, 151, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-15 19:25:19');
INSERT INTO `glass_packages` VALUES (130, 'NT1114089', 7, 3660, 2440, 0, 0, '2025-11-14', 178, 133, 1, 'used_up', '2025-11-14 20:55:48', '2025-11-15 19:23:24');
INSERT INTO `glass_packages` VALUES (131, 'NT1114097', 4, 3660, 2620, 17, 0, '2024-12-28', 179, 179, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (132, 'NT1114098', 75, 3660, 2440, 31, 0, '2025-05-08', 179, 179, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (133, 'NT1114099', 75, 3660, 2440, 31, 0, '2025-05-08', 179, 179, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (134, 'NT1114100', 75, 3660, 2440, 3, 0, '2025-05-08', 179, 179, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (135, 'NT1114106', 2, 3300, 2600, 5, 0, '2020-01-01', 180, 180, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (136, 'NT1114111', 75, 3660, 2440, 31, 0, '2025-05-06', 180, 180, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (137, 'NT1114112', 75, 3660, 2440, 8, 0, '2025-05-06', 180, 180, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (138, 'NT1114117', 72, 3660, 2440, 14, 0, '2025-11-14', 154, 154, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (139, 'NT1114119', 11, 4880, 3050, 16, 0, '2025-04-10', 182, 182, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (140, 'NT1114120', 11, 4880, 3050, 4, 0, '2025-04-10', 182, 182, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (141, 'NT1114121', 4, 4880, 2440, 13, 0, '2025-11-14', 182, 182, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (142, 'NT1114122', 9, 4880, 3050, 16, 0, '2025-02-12', 156, 156, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (143, 'NT1114123', 9, 4880, 3050, 16, 0, '2025-03-01', 156, 156, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (144, 'NT1114124', 9, 4880, 3050, 16, 0, '2025-03-01', 156, 156, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (145, 'NT1114125', 9, 4880, 3050, 16, 0, '2025-03-01', 156, 156, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (146, 'NT1114126', 6, 3660, 2600, 24, 0, '2025-11-14', 157, 157, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (147, 'NT1114129', 84, 3660, 2440, 30, 0, '2020-01-01', 165, 165, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (148, 'NT1114131', 5, 3660, 2800, 22, 0, '2025-01-30', 165, 165, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (149, 'NT1114132', 5, 3660, 2800, 5, 0, '2025-01-30', 165, 165, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (150, 'NT1114133', 1, 3660, 2440, 18, 0, '2025-11-14', 137, 137, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (151, 'NT1114135', 67, 3660, 2740, 19, 0, '2025-11-14', 137, 137, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (152, 'NT1114136', 67, 3660, 2740, 4, 0, '2025-11-14', 137, 137, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (153, 'NT1114137', 1, 3660, 2800, 10, 0, '2025-11-14', 137, 137, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (154, 'NT1114138', 90, 4880, 3050, 3, 0, '2025-11-14', 137, 137, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (155, 'NT1114139', 9, 4880, 3050, 2, 0, '2025-11-14', 137, 137, 1, 'in_storage', '2025-11-14 20:55:48', '2025-11-14 20:55:48');
INSERT INTO `glass_packages` VALUES (156, 'NT1114140', 89, 4880, 3050, 4, 0, '2025-11-14', 135, 133, 1, 'in_processing', '2025-11-14 20:55:48', '2025-11-14 20:57:40');

-- ----------------------------
-- Table structure for glass_types
-- ----------------------------
DROP TABLE IF EXISTS `glass_types`;
CREATE TABLE `glass_types`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `custom_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '原片ID（自定义，唯一，采购使用）',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '原片名称（完整名称）',
  `short_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '原片简称（如：5白、4白、5白南玻）',
  `finance_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '财务核算名',
  `product_series` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '原片商色系（如：5lowe XETB0160）',
  `brand` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '原片品牌（如：信义、台玻、金晶、旗滨）',
  `manufacturer` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '原片生产商（如：重庆信义、德阳信义、成都台玻）',
  `color` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '原片颜色（如：白玻、玉沙、LOWE、超白等）',
  `thickness` decimal(10, 0) NULL DEFAULT NULL COMMENT '原片厚度(mm)（如：4、5、6、8、10、12、15、19）',
  `silver_layers` enum('单银','双银','三银','无银','') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '银层类型',
  `substrate` enum('普白','超白','') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '基片类型',
  `transmittance` enum('高透','中透','低透','') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '透光性',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态：1启用，0禁用',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `custom_id`(`custom_id`) USING BTREE,
  INDEX `idx_brand`(`brand`) USING BTREE,
  INDEX `idx_color`(`color`) USING BTREE,
  INDEX `idx_custom_id`(`custom_id`) USING BTREE,
  INDEX `idx_manufacturer`(`manufacturer`) USING BTREE,
  INDEX `idx_short_name`(`short_name`) USING BTREE,
  INDEX `idx_silver_layers`(`silver_layers`) USING BTREE,
  INDEX `idx_substrate`(`substrate`) USING BTREE,
  INDEX `idx_transmittance`(`transmittance`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 91 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '原片基础信息表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of glass_types
-- ----------------------------
INSERT INTO `glass_types` VALUES (1, '5CTG', '5mm白玻台玻', '5白台玻', '5白', 'CTG', '台玻', '成都台玻', '白玻', 5, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (2, '5XETB0160', '5mmXETB0160', '5XETB0160', '5L', 'XETB0160', '信义', '德阳信义', 'LOWE', 5, '单银', '普白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:17:33');
INSERT INTO `glass_types` VALUES (3, '5CXY', '5mm白玻信义', '5白', '5白', 'CXY', '信义', '德阳信义', '白玻', 5, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (4, '6SCSG', '6mm南玻超白玻', '6超白南玻', '6超白', 'SCSG', '南玻', '成都南玻', '超白', 6, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (5, '6SCXY', '6mm信义超白玻', '6超白', '6超白', 'SCXY', '信义', '德阳信义', '超白', 6, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (6, '6CSG', '6mm南玻白玻', '6白南玻', '6白', 'CSG', '南玻', '成都南玻', '白玻', 6, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (7, '6SUPERC1', '6mmSUPER-C1', '6C1', '6L', 'SUPER-C1', '南玻', '成都南玻', 'LOWE', 6, '单银', '普白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (8, '6XETB0060', '6mmXETB0060', '6XETB0060', '6L', 'XETB0060', '信义', '德阳信义', 'LOWE', 6, '单银', '超白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (9, '6XETB0160', '6mmXETB0160', '6XETB0160', '6L', 'XETB0160', '信义', '德阳信义', 'LOWE', 6, '单银', '普白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (10, '6XETN0180', '6mmXETN0180', '6XETN0180', '6L80', 'XETN0180', '信义', '德阳信义', 'LOWE', 6, '单银', '普白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (11, '6CXY', '6mm信义白玻', '6白', '6白', 'CXY', '信义', '德阳信义', '白玻', 6, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (12, '8SCSG', '8mm南玻超白玻', '8超白南玻', '8超白', 'SCSG', '南玻', '成都南玻', '超白', 8, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (13, '8SCXY', '8mm信义超白玻', '8超白', '8超白', 'SCXY', '信义', '德阳信义', '超白', 8, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (14, '8CSG', '8mm南玻白玻', '8白南玻', '8白', 'CSG', '南玻', '成都南玻', '白玻', 8, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (15, '8SUPER3', '8mmSUPER3', '8S3', '8S3', 'SUPER3', '南玻', '成都南玻', 'LOWE', 8, '单银', '普白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (16, '8SUPERC1', '8mmSUPER-C1', '8C1', '8C1', 'SUPER-C1', '南玻', '成都南玻', 'LOWE', 8, '单银', '普白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (17, '8TD178', '8mmTD178', '8TD178', '8L78', 'TD178', '南玻', '成都南玻', 'LOWE', 8, '双银', '普白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (18, '8XDTB0061', '8mmXDTB0061', '8XDTB0061', '8L双银', 'XDTB0061', '信义', '德阳信义', 'LOWE', 8, '双银', '超白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (19, '8XDTB0161', '8mmXDTB0161', '8XDTB0161', '8L双银', 'XDTB0161', '信义', '德阳信义', 'LOWE', 8, '双银', '普白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (20, '8XDTG0156', '8mmXDTG0156', '8XDTG0156', '8L双银', 'XDTG0156', '信义', '德阳信义', 'LOWE', 8, '双银', '普白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (21, '8XDTN0082', '8mmXDTN0082', '8XDTN0082', '8L80双银', 'XDTN0082', '信义', '德阳信义', 'LOWE', 8, '双银', '超白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (22, '8XDTN0182', '8mmXDTN0182', '8XDTN0182', '8L80双银', 'XDTN0182', '信义', '德阳信义', 'LOWE', 8, '双银', '普白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (23, '8XETB0060', '8mmXETB0060', '8XETB0060', '8L', 'XETB0060', '信义', '德阳信义', 'LOWE', 8, '单银', '超白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (24, '8XETB0160', '8mmXETB0160', '8XETB0160', '8L', 'XETB0160', '信义', '德阳信义', 'LOWE', 8, '单银', '普白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (25, '8XETB0180', '8mmXETB0180', '8XETB0180', '8L80', 'XETB0180', '信义', '德阳信义', 'LOWE', 8, '单银', '普白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (26, '8XETN0080', '8mmXETN0080', '8XETN0080', '8L80', 'XETN0080', '信义', '德阳信义', 'LOWE', 8, '单银', '超白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (27, '8XETN0180', '8mmXETN0180', '8XETN0180', '8L80', 'XETN0180', '信义', '德阳信义', 'LOWE', 8, '单银', '普白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (28, '8CXY', '8mm信义白玻', '8白', '8白', 'CXY', '信义', '德阳信义', '白玻', 8, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (29, '10SCSG', '10mm南玻超白玻', '10超白南玻', '10超白', 'SCSG', '南玻', '成都南玻', '超白', 10, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (30, '10SCXY', '10mm信义超白玻', '10超白', '10超白', 'SCXY', '信义', '德阳信义', '超白', 10, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (31, '10CSG', '10mm南玻白玻', '10白南玻', '10白', 'CSG', '南玻', '成都南玻', '白玻', 10, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (32, '10SUPER3', '10mmsuper3', '10S3', '10S3', 'SUPER3', '南玻', '成都南玻', 'LOWE', 10, '单银', '普白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (33, '10SUPERC1', '10mmSUPER-C1', '10C1', '10C1', 'SUPER-C1', '南玻', '成都南玻', 'LOWE', 10, '单银', '普白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (34, '10TD178', '10mmTD178', '10TD178', '10L78', 'TD178', '南玻', '成都南玻', 'LOWE', 10, '双银', '普白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (35, '10XDTB0061', '10mmXDTB0061', '10XDTB0061', '10L双银', 'XDTB0061', '信义', '德阳信义', 'LOWE', 10, '双银', '超白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (36, '10XDTB0161', '10mmXDTB0161', '10XDTB0161', '10L双银', 'XDTB0161', '信义', '德阳信义', 'LOWE', 10, '双银', '普白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (37, '10XDTN0082', '10mmXDTN0082', '10XDTN0082', '10L80双银', 'XDTN0082', '信义', '德阳信义', 'LOWE', 10, '双银', '超白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (38, '10XDTN0182', '10mmXDTN0182', '10XDTN0182', '10L80双银', 'XDTN0182', '信义', '德阳信义', 'LOWE', 10, '双银', '普白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (39, '10XETB0060', '10mmXETB0060', '10XETB0060', '10L', 'XETB0060', '信义', '德阳信义', 'LOWE', 10, '单银', '超白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (40, '10XETB0140', '10mmXETB0140', '10XETB0140', '10L40', 'XETB0140', '信义', '德阳信义', 'LOWE', 10, '单银', '普白', '低透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (41, '10XETB0160', '10mmXETB0160', '10XETB0160', '10L', 'XETB0160', '信义', '德阳信义', 'LOWE', 10, '单银', '普白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (42, '10XETB0180', '10mmXETB0180', '10XETB0180', '10L80', 'XETB0180', '信义', '德阳信义', 'LOWE', 10, '单银', '普白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (43, '10XETG0140', '10mmXETG0140', '10XETG0140', '10L40', 'XETG0140', '信义', '德阳信义', 'LOWE', 10, '单银', '普白', '低透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (44, '10XETN0080', '10mmXETN0080', '10XETN0080', '10L80', 'XETN0080', '信义', '德阳信义', 'LOWE', 10, '单银', '超白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (45, '10XETN0180', '10mmXETN0180', '10XETN0180', '10L80', 'XETN0180', '信义', '德阳信义', 'LOWE', 10, '单银', '普白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (46, '10CXY', '10mm信义白玻', '10白', '10白', 'CXY', '信义', '德阳信义', '白玻', 10, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (47, '12CS3', '12mm超白SUPER3', '12超白S3', '12超白S3', 'CSUPER3', '南玻', '成都南玻', 'LOWE', 12, '单银', '超白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (48, '12CSC1', '12mm超白SUPER-C1', '12超白C1', '12超白C1', 'CSUPER-C1', '南玻', '成都南玻', 'LOWE', 12, '单银', '超白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (49, '12SCSG', '12mm南玻超白玻', '12超白南玻', '12超白', 'SCSG', '南玻', '成都南玻', '超白', 12, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (50, '12SCXY', '12mm信义超白玻', '12超白', '12超白', 'SCXY', '信义', '德阳信义', '超白', 12, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (51, '12CSG', '12mm南玻白玻', '12白南玻', '12白', 'CSG', '南玻', '成都南玻', '白玻', 12, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (52, '12SUPER3', '12mmSUPER3', '12S3', '12S3', 'SUPER3', '南玻', '成都南玻', 'LOWE', 12, '单银', '普白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (53, '12SUPERC1', '12mmSUPER-C1', '12C1', '12C1', 'SUPER-C1', '南玻', '成都南玻', 'LOWE', 12, '单银', '普白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (54, '12TD178', '12mmTD178', '12TD178', '12L78', 'TD178', '南玻', '成都南玻', 'LOWE', 12, '双银', '普白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (55, '12XDTB0061', '12mmXDTB0061', '12XDTB0061', '12L双银', 'XDTB0061', '信义', '德阳信义', 'LOWE', 12, '双银', '超白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (56, '12XDTB0161', '12mmXDTB0161', '12XDTB0161', '12L双银', 'XDTB0161', '信义', '德阳信义', 'LOWE', 12, '双银', '普白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (57, '12XDTN0082', '12mmXDTN0082', '12XDTN0082', '12L80双银', 'XDTN0082', '信义', '德阳信义', 'LOWE', 12, '双银', '超白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (58, '12XETB0060', '12mmXETB0060', '12XETB0060', '12L', 'XETB0060', '信义', '德阳信义', 'LOWE', 12, '单银', '超白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (59, '12XETB0160', '12mmXETB0160', '12XETB0160', '12L', 'XETB0160', '信义', '德阳信义', 'LOWE', 12, '单银', '普白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (60, '12XETB0180', '12mmXETB0180', '12XETB0180', '12L80', 'XETB0180', '信义', '德阳信义', 'LOWE', 12, '单银', '普白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (61, '12XETN0080', '12mmXETN0080', '12XETN0080', '12L80', 'XETN0080', '信义', '德阳信义', 'LOWE', 12, '单银', '超白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (62, '12XETN0180', '12mmXETN0180', '12XETN0180', '12L80', 'XETN0180', '信义', '德阳信义', 'LOWE', 12, '单银', '普白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (63, '12XSTB0160', '12mmXSTB0160', '12XSTB0160', '12热反射', 'XSTB0160', '信义', '德阳信义', '热反射', 12, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (64, '12CXY', '12mm信义白玻', '12白', '12白', 'CXY', '信义', '德阳信义', '白玻', 12, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (65, '15SCXY', '15mm信义超白', '15超白', '15超白', 'SCXY', '信义', '德阳信义', '超白', 15, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (66, '15XDTB0061', '15mmXDTB0061', '15XDTB0061', '15L双银', 'XDTB0061', '信义', '德阳信义', 'LOWE', 15, '双银', '超白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (67, '4CTG', '4mm台玻白玻', '4白', '4白', 'CTG', '台玻', '成都台玻', '白玻', 4, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (68, '4YSTG', '4mm台玻玉砂', '4玉', '4玉', 'YSTG', '台玻', '成都台玻', '玉砂', 4, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (69, '4CBXL', '4mm超白香梨', '4香梨', '4香梨', 'CBXL', '江西', '', '压花', 4, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (70, '5CSG', '5mm白玻南玻', '5白南玻', '5白', 'CSG', '南玻', '成都南玻', '白玻', 5, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (71, '5YSTG', '5mm玉砂台玻', '5玉台玻', '5玉', 'YSTG', '台玻', '成都台玻', '玉砂', 5, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (72, '6YSTG', '6mm玉砂台玻', '6玉台玻', '6玉', 'YSTG', '台玻', '成都台玻', '玉砂', 6, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (73, '5XETB0060', '5mmXETB0060', '5超白LOWE', '5L', 'XETB0060', '信义', '德阳信义', 'LOWE', 5, '单银', '超白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (74, '5XDTG0156', '5mmXDTG0156', '5XDTG0156', '5L双银', 'XDTG0156', '信义', '德阳信义', 'LOWE', 5, '双银', '普白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (75, '5SUPER3', '5mmSUPER3', '5S3', '5L', 'SUPER3', '南玻', '成都南玻', 'LOWE', 5, '单银', '普白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (76, '5TD160', '5mmTD160', '5TD160', '5L', 'TD160', '南玻', '成都南玻', 'LOWE', 5, '双银', '普白', '中透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (77, '5TD178', '5mmTD178', '5TD178', '5L', 'TD178', '南玻', '成都南玻', 'LOWE', 5, '双银', '普白', '高透', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (78, '5CH', '5mm长虹', '5CH', '5长虹', '长虹', '东兴', '', '长虹', 5, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (79, '5SCH', '5mm超白长虹', '5SCH', '5超白长虹', '超白长虹', '南玻', '成都南玻', '长虹', 5, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (80, '8CH', '8mm长虹', '8CH', '8长虹', '长虹', '东兴', '', '长虹', 8, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (81, '8SCH', '8mm超白长虹', '8SCH', '8超白长虹', '超白长虹', '南玻', '成都南玻', '长虹', 8, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (82, '5EUG', '5mm欧洲灰', '5EUG', '5欧洲灰', '欧洲灰', '旗滨', '', '灰玻', 5, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (83, '8EUG', '8mm欧洲灰', '8EUG', '8欧洲灰', '欧洲灰', '中玻', '', '灰玻', 8, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (84, '5FordBlue', '5mm福特蓝有色', '5FB', '5福特蓝有色', '福特蓝', '中玻', '', '色玻', 5, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (85, '5FBSUNE', '5mm福特蓝镀膜', '5FBE', '5福特蓝镀膜', '福特蓝', '金晶', '', '镀膜', 5, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (86, '5SCXY', '5mm超白信义', '5SCXY', '5超白', 'SCXY', '信义', '', '超白', 5, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (87, '5SCSG', '5mm超白南玻', '5SCSG', '5超白', 'SCSG', '南玻', '', '超白', 5, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (88, '5DRCH', '5mm单绒', '5DRCH', '5单绒', '单绒长虹', '金晶', '', '单绒', 5, '', '', '', 1, '2025-08-27 16:07:37', '2025-08-27 16:07:37');
INSERT INTO `glass_types` VALUES (89, '8XSTB0160', '8mm热反射XSTB0160', '8热反射', '8热反射', 'XSTB0160', '信义', '德阳信义', '镀膜', 8, NULL, NULL, NULL, 1, '2025-09-03 11:17:02', '2025-09-03 11:17:02');
INSERT INTO `glass_types` VALUES (90, '6CTG', '6mm白玻台玻', '6白台玻', '6白', '白玻', '台玻', '成都台玻', '白玻', 6, NULL, NULL, NULL, 1, '2025-11-14 18:19:42', '2025-11-14 18:19:42');

-- ----------------------------
-- Table structure for inventory_check_cache
-- ----------------------------
DROP TABLE IF EXISTS `inventory_check_cache`;
CREATE TABLE `inventory_check_cache`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL COMMENT '盘点任务ID',
  `package_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '包号',
  `package_id` int(11) NULL DEFAULT NULL COMMENT '包ID（关联查询得到）',
  `system_quantity` int(11) NOT NULL DEFAULT 0 COMMENT '系统数量',
  `check_quantity` int(11) NOT NULL DEFAULT 0 COMMENT '盘点数量',
  `difference` int(11) NOT NULL DEFAULT 0 COMMENT '差异数量 = 盘点数量 - 系统数量',
  `rack_id` int(11) NULL DEFAULT NULL COMMENT '盘点时的库位架ID',
  `check_method` enum('pda_scan','manual_input','excel_import') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual_input' COMMENT '盘点方式',
  `check_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '盘点时间',
  `operator_id` int(11) NULL DEFAULT NULL COMMENT '操作员ID',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注信息',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_task_package`(`task_id`, `package_code`) USING BTREE,
  INDEX `idx_package_code`(`package_code`) USING BTREE,
  INDEX `idx_task_id`(`task_id`) USING BTREE,
  INDEX `idx_check_cache_task_status`(`task_id`, `check_quantity`, `system_quantity`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '盘点明细缓存表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of inventory_check_cache
-- ----------------------------

-- ----------------------------
-- Table structure for inventory_check_results
-- ----------------------------
DROP TABLE IF EXISTS `inventory_check_results`;
CREATE TABLE `inventory_check_results`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_id` int(11) NOT NULL COMMENT '盘点任务ID',
  `glass_type_id` int(11) NULL DEFAULT NULL COMMENT '原片类型ID',
  `total_system_quantity` int(11) NOT NULL DEFAULT 0 COMMENT '系统总数',
  `total_check_quantity` int(11) NOT NULL DEFAULT 0 COMMENT '盘点总数',
  `total_difference` int(11) NOT NULL DEFAULT 0 COMMENT '总差异',
  `profit_packages` int(11) NOT NULL DEFAULT 0 COMMENT '盘盈包数',
  `loss_packages` int(11) NOT NULL DEFAULT 0 COMMENT '盘亏包数',
  `normal_packages` int(11) NOT NULL DEFAULT 0 COMMENT '正常包数',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_task_glass`(`task_id`, `glass_type_id`) USING BTREE,
  INDEX `idx_task_id`(`task_id`) USING BTREE,
  INDEX `idx_results_task_glass`(`task_id`, `glass_type_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '盘点结果汇总表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of inventory_check_results
-- ----------------------------

-- ----------------------------
-- Table structure for inventory_check_settings
-- ----------------------------
DROP TABLE IF EXISTS `inventory_check_settings`;
CREATE TABLE `inventory_check_settings`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `base_id` int(11) NOT NULL COMMENT '基地ID',
  `auto_task_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '自动任务编号前缀',
  `check_frequency` enum('daily','weekly','monthly','quarterly','yearly','manual') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual' COMMENT '盘点频率',
  `tolerance_percent` decimal(5, 2) NOT NULL DEFAULT 5 COMMENT '容差百分比',
  `require_approval` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否需要审批',
  `approval_role` enum('admin','manager') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin' COMMENT '审批角色',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_base`(`base_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '盘点配置表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of inventory_check_settings
-- ----------------------------

-- ----------------------------
-- Table structure for inventory_check_tasks
-- ----------------------------
DROP TABLE IF EXISTS `inventory_check_tasks`;
CREATE TABLE `inventory_check_tasks`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '盘点任务名称',
  `base_id` int(11) NOT NULL COMMENT '盘点基地ID',
  `task_type` enum('full','partial','random') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full' COMMENT '盘点类型：full=全盘，partial=部分盘点，random=抽盘',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '盘点说明',
  `status` enum('created','in_progress','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'created' COMMENT '任务状态',
  `created_by` int(11) NOT NULL COMMENT '创建人ID',
  `started_at` timestamp NULL DEFAULT NULL COMMENT '开始时间',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT '完成时间',
  `total_packages` int(11) NOT NULL DEFAULT 0 COMMENT '应盘包总数',
  `checked_packages` int(11) NOT NULL DEFAULT 0 COMMENT '已盘包数量',
  `difference_count` int(11) NOT NULL DEFAULT 0 COMMENT '差异数量',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_tasks_base_created`(`base_id`, `created_at`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '盘点任务表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of inventory_check_tasks
-- ----------------------------

-- ----------------------------
-- Table structure for inventory_operation_records
-- ----------------------------
DROP TABLE IF EXISTS `inventory_operation_records`;
CREATE TABLE `inventory_operation_records`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `record_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '记录单号（自动生成）',
  `operation_type` enum('purchase_in','usage_out','partial_usage','return_in','scrap','check_in','check_out') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '操作类型',
  `package_id` int(11) NOT NULL COMMENT '原片包ID',
  `glass_type_id` int(11) NOT NULL COMMENT '原片类型ID',
  `base_id` int(11) NOT NULL COMMENT '操作基地ID',
  `operation_quantity` int(11) NOT NULL COMMENT '操作数量',
  `before_quantity` int(11) NOT NULL COMMENT '操作前数量',
  `after_quantity` int(11) NOT NULL COMMENT '操作后数量',
  `from_rack_id` int(11) NULL DEFAULT NULL COMMENT '来源库位架ID',
  `to_rack_id` int(11) NULL DEFAULT NULL COMMENT '目标库位架ID',
  `unit_area` decimal(10, 2) NULL DEFAULT NULL COMMENT '单片面积（平方米）',
  `total_area` decimal(10, 2) NULL DEFAULT NULL COMMENT '操作总面积',
  `operator_id` int(11) NOT NULL COMMENT '操作员ID',
  `operation_date` date NOT NULL COMMENT '操作日期',
  `operation_time` time NOT NULL DEFAULT '08:00:00' COMMENT '操作时间',
  `status` enum('pending','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'completed' COMMENT '记录状态',
  `scrap_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '报废原因（报废操作必填）',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注信息',
  `related_record_id` int(11) NULL DEFAULT NULL COMMENT '关联记录ID（如归还时关联原领用记录）',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `record_no`(`record_no`) USING BTREE,
  INDEX `from_rack_id`(`from_rack_id`) USING BTREE,
  INDEX `idx_base_date`(`base_id`, `operation_date`) USING BTREE,
  INDEX `idx_glass_type`(`glass_type_id`) USING BTREE,
  INDEX `idx_operation_time`(`operation_date`, `operation_time`) USING BTREE,
  INDEX `idx_operator`(`operator_id`) USING BTREE,
  INDEX `idx_package_operation`(`package_id`, `operation_type`) USING BTREE,
  INDEX `idx_record_no`(`record_no`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `related_record_id`(`related_record_id`) USING BTREE,
  INDEX `to_rack_id`(`to_rack_id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '库存操作记录表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of inventory_operation_records
-- ----------------------------

-- ----------------------------
-- Table structure for inventory_transactions
-- ----------------------------
DROP TABLE IF EXISTS `inventory_transactions`;
CREATE TABLE `inventory_transactions`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `package_id` int(11) NOT NULL COMMENT '原片包ID',
  `transaction_type` enum('purchase_in','usage_out','return_in','scrap','partial_usage','location_adjust','check_in','check_out') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '交易类型：采购入库、领用出库、归还入库、报废、部分领用、基地流转、盘盈入库、盘亏出库',
  `from_rack_id` int(11) NULL DEFAULT NULL COMMENT '来源库位架ID',
  `to_rack_id` int(11) NULL DEFAULT NULL COMMENT '目标库位架ID',
  `quantity` int(11) NOT NULL COMMENT '数量',
  `operator_id` int(11) NULL DEFAULT NULL COMMENT '操作员ID',
  `scrap_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '报废原因',
  `transaction_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '交易时间',
  `actual_usage` int(11) NULL DEFAULT 0 COMMENT '实际领用量',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注信息',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `from_rack_id`(`from_rack_id`) USING BTREE,
  INDEX `package_id`(`package_id`) USING BTREE,
  INDEX `to_rack_id`(`to_rack_id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 15 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '库存流转记录表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of inventory_transactions
-- ----------------------------
INSERT INTO `inventory_transactions` VALUES (1, 1, 'location_adjust', 6, 11, 25, 3, NULL, '2025-08-30 18:11:12', 0, '', '2025-08-30 18:11:12');
INSERT INTO `inventory_transactions` VALUES (2, 1, 'usage_out', 11, 7, 25, 3, NULL, '2025-08-30 18:12:16', 0, NULL, '2025-08-30 18:12:16');
INSERT INTO `inventory_transactions` VALUES (3, 1, 'return_in', 7, 16, 10, 3, NULL, '2025-08-30 18:12:43', 15, '', '2025-08-30 18:12:43');
INSERT INTO `inventory_transactions` VALUES (4, 6, 'usage_out', 26, 7, 52, 2, NULL, '2025-08-31 18:48:12', 0, NULL, '2025-08-31 18:48:12');
INSERT INTO `inventory_transactions` VALUES (5, 6, 'return_in', 7, 14, 25, 2, NULL, '2025-08-31 18:49:02', 27, '', '2025-08-31 18:49:02');
INSERT INTO `inventory_transactions` VALUES (6, 5, 'partial_usage', 88, 8, 1, 2, NULL, '2025-08-31 18:49:44', 1, NULL, '2025-08-31 18:49:44');
INSERT INTO `inventory_transactions` VALUES (7, 6, 'usage_out', 14, 7, 25, 5, NULL, '2025-09-05 14:27:14', 0, NULL, '2025-09-05 14:27:14');
INSERT INTO `inventory_transactions` VALUES (8, 6, 'return_in', 7, 15, 20, 3, NULL, '2025-09-05 15:41:14', 5, '', '2025-09-05 15:41:14');
INSERT INTO `inventory_transactions` VALUES (9, 6, 'location_adjust', 15, 4, 20, 3, NULL, '2025-09-05 15:45:26', 0, '[基地间转移] 信义基地 → 新丰基地', '2025-09-05 15:45:26');
INSERT INTO `inventory_transactions` VALUES (10, 6, 'location_adjust', 4, 152, 20, 5, NULL, '2025-09-05 15:46:08', 0, '', '2025-09-05 15:46:08');
INSERT INTO `inventory_transactions` VALUES (11, 6, 'location_adjust', 152, 6, 20, 5, NULL, '2025-09-05 15:47:53', 0, '[基地间转移] 新丰基地 → 信义基地', '2025-09-05 15:47:53');
INSERT INTO `inventory_transactions` VALUES (12, 156, 'usage_out', 137, 133, 4, 5, NULL, '2025-11-14 20:57:40', 0, '', '2025-11-14 20:57:40');
INSERT INTO `inventory_transactions` VALUES (13, 130, 'partial_usage', 178, 133, 23, 6, NULL, '2025-11-15 19:23:24', 23, ' (完全使用)', '2025-11-15 19:23:24');
INSERT INTO `inventory_transactions` VALUES (14, 129, 'partial_usage', 151, 134, 15, 6, NULL, '2025-11-15 19:25:19', 15, '', '2025-11-15 19:25:19');

-- ----------------------------
-- Table structure for settings
-- ----------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '设置键',
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '设置值',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '设置描述',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `setting_key`(`setting_key`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '系统设置表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of settings
-- ----------------------------
INSERT INTO `settings` VALUES (1, 'system_name', '玻璃原片管理系统', '系统名称', '2025-08-13 13:39:38', '2025-08-13 13:42:00');
INSERT INTO `settings` VALUES (2, 'system_version', '1.0.0', '系统版本', '2025-08-13 13:39:38', '2025-08-13 13:39:38');
INSERT INTO `settings` VALUES (3, 'maintenance_mode', '0', '维护模式：1开启，0关闭', '2025-08-13 13:39:38', '2025-08-17 16:44:46');
INSERT INTO `settings` VALUES (4, 'session_timeout', '30', '会话超时时间（分钟）', '2025-08-13 13:39:38', '2025-08-13 13:39:38');
INSERT INTO `settings` VALUES (5, 'backup_retention_days', '30', '备份文件保留天数', '2025-08-13 13:39:38', '2025-08-13 13:39:38');
INSERT INTO `settings` VALUES (6, 'log_retention_days', '90', '日志保留天数', '2025-08-13 13:39:38', '2025-08-13 13:39:38');

-- ----------------------------
-- Table structure for storage_racks
-- ----------------------------
DROP TABLE IF EXISTS `storage_racks`;
CREATE TABLE `storage_racks`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `base_id` int(11) NOT NULL COMMENT '所属基地ID',
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '库位架编码',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '库位架名称',
  `area_type` enum('storage','processing','scrap','temporary') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'storage' COMMENT '区域类型：库存区、加工区、报废区、临时区',
  `capacity` int(11) NULL DEFAULT NULL COMMENT '容量',
  `status` enum('normal','maintenance','full') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'normal' COMMENT '状态：正常、维护中、已满',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `base_id`(`base_id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 193 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '库位架信息表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of storage_racks
-- ----------------------------
INSERT INTO `storage_racks` VALUES (1, 2, 'XF-B-废片架', '废片架', 'scrap', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (2, 3, 'JY-B-废片架', '废片架', 'scrap', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (3, 1, 'XY-B-废片架', '废片架', 'scrap', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (4, 2, 'XF-T-临时区', '临时区', 'temporary', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (5, 3, 'JY-T-临时区', '临时区', 'temporary', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (6, 1, 'XY-T-临时区', '临时区', 'temporary', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (7, 1, 'XY-P-A1', 'A1', 'processing', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (8, 1, 'XY-P-B1', 'B1', 'processing', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (9, 1, 'XY-P-A2', 'A2', 'processing', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (10, 1, 'XY-P-B2', 'B2', 'processing', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (11, 1, 'XY-N-1A', '1A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (12, 1, 'XY-N-2A', '2A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (13, 1, 'XY-N-3A', '3A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (14, 1, 'XY-N-4A', '4A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (15, 1, 'XY-N-5A', '5A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (16, 1, 'XY-N-6A', '6A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (17, 1, 'XY-N-7A', '7A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (18, 1, 'XY-N-8A', '8A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (19, 1, 'XY-N-9A', '9A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (20, 1, 'XY-N-10A', '10A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (21, 1, 'XY-N-11A', '11A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (22, 1, 'XY-N-12A', '12A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (23, 1, 'XY-N-13A', '13A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (24, 1, 'XY-N-14A', '14A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (25, 1, 'XY-N-15A', '15A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (26, 1, 'XY-N-16A', '16A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (27, 1, 'XY-N-17A', '17A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (28, 1, 'XY-N-18A', '18A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (29, 1, 'XY-N-19A', '19A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (30, 1, 'XY-N-20A', '20A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (31, 1, 'XY-N-21A', '21A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (32, 1, 'XY-N-22A', '22A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (33, 1, 'XY-N-23A', '23A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (34, 1, 'XY-N-24A', '24A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (35, 1, 'XY-N-25A', '25A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (36, 1, 'XY-N-26A', '26A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (37, 1, 'XY-N-27A', '27A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (38, 1, 'XY-N-28A', '28A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (39, 1, 'XY-N-29A', '29A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (40, 1, 'XY-N-30A', '30A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (41, 1, 'XY-N-31A', '31A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (42, 1, 'XY-N-32A', '32A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (43, 1, 'XY-N-33A', '33A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (44, 1, 'XY-N-34A', '34A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (45, 1, 'XY-N-35A', '35A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (46, 1, 'XY-N-36A', '36A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (47, 1, 'XY-N-37A', '37A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (48, 1, 'XY-N-38A', '38A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (49, 1, 'XY-N-39A', '39A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (50, 1, 'XY-N-40A', '40A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (51, 1, 'XY-N-41A', '41A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (52, 1, 'XY-N-42A', '42A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (53, 1, 'XY-N-43A', '43A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (54, 1, 'XY-N-44A', '44A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (55, 1, 'XY-N-45A', '45A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (56, 1, 'XY-N-46A', '46A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (57, 1, 'XY-N-47A', '47A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (58, 1, 'XY-N-48A', '48A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (59, 1, 'XY-N-49A', '49A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (60, 1, 'XY-N-50A', '50A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (61, 1, 'XY-N-51A', '51A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (62, 1, 'XY-N-52A', '52A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (63, 1, 'XY-N-53A', '53A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (64, 1, 'XY-N-54A', '54A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (65, 1, 'XY-N-55A', '55A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (66, 1, 'XY-N-56A', '56A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (67, 1, 'XY-N-57A', '57A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (68, 1, 'XY-N-58A', '58A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (69, 1, 'XY-N-59A', '59A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (70, 1, 'XY-N-60A', '60A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (71, 1, 'XY-N-1B', '1B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (72, 1, 'XY-N-2B', '2B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (73, 1, 'XY-N-3B', '3B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (74, 1, 'XY-N-4B', '4B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (75, 1, 'XY-N-5B', '5B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (76, 1, 'XY-N-6B', '6B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (77, 1, 'XY-N-7B', '7B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (78, 1, 'XY-N-8B', '8B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (79, 1, 'XY-N-9B', '9B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (80, 1, 'XY-N-10B', '10B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (81, 1, 'XY-N-11B', '11B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (82, 1, 'XY-N-12B', '12B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (83, 1, 'XY-N-13B', '13B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (84, 1, 'XY-N-14B', '14B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (85, 1, 'XY-N-15B', '15B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (86, 1, 'XY-N-16B', '16B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (87, 1, 'XY-N-17B', '17B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (88, 1, 'XY-N-18B', '18B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (89, 1, 'XY-N-19B', '19B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (90, 1, 'XY-N-20B', '20B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (91, 1, 'XY-N-21B', '21B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (92, 1, 'XY-N-22B', '22B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (93, 1, 'XY-N-23B', '23B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (94, 1, 'XY-N-24B', '24B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (95, 1, 'XY-N-25B', '25B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (96, 1, 'XY-N-26B', '26B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (97, 1, 'XY-N-27B', '27B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (98, 1, 'XY-N-28B', '28B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (99, 1, 'XY-N-29B', '29B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (100, 1, 'XY-N-30B', '30B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (101, 1, 'XY-N-31B', '31B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (102, 1, 'XY-N-32B', '32B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (103, 1, 'XY-N-33B', '33B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (104, 1, 'XY-N-34B', '34B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (105, 1, 'XY-N-35B', '35B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (106, 1, 'XY-N-36B', '36B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (107, 1, 'XY-N-37B', '37B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (108, 1, 'XY-N-38B', '38B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (109, 1, 'XY-N-39B', '39B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (110, 1, 'XY-N-40B', '40B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (111, 1, 'XY-N-41B', '41B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (112, 1, 'XY-N-42B', '42B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (113, 1, 'XY-N-43B', '43B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (114, 1, 'XY-N-44B', '44B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (115, 1, 'XY-N-45B', '45B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (116, 1, 'XY-N-46B', '46B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (117, 1, 'XY-N-47B', '47B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (118, 1, 'XY-N-48B', '48B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (119, 1, 'XY-N-49B', '49B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (120, 1, 'XY-N-50B', '50B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (121, 1, 'XY-N-51B', '51B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (122, 1, 'XY-N-52B', '52B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (123, 1, 'XY-N-53B', '53B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (124, 1, 'XY-N-54B', '54B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (125, 1, 'XY-N-55B', '55B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (126, 1, 'XY-N-56B', '56B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (127, 1, 'XY-N-57B', '57B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (128, 1, 'XY-N-58B', '58B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (129, 1, 'XY-N-59B', '59B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (130, 1, 'XY-N-60B', '60B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (131, 2, 'XF-P-A', 'A', 'processing', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (132, 2, 'XF-P-B', 'B', 'processing', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (133, 2, 'XF-P-1', '1', 'processing', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (134, 2, 'XF-P-2', '2', 'processing', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (135, 2, 'XF-P-3', '3', 'processing', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (136, 2, 'XF-P-4', '4', 'processing', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (137, 2, 'XF-N-5A', '5A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (138, 2, 'XF-N-6A', '6A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (139, 2, 'XF-N-7A', '7A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (140, 2, 'XF-N-8A', '8A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (141, 2, 'XF-N-9A', '9A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (142, 2, 'XF-N-10A', '10A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (143, 2, 'XF-N-11A', '11A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (144, 2, 'XF-N-12A', '12A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (145, 2, 'XF-N-13A', '13A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (146, 2, 'XF-N-14A', '14A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (147, 2, 'XF-N-15A', '15A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (148, 2, 'XF-N-16A', '16A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (149, 2, 'XF-N-17A', '17A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (150, 2, 'XF-N-18A', '18A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (151, 2, 'XF-N-19A', '19A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (152, 2, 'XF-N-20A', '20A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (153, 2, 'XF-N-21A', '21A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (154, 2, 'XF-N-22A', '22A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (155, 2, 'XF-N-23A', '23A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (156, 2, 'XF-N-24A', '24A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (157, 2, 'XF-N-25A', '25A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (158, 2, 'XF-N-26A', '26A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (159, 2, 'XF-N-27A', '27A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (160, 2, 'XF-N-28A', '28A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (161, 2, 'XF-N-29A', '29A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (162, 2, 'XF-N-30A', '30A', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (163, 2, 'XF-N-5B', '5B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (164, 2, 'XF-N-6B', '6B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (165, 2, 'XF-N-7B', '7B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (166, 2, 'XF-N-8B', '8B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (167, 2, 'XF-N-9B', '9B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (168, 2, 'XF-N-10B', '10B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (169, 2, 'XF-N-11B', '11B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (170, 2, 'XF-N-12B', '12B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (171, 2, 'XF-N-13B', '13B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (172, 2, 'XF-N-14B', '14B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (173, 2, 'XF-N-15B', '15B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (174, 2, 'XF-N-16B', '16B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (175, 2, 'XF-N-17B', '17B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (176, 2, 'XF-N-18B', '18B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (177, 2, 'XF-N-19B', '19B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (178, 2, 'XF-N-20B', '20B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (179, 2, 'XF-N-21B', '21B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (180, 2, 'XF-N-22B', '22B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (181, 2, 'XF-N-23B', '23B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (182, 2, 'XF-N-24B', '24B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (183, 2, 'XF-N-25B', '25B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (184, 2, 'XF-N-26B', '26B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (185, 2, 'XF-N-27B', '27B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (186, 2, 'XF-N-28B', '28B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (187, 2, 'XF-N-29B', '29B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (188, 2, 'XF-N-30B', '30B', 'storage', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (189, 3, 'JY-P-A1', 'A1', 'processing', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (190, 3, 'JY-P-A2', 'A2', 'processing', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (191, 3, 'JY-P-A3', 'A3', 'processing', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');
INSERT INTO `storage_racks` VALUES (192, 3, 'JY-P-A4', 'A4', 'processing', 0, 'normal', '2025-08-25 17:49:38', '2025-08-25 17:49:38');

-- ----------------------------
-- Table structure for transaction_logs
-- ----------------------------
DROP TABLE IF EXISTS `transaction_logs`;
CREATE TABLE `transaction_logs`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NULL DEFAULT NULL COMMENT '操作用户ID',
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '操作类型',
  `table_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '操作表名',
  `record_id` int(11) NULL DEFAULT NULL COMMENT '记录ID',
  `old_data` json NULL COMMENT '旧数据',
  `new_data` json NULL COMMENT '新数据',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'IP地址',
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '用户代理',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `user_id`(`user_id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '操作日志表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of transaction_logs
-- ----------------------------

-- ----------------------------
-- Table structure for users
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '用户名',
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '密码',
  `real_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '真实姓名',
  `role` enum('admin','manager','operator','viewer') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'operator',
  `base_id` int(11) NULL DEFAULT NULL COMMENT '所属基地ID',
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '电话',
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '邮箱',
  `last_login` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最后登录时间',
  `status` tinyint(1) NULL DEFAULT 1 COMMENT '状态：1启用，0禁用',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `base_id`(`base_id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 8 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '用户表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of users
-- ----------------------------
INSERT INTO `users` VALUES (1, 'admin', '$2y$10$hNBH50jdl3CmohUBrSUI6eBg/omd8wqNTjSXOUHw3c3Z3xB6v5OWO', '系统管理员', 'admin', NULL, '13297409490', '', '2025-09-02 19:12:38', 1, '2025-08-13 10:53:20', '2025-09-02 19:12:38');
INSERT INTO `users` VALUES (2, '0050016', '$2y$10$PtcsNQDtHl4NwQpf09CTM.9F3bBUCXicOs5oEcbhHzfIjqJmfxbSi', '刘春光', 'operator', 1, '13881030875', '', '2025-09-03 20:59:20', 1, '2025-08-13 13:32:10', '2025-09-03 20:59:20');
INSERT INTO `users` VALUES (3, '0030025', '$2y$10$MhFaeVbI2V4veiOwfeJWneJoAJpgj5ydt2haf/Hvq0.KoSQG0PKxK', '王明阳', 'manager', 1, '18582728595', '', '2025-08-19 14:53:55', 1, '2025-08-13 13:33:20', '2025-08-19 14:53:55');
INSERT INTO `users` VALUES (4, '0030013', '$2y$10$Sau4.JoAsVlFFNdiY6KHR.bdFPbNJ0hc9FUwh3.aK/nMOqXv9lqAS', '陈一靖', 'viewer', 1, '13658154114', '', '2025-08-19 14:57:34', 1, '2025-08-13 15:12:19', '2025-08-19 14:57:34');
INSERT INTO `users` VALUES (5, 'xinfeng', '$2y$10$.2gWVeYjb9T0QwcvCbDTtOaKdKZc1zlhc9gIAPjp3GTRqteov.Ouy', '新丰库管', 'manager', 2, '', '', '2025-11-14 20:55:29', 1, '2025-09-02 09:50:50', '2025-11-14 20:55:29');
INSERT INTO `users` VALUES (6, '0051001', '$2y$10$tpND0ubkSeafVU/JleSNvOXVYayZIafZSfWHGLS/epNeU6x.t5Qfa', '张帅帅', 'operator', 2, '', '', '2025-09-02 19:10:27', 1, '2025-09-02 19:10:27', '2025-09-02 19:10:27');
INSERT INTO `users` VALUES (7, '0050038', '$2y$10$bObYeQ6h1fv4eHtUXyAr0eSWO1aCojjVXogFxXonN41PH.Vonuw9W', '张志雄', 'operator', 2, '', '', '2025-09-02 19:10:51', 1, '2025-09-02 19:10:51', '2025-09-02 19:10:51');

-- ----------------------------
-- Procedure structure for AddForeignKeySafely
-- ----------------------------
DROP PROCEDURE IF EXISTS `AddForeignKeySafely`;
delimiter ;;
CREATE PROCEDURE `AddForeignKeySafely`()
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
    
END
;;
delimiter ;

SET FOREIGN_KEY_CHECKS = 1;
