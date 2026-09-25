<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();

if ($user['role'] !== 'teacher' || $user['head_service'] !== 'guidance') {
    header('Location: guidance_home.php');
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
ensure_guidance_schema();

$pdo = get_db();
$message = '';
$messageType = 'success';
$studentSearchResult = null;
$studentCases = [];

// Debug: Log all POST data
if (!empty($_POST)) {
    error_log("POST data received: " . print_r($_POST, true));
}

// Search for student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_student'])) {
    $studentSearch = trim($_POST['student_search'] ?? '');
    error_log("Search term: '$studentSearch'");

    if (!empty($studentSearch)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (student_id LIKE ? OR full_name LIKE ?) AND role = 'student' LIMIT 1");
        $stmt->execute(['%' . $studentSearch . '%', '%' . $studentSearch . '%']);
        $studentSearchResult = $stmt->fetch(PDO::FETCH_ASSOC);

        error_log("Search result: " . ($studentSearchResult ? $studentSearchResult['full_name'] : 'null'));

        if ($studentSearchResult) {
            // Get all cases for this student (both solved and unsolved)
            $stmt = $pdo->prepare("
                SELECT gc.*, u_reporter.full_name as reporter_name
                FROM guidance_cases gc
                LEFT JOIN users u_reporter ON gc.reporter_user_id = u_reporter.id
                WHERE gc.reported_student_id = ?
                ORDER BY gc.created_at DESC
            ");
            $stmt->execute([$studentSearchResult['id']]);
            $studentCases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $message = 'Student not found.';
            $messageType = 'error';
        }
    } else {
        $message = 'Please enter a search term.';
        $messageType = 'error';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Debug Search</title>
</head>
<body>
    <h1>Debug Search Test</h1>

    <?php if ($message !== ''): ?>
        <div style="color: <?= $messageType === 'error' ? 'red' : 'green' ?>; padding: 10px; border: 1px solid;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="search_student" value="1">
        <label>Student ID or Name:</label>
        <input type="text" name="student_search" placeholder="Enter student name or ID" required />
        <button type="submit">Search</button>
    </form>

    <?php if ($studentSearchResult): ?>
        <h2>Found Student:</h2>
        <p>Name: <?= htmlspecialchars($studentSearchResult['full_name']) ?></p>
        <p>ID: <?= htmlspecialchars($studentSearchResult['student_id']) ?></p>
        <p>Cases: <?= count($studentCases) ?></p>
    <?php endif; ?>

    <p><a href="good_moral.php">Back to Good Moral</a></p>
</body>
</html>