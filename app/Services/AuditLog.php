<?php
declare(strict_types=1);
require_once __DIR__ . '/log.php';

/** Best-effort audit trail; callers must never pass credentials, tokens or raw requests. */
function auditEvent(string $action, string $outcome, array $fields = [], ?string $directory = null): bool
{
    try {
        $directory ??= dirname(__DIR__, 2) . '/storage/audit';
        if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('PDO SQLite extension unavailable');
        if (is_link($directory) || (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory))) {
            throw new RuntimeException('Audit directory unavailable');
        }
        $path = $directory . '/audit.sqlite';
        if (is_link($path)) throw new RuntimeException('Audit database cannot be a symlink');
        $db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]);
        $db->exec('PRAGMA busy_timeout=2000');
        $db->exec('CREATE TABLE IF NOT EXISTS audit_events (id INTEGER PRIMARY KEY AUTOINCREMENT, occurred_at TEXT NOT NULL, action TEXT NOT NULL, outcome TEXT NOT NULL, actor_type TEXT NOT NULL, employee_id TEXT, username TEXT, client_ip TEXT, branch TEXT, document TEXT, table_id TEXT, subject TEXT, details TEXT)');
        $db->exec('CREATE INDEX IF NOT EXISTS audit_events_time ON audit_events (occurred_at)');
        $session = $_SESSION ?? [];
        $employee = trim((string)(array_key_exists('employee_id', $fields) ? $fields['employee_id'] : ((($fields['actor_type'] ?? '') === 'anonymous') ? '' : ($session['Em_Id'] ?? ''))));
        $details = $fields['details'] ?? [];
        $stmt = $db->prepare('INSERT INTO audit_events (occurred_at, action, outcome, actor_type, employee_id, username, client_ip, branch, document, table_id, subject, details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([gmdate('Y-m-d\TH:i:s\Z'), $action, $outcome, $fields['actor_type'] ?? ($employee !== '' ? 'staff' : 'customer'), $employee ?: null,
            $fields['username'] ?? ($employee !== '' ? ($session['SUser'] ?? null) : null),
            filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: null,
            $fields['branch'] ?? null, $fields['document'] ?? null, $fields['table'] ?? null, $fields['subject'] ?? null,
            json_encode($details, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)]);
        auditPruneExpired($db);
        return true;
    } catch (Throwable $e) {
        appLog('error', 'Audit log write failed', ['error' => $e->getMessage()]);
        return false;
    }
}

/** Delete events older than 30 days at most once per UTC day, when an event is written. */
function auditPruneExpired(PDO $db): void
{
    try {
        $db->exec('CREATE TABLE IF NOT EXISTS audit_maintenance (name TEXT PRIMARY KEY, last_run TEXT NOT NULL)');
        $today = gmdate('Y-m-d');
        $lastRun = $db->query("SELECT last_run FROM audit_maintenance WHERE name = 'retention'")->fetchColumn();
        if ($lastRun !== false && $lastRun >= $today) return;
        $db->beginTransaction();
        $claim = $db->prepare("INSERT INTO audit_maintenance (name, last_run) VALUES ('retention', ?) ON CONFLICT(name) DO UPDATE SET last_run = excluded.last_run WHERE last_run < excluded.last_run");
        $claim->execute([$today]);
        if ($claim->rowCount() > 0) {
            $delete = $db->prepare('DELETE FROM audit_events WHERE occurred_at < ?');
            $delete->execute([gmdate('Y-m-d\TH:i:s\Z', time() - 30 * 86400)]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        appLog('error', 'Audit log retention cleanup failed', ['error' => $e->getMessage()]);
    }
}
