<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();
if (!($user['role'] === 'admin' || ($user['role'] === 'teacher' && $user['head_service'] === 'ssc_scholarship'))) {
    header('Location: student_home.php');
    exit;
}

if (isset($_POST['export_scholarship_report'])) {
    $reportType = $_POST['report_type'] ?? 'announcements';
    $exportFormat = $_POST['export_format'] ?? 'excel';
    $pdo = get_db();
    $headers = [];
    $rows = [];

    if ($reportType === 'announcements') {
        $headers = ['Title', 'Program Type', 'Deadline', 'Created At'];
        $stmt = $pdo->query("SELECT title, program_type, deadline, created_at FROM scholarship_announcements ORDER BY created_at DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    } elseif ($reportType === 'applications') {
        $headers = ['Student Name', 'Student ID', 'Announcement Title', 'Status', 'Ready Timestamp', 'Created At', 'Updated At'];
        $stmt = $pdo->query("SELECT u.full_name, u.student_id, sa.title, sa.status, sa.ready_timestamp, sa.created_at, sa.updated_at FROM scholarship_applications sa JOIN users u ON sa.student_id = u.id JOIN scholarship_announcements ann ON sa.announcement_id = ann.id ORDER BY sa.created_at DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    } elseif ($reportType === 'validations') {
        $headers = ['Student Name', 'Student ID', 'Announcement Title', 'Validated By', 'Validation Date', 'Notes', 'Validated At', 'Requirements'];
        $stmt = $pdo->query("SELECT u.full_name, u.student_id, sa.title, vb.full_name, v.validation_date, v.notes, v.validated_at, v.requirements FROM scholarship_document_validations v JOIN users u ON v.student_id = u.id JOIN scholarship_announcements sa ON v.announcement_id = sa.id JOIN users vb ON v.validated_by = vb.id ORDER BY v.validated_at DESC");
        $rows = array_map(function($row) {
            $row[7] = json_decode($row[7], true);
            if (is_array($row[7])) {
                $row[7] = implode(', ', $row[7]);
            }
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_NUM));
    } else {
        $headers = ['Metric', 'Value'];
        $announcements = $pdo->query("SELECT COUNT(*) FROM scholarship_announcements")->fetchColumn();
        $applications = $pdo->query("SELECT COUNT(*) FROM scholarship_applications")->fetchColumn();
        $validations = $pdo->query("SELECT COUNT(*) FROM scholarship_document_validations")->fetchColumn();
        $ready = $pdo->query("SELECT COUNT(*) FROM scholarship_applications WHERE status = 'ready'")->fetchColumn();
        $rows = [
            ['Announcements Published', $announcements],
            ['Applications Submitted', $applications],
            ['Documents Validated', $validations],
            ['Ready Applications', $ready],
        ];
    }

    $filename = "scholarship_{$reportType}_report." . ($exportFormat === 'docs' ? 'doc' : 'csv');
    if ($exportFormat === 'docs') {
        header('Content-Type: application/vnd.ms-word; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo '<html><head><meta charset="UTF-8"></head><body>';
        echo '<h1>' . ucfirst($reportType) . ' Report</h1>';
        echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
        echo '<tr>' . implode('', array_map(fn($h) => '<th style="background:#f0f0f0; text-align:left;">' . htmlspecialchars($h) . '</th>', $headers)) . '</tr>';
        foreach ($rows as $row) {
            echo '<tr>' . implode('', array_map(fn($cell) => '<td>' . htmlspecialchars((string)$cell) . '</td>', $row)) . '</tr>';
        }
        echo '</table></body></html>';
    } else {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo implode(',', array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $headers)) . "\r\n";
        foreach ($rows as $row) {
            echo implode(',', array_map(fn($cell) => '"' . str_replace('"', '""', (string)$cell) . '"', $row)) . "\r\n";
        }
    }
    exit;
}

require_once __DIR__ . '/../AI CHAT BOT/chat_widget.php';

$displayCourse = $user['course'] ?? $user['course_year'] ?? 'Course / Department';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports & Analytics | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/responsive.css">
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
        .topbar-divider { width: 1px; height: 24px; background: #e9ecef; margin: 0 12px; }
        .mic-button { background: none; border: none; cursor: pointer; color: #666; padding: 0 8px; }
    </style>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 24px 0;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-icon {
            font-size: 2.5rem;
            color: #3498db;
            margin-bottom: 12px;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin: 12px 0;
        }
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        @media (max-width: 720px) {
            body {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }

            .main-scroll {
                padding: 0 !important;
                width: 100% !important;
                overflow-x: hidden !important;
                box-sizing: border-box !important;
            }

            .dashboard-intro {
                display: flex !important;
                flex-direction: column !important;
                gap: 14px !important;
                padding: 12px 8px !important;
                margin: 0 !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }

            .dashboard-intro > div:first-child {
                width: 100% !important;
            }

            .dashboard-intro h1 {
                font-size: 1.5rem !important;
                line-height: 1.2 !important;
                margin: 0 !important;
            }

            .dashboard-intro .eyebrow {
                font-size: 0.75rem !important;
                margin: 0 0 6px 0 !important;
            }

            .dashboard-intro .dashboard-subtitle {
                font-size: 0.85rem !important;
                line-height: 1.3 !important;
                margin: 4px 0 0 0 !important;
            }

            .dashboard-summary {
                display: grid !important;
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 8px !important;
                margin: 0 !important;
                width: 100% !important;
            }

            .dashboard-summary > div {
                padding: 10px 8px !important;
                display: flex !important;
                flex-direction: column !important;
                background: #fff !important;
                border: 1px solid rgba(15,23,42,0.06) !important;
                border-radius: 8px !important;
                min-height: auto !important;
            }

            .dashboard-summary span {
                font-size: 0.75rem !important;
                font-weight: 500 !important;
                margin-bottom: 4px !important;
            }

            .dashboard-summary strong {
                font-size: 1.4rem !important;
                line-height: 1 !important;
            }

            .dashboard-section {
                padding: 12px 8px !important;
                margin: 0 !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }

            .dashboard-section h2 {
                font-size: 1.05rem !important;
                margin: 0 0 10px 0 !important;
                font-weight: 600 !important;
            }

            .metrics-grid {
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 10px !important;
                margin: 0 !important;
                width: 100% !important;
            }

            .metric-card {
                padding: 14px !important;
                margin: 0 !important;
                width: 100% !important;
                box-sizing: border-box !important;
                display: flex !important;
                flex-direction: column !important;
            }

            .metric-icon {
                font-size: 1.6rem !important;
                margin-bottom: 8px !important;
            }

            .metric-content h3 {
                font-size: 1.4rem !important;
                margin: 0 0 2px 0 !important;
            }

            .metric-content p {
                font-size: 0.8rem !important;
                margin: 0 !important;
            }

            .report-export-grid {
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 10px !important;
                width: 100% !important;
                margin: 0 !important;
            }

            .report-export-grid > div {
                width: 100% !important;
            }

            .report-export-grid label {
                font-size: 0.85rem !important;
                margin-bottom: 4px !important;
                display: block !important;
            }

            .report-export-grid select,
            .report-export-grid input {
                width: 100% !important;
                box-sizing: border-box !important;
            }

            .report-actions {
                display: flex !important;
                flex-direction: column !important;
                width: 100% !important;
                gap: 0 !important;
            }

            .report-actions button {
                width: 100% !important;
                padding: 10px 12px !important;
            }

            .metric-card[style] {
                padding: 14px !important;
            }

            .metric-card[style] input,
            .metric-card[style] select {
                width: 100% !important;
            }

            .stats-grid {
                grid-template-columns: 1fr !important;
            }

            form#exportForm,
            form#exportForm > div,
            form#exportForm > div > div {
                width: 100% !important;
            }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-mobile-header">
                <div class="mobile-user-info">
                    <h4><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p>SSC / Scholarship Head</p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                
            </div>
            <div class="nav-section">
                <a href="ssc_head_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                    <span class="nav-text">Dashboard</span>
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
                        <a href="librarian_home.php" data-tooltip="Library">
                            <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                            <span class="nav-text">Library</span>
                        </a>
                        <a href="nurse_home.php" data-tooltip="Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                            <span class="nav-text">Clinic</span>
                        </a>
                        <a href="ssc/ssc_dashboard.php" data-tooltip="SSC">
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
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage SSC">
                        <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
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
                        <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                        <span class="nav-text">Manage Scholarship</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu" aria-hidden="true">
                        <a href="scholarship_create_announcement.php" data-tooltip="Scholarship Announcements">
                            <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                            <span class="nav-text">Scholarship Announcements</span>
                        </a>
                        
                        <a href="scholarship_reports.php" class="active" data-tooltip="Reports & Analytics">
                            <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                            <span class="nav-text">Reports & Analytics</span>
                        </a>
                    </div>
                </div>
                <a href="profile.php" data-tooltip="Profile">
                    <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                    <span class="nav-text">Profile</span>
                </a>
                <a href="about.php" data-tooltip="About">
                    <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                    <span class="nav-text">About</span>
                </a>
            </div>
            <div class="nav-footer">
                <a href="../logout.php" class="logout-link" data-tooltip="Logout">
                    <span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
        </aside>
        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>SSC / Scholarship Management</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">SSC / Scholarship Head</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Profile" data-tooltip="Profile" data-menu-target="profileMenu"><i class="fa-solid fa-user"></i></button>
                    <!-- support icon removed per UI update -->

                    <div class="topbar-menu" id="notificationMenu" role="menu" aria-label="Notifications menu">
                        <div class="menu-header">
                            <strong>Notifications</strong>
                            <span class="menu-note">Latest announcements</span>
                        </div>
                        <div class="menu-empty">No new announcements.</div>
                        <a class="menu-link menu-footer-link" href="about.php">View all announcements</a>
                    </div>

                    <div class="topbar-menu" id="profileMenu" role="menu" aria-label="Profile menu">
                        <div class="menu-item profile-menu-item" role="menuitem">
                            <a href="profile.php" class="menu-link profile-link"><?= htmlspecialchars($user['full_name']) ?></a>
                            <span class="menu-subtext">View your account details</span>
                        </div>
                        <a href="profile.php" class="menu-action">View profile</a>
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
            <div class="page-overlay" id="pageOverlay"></div>
            <div class="main-scroll">
                <section class="dashboard-intro">
                    <div>
                        <p class="eyebrow" style="color: #000000;">Scholarship Reports</p>
                        <h1 style="color: #000000;">Scholarship analytics overview</h1>
                        <p class="dashboard-subtitle" style="color: #000000;">Monitor application trends, document validation status, and scholarship program performance across all initiatives.</p>
                    </div>
                    <div class="dashboard-summary">
                        <div>
                            <span>Announcements</span>
                            <strong id="quick-announcements">0</strong>
                        </div>
                        <div>
                            <span>Applications</span>
                            <strong id="quick-applications">0</strong>
                        </div>
                        <div>
                            <span>Validations</span>
                            <strong id="quick-validations">0</strong>
                        </div>
                        <div>
                            <span>Updates Posted</span>
                            <strong id="quick-updates">0</strong>
                        </div>
                    </div>
                </section>

                <!-- Key Metrics Row -->
                <section class="dashboard-section">
                    <h2>Scholarship Overview (Last 30 Days)</h2>
                    <div class="metrics-grid" style="grid-template-columns: repeat(3, 1fr);">
                        <div class="metric-card">
                            <div class="metric-icon"><i class="fa-solid fa-bullhorn"></i></div>
                            <div class="metric-content">
                                <h3 id="metric-announcements">0</h3>
                                <p>Announcements Published</p>
                            </div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-icon"><i class="fa-solid fa-file-circle-check"></i></div>
                            <div class="metric-content">
                                <h3 id="metric-applications">0</h3>
                                <p>Applications Submitted</p>
                            </div>
                        </div>
                        <div class="metric-card">
                            <div class="metric-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                            <div class="metric-content">
                                <h3 id="metric-validations">0</h3>
                                <p>Documents Validated</p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Export Reports Section -->
                <section class="dashboard-section">
                    <h2>Export Reports</h2>
                    <div class="metric-card" style="width: 100%; max-width: none; padding: 24px;">
                        <div style="margin-bottom: 20px; padding: 20px; background: #f8fafc; border-radius: 12px; border-left: 4px solid var(--deep-maroon);">
                            <h4 style="margin: 0 0 10px 0; color: var(--deep-maroon); font-size: 15px;">Report Types:</h4>
                            <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: #475569; line-height: 1.7;">
                                <li><strong>Announcements:</strong> All scholarship announcements with titles, content, and deadlines</li>
                                <li><strong>Applications:</strong> Student application submissions and status tracking</li>
                                <li><strong>Validations:</strong> Document validation records and submission status</li>
                                <li><strong>Others:</strong> General scholarship statistics and summaries</li>
                            </ul>
                            <p style="margin: 16px 0 0; font-size: 13px; color: #64748b;">Export data in multiple formats for reporting and analysis.</p>
                        </div>
                        <form id="exportForm" method="post" style="width: 100%;">
                            <div class="report-export-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px;">
                                <div>
                                    <label for="scholarship_report_type" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Report Type</label>
                                    <select name="report_type" id="scholarship_report_type" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                        <option value="announcements">Announcements Report</option>
                                        <option value="applications">Applications Report</option>
                                        <option value="validations">Validations Report</option>
                                        <option value="others">General Statistics</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="scholarship_export_format" style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--deep-maroon);">Export Format</label>
                                    <select name="export_format" id="scholarship_export_format" style="width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px;">
                                        <option value="excel">Excel (CSV)</option>
                                        <option value="docs">Word Document</option>
                                    </select>
                                </div>
                            </div>
                            <div class="report-actions" style="display: flex; justify-content: flex-start;">
                                <button type="button" onclick="handleExport()" style="padding: 14px 28px; background: linear-gradient(135deg, var(--deep-maroon) 0%, var(--soft-gold) 100%); color: white; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; width: auto;">
                                    <i class="fa-solid fa-download"></i>
                                    Export Report
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <div id="notification-container" class="notification-container"></div>

    <script>
        // Mobile Menu Toggle (small screens)
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sideNav = document.querySelector('.side-nav');
            const pageOverlay = document.getElementById('pageOverlay');

            if (mobileMenuToggle && sideNav && pageOverlay) {
                mobileMenuToggle.addEventListener('click', function() {
                    sideNav.classList.toggle('mobile-open');
                    pageOverlay.classList.toggle('active');
                });

                pageOverlay.addEventListener('click', function() {
                    sideNav.classList.remove('mobile-open');
                    pageOverlay.classList.remove('active');
                });

                const navLinks = sideNav.querySelectorAll('a, .nav-toggle');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (!this.classList.contains('nav-toggle')) {
                            sideNav.classList.remove('mobile-open');
                            pageOverlay.classList.remove('active');
                        }
                    });
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && sideNav.classList.contains('mobile-open')) {
                        sideNav.classList.remove('mobile-open');
                        pageOverlay.classList.remove('active');
                    }
                });
            }
        });

    </script>
    <script src="../assets/js/app.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadReportsSummary();
        });

        async function loadReportsSummary() {
            try {
                const response = await fetch('scholarship_api.php?action=get_reports');
                const data = await response.json();
                displayReportsSummary(data);
            } catch (error) {
                console.error('Error loading reports:', error);
                showNotification('Error loading reports', 'error');
            }
        }

        function displayReportsSummary(data) {
            document.getElementById('quick-announcements').textContent = data.reports.announcements || 0;
            document.getElementById('quick-applications').textContent = data.reports.applications || 0;
            document.getElementById('quick-validations').textContent = data.reports.validations || 0;
            document.getElementById('quick-updates').textContent = data.reports.updates || 0;
            
            document.getElementById('metric-announcements').textContent = data.reports.announcements || 0;
            document.getElementById('metric-applications').textContent = data.reports.applications || 0;
            document.getElementById('metric-validations').textContent = data.reports.validations || 0;
        }

        function handleExport() {
            const reportType = document.getElementById('scholarship_report_type').value;
            const exportFormat = document.getElementById('scholarship_export_format').value;
            const form = document.getElementById('exportForm');

            const inputs = [
                { name: 'export_scholarship_report', value: '1' },
                { name: 'report_type', value: reportType },
                { name: 'export_format', value: exportFormat }
            ];

            inputs.forEach(({ name, value }) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            });

            form.submit();

            inputs.forEach(({ name }) => {
                const input = form.querySelector(`input[name="${name}"]`);
                if (input) form.removeChild(input);
            });
        }

        function showNotification(message, type) {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            container.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }
    </script>
</body>
</html>
