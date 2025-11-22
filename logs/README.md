# DTS 调试日志目录

## 📂 日志文件位置

本目录用于存储 DTS 系统的调试日志。

### 开发环境

**日志文件**: `/logs/debug.log`
**完整路径**: `/home/user/cccp/logs/debug.log`

**查看日志**:
```bash
# 实时监控日志
tail -f /home/user/cccp/logs/debug.log

# 查看最后 50 行
tail -n 50 /home/user/cccp/logs/debug.log

# 搜索特定关键词
grep "dts_ev_add" /home/user/cccp/logs/debug.log
```

### 生产环境

在生产环境中，日志位置取决于 Web 服务器配置：

#### 1. Apache 服务器

**默认日志位置**:
- Debian/Ubuntu: `/var/log/apache2/error.log`
- RHEL/CentOS: `/var/log/httpd/error_log`

**查看日志**:
```bash
# Ubuntu/Debian
sudo tail -f /var/log/apache2/error.log

# CentOS/RHEL
sudo tail -f /var/log/httpd/error_log

# 搜索 DTS 相关日志
sudo grep -E "CP index|DTS_EV_ADD" /var/log/apache2/error.log
```

#### 2. Nginx + PHP-FPM

**PHP-FPM 日志位置**:
- 通常在: `/var/log/php-fpm/error.log` 或 `/var/log/php7.x-fpm.log`

**查看日志**:
```bash
sudo tail -f /var/log/php-fpm/error.log
```

#### 3. 自定义 PHP error_log

如果在 `php.ini` 中配置了自定义日志路径：
```bash
php -i | grep error_log
```
这会显示当前配置的日志文件路径。

## 🔍 当前调试日志内容

本次 403 排查添加的日志包括：

### 1. CP 入口日志 (dc_html/cp/index.php)
```
[2025-11-22 16:12:30] CP index hit, action=dts_quick
[2025-11-22 16:12:35] CP index hit, action=dts_ev_add
```

### 2. dts_ev_add Action 日志 (app/cp/dts/actions/dts_ev_add.php)
```
[2025-11-22 16:12:35] DTS_EV_ADD reached
```

## 📊 日志分析

### 正常流程
如果请求成功到达 PHP，应该看到：
```
[时间] CP index hit, action=dts_ev_add
[时间] DTS_EV_ADD reached
```

### 403 拦截场景

**场景 1**: Apache/ModSecurity 拦截（请求未到达 PHP）
```
# 日志文件中没有任何输出
# 或者只在 Apache error.log 中看到 403 记录
```

**场景 2**: PHP 路由层 403（请求到达 PHP 但被拒绝）
```
[时间] CP index hit, action=dts_ev_add
# 没有看到 "DTS_EV_ADD reached"
```

**场景 3**: Action 执行中的 403（请求到达 Action 但处理失败）
```
[时间] CP index hit, action=dts_ev_add
[时间] DTS_EV_ADD reached
# 后续可能有错误信息
```

## ⚙️ 配置自定义日志（可选）

如果需要在生产环境中将日志输出到项目目录：

### 方法 1: 修改 php.ini
```ini
error_log = /path/to/cccp/logs/debug.log
log_errors = On
```

### 方法 2: 使用 .htaccess（Apache）
```apache
php_flag log_errors on
php_value error_log /path/to/cccp/logs/debug.log
```

### 方法 3: 在代码中设置（已实现）
代码中已经使用了：
```php
error_log($message, 3, $debug_log);
```
这会将日志写入项目的 `/logs/debug.log` 文件。

## 🗑️ 清理日志

日志文件可能会随时间增长，定期清理：

```bash
# 清空日志文件
> /home/user/cccp/logs/debug.log

# 或删除旧日志
rm /home/user/cccp/logs/debug.log
```

## 📝 注意事项

1. **权限**: 确保 Web 服务器用户（如 `www-data`, `apache`, `nginx`）对 `/logs` 目录有写入权限
2. **安全**: 日志文件已通过 `.gitignore` 排除，避免提交到版本控制
3. **大小**: 定期检查日志文件大小，避免占用过多磁盘空间
4. **敏感信息**: 调试日志可能包含敏感信息，生产环境中应妥善保护

---

**相关文档**:
- `DTS-403-Debug-Report-Preliminary.md` - 403 错误排查报告
- `Jules-DTS-v2.1.2-EventAdd-Report.md` - DTS v2.1.2 实现报告
