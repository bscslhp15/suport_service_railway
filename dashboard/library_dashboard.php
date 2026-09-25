<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions_fixed.php';
require_once __DIR__ . '/../includes/library_dashboard_functions.php';
require_login();
$user = current_user();
if (!$user) {
    header('Location: ../auth/student_login.php');
    exit;
}

$service = strtolower(trim($_GET['service'] ?? ''));
// Treat teacher head for SSC/Scholarship as combined mode when no explicit service provided
$isHeadSscScholarship = $user['role'] === 'teacher' && (($user['head_service'] ?? '') === 'ssc_scholarship');
if ($service === '' && $isHeadSscScholarship) { $service = 'ssc/scholarship'; }
$isLibraryService = $service === 'library';
$isGuidanceService = $service === 'guidance';
// Combined SSC/Scholarship support
$isSscScholarshipService = $service === 'ssc/scholarship' || ($service === 'ssc' && $isHeadSscScholarship);
$sscScholarshipServiceParam = $isSscScholarshipService ? 'ssc/scholarship' : 'ssc';
$scholarshipServiceParam = $isSscScholarshipService ? 'ssc/scholarship' : 'scholarship';
$isClinicService = $service === 'clinic';

if ($user['role'] === 'teacher' && $user['head_service'] === 'library' && !$isLibraryService && !$isGuidanceService) {
    header('Location: librarian_home.php');
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
$topbarInfo = build_user_dashboard_header_info($user);
$roleLabel = $user['role'] === 'teacher' ? 'Teacher' : 'Student';
$dashboardLink = $user['role'] === 'teacher' ? 'teacher_home.php' : 'student_home.php';
$currentPage = basename($_SERVER['PHP_SELF']);
$servicesOpen = in_array($currentPage, ['guidance_home.php', 'nurse_home.php', 'ssc_head_home.php', 'ssaa_home.php'], true);

ensure_library_schema();
mark_overdue_library_items();
auto_cancel_unpicked_reservations(); // Auto-cancel unpicked reservations after 1 day
finalize_library_visits_at_end_of_day(); // Auto-checkout active visits after 5pm

// Normalize cover URL for use in dashboard outputs
function normalize_cover_url_for_dashboard(?string $rawUrl): string {
    if (empty($rawUrl)) return '';
    $raw = trim($rawUrl);
    if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://') || str_starts_with($raw, 'data:')) {
        return $raw;
    }
    // ensure relative path from dashboard folder
    return str_starts_with($raw, '../') ? $raw : '../' . ltrim($raw, './');
}
$categories = get_library_categories();
$maxBorrowed = (int)get_library_setting('max_borrowed_books', 3);
$maxReservations = (int)get_library_setting('max_active_reservations', 2);
$borrowDuration = (int)get_library_setting('borrow_duration_school_days', 3);
$message = '';
$messageType = 'success';
$actionResult = null;

