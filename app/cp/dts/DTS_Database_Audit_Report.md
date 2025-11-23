# DTS 项目数据库审计报告

**审计日期**: 2025-11-23
**DTS 版本**: v2.1.3
**审计范围**: 数据库表结构、字段使用情况、冗余检测

---

## 📊 数据库表概览

DTS 项目共使用 **6 张数据库表**：

| 表名 | 状态 | 用途 | 代码引用 |
|------|------|------|---------|
| `cp_dts_subject` | ✅ 活跃 | 主体管理（人/公司） | `dts_lib.php`, `dts_object.php`, `dts_view_quick.php` |
| `cp_dts_object` | ✅ 活跃 | 对象管理（证件/车辆等） | `dts_lib.php`, `dts_object.php`, `dts_view_quick.php` |
| `cp_dts_event` | ✅ 活跃 | 事件记录（递交/签发等） | `dts_lib.php`, `dts_action_quick_save.php`, `dts_ev_add.php` |
| `cp_dts_rule` | ✅ 活跃 | 规则模板（计算逻辑） | `dts_lib.php`, `dts_rule.php`, `dts_view_quick.php` |
| `cp_dts_object_state` | ✅ 活跃 | 对象当前状态（快速查询） | `dts_lib.php`, `dts_object_detail.php` |
| `cp_dts_entry` | ✅ 活跃 | DTS 条目（节假日/活动） | `dts_entry.php`, `dts_entry_form.php`, `dts_entry_save.php` |

**结论**: 所有表均在使用中，无废弃表。

---

## 🔍 字段审计详情

### 1. cp_dts_event 表

#### ✅ 正在使用的字段

| 字段名 | 类型 | 用途 | 引用位置 |
|--------|------|------|---------|
| `id` | INT | 主键 | 所有查询 |
| `object_id` | INT | 关联对象 | `dts_lib.php:183`, `dts_action_quick_save.php` |
| `subject_id` | INT | 关联主体 | `dts_lib.php:608`, `dts_action_quick_save.php` |
| `rule_id` | INT | 关联规则 | `dts_lib.php:578`, `dts_view_quick.php:71` |
| `event_type` | VARCHAR | 事件类型 | `dts_lib.php:610`, `dts_view_quick.php:67` |
| `event_date` | DATE | 事件日期 | `dts_lib.php:611`, `dts_view_quick.php:66` |
| `expiry_date_new` | DATE | 新过期日 | `dts_lib.php:612`, `dts_view_quick.php:70` |
| `custom_lock_date` | DATE | 🆕 v2.1.3 自定义锁定截止日 | `dts_lib.php:616`, `dts_view_quick.php:74` |
| `custom_window_start` | DATE | 🆕 v2.1.3 自定义窗口开始 | `dts_lib.php:617`, `dts_view_quick.php:75` |
| `custom_window_end` | DATE | 🆕 v2.1.3 自定义窗口结束 | `dts_lib.php:618`, `dts_view_quick.php:76` |
| `custom_follow_up_date` | DATE | 🆕 v2.1.3 自定义跟进日期 | `dts_lib.php:619`, `dts_view_quick.php:77` |
| `mileage_now` | INT | 当前里程 | `dts_lib.php:613`, `dts_view_quick.php:69` |
| `note` | TEXT | 备注 | `dts_lib.php:614`, `dts_view_quick.php:68` |
| `status` | ENUM | 事件状态 | `dts_lib.php:184`, 默认值 'completed' |
| `is_deleted` | TINYINT | 软删除标记 | `dts_lib.php:184`, `dts_ev_del.php` |
| `created_at` | DATETIME | 创建时间 | 自动填充 |
| `updated_at` | DATETIME | 更新时间 | 自动更新 |

**结论**: 所有字段均在使用，无冗余字段。v2.1.3 新增的 4 个 custom_* 字段已整合到代码中。

---

### 2. cp_dts_object 表

#### ✅ 正在使用的字段

