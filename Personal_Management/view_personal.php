<?php
session_start();

require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /Personal_Management/login.php');
    exit;
}
// تحديد صلاحيات المستخدم
$userRole = $_SESSION['role'] ?? '';

$canAdd = in_array($userRole, ['admin', 'editor'], true);
$canEdit = in_array($userRole, ['admin', 'editor'], true);
$canDelete = ($userRole === 'admin');

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function displayValue($value): string
{
    return ($value === null || $value === '') ? 'غير مسجل' : (string)$value;
}

function formatDateArabic($date): string
{
    if (empty($date)) {
        return 'غير مسجل';
    }

    try {
        return (new DateTime($date))->format('Y/m/d');
    } catch (Exception $ex) {
        return 'غير مسجل';
    }
}

function calculateAge($birthDate): ?int
{
    if (empty($birthDate)) {
        return null;
    }

    try {
        $birth = new DateTime($birthDate);
        $today = new DateTime('today');

        if ($birth > $today) {
            return null;
        }

        return $birth->diff($today)->y;
    } catch (Exception $ex) {
        return null;
    }
}

$educationNames = [
    'none' => 'بدون تعليم',
    'intermediate' => 'تعليم متوسط',
    'above_intermediate' => 'فوق متوسط',
    'higher' => 'تعليم عالٍ'
];

$statusNames = [
    'active' => 'نشط',
    'completed' => 'مكتمل'
];

$maritalNames = [
    'single' => 'أعزب',
    'married' => 'متزوج',
    'divorced' => 'مطلق',
    'widowed' => 'أرمل'
];

$record = null;
$error = '';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    $error = 'رقم السجل المطلوب غير صحيح.';
} else {
    try {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM personnel
             WHERE id = ?
             LIMIT 1"
        );

        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            $error = 'السجل المطلوب غير موجود.';
        }
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        $error = 'تعذر تحميل تفاصيل السجل. راجع إعدادات قاعدة البيانات.';
    }
}

$age = $record ? calculateAge($record['birth_date'] ?? null) : null;

$durationMonths = $record['service_duration_months'] ?? null;
$durationText = 'غير مسجل';

if ($durationMonths !== null && $durationMonths !== '') {
    $durationMonths = (int)$durationMonths;
    $years = intdiv($durationMonths, 12);
    $months = $durationMonths % 12;

    $parts = [];

    if ($years > 0) {
        $parts[] = $years . ' سنة';
    }

    if ($months > 0) {
        $parts[] = $months . ' شهر';
    }

    $durationText = $parts ? implode(' و', $parts) : '0 شهر';
}

$education = $record
    ? ($educationNames[$record['education_level'] ?? ''] ?? 'غير مسجل')
    : '';

$status = $record
    ? ($statusNames[$record['status'] ?? ''] ?? 'غير محدد')
    : '';

$statusClass = $record && ($record['status'] ?? '') === 'active'
    ? 'active'
    : 'completed';

