<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

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
    'employee_id' => '',
    'course' => '',
    'is_head' => false,
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
        $registerValues['employee_id'] = secure_input($_POST['employee_id'] ?? '');
        $registerValues['course'] = secure_input($_POST['course'] ?? '');
        $registerValues['is_head'] = isset($_POST['is_head']);
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $agreementAccepted = isset($_POST['agreement_consent']) && $_POST['agreement_consent'] === '1';
        $privacyAccepted = isset($_POST['privacy_consent']) && $_POST['privacy_consent'] === '1';

        if (!$agreementAccepted || !$privacyAccepted) {
            $message = 'You must accept the Agreement and Privacy Policy before requesting an account.';
            $messageClass = 'error';
            $consentError = true;
        } elseif (!$registerValues['first_name'] || !$registerValues['last_name'] || !$registerValues['email'] || !$registerValues['employee_id'] || !$registerValues['course'] || !$password || !$confirm_password) {
            $message = 'All fields are required.';
            $messageClass = 'error';
        } elseif (!filter_var($registerValues['email'], FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
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
        } elseif (is_pending_registration_blocking(get_pending_registration($registerValues['email'])) || is_pending_registration_blocking(get_db_pending_registration_by_email($registerValues['email']))) {
            $message = 'A verification email is already pending for this address.';
            $messageClass = 'error';
        } elseif (check_duplicate_full_name($registerValues['full_name'])) {
            $message = 'You cannot create an account with this name because someone with that name already exists in the system. Please visit the admin office to resolve this issue.';
            $messageClass = 'error';
        } else {
            $verification_code = generate_code();
            $expires = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            set_pending_registration([
                'full_name' => $registerValues['full_name'],
                'email' => $registerValues['email'],
                'username' => null,
                'role' => 'teacher',
                'course_year' => $registerValues['course'],
                'student_id' => null,
                'employee_id' => $registerValues['employee_id'],
                'is_head' => $registerValues['is_head'] ? 1 : 0,
                'head_service' => 'none',
                'email_verified' => 0,
                'verification_code' => $verification_code,
                'verification_expires' => $expires,
                'password_hash' => $password_hash,
                'admin_type' => null,
            ]);

            header('Location: verify_email.php?email=' . urlencode($registerValues['email']) . '&role=teacher&send=1');
            exit;
        }
    } else {
        $email = secure_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = find_user_by_email($email);
        if (!$user || $user['role'] !== 'teacher') {
            $message = 'Invalid teacher email or password.';
            $messageClass = 'error';
        } elseif (is_locked_out($user)) {
            $message = 'Account locked. Please try again later.';
            $messageClass = 'error';
        } elseif (!password_verify($password, $user['password_hash'])) {
            record_failed_login($user);
            $message = 'Invalid teacher email or password.';
            $messageClass = 'error';
        } elseif (!$user['email_verified']) {
            $message = 'Please verify your email before logging in.';
            $messageClass = 'error';
        } else {
            reset_failed_login($user);
            login_user($user);
            if ($user['head_service'] !== 'none') {
                header('Location: ../dashboard/head_home.php');
            } else {
                header('Location: ../dashboard/teacher_home.php');
            }
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

$carouselImages = get_carousel_images('teacher');
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
    <title>Employee Login | PASS College</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
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
            font-family: 'ElephantLocal', 'Cormorant Garamond', serif;
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
        .register-grid .form-actions.full-width {
            grid-column: 1 / -1;
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
        }
        .input-icon {
            display: none;
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
            /* Mirror student login responsive layout for tablet -> mobile */
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

            .auth-header img {
                width: 56px !important;
                height: 56px !important;
            }

            .auth-header-link {
                margin: 0 !important;
                padding: 6px 10px !important;
                border-radius: 0 !important;
            }

            .auth-header-title strong {
                font-size: 1.1rem !important;
            }

            .auth-card {
                width: calc(100vw - 24px) !important;
                margin: 90px auto 24px !important;
                border-radius: 20px !important;
                grid-template-columns: 1fr !important;
            }

            .auth-side {
                padding: 18px !important;
            }

            .panel-title { font-size: 1.8rem !important; }

            .auth-page { padding-top: 84px; }
        }

        @media (max-width: 375px) {
            .auth-header {
                padding: 8px 10px !important;
                gap: 6px !important;
                flex-wrap: wrap !important;
                align-items: flex-start !important;
            }

            .auth-header img {
                width: 44px !important;
                height: 44px !important;
            }

            .auth-header-link {
                width: 100% !important;
                min-width: 0 !important;
                align-items: flex-start !important;
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
        }

        @media (max-width: 320px) {
            .auth-header {
                padding: 8px 10px !important;
                flex-wrap: wrap !important;
                align-items: flex-start !important;
            }

            .auth-header img {
                width: 40px !important;
                height: 40px !important;
            }

            .auth-header-link {
                width: 100% !important;
                min-width: 0 !important;
                align-items: flex-start !important;
            }

            .auth-header-title {
                display: flex !important;
                flex-direction: column !important;
                gap: 1px !important;
                min-width: 0 !important;
                max-width: calc(100% - 48px) !important;
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

            .panel-title {
                font-size: 1.4rem !important;
            }

            .auth-page {
                padding-top: 75px;
            }
        }

        @media (max-width: 1024px) {
            .auth-card {
                grid-template-columns: 1fr;
                min-height: auto;
            }
            .auth-side {
                padding: 32px;
            }
            .auth-header {
                left: 16px;
                right: 16px;
            }
        }
        @media (max-width: 720px) {
            .auth-card {
                width: calc(100vw - 24px);
                margin: 80px auto 32px;
                border-radius: 28px;
            }
            .auth-side {
                padding: 28px 20px;
            }
            .panel-title { font-size: 1.8rem; }
        }
        .optional {
            font-size: 0.85rem;
            font-weight: 400;
            opacity: 0.8;
        }
        .password-requirements {
            margin-top: 12px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            display: grid;
            gap: 8px;
        }
        .requirement-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
        }
        .requirement-icon {
            font-size: 0.5rem;
            color: rgba(255, 255, 255, 0.3);
        }
        .requirement-item.met {
            color: rgba(34, 197, 94, 0.9);
        }
        .requirement-item.met .requirement-icon {
            color: rgba(34, 197, 94, 0.8);
        }
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 12px 0;
        }
        .checkbox-wrapper input[type="checkbox"] {
            appearance: none;
            width: 18px;
            height: 18px;
            border: 1.5px solid rgba(212, 175, 55, 0.5);
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.08);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .checkbox-wrapper input[type="checkbox"]:checked {
            background: rgba(212, 175, 55, 0.8);
            border-color: rgba(212, 175, 55, 0.9);
        }
        .checkbox-wrapper input[type="checkbox"]:checked::after {
            content: '✓';
            display: block;
            color: #800000;
            font-size: 0.9rem;
            text-align: center;
            line-height: 1;
        }
        .checkbox-wrapper label {
            color: rgba(255, 255, 255, 0.9);
            cursor: pointer;
            font-weight: 500;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease;
        }
        .modal-overlay.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-content {
            background: rgba(128, 0, 0, 0.96);
            border: 1px solid rgba(212, 175, 55, 0.25);
            border-radius: 24px;
            padding: 32px;
            width: min(100%, 480px);
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
            color: #fff;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }
        .modal-close {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-close:hover {
            color: #fff;
        }
        .modal-body {
            margin-bottom: 24px;
        }
        .modal-footer {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        .modal-field {
            margin-bottom: 16px;
        }
        .modal-field label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .modal-field input {
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 12px;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s ease;
        }
        .modal-field input:focus {
            border-color: rgba(212, 175, 55, 0.5);
            background: rgba(255, 255, 255, 0.12);
        }
        .modal-field input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        .btn-modal-primary {
            background: #D4AF37;
            color: #800000;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-modal-primary:hover {
            background: #b58f31;
            transform: translateY(-1px);
        }
        .btn-modal-secondary {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.18);
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-modal-secondary:hover {
            background: rgba(255, 255, 255, 0.18);
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
            <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
            <div class="auth-header-title">
                <strong>PASS COLLEGE</strong>
                <span>Employee Support Portal</span>
            </div>
        </header>

        <main class="auth-card<?= $authMode === 'register' ? ' register-mode' : '' ?>">
            <section class="auth-side auth-side-left">
                <div class="panel panel-content login-only">
                    <h1 class="panel-title"><i class="fa-solid fa-right-to-bracket panel-icon"></i> Welcome Back to PASS College</h1>
                    <p class="panel-text">Experience a secure Employee portal for class management, grade tracking, and institutional resources. Login now or request a new teacher account.</p>
                    <div class="panel-actions">
                        <button type="button" class="btn-action" id="show-register">Request Employee Account</button>
                        <button type="button" class="btn-action" id="check-request-status" style="background:#fff;color:#800000;border:1px solid rgba(0,0,0,0.08);">Check Request Status</button>
                    </div>
                </div>
                <div class="panel panel-content register-only">
                    <h1 class="panel-title"><i class="fa-solid fa-user-plus panel-icon"></i> Request Employee Account</h1>
                    <p class="panel-text">Register now and get access to teaching tools, gradebooks, and faculty resources for PASS College educators.</p>
                    <?php if ($message && $authMode === 'register'): ?><div class="alert <?= $messageClass ?>"><?= $message ?></div><?php endif; ?>
                    <form method="post" action="" class="form-panel register-grid registration-form">
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
                            <label for="employeeIdInput"><i class="fa-solid fa-id-card label-icon"></i> Employee ID</label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-id-card input-icon"></i>
                                <input id="employeeIdInput" type="text" name="employee_id" value="<?= htmlspecialchars($registerValues['employee_id']) ?>" placeholder="Enter your employee ID" required>
                            </div>
                        </div>
                        <div class="field-group full-width">
                            <label for="teacherCourseSelect"><i class="fa-solid fa-book label-icon"></i> Department/Course</label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-book input-icon"></i>
                                <select id="teacherCourseSelect" name="course" required>
                                    <option value="">Select department/course</option>
                                    <?php foreach ($courseOptions as $course): ?>
                                        <option value="<?= htmlspecialchars($course) ?>"<?= $registerValues['course'] === $course ? ' selected' : '' ?>><?= htmlspecialchars($course) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="checkbox-wrapper full-width" style="grid-column: 1 / -1;">
                            <input id="isHeadInput" type="checkbox" name="is_head" value="1"<?= $registerValues['is_head'] ? ' checked' : '' ?>>
                            <label for="isHeadInput">I am a Department Head</label>
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
                            <button type="submit" class="btn-primary">Request Employee Account</button>
                            <p class="link-text">Already have an account? <button type="button" class="btn-link" id="show-login">Login</button></p>
                        </div>
                    </form>
                </div>
            </section>

            <section class="auth-side auth-side-right">
                <div class="panel panel-content login-only">
                    <h1 class="panel-title"><i class="fa-solid fa-right-to-bracket panel-icon"></i> Employee Login</h1>
                    <?php if ($message && $authMode === 'login'): ?><div class="alert <?= $messageClass ?>"><?= $message ?></div><?php endif; ?>
                    <form method="post" action="" class="form-panel">
                        <input type="hidden" name="action" value="login">
                        <div class="field-group">
                            <label for="loginEmail"><i class="fa-solid fa-envelope label-icon"></i> Email</label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-envelope input-icon"></i>
                                <input id="loginEmail" type="email" name="email" placeholder="Enter your teacher email" autocomplete="email" required>
                            </div>
                        </div>
                        <div class="field-group">
                            <label for="loginPassword"><i class="fa-solid fa-lock label-icon"></i> Password</label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-lock input-icon"></i>
                                <input id="loginPassword" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
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
                    <p class="panel-text">If you're already registered, return to the login page and continue your teacher access.</p>
                    <div class="panel-actions">
                        <button type="button" class="btn-action" id="show-login-2">Back to Login</button>
                    </div>
                </div>
            </section>
        </main>

        <?php include __DIR__ . '/../includes/privacy_consent.php'; ?>

        <!-- Check Request Status Modal -->
        <div class="modal-overlay" id="checkRequestModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title"><i class="fa-solid fa-search"></i> Check Request Status</h2>
                    <button class="modal-close" data-close="checkRequestModal">&times;</button>
                </div>
                <div class="modal-body">
                    <p style="margin-top:0; font-size:0.95rem; opacity:0.9;">Enter your email or employee ID to check the status of your teacher account request.</p>
                    <form id="checkRequestForm">
                        <div class="modal-field">
                            <label for="requestCheckInput">Email or Employee ID</label>
                            <input id="requestCheckInput" type="text" placeholder="Enter your email or employee ID" required>
                        </div>
                    </form>
                    <div id="requestStatusResult" style="display:none; padding:12px; background:rgba(34,197,94,0.15); border-radius:8px; color:rgba(34,197,94,0.9); font-size:0.9rem; margin-top:16px;"></div>
                </div>
                <div class="modal-footer">
                    <button class="btn-modal-secondary" data-close="checkRequestModal">Close</button>
                    <button class="btn-modal-primary" id="checkRequestBtn">Check Status</button>
                </div>
            </div>
        </div>

        <!-- Forgot Password Modal -->
        <div class="modal-overlay" id="forgotPasswordModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title"><i class="fa-solid fa-key"></i> Reset Password</h2>
                    <button class="modal-close" data-close="forgotPasswordModal">&times;</button>
                </div>
                <div class="modal-body">
                    <p style="margin-top:0; font-size:0.95rem; opacity:0.9;">Enter your email and follow the OTP steps to reset your password.</p>
                    <div id="resetStepEmail" style="display:grid;gap:10px;margin-bottom:12px;">
                        <input id="resetEmail" type="email" placeholder="Teacher email" style="padding:12px;border:1px solid #ddd;border-radius:10px;">
                        <button type="button" id="sendResetOtp" class="btn-primary" style="width:100%;">Send Verification Code</button>
                    </div>
                    <div id="resetStepOtp" style="display:none;gap:10px;margin-bottom:12px;">
                        <label style="font-size:0.95rem;font-weight:600;color:#111;">Enter 6-digit code</label>
                        <div id="resetOtpInputs" style="display:grid;grid-template-columns:repeat(6,1fr);gap:5px;margin-bottom:10px;max-width:100%;">
                            <?php for ($i = 0; $i < 6; $i++): ?>
                                <input type="text" class="reset-otp-digit" inputmode="numeric" pattern="[0-9]*" maxlength="1" style="padding:6px;border:1px solid #ddd;border-radius:6px;text-align:center;font-size:0.95rem;font-weight:600;width:100%;box-sizing:border-box;" />
                            <?php endfor; ?>
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
                </div>
                <div class="modal-footer">
                    <button class="btn-modal-secondary" data-close="forgotPasswordModal">Close</button>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js"></script>
    <script>
        const authCard = document.querySelector('.auth-card');
        const registerTriggers = document.querySelectorAll('#show-register');
        const loginTriggers = document.querySelectorAll('#show-login, #show-login-2');
        const slides = document.querySelectorAll('.background-slide');
        let slideIndex = 0;

        function switchMode(mode) {
            if (mode === 'register') {
                authCard.classList.add('register-mode');
                history.replaceState(null, null, '?mode=register');
            } else {
                authCard.classList.remove('register-mode');
                history.replaceState(null, null, window.location.pathname);
            }
        }

        registerTriggers.forEach(btn => {
            btn.addEventListener('click', () => switchMode('register'));
        });

        loginTriggers.forEach(btn => {
            btn.addEventListener('click', () => switchMode('login'));
        });

        function nextSlide() {
            slides[slideIndex].classList.remove('active');
            slideIndex = (slideIndex + 1) % slides.length;
            slides[slideIndex].classList.add('active');
        }

        setInterval(nextSlide, 4000);

        // Password toggle
        document.querySelectorAll('.password-toggle').forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.dataset.target;
                const input = document.getElementById(targetId);
                if (input) {
                    const type = input.type === 'password' ? 'text' : 'password';
                    input.type = type;
                    this.innerHTML = type === 'password' ? '<i class="fa-solid fa-eye"></i>' : '<i class="fa-solid fa-eye-slash"></i>';
                }
            });
        });

        // Password requirements validation
        const registerPasswordInput = document.getElementById('registerPassword');
        if (registerPasswordInput) {
            registerPasswordInput.addEventListener('input', function() {
                const password = this.value;
                const requirements = {
                    'req-length': password.length >= 9,
                    'req-upper': /[A-Z]/.test(password),
                    'req-lower': /[a-z]/.test(password),
                    'req-number': /[0-9]/.test(password),
                };
                
                Object.entries(requirements).forEach(([id, met]) => {
                    const elem = document.getElementById(id);
                    if (met) {
                        elem.classList.add('met');
                    } else {
                        elem.classList.remove('met');
                    }
                });
            });
        }

        // Name capitalization
        function capitalizeWords(value) {
            return value.split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()).join(' ');
        }

        const nameFields = ['firstNameInput', 'middleNameInput', 'lastNameInput'];
        nameFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('input', function() {
                    this.value = capitalizeWords(this.value);
                });
            }
        });

        // Modal handling
        document.querySelectorAll('[data-close]').forEach(btn => {
            btn.addEventListener('click', function() {
                const modalId = this.dataset.close;
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.remove('active');
                }
            });
        });

        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        // Check request status
        document.getElementById('check-request-status').addEventListener('click', function() {
            document.getElementById('checkRequestModal').classList.add('active');
        });

        document.getElementById('checkRequestBtn').addEventListener('click', async function() {
            const input = document.getElementById('requestCheckInput').value.trim();
            if (!input) return;

            try {
                const isEmail = input.includes('@');
                const response = await fetch('check_request_status.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(isEmail ? {email: input} : {employee_id: input})
                });
                const data = await response.json();
                const resultDiv = document.getElementById('requestStatusResult');
                resultDiv.style.display = 'block';
                if (!data.success) {
                    resultDiv.textContent = data.message || 'No pending request found for this email or employee ID.';
                    return;
                }

                const record = data.record;
                resultDiv.textContent = 'Status: ' + (record.status || 'Pending') +
                    (record.created_at ? '\nRequested: ' + record.created_at : '');
            } catch (err) {
                console.error('Error:', err);
                document.getElementById('requestStatusResult').textContent = 'Error checking request status.';
            }
        });

        const passwordResetConfig = {
            serviceId: 'service_843t745',
            templateId: 'template_jb5tjxv',
            publicKey: 'rLeRK76s3p-IQn-X1'
        };

        // Forgot password modal wiring
        const forgotPasswordBtn = document.getElementById('forgotPasswordBtn');
        const forgotPasswordModal = document.getElementById('forgotPasswordModal');
        const closeForgotModal = document.querySelector('[data-close="forgotPasswordModal"]');
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
            if (step === 'otp') resetOtpDigits[0].focus();
        }

        function openForgotPasswordModal() {
            forgotPasswordModal.classList.add('active');
            resetEmailInput.value = '';
            resetOtpDigits.forEach(input => input.value = '');
            resetOtpCode.value = '';
            resetNewPasswordInput.value = '';
            resetConfirmPasswordInput.value = '';
            showResetStep('email');
        }

        resetOtpDigits.forEach((input, index) => {
            input.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '').slice(-1);
                if (this.value && index < resetOtpDigits.length - 1) resetOtpDigits[index + 1].focus();
            });
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Backspace' && !this.value && index > 0) resetOtpDigits[index - 1].focus();
            });
        });

        function assembleResetOtp() {
            const code = Array.from(resetOtpDigits).map(input => input.value.trim()).join('');
            resetOtpCode.value = code;
            return code;
        }

        async function sendPasswordResetOtp(email) {
            const response = await fetch('password_reset_send_otp.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email})
            });
            return response.json();
        }

        async function verifyPasswordResetOtp(email, code) {
            const response = await fetch('password_reset_verify_otp.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email, code})
            });
            return response.json();
        }

        async function completePasswordReset(email, code, password, confirmPassword) {
            const response = await fetch('password_reset_complete.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({email, code, password, confirm_password: confirmPassword})
            });
            return response.json();
        }

        forgotPasswordBtn.addEventListener('click', openForgotPasswordModal);
        closeForgotModal.addEventListener('click', () => forgotPasswordModal.classList.remove('active'));

        sendResetOtp.addEventListener('click', async () => {
            const email = resetEmailInput.value.trim();
            if (!email) {
                resetFeedback.textContent = 'Please enter your teacher email.';
                resetFeedback.style.color = '#b91c1c';
                return;
            }
            resetFeedback.textContent = 'Sending code...';
            resetFeedback.style.color = '#111';
            try {
                const result = await sendPasswordResetOtp(email);
                if (!result.success) throw new Error(result.message || 'Unable to send code.');
                if (typeof emailjs !== 'undefined') {
                    emailjs.init(passwordResetConfig.publicKey);
                    await emailjs.send(passwordResetConfig.serviceId, passwordResetConfig.templateId, {
                        user_email: email, to_email: email, email, name: email, otp_code: result.code
                    });
                }
                resetFeedback.textContent = 'Verification code sent. Check your email.';
                resetFeedback.style.color = '#0f766e';
                showResetStep('otp');
            } catch (err) {
                resetFeedback.textContent = err.message || 'Failed to send code. Please try again.';
                resetFeedback.style.color = '#b91c1c';
            }
        });

        verifyResetOtp.addEventListener('click', async () => {
            const email = resetEmailInput.value.trim();
            const code = assembleResetOtp();
            if (code.length !== 6) {
                resetFeedback.textContent = 'Enter all 6 digits.';
                resetFeedback.style.color = '#b91c1c';
                return;
            }
            resetFeedback.textContent = 'Verifying code...';
            resetFeedback.style.color = '#111';
            try {
                const result = await verifyPasswordResetOtp(email, code);
                if (!result.success) throw new Error(result.message || 'Invalid code.');
                resetFeedback.textContent = 'Code verified. You may now change your password.';
                resetFeedback.style.color = '#0f766e';
                showResetStep('password');
            } catch (err) {
                resetFeedback.textContent = err.message || 'Verification failed.';
                resetFeedback.style.color = '#b91c1c';
            }
        });

        changePasswordBtn.addEventListener('click', async () => {
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
                const result = await completePasswordReset(resetEmailInput.value.trim(), resetOtpCode.value, password, confirmPassword);
                if (!result.success) throw new Error(result.message || 'Unable to reset password.');
                resetFeedback.textContent = 'Password changed successfully. You may now log in.';
                resetFeedback.style.color = '#0f766e';
                setTimeout(() => forgotPasswordModal.classList.remove('active'), 1800);
            } catch (err) {
                resetFeedback.textContent = err.message || 'Error changing password.';
                resetFeedback.style.color = '#b91c1c';
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
