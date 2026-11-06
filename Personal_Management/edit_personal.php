<?php
session_start();

require_once __DIR__ . '/config/database.php';

// التأكد من تسجيل الدخول
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// السماح للمدير والمحرر بالتعديل فقط
if (!in_array($_SESSION['role'] ?? '', ['admin', 'editor'], true)) {
    header('Location: dashboard.php');
    exit;
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function validDate(string $date): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $date);

    return $d !== false
        && $d->format('Y-m-d') === $date;
}

function calculateEndDate(string $startDate, int $months): string
{
    $start = new DateTime($startDate);

    // التعامل الصحيح مع نهاية الشهر عند إضافة مدة الخدمة.
    $day = (int)$start->format('d');
    $start->modify('first day of this month');

    $start->modify('+' . $months . ' months');

    $lastDay = (int)$start->format('t');
    $start->setDate(
        (int)$start->format('Y'),
        (int)$start->format('m'),
        min($day, $lastDay)
    );

    return $start->format('Y-m-d');
}

$durations = [
    'none' => 36,
    'intermediate' => 24,
    'above_intermediate' => 18,
    'higher' => 12
];

$educationLabels = [
    'none' => 'بدون تعليم',
    'intermediate' => 'تعليم متوسط',
    'above_intermediate' => 'فوق متوسط',
    'higher' => 'تعليم عالٍ'
];

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
}

if (!$id || $id < 1) {
    header('Location: personal.php');
    exit;
}

$error = '';

try {
    $stmt = $pdo->prepare(
        "SELECT * FROM personnel WHERE id = :id LIMIT 1"
    );

    $stmt->execute([':id' => $id]);

    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        header('Location: personal.php');
        exit;
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    exit('تعذر تحميل بيانات السجل. حاول مرة أخرى.');
}

$fields = [
    'record_number',
    'full_name',
    'national_id',
    'birth_date',
    'address',
    'phone',
    'marital_status',
    'education_level',
    'civil_job',
    'service_start_date',
    'status',
    'notes'
];

$data = [];

