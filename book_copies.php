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
$book = null;
$copies = [];
$message = '';
$messageType = '';

if ($bookId > 0) {
    $book = get_library_book_by_id($bookId);
    if ($book) {
        $copies = get_library_book_copies($bookId);
    } else {
        header('Location: library_catalog.php?tab=summary&message=' . urlencode('Book not found.') . '&message_type=error');
        exit;
    }
} else {
    header('Location: library_catalog.php?tab=summary&message=' . urlencode('Invalid book ID.') . '&message_type=error');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_copies') {
        $copyType = $book['additional_info_type'];
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));

        if (!$copyType) {
            $message = 'Invalid copy type.';
            $messageType = 'error';
        } else {
            if (add_library_book_copies($bookId, $quantity, $copyType)) {
                $message = "Added $quantity $copyType(s) successfully.";
                $messageType = 'success';
                $book = get_library_book_by_id($bookId);
                $copies = get_library_book_copies($bookId);
            } else {
                $message = 'Failed to add copies. Please try again.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'add_copy_for_row') {
        $copyType = trim($_POST['copy_type'] ?? '');
        $copyNumber = max(1, (int)($_POST['copy_number'] ?? 0));
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));

        if (!$copyType || $copyNumber <= 0) {
            $message = 'Invalid copy selection.';
            $messageType = 'error';
        } else {
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
    } elseif ($action === 'add_copy_single') {
        $copyType = $book['additional_info_type'];
        $copyEntriesJson = trim($_POST['copy_entries'] ?? '[]');
        $copyEntries = json_decode($copyEntriesJson, true) ?: [];

        if (!$copyType) {
            $message = 'Invalid copy type.';
            $messageType = 'error';
        } elseif (empty($copyEntries)) {
            $message = 'Please add at least one entry.';
            $messageType = 'error';
        } else {
            try {
                $pdo = get_db();
                $prefix = match (strtolower($copyType)) {
                    'volume' => 'V',
                    'part' => 'P',
                    'number' => 'N',
                    'copies' => 'C',
                    default => 'C',
                };
                
                $addedCount = 0;
                foreach ($copyEntries as $entry) {
                    $identifier = $prefix . $entry;
                    $stmt = $pdo->prepare('INSERT INTO library_book_copies (book_id, copy_type, copy_number, identifier, lcc_letter_line, lcc_class_number, lcc_cutter_code, lcc_published_year) VALUES (:book_id, :copy_type, :copy_number, :identifier, :lcc_letter_line, :lcc_class_number, :lcc_cutter_code, :lcc_published_year)');
                    if ($stmt->execute([
                        'book_id' => $bookId,
                        'copy_type' => $copyType,
                        'copy_number' => (int)$entry,
                        'identifier' => $identifier,
                        'lcc_letter_line' => $book['lcc_letter_line'],
                        'lcc_class_number' => $book['lcc_class_number'],
                        'lcc_cutter_code' => $book['lcc_cutter_code'],
                        'lcc_published_year' => $book['lcc_published_year'],
                    ])) {
                        $addedCount++;
                    }
                }
                
                if ($addedCount > 0) {
                    // Update book stock
                    $newTotal = ($book['total_copies'] ?? 0) + $addedCount;
                    $newAvailable = ($book['available_copies'] ?? 0) + $addedCount;
                    update_library_book_stock($bookId, $newTotal, $newAvailable);
                    
                    $message = "Added $addedCount " . ($addedCount === 1 ? $copyType : $copyType . 's') . " successfully.";
                    $messageType = 'success';
                    $book = get_library_book_by_id($bookId);
                    $copies = get_library_book_copies($bookId);
                } else {
                    $message = 'Failed to add entries. Please try again.';
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                $message = 'Error adding entries: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

$virtualCopies = [];
$copyType = $book['additional_info_type'] ?? null;
$quantity = (int)($book['additional_info_quantity'] ?? 0);
if (empty($copies) && $quantity > 0 && in_array($copyType, ['volume', 'part', 'number', 'copies'], true)) {
    $prefix = match (strtolower($copyType)) {
        'volume' => 'V',
        'part' => 'P',
        'number' => 'N',
        'copies' => 'C',
        default => 'C',
    };
    for ($i = 1; $i <= $quantity; $i++) {
        $virtualCopies[] = [
            'copy_type' => $copyType,
            'copy_number' => $i,
            'identifier' => $prefix . $i,
            'lcc_letter_line' => $book['lcc_letter_line'],
            'lcc_class_number' => $book['lcc_class_number'],
            'lcc_cutter_code' => $book['lcc_cutter_code'],
            'lcc_published_year' => $book['lcc_published_year'],
            'lcc_additional_info' => $book['lcc_additional_info'] ?? null,
        ];
    }
}

if (!empty($copies) && in_array($copyType, ['volume', 'part', 'number'], true)) {
    $groupedCopies = [];
    foreach ($copies as $copy) {
        $groupKey = $copy['copy_number'];
        if (!isset($groupedCopies[$groupKey])) {
            $groupedCopies[$groupKey] = [
                'copy_type' => $copy['copy_type'],
                'copy_number' => $copy['copy_number'],
                'lcc_letter_line' => $copy['lcc_letter_line'],
                'lcc_class_number' => $copy['lcc_class_number'],
                'lcc_cutter_code' => $copy['lcc_cutter_code'],
                'lcc_published_year' => $copy['lcc_published_year'],
                'lcc_additional_info' => $copy['lcc_additional_info'],
                'copies' => [],
            ];
        }
        $groupedCopies[$groupKey]['copies'][] = $copy;
    }
    $displayCopies = array_values($groupedCopies);
} else {
    $displayCopies = !empty($copies) ? $copies : $virtualCopies;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Copies - <?= htmlspecialchars($book['title'] ?? 'Unknown') ?> - Library Management</title>
    <link rel="stylesheet" href="assets/css/styles.css">
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
        .copy-page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 24px;
            padding: 22px 24px;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
        }
        .copy-page-header h1 {
            margin: 0 0 8px 0;
            font-size: 26px;
            line-height: 1.1;
            color: #0f172a;
        }
        .copy-page-header p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
            max-width: 760px;
        }
        .copy-meta-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
        }
        .copy-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid #dbe4f0;
            color: #0f172a;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
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
        .copy-actions-panel {
            margin: 0 0 24px 0;
            padding: 24px;
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.05);
        }
        .copy-actions-head {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 12px;
            align-items: end;
            margin-bottom: 20px;
        }
        .copy-actions-head h3 {
            margin: 0;
            color: #0f172a;
            font-size: 20px;
        }
        .copy-actions-head p {
            margin: 6px 0 0 0;
            color: #64748b;
            font-size: 14px;
        }
        .copy-actions-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }
        .copy-action-card {
            padding: 20px;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
        }
        .copy-action-card h4 {
            margin: 0 0 6px 0;
            color: #0f172a;
            font-size: 16px;
        }
        .copy-action-card .card-note {
            margin: 0 0 16px 0;
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }
        .copy-action-card .primary-button {
            width: 100%;
            margin-top: 14px;
        }
        .copy-action-card .form-grid {
            margin-bottom: 0;
            grid-template-columns: 1fr;
        }
        .copy-entry-list {
            margin-top: 12px;
            padding: 12px;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
        }
        .copy-entry-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            padding: 10px 12px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }
        .copy-entry-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 700;
            font-size: 12px;
        }
        .copy-entry-remove {
            margin-left: auto;
            padding: 6px 10px;
            border: none;
            border-radius: 8px;
            background: #fee2e2;
            color: #b91c1c;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .copy-helper {
            margin-top: 10px;
            font-size: 12px;
            color: #64748b;
        }
        .copies-section {
            margin-top: 32px;
        }
        .copies-section h3 {
            margin: 0 0 16px 0;
            color: #1e293b;
        }
        .copy-item {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .copy-info {
            flex: 1;
        }
        .copy-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 8px;
        }
        .copy-detail {
            display: flex;
            flex-direction: column;
        }
        .copy-label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .copy-value {
            font-size: 13px;
            color: #1e293b;
        }
        .add-copies-form {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 0;
        }
        .add-copies-form h3 {
            margin: 0 0 16px 0;
            color: #1e293b;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-size: 14px;
            color: #374151;
            margin-bottom: 6px;
            font-weight: 500;
        }
        .form-group select,
        .form-group input {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-note {
            margin-top: 8px;
            font-size: 12px;
            color: #64748b;
        }
        .copies-section {
            margin-top: 32px;
            overflow-x: auto;
        }
        .book-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
            background: #ffffff;
            margin-top: 16px;
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
        .book-table th:first-child,
        .book-table td:first-child {
            width: 90px;
        }
        .book-table th:nth-child(3),
        .book-table td:nth-child(3),
        .book-table th:nth-child(4),
        .book-table td:nth-child(4),
        .book-table th:nth-child(5),
        .book-table td:nth-child(5),
        .book-table th:nth-child(6),
        .book-table td:nth-child(6) {
            min-width: 130px;
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
        @media (max-width: 960px) {
            .copy-page-header,
            .copy-actions-head {
                flex-direction: column;
                align-items: flex-start;
            }
            .copy-meta-badges {
                justify-content: flex-start;
            }
            .copy-actions-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 640px) {
            .copy-page-header,
            .copy-actions-panel,
            .book-overview,
            .add-copies-form {
                padding: 18px;
                border-radius: 14px;
            }
            .copy-page-header h1 {
                font-size: 22px;
            }
        }
    </style>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/responsive.css">
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
                <div class="topbar-right">
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
                <div class="page-overlay" id="pageOverlay"></div>
                <?php if ($message): ?>
                    <div class="message message-<?= $messageType ?>">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Back Button -->
                <div style="margin-bottom: 24px;">
                    <a href="library_catalog.php?tab=summary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; color: #334155; text-decoration: none; font-size: 14px; font-weight: 500; transition: all 0.2s ease;">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back to Inventory Summary
                    </a>
                </div>

                <!-- Book Overview -->
                <div class="copy-page-header">
                    <div>
                        <h1><?= htmlspecialchars($book['title']) ?></h1>
                        <p>Manage the individual copies for this book, whether you want the system to auto-generate copies or add specific numbered entries.</p>
                    </div>
                    <div class="copy-meta-badges">
                        <span class="copy-badge"><i class="fa-solid fa-layer-group"></i> <?= count($displayCopies) ?> listed</span>
                        <span class="copy-badge"><i class="fa-solid fa-book"></i> <?= htmlspecialchars($book['additional_info_type'] ? ucfirst($book['additional_info_type']) : 'Book') ?></span>
                    </div>
                </div>

                <div class="book-overview">
                    <h2>Book Overview</h2>
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
                            <div class="overview-label">LCC</div>
                            <div class="overview-value"><?= htmlspecialchars(trim(implode(' ', array_filter([$book['lcc_letter_line'], $book['lcc_class_number'], $book['lcc_cutter_code'], $book['lcc_published_year'], $book['lcc_additional_info']]))) ?: 'N/A') ?></div>
                        </div>
                        <div class="overview-item">
                            <div class="overview-label">Category</div>
                            <div class="overview-value"><?= htmlspecialchars($book['category'] ?: 'General') ?></div>
                        </div>
                        <?php if ($book['additional_info_type']): ?>
                        <div class="overview-item">
                            <div class="overview-label">Additional Info</div>
                            <div class="overview-value"><?= htmlspecialchars(ucfirst($book['additional_info_type'])) ?> (<?= htmlspecialchars($book['additional_info_quantity']) ?>)</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($book['additional_info_type'] && in_array($book['additional_info_type'], ['volume', 'part', 'number', 'copies'])): ?>
                <div class="copy-actions-panel">
                    <div class="copy-actions-head">
                        <div>
                            <h3>Add Copies</h3>
                            <p>Choose auto-generated copies for a quick stock increase, or add specific numbers when the copy labels matter.</p>
                        </div>
                    </div>

                    <div class="copy-actions-grid">
                        <div class="copy-action-card">
                            <h4>Auto-Generated</h4>
                            <p class="card-note">Use this when you want the system to create the next copy numbers automatically. Example: quantity 5 adds the next 5 available copies.</p>
                            <form method="post" id="add-copies-auto-form">
                                <input type="hidden" name="action" value="add_copies">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="quantity">Quantity</label>
                                        <input type="number" name="quantity" id="quantity" min="1" value="1">
                                        <div class="form-note">Applies to the book's current copy type.</div>
                                    </div>
                                </div>
                                <button type="submit" class="primary-button">Add <?= ucfirst($book['additional_info_type'] === 'copies' ? 'copy' : $book['additional_info_type']) ?>s</button>
                            </form>
                        </div>

                        <div class="copy-action-card">
                            <h4>Specific Numbers</h4>
                            <p class="card-note">Use this when you already know the exact copy numbers you want to store, such as 10, 7, or 11.</p>
                            <form method="post" id="add-copies-form">
                                <input type="hidden" name="action" value="add_copy_single">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="copy-number">Enter <?= ucfirst($book['additional_info_type'] === 'copies' ? 'copy' : $book['additional_info_type']) ?> Number</label>
                                        <div style="display: flex; gap: 8px;">
                                            <input type="number" name="copy-number" id="copy-number" min="1" placeholder="e.g., 10, 7, 11..." style="flex: 1;">
                                            <button type="button" id="add-copy-more-btn" style="padding: 10px 16px; background: #0f766e; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600;">Add More</button>
                                        </div>
                                        <div id="copy-entries-list" class="copy-entry-list" style="display:none;">
                                            <small style="color: #64748b; display: block; margin-bottom: 8px; font-weight: 600;">Entries to add:</small>
                                        </div>
                                        <div class="copy-helper">Tip: Click Add More each time to build the list before submitting.</div>
                                    </div>
                                </div>
                                <input type="hidden" name="copy_entries" id="copy-entries-field" value="">
                                <button type="submit" class="primary-button">Add Selected <?= ucfirst($book['additional_info_type'] === 'copies' ? 'copy' : $book['additional_info_type']) ?>s</button>
                            </form>
                        </div>
                    </div>
                </div>

                <script>
                    let copyEntries = [];
                    const copyNumberInput = document.getElementById('copy-number');
                    const addCopyMoreBtn = document.getElementById('add-copy-more-btn');
                    const copyEntriesList = document.getElementById('copy-entries-list');
                    const copyEntriesField = document.getElementById('copy-entries-field');
                    const addCopiesForm = document.getElementById('add-copies-form');

                    addCopyMoreBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        const value = copyNumberInput.value.trim();
                        if (!value) {
                            alert('Please enter a number');
                            return;
                        }
                        
                        // Check if already added
                        if (copyEntries.includes(value)) {
                            alert('This number is already in the list');
                            return;
                        }
                        
                        copyEntries.push(value);
                        
                        if (copyEntries.length > 0) {
                            copyEntriesList.style.display = '';
                            copyEntriesList.innerHTML = '<small style="color: #64748b; display: block; margin-bottom: 8px; font-weight: 600;">Entries to add:</small>' +
                                copyEntries.map((entry, idx) => `
                                    <div class="copy-entry-item">
                                        <span class="copy-entry-pill">${entry}</span>
                                        <button type="button" class="copy-entry-remove remove-copy-entry" data-index="${idx}">Remove</button>
                                    </div>
                                `).join('');
                            
                            document.querySelectorAll('.remove-copy-entry').forEach(btn => {
                                btn.addEventListener('click', (e) => {
                                    e.preventDefault();
                                    const idx = parseInt(btn.dataset.index);
                                    copyEntries.splice(idx, 1);
                                    addCopyMoreBtn.click(); // Refresh the display
                                    copyNumberInput.focus();
                                });
                            });
                        }
                        copyNumberInput.value = '';
                        copyNumberInput.focus();
                    });

                    addCopiesForm.addEventListener('submit', (e) => {
                        if (copyEntries.length === 0) {
                            e.preventDefault();
                            alert('Please add at least one entry');
                            return;
                        }
                        copyEntriesField.value = JSON.stringify(copyEntries);
                    });
                </script>
                </div>
                <?php endif; ?>

                <!-- Copies List -->
                <div class="copies-section">
                    <?php
                    $type = $book['additional_info_type'] ?? null;
                    if ($type === 'volume') {
                        $label = 'Volumes';
                    } elseif ($type === 'part') {
                        $label = 'Parts';
                    } elseif ($type === 'number') {
                        $label = 'Numbers';
                    } elseif ($type === 'copies') {
                        $label = 'Books';
                    } else {
                        $label = 'Books';
                    }
                    ?>
                    <h3><?php echo $label; ?> (<?= count($displayCopies) ?>)</h3>
                    <?php if (empty($displayCopies)): ?>
                        <p>No <?php echo strtolower($label); ?> found for this book.</p>
                    <?php else: ?>
                        <table class="book-table">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Title</th>
                                    <th>Class Number</th>
                                    <th>Cutter Code</th>
                                    <th>Year Published</th>
                                    <th>Additional Info</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($displayCopies as $copy): ?>
                                    <?php
                                    $isGroup = isset($copy['copies']) && is_array($copy['copies']);
                                    $groupCount = $isGroup ? count($copy['copies']) : 1;
                                    $copyType = $copy['copy_type'] ?? $book['additional_info_type'] ?? 'copies';
                                    $copyNumber = $copy['copy_number'];
                                    $title = htmlspecialchars($book['title']);
                                    if ($copyType === 'volume') {
                                        $title .= " Volume $copyNumber";
                                    } elseif ($copyType === 'part') {
                                        $title .= " Part $copyNumber";
                                    } elseif ($copyType === 'number') {
                                        $title .= " No. $copyNumber";
                                    } elseif ($copyType === 'copies') {
                                        $title .= " Copy $copyNumber";
                                    }
                                    $additional = '';
                                    if ($copyType === 'volume') {
                                        $additional = "V.$copyNumber";
                                    } elseif ($copyType === 'part') {
                                        $additional = "P.$copyNumber";
                                    } elseif ($copyType === 'number') {
                                        $additional = "N.$copyNumber";
                                    } elseif ($copyType === 'copies') {
                                        $additional = "C.$copyNumber";
                                    }
                                    if ($isGroup) {
                                        $additional .= " ({$groupCount} copies)";
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="book-cover-wrapper">
                                                <div class="book-cover">
                                                    <?php if (!empty($book['cover_url'])): ?>
                                                        <img src="<?= htmlspecialchars($book['cover_url']) ?>" alt="<?= htmlspecialchars($book['title']) ?>">
                                                    <?php else: ?>
                                                        <span style="color:#64748b; font-size:12px;">No cover</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?= $title ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($copy['lcc_class_number'] ?: 'N/A') ?></td>
                                        <td><?= htmlspecialchars($copy['lcc_cutter_code'] ?: 'N/A') ?></td>
                                        <td><?= htmlspecialchars($copy['lcc_published_year'] ?: 'N/A') ?></td>
                                        <td><?= htmlspecialchars($additional ?: 'N/A') ?></td>
                                        <td><?= htmlspecialchars($book['available_copies'] > 0 ? 'Available' : 'Borrowed') ?></td>
                                        <td>
                                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                                <?php if (in_array($copyType, ['volume', 'part', 'number'], true)): ?>
                                                    <?php $addCopiesLink = sprintf('add_copies.php?book_id=%s&copy_type=%s&copy_number=%s', urlencode($bookId), urlencode($copyType), urlencode($copyNumber)); ?>
                                                    <a href="<?= htmlspecialchars($addCopiesLink) ?>" class="secondary-button" style="background:#16a34a; color:#fff; text-decoration: none; display: inline-block;">Add Copies</a>
                                                <?php endif; ?>
                                                <button type="button" class="secondary-button" style="background:#dc2626; color:#fff;">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

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