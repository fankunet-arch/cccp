<?php
/**
 * API: Change Status
 * 文件路径: app/mrs/api/status_change.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mrs_json_response(false, null, '非法请求方式');
}

$input = mrs_get_json_input();
if (!$input) {
    $input = $_POST;
}

$ledger_id = (int)($input['ledger_id'] ?? 0);
$new_status = trim($input['new_status'] ?? '');
$reason = trim($input['reason'] ?? '');

if ($ledger_id <= 0) {
    mrs_json_response(false, null, '台账ID无效');
}

if (!in_array($new_status, ['void'])) {
    mrs_json_response(false, null, '状态无效');
}

// 获取操作员
$operator = $_SESSION['user_login'] ?? 'system';

// 执行状态变更
$result = mrs_change_status($pdo, $ledger_id, $new_status, $reason, $operator);

if ($result['success']) {
    mrs_json_response(true, null, $result['message']);
} else {
    mrs_json_response(false, null, $result['message']);
}
