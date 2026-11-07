<?php
declare(strict_types=1);

function clearStaffSession(array &$session): void {
    unset($session['Em_Id'], $session['Em_Fullname'], $session['SUser'], $session['Status'],
        $session['Table_ID'], $session['Table_Name'], $session['Fn_Doc_No'], $session['staff_expires']);
}

function establishStaffSession(bool $remember): void {
    session_regenerate_id(true);
    $expires = $remember ? time() + 2592000 : 0;
    if ($remember) $_SESSION['staff_expires'] = $expires;
    else unset($_SESSION['staff_expires']);
    $params = session_get_cookie_params();
    setcookie(session_name(), session_id(), [
        'expires' => $expires,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => true,
        'samesite' => $params['samesite'] ?: 'Lax',
    ]);
}
