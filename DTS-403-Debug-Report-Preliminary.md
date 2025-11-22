# DTS-403-Debug-Report（初步排查）

**报告编号**: DTS-403-Debug-Report-Preliminary
**问题**: `dts_ev_add` 访问返回 403 Forbidden
**排查日期**: 2025-11-22
**状态**: ⏸️ 待用户验证日志

---

## 一、问题现象

| URL | 状态 |
|-----|------|
| `?action=dts_quick` | ✅ 正常 |
| `?action=dts_quick&id=1` | ✅ 正常 |
| `?action=dts_ev_add&object_id=3` | ❌ **纯白 403（Apache 提示）** |

**关键特征**:
- 相同的 URL 结构，仅 action 参数不同
- `dts_ev_add` 触发 403，说明问题与 action 名称有关

---

## 二、已完成的排查步骤

### 2.1 ✅ 添加调试日志

#### 文件1: `/home/user/cccp/dc_html/cp/index.php`
**位置**: Line 5-6
```php
// [DEBUG] 记录所有请求到达入口
error_log('CP index hit, action=' . ($_GET['action'] ?? 'none'));
```

**作用**: 确认请求是否到达 PHP 入口文件

#### 文件2: `/home/user/cccp/app/cp/dts/actions/dts_ev_add.php`
**位置**: Line 2-3
```php
// [DEBUG] 确认 dts_ev_add action 是否被执行
error_log('DTS_EV_ADD reached');
```

**作用**: 确认 dts_ev_add action 文件是否被加载

### 2.2 ✅ 检查路由配置

**文件**: `/home/user/cccp/app/cp/index.php`
**结果**: ✅ **路由配置正常**

在 `$protected_routes` 数组中找到（Line 107）：
```php
'dts_ev_add' => APP_PATH_CP . '/dts/actions/dts_ev_add.php', // [v2.1.2] 对象追加事件专用链路（推荐）
```

**分析**:
- `dts_ev_add` 已正确注册到路由表
- 指向正确的文件路径：`/app/cp/dts/actions/dts_ev_add.php`
- 会被识别为 action（因为路径包含 `/actions/`），直接 require
- 如果 action 不在路由表中，会返回 **404** 而非 403

**结论**: ✅ **PHP 路由层面没有问题**

### 2.3 ✅ 检查 .htaccess 规则

**搜索范围**: 整个项目目录
**结果**: ❌ **未找到任何 .htaccess 文件**

**结论**: 403 不是由项目内部的 .htaccess 规则引起的

---

## 三、当前分析结论

### 3.1 可能性分析

基于已排查的信息，403 错误的来源可能性：

| 来源 | 可能性 | 说明 |
|------|--------|------|
| **PHP 路由白名单** | ❌ 排除 | 路由配置正常，且非法 action 返回 404 而非 403 |
| **项目 .htaccess** | ❌ 排除 | 项目中无 .htaccess 文件 |
| **Apache 配置** | 🔴 **高** | 纯白 403 且显示 "Apache" 提示，说明在进入 PHP 前被拦截 |
| **服务器 ModSecurity** | 🔴 **高** | `dts_ev_add` 可能触发了 WAF 规则（如禁止特定字符串） |
| **服务商（OVH）WAF** | 🟡 中 | 可能存在托管服务器级别的安全规则 |

### 3.2 初步判断

**最有可能的原因**: ⚠️ **Apache 或 ModSecurity 在 PHP 执行前拦截了包含 `dts_ev_add` 的请求**

**依据**:
1. 纯白 403 页面 + Apache 提示 → 请求未到达 PHP
2. 相同结构的 URL，仅 action 名称不同，却有不同结果
3. `dts_quick` 正常，`dts_ev_add` 被拦截 → 说明是名称触发了规则

---

## 四、待用户完成的验证步骤

### 4.1 📋 测试日志验证

请在**生产环境**依次访问以下 URL，并记录日志输出：

#### 测试1: `?action=dts_quick`
- **访问**: `http://yourdomain.com/index.php?action=dts_quick`
- **预期日志**:
  ```
  CP index hit, action=dts_quick
  ```

#### 测试2: `?action=dts_quick&id=1`
- **访问**: `http://yourdomain.com/index.php?action=dts_quick&id=1`
- **预期日志**:
  ```
  CP index hit, action=dts_quick
  ```

