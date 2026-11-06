<?php

require_once __DIR__ . '/config/database.php';

$username = 'demo_admin';
$password = 'Demo@12345';
$role = 'admin';

$check = $pdo->prepare(
    "SELECT id FROM users WHERE username = ?"
);
$check->execute([$username]);

if ($check->fetch()) {
    exit('الحساب  موجود بالفعل.');
}

$hashedPassword = password_hash(
    $password,
    PASSWORD_DEFAULT
);

$stmt = $pdo->prepare(
    "INSERT INTO users (username, password, role)
     VALUES (?, ?, ?)"
);

$stmt->execute([
    $username,
    $hashedPassword,
    $role
]);

echo 'تم إنشاء الحساب بنجاح.';

?>