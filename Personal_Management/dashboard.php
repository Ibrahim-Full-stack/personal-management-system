<?php

session_start();

require_once __DIR__ . '/config/database.php';

/*
|--------------------------------------------------------------------------
| Personnel Management System
| Developed & Designed by Ibrahim Ali El-Tabakh
| © 2026 Ibrahim Ali El-Tabakh. All Rights Reserved.
|--------------------------------------------------------------------------
*/

// التأكد من تسجيل الدخول
if (empty($_SESSION['user_id'])) {
    header('Location: /Personal_Management/login.php');
    exit;
}

// المدير فقط يمكنه الدخول
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /Personal_Management/login.php');
    exit;
}

// حماية عرض البيانات
function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// تنسيق التاريخ
function formatDate($date): string
{
    if (empty($date) || $date === '0000-00-00') {
        return 'غير محدد';
    }

    $timestamp = strtotime($date);

    return $timestamp === false
        ? 'غير محدد'
        : date('Y-m-d', $timestamp);
}

$username = $_SESSION['username'] ?? 'المستخدم';
$role = 'مدير النظام';

// متغيرات الإحصائيات
$totalPersonnel = 0;
$activePersonnel = 0;
$completedPersonnel = 0;
$endingSoonPersonnel = 0;
$latestPersonnel = [];

$errorMessage = false;