// Handle e-resource upload (AJAX or form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_resource' && $user['role'] === 'teacher') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($title) || empty($description)) {
        $message = 'Please fill in all required fields.';
        $messageType = 'error';
    } elseif (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Please upload a valid file.';
        $messageType = 'error';
    } else {
        $file = $_FILES['file'];
        $maxSize = 50 * 1024 * 1024; // 50 MB
        $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'txt'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($file['size'] > $maxSize) {
            $message = 'File size exceeds 50 MB limit.';
            $messageType = 'error';
        } elseif (!in_array($fileExt, $allowedTypes)) {
            $message = 'File type not allowed. Supported: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, ZIP, TXT';
            $messageType = 'error';
        } else {
            // Create uploads directory if it doesn't exist
            $uploadsDir = __DIR__ . '/uploads/e-resources';
            if (!is_dir($uploadsDir)) {
                @mkdir($uploadsDir, 0755, true);
            }
            
            // Generate unique filename
            $fileName = uniqid() . '_' . str_replace(' ', '_', $file['name']);
            $filePath = 'uploads/e-resources/' . $fileName;
            $fullPath = $uploadsDir . '/' . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $fullPath)) {
                if (upload_library_resource($user['id'], $title, $description, $filePath, $file['name'], $file['size'], $fileExt)) {
                    $message = 'E-Resource uploaded successfully! It will be reviewed by the librarian.';
                } else {
                    @unlink($fullPath);
                    $message = 'Unable to save resource information. Please try again.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Unable to upload file. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookIdStr = $_POST['book_id'] ?? '';
    $action = $_POST['action'] ?? '';
    $activeVisit = get_user_active_visit($user['id']);
    $borrowedBooks = get_user_borrowed_books($user['id']);
    $reservationCount = get_user_active_reservations_count($user['id']);
    $borrowedCount = count(array_filter($borrowedBooks, fn($item) => $item['borrow_status'] !== 'Returned'));

    // Parse bookId and additional_info if virtual
    $additionalInfo = '';
    if (strpos($bookIdStr, '-') !== false) {
        $parts = explode('-', $bookIdStr);
        if (count($parts) >= 3) {
            $bookId = (int)$parts[0];
            $type = $parts[1];
            $number = $parts[2];
            $additionalInfo = ucfirst($type) . ' ' . $number;
        } else {
            $bookId = (int)$bookIdStr;
        }
    } else {
        $bookId = (int)$bookIdStr;
    }

    $book = $bookId > 0 ? get_library_book_by_id($bookId) : null;

    if (($action === 'borrow' || $action === 'reserve') && (!$book || $bookId <= 0)) {
        $message = 'Unable to process the request. Please select a valid book.';
        $messageType = 'error';
    } elseif ($action === 'borrow') {
        if (!$activeVisit) {
            $message = 'You must check in at the library QR station before borrowing a book.';
            $messageType = 'error';
        } elseif ($borrowedCount >= $maxBorrowed) {
            $message = 'You have reached the maximum of ' . $maxBorrowed . ' borrowed books. Return a book before borrowing another.';
            $messageType = 'error';
        } elseif ($book['status'] !== 'Available' || $book['available_copies'] <= 0) {
            $message = 'This book is not currently available for borrowing.';
            $messageType = 'error';
        } else {
            $now = new DateTime();
            $borrowDays = (int)get_library_setting('borrow_duration_school_days', 3);
            $dueDate = calculate_library_due_date($now, $borrowDays)->format('Y-m-d H:i:s');
            if (borrow_book($user['id'], $bookId, $now->format('Y-m-d H:i:s'), $dueDate)) {
                $message = 'Borrow request successful. Please return the book by ' . (new DateTime($dueDate))->format('M d, Y') . ' by 5:00 PM.';
            } else {
                $message = 'Unable to borrow the book at this time. Please try again later.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'reserve') {
        $pickupDate = $_POST['pickup_date'] ?? '';
        if (empty($pickupDate)) {
            $message = 'Please select a pickup date.';
            $messageType = 'error';
        } elseif ($book['available_copies'] <= 0) {
            $message = 'This book is not available for reservation. No copies are currently in stock.';
            $messageType = 'error';
        } elseif ($reservationCount >= 2) {
            $message = 'You already have 2 active reservations. Cancel one before reserving another.';
            $messageType = 'error';
        } elseif (has_user_reserved_book($user['id'], $bookId)) {
            $message = 'You already have an active reservation for this book.';
            $messageType = 'error';
        } else {
            if (reserve_book_with_pickup($user['id'], $bookId, $pickupDate, $additionalInfo)) {
                $pickupDateTime = DateTime::createFromFormat('Y-m-d', $pickupDate);
                if ($pickupDateTime !== false) {
                    $pickupDateTime->setTime(17, 0, 0);
                    $pickupDateTime = $pickupDateTime->format('M d, Y g:i A');
                } else {
                    $pickupDateTime = $pickupDate;
                }
                $message = 'Reservation successful! Pick up by ' . $pickupDateTime . '.';
            } else {
                $message = 'Unable to reserve this book right now. Please try again later.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'cancel_reservation') {
        $reservationId = (int)($_POST['reservation_id'] ?? 0);
        if ($reservationId <= 0) {
            $message = 'Invalid reservation. Please try again.';
            $messageType = 'error';
        } elseif (cancel_library_reservation($reservationId, $user['id'])) {
            $message = 'Reservation cancelled successfully.';
            $messageType = 'success';
        } else {
            $message = 'Unable to cancel the reservation. It may have already been processed.';
            $messageType = 'error';
        }
    } elseif ($action === 'save_feedback') {
        $borrowId = (int)($_POST['borrow_id'] ?? 0);
        $bookId = (int)($_POST['book_id'] ?? 0);
        $bookRating = (int)($_POST['book_rating'] ?? 0);
        $serviceRating = (int)($_POST['service_rating'] ?? 0);
        $bookReview = $_POST['book_review'] ?? '';
        $serviceFeedback = $_POST['service_feedback'] ?? '';
        
        if ($borrowId <= 0 || $bookId <= 0 || $bookRating < 1 || $bookRating > 5 || $serviceRating < 1 || $serviceRating > 5) {
            $message = 'Please provide valid feedback data with both ratings.';
            $messageType = 'error';
        } else {
            $feedbackData = [
                'borrow_id' => $borrowId,
                'user_id' => $user['id'],
                'book_id' => $bookId,
                'book_rating' => $bookRating,
                'service_rating' => $serviceRating,
                'book_review' => $bookReview,
                'service_feedback' => $serviceFeedback
            ];
            if (save_library_feedback($feedbackData)) {
                $message = 'Thank you for your feedback!';
                $messageType = 'success';
            } else {
                $message = 'Unable to save your feedback. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

$activeVisit = get_user_active_visit($user['id']);
$borrowedBooks = get_user_borrowed_books($user['id']);
$activeBorrowedBooks = array_values(array_filter($borrowedBooks, fn($borrow) => $borrow['borrow_status'] !== 'Returned'));
$reservations = get_user_reservations($user['id']);
$cancelledReservations = get_user_cancelled_reservations($user['id'], 10);
$recentReturnedBooks = array_filter($borrowedBooks, fn($borrow) => $borrow['borrow_status'] === 'Returned');
$userCourse = trim($user['course_year'] ?? '');
$availableResources = get_library_resources_for_user($user['id'], true, 6);
$resourceCategories = get_all_resource_categories($userCourse);
$openAccessCategories = get_open_access_resource_categories($userCourse);
$openAccessResources = get_open_access_resources($userCourse);
$userUploadedResources = ($user['role'] === 'teacher') ? get_user_uploaded_resources($user['id']) : [];
$libraryBooks = get_library_books(500);
$catalogBooks = [];
foreach ($libraryBooks as $book) {
    $coverUrl = '';
    if (!empty($book['cover_url'])) {
        $rawUrl = trim($book['cover_url']);
        if (str_starts_with($rawUrl, 'http://') || str_starts_with($rawUrl, 'https://') || str_starts_with($rawUrl, 'data:')) {
            $coverUrl = $rawUrl;
        } else {
            $coverUrl = str_starts_with($rawUrl, '/') ? $rawUrl : (str_starts_with($rawUrl, '../') ? $rawUrl : '../' . ltrim($rawUrl, './'));
        }
    }
    $catalogBooks[] = [
        'id' => $book['id'],
        'title' => $book['title'],
        'author' => $book['author'],
        'isbn' => $book['isbn'],
        'lcc_letter_line' => $book['lcc_letter_line'],
        'lcc_class_number' => $book['lcc_class_number'],
        'lcc_cutter_code' => $book['lcc_cutter_code'],
        'lcc_published_year' => $book['lcc_published_year'],
        'lcc_additional_info' => $book['lcc_additional_info'],
        'additional_info_type' => $book['additional_info_type'] ?? null,
        'additional_info_quantity' => $book['additional_info_quantity'] ?? 0,
        'category' => $book['category'] ?: 'General',
        'status' => $book['status'],
        'available_copies' => $book['available_copies'],
        'total_copies' => $book['total_copies'],
        'rating' => $book['rating'],
        'overview' => $book['overview'],
        'cover_url' => $coverUrl,
        'reservationQueue' => get_book_reservation_queue($book['id']),
        'userReservation' => get_user_book_reservation($user['id'], $book['id']) ?: null,
    ];
}
$borrowedCount = count(array_filter($borrowedBooks, fn($item) => $item['borrow_status'] !== 'Returned'));
$reservationCount = get_user_active_reservations_count($user['id']);
$resourceCount = count($availableResources);
$calendarBlocks = get_library_calendar_blocks(5);
$checkinLabel = $activeVisit ? 'Checked in at ' . (new DateTime($activeVisit['time_in']))->format('h:i A') : 'Not checked in';
$clearanceStatus = can_student_get_clearance($user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        @font-face {
            font-family: 'ElephantLocal';
            src: url('../assets/FONTS/ELEPHNT.TTF') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        .nav-brand h2 {
            font-family: 'ElephantLocal', 'Playfair Display', serif;
        }
    </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Dashboard | PASS Support System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <meta name="theme-color" content="#800000">
    <style>
        .resources-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            grid-auto-rows: minmax(220px, auto);
        }

        .library-header h1 i {
            color: #6B1F2A;
            font-size: 2.4rem;
            border: none;
            background: transparent;
        }

        .library-header {
            background: #ffffff;
        }

        .library-tab-bar {
            background: #ffffff;
        }

        .library-tab-swipe-hint {
            display: none;
            margin: 12px 0 24px;
            font-size: 13px;
            color: #6b7280;
            text-align: center;
        }

        .borrowed-card-list,
        .activity-history-card-list {
            display: grid;
            gap: 16px;
        }

        .borrowed-book-card,
        .activity-history-card,
        .returned-book-card {
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            background: #ffffff;
            padding: 18px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
        }

        .borrowed-card-title,
        .activity-history-card-title {
            margin: 0 0 14px;
            font-size: 16px;
            font-weight: 700;
            color: #111827;
        }

        .borrowed-card-row,
        .activity-card-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 14px;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .borrowed-card-row:last-child,
        .activity-card-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .borrowed-card-label,
        .activity-card-label {
            color: #6b7280;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .borrowed-card-value,
        .activity-card-value {
            color: #111827;
            font-size: 14px;
            text-align: right;
        }

        .borrowed-card-status,
        .activity-card-status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .borrowed-card-status.overdue,
        .activity-card-status.overdue {
            background: #fee2e2;
            color: #b91c1c;
        }

        .borrowed-card-status.borrowed,
        .activity-card-status.returned {
            background: #d4fc79;
            color: #0f172a;
        }

        .borrowed-card-status.available,
        .activity-card-status.available {
            background: #dbeafe;
            color: #1e40af;
        }

        .borrowed-card-note,
        .activity-card-note {
            color: #475569;
            font-size: 13px;
            line-height: 1.5;
            margin: 8px 0 0;
        }

        @media (max-width: 768px) {
            .library-tab-bar {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }
            .library-tab-bar::-webkit-scrollbar {
                display: none;
            }
            .library-tab-button {
                flex: 0 0 auto;
                white-space: nowrap;
                min-width: 140px;
            }
            .library-tab-swipe-hint {
                display: block;
            }
            .library-panel {
                touch-action: pan-y;
            }
            .dashboard-summary-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 14px;
            }
            .catalog-search-row,
            .resource-filter-row {
                display: grid;
                gap: 12px;
            }
            .catalog-search-row input,
            .catalog-search-row select,
            .resource-filter-row input,
            .resource-filter-row select {
                width: 100%;
                min-width: 0;
            }
            .catalog-track {
                display: grid;
                grid-template-columns: 1fr;
                grid-auto-flow: row;
                grid-auto-columns: auto;
                gap: 16px;
                padding: 0;
            }
            .catalog-card {
                min-width: auto !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            .catalog-cover {
                height: 240px;
            }
            .catalog-card-meta {
                padding: 16px 14px 18px;
            }
            .catalog-card-meta h3 {
                font-size: 16px;
            }
            .catalog-card-meta .catalog-meta-top {
                gap: 10px;
            }
            .carousel-nav {
                display: none;
            }
            .library-table-card {
                overflow-x: visible;
            }
            .borrowed-table {
                min-width: 0;
                width: 100%;
            }
            .borrowed-table th,
            .borrowed-table td {
                white-space: normal;
            }
            .resource-card {
                min-height: auto;
                padding: 14px;
            }
            .resource-layout {
                display: grid;
                grid-template-columns: 1fr;
                gap: 16px;
            }
            .book-detail-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 20px;
            }
            #book-detail-modal .modal-content {
                padding: 20px;
                max-width: 100%;
            }
            #book-detail-modal .modal-content h1 {
                font-size: 24px;
            }
            #book-detail-modal .book-detail-grid {
                gap: 18px;
            }
            #book-detail-modal .modal-content {
                padding: 18px;
            }
            #book-detail-modal .modal-content > div {
                width: 100%;
            }
            #book-detail-modal .book-detail-grid > div {
                width: 100%;
            }
            #book-detail-modal .modal-content h1 {
                font-size: 22px;
                margin-bottom: 12px;
            }
            #book-detail-modal .book-detail-grid .cover-placeholder,
            #book-detail-modal .book-detail-grid img {
                min-height: 320px;
                max-height: 320px;
            }
            #book-detail-modal .book-detail-grid img {
                width: 100%;
                object-fit: cover;
            }
            #book-detail-modal .modal-content > div:first-child {
                padding-bottom: 0;
            }
            .library-table-card {
                overflow-x: visible;
                padding: 18px;
                border-radius: 18px;
                border: 1px solid #e2e8f0;
                background: #ffffff;
            }
            .library-table-card h3 {
                margin-top: 0;
            }
            .activity-history-section,
            .reservation-list,
            .activity-history-list {
                display: grid;
                gap: 14px;
            }
            .activity-history-section table {
                width: 100%;
                min-width: 0;
            }
            .activity-history-section th,
            .activity-history-section td {
                padding: 12px 14px;
                border: 1px solid #e2e8f0;
                font-size: 13px;
            }
            .activity-history-section th {
                background: #f8fafc;
                font-weight: 700;
                color: #334155;
            }
            .activity-history-section td {
                color: #475569;
            }
            .activity-history-item,
            .reservation-item {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 18px;
                padding: 16px;
                box-shadow: 0 10px 24px rgba(15,23,42,0.06);
            }
            .activity-history-title,
            .reservation-item-title {
                font-size: 15px;
                margin: 0 0 6px 0;
                color: #111827;
            }
            .activity-history-meta,
            .reservation-item-meta,
            .reservation-item-date {
                font-size: 13px;
                color: #64748b;
                margin: 0;
                line-height: 1.5;
            }
            .reservation-item-header {
                display: grid;
                gap: 10px;
            }
            .reservation-item-footer {
                display: grid;
                gap: 10px;
                align-items: start;
            }
            .reservation-item-footer form {
                width: 100%;
            }
            .cancel-reservation-button {
                width: 100%;
                max-width: 320px;
                border-radius: 10px;
                background: #f8fafc;
                border: 1px solid #cbd5e0;
                padding: 10px 14px;
                color: #2563eb;
                font-weight: 600;
            }
            .reservation-item-status {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 6px 12px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 700;
                width: fit-content;
            }
            .reservation-item-status.active {
                background: #dcfce7;
                color: #166534;
            }
            .reservation-item-status.pending,
            .reservation-item-status.waiting {
                background: #fef3c7;
                color: #92400e;
            }
            .reservation-item-status.cancelled,
            .reservation-item-status.cancelled {
                background: #fee2e2;
                color: #b91c1c;
            }
            .resource-filter-row {
                display: grid !important;
                gap: 12px !important;
                width: 100%;
            }
            .resource-filter-row input,
            .resource-filter-row select {
                width: 100% !important;
                min-width: 0 !important;
            }
            .resource-card {
                min-height: auto;
                padding: 16px;
            }
            .resource-card .download-btn {
                display: inline-flex;
                width: fit-content;
                padding: 8px 14px;
                border-radius: 10px;
                border: 1px solid #cbd5e0;
                background: #f8fafc;
            }
            .calendar-placeholder {
                padding: 18px;
                border-radius: 16px;
                border: 1px dashed #cbd5e0;
                background: #f8fafc;
            }
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .responsive-table,
        .borrowed-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 0;
        }
        .responsive-table th,
        .responsive-table td,
        .borrowed-table th,
        .borrowed-table td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            white-space: normal;
        }
        .responsive-table thead,
        .borrowed-table thead {
            background: #f8fafc;
        }
        .responsive-table th,
        .borrowed-table th {
            font-weight: 700;
            color: #334155;
            font-size: 13px;
        }
        .responsive-table td,
        .borrowed-table td {
            color: #475569;
            font-size: 13px;
        }
        .responsive-table td button.btn-review,
        .borrowed-table td button.btn-review {
            width: auto;
            min-width: 150px;
        }
        .responsive-table td[data-label]:before,
        .borrowed-table td[data-label]:before {
            content: attr(data-label) ": ";
            font-weight: 700;
            display: none;
        }
        .activity-history-list,
        .reservation-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        @media (max-width: 768px) {
            .responsive-table,
            .responsive-table thead,
            .responsive-table tbody,
            .responsive-table th,
            .responsive-table td,
            .responsive-table tr,
            .borrowed-table,
            .borrowed-table thead,
            .borrowed-table tbody,
            .borrowed-table th,
            .borrowed-table td,
            .borrowed-table tr {
                display: block;
                width: 100%;
            }
            .responsive-table thead,
            .borrowed-table thead { display: none; }
            .responsive-table tbody tr,
            .borrowed-table tbody tr {
                display: flex;
                flex-direction: column;
                margin-bottom: 16px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            }
            .responsive-table tbody tr:hover,
            .borrowed-table tbody tr:hover {
                background: #ffffff;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            }
            .responsive-table td,
            .borrowed-table td {
                padding: 12px 16px;
                border: none;
                border-bottom: 1px solid #f3f4f6;
                position: relative;
                display: grid;
                grid-template-columns: max-content minmax(0, 1fr);
                column-gap: 16px;
                align-items: center;
                width: 100% !important;
                min-width: 0 !important;
                word-break: normal !important;
                overflow-wrap: normal !important;
            }
            .responsive-table td:last-child,
            .borrowed-table td:last-child { border-bottom: none; }
            .responsive-table td[data-label]::before,
            .borrowed-table td[data-label]::before {
                content: attr(data-label) ": ";
                font-weight: 600;
                color: #6b7280;
                display: inline-block;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                min-width: 0;
            }
            .responsive-table td:last-child,
            .borrowed-table td:last-child { padding-bottom: 18px; }
            .responsive-table td > *,
            .borrowed-table td > * {
                min-width: 0 !important;
                word-break: normal !important;
                overflow-wrap: normal !important;
            }
        }
        .activity-history-item,
        .reservation-item {
            list-style: none;
        }
        .library-header p {
            color: #B07A2B;
            margin: 0;
            font-size: 16px;
            line-height: 1.5;
        }

        .library-header p .welcome-name {
            font-weight: 700;
            color: inherit;
        }


        .resource-card {
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 18px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-height: 215px;
        }

        .resource-card h4 {
            margin: 0;
            font-size: 15px;
            color: #1e293b;
            line-height: 1.3;
        }

        .resource-card p {
            margin: 0;
            color: #475569;
            font-size: 13px;
            line-height: 1.45;
        }

        .resource-card .download-btn {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
        }

        .resource-layout {
            display: grid;
            gap: 24px;
            grid-template-columns: 2fr 1fr;
            align-items: start;
        }

        @media screen and (max-width: 900px) {
            .resource-layout {
                grid-template-columns: 1fr;
            }
        }

        .modal-variant-section {
            display: none;
            margin-top: 18px;
        }

        .modal-variant-grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        }

        .variant-card {
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 16px;
            background: #ffffff;
            display: grid;
            grid-template-rows: auto 1fr;
            gap: 12px;
            min-height: 120px;
            text-align: center;
            align-items: center;
            justify-items: center;
            cursor: pointer;
            transition: transform 0.22s ease, border-color 0.22s ease, box-shadow 0.22s ease;
        }

        .variant-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 36px rgba(15, 23, 42, 0.12);
        }

        .variant-card.active {
            border-color: #7a2b2b;
            box-shadow: 0 16px 36px rgba(122, 43, 43, 0.14);
            background: #fff7f0;
        }

        .variant-card-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #f1f5f9;
            display: grid;
            place-items: center;
            font-size: 24px;
            color: #7a2b2b;
        }

        .variant-label {
            color: #0f172a;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.3;
        }

        @media (max-width: 1600px) {
            .resources-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (max-width: 1200px) {
            .resources-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 900px) {
            .resources-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .resources-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .resource-card {
                min-height: auto;
                padding: 14px;
            }

            .resource-card h4 {
                font-size: 14px;
            }

            .library-header h1 i {
                font-size: 1.8rem;
            }

            .library-header h1 {
                font-size: 1.5rem;
            }
        }

        /* Responsive grid for two columns */
        .responsive-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        @media (max-width: 768px) {
            .responsive-grid-2 {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }

        /* Responsive grid for two columns with auto layout */
        .responsive-grid-2-auto {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 16px;
            align-items: center;
        }

        @media (max-width: 768px) {
            .responsive-grid-2-auto {
                grid-template-columns: 1fr;
                gap: 10px;
                align-items: stretch;
            }
        }

        /* Responsive grid for two columns variant */
        .responsive-grid-2-variant {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        @media (max-width: 768px) {
            .responsive-grid-2-variant {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }

        body.library-tab-loading .library-panel {
            display: none !important;
        }
        body.library-tab-loading .library-panel.active {
            display: block !important;
        }

        @keyframes spin-refresh {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        /* Swipe-to-Refresh for Mobile (320px to 768px) */
        @media (max-width: 768px) {
            body.swipe-refresh-enabled {
                touch-action: pan-x pan-y;
            }
        }

        #book-detail-modal .modal-content {
            position: relative;
            padding: 0 !important;
            border-radius: 24px;
            background: #ffffff;
        }
        #book-detail-modal .book-detail-grid {
            position: relative;
            grid-template-columns: 260px minmax(0, 1fr) !important;
            gap: 28px;
            min-height: 540px;
            margin-bottom: 0;
            padding: 190px 32px 32px;
            background: linear-gradient(to bottom, #68151c 0, #8f2730 190px, #ffffff 190px);
        }
        #book-detail-modal .book-detail-grid > div:first-child {
            position: absolute;
            z-index: 2;
            top: 122px;
            left: 32px;
            width: 230px !important;
            padding: 10px !important;
            border-radius: 14px !important;
            background: #ffffff !important;
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.18) !important;
        }
        #book-detail-modal .book-detail-grid > div:first-child #modal-cover-container {
            border-radius: 9px !important;
        }
        #book-detail-modal .book-detail-grid > div:last-child {
            grid-column: 2;
            min-width: 0;
        }
        #book-detail-modal #modal-book-title {
            position: absolute;
            top: 28px;
            left: 292px;
            right: 72px;
            z-index: 1;
            overflow: visible;
            margin: 0 !important;
            color: #ffffff !important;
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 34px !important;
            line-height: 1.15;
            white-space: normal;
            overflow-wrap: anywhere;
        }
        #book-detail-modal #modal-book-author {
            position: absolute;
            top: 112px;
            left: 292px;
            right: 72px;
            z-index: 1;
            color: rgba(255, 255, 255, 0.8) !important;
        }
        #book-detail-modal .book-detail-grid > div:last-child > div:first-of-type {
            margin-bottom: 20px !important;
        }
        #book-detail-modal #modal-action-form > div {
            padding: 0 !important;
        }
        #book-detail-modal #pickup-date-input {
            border-color: #d8b8a8 !important;
            border-radius: 10px !important;
        }
        #book-detail-modal .book-detail-grid > div:last-child > div[style*="background: #fffbf5"] {
            border: 1px solid #eadfd8 !important;
            border-radius: 14px !important;
            background: #fffaf4 !important;
        }
        #book-detail-modal .book-reservation-panel {
            padding: 16px !important;
            border: 1px solid #ecd29a !important;
            border-radius: 14px !important;
            background: #fff8e9 !important;
        }
        #book-detail-modal .book-reservation-form {
            display: grid !important;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px !important;
            align-items: end;
        }
        #book-detail-modal .book-reservation-label {
            display: block;
            margin-bottom: 8px;
            color: #5f3b2d;
            font-size: 12px;
            font-weight: 700;
        }
        #book-detail-modal .book-reservation-label i {
            margin-right: 6px;
            color: #8f0011;
        }
        #book-detail-modal #pickup-date-input {
            box-sizing: border-box;
            min-height: 36px;
            padding: 8px 10px !important;
            background: #ffffff !important;
            font-size: 12px !important;
        }
        #book-detail-modal #modal-reserve-btn {
            width: auto !important;
            min-height: 36px;
            margin-top: 0 !important;
            padding: 8px 16px !important;
            border-radius: 9px !important;
            background: #8f0011 !important;
            font-size: 12px;
            white-space: nowrap;
        }
        #book-detail-modal #modal-reserve-btn i {
            margin-right: 6px;
        }
        #book-detail-modal .book-reservation-hint {
            margin: 8px 0 0 !important;
            color: #92756b !important;
            font-size: 10px !important;
        }
        #book-detail-modal .modal-close {
            z-index: 4;
            color: #ffffff;
        }
        @media (max-width: 980px) {
            #book-detail-modal .book-detail-grid {
                grid-template-columns: 230px minmax(0, 1fr) !important;
                padding-left: 24px;
                padding-right: 24px;
            }
            #book-detail-modal .book-detail-grid > div:first-child {
                left: 24px;
                width: 200px !important;
            }
            #book-detail-modal #modal-book-title,
            #book-detail-modal #modal-book-author {
                left: 274px;
            }
        }
        @media (max-width: 768px) {
            #book-detail-modal .book-detail-grid {
                display: block !important;
                min-height: 0;
                padding: 150px 18px 24px;
                background: linear-gradient(to bottom, #68151c 0, #8f2730 var(--mobile-modal-hero-height, 150px), #ffffff var(--mobile-modal-hero-height, 150px));
            }
            #book-detail-modal .book-detail-grid > div:first-child {
                position: relative;
                top: auto;
                left: auto;
                width: min(190px, 70vw) !important;
                margin: var(--mobile-modal-cover-offset, -60px) auto 22px;
            }
            #book-detail-modal .book-detail-grid > div:last-child {
                width: 100% !important;
            }
            #book-detail-modal #modal-book-title,
            #book-detail-modal #modal-book-author {
                position: absolute;
                left: 18px;
                right: 52px;
                text-align: center;
            }
            #book-detail-modal #modal-book-title {
                top: 24px;
                font-size: 25px !important;
                line-height: 1.12;
            }
            #book-detail-modal #modal-book-author {
                top: 88px;
                z-index: 3;
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            #book-detail-modal .book-reservation-form {
                grid-template-columns: 1fr;
                align-items: stretch;
            }
            #book-detail-modal #modal-reserve-btn {
                width: 100% !important;
            }
        }

        #catalog-books-container.catalog-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 20px;
            width: 100%;
            padding: 0;
            overflow: visible;
        }
        #catalog-books-container.catalog-grid .catalog-card {
            min-width: 0;
            width: 100%;
        }
        .catalog-pagination {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 24px;
        }
        .catalog-page-button {
            min-width: 38px;
            height: 38px;
            padding: 0 10px;
            border: 1px solid #d8b8a8;
            border-radius: 8px;
            background: #fffdfb;
            color: #8f0011;
            font-weight: 700;
            cursor: pointer;
        }
        .catalog-page-button:hover,
        .catalog-page-button.active {
            background: #8f0011;
            color: #ffffff;
            border-color: #8f0011;
        }
        @media (max-width: 1100px) {
            #catalog-books-container.catalog-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        @media (max-width: 768px) {
            #catalog-books-container.catalog-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 520px) {
            #catalog-books-container.catalog-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="library-tab-loading">
    <!-- Swipe-to-Refresh Spinner (Mobile Only) -->
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); z-index: 9999; justify-content: center; align-items: center;">
        <div style="text-align: center;">
            <div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div>
            <p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0;">Refreshing...</p>
        </div>
    </div>
    <div id="toast-container" class="toast-container"></div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-mobile-header">
                <div class="mobile-user-info">
                    <h4><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p><?= htmlspecialchars($topbarInfo['displayMeta']) ?></p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar" <?= $isLibraryService ? 'onclick="toggleGuidanceSidebar()"' : '' ?>>
                    <i class="fa-solid fa-bars"></i>
                </button>
                <!-- search button removed per request -->
            </div>
            <div class="nav-section">
                <?php if ($isGuidanceService): ?>
                    <a href="guidance_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=guidance" data-tooltip="Announcements">
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
                            <a href="guidance_dashboard.php?service=guidance" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php?service=guidance" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php?service=guidance" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc/ssc_dashboard.php?service=guidance" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship/scholarship_dashboard.php?service=guidance" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="ssaa_student_home.php?service=guidance" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
                            </a>
                        </div>
                    </div>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage Guidance">
                            <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                            <span class="nav-text">Manage Guidance</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="case_management.php" data-tooltip="Case Management">
                                <span class="nav-icon"><i class="fa-solid fa-file-circle-plus"></i></span>
                                <span class="nav-text">Case Management</span>
                            </a>
                            <a href="good_moral.php" data-tooltip="Good Moral">
                                <span class="nav-icon"><i class="fa-solid fa-certificate"></i></span>
                                <span class="nav-text">Good Moral</span>
                            </a>
                            <a href="reports.php" data-tooltip="Reports">
                                <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                                <span class="nav-text">Reports</span>
                            </a>
                        </div>
                    </div>
                    <a href="profile.php?service=guidance" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php?service=guidance" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($isLibraryService): ?>
                    <a href="librarian_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=library" data-tooltip="Announcements">
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
                            <a href="guidance_dashboard.php?service=library" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php?service=library" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php?service=library" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc/ssc_dashboard.php?service=library" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship/scholarship_dashboard.php?service=library" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="ssaa_student_home.php?service=library" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
                            </a>
                        </div>
                    </div>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage Library">
                            <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                            <span class="nav-text">Manage Library</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="../library_checkin.php" data-tooltip="QR Check-In">
                                <span class="nav-icon"><i class="fa-solid fa-qrcode"></i></span>
                                <span class="nav-text">QR Check-In</span>
                            </a>
                            <a href="../library_catalog.php" data-tooltip="Digital Catalog">
                                <span class="nav-icon"><i class="fa-solid fa-book-open"></i></span>
                                <span class="nav-text">Digital Catalog</span>
                            </a>
                            <a href="../library_inventory.php" data-tooltip="Inventory">
                                <span class="nav-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
                                <span class="nav-text">Inventory</span>
                            </a>
                            <a href="../library_reports.php" data-tooltip="Reports">
                                <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                                <span class="nav-text">Reports</span>
                            </a>
                            <a href="../library_settings.php" data-tooltip="Settings">
                                <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
                                <span class="nav-text">Settings</span>
                            </a>
                            <a href="../library_qr.php" data-tooltip="Library QR">
                                <span class="nav-icon"><i class="fa-solid fa-qrcode"></i></span>
                                <span class="nav-text">Library QR</span>
                            </a>
                        </div>
                    </div>
                    <a href="profile.php?service=library" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php?service=library" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($isClinicService): ?>
                    <a href="nurse_home.php" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=clinic" data-tooltip="Announcements">
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
                            <a href="guidance_dashboard.php?service=clinic" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php?service=clinic" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php?service=clinic" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc/ssc_dashboard.php?service=clinic" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship/scholarship_dashboard.php?service=clinic" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="ssaa_student_home.php?service=clinic" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
                            </a>
                        </div>
                    </div>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage">
                            <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                            <span class="nav-text">Manage</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="clinic_status.php" data-tooltip="Clinic Status">
                                <span class="nav-icon"><i class="fa-solid fa-clock"></i></span>
                                <span class="nav-text">Clinic Status</span>
                            </a>
                            <a href="clinic_health_records.php" data-tooltip="Health Records">
                                <span class="nav-icon"><i class="fa-solid fa-heart-pulse"></i></span>
                                <span class="nav-text">Health Records</span>
                            </a>
                            <a href="clinic_medicine_inventory.php" data-tooltip="Medicine Inventory">
                                <span class="nav-icon"><i class="fa-solid fa-pills"></i></span>
                                <span class="nav-text">Medicine Inventory</span>
                            </a>
                            <a href="clinic_visit_logs.php" data-tooltip="Visit Logs">
                                <span class="nav-icon"><i class="fa-solid fa-notes-medical"></i></span>
                                <span class="nav-text">Visit Logs</span>
                            </a>
                            <a href="clinic_analytics.php" data-tooltip="Analytics">
                                <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                                <span class="nav-text">Analytics</span>
                            </a>
                        </div>
                    </div>
                <?php elseif ($isSscScholarshipService): ?>
                    <a href="<?= $isSscScholarshipService ? 'ssc_head_home.php' : htmlspecialchars($dashboardLink) ?>" data-tooltip="Dashboard">
                        <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="school_announcements.php?service=ssc/scholarship" data-tooltip="Announcements">
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
                            <a href="guidance_dashboard.php?service=ssc/scholarship" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php?service=ssc/scholarship" class="active" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php?service=ssc/scholarship" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc_head_home.php?service=ssc/scholarship" data-tooltip="SSC">
                                <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                                <span class="nav-text">SSC</span>
                            </a>
                            <a href="scholarship_dashboard.php?service=ssc/scholarship" data-tooltip="Scholarship">
                                <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                                <span class="nav-text">Scholarship</span>
                            </a>
                            <a href="ssaa_student_home.php?service=ssc/scholarship" data-tooltip="Alumni">
                                <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                                <span class="nav-text">Alumni</span>
                            </a>
                        </div>
                    </div>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage SSC">
                            <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                            <span class="nav-text">Manage SSC</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="ssc_manage_events.php" data-tooltip="SSC Events">
                                <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span>
                                <span class="nav-text">SSC Events</span>
                            </a>
                            <a href="ssc_manage_candidates.php" data-tooltip="Candidates">
                                <span class="nav-icon"><i class="fa-solid fa-user-group"></i></span>
                                <span class="nav-text">Candidates</span>
                            </a>
                            <a href="ssc_reports.php" data-tooltip="Reports & Analytics">
                                <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                                <span class="nav-text">Reports & Analytics</span>
                            </a>
                        </div>
                    </div>
                    <div class="nav-group">
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage Scholarship">
                            <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                            <span class="nav-text">Manage Scholarship</span>
                            <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="submenu" aria-hidden="true">
                            <a href="scholarship_create_announcement.php" data-tooltip="Scholarship Announcements">
                                <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                                <span class="nav-text">Scholarship Announcements</span>
                            </a>
                            <a href="scholarship_reports.php" data-tooltip="Reports & Analytics">
                                <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                                <span class="nav-text">Reports & Analytics</span>
                            </a>
                        </div>
                    </div>
                    <a href="profile.php?service=ssc/scholarship" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php?service=ssc/scholarship" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($dashboardLink) ?>" data-tooltip="Dashboard">
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
                            <a href="guidance_dashboard.php" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php" class="active" data-tooltip="Library">
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
                <?php endif; ?>
            </div>
            <div class="nav-footer">
                <a href="../logout.php" class="logout-link" data-tooltip="Logout">
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
                            <p>Library Services</p>
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
                    <!-- support button removed per request -->

                    <div class="topbar-menu" id="notificationMenu" role="menu" aria-label="Notifications menu">
                        <div class="menu-header">
                            <strong>Notifications</strong>
                            <span class="menu-note">Latest announcements</span>
                        </div>
                        <div class="menu-empty">No new announcements.</div>
                        <a class="menu-link menu-footer-link" href="about.php">View all announcements</a>
                    </div>

                    <div class="topbar-menu" id="profileMenu" role="menu" aria-label="Profile menu">
                        <div class="menu-item profile-menu-item" role="menuitem">
                            <a href="profile.php" class="menu-link profile-link"><?= htmlspecialchars($user['full_name']) ?></a>
                            <span class="menu-subtext">View your account details</span>
                        </div>
                        <a href="profile.php" class="menu-action">View profile</a>
                    </div>

                    <!-- support menu removed per request -->
                </div>
            </header>
            <div class="main-scroll">
                    <?php if (!empty($message)): ?>
                        <div class="dashboard-alert <?= $messageType === 'error' ? 'alert error' : 'alert success' ?>" style="margin-bottom: 20px;">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="service-page-header">
                        <div class="service-page-header-main">
                            <div class="service-page-header-icon"><i class="fa-solid fa-book" aria-hidden="true"></i></div>
                            <div>
                                <p class="service-page-header-eyebrow">Campus Portal</p>
                                <h1>Library Services</h1>
                                <p class="service-page-header-welcome">Welcome back, <strong><?= htmlspecialchars($user['full_name']) ?></strong></p>
                            </div>
                        </div>
                        <div class="service-page-header-center">
                            <i class="fa-solid fa-book-open-reader" aria-hidden="true"></i>
                            <div><strong>Explore library resources</strong><span>Search, reserve, and access materials for your academic journey.</span></div>
                        </div>
                        <div class="service-page-header-profile">
                            <strong><?= htmlspecialchars($roleLabel) ?></strong>
                            <div><?= htmlspecialchars($topbarInfo['displayMeta']) ?></div>
                        </div>
                    </div>

                    <section class="library-status-grid">
                        <div class="library-card status-card">
                            <span class="status-label">Borrowed books</span>
                            <strong><?= htmlspecialchars($borrowedCount) ?> / <?= htmlspecialchars($maxBorrowed) ?></strong>
                            <p>Current loans pending return or renewal.</p>
                        </div>
                        <div class="library-card status-card">
                            <span class="status-label">Reservations</span>
                            <strong><?= htmlspecialchars($reservationCount) ?> active</strong>
                            <p>Track reserved books and queue positions.</p>
                        </div>
                        <?php if ($user['role'] !== 'teacher'): ?>
                        <div class="library-card status-card">
                            <span class="status-label">Clearance Status</span>
                            <strong style="color: <?= $clearanceStatus['can_clear'] ? '#4CAF50' : '#f44336' ?>;">
                                <?= $clearanceStatus['can_clear'] ? '✓ Eligible' : '✗ Blocked' ?>
                            </strong>
                            <p>
                                <?php if (!$clearanceStatus['can_clear']): ?>
                                    <?php if ($clearanceStatus['unpaid_fines'] > 0): ?>
                                        ₱<?= number_format($clearanceStatus['unpaid_fines'], 2) ?> in unpaid fines
                                    <?php endif; ?>
                                    <?php if ($clearanceStatus['overdue_count'] > 0): ?>
                                        <?= $clearanceStatus['overdue_count'] ?> overdue book<?= $clearanceStatus['overdue_count'] !== 1 ? 's' : '' ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    You're eligible for clearance.
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        <div class="library-card status-card">
                            <span class="status-label">E-Resources</span>
                            <strong><?= htmlspecialchars($resourceCount) ?> available</strong>
                            <p>Access digital books, journals, and theses.</p>
                        </div>
                    </section>

                    <section class="library-tab-bar" role="tablist" aria-label="Library navigation">
                        <button type="button" class="library-tab-button active" data-tab="overview">Overview</button>
                        <button type="button" class="library-tab-button" data-tab="catalog">Catalog</button>
                        <button type="button" class="library-tab-button" data-tab="activity">My Activity</button>
                        <button type="button" class="library-tab-button" data-tab="reservations">Reservations</button>
                        <?php if ($user['role'] === 'teacher'): ?>
                            <button type="button" class="library-tab-button" data-tab="upload">Upload Resources</button>
                        <?php endif; ?>
                        <button type="button" class="library-tab-button" data-tab="resources">E-Resources</button>
                        <button type="button" class="library-tab-button" data-tab="open_access">Open Access Library</button>
                    </section>
                    <div class="library-tab-swipe-hint">Swipe left/right to switch sections on mobile.</div>

                    <section class="library-panel active" id="overview">
                        <div class="dashboard-summary-grid">
                            <div class="summary-card">
                                <span>Library rule</span>
                                <strong><?= htmlspecialchars($maxBorrowed) ?> books maximum</strong>
                            </div>
                            <div class="summary-card">
                                <span>Borrowing window</span>
                                <strong><?= htmlspecialchars($borrowDuration) ?> valid school days</strong>
                            </div>
                            <div class="summary-card">
                                <span>Reservation limit</span>
                                <strong><?= htmlspecialchars($maxReservations) ?> active reservations</strong>
                            </div>
                        </div>

                        <!-- Trending Books Carousel -->
                        <div style="margin-top: 40px;">
                            <div class="section-intro">
                                <span class="section-label">CURATED THIS MONTH</span>
                                <h2>Trending in the stacks</h2>
                                <p>Most frequently borrowed books this month</p>
                            </div>
                            <div class="catalog-carousel">
                            <button type="button" class="carousel-nav carousel-prev" aria-label="Previous book">‹</button>
                            <div class="catalog-track">
                                <?php
                                $pdo = get_db();
                                $stmt = $pdo->prepare('
                                    SELECT lb.id, lb.title, lb.author, lb.isbn, lb.lcc_letter_line, lb.lcc_class_number, lb.lcc_cutter_code, lb.lcc_published_year, lb.lcc_additional_info, lb.additional_info_type, lb.additional_info_quantity, lb.category, lb.status, lb.total_copies, lb.available_copies, lb.rating, lb.overview, lb.cover_url, COUNT(b.id) as borrow_count
                                    FROM library_books lb
                                    LEFT JOIN library_borrows b ON lb.id = b.book_id AND b.borrow_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                    WHERE lb.status = "Available"
                                    GROUP BY lb.id
                                    ORDER BY borrow_count DESC
                                    LIMIT 10
                                ');
                                $stmt->execute();
                                $trendingBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (empty($trendingBooks)):
                                ?>
                                    <p style="color: #64748b;">No trending books yet.</p>
                                <?php else: ?>
                                    <?php foreach ($trendingBooks as $book): 
                                        $reservationQueue = get_book_reservation_queue($book['id']);
                                        $userReservation = get_user_book_reservation($user['id'], $book['id']);
                                    ?>
                                        <article class="catalog-card catalog-book-card"
                                            data-book-id="<?= htmlspecialchars($book['id']) ?>"
                                            data-title="<?= htmlspecialchars($book['title']) ?>"
                                            data-author="<?= htmlspecialchars($book['author']) ?>"
                                            data-isbn="<?= htmlspecialchars($book['isbn'] ?? '') ?>"
                                            data-lcc-letter-line="<?= htmlspecialchars($book['lcc_letter_line'] ?? '') ?>"
                                            data-lcc-class-number="<?= htmlspecialchars($book['lcc_class_number'] ?? '') ?>"
                                            data-lcc-cutter-code="<?= htmlspecialchars($book['lcc_cutter_code'] ?? '') ?>"
                                            data-lcc-published-year="<?= htmlspecialchars($book['lcc_published_year'] ?? '') ?>"
                                            data-lcc-additional-info="<?= htmlspecialchars($book['lcc_additional_info'] ?? '') ?>"
                                            data-additional-info-type="<?= htmlspecialchars($book['additional_info_type'] ?? '') ?>"
                                            data-additional-info-quantity="<?= htmlspecialchars($book['additional_info_quantity'] ?? 0) ?>"
                                            data-category="<?= htmlspecialchars($book['category'] ?? '') ?>"
                                            data-status="<?= htmlspecialchars($book['status']) ?>"
                                            data-available-copies="<?= htmlspecialchars($book['available_copies']) ?>"
                                            data-total-copies="<?= htmlspecialchars($book['total_copies']) ?>"
                                            data-rating="<?= htmlspecialchars($book['rating'] ?? 0) ?>"
                                            data-overview="<?= htmlspecialchars($book['overview'] ?? '') ?>"
                                            data-cover-url="<?= htmlspecialchars(normalize_cover_url_for_dashboard($book['cover_url'] ?? '')) ?>"
                                            data-reservation-queue='<?= json_encode($reservationQueue, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_APOS) ?>'
                                            data-user-reservation='<?= json_encode($userReservation, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_APOS) ?>'
                                            role="button"
                                            tabindex="0"
                                        >
                                            <div class="catalog-cover">
                                                    <?php if (!empty($book['cover_url'])): ?>
                                                    <img src="<?= htmlspecialchars(normalize_cover_url_for_dashboard($book['cover_url'] ?? '')) ?>" alt="<?= htmlspecialchars($book['title']) ?>">
                                                <?php else: ?>
                                                    <div class="cover-placeholder">📖</div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="catalog-card-meta">
                                                <div class="catalog-meta-top">
                                                    <span class="catalog-callnumber"><?php $lccParts = array_filter([$book['lcc_letter_line'], $book['lcc_class_number'], $book['lcc_cutter_code'], $book['lcc_published_year'], $book['lcc_additional_info']]); echo htmlspecialchars(!empty($lccParts) ? implode(' ', $lccParts) : ($book['category'] ? $book['category'] : 'General Collection')); ?></span>
                                                    <span class="catalog-status <?= strtolower(str_replace(' ', '-', htmlspecialchars($book['status']))) ?>"><?= htmlspecialchars($book['status']) ?></span>
                                                </div>
                                                <h3><?= htmlspecialchars($book['title']) ?></h3>
                                                <p class="catalog-author"><?= htmlspecialchars($book['author'] ?? 'Unknown author') ?></p>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="carousel-nav carousel-next" aria-label="Next book">›</button>
                        </div>
                    </div>

                    <!-- Recently Returned Carousel -->
                    <div style="margin-top: 40px;">
                        <h2>Recently Returned</h2>
                        <p style="color: #64748b; margin-bottom: 16px;">Books returned in the past week</p>
                        <div class="catalog-carousel">
                            <button type="button" class="carousel-nav carousel-prev" aria-label="Previous book">‹</button>
                            <div class="catalog-track">
                                <?php
                                $stmt = $pdo->prepare('
                                    SELECT DISTINCT lb.id, lb.title, lb.author, lb.isbn, lb.lcc_letter_line, lb.lcc_class_number, lb.lcc_cutter_code, lb.lcc_published_year, lb.lcc_additional_info, lb.additional_info_type, lb.additional_info_quantity, lb.category, lb.status, lb.total_copies, lb.available_copies, lb.rating, lb.overview, lb.cover_url, b.returned_at
                                    FROM library_borrows b
                                    JOIN library_books lb ON b.book_id = lb.id
                                    WHERE b.status = "Returned" AND b.returned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                    ORDER BY b.returned_at DESC
                                    LIMIT 10
                                ');
                                $stmt->execute();
                                $recentlyReturned = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (empty($recentlyReturned)):
                                ?>
                                    <p style="color: #64748b;">No recently returned books.</p>
                                <?php else: ?>
                                    <?php foreach ($recentlyReturned as $book): 
                                        $reservationQueue = get_book_reservation_queue($book['id']);
                                        $userReservation = get_user_book_reservation($user['id'], $book['id']);
                                    ?>
                                        <article class="catalog-card catalog-book-card"
                                            data-book-id="<?= htmlspecialchars($book['id']) ?>"
                                            data-title="<?= htmlspecialchars($book['title']) ?>"
                                            data-author="<?= htmlspecialchars($book['author']) ?>"
                                            data-isbn="<?= htmlspecialchars($book['isbn'] ?? '') ?>"
                                            data-lcc-letter-line="<?= htmlspecialchars($book['lcc_letter_line'] ?? '') ?>"
                                            data-lcc-class-number="<?= htmlspecialchars($book['lcc_class_number'] ?? '') ?>"
                                            data-lcc-cutter-code="<?= htmlspecialchars($book['lcc_cutter_code'] ?? '') ?>"
                                            data-lcc-published-year="<?= htmlspecialchars($book['lcc_published_year'] ?? '') ?>"
                                            data-lcc-additional-info="<?= htmlspecialchars($book['lcc_additional_info'] ?? '') ?>"
                                            data-additional-info-type="<?= htmlspecialchars($book['additional_info_type'] ?? '') ?>"
                                            data-additional-info-quantity="<?= htmlspecialchars($book['additional_info_quantity'] ?? 0) ?>"
                                            data-category="<?= htmlspecialchars($book['category'] ?? '') ?>"
                                            data-status="<?= htmlspecialchars($book['status']) ?>"
                                            data-available-copies="<?= htmlspecialchars($book['available_copies']) ?>"
                                            data-total-copies="<?= htmlspecialchars($book['total_copies']) ?>"
                                            data-rating="<?= htmlspecialchars($book['rating'] ?? 0) ?>"
                                            data-overview="<?= htmlspecialchars($book['overview'] ?? '') ?>"
                                            data-cover-url="<?= htmlspecialchars(normalize_cover_url_for_dashboard($book['cover_url'] ?? '')) ?>"
                                            data-reservation-queue='<?= json_encode($reservationQueue, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_APOS) ?>'
                                            data-user-reservation='<?= json_encode($userReservation, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_APOS) ?>'
                                            role="button"
                                            tabindex="0"
                                        >
                                            <div class="catalog-cover">
                                                    <?php if (!empty($book['cover_url'])): ?>
                                                    <img src="<?= htmlspecialchars(normalize_cover_url_for_dashboard($book['cover_url'] ?? '')) ?>" alt="<?= htmlspecialchars($book['title']) ?>">
                                                <?php else: ?>
                                                    <div class="cover-placeholder"><i class="fa-solid fa-book"></i></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="catalog-card-meta">
                                                <div class="catalog-meta-top">
                                                    <span class="catalog-callnumber"><?= htmlspecialchars($book['isbn'] ? 'ISBN '.$book['isbn'] : ($book['category'] ? $book['category'] : 'General Collection')) ?></span>
                                                    <span class="catalog-status <?= strtolower(str_replace(' ', '-', htmlspecialchars($book['status']))) ?>"><?= htmlspecialchars($book['status']) ?></span>
                                                </div>
                                                <h3><?= htmlspecialchars($book['title']) ?></h3>
                                                <p class="catalog-author"><?= htmlspecialchars($book['author'] ?? 'Unknown author') ?></p>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="carousel-nav carousel-next" aria-label="Next book">›</button>
                        </div>
                    </div>

                    <!-- New Books Carousel -->
                    <div style="margin-top: 40px;">
                        <h2>New Books</h2>
                        <p style="color: #64748b; margin-bottom: 16px;">Recently added to the library collection</p>
                        <div class="catalog-carousel">
                            <button type="button" class="carousel-nav carousel-prev" aria-label="Previous book">‹</button>
                            <div class="catalog-track">
                                <?php
                                $stmt = $pdo->prepare('
                                    SELECT id, title, author, isbn, lcc_letter_line, lcc_class_number, lcc_cutter_code, lcc_published_year, lcc_additional_info, additional_info_type, additional_info_quantity, category, status, total_copies, available_copies, rating, overview, cover_url, created_at
                                    FROM library_books
                                    WHERE status = "Available"
                                    ORDER BY created_at DESC
                                    LIMIT 10
                                ');
                                $stmt->execute();
                                $newBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (empty($newBooks)):
                                ?>
                                    <p style="color: #64748b;">No new books yet.</p>
                                <?php else: ?>
                                    <?php foreach ($newBooks as $book): 
                                        $reservationQueue = get_book_reservation_queue($book['id']);
                                        $userReservation = get_user_book_reservation($user['id'], $book['id']);
                                    ?>
                                        <article class="catalog-card catalog-book-card"
                                            data-book-id="<?= htmlspecialchars($book['id']) ?>"
                                            data-title="<?= htmlspecialchars($book['title']) ?>"
                                            data-author="<?= htmlspecialchars($book['author']) ?>"
                                            data-isbn="<?= htmlspecialchars($book['isbn'] ?? '') ?>"
                                            data-lcc-letter-line="<?= htmlspecialchars($book['lcc_letter_line'] ?? '') ?>"
                                            data-lcc-class-number="<?= htmlspecialchars($book['lcc_class_number'] ?? '') ?>"
                                            data-lcc-cutter-code="<?= htmlspecialchars($book['lcc_cutter_code'] ?? '') ?>"
                                            data-lcc-published-year="<?= htmlspecialchars($book['lcc_published_year'] ?? '') ?>"
                                            data-lcc-additional-info="<?= htmlspecialchars($book['lcc_additional_info'] ?? '') ?>"
                                            data-additional-info-type="<?= htmlspecialchars($book['additional_info_type'] ?? '') ?>"
                                            data-additional-info-quantity="<?= htmlspecialchars($book['additional_info_quantity'] ?? 0) ?>"
                                            data-category="<?= htmlspecialchars($book['category'] ?? '') ?>"
                                            data-status="<?= htmlspecialchars($book['status']) ?>"
                                            data-available-copies="<?= htmlspecialchars($book['available_copies']) ?>"
                                            data-total-copies="<?= htmlspecialchars($book['total_copies']) ?>"
                                            data-rating="<?= htmlspecialchars($book['rating'] ?? 0) ?>"
                                            data-overview="<?= htmlspecialchars($book['overview'] ?? '') ?>"
                                            data-cover-url="<?= htmlspecialchars(normalize_cover_url_for_dashboard($book['cover_url'] ?? '')) ?>"
                                            data-reservation-queue='<?= json_encode($reservationQueue, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_APOS) ?>'
                                            data-user-reservation='<?= json_encode($userReservation, JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_APOS) ?>'
                                            role="button"
                                            tabindex="0"
                                        >
                                            <div class="catalog-cover<?= $book['status'] === 'Reserved' ? ' cover-alt' : '' ?>">
                                                    <?php if (!empty($book['cover_url'])): ?>
                                                    <img src="<?= htmlspecialchars(normalize_cover_url_for_dashboard($book['cover_url'] ?? '')) ?>" alt="<?= htmlspecialchars($book['title']) ?>">
                                                <?php else: ?>
                                                    <div class="cover-placeholder" style="display: flex; align-items: center; justify-content: center; background: #e2e8f0; font-size: 48px; color: #94a3b8;">📖</div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="catalog-card-meta">
                                                <div class="catalog-meta-top">
                                                    <span class="catalog-callnumber"><?= htmlspecialchars($book['isbn'] ? 'ISBN '.$book['isbn'] : ($book['category'] ? $book['category'] : 'General Collection')) ?></span>
                                                    <span class="catalog-status <?= strtolower(str_replace(' ', '-', htmlspecialchars($book['status']))) ?>"><?= htmlspecialchars($book['status']) ?></span>
                                                </div>
                                                <h3><?= htmlspecialchars($book['title']) ?></h3>
                                                <p class="catalog-author"><?= htmlspecialchars($book['author'] ?? 'Unknown author') ?></p>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="carousel-nav carousel-next" aria-label="Next book">›</button>
                        </div>
                    </div>
                    </section>

                    <section class="library-panel" id="catalog">
                        <div class="catalog-search-row">
                            <input id="catalog-search-input" type="search" placeholder="Search by title, author, ISBN or category" aria-label="Search catalog">
                            <select id="catalog-category-filter" aria-label="Filter category">
                                <option value="">All categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option><?= htmlspecialchars($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="catalog-grid" id="catalog-books-container">
                                <?php if (count($catalogBooks) === 0): ?>
                                    <div class="library-card overview-card">
                                        <p>No books are available in the catalog right now.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($catalogBooks as $book): ?>
                                        <article class="catalog-card catalog-book-card"
                                            data-book-id="<?= htmlspecialchars($book['id']) ?>"
                                            data-title="<?= htmlspecialchars($book['title']) ?>"
                                            data-author="<?= htmlspecialchars($book['author']) ?>"
                                            data-isbn="<?= htmlspecialchars($book['isbn']) ?>"
                                            data-lcc-letter-line="<?= htmlspecialchars($book['lcc_letter_line'] ?? '') ?>"
                                            data-lcc-class-number="<?= htmlspecialchars($book['lcc_class_number'] ?? '') ?>"
                                            data-lcc-cutter-code="<?= htmlspecialchars($book['lcc_cutter_code'] ?? '') ?>"
                                            data-lcc-published-year="<?= htmlspecialchars($book['lcc_published_year'] ?? '') ?>"
                                            data-lcc-additional-info="<?= htmlspecialchars($book['lcc_additional_info'] ?? '') ?>"
                                            data-additional-info-type="<?= htmlspecialchars($book['additional_info_type'] ?? '') ?>"
                                            data-additional-info-quantity="<?= htmlspecialchars($book['additional_info_quantity'] ?? 0) ?>"
                                            data-category="<?= htmlspecialchars($book['category']) ?>"
                                            data-status="<?= htmlspecialchars($book['status']) ?>"
                                            data-available-copies="<?= htmlspecialchars($book['available_copies']) ?>"
                                            data-total-copies="<?= htmlspecialchars($book['total_copies']) ?>"
                                            data-rating="<?= htmlspecialchars($book['rating']) ?>"
                                            data-overview="<?= htmlspecialchars($book['overview']) ?>"
                                            data-cover-url="<?= htmlspecialchars(normalize_cover_url_for_dashboard($book['cover_url'] ?? '')) ?>"
                                            data-reservation-queue='<?= json_encode($book['reservationQueue'], JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_APOS) ?>'
                                            data-user-reservation='<?= json_encode($book['userReservation'], JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_APOS) ?>'
                                            role="button"
                                            tabindex="0"
                                        >
                                            <div class="catalog-cover<?= $book['status'] === 'Reserved' ? ' cover-alt' : '' ?>">
                                                    <?php if (!empty($book['cover_url'])): ?>
                                                    <img src="<?= htmlspecialchars(normalize_cover_url_for_dashboard($book['cover_url'] ?? '')) ?>" alt="<?= htmlspecialchars($book['title']) ?>">
                                                <?php else: ?>
                                                    <div class="cover-placeholder"><i class="fa-solid fa-book"></i></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="catalog-card-meta">
                                                <div class="catalog-meta-top">
                                                    <span class="catalog-callnumber"><?= htmlspecialchars($book['isbn'] ? 'ISBN '.$book['isbn'] : ($book['category'] ? $book['category'] : 'General Collection')) ?></span>
                                                    <span class="catalog-status <?= strtolower(str_replace(' ', '-', htmlspecialchars($book['status']))) ?>"><?= htmlspecialchars($book['status']) ?></span>
                                                </div>
                                                <h3><?= htmlspecialchars($book['title']) ?></h3>
                                                <p class="catalog-author"><?= htmlspecialchars($book['author'] ?? 'Unknown author') ?></p>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                        </div>
                        <div class="catalog-pagination" id="catalog-pagination" aria-label="Catalog pages"></div>
                    </section>

                    <section class="library-panel" id="activity">
                        <div class="library-table-card">
                            <h3>Borrowed books</h3>
                            <div class="table-responsive">
                                <table class="borrowed-table">
                                    <thead>
                                        <tr>
                                            <th>Book</th>
                                            <th>Status</th>
                                            <th>Due date</th>
                                            <th>Days left</th>
                                            <th>Returned date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($activeBorrowedBooks) === 0): ?>
                                            <tr><td colspan="5">No borrowed books yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($activeBorrowedBooks as $borrow): ?>
                                                <tr>
                                                    <td data-label="Book"><?= htmlspecialchars($borrow['title']) ?></td>
                                                    <td data-label="Status"><span class="status-pill status-<?= strtolower(htmlspecialchars($borrow['borrow_status'])) ?>"><?= htmlspecialchars($borrow['borrow_status']) ?></span></td>
                                                    <td data-label="Due date"><?= htmlspecialchars($borrow['due_date'] ? (new DateTime($borrow['due_date']))->format('M d, Y') : '—') ?></td>
                                                    <td data-label="Days left">
                                                    <?php
                                                    $dueDate = new DateTime($borrow['due_date']);
                                                    $now = new DateTime();
                                                    if ($borrow['borrow_status'] === 'Returned') {
                                                        echo '—';
                                                    } elseif ($dueDate < $now) {
                                                        $overdueDays = $now->diff($dueDate)->days;
                                                        echo $overdueDays . ' school days overdue';
                                                    } else {
                                                        $daysLeft = $now->diff($dueDate)->days;
                                                        echo $daysLeft . ' school days left';
                                                    }
                                                    ?>
                                                </td>
                                                <td data-label="Returned date"><?= htmlspecialchars($borrow['returned_at'] ? (new DateTime($borrow['returned_at']))->format('M d, Y') : '—') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="library-table-card">
                            <h3>Activity history</h3>
                            <?php if (empty($reservations) && empty($recentReturnedBooks) && empty($cancelledReservations)): ?>
                                <p class="empty-history">No recent library activity recorded yet.</p>
                            <?php else: ?>
                                <?php if (!empty($recentReturnedBooks)): ?>
                                    <div class="activity-history-section">
                                        <h4>Returned books</h4>
                                        <div class="table-responsive">
                                            <table class="borrowed-table">
                                                <thead>
                                                    <tr>
                                                        <th>Book</th>
                                                        <th>Status</th>
                                                        <th>Due date</th>
                                                        <th>Returned date</th>
                                                        <th>Feedback</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recentReturnedBooks as $borrow): ?>
                                                        <tr>
                                                            <td data-label="Book"><?= htmlspecialchars($borrow['title']) ?></td>
                                                            <td data-label="Status"><span class="status-pill status-returned">Returned</span></td>
                                                            <td data-label="Due date"><?= htmlspecialchars($borrow['due_date'] ? (new DateTime($borrow['due_date']))->format('M d, Y') : '—') ?></td>
                                                            <td data-label="Returned date"><?= htmlspecialchars($borrow['returned_at'] ? (new DateTime($borrow['returned_at']))->format('M d, Y') : '—') ?></td>
                                                            <td data-label="Feedback">
                                                            <?php
                                                                $fbCheck = $pdo->prepare('SELECT id, book_rating, service_rating FROM library_feedback WHERE borrow_id = :borrow_id LIMIT 1');
                                                                $fbCheck->execute(['borrow_id' => $borrow['id']]);
                                                                $existingFeedback = $fbCheck->fetch(PDO::FETCH_ASSOC);
                                                                if ($existingFeedback):
                                                            ?>
                                                                <span class="status-pill" style="background:#d4fc79;color:#0f172a;font-size:11px;">Book: ★<?= (int)$existingFeedback['book_rating'] ?> | Service: ★<?= (int)$existingFeedback['service_rating'] ?></span>
                                                            <?php else: ?>
                                                                <button class="btn-review" onclick="openFeedbackModal(<?= (int)($borrow['id'] ?? 0) ?>, <?= (int)($borrow['book_id'] ?? 0) ?>)" style="padding:6px 12px;font-size:12px;border:1px solid #cbd5e1;background:#fff;border-radius:4px;cursor:pointer;">Review</button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Feedback Modal -->
                                    <div id="feedbackModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.55);z-index:1000;justify-content:center;align-items:center;padding:24px;">
                                        <div style="background:#ffffff;border-radius:24px;padding:32px;max-width:580px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 30px 80px rgba(15,23,42,0.18);border:1px solid rgba(226,232,240,0.8);">
                                            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:24px;">
                                                <div>
                                                    <h3 style="margin:0 0 8px 0;font-size:22px;color:#0f172a;letter-spacing:-0.02em;">Service and Book Review</h3>
                                                    <p style="margin:0;color:#64748b;font-size:14px;max-width:500px;line-height:1.6;">Share a quick rating for the book and the service with a short review. All fields are optional except ratings.</p>
                                                </div>
                                                <button type="button" onclick="closeFeedbackModal()" style="background:transparent;border:none;color:#475569;font-size:20px;cursor:pointer;line-height:1;">×</button>
                                            </div>
                                            <form id="feedbackForm" onsubmit="submitStudentFeedback(event)" style="display:grid;gap:22px;">
                                                <input type="hidden" id="feedbackBorrowId" name="borrow_id">
                                                <input type="hidden" id="feedbackBookId" name="book_id">
                                                <input type="hidden" id="feedbackBookRating" name="book_rating" value="0">
                                                <input type="hidden" id="feedbackServiceRating" name="service_rating" value="0">

                                                <div style="display:grid;gap:18px;">
                                                    <div style="padding:20px;border:1px solid #e2e8f0;border-radius:18px;background:#f8fafc;">
                                                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;">
                                                            <div>
                                                                <p style="margin:0 0 6px 0;font-size:13px;font-weight:700;color:#334155;letter-spacing:0.02em;">Book Rating</p>
                                                                <p id="bookRatingText" style="margin:0;color:#475569;font-size:13px;">Click the stars to rate this book.</p>
                                                            </div>
                                                            <span id="bookRatingScore" style="font-size:14px;font-weight:700;color:#0f172a;">0/5</span>
                                                        </div>
                                                        <div id="bookRatingStars" class="feedback-stars" style="display:flex;gap:10px;">
                                                            <button type="button" class="star" data-value="1" onclick="setBookRating(1)" aria-label="1 star">★</button>
                                                            <button type="button" class="star" data-value="2" onclick="setBookRating(2)" aria-label="2 stars">★</button>
                                                            <button type="button" class="star" data-value="3" onclick="setBookRating(3)" aria-label="3 stars">★</button>
                                                            <button type="button" class="star" data-value="4" onclick="setBookRating(4)" aria-label="4 stars">★</button>
                                                            <button type="button" class="star" data-value="5" onclick="setBookRating(5)" aria-label="5 stars">★</button>
                                                        </div>
                                                    </div>

                                                    <div style="padding:20px;border:1px solid #e2e8f0;border-radius:18px;background:#f8fafc;">
                                                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px;">
                                                            <div>
                                                                <p style="margin:0 0 6px 0;font-size:13px;font-weight:700;color:#334155;letter-spacing:0.02em;">Service Rating</p>
                                                                <p id="serviceRatingText" style="margin:0;color:#475569;font-size:13px;">Click the stars to rate the service.</p>
                                                            </div>
                                                            <span id="serviceRatingScore" style="font-size:14px;font-weight:700;color:#0f172a;">0/5</span>
                                                        </div>
                                                        <div id="serviceRatingStars" class="feedback-stars" style="display:flex;gap:10px;">
                                                            <button type="button" class="star" data-value="1" onclick="setServiceRating(1)" aria-label="1 star">★</button>
                                                            <button type="button" class="star" data-value="2" onclick="setServiceRating(2)" aria-label="2 stars">★</button>
                                                            <button type="button" class="star" data-value="3" onclick="setServiceRating(3)" aria-label="3 stars">★</button>
                                                            <button type="button" class="star" data-value="4" onclick="setServiceRating(4)" aria-label="4 stars">★</button>
                                                            <button type="button" class="star" data-value="5" onclick="setServiceRating(5)" aria-label="5 stars">★</button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div style="display:grid;gap:18px;">
                                                    <div>
                                                        <label style="display:block;margin-bottom:10px;font-size:13px;font-weight:700;color:#334155;">Book Review</label>
                                                        <textarea id="feedbackReview" name="book_review" placeholder="Share your thoughts about the book..." class="feedback-textarea"></textarea>
                                                    </div>
                                                    <div>
                                                        <label style="display:block;margin-bottom:10px;font-size:13px;font-weight:700;color:#334155;">Service Feedback</label>
                                                        <textarea id="feedbackService" name="service_feedback" placeholder="Describe the service experience..." class="feedback-textarea"></textarea>
                                                    </div>
                                                </div>

                                                <div style="display:flex;justify-content:flex-end;gap:12px;flex-wrap:wrap;">
                                                    <button type="button" onclick="closeFeedbackModal()" class="feedback-button feedback-button-secondary">Cancel</button>
                                                    <button type="submit" class="feedback-button feedback-button-primary">Submit Review</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <style>
                                        .feedback-stars .star {
                                            width: 44px;
                                            height: 44px;
                                            border: 1px solid #d1d5db;
                                            border-radius: 14px;
                                            background: #ffffff;
                                            color: #cbd5e1;
                                            cursor: pointer;
                                            transition: all 0.2s ease;
                                            display: inline-flex;
                                            align-items: center;
                                            justify-content: center;
                                            font-size: 20px;
                                            line-height: 1;
                                            font-weight: 700;
                                        }
                                        .feedback-stars .star:hover {
                                            border-color: #a1a1aa;
                                            color: #fbbf24;
                                            transform: translateY(-1px);
                                        }
                                        .feedback-stars .star.active {
                                            border-color: #fbbf24;
                                            background: rgba(245, 158, 11, 0.14);
                                            color: #f59e0b;
                                        }
                                        .feedback-textarea {
                                            width: 100%;
                                            min-height: 110px;
                                            padding: 16px;
                                            border: 1px solid #e2e8f0;
                                            border-radius: 16px;
                                            background: #f8fafc;
                                            color: #111827;
                                            font-size: 14px;
                                            line-height: 1.7;
                                            resize: vertical;
                                            font-family: inherit;
                                        }
                                        .feedback-button {
                                            min-width: 140px;
                                            padding: 12px 18px;
                                            border-radius: 14px;
                                            font-weight: 700;
                                            border: 1px solid transparent;
                                            cursor: pointer;
                                            transition: transform 0.2s ease, box-shadow 0.2s ease;
                                        }
                                        .feedback-button:hover { transform: translateY(-1px); }
                                        .feedback-button-primary {
                                            background: #0f172a;
                                            color: #ffffff;
                                        }
                                        .feedback-button-secondary {
                                            background: #ffffff;
                                            color: #334155;
                                            border-color: #cbd5e1;
                                        }
                                        @media (max-width: 420px) {
                                            #feedbackModal {
                                                padding: 12px;
                                                align-items: flex-start;
                                            }
                                            #feedbackModal > div {
                                                max-width: 100%;
                                                padding: 20px;
                                                border-radius: 18px;
                                                max-height: calc(100vh - 24px);
                                            }
                                            #feedbackModal h3 {
                                                font-size: 18px;
                                            }
                                            #feedbackModal p {
                                                font-size: 13px;
                                                max-width: 100%;
                                            }
                                            .feedback-stars {
                                                display: flex !important;
                                                flex-wrap: wrap;
                                                gap: 8px !important;
                                            }
                                            .feedback-stars .star {
                                                width: 38px;
                                                height: 38px;
                                                font-size: 18px;
                                            }
                                            .feedback-textarea {
                                                min-height: 100px;
                                                padding: 14px;
                                            }
                                            .feedback-button {
                                                min-width: 0;
                                                width: 100%;
                                                flex: 1 1 100%;
                                            }
                                            #feedbackModal form {
                                                gap: 16px;
                                            }
                                            #feedbackModal > div > div {
                                                gap: 12px;
                                            }
                                        }
                                        @media (max-width: 360px) {
                                            #feedbackModal > div {
                                                padding: 16px;
                                            }
                                            #feedbackModal h3 {
                                                font-size: 16px;
                                            }
                                            .feedback-button {
                                                padding: 10px 14px;
                                                font-size: 13px;
                                            }
                                        }
                                    </style>

                                    <script>
                                        let bookRatingValue = 0;
                                        let serviceRatingValue = 0;
                                        
                                        function setBookRating(value) {
                                            bookRatingValue = value;
                                            document.getElementById('feedbackBookRating').value = value;
                                            updateBookStars(value);
                                            const labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                                            document.getElementById('bookRatingText').textContent = labels[value] + ' (' + value + '/5)';
                                            document.getElementById('bookRatingScore').textContent = value + '/5';
                                        }
                                        
                                        function setServiceRating(value) {
                                            serviceRatingValue = value;
                                            document.getElementById('feedbackServiceRating').value = value;
                                            updateServiceStars(value);
                                            const labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                                            document.getElementById('serviceRatingText').textContent = labels[value] + ' (' + value + '/5)';
                                            document.getElementById('serviceRatingScore').textContent = value + '/5';
                                        }
                                        
                                        function updateBookStars(rating) {
                                            document.querySelectorAll('#bookRatingStars .star').forEach((star, idx) => {
                                                if (idx + 1 <= rating) {
                                                    star.classList.add('active');
                                                } else {
                                                    star.classList.remove('active');
                                                }
                                            });
                                        }
                                        
                                        function updateServiceStars(rating) {
                                            document.querySelectorAll('#serviceRatingStars .star').forEach((star, idx) => {
                                                if (idx + 1 <= rating) {
                                                    star.classList.add('active');
                                                } else {
                                                    star.classList.remove('active');
                                                }
                                            });
                                        }
                                        
                                        function openFeedbackModal(borrowId, bookId) {
                                            document.getElementById('feedbackBorrowId').value = borrowId;
                                            document.getElementById('feedbackBookId').value = bookId;
                                            bookRatingValue = 0;
                                            serviceRatingValue = 0;
                                            document.getElementById('feedbackForm').reset();
                                            document.getElementById('feedbackBookRating').value = 0;
                                            document.getElementById('feedbackServiceRating').value = 0;
                                            updateBookStars(0);
                                            updateServiceStars(0);
                                            document.getElementById('bookRatingText').textContent = 'Click to rate the book';
                                            document.getElementById('serviceRatingText').textContent = 'Click to rate the service';
                                            document.getElementById('bookRatingScore').textContent = '0/5';
                                            document.getElementById('serviceRatingScore').textContent = '0/5';
                                            document.getElementById('feedbackModal').style.display = 'flex';
                                        }
                                        
                                        function closeFeedbackModal() {
                                            document.getElementById('feedbackModal').style.display = 'none';
                                            document.getElementById('feedbackForm').reset();
                                            bookRatingValue = 0;
                                            serviceRatingValue = 0;
                                        }
                                        
                                        function submitStudentFeedback(event) {
                                            event.preventDefault();
                                            
                                            if (bookRatingValue === 0 || serviceRatingValue === 0) {
                                                alert('Please rate both the book and service.');
                                                return;
                                            }
                                            
                                            const formData = new FormData(document.getElementById('feedbackForm'));
                                            formData.append('action', 'save_feedback');
                                            
                                            fetch(window.location.href, {
                                                method: 'POST',
                                                body: formData
                                            })
                                            .then(response => response.text())
                                            .then(() => {
                                                alert('Thank you for your feedback!');
                                                closeFeedbackModal();
                                                location.reload();
                                            })
                                            .catch(err => {
                                                alert('Error submitting feedback. Please try again.');
                                                console.error(err);
                                            });
                                        }
                                    </script>
                                <?php endif; ?>

                                <?php if (!empty($cancelledReservations)): ?>
                                    <div class="activity-history-section" style="margin-top: 24px;">
                                        <h4>Cancelled reservations</h4>
                                        <table class="borrowed-table">
                                            <thead>
                                                <tr>
                                                    <th>Book</th>
                                                    <th>Queue</th>
                                                    <th>Reserved date</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($cancelledReservations as $reservation): ?>
                                                    <tr>
                                                        <td data-label="Book"><?= htmlspecialchars($reservation['title']) ?></td>
                                                        <td data-label="Queue"><?= htmlspecialchars($reservation['queue_position']) ?></td>
                                                        <td data-label="Reserved date"><?= htmlspecialchars($reservation['reserved_date'] ? (new DateTime($reservation['reserved_date']))->format('M d, Y') : '—') ?></td>
                                                        <td data-label="Status"><span class="status-pill status-cancelled">Cancelled</span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($reservations)): ?>
                                    <div class="activity-history-section" style="margin-top: 24px;">
                                        <h4>Active reservations</h4>
                                        <ul class="activity-history-list">
                                            <?php foreach ($reservations as $reservation): ?>
                                                <li class="activity-history-item">
                                                    <p class="activity-history-title">Reserved "<?= htmlspecialchars($reservation['title']) ?>"<?= !empty($reservation['additional_info']) ? ' — ' . htmlspecialchars($reservation['additional_info']) : '' ?></p>
                                                    <p class="activity-history-meta">Queue position <?= htmlspecialchars($reservation['queue_position']) ?> · Reserved on <?= htmlspecialchars((new DateTime($reservation['reserved_date']))->format('M d, Y')) ?></p>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="library-panel" id="reservations">
                        <div class="library-card">
                            <h3>Reservation queue</h3>
                            <p>You have <?= htmlspecialchars($reservationCount) ?> active reservation<?= $reservationCount === 1 ? '' : 's' ?>.</p>
                            <?php if ($reservationCount > 0): ?>
                                <ul class="reservation-list">
                                    <?php foreach ($reservations as $reservation): ?>
                                        <li class="reservation-item">
                                            <div class="reservation-item-header">
                                                <div>
                                                    <p class="reservation-item-title"><?= htmlspecialchars($reservation['title']) ?><?= !empty($reservation['additional_info']) ? ' — ' . htmlspecialchars($reservation['additional_info']) : '' ?></p>
                                                    <p class="reservation-item-meta">Queue position <?= htmlspecialchars($reservation['queue_position']) ?> · Reserved on <?= htmlspecialchars((new DateTime($reservation['reserved_date']))->format('M d, Y')) ?></p>
                                                </div>
                                                <span class="reservation-item-status <?= strtolower(htmlspecialchars($reservation['status'])) ?>"><?= htmlspecialchars($reservation['status']) ?></span>
                                            </div>
                                            <div class="reservation-item-footer">
                                                <span class="reservation-item-date">Active on <?= htmlspecialchars((new DateTime($reservation['reserved_date']))->format('M d, Y')) ?></span>
                                                <form method="post" class="inline-form">
                                                    <input type="hidden" name="action" value="cancel_reservation">
                                                    <input type="hidden" name="reservation_id" value="<?= htmlspecialchars($reservation['id']) ?>">
                                                    <button type="submit" class="cancel-reservation-button">Cancel reservation</button>
                                                </form>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                    </section>

                    <?php if ($user['role'] === 'teacher'): ?>
                    <section class="library-panel" id="upload">
                        <h2>Upload New E-Resource</h2>
                        <p style="color: #64748b; margin-bottom: 24px;">Share educational materials with other teachers and students. All uploads require librarian approval before publication.</p>
                        
                        <div class="library-card" style="margin-bottom: 30px;">
                            <form id="upload-resource-form" enctype="multipart/form-data" style="display: grid; gap: 16px;">
                                <div>
                                    <label for="resource-title" style="display: block; margin-bottom: 6px; font-weight: 500;">Resource Title *</label>
                                    <input type="text" id="resource-title" name="title" required placeholder="e.g., Data Structures Module" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
                                </div>

                                <div>
                                    <label for="resource-description" style="display: block; margin-bottom: 6px; font-weight: 500;">Description *</label>
                                    <textarea id="resource-description" name="description" required rows="4" placeholder="Describe the content and key topics covered..." style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit;"></textarea>
                                </div>

                                <div>
                                    <label for="resource-file" style="display: block; margin-bottom: 6px; font-weight: 500;">Upload File (PDF, DOC, PPT, etc.) *</label>
                                    <div class="file-upload-area" style="border: 2px dashed #cbd5e1; border-radius: 8px; padding: 24px; text-align: center; cursor: pointer;">
                                        <input type="file" id="resource-file" name="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.txt" style="display: none;">
                                        <p style="margin: 0; color: #64748b;">📄 Drag and drop your file or click to browse</p>
                                        <p style="margin: 6px 0 0 0; font-size: 12px; color: #94a3b8;">Max 50 MB • Formats: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, ZIP, TXT</p>
                                        <p id="file-name" style="margin-top: 12px; font-weight: 500; color: #059669; display: none;"></p>
                                    </div>
                                </div>

                                <div class="responsive-grid-2">
                                    <button type="submit" class="primary-button" style="width: 100%;">📤 Submit for Approval</button>
                                    <button type="reset" class="secondary-button" style="width: 100%;">Clear Form</button>
                                </div>
                            </form>
                        </div>

                        <h3 style="margin-bottom: 16px;">Your Uploaded Resources</h3>
                        <p style="color: #64748b; margin-bottom: 16px;">Track the status of your uploaded materials</p>
                        
                        <?php if (empty($userUploadedResources)): ?>
                            <div style="text-align: center; padding: 30px; background: #f8fafc; border-radius: 10px;">
                                <p style="color: #94a3b8; margin: 0;">No resources uploaded yet</p>
                            </div>
                        <?php else: ?>
                            <div style="display: grid; gap: 12px;">
                                <?php foreach ($userUploadedResources as $resource): ?>
                                    <div class="responsive-grid-2-auto" style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px;">
                                        <div>
                                            <h4 style="margin: 0 0 6px 0; font-size: 14px; color: #111827;"><?= htmlspecialchars($resource['title']) ?></h4>
                                            <p style="margin: 0; font-size: 12px; color: #64748b;">📄 <?= htmlspecialchars($resource['file_type'] ?? 'File') ?> • <?= date('M d, Y', strtotime($resource['uploaded_at'] ?? 'now')) ?></p>
                                        </div>
                                        <div style="text-align: right;">
                                            <?php if ($resource['is_approved']): ?>
                                                <span style="background: #dcfce7; color: #166534; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-block;">✓ Approved</span>
                                                <?php if (!empty($resource['category'])): ?>
                                                    <p style="margin: 6px 0 0 0; font-size: 11px; color: #64748b;">Category: <?= htmlspecialchars($resource['category']) ?></p>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="background: #fef3c7; color: #92400e; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; display: inline-block;">⏳ Pending Review</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>

                    <section class="library-panel" id="resources">
                        <h2>E-Resource Hub</h2>
                        <p style="color: #64748b; margin-bottom: 24px;">Access approved digital resources, modules, and educational materials</p>

                        <div style="background: #eff6ff; border-left: 4px solid #2563eb; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; color: #1e40af;">
                            <strong>ℹ️ Course-Specific Resources:</strong> Resources are filtered based on your course. You see E-Resources relevant to <?= htmlspecialchars(get_user_course_category($user['id']) ?? 'your course') ?>.
                        </div>

                        <div class="resource-filter-row" style="margin-bottom: 20px;">
                            <input type="search" id="resource-search" placeholder="Search resources by title or category..." style="padding: 10px 16px; border: 1px solid #cbd5e1; border-radius: 6px; width: 100%; max-width: 400px;">
                            <select id="resource-category-filter" style="padding: 10px 16px; border: 1px solid #cbd5e1; border-radius: 6px; width: 100%; max-width: 260px; background: #ffffff;">
                                <option value="">All Categories</option>
                                <?php foreach ($resourceCategories as $category): ?>
                                    <option value="<?= htmlspecialchars(strtolower($category)) ?>"><?= htmlspecialchars($category) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="resources-grid" id="resources-grid">
                            <?php if (empty($availableResources)): ?>
                                <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                                    <p style="color: #94a3b8; font-size: 16px;">No approved resources yet</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($availableResources as $resource): ?>
                                    <div class="resource-card" data-title="<?= htmlspecialchars(strtolower($resource['title'])) ?>" data-category="<?= htmlspecialchars(strtolower($resource['category'] ?? '')) ?>">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                            <h4 style="margin: 0; flex: 1; font-size: 15px; color: #1e293b;"><?= htmlspecialchars($resource['title']) ?></h4>
                                            <span style="background: #dbeafe; color: #1e40af; padding: 4px 10px; border-radius: 20px; font-size: 12px; white-space: nowrap; margin-left: 8px;">📁 <?= strtoupper($resource['file_type'] ?? 'FILE') ?></span>
                                        </div>
                                        
                                        <p style="margin: 0 0 8px 0; font-size: 13px; color: #64748b;">Category: <strong><?= htmlspecialchars($resource['category'] ?? 'General') ?></strong></p>
                                        <?php if (!empty($resource['course_year'])): ?>
                                            <p style="margin: 0 0 8px 0; font-size: 13px; color: #64748b;">For: <strong><?= htmlspecialchars($resource['course_year']) ?></strong></p>
                                        <?php endif; ?>

                                        <?php if (!empty($resource['description'])): ?>
                                            <p style="margin: 8px 0; font-size: 13px; color: #475569; line-height: 1.4;"><?= htmlspecialchars(substr($resource['description'], 0, 100)) ?>...</p>
                                        <?php endif; ?>

                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8;">
                                            <span>⬇️ <?= $resource['download_count'] ?? 0 ?> downloads</span>
                                            <a href="#download-<?= $resource['id'] ?>" class="download-btn" data-resource-id="<?= $resource['id'] ?>" data-file-url="<?= htmlspecialchars($resource['file_url']) ?>" data-file-name="<?= htmlspecialchars($resource['file_name']) ?>" style="color: #2563eb; text-decoration: none; font-weight: 500; cursor: pointer;">Download ↓</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div id="no-resources-message" style="grid-column: 1 / -1; text-align: center; padding: 40px; display: none;">
                                    <p style="color: #94a3b8; font-size: 16px;">No resources match your search or filter</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="library-panel" id="open_access">
                        <h2>Open Access Library</h2>
                        <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 18px 20px; margin-bottom: 20px;">
                            <p style="font-weight: 700; font-size: 0.98rem; margin: 0 0 8px 0; color: #111827;">The Official Disclaimer</p>
                            <p style="margin: 0 0 4px 0; color: #0f172a; font-weight: 600;">Notice: External Academic Resources</p>
                            <p style="margin: 0; color: #475569; line-height: 1.6;">The links provided in this section are curated for academic support and research purposes only. Pass College Library does not host, own, or claim copyright over the external content found on these sites. Users are redirected to the official publishers to ensure compliance with Intellectual Property Rights and to maintain academic integrity.</p>
                        </div>
                        <p style="color: #64748b; margin-bottom: 24px;">Access external open-source journals, research papers, and vetted academic links.</p>

                        <div class="resource-filter-row" style="margin-bottom: 20px;">
                            <input type="search" id="open-access-search" placeholder="Search open access resources by title or category..." style="padding: 10px 16px; border: 1px solid #cbd5e1; border-radius: 6px; width: 100%; max-width: 400px;">
                            <select id="open-access-category-filter" style="padding: 10px 16px; border: 1px solid #cbd5e1; border-radius: 6px; width: 100%; max-width: 260px; background: #ffffff;">
                                <option value="">All Categories</option>
                                <?php foreach ($openAccessCategories as $category): ?>
                                    <option value="<?= htmlspecialchars(strtolower($category)) ?>"><?= htmlspecialchars($category) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="resources-grid" id="open-access-grid">
                            <?php if (empty($openAccessResources)): ?>
                                <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                                    <p style="color: #94a3b8; font-size: 16px;">No Open Access resources are available yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($openAccessResources as $resource): ?>
                                    <div class="resource-card" data-title="<?= htmlspecialchars(strtolower($resource['name'] ?? '')) ?>" data-category="<?= htmlspecialchars(strtolower($resource['category'] ?? '')) ?>">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                            <h4 style="margin: 0; flex: 1; font-size: 15px; color: #1e293b;"><?= htmlspecialchars($resource['name'] ?? 'Untitled Resource') ?></h4>
                                            <span style="background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 20px; font-size: 12px; white-space: nowrap; margin-left: 8px;">🔗 Open Access</span>
                                        </div>
                                        <p style="margin: 0 0 8px 0; font-size: 13px; color: #64748b;">Category: <strong><?= htmlspecialchars($resource['category'] ?? 'General') ?></strong></p>
                                        <?php if (!empty($resource['description'])): ?>
                                            <p style="margin: 8px 0; font-size: 13px; color: #475569; line-height: 1.4;"><?= htmlspecialchars(substr($resource['description'], 0, 100)) ?>...</p>
                                        <?php endif; ?>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8;">
                                            <?php if (!empty($resource['url'])): ?>
                                                <a href="<?= htmlspecialchars($resource['url']) ?>" target="_blank" rel="noopener noreferrer" style="color: #2563eb; text-decoration: none; font-weight: 500;">Visit Resource</a>
                                            <?php else: ?>
                                                <span style="color: #94a3b8;">Resource link unavailable</span>
                                            <?php endif; ?>
                                            <span>Updated: <?= htmlspecialchars(date('M j, Y', strtotime($resource['updated_at'] ?? $resource['created_at'] ?? date('Y-m-d')))) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div id="no-open-access-message" style="grid-column: 1 / -1; text-align: center; padding: 40px; display: none;">
                                    <p style="color: #94a3b8; font-size: 16px;">No Open Access resources match your search or filter</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <div id="book-detail-modal" class="modal" aria-hidden="true">
                        <div class="modal-content">
                            <button type="button" class="modal-close" aria-label="Close">&times;</button>
                            
                            <!-- Hero Section: responsive split -->
                            <div class="book-detail-grid">
                                <!-- Left Column: Book Cover Card -->
                                <div style="background: #ffffff; border-radius: 16px; padding: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); height: fit-content;">
                                    <div id="modal-cover-container" style="aspect-ratio: 3/4; border-radius: 12px; overflow: hidden; background: #f1f5f9; display: flex; align-items: center; justify-content: center;">
                                        <img id="modal-cover-image" src="" alt="Book cover" style="display:none; width:100%; height:100%; object-fit:cover;">
                                        <div id="modal-cover-placeholder" class="cover-placeholder" style="display:grid; width:100%; height:100%;"> <i class="fa-solid fa-book"></i> </div>
                                    </div>
                                </div>

                                <!-- Right Column: Book Info -->
                                <div>
                                    <h1 id="modal-book-title" style="color: #7a2b2b; font-size: 32px; margin: 0 0 16px 0; font-weight: 700;">Title</h1>
                                    
                                    <div style="margin-bottom: 20px;">
                                        <p class="book-meta" id="modal-book-author" style="color: #64748b; font-size: 14px; margin: 4px 0;"></p>
                                    </div>

                                    <div style="padding: 12px; background: #f1f5f9; border-radius: 8px; margin-bottom: 20px;">
                                        <p style="color: #64748b; font-size: 13px; margin: 0;"><strong>Category:</strong> <span id="modal-book-category">General</span></p>
                                    </div>

                                    <div style="display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap;">
                                        <div style="flex: 1; min-width: 180px;">
                                            <p style="color: #94a3b8; font-size: 12px; margin: 0 0 4px 0;">Status</p>
                                            <p class="book-badge" id="modal-book-status" style="display: inline-block; margin: 0;"></p>
                                        </div>
                                        <div style="flex: 1; min-width: 180px;">
                                            <p style="color: #94a3b8; font-size: 12px; margin: 0 0 4px 0;">Available</p>
                                            <p id="modal-book-availability" style="color: #0f172a; font-weight: 600; margin: 0;"></p>
                                        </div>
                                        <div style="flex: 1; min-width: 180px;">
                                            <p style="color: #94a3b8; font-size: 12px; margin: 0 0 4px 0;">Rating</p>
                                            <p id="modal-book-rating" style="color: #0f172a; font-weight: 600; margin: 0;"></p>
                                        </div>
                                    </div>

                                    <div class="responsive-grid-2-variant" style="margin-bottom: 32px;">
                                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 22px; min-height: 120px; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);">
                                            <p style="margin: 0 0 10px 0; font-size: 12px; font-weight: 700; color: #475569; letter-spacing: 0.02em;">Book Quality Rating</p>
                                            <div style="display: flex; align-items: flex-end; gap: 10px;">
                                                <p id="modal-book-rating-avg" style="margin: 0; font-size: 32px; font-weight: 700; color: #1f2937;">—</p>
                                                <span style="font-size: 22px; color: #f59e0b; line-height: 1;">★</span>
                                            </div>
                                            <p id="modal-book-rating-count" style="margin: 10px 0 0 0; font-size: 12px; color: #64748b;">0 ratings</p>
                                        </div>
                                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; padding: 22px; min-height: 120px; display: flex; flex-direction: column; justify-content: center; box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);">
                                            <p style="margin: 0 0 10px 0; font-size: 12px; font-weight: 700; color: #475569; letter-spacing: 0.02em;">Service Quality Rating</p>
                                            <div style="display: flex; align-items: flex-end; gap: 10px;">
                                                <p id="modal-service-rating-avg" style="margin: 0; font-size: 32px; font-weight: 700; color: #1f2937;">—</p>
                                                <span style="font-size: 22px; color: #f59e0b; line-height: 1;">★</span>
                                            </div>
                                            <p id="modal-service-rating-count" style="margin: 10px 0 0 0; font-size: 12px; color: #64748b;">0 ratings</p>
                                        </div>
                                    </div>

                                    <!-- Reservation Card -->
                                    <div class="book-reservation-panel" style="background: #fffbf5; border: 2px solid #f5deb3; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                                        <form method="post" id="modal-action-form" class="book-reservation-form" style="display: grid; gap: 12px;">
                                            <input type="hidden" name="book_id" id="modal-book-id" value="">
                                            
                                            <div>
                                                <label for="pickup-date-input" class="book-reservation-label"><i class="fa-regular fa-calendar-days" aria-hidden="true"></i>Reserve this copy</label>
                                                <select id="pickup-date-input" name="pickup_date" style="width: 100%; padding: 10px 12px; border: 1px solid #f5deb3; border-radius: 8px; background: #ffffff; font-size: 14px; color: #0f172a;">
                                                    <option value="">Select a date</option>
                                                    <option id="pickup-today" value="">Today (until 5:00 PM)</option>
                                                    <option id="pickup-tomorrow" value="">Tomorrow (until 5:00 PM)</option>
                                                </select>
                                            </div>

                                            <button type="submit" name="action" value="reserve" class="primary-button" id="modal-reserve-btn" style="width: 100%; background: #7a2b2b; border: none; color: white; padding: 12px 16px; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 8px;"><i class="fa-solid fa-bookmark" aria-hidden="true"></i>Reserve book</button>
                                        </form>
                                        <p id="modal-action-hint" class="book-reservation-hint" style="color: #94a3b8; font-size: 12px; margin-top: 8px; margin-bottom: 0;">Hold expires 24 hours after the selected pick-up date.</p>
                                    </div>

                                    <div id="modal-book-variants-section" class="modal-variant-section">
                                        <p style="margin: 0 0 12px 0; font-size: 14px; font-weight: 700; color: #1f2937;">Choose a volume, part, or number</p>
                                        <div id="modal-variant-grid" class="modal-variant-grid"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Overview Section -->
                            <div style="background: #f8fafc; border-radius: 12px; padding: 20px; margin-bottom: 32px;">
                                <h4 style="color: #0f172a; margin: 0 0 12px 0; font-size: 16px;">Overview</h4>
                                <p id="modal-book-overview" style="color: #475569; margin: 0; line-height: 1.6; font-size: 14px;"></p>
                            </div>

                            <!-- Content Tabs -->
                            <div style="border-bottom: 2px solid #e2e8f0; margin-bottom: 24px;">
                                <div class="detail-tab-bar" style="display: flex; gap: 0; border: none; margin: 0;">
                                    <button type="button" class="detail-tab active" data-tab="overview" style="padding: 12px 20px; border: none; border-bottom: 3px solid #7a2b2b; color: #7a2b2b; background: none; cursor: pointer; font-weight: 600; font-size: 14px;">Overview</button>
                                    <button type="button" class="detail-tab" data-tab="editions" style="padding: 12px 20px; border: none; border-bottom: 3px solid transparent; color: #94a3b8; background: none; cursor: pointer; font-weight: 600; font-size: 14px;">Editions</button>
                                    <button type="button" class="detail-tab" data-tab="reserved" style="padding: 12px 20px; border: none; border-bottom: 3px solid transparent; color: #94a3b8; background: none; cursor: pointer; font-weight: 600; font-size: 14px;">Related Books</button>
                                </div>
                            </div>

                            <!-- Tab Panels -->
                            <div class="detail-tab-panel active" id="detail-overview" style="display: block;">
                                <p class="book-meta" style="color: #475569; margin: 0;">Detailed description and story summary appear here. Librarian manages the inventory content from the book record.</p>
                            </div>

                            <div class="detail-tab-panel" id="detail-editions" style="display: none;">
                                <p class="book-meta" style="color: #475569; margin: 0;">Edition details are maintained by the librarian in inventory. If there are no editions, the librarian can add that information there.</p>
                            </div>

                            <div class="detail-tab-panel" id="detail-reserved" style="display: none;">
                                <div id="modal-reservation-summary" style="margin-bottom: 20px; color: #475569;"></div>
                                <div id="modal-reservation-queue" class="reservation-queue" style="margin-bottom: 20px; max-height: 300px; overflow-y: auto;"></div>
                                
                                <!-- Related Books Horizontal Scroll -->
                                <div style="margin-top: 20px;">
                                    <h4 style="color: #0f172a; margin: 0 0 16px 0; font-size: 16px;">Related Books</h4>
                                    <div style="display: flex; gap: 12px; overflow-x: auto; padding-bottom: 12px;">
                                        <div id="modal-related-books-container" style="display: flex; gap: 12px;"></div>
                                    </div>
                                </div>

                                <form method="post" id="modal-cancel-reservation-form" style="display:none; margin-top:18px;">
                                    <input type="hidden" name="action" value="cancel_reservation">
                                    <input type="hidden" name="reservation_id" id="modal-cancel-reservation-id" value="">
                                    <button type="submit" class="secondary-button" style="background:#fb7185; color:#fff;">Cancel reservation</button>
                                </form>
                            </div>
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
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function () {
            // Swipe-to-Refresh Functionality (Mobile Only: 320px - 768px)
            const swipeRefreshSpinner = document.getElementById('swipe-refresh-spinner');
            let touchStartY = 0;
            let touchEndY = 0;
            let isRefreshing = false;
            const SWIPE_THRESHOLD = 100; // minimum pixels to swipe
            const SWIPE_START_LIMIT = 100; // only detect swipe from top 100px

            function isScreenMobile() {
                const width = window.innerWidth;
                return width >= 320 && width <= 768;
            }

            function showRefreshSpinner() {
                if (isScreenMobile() && !isRefreshing && swipeRefreshSpinner) {
                    isRefreshing = true;
                    swipeRefreshSpinner.style.display = 'flex';
                    setTimeout(() => {
                        location.reload();
                    }, 800);
                }
            }

            document.addEventListener('touchstart', function(e) {
                if (!isScreenMobile()) return;
                const touch = e.touches[0];
                touchStartY = touch.clientY;
            }, { passive: true });

            document.addEventListener('touchend', function(e) {
                if (!isScreenMobile()) return;
                const touch = e.changedTouches[0];
                touchEndY = touch.clientY;

                // Detect swipe down from top of page
                if (touchStartY <= SWIPE_START_LIMIT && touchEndY - touchStartY >= SWIPE_THRESHOLD) {
                    // Check if we're near the top of the page
                    if (window.scrollY <= 10) {
                        e.preventDefault();
                        showRefreshSpinner();
                    }
                }
            }, { passive: true });

            // Library toast notifications
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
            
            const tabs = document.querySelectorAll('.library-tab-button');
            const panels = document.querySelectorAll('.library-panel');
            const storageKey = 'libraryDashboardActiveTab';
            const activateTab = (targetId, save = true) => {
                if (!targetId) return;
                tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.tab === targetId));
                panels.forEach(panel => panel.classList.toggle('active', panel.id === targetId));

                if (save) {
                    try {
                        sessionStorage.setItem(storageKey, targetId);
                    } catch (e) {
                        // ignore storage failures
                    }
                    window.location.hash = targetId;
                }
            };
            tabs.forEach(button => {
                button.addEventListener('click', function () {
                    const target = this.dataset.tab;
                    activateTab(target);
                });
            });
            const initialHash = window.location.hash.substring(1);
            const storedTab = sessionStorage.getItem(storageKey);
            if (initialHash) {
                activateTab(initialHash, false);
            } else if (storedTab) {
                activateTab(storedTab, false);
            } else {
                activateTab('overview', false);
            }

            const tabBarContainers = document.querySelectorAll('.library-tab-bar');
            tabBarContainers.forEach(container => {
                let touchStartX = 0;
                let touchEndX = 0;
                const swipeThreshold = 60;

                container.addEventListener('touchstart', function (event) {
                    touchStartX = event.changedTouches[0].clientX;
                }, { passive: true });

                container.addEventListener('touchend', function (event) {
                    touchEndX = event.changedTouches[0].clientX;
                    const dx = touchEndX - touchStartX;

                    if (Math.abs(dx) < swipeThreshold || window.innerWidth > 768) {
                        return;
                    }

                    const buttons = Array.from(container.querySelectorAll('.library-tab-button'));
                    const activeButton = container.querySelector('.library-tab-button.active');
                    const activeIndex = buttons.indexOf(activeButton);
                    if (activeIndex === -1) {
                        return;
                    }

                    if (dx < 0 && activeIndex < buttons.length - 1) {
                        buttons[activeIndex + 1].click();
                    } else if (dx > 0 && activeIndex > 0) {
                        buttons[activeIndex - 1].click();
                    }
                }, { passive: true });
            });

            const panelContainers = document.querySelectorAll('.library-panel');
            panelContainers.forEach(panel => {
                let touchStartX = 0;
                let touchEndX = 0;
                const swipeThreshold = 50;

                panel.addEventListener('touchstart', function (event) {
                    if (!panel.classList.contains('active')) return;
                    touchStartX = event.changedTouches[0].clientX;
                }, { passive: true });

                panel.addEventListener('touchend', function (event) {
                    if (!panel.classList.contains('active')) return;
                    touchEndX = event.changedTouches[0].clientX;
                    const dx = touchEndX - touchStartX;

                    if (Math.abs(dx) < swipeThreshold || window.innerWidth > 768) {
                        return;
                    }

                    const buttons = Array.from(document.querySelectorAll('.library-tab-button'));
                    const activeButton = document.querySelector('.library-tab-button.active');
                    const activeIndex = buttons.indexOf(activeButton);
                    if (activeIndex === -1) {
                        return;
                    }

                    if (dx < 0 && activeIndex < buttons.length - 1) {
                        buttons[activeIndex + 1].click();
                    } else if (dx > 0 && activeIndex > 0) {
                        buttons[activeIndex - 1].click();
                    }
                }, { passive: true });
            });

            const detailTabs = document.querySelectorAll('.detail-tab');
            const detailPanels = document.querySelectorAll('.detail-tab-panel');
            const activateDetailTab = (tabId) => {
                detailTabs.forEach(tab => {
                    const isActive = tab.dataset.tab === tabId;
                    tab.classList.toggle('active', isActive);
                    tab.style.borderBottomColor = isActive ? '#7a2b2b' : 'transparent';
                    tab.style.color = isActive ? '#7a2b2b' : '#94a3b8';
                });
                detailPanels.forEach(panel => {
                    panel.style.display = panel.id === `detail-${tabId}` ? 'block' : 'none';
                    panel.classList.toggle('active', panel.id === `detail-${tabId}`);
                });
            };
            detailTabs.forEach(tab => {
                tab.addEventListener('click', () => activateDetailTab(tab.dataset.tab));
            });

            const dashboardAlert = document.querySelector('.dashboard-alert');
            if (dashboardAlert) {
                const isError = dashboardAlert.classList.contains('error');
                const message = dashboardAlert.textContent.trim();
                showToast(message, isError ? 'error' : 'success', 5000);
            }

            const catalogBooks = <?= json_encode(array_map(function($book) {
                return [
                    'id' => $book['id'],
                    'title' => $book['title'],
                    'author' => $book['author'],
                    'isbn' => $book['isbn'],
                    'lcc_letter_line' => $book['lcc_letter_line'],
                    'lcc_class_number' => $book['lcc_class_number'],
                    'lcc_cutter_code' => $book['lcc_cutter_code'],
                    'lcc_published_year' => $book['lcc_published_year'],
                    'lcc_additional_info' => $book['lcc_additional_info'],
                    'additional_info_type' => $book['additional_info_type'] ?? null,
                    'additional_info_quantity' => $book['additional_info_quantity'] ?? 0,
                    'category' => $book['category'] ?: 'General',
                    'status' => $book['status'],
                    'available_copies' => $book['available_copies'],
                    'total_copies' => $book['total_copies'],
                    'rating' => $book['rating'],
                    'overview' => $book['overview'],
                    'cover_url' => $book['cover_url'],
                ];
            }, $catalogBooks), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            
            // Catalog search and filter functionality
            const catalogSearchInput = document.getElementById('catalog-search-input');
            const catalogCategoryFilter = document.getElementById('catalog-category-filter');
            const catalogBooksContainer = document.getElementById('catalog-books-container');
            const catalogPagination = document.getElementById('catalog-pagination');
            const catalogPageSize = 40;
            let catalogCurrentPage = 1;
            
            // Define this BEFORE renderCatalogBooks so it can be called from within
            const attachCatalogCardListeners = () => {
                document.querySelectorAll('.catalog-book-card').forEach(card => {
                    card.addEventListener('click', () => openModal(buildBookFromElement(card)));
                    card.addEventListener('keypress', (event) => {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            openModal(buildBookFromElement(card));
                        }
                    });
                });
            };
            
            const renderCatalogBooks = () => {
                if (!catalogBooksContainer) return;
                
                const searchTerm = (catalogSearchInput?.value || '').toLowerCase();
                const selectedCategory = catalogCategoryFilter?.value || '';
                
                const filteredBooks = catalogBooks.filter(book => {
                    const matchesSearch = !searchTerm || 
                        book.title.toLowerCase().includes(searchTerm) ||
                        book.author.toLowerCase().includes(searchTerm) ||
                        book.isbn.toLowerCase().includes(searchTerm) ||
                        (book.lcc_letter_line + ' ' + book.lcc_class_number + ' ' + book.lcc_cutter_code + ' ' + book.lcc_published_year + ' ' + book.lcc_additional_info).toLowerCase().includes(searchTerm) ||
                        book.category.toLowerCase().includes(searchTerm);
                    
                    const matchesCategory = !selectedCategory || book.category === selectedCategory;
                    
                    return matchesSearch && matchesCategory;
                });
                
                if (filteredBooks.length === 0) {
                    catalogBooksContainer.innerHTML = '<div class="library-card overview-card" style="grid-column: 1 / -1;"><p>No books match your search or filter criteria.</p></div>';
                    if (catalogPagination) catalogPagination.innerHTML = '';
                    return;
                }

                const totalPages = Math.ceil(filteredBooks.length / catalogPageSize);
                catalogCurrentPage = Math.min(catalogCurrentPage, totalPages);
                const pageStart = (catalogCurrentPage - 1) * catalogPageSize;
                const pageBooks = filteredBooks.slice(pageStart, pageStart + catalogPageSize);
                
                catalogBooksContainer.innerHTML = pageBooks.map(book => `
                    <article class="catalog-card catalog-book-card"
                        data-book-id="${book.id}"
                        data-title="${book.title}"
                        data-author="${book.author}"
                        data-isbn="${book.isbn}"
                        data-lcc-letter-line="${book.lcc_letter_line || ''}"
                        data-lcc-class-number="${book.lcc_class_number || ''}"
                        data-lcc-cutter-code="${book.lcc_cutter_code || ''}"
                        data-lcc-published-year="${book.lcc_published_year || ''}"
                        data-lcc-additional-info="${book.lcc_additional_info || ''}"
                        data-additional-info-type="${book.additional_info_type || ''}"
                        data-additional-info-quantity="${book.additional_info_quantity || 0}"
                        data-category="${book.category}"
                        data-status="${book.status}"
                        data-available-copies="${book.available_copies}"
                        data-total-copies="${book.total_copies}"
                        data-rating="${book.rating}"
                        data-overview="${book.overview}"
                        data-cover-url="${book.cover_url}"
                        role="button"
                        tabindex="0"
                    >
                        <div class="catalog-cover${book.status === 'Reserved' ? ' cover-alt' : ''}">
                            ${book.cover_url ? `<img src='${book.cover_url}' alt='${book.title}'>` : '<div class="cover-placeholder"><i class="fa-solid fa-book"></i></div>'}
                        </div>
                        <div class="catalog-card-meta">
                            <div class="catalog-meta-top">
                                <span class="catalog-callnumber">${(() => {
                                    const lccParts = [book.lcc_letter_line, book.lcc_class_number, book.lcc_cutter_code, book.lcc_published_year, book.lcc_additional_info].filter(p => p);
                                    return lccParts.length > 0 ? lccParts.join(' ') : (book.category ? book.category : 'General Collection');
                                })()}</span>
                                <span class="catalog-status ${book.status.toLowerCase().replace(/\s+/g, '-')}">${book.status}</span>
                            </div>
                            <h3>${book.title}</h3>
                            <p class="catalog-author">${book.author || 'Unknown author'}</p>
                        </div>
                    </article>
                `).join('');

                if (catalogPagination) {
                    catalogPagination.innerHTML = Array.from({ length: totalPages }, (_, index) => {
                        const page = index + 1;
                        return `<button type="button" class="catalog-page-button${page === catalogCurrentPage ? ' active' : ''}" data-page="${page}" aria-label="Catalog page ${page}" aria-current="${page === catalogCurrentPage ? 'page' : 'false'}">${page}</button>`;
                    }).join('');
                    catalogPagination.querySelectorAll('.catalog-page-button').forEach(button => {
                        button.addEventListener('click', () => {
                            catalogCurrentPage = Number(button.dataset.page);
                            renderCatalogBooks();
                            catalogBooksContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        });
                    });
                }
            };
            const detailModal = document.getElementById('book-detail-modal');
            const modalCloseButtons = detailModal ? detailModal.querySelectorAll('.modal-close') : [];
            const modalTitle = document.getElementById('modal-book-title');
            const modalAuthor = document.getElementById('modal-book-author');
            const modalCategory = document.getElementById('modal-book-category');
            const modalStatus = document.getElementById('modal-book-status');
            const modalAvailability = document.getElementById('modal-book-availability');
            const modalRating = document.getElementById('modal-book-rating');
            const modalOverview = document.getElementById('modal-book-overview');
            const modalBookId = document.getElementById('modal-book-id');
            const modalReserveBtn = document.getElementById('modal-reserve-btn');
            const modalActionHint = document.getElementById('modal-action-hint');
            const modalCoverImage = document.getElementById('modal-cover-image');
            const modalCoverPlaceholder = document.getElementById('modal-cover-placeholder');
            const modalReservationSummary = document.getElementById('modal-reservation-summary');
            const modalReservationQueue = document.getElementById('modal-reservation-queue');
            const modalCancelForm = document.getElementById('modal-cancel-reservation-form');
            const modalCancelReservationId = document.getElementById('modal-cancel-reservation-id');
            const relatedBooksList = document.getElementById('modal-related-books-container');
            const modalVariantSection = document.getElementById('modal-book-variants-section');
            const modalVariantGrid = document.getElementById('modal-variant-grid');

            const parseAdditionalInfoRange = (info) => {
                if (!info) return [];
                const normalized = info.trim().toLowerCase();

                // Parse volume ranges like "v.1 - c.3" or "vol.1-3"
                const volumeRangeMatch = normalized.match(/(?:vol(?:ume)?|v)\.?\s*(\d+)\s*(?:-\s*(?:c\.?|copy\.?)?\s*(\d+))?/i);
                if (volumeRangeMatch) {
                    const start = parseInt(volumeRangeMatch[1]);
                    const end = volumeRangeMatch[2] ? parseInt(volumeRangeMatch[2]) : start;
                    const variants = [];
                    for (let i = start; i <= end; i++) {
                        variants.push({ type: 'volume', number: i, label: `Volume ${i}` });
                    }
                    return variants;
                }

                // Parse part ranges like "pt.1-2" or "part 1-3"
                const partRangeMatch = normalized.match(/(?:part|pt)\.?\s*(\d+)\s*(?:-\s*(\d+))?/i);
                if (partRangeMatch) {
                    const start = parseInt(partRangeMatch[1]);
                    const end = partRangeMatch[2] ? parseInt(partRangeMatch[2]) : start;
                    const variants = [];
                    for (let i = start; i <= end; i++) {
                        variants.push({ type: 'part', number: i, label: `Part ${i}` });
                    }
                    return variants;
                }

                // Parse number ranges like "no.1-5" or "number 1-3"
                const numberRangeMatch = normalized.match(/(?:no(?:\.|)|number|num)\.?\s*(\d+)\s*(?:-\s*(\d+))?/i);
                if (numberRangeMatch) {
                    const start = parseInt(numberRangeMatch[1]);
                    const end = numberRangeMatch[2] ? parseInt(numberRangeMatch[2]) : start;
                    const variants = [];
                    for (let i = start; i <= end; i++) {
                        variants.push({ type: 'number', number: i, label: `Number ${i}` });
                    }
                    return variants;
                }

                // Single item like "v.1" or "pt.2"
                const singleVolumeMatch = normalized.match(/(?:vol(?:ume)?|v)\.?\s*(\d+)/i);
                if (singleVolumeMatch) {
                    return [{ type: 'volume', number: parseInt(singleVolumeMatch[1]), label: `Volume ${singleVolumeMatch[1]}` }];
                }

                const singlePartMatch = normalized.match(/(?:part|pt)\.?\s*(\d+)/i);
                if (singlePartMatch) {
                    return [{ type: 'part', number: parseInt(singlePartMatch[1]), label: `Part ${singlePartMatch[1]}` }];
                }

                const singleNumberMatch = normalized.match(/(?:no(?:\.|)|number|num)\.?\s*(\d+)/i);
                if (singleNumberMatch) {
                    return [{ type: 'number', number: parseInt(singleNumberMatch[1]), label: `Number ${singleNumberMatch[1]}` }];
                }

                return [];
            };

            const getVariantLabel = (type, number) => {
                switch (type) {
                    case 'volume': return `Volume ${number}`;
                    case 'part': return `Part ${number}`;
                    case 'number': return `Number ${number}`;
                    default: return `${type.charAt(0).toUpperCase() + type.slice(1)} ${number}`;
                }
            };

            const buildVariantGroups = (currentBook) => {
                const type = (currentBook.additional_info_type || '').toLowerCase();
                const quantity = Number(currentBook.additional_info_quantity) || 0;
                const totalCopies = Number(currentBook.total_copies) || 0;
                const availableCopies = Number(currentBook.available_copies) || 0;

                if (['volume', 'part', 'number'].includes(type) && quantity > 0) {
                    const baseTotal = Math.floor(totalCopies / quantity);
                    const baseAvailable = Math.floor(availableCopies / quantity);
                    const totalRemainder = totalCopies % quantity;
                    const availableRemainder = availableCopies % quantity;

                    return Array.from({ length: quantity }, (_, index) => {
                        const number = index + 1;
                        return {
                            label: getVariantLabel(type, number),
                            type,
                            number,
                            representative: {
                                ...currentBook,
                                lcc_additional_info: getVariantLabel(type, number),
                                id: `${currentBook.id}-${type}-${number}`,
                                total_copies: baseTotal + (number <= totalRemainder ? 1 : 0),
                                available_copies: baseAvailable + (number <= availableRemainder ? 1 : 0),
                            }
                        };
                    });
                }

                const variants = parseAdditionalInfoRange(currentBook.lcc_additional_info || '');
                if (variants.length === 0) return [];

                return variants.map(variant => ({
                    label: variant.label,
                    type: variant.type,
                    number: variant.number,
                    representative: {
                        ...currentBook,
                        lcc_additional_info: `${variant.type.charAt(0).toUpperCase() + variant.type.slice(1)} ${variant.number}`,
                        id: `${currentBook.id}-${variant.type}-${variant.number}`,
                        available_copies: Math.floor(Math.random() * 3) + 1,
                        total_copies: Math.floor(Math.random() * 5) + 3
                    }
                }));
            };

            const renderVariantCards = (book) => {
                if (!modalVariantSection || !modalVariantGrid) return;
                const variants = buildVariantGroups(book);
                if (variants.length === 0) {
                    modalVariantSection.style.display = 'none';
                    modalVariantGrid.innerHTML = '';
                    return;
                }

                modalVariantSection.style.display = 'block';
                modalVariantGrid.innerHTML = variants.map(variant => `
                    <button type="button" class="variant-card" data-book-id="${variant.representative.id}" data-variant-label="${variant.label}">
                        <div class="variant-card-icon">📚</div>
                        <span class="variant-label">${variant.label}</span>
                    </button>
                `).join('');

                modalVariantGrid.querySelectorAll('.variant-card').forEach(button => {
                    button.addEventListener('click', () => {
                        const selectedId = button.dataset.bookId;
                        const variantLabel = button.dataset.variantLabel;
                        modalBookId.value = selectedId;
                        modalTitle.textContent = `${book.title} — ${variantLabel}`;
                        positionModalAuthor();
                        // Update availability display
                        const variant = variants.find(v => v.representative.id === selectedId);
                        if (variant) {
                            modalAvailability.textContent = `${variant.representative.available_copies} / ${variant.representative.total_copies}`;
                        }
                    });
                });
            };

            const positionModalAuthor = () => {
                if (!modalTitle || !modalAuthor) return;
                if (window.innerWidth <= 768) {
                    const titleTop = 24;
                    const authorTop = titleTop + modalTitle.offsetHeight + 12;
                    const heroHeight = Math.max(150, authorTop + modalAuthor.offsetHeight + 36);
                    const coverOffset = heroHeight - 150 + 12;
                    modalAuthor.style.top = `${authorTop}px`;
                    modalTitle.closest('.book-detail-grid')?.style.setProperty('--mobile-modal-hero-height', `${heroHeight}px`);
                    modalTitle.closest('.book-detail-grid')?.style.setProperty('--mobile-modal-cover-offset', `${coverOffset}px`);
                    return;
                }
                modalAuthor.style.top = `${28 + modalTitle.offsetHeight + 12}px`;
            };
            window.addEventListener('resize', positionModalAuthor);

            const userContext = {
                activeVisit: <?= $activeVisit ? 'true' : 'false' ?>,
                borrowedCount: <?= json_encode($borrowedCount) ?>,
                reservationCount: <?= json_encode($reservationCount) ?>,
                maxBorrowed: <?= json_encode($maxBorrowed) ?>,
                maxReservations: <?= json_encode($maxReservations) ?>,
            };

            const openModal = (book) => {
                if (!detailModal || !book) return;
                modalTitle.textContent = book.title;
                modalAuthor.textContent = 'Author: ' + book.author;
                positionModalAuthor();
                modalCategory.textContent = book.category;
                modalStatus.textContent = book.status;
                const statusClassName = book.status.toLowerCase().replace(/\s+/g, '-');
                modalStatus.className = 'book-badge ' + statusClassName;
                modalAvailability.textContent = book.available_copies + ' / ' + book.total_copies;
                modalRating.textContent = (book.rating || '0') + ' / 5';
                modalOverview.textContent = book.overview || 'No overview provided.';
                modalBookId.value = book.id;
                renderVariantCards(book);
                if (book.cover_url) {
                    // Add ../ prefix for images since we're in dashboard folder
                    const imgSrc = book.cover_url.startsWith('../') ? book.cover_url : '../' + book.cover_url;
                    modalCoverImage.src = imgSrc;
                    modalCoverImage.style.display = 'block';
                    modalCoverPlaceholder.style.display = 'none';
                } else {
                    modalCoverImage.style.display = 'none';
                    modalCoverPlaceholder.style.display = 'grid';
                }

                const related = catalogBooks.filter(item => item.category === book.category && item.id !== book.id).slice(0, 5);
                if (relatedBooksList) {
                    if (related.length > 0) {
                        relatedBooksList.innerHTML = related.map(item => `
                            <article style="display: flex; flex-direction: column; gap: 8px; width: 115px; flex-shrink: 0; cursor: pointer;">
                                <div class="catalog-book-card" 
                                    data-book-id="${item.id}"
                                    data-title="${item.title}"
                                    data-author="${item.author}"
                                    data-isbn="${item.isbn}"
                                    data-lcc-letter-line="${item.lcc_letter_line || ''}"
                                    data-lcc-class-number="${item.lcc_class_number || ''}"
                                    data-lcc-cutter-code="${item.lcc_cutter_code || ''}"
                                    data-lcc-published-year="${item.lcc_published_year || ''}"
                                    data-lcc-additional-info="${item.lcc_additional_info || ''}"
                                    data-additional-info-type="${item.additional_info_type || ''}"
                                    data-additional-info-quantity="${item.additional_info_quantity || 0}"
                                    data-category="${item.category}"
                                    data-status="${item.status}"
                                    data-available-copies="${item.available_copies}"
                                    data-total-copies="${item.total_copies}"
                                    data-rating="${item.rating}"
                                    data-overview="${item.overview}"
                                    data-cover-url="${item.cover_url}"
                                    role="button"
                                    tabindex="0"
                                    style="width: 100%; height: 100%; display: contents;">
                                    <div style="aspect-ratio: 3/4; border-radius: 6px; overflow: hidden; background: #f1f5f9; box-shadow: 0 2px 6px rgba(0,0,0,0.08);">
                                        ${item.cover_url ? `<img src='${item.cover_url}' alt='${item.title}' style='width: 100%; height: 100%; object-fit: cover;'>` : '<div style="display: flex; align-items: center; justify-content: center; height: 100%; font-size: 16px;"><i class="fa-solid fa-book"></i></div>'}
                                    </div>
                                    <h4 style="font-size: 11px; margin: 0; color: #0f172a; line-height: 1.2; word-break: break-word; text-align: center;">${item.title}</h4>
                                    <p style="font-size: 9px; margin: 0; color: #94a3b8; line-height: 1; text-align: center;">${item.author}</p>
                                </div>
                            </article>
                        `).join('');
                        
                        // Re-attach click listeners to related book cards
                        relatedBooksList.querySelectorAll('article > div[data-book-id]').forEach(card => {
                            card.parentElement.addEventListener('click', () => openModal(buildBookFromElement(card)));
                        });
                    } else {
                        relatedBooksList.innerHTML = '<p style="color: #94a3b8; font-size: 14px; text-align: center; padding: 20px; grid-column: 1/-1;">No related books available</p>';
                    }
                }

                const queue = book.reservationQueue || [];
                const userReservation = book.userReservation || null;
                if (queue.length === 0) {
                    modalReservationSummary.textContent = 'No active reservation queue for this book.';
                    modalReservationQueue.innerHTML = '<p class="book-meta">Everyone can reserve this book if it becomes unavailable.</p>';
                } else {
                    modalReservationSummary.textContent = `Reservation queue (${queue.length} active):`;
                    modalReservationQueue.innerHTML = '<ol>' + queue.map(item => '<li><strong>' + item.queue_position + '</strong>. ' + item.reserved_by + ' — ' + item.reserved_date + '</li>').join('') + '</ol>';
                }
                if (userReservation) {
                    modalReservationSummary.textContent = 'You have an active reservation for this book.';
                    modalCancelReservationId.value = userReservation.id;
                    modalCancelForm.style.display = 'block';
                } else {
                    modalCancelForm.style.display = 'none';
                    modalCancelReservationId.value = '';
                }

                const isAvailableForBorrow = book.status === 'Available' && book.available_copies > 0;
                const canReserve = userContext.reservationCount < userContext.maxReservations;
                
                const today = new Date();
                const tomorrow = new Date();
                tomorrow.setDate(today.getDate() + 1);
                
                // Format dates as YYYY-MM-DD using local timezone (not UTC)
                const todayStr = String(today.getFullYear()).padStart(4, '0') + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
                const tomorrowStr = String(tomorrow.getFullYear()).padStart(4, '0') + '-' + String(tomorrow.getMonth() + 1).padStart(2, '0') + '-' + String(tomorrow.getDate()).padStart(2, '0');
                const todayDisplay = today.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                const tomorrowDisplay = tomorrow.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                
                const pickupTodayOpt = document.getElementById('pickup-today');
                const pickupTomorrowOpt = document.getElementById('pickup-tomorrow');
                if (pickupTodayOpt) {
                    pickupTodayOpt.value = todayStr;
                    pickupTodayOpt.textContent = 'Today, ' + todayDisplay + ' (until 5:00 PM)';
                }
                if (pickupTomorrowOpt) {
                    pickupTomorrowOpt.value = tomorrowStr;
                    pickupTomorrowOpt.textContent = 'Tomorrow, ' + tomorrowDisplay + ' (until 5:00 PM)';
                }
                
                const pickupDateInput = document.getElementById('pickup-date-input');
                if (pickupDateInput) {
                    pickupDateInput.value = '';
                }
                
                modalReserveBtn.disabled = !canReserve;
                modalReserveBtn.title = canReserve ? '' : 'Max ' + userContext.maxReservations + ' active reservations reached.';
                if (!modalActionHint) {
                    console.warn('Modal action hint element not found.');
                } else {
                    if (!canReserve) {
                        modalActionHint.textContent = 'You already have the maximum of ' + userContext.maxReservations + ' active reservations. Cancel one first.';
                    } else {
                        modalActionHint.textContent = '';
                    }
                }
                
                // Fetch and display book and service rating averages
                fetch('../includes/get_book_ratings.php?book_id=' + book.id)
                    .then(response => response.json())
                    .then(data => {
                        const bookRatingValue = document.getElementById('modal-book-rating-avg');
                        const bookRatingCount = document.getElementById('modal-book-rating-count');
                        const serviceRatingValue = document.getElementById('modal-service-rating-avg');
                        const serviceRatingCount = document.getElementById('modal-service-rating-count');
                        
                        const bookCount = data.book_rating_count || 0;
                        const serviceCount = data.service_rating_count || 0;

                        if (bookRatingValue) {
                            bookRatingValue.textContent = data.book_rating_avg !== null ? parseFloat(data.book_rating_avg).toFixed(1) : '—';
                        }
                        if (bookRatingCount) {
                            bookRatingCount.textContent = bookCount > 0 ? bookCount + (bookCount === 1 ? ' rating' : ' ratings') : 'No ratings';
                        }
                        if (serviceRatingValue) {
                            serviceRatingValue.textContent = data.service_rating_avg !== null ? parseFloat(data.service_rating_avg).toFixed(1) : '—';
                        }
                        if (serviceRatingCount) {
                            serviceRatingCount.textContent = serviceCount > 0 ? serviceCount + (serviceCount === 1 ? ' rating' : ' ratings') : 'No ratings';
                        }
                    })
                    .catch(error => console.error('Error fetching ratings:', error));
                
                detailModal.classList.add('show');
                detailModal.setAttribute('aria-hidden', 'false');
                requestAnimationFrame(positionModalAuthor);
            };

            const closeModal = () => {
                if (!detailModal) return;
                detailModal.classList.remove('show');
                detailModal.setAttribute('aria-hidden', 'true');
            };

            const buildBookFromElement = (element) => ({
                id: element.dataset.bookId,
                title: element.dataset.title,
                author: element.dataset.author,
                isbn: element.dataset.isbn,
                lcc_letter_line: element.dataset.lccLetterLine,
                lcc_class_number: element.dataset.lccClassNumber,
                lcc_cutter_code: element.dataset.lccCutterCode,
                lcc_published_year: element.dataset.lccPublishedYear,
                lcc_additional_info: element.dataset.lccAdditionalInfo,
                additional_info_type: element.dataset.additionalInfoType || null,
                additional_info_quantity: parseInt(element.dataset.additionalInfoQuantity, 10) || 0,
                category: element.dataset.category,
                status: element.dataset.status,
                available_copies: parseInt(element.dataset.availableCopies, 10) || 0,
                total_copies: parseInt(element.dataset.totalCopies, 10) || 0,
                rating: parseFloat(element.dataset.rating) || 0,
                overview: element.dataset.overview,
                cover_url: element.dataset.coverUrl,
                reservationQueue: element.dataset.reservationQueue ? JSON.parse(element.dataset.reservationQueue) : [],
                userReservation: element.dataset.userReservation ? JSON.parse(element.dataset.userReservation) : null,
            });

            // Attach listeners to initial catalog cards
            attachCatalogCardListeners();
            catalogSearchInput?.addEventListener('input', () => {
                catalogCurrentPage = 1;
                renderCatalogBooks();
                attachCatalogCardListeners();
            });
            catalogCategoryFilter?.addEventListener('change', () => {
                catalogCurrentPage = 1;
                renderCatalogBooks();
                attachCatalogCardListeners();
            });
            renderCatalogBooks();
            attachCatalogCardListeners();

            // Handle multiple carousels on the page
            document.querySelectorAll('.catalog-carousel').forEach(carouselContainer => {
                const carouselTrack = carouselContainer.querySelector('.catalog-track');
                const carouselPrev = carouselContainer.querySelector('.carousel-prev');
                const carouselNext = carouselContainer.querySelector('.carousel-next');
                
                if (carouselTrack && carouselPrev && carouselNext) {
                    let scrollAmount = 0;
                    const cardWidth = carouselTrack.querySelector('.catalog-card')?.offsetWidth || 240;
                    const gap = 20;
                    
                    carouselPrev.addEventListener('click', () => {
                        scrollAmount = Math.max(0, scrollAmount - (cardWidth + gap));
                        carouselTrack.scrollTo({ left: scrollAmount, behavior: 'smooth' });
                    });
                    
                    carouselNext.addEventListener('click', () => {
                        scrollAmount = Math.min(carouselTrack.scrollWidth - carouselTrack.clientWidth, scrollAmount + (cardWidth + gap));
                        carouselTrack.scrollTo({ left: scrollAmount, behavior: 'smooth' });
                    });
                }
            });

            modalCloseButtons.forEach(button => button.addEventListener('click', closeModal));
            window.addEventListener('click', (event) => {
                if (event.target === detailModal) {
                    closeModal();
                }
            });

            // E-Resource upload handling
            const uploadForm = document.getElementById('upload-resource-form');
            const fileUploadArea = document.querySelector('.file-upload-area');
            const fileInput = document.getElementById('resource-file');
            const fileName = document.getElementById('file-name');

            if (uploadForm) {
                // File drag and drop
                if (fileUploadArea) {
                    fileUploadArea.addEventListener('click', () => fileInput.click());
                    fileUploadArea.addEventListener('dragover', (e) => {
                        e.preventDefault();
                        fileUploadArea.style.borderColor = '#2563eb';
                        fileUploadArea.style.backgroundColor = '#eff6ff';
                    });
                    fileUploadArea.addEventListener('dragleave', () => {
                        fileUploadArea.style.borderColor = '#cbd5e1';
                        fileUploadArea.style.backgroundColor = 'transparent';
                    });
                    fileUploadArea.addEventListener('drop', (e) => {
                        e.preventDefault();
                        fileUploadArea.style.borderColor = '#cbd5e1';
                        fileUploadArea.style.backgroundColor = 'transparent';
                        if (e.dataTransfer.files.length > 0) {
                            fileInput.files = e.dataTransfer.files;
                            updateFileName();
                        }
                    });
                }

                fileInput.addEventListener('change', updateFileName);

                function updateFileName() {
                    if (fileInput.files.length > 0) {
                        fileName.textContent = '✓ ' + fileInput.files[0].name;
                        fileName.style.display = 'block';
                    } else {
                        fileName.style.display = 'none';
                    }
                }

                uploadForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    formData.append('action', 'upload_resource');

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    }).then(() => {
                        window.location.reload();
                    }).catch(error => {
                        console.error('Upload error:', error);
                        alert('Upload failed. Please try again.');
                    });
                });
            }

            // E-Resource download handling
            document.querySelectorAll('.download-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const resourceId = this.dataset.resourceId;
                    const fileUrl = this.dataset.fileUrl;
                    const fileName = this.dataset.fileName;

                    // Log download
                    fetch('library_e-resources.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=download&resource_id=' + resourceId
                    });

                    // Trigger download
                    const link = document.createElement('a');
                    link.href = fileUrl;
                    link.download = fileName;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                });
            });

            // Approved resources search and category filter
            const searchInput = document.getElementById('resource-search');
            const categoryFilter = document.getElementById('resource-category-filter');
            const noResourcesMessage = document.getElementById('no-resources-message');

            function filterResourceCards() {
                const query = searchInput ? searchInput.value.toLowerCase() : '';
                const selectedCategory = categoryFilter ? categoryFilter.value : '';
                let visibleCount = 0;

                document.querySelectorAll('#resources .resource-card').forEach(card => {
                    const title = card.dataset.title;
                    const category = card.dataset.category;
                    const matchesText = title.includes(query) || category.includes(query);
                    const matchesCategory = !selectedCategory || category === selectedCategory;
                    const isVisible = (matchesText && matchesCategory);
                    card.style.display = isVisible ? 'block' : 'none';
                    if (isVisible) visibleCount++;
                });
                
                if (noResourcesMessage) {
                    noResourcesMessage.style.display = visibleCount === 0 ? 'block' : 'none';
                }
            }

            if (searchInput) {
                searchInput.addEventListener('input', filterResourceCards);
            }
            if (categoryFilter) {
                categoryFilter.addEventListener('change', filterResourceCards);
            }

            // Open access resource search and category filter
            const openAccessSearchInput = document.getElementById('open-access-search');
            const openAccessCategoryFilter = document.getElementById('open-access-category-filter');
            const noOpenAccessMessage = document.getElementById('no-open-access-message');

            function filterOpenAccessCards() {
                const query = openAccessSearchInput ? openAccessSearchInput.value.toLowerCase() : '';
                const selectedCategory = openAccessCategoryFilter ? openAccessCategoryFilter.value : '';
                let visibleCount = 0;

                document.querySelectorAll('#open_access .resource-card').forEach(card => {
                    const title = card.dataset.title;
                    const category = card.dataset.category;
                    const matchesText = title.includes(query) || category.includes(query);
                    const matchesCategory = !selectedCategory || category === selectedCategory;
                    const isVisible = (matchesText && matchesCategory);
                    card.style.display = isVisible ? 'block' : 'none';
                    if (isVisible) visibleCount++;
                });

                if (noOpenAccessMessage) {
                    noOpenAccessMessage.style.display = visibleCount === 0 ? 'block' : 'none';
                }
            }

            if (openAccessSearchInput) {
                openAccessSearchInput.addEventListener('input', filterOpenAccessCards);
            }
            if (openAccessCategoryFilter) {
                openAccessCategoryFilter.addEventListener('change', filterOpenAccessCards);
            }

            // Initialize default filters
            filterResourceCards();
            filterOpenAccessCards();

            // Mobile Menu Toggle Functionality
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sideNav = document.querySelector('.side-nav');
            const pageOverlay = document.getElementById('pageOverlay');

            if (mobileMenuToggle && sideNav && pageOverlay) {
                // Toggle menu when hamburger icon is clicked
                mobileMenuToggle.addEventListener('click', function() {
                    sideNav.classList.toggle('mobile-open');
                    pageOverlay.classList.toggle('active');
                });

                // Close menu when overlay is clicked
                pageOverlay.addEventListener('click', function() {
                    sideNav.classList.remove('mobile-open');
                    pageOverlay.classList.remove('active');
                });

                // Close menu when any navigation link is clicked
                const navLinks = sideNav.querySelectorAll('a, .nav-toggle');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        // Don't close if clicking toggle buttons for submenus
                        if (!this.classList.contains('nav-toggle')) {
                            sideNav.classList.remove('mobile-open');
                            pageOverlay.classList.remove('active');
                        }
                    });
                });

                // Close menu on ESC key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && sideNav.classList.contains('mobile-open')) {
                        sideNav.classList.remove('mobile-open');
                        pageOverlay.classList.remove('active');
                    }
                });
            }
        });

        // search button handler removed per request
    </script>
    <script src="../assets/js/app.js" defer></script>
    <?php include '../AI CHAT BOT/chat_widget.php'; ?>
</body>
</html>
