-- ========================================
-- DTS v2.1 数据库升级迁移脚本
-- ========================================
-- 用途: 为现有DTS数据库添加v2.1新字段
-- 执行时间: 2025-11-22
-- 说明: 此脚本用于升级现有数据库,不会删除任何数据
-- ========================================

-- 1. 为 cp_dts_rule 表添加 lock_days 字段
ALTER TABLE `cp_dts_rule`
ADD COLUMN `lock_days` INT(11) DEFAULT NULL COMMENT '锁定天数（事件后多少天内不可再次操作）'
AFTER `follow_up_offset_months`;

-- 2. 为 cp_dts_object_state 表添加 locked_until_date 字段
ALTER TABLE `cp_dts_object_state`
ADD COLUMN `locked_until_date` DATE DEFAULT NULL COMMENT '锁定截止日期（Lock-in轨）'
AFTER `next_mileage_suggest`;

-- 3. 更新示例规则的 lock_days 值
UPDATE `cp_dts_rule` SET `lock_days` = 30 WHERE `rule_name` = '中国护照_换发规则_v1';
UPDATE `cp_dts_rule` SET `lock_days` = 30 WHERE `rule_name` = '西班牙护照_换发规则_v1';
UPDATE `cp_dts_rule` SET `lock_days` = 60 WHERE `rule_name` = 'NIE_续期规则_v1';
UPDATE `cp_dts_rule` SET `lock_days` = 90 WHERE `rule_name` = 'NIE_递交跟进规则_v1';
UPDATE `cp_dts_rule` SET `lock_days` = 180 WHERE `rule_name` = '车辆整车保养_年度规则_v1';

-- ========================================
-- 迁移完成
-- ========================================
-- 验证步骤:
-- 1. 检查字段是否添加成功:
--    SHOW COLUMNS FROM cp_dts_rule LIKE 'lock_days';
--    SHOW COLUMNS FROM cp_dts_object_state LIKE 'locked_until_date';
--
-- 2. 检查示例数据是否更新:
--    SELECT rule_name, lock_days FROM cp_dts_rule WHERE lock_days IS NOT NULL;
-- ========================================
