<?php
declare(strict_types=1);
function writeServiceResult(array $body, int $status = 200): array { return ['body' => $body, 'status' => $status]; }
