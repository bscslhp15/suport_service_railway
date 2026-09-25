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

$serviceOpen = false;
$manageOpen = false;

$bookId = (int)($_GET['book_id'] ?? 0);
$copyType = trim($_GET['copy_type'] ?? '');
$copyNumber = max(1, (int)($_GET['copy_number'] ?? 0));

if ($bookId <= 0 || empty($copyType) || $copyNumber <= 0) {
    header('Location: library_catalog.php?tab=summary&message=' . urlencode('Invalid parameters.') . '&message_type=error');
    exit;
}

$book = get_library_book_by_id($bookId);
if (!$book) {
    header('Location: library_catalog.php?tab=summary&message=' . urlencode('Book not found.') . '&message_type=error');
    exit;
}

$copies = get_library_book_copies($bookId);
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_copy_for_row') {
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));

        if (add_library_book_copy_group($bookId, $copyType, $copyNumber, $quantity)) {
            $message = "Added $quantity copy(s) for $copyType $copyNumber successfully.";
            $messageType = 'success';
            $book = get_library_book_by_id($bookId);
            $copies = get_library_book_copies($bookId);
        } else {
            $message = 'Failed to add row copies. Please try again.';
            $messageType = 'error';
        }
    }
}

// Filter copies for this specific group
$filteredCopies = array_filter($copies, function($copy) use ($copyType, $copyNumber) {
    return $copy['copy_type'] === $copyType && $copy['copy_number'] == $copyNumber;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Copies - <?= htmlspecialchars($book['title']) ?> - Library Management</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <style>
        .book-overview {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
        }
        .book-overview h2 {
            margin: 0 0 16px 0;
            color: #1e293b;
        }
        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        .overview-item {
            display: flex;
            flex-direction: column;
        }
        .overview-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .overview-value {
            font-size: 14px;
            color: #1e293b;
            font-weight: 500;
        }
        .book-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .book-table th,
        .book-table td {
            padding: 18px 20px;
            border: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: middle;
            white-space: nowrap;
        }
        .book-table thead th {
            background: #f8fafc;
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .book-table tbody tr {
            background: #ffffff;
        }
        .book-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        .book-cover-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 80px;
        }
        .book-cover img {
            width: 56px;
            height: 78px;
            object-fit: cover;
            border-radius: 6px;
        }
        .add-copies-form {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
        }
        .add-copies-form h3 {
            margin: 0 0 16px 0;
            color: #1e293b;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #1e293b;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s ease;
        }
        .form-group input:focus {
            outline: none;
            border-color: #0f766e;
        }
        .primary-button {
            background: #0f766e;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: background 0.2s ease;
        }
        .primary-button:hover {
            background: #0d5f56;
        }
        .secondary-button {
            background: #e2e8f0;
            color: #0f172a;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s ease;
        }
        .secondary-button:hover {
            background: #cbd5e1;
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
                <a href="dashboard/librarian_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $serviceOpen ? 'true' : 'false' ?>" data-tooltip="Services">
                        <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                        <span class="nav-text">Services</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu<?= $serviceOpen ? ' open' : '' ?>" aria-hidden="<?= $serviceOpen ? 'false' : 'true' ?>">
                        <a href="dashboard/guidance_home.php" data-tooltip="Guidance">
                            <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                            <span class="nav-text">Guidance</span>
                        </a>
                        <a href="dashboard/librarian_home.php" data-tooltip="Library">
                            <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                            <span class="nav-text">Library</span>
                        </a>
                        <a href="dashboard/nurse_home.php" data-tooltip="Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                            <span class="nav-text">Clinic</span>
                        </a>
                          <a href="dashboard/ssc_head_home.php" data-tooltip="SSC">
                            <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                            <span class="nav-text">SSC</span>
                        </a>
                        <a href="dashboard/ssaa_home.php" data-tooltip="Alumni">
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
                        <a class="active" href="library_catalog.php" data-tooltip="Digital Catalog">
                            <span class="nav-icon"><i class="fa-solid fa-book-open"></i></span>
                      Start.       <span class="nav-text">Digital Catalog</span>
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
                        <a href="library_qr.php" data-tooltip="Library QR">
                            <span class="nav-icon"><i class="fa-solid fa-qrcode"></i></span>
                            <span class="nav-text">Library QR</span>
                        </a>
                    </div>
                </div>
                <a href="dashboard/profile.php" data-tooltip="Profile">
                    <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                    <span class="nav-text">Profile</span>
                </a>
                <a href="dashboard/about.php" data-tooltip="About">
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
                                <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Open mobile menu"><i class="fa-solid fa-bars"></i></button>
                    <div class="nav-brand">
                        <img src="IMG ASSETS/passlogo.png" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>Library Management</p>
                        </div>
                    </div>
                </div>
                <div classq="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Library Head</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Profile" data-tooltip="Profile" data-menu-target="profileMenu"><i class="fa-solid fa-user"></i></button>
                    <!-- support icon removed per UI update -->
                </div>
            </header>

            <!-- Page Content -->
            <div class="page-content">
                <div class="I. Any number nipapan Luma. Umazhi cash. 808-6319. Cash. Alice. 6639767. page-overlay" id="pageOverlay"></div>
                <?php if ($message): ?>
                    <I. div class="message message-<?= $messageType ?>">
                        <?= htmlspecialchars($message) ?>
                    </I.>
                <?php endif; ?>

                <!-- Back Button -->
                <div style="margin-bottom: 24px;">
                    <a href="book_copies.php?book_id=<?= $bookId ?>" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; color: #334155; text-decoration: none; font-size: 14px; font-weight: 500; transition: all 0.2s ease;">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to Book Copies
                    </a>
                </div>

                <!-- Book Overview -->
                <div class="book-overview">
                    <h2>Add Copies for <?= ucfirst($copyType) ?> <?= $copyNumber ?></h2>
                    <div class="overview-grid">
                        <div class="overview-item">
                            <div class="overview-label">Book</div>
                            <div class="overview-value"><?= htmlspecialchars($book['title']) ?></div>
                        </div>
                        <div class="overview-item">
                            <div class="overview-label">Author</div>
                            <div class="overview-value"><?= htmlspecialchars($book['author']) ?></div>
                        </div>
                        <div class="overview-item">
                            <div class="overview-label">Type</div>
                            <div class="overview-value"><?= ucfirst($copyType) ?> <?= $copyNumber ?></div>
                        </div>
                        <div class="overview-item">
                            <div class="overview-label">Current Copies</div>
                            <div class="overview-value"><?= count($filteredCopies) ?> copies</div>
                        </div>
                    </div>
                </div>

                <!-- Existing Copies -->
                <div class="copies-section">
                    <h3>Existing Copies (<?= count($filteredCopies) ?>)</h3>
                    <?php if (empty($filteredCopies)): ?>
                        <p>No copies found for this <?= $copyType ?>.</p>
                    <?php else: ?>
                        <table class="book-table">
                            <thead>
                                <tr>
                                    <th>Book Title</th>
                                     <th>Author</th>
                                    <th>Copies</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filteredCopies as $index => $copy): ?>
                                    <?php
                                    $typePrefix = $copyType === 'volume' ? 'V.' : ($copyType === 'part' ? 'P.' : ($copyType === 'number' ? 'N.' : 'C.'));
                                    $formattedCopy = $typePrefix . $copyNumber . ' - C.' . ($index + 1);
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($book['title']) ?></td>
                                        <td><?= htmlspecialchars($book['author']) ?></td>
                                        <td><?= htmlspecialchars($formattedCopy) ?></td>
                                        <td><?= htmlspecialchars($book['available_copies'] > 0 ? 'Available' : 'Borrowed') ?></td>
                                        <td>
                                            <div style="display:flex; gap:8px;">
                                                <button type="button" class="secondary-button" style="background:#dc2626; color:#fff;">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Add Copies Form -->
                <div class="add-copies-form">
                    <h3>Add More Copies</h3>
                    <form method="post">
                        <input type="hidden" name="action" value="add_copy_for_row">
                        <div class="form-group">
                            <label for="quantity">Number of copies to add</label>
                            <input type="number" name="quantity" id="quantity" min="1" max="100" value="1" required>
                        </div>
                        <button type="submit" class="primary-button">Add Copies</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        const mobileToggle = document.getElementById('mobileMenuToggle');
        const pageOverlay = document.getElementById('pageOverlay');
        const sideNav = document.querySelector('.side-nav');
        if (!mobileToggle || !pageOverlay || !sideNav) return;
        function openMobileNav(){ sideNav.classList.add('mobile-open'); pageOverlay.classList.add('active'); }
        function closeMobileNav(){ sideNav.classList.remove('mobile-open'); pageOverlay.classList.remove('active'); }
        mobileToggle.addEventListener('click', function(){ sideNav.classList.contains('mobile-open') ? closeMobileNav() : openMobileNav(); });
        pageOverlay.addEventListener('click', closeMobileNav);
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeMobileNav(); });
    });
    </script>
    <script src="assets/js/app.js" defer></script>
</body>
</html>