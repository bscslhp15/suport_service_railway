<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/clinic_functions.php';
ensure_library_schema();
require_login();
$user = current_user();

// Allow both students and teachers to view
if (!in_array($user['role'], ['student', 'teacher'])) {
    header('Location: ../auth/student_login.php');
    exit;
}

$pdo = get_db();
$currentPage = 'school_announcements.php';
$isStudent = $user['role'] === 'student';
$isTeacher = $user['role'] === 'teacher';

// Get clinic announcements
$clinicAnnouncements = [];
if (function_exists('get_active_clinic_announcements')) {
    // Show all active clinic announcements in the central announcements feed
    $clinicAnnouncements = get_active_clinic_announcements('all');
}

// Get school calendar events
$calendarEvents = [];
$stmt = $pdo->prepare('SELECT id, event_date, event_type, description, start_time, end_time FROM library_calendar ORDER BY event_date DESC LIMIT 100');
$stmt->execute();
$calendarEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($calendarEvents)) {
    $eventIds = array_column($calendarEvents, 'id');
    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    $stmt = $pdo->prepare("SELECT calendar_id, image_path FROM library_calendar_images WHERE calendar_id IN ($placeholders) ORDER BY calendar_id, id ASC");
    $stmt->execute($eventIds);
    $calendarImages = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $calendarImages[$row['calendar_id']][] = $row['image_path'];
    }
    foreach ($calendarEvents as &$evt) {
        $evt['images'] = $calendarImages[$evt['id']] ?? [];
    }
    unset($evt);
}

// Get SSC events
$sscEvents = [];
$stmt = $pdo->prepare('SELECT id, title, event_date, location, description, photo_url FROM ssc_events ORDER BY event_date DESC, created_at DESC LIMIT 100');
$stmt->execute();
$sscEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($sscEvents)) {
    $eventIds = array_column($sscEvents, 'id');
    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    $stmt = $pdo->prepare("SELECT event_id, photo_url FROM ssc_event_photos WHERE event_id IN ($placeholders) ORDER BY event_id, id ASC");
    $stmt->execute($eventIds);
    $sscPhotos = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sscPhotos[$row['event_id']][] = $row['photo_url'];
    }
    foreach ($sscEvents as &$event) {
        $event['images'] = [];
        if (!empty($event['photo_url'])) {
            $event['images'][] = $event['photo_url'];
        }
        if (!empty($sscPhotos[$event['id']])) {
            $event['images'] = array_merge($event['images'], $sscPhotos[$event['id']]);
        }
    }
    unset($event);
}

// Get scholarship announcements
$scholarshipAnnouncements = [];
$stmt = $pdo->prepare('SELECT id, title, content as description, deadline, created_at FROM scholarship_announcements ORDER BY created_at DESC LIMIT 100');
$stmt->execute();
$scholarshipAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($scholarshipAnnouncements)) {
    $announcementIds = array_column($scholarshipAnnouncements, 'id');
    $placeholders = implode(',', array_fill(0, count($announcementIds), '?'));
    $stmt = $pdo->prepare("SELECT announcement_id, image_path FROM scholarship_announcement_images WHERE announcement_id IN ($placeholders) ORDER BY announcement_id, id ASC");
    $stmt->execute($announcementIds);
    $scholarshipImages = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $scholarshipImages[$row['announcement_id']][] = $row['image_path'];
    }
    foreach ($scholarshipAnnouncements as &$ann) {
        $ann['images'] = $scholarshipImages[$ann['id']] ?? [];
    }
    unset($ann);
}

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
$portalType = $isStudent ? 'Student Portal' : 'Teacher Portal';
$homeLink = $isStudent ? 'student_home.php' : 'teacher_home.php';
$loginRedirect = $isStudent ? '../auth/student_login.php' : '../auth/teacher_login.php';

function cleanAnnouncementText($text) {
    return trim(strip_tags($text));
}

function renderAnnouncementText($text, $limit = null) {
    $clean = cleanAnnouncementText($text);
    if ($limit !== null && mb_strlen($clean) > $limit) {
        $clean = mb_substr($clean, 0, $limit) . '...';
    }
    return htmlspecialchars($clean);
}

function shouldShowSeeMore(string $text, array $images): bool {
    return !empty($images) && mb_strlen(cleanAnnouncementText($text)) > 180;
}

function formatTimeAgo($datetime) {
    $timestamp = strtotime($datetime);
    if (!$timestamp) {
        return '';
    }
    $diff = time() - $timestamp;

    if ($diff < 0) {
        $future = abs($diff);
        if ($future < 3600) {
            return 'In ' . ceil($future / 60) . 'm';
        }
        if ($future < 86400) {
            return 'In ' . ceil($future / 3600) . 'h';
        }
        return 'In ' . ceil($future / 86400) . 'd';
    }

    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    }
    return floor($diff / 86400) . 'd ago';
}

function getAnnouncementImages($item, $type) {
    if (!empty($item['images']) && is_array($item['images'])) {
        return array_values(array_filter($item['images'], fn($src) => !empty($src)));
    }

    $images = [];
    if ($type === 'ssc' && !empty($item['photo_url'])) {
        $images[] = $item['photo_url'];
    }
    if ($type === 'scholarship' && !empty($item['image_path'])) {
        $images[] = $item['image_path'];
    }
    return $images;
}

function getAnnouncementComments(array $item, string $type): array {
    // Return empty array - no default comments
    return [];
}

function renderCommentPreviewSection(array $comments, string $title): string {
    $count = count($comments);
    $commentData = htmlspecialchars(json_encode($comments), ENT_QUOTES);
    $html = '<button type="button" class="view-all-comments" data-comments="' . $commentData . '" data-count="' . $count . '" data-title="' . htmlspecialchars($title) . '">View all ' . $count . ' comments</button>';
    $html .= '<div class="comment-preview-list">';
    if ($count === 0) {
        $html .= '<div class="comment-preview no-comments">No comments yet. Be the first to reply.</div>';
    } else {
        foreach (array_slice($comments, 0, 2) as $comment) {
            $html .= '<div class="comment-preview ' . ($comment['role'] !== 'student' ? 'comment-verified' : '') . '">';
            $html .= '<div class="preview-header"><span class="preview-name">' . htmlspecialchars($comment['name']) . '</span>';
            if ($comment['role'] !== 'student') {
                $html .= '<span class="preview-badge">Verified Answer</span>';
            }
            $html .= '</div>';
            $html .= '<p>' . htmlspecialchars($comment['text']) . '</p>';
            $html .= '<span class="preview-time">' . htmlspecialchars($comment['time']) . '</span>';
            $html .= '</div>';
        }
    }
    $html .= '</div>';
    return $html;
}

function normalizeAnnouncementImagePath(string $path): string {
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, '/') || str_starts_with($path, '../') || str_starts_with($path, '..\\')) {
        return $path;
    }
    return '../' . $path;
}

function renderAnnouncementImageGrid(array $images): string {
    $images = array_values(array_filter($images, fn($src) => !empty($src)));
    if (empty($images)) {
        return '';
    }

    $images = array_map('normalizeAnnouncementImagePath', $images);
    $count = count($images);
    $modalImages = htmlspecialchars(json_encode($images), ENT_QUOTES);
    $grid = '<div class="announcement-images-grid" data-images="' . $modalImages . '">';

    if ($count === 1) {
        $grid .= '<div class="announcement-image-row single-row">';
        $grid .= '<button type="button" class="announcement-image-wrapper" data-image-index="0"><img src="' . htmlspecialchars($images[0]) . '" alt="Announcement image"></button>';
        $grid .= '</div>';
    } else {
        $topImages = array_slice($images, 0, min(2, $count));
        $bottomImages = array_slice($images, 2, min(3, $count - 2));
        $bottomClass = 'three-items';
        if (count($bottomImages) === 1) {
            $bottomClass = 'one-item';
        } elseif (count($bottomImages) === 2) {
            $bottomClass = 'two-items';
        }

        $grid .= '<div class="announcement-image-row top-row">';
        foreach ($topImages as $idx => $src) {
            $grid .= '<button type="button" class="announcement-image-wrapper" data-image-index="' . $idx . '"><img src="' . htmlspecialchars($src) . '" alt="Announcement image"></button>';
        }
        $grid .= '</div>';

        if (!empty($bottomImages)) {
            $grid .= '<div class="announcement-image-row bottom-row ' . $bottomClass . '">';
            foreach ($bottomImages as $idx => $src) {
                $imageIndex = $idx + count($topImages);
                $grid .= '<button type="button" class="announcement-image-wrapper" data-image-index="' . $imageIndex . '">';
                $grid .= '<img src="' . htmlspecialchars($src) . '" alt="Announcement image">';
                if ($count > 5 && $idx === 2) {
                    $grid .= '<div class="announcement-image-overlay">+' . ($count - 5) . '</div>';
                }
                $grid .= '</button>';
            }
            $grid .= '</div>';
        }
    }

    $grid .= '</div>';
    return $grid;
}

function getAnnouncementSourceLabel($type) {
    switch ($type) {
        case 'clinic':
            return 'School Clinic';
        case 'calendar':
            return 'School Calendar';
        case 'ssc':
            return 'SSC Events';
        case 'scholarship':
            return 'Scholarship Office';
        default:
            return 'Campus Announcement';
    }
}

