<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

require_once __DIR__ . '/../includes/session.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput ?: '{}', true);
if (!is_array($data)) {
    $data = [];
}

$action = trim((string)($data['action'] ?? $_POST['action'] ?? $_GET['action'] ?? ($method === 'GET' ? 'me' : 'login')));

if ($action === 'logout') {
    if ($method !== 'POST') jsonResponse(['error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'Method not allowed']], 405);
    unset(
        $_SESSION['Em_Id'],
        $_SESSION['Em_Fullname'],
        $_SESSION['SUser'],
        $_SESSION['Status'],
        $_SESSION['Table_ID'],
        $_SESSION['Table_Name'],
        $_SESSION['Fn_Doc_No']
    );
    unset($_SESSION['staff_expires']);
    jsonResponse([
        'success' => true,
        'authenticated' => false,
        'staff' => null,
        'message' => 'ออกจากระบบเรียบร้อยแล้ว',
    ]);
}

if ($action === 'me') {
    $emId = trim((string)($_SESSION['Em_Id'] ?? ''));
    if ($emId !== '') {
        jsonResponse([
            'success' => true,
            'authenticated' => true,
            'staff' => [
                'emId' => $emId,
                'name' => trim((string)($_SESSION['Em_Fullname'] ?? $emId)),
                'username' => trim((string)($_SESSION['SUser'] ?? '')),
                'role' => trim((string)($_SESSION['Status'] ?? 'Staff')),
            ],
        ]);
    }
    jsonResponse([
        'success' => true,
        'authenticated' => false,
        'staff' => null,
    ]);
}

// Action: login
if ($method !== 'POST') {
    jsonResponse(['error' => ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'Method not allowed']], 405);
}

$username = trim((string)($data['username'] ?? $data['SUser'] ?? $_POST['username'] ?? $_POST['SUser'] ?? ''));
$password = trim((string)($data['password'] ?? $data['SPass'] ?? $_POST['password'] ?? $_POST['SPass'] ?? ''));
$remember = ($data['remember'] ?? false) === true;

function establishStaffSession(bool $remember): void {
    session_regenerate_id(true);
    $expires = $remember ? time() + 2592000 : 0;
    if ($remember) {
        $_SESSION['staff_expires'] = $expires;
    } else {
        unset($_SESSION['staff_expires']);
    }
    $params = session_get_cookie_params();
    setcookie(session_name(), session_id(), [
        'expires' => $expires,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => true,
        'samesite' => $params['samesite'] ?: 'Lax',
    ]);
}

if ($username === '' || $password === '') {
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'MISSING_CREDENTIALS',
            'message' => 'กรุณากรอกชื่อผู้ใช้งานและรหัสผ่าน',
        ],
    ], 400);
}

if (class_exists(\Symfony\Component\Validator\Validation::class)) {
    $validator = \Symfony\Component\Validator\Validation::createValidator();
    $limits = [new \Symfony\Component\Validator\Constraints\Length(max: 50)];
    if (count($validator->validate($username, $limits)) || count($validator->validate($password, $limits))) {
        jsonResponse(['success' => false, 'error' => ['code' => 'INVALID_CREDENTIALS', 'message' => 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง']], 400);
    }
}


$pdo = Database::getConnection();
if (!$pdo) {
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'DATABASE_UNAVAILABLE',
            'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูล SQL Server ได้',
        ],
    ], 503);
}

