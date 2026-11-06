<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

require_once __DIR__ . '/../includes/session.php';

$sessionEmId = trim((string)($_SESSION['Em_Id'] ?? ''));
$fnDocNo = trim((string)($_GET['Fn_Doc_No'] ?? $_GET['doc'] ?? $_GET['doc_no'] ?? $_GET['bill'] ?? ''));
$branchId = trim((string)($_GET['Branch_ID'] ?? $_GET['branch'] ?? (getenv('DEFAULT_BRANCH_ID') ?: '')));
if ($branchId === '') { jsonResponse(['error' => ['code' => 'MISSING_BRANCH', 'message' => 'กรุณาระบุรหัสสาขาใน URL หรือ DEFAULT_BRANCH_ID']], 400); }
$tableInput = trim((string)($_GET['table'] ?? ''));

if ($fnDocNo === '' && $tableInput === '') {
    jsonResponse(['error' => ['code' => 'MISSING_TABLE_OR_BILL', 'message' => 'กรุณาระบุหมายเลขโต๊ะหรือเลขที่บิล']], 400);
}


$pdo = Database::getConnection();
if (!$pdo) {
    $tableDisplay = $tableInput;
    jsonResponse([
        'mode' => 'offline',
        'bill' => [
            'id' => $fnDocNo ?: 'OFFLINE',
            'docNo' => $fnDocNo ?: '',
            'docSts' => 0,
            'docStsDesc' => 'ออฟไลน์',
            'table' => $tableDisplay,
            'tableName' => 'โต๊ะ ' . $tableDisplay,
            'guests' => null,
            'status' => 'open',
            'total' => 0.0,
            'closed' => false,
            'canOrder' => false,
        ],
    ]);
}

