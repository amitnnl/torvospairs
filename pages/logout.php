<?php
require_once dirname(__DIR__) . '/config/database.php';
requireLogin();
session_destroy();
header('Location: ' . APP_URL . '/admin.php');
exit;
