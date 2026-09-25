<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();

$service = strtolower(trim($_GET['service'] ?? ''));
$isHeadSscScholarship = $user['role'] === 'teacher' && (($user['head_service'] ?? '') === 'ssc_scholarship');
if ($service === '' && $isHeadSscScholarship) {
    $service = 'ssc/scholarship';
}
$serviceMap = [
    'guidance' => 'guidance',
    'library' => 'library',
    'clinic' => 'clinic',
    'ssc' => 'ssc',
    'scholarship' => 'scholarship',
    'ssc/scholarship' => 'ssc/scholarship',
    'alumni' => 'alumni'
];
$currentService = $serviceMap[$service] ?? null;
$isGuidanceService = $service === 'guidance';
$isLibraryService = $service === 'library';
$isSscScholarshipService = $service === 'ssc/scholarship' || ($service === 'ssc' && $isHeadSscScholarship);
$sscScholarshipServiceParam = $isSscScholarshipService ? 'ssc/scholarship' : 'ssc';
$scholarshipServiceParam = $isSscScholarshipService ? 'ssc/scholarship' : 'scholarship';
$dashboardHome = $user['role'] === 'teacher' ? 'teacher_home.php' : 'student_home.php';
$portalLabel = $user['role'] === 'teacher' ? 'Teacher Portal' : 'Student Portal';
$dashboardClass = $user['role'] === 'teacher' ? 'teacher-dashboard' : 'student-dashboard';

$displayCourse = $user['course'] ?? $user['course_year'] ?? 'Course';
$displayYear = $user['year_level'] ?? null;
if (!$displayYear && !empty($user['course_year'])) {
    $parts = explode(' ', trim($user['course_year']));
    $lastPart = end($parts);
    if (in_array($lastPart, ['1', '2', '3', '4'], true)) {
        $displayYear = $lastPart;
        array_pop($parts);
        $displayCourse = implode(' ', $parts) ?: $displayCourse;
    }
}
if (!$displayYear) {
    $displayYear = 'N/A';
}
$topbarInfo = build_user_dashboard_header_info($user);

$aboutVision = trim((string)get_library_setting('about_vision', 'To become a leading Higher Educational Institution committed to a holistic and transformative community through learning dedicated to academic excellence, leadership, and values formation.'));
$aboutMission = trim((string)get_library_setting('about_mission', 'Empower every student with accessible learning resources, strong guidance, and a safe environment that encourages growth, innovation, and global competitiveness.'));
$aboutCoreValuesRaw = json_decode(get_library_setting('about_core_values', '[]'), true);
$aboutCoreValues = [];
if (is_array($aboutCoreValuesRaw) && !empty($aboutCoreValuesRaw)) {
    foreach ($aboutCoreValuesRaw as $item) {
        if (is_array($item)) {
            $value = trim((string)($item['value'] ?? $item['label'] ?? $item['title'] ?? ''));
            $description = trim((string)($item['description'] ?? ''));
        } else {
            $value = trim((string)$item);
            $description = '';
        }
        if ($value !== '') {
            $aboutCoreValues[] = ['title' => $value, 'description' => $description];
        }
    }
}
if ($aboutCoreValues === []) {
    $aboutCoreValues = [
        ['title' => 'Integrity', 'description' => 'Honesty and responsibility form the moral foundation of the PASSian community.'],
        ['title' => 'Nationalism', 'description' => 'PASSian Education fosters loyalty, compassion, and empowered citizenship.'],
        ['title' => 'Innovative', 'description' => 'We champion modern, collaborative learning and interdisciplinary thinking.'],
    ];
}

$aboutProgramsRaw = json_decode(get_library_setting('about_programs', '[]'), true);
$aboutPrograms = [];
if (is_array($aboutProgramsRaw) && !empty($aboutProgramsRaw)) {
    foreach ($aboutProgramsRaw as $item) {
        if (!is_array($item)) {
            continue;
        }
        $title = trim((string)($item['title'] ?? $item['name'] ?? ''));
        $description = trim((string)($item['description'] ?? ''));
        $color = trim((string)($item['color'] ?? ''));
        if ($title === '' && $description === '' && $color === '') {
            continue;
        }
        $aboutPrograms[] = ['title' => $title, 'description' => $description, 'color' => $color];
    }
}
if ($aboutPrograms === []) {
    $aboutPrograms = [
        ['title' => 'BS Accountancy', 'description' => 'Builds strong foundations in accounting, auditing, and financial decision-making.', 'color' => '#8B2E2F'],
        ['title' => 'BS Computer Science', 'description' => 'Prepares students for software, systems, and technology-driven problem solving.', 'color' => '#2e8b57'],
        ['title' => 'BS Business Administration', 'description' => 'Develops leadership and management skills for the modern business world.', 'color' => '#3b82f6'],
        ['title' => 'Bachelor in Elementary Education', 'description' => 'Shapes future educators with a strong foundation in teaching and child development.', 'color' => '#fbbf24'],
        ['title' => 'BS Criminology', 'description' => 'Equips learners with knowledge in law enforcement, justice, and public safety.', 'color' => '#60a5fa'],
        ['title' => 'BS Hospitality Management', 'description' => 'Focused on service excellence, operations, and guest experience in hospitality.', 'color' => '#8b5cf6'],
        ['title' => 'BS Tourism Management', 'description' => 'Offers insights into travel, tourism services, and destination management.', 'color' => '#ec4899'],
        ['title' => 'Associate in Computer Technology', 'description' => 'Provides practical knowledge for entry-level roles in computing and technical support.', 'color' => '#2e8b57'],
    ];
}

$aboutPeopleRaw = json_decode(get_library_setting('about_people', '[]'), true);
$aboutPeople = [];
if (is_array($aboutPeopleRaw) && !empty($aboutPeopleRaw)) {
    foreach ($aboutPeopleRaw as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = trim((string)($item['name'] ?? ''));
        $category = trim((string)($item['category'] ?? ''));
        $image = trim((string)($item['image'] ?? ''));
        if ($name === '' && $category === '' && $image === '') {
            continue;
        }
        $aboutPeople[] = ['name' => $name, 'category' => $category, 'image' => $image];
    }
}
if ($aboutPeople === []) {
    $aboutPeople = [
        ['name' => 'Maria Santos', 'category' => 'Administration', 'image' => ''],
        ['name' => 'Rina Dela Cruz', 'category' => 'Administration', 'image' => ''],
        ['name' => 'John Cruz', 'category' => 'Support Services', 'image' => ''],
        ['name' => 'Ana Lim', 'category' => 'Support Services', 'image' => ''],
        ['name' => 'Paul Torres', 'category' => 'Support Services', 'image' => ''],
        ['name' => 'Sophia Kim', 'category' => 'Student Engagement', 'image' => ''],
        ['name' => 'Ben Tan', 'category' => 'Student Engagement', 'image' => ''],
        ['name' => 'Chris Toledo', 'category' => 'Operations', 'image' => ''],
    ];
}

