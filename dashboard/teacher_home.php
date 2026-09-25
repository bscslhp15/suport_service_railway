<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();
if ($user['role'] !== 'teacher') {
    header('Location: ../auth/teacher_login.php');
    exit;
}
if ($user['head_service'] !== 'none') {
    header('Location: head_home.php');
    exit;
}

$displayCourse = $user['course'] ?? ($user['course_year'] ?? 'Course');
$displayYearLabel = $user['year_level'] ?? null;
if (empty($displayYearLabel) && !empty($user['course_year'])) {
    $parts = explode(' ', trim($user['course_year']));
    $lastPart = end($parts);
    $ordinals = ['1' => '1st Year', '2' => '2nd Year', '3' => '3rd Year', '4' => '4th Year'];
    if (isset($ordinals[$lastPart])) {
        $displayYearLabel = $ordinals[$lastPart];
        array_pop($parts);
        $displayCourse = implode(' ', $parts) ?: $displayCourse;
    }
}
$topbarInfo = build_user_dashboard_header_info($user);
$calendarBlocks = get_upcoming_library_calendar_blocks(5);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | PASS Support System</title>
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
<body class="teacher-dashboard">
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
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
            <div class="nav-section">
                <a href="teacher_home.php" class="active" data-tooltip="Dashboard">
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
                        <a href="/THESIS/SUPPORTSERVICESYSTEM/dashboard/guidance_home.php" data-tooltip="Guidance">
                            <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                            <span class="nav-text">Guidance</span>
                        </a>
                        <a href="/THESIS/SUPPORTSERVICESYSTEM/dashboard/library_dashboard.php" data-tooltip="Library">
                            <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                            <span class="nav-text">Library</span>
                        </a>
                        <a href="/THESIS/SUPPORTSERVICESYSTEM/dashboard/clinic_dashboard.php" data-tooltip="Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                            <span class="nav-text">Clinic</span>
                        </a>
                        <a href="ssc/ssc_dashboard.php" data-tooltip="Supreme Student Council">
                            <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                            <span class="nav-text">SSC</span>
                        </a>
                        <a href="/THESIS/SUPPORTSERVICESYSTEM/dashboard/scholarship/scholarship_dashboard.php" data-tooltip="Scholarship">
                            <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                            <span class="nav-text">Scholarship</span>
                        </a>
                        <a href="/THESIS/SUPPORTSERVICESYSTEM/dashboard/ssaa_student_home.php" data-tooltip="Alumni">
                            <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                            <span class="nav-text">Alumni</span>
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
                    <span class="nav-icon"><i class="fa-solid fa-sign-out-alt"></i></span>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
        </aside>
        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <button type="button" class="mobile-menu-toggle" aria-label="Toggle menu"><i class="fa-solid fa-bars"></i></button>
                    <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>Teacher Portal</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta"><?= htmlspecialchars($displayCourse) ?><?php if (!empty($displayYearLabel)): ?> · <?= htmlspecialchars($displayYearLabel) ?><?php endif; ?></span>
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
                        <a class="menu-link menu-footer-link" href="school_announcements.php">View all announcements</a>
                    </div>

                    <div class="topbar-menu" id="profileMenu" role="menu" aria-label="Profile menu">
                        <div class="menu-item profile-menu-item" role="menuitem">
                            <a href="profile.php" class="menu-link profile-link"><?= htmlspecialchars($user['full_name']) ?></a>
                            <span class="menu-subtext">View your account details</span>
                        </div>
                        <a href="profile.php" class="menu-action">View profile</a>
                    </div>
                </div>
            </header>

            <section class="dashboard-intro">
                    <div class="dashboard-intro-left">
                        <p class="eyebrow"><i class="fa-solid fa-star sparkle-icon"></i> Welcome back</p>
                        <h1><?= htmlspecialchars($user['full_name']) ?></h1>
                        <p class="dashboard-subtitle">Your teacher dashboard gives quick access to service portals and school resources.</p>
                        <p class="dashboard-subtitle">Manage classes, monitor student support, and stay updated on school events.</p>
                        <a href="#available-services" class="view-services-btn">View Services →</a>
                    </div>
                    <aside class="dashboard-intro-right">
                        <div class="dashboard-detail-panel">
                            <div class="detail-row role-row">
                                <div class="detail-icon"><i class="fa-solid fa-chalkboard-teacher"></i></div>
                                <div class="detail-content">
                                    <div class="detail-top">Role</div>
                                    <div class="detail-bottom">Teacher</div>
                                </div>
                            </div>
                            <div class="detail-row course-row">
                                <div class="detail-icon"><i class="fa-solid fa-book"></i></div>
                                <div class="detail-content">
                                    <div class="detail-top">Course</div>
                                    <div class="detail-bottom"><?= htmlspecialchars($displayCourse) ?></div>
                                </div>
                            </div>
                            <div class="detail-row year-row">
                                <div class="detail-icon"><i class="fa-solid fa-school"></i></div>
                                <div class="detail-content">
                                    <div class="detail-top">Head Service</div>
                                    <div class="detail-bottom"><?= htmlspecialchars(!empty($user['head_service']) ? $user['head_service'] : 'Not assigned') ?></div>
                                </div>
                            </div>
                            <div class="detail-row id-row">
                                <div class="detail-icon"><i class="fa-solid fa-id-card"></i></div>
                                <div class="detail-content">
                                    <div class="detail-top">Employee ID</div>
                                    <div class="detail-bottom"><?= htmlspecialchars(!empty($user['employee_id']) ? $user['employee_id'] : 'N/A') ?></div>
                                </div>
                            </div>
                        </div>
                    </aside>
                </section>
                <h2 class="section-caption">Available services</h2>
                <section class="dashboard-grid services-grid">
                    <div class="service-card">
                        <div class="service-card-header">
                            <div class="service-card-icon guidance"><i class="fa-solid fa-user-graduate"></i></div>
                        </div>
                        <div class="service-card-body">
                            <h2>Guidance</h2>
                            <p>Book counseling sessions and view student support updates.</p>
                        </div>
                        <div class="service-card-footer">
                            <a class="service-button" href="guidance_home.php">View Service →</a>
                        </div>
                    </div>
                    <div class="service-card">
                        <div class="service-card-header">
                            <div class="service-card-icon library"><i class="fa-solid fa-book"></i></div>
                        </div>
                        <div class="service-card-body">
                            <h2>Library</h2>
                            <p>Access resources, materials, and library support tools.</p>
                        </div>
                        <div class="service-card-footer">
                            <a class="service-button" href="library_dashboard.php">View Service →</a>
                        </div>
                    </div>
                    <div class="service-card">
                        <div class="service-card-header">
                            <div class="service-card-icon clinic"><i class="fa-solid fa-stethoscope"></i></div>
                        </div>
                        <div class="service-card-body">
                            <h2>Clinic</h2>
                            <p>Coordinate health services and student medical support.</p>
                        </div>
                        <div class="service-card-footer">
                            <a class="service-button" href="clinic_dashboard.php">View Service →</a>
                        </div>
                    </div>
                    <div class="service-card">
                        <div class="service-card-header">
                            <div class="service-card-icon scholarship"><i class="fa-solid fa-award"></i></div>
                        </div>
                        <div class="service-card-body">
                            <h2>SSC</h2>
                            <p>Monitor SSC activities and student council participation.</p>
                        </div>
                        <div class="service-card-footer">
                            <a class="service-button" href="ssc/ssc_dashboard.php">View Service →</a>
                        </div>
                    </div>
                    <div class="service-card">
                        <div class="service-card-header">
                            <div class="service-card-icon funding"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                        </div>
                        <div class="service-card-body">
                            <h2>Scholarship</h2>
                            <p>Track scholarships, grants, and student aid opportunities.</p>
                        </div>
                        <div class="service-card-footer">
                            <a class="service-button" href="scholarship/scholarship_dashboard.php">View Service →</a>
                        </div>
                    </div>
                    <div class="service-card">
                        <div class="service-card-header">
                            <div class="service-card-icon ssaa"><i class="fa-solid fa-users"></i></div>
                        </div>
                        <div class="service-card-body">
                            <h2>Alumni</h2>
                            <p>Review alumni engagements and student affairs coordination.</p>
                        </div>
                        <div class="service-card-footer">
                            <a class="service-button" href="ssaa_student_home.php">View Service →</a>
                        </div>
                    </div>
                </section>
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
                    require_once __DIR__ . '/../includes/functions.php';
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
            <div class="footer-copyright">© <?= date('Y') ?> PASS College. All rights reserved.</div>
        </footer>
        <div class="page-overlay"></div>
    </div>
    <script>
        // Mobile menu toggle functionality
        const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
        const sideNav = document.querySelector('.side-nav');
        const pageOverlay = document.querySelector('.page-overlay');

        // Toggle menu
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                sideNav.classList.toggle('mobile-open');
                pageOverlay.classList.toggle('active');
            });
        }

        // Close menu when overlay is clicked
        if (pageOverlay) {
            pageOverlay.addEventListener('click', () => {
                sideNav.classList.remove('mobile-open');
                pageOverlay.classList.remove('active');
            });
        }

        // Close menu on nav link click (except toggles)
        document.querySelectorAll('.side-nav a:not(.nav-toggle)').forEach(link => {
            link.addEventListener('click', () => {
                sideNav.classList.remove('mobile-open');
                pageOverlay.classList.remove('active');
            });
        });

        // Close menu on ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                sideNav.classList.remove('mobile-open');
                pageOverlay.classList.remove('active');
            }
        });

        // PWA Installation Handler for Teacher Portal
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
                    console.log(`User response to install teacher portal: ${outcome}`);
                    deferredPrompt = null;
                    installAppBtn.style.display = 'none';
                } else {
                    window.location.href = '../auth/teacher_login.php';
                }
            });
        }

        window.addEventListener('appinstalled', () => {
            console.log('PASS Support System (Teacher) installed as an app');
            if (installAppBtn) {
                installAppBtn.style.display = 'none';
            }
        });

        // Swipe-to-Refresh Functionality (Mobile Only: 320px - 768px)
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    </script>
    <script src="../PWA/pwa-registration.js" defer></script>
    <script src="../assets/js/app.js" defer></script>
    <?php include '../AI CHAT BOT/chat_widget.php'; ?>
</body>
</html>
