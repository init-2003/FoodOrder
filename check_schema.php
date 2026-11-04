<?php
require __DIR__ . '/includes/db.php';
$pdo = Database::getConnection();
if (!$pdo) {
    echo "NO_DB\n";
    exit;
}
$stmt = $pdo->query("SELECT TOP 1 * FROM Fnt_Detail");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Fnt_Detail columns:\n";
print_r(array_keys($row ?: []));

$stmt2 = $pdo->query("SELECT TOP 1 * FROM Fnt_Header");
$row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
echo "Fnt_Header columns:\n";
print_r(array_keys($row2 ?: []));

