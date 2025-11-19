<?php
declare(strict_types=1);

/**
 * Sushisom private bootstrap under CP (som_*)
 * 仅用于该子模块的内部常量/工具；必要时扩展。
 */
if (!defined('BASE_PATH')) {
    // /html/abcabc_net
    define('BASE_PATH', dirname(__DIR__, 3));
    // 确保 CP 引导已加载（提供 $pdo、错误策略等）
    require_once BASE_PATH . '/app/cp/bootstrap.php';
}

if (!defined('SOM_APP_DIR')) {
    // /html/abcabc_net/app/cp/som
    define('SOM_APP_DIR', __DIR__);
}
