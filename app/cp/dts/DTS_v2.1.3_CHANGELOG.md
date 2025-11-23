# DTS v2.1.3 更新日志

**发布日期**: 2025-11-23
**核心目标**: 实现事件录入时的规则灵活性，从单一的"自动匹配"升级为"自动/指定/自定义"三种模式

---

## 📋 更新概述

DTS v2.1.3 引入了**规则模式升级**，允许用户在录入事件时选择三种不同的规则应用方式：

- **模式 A (自动匹配)**：系统根据对象分类自动匹配默认规则
- **模式 B (指定规则)**：用户手动选择特定规则强制应用
- **模式 C (自定义)**：用户直接设置具体日期，不依赖任何规则

---

## ✨ 核心功能

### 1. 三种规则模式

#### 模式 A：默认规则 (Auto/Default)
- **交互表现**：无需额外配置，系统自动处理
- **后端逻辑**：`rule_id` 为空，`custom_*` 字段为空，系统根据大类/小类自动匹配默认规则
- **适用场景**：常规业务，完全依赖系统预设配置

#### 模式 B：指定规则 (Select Rule)
- **交互表现**：显示规则下拉菜单，用户手动选择
- **后端逻辑**：记录用户选中的 `rule_id`，强制使用该规则计算状态
- **适用场景**：对象特殊，需要使用非默认的特定规则（如"VIP 快速通道规则"）

#### 模式 C：自定义 (Custom/Manual)
- **交互表现**：显示四个日期选择框
- **后端逻辑**：直接保存用户输入的具体日期到 `custom_*` 字段
- **适用场景**：完全非标准化的业务，如"特批延期"、"临时调整"

### 2. 自定义日期字段

新增四个自定义日期字段，用于模式 C（自定义）：

| 字段名称 | 数据库字段 | 说明 |
|---------|-----------|------|
| 锁定截止日 | `custom_lock_date` | 事件锁定至此日期，期间不可再次操作 |
| 窗口期开始 | `custom_window_start` | 可办理/操作的最早日期 |
| 窗口期结束 | `custom_window_end` | 可办理/操作的最晚日期 |
| 跟进日期 | `custom_follow_up_date` | 下次跟进/检查的日期 |

---

## 🗄️ 数据库变更

### 新增字段（cp_dts_event 表）

```sql
ALTER TABLE `cp_dts_event`
ADD COLUMN `custom_lock_date` DATE DEFAULT NULL COMMENT '自定义锁定截止日（模式C：自定义）' AFTER `note`,
ADD COLUMN `custom_window_start` DATE DEFAULT NULL COMMENT '自定义窗口期开始日（模式C：自定义）' AFTER `custom_lock_date`,
ADD COLUMN `custom_window_end` DATE DEFAULT NULL COMMENT '自定义窗口期结束日（模式C：自定义）' AFTER `custom_window_start`,
ADD COLUMN `custom_follow_up_date` DATE DEFAULT NULL COMMENT '自定义跟进日期（模式C：自定义）' AFTER `custom_window_end`;
```

### 新增索引

```sql
ALTER TABLE `cp_dts_event`
ADD INDEX `idx_custom_follow_up` (`custom_follow_up_date`);
```

**迁移文件**: `app/cp/dts/migrations/dts_v2.1.3_migration.sql`

---

## 🎯 业务逻辑优先级规则

在计算对象当前状态 (`cp_dts_object_state`) 时，严格遵循以下优先级（由高到低）：

1. **最高优先级 (Custom)**: 如果最新事件中存在 `custom_*` 字段的值，直接使用该日期，忽略规则计算逻辑
2. **次优先级 (Rule ID)**: 如果最新事件指定了 `rule_id`，使用该规则的参数基于事件日期进行计算
3. **低优先级 (Default)**: 如果既无自定义字段也无 `rule_id`，尝试按分类自动匹配默认规则进行计算

**实现位置**: `dts_lib.php` → `dts_update_object_state()` 函数

---

## 📝 代码变更清单

### 1. 前端变更

#### 文件：`app/cp/dts/views/dts_view_quick.php`
- **新增**：三种规则模式的单选按钮组
- **新增**：模式 A 的提示信息区域
- **新增**：模式 B 的规则下拉选择器
- **新增**：模式 C 的四个自定义日期输入框
- **新增**：JavaScript 模式切换逻辑（滑动显隐效果）
- **新增**：日期输入框交互优化（点击任意位置弹出选择器）
- **修改**：数据加载逻辑，支持编辑模式时自动识别规则模式

**关键代码行**：
- 行 39-44：新增 `custom_*` 和 `rule_mode` 字段初始化
- 行 79-96：编辑模式下自动判断规则模式
- 行 362-460：三种规则模式的 UI 实现
- 行 605-700：JavaScript 模式切换和日期交互逻辑

### 2. 后端变更

#### 文件：`app/cp/dts/actions/dts_action_quick_save.php`
- **修改**：事件参数中新增 `custom_*` 字段和 `rule_mode` 字段传递
- **影响范围**：ev_add 模式和普通新建/编辑模式

**关键代码行**：
- 行 65-70：ev_add 模式的 custom 字段传递
- 行 145-150：普通模式的 custom 字段传递

#### 文件：`app/cp/dts/actions/dts_ev_add.php`
- **修改**：POST 处理逻辑中新增 `custom_*` 字段和 `rule_mode` 字段传递
- **说明**：保持与 `dts_action_quick_save.php` 一致