try {

    // إجمالي السجلات
    $totalPersonnel = (int) $pdo
        ->query("SELECT COUNT(*) FROM personnel")
        ->fetchColumn();

    // السجلات النشطة
    $activePersonnel = (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM personnel
            WHERE status = 'active'
        ")
        ->fetchColumn();

    // السجلات المكتملة
    $completedPersonnel = (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM personnel
            WHERE status = 'completed'
        ")
        ->fetchColumn();

    // السجلات المتوقع انتهاء خدمتها خلال 90 يومًا
    $endingSoonPersonnel = (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM personnel
            WHERE status = 'active'
            AND expected_end_date >= CURDATE()
            AND expected_end_date <= DATE_ADD(
                CURDATE(),
                INTERVAL 90 DAY
            )
        ")
        ->fetchColumn();

    // أحدث 5 سجلات
    $stmt = $pdo->query("
        SELECT
            id,
            record_number,
            full_name,
            service_start_date,
            expected_end_date,
            status
        FROM personnel
        ORDER BY created_at DESC, id DESC
        LIMIT 5
    ");

    $latestPersonnel = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {

    error_log($ex->getMessage());
    $errorMessage = true;
}

// حساب النسب المئوية
$activePercentage = $totalPersonnel > 0
    ? min(100, round(($activePersonnel / $totalPersonnel) * 100))
    : 0;

$completedPercentage = $totalPersonnel > 0
    ? min(100, round(($completedPersonnel / $totalPersonnel) * 100))
    : 0;

$otherPersonnel = max(
    0,
    $totalPersonnel - $activePersonnel - $completedPersonnel
);

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>لوحة التحكم | Personnel Management System</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

:root {
    --olive: #667849;
    --olive-dark: #39472c;
    --olive-light: #e8eddf;
    --gold: #c9a96e;
    --sand: #e7dcc3;
    --background: #f4f5f0;
    --white: #ffffff;
    --text: #263126;
    --muted: #81897c;
    --sidebar: #20291f;
    --sidebar-light: #303c2d;
    --border: #e7e9e1;
    --green: #54804c;
    --orange: #b98535;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Cairo', Tahoma, Arial, sans-serif;
    background: var(--background);
    color: var(--text);
    min-height: 100vh;
}

a {
    text-decoration: none;
    color: inherit;
}

/* ================= SIDEBAR ================= */

.sidebar {
    position: fixed;
    top: 0;
    right: 0;
    width: 260px;
    height: 100vh;
    background: var(--sidebar);
    color: white;
    padding: 25px 18px;
    display: flex;
    flex-direction: column;
    z-index: 1000;
    overflow-y: auto;
}

.brand {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 5px 8px 25px;
    border-bottom: 1px solid rgba(255,255,255,.09);
}

.brand-logo {
    width: 48px;
    height: 48px;
    border: 1px solid rgba(201,169,110,.7);
    border-radius: 14px;
    background: linear-gradient(145deg,#667849,#39472c);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 25px;
    flex-shrink: 0;
}

.brand h2 {
    font-size: 16px;
    font-weight: 800;
}

.brand p {
    color: #b8c2ad;
    font-size: 10px;
    margin-top: 3px;
}

.menu-title {
    color: #8e9b83;
    font-size: 11px;
    font-weight: 700;
    padding: 25px 12px 12px;
}

.menu {
    list-style: none;
}

.menu li {
    margin-bottom: 7px;
}

.menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 13px;
    border-radius: 10px;
    color: #c2cbb9;
    font-size: 13px;
    transition: .25s;
}

.menu a:hover {
    background: var(--sidebar-light);
    color: white;
}

.menu a.active {
    background: var(--olive);
    color: white;
    box-shadow: 0 5px 15px rgba(0,0,0,.12);
}

.menu-icon {
    width: 25px;
    text-align: center;
    font-size: 18px;
}

.sidebar-bottom {
    margin-top: auto;
    padding-top: 25px;
}

.sidebar-note {
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.08);
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 15px;
}

.sidebar-note strong {
    display: block;
    font-size: 12px;
    margin-bottom: 6px;
    color: var(--sand);
}

.sidebar-note p {
    font-size: 11px;
    line-height: 1.9;
    color: #b6c0ad;
}

.logout {
    display: block;
    padding: 11px;
    border-radius: 9px;
    text-align: center;
    color: #e5b6aa;
    background: rgba(160,65,48,.13);
    font-size: 13px;
    transition: .2s;
}

.logout:hover {
    background: rgba(160,65,48,.25);
}

/* ================= MAIN ================= */

.main {
    margin-right: 260px;
    padding: 30px;
    min-height: 100vh;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 30px;
}

.page-heading h1 {
    font-size: 25px;
    font-weight: 800;
}

.page-heading p {
    font-size: 13px;
    color: var(--muted);
    margin-top: 5px;
}

.user-box {
    display: flex;
    align-items: center;
    gap: 12px;
    background: white;
    border: 1px solid var(--border);
    padding: 9px 14px;
    border-radius: 13px;
}

.user-avatar {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: var(--olive-light);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 21px;
}

.user-info strong {
    display: block;
    font-size: 13px;
}

.user-info span {
    font-size: 11px;
    color: var(--muted);
}

/* ================= HERO ================= */

.hero {
    position: relative;
    overflow: hidden;
    background: linear-gradient(115deg,#263322,#465b36 65%,#667849);
    border-radius: 20px;
    padding: 34px;
    color: white;
    margin-bottom: 28px;
    min-height: 210px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.hero-content {
    position: relative;
    z-index: 1;
}

.hero-label {
    display: inline-block;
    background: rgba(201,169,110,.17);
    border: 1px solid rgba(201,169,110,.35);
    color: #f0d7a5;
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 11px;
    margin-bottom: 15px;
}

.hero h2 {
    font-size: 27px;
    font-weight: 800;
    margin-bottom: 9px;
}

.hero p {
    font-size: 13px;
    color: #d5ddce;
    line-height: 2;
    max-width: 500px;
}

.hero-emblem {
    width: 105px;
    height: 105px;
    min-width: 105px;
    border: 1px solid rgba(231,220,195,.45);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    color: var(--sand);
    background: rgba(255,255,255,.04);
}

/* ================= SECTION ================= */

.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 17px;
}

.section-heading h2 {
    font-size: 18px;
    font-weight: 800;
}

.section-heading p {
    font-size: 12px;
    color: var(--muted);
    margin-top: 4px;
}

.section-tag {
    font-size: 11px;
    background: var(--olive-light);
    color: var(--olive-dark);
    padding: 7px 12px;
    border-radius: 8px;
}

/* ================= STATISTICS ================= */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4,minmax(0,1fr));
    gap: 16px;
    margin-bottom: 28px;
}

.stat-card {
    background: white;
    padding: 22px;
    border: 1px solid var(--border);
    border-radius: 16px;
    transition: .25s;
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 25px rgba(35,48,31,.07);
}

.stat-card::after {
    content: "";
    position: absolute;
    top: 0;
    right: 0;
    height: 4px;
    width: 100%;
    background: var(--olive);
}

.stat-card.active-card::after {
    background: #6b9862;
}

.stat-card.completed-card::after {
    background: var(--gold);
}

.stat-card.ending-card::after {
    background: var(--orange);
}

.stat-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    gap: 10px;
}

.stat-icon {
    width: 45px;
    height: 45px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    background: var(--olive-light);
}

.active-card .stat-icon {
    background: #e2f1e1;
}

.completed-card .stat-icon {
    background: #f5eddd;
}

.ending-card .stat-icon {
    background: #fbefdd;
}

.stat-badge {
    color: var(--muted);
    font-size: 10px;
}

.stat-card h3 {
    font-size: 28px;
    font-weight: 800;
    margin-bottom: 4px;
}

.stat-card p {
    font-size: 12px;
    color: var(--muted);
    line-height: 1.8;
}

/* ================= BOTTOM GRID ================= */

.bottom-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(0, .85fr);
    grid-template-areas:
        "latest analysis"
        "latest system";
    align-items: start;
    gap: 20px;
    width: 100%;
}

.latest-panel {
    grid-area: latest;
}

.analysis-panel {
    grid-area: analysis;
}

.system-panel {
    grid-area: system;
}

/* ================= PANELS ================= */

.panel {
    background: white;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    min-width: 0;
    box-shadow: 0 3px 12px rgba(35,48,31,.025);
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.panel-header h3 {
    font-size: 16px;
    font-weight: 800;
}

.panel-header span {
    font-size: 11px;
    color: var(--muted);
}

.view-all {
    color: var(--olive-dark);
    background: var(--olive-light);
    padding: 7px 11px;
    border-radius: 8px;
    font-size: 11px;
    white-space: nowrap;
    transition: .2s;
}

.view-all:hover {
    background: var(--olive);
    color: white;
}

/* ================= TABLE ================= */

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

th {
    background: #f6f7f2;
    color: #65705d;
    font-size: 11px;
    padding: 13px 11px;
    text-align: right;
}

td {
    padding: 14px 11px;
    border-bottom: 1px solid #edf0e8;
    font-size: 12px;
}

tbody tr:last-child td {
    border-bottom: none;
}

tbody tr:hover {
    background: #fafbf8;
}

.person-name {
    font-weight: 700;
}

.record-number {
    color: var(--muted);
    font-size: 11px;
}

.status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 7px;
    font-size: 10px;
    font-weight: 700;
}

.status-active {
    background: #e3f1df;
    color: #477540;
}

.status-completed {
    background: #f4ead8;
    color: #8c6a32;
}

.status-other {
    background: #eceee8;
    color: #626a5c;
}

.details-link {
    display: inline-block;
    color: var(--olive-dark);
    background: var(--olive-light);
    padding: 6px 10px;
    border-radius: 7px;
    font-size: 11px;
    transition: .2s;
}

.details-link:hover {
    background: var(--olive);
    color: white;
}

.empty-state {
    text-align: center;
    color: var(--muted);
    padding: 28px 12px;
    font-size: 13px;
}

/* ================= ROLE BOX ================= */

.role-box {
    background: #f7f8f4;
    padding: 18px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.role-box small {
    display: block;
    font-size: 11px;
    color: var(--muted);
    margin-bottom: 7px;
}

.role-box strong {
    font-size: 14px;
    color: var(--olive-dark);
    overflow-wrap: anywhere;
}

/* ================= PROGRESS ================= */

.progress-label {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    font-size: 12px;
    margin-bottom: 10px;
}

.progress-track {
    height: 9px;
    background: #e8ebe3;
    border-radius: 20px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    width: <?= $activePercentage ?>%;
    background: linear-gradient(90deg,#657b49,#91a66c);
    border-radius: 20px;
    transition: width .5s;
}

/* ================= WELCOME ================= */

.welcome-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px;
    border-radius: 12px;
    background: #f7f8f4;
    margin-bottom: 12px;
}

.welcome-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: var(--olive-light);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.welcome-row h4 {
    font-size: 13px;
    margin-bottom: 4px;
}

.welcome-row p {
    font-size: 11px;
    color: var(--muted);
    line-height: 1.9;
}

/* ================= QUICK LINK ================= */

.quick-link {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    background: var(--olive-dark);
    color: white;
    padding: 12px 15px;
    border-radius: 10px;
    margin-top: 18px;
    font-size: 12px;
    transition: .2s;
}

.quick-link:hover {
    background: var(--olive);
}

/* ================= CLOCK ================= */

.clock-box {
    background: var(--olive-light);
    color: var(--olive-dark);
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 18px;
    text-align: center;
}

.clock-box strong {
    display: block;
    font-size: 20px;
}

.clock-box span {
    font-size: 11px;
}

/* ================= ERROR ================= */

.error-notice {
    padding: 13px 16px;
    background: #fff1ed;
    border: 1px solid #f0d0c8;
    color: #8b3f32;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 12px;
    line-height: 1.9;
}

/* ================= FOOTER ================= */

.footer {
    text-align: center;
    padding: 25px 10px 10px;
    font-size: 11px;
    color: var(--muted);
    line-height: 2;
}

.footer strong {
    color: var(--olive-dark);
}

.footer .copyright {
    color: var(--muted);
    direction: ltr;
    display: inline-block;
}

/* ================= RESPONSIVE ================= */

@media(max-width:1350px) {

    .stats-grid {
        grid-template-columns: repeat(2,minmax(0,1fr));
    }

}

@media(max-width:1000px) {

    .bottom-grid {
        grid-template-columns: 1fr 1fr;
        grid-template-areas:
            "latest latest"
            "analysis system";
    }

}

@media(max-width:760px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        padding: 17px;
    }

    .brand {
        padding-bottom: 15px;
    }

    .menu-title {
        padding-top: 17px;
    }

    .menu {
        display: grid;
        grid-template-columns: repeat(2,minmax(0,1fr));
        gap: 5px;
    }

    .menu li {
        margin: 0;
    }

    .menu a {
        padding: 10px;
        font-size: 12px;
    }

    .sidebar-bottom {
        margin-top: 18px;
        padding-top: 0;
    }

    .sidebar-note {
        display: none;
    }

    .main {
        margin-right: 0;
        padding: 20px 14px;
    }

    .topbar {
        align-items: flex-start;
        flex-direction: column;
        margin-bottom: 20px;
    }

    .page-heading h1 {
        font-size: 22px;
    }

    .hero {
        padding: 23px 19px;
        min-height: auto;
    }

    .hero h2 {
        font-size: 21px;
    }

    .hero p {
        font-size: 12px;
    }

    .hero-emblem {
        width: 65px;
        height: 65px;
        min-width: 65px;
        font-size: 30px;
    }

    .stats-grid {
        grid-template-columns: repeat(2,minmax(0,1fr));
        gap: 12px;
    }

    .stat-card {
        padding: 16px;
    }

    .stat-card h3 {
        font-size: 24px;
    }

    .stat-top {
        align-items: flex-start;
        flex-direction: column;
        margin-bottom: 14px;
    }

    .bottom-grid {
        grid-template-columns: 1fr;
        grid-template-areas:
            "latest"
            "analysis"
            "system";
    }

    .panel {
        padding: 16px;
    }

    .panel-header {
        flex-wrap: wrap;
    }

}

@media(max-width:380px) {

    .hero-emblem {
        display: none;
    }

    .menu a {
        gap: 6px;
        font-size: 11px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

}

/* ================= END CSS ================= */

</style>

</head>

<body>

<div class="layout">

<!-- ================= SIDEBAR ================= -->

<aside class="sidebar">

    <div class="brand">

        <div class="brand-logo">🛡️</div>

        <div>
            <h2>إدارة السجلات</h2>
            <p>PERSONNEL MANAGEMENT</p>
        </div>

    </div>

    <div class="menu-title">القائمة الرئيسية</div>

    <ul class="menu">

        <li>
            <a href="dashboard.php" class="active">
                <span class="menu-icon">▦</span>
                <span>لوحة التحكم</span>
            </a>
        </li>

        <li>
            <a href="personal.php">
                <span class="menu-icon">▤</span>
                <span>إدارة السجلات</span>
            </a>
        </li>

        <li>
            <a href="add_personal.php">
                <span class="menu-icon">➕</span>
                <span>إضافة سجل جديد</span>
            </a>
        </li>

        <!-- رابط التقارير الصحيح -->
        <li>
            <a href="reports.php">
                <span class="menu-icon">▧</span>
                <span>التقارير والطباعة</span>
            </a>
        </li>

    </ul>

    <div class="sidebar-bottom">

        <div class="sidebar-note">
            <strong>🛡️ نظام إدارة السجلات</strong>

            <p>
                نظام منظم لمتابعة السجلات والإحصائيات
                وإدارة البيانات.
            </p>
        </div>

        <a href="logout.php" class="logout">
            ↪ تسجيل الخروج
        </a>

    </div>

</aside>

<!-- ================= MAIN ================= -->

<main class="main">

<header class="topbar">

    <div class="page-heading">

        <h1>لوحة التحكم</h1>

        <p>
            مرحباً بك في نظام إدارة السجلات،
            <?= e($username) ?>
        </p>

    </div>

    <div class="user-box">

        <div class="user-avatar">👤</div>

        <div class="user-info">

            <strong><?= e($username) ?></strong>

            <span><?= e($role) ?></span>

        </div>

    </div>

</header>

<?php if ($errorMessage): ?>

    <div class="error-notice">
        تعذر جلب بعض الإحصائيات من قاعدة البيانات.
        تحقق من إعدادات قاعدة البيانات وأسماء الحقول.
    </div>

<?php endif; ?>

<!-- ================= HERO ================= -->

<section class="hero">

    <div class="hero-content">

        <span class="hero-label">🛡️ نظام الإدارة</span>

        <h2>مرحباً بك في لوحة التحكم</h2>

        <p>
            تابع إحصائيات السجلات وأحدث الإضافات ومواعيد انتهاء الخدمة
            من خلال لوحة تحكم منظمة وسهلة الاستخدام.
        </p>

    </div>

    <div class="hero-emblem">🛡️</div>

</section>

<!-- ================= STATISTICS ================= -->

<section>

    <div class="section-heading">

        <div>
            <h2>نظرة عامة</h2>
            <p>ملخص إحصائي للسجلات المسجلة بالنظام</p>
        </div>

        <span class="section-tag">
            إحصائيات السجلات
        </span>

    </div>

    <div class="stats-grid">

        <article class="stat-card">

            <div class="stat-top">
                <div class="stat-icon">▤</div>
                <span class="stat-badge">الإجمالي</span>
            </div>

            <h3><?= number_format($totalPersonnel) ?></h3>

            <p>إجمالي السجلات</p>

        </article>

        <article class="stat-card active-card">

            <div class="stat-top">
                <div class="stat-icon">✓</div>
                <span class="stat-badge">نشطة</span>
            </div>

            <h3><?= number_format($activePersonnel) ?></h3>

            <p>الأفراد الموجودون في الخدمة</p>

        </article>

        <article class="stat-card completed-card">

            <div class="stat-top">
                <div class="stat-icon">🏅</div>
                <span class="stat-badge">مكتملة</span>
            </div>

            <h3><?= number_format($completedPersonnel) ?></h3>

            <p>الأفراد الذين انتهت خدمتهم</p>

        </article>

        <article class="stat-card ending-card">

            <div class="stat-top">
                <div class="stat-icon">⏳</div>
                <span class="stat-badge">خلال 90 يومًا</span>
            </div>

            <h3><?= number_format($endingSoonPersonnel) ?></h3>

            <p>الأفراد المتوقع انتهاء خدمتهم خلال 90 يومًا</p>

        </article>

    </div>

</section>

<!-- ================= BOTTOM GRID ================= -->

<section class="bottom-grid">

    <!-- أحدث السجلات -->

    <div class="panel latest-panel">

        <div class="panel-header">

            <h3>أحدث السجلات المضافة</h3>

            <a href="personal.php" class="view-all">
                عرض جميع السجلات ←
            </a>

        </div>

        <?php if (count($latestPersonnel) > 0): ?>

            <div class="table-wrapper">

                <table>

                    <thead>
                        <tr>
                            <th>رقم السجل</th>
                            <th>اسم الفرد</th>
                            <th>بداية الخدمة</th>
                            <th>نهاية الخدمة</th>
                            <th>الحالة</th>
                            <th>التفاصيل</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($latestPersonnel as $person): ?>

                        <?php

                        $status = $person['status'] ?? '';

                        if ($status === 'active') {

                            $statusLabel = 'في الخدمة';
                            $statusClass = 'status-active';

                        } elseif ($status === 'completed') {

                            $statusLabel = 'انتهت الخدمة';
                            $statusClass = 'status-completed';

                        } else {

                            $statusLabel = $status !== ''
                                ? $status
                                : 'غير محدد';

                            $statusClass = 'status-other';

                        }

                        ?>

                        <tr>

                            <td>
                                <span class="record-number">
                                    <?= e($person['record_number']) ?>
                                </span>
                            </td>

                            <td>
                                <span class="person-name">
                                    <?= e($person['full_name']) ?>
                                </span>
                            </td>

                            <td>
                                <?= e(formatDate($person['service_start_date'])) ?>
                            </td>

                            <td>
                                <?= e(formatDate($person['expected_end_date'])) ?>
                            </td>

                            <td>
                                <span class="status <?= e($statusClass) ?>">
                                    <?= e($statusLabel) ?>
                                </span>
                            </td>

                            <td>
                                <a
                                    class="details-link"
                                    href="view_personal.php?id=<?= (int) $person['id'] ?>"
                                >
                                    عرض
                                </a>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty-state">
                لا توجد سجلات لعرضها حاليًا.
            </div>

        <?php endif; ?>

    </div>

    <!-- تحليل حالة السجلات -->

    <div class="panel analysis-panel">

        <div class="panel-header">

            <h3>تحليل حالة السجلات</h3>

            <span>STATISTICS</span>

        </div>

        <canvas
            id="personnelChart"
            aria-label="رسم بياني لحالة السجلات"
            role="img">
        </canvas>

        <div class="progress-label" style="margin-top:25px">

            <span>نسبة الأفراد الموجودين في الخدمة</span>

            <strong><?= $activePercentage ?>%</strong>

        </div>

        <div class="progress-track">

            <div class="progress-fill"></div>

        </div>

    </div>

    <!-- حالة النظام -->

    <div class="panel system-panel">

        <div class="panel-header">

            <h3>حالة النظام</h3>

            <span>SYSTEM STATUS</span>

        </div>

        <div class="clock-box">

            <strong id="liveClock">--:--:--</strong>

            <span id="liveDate">
                جارٍ تحميل التاريخ...
            </span>

        </div>

        <div class="role-box">

            <small>المستخدم الحالي</small>

            <strong><?= e($username) ?></strong>

            <br><br>

            <small>مستوى الصلاحية</small>

            <strong><?= e($role) ?></strong>

        </div>

        <div class="welcome-row">

            <div class="welcome-icon">🔐</div>

            <div>

                <h4>حماية الوصول</h4>

                <p>
                    يلزم تسجيل الدخول للوصول إلى لوحة التحكم.
                </p>

            </div>

        </div>

        <div class="welcome-row">

            <div class="welcome-icon">⏳</div>

            <div>

                <h4>متابعة انتهاء الخدمة</h4>

                <p>
                    يوجد <?= number_format($endingSoonPersonnel) ?>
                    سجلًا متوقع انتهاء خدمته خلال 90 يومًا.
                </p>

            </div>

        </div>

        <a href="add_personal.php" class="quick-link">
            ➕ إضافة سجل جديد
        </a>

    </div>

</section>

<!-- ================= FOOTER ================= -->

<footer class="footer">

    <div>
        نظام إدارة السجلات | Personnel Management System
    </div>

    <div>
        تصميم وتطوير:
        <strong>Ibrahim Ali El-Tabakh</strong>
    </div>

    <div class="copyright">
        © 2026 Ibrahim Ali El-Tabakh. All Rights Reserved.
    </div>

</footer>

</main>

</div>

<!-- ================= JAVASCRIPT ================= -->

<script>

// الساعة والتاريخ

const clockElement = document.getElementById('liveClock');
const dateElement = document.getElementById('liveDate');

function updateClock() {

    const now = new Date();

    clockElement.textContent = now.toLocaleTimeString('ar-EG', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });

    dateElement.textContent = now.toLocaleDateString('ar-EG', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

}

updateClock();

setInterval(updateClock, 1000);


// الرسم البياني

const chartElement = document.getElementById('personnelChart');

if (chartElement && typeof Chart !== 'undefined') {

    new Chart(chartElement, {

        type: 'doughnut',

        data: {

            labels: [
                'في الخدمة',
                'انتهت الخدمة',
                'حالات أخرى'
            ],

            datasets: [{

                data: [
                    <?= $activePersonnel ?>,
                    <?= $completedPersonnel ?>,
                    <?= $otherPersonnel ?>
                ],

                backgroundColor: [
                    '#667849',
                    '#c9a96e',
                    '#b9c1ae'
                ],

                borderColor: '#ffffff',
                borderWidth: 3,
                hoverOffset: 8

            }]

        },

        options: {

            responsive: true,
            maintainAspectRatio: true,
            cutout: '65%',

            plugins: {

                legend: {

                    position: 'bottom',

                    labels: {

                        usePointStyle: true,
                        padding: 18,

                        font: {
                            family: 'Cairo',
                            size: 11
                        }

                    }

                }

            }

        }

    });

}

</script>

</body>
</html>
