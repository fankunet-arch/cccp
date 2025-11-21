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

    // --- T03: Lock-in 轨计算 (通用) ---
    // 如果规则定义了 lock_days，则计算锁定截止日
    // 逻辑：锁定截止日 = 基准日 + lock_days
    if (!empty($rule['lock_days']) && $rule['lock_days'] > 0) {
        $lock_dt = clone $base_dt;
        $lock_dt->modify("+{$rule['lock_days']} days");
        $nodes['locked_until_date'] = $lock_dt->format('Y-m-d');
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
        // 对于周期类任务，deadline 通常就是下次周期日
        $nodes['deadline_date'] = $nodes['cycle_next_date'];

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
        // 跟进日也可视为一种 deadline
        $nodes['deadline_date'] = $nodes['follow_up_date'];
    }

    return $nodes;
}

/**
 * 更新对象的当前状态
 * [核心逻辑]：每次事件保存后调用，计算该对象接下来的重要日期
 * * @param PDO $pdo 数据库连接
 * @param int $object_id 对象ID
 * @return bool 成功返回 true
 */
function dts_update_object_state(PDO $pdo, int $object_id): bool {
    try {
        // 1. 获取该对象的最新“已完成”事件（按日期倒序）
        $stmt = $pdo->prepare("
            SELECT e.*, r.*
            FROM cp_dts_event e
            LEFT JOIN cp_dts_rule r ON e.rule_id = r.id
            WHERE e.object_id = ? AND e.status = 'completed'
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
                'lock_days' => $latest_event['lock_days'] ?? null, // T03: Added lock_days
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

        // 情况B：[极速录入兼容] 无规则，但事件里直接填了“新过期日”
        // 此时直接把这个过期日当做 deadline，不计算窗口期
        if (empty($nodes['deadline_date']) && !empty($latest_event['expiry_date_new'])) {
            $nodes['deadline_date'] = $latest_event['expiry_date_new'];
        }

        // T03 Default Fallback: Try to find a default rule if no rule was applied but we have object info
        if (empty($nodes) && empty($latest_event['rule_id'])) {
             // Fetch object category to look for default rules
             $o_stmt = $pdo->prepare("SELECT object_type_main, object_type_sub FROM cp_dts_object WHERE id = ?");
             $o_stmt->execute([$object_id]);
             $obj_info = $o_stmt->fetch(PDO::FETCH_ASSOC);

             if ($obj_info) {
                 // Look for default rule (heuristic: find first rule matching category)
                 // This is a simple "Default Parameter" implementation as requested.
                 // Prioritize exact sub-category match, then main category match.
                 $def_rule_stmt = $pdo->prepare("
                    SELECT * FROM cp_dts_rule
                    WHERE (cat_main = ? OR cat_main = 'ALL')
                    AND (cat_sub = ? OR cat_sub IS NULL OR cat_sub = '')
                    AND rule_status = 1
                    ORDER BY cat_sub DESC, id DESC LIMIT 1
                 ");
                 $def_rule_stmt->execute([$obj_info['object_type_main'], $obj_info['object_type_sub']]);
                 $default_rule = $def_rule_stmt->fetch(PDO::FETCH_ASSOC);

                 if ($default_rule) {
                      // Determine base date from event data based on rule type
                      $base_date = null;
                      if ($default_rule['rule_type'] === 'expiry_based' && !empty($latest_event['expiry_date_new'])) {
                          $base_date = $latest_event['expiry_date_new'];
                      } elseif ($default_rule['rule_type'] === 'last_done_based') {
                          $base_date = $latest_event['event_date'];
                      } elseif ($default_rule['rule_type'] === 'submit_based') {
                          $base_date = $latest_event['event_date'];
                      }

                      if ($base_date) {
                          $nodes = dts_calculate_nodes($default_rule, $base_date, (int)$latest_event['mileage_now']);
                      }
                 }
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
            ':locked_until' => $nodes['locked_until_date'] ?? null, // T03: New field
            ':window_start' => $nodes['window_start_date'] ?? null,
            ':window_end' => $nodes['window_end_date'] ?? null,
            ':cycle' => $nodes['cycle_next_date'] ?? null,
            ':follow_up' => $nodes['follow_up_date'] ?? null,
            ':mileage' => $nodes['next_mileage_suggest'] ?? null,
            ':event_id' => $latest_event['id']
        ];

        if ($state_row) {
            // 更新
            $stmt = $pdo->prepare("
                UPDATE cp_dts_object_state SET
                    next_deadline_date = :deadline,
                    locked_until_date = :locked_until,
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
                (object_id, next_deadline_date, locked_until_date, next_window_start_date, next_window_end_date,
                 next_cycle_date, next_follow_up_date, next_mileage_suggest, last_event_id, last_updated_at)
                VALUES (:object_id, :deadline, :locked_until, :window_start, :window_end, :cycle, :follow_up, :mileage, :event_id, NOW())
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

/**
 * 统一保存对象（包含主体处理逻辑）
 *
 * @param PDO $pdo 数据库连接
 * @param int|null $subject_id 主体ID（如果已知）
 * @param string $object_name 对象名称
 * @param array $params 包含 subject_name, subject_type (可选), cat_main, cat_sub
 * @return array ['subject_id' => int, 'object_id' => int]
 * @throws Exception
 */
function dts_save_object(PDO $pdo, ?int $subject_id, string $object_name, array $params): array {
    // --- 1. 处理主体 (Subject) ---
    $subject_name_input = trim($params['subject_name'] ?? '');

    if (empty($subject_id)) {
        if (empty($subject_name_input)) {
            throw new Exception("Subject ID or Name is required.");
        }

        // 前端没ID，说明是新名字。双重检查数据库是否真有同名
        $stmt = $pdo->prepare("SELECT id FROM cp_dts_subject WHERE subject_name = ? LIMIT 1");
        $stmt->execute([$subject_name_input]);
        $exist_subj = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($exist_subj) {
            $subject_id = (int)$exist_subj['id'];
        } else {
            // 真没有，创建新主体
            $new_type = $params['subject_type'] ?? 'person';
            $stmt_new_s = $pdo->prepare("INSERT INTO cp_dts_subject (subject_name, subject_type, subject_status, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())");
            $stmt_new_s->execute([$subject_name_input, $new_type]);
            $subject_id = (int)$pdo->lastInsertId();
        }
    }

    // --- 2. 处理对象 (Object) ---
    $object_name = trim($object_name);
    if (empty($object_name)) {
        throw new Exception("Object Name is required.");
    }

    $cat_main = trim($params['cat_main'] ?? '');
    $cat_sub = trim($params['cat_sub'] ?? '');

    // 在该主体下查找是否已有该对象
    $stmt_obj = $pdo->prepare("SELECT id FROM cp_dts_object WHERE subject_id = ? AND object_name = ? LIMIT 1");
    $stmt_obj->execute([$subject_id, $object_name]);
    $exist_obj = $stmt_obj->fetch(PDO::FETCH_ASSOC);

    $object_id = null;
    if ($exist_obj) {
        $object_id = (int)$exist_obj['id'];
        // 可选：如果老对象没有分类，顺便更新一下分类？这里暂不覆盖，以老数据为准
    } else {
        // 创建新对象
        $stmt_new_o = $pdo->prepare("INSERT INTO cp_dts_object (subject_id, object_name, object_type_main, object_type_sub, active_flag, created_at, updated_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())");
        // cat_sub 为空则存 NULL
        $stmt_new_o->execute([$subject_id, $object_name, $cat_main, $cat_sub ?: null]);
        $object_id = (int)$pdo->lastInsertId();
    }

    return ['subject_id' => $subject_id, 'object_id' => $object_id];
}

/**
 * 统一保存事件（包含状态更新）
 *
 * @param PDO $pdo 数据库连接
 * @param int $object_id 对象ID
 * @param array $params 包含 event_id (可选), subject_id, event_type, event_date, rule_id, note, mileage_now, expiry_date_new
 * @return int Event ID
 * @throws Exception
 */
function dts_save_event(PDO $pdo, int $object_id, array $params): int {
    $event_id = $params['event_id'] ?? null;
    $subject_id = $params['subject_id'] ?? null;
    $event_type = $params['event_type'] ?? '';
    $event_date = $params['event_date'] ?? '';

    if (empty($object_id) || empty($subject_id) || empty($event_type) || empty($event_date)) {
        throw new Exception("Missing required event fields (object, subject, type, date).");
    }

    $rule_id = !empty($params['rule_id']) ? $params['rule_id'] : null;
    $mileage_now = !empty($params['mileage_now']) ? $params['mileage_now'] : null;
    $expiry_date_new = !empty($params['expiry_date_new']) ? $params['expiry_date_new'] : null;
    $note = trim($params['note'] ?? '');

    if ($event_id) {
        // UPDATE
        $stmt_ev = $pdo->prepare("UPDATE cp_dts_event SET object_id=?, subject_id=?, rule_id=?, event_type=?, event_date=?, mileage_now=?, expiry_date_new=?, note=?, updated_at=NOW() WHERE id=?");
        $stmt_ev->execute([$object_id, $subject_id, $rule_id, $event_type, $event_date, $mileage_now, $expiry_date_new, $note, $event_id]);
    } else {
        // INSERT
        $stmt_ev = $pdo->prepare("INSERT INTO cp_dts_event (object_id, subject_id, rule_id, event_type, event_date, mileage_now, expiry_date_new, note, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW(), NOW())");
        $stmt_ev->execute([$object_id, $subject_id, $rule_id, $event_type, $event_date, $mileage_now, $expiry_date_new, $note]);
        $event_id = (int)$pdo->lastInsertId();
    }

    // --- 触发状态计算 ---
    dts_update_object_state($pdo, (int)$object_id);

    return (int)$event_id;
}