foreach ($fields as $field) {
    $data[$field] = (string)($record[$field] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($fields as $field) {
        $value = $_POST[$field] ?? '';

        if (!is_string($value)) {
            $value = '';
        }

        $data[$field] = trim($value);
    }

    // التحقق من الحقول الأساسية.
    if (
        $data['record_number'] === '' ||
        $data['full_name'] === '' ||
        $data['national_id'] === '' ||
        $data['birth_date'] === '' ||
        $data['address'] === '' ||
        $data['education_level'] === '' ||
        $data['service_start_date'] === ''
    ) {
        $error = 'من فضلك املأ جميع الحقول الأساسية المطلوبة.';

    } elseif (
        mb_strlen($data['record_number'], 'UTF-8') > 30
    ) {
        $error = 'رقم السجل يجب ألا يزيد على 30 حرفًا.';

    } elseif (
        mb_strlen($data['full_name'], 'UTF-8') > 150
    ) {
        $error = 'الاسم أطول من الحد المسموح به.';

    } elseif (
        !preg_match('/^[0-9]{14}$/', $data['national_id'])
    ) {
        $error = 'الرقم القومي يجب أن يتكون من 14 رقمًا.';

    } elseif (
        !isset($durations[$data['education_level']])
    ) {
        $error = 'المؤهل الدراسي المحدد غير صحيح.';

    } elseif (
        !in_array($data['status'], ['active', 'completed'], true)
    ) {
        $error = 'حالة السجل غير صحيحة.';

    } elseif (
        !in_array(
            $data['marital_status'],
            ['', 'single', 'married', 'divorced', 'widowed'],
            true
        )
    ) {
        $error = 'الحالة الاجتماعية غير صحيحة.';

    } elseif (!validDate($data['birth_date'])) {
        $error = 'تاريخ الميلاد غير صحيح.';

    } elseif (!validDate($data['service_start_date'])) {
        $error = 'تاريخ بداية الخدمة غير صحيح.';

    } else {

        $birth = new DateTime($data['birth_date']);
        $start = new DateTime($data['service_start_date']);
        $today = new DateTime('today');

        if ($birth > $today) {
            $error = 'تاريخ الميلاد لا يمكن أن يكون في المستقبل.';

        } elseif ($start > $today) {
            $error = 'تاريخ بداية الخدمة لا يمكن أن يكون في المستقبل.';

        } elseif ($birth > $start) {
            $error = 'تاريخ الميلاد يجب أن يسبق تاريخ بداية الخدمة.';

        } else {

            $durationMonths = $durations[$data['education_level']];

            $expectedEndDate = calculateEndDate(
                $data['service_start_date'],
                $durationMonths
            );

            try {

                // منع تكرار رقم السجل أو الرقم القومي.
                $check = $pdo->prepare(
                    "SELECT id
                     FROM personnel
                     WHERE (
                         record_number = :record_number
                         OR national_id = :national_id
                     )
                     AND id <> :id
                     LIMIT 1"
                );

                $check->execute([
                    ':record_number' => $data['record_number'],
                    ':national_id' => $data['national_id'],
                    ':id' => $id
                ]);

                if ($check->fetch(PDO::FETCH_ASSOC)) {

                    $error = 'رقم السجل أو الرقم القومي مستخدم في سجل آخر.';

                } else {

                    $update = $pdo->prepare(
                        "UPDATE personnel SET
                            record_number = :record_number,
                            full_name = :full_name,
                            national_id = :national_id,
                            birth_date = :birth_date,
                            address = :address,
                            phone = :phone,
                            marital_status = :marital_status,
                            education_level = :education_level,
                            civil_job = :civil_job,
                            service_start_date = :service_start_date,
                            expected_end_date = :expected_end_date,
                            service_duration_months = :duration_months,
                            status = :status,
                            notes = :notes
                         WHERE id = :id"
                    );

                    $update->execute([
                        ':record_number' => $data['record_number'],
                        ':full_name' => $data['full_name'],
                        ':national_id' => $data['national_id'],
                        ':birth_date' => $data['birth_date'],
                        ':address' => $data['address'],
                        ':phone' => $data['phone'] !== ''
                            ? $data['phone']
                            : null,
                        ':marital_status' => $data['marital_status'] !== ''
                            ? $data['marital_status']
                            : null,
                        ':education_level' => $data['education_level'],
                        ':civil_job' => $data['civil_job'] !== ''
                            ? $data['civil_job']
                            : null,
                        ':service_start_date' => $data['service_start_date'],
                        ':expected_end_date' => $expectedEndDate,
                        ':duration_months' => $durationMonths,
                        ':status' => $data['status'],
                        ':notes' => $data['notes'] !== ''
                            ? $data['notes']
                            : null,
                        ':id' => $id
                    ]);

                    header(
                        'Location: view_personal.php?id='
                        . $id
                        . '&updated=1'
                    );

                    exit;
                }

            } catch (PDOException $ex) {

                error_log($ex->getMessage());

                if ($ex->getCode() === '23000') {
                    $error = 'تعذر الحفظ بسبب تكرار إحدى البيانات.';
                } else {
                    $error = 'حدث خطأ أثناء حفظ التعديلات. راجع إعدادات قاعدة البيانات.';
                }
            }
        }
    }
}

// حساب السن.
$age = '';

if (
    $data['birth_date'] !== ''
    && validDate($data['birth_date'])
) {
    $birth = new DateTime($data['birth_date']);
    $today = new DateTime('today');

    if ($birth <= $today) {
        $age = $birth->diff($today)->y;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>تعديل بيانات السجل</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

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
            color: white;
            padding: 25px 18px;
            overflow-y: auto;
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
            flex-shrink: 0;
        }

        .brand h2 {
            font-size: 16px;
        }

        .brand p {
            font-size: 10px;
            color: #b8c2ad;
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
            transition: 0.2s;
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
            line-height: 1.9;
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
        }

        .banner p {
            color: #e0e7d9;
            font-size: 13px;
            line-height: 1.9;
            overflow-wrap: anywhere;
        }

        .panel {
            max-width: 1000px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 25px;
        }

        .panel h2 {
            font-size: 18px;
            margin-bottom: 6px;
        }

        .panel-description {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 24px;
            line-height: 1.8;
        }

        .section-title {
            grid-column: 1 / -1;
            border-right: 3px solid var(--gold);
            padding: 8px 12px;
            margin-top: 8px;
            background: #f5f6f1;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .form-group {
            min-width: 0;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #dce1d5;
            border-radius: 9px;
            background: white;
            font-family: inherit;
            font-size: 13px;
            outline: none;
            color: var(--text);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--olive);
            box-shadow: 0 0 0 3px #66784918;
        }

        .form-group input[readonly] {
            background: #f5f6f1;
            color: var(--olive-dark);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .calculation-box {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            padding: 16px;
            border: 1px solid #dce4d1;
            background: #f4f7ef;
            border-radius: 12px;
        }

        .calculation-item {
            min-width: 0;
        }

        .calculation-item span {
            display: block;
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 5px;
        }

        .calculation-item strong {
            font-size: 15px;
            color: var(--olive-dark);
            overflow-wrap: anywhere;
        }

        .error {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 9px;
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            font-size: 13px;
            line-height: 1.9;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 25px;
        }

        .btn {
            display: inline-block;
            border: none;
            border-radius: 9px;
            padding: 12px 20px;
            background: var(--olive);
            color: white;
            font-family: inherit;
            font-size: 13px;
            cursor: pointer;
            text-align: center;
            transition: background 0.2s;
        }

        .btn:hover {
            background: var(--olive-dark);
        }

        .btn-secondary {
            background: #e8eddf;
            color: var(--olive-dark);
        }

        .btn-secondary:hover {
            background: #dce4d1;
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
        }

        @media (max-width: 550px) {
            .form-grid,
            .calculation-box {
                grid-template-columns: 1fr;
            }

            .panel {
                padding: 16px;
            }

            h1 {
                font-size: 21px;
            }

            .banner {
                padding: 18px;
            }

            .actions .btn {
                width: 100%;
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
    </nav>

    <a href="logout.php" class="logout">↪ تسجيل الخروج</a>
</aside>

<main class="main">

    <header class="topbar">
        <h1>تعديل بيانات السجل</h1>

        <p class="subtitle">
            عدّل البيانات المطلوبة، وسيُعاد حساب مدة الخدمة وتاريخ الانتهاء تلقائيًا.
        </p>
    </header>

    <section class="banner">
        <h2>✎ تعديل ملف السجل</h2>

        <p>
            رقم السجل: <?= e($record['record_number'] ?? '') ?>
            — <?= e($record['full_name'] ?? '') ?>
        </p>
    </section>

    <section class="panel">

        <h2>بيانات السجل</h2>

        <p class="panel-description">
            راجع البيانات بعد التعديل واضغط حفظ التعديلات.
        </p>

        <?php if ($error !== ''): ?>
            <div class="error" role="alert">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form
            method="POST"
            action="edit_personal.php?id=<?= (int)$id ?>"
            autocomplete="off"
        >

            <input type="hidden" name="id" value="<?= (int)$id ?>">

            <div class="form-grid">

                <h3 class="section-title">أولًا: البيانات الشخصية</h3>

                <div class="form-group">
                    <label for="record_number">رقم السجل *</label>

                    <input
                        id="record_number"
                        name="record_number"
                        required
                        maxlength="30"
                        value="<?= e($data['record_number']) ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="full_name">الاسم بالكامل *</label>

                    <input
                        id="full_name"
                        name="full_name"
                        required
                        maxlength="150"
                        value="<?= e($data['full_name']) ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="national_id">الرقم القومي *</label>

                    <input
                        id="national_id"
                        name="national_id"
                        required
                        inputmode="numeric"
                        maxlength="14"
                        pattern="[0-9]{14}"
                        title="أدخل الرقم القومي المكون من 14 رقمًا باللغة الإنجليزية"
                        value="<?= e($data['national_id']) ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="birth_date">تاريخ الميلاد *</label>

                    <input
                        type="date"
                        id="birth_date"
                        name="birth_date"
                        required
                        max="<?= date('Y-m-d') ?>"
                        value="<?= e($data['birth_date']) ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="age_display">السن</label>

                    <input
                        type="text"
                        id="age_display"
                        readonly
                        value="<?= e($age !== '' ? $age . ' سنة' : '') ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="phone">رقم الهاتف</label>

                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        maxlength="20"
                        value="<?= e($data['phone']) ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="address">العنوان بالتفصيل *</label>

                    <textarea
                        id="address"
                        name="address"
                        required
                    ><?= e($data['address']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="marital_status">الحالة الاجتماعية</label>

                    <select id="marital_status" name="marital_status">
                        <option value="">اختر الحالة</option>

                        <option
                            value="single"
                            <?= $data['marital_status'] === 'single' ? 'selected' : '' ?>
                        >أعزب</option>

                        <option
                            value="married"
                            <?= $data['marital_status'] === 'married' ? 'selected' : '' ?>
                        >متزوج</option>

                        <option
                            value="divorced"
                            <?= $data['marital_status'] === 'divorced' ? 'selected' : '' ?>
                        >مطلق</option>

                        <option
                            value="widowed"
                            <?= $data['marital_status'] === 'widowed' ? 'selected' : '' ?>
                        >أرمل</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="civil_job">المهنة المدنية</label>

                    <input
                        id="civil_job"
                        name="civil_job"
                        maxlength="150"
                        value="<?= e($data['civil_job']) ?>"
                    >
                </div>

                <h3 class="section-title">ثانيًا: بيانات الخدمة</h3>

                <div class="form-group">
                    <label for="education_level">المؤهل الدراسي *</label>

                    <select id="education_level" name="education_level" required>
                        <option value="">اختر المؤهل</option>

                        <?php foreach ($educationLabels as $value => $label): ?>
                            <option
                                value="<?= e($value) ?>"
                                <?= $data['education_level'] === $value ? 'selected' : '' ?>
                            >
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="service_start_date">تاريخ بداية الخدمة *</label>

                    <input
                        type="date"
                        id="service_start_date"
                        name="service_start_date"
                        max="<?= date('Y-m-d') ?>"
                        required
                        value="<?= e($data['service_start_date']) ?>"
                    >
                </div>

                <div class="calculation-box">

                    <div class="calculation-item">
                        <span>مدة الخدمة المحددة</span>
                        <strong id="duration_display">—</strong>
                    </div>

                    <div class="calculation-item">
                        <span>تاريخ الانتهاء المتوقع</span>
                        <strong id="end_date_display">—</strong>
                    </div>

                    <div class="calculation-item">
                        <span>السن</span>
                        <strong id="age_summary">
                            <?= e($age !== '' ? $age . ' سنة' : '—') ?>
                        </strong>
                    </div>

                </div>

                <div class="form-group">
                    <label for="status">حالة السجل</label>

                    <select id="status" name="status" required>
                        <option
                            value="active"
                            <?= $data['status'] === 'active' ? 'selected' : '' ?>
                        >نشط</option>

                        <option
                            value="completed"
                            <?= $data['status'] === 'completed' ? 'selected' : '' ?>
                        >مكتمل</option>
                    </select>
                </div>

                <h3 class="section-title">ثالثًا: الملاحظات</h3>

                <div class="form-group" style="grid-column:1/-1">
                    <label for="notes">الملاحظات</label>

                    <textarea
                        id="notes"
                        name="notes"
                    ><?= e($data['notes']) ?></textarea>
                </div>

            </div>

            <div class="actions">

                <button type="submit" class="btn">
                    حفظ التعديلات
                </button>

                <a
                    href="view_personal.php?id=<?= (int)$id ?>"
                    class="btn btn-secondary"
                >
                    إلغاء
                </a>

                <a href="personal.php" class="btn btn-secondary">
                    قائمة السجلات
                </a>

            </div>

        </form>

    </section>

</main>

<script>
'use strict';

const durations = {
    none: 36,
    intermediate: 24,
    above_intermediate: 18,
    higher: 12
};

const educationSelect = document.getElementById('education_level');
const startInput = document.getElementById('service_start_date');
const birthInput = document.getElementById('birth_date');

const durationDisplay = document.getElementById('duration_display');
const endDateDisplay = document.getElementById('end_date_display');
const ageDisplay = document.getElementById('age_display');
const ageSummary = document.getElementById('age_summary');

function formatDate(date) {
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');

    return `${day}/${month}/${date.getFullYear()}`;
}

// حساب تاريخ الانتهاء مع مراعاة اختلاف عدد أيام الشهور.
function calculateEndDate(startValue, months) {
    if (!startValue || !months) {
        return null;
    }

    const parts = startValue.split('-').map(Number);

    if (parts.length !== 3 || parts.some(Number.isNaN)) {
        return null;
    }

    const [year, month, day] = parts;

    const targetIndex = month - 1 + months;
    const targetYear = year + Math.floor(targetIndex / 12);
    const targetMonth = targetIndex % 12;

    const lastDay = new Date(
        targetYear,
        targetMonth + 1,
        0
    ).getDate();

    return new Date(
        targetYear,
        targetMonth,
        Math.min(day, lastDay)
    );
}

function calculateAge(value) {
    if (!value) {
        return null;
    }

    const parts = value.split('-').map(Number);

    if (parts.length !== 3 || parts.some(Number.isNaN)) {
        return null;
    }

    const [year, month, day] = parts;

    const birth = new Date(year, month - 1, day);
    const today = new Date();

    if (
        birth.getFullYear() !== year ||
        birth.getMonth() !== month - 1 ||
        birth.getDate() !== day ||
        birth > today
    ) {
        return null;
    }

    let age = today.getFullYear() - year;

    if (
        today.getMonth() < month - 1 ||
        (
            today.getMonth() === month - 1 &&
            today.getDate() < day
        )
    ) {
        age--;
    }

    return age;
}

function updateCalculations() {
    const months = durations[educationSelect.value];
    const startValue = startInput.value;

    if (months) {
        const years = Math.floor(months / 12);
        const remainingMonths = months % 12;

        let text = '';

        if (years > 0) {
            text = years + (years === 1 ? ' سنة' : ' سنوات');
        }

        if (remainingMonths > 0) {
            text += (text ? ' و' : '') + remainingMonths + ' شهر';
        }

        durationDisplay.textContent = text || '—';
    } else {
        durationDisplay.textContent = '—';
    }

    const endDate = months
        ? calculateEndDate(startValue, months)
        : null;

    endDateDisplay.textContent = endDate
        ? formatDate(endDate)
        : '—';

    const age = calculateAge(birthInput.value);

    ageDisplay.value = age === null
        ? ''
        : age + ' سنة';

    ageSummary.textContent = age === null
        ? '—'
        : age + ' سنة';
}

educationSelect.addEventListener('change', updateCalculations);
startInput.addEventListener('change', updateCalculations);
birthInput.addEventListener('change', updateCalculations);

updateCalculations();
</script>

</body>
</html>