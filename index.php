<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';

require_once __DIR__ . '/includes/session.php';

$staffEmId = trim((string)($_SESSION['Em_Id'] ?? ''));
$staffName = trim((string)($_SESSION['Em_Fullname'] ?? ''));
$staffUser = trim((string)($_SESSION['SUser'] ?? ''));
$staffRole = trim((string)($_SESSION['Status'] ?? ''));

$queryFirstPart = explode('&', (string)($_SERVER['QUERY_STRING'] ?? ''), 2)[0];
$shortDocNo = preg_match('/^\d+$/D', $queryFirstPart) ? $queryFirstPart : '';
$fnDocNo = trim((string)($_GET['Fn_Doc_No'] ?? $_GET['doc'] ?? $_GET['doc_no'] ?? $_GET['bill'] ?? $shortDocNo));
$tableNo = trim((string)($_GET['table'] ?? ''));
$branchId = trim((string)($_GET['Branch_ID'] ?? $_GET['branch'] ?? getenv('DEFAULT_BRANCH_ID') ?? '001'));

if (!isset($initialPage)) {
    if ($fnDocNo !== '' || $tableNo !== '') {
        $initialPage = 'menu';
    } elseif ($staffEmId !== '') {
        $initialPage = 'tables';
    } else {
        $initialPage = 'login';
    }
}
$initialClosed = false;
$initialCancelled = false;
$guestsCount = 4;

if ($fnDocNo !== '' || $tableNo !== '') {
    try {
        $pdo = Database::getConnection();
        if ($pdo) {
            if ($fnDocNo !== '') {
                $stmt = $pdo->prepare("SELECT Doc_Sts, Table_ID, Table_Person FROM Fnt_Header WHERE Fn_Doc_No = ? AND Branch_ID = ?");
                $stmt->execute([$fnDocNo, $branchId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $docSts = (int)$row['Doc_Sts'];
                    $initialCancelled = $docSts === 3;
                    if ($docSts === 1 || $docSts === 3) {
                        $initialPage = 'closed';
                        $initialClosed = true;
                    }
                    if ($tableNo === '' && !empty($row['Table_ID'])) {
                        $rawTable = trim((string)$row['Table_ID']);
                        $tableNo = ltrim($rawTable, '0') ?: $rawTable;
                    }
                    if (isset($row['Table_Person']) && (int)$row['Table_Person'] > 0) {
                        $guestsCount = (int)$row['Table_Person'];
                    }
                }
            } elseif ($tableNo !== '') {
                $paddedTable = str_pad($tableNo, 3, '0', STR_PAD_LEFT);
                $autoOpen = filter_var($_GET['open'] ?? $_GET['auto_open'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if ($autoOpen && $staffEmId !== '') {
                    require_once __DIR__ . '/includes/functions.php';
                    $tablePerson = trim((string)($_GET['Table_Person'] ?? $_GET['guests'] ?? ''));
                    $opened = openOrGetTableBill($pdo, $tableNo, $branchId, $staffEmId, $tablePerson);
                    $fnDocNo = $opened['docNo'];
                    $guestsCount = $opened['guests'];
                } else {
                    // Prefer open bill (Doc_Sts = 0) on this table first
                    $stmt = $pdo->prepare("SELECT TOP 1 Fn_Doc_No, Doc_Sts, Table_Person FROM Fnt_Header WHERE (Table_ID = ? OR Table_ID = ?) AND Doc_Sts = 0 AND Branch_ID = ? ORDER BY Fn_Doc_Date_Time DESC");
                    $stmt->execute([$tableNo, $paddedTable, $branchId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $stmt->closeCursor();
                    if ($row) {
                        $fnDocNo = trim((string)$row['Fn_Doc_No']);
                        if (isset($row['Table_Person']) && (int)$row['Table_Person'] > 0) {
                            $guestsCount = (int)$row['Table_Person'];
                        }
                    } elseif ($staffEmId !== '') {
                        // Only if staff is logged in: if table is marked open (Table_STS = 'O') in pbm_table, recover/create open bill
                        $stmtT = $pdo->prepare("SELECT TOP 1 Table_STS FROM pbm_table WHERE (Table_ID = ? OR Table_ID = ?) AND Branch_ID = ?");
                        $stmtT->execute([$tableNo, $paddedTable, $branchId]);
                        $tRow = $stmtT->fetch(PDO::FETCH_ASSOC);
                        $stmtT->closeCursor();
                        if ($tRow && trim((string)($tRow['Table_STS'] ?? '')) === 'O') {
                            require_once __DIR__ . '/includes/functions.php';
                            $opened = openOrGetTableBill($pdo, $tableNo, $branchId, $staffEmId, '');
                            $fnDocNo = $opened['docNo'];
                            $guestsCount = $opened['guests'];
                        }
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        // Fall back to client-side syncBill
    }
}

$displayTable = $tableNo !== '' ? $tableNo : '12';
$displayDoc = $fnDocNo;

require __DIR__ . '/includes/app.php';
