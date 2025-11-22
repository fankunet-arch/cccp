-- ========================================
-- DTS (Date Timeline System) 数据库表结构
-- ========================================
-- 创建时间: 2025-11-17
-- 说明: DTS 子项目的核心数据库表
-- ========================================

-- 1. 主体表 (cp_dts_subject) - "谁的事情"
-- ========================================
CREATE TABLE IF NOT EXISTS `cp_dts_subject` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `subject_name` VARCHAR(100) NOT NULL COMMENT '主体名称（如A1、A1公司、B2等）',
  `subject_type` ENUM('person', 'company', 'other') NOT NULL DEFAULT 'person' COMMENT '主体类型',
  `subject_status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态：1=启用，0=停用',
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '软删除标记：0=正常，1=已删除',
  `remark` TEXT DEFAULT NULL COMMENT '备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_subject_status` (`subject_status`),
  KEY `idx_subject_name` (`subject_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DTS主体表';


-- 1.1 CP 基线 DTS 条目表 (cp_dts_entry)
-- 用于 CP 层维护标准日期标签，后续 SOM 可继承/覆写
CREATE TABLE IF NOT EXISTS `cp_dts_entry` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `dts_code` VARCHAR(100) NOT NULL COMMENT '系统唯一 code',
  `entry_type` ENUM('holiday','promotion','system','custom') NOT NULL DEFAULT 'custom' COMMENT '条目类型',
  `date_mode` ENUM('single','range') NOT NULL DEFAULT 'single' COMMENT '日期模式：单日/区间',
  `date_value` DATE DEFAULT NULL COMMENT '单日日期',
  `start_date` DATE DEFAULT NULL COMMENT '区间开始日期',
  `end_date` DATE DEFAULT NULL COMMENT '区间结束日期',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=启用 0=停用',
  `show_to_front` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=向前端展示 0=仅内部逻辑',
  `name_zh` VARCHAR(200) NOT NULL COMMENT '名称（中文）',
  `name_en` VARCHAR(200) DEFAULT NULL COMMENT '名称（英文）',
  `short_title` VARCHAR(100) DEFAULT NULL COMMENT '前端短标题',
  `color_hex` VARCHAR(20) DEFAULT NULL COMMENT '颜色值',
  `tag_class` VARCHAR(100) DEFAULT NULL COMMENT '标签样式类',
  `languages` VARCHAR(200) DEFAULT NULL COMMENT '适用语言列表，逗号分隔，空=全部',
  `platforms` VARCHAR(200) DEFAULT NULL COMMENT '适用端，如PC,M,APP，空=全部',
  `modules` TEXT DEFAULT NULL COMMENT '适用模块列表，逗号分隔',
  `priority` INT(11) NOT NULL DEFAULT 100 COMMENT '优先级，越大越靠前',
  `external_id` VARCHAR(100) DEFAULT NULL COMMENT '外部关联ID',
  `external_url` VARCHAR(500) DEFAULT NULL COMMENT '外部链接',
  `remark` TEXT DEFAULT NULL COMMENT '备注',
  `source` ENUM('CP','SOM') NOT NULL DEFAULT 'CP' COMMENT '来源：CP或SOM',
  `som_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '所属SOM（来源为SOM时使用）',
  `local_override` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'SOM是否为覆写记录',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code_source` (`dts_code`, `source`, `som_id`),
  KEY `idx_type` (`entry_type`),
  KEY `idx_date` (`date_mode`, `date_value`, `start_date`, `end_date`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CP 基线 DTS 条目表';


-- 2. 对象表 (cp_dts_object) - "哪一个对象"
-- ========================================
CREATE TABLE IF NOT EXISTS `cp_dts_object` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `subject_id` INT(11) UNSIGNED NOT NULL COMMENT '关联主体ID',
  `object_name` VARCHAR(200) NOT NULL COMMENT '对象名称（如车辆Q3、中国护照、T8证件等）',
  `object_type_main` VARCHAR(50) NOT NULL COMMENT '大类（证件/车辆/健康/家庭/店铺等）',
  `object_type_sub` VARCHAR(50) NOT NULL COMMENT '小类（护照/NIE/整车保养/轮胎等）',
  `identifier` VARCHAR(200) DEFAULT NULL COMMENT '对象标识（如车牌号、证件号等）',
  `active_flag` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=当前使用，0=历史对象',
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '软删除标记：0=正常，1=已删除',
  `remark` TEXT DEFAULT NULL COMMENT '备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_subject_id` (`subject_id`),
  KEY `idx_object_type` (`object_type_main`, `object_type_sub`),
  KEY `idx_active_flag` (`active_flag`),
  CONSTRAINT `fk_object_subject` FOREIGN KEY (`subject_id`) REFERENCES `cp_dts_subject` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DTS对象表';


-- 3. 规则模板表 (cp_dts_rule) - "怎么算时间"
-- ========================================
CREATE TABLE IF NOT EXISTS `cp_dts_rule` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `rule_name` VARCHAR(200) NOT NULL COMMENT '规则名称（如中国护照_换发规则_v1）',
  `rule_type` ENUM('expiry_based', 'last_done_based', 'submit_based') NOT NULL COMMENT '规则类型',
  `base_field` ENUM('expiry_date', 'last_done_date', 'submit_date', 'event_date') NOT NULL COMMENT '基准字段',
  `cat_main` VARCHAR(50) DEFAULT NULL COMMENT '适用大类',
  `cat_sub` VARCHAR(50) DEFAULT NULL COMMENT '适用小类',
  `earliest_offset_days` INT(11) DEFAULT NULL COMMENT '最早可办偏移（负数=提前）',
  `suggest_offset_days` INT(11) DEFAULT NULL COMMENT '建议办理偏移（负数=提前）',
  `safe_last_offset_days` INT(11) DEFAULT NULL COMMENT '最晚安全日偏移（负数=提前）',
  `cycle_interval_days` INT(11) DEFAULT NULL COMMENT '周期间隔天数',
  `cycle_interval_months` INT(11) DEFAULT NULL COMMENT '周期间隔月数',
  `mileage_interval` INT(11) DEFAULT NULL COMMENT '建议里程间隔（公里）',
  `follow_up_offset_days` INT(11) DEFAULT NULL COMMENT '跟进偏移天数',
  `follow_up_offset_months` INT(11) DEFAULT NULL COMMENT '跟进偏移月数',
  `lock_days` INT(11) DEFAULT NULL COMMENT '锁定天数（事件后多少天内不可再次操作）',
  `rule_status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=启用，0=禁用',
  `remark` TEXT DEFAULT NULL COMMENT '备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_rule_type` (`rule_type`),
  KEY `idx_cat` (`cat_main`, `cat_sub`),
  KEY `idx_rule_status` (`rule_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DTS规则模板表';


-- 4. 事件表 (cp_dts_event) - "具体发生的事情"
-- ========================================
CREATE TABLE IF NOT EXISTS `cp_dts_event` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `object_id` INT(11) UNSIGNED NOT NULL COMMENT '关联对象ID',
  `subject_id` INT(11) UNSIGNED NOT NULL COMMENT '冗余存储主体ID，方便按主体筛选',
  `rule_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '关联规则模板ID（可选）',
  `event_type` VARCHAR(50) NOT NULL COMMENT '事件类型（submit/issue/renew/maintain/replace_part/follow_up/other）',
  `event_date` DATE NOT NULL COMMENT '事件发生日期',
  `expiry_date_new` DATE DEFAULT NULL COMMENT '新证件的过期日（可选）',
  `mileage_now` INT(11) DEFAULT NULL COMMENT '当前里程（车辆类事件）',
  `note` TEXT DEFAULT NULL COMMENT '备注',
  `status` ENUM('completed', 'cancelled', 'pending') NOT NULL DEFAULT 'completed' COMMENT '事件状态',
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '软删除标记：0=正常，1=已删除',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_subject_id` (`subject_id`),
  KEY `idx_rule_id` (`rule_id`),
  KEY `idx_event_date` (`event_date`),
  KEY `idx_event_type` (`event_type`),
  CONSTRAINT `fk_event_object` FOREIGN KEY (`object_id`) REFERENCES `cp_dts_object` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_subject` FOREIGN KEY (`subject_id`) REFERENCES `cp_dts_subject` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_rule` FOREIGN KEY (`rule_id`) REFERENCES `cp_dts_rule` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DTS事件表';


-- 5. 对象当前状态表 (cp_dts_object_state) - "快速查询下一节点"（可选，性能优化）
-- ========================================
CREATE TABLE IF NOT EXISTS `cp_dts_object_state` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `object_id` INT(11) UNSIGNED NOT NULL COMMENT '关联对象ID',
  `next_deadline_date` DATE DEFAULT NULL COMMENT '下一个截止日',
  `next_window_start_date` DATE DEFAULT NULL COMMENT '下一个窗口开始日',
  `next_window_end_date` DATE DEFAULT NULL COMMENT '下一个窗口结束日',
  `next_cycle_date` DATE DEFAULT NULL COMMENT '下一次周期日期',
  `next_follow_up_date` DATE DEFAULT NULL COMMENT '下一次跟进日期',
  `next_mileage_suggest` INT(11) DEFAULT NULL COMMENT '建议下次里程',
  `locked_until_date` DATE DEFAULT NULL COMMENT '锁定截止日期（Lock-in轨）',
  `last_event_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '最后一个事件ID',
  `last_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最后更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_object_id` (`object_id`),
  KEY `idx_next_deadline` (`next_deadline_date`),
  KEY `idx_next_cycle` (`next_cycle_date`),
  KEY `idx_next_follow_up` (`next_follow_up_date`),
  CONSTRAINT `fk_state_object` FOREIGN KEY (`object_id`) REFERENCES `cp_dts_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DTS对象当前状态表';


-- ========================================
-- 初始化数据（示例）
-- ========================================

-- 插入示例主体
INSERT INTO `cp_dts_subject` (`subject_name`, `subject_type`, `subject_status`, `remark`) VALUES
('A1', 'person', 1, '个人主体A1'),
('A1公司', 'company', 1, 'A1的公司'),
('B2', 'person', 1, '个人主体B2'),
('B2公司', 'company', 1, 'B2的公司');

-- 插入示例规则模板
INSERT INTO `cp_dts_rule` (`rule_name`, `rule_type`, `base_field`, `cat_main`, `cat_sub`, `earliest_offset_days`, `suggest_offset_days`, `safe_last_offset_days`, `rule_status`, `remark`) VALUES
('中国护照_换发规则_v1', 'expiry_based', 'expiry_date', '证件', '护照', -180, -90, -30, 1, '中国护照换发规则：最早可办提前180天，建议提前90天，最晚安全提前30天'),
('西班牙护照_换发规则_v1', 'expiry_based', 'expiry_date', '证件', '护照', -180, -90, -30, 1, '西班牙护照换发规则'),
('NIE_续期规则_v1', 'expiry_based', 'expiry_date', '证件', 'NIE', -90, -60, -20, 1, 'NIE续期规则'),
('NIE_递交跟进规则_v1', 'submit_based', 'submit_date', '证件', 'NIE', NULL, NULL, NULL, 1, 'NIE递交后3个月跟进');

INSERT INTO `cp_dts_rule` (`rule_name`, `rule_type`, `base_field`, `cat_main`, `cat_sub`, `cycle_interval_months`, `rule_status`, `remark`) VALUES
('车辆整车保养_年度规则_v1', 'last_done_based', 'last_done_date', '车辆', '整车保养', 12, 1, '车辆整车保养每年一次');

INSERT INTO `cp_dts_rule` (`rule_name`, `rule_type`, `base_field`, `cat_main`, `cat_sub`, `cycle_interval_days`, `mileage_interval`, `rule_status`, `remark`) VALUES
('轮胎更换_3年规则_v1', 'last_done_based', 'last_done_date', '车辆', '轮胎', 1095, 40000, 1, '轮胎更换：3年或40000公里'),
('刹车片更换_2年规则_v1', 'last_done_based', 'last_done_date', '车辆', '刹车片', 730, 30000, 1, '刹车片更换：2年或30000公里');

UPDATE `cp_dts_rule` SET `follow_up_offset_months` = 3 WHERE `rule_name` = 'NIE_递交跟进规则_v1';

-- [DTS v2.1] 新增 lock_days 示例数据
UPDATE `cp_dts_rule` SET `lock_days` = 30 WHERE `rule_name` = '中国护照_换发规则_v1';
UPDATE `cp_dts_rule` SET `lock_days` = 30 WHERE `rule_name` = '西班牙护照_换发规则_v1';
UPDATE `cp_dts_rule` SET `lock_days` = 60 WHERE `rule_name` = 'NIE_续期规则_v1';
UPDATE `cp_dts_rule` SET `lock_days` = 90 WHERE `rule_name` = 'NIE_递交跟进规则_v1';
UPDATE `cp_dts_rule` SET `lock_days` = 180 WHERE `rule_name` = '车辆整车保养_年度规则_v1';


-- ========================================
-- 完成
-- ========================================
