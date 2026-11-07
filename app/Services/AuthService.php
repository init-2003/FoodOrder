<?php
declare(strict_types=1);
require_once __DIR__ . '/../Database/db.php';

function findStaffCredentials(PDO $pdo, string $username, string $password): array|false
{
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

    return $row;
}
