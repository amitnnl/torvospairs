<?php
$source = '/home/hotelsunplaza/repositories/torvospairs/portal/assets/css/portal.css';
$destDir = '/home/hotelsunplaza/torvotools.com/portal/assets/css';
$destFile = $destDir . '/portal.css';

echo "<h3>Manual Repair Utility</h3>";

// 1. Ensure directory exists
if (!file_exists($destDir)) {
    echo "Creating directory: $destDir... ";
    if (mkdir($destDir, 0755, true)) {
        echo "SUCCESS<br>";
    } else {
        echo "FAILED<br>";
    }
} else {
    echo "Directory exists: $destDir<br>";
}

// 2. Perform copy
echo "Copying from $source to $destFile... ";
if (file_exists($source)) {
    if (copy($source, $destFile)) {
        echo "<strong>SUCCESS!</strong><br>";
        chmod($destFile, 0644);
    } else {
        $err = error_get_last();
        echo "<strong>FAILED:</strong> " . $err['message'] . "<br>";
    }
} else {
    echo "<strong>FAILED: Source file not found in repository!</strong><br>";
}

echo "<h3>Verification</h3>";
if (file_exists($destFile)) {
    echo "The file now exists at: " . $destFile . "<br>";
    echo "Size: " . filesize($destFile) . " bytes<br>";
} else {
    echo "The file is STILL missing.<br>";
}
?>
