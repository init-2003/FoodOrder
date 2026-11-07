<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/Services/ProductImageService.php';
$file = (string) ($_GET['file'] ?? '');
$path = findProductImage($file, dirname(__DIR__, 2) . '/storage/uploads/products');
if ($path === null) { http_response_code(404); exit; }
header('Content-Type: image/jpeg');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($path));
readfile($path);
