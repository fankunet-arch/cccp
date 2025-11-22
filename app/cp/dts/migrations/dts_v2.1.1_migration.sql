-- ========================================
-- DTS v2.1.1 数据库升级迁移脚本
-- ========================================
-- 用途: 为现有DTS数据库添加v2.1.1软删除功能
-- 执行时间: 2025-11-22
-- 说明: 此脚本为主体/对象/事件表添加软删除字段
-- ========================================

-- 1. 为 cp_dts_subject 表添加软删除字段
ALTER TABLE `cp_dts_subject`
ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0
COMMENT '软删除标记：0=正常，1=已删除'
AFTER `subject_status`;

-- 2. 为 cp_dts_object 表添加软删除字段
ALTER TABLE `cp_dts_object`
ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0
COMMENT '软删除标记：0=正常，1=已删除'
AFTER `active_flag`;

-- 3. 为 cp_dts_event 表添加软删除字段
ALTER TABLE `cp_dts_event`
ADD COLUMN `is_deleted` TINYINT(1) NOT NULL DEFAULT 0
COMMENT '软删除标记：0=正常，1=已删除'
AFTER `status`;

-- 4. 添加索引以优化软删除查询性能
ALTER TABLE `cp_dts_subject` ADD KEY `idx_is_deleted` (`is_deleted`);
ALTER TABLE `cp_dts_object` ADD KEY `idx_is_deleted` (`is_deleted`);
ALTER TABLE `cp_dts_event` ADD KEY `idx_is_deleted` (`is_deleted`);

-- ========================================
-- 迁移完成
-- ========================================
-- 验证步骤:
-- 1. 检查字段是否添加成功:
--    SHOW COLUMNS FROM cp_dts_subject LIKE 'is_deleted';
--    SHOW COLUMNS FROM cp_dts_object LIKE 'is_deleted';
--    SHOW COLUMNS FROM cp_dts_event LIKE 'is_deleted';
--
-- 2. 验证索引:
--    SHOW INDEX FROM cp_dts_subject WHERE Key_name = 'idx_is_deleted';
-- ========================================
