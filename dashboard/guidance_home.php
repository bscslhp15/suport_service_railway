<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();

if ($user['role'] !== 'teacher' || $user['head_service'] !== 'guidance') {
    header('Location: guidance_dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
ensure_guidance_schema();

$pdo = get_db();

// Get all cases for overview
$allCases = get_guidance_cases_for_counselor($user['id']);
$newCases = array_filter($allCases, fn($c) => $c['status'] === 'pending_review');
$activeCases = array_filter($allCases, fn($c) => in_array($c['status'], ['under_counseling', 'returned_for_clarification', 'awaiting_session']));
$closedCases = array_filter($allCases, fn($c) => $c['status'] === 'closed');

// Count non-appearances for auto-flagging
$stmt = $pdo->query("SELECT COUNT(*) FROM guidance_sessions WHERE attendance_status = 'non_appearance'");
$nonAppearanceFlags = (int)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Guidance System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <link rel="manifest" href="../PWA/manifest-teacher.json">
    <meta name="theme-color" content="#800000">
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

        .card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .metric-card { border: 1px solid #e2e8f0; padding: 20px; border-radius: 12px; background: #ffffff; }
        .metric-card h3 { margin: 0 0 8px 0; color: #6b7280; font-size: 14px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .metric-card .value { font-size: 32px; font-weight: 700; color: #1f2937; margin: 8px 0; }
        .metric-card .status { font-size: 13px; color: #6b7280; }
        .quick-links { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .quick-link { background: #f3f4f6; padding: 16px; border-radius: 8px; text-decoration: none; color: #1f2937; transition: all 0.2s; }
        .quick-link:hover { background: #e5e7eb; transform: translateY(-2px); }
        .quick-link i { font-size: 20px; margin-bottom: 8px; color: #3b82f6; display: block; }
        .quick-link span { font-weight: 600; display: block; }
        @keyframes spin-refresh {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); z-index: 9999; justify-content: center; align-items: center;"><div style="text-align: center;"><div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div><p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0;">Refreshing...</p></div></div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-mobile-header">
                <div class="mobile-user-info">
                    <h4><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p>Guidance Counselor</p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
            <div class="nav-section">
                <a href="guidance_home.php?service=guidance" class="active" data-tooltip="Dashboard">
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
                        <a href="ssc_head_home.php?service=guidance" data-tooltip="SSC">
                            <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                            <span class="nav-text">SSC</span>
                        </a>
                        <a href="scholarship_dashboard.php?service=guidance" data-tooltip="Scholarship">
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
                <a href="about.php?service=guidance" data-tooltip="About">
                    <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                    <span class="nav-text">About</span>
                </a>
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
                        <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Open menu"><i class="fa-solid fa-bars"></i></button>
                    <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>Guidance Management</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Guidance Counselor</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Profile" data-tooltip="Profile" data-menu-target="profileMenu"><i class="fa-solid fa-user"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Download App" data-tooltip="Download App" id="installAppBtn"><i class="fa-solid fa-download"></i></button>

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
                    <!-- DASHBOARD OVERVIEW -->
                    <section class="dashboard-intro">
                        <div class="dashboard-intro-left">
                            <p class="eyebrow"><i class="fa-solid fa-star sparkle-icon"></i> Guidance Management System</p>
                            <p class="dashboard-date" style="color: white;"><strong style="color: white;">Today's Date</strong> <?= date('F j, Y') ?></p>
                            <h1>Welcome back, <?= htmlspecialchars($user['full_name']) ?>!</h1>
                            <p class="dashboard-subtitle">Manage student guidance cases, track counseling progress, and maintain comprehensive support records.</p>
                        </div>
                        <aside class="dashboard-intro-right">
                            <div class="dashboard-detail-panel">
                                <div class="detail-row course-row">
                                    <div class="detail-icon"><i class="fa-solid fa-user-tie"></i></div>
                                    <div class="detail-content">
                                        <div class="detail-top">Active Cases</div>
                                        <div class="detail-bottom"><?= count($activeCases) ?></div>
                                    </div>
                                </div>
                                <div class="detail-row year-row">
                                    <div class="detail-icon"><i class="fa-solid fa-clock"></i></div>
                                    <div class="detail-content">
                                        <div class="detail-top">Pending Review</div>
                                        <div class="detail-bottom"><?= count($newCases) ?></div>
                                    </div>
                                </div>
                                <div class="detail-row id-row">
                                    <div class="detail-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                                    <div class="detail-content">
                                        <div class="detail-top">Flagged Issues</div>
                                        <div class="detail-bottom"><?= $nonAppearanceFlags ?></div>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </section>

                    <!-- METRICS OVERVIEW -->
                    <div class="card-grid">
                        <div class="metric-card">
                            <h3><i class="fa-solid fa-inbox"></i> New Cases</h3>
                            <div class="value"><?= count($newCases) ?></div>
                            <div class="status">Awaiting initial review</div>
                        </div>
                        <div class="metric-card">
                            <h3><i class="fa-solid fa-hourglass-half"></i> Active Cases</h3>
                            <div class="value"><?= count($activeCases) ?></div>
                            <div class="status">Under management</div>
                        </div>
                        <div class="metric-card">
                            <h3><i class="fa-solid fa-check"></i> Closed Cases</h3>
                            <div class="value"><?= count($closedCases) ?></div>
                            <div class="status">Resolved and archived</div>
                        </div>
                        <div class="metric-card">
                            <h3><i class="fa-solid fa-triangle-exclamation"></i> Flagged Items</h3>
                            <div class="value" style="color: #f59e0b;"><?= $nonAppearanceFlags ?></div>
                            <div class="status">Non-appearance sessions</div>
                        </div>
                    </div>

                    <!-- QUICK NAVIGATION -->
                    <section style="margin-bottom: 32px;">
                        <h2 style="color: #1f2937; margin-bottom: 16px;">Quick Navigation</h2>
                        <div class="quick-links">
                            <a href="case_management.php" class="quick-link">
                                <i class="fa-solid fa-folder-open"></i>
                                <span>Case Management</span>
                                <small style="color: #6b7280; font-weight: normal;">Accept cases, log sessions, manage resolutions</small>
                            </a>
                            <a href="good_moral.php" class="quick-link">
                                <i class="fa-solid fa-certificate"></i>
                                <span>Good Moral Certs</span>
                                <small style="color: #6b7280; font-weight: normal;">Evaluate student eligibility</small>
                            </a>
                            <a href="reports.php" class="quick-link">
                                <i class="fa-solid fa-chart-pie"></i>
                                <span>Analytics & Reports</span>
                                <small style="color: #6b7280; font-weight: normal;">View systems statistics and trends</small>
                            </a>
                        </div>
                    </section>

                    <!-- SYSTEM STATUS -->
                    <div style="background: #dbeafe; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 8px;">
                        <p style="margin: 0; color: #1e40af;">
                            <strong>ℹ️ System Status:</strong> All guidance modules are operational. 
                            <span style="float: right; font-size: 13px;">Last updated: <?= date('g:i A') ?></span>
                        </p>
                    </div>
            </div>
        </main>
    </div>

    <script src="../PWA/pwa-registration.js" defer></script>
    <script src="../assets/js/app.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function isScreenMobile() {
                return window.innerWidth >= 320 && window.innerWidth <= 768;
            }
            
            let touchStartY = 0;
            const SWIPE_THRESHOLD = 100;
            const SWIPE_START_LIMIT = 100;
            
            document.addEventListener('touchstart', function(e) {
                if (!isScreenMobile() || window.scrollY > 10) return;
                if (e.touches.length !== 1) return;
                touchStartY = e.touches[0].clientY;
            }, { passive: true });
            
            document.addEventListener('touchend', function(e) {
                if (!isScreenMobile() || window.scrollY > 10) return;
                if (e.changedTouches.length !== 1) return;
                
                const touchEndY = e.changedTouches[0].clientY;
                const distance = touchEndY - touchStartY;
                
                if (touchStartY <= SWIPE_START_LIMIT && distance >= SWIPE_THRESHOLD) {
                    const spinner = document.getElementById('swipe-refresh-spinner');
                    if (spinner) {
                        spinner.style.display = 'flex';
                    }
                    
                    setTimeout(function() {
                        window.location.reload();
                    }, 800);
                }
            }, { passive: true });
        });
        
        document.addEventListener('DOMContentLoaded', function () {
            const mobileToggle = document.getElementById('mobileMenuToggle');
            const pageOverlay = document.getElementById('pageOverlay');
            const sideNav = document.querySelector('.side-nav');

            function openMobileNav() {
                if (sideNav) sideNav.classList.add('mobile-open');
                if (pageOverlay) pageOverlay.classList.add('active');
            }

            function closeMobileNav() {
                if (sideNav) sideNav.classList.remove('mobile-open');
                if (pageOverlay) pageOverlay.classList.remove('active');
            }

            if (mobileToggle) {
                mobileToggle.addEventListener('click', function () {
                    if (sideNav && sideNav.classList.contains('mobile-open')) {
                        closeMobileNav();
                    } else {
                        openMobileNav();
                    }
                });
            }

            if (pageOverlay) {
                pageOverlay.addEventListener('click', closeMobileNav);
            }

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeMobileNav();
            });

            // PWA Installation Handler
            let deferredPrompt;
            const installAppBtn = document.getElementById('installAppBtn');

            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                if (installAppBtn) {
                    installAppBtn.style.display = 'inline-flex';
                }
            });

            if (installAppBtn) {
                installAppBtn.addEventListener('click', async () => {
                    if (deferredPrompt) {
                        deferredPrompt.prompt();
                        const { outcome } = await deferredPrompt.userChoice;
                        console.log(`User response to install app: ${outcome}`);
                        deferredPrompt = null;
                        installAppBtn.style.display = 'none';
                    } else {
                        alert('App installation is not available on this device or browser.');
                    }
                });
            }

            window.addEventListener('appinstalled', () => {
                console.log('PASS Support System installed as an app');
                if (installAppBtn) {
                    installAppBtn.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
