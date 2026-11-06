<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
    // Keep remembered sessions available for the lifetime of their cookie.
    ini_set('session.gc_maxlifetime', '2592000');
    @session_start();
}

if (isset($_SESSION['staff_expires']) && (int) $_SESSION['staff_expires'] <= time()) {
    unset($_SESSION['Em_Id'], $_SESSION['Em_Fullname'], $_SESSION['SUser'], $_SESSION['Status'], $_SESSION['staff_expires']);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
