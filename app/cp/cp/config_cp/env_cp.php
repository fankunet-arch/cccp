<?php
// /app/cp/config_cp/env_cp.php

/**
 * 核心环境配置
 * 根据用户提供的凭据重建
 */

return [
    'app_env'   => 'dev', // 从 'prod' 改为 'dev'
    'app_debug' => true,  // 从 false 改为 true，以显示 PHP 错误
    'app_version' => '1.0.0',
    'timezone'  => 'Europe/Madrid', // 根据数据库和业务推断

    'db' => [
        'host'    => 'mhdlmskp2kpxguj.mysql.db',
        'port'    => 3306,
        'name'    => 'mhdlmskp2kpxguj',
        'user'    => 'mhdlmskp2kpxguj',
        'pass'    => 'BWNrmksqMEqgbX37r3QNDJLGRrUka',
        'charset' => 'utf8mb4',
    ],
];
