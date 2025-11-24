<?php
// tests/dts_simulation.php

// 1. Setup Environment
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 2. Schema Setup
$pdo->exec("
    CREATE TABLE cp_dts_subject (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        subject_name TEXT,
        subject_type TEXT,
        subject_status INTEGER DEFAULT 1,
        is_deleted INTEGER DEFAULT 0,
        created_at DATETIME,
        updated_at DATETIME
    );

    CREATE TABLE cp_dts_object (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        subject_id INTEGER,
        object_name TEXT,
        object_type_main TEXT,
        object_type_sub TEXT,
        identifier TEXT,
        remark TEXT,
        active_flag INTEGER DEFAULT 1,
        is_deleted INTEGER DEFAULT 0,
        created_at DATETIME,
        updated_at DATETIME
    );

    CREATE TABLE cp_dts_event (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        object_id INTEGER,
        subject_id INTEGER,
        rule_id INTEGER,
        event_type TEXT,
        event_date DATE,
        expiry_date_new DATE,
        mileage_now INTEGER,
        note TEXT,
        custom_lock_date DATE,
        custom_window_start DATE,
        custom_window_end DATE,
        custom_follow_up_date DATE,
        status TEXT DEFAULT 'completed',
        is_deleted INTEGER DEFAULT 0,
        created_at DATETIME,
        updated_at DATETIME
    );

    -- Unique Index for Duplication Prevention
    CREATE UNIQUE INDEX uk_event_duplicate ON cp_dts_event (object_id, event_type, event_date, is_deleted);

    CREATE TABLE cp_dts_rule (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        rule_name TEXT,
        rule_type TEXT, -- expiry_based, last_done_based, submit_based
        cat_main TEXT,
        cat_sub TEXT,
        cycle_interval_days INTEGER,
        cycle_interval_months INTEGER,
        earliest_offset_days INTEGER, -- Window Start offset
        suggest_offset_days INTEGER,
        safe_last_offset_days INTEGER, -- Window End offset
        follow_up_offset_days INTEGER,
        follow_up_offset_months INTEGER,
        lock_days INTEGER,
        rule_status INTEGER DEFAULT 1
    );

    CREATE TABLE cp_dts_object_state (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        object_id INTEGER,
        next_deadline_date DATE,
        next_window_start_date DATE,
        next_window_end_date DATE,
        next_cycle_date DATE,
        next_follow_up_date DATE,
        next_mileage_suggest INTEGER,
        locked_until_date DATE,
        last_event_id INTEGER,
        last_updated_at DATETIME
    );
");

// 3. Logic Injection (Mocking dts_lib.php functions)
// We paste the relevant logic here to ensure we are testing the actual algorithms.

function dts_calculate_nodes(array $rule, string $base_date, ?int $current_mileage = null): array {
    $nodes = [];
    if (empty($base_date)) return $nodes;

    try {
        $base_dt = new DateTime($base_date);
    } catch (Exception $e) {
        return [];
    }

    $window_base_dt = null;

    if ($rule['rule_type'] === 'expiry_based') {
        $nodes['deadline_date'] = $base_date;
        $window_base_dt = clone $base_dt;
    }
    elseif ($rule['rule_type'] === 'last_done_based') {
        $next_dt = clone $base_dt;
        $has_interval = false;

        if (!empty($rule['cycle_interval_months']) && $rule['cycle_interval_months'] > 0) {
            $next_dt->modify("+{$rule['cycle_interval_months']} months");
            $has_interval = true;
        } elseif (!empty($rule['cycle_interval_days']) && $rule['cycle_interval_days'] > 0) {
            $next_dt->modify("+{$rule['cycle_interval_days']} days");
            $has_interval = true;
        }

        if ($has_interval) {
            $nodes['cycle_next_date'] = $next_dt->format('Y-m-d');
            $nodes['deadline_date'] = $nodes['cycle_next_date'];
            $window_base_dt = clone $next_dt;
        }
    }
    elseif ($rule['rule_type'] === 'submit_based') {
        // Simplified for simulation
        $follow_dt = clone $base_dt;
        if (!empty($rule['follow_up_offset_days'])) {
             $follow_dt->modify("+{$rule['follow_up_offset_days']} days");
             $nodes['follow_up_date'] = $follow_dt->format('Y-m-d');
             $nodes['deadline_date'] = $nodes['follow_up_date'];
             $window_base_dt = clone $follow_dt;
        }
    }

    if ($window_base_dt) {
        if (isset($rule['earliest_offset_days'])) {
            $earliest_dt = clone $window_base_dt;
            // Note: offset is usually negative for window start (e.g. -60 days before deadline)
            // But checking DB values, let's assume the value stored is negative if it's before.
            // Let's assume the rule stores "-60".
            $earliest_dt->modify("{$rule['earliest_offset_days']} days");
            $nodes['window_start_date'] = $earliest_dt->format('Y-m-d');
        }
    }

    return $nodes;
}

function dts_update_object_state(PDO $pdo, int $object_id): bool {
    // Mocking the logic from dts_lib.php
    $stmt = $pdo->prepare("
        SELECT e.*, r.*, r.id as rule_id_from_join
        FROM cp_dts_event e
        LEFT JOIN cp_dts_rule r ON e.rule_id = r.id
        WHERE e.object_id = ? AND e.status = 'completed' AND e.is_deleted = 0
        ORDER BY e.event_date DESC, e.id DESC
        LIMIT 1
    ");
    $stmt->execute([$object_id]);
    $latest_event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$latest_event) {
        $pdo->prepare("DELETE FROM cp_dts_object_state WHERE object_id = ?")->execute([$object_id]);
        return true;
    }

    $nodes = [];
    $locked_until = null;

    // Check custom fields (Priority 1)
    $has_custom = !empty($latest_event['custom_window_start']) || !empty($latest_event['custom_lock_date']);
    // Simplified check for sim

    if ($has_custom) {
        if (!empty($latest_event['custom_window_start'])) $nodes['window_start_date'] = $latest_event['custom_window_start'];
        // ... other custom fields
    } elseif (!empty($latest_event['rule_id'])) {
        // Priority 2: Rule
        // Construct rule array manually because fetchAll flattened it
        $rule = [
            'rule_type' => $latest_event['rule_type'],
            'cycle_interval_months' => $latest_event['cycle_interval_months'],
            'cycle_interval_days' => $latest_event['cycle_interval_days'],
            'earliest_offset_days' => $latest_event['earliest_offset_days'],
            'follow_up_offset_days' => $latest_event['follow_up_offset_days']
        ];

        $base_date = $latest_event['event_date'];
        if ($rule['rule_type'] === 'expiry_based') $base_date = $latest_event['expiry_date_new'];

        $nodes = dts_calculate_nodes($rule, $base_date);
    }

    // Insert/Update State
    // Check exist
    $chk = $pdo->prepare("SELECT id FROM cp_dts_object_state WHERE object_id=?");
    $chk->execute([$object_id]);
    if ($chk->fetch()) {
        $sql = "UPDATE cp_dts_object_state SET next_deadline_date=?, next_window_start_date=?, last_updated_at=CURRENT_TIMESTAMP WHERE object_id=?";
        $pdo->prepare($sql)->execute([
            $nodes['deadline_date'] ?? null,
            $nodes['window_start_date'] ?? null,
            $object_id
        ]);
    } else {
        $sql = "INSERT INTO cp_dts_object_state (object_id, next_deadline_date, next_window_start_date, last_updated_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
        $pdo->prepare($sql)->execute([
            $object_id,
            $nodes['deadline_date'] ?? null,
            $nodes['window_start_date'] ?? null
        ]);
    }
    return true;
}

function dts_save_event_sim(PDO $pdo, int $object_id, array $params) {
    // Dup Check
    if (empty($params['event_id'])) {
        $stmt = $pdo->prepare("SELECT id FROM cp_dts_event WHERE object_id=? AND event_type=? AND event_date=? AND is_deleted=0");
        $stmt->execute([$object_id, $params['event_type'], $params['event_date']]);
        if ($stmt->fetch()) {
            throw new Exception("Duplicate Entry Detected!");
        }
    }

    // Insert
    $stmt = $pdo->prepare("INSERT INTO cp_dts_event (object_id, subject_id, rule_id, event_type, event_date, expiry_date_new, created_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
    $stmt->execute([
        $object_id,
        $params['subject_id'],
        $params['rule_id'],
        $params['event_type'],
        $params['event_date'],
        $params['expiry_date_new'] ?? null
    ]);

    // Update State
    dts_update_object_state($pdo, $object_id);
}

// 4. Scenarios

echo "--- Starting DTS Simulation ---\n";

// Setup Rule: Annual Audit (Starts 60 days before)
$pdo->exec("INSERT INTO cp_dts_rule (rule_name, rule_type, cycle_interval_months, earliest_offset_days) VALUES ('Annual Audit', 'last_done_based', 12, -60)");
$rule_id = $pdo->lastInsertId();

// Setup Subject
$pdo->exec("INSERT INTO cp_dts_subject (subject_name) VALUES ('Test Company')");
$subj_id = $pdo->lastInsertId();

// Helper to simulate today
$today = new DateTime('today');

// --- Object 1: Future Safe ---
// Done today. Next due in 1 year. Window starts in 10 months.
$pdo->exec("INSERT INTO cp_dts_object (subject_id, object_name) VALUES ($subj_id, 'Obj_Future_Safe')");
$obj1_id = $pdo->lastInsertId();
dts_save_event_sim($pdo, (int)$obj1_id, [
    'subject_id' => $subj_id,
    'rule_id' => $rule_id,
    'event_type' => 'audit_done',
    'event_date' => $today->format('Y-m-d')
]);
echo "[Obj 1] Saved. Logic: Done today -> Due +1yr -> Window -60d.\n";

// --- Object 2: Window Open ---
// Done 11 months ago. Next due in 1 month. Window started 1 month ago.
$pdo->exec("INSERT INTO cp_dts_object (subject_id, object_name) VALUES ($subj_id, 'Obj_Window_Open')");
$obj2_id = $pdo->lastInsertId();
$date_11_months_ago = (clone $today)->modify('-11 months')->format('Y-m-d');
dts_save_event_sim($pdo, (int)$obj2_id, [
    'subject_id' => $subj_id,
    'rule_id' => $rule_id,
    'event_type' => 'audit_done',
    'event_date' => $date_11_months_ago
]);
echo "[Obj 2] Saved. Logic: Done -11m -> Due +1m -> Window Open (Starts -60d from due, so 1m ago).\n";

// --- Object 3: Overdue ---
// Done 13 months ago. Overdue by 1 month.
$pdo->exec("INSERT INTO cp_dts_object (subject_id, object_name) VALUES ($subj_id, 'Obj_Overdue')");
$obj3_id = $pdo->lastInsertId();
$date_13_months_ago = (clone $today)->modify('-13 months')->format('Y-m-d');
dts_save_event_sim($pdo, (int)$obj3_id, [
    'subject_id' => $subj_id,
    'rule_id' => $rule_id,
    'event_type' => 'audit_done',
    'event_date' => $date_13_months_ago
]);
echo "[Obj 3] Saved. Logic: Done -13m -> Due -1m (Overdue).\n";

// --- Duplicate Test ---
echo "[Test] Attempting Duplicate Save on Obj 1...\n";
try {
    dts_save_event_sim($pdo, (int)$obj1_id, [
        'subject_id' => $subj_id,
        'rule_id' => $rule_id,
        'event_type' => 'audit_done',
        'event_date' => $today->format('Y-m-d')
    ]);
    echo "[Fail] Duplicate NOT caught!\n";
} catch (Exception $e) {
    echo "[Pass] Duplicate caught: " . $e->getMessage() . "\n";
}

// 5. Simulate View (dts_main)
echo "\n--- Simulating Main View Display ---\n";
$stmt = $pdo->query("SELECT o.*, st.* FROM cp_dts_object o LEFT JOIN cp_dts_object_state st ON o.id = st.object_id");
$objects = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($objects as $obj) {
    echo "Object: {$obj['object_name']}\n";
    echo "  DB State -> Deadline: {$obj['next_deadline_date']}, WindowStart: {$obj['next_window_start_date']}\n";

    // View Logic (from patched dts_main.php)
    $today_dt = new DateTime('today');
    $ws_date = $obj['next_window_start_date'];
    $dl_date = $obj['next_deadline_date'];

    $display = "Hidden";

    if ($ws_date) {
        $ws_dt = new DateTime($ws_date);
        if ($ws_dt > $today_dt) {
            $display = "[Info] Starts in " . $today_dt->diff($ws_dt)->days . " days";
        } else {
            // Window Open
            if ($dl_date) {
                $dl_dt = new DateTime($dl_date);
                $days = $today_dt->diff($dl_dt)->days;
                $invert = $today_dt->diff($dl_dt)->invert;
                if ($invert) {
                    $display = "[Danger] OVERDUE by $days days";
                } else {
                    $display = "[Warning/Action] Open. Deadline in $days days";
                }
            } else {
                $display = "[Success] Window Open (No Deadline)";
            }
        }
    } elseif ($dl_date) {
        $dl_dt = new DateTime($dl_date);
        $days = $today_dt->diff($dl_dt)->days;
        $invert = $today_dt->diff($dl_dt)->invert;
        if ($invert) {
            $display = "[Danger] OVERDUE by $days days";
        } else {
             $display = "[Info] Deadline in $days days";
        }
    }

    echo "  View Output -> $display\n";
}

?>
