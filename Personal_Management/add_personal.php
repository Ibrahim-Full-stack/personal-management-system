<?php
session_start();

require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// منع المشاهد من إضافة سجلات
if (!in_array($_SESSION['role'] ?? '', ['admin', 'editor'], true)) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function calculateAge(string $birthDate): ?int
{
    if ($birthDate === '') {
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

$educationDurations = [
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

$old = [
    'record_number' => '',
    'full_name' => '',
    'national_id' => '',
    'birth_date' => '',
    'address' => '',
    'phone' => '',
    'marital_status' => '',
    'education_level' => '',
    'civil_job' => '',
    'service_start_date' => '',
    'status' => 'active',
    'notes' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $key => $value) {
        if ($key === 'status') {
            $old[$key] = trim($_POST[$key] ?? 'active');
        } else {
            $old[$key] = trim($_POST[$key] ?? '');
        }
    }

    $record_number = $old['record_number'];
    $full_name = $old['full_name'];
    $national_id = $old['national_id'];
    $birth_date = $old['birth_date'];
    $address = $old['address'];
    $phone = $old['phone'];
    $marital_status = $old['marital_status'];
    $education_level = $old['education_level'];
    $civil_job = $old['civil_job'];
    $service_start_date = $old['service_start_date'];
    $status = $old['status'];
    $notes = $old['notes'];

    if (
        $record_number === '' ||
        $full_name === '' ||
        $national_id === '' ||
        $birth_date === '' ||
        $address === '' ||
        $education_level === '' ||
        $service_start_date === ''
    ) {
        $error = 'من فضلك املأ جميع الحقول الأساسية المطلوبة.';
    } elseif (!preg_match('/^[0-9]{14}$/', $national_id)) {
        $error = 'الرقم القومي يجب أن يتكون من 14 رقمًا.';
    } elseif (!isset($educationDurations[$education_level])) {
        $error = 'المؤهل الدراسي المحدد غير صحيح.';
    } elseif (!in_array($status, ['active', 'completed'], true)) {
        $error = 'حالة السجل غير صحيحة.';
    } elseif (
        !in_array(
            $marital_status,
            ['', 'single', 'married', 'divorced', 'widowed'],
            true
        )
    ) {
        $error = 'الحالة الاجتماعية غير صحيحة.';
    } else {
        $startDateObject = DateTime::createFromFormat('!Y-m-d', $service_start_date);
        $birthDateObject = DateTime::createFromFormat('!Y-m-d', $birth_date);
        $today = new DateTime('today');

        if (
            !$startDateObject ||
            $startDateObject->format('Y-m-d') !== $service_start_date
        ) {
            $error = 'تاريخ بداية الخدمة غير صحيح.';
        } elseif (
            !$birthDateObject ||
            $birthDateObject->format('Y-m-d') !== $birth_date ||
            $birthDateObject > $today
        ) {
            $error = 'تاريخ الميلاد غير صحيح.';
        } elseif ($startDateObject > $today) {
            $error = 'تاريخ بداية الخدمة لا يمكن أن يكون في المستقبل.';
        } elseif (calculateAge($birth_date) === null) {
            $error = 'تعذر حساب السن من تاريخ الميلاد.';
        } else {
            try {
                // حساب مدة الخدمة بالشهور وفق المدد التي حددها المستخدم.
                $durationMonths = $educationDurations[$education_level];

                // حساب تاريخ الانتهاء تلقائيًا في الخادم.
                $endDateObject = clone $startDateObject;
                $endDateObject->modify('+' . $durationMonths . ' months');
                $expected_end_date = $endDateObject->format('Y-m-d');

                $stmt = $pdo->prepare(
                    "INSERT INTO personnel (
                        record_number,
                        full_name,
                        national_id,
                        birth_date,
                        address,
                        phone,
                        marital_status,
                        education_level,
                        civil_job,
                        service_start_date,
                        expected_end_date,
                        service_duration_months,
                        status,
                        notes
                    ) VALUES (
                        :record_number,
                        :full_name,
                        :national_id,
                        :birth_date,
                        :address,
                        :phone,
                        :marital_status,
                        :education_level,
                        :civil_job,
                        :service_start_date,
                        :expected_end_date,
                        :duration_months,
                        :status,
                        :notes
                    )"
                );

                $stmt->execute([
                    ':record_number' => $record_number,
                    ':full_name' => $full_name,
                    ':national_id' => $national_id,
                    ':birth_date' => $birth_date,
                    ':address' => $address,
                    ':phone' => $phone !== '' ? $phone : null,
                    ':marital_status' => $marital_status !== '' ? $marital_status : null,
                    ':education_level' => $education_level,
                    ':civil_job' => $civil_job !== '' ? $civil_job : null,
                    ':service_start_date' => $service_start_date,
                    ':expected_end_date' => $expected_end_date,
                    ':duration_months' => $durationMonths,
                    ':status' => $status,
                    ':notes' => $notes !== '' ? $notes : null
                ]);

                header('Location: /Personal_Management/personal.php?added=1');
                exit;

            } catch (PDOException $ex) {
                error_log($ex->getMessage());

                if ($ex->getCode() === '23000') {
                    $error = 'رقم السجل أو الرقم القومي مسجل بالفعل، أو توجد مشكلة في البيانات.';
                } else {
                    $error = 'تعذر حفظ السجل. تأكد من أعمدة قاعدة البيانات ثم حاول مرة أخرى.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>إضافة سجل جديد</title>

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
            text-decoration: none;
            color: inherit;
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
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--olive);
        }

        .form-group small {
            display: block;
            color: var(--muted);
            margin-top: 6px;
            font-size: 11px;
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

        .calculation-item span {
            display: block;
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 5px;
        }

        .calculation-item strong {
            font-size: 15px;
            color: var(--olive-dark);
        }

        .error {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 9px;
            background: #fee2e2;
            color: #991b1b;
            font-size: 13px;
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
        }

        .btn:hover {
            background: var(--olive-dark);
        }

        .btn-secondary {
            background: #e8eddf;
            color: var(--olive-dark);
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
        <a href="personal.php">▤ &nbsp; إدارة السجلات</a>
        <a href="add_personal.php" class="active">＋ &nbsp; إضافة سجل جديد</a>
    </nav>

    <a href="logout.php" class="logout">↪ تسجيل الخروج</a>
</aside>

<main class="main">

    <header class="topbar">
        <h1>إضافة سجل جديد</h1>
        <p class="subtitle">أدخل بيانات السجل، وسيتم حساب مدة الخدمة وتاريخ انتهائها تلقائيًا.</p>
    </header>

    <section class="banner">
        <h2>تسجيل بيانات جديدة</h2>
        <p>
            أدخل البيانات بدقة. يتم حساب مدة الخدمة وفق المؤهل الذي تختاره
            وحسب المدد المحددة في إعدادات النظام.
        </p>
    </section>

    <section class="panel">
        <h2>بيانات السجل</h2>
        <p class="panel-description">الحقول الأساسية مطلوبة، وباقي البيانات يمكن استكمالها حسب الحاجة.</p>

        <?php if ($error !== ''): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="add_personal.php">
            <div class="form-grid">

                <h3 class="section-title">أولًا: البيانات الشخصية</h3>

                <div class="form-group">
                    <label for="record_number">رقم السجل *</label>
                    <input type="text" id="record_number" name="record_number"
                           value="<?= e($old['record_number']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="full_name">الاسم بالكامل *</label>
                    <input type="text" id="full_name" name="full_name"
                           value="<?= e($old['full_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="national_id">الرقم القومي *</label>
                    <input type="text" id="national_id" name="national_id"
                           inputmode="numeric" maxlength="14" pattern="[0-9]{14}"
                           value="<?= e($old['national_id']) ?>" required>
                    <small>14 رقمًا، وسيتم التحقق منه قبل الحفظ.</small>
                </div>

                <div class="form-group">
                    <label for="birth_date">تاريخ الميلاد *</label>
                    <input type="date" id="birth_date" name="birth_date"
                           value="<?= e($old['birth_date']) ?>"
                           max="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label>السن</label>
                    <input type="text" id="age_display" readonly placeholder="يُحسب تلقائيًا">
                </div>

                <div class="form-group">
                    <label for="phone">رقم الهاتف</label>
                    <input type="tel" id="phone" name="phone"
                           value="<?= e($old['phone']) ?>">
                </div>

                <div class="form-group">
                    <label for="address">العنوان بالتفصيل *</label>
                    <textarea id="address" name="address" required><?= e($old['address']) ?></textarea>
                </div>

                <div class="form-group">
                    <label for="marital_status">الحالة الاجتماعية</label>
                    <select id="marital_status" name="marital_status">
                        <option value="">اختر الحالة</option>
                        <option value="single" <?= $old['marital_status'] === 'single' ? 'selected' : '' ?>>أعزب</option>
                        <option value="married" <?= $old['marital_status'] === 'married' ? 'selected' : '' ?>>متزوج</option>
                        <option value="divorced" <?= $old['marital_status'] === 'divorced' ? 'selected' : '' ?>>مطلق</option>
                        <option value="widowed" <?= $old['marital_status'] === 'widowed' ? 'selected' : '' ?>>أرمل</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="civil_job">المهنة المدنية</label>
                    <input type="text" id="civil_job" name="civil_job"
                           value="<?= e($old['civil_job']) ?>">
                </div>

                <h3 class="section-title">ثانيًا: بيانات الخدمة</h3>

                <div class="form-group">
                    <label for="education_level">المؤهل الدراسي *</label>
                    <select id="education_level" name="education_level" required>
                        <option value="">اختر المؤهل الدراسي</option>
                        <?php foreach ($educationLabels as $value => $label): ?>
                            <option value="<?= e($value) ?>"
                                <?= $old['education_level'] === $value ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="service_start_date">تاريخ بداية الخدمة *</label>
                    <input type="date" id="service_start_date" name="service_start_date"
                           value="<?= e($old['service_start_date']) ?>"
                           max="<?= date('Y-m-d') ?>" required>
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
                        <strong id="age_summary">—</strong>
                    </div>
                </div>

                <div class="form-group">
                    <label for="status">حالة السجل</label>
                    <select id="status" name="status" required>
                        <option value="active" <?= $old['status'] === 'active' ? 'selected' : '' ?>>نشط</option>
                        <option value="completed" <?= $old['status'] === 'completed' ? 'selected' : '' ?>>مكتمل</option>
                    </select>
                </div>

                <h3 class="section-title">ثالثًا: الملاحظات الإدارية</h3>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label for="notes">الملاحظات</label>
                    <textarea id="notes" name="notes"
                              placeholder="أدخل الملاحظات الإدارية المسموح بتسجيلها"><?= e($old['notes']) ?></textarea>
                </div>

            </div>

            <div class="actions">
                <button type="submit" class="btn">حفظ السجل</button>
                <a href="personal.php" class="btn btn-secondary">العودة للسجلات</a>
            </div>
        </form>
    </section>
</main>

<script>
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
    const year = date.getFullYear();

    return `${day}/${month}/${year}`;
}

function calculateEndDate(startValue, months) {
    const parts = startValue.split('-').map(Number);

    if (parts.length !== 3 || !months) {
        return null;
    }

    const [year, month, day] = parts;
    const targetMonthIndex = month - 1 + months;
    const targetYear = year + Math.floor(targetMonthIndex / 12);
    const targetMonth = targetMonthIndex % 12;

    // الحفاظ على آخر يوم صالح إذا كان يوم البداية غير موجود في الشهر النهائي.
    const lastDay = new Date(targetYear, targetMonth + 1, 0).getDate();

    return new Date(
        targetYear,
        targetMonth,
        Math.min(day, lastDay)
    );
}

function calculateAge(birthValue) {
    if (!birthValue) {
        return null;
    }

    const parts = birthValue.split('-').map(Number);

    if (parts.length !== 3) {
        return null;
    }

    const [year, month, day] = parts;
    const birth = new Date(year, month - 1, day);
    const today = new Date();

    if (birth > today) {
        return null;
    }

    let age = today.getFullYear() - birth.getFullYear();

    if (
        today.getMonth() < birth.getMonth() ||
        (
            today.getMonth() === birth.getMonth() &&
            today.getDate() < birth.getDate()
        )
    ) {
        age--;
    }

    return age;
}

function updateCalculations() {
    const education = educationSelect.value;
    const months = durations[education];
    const startValue = startInput.value;

    if (months) {
        const years = Math.floor(months / 12);
        const remainingMonths = months % 12;

        let durationText = '';

        if (years > 0) {
            durationText += years + (years === 1 ? ' سنة' : ' سنوات');
        }

        if (remainingMonths > 0) {
            if (durationText) {
                durationText += ' و';
            }

            durationText += remainingMonths + ' شهر';
        }

        durationDisplay.textContent = durationText;
    } else {
        durationDisplay.textContent = '—';
    }

    const endDate = months && startValue
        ? calculateEndDate(startValue, months)
        : null;

    endDateDisplay.textContent = endDate
        ? formatDate(endDate)
        : '—';

    const age = calculateAge(birthInput.value);

    ageDisplay.value = age !== null ? age + ' سنة' : '';
    ageSummary.textContent = age !== null ? age + ' سنة' : '—';
}

educationSelect.addEventListener('change', updateCalculations);
startInput.addEventListener('change', updateCalculations);
birthInput.addEventListener('change', updateCalculations);

updateCalculations();
</script>

</body>
</html>