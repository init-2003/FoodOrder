<?php
declare(strict_types=1);

function confirmOrderResult(array $body, int $status = 200): array { return ['body' => $body, 'status' => $status]; }

function confirmPendingOrder(PDO $pdo, string $docNo, string $tradeId, string $branchId): array
{
    $pdo->beginTransaction();

    // -------------------------------------------------------------
    // ตรวจสอบสถานะบิล: Doc_Sts = 0 (เปิดบิล), 1 (ออกใบเสร็จแล้ว), 3 (ยกเลิก)
    // -------------------------------------------------------------
    $stmtCheck = $pdo->prepare("SELECT Doc_Sts FROM Fnt_Header WHERE Fn_Doc_No = ? AND Branch_ID = ?");
    $stmtCheck->execute([$docNo, $branchId]);
    $bill = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    $stmtCheck->closeCursor();

    if (!$bill) {
        $pdo->rollBack();
        return confirmOrderResult(['error' => ['code' => 'BILL_NOT_FOUND', 'message' => "ไม่พบบิลเลขที่ $docNo ในระบบ"]], 404);
    }

    $docSts = (int)$bill['Doc_Sts'];
    if ($docSts === 1) {
        $pdo->rollBack();
        return confirmOrderResult(['error' => ['code' => 'BILL_CLOSED', 'message' => 'บิลนี้ออกใบเสร็จแล้ว (Doc_Sts = 1) ไม่สามารถยืนยันอาหารได้']], 400);
    } elseif ($docSts === 3) {
        $pdo->rollBack();
        return confirmOrderResult(['error' => ['code' => 'BILL_CANCELLED', 'message' => 'บิลนี้ถูกยกเลิกแล้ว (Doc_Sts = 3) ไม่สามารถยืนยันอาหารได้']], 400);
    } elseif ($docSts !== 0) {
        $pdo->rollBack();
        return confirmOrderResult(['error' => ['code' => 'BILL_NOT_OPEN', 'message' => "บิลไม่อยู่ในสถานะเปิดบิล (Doc_Sts = $docSts)"]], 400);
    }

    // -------------------------------------------------------------
    // เวลายืนยัน: Update fnt_detail Set Food_Que_STS = 'S', Print_STS = ''
    // -------------------------------------------------------------
    if ($tradeId !== '') {
        $sql = "UPDATE Fnt_Detail 
                SET Food_Que_STS = 'S', Print_STS = '' 
                WHERE Trade_ID = ? 
                  AND Food_Que_STS = '' 
                  AND Fn_Doc_No = ? 
                  AND Branch_ID = ?
                  AND NOT EXISTS (SELECT 1 FROM dbo.Web_Cart_Lines w WHERE w.Detail_ID=Fnt_Detail.ID_NO AND w.State='D')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tradeId, $docNo, $branchId]);
    } else {
        // Confirm all pending items for this bill
        $sql = "UPDATE Fnt_Detail 
                SET Food_Que_STS = 'S', Print_STS = '' 
                WHERE Food_Que_STS = '' 
                  AND Fn_Doc_No = ? 
                  AND Branch_ID = ?
                  AND NOT EXISTS (SELECT 1 FROM dbo.Web_Cart_Lines w WHERE w.Detail_ID=Fnt_Detail.ID_NO AND w.State='D')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$docNo, $branchId]);
    }

    $affected = $stmt->rowCount();
    $stmt->closeCursor();

    $pdo->commit();

    return confirmOrderResult([
        'success' => true,
        'docNo' => $docNo,
        'tradeId' => $tradeId ?: null,
        'updatedCount' => $affected,
        'message' => $affected > 0 ? 'ลูกค้ายืนยันรายการอาหารเรียบร้อยแล้ว' : 'ไม่พบรายการที่รอการยืนยัน',
    ]);

}
