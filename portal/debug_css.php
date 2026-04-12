<?php
echo "<h3>Path Debug</h3>";
echo "Current File: " . __FILE__ . "<br>";
echo "Current Dir: " . __DIR__ . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";

function findFileCaseInsensitive($dir, $fileName) {
    if (!is_dir($dir)) return;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        if (strtolower($file) === strtolower($fileName)) {
            echo "<strong>FOUND!</strong> path: " . $path . "<br>";
        }
        if (is_dir($path)) {
            findFileCaseInsensitive($path, $fileName);
        }
    }
}

echo "<h3>Case-Insensitive Search for portal.css...</h3>";
findFileCaseInsensitive(dirname(__DIR__, 2), 'portal.css');
echo "Done.";
?>
