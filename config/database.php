<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

return [
    'driver' => 'sqlsrv',
    'host' => getenv('DB_HOST') ?: '',
    'database' => getenv('DB_DATABASE') ?: '',
    'username' => getenv('DB_USERNAME') ?: '',
    'password' => getenv('DB_PASSWORD') ?: '',
];