#### 测试3: `?action=dts_ev_add&object_id=3`
- **访问**: `http://yourdomain.com/index.php?action=dts_ev_add&object_id=3`
- **预期日志**（如果进入 PHP）:
  ```
  CP index hit, action=dts_ev_add
  DTS_EV_ADD reached
  ```
- **可能日志**（如果被拦截）:
  ```
  无日志，或仅有 Apache 错误日志
  ```

### 4.2 📊 查看错误日志

#### 开发环境日志

**项目调试日志**: `/logs/debug.log`
```bash
# 实时监控（推荐）
tail -f /home/user/cccp/logs/debug.log

# 查看最后 50 行
tail -n 50 /home/user/cccp/logs/debug.log

# 搜索特定内容
grep -E "CP index|DTS_EV_ADD" /home/user/cccp/logs/debug.log
```

**日志格式**:
```
[2025-11-22 16:12:30] CP index hit, action=dts_quick
[2025-11-22 16:12:35] DTS_EV_ADD reached
```

#### 生产环境日志

请检查以下日志文件（根据服务器配置，路径可能不同）：

```bash
# 1. 项目调试日志（首选）
# 代码已配置写入项目目录
tail -f /path/to/cccp/logs/debug.log

# 2. Apache 错误日志
tail -f /var/log/apache2/error.log   # Debian/Ubuntu
tail -f /var/log/httpd/error_log     # CentOS/RHEL

# 3. PHP-FPM 日志（如果使用 Nginx）
tail -f /var/log/php-fpm/error.log

# 4. 或通过 OVH 面板查看日志
```

**📌 重要说明**:
- 代码中已配置将日志写入 **项目的 `/logs/debug.log`** 文件
- 确保 Web 服务器用户（如 `www-data`, `apache`）对该目录有写入权限
- 如果项目日志为空，则可能需要检查目录权限或查看系统日志

### 4.3 🔍 判断标准

根据日志输出判断问题来源：

| 日志情况 | 判断 | 下一步 |
|---------|------|--------|
| **无任何日志** | Apache/WAF 在 PHP 前拦截 | 检查 Apache 配置 / ModSecurity 规则 |
| **有 "CP index hit"，无 "DTS_EV_ADD reached"** | PHP 路由层拦截（极低可能） | 检查 index.php 是否有其他逻辑 |
| **两条日志都有** | PHP 代码内部错误（非 403） | 检查 dts_ev_add.php 代码 |

---

## 五、下一步操作建议

### 5.1 如果确认是 Apache/WAF 拦截

#### 方案A: 修改 Apache 配置（推荐）

**步骤**:
1. 查找 Apache 配置文件（通常在 `/etc/apache2/sites-available/` 或 OVH 面板）
2. 搜索是否有针对 `dts_ev_add` 或类似模式的拦截规则
3. 添加白名单或修改规则

**示例修改**（如果找到拦截规则）:
```apache
<IfModule mod_security.c>
    # 允许 dts_ev_add 通过
    SecRule REQUEST_URI "dts_ev_add" "phase:1,pass,id:100001"
</IfModule>
```

#### 方案B: 改名绕过（临时方案）

**步骤**:
1. 将 action 名称从 `dts_ev_add` 改为 `dts_event_add` 或 `dts_eva`
2. 更新路由配置
3. 更新所有入口链接

**修改文件**:
- `/home/user/cccp/app/cp/index.php` (Line 107)
- `/home/user/cccp/app/cp/dts/views/dts_object_detail.php`
- `/home/user/cccp/app/cp/dts/views/_dts_object_detail_view.php`

**示例**:
```php
// 修改前
'dts_ev_add' => APP_PATH_CP . '/dts/actions/dts_ev_add.php',

// 修改后
'dts_event_add' => APP_PATH_CP . '/dts/actions/dts_ev_add.php',
```

```php
// 视图中修改前
<a href="<?php echo CP_BASE_URL; ?>dts_ev_add&object_id=<?php echo $object['id']; ?>">

// 修改后
<a href="<?php echo CP_BASE_URL; ?>dts_event_add&object_id=<?php echo $object['id']; ?>">
```

### 5.2 如果是 ModSecurity 规则

**步骤**:
1. 通过 OVH 面板或 SSH 访问 ModSecurity 日志
2. 搜索包含 `dts_ev_add` 的拦截记录
3. 找到触发的规则 ID
4. 在 ModSecurity 配置中禁用该规则或添加例外