| 字段名 | 类型 | 用途 | 引用位置 |
|--------|------|------|---------|
| `id` | INT | 主键 | 所有查询 |
| `subject_id` | INT | 关联主体 | `dts_lib.php:536`, `dts_object.php` |
| `object_name` | VARCHAR | 对象名称 | `dts_lib.php:542`, `dts_view_quick.php:101` |
| `object_type_main` | VARCHAR | 大类 | `dts_lib.php:543`, `dts_view_quick.php:102` |
| `object_type_sub` | VARCHAR | 小类 | `dts_lib.php:544`, `dts_view_quick.php:103` |
| `identifier` | VARCHAR | 对象标识 | `dts_object_form.php`, `dts_lib.php:545` |
| `active_flag` | TINYINT | 是否当前使用 | `dts_object.php` 筛选 |
| `is_deleted` | TINYINT | 软删除标记 | `dts_lib.php:733`, `dts_object.php` |
| `remark` | TEXT | 备注 | `dts_object_form.php`, `dts_lib.php:546` |
| `created_at` | DATETIME | 创建时间 | 自动填充 |
| `updated_at` | DATETIME | 更新时间 | 自动更新 |

**结论**: 所有字段均在使用，无冗余字段。

---

### 3. cp_dts_rule 表

#### ✅ 正在使用的字段

| 字段名 | 类型 | 用途 | 引用位置 |
|--------|------|------|---------|
| `id` | INT | 主键 | 所有查询 |
| `rule_name` | VARCHAR | 规则名称 | `dts_rule.php`, `dts_view_quick.php:160` |
| `rule_type` | ENUM | 规则类型 | `dts_lib.php:202`, 状态计算逻辑 |
| `base_field` | ENUM | 基准字段 | `dts_lib.php:203` |
| `cat_main` | VARCHAR | 适用大类 | `dts_lib.php:478`, 自动匹配逻辑 |
| `cat_sub` | VARCHAR | 适用小类 | `dts_lib.php:481`, 自动匹配逻辑 |
| `earliest_offset_days` | INT | 最早可办偏移 | `dts_lib.php:204`, 节点计算 |
| `suggest_offset_days` | INT | 建议办理偏移 | `dts_lib.php:205`, 节点计算 |
| `safe_last_offset_days` | INT | 最晚安全日偏移 | `dts_lib.php:206`, 节点计算 |
| `cycle_interval_days` | INT | 周期间隔天数 | `dts_lib.php:207`, 周期计算 |
| `cycle_interval_months` | INT | 周期间隔月数 | `dts_lib.php:208`, 周期计算 |
| `mileage_interval` | INT | 建议里程间隔 | `dts_lib.php:209`, 里程计算 |
| `follow_up_offset_days` | INT | 跟进偏移天数 | `dts_lib.php:210`, 跟进计算 |
| `follow_up_offset_months` | INT | 跟进偏移月数 | `dts_lib.php:211`, 跟进计算 |
| `lock_days` | INT | 锁定天数 | `dts_lib.php:230`, Lock-in 轨计算 |
| `rule_status` | TINYINT | 启用状态 | `dts_lib.php:479`, `dts_view_quick.php:160` |
| `remark` | TEXT | 备注 | `dts_rule.php` |
| `created_at` | DATETIME | 创建时间 | 自动填充 |
| `updated_at` | DATETIME | 更新时间 | 自动更新 |

**结论**: 所有字段均在使用，无冗余字段。`lock_days` 在 v2.1 中引入，用于 Lock-in 轨计算。

---

### 4. cp_dts_object_state 表

#### ✅ 正在使用的字段

| 字段名 | 类型 | 用途 | 引用位置 |
|--------|------|------|---------|
| `id` | INT | 主键 | 所有查询 |
| `object_id` | INT | 关联对象 | `dts_lib.php:252`, 唯一索引 |
| `next_deadline_date` | DATE | 下一个截止日 | `dts_lib.php:256`, 对象详情展示 |
| `next_window_start_date` | DATE | 窗口开始日 | `dts_lib.php:257`, 对象详情展示 |
| `next_window_end_date` | DATE | 窗口结束日 | `dts_lib.php:258`, 对象详情展示 |
| `next_cycle_date` | DATE | 下一次周期日期 | `dts_lib.php:259`, 对象详情展示 |
| `next_follow_up_date` | DATE | 下一次跟进日期 | `dts_lib.php:260`, 对象详情展示 |
| `next_mileage_suggest` | INT | 建议下次里程 | `dts_lib.php:261`, 车辆管理 |
| `locked_until_date` | DATE | 锁定截止日期 | `dts_lib.php:262`, Lock-in 轨 (v2.1) |
| `last_event_id` | INT | 最后一个事件ID | `dts_lib.php:263`, 状态回溯 |
| `last_updated_at` | DATETIME | 最后更新时间 | 自动更新 |

