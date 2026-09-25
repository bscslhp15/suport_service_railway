<?php
require_once __DIR__ . '/../../includes/session.php';
require_login();
$user = current_user();
if (!in_array($user['role'], ['student', 'teacher', 'admin'], true)) {
    header('Location: /auth/student_login.php');
    exit;
}
if ($user['role'] === 'teacher' && $user['head_service'] !== 'none') {
    header('Location: ../ssc_head_home.php');
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
$topbarInfo = build_user_dashboard_header_info($user);
$roleLabel = $user['role'] === 'teacher' ? 'Teacher' : ($user['role'] === 'student' ? 'Student' : 'Admin');
$dashboardLink = $user['role'] === 'student' ? '../student_home.php' : ($user['role'] === 'teacher' ? '../teacher_home.php' : '../admin_home.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SSC Events | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="style.css">
    <style>
        .ssc-events-page-header {
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .ssc-events-page-header h1 {
            margin: 0;
            font-size: 2rem;
            color: #0f172a;
        }

        .ssc-events-page-header p {
            margin: 0;
            color: #475569;
            max-width: 680px;
            line-height: 1.7;
        }

        .ssc-event-row {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 28px;
            align-items: center;
            margin-bottom: 48px;
        }

        .ssc-event-row.reverse {
            grid-template-columns: 0.9fr 1.1fr;
        }

        .ssc-event-row.reverse .event-media {
            order: -1;
        }

        .ssc-event-row .event-content,
        .ssc-event-row .event-media {
            background: transparent;
            border-radius: 0;
            box-shadow: none;
            overflow: hidden;
        }

        .ssc-event-row .event-content {
            padding: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 18px;
        }

        .ssc-event-row .event-content h2 {
            margin: 0;
            font-size: 1.65rem;
            color: #0f172a;
            line-height: 1.15;
        }

        .ssc-event-row .event-content .event-description {
            margin: 0;
            color: #475569;
            line-height: 1.78;
            font-size: 1rem;
        }

        .event-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-top: 10px;
        }

        .event-meta-item {
            background: #f8fafc;
            border-radius: 999px;
            padding: 12px 16px;
            color: #475569;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .event-status-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eef2ff;
            color: #3730a3;
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 700;
            font-size: 0.9rem;
            width: fit-content;
        }

        .event-media {
            min-height: 370px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .event-media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .event-media.placeholder {
            background: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: 700;
            min-height: 320px;
            padding: 24px;
            text-align: center;
        }

        .ssc-event-gallery {
            display: grid;
            gap: 14px;
            margin-top: 22px;
        }

        .ssc-event-gallery.single {
            justify-items: center;
        }

        .ssc-event-gallery img {
            width: 100%;
            border-radius: 18px;
            object-fit: cover;
            transition: transform 0.25s ease;
            cursor: pointer;
        }

        .ssc-event-gallery.single img {
            max-width: 640px;
        }

        .ssc-event-gallery.double {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .ssc-event-gallery img:hover {
            transform: scale(1.02);
        }

        /* Carousel Styles */
        .event-carousel-wrapper {
            margin-top: 0;
            background: transparent;
            border-radius: 0;
            box-shadow: none;
            padding: 0;
            position: relative;
        }

        /* Event Card Container */
        .event-card {
            background: white;
            border-radius: 22px;
            box-shadow: 0 14px 40px rgba(15, 23, 42, 0.06);
            padding: 28px;
            margin-bottom: 32px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .event-carousel-container {
            display: flex;
            gap: 16px;
            overflow-x: auto;
            scroll-behavior: smooth;
            padding: 10px 0;
            scroll-padding-left: 10px;
            margin-top: 16px;
        }

        .event-carousel-container::-webkit-scrollbar {
            height: 8px;
        }

        .event-carousel-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }

        .event-carousel-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .event-carousel-container::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .carousel-slide {
            flex: 0 0 240px;
            border-radius: 14px;
            overflow: hidden;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .carousel-slide img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            display: block;
        }

        .carousel-slide:hover {
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.1);
            border-color: #cbd5e1;
        }

        .carousel-nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #475569;
            font-size: 1.2rem;
            z-index: 10;
        }

        .carousel-nav-btn:hover {
            background: #f8fafc;
            border-color: #475569;
            color: #0f172a;
        }

        .carousel-nav-btn.prev {
            left: 10px;
        }

        .carousel-nav-btn.next {
            right: 10px;
        }

        .ssc-event-no-events {
            padding: 60px 0;
            text-align: center;
            color: #475569;
        }

        @media (max-width: 980px) {
            .ssc-event-row,
            .ssc-event-gallery.double {
                grid-template-columns: 1fr;
            }

            .ssc-events-page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .ssc-event-row.reverse {
                grid-template-columns: 1fr;
            }

            .event-card {
                padding: 20px;
            }

            .carousel-slide {
                flex: 0 0 180px;
            }
        }

        @media (max-width: 640px) {
            .ssc-event-row {
                gap: 18px;
            }

            .ssc-event-row .event-content {
                padding: 0;
            }

            .event-card {
                padding: 16px;
                margin-bottom: 24px;
                gap: 18px;
            }

            .carousel-nav-btn {
                width: 36px;
                height: 36px;
                font-size: 1rem;
            }

            .carousel-slide {
                flex: 0 0 140px;
            }

            .carousel-slide img {
                height: 140px;
            }
        }
    </style>
</head>
<body>
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
                <a href="<?= htmlspecialchars($dashboardLink) ?>" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Services">
                        <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                        <span class="nav-text">Services</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu" aria-hidden="true">
                        <a href="../guidance_dashboard.php" data-tooltip="Guidance">
                            <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                            <span class="nav-text">Guidance</span>
                        </a>
                        <a href="../library_dashboard.php" data-tooltip="Library">
                            <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                            <span class="nav-text">Library</span>
                        </a>
                        <a href="../clinic_dashboard.php" data-tooltip="Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                            <span class="nav-text">Clinic</span>
                        </a>
                        <a href="ssc_dashboard.php" data-tooltip="Supreme Student Council">
                            <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                            <span class="nav-text">Supreme Student Council</span>
                        </a>
                        <a href="../scholarship/scholarship_dashboard.php" data-tooltip="Scholarship">
                            <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                            <span class="nav-text">Scholarship</span>
                        </a>
                        <a href="../ssaa_student_home.php" data-tooltip="Alumni">
                            <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                            <span class="nav-text">Alumni</span>
                        </a>
                    </div>
                </div>
                <a href="../profile.php" data-tooltip="Profile">
                    <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                    <span class="nav-text">Profile</span>
                </a>
                <a href="../about.php" data-tooltip="About">
                    <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                    <span class="nav-text">About</span>
                </a>
            </div>
            <div class="nav-footer">
                <a href="../../logout.php" class="logout-link" data-tooltip="Logout">
                    <span class="nav-icon"><i class="fa-solid fa-sign-out-alt"></i></span>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
        </aside>
        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <div class="nav-brand">
                        <img src="../../IMG ASSETS/passlogo.png" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>SSC Portal</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta"><?= htmlspecialchars($topbarInfo['displayMeta']) ?></span>
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
                        <a class="menu-link menu-footer-link" href="../about.php">View all announcements</a>
                    </div>

                    <div class="topbar-menu" id="profileMenu" role="menu" aria-label="Profile menu">
                        <div class="menu-item profile-menu-item" role="menuitem">
                            <a href="../profile.php" class="menu-link profile-link"><?= htmlspecialchars($user['full_name']) ?></a>
                            <span class="menu-subtext">View your account details</span>
                        </div>
                        <a href="../profile.php" class="menu-action">View profile</a>
                    </div>

                    <div class="topbar-menu" id="supportMenu" role="menu" aria-label="Support menu">
                        <div class="menu-header">
                            <strong>Support</strong>
                            <span class="menu-note">Need help?</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="../about.php">Help center</a>
                            <span class="menu-subtext">View support resources and FAQs.</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="mailto:support@passcollege.edu">Email support</a>
                        </div>
                    </div>
                </div>
            </header>
            <div class="main-scroll">
                <div class="content-panel">
                    <section class="ssc-events-page-header">
                        <div>
                            <h1>SSC Events</h1>
                            <p>All SSC events are shown here with their full details, including pictures and schedules. Use this page to review event descriptions, locations, and photo galleries from SSC management.</p>
                        </div>
                    </section>

                    <div id="ssc-events-list"></div>
                </div>
            </div>
        </main>
    </div>
    <script src="../../assets/js/app.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadAllEvents();
        });

        async function loadAllEvents() {
            try {
                const response = await fetch('../ssc_api.php?action=get_events');
                const data = await response.json();
                displayAllEvents(data.events || []);
            } catch (error) {
                console.error('Error loading events:', error);
                document.getElementById('ssc-events-list').innerHTML = '<div class="ssc-event-no-events">Unable to load SSC events at this time.</div>';
            }
        }

        function displayAllEvents(events) {
            const container = document.getElementById('ssc-events-list');
            if (!events.length) {
                container.innerHTML = '<div class="ssc-event-no-events"><h3>No SSC events available</h3><p>There are no events recorded yet. Check back later for event updates.</p></div>';
                return;
            }

            container.innerHTML = events.map((event, index) => renderEventDetail(event, index)).join('');
        }

        function renderEventDetail(event, index) {
            const photos = (event.photos && event.photos.length > 0) ? event.photos : (event.photo_url ? [event.photo_url] : []);
            const primaryPhoto = photos.length ? resolveEventImageUrl(photos[0]) : null;
            const rowClass = index % 2 === 0 ? 'ssc-event-row' : 'ssc-event-row reverse';
            const carouselId = `carousel-${event.id || index}`;

            const carouselHtml = photos.length ? `
                <div class="event-carousel-wrapper">
                    <button class="carousel-nav-btn prev" onclick="scrollCarousel('${carouselId}', -1)"><i class="fa-solid fa-chevron-left"></i></button>
                    <button class="carousel-nav-btn next" onclick="scrollCarousel('${carouselId}', 1)"><i class="fa-solid fa-chevron-right"></i></button>
                    <div class="event-carousel-container" id="${carouselId}">
                        ${photos.map(photo => `
                            <div class="carousel-slide">
                                <img src="${resolveEventImageUrl(photo)}" alt="${event.title} photo">
                            </div>
                        `).join('')}
                    </div>
                </div>
            ` : `<div class="event-carousel-wrapper"><div style="text-align: center; padding: 20px; color: #64748b;">No photos uploaded for this event yet.</div></div>`;

            return `
                <div class="event-card">
                    <div class="${rowClass}">
                        <div class="event-content">
                            <span class="event-status-pill">${event.status || 'upcoming'}</span>
                            <h2>${event.title}</h2>
                            <p class="event-description">${event.description || 'No description provided for this event.'}</p>
                            <div class="event-meta-grid">
                                <div class="event-meta-item"><i class="fa-solid fa-calendar-day"></i> ${formatDate(event.event_date)}</div>
                                <div class="event-meta-item"><i class="fa-solid fa-clock"></i> ${event.event_time || 'Time not specified'}</div>
                                <div class="event-meta-item"><i class="fa-solid fa-location-dot"></i> ${event.location || 'Location not specified'}</div>
                                <div class="event-meta-item"><i class="fa-solid fa-users"></i> ${event.organizer || 'Organizer not specified'}</div>
                            </div>
                        </div>
                        <div class="event-media ${photos.length ? '' : 'placeholder'}">
                            ${photos.length ? `<img src="${primaryPhoto}" alt="${event.title}">` : 'No photos uploaded for this event yet.'}
                        </div>
                    </div>
                    ${carouselHtml}
                </div>
            `;
        }

        function resolveEventImageUrl(photoUrl) {
            if (!photoUrl) {
                return '';
            }
            if (photoUrl.startsWith('http://') || photoUrl.startsWith('https://') || photoUrl.startsWith('/')) {
                return photoUrl;
            }
            return '../../' + photoUrl;
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        function scrollCarousel(carouselId, direction) {
            const carousel = document.getElementById(carouselId);
            if (!carousel) return;
            
            const scrollAmount = 256; // 240px slide + 16px gap
            carousel.scrollBy({
                left: direction * scrollAmount,
                behavior: 'smooth'
            });
        }
    </script>
</body>
</html>
