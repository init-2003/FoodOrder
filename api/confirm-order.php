<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'Method not allowed']], 405);
}
if (empty($_SESSION['Em_Id'])) {
    jsonResponse(['error' => ['code' => 'UNAUTHORIZED', 'message' => 'กรุณาเข้าสู่ระบบพนักงาน']], 401);
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput ?: '{}', true);

$docNo = trim((string)($data['docNo'] ?? $data['Fn_Doc_No'] ?? $data['doc'] ?? ''));
$tradeId = trim((string)($data['tradeId'] ?? $data['Trade_ID'] ?? ''));
$branchId = trim((string)($data['branch'] ?? $data['Branch_ID'] ?? (getenv('DEFAULT_BRANCH_ID') ?: '')));
if ($branchId === '') { jsonResponse(['error' => ['code' => 'MISSING_BRANCH', 'message' => 'กรุณาระบุรหัสสาขาใน URL หรือ DEFAULT_BRANCH_ID']], 400); }

if ($docNo === '') {
    jsonResponse(['error' => ['code' => 'INVALID_PAYLOAD', 'message' => 'กรุณาระบุเลขที่บิล (Fn_Doc_No)']], 400);
}


$pdo = Database::getConnection();
if (!$pdo) {
    jsonResponse(['error' => ['code' => 'DATABASE_UNAVAILABLE', 'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้']], 503);
}

try {
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
        jsonResponse(['error' => ['code' => 'BILL_NOT_FOUND', 'message' => "ไม่พบบิลเลขที่ $docNo ในระบบ"]], 404);
    }

    $docSts = (int)$bill['Doc_Sts'];
    if ($docSts === 1) {
        $pdo->rollBack();
        jsonResponse(['error' => ['code' => 'BILL_CLOSED', 'message' => 'บิลนี้ออกใบเสร็จแล้ว (Doc_Sts = 1) ไม่สามารถยืนยันอาหารได้']], 400);
    } elseif ($docSts === 3) {
        $pdo->rollBack();
        jsonResponse(['error' => ['code' => 'BILL_CANCELLED', 'message' => 'บิลนี้ถูกยกเลิกแล้ว (Doc_Sts = 3) ไม่สามารถยืนยันอาหารได้']], 400);
    } elseif ($docSts !== 0) {
        $pdo->rollBack();
        jsonResponse(['error' => ['code' => 'BILL_NOT_OPEN', 'message' => "บิลไม่อยู่ในสถานะเปิดบิล (Doc_Sts = $docSts)"]], 400);
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

    jsonResponse([
        'success' => true,
        'docNo' => $docNo,
        'tradeId' => $tradeId ?: null,
        'updatedCount' => $affected,
        'message' => $affected > 0 ? 'ลูกค้ายืนยันรายการอาหารเรียบร้อยแล้ว' : 'ไม่พบรายการที่รอการยืนยัน',
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    appLog('error', 'confirm-order request failed', ['error' => $e->getMessage()]);
    jsonResponse(['error' => ['code' => 'CONFIRM_FAILED', 'message' => 'เกิดข้อผิดพลาดในการยืนยันรายการ กรุณาลองอีกครั้ง']], 500);
}

