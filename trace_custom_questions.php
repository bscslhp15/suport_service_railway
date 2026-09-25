<?php
require_once 'includes/db.php';

echo "<h1>🔍 Alumni Tracer Form - Complete Debug Trace</h1>";

$conn = get_db();

// Test with alumni_id=7 (employed)
$testAlumniId = 7;

echo "<h2>Step 1: Load Alumni Data</h2>";
$stmt = $conn->prepare("SELECT id, full_name, employment_status FROM ssaa_alumni WHERE id = ?");
$stmt->execute([$testAlumniId]);
$alumniData = $stmt->fetch(PDO::FETCH_ASSOC);

if ($alumniData) {
    echo "<p>✅ Alumni found: <strong>" . $alumniData['full_name'] . "</strong></p>";
    echo "<p>Employment Status: <strong>" . ($alumniData['employment_status'] ?: '(empty)') . "</strong></p>";
} else {
    echo "<p style='color:red;'>❌ Alumni not found</p>";
    exit;
}

echo "<hr>";
echo "<h2>Step 2: Simulate alumni_tracer_form.php Logic</h2>";

// Simulate what alumni_tracer_form.php does
$employmentStatus = $alumniData['employment_status'] ?? null;

echo "<p>Employment Status from alumni data: <strong>" . ($employmentStatus ?: '(null)') . "</strong></p>";

if ($employmentStatus) {
    echo "<p>Status is set. Querying for employment_status IN ('all', ?)...</p>";
    $customQuestionsStmt = $conn->prepare("
        SELECT id, question_text, question_type, answer_choices, is_default, is_removable
        FROM ssaa_alumni_survey_questions
        WHERE employment_status IN ('all', ?)
        ORDER BY is_default DESC, order_index ASC
    ");
    $customQuestionsStmt->execute([$employmentStatus]);
} else {
    echo "<p>Status is NULL. Querying for employment_status = 'all'...</p>";
    $customQuestionsStmt = $conn->prepare("
        SELECT id, question_text, question_type, answer_choices, is_default, is_removable
        FROM ssaa_alumni_survey_questions
        WHERE employment_status = 'all'
        ORDER BY is_default DESC, order_index ASC
    ");
    $customQuestionsStmt->execute();
}

$customQuestions = $customQuestionsStmt->fetchAll(PDO::FETCH_ASSOC);
echo "<p>Questions fetched from database: <strong>" . count($customQuestions) . "</strong></p>";

if (!empty($customQuestions)) {
    echo "<table border='1' cellpadding='10' style='width: 100%;'>";
    echo "<tr><th>ID</th><th>Question</th><th>Type</th><th>is_default</th><th>is_removable</th></tr>";
    foreach ($customQuestions as $q) {
        echo "<tr>";
        echo "<td>" . $q['id'] . "</td>";
        echo "<td>" . htmlspecialchars(substr($q['question_text'], 0, 40)) . "...</td>";
        echo "<td>" . $q['question_type'] . "</td>";
        echo "<td>" . ($q['is_default'] ? '✓' : '✗') . "</td>";
        echo "<td style='background:" . ($q['is_removable'] ? '#90EE90' : '#FFB6C6') . ";'>" . ($q['is_removable'] ? '✓ YES' : '✗ NO') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<h2>Step 3: Apply Filter (is_removable == 1)</h2>";

$customQuestions = array_filter($customQuestions, function($q) {
    return $q['is_removable'] == 1;
});

echo "<p>After filtering: <strong>" . count($customQuestions) . "</strong> questions remain</p>";

if (count($customQuestions) > 0) {
    echo "<p style='color: green; background: #e6ffe6; padding: 10px;'>";
    echo "✅ <strong>Questions should be visible in alumni_tracer_form.php</strong>";
    echo "</p>";
    
    echo "<p>Questions that will display:</p>";
    echo "<ul>";
    foreach ($customQuestions as $q) {
        echo "<li>ID " . $q['id'] . ": " . htmlspecialchars($q['question_text']) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red; background: #ffe6e6; padding: 10px;'>";
    echo "❌ <strong>NO questions passed the filter!</strong>";
    echo "</p>";
    echo "<p>This is why 'Additional Questions' section doesn't show.</p>";
}

echo "<hr>";
echo "<h2>Step 4: Direct Test - Open Form</h2>";
echo "<p><a href='alumni_tracer_form.php?alumni_id=" . $testAlumniId . "' target='_blank' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>";
echo "→ Open Alumni Tracer Form for Alumni ID " . $testAlumniId;
echo "</a></p>";
echo "<p><strong>After clicking the link above:</strong></p>";
echo "<ol>";
echo "<li>Scroll down past 'Employment Details' section</li>";
echo "<li>Look for 'Additional Questions' section</li>";
echo "<li>If not visible, check browser console (F12 → Console tab)</li>";
echo "<li>Look for DEBUG log messages</li>";
echo "</ol>";

?>