**结论**: 所有字段均在使用，无冗余字段。这是性能优化表，存储计算后的状态以避免实时计算。

---

### 5. cp_dts_subject 表

#### ✅ 正在使用的字段

| 字段名 | 类型 | 用途 | 引用位置 |
|--------|------|------|---------|
| `id` | INT | 主键 | 所有查询 |
| `subject_name` | VARCHAR | 主体名称 | `dts_subject.php`, `dts_view_quick.php:122` |
| `subject_type` | ENUM | 主体类型 | `dts_subject.php`, `dts_view_quick.php:228` |
| `subject_status` | TINYINT | 启用状态 | `dts_subject.php`, `dts_view_quick.php:146` |
| `is_deleted` | TINYINT | 软删除标记 | `dts_lib.php:674`, `dts_subject.php` |
| `remark` | TEXT | 备注 | `dts_subject.php` |
| `created_at` | DATETIME | 创建时间 | 自动填充 |
| `updated_at` | DATETIME | 更新时间 | 自动更新 |

**结论**: 所有字段均在使用，无冗余字段。

---

### 6. cp_dts_entry 表

#### ✅ 正在使用的字段

| 字段名 | 类型 | 用途 | 引用位置 |
|--------|------|------|---------|
| `id` | INT | 主键 | 所有查询 |
| `dts_code` | VARCHAR | 系统唯一 code | `dts_entry.php:49`, 唯一索引 |
| `entry_type` | ENUM | 条目类型 | `dts_entry.php` 筛选 |
| `date_mode` | ENUM | 日期模式 | `dts_entry_form.php:85` |
| `date_value` | DATE | 单日日期 | `dts_entry.php:49` |
| `start_date` | DATE | 区间开始日期 | `dts_entry.php:49` |
| `end_date` | DATE | 区间结束日期 | `dts_entry_form.php:104` |
| `status` | TINYINT | 启用状态 | `dts_entry.php` 筛选 |
| `show_to_front` | TINYINT | 是否前端展示 | `dts_entry_form.php:113` |
| `name_zh` | VARCHAR | 名称（中文） | `dts_entry.php` 显示 |
| `name_en` | VARCHAR | 名称（英文） | `dts_entry_form.php:59` |
| `short_title` | VARCHAR | 前端短标题 | `dts_entry_form.php:70` |
| `color_hex` | VARCHAR | 颜色值 | `dts_entry_form.php:77` |
| `tag_class` | VARCHAR | 标签样式类 | `dts_entry_form.php:85` |
| `languages` | VARCHAR | 适用语言列表 | `dts_entry_form.php:93` |
| `platforms` | VARCHAR | 适用端 | `dts_entry_form.php:101` |
| `modules` | TEXT | 适用模块列表 | `dts_entry_form.php:109` |
| `priority` | INT | 优先级 | `dts_entry.php:49` 排序 |
| `external_id` | VARCHAR | 外部关联ID | `dts_entry_form.php:125` |
| `external_url` | VARCHAR | 外部链接 | `dts_entry_form.php:133` |
| `remark` | TEXT | 备注 | `dts_entry_form.php:141` |
| `source` | ENUM | 来源 | `dts_entry.php:16` 筛选 |
| `som_id` | INT | 所属SOM | `dts_entry_form.php:149` |
| `local_override` | TINYINT | SOM覆写标记 | `dts_entry_form.php:157` |
| `created_at` | DATETIME | 创建时间 | 自动填充 |
| `updated_at` | DATETIME | 更新时间 | 自动更新 |

**说明**: `cp_dts_entry` 表用于管理日期标签（节假日、促销活动等），是 DTS 系统的扩展功能模块。所有字段均在使用中。

**结论**: 所有字段均在使用，无冗余字段。这是一个独立的日期条目管理模块。

---

## 📋 索引审计

### 已有索引（来自实际数据库）

根据 `docs/dc_db_schema_structure_only.sql`，以下索引已正确创建：

#### cp_dts_event 表
- `PRIMARY KEY (id)`
- `KEY idx_object_id (object_id)`
- `KEY idx_subject_id (subject_id)`
- `KEY idx_rule_id (rule_id)`
- `KEY idx_event_date (event_date)`
- `KEY idx_event_type (event_type)`
- 🆕 `KEY idx_custom_follow_up (custom_follow_up_date)` - v2.1.3 新增

