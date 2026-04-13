<?php
require 'config/db_config.php';
try {
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $stmt = $db->query("SHOW TABLES");
    while ($row = $stmt->fetch()) {
        echo $row[0] . PHP_EOL;
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
