<?php
session_start();

require_once __DIR__ . '/config/database.php';

// التأكد من تسجيل الدخول
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// السماح للمدير فقط بالحذف
if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// التأكد من أن الطلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: personal.php");
    exit;
}

// التأكد من صحة رقم السجل
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header("Location: personal.php");
    exit;
}

try {
    // حذف السجل من قاعدة البيانات
    $stmt = $pdo->prepare(
        "DELETE FROM personnel WHERE id = :id"
    );

    $stmt->execute([
        ':id' => $id
    ]);

    // الرجوع إلى صفحة السجلات
    if ($stmt->rowCount() > 0) {
        header("Location: personal.php?deleted=1");
    } else {
        header("Location: personal.php?error=notfound");
    }

    exit;

} catch (PDOException $e) {

    error_log($e->getMessage());

    header("Location: personal.php?error=delete");
    exit;
}
?>