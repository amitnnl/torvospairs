<?php
$conn = new mysqli("localhost", "root", "", "u796699653_spareparts");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$tables = [];
$res = $conn->query("SHOW TABLES");
while ($row = $res->fetch_row()) {
    $table = $row[0];
    echo "TABLE: $table\n";
    $cRes = $conn->query("DESCRIBE `$table`");
    while ($cRow = $cRes->fetch_assoc()) {
        echo "  - {$cRow['Field']} ({$cRow['Type']})\n";
    }
}
?>
