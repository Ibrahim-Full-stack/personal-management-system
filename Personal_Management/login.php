<?php
session_start();

require_once __DIR__ . '/config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $error = 'من فضلك أدخل اسم المستخدم وكلمة المرور.';

    } else {

        $stmt = $pdo->prepare(
            "SELECT id, username, password, role
             FROM users
             WHERE username = ?
             LIMIT 1"
        );

        $stmt->execute([$username]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $user &&
            $user['role'] === 'admin' &&
            password_verify($password, $user['password'])
        ) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = 'admin';

            header('Location: dashboard.php');
            exit;

        } else {

            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';

        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>تسجيل الدخول | إدارة السجلات</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Tahoma, Arial, sans-serif;
            background: linear-gradient(135deg, #0f172a, #1e3a5f);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            background: #fff;
            width: 100%;
            max-width: 420px;
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25);
        }

        .icon {
            width: 70px;
            height: 70px;
            background: #e8f1ff;
            color: #1d4ed8;
            border-radius: 18px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 30px;
            margin: 0 auto 20px;
        }

        h1 {
            text-align: center;
            color: #0f172a;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 30px;
            font-size: 14px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 15px;
            outline: none;
        }

        input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        button {
            width: 100%;
            padding: 14px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 9px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .footer {
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
            margin-top: 25px;
        }

        .error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 12px;
            border-radius: 9px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
    </style>
</head>

<body>

    <div class="login-card">

        <div class="icon">🔐</div>

        <h1>تسجيل الدخول</h1>

        <p class="subtitle">
            نظام إدارة السجلات
        </p>

        <?php if ($error !== ''): ?>
            <div class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">

            <label for="username">اسم المستخدم</label>

            <input
                type="text"
                id="username"
                name="username"
                placeholder="أدخل اسم المستخدم"
                autocomplete="username"
                required
            >

            <label for="password">كلمة المرور</label>

            <input
                type="password"
                id="password"
                name="password"
                placeholder="أدخل كلمة المرور"
                autocomplete="current-password"
                required
            >

            <button type="submit">تسجيل الدخول</button>

        </form>

        <div class="footer">
            نظام إدارة السجلات باستخدام PHP وMySQL
        </div>

    </div>

</body>
</html>