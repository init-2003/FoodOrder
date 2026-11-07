<?php
declare(strict_types=1);

/** Execute endpoint in an isolated temporary copy, without the real .env or database. */
function runApi(string $endpoint, string $method = 'GET', array $query = [], array $post = [], string $branch = 'test', array $server = [], string $csrf = 'valid'): array
{
    $root = dirname(__DIR__, 2);
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ordering-test-' . bin2hex(random_bytes(12));
    mkdir($tmp . '/public', 0700, true);
    foreach (['Database', 'Services', 'Security', 'Views', 'Controllers', 'Middlewares', 'Http'] as $folder) {
        mkdir($tmp . '/app/' . $folder, 0700, true);
        foreach (glob($root . '/app/' . $folder . '/*.php') as $file) {
            if (!copy($file, $tmp . '/app/' . $folder . '/' . basename($file))) throw new RuntimeException('Cannot prepare isolated test');
        }
    }
    if (!copy($root . '/public/index.php', $tmp . '/public/index.php')) throw new RuntimeException('Cannot prepare front controller');
    mkdir($tmp . '/routes', 0700, true);
    foreach (glob($root . '/routes/*.php') as $file) {
        if (!copy($file, $tmp . '/routes/' . basename($file))) throw new RuntimeException('Cannot prepare routes');
    }
    $harness = <<<'PHP'
<?php
$_SERVER['REQUEST_METHOD'] = $argv[1];
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/api/' . $argv[5];
$_GET = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);
$_POST = json_decode($argv[3], true, 512, JSON_THROW_ON_ERROR);
$_SERVER = array_merge($_SERVER, json_decode($argv[6], true, 512, JSON_THROW_ON_ERROR));
session_save_path(__DIR__);
session_start();
$_SESSION['csrf_token'] = str_repeat('a', 64);
if ($argv[7] === 'valid') $_SERVER['HTTP_X_CSRF_TOKEN'] = $_SESSION['csrf_token'];
if ($argv[7] === 'invalid') $_SERVER['HTTP_X_CSRF_TOKEN'] = str_repeat('b', 64);
putenv('DEFAULT_BRANCH_ID=' . $argv[4]);
putenv('DB_HOST='); putenv('DB_DATABASE='); putenv('POS_MODE=live');
register_shutdown_function(static function () { file_put_contents('php://stderr', 'STATUS:' . (http_response_code() ?: 200)); });
require __DIR__ . '/public/index.php';
PHP;
    file_put_contents($tmp . '/run.php', $harness);
    try {
        $process = proc_open([PHP_BINARY, $tmp . '/run.php', $method, json_encode($query, JSON_THROW_ON_ERROR), json_encode($post, JSON_THROW_ON_ERROR), $branch, $endpoint, json_encode($server, JSON_THROW_ON_ERROR), $csrf], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $tmp);
        if (!is_resource($process)) throw new RuntimeException('Cannot run API process');
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0 || !preg_match('/STATUS:(\d+)/', $stderr, $match)) throw new RuntimeException('API process failed: ' . $stderr);
        return ['status' => (int)$match[1], 'json' => json_decode($stdout, true, 512, JSON_THROW_ON_ERROR)];
    } finally {
        foreach (['Database', 'Services', 'Security', 'Views', 'Controllers', 'Middlewares', 'Http'] as $folder) {
            foreach (glob($tmp . '/app/' . $folder . '/*.php') as $file) unlink($file);
            rmdir($tmp . '/app/' . $folder);
        }
        unlink($tmp . '/public/index.php');
        foreach (glob($tmp . '/routes/*.php') as $file) unlink($file);
        rmdir($tmp . '/routes');
        rmdir($tmp . '/public'); rmdir($tmp . '/app'); unlink($tmp . '/run.php');
        foreach (glob($tmp . '/sess_*') as $sessionFile) unlink($sessionFile);
        rmdir($tmp);
    }
}
