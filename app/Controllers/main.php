<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/db.php';

require_once __DIR__ . '/../Security/session.php';
header('Cache-Control: private, no-store');

$staffEmId = trim((string)($_SESSION['Em_Id'] ?? ''));
$staffName = trim((string)($_SESSION['Em_Fullname'] ?? ''));
$staffUser = trim((string)($_SESSION['SUser'] ?? ''));
$staffRole = trim((string)($_SESSION['Status'] ?? ''));

$queryFirstPart = explode('&', (string)($_SERVER['QUERY_STRING'] ?? ''), 2)[0];
$shortDocNo = preg_match('/^\d+$/D', $queryFirstPart) ? $queryFirstPart : '';
$fnDocNo = trim((string)($_GET['Fn_Doc_No'] ?? $_GET['doc'] ?? $_GET['doc_no'] ?? $_GET['bill'] ?? $shortDocNo));
if ($fnDocNo === '') {
    $uriPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
    if (preg_match('#/([0-9]{6,10})(?:/|$)#', $uriPath, $m)) {
        $fnDocNo = $m[1];
    }
}
$tableNo = trim((string)($_GET['table'] ?? ''));
$branchId = trim((string)($_GET['Branch_ID'] ?? $_GET['branch'] ?? (getenv('DEFAULT_BRANCH_ID') ?: '')));
if ($branchId === '') { http_response_code(500); exit('กรุณากำหนด DEFAULT_BRANCH_ID'); }
$billHash = (string)($_GET['billHash'] ?? '');
if ($billHash !== '') {
    require_once __DIR__ . '/../Services/BillHashService.php';
    $hashLookupUnavailable = false;
    try {
        $pdo = Database::getConnection();
        if (!$pdo) throw new RuntimeException('Database unavailable');
        $fnDocNo = findBillByHash($pdo, $branchId, $billHash);
    } catch (Throwable $e) {
        appLog('error', 'Bill hash lookup failed', ['error' => $e->getMessage()]);
        $hashLookupUnavailable = true;
        $fnDocNo = null;
    }
    if ($fnDocNo === null) {
        http_response_code($hashLookupUnavailable ? 503 : 404);
        require __DIR__ . '/../Views/bill-unavailable.php';
        return;
    }
}

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
$guestsCount = null;
$canonicalBillHash = '';

if ($fnDocNo !== '' || $tableNo !== '') {
    try {
        $pdo = Database::getConnection();
        if ($pdo) {
            if ($fnDocNo !== '') {
                $stmt = $pdo->prepare("SELECT Doc_Sts, Table_ID, Table_Person, CONVERT(varbinary(max), Fn_Qrcode_Order) AS Fn_Qrcode_Order FROM Fnt_Header WHERE Fn_Doc_No = ? AND Branch_ID = ?");
                $stmt->execute([$fnDocNo, $branchId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
                    if ($staffEmId !== '' && preg_match('#/staff/tables/([0-9]{6,10})/?$#D', $path, $match) && $match[1] === $fnDocNo) {
                        $candidate = trim(Database::decodeThai($row['Fn_Qrcode_Order'] ?? null));
                        if (preg_match('/\A[a-p]{64}\z/D', $candidate)) {
                            try {
                                require_once __DIR__ . '/../Services/BillHashService.php';
                                $canonicalBillHash = canonicalBillHash($pdo, $branchId, $fnDocNo, $candidate);
                            } catch (Throwable $e) {
                                appLog('warning', 'Cannot resolve canonical bill hash', ['error' => $e->getMessage()]);
                            }
                        }
                    }
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
                $autoOpen = false;
                if ($autoOpen && $staffEmId !== '') {
                    require_once __DIR__ . '/../Services/functions.php';
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
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        // Fall back to client-side syncBill
    }
}

if (!isset($canViewExpenses) && $staffEmId !== '') {
    try {
        require_once __DIR__ . '/../Services/ExpenseAccessService.php';
        $expDb = $pdo ?? Database::getConnection();
        $canViewExpenses = $expDb ? staffCanViewExpenses($expDb, $staffEmId, $staffUser) : false;
    } catch (\Throwable $e) {
        $canViewExpenses = false;
    }
}

$displayTable = $tableNo;
$displayDoc = $fnDocNo;

require __DIR__ . '/../Views/app.php';
