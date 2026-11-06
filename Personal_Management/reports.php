<?php
session_start();

require_once __DIR__ . '/config/database.php';

// التأكد من تسجيل الدخول
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// التقارير متاحة للمدير فقط
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// دالة حماية المخرجات
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// استقبال الفلاتر
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$error = '';
$records = [];

// التحقق من الحالة
if (!in_array($status, ['', 'active', 'completed'], true)) {
    $status = '';
}

// التحقق من صحة التاريخ
function validDate(string $date): bool
{
    if ($date === '') {
        return true;
    }

    $object = DateTime::createFromFormat('!Y-m-d', $date);

    return $object && $object->format('Y-m-d') === $date;
}

if (!validDate($dateFrom) || !validDate($dateTo)) {
    $error = 'أحد التواريخ المدخلة غير صحيح.';
} elseif ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    $error = 'تاريخ البداية يجب أن يكون قبل تاريخ النهاية.';
} else {

    try {
        // تجهيز شروط البحث
        $conditions = [];
        $params = [];

        // البحث بالاسم أو رقم السجل أو الرقم القومي
        if ($search !== '') {
            $conditions[] = "(
                record_number LIKE :search1
                OR full_name LIKE :search2
                OR national_id LIKE :search3
            )";

            $term = '%' . $search . '%';

            $params[':search1'] = $term;
            $params[':search2'] = $term;
            $params[':search3'] = $term;
        }

        // التصفية حسب الحالة
        if ($status !== '') {
            $conditions[] = "status = :status";
            $params[':status'] = $status;
        }

        // تاريخ بداية الخدمة من
        if ($dateFrom !== '') {
            $conditions[] = "service_start_date >= :date_from";
            $params[':date_from'] = $dateFrom;
        }

        // تاريخ بداية الخدمة إلى
        if ($dateTo !== '') {
            $conditions[] = "service_start_date <= :date_to";
            $params[':date_to'] = $dateTo;
        }

        // تكوين جملة SQL
        $sql = "
            SELECT
                id,
                record_number,
                full_name,
                service_start_date,
                expected_end_date,
                status
            FROM personnel
        ";

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        $error = 'حدث خطأ أثناء تحميل التقرير.';
    }
}

// حساب الإحصائيات للنتائج الحالية
$totalRecords = count($records);
$activeCount = 0;
$completedCount = 0;

foreach ($records as $record) {
    if ($record['status'] === 'active') {
        $activeCount++;
    } elseif ($record['status'] === 'completed') {
        $completedCount++;
    }
}

