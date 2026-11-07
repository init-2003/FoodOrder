<?php
declare(strict_types=1);

$uriPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
$hasBillPath = (bool)preg_match('#/staff/tables/([0-9]{6,10})(?:/|$)#', $uriPath);
if (empty($_GET['Fn_Doc_No']) && empty($_GET['doc']) && empty($_GET['bill']) && empty($_GET['table']) && !$hasBillPath) {
    $initialPage = 'tables';
}
require dirname(__DIR__) . '/Controllers/main.php';
