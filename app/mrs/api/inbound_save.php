<?php
/**
 * API: Save Inbound (从 Express 批次入库)
 * 文件路径: app/mrs/api/inbound_save.php
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

$batch_name = trim($input['batch_name'] ?? '');
$packages = $input['packages'] ?? [];
$spec_info = trim($input['spec_info'] ?? '');

// 验证输入
if (empty($batch_name)) {
    mrs_json_response(false, null, '批次名称不能为空');
}

if (empty($packages) || !is_array($packages)) {
    mrs_json_response(false, null, '请选择要入库的包裹');
}

// 获取操作员
$operator = $_SESSION['user_login'] ?? 'system';

// 执行入库
$result = mrs_inbound_packages($pdo, $packages, $spec_info, $operator);

if ($result['success']) {
    mrs_json_response(true, [
        'created' => $result['created'],
        'errors' => $result['errors']
    ], '入库成功');
} else {
    mrs_json_response(false, null, $result['message'] ?? '入库失败');
}
