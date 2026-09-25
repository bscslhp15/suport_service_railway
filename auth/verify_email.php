<?php
require_once __DIR__ . '/../includes/functions.php';

$message = '';
$email = secure_input($_GET['email'] ?? '');
$role = secure_input($_GET['role'] ?? '');
$pendingSession = $email ? get_pending_registration($email) : null;
$pendingDb = $email ? get_db_pending_registration_by_email($email) : null;
$pending = is_pending_email_verification_active($pendingDb) ? $pendingDb : $pendingSession;
$sendOnLoad = isset($_GET['send']) && $_GET['send'] === '1';
$verificationCode = $pending['verification_code'] ?? '';
$verificationExpires = $pending['verification_expires'] ?? '';
$verificationExpiresTimestamp = $verificationExpires ? (new DateTime($verificationExpires))->getTimestamp() : null;
$userName = $pending['full_name'] ?? '';

if ($email && isset($_GET['action']) && $_GET['action'] === 'refresh' && $pending) {
    $verificationCode = generate_code();
    $verificationExpires = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
    $verificationExpiresTimestamp = (new DateTime($verificationExpires))->getTimestamp();
    if ($pendingDb) {
        update_db_pending_registration($pendingDb['id'], [
            'verification_code' => $verificationCode,
            'verification_expires' => $verificationExpires,
        ]);
    } else {
        $pending['verification_code'] = $verificationCode;
        $pending['verification_expires'] = $verificationExpires;
        set_pending_registration($pending);
    }

    header('Location: verify_email.php?email=' . urlencode($email) . '&role=' . urlencode($role) . '&send=1');
    exit;
}

if ($email && !$pending) {
    $message = 'No pending registration found for that email. Please register again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = secure_input($_POST['email'] ?? '');
    $code = secure_input($_POST['code'] ?? '');
    $pendingSession = $email ? get_pending_registration($email) : null;
    $pendingDb = $email ? get_db_pending_registration_by_email($email) : null;
    $pending = is_pending_email_verification_active($pendingDb) ? $pendingDb : $pendingSession;

    if (!$pending) {
        $message = 'No pending registration found for that email. Please register again.';
    } elseif ($pending['verification_code'] !== $code) {
        $message = 'Invalid verification code.';
    } elseif (empty($pending['verification_expires']) || new DateTime() > new DateTime($pending['verification_expires'])) {
        $message = 'This verification code has expired.';
    } else {
        if (find_user_by_email($email)) {
            $message = 'That email is already registered.';
        } else {
            if ($pendingDb) {
                update_db_pending_registration($pendingDb['id'], [
                    'email_verified' => 1,
                    'verification_code' => null,
                    'verification_expires' => null,
                    'status' => 'pending_admin',
                ]);
            } else {
                $pending['email_verified'] = 1;
                $pending['verification_code'] = null;
                $pending['verification_expires'] = null;
                $pending['status'] = 'pending_admin';
                save_db_pending_registration([
                    'full_name' => $pending['full_name'],
                    'email' => $pending['email'],
                    'role' => !empty($pending['role']) ? $pending['role'] : ($role ?: 'student'),
                    'student_id' => $pending['student_id'] ?? null,
                    'employee_id' => $pending['employee_id'] ?? null,
                    'course_year' => $pending['course_year'] ?? null,
                    'password_hash' => $pending['password_hash'],
                    'documents' => $pending['documents'] ?? [],
                    'email_verified' => 1,
                    'verification_code' => null,
                    'verification_expires' => null,
                    'is_head' => $pending['is_head'] ?? 0,
                    'head_service' => $pending['head_service'] ?? 'none',
                    'admin_type' => $pending['admin_type'] ?? null,
                    'status' => 'pending_admin',
                ]);
            }
            clear_pending_registration();
            if ($role === 'teacher') {
                header('Location: teacher_login.php?pending_approval=1');
            } elseif ($role === 'admin') {
                header('Location: admin_login.php?pending_approval=1');
            } else {
                header('Location: student_login.php?pending_approval=1');
            }
            exit;
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="verify-page">
    <div class="verify-container">
        <!-- Top Navigation -->
        <div class="verify-top-nav">
            <button type="button" class="verify-back-btn" onclick="window.history.back();">
                <i class="fas fa-arrow-left"></i>
                <span>Back to sign in</span>
            </button>
        </div>

        <!-- Main Heading Section -->
        <div class="verify-content">
            <div class="verify-header-section">
                <div class="verify-label-row">
                    <span class="verify-label">VERIFICATION</span>
                    <div class="verify-timer">
                        <i class="fas fa-clock"></i>
                        <span id="verificationCountdown">10:00</span>
                    </div>
                </div>
                <h1 class="verify-title">Check Your <span class="verify-title-mail">mail</span>.</h1>
                <p class="verify-description">A six-digit verification code is on its way to <strong><?= htmlspecialchars($email) ?></strong>. It expires in 10 minutes.</p>
            </div>

            <?php if ($message): ?><div class="alert <?= $message === 'Email verified successfully. You may now log in.' ? 'success' : 'error' ?>"><?= $message ?></div><?php endif; ?>

            <!-- Success message for email sent -->
            <div id="emailSentMessage" class="alert success" style="display: none;">
                <i class="fa-solid fa-check-circle"></i>
                Verification code sent successfully to <strong><?= htmlspecialchars($email) ?></strong>!
            </div>

            <!-- Input Card -->
            <div class="verify-input-card">
                <label class="verify-code-label">ENTER CODE</label>
                <form method="post" action="" class="verify-form" id="verifyForm">
                    <div class="code-inputs" id="codeInputs">
                        <?php for ($i = 0; $i < 6; $i++): ?>
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="code-digit" autocomplete="one-time-code" />
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                    <input type="hidden" name="code" id="codeInput">
                    <button type="submit" class="verify-submit-btn">Verify Code</button>
                </form>
            </div>

            <!-- Resend Section -->
            <div class="verify-resend-section">
                <p class="verify-resend-text">Didn't get it? Check spam, or <button type="button" id="resendCodeBtn" class="verify-resend-link">send a new code</button>.</p>
            </div>
        </div>
    </div>

    <script>
        window.emailVerifyData = {
            email: <?= json_encode($email) ?>,
            name: <?= json_encode($userName) ?>,
            code: <?= json_encode($verificationCode) ?>,
            expires: <?= json_encode($verificationExpiresTimestamp) ?>,
            sendOnLoad: <?= json_encode($sendOnLoad) ?>,
            serviceId: 'service_843t745',
            templateId: 'template_jb5tjxv',
            publicKey: 'rLeRK76s3p-IQn-X1'
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js"></script>
    <script src="../assets/js/email_verify.js"></script>
    <script src="../assets/js/app.js"></script>
</body>
</html>