try {
    // -------------------------------------------------------------
    // Case 1: Scanned QR code with Fn_Doc_No (or doc parameter)
    // -------------------------------------------------------------
    if ($fnDocNo !== '') {
        // Query exact bill in Fnt_Header
        $sql = "SELECT b.Branch_ID, b.Fn_Doc_No, b.Doc_Sts, b.Table_ID, 
                       CONVERT(varbinary(max), b.Table_Name) as Table_Name, 
                       b.Fn_Total, b.Table_Person, b.Fn_Doc_Date_Time 
                FROM Fnt_Header b 
                WHERE b.Fn_Doc_No = ? AND b.Branch_ID = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fnDocNo, $branchId]);
        $bill = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bill) {
            jsonResponse([
                'mode' => 'live',
                'bill' => null,
                'error' => [
                    'code' => 'BILL_NOT_FOUND',
                    'message' => "ไม่พบข้อมูลบิลเลขที่ {$fnDocNo} สาขา {$branchId} ในระบบ หรือ QR Code ไม่ถูกต้อง",
                ],
            ], 404);
        }

        $docSts = (int)$bill['Doc_Sts'];
        $rawTableName = Database::decodeThai($bill['Table_Name']);
        $tableId = trim((string)$bill['Table_ID']);
        $tableUnpadded = ltrim($tableId, '0') ?: $tableId;
        $tableName = $rawTableName ?: ('โต๊ะ ' . $tableUnpadded);
        $guests = (int)($bill['Table_Person'] ?? 0) ?: null;
        if ($guests !== null && $guests < 0) $guests = null;
        $total = (float)$bill['Fn_Total'];

        // Doc_Sts: 0 = เปิดบิล, 1 = ออกใบเสร็จแล้ว, 3 = ยกเลิก
        if ($docSts === 0) {
            // เปิดบิล (Open - Can order)
            jsonResponse([
                'mode' => 'live',
                'bill' => [
                    'id' => $bill['Fn_Doc_No'],
                    'docNo' => $bill['Fn_Doc_No'],
                    'docSts' => 0,
                    'docStsDesc' => 'เปิดบิล',
                    'table' => $tableUnpadded,
                    'tableId' => $tableId,
                    'tableName' => $tableName,
                    'guests' => $guests,
                    'status' => 'open',
                    'total' => $total,
                    'closed' => false,
                    'canOrder' => true,
                ],
            ]);
        } elseif ($docSts === 1) {
            // ออกใบเสร็จแล้ว (Receipt issued - Closed)
            jsonResponse([
                'mode' => 'live',
                'bill' => [
                    'id' => $bill['Fn_Doc_No'],
                    'docNo' => $bill['Fn_Doc_No'],
                    'docSts' => 1,
                    'docStsDesc' => 'ออกใบเสร็จแล้ว',
                    'table' => $tableUnpadded,
                    'tableId' => $tableId,
                    'tableName' => $tableName,
                    'guests' => $guests,
                    'status' => 'closed',
                    'total' => $total,
                    'closed' => true,
                    'canOrder' => false,
                    'message' => 'บิลนี้ชำระเงินและออกใบเสร็จเรียบร้อยแล้ว ไม่สามารถสั่งอาหารเพิ่มได้',
                ],
            ]);
        } elseif ($docSts === 3) {
            // ยกเลิก (Cancelled / Void)
            jsonResponse([
                'mode' => 'live',
                'bill' => [
                    'id' => $bill['Fn_Doc_No'],
                    'docNo' => $bill['Fn_Doc_No'],
                    'docSts' => 3,
                    'docStsDesc' => 'ยกเลิก',
                    'table' => $tableUnpadded,
                    'tableId' => $tableId,
                    'tableName' => $tableName,
                    'guests' => $guests,
                    'status' => 'cancelled',
                    'total' => $total,
                    'closed' => true,
                    'cancelled' => true,
                    'canOrder' => false,
                    'message' => 'บิลนี้ถูกยกเลิกแล้ว (Doc_Sts = 3) ไม่สามารถสั่งอาหารได้ กรุณาติดต่อพนักงาน',
                ],
            ]);
        } else {
            // สถานะอื่นๆ
            jsonResponse([
                'mode' => 'live',
                'bill' => [
                    'id' => $bill['Fn_Doc_No'],
                    'docNo' => $bill['Fn_Doc_No'],
                    'docSts' => $docSts,
                    'docStsDesc' => "สถานะ $docSts",
                    'table' => $tableUnpadded,
                    'tableId' => $tableId,
                    'tableName' => $tableName,
                    'guests' => $guests,
                    'status' => 'closed',
                    'total' => $total,
                    'closed' => true,
                    'canOrder' => false,
                    'message' => "สถานะบิลไม่พร้อมรับออเดอร์ (Doc_Sts = $docSts)",
                ],
            ]);
        }
    }

    // -------------------------------------------------------------
    // Case 2: Queried by Table ID (e.g. ?table=2 or default table)
    // -------------------------------------------------------------
    $tableTarget = $tableInput;
    $tablePadded = str_pad((string)$tableTarget, 3, '0', STR_PAD_LEFT);
    $tableUnpadded = ltrim((string)$tableTarget, '0') ?: $tableTarget;
    $autoOpen = false;

    // 2.0 Check table info & status in pbm_table
    $tableSts = '';
    $pbmTableName = '';
    $pbmTableId = $tablePadded;
    try {
        $stmtTable = $pdo->prepare(
            "SELECT TOP 1 Table_ID, CONVERT(varbinary(max), Table_Name) AS Table_Name, Table_STS
             FROM pbm_table
             WHERE (Table_ID = ? OR Table_ID = ? OR Table_ID = ?) AND Branch_ID = ?"
        );
        $stmtTable->execute([$tableTarget, $tablePadded, $tableUnpadded, $branchId]);
        $tRow = $stmtTable->fetch(PDO::FETCH_ASSOC);
        $stmtTable->closeCursor();
        if ($tRow) {
            $pbmTableId = trim((string)$tRow['Table_ID']) ?: $tablePadded;
            $pbmTableName = trim(Database::decodeThai($tRow['Table_Name'] ?? null));
            $tableSts = trim((string)($tRow['Table_STS'] ?? ''));
        }
    } catch (Throwable $e) {
        // Ignore if pbm_table is not present in minimal test schemas
    }

    // If auto-open is requested, require staff login
    if ($autoOpen) {
        if ($sessionEmId === '') {
            jsonResponse([
                'mode' => 'live',
                'bill' => null,
                'error' => [
                    'code' => 'STAFF_LOGIN_REQUIRED',
                    'message' => 'กรุณาเข้าสู่ระบบพนักงานก่อนทำการเปิดโต๊ะ',
                ],
            ], 401);
        }
        $tablePerson = trim((string)($_GET['Table_Person'] ?? $_GET['guests'] ?? ''));
        $opened = openOrGetTableBill($pdo, (string)$tableTarget, $branchId, $sessionEmId, $tablePerson);
        jsonResponse([
            'mode' => 'live',
            'bill' => [
                'id' => $opened['docNo'],
                'docNo' => $opened['docNo'],
                'docSts' => 0,
                'docStsDesc' => 'เปิดบิล',
                'table' => $opened['tableUnpadded'],
                'tableId' => $opened['tableId'],
                'tableName' => $opened['tableName'],
                'tableSts' => $opened['tableSts'],
                'guests' => $opened['guests'],
                'status' => 'open',
                'total' => 0.0,
                'closed' => false,
                'canOrder' => true,
                'created' => $opened['created'],
            ],
        ]);
    }

    // 2.1 Check for active open bill (Doc_Sts = 0) on this table
    $stmt = $pdo->prepare("SELECT TOP 1 b.Branch_ID, b.Fn_Doc_No, b.Doc_Sts, b.Table_ID, 
                                  CONVERT(varbinary(max), b.Table_Name) as Table_Name, 
                                  b.Fn_Total, b.Table_Person, b.Fn_Doc_Date_Time 
                           FROM Fnt_Header b 
                           WHERE (b.Table_ID = ? OR b.Table_ID = ? OR b.Table_ID = ?) 
                             AND b.Doc_Sts = 0 
                             AND b.Branch_ID = ? 
                           ORDER BY b.Fn_Doc_Date_Time DESC");
    $stmt->execute([$pbmTableId, $tablePadded, $tableUnpadded, $branchId]);
    $openBill = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if ($openBill) {
        $rawTableName = Database::decodeThai($openBill['Table_Name']);
        $tableName = $rawTableName ?: ($pbmTableName ?: ('โต๊ะ ' . $tableUnpadded));
        $guests = (int)($openBill['Table_Person'] ?? 0) ?: null;
        if ($guests !== null && $guests < 0) $guests = null;

        jsonResponse([
            'mode' => 'live',
            'bill' => [
                'id' => $openBill['Fn_Doc_No'],
                'docNo' => $openBill['Fn_Doc_No'],
                'docSts' => 0,
                'docStsDesc' => 'เปิดบิล',
                'table' => $tableUnpadded,
                'tableId' => $openBill['Table_ID'],
                'tableName' => $tableName,
                'tableSts' => $tableSts ?: 'O',
                'guests' => $guests,
                'status' => 'open',
                'total' => (float)$openBill['Fn_Total'],
                'closed' => false,
                'canOrder' => true,
            ],
        ]);
    }

    // 2.3 No open bill (Doc_Sts = 0). Check latest bill for table to get table name
    $stmt = $pdo->prepare("SELECT TOP 1 b.Branch_ID, b.Fn_Doc_No, b.Doc_Sts, b.Table_ID, 
                                  CONVERT(varbinary(max), b.Table_Name) as Table_Name, 
                                  b.Fn_Total, b.Table_Person, b.Fn_Doc_Date_Time 
                           FROM Fnt_Header b 
                           WHERE (b.Table_ID = ? OR b.Table_ID = ?) 
                             AND b.Branch_ID = ? 
                           ORDER BY b.Fn_Doc_Date_Time DESC");
    $stmt->execute([$tablePadded, $tableUnpadded, $branchId]);
    $latestBill = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    // Table Name from pbm_table, Fnt_Header, or default
    $tableName = $pbmTableName ?: ($latestBill ? Database::decodeThai($latestBill['Table_Name']) : ('โต๊ะ ' . $tableUnpadded));
    if (!$tableName) $tableName = 'โต๊ะ ' . $tableUnpadded;

    jsonResponse([
        'mode' => 'live',
        'bill' => [
            'id' => '',
            'docNo' => '',
            'docSts' => 0,
            'docStsDesc' => 'โต๊ะว่าง (รอพนักงานเปิดโต๊ะ)',
            'table' => $tableUnpadded,
            'tableId' => $pbmTableId,
            'tableName' => $tableName,
            'tableSts' => $tableSts,
            'guests' => null,
            'status' => 'available',
            'total' => 0.0,
            'closed' => false,
            'hasOpenBill' => false,
            'canOrder' => $sessionEmId !== '',
        ],
    ]);
} catch (Throwable $e) {
    appLog('error', 'bill request failed', ['error' => $e->getMessage()]);
    jsonResponse([
        'mode' => 'error',
        'error' => [
            'code' => 'SERVER_ERROR',
            'message' => 'ไม่สามารถโหลดข้อมูลบิลได้ กรุณาลองอีกครั้ง',
        ],
        'bill' => [
            'id' => '',
            'docNo' => '',
            'docSts' => 0,
            'table' => $tableInput,
            'tableName' => $tableInput !== '' ? 'โต๊ะ ' . $tableInput : '',
            'status' => 'open',
            'total' => 0.0,
            'closed' => false,
            'canOrder' => false,
        ],
    ], 500);
}
