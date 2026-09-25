<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$user = current_user();
if (!$user) {
    header('Location: ../auth/student_login.php');
    exit;
}

$displayCourse = $user['course'] ?? $user['course_year'] ?? 'Course / Department';
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
$roleLabel = $user['role'] === 'teacher' ? 'Teacher' : 'Student';
$dashboardLink = $user['role'] === 'teacher' ? 'teacher_home.php' : 'student_home.php';
$topbarInfo = build_user_dashboard_header_info($user);

$conn = get_db();
$alumniYears = $conn->query("SELECT DISTINCT graduation_year FROM ssaa_alumni ORDER BY graduation_year DESC")->fetchAll(PDO::FETCH_COLUMN);
$rawAlumniCourses = $conn->query("SELECT DISTINCT course FROM ssaa_alumni WHERE course IS NOT NULL AND course != '' ORDER BY course")->fetchAll(PDO::FETCH_COLUMN);
$alumniCourses = normalize_course_list($rawAlumniCourses);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Alumni | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        .alumni-page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 22px;
        }
        .alumni-page-header h1 {
            margin: 0;
            font-size: 1.6rem;
            color: #111827;
        }
        .alumni-page-header p {
            margin: 0;
            color: #6b7280;
        }
        .alumni-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(220px, 1fr));
            gap: 20px;
        }
        .alumni-card {
            min-height: 240px;
            padding: 24px;
            border-radius: 20px;
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 1);
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
            display: grid;
            gap: 12px;
        }
        @media (max-width: 1280px) {
            .alumni-grid {
                grid-template-columns: repeat(3, minmax(220px, 1fr));
            }
        }
        @media (max-width: 900px) {
            .alumni-grid {
                grid-template-columns: repeat(2, minmax(220px, 1fr));
            }
        }
        @media (max-width: 640px) {
            .alumni-grid {
                grid-template-columns: 1fr;
            }
        }
        .alumni-card strong {
            font-size: 1rem;
            color: #111827;
        }
        .alumni-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            color: #475569;
            font-size: 0.95rem;
        }
        .alumni-card .pill {
            background: rgba(37, 99, 235, 0.12);
            color: #1d4ed8;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 18px;
            border-radius: 14px;
            background: #f8fafc;
            color: #1f2937;
            text-decoration: none;
            border: 1px solid #e5e7eb;
        }
        .back-link:hover {
            background: #eff6ff;
        }
        .alumni-loading {
            color: #475569;
            padding: 18px 0;
        }
        .alumni-no-results {
            color: #6b7280;
            padding: 18px 0;
        }
        .dashboard-card-full-width {
            grid-column: 1 / -1;
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-mobile-header">
                <div class="mobile-user-info">
                    <h4><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p><?= htmlspecialchars($topbarInfo['displayMeta']) ?></p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
            <div class="nav-section">
                <a href="ssaa_student_home.php" data-tooltip="Dashboard"><span class="nav-icon"><i class="fa-solid fa-house"></i></span><span class="nav-text">Dashboard</span></a>
                <a href="school_announcements.php" data-tooltip="Announcements"><span class="nav-icon"><i class="fa-solid fa-bell"></i></span><span class="nav-text">Announcements</span></a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Services"><span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span><span class="nav-text">Services</span><span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span></button>
                    <div class="submenu" aria-hidden="true">
                        <a href="guidance_dashboard.php" data-tooltip="Guidance"><span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span><span class="nav-text">Guidance</span></a>
                        <a href="library_dashboard.php" data-tooltip="Library"><span class="nav-icon"><i class="fa-solid fa-book"></i></span><span class="nav-text">Library</span></a>
                        <a href="clinic_dashboard.php" data-tooltip="Clinic"><span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span><span class="nav-text">Clinic</span></a>
                        <a href="ssc/ssc_dashboard.php" data-tooltip="SSC"><span class="nav-icon"><i class="fa-solid fa-award"></i></span><span class="nav-text">SSC</span></a>
                        <a href="scholarship/scholarship_dashboard.php" data-tooltip="Scholarship"><span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span><span class="nav-text">Scholarship</span></a>
                        <a href="ssaa_student_home.php" data-tooltip="Alumni"><span class="nav-icon"><i class="fa-solid fa-users"></i></span><span class="nav-text">Alumni</span></a>
                    </div>
                </div>
                <a href="profile.php" data-tooltip="Profile"><span class="nav-icon"><i class="fa-solid fa-user"></i></span><span class="nav-text">Profile</span></a>
                <a href="about.php" data-tooltip="About"><span class="nav-icon"><i class="fa-solid fa-circle-info"></i></span><span class="nav-text">About</span></a>
            </div>
            <div class="nav-footer"><a href="../logout.php" class="logout-link" data-tooltip="Logout"><span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span><span class="nav-text">Logout</span></a></div>
        </aside>
        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle mobile menu"><i class="fa-solid fa-bars"></i></button>
                    <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div><h2>PASS College</h2><p>Alumni Services</p></div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta"><?= htmlspecialchars($displayCourse) ?> · Year <?= htmlspecialchars($displayYear) ?></span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Profile" data-tooltip="Profile" data-menu-target="profileMenu"><i class="fa-solid fa-user"></i></button>
                    <div class="topbar-menu" id="notificationMenu" role="menu" aria-label="Notifications menu"><div class="menu-header"><strong>Notifications</strong><span class="menu-note">Latest announcements</span></div><div class="menu-empty">No new announcements.</div><a class="menu-link menu-footer-link" href="school_announcements.php">View all announcements</a></div>
                    <div class="topbar-menu" id="profileMenu" role="menu" aria-label="Profile menu"><div class="menu-item profile-menu-item" role="menuitem"><a href="profile.php" class="menu-link profile-link"><?= htmlspecialchars($user['full_name']) ?></a><span class="menu-subtext">View your account details</span></div><a href="profile.php" class="menu-action">View profile</a></div>
                </div>
            </header>
            <div class="main-scroll">
                <div class="content-panel">
                    <section class="dashboard-intro">
                        <div class="dashboard-intro-left">
                            <p class="eyebrow">Alumni Directory</p>
                            <h1>All Alumni</h1>
                            <p class="dashboard-subtitle">Browse the full alumni directory and explore graduate profiles by year, course, and employment status.</p>
                        </div>
                        <aside class="dashboard-intro-right">
                            <a class="back-link" href="ssaa_student_home.php"><i class="fa-solid fa-arrow-left"></i> Back to SSAA Dashboard</a>
                        </aside>
                    </section>

                    <div class="dashboard-grid">
                        <div class="dashboard-card dashboard-card-full-width">
                            <div class="card-header">
                                <h3><i class="fa-solid fa-list"></i> Alumni Showcase</h3>
                            </div>
                            <div class="card-content">
                                <div class="filter-panel" style="margin-bottom: 22px;">
                                    <div class="filter-row" style="grid-template-columns: 1fr 160px 180px; gap: 14px; align-items: center;">
                                        <input type="search" id="alumni-search" placeholder="Search by name, course, or job title..." oninput="loadAlumniList()">
                                        <select id="alumni-year-filter" onchange="loadAlumniList()">
                                            <option value="">All years</option>
                                            <?php foreach ($alumniYears as $year): ?>
                                                <option value="<?= htmlspecialchars($year) ?>"><?= htmlspecialchars($year) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select id="alumni-course-filter" onchange="loadAlumniList()">
                                            <option value="">All courses</option>
                                            <?php foreach ($alumniCourses as $course): ?>
                                                <option value="<?= htmlspecialchars($course) ?>"><?= htmlspecialchars($course) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div id="alumni-grid" class="alumni-grid"></div>
                            </div>
                        </div>
                    </div>
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
                        <?php foreach ($locations as $loc): ?>
                            <p style="margin-bottom:8px;"><strong><?= htmlspecialchars($loc['name'] ?? $loc[0] ?? 'Location') ?></strong><br>
                            <?= htmlspecialchars($loc['address'] ?? $loc[2] ?? '') ?><br>
                            <?php $locationLink = $loc['link'] ?? $loc[1] ?? ''; if ($locationLink): ?><a href="<?= htmlspecialchars($locationLink) ?>" target="_blank" rel="noopener noreferrer">View on map</a><?php endif; ?></p>
                        <?php endforeach; ?>
                    <?php else: ?><p>No address / location set.</p><?php endif; ?>
                </div>
                <div class="footer-card">
                    <h3>Connect</h3>
                    <?php if (!empty($contacts)): ?><ul style="list-style:none;padding:0;margin:0 0 8px 0;">
                        <?php foreach ($contacts as $contact): ?><li style="margin-bottom:6px;"><strong><?= htmlspecialchars($contact['label'] ?? $contact[0] ?? '') ?>:</strong> <?= htmlspecialchars($contact['value'] ?? $contact[1] ?? '') ?></li><?php endforeach; ?>
                    </ul><?php else: ?><p>No contacts set.</p><?php endif; ?>
                    <?php if (!empty($social)): ?><p><?php foreach ($social as $socialLink): ?><a href="<?= htmlspecialchars($socialLink['link'] ?? $socialLink[1] ?? '#') ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-link" style="margin-right:6px"></i><?= htmlspecialchars($socialLink['platform'] ?? $socialLink[0] ?? 'Link') ?></a> <?php endforeach; ?></p><?php endif; ?>
                </div>
            </div>
            <div class="footer-divider"></div>
            <div class="footer-copyright">© <?= date('Y') ?> PASS College. All rights reserved.</div>
        </footer>
    </div>
    <script src="../PWA/pwa-registration.js" defer></script>
    <script src="../assets/js/app.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadAlumniList();
        });

        async function loadAlumniList() {
            const query = document.getElementById('alumni-search').value.trim();
            const year = document.getElementById('alumni-year-filter').value;
            const course = document.getElementById('alumni-course-filter').value;
            const grid = document.getElementById('alumni-grid');

            grid.innerHTML = '<div class="alumni-loading">Loading alumni records...</div>';

            try {
                const params = new URLSearchParams({
                    action: 'get_alumni_list',
                    query,
                    year,
                    course
                });
                const response = await fetch('ssaa_api.php?' + params.toString());
                const data = await response.json();

                if (!data.success || !Array.isArray(data.alumni)) {
                    grid.innerHTML = '<div class="alumni-no-results">No alumni found.</div>';
                    return;
                }

                if (data.alumni.length === 0) {
                    grid.innerHTML = '<div class="alumni-no-results">No alumni found.</div>';
                    return;
                }

                grid.innerHTML = data.alumni.map(alum => `
                    <div class="alumni-card">
                        <strong>${alum.full_name || 'Unknown'}</strong>
                        <div class="alumni-meta">
                            <span class="pill pill-year">${alum.graduation_year || 'N/A'}</span>
                            <span>${alum.course || 'N/A'}</span>
                            <span class="pill pill-status">${alum.employment_status || 'Unknown'}</span>
                        </div>
                        <div>Company: ${alum.company || 'N/A'}</div>
                        <div>Job Title: ${alum.job_title || 'N/A'}</div>
                    </div>
                `).join('');
            } catch (error) {
                console.error('Error loading alumni:', error);
                grid.innerHTML = '<div class="alumni-no-results">Unable to load alumni records.</div>';
            }
        }
    </script>
</body>
</html>
