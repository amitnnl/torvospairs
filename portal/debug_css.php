<?php
$file = __DIR__ . '/assets/css/portal.css';
echo "File exists: " . (file_exists($file) ? 'YES' : 'NO') . "<br>";
echo "Path: " . $file;
?>
