<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Authentication & Authorization
| Personnel Management System
|--------------------------------------------------------------------------
*/

// التأكد من تسجيل الدخول
function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// التأكد من صلاحية المستخدم
function requireRole(array $allowedRoles): void
{
    requireLogin();

    $userRole = $_SESSION['role'] ?? '';

    if (!in_array($userRole, $allowedRoles, true)) {
        http_response_code(403);

        exit(
            '<h2 style="text-align:center; margin-top:100px;">
                عذرًا، ليس لديك صلاحية للوصول إلى هذه الصفحة.
            </h2>'
        );
    }
}