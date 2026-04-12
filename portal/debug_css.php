<?php
echo "<h3>Contents of portal/</h3>";
$dir = __DIR__;
$files = scandir($dir);
foreach ($files as $file) {
    echo $file . (is_dir($dir . '/' . $file) ? ' [DIR]' : '') . "<br>";
}

echo "<h3>Check portal/assets/</h3>";
$assetsDir = $dir . '/assets';
if (file_exists($assetsDir)) {
    $files = scandir($assetsDir);
    foreach ($files as $file) {
        echo $file . (is_dir($assetsDir . '/' . $file) ? ' [DIR]' : '') . "<br>";
    }
} else {
    echo "assets folder does NOT exist";
}
?>
