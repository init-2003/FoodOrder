<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

require_once __DIR__ . '/../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'Method not allowed']], 405);
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput ?: '{}', true);

if (!is_array($data) || empty($data['items']) || !is_array($data['items'])) {
    jsonResponse(['error' => ['code' => 'INVALID_PAYLOAD', 'message' => 'ไม่มีรายการอาหารในคำสั่งซื้อ']], 400);
}

$posMode = getenv('POS_MODE') ?: 'live';
if ($posMode === 'demo') {
    jsonResponse(['error' => ['code' => 'POS_DEMO_MODE', 'message' => 'ระบบอยู่ในโหมดทดลอง']], 501);
}

$pdo = Database::getConnection();
if (!$pdo) {
    jsonResponse(['error' => ['code' => 'DATABASE_UNAVAILABLE', 'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูล SQL Server ได้']], 503);
}

$docInput = trim((string)($data['doc'] ?? $data['docNo'] ?? $data['Fn_Doc_No'] ?? ''));
$tableInput = $data['table'] ?? getenv('DEFAULT_TABLE_ID') ?? '012';
$tablePadded = str_pad((string)$tableInput, 3, '0', STR_PAD_LEFT);
$tableUnpadded = ltrim((string)$tableInput, '0');
$branchId = trim((string)($data['branch'] ?? $data['Branch_ID'] ?? getenv('DEFAULT_BRANCH_ID') ?? '001'));
$pbUser = trim((string)(
    $data['staff_id']
    ?? $data['Em_Id']
    ?? $data['Pb_User']
    ?? $_SESSION['Em_Id']
    ?? getenv('POS_USER')
    ?? '333333'
));
if ($pbUser === '') {
    $pbUser = '333333';
}

