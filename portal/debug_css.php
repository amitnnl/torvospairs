<?php
echo "<h3>Contents of portal/assets/css/</h3>";
$cssDir = __DIR__ . '/assets/css';
if (file_exists($cssDir) && is_dir($cssDir)) {
    $files = scandir($cssDir);
    foreach ($files as $file) {
        echo $file . (is_dir($cssDir . '/' . $file) ? ' [DIR]' : '') . "<br>";
    }
} else {
    echo "portal/assets/css folder does NOT exist or is not a directory";
}
?>
