<?php

$host = 'localhost';
$dbname = 'personal management';
$username = 'YOUR_DATABASE_USERNAME';
$password = 'YOUR_DATABASE_PASSWORD';

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {

    error_log($e->getMessage());

    die('حدث خطأ أثناء الاتصال بقاعدة البيانات.');

}
