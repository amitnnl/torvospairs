<?php
function findFile($dir, $fileName) {
    if (!is_dir($dir)) return;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        if ($file === $fileName) {
            echo "<strong>FOUND!</strong> path: " . $path . "<br>";
        }
        if (is_dir($path)) {
            findFile($path, $fileName);
        }
    }
}

echo "<h3>Searching for portal.css...</h3>";
findFile('/home/hotelsunplaza/torvotools.com', 'portal.css');
echo "Done.";
?>
