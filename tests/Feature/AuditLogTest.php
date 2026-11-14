<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Services/AuditLog.php';

it('records audit events without copying credentials or session tokens', function () {
    if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('PDO SQLite is required');
    $dir = sys_get_temp_dir() . '/audit-test-' . bin2hex(random_bytes(8));
    $oldSession = $_SESSION ?? null; $oldIp = $_SERVER['REMOTE_ADDR'] ?? null;
    try {
        $_SESSION = ['Em_Id' => 'E01', 'SUser' => 'staff', 'password' => 'secret', 'session_token' => 'token'];
        $_SERVER['REMOTE_ADDR'] = '192.0.2.1';
        expect(auditEvent('order.confirm', 'success', ['branch' => '001', 'document' => '123', 'details' => ['updated_count' => 2]], $dir))->toBeTrue();
        $db = new PDO('sqlite:' . $dir . '/audit.sqlite');
        $row = $db->query('SELECT * FROM audit_events')->fetch(PDO::FETCH_ASSOC);
        expect($row['actor_type'])->toBe('staff')->and($row['employee_id'])->toBe('E01')->and($row['client_ip'])->toBe('192.0.2.1')->and($row['document'])->toBe('123')->and($row['details'])->not->toContain('secret')->not->toContain('token');
        $db = null;
    } finally {
        if ($oldSession === null) unset($_SESSION); else $_SESSION = $oldSession;
        if ($oldIp === null) unset($_SERVER['REMOTE_ADDR']); else $_SERVER['REMOTE_ADDR'] = $oldIp;
        if (is_file($dir . '/audit.sqlite')) unlink($dir . '/audit.sqlite');
        if (is_dir($dir)) rmdir($dir);
    }
});

it('returns failure when the audit location cannot be created', function () {
    $path = tempnam(sys_get_temp_dir(), 'audit-file-');
    set_error_handler(static fn() => true);
    try { expect(auditEvent('test', 'failure', [], $path . '/child'))->toBeFalse(); }
    finally { restore_error_handler(); unlink($path); }
});

it('removes events older than 30 days while retaining recent events', function () {
    if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('PDO SQLite is required');
    $dir = sys_get_temp_dir() . '/audit-retention-' . bin2hex(random_bytes(8));
    try {
        expect(auditEvent('initial', 'success', [], $dir))->toBeTrue();
        $db = new PDO('sqlite:' . $dir . '/audit.sqlite');
        $db->exec("UPDATE audit_events SET occurred_at = '2000-01-01T00:00:00Z'");
        $db->exec("UPDATE audit_maintenance SET last_run = '2000-01-01'");
        $db = null;
        expect(auditEvent('recent', 'success', [], $dir))->toBeTrue();
        $db = new PDO('sqlite:' . $dir . '/audit.sqlite');
        expect($db->query('SELECT action FROM audit_events')->fetchAll(PDO::FETCH_COLUMN))->toBe(['recent']);
        $db = null;
    } finally {
        if (isset($db)) $db = null;
        if (is_file($dir . '/audit.sqlite')) unlink($dir . '/audit.sqlite');
        if (is_dir($dir)) rmdir($dir);
    }
});
