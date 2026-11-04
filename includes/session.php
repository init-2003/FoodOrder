<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    // Keep remembered sessions available for the lifetime of their cookie.
    ini_set('session.gc_maxlifetime', '2592000');
    @session_start();
}

if (isset($_SESSION['staff_expires']) && (int) $_SESSION['staff_expires'] <= time()) {
    unset($_SESSION['Em_Id'], $_SESSION['Em_Fullname'], $_SESSION['SUser'], $_SESSION['Status'], $_SESSION['staff_expires']);
}
