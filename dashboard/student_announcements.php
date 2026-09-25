<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/clinic_functions.php';
require_login();
$user = current_user();
if ($user['role'] !== 'student') {
    header('Location: ../auth/student_login.php');
    exit;
}

$pdo = get_db();
$currentPage = 'student_announcements.php';

// Get clinic announcements
$clinicAnnouncements = [];
if (function_exists('get_active_clinic_announcements')) {
    $clinicAnnouncements = get_active_clinic_announcements('students');
}

// Get school calendar events
$calendarEvents = [];
$stmt = $pdo->prepare('SELECT id, event_date, event_type, description, start_time, end_time FROM library_calendar_blocks WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 20');
$stmt->execute();
$calendarEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get SSC events
$sscEvents = [];
$stmt = $pdo->prepare('SELECT id, event_name, event_date, event_location, description, event_photo FROM ssc_events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 20');
$stmt->execute();
$sscEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get scholarship announcements
$scholarshipAnnouncements = [];
$stmt = $pdo->prepare('SELECT id, title, description, deadline, image_path, created_at FROM scholarship_announcements ORDER BY created_at DESC LIMIT 20');
$stmt->execute();
$scholarshipAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$displayCourse = $user['course'] ?? $user['course_year'] ?? 'Course';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Announcements | PASS Support System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .announcement-grid {
            display: grid;
            gap: 16px;
        }

        .announcement-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border-left: 4px solid #0066cc;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .announcement-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .announcement-card.clinic {
            border-left-color: #800000;
        }

        .announcement-card.calendar {
            border-left-color: #28a745;
        }

        .announcement-card.ssc {
            border-left-color: #ff9800;
        }

        .announcement-card.scholarship {
            border-left-color: #6f42c1;
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .announcement-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0;
        }

        .announcement-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .announcement-badge.clinic {
            background: #fff0f0;
            color: #800000;
        }

        .announcement-badge.calendar {
            background: #f0fff4;
            color: #28a745;
        }

        .announcement-badge.ssc {
            background: #fff8f0;
            color: #ff9800;
        }

        .announcement-badge.scholarship {
            background: #f5f1ff;
            color: #6f42c1;
        }

        .announcement-meta {
            display: flex;
            gap: 16px;
            margin-bottom: 12px;
            font-size: 13px;
            color: #666;
        }

        .announcement-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .announcement-description {
            color: #333;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .announcement-description p {
            margin: 0;
        }

        .announcement-image {
            width: 100%;
            max-height: 250px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .announcement-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid #eee;
            font-size: 13px;
            color: #666;
        }

        .event-time {
            background: #f0f4f8;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .tabs-container {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 24px;
            background: #f8f9fa;
            border-radius: 10px 10px 0 0;
            overflow-x: auto;
            padding: 0 0 0 0;
        }

        .tab-button {
            padding: 12px 20px;
            border: none;
            background: transparent;
            color: #666;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
        }

        .tab-button.active {
            color: #0066cc;
            border-bottom-color: #0066cc;
            background: white;
        }

        .tab-button:hover {
            color: #0066cc;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .event-type-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            background: #f0f4f8;
            color: #0066cc;
        }

        .priority-high {
            background: #fff3cd;
            color: #856404;
        }

        .priority-medium {
            background: #e2e3e5;
            color: #383d41;
        }

        .priority-low {
            background: #d1ecf1;
            color: #0c5460;
        }
    </style>
