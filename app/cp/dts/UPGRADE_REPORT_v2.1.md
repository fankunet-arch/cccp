# DTS v2.1 升级报告

## 1. 修改的文件清单
- `app/cp/dts/dts_schema.sql`
- `app/cp/dts/dts_lib.php`
- `app/cp/dts/actions/dts_action_quick_save.php`
- `app/cp/dts/actions/dts_ev_save.php`
- `app/cp/dts/views/dts_main.php`
- `app/cp/dts/views/dts_object_detail.php`
- `app/cp/dts/views/dts_view_quick.php`

## 2. 新增函数 & 功能说明
- **统一保存入口**: 在 `dts_lib.php` 中实现了 `dts_save_object` 和 `dts_save_event`，确保所有入口（极速录入、常规编辑）逻辑一致，杜绝数据不一致问题。
- **默认参数自动匹配**: 新增 `dts_match_default_rule` 函数。在保存事件时，若未指定规则，系统会根据对象的“大类+小类”在 `cp_dts_rule` 表中查找匹配的规则，并自动应用（如自动计算过期日、锁定日）。
- **双轨状态计算**: 升级 `dts_update_object_state`。
  - **Deadline 轨**: 保持原有逻辑（过期日、周期日、跟进日）。
  - **Lock-in 轨**: 新增逻辑，若规则包含 `lock_days`，则计算 `locked_until_date`（锁定截止日），在此日期前对象处于“锁定/保护”状态。

## 3. Schema 改动
- **cp_dts_rule**: 新增 `lock_days` (INT) 字段，用于定义锁定天数。
- **cp_dts_object_state**: 新增 `locked_until_date` (DATE) 字段，用于存储计算后的锁定截止日。

## 4. 视图增强点
- **dts_main.php**: 列表增加“锁定中”状态展示（灰色锁图标），当对象处于锁定保护期时显示，替代原有的紧急度提示，避免误操作。
- **dts_object_detail.php**: 详情页顶部增加灰色 Alert 区块，仅在对象处于锁定状态时显示，明确提示“锁定直至 YYYY-MM-DD”。
- **dts_view_quick.php**: 在规则选择器下方增加 Helper Text，明确告知用户“如未选择，将自动匹配默认规则”，提升用户体验。

## 5. 自测结果
- **场景1：极速录入 + 默认规则**
  - 结果：**通过**。不选规则提交后，事件自动关联了对应的 default rule，并正确计算了 Next Deadline。
- **场景2：手动选择 lock_days=30 的规则**
  - 结果：**通过**。保存后，对象状态表 `locked_until_date` 正确更新为 `event_date + 30 days`。详情页显示“锁定中”。
- **场景3：旧版对象编辑器 → 不改变状态/事件**
  - 结果：**通过**。使用重构后的 `dts_ev_save.php` 保存，逻辑与极速录入一致，无 Regression。
- **场景4：视图展示一致性**
  - 结果：**通过**。总览页和详情页对同一锁定对象的展示状态一致（均为锁定提示），颜色符合 UI 规范（灰色/Secondary）。

## 6. 发布建议
- **建议等级**: **A (直接发布)**
- **原因**: 核心逻辑已通过统一函数封装，最大限度降低了副作用。Schema 变更向下兼容（新增字段，默认 NULL）。双轨制计算逻辑独立，不干扰原有 Deadline 计算。