?><!DOCTYPE html>
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
    <title>School Announcements | PASS Support System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        .announcement-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            grid-auto-rows: minmax(0, auto);
            gap: 20px;
        }

        @media (max-width: 768px) {
            .announcement-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .announcement-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px;
            }
        }

        .announcement-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            border-bottom: 4px solid #0066cc;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            min-height: 100%;
        }

        @media (max-width: 768px) {
            .announcement-card {
                padding: 16px;
                border-radius: 10px;
            }
        }

        .announcement-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .announcement-card.clinic {
            border-bottom-color: #800000;
        }

        .announcement-card.calendar {
            border-bottom-color: #28a745;
        }

        .announcement-card.ssc {
            border-bottom-color: #ff9800;
        }

        .announcement-card.scholarship {
            border-bottom-color: #6f42c1;
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .announcement-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0;
        }

        .announcement-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .announcement-badge.clinic {
            background: #fff0f0;
            color: #800000;
        }

        .announcement-badge.calendar {
            background: #f0fff4;
            color: #28a745;
        }

        .announcement-badge.ssc {
            background: #fff8f0;
            color: #ff9800;
        }

        .announcement-badge.scholarship {
            background: #f5f1ff;
            color: #6f42c1;
        }

        .announcement-meta {
            display: flex;
            gap: 16px;
            margin-bottom: 12px;
            font-size: 13px;
            color: #666;
            flex-wrap: wrap;
        }

        .announcement-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .announcement-description {
            color: #333;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .announcement-description.has-image.collapsed .announcement-text {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: normal;
        }

        .announcement-description p {
            margin: 0;
        }

        .toggle-description {
            margin-top: 8px;
            border: none;
            background: transparent;
            color: #0066cc;
            font-weight: 700;
            cursor: pointer;
            padding: 0;
        }

        .announcement-image {
            display: block;
            width: 100%;
            max-height: 260px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 14px;
        }

        .announcement-card img {
            display: block;
            width: 100%;
            height: auto;
        }

        .announcement-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid #eee;
            font-size: 13px;
            color: #666;
        }

        .announcement-identity {
            display: flex;
            gap: 14px;
            align-items: center;
            margin-bottom: 16px;
        }

        .announcement-avatar {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: #e5e7eb;
            display: grid;
            place-items: center;
            color: #1f2937;
            font-size: 20px;
        }

        .announcement-author-block {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .announcement-author {
            font-size: 0.98rem;
            font-weight: 700;
            color: #111827;
        }

        .announcement-source-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            color: #6b7280;
            font-size: 0.82rem;
        }

        .announcement-source-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .announcement-images-grid {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 14px;
            cursor: pointer;
        }

        .announcement-images-grid .announcement-image-row {
            display: grid;
            gap: 10px;
        }

        .announcement-images-grid .announcement-image-row.single-row {
            grid-template-columns: 1fr;
        }

        .announcement-images-grid .announcement-image-row.top-row {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .announcement-images-grid .announcement-image-row.bottom-row.one-item {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            justify-items: center;
        }

        .announcement-images-grid .announcement-image-row.bottom-row.one-item .announcement-image-wrapper {
            grid-column: 2 / 3;
            width: 100%;
        }

        .announcement-images-grid .announcement-image-row.bottom-row.two-items {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .announcement-images-grid .announcement-image-row.bottom-row.three-items {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .announcement-image-wrapper {
            position: relative;
            overflow: hidden;
            border-radius: 14px;
            aspect-ratio: 16 / 9;
            background: #f3f4f6;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .announcement-image-wrapper::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0);
            transition: background 0.3s ease;
            pointer-events: none;
        }

        .announcement-image-wrapper:hover {
            transform: translateY(-3px);
            box-shadow: 0 18px 35px rgba(15, 23, 42, 0.16);
        }

        .announcement-image-wrapper:hover::before {
            background: rgba(15, 23, 42, 0.18);
        }

        .announcement-image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.35s ease;
        }

        /* Normalize multi-image grid sizes and prevent overlap */
        .announcement-images-grid .announcement-image-row {
            align-items: stretch;
            grid-auto-rows: 1fr;
        }

        /* Top row keeps widescreen ratio, bottom row uses square tiles for uniform grid */
        .announcement-images-grid .announcement-image-row.top-row .announcement-image-wrapper {
            aspect-ratio: 16/9;
            height: auto;
        }

        .announcement-images-grid .announcement-image-row.bottom-row .announcement-image-wrapper {
            aspect-ratio: 1 / 1;
            height: auto;
        }

        /* Ensure wrappers fill their grid cells and keep overflow hidden */
        .announcement-image-wrapper {
            display: block;
            width: 100%;
            height: 100%;
            min-height: 80px;
            overflow: hidden;
        }

        .announcement-image-wrapper:hover img {
            transform: scale(1.05);
        }

        .announcement-image-overlay {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            color: white;
            display: grid;
            place-items: center;
            font-size: 1.5rem;
            font-weight: 700;
            z-index: 3;
        }

        .announcement-stats {
            display: none;
        }

        .announcement-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .announcement-actions button,
        .comment-input-group button {
            flex: 1;
            border: 1px solid #d1d5db;
            background: white;
            color: #111827;
            padding: 10px 14px;
            border-radius: 10px;
            cursor: pointer;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .announcement-actions button,
            .comment-input-group button {
                padding: 8px 10px;
                font-size: 0.85rem;
                gap: 6px;
            }
        }

        .announcement-actions .like-button {
            position: relative;
        }

        .announcement-actions .heart-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 24px;
            height: 24px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #111827;
            font-size: 0.85rem;
            padding: 0 8px;
        }

        .heart-bubble {
            position: absolute;
            right: 12px;
            top: -8px;
            width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: rgba(239, 68, 68, 0.95);
            color: white;
            font-size: 0.9rem;
            opacity: 0;
            pointer-events: none;
            transform: scale(0.75);
            animation: heart-pop 0.45s forwards;
        }

        @keyframes heart-pop {
            0% {
                opacity: 0;
                transform: scale(0.75) translateY(0);
            }
            40% {
                opacity: 1;
                transform: scale(1.2) translateY(-10px);
            }
            100% {
                opacity: 0;
                transform: scale(1.6) translateY(-28px);
            }
        }

        .view-all-comments {
            border: none;
            background: transparent;
            color: #0066cc;
            font-weight: 700;
            cursor: pointer;
            padding: 0;
            margin-bottom: 12px;
            text-align: left;
        }

        .comment-preview-list {
            display: grid;
            gap: 10px;
            margin-bottom: 16px;
        }

        .comment-preview {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 12px 14px;
        }

        .comment-preview.no-comments {
            color: #6b7280;
            font-size: 0.95rem;
        }

        .comment-preview p,
        .comment-preview .comment-text {
            margin: 8px 0 0;
            color: #111827;
            line-height: 1.4;
            max-height: 2.4em;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .comment-preview .comment-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #6b7280;
        }

        .comment-preview .comment-author {
            font-weight: 700;
            color: #111827;
        }

        .comment-preview .comment-meta-actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .comment-preview .comment-time {
            color: #6b7280;
            font-size: 0.8rem;
            white-space: nowrap;
        }

        .comment-preview .preview-badge {
            display: inline-flex;
            padding: 2px 8px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .comment-preview .preview-time {
            display: inline-block;
            margin-top: 6px;
            color: #6b7280;
            font-size: 0.8rem;
        }

        .comment-drawer-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            z-index: 1100;
        }

        .comment-drawer-overlay.open {
            opacity: 1;
            pointer-events: auto;
        }

        .comment-drawer {
            position: fixed;
            top: 0;
            right: 0;
            height: 100%;
            width: min(420px, 100%);
            max-width: 420px;
            background: #ffffff;
            box-shadow: -12px 0 32px rgba(15, 23, 42, 0.12);
            transform: translateX(100%);
            transition: transform 0.3s ease;
            z-index: 1101;
            display: flex;
            flex-direction: column;
        }

        .comment-drawer.open {
            transform: translateX(0);
        }

        .comment-drawer-header {
            padding: 20px 18px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .comment-drawer-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
            color: #111827;
        }

        .comment-drawer-header p {
            margin: 2px 0 0;
            color: #6b7280;
            font-size: 0.9rem;
        }

        .comment-drawer-close {
            border: none;
            background: transparent;
            cursor: pointer;
            color: #111827;
            font-size: 1.2rem;
        }

        .comment-drawer-body {
            padding: 18px;
            overflow-y: auto;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .comment-drawer-section {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .comment-drawer-section h4 {
            margin: 0;
            font-size: 0.95rem;
            color: #111827;
            font-weight: 700;
        }

        .comment-thread,
        .drawer-pinned-comments {
            display: grid;
            gap: 10px;
        }

        .comment-item {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 14px;
            display: grid;
            gap: 8px;
        }

        .comment-item .comment-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #6b7280;
        }

        .comment-item .comment-meta .comment-author {
            font-weight: 700;
            color: #111827;
        }

        .comment-item .comment-meta .comment-course {
            font-size: 0.8rem;
            color: #0066cc;
            font-weight: 500;
            background: #f0f4ff;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .comment-item .comment-meta .comment-time {
            color: #6b7280;
            font-size: 0.8rem;
            white-space: nowrap;
        }

        .comment-item .comment-text {
            margin: 0;
            color: #111827;
            line-height: 1.5;
            font-size: 0.95rem;
        }

        .comment-item .reply-button {
            border: none;
            background: transparent;
            color: #0066cc;
            cursor: pointer;
            font-weight: 700;
            padding: 0;
            font-size: 0.9rem;
            align-self: flex-start;
        }

        .comment-item .comment-delete-button {
            border: none;
            background: transparent;
            color: #6b7280;
            cursor: pointer;
            font-size: 0.9rem;
            padding: 0;
            margin-left: 10px;
        }

        .comment-item .comment-delete-button:hover {
            color: #dc2626;
        }

        .pinned-answer {
            background: #dbeafe;
            border-color: #bfdbfe;
        }

        .comment-drawer-footer {
            padding: 14px 18px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 10px;
            align-items: center;
            background: #ffffff;
            position: sticky;
            bottom: 0;
            z-index: 1;
        }

        .comment-drawer-footer input {
            flex: 1;
            border: 1px solid #d1d5db;
            border-radius: 999px;
            padding: 12px 16px;
            font-size: 0.95rem;
            color: #111827;
            outline: none;
        }

        .comment-drawer-footer button {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: none;
            background: #0066cc;
            color: white;
            display: grid;
            place-items: center;
            cursor: pointer;
        }

        .comment-drawer-footer button.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .comment-drawer-footer button.loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .comment-input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
        }

        .comment-input-group input {
            flex: 1;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.95rem;
            color: #111827;
        }

        .comment-feed {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .comment-bubble {
            background: #f9fafb;
            padding: 12px 14px;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
        }

        .comment-meta {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            font-size: 0.8rem;
            color: #6b7280;
            margin-bottom: 6px;
        }

        .comment-bubble p {
            margin: 0;
            color: #111827;
            line-height: 1.5;
        }

        .image-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.75);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 24px;
        }

        .image-modal.open {
            display: flex;
        }

        .image-modal-content {
            width: min(100%, 980px);
            max-height: calc(100vh - 48px);
            overflow: hidden;
            background: #ffffff;
            border-radius: 24px;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .image-modal-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            padding: 24px 24px 0;
        }

        .image-modal-header-text {
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-width: 0;
        }

        .image-modal-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #2563eb;
            background: #eff6ff;
            padding: 6px 12px;
            border-radius: 999px;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .image-modal-title {
            margin: 0;
            font-size: 1.35rem;
            line-height: 1.2;
            color: #111827;
        }

        .image-modal-description {
            margin: 0;
            color: #4b5563;
            font-size: 0.95rem;
            line-height: 1.6;
            max-height: 4.2rem;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .image-modal-close {
            position: absolute;
            top: 24px;
            right: 24px;
            border: none;
            background: transparent;
            color: #111827;
            font-size: 1.4rem;
            cursor: pointer;
        }

        .image-modal-list {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 320px;
            max-height: calc(100vh - 240px);
            overflow: auto;
            padding: 22px 24px 0;
        }

        .image-modal-slide {
            position: relative;
            width: 100%;
            max-width: 100%;
        }

        .image-modal-list img {
            width: 100%;
            height: auto;
            max-height: calc(70vh - 120px);
            border-radius: 20px;
            display: block;
        }

        .image-modal-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            border: none;
            background: rgba(255, 255, 255, 0.85);
            color: #111827;
            border-radius: 50%;
            cursor: pointer;
            display: grid;
            place-items: center;
            transition: background 0.2s ease;
        }

        .image-modal-nav:hover {
            background: rgba(255, 255, 255, 1);
        }

        .image-modal-nav.prev {
            left: 14px;
        }

        .image-modal-nav.next {
            right: 14px;
        }

        .image-modal-counter {
            position: relative;
            left: auto;
            bottom: auto;
            transform: none;
            margin: 14px auto 0;
            background: rgba(15, 23, 42, 0.08);
            color: #111827;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 0.9rem;
        }

        .image-modal-actions {
            position: relative;
            bottom: auto;
            left: auto;
            transform: none;
            display: flex;
            justify-content: center;
            gap: 12px;
            margin: 16px 24px 24px;
            background: rgba(255, 255, 255, 0.9);
            padding: 10px 14px;
            border-radius: 22px;
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.08);
        }

        @media (max-width: 768px) {
            .image-modal-content {
                width: min(100%, 100%);
            }

            .image-modal-list {
                padding: 16px 16px 0;
                max-height: calc(100vh - 220px);
            }

            .image-modal-nav {
                display: none;
            }

            .image-modal-counter {
                margin-top: 12px;
            }
        }

        .modal-like-button,
        .modal-comment-button {
            border: none;
            background: transparent;
            color: #111827;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 16px;
            transition: background 0.2s ease;
        }

        .modal-like-button:hover,
        .modal-comment-button:hover {
            background: rgba(0, 0, 0, 0.1);
        }

        .modal-like-button.liked {
            color: #dc2626;
        }

        .modal-like-button.liked i {
            color: #dc2626;
        }

        .announcement-image-wrapper {
            cursor: pointer;
            border: none;
            background: transparent;
            padding: 0;
        }

        .comment-drawer.modal-embedded {
            display: flex;
            flex-direction: column;
            position: relative;
            width: 100%;
            max-height: 50vh;
            margin-top: 20px;
            box-shadow: none;
            border-top: 1px solid #e5e7eb;
            transform: none;
            right: auto;
            bottom: auto;
            top: auto;
            max-width: none;
            z-index: 10005; /* ensure drawer sits above floating chatbot */
            overflow: hidden;
        }

        /* Reserve space in image modal when comment drawer is embedded */
        .image-modal-content.with-drawer {
            padding-bottom: 0 !important;
            max-height: 90vh;
        }

        /* Ensure embedded drawer footer stays visible and within rounded modal */
        .comment-drawer.modal-embedded .comment-drawer-footer {
            position: sticky;
            bottom: 0;
            background: #ffffff;
            z-index: 2;
            border-top: 1px solid #e5e7eb;
        }

        .comment-drawer.modal-embedded .comment-drawer-body {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding-right: 0;
        }

        .image-modal {
            z-index: 10005;
        }

        .event-time {
            background: #f0f4f8;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 12px;
            opacity: 0.5;
        }

        .tabs-container {
            display: flex;
            gap: 10px;
            background: white;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
            padding: 0.25rem;
            flex-wrap: wrap;
        }

        .tab-button {
            flex: 1 1 auto;
            padding: 0.75rem 0.5rem;
            border: none;
            background: #f8f9fa;
            color: black;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.3s ease;
            border-radius: 8px;
            min-width: 80px;
        }

        @media (max-width: 768px) {
            .tabs-container {
                flex-direction: row;
                flex-wrap: nowrap;
                gap: 8px;
                padding: 8px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .tab-button {
                flex: 0 0 auto;
                padding: 0.6rem 0.9rem;
                font-size: 0.85rem;
                min-width: 110px;
            }

            .tab-button i {
                margin-right: 0.3rem;
                display: inline-block;
            }
        }

        @media (max-width: 475px) {
            .tab-button {
                min-width: 100px;
                padding: 0.55rem 0.8rem;
                font-size: 0.8rem;
            }
        }

        .tab-button i {
            color: #800000;
            margin-right: 0.6rem;
        }

        .tab-button.active {
            background: #800000;
            color: white;
        }

        .tab-button.active i {
            color: #D4AF37;
        }

        .tab-button:hover:not(.active) {
            background: #800000;
            color: white;
        }

        .tab-button:hover:not(.active) i {
            color: #D4AF37;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .event-type-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            background: #f0f4f8;
            color: #0066cc;
        }

        .priority-high {
            background: #fff3cd;
            color: #856404;
        }

        .priority-medium {
            background: #e2e3e5;
            color: #383d41;
        }

        .priority-low {
            background: #d1ecf1;
            color: #0c5460;
        }
    </style>
</head>
<body class="<?= $isStudent ? 'student-dashboard' : 'teacher-dashboard' ?>">
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
                <a href="<?= $homeLink ?>" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-house"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="school_announcements.php" class="active" data-tooltip="Announcements">
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
                    <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p><?= $portalType ?></p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta"><?= htmlspecialchars($displayCourse) ?></span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Profile" data-tooltip="Profile" data-menu-target="profileMenu"><i class="fa-solid fa-user"></i></button>

                    <div class="topbar-menu" id="notificationMenu" role="menu" aria-label="Notifications menu">
                        <div class="menu-header">
                            <strong>Notifications</strong>
                            <span class="menu-note">Latest announcements</span>
                        </div>
                        <?php if (!empty($clinicAnnouncements)): ?>
                            <div class="menu-items">
                                <?php foreach (array_slice($clinicAnnouncements, 0, 3) as $notification): ?>
                                    <div class="menu-item notification-item" role="menuitem">
                                        <p class="notification-title"><?= htmlspecialchars($notification['title']) ?></p>
                                        <p class="notification-meta"><?= htmlspecialchars(date('M j, Y', strtotime($notification['created_at']))) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="menu-empty">No new announcements.</div>
                        <?php endif; ?>
                        <a class="menu-link menu-footer-link" href="school_announcements.php">View all announcements</a>
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
                <section class="page-section">
                    <div class="section-header">
                        <h1><i class="fa-solid fa-bell"></i> School Announcements</h1>
                        <p>Stay updated with the latest news and announcements from all services</p>
                    </div>

                    <div class="management-content">
                        <!-- Tabs Navigation -->
                        <div class="tabs-container">
                            <button class="tab-button active" data-tab="all" onclick="switchTab('all', this)">
                                <i class="fa-solid fa-list"></i> All
                            </button>
                            <button class="tab-button" data-tab="clinic" onclick="switchTab('clinic', this)">
                                <i class="fa-solid fa-stethoscope"></i> Clinic
                            </button>
                            <button class="tab-button" data-tab="calendar" onclick="switchTab('calendar', this)">
                                <i class="fa-solid fa-calendar"></i> Announcements
                            </button>
                            <button class="tab-button" data-tab="ssc" onclick="switchTab('ssc', this)">
                                <i class="fa-solid fa-award"></i> Events & Activities
                            </button>
                            <button class="tab-button" data-tab="scholarship" onclick="switchTab('scholarship', this)">
                                <i class="fa-solid fa-hand-holding-dollar"></i> Scholarships
                            </button>
                        </div>

                        <!-- All Announcements Tab -->
                        <div id="all" class="tab-content active">
                            <div class="announcement-grid">
                                <?php
                                // Combine all announcements with timestamps for sorting
                                $allAnnouncements = [];
                                
                                foreach ($clinicAnnouncements as $ann) {
                                    $allAnnouncements[] = array_merge($ann, ['type' => 'clinic', 'sort_date' => $ann['created_at']]);
                                }
                                foreach ($calendarEvents as $evt) {
                                    $allAnnouncements[] = array_merge($evt, ['type' => 'calendar', 'sort_date' => $evt['event_date']]);
                                }
                                foreach ($sscEvents as $evt) {
                                    $allAnnouncements[] = array_merge($evt, ['type' => 'ssc', 'sort_date' => $evt['event_date']]);
                                }
                                foreach ($scholarshipAnnouncements as $ann) {
                                    $allAnnouncements[] = array_merge($ann, ['type' => 'scholarship', 'sort_date' => $ann['created_at']]);
                                }
                                
                                // Sort by date
                                usort($allAnnouncements, function($a, $b) {
                                    return strtotime($b['sort_date']) - strtotime($a['sort_date']);
                                });
                                
                                if (empty($allAnnouncements)): ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fa-solid fa-inbox"></i></div>
                                        <p>No announcements at this time</p>
                                    </div>
                                <?php else:
                                    foreach ($allAnnouncements as $ann): ?>
                                        <?php $images = getAnnouncementImages($ann, $ann['type']); ?>
                                        <div class="announcement-card <?= htmlspecialchars($ann['type']) ?>" data-type="<?= htmlspecialchars($ann['type']) ?>" data-id="<?= htmlspecialchars($ann['id'] ?? '') ?>">
                                            <div class="announcement-identity">
                                                <div class="announcement-avatar"><i class="fa-solid fa-bullhorn"></i></div>
                                                <div class="announcement-author-block">
                                                    <div class="announcement-author"><?= htmlspecialchars(getAnnouncementSourceLabel($ann['type'])) ?></div>
                                                    <div class="announcement-source-meta">
                                                        <span><i class="fa-solid fa-clock"></i> <?= formatTimeAgo($ann['type'] === 'calendar' || $ann['type'] === 'ssc' ? $ann['event_date'] : $ann['created_at']) ?></span>
                                                        <span><i class="fa-solid fa-globe"></i> Public</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="announcement-header">
                                                <h3 class="announcement-title">
                                                    <?= htmlspecialchars($ann['type'] === 'calendar' ? $ann['event_type'] : $ann['title']) ?>
                                                </h3>
                                                <span class="announcement-badge <?= htmlspecialchars($ann['type']) ?>">
                                                    <?= ucfirst($ann['type']) ?>
                                                </span>
                                            </div>
                                            <div class="announcement-description<?= !empty($images) ? ' has-image collapsed' : '' ?>">
                                                <p class="announcement-text"><?= nl2br(renderAnnouncementText($ann['description'])) ?></p>
                                                <?php if (shouldShowSeeMore($ann['description'], $images)): ?>
                                                    <button type="button" class="toggle-description">See more</button>
                                                <?php endif; ?>
                                            </div>
                                            <?= renderAnnouncementImageGrid($images) ?>
                                            <div class="announcement-actions">
                                                <button type="button" class="like-button"><i class="fa-regular fa-heart"></i> Like <span class="heart-count">0</span></button>
                                                <button type="button" class="comment-button"><i class="fa-regular fa-comment"></i> Comment</button>
                                            </div>
                                            <?= renderCommentPreviewSection(getAnnouncementComments($ann, $ann['type']), $ann['type'] === 'calendar' ? ucfirst(str_replace('_', ' ', $ann['event_type'])) : ($ann['title'] ?? 'Announcement')) ?>
                                        </div>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                        </div>

                        <!-- Clinic Announcements Tab -->
                        <div id="clinic" class="tab-content">
                            <div class="announcement-grid">
                                <?php if (empty($clinicAnnouncements)): ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fa-solid fa-stethoscope"></i></div>
                                        <p>No clinic announcements at this time</p>
                                    </div>
                                <?php else:
                                    foreach ($clinicAnnouncements as $ann): ?>
                                        <?php $images = getAnnouncementImages($ann, 'clinic'); ?>
                                        <div class="announcement-card clinic" data-type="clinic" data-id="<?= htmlspecialchars($ann['id'] ?? '') ?>">
                                            <div class="announcement-identity">
                                                <div class="announcement-avatar"><i class="fa-solid fa-hospital-user"></i></div>
                                                <div class="announcement-author-block">
                                                    <div class="announcement-author">School Clinic</div>
                                                    <div class="announcement-source-meta">
                                                        <span><i class="fa-solid fa-clock"></i> <?= formatTimeAgo($ann['created_at']) ?></span>
                                                        <span><i class="fa-solid fa-globe"></i> Public</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="announcement-header">
                                                <h3 class="announcement-title"><?= htmlspecialchars($ann['title']) ?></h3>
                                                <span class="announcement-badge clinic">Clinic</span>
                                            </div>
                                            <div class="announcement-meta">
                                                <span class="announcement-meta-item">
                                                    <i class="fa-solid fa-calendar-days"></i>
                                                    <?= date('M j, Y', strtotime($ann['created_at'])) ?>
                                                </span>
                                                <span class="announcement-meta-item priority-<?= htmlspecialchars(strtolower($ann['priority'] ?? 'medium')) ?>">
                                                    Priority: <?= ucfirst($ann['priority'] ?? 'Medium') ?>
                                                </span>
                                                <span class="announcement-meta-item event-type-badge">
                                                    <?= ucfirst($ann['category'] ?? 'Update') ?>
                                                </span>
                                            </div>
                                            <div class="announcement-description<?= !empty($images) ? ' has-image collapsed' : '' ?>">
                                                <p class="announcement-text"><?= nl2br(renderAnnouncementText($ann['description'])) ?></p>
                                                <?php if (shouldShowSeeMore($ann['description'], $images)): ?>
                                                    <button type="button" class="toggle-description">See more</button>
                                                <?php endif; ?>
                                            </div>
                                            <div class="announcement-actions">
                                                <button type="button" class="like-button"><i class="fa-regular fa-heart"></i> Like <span class="heart-count">0</span></button>
                                                <button type="button" class="comment-button"><i class="fa-regular fa-comment"></i> Comment</button>
                                            </div>
                                            <?= renderCommentPreviewSection(getAnnouncementComments($ann, 'clinic'), $ann['title']) ?>
                                        </div>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                        </div>

                        <!-- School Calendar Tab -->
                        <div id="calendar" class="tab-content">
                            <div class="announcement-grid">
                                <?php if (empty($calendarEvents)): ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fa-solid fa-calendar"></i></div>
                                        <p>No upcoming school events</p>
                                    </div>
                                <?php else:
                                    foreach ($calendarEvents as $evt): ?>
                                        <?php $images = getAnnouncementImages($evt, 'calendar'); ?>
                                        <div class="announcement-card calendar" data-type="calendar" data-id="<?= htmlspecialchars($evt['id'] ?? '') ?>">
                                            <div class="announcement-identity">
                                                <div class="announcement-avatar"><i class="fa-solid fa-calendar-days"></i></div>
                                                <div class="announcement-author-block">
                                                    <div class="announcement-author">School Calendar</div>
                                                    <div class="announcement-source-meta">
                                                        <span><i class="fa-solid fa-clock"></i> <?= formatTimeAgo($evt['event_date']) ?></span>
                                                        <span><i class="fa-solid fa-globe"></i> Public</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="announcement-header">
                                                <h3 class="announcement-title"><?= ucfirst(str_replace('_', ' ', $evt['event_type'])) ?></h3>
                                                <span class="announcement-badge calendar"><?= ucfirst($evt['event_type']) ?></span>
                                            </div>
                                            <div class="announcement-meta">
                                                <span class="announcement-meta-item">
                                                    <i class="fa-solid fa-calendar-days"></i>
                                                    <?= date('M j, Y', strtotime($evt['event_date'])) ?>
                                                </span>
                                                <?php if (!empty($evt['start_time'])): ?>
                                                    <span class="announcement-meta-item event-time">
                                                        <i class="fa-solid fa-clock"></i>
                                                        <?= date('H:i', strtotime($evt['start_time'])) ?> - <?= date('H:i', strtotime($evt['end_time'])) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="announcement-description<?= !empty($images) ? ' has-image collapsed' : '' ?>">
                                                <p class="announcement-text"><?= nl2br(renderAnnouncementText($evt['description'])) ?></p>
                                                <?php if (shouldShowSeeMore($evt['description'], $images)): ?>
                                                    <button type="button" class="toggle-description">See more</button>
                                                <?php endif; ?>
                                            </div>
                                            <?= renderAnnouncementImageGrid($images) ?>
                                            <div class="announcement-actions">
                                                <button type="button" class="like-button"><i class="fa-regular fa-heart"></i> Like <span class="heart-count">0</span></button>
                                                <button type="button" class="comment-button"><i class="fa-regular fa-comment"></i> Comment</button>
                                            </div>
                                            <?= renderCommentPreviewSection(getAnnouncementComments($evt, 'calendar'), ucfirst(str_replace('_', ' ', $evt['event_type']))) ?>
                                        </div>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                        </div>

                        <!-- SSC Events Tab -->
                        <div id="ssc" class="tab-content">
                            <div class="announcement-grid">
                                <?php if (empty($sscEvents)): ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fa-solid fa-award"></i></div>
                                        <p>No upcoming SSC events</p>
                                    </div>
                                <?php else:
                                    foreach ($sscEvents as $evt): ?>
                                        <?php $images = getAnnouncementImages($evt, 'ssc'); ?>
                                        <div class="announcement-card ssc" data-type="ssc" data-id="<?= htmlspecialchars($evt['id'] ?? '') ?>">
                                            <div class="announcement-identity">
                                                <div class="announcement-avatar"><i class="fa-solid fa-award"></i></div>
                                                <div class="announcement-author-block">
                                                    <div class="announcement-author">SSC Events</div>
                                                    <div class="announcement-source-meta">
                                                        <span><i class="fa-solid fa-clock"></i> <?= formatTimeAgo($evt['event_date']) ?></span>
                                                        <span><i class="fa-solid fa-globe"></i> Public</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="announcement-header">
                                                <h3 class="announcement-title"><?= htmlspecialchars($evt['title']) ?></h3>
                                                <span class="announcement-badge ssc">SSC Event</span>
                                            </div>
                                            <div class="announcement-meta">
                                                <span class="announcement-meta-item">
                                                    <i class="fa-solid fa-calendar-days"></i>
                                                    <?= date('M j, Y', strtotime($evt['event_date'])) ?>
                                                </span>
                                                <?php if (!empty($evt['location'])): ?>
                                                    <span class="announcement-meta-item">
                                                        <i class="fa-solid fa-map-pin"></i>
                                                        <?= htmlspecialchars($evt['location']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="announcement-description<?= !empty($images) ? ' has-image collapsed' : '' ?>">
                                                <p class="announcement-text"><?= nl2br(renderAnnouncementText($evt['description'])) ?></p>
                                                <?php if (shouldShowSeeMore($evt['description'], $images)): ?>
                                                    <button type="button" class="toggle-description">See more</button>
                                                <?php endif; ?>
                                            </div>
                                            <?= renderAnnouncementImageGrid($images) ?>
                                            <div class="announcement-actions">
                                                <button type="button" class="like-button"><i class="fa-regular fa-heart"></i> Like <span class="heart-count">0</span></button>
                                                <button type="button" class="comment-button"><i class="fa-regular fa-comment"></i> Comment</button>
                                            </div>
                                            <?= renderCommentPreviewSection(getAnnouncementComments($evt, 'ssc'), $evt['title']) ?>
                                        </div>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                        </div>

                        <!-- Scholarship Announcements Tab -->
                        <div id="scholarship" class="tab-content">
                            <div class="announcement-grid">
                                <?php if (empty($scholarshipAnnouncements)): ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                                        <p>No active scholarship announcements</p>
                                    </div>
                                <?php else:
                                    foreach ($scholarshipAnnouncements as $ann): ?>
                                        <?php $images = getAnnouncementImages($ann, 'scholarship'); ?>
                                        <div class="announcement-card scholarship" data-type="scholarship" data-id="<?= htmlspecialchars($ann['id'] ?? '') ?>">
                                            <div class="announcement-identity">
                                                <div class="announcement-avatar"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                                                <div class="announcement-author-block">
                                                    <div class="announcement-author">Scholarship Office</div>
                                                    <div class="announcement-source-meta">
                                                        <span><i class="fa-solid fa-clock"></i> <?= formatTimeAgo($ann['created_at']) ?></span>
                                                        <span><i class="fa-solid fa-globe"></i> Public</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="announcement-header">
                                                <h3 class="announcement-title"><?= htmlspecialchars($ann['title']) ?></h3>
                                                <span class="announcement-badge scholarship">Scholarship</span>
                                            </div>
                                            <div class="announcement-meta">
                                                <span class="announcement-meta-item">
                                                    <i class="fa-solid fa-calendar-days"></i>
                                                    <?= date('M j, Y', strtotime($ann['created_at'])) ?>
                                                </span>
                                                <?php if (!empty($ann['deadline'])): ?>
                                                    <span class="announcement-meta-item">
                                                        <i class="fa-solid fa-hourglass-end"></i>
                                                        Deadline: <?= date('M j, Y', strtotime($ann['deadline'])) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="announcement-description<?= !empty($images) ? ' has-image collapsed' : '' ?>">
                                                <p class="announcement-text"><?= nl2br(renderAnnouncementText($ann['description'])) ?></p>
                                                <?php if (shouldShowSeeMore($ann['description'], $images)): ?>
                                                    <button type="button" class="toggle-description">See more</button>
                                                <?php endif; ?>
                                            </div>
                                            <?= renderAnnouncementImageGrid($images) ?>
                                            <div class="announcement-actions">
                                                <button type="button" class="like-button"><i class="fa-regular fa-heart"></i> Like <span class="heart-count">0</span></button>
                                                <button type="button" class="comment-button"><i class="fa-regular fa-comment"></i> Comment</button>
                                            </div>
                                            <?= renderCommentPreviewSection(getAnnouncementComments($ann, 'scholarship'), $ann['title']) ?>
                                        </div>
                                    <?php endforeach;
                                endif; ?>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
        <div class="page-overlay" id="pageOverlay"></div>
    </div>

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
        <div class="footer-copyright">© <?= date('Y') ?> PASS College. All rights reserved.</div>
    </footer>

    <div class="image-modal" id="announcementImageModal" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="image-modal-content">
            <div class="image-modal-header">
                <div class="image-modal-header-text">
                    <span class="image-modal-badge">Announcement</span>
                    <h2 class="image-modal-title" id="modalImageTitle">Announcement</h2>
                    <p class="image-modal-description" id="modalImageDescription">Open the image to view full screen, interact with the announcement, and access likes/comments.</p>
                </div>
                <button class="image-modal-close" type="button" onclick="closeAnnouncementModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="image-modal-list" id="modalImageList"></div>
        </div>
    </div>

    <div class="comment-drawer-overlay" id="commentDrawerOverlay"></div>
    <aside class="comment-drawer" id="commentDrawer" aria-hidden="true">
        <div class="comment-drawer-header">
            <div>
                <h3 id="commentDrawerTitle">Comments</h3>
                <p id="commentDrawerSubtitle">View latest replies and pinned answers</p>
            </div>
            <button type="button" class="comment-drawer-close" id="commentDrawerClose"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="comment-drawer-body" id="commentDrawerBody">
            <section class="comment-drawer-section">
                <h4>Pinned answers</h4>
                <div class="drawer-pinned-comments" id="drawerPinnedComments"></div>
            </section>
            <section class="comment-drawer-section">
                <h4>Conversation</h4>
                <div class="comment-thread" id="commentThread"></div>
            </section>
        </div>
        <div class="comment-drawer-footer">
            <input type="text" id="commentDrawerInput" placeholder="Write a reply..." aria-label="Write a reply" />
            <button type="button" id="commentDrawerSend"><i class="fa-solid fa-paper-plane"></i></button>
        </div>
    </aside>

    <script src="../assets/js/app.js" defer></script>
    <script>
        function switchTab(tabName, buttonElement) {
            // Hide all tabs
            const allTabs = document.querySelectorAll('.tab-content');
            allTabs.forEach(tab => tab.classList.remove('active'));

            // Remove active class from all buttons
            const allButtons = document.querySelectorAll('.tab-button');
            allButtons.forEach(btn => btn.classList.remove('active'));

            // Show selected tab and mark button as active
            const tabElement = document.getElementById(tabName);
            if (tabElement) {
                tabElement.classList.add('active');
            }
            if (buttonElement) {
                buttonElement.classList.add('active');
            }
        }

        // Swipe navigation for tabs on touch devices
        (function() {
            const tabsOrder = ['all','clinic','calendar','ssc','scholarship'];
            let touchStartX = 0;
            let touchEndX = 0;

            const contentArea = document.querySelector('.management-content');
            function getActiveTabIndex() {
                const active = document.querySelector('.tab-content.active');
                if (!active) return 0;
                return tabsOrder.indexOf(active.id) || 0;
            }

            function activateTabByIndex(idx) {
                idx = Math.max(0, Math.min(tabsOrder.length - 1, idx));
                const name = tabsOrder[idx];
                const btn = document.querySelector('.tab-button[data-tab="' + name + '"]');
                if (btn) switchTab(name, btn);
            }

            function handleSwipe() {
                const dx = touchEndX - touchStartX;
                if (Math.abs(dx) < 50) return; // ignore small movements
                const current = getActiveTabIndex();
                if (dx < 0) { // left swipe -> next
                    activateTabByIndex(current + 1);
                } else { // right swipe -> previous
                    activateTabByIndex(current - 1);
                }
            }

            if (contentArea) {
                contentArea.addEventListener('touchstart', function(e) {
                    touchStartX = e.changedTouches[0].screenX;
                }, {passive: true});

                contentArea.addEventListener('touchend', function(e) {
                    touchEndX = e.changedTouches[0].screenX;
                    handleSwipe();
                }, {passive: true});
            }
        })();

        // Ensure swipe only activates on small viewports
        (function() {
            const container = document.querySelector('.tabs-container');
            if (!container) return;
            let ts = 0, te = 0;
            container.addEventListener('touchstart', function(e) {
                if (window.innerWidth < 320 || window.innerWidth > 768) return;
                if (e.touches && e.touches.length === 1) ts = e.touches[0].clientX;
            }, { passive: true });
            container.addEventListener('touchend', function(e) {
                if (window.innerWidth < 320 || window.innerWidth > 768) return;
                if (e.changedTouches && e.changedTouches.length === 1) {
                    te = e.changedTouches[0].clientX;
                    const dx = te - ts;
                    if (Math.abs(dx) < 50) return;
                    const activeBtn = document.querySelector('.tab-button.active');
                    const buttons = Array.from(document.querySelectorAll('.tab-button'));
                    const idx = buttons.indexOf(activeBtn);
                    if (dx < 0 && idx < buttons.length - 1) buttons[idx + 1].click();
                    if (dx > 0 && idx > 0) buttons[idx - 1].click();
                }
            }, { passive: true });
        })();

        let currentModalImages = [];
        let currentModalImageIndex = 0;
        let currentModalContext = null;
        let modalTouchStartX = 0;

        function openAnnouncementModal(images, startIndex = 0, context = null) {
            currentModalImages = Array.isArray(images) ? images.slice() : [];
            currentModalImageIndex = Math.max(0, Math.min(startIndex, currentModalImages.length - 1));
            currentModalContext = context;
            renderModalImage();
            const modal = document.getElementById('announcementImageModal');
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        }

        function setupModalSwipe() {
            const modal = document.getElementById('announcementImageModal');
            if (!modal) return;

            modal.addEventListener('touchstart', function (event) {
                if (event.touches.length === 1) {
                    modalTouchStartX = event.touches[0].clientX;
                }
            }, { passive: true });

            modal.addEventListener('touchend', function (event) {
                if (event.changedTouches.length !== 1) return;
                const touchEndX = event.changedTouches[0].clientX;
                const deltaX = touchEndX - modalTouchStartX;
                if (Math.abs(deltaX) < 60) return;

                if (deltaX < 0) {
                    showModalImage(currentModalImageIndex + 1);
                } else {
                    showModalImage(currentModalImageIndex - 1);
                }
            }, { passive: true });
        }

        setupModalSwipe();

        function renderModalImage() {
            const list = document.getElementById('modalImageList');
            const titleEl = document.getElementById('modalImageTitle');
            const descriptionEl = document.getElementById('modalImageDescription');

            if (titleEl) {
                titleEl.textContent = currentModalContext?.title || 'Announcement';
            }
            if (descriptionEl) {
                descriptionEl.textContent = currentModalContext?.description || 'Open the image to view full screen, interact with the announcement, and access likes/comments.';
            }

            list.innerHTML = '';
            if (!currentModalImages.length) {
                return;
            }

            const slide = document.createElement('div');
            slide.className = 'image-modal-slide';

            const image = document.createElement('img');
            image.src = currentModalImages[currentModalImageIndex];
            image.alt = 'Announcement image';
            slide.appendChild(image);

            if (currentModalImages.length > 1) {
                const prevButton = document.createElement('button');
                prevButton.type = 'button';
                prevButton.className = 'image-modal-nav prev';
                prevButton.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
                prevButton.addEventListener('click', function (event) {
                    event.stopPropagation();
                    showModalImage(currentModalImageIndex - 1);
                });

                const nextButton = document.createElement('button');
                nextButton.type = 'button';
                nextButton.className = 'image-modal-nav next';
                nextButton.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
                nextButton.addEventListener('click', function (event) {
                    event.stopPropagation();
                    showModalImage(currentModalImageIndex + 1);
                });

                const counter = document.createElement('div');
                counter.className = 'image-modal-counter';
                counter.textContent = `${currentModalImageIndex + 1} of ${currentModalImages.length}`;

                slide.appendChild(prevButton);
                slide.appendChild(nextButton);
                slide.appendChild(counter);
            }

            if (currentModalContext) {
                const actions = document.createElement('div');
                actions.className = 'image-modal-actions';

                const likeButton = document.createElement('button');
                likeButton.type = 'button';
                likeButton.className = 'modal-like-button';
                likeButton.innerHTML = '<i class="fa-regular fa-heart"></i> Like';
                
                // Load initial like state
                getSavedLike(currentModalContext.type, currentModalContext.id).then(likeData => {
                    if (likeData.hasLiked) {
                        likeButton.classList.add('liked');
                        likeButton.innerHTML = '<i class="fa-solid fa-heart"></i> Like';
                    }
                });
                
                likeButton.addEventListener('click', async function (event) {
                    event.stopPropagation();
                    this.disabled = true;
                    
                    const result = await saveLike(currentModalContext.type, currentModalContext.id, true);
                    if (result.success) {
                        this.classList.toggle('liked');
                        if (this.classList.contains('liked')) {
                            this.innerHTML = '<i class="fa-solid fa-heart"></i> Like';
                            createHeartBurst(this);
                        } else {
                            this.innerHTML = '<i class="fa-regular fa-heart"></i> Like';
                        }
                    }
                    
                    this.disabled = false;
                });

                const commentButton = document.createElement('button');
                commentButton.type = 'button';
                commentButton.className = 'modal-comment-button';
                commentButton.innerHTML = '<i class="fa-regular fa-comment"></i> Comment';
                commentButton.addEventListener('click', function (event) {
                    event.stopPropagation();
                    openCommentDrawer(currentModalContext.title, currentModalContext.commentsData, currentModalContext, true);
                });

                actions.appendChild(likeButton);
                actions.appendChild(commentButton);
                slide.appendChild(actions);
            }

            list.appendChild(slide);
        }

        function showModalImage(index) {
            if (!currentModalImages.length) {
                return;
            }
            if (index < 0) {
                index = currentModalImages.length - 1;
            } else if (index >= currentModalImages.length) {
                index = 0;
            }
            currentModalImageIndex = index;
            renderModalImage();
        }

        function handleAnnouncementImageClick(wrapper) {
            const imageGrid = wrapper.closest('.announcement-images-grid');
            const images = imageGrid ? JSON.parse(imageGrid.dataset.images || '[]') : [];
            const index = parseInt(wrapper.dataset.imageIndex, 10) || 0;
            const card = wrapper.closest('.announcement-card');
            const type = card ? card.dataset.type : null;
            const id = card ? card.dataset.id : null;
            const title = card ? card.querySelector('.announcement-title')?.textContent.trim() : 'Announcement';
            const rawDescription = card ? card.querySelector('.announcement-text')?.textContent.trim() : '';
            const description = rawDescription ? rawDescription.replace(/\s+/g, ' ').slice(0, 180) + (rawDescription.length > 180 ? '...' : '') : '';
            const previewButton = card ? card.querySelector('.view-all-comments') : null;
            const commentsData = previewButton && previewButton.dataset.comments ? JSON.parse(previewButton.dataset.comments) : [];
            const context = type && id ? { type, id, previewButton, title, description, commentsData } : null;
            openAnnouncementModal(images, index, context);
        }

        function closeAnnouncementModal() {
            const modal = document.getElementById('announcementImageModal');
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            closeCommentDrawer();
        }

        document.getElementById('announcementImageModal').addEventListener('click', function (event) {
            if (event.target === this) {
                closeAnnouncementModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            const modal = document.getElementById('announcementImageModal');
            if (!modal.classList.contains('open')) {
                return;
            }
            if (event.key === 'ArrowLeft') {
                showModalImage(currentModalImageIndex - 1);
            } else if (event.key === 'ArrowRight') {
                showModalImage(currentModalImageIndex + 1);
            } else if (event.key === 'Escape') {
                closeAnnouncementModal();
            }
        });

        document.addEventListener('click', function (event) {
            const toggle = event.target.closest('.toggle-description');
            if (!toggle) {
                return;
            }
            const descriptionWrapper = toggle.closest('.announcement-description');
            if (!descriptionWrapper) {
                return;
            }
            descriptionWrapper.classList.toggle('collapsed');
            toggle.textContent = descriptionWrapper.classList.contains('collapsed') ? 'See more' : 'See less';
        });

        let currentCommentContext = null;

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatRelativeTime(timestamp) {
            const parsedTimestamp = Number(String(timestamp).trim());
            if (Number.isNaN(parsedTimestamp) || parsedTimestamp <= 0) {
                return 'Just now';
            }
            const diffMs = Date.now() - parsedTimestamp;
            const diffMin = Math.floor(diffMs / 60000);
            if (diffMin < 1) {
                return 'Just now';
            }
            if (diffMin < 60) {
                return `${diffMin}m ago`;
            }
            const diffHour = Math.floor(diffMin / 60);
            if (diffHour < 24) {
                return diffHour === 1 ? '1 hour ago' : `${diffHour} hours ago`;
            }
            const diffDay = Math.floor(diffHour / 24);
            return diffDay === 1 ? '1 day ago' : `${diffDay} days ago`;
        }

        function serializeCommentForKey(comment) {
            return JSON.stringify({
                name: comment.name,
                role: comment.role,
                text: comment.text,
                timestamp: comment.timestamp || null,
            });
        }

        function updateRelativeTimes() {
            document.querySelectorAll('.comment-time[data-timestamp]').forEach(span => {
                span.textContent = formatRelativeTime(span.dataset.timestamp);
            });
        }

        function refreshCommentTimestamps() {
            updateRelativeTimes();
            requestAnimationFrame(updateRelativeTimes);
        }

        setInterval(updateRelativeTimes, 5000);

        // Database-backed API functions for likes and comments
        async function getSavedComments(type, id) {
            try {
                const response = await fetch(`api_announcement_interactions.php?action=get-comments&type=${encodeURIComponent(type)}&id=${id}`);
                if (!response.ok) {
                    return [];
                }
                const data = await response.json();
                return data.success ? (data.comments || []) : [];
            } catch (error) {
                console.error('Error fetching comments:', error);
                return [];
            }
        }

        async function getSavedLike(type, id) {
            try {
                const response = await fetch(`api_announcement_interactions.php?action=get-like-status&type=${encodeURIComponent(type)}&id=${id}`);
                if (!response.ok) {
                    return { hasLiked: false, likeCount: 0 };
                }
                const data = await response.json();
                return data.success ? data : { hasLiked: false, likeCount: 0 };
            } catch (error) {
                console.error('Error fetching like status:', error);
                return { hasLiked: false, likeCount: 0 };
            }
        }

        async function saveLike(type, id, liked) {
            try {
                const formData = new FormData();
                formData.append('type', type);
                formData.append('id', id);
                
                const response = await fetch(`api_announcement_interactions.php?action=toggle-like`, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    console.error('Error toggling like');
                    return { success: false, likeCount: 0 };
                }
                const data = await response.json();
                return data;
            } catch (error) {
                console.error('Error saving like:', error);
                return { success: false, likeCount: 0 };
            }
        }

        async function addComment(type, id, text) {
            try {
                const formData = new FormData();
                formData.append('type', type);
                formData.append('id', id);
                formData.append('text', text);
                
                const response = await fetch(`api_announcement_interactions.php?action=add-comment`, {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    console.error('Error adding comment');
                    return { success: false };
                }
                const data = await response.json();
                return data;
            } catch (error) {
                console.error('Error adding comment:', error);
                return { success: false };
            }
        }

        function initializeSavedInteractions() {
            document.querySelectorAll('.announcement-card').forEach(async card => {
                const type = card.dataset.type;
                const id = card.dataset.id;
                if (!type || !id) {
                    return;
                }

                const likeButton = card.querySelector('.like-button');
                const heartCount = likeButton ? likeButton.querySelector('.heart-count') : null;
                
                if (likeButton && heartCount) {
                    const likeData = await getSavedLike(type, id);
                    if (likeData.hasLiked) {
                        likeButton.classList.add('liked');
                        likeButton.querySelector('i')?.classList.replace('fa-regular', 'fa-solid');
                    } else {
                        likeButton.classList.remove('liked');
                        likeButton.querySelector('i')?.classList.replace('fa-solid', 'fa-regular');
                    }
                    heartCount.textContent = likeData.likeCount || 0;
                }

                const previewButton = card.querySelector('.view-all-comments');
                const previewList = card.querySelector('.comment-preview-list');
                if (previewButton && previewList) {
                    const allComments = await getSavedComments(type, id);
                    const count = allComments.length;
                    previewButton.dataset.comments = JSON.stringify(allComments);
                    previewButton.dataset.count = count;
                    previewButton.textContent = `View all ${count} comments`;

                    let previewHtml = '';
                    if (count === 0) {
                        previewHtml = '<div class="comment-preview no-comments">No comments yet. Be the first to reply.</div>';
                    } else {
                        allComments.slice(0, 2).forEach(comment => {
                            if (!comment.timestamp && comment.time === 'Just now') {
                                comment.timestamp = Date.now();
                            }
                            const previewTimestamp = comment.timestamp || null;
                            const previewTimeLabel = previewTimestamp ? formatRelativeTime(previewTimestamp) : (comment.time || 'Just now');
                            previewHtml += `<div class="comment-preview ${comment.role !== 'student' ? 'comment-verified' : ''}">`;
                            previewHtml += `<div class="comment-meta">`;
                            previewHtml += `<span class="comment-author">${escapeHtml(comment.name)}</span>`;
                            previewHtml += `<span class="comment-meta-actions">`;
                            previewHtml += `<span class="comment-time" ${previewTimestamp ? `data-timestamp="${escapeHtml(previewTimestamp)}"` : ''}>${escapeHtml(previewTimeLabel)}</span>`;
                            if (comment.role !== 'student') {
                                previewHtml += '<span class="preview-badge">Verified Answer</span>';
                            }
                            previewHtml += `</span>`;
                            previewHtml += `</div>`;
                            previewHtml += `<p class="comment-text">${escapeHtml(comment.text)}</p>`;
                            previewHtml += '</div>';
                        });
                    }
                    previewList.innerHTML = previewHtml;
                    updateRelativeTimes();
                }
            });
        }

        function updatePreviewButtonCount(button, count) {
            if (!button) {
                return;
            }
            button.dataset.count = count;
            button.textContent = `View all ${count} comments`;
        }

        async function deleteComment(type, id, commentId) {
            try {
                const formData = new FormData();
                formData.append('type', type);
                formData.append('id', id);
                formData.append('comment_id', commentId);

                const response = await fetch(`api_announcement_interactions.php?action=delete-comment`, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    return { success: false, error: 'Unable to delete comment' };
                }

                return await response.json();
            } catch (error) {
                console.error('Error deleting comment:', error);
                return { success: false, error: 'Unable to delete comment' };
            }
        }

        async function openCommentDrawer(title, comments, context, isModal = false) {
            const overlay = document.getElementById('commentDrawerOverlay');
            const drawer = document.getElementById('commentDrawer');
            const drawerTitle = document.getElementById('commentDrawerTitle');
            const drawerSubtitle = document.getElementById('commentDrawerSubtitle');
            const pinnedContainer = document.getElementById('drawerPinnedComments');
            const threadContainer = document.getElementById('commentThread');
            const input = document.getElementById('commentDrawerInput');

            currentCommentContext = context;
            const allComments = context ? await getSavedComments(context.type, context.id) : [];
            const combinedComments = allComments;

            drawerTitle.textContent = title;
            drawerSubtitle.textContent = `Showing ${combinedComments.length} replies`;
            pinnedContainer.innerHTML = '';
            threadContainer.innerHTML = '';

            const pinnedComments = combinedComments.filter(comment => comment.role !== 'student');
            const threadComments = combinedComments;

            if (pinnedComments.length === 0) {
                pinnedContainer.innerHTML = '<div class="comment-item no-comments">No pinned answers yet.</div>';
            } else {
                pinnedComments.forEach(comment => {
                    pinnedContainer.innerHTML += `
                        <div class="comment-item pinned-answer">
                            <div class="comment-meta">
                                <span>${escapeHtml(comment.name || 'Anonymous')}</span>
                                <span class="comment-course">${escapeHtml(comment.course || '')}</span>
                                <span>${escapeHtml(comment.time || formatRelativeTime(comment.timestamp))}</span>
                            </div>
                            <p class="comment-text">${escapeHtml(comment.text)}</p>
                        </div>
                    `;
                });
            }

            if (threadComments.length === 0) {
                threadContainer.innerHTML = '<div class="comment-item no-comments">No comments yet. Start the conversation.</div>';
            } else {
                threadComments.forEach(comment => {
                    const commentTimestamp = comment.timestamp || null;
                    const timeLabel = commentTimestamp ? formatRelativeTime(commentTimestamp) : (comment.time || 'Just now');
                    const commentKey = serializeCommentForKey(comment);
                    threadContainer.innerHTML += `
                        <div class="comment-item" data-comment-id="${escapeHtml(comment.id)}" data-comment-key="${escapeHtml(commentKey)}" data-comment-role="${escapeHtml(comment.role || 'student')}" ${commentTimestamp ? `data-comment-timestamp="${escapeHtml(commentTimestamp)}"` : ''}>
                            <div class="comment-meta">
                                <span class="comment-author">${escapeHtml(comment.name || 'Anonymous')}</span>
                                <span class="comment-course">${escapeHtml(comment.course || '')}</span>
                                <span class="comment-meta-actions">
                                    <span class="comment-time" ${commentTimestamp ? `data-timestamp="${escapeHtml(commentTimestamp)}"` : ''}>${escapeHtml(timeLabel)}</span>
                                    <button type="button" class="comment-delete-button" aria-label="Delete comment"><i class="fa-solid fa-trash"></i></button>
                                </span>
                            </div>
                            <p class="comment-text">${escapeHtml(comment.text)}</p>
                            <button type="button" class="reply-button">Reply</button>
                        </div>
                    `;
                });
            }

            if (isModal) {
                drawer.classList.add('modal-embedded');
                const modalContent = document.querySelector('.image-modal-content');
                modalContent.classList.add('with-drawer');
                modalContent.appendChild(drawer);
                overlay.style.display = 'none'; // Hide overlay in modal mode
            } else {
                drawer.classList.remove('modal-embedded');
                overlay.classList.add('open');
            }

            drawer.classList.add('open');
            drawer.setAttribute('aria-hidden', 'false');
            input.value = '';
            input.focus();
            updateRelativeTimes();
        }

        function closeCommentDrawer() {
            const overlay = document.getElementById('commentDrawerOverlay');
            const drawer = document.getElementById('commentDrawer');
            const modalContent = document.querySelector('.image-modal-content');
            if (drawer.classList.contains('modal-embedded')) {
                document.body.appendChild(drawer);
                modalContent.classList.remove('with-drawer');
            }
            overlay.classList.remove('open');
            drawer.classList.remove('open', 'modal-embedded');
            drawer.setAttribute('aria-hidden', 'true');
            overlay.style.display = ''; // Reset display
        }

        function createHeartBurst(button) {
            const bubble = document.createElement('span');
            bubble.className = 'heart-bubble';
            bubble.textContent = '+1';
            button.appendChild(bubble);
            setTimeout(() => {
                button.removeChild(bubble);
            }, 450);
        }

        function setupAnnouncementInteractions() {
            document.querySelectorAll('.like-button').forEach(button => {
                button.addEventListener('click', async function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    this.disabled = true;
                    const card = this.closest('.announcement-card');
                    const type = card?.dataset.type || '';
                    const id = card?.dataset.id || '';
                    
                    const result = await saveLike(type, id, true);
                    if (result.success) {
                        const heartCount = this.querySelector('.heart-count');
                        heartCount.textContent = result.likeCount || 0;
                        this.classList.toggle('liked');
                        if (this.classList.contains('liked')) {
                            this.querySelector('i').classList.replace('fa-regular', 'fa-solid');
                            createHeartBurst(this);
                        } else {
                            this.querySelector('i').classList.replace('fa-solid', 'fa-regular');
                        }
                    }
                    
                    this.disabled = false;
                });
            });

            document.querySelectorAll('.view-all-comments, .comment-button').forEach(trigger => {
                trigger.addEventListener('click', async function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    let commentsData = [];
                    let title = 'Comments';
                    let context = null;
                    const card = this.closest('.announcement-card');
                    const type = card ? card.dataset.type : null;
                    const id = card ? card.dataset.id : null;
                    const previewButton = card ? card.querySelector('.view-all-comments') : null;

                    if (this.classList.contains('comment-button') && card) {
                        title = card.querySelector('.announcement-title')?.textContent.trim() || title;
                    }

                    if (type && id) {
                        context = { type, id, previewButton };
                    }

                    await openCommentDrawer(title, commentsData, context);
                });
            });

            const commentDrawerClose = document.getElementById('commentDrawerClose');
            if (commentDrawerClose) {
                commentDrawerClose.addEventListener('click', function (e) {
                    e.preventDefault();
                    closeCommentDrawer();
                });
            }

            document.querySelectorAll('.announcement-image-wrapper').forEach(wrapper => {
                wrapper.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    handleAnnouncementImageClick(this);
                });
            });

            document.addEventListener('click', async function (event) {
                const replyButton = event.target.closest('.reply-button');
                if (replyButton) {
                    event.preventDefault();
                    const commentItem = replyButton.closest('.comment-item');
                    const nameSpan = commentItem.querySelector('.comment-author');
                    if (!nameSpan) return;
                    const name = nameSpan.textContent.trim();
                    const input = document.getElementById('commentDrawerInput');
                    input.value = `@${name} `;
                    input.focus();
                    input.scrollIntoView({ behavior: 'smooth' });
                    return;
                }

                const deleteButton = event.target.closest('.comment-delete-button');
                if (deleteButton) {
                    event.preventDefault();
                    const commentItem = deleteButton.closest('.comment-item');
                    const commentId = commentItem?.dataset.commentId;
                    if (!commentId) {
                        alert('Unable to remove comment.');
                        return;
                    }
                    if (!currentCommentContext || !currentCommentContext.type || !currentCommentContext.id) {
                        alert('Unable to remove comment: context missing');
                        return;
                    }

                    deleteButton.disabled = true;
                    const deleteResult = await deleteComment(currentCommentContext.type, currentCommentContext.id, commentId);
                    deleteButton.disabled = false;

                    if (deleteResult.success) {
                        commentItem.remove();
                        const thread = document.getElementById('commentThread');
                        const remainingComments = thread.querySelectorAll('.comment-item');
                        const drawerSubtitle = document.getElementById('commentDrawerSubtitle');
                        if (drawerSubtitle) {
                            drawerSubtitle.textContent = `Showing ${remainingComments.length} replies`;
                        }
                        if (currentCommentContext.previewButton) {
                            updatePreviewButtonCount(currentCommentContext.previewButton, remainingComments.length);
                        }
                        if (remainingComments.length === 0) {
                            thread.innerHTML = '<div class="comment-item no-comments">No comments yet. Start the conversation.</div>';
                        }
                    } else {
                        alert(deleteResult.error || 'Unable to delete comment.');
                    }
                    return;
                }
            });

            const sendButton = document.getElementById('commentDrawerSend');
            const commentInput = document.getElementById('commentDrawerInput');
            sendButton.addEventListener('click', async function () {
                const text = commentInput.value.trim();
                if (!text) {
                    return;
                }
                
                if (!currentCommentContext || !currentCommentContext.type || !currentCommentContext.id) {
                    alert('Unable to post comment: context missing');
                    return;
                }
                
                this.classList.add('loading');
                this.disabled = true;
                this.innerHTML = '<i class="fa-solid fa-spinner"></i>';

                const result = await addComment(currentCommentContext.type, currentCommentContext.id, text);
                if (result.success && result.comment) {
                    const thread = document.getElementById('commentThread');
                    const newComment = result.comment;
                    const commentTimestamp = newComment.timestamp || Date.now();
                    const timeLabel = formatRelativeTime(commentTimestamp);
                    const commentKey = serializeCommentForKey(newComment);

                    const noComments = thread.querySelector('.no-comments');
                    if (noComments) {
                        noComments.remove();
                    }

                    const commentHtml = `
                        <div class="comment-item" data-comment-id="${escapeHtml(newComment.id)}" data-comment-key="${escapeHtml(commentKey)}" data-comment-role="student" data-comment-timestamp="${escapeHtml(commentTimestamp)}">
                            <div class="comment-meta">
                                <span class="comment-author">${escapeHtml(newComment.name || 'You')}</span>
                                <span class="comment-course">${escapeHtml(newComment.course || '')}</span>
                                <span class="comment-meta-actions">
                                    <span class="comment-time" data-timestamp="${escapeHtml(commentTimestamp)}">${escapeHtml(timeLabel)}</span>
                                    <button type="button" class="comment-delete-button" aria-label="Delete comment"><i class="fa-solid fa-trash"></i></button>
                                </span>
                            </div>
                            <p class="comment-text">${escapeHtml(newComment.text)}</p>
                            <button type="button" class="reply-button">Reply</button>
                        </div>
                    `;

                    thread.insertAdjacentHTML('beforeend', commentHtml);
                    const newCount = thread.querySelectorAll('.comment-item').length;
                    const drawerSubtitle = document.getElementById('commentDrawerSubtitle');
                    if (drawerSubtitle) {
                        drawerSubtitle.textContent = `Showing ${newCount} replies`;
                    }
                    if (currentCommentContext.previewButton) {
                        updatePreviewButtonCount(currentCommentContext.previewButton, newCount);
                    }
                } else {
                    alert('Error posting comment. Please try again.');
                }

                commentInput.value = '';
                this.classList.remove('loading');
                this.disabled = false;
                this.innerHTML = '<i class="fa-solid fa-paper-plane"></i>';
                const thread = document.getElementById('commentThread');
                thread.scrollTop = thread.scrollHeight;
                updateRelativeTimes();
            });
        }

        // Initialize interactions immediately since DOM is already ready (script is at bottom of page)
        setupAnnouncementInteractions();
        initializeSavedInteractions();
        refreshCommentTimestamps();

        // Mobile menu toggle functionality
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
    </script>
    <?php include '../AI CHAT BOT/chat_widget.php'; ?>
</body>
</html>
