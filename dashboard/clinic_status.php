<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/clinic_functions.php';
require_login();
$user = current_user();
if ($user['role'] !== 'teacher' || $user['head_service'] !== 'clinic') {
    header('Location: /auth/teacher_login.php');
    exit;
}

// Initialize clinic schema
ensure_clinic_schema();

// Manage section remains collapsed by default on clinic management pages
$is_manage_open = false;

// Get clinic data
$clinicStatus = get_clinic_status();

// Handle form submissions
$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $isAvailable = (int)$_POST['is_available'] === 1;
        $reason = $_POST['reason'] ?? null;
        $duration = !empty($_POST['duration_hours']) ? (int)$_POST['duration_hours'] : null;

        if (update_clinic_status($isAvailable, $reason, $duration, $user['id'])) {
            $message = 'Clinic status updated successfully!';
            $clinicStatus = get_clinic_status();
        } else {
            $message = 'Failed to update clinic status.';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clinic Status Management | PASS Support System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/clinic-header.css">
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
        :root {
            --clinic-maroon: #800000;
            --clinic-gold: #D4AF37;
            --clinic-light: #f8f9fa;
        }

        .clinic-status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-right: 1rem;
        }

        .clinic-status-indicator.available {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .clinic-status-indicator.unavailable {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .clinic-card {
            background: linear-gradient(135deg, var(--clinic-maroon), var(--clinic-gold));
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .clinic-card h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
        }

        .clinic-card p {
            margin: 0;
            opacity: 0.9;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--clinic-maroon);
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background: var(--clinic-maroon);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            background: #600000;
            border-color: #600000;
            box-shadow: 0 8px 20px rgba(128, 0, 0, 0.18);
        }

        /* Stronger override specifically for the Update Clinic Status button */
        #updateClinicStatusBtn {
            background: var(--clinic-maroon) !important;
            color: #ffffff !important;
            border: 1px solid var(--clinic-maroon) !important;
            transition: all 0.2s ease !important;
        }

        #updateClinicStatusBtn:hover,
        #updateClinicStatusBtn:focus {
            transform: translateY(-2px) !important;
            background: #600000 !important;
            border-color: #600000 !important;
            box-shadow: 0 8px 20px rgba(128, 0, 0, 0.18) !important;
            color: #ffffff !important;
        }

        .alert-card {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-card.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-card.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .content-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            margin: 0;
            color: var(--clinic-maroon);
        }

        .section-subtitle {
            margin-top: 10px;
            color: #4b5563;
            font-size: 0.95rem;
            line-height: 1.6;
            max-width: 760px;
        }

        .page-header-panel {
            margin-bottom: 48px;
        }

        @media (max-width: 720px) {
            /* Main layout */
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

            /* Page header */
            .page-header-panel {
                padding: 12px 8px !important;
                margin-bottom: 16px !important;
            }

            .section-header {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 12px !important;
            }

            .section-header h2 {
                font-size: 1.2rem !important;
                line-height: 1.3 !important;
                width: 100% !important;
            }

            .section-subtitle {
                font-size: 0.85rem !important;
                line-height: 1.4 !important;
                margin-top: 6px !important;
            }

            /* Alert cards */
            .alert-card {
                flex-direction: column !important;
                align-items: flex-start !important;
                padding: 12px !important;
                font-size: 0.9rem !important;
                margin: 12px 8px !important;
                gap: 8px !important;
            }

            .alert-card i {
                font-size: 1.2rem !important;
            }

            /* Closed clinic warning */
            [style*="background: #dc3545"] {
                padding: 16px 8px !important;
                margin: 12px 8px !important;
                border-radius: 8px !important;
                text-align: center !important;
            }

            [style*="background: #dc3545"] h3 {
                font-size: 1.1rem !important;
                margin: 8px 0 !important;
            }

            [style*="background: #dc3545"] p {
                font-size: 0.85rem !important;
                margin: 4px 0 !important;
            }

            /* Content sections */
            .content-section {
                padding: 16px 8px !important;
                margin: 12px 8px !important;
                border-radius: 8px !important;
                box-shadow: 0 1px 3px rgba(0,0,0,0.08) !important;
            }

            .content-section h2 {
                font-size: 1.05rem !important;
                margin-bottom: 12px !important;
            }

            /* Clinic card */
            .clinic-card {
                padding: 14px !important;
                margin-bottom: 16px !important;
                border-radius: 8px !important;
            }

            .clinic-card h3 {
                font-size: 1rem !important;
                margin-bottom: 8px !important;
                line-height: 1.3 !important;
            }

            .clinic-card p {
                font-size: 0.85rem !important;
                margin: 4px 0 !important;
            }

            /* Form groups */
            .form-group {
                margin-bottom: 12px !important;
            }

            .form-group label {
                font-size: 0.9rem !important;
                margin-bottom: 4px !important;
            }

            .form-group input,
            .form-group select,
            .form-group textarea {
                font-size: 16px !important;
                padding: 12px !important;
                border-radius: 6px !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }

            .form-group textarea {
                min-height: 80px !important;
            }

            /* Buttons */
            .btn {
                width: 100% !important;
                padding: 12px 16px !important;
                font-size: 0.95rem !important;
                border-radius: 6px !important;
                display: block !important;
            }

            /* Table responsive */
            table {
                font-size: 0.85rem !important;
            }

            table thead {
                display: none !important;
            }

            table tbody tr {
                display: block !important;
                margin-bottom: 12px !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 6px !important;
                padding: 12px !important;
                background: white !important;
            }

            table tbody td {
                display: block !important;
                padding: 8px 0 !important;
                text-align: left !important;
                border: none !important;
            }

            table tbody td:before {
                content: attr(data-label) !important;
                font-weight: 600 !important;
                color: var(--clinic-maroon) !important;
                display: block !important;
                font-size: 0.8rem !important;
                margin-bottom: 4px !important;
            }

            /* Feedback table specific */
            #feedback-table tbody td[style*="text-align: center"] {
                text-align: left !important;
            }

            /* Overflow container for tables */
            [style*="overflow-x: auto"] {
                overflow-x: visible !important;
                padding: 0 !important;
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
<body class="swipe-refresh-enabled">
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); z-index: 9999; justify-content: center; align-items: center;"><div style="text-align: center;"><div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div><p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0;">Refreshing...</p></div></div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-mobile-header">
                <div class="mobile-user-info">
                    <h4><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p>Clinic Head</p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                
            </div>
            <div class="nav-section">
                <a href="nurse_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="school_announcements.php?service=clinic" data-tooltip="Announcements">
                    <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
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
                        <a href="librarian_home.php" data-tooltip="Library">
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
                    <button type="button" class="nav-toggle" aria-expanded="<?= $is_manage_open ? 'true' : 'false' ?>" data-tooltip="Manage">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Manage</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu <?= $is_manage_open ? 'open' : '' ?>" aria-hidden="<?= $is_manage_open ? 'false' : 'true' ?>">
                        <a href="clinic_status.php" class="active" data-tooltip="Clinic Status">
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
                <a href="about.php?service=clinic" data-tooltip="About">
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
                    <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>Clinic Management</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="clinic-status-indicator <?= $clinicStatus['is_available'] ? 'available' : 'unavailable' ?>">
                        <i class="fa-solid fa-circle"></i>
                        Clinic: <?= $clinicStatus['is_available'] ? 'Open' : 'Closed' ?>
                    </div>
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Clinic Head</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Profile" data-tooltip="Profile" data-menu-target="profileMenu"><i class="fa-solid fa-user"></i></button>
                </div>
            </header>
            <div class="page-overlay" id="pageOverlay"></div>
            <div class="main-scroll">
                <section class="clinic-page-header">
                    <div class="clinic-header__content">
                        <span class="eyebrow">CLINIC STATUS</span>
                        <h1>Clinic Status Management</h1>
                        <p class="clinic-header__subtitle">Monitor availability and update clinic readiness in real time.</p>
                    </div>
                    <div class="clinic-header__art" aria-hidden="true"><i class="fa-solid fa-clipboard medical-clipboard"></i><i class="fa-solid fa-plus medical-cross"></i><i class="fa-solid fa-circle-check medical-check"></i><i class="fa-solid fa-stethoscope medical-stethoscope"></i><i class="fa-solid fa-location-pin medical-pin"></i><i class="fa-solid fa-leaf medical-leaf"></i></div>
                </section>
                <?php if ($message): ?>
                    <div class="alert-card <?= $messageType ?>">
                        <i class="fa-solid fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <?php if (!$clinicStatus['is_available']): ?>
                <div style="background: #dc3545; color: white; padding: 20px; border-radius: 12px; margin-bottom: 30px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.15);">
                    <i class="fa-solid fa-exclamation-triangle" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                    <h3 style="margin: 10px 0; font-size: 20px;">⚠️ CLINIC IS CURRENTLY CLOSED</h3>
                    <p style="margin: 5px 0; font-size: 16px;">No Nurse Available</p>
                    <?php if ($clinicStatus['reason']): ?>
                        <p style="margin: 10px 0; font-size: 14px;"><strong>Reason:</strong> <?= htmlspecialchars($clinicStatus['reason']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Clinic Status Management -->
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fa-solid fa-clock"></i> Clinic Status Management</h2>
                    </div>
                    <div class="clinic-card" style="<?= !$clinicStatus['is_available'] ? 'background: linear-gradient(135deg, #dc3545, #c82333); border: 2px solid #a71d2a;' : '' ?>">
                        <h3><i class="fa-solid fa-<?= $clinicStatus['is_available'] ? 'circle-check' : 'circle-xmark' ?>"></i> Current Status: <?= $clinicStatus['is_available'] ? 'OPEN - Nurse Available' : 'CLOSED - No Nurse Available' ?></h3>
                        <p><?= $clinicStatus['is_available'] ? 'Clinic is currently accepting patients' : 'Clinic is currently CLOSED and NOT accepting patients' ?></p>
                        <?php if (!$clinicStatus['is_available'] && $clinicStatus['reason']): ?>
                            <p><strong>Reason:</strong> <?= htmlspecialchars($clinicStatus['reason']) ?></p>
                        <?php endif; ?>
                    </div>

                    <form method="POST">
                        <div class="form-group">
                            <label for="clinic_status_select">Clinic Nurse Status</label>
                            <select id="clinic_status_select" name="is_available" required>
                                <option value="1" <?= $clinicStatus['is_available'] ? 'selected' : '' ?>>Nurse Available - Clinic Open</option>
                                <option value="0" <?= !$clinicStatus['is_available'] ? 'selected' : '' ?>>No Nurse Available - Clinic Closed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="reason">Additional Details/Reason</label>
                            <input type="text" id="reason" name="reason" placeholder="e.g., Lunch break, Medical emergency, etc." value="<?= htmlspecialchars($clinicStatus['reason'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="duration_hours">Duration (hours, optional)</label>
                            <input type="number" id="duration_hours" name="duration_hours" min="1" max="24" placeholder="Leave empty for indefinite">
                        </div>
                        <button id="updateClinicStatusBtn" type="submit" name="update_status" class="btn btn-primary">
                            <i class="fa-solid fa-save"></i> Update Clinic Status
                        </button>
                    </form>
                </div>

                <!-- Clinic Feedback Section -->
                <div class="content-section" style="margin-top: 40px; padding-top: 30px; border-top: 2px solid #e2e8f0;">
                    <div class="section-header">
                        <h2><i class="fa-solid fa-comments"></i> Feedback Received</h2>
                    </div>
                    <p class="section-subtitle">View all feedback and ratings submitted about our clinic services</p>
                    
                    <div style="margin-top: 20px; overflow-x: auto;">
                        <table id="feedback-table" style="width: 100%; border-collapse: collapse; background: white;">
                            <thead>
                                <tr style="background: #f8f9fa; border-bottom: 2px solid #e2e8f0;">
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151;">Student Name</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151;">Email</th>
                                    <th style="padding: 12px; text-align: center; font-weight: 600; color: #374151;">Rating</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151;">Feedback</th>
                                    <th style="padding: 12px; text-align: left; font-weight: 600; color: #374151;">Date Submitted</th>
                                </tr>
                            </thead>
                            <tbody id="feedback-tbody">
                                <tr>
                                    <td colspan="5" style="padding: 20px; text-align: center; color: #6b7280;">Loading feedback...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="feedback-empty" style="display: none; text-align: center; padding: 40px; color: #6b7280;">
                        <i class="fa-solid fa-inbox" style="font-size: 48px; color: #d1d5db; margin-bottom: 16px; display: block;"></i>
                        <p style="font-size: 16px;">No feedback received yet. Students can submit feedback from their clinic dashboard.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/app.js" defer></script>
    <script>
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
    <script>
        // Load all clinic feedback
        async function loadAllClinicFeedback() {
            try {
                const response = await fetch('/THESIS/SUPPORTSERVICESYSTEM/includes/api_clinic_feedback.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'get_all'
                    }),
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (result.success && result.data && result.data.length > 0) {
                    const tbody = document.getElementById('feedback-tbody');
                    tbody.innerHTML = '';

                    result.data.forEach(feedback => {
                        const date = new Date(feedback.created_at).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });

                        const stars = '★'.repeat(feedback.rating) + '☆'.repeat(5 - feedback.rating);
                        const ratingTexts = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                        const ratingLabel = ratingTexts[feedback.rating] || '';

                        const row = `
                            <tr style="border-bottom: 1px solid #e2e8f0; hover: background: #f9fafb;">
                                <td data-label="Student Name" style="padding: 12px; color: #111827;">${feedback.full_name}</td>
                                <td data-label="Email" style="padding: 12px; color: #6b7280; font-size: 0.9em;">${feedback.email}</td>
                                <td data-label="Rating" style="padding: 12px; text-align: center; color: #fbbf24; font-size: 16px;" title="${ratingLabel}">${stars}</td>
                                <td data-label="Feedback" style="padding: 12px; color: #374151; max-width: 300px; word-break: break-word;">${feedback.comments || '-'}</td>
                                <td data-label="Date Submitted" style="padding: 12px; color: #6b7280; font-size: 0.9em;">${date}</td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });

                    document.getElementById('feedback-empty').style.display = 'none';
                } else {
                    document.getElementById('feedback-tbody').innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #6b7280;">No feedback received yet.</td></tr>';
                    document.getElementById('feedback-empty').style.display = 'block';
                }
            } catch (error) {
                console.error('Error loading feedback:', error);
                document.getElementById('feedback-tbody').innerHTML = '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #991b1b;">Error loading feedback</td></tr>';
            }
        }

        // Load feedback when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadAllClinicFeedback();
        });
    </script>
    <?php include '../AI CHAT BOT/chat_widget.php'; ?>
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