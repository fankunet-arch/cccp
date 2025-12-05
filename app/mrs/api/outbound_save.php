<?php
/**
 * API: Save Outbound
 * 文件路径: app/mrs/api/outbound_save.php
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

$ledger_ids = $input['ledger_ids'] ?? [];
$destination_id = $input['destination_id'] ?? null;
$destination_note = $input['destination_note'] ?? '';

if (empty($ledger_ids) || !is_array($ledger_ids)) {
    mrs_json_response(false, null, '请选择要出库的包裹');
}

if (empty($destination_id)) {
    mrs_json_response(false, null, '请选择出库去向');
}

// 获取操作员
$operator = $_SESSION['user_login'] ?? 'system';

// 执行出库
$result = mrs_outbound_packages($pdo, $ledger_ids, $operator, $destination_id, $destination_note);

if ($result['success']) {
    mrs_json_response(true, null, $result['message']);
} else {
    mrs_json_response(false, null, $result['message']);
}