**关键代码行**：
- 行 112-117：自定义字段传递

#### 文件：`app/cp/dts/dts_lib.php`

##### 函数：`dts_save_event()`
- **修改**：支持接收和保存 `custom_*` 字段
- **修改**：根据 `rule_mode` 参数决定规则处理逻辑
- **新增**：三种模式的区分处理日志

**关键代码行**：
- 行 557-570：函数注释更新
- 行 575-603：三种规则模式的区分处理
- 行 615-619：custom 字段添加到数据数组
- 行 634-637：UPDATE 语句添加 custom 字段
- 行 652-653：INSERT 语句添加 custom 字段

##### 函数：`dts_update_object_state()`
- **修改**：实现优先级规则（自定义 > 指定规则 > 默认规则）
- **新增**：自定义字段的优先检查和直接赋值逻辑
- **优化**：日志记录，标识使用的模式

**关键代码行**：
- 行 166-175：函数注释更新
- 行 201-228：优先级 1 - 自定义字段处理
- 行 230-271：优先级 2 - 指定规则处理
- 行 273-281：优先级 3 - 默认规则/兜底逻辑

---

## 🧪 验收测试清单

| 序号 | 检查项 | 预期结果 | 状态 |
|------|--------|---------|------|
| 1 | 双入口覆盖 | 极速录入(dts_quick) 和 追加事件(dts_ops&op=ev_add) 界面均包含三种模式选择 | ⏳ 待测试 |
| 2 | 自动匹配测试 | 选择模式 A 保存，数据库 rule_id 为 NULL，但对象详情页正确应用默认规则逻辑 | ⏳ 待测试 |
| 3 | 手动指定测试 | 选择模式 B 并指定规则 X，即使对象默认应匹配规则 Y，最终状态计算仍基于规则 X | ⏳ 待测试 |
| 4 | 自定义持久化 | 选择模式 C 并输入"锁定至 2025-12-31"，查看数据库 cp_dts_event 表，custom_lock_date 字段确切存储了 2025-12-31 | ⏳ 待测试 |
| 5 | 状态回溯性 | 删除自定义事件后，对象状态回退到上一个事件；重新添加后，状态再次变为自定义设定值 | ⏳ 待测试 |
| 6 | 日期交互 | 点击任何日期输入框的空白处，浏览器原生日期选择器均能正常弹出 | ⏳ 待测试 |
| 7 | 编辑模式识别 | 编辑已有事件时，系统能自动识别并选中正确的规则模式（A/B/C） | ⏳ 待测试 |
| 8 | 模式切换动画 | 切换规则模式时，内容区域有流畅的滑动显隐效果 | ⏳ 待测试 |

---

## 📂 文件清单

### 新增文件
- `app/cp/dts/migrations/dts_v2.1.3_migration.sql` - 数据库迁移脚本
- `app/cp/dts/DTS_v2.1.3_CHANGELOG.md` - 本更新日志

### 修改文件
- `app/cp/dts/views/dts_view_quick.php` - 极速录入页面（前端 UI + JavaScript）
- `app/cp/dts/actions/dts_action_quick_save.php` - 极速录入保存逻辑
- `app/cp/dts/actions/dts_ev_add.php` - 追加事件保存逻辑
- `app/cp/dts/dts_lib.php` - 核心库（dts_save_event + dts_update_object_state）

### 保留但已被替代的文件（建议标记为废弃）
- `app/cp/dts/views/dts_ev_add.php` - 旧的追加事件视图（已被 dts_view_quick.php 替代）

---

## 🔄 升级步骤

1. **备份数据库**
   ```bash
   mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
   ```

2. **执行数据库迁移**
   ```bash
   mysql -u username -p database_name < app/cp/dts/migrations/dts_v2.1.3_migration.sql
   ```

3. **验证迁移结果**
   ```sql
   SHOW COLUMNS FROM cp_dts_event LIKE 'custom_%';
   SHOW INDEX FROM cp_dts_event WHERE Key_name = 'idx_custom_follow_up';
   ```

4. **部署代码**
   - 更新所有修改的 PHP 文件
   - 清除 PHP OpCache（如果启用）
   ```bash
   service php-fpm reload  # 或根据实际环境调整
   ```

5. **验收测试**
   - 按照"验收测试清单"逐项测试
   - 特别关注模式 C（自定义）的数据持久化

---

## ⚠️ 注意事项

1. **兼容性**
   - v2.1.3 完全向下兼容 v2.1.x 的数据
   - 旧事件记录（无 custom_* 字段）将自动按模式 A 或模式 B 处理

2. **数据完整性**
   - 自定义字段允许为空，不影响现有业务逻辑
   - 建议定期检查 custom_* 字段的使用情况

3. **性能考虑**
   - 新增的索引 `idx_custom_follow_up` 可优化跟进日期查询
   - 状态计算逻辑增加了优先级判断，但性能影响可忽略不计

4. **用户培训**
   - 建议向用户说明三种模式的区别和适用场景
   - 特别强调模式 C（自定义）的使用场景（非标准化业务）

---

## 📞 技术支持

如有问题，请联系开发团队或查阅以下文档：
- DTS 系统文档：`app/cp/dts/dts_readme.md`
- 数据库 Schema：`app/cp/dts/dts_schema.sql`

---

**变更记录**:
- 2025-11-23: v2.1.3 初始发布
