<?php
declare(strict_types=1);

function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

/**
 * Open a table or retrieve its active open bill (Doc_Sts = 0) using POS logic:
 * 1. Check pbm_table for Table_ID, Table_Name, and Table_STS.
 * 2. If Table_STS == 'O', look for an existing open bill (Doc_Sts = 0) in Fnt_Header.
 * 3. If Table_STS !== 'O' or no open bill exists in Fnt_Header:
 *    - Generate next Fn_Doc_No from pbm_ctrl (pb_run_cd = 'FIN', 6-digit zero-padded PB_RUN_NO)
 *    - Increment pb_run_no in pbm_ctrl
 *    - Update pbm_table SET Table_STS = 'O'
 *    - Insert new open bill (Doc_Sts = '0') into Fnt_Header
 *
 * Caller may manage the transaction externally or let this function wrap in a transaction.
 *
 * @return array{docNo: string, tableId: string, tableUnpadded: string, tableName: string, tableSts: string, orderNo: int, guests: int, created: bool}
 */
function openOrGetTableBill(
    PDO $pdo,
    string $tableInput,
    string $branchId = '001',
    string $pbUser = '',
    string|int $tablePerson = ''
): array {
    $rawInput = trim($tableInput);
    if ($rawInput === '') {
        $rawInput = trim((string)(getenv('DEFAULT_TABLE_ID') ?: '012'));
    }
    $tablePadded = str_pad($rawInput, 3, '0', STR_PAD_LEFT);
    $tableUnpadded = ltrim($rawInput, '0') ?: $rawInput;

    if ($pbUser === '') {
        $pbUser = trim((string)($_SESSION['Em_Id'] ?? getenv('POS_USER') ?: '333333'));
    }

    $personStr = trim((string)$tablePerson);
    $guestsInt = (int)$personStr > 0 ? (int)$personStr : null;

    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }

    try {
        // 1. Check table info & status in pbm_table
        $tableId = $tablePadded;
        $tableName = 'โต๊ะ ' . $tableUnpadded;
        $tableSts = '';

        $stmtTable = $pdo->prepare(
            "SELECT TOP 1 Table_ID, CONVERT(varbinary(max), Table_Name) AS Table_Name, Table_STS
             FROM pbm_table WITH (UPDLOCK, ROWLOCK)
             WHERE (Table_ID = ? OR Table_ID = ? OR Table_ID = ?) AND Branch_ID = ?"
        );
        $stmtTable->execute([$rawInput, $tablePadded, $tableUnpadded, $branchId]);
        $tableRow = $stmtTable->fetch(PDO::FETCH_ASSOC);
        $stmtTable->closeCursor();

        if ($tableRow) {
            $tableId = trim((string)$tableRow['Table_ID']);
            $tableUnpadded = ltrim($tableId, '0') ?: $tableId;
            $decodedTableName = trim(Database::decodeThai($tableRow['Table_Name'] ?? null));
            if ($decodedTableName !== '') {
                $tableName = $decodedTableName;
            }
            $tableSts = trim((string)($tableRow['Table_STS'] ?? ''));
        }

        // 2. If Table_STS === 'O', check for existing open bill (Doc_Sts = 0) in Fnt_Header
        if ($tableSts === 'O') {
            $stmtBill = $pdo->prepare(
                "SELECT TOP 1 Fn_Doc_No, Doc_Sts, Fn_Order_No, Table_ID,
                        CONVERT(varbinary(max), Table_Name) AS Table_Name, Table_Person
                 FROM Fnt_Header b WITH (UPDLOCK, ROWLOCK)
                 WHERE (b.Table_ID = ? OR b.Table_ID = ? OR b.Table_ID = ?)
                   AND b.Doc_Sts = 0
                   AND b.Branch_ID = ?
                 ORDER BY b.Fn_Doc_Date_Time DESC"
            );
            $stmtBill->execute([$tableId, $tablePadded, $tableUnpadded, $branchId]);
            $openBill = $stmtBill->fetch(PDO::FETCH_ASSOC);
            $stmtBill->closeCursor();

            if ($openBill) {
                $docNo = trim((string)$openBill['Fn_Doc_No']);
                $billTableId = trim((string)($openBill['Table_ID'] ?? $tableId)) ?: $tableId;
                $decodedBillTable = trim(Database::decodeThai($openBill['Table_Name'] ?? null));
                if ($decodedBillTable !== '') {
                    $tableName = $decodedBillTable;
                }
                $billGuests = (int)($openBill['Table_Person'] ?? 0);
                if ($billGuests > 0) {
                    $guestsInt = $billGuests;
                }
                $orderNo = (int)($openBill['Fn_Order_No'] ?? 0);

                if ($ownTransaction) {
                    $pdo->commit();
                }

                return [
                    'docNo' => $docNo,
                    'tableId' => $billTableId,
                    'tableUnpadded' => ltrim($billTableId, '0') ?: $billTableId,
                    'tableName' => $tableName,
                    'tableSts' => 'O',
                    'orderNo' => $orderNo,
                    'guests' => $guestsInt,
                    'created' => false,
                ];
            }
        } else {
            // Even if pbm_table is not 'O' (or table not found in pbm_table), check if an open bill already exists
            $stmtBill = $pdo->prepare(
                "SELECT TOP 1 Fn_Doc_No, Doc_Sts, Fn_Order_No, Table_ID,
                        CONVERT(varbinary(max), Table_Name) AS Table_Name, Table_Person
                 FROM Fnt_Header b WITH (UPDLOCK, ROWLOCK)
                 WHERE (b.Table_ID = ? OR b.Table_ID = ? OR b.Table_ID = ?)
                   AND b.Doc_Sts = 0
                   AND b.Branch_ID = ?
                 ORDER BY b.Fn_Doc_Date_Time DESC"
            );
            $stmtBill->execute([$tableId, $tablePadded, $tableUnpadded, $branchId]);
            $openBill = $stmtBill->fetch(PDO::FETCH_ASSOC);
            $stmtBill->closeCursor();

            if ($openBill && $tableRow === false) {
                // Fallback when pbm_table doesn't have the row (e.g. test fixtures) but Fnt_Header has an open bill
                $docNo = trim((string)$openBill['Fn_Doc_No']);
                $billTableId = trim((string)($openBill['Table_ID'] ?? $tableId)) ?: $tableId;
                $decodedBillTable = trim(Database::decodeThai($openBill['Table_Name'] ?? null));
                if ($decodedBillTable !== '') {
                    $tableName = $decodedBillTable;
                }
                $billGuests = (int)($openBill['Table_Person'] ?? 0);
                if ($billGuests > 0) {
                    $guestsInt = $billGuests;
                }
                $orderNo = (int)($openBill['Fn_Order_No'] ?? 0);

                if ($ownTransaction) {
                    $pdo->commit();
                }

                return [
                    'docNo' => $docNo,
                    'tableId' => $billTableId,
                    'tableUnpadded' => ltrim($billTableId, '0') ?: $billTableId,
                    'tableName' => $tableName,
                    'tableSts' => 'O',
                    'orderNo' => $orderNo,
                    'guests' => $guestsInt,
                    'created' => false,
                ];
            }
        }

        // 3. Open table & create new bill in Fnt_Header using pbm_ctrl running number
        $stmtCtrl = $pdo->prepare(
            "SELECT PB_RUN_YR, PB_RUN_NO
             FROM pbm_ctrl WITH (UPDLOCK, ROWLOCK)
             WHERE pb_run_cd = 'FIN' AND Branch_ID = ?"
        );
        $stmtCtrl->execute([$branchId]);
        $ctrlRow = $stmtCtrl->fetch(PDO::FETCH_ASSOC);
        $stmtCtrl->closeCursor();

        if (!$ctrlRow) {
            throw new RuntimeException("ไม่พบข้อมูลรันเลขที่เอกสาร (pb_run_cd = 'FIN') สำหรับสาขา {$branchId} ในตาราง pbm_ctrl");
        }

        $runYr = trim((string)($ctrlRow['PB_RUN_YR'] ?? ''));
        $runNo = trim((string)($ctrlRow['PB_RUN_NO'] ?? '1'));
        $fnDocNo = $runYr . str_pad($runNo, 6, '0', STR_PAD_LEFT);

        // Update running number in Pbm_ctrl
        $stmtUpdCtrl = $pdo->prepare(
            "UPDATE Pbm_ctrl SET pb_run_no = pb_run_no + 1
             WHERE pb_run_cd = 'FIN' AND Branch_ID = ?"
        );
        $stmtUpdCtrl->execute([$branchId]);
        $stmtUpdCtrl->closeCursor();

        // Update Pbm_Table status to 'O' (Open)
        if ($tableRow) {
            $stmtUpdTable = $pdo->prepare(
                "UPDATE Pbm_Table SET Table_STS = 'O'
                 WHERE Table_ID = ? AND Branch_ID = ?"
            );
            $stmtUpdTable->execute([$tableId, $branchId]);
            $stmtUpdTable->closeCursor();
        } else {
            $stmtUpdTable = $pdo->prepare(
                "UPDATE Pbm_Table SET Table_STS = 'O'
                 WHERE (Table_ID = ? OR Table_ID = ?) AND Branch_ID = ?"
            );
            $stmtUpdTable->execute([$tablePadded, $tableUnpadded, $branchId]);
            $stmtUpdTable->closeCursor();
        }

        // Insert new bill header into Fnt_Header
        $datetime1 = date('Y-m-d H:i:s');
        $date1 = date('Y-m-d');
        $time1 = date('H:i:s');
        $tableNameHex = Database::hexThai($tableName);

        $sqlInsert = "INSERT INTO Fnt_Header (
            Branch_ID, Fn_Doc_No, Fn_Doc_Date, Fn_Doc_Time, Fn_Doc_Date_Time,
            Fn_Doc_Type, Doc_Sts, Customer_Id,
            Table_ID, Table_Name, Pb_User, Table_Person
        ) VALUES (
            ?, ?, CONVERT(date, CAST(? AS varchar(10)), 23), CAST(? AS varchar(8)), CONVERT(datetime, CAST(? AS varchar(19)), 120),
            '001', '0', 'CASH',
            ?, CONVERT(nvarchar(30), $tableNameHex), ?, ?
        )";
        $stmtIns = $pdo->prepare($sqlInsert);
        $stmtIns->execute([
            $branchId,
            $fnDocNo,
            $date1,
            $time1,
            $datetime1,
            $tableId,
            $pbUser,
            $guestsInt,
        ]);
        $stmtIns->closeCursor();

        if ($ownTransaction) {
            $pdo->commit();
        }

        return [
            'docNo' => $fnDocNo,
            'tableId' => $tableId,
            'tableUnpadded' => ltrim($tableId, '0') ?: $tableId,
            'tableName' => $tableName,
            'tableSts' => 'O',
            'orderNo' => 0,
            'guests' => $guestsInt,
            'created' => true,
        ];
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

