<?php
/**
 * DTS 分类保存动作
 * 将提交的分类数据写入 dts_category.conf 文件
 */

declare(strict_types=1);

// 加载 DTS 函数库
require_once APP_PATH_CP . '/dts/dts_lib.php';

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

try {
    $cats_input = $_POST['cats'] ?? [];
    $config_file = APP_PATH_CP . '/dts/dts_category.conf';

    if (!is_array($cats_input)) {
        throw new Exception("无效的数据格式");
    }

    // 准备写入文件的内容
    $content = "# ========================================\n";
    $content .= "# DTS 分类配置文件\n";
    $content .= "# ========================================\n";
    $content .= "# 最后更新时间: " . date('Y-m-d H:i:s') . "\n";
    $content .= "# 格式说明：大类;小类1,小类2,小类3;\n";
    $content .= "# ========================================\n\n";

    foreach ($cats_input as $main_cat => $sub_cats_str) {
        $main_cat = trim($main_cat);
        if (empty($main_cat)) continue;

        // 处理小类字符串：替换中文逗号，按逗号分割，去空，去重
        $sub_cats_str = str_replace('，', ',', $sub_cats_str);
        $sub_cats_arr = explode(',', $sub_cats_str);
        
        $clean_subs = [];
        foreach ($sub_cats_arr as $sub) {
            $sub = trim($sub);
            if ($sub !== '') {
                $clean_subs[] = $sub;
            }
        }
        
        // 去重
        $clean_subs = array_unique($clean_subs);

        // 拼接行：大类;小类1,小类2;
        $line = $main_cat . ';' . implode(',', $clean_subs) . ";\n";
        $content .= $line;
    }

    // 写入文件
    if (file_put_contents($config_file, $content) === false) {
        throw new Exception("无法写入配置文件，请检查文件权限");
    }

    dts_set_feedback('success', '分类配置已更新');
    // 保存后跳转回管理页，方便查看结果
    header('Location: /cp/index.php?action=dts_category_manage');
    exit();

} catch (Exception $e) {
    error_log("DTS Category Save Error: " . $e->getMessage());
    dts_set_feedback('danger', '保存失败：' . $e->getMessage());
    header('Location: /cp/index.php?action=dts_category_manage');
    exit();
}