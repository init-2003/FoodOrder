<?php
declare(strict_types=1);
require_once __DIR__ . '/../Database/db.php';

/** Check the active account on each request so permission changes take effect immediately. */
function staffCanViewExpenses(PDO $pdo, string $employeeId, string $username): bool
{
    if ($employeeId === '' || $username === '') return false;
    $userHex = Database::hexThai($username);
    $stmt = $pdo->prepare("SELECT TOP (2) SDeGree FROM System_Access WHERE Em_Id = ? AND SUser = CONVERT(nvarchar(50), $userHex) AND ISNULL(Sts, '') <> 'D'");
    $stmt->execute([$employeeId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    return count($rows) === 1 && (string)($rows[0]['SDeGree'] ?? '') === '4';
}
