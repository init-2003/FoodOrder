<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/includes/db.php';
$pdo = Database::getConnection();
if (!$pdo) {
    echo "DB connection failed\n";
    exit;
}
$stmt = $pdo->query("SELECT TOP 5 Fn_Doc_No, Table_ID, CONVERT(varbinary(max), Table_Name) as Table_Name, Doc_Sts, Fn_Total FROM Fnt_Header WHERE Doc_Sts = 1 ORDER BY Fn_Doc_No DESC");
$bills = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($bills as &$b) {
    $b['Table_Name'] = Database::decodeThai($b['Table_Name']);
}
echo json_encode($bills, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

