<?php
/**
 * API: Logout
 * 文件路径: app/mrs/api/logout.php
 */

if (!defined('MRS_ENTRY')) {
    die('Access denied');
}

mrs_destroy_user_session();

header('Location: /mrs/ap/index.php?action=login&error=logout');
exit;
