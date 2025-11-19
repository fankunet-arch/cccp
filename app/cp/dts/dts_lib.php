<?php
/**
 * DTS (Date Timeline System) 通用函数库
 *
 * 提供 DTS 模块的核心功能函数
 */

declare(strict_types=1);

/**
 * 读取分类配置文件
 *
 * @return array 返回分类数组，格式：['大类' => ['小类1', '小类2', ...], ...]
 */
function dts_load_categories(): array {
    $config_file = APP_PATH_CP . '/dts/dts_category.conf';

    if (!file_exists($config_file)) {
        error_log("DTS: Category config file not found: $config_file");
        return [];
    }

    $categories = [];
    $lines = file($config_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // 跳过注释行
        if (empty($line) || $line[0] === '#') {
            continue;
        }

        // 解析格式：大类;小类1,小类2,小类3;
        $parts = explode(';', $line);
        if (count($parts) >= 2) {
            $main_cat = trim($parts[0]);
            $sub_cats_str = trim($parts[1]);

            if (!empty($main_cat)) {
                $sub_cats = [];
                if (!empty($sub_cats_str)) {
                    $sub_cats = array_map('trim', explode(',', $sub_cats_str));
                    $sub_cats = array_filter($sub_cats); // 移除空值
                }
                $categories[$main_cat] = $sub_cats;
            }
        }
    }

    return $categories;
}

/**
 * 获取所有大类
 *
 * @return array
 */
function dts_get_main_categories(): array {
    $categories = dts_load_categories();
    return array_keys($categories);
}

/**
 * 获取指定大类下的小类
 *
 * @param string $main_cat 大类名称
 * @return array
 */
function dts_get_sub_categories(string $main_cat): array {
    $categories = dts_load_categories();
    return $categories[$main_cat] ?? [];
}

/**
 * 根据规则和基准日期计算时间节点
 *
 * @param array $rule 规则数组（从数据库查询）
 * @param string $base_date 基准日期（格式：YYYY-MM-DD）
 * @param int|null $current_mileage 当前里程（可选）
 * @return array 返回计算后的节点数组
 */
function dts_calculate_nodes(array $rule, string $base_date, ?int $current_mileage = null): array {
    $nodes = [];

    if (empty($base_date)) {
        return $nodes;
    }

    $base_dt = new DateTime($base_date);

    // 1. 证件类（expiry_based）：基于过期日计算窗口
    if ($rule['rule_type'] === 'expiry_based') {
        // 截止日就是过期日本身
        $nodes['deadline_date'] = $base_date;

        // 最早可办日
        if ($rule['earliest_offset_days'] !== null) {
            $earliest_dt = clone $base_dt;
            $earliest_dt->modify("{$rule['earliest_offset_days']} days");
            $nodes['window_start_date'] = $earliest_dt->format('Y-m-d');
        }

        // 建议办理日
        if ($rule['suggest_offset_days'] !== null) {
            $suggest_dt = clone $base_dt;
            $suggest_dt->modify("{$rule['suggest_offset_days']} days");
            $nodes['suggest_date'] = $suggest_dt->format('Y-m-d');
        }

        // 最晚安全日
        if ($rule['safe_last_offset_days'] !== null) {
            $safe_dt = clone $base_dt;
            $safe_dt->modify("{$rule['safe_last_offset_days']} days");
            $nodes['window_end_date'] = $safe_dt->format('Y-m-d');
        }
    }

    // 2. 周期类（last_done_based）：基于上次完成日计算下一次
    if ($rule['rule_type'] === 'last_done_based') {
        $next_dt = clone $base_dt;

        // 优先使用月数间隔
        if ($rule['cycle_interval_months'] !== null && $rule['cycle_interval_months'] > 0) {
            $next_dt->modify("+{$rule['cycle_interval_months']} months");
        }
        // 否则使用天数间隔
        elseif ($rule['cycle_interval_days'] !== null && $rule['cycle_interval_days'] > 0) {
            $next_dt->modify("+{$rule['cycle_interval_days']} days");
        }

        $nodes['cycle_next_date'] = $next_dt->format('Y-m-d');

        // 如果有里程间隔，计算建议里程
        if ($rule['mileage_interval'] !== null && $current_mileage !== null) {
            $nodes['next_mileage_suggest'] = $current_mileage + $rule['mileage_interval'];
        }
    }

    // 3. 递交跟进类（submit_based）：基于递交日计算跟进日
    if ($rule['rule_type'] === 'submit_based') {
        $follow_dt = clone $base_dt;

        // 优先使用月数偏移
        if ($rule['follow_up_offset_months'] !== null && $rule['follow_up_offset_months'] > 0) {
            $follow_dt->modify("+{$rule['follow_up_offset_months']} months");
        }
        // 否则使用天数偏移
        elseif ($rule['follow_up_offset_days'] !== null && $rule['follow_up_offset_days'] > 0) {
            $follow_dt->modify("+{$rule['follow_up_offset_days']} days");
        }

        $nodes['follow_up_date'] = $follow_dt->format('Y-m-d');
    }

    return $nodes;
}

