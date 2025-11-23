<?php
/**
 * DTS 保存分类配置
 */

declare(strict_types=1);

// 加载 DTS 函数库
require_once APP_PATH_CP . '/dts/dts_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /cp/index.php?action=dts_category_manage');
    exit();
}

$categories_text = dts_post('categories', '');
$config_file = APP_PATH_CP . '/dts/dts_category.conf';
$config_dir = dirname($config_file);

// 确保配置目录存在
if (!is_dir($config_dir) && !mkdir($config_dir, 0775, true) && !is_dir($config_dir)) {
    dts_set_feedback('danger', '保存失败：无法创建配置目录');
    header('Location: /cp/index.php?action=dts_category_manage');
    exit();
}

// 备份旧的配置文件（若存在）
if (file_exists($config_file) && !@copy($config_file, $config_file . '.bak')) {
    dts_set_feedback('danger', '保存失败：无法备份原有配置');
    header('Location: /cp/index.php?action=dts_category_manage');
    exit();
}

// 清理和验证输入
$lines = preg_split('/\r?\n/', (string)$categories_text);
$cleaned_lines = [];
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
        $cleaned_lines[] = $line;
        continue;
    }

    $parts = explode(';', $line);
    if (count($parts) >= 2) {
        $main_cat = trim($parts[0]);
        $sub_cats_str = trim($parts[1]);

        if ($main_cat !== '') {
            $sub_cats = [];
            if ($sub_cats_str !== '') {
                // Normalize commas and split
                $sub_cats_str = str_replace(['，', '、'], ',', $sub_cats_str);
                $sub_cats = array_map('trim', explode(',', $sub_cats_str));
                $sub_cats = array_filter($sub_cats); // Remove empty values
                $sub_cats = array_unique($sub_cats); // Remove duplicates
            }
            $cleaned_lines[] = $main_cat . ';' . implode(',', $sub_cats) . ';';
        }
    }
}

// 将清理后的内容写回配置文件
$content = implode(PHP_EOL, $cleaned_lines);
if ($content !== '') {
    $content .= PHP_EOL; // 确保文件以换行结尾，避免提示残留
}

$written = file_put_contents($config_file, $content, LOCK_EX);

if ($written === false || $written !== strlen($content)) {
    dts_set_feedback('danger', '保存失败：无法写入配置文件');
} else {
    dts_set_feedback('success', '分类配置已保存');
}

header('Location: /cp/index.php?action=dts_category_manage');
exit();
