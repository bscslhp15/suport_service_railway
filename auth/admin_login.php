<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

$message = '';
$messageClass = 'error';
if (!empty($_GET['verified'])) {
    $message = 'Email verified successfully. You may now log in.';
    $messageClass = 'success';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = secure_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = find_user_by_email($email);
    if (!$user || $user['role'] !== 'admin') {
        $message = 'Invalid admin email or password.';
    } elseif (is_locked_out($user)) {
        $message = 'Account locked. Please try again later.';
    } elseif (!password_verify($password, $user['password_hash'])) {
        record_failed_login($user);
        $message = 'Invalid admin email or password.';
    } elseif (!$user['email_verified']) {
        $message = 'Please verify your email before logging in.';
    } else {
        reset_failed_login($user);
        login_user($user);
        header('Location: ../dashboard/admin_home.php');
        exit;
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Admin Login | PASS Support System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <div class="auth-page admin-login">
        <div class="auth-layout">
            <div class="auth-left-card">
                <div class="auth-left">
                    <div class="auth-branding">
                        <div class="logo-section">
                            <img class="auth-logo" src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                            <span class="college-name">PASS COLLEGE</span>
                        </div>
                        <h2><span class="admin-text">Admin</span> Portal</h2>
                        <p>Welcome to the central command center for system-wide controls and service head assignments.</p>
                    </div>
                    <div class="status-panel">
                        <span class="status-badge status-online"><span></span>System Online</span>
                        <span class="status-badge status-secure"><span></span>Secure Access</span>
                    </div>
                </div>
            </div>
            <div class="auth-right-card">
                <div class="auth-right">
                    <div class="auth-form-header">
                        <h1>Restricted Access</h1>
                        <h2>Sign in to continue</h2>
                        <p class="auth-description">Enter your administrator credentials below.</p>
                    </div>
                    <?php if ($message): ?><div class="alert <?= $messageClass ?>"><?= $message ?></div><?php endif; ?>
                    <form method="post" action="">
                        <div class="form-field">
                            <label for="email" class="field-label">Email Address</label>
                            <div class="input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" placeholder="admin@pass.edu.ph" required>
                            </div>
                        </div>
                        <div class="form-field">
                            <div class="password-label-row">
                                <label for="password" class="field-label">Password</label>
                                <a href="reset_password.php" class="forgot-password-link">Forgot password?</a>
                            </div>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="password" name="password" placeholder="••••••••" required>
                                <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                            </div>
                        </div>
                        <button type="submit">Login</button>
                    </form>
                    <div class="auth-footer">
                        <p class="request-access">Need an admin account? <a href="admin_register.php">Request access</a></p>
                        <p class="alternative-logins">
                            <a href="../auth/student_login.php">Student login</a> • 
                            <a href="../auth/teacher_login.php">Teacher login</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/app.js"></script>
</body>
</html>