/**
 * 更新对象的当前状态
 *
 * @param PDO $pdo 数据库连接
 * @param int $object_id 对象ID
 * @return bool 成功返回 true
 */
function dts_update_object_state(PDO $pdo, int $object_id): bool {
    try {
        // 1. 获取该对象的最新事件（按日期倒序）
        $stmt = $pdo->prepare("
            SELECT e.*, r.*
            FROM cp_dts_event e
            LEFT JOIN cp_dts_rule r ON e.rule_id = r.id
            WHERE e.object_id = ? AND e.status = 'completed'
            ORDER BY e.event_date DESC, e.id DESC
            LIMIT 1
        ");
        $stmt->execute([$object_id]);
        $latest_event = $stmt->fetch();

        if (!$latest_event) {
            // 如果没有事件，清空状态
            $pdo->prepare("DELETE FROM cp_dts_object_state WHERE object_id = ?")->execute([$object_id]);
            return true;
        }

        // 2. 根据事件和规则计算节点
        $nodes = [];

        if ($latest_event['rule_id'] && !empty($latest_event['rule_type'])) {
            $rule = [
                'rule_type' => $latest_event['rule_type'],
                'base_field' => $latest_event['base_field'],
                'earliest_offset_days' => $latest_event['earliest_offset_days'],
                'suggest_offset_days' => $latest_event['suggest_offset_days'],
                'safe_last_offset_days' => $latest_event['safe_last_offset_days'],
                'cycle_interval_days' => $latest_event['cycle_interval_days'],
                'cycle_interval_months' => $latest_event['cycle_interval_months'],
                'mileage_interval' => $latest_event['mileage_interval'],
                'follow_up_offset_days' => $latest_event['follow_up_offset_days'],
                'follow_up_offset_months' => $latest_event['follow_up_offset_months'],
            ];

            // 确定基准日期
            $base_date = null;
            if ($rule['rule_type'] === 'expiry_based' && !empty($latest_event['expiry_date_new'])) {
                $base_date = $latest_event['expiry_date_new'];
            } elseif ($rule['rule_type'] === 'last_done_based') {
                $base_date = $latest_event['event_date'];
            } elseif ($rule['rule_type'] === 'submit_based') {
                $base_date = $latest_event['event_date'];
            }

            if ($base_date) {
                $nodes = dts_calculate_nodes($rule, $base_date, $latest_event['mileage_now']);
            }
        }

        // 3. 更新或插入状态表
        $state_stmt = $pdo->prepare("SELECT id FROM cp_dts_object_state WHERE object_id = ?");
        $state_stmt->execute([$object_id]);
        $state_row = $state_stmt->fetch();

        if ($state_row) {
            // 更新
            $stmt = $pdo->prepare("
                UPDATE cp_dts_object_state SET
                    next_deadline_date = :deadline,
                    next_window_start_date = :window_start,
                    next_window_end_date = :window_end,
                    next_cycle_date = :cycle,
                    next_follow_up_date = :follow_up,
                    next_mileage_suggest = :mileage,
                    last_event_id = :event_id,
                    last_updated_at = NOW()
                WHERE object_id = :object_id
            ");
        } else {
            // 插入
            $stmt = $pdo->prepare("
                INSERT INTO cp_dts_object_state
                (object_id, next_deadline_date, next_window_start_date, next_window_end_date,
                 next_cycle_date, next_follow_up_date, next_mileage_suggest, last_event_id, last_updated_at)
                VALUES (:object_id, :deadline, :window_start, :window_end, :cycle, :follow_up, :mileage, :event_id, NOW())
            ");
        }

        $stmt->execute([
            ':object_id' => $object_id,
            ':deadline' => $nodes['deadline_date'] ?? null,
            ':window_start' => $nodes['window_start_date'] ?? null,
            ':window_end' => $nodes['window_end_date'] ?? null,
            ':cycle' => $nodes['cycle_next_date'] ?? null,
            ':follow_up' => $nodes['follow_up_date'] ?? null,
            ':mileage' => $nodes['next_mileage_suggest'] ?? null,
            ':event_id' => $latest_event['id']
        ]);

        return true;

    } catch (Exception $e) {
        error_log("DTS: Error updating object state: " . $e->getMessage());
        return false;
    }
}

/**
 * 获取对象的当前状态
 *
 * @param PDO $pdo 数据库连接
 * @param int $object_id 对象ID
 * @return array|null 返回状态数组，无状态返回 null
 */
function dts_get_object_state(PDO $pdo, int $object_id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM cp_dts_object_state WHERE object_id = ?");
    $stmt->execute([$object_id]);
    $state = $stmt->fetch();

    return $state ?: null;
}

/**
 * 格式化日期显示
 *
 * @param string|null $date 日期字符串
 * @param string $format 格式（默认 Y-m-d）
 * @return string
 */
function dts_format_date(?string $date, string $format = 'Y年m月d日'): string {
    if (empty($date) || $date === '0000-00-00') {
        return '-';
    }

    try {
        $dt = new DateTime($date);
        return $dt->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * 计算日期距今天数
 *
 * @param string $date 日期字符串
 * @return int 返回天数（负数表示已过期）
 */
function dts_days_from_today(string $date): int {
    $today = new DateTime('today');
    $target = new DateTime($date);
    $diff = $today->diff($target);

    return $diff->invert ? -$diff->days : $diff->days;
}

/**
 * 获取日期的紧急程度标记
 *
 * @param string|null $date 日期字符串
 * @return string 返回 CSS 类名：danger（已过期/紧急）、warning（临近）、info（正常）、''（无）
 */
function dts_get_urgency_class(?string $date): string {
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }

    $days = dts_days_from_today($date);

    if ($days < 0) {
        return 'danger'; // 已过期
    } elseif ($days <= 7) {
        return 'danger'; // 7天内
    } elseif ($days <= 30) {
        return 'warning'; // 30天内
    } elseif ($days <= 90) {
        return 'info'; // 90天内
    }

    return '';
}

/**
 * 获取日期的紧急程度描述
 *
 * @param string|null $date 日期字符串
 * @return string
 */
function dts_get_urgency_text(?string $date): string {
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }

    $days = dts_days_from_today($date);

    if ($days < 0) {
        return "已过期 " . abs($days) . " 天";
    } elseif ($days === 0) {
        return "今天到期";
    } elseif ($days === 1) {
        return "明天到期";
    } elseif ($days <= 7) {
        return "还有 {$days} 天";
    } elseif ($days <= 30) {
        return "还有 {$days} 天";
    } elseif ($days <= 90) {
        return "还有 {$days} 天";
    }

    return "还有 {$days} 天";
}

/**
 * 安全获取 POST 参数
 *
 * @param string $key 参数键名
 * @param mixed $default 默认值
 * @return mixed
 */
function dts_post(string $key, $default = null) {
    return $_POST[$key] ?? $default;
}

/**
 * 安全获取 GET 参数
 *
 * @param string $key 参数键名
 * @param mixed $default 默认值
 * @return mixed
 */
function dts_get(string $key, $default = null) {
    return $_GET[$key] ?? $default;
}

/**
 * 设置反馈消息（用于页面跳转后显示）
 *
 * @param string $type 类型：success、danger、info
 * @param string $message 消息内容
 */
function dts_set_feedback(string $type, string $message): void {
    $_SESSION['dts_feedback'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * 获取并清除反馈消息
 *
 * @return array|null
 */
function dts_get_feedback(): ?array {
    if (isset($_SESSION['dts_feedback'])) {
        $feedback = $_SESSION['dts_feedback'];
        unset($_SESSION['dts_feedback']);
        return $feedback;
    }
    return null;
}
