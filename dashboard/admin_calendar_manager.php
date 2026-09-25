<?php
ob_start();
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
$user = current_user();
if ($user['role'] !== 'admin') {
    header('Location: ../auth/admin_login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$ssaaOpen = in_array($currentPage, ['student_promotion.php', 'graduation_events.php', 'alumni_management.php', 'reports_analytics.php'], true);

ensure_library_schema();

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_calendar_block') {
        $blockId = (int)($_POST['block_id'] ?? 0);
        $eventDate = trim($_POST['event_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '') ?: $eventDate;
        $eventType = trim($_POST['event_type'] ?? 'graduation');
        $description = trim($_POST['description'] ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime = trim($_POST['end_time'] ?? '');
        $announcementImages = $_FILES['announcement_images'] ?? null;
        // Debug: log uploaded files array to help diagnose missing uploads
        try {
            $logPath = __DIR__ . '/upload_debug.log';
            $logData = "[" . date('Y-m-d H:i:s') . "] POST keys: " . json_encode(array_keys($_POST)) . "\n";
            $logData .= "FILES: " . print_r($_FILES, true) . "\n\n";
            file_put_contents($logPath, $logData, FILE_APPEND | LOCK_EX);
        } catch (Throwable $ex) {
            // ignore logging errors
        }
        if (!$eventDate || !in_array($eventType, ['holiday', 'no_class', 'graduation', 'exam', 'acquaintance', 'intramurals', 'others'], true)) {
            $message = 'Please select a valid calendar date and event type.';
            $messageType = 'error';
        } else {
            if ($blockId > 0) {
                if (update_library_calendar_block($blockId, $eventDate, $eventType, $description, $startTime ?: null, $endTime ?: null)) {
                    if ($announcementImages && !empty($announcementImages['name'][0])) {
                        upload_library_calendar_images($blockId, $announcementImages);
                    }
                    sync_library_due_dates_for_calendar_updates();
                    if ($eventType === 'graduation' && $eventDate <= date('Y-m-d')) {
                        process_graduating_students();
                    }
                    $message = 'Announcement updated successfully. Calendar dates were recalculated.';
                    $messageType = 'success';
                } else {
                    $message = 'Unable to update the announcement. Please try again.';
                    $messageType = 'error';
                }
            } else {
                $calendarId = add_library_calendar_block($eventDate, $eventType, $description, $startTime ?: null, $endTime ?: null);
                if ($calendarId !== false) {
                    if ($announcementImages && !empty($announcementImages['name'][0])) {
                        upload_library_calendar_images($calendarId, $announcementImages);
                    }
                    sync_library_due_dates_for_calendar_updates();
                    if ($eventType === 'graduation' && $eventDate <= date('Y-m-d')) {
                        process_graduating_students();
                    }
                    $message = 'Announcement added successfully. Calendar dates were recalculated.';
                    $messageType = 'success';
                } else {
                    $message = 'Unable to add the announcement. Please try again.';
                    $messageType = 'error';
                }
            }
        }
    } elseif ($action === 'add_calendar_announcement') {
        $announcementTitle = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $announcementImages = $_FILES['announcement_images'] ?? null;
        $eventDate = date('Y-m-d');
        $eventType = 'others';

        if ($announcementTitle === '' && $description === '' && (! $announcementImages || empty($announcementImages['name'][0]))) {
            $message = 'Please provide a title, description, or upload at least one image for the announcement.';
            $messageType = 'error';
        } else {
            $descriptionToSave = $announcementTitle !== ''
                ? trim($announcementTitle . ($description !== '' ? "\n\n" . $description : ''))
                : $description;

            $calendarId = add_library_calendar_block($eventDate, $eventType, $descriptionToSave, null, null);
            if ($calendarId !== false) {
                if ($announcementImages && !empty($announcementImages['name'][0])) {
                    upload_library_calendar_images($calendarId, $announcementImages);
                }
                sync_library_due_dates_for_calendar_updates();
                $message = 'Announcement added successfully.';
                $messageType = 'success';
            } else {
                $message = 'Unable to add the announcement. Please try again.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'remove_calendar_block') {
        $blockId = (int)($_POST['block_id'] ?? 0);
        if ($blockId > 0 && remove_library_calendar_block($blockId)) {
            sync_library_due_dates_for_calendar_updates();
            $message = 'Calendar block removed successfully. Library due dates were recalculated.';
            $messageType = 'success';
        } else {
            $message = 'Unable to remove the calendar block.';
            $messageType = 'error';
        }
    } elseif ($action === 'get_recent_graduates') {
        // Get students that were recently promoted to alumni (within last 24 hours)
        $conn = get_db();
        $stmt = $conn->prepare('
            SELECT DISTINCT a.id, a.full_name, a.email, a.personal_email, a.course, a.graduation_year
            FROM ssaa_alumni a
            WHERE DATE(a.created_at) = CURDATE()
            ORDER BY a.created_at DESC
            LIMIT 100
        ');
        $stmt->execute();
        $recentGraduates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Return as JSON for AJAX
        header('Content-Type: application/json');
        echo json_encode($recentGraduates);
        exit;
    }
}

$calendarBlocks = get_library_calendar_blocks(50);
$calendarImages = get_library_calendar_images_by_calendar_ids(array_column($calendarBlocks, 'id'));
foreach ($calendarBlocks as &$block) {
    $block['images'] = $calendarImages[$block['id']] ?? [];
}
unset($block);
$calendarBlocksJson = json_encode($calendarBlocks);

// Fetch all announcements separately (without the 50-item limit from calendar blocks)
// This ensures announcements are never cut off even if there are many calendar events
$conn = get_db();
$announcementStmt = $conn->prepare('SELECT * FROM library_calendar ORDER BY event_date DESC');
$announcementStmt->execute();
$allAnnouncements = $announcementStmt->fetchAll(PDO::FETCH_ASSOC);

// Get images for all announcements
$announcementIds = array_column($allAnnouncements, 'id');
$announcementImages = get_library_calendar_images_by_calendar_ids($announcementIds);
foreach ($allAnnouncements as &$announcement) {
    $announcement['images'] = $announcementImages[$announcement['id']] ?? [];
}
unset($announcement);

$calendarAnnouncements = array_values($allAnnouncements);
$calendarAnnouncementsJson = json_encode($calendarAnnouncements);
$currentPage = basename($_SERVER['PHP_SELF']);
$ssaaOpen = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Calendar Manager | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
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
        .case-page-header {
            position: relative;
            min-height: 245px;
            display: flex;
            align-items: center;
            overflow: hidden;
            margin: 0 0 24px;
            padding: 42px 48px 30px;
            background: #f8f1e7;
            border: 0;
            border-radius: 0 0 24px 24px;
        }

        .case-page-header::before {
            content: '';
            position: absolute;
            z-index: 0;
            inset: 0 auto 0 0;
            width: 3px;
            background: #861b17;
        }

        .case-page-header > div:first-child {
            position: relative;
            z-index: 2;
            width: 65%;
        }

        .case-page-header .eyebrow {
            margin: 0;
            color: #861b17 !important;
            font-family: Arial, sans-serif;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 3px;
            line-height: 1;
            text-transform: uppercase;
        }

        .case-page-header h1 {
            margin: 14px 0 12px;
            color: #111 !important;
            font-family: Georgia, serif;
            font-size: clamp(36px, 4vw, 62px);
            font-weight: 600;
            letter-spacing: 0;
            line-height: 1.05;
        }

        .case-page-header .dashboard-subtitle {
            max-width: 760px;
            margin: 0;
            color: #34302d !important;
            font-family: Arial, sans-serif;
            font-size: 16px;
            line-height: 1.5;
        }

        .case-header__art {
            position: absolute;
            z-index: 1;
            top: 0;
            right: 0;
            width: 43%;
            height: 100%;
            color: #d7a58d;
            opacity: .78;
        }

        .case-header__art::before {
            content: '';
            position: absolute;
            right: 13%;
            bottom: 2%;
            width: 82%;
            height: 30%;
            background: radial-gradient(ellipse at center, rgba(235, 205, 167, .48) 0 42%, transparent 43%);
            border-radius: 50%;
        }

        .case-header__art::after {
            content: '';
            position: absolute;
            top: 23%;
            left: 4%;
            width: 76%;
            height: 42%;
            border: 1px solid rgba(215, 165, 141, .45);
            border-left-color: transparent;
            border-radius: 50%;
            transform: rotate(-13deg);
        }

        .case-header__art i {
            position: absolute;
            z-index: 2;
            font-family: 'Font Awesome 6 Free';
            font-size: 28px;
            font-style: normal;
            font-weight: 900;
        }

        .case-header__art .art-chat {
            top: 57%;
            left: 2%;
            padding: 12px 14px;
            border: 2px solid rgba(215, 165, 141, .7);
            border-radius: 9px;
            font-size: 15px;
        }

        .case-header__art .art-chat::after {
            content: '';
            position: absolute;
            right: 12px;
            bottom: -9px;
            width: 14px;
            height: 14px;
            border-right: 2px solid rgba(215, 165, 141, .7);
            border-bottom: 2px solid rgba(215, 165, 141, .7);
            background: #f8f1e7;
            transform: rotate(45deg);
        }

        .case-header__art .art-file {
            top: 17%;
            left: 39%;
            padding: 16px 19px;
            border: 2px solid rgba(215, 165, 141, .58);
            border-radius: 9px;
            box-shadow: 20px 10px 0 -1px #f8f1e7, 20px 10px 0 1px rgba(215, 165, 141, .48), 36px 20px 0 -1px #f8f1e7, 36px 20px 0 1px rgba(215, 165, 141, .42);
            font-size: 35px;
        }

        .case-header__art .art-card {
            top: 35%;
            left: 47%;
            padding: 13px 16px;
            border: 2px solid rgba(215, 165, 141, .65);
            border-radius: 8px;
            background: rgba(248, 241, 231, .7);
            font-size: 28px;
        }

        .case-header__art .art-check {
            top: 22%;
            right: 7%;
            padding: 12px;
            border: 2px solid #e3b968;
            border-radius: 50%;
            color: #e3b968;
            font-size: 21px;
        }

        .case-header__art .art-pin {
            bottom: 10%;
            left: 42%;
            width: 31px;
            height: 31px;
            color: transparent;
            background: #cf9589;
            border-radius: 50% 50% 50% 0;
            font-size: 0;
            transform: rotate(-45deg);
        }

        .case-header__art .art-pin::after {
            content: '';
            position: absolute;
            top: 9px;
            left: 9px;
            width: 13px;
            height: 13px;
            background: #f8f1e7;
            border-radius: 50%;
        }

        .case-header__art .art-dots {
            right: 25%;
            bottom: 12%;
            color: #d7a58d;
            font-size: 28px;
        }

        .management-content {
            background: white;
            border-radius: 16px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
            padding: 26px;
            margin-bottom: 24px;
        }

        .main-tabs-container {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 24px;
            background: #f8f9fa;
            border-radius: 10px 10px 0 0;
            overflow: hidden;
        }

        .main-tab-link {
            flex: 1;
            padding: 16px 22px;
            border: none;
            background: none;
            cursor: pointer;
            color: #55637c;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            border-bottom: 3px solid transparent;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .main-tab-link:hover {
            color: #3498db;
            background: rgba(52, 152, 219, 0.08);
        }

        .main-tab-link.active {
            color: #3498db;
            background: white;
            border-bottom-color: #3498db;
        }

        .main-tab-content {
            display: none;
        }

        .main-tab-content.active {
            display: block;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 18px;
            padding-bottom: 18px;
            border-bottom: 1px solid #e9ecef;
        }

        .section-header h2 {
            margin: 0;
            font-size: 1.4rem;
            color: #1f2937;
        }

        .tabs-container {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 20px;
            overflow-x: auto;
        }

        .tab-link {
            padding: 14px 20px;
            border: none;
            background: none;
            cursor: pointer;
            color: #566674;
            font-size: 0.94rem;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            white-space: nowrap;
        }

        .tab-link:hover {
            color: #3498db;
            background: #f8f9fa;
        }

        .tab-link.active {
            color: #3498db;
            border-bottom-color: #3498db;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .tab-badge {
            background: #e9ecef;
            color: #475569;
            border-radius: 999px;
            padding: 3px 9px;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .sub-tabs-container {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #e9ecef;
            background: #f8f9fa;
            border-radius: 10px;
            overflow-x: auto;
            padding: 0 4px;
        }

        .sub-tab-link {
            padding: 12px 18px;
            border: none;
            background: none;
            cursor: pointer;
            color: #566674;
            font-size: 0.92rem;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            white-space: nowrap;
        }

        .sub-tab-link:hover {
            color: #3498db;
        }

        .sub-tab-link.active {
            color: #3498db;
            border-bottom-color: #3498db;
            background: white;
            border-radius: 8px 8px 0 0;
        }

        .sub-tab-content {
            display: none;
        }

        .sub-tab-content.active {
            display: block;
        }

        .event-grid {
            display: grid;
            gap: 16px;
        }

        .event-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 16px;
            transition: all 0.3s ease;
        }

        .event-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: #3498db;
        }

        .event-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .event-item-title {
            margin: 0;
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .event-item-actions {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .btn-edit {
            background: #3498db;
            color: white;
        }

        .btn-edit:hover {
            background: #2980b9;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        .event-item-details {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .event-item-details p {
            margin: 4px 0;
        }

        .event-status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            background: #3498db;
            color: white;
        }
        .event-type-pill {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: capitalize;
            background: #f3f4f6;
            color: #334155;
            margin-right: 8px;
        }

        .status-upcoming { background: #3498db; }
        .status-ongoing { background: #f39c12; }
        .status-completed { background: #27ae60; }
        .status-cancelled { background: #e74c3c; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 3rem;
            color: #bdc3c7;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            margin: 0 0 8px 0;
            color: #2c3e50;
        }

        .empty-state p {
            margin: 0;
            color: #666;
        }

        .image-preview-list {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .image-preview-card {
            position: relative;
            width: 90px;
            height: 90px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e9ecef;
            background: #fff;
        }

        .image-preview-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .announcement-image-card {
            cursor: pointer;
        }

        .image-lightbox-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
        }

        .image-lightbox-overlay.open {
            display: flex;
        }

        .image-lightbox-content {
            position: relative;
            max-width: 95%;
            max-height: 95%;
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,0.35);
        }

        .image-lightbox-content img {
            width: 100%;
            height: auto;
            display: block;
            max-height: calc(100vh - 120px);
            object-fit: contain;
            background: #000;
        }

        .image-lightbox-close {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 36px;
            height: 36px;
            border: none;
            border-radius: 50%;
            background: rgba(0,0,0,0.65);
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }

        .image-preview-remove {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: none;
            background: rgba(0,0,0,0.6);
            color: white;
            font-size: 0.8rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-add {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 22px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: background 0.2s ease;
        }

        .btn-add:hover {
            background: #2779bd;
        }

        .calendar-panel {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 24px;
            align-items: start;
        }

        .calendar-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
            overflow: hidden;
        }

        .calendar-header {
            padding: 22px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .calendar-title {
            margin: 0;
            font-size: 1.2rem;
            color: #111827;
        }

        .calendar-nav {
            display: flex;
            gap: 8px;
        }

        .calendar-nav button {
            border: none;
            background: #f8fafc;
            color: #1f2937;
            padding: 10px 14px;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .calendar-nav button:hover {
            background: #e2e8f0;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 8px;
            padding: 18px;
            background: #f8fafc;
        }

        .calendar-cell {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            min-height: 110px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .calendar-cell:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
        }

        .calendar-cell.disabled {
            opacity: 0.45;
            cursor: default;
            transform: none;
            box-shadow: none;
        }

        .calendar-cell .day-number {
            font-weight: 700;
            color: #111827;
        }

        .calendar-cell .day-note {
            margin-top: 10px;
            color: #475569;
            font-size: 0.82rem;
            line-height: 1.4;
        }

        .calendar-cell.holiday {
            border-color: #ef4444;
            background: #fef2f2;
        }

        .calendar-cell.sunday {
            border-color: #9ca3af;
            background: #f3f4f6;
        }

        .calendar-cell.no-class {
            border-color: #f59e0b;
            background: #fffbeb;
        }

        .calendar-cell.today {
            box-shadow: inset 0 0 0 2px #3b82f6;
        }

        .legend-list {
            display: grid;
            gap: 12px;
            padding: 20px 24px 24px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            color: #475569;
        }

        .legend-badge {
            width: 14px;
            height: 14px;
            border-radius: 4px;
            display: inline-block;
        }

        .legend-holiday { background: #fecaca; }
        .legend-sunday { background: #d1d5db; }
        .legend-no-class { background: #fef08a; }

        .calendar-day-info {
            padding: 20px 24px 24px;
            font-size: 0.95rem;
            color: #475569;
            line-height: 1.6;
        }

        .calendar-day-info strong {
            display: block;
            margin-bottom: 8px;
            color: #111827;
        }

        .calendar-actions {
            margin-top: 14px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .calendar-actions .secondary-button {
            background: #f3f4f6;
            color: #111827;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 10px 18px;
            cursor: pointer;
        }

        .calendar-actions .secondary-button:hover {
            background: #e5e7eb;
        }

        .event-form-group {
            display: grid;
            gap: 14px;
        }

        @media (max-width: 980px) {
            .section-header {
                flex-direction: column;
                align-items: stretch;
                gap: 14px;
            }

            .section-header > div {
                width: 100%;
            }

            .section-header .btn-add {
                width: 100%;
                justify-content: center;
            }

            .tabs-container,
            .main-tabs-container,
            .sub-tabs-container {
                gap: 10px;
                overflow-x: auto;
                padding-bottom: 10px;
            }

            .tab-link,
            .main-tab-link,
            .sub-tab-link {
                flex: 0 0 auto;
                min-width: 150px;
            }

            .event-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 16px;
                width: 100%;
            }

            .event-item {
                width: 100%;
                padding: 16px;
                box-shadow: none;
            }

            .event-item-header {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .event-item-actions {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
            }

            .event-item-actions button {
                flex: 1 1 auto;
                min-width: 120px;
            }

            .event-item-details {
                margin-bottom: 10px;
                white-space: normal;
            }

            .tab-link,
            .main-tab-link,
            .sub-tab-link {
                white-space: normal;
                min-width: 120px;
            }

            .calendar-panel {
                grid-template-columns: 1fr;
                align-items: stretch;
                gap: 18px;
            }

            .calendar-header {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .calendar-nav {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
            }

            .calendar-card {
                width: 100%;
                min-width: 0;
            }

            .calendar-grid {
                gap: 6px;
                padding: 14px;
            }

            .calendar-cell {
                min-height: 90px;
                padding: 10px;
            }

            .calendar-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .calendar-actions .event-form-group,
            .calendar-actions .secondary-button,
            .calendar-actions button {
                width: 100%;
            }

            .calendar-actions .event-form-group > div {
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }

            .calendar-day-info {
                padding: 16px;
            }

            .announcement-list {
                padding: 18px 16px 18px 16px;
            }

            .announcement-card {
                width: 100%;
                padding: 16px;
                margin-bottom: 14px;
            }

            .announcement-card p {
                margin-bottom: 10px;
            }

            .announcement-card strong {
                font-size: 1rem;
            }

            .image-preview-list {
                width: 100%;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
                gap: 10px;
            }

            #announcementImagePreviewContainer,
            #announcementUploadedImagesContainer {
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)) !important;
            }

            .announcement-empty {
                padding: 24px 16px;
            }
        }

        @media (max-width: 720px) {
            html,
            body {
                height: auto !important;
                min-height: 100%;
                overflow-x: hidden;
                overflow-y: scroll !important;
                position: static !important;
            }

            .page-shell {
                height: auto !important;
                min-height: 100vh !important;
                max-height: none !important;
                overflow: visible !important;
                position: static !important;
            }

            .page-content {
                display: block !important;
                height: auto !important;
                min-width: 0;
                min-height: 0 !important;
                max-height: none !important;
                position: static !important;
                overflow: visible !important;
            }

            .main-scroll {
                display: block !important;
                flex: none !important;
                height: auto !important;
                min-width: 0;
                min-height: 0;
                max-height: none !important;
                position: static !important;
                overflow-x: visible !important;
                overflow-y: visible !important;
                -webkit-overflow-scrolling: touch;
            }

            .management-content,
            section.dashboard-section,
            #add-announcements .calendar-card,
            #add-announcements .calendar-actions,
            #add-announcements .calendar-grid,
            #add-announcements .calendar-cell,
            #add-announcements .calendar-header,
            #add-announcements .calendar-nav,
            #add-announcements .calendar-day-info,
            #add-announcements .announcement-list,
            #add-announcements .announcement-card,
            #add-announcements .image-preview-list,
            #add-announcements #announcementImagePreviewContainer,
            #add-announcements #announcementUploadedImagesContainer,
            #view-announcements .calendar-card,
            #view-announcements .announcement-list,
            #view-announcements .announcement-card,
            #view-announcements .image-preview-list,
            #view-announcements #announcementImagePreviewContainer,
            #view-announcements #announcementUploadedImagesContainer {
                max-width: 100% !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }

            .management-content {
                padding: 18px !important;
                overflow: visible !important;
            }

            #add-announcements .calendar-panel,
            #view-announcements .calendar-panel {
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 18px !important;
            }

            #add-announcements .calendar-header,
            #view-announcements .calendar-header {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 12px !important;
                padding: 16px !important;
            }

            #add-announcements .calendar-nav,
            #view-announcements .calendar-nav {
                width: 100% !important;
                justify-content: flex-start !important;
                flex-wrap: wrap !important;
            }

            #add-announcements .calendar-actions,
            #view-announcements .calendar-actions {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 12px !important;
            }

            #add-announcements .calendar-actions .event-form-group > div,
            #view-announcements .calendar-actions .event-form-group > div {
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }

            #add-announcements .calendar-grid,
            #view-announcements .calendar-grid {
                padding: 12px !important;
                gap: 8px !important;
                grid-template-columns: repeat(7, minmax(0, 1fr)) !important;
            }

            #add-announcements .calendar-cell,
            #view-announcements .calendar-cell {
                min-height: 72px !important;
                padding: 10px !important;
            }

            #add-announcements .announcement-list,
            #view-announcements .announcement-list {
                padding: 16px !important;
            }

            #add-announcements .announcement-card,
            #view-announcements .announcement-card {
                width: 100% !important;
                padding: 16px !important;
                margin-bottom: 14px !important;
            }

            #add-announcements .announcement-card p,
            #view-announcements .announcement-card p {
                margin-bottom: 10px !important;
                white-space: normal !important;
            }

            #add-announcements .image-preview-list,
            #view-announcements .image-preview-list {
                display: grid !important;
                grid-template-columns: repeat(auto-fit, minmax(90px, 1fr)) !important;
                gap: 10px !important;
            }

            #add-announcements #announcementImagePreviewContainer,
            #add-announcements #announcementUploadedImagesContainer,
            #view-announcements #announcementImagePreviewContainer,
            #view-announcements #announcementUploadedImagesContainer {
                display: grid !important;
                grid-template-columns: repeat(auto-fit, minmax(90px, 1fr)) !important;
                gap: 10px !important;
            }

            .announcement-empty {
                padding: 20px 16px !important;
                font-size: 0.95rem !important;
            }
        }

        @media (max-width: 560px) {
            #add-announcements .calendar-grid,
            #view-announcements .calendar-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }

        @media (max-width: 420px) {
            html, body, .management-content, section.dashboard-section,
            #add-announcements, #view-announcements,
            .calendar-card, .calendar-panel, .calendar-header,
            .calendar-actions, .calendar-grid, .calendar-cell,
            .announcement-list, .announcement-card,
            #announcementImagePreviewContainer, #announcementUploadedImagesContainer {
                min-width: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                overflow-x: hidden !important;
            }

            .management-content,
            section.dashboard-section {
                padding-right: 0 !important;
                margin-right: 0 !important;
            }

            .sub-tabs-container {
                padding-right: 0 !important;
                overflow-x: auto !important;
            }

            .sub-tab-link {
                min-width: 0 !important;
                flex: 1 1 auto !important;
                white-space: normal !important;
            }

            #add-announcements .calendar-grid,
            #view-announcements .calendar-grid {
                grid-template-columns: 1fr !important;
                padding: 0 !important;
                gap: 10px !important;
            }

            #add-announcements .calendar-cell,
            #view-announcements .calendar-cell {
                min-height: auto !important;
                padding: 12px !important;
            }

            #add-announcements .calendar-actions .event-form-group > div,
            #view-announcements .calendar-actions .event-form-group > div {
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 12px !important;
                min-width: 0 !important;
            }

            .calendar-list-right {
                min-width: 0 !important;
                text-align: left !important;
            }

            #announcementImagePreviewContainer,
            #announcementUploadedImagesContainer {
                grid-template-columns: 1fr !important;
            }

            .calendar-header {
                padding-left: 12px !important;
                padding-right: 12px !important;
            }

            .calendar-actions {
                padding-left: 0 !important;
                padding-right: 0 !important;
            }

            .announcement-card {
                overflow-x: hidden !important;
            }

            #view-announcements .announcement-card > div:first-child {
                flex-wrap: wrap !important;
                justify-content: space-between !important;
                gap: 8px !important;
            }

            #view-announcements .announcement-card > div:first-child > div {
                min-width: 0 !important;
                flex: 1 1 100% !important;
            }

            #view-announcements .announcement-card > div:first-child span {
                white-space: normal !important;
                width: 100% !important;
            }

            #view-announcements .announcement-card > div:last-child {
                flex-wrap: wrap !important;
                gap: 8px !important;
            }

            #view-announcements .announcement-card > div:last-child button {
                min-width: 0 !important;
                flex: 1 1 100% !important;
            }
        }

        @media (max-width: 720px) {
            #calendar-grid.calendar-list {
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 10px !important;
                padding: 10px 0 !important;
            }

            #calendar-grid.calendar-list .calendar-cell {
                min-height: auto !important;
                padding: 14px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 12px !important;
                border-radius: 14px !important;
            }

            #calendar-grid.calendar-list .calendar-list-left {
                display: flex;
                flex-direction: column;
                gap: 4px;
                min-width: 80px;
            }

            #calendar-grid.calendar-list .calendar-list-weekday {
                font-size: 0.85rem;
                color: #6b7280;
            }

            #calendar-grid.calendar-list .calendar-list-right {
                text-align: right;
                font-size: 0.92rem;
                color: #334155;
                line-height: 1.4;
            }

            #calendar-grid.calendar-list .calendar-cell .day-number {
                font-size: 1.15rem;
            }

            #calendar-grid.calendar-list .calendar-cell .day-note {
                margin-top: 0 !important;
            }
        }

        @media (max-width: 640px) {
            .section-header {
                padding-bottom: 12px;
            }

            .tab-link,
            .main-tab-link,
            .sub-tab-link {
                min-width: 120px;
                padding: 12px 14px;
                font-size: 0.9rem;
            }

            .event-item {
                padding: 14px;
            }

            .event-item-title {
                font-size: 1rem;
            }

            .event-item-details {
                font-size: 0.88rem;
            }

            .event-item-actions {
                gap: 8px;
            }

            .event-item-actions button {
                min-width: 100px;
            }

            .calendar-panel {
                grid-template-columns: 1fr;
            }

            .calendar-grid {
                padding: 12px;
                gap: 6px;
            }

            .calendar-cell {
                min-height: 72px;
                padding: 10px;
            }

            .btn-add {
                width: 100%;
            }

            .calendar-header {
                padding: 16px;
            }

            .announcement-list {
                padding: 14px;
            }

            .announcement-card {
                padding: 14px;
            }

            .image-preview-card {
                width: 82px;
                height: 82px;
            }

            .announcement-empty {
                padding: 24px 12px;
                font-size: 0.95rem;
            }

            .announcement-list,
            .calendar-day-info {
                width: 100%;
            }

            .calendar-actions .event-form-group > div {
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 12px !important;
            }

            .image-preview-list {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(90px, 1fr));
                gap: 10px;
                width: 100%;
            }

            #announcementImagePreviewContainer,
            #announcementUploadedImagesContainer {
                display: grid !important;
                grid-template-columns: repeat(auto-fit, minmax(90px, 1fr)) !important;
                gap: 10px !important;
            }
        }

        .event-form-group label {
            font-size: 0.95rem;
            font-weight: 600;
            color: #1f2937;
        }

        .event-form-group input,
        .event-form-group textarea,
        .event-form-group select {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid #d1d5db;
            font-size: 0.95rem;
            color: #111827;
            background: white;
        }

        .event-form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .event-form-actions {
            margin-top: 18px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background-color: white;
            padding: 0;
            border-radius: 12px;
            width: 100%;
            max-width: 600px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: #2c3e50;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .modal-body {
            padding: 20px 24px;
            display: grid;
            gap: 18px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #2c3e50;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .form-group textarea {
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .modal-actions {
            padding: 20px 24px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-primary {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        /* Notification System */
        .notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 400px;
        }

        .notification {
            background: #4caf50;
            color: white;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-weight: 500;
            animation: slideIn 0.3s ease-out;
            margin-bottom: 10px;
        }

        .notification.error {
            background: #f44336;
        }

        .notification.success {
            background: #4caf50;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .message-card {
            margin-bottom: 18px;
        }

        .message-card.alert.success {
            background: #dcfce7;
            color: #166534;
        }

        .message-card.alert.error {
            background: #fee2e2;
            color: #b91c1c;
        }

        [data-tooltip] {
            position: relative;
        }

        .side-nav.collapsed [data-tooltip]:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: #333;
            color: #fff;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
            margin-left: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .side-nav.collapsed [data-tooltip]:hover::before {
            content: '';
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            border: 6px solid transparent;
            border-right-color: #333;
            margin-left: 2px;
            z-index: 1000;
        }

        /* Announcement Images Styles */
        .event-form-group {
            display: grid;
            gap: 10px;
        }

        .event-form-group label {
            display: block;
            margin-bottom: 4px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .event-form-group input[type="file"],
        .event-form-group input[type="text"],
        .event-form-group input[type="date"],
        .event-form-group input[type="time"],
        .event-form-group select,
        .event-form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.2s ease;
        }

        .event-form-group input[type="file"] {
            padding: 12px 14px;
            background: #f8fafc;
            cursor: pointer;
        }

        .event-form-group input[type="file"]::file-selector-button {
            padding: 8px 14px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            margin-right: 8px;
        }

        .event-form-group input[type="file"]::file-selector-button:hover {
            background: #2980b9;
        }

        .event-form-group input[type="text"]:focus,
        .event-form-group input[type="date"]:focus,
        .event-form-group input[type="time"]:focus,
        .event-form-group select:focus,
        .event-form-group textarea:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .event-form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .main-tabs-container:has(+ #events-tab.active) .main-tab-link[data-main-tab="events-tab"],
        .main-tabs-container:has(+ #events-tab.active) .main-tab-link[data-main-tab="events-tab"]:hover {
            color: #800000;
            background: white;
            border-bottom-color: #800000;
        }

        .main-tabs-container:has(~ #calendar-tab.active) .main-tab-link[data-main-tab="calendar-tab"],
        .main-tabs-container:has(~ #calendar-tab.active) .main-tab-link[data-main-tab="calendar-tab"]:hover {
            color: #800000;
            background: white;
            border-bottom-color: #800000;
        }

        #events-tab .tabs-container .tab-link:hover,
        #events-tab .tabs-container .tab-link.active {
            color: #800000;
        }

        #events-tab .tabs-container .tab-link.active {
            border-bottom-color: #800000;
        }

        #events-tab .event-item:hover {
            border-color: #800000;
        }

        #events-tab .event-item-actions .btn-edit,
        #events-tab .event-status-badge,
        #events-tab .status-upcoming,
        #events-tab .btn-add {
            background: #800000 !important;
        }

        #events-tab .event-item-actions .btn-edit:hover,
        #events-tab .btn-add:hover {
            background: #660000 !important;
        }

        #calendar-tab .sub-tab-link:hover,
        #calendar-tab .sub-tab-link.active {
            color: #800000;
        }

        #calendar-tab .sub-tab-link.active {
            border-bottom-color: #800000;
        }

        #calendar-tab .btn-add,
        #calendar-tab .event-form-group input[type="file"]::file-selector-button {
            background: #800000 !important;
        }

        #calendar-tab .btn-add:hover,
        #calendar-tab .event-form-group input[type="file"]::file-selector-button:hover {
            background: #660000 !important;
        }

        #calendar-tab .calendar-nav button:hover {
            background: #f3e6e6;
            color: #800000;
        }

        #calendar-tab .calendar-cell.today {
            box-shadow: inset 0 0 0 2px #800000;
        }

        #calendar-tab .event-form-group input[type="text"]:focus,
        #calendar-tab .event-form-group input[type="date"]:focus,
        #calendar-tab .event-form-group input[type="time"]:focus,
        #calendar-tab .event-form-group select:focus,
        #calendar-tab .event-form-group textarea:focus {
            border-color: #800000;
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1);
        }

        #calendar-tab .announcement-card {
            border-color: #ead6d6 !important;
        }

        #calendar-tab .announcement-card button[onclick^="editCalendarBlock"] {
            background: #800000 !important;
        }

        small {
            display: block;
            color: #6b7280;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        #announcementImagePreviewContainer {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px;
        }

        .announcement-image-card {
            position: relative;
            width: 100%;
            border-radius: 8px;
            overflow: hidden;
            background: #f0f0f0;
            aspect-ratio: 1 / 1;
            cursor: grab;
            transition: all 0.2s ease;
            border: 2px solid transparent;
        }

        .announcement-image-card:active {
            cursor: grabbing;
        }

        .announcement-image-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .announcement-image-card[draggable="true"]:hover {
            border-color: #e0e0e0;
        }

        #announcementUploadedImagesContainer {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px;
        }

        .calendar-actions {
            padding: 22px 24px;
            display: grid;
            gap: 12px;
        }

        .calendar-actions form {
            display: grid;
            gap: 12px;
        }

        @keyframes spin-refresh {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 768px) {
            body.swipe-refresh-enabled {
                overscroll-behavior-y: none;
            }
        }
    </style>
    <!-- EmailJS for Graduation Congratulations -->
    <script>
        window.emailJsGraduationConfig = {
            serviceId: 'service_29ejn1d',
            templateId: 'template_ywgtohl',
            publicKey: 'IXC8qLoI_tNcsslwC'
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof emailjs !== 'undefined') {
                emailjs.init(window.emailJsGraduationConfig.publicKey);
            }
        });
    </script>
