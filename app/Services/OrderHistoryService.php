<?php
declare(strict_types=1);
require_once __DIR__ . '/../Database/db.php';

function loadOrderHistory(PDO $pdo, string $fnDocNo, string $branchId, string $tableInput): array
{
    $getHistoryImage = static function (string $name): string {
        if (str_contains($name, 'กาแฟ') || str_contains($name, 'ชา') || str_contains($name, 'น้ำ') || str_contains($name, 'โซดา') || str_contains($name, 'เบียร์') || str_contains($name, 'Americano') || str_contains($name, 'Latte') || str_contains($name, 'Tea') || str_contains($name, 'Cocoa')) return 'assets/images/drink.jpg';
        if (str_contains($name, 'ไอศกรีม') || str_contains($name, 'หวาน') || str_contains($name, 'สาคู') || str_contains($name, 'เฉาก๊วย') || str_contains($name, 'ถั่วดำ')) return 'assets/images/icecream.jpg';
        if (str_contains($name, 'สลัด') || str_contains($name, 'ยำ')) return 'assets/images/salad.jpg';
        if (str_contains($name, 'ก๋วย') || str_contains($name, 'เฝ่อ') || str_contains($name, 'ต้ม')) return 'assets/images/noodles.jpg';
        if (str_contains($name, 'แกง') || str_contains($name, 'คลุก') || str_contains($name, 'ผัด')) return 'assets/images/curry.jpg';
        return 'assets/images/pad-thai.jpg';
    };

    $formatQueStatus = static function (?string $sts): string {
        return match (trim((string)$sts)) {
            'S', '' => 'ส่งเข้าครัวแล้ว',
            'P' => 'ออกใบเสร็จแล้ว',
            'W' => 'เสิร์ฟแล้ว',
            default => (string)$sts,
        };
    };

    $header = null;

    // 1. If Fn_Doc_No is provided, query directly
    if ($fnDocNo !== '') {
        $stmt = $pdo->prepare("SELECT TOP 1 Fn_Doc_No, Fn_Doc_Date_Time, Doc_Sts, Fn_Total, Table_ID FROM Fnt_Header WHERE Fn_Doc_No = ? AND Branch_ID = ?");
        $stmt->execute([$fnDocNo, $branchId]);
        $header = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
    } else {
        // Query by table (prefer Doc_Sts = 0 open bill, then latest)
        $tableTarget = $tableInput;
        $tablePadded = str_pad((string)$tableTarget, 3, '0', STR_PAD_LEFT);
        $tableUnpadded = ltrim((string)$tableTarget, '0') ?: $tableTarget;

        $stmt = $pdo->prepare("SELECT TOP 1 Fn_Doc_No, Fn_Doc_Date_Time, Doc_Sts, Fn_Total, Table_ID FROM Fnt_Header WHERE (Table_ID = ? OR Table_ID = ?) AND Branch_ID = ? ORDER BY Doc_Sts ASC, Fn_Doc_Date_Time DESC");
        $stmt->execute([$tablePadded, $tableUnpadded, $branchId]);
        $header = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
    }

    if (!$header) {
        return [
            'mode' => 'live',
            'pendingItems' => [],
            'pendingTotal' => 0,
            'orders' => [],
            'confirmedTotal' => 0,
            'total' => 0,
            'docSts' => 0,
            'closed' => false,
            'cancelled' => false
        ];
    }

    $docNo = $header['Fn_Doc_No'];
    $docSts = (int)$header['Doc_Sts'];
    $billTotal = (float)$header['Fn_Total'];

    $pendingItems = [];
    $pendingTotal = 0.0;

    // 2. Fetch the bill's ordered items together, newest first.
    $sqlConfirmed = "SELECT ID_NO, Trade_ID, CONVERT(varbinary(max), Trade_Name) as Trade_Name, 
                            Qty, Sale_Price, Fn_Doc_Date_Time, 
                            CONVERT(varbinary(max), Group_Pro) as Group_Pro,
                            CONVERT(varbinary(max), Unit_Name) as Unit_Name,
                            Food_Que_STS, Print_STS
                     FROM Fnt_Detail 
                     WHERE Fn_Doc_No = ? 
                       AND Qty <> 0 
                       AND Branch_ID = ? 
                       AND NULLIF(LTRIM(RTRIM(ISNULL(Food_Que_STS,''))), '') IS NOT NULL
                     ORDER BY Fn_Doc_Date_Time DESC, ID_NO DESC";
    $stmtC = $pdo->prepare($sqlConfirmed);
    $stmtC->execute([$docNo, $branchId]);
    $rawConfirmed = $stmtC->fetchAll(PDO::FETCH_ASSOC);
    $stmtC->closeCursor();

    $historyItems = [];
    $confirmedTotal = 0.0;

    foreach ($rawConfirmed as $cRow) {
        $tName = Database::decodeThai($cRow['Trade_Name']);
        $groupPro = Database::decodeThai($cRow['Group_Pro']);
        $unitName = Database::decodeThai($cRow['Unit_Name']);
        $note = trim($groupPro, " \t\n\r\0\x0B()");
        $price = (float)$cRow['Sale_Price'];
        $qty = (int)$cRow['Qty'];
        $sts = trim((string)$cRow['Food_Que_STS']);
        $lineTotal = $price * $qty;
        $confirmedTotal += $lineTotal;

        $itemObj = [
            'key' => (string)$cRow['ID_NO'],
            'id' => $cRow['Trade_ID'],
            'name' => $tName,
            'qty' => $qty,
            'price' => $price,
            'unit' => $unitName,
            'note' => $note,
            'orderedAt' => $cRow['Fn_Doc_Date_Time'] ?? null,
            'foodQueSts' => $sts,
            'statusDesc' => $formatQueStatus($sts),
            'image' => $getHistoryImage($tName),
            'lineTotal' => $lineTotal,
        ];

        $historyItems[] = $itemObj;
    }

    $orders = $historyItems ? [[
        'id' => (string)$docNo,
        'total' => $confirmedTotal,
        'items' => $historyItems,
    ]] : [];

    $finalTotal = $billTotal > 0 ? $billTotal : ($confirmedTotal + $pendingTotal);

    return [
        'mode' => 'live',
        'docNo' => $docNo,
        'docSts' => $docSts,
        'closed' => $docSts === 1,
        'cancelled' => $docSts === 3,
        'pendingItems' => $pendingItems,
        'pendingTotal' => $pendingTotal,
        'orders' => $orders,
        'confirmedTotal' => $confirmedTotal,
        'total' => $finalTotal,
    ];
}
