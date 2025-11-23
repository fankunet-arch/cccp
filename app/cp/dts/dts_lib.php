<?php
/**
 * DTS (Date Timeline System) 通用函数库
 * [完整修复版] 
 * 1. 修复极速录入无规则时不更新截止日的问题
 * 2. 保留所有辅助函数 (urgency, categories等)
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
                    // 兼容中文逗号
                    $sub_cats_str = str_replace('，', ',', $sub_cats_str);
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

    try {
        $base_dt = new DateTime($base_date);
    } catch (Exception $e) {
        return [];
    }

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
 * [核心逻辑]：每次事件保存后调用，计算该对象接下来的重要日期
 * [v2.1] 增强：支持双轨状态计算（Deadline轨 + Lock-in轨）
 *
 * @param PDO $pdo 数据库连接
 * @param int $object_id 对象ID
 * @return bool 成功返回 true
 */
function dts_update_object_state(PDO $pdo, int $object_id): bool {
    try {
        // 1. 获取该对象的最新"已完成"事件（按日期倒序）
        // [v2.1.1] 增强：忽略已软删除的事件
        $stmt = $pdo->prepare("
            SELECT e.*, r.*
            FROM cp_dts_event e
            LEFT JOIN cp_dts_rule r ON e.rule_id = r.id
            WHERE e.object_id = ? AND e.status = 'completed' AND e.is_deleted = 0
            ORDER BY e.event_date DESC, e.id DESC
            LIMIT 1
        ");
        $stmt->execute([$object_id]);
        $latest_event = $stmt->fetch(PDO::FETCH_ASSOC); // 显式关联数组

        if (!$latest_event) {
            // 如果没有事件，清空状态
            $pdo->prepare("DELETE FROM cp_dts_object_state WHERE object_id = ?")->execute([$object_id]);
            return true;
        }

        // 2. 根据事件和规则计算节点
        $nodes = [];

        // 情况A：有规则关联，按规则计算
        if (!empty($latest_event['rule_id']) && !empty($latest_event['rule_type'])) {
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
                // 里程数转int处理
                $nodes = dts_calculate_nodes($rule, $base_date, (int)$latest_event['mileage_now']);
            }
        }
        
        // 情况B：[极速录入兼容] 无规则，但事件里直接填了"新过期日"
        // 此时直接把这个过期日当做 deadline，不计算窗口期
        if (empty($nodes['deadline_date']) && !empty($latest_event['expiry_date_new'])) {
            $nodes['deadline_date'] = $latest_event['expiry_date_new'];
        }

        // [v2.1] Lock-in轨：计算锁定截止日期
        $locked_until = null;
        if (!empty($latest_event['lock_days']) && $latest_event['lock_days'] > 0) {
            try {
                $event_dt = new DateTime($latest_event['event_date']);
                $event_dt->modify("+{$latest_event['lock_days']} days");
                $locked_until = $event_dt->format('Y-m-d');
            } catch (Exception $e) {
                error_log("DTS v2.1: Error calculating locked_until_date: " . $e->getMessage());
            }
        }

        // 3. 更新或插入状态表
        // 先检查是否存在
        $state_stmt = $pdo->prepare("SELECT id FROM cp_dts_object_state WHERE object_id = ?");
        $state_stmt->execute([$object_id]);
        $state_row = $state_stmt->fetch();

        $data_params = [
            ':object_id' => $object_id,
            ':deadline' => $nodes['deadline_date'] ?? null,
            ':window_start' => $nodes['window_start_date'] ?? null,
            ':window_end' => $nodes['window_end_date'] ?? null,
            ':cycle' => $nodes['cycle_next_date'] ?? null,
            ':follow_up' => $nodes['follow_up_date'] ?? null,
            ':mileage' => $nodes['next_mileage_suggest'] ?? null,
            ':locked_until' => $locked_until,
            ':event_id' => $latest_event['id']
        ];

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
                    locked_until_date = :locked_until,
                    last_event_id = :event_id,
                    last_updated_at = NOW()
                WHERE object_id = :object_id
            ");
        } else {
            // 插入
            $stmt = $pdo->prepare("
                INSERT INTO cp_dts_object_state
                (object_id, next_deadline_date, next_window_start_date, next_window_end_date,
                 next_cycle_date, next_follow_up_date, next_mileage_suggest, locked_until_date,
                 last_event_id, last_updated_at)
                VALUES (:object_id, :deadline, :window_start, :window_end, :cycle, :follow_up, :mileage,
                        :locked_until, :event_id, NOW())
            ");
        }

        $stmt->execute($data_params);

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
    $state = $stmt->fetch(PDO::FETCH_ASSOC);

    return $state ?: null;
}

/**
 * 格式化日期显示
 *
 * @param string|null $date 日期字符串
 * @param string $format 格式（默认 Y-m-d）
 * @return string
 */
function dts_format_date(?string $date, string $format = 'Y-m-d'): string {
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

    return 'success';
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
        return "剩 {$days} 天";
    } elseif ($days <= 30) {
        return "剩 {$days} 天";
    } elseif ($days <= 90) {
        return "剩 {$days} 天";
    }

    return "剩 {$days} 天";
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

// ========================================
// DTS v2.1 新增功能
// ========================================

/**
 * [v2.1] 获取默认规则
 * 根据大类和小类自动匹配规则，优先级：
 * 1. 小类匹配 (cat_sub != '')
 * 2. 大类匹配 (cat_main)
 * 3. 全局匹配 (cat_main = 'ALL')
 *
 * @param PDO $pdo 数据库连接
 * @param string $cat_main 大类
 * @param string|null $cat_sub 小类（可选）
 * @return array|null 返回匹配的规则数组，无匹配返回 null
 */
function dts_get_default_rule(PDO $pdo, string $cat_main, ?string $cat_sub = null): ?array {
    try {
        // 优先匹配：小类 > 大类 > ALL
        // ORDER BY: cat_sub DESC (有值的排前面), id DESC (取最新)
        $sql = "
            SELECT * FROM cp_dts_rule
            WHERE rule_status = 1
              AND (
                  (cat_main = ? AND cat_sub = ?)
                  OR (cat_main = ? AND (cat_sub IS NULL OR cat_sub = ''))
                  OR (cat_main = 'ALL')
              )
            ORDER BY
              CASE
                WHEN cat_sub = ? AND cat_sub != '' THEN 1
                WHEN cat_main = ? AND (cat_sub IS NULL OR cat_sub = '') THEN 2
                ELSE 3
              END,
              id DESC
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cat_main, $cat_sub ?: '', $cat_main, $cat_sub ?: '', $cat_main]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);

        return $rule ?: null;

    } catch (Exception $e) {
        error_log("DTS: Error getting default rule: " . $e->getMessage());
        return null;
    }
}

