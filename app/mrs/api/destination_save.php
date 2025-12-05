<?php
/**
 * API: Save/Update/Delete Destination
 * 文件路径: app/mrs/api/destination_save.php
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

$operator = $_SESSION['user_login'] ?? 'system';

// 检查是否是删除操作
if (isset($input['action']) && $input['action'] === 'delete') {
    if (empty($input['destination_id'])) {
        mrs_json_response(false, null, '缺少去向ID');
    }

    $result = mrs_delete_destination($pdo, $input['destination_id']);
    mrs_json_response($result['success'], null, $result['message']);
}

// 创建或更新去向
if (empty($input['type_code']) || empty($input['destination_name'])) {
    mrs_json_response(false, null, '去向类型和名称不能为空');
}

$data = [
    'type_code' => $input['type_code'],
    'destination_name' => $input['destination_name'],
    'destination_code' => $input['destination_code'] ?? null,
    'contact_person' => $input['contact_person'] ?? null,
    'contact_phone' => $input['contact_phone'] ?? null,
    'address' => $input['address'] ?? null,
    'remark' => $input['remark'] ?? null,
    'sort_order' => $input['sort_order'] ?? 0,
    'created_by' => $operator
];

// 更新
if (!empty($input['destination_id'])) {
    $result = mrs_update_destination($pdo, $input['destination_id'], $data);
    mrs_json_response($result['success'], null, $result['message']);
}

// 创建
$result = mrs_create_destination($pdo, $data);
mrs_json_response($result['success'], ['destination_id' => $result['destination_id'] ?? null], $result['message']);