if (!class_exists(\Symfony\Component\RateLimiter\RateLimiterFactory::class) || !class_exists(\Symfony\Component\Validator\Validation::class)) {
    appLog('error', 'Authentication dependencies unavailable');
    jsonResponse(['success' => false, 'error' => ['code' => 'AUTH_UNAVAILABLE', 'message' => 'ไม่สามารถเข้าสู่ระบบได้ กรุณาติดต่อผู้ดูแลระบบ']], 503);
}
$loginLimiter = null;
if (class_exists(\Symfony\Component\RateLimiter\RateLimiterFactory::class)) {
    try {
        require_once __DIR__ . '/../includes/login_limiter.php';
        $clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $loginLimiter = createLoginLimiter($clientIp, dirname(__DIR__) . '/storage/rate-limit');
        $limit = $loginLimiter->consume();
        if (!$limit->isAccepted()) {
            header('Retry-After: ' . max(1, $limit->getRetryAfter()->getTimestamp() - time()));
            jsonResponse(['success' => false, 'error' => ['code' => 'TOO_MANY_ATTEMPTS', 'message' => 'พยายามเข้าสู่ระบบบ่อยเกินไป กรุณาลองใหม่ภายหลัง']], 429);
        }
    } catch (Throwable $e) {
        appLog('error', 'Login rate limiter unavailable', ['error' => $e->getMessage()]);
        jsonResponse(['success' => false, 'error' => ['code' => 'AUTH_UNAVAILABLE', 'message' => 'ไม่สามารถเข้าสู่ระบบได้ กรุณาลองใหม่ภายหลัง']], 503);
    }
}

try {
    $userHex = Database::hexThai($username);
    $passHex = Database::hexThai($password);

    $sql = "SELECT TOP 1
                s.Em_Id,
                CONVERT(varbinary(max), s.Em_Fullname) AS Em_Fullname,
                CONVERT(varbinary(max), s.SUser) AS SUser,
                CONVERT(varbinary(max), s.Status) AS Status,
                s.Sts,
                s.Em_Order,
                CONVERT(varbinary(max), e.Em_Pfx) AS Em_Pfx,
                CONVERT(varbinary(max), e.Em_Name) AS Em_Name,
                CONVERT(varbinary(max), e.Em_SName) AS Em_SName,
                CONVERT(varbinary(max), e.Em_NName) AS Em_NName
            FROM System_Access s
            LEFT JOIN Employee e ON e.Em_id = s.Em_Id
            WHERE s.SUser = CONVERT(nvarchar(50), $userHex)
              AND s.SPass = CONVERT(nvarchar(50), $passHex)
              AND ISNULL(s.Sts, '') <> 'D'";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$row) {
        jsonResponse([
            'success' => false,
            'error' => [
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง',
            ],
        ], 401);
    }

    $emId = trim((string)$row['Em_Id']);
    $fullName = trim(Database::decodeThai($row['Em_Fullname'] ?? null));
    $empName = trim(Database::decodeThai($row['Em_Name'] ?? null));
    $empSName = trim(Database::decodeThai($row['Em_SName'] ?? null));
    $empNName = trim(Database::decodeThai($row['Em_NName'] ?? null));

    if ($fullName === '' && $empName !== '') {
        $fullName = trim($empName . ' ' . $empSName);
    }
    if ($fullName === '') {
        $fullName = $emId;
    }
    $displayName = $empNName !== '' ? "{$fullName} ({$empNName})" : $fullName;
    $sUser = trim(Database::decodeThai($row['SUser'] ?? null)) ?: $username;
    $role = trim(Database::decodeThai($row['Status'] ?? null)) ?: 'Staff';

    if ($loginLimiter !== null) $loginLimiter->reset();
    establishStaffSession($remember);
    $_SESSION['Em_Id'] = $emId;
    $_SESSION['Em_Fullname'] = $displayName;
    $_SESSION['SUser'] = $sUser;
    $_SESSION['Status'] = $role;

    jsonResponse([
        'success' => true,
        'authenticated' => true,
        'mode' => 'live',
        'staff' => [
            'emId' => $emId,
            'name' => $displayName,
            'fullName' => $fullName,
            'nickname' => $empNName,
            'username' => $sUser,
            'role' => $role,
        ],
    ]);
} catch (Throwable $e) {
    appLog('error', 'auth request failed', ['error' => $e->getMessage()]);
    jsonResponse([
        'success' => false,
        'error' => [
            'code' => 'AUTH_ERROR',
            'message' => 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ กรุณาลองอีกครั้ง',
        ],
    ], 500);
}