$maritalStatus = $record
    ? ($maritalNames[$record['marital_status'] ?? ''] ?? 'غير مسجل')
    : '';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>تفاصيل السجل</title>

    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <style>
        :root {
            --olive: #667849;
            --olive-dark: #39472c;
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
            color: white;
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
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
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

        .banner {
            background: linear-gradient(115deg, #263322, #667849);
            color: white;
            border-right: 4px solid var(--gold);
            border-radius: 17px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .banner h2 {
            font-size: 21px;
            margin-bottom: 7px;
            overflow-wrap: anywhere;
        }

        .banner p {
            color: #e0e7d9;
            font-size: 13px;
            line-height: 1.9;
        }

        .panel {
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 22px;
        }

        .panel h2 {
            font-size: 17px;
            padding-right: 12px;
            border-right: 3px solid var(--gold);
            margin-bottom: 20px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
        }

        .detail-item {
            background: #f8f9f5;
            border: 1px solid #edf0e8;
            border-radius: 10px;
            padding: 14px;
            min-width: 0;
        }

        .detail-item.full {
            grid-column: 1 / -1;
        }

        .detail-item span {
            display: block;
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 7px;
        }

        .detail-item strong {
            display: block;
            font-size: 14px;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }

        .status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
        }

        .status.active {
            background: #e2f1e1;
            color: #426b3e;
        }

        .status.completed {
            background: #f5eddd;
            color: #876a37;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 22px;
        }

        .btn {
            display: inline-block;
            padding: 11px 18px;
            border: 0;
            border-radius: 9px;
            background: var(--olive);
            color: white;
            font-family: inherit;
            font-size: 13px;
            text-align: center;
        }

        .btn:hover {
            background: var(--olive-dark);
        }

        .btn-secondary {
            background: #e8eddf;
            color: var(--olive-dark);
        }

        .error {
            padding: 16px;
            margin-bottom: 20px;
            border-radius: 9px;
            background: #fee2e2;
            color: #991b1b;
        }

        @media (max-width: 1050px) {
            .details-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
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

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }
        }

        @media (max-width: 550px) {
            .details-grid {
                grid-template-columns: 1fr;
            }

            .panel {
                padding: 16px;
            }
        }

        @media print {
            .sidebar,
            .actions {
                display: none !important;
            }

            .main {
                margin: 0;
                padding: 10px;
            }

            body {
                background: white;
            }

            .panel,
            .banner {
                break-inside: avoid;
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
       <?php if ($canAdd): ?>
    <a href="add_personal.php">＋ &nbsp; إضافة سجل جديد</a>
<?php endif; ?>
    </nav>

    <a href="logout.php" class="logout">↪ تسجيل الخروج</a>
</aside>

<main class="main">

    <header class="topbar">
        <div>
            <h1>ملف السجل</h1>
            <p class="subtitle">عرض التفاصيل المسجلة لهذا الملف</p>
        </div>
    </header>

    <?php if ($error !== ''): ?>

        <div class="error"><?= e($error) ?></div>

        <div class="actions">
            <a href="personal.php" class="btn">العودة إلى السجلات</a>
        </div>

    <?php elseif ($record): ?>

        <section class="banner">
            <h2><?= e(displayValue($record['full_name'] ?? null)) ?></h2>
            <p>
                رقم السجل: <?= e(displayValue($record['record_number'] ?? null)) ?>
                &nbsp; | &nbsp;
                المؤهل: <?= e($education) ?>
            </p>
        </section>

       <div class="actions">

    <a href="personal.php" class="btn btn-secondary">
        ← العودة للسجلات
    </a>

    <?php if ($canEdit): ?>
        <a
            href="edit_personal.php?id=<?= (int)$record['id'] ?>"
            class="btn"
        >
            ✎ تعديل البيانات
        </a>
    <?php endif; ?>

    <?php if ($canDelete): ?>
        <form
            action="delete_personal.php"
            method="POST"
            onsubmit="return confirm('هل أنت متأكد من حذف هذا السجل نهائيًا؟');"
        >
            <input
                type="hidden"
                name="id"
                value="<?= (int)$record['id'] ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token'] ?? '') ?>"
            >

            <button type="submit" class="btn" style="background:#b94343">
                🗑 حذف السجل
            </button>
        </form>
    <?php endif; ?>

    <button type="button" class="btn" onclick="window.print()">
        🖨️ طباعة الملف
    </button>

</div>

        <section class="panel">
            <h2>أولًا: البيانات الشخصية</h2>

            <div class="details-grid">

                <div class="detail-item">
                    <span>رقم السجل / الرقم العسكري المسجل</span>
                    <strong><?= e(displayValue($record['record_number'] ?? null)) ?></strong>
                </div>

                <div class="detail-item">
                    <span>الاسم بالكامل</span>
                    <strong><?= e(displayValue($record['full_name'] ?? null)) ?></strong>
                </div>

                <div class="detail-item">
                    <span>الرقم القومي</span>
                    <strong><?= e(displayValue($record['national_id'] ?? null)) ?></strong>
                </div>

                <div class="detail-item">
                    <span>تاريخ الميلاد</span>
                    <strong><?= e(formatDateArabic($record['birth_date'] ?? null)) ?></strong>
                </div>

                <div class="detail-item">
                    <span>السن</span>
                    <strong><?= $age !== null ? e($age . ' سنة') : 'غير مسجل' ?></strong>
                </div>

                <div class="detail-item">
                    <span>رقم الهاتف</span>
                    <strong><?= e(displayValue($record['phone'] ?? null)) ?></strong>
                </div>

                <div class="detail-item">
                    <span>الحالة الاجتماعية</span>
                    <strong><?= e($maritalStatus) ?></strong>
                </div>

                <div class="detail-item">
                    <span>المؤهل الدراسي</span>
                    <strong><?= e($education) ?></strong>
                </div>

                <div class="detail-item">
                    <span>المهنة المدنية</span>
                    <strong><?= e(displayValue($record['civil_job'] ?? null)) ?></strong>
                </div>

                <div class="detail-item full">
                    <span>العنوان بالتفصيل</span>
                    <strong><?= e(displayValue($record['address'] ?? null)) ?></strong>
                </div>

            </div>
        </section>

        <section class="panel">
            <h2>ثانيًا: بيانات الخدمة</h2>

            <div class="details-grid">

                <div class="detail-item">
                    <span>تاريخ بداية الخدمة</span>
                    <strong><?= e(formatDateArabic($record['service_start_date'] ?? null)) ?></strong>
                </div>

                <div class="detail-item">
                    <span>مدة الخدمة المسجلة</span>
                    <strong><?= e($durationText) ?></strong>
                </div>

                <div class="detail-item">
                    <span>تاريخ الانتهاء المتوقع</span>
                    <strong><?= e(formatDateArabic($record['expected_end_date'] ?? null)) ?></strong>
                </div>

                <div class="detail-item">
                    <span>حالة السجل</span>
                    <strong>
                        <span class="status <?= e($statusClass) ?>">
                            <?= e($status) ?>
                        </span>
                    </strong>
                </div>

                <div class="detail-item">
                    <span>تاريخ إنشاء السجل</span>
                    <strong><?= e(formatDateArabic($record['created_at'] ?? null)) ?></strong>
                </div>

                <div class="detail-item">
                    <span>آخر تحديث</span>
                    <strong><?= e(formatDateArabic($record['updated_at'] ?? null)) ?></strong>
                </div>

            </div>
        </section>

        <section class="panel">
            <h2>ثالثًا: الملاحظات الإدارية</h2>

            <div class="details-grid">
                <div class="detail-item full">
                    <span>الملاحظات</span>
                    <strong><?= e(displayValue($record['notes'] ?? null)) ?></strong>
                </div>
            </div>
        </section>

        <div class="actions">
            <a href="personal.php" class="btn btn-secondary">← العودة للسجلات</a>
            <button type="button" class="btn" onclick="window.print()">🖨️ طباعة الملف</button>
        </div>

    <?php endif; ?>

</main>

</body>
</html>