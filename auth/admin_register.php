<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/session.php';

$message = '';
$canRegister = false;
if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $canRegister = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canRegister) {
    $full_name = secure_input($_POST['full_name'] ?? '');
    $email = secure_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$full_name || !$email || !$password || !$confirm_password) {
        $message = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
    } elseif ($password !== $confirm_password) {
        $message = 'Password and confirm password do not match.';
    } elseif (find_user_by_email($email)) {
        $message = 'That email is already registered.';
    } elseif ($pending = get_pending_registration($email)) {
        if (empty($pending['verification_expires']) || new DateTime() > new DateTime($pending['verification_expires'])) {
            $verification_code = generate_code();
            $expires = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $pending = [
                'full_name' => $full_name,
                'email' => $email,
                'username' => null,
                'role' => 'admin',
                'course_year' => null,
                'student_id' => null,
                'employee_id' => null,
                'is_head' => 0,
                'head_service' => 'none',
                'email_verified' => 0,
                'verification_code' => $verification_code,
                'verification_expires' => $expires,
                'password_hash' => $password_hash,
                'admin_type' => 'regular',
            ];
            set_pending_registration($pending);
            header('Location: verify_email.php?email=' . urlencode($email) . '&role=admin&send=1');
            exit;
        }

        header('Location: verify_email.php?email=' . urlencode($email) . '&role=admin&send=1');
        exit;
    } else {
        $verification_code = generate_code();
        $expires = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        set_pending_registration([
            'full_name' => $full_name,
            'email' => $email,
            'username' => null,
            'role' => 'admin',
            'course_year' => null,
            'student_id' => null,
            'employee_id' => null,
            'is_head' => 0,
            'head_service' => 'none',
            'email_verified' => 0,
            'verification_code' => $verification_code,
            'verification_expires' => $expires,
            'password_hash' => $password_hash,
            'admin_type' => 'regular',
        ]);

        header('Location: verify_email.php?email=' . urlencode($email) . '&role=admin');
        exit;
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Registration | PASS Support System</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-left">
                <img class="auth-logo" src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                <h2>Admin Registration</h2>
                <p>Only logged-in admins may register additional admin accounts. This page matches the rest of the login/register flow.</p>
                <div class="intro-links">
                    <a href="admin_register.php">Admin</a>
                    <a href="admin_login.php">Login</a>
                </div>
            </div>
            <div class="auth-right">
                <h2>Admin Registration</h2>
                <?php if ($message): ?><div class="alert error"><?= $message ?></div><?php endif; ?>
                <?php if (!$canRegister): ?>
                    <p>You must be logged in as an admin to register a new admin account.</p>
                    <div class="auth-footer">
                        <p><a href="admin_login.php">Back to admin login</a></p>
                    </div>
                <?php else: ?>
                    <form method="post" action="">
                        <label>Full Name</label>
                        <input type="text" name="full_name" required>
                        <label>Email</label>
                        <input type="email" name="email" required>
                        <label>Password</label>
                        <input type="password" name="password" required>
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" required>
                        <button type="submit">Register Admin</button>
                    </form>
                    <div class="auth-footer">
                        <p><a href="admin_login.php">Back to admin login</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="../assets/js/app.js"></script>
    <script>
        // localStorage form data persistence for admin registration
        const STORAGE_KEY = 'adminRegistrationForm';
        const registerForm = document.querySelector('.form-panel');
        const registerInputs = registerForm ? registerForm.querySelectorAll('input[name], select[name]') : [];

        // Restore form data on page load
        const restoreFormData = function () {
            const savedData = localStorage.getItem(STORAGE_KEY);
            if (savedData) {
                try {
                    const data = JSON.parse(savedData);
                    registerInputs.forEach(function (input) {
                        if (data[input.name] !== undefined) {
                            input.value = data[input.name];
                        }
                    });
                } catch (e) {
                    console.error('Failed to restore form data:', e);
                }
            }
        };

        // Save form data as user types
        const saveFormData = function () {
            if (!registerForm) return;
            const formData = {};
            registerInputs.forEach(function (input) {
                if (input.name) {
                    formData[input.name] = input.value;
                }
            });
            localStorage.setItem(STORAGE_KEY, JSON.stringify(formData));
        };

        // Clear localStorage after successful registration
        if (registerForm) {
            registerForm.addEventListener('submit', function (e) {
                setTimeout(function () {
                    localStorage.removeItem(STORAGE_KEY);
                }, 100);
            });

            // Save on input change
            registerInputs.forEach(function (input) {
                input.addEventListener('input', saveFormData);
                input.addEventListener('change', saveFormData);
            });
        }

        // Restore on page load
        restoreFormData();
    </script>
</body>
</html>
