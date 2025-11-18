<?php
/**
 * DTS 保存分类配置
 */

declare(strict_types=1);

// 加载 DTS 函数库
require_once APP_PATH_CP . '/dts/dts_lib.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categories_text = dts_post('categories', '');
    $config_file = APP_PATH_CP . '/dts/dts_category.conf';

    // 备份旧的配置文件
    copy($config_file, $config_file . '.bak');

    // 将文本区的内容写入配置文件
    file_put_contents($config_file, $categories_text);

    dts_set_feedback('success', '分类配置已保存');
    header('Location: /index.php?action=dts_category_manage');
    exit();
}