$aboutPeopleByCategory = [];
foreach ($aboutPeople as $person) {
    $category = trim((string)($person['category'] ?? 'General'));
    if ($category === '') {
        $category = 'General';
    }
    $aboutPeopleByCategory[$category][] = $person;
}

if ($user['role'] !== 'student' && $user['role'] !== 'teacher') {
    header('Location: ../auth/student_login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        @font-face {
            font-family: 'ElephantLocal';
            src: url('../assets/FONTS/ELEPHNT.TTF') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        .nav-brand h2 {
            font-family: 'ElephantLocal', 'Playfair Display', serif;
        }
    </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About PASS College | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        /* =========================================================
           About page — redesigned layout (same color scheme)
           Uses the site's blue/white palette + soft shadows.
           ========================================================= */

        :root {
            --about-primary: #800000;
            --about-primary-dark: #5C1F23;
            --about-primary-mid: #8B2E2F;
            --about-primary-soft: #A84A38;
            --about-accent: #D4AF37;
            --about-accent-dark: #B07A2B;
            --about-accent-soft: #E0B250;
            --about-surface: #FAF7F0;
            --about-surface-soft: #FBF4E4;
            --about-ink: #2f211c;
            --about-muted: #6f5548;
            --about-line: #e8dbc9;
            --about-card-shadow: 0 10px 30px rgba(92,31,35,0.10);
            --about-radius: 16px;
        }

        [data-animate] { opacity: 0; transform: translateY(14px); transition: opacity .55s ease, transform .55s cubic-bezier(.2,.9,.2,1); }
        [data-animate].in-view { opacity: 1; transform: translateY(0); }

        .about-page .content-panel { padding: 0; background: transparent; }

        /* ---------- HERO ---------- */
        .about-hero-new {
            position: relative;
            border-radius: var(--about-radius);
            overflow: hidden;
            background:
                radial-gradient(1200px 400px at -10% -20%, rgba(212,175,55,.22), transparent 60%),
                radial-gradient(900px 350px at 110% 120%, rgba(128,0,0,.15), transparent 55%),
                linear-gradient(135deg, var(--about-surface) 0%, var(--about-surface-soft) 100%);
            padding: 44px 40px;
            border: 1px solid var(--about-line);
            box-shadow: var(--about-card-shadow);
        }
        .about-hero-new .hero-grid {
            display: grid;
            grid-template-columns: 1.15fr .85fr;
            gap: 36px;
            align-items: center;
        }
        .about-hero-new .eyebrow {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: .78rem; font-weight: 700; letter-spacing: .12em;
            text-transform: uppercase; color: var(--about-primary);
            background: var(--about-surface); padding: 6px 12px; border-radius: 999px;
            border: 1px solid var(--about-line);
        }
        .about-hero-new h1 {
            font-size: clamp(1.7rem, 2.8vw, 2.6rem);
            line-height: 1.15; margin: 14px 0 12px;
            color: var(--about-ink); letter-spacing: -0.01em;
        }
        .about-hero-new .lead { color: var(--about-muted); font-size: 1.02rem; max-width: 56ch; }
        .about-hero-new .hero-cta { margin-top: 22px; display:flex; flex-wrap: wrap; gap: 10px; }
        .about-hero-new .btn-solid {
            background: linear-gradient(135deg, var(--about-primary-dark), var(--about-primary-soft)); color: var(--about-surface); padding: 11px 18px;
            border-radius: 10px; text-decoration:none; font-weight:600;
            transition: transform .2s ease, box-shadow .2s ease;
            box-shadow: 0 8px 20px rgba(92,31,35,.22);
        }
        .about-hero-new .btn-solid:hover { transform: translateY(-2px); }
        .about-hero-new .btn-ghost {
            background: var(--about-surface); color: var(--about-primary); padding: 11px 18px;
            border-radius: 10px; text-decoration:none; font-weight:600;
            border: 1px solid var(--about-line);
        }
        .about-hero-new .badge-row { margin-top: 22px; display:flex; flex-wrap:wrap; gap: 10px; }
        .about-hero-new .badge-row span {
            background: var(--about-surface); color: var(--about-primary); font-weight:600; font-size:.82rem;
            padding: 8px 12px; border-radius: 999px; border:1px solid var(--about-line);
        }

        .hero-visual {
            background: var(--about-surface); border-radius: 20px; padding: 26px;
            border: 1px solid var(--about-line); box-shadow: var(--about-card-shadow);
            display: grid; gap: 14px;
        }
        .hero-visual .hv-logo {
            display:flex; align-items:center; gap:12px;
            padding-bottom: 14px; border-bottom: 1px dashed var(--about-line);
        }
        .hero-visual .hv-logo img { width: 46px; height: 46px; object-fit: contain; }
        .hero-visual .hv-logo strong { color: var(--about-ink); font-size: 1rem; }
        .hero-visual .hv-logo small { color: var(--about-muted); display:block; font-size:.8rem; }
        .hero-stats { display:grid; grid-template-columns: repeat(3,1fr); gap: 10px; }
        .hero-stats .stat {
            text-align:center; background: var(--about-accent); border-radius: 12px; padding: 12px 8px;
        }
        .hero-stats .stat b { display:block; color: var(--about-primary); font-size: 1.35rem; }
        .hero-stats .stat span { font-size: .72rem; color: var(--about-muted); font-weight: 600; letter-spacing:.04em; text-transform:uppercase; }

        /* ---------- VISION / MISSION SPLIT ---------- */
        .vm-split { display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 22px; }
        .vm-card {
            background: var(--about-surface); border-radius: var(--about-radius); padding: 24px;
            border: 1px solid var(--about-line); box-shadow: var(--about-card-shadow); position:relative;
        }
        .vm-card .vm-icon {
            width: 42px; height: 42px; border-radius: 10px;
            display:grid; place-items:center; background: var(--about-surface-soft); color: var(--about-primary);
            font-size: 1.1rem; margin-bottom: 12px;
        }
        .vm-card h3 { margin: 0 0 8px; color: var(--about-ink); }
        .vm-card p { color: var(--about-muted); line-height:1.55; margin:0; }
        .vm-card.accent { background: linear-gradient(140deg, var(--about-primary-dark), var(--about-primary-soft)); color: var(--about-surface); }
        .vm-card.accent h3, .vm-card.accent p { color: var(--about-surface); }
        .vm-card.accent .vm-icon { background: rgba(255,255,255,.15); color: var(--about-surface); }

        /* ---------- SECTION HEADS ---------- */
        .sec-head { margin: 36px 0 16px; }
        .sec-head .eyebrow {
            font-size:.72rem; letter-spacing:.14em; text-transform:uppercase;
            color: var(--about-primary); font-weight:700;
        }
        .sec-head h2 { font-size: 1.6rem; margin: 6px 0 0; color: var(--about-ink); letter-spacing:-.01em; }
        .sec-head p { color: var(--about-muted); margin: 6px 0 0; max-width: 70ch; }

        /* ---------- SUPPORT CARDS (Bento) ---------- */
        .support-bento {
            display:grid; gap: 16px;
            grid-template-columns: 1.4fr 1fr 1fr;
            grid-template-rows: auto auto;
        }
        .support-bento .b {
            background: var(--about-surface); border:1px solid var(--about-line); border-radius: var(--about-radius);
            padding: 22px; box-shadow: var(--about-card-shadow); transition: transform .25s ease, box-shadow .25s ease;
        }
        .support-bento .b:hover { transform: translateY(-3px); box-shadow: 0 14px 34px rgba(92,31,35,.12); }
        .support-bento .b i { color: var(--about-primary); font-size: 1.3rem; }
        .support-bento .b h3 { margin: 10px 0 6px; color: var(--about-ink); font-size: 1.05rem; }
        .support-bento .b p { margin:0; color: var(--about-muted); font-size:.92rem; line-height:1.5; }
        .support-bento .b-lead {
            grid-row: span 2;
            background: linear-gradient(160deg, var(--about-primary-dark), var(--about-primary-soft));
            color: var(--about-surface); display:flex; flex-direction:column; justify-content:space-between;
        }
        .support-bento .b-lead h3, .support-bento .b-lead p { color: var(--about-surface); }
        .support-bento .b-lead .tag {
            display:inline-block; background:rgba(255,255,255,.15); padding:6px 10px; border-radius:999px;
            font-size:.75rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase;
        }

        /* ---------- TIMELINE (horizontal on desktop) ---------- */
        .timeline-wrap { background: var(--about-surface); border:1px solid var(--about-line); border-radius: var(--about-radius); padding: 28px; box-shadow: var(--about-card-shadow); }
        .timeline-h { display:grid; grid-template-columns: repeat(4,1fr); gap: 18px; position:relative; }
        .timeline-h::before {
            content:""; position:absolute; top: 22px; left: 30px; right: 30px;
            height: 2px; background: linear-gradient(90deg, var(--about-primary), var(--about-primary-soft));
            opacity:.35;
        }
        .tl-item { position:relative; }
        .tl-dot {
            width: 44px; height:44px; border-radius:50%;
            background: linear-gradient(135deg, var(--about-accent), var(--about-accent-dark)); color: var(--about-surface); display:grid; place-items:center;
            font-weight:800; font-size:.85rem; box-shadow: 0 6px 18px rgba(128,0,0,.22);
            position:relative; z-index:1; border: 4px solid var(--about-surface);
        }
        .tl-item h4 { margin: 12px 0 4px; color: var(--about-ink); font-size:.98rem; }
        .tl-item p { margin:0; color: var(--about-muted); font-size:.86rem; line-height:1.5; }

        .history-story {
            margin-top: 20px; background: var(--about-surface-soft);
            border-radius: var(--about-radius); padding: 24px; border: 1px solid var(--about-line);
        }
        .history-story h3 { margin-top: 0; color: var(--about-ink); }
        .history-story h4 { color: var(--about-primary); margin-top: 16px; }
        .history-story p { color: #334155; line-height: 1.65; }

        /* ---------- VALUES ---------- */
        .values-grid-new { display:grid; grid-template-columns: repeat(3,1fr); gap: 18px; }
        .value-card {
            background: var(--about-surface); border-radius: var(--about-radius); padding: 24px;
            border:1px solid var(--about-line); box-shadow: var(--about-card-shadow);
            position: relative; overflow:hidden;
        }
        .value-card::before {
            content:""; position:absolute; top:0; left:0; right:0; height:4px;
            background: linear-gradient(90deg, var(--about-accent), var(--about-primary-soft));
        }
        .value-card i { color: var(--about-primary); font-size: 1.5rem; margin-bottom: 10px; display:block; }
        .value-card h3 { margin: 6px 0 6px; color: var(--about-ink); }
        .value-card p { margin: 0; color: var(--about-muted); line-height:1.55; }

        /* ---------- PROGRAMS ---------- */
        .programs-new { display:grid; grid-template-columns: repeat(4,1fr); gap: 14px; }
        .prog {
            background: var(--about-surface); border:1px solid var(--about-line); border-radius: 14px;
            padding: 18px 16px; text-align:left;
            transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
            display:flex; align-items:center; gap: 12px;
        }
        .prog-group-accountancy { position: relative; overflow: hidden; }
        .prog-group-tech { position: relative; overflow: hidden; }
        .prog-group-business { position: relative; overflow: hidden; }
        .prog-group-crim { position: relative; overflow: hidden; }
        .prog-group-hospitality { position: relative; overflow: hidden; }
        .prog-group-tourism { position: relative; overflow: hidden; }
        .prog-group-education { position: relative; overflow: hidden; }
        .prog-group-accountancy::after,
        .prog-group-tech::after,
        .prog-group-business::after,
        .prog-group-crim::after,
        .prog-group-hospitality::after,
        .prog-group-tourism::after,
        .prog-group-education::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 4px;
        }
        .prog-group-accountancy::after { background: #8B2E2F; }
        .prog-group-tech::after { background: #2e8b57; }
        .prog-group-business::after { background: #3b82f6; }
        .prog-group-crim::after { background: #60a5fa; }
        .prog-group-hospitality::after { background: #8b5cf6; }
        .prog-group-tourism::after { background: #ec4899; }
        .prog-group-education::after { background: #fbbf24; }
        .prog-group-accountancy .p-icon { background: rgba(139,46,47,.12); color: #8B2E2F; }
        .prog-group-tech .p-icon { background: rgba(46,139,87,.12); color: #2e8b57; }
        .prog-group-business .p-icon { background: rgba(59,130,246,.12); color: #3b82f6; }
        .prog-group-crim .p-icon { background: rgba(96,165,250,.12); color: #60a5fa; }
        .prog-group-hospitality .p-icon { background: rgba(139,92,246,.12); color: #8b5cf6; }
        .prog-group-tourism .p-icon { background: rgba(236,72,153,.12); color: #ec4899; }
        .prog-group-education .p-icon { background: rgba(251,191,36,.16); color: #b45309; }

        /* ---------- EMPLOYEE CHART ---------- */
        .employee-chart-section {
            margin-top: 34px;
        }
        .employee-chart {
            display:grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }
        .employee-chart-card {
            background: var(--about-surface);
            border: 1px solid var(--about-line);
            border-radius: var(--about-radius);
            padding: 20px;
            box-shadow: var(--about-card-shadow);
        }
        .employee-chart-card h3 {
            margin: 0 0 14px;
            color: var(--about-primary);
            font-size: 1rem;
            text-align: center;
        }
        .employee-list {
            display:grid;
            gap: 12px;
        }
        .employee-item {
            display:flex;
            flex-direction: column;
            align-items:center;
            justify-content:center;
            text-align:center;
            padding: 16px 12px;
            background: var(--about-surface-soft);
            border: 1px solid var(--about-line);
            border-radius: 14px;
            min-height: 150px;
        }
        .employee-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--about-accent), var(--about-primary-soft));
            color: var(--about-surface);
            display:grid;
            place-items:center;
            font-weight: 700;
            font-size: 1.1rem;
            flex-shrink: 0;
            margin-bottom: 10px;
            box-shadow: 0 6px 16px rgba(128,0,0,.2);
        }
        .employee-item strong {
            display:block;
            color: var(--about-ink);
            font-size: 0.95rem;
            margin-bottom: 4px;
        }
        .employee-item small {
            color: var(--about-muted);
            display:block;
            line-height: 1.4;
        }
        .prog:hover { transform: translateY(-3px); border-color: var(--about-primary-soft); box-shadow: 0 10px 24px rgba(92,31,35,.12); }
        .prog .p-icon {
            width: 42px; height:42px; border-radius: 10px;
            background: var(--about-surface-soft); color: var(--about-primary);
            display:grid; place-items:center; font-size: 1.05rem; flex-shrink:0;
        }
        .prog h4 { margin:0; color: var(--about-ink); font-size:.92rem; font-weight:600; line-height:1.3; }
        .prog .prog-desc {
            margin: 8px 0 0;
            color: var(--about-muted);
            font-size: .82rem;
            line-height: 1.45;
        }

        /* ---------- RESPONSIVE ---------- */
        @media (max-width: 1024px) {
            .about-hero-new .hero-grid { grid-template-columns: 1fr; }
            .support-bento { grid-template-columns: 1fr 1fr; }
            .support-bento .b-lead { grid-row: auto; grid-column: 1 / -1; }
            .timeline-h { grid-template-columns: 1fr 1fr; }
            .timeline-h::before { display:none; }
            .programs-new { grid-template-columns: repeat(3,1fr); }
            .employee-chart { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 720px) {
            .about-hero-new { padding: 28px 22px; }
            .vm-split { grid-template-columns: 1fr; }
            .support-bento { grid-template-columns: 1fr; }
            .timeline-h { grid-template-columns: 1fr; }
            .values-grid-new { grid-template-columns: 1fr; }
            .programs-new { grid-template-columns: 1fr 1fr; }
            .hero-stats { grid-template-columns: repeat(3,1fr); }
            .employee-chart { grid-template-columns: 1fr; }
        }

        @keyframes spin-refresh {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        /* Swipe-to-Refresh for Mobile (320px to 768px) */
        @media (max-width: 768px) {
            body.swipe-refresh-enabled {
                touch-action: pan-x pan-y;
            }
        }
    </style>
</head>
<body class="<?= $dashboardClass ?> about-page">
    <!-- Swipe-to-Refresh Spinner (Mobile Only) -->
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); z-index: 9999; justify-content: center; align-items: center;">
        <div style="text-align: center;">
            <div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div>
            <p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0;">Refreshing...</p>
        </div>
    </div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-mobile-header">
                <div class="mobile-user-info">
                    <h4><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p><?= htmlspecialchars($topbarInfo['displayMeta']) ?></p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar" <?= $currentService ? 'onclick="toggleGuidanceSidebar()"' : '' ?>>
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
            <div class="nav-section">
                <?php if ($currentService === 'guidance'): ?>
                    <a href="guidance_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=guidance" data-tooltip="Announcements">
                        <span class="nav-icon"><i class="fa-solid fa-bell"></i></span>
                        <span class="nav-text">Announcements</span>
                    </a>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Services">
                            <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                            <span class="nav-text">Services</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="guidance_dashboard.php?service=guidance" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php?service=guidance" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php?service=guidance" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc/ssc_dashboard.php?service=guidance" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship/scholarship_dashboard.php?service=guidance" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="ssaa_student_home.php?service=guidance" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
                            </a>
                        </div>
                    </div>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage Guidance">
                            <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                            <span class="nav-text">Manage Guidance</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="case_management.php" data-tooltip="Case Management">
                                <span class="nav-icon"><i class="fa-solid fa-file-circle-plus"></i></span>
                                <span class="nav-text">Case Management</span>
                            </a>
                            <a href="good_moral.php" data-tooltip="Good Moral">
                                <span class="nav-icon"><i class="fa-solid fa-certificate"></i></span>
                                <span class="nav-text">Good Moral</span>
                            </a>
                            <a href="reports.php" data-tooltip="Reports">
                                <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                                <span class="nav-text">Reports</span>
                            </a>
                        </div>
                    </div>
                    <a href="profile.php?service=guidance" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php?service=guidance" class="active" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($currentService === 'library'): ?>
                    <a href="librarian_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=library" data-tooltip="Announcements">
                        <span class="nav-icon"><i class="fa-solid fa-bell"></i></span>
                        <span class="nav-text">Announcements</span>
                    </a>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Services">
                            <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                            <span class="nav-text">Services</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="guidance_dashboard.php?service=guidance" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php?service=library" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php?service=library" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc/ssc_dashboard.php?service=library" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship/scholarship_dashboard.php?service=library" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="ssaa_student_home.php?service=library" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
                            </a>
                        </div>
                    </div>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage">
                            <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                            <span class="nav-text">Manage</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="../library_checkin.php" data-tooltip="QR Check-In">
                                <span class="nav-icon"><i class="fa-solid fa-qrcode"></i></span>
                                <span class="nav-text">QR Check-In</span>
                            </a>
                            <a href="../library_catalog.php" data-tooltip="Digital Catalog">
                                <span class="nav-icon"><i class="fa-solid fa-book-open"></i></span>
                                <span class="nav-text">Digital Catalog</span>
                            </a>
                            <a href="../library_inventory.php" data-tooltip="Inventory">
                                <span class="nav-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
                                <span class="nav-text">Inventory</span>
                            </a>
                            <a href="../library_reports.php" data-tooltip="Reports">
                                <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                                <span class="nav-text">Reports</span>
                            </a>
                            <a href="../library_settings.php" data-tooltip="Settings">
                                <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
                                <span class="nav-text">Settings</span>
                            </a>
                            <a href="../library_qr.php" data-tooltip="Library QR">
                                <span class="nav-icon"><i class="fa-solid fa-code"></i></span>
                                <span class="nav-text">Library QR</span>
                            </a>
                        </div>
                    </div>
                    <a href="profile.php?service=library" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php?service=library" class="active" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($currentService === 'clinic'): ?>
                    <a href="nurse_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=clinic" data-tooltip="Announcements">
                        <span class="nav-icon"><i class="fa-solid fa-bell"></i></span>
                        <span class="nav-text">Announcements</span>
                    </a>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Services">
                            <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                            <span class="nav-text">Services</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="guidance_dashboard.php?service=clinic" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php?service=clinic" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php?service=clinic" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc/ssc_dashboard.php?service=clinic" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship/scholarship_dashboard.php?service=clinic" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="ssaa_student_home.php?service=clinic" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
                            </a>
                        </div>
                    </div>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                            <span class="nav-text">Manage Clinic</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="clinic_status.php" data-tooltip="Clinic Status">
                                <span class="nav-icon"><i class="fa-solid fa-clock"></i></span>
                                <span class="nav-text">Clinic Status</span>
                            </a>
                            <a href="clinic_health_records.php" data-tooltip="Health Records">
                                <span class="nav-icon"><i class="fa-solid fa-heart-pulse"></i></span>
                                <span class="nav-text">Health Records</span>
                            </a>
                            <a href="clinic_medicine_inventory.php" data-tooltip="Medicine Inventory">
                                <span class="nav-icon"><i class="fa-solid fa-pills"></i></span>
                                <span class="nav-text">Medicine Inventory</span>
                            </a>
                            <a href="clinic_visit_logs.php" data-tooltip="Visit Logs">
                                <span class="nav-icon"><i class="fa-solid fa-notes-medical"></i></span>
                                <span class="nav-text">Visit Logs</span>
                            </a>
                            <a href="clinic_announcements.php" data-tooltip="Announcements">
                                <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                                <span class="nav-text">Announcements</span>
                            </a>
                            <a href="clinic_analytics.php" data-tooltip="Analytics">
                                <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                                <span class="nav-text">Analytics</span>
                            </a>
                        </div>
                    </div>
                    <a href="profile.php?service=clinic" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php?service=clinic" class="active" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($currentService === 'ssc' || $isSscScholarshipService): ?>
                    <a href="ssc_head_home.php?service=ssc/scholarship" class="active" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=ssc/scholarship" data-tooltip="Announcements">
                        <span class="nav-icon"><i class="fa-solid fa-bell"></i></span>
                        <span class="nav-text">Announcements</span>
                    </a>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Services">
                            <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                            <span class="nav-text">Services</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="guidance_dashboard.php?service=ssc/scholarship" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php?service=ssc/scholarship" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php?service=ssc/scholarship" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc_head_home.php?service=<?= htmlspecialchars($sscScholarshipServiceParam) ?>" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship_dashboard.php?service=<?= htmlspecialchars($scholarshipServiceParam) ?>" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="ssaa_student_home.php?service=ssc/scholarship" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
                            </a>
                        </div>
                    </div>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage SSC">
                            <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                            <span class="nav-text">Manage SSC</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="ssc_manage_events.php" data-tooltip="SSC Events">
                                <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span>
                                <span class="nav-text">SSC Events</span>
                            </a>
                            <a href="ssc_manage_candidates.php" data-tooltip="Candidates">
                                <span class="nav-icon"><i class="fa-solid fa-user-group"></i></span>
                                <span class="nav-text">Candidates</span>
                            </a>
                            <a href="ssc_reports.php" data-tooltip="Reports & Analytics">
                                <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                                <span class="nav-text">Reports & Analytics</span>
                            </a>
                        </div>
                    </div>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage Scholarship">
                            <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                            <span class="nav-text">Manage Scholarship</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="scholarship_create_announcement.php" data-tooltip="Scholarship Announcements">
                                <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                                <span class="nav-text">Scholarship Announcements</span>
                            </a>
                            <a href="scholarship_reports.php" data-tooltip="Reports & Analytics">
                                <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                                <span class="nav-text">Reports & Analytics</span>
                            </a>
                        </div>
                    </div>
                    <a href="profile.php?service=<?= htmlspecialchars($sscScholarshipServiceParam) ?>" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php?service=<?= htmlspecialchars($sscScholarshipServiceParam) ?>" class="active" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($currentService === 'scholarship'): ?>
                    <a href="ssc_head_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=scholarship" data-tooltip="Announcements">
                        <span class="nav-icon"><i class="fa-solid fa-bell"></i></span>
                        <span class="nav-text">Announcements</span>
                    </a>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Services">
                            <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                            <span class="nav-text">Services</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="guidance_dashboard.php?service=guidance" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php?service=library" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php?service=clinic" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc_head_home.php?service=ssc" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship_dashboard.php?service=scholarship" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="ssaa_student_home.php?service=ssc/scholarship" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
                            </a>
                        </div>
                    </div>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage Scholarship">
                            <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                            <span class="nav-text">Manage Scholarship</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="scholarship/scholarship_create_announcement.php" data-tooltip="Scholarship Announcements">
                                <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                                <span class="nav-text">Scholarship Announcements</span>
                            </a>
                            <a href="scholarship/scholarship_reports.php" data-tooltip="Reports & Analytics">
                                <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                                <span class="nav-text">Reports & Analytics</span>
                            </a>
                        </div>
                    </div>
                    <a href="profile.php?service=scholarship" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php?service=scholarship" class="active" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($currentService === 'alumni'): ?>
                    <a href="ssaa_student_home.php?service=alumni" class="active" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=alumni" data-tooltip="Announcements">
                        <span class="nav-icon"><i class="fa-solid fa-bell"></i></span>
                        <span class="nav-text">Announcements</span>
                    </a>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Services">
                            <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                            <span class="nav-text">Services</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="guidance_dashboard.php?service=guidance" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php?service=library" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php?service=clinic" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc_head_home.php?service=ssc" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship/scholarship_dashboard.php?service=scholarship" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="ssaa_student_home.php?service=alumni" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
                            </a>
                        </div>
                    </div>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage Alumni">
                            <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                            <span class="nav-text">Manage Alumni</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="alumni_tracer_form.php" data-tooltip="Alumni Tracer">
                                <span class="nav-icon"><i class="fa-solid fa-file-alt"></i></span>
                                <span class="nav-text">Alumni Tracer</span>
                            </a>
                            <a href="check_latest_books.php" data-tooltip="Alumni Directory">
                                <span class="nav-icon"><i class="fa-solid fa-address-book"></i></span>
                                <span class="nav-text">Alumni Directory</span>
                            </a>
                            <a href="check_resources.php" data-tooltip="Alumni Programs">
                                <span class="nav-icon"><i class="fa-solid fa-graduation-cap"></i></span>
                                <span class="nav-text">Alumni Programs</span>
                            </a>
                        </div>
                    </div>
                    <a href="profile.php?service=alumni" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php?service=alumni" class="active" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php else: ?>
                    <a href="<?= $dashboardHome ?>" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php" data-tooltip="Announcements">
                        <span class="nav-icon"><i class="fa-solid fa-bell"></i></span>
                        <span class="nav-text">Announcements</span>
                    </a>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Services">
                            <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                            <span class="nav-text">Services</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="guidance_home.php" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc/ssc_dashboard.php" data-tooltip="Supreme Student Council">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship/scholarship_dashboard.php" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="ssaa_student_home.php" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
                            </a>
                        </div>
                    </div>
                    <a href="profile.php" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php" class="active" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php endif; ?>
            </div>
            <div class="nav-footer">
                <a href="../logout.php" class="logout-link" data-tooltip="Logout">
                    <span class="nav-icon"><i class="fa-solid fa-sign-out-alt"></i></span>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
        </aside>
        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle mobile menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p><?= htmlspecialchars($portalLabel) ?></p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta"><?= htmlspecialchars($user['course'] ?? ($user['course_year'] ?? 'Course')) ?></span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Profile" data-tooltip="Profile" data-menu-target="profileMenu"><i class="fa-solid fa-user"></i></button>

                    <div class="topbar-menu" id="notificationMenu" role="menu" aria-label="Notifications menu">
                        <div class="menu-header">
                            <strong>Notifications</strong>
                            <span class="menu-note">Latest announcements</span>
                        </div>
                        <div class="menu-empty">No new announcements.</div>
                        <a class="menu-link menu-footer-link" href="clinic_dashboard.php#announcements">View all announcements</a>
                    </div>

                    <div class="topbar-menu" id="profileMenu" role="menu" aria-label="Profile menu">
                        <div class="menu-item profile-menu-item" role="menuitem">
                            <a href="profile.php" class="menu-link profile-link"><?= htmlspecialchars($user['full_name']) ?></a>
                            <span class="menu-subtext">View your account details</span>
                        </div>
                        <a href="profile.php" class="menu-action">Open profile</a>
                    </div>

                    <div class="topbar-menu" id="supportMenu" role="menu" aria-label="Support menu">
                        <div class="menu-header">
                            <strong>Support</strong>
                            <span class="menu-note">Need help?</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="about.php">Help center</a>
                            <span class="menu-subtext">View support resources and FAQs.</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="mailto:support@passcollege.edu">Email support</a>
                        </div>
                    </div>
                </div>
            </header>
            <div class="main-scroll">
                <div class="content-panel about-page">

                    <!-- ============ HERO ============ -->
                    <section class="about-hero-new" data-animate>
                        <div class="hero-grid">
                            <div>
                                <span class="eyebrow"><i class="fa-solid fa-graduation-cap"></i> About PASS College</span>
                                <h1>Premium yet affordable education for a values-driven future.</h1>
                                <p class="lead">A student-centered institution in Alaminos City, built on academic excellence, supportive services, and proven outcomes for learners across Western Pangasinan.</p>
                                <div class="hero-cta">
                                    <a href="#programs" class="btn-solid"><i class="fa-solid fa-compass"></i> &nbsp;Explore Programs</a>
                                    <a href="#history" class="btn-ghost"><i class="fa-solid fa-clock-rotate-left"></i> &nbsp;Our History</a>
                                </div>
                                <div class="badge-row">
                                    <span><i class="fa-solid fa-trophy"></i> &nbsp;#1 CPA Producer — Western Pangasinan</span>
                                    <span><i class="fa-solid fa-shield-halved"></i> &nbsp;Values-Driven</span>
                                </div>
                            </div>
                            <aside class="hero-visual">
                                <div class="hv-logo">
                                    <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS College logo">
                                    <div>
                                        <strong>PASS College</strong>
                                        <small>Alaminos City, Pangasinan</small>
                                    </div>
                                </div>
                                <div class="hero-stats">
                                    <div class="stat"><b>28+</b><span>Years</span></div>
                                    <div class="stat"><b>11</b><span>Programs</span></div>
                                    <div class="stat"><b>4</b><span>Services</span></div>
                                </div>
                                <p style="margin:6px 0 0; color:var(--about-muted); font-size:.9rem; line-height:1.55;">
                                    Delivering quality, well-rounded education since 1997 — from ladderized programs to modern degrees.
                                </p>
                            </aside>
                        </div>
                    </section>

                    <!-- ============ VISION / MISSION ============ -->
                    <div class="vm-split" data-animate>
                        <article class="vm-card">
                            <div class="vm-icon"><i class="fa-solid fa-eye"></i></div>
                            <h3>Our Vision</h3>
                            <p><?= nl2br(htmlspecialchars($aboutVision)) ?></p>
                        </article>
                        <article class="vm-card accent">
                            <div class="vm-icon"><i class="fa-solid fa-bullseye"></i></div>
                            <h3>Our Mission</h3>
                            <p><?= nl2br(htmlspecialchars($aboutMission)) ?></p>
                        </article>
                    </div>

                    <!-- ============ SUPPORT STRUCTURE (BENTO) ============ -->
                    <div class="sec-head" data-animate>
                        <p class="eyebrow">Support Structure</p>
                        <h2>Built for student success</h2>
                        <p>Integrated services across leadership, learning, health, and student life.</p>
                    </div>
                    <section class="support-bento" data-animate>
                        <div class="b b-lead">
                            <div>
                                <span class="tag">Leadership</span>
                                <h3 style="margin-top:14px; font-size:1.25rem;">PASS College Leadership</h3>
                                <p>Guiding campus strategy, academic quality, and student-focused operations across every department.</p>
                            </div>
                            <p style="opacity:.85; font-size:.85rem;">Library · Clinic · SSC · Guidance</p>
                        </div>
                        <div class="b"><i class="fa-solid fa-book"></i><h3>Library Services</h3><p>Research support, digital access, and learning resources for every student.</p></div>
                        <div class="b"><i class="fa-solid fa-heart-pulse"></i><h3>Clinic Services</h3><p>Health and wellness services to keep the student community safe and supported.</p></div>
                        <div class="b"><i class="fa-solid fa-award"></i><h3>SSC & Outreach</h3><p>Student leadership, engagement programs, and community-building events.</p></div>
                        <div class="b"><i class="fa-solid fa-user-graduate"></i><h3>Guidance Office</h3><p>Counseling, mentoring, and career readiness for academic and personal development.</p></div>
                    </section>

                    <!-- ============ HISTORY (HORIZONTAL TIMELINE) ============ -->
                    <div class="sec-head" id="history" data-animate>
                        <p class="eyebrow">History Timeline</p>
                        <h2>From PASS to PASS College</h2>
                        <p>Nearly three decades of growth, innovation, and student-centered education.</p>
                    </div>
                    <section class="timeline-wrap" data-animate>
                        <div class="timeline-h">
                            <div class="tl-item">
                                <div class="tl-dot">1997</div>
                                <h4>Founded as PASS</h4>
                                <p>Philippine Accountancy and Science School opens in Alaminos City.</p>
                            </div>
                            <div class="tl-item">
                                <div class="tl-dot">2001</div>
                                <h4>Becomes PASS College</h4>
                                <p>Renamed and expanded, continuing its commitment to accessible, high-quality education.</p>
                            </div>
                            <div class="tl-item">
                                <div class="tl-dot">2007</div>
                                <h4>Ladderized Education</h4>
                                <p>Responds to EO 358 by launching ladderized programs and career pathways.</p>
                            </div>
                            <div class="tl-item">
                                <div class="tl-dot">2016</div>
                                <h4>Modern Era</h4>
                                <p>Blending academic excellence with innovation, strengthening regional leadership.</p>
                            </div>
                        </div>
                        <div class="history-story">
                            <h3>The History of our School</h3>
                            <p>Founded in 1997 as the Philippine Accountancy and Science School, PASS College continues Mrs. Adelina M. Morante's dream of providing quality, well-rounded education to produce top-caliber, values-driven graduates.</p>
                            <h4>History of PASS College</h4>
                            <p>The dream to establish an institution that produces not only successful graduates but also God-loving and law-abiding citizens became reality in 1997 when PASS College was inaugurated. In 2001, the school evolved into PASS College and expanded its reach across Western Pangasinan.</p>
                            <p>PASS College started with four ladderized programs: BS Accountancy, BS Computer Science, BS Commerce, and Secretarial Administration. Later, it added Elementary Education, Tourism Hotel and Restaurant Management, Computer Secretarial, and Caregiving programs. Today, it offers a broader set of programs to meet local needs and support student competence.</p>
                            <p>In 2007, PASS College responded to Executive Order 358 of President Gloria Macapagal-Arroyo by inaugurating the Ladderized Education System. The institution continues its quest for academic excellence and remains committed to providing a quality, well-rounded education for the youth.</p>
                        </div>
                    </section>

                    <!-- ============ VALUES ============ -->
                    <div class="sec-head" data-animate>
                        <p class="eyebrow">Core Values</p>
                        <h2>The PASSian promise</h2>
                    </div>
                    <section class="values-grid-new" data-animate>
                        <?php foreach ($aboutCoreValues as $index => $value): $iconClass = ['fa-solid fa-shield-halved', 'fa-solid fa-flag', 'fa-solid fa-atom'][$index % 3] ?? 'fa-solid fa-star'; ?>
                            <div class="value-card">
                                <i class="<?= htmlspecialchars($iconClass) ?>"></i>
                                <h3><?= htmlspecialchars($value['title'] ?? '') ?></h3>
                                <p><?= htmlspecialchars($value['description'] ?? '') ?></p>
                            </div>
                        <?php endforeach; ?>
                    </section>

                    <!-- ============ PROGRAMS ============ -->
                    <div class="sec-head" id="programs" data-animate>
                        <p class="eyebrow">Program Gallery</p>
                        <h2>Programs designed for the future</h2>
                        <p>A premium yet affordable choice for learners in business, education, technology, hospitality, and service.</p>
                    </div>
                    <section class="programs-new" data-animate>
                        <?php foreach ($aboutPrograms as $program):
                            $color = strtolower((string)($program['color'] ?? ''));
                            $groupClass = 'prog-group-accountancy';
                            $iconClass = 'fa-solid fa-graduation-cap';
                            if (str_contains($color, 'green') || str_contains($color, 'teal')) { $groupClass = 'prog-group-tech'; $iconClass = 'fa-solid fa-code'; }
                            elseif (str_contains($color, 'blue')) { $groupClass = 'prog-group-business'; $iconClass = 'fa-solid fa-briefcase'; }
                            elseif (str_contains($color, 'purple')) { $groupClass = 'prog-group-hospitality'; $iconClass = 'fa-solid fa-utensils'; }
                            elseif (str_contains($color, 'pink')) { $groupClass = 'prog-group-tourism'; $iconClass = 'fa-solid fa-plane-departure'; }
                            elseif (str_contains($color, 'yellow') || str_contains($color, 'gold')) { $groupClass = 'prog-group-education'; $iconClass = 'fa-solid fa-chalkboard-user'; }
                            elseif (str_contains($color, 'sky') || str_contains($color, 'cyan')) { $groupClass = 'prog-group-crim'; $iconClass = 'fa-solid fa-shield'; }
                        ?>
                            <div class="prog <?= htmlspecialchars($groupClass) ?>">
                                <div class="p-icon"><i class="<?= htmlspecialchars($iconClass) ?>"></i></div>
                                <div>
                                    <h4><?= htmlspecialchars($program['title'] ?? '') ?></h4>
                                    <p class="prog-desc"><?= htmlspecialchars($program['description'] ?? '') ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </section>

                    <section class="employee-chart-section" data-animate>
                        <div class="sec-head">
                            <p class="eyebrow">People</p>
                            <h2>Employee chart</h2>
                            <p>Meet the dedicated team who help guide students, support operations, and strengthen the campus community.</p>
                        </div>
                        <div class="employee-chart">
                            <?php foreach ($aboutPeopleByCategory as $category => $people): ?>
                                <div class="employee-chart-card">
                                    <h3><?= htmlspecialchars($category) ?></h3>
                                    <div class="employee-list">
                                        <?php foreach ($people as $person):
                                            $name = trim((string)($person['name'] ?? ''));
                                            $image = trim((string)($person['image'] ?? ''));
                                            $initials = '';
                                            if ($name !== '') {
                                                $parts = preg_split('/\s+/', $name);
                                                $initials = strtoupper(substr($parts[0] ?? '', 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                                            }
                                        ?>
                                            <div class="employee-item">
                                                <?php if ($image !== ''): ?>
                                                    <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($name) ?>" style="width:72px;height:72px;border-radius:50%;object-fit:cover;margin-bottom:10px;box-shadow:0 6px 16px rgba(128,0,0,.2);">
                                                <?php else: ?>
                                                    <div class="employee-avatar"><?= htmlspecialchars($initials ?: 'P') ?></div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?= htmlspecialchars($name) ?></strong>
                                                    <small><?= htmlspecialchars($category) ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                </div>
            </div>
        </main>
        <footer class="dashboard-footer">
            <div class="footer-content">
                <div class="footer-card">
                    <h3>About</h3>
                    <p>PASS College is dedicated to supporting student success through integrated academic, guidance, and health services.</p>
                </div>
                <div class="footer-card">
                    <h3>Address</h3>
                    <?php
                    $contacts = json_decode(get_library_setting('footer_contacts', '[]'), true) ?: [];
                    $locations = json_decode(get_library_setting('footer_locations', '[]'), true) ?: [];
                    $social = json_decode(get_library_setting('footer_social', '[]'), true) ?: [];
                    ?>
                    <?php if (!empty($locations)): ?>
                        <?php foreach ($locations as $loc):
                            $lname = htmlspecialchars($loc['name'] ?? $loc[0] ?? 'Location');
                            $laddr = htmlspecialchars($loc['address'] ?? $loc[2] ?? '');
                            $llink = htmlspecialchars($loc['link'] ?? $loc[1] ?? '#');
                        ?>
                            <p style="margin-bottom:8px;"><strong><?= $lname ?></strong><br>
                            <?= $laddr ? $laddr . '<br>' : '' ?>
                            <?php if (!empty($llink) && $llink !== '#'): ?>
                                <a href="<?= $llink ?>" target="_blank" rel="noopener noreferrer">View on map</a>
                            <?php endif; ?>
                            </p>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No address / location set.</p>
                    <?php endif; ?>
                </div>
                <div class="footer-card">
                    <h3>Connect</h3>
                    <?php if (!empty($contacts)): ?>
                        <ul style="list-style:none;padding:0;margin:0 0 8px 0;">
                        <?php foreach ($contacts as $c):
                            $label = htmlspecialchars($c['label'] ?? $c[0] ?? '');
                            $value = trim($c['value'] ?? $c[1] ?? '');
                            $escaped = htmlspecialchars($value);
                            $href = '';
                            if (preg_match('#^https?://#i', $value)) {
                                $href = $escaped;
                            } elseif (strpos($value, '@') !== false) {
                                $href = 'mailto:' . $escaped;
                            } elseif (preg_match('/^[+0-9() \-]+$/', $value)) {
                                $href = 'tel:' . preg_replace('/[^0-9+]/', '', $value);
                            }
                        ?>
                            <li style="margin-bottom:6px;"><strong><?= $label ?>:</strong>
                                <?php if ($href): ?>
                                    <a href="<?= $href ?>" target="_blank" rel="noopener noreferrer"><?= $escaped ?></a>
                                <?php else: ?>
                                    <?= $escaped ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>No contacts set.</p>
                    <?php endif; ?>

                    <?php if (!empty($social)): ?>
                        <p>
                        <?php
                            $iconMap = [
                                'facebook' => 'fab fa-facebook',
                                'facebook-messenger' => 'fab fa-facebook-messenger',
                                'tiktok' => 'fab fa-tiktok',
                                'x' => 'fab fa-x',
                                'youtube' => 'fab fa-youtube',
                                'instagram' => 'fab fa-instagram',
                                'threads' => 'fab fa-internet-explorer',
                                'whatsapp' => 'fab fa-whatsapp',
                                'telegram' => 'fab fa-telegram',
                                'discord' => 'fab fa-discord',
                                'reddit' => 'fab fa-reddit',
                                'pinterest' => 'fab fa-pinterest',
                                'quora' => 'fab fa-quora',
                                'others' => 'fas fa-link'
                            ];
                            foreach ($social as $i => $s) {
                                $platRaw = strtolower(trim((string)($s['platform'] ?? $s[0] ?? '')));
                                $label = htmlspecialchars($s['platform'] ?? $s[0] ?? 'Link');
                                $link = htmlspecialchars($s['link'] ?? $s[1] ?? '#');
                                $iconClass = $iconMap[$platRaw] ?? 'fas fa-link';
                        ?>
                            <a href="<?= $link ?>" target="_blank" rel="noopener noreferrer"><i class="<?= $iconClass ?>" style="margin-right:6px"></i><?= $label ?></a><?= $i < count($social)-1 ? ' · ' : '' ?>
                        <?php } ?>
                        </p>
                    <?php else: ?>
                        <p>No social links set.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="footer-divider"></div>
            <div class="footer-copyright">© 2026 PASS College. All rights reserved.</div>
        </footer>
    </div>
    <div class="page-overlay" id="pageOverlay"></div>
    <script src="../assets/js/app.js" defer></script>
    <script>
        const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
        const sidebar = document.querySelector('.side-nav');
        const pageOverlay = document.querySelector('.page-overlay');

        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', function(e) {
                e.preventDefault();
                sidebar.classList.toggle('mobile-open');
                pageOverlay.classList.toggle('active');
            });
        }
        if (pageOverlay) {
            pageOverlay.addEventListener('click', function() {
                sidebar.classList.remove('mobile-open');
                pageOverlay.classList.remove('active');
            });
        }
        const navLinks = document.querySelectorAll('.side-nav a');
        navLinks.forEach(link => {
            if (!link.classList.contains('nav-toggle')) {
                link.addEventListener('click', function() {
                    sidebar.classList.remove('mobile-open');
                    pageOverlay.classList.remove('active');
                });
            }
        });
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                sidebar.classList.remove('mobile-open');
                pageOverlay.classList.remove('active');
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Swipe-to-Refresh Functionality (Mobile Only: 320px - 768px)
            const swipeRefreshSpinner = document.getElementById('swipe-refresh-spinner');
            let touchStartY = 0;
            let touchEndY = 0;
            let isRefreshing = false;
            const SWIPE_THRESHOLD = 100; // minimum pixels to swipe
            const SWIPE_START_LIMIT = 100; // only detect swipe from top 100px

            function isScreenMobile() {
                const width = window.innerWidth;
                return width >= 320 && width <= 768;
            }

            function showRefreshSpinner() {
                if (isScreenMobile() && !isRefreshing && swipeRefreshSpinner) {
                    isRefreshing = true;
                    swipeRefreshSpinner.style.display = 'flex';
                    setTimeout(() => {
                        location.reload();
                    }, 800);
                }
            }

            document.addEventListener('touchstart', function(e) {
                if (!isScreenMobile()) return;
                const touch = e.touches[0];
                touchStartY = touch.clientY;
            }, { passive: true });

            document.addEventListener('touchend', function(e) {
                if (!isScreenMobile()) return;
                const touch = e.changedTouches[0];
                touchEndY = touch.clientY;

                // Detect swipe down from top of page
                if (touchStartY <= SWIPE_START_LIMIT && touchEndY - touchStartY >= SWIPE_THRESHOLD) {
                    // Check if we're near the top of the page
                    if (window.scrollY <= 10) {
                        e.preventDefault();
                        showRefreshSpinner();
                    }
                }
            }, { passive: true });

            // Animation observer for scroll-triggered effects
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('in-view');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12 });
            document.querySelectorAll('[data-animate]').forEach(el => observer.observe(el));
        });
    </script>
    <?php include '../AI CHAT BOT/chat_widget.php'; ?>
</body>
</html>
