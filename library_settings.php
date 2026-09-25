<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
$user = current_user();
if ($user['role'] !== 'teacher' || $user['head_service'] !== 'library') {
    header('Location: /auth/teacher_login.php');
    exit;
}
ensure_library_schema();
$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_settings') {
        $maxBorrowed = max(1, (int)($_POST['max_borrowed_books'] ?? 3));
        $maxReserve = max(0, (int)($_POST['max_active_reservations'] ?? 2));
        $borrowDays = max(1, (int)($_POST['borrow_duration_school_days'] ?? 3));
        $holdDays = max(1, (int)($_POST['reservation_hold_days'] ?? 2));
        $fineRate = max(0, (float)($_POST['overdue_fine_per_day'] ?? 20));

        set_library_setting('max_borrowed_books', (string)$maxBorrowed);
        set_library_setting('max_active_reservations', (string)$maxReserve);
        set_library_setting('borrow_duration_school_days', (string)$borrowDays);
        set_library_setting('reservation_hold_days', (string)$holdDays);
        set_library_setting('overdue_fine_per_day', number_format($fineRate, 2, '.', ''));

        $message = 'Library settings have been updated successfully.';
    }
}
$settings = get_library_config();
$currentPage = basename($_SERVER['PHP_SELF']);
$serviceOpen = false;
$manageOpen = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Settings | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/library-header.css">
    <style>
        @font-face {
            font-family: 'ElephantLocal';
            src: url('assets/FONTS/ELEPHNT.TTF') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        .nav-brand h2 {
            font-family: 'ElephantLocal', 'Playfair Display', serif;
        }
        @media (max-width: 768px) {
            .dashboard-intro {
                display: grid;
                gap: 18px;
            }
            .dashboard-section {
                padding: 16px !important;
            }
            .dashboard-section h2 {
                font-size: 1.25rem;
            }
            .dashboard-section > div[style*="display: grid"] {
                grid-template-columns: 1fr !important;
            }
            .dashboard-section > div[style*="grid-template-columns: repeat(auto-fit"] {
                grid-template-columns: 1fr !important;
            }
            .dashboard-section > div[style*="grid-template-columns: 1.05fr 0.95fr"] {
                grid-template-columns: 1fr !important;
            }
            .dashboard-summary-card,
            .dashboard-section .dashboard-summary-card,
            .dashboard-section form,
            .dashboard-section .dashboard-summary-grid {
                width: 100% !important;
            }
            .dashboard-section input,
            .dashboard-section select,
            .dashboard-section textarea,
            .dashboard-section button,
            .dashboard-section a {
                width: 100% !important;
                box-sizing: border-box;
            }
            .dashboard-section button.primary-button,
            .dashboard-section .primary-button,
            .dashboard-section .secondary-button,
            .dashboard-section a {
                min-width: 0 !important;
            }
            .dashboard-section input,
            .dashboard-section select {
                padding: 14px !important;
            }
            .dashboard-summary-grid {
                display: grid !important;
                gap: 14px !important;
            }
        }
    </style>
    <style>
        @keyframes spin-refresh {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @media (max-width: 768px) {
            body.swipe-refresh-enabled {
                touch-action: pan-x pan-y;
            }
        }
    </style>
</head>
<body class="library-settings swipe-refresh-enabled">
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); z-index: 9999; justify-content: center; align-items: center;"><div style="text-align: center;"><div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div><p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0;">Refreshing...</p></div></div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-mobile-header">
                <div class="mobile-user-info">
                    <h4><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p>Library Head</p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                
            </div>
            <div class="nav-section">
                <a href="dashboard/librarian_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="dashboard/school_announcements.php?service=library" data-tooltip="Announcements">
                    <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                    <span class="nav-text">Announcements</span>
                </a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $serviceOpen ? 'true' : 'false' ?>" data-tooltip="Services">
                        <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                        <span class="nav-text">Services</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu<?= $serviceOpen ? ' open' : '' ?>" aria-hidden="<?= $serviceOpen ? 'false' : 'true' ?>">
                        <a href="dashboard/guidance_dashboard.php?service=library" data-tooltip="Guidance">
                            <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                            <span class="nav-text">Guidance</span>
                        </a>
                        <a href="dashboard/library_dashboard.php?service=library" data-tooltip="Library">
                            <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                            <span class="nav-text">Library</span>
                        </a>
                        <a href="dashboard/clinic_dashboard.php?service=library" data-tooltip="Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                            <span class="nav-text">Clinic</span>
                        </a>
                        <a href="dashboard/ssc/ssc_dashboard.php?service=library" data-tooltip="SSC">
                            <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                            <span class="nav-text">SSC</span>
                        </a>
                        <a href="dashboard/scholarship/scholarship_dashboard.php?service=library" data-tooltip="Scholarship">
                            <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                            <span class="nav-text">Scholarship</span>
                        </a>
                        <a href="dashboard/ssaa_student_home.php?service=library" data-tooltip="Alumni">
                            <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                            <span class="nav-text">Alumni</span>
                        </a>
                    </div>
                </div>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $manageOpen ? 'true' : 'false' ?>" data-tooltip="Manage">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Manage</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu<?= $manageOpen ? ' open' : '' ?>" aria-hidden="<?= $manageOpen ? 'false' : 'true' ?>">
                        <a href="library_checkin.php" data-tooltip="QR Check-In">
                            <span class="nav-icon"><i class="fa-solid fa-qrcode"></i></span>
                            <span class="nav-text">QR Check-In</span>
                        </a>
                        <a href="library_catalog.php" data-tooltip="Digital Catalog">
                            <span class="nav-icon"><i class="fa-solid fa-book-open"></i></span>
                            <span class="nav-text">Digital Catalog</span>
                        </a>
                        <a href="library_inventory.php" data-tooltip="Inventory">
                            <span class="nav-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
                            <span class="nav-text">Inventory</span>
                        </a>
                        <a href="library_reports.php" data-tooltip="Reports">
                            <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                            <span class="nav-text">Reports</span>
                        </a>
                        <a class="active" href="library_settings.php" data-tooltip="Settings">
                            <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
                            <span class="nav-text">Settings</span>
                        </a>
                        <a href="library_qr.php" data-tooltip="Library QR">
                            <span class="nav-icon"><i class="fa-solid fa-qrcode"></i></span>
                            <span class="nav-text">Library QR</span>
                        </a>
                    </div>
                </div>
                <a href="dashboard/profile.php?service=library" data-tooltip="Profile">
                    <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                    <span class="nav-text">Profile</span>
                </a>
                <a href="dashboard/about.php?service=library" data-tooltip="About">
                    <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                    <span class="nav-text">About</span>
                </a>
            </div>
            <div class="nav-footer">
                <a href="logout.php" class="logout-link" data-tooltip="Logout">
                    <span class="nav-icon"><i class="fa-solid fa-sign-out-alt"></i></span>
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
                            <p>Library Management</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Library Head</span>
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
                        <a class="menu-link menu-footer-link" href="dashboard/about.php">View all announcements</a>
                    </div>

                    <div class="topbar-menu" id="profileMenu" role="menu" aria-label="Profile menu">
                        <div class="menu-item profile-menu-item" role="menuitem">
                            <a href="dashboard/profile.php" class="menu-link profile-link"><?= htmlspecialchars($user['full_name']) ?></a>
                            <span class="menu-subtext">View your account details</span>
                        </div>
                        <a href="dashboard/profile.php" class="menu-action">View profile</a>
                    </div>

                    <div class="topbar-menu" id="supportMenu" role="menu" aria-label="Support menu">
                        <div class="menu-header">
                            <strong>Support</strong>
                            <span class="menu-note">Need help?</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="dashboard/about.php">Help center</a>
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
                <!-- Hero Section -->
                    <section class="dashboard-intro library-page-header">
                        <div>
                            <div class="library-header__content">
                                <span class="eyebrow">LIBRARY SETTINGS</span>
                                <h1>Library Rules &amp; Schedules</h1>
                                <p class="dashboard-subtitle">Update borrowing limits and overdue fines from one control panel.</p>
                            </div>
                        </div>
                        <div class="library-header__art" aria-hidden="true"><i class="fa-solid fa-layer-group book-stack"></i><i class="fa-solid fa-book-open open-book"></i><i class="fa-solid fa-id-card library-card"></i><i class="fa-solid fa-circle-check library-check"></i><i class="fa-solid fa-location-pin library-pin"></i><i class="fa-solid fa-leaf library-leaf"></i></div>
                    </section>     

                    <?php if (!empty($message)): ?>
                        <section class="dashboard-section">
                            <div class="dashboard-summary-card <?= $messageType === 'error' ? 'alert error' : 'alert success' ?>" style="padding: 16px 20px; border-radius: 10px; margin-bottom: 30px; animation: slideIn 0.3s ease-out; <?= $messageType === 'error' ? 'background: #fee; border: 1px solid #fcc; color: #c33;' : 'background: #efe; border: 1px solid #cfc; color: #3c3;' ?>">
                                <?= htmlspecialchars($message) ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <!-- Features Grid -->
                    <section class="dashboard-section" style="margin-bottom: 40px;">
                        <h2 style="font-size: 1.5rem; margin-bottom: 20px; color: var(--deep-maroon);">Library Management Features</h2>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                            <!-- QR Check-In -->
                            <div style="background: #fcfaf5; border-radius: 12px; padding: 25px; color: #1f2937; box-shadow: 0 18px 40px rgba(15,23,42,0.08); border: 1px solid rgba(214,168,74,0.18); transition: transform 0.3s ease; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 24px 55px rgba(15,23,42,0.12)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 18px 40px rgba(15,23,42,0.08)'">
                                <div style="font-size: 2.5rem; margin-bottom: 15px; color: var(--deep-maroon);"><i class="fa-solid fa-door-open"></i></div>
                                <h3 style="font-size: 1.2rem; margin-bottom: 10px; color: var(--deep-maroon);">QR Check-In</h3>
                                <p style="font-size: 0.95rem; line-height: 1.5; color: #64748b;">Manage library entry logs. Track pending approvals, active visitors, and recent scan history in one clean, organized view.</p>
                                <a href="library_checkin.php" style="display: inline-block; margin-top: 15px; color: #2563eb; text-decoration: none; font-weight: 600; padding: 8px 16px; background: #eff6ff; border-radius: 6px; transition: background 0.3s;">Go to QR Check-In →</a>
                            </div>

                            <!-- Inventory -->
                            <div style="background: #fcfaf5; border-radius: 12px; padding: 25px; color: #1f2937; box-shadow: 0 18px 40px rgba(15,23,42,0.08); border: 1px solid rgba(214,168,74,0.18); transition: transform 0.3s ease; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 24px 55px rgba(15,23,42,0.12)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 18px 40px rgba(15,23,42,0.08)'">
                                <div style="font-size: 2.5rem; margin-bottom: 15px; color: #0ea5e9;"><i class="fa-solid fa-boxes-stacked"></i></div>
                                <h3 style="font-size: 1.2rem; margin-bottom: 10px; color: var(--deep-maroon);">Inventory</h3>
                                <p style="font-size: 0.95rem; line-height: 1.5; color: #64748b;">Track library copies. Manage book availability, low-stock alerts, and reservation pressure from one inventory panel.</p>
                                <a href="library_inventory.php" style="display: inline-block; margin-top: 15px; color: #10b981; text-decoration: none; font-weight: 600; padding: 8px 16px; background: #f0fdf4; border-radius: 6px; transition: background 0.3s;">Go to Inventory →</a>
                            </div>

                            <!-- Inventory Management (Catalog) -->
                            <div style="background: #fcfaf5; border-radius: 12px; padding: 25px; color: #1f2937; box-shadow: 0 18px 40px rgba(15,23,42,0.08); border: 1px solid rgba(214,168,74,0.18); transition: transform 0.3s ease; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 24px 55px rgba(15,23,42,0.12)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 18px 40px rgba(15,23,42,0.08)'">
                                <div style="font-size: 2.5rem; margin-bottom: 15px; color: #7c3aed;"><i class="fa-solid fa-book-open"></i></div>
                                <h3 style="font-size: 1.2rem; margin-bottom: 10px; color: var(--deep-maroon);">Digital Catalog</h3>
                                <p style="font-size: 0.95rem; line-height: 1.5; color: #64748b;">Manage physical borrows & fines. Search students, record borrows, mark returns, and manage fines in one place.</p>
                                <a href="library_catalog.php" style="display: inline-block; margin-top: 15px; color: #8b5cf6; text-decoration: none; font-weight: 600; padding: 8px 16px; background: #faf5ff; border-radius: 6px; transition: background 0.3s;">Go to Digital Catalog →</a>
                            </div>

                            <!-- Library Reports -->
                            <div style="background: #fcfaf5; border-radius: 12px; padding: 25px; color: #1f2937; box-shadow: 0 18px 40px rgba(15,23,42,0.08); border: 1px solid rgba(214,168,74,0.18); transition: transform 0.3s ease; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 24px 55px rgba(15,23,42,0.12)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 18px 40px rgba(15,23,42,0.08)'">
                                <div style="font-size: 2.5rem; margin-bottom: 15px; color: #f59e0b;"><i class="fa-solid fa-chart-line"></i></div>
                                <h3 style="font-size: 1.2rem; margin-bottom: 10px; color: var(--deep-maroon);">Library Reports</h3>
                                <p style="font-size: 0.95rem; line-height: 1.5; color: #64748b;">Comprehensive library analytics. Monitor borrowing trends, user activity, inventory status, and financial performance across all operations.</p>
                                <a href="library_reports.php" style="display: inline-block; margin-top: 15px; color: #f59e0b; text-decoration: none; font-weight: 600; padding: 8px 16px; background: #fffbeb; border-radius: 6px; transition: background 0.3s;">Go to Reports →</a>
                            </div>

                            <!-- Library QR -->
                            <div style="background: #fcfaf5; border-radius: 12px; padding: 25px; color: #1f2937; box-shadow: 0 18px 40px rgba(15,23,42,0.08); border: 1px solid rgba(214,168,74,0.18); transition: transform 0.3s ease; cursor: pointer;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 24px 55px rgba(15,23,42,0.12)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 18px 40px rgba(15,23,42,0.08)'">
                                <div style="font-size: 2.5rem; margin-bottom: 15px; color: #ef4444;"><i class="fa-solid fa-qrcode"></i></div>
                                <h3 style="font-size: 1.2rem; margin-bottom: 10px; color: var(--deep-maroon);">Library QR</h3>
                                <p style="font-size: 0.95rem; line-height: 1.5; color: var(--text-soft);">Generate the public library entry QR code. Use this QR for the library entrance so visitors can scan directly and open the entry form.</p>
                                <a href="library_qr.php" style="display: inline-block; margin-top: 15px; color: #ef4444; text-decoration: none; font-weight: 600; padding: 8px 16px; background: rgba(239,68,68,0.12); border-radius: 6px; transition: background 0.3s;">Go to Library QR →</a>
                            </div>
                        </div>
                    </section>

                    <!-- Policy Settings Form -->
                    <section class="dashboard-section" style="background: var(--surface-soft); border-radius: 12px; padding: 35px; box-shadow: 0 18px 40px rgba(15,23,42,0.08); border: 1px solid rgba(214,168,74,0.18);">
                        <h2 style="font-size: 1.5rem; margin-bottom: 10px; color: var(--deep-maroon);"><i class="fa-solid fa-book-open" style="margin-right: 10px; color: var(--soft-gold);"></i>Library Policy Settings</h2>
                        <p style="color: var(--text-soft); margin-bottom: 30px; font-size: 0.95rem;">Configure core borrowing rules and fine policies.</p>
                        
                        <form method="post">
                            <input type="hidden" name="action" value="save_settings">
                            
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 30px;">
                                <!-- Max Borrowed Books -->
                                <div style="background: white; border-radius: 10px; padding: 20px; border-left: 4px solid #2563eb;">
                                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: var(--deep-maroon); margin-bottom: 8px; font-size: 0.95rem;"><i class="fa-solid fa-book" style="color: #2563eb;"></i>Max Borrowed Books</label>
                                    <input type="number" name="max_borrowed_books" min="1" value="<?= htmlspecialchars($settings['max_borrowed_books']) ?>" required style="width: 100%; padding: 12px; border: 2px solid #cbd5e1; border-radius: 6px; font-size: 1rem; transition: border-color 0.3s; box-sizing: border-box;" onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#cbd5e1'">
                                    <p style="color: #475569; font-size: 0.85rem; margin-top: 8px;">Maximum number of books a student can borrow at once.</p>
                                </div>

                                <!-- Max Active Reservations -->
                                <div style="background: white; border-radius: 10px; padding: 20px; border-left: 4px solid #10b981;">
                                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: var(--deep-maroon); margin-bottom: 8px; font-size: 0.95rem;"><i class="fa-solid fa-calendar-check" style="color: #10b981;"></i>Max Active Reservations</label>
                                    <input type="number" name="max_active_reservations" min="0" value="<?= htmlspecialchars($settings['max_active_reservations']) ?>" required style="width: 100%; padding: 12px; border: 2px solid #cbd5e1; border-radius: 6px; font-size: 1rem; transition: border-color 0.3s; box-sizing: border-box;" onfocus="this.style.borderColor='#10b981'" onblur="this.style.borderColor='#cbd5e1'">
                                    <p style="color: #475569; font-size: 0.85rem; margin-top: 8px;">Maximum number of books a student can reserve simultaneously.</p>
                                </div>

                                <!-- Borrow Duration -->
                                <div style="background: white; border-radius: 10px; padding: 20px; border-left: 4px solid #8b5cf6;">
                                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: var(--deep-maroon); margin-bottom: 8px; font-size: 0.95rem;"><i class="fa-solid fa-hourglass-half" style="color: #8b5cf6;"></i>Borrow Duration</label>
                                    <input type="number" name="borrow_duration_school_days" min="1" value="<?= htmlspecialchars($settings['borrow_duration_school_days']) ?>" required style="width: 100%; padding: 12px; border: 2px solid #cbd5e1; border-radius: 6px; font-size: 1rem; transition: border-color 0.3s; box-sizing: border-box;" onfocus="this.style.borderColor='#8b5cf6'" onblur="this.style.borderColor='#cbd5e1'">
                                    <p style="color: #475569; font-size: 0.85rem; margin-top: 8px;">Number of school days before a borrowed book is due.</p>
                                </div>

                                <!-- Overdue Fine Per Day -->
                                <div style="background: white; border-radius: 10px; padding: 20px; border-left: 4px solid #ef4444;">
                                    <label style="display: flex; align-items: center; gap: 8px; font-weight: 700; color: var(--deep-maroon); margin-bottom: 8px; font-size: 0.95rem;"><i class="fa-solid fa-coins" style="color: #ef4444;"></i>Overdue Fine Per Day</label>
                                    <div style="position: relative;">
                                        <span style="position: absolute; left: 12px; top: 12px; font-size: 1rem; color: #475569;">₱</span>
                                        <input type="number" name="overdue_fine_per_day" min="0" step="0.01" value="<?= htmlspecialchars($settings['overdue_fine_per_day']) ?>" required style="width: 100%; padding: 12px 12px 12px 28px; border: 2px solid #cbd5e1; border-radius: 6px; font-size: 1rem; transition: border-color 0.3s; box-sizing: border-box;" onfocus="this.style.borderColor='#ef4444'" onblur="this.style.borderColor='#cbd5e1'">
                                    </div>
                                    <p style="color: #475569; font-size: 0.85rem; margin-top: 8px;">Daily fine amount for overdue books.</p>
                                </div>
                            </div>

                            <button type="submit" class="primary-button" style="background: var(--deep-maroon); color: white; padding: 14px 40px; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: transform 0.2s, background 0.2s; box-shadow: 0 4px 15px rgba(128, 0, 0, 0.2);" onmouseover="this.style.transform='translateY(-2px)'; this.style.background='#5b0f0f'" onmouseout="this.style.transform='translateY(0)'; this.style.background='var(--deep-maroon)'">
                                <i class="fa-solid fa-save" style="margin-right: 8px;"></i>Save Library Rules
                            </button>
                        </form>
                    </section>
            </div>
        </main>
    </div>
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
    <script src="assets/js/app.js" defer></script>
    <?php include 'AI CHAT BOT/chat_widget.php'; ?>
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
    </script>
</body>
</html>
