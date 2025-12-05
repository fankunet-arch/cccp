<?php
/**
 * API: Save SKU
 * 文件路径: app/mrs/api/sku_save.php
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

$sku_name = trim($input['sku_name'] ?? '');

if (empty($sku_name)) {
    mrs_json_response(false, null, '物料名称不能为空');
}

// 创建物料
$sku_id = mrs_create_sku($pdo, $sku_name);

if ($sku_id) {
    mrs_json_response(true, ['sku_id' => $sku_id], '物料创建成功');
} else {
    mrs_json_response(false, null, '物料创建失败,可能已存在');
}
