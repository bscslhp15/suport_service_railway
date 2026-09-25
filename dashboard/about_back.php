<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();

$displayCourse = $user['course'] ?? $user['course_year'] ?? 'Course';
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

if ($user['role'] !== 'student' && $user['role'] !== 'teacher') {
    header('Location: ../auth/student_login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About PASS College | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        /* Simple section reveal animations */
        [data-animate] { opacity: 0; transform: translateY(12px); transition: opacity 520ms ease, transform 520ms cubic-bezier(.2,.9,.2,1); }
        [data-animate].in-view { opacity: 1; transform: translateY(0); }

        .about-who-inner, .legacy-inner { max-width: 900px; margin: 0 auto; }
        .about-who h2, .about-values h2 { margin-top: 0.25rem; }
        .values-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 18px; margin-top: 12px; }
        .value-item { background:#fff; border-radius:12px; padding:16px; box-shadow:0 8px 24px rgba(15,23,42,0.05); }
        .programs-grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap:12px; margin-top:12px; }
        .program { background: linear-gradient(135deg,#f8fafc,#ffffff); padding:12px; border-radius:10px; border:1px solid #eef2ff; text-align:center; font-weight:600; }
    </style>
</head>
<body class="student-dashboard about-page">
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
                <a href="student_home.php" data-tooltip="Dashboard">
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
                <a href="profile.php" data-tooltip="Profile">
                    <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                    <span class="nav-text">Profile</span>
                </a>
                <a href="about.php" class="active" data-tooltip="About">
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
                    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle mobile menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>Student Portal</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta"><?= htmlspecialchars($user['course'] ?? ($user['course_year'] ?? 'Course')) ?></span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Profile" data-tooltip="Profile" data-menu-target="profileMenu"><i class="fa-solid fa-user"></i></button>

                    <div class="topbar-menu" id="notificationMenu" role="menu" aria-label="Notifications menu">
                        <div class="menu-header">
                            <strong>Notifications</strong>
                            <span class="menu-note">Latest announcements</span>
                        </div>
                        <div class="menu-empty">No new announcements.</div>
                        <a class="menu-link menu-footer-link" href="clinic_dashboard.php#announcements">View all announcements</a>
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
                <div class="content-panel about-page">
                    <section class="about-hero hero-split" data-animate>
                        <div class="hero-copy">
                            <p class="eyebrow">About PASS College</p>
                            <h1>Premium yet affordable education for a values-driven future.</h1>
                            <p>PASS College is a student-centered institution in Alaminos City, built on academic excellence, supportive services, and proven outcomes for learners across Western Pangasinan.</p>
                            <div class="hero-actions">
                                <span class="hero-pill">#1 Producer of CPAs in Western Pangasinan</span>
                                <a href="#programs" class="btn btn-primary">Explore Programs</a>
                            </div>
                        </div>
                        <div class="hero-panel">
                            <div class="hero-card hero-card-light">
                                <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS College logo" class="hero-logo">
                                <h2>Our Vision</h2>
                                <p>To become a leading Higher Educational Institution committed to a holistic and transformative community through learning dedicated to academic excellence, leadership, and values formation.</p>
                            </div>
                            <div class="hero-card hero-card-light">
                                <h2>Our Mission</h2>
                                <p>Empower every student with accessible learning resources, strong guidance, and a safe environment that encourages growth, innovation, and global competitiveness.</p>
                            </div>
                            <div class="hero-card hero-card-accent">
                                <span class="hero-badge">Premium + Affordable</span>
                                <h3>Student-first learning that balances quality and value.</h3>
                                <p>We combine modern curriculum, guidance support, and campus wellness to help learners succeed without sacrificing affordability.</p>
                            </div>
                        </div>
                    </section>
                    <section class="section-block support-section" data-animate>
                        <div class="section-head">
                            <div>
                                <p class="eyebrow">Support Structure</p>
                                <h2>Built for student success</h2>
                            </div>
                        </div>
                        <div class="org-grid">
                            <div class="org-card org-card-lead">
                                <span class="org-label">PASS College Leadership</span>
                                <p>Guiding campus strategy, academic quality, and student-focused operations across library, clinic, SSC, and guidance.</p>
                            </div>
                            <div class="org-card">
                                <i class="fa-solid fa-book"></i>
                                <h3>Library Services</h3>
                                <p>Research support, digital access, and learning resources for every student.</p>
                            </div>
                            <div class="org-card">
                                <i class="fa-solid fa-heart-pulse"></i>
                                <h3>Clinic Services</h3>
                                <p>Health and wellness services to keep the student community safe and supported.</p>
                            </div>
                            <div class="org-card">
                                <i class="fa-solid fa-award"></i>
                                <h3>SSC & Outreach</h3>
                                <p>Student leadership, engagement programs, and community-building events.</p>
                            </div>
                            <div class="org-card">
                                <i class="fa-solid fa-user-graduate"></i>
                                <h3>Guidance Office</h3>
                                <p>Counseling, mentoring, and career readiness services for academic and personal development.</p>
                            </div>
                        </div>
                    </section>
                    <section class="section-block history-timeline" id="history" data-animate>
                        <div class="section-head">
                            <div>
                                <p class="eyebrow">History Timeline</p>
                                <h2>From PASS to PASS College</h2>
                            </div>
                        </div>
                        <div class="timeline">
                            <div class="timeline-item">
                                <span class="timeline-year">1997</span>
                                <h3>Founded as Philippine Accountancy and Science School</h3>
                                <p>PASS opens with a mission to deliver focused accounting and science education to Alaminos City.</p>
                            </div>
                            <div class="timeline-item">
                                <span class="timeline-year">2001</span>
                                <h3>Becomes PASS College</h3>
                                <p>The school is renamed PASS College, continuing its commitment to accessible, high-quality education.</p>
                            </div>
                            <div class="timeline-item">
                                <span class="timeline-year">2007</span>
                                <h3>Introduced Ladderized Education</h3>
                                <p>PASS College responds to Executive Order 358 by expanding ladderized programs and career pathways.</p>
                            </div>
                            <div class="timeline-item">
                                <span class="timeline-year">2016</span>
                                <h3>A tradition of breaking with tradition</h3>
                                <p>The college blends academic excellence with innovation and strengthens its regional leadership.</p>
                            </div>
                        </div>
                        <div class="history-story">
                            <h3>The History of our School</h3>
                            <p>Founded in 1997 as the Philippine Accountancy and Science School, PASS College continues Mrs. Adelina M. Morante's dream of providing quality, well-rounded education to produce top-caliber, values-driven graduates.</p>
                            <h4>History of PASS College</h4>
                            <p>The dream to establish an institution that produces not only successful graduates but also God-loving and law-abiding citizens became reality in 1997 when PASS College was inaugurated. In 2001, the school evolved into PASS College and expanded its reach across Western Pangasinan.</p>
                            <p>PASS College started with four ladderized programs: BS Accountancy, BS Computer Science, BS Commerce, and Secretarial Administration. Later, it added Elementary Education, Tourism Hotel and Restaurant Management, Computer Secretarial, and Caregiving programs. Today, it offers a broader set of programs to meet local needs and support student competence.</p>
                            <p>In 2007, PASS College responded to Executive Order 358 of President Gloria Macapagal-Arroyo by inaugurating the Ladderized Education System. The institution continues its quest for academic excellence and remains committed to providing a quality, well-rounded education for the youth.</p>
                        </div>
                    </section>

                    <section class="section-block values-section" data-animate>
                        <div class="section-head">
                            <div>
                                <p class="eyebrow">Core Values</p>
                                <h2>The PASSian promise</h2>
                            </div>
                        </div>
                        <div class="values-grid">
                            <div class="value-item value-strong">
                                <i class="fa-solid fa-shield-halved"></i>
                                <h3>Integrity</h3>
                                <p>Honesty and responsibility form the moral foundation of the PASSian community.</p>
                            </div>
                            <div class="value-item value-pride">
                                <i class="fa-solid fa-flag"></i>
                                <h3>Nationalism</h3>
                                <p>PASSian Education fosters loyalty, compassion, and empowered citizenship.</p>
                            </div>
                            <div class="value-item value-innovate">
                                <i class="fa-solid fa-atom"></i>
                                <h3>Innovative</h3>
                                <p>We champion modern, collaborative learning and interdisciplinary thinking.</p>
                            </div>
                        </div>
                    </section>

                    <section class="section-block programs-section" id="programs" data-animate>
                        <div class="section-head">
                            <div>
                                <p class="eyebrow">Program Gallery</p>
                                <h2>Programs designed for the future</h2>
                            </div>
                        </div>
                        <div class="section-intro">
                            <p>Discover the programs that make PASS College a premium yet affordable choice for learners pursuing careers in business, education, technology, hospitality, and service.</p>
                        </div>
                        <div class="programs-gallery">
                            <article class="program-card"><i class="fa-solid fa-calculator"></i><h4>BS Accountancy</h4></article>
                            <article class="program-card"><i class="fa-solid fa-code"></i><h4>BS Computer Science</h4></article>
                            <article class="program-card"><i class="fa-solid fa-briefcase"></i><h4>BS Business Administration</h4></article>
                            <article class="program-card"><i class="fa-solid fa-chalkboard-user"></i><h4>Bachelor in Elementary Education</h4></article>
                            <article class="program-card"><i class="fa-solid fa-shield"></i><h4>BS Criminology</h4></article>
                            <article class="program-card"><i class="fa-solid fa-utensils"></i><h4>BS Hospitality Management</h4></article>
                            <article class="program-card"><i class="fa-solid fa-plane-departure"></i><h4>BS Tourism Management</h4></article>
                            <article class="program-card"><i class="fa-solid fa-laptop-code"></i><h4>Associate in Computer Technology</h4></article>
                            <article class="program-card"><i class="fa-solid fa-building-columns"></i><h4>Associate in Business Knowledge</h4></article>
                            <article class="program-card"><i class="fa-solid fa-wine-glass"></i><h4>Associate in Hospitality Management</h4></article>
                            <article class="program-card"><i class="fa-solid fa-plane-alt"></i><h4>Associate in Tourism Management</h4></article>
                        </div>
                    </section>
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
            <div class="footer-copyright">© 2026 PASS College. All rights reserved.</div>
        </footer>
    </div>
    <div class="page-overlay" id="pageOverlay"></div>
    <script src="../assets/js/app.js" defer></script>
    <script>
        // Mobile menu toggle
        const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
        const sidebar = document.querySelector('.side-nav');
        const pageOverlay = document.querySelector('.page-overlay');

        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', function(e) {
                e.preventDefault();
                sidebar.classList.toggle('mobile-open');
                pageOverlay.classList.toggle('active');
            });
        }

        // Close sidebar when overlay is clicked
        if (pageOverlay) {
            pageOverlay.addEventListener('click', function() {
                sidebar.classList.remove('mobile-open');
                pageOverlay.classList.remove('active');
            });
        }

        // Close sidebar when nav links are clicked
        const navLinks = document.querySelectorAll('.side-nav a');
        navLinks.forEach(link => {
            if (!link.classList.contains('nav-toggle')) {
                link.addEventListener('click', function() {
                    sidebar.classList.remove('mobile-open');
                    pageOverlay.classList.remove('active');
                });
            }
        });

        // Close sidebar on ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const sidebar = document.querySelector('.side-nav');
                const pageOverlay = document.querySelector('.page-overlay');
                sidebar.classList.remove('mobile-open');
                pageOverlay.classList.remove('active');
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Reveal on scroll for sections with data-animate
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('in-view');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12 });

            document.querySelectorAll('[data-animate]').forEach(el => observer.observe(el));
        });
    </script>
    <?php include '../AI CHAT BOT/chat_widget.php'; ?>
</body>
</html>
