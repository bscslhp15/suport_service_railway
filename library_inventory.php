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
mark_overdue_library_items();

$message = '';
$messageType = 'success';
$selectedUser = null;
$searchResults = [];
$selectedUserBorrows = [];
$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'search_user') {
        $searchQuery = trim($_POST['search_query'] ?? '');
        if (!empty($searchQuery)) {
            // Only search for registered users (students/teachers)
            $pdo = get_db();
            $stmt = $pdo->prepare('
                SELECT * FROM users 
                WHERE (full_name LIKE ? OR id LIKE ?) 
                  AND role IN ("student", "teacher")
                LIMIT 10
            ');
            $searchTerm = '%' . $searchQuery . '%';
            $stmt->execute([$searchTerm, $searchTerm]);
            $searchResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $message = 'Please enter a name or ID to search.';
            $messageType = 'error';
        }
    } elseif ($action === 'select_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            $pdo = get_db();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
            $stmt->execute(['id' => $userId]);
            $selectedUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($selectedUser) {
                $selectedUserBorrows = get_user_borrowed_books($userId);
            } else {
                $message = 'User not found.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'record_borrow') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $bookId = (int)($_POST['book_id'] ?? 0);
        $copyNumber = (int)($_POST['copy_number'] ?? 0);
        
        if ($userId <= 0 || $bookId <= 0) {
            $message = 'Please select a valid student and book.';
            $messageType = 'error';
        } else {
            $now = new DateTime();
            $borrowDays = (int)get_library_setting('borrow_duration_school_days', 3);
            $dueDate = add_school_days($now, $borrowDays)->format('Y-m-d H:i:s');
            if (borrow_book($userId, $bookId, $now->format('Y-m-d H:i:s'), $dueDate, $copyNumber)) {
                $message = 'Borrow recorded successfully. Due date: ' . (new DateTime($dueDate))->format('M d, Y');
                $selectedUserBorrows = get_user_borrowed_books($userId);
            } else {
                $message = 'Unable to record the borrow. Please try again.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'record_return') {
        $borrowId = (int)($_POST['borrow_id'] ?? 0);
        if ($borrowId > 0 && mark_book_returned($borrowId, (new DateTime())->format('Y-m-d H:i:s'))) {
            $message = 'Return recorded successfully.';
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId > 0) {
                $selectedUserBorrows = get_user_borrowed_books($userId);
            }
        } else {
            $message = 'Unable to record the return. Please try again.';
            $messageType = 'error';
        }
    } elseif ($action === 'mark_all_fines_paid') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            $stmt = $pdo->prepare('SELECT id FROM library_fines WHERE user_id = :user_id AND paid = 0');
            $stmt->execute(['user_id' => $userId]);
            $fineIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $paidCount = 0;
            foreach ($fineIds as $fineId) {
                if (mark_fine_paid((int)$fineId)) {
                    $paidCount++;
                }
            }
            if ($paidCount > 0) {
                $message = 'Marked ' . $paidCount . ' unpaid fine' . ($paidCount === 1 ? '' : 's') . ' as paid.';
                $pdo = get_db();
                $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
                $stmt->execute(['id' => $userId]);
                $selectedUser = $stmt->fetch(PDO::FETCH_ASSOC);
                $selectedUserBorrows = get_user_borrowed_books($userId);
            } else {
                $message = 'No unpaid fines were found for this student.';
                $messageType = 'error';
            }
        } else {
            $message = 'Invalid user selected. Please try again.';
            $messageType = 'error';
        }
    } elseif ($action === 'mark_fine_paid') {
        $fineId = (int)($_POST['fine_id'] ?? 0);
        if ($fineId > 0 && mark_fine_paid($fineId)) {
            $message = 'Fine marked as paid.';
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId > 0) {
                $pdo = get_db();
                $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
                $stmt->execute(['id' => $userId]);
                $selectedUser = $stmt->fetch(PDO::FETCH_ASSOC);
                $selectedUserBorrows = get_user_borrowed_books($userId);
            }
        } else {
            $message = 'Unable to mark fine as paid. Please try again.';
            $messageType = 'error';
        }
    } elseif ($action === 'send_fines_email') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            // Prepare email data for client-side JavaScript
            $stmt = $pdo->prepare('SELECT full_name, email FROM users WHERE id = ? AND role = "student"');
            $stmt->execute([$userId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student && !empty($student['email'])) {
                // Get all unpaid fines for this user
                $finesStmt = $pdo->prepare('
                    SELECT lf.amount, lb.title, lb.author, b.due_date
                    FROM library_fines lf
                    JOIN library_borrows b ON lf.borrow_id = b.id
                    JOIN library_books lb ON b.book_id = lb.id
                    WHERE lf.user_id = ? AND lf.paid = 0
                    ORDER BY b.due_date DESC
                ');
                $finesStmt->execute([$userId]);
                $unpaidFines = $finesStmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($unpaidFines)) {
                    $totalFines = 0;
                    $booksText = '';
                    foreach ($unpaidFines as $fine) {
                        $totalFines += (float)$fine['amount'];
                        $daysOverdue = (new DateTime($fine['due_date']))->diff(new DateTime())->days;
                        $booksText .= htmlspecialchars($fine['title']) . "\n";
                        $booksText .= 'Author: ' . htmlspecialchars($fine['author']) . " | ";
                        $booksText .= 'Overdue: ' . $daysOverdue . ' day(s) | ';
                        $booksText .= 'Fine: ₱' . number_format((float)$fine['amount'], 2) . "\n\n";
                    }
                    
                    $emailData = [
                        'send' => true,
                        'user_name' => $student['full_name'],
                        'email' => $student['email'],
                        'total_fines' => number_format($totalFines, 2),
                        'overdue_books' => $booksText,
                        'portal_link' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/THESIS/SUPPORTSERVICESYSTEM/dashboard/library_dashboard.php',
                        'request_id' => 'LIBFINE-' . time() . '-' . $userId
                    ];
                    $message = 'Email sending to student...';
                    $messageType = 'success';
                } else {
                    $message = 'No unpaid fines found for this student.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Student email not found.';
                $messageType = 'error';
            }
            $pdo = get_db();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
            $stmt->execute(['id' => $userId]);
            $selectedUser = $stmt->fetch(PDO::FETCH_ASSOC);
            $selectedUserBorrows = get_user_borrowed_books($userId);
        } else {
            $message = 'Unable to send email. Please try again.';
            $messageType = 'error';
        }
    } elseif ($action === 'mark_reservation_picked_up') {
        $reservationId = (int)($_POST['reservation_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $bookId = (int)($_POST['book_id'] ?? 0);
        
        if ($reservationId > 0 && $userId > 0 && $bookId > 0) {
            $stmt = $pdo->prepare('SELECT additional_info FROM library_reservations WHERE id = :id');
            $stmt->execute(['id' => $reservationId]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            $reservationInfo = $reservation['additional_info'] ?? '';
            $copyNumber = 0;
            if (preg_match('/\b(?:volume|part|number)\s+(\d+)\b/i', $reservationInfo, $matches)) {
                $copyNumber = (int)$matches[1];
            }

            $now = new DateTime();
            $borrowDays = (int)get_library_setting('borrow_duration_school_days', 3);
            $dueDate = add_school_days($now, $borrowDays)->format('Y-m-d H:i:s');
            if (fulfill_reserved_book_pickup($reservationId, $userId, $bookId, $now->format('Y-m-d H:i:s'), $dueDate, $copyNumber)) {
                $message = 'Book picked up. Borrow recorded with due date: ' . (new DateTime($dueDate))->format('M d, Y');
                $activeTab = 'borrowed';
            } else {
                $message = 'Unable to record the borrow. Please try again.';
                $messageType = 'error';
            }
        } else {
            $message = 'Invalid selection. Please try again.';
            $messageType = 'error';
        }
    } elseif ($action === 'cancel_reservation_admin') {
        $reservationId = (int)($_POST['reservation_id'] ?? 0);
        if ($reservationId > 0) {
            $pdo = get_db();
            $stmt = $pdo->prepare('
                SELECT user_id FROM library_reservations WHERE id = :id
            ');
            $stmt->execute(['id' => $reservationId]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reservation && cancel_library_reservation($reservationId, $reservation['user_id'])) {
                $message = 'Reservation cancelled successfully.';
                $activeTab = 'reservations';
            } else {
                $message = 'Unable to cancel the reservation. Please try again.';
                $messageType = 'error';
            }
        } else {
            $message = 'Invalid reservation. Please try again.';
            $messageType = 'error';
        }
    }
}

$activeTab = $activeTab ?? $_POST['active_tab'] ?? $_GET['tab'] ?? 'borrowed';
$books = get_library_books(100);
$availableBooks = array_values(array_filter($books, function ($book) {
    return !empty($book['available_copies']) && $book['available_copies'] > 0;
}));

$pdo = get_db();
$stmt = $pdo->prepare('SELECT u.id AS user_id, u.full_name, u.course_year, u.role, f.id AS fine_id, f.amount, f.description, f.created_at, lb.title AS book_title, b.due_date FROM library_fines f JOIN library_borrows b ON f.borrow_id = b.id JOIN library_books lb ON b.book_id = lb.id JOIN users u ON f.user_id = u.id WHERE f.paid = 0 AND u.role != "teacher" ORDER BY u.full_name ASC, f.created_at DESC');
$stmt->execute();
$unpaidFineRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$userFineData = [];
foreach ($unpaidFineRows as $fine) {
    $userId = $fine['user_id'];
    if (!isset($userFineData[$userId])) {
        $userFineData[$userId] = [
            'user' => [
                'id' => $userId,
                'full_name' => $fine['full_name'],
                'course_year' => $fine['course_year'],
                'role' => $fine['role'],
                'total_fines' => 0,
            ],
            'fines' => []
        ];
    }
    $userFineData[$userId]['user']['total_fines'] += $fine['amount'];
    $userFineData[$userId]['fines'][] = [
        'fine_id' => $fine['fine_id'],
        'amount' => $fine['amount'],
        'description' => $fine['description'],
        'created_at' => $fine['created_at'],
        'book_title' => $fine['book_title'],
        'due_date' => $fine['due_date'],
    ];
}

$currentPage = basename($_SERVER['PHP_SELF']);
$serviceOpen = false;
$manageOpen = false;
?> 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Inventory | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="assets/css/styles.css">
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
        .library-page-header > div:first-child { position: relative; z-index: 2; width: 65%; }
        .library-page-header .eyebrow { margin: 0; color: #861b17 !important; font-family: Arial, sans-serif; font-size: 14px; font-weight: 700; letter-spacing: 3px; line-height: 1; text-transform: uppercase; }
        .library-page-header h1 { margin: 14px 0 12px; color: #111 !important; font-family: Georgia, serif; font-size: clamp(36px, 4vw, 62px); font-weight: 600; line-height: 1.05; }
        .library-page-header .dashboard-subtitle { max-width: 760px; margin: 0; color: #34302d !important; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; }
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
        @media (max-width: 768px) { .library-page-header { min-height: 245px; padding: 32px 24px 110px; } .library-page-header > div:first-child { width: 100%; } .library-page-header h1 { font-size: 36px; } .library-header__art { width: 55%; opacity: .35; } }

        .library-status-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 2rem;
        }
        .search-manage-grid {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .search-top-grid {
            display: grid;
            grid-template-columns: minmax(320px, 420px) 1fr;
            gap: 24px;
            align-items: flex-start;
        }
        .search-panel,
        .user-details-panel,
        .borrow-history-panel,
        .fines-panel {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .search-panel .inventory-card,
        .user-details-panel .inventory-card,
        .borrow-history-panel .inventory-card,
        .fines-panel .inventory-card {
            padding: 24px;
        }
        .search-panel h2,
        .detail-panel h2,
        .user-details-panel h2,
        .borrow-history-panel h2,
        .fines-panel h2 {
            margin-bottom: 12px;
        }
        .section-heading {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 14px;
        }
        .section-heading h2,
        .section-heading h3 {
            margin: 0;
            font-size: 18px;
            color: #111827;
        }
        .section-hint,
        .card-subtitle {
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
        }
        .section-hint {
            text-align: right;
            max-width: 220px;
        }
        .book-search-field {
            margin-bottom: 14px;
        }
        .book-search-field input {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            font-size: 14px;
        }
        .book-results {
            display: grid;
            gap: 10px;
            background: #f8fafc;
            border-radius: 14px;
            max-height: 320px;
            overflow-y: auto;
            margin-bottom: 16px;
            border: 1px solid #e2e8f0;
            padding: 12px;
        }
        .book-result-item {
            width: 100%;
            text-align: left;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 14px 16px;
            cursor: pointer;
            background: #ffffff !important;
            color: #0f172a;
            -webkit-appearance: none;
            appearance: none;
            transition: background 0.2s ease, border-color 0.2s ease;
        }
        .book-result-item:hover,
        .book-result-item.active,
        .book-result-item:focus,
        .book-result-item:active {
            background: #ffffff !important;
            border-color: #cbd5e1;
            outline: none;
        }
        .book-result-item strong {
            display: block;
            margin-bottom: 4px;
            color: #0f172a;
        }
        .book-result-item span {
            display: block;
            color: #475569;
            font-size: 13px;
        }
        .book-result-prompt,
        .no-results-message {
            color: #64748b;
            margin: 0;
            font-size: 14px;
            padding: 12px 0;
        }
        .selected-book-summary {
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #0f172a;
            font-size: 14px;
            margin-bottom: 16px;
        }
        .search-results {
            background: #f8fafc;
            border-radius: 14px;
            max-height: 320px;
            overflow-y: auto;
            margin-bottom: 16px;
            border: 1px solid #e2e8f0;
        }
        .search-result-form {
            margin: 0;
        }
        .search-result-item {
            width: 100%;
            text-align: left;
            background: none;
            border: none;
            padding: 14px 16px;
            cursor: pointer;
            border-bottom: 1px solid #e2e8f0;
            transition: background 0.2s ease;
        }
        .search-result-item:last-child {
            border-bottom: none;
        }
        .search-result-item span {
            display: block;
            margin-top: 6px;
            color: #475569;
            font-size: 13px;
        }
        .search-result-item:hover {
            background: #e0e7ff;
        }
        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .section-heading h3 {
            margin: 0;
            font-size: 18px;
        }
        .section-hint {
            color: #64748b;
            font-size: 13px;
        }
        .inventory-card .borrow-table {
            margin-top: 12px;
        }
        .inventory-card .borrow-table th,
        .inventory-card .borrow-table td {
            padding: 10px 12px;
        }
        .inventory-card .inventory-form button {
            width: auto;
            padding: 12px 18px;
        }
        .inventory-card .user-info-card {
            margin-bottom: 18px;
        }
        .inventory-card .user-info-card div {
            gap: 6px;
        }
        .inventory-card .prompt-card {
            padding: 24px;
            text-align: center;
            color: #475569;
            background: #f8fafc;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }
        .inventory-card .prompt-card strong {
            display: block;
            margin-bottom: 10px;
            color: #111827;
        }
        .inventory-card .summary-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
            margin-top: 10px;
        }
        .inventory-card .summary-item {
            background: #f8fafc;
            padding: 12px 14px;
            border-radius: 14px;
            font-size: 13px;
            color: #334155;
        }
        .inventory-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 28px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            padding: 28px;
        }
        .inventory-card h2 {
            font-size: 20px;
            margin-bottom: 18px;
            color: #111827;
        }
        .inventory-form label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #475569;
            font-weight: 600;
        }
        .inventory-form input,
        .inventory-form select {
            display: block;
            width: 100%;
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            font-size: 14px;
        }
        .inventory-form input:focus,
        .inventory-form select:focus {
            outline: none;
            border-color: #2563eb;
            background: #ffffff;
        }
        .inventory-form button {
            padding: 12px 18px;
            border-radius: 14px;
            border: none;
            background: #2563eb;
            color: #ffffff;
            cursor: pointer;
            font-weight: 600;
            width: 100%;
        }
        .inventory-form button:hover {
            background: #1d4ed8;
        }
        .search-results {
            background: #f8fafc;
            border-radius: 14px;
            max-height: 320px;
            overflow-y: auto;
            margin-bottom: 16px;
            border: 1px solid #e2e8f0;
        }
        .search-result-item {
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .search-result-item:hover {
            background: #e0e7ff;
        }
        .user-info-card {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .user-info-card strong {
            color: #0369a1;
        }
        .borrow-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        .borrow-table th,
        .borrow-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 13px;
        }
        .borrow-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #0f172a;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-borrowed {
            background: #fef3c7;
            color: #92400e;
        }
        .status-returned {
            background: #d1fae5;
            color: #065f46;
        }
        .status-overdue {
            background: #fee2e2;
            color: #991b1b;
        }
        .fine-badge {
            background: #fee2e2;
            color: #991b1b;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
        }
        .action-button {
            padding: 6px 12px;
            border-radius: 8px;
            border: none;
            font-size: 12px;
            cursor: pointer;
            font-weight: 600;
            margin-right: 4px;
        }
        .return-button {
            background: #10b981;
            color: white;
        }
        .return-button:hover {
            background: #059669;
        }
        .fine-button {
            background: #f97316;
            color: white;
        }
        .fine-button:hover {
            background: #ea580c;
        }
        .fine-list {
            background: #fef2f2;
            border-radius: 14px;
            padding: 16px;
            margin-top: 16px;
        }
        .fine-item {
            padding: 12px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #ef4444;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .fine-item:last-child {
            margin-bottom: 0;
        }
        .fine-amount {
            font-weight: 700;
            color: #991b1b;
            font-size: 16px;
        }
        .fine-info {
            flex: 1;
        }
        .fine-paid {
            background: #f0fdf4 !important;
            border-left-color: #22c55e !important;
        }
        /* Tab Styles */
        .inventory-tabs {
            width: 100%;
        }
        .inventory-tab-nav {
            display: flex;
            gap: 10px;
            background: white;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .clinic-tab {
            flex: 1;
            padding: 1rem;
            text-align: center;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            color: black;
            border-radius: 8px;
        }
        .clinic-tab i {
            color: #800000;
            margin-right: 0.5rem;
        }
        .clinic-tab.active {
            background: #800000;
            color: white;
        }
        .clinic-tab.active i {
            color: #D4AF37;
        }
        .clinic-tab:hover:not(.active) {
            background: #800000;
            color: white;
        }
        .clinic-tab:hover:not(.active) i {
            color: #D4AF37;
        }
        .inventory-tab-content {
            display: none;
        }
        .inventory-tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        .inventory-tab-button i {
            margin-right: 6px;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: flex-start;
            justify-content: center;
            background: rgba(15, 23, 42, 0.72);
            padding: 40px 16px 24px;
            z-index: 1100;
            overflow-y: auto;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-overlay .modal-content {
            width: min(760px, 100%);
            max-width: 760px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 32px 80px rgba(15, 23, 42, 0.18);
            overflow: hidden;
            animation: fadeIn 0.2s ease;
        }

        .modal-overlay .modal-header,
        .modal-overlay .modal-footer {
            padding: 20px 24px;
            background: #ffffff;
        }

        .modal-overlay .modal-header {
            border-bottom: 1px solid #e2e8f0;
        }

        .modal-overlay .modal-body {
            padding: 22px 24px 24px;
            display: grid;
            gap: 18px;
            color: #334155;
        }

        .modal-overlay .modal-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-bottom: 4px;
        }

        .modal-overlay .summary-chip {
            background: #eef2ff;
            color: #1d4ed8;
            border-radius: 999px;
            padding: 8px 14px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .modal-overlay .field-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .modal-overlay .field-item {
            background: #f8fafc;
            border-radius: 18px;
            padding: 16px;
            min-height: 88px;
            display: grid;
            gap: 6px;
        }

        .modal-overlay .field-item span {
            color: #64748b;
            font-size: 0.82rem;
        }

        .modal-overlay .field-item strong {
            color: #0f172a;
            line-height: 1.4;
            font-size: 0.95rem;
        }

        .modal-overlay .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }

        @media (max-width: 720px) {
            .modal-overlay {
                align-items: center;
                padding: 24px 12px;
            }

            .modal-overlay .field-grid {
                grid-template-columns: 1fr;
            }

            .inventory-tab-nav {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                touch-action: pan-x;
                scroll-snap-type: x mandatory;
                flex-wrap: nowrap;
                padding-bottom: 6px;
            }
            .inventory-tab-nav::-webkit-scrollbar {
                height: 8px;
            }
            .inventory-tab-nav::-webkit-scrollbar-thumb {
                background: rgba(148, 163, 184, 0.45);
                border-radius: 999px;
            }
            .clinic-tab {
                flex: 0 0 auto;
                min-width: 150px;
                scroll-snap-align: center;
                white-space: nowrap;
            }

            .inventory-card {
                padding: 20px;
            }
            .inventory-card h2 {
                font-size: 18px;
                margin-bottom: 14px;
            }
            .filter-controls {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .filter-controls label {
                margin-right: 0;
                font-size: 14px;
            }
            .filter-controls select {
                width: 100%;
                padding: 10px 12px;
                border-radius: 12px;
            }
            .borrow-table {
                display: block;
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-radius: 16px;
                margin-top: 12px;
            }
            .borrow-table thead {
                display: none;
            }
            .borrow-table tbody {
                display: block;
                width: 100%;
            }
            .borrow-table tr {
                display: grid;
                gap: 10px;
                width: 100%;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 18px;
                padding: 14px;
                margin-bottom: 14px;
            }
            .borrow-table td {
                display: grid;
                width: 100%;
                padding: 10px 0;
                border: none;
                font-size: 13px;
                white-space: normal;
                line-height: 1.4;
            }
            .borrow-table td:first-child {
                padding-top: 0;
            }
            .borrow-table td::before {
                content: attr(data-label);
                display: block;
                color: #475569;
                font-weight: 700;
                margin-bottom: 6px;
                font-size: 12px;
                letter-spacing: 0.01em;
            }
            .borrow-table td:last-child {
                padding-bottom: 0;
            }
            .borrow-table td:last-child .action-button,
            .borrow-table td:last-child form {
                width: 100%;
                margin: 4px 0 0;
            }
            .borrow-table td:last-child form {
                display: grid;
                gap: 8px;
                margin-left: 0;
            }
            .borrow-table td:last-child form button {
                width: 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css">
    <script>
        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            const libraryFineData = <?= json_encode($userFineData ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

            const setFormStateInputs = () => {
                const activeTab = document.querySelector('.clinic-tab.active')?.getAttribute('onclick')?.match(/'([^']+)'/)?.[1] || 'borrowed';
                document.querySelectorAll('form').forEach(form => {
                    let input = form.querySelector('input[name="active_tab"]');
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'active_tab';
                        form.appendChild(input);
                    }
                    input.value = activeTab;
                });
            };

            const attachInventoryTabSwipe = () => {
                const tabNav = document.querySelector('.inventory-tab-nav.clinic-tabs');
                if (!tabNav) return;

                let startX = 0;
                let startScroll = 0;
                let isDragging = false;

                tabNav.addEventListener('touchstart', function (event) {
                    if (event.touches.length !== 1) return;
                    startX = event.touches[0].clientX;
                    startScroll = tabNav.scrollLeft;
                    isDragging = true;
                }, { passive: true });

                tabNav.addEventListener('touchmove', function (event) {
                    if (!isDragging || event.touches.length !== 1) return;
                    const currentX = event.touches[0].clientX;
                    tabNav.scrollLeft = startScroll + (startX - currentX);
                }, { passive: true });

                const stopDrag = () => {
                    isDragging = false;
                };

                tabNav.addEventListener('touchend', stopDrag, { passive: true });
                tabNav.addEventListener('touchcancel', stopDrag, { passive: true });
            };

            const escapeHtml = (text) => {
                return String(text)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            };

            const fineModalOverlay = document.getElementById('fine-modal-overlay');
            const fineModal = fineModalOverlay?.querySelector('.modal-content');
            const fineModalTitle = document.getElementById('fine-modal-title');
            const fineModalBody = document.getElementById('fine-modal-body');
            const fineModalSummary = document.getElementById('fine-modal-summary');

            const openFineModal = (userId) => {
                if (!fineModalOverlay || !fineModal || !fineModalBody || !fineModalTitle || !fineModalSummary) {
                    return;
                }

                const userData = libraryFineData[userId];
                if (!userData) {
                    return;
                }

                fineModalTitle.textContent = `${escapeHtml(userData.user.full_name)} — Outstanding Fines`;
                fineModalSummary.innerHTML = `
                    <div class="summary-chip">Role: ${escapeHtml(userData.user.role)}</div>
                    <div class="summary-chip">Course: ${escapeHtml(userData.user.course_year || 'N/A')}</div>
                    <div class="summary-chip">Total Due: ₱${Number(userData.user.total_fines).toFixed(2)}</div>
                `;

                const fineRows = userData.fines.map(fine => {
                    return `
                        <tr>
                            <td>${escapeHtml(fine.book_title)}</td>
                            <td>${escapeHtml(fine.description || 'Library fine')}</td>
                            <td>${new Date(fine.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}</td>
                            <td>₱${Number(fine.amount).toFixed(2)}</td>
                            <td>
                                <form method="post" style="margin:0;">
                                    <input type="hidden" name="action" value="mark_fine_paid">
                                    <input type="hidden" name="user_id" value="${escapeHtml(userData.user.id)}">
                                    <input type="hidden" name="fine_id" value="${escapeHtml(fine.fine_id)}">
                                    <input type="hidden" name="active_tab" value="${escapeHtml(document.querySelector('.clinic-tab.active')?.getAttribute('onclick')?.match(/'([^']+)'/)?.[1] || 'borrowed')}">
                                    <button type="submit" class="action-button fine-button">Mark Paid</button>
                                </form>
                            </td>
                        </tr>
                    `;
                }).join('');

                fineModalBody.innerHTML = `
                    <p>Review all unpaid fine entries for this user. Click a fine to record payment and refresh the list.</p>
                    <table class="modal-table">
                        <thead>
                            <tr>
                                <th>Book</th>
                                <th>Description</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${fineRows}
                        </tbody>
                    </table>
                    <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: center;">
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="send_fines_email">
                            <input type="hidden" name="user_id" value="${userId}">
                            <button type="submit" class="action-button" style="background: #3498db; color: white; padding: 10px 20px;">
                                <i class="fa-solid fa-envelope"></i> Send Notification Email
                            </button>
                        </form>
                    </div>
                `;

                fineModalOverlay.classList.add('active');
                fineModalOverlay.setAttribute('aria-hidden', 'false');
            };

            const closeFineModal = () => {
                fineModalOverlay.classList.remove('active');
                fineModal?.classList.remove('show');
                fineModalOverlay.setAttribute('aria-hidden', 'true');
                fineModalBody.innerHTML = '';
            };

            const modalCloseButton = document.getElementById('fine-modal-close');
            const modalCancelButton = document.getElementById('fine-modal-cancel');

            modalCloseButton?.addEventListener('click', closeFineModal);
            modalCancelButton?.addEventListener('click', closeFineModal);
            fineModalOverlay?.addEventListener('click', (event) => {
                if (event.target === fineModalOverlay) {
                    closeFineModal();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeFineModal();
                }
            });

            // Tab switching function
            window.switchTab = function(tabId, event) {
                // Hide all tab contents
                const contents = document.querySelectorAll('.inventory-tab-content');
                contents.forEach(content => content.classList.remove('active'));

                // Remove active class from all tabs
                const tabs = document.querySelectorAll('.clinic-tab');
                tabs.forEach(tab => tab.classList.remove('active'));

                // Show selected tab content
                document.getElementById('tab-' + tabId).classList.add('active');

                // Add active class to clicked tab
                if (event && event.currentTarget) {
                    event.currentTarget.classList.add('active');
                } else if (event && event.target) {
                    event.target.classList.add('active');
                } else {
                    // Fallback: find the tab button by tabId
                    const activeTab = document.querySelector(`.clinic-tab[onclick*="switchTab('${tabId}'"]`);
                    if (activeTab) {
                        activeTab.classList.add('active');
                    }
                }

                const activeTabButton = document.querySelector('.clinic-tab.active');
                activeTabButton?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });

                // Update form state
                setFormStateInputs(tabId);
            };

            attachInventoryTabSwipe();

            document.body.addEventListener('click', function(event) {
                const manageButton = event.target.closest('.manage-fines-button');
                if (!manageButton) {
                    return;
                }
                event.preventDefault();
                openFineModal(manageButton.getAttribute('data-user-id'));
            });

            setFormStateInputs();

            const bookSearchInput = document.getElementById('book-search-input');
            const bookSearchResults = document.getElementById('book-search-results');
            const bookSearchPrompt = document.getElementById('book-search-prompt');
            const bookIdInput = document.getElementById('book-id-input');
            const copyNumberInput = document.getElementById('copy-number-input');
            const selectedBookSummary = document.getElementById('selected-book-summary');
            const copySelection = document.getElementById('copy-selection');
            const copyNumberSelect = document.getElementById('copy-number-select');
            const copyTypeLabel = document.getElementById('copy-type-label');

            if (bookSearchInput && bookSearchResults && bookIdInput) {
                const bookItems = Array.from(bookSearchResults.querySelectorAll('.book-result-item'));

                const clearSelection = () => {
                    bookItems.forEach(item => item.classList.remove('active'));
                    bookIdInput.value = '';
                    copyNumberInput.value = '';
                    selectedBookSummary.style.display = 'none';
                    selectedBookSummary.textContent = '';
                    copySelection.style.display = 'none';
                    copyNumberSelect.innerHTML = '<option value="">Choose...</option>';
                };

                const selectBook = (item) => {
                    bookItems.forEach(button => button.classList.toggle('active', button === item));
                    bookIdInput.value = item.dataset.bookId || '';
                    
                    const additionalType = item.dataset.additionalType;
                    const additionalQuantity = parseInt(item.dataset.additionalQuantity) || 0;
                    
                    if (additionalType && additionalQuantity > 0 && ['volume', 'part', 'number'].includes(additionalType)) {
                        // Show dropdown for books with additional info
                        copyTypeLabel.textContent = additionalType.charAt(0).toUpperCase() + additionalType.slice(1);
                        copyNumberSelect.innerHTML = '<option value="">Choose...</option>';
                        
                        for (let i = 1; i <= additionalQuantity; i++) {
                            const option = document.createElement('option');
                            option.value = i;
                            option.textContent = `${additionalType.charAt(0).toUpperCase() + additionalType.slice(1)} ${i}`;
                            copyNumberSelect.appendChild(option);
                        }
                        
                        copySelection.style.display = 'block';
                        copyNumberInput.value = '';
                        selectedBookSummary.style.display = 'block';
                        selectedBookSummary.innerHTML = `Selected book: <strong>${item.querySelector('strong').textContent}</strong> — ${item.querySelector('span').textContent}<br><em>Please select a ${additionalType} below.</em>`;
                    } else {
                        // Regular book selection
                        copyNumberInput.value = '';
                        copySelection.style.display = 'none';
                        selectedBookSummary.style.display = 'block';
                        selectedBookSummary.innerHTML = `Selected book: <strong>${item.querySelector('strong').textContent}</strong> — ${item.querySelector('span').textContent}`;
                    }
                };

                const updateBookResults = () => {
                    const searchValue = bookSearchInput.value.trim().toLowerCase();
                    let anyVisible = false;
                    bookItems.forEach(item => {
                        const title = item.dataset.bookTitle || item.textContent.toLowerCase();
                        const visible = searchValue !== '' && title.includes(searchValue);
                        item.hidden = !visible;
                        if (visible) {
                            anyVisible = true;
                        }
                    });

                    bookSearchResults.querySelectorAll('.no-results-message').forEach(el => el.remove());
                    if (searchValue === '') {
                        if (bookSearchPrompt) {
                            bookSearchPrompt.style.display = 'block';
                        }
                    } else {
                        if (bookSearchPrompt) {
                            bookSearchPrompt.style.display = 'none';
                        }
                        if (!anyVisible) {
                            const noResults = document.createElement('p');
                            noResults.className = 'no-results-message';
                            noResults.textContent = 'No matching available books found.';
                            bookSearchResults.appendChild(noResults);
                        }
                    }
                };

                bookItems.forEach(item => item.hidden = true);
                if (bookSearchPrompt) {
                    bookSearchPrompt.style.display = 'block';
                }

                bookSearchResults.addEventListener('click', function (event) {
                    const item = event.target.closest('.book-result-item');
                    if (!item || item.hidden) {
                        return;
                    }
                    selectBook(item);
                });

                bookSearchInput.addEventListener('input', function () {
                    updateBookResults();
                });

                // Handle copy number selection
                copyNumberSelect.addEventListener('change', function () {
                    const selectedValue = this.value;
                    copyNumberInput.value = selectedValue;
                    
                    if (selectedValue) {
                        const selectedBook = bookItems.find(item => item.classList.contains('active'));
                        if (selectedBook) {
                            const additionalType = selectedBook.dataset.additionalType;
                            const typeLabel = additionalType.charAt(0).toUpperCase() + additionalType.slice(1);
                            selectedBookSummary.innerHTML = `Selected book: <strong>${selectedBook.querySelector('strong').textContent}</strong> — ${selectedBook.querySelector('span').textContent}<br><em>Selected: ${typeLabel} ${selectedValue}</em>`;
                        }
                    } else {
                        const selectedBook = bookItems.find(item => item.classList.contains('active'));
                        if (selectedBook) {
                            const additionalType = selectedBook.dataset.additionalType;
                            selectedBookSummary.innerHTML = `Selected book: <strong>${selectedBook.querySelector('strong').textContent}</strong> — ${selectedBook.querySelector('span').textContent}<br><em>Please select a ${additionalType} below.</em>`;
                        }
                    }
                });

                // Handle form submission validation
                const borrowForm = document.getElementById('borrow-form');
                if (borrowForm) {
                    borrowForm.addEventListener('submit', function (e) {
                        const selectedBook = bookItems.find(item => item.classList.contains('active'));
                        if (!selectedBook) {
                            e.preventDefault();
                            alert('Please select a book first.');
                            return;
                        }
                        
                        const additionalType = selectedBook.dataset.additionalType;
                        const additionalQuantity = parseInt(selectedBook.dataset.additionalQuantity) || 0;
                        
                        if (additionalType && additionalQuantity > 0 && ['volume', 'part', 'number'].includes(additionalType)) {
                            if (!copyNumberInput.value) {
                                e.preventDefault();
                                alert(`Please select a ${additionalType} for this book.`);
                                return;
                            }
                        }
                    });
                }
            }

            const bookInfoModalOverlay = document.getElementById('book-info-modal-overlay');
            const bookInfoModal = bookInfoModalOverlay?.querySelector('.modal-content');
            const bookInfoModalTitle = document.getElementById('book-info-modal-title');
            const bookInfoModalBody = document.getElementById('book-info-modal-body');
            const bookInfoModalSummary = document.getElementById('book-info-modal-summary');
            const bookInfoModalClose = document.getElementById('book-info-modal-close');
            const bookInfoModalCancel = document.getElementById('book-info-modal-cancel');

            const closeBookInfoModal = () => {
                if (!bookInfoModalOverlay) return;
                bookInfoModalOverlay.classList.remove('active');
                bookInfoModal?.classList.remove('show');
                bookInfoModalOverlay.setAttribute('aria-hidden', 'true');
                bookInfoModalBody.innerHTML = '';
            };

            const openBookInfoModal = (button) => {
                if (!bookInfoModalOverlay || !bookInfoModalBody || !bookInfoModalTitle || !bookInfoModalSummary) {
                    return;
                }

                const title = button.dataset.bookTitle || 'N/A';
                const author = button.dataset.author || 'N/A';
                const lccLetterLine = button.dataset.lccLetterLine || 'N/A';
                const lccClassNumber = button.dataset.lccClassNumber || 'N/A';
                const lccCutterCode = button.dataset.lccCutterCode || 'N/A';
                const lccPublishedYear = button.dataset.lccPublishedYear || 'N/A';
                const additionalInfoType = button.dataset.additionalInfoType || '';
                const additionalInfoQuantity = button.dataset.additionalInfoQuantity || '';
                const reservationInfo = button.dataset.additionalInfo || '';
                const copyNumber = button.dataset.copyNumber || '';
                const borrowerName = button.dataset.borrowerName || 'N/A';

                const hasCopySelection = copyNumber && copyNumber !== '0';
                const selectedInfo = hasCopySelection && additionalInfoType
                    ? `${additionalInfoType.charAt(0).toUpperCase() + additionalInfoType.slice(1)} ${copyNumber}`
                    : '';
                const additionalInfoLabel = 'Additional Information';
                const additionalInfoValue = reservationInfo || selectedInfo || '';

                bookInfoModalTitle.textContent = `${title} — Book Details`;
                const rowElement = button.closest('tr');
                const statusBadge = rowElement ? rowElement.querySelector('.status-badge') : null;
                const statusText = statusBadge ? statusBadge.textContent : '';

                const summaryChips = [`
                    <div class="summary-chip">Borrower: ${escapeHtml(borrowerName)}</div>
                `];
                if (statusText) {
                    summaryChips.push(`
                        <div class="summary-chip">Status: ${escapeHtml(statusText)}</div>
                    `);
                }
                bookInfoModalSummary.innerHTML = summaryChips.join('');

                const details = [
                    `<div><strong>Title:</strong> ${escapeHtml(title)}</div>`,
                    `<div><strong>Author:</strong> ${escapeHtml(author)}</div>`,
                    `<div><strong>Library of Congress Classification (LCC):</strong> ${escapeHtml(lccLetterLine)}</div>`,
                    `<div><strong>Class Number:</strong> ${escapeHtml(lccClassNumber)}</div>`,
                    `<div><strong>Cutter Code:</strong> ${escapeHtml(lccCutterCode)}</div>`,
                    `<div><strong>Year:</strong> ${escapeHtml(lccPublishedYear)}</div>`
                ];
                if (additionalInfoValue) {
                    details.push(`<div><strong>${escapeHtml(additionalInfoLabel)}:</strong> ${escapeHtml(additionalInfoValue)}</div>`);
                }

                bookInfoModalBody.innerHTML = `
                    <div style="display: grid; gap: 12px;">
                        ${details.join('')}
                    </div>
                `;

                bookInfoModalOverlay.classList.add('active');
                bookInfoModalOverlay.setAttribute('aria-hidden', 'false');
            };

            bookInfoModalClose?.addEventListener('click', closeBookInfoModal);
            bookInfoModalCancel?.addEventListener('click', closeBookInfoModal);
            bookInfoModalOverlay?.addEventListener('click', (event) => {
                if (event.target === bookInfoModalOverlay) {
                    closeBookInfoModal();
                }
            });

            document.body.addEventListener('click', function(event) {
                const bookButton = event.target.closest('.view-book-info-button');
                if (!bookButton) {
                    return;
                }
                event.preventDefault();
                openBookInfoModal(bookButton);
            });

            // Status filter functionality
            const statusFilter = document.getElementById('status-filter');
            if (statusFilter) {
                statusFilter.addEventListener('change', function() {
                    const filterValue = this.value;
                    const rows = document.querySelectorAll('#tab-borrowed tbody tr');
                    
                    rows.forEach(row => {
                        const status = row.getAttribute('data-status');
                        if (filterValue === 'all' || status === filterValue) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }
        });
    </script>
    <script>
        window.emailJsConfig = {
            serviceId: 'service_41woiyr',
            templateId: 'template_qfr20uh',
            publicKey: 'M8U5HqlpqCLWLl9OS'
        };
        window.libraryFinesEmailData = <?= isset($emailData) ? json_encode($emailData) : 'null' ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (!window.libraryFinesEmailData || !window.libraryFinesEmailData.send) {
                return;
            }

            if (typeof emailjs === 'undefined') {
                console.error('EmailJS SDK not loaded.');
                return;
            }

            emailjs.init(window.emailJsConfig.publicKey);

            const emailData = window.libraryFinesEmailData;
            
            setTimeout(function() {
                emailjs.send(window.emailJsConfig.serviceId, window.emailJsConfig.templateId, {
                    user_name: emailData.user_name,
                    email: emailData.email,
                    total_fines: emailData.total_fines,
                    overdue_books: emailData.overdue_books,
                    portal_link: emailData.portal_link,
                    request_id: emailData.request_id
                }).then(function(response) {
                    console.log('Library fines email sent successfully', response);
                }, function(error) {
                    console.error('Library fines email sending failed:', error);
                });
            }, 500);
        });
    </script>
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
                    <p>Library Head</p>
                </div>
            </div>
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
                <a href="dashboard/school_announcements.php?service=library" data-tooltip="Announcements">
                    <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                    <span class="nav-text">Announcements</span>
                </a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $serviceOpen ? 'true' : 'false' ?>" data-tooltip="Services">
                        <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                        <span class="nav-text">Services</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu<?= $serviceOpen ? ' open' : '' ?>" aria-hidden="<?= $serviceOpen ? 'false' : 'true' ?>">
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
                        <a href="library_catalog.php" data-tooltip="Digital Catalog">
                            <span class="nav-icon"><i class="fa-solid fa-book-open"></i></span>
                            <span class="nav-text">Digital Catalog</span>
                        </a>
                        <a class="active" href="library_inventory.php" data-tooltip="Inventory">
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
        <div id="pageOverlay" class="page-overlay"></div>
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
            <div class="main-scroll">
                <section class="dashboard-intro library-page-header">
                        <div>
                            <span class="eyebrow">INVENTORY MANAGEMENT</span>
                            <h1>Manage Physical Borrows &amp; Fines</h1>
                            <p class="dashboard-subtitle">Search students, record borrows, mark returns, and manage fines in one place.</p>
                        </div>
                    <div class="library-header__art" aria-hidden="true"><i class="fa-solid fa-layer-group book-stack"></i><i class="fa-solid fa-book-open open-book"></i><i class="fa-solid fa-id-card library-card"></i><i class="fa-solid fa-circle-check library-check"></i><i class="fa-solid fa-location-pin library-pin"></i><i class="fa-solid fa-leaf library-leaf"></i></div>
                    </section>
                    
                    <!-- Overview Statistics Section -->
                    <section class="library-status-grid">
                        <?php 
                        // Get statistics for the overview
                        $pdo = get_db();
                        
                        // Count active borrows
                        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM library_borrows WHERE status IN ("Borrowed", "Overdue")');
                        $stmt->execute();
                        $activeBorrowCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        
                        // Count users with outstanding fines (exclude teachers)
                        $stmt = $pdo->prepare('SELECT COUNT(DISTINCT f.user_id) as count FROM library_fines f JOIN users u ON f.user_id = u.id WHERE f.paid = 0 AND u.role != "teacher"');
                        $stmt->execute();
                        $usersWithFinesCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        
                        // Count total books borrowed (all time)
                        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM library_borrows');
                        $stmt->execute();
                        $totalBorrowsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        
                        // Count active reservations
                        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM library_reservations WHERE status = "Active"');
                        $stmt->execute();
                        $activeReservationsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        
                        // Count total available books
                        $stmt = $pdo->prepare('SELECT SUM(available_copies) as total FROM library_books');
                        $stmt->execute();
                        $totalBooksAvailable = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                        
                        // Get total unpaid fines amount (exclude teachers)
                        $stmt = $pdo->prepare('SELECT SUM(f.amount) as total FROM library_fines f JOIN users u ON f.user_id = u.id WHERE f.paid = 0 AND u.role != "teacher"');
                        $stmt->execute();
                        $totalUnpaidFines = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
                        ?>
                        <div class="library-card status-card">
                            <span class="status-label">Active Borrows</span>
                            <strong style="font-size: 28px; color: #2563eb;"><?= htmlspecialchars($activeBorrowCount) ?></strong>
                            <p>Books currently borrowed by students and teachers.</p>
                        </div>
                        <div class="library-card status-card">
                            <span class="status-label">Users with Fines</span>
                            <strong style="font-size: 28px; color: #f59e0b;"><?= htmlspecialchars($usersWithFinesCount) ?></strong>
                            <p>Users with outstanding unpaid fines.</p>
                        </div>
                        <div class="library-card status-card">
                            <span class="status-label">Pending Reservations</span>
                            <strong style="font-size: 28px; color: #8b5cf6;"><?= htmlspecialchars($activeReservationsCount) ?></strong>
                            <p>Active reservations awaiting pickup.</p>
                        </div>
                        <div class="library-card status-card">
                            <span class="status-label">Available Books</span>
                            <strong style="font-size: 28px; color: #10b981;"><?= htmlspecialchars($totalBooksAvailable) ?></strong>
                            <p>Total copies in stock ready for borrowing.</p>
                        </div>
                        <div class="library-card status-card">
                            <span class="status-label">Total Borrows (All Time)</span>
                            <strong style="font-size: 28px; color: #6366f1;"><?= htmlspecialchars($totalBorrowsCount) ?></strong>
                            <p>Complete count of all borrow transactions.</p>
                        </div>
                        <div class="library-card status-card">
                            <span class="status-label">Unpaid Fines</span>
                            <strong style="font-size: 28px; color: #ef4444;">₱<?= number_format($totalUnpaidFines, 2) ?></strong>
                            <p>Total amount of outstanding fines to collect.</p>
                        </div>
                    </section>
                    
                    <?php if (!empty($message)): ?>
                        <section class="dashboard-section">
                            <div class="dashboard-summary-card <?= $messageType === 'error' ? 'alert error' : 'alert success' ?>">
                                <?= htmlspecialchars($message) ?>
                            </div>
                        </section>
                    <?php endif; ?>

                    <!-- Tabbed Inventory Management System -->
                    <section class="dashboard-section">
                        <div class="inventory-tabs">
                            <!-- Tab Navigation -->
                            <div class="inventory-tab-nav clinic-tabs">
                                <button type="button" class="clinic-tab <?= $activeTab === 'borrowed' ? 'active' : '' ?>" onclick="switchTab('borrowed', event)">
                                    <i class="fa-solid fa-book"></i> Borrowed Books
                                </button>
                                <button type="button" class="clinic-tab <?= $activeTab === 'reservations' ? 'active' : '' ?>" onclick="switchTab('reservations', event)">
                                    <i class="fa-solid fa-bookmark"></i> Reservation Queue
                                </button>
                                <button type="button" class="clinic-tab <?= $activeTab === 'fines' ? 'active' : '' ?>" onclick="switchTab('fines', event)">
                                    <i class="fa-solid fa-receipt"></i> Outstanding Fines
                                </button>
                                <button type="button" class="clinic-tab <?= $activeTab === 'history' ? 'active' : '' ?>" onclick="switchTab('history', event)">
                                    <i class="fa-solid fa-history"></i> Borrow History
                                </button>
                                <button type="button" class="clinic-tab <?= $activeTab === 'search' ? 'active' : '' ?>" onclick="switchTab('search', event)">
                                    <i class="fa-solid fa-magnifying-glass"></i> Search & Manage
                                </button>
                            </div>

                            <!-- Tab 1: All Borrowed Books -->
                            <div class="inventory-tab-content <?= $activeTab === 'borrowed' ? 'active' : '' ?>" id="tab-borrowed">
                                <div class="inventory-card">
                                    <h2>All Borrowed Books</h2>
                                    <p style="color: #64748b; font-size: 14px; margin-bottom: 16px;">Track all active and overdue books currently borrowed by students and teachers.</p>
                                    <div class="filter-controls" style="margin-bottom: 16px;">
                                        <label for="status-filter" style="margin-right: 8px; font-weight: 500;">Filter by Status:</label>
                                        <select id="status-filter" style="padding: 6px 12px; border: 1px solid #d1d5db; border-radius: 4px;">
                                            <option value="all">All</option>
                                            <option value="borrowed">Borrowed</option>
                                            <option value="overdue">Overdue</option>
                                        </select>
                                    </div>
                                    <?php 
                                    $pdo = get_db();
                                    $stmt = $pdo->prepare('
                                        SELECT lb.id, lbk.title, lbk.author, lb.due_date, CASE WHEN u.role = "teacher" AND lb.status = "Overdue" THEN "Borrowed" ELSE lb.status END AS borrow_status, u.full_name, u.course_year, u.role,
                                               lbk.lcc_letter_line, lbk.lcc_class_number, lbk.lcc_cutter_code, lbk.lcc_published_year, lbk.additional_info_type, lbk.additional_info_quantity, lb.copy_number
                                        FROM library_borrows lb
                                        JOIN library_books lbk ON lb.book_id = lbk.id
                                        JOIN users u ON lb.user_id = u.id
                                        WHERE lb.status IN ("Borrowed", "Overdue")
                                        ORDER BY 
                                            CASE WHEN lb.status = "Overdue" THEN 0 ELSE 1 END,
                                            lb.due_date ASC
                                    ');
                                    $stmt->execute();
                                    $allBorrows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    <?php if (!empty($allBorrows)): ?>
                                        <table class="borrow-table">
                                            <thead>
                                                <tr>
                                                    <th>Student/Teacher</th>
                                                    <th>Book Title</th>
                                                    <th>Due Date</th>
                                                    <th>Status</th>
                                                    <th>Course</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($allBorrows as $borrow): ?>
                                                    <tr data-status="<?= strtolower($borrow['borrow_status']) ?>">
                                                        <td data-label="Student/Teacher"><strong><?= htmlspecialchars($borrow['full_name']) ?></strong></td>
                                                        <td data-label="Book Title"><?= htmlspecialchars($borrow['title']) ?></td>
                                                        <td data-label="Due Date"><?= (new DateTime($borrow['due_date']))->format('M d, Y') ?></td>
                                                        <td data-label="Status">
                                                            <span class="status-badge status-<?= strtolower($borrow['borrow_status']) ?>">
                                                                <?= htmlspecialchars($borrow['borrow_status']) ?>
                                                            </span>
                                                        </td>
                                                        <td data-label="Course"><?= htmlspecialchars($borrow['course_year'] ?? 'N/A') ?></td>
                                                        <td data-label="Action">
                                                            <button type="button" class="action-button view-book-info-button" data-book-title="<?= htmlspecialchars($borrow['title']) ?>" data-author="<?= htmlspecialchars($borrow['author']) ?>" data-lcc-letter-line="<?= htmlspecialchars($borrow['lcc_letter_line'] ?? '') ?>" data-lcc-class-number="<?= htmlspecialchars($borrow['lcc_class_number'] ?? '') ?>" data-lcc-cutter-code="<?= htmlspecialchars($borrow['lcc_cutter_code'] ?? '') ?>" data-lcc-published-year="<?= htmlspecialchars($borrow['lcc_published_year'] ?? '') ?>" data-additional-info-type="<?= htmlspecialchars($borrow['additional_info_type'] ?? '') ?>" data-additional-info-quantity="<?= htmlspecialchars($borrow['additional_info_quantity'] ?? 0) ?>" data-additional-info="<?= htmlspecialchars(!empty($borrow['additional_info_type']) && !empty($borrow['copy_number']) ? ucfirst($borrow['additional_info_type']) . ' ' . $borrow['copy_number'] : '') ?>" data-copy-number="<?= htmlspecialchars($borrow['copy_number'] ?? 0) ?>" data-borrower-name="<?= htmlspecialchars($borrow['full_name']) ?>">View Info</button>
                                                            <form method="post" style="display: inline; margin-left: 8px;">
                                                                <input type="hidden" name="action" value="record_return">
                                                                <input type="hidden" name="borrow_id" value="<?= htmlspecialchars($borrow['id']) ?>">
                                                                <button type="submit" class="action-button return-button">Mark Return</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <p style="color: #64748b; text-align: center; padding: 40px;">No active or overdue borrows at this time.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Tab 2: Reservation Queue -->
                            <div class="inventory-tab-content <?= $activeTab === 'reservations' ? 'active' : '' ?>" id="tab-reservations">
                                <div class="inventory-card">
                                    <h2>Reservation Queue</h2>
                                    <p style="color: #64748b; font-size: 14px; margin-bottom: 16px;">Manage student and teacher reservations. Click "Picked Up" when they collect their reserved book, or "Cancel" to remove the reservation.</p>
                                    <?php 
                                    $pdo = get_db();
                                    $stmt = $pdo->prepare('
                                        SELECT lr.id, lr.user_id, lr.book_id, lr.status, lr.reserved_date, lr.pickup_date, lr.additional_info,
                                               lb.title, lb.author, lb.lcc_letter_line, lb.lcc_class_number, lb.lcc_cutter_code, lb.lcc_published_year, lb.additional_info_type, lb.additional_info_quantity,
                                               u.full_name, u.course_year
                                        FROM library_reservations lr
                                        JOIN library_books lb ON lr.book_id = lb.id
                                        JOIN users u ON lr.user_id = u.id
                                        WHERE lr.status = "Active"
                                        ORDER BY lr.queue_position ASC
                                    ');
                                    $stmt->execute();
                                    $pendingReservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    <?php if (!empty($pendingReservations)): ?>
                                        <table class="borrow-table">
                                            <thead>
                                                <tr>
                                                    <th>Reserved By</th>
                                                    <th>Book Title</th>
                                                    <th>Pickup Date</th>
                                                    <th>Course</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pendingReservations as $res): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($res['full_name']) ?></strong></td>
                                                        <td><?= htmlspecialchars($res['title']) ?></td>
                                                        <td><?= (new DateTime($res['pickup_date']))->format('M d, Y') ?></td>
                                                        <td><?= htmlspecialchars($res['course_year'] ?? 'N/A') ?></td>
                                                        <td style="display: flex; gap: 8px; align-items: center;">
                                                            <button type="button" class="action-button view-book-info-button" data-book-title="<?= htmlspecialchars($res['title']) ?>" data-author="<?= htmlspecialchars($res['author']) ?>" data-lcc-letter-line="<?= htmlspecialchars($res['lcc_letter_line'] ?? '') ?>" data-lcc-class-number="<?= htmlspecialchars($res['lcc_class_number'] ?? '') ?>" data-lcc-cutter-code="<?= htmlspecialchars($res['lcc_cutter_code'] ?? '') ?>" data-lcc-published-year="<?= htmlspecialchars($res['lcc_published_year'] ?? '') ?>" data-additional-info-type="<?= htmlspecialchars($res['additional_info_type'] ?? '') ?>" data-additional-info-quantity="<?= htmlspecialchars($res['additional_info_quantity'] ?? 0) ?>" data-additional-info="<?= htmlspecialchars($res['additional_info'] ?? '') ?>" data-copy-number="" data-borrower-name="<?= htmlspecialchars($res['full_name']) ?>">View Info</button>
                                                            <form method="post" style="display: inline;">
                                                                <input type="hidden" name="action" value="mark_reservation_picked_up">
                                                                <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($res['id']) ?>">
                                                                <input type="hidden" name="user_id" value="<?= htmlspecialchars($res['user_id']) ?>">
                                                                <input type="hidden" name="book_id" value="<?= htmlspecialchars($res['book_id']) ?>">
                                                                <button type="submit" class="action-button" style="background: #10b981; color: white; padding: 6px 12px; font-size: 12px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600;">✓ Picked Up</button>
                                                            </form>
                                                            <form method="post" style="display: inline;">
                                                                <input type="hidden" name="action" value="cancel_reservation_admin">
                                                                <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($res['id']) ?>">
                                                                <button type="submit" class="action-button" style="background: #ef4444; color: white; padding: 6px 12px; font-size: 12px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600;">✕ Cancel</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <p style="color: #64748b; text-align: center; padding: 40px;">No active reservations. All students are caught up!</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Tab 3: Outstanding Fines -->
                            <div class="inventory-tab-content <?= $activeTab === 'fines' ? 'active' : '' ?>" id="tab-fines">
                                <div class="inventory-card">
                                    <h2>Outstanding Fines</h2>
                                    <p style="color: #64748b; font-size: 14px; margin-bottom: 16px;">View all students with unpaid fines. Click "Mark Paid" to settle every unpaid fine for that student.</p>
                                    <?php 
                                    $usersWithFines = array_values(array_map(function($entry) {
                                        return $entry['user'];
                                    }, $userFineData));
                                    usort($usersWithFines, function($a, $b) {
                                        return $b['total_fines'] <=> $a['total_fines'];
                                    });
                                    ?>
                                    <?php if (!empty($usersWithFines)): ?>
                                        <table class="borrow-table">
                                            <thead>
                                                <tr>
                                                    <th>Student/Teacher</th>
                                                    <th>Role</th>
                                                    <th>Course</th>
                                                    <th>Total Unpaid Fines</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($usersWithFines as $userFine): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($userFine['full_name']) ?></strong></td>
                                                        <td><?= htmlspecialchars($userFine['role']) ?></td>
                                                        <td><?= htmlspecialchars($userFine['course_year'] ?? 'N/A') ?></td>
                                                        <td><span class="fine-badge">₱<?= number_format($userFine['total_fines'], 2) ?></span></td>
                                                        <td>
                                                            <form method="post" style="display: inline; margin: 0; margin-right: 6px;">
                                                                <input type="hidden" name="action" value="send_fines_email">
                                                                <input type="hidden" name="user_id" value="<?= htmlspecialchars($userFine['id']) ?>">
                                                                <button type="submit" class="action-button" style="background: #3498db; color: white; padding: 6px 12px; font-size: 12px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600;">
                                                                    <i class="fa-solid fa-envelope"></i> Send Email
                                                                </button>
                                                            </form>
                                                            <form method="post" style="display: inline; margin: 0;">
                                                                <input type="hidden" name="action" value="mark_all_fines_paid">
                                                                <input type="hidden" name="user_id" value="<?= htmlspecialchars($userFine['id']) ?>">
                                                                <button type="submit" class="action-button" style="background: #2563eb; color: white; padding: 6px 12px; font-size: 12px; border-radius: 8px; border: none; cursor: pointer; font-weight: 600;">Mark Paid</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <p style="color: #64748b; text-align: center; padding: 40px;">No outstanding fines. Everyone has paid!</p>
                                    <?php endif; ?>
                                </div>
                                <div class="inventory-card" style="margin-top: 24px;">
                                    <h3>Outstanding Fines History</h3>
                                    <p style="color: #64748b; font-size: 14px; margin-bottom: 16px;">Recent fine records for students, including paid and unpaid entries.</p>
                                    <?php
                                    $historyStmt = $pdo->prepare('SELECT f.id, f.user_id, f.amount, f.paid, f.description, f.created_at, u.full_name, u.course_year, lb.title AS book_title FROM library_fines f JOIN library_borrows b ON f.borrow_id = b.id JOIN library_books lb ON b.book_id = lb.id JOIN users u ON f.user_id = u.id WHERE u.role != "teacher" ORDER BY f.created_at DESC LIMIT 30');
                                    $historyStmt->execute();
                                    $fineHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    <?php if (!empty($fineHistory)): ?>
                                        <table class="borrow-table">
                                            <thead>
                                                <tr>
                                                    <th>Student</th>
                                                    <th>Book</th>
                                                    <th>Description</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($fineHistory as $historyRow): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($historyRow['full_name']) ?></strong></td>
                                                        <td><?= htmlspecialchars($historyRow['book_title']) ?></td>
                                                        <td><?= htmlspecialchars($historyRow['description'] ?: 'Library fine') ?></td>
                                                        <td>₱<?= number_format($historyRow['amount'], 2) ?></td>
                                                        <td>
                                                            <span class="status-badge <?= $historyRow['paid'] ? 'status-returned' : 'status-overdue' ?>">
                                                                <?= $historyRow['paid'] ? 'Paid' : 'Unpaid' ?>
                                                            </span>
                                                        </td>
                                                        <td><?= (new DateTime($historyRow['created_at']))->format('M d, Y') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <p style="color: #64748b; text-align: center; padding: 24px;">No fine history is available yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Tab 4: Borrow History -->
                            <div class="inventory-tab-content <?= $activeTab === 'history' ? 'active' : '' ?>" id="tab-history">
                                <div class="inventory-card">
                                    <h2>Borrow History</h2>
                                    <p style="color: #64748b; font-size: 14px; margin-bottom: 16px;">Complete record of all books borrowed and returned by students and teachers.</p>
                                    <?php 
                                    $pdo = get_db();
                                    $stmt = $pdo->prepare('
                                        SELECT lb.id, lb.user_id, lb.book_id, lb.status, lb.borrow_date, lb.due_date, lb.returned_at,
                                               lbk.title, u.full_name, u.course_year, u.role
                                        FROM library_borrows lb
                                        JOIN library_books lbk ON lb.book_id = lbk.id
                                        JOIN users u ON lb.user_id = u.id
                                        ORDER BY lb.borrow_date DESC
                                        LIMIT 200
                                    ');
                                    $stmt->execute();
                                    $borrowHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    <?php if (!empty($borrowHistory)): ?>
                                        <table class="borrow-table">
                                            <thead>
                                                <tr>
                                                    <th>Student/Teacher</th>
                                                    <th>Book Title</th>
                                                    <th>Borrow Date</th>
                                                    <th>Due Date</th>
                                                    <th>Return Date</th>
                                                    <th>Status</th>
                                                    <th>Course</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($borrowHistory as $history): ?>
                                                    <tr>
                                                        <td><strong><?= htmlspecialchars($history['full_name']) ?></strong></td>
                                                        <td><?= htmlspecialchars($history['title']) ?></td>
                                                        <td><?= (new DateTime($history['borrow_date']))->format('M d, Y h:i A') ?></td>
                                                        <td><?= (new DateTime($history['due_date']))->format('M d, Y') ?></td>
                                                        <td>
                                                            <?php if ($history['status'] === 'Returned' && !empty($history['returned_at'])): ?>
                                                                <?= (new DateTime($history['returned_at']))->format('M d, Y h:i A') ?>
                                                            <?php else: ?>
                                                                <span style="color: #64748b;">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="status-badge status-<?= strtolower($history['status']) ?>">
                                                                <?= htmlspecialchars($history['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= htmlspecialchars($history['course_year'] ?? 'N/A') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <p style="color: #64748b; text-align: center; padding: 40px;">No borrow history recorded yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Tab 5: Search & Manage -->
                            <div class="inventory-tab-content <?= $activeTab === 'search' ? 'active' : '' ?>" id="tab-search">
                                <div class="inventory-container search-manage-grid">
                                    <div class="search-top-grid">
                                        <div class="search-panel">
                                            <div class="inventory-card">
                                                <div class="section-heading">
                                                    <div>
                                                        <h2>Search Student/Teacher</h2>
                                                        <p class="section-hint">Enter a full name or student/employee ID to locate library records quickly.</p>
                                                    </div>
                                                </div>
                                                <form method="post" class="inventory-form">
                                                    <input type="hidden" name="action" value="search_user">
                                                    <input type="hidden" name="active_tab" value="search">
                                                    <label>Name or ID</label>
                                                    <input type="text" name="search_query" placeholder="Enter full name or student/employee ID" required>
                                                    <button type="submit">Search</button>
                                                </form>

                                                <?php if (!empty($searchResults)): ?>
                                                    <div class="search-results">
                                                        <?php foreach ($searchResults as $result): ?>
                                                            <form method="post" class="search-result-form">
                                                                <input type="hidden" name="action" value="select_user">
                                                                <input type="hidden" name="active_tab" value="search">
                                                                <input type="hidden" name="user_id" value="<?= htmlspecialchars($result['id']) ?>">
                                                                <button type="submit" class="search-result-item">
                                                                    <strong><?= htmlspecialchars($result['full_name']) ?></strong>
                                                                    <span><?= htmlspecialchars($result['role']) ?> · <?= htmlspecialchars($result['course_year'] ?? 'N/A') ?> · <?= htmlspecialchars($result['student_id'] ?? $result['employee_id'] ?? 'N/A') ?></span>
                                                                </button>
                                                            </form>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php elseif (isset($searchResults)): ?>
                                                    <p style="color: #64748b; margin: 0;">No users matched your search. Try another name or ID.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <div class="user-details-panel">
                                            <div class="inventory-card">
                                                <div class="section-heading">
                                                    <h2>User Details</h2>
                                                    <?php if ($selectedUser): ?>
                                                        <span class="section-hint"><?= htmlspecialchars($selectedUser['role']) ?> · <?= htmlspecialchars($selectedUser['course_year'] ?? 'N/A') ?></span>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if ($selectedUser): ?>
                                                    <div class="user-info-card">
                                                        <div>
                                                            <strong><?= htmlspecialchars($selectedUser['full_name']) ?></strong>
                                                            <p style="margin: 8px 0 0; color: #475569; font-size: 14px;">
                                                                ID: <?= htmlspecialchars($selectedUser['student_id'] ?? $selectedUser['employee_id'] ?? $selectedUser['id']) ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="section-heading">
                                                        <h3>Record New Borrow</h3>
                                                        <span class="section-hint">Filter available books by title before choosing one.</span>
                                                    </div>
                                                    <form method="post" class="inventory-form">
                                                        <input type="hidden" name="action" value="record_borrow">
                                                        <input type="hidden" name="active_tab" value="search">
                                                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($selectedUser['id']) ?>">
                                                        <div class="book-search-field">
                                                            <label>Search available books</label>
                                                            <input type="search" id="book-search-input" placeholder="Search by book title" autocomplete="off">
                                                        </div>
                                                        <input type="hidden" name="book_id" id="book-id-input" value="">
                                                        <input type="hidden" name="copy_number" id="copy-number-input" value="">
                                                        <div class="book-results" id="book-search-results">
                                                            <?php if (!empty($availableBooks)): ?>
                                                                <p class="book-result-prompt" id="book-search-prompt">Type a book title to begin searching available books.</p>
                                                                <?php foreach ($availableBooks as $book): ?>
                                                                    <button type="button" class="book-result-item" data-book-id="<?= htmlspecialchars($book['id']) ?>" data-book-title="<?= htmlspecialchars(strtolower($book['title'])) ?>" data-additional-type="<?= htmlspecialchars($book['additional_info_type'] ?? '') ?>" data-additional-quantity="<?= htmlspecialchars($book['additional_info_quantity'] ?? 0) ?>">
                                                                        <strong><?= htmlspecialchars($book['title']) ?></strong>
                                                                        <span><?= htmlspecialchars($book['available_copies']) ?> available</span>
                                                                    </button>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                                <p style="color: #64748b; margin: 0;">No books are currently available for borrowing.</p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="selected-book-summary" id="selected-book-summary" style="display: none;"></div>
                                                        <div class="copy-selection" id="copy-selection" style="display: none; margin-top: 12px;">
                                                            <label for="copy-number-select" style="font-weight: 500; margin-bottom: 6px; display: block;">Select <span id="copy-type-label">Volume</span>:</label>
                                                            <select id="copy-number-select" style="padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 4px; width: 100%;">
                                                                <option value="">Choose...</option>
                                                            </select>
                                                        </div>
                                                        <button type="submit">Record Borrow (3 days due)</button>
                                                    </form>
                                                <?php else: ?>
                                                    <p style="color: #64748b; margin: 0;">Search for a user and select their name to show details here.</p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="borrow-history-panel">
                                        <div class="inventory-card">
                                            <div class="section-heading">
                                                <h2>Borrow History</h2>
                                                <span class="section-hint">Review loans and overdue fines for this user.</span>
                                            </div>
                                            <?php if ($selectedUser && !empty($selectedUserBorrows)): ?>
                                                <table class="borrow-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Book</th>
                                                            <th>Due Date</th>
                                                            <th>Status</th>
                                                            <th>Fine</th>
                                                            <th>Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($selectedUserBorrows as $borrow): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($borrow['title']) ?></td>
                                                                <td><?= (new DateTime($borrow['due_date']))->format('M d, Y') ?></td>
                                                                <td>
                                                                    <span class="status-badge status-<?= strtolower($borrow['borrow_status']) ?>">
                                                                        <?= htmlspecialchars($borrow['borrow_status']) ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <?php if ($borrow['borrow_status'] === 'Overdue' && !empty($borrow['fine_amount'])): ?>
                                                                        <span class="fine-badge">₱<?= number_format($borrow['fine_amount'], 2) ?></span>
                                                                    <?php else: ?>
                                                                        <span style="color: #64748b;">—</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td>
                                                                    <?php if ($borrow['borrow_status'] !== 'Returned'): ?>
                                                                        <form method="post" style="display: inline;">
                                                                            <input type="hidden" name="action" value="record_return">
                                                                            <input type="hidden" name="active_tab" value="search">
                                                                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($selectedUser['id'] ?? '') ?>">
                                                                            <input type="hidden" name="borrow_id" value="<?= htmlspecialchars($borrow['id'] ?? '') ?>">
                                                                            <button type="submit" class="action-button return-button">Mark Return</button>
                                                                        </form>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            <?php elseif ($selectedUser): ?>
                                                <p style="color: #64748b; text-align: center; padding: 24px;">No borrow history for this user.</p>
                                            <?php else: ?>
                                                <p style="color: #64748b; text-align: center; padding: 24px;">Start by selecting a user to see borrow history here.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="fines-panel">
                                        <div class="inventory-card">
                                            <div class="section-heading">
                                                <h2>Outstanding Fines</h2>
                                                <span class="section-hint">Review and mark fines paid after returning books.</span>
                                            </div>
                                            <?php if ($selectedUser): ?>
                                                <?php if ($selectedUser['role'] === 'teacher'): ?>
                                                    <div class="fine-list" style="background: #ecfdf5; border-left-color: #22c55e;">
                                                        <p style="margin: 0; color: #166534; font-size: 14px;">Teachers do not incur library fines for overdue borrows.</p>
                                                    </div>
                                                <?php else: ?>
                                                    <?php $userFines = get_student_fines($selectedUser['id']); ?>
                                                    <?php if (!empty($userFines)): ?>
                                                        <?php $totalUnpaid = 0; ?>
                                                        <?php foreach ($userFines as $fine): ?>
                                                            <?php if ($fine['paid'] == 0) { $totalUnpaid += $fine['amount']; } ?>
                                                            <div class="fine-item <?= $fine['paid'] == 1 ? 'fine-paid' : '' ?>">
                                                                <div class="fine-info">
                                                                    <p style="margin: 0; font-size: 13px; color: #334155;">
                                                                        <strong><?= htmlspecialchars($fine['title'] ?? 'System Fine') ?></strong><br>
                                                                        <small><?= $fine['paid'] == 1 ? '✓ Paid on ' . (new DateTime($fine['created_at']))->format('M d, Y') : 'Due: ' . (new DateTime($fine['due_date']))->format('M d, Y') ?></small>
                                                                    </p>
                                                                </div>
                                                                <div style="text-align: right;">
                                                                    <div class="fine-amount">₱<?= number_format($fine['amount'], 2) ?></div>
                                                                    <?php if ($fine['paid'] == 0): ?>
                                                                        <form method="post" style="display: inline; margin-top: 4px;">
                                                                            <input type="hidden" name="action" value="mark_fine_paid">
                                                                            <input type="hidden" name="active_tab" value="search">
                                                                            <input type="hidden" name="user_id" value="<?= htmlspecialchars($selectedUser['id']) ?>">
                                                                            <input type="hidden" name="fine_id" value="<?= htmlspecialchars($fine['id']) ?>">
                                                                            <button type="submit" class="action-button fine-button">Mark Paid</button>
                                                                        </form>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                        <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #fee2e2; background: white; border-radius: 8px; padding: 12px;">
                                                            <strong style="color: #991b1b;">Total Unpaid: ₱<?= number_format($totalUnpaid, 2) ?></strong>
                                                        </div>
                                                    <?php else: ?>
                                                        <p style="color: #64748b; margin: 0;">This user has no outstanding fines.</p>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <p style="color: #64748b; text-align: center; padding: 24px;">Select a user to view outstanding fines.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>
    <div class="modal-overlay" id="fine-modal-overlay" aria-hidden="true">
        <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="fine-modal-title">
            <div class="modal-header">
                <h3 id="fine-modal-title">Outstanding Fines</h3>
                <button type="button" id="fine-modal-close" class="modal-close" aria-label="Close modal">×</button>
            </div>
            <div class="modal-body">
                <div class="modal-summary" id="fine-modal-summary"></div>
                <div id="fine-modal-body"></div>
            </div>
            <div class="modal-footer">
                <button type="button" id="fine-modal-cancel" class="modal-action-button secondary">Close</button>
            </div>
        </div>
    </div>
    <div class="modal-overlay" id="book-info-modal-overlay" aria-hidden="true">
        <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="book-info-modal-title">
            <div class="modal-header">
                <h3 id="book-info-modal-title">Book Details</h3>
                <button type="button" id="book-info-modal-close" class="modal-close" aria-label="Close modal">×</button>
            </div>
            <div class="modal-body">
                <div class="modal-summary" id="book-info-modal-summary"></div>
                <div id="book-info-modal-body"></div>
            </div>
            <div class="modal-footer">
                <button type="button" id="book-info-modal-cancel" class="modal-action-button secondary">Close</button>
            </div>
        </div>
    </div>
    <script src="assets/js/app.js" defer></script>
    <script>
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
