<?php
require_once __DIR__ . '/../includes/functions.php';

$step = $_GET['step'] ?? 'request';
$message = '';
$email = secure_input($_POST['email'] ?? $_GET['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['request_code'])) {
        $email = secure_input($_POST['email'] ?? '');
        $user = find_user_by_email($email);
        if (!$user) {
            $message = 'No account found for that email.';
        } else {
            $reset_code = generate_code();
            $expires = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s');
            update_user([
                'id' => $user['id'],
                'reset_code' => $reset_code,
                'reset_expires' => $expires,
            ]);
            $subject = 'PASS Password Reset Code';
            $body = "<p>Hello {$user['full_name']},</p><p>Your reset code is <strong>{$reset_code}</strong>.</p><p>Use it to reset your password.</p>";
            send_email($email, $subject, $body);
            $message = 'Reset instructions were sent to your email.';
            $step = 'reset';
        }
    } elseif (isset($_POST['reset_password'])) {
        $email = secure_input($_POST['email'] ?? '');
        $code = secure_input($_POST['reset_code'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $user = find_user_by_email($email);

        if (!$user || $user['reset_code'] !== $code) {
            $message = 'Invalid reset code.';
        } elseif (empty($user['reset_expires']) || new DateTime() > new DateTime($user['reset_expires'])) {
            $message = 'The reset code has expired.';
        } elseif (!$password || $password !== $confirm_password) {
            $message = 'Passwords must match.';
        } else {
            update_user([
                'id' => $user['id'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'reset_code' => null,
                'reset_expires' => null,
            ]);
            $message = 'Your password has been updated. You may now log in.';
            $step = 'complete';
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Reset | PASS Support System</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card register-card">
            <div class="auth-image" style="background: linear-gradient(180deg, #0f172a, #1e293b);"></div>
            <div class="auth-form">
                <h2>Password Reset</h2>
                <?php if ($message): ?><div class="alert <?= strpos($message, 'sent') !== false || strpos($message, 'updated') !== false ? 'success' : 'error' ?>"><?= $message ?></div><?php endif; ?>
                <?php if ($step === 'request'): ?>
                    <form method="post" action="">
                        <label>Email</label>
                        <input type="email" name="email" required>
                        <button type="submit" name="request_code">Send reset code</button>
                    </form>
                <?php elseif ($step === 'reset'): ?>
                    <form method="post" action="">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
                        <label>Reset Code</label>
                        <input type="text" name="reset_code" required>
                        <label>New Password</label>
                        <input type="password" name="password" required>
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" required>
                        <button type="submit" name="reset_password">Reset password</button>
                    </form>
                <?php else: ?>
                    <p>Your password has been reset.</p>
                    <p><a href="student_login.php">Student login</a> | <a href="teacher_login.php">Teacher login</a> | <a href="admin_login.php">Admin login</a></p>
                <?php endif; ?>
                <p><a href="student_login.php">Back to login options</a></p>
            </div>
        </div>
    </div>
    <script src="../assets/js/app.js"></script>
</body>
</html>
