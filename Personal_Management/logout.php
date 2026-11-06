<?php

session_start();

// مسح جميع بيانات الجلسة
$_SESSION = [];

// حذف Cookie الجلسة من المتصفح
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// إنهاء الجلسة
session_destroy();

// العودة إلى صفحة تسجيل الدخول
header('Location: /Personal_Management/login.php');
exit;