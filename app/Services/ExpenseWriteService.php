<?php
declare(strict_types=1);
require_once __DIR__ . '/../Database/db.php';

function loadExpenseOptions(PDO $db): array {
    $types = $db->query("SELECT Expense_Type_Code, CONVERT(varbinary(max), Expense_Type_Name) AS name FROM Expense_Type WHERE Active_Status = 1 ORDER BY Expense_Type_Code")->fetchAll(PDO::FETCH_ASSOC);
    $staff = $db->query("SELECT Em_id, CONVERT(varbinary(max), CONCAT(ISNULL(Em_Name, ''), ' ', ISNULL(Em_SName, ''))) AS name FROM Employee WHERE ISNULL(Sts, '') <> 'D' ORDER BY Em_id")->fetchAll(PDO::FETCH_ASSOC);
    return ['types' => array_map(static fn($r) => ['id' => trim((string)$r['Expense_Type_Code']), 'name' => trim(Database::decodeThai($r['name']))], $types), 'staff' => array_map(static fn($r) => ['id' => trim((string)$r['Em_id']), 'name' => trim(Database::decodeThai($r['name']))], $staff)];
}

function validateExpense(array $input): array {
    $date = trim((string)($input['date'] ?? ''));
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('กรุณาระบุวันที่ให้ถูกต้อง');
    $detail = trim((string)($input['detail'] ?? ''));
    if ($detail === '' || mb_strlen($detail, 'UTF-8') > 1000) throw new InvalidArgumentException('กรุณาระบุรายละเอียดไม่เกิน 1,000 ตัวอักษร');
    $amount = trim((string)($input['amount'] ?? ''));
    if (!preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,2})?$/D', $amount) || (float)$amount <= 0) throw new InvalidArgumentException('กรุณาระบุจำนวนเงินที่มากกว่า 0 และทศนิยมไม่เกิน 2 ตำแหน่ง');
    $type = trim((string)($input['type'] ?? ''));
    $payer = trim((string)($input['payer'] ?? ''));
    if ($type === '' || strlen($type) > 10 || $payer === '' || strlen($payer) > 10) throw new InvalidArgumentException('กรุณาเลือกประเภทค่าใช้จ่ายและผู้จ่าย');
    return compact('date', 'detail', 'amount', 'type', 'payer');
}

function saveExpense(PDO $db, string $branch, string $user, array $data): string {
    if ($branch === '' || strlen($branch) > 3 || $user === '' || mb_strlen($user, 'UTF-8') > 15) throw new InvalidArgumentException('ข้อมูลสาขาหรือผู้บันทึกไม่ถูกต้อง');
    $db->beginTransaction();
    try {
        $type = $db->prepare('SELECT CONVERT(varbinary(max), Expense_Type_Name) AS name FROM Expense_Type WHERE Expense_Type_Code = ? AND Active_Status = 1');
        $type->execute([$data['type']]); $types = $type->fetchAll(PDO::FETCH_ASSOC); $type->closeCursor();
        $payer = $db->prepare("SELECT CONVERT(varbinary(max), CONCAT(ISNULL(Em_Name, ''), ' ', ISNULL(Em_SName, ''))) AS name FROM Employee WHERE Em_id = ? AND ISNULL(Sts, '') <> 'D'");
        $payer->execute([$data['payer']]); $payers = $payer->fetchAll(PDO::FETCH_ASSOC); $payer->closeCursor();
        if (count($types) !== 1 || count($payers) !== 1) throw new InvalidArgumentException('ไม่พบประเภทค่าใช้จ่ายหรือผู้จ่ายที่เลือก');
        $typeName = trim(Database::decodeThai($types[0]['name']));
        $payerName = trim(Database::decodeThai($payers[0]['name']));
        $ctrl = $db->prepare("SELECT PB_RUN_YR, PB_RUN_NO FROM PBM_CTRL WITH (UPDLOCK, HOLDLOCK) WHERE Branch_ID = ? AND PB_RUN_CD = 'EXP'");
        $ctrl->execute([$branch]); $rows = $ctrl->fetchAll(PDO::FETCH_ASSOC); $ctrl->closeCursor();
        if (count($rows) !== 1) throw new RuntimeException('ไม่พบหรือพบข้อมูลรันเลข EXP ซ้ำสำหรับสาขานี้');
        $year = trim((string)$rows[0]['PB_RUN_YR']); $number = $rows[0]['PB_RUN_NO'];
        if ($year === '' || !ctype_digit((string)$number) || (int)$number < 0 || (int)$number > 999999) throw new RuntimeException('ข้อมูลรันเลข EXP ไม่ถูกต้อง');
        $doc = $year . str_pad((string)$number, 6, '0', STR_PAD_LEFT);
        $update = $db->prepare("UPDATE PBM_CTRL SET PB_RUN_NO = PB_RUN_NO + 1 WHERE Branch_ID = ? AND PB_RUN_CD = 'EXP'");
        $update->execute([$branch]);
        if ($update->rowCount() !== 1) throw new RuntimeException('ไม่สามารถปรับเลขเอกสารได้');
        // Legacy varchar/text columns use the database Thai code page, not UTF-8.
        $detailHex = Database::hexThai($data['detail']); $payerHex = Database::hexThai($payerName); $typeHex = Database::hexThai($typeName); $userHex = Database::hexThai($user);
        $sql = "INSERT INTO Expending (Branch_Id, Ex_Doc_No, Ex_Doc_Date, Ex_Detail, Ex_Price, Expense_Type_Code, Expense_Type_Name, Ex_Doc_No_FileName, Emp_Pay_ID, Emp_Pay_Name, Pb_User, Pb_Now) VALUES (?, ?, CONVERT(datetime, ?, 23), CONVERT(varchar(max), CONVERT(nvarchar(max), $detailHex)), ?, ?, CONVERT(nvarchar(max), $typeHex), ?, ?, CONVERT(varchar(200), CONVERT(nvarchar(max), $payerHex)), CONVERT(varchar(15), CONVERT(nvarchar(max), $userHex)), CONVERT(datetime, ?, 120))";
        $insert = $db->prepare($sql);
        $insert->execute([$branch, $doc, $data['date'], $data['amount'], $data['type'], $doc, $data['payer'], (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('Y-m-d H:i:s')]);
        $db->commit();
        return $doc;
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
}
