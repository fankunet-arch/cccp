-- ========================================
-- DTS v2.1.3 数据库迁移
-- ========================================
-- 迭代版本: v2.1.3
-- 核心目标: 实现事件录入时的规则灵活性（自动/指定/自定义三种模式）
-- 创建时间: 2025-11-23
-- ========================================

-- 1. 为 cp_dts_event 表添加自定义日期字段
-- ========================================

-- 检查并添加 custom_lock_date（自定义锁定截止日）
SET @db_name = DATABASE();
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'cp_dts_event'
  AND COLUMN_NAME = 'custom_lock_date';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `cp_dts_event` ADD COLUMN `custom_lock_date` DATE DEFAULT NULL COMMENT ''自定义锁定截止日（模式C：自定义）'' AFTER `note`',
    'SELECT ''Column custom_lock_date already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加 custom_window_start（自定义窗口期开始日）
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'cp_dts_event'
  AND COLUMN_NAME = 'custom_window_start';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `cp_dts_event` ADD COLUMN `custom_window_start` DATE DEFAULT NULL COMMENT ''自定义窗口期开始日（模式C：自定义）'' AFTER `custom_lock_date`',
    'SELECT ''Column custom_window_start already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加 custom_window_end（自定义窗口期结束日）
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'cp_dts_event'
  AND COLUMN_NAME = 'custom_window_end';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `cp_dts_event` ADD COLUMN `custom_window_end` DATE DEFAULT NULL COMMENT ''自定义窗口期结束日（模式C：自定义）'' AFTER `custom_window_start`',
    'SELECT ''Column custom_window_end already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加 custom_follow_up_date（自定义跟进日期）
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'cp_dts_event'
  AND COLUMN_NAME = 'custom_follow_up_date';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `cp_dts_event` ADD COLUMN `custom_follow_up_date` DATE DEFAULT NULL COMMENT ''自定义跟进日期（模式C：自定义）'' AFTER `custom_window_end`',
    'SELECT ''Column custom_follow_up_date already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- 2. 为 custom_follow_up_date 添加索引（优化查询性能）
-- ========================================

-- 检查索引是否已存在
SELECT COUNT(*) INTO @index_exists
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'cp_dts_event'
  AND INDEX_NAME = 'idx_custom_follow_up';

-- 如果索引不存在，则创建
SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `cp_dts_event` ADD INDEX `idx_custom_follow_up` (`custom_follow_up_date`)',
    'SELECT ''Index idx_custom_follow_up already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- 3. 更新 schema 版本记录（如果有版本表的话）
-- ========================================
-- 注：如果项目中有专门的版本跟踪表，可在此记录升级信息
-- INSERT INTO schema_version (version, description, applied_at) VALUES ('2.1.3', 'DTS规则模式升级：支持自动/指定/自定义三种模式', NOW());


-- ========================================
-- 迁移完成
-- ========================================
-- 说明：
-- 1. 新增的 custom_* 字段用于模式C（自定义）的数据持久化
-- 2. 这些字段仅在用户选择自定义模式时才会被填充
-- 3. 模式A（默认规则）：rule_id 为空，custom_* 字段为空
-- 4. 模式B（指定规则）：rule_id 有值，custom_* 字段为空
-- 5. 模式C（自定义）：custom_* 字段有值，rule_id 可能为空
-- ========================================
