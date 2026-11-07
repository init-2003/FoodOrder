<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/Security/session.php';
if (empty($_SESSION['Em_Id'])) {
    header('Location: ' . $publicBase . '#login');
    exit;
}
