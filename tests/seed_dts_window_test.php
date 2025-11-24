<?php
// tests/seed_dts_window_test.php

// 1. Load Bootstrap
define('BASE_PATH', dirname(__DIR__));
define('CP_APP_DIR', BASE_PATH . '/app/cp');
require_once CP_APP_DIR . '/bootstrap.php';
require_once CP_APP_DIR . '/dts/dts_lib.php';

global $pdo;

try {
    echo "Starting Seed...\n";
    $pdo->beginTransaction();

    // 2. Create User (if not exists) for Login
    $stmt = $pdo->prepare("SELECT user_id FROM sys_users WHERE user_login = 'admin'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $hash = password_hash('password', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO sys_users (user_login, user_secret_hash, user_display_name, user_status) VALUES ('admin', '$hash', 'Admin', 'active')");
    }

    // 3. Create Rule: "Window Test 180"
    // Interval: 12 months. Window Start Offset: -180 days (6 months before).
    $rule_name = "Window Test 180";
    $stmt = $pdo->prepare("SELECT id FROM cp_dts_rule WHERE rule_name = ?");
    $stmt->execute([$rule_name]);
    $rule_row = $stmt->fetch();

    if (!$rule_row) {
        $pdo->prepare("
            INSERT INTO cp_dts_rule
            (rule_name, rule_type, cycle_interval_months, earliest_offset_days, rule_status)
            VALUES (?, 'last_done_based', 12, -180, 1)
        ")->execute([$rule_name]);
        $rule_id = $pdo->lastInsertId();
    } else {
        $rule_id = $rule_row['id'];
    }

    // 4. Create Subject
    $subj_name = "Test Corp " . time();
    $pdo->prepare("INSERT INTO cp_dts_subject (subject_name, subject_status) VALUES (?, 1)")->execute([$subj_name]);
    $subj_id = $pdo->lastInsertId();

    // 5. Create Object
    $obj_name = "Window Test Object";
    $pdo->prepare("INSERT INTO cp_dts_object (subject_id, object_name, active_flag) VALUES (?, ?, 1)")->execute([$subj_id, $obj_name]);
    $obj_id = $pdo->lastInsertId();

    // 6. Calculate Dates
    // Goal: Next Deadline = Today + 100 days.
    // Logic: Last Done = Next Deadline - 12 months.
    $today = new DateTime('today');
    $target_deadline = (clone $today)->modify('+100 days');
    $event_date = (clone $target_deadline)->modify('-12 months');

    // Window Start = Target Deadline - 180 days.
    // T+100 - 180 = T-80.
    // Window should be OPEN.

    echo "Target Deadline: " . $target_deadline->format('Y-m-d') . "\n";
    echo "Event Date (Last Done): " . $event_date->format('Y-m-d') . "\n";
    echo "Expected Window Start: " . (clone $target_deadline)->modify('-180 days')->format('Y-m-d') . "\n";

    // 7. Insert Event
    dts_save_event($pdo, (int)$obj_id, [
        'subject_id' => $subj_id,
        'rule_id' => $rule_id,
        'event_type' => 'test_done',
        'event_date' => $event_date->format('Y-m-d'),
        'rule_mode' => 'select' // Force use of our rule
    ]);

    $pdo->commit();
    echo "Seed Complete. User: admin / password. Object ID: $obj_id\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
