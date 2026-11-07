<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/Services/AuthService.php';
require_once dirname(__DIR__, 2) . '/app/Security/staff-session.php';

it('authenticates against SUser and returns no user for invalid credentials', function () {
    $stmt = $this->createMock(PDOStatement::class);
    $stmt->method('fetch')->willReturn(false);
    $pdo = $this->createMock(PDO::class);
    $pdo->expects($this->once())->method('prepare')
        ->with($this->callback(fn ($sql) => str_contains($sql, 's.SUser =') && str_contains($sql, 's.SPass =')))
        ->willReturn($stmt);
    expect(findStaffCredentials($pdo, 'unknown', 'invalid'))->toBeFalse();
});

it('clears staff and table state when logging out while retaining the CSRF token', function () {
    $session = ['Em_Id' => '1', 'SUser' => 'admin', 'Table_ID' => '002', 'Fn_Doc_No' => '68005642', 'staff_expires' => 123, 'csrf_token' => 'token'];
    clearStaffSession($session);
    expect($session)->toBe(['csrf_token' => 'token']);
});
