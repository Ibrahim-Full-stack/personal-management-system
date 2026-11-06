<?php
session_start();

require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /Personal_Management/login.php');
    exit;
}

// إنشاء رمز حماية CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// تحديد صلاحيات المستخدم
$userRole = $_SESSION['role'] ?? '';

$canAdd = in_array($userRole, ['admin', 'editor'], true);
$canEdit = in_array($userRole, ['admin', 'editor'], true);
$canDelete = ($userRole === 'admin');

$search = trim($_GET['search'] ?? '');
$error = '';
$records = [];
$success = '';

if (isset($_GET['added']) && $_GET['added'] === '1') {
    $success = 'تمت إضافة السجل بنجاح.';
} elseif (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $success = 'تم تعديل بيانات السجل بنجاح.';
} elseif (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $success = 'تم حذف السجل بنجاح.';
}

try {
    if ($search !== '') {
        $stmt = $pdo->prepare(
            "SELECT id, record_number, full_name,
                    service_start_date, expected_end_date, status
             FROM personnel
             WHERE record_number LIKE ?
                OR full_name LIKE ?
             ORDER BY id DESC"
        );

        $term = '%' . $search . '%';
        $stmt->execute([$term, $term]);
    } else {
        $stmt = $pdo->query(
            "SELECT id, record_number, full_name,
                    service_start_date, expected_end_date, status
             FROM personnel
             ORDER BY id DESC"
        );
    }

    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {
    error_log($ex->getMessage());
    $error = 'تعذر تحميل السجلات. راجع إعدادات قاعدة البيانات.';
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$statusNames = [
    'active' => 'نشط',
    'completed' => 'مكتمل'
];

$statusClasses = [
    'active' => 'active',
    'completed' => 'completed'
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>إدارة السجلات</title>

    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <style>
        :root {
            --olive: #667849;
            --olive-dark: #39472c;
            --olive-light: #e8eddf;
            --gold: #c9a96e;
            --background: #f4f5f0;
            --white: #fff;
            --text: #263126;
            --muted: #81897c;
            --sidebar: #20291f;
            --border: #e7e9e1;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            background: var(--background);
            color: var(--text);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .sidebar {
            position: fixed;
            top: 0;
            right: 0;
            width: 250px;
            height: 100vh;
            background: var(--sidebar);
            color: #fff;
            padding: 25px 18px;
            z-index: 10;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 24px;
            border-bottom: 1px solid #ffffff18;
        }

        .brand-logo {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            background: var(--olive-dark);
            border: 1px solid var(--gold);
            border-radius: 13px;
            font-size: 24px;
        }

        .brand h2 {
            font-size: 16px;
        }

        .brand p {
            color: #b8c2ad;
            font-size: 10px;
        }

        .menu-title {
            color: #8e9b83;
            font-size: 11px;
            margin: 27px 10px 12px;
        }

        .menu a {
            display: block;
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 9px;
            color: #c2cbb9;
            font-size: 13px;
        }

        .menu a:hover,
        .menu a.active {
            background: var(--olive);
            color: #fff;
        }

        .logout {
            display: block;
            margin-top: 35px;
            padding: 12px;
            border-radius: 9px;
            text-align: center;
            background: #a0413020;
            color: #e5b6aa;
            font-size: 13px;
        }

        .main {
            margin-right: 250px;
            padding: 30px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 27px;
        }

        h1 {
            font-size: 25px;
            font-weight: 800;
        }

        .subtitle {
            margin-top: 5px;
            color: var(--muted);
            font-size: 13px;
        }

        .user {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 10px 15px;
            font-size: 13px;
        }

        .banner {
            background: linear-gradient(115deg, #263322, #667849);
            color: #fff;
            border-right: 4px solid var(--gold);
            border-radius: 17px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .banner h2 {
            font-size: 21px;
            margin-bottom: 7px;
        }

        .banner p {
            color: #e0e7d9;
            font-size: 13px;
            line-height: 1.9;
        }

        .panel {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
        }

        .panel-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 22px;
        }

        .panel-heading h2 {
            font-size: 18px;
        }

        .panel-heading p {
            font-size: 12px;
            color: var(--muted);
            margin-top: 4px;
        }

        .success {
            padding: 15px 18px;
            margin-bottom: 20px;
            border: 1px solid #b9dfb6;
            border-right: 4px solid #4f8a49;
            border-radius: 10px;
            background: #eaf7e8;
            color: #2f6b32;
            font-size: 13px;
            font-weight: 700;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            border-radius: 9px;
            padding: 12px;
            margin-bottom: 18px;
            font-size: 13px;
        }

        .tools {
            display: flex;
            gap: 10px;
            margin-bottom: 22px;
        }

        .search {
            display: flex;
            flex: 1;
            gap: 8px;
        }

        input {
            min-width: 0;
            flex: 1;
            padding: 11px 13px;
            border: 1px solid #dce1d5;
            border-radius: 9px;
            font-family: inherit;
            outline: none;
        }

        input:focus {
            border-color: var(--olive);
        }

        .btn {
            display: inline-block;
            padding: 9px 12px;
            border: 0;
            border-radius: 8px;
            background: var(--olive);
            color: #fff;
            font-family: inherit;
            font-size: 12px;
            cursor: pointer;
            white-space: nowrap;
            text-align: center;
            transition: 0.2s;
        }

        .btn:hover {
            filter: brightness(0.9);
        }

        .btn-secondary {
            background: #e8eddf;
            color: var(--olive-dark);
        }

        .btn-edit {
            background: #356a91;
        }

        .btn-danger {
            background: #b94343;
        }

        .btn-details {
            background: var(--olive);
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1050px;
            border-collapse: collapse;
        }

        th {
            background: #f5f6f1;
            color: #697360;
            text-align: right;
            font-size: 12px;
            font-weight: 700;
            padding: 14px 12px;
        }

        td {
            border-bottom: 1px solid #edf0e8;
            padding: 13px 10px;
            font-size: 12px;
        }

        tbody tr:hover {
            background: #fafbf8;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
        }

        .status.active {
            background: #e2f1e1;
            color: #426b3e;
        }

        .status.completed {
            background: #f5eddd;
            color: #876a37;
        }

        .empty {
            padding: 35px 15px;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
        }

        .count {
            display: inline-block;
            padding: 6px 11px;
            background: var(--olive-light);
            color: var(--olive-dark);
            border-radius: 8px;
            font-size: 12px;
        }

        .actions-cell {
            white-space: nowrap;
        }

        .actions-cell form {
            display: inline-block;
            margin: 2px;
        }

        @media (max-width: 850px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main {
                margin-right: 0;
                padding: 20px 14px;
            }

            .topbar,
            .panel-heading {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-width: 500px) {
            .panel {
                padding: 15px;
            }

            .tools {
                flex-direction: column;
            }

            .search {
                width: 100%;
            }

            h1 {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">

    <div class="brand">
        <div class="brand-logo">🛡️</div>

        <div>
            <h2>إدارة السجلات</h2>
            <p>PERSONAL MANAGEMENT</p>
        </div>
    </div>

    <div class="menu-title">القائمة الرئيسية</div>

    <nav class="menu">
        <a href="dashboard.php">▦ &nbsp; لوحة التحكم</a>
        <a href="personal.php" class="active">▤ &nbsp; إدارة السجلات</a>
        <a href="add_personal.php">＋ &nbsp; إضافة سجل جديد</a>
        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
    <a href="reports.php">▧ &nbsp; التقارير والطباعة</a>
<?php endif; ?>
    </nav>

    <a href="logout.php" class="logout">↪ تسجيل الخروج</a>

</aside>

<main class="main">

    <header class="topbar">

        <div>
            <h1>إدارة السجلات</h1>
            <p class="subtitle">عرض السجلات والبحث فيها وتعديل بياناتها.</p>
        </div>

        <div class="user">
            👤 <?= e($_SESSION['username'] ?? 'المستخدم') ?>
        </div>

    </header>

    <section class="banner">
        <h2>مركز إدارة السجلات</h2>
        <p>
            ابحث عن السجل المطلوب، واعرض تفاصيله أو عدّل بياناته أو احذفه.
        </p>
    </section>

    <section class="panel">

        <?php if ($success !== ''): ?>
            <div class="success" role="status">
                ✓ <?= e($success) ?>
            </div>
        <?php endif; ?>

        <div class="panel-heading">

            <div>
                <h2>قائمة السجلات</h2>
                <p>يمكنك إدارة كل سجل من خلال أزرار الإجراءات.</p>
            </div>

            <span class="count">
                عدد النتائج: <?= count($records) ?>
            </span>

        </div>

        <?php if ($error !== ''): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="tools">

            <form class="search" method="GET" action="personal.php">

                <input
                    type="search"
                    name="search"
                    placeholder="ابحث برقم السجل أو الاسم..."
                    value="<?= e($search) ?>"
                >

                <button type="submit" class="btn">بحث</button>

                <?php if ($search !== ''): ?>
                    <a href="personal.php" class="btn btn-secondary">
                        إلغاء البحث
                    </a>
                <?php endif; ?>

            </form>

            <a href="add_personal.php" class="btn">
                + إضافة سجل
            </a>

        </div>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>رقم السجل</th>
                        <th>الاسم</th>
                        <th>تاريخ البداية</th>
                        <th>التاريخ المتوقع للنهاية</th>
                        <th>الحالة</th>
                        <th>التفاصيل</th>
                        <th>التعديل</th>
                        <th>الحذف</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($records) > 0): ?>

                    <?php foreach ($records as $record): ?>

                        <?php
                        $status = $record['status'] ?? '';
                        $statusName = $statusNames[$status] ?? 'غير محدد';
                        $statusClass = $statusClasses[$status] ?? '';
                        $recordId = (int)$record['id'];
                        ?>

                        <tr>

                            <td><?= e($record['record_number']) ?></td>

                            <td><?= e($record['full_name']) ?></td>

                            <td><?= e($record['service_start_date']) ?></td>

                            <td><?= e($record['expected_end_date']) ?></td>

                            <td>
                                <span class="status <?= e($statusClass) ?>">
                                    <?= e($statusName) ?>
                                </span>
                            </td>

                            <td>
                                <a
                                    class="btn btn-details"
                                    href="view_personal.php?id=<?= $recordId ?>"
                                >
                                    عرض التفاصيل
                                </a>
                            </td>

                            <td>
                                <a
                                    class="btn btn-edit"
                                    href="edit_personal.php?id=<?= $recordId ?>"
                                >
                                    ✎ تعديل
                                </a>
                            </td>

                            <td class="actions-cell">

                                <form
                                    action="delete_personal.php"
                                    method="POST"
                                    onsubmit="return confirm('هل أنت متأكد من حذف هذا السجل نهائيًا؟ لا يمكن التراجع عن الحذف.');"
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= $recordId ?>"
                                    >

                                    <button type="submit" class="btn btn-danger">
                                        🗑 حذف
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="empty">
                            لا توجد سجلات مطابقة للبحث.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

</body>
</html>