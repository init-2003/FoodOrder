<?php
declare(strict_types=1);
require_once __DIR__ . '/WriteServiceResult.php';
require_once __DIR__ . '/../Database/db.php';

function processCart(PDO $pdo, string $action, string $docNo, string $branch, string $session, string $key, string $operation, array $itemInput, string $operationHash): array
{
    $pdo->beginTransaction();
    // Lock the bill first for every write, in the same order as order.php.
    $sqlBill = "SELECT Doc_Sts, Customer_Id, Pb_User, CONVERT(varbinary(max), Table_Name) AS Table_Name FROM Fnt_Header"
        . ($action === 'list' ? '' : ' WITH (UPDLOCK, ROWLOCK)')
        . " WHERE Fn_Doc_No = ? AND Branch_ID = ?";
    $stmt = $pdo->prepare($sqlBill);
    $stmt->execute([$docNo, $branch]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    if (!$bill || (int)$bill['Doc_Sts'] !== 0) {
        $pdo->rollBack();
        return writeServiceResult(['error' => ['code' => 'BILL_NOT_OPEN', 'message' => 'ไม่พบบิลที่เปิดอยู่ หรือบิลถูกปิดแล้ว']], 409);
    }

    if ($action !== 'list') {
        $stmt = $pdo->prepare("SELECT w.Detail_ID, w.State, w.Revision, w.Last_Operation, w.Last_Operation_Hash, d.Food_Que_STS, d.Trade_ID, d.Qty, CONVERT(varbinary(max), w.Note) AS Audit_Note
            FROM dbo.Web_Cart_Lines w WITH (UPDLOCK, ROWLOCK)
            LEFT JOIN dbo.Fnt_Detail d WITH (UPDLOCK, ROWLOCK) ON d.ID_NO=w.Detail_ID
            WHERE w.Branch_ID=? AND w.Fn_Doc_No=? AND w.Session_Token=? AND w.Cart_Key=?");
        $stmt->execute([$branch, $docNo, $session, $key]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $sameOperation = $existing && hash_equals((string)$existing['Last_Operation'], $operation);
        if ($sameOperation && !hash_equals((string)$existing['Last_Operation_Hash'], $operationHash)) {
            $pdo->rollBack();
            return writeServiceResult(['error' => ['code' => 'IDEMPOTENCY_MISMATCH', 'message' => 'ข้อมูลคำขอไม่ตรงกับรายการก่อนหน้า']], 409);
        }
        $alreadyApplied = $sameOperation && (($action === 'upsert' && $existing['State'] === 'D') || ($action === 'delete' && $existing['State'] === 'X'));
        $audit = null;
        if (!$alreadyApplied && ($action !== 'delete' || $existing)) {
            $before = $existing ? ['product_id' => trim((string)$existing['Trade_ID']), 'quantity' => (int)$existing['Qty'], 'note' => Database::decodeThai($existing['Audit_Note'] ?? null)] : null;
            $after = $action === 'upsert' ? ['product_id' => trim((string)($itemInput['id'] ?? '')), 'quantity' => (int)($itemInput['qty'] ?? 0), 'note' => trim((string)($itemInput['note'] ?? ''))] : null;
            $audit = ['change' => $action === 'delete' ? 'delete' : ($existing ? 'update' : 'add'), 'before' => $before, 'after' => $after];
        }
        if ($existing && !$alreadyApplied && ($existing['State'] !== 'D' || $existing['Food_Que_STS'] === null || trim((string)$existing['Food_Que_STS']) !== '')) {
            $pdo->rollBack();
            return writeServiceResult(['error' => ['code' => 'CART_ITEM_CHANGED', 'message' => 'รายการนี้ถูกสั่งหรือเปลี่ยนแปลงแล้ว กรุณาโหลดตะกร้าใหม่']], 409);
        }
        $expectedRevision = filter_var($itemInput['revision'] ?? null, FILTER_VALIDATE_INT);
        if ($existing && !$alreadyApplied && ($expectedRevision === false || $expectedRevision !== (int)$existing['Revision'])) {
            $pdo->rollBack();
            return writeServiceResult(['error' => ['code' => 'CART_ITEM_CHANGED', 'message' => 'รายการในตะกร้ามีการเปลี่ยนแปลง กรุณาโหลดตะกร้าใหม่']], 409);
        }

        if ($action === 'delete') {
            if ($existing && !$alreadyApplied) {
                $stmt = $pdo->prepare("DELETE FROM dbo.Fnt_Detail WHERE ID_NO=? AND Branch_ID=? AND Fn_Doc_No=? AND Food_Que_STS=''");
                $stmt->execute([$existing['Detail_ID'], $branch, $docNo]);
                if ($stmt->rowCount() !== 1) throw new RuntimeException('Cart detail changed during deletion');
                $stmt->closeCursor();
                $stmt = $pdo->prepare("UPDATE dbo.Web_Cart_Lines SET State='X', Revision=Revision+1, Last_Operation=?, Last_Operation_Hash=?, Updated_At=SYSUTCDATETIME() WHERE Detail_ID=? AND State='D'");
                $stmt->execute([$operation, $operationHash, $existing['Detail_ID']]);
                $stmt->closeCursor();
            }
        } elseif (!$alreadyApplied) {
            $item = $itemInput;
            $tradeId = trim((string)($item['id'] ?? ''));
            $qty = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT);
            $note = trim((string)($item['note'] ?? ''));
            if ($tradeId === '' || mb_strlen($tradeId, 'UTF-8') > 35 || $qty === false || $qty < 1 || $qty > 99 || mb_strlen($note, 'UTF-8') > 200) {
                $pdo->rollBack();
                return writeServiceResult(['error' => ['code' => 'INVALID_ITEM', 'message' => 'ข้อมูลรายการอาหารไม่ถูกต้อง']], 400);
            }
            if ($existing && trim((string)$existing['Trade_ID']) !== $tradeId) {
                $pdo->rollBack();
                return writeServiceResult(['error' => ['code' => 'CART_ITEM_CHANGED', 'message' => 'ไม่สามารถเปลี่ยนสินค้าในรายการเดิมได้']], 409);
            }
            $stmt = $pdo->prepare("SELECT Trade_ID, CONVERT(varbinary(max), Trade_Name) AS Trade_Name, CONVERT(varbinary(max), Type_Name) AS Type_Name, CONVERT(varbinary(max), Unit_Name) AS Unit_Name, CONVERT(varbinary(max), Group_Pro) AS Group_Pro, Food_Type, Cost_Price, Sale_Price, Qty_Cut_Stock, Trade_Point FROM Trade WHERE Branch_ID=? AND Trade_ID=?");
            $stmt->execute([$branch, $tradeId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            if (!$product) {
                $pdo->rollBack();
                return writeServiceResult(['error' => ['code' => 'ITEM_NOT_FOUND', 'message' => 'ไม่พบเมนูอาหารนี้ในระบบ POS']], 404);
            }
            $name = Database::decodeThai($product['Trade_Name']);
            $type = Database::decodeThai($product['Type_Name']);
            $unit = Database::decodeThai($product['Unit_Name']);
            $group = Database::decodeThai($product['Group_Pro']);
            $displayName = mb_substr($name . ($note !== '' ? ' (' . $note . ')' : ''), 0, 200, 'UTF-8');
            $groupValue = mb_substr($group !== '' ? $group : ($note !== '' ? '(' . $note . ')' : ''), 0, 50, 'UTF-8');
            $nameHex = Database::hexThai($displayName);
            $typeHex = Database::hexThai($type);
            $unitHex = Database::hexThai($unit);
            $groupHex = Database::hexThai($groupValue);
            $noteHex = Database::hexThai($note);
            $cutPerUnit = (float)($product['Qty_Cut_Stock'] ?? 1);
            if ($cutPerUnit <= 0) $cutPerUnit = 1;
            $qtyCut = $qty * $cutPerUnit;
            $price = (float)$product['Sale_Price'];
            $cost = (float)$product['Cost_Price'];
            $points = (int)$product['Trade_Point'];
            $foodType = (string)$product['Food_Type'];
            $now = date('Y-m-d H:i:s');
            $user = trim((string)($bill['Pb_User'] ?? ''));
            if ($existing) {
                $stmt = $pdo->prepare("UPDATE dbo.Fnt_Detail SET Qty=?, Qty_Cut_Stock=?, Trade_Name=CONVERT(nvarchar(200), $nameHex), Group_Pro=CONVERT(nvarchar(50), $groupHex), Sale_Price=?, Cost_Price=?, Pb_Now=? WHERE ID_NO=? AND Branch_ID=? AND Fn_Doc_No=? AND Food_Que_STS=''");
                $stmt->execute([$qty, $qtyCut, $price, $cost, $now, $existing['Detail_ID'], $branch, $docNo]);
                if ($stmt->rowCount() !== 1) throw new RuntimeException('Cart detail changed during update');
                $stmt->closeCursor();
                $stmt = $pdo->prepare("UPDATE dbo.Web_Cart_Lines SET Note=CONVERT(nvarchar(200), $noteHex), Revision=Revision+1, Last_Operation=?, Last_Operation_Hash=?, Updated_At=SYSUTCDATETIME() WHERE Detail_ID=? AND State='D'");
                $stmt->execute([$operation, $operationHash, $existing['Detail_ID']]);
                $stmt->closeCursor();
            } else {
                $tableHex = Database::hexThai(Database::decodeThai($bill['Table_Name']));
                $stmt = $pdo->prepare("INSERT INTO dbo.Fnt_Detail (Branch_ID, Fn_Doc_No, Fn_Doc_Date, Fn_Doc_Date_Time, Table_Name, Customer_ID, Trade_ID, Trade_Name, Qty, Qty_Cut_Stock, Unit_Name, Type_Name, Food_Type, Group_Pro, Cost_Price, Sale_Price, Pb_User, Pb_Now, Print_STS, Food_Que_STS, Trade_Point) OUTPUT INSERTED.ID_NO VALUES (?, ?, ?, ?, CONVERT(nvarchar(30), $tableHex), ?, ?, CONVERT(nvarchar(200), $nameHex), ?, ?, CONVERT(nvarchar(20), $unitHex), CONVERT(nvarchar(50), $typeHex), ?, CONVERT(nvarchar(50), $groupHex), ?, ?, ?, ?, '', '', ?)");
                $stmt->execute([$branch, $docNo, date('Y-m-d 00:00:00'), $now, $bill['Customer_Id'], $tradeId, $qty, $qtyCut, $foodType, $cost, $price, $user, $now, $points]);
                $inserted = $stmt->fetch(PDO::FETCH_ASSOC);
                $stmt->closeCursor();
                if (!$inserted || !isset($inserted['ID_NO'])) throw new RuntimeException('Could not read inserted detail ID');
                $stmt = $pdo->prepare("INSERT INTO dbo.Web_Cart_Lines (Detail_ID, Branch_ID, Fn_Doc_No, Session_Token, Cart_Key, Note, Last_Operation, Last_Operation_Hash) VALUES (?, ?, ?, ?, ?, CONVERT(nvarchar(200), $noteHex), ?, ?)");
                $stmt->execute([$inserted['ID_NO'], $branch, $docNo, $session, $key, $operation, $operationHash]);
                $stmt->closeCursor();
            }
        }
    }

    $stmt = $pdo->prepare("SELECT w.Cart_Key, w.Revision, d.Trade_ID, d.Qty, d.Sale_Price, CONVERT(varbinary(max), w.Note) AS Note, CONVERT(varbinary(max), d.Trade_Name) AS Detail_Name, CONVERT(varbinary(max), t.Trade_Name) AS Product_Name, CONVERT(varbinary(max), d.Unit_Name) AS Unit_Name FROM dbo.Web_Cart_Lines w JOIN dbo.Fnt_Detail d ON d.ID_NO=w.Detail_ID AND d.Branch_ID=w.Branch_ID AND d.Fn_Doc_No=w.Fn_Doc_No LEFT JOIN dbo.Trade t ON t.Branch_ID=d.Branch_ID AND t.Trade_ID=d.Trade_ID WHERE w.Branch_ID=? AND w.Fn_Doc_No=? AND w.Session_Token=? AND w.State='D' AND d.Food_Que_STS='' ORDER BY d.ID_NO");
    $stmt->execute([$branch, $docNo, $session]);
    $items = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $items[] = ['key' => $row['Cart_Key'], 'revision' => (int)$row['Revision'], 'id' => trim((string)$row['Trade_ID']), 'name' => Database::decodeThai($row['Product_Name']) ?: Database::decodeThai($row['Detail_Name']), 'qty' => (int)$row['Qty'], 'price' => (float)$row['Sale_Price'], 'unit' => Database::decodeThai($row['Unit_Name']), 'note' => Database::decodeThai($row['Note'])];
    }
    $stmt->closeCursor();
    $pdo->commit();
    $result = writeServiceResult(['success' => true, 'items' => $items]);
    if (!empty($audit)) $result['audit'] = $audit;
    return $result;

}
