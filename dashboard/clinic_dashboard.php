<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/clinic_functions.php';
require_login();
$user = current_user();
if (!$user) {
    header('Location: ../auth/student_login.php');
    exit;
}

$service = strtolower(trim($_GET['service'] ?? ''));
// Treat teacher head for SSC/Scholarship as combined mode when no explicit service provided
$isHeadSscScholarship = $user['role'] === 'teacher' && (($user['head_service'] ?? '') === 'ssc_scholarship');
if ($service === '') {
    if ($isHeadSscScholarship) {
        $service = 'ssc/scholarship';
    } elseif (basename($_SERVER['PHP_SELF']) === 'clinic_dashboard.php') {
        $service = 'clinic';
    }
}
$isClinicService = $service === 'clinic';
$isGuidanceService = $service === 'guidance';
// Combined SSC/Scholarship support
$isSscScholarshipService = $service === 'ssc/scholarship' || ($service === 'ssc' && $isHeadSscScholarship);
$sscScholarshipServiceParam = $isSscScholarshipService ? 'ssc/scholarship' : 'ssc';
$scholarshipServiceParam = $isSscScholarshipService ? 'ssc/scholarship' : 'scholarship';
$isLibraryService = $service === 'library';

if ($user['role'] === 'teacher' && $user['head_service'] === 'clinic' && !$isClinicService && !$isGuidanceService && !$isLibraryService) {
    header('Location: nurse_home.php');
    exit;
}
if (!in_array($user['role'], ['student', 'teacher'], true)) {
    header('Location: ../auth/student_login.php');
    exit;
}

// Initialize clinic schema
ensure_clinic_schema();

// Get clinic data
$clinicStatus = get_clinic_status();
$userHealthRecord = get_user_health_record($user['id']);
$todayHealthData = get_today_health_data($user['id']);
$userClearanceRequests = get_user_clearance_requests($user['id']);
$activeAnnouncements = get_active_clinic_announcements($user['role'] === 'teacher' ? 'teachers' : 'students');
$medicines = get_clinic_medicines();
$medicineCategories = get_medicine_categories();
$pendingClearanceRequests = get_pending_clearance_requests();
$recentClinicVisits = get_today_clinic_visits();
$recentCancelledRequests = get_recent_cancelled_clearance_requests();
$clinicRecentVisits = get_recent_clinic_visits(50);

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

function calculate_daily_health_assessment($data) {
    $assessment = [
        'bmi' => null,
        'bmiStatus' => null,
        'sleepQuality' => null,
        'hydrationStatus' => null,
        'overallHealthStatus' => 'No data',
        'overallHealthNote' => 'Add your weight, height, sleep, and water intake to see a health summary.'
    ];

    if (!empty($data['weight_kg']) && !empty($data['height_cm']) && floatval($data['height_cm']) > 0) {
        $heightInMeters = floatval($data['height_cm']) / 100;
        $bmi = round(floatval($data['weight_kg']) / ($heightInMeters * $heightInMeters), 1);
        $assessment['bmi'] = $bmi;

        if ($bmi < 18.5) {
            $assessment['bmiStatus'] = 'Underweight';
        } elseif ($bmi >= 18.5 && $bmi < 25) {
            $assessment['bmiStatus'] = 'Normal';
        } elseif ($bmi >= 25 && $bmi < 30) {
            $assessment['bmiStatus'] = 'Overweight';
        } else {
            $assessment['bmiStatus'] = 'Obese';
        }
    }

    if (!empty($data['sleep_hours'])) {
        $sleepHours = floatval($data['sleep_hours']);
        if ($sleepHours >= 7 && $sleepHours <= 9) {
            $assessment['sleepQuality'] = 'Good';
        } elseif ($sleepHours >= 5 && $sleepHours < 7) {
            $assessment['sleepQuality'] = 'Fair';
        } else {
            $assessment['sleepQuality'] = 'Poor';
        }
    }

    if (!empty($data['water_intake_liters'])) {
        $waterIntake = floatval($data['water_intake_liters']);
        if ($waterIntake >= 2.5) {
            $assessment['hydrationStatus'] = 'Good';
        } elseif ($waterIntake >= 1.8) {
            $assessment['hydrationStatus'] = 'Fair';
        } else {
            $assessment['hydrationStatus'] = 'Low';
        }
    }

    $hasAnyMetric = $assessment['bmiStatus'] !== null || $assessment['sleepQuality'] !== null || $assessment['hydrationStatus'] !== null;
    if (!$hasAnyMetric) {
        return $assessment;
    }

    $issues = 0;
    if ($assessment['bmiStatus'] !== null && $assessment['bmiStatus'] !== 'Normal') {
        $issues += 1;
    }
    if ($assessment['sleepQuality'] === 'Poor') {
        $issues += 1;
    } elseif ($assessment['sleepQuality'] === 'Fair') {
        $issues += 0.5;
    }
    if ($assessment['hydrationStatus'] === 'Low') {
        $issues += 1;
    } elseif ($assessment['hydrationStatus'] === 'Fair') {
        $issues += 0.5;
    }

    if ($issues === 0) {
        $assessment['overallHealthStatus'] = 'Healthy';
        $assessment['overallHealthNote'] = 'Great job keeping your habits balanced.';
    } elseif ($issues <= 1) {
        $assessment['overallHealthStatus'] = 'Fair';
        $assessment['overallHealthNote'] = 'A few habits can be improved for better wellness.';
    } else {
        $assessment['overallHealthStatus'] = 'Needs Attention';
        $assessment['overallHealthNote'] = 'Consider improving your sleep, hydration, or body metrics.';
    }

    return $assessment;
}

// Handle form submissions
$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_health_record'])) {
        $data = [
            'medical_history' => $_POST['medical_history'] ?? null,
            'allergies' => $_POST['allergies'] ?? null,
            'current_medications' => $_POST['current_medications'] ?? null,
            'blood_type' => $_POST['blood_type'] ?? null,
            'emergency_contact_name' => $_POST['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $_POST['emergency_contact_phone'] ?? null,
            'emergency_contact_relationship' => $_POST['emergency_contact_relationship'] ?? null
        ];

        if (save_health_record($user['id'], $data)) {
            $message = 'Health record saved successfully!';
            $userHealthRecord = get_user_health_record($user['id']);
        } else {
            $message = 'Failed to save health record.';
            $messageType = 'error';
        }
    }

        if (isset($_POST['save_daily_health'])) {
        $data = [
            'sleep_hours' => !empty($_POST['sleep_hours']) ? (float)$_POST['sleep_hours'] : null,
            'water_intake_liters' => !empty($_POST['water_intake_liters']) ? (float)$_POST['water_intake_liters'] : null,
            'height_cm' => !empty($_POST['height_cm']) ? (float)$_POST['height_cm'] : null,
            'weight_kg' => !empty($_POST['weight_kg']) ? (float)$_POST['weight_kg'] : null
        ];

        if (save_daily_health_data($user['id'], $data)) {
            $message = 'Daily health data saved successfully!';
            $todayHealthData = get_today_health_data($user['id']);
        } else {
            $message = 'Failed to save daily health data.';
            $messageType = 'error';
        }
    }

    if (isset($_POST['submit_clearance_request'])) {
        // Check if user already has a pending clearance request
        $hasPendingRequest = false;
        foreach ($userClearanceRequests as $request) {
            if ($request['status'] === 'pending') {
                $hasPendingRequest = true;
                break;
            }
        }

        if ($hasPendingRequest) {
            $message = 'You already have a pending clearance request. Please wait for it to be processed before submitting a new one.';
            $messageType = 'error';
        } else {
            $data = [
                'request_type' => $_POST['request_type'],
                'purpose' => $_POST['purpose'],
                'documents_uploaded' => $_POST['documents_uploaded'] ?? null
            ];

            if (submit_clearance_request($user['id'], $data)) {
                $message = 'Clearance request submitted successfully!';
                $userClearanceRequests = get_user_clearance_requests($user['id']);
            } else {
                $message = 'Failed to submit clearance request.';
                $messageType = 'error';
            }
        }
    }

    if (isset($_POST['cancel_clearance_request'])) {
        $requestId = (int)$_POST['request_id'];
        
        // Check if the request belongs to the current user and is still pending
        $request = get_clearance_request_by_id($requestId);
        if ($request && $request['user_id'] == $user['id'] && $request['status'] === 'pending') {
            if (cancel_clearance_request($requestId)) {
                $message = 'Clearance request cancelled successfully!';
                $userClearanceRequests = get_user_clearance_requests($user['id']);
            } else {
                $message = 'Failed to cancel clearance request.';
                $messageType = 'error';
            }
        } else {
            $message = 'Cannot cancel this request. It may have already been processed.';
            $messageType = 'error';
        }
    }
}