</head>
<body class="student-dashboard">
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <button type="button" class="nav-search-btn" aria-label="Search" data-tooltip="Search">
                    <i class="fa-solid fa-search"></i>
                </button>
            </div>
            <div class="nav-section">
                <a href="student_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="student_announcements.php" class="active" data-tooltip="Announcements">
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
                            <span class="nav-text">Supreme Student Council</span>
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
                    <div class="nav-brand">
                        <img src="../IMG ASSETS/passlogo.png" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>Student Portal</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta"><?= htmlspecialchars($displayCourse) ?></span>
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
                        <?php if (!empty($clinicAnnouncements)): ?>
                            <div class="menu-items">
                                <?php foreach (array_slice($clinicAnnouncements, 0, 3) as $notification): ?>
                                    <div class="menu-item notification-item" role="menuitem">
                                        <p class="notification-title"><?= htmlspecialchars($notification['title']) ?></p>
                                        <p class="notification-meta"><?= htmlspecialchars(date('M j, Y', strtotime($notification['created_at']))) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="menu-empty">No new announcements.</div>
                        <?php endif; ?>
                        <a class="menu-link menu-footer-link" href="student_announcements.php">View all announcements</a>
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
                <section class="page-section">
                    <div class="section-header">
                        <h1><i class="fa-solid fa-bell"></i> Announcements</h1>
                        <p>Stay updated with the latest news and announcements from all services</p>
                    </div>

                    <div class="management-content">
                        <!-- Tabs Navigation -->
                        <div class="tabs-container">
                            <button class="tab-button active" onclick="switchTab('all', this)">
                                <i class="fa-solid fa-list"></i> All Announcements
                            </button>
                            <button class="tab-button" onclick="switchTab('clinic', this)">
                                <i class="fa-solid fa-stethoscope"></i> Clinic
                            </button>
                            <button class="tab-button" onclick="switchTab('calendar', this)">
                                <i class="fa-solid fa-calendar"></i> School Calendar
                            </button>
                            <button class="tab-button" onclick="switchTab('ssc', this)">
                                <i class="fa-solid fa-award"></i> SSC Events
                            </button>
                            <button class="tab-button" onclick="switchTab('scholarship', this)">
                                <i class="fa-solid fa-hand-holding-dollar"></i> Scholarships
                            </button>
                        </div>

                        <!-- All Announcements Tab -->
                        <div id="all" class="tab-content active">
                            <div class="announcement-grid">
                                <?php
                                // Combine all announcements with timestamps for sorting
                                $allAnnouncements = [];
                                
                                foreach ($clinicAnnouncements as $ann) {
                                    $allAnnouncements[] = array_merge($ann, ['type' => 'clinic', 'sort_date' => $ann['created_at']]);
                                }
                                foreach ($calendarEvents as $evt) {
                                    $allAnnouncements[] = array_merge($evt, ['type' => 'calendar', 'sort_date' => $evt['event_date']]);
                                }
                                foreach ($sscEvents as $evt) {
                                    $allAnnouncements[] = array_merge($evt, ['type' => 'ssc', 'sort_date' => $evt['event_date']]);
                                }
                                foreach ($scholarshipAnnouncements as $ann) {
                                    $allAnnouncements[] = array_merge($ann, ['type' => 'scholarship', 'sort_date' => $ann['created_at']]);
                                }
                                
                                // Sort by date
                                usort($allAnnouncements, function($a, $b) {
                                    return strtotime($b['sort_date']) - strtotime($a['sort_date']);
                                });
                                
                                if (empty($allAnnouncements)): ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fa-solid fa-inbox"></i></div>
                                        <p>No announcements at this time</p>
                                    </div>
                                <?php else:
                                    foreach ($allAnnouncements as $ann): ?>
                                        <div class="announcement-card <?= htmlspecialchars($ann['type']) ?>">
                                            <div class="announcement-header">
                                                <h3 class="announcement-title">
                                                    <?= htmlspecialchars($ann['type'] === 'calendar' ? $ann['event_type'] : ($ann['type'] === 'ssc' ? $ann['event_name'] : $ann['title'])) ?>
                                                </h3>
                                                <span class="announcement-badge <?= htmlspecialchars($ann['type']) ?>">
                                                    <?= ucfirst($ann['type']) ?>
                                                </span>
                                            </div>
                                            <div class="announcement-meta">
                                                <span class="announcement-meta-item">
                                                    <i class="fa-solid fa-calendar-days"></i>
                                                    <?= date('M j, Y', strtotime($ann['type'] === 'calendar' || $ann['type'] === 'ssc' ? $ann['event_date'] : $ann['created_at'])) ?>
                                                </span>
                                                <?php if ($ann['type'] === 'calendar'): ?>
                                                    <span class="announcement-meta-item event-type-badge">
                                                        <?= ucfirst($ann['event_type']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($ann['type'] === 'clinic' && !empty($ann['priority'])): ?>
                                                    <span class="announcement-meta-item priority-<?= htmlspecialchars(strtolower($ann['priority'])) ?>">
                                                        Priority: <?= ucfirst($ann['priority']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($ann['type'] === 'ssc' && !empty($ann['event_photo'])): ?>
                                                <img src="<?= htmlspecialchars($ann['event_photo']) ?>" alt="Event photo" class="announcement-image">
                                            <?php endif; ?>
                                            <?php if ($ann['type'] === 'scholarship' && !empty($ann['image_path'])): ?>
                                                <img src="<?= htmlspecialchars($ann['image_path']) ?>" alt="Scholarship image" class="announcement-image">
                                            <?php endif; ?>
                                            <div class="announcement-description">
                                                <p><?= nl2br(htmlspecialchars(substr($ann['type'] === 'calendar' ? $ann['description'] : ($ann['type'] === 'ssc' ? $ann['description'] : $ann['description']), 0, 300))) ?><?= strlen($ann['description']) > 300 ? '...' : '' ?></p>
                                            </div>
                                            <div class="announcement-footer">
                                                <?php if ($ann['type'] === 'calendar' && !empty($ann['start_time'])): ?>
                                                    <span class="event-time">
                                                        <i class="fa-solid fa-clock"></i> 
                                                        <?= date('H:i', strtotime($ann['start_time'])) ?> - <?= date('H:i', strtotime($ann['end_time'])) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($ann['type'] === 'ssc' && !empty($ann['event_location'])): ?>
                                                    <span><i class="fa-solid fa-map-pin"></i> <?= htmlspecialchars($ann['event_location']) ?></span>
                                                <?php endif; ?>
                                                <?php if ($ann['type'] === 'scholarship' && !empty($ann['deadline'])): ?>
                                                    <span><i class="fa-solid fa-hourglass-end"></i> Deadline: <?= date('M j, Y', strtotime($ann['deadline'])) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                        </div>

                        <!-- Clinic Announcements Tab -->
                        <div id="clinic" class="tab-content">
                            <div class="announcement-grid">
                                <?php if (empty($clinicAnnouncements)): ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fa-solid fa-stethoscope"></i></div>
                                        <p>No clinic announcements at this time</p>
                                    </div>
                                <?php else:
                                    foreach ($clinicAnnouncements as $ann): ?>
                                        <div class="announcement-card clinic">
                                            <div class="announcement-header">
                                                <h3 class="announcement-title"><?= htmlspecialchars($ann['title']) ?></h3>
                                                <span class="announcement-badge clinic">Clinic</span>
                                            </div>
                                            <div class="announcement-meta">
                                                <span class="announcement-meta-item">
                                                    <i class="fa-solid fa-calendar-days"></i>
                                                    <?= date('M j, Y', strtotime($ann['created_at'])) ?>
                                                </span>
                                                <span class="announcement-meta-item priority-<?= htmlspecialchars(strtolower($ann['priority'] ?? 'medium')) ?>">
                                                    Priority: <?= ucfirst($ann['priority'] ?? 'Medium') ?>
                                                </span>
                                                <span class="announcement-meta-item event-type-badge">
                                                    <?= ucfirst($ann['category'] ?? 'Update') ?>
                                                </span>
                                            </div>
                                            <div class="announcement-description">
                                                <p><?= nl2br(htmlspecialchars($ann['description'])) ?></p>
                                            </div>
                                            <div class="announcement-footer">
                                                <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($ann['created_by_name'] ?? 'Clinic') ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                        </div>

                        <!-- School Calendar Tab -->
                        <div id="calendar" class="tab-content">
                            <div class="announcement-grid">
                                <?php if (empty($calendarEvents)): ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fa-solid fa-calendar"></i></div>
                                        <p>No upcoming school events</p>
                                    </div>
                                <?php else:
                                    foreach ($calendarEvents as $evt): ?>
                                        <div class="announcement-card calendar">
                                            <div class="announcement-header">
                                                <h3 class="announcement-title"><?= ucfirst(str_replace('_', ' ', $evt['event_type'])) ?></h3>
                                                <span class="announcement-badge calendar"><?= ucfirst($evt['event_type']) ?></span>
                                            </div>
                                            <div class="announcement-meta">
                                                <span class="announcement-meta-item">
                                                    <i class="fa-solid fa-calendar-days"></i>
                                                    <?= date('M j, Y', strtotime($evt['event_date'])) ?>
                                                </span>
                                                <?php if (!empty($evt['start_time'])): ?>
                                                    <span class="announcement-meta-item event-time">
                                                        <i class="fa-solid fa-clock"></i>
                                                        <?= date('H:i', strtotime($evt['start_time'])) ?> - <?= date('H:i', strtotime($evt['end_time'])) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="announcement-description">
                                                <p><?= nl2br(htmlspecialchars($evt['description'])) ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                        </div>

                        <!-- SSC Events Tab -->
                        <div id="ssc" class="tab-content">
                            <div class="announcement-grid">
                                <?php if (empty($sscEvents)): ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fa-solid fa-award"></i></div>
                                        <p>No upcoming SSC events</p>
                                    </div>
                                <?php else:
                                    foreach ($sscEvents as $evt): ?>
                                        <div class="announcement-card ssc">
                                            <div class="announcement-header">
                                                <h3 class="announcement-title"><?= htmlspecialchars($evt['event_name']) ?></h3>
                                                <span class="announcement-badge ssc">SSC Event</span>
                                            </div>
                                            <div class="announcement-meta">
                                                <span class="announcement-meta-item">
                                                    <i class="fa-solid fa-calendar-days"></i>
                                                    <?= date('M j, Y', strtotime($evt['event_date'])) ?>
                                                </span>
                                                <?php if (!empty($evt['event_location'])): ?>
                                                    <span class="announcement-meta-item">
                                                        <i class="fa-solid fa-map-pin"></i>
                                                        <?= htmlspecialchars($evt['event_location']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($evt['event_photo'])): ?>
                                                <img src="<?= htmlspecialchars($evt['event_photo']) ?>" alt="Event photo" class="announcement-image">
                                            <?php endif; ?>
                                            <div class="announcement-description">
                                                <p><?= nl2br(htmlspecialchars($evt['description'])) ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                        </div>

                        <!-- Scholarship Announcements Tab -->
                        <div id="scholarship" class="tab-content">
                            <div class="announcement-grid">
                                <?php if (empty($scholarshipAnnouncements)): ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                                        <p>No active scholarship announcements</p>
                                    </div>
                                <?php else:
                                    foreach ($scholarshipAnnouncements as $ann): ?>
                                        <div class="announcement-card scholarship">
                                            <div class="announcement-header">
                                                <h3 class="announcement-title"><?= htmlspecialchars($ann['title']) ?></h3>
                                                <span class="announcement-badge scholarship">Scholarship</span>
                                            </div>
                                            <div class="announcement-meta">
                                                <span class="announcement-meta-item">
                                                    <i class="fa-solid fa-calendar-days"></i>
                                                    <?= date('M j, Y', strtotime($ann['created_at'])) ?>
                                                </span>
                                                <?php if (!empty($ann['deadline'])): ?>
                                                    <span class="announcement-meta-item">
                                                        <i class="fa-solid fa-hourglass-end"></i>
                                                        Deadline: <?= date('M j, Y', strtotime($ann['deadline'])) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($ann['image_path'])): ?>
                                                <img src="<?= htmlspecialchars($ann['image_path']) ?>" alt="Scholarship image" class="announcement-image">
                                            <?php endif; ?>
                                            <div class="announcement-description">
                                                <p><?= nl2br(htmlspecialchars($ann['description'])) ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script src="../assets/js/app.js" defer></script>
    <script>
        function switchTab(tabName, buttonElement) {
            // Hide all tabs
            const allTabs = document.querySelectorAll('.tab-content');
            allTabs.forEach(tab => tab.classList.remove('active'));

            // Remove active class from all buttons
            const allButtons = document.querySelectorAll('.tab-button');
            allButtons.forEach(btn => btn.classList.remove('active'));

            // Show selected tab and mark button as active
            const tabElement = document.getElementById(tabName);
            if (tabElement) {
                tabElement.classList.add('active');
            }
            if (buttonElement) {
                buttonElement.classList.add('active');
            }
        }
    </script>
</body>
</html>
