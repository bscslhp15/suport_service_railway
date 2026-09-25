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
$showEditModal = false;
$editingBook = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_book') {
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $preferredCategory = trim($_POST['category_select'] ?? '');
        $newCategory = trim($_POST['new_category'] ?? '');
        $category = $newCategory !== '' ? $newCategory : ($preferredCategory ?: 'General');
        $totalCopies = max(0, (int)($_POST['total_copies'] ?? 0));
        $availableCopies = max(0, (int)($_POST['available_copies'] ?? 0));
        $overview = trim($_POST['overview'] ?? '');
        $coverUrl = '';

        if (!empty($newCategory)) {
            add_library_category($newCategory);
        }
        if (!empty($_FILES['cover']['name'])) {
            $uploaded = save_library_cover_upload($_FILES['cover']);
            if ($uploaded === null) {
                $message = 'Cover upload failed. Use JPG, PNG, or WebP format and try again.';
                $messageType = 'error';
            } else {
                $coverUrl = $uploaded;
            }
        }

        if (!$title || !$author || $totalCopies <= 0) {
            $message = 'Please enter a title, author, and total copies to add a new book.';
            $messageType = 'error';
        } elseif ($availableCopies > $totalCopies) {
            $message = 'Available copies cannot exceed total copies.';
            $messageType = 'error';
        } elseif ($messageType === 'error' && $message !== '') {
            // keep existing error from cover upload
        } else {
            if (add_library_book([
                'title' => $title,
                'author' => $author,
                'isbn' => $isbn,
                'category' => $category,
                'total_copies' => $totalCopies,
                'available_copies' => $availableCopies,
                'overview' => $overview,
                'rating' => 0,
                'cover_url' => $coverUrl,
            ])) {
                $message = 'New book added to inventory successfully.';
            } else {
                $message = 'Unable to add the book. Please try again.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'save_book') {
        $bookId = (int)($_POST['book_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $preferredCategory = trim($_POST['category_select'] ?? '');
        $newCategory = trim($_POST['new_category'] ?? '');
        $category = $newCategory !== '' ? $newCategory : ($preferredCategory ?: 'General');
        $totalCopies = max(0, (int)($_POST['total_copies'] ?? 0));
        $availableCopies = max(0, (int)($_POST['available_copies'] ?? 0));
        $overview = trim($_POST['overview'] ?? '');
        $coverUrl = '';

        if ($bookId > 0) {
            $editingBook = get_library_book_by_id($bookId);
        }
        if (!empty($newCategory)) {
            add_library_category($newCategory);
        }
        if (!empty($_FILES['cover']['name'])) {
            $uploaded = save_library_cover_upload($_FILES['cover']);
            if ($uploaded === null) {
                $message = 'Cover upload failed. Use JPG, PNG, or WebP format and try again.';
                $messageType = 'error';
            } else {
                $coverUrl = $uploaded;
            }
        } elseif (!empty($editingBook['cover_url'])) {
            $coverUrl = $editingBook['cover_url'];
        }

        if ($bookId <= 0 || !$editingBook) {
            $message = 'Please choose a valid book to update.';
            $messageType = 'error';
        } elseif (!$title || !$author || $totalCopies <= 0) {
            $message = 'Please enter a title, author, and total copies to update the book.';
            $messageType = 'error';
        } elseif ($availableCopies > $totalCopies) {
            $message = 'Available copies cannot exceed total copies.';
            $messageType = 'error';
        } elseif ($messageType === 'error' && $message !== '') {
            // keep upload error
        } else {
            if (update_library_book([
                'id' => $bookId,
                'title' => $title,
                'author' => $author,
                'isbn' => $isbn,
                'category' => $category,
                'total_copies' => $totalCopies,
                'available_copies' => $availableCopies,
                'overview' => $overview,
                'cover_url' => $coverUrl,
            ])) {
                $message = 'Book details updated successfully.';
                $editingBook = get_library_book_by_id($bookId);
            } else {
                $message = 'Unable to update the book. Please try again.';
                $messageType = 'error';
            }
        }
        if ($messageType === 'error') {
            $showEditModal = true;
        }
    } elseif ($action === 'update_stock') {
        $bookId = (int)($_POST['book_id'] ?? 0);
        $totalCopies = max(0, (int)($_POST['total_copies'] ?? 0));
        $availableCopies = max(0, (int)($_POST['available_copies'] ?? 0));

        if ($bookId <= 0 || $totalCopies <= 0) {
            $message = 'Please select a valid book and enter a valid total copy count.';
            $messageType = 'error';
        } elseif ($availableCopies > $totalCopies) {
            $message = 'Available copies cannot exceed total copies.';
            $messageType = 'error';
        } else {
            if (update_library_book_stock($bookId, $totalCopies, $availableCopies)) {
                $message = 'Book stock updated successfully.';
            } else {
                $message = 'Unable to update the stock. Please try again.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'add_category') {
        $categoryName = trim($_POST['category_name'] ?? '');
        if ($categoryName === '') {
            $message = 'Enter a category name to add.';
            $messageType = 'error';
        } elseif (add_library_category($categoryName)) {
            $message = 'Category added successfully.';
        } else {
            $message = 'Unable to add category. It may already exist.';
            $messageType = 'error';
        }
    } elseif ($action === 'edit_category') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['category_name'] ?? '');
        if ($categoryId <= 0 || $name === '') {
            $message = 'Please provide a valid category name.';
            $messageType = 'error';
        } elseif (update_library_category($categoryId, $name)) {
            $message = 'Category updated successfully.';
        } else {
            $message = 'Unable to update category.';
            $messageType = 'error';
        }
    } elseif ($action === 'delete_category') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        if ($categoryId > 0 && delete_library_category($categoryId)) {
            $message = 'Category deleted and related books reset to General.';
        } else {
            $message = 'Unable to delete the category.';
            $messageType = 'error';
        }
    }
}
$books = get_library_books(12);
$categories = get_library_categories();
$pendingReservations = get_active_library_reservations();
$lowStock = 0;
$totalCopiesAll = 0;
$availableCopiesAll = 0;
foreach ($books as $book) {
    $available = (int)$book['available_copies'];
    $totalCopiesAll += (int)$book['total_copies'];
    $availableCopiesAll += $available;
    if ($available <= 1) {
        $lowStock++;
    }
}
$categoryCount = count($categories);
$currentPage = basename($_SERVER['PHP_SELF']);
$serviceOpen = false;
$manageOpen = in_array($currentPage, ['library_checkin.php', 'library_catalog.php', 'library_inventory.php', 'library_reports.php', 'library_settings.php'], true);
?> 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Library Inventory | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .inventory-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 24px;
            align-items: start;
        }
        .inventory-panel#management .inventory-grid {
            grid-template-columns: 1fr;
        }
        .inventory-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 28px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            padding: 28px;
        }
        .inventory-card h2 {
            font-size: 24px;
            margin-bottom: 18px;
            color: #111827;
        }
        .inventory-form label,
        .inventory-form input,
        .inventory-form select,
        .inventory-form textarea {
            display: block;
            width: 100%;
        }
        .inventory-form label {
            margin-bottom: 8px;
            font-size: 14px;
            color: #475569;
        }
        .inventory-form input,
        .inventory-form select,
        .inventory-form textarea {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            font-size: 14px;
        }
        .inventory-form button {
            margin-top: 4px;
            padding: 14px 18px;
            border-radius: 14px;
            border: none;
            background: #2563eb;
            color: #ffffff;
            cursor: pointer;
        }
        .inventory-form button:hover {
            background: #1d4ed8;
        }
        .category-grid {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }
        .category-table,
        .book-table {
            width: 100%;
            border-collapse: collapse;
        }
        .category-table th,
        .category-table td,
        .book-table th,
        .book-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 14px;
            color: #334155;
        }
        .category-table th,
        .book-table th {
            font-weight: 600;
            color: #0f172a;
        }
        .book-cover-cell {
            width: 96px;
        }
        .book-cover {
            width: 72px;
            height: 100px;
            border-radius: 18px;
            overflow: hidden;
            background: #f8fafc;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .book-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .inline-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .inline-form input {
            width: 84px;
        }
        .inventory-meta {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-top: 20px;
        }
        .inventory-meta .meta-card {
            background: #f8fafc;
            border-radius: 20px;
            padding: 18px;
            text-align: center;
        }
        .inventory-meta .meta-card strong {
            display: block;
            margin-top: 10px;
            font-size: 20px;
            color: #111827;
        }
        .inventory-meta .meta-card span {
            color: #64748b;
        }
        .inventory-tab-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }
        .inventory-tab-button {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            color: #334155;
            padding: 12px 18px;
            cursor: pointer;
            font-weight: 600;
        }
        .inventory-tab-button.active {
            background: #2563eb;
            color: #ffffff;
            border-color: #1d4ed8;
        }
        .inventory-panel {
            display: none;
        }
        .inventory-panel.active {
            display: block;
        }
        .inventory-card-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }
        .inventory-card-header h2 {
            margin: 0;
            font-size: 20px;
        }
        .modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.65);
            z-index: 1000;
            padding: 24px;
        }
        .modal.show {
            display: flex;
        }
        .modal-content {
            background: #ffffff;
            border-radius: 24px;
            width: min(900px, 100%);
            max-height: 90vh;
            overflow-y: auto;
            padding: 28px;
            box-shadow: 0 28px 60px rgba(15, 23, 42, 0.16);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        .modal-close {
            border: none;
            background: transparent;
            color: #475569;
            font-size: 28px;
            cursor: pointer;
            line-height: 1;
        }
        .modal-form input,
        .modal-form select,
        .modal-form textarea {
            width: 100%;
        }
        .modal-form .small-text {
            margin-top: 0;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <aside class="side-nav">
            <div class="nav-brand">
                <img src="IMG ASSETS/passlogo.png" alt="PASS logo">
                <div>
                    <h2>PASS College</h2>
                    <p>Library Head</p>
                </div>
            </div>
            <div class="nav-section">
                <h3>Menu</h3>
                <a href="dashboard/librarian_home.php"><span class="nav-icon"><i class="fa-solid fa-book"></i></span>Dashboard</a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $serviceOpen ? 'true' : 'false' ?>">
                        <span class="nav-icon">▸</span>
                        Services
                        <span class="toggle-arrow">▾</span>
                    </button>
                    <div class="submenu<?= $serviceOpen ? ' open' : '' ?>" aria-hidden="<?= $serviceOpen ? 'false' : 'true' ?>">
                        <a href="dashboard/guidance_dashboard.php"><span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>Guidance</a>
                        <a href="dashboard/librarian_home.php"><span class="nav-icon"><i class="fa-solid fa-book"></i></span>Library</a>
                        <a href="dashboard/nurse_home.php"><span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>Clinic</a>
                        <a href="dashboard/ssc_head_home.php"><span class="nav-icon"><i class="fa-solid fa-award"></i></span>SSC / Scholarship</a>
                        <a href="dashboard/ssaa_home.php"><span class="nav-icon"><i class="fa-solid fa-users"></i></span>SSAA</a>
                    </div>
                </div>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $manageOpen ? 'true' : 'false' ?>">
                        <span class="nav-icon">▸</span>
                        Manage
                        <span class="toggle-arrow">▾</span>
                    </button>
                    <div class="submenu<?= $manageOpen ? ' open' : '' ?>" aria-hidden="<?= $manageOpen ? 'false' : 'true' ?>">
                        <a href="library_checkin.php"><span class="nav-icon"><i class="fa-solid fa-qrcode"></i></span>QR Check-In</a>
                        <a href="library_catalog.php"><span class="nav-icon"><i class="fa-solid fa-book-open"></i></span>Digital Catalog</a>
                        <a class="active" href="library_inventory.php"><span class="nav-icon"><i class="fa-solid fa-boxes-stacked"></i></span>Inventory</a>
                        <a href="library_reports.php"><span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>Reports</a>
                        <a href="library_settings.php"><span class="nav-icon"><i class="fa-solid fa-gear"></i></span>Settings</a>
                        <a href="library_qr.php"><span class="nav-icon"><i class="fa-solid fa-qrcode"></i></span>Library QR</a>
                    </div>
                </div>
                <a href="#support"><span class="nav-icon">⚙</span>Support</a>
                <a href="#profile"><span class="nav-icon">👤</span>Profile</a>
                <a href="dashboard/about.php"><span class="nav-icon">ℹ</span>About</a>
            </div>
            <div class="nav-footer">
                <a href="dashboard/librarian_home.php"><span class="nav-icon"><i class="fa-solid fa-gear"></i></span>Settings</a>
                <a href="logout.php"><span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>Logout</a>
            </div>
        </aside>
        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <div class="search-box">
                        <i class="fa-solid fa-search"></i>
                        <input type="search" placeholder="Search inventory, titles, or ISBN" />
                        <button type="button" class="mic-button"><i class="fa-solid fa-microphone"></i></button>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Library · Head</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Settings"><i class="fa-solid fa-gear"></i></button>
                </div>
            </header>
            <div class="main-scroll">
                <div class="content-panel">
                    <section class="dashboard-intro">
                        <div>
                            <p class="eyebrow">Inventory</p>
                            <h1>Track library copies</h1>
                            <p class="dashboard-subtitle">Manage book availability, low-stock alerts, and reservation pressure from one inventory panel.</p>
                        </div>
                    </section>
                    <?php if (!empty($message)): ?>
                        <section class="dashboard-section">
                            <div class="dashboard-summary-card <?= $messageType === 'error' ? 'alert error' : 'alert success' ?>">
                                <?= htmlspecialchars($message) ?>
                            </div>
                        </section>
                    <?php endif; ?>
                    <section class="dashboard-section">
                        <div class="inventory-tab-bar" role="tablist" aria-label="Inventory navigation">
                            <button type="button" class="inventory-tab-button active" data-tab="overview">Overview</button>
                            <button type="button" class="inventory-tab-button" data-tab="management">Book Management</button>
                            <button type="button" class="inventory-tab-button" data-tab="summary">Inventory Summary</button>
                            <button type="button" class="inventory-tab-button" data-tab="categories">Categories</button>
                        </div>

                        <div class="inventory-panel active" id="overview">
                            <div class="inventory-meta">
                                <div class="meta-card"><span>Books tracked</span><strong><?= htmlspecialchars(count($books)) ?></strong></div>
                                <div class="meta-card"><span>Available copies</span><strong><?= htmlspecialchars($availableCopiesAll) ?></strong></div>
                                <div class="meta-card"><span>Total copies</span><strong><?= htmlspecialchars($totalCopiesAll) ?></strong></div>
                                <div class="meta-card"><span>Categories</span><strong><?= htmlspecialchars($categoryCount) ?></strong></div>
                            </div>
                            <div class="inventory-card" style="margin-top:24px;">
                                <h2>Inventory overview</h2>
                                <p>Browse book metrics, manage stock, and keep categories updated without leaving this page.</p>
                            </div>
                        </div>

                        <div class="inventory-panel" id="management">
                            <div class="inventory-grid">
                                <div class="inventory-card inventory-form">
                                    <h2>Add new book</h2>
                                    <form method="post" enctype="multipart/form-data" class="inventory-form">
                                        <input type="hidden" name="action" value="add_book">
                                        <label>Title</label>
                                        <input type="text" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                                        <label>Author</label>
                                        <input type="text" name="author" required value="<?= htmlspecialchars($_POST['author'] ?? '') ?>">
                                        <label>ISBN</label>
                                        <input type="text" name="isbn" placeholder="Optional" value="<?= htmlspecialchars($_POST['isbn'] ?? '') ?>">
                                        <label>Category</label>
                                        <select name="category_select">
                                            <option value="">Select category</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= htmlspecialchars($cat['name']) ?>" <?= (($_POST['category_select'] ?? '') === $cat['name']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label>Or add new category</label>
                                        <input type="text" name="new_category" placeholder="Create a new category" value="<?= htmlspecialchars($_POST['new_category'] ?? '') ?>">
                                        <label>Total copies</label>
                                        <input type="number" name="total_copies" min="1" value="<?= htmlspecialchars($_POST['total_copies'] ?? 1) ?>" required>
                                        <label>Available copies</label>
                                        <input type="number" name="available_copies" min="0" value="<?= htmlspecialchars($_POST['available_copies'] ?? 1) ?>" required>
                                        <label>Book overview</label>
                                        <textarea name="overview" rows="4" placeholder="Add a short description..."> <?= htmlspecialchars($_POST['overview'] ?? '') ?></textarea>
                                        <label>Cover image</label>
                                        <input type="file" name="cover" accept="image/png,image/jpeg,image/webp">
                                        <div style="display:flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                                            <button type="submit">Add book</button>
                                        </div>
                                    </form>
                                </div>
                                <div class="inventory-card">
                                    <h2>Quick management</h2>
                                    <p>Use the Inventory Summary tab to open a modal for editing any book. Add new titles here and keep inventory updated in one place.</p>
                                </div>
                            </div>
                        </div>

                        <div class="inventory-panel" id="summary">
                            <div class="inventory-card">
                                <div class="inventory-card-header">
                                    <h2>Inventory summary</h2>
                                    <div style="display:flex; gap:12px; align-items:center;">
                                        <label for="summary-category-filter" style="margin:0; color:#475569;">Category</label>
                                        <select id="summary-category-filter">
                                            <option value="">All categories</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option><?= htmlspecialchars($cat['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <table class="book-table" style="margin-top:24px;">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th>Title</th>
                                            <th>Author</th>
                                            <th>Category</th>
                                            <th>Available</th>
                                            <th>Status</th>
                                            <th>Adjust</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($books)): ?>
                                            <tr><td colspan="7">No books found in inventory.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($books as $book): ?>
                                                <tr class="book-row" data-category="<?= htmlspecialchars($book['category'] ?: 'General') ?>">
                                                    <td class="book-cover-cell">
                                                        <div class="book-cover">
                                                            <?php if (!empty($book['cover_url'])): ?>
                                                                <img src="<?= htmlspecialchars($book['cover_url']) ?>" alt="<?= htmlspecialchars($book['title']) ?>">
                                                            <?php else: ?>
                                                                <span style="color:#64748b; font-size:12px;">No cover</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td><?= htmlspecialchars($book['title']) ?></td>
                                                    <td><?= htmlspecialchars($book['author']) ?></td>
                                                    <td><?= htmlspecialchars($book['category'] ?: 'General') ?></td>
                                                    <td><?= htmlspecialchars($book['available_copies']) ?></td>
                                                    <td><?= (int)$book['available_copies'] <= 1 ? 'Low stock' : htmlspecialchars($book['status']) ?></td>
                                                    <td>
                                                        <div style="display:grid; gap:8px;">
                                                            <form method="post" class="inline-form">
                                                                <input type="hidden" name="action" value="update_stock">
                                                                <input type="hidden" name="book_id" value="<?= htmlspecialchars($book['id']) ?>">
                                                                <input type="number" name="total_copies" min="1" value="<?= htmlspecialchars($book['total_copies']) ?>">
                                                                <input type="number" name="available_copies" min="0" max="<?= htmlspecialchars($book['total_copies']) ?>" value="<?= htmlspecialchars($book['available_copies']) ?>">
                                                                <button type="submit" class="secondary-button">Save</button>
                                                            </form>
                                                            <button type="button" class="secondary-button edit-book-button" style="background:#2563eb; color:#fff;"
                                                                data-book-id="<?= htmlspecialchars($book['id']) ?>"
                                                                data-title="<?= htmlspecialchars($book['title']) ?>"
                                                                data-author="<?= htmlspecialchars($book['author']) ?>"
                                                                data-isbn="<?= htmlspecialchars($book['isbn']) ?>"
                                                                data-category="<?= htmlspecialchars($book['category'] ?: 'General') ?>"
                                                                data-total-copies="<?= htmlspecialchars($book['total_copies']) ?>"
                                                                data-available-copies="<?= htmlspecialchars($book['available_copies']) ?>"
                                                                data-overview="<?= htmlspecialchars($book['overview']) ?>"
                                                                data-cover-url="<?= htmlspecialchars($book['cover_url']) ?>"
                                                            >Edit</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr id="no-books-message" style="display:none;"><td colspan="7">No books match this category.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div id="book-edit-modal" class="modal" aria-hidden="true">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h2>Edit book</h2>
                                    <button type="button" class="modal-close" aria-label="Close">&times;</button>
                                </div>
                                <form method="post" enctype="multipart/form-data" class="inventory-form modal-form">
                                    <input type="hidden" name="action" value="save_book">
                                    <input type="hidden" name="book_id" id="modal-book-id" value="">
                                    <label>Title</label>
                                    <input type="text" name="title" id="modal-title" required>
                                    <label>Author</label>
                                    <input type="text" name="author" id="modal-author" required>
                                    <label>ISBN</label>
                                    <input type="text" name="isbn" id="modal-isbn" placeholder="Optional">
                                    <label>Category</label>
                                    <select name="category_select" id="modal-category-select">
                                        <option value="">Select category</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Or add new category</label>
                                    <input type="text" name="new_category" id="modal-new-category" placeholder="Create a new category">
                                    <label>Total copies</label>
                                    <input type="number" name="total_copies" id="modal-total-copies" min="1" required>
                                    <label>Available copies</label>
                                    <input type="number" name="available_copies" id="modal-available-copies" min="0" required>
                                    <label>Book overview</label>
                                    <textarea name="overview" id="modal-overview" rows="4" placeholder="Add a short description..."></textarea>
                                    <p class="small-text" id="modal-current-cover" style="display:none;">Current cover: <a href="#" target="_blank" id="modal-cover-link">View image</a></p>
                                    <label>Cover image</label>
                                    <input type="file" name="cover" id="modal-cover" accept="image/png,image/jpeg,image/webp">
                                    <div style="display:flex; gap: 12px; flex-wrap: wrap; align-items: center; margin-top:16px;">
                                        <button type="submit">Save changes</button>
                                        <button type="button" class="secondary-button modal-close" style="background:#64748b;">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="inventory-panel" id="categories">
                            <div class="inventory-card">
                                <h2>Category manager</h2>
                                <form method="post" class="inventory-form">
                                    <input type="hidden" name="action" value="add_category">
                                    <label>New category name</label>
                                    <input type="text" name="category_name" placeholder="Example: Reference">
                                    <button type="submit">Add category</button>
                                </form>
                                <div class="category-grid">
                                    <table class="category-table">
                                        <thead>
                                            <tr><th>Category</th><th>Actions</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($categories)): ?>
                                                <tr><td colspan="2">No categories defined yet.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($categories as $cat): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($cat['name']) ?></td>
                                                        <td>
                                                            <form method="post" class="inline-form">
                                                                <input type="hidden" name="action" value="edit_category">
                                                                <input type="hidden" name="category_id" value="<?= htmlspecialchars($cat['id']) ?>">
                                                                <input type="text" name="category_name" value="<?= htmlspecialchars($cat['name']) ?>" style="min-width:150px;">
                                                                <button type="submit" class="secondary-button">Save</button>
                                                            </form>
                                                            <form method="post" class="inline-form" style="margin-top:8px;">
                                                                <input type="hidden" name="action" value="delete_category">
                                                                <input type="hidden" name="category_id" value="<?= htmlspecialchars($cat['id']) ?>">
                                                                <button type="submit" class="secondary-button" style="background:#fb7185; color:#fff;">Delete</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabButtons = document.querySelectorAll('.inventory-tab-button');
            const panels = document.querySelectorAll('.inventory-panel');
            const filter = document.getElementById('summary-category-filter');
            const bookRows = document.querySelectorAll('.book-row');
            const noBooksMessage = document.getElementById('no-books-message');

            const activateTab = (tabId) => {
                tabButtons.forEach(button => {
                    button.classList.toggle('active', button.dataset.tab === tabId);
                });
                panels.forEach(panel => {
                    panel.classList.toggle('active', panel.id === tabId);
                });
            };

            tabButtons.forEach(button => {
                button.addEventListener('click', () => activateTab(button.dataset.tab));
            });

            if (filter) {
                filter.addEventListener('change', () => {
                    const selected = filter.value;
                    let visibleCount = 0;
                    bookRows.forEach(row => {
                        const category = row.dataset.category || 'General';
                        const show = !selected || category === selected;
                        row.style.display = show ? '' : 'none';
                        if (show) visibleCount++;
                    });
                    if (noBooksMessage) {
                        noBooksMessage.style.display = visibleCount === 0 ? '' : 'none';
                    }
                });
            }

            const editModal = document.getElementById('book-edit-modal');
            const modalCloseButtons = editModal ? editModal.querySelectorAll('.modal-close') : [];
            const modalBookId = document.getElementById('modal-book-id');
            const modalTitle = document.getElementById('modal-title');
            const modalAuthor = document.getElementById('modal-author');
            const modalIsbn = document.getElementById('modal-isbn');
            const modalCategorySelect = document.getElementById('modal-category-select');
            const modalNewCategory = document.getElementById('modal-new-category');
            const modalTotalCopies = document.getElementById('modal-total-copies');
            const modalAvailableCopies = document.getElementById('modal-available-copies');
            const modalOverview = document.getElementById('modal-overview');
            const modalCurrentCover = document.getElementById('modal-current-cover');
            const modalCoverLink = document.getElementById('modal-cover-link');

            const fillModal = (book) => {
                if (!book || !modalBookId) return;
                modalBookId.value = book.id || '';
                modalTitle.value = book.title || '';
                modalAuthor.value = book.author || '';
                modalIsbn.value = book.isbn || '';
                modalTotalCopies.value = book.totalCopies || 1;
                modalAvailableCopies.value = book.availableCopies || 0;
                modalOverview.value = book.overview || '';
                modalNewCategory.value = '';

                if (modalCategorySelect) {
                    const optionToSelect = Array.from(modalCategorySelect.options).find(opt => opt.value === book.category);
                    if (optionToSelect) {
                        modalCategorySelect.value = book.category;
                    } else {
                        modalCategorySelect.value = '';
                    }
                }

                if (book.coverUrl) {
                    modalCurrentCover.style.display = '';
                    modalCoverLink.href = book.coverUrl;
                    modalCoverLink.textContent = 'View current image';
                } else if (modalCurrentCover) {
                    modalCurrentCover.style.display = 'none';
                }
            };

            const openModal = (book) => {
                if (!editModal) return;
                fillModal(book);
                editModal.classList.add('show');
                editModal.setAttribute('aria-hidden', 'false');
            };

            const closeModal = () => {
                if (!editModal) return;
                editModal.classList.remove('show');
                editModal.setAttribute('aria-hidden', 'true');
            };

            document.querySelectorAll('.edit-book-button').forEach(button => {
                button.addEventListener('click', () => {
                    const book = {
                        id: button.dataset.bookId,
                        title: button.dataset.title,
                        author: button.dataset.author,
                        isbn: button.dataset.isbn,
                        category: button.dataset.category,
                        totalCopies: button.dataset.totalCopies,
                        availableCopies: button.dataset.availableCopies,
                        overview: button.dataset.overview,
                        coverUrl: button.dataset.coverUrl,
                    };
                    openModal(book);
                });
            });

            modalCloseButtons.forEach(button => button.addEventListener('click', closeModal));
            window.addEventListener('click', (event) => {
                if (event.target === editModal) {
                    closeModal();
                }
            });

            <?php if ($showEditModal && $editingBook): ?>
                openModal(<?= json_encode([
                    'id' => $editingBook['id'] ?? '',
                    'title' => $_POST['title'] ?? $editingBook['title'] ?? '',
                    'author' => $_POST['author'] ?? $editingBook['author'] ?? '',
                    'isbn' => $_POST['isbn'] ?? $editingBook['isbn'] ?? '',
                    'category' => $_POST['category_select'] ?: ($editingBook['category'] ?? 'General'),
                    'totalCopies' => $_POST['total_copies'] ?? $editingBook['total_copies'] ?? 1,
                    'availableCopies' => $_POST['available_copies'] ?? $editingBook['available_copies'] ?? 0,
                    'overview' => $_POST['overview'] ?? $editingBook['overview'] ?? '',
                    'coverUrl' => $editingBook['cover_url'] ?? '',
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
            <?php endif; ?>
        });
    </script>
    <script src="assets/js/app.js" defer></script>
</body>
</html>
