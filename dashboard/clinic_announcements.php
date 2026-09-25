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

// Get announcements
$activeAnnouncements = get_active_clinic_announcements();

// Handle form submissions
$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_announcement'])) {
        $announcementData = [
            'title' => $_POST['announcement_title'],
            'category' => $_POST['announcement_category'],
            'description' => $_POST['announcement_description'],
            'target_audience' => $_POST['target_audience'],
            'priority' => $_POST['priority'],
            'duration_days' => (int)$_POST['duration_days']
        ];

        if (create_clinic_announcement($announcementData, $user['id'])) {
            $message = 'Announcement created successfully!';
            $activeAnnouncements = get_active_clinic_announcements();
        } else {
            $message = 'Failed to create announcement.';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Announcements Management | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/clinic-header.css">
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

        :root {
            --clinic-maroon: #800000;
            --clinic-gold: #D4AF37;
            --clinic-light: #f8f9fa;
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
            transform: scale(1.05);
        }

        .btn-secondary {
            background: var(--clinic-gold);
            color: var(--clinic-maroon);
        }

        .inventory-tab-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
            padding: 10px;
            background: #ffffff;
            border-radius: 12px;
            justify-content: space-between;
            border: 1px solid #e5e7eb;
        }

        /* Small-screen swipeable announcement tabs and responsive create form */
        @media (max-width: 720px) {
            .announcement-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scroll-snap-type: x mandatory;
                padding-bottom: 8px;
            }

            .announcement-tabs::-webkit-scrollbar { display: none; }

            .announcement-tab-button {
                flex: 0 0 auto;
                min-width: 160px;
                margin: 0;
                scroll-snap-align: start;
            }

            /* Create form stacks and increases touch targets */
            #announcement-create-section form {
                display: grid;
                grid-template-columns: 1fr;
                gap: 12px;
            }

            #announcement-create-section .form-group label {
                font-size: 0.95rem;
            }

            #announcement-create-section .form-group input,
            #announcement-create-section .form-group select,
            #announcement-create-section .form-group textarea {
                font-size: 15px;
                padding: 12px;
            }

            #announcement-create-section .btn {
                width: 100%;
                padding: 14px 18px;
                font-size: 16px;
            }

            /* Reduce surrounding padding to fit mobile */
            #announcement-create-section { padding: 12px !important; }
        }

        /* Announcement History responsive card view */
        @media (max-width: 720px) {
            #announcement-history-section .data-table {
                display: block !important;
                width: 100% !important;
                border: none !important;
                margin: 0 !important;
            }

            #announcement-history-section .data-table thead { display: none !important; }

            #announcement-history-section .data-table tbody,
            #announcement-history-section .data-table tr {
                display: block !important;
                width: 100% !important;
                margin-bottom: 12px !important;
                border: 1px solid #e5e7eb !important;
                border-radius: 12px !important;
                padding: 12px !important;
                background: #fff !important;
                box-shadow: 0 2px 8px rgba(15,23,42,0.04) !important;
            }

            #announcement-history-section .data-table td {
                display: block !important;
                width: 100% !important;
                padding: 6px 0 !important;
                border: none !important;
                box-sizing: border-box !important;
            }

            #announcement-history-section .data-table td:before {
                content: attr(data-label) !important;
                display: block !important;
                font-size: 0.78rem !important;
                font-weight: 700 !important;
                color: #475569 !important;
                margin-bottom: 6px !important;
                text-transform: uppercase !important;
                letter-spacing: 0.02em !important;
            }
        }

        .announcement-tab-button,
        .library-tab-button {
            border: 2px solid transparent;
            background: transparent;
            color: #334155;
            padding: 14px 16px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            min-width: 150px;
            text-align: center;
            flex: 1;
        }

        .announcement-tab-button:hover,
        .library-tab-button:hover {
            background: var(--clinic-maroon);
            color: white;
            border-color: var(--clinic-gold);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
        }

        .announcement-tab-button.active,
        .library-tab-button.active {
            background: var(--clinic-maroon);
            color: #ffffff;
            border-color: var(--clinic-gold);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
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

        .table-responsive {
            overflow-x: auto;
            margin-bottom: 20px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .data-table th, .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .data-table th {
            background: var(--clinic-light);
            color: var(--clinic-maroon);
            font-weight: 600;
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

        .priority-high { color: #dc3545; }
        .priority-medium { color: #ffc107; }
        .priority-low { color: #28a745; }

        .announcement-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid var(--clinic-maroon);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .announcement-card h4 {
            margin: 0 0 10px 0;
            color: var(--clinic-maroon);
        }

        .announcement-card .meta {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
        }

        .announcement-card .description {
            color: #333;
            line-height: 1.5;
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
                            <span class="nav-text">SSAA</span>
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
                <?php if ($message): ?>
                    <div class="alert-card <?= $messageType ?>">
                        <i class="fa-solid fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                    <section class="clinic-page-header">
                        <div class="clinic-header__content">
                            <span class="eyebrow">CLINIC ANNOUNCEMENTS</span>
                            <h1>Clinic Announcements</h1>
                            <p class="clinic-header__subtitle">Publish alerts and messages for clinic staff, students, and visitors.</p>
                        </div>
                        <div class="clinic-header__art" aria-hidden="true"><i class="fa-solid fa-clipboard medical-clipboard"></i><i class="fa-solid fa-plus medical-cross"></i><i class="fa-solid fa-circle-check medical-check"></i><i class="fa-solid fa-stethoscope medical-stethoscope"></i><i class="fa-solid fa-location-pin medical-pin"></i><i class="fa-solid fa-leaf medical-leaf"></i></div>
                    </section>

                    <div class="inventory-tab-bar announcement-tabs" role="tablist" aria-label="Announcements navigation">
                        <button type="button" class="announcement-tab-button library-tab-button active" id="announcement-tab-create" onclick="switchAnnouncementTab('create')">Create Announcement</button>
                        <button type="button" class="announcement-tab-button library-tab-button" id="announcement-tab-active" onclick="switchAnnouncementTab('active')">Active Announcements</button>
                    </div>

                    <!-- Create Announcement Section -->
                    <div id="announcement-create-section" class="content-section" style="display: block;">
                        <div class="section-header">
                            <h2><i class="fa-solid fa-plus"></i> Create New Announcement</h2>
                        </div>

                        <form method="POST">
                            <div class="form-group">
                                <label for="announcement_title">Title</label>
                                <input type="text" id="announcement_title" name="announcement_title" required>
                            </div>

                            <div class="form-group">
                                <label for="announcement_category">Category</label>
                                <select id="announcement_category" name="announcement_category" required>
                                    <option value="">Select category</option>
                                    <option value="health">Health & Safety</option>
                                    <option value="services">Clinic Services</option>
                                    <option value="vaccination">Vaccination</option>
                                    <option value="emergency">Emergency</option>
                                    <option value="general">General</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="announcement_description">Description</label>
                                <textarea id="announcement_description" name="announcement_description" rows="4" required></textarea>
                            </div>

                            <div class="form-group">
                                <label for="target_audience">Target Audience</label>
                                <select id="target_audience" name="target_audience" required>
                                    <option value="all">All Students & Teachers</option>
                                    <option value="students">Students Only</option>
                                    <option value="teachers">Teachers Only</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="priority">Priority</label>
                                <select id="priority" name="priority" required>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="duration_days">Display Duration (days)</label>
                                <input type="number" id="duration_days" name="duration_days" min="1" max="30" value="7" required>
                            </div>

                            <button type="submit" name="create_announcement" class="btn btn-primary">
                                <i class="fa-solid fa-paper-plane"></i> Create Announcement
                            </button>
                        </form>
                    </div>

                    <!-- Active Announcements -->
                    <div id="announcement-active-section" class="content-section" style="display: none;">
                        <div class="section-header">
                            <h2><i class="fa-solid fa-bullhorn"></i> Active Announcements</h2>
                        </div>

                        <?php if (!empty($activeAnnouncements)): ?>
                            <?php foreach ($activeAnnouncements as $announcement): ?>
                            <div class="announcement-card">
                                <h4>
                                    <span class="priority-<?= htmlspecialchars($announcement['priority']) ?>">
                                        [<?= ucfirst(htmlspecialchars($announcement['priority'])) ?>]
                                    </span>
                                    <?= htmlspecialchars($announcement['title']) ?>
                                </h4>
                                <div class="meta">
                                    <strong>Category:</strong> <?= htmlspecialchars(ucfirst($announcement['category'])) ?> |
                                    <strong>Target:</strong> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $announcement['target_audience']))) ?> |
                                    <strong>Created:</strong> <?= htmlspecialchars(date('M d, Y', strtotime($announcement['created_at']))) ?> |
                                    <strong>Expires:</strong> <?= htmlspecialchars(date('M d, Y', strtotime($announcement['expires_at']))) ?>
                                </div>
                                <div class="description">
                                    <?= nl2br(htmlspecialchars($announcement['description'])) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #666;">
                            <i class="fa-solid fa-bullhorn" style="font-size: 48px; margin-bottom: 20px; color: var(--clinic-maroon);"></i>
                            <h3>No Active Announcements</h3>
                            <p>Create health announcements to inform students and teachers about important updates.</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Announcement History -->
                    <div id="announcement-history-section" class="content-section">
                        <div class="section-header">
                            <h2><i class="fa-solid fa-history"></i> Announcement History</h2>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Priority</th>
                                        <th>Target</th>
                                        <th>Created</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Get all announcements including expired ones
                                    $pdo = get_db();
                                    $stmt = $pdo->prepare('SELECT ca.*, u.full_name as created_by_name FROM clinic_announcements ca LEFT JOIN users u ON ca.created_by = u.id ORDER BY ca.created_at DESC LIMIT 50');
                                    $stmt->execute();
                                    $allAnnouncements = $stmt->fetchAll();

                                    foreach ($allAnnouncements as $announcement):
                                    ?>
                                    <tr>
                                        <td data-label="Title">
                                            <strong><?= htmlspecialchars($announcement['title']) ?></strong>
                                            <br><small style="color: #666;"><?= htmlspecialchars(substr($announcement['description'], 0, 50)) ?>...</small>
                                        </td>
                                        <td data-label="Category"><?= htmlspecialchars(ucfirst($announcement['category'])) ?></td>
                                        <td data-label="Priority"><span class="priority-<?= htmlspecialchars($announcement['priority']) ?>"><?= ucfirst(htmlspecialchars($announcement['priority'])) ?></span></td>
                                        <td data-label="Target"><?= htmlspecialchars(ucfirst($announcement['target_audience'])) ?></td>
                                        <td data-label="Created"><?= htmlspecialchars(date('M d, Y', strtotime($announcement['created_at']))) ?></td>
                                        <td data-label="Status">
                                            <?php if ($announcement['is_active'] && (!$announcement['expires_at'] || strtotime($announcement['expires_at']) > time())): ?>
                                                <span style="color: #28a745; font-weight: bold;">Active</span>
                                            <?php elseif ($announcement['expires_at'] && strtotime($announcement['expires_at']) <= time()): ?>
                                                <span style="color: #6c757d;">Expired</span>
                                            <?php else: ?>
                                                <span style="color: #dc3545;">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
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
    <script>
        function switchAnnouncementTab(tab) {
            const createSection = document.getElementById('announcement-create-section');
            const activeSection = document.getElementById('announcement-active-section');
            const createTab = document.getElementById('announcement-tab-create');
            const activeTab = document.getElementById('announcement-tab-active');

            if (tab === 'active') {
                createSection.style.display = 'none';
                activeSection.style.display = 'block';
                createTab.classList.remove('active');
                activeTab.classList.add('active');
            } else {
                createSection.style.display = 'block';
                activeSection.style.display = 'none';
                createTab.classList.add('active');
                activeTab.classList.remove('active');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            switchAnnouncementTab('create');
            enableAnnouncementTabSwipe();
        });

        // Swipe handling for announcement tabs (active between 320px and 720px)
        function enableAnnouncementTabSwipe() {
            const minW = 320, maxW = 720;
            let startX = 0, startY = 0, tracking = false;
            const tabs = ['create','active'];

            function onStart(e) {
                if (window.innerWidth > maxW || window.innerWidth < minW) return;
                const t = e.touches ? e.touches[0] : e;
                startX = t.clientX; startY = t.clientY; tracking = true;
            }

            function onEnd(e) {
                if (!tracking) return; tracking = false;
                const t = (e.changedTouches && e.changedTouches[0]) || e;
                const dx = t.clientX - startX; const dy = t.clientY - startY;
                if (Math.abs(dy) > Math.abs(dx)) return;
                const threshold = 50;
                const current = document.querySelector('.announcement-tab-button.active');
                const currentId = current && current.id === 'announcement-tab-active' ? 'active' : 'create';
                const idx = tabs.indexOf(currentId);
                if (dx <= -threshold && idx < tabs.length-1) { // swipe left
                    const next = tabs[idx+1]; switchAnnouncementTab(next);
                    const btn = document.getElementById('announcement-tab-' + next);
                    if (btn && btn.scrollIntoView) btn.scrollIntoView({behavior:'smooth', inline:'center'});
                } else if (dx >= threshold && idx > 0) { // swipe right
                    const prev = tabs[idx-1]; switchAnnouncementTab(prev);
                    const btn = document.getElementById('announcement-tab-' + prev);
                    if (btn && btn.scrollIntoView) btn.scrollIntoView({behavior:'smooth', inline:'center'});
                }
            }

            const scroller = document.querySelector('.main-scroll') || document.body;
            scroller.addEventListener('touchstart', onStart, {passive:true});
            scroller.addEventListener('touchend', onEnd, {passive:true});
        }
    </script>
    <script src="../assets/js/app.js" defer></script>
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