$healthAssessment = calculate_daily_health_assessment($todayHealthData);
$bmi = $healthAssessment['bmi'];
$bmiStatus = $healthAssessment['bmiStatus'];
$sleepQuality = $healthAssessment['sleepQuality'];
$hydrationStatus = $healthAssessment['hydrationStatus'];
$overallHealthStatus = $healthAssessment['overallHealthStatus'];
$overallHealthNote = $healthAssessment['overallHealthNote'];

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
    <title>Clinic Services | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <meta name="theme-color" content="#800000">
    <style>
        :root {
            --clinic-maroon: #800000;
            --clinic-gold: #D4AF37;
            --clinic-rose: #FFB6C1;
        }

        .clinic-header {
            background: linear-gradient(135deg, var(--clinic-maroon), var(--clinic-gold));
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(128, 0, 0, 0.2);
        }

        .clinic-tabs {
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

        .clinic-content {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--clinic-maroon);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--clinic-gold);
        }

        .btn-clinic {
            background: linear-gradient(135deg, var(--clinic-maroon), var(--clinic-gold));
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-clinic:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(128, 0, 0, 0.3);
        }

        .status-card {
            background: linear-gradient(135deg, var(--clinic-rose), #ff69b4);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            box-shadow: 0 4px 15px rgba(255, 182, 193, 0.3);
            text-align: center;
        }

        .status-card h3,
        .status-card p {
            margin-left: auto;
            margin-right: auto;
        }

        .health-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .metric-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid var(--clinic-gold);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--clinic-maroon);
        }



        .rating-stars {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .star {
            font-size: 1.5rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .star.active {
            color: var(--clinic-gold);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Overview Cards */
        .overview-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .overview-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-bottom: 4px solid var(--clinic-gold);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 180px;
        }

        .overview-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        .overview-card-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .overview-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #E0B250, #B07A2B);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .overview-info h4 {
            margin: 0 0 0.5rem 0;
            color: var(--clinic-maroon);
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .overview-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--clinic-maroon);
        }

        .overview-change {
            font-size: 0.8rem;
            color: #28a745;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        /* Main Layout - Equal Sized Cards 50% / 50% */
        .clinic-main-layout {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 2rem;
            align-items: stretch;
            width: 100%;
        }

        .clinic-left-column,
        .clinic-right-column {
            display: flex;
            flex-direction: column;
            width: 100%;
            min-width: 0;
        }

        .health-card,
        .daily-health-card {
            width: 100%;
            height: 100%;
        }

        /* Health Card - Medical ID (Left) */
        .health-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            display: flex;
            flex-direction: column;
            min-height: 450px;
            width: 100%;
        }

        .health-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: var(--clinic-gold);
        }

        .health-card-header {
            background: var(--clinic-maroon);
            color: white;
            padding: 1.25rem;
            border-radius: 12px 12px 0 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-family: 'Playfair Display', serif;
        }

        .health-card-header i {
            font-size: 1.4rem;
            color: white;
        }

        .health-card-header h3 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
            color: white;
            font-family: 'Playfair Display', serif;
            letter-spacing: 0.5px;
        }

        .health-card-content {
            padding: 1.5rem;
            background: white;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .health-info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.85rem 0;
            border-bottom: 1px solid #e8e8e8;
        }

        .health-info-item:last-of-type {
            border-bottom: none;
        }

        .health-info-item label {
            font-weight: 600;
            color: #333;
            flex: 0 0 45%;
            font-size: 0.95rem;
        }

        .health-info-item span {
            text-align: right;
            flex: 0 0 55%;
            word-wrap: break-word;
            color: #555;
            font-size: 0.9rem;
        }

        .health-card-footer {
            text-align: center;
            padding: 1rem;
            background: #FDD7E4;
            border-radius: 0 0 12px 12px;
            color: var(--clinic-maroon);
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .health-card-footer:hover {
            background: #FCC5D8;
        }

        .health-card-footer i {
            font-size: 1rem;
        }

        /* Daily Health Card - Vitals Tracker (Right) */
        .daily-health-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            display: flex;
            flex-direction: column;
            min-height: 450px;
            width: 100%;
        }

        .daily-health-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: var(--clinic-gold);
        }

        .daily-health-header {
            background: var(--clinic-maroon);
            color: white;
            padding: 1.25rem;
            border-radius: 12px 12px 0 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-family: 'Playfair Display', serif;
        }

        .daily-health-header i {
            font-size: 1.4rem;
            color: white;
        }

        .daily-health-header h3 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
            color: white;
            font-family: 'Playfair Display', serif;
            letter-spacing: 0.5px;
        }

        .daily-health-content {
            padding: 1.5rem;
            background: white;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .daily-health-info {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .daily-info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.85rem 0;
            border-bottom: 1px solid #e8e8e8;
        }

        .daily-info-item:last-of-type {
            border-bottom: none;
        }

        .daily-info-item label {
            font-weight: 600;
            color: #333;
            flex: 0 0 45%;
            font-size: 0.95rem;
        }

        .daily-info-item span {
            text-align: right;
            flex: 0 0 55%;
            color: #555;
            font-size: 0.9rem;
        }

        .bmi-status, .sleep-status {
            font-size: 0.8rem;
            font-weight: 500;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            margin-left: 0.5rem;
        }

        .status-good, .status-normal, .status-healthy {
            background: #d4edda;
            color: #155724;
        }

        .status-fair {
            background: #fff3cd;
            color: #856404;
        }

        .status-poor, .status-underweight, .status-overweight, .status-obese, .status-low, .status-needs-attention {
            background: #f8d7da;
            color: #721c24;
        }

        .daily-health-footer {
            text-align: center;
            padding: 1rem;
            background: #FDD7E4;
            border-radius: 0 0 12px 12px;
            color: var(--clinic-maroon);
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .daily-health-footer:hover {
            background: #FCC5D8;
        }

        .daily-health-footer i {
            font-size: 1rem;
        }

        @media (max-width: 1200px) {
            .clinic-main-layout {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .health-card,
            .daily-health-card {
                min-height: auto;
            }
        }

        /* Health Advisories */
        .no-announcements {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .no-announcements i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .announcement-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
            margin-top: 1.5rem;
        }

        @media (max-width: 980px) {
            .announcement-grid {
                grid-template-columns: 1fr;
            }
        }

        .announcement-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            border-bottom: 4px solid #800000;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            min-height: 100%;
        }

        .announcement-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .announcement-card.clinic {
            border-bottom-color: #800000;
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .announcement-header h4 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
        }

        .announcement-date {
            font-size: 0.85rem;
            color: #666;
            white-space: nowrap;
        }

        .announcement-content {
            color: #333;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .announcement-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            margin-top: auto;
            width: fit-content;
        }

        .announcement-badge.clinic {
            background: #fff0f0;
            color: #800000;
        }

        .announcement-identity {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .announcement-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #f8fafc;
            display: grid;
            place-items: center;
            color: #800000;
            font-size: 1.1rem;
        }

        .announcement-author-block {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .announcement-author {
            font-weight: 700;
            color: #111827;
        }

        .announcement-source-meta {
            display: flex;
            gap: 12px;
            font-size: 0.85rem;
            color: #6b7280;
            flex-wrap: wrap;
        }

        .announcement-source-meta span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .announcement-actions {
            display: flex;
            gap: 10px;
            margin: 16px 0 8px;
            flex-wrap: wrap;
        }

        .announcement-actions button,
        .view-all-comments {
            flex: 1;
            min-width: 140px;
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

        .view-all-comments {
            border: none;
            background: transparent;
            color: #0066cc;
            margin-bottom: 12px;
            text-align: left;
        }

        .comment-preview-list {
            display: grid;
            gap: 10px;
            margin-bottom: 0;
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

        .comment-drawer-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            z-index: 50;
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
            z-index: 60;
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

        .heart-bubble {
            position: absolute;
            font-size: 14px;
            font-weight: bold;
            color: #e74c3c;
            pointer-events: none;
            animation: heart-pop 0.45s forwards;
        }

        @keyframes heart-pop {
            0% { 
                opacity: 1; 
                transform: translate(0, 0) scale(1);
            }
            100% { 
                opacity: 0; 
                transform: translate(0, -30px) scale(1.5);
            }
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

        .announcement-meta-item.event-type-badge {
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 999px;
            padding: 4px 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
        }

        .priority-high {
            background: #ffebee;
            color: #c62828;
        }

        .priority-medium {
            background: #fff3e0;
            color: #ef6c00;
        }

        .priority-low {
            background: #e8f5e8;
            color: #2e7d32;
        }

        /* Old duplicate layout styles removed; main grid styles are defined above */

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            background: linear-gradient(135deg, var(--clinic-maroon), var(--clinic-gold));
            color: white;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Specific modal header gradient overrides for Health Card and Daily Health modals */
        #healthModal .modal-header,
        #dailyHealthModal .modal-header {
            background: linear-gradient(135deg, #E0B250, #B07A2B);
            color: white;
        }

        .modal-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-close {
            font-size: 2rem;
            cursor: pointer;
            transition: opacity 0.3s ease;
        }

        .modal-close:hover {
            opacity: 0.7;
        }

        .modal-body {
            padding: 1.5rem;
            max-height: 60vh;
            overflow-y: auto;
        }

        .modal-form .form-group {
            margin-bottom: 1rem;
        }

        .modal-form .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--clinic-maroon);
        }

        .modal-form .form-group input,
        .modal-form .form-group select,
        .modal-form .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .modal-form .form-group input:focus,
        .modal-form .form-group select:focus,
        .modal-form .form-group textarea:focus {
            outline: none;
            border-color: var(--clinic-gold);
        }

        .modal-form h4 {
            color: var(--clinic-maroon);
            margin: 1.5rem 0 1rem 0;
            font-size: 1.1rem;
        }

        .modal-footer {
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 0 0 15px 15px;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }

        .btn-secondary:hover {
            background: #5a6268;
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
    </style>
</head>
<body>
    <!-- Swipe-to-Refresh Spinner (Mobile Only) -->
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); z-index: 9999; justify-content: center; align-items: center;">
        <div style="text-align: center;">
            <div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div>
            <p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0;">Refreshing...</p>
        </div>
    </div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-mobile-header">
                <div class="mobile-user-info">
                    <h4><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p><?= htmlspecialchars($topbarInfo['displayMeta']) ?></p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar" <?= $isClinicService ? 'onclick="toggleGuidanceSidebar()"' : '' ?>>
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
                        <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
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
                        <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Manage">
                            <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                            <span class="nav-text">Manage</span>
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
                            <a href="library_dashboard.php" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php" class="active" data-tooltip="Clinic">
                                <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                                <span class="nav-text">Clinic</span>
                            </a>
                            <a href="ssc/ssc_dashboard.php" data-tooltip="SSC">
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
                    <a href="about.php" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php elseif ($isSscScholarshipService): ?>
                    <a href="ssc_head_home.php" data-tooltip="Dashboard">
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
                            <a href="guidance_dashboard.php?service=ssc/scholarship" data-tooltip="Guidance">
                                <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                                <span class="nav-text">Guidance</span>
                            </a>
                            <a href="library_dashboard.php?service=ssc/scholarship" data-tooltip="Library">
                                <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                                <span class="nav-text">Library</span>
                            </a>
                            <a href="clinic_dashboard.php?service=ssc/scholarship" class="active" data-tooltip="Clinic">
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
                    <a href="profile.php" data-tooltip="Profile">
                        <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                        <span class="nav-text">Profile</span>
                    </a>
                    <a href="about.php" data-tooltip="About">
                        <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                        <span class="nav-text">About</span>
                    </a>
                <?php else: ?>
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
                            <p>Clinic Services</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
                        <span class="user-meta"><?php echo htmlspecialchars($topbarInfo['displayMeta']); ?></span>
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
                            <a href="profile.php" class="menu-link profile-link"><?php echo htmlspecialchars($user['full_name']); ?></a>
                            <span class="menu-subtext">View your account details</span>
                        </div>
                        <a href="profile.php" class="menu-action">View profile</a>
                    </div>

                    <!-- support menu removed per request -->
                </div>
            </header>
            <div class="main-scroll">
        <!-- Header -->
        <div class="service-page-header">
            <div class="service-page-header-main">
                <div class="service-page-header-icon"><i class="fa-solid fa-stethoscope" aria-hidden="true"></i></div>
                <div>
                    <p class="service-page-header-eyebrow">Campus Portal</p>
                    <h1>Clinic Services</h1>
                    <p class="service-page-header-welcome">Welcome back, <strong><?= htmlspecialchars($user['full_name']) ?></strong></p>
                </div>
            </div>
            <div class="service-page-header-center">
                <i class="fa-solid fa-shield-heart" aria-hidden="true"></i>
                <div><strong>Health and wellness support</strong><span>Access care, track your health, and request clinic assistance.</span></div>
            </div>
            <div class="service-page-header-profile">
                <strong><?= htmlspecialchars($roleLabel) ?></strong>
                <div><?= htmlspecialchars($topbarInfo['displayMeta']) ?></div>
            </div>
        </div>

        <!-- Clinic Status -->
        <?php if (!$clinicStatus['is_available']): ?>
        <div class="status-card" style="background-color: #dc3545 !important; background: #dc3545 !important; border-color: #a71d2a !important; color: white !important;">
            <h3><i class="fas fa-exclamation-triangle"></i> Clinic Currently Unavailable</h3>
            <p><?php echo htmlspecialchars($clinicStatus['reason'] ?? 'The clinic is temporarily closed.'); ?></p>
            <?php if ($clinicStatus['duration_hours']): ?>
                <p><small>Expected to reopen in approximately <?php echo htmlspecialchars($clinicStatus['duration_hours']); ?> hours.</small></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Overview Cards -->
        <div class="overview-cards">
            <div class="overview-card">
                <div class="overview-card-content">
                    <div class="overview-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="overview-info">
                        <h4>Total Visits</h4>
                        <div class="overview-value">-- <span class="overview-change">-- this month</span></div>
                    </div>
                </div>
            </div>

            <div class="overview-card">
                <div class="overview-card-content">
                    <div class="overview-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="overview-info">
                        <h4>Last Visit</h4>
                        <div class="overview-value">--</div>
                    </div>
                </div>
            </div>

            <div class="overview-card">
                <div class="overview-card-content">
                    <div class="overview-icon">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                    <div class="overview-info">
                        <h4>Health Status</h4>
                        <div class="overview-value">--</div>
                    </div>
                </div>
            </div>

            <div class="overview-card">
                <div class="overview-card-content">
                    <div class="overview-icon">
                        <i class="fas fa-pills"></i>
                    </div>
                    <div class="overview-info">
                        <h4>Active Medications</h4>
                        <div class="overview-value">--</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Layout -->
        <div class="clinic-main-layout">
            <!-- Left Column - Health Card -->
            <div class="clinic-left-column">
                <div class="health-card" onclick="openHealthModal()">
                    <div class="health-card-header">
                        <i class="fas fa-id-card"></i>
                        <h3>Health Card</h3>
                    </div>
                    <div class="health-card-content">
                        <div class="health-info-item">
                            <label>Name:</label>
                            <span><?php echo htmlspecialchars($user['full_name']); ?></span>
                        </div>
                        <div class="health-info-item">
                            <label>Allergies:</label>
                            <span><?php echo htmlspecialchars($userHealthRecord['allergies'] ?? 'Not specified'); ?></span>
                        </div>
                        <div class="health-info-item">
                            <label>Blood Type:</label>
                            <span><?php echo htmlspecialchars($userHealthRecord['blood_type'] ?? 'Not specified'); ?></span>
                        </div>
                        <div class="health-info-item">
                            <label>Current Medications:</label>
                            <span><?php echo htmlspecialchars($userHealthRecord['current_medications'] ?? 'None'); ?></span>
                        </div>
                        <div class="health-info-item">
                            <label>Emergency Contact:</label>
                            <span><?php echo htmlspecialchars(($userHealthRecord['emergency_contact_name'] ?? 'Not specified') . ' (' . ($userHealthRecord['emergency_contact_phone'] ?? '') . ')'); ?></span>
                        </div>
                    </div>
                    <div class="health-card-footer">
                        <i class="fas fa-edit"></i> Click to edit
                    </div>
                </div>
            </div>

            <!-- Right Column - Daily Health Monitoring -->
            <div class="clinic-right-column">
                <div class="daily-health-card" onclick="openDailyHealthModal()">
                    <div class="daily-health-header">
                        <i class="fas fa-heartbeat"></i>
                        <h3>Daily Health Monitoring</h3>
                    </div>
                    <div class="daily-health-content">
                        <div class="daily-health-info">
                            <div class="daily-info-item">
                                <label>Weight:</label>
                                <span><?php echo htmlspecialchars($todayHealthData['weight_kg'] ?? '--'); ?> kg</span>
                            </div>
                            <div class="daily-info-item">
                                <label>Height:</label>
                                <span><?php echo htmlspecialchars($todayHealthData['height_cm'] ?? '--'); ?> cm</span>
                            </div>
                            <?php if ($bmi !== null): ?>
                            <div class="daily-info-item">
                                <label>BMI:</label>
                                <span><?php echo htmlspecialchars($bmi); ?> <small class="bmi-status status-<?php echo strtolower($bmiStatus); ?>">(<?php echo htmlspecialchars($bmiStatus); ?>)</small></span>
                            </div>
                            <div class="daily-info-item">
                                <label>Health Assessment:</label>
                                <span><?php echo htmlspecialchars($bmiStatus === 'Normal' ? 'Healthy' : $bmiStatus); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="daily-info-item">
                                <label>Sleep Hours:</label>
                                <span><?php echo htmlspecialchars($todayHealthData['sleep_hours'] ?? '--'); ?> hrs <?php if ($sleepQuality): ?><small class="sleep-status status-<?php echo strtolower($sleepQuality); ?>">(<?php echo htmlspecialchars($sleepQuality); ?>)</small><?php endif; ?></span>
                            </div>
                            <div class="daily-info-item">
                                <label>Water Intake:</label>
                                <span><?php echo htmlspecialchars($todayHealthData['water_intake_liters'] ?? '--'); ?> L <?php if ($hydrationStatus): ?><small class="sleep-status status-<?php echo strtolower($hydrationStatus); ?>">(<?php echo htmlspecialchars($hydrationStatus); ?>)</small><?php endif; ?></span>
                            </div>
                            <div class="daily-info-item">
                                <label>Overall:</label>
                                <span><?php echo htmlspecialchars($overallHealthStatus); ?><?php if ($overallHealthNote): ?><small class="bmi-status status-<?php echo strtolower(str_replace(' ', '-', $overallHealthStatus)); ?>">(<?php echo htmlspecialchars($overallHealthNote); ?>)</small><?php endif; ?></span>
                            </div>
                            <!-- Current Mood removed -->
                        </div>
                    </div>
                    <div class="daily-health-footer">
                        <i class="fas fa-edit"></i> Click to update daily health
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs - Below both Health Card and Daily Monitoring -->
        <div class="clinic-tabs">
            <button class="clinic-tab active" onclick="switchTab('health-advisories')">
                <i class="fas fa-bullhorn"></i> Health Advisories
            </button>
            <button class="clinic-tab" onclick="switchTab('medicines')">
                <i class="fas fa-pills"></i> Medicines
            </button>
        </div>

        <!-- Tab Contents -->
        <div id="health-advisories" class="tab-content active clinic-content">
            <h2><i class="fas fa-bullhorn"></i> Health Advisories</h2>
            <p>Important health announcements and advisories from the clinic staff.</p>

            <?php if (empty($activeAnnouncements)): ?>
            <div class="no-announcements">
                <i class="fas fa-info-circle"></i>
                <p>No active health advisories at this time.</p>
            </div>
            <?php else: ?>
            <div class="announcement-grid">
                <?php foreach ($activeAnnouncements as $announcement): ?>
                <div class="announcement-card clinic" data-type="clinic" data-id="<?= htmlspecialchars($announcement['id'] ?? '') ?>">
                    <div class="announcement-identity">
                        <div class="announcement-avatar"><i class="fa-solid fa-hospital-user"></i></div>
                        <div class="announcement-author-block">
                            <div class="announcement-author">School Clinic</div>
                            <div class="announcement-source-meta">
                                <span><i class="fa-solid fa-clock"></i> <?= htmlspecialchars(formatTimeAgo($announcement['created_at'])) ?></span>
                                <span><i class="fa-solid fa-globe"></i> Public</span>
                            </div>
                        </div>
                    </div>
                    <div class="announcement-header">
                        <h4 class="announcement-title"><?= htmlspecialchars($announcement['title']) ?></h4>
                        <span class="announcement-badge clinic">Clinic</span>
                    </div>
                    <div class="announcement-meta">
                        <span class="announcement-meta-item">
                            <i class="fa-solid fa-calendar-days"></i>
                            <?= htmlspecialchars(date('M j, Y', strtotime($announcement['created_at']))) ?>
                        </span>
                        <?php if (!empty($announcement['priority'])): ?>
                        <span class="announcement-meta-item priority-<?= htmlspecialchars(strtolower($announcement['priority'] ?? 'medium')) ?>">
                            Priority: <?= htmlspecialchars(ucfirst($announcement['priority'] ?? 'Medium')) ?>
                        </span>
                        <?php endif; ?>
                        <span class="announcement-meta-item event-type-badge">
                            <?= htmlspecialchars(ucfirst($announcement['category'] ?? 'Health')) ?>
                        </span>
                    </div>
                    <div class="announcement-content">
                        <p><?= nl2br(htmlspecialchars($announcement['description'] ?? $announcement['content'] ?? '')) ?></p>
                    </div>
                    <div class="announcement-actions">
                        <button type="button" class="like-button"><i class="fa-regular fa-heart"></i> Like <span class="heart-count">0</span></button>
                        <button type="button" class="comment-button"><i class="fa-regular fa-comment"></i> Comment</button>
                    </div>
                    <button type="button" class="view-all-comments" data-comments="[]">View all 0 comments</button>
                    <div class="comment-preview-list">
                        <div class="comment-preview no-comments">No comments yet. Be the first to reply.</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div id="medicines" class="tab-content clinic-content">
            <h2><i class="fas fa-pills"></i> Medicine Search & Information</h2>
            <p>Browse available medicines in the clinic inventory.</p>

            <div style="display: flex; gap: 20px; align-items: end; margin-bottom: 1rem;">
                <div class="form-group" style="flex: 1;">
                    <label for="medicine_search">Search Medicine</label>
                    <input type="text" id="medicine_search" placeholder="Enter medicine name..." onkeyup="searchMedicine()">
                </div>

                <div class="form-group" style="flex: 1;">
                    <label for="medicine_category">Filter by Category</label>
                    <select id="medicine_category" onchange="searchMedicine()">
                        <option value="">All Categories</option>
                        <?php foreach ($medicineCategories as $category): ?>
                        <option value="<?= htmlspecialchars(strtolower($category['name'])) ?>"><?= htmlspecialchars($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="medicine-results" style="margin-top: 2rem;">
                <?php if (!empty($medicines)): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                    <?php foreach ($medicines as $medicine): ?>
                    <div class="medicine-card" data-medicine-name="<?= htmlspecialchars(strtolower($medicine['name'])) ?>" data-medicine-category="<?= htmlspecialchars(strtolower($medicine['category'] ?? '')) ?>">
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #800000;">
                            <h4 style="margin: 0 0 8px 0; color: #800000; font-size: 18px;">
                                <i class="fas fa-pills"></i> <?= htmlspecialchars($medicine['name']) ?>
                            </h4>
                            <?php if ($medicine['generic_name']): ?>
                            <p style="margin: 0 0 10px 0; color: #666; font-size: 13px;">
                                <strong>Generic:</strong> <?= htmlspecialchars($medicine['generic_name']) ?>
                            </p>
                            <?php endif; ?>
                            
                            <div style="display: flex; gap: 15px; margin: 10px 0; font-size: 13px;">
                                <div>
                                    <strong style="color: #800000;">Stock:</strong>
                                    <span style="color: <?= $medicine['stock_quantity'] > $medicine['min_stock_level'] ? '#28a745' : '#dc3545' ?>;">
                                        <?= $medicine['stock_quantity'] ?> <?= htmlspecialchars($medicine['unit']) ?>
                                    </span>
                                </div>
                            </div>

                            <?php if ($medicine['category']): ?>
                            <p style="margin: 8px 0; font-size: 12px; color: #666;">
                                <strong>Category:</strong> <span style="background: #e9ecef; padding: 2px 8px; border-radius: 4px;">
                                    <?= htmlspecialchars($medicine['category']) ?>
                                </span>
                            </p>
                            <?php endif; ?>

                            <?php if ($medicine['description']): ?>
                            <p style="margin: 10px 0 0 0; font-size: 12px; color: #555; line-height: 1.4;">
                                <?= htmlspecialchars(substr($medicine['description'], 0, 100)) ?>
                                <?php if (strlen($medicine['description']) > 100): ?>...<?php endif; ?>
                            </p>
                            <?php endif; ?>

                            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e9ecef;">
                                <?php if ($medicine['stock_quantity'] <= 0): ?>
                                    <span style="color: #dc3545; font-weight: bold;">
                                        <i class="fas fa-times-circle"></i> Out of Stock
                                    </span>
                                <?php elseif ($medicine['stock_quantity'] <= $medicine['min_stock_level']): ?>
                                    <span style="color: #ffc107; font-weight: bold;">
                                        <i class="fas fa-exclamation-triangle"></i> Low Stock
                                    </span>
                                <?php else: ?>
                                    <span style="color: #28a745; font-weight: bold;">
                                        <i class="fas fa-check-circle"></i> Available
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 8px; color: #666;">
                    <i class="fas fa-pills" style="font-size: 48px; margin-bottom: 15px; color: #ccc;"></i>
                    <h3>No medicines available</h3>
                    <p>The clinic inventory is currently empty. Please contact the clinic for more information.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rate Our Service Button Section -->
        <div class="clinic-content" style="margin-top: 40px; padding-top: 30px; border-top: 2px solid #e2e8f0; text-align: center;">
            <div class="section-header">
                <h2><i class="fas fa-star"></i> Share Your Experience</h2>
            </div>
            <p style="color: #666; margin-bottom: 30px;">Your feedback helps us improve our clinic services</p>
            <button id="rate-service-btn" class="btn-clinic" style="font-size: 16px; padding: 12px 40px;">
                <i class="fas fa-star"></i> Rate Our Service
            </button>
        </div>

        </div>

        <!-- Health Card Edit Modal -->
        <div id="healthModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-edit"></i> Edit Health Information</h3>
                    <span class="modal-close" onclick="closeHealthModal()">&times;</span>
                </div>
                <form method="POST" class="modal-form">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="modal_medical_history">Medical History</label>
                            <textarea id="modal_medical_history" name="medical_history" rows="3" placeholder="List any past medical conditions, surgeries, or significant health events..."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="modal_allergies">Allergies</label>
                            <textarea id="modal_allergies" name="allergies" rows="2" placeholder="List any known allergies..."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="modal_current_medications">Current Medications</label>
                            <textarea id="modal_current_medications" name="current_medications" rows="2" placeholder="List any medications you are currently taking..."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="modal_blood_type">Blood Type</label>
                            <select id="modal_blood_type" name="blood_type">
                                <option value="">Select Blood Type</option>
                                <option value="A+">A+</option>
                                <option value="A-">A-</option>
                                <option value="B+">B+</option>
                                <option value="B-">B-</option>
                                <option value="AB+">AB+</option>
                                <option value="AB-">AB-</option>
                                <option value="O+">O+</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>

                        <h4>Emergency Contact</h4>
                        <div class="form-group">
                            <label for="modal_emergency_contact_name">Contact Name</label>
                            <input type="text" id="modal_emergency_contact_name" name="emergency_contact_name" placeholder="Full name of emergency contact">
                        </div>

                        <div class="form-group">
                            <label for="modal_emergency_contact_phone">Contact Phone</label>
                            <input type="tel" id="modal_emergency_contact_phone" name="emergency_contact_phone" placeholder="Phone number">
                        </div>

                        <div class="form-group">
                            <label for="modal_emergency_contact_relationship">Relationship</label>
                            <input type="text" id="modal_emergency_contact_relationship" name="emergency_contact_relationship" placeholder="e.g., Parent, Spouse, Sibling">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeHealthModal()">Cancel</button>
                        <button type="submit" name="save_health_record" class="btn-clinic">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Feedback Modal -->
        <div id="feedbackModal" class="modal">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h3><i class="fas fa-star"></i> Clinic Service Feedback</h3>
                    <span class="modal-close" onclick="closeFeedbackModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="feedback-message" style="display: none; padding: 12px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: 500;"></div>
                    <form id="feedbackForm">
                        <div class="form-group">
                            <label>Overall Rating: *</label>
                            <div class="rating-stars" style="display: flex; gap: 10px; justify-content: center;">
                                <input type="radio" id="mstar5" name="rating" value="5" style="display: none;"><label for="mstar5" style="color: #d1d5db; font-size: 2rem; cursor: pointer; margin: 0; transition: color 0.2s;">★</label>
                                <input type="radio" id="mstar4" name="rating" value="4" style="display: none;"><label for="mstar4" style="color: #d1d5db; font-size: 2rem; cursor: pointer; margin: 0; transition: color 0.2s;">★</label>
                                <input type="radio" id="mstar3" name="rating" value="3" style="display: none;"><label for="mstar3" style="color: #d1d5db; font-size: 2rem; cursor: pointer; margin: 0; transition: color 0.2s;">★</label>
                                <input type="radio" id="mstar2" name="rating" value="2" style="display: none;"><label for="mstar2" style="color: #d1d5db; font-size: 2rem; cursor: pointer; margin: 0; transition: color 0.2s;">★</label>
                                <input type="radio" id="mstar1" name="rating" value="1" style="display: none;"><label for="mstar1" style="color: #d1d5db; font-size: 2rem; cursor: pointer; margin: 0; transition: color 0.2s;">★</label>
                            </div>
                            <p id="rating-text" style="text-align: center; color: #6b7280; margin-top: 10px; font-size: 14px;">Please select a rating</p>
                        </div>
                        <div class="form-group">
                            <label for="modal_comments">Your Feedback: *</label>
                            <textarea id="modal_comments" name="comments" rows="5" placeholder="Share your experience with our clinic service..." required></textarea>
                        </div>
                        <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                            <button type="button" class="btn-secondary" onclick="closeFeedbackModal()" style="padding: 0.75rem 2rem;">Cancel</button>
                            <button type="submit" class="btn-clinic" style="padding: 0.75rem 2rem;"><i class="fas fa-paper-plane"></i> Submit Feedback</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Daily Health Modal -->
        <div id="dailyHealthModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-heartbeat"></i> Update Daily Health</h3>
                    <span class="modal-close" onclick="closeDailyHealthModal()">&times;</span>
                </div>
                <form method="POST" class="modal-form">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="modal_height_unit">Height</label>
                            <select id="modal_height_unit" class="height-unit-select">
                                <option value="cm">Centimeters (cm)</option>
                                <option value="m">Meters (m)</option>
                                <option value="ft-in">Feet / Inches</option>
                            </select>
                        </div>

                        <div class="form-group height-input-group" id="height-cm-group">
                            <label for="modal_height_value">Height (cm)</label>
                            <input type="number" id="modal_height_value" step="0.1" min="50" max="250" placeholder="170.5">
                        </div>

                        <div class="form-group height-input-group" id="height-m-group" style="display:none;">
                            <label for="modal_height_value_m">Height (m)</label>
                            <input type="number" id="modal_height_value_m" step="0.01" min="0.5" max="2.5" placeholder="1.70">
                        </div>

                        <div class="form-group height-input-group" id="height-ft-in-group" style="display:none; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; display: grid;">
                            <div>
                                <label for="modal_height_value_ft">Height (ft)</label>
                                <input type="number" id="modal_height_value_ft" step="1" min="1" max="10" placeholder="5">
                            </div>
                            <div>
                                <label for="modal_height_value_in">Height (in)</label>
                                <input type="number" id="modal_height_value_in" step="0.1" min="0" max="11.9" placeholder="7.5">
                            </div>
                        </div>

                        <input type="hidden" id="modal_height_cm_hidden" name="height_cm" value="<?php echo htmlspecialchars($todayHealthData['height_cm'] ?? ''); ?>">

                        <div class="form-group">
                            <label for="modal_weight_kg">Weight (kg)</label>
                            <input type="number" id="modal_weight_kg" name="weight_kg" step="0.1" min="20" max="300" value="<?php echo htmlspecialchars($todayHealthData['weight_kg'] ?? ''); ?>" placeholder="65.5">
                        </div>

                        <div class="form-group">
                            <label for="modal_sleep_hours">Sleep Hours</label>
                            <input type="number" id="modal_sleep_hours" name="sleep_hours" step="0.5" min="0" max="24" value="<?php echo htmlspecialchars($todayHealthData['sleep_hours'] ?? ''); ?>" placeholder="8.0">
                        </div>

                        <div class="form-group">
                            <label for="modal_water_intake_liters">Water Intake (L)</label>
                            <input type="number" id="modal_water_intake_liters" name="water_intake_liters" step="0.1" min="0" max="10" value="<?php echo htmlspecialchars($todayHealthData['water_intake_liters'] ?? ''); ?>" placeholder="2.5">
                        </div>

                        <!-- Mood input removed from Daily Health Modal -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" onclick="closeDailyHealthModal()">Cancel</button>
                        <button type="submit" name="save_daily_health" class="btn-clinic">Save Daily Health</button>
                    </div>
                </form>
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

    <div id="commentDrawerOverlay" class="comment-drawer-overlay" aria-hidden="true"></div>
    <div id="commentDrawer" class="comment-drawer" aria-hidden="true">
        <div class="comment-drawer-header">
            <div>
                <h3 id="commentDrawerTitle">Comments</h3>
                <p id="commentDrawerSubtitle">Showing 0 replies</p>
            </div>
            <button type="button" id="commentDrawerClose" class="comment-drawer-close" aria-label="Close comments">&times;</button>
        </div>
        <div class="comment-drawer-body">
            <div class="comment-drawer-section">
                <h4>Pinned Answers</h4>
                <div id="drawerPinnedComments" class="drawer-pinned-comments">
                    <div class="comment-item no-comments">No pinned answers yet.</div>
                </div>
            </div>
            <div class="comment-drawer-section">
                <h4>Replies</h4>
                <div id="commentThread" class="comment-thread">
                    <div class="comment-item no-comments">No comments yet. Start the conversation.</div>
                </div>
            </div>
        </div>
        <div class="comment-drawer-footer">
            <input id="commentDrawerInput" type="text" placeholder="Write a reply..." aria-label="Write a comment">
            <button type="button" id="commentDrawerSend"><i class="fa-solid fa-paper-plane"></i></button>
        </div>
    </div>

    <script>
        // Global functions available immediately
        function openHealthModal() {
            document.getElementById('healthModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeHealthModal() {
            document.getElementById('healthModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function openDailyHealthModal() {
            document.getElementById('dailyHealthModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
            syncHeightFields();
        }

        function closeDailyHealthModal() {
            document.getElementById('dailyHealthModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function convertHeightToCm() {
            const unit = document.getElementById('modal_height_unit').value;
            const hiddenInput = document.getElementById('modal_height_cm_hidden');
            let heightCm = null;

            if (unit === 'cm') {
                const value = parseFloat(document.getElementById('modal_height_value').value);
                heightCm = Number.isFinite(value) ? value : null;
            } else if (unit === 'm') {
                const value = parseFloat(document.getElementById('modal_height_value_m').value);
                heightCm = Number.isFinite(value) ? value * 100 : null;
            } else if (unit === 'ft-in') {
                const feet = parseFloat(document.getElementById('modal_height_value_ft').value);
                const inches = parseFloat(document.getElementById('modal_height_value_in').value);
                if (Number.isFinite(feet) && Number.isFinite(inches)) {
                    heightCm = (feet * 12 + inches) * 2.54;
                }
            }

            if (heightCm !== null && Number.isFinite(heightCm)) {
                hiddenInput.value = heightCm.toFixed(1);
            }
        }

        function syncHeightFields() {
            const currentCm = parseFloat(document.getElementById('modal_height_cm_hidden').value);
            const unitSelect = document.getElementById('modal_height_unit');
            const unit = unitSelect.value;
            if (!Number.isFinite(currentCm)) {
                return;
            }

            if (unit === 'cm') {
                document.getElementById('modal_height_value').value = currentCm.toFixed(1);
            } else if (unit === 'm') {
                document.getElementById('modal_height_value_m').value = (currentCm / 100).toFixed(2);
            } else if (unit === 'ft-in') {
                const totalInches = currentCm / 2.54;
                const feet = Math.floor(totalInches / 12);
                const inches = totalInches - feet * 12;
                document.getElementById('modal_height_value_ft').value = feet;
                document.getElementById('modal_height_value_in').value = inches.toFixed(1);
            }
        }

        // Feedback Modal Functions
        const feedbackModal = document.getElementById('feedbackModal');
        const rateServiceBtn = document.getElementById('rate-service-btn');
        const feedbackForm = document.getElementById('feedbackForm');
        const feedbackMessage = document.getElementById('feedback-message');
        const mstars = document.querySelectorAll('input[name="rating"]');
        const ratingText = document.getElementById('rating-text');
        const ratingLabels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

        rateServiceBtn.addEventListener('click', function() {
            feedbackModal.style.display = 'flex';
            feedbackForm.reset();
            feedbackMessage.style.display = 'none';
            mstars.forEach(star => star.checked = false);
            ratingText.textContent = 'Please select a rating';
            updateStarColors();
        });

        function closeFeedbackModal() {
            feedbackModal.style.display = 'none';
        }

        feedbackModal.addEventListener('click', function(e) {
            if (e.target === feedbackModal) {
                closeFeedbackModal();
            }
        });

        // Star rating handlers
        mstars.forEach((star, index) => {
            star.addEventListener('change', function() {
                updateStarColors();
                ratingText.textContent = ratingLabels[this.value] + ' (' + this.value + '/5)';
            });

            // Hover effect
            document.querySelector('label[for="mstar' + (5 - index) + '"]').addEventListener('mouseover', function() {
                const value = 5 - index;
                updateStarHover(value);
            });
        });

        feedbackModal.querySelector('.rating-stars').addEventListener('mouseout', function() {
            updateStarColors();
        });

        function updateStarColors() {
            const selectedValue = document.querySelector('input[name="rating"]:checked');
            const value = selectedValue ? parseInt(selectedValue.value) : 0;
            
            mstars.forEach((star, index) => {
                const starNum = 5 - index;
                const label = document.querySelector('label[for="mstar' + starNum + '"]');
                if (starNum <= value) {
                    label.style.color = '#fbbf24';
                } else {
                    label.style.color = '#d1d5db';
                }
            });
        }

        function updateStarHover(value) {
            mstars.forEach((star, index) => {
                const starNum = 5 - index;
                const label = document.querySelector('label[for="mstar' + starNum + '"]');
                if (starNum <= value) {
                    label.style.color = '#fbbf24';
                } else {
                    label.style.color = '#d1d5db';
                }
            });
        }

        // Form submission
        feedbackForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const rating = document.querySelector('input[name="rating"]:checked');
            if (!rating) {
                showFeedbackMessage('Please select a rating', 'error');
                return;
            }

            const comments = document.getElementById('modal_comments').value.trim();
            if (!comments) {
                showFeedbackMessage('Please enter your feedback', 'error');
                return;
            }

            try {
                const response = await fetch('/THESIS/SUPPORTSERVICESYSTEM/includes/api_clinic_feedback.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'save',
                        rating: parseInt(rating.value),
                        comments: comments
                    }),
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (result.success) {
                    showFeedbackMessage('Thank you! Your feedback has been submitted successfully.', 'success');
                    setTimeout(() => {
                        closeFeedbackModal();
                    }, 2000);
                } else {
                    showFeedbackMessage(result.message || 'Failed to submit feedback', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showFeedbackMessage('Error submitting feedback: ' + error.message, 'error');
            }
        });

        function showFeedbackMessage(message, type) {
            feedbackMessage.textContent = message;
            feedbackMessage.style.display = 'block';
            feedbackMessage.style.backgroundColor = type === 'success' ? '#d1fae5' : '#fee2e2';
            feedbackMessage.style.color = type === 'success' ? '#065f46' : '#991b1b';
            feedbackMessage.style.border = type === 'success' ? '1px solid #a7f3d0' : '1px solid #fecaca';
        }

        function switchTab(tabId) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));

            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.clinic-tab');
            tabs.forEach(tab => tab.classList.remove('active'));

            // Show selected tab content
            document.getElementById(tabId).classList.add('active');

            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        function searchMedicine() {
            const searchTerm = document.getElementById('medicine_search').value.toLowerCase().trim();
            const selectedCategory = document.getElementById('medicine_category').value.toLowerCase().trim();
            const medicineCards = document.querySelectorAll('.medicine-card');
            let visibleCount = 0;

            medicineCards.forEach(card => {
                const medicineName = card.getAttribute('data-medicine-name');
                const medicineCategory = card.getAttribute('data-medicine-category') || '';
                
                const matchesSearch = !searchTerm || medicineName.includes(searchTerm);
                const matchesCategory = !selectedCategory || medicineCategory.includes(selectedCategory);
                
                if (matchesSearch && matchesCategory) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Show "no results" message if nothing found
            if (visibleCount === 0 && (searchTerm || selectedCategory)) {
                const resultsDiv = document.getElementById('medicine-results');
                const noResultsMsg = resultsDiv.querySelector('.no-results-message');
                if (!noResultsMsg) {
                    const msg = document.createElement('div');
                    msg.className = 'no-results-message';
                    msg.style.cssText = 'text-align: center; padding: 40px; color: #666;';
                    const searchDesc = searchTerm ? `search: "${searchTerm}"` : '';
                    const categoryDesc = selectedCategory ? `category: "${selectedCategory}"` : '';
                    const filterDesc = [searchDesc, categoryDesc].filter(Boolean).join(', ');
                    msg.innerHTML = `
                        <i class="fas fa-search" style="font-size: 48px; margin-bottom: 15px; color: #ccc;"></i>
                        <h3>No medicines found</h3>
                        <p>No medicines match your filters: <strong>${filterDesc}</strong></p>
                    `;
                    resultsDiv.appendChild(msg);
                }
            } else {
                const noResultsMsg = document.querySelector('.no-results-message');
                if (noResultsMsg) {
                    noResultsMsg.remove();
                }
            }
        }

        // DOM-dependent code runs after page loads
        document.addEventListener('DOMContentLoaded', function() {
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

            // Clinic health form initialization
            const heightUnitSelect = document.getElementById('modal_height_unit');
            const heightCmHidden = document.getElementById('modal_height_cm_hidden');
            const heightCmGroup = document.getElementById('height-cm-group');
            const heightMGroup = document.getElementById('height-m-group');
            const heightFtInGroup = document.getElementById('height-ft-in-group');

            const updateHeightControls = () => {
                const unit = heightUnitSelect.value;
                heightCmGroup.style.display = unit === 'cm' ? 'block' : 'none';
                heightMGroup.style.display = unit === 'm' ? 'block' : 'none';
                heightFtInGroup.style.display = unit === 'ft-in' ? 'grid' : 'none';
                syncHeightFields();
            };

            if (heightUnitSelect) {
                heightUnitSelect.addEventListener('change', updateHeightControls);
            }

            const dailyHealthForm = document.querySelector('#dailyHealthModal form');
            if (dailyHealthForm) {
                dailyHealthForm.addEventListener('submit', function(event) {
                    convertHeightToCm();
                });
            }

            // Star rating functionality
            const stars = document.querySelectorAll('.star');
            const ratingInput = document.getElementById('rating');

            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = this.dataset.rating;

                    // Remove active class from all stars
                    stars.forEach(s => s.classList.remove('active'));

                    // Add active class to clicked star and previous stars
                    for (let i = 0; i < rating; i++) {
                        stars[i].classList.add('active');
                    }

                    // Set hidden input value
                    ratingInput.value = rating;
                });
            });

            // Set minimum date for visit scheduling
            const today = new Date().toISOString().split('T')[0];
            const visitDateInput = document.getElementById('visit_date');
            if (visitDateInput) {
                visitDateInput.setAttribute('min', today);
            }

            // Close modal when clicking outside
            window.onclick = function(event) {
                const healthModal = document.getElementById('healthModal');
                const dailyHealthModal = document.getElementById('dailyHealthModal');
                if (event.target === healthModal) {
                    closeHealthModal();
                }
                if (event.target === dailyHealthModal) {
                    closeDailyHealthModal();
                }
            }

            // Close modal on Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeHealthModal();
                    closeDailyHealthModal();
                }
            });

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

        // Handle search button click
        const searchBtn = document.querySelector('.nav-search-btn');
        if (searchBtn) {
            searchBtn.addEventListener('click', function() {
                alert('Search functionality coming soon!');
            });
        }
    </script>

    <script>
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

        function commentsAreEquivalent(a, b) {
            if (!a || !b) {
                return false;
            }
            const sameName = String(a.name || '').trim() === String(b.name || '').trim();
            const sameRole = String(a.role || '').trim() === String(b.role || '').trim();
            const sameText = String(a.text || '').trim() === String(b.text || '').trim();
            if (!sameName || !sameRole || !sameText) {
                return false;
            }
            if (a.timestamp != null && b.timestamp != null) {
                return Number(a.timestamp) === Number(b.timestamp);
            }
            return true;
        }

        const announcementApiUrl = 'api_announcement_interactions.php';
        let currentCommentContext = null;

        function buildCommentAuthor(comment) {
            if (!comment.course) {
                return comment.name || 'Anonymous';
            }
            return `${comment.name} • ${comment.course}`;
        }

        function escapeHtmlForAttr(value) {
            return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        async function apiPost(action, body) {
            const response = await fetch(`${announcementApiUrl}?action=${action}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams(body)
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Announcement API error');
            }
            return data;
        }

        async function fetchAnnouncementLikeStatus(type, id) {
            const response = await fetch(`${announcementApiUrl}?action=get-like-status&type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`);
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Unable to load like status');
            }
            return { hasLiked: !!data.hasLiked, likeCount: Number(data.likeCount || 0) };
        }

        async function fetchAnnouncementComments(type, id) {
            const response = await fetch(`${announcementApiUrl}?action=get-comments&type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`);
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || 'Unable to load comments');
            }
            return Array.isArray(data.comments) ? data.comments : [];
        }

        function renderLikeButton(button, hasLiked, likeCount) {
            if (!button) return;
            const heartIcon = button.querySelector('i');
            const heartCount = button.querySelector('.heart-count');
            if (hasLiked) {
                button.classList.add('liked');
                heartIcon?.classList.remove('fa-regular');
                heartIcon?.classList.add('fa-solid');
            } else {
                button.classList.remove('liked');
                heartIcon?.classList.remove('fa-solid');
                heartIcon?.classList.add('fa-regular');
            }
            if (heartCount) {
                heartCount.textContent = String(likeCount);
            }
        }

        function updateRelativeTimes() {
            document.querySelectorAll('.comment-time[data-timestamp]').forEach(span => {
                span.textContent = formatRelativeTime(Number(span.dataset.timestamp));
            });
        }

        function refreshCommentTimestamps() {
            updateRelativeTimes();
            requestAnimationFrame(updateRelativeTimes);
        }

        function setupAnnouncementInteractions() {
            initializeSavedInteractions();
        }

        function renderCommentPreview(card, comments) {
            if (!card) return;
            const previewList = card.querySelector('.comment-preview-list');
            const previewButton = card.querySelector('.view-all-comments');
            if (!previewList || !previewButton) return;

            const count = comments.length;
            previewButton.dataset.count = String(count);
            previewButton.textContent = `View all ${count} comments`;
            previewButton.dataset.title = card.querySelector('.announcement-title')?.textContent || '';

            if (count === 0) {
                previewList.innerHTML = '<div class="comment-preview no-comments">No comments yet. Be the first to reply.</div>';
                return;
            }

            const previewComments = comments.slice(0, 2);
            previewList.innerHTML = previewComments.map(comment => {
                const author = buildCommentAuthor(comment);
                const time = comment.timestamp ? formatRelativeTime(comment.timestamp) : 'Just now';
                return `
                    <div class="comment-preview">
                        <div class="comment-meta">
                            <span class="comment-author">${escapeHtml(author)}</span>
                            <span class="comment-time">${escapeHtml(time)}</span>
                        </div>
                        <p class="comment-text">${escapeHtml(comment.text)}</p>
                    </div>
                `;
            }).join('');
        }

        async function loadCardInteractionState(card) {
            const type = card.dataset.type;
            const id = card.dataset.id;
            if (!type || !id) return;
            try {
                const [status, comments] = await Promise.all([
                    fetchAnnouncementLikeStatus(type, id),
                    fetchAnnouncementComments(type, id)
                ]);
                const likeButton = card.querySelector('.like-button');
                renderLikeButton(likeButton, status.hasLiked, status.likeCount);
                renderCommentPreview(card, comments);
            } catch (error) {
                console.error('Announcement interaction load failed:', error);
            }
        }

        function initializeSavedInteractions() {
            document.querySelectorAll('.announcement-card').forEach(card => {
                const type = card.dataset.type;
                const id = card.dataset.id;
                if (!type || !id) return;
                loadCardInteractionState(card);

                const likeButton = card.querySelector('.like-button');
                if (likeButton) {
                    likeButton.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        toggleLike(type, id, likeButton);
                    });
                }

                const title = card.querySelector('.announcement-title')?.textContent || 'Comments';
                const commentButton = card.querySelector('.comment-button');
                const viewAllBtn = card.querySelector('.view-all-comments');
                if (commentButton) {
                    commentButton.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        openCommentDrawer(type, id, title);
                    });
                }
                if (viewAllBtn) {
                    viewAllBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        openCommentDrawer(type, id, title);
                    });
                }
            });
        }

        async function toggleLike(type, id, button) {
            if (!type || !id || !button) return;
            try {
                const result = await apiPost('toggle-like', { type, id });
                if (result.action === 'added') {
                    createHeartBurst(button);
                }
                renderLikeButton(button, result.action === 'added', Number(result.likeCount || 0));
            } catch (error) {
                console.error('Unable to toggle like:', error);
            }
        }

        function renderCommentThread(comments) {
            const threadContainer = document.getElementById('commentThread');
            if (!threadContainer) return;
            threadContainer.innerHTML = comments.length ? comments.map(comment => {
                const deleteButton = comment.canDelete ? `<button type="button" class="comment-delete-button" aria-label="Delete comment"><i class="fa-solid fa-trash"></i></button>` : '';
                return `
                    <div class="comment-item" data-comment-id="${escapeHtml(String(comment.id))}">
                        <div class="comment-meta">
                            <span class="comment-author">${escapeHtml(buildCommentAuthor(comment))}</span>
                            <span class="comment-meta-actions">
                                <span class="comment-time">${escapeHtml(formatRelativeTime(comment.timestamp))}</span>
                                ${deleteButton}
                            </span>
                        </div>
                        <p class="comment-text">${escapeHtml(comment.text)}</p>
                    </div>
                `;
            }).join('') : '<div class="comment-item no-comments">No comments yet. Start the conversation.</div>';
        }

        function updateCommentDrawerTitle(count) {
            const drawerSubtitle = document.getElementById('commentDrawerSubtitle');
            if (drawerSubtitle) {
                drawerSubtitle.textContent = `Showing ${count} replies`;
            }
        }

        async function openCommentDrawer(type, id, title) {
            if (!type || !id) return;
            currentCommentContext = { type, id };
            const overlay = document.getElementById('commentDrawerOverlay');
            const drawer = document.getElementById('commentDrawer');
            const drawerTitle = document.getElementById('commentDrawerTitle');
            const commentInput = document.getElementById('commentDrawerInput');
            const pinnedContainer = document.getElementById('drawerPinnedComments');

            drawerTitle.textContent = title || 'Comments';
            drawer.dataset.type = type;
            drawer.dataset.id = id;

            try {
                const comments = await fetchAnnouncementComments(type, id);
                renderCommentThread(comments);
                updateCommentDrawerTitle(comments.length);
                const card = document.querySelector(`.announcement-card[data-type="${type}"][data-id="${id}"]`);
                renderCommentPreview(card, comments);
            } catch (error) {
                console.error('Unable to load comments:', error);
                renderCommentThread([]);
                updateCommentDrawerTitle(0);
            }

            if (pinnedContainer) {
                pinnedContainer.innerHTML = '';
            }
            if (overlay) {
                overlay.classList.add('open');
            }
            if (drawer) {
                drawer.classList.add('open');
                drawer.setAttribute('aria-hidden', 'false');
            }
            if (commentInput) {
                commentInput.value = '';
                commentInput.focus();
            }
        }

        function closeCommentDrawer() {
            const overlay = document.getElementById('commentDrawerOverlay');
            const drawer = document.getElementById('commentDrawer');
            if (overlay) {
                overlay.classList.remove('open');
            }
            if (drawer) {
                drawer.classList.remove('open');
                drawer.setAttribute('aria-hidden', 'true');
            }
        }

        function createHeartBurst(button) {
            const bubble = document.createElement('span');
            bubble.className = 'heart-bubble';
            bubble.textContent = '+1';
            button.appendChild(bubble);
            setTimeout(() => {
                if (bubble.parentNode === button) {
                    button.removeChild(bubble);
                }
            }, 450);
        }

        async function submitComment() {
            const drawer = document.getElementById('commentDrawer');
            const type = drawer.dataset.type;
            const id = drawer.dataset.id;
            const commentInput = document.getElementById('commentDrawerInput');
            const text = commentInput.value.trim();

            if (!type || !id || !text) return;

            try {
                await apiPost('add-comment', { type, id, text });
                const comments = await fetchAnnouncementComments(type, id);
                renderCommentThread(comments);
                updateCommentDrawerTitle(comments.length);
                const card = document.querySelector(`.announcement-card[data-type="${type}"][data-id="${id}"]`);
                renderCommentPreview(card, comments);
                commentInput.value = '';
            } catch (error) {
                console.error('Unable to save comment:', error);
            }
        }

        async function deleteComment(commentId) {
            if (!currentCommentContext || !commentId) return;
            const { type, id } = currentCommentContext;
            try {
                await apiPost('delete-comment', { type, id, comment_id: commentId });
                const comments = await fetchAnnouncementComments(type, id);
                renderCommentThread(comments);
                updateCommentDrawerTitle(comments.length);
                const card = document.querySelector(`.announcement-card[data-type="${type}"][data-id="${id}"]`);
                renderCommentPreview(card, comments);
            } catch (error) {
                console.error('Unable to delete comment:', error);
            }
        }

        document.addEventListener('click', function (event) {
            const deleteButton = event.target.closest('.comment-delete-button');
            if (!deleteButton) {
                return;
            }
            event.preventDefault();
            const commentItem = deleteButton.closest('.comment-item');
            const commentId = commentItem?.dataset.commentId;
            if (commentId) {
                deleteComment(commentId);
            }
        });

        document.addEventListener('click', function (event) {
            const replyButton = event.target.closest('.reply-button');
            if (replyButton) {
                event.preventDefault();
                const commentItem = replyButton.closest('.comment-item');
                const nameSpan = commentItem.querySelector('.comment-author');
                if (!nameSpan) return;
                const name = nameSpan.textContent.trim();
                const input = document.getElementById('commentDrawerInput');
                if (input) {
                    input.value = `@${name} `;
                    input.focus();
                    input.scrollIntoView({ behavior: 'smooth' });
                }
                return;
            }
        });

        const sendButton = document.getElementById('commentDrawerSend');
        const commentInput = document.getElementById('commentDrawerInput');
        if (sendButton && commentInput) {
            sendButton.addEventListener('click', function () {
                const text = commentInput.value.trim();
                if (!text) {
                    return;
                }
                submitComment();
            });
        }

        const commentDrawerClose = document.getElementById('commentDrawerClose');
        const commentDrawerOverlay = document.getElementById('commentDrawerOverlay');
        if (commentDrawerClose) {
            commentDrawerClose.addEventListener('click', closeCommentDrawer);
        }
        if (commentDrawerOverlay) {
            commentDrawerOverlay.addEventListener('click', closeCommentDrawer);
        }

        setInterval(updateRelativeTimes, 5000);

        // Initialize interactions immediately since DOM is already ready (script is at bottom of page)
        initializeSavedInteractions();
        refreshCommentTimestamps();
    </script>
    <script src="../assets/js/app.js" defer></script>
    <?php include '../AI CHAT BOT/chat_widget.php'; ?>
</body>
</html>