// أسماء الحالات
$statusNames = [
    'active' => 'نشط',
    'completed' => 'مكتمل'
];

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>التقارير والطباعة | إدارة السجلات</title>

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

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 20px;
        }

        .stat-card span {
            display: block;
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 8px;
        }

        .stat-card strong {
            font-size: 25px;
            color: var(--olive-dark);
        }

        .panel {
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 25px;
        }

        .panel h2 {
            font-size: 18px;
            margin-bottom: 8px;
        }

        .description {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 22px;
        }

        .filters {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
            align-items: end;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 11px;
            border: 1px solid #dce1d5;
            border-radius: 9px;
            font-family: inherit;
            font-size: 12px;
            outline: none;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--olive);
        }

        .filter-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            border: none;
            border-radius: 9px;
            background: var(--olive);
            color: white;
            font-family: inherit;
            font-size: 12px;
            cursor: pointer;
            text-align: center;
        }

        .btn:hover {
            background: var(--olive-dark);
        }

        .btn-secondary {
            background: #e8eddf;
            color: var(--olive-dark);
        }

        .btn-print {
            background: #356a91;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            padding: 13px;
            border-radius: 9px;
            margin-bottom: 18px;
            font-size: 13px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 750px;
            border-collapse: collapse;
        }

        th {
            background: #f5f6f1;
            color: #697360;
            text-align: right;
            padding: 14px 12px;
            font-size: 12px;
        }

        td {
            padding: 13px 12px;
            border-bottom: 1px solid #edf0e8;
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

        .active {
            background: #e2f1e1;
            color: #426b3e;
        }

        .completed {
            background: #f5eddd;
            color: #876a37;
        }

        .empty {
            padding: 30px;
            text-align: center;
            color: var(--muted);
        }

        .report-header {
            display: none;
        }

        @media (max-width: 950px) {
            .filters {
                grid-template-columns: repeat(2, 1fr);
            }

            .stats {
                grid-template-columns: 1fr;
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
        }

        @media (max-width: 550px) {
            .filters {
                grid-template-columns: 1fr;
            }

            .panel {
                padding: 15px;
            }

            h1 {
                font-size: 22px;
            }
        }

        /* تنسيق الطباعة */
        @media print {

            @page {
                size: landscape;
                margin: 12mm;
            }

            body {
                background: white;
                color: black;
                font-family: Tahoma, Arial, sans-serif;
            }

            .sidebar,
            .banner,
            .filters-panel,
            .print-button,
            .no-print {
                display: none !important;
            }

            .main {
                margin: 0;
                padding: 0;
            }

            .report-header {
                display: block;
                text-align: center;
                margin-bottom: 25px;
                padding-bottom: 15px;
                border-bottom: 2px solid #333;
            }

            .report-header h2 {
                font-size: 20px;
                margin-bottom: 8px;
            }

            .report-header p {
                font-size: 12px;
            }

            .stats {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
                margin-bottom: 20px;
            }

            .stat-card {
                border: 1px solid #aaa;
                padding: 10px;
            }

            .panel {
                border: none;
                padding: 0;
                margin: 0;
            }

            .panel h2,
            .description {
                display: none;
            }

            table {
                width: 100%;
                min-width: 0;
                border-collapse: collapse;
            }

            th,
            td {
                border: 1px solid #999;
                padding: 8px;
                font-size: 10px;
                color: black;
            }

            th {
                background: #eee !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            tr {
                page-break-inside: avoid;
            }

            .status {
                background: none !important;
                color: black !important;
                padding: 0;
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
        <a href="add_personal.php">＋ &nbsp; إضافة سجل جديد</a>
        <a href="reports.php" class="active">▧ &nbsp; التقارير والطباعة</a>
    </nav>

    <a href="logout.php" class="logout">↪ تسجيل الخروج</a>

</aside>

<main class="main">

    <header class="topbar">
        <h1>التقارير والطباعة</h1>
        <p class="subtitle">
            استخراج تقارير السجلات والبحث والتصفية وطباعتها.
        </p>
    </header>

    <section class="banner">
        <h2>مركز التقارير</h2>
        <p>
            يمكنك استخراج تقرير حسب الاسم أو رقم السجل أو الحالة
            أو الفترة الزمنية، ثم طباعته مباشرة.
        </p>
    </section>

    <div class="report-header">
        <h2>تقرير إدارة السجلات</h2>
        <p>تاريخ استخراج التقرير: <?= date('Y-m-d H:i') ?></p>
    </div>

    <section class="stats">

        <div class="stat-card">
            <span>إجمالي النتائج</span>
            <strong><?= $totalRecords ?></strong>
        </div>

        <div class="stat-card">
            <span>السجلات النشطة</span>
            <strong><?= $activeCount ?></strong>
        </div>

        <div class="stat-card">
            <span>السجلات المكتملة</span>
            <strong><?= $completedCount ?></strong>
        </div>

    </section>

    <section class="panel filters-panel">

        <h2>تصفية التقرير</h2>

        <p class="description">
            حدد معايير البحث للحصول على النتائج المطلوبة.
        </p>

        <?php if ($error !== ''): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="GET" action="reports.php">

            <div class="filters">

                <div class="form-group">
                    <label>البحث</label>

                    <input
                        type="search"
                        name="search"
                        placeholder="الاسم أو رقم السجل أو الرقم القومي"
                        value="<?= e($search) ?>"
                    >
                </div>

                <div class="form-group">
                    <label>حالة السجل</label>

                    <select name="status">
                        <option value="">جميع الحالات</option>

                        <option value="active"
                            <?= $status === 'active' ? 'selected' : '' ?>>
                            نشط
                        </option>

                        <option value="completed"
                            <?= $status === 'completed' ? 'selected' : '' ?>>
                            مكتمل
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label>من تاريخ بداية الخدمة</label>

                    <input
                        type="date"
                        name="date_from"
                        value="<?= e($dateFrom) ?>"
                    >
                </div>

                <div class="form-group">
                    <label>إلى تاريخ بداية الخدمة</label>

                    <input
                        type="date"
                        name="date_to"
                        value="<?= e($dateTo) ?>"
                    >
                </div>

            </div>

            <div class="filter-actions">

                <button type="submit" class="btn">
                    بحث وتصفية
                </button>

                <a href="reports.php" class="btn btn-secondary">
                    إعادة ضبط
                </a>

                <button
                    type="button"
                    class="btn btn-print print-button"
                    onclick="window.print()"
                >
                    🖨 طباعة التقرير
                </button>

            </div>

        </form>

    </section>

    <section class="panel">

        <h2>نتائج التقرير</h2>

        <p class="description">
            عدد السجلات المعروضة: <?= $totalRecords ?>
        </p>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>#</th>
                        <th>رقم السجل</th>
                        <th>الاسم بالكامل</th>
                        <th>بداية الخدمة</th>
                        <th>النهاية المتوقعة</th>
                        <th>الحالة</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (!empty($records)): ?>

                    <?php foreach ($records as $index => $record): ?>

                        <?php
                        $recordStatus = $record['status'] ?? '';
                        $statusName = $statusNames[$recordStatus] ?? 'غير محدد';
                        ?>

                        <tr>

                            <td><?= $index + 1 ?></td>

                            <td><?= e($record['record_number']) ?></td>

                            <td><?= e($record['full_name']) ?></td>

                            <td><?= e($record['service_start_date']) ?></td>

                            <td><?= e($record['expected_end_date']) ?></td>

                            <td>
                                <span class="status <?= e($recordStatus) ?>">
                                    <?= e($statusName) ?>
                                </span>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="empty">
                            لا توجد سجلات مطابقة لمعايير البحث.
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