<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();
if ($user['role'] !== 'teacher' || $user['head_service'] === 'none') {
    header('Location: ../auth/teacher_login.php');
    exit;
}
$redirectMap = [
    'library' => 'librarian_home.php',
    'clinic' => 'nurse_home.php',
    'ssc_scholarship' => 'ssc_head_home.php',
    'guidance' => 'guidance_home.php',
];
$target = $redirectMap[$user['head_service']] ?? null;
if ($target) {
    header('Location: ' . $target);
    exit;
}
header('Location: ../auth/teacher_login.php');
exit;
