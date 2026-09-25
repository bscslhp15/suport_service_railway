<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();
if (!$user) {
    header('Location: ../auth/student_login.php');
    exit;
}
if ($user['role'] !== 'teacher' || $user['head_service'] !== 'library') {
    header('Location: student_home.php');
    exit;
}

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'mark_returned') {
        $borrowId = (int)($_POST['borrow_id'] ?? 0);
        $returnedAt = $_POST['returned_at'] ?? (new DateTime())->format('Y-m-d H:i:s');
        $condition = $_POST['condition'] ?? 'Good';
        
        if (mark_book_returned($borrowId, $returnedAt, $condition)) {
            $message = 'Book marked as returned successfully. Stock updated.';
        } else {
            $message = 'Unable to mark book as returned.';
            $messageType = 'error';
        }
    } elseif ($action === 'mark_fine_paid') {
        $fineId = (int)($_POST['fine_id'] ?? 0);
        if (mark_fine_paid($fineId)) {
            $message = 'Fine marked as paid.';
        } else {
            $message = 'Unable to update fine status.';
            $messageType = 'error';
        }
    }
}

$activeBorrows = get_all_active_borrows();
$overdueItems = get_overdue_borrows(20);
$unpaidFines = get_all_unpaid_fines(20);
$searchResults = [];
$studentBorrowHistory = [];
$studentFines = [];
$selectedStudent = null;

if (!empty($_GET['search'])) {
    $searchQuery = trim($_GET['search']);
    if (strlen($searchQuery) >= 2) {
        $searchResults = search_student_by_name_or_id($searchQuery, 50);
    }
}

