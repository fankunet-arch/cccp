-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- 主机： mhdlmskp2kpxguj.mysql.db
-- 生成日期： 2025-11-19 14:50:41
-- 服务器版本： 8.4.6-6
-- PHP 版本： 8.1.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `mhdlmskp2kpxguj`
--
CREATE DATABASE IF NOT EXISTS `mhdlmskp2kpxguj` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `mhdlmskp2kpxguj`;

-- --------------------------------------------------------

--
-- 表的结构 `cp_dts_entry`
--

DROP TABLE IF EXISTS `cp_dts_entry`;
CREATE TABLE `cp_dts_entry` (
  `id` int UNSIGNED NOT NULL COMMENT '主键ID',
  `dts_code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '系统唯一 code',
  `entry_type` enum('holiday','promotion','system','custom') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'custom' COMMENT '条目类型',
  `date_mode` enum('single','range') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'single' COMMENT '日期模式：单日/区间',
  `date_value` date DEFAULT NULL COMMENT '单日日期',
  `start_date` date DEFAULT NULL COMMENT '区间开始日期',
  `end_date` date DEFAULT NULL COMMENT '区间结束日期',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=启用 0=停用',
  `show_to_front` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=向前端展示 0=仅内部逻辑',
  `name_zh` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '名称（中文）',
  `name_en` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '名称（英文）',
  `short_title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '前端短标题',
  `color_hex` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '颜色值',
  `tag_class` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '标签样式类',
  `languages` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '适用语言列表，逗号分隔，空=全部',
  `platforms` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '适用端，如PC,M,APP，空=全部',
  `modules` text COLLATE utf8mb4_unicode_ci COMMENT '适用模块列表，逗号分隔',
  `priority` int NOT NULL DEFAULT '100' COMMENT '优先级，越大越靠前',
  `external_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '外部关联ID',
  `external_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '外部链接',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `source` enum('CP','SOM') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CP' COMMENT '来源：CP或SOM',
  `som_id` int UNSIGNED DEFAULT NULL COMMENT '所属SOM（来源为SOM时使用）',
  `local_override` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'SOM是否为覆写记录',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CP 基线 DTS 条目表';

-- --------------------------------------------------------

--
-- 表的结构 `cp_dts_event`
--

DROP TABLE IF EXISTS `cp_dts_event`;
CREATE TABLE `cp_dts_event` (
  `id` int UNSIGNED NOT NULL COMMENT '主键ID',
  `object_id` int UNSIGNED NOT NULL COMMENT '关联对象ID',
  `subject_id` int UNSIGNED NOT NULL COMMENT '冗余存储主体ID，方便按主体筛选',
  `rule_id` int UNSIGNED DEFAULT NULL COMMENT '关联规则模板ID（可选）',
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '事件类型（submit/issue/renew/maintain/replace_part/follow_up/other）',
  `event_date` date NOT NULL COMMENT '事件发生日期',
  `expiry_date_new` date DEFAULT NULL COMMENT '新证件的过期日（可选）',
  `mileage_now` int DEFAULT NULL COMMENT '当前里程（车辆类事件）',
  `note` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `status` enum('completed','cancelled','pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed' COMMENT '事件状态',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DTS事件表';

-- --------------------------------------------------------

--
-- 表的结构 `cp_dts_object`
--

DROP TABLE IF EXISTS `cp_dts_object`;
CREATE TABLE `cp_dts_object` (
  `id` int UNSIGNED NOT NULL COMMENT '主键ID',
  `subject_id` int UNSIGNED NOT NULL COMMENT '关联主体ID',
  `object_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '对象名称（如车辆Q3、中国护照、T8证件等）',
  `object_type_main` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '大类（证件/车辆/健康/家庭/店铺等）',
  `object_type_sub` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '小类（护照/NIE/整车保养/轮胎等）',
  `identifier` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '对象标识（如车牌号、证件号等）',
  `active_flag` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=当前使用，0=历史对象',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DTS对象表';

-- --------------------------------------------------------

--
-- 表的结构 `cp_dts_object_state`
--

DROP TABLE IF EXISTS `cp_dts_object_state`;
CREATE TABLE `cp_dts_object_state` (
  `id` int UNSIGNED NOT NULL COMMENT '主键ID',
  `object_id` int UNSIGNED NOT NULL COMMENT '关联对象ID',
  `next_deadline_date` date DEFAULT NULL COMMENT '下一个截止日',
  `next_window_start_date` date DEFAULT NULL COMMENT '下一个窗口开始日',
  `next_window_end_date` date DEFAULT NULL COMMENT '下一个窗口结束日',
  `next_cycle_date` date DEFAULT NULL COMMENT '下一次周期日期',
  `next_follow_up_date` date DEFAULT NULL COMMENT '下一次跟进日期',
  `next_mileage_suggest` int DEFAULT NULL COMMENT '建议下次里程',
  `last_event_id` int UNSIGNED DEFAULT NULL COMMENT '最后一个事件ID',
  `last_updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最后更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DTS对象当前状态表';

-- --------------------------------------------------------

--
-- 表的结构 `cp_dts_rule`
--

DROP TABLE IF EXISTS `cp_dts_rule`;
CREATE TABLE `cp_dts_rule` (
  `id` int UNSIGNED NOT NULL COMMENT '主键ID',
  `rule_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '规则名称（如中国护照_换发规则_v1）',
  `rule_type` enum('expiry_based','last_done_based','submit_based') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '规则类型',
  `base_field` enum('expiry_date','last_done_date','submit_date','event_date') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '基准字段',
  `cat_main` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '适用大类',
  `cat_sub` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '适用小类',
  `earliest_offset_days` int DEFAULT NULL COMMENT '最早可办偏移（负数=提前）',
  `suggest_offset_days` int DEFAULT NULL COMMENT '建议办理偏移（负数=提前）',
  `safe_last_offset_days` int DEFAULT NULL COMMENT '最晚安全日偏移（负数=提前）',
  `cycle_interval_days` int DEFAULT NULL COMMENT '周期间隔天数',
  `cycle_interval_months` int DEFAULT NULL COMMENT '周期间隔月数',
  `mileage_interval` int DEFAULT NULL COMMENT '建议里程间隔（公里）',
  `follow_up_offset_days` int DEFAULT NULL COMMENT '跟进偏移天数',
  `follow_up_offset_months` int DEFAULT NULL COMMENT '跟进偏移月数',
  `rule_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=启用，0=禁用',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DTS规则模板表';

-- --------------------------------------------------------

--
-- 表的结构 `cp_dts_subject`
--

DROP TABLE IF EXISTS `cp_dts_subject`;
CREATE TABLE `cp_dts_subject` (
  `id` int UNSIGNED NOT NULL COMMENT '主键ID',
  `subject_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '主体名称（如A1、A1公司、B2等）',
  `subject_type` enum('person','company','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'person' COMMENT '主体类型',
  `subject_status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：1=启用，0=停用',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DTS主体表';

-- --------------------------------------------------------

--
-- 表的结构 `decimal_test`
--

DROP TABLE IF EXISTS `decimal_test`;
CREATE TABLE `decimal_test` (
  `my_value` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `obs_daily_summaries`
--

DROP TABLE IF EXISTS `obs_daily_summaries`;
CREATE TABLE `obs_daily_summaries` (
  `id` bigint NOT NULL,
  `store_id` int NOT NULL,
  `summary_date` date NOT NULL,
  `gender_age_key` varchar(16) NOT NULL,
  `enter_count` int NOT NULL DEFAULT '0',
  `leave_count` int NOT NULL DEFAULT '0',
  `stay_estimate` int NOT NULL DEFAULT '0',
  `generated_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `obs_people_logs`
--

DROP TABLE IF EXISTS `obs_people_logs`;
CREATE TABLE `obs_people_logs` (
  `id` bigint NOT NULL,
  `store_id` int NOT NULL,
  `recorded_at_utc` datetime(6) NOT NULL,
  `recorded_epoch` bigint NOT NULL,
  `gender_age_key` varchar(16) NOT NULL,
  `action_key` varchar(8) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `is_deleted_flag` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `obs_stores`
--

DROP TABLE IF EXISTS `obs_stores`;
CREATE TABLE `obs_stores` (
  `id` int NOT NULL,
  `store_code` varchar(32) NOT NULL,
  `display_title` varchar(128) NOT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) DEFAULT NULL,
  `is_deleted_flag` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `prs_import_batches`
--

DROP TABLE IF EXISTS `prs_import_batches`;
CREATE TABLE `prs_import_batches` (
  `id` bigint UNSIGNED NOT NULL,
  `store_id` bigint UNSIGNED NOT NULL,
  `date_local` date NOT NULL,
  `raw_payload_sha256` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ai_model` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 表的结构 `prs_price_observations`
--

DROP TABLE IF EXISTS `prs_price_observations`;
CREATE TABLE `prs_price_observations` (
  `id` bigint UNSIGNED NOT NULL,
  `product_id` bigint UNSIGNED NOT NULL,
  `store_id` bigint UNSIGNED NOT NULL,
  `batch_id` bigint UNSIGNED NOT NULL,
  `date_local` date NOT NULL,
  `observed_at` datetime(6) NOT NULL,
  `price_per_kg_eur` decimal(10,3) DEFAULT NULL,
  `price_per_ud_eur` decimal(10,3) DEFAULT NULL,
  `unit_weight_g` int DEFAULT NULL,
  `status` enum('listed','delisted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'listed',
  `source_line_fingerprint` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 表的结构 `prs_products`
--

DROP TABLE IF EXISTS `prs_products`;
CREATE TABLE `prs_products` (
  `id` bigint UNSIGNED NOT NULL,
  `name_zh` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_es` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_name_es` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` enum('fruit','seafood','dairy','unknown') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `image_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_unit_weight_g` int DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 表的结构 `prs_product_aliases`
--

DROP TABLE IF EXISTS `prs_product_aliases`;
CREATE TABLE `prs_product_aliases` (
  `id` bigint UNSIGNED NOT NULL,
  `product_id` bigint UNSIGNED NOT NULL,
  `alias_text` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lang` enum('zh','es','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `created_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 替换视图以便查看 `prs_season_monthly_v2`
-- （参见下面的实际视图）
--
DROP VIEW IF EXISTS `prs_season_monthly_v2`;
CREATE TABLE `prs_season_monthly_v2` (
`product_id` bigint unsigned
,`store_id` bigint unsigned
,`ym` varchar(7)
,`days_with_obs` bigint
,`is_in_market_month` int
);

-- --------------------------------------------------------

--
-- 替换视图以便查看 `prs_stockout_segments_v2`
-- （参见下面的实际视图）
--
DROP VIEW IF EXISTS `prs_stockout_segments_v2`;
CREATE TABLE `prs_stockout_segments_v2` (
`product_id` bigint unsigned
,`store_id` bigint unsigned
,`gap_start` date
,`gap_end` date
,`gap_days` bigint
);

-- --------------------------------------------------------

--
-- 表的结构 `prs_stores`
--

DROP TABLE IF EXISTS `prs_stores`;
CREATE TABLE `prs_stores` (
  `id` bigint UNSIGNED NOT NULL,
  `store_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `district` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(12) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 表的结构 `sushisom_daily_operations`
--

DROP TABLE IF EXISTS `sushisom_daily_operations`;
CREATE TABLE `sushisom_daily_operations` (
  `ss_daily_id` bigint UNSIGNED NOT NULL COMMENT '日常经营记录唯一ID (主键)',
  `ss_daily_uuid` char(36) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `ss_daily_date` date NOT NULL COMMENT '记录日期',
  `ss_daily_morning_count` smallint UNSIGNED DEFAULT '0' COMMENT '上午人数',
  `ss_daily_afternoon_count` smallint UNSIGNED DEFAULT '0' COMMENT '下午人数',
  `ss_daily_cash_income` decimal(15,2) DEFAULT '0.00' COMMENT '现金收入',
  `ss_daily_cash_expense` decimal(15,2) DEFAULT '0.00' COMMENT '现金支出',
  `ss_daily_cash_balance` decimal(15,2) DEFAULT NULL COMMENT '现金余额',
  `ss_daily_bank_balance` decimal(15,2) DEFAULT NULL COMMENT '银行余额',
  `ss_daily_bank_expense` decimal(15,2) DEFAULT '0.00' COMMENT '银行支出',
  `ss_daily_bank_income` decimal(15,2) DEFAULT '0.00' COMMENT '银行收入',
  `ss_daily_match_name` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL COMMENT '球赛名称',
  `ss_daily_match_time` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL COMMENT '比赛时间',
  `ss_daily_created_by` bigint UNSIGNED NOT NULL COMMENT '记录创建者ID (关联 sys_users.user_id)',
  `ss_daily_created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '记录创建时间',
  `ss_daily_updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '记录最后更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci COMMENT='Sushisom - 日常经营数据表';

-- --------------------------------------------------------

--
-- 表的结构 `sushisom_financial_transactions`
--

DROP TABLE IF EXISTS `sushisom_financial_transactions`;
CREATE TABLE `sushisom_financial_transactions` (
  `ss_fin_id` bigint UNSIGNED NOT NULL COMMENT '财务流水唯一ID (主键)',
  `ss_fin_op_id` bigint UNSIGNED NOT NULL COMMENT '关联的日常经营记录ID (sushisom_daily_operations.ss_daily_id)',
  `ss_fin_op_uuid` char(36) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `ss_fin_date` date NOT NULL COMMENT '交易发生日期',
  `ss_fin_category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '财务类别',
  `ss_fin_amount` decimal(15,2) NOT NULL COMMENT '交易金额 (可为负数)',
  `ss_fin_created_by` bigint UNSIGNED NOT NULL COMMENT '记录创建者ID (关联 sys_users.user_id)',
  `ss_fin_created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '记录创建时间',
  `ss_fin_updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '记录最后更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci COMMENT='Sushisom - 投资与专项财务流水表';

-- --------------------------------------------------------

--
-- 表的结构 `sushisom_monthly_salaries`
--

DROP TABLE IF EXISTS `sushisom_monthly_salaries`;
CREATE TABLE `sushisom_monthly_salaries` (
  `ss_ms_id` int NOT NULL,
  `salary_month` varchar(7) NOT NULL,
  `ss_ms_sushi_salary` decimal(10,2) DEFAULT '0.00',
  `ss_ms_kitchen_salary` decimal(10,2) DEFAULT '0.00',
  `ss_ms_waitstaff_salary` decimal(10,2) DEFAULT '0.00',
  `ss_ms_created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ss_ms_updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `ss_ms_record_date` date DEFAULT NULL,
  `ss_ms_created_by` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `sys_users`
--

DROP TABLE IF EXISTS `sys_users`;
CREATE TABLE `sys_users` (
  `user_id` bigint UNSIGNED NOT NULL COMMENT '用户唯一ID (主键)',
  `user_login` varchar(60) COLLATE utf8mb4_unicode_520_ci NOT NULL COMMENT '用户登录名 (不可变, 用于登录)',
  `user_secret_hash` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL COMMENT '用户密码的哈希值 (用于验证)',
  `user_email` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL COMMENT '用户电子邮箱 (可用于通知和找回密码)',
  `user_display_name` varchar(250) COLLATE utf8mb4_unicode_520_ci NOT NULL COMMENT '用户显示名称 (在界面上展示的名字)',
  `user_status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'pending' COMMENT '用户账户状态 (例如: active, suspended, pending, deleted)',
  `user_registered_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '用户注册时间 (UTC)',
  `user_last_login_at` datetime(6) DEFAULT NULL COMMENT '用户最后登录时间 (UTC)',
  `user_updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '记录最后更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci COMMENT='系统用户表';

-- --------------------------------------------------------

--
-- 表的结构 `tea_financial_transactions`
--

DROP TABLE IF EXISTS `tea_financial_transactions`;
CREATE TABLE `tea_financial_transactions` (
  `tea_fin_id` bigint UNSIGNED NOT NULL COMMENT '金融交易流水唯一ID (主键)',
  `tea_date` date NOT NULL COMMENT '交易发生日期',
  `tea_store` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL COMMENT '关联店铺名称',
  `tea_currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL COMMENT '交易币种 (如EUR, CNY)',
  `tea_amount` decimal(15,2) NOT NULL COMMENT '交易金额 (可为负数)',
  `tea_exchange_rate` decimal(10,4) DEFAULT '1.0000' COMMENT '交易当日汇率 (相对于基础币种)',
  `tea_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL COMMENT '交易类型 (如INVESTMENT_IN, RENT)',
  `tea_is_equity` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否计入本金/股份 (0=否, 1=是)',
  `tea_notes` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL COMMENT '交易备注/说明',
  `tea_created_by` bigint UNSIGNED NOT NULL COMMENT '记录创建者ID (关联 sys_users.user_id)',
  `tea_created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) COMMENT '记录创建时间',
  `tea_updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6) COMMENT '记录最后更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci COMMENT='Tea项目 - 投资与支出金融交易流水表';

-- --------------------------------------------------------

--
-- 表的结构 `tea_stores`
--

DROP TABLE IF EXISTS `tea_stores`;
CREATE TABLE `tea_stores` (
  `id` int NOT NULL,
  `store_name` varchar(255) NOT NULL COMMENT '店铺名称，如: Madrid-A',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Tea项目店铺列表';

-- --------------------------------------------------------

--
-- 表的结构 `wds_business_hours`
--

DROP TABLE IF EXISTS `wds_business_hours`;
CREATE TABLE `wds_business_hours` (
  `id` tinyint NOT NULL DEFAULT '1',
  `open_hour_local` tinyint NOT NULL DEFAULT '12',
  `close_hour_local` tinyint NOT NULL DEFAULT '22',
  `created_at` datetime(6) NOT NULL DEFAULT (utc_timestamp(6)),
  `updated_at` datetime(6) NOT NULL DEFAULT (utc_timestamp(6))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `wds_holidays`
--

DROP TABLE IF EXISTS `wds_holidays`;
CREATE TABLE `wds_holidays` (
  `scope_key` varchar(128) NOT NULL,
  `date` date NOT NULL,
  `local_name` varchar(120) NOT NULL,
  `name_en` varchar(120) NOT NULL,
  `country_code` char(2) NOT NULL DEFAULT 'ES',
  `fixed` tinyint(1) NOT NULL DEFAULT '0',
  `global` tinyint(1) NOT NULL DEFAULT '1',
  `type` varchar(40) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT (utc_timestamp(6)),
  `updated_at` datetime(6) NOT NULL DEFAULT (utc_timestamp(6))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `wds_locations`
--

DROP TABLE IF EXISTS `wds_locations`;
CREATE TABLE `wds_locations` (
  `location_id` bigint NOT NULL,
  `name` varchar(120) NOT NULL,
  `lat` decimal(8,5) NOT NULL,
  `lon` decimal(8,5) NOT NULL,
  `country_code` char(2) NOT NULL DEFAULT 'ES',
  `region_code` varchar(8) DEFAULT 'ES-M',
  `city` varchar(64) DEFAULT 'Madrid',
  `district` varchar(64) DEFAULT 'Usera',
  `primary_station` varchar(12) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime(6) NOT NULL DEFAULT (utc_timestamp(6)),
  `updated_at` datetime(6) NOT NULL DEFAULT (utc_timestamp(6))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `wds_weather_hourly_forecast`
--

DROP TABLE IF EXISTS `wds_weather_hourly_forecast`;
CREATE TABLE `wds_weather_hourly_forecast` (
  `location_id` bigint NOT NULL,
  `run_time_utc` datetime(6) NOT NULL,
  `forecast_time_utc` datetime(6) NOT NULL,
  `temp_c` int DEFAULT NULL,
  `wmo_code` int DEFAULT NULL,
  `precip_mm_tenths` int DEFAULT NULL,
  `precip_prob_pct` int DEFAULT NULL,
  `wind_kph_tenths` int DEFAULT NULL,
  `gust_kph_tenths` int DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT (utc_timestamp(6)),
  `updated_at` datetime(6) NOT NULL DEFAULT (utc_timestamp(6))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `wds_weather_hourly_observed`
--

DROP TABLE IF EXISTS `wds_weather_hourly_observed`;
CREATE TABLE `wds_weather_hourly_observed` (
  `location_id` bigint NOT NULL,
  `obs_time_utc` datetime(6) NOT NULL,
  `temp_c` int DEFAULT NULL,
  `wmo_code` int DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT (utc_timestamp(6)),
  `updated_at` datetime(6) NOT NULL DEFAULT (utc_timestamp(6))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `zh_somlist`
--

DROP TABLE IF EXISTS `zh_somlist`;
CREATE TABLE `zh_somlist` (
  `ID` int NOT NULL,
  `dt` datetime NOT NULL,
  `unidt` int NOT NULL,
  `cas_in` decimal(15,2) NOT NULL,
  `cas_out` decimal(15,2) NOT NULL,
  `bk_in` decimal(15,2) NOT NULL,
  `bk_out` decimal(15,2) NOT NULL,
  `fenh` decimal(15,2) NOT NULL,
  `huank` decimal(15,2) NOT NULL,
  `allin` decimal(15,2) NOT NULL,
  `allout` decimal(15,2) NOT NULL,
  `lirun` decimal(15,2) NOT NULL,
  `gzpt` decimal(15,2) NOT NULL,
  `gzsushi` decimal(15,2) NOT NULL,
  `gzcocina` decimal(15,2) NOT NULL,
  `nid` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `zh_som_tou`
--

DROP TABLE IF EXISTS `zh_som_tou`;
CREATE TABLE `zh_som_tou` (
  `ID` int NOT NULL,
  `dt` datetime NOT NULL,
  `unidt` int NOT NULL,
  `huobi` varchar(255) NOT NULL,
  `qtype` int NOT NULL,
  `qian` decimal(15,2) NOT NULL,
  `beizhu` varchar(255) DEFAULT NULL,
  `nid` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- 表的结构 `zh_ssom`
--

DROP TABLE IF EXISTS `zh_ssom`;
CREATE TABLE `zh_ssom` (
  `ID` int NOT NULL,
  `dt` datetime NOT NULL,
  `unidt` int NOT NULL,
  `cas_out` decimal(15,2) NOT NULL,
  `cas_in` decimal(15,2) NOT NULL,
  `cas` decimal(15,2) NOT NULL,
  `bk_out` decimal(15,2) NOT NULL,
  `bk_in` decimal(15,2) NOT NULL,
  `bk` decimal(15,2) NOT NULL,
  `fenh` decimal(15,2) NOT NULL,
  `huank` decimal(15,2) NOT NULL,
  `pam` int DEFAULT NULL,
  `ppm` int DEFAULT NULL,
  `football` varchar(255) DEFAULT NULL,
  `fbtime` varchar(255) DEFAULT NULL,
  `nid` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- 转储表的索引
--

--
-- 表的索引 `cp_dts_entry`
--
ALTER TABLE `cp_dts_entry`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_code_source` (`dts_code`,`source`,`som_id`),
  ADD KEY `idx_type` (`entry_type`),
  ADD KEY `idx_date` (`date_mode`,`date_value`,`start_date`,`end_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`);

--
-- 表的索引 `cp_dts_event`
--
ALTER TABLE `cp_dts_event`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_object_id` (`object_id`),
  ADD KEY `idx_subject_id` (`subject_id`),
  ADD KEY `idx_rule_id` (`rule_id`),
  ADD KEY `idx_event_date` (`event_date`),
  ADD KEY `idx_event_type` (`event_type`);

--
-- 表的索引 `cp_dts_object`
--
ALTER TABLE `cp_dts_object`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_subject_id` (`subject_id`),
  ADD KEY `idx_object_type` (`object_type_main`,`object_type_sub`),
  ADD KEY `idx_active_flag` (`active_flag`);

--
-- 表的索引 `cp_dts_object_state`
--
ALTER TABLE `cp_dts_object_state`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_object_id` (`object_id`),
  ADD KEY `idx_next_deadline` (`next_deadline_date`),
  ADD KEY `idx_next_cycle` (`next_cycle_date`),
  ADD KEY `idx_next_follow_up` (`next_follow_up_date`);

--
-- 表的索引 `cp_dts_rule`
--
ALTER TABLE `cp_dts_rule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rule_type` (`rule_type`),
  ADD KEY `idx_cat` (`cat_main`,`cat_sub`),
  ADD KEY `idx_rule_status` (`rule_status`);

--
-- 表的索引 `cp_dts_subject`
--
ALTER TABLE `cp_dts_subject`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_subject_status` (`subject_status`),
  ADD KEY `idx_subject_name` (`subject_name`);

--
-- 表的索引 `obs_daily_summaries`
--
ALTER TABLE `obs_daily_summaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_daily` (`store_id`,`summary_date`,`gender_age_key`);

--
-- 表的索引 `obs_people_logs`
--
ALTER TABLE `obs_people_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logs_store_time` (`store_id`,`recorded_at_utc`),
  ADD KEY `idx_logs_dims` (`store_id`,`gender_age_key`,`action_key`);

--
-- 表的索引 `obs_stores`
--
ALTER TABLE `obs_stores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_obs_store_code` (`store_code`);

--
-- 表的索引 `prs_import_batches`
--
ALTER TABLE `prs_import_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_prs_batch` (`store_id`,`date_local`,`raw_payload_sha256`),
  ADD KEY `idx_prs_batch_store_date` (`store_id`,`date_local`);

--
-- 表的索引 `prs_price_observations`
--
ALTER TABLE `prs_price_observations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_prs_obs_idem` (`product_id`,`store_id`,`date_local`,`source_line_fingerprint`),
  ADD KEY `fk_prs_obs_store` (`store_id`),
  ADD KEY `fk_prs_obs_batch` (`batch_id`),
  ADD KEY `idx_prs_obs_prod_store_date` (`product_id`,`store_id`,`date_local`);

--
-- 表的索引 `prs_products`
--
ALTER TABLE `prs_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_prs_products_es_cat` (`name_es`,`category`),
  ADD KEY `idx_prs_products_cat_name` (`category`,`name_es`);

--
-- 表的索引 `prs_product_aliases`
--
ALTER TABLE `prs_product_aliases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_prs_alias` (`alias_text`,`lang`),
  ADD KEY `idx_prs_alias_product` (`product_id`);

--
-- 表的索引 `prs_stores`
--
ALTER TABLE `prs_stores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_prs_store_name` (`store_name`);

--
-- 表的索引 `sushisom_daily_operations`
--
ALTER TABLE `sushisom_daily_operations`
  ADD PRIMARY KEY (`ss_daily_id`),
  ADD UNIQUE KEY `uk_ss_daily_date` (`ss_daily_date`),
  ADD UNIQUE KEY `unique_daily_date` (`ss_daily_date`),
  ADD UNIQUE KEY `idx_uuid` (`ss_daily_uuid`);

--
-- 表的索引 `sushisom_financial_transactions`
--
ALTER TABLE `sushisom_financial_transactions`
  ADD PRIMARY KEY (`ss_fin_id`),
  ADD KEY `idx_ss_fin_date` (`ss_fin_date`),
  ADD KEY `idx_ss_fin_category` (`ss_fin_category`),
  ADD KEY `idx_op_uuid` (`ss_fin_op_uuid`);

--
-- 表的索引 `sushisom_monthly_salaries`
--
ALTER TABLE `sushisom_monthly_salaries`
  ADD PRIMARY KEY (`ss_ms_id`),
  ADD UNIQUE KEY `salary_month` (`salary_month`);

--
-- 表的索引 `sys_users`
--
ALTER TABLE `sys_users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uk_user_login` (`user_login`),
  ADD UNIQUE KEY `uk_user_email` (`user_email`);

--
-- 表的索引 `tea_financial_transactions`
--
ALTER TABLE `tea_financial_transactions`
  ADD PRIMARY KEY (`tea_fin_id`),
  ADD KEY `idx_tea_date` (`tea_date`),
  ADD KEY `idx_tea_type` (`tea_type`),
  ADD KEY `idx_tea_store` (`tea_store`);

--
-- 表的索引 `tea_stores`
--
ALTER TABLE `tea_stores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `store_name` (`store_name`);

--
-- 表的索引 `wds_business_hours`
--
ALTER TABLE `wds_business_hours`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `wds_holidays`
--
ALTER TABLE `wds_holidays`
  ADD PRIMARY KEY (`scope_key`),
  ADD KEY `idx_wds_holiday_date` (`date`);

--
-- 表的索引 `wds_locations`
--
ALTER TABLE `wds_locations`
  ADD PRIMARY KEY (`location_id`);

--
-- 表的索引 `wds_weather_hourly_forecast`
--
ALTER TABLE `wds_weather_hourly_forecast`
  ADD PRIMARY KEY (`location_id`,`forecast_time_utc`,`run_time_utc`),
  ADD KEY `idx_wds_fc_run` (`run_time_utc`),
  ADD KEY `idx_wds_fc_ft` (`forecast_time_utc`);

--
-- 表的索引 `wds_weather_hourly_observed`
--
ALTER TABLE `wds_weather_hourly_observed`
  ADD PRIMARY KEY (`location_id`,`obs_time_utc`),
  ADD KEY `idx_wds_ob_t` (`obs_time_utc`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `cp_dts_entry`
--
ALTER TABLE `cp_dts_entry`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID';

--
-- 使用表AUTO_INCREMENT `cp_dts_event`
--
ALTER TABLE `cp_dts_event`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID';

--
-- 使用表AUTO_INCREMENT `cp_dts_object`
--
ALTER TABLE `cp_dts_object`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID';

--
-- 使用表AUTO_INCREMENT `cp_dts_object_state`
--
ALTER TABLE `cp_dts_object_state`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID';

--
-- 使用表AUTO_INCREMENT `cp_dts_rule`
--
ALTER TABLE `cp_dts_rule`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID';

--
-- 使用表AUTO_INCREMENT `cp_dts_subject`
--
ALTER TABLE `cp_dts_subject`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID';

--
-- 使用表AUTO_INCREMENT `obs_daily_summaries`
--
ALTER TABLE `obs_daily_summaries`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `obs_people_logs`
--
ALTER TABLE `obs_people_logs`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `obs_stores`
--
ALTER TABLE `obs_stores`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `prs_import_batches`
--
ALTER TABLE `prs_import_batches`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `prs_price_observations`
--
ALTER TABLE `prs_price_observations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `prs_products`
--
ALTER TABLE `prs_products`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `prs_product_aliases`
--
ALTER TABLE `prs_product_aliases`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `prs_stores`
--
ALTER TABLE `prs_stores`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `sushisom_daily_operations`
--
ALTER TABLE `sushisom_daily_operations`
  MODIFY `ss_daily_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '日常经营记录唯一ID (主键)';

--
-- 使用表AUTO_INCREMENT `sushisom_financial_transactions`
--
ALTER TABLE `sushisom_financial_transactions`
  MODIFY `ss_fin_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '财务流水唯一ID (主键)';

--
-- 使用表AUTO_INCREMENT `sushisom_monthly_salaries`
--
ALTER TABLE `sushisom_monthly_salaries`
  MODIFY `ss_ms_id` int NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `sys_users`
--
ALTER TABLE `sys_users`
  MODIFY `user_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '用户唯一ID (主键)';

--
-- 使用表AUTO_INCREMENT `tea_financial_transactions`
--
ALTER TABLE `tea_financial_transactions`
  MODIFY `tea_fin_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '金融交易流水唯一ID (主键)';

--
-- 使用表AUTO_INCREMENT `tea_stores`
--
ALTER TABLE `tea_stores`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `wds_locations`
--
ALTER TABLE `wds_locations`
  MODIFY `location_id` bigint NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- 视图结构 `prs_season_monthly_v2`
--
DROP TABLE IF EXISTS `prs_season_monthly_v2`;

DROP VIEW IF EXISTS `prs_season_monthly_v2`;
CREATE ALGORITHM=UNDEFINED DEFINER=`mhdlmskp2kpxguj`@`%` SQL SECURITY DEFINER VIEW `prs_season_monthly_v2`  AS WITH   `days` as (select distinct `prs_price_observations`.`product_id` AS `product_id`,`prs_price_observations`.`store_id` AS `store_id`,`prs_price_observations`.`date_local` AS `date_local` from `prs_price_observations`), `m` as (select `days`.`product_id` AS `product_id`,`days`.`store_id` AS `store_id`,date_format(`days`.`date_local`,'%Y-%m') AS `ym`,count(0) AS `days_with_obs` from `days` group by `days`.`product_id`,`days`.`store_id`,`ym`) select `m`.`product_id` AS `product_id`,`m`.`store_id` AS `store_id`,`m`.`ym` AS `ym`,`m`.`days_with_obs` AS `days_with_obs`,(case when (`m`.`days_with_obs` >= 1) then 1 else 0 end) AS `is_in_market_month` from `m`  ;

-- --------------------------------------------------------

--
-- 视图结构 `prs_stockout_segments_v2`
--
DROP TABLE IF EXISTS `prs_stockout_segments_v2`;

DROP VIEW IF EXISTS `prs_stockout_segments_v2`;
CREATE ALGORITHM=UNDEFINED DEFINER=`mhdlmskp2kpxguj`@`%` SQL SECURITY DEFINER VIEW `prs_stockout_segments_v2`  AS WITH   `days` as (select distinct `prs_price_observations`.`product_id` AS `product_id`,`prs_price_observations`.`store_id` AS `store_id`,`prs_price_observations`.`date_local` AS `date_local` from `prs_price_observations`), `seq` as (select `days`.`product_id` AS `product_id`,`days`.`store_id` AS `store_id`,`days`.`date_local` AS `date_local`,lag(`days`.`date_local`) OVER (PARTITION BY `days`.`product_id`,`days`.`store_id` ORDER BY `days`.`date_local` )  AS `prev_date` from `days`) select `seq`.`product_id` AS `product_id`,`seq`.`store_id` AS `store_id`,(`seq`.`prev_date` + interval 1 day) AS `gap_start`,(`seq`.`date_local` - interval 1 day) AS `gap_end`,((to_days(`seq`.`date_local`) - to_days(`seq`.`prev_date`)) - 1) AS `gap_days` from `seq` where ((`seq`.`prev_date` is not null) and ((to_days(`seq`.`date_local`) - to_days(`seq`.`prev_date`)) > 1))  ;

--
-- 限制导出的表
--

--
-- 限制表 `cp_dts_event`
--
ALTER TABLE `cp_dts_event`
  ADD CONSTRAINT `fk_event_object` FOREIGN KEY (`object_id`) REFERENCES `cp_dts_object` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_event_rule` FOREIGN KEY (`rule_id`) REFERENCES `cp_dts_rule` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_event_subject` FOREIGN KEY (`subject_id`) REFERENCES `cp_dts_subject` (`id`) ON DELETE CASCADE;

--
-- 限制表 `cp_dts_object`
--
ALTER TABLE `cp_dts_object`
  ADD CONSTRAINT `fk_object_subject` FOREIGN KEY (`subject_id`) REFERENCES `cp_dts_subject` (`id`) ON DELETE CASCADE;

--
-- 限制表 `cp_dts_object_state`
--
ALTER TABLE `cp_dts_object_state`
  ADD CONSTRAINT `fk_state_object` FOREIGN KEY (`object_id`) REFERENCES `cp_dts_object` (`id`) ON DELETE CASCADE;

--
-- 限制表 `obs_daily_summaries`
--
ALTER TABLE `obs_daily_summaries`
  ADD CONSTRAINT `fk_daily_store` FOREIGN KEY (`store_id`) REFERENCES `obs_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- 限制表 `obs_people_logs`
--
ALTER TABLE `obs_people_logs`
  ADD CONSTRAINT `fk_logs_store` FOREIGN KEY (`store_id`) REFERENCES `obs_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- 限制表 `prs_import_batches`
--
ALTER TABLE `prs_import_batches`
  ADD CONSTRAINT `fk_prs_batch_store` FOREIGN KEY (`store_id`) REFERENCES `prs_stores` (`id`) ON DELETE RESTRICT;

--
-- 限制表 `prs_price_observations`
--
ALTER TABLE `prs_price_observations`
  ADD CONSTRAINT `fk_prs_obs_batch` FOREIGN KEY (`batch_id`) REFERENCES `prs_import_batches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_prs_obs_product` FOREIGN KEY (`product_id`) REFERENCES `prs_products` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_prs_obs_store` FOREIGN KEY (`store_id`) REFERENCES `prs_stores` (`id`) ON DELETE RESTRICT;

--
-- 限制表 `prs_product_aliases`
--
ALTER TABLE `prs_product_aliases`
  ADD CONSTRAINT `fk_prs_alias_product` FOREIGN KEY (`product_id`) REFERENCES `prs_products` (`id`) ON DELETE CASCADE;

--
-- 限制表 `sushisom_financial_transactions`
--
ALTER TABLE `sushisom_financial_transactions`
  ADD CONSTRAINT `fk_financial_op_uuid` FOREIGN KEY (`ss_fin_op_uuid`) REFERENCES `sushisom_daily_operations` (`ss_daily_uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
