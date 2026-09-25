<?php
require_once __DIR__ . '/includes/session.php';
$conn = get_db();

echo "<h1>Alumni Employment Data Sync - Complete Diagnostic</h1>";

// Check Alumni ID 4 data from all sources
$alumniId = 4;

echo "<h2>Alumni ID: $alumniId (Ley Harvie Barreto Peralta)</h2>";

// 1. Check ssaa_alumni table
echo "<h3>1. Data in ssaa_alumni (Main Table):</h3>";
$stmt = $conn->prepare("SELECT full_name, employment_status, company_name, industry, salary_range, job_description, location FROM ssaa_alumni WHERE id = ?");
$stmt->execute([$alumniId]);
$mainTable = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($mainTable);
echo "</pre>";

// 2. Check ssaa_alumni_survey_responses table (JSON)
echo "<h3>2. Data in ssaa_alumni_survey_responses (JSON Response):</h3>";
$stmt = $conn->prepare("SELECT response_data FROM ssaa_alumni_survey_responses WHERE alumni_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$alumniId]);
$response = $stmt->fetch(PDO::FETCH_ASSOC);
if ($response) {
    $json = json_decode($response['response_data'], true);
    $employmentFields = [];
    foreach ($json as $key => $val) {
        if (strpos($key, 'employment') !== false || strpos($key, 'company') !== false || 
            strpos($key, 'salary') !== false || strpos($key, 'industry') !== false || 
            strpos($key, 'job') !== false) {
            $employmentFields[$key] = $val;
        }
    }
    echo "<pre>";
    print_r($employmentFields);
    echo "</pre>";
} else {
    echo "<p>No response found</p>";
}

// 3. Check what Alumni Information tab query returns
echo "<h3>3. Alumni Information Tab Query (what admin sees):</h3>";
$stmt = $conn->prepare("
    SELECT 
        id, full_name, student_id, course, graduation_year,
        gender, civil_status, dob, nationality,
        personal_email, contact_number, permanent_address, location,
        employment_status, company_name AS company, job_title, 
        industry, salary_range, job_description
    FROM ssaa_alumni WHERE id = ?
");
$stmt->execute([$alumniId]);
$adminView = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($adminView);
echo "</pre>";

// 4. Verify sync status
echo "<h3>4. Sync Status Verification:</h3>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Field</th><th>In ssaa_alumni?</th><th>Value</th></tr>";

$fields = ['employment_status', 'company_name', 'industry', 'salary_range', 'job_description', 'location'];
foreach ($fields as $field) {
    $value = $mainTable[$field] ?? 'NULL';
    $status = (!empty($value) && $value !== 'NULL') ? '✓ YES' : '✗ EMPTY/NULL';
    echo "<tr>";
    echo "<td><strong>$field</strong></td>";
    echo "<td>$status</td>";
    echo "<td>" . htmlspecialchars($value) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>5. Summary:</h3>";
$hasEmploymentData = !empty($mainTable['employment_status']) && $mainTable['employment_status'] !== 'unknown';
if ($hasEmploymentData) {
    echo "<p style='color: green; font-size: 16px;'><strong>✓ EXCELLENT!</strong> Employment data IS syncing to ssaa_alumni table!</p>";
    echo "<p>Alumni Information tab and ssaa_student_home modal should now display:</p>";
    echo "<ul>";
    echo "<li>Employment Status: " . htmlspecialchars($mainTable['employment_status']) . "</li>";
    echo "<li>Company: " . htmlspecialchars($mainTable['company_name']) . "</li>";
    echo "<li>Industry: " . htmlspecialchars($mainTable['industry']) . "</li>";
    echo "<li>Salary Range: " . htmlspecialchars($mainTable['salary_range']) . "</li>";
    echo "<li>Job Description: " . htmlspecialchars($mainTable['job_description']) . "</li>";
    echo "<li>Location: " . htmlspecialchars($mainTable['location']) . "</li>";
    echo "</ul>";
} else {
    echo "<p style='color: red; font-size: 16px;'><strong>✗ PROBLEM:</strong> Employment data is NOT in ssaa_alumni table!</p>";
    echo "<p>The form might be saving only to JSON response, but NOT updating the main alumni table.</p>";
}
?>