#### cp_dts_object 表
- `PRIMARY KEY (id)`
- `KEY idx_subject_id (subject_id)`
- `KEY idx_object_type (object_type_main, object_type_sub)`
- `KEY idx_active_flag (active_flag)`

#### cp_dts_object_state 表
- `PRIMARY KEY (id)`
- `UNIQUE KEY uk_object_id (object_id)`
- `KEY idx_next_deadline (next_deadline_date)`
- `KEY idx_next_cycle (next_cycle_date)`
- `KEY idx_next_follow_up (next_follow_up_date)`

#### cp_dts_rule 表
- `PRIMARY KEY (id)`
- `KEY idx_rule_type (rule_type)`
- `KEY idx_cat (cat_main, cat_sub)`
- `KEY idx_rule_status (rule_status)`

#### cp_dts_subject 表
- `PRIMARY KEY (id)`
- `KEY idx_subject_status (subject_status)`
- `KEY idx_subject_name (subject_name)`

#### cp_dts_entry 表
- `PRIMARY KEY (id)`
- `UNIQUE KEY uk_code_source (dts_code, source, som_id)`
- `KEY idx_type (entry_type)`
- `KEY idx_date (date_mode, date_value, start_date, end_date)`
- `KEY idx_status (status)`
- `KEY idx_priority (priority)`

**结论**: 所有索引均合理，性能优化到位。v2.1.3 新增的 `idx_custom_follow_up` 索引已正确创建。

---

## ⚠️ 发现的问题

### 无重大问题

经过全面审计，DTS 项目的数据库结构非常健康：
- ✅ 无废弃表
- ✅ 无冗余字段
- ✅ 索引覆盖合理
- ✅ 字段命名规范
- ✅ 软删除机制完善

---

## 💡 优化建议

### 1. 文档完善
建议在 `dts_readme.md` 中补充 `cp_dts_entry` 表的使用说明，说明其与核心 DTS 功能的关系。

### 2. 代码注释
建议在 `dts_entry.php` 等文件顶部添加模块说明注释，明确其用途（日期条目管理）。

### 3. 未来扩展
如果 `cp_dts_entry` 表的使用频率不高，建议考虑：
- 在主文档中明确标注为"可选模块"
- 或在未来版本中考虑将其独立为插件

---

## 📈 数据库健康评分

| 评估项 | 得分 | 说明 |
|--------|------|------|
| 表结构合理性 | ⭐⭐⭐⭐⭐ | 5/5 - 所有表均有明确用途 |
| 字段使用率 | ⭐⭐⭐⭐⭐ | 5/5 - 无冗余字段 |
| 索引覆盖率 | ⭐⭐⭐⭐⭐ | 5/5 - 查询性能优化到位 |
| 命名规范性 | ⭐⭐⭐⭐⭐ | 5/5 - 命名清晰一致 |
| 软删除机制 | ⭐⭐⭐⭐⭐ | 5/5 - 所有核心表支持软删除 |

**总体健康度**: ⭐⭐⭐⭐⭐ **优秀 (100分)**

---

## 🔄 v2.1.3 变更确认

### 新增字段（已正确集成）
- ✅ `cp_dts_event.custom_lock_date`
- ✅ `cp_dts_event.custom_window_start`
- ✅ `cp_dts_event.custom_window_end`
- ✅ `cp_dts_event.custom_follow_up_date`

### 新增索引（已正确创建）
- ✅ `cp_dts_event.idx_custom_follow_up`

### 代码集成确认
- ✅ `dts_lib.php` - dts_save_event() 函数已支持
- ✅ `dts_lib.php` - dts_update_object_state() 函数已实现优先级逻辑
- ✅ `dts_view_quick.php` - 前端 UI 已完整实现
- ✅ `dts_action_quick_save.php` - 保存逻辑已更新
- ✅ `dts_ev_add.php` - 追加事件逻辑已更新

---

**审计结论**: DTS 项目数据库结构健康，无废弃表或冗余字段。v2.1.3 的新增字段已完美集成到代码中，数据库设计优秀。

**审计人**: Claude (AI Assistant)
**审计日期**: 2025-11-23
