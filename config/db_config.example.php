<?php
/**
 * DATABASE CREDENTIALS FOR LIVE SERVER
 * Rename this file to db_config.php on your live server.
 * This file is git-ignored and won't be overwritten.
 */

// If on live server, define these. If on localhost, you can leave them or the main config will use defaults.
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$is_localhost = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);

if (!$is_localhost) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'your_live_user');
    define('DB_PASS', 'your_live_password');
    define('DB_NAME', 'your_live_db_name');
}
?>