if (!empty($_GET['student_id'])) {
    $studentId = (int)$_GET['student_id'];
    $selectedStudent = find_user_by_id($studentId);
    if ($selectedStudent) {
        $studentBorrowHistory = get_student_borrow_history($studentId, 100);
        $studentFines = get_student_fines($studentId);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Library Inventory Management | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <div id="toast-container" class="toast-container"></div>
    <div class="page-shell">
        <aside class="side-nav">
            <div class="nav-brand">
                <img src="../IMG ASSETS/passlogo.png" alt="PASS logo">
                <div>
                    <h2>PASS College</h2>
                    <p>Librarian Panel</p>
                </div>
            </div>
            <div class="nav-section">
                <h3>Library Management</h3>
                <a href="librarian_inventory.php" class="active"><span class="nav-icon"><i class="fa-solid fa-book"></i></span>Inventory</a>
                <a href="admin_home.php"><span class="nav-icon"><i class="fa-solid fa-undo"></i></span>Return to Admin</a>
            </div>
            <div class="nav-footer">
                <a href="../logout.php"><span class="nav-icon">↪</span>Logout</a>
            </div>
        </aside>
        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <div class="search-box">
                        <i class="fa-solid fa-search"></i>
                        <form method="get" style="display:flex; gap:8px; width: 100%;">
                            <input type="search" name="search" placeholder="Search student by name or ID" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" />
                            <button type="submit" class="primary-button" style="padding: 8px 16px;">Search</button>
                        </form>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Librarian</span>
                    </div>
                </div>
            </header>
            <div class="main-scroll">
                <div class="content-panel">
                    <section class="dashboard-intro">
                        <div>
                            <p class="eyebrow">Library System</p>
                            <h1>Inventory & Student Management</h1>
                            <p class="dashboard-subtitle">Track borrowing, returns, overdue items, and student fines.</p>
                        </div>
                        <?php if (!empty($message)): ?>
                            <div class="dashboard-alert <?= $messageType === 'error' ? 'alert error' : 'alert success' ?>" style="margin-top:20px;">
                                <?= htmlspecialchars($message) ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <?php if (!empty($searchResults) && empty($selectedStudent)): ?>
                        <section class="library-panel">
                            <h2>Search Results</h2>
                            <ul class="activity-list">
                                <?php foreach ($searchResults as $result): ?>
                                    <li>
                                        <strong><?= htmlspecialchars($result['full_name']) ?></strong> (<?= htmlspecialchars($result['student_id'] ?? 'N/A') ?>)
                                        <p style="font-size: 12px; color: #94a3b8; margin-top: 4px;"><?= htmlspecialchars($result['course_year'] ?? 'N/A') ?></p>
                                        <a href="?student_id=<?= $result['id'] ?>" class="primary-button" style="margin-top: 8px; display: inline-block; padding: 6px 12px; font-size: 12px;">View Details</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endif; ?>

                    <?php if ($selectedStudent): ?>
                        <section class="library-panel">
                            <h2>Student: <?= htmlspecialchars($selectedStudent['full_name']) ?></h2>
                            <div class="library-overview-grid">
                                <article class="library-card overview-card">
                                    <h3>Student ID</h3>
                                    <p><strong><?= htmlspecialchars($selectedStudent['student_id'] ?? 'N/A') ?></strong></p>
                                </article>
                                <article class="library-card overview-card">
                                    <h3>Course/Department</h3>
                                    <p><strong><?= htmlspecialchars($selectedStudent['course_year'] ?? 'N/A') ?></strong></p>
                                </article>
                                <article class="library-card overview-card">
                                    <h3>Total Unpaid Fines</h3>
                                    <p><strong style="color: #dc2626;">₱ <?= number_format(get_student_total_unpaid_fines($selectedStudent['id']), 2) ?></strong></p>
                                </article>
                            </div>

                            <h3 style="margin-top: 24px;">Borrow History</h3>
                            <div class="library-table-card">
                                <?php if (empty($studentBorrowHistory)): ?>
                                    <p class="book-meta">No borrow records found.</p>
                                <?php else: ?>
                                    <table style="width: 100%;">
                                        <thead>
                                            <tr>
                                                <th>Book</th>
                                                <th>Borrowed</th>
                                                <th>Due Date</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($studentBorrowHistory as $borrow): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($borrow['title']) ?></td>
                                                    <td><?= (new DateTime($borrow['borrow_date']))->format('M d, Y') ?></td>
                                                    <td><?= $borrow['due_date'] ? (new DateTime($borrow['due_date']))->format('M d, Y') : '—' ?></td>
                                                    <td><span class="book-badge" style="background: <?= $borrow['status'] === 'Returned' ? '#dcfce7' : ($borrow['status'] === 'Overdue' ? '#fee2e2' : '#dbeafe') ?>;"><?= htmlspecialchars($borrow['status']) ?></span></td>
                                                    <td>
                                                        <?php if ($borrow['status'] !== 'Returned'): ?>
                                                            <form method="post" style="display:inline;">
                                                                <input type="hidden" name="action" value="mark_returned">
                                                                <input type="hidden" name="borrow_id" value="<?= $borrow['id'] ?>">
                                                                <input type="hidden" name="returned_at" value="<?= (new DateTime())->format('Y-m-d H:i:s') ?>">
                                                                <button type="submit" class="primary-button" style="padding: 4px 8px; font-size: 12px;">Mark Returned</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>

                            <h3 style="margin-top: 24px;">Fines</h3>
                            <div class="library-table-card">
                                <?php if (empty($studentFines)): ?>
                                    <p class="book-meta">No fines recorded.</p>
                                <?php else: ?>
                                    <table style="width: 100%;">
                                        <thead>
                                            <tr>
                                                <th>Description</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($studentFines as $fine): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($fine['description']) ?></td>
                                                    <td>₱ <?= number_format($fine['amount'], 2) ?></td>
                                                    <td><span class="book-badge" style="background: <?= $fine['paid'] ? '#dcfce7' : '#fee2e2' ?>;"><?= $fine['paid'] ? 'Paid' : 'Unpaid' ?></span></td>
                                                    <td><?= (new DateTime($fine['created_at']))->format('M d, Y') ?></td>
                                                    <td>
                                                        <?php if (!$fine['paid']): ?>
                                                            <form method="post" style="display:inline;">
                                                                <input type="hidden" name="action" value="mark_fine_paid">
                                                                <input type="hidden" name="fine_id" value="<?= $fine['id'] ?>">
                                                                <button type="submit" class="primary-button" style="padding: 4px 8px; font-size: 12px;">Mark Paid</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                            <a href="librarian_inventory.php" class="secondary-button" style="margin-top: 16px;">Back to Inventory</a>
                        </section>
                    <?php elseif (!empty($searchResults)): ?>
                    <?php else: ?>
                        <section class="library-panel">
                            <h2>Active Borrows</h2>
                            <div class="library-table-card">
                                <?php if (empty($activeBorrows)): ?>
                                    <p class="book-meta">No active borrows.</p>
                                <?php else: ?>
                                    <table style="width: 100%; font-size: 13px;">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>Book</th>
                                                <th>Borrowed</th>
                                                <th>Due</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($activeBorrows, 0, 10) as $borrow): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($borrow['full_name']) ?></td>
                                                    <td><?= htmlspecialchars($borrow['title']) ?></td>
                                                    <td><?= (new DateTime($borrow['borrow_date']))->format('M d, Y') ?></td>
                                                    <td><?= (new DateTime($borrow['due_date']))->format('M d, Y') ?></td>
                                                    <td><span class="book-badge" style="background: <?= $borrow['status'] === 'Overdue' ? '#fee2e2' : '#dbeafe' ?>;"><?= htmlspecialchars($borrow['status']) ?></span></td>
                                                    <td>
                                                        <form method="post" style="display:inline;">
                                                            <input type="hidden" name="action" value="mark_returned">
                                                            <input type="hidden" name="borrow_id" value="<?= $borrow['id'] ?>">
                                                            <input type="hidden" name="returned_at" value="<?= (new DateTime())->format('Y-m-d H:i:s') ?>">
                                                            <button type="submit" class="primary-button" style="padding: 4px 8px; font-size: 11px;">Return</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="library-panel">
                            <h2>Overdue Items</h2>
                            <div class="library-table-card">
                                <?php if (empty($overdueItems)): ?>
                                    <p class="book-meta">No overdue items.</p>
                                <?php else: ?>
                                    <table style="width: 100%; font-size: 13px;">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>Book</th>
                                                <th>Days Overdue</th>
                                                <th>Due Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($overdueItems, 0, 10) as $item): ?>
                                                <tr style="background: #fee2e2;">
                                                    <td><?= htmlspecialchars($item['full_name']) ?></td>
                                                    <td><?= htmlspecialchars($item['title']) ?></td>
                                                    <td><strong><?= $item['days_overdue'] ?> days</strong></td>
                                                    <td><?= (new DateTime($item['due_date']))->format('M d, Y') ?></td>
                                                    <td>
                                                        <a href="?student_id=<?= $item['user_id'] ?>" class="secondary-button" style="padding: 4px 8px; font-size: 11px;">View Student</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="library-panel">
                            <h2>Unpaid Fines</h2>
                            <div class="library-table-card">
                                <?php if (empty($unpaidFines)): ?>
                                    <p class="book-meta">No unpaid fines.</p>
                                <?php else: ?>
                                    <table style="width: 100%; font-size: 13px;">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>Description</th>
                                                <th>Amount</th>
                                                <th>Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($unpaidFines, 0, 10) as $fine): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($fine['full_name']) ?></td>
                                                    <td><?= htmlspecialchars($fine['description']) ?></td>
                                                    <td><strong>₱ <?= number_format($fine['amount'], 2) ?></strong></td>
                                                    <td><?= (new DateTime($fine['created_at']))->format('M d, Y') ?></td>
                                                    <td>
                                                        <form method="post" style="display:inline;">
                                                            <input type="hidden" name="action" value="mark_fine_paid">
                                                            <input type="hidden" name="fine_id" value="<?= $fine['id'] ?>">
                                                            <button type="submit" class="primary-button" style="padding: 4px 8px; font-size: 11px;">Mark Paid</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script>
        const showToast = (message, type = 'success', duration = 5000) => {
            const container = document.getElementById('toast-container');
            if (!container) return;
            
            const toast = document.createElement('div');
            toast.className = 'toast ' + type;
            toast.innerHTML = '<span class="toast-message">' + message + '</span><button class="toast-close" type="button">&times;</button>';
            
            container.appendChild(toast);
            
            const closeBtn = toast.querySelector('.toast-close');
            const removeToast = () => {
                toast.classList.add('removing');
                setTimeout(() => toast.remove(), 300);
            };
            
            closeBtn.addEventListener('click', removeToast);
            
            if (duration > 0) {
                setTimeout(removeToast, duration);
            }
        };
        
        const dashboardAlert = document.querySelector('.dashboard-alert');
        if (dashboardAlert) {
            const isError = dashboardAlert.classList.contains('error');
            const message = dashboardAlert.textContent.trim();
            showToast(message, isError ? 'error' : 'success', 5000);
        }
    </script>
</body>
</html>
