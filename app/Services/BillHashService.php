<?php
declare(strict_types=1);

/** Find one bill in the configured branch. Ambiguous hashes must not select a bill. */
function findBillByHash(PDO $pdo, string $branch, string $hash): ?string
{
    if (!preg_match('/\A[a-p]{64}\z/D', $hash) || $branch === '') return null;
    $stmt = $pdo->prepare('SELECT TOP (2) Fn_Doc_No FROM Fnt_Header WHERE Branch_ID = ? AND Fn_Qrcode_Order = ?');
    $stmt->execute([$branch, $hash]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    if (count($rows) !== 1) return null;
    $doc = trim((string)($rows[0]['Fn_Doc_No'] ?? ''));
    return $doc !== '' ? $doc : null;
}

function canonicalBillHash(PDO $pdo, string $branch, string $doc, string $storedHash): string
{
    $hash = trim($storedHash);
    if (!preg_match('/\A[a-p]{64}\z/D', $hash)) return '';
    return findBillByHash($pdo, $branch, $hash) === $doc ? $hash : '';
}