$sessionToken = trim((string)($data['session_token'] ?? $data['sessionToken'] ?? ''));
$idempotencyKey = trim((string)($data['idempotency_key'] ?? $data['idempotencyKey'] ?? $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
$fromCart = ($data['from_cart'] ?? false) === true;
if ($fromCart && (!preg_match('/^st2_[a-f0-9]{64}$/D', $sessionToken) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $idempotencyKey))) {
    jsonResponse(['error' => ['code' => 'INVALID_CART', 'message' => 'ข้อมูลตะกร้าไม่ถูกต้อง']], 400);
}
$requestHash = hash('sha256', json_encode([$branchId, $docInput, $tableInput, $sessionToken, $fromCart, $data['items']], JSON_THROW_ON_ERROR));

// -------------------------------------------------------------
// Idempotency check: prevent duplicate submissions
// -------------------------------------------------------------
if ($idempotencyKey !== '') {
    $idemDir = __DIR__ . '/../storage/idempotency';
    $idemFile = $idemDir . '/' . hash('sha256', $idempotencyKey) . '.json';
    if (file_exists($idemFile)) {
        $cached = json_decode((string)file_get_contents($idemFile), true);
        if (is_array($cached) && (time() - ($cached['time'] ?? 0)) < 600 && !empty($cached['response'])) {
            if (isset($cached['requestHash']) && !hash_equals((string)$cached['requestHash'], $requestHash)) {
                jsonResponse(['error' => ['code' => 'IDEMPOTENCY_MISMATCH', 'message' => 'ข้อมูลคำสั่งซื้อไม่ตรงกับคำขอก่อนหน้า']], 409);
            }
            if ($fromCart && !isset($cached['requestHash'])) {
                jsonResponse(['error' => ['code' => 'IDEMPOTENCY_MISMATCH', 'message' => 'กรุณาลองสั่งใหม่อีกครั้ง']], 409);
            }
            $cachedResp = $cached['response'];
            $cachedResp['idempotent'] = true;
            jsonResponse($cachedResp, 200);
        }
    }
}

try {
    $pdo->beginTransaction();

    $now = date('Y-m-d H:i:s');
    $dateOnly = date('Y-m-d 00:00:00');
    $timeOnly = date('H:i:s');

    $docNo = null;
    $orderNo = 1;
    $tableName = 'โต๊ะ ' . ($tableUnpadded ?: '12');

    // -------------------------------------------------------------
    // Check bill status with row-level update lock (UPDLOCK, ROWLOCK) to prevent order round race condition
    // Doc_Sts = 0 (เปิดบิล), 1 (ออกใบเสร็จแล้ว), 3 (ยกเลิก)
    // -------------------------------------------------------------
    if ($docInput !== '') {
        $stmt = $pdo->prepare("SELECT b.Branch_ID, b.Fn_Doc_No, b.Doc_Sts, b.Fn_Order_No, b.Table_ID, 
                                      CONVERT(varbinary(max), b.Table_Name) as Table_Name, b.Table_Person 
                               FROM Fnt_Header b WITH (UPDLOCK, ROWLOCK)
                               WHERE b.Fn_Doc_No = ? AND b.Branch_ID = ?");
        $stmt->execute([$docInput, $branchId]);
        $targetBill = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if (!$targetBill) {
            $pdo->rollBack();
            jsonResponse(['error' => ['code' => 'BILL_NOT_FOUND', 'message' => "ไม่พบบิลเลขที่ $docInput ในระบบ"]], 404);
        }

        $docSts = (int)$targetBill['Doc_Sts'];
        if ($docSts === 1) {
            $pdo->rollBack();
            jsonResponse(['error' => ['code' => 'BILL_CLOSED', 'message' => 'บิลนี้ออกใบเสร็จแล้ว (Doc_Sts = 1) ไม่สามารถสั่งอาหารเพิ่มได้']], 400);
        } elseif ($docSts === 3) {
            $pdo->rollBack();
            jsonResponse(['error' => ['code' => 'BILL_CANCELLED', 'message' => 'บิลนี้ถูกยกเลิกแล้ว (Doc_Sts = 3) ไม่สามารถสั่งอาหารได้']], 400);
        } elseif ($docSts !== 0) {
            $pdo->rollBack();
            jsonResponse(['error' => ['code' => 'BILL_NOT_OPEN', 'message' => "บิลไม่อยู่ในสถานะเปิดบิล (Doc_Sts = $docSts)"]], 400);
        }

        // Bill is open (Doc_Sts = 0)
        $docNo = $targetBill['Fn_Doc_No'];
        $orderNo = ((int)($targetBill['Fn_Order_No'] ?? 0)) + 1;
        if ($orderNo < 1) $orderNo = 1;
        $tablePadded = $targetBill['Table_ID'];
        $tableUnpadded = ltrim($tablePadded, '0') ?: $tablePadded;
        $decodedName = Database::decodeThai($targetBill['Table_Name']);
        if ($decodedName) $tableName = $decodedName;
        $tableNameHex = Database::hexThai($tableName);
    } else {
        // Look for active open bill (Doc_Sts = 0) on this table with row lock
        $stmt = $pdo->prepare("SELECT TOP 1 b.Fn_Doc_No, b.Doc_Sts, b.Fn_Order_No, b.Table_ID, 
                                      CONVERT(varbinary(max), b.Table_Name) as Table_Name, b.Table_Person 
                               FROM Fnt_Header b WITH (UPDLOCK, ROWLOCK)
                               WHERE (b.Table_ID = ? OR b.Table_ID = ?) 
                                 AND b.Doc_Sts = 0 
                                 AND b.Branch_ID = ? 
                               ORDER BY b.Fn_Doc_Date_Time DESC");
        $stmt->execute([$tablePadded, $tableUnpadded, $branchId]);
        $openBill = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($openBill) {
            $docNo = $openBill['Fn_Doc_No'];
            $orderNo = ((int)($openBill['Fn_Order_No'] ?? 0)) + 1;
            if ($orderNo < 1) $orderNo = 1;
            $decodedName = Database::decodeThai($openBill['Table_Name']);
            if ($decodedName) $tableName = $decodedName;
            $tableNameHex = Database::hexThai($tableName);
        } else {
            // Only authenticated staff can open a new table / create a new bill
            $sessionEmId = trim((string)($_SESSION['Em_Id'] ?? ''));
            if ($sessionEmId === '') {
                $pdo->rollBack();
                jsonResponse([
                    'error' => [
                        'code' => 'STAFF_LOGIN_REQUIRED',
                        'message' => 'โต๊ะนี้ยังไม่ได้เปิดบิล กรุณาเข้าสู่ระบบพนักงานเพื่อเปิดโต๊ะก่อนสั่งอาหาร',
                    ],
                ], 401);
            }
            $tablePerson = $data['guests'] ?? $data['Table_Person'] ?? '';
            $openedBill = openOrGetTableBill($pdo, (string)$tableInput, $branchId, $sessionEmId, $tablePerson);
            $docNo = $openedBill['docNo'];
            $orderNo = ((int)($openedBill['orderNo'] ?? 0)) + 1;
            if ($orderNo < 1) $orderNo = 1;
            $tablePadded = $openedBill['tableId'];
            $tableUnpadded = $openedBill['tableUnpadded'];
            if (!empty($openedBill['tableName'])) {
                $tableName = $openedBill['tableName'];
            }
            $tableNameHex = Database::hexThai($tableName);
        }
    }

    // Resolve and lock every cart row before changing any stock or status.
    $drafts = [];
    if ($fromCart) {
        foreach ($data['items'] as $item) {
            $key = trim((string)($item['key'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $key) || isset($drafts[$key])) {
                $pdo->rollBack();
                jsonResponse(['error' => ['code' => 'INVALID_CART', 'message' => 'ข้อมูลตะกร้าไม่ถูกต้อง']], 400);
            }
            $stmt = $pdo->prepare("SELECT w.Detail_ID, w.State, w.Revision, w.Order_Key, d.Trade_ID, d.Qty, d.Qty_Cut_Stock, d.Sale_Price, d.Cost_Price, d.Food_Que_STS, CONVERT(varbinary(max), w.Note) AS Note FROM dbo.Web_Cart_Lines w WITH (UPDLOCK, ROWLOCK) JOIN dbo.Fnt_Detail d WITH (UPDLOCK, ROWLOCK) ON d.ID_NO=w.Detail_ID AND d.Branch_ID=w.Branch_ID AND d.Fn_Doc_No=w.Fn_Doc_No WHERE w.Branch_ID=? AND w.Fn_Doc_No=? AND w.Session_Token=? AND w.Cart_Key=?");
            $stmt->execute([$branchId, $docNo, $sessionToken, $key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            if (!$row) {
                $pdo->rollBack();
                jsonResponse(['error' => ['code' => 'CART_ITEM_CHANGED', 'message' => 'รายการในตะกร้ามีการเปลี่ยนแปลง กรุณาโหลดตะกร้าใหม่']], 409);
            }
            $drafts[$key] = $row;
            $drafts[$key]['expectedRevision'] = filter_var($item['revision'] ?? null, FILTER_VALIDATE_INT);
        }
        $alreadyConfirmed = count(array_filter($drafts, fn($row) => $row['State'] === 'S' && $row['Order_Key'] === $idempotencyKey));
        if ($alreadyConfirmed === count($drafts)) {
            $pdo->rollBack();
            jsonResponse(['success' => true, 'docNo' => $docNo, 'idempotent' => true, 'message' => 'รายการนี้ส่งเข้าครัวแล้ว']);
        }
        foreach ($drafts as $row) {
            if ($row['State'] !== 'D' || $row['Food_Que_STS'] === null || trim((string)$row['Food_Que_STS']) !== '' || $row['expectedRevision'] === false || $row['expectedRevision'] !== (int)$row['Revision']) {
                $pdo->rollBack();
                jsonResponse(['error' => ['code' => 'CART_ITEM_CHANGED', 'message' => 'รายการในตะกร้าถูกสั่งหรือเปลี่ยนแปลงแล้ว กรุณาโหลดตะกร้าใหม่']], 409);
            }
        }
    }

    // Confirm owned draft rows or insert quick orders, then cut stock.
    foreach ($data['items'] as $item) {
        $draft = $fromCart ? $drafts[(string)$item['key']] : null;
        $itemId = trim((string)($draft['Trade_ID'] ?? $item['id'] ?? ''));
        $amountno = max(1, (int)($draft['Qty'] ?? $item['qty'] ?? 1));
        $note = $draft ? Database::decodeThai($draft['Note']) : trim((string)($item['note'] ?? ''));

        // Query product details from Trade
        $stmtP = $pdo->prepare("SELECT Branch_ID, Trade_ID, 
                                       CONVERT(varbinary(max), Trade_Name) as Trade_Name, 
                                       CONVERT(varbinary(max), Type_Name) as Type_Name, 
                                       CONVERT(varbinary(max), Unit_Name) as Unit_Name, 
                                       CONVERT(varbinary(max), Group_Pro) as Group_Pro, 
                                       Cost_Price, Sale_Price, Food_Type, Qty, Qty_Cut_Stock, Trade_Point 
                                FROM Trade WITH (UPDLOCK, ROWLOCK)
                                WHERE Trade_ID = ? AND Branch_ID = ?");
        $stmtP->execute([$itemId, $branchId]);
        $product = $stmtP->fetch(PDO::FETCH_ASSOC);
        $stmtP->closeCursor();
        if ($fromCart && !$product) {
            $pdo->rollBack();
            jsonResponse(['error' => ['code' => 'ITEM_NOT_FOUND', 'message' => 'ไม่พบสินค้าในระบบ POS กรุณาตรวจสอบตะกร้า']], 409);
        }

        $tName = $product ? Database::decodeThai($product['Trade_Name']) : ($item['name'] ?? 'อาหาร');
        $cName = $product ? Database::decodeThai($product['Type_Name']) : 'อาหารจานหลัก';
        $uName = $product ? Database::decodeThai($product['Unit_Name']) : 'จาน';
        $tradeGroupPro = $product ? (Database::decodeThai($product['Group_Pro']) ?: '') : '';
        $foodType = $product ? ($product['Food_Type'] ?? 'FOOD') : 'FOOD';
        $costPrice = (float)($draft['Cost_Price'] ?? $product['Cost_Price'] ?? 0);
        $salePrice = (float)($draft['Sale_Price'] ?? $product['Sale_Price'] ?? $item['price'] ?? 0);
        $tradePoint = (int)($product['Trade_Point'] ?? 0);
        $currentTradeQty = (float)($product['Qty'] ?? 0);
        $cutPerUnit = (float)($product['Qty_Cut_Stock'] ?? 1);
        if ($cutPerUnit <= 0) $cutPerUnit = 1;
        $qtyCutStock = $draft ? (float)$draft['Qty_Cut_Stock'] : $amountno * $cutPerUnit;

        $displayName = $note ? "$tName ($note)" : $tName;

        $tradeHex = Database::hexThai($displayName);
        $typeHex = Database::hexThai($cName);
        $unitHex = Database::hexThai($uName);
        $groupProHex = Database::hexThai($tradeGroupPro ?: ($note ? "($note)" : ''));

        // 1. Insert into Fnt_Detail (Food_Que_STS = 'S')
        if ($draft) {
            $stmt = $pdo->prepare("UPDATE dbo.Fnt_Detail SET Food_Que_STS='S', Print_STS='', Fn_Doc_Date=CONVERT(datetime, CAST(? AS varchar(19)), 120), Fn_Doc_Date_Time=CONVERT(datetime, CAST(? AS varchar(19)), 120) WHERE ID_NO=? AND Branch_ID=? AND Fn_Doc_No=? AND Food_Que_STS=''");
            $stmt->execute([$dateOnly, $now, $draft['Detail_ID'], $branchId, $docNo]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('Cart detail changed during confirmation');
            $stmt->closeCursor();
            $stmt = $pdo->prepare("UPDATE dbo.Web_Cart_Lines SET State='S', Order_Key=?, Updated_At=SYSUTCDATETIME() WHERE Detail_ID=? AND State='D'");
            $stmt->execute([$idempotencyKey, $draft['Detail_ID']]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('Cart mapping changed during confirmation');
            $stmt->closeCursor();
        } else {
        $sqlDetail = "INSERT INTO Fnt_Detail (
            Branch_ID, Fn_Doc_No, Fn_Doc_Date, Fn_Doc_Date_Time, Table_Name, Customer_ID, Trade_ID, Trade_Name,
            Qty, Qty_Cut_Stock,
            Unit_Name, Type_Name, Food_Type, Group_Pro,
            Cost_Price, Sale_Price, Pb_User, Pb_Now, Print_STS, Food_Que_STS, Trade_Point
        ) VALUES (
            '$branchId', '$docNo', '$dateOnly', '$now', CONVERT(nvarchar(30), $tableNameHex), 'CASH', '$itemId', CONVERT(nvarchar(200), $tradeHex),
            $amountno, $qtyCutStock,
            CONVERT(nvarchar(20), $unitHex), CONVERT(nvarchar(50), $typeHex), '$foodType', CONVERT(nvarchar(50), $groupProHex),
            $costPrice, $salePrice, '$pbUser', '$now',
            '', 'S', $tradePoint
        )";
        $pdo->exec($sqlDetail);
        }

        // 2. Cut Stock in Trade
        $sqlCut = "UPDATE Trade SET Qty = Qty - ? OUTPUT inserted.Trade_ID
                   WHERE Branch_ID = ? AND ((Trade_ID = ?) OR (Group_Pro = ? AND Group_Pro <> ''))";
        $stmtCut = $pdo->prepare($sqlCut);
        $stmtCut->execute([$qtyCutStock, $branchId, $itemId, $tradeGroupPro]);
        $affectedTrades = $stmtCut->fetchAll(PDO::FETCH_COLUMN);
        $affected = count($affectedTrades);
        $stmtCut->closeCursor();
        $recordDeduction = $pdo->prepare("INSERT INTO dbo.Web_Stock_Deductions (Branch_ID, Fn_Doc_No, Trade_ID, Qty) VALUES (?, ?, ?, ?)");
        foreach ($affectedTrades as $affectedTrade) {
            $recordDeduction->execute([$branchId, $docNo, $affectedTrade, $qtyCutStock]);
            $recordDeduction->closeCursor();
        }

        // 3. Insert into Movement (Inventory Log)
        if ($affected > 0) {
            $balanceQty = $currentTradeQty - $qtyCutStock;
            $saleHex = Database::hexThai('ขาย-web');
            $sqlMov = "INSERT INTO Movement (
                Branch_ID, Mo_Doc_Date, Mo_Doc_no, Reference, Trade_id, Pay_Qty, Cost_Price, Pb_User, Pb_now, Balance_Qty
            ) VALUES (
                ?, ?, ?, CONVERT(nvarchar(50), $saleHex), ?, ?, ?, ?, ?, ?
            )";
            $stmtMov = $pdo->prepare($sqlMov);
            $stmtMov->execute([
                $branchId,
                $now,
                $docNo,
                $itemId,
                $qtyCutStock,
                $costPrice,
                $pbUser,
                $now,
                $balanceQty
            ]);
            $stmtMov->closeCursor();
        }

        // 4. Cut Raw Materials / Ingredients from Product_Ingredient (ตัดวัตถุดิบ)
        $sqlRaw = "SELECT * FROM Product_Ingredient 
                   WHERE (STS_RAW = '' OR STS_RAW = 'Y' OR STS_RAW IS NULL) 
                     AND Trade_ID = ? 
                   ORDER BY autoid";
        $stmtRaw = $pdo->prepare($sqlRaw);
        $stmtRaw->execute([$itemId]);
        $ingredients = $stmtRaw->fetchAll(PDO::FETCH_ASSOC);
        $stmtRaw->closeCursor();

        if (!empty($ingredients)) {
            $rawRefHex = Database::hexThai('ตัดRAW-web');

            foreach ($ingredients as $dbsubraw) {
                $qtyCutStockRaw = (float)($dbsubraw['Qty_Cut_Stock_Raw'] ?? 1);
                $qtyRaw = $amountno * $qtyCutStockRaw;
                $tradeIdRaw = trim((string)$dbsubraw['Trade_ID_Raw']);

                // 4.1 Update stock of raw material in Trade
                $subquery1 = "UPDATE Trade SET Qty = Qty - ? OUTPUT inserted.Trade_ID
                              WHERE Branch_Id = ? AND Trade_ID = ?";
                $stmtsub1 = $pdo->prepare($subquery1);
                $stmtsub1->execute([$qtyRaw, $branchId, $tradeIdRaw]);
                $affectedRawTrades = $stmtsub1->fetchAll(PDO::FETCH_COLUMN);
                $stmtsub1->closeCursor();
                foreach ($affectedRawTrades as $affectedRawTrade) {
                    $recordDeduction->execute([$branchId, $docNo, $affectedRawTrade, $qtyRaw]);
                    $recordDeduction->closeCursor();
                }

                // 4.2 Query latest balance of raw material from Trade
                $subquery3 = "SELECT Qty, Cost_Price, Sale_Price 
                              FROM Trade 
                              WHERE Branch_Id = ? AND Trade_Id = ?";
                $stmtsub3 = $pdo->prepare($subquery3);
                $stmtsub3->execute([$branchId, $tradeIdRaw]);
                $rawRow = $stmtsub3->fetch(PDO::FETCH_ASSOC);
                $stmtsub3->closeCursor();

                $balanceQtyRaw = $rawRow ? (float)$rawRow['Qty'] : 0.0;
                $ingCost = $rawRow ? (float)($rawRow['Cost_Price'] ?? $costPrice) : $costPrice;
                $ingSale = $rawRow ? (float)($rawRow['Sale_Price'] ?? $salePrice) : $salePrice;

                // 4.3 Insert into Movement (Reference = 'ตัดRAW-web')
                $subquery2 = "INSERT INTO Movement (
                    Branch_Id, Mo_Doc_Date, Mo_Doc_no, Reference, Trade_id, Pay_Qty, Cost_Price, Sale_Price, Balance_Qty, Pb_User, Pb_now
                ) VALUES (
                    ?, ?, ?, CONVERT(nvarchar(50), $rawRefHex), ?, ?, ?, ?, ?, ?, ?
                )";
                $stmtsub2 = $pdo->prepare($subquery2);
                $stmtsub2->execute([
                    $branchId,
                    $now,
                    $docNo,
                    $tradeIdRaw,
                    $qtyRaw,
                    $ingCost,
                    $ingSale,
                    $balanceQtyRaw,
                    $pbUser,
                    $now
                ]);
                $stmtsub2->closeCursor();
            }
        }
    }

    // -------------------------------------------------------------
    // Recalculate totals for Fnt_Header
    // -------------------------------------------------------------
    $stmtSum = $pdo->prepare("SELECT SUM(d.Sale_Price * d.Qty) as total FROM Fnt_Detail d WHERE d.Fn_Doc_No=? AND d.Branch_ID=? AND NULLIF(LTRIM(RTRIM(ISNULL(d.Food_Que_STS,''))), '') IS NOT NULL");
    $stmtSum->execute([$docNo, $branchId]);
    $total = (float)($stmtSum->fetch()['total'] ?? 0);
    $stmtSum->closeCursor();

    $vatRate = 0.07;
    $vatTotal = round($total / (1 + $vatRate), 4);
    $vatBath = round($total - $vatTotal, 4);

    $stmtUpd = $pdo->prepare("UPDATE Fnt_Header SET
        Fn_Food_Total = ?,
        Fn_Total = ?,
        Fn_Amount = ?,
        Fn_Vat_Total = ?,
        Fn_Vat_Bath = ?,
        Fn_Order_No = ?
        WHERE Fn_Doc_No = ? AND Branch_ID = ?");
    $stmtUpd->execute([$total, $total, $total, $vatTotal, $vatBath, $orderNo, $docNo, $branchId]);
    $stmtUpd->closeCursor();

    $pdo->commit();

    $resPayload = [
        'success' => true,
        'docNo' => $docNo,
        'orderNo' => $orderNo,
        'table' => $tableUnpadded ?: '12',
        'total' => $total,
        'message' => 'บันทึกออเดอร์ส่งเข้าห้องครัวและตัดสต็อกสำเร็จ (สถานะ S)',
    ];

    if ($idempotencyKey !== '') {
        $idemDir = __DIR__ . '/../storage/idempotency';
        if (!is_dir($idemDir)) {
            @mkdir($idemDir, 0777, true);
        }
        $idemFile = $idemDir . '/' . hash('sha256', $idempotencyKey) . '.json';
        @file_put_contents($idemFile, json_encode([
            'time' => time(),
            'requestHash' => $requestHash,
            'response' => $resPayload,
        ], JSON_UNESCAPED_UNICODE));
    }

    jsonResponse($resPayload);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error in api/order.php: " . $e->getMessage());
    jsonResponse(['error' => ['code' => 'ORDER_FAILED', 'message' => 'เกิดข้อผิดพลาดในการบันทึกออเดอร์: ' . $e->getMessage()]], 500);
}