</head>
<body class="swipe-refresh-enabled">
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; inset: 0; background: rgba(255, 255, 255, 0.92); z-index: 9999; justify-content: center; align-items: center;"><div style="text-align: center;"><div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div><p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0; text-align: center;">Refreshing...</p></div></div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                
            </div>
            <div class="nav-section">
                <a href="admin_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-user-shield"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="admin_assignments.php" data-tooltip="Head Assignments">
                    <span class="nav-icon"><i class="fa-solid fa-user-tie"></i></span>
                    <span class="nav-text">Head Assignments</span>
                </a>
                <a href="admin_users.php" data-tooltip="Users">
                    <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                    <span class="nav-text">Users</span>
                </a>
                <a href="admin_analytics.php" data-tooltip="Analytics">
                    <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                    <span class="nav-text">Analytics</span>
                </a>
                <a href="admin_calendar_manager.php" class="active" data-tooltip="School Calendar">
                    <span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span>
                    <span class="nav-text">School Calendar</span>
                </a>
                <a href="admin_settings.php" data-tooltip="Settings">
                    <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
                    <span class="nav-text">Settings</span>
                </a>
                <a href="feedback_and_ratings.php" data-tooltip="Feedback">
                    <span class="nav-icon"><i class="fa-solid fa-star"></i></span>
                    <span class="nav-text">Feedback</span>
                </a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $ssaaOpen ? 'true' : 'false' ?>" data-tooltip="SSAA Management">
                        <span class="nav-icon"><i class="fa-solid fa-building-columns"></i></span>
                        <span class="nav-text">SSAA Management</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu<?= $ssaaOpen ? ' open' : '' ?>" aria-hidden="<?= $ssaaOpen ? 'false' : 'true' ?>">
                        <a href="student_promotion.php" data-tooltip="Student Promotion">
                            <span class="nav-icon"><i class="fa-solid fa-arrow-up"></i></span>
                            <span class="nav-text">Student Promotion</span>
                        </a>
                        <a href="alumni_management.php" data-tooltip="Alumni Management">
                            <span class="nav-icon"><i class="fa-solid fa-user-group"></i></span>
                            <span class="nav-text">Alumni Management</span>
                        </a>
                        <a href="reports_analytics.php" data-tooltip="Reports & Analytics">
                            <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                            <span class="nav-text">Reports & Analytics</span>
                        </a>
                    </div>
                </div>
            </div>
            <div class="nav-footer">
                <a href="../logout.php" class="logout-link" data-tooltip="Logout">
                    <span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
        </aside>
        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Open mobile menu"><i class="fa-solid fa-bars"></i></button>
                    <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>Admin Portal</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Admin</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <!-- Profile button removed per request -->
                    <!-- support icon removed per UI update -->

                    <div class="topbar-menu" id="notificationMenu" role="menu" aria-label="Notifications menu">
                        <div class="menu-header">
                            <strong>Notifications</strong>
                            <span class="menu-note">Latest system alerts</span>
                        </div>
                        <div class="menu-empty">No new notifications.</div>
                        <a class="menu-link menu-footer-link" href="admin_analytics.php">View system analytics</a>
                    </div>

                    <div class="topbar-menu" id="profileMenu" role="menu" aria-label="Profile menu">
                        <div class="menu-item profile-menu-item" role="menuitem">
                            <a href="admin_assignments.php" class="menu-link profile-link"><?= htmlspecialchars($user['full_name']) ?></a>
                            <span class="menu-subtext">Manage admin assignments</span>
                        </div>
                        <a href="admin_users.php" class="menu-action">View users</a>
                    </div>

                    <div class="topbar-menu" id="supportMenu" role="menu" aria-label="Support menu">
                        <div class="menu-header">
                            <strong>Support</strong>
                            <span class="menu-note">Need help?</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="admin_settings.php">System settings</a>
                            <span class="menu-subtext">Configure system policies and access.</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="../about.php">Help center</a>
                        </div>
                    </div>
                </div>
            </header>
            <div class="main-scroll">
                <div class="page-overlay" id="pageOverlay"></div>
                    <section class="dashboard-intro case-page-header">
                        <div>
                            <p class="eyebrow">School calendar</p>
                            <h1>Events and No-Class Schedule</h1>
                            <p class="dashboard-subtitle">Add SSC events for the student dashboard and manage school holiday/no-class days in one calendar view.</p>
                        </div>
                        <div class="case-header__art" aria-hidden="true">
                            <i class="fa-solid fa-file-lines art-file"></i>
                            <i class="fa-solid fa-user-tie art-card"></i>
                            <i class="fa-solid fa-clipboard-check art-chat"></i>
                            <i class="fa-solid fa-check art-check"></i>
                            <i class="fa-solid fa-location-dot art-pin"></i>
                            <i class="fa-solid fa-leaf art-dots"></i>
                        </div>
                    </section>
                    <?php if (!empty($message)): ?>
                        <section class="dashboard-section">
                            <div class="dashboard-summary-card <?= $messageType === 'error' ? 'alert error' : 'alert success' ?> message-card">
                                <?= htmlspecialchars($message) ?>
                            </div>
                        </section>
                    <?php endif; ?>
                    <section class="dashboard-section">
                        <div class="management-content">
                            <div class="main-tabs-container">
                                <button class="main-tab-link active" data-main-tab="events-tab">
                                    <i class="fa-solid fa-calendar-days"></i> Events & Activities
                                </button>
                                <button class="main-tab-link" data-main-tab="calendar-tab">
                                    <i class="fa-solid fa-calendar-week"></i> Important Announcements
                                </button>
                            </div>

                            <div id="events-tab" class="main-tab-content active">
                                <div class="section-header">
                                    <h3>SSC Events Manager</h3>
                                    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                                        <button class="btn-add" onclick="showEventModal()">
                                            <i class="fa-solid fa-plus"></i> Add Event
                                        </button>
                                        <button class="btn-add" onclick="showAnnouncementModal()">
                                            <i class="fa-solid fa-bullhorn"></i> Add Announcement
                                        </button>
                                    </div>
                                </div>

                                <div class="tabs-container">
                                    <button class="tab-link active" data-tab="all-events">
                                        <i class="fa-solid fa-list"></i> All Events
                                        <span class="tab-badge" id="badge-all">0</span>
                                    </button>
                                    <button class="tab-link" data-tab="announcements-events">
                                        <i class="fa-solid fa-bullhorn"></i> Announcements
                                        <span class="tab-badge" id="badge-announcements">0</span>
                                    </button>
                                    <button class="tab-link" data-tab="upcoming-events">
                                        <i class="fa-solid fa-calendar-check"></i> Upcoming
                                        <span class="tab-badge" id="badge-upcoming">0</span>
                                    </button>
                                                    <button class="tab-link" data-tab="completed-events">
                                        <i class="fa-solid fa-circle-check"></i> Completed
                                        <span class="tab-badge" id="badge-completed">0</span>
                                    </button>
                                </div>

                                <div id="all-events" class="tab-content active">
                                    <div id="events-all" class="event-grid">
                                        <!-- Events will be loaded here -->
                                    </div>
                                </div>
                                <div id="announcements-events" class="tab-content">
                                    <div id="events-announcements" class="event-grid">
                                        <!-- Announcements will be loaded here -->
                                    </div>
                                </div>
                                <div id="upcoming-events" class="tab-content">
                                    <div id="events-upcoming" class="event-grid">
                                        <!-- Upcoming events will be loaded here -->
                                    </div>
                                </div>
                                <div id="completed-events" class="tab-content">
                                    <div id="events-completed" class="event-grid">
                                        <!-- Completed events will be loaded here -->
                                    </div>
                                </div>
                            </div>

                            <div id="calendar-tab" class="main-tab-content">
                                <div class="section-header" style="display:flex; align-items:center; justify-content:space-between; gap:16px;">
                                    <h2>Important Announcements</h2>
                                </div>
                                
                                <!-- Sub-tabs for Important Announcements section -->
                                <div class="sub-tabs-container" style="margin-bottom: 20px;">
                                    <button class="sub-tab-link active" data-sub-tab="add-announcements">
                                        <i class="fa-solid fa-plus-circle"></i> Important Announcements
                                    </button>
                                    <button class="sub-tab-link" data-sub-tab="view-announcements">
                                        <i class="fa-solid fa-eye"></i> View Announcements
                                    </button>
                                    <!-- Graduation Congratulations tab removed per request -->
                                </div>
                                
                                <div id="add-announcements" class="sub-tab-content active">
                                <div class="calendar-panel">
                                    <div class="calendar-card">
                                        <div class="calendar-header">
                                            <div>
                                                <h3 class="calendar-title" id="calendar-month-label"></h3>
                                                <p id="calendar-year-label" style="margin:0;color:#6b7280;font-size:0.95rem;"></p>
                                            </div>
                                            <div class="calendar-nav">
                                                <button type="button" id="prev-month"><i class="fa-solid fa-chevron-left"></i></button>
                                                <button type="button" id="next-month"><i class="fa-solid fa-chevron-right"></i></button>
                                            </div>
                                        </div>
                                        <div class="calendar-grid" id="calendar-grid"></div>
                                    </div>

                                    <form method="post" enctype="multipart/form-data" style="width:100%;" id="calendarBlockForm">
                                        <input type="hidden" name="action" value="add_calendar_block">
                                        <input type="hidden" id="block_id" name="block_id" value="0">
                                        <div class="calendar-card">
                                            <div class="calendar-header">
                                                <h3 class="calendar-title">Legend & Notes</h3>
                                            </div>
                                            <div class="legend-list">
                                                <div class="legend-item"><span class="legend-badge legend-holiday"></span> Legal holiday</div>
                                                <div class="legend-item"><span class="legend-badge legend-sunday"></span> Sunday / No classes</div>
                                                <div class="legend-item"><span class="legend-badge legend-no-class"></span> Admin no-class day</div>
                                            </div>
                                            <div class="calendar-day-info" id="calendar-day-info">
                                                <strong>Click a date</strong>
                                                Select a day to see holiday or school block details here.
                                            </div>
                                            <div class="calendar-actions">
                                                <div class="event-form-group">
                                                    <label for="event_date">Date</label>
                                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                                        <div>
                                                            <label for="start_date" style="font-size: 0.9rem; margin-bottom: 4px; display: block;">Start Date</label>
                                                            <input type="date" id="start_date" name="event_date" required>
                                                        </div>
                                                        <div>
                                                            <label for="end_date" style="font-size: 0.9rem; margin-bottom: 4px; display: block;">End Date</label>
                                                            <input type="date" id="end_date" name="end_date">
                                                        </div>
                                                    </div>
                                                    <label for="event_type">Event Type</label>
                                                    <select id="event_type" name="event_type">
                                                        <option value="holiday">Holiday</option>
                                                        <option value="no_class">No class</option>
                                                        <option value="graduation">Graduation</option>
                                                        <option value="exam">Exam</option>
                                                        <option value="acquaintance">Acquaintance</option>
                                                        <option value="intramurals">Intramurals</option>
                                                        <option value="others">Others</option>
                                                    </select>
                                                    <label for="start_time">Start Time</label>
                                                    <input type="time" id="start_time" name="start_time">
                                                    <label for="end_time">End Time</label>
                                                    <input type="time" id="end_time" name="end_time">
                                                    <div id="description-container">
                                                        <label for="description">Description</label>
                                                        <textarea id="description" name="description" placeholder="Add a description or important details (optional)"></textarea>
                                                    </div>
                                                </div>
                                                <button type="submit" class="btn-add" id="calendarSubmitButton" style="width:100%;justify-content:center;">Add Announcements</button>
                                            </div>
                                        </div>

                                        <div class="calendar-card">
                                            <div class="calendar-header">
                                                <h3 class="calendar-title">Announcement Images</h3>
                                            </div>
                                            <div class="calendar-actions">
                                                <div class="event-form-group">
                                                    <label for="announcementImages">Upload Images</label>
                                                    <input type="file" id="announcementImages" name="announcement_images[]" multiple accept="image/png,image/jpeg,image/webp,image/gif">
                                                    <small style="display:block; margin-top:8px; color:#64748b;">Select one or more images to upload. Supported formats: PNG, JPEG, WebP, GIF</small>
                                                </div>
                                            </div>
                                            <div id="announcementImagePreview" style="margin-top: 20px; display: none;">
                                                <h4 style="margin: 0 0 12px 0; color: #2c3e50; font-weight: 600;">Selected Images</h4>
                                                <div id="announcementImagePreviewContainer" class="image-preview-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px;"></div>
                                            </div>
                                            <div id="announcementUploadedImages" style="margin-top: 20px; display: none;">
                                                <h4 style="margin: 0 0 12px 0; color: #2c3e50; font-weight: 600;">Uploaded Images</h4>
                                                <div id="announcementUploadedImagesContainer" class="image-preview-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px;"></div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                </div>
                                
                                <div id="view-announcements" class="sub-tab-content">
                                    <div class="calendar-card">
                                        <div class="calendar-header">
                                            <h3 class="calendar-title">Important Announcements Feed</h3>
                                        </div>
                                        <div class="announcement-list" style="padding: 20px;">
                                            <?php if (empty($calendarAnnouncements)): ?>
                                                <div class="announcement-empty">No important announcements yet. Use the button above to add one.</div>
                                            <?php else: ?>
                                                <?php foreach ($calendarAnnouncements as $announcement):
                                                    // Determine title and body from description or event_type
                                                    if (!empty($announcement['description'])) {
                                                        $lines = preg_split('/\r\n|\r|\n/', trim($announcement['description']));
                                                        $title = htmlspecialchars($lines[0] ?: 'Announcement');
                                                        $body = htmlspecialchars(implode(' ', array_filter(array_slice($lines, 1), fn($line) => trim($line) !== '')));
                                                    } else {
                                                        // Use event_type as title if no description
                                                        $typeTitle = ucwords(str_replace('_', ' ', $announcement['event_type']));
                                                        $title = htmlspecialchars($typeTitle);
                                                        $body = '';
                                                    }
                                                    
                                                    // Show when the announcement was created, not the scheduled event date.
                                                    $createdDate = new DateTime($announcement['created_at'] ?? 'now');
                                                    $today = new DateTime('today');
                                                    $daysAgo = (int)$createdDate->diff($today)->format('%r%a');
                                                    $daysText = $daysAgo <= 0 ? 'Today' : ($daysAgo == 1 ? '1d ago' : $daysAgo . 'd ago');
                                                ?>
                                                    <div class="announcement-card" style="margin-bottom: 16px; padding: 16px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fafbff;">
                                                        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px;">
                                                            <div style="flex:1;">
                                                                <strong style="display:block; margin-bottom:4px;"><?= $title ?></strong>
                                                                <span style="font-size:0.85rem; color:#64748b;"><?= $daysText ?> • Public</span>
                                                            </div>
                                                            <span style="font-size:0.9rem; color:#475569; white-space:nowrap;"><?= htmlspecialchars($announcement['event_date']) ?></span>
                                                        </div>
                                                        
                                                        <?php if ($body !== ''): ?>
                                                            <p style="margin:0 0 12px 0; color:#334155; line-height:1.5;"><?= nl2br($body) ?></p>
                                                        <?php endif; ?>
                                                        
                                                        <?php 
                                                            // Show time if available
                                                            if (!empty($announcement['start_time']) || !empty($announcement['end_time'])):
                                                                $startTime = !empty($announcement['start_time']) ? substr($announcement['start_time'], 0, 5) : '';
                                                                $endTime = !empty($announcement['end_time']) ? substr($announcement['end_time'], 0, 5) : '';
                                                                $timeRange = '';
                                                                if ($startTime && $endTime) {
                                                                    $timeRange = "$startTime - $endTime";
                                                                } elseif ($startTime) {
                                                                    $timeRange = $startTime;
                                                                }
                                                        ?>
                                                            <p style="margin:8px 0; color:#64748b; font-size:0.95rem;">
                                                                <?php if ($timeRange): ?>
                                                                    <strong><?= $timeRange ?></strong>
                                                                <?php endif; ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!empty($announcement['images'])): ?>
                                                            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; margin-top: 12px;">
                                                                <?php foreach ($announcement['images'] as $image): ?>
                                                                    <img src="<?= htmlspecialchars('../' . ltrim($image, '/\\')) ?>" alt="Announcement image" style="width:100%; height:120px; object-fit:cover; border-radius:8px;">
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <div style="display:flex; gap:8px; margin-top:12px; padding-top:12px; border-top:1px solid #e2e8f0;">
                                                            <button type="button" onclick="editCalendarBlock(<?= $announcement['id'] ?>)" style="flex:1; padding:8px 12px; background:#3b82f6; color:white; border:none; border-radius:6px; cursor:pointer; font-size:0.9rem; display:flex; align-items:center; justify-content:center; gap:6px;">
                                                                <i class="fa-solid fa-pen-to-square"></i> Edit
                                                            </button>
                                                            <button type="button" onclick="deleteCalendarBlock(<?= $announcement['id'] ?>)" style="flex:1; padding:8px 12px; background:#ef4444; color:white; border:none; border-radius:6px; cursor:pointer; font-size:0.9rem; display:flex; align-items:center; justify-content:center; gap:6px;">
                                                                <i class="fa-solid fa-trash"></i> Delete
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Graduation congratulations UI removed; sending handled automatically via backend -->
                        </div>
                    </section>
            </div>
        </main>
    </div>

    <div id="eventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="eventModalTitle">Add New Event</h3>
                <button class="modal-close" onclick="closeModal('eventModal')">&times;</button>
            </div>
            <form id="eventForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="eventId" name="id">
                    <div class="form-group">
                        <label for="eventTitle">Event Title *</label>
                        <input type="text" id="eventTitle" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="eventDescription">Description</label>
                        <textarea id="eventDescription" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="eventDate">Event Date</label>
                            <input type="date" id="eventDate" name="event_date">
                        </div>
                        <div class="form-group">
                            <label for="eventTime">Event Time</label>
                            <input type="time" id="eventTime" name="event_time">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="eventLocation">Location</label>
                            <input type="text" id="eventLocation" name="location">
                        </div>
                        <div class="form-group">
                            <label for="eventOrganizer">Organizer</label>
                            <input type="text" id="eventOrganizer" name="organizer">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="eventPhotos">Event Images</label>
                        <input type="file" id="eventPhotos" name="event_photos[]" multiple accept="image/png,image/jpeg,image/webp">
                        <small style="display:block; margin-top:8px; color:#64748b;">Optional: upload multiple images for the event gallery.</small>
                    </div>
                    <div class="form-group" id="selectedPhotosPreview" style="display:none;">
                        <label>Selected Images</label>
                        <div id="selectedPhotosContainer" class="image-preview-list"></div>
                    </div>
                    <div class="form-group" id="currentPhotosPreview" style="display:none;">
                        <label>Current Images</label>
                        <div id="currentPhotosContainer" class="image-preview-list"></div>
                        <div id="currentPhotoRemovalInputs"></div>
                    </div>
                    <div id="graduationEmailStatus" style="display:none; margin-top: 16px;"></div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('eventModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Event</button>
                </div>
            </form>
        </div>
    </div>

    <div id="announcementModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Announcement</h3>
                <button class="modal-close" onclick="closeModal('announcementModal')">&times;</button>
            </div>
            <form id="announcementForm" enctype="multipart/form-data">
                <input type="hidden" id="announcementDate" name="event_date">
                <input type="hidden" name="event_type" value="others">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="announcementTitle">Announcement Title</label>
                        <input type="text" id="announcementTitle" name="title" placeholder="Announcement title">
                    </div>
                    <div class="form-group">
                        <label for="announcementDescription">Description</label>
                        <textarea id="announcementDescription" name="description" rows="4" placeholder="Write the announcement details..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="announcementModalImages">Announcement Images</label>
                        <input type="file" id="announcementModalImages" name="event_photos[]" multiple accept="image/png,image/jpeg,image/webp,image/gif">
                        <small style="display:block; margin-top:8px; color:#64748b;">Upload announcement images only. Date and time are not required.</small>
                    </div>
                    <div class="form-group" id="announcementSelectedPhotosPreview" style="display:none;">
                        <label>Selected Images</label>
                        <div id="announcementSelectedPhotosContainer" class="image-preview-list"></div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('announcementModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Add Announcement</button>
                </div>
            </form>
        </div>
    </div>

    <div id="calendarDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="calendarDetailsTitle">Calendar Details</h3>
                <button class="modal-close" onclick="closeModal('calendarDetailsModal')">&times;</button>
            </div>
            <div class="modal-body" id="calendarDetailsBody">
                <p>No details available.</p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal('calendarDetailsModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Notification System -->
    <div id="notification-container" class="notification-container"></div>

    <div id="announcementImageLightboxOverlay" class="image-lightbox-overlay" onclick="if (event.target === this) closeAnnouncementImageLightbox()">
        <div class="image-lightbox-content">
            <button class="image-lightbox-close" type="button" onclick="closeAnnouncementImageLightbox()">×</button>
            <img id="announcementImageLightboxImage" src="" alt="Announcement preview">
        </div>
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
    <script src="../assets/js/app.js" defer></script>
    <script>window.addEventListener('DOMContentLoaded', function(){ window.dispatchEvent(new Event('resize')); });</script>
    <script>
        // Event type description toggle
    (function(){ const sd=document.getElementById('sidebarToggle'); const side=document.querySelector('.side-nav'); if(sd){ sd.addEventListener('click', ()=>{ setTimeout(()=>window.dispatchEvent(new Event('resize')),60); }); } if(side){ side.addEventListener('mouseenter', ()=>window.dispatchEvent(new Event('resize'))); side.addEventListener('mouseleave', ()=>window.dispatchEvent(new Event('resize'))); } })();
        document.addEventListener('DOMContentLoaded', function() {
            const eventTypeSelect = document.getElementById('event_type');
            const descriptionContainer = document.getElementById('description-container');
            const calendarSubmitButton = document.getElementById('calendarSubmitButton');
            const calendarForm = document.getElementById('calendarBlockForm');

            function toggleDescription() {
                descriptionContainer.style.display = 'block';
            }

            function resetCalendarBlockForm() {
                calendarForm.reset();
                document.getElementById('block_id').value = '0';
                calendarSubmitButton.textContent = 'Add Announcements';
                toggleDescription();
            }

            eventTypeSelect.addEventListener('change', toggleDescription);
            toggleDescription(); // Initial check

            calendarForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                calendarSubmitButton.disabled = true;
                calendarSubmitButton.textContent = 'Processing...';

                const formData = new FormData(calendarForm);
                const eventType = formData.get('event_type');
                const eventDate = formData.get('event_date');
                
                try {
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });
                    
                    const responseText = await response.text();
                    
                    // Check if it was a graduation event that was processed
                    if (eventType === 'graduation' && eventDate <= new Date().toISOString().split('T')[0]) {
                        // Automatically send graduation emails via browser-side EmailJS (like Alumni Tracer)
                        await new Promise(resolve => setTimeout(resolve, 500)); // Wait a moment
                        await sendGraduationCongratulationEmails();
                    }
                    
                    // Reload page to show updated data
                    setTimeout(() => location.reload(), 2000);
                } catch (error) {
                    console.error('Error submitting form:', error);
                    calendarSubmitButton.disabled = false;
                    calendarSubmitButton.textContent = calendarSubmitButton.dataset.originalText || 'Add Announcements';
                } finally {
                    calendarSubmitButton.disabled = false;
                    calendarSubmitButton.textContent = calendarSubmitButton.dataset.originalText || 'Add Announcements';
                }
            });

            document.getElementById('start_date').addEventListener('focus', function () {
                if (this.value === '') {
                    this.value = '';
                }
            });

            // Optional: clear form on page load or after page navigation
            resetCalendarBlockForm();
        });

        const calendarBlocks = <?= $calendarBlocksJson ?: '[]' ?>;
        const calendarAnnouncements = <?= $calendarAnnouncementsJson ?: '[]' ?>;

        async function removeCalendarBlock(blockId) {
            if (!confirm('Are you sure you want to remove this calendar block?')) {
                return;
            }
            try {
                const formData = new FormData();
                formData.append('action', 'remove_calendar_block');
                formData.append('block_id', blockId);
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                });
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                window.location.reload();
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error removing calendar block', 'error');
            }
        }

        const legalHolidays = [
            { date: '2026-01-01', name: "New Year's Day" },
            { date: '2026-02-25', name: 'EDSA People Power Revolution Anniversary' },
            { date: '2026-04-09', name: 'Araw ng Kagitingan' },
            { date: '2026-05-01', name: 'Labor Day' },
            { date: '2026-06-12', name: 'Independence Day' },
            { date: '2026-08-31', name: 'National Heroes Day' },
            { date: '2026-11-30', name: 'Bonifacio Day' },
            { date: '2026-12-25', name: 'Christmas Day' },
            { date: '2026-12-30', name: 'Rizal Day' }
        ];

        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        let activeYear = new Date().getFullYear();
        let activeMonth = new Date().getMonth();

        // Notification System
        function showNotification(message, type = 'success') {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;

            container.appendChild(notification);

            setTimeout(() => {
                container.removeChild(notification);
            }, 5000);
        }

        // Modal Functions
        function showEventModal(eventId = null) {
            const modal = document.getElementById('eventModal');
            const form = document.getElementById('eventForm');
            const title = document.getElementById('eventModalTitle');
            const photoInput = document.getElementById('eventPhotos');

            photoInput.value = '';
            handleEventPhotosChange();
            clearCurrentPhotoRemovals();

            if (eventId) {
                title.textContent = 'Edit Event';
                const event = (window.sscEvents || []).find(evt => Number(evt.id) === Number(eventId));
                if (event) {
                    document.getElementById('eventId').value = event.id;
                    document.getElementById('eventTitle').value = event.title || '';
                    document.getElementById('eventDescription').value = event.description || '';
                    document.getElementById('eventDate').value = event.event_date || '';
                    document.getElementById('eventTime').value = event.event_time || '';
                    document.getElementById('eventLocation').value = event.location || '';
                    document.getElementById('eventOrganizer').value = event.organizer || '';
                    renderCurrentPhotos(event.photos || []);
                } else {
                    form.reset();
                    document.getElementById('eventId').value = eventId;
                    renderCurrentPhotos([]);
                }
            } else {
                title.textContent = 'Add New Event';
                form.reset();
                document.getElementById('eventId').value = '';
                renderCurrentPhotos([]);
            }

            modal.classList.add('show');
        }

        function showAnnouncementModal() {
            const modal = document.getElementById('announcementModal');
            const form = document.getElementById('announcementForm');
            form.reset();
            clearAnnouncementPhotoSelection();
            modal.classList.add('show');
        }

        function handleAnnouncementModalPhotosChange() {
            const input = document.getElementById('announcementModalImages');
            const previewSection = document.getElementById('announcementSelectedPhotosPreview');
            const previewContainer = document.getElementById('announcementSelectedPhotosContainer');
            const files = Array.from(input.files || []);

            if (files.length === 0) {
                previewSection.style.display = 'none';
                previewContainer.innerHTML = '';
                return;
            }

            previewSection.style.display = 'block';
            previewContainer.innerHTML = '';

            files.forEach((file, index) => {
                const fileReader = new FileReader();
                const card = document.createElement('div');
                card.className = 'image-preview-card';
                card.draggable = true;
                card.dataset.index = index;

                const img = document.createElement('img');
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'image-preview-remove';
                removeBtn.textContent = '×';
                removeBtn.title = 'Remove selected image';
                removeBtn.addEventListener('click', () => removeAnnouncementSelectedImage(index));

                card.appendChild(img);
                card.appendChild(removeBtn);
                previewContainer.appendChild(card);
                attachDragAndDropReordering(card, previewContainer, input, handleAnnouncementModalPhotosChange);

                fileReader.onload = (e) => {
                    img.src = e.target.result;
                };
                fileReader.readAsDataURL(file);
            });
        }

        function removeAnnouncementSelectedImage(fileIndex) {
            const input = document.getElementById('announcementModalImages');
            const files = Array.from(input.files || []);
            const dataTransfer = new DataTransfer();

            files.forEach((file, index) => {
                if (index !== fileIndex) {
                    dataTransfer.items.add(file);
                }
            });

            input.files = dataTransfer.files;
            handleAnnouncementModalPhotosChange();
        }

        function clearAnnouncementPhotoSelection() {
            const input = document.getElementById('announcementModalImages');
            input.value = '';
            const previewSection = document.getElementById('announcementSelectedPhotosPreview');
            const previewContainer = document.getElementById('announcementSelectedPhotosContainer');
            previewSection.style.display = 'none';
            previewContainer.innerHTML = '';
        }

        function handleEventPhotosChange() {
            const input = document.getElementById('eventPhotos');
            const previewSection = document.getElementById('selectedPhotosPreview');
            const previewContainer = document.getElementById('selectedPhotosContainer');
            const files = Array.from(input.files || []);

            if (files.length === 0) {
                previewSection.style.display = 'none';
                previewContainer.innerHTML = '';
                return;
            }

            previewSection.style.display = 'block';
            previewContainer.innerHTML = '';

            files.forEach((file, index) => {
                const fileReader = new FileReader();
                const card = document.createElement('div');
                card.className = 'image-preview-card';
                card.draggable = true;
                card.dataset.index = index;

                const img = document.createElement('img');
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'image-preview-remove';
                removeBtn.textContent = '×';
                removeBtn.title = 'Remove selected image';
                removeBtn.addEventListener('click', () => removeSelectedImage(index));

                card.appendChild(img);
                card.appendChild(removeBtn);
                previewContainer.appendChild(card);
                attachDragAndDropReordering(card, previewContainer, input, handleEventPhotosChange);

                fileReader.onload = (e) => {
                    img.src = e.target.result;
                };
                fileReader.readAsDataURL(file);
            });
        }

        function removeSelectedImage(fileIndex) {
            const input = document.getElementById('eventPhotos');
            const dataTransfer = new DataTransfer();
            const files = Array.from(input.files || []);

            files.forEach((file, index) => {
                if (index !== fileIndex) {
                    dataTransfer.items.add(file);
                }
            });

            input.files = dataTransfer.files;
            handleEventPhotosChange();
        }

        function attachDragAndDropReordering(card, previewContainer, input, onReorder) {
            card.addEventListener('dragstart', (e) => {
                e.dataTransfer.effectAllowed = 'move';
                card.classList.add('dragging');
                e.dataTransfer.setData('text/plain', card.dataset.index);
            });

            card.addEventListener('dragend', () => {
                card.classList.remove('dragging');
            });

            card.addEventListener('dragover', (e) => {
                e.preventDefault();
                card.classList.add('drag-over');
            });

            card.addEventListener('dragleave', () => {
                card.classList.remove('drag-over');
            });

            card.addEventListener('drop', (e) => {
                e.preventDefault();
                card.classList.remove('drag-over');
                const fromIndex = parseInt(e.dataTransfer.getData('text/plain'), 10);
                const toIndex = parseInt(card.dataset.index, 10);
                if (!Number.isNaN(fromIndex) && fromIndex !== toIndex) {
                    reorderFilesInInput(input, fromIndex, toIndex);
                    if (typeof onReorder === 'function') {
                        onReorder();
                    }
                }
            });
        }

        function reorderFilesInInput(input, fromIndex, toIndex) {
            const files = Array.from(input.files || []);
            if (fromIndex < 0 || fromIndex >= files.length || toIndex < 0 || toIndex >= files.length) {
                return;
            }

            const [movedFile] = files.splice(fromIndex, 1);
            files.splice(toIndex, 0, movedFile);
            const dataTransfer = new DataTransfer();
            files.forEach(file => dataTransfer.items.add(file));
            input.files = dataTransfer.files;
        }

        function resolveEventImageUrl(photoUrl) {
            if (!photoUrl) {
                return '';
            }
            if (photoUrl.startsWith('http://') || photoUrl.startsWith('https://') || photoUrl.startsWith('/')) {
                return photoUrl;
            }
            return '../' + photoUrl.replace(/^\/*/, '');
        }

        function renderCurrentPhotos(photos) {
            const previewSection = document.getElementById('currentPhotosPreview');
            const container = document.getElementById('currentPhotosContainer');
            const removalInputs = document.getElementById('currentPhotoRemovalInputs');
            container.innerHTML = '';
            removalInputs.innerHTML = '';

            if (!photos || photos.length === 0) {
                previewSection.style.display = 'none';
                return;
            }

            previewSection.style.display = 'block';

            photos.forEach((photo, index) => {
                const photoUrl = typeof photo === 'string' ? photo : (photo.url || photo.photo_url || '');
                const card = document.createElement('div');
                card.className = 'image-preview-card';

                const img = document.createElement('img');
                img.src = resolveEventImageUrl(photoUrl);
                img.alt = 'Current event image';

                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'image-preview-remove';
                removeBtn.textContent = '×';
                removeBtn.title = 'Remove current image';
                removeBtn.addEventListener('click', () => removeCurrentPhoto(photoUrl, index));

                card.appendChild(img);
                card.appendChild(removeBtn);
                container.appendChild(card);
            });
        }

        function removeCurrentPhoto(photoUrl, index) {
            const removalInputs = document.getElementById('currentPhotoRemovalInputs');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'removed_photos[]';
            input.value = photoUrl;
            removalInputs.appendChild(input);

            const currentPhotosPreview = document.getElementById('currentPhotosPreview');
            const cards = currentPhotosPreview.querySelectorAll('.image-preview-card');
            if (cards[index]) {
                cards[index].remove();
            }
            if (!currentPhotosPreview.querySelector('.image-preview-card')) {
                currentPhotosPreview.style.display = 'none';
            }
        }

        function clearCurrentPhotoRemovals() {
            const removalInputs = document.getElementById('currentPhotoRemovalInputs');
            removalInputs.innerHTML = '';
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
        }

        // Events Functions
        async function loadEvents() {
            try {
                const response = await fetch('ssc_api.php?action=get_events');
                const data = await response.json();
                window.sscEvents = normalizeEvents(data.events || []);
                displayEventsByStatus(window.sscEvents);
            } catch (error) {
                console.error('Error loading events:', error);
                showNotification('Error loading events', 'error');
            }
        }

        function getEventStatus(event) {
            const now = new Date();
            let eventDateTime;
            if (event.event_date) {
                const [year, month, day] = event.event_date.split('-').map(Number);
                if (event.event_time) {
                    const [hour, minute] = event.event_time.split(':').map(Number);
                    eventDateTime = new Date(year, month - 1, day, hour, minute, 0, 0);
                } else {
                    eventDateTime = new Date(year, month - 1, day, 23, 59, 59, 999);
                }
            } else {
                return event.status || 'upcoming';
            }

            if (event.status === 'completed') {
                return 'completed';
            }
            if (eventDateTime <= now) {
                return 'completed';
            }
            return 'upcoming';
        }

        function normalizeEvents(events) {
            return events.map(event => ({ ...event, status: getEventStatus(event) }));
        }

        function displayEventsByStatus(events) {
            const normalized = normalizeEvents(events);
            
            // Separate announcements from events
            const announcements = normalized.filter(e => e.event_type === 'others');
            const regularEvents = normalized.filter(e => e.event_type !== 'others');

            // Update badges
            document.getElementById('badge-all').textContent = regularEvents.length;
            document.getElementById('badge-announcements').textContent = announcements.length;
            document.getElementById('badge-upcoming').textContent = regularEvents.filter(e => e.status === 'upcoming').length;
            document.getElementById('badge-completed').textContent = regularEvents.filter(e => e.status === 'completed').length;

            // Display events in each tab
            displayEvents(regularEvents, 'all-events', 'events-all');
            displayEvents(announcements, 'announcements-events', 'events-announcements');
            displayEvents(regularEvents.filter(e => e.status === 'upcoming'), 'upcoming-events', 'events-upcoming');
            displayEvents(regularEvents.filter(e => e.status === 'completed'), 'completed-events', 'events-completed');
        }

        function displayEvents(events, tabId, containerId) {
            const container = document.getElementById(containerId);

            if (events.length === 0) {
                let emptyMessage = 'No events found';
                if (tabId === 'announcements-events') emptyMessage = 'No announcements';
                else if (tabId === 'upcoming-events') emptyMessage = 'No upcoming events';
                else if (tabId === 'completed-events') emptyMessage = 'No completed events';

                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-calendar-days"></i>
                        <h3>${emptyMessage}</h3>
                        <p>Events will appear here when available.</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = events.map(event => `
                <div class="event-item">
                    <div class="event-item-header">
                        <div>
                            <h3 class="event-item-title">${event.title}</h3>
                            <p class="event-item-details">
                                <span class="event-type-pill">${escapeHtml(formatEventType(event.event_type || 'unknown'))}</span>
                                <span><i class="fa-solid fa-calendar"></i> ${event.event_date ? new Date(event.event_date).toLocaleDateString() : 'No date set'}</span>
                                ${event.event_time ? `<span><i class="fa-solid fa-clock"></i> ${event.event_time}</span>` : ''}
                                ${event.location ? `<span><i class="fa-solid fa-map-marker-alt"></i> ${event.location}</span>` : ''}
                            </p>
                        </div>
                        <div class="event-item-actions">
                            <button class="btn-sm btn-edit" onclick="showEventModal(${event.id})">
                                <i class="fa-solid fa-edit"></i> Edit
                            </button>
                            <button class="btn-sm btn-delete" onclick="deleteEvent(${event.id})">
                                <i class="fa-solid fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                    ${event.description ? `<p class="event-item-details">${event.description}</p>` : ''}
                    <span class="event-status-badge status-${event.status}">${event.status}</span>
                </div>
            `).join('');
        }

        // Form Submissions
        async function handleEventSubmit(event) {
            event.preventDefault();

            const form = document.getElementById('eventForm');
            const formData = new FormData(form);

            try {
                const response = await fetch('ssc_api.php?action=save_event', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (result.success) {
                    showNotification('Event saved successfully!', 'success');
                    closeModal('eventModal');
                    loadEvents();
                } else {
                    showNotification(result.message || 'Error saving event', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error saving event', 'error');
            }
        }

        async function handleAnnouncementSubmit(event) {
            event.preventDefault();

            const form = document.getElementById('announcementForm');
            const formData = new FormData(form);
            
            // Get the title value, default to "Announcement" if empty
            const title = document.getElementById('announcementTitle').value.trim();
            const description = document.getElementById('announcementDescription').value.trim();
            
            // Clear and set the proper values
            formData.set('title', title || 'Announcement');
            formData.set('description', description);
            formData.set('event_type', 'others'); // Mark as announcement
            
            // Set event_date to today if not already set
            if (!formData.get('event_date')) {
                const today = new Date().toISOString().split('T')[0];
                formData.set('event_date', today);
            }
            
            // Ensure these fields are set for announcements
            if (!formData.has('event_time') || formData.get('event_time') === '') {
                formData.delete('event_time');
            }
            if (!formData.has('location') || formData.get('location') === '') {
                formData.delete('location');
            }
            if (!formData.has('organizer') || formData.get('organizer') === '') {
                formData.delete('organizer');
            }

            try {
                const response = await fetch('ssc_api.php?action=save_event', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    showNotification('Announcement saved successfully!', 'success');
                    closeModal('announcementModal');
                    loadEvents();
                } else {
                    showNotification(result.message || 'Error saving announcement', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error saving announcement', 'error');
            }
        }

        async function deleteEvent(eventId) {
            if (!confirm('Are you sure you want to delete this event?')) {
                return;
            }

            try {
                const response = await fetch('ssc_api.php?action=delete_event', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: eventId })
                });

                const result = await response.json();
                if (result.success) {
                    showNotification('Event deleted successfully!', 'success');
                    loadEvents();
                } else {
                    showNotification(result.message || 'Error deleting event', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error deleting event', 'error');
            }
        }

        function editCalendarBlock(blockId) {
            try {
                const announcement = calendarAnnouncements.find(item => Number(item.id) === Number(blockId));
                if (!announcement) {
                    showNotification('Unable to find the selected announcement.', 'error');
                    return;
                }
                
                // Populate the calendar block form with the announcement data
                document.getElementById('block_id').value = blockId;
                document.getElementById('start_date').value = announcement.event_date;
                document.getElementById('end_date').value = announcement.end_date || announcement.event_date;
                document.getElementById('event_type').value = announcement.event_type;
                document.getElementById('start_time').value = announcement.start_time ? announcement.start_time.substring(0, 5) : '';
                document.getElementById('end_time').value = announcement.end_time ? announcement.end_time.substring(0, 5) : '';
                
                // Handle description - extract from the stored format
                if (announcement.description) {
                    document.getElementById('description').value = announcement.description;
                    document.getElementById('description-container').style.display = 'block';
                } else {
                    document.getElementById('description').value = '';
                    document.getElementById('description-container').style.display = 'block';
                }
                
                // Scroll to the form and switch to "Important Announcements" sub-tab
                document.querySelector('[data-sub-tab="add-announcements"]').click();
                document.querySelector('#add-announcements').scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                showNotification('Edit the fields below and click "Add Announcements" to save changes', 'info');
            } catch (error) {
                console.error('Error editing calendar block:', error);
                showNotification('Error loading announcement for editing', 'error');
            }
        }

        async function deleteCalendarBlock(blockId) {
            if (!confirm('Are you sure you want to delete this announcement? This action cannot be undone.')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'remove_calendar_block');
                formData.append('block_id', blockId);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                // Reload the page to refresh the announcements list after successful deletion
                window.location.reload();
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error deleting announcement', 'error');
            }
        }

        // Page Initialization
        document.addEventListener('DOMContentLoaded', function () {
            initializeMainTabs();
            initializeEventTabs();
            initializeSubTabs();
            loadEvents();
            renderCalendar(activeYear, activeMonth);

            document.getElementById('prev-month').addEventListener('click', () => changeMonth(-1));
            document.getElementById('next-month').addEventListener('click', () => changeMonth(1));
            document.getElementById('eventForm').addEventListener('submit', handleEventSubmit);
            document.getElementById('announcementForm').addEventListener('submit', handleAnnouncementSubmit);
            document.getElementById('eventPhotos').addEventListener('change', handleEventPhotosChange);
            document.getElementById('announcementModalImages').addEventListener('change', handleAnnouncementModalPhotosChange);
            
            // Graduation manual send button removed; emails are sent automatically when graduation event is processed
        });

        function initializeMainTabs() {
            const tabLinks = Array.from(document.querySelectorAll('.main-tab-link'));

            function changeMainTab(link) {
                const target = link.dataset.mainTab;
                tabLinks.forEach(item => item.classList.toggle('active', item === link));
                document.querySelectorAll('.main-tab-content').forEach(content => content.classList.toggle('active', content.id === target));
            }

            tabLinks.forEach(link => {
                link.addEventListener('click', function () {
                    changeMainTab(this);
                });
            });

            attachSwipeNavigation(document.querySelector('.main-tabs-container'), tabLinks, changeMainTab);
        }

        function initializeEventTabs() {
            const eventLinks = Array.from(document.querySelectorAll('.tab-link'));

            function changeEventTab(link) {
                const target = link.dataset.tab;
                eventLinks.forEach(item => item.classList.toggle('active', item === link));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.toggle('active', content.id === target));
            }

            eventLinks.forEach(link => {
                link.addEventListener('click', function () {
                    changeEventTab(this);
                });
            });

            attachSwipeNavigation(document.querySelector('.tabs-container'), eventLinks, changeEventTab);
        }

        function initializeSubTabs() {
            const subTabLinks = Array.from(document.querySelectorAll('.sub-tab-link'));

            function changeSubTab(link) {
                const target = link.dataset.subTab;
                subTabLinks.forEach(item => item.classList.toggle('active', item === link));
                document.querySelectorAll('.sub-tab-content').forEach(content => content.classList.toggle('active', content.id === target));
            }

            subTabLinks.forEach(link => {
                link.addEventListener('click', function () {
                    changeSubTab(this);
                });
            });

            attachSwipeNavigation(document.querySelector('.sub-tabs-container'), subTabLinks, changeSubTab);
        }

        function attachSwipeNavigation(container, buttons, onSelect) {
            if (!container || buttons.length < 2) return;

            let startX = 0;
            let currentX = 0;
            let isTouching = false;

            container.addEventListener('touchstart', function (event) {
                if (event.touches.length !== 1) return;
                startX = event.touches[0].clientX;
                currentX = startX;
                isTouching = true;
            }, { passive: true });

            container.addEventListener('touchmove', function (event) {
                if (!isTouching || event.touches.length !== 1) return;
                currentX = event.touches[0].clientX;
            }, { passive: true });

            container.addEventListener('touchend', function () {
                if (!isTouching) return;
                const delta = startX - currentX;
                const threshold = 50;
                if (Math.abs(delta) >= threshold) {
                    const activeIndex = buttons.findIndex(btn => btn.classList.contains('active'));
                    if (activeIndex === -1) {
                        isTouching = false;
                        return;
                    }
                    const nextIndex = delta > 0 ? Math.min(buttons.length - 1, activeIndex + 1) : Math.max(0, activeIndex - 1);
                    if (nextIndex !== activeIndex) {
                        onSelect(buttons[nextIndex]);
                    }
                }
                isTouching = false;
            }, { passive: true });
        }

        function changeMonth(offset) {
            activeMonth += offset;
            if (activeMonth < 0) {
                activeMonth = 11;
                activeYear -= 1;
            } else if (activeMonth > 11) {
                activeMonth = 0;
                activeYear += 1;
            }
            renderCalendar(activeYear, activeMonth);
        }

        function renderCalendar(year, month) {
            const date = new Date(year, month, 1);
            const monthLabel = document.getElementById('calendar-month-label');
            const yearLabel = document.getElementById('calendar-year-label');
            const grid = document.getElementById('calendar-grid');
            const infoBox = document.getElementById('calendar-day-info');
            const todayDate = new Date();
            const isMobileList = window.matchMedia('(max-width: 720px)').matches;
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            monthLabel.textContent = monthNames[month];
            yearLabel.textContent = year;
            grid.innerHTML = '';
            infoBox.innerHTML = `<strong>Click a date</strong> Select a day to see holiday or school block details here.`;
            grid.classList.toggle('calendar-list', isMobileList);

            if (isMobileList) {
                renderCalendarList(grid, year, month, todayDate, daysInMonth);
                return;
            }

            const startDay = new Date(year, month, 1).getDay();
            const daysInPrevMonth = new Date(year, month, 0).getDate();

            const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            dayNames.forEach(name => {
                const headerCell = document.createElement('div');
                headerCell.className = 'calendar-cell disabled';
                headerCell.style.fontWeight = '700';
                headerCell.innerHTML = `<div class="day-number">${name}</div>`;
                grid.appendChild(headerCell);
            });

            const totalCells = 42;
            for (let i = 0; i < totalCells; i++) {
                const cell = document.createElement('div');
                cell.className = 'calendar-cell';
                const cellIndex = i - startDay + 1;
                if (i < startDay) {
                    const dayNumber = daysInPrevMonth - (startDay - 1 - i);
                    cell.classList.add('disabled');
                    cell.innerHTML = `<div class="day-number">${dayNumber}</div>`;
                } else if (cellIndex > daysInMonth) {
                    const dayNumber = cellIndex - daysInMonth;
                    cell.classList.add('disabled');
                    cell.innerHTML = `<div class="day-number">${dayNumber}</div>`;
                } else {
                    const dayDate = new Date(year, month, cellIndex);
                    const formatted = formatCalendarDate(dayDate);
                    const isSunday = dayDate.getDay() === 0;
                    const holiday = legalHolidays.find(h => h.date === formatted);
                    const blocksForDate = calendarBlocks.filter(b => b.event_date === formatted);
                    const notes = [];

                    if (holiday) {
                        cell.classList.add('holiday');
                        notes.push(holiday.name);
                    }
                    if (isSunday) {
                        cell.classList.add('sunday');
                        notes.push('Sunday (No class)');
                    }
                    if (blocksForDate.length) {
                        cell.classList.add('no-class');
                        if (blocksForDate.length === 1) {
                            const block = blocksForDate[0];
                            const blockType = formatEventType(block.event_type);
                            const timeLabel = block.start_time ? ` ${block.start_time}` : '';
                            const endLabel = block.end_time ? ` - ${block.end_time}` : '';
                            notes.push(`${blockType}${timeLabel}${endLabel}`);
                        } else {
                            notes.push(`${blocksForDate.length} events`);
                        }
                    }
                    if (dayDate.toDateString() === todayDate.toDateString()) {
                        cell.classList.add('today');
                    }

                    cell.innerHTML = `<div><span class="day-number">${cellIndex}</span></div>`;
                    if (notes.length > 0) {
                        cell.innerHTML += `<div class="day-note">${notes[0]}</div>`;
                    }
                    cell.addEventListener('click', () => {
                        if (blocksForDate.length) {
                            showCalendarDetailsModal(`${monthNames[month]} ${cellIndex}, ${year}`, holiday, isSunday, blocksForDate);
                            return;
                        }

                        const detailLines = [`<strong>${monthNames[month]} ${cellIndex}, ${year}</strong>`];
                        if (holiday) detailLines.push(`<strong>Holiday:</strong> ${escapeHtml(holiday.name)}`);
                        if (isSunday) detailLines.push('<strong>Sunday:</strong> No classes');
                        if (!holiday && !isSunday) detailLines.push('No special holiday or no-class block for this day.');
                        infoBox.innerHTML = detailLines.join('<br>');
                    });
                }
                grid.appendChild(cell);
            }
        }

        function renderCalendarList(grid, year, month, todayDate, daysInMonth) {
            for (let day = 1; day <= daysInMonth; day++) {
                const dayDate = new Date(year, month, day);
                const formatted = formatCalendarDate(dayDate);
                const isSunday = dayDate.getDay() === 0;
                const holiday = legalHolidays.find(h => h.date === formatted);
                const blocksForDate = calendarBlocks.filter(b => b.event_date === formatted);
                const cell = document.createElement('div');
                cell.className = 'calendar-cell';
                if (holiday) cell.classList.add('holiday');
                if (isSunday) cell.classList.add('sunday');
                if (blocksForDate.length) cell.classList.add('no-class');
                if (dayDate.toDateString() === todayDate.toDateString()) cell.classList.add('today');

                const weekday = dayDate.toLocaleDateString('en-US', { weekday: 'short' });
                const noteLines = [];
                if (holiday) noteLines.push(holiday.name);
                if (isSunday) noteLines.push('Sunday (No class)');
                if (blocksForDate.length) {
                    if (blocksForDate.length === 1) {
                        const block = blocksForDate[0];
                        const blockType = formatEventType(block.event_type);
                        const timeLabel = block.start_time ? ` ${block.start_time}` : '';
                        const endLabel = block.end_time ? ` - ${block.end_time}` : '';
                        noteLines.push(`${blockType}${timeLabel}${endLabel}`);
                    } else {
                        noteLines.push(`${blocksForDate.length} events`);
                    }
                }
                if (!noteLines.length) {
                    noteLines.push('No events');
                }

                cell.innerHTML = `
                    <div class="calendar-list-left">
                        <span class="day-number">${day}</span>
                        <span class="calendar-list-weekday">${weekday}</span>
                    </div>
                    <div class="calendar-list-right">
                        ${noteLines.map(line => `<div>${escapeHtml(line)}</div>`).join('')}
                    </div>
                `;

                cell.addEventListener('click', () => {
                    showCalendarDetailsModal(`${monthNames[month]} ${day}, ${year}`, holiday, isSunday, blocksForDate);
                });

                grid.appendChild(cell);
            }
        }

        function showCalendarDetailsModal(dateLabel, holiday, isSunday, blocks) {
            const modal = document.getElementById('calendarDetailsModal');
            const title = document.getElementById('calendarDetailsTitle');
            const body = document.getElementById('calendarDetailsBody');

            title.textContent = `Details for ${dateLabel}`;
            const lines = [];

            if (holiday) {
                lines.push(`<div><strong>Holiday:</strong> ${escapeHtml(holiday.name)}</div>`);
            }
            if (isSunday) {
                lines.push('<div><strong>Sunday:</strong> No classes</div>');
            }
            if (blocks.length) {
                blocks.forEach((block) => {
                    const blockType = formatEventType(block.event_type);
                    const startTime = block.start_time || '--:--';
                    const endTime = block.end_time || '--:--';
                    lines.push(`<div class="calendar-detail-row"><strong>Event type:</strong> ${escapeHtml(blockType)}</div>`);
                    lines.push(`<div class="calendar-detail-row"><strong>Event date:</strong> ${escapeHtml(dateLabel)}</div>`);
                    lines.push(`<div class="calendar-detail-row"><strong>Start time:</strong> ${escapeHtml(startTime)}</div>`);
                    lines.push(`<div class="calendar-detail-row"><strong>End time:</strong> ${escapeHtml(endTime)}</div>`);
                    if (block.description) {
                        lines.push(`<div class="calendar-detail-row"><strong>Description:</strong> ${escapeHtml(block.description)}</div>`);
                    }
                    lines.push(`<div class="calendar-detail-actions">` +
                        `<button type="button" class="btn-sm btn-edit" onclick="closeModal('calendarDetailsModal'); editCalendarBlock(${block.id})"><i class="fa-solid fa-edit"></i> Edit</button>` +
                        `<button type="button" class="btn-sm btn-delete" onclick="removeCalendarBlock(${block.id})"><i class="fa-solid fa-trash"></i> Remove</button>` +
                        `</div>`);
                    lines.push('<hr>');
                });
                lines.pop();
            }
            if (!holiday && !isSunday && blocks.length === 0) {
                lines.push('<div>No special holiday or no-class block for this day.</div>');
            }

            body.innerHTML = lines.join('<br>');
            modal.classList.add('show');
        }

        function formatCalendarDate(date) {
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        }

        function formatEventType(text) {
            const safeText = String(text || '').replace(/_/g, ' ').trim();
            if (!safeText) {
                return 'Not specified';
            }
            return safeText
                .split(' ')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
        }

        function escapeHtml(text) {
            return String(text)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Announcement Images Functionality
        const announcementImagesInput = document.getElementById('announcementImages');
        const announcementImagePreview = document.getElementById('announcementImagePreview');
        const announcementImagePreviewContainer = document.getElementById('announcementImagePreviewContainer');
        let draggedIndex = null;

        if (announcementImagesInput) {
            announcementImagesInput.addEventListener('change', function() {
                const files = Array.from(this.files || []);
                
                if (files.length === 0) {
                    announcementImagePreview.style.display = 'none';
                    announcementImagePreviewContainer.innerHTML = '';
                    return;
                }

                announcementImagePreview.style.display = 'block';
                announcementImagePreviewContainer.innerHTML = '';

                files.forEach((file, index) => {
                    const fileReader = new FileReader();
                    const card = document.createElement('div');
                    card.className = 'announcement-image-card';
                    card.draggable = true;
                    card.dataset.index = index;
                    card.style.cssText = 'position: relative; width: 100%; border-radius: 8px; overflow: hidden; background: #f0f0f0; aspect-ratio: 1 / 1; cursor: grab; transition: all 0.2s ease; border: 2px solid transparent;';

                    const img = document.createElement('img');
                    img.style.cssText = 'width: 100%; height: 100%; object-fit: cover; display: block; pointer-events: none;';
                    
                    const dragHandle = document.createElement('div');
                    dragHandle.style.cssText = 'position: absolute; top: 6px; left: 6px; width: 24px; height: 24px; background: rgba(52, 152, 219, 0.9); border-radius: 4px; display: flex; align-items: center; justify-content: center; color: white; font-size: 14px; opacity: 0; transition: opacity 0.2s ease;';
                    dragHandle.innerHTML = '<i class="fa-solid fa-arrows" style="pointer-events: none;"></i>';

                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.style.cssText = 'position: absolute; top: 6px; right: 6px; width: 28px; height: 28px; border-radius: 50%; border: none; background: rgba(0,0,0,0.6); color: white; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; transition: all 0.2s ease;';
                    removeBtn.textContent = '×';
                    removeBtn.title = 'Remove image';
                    removeBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        removeAnnouncementImage(index);
                    });

                    // Drag events
                    card.addEventListener('dragstart', function(e) {
                        draggedIndex = index;
                        card.style.opacity = '0.5';
                        card.style.borderColor = '#3498db';
                    });

                    card.addEventListener('dragend', function(e) {
                        card.style.opacity = '1';
                        card.style.borderColor = 'transparent';
                        draggedIndex = null;
                        document.querySelectorAll('.announcement-image-card').forEach(c => {
                            c.style.borderColor = 'transparent';
                            c.style.backgroundColor = '#f0f0f0';
                        });
                    });

                    card.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        if (draggedIndex !== null && draggedIndex !== index) {
                            card.style.borderColor = '#3498db';
                            card.style.backgroundColor = 'rgba(52, 152, 219, 0.1)';
                        }
                    });

                    card.addEventListener('dragleave', function(e) {
                        if (draggedIndex !== null && draggedIndex !== index) {
                            card.style.borderColor = 'transparent';
                            card.style.backgroundColor = '#f0f0f0';
                        }
                    });

                    card.addEventListener('drop', function(e) {
                        e.preventDefault();
                        if (draggedIndex !== null && draggedIndex !== index) {
                            reorderAnnouncementImages(draggedIndex, index);
                        }
                    });

                    card.addEventListener('mouseenter', function() {
                        dragHandle.style.opacity = '1';
                    });

                    card.addEventListener('mouseleave', function() {
                        dragHandle.style.opacity = '0';
                    });

                    card.addEventListener('click', function(e) {
                        if (e.target === removeBtn || removeBtn.contains(e.target) || dragHandle.contains(e.target)) {
                            return;
                        }
                        if (img.src) {
                            openAnnouncementImageLightbox(img.src);
                        }
                    });

                    card.appendChild(img);
                    card.appendChild(dragHandle);
                    card.appendChild(removeBtn);
                    announcementImagePreviewContainer.appendChild(card);

                    fileReader.onload = (e) => {
                        img.src = e.target.result;
                    };
                    fileReader.readAsDataURL(file);
                });
            });
        }

        function reorderAnnouncementImages(fromIndex, toIndex) {
            const input = document.getElementById('announcementImages');
            const files = Array.from(input.files || []);
            
            if (fromIndex < 0 || fromIndex >= files.length || toIndex < 0 || toIndex >= files.length) {
                return;
            }

            // Reorder the files array
            const [movedFile] = files.splice(fromIndex, 1);
            files.splice(toIndex, 0, movedFile);

            // Update the file input with reordered files
            const dataTransfer = new DataTransfer();
            files.forEach(file => dataTransfer.items.add(file));
            input.files = dataTransfer.files;

            // Trigger change event to update preview
            const event = new Event('change', { bubbles: true });
            input.dispatchEvent(event);
        }

        function removeAnnouncementImage(fileIndex) {
            const input = document.getElementById('announcementImages');
            const dataTransfer = new DataTransfer();
            const files = Array.from(input.files || []);

            files.forEach((file, index) => {
                if (index !== fileIndex) {
                    dataTransfer.items.add(file);
                }
            });

            input.files = dataTransfer.files;
            
            // Trigger change event to update preview
            const event = new Event('change', { bubbles: true });
            input.dispatchEvent(event);
        }

        function openAnnouncementImageLightbox(src) {
            const overlay = document.getElementById('announcementImageLightboxOverlay');
            const lightboxImage = document.getElementById('announcementImageLightboxImage');
            if (!overlay || !lightboxImage) return;

            lightboxImage.src = src;
            overlay.classList.add('open');
        }

        function closeAnnouncementImageLightbox() {
            const overlay = document.getElementById('announcementImageLightboxOverlay');
            const lightboxImage = document.getElementById('announcementImageLightboxImage');
            if (!overlay || !lightboxImage) return;

            overlay.classList.remove('open');
            lightboxImage.src = '';
        }

        // Send Graduation Congratulations Emails via EmailJS
        async function sendGraduationCongratulationEmails() {
            const statusDiv = document.getElementById('graduationEmailStatus');
            
            if (typeof emailjs === 'undefined') {
                showNotification('Email service is not loaded. Please refresh the page.', 'error');
                return;
            }

            try {
                if (statusDiv) {
                    statusDiv.innerHTML = '<p style="color: #0284c7;"><i class="fa-solid fa-spinner"></i> Fetching graduate list...</p>';
                    statusDiv.style.display = 'block';
                }

                // Fetch recently graduated students
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=get_recent_graduates'
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch graduates');
                }

                const graduates = await response.json();

                if (!Array.isArray(graduates) || graduates.length === 0) {
                    if (statusDiv) {
                        statusDiv.innerHTML = '<div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; color: #991b1b;"><i class="fa-solid fa-circle-info"></i> No recent graduates found to send congratulations emails.</div>';
                    }
                    return;
                }

                if (statusDiv) {
                    statusDiv.innerHTML = `<p style="color: #0284c7;"><i class="fa-solid fa-spinner"></i> Sending ${graduates.length} congratulation emails...</p>`;
                }

                // Send emails sequentially with delay
                let successCount = 0;
                let failCount = 0;

                for (let i = 0; i < graduates.length; i++) {
                    const grad = graduates[i];
                    
                    await new Promise(resolve => {
                        setTimeout(() => {
                            const templateParams = {
                                user_name: grad.full_name || 'Graduate',
                                email: grad.personal_email || grad.email,
                                course: grad.course || 'N/A',
                                graduation_year: grad.graduation_year || new Date().getFullYear(),
                                school_name: 'City of Alaminos College',
                                login_link: 'https://grasp-manlike-atrophy.ngrok-free.dev/THESIS/SUPPORTSERVICESYSTEM/auth/student_login.php',
                                tracer_link: 'https://grasp-manlike-atrophy.ngrok-free.dev/THESIS/SUPPORTSERVICESYSTEM/alumni_tracer_form.php'
                            };

                            emailjs.send(window.emailJsGraduationConfig.serviceId, window.emailJsGraduationConfig.templateId, templateParams)
                                .then(response => {
                                    successCount++;
                                    console.log('Graduation email sent to ' + grad.full_name, response);
                                    if (statusDiv) {
                                        statusDiv.innerHTML = `<p style="color: #0284c7;"><i class="fa-solid fa-spinner"></i> Sent ${successCount}/${graduates.length} emails...</p>`;
                                    }
                                    resolve();
                                })
                                .catch(error => {
                                    failCount++;
                                    console.error('Failed to send graduation email to ' + grad.full_name, error);
                                    resolve();
                                });
                        }, i * 1000); // 1 second delay between emails
                    });
                }

                // Show result
                const resultHtml = failCount > 0 
                    ? `<div style="background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 16px; color: #78350f;"><i class="fa-solid fa-check-circle"></i> <strong>Partial Success:</strong> Sent ${successCount} emails successfully. ${failCount} failed. Please check the browser console for details.</div>`
                    : `<div style="background: #dcfce7; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; color: #166534;"><i class="fa-solid fa-check-circle"></i> <strong>Success!</strong> Sent ${successCount} graduation congratulations emails to all newly graduated students.</div>`;
                
                if (statusDiv) {
                    statusDiv.innerHTML = resultHtml;
                }
            } catch (error) {
                console.error('Error sending graduation congratulations:', error);
                if (statusDiv) {
                    statusDiv.innerHTML = `<div style="background: #fee2e2; border: 1px solid #fca5a5; border-radius: 8px; padding: 16px; color: #991b1b;"><i class="fa-solid fa-exclamation-circle"></i> <strong>Error:</strong> ${error.message}</div>`;
                }
            }
        }
    </script>
    <?php ob_end_flush(); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const swipeRefreshSpinner = document.getElementById('swipe-refresh-spinner');
            if (!swipeRefreshSpinner || window.innerWidth > 768) {
                return;
            }

            let touchStartY = 0;
            let isPulling = false;

            document.addEventListener('touchstart', function (event) {
                if (window.scrollY > 0 || event.touches.length !== 1) {
                    return;
                }
                touchStartY = event.touches[0].clientY;
                isPulling = true;
            }, { passive: true });

            document.addEventListener('touchmove', function (event) {
                if (!isPulling) {
                    return;
                }

                const deltaY = event.touches[0].clientY - touchStartY;
                if (deltaY > 90) {
                    swipeRefreshSpinner.style.display = 'flex';
                    setTimeout(function () {
                        window.location.reload();
                    }, 450);
                    isPulling = false;
                }
            }, { passive: true });

            document.addEventListener('touchend', function () {
                isPulling = false;
            }, { passive: true });
        });
    </script>
</body>
</html>
