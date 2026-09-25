<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_login();
$user = current_user();
if ($user['role'] !== 'teacher' || $user['head_service'] !== 'library') {
    header('Location: /auth/teacher_login.php');
    exit;
}
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$libraryEntryUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $basePath . '/library_entry.php';
$qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=360x360&data=' . urlencode($libraryEntryUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library QR Generator | PASS Support System</title>
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
        .library-page-header { position: relative; min-height: 245px; display: flex; align-items: center; overflow: hidden; margin: 0 0 24px; padding: 42px 48px 30px; background: #f8f1e7; border-radius: 0 0 24px 24px; }
        .library-page-header::before { content: ''; position: absolute; z-index: 0; inset: 0 auto 0 0; width: 3px; background: #861b17; }
        .library-page-header .dashboard-intro-left { position: relative; z-index: 2; width: 65%; padding: 0 !important; }
        .library-page-header .eyebrow { margin: 0; color: #861b17 !important; font-family: Arial, sans-serif; font-size: 14px; font-weight: 700; letter-spacing: 3px; line-height: 1; text-transform: uppercase; }
        .library-page-header h1 { margin: 14px 0 12px; color: #111 !important; font-family: Georgia, serif; font-size: clamp(36px, 4vw, 62px); font-weight: 600; line-height: 1.05; }
        .library-page-header .dashboard-subtitle { max-width: 760px; margin: 0; color: #34302d !important; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; }
        .library-page-header .dashboard-summary-grid { margin-top: 18px !important; }
        .library-page-header .dashboard-intro-right { display: none; }
        .library-header__art { position: absolute; z-index: 1; top: 0; right: 0; width: 43%; height: 100%; color: #d7a58d; opacity: .78; }
        .library-header__art::before { content: ''; position: absolute; right: 10%; bottom: 4%; width: 84%; height: 28%; background: radial-gradient(ellipse at center, rgba(235, 205, 167, .5) 0 42%, transparent 43%); border-radius: 50%; }
        .library-header__art::after { content: ''; position: absolute; top: 23%; left: 3%; width: 76%; height: 45%; border: 1px solid rgba(215, 165, 141, .45); border-left-color: transparent; border-radius: 50%; transform: rotate(-13deg); }
        .library-header__art i { position: absolute; z-index: 2; font-family: 'Font Awesome 6 Free'; font-style: normal; font-weight: 900; }
        .library-header__art .book-stack { top: 16%; left: 39%; padding: 16px 18px; border: 2px solid rgba(215, 165, 141, .58); border-radius: 9px; box-shadow: 18px 12px 0 -1px #f8f1e7, 18px 12px 0 1px rgba(215, 165, 141, .48), 34px 23px 0 -1px #f8f1e7, 34px 23px 0 1px rgba(215, 165, 141, .42); font-size: 36px; }
        .library-header__art .open-book { top: 42%; left: 48%; color: #cf9589; font-size: 53px; transform: rotate(-3deg); }
        .library-header__art .library-card { top: 61%; left: 36%; padding: 10px 13px; border: 2px solid rgba(215, 165, 141, .65); border-radius: 7px; background: rgba(248, 241, 231, .75); color: #d7a58d; font-size: 22px; transform: rotate(-8deg); }
        .library-header__art .library-check { top: 22%; right: 7%; padding: 12px; border: 2px solid #e3b968; border-radius: 50%; color: #e3b968; font-size: 21px; }
        .library-header__art .library-pin { bottom: 9%; left: 57%; width: 31px; height: 31px; color: transparent; background: #cf9589; border-radius: 50% 50% 50% 0; font-size: 0; transform: rotate(-45deg); }
        .library-header__art .library-pin::after { content: ''; position: absolute; top: 9px; left: 9px; width: 13px; height: 13px; background: #f8f1e7; border-radius: 50%; }
        .library-header__art .library-leaf { right: 12%; bottom: 11%; color: #d7a58d; font-size: 28px; }
        @media (max-width: 768px) { .library-page-header { min-height: 245px; padding: 32px 24px 110px; } .library-page-header .dashboard-intro-left { width: 100%; } .library-page-header h1 { font-size: 36px; } .library-header__art { width: 55%; opacity: .35; } }
        @media (max-width: 768px) {
            .dashboard-intro {
                display: grid;
                grid-template-columns: 1fr !important;
                gap: 18px;
            }
            .dashboard-intro-left,
            .dashboard-intro-right {
                width: 100%;
                min-width: 0;
            }
            .dashboard-intro-left {
                padding: 24px !important;
                align-items: flex-start;
            }
            .dashboard-intro h1 {
                font-size: 2rem !important;
                line-height: 1.1 !important;
                word-break: break-word;
                overflow-wrap: break-word;
            }
            .dashboard-intro .dashboard-subtitle {
                font-size: 0.98rem !important;
                line-height: 1.65 !important;
            }
            .dashboard-summary-grid {
                display: grid !important;
                gap: 14px !important;
                margin-top: 18px !important;
            }
            .dashboard-summary-card {
                width: 100% !important;
                padding: 18px !important;
                word-break: break-word;
                overflow-wrap: anywhere;
            }
            .dashboard-summary-card a,
            .dashboard-summary-card strong,
            .dashboard-summary-card span {
                word-break: break-word;
                overflow-wrap: anywhere;
                white-space: normal !important;
            }
            .dashboard-summary-card a {
                display: inline-block;
            }
            .overview-panel {
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 18px !important;
            }
            .dashboard-summary-card input,
            .dashboard-summary-card select,
            .dashboard-summary-card button,
            .dashboard-summary-card a {
                width: 100% !important;
                min-width: 0 !important;
                box-sizing: border-box;
            }
            .dashboard-summary-card button.primary-button,
            .dashboard-summary-card .primary-button,
            .dashboard-summary-card .secondary-button,
            .dashboard-summary-card a.secondary-button {
                width: 100% !important;
            }
            .dashboard-summary-card img {
                max-width: 100% !important;
                height: auto !important;
            }
            .dashboard-section h2 {
                font-size: 1.3rem;
            }
            .dashboard-section {
                padding: 16px !important;
            }
        }
        @media (max-width: 520px) {
            .dashboard-intro-left {
                padding: 18px !important;
            }
            .dashboard-intro h1 {
                font-size: 1.75rem !important;
            }
            .dashboard-intro .eyebrow {
                font-size: 14px !important;
            }
            .dashboard-intro .dashboard-subtitle {
                font-size: 0.92rem !important;
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
<body class="library-qr swipe-refresh-enabled">
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
                    <button type="button" class="nav-toggle" aria-expanded="<?= isset($serviceOpen) && $serviceOpen ? 'true' : 'false' ?>" data-tooltip="Services">
                        <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                        <span class="nav-text">Services</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu<?= isset($serviceOpen) && $serviceOpen ? ' open' : '' ?>" aria-hidden="<?= isset($serviceOpen) && $serviceOpen ? 'false' : 'true' ?>">
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
                    <button type="button" class="nav-toggle" aria-expanded="<?= isset($manageOpen) && $manageOpen ? 'true' : 'false' ?>" data-tooltip="Manage">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Manage</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu<?= isset($manageOpen) && $manageOpen ? ' open' : '' ?>" aria-hidden="<?= isset($manageOpen) && $manageOpen ? 'false' : 'true' ?>">
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
                        <a href="library_settings.php" data-tooltip="Settings">
                            <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
                            <span class="nav-text">Settings</span>
                        </a>
                        <a class="active" href="library_qr.php" data-tooltip="Library QR">
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
                <section class="dashboard-intro library-page-header">
                    <div>
                        <span class="eyebrow">LIBRARY QR</span>
                        <h1>Library Entry QR Generator</h1>
                        <p class="dashboard-subtitle">Create a scan-ready QR code for your library entry page, or generate custom QR codes instantly.</p>
                    </div>
                    <div class="library-header__art" aria-hidden="true"><i class="fa-solid fa-layer-group book-stack"></i><i class="fa-solid fa-book-open open-book"></i><i class="fa-solid fa-id-card library-card"></i><i class="fa-solid fa-circle-check library-check"></i><i class="fa-solid fa-location-pin library-pin"></i><i class="fa-solid fa-leaf library-leaf"></i></div>
                </section>

                    <section class="dashboard-section">
                        <h2>QR Code Generator</h2>
                        <div class="overview-panel" style="grid-template-columns: 1.05fr 0.95fr; gap: 24px;">
                            <div class="dashboard-summary-card">
                                <p class="eyebrow">Generate custom QR</p>
                                <label for="qrText" style="display:block; margin-bottom:10px; font-weight:700; color:#0f172a;">Enter URL</label>
                                <input id="qrText" type="text" value="<?= htmlspecialchars($libraryEntryUrl) ?>" style="width:100%; padding:14px 18px; border-radius:18px; border:1px solid rgba(37,99,235,0.18); font-size:1rem;" />
                                <label for="qrSize" style="display:block; margin:18px 0 10px; font-weight:700; color:#0f172a;">QR size</label>
                                <select id="qrSize" style="width:100%; padding:14px 18px; border-radius:18px; border:1px solid rgba(37,99,235,0.18); font-size:1rem;">
                                    <option value="240">240 x 240</option>
                                    <option value="360" selected>360 x 360</option>
                                    <option value="480">480 x 480</option>
                                </select>
                                <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:22px;">
                                    <button type="button" class="primary-button" id="generateBtn" style="flex:1; min-width:150px;">Generate QR</button>
                                    <button type="button" class="secondary-button" id="resetBtn" style="flex:1; min-width:150px;">Reset default</button>
                                </div>
                            </div>

                            <div class="dashboard-summary-card" style="display:flex; flex-direction:column; gap:18px;">
                                <div>
                                    <p class="eyebrow">Preview</p>
                                </div>
                                <div style="background: #fff; padding: 28px; border-radius: 28px; border: 1px solid rgba(214,168,74,0.18); box-shadow: 0 18px 36px rgba(15,23,42,0.08); display:flex; justify-content:center;">
                                    <img id="qrCodeImage" src="<?= htmlspecialchars($qrImage) ?>" alt="Current QR code" style="width: 100%; max-width: 320px; height: auto; display:block;" />
                                </div>
                                <div style="display:grid; gap:12px;">
                                    <button type="button" class="primary-button" id="downloadQrBtn">Download QR</button>
                                    <button type="button" class="secondary-button" id="copyLinkBtn">Copy link</button>
                                    <a class="secondary-button" href="<?= htmlspecialchars($libraryEntryUrl) ?>" target="_blank" style="text-align:center;">Open entry page</a>
                                </div>
                                <p class="action-note" style="margin-top: 8px;">Tip: generate a code for any valid URL — not just the library entry page.</p>
                            </div>
                        </div>
                    </section>
            </div>
        </main>
    </div>
    <script>
        (function() {
            const defaultUrl = <?= json_encode($libraryEntryUrl) ?>;
            const qrPreview = document.getElementById('qrCodeImage');
            const qrText = document.getElementById('qrText');
            const qrSize = document.getElementById('qrSize');
            const downloadQrBtn = document.getElementById('downloadQrBtn');
            const copyLinkBtn = document.getElementById('copyLinkBtn');
            const generateBtn = document.getElementById('generateBtn');
            const resetBtn = document.getElementById('resetBtn');

            const buildQrUrl = (url, size) => {
                const encoded = encodeURIComponent(url.trim() || defaultUrl);
                return `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encoded}`;
            };

            const refreshQr = () => {
                const url = qrText.value.trim() || defaultUrl;
                const size = qrSize.value;
                const qrUrl = buildQrUrl(url, size);
                qrPreview.src = qrUrl;
                qrPreview.alt = `QR code for ${url}`;
                downloadQrBtn.dataset.qr = qrUrl;
                downloadQrBtn.dataset.filename = `library-qr-${size}.png`;
            };

            const downloadQr = async () => {
                const qrUrl = downloadQrBtn.dataset.qr || buildQrUrl(defaultUrl, qrSize.value);
                const filename = downloadQrBtn.dataset.filename || `library-qr-${qrSize.value}.png`;
                downloadQrBtn.textContent = 'Downloading...';
                try {
                    const response = await fetch(qrUrl);
                    const blob = await response.blob();
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                } catch (error) {
                    alert('Download failed. You can still right-click the QR image and save it manually.');
                } finally {
                    downloadQrBtn.textContent = 'Download QR';
                }
            };

            const copyLink = async () => {
                const url = qrText.value.trim() || defaultUrl;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    try {
                        await navigator.clipboard.writeText(url);
                        copyLinkBtn.textContent = 'Copied!';
                        setTimeout(() => { copyLinkBtn.textContent = 'Copy link'; }, 1500);
                    } catch (error) {
                        alert('Unable to copy link. Please copy it manually.');
                    }
                } else {
                    window.prompt('Copy the URL below:', url);
                }
            };

            generateBtn.addEventListener('click', refreshQr);
            resetBtn.addEventListener('click', () => {
                qrText.value = defaultUrl;
                qrSize.value = '360';
                refreshQr();
            });
            downloadQrBtn.addEventListener('click', downloadQr);
            copyLinkBtn.addEventListener('click', copyLink);
            refreshQr();
        })();
    </script>
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
