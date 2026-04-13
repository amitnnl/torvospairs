<?php
require_once __DIR__ . '/config/auth.php';
requireCustomerLogin();
$id = (int)($_GET['id'] ?? 0);
header("Location: rfq_view.php?id=$id");
exit;