**示例日志搜索**:
```bash
grep "dts_ev_add" /var/log/modsec_audit.log
```

---

## 六、临时测试方案

在等待日志验证的同时，可以尝试以下快速测试：

### 测试1: 改名测试

将 `dts_ev_add` 改为 `dts_eva`，看是否还触发 403：

```bash
# 1. 修改路由
# app/cp/index.php Line 107
'dts_eva' => APP_PATH_CP . '/dts/actions/dts_ev_add.php',

# 2. 修改视图链接
# app/cp/dts/views/dts_object_detail.php Line 82
dts_eva&object_id=...

# 3. 访问测试
http://yourdomain.com/index.php?action=dts_eva&object_id=3
```

**如果改名后正常**: ✅ 确认是 action 名称触发了 WAF 规则
**如果改名后仍 403**: ⚠️ 可能是其他参数或 URL 模式问题

---

## 七、已修改的文件（调试日志）

| 文件 | 修改内容 | 行号 |
|------|---------|------|
| `dc_html/cp/index.php` | 添加日志到 `/logs/debug.log` | 5-7 |
| `app/cp/dts/actions/dts_ev_add.php` | 添加日志到 `/logs/debug.log` | 2-4 |
| `logs/README.md` | 日志使用说明文档 | 新建 |
| `logs/.gitignore` | 忽略 *.log 文件 | 新建 |

**日志输出路径**: `/home/user/cccp/logs/debug.log`

**日志格式**:
```php
// dc_html/cp/index.php (Line 6-7)
$debug_log = dirname(dirname(__DIR__)) . '/logs/debug.log';
error_log('[' . date('Y-m-d H:i:s') . '] CP index hit, action=' . ($_GET['action'] ?? 'none') . PHP_EOL, 3, $debug_log);

// app/cp/dts/actions/dts_ev_add.php (Line 3-4)
$debug_log = dirname(__DIR__, 3) . '/logs/debug.log';
error_log('[' . date('Y-m-d H:i:s') . '] DTS_EV_ADD reached' . PHP_EOL, 3, $debug_log);
```

**注意**: 这些调试日志在问题解决后应该删除。详细的日志使用说明请查看 `logs/README.md`。

---

## 八、总结

### 8.1 当前状态
- ✅ 路由配置正常
- ✅ 调试日志已添加
- ⏸️ **等待用户验证日志输出**

### 8.2 最有可能的原因
🔴 **Apache / ModSecurity 在 PHP 前拦截了包含 `dts_ev_add` 的请求**

### 8.3 下一步
1. 用户访问测试 URL 并检查日志
2. 根据日志输出确认拦截点
3. 修改 Apache 配置或改名绕过

---

## 九、快速参考

### 日志位置

#### 项目调试日志（已配置）
```bash
# 开发环境
/home/user/cccp/logs/debug.log

# 生产环境（相对于项目根目录）
/path/to/cccp/logs/debug.log
```

#### 系统日志（常见路径）
```bash
# Apache 错误日志
/var/log/apache2/error.log  # Debian/Ubuntu
/var/log/httpd/error_log    # CentOS/RHEL

# PHP 错误日志
/var/log/php_errors.log
/var/log/php/error.log

# PHP-FPM 日志
/var/log/php-fpm/error.log

# ModSecurity 审计日志
/var/log/modsec_audit.log
```

### 快速测试命令

#### 查看项目日志
```bash
# 实时监控（推荐）
tail -f /home/user/cccp/logs/debug.log

# 搜索特定内容
grep -E "CP index|DTS_EV_ADD" /home/user/cccp/logs/debug.log

# 查看最后 50 行
tail -n 50 /home/user/cccp/logs/debug.log
```

#### 查看系统日志
```bash
# 实时监控 Apache 日志
tail -f /var/log/apache2/error.log | grep -E "CP index|DTS_EV_ADD|403"

# 搜索历史日志
grep -i "dts_ev_add" /var/log/apache2/error.log
```

#### 权限设置（如果需要）
```bash
# 确保日志目录可写
chmod 777 /path/to/cccp/logs
chmod 666 /path/to/cccp/logs/debug.log
```

---

**报告完成日期**: 2025-11-22
**报告编写**: Claude (DTS-403-Debug)
**状态**: ⏸️ 待用户提供日志验证结果

**下一步**: 请用户按照"第四节"的步骤进行测试，并将日志输出反馈。
