<?php
/**
 * MRS Package Management System - Bootstrap
 * 文件路径: app/mrs/bootstrap.php
 * 说明: 系统初始化,加载配置和核心库
 */

// 防止直接访问
if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

// 1. 加载配置文件
require_once __DIR__ . '/config_mrs/env_mrs.php';

// 2. 获取数据库连接
try {
    $pdo = get_mrs_db_connection();
} catch (PDOException $e) {
    http_response_code(503);
    error_log('Critical: MRS Database connection failed - ' . $e->getMessage());
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>系统错误</title></head><body><h1>系统维护中</h1><p>数据库连接失败,请稍后再试。</p></body></html>');
}

// 3. 加载核心业务库
require_once MRS_LIB_PATH . '/mrs_lib.php';

// 4. 启动会话
mrs_start_secure_session();