/**
 * [v2.1] 统一对象保存入口
 * 在指定主体下创建或获取对象
 *
 * @param PDO $pdo 数据库连接
 * @param int $subject_id 主体ID
 * @param string $object_name 对象名称
 * @param array $params 对象参数 ['cat_main', 'cat_sub', 'identifier', 'remark']
 * @return int|false 返回对象ID，失败返回 false
 */
function dts_save_object(PDO $pdo, int $subject_id, string $object_name, array $params) {
    try {
        // 查找是否已存在同名对象
        $stmt = $pdo->prepare("
            SELECT id FROM cp_dts_object
            WHERE subject_id = ? AND object_name = ?
            LIMIT 1
        ");
        $stmt->execute([$subject_id, $object_name]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // 对象已存在，返回现有ID（不覆盖分类等信息，保持旧数据）
            return (int)$existing['id'];
        }

        // 创建新对象
        $stmt = $pdo->prepare("
            INSERT INTO cp_dts_object
            (subject_id, object_name, object_type_main, object_type_sub, identifier, remark, active_flag, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");

        $stmt->execute([
            $subject_id,
            $object_name,
            $params['cat_main'] ?? '',
            $params['cat_sub'] ?? null,
            $params['identifier'] ?? null,
            $params['remark'] ?? null
        ]);

        return (int)$pdo->lastInsertId();

    } catch (Exception $e) {
        error_log("DTS: Error saving object: " . $e->getMessage());
        return false;
    }
}

/**
 * [v2.1] 统一事件保存入口
 * 创建或更新事件，支持默认规则自动匹配
 *
 * @param PDO $pdo 数据库连接
 * @param int $object_id 对象ID
 * @param array $params 事件参数
 *   必需: 'subject_id', 'event_type', 'event_date'
 *   可选: 'rule_id', 'expiry_date_new', 'mileage_now', 'note', 'event_id'(更新模式)
 *   可选: 'cat_main', 'cat_sub' (用于自动匹配默认规则)
 * @return int|false 返回事件ID，失败返回 false
 */
function dts_save_event(PDO $pdo, int $object_id, array $params) {
    try {
        $event_id = $params['event_id'] ?? null;
        $is_update = !empty($event_id);

        // [v2.1] 默认规则逻辑：如果未提供 rule_id，尝试自动匹配
        $rule_id = $params['rule_id'] ?? null;

        if (empty($rule_id) && !empty($params['cat_main'])) {
            $default_rule = dts_get_default_rule(
                $pdo,
                $params['cat_main'],
                $params['cat_sub'] ?? null
            );

            if ($default_rule) {
                $rule_id = $default_rule['id'];
                error_log("DTS v2.1: Auto-matched default rule #{$rule_id} for {$params['cat_main']}/{$params['cat_sub']}");
            }
        }

        // 准备数据
        $data = [
            'object_id' => $object_id,
            'subject_id' => $params['subject_id'],
            'rule_id' => $rule_id ?: null,
            'event_type' => $params['event_type'],
            'event_date' => $params['event_date'],
            'expiry_date_new' => $params['expiry_date_new'] ?? null,
            'mileage_now' => $params['mileage_now'] ?? null,
            'note' => $params['note'] ?? null
        ];

        if ($is_update) {
            // 更新模式
            $stmt = $pdo->prepare("
                UPDATE cp_dts_event SET
                    object_id = :object_id,
                    subject_id = :subject_id,
                    rule_id = :rule_id,
                    event_type = :event_type,
                    event_date = :event_date,
                    expiry_date_new = :expiry_date_new,
                    mileage_now = :mileage_now,
                    note = :note,
                    updated_at = NOW()
                WHERE id = :event_id
            ");

            $data['event_id'] = $event_id;
            $stmt->execute($data);

            $final_event_id = (int)$event_id;
        } else {
            // 插入模式
            $stmt = $pdo->prepare("
                INSERT INTO cp_dts_event
                (object_id, subject_id, rule_id, event_type, event_date,
                 expiry_date_new, mileage_now, note, status, created_at, updated_at)
                VALUES
                (:object_id, :subject_id, :rule_id, :event_type, :event_date,
                 :expiry_date_new, :mileage_now, :note, 'completed', NOW(), NOW())
            ");

            $stmt->execute($data);
            $final_event_id = (int)$pdo->lastInsertId();
        }

        // 触发状态更新
        dts_update_object_state($pdo, $object_id);

        return $final_event_id;

    } catch (Exception $e) {
        error_log("DTS: Error saving event: " . $e->getMessage());
        return false;
    }
}

// ========================================
// DTS v2.1.1 软删除功能
// ========================================

/**
 * [v2.1.1] 软删除主体
 *
 * @param PDO $pdo 数据库连接
 * @param int $subject_id 主体ID
 * @param string $mode 删除模式: 'subject_only' 或 'cascade'
 * @return array 返回结果 ['success' => bool, 'message' => string, 'stats' => array]
 */
function dts_soft_delete_subject(PDO $pdo, int $subject_id, string $mode = 'subject_only'): array {
    try {
        $pdo->beginTransaction();

        // 统计该主体下的对象和事件数量
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cp_dts_object WHERE subject_id = ? AND is_deleted = 0");
        $stmt->execute([$subject_id]);
        $object_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cp_dts_event WHERE subject_id = ? AND is_deleted = 0");
        $stmt->execute([$subject_id]);
        $event_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // 软删除主体
        $stmt = $pdo->prepare("UPDATE cp_dts_subject SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$subject_id]);

        $cascade_stats = ['objects' => 0, 'events' => 0];

        if ($mode === 'cascade') {
            // 级联软删除所有对象
            $stmt = $pdo->prepare("UPDATE cp_dts_object SET is_deleted = 1 WHERE subject_id = ? AND is_deleted = 0");
            $stmt->execute([$subject_id]);
            $cascade_stats['objects'] = $stmt->rowCount();

            // 级联软删除所有事件
            $stmt = $pdo->prepare("UPDATE cp_dts_event SET is_deleted = 1 WHERE subject_id = ? AND is_deleted = 0");
            $stmt->execute([$subject_id]);
            $cascade_stats['events'] = $stmt->rowCount();
        }

        $pdo->commit();

        return [
            'success' => true,
            'message' => '主体已成功删除',
            'stats' => [
                'object_count' => $object_count,
                'event_count' => $event_count,
                'deleted_objects' => $cascade_stats['objects'],
                'deleted_events' => $cascade_stats['events']
            ]
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("DTS: Error soft deleting subject: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '删除失败：' . $e->getMessage(),
            'stats' => []
        ];
    }
}

/**
 * [v2.1.1] 软删除对象
 *
 * @param PDO $pdo 数据库连接
 * @param int $object_id 对象ID
 * @param string $mode 删除模式: 'object_only' 或 'cascade'
 * @return array 返回结果 ['success' => bool, 'message' => string, 'stats' => array]
 */
function dts_soft_delete_object(PDO $pdo, int $object_id, string $mode = 'object_only'): array {
    try {
        $pdo->beginTransaction();

        // 统计该对象的事件数量
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cp_dts_event WHERE object_id = ? AND is_deleted = 0");
        $stmt->execute([$object_id]);
        $event_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        // 软删除对象
        $stmt = $pdo->prepare("UPDATE cp_dts_object SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$object_id]);

        $deleted_events = 0;

        if ($mode === 'cascade') {
            // 级联软删除所有事件
            $stmt = $pdo->prepare("UPDATE cp_dts_event SET is_deleted = 1 WHERE object_id = ? AND is_deleted = 0");
            $stmt->execute([$object_id]);
            $deleted_events = $stmt->rowCount();
        }

        // 清空该对象的状态(因为对象已删除)
        $stmt = $pdo->prepare("DELETE FROM cp_dts_object_state WHERE object_id = ?");
        $stmt->execute([$object_id]);

        $pdo->commit();

        return [
            'success' => true,
            'message' => '对象已成功删除',
            'stats' => [
                'event_count' => $event_count,
                'deleted_events' => $deleted_events
            ]
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("DTS: Error soft deleting object: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '删除失败：' . $e->getMessage(),
            'stats' => []
        ];
    }
}

/**
 * [v2.1.1] 软删除事件
 *
 * @param PDO $pdo 数据库连接
 * @param int $event_id 事件ID
 * @return array 返回结果 ['success' => bool, 'message' => string, 'object_id' => int]
 */
function dts_soft_delete_event(PDO $pdo, int $event_id): array {
    try {
        // 获取事件所属的对象ID
        $stmt = $pdo->prepare("SELECT object_id FROM cp_dts_event WHERE id = ?");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            return [
                'success' => false,
                'message' => '事件不存在',
                'object_id' => null
            ];
        }

        $object_id = (int)$event['object_id'];

        // 软删除事件
        $stmt = $pdo->prepare("UPDATE cp_dts_event SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$event_id]);

        // 重新计算对象状态(基于剩余未删除的事件)
        dts_update_object_state($pdo, $object_id);

        return [
            'success' => true,
            'message' => '事件已成功删除',
            'object_id' => $object_id
        ];

    } catch (Exception $e) {
        error_log("DTS: Error soft deleting event: " . $e->getMessage());
        return [
            'success' => false,
            'message' => '删除失败：' . $e->getMessage(),
            'object_id' => null
        ];
    }
}