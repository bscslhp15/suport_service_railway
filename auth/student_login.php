<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

const STUDENT_EMAIL_VERIFICATION_ENABLED = false;

$message = '';
$messageClass = '';
$consentError = false;
$authMode = 'login';
$registerValues = [
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'full_name' => '',
    'email' => '',
    'student_id' => '',
    'course' => '',
    'ladderize_course' => '',
    'year' => '',
];

if (!empty($_GET['verified'])) {
    $message = 'Email verified successfully. You may now log in.';
    $messageClass = 'success';
}
if (!empty($_GET['mode']) && $_GET['mode'] === 'register') {
    $authMode = 'register';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    if ($action === 'register') {
        $authMode = 'register';
        $registerValues['first_name'] = secure_input($_POST['first_name'] ?? '');
        $registerValues['middle_name'] = secure_input($_POST['middle_name'] ?? '');
        $registerValues['last_name'] = secure_input($_POST['last_name'] ?? '');
        $registerValues['full_name'] = trim($registerValues['first_name'] . ' ' . ($registerValues['middle_name'] ? $registerValues['middle_name'] . ' ' : '') . $registerValues['last_name']);
        $registerValues['full_name'] = ucwords(mb_strtolower($registerValues['full_name']));
        $registerValues['email'] = secure_input($_POST['email'] ?? '');
        $registerValues['student_id'] = secure_input($_POST['student_id'] ?? '');
        $registerValues['course'] = secure_input($_POST['course'] ?? '');
        $registerValues['ladderize_course'] = secure_input($_POST['ladderize_course'] ?? '');
        $registerValues['year'] = secure_input($_POST['year'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $agreementAccepted = isset($_POST['agreement_consent']) && $_POST['agreement_consent'] === '1';
        $privacyAccepted = isset($_POST['privacy_consent']) && $_POST['privacy_consent'] === '1';
        $selectedCourse = $registerValues['ladderize_course'] ?: $registerValues['course'];
        $course_year = $selectedCourse && $registerValues['year'] ? trim($selectedCourse . ' ' . $registerValues['year']) : '';

        if (!$agreementAccepted || !$privacyAccepted) {
            $message = 'You must accept the Agreement and Privacy Policy before requesting an account.';
            $messageClass = 'error';
            $consentError = true;
        } elseif (!$registerValues['first_name'] || !$registerValues['last_name'] || !$registerValues['email'] || !$registerValues['student_id'] || !$registerValues['year'] || !$password || !$confirm_password) {
            $message = 'All fields are required.';
            $messageClass = 'error';
        } elseif ($registerValues['course'] && $registerValues['ladderize_course']) {
            $message = 'Please choose either Course or Ladderize Course, not both.';
            $messageClass = 'error';
        } elseif (!$registerValues['course'] && !$registerValues['ladderize_course']) {
            $message = 'Please choose a Course or a Ladderize Course.';
            $messageClass = 'error';
        } elseif (!filter_var($registerValues['email'], FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageClass = 'error';
        } elseif (!preg_match('/^[0-9]{2}-[0-9]{5}$/', $registerValues['student_id'])) {
            $message = 'Student ID must be in the format 22-12345.';
            $messageClass = 'error';
        } elseif (strlen($password) < 9) {
            $message = 'Password must be more than 8 characters.';
            $messageClass = 'error';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $message = 'Password must contain at least one uppercase letter.';
            $messageClass = 'error';
        } elseif (!preg_match('/[a-z]/', $password)) {
            $message = 'Password must contain at least one lowercase letter.';
            $messageClass = 'error';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $message = 'Password must contain at least one number.';
            $messageClass = 'error';
        } elseif ($password !== $confirm_password) {
            $message = 'Password and confirm password do not match.';
            $messageClass = 'error';
        } elseif (find_user_by_email($registerValues['email'])) {
            $message = 'That email is already registered.';
            $messageClass = 'error';
        } elseif (get_pending_registration($registerValues['email']) || (($pendingDbAccount = get_db_pending_registration_by_email($registerValues['email'])) && $pendingDbAccount['status'] !== 'rejected')) {
            $message = 'A verification email is already pending for this address.';
            $messageClass = 'error';
        } elseif (check_duplicate_full_name($registerValues['full_name'])) {
            $message = 'You cannot create an account with this name because someone with that name already exists in the system. Please visit the admin office to resolve this issue.';
            $messageClass = 'error';
        } else {
            $documentPaths = [];
            $uploadFields = [
                'document_front' => 'front',
                'document_back' => 'back',
            ];
            foreach ($uploadFields as $field => $label) {
                if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
                    $message = 'Front and back document uploads are required. Please attach both copies before requesting an account.';
                    $messageClass = 'error';
                    break;
                }
                if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
                    $message = 'There was an error uploading the ' . $label . ' document. Please try again.';
                    $messageClass = 'error';
                    break;
                }
                $savedPath = save_student_registration_document_upload($_FILES[$field]);
                if (!$savedPath) {
                    $message = 'Invalid document upload. Please use JPG, PNG, WEBP, or PDF files.';
                    $messageClass = 'error';
                    break;
                }
                $documentPaths[] = $savedPath;
            }

            if (!empty($message) && !empty($documentPaths)) {
                foreach ($documentPaths as $cleanupPath) {
                    $fullPath = __DIR__ . '/../' . $cleanupPath;
                    if (is_file($fullPath)) {
                        @unlink($fullPath);
                    }
                }
                $documentPaths = [];
            }

            if (empty($message)) {
                $verification_code = generate_code();
                $expires = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s');
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $pendingRegistration = [
                    'full_name' => $registerValues['full_name'],
                    'email' => $registerValues['email'],
                    'username' => null,
                    'role' => 'student',
                    'course_year' => $course_year,
                    'student_id' => $registerValues['student_id'],
                    'employee_id' => null,
                    'is_head' => 0,
                    'head_service' => 'none',
                    'email_verified' => 0,
                    'verification_code' => $verification_code,
                    'verification_expires' => $expires,
                    'password_hash' => $password_hash,
                    'documents' => $documentPaths,
                    'admin_type' => null,
                ];

                if (STUDENT_EMAIL_VERIFICATION_ENABLED) {
                    set_pending_registration($pendingRegistration);
                    header('Location: verify_email.php?email=' . urlencode($registerValues['email']) . '&role=student&send=1');
                } else {
                    $pendingRegistration['email_verified'] = 1;
                    $pendingRegistration['verification_code'] = null;
                    $pendingRegistration['verification_expires'] = null;
                    $pendingRegistration['status'] = 'pending_admin';
                    save_db_pending_registration($pendingRegistration);
                    header('Location: student_login.php?pending_approval=1');
                }
                exit;
            }
        }
    } else {
        $email = secure_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = find_user_by_email($email);
        if (!$user || $user['role'] !== 'student') {
            $message = 'Invalid student email or password.';
            $messageClass = 'error';
        } elseif (is_locked_out($user)) {
            $message = 'Account locked. Please try again later.';
            $messageClass = 'error';
        } elseif (!password_verify($password, $user['password_hash'])) {
            record_failed_login($user);
            $message = 'Invalid student email or password.';
            $messageClass = 'error';
        } elseif (!$user['email_verified']) {
            $message = 'Please verify your email before logging in.';
            $messageClass = 'error';
        } else {
            reset_failed_login($user);
            login_user($user);
            header('Location: ../dashboard/student_home.php');
            exit;
        }
    }
}

$courseOptions = [
    'Bachelor of Science in Accountancy',
    'Bachelor of Science in Business Administration',
    'Bachelor in Elementary Education',
    'Bachelor of Science in Computer Science',
    'Bachelor of Science in Criminology',
    'Bachelor of Science in Hospitality Management',
    'Bachelor of Science in Tourism Management',
];

$ladderizeOptions = [
    'Associate in Computer Technology',
    'Associate in Business Knowledge',
    'Associate in Hospitality Management',
    'Associate in Tourism Management',
];

$carouselImages = get_carousel_images('student');
if (empty($carouselImages)) {
    $carouselImages = [
        '../IMG ASSETS/SCHOOL IMG/pass15.jpg',
        '../IMG ASSETS/SCHOOL IMG/passp1.jpg',
        '../IMG ASSETS/SCHOOL IMG/passp2.jpg',
        '../IMG ASSETS/SCHOOL IMG/passp3.jpg',
        '../IMG ASSETS/SCHOOL IMG/passp4.jpg',
    ];
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Student Login | PASS College</title>
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

        :root {
            --deep-maroon: #800000;
            --soft-gold: #D4AF37;
            --muted-rose: rgba(224, 131, 137, 0.28);
            --text-light: #ffffff;
            --text-dark: #111827;
            --border-glow: rgba(255, 255, 255, 0.18);
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: var(--text-dark);
            overflow-x: hidden;
            background: #100000;
        }
        .auth-page {
            position: relative;
            min-height: 100vh;
            overflow: hidden;
        }
        .background-carousel,
        .background-tint {
            position: absolute;
            inset: 0;
            z-index: 0;
        }
        .background-carousel {
            overflow: hidden;
        }
        .background-slide {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center center;
            opacity: 0;
            transition: opacity 1.2s ease;
            will-change: opacity;
        }
        .background-slide.active {
            opacity: 1;
        }
        .background-tint {
            background: linear-gradient(180deg, rgba(128, 0, 0, 0.80) 0%, rgba(128, 0, 0, 0.38) 100%);
            backdrop-filter: none;
            pointer-events: none;
        }
        .auth-card {
            background: rgba(0, 0, 0, 0.08);
        }
        .auth-header {
            position: absolute;
            top: 24px;
            left: 24px;
            z-index: 20;
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 0;
            padding: 0;
            background: none;
            border: none;
            box-shadow: none;
        }
        .auth-header-link {
            display: flex;
            align-items: center;
            gap: 16px;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 8px 12px;
            border-radius: 8px;
            margin: -8px -12px;
        }
        .auth-header-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.02);
        }
        .auth-header img {
            width: 96px;
            height: 96px;
            object-fit: contain;
            border-radius: 0;
            background: none;
            padding: 0;
        }
        .auth-header-title {
            display: flex;
            flex-direction: column;
            line-height: 1.02;
            color: #fff;
        }
        .auth-header-title strong {
            font-size: 1.5rem;
            letter-spacing: 0.05em;
            font-family: 'ElephantLocal', 'Cormorant Garamond', serif !important;
            font-weight: 700;
        }
        .auth-header-title span {
            color: rgba(255, 255, 255, 0.88);
            font-size: 1.05rem;
        }
        .auth-card {
            position: relative;
            z-index: 10;
            width: min(1100px, calc(100vw - 40px));
            margin: 110px auto 18px;
            min-height: auto;
            border-radius: 28px;
            background: rgba(128, 0, 0, 0.08);
            border: 1px solid rgba(212, 175, 55, 0.14);
            box-shadow: 0 20px 48px rgba(0, 0, 0, 0.20);
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
            backdrop-filter: none;
            transition: transform 0.45s ease;
        }
        .auth-side {
            position: relative;
            padding: 8px 20px 16px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            transition: background 0.6s ease, color 0.6s ease;
        }
        .auth-side-left {
            background: rgba(128, 0, 0, 0.24);
            color: #fff;
        }
        .auth-side-right {
            background: rgba(128, 0, 0, 0.92);
            color: #fff;
        }
        .auth-card.register-mode .auth-side-left {
            background: rgba(128, 0, 0, 0.92);
            color: #fff;
        }
        .auth-card.register-mode .auth-side-right {
            background: rgba(128, 0, 0, 0.08);
            color: #fff;
        }
        .panel-content {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            min-height: auto;
            transition: opacity 0.6s ease, transform 0.6s ease;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(212, 175, 55, 0.35);
            border-radius: 999px;
            background: rgba(128, 0, 0, 0.96);
            color: #fff;
            padding: 12px 22px;
            font-size: 0.96rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
        }
        .btn-action:hover {
            background: rgba(128, 0, 0, 0.82);
            transform: translateY(-1px);
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.18);
        }
        .form-panel {
            display: grid;
            gap: 12px;
            width: 100%;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            padding: 18px 20px;
        }
        .register-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .register-grid .field-group.full-width,
        .register-grid .upload-grid,
        .register-grid .form-actions.full-width {
            grid-column: 1 / -1;
        }
        .field-note {
            display: block;
            width: 100%;
            margin-top: 8px;
            color: rgba(255,255,255,0.92);
        }
        .upload-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            width: 100%;
        }
        .form-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 14px;
            margin-top: 10px;
        }
        .form-section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
            letter-spacing: 0.01em;
        }
        .auth-footer {
            margin-top: 24px;
            display: grid;
            gap: 12px;
            color: rgba(255,255,255,0.92);
        }
        .auth-footer p {
            margin: 0;
        }
        .footer-links {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            color: rgba(255,255,255,0.9);
        }
        .footer-links a {
            color: rgba(255,255,255,0.95);
            text-decoration: none;
            font-weight: 600;
        }
        .footer-links a:hover {
            color: #D4AF37;
        }
        .panel {
            position: relative;
            width: 100%;
            transition: opacity 0.6s ease, transform 0.6s ease;
            margin-top: 0;
        }
        .panel-title {
            margin: 0 0 10px;
            font-size: clamp(1.85rem, 2.2vw, 2rem);
            line-height: 1.04;
            color: #fff;
        }
        .panel-brand-text {
            font-family: 'ElephantLocal', 'Cormorant Garamond', serif !important;
            font-weight: 700;
        }
        .panel-icon {
            margin-right: 10px;
            color: #D4AF37;
            font-size: 1.05em;
            vertical-align: middle;
        }
        .label-icon {
            margin-right: 8px;
            color: #D4AF37;
            font-size: 0.95rem;
            vertical-align: middle;
        }
        .input-icon-wrapper {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            color: #666;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            z-index: 10;
            border-radius: 4px;
        }

        .request-status-modal {
            background: rgba(16, 0, 0, .72) !important;
            backdrop-filter: blur(5px);
        }

        .request-status-card {
            position: relative;
            width: min(460px, calc(100% - 32px));
            max-height: calc(100vh - 32px);
            overflow-y: auto;
            background: #fffdf9;
            border: 1px solid rgba(128, 0, 0, .12);
            border-radius: 20px;
            padding: 26px;
            box-shadow: 0 24px 60px rgba(36, 0, 0, .28);
            z-index: 1000;
            color: #241817;
        }

        .request-status-heading {
            display: flex;
            align-items: center;
            gap: 13px;
            margin-bottom: 9px;
        }

        .request-status-heading-icon {
            display: grid;
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            place-items: center;
            border-radius: 13px;
            background: #f8e9d3;
            color: #800000;
            font-size: 19px;
        }

        .request-status-card h3 {
            margin: 0;
            color: #3b1d19;
            font-size: 21px;
        }

        .request-status-description {
            margin: 0 0 19px;
            color: #765d56;
            font-size: 14px;
            line-height: 1.5;
        }

        .request-status-fields {
            display: grid;
            gap: 11px;
            margin-bottom: 14px;
        }

        .request-status-fields input {
            width: 100%;
            min-height: 46px;
            padding: 11px 13px;
            border: 1px solid #e4d5ca;
            border-radius: 10px;
            background: #fff;
            color: #241817;
            font-size: 14px;
            outline: none;
            transition: border-color .2s ease, box-shadow .2s ease;
        }

        .request-status-fields input:focus {
            border-color: #b8871d;
            box-shadow: 0 0 0 3px rgba(184, 135, 29, .14);
        }

        .request-status-result {
            min-height: 40px;
            margin-bottom: 16px;
            color: #241817;
        }

        .request-status-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .request-status-footer button {
            min-height: 42px;
            padding: 10px 18px;
            border: 0;
            border-radius: 9px;
            font-weight: 700;
            cursor: pointer;
        }

        .request-status-close {
            background: #f1e9e3;
            color: #5c443b;
        }

        .request-status-check {
            background: #d4af37;
            color: #3f2700;
        }

        .request-status-check:hover {
            background: #c49e2b;
        }

        @media (max-width: 480px) {
            .request-status-card {
                padding: 21px;
            }

            .request-status-footer {
                flex-direction: column-reverse;
            }

            .request-status-footer button {
                width: 100%;
            }
        }
        .password-toggle:hover {
            color: #333;
            background: rgba(0, 0, 0, 0.05);
        }
        .panel-text {
            margin: 0 0 10px;
            color: rgba(255, 255, 255, 0.93);
            max-width: 520px;
            line-height: 1.6;
            font-size: 0.96rem;
        }
        .panel-actions {
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-link {
            color: #D4AF37;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(212, 175, 55, 0.35);
            border-radius: 999px;
            padding: 14px 22px;
            font: inherit;
            transition: background 0.25s ease, transform 0.25s ease;
        }
        .btn-link:hover {
            background: rgba(255, 255, 255, 0.18);
            transform: translateY(-1px);
        }
        .form-panel {
            margin-top: 0;
        }
        .form-panel h2 {
            margin: 0 0 20px;
            font-size: 2rem;
        }
        .field-group {
            margin-bottom: 12px;
            display: grid;
        }
        .field-group label {
            margin-bottom: 10px;
            font-size: 0.95rem;
            font-weight: 600;
        }
        .input-icon-wrapper {
            position: relative;
            width: 100%;
            overflow: hidden;
        }
        .input-icon-wrapper input,
        .input-icon-wrapper select {
            width: 100%;
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 18px;
            padding: 12px 40px 12px 16px;
            font-size: 0.95rem;
            background: rgba(255,255,255,0.94);
            color: #111;
            outline: none;
            transition: border-color 0.25s ease, background 0.25s ease;
            box-sizing: border-box;
        }
        .input-icon {
            display: none;
        }
        .upload-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 14px;
        }
        .upload-box {
            position: relative;
            background: #ffffff;
            border: 1px dashed #d1d5db;
            border-radius: 24px;
            padding: 24px 18px;
            min-height: 220px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            cursor: pointer;
            transition: border-color 0.25s ease, box-shadow 0.25s ease, transform 0.25s ease, padding 0.25s ease;
            overflow: hidden;
        }
        .upload-box:hover {
            border-color: #9ca3af;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            transform: translateY(-1px);
        }
        .upload-box .upload-preview {
            position: absolute;
            inset: 0;
            display: none;
            background: #f8fafc;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .upload-box.has-preview {
            padding: 0;
        }
        .upload-box.has-preview .upload-preview {
            border-radius: 24px;
        }
        .upload-box .upload-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .upload-box .remove-preview {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.56);
            color: #ffffff;
            border: none;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2;
            cursor: pointer;
            font-size: 0.95rem;
        }
        .upload-box.has-preview .remove-preview {
            display: flex;
        }
        .upload-box .upload-content {
            position: relative;
            z-index: 2;
            display: grid;
            gap: 10px;
        }
        .upload-box.has-preview .upload-content {
            opacity: 0;
            pointer-events: none;
        }
        .upload-box .upload-title {
            display: block;
            font-size: 0.95rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 12px;
        }
        .upload-box .upload-icon {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 2.5rem;
            margin: 10px 0;
        }
        .upload-box .upload-caption {
            display: block;
            text-align: center;
            color: #6b7280;
            font-size: 0.95rem;
            margin-top: 10px;
        }
        .upload-box .upload-filename {
            display: block;
            text-align: center;
            color: #9ca3af;
            font-size: 0.82rem;
            margin-top: 10px;
        }
        .upload-box input[type="file"] {
            display: none;
        }
        @media (max-width: 900px) {
            .upload-grid {
                grid-template-columns: 1fr;
            }
        }
        .input-icon-wrapper select {
            appearance: none;
            background-image: url('data:image/svg+xml;charset=UTF-8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="%23000" d="M5.8 7.6 10 11.8l4.2-4.2"/></svg>');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 14px;
        }
        .input-icon-wrapper select option {
            color: #111;
            background: #fff;
        }
        .input-icon-wrapper input:focus,
        .input-icon-wrapper select:focus {
            border-color: rgba(128,0,0,0.45);
            background: rgba(255,255,255,0.98);
        }
        .input-icon {
            position: absolute;
            top: 50%;
            left: 16px;
            transform: translateY(-50%);
            color: #800000;
            opacity: 0.8;
            font-size: 1rem;
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-width: 140px;
            border: none;
            border-radius: 16px;
            background: #D4AF37;
            color: #800000;
            padding: 14px 20px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, color 0.2s ease;
            text-shadow: 0 1px 2px rgba(255,255,255,0.15);
        }
        .btn-primary:hover {
            background: #b58f31;
            color: #590000;
            transform: translateY(-1px);
            box-shadow: 0 18px 40px rgba(120, 0, 0, 0.25);
        }
        .auth-side-right .btn-primary {
            background: #D4AF37;
            color: #800000;
        }
        .auth-side-right .btn-primary:hover {
            background: #b58f31;
            color: #590000;
        }
        .link-text {
            color: inherit;
            opacity: 0.9;
        }
        .link-text a {
            color: inherit;
            text-decoration: underline;
            font-weight: 600;
        }
        .alert {
            margin-bottom: 20px;
            padding: 16px 18px;
            border-radius: 16px;
            font-size: 0.95rem;
            line-height: 1.5;
            border: 1px solid transparent;
        }
        .alert.error {
            background: rgba(255, 255, 255, 0.12);
            color: #ffe3e3;
            border-color: rgba(255, 255, 255, 0.18);
        }
        .alert.success {
            background: rgba(220, 38, 38, 0.16);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.25);
        }
        .panel-content {
            transition: opacity 0.6s ease, transform 0.6s ease;
        }
        .login-only {
            opacity: 1;
            transform: translateX(0);
            pointer-events: auto;
        }
        .register-only {
            opacity: 0;
            transform: translateX(40px);
            pointer-events: none;
            position: absolute;
            inset: 0;
        }
        .auth-card.register-mode .login-only {
            opacity: 0;
            transform: translateX(-40px);
            pointer-events: none;
            position: absolute;
            inset: 0;
        }
        .auth-card.register-mode .register-only {
            opacity: 1;
            transform: translateX(0);
            pointer-events: auto;
            position: relative;
        }
        .auth-footer {
            margin-top: 22px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            color: rgba(255,255,255,0.82);
        }
        .auth-footer a {
            color: inherit;
            text-decoration: underline;
        }
        .footer-links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            color: rgba(255,255,255,0.8);
        }
        @media (max-width: 768px) and (min-width: 320px) {
            /* Mirror teacher login: fixed full-width header and tighter card for tablet->mobile */
            .auth-header {
                left: 0 !important;
                right: 0 !important;
                top: 0 !important;
                width: 100% !important;
                padding: 12px 14px !important;
                gap: 10px !important;
                background: rgba(128, 0, 0, 0.96) !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                position: fixed !important;
                z-index: 30 !important;
            }

            .auth-header-link {
                margin: 0 !important;
                padding: 6px 10px !important;
                border-radius: 0 !important;
            }

            .auth-header img {
                width: 56px !important;
                height: 56px !important;
            }

            .auth-header-title strong {
                font-size: 1.1rem !important;
            }

            .auth-card {
                width: calc(100vw - 24px) !important;
                margin: 70px auto 24px !important;
                border-radius: 20px !important;
                grid-template-columns: 1fr !important;
            }

            .auth-side,
            .auth-side-left,
            .auth-side-right {
                width: 100% !important;
                padding: 16px !important;
            }

            .panel {
                margin-bottom: 12px !important;
                padding: 0 !important;
            }

            .panel-title {
                font-size: 1.8rem !important;
                margin-bottom: 12px !important;
                line-height: 1.1 !important;
            }

            .panel-text {
                margin-bottom: 14px !important;
                line-height: 1.55 !important;
                font-size: 0.95rem !important;
            }

            .panel-actions {
                gap: 12px !important;
                margin-top: 10px !important;
                display: flex !important;
                flex-wrap: wrap !important;
                align-items: center !important;
                width: 100% !important;
            }

            .panel-actions .btn-action {
                flex: 1 !important;
                min-width: 120px !important;
                width: auto !important;
            }

            .auth-page { padding-top: 72px; }

            /* show more of the image near the top on smaller screens */
            .background-slide {
                background-position: center top !important;
            }

            .background-tint {
                background: linear-gradient(180deg, rgba(128, 0, 0, 0.72) 0%, rgba(128, 0, 0, 0.34) 100%) !important;
            }
        }

        @media (min-width: 769px) and (max-width: 1099px) {
            .auth-card {
                width: min(980px, calc(100vw - 28px));
                margin: 86px auto 18px;
                grid-template-columns: 1fr;
            }
            .auth-side {
                padding: 26px 24px;
            }
            .panel-title {
                font-size: 1.8rem;
            }
            .register-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (min-width: 1100px) {
            .auth-card {
                width: min(1180px, calc(100vw - 48px));
                margin: 96px auto 18px;
            }
            .auth-side {
                padding: 28px 26px 24px;
            }
            .panel-title {
                font-size: 2rem;
            }
        }

        /* Password Requirements Styling */
        .password-requirements {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
            margin-top: 12px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 8px;
            border: 1px solid rgba(212, 175, 55, 0.1);
            width: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }

        .requirement-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #8b5a3c;
            animation: slideIn 0.4s ease-out forwards;
            opacity: 0;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-8px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .requirement-item:nth-child(1) { animation-delay: 0.05s; }
        .requirement-item:nth-child(2) { animation-delay: 0.1s; }
        .requirement-item:nth-child(3) { animation-delay: 0.15s; }
        .requirement-item:nth-child(4) { animation-delay: 0.2s; }

        .requirement-icon {
            font-size: 0.6rem;
            color: #d4a574;
            transition: all 0.3s ease;
            display: inline-flex;
        }

        .requirement-text {
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .requirement-item.met {
            color: #4caf50;
            animation: bounce 0.4s ease;
        }

        .requirement-item.met .requirement-icon {
            color: #4caf50;
            font-size: 0.75rem;
        }

        .requirement-item.met .requirement-icon::before {
            content: "✓";
            position: absolute;
            font-size: 0.85rem;
        }

        @keyframes bounce {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.08);
            }
            100% {
                transform: scale(1);
            }
        }

        .requirement-item.not-met {
            color: #d4a574;
        }

        .requirement-item.not-met .requirement-icon {
            color: #d4a574;
        }

        

        @media (max-width: 425px) {
            .auth-header {
                padding: 10px 12px !important;
                gap: 8px !important;
                flex-wrap: wrap !important;
                align-items: center !important;
            }

            .auth-header-link {
                width: 100% !important;
                min-width: 0 !important;
                align-items: center !important;
            }

            .auth-header-title {
                display: flex !important;
                flex-direction: column !important;
                gap: 2px !important;
                min-width: 0 !important;
                max-width: calc(100% - 56px) !important;
            }

            .auth-header-title strong {
                font-size: 0.85rem !important;
                white-space: normal !important;
                display: block !important;
                font-family: 'ElephantLocal', 'Cormorant Garamond', serif !important;
            }

            .auth-header-title span {
                font-size: 0.65rem !important;
                white-space: normal !important;
                display: block !important;
                line-height: 1.1 !important;
                max-width: 100% !important;
                overflow-wrap: break-word !important;
            }

            /* higher-specificity rule to override global responsive.css padding-left */
            .auth-page .input-icon-wrapper input,
            .auth-page .input-icon-wrapper select {
                min-height: 48px !important;
                /* tighten left padding so typing starts closer to edge; keep room on right for toggle */
                padding: 14px 56px 14px 16px !important;
            }

            .input-icon-wrapper input[type="password"] {
                padding-right: 58px !important;
            }

            .btn-action,
            .btn-primary,
            .btn-link {
                min-height: 48px !important;
            }
        }

        

        @media (max-width: 375px) {
            .auth-header {
                padding: 8px 10px !important;
                gap: 6px !important;
                align-items: center !important;
            }

            .auth-header img {
                width: 44px !important;
                height: 44px !important;
            }

            .auth-header-link {
                width: 100% !important;
                min-width: 0 !important;
                align-items: center !important;
            }

            .auth-header-title {
                display: flex !important;
                flex-direction: column !important;
                gap: 2px !important;
                min-width: 0 !important;
                max-width: calc(100% - 56px) !important;
            }

            .auth-header-title strong {
                font-size: 0.8rem !important;
                white-space: normal !important;
                display: block !important;
                font-family: 'ElephantLocal', 'Cormorant Garamond', serif !important;
            }

            .auth-header-title span {
                font-size: 0.65rem !important;
                white-space: normal !important;
                display: block !important;
                line-height: 1.1 !important;
                max-width: 100% !important;
                overflow-wrap: break-word !important;
            }

            .auth-page {
                padding-top: 80px;
            }
            /* Small-screen input tweaks: hide decorative input icons, tighten left padding, keep password toggle inside */
            .input-icon { display: none !important; }

            .input-icon-wrapper input,
            .input-icon-wrapper select {
                padding-left: 12px !important;
            }

            .input-icon-wrapper input[type="password"] {
                padding-right: 48px !important;
            }

            .password-toggle {
                right: 8px !important;
                padding: 6px 8px !important;
                font-size: 0.95rem !important;
                top: 50% !important;
                transform: translateY(-50%) !important;
                z-index: 12 !important;
            }
        }

        @media (max-width: 320px) {
            .auth-header {
                padding: 8px 10px !important;
                flex-wrap: wrap !important;
                align-items: center !important;
            }

            .auth-header img {
                width: 40px !important;
                height: 40px !important;
            }

            .auth-header-link {
                width: 100% !important;
                min-width: 0 !important;
                align-items: center !important;
            }

            .auth-header-title strong {
                font-size: 0.75rem !important;
                white-space: normal !important;
                display: block !important;
            }

            .auth-header-title span {
                font-size: 0.6rem !important;
                white-space: normal !important;
                display: block !important;
                line-height: 1.05 !important;
                max-width: 100% !important;
                overflow-wrap: break-word !important;
            }

            .auth-header-title strong {
                font-family: 'ElephantLocal', 'Cormorant Garamond', serif !important;
            }

            .auth-header-title strong,
            .panel-title,
            .nav-title {
                font-family: 'ElephantLocal', 'Cormorant Garamond', serif !important;
            }

            .panel-title {
                font-size: 1.4rem !important;
            }

            .auth-page {
                padding-top: 75px;
            }

            /* Even tighter input spacing on smallest screens */
            .input-icon { display: none !important; }
            .input-icon-wrapper input,
            .input-icon-wrapper select { padding-left: 10px !important; }
            .input-icon-wrapper input[type="password"] { padding-right: 42px !important; }
            .password-toggle { right: 6px !important; top: 50% !important; transform: translateY(-50%) !important; z-index: 12 !important; }

            .panel-title {
                font-size: 1.4rem !important;
            }

            .auth-page {
                padding-top: 75px;
            }
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
    <div class="auth-page">
        <div class="background-carousel">
            <?php foreach ($carouselImages as $index => $image): ?>
            <div class="background-slide<?= $index === 0 ? ' active' : '' ?>" style="background-image: url('<?= htmlspecialchars($image) ?>');"></div>
            <?php endforeach; ?>
        </div>
        <div class="background-tint"></div>

        <header class="auth-header">
            <a href="../" class="auth-header-link" title="Go to Home Page">
                <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                <div class="auth-header-title">
                    <strong>PASS COLLEGE</strong>
                    <span>Student Support Portal</span>
                </div>
            </a>
        </header>

        <main class="auth-card<?= $authMode === 'register' ? ' register-mode' : '' ?>">
            <section class="auth-side auth-side-left">
                <div class="panel panel-content login-only">
                    <h1 class="panel-title"><i class="fa-solid fa-right-to-bracket panel-icon"></i> Welcome Back to <span class="panel-brand-text">PASS College</span></h1>
                    <p class="panel-text">Experience a secure student portal for class updates, academic progress, and campus services. Login now to continue your journey or create a new student account.</p>
                    <div class="panel-actions">
                        <button type="button" class="btn-action" id="show-register">Request Student Account</button>
                        <button type="button" class="btn-action" id="check-request-status" style="background:#fff;color:#800000;border:1px solid rgba(0,0,0,0.08);">Check Request Status</button>
                    </div>
                </div>
                <div class="panel panel-content register-only">
                    <h1 class="panel-title"><i class="fa-solid fa-user-plus panel-icon"></i> Request Student Account</h1>
                    <p class="panel-text">Register now and get access to campus resources, academic tracking, and support tools for PASS College students.</p>
                    <?php if ($message && $authMode === 'register'): ?><div class="alert <?= $messageClass ?>"><?= $message ?></div><?php endif; ?>
                    <form method="post" action="" enctype="multipart/form-data" class="form-panel register-grid registration-form">
                        <input type="hidden" name="action" value="register">
                        <input type="hidden" name="agreement_consent" value="0">
                        <input type="hidden" name="privacy_consent" value="0">
                        <div class="field-group">
                            <label for="firstNameInput"><i class="fa-solid fa-user label-icon"></i> First Name</label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-user input-icon"></i>
                                <input id="firstNameInput" type="text" name="first_name" value="<?= htmlspecialchars($registerValues['first_name'] ?? '') ?>" placeholder="Enter your first name" autocomplete="given-name" required>
                            </div>
                        </div>
                        <div class="field-group">
                            <label for="middleNameInput"><i class="fa-solid fa-user-pen label-icon"></i> Middle Name <span class="optional">(optional)</span></label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-user input-icon"></i>
                                <input id="middleNameInput" type="text" name="middle_name" value="<?= htmlspecialchars($registerValues['middle_name'] ?? '') ?>" placeholder="Enter your middle name (optional)" autocomplete="additional-name">
                            </div>
                        </div>
                        <div class="field-group full-width">
                            <label for="lastNameInput"><i class="fa-solid fa-user-tag label-icon"></i> Last Name</label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-user input-icon"></i>
                                <input id="lastNameInput" type="text" name="last_name" value="<?= htmlspecialchars($registerValues['last_name'] ?? '') ?>" placeholder="Enter your last name" autocomplete="family-name" required>
                            </div>
                        </div>
                        <div class="field-group full-width">
                            <label for="registerEmail"><i class="fa-solid fa-envelope label-icon"></i> Email</label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-envelope input-icon"></i>
                                <input id="registerEmail" type="email" name="email" value="<?= htmlspecialchars($registerValues['email']) ?>" placeholder="Enter your email address" autocomplete="email" required>
                            </div>
                        </div>
                        <div class="field-group full-width">
                            <label for="studentIdInput"><i class="fa-solid fa-id-card label-icon"></i> Student ID</label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-id-card input-icon"></i>
                                <input id="studentIdInput" type="text" name="student_id" value="<?= htmlspecialchars($registerValues['student_id']) ?>" placeholder="22-12345" pattern="[0-9]{2}-[0-9]{5}" maxlength="8" required>
                            </div>
                        </div>
                        <div class="field-group">
                            <label for="studentCourseSelect"><i class="fa-solid fa-graduation-cap label-icon"></i> Course</label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-graduation-cap input-icon"></i>
                                <select id="studentCourseSelect" name="course">
                                    <option value="">Select course</option>
                                    <?php foreach ($courseOptions as $course): ?>
                                        <option value="<?= htmlspecialchars($course) ?>"<?= $registerValues['course'] === $course ? ' selected' : '' ?>><?= htmlspecialchars($course) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="field-group">
                            <label for="ladderizeCourseSelect"><i class="fa-solid fa-layer-group label-icon"></i> Ladderize Courses</label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-layer-group input-icon"></i>
                                <select id="ladderizeCourseSelect" name="ladderize_course">
                                    <option value="">Select ladderize course</option>
                                    <?php foreach ($ladderizeOptions as $course): ?>
                                        <option value="<?= htmlspecialchars($course) ?>"<?= $registerValues['ladderize_course'] === $course ? ' selected' : '' ?>><?= htmlspecialchars($course) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="field-group">
                            <label for="studentYearSelect"><i class="fa-solid fa-calendar-days label-icon"></i> Year Level</label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-calendar-days input-icon"></i>
                                <select id="studentYearSelect" name="year" required>
                                    <option value="">Select year</option>
                                    <option value="1"<?= $registerValues['year'] === '1' ? ' selected' : '' ?>>1</option>
                                    <option value="2"<?= $registerValues['year'] === '2' ? ' selected' : '' ?>>2</option>
                                    <option value="3"<?= $registerValues['year'] === '3' ? ' selected' : '' ?>>3</option>
                                    <option value="4"<?= $registerValues['year'] === '4' ? ' selected' : '' ?>>4</option>
                                </select>
                            </div>
                        </div>
                        <div class="field-group full-width">
                            <label style="margin-top: 16px;"><i class="fa-solid fa-image label-icon"></i> Input Registration Form</label>
                            <p class="field-note">Upload both front and back copies of your registration document. Both files are required to submit the request.</p>
                        </div>
                        <div class="upload-grid full-width">
                            <div class="upload-box" data-target="documentFront">
                                <div class="upload-preview"><img src="" alt="Front side preview"></div>
                                <button type="button" class="remove-preview" aria-label="Remove front side image">×</button>
                                <div class="upload-content">
                                    <span class="upload-title">Front Side (required)</span>
                                    <div class="upload-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                                    <span class="upload-caption">Upload front side</span>
                                    <span class="upload-filename">No file chosen</span>
                                </div>
                                <input id="documentFront" type="file" name="document_front" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                            </div>
                            <div class="upload-box" data-target="documentBack">
                                <div class="upload-preview"><img src="" alt="Back side preview"></div>
                                <button type="button" class="remove-preview" aria-label="Remove back side image">×</button>
                                <div class="upload-content">
                                    <span class="upload-title">Back Side (required)</span>
                                    <div class="upload-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                                    <span class="upload-caption">Upload back side</span>
                                    <span class="upload-filename">No file chosen</span>
                                </div>
                                <input id="documentBack" type="file" name="document_back" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                            </div>
                        </div>
                        <div class="field-group full-width">
                            <label for="registerPassword"><i class="fa-solid fa-lock label-icon"></i> Password</label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-lock input-icon"></i>
                                <input id="registerPassword" type="password" name="password" placeholder="Min 9 chars: Uppercase, Lowercase, Number" pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{9,}$" title="Password must be at least 9 characters and contain uppercase, lowercase, and a number" required>
                                <button type="button" class="password-toggle" data-target="registerPassword"><i class="fa-solid fa-eye"></i></button>
                            </div>
                            <div class="password-requirements">
                                <div class="requirement-item" id="req-length">
                                    <i class="fa-solid fa-circle requirement-icon"></i>
                                    <span class="requirement-text">At least 9 characters</span>
                                </div>
                                <div class="requirement-item" id="req-upper">
                                    <i class="fa-solid fa-circle requirement-icon"></i>
                                    <span class="requirement-text">Uppercase letter (A-Z)</span>
                                </div>
                                <div class="requirement-item" id="req-lower">
                                    <i class="fa-solid fa-circle requirement-icon"></i>
                                    <span class="requirement-text">Lowercase letter (a-z)</span>
                                </div>
                                <div class="requirement-item" id="req-number">
                                    <i class="fa-solid fa-circle requirement-icon"></i>
                                    <span class="requirement-text">Number (0-9)</span>
                                </div>
                            </div>
                        </div>
                        <div class="field-group full-width">
                            <label for="registerConfirmPassword"><i class="fa-solid fa-lock label-icon"></i> Confirm Password</label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-lock input-icon"></i>
                                <input id="registerConfirmPassword" type="password" name="confirm_password" placeholder="Confirm your password" required>
                                <button type="button" class="password-toggle" data-target="registerConfirmPassword"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="form-actions full-width">
                            <button type="submit" class="btn-primary">Request Student Account</button>
                            <p class="link-text">Already have an account? <button type="button" class="btn-link" id="show-login">Login</button></p>
                        </div>
                    </form>
                </div>
            </section>

            <section class="auth-side auth-side-right">
                <div class="panel panel-content login-only">
                    <h1 class="panel-title"><i class="fa-solid fa-right-to-bracket panel-icon"></i> Student Login</h1>
                    <?php if ($message && $authMode === 'login'): ?><div class="alert <?= $messageClass ?>"><?= $message ?></div><?php endif; ?>
                    <form method="post" action="" class="form-panel">
                        <input type="hidden" name="action" value="login">
                        <div class="field-group">
                            <label for="loginEmail"><i class="fa-solid fa-envelope label-icon"></i> Email</label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-envelope input-icon"></i>
                                <input id="loginEmail" type="email" name="email" placeholder="Enter your student email" autocomplete="email" required style="padding-left:12px !important;">
                            </div>
                        </div>
                        <div class="field-group">
                            <label for="loginPassword"><i class="fa-solid fa-lock label-icon"></i> Password</label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-lock input-icon"></i>
                                <input id="loginPassword" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required style="padding-left:12px !important; padding-right:48px !important;">
                                <button type="button" class="password-toggle" data-target="loginPassword"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                        <button type="submit" class="btn-primary">Login</button>
                    </form>
                    <div class="auth-footer">
                        <p><button type="button" class="btn-link" id="forgotPasswordBtn">Forgot password?</button></p>
                    </div>
                </div>
                <div class="panel panel-content register-only">
                    <h1 class="panel-title">Already have an account?</h1>
                    <p class="panel-text">If you're already registered, return to the login page and continue your student access.</p>
                    <div class="panel-actions">
                        <button type="button" class="btn-action" id="show-login-2">Back to Login</button>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <?php include __DIR__ . '/../includes/privacy_consent.php'; ?>

    <!-- Request status modal -->
    <div id="requestStatusModal" class="modal request-status-modal" style="display:none;">
        <div class="request-status-card">
            <div class="request-status-heading">
                <div class="request-status-heading-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                <h3>Check Request Status</h3>
            </div>
            <p class="request-status-description">Enter the email or student ID you used when requesting your account.</p>
            <div class="request-status-fields">
                <input id="statusEmail" type="email" placeholder="Email (optional)" autocomplete="email">
                <input id="statusStudentId" type="text" placeholder="Student ID (e.g. 23-02036)" inputmode="numeric" pattern="[0-9]{2}-[0-9]{5}" maxlength="8" autocomplete="off">
            </div>
            <div id="statusResult" class="request-status-result"></div>
            <div class="request-status-footer">
                <button type="button" id="closeStatusModal" class="request-status-close">Close</button>
                <button type="button" id="submitStatusCheck" class="request-status-check"><i class="fa-solid fa-magnifying-glass"></i> Check status</button>
            </div>
        </div>
    </div>

    <!-- Forgot password modal -->
    <div id="forgotPasswordModal" class="modal" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;z-index:9999;">
        <div style="background:rgba(0,0,0,0.6);position:absolute;inset:0;"></div>
        <div style="position:relative;background:#fff;width:440px;max-width:96%;border-radius:14px;padding:20px;z-index:1000;color:#111;">
            <h3 style="margin:0 0 8px 0;">Reset Password</h3>
            <p style="margin:0 0 14px 0;color:#444;">Enter your email and follow the OTP steps to reset your password.</p>
            <div id="resetStepEmail" style="display:grid;gap:10px;margin-bottom:12px;">
                <input id="resetEmail" type="email" placeholder="Student email" style="padding:12px;border:1px solid #ddd;border-radius:10px;">
                <button type="button" id="sendResetOtp" class="btn-primary" style="width:100%;">Send Verification Code</button>
            </div>
            <div id="resetStepOtp" style="display:none;gap:10px;margin-bottom:12px;">
                <label style="font-size:0.95rem;font-weight:600;color:#111;">Enter 6-digit code</label>
                <div id="resetOtpInputs" style="display:grid;grid-template-columns:repeat(6,1fr);gap:5px;margin-bottom:10px;max-width:100%;">
                    <input type="text" class="reset-otp-digit" inputmode="numeric" pattern="[0-9]*" maxlength="1" style="padding:6px;border:1px solid #ddd;border-radius:6px;text-align:center;font-size:0.95rem;font-weight:600;width:100%;box-sizing:border-box;" />
                    <input type="text" class="reset-otp-digit" inputmode="numeric" pattern="[0-9]*" maxlength="1" style="padding:6px;border:1px solid #ddd;border-radius:6px;text-align:center;font-size:0.95rem;font-weight:600;width:100%;box-sizing:border-box;" />
                    <input type="text" class="reset-otp-digit" inputmode="numeric" pattern="[0-9]*" maxlength="1" style="padding:6px;border:1px solid #ddd;border-radius:6px;text-align:center;font-size:0.95rem;font-weight:600;width:100%;box-sizing:border-box;" />
                    <input type="text" class="reset-otp-digit" inputmode="numeric" pattern="[0-9]*" maxlength="1" style="padding:6px;border:1px solid #ddd;border-radius:6px;text-align:center;font-size:0.95rem;font-weight:600;width:100%;box-sizing:border-box;" />
                    <input type="text" class="reset-otp-digit" inputmode="numeric" pattern="[0-9]*" maxlength="1" style="padding:6px;border:1px solid #ddd;border-radius:6px;text-align:center;font-size:0.95rem;font-weight:600;width:100%;box-sizing:border-box;" />
                    <input type="text" class="reset-otp-digit" inputmode="numeric" pattern="[0-9]*" maxlength="1" style="padding:6px;border:1px solid #ddd;border-radius:6px;text-align:center;font-size:0.95rem;font-weight:600;width:100%;box-sizing:border-box;" />
                </div>
                <input type="hidden" id="resetOtpCode">
                <button type="button" id="verifyResetOtp" class="btn-primary" style="width:100%;">Verify Code</button>
            </div>
            <div id="resetStepPassword" style="display:none;gap:10px;margin-bottom:12px;">
                <input id="resetNewPassword" type="password" placeholder="New password" style="padding:12px;border:1px solid #ddd;border-radius:10px;">
                <input id="resetConfirmPassword" type="password" placeholder="Confirm new password" style="padding:12px;border:1px solid #ddd;border-radius:10px;">
                <button type="button" id="changePasswordBtn" class="btn-primary" style="width:100%;">Change Password</button>
            </div>
            <div id="resetFeedback" style="min-height:42px;color:#222;margin-bottom:12px;"></div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" id="closeForgotModal" class="btn-action" style="background:#eee;color:#111;border:1px solid rgba(0,0,0,0.08);">Close</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js"></script>
    <script>
        const authCard = document.querySelector('.auth-card');
        const registerTriggers = document.querySelectorAll('#show-register, #show-register-2');
        const loginTriggers = document.querySelectorAll('#show-login, #show-login-2');
        const slides = document.querySelectorAll('.background-slide');
        let slideIndex = 0;

        function setAuthMode(mode) {
            const isRegister = mode === 'register';
            authCard.classList.toggle('register-mode', isRegister);
            window.history.replaceState({}, '', isRegister ? '?mode=register' : '?mode=login');
            toggleRegisterFields(isRegister);
        }

        function toggleRegisterFields(enable) {
            const regForm = document.querySelector('.form-panel.register-grid');
            if (!regForm) return;
            regForm.querySelectorAll('input,select,textarea,button').forEach(el => {
                // keep hidden action inputs enabled
                if (el.type === 'hidden') return;
                if (enable) {
                    el.disabled = false;
                    if (el.dataset.required === '1') el.required = true;
                } else {
                    if (el.required) {
                        el.dataset.required = '1';
                        el.required = false;
                    }
                    el.disabled = true;
                }
            });
        }

        registerTriggers.forEach(button => button.addEventListener('click', function () {
            setAuthMode('register');
        }));

        loginTriggers.forEach(button => button.addEventListener('click', function () {
            setAuthMode('login');
        }));

        function rotateSlide() {
            slides[slideIndex].classList.remove('active');
            slideIndex = (slideIndex + 1) % slides.length;
            slides[slideIndex].classList.add('active');
        }

        document.querySelectorAll('.upload-box').forEach(box => {
            const targetId = box.dataset.target;
            const fileInput = document.getElementById(targetId);
            const filenameLabel = box.querySelector('.upload-filename');
            if (!fileInput || !filenameLabel) {
                return;
            }

            const previewContainer = box.querySelector('.upload-preview');
            const previewImage = previewContainer ? previewContainer.querySelector('img') : null;

            const removeButton = box.querySelector('.remove-preview');

            const clearPreview = () => {
                box.classList.remove('has-preview');
                if (previewContainer) {
                    previewContainer.style.display = 'none';
                }
                if (previewImage) {
                    const oldUrl = previewImage.dataset.url;
                    if (oldUrl) {
                        URL.revokeObjectURL(oldUrl);
                        previewImage.dataset.url = '';
                    }
                    previewImage.src = '';
                }
                if (fileInput) {
                    fileInput.value = '';
                }
                filenameLabel.textContent = 'No file chosen';
            };

            box.addEventListener('click', event => {
                if (event.target.closest('.remove-preview')) {
                    return;
                }
                fileInput.click();
            });

            if (removeButton) {
                removeButton.addEventListener('click', event => {
                    event.stopPropagation();
                    clearPreview();
                });
            }

            fileInput.addEventListener('change', () => {
                const file = fileInput.files[0];
                const fileName = file ? file.name : 'No file chosen';
                filenameLabel.textContent = fileName;

                if (!file || !previewImage) {
                    clearPreview();
                    return;
                }

                const isImage = file.type.startsWith('image/');
                if (isImage) {
                    const oldUrl = previewImage.dataset.url;
                    if (oldUrl) {
                        URL.revokeObjectURL(oldUrl);
                    }
                    const objectUrl = URL.createObjectURL(file);
                    previewImage.src = objectUrl;
                    previewImage.dataset.url = objectUrl;
                    previewContainer.style.display = 'flex';
                    box.classList.add('has-preview');
                } else {
                    clearPreview();
                }
            });
        });

        setInterval(rotateSlide, 7000);
        if (authCard.classList.contains('register-mode')) {
            setAuthMode('register');
        }

        const passwordResetConfig = {
            serviceId: 'service_843t745',
            templateId: 'template_jb5tjxv',
            publicKey: 'rLeRK76s3p-IQn-X1'
        };

        // Forgot password modal wiring
        const forgotPasswordBtn = document.getElementById('forgotPasswordBtn');
        const forgotPasswordModal = document.getElementById('forgotPasswordModal');
        const closeForgotModal = document.getElementById('closeForgotModal');
        const sendResetOtp = document.getElementById('sendResetOtp');
        const verifyResetOtp = document.getElementById('verifyResetOtp');
        const changePasswordBtn = document.getElementById('changePasswordBtn');
        const resetEmailInput = document.getElementById('resetEmail');
        const resetOtpDigits = document.querySelectorAll('.reset-otp-digit');
        const resetOtpCode = document.getElementById('resetOtpCode');
        const resetNewPasswordInput = document.getElementById('resetNewPassword');
        const resetConfirmPasswordInput = document.getElementById('resetConfirmPassword');
        const resetFeedback = document.getElementById('resetFeedback');
        const resetStepEmail = document.getElementById('resetStepEmail');
        const resetStepOtp = document.getElementById('resetStepOtp');
        const resetStepPassword = document.getElementById('resetStepPassword');

        function showResetStep(step) {
            resetStepEmail.style.display = step === 'email' ? 'grid' : 'none';
            resetStepOtp.style.display = step === 'otp' ? 'grid' : 'none';
            resetStepPassword.style.display = step === 'password' ? 'grid' : 'none';
            resetFeedback.textContent = '';
            if (step === 'otp') {
                resetOtpDigits[0].focus();
            }
        }

        function openForgotPasswordModal() {
            forgotPasswordModal.style.display = 'flex';
            resetEmailInput.value = '';
            resetOtpDigits.forEach(d => d.value = '');
            resetOtpCode.value = '';
            resetNewPasswordInput.value = '';
            resetConfirmPasswordInput.value = '';
            showResetStep('email');
        }

        // Wire up OTP digit inputs for auto-focus behavior
        resetOtpDigits.forEach((input, index) => {
            input.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length > 1) {
                    this.value = this.value.slice(-1);
                }
                if (this.value && index < resetOtpDigits.length - 1) {
                    resetOtpDigits[index + 1].focus();
                }
            });

            input.addEventListener('keydown', function (event) {
                if (event.key === 'Backspace' && !this.value && index > 0) {
                    resetOtpDigits[index - 1].focus();
                }
            });
        });

        function assembleResetOtp() {
            return Array.from(resetOtpDigits).map(d => d.value.trim()).join('');
        }

        async function sendPasswordResetOtp(email) {
            const response = await fetch('password_reset_send_otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email })
            });
            return response.json();
        }

        async function verifyPasswordResetOtp(email, code) {
            const response = await fetch('password_reset_verify_otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, code })
            });
            return response.json();
        }

        async function completePasswordReset(email, code, password, confirm_password) {
            const response = await fetch('password_reset_complete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, code, password, confirm_password })
            });
            return response.json();
        }

        if (forgotPasswordBtn) {
            forgotPasswordBtn.addEventListener('click', openForgotPasswordModal);
        }
        if (closeForgotModal) {
            closeForgotModal.addEventListener('click', () => { forgotPasswordModal.style.display = 'none'; });
        }

        if (sendResetOtp) {
            sendResetOtp.addEventListener('click', async () => {
                const email = resetEmailInput.value.trim();
                if (!email) {
                    resetFeedback.textContent = 'Please enter your student email.';
                    resetFeedback.style.color = '#b91c1c';
                    return;
                }
                resetFeedback.textContent = 'Sending code...';
                resetFeedback.style.color = '#111';
                try {
                    const result = await sendPasswordResetOtp(email);
                    if (!result.success) {
                        resetFeedback.textContent = result.message || 'Unable to send code.';
                        resetFeedback.style.color = '#b91c1c';
                        return;
                    }
                    if (typeof emailjs !== 'undefined') {
                        emailjs.init(passwordResetConfig.publicKey);
                        await emailjs.send(passwordResetConfig.serviceId, passwordResetConfig.templateId, {
                            user_email: email,
                            to_email: email,
                            email: email,
                            name: email,
                            otp_code: result.code || result.otp
                        });
                    }
                    resetFeedback.textContent = 'Verification code sent. Check your email.';
                    resetFeedback.style.color = '#0f766e';
                    showResetStep('otp');
                } catch (err) {
                    resetFeedback.textContent = 'Failed to send code. Please try again.';
                    resetFeedback.style.color = '#b91c1c';
                }
            });
        }

        if (verifyResetOtp) {
            verifyResetOtp.addEventListener('click', async () => {
                const email = resetEmailInput.value.trim();
                const code = assembleResetOtp();
                if (!code || code.length !== 6) {
                    resetFeedback.textContent = 'Enter all 6 digits.';
                    resetFeedback.style.color = '#b91c1c';
                    return;
                }
                resetFeedback.textContent = 'Verifying code...';
                resetFeedback.style.color = '#111';
                try {
                    const result = await verifyPasswordResetOtp(email, code);
                    if (!result.success) {
                        resetFeedback.textContent = result.message || 'Invalid code.';
                        resetFeedback.style.color = '#b91c1c';
                        return;
                    }
                    resetOtpCode.value = code;
                    resetFeedback.textContent = 'Code verified. You may now change your password.';
                    resetFeedback.style.color = '#0f766e';
                    showResetStep('password');
                } catch (err) {
                    resetFeedback.textContent = 'Verification failed.';
                    resetFeedback.style.color = '#b91c1c';
                }
            });
        }

        if (changePasswordBtn) {
            changePasswordBtn.addEventListener('click', async () => {
                const email = resetEmailInput.value.trim();
                const code = resetOtpCode.value.trim();
                const password = resetNewPasswordInput.value;
                const confirmPassword = resetConfirmPasswordInput.value;
                if (!password || !confirmPassword) {
                    resetFeedback.textContent = 'Enter and confirm your new password.';
                    resetFeedback.style.color = '#b91c1c';
                    return;
                }
                if (password !== confirmPassword) {
                    resetFeedback.textContent = 'Passwords do not match.';
                    resetFeedback.style.color = '#b91c1c';
                    return;
                }
                resetFeedback.textContent = 'Updating password...';
                resetFeedback.style.color = '#111';
                try {
                    const result = await completePasswordReset(email, code, password, confirmPassword);
                    if (!result.success) {
                        resetFeedback.textContent = result.message || 'Unable to reset password.';
                        resetFeedback.style.color = '#b91c1c';
                        return;
                    }
                    resetFeedback.textContent = 'Password changed successfully. You may now log in.';
                    resetFeedback.style.color = '#0f766e';
                    setTimeout(() => { forgotPasswordModal.style.display = 'none'; }, 1800);
                } catch (err) {
                    resetFeedback.textContent = 'Error changing password.';
                    resetFeedback.style.color = '#b91c1c';
                }
            });
        }

        // Request status modal wiring
        const checkStatusBtn = document.getElementById('check-request-status');
        const requestModal = document.getElementById('requestStatusModal');
        const closeStatusModal = document.getElementById('closeStatusModal');
        const submitStatusCheck = document.getElementById('submitStatusCheck');
        const statusResult = document.getElementById('statusResult');
        const statusStudentIdInput = document.getElementById('statusStudentId');

        function formatStudentId(value) {
            const digits = value.replace(/[^0-9]/g, '').slice(0, 7);
            return digits.length > 2 ? digits.slice(0, 2) + '-' + digits.slice(2) : digits;
        }

        function formatRequestStatus(status) {
            return String(status || 'unknown')
                .replace(/[_-]+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .replace(/\b\w/g, character => character.toUpperCase());
        }

        if (checkStatusBtn && requestModal) {
            checkStatusBtn.addEventListener('click', () => {
                requestModal.style.display = 'flex';
                document.getElementById('statusEmail').value = '';
                statusStudentIdInput.value = '';
                statusResult.innerHTML = '';
            });
        }
        if (closeStatusModal) {
            closeStatusModal.addEventListener('click', () => requestModal.style.display = 'none');
        }

        if (submitStatusCheck) {
            submitStatusCheck.addEventListener('click', async () => {
                const email = document.getElementById('statusEmail').value.trim();
                const studentId = formatStudentId(statusStudentIdInput.value);
                statusStudentIdInput.value = studentId;
                if (!email && !studentId) {
                    statusResult.innerHTML = '<div style="color:#b91c1c">Please enter email or student ID.</div>';
                    return;
                }
                if (studentId && !/^\d{2}-\d{5}$/.test(studentId)) {
                    statusResult.innerHTML = '<div style="color:#b91c1c">Student ID must be in the format 23-02036.</div>';
                    return;
                }
                statusResult.innerHTML = 'Checking...';
                try {
                    const resp = await fetch('check_request_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ email, student_id: studentId })
                    });
                    const data = await resp.json();
                    if (!data.success) {
                        statusResult.innerHTML = '<div style="color:#b91c1c">' + (data.message || 'Not found') + '</div>';
                        return;
                    }
                    const r = data.record;
                    let html = '<div style="padding:8px;border-radius:8px;background:#f8fafc;color:#111">';
                    html += '<strong>Status:</strong> ' + formatRequestStatus(r.status) + '<br>';
                    if (r.created_at) html += '<strong>Requested:</strong> ' + r.created_at + '<br>';
                    if (r.approved_at) html += '<strong>Approved at:</strong> ' + r.approved_at + '<br>';
                    if (r.approved_by) html += '<strong>Approved by (id):</strong> ' + r.approved_by + '<br>';
                    html += '</div>';
                    statusResult.innerHTML = html;
                } catch (err) {
                    statusResult.innerHTML = '<div style="color:#b91c1c">Error checking status.</div>';
                }
            });
        }

        document.getElementById('studentCourseSelect').addEventListener('change', handleCourseSelection);
        document.getElementById('ladderizeCourseSelect').addEventListener('change', handleCourseSelection);

        function handleCourseSelection() {
            const courseSelect = document.getElementById('studentCourseSelect');
            const ladderizeSelect = document.getElementById('ladderizeCourseSelect');
            const yearSelect = document.getElementById('studentYearSelect');
            const courseValue = courseSelect.value;
            const ladderizeValue = ladderizeSelect.value;

            if (courseValue) {
                ladderizeSelect.value = '';
                ladderizeSelect.disabled = true;
            } else {
                ladderizeSelect.disabled = false;
            }
            if (ladderizeValue) {
                courseSelect.value = '';
                courseSelect.disabled = true;
            } else {
                courseSelect.disabled = false;
            }

            populateYearOptions(courseValue || ladderizeValue);
        }

        function populateYearOptions(courseValue) {
            const yearSelect = document.getElementById('studentYearSelect');
            const twoYearPrograms = ['Associate in Computer Technology', 'Associate in Business Knowledge', 'Associate in Hospitality Management', 'Associate in Tourism Management'];
            const years = twoYearPrograms.includes(courseValue) ? [1, 2] : [1, 2, 3, 4];
            const currentValue = yearSelect.value;
            yearSelect.innerHTML = '<option value="">Select year</option>' + years.map(year => `
                <option value="${year}"${currentValue === String(year) ? ' selected' : ''}>${year}</option>
            `).join('');
        }

        document.querySelectorAll('.password-toggle').forEach(button => {
            button.addEventListener('click', function () {
                const targetId = this.dataset.target;
                const input = document.getElementById(targetId);
                if (!input) return;
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                const icon = this.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                }
            });
        });

        document.getElementById('studentIdInput').addEventListener('input', function () {
            this.value = formatStudentId(this.value);
        });

        if (statusStudentIdInput) {
            statusStudentIdInput.addEventListener('input', function () {
                this.value = formatStudentId(this.value);
            });
        }

        // Password Requirements Real-time Validation
        const passwordInput = document.getElementById('registerPassword');
        if (passwordInput) {
            function checkPasswordRequirements() {
                const password = passwordInput.value;
                
                const requirements = {
                    'req-length': password.length >= 9,
                    'req-upper': /[A-Z]/.test(password),
                    'req-lower': /[a-z]/.test(password),
                    'req-number': /[0-9]/.test(password)
                };

                Object.entries(requirements).forEach(([id, isMet]) => {
                    const element = document.getElementById(id);
                    if (element) {
                        element.classList.toggle('met', isMet);
                        element.classList.toggle('not-met', !isMet);
                    }
                });
            }

            passwordInput.addEventListener('input', checkPasswordRequirements);
            passwordInput.addEventListener('change', checkPasswordRequirements);
            
            // Initial check
            checkPasswordRequirements();
        }

        // Capitalize first letter of each word in name fields
        function capitalizeWords(value) {
            return value
                .split(' ')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                .join(' ');
        }

        const nameFields = ['firstNameInput', 'middleNameInput', 'lastNameInput'];
        nameFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('input', function () {
                    this.value = capitalizeWords(this.value);
                });
            }
        });

        // Swipe-to-Refresh Functionality (Mobile Only: 320px - 768px)
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    </script>
</body>
</html>
