<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();
if (!$user) {
    header('Location: ../auth/student_login.php');
    exit;
}
if (!in_array($user['role'], ['student', 'teacher'], true)) {
    header('Location: ../auth/student_login.php');
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

$roleLabel = $user['role'] === 'teacher' ? 'Teacher' : 'Student';
$dashboardLink = $user['role'] === 'teacher' ? 'teacher_home.php' : 'student_home.php';

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'request_clearance') {
        $clearanceStatus = can_student_get_clearance($user['id']);
        if ($clearanceStatus['can_clear']) {
            // Record clearance request (can be approved)
            $message = 'Clearance request submitted. You are eligible for clearance!';
        } else {
            $message = 'Unable to request clearance: ' . $clearanceStatus['reason'];
            $messageType = 'error';
        }
    }
}

$clearanceStatus = can_student_get_clearance($user['id']);
$studentFines = get_student_fines($user['id']);
$activeBorrows = get_user_borrowed_books($user['id']);
$overdueCount = count(array_filter($activeBorrows, fn($item) => $item['borrow_status'] === 'Overdue'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Library Clearance | PASS Support System</title>
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
                    <p>Library Portal</p>
                </div>
            </div>
            <div class="nav-section">
                <h3>Menu</h3>
                <a href="<?= htmlspecialchars($dashboardLink) ?>"><span class="nav-icon">⌂</span>Dashboard</a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="true">
                        <span class="nav-icon">▸</span>
                        Services
                        <span class="toggle-arrow">▾</span>
                    </button>
                    <div class="submenu open" aria-hidden="false">
                        <a href="guidance_home.php"><span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>Guidance</a>
                        <a href="library_dashboard.php"><span class="nav-icon"><i class="fa-solid fa-book"></i></span>Library</a>
                        <a href="nurse_home.php"><span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>Clinic</a>
                        <a href="ssc_head_home.php"><span class="nav-icon"><i class="fa-solid fa-award"></i></span>SSC</a>
                        <a href="ssaa_home.php"><span class="nav-icon"><i class="fa-solid fa-users"></i></span>SSAA</a>
                    </div>
                </div>
                <a href="notification.php"><span class="nav-icon">⚑</span>Notifications</a>
                <a href="clearance.php" class="active"><span class="nav-icon"><i class="fa-solid fa-check-circle"></i></span>Clearance</a>
                <a href="#profile"><span class="nav-icon">👤</span>Profile</a>
                <a href="about.php"><span class="nav-icon">ℹ</span>About</a>
            </div>
            <div class="nav-footer">
                <a href="#settings"><span class="nav-icon">⚙</span>Settings</a>
                <a href="../logout.php"><span class="nav-icon">↪</span>Logout</a>
            </div>
        </aside>
        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <div class="search-box">
                        <i class="fa-solid fa-search"></i>
                        <input type="search" placeholder="Search..." />
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta"><?= htmlspecialchars($displayCourse) ?> · <?= htmlspecialchars($displayYear) ?></span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Settings"><i class="fa-solid fa-gear"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Help"><i class="fa-solid fa-question-circle"></i></button>
                </div>
            </header>
            <div class="main-scroll">
                <div class="content-panel">
                    <section class="dashboard-intro">
                        <div>
                            <p class="eyebrow">Student Clearance</p>
                            <h1>Library Clearance Status</h1>
                            <p class="dashboard-subtitle">Check your library fines and clearance eligibility.</p>
                        </div>
                        <?php if (!empty($message)): ?>
                            <div class="dashboard-alert <?= $messageType === 'error' ? 'alert error' : 'alert success' ?>" style="margin-top:20px;">
                                <?= htmlspecialchars($message) ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="library-panel">
                        <div class="library-overview-grid">
                            <article class="library-card overview-card">
                                <h3><i class="fa-solid fa-circle-check" style="color: <?= $clearanceStatus['can_clear'] ? '#16a34a' : '#dc2626'; ?>"></i> Clearance Status</h3>
                                <p><strong style="font-size: 18px; color: <?= $clearanceStatus['can_clear'] ? '#16a34a' : '#dc2626'; ?>"><?= $clearanceStatus['can_clear'] ? 'Eligible' : 'Not Eligible' ?></strong></p>
                            </article>
                            <article class="library-card overview-card">
                                <h3>Total Unpaid Fines</h3>
                                <p><strong style="font-size: 18px; color: <?= $clearanceStatus['unpaid_fines'] > 0 ? '#dc2626' : '#16a34a'; ?>">₱ <?= number_format($clearanceStatus['unpaid_fines'], 2) ?></strong></p>
                            </article>
                            <article class="library-card overview-card">
                                <h3>Overdue Books</h3>
                                <p><strong style="font-size: 18px; color: <?= $clearanceStatus['overdue_count'] > 0 ? '#dc2626' : '#16a34a'; ?>"><?= $clearanceStatus['overdue_count'] ?> item(s)</strong></p>
                            </article>
                        </div>

                        <?php if (!$clearanceStatus['can_clear']): ?>
                            <div style="background: #fee2e2; padding: 20px; border-radius: 16px; border-left: 4px solid #dc2626; margin-top: 24px;">
                                <p style="color: #991b1b; margin: 0;"><strong>⚠️ Clearance Blocked</strong></p>
                                <p style="color: #7f1d1d; margin: 8px 0 0 0; font-size: 14px;">Reason: <?= htmlspecialchars($clearanceStatus['reason']) ?></p>
                                <p style="color: #7f1d1d; margin: 12px 0 0 0; font-size: 13px;">Please settle your fines and/or return overdue books before requesting clearance.</p>
                            </div>
                        <?php else: ?>
                            <div style="background: #dcfce7; padding: 20px; border-radius: 16px; border-left: 4px solid #16a34a; margin-top: 24px;">
                                <p style="color: #15803d; margin: 0;"><strong>✓ You are eligible for clearance!</strong></p>
                                <p style="color: #166534; margin: 12px 0 0 0; font-size: 13px;">All library requirements have been met. You can request your clearance now.</p>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="action" value="request_clearance">
                                    <button type="submit" class="primary-button" style="margin-top: 16px;">Request Clearance</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="library-panel">
                        <h2>Your Fines</h2>
                        <div class="library-table-card">
                            <?php if (empty($studentFines)): ?>
                                <p class="book-meta">No fines recorded. You're all set! ✓</p>
                            <?php else: ?>
                                <table style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>Book</th>
                                            <th>Description</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($studentFines as $fine): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($fine['title']) ?></td>
                                                <td><?= htmlspecialchars($fine['description']) ?></td>
                                                <td><strong>₱ <?= number_format($fine['amount'], 2) ?></strong></td>
                                                <td>
                                                    <span class="book-badge" style="background: <?= $fine['paid'] ? '#dcfce7' : '#fee2e2'; ?>;">
                                                        <?= $fine['paid'] ? 'Paid' : 'Unpaid' ?>
                                                    </span>
                                                </td>
                                                <td><?= (new DateTime($fine['created_at']))->format('M d, Y') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="library-panel">
                        <h2>Borrowed Books Status</h2>
                        <div class="library-table-card">
                            <?php if (empty($activeBorrows)): ?>
                                <p class="book-meta">No borrowed books.</p>
                            <?php else: ?>
                                <table style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>Book</th>
                                            <th>Borrowed</th>
                                            <th>Due Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activeBorrows as $borrow): ?>
                                            <tr style="background: <?= $borrow['borrow_status'] === 'Overdue' ? '#fee2e2' : 'transparent'; ?>">
                                                <td><?= htmlspecialchars($borrow['title']) ?></td>
                                                <td><?= (new DateTime($borrow['borrow_date']))->format('M d, Y') ?></td>
                                                <td><?= $borrow['due_date'] ? (new DateTime($borrow['due_date']))->format('M d, Y') : '—' ?></td>
                                                <td>
                                                    <span class="book-badge" style="background: <?= $borrow['borrow_status'] === 'Returned' ? '#dcfce7' : ($borrow['borrow_status'] === 'Overdue' ? '#fee2e2' : '#dbeafe'); ?>;">
                                                        <?= htmlspecialchars($borrow['borrow_status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="library-panel">
                        <h3>Clearance Information</h3>
                        <div style="background: #f8fafc; padding: 20px; border-radius: 14px; border: 1px solid #e2e8f0;">
                            <h4 style="margin-top: 0;">Clearance Requirements:</h4>
                            <ul class="activity-list" style="margin-top: 12px;">
                                <li style="background: none; padding: 8px 0; <?= $clearanceStatus['unpaid_fines'] == 0 ? 'color: #16a34a;' : 'color: #dc2626;'; ?>">
                                    <i class="fa-solid fa-<?= $clearanceStatus['unpaid_fines'] == 0 ? 'check' : 'xmark'; ?>"></i> All fines must be paid
                                </li>
                                <li style="background: none; padding: 8px 0; <?= $clearanceStatus['overdue_count'] == 0 ? 'color: #16a34a;' : 'color: #dc2626;'; ?>">
                                    <i class="fa-solid fa-<?= $clearanceStatus['overdue_count'] == 0 ? 'check' : 'xmark'; ?>"></i> No overdue books
                                </li>
                                <li style="background: none; padding: 8px 0; color: #16a34a;">
                                    <i class="fa-solid fa-check"></i> All borrowed books must be returned
                                </li>
                            </ul>
                        </div>
                    </section>
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

        document.querySelector('.nav-toggle')?.addEventListener('click', function () {
            this.nextElementSibling.classList.toggle('open');
            this.setAttribute('aria-expanded', this.getAttribute('aria-expanded') === 'true' ? 'false' : 'true');
        });
    </script>
</body>
</html>
