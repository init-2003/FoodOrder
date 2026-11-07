<?php
declare(strict_types=1);
$appBase = $definition['base'];
require dirname(__DIR__) . '/Middlewares/staff-access.php';
require dirname(__DIR__) . '/Views/' . $definition['view'];
