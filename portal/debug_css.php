<?php
$path = __DIR__ . '/assets/css';
echo "Path: " . $path . "<br>";
echo "Exists: " . (file_exists($path) ? 'YES' : 'NO') . "<br>";
echo "Is Dir: " . (is_dir($path) ? 'YES' : 'NO') . "<br>";
echo "Is File: " . (is_file($path) ? 'YES' : 'NO') . "<br>";
if (file_exists($path) && !is_dir($path)) {
    echo "File contents (first 20 chars): " . file_get_contents($path, false, null, 0, 20);
}
?>
