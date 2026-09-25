<?php
echo "<h1 style='color: #28a745;'>✅ Custom Survey Questions - Complete Setup Guide</h1>";

try {
    $conn = new PDO('mysql:host=localhost;dbname=support_system', 'root', '');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get current questions
    $stmt = $conn->query("
        SELECT id, question_text, employment_status, is_removable, is_default
        FROM ssaa_alumni_survey_questions
        ORDER BY employment_status, id
    ");
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>📋 How Custom Survey Questions Work</h2>";
    
    echo "<div style='background: #f0f8ff; border-left: 4px solid #007bff; padding: 15px; margin: 20px 0;'>";
    echo "<p><strong>Step 1:</strong> Go to <a href='dashboard/alumni_management.php' target='_blank'>Admin Dashboard → Alumni Tracer Tab</a></p>";
    echo "<p><strong>Step 2:</strong> Click on \"Survey Configuration\" button</p>";
    echo "<p><strong>Step 3:</strong> Click \"Add Survey Question\" button</p>";
    echo "<p><strong>Step 4:</strong> Fill in the form:</p>";
    echo "<ul>";
    echo "<li><strong>Apply To:</strong> Select employment status (Employed, Self-Employed, Unemployed, All)</li>";
    echo "<li><strong>Question Type:</strong> Text or Multiple Choice</li>";
    echo "<li><strong>Question Text:</strong> Write your question</li>";
    echo "<li><strong>Placeholder/Choices:</strong> Add placeholder text or choices</li>";
    echo "</ul>";
    echo "<p><strong>Step 5:</strong> Click \"Save\" button</p>";
    echo "<p><strong>IMPORTANT:</strong> Make sure it says <span style='background: #90EE90; padding: 2px 5px;'>✓ Custom Question</span> (not Default)</p>";
    echo "</div>";
    
    echo "<hr>";
    echo "<h2>✅ Current Custom Questions in Database</h2>";
    
    $customQuestions = array_filter($questions, function($q) {
        return $q['is_removable'] == 1;
    });
    
    if (empty($customQuestions)) {
        echo "<p style='color: red; background: #ffe6e6; padding: 10px;'>";
        echo "❌ <strong>No custom questions found!</strong>";
        echo "<br>You need to add questions via the admin dashboard first.";
        echo "</p>";
    } else {
        echo "<p style='color: green; background: #e6ffe6; padding: 10px;'>";
        echo "✅ <strong>Found " . count($customQuestions) . " custom questions</strong>";
        echo "</p>";
        
        echo "<table border='1' cellpadding='10' style='width: 100%; margin-top: 10px;'>";
        echo "<tr>";
        echo "<th>ID</th>";
        echo "<th>Question</th>";
        echo "<th>Applied To</th>";
        echo "<th>Type</th>";
        echo "<th>Custom?</th>";
        echo "</tr>";
        
        foreach ($customQuestions as $q) {
            echo "<tr>";
            echo "<td>" . $q['id'] . "</td>";
            echo "<td>" . htmlspecialchars(substr($q['question_text'], 0, 50)) . "...</td>";
            echo "<td><strong>" . ucfirst($q['employment_status']) . "</strong></td>";
            echo "<td>" . ($q['question_type'] === 'choice' ? 'Multiple Choice' : 'Text') . "</td>";
            echo "<td style='background: #90EE90; color: green; font-weight: bold;'>✓ YES</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<hr>";
    echo "<h2>🧪 Test - See Custom Questions in Form</h2>";
    
    // Group by employment status
    $byStatus = [];
    foreach ($customQuestions as $q) {
        if (!isset($byStatus[$q['employment_status']])) {
            $byStatus[$q['employment_status']] = [];
        }
        $byStatus[$q['employment_status']][] = $q;
    }
    
    foreach (['employed', 'self-employed', 'unemployed'] as $status) {
        if (isset($byStatus[$status]) && !empty($byStatus[$status])) {
            echo "<h3>" . ucfirst(str_replace('-', ' ', $status)) . " Status</h3>";
            echo "<p><strong>Questions:</strong> " . count($byStatus[$status]) . "</p>";
            
            // Find alumni with this status
            $stmt = $conn->prepare("
                SELECT id, full_name
                FROM ssaa_alumni
                WHERE employment_status = ?
                LIMIT 3
            ");
            $stmt->execute([$status]);
            $alumni = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($alumni)) {
                echo "<p><strong>Test Links:</strong></p>";
                echo "<ul>";
                foreach ($alumni as $a) {
                    echo "<li>";
                    echo "<a href='alumni_tracer_form.php?alumni_id=" . $a['id'] . "' target='_blank' style='color: #007bff;'>";
                    echo $a['full_name'] . " (ID: " . $a['id'] . ")";
                    echo "</a>";
                    echo " - Fill form and scroll to see 'Additional Questions'";
                    echo "</li>";
                }
                echo "</ul>";
            }
        }
    }
    
    // Add "all" questions
    if (isset($byStatus['all']) && !empty($byStatus['all'])) {
        echo "<h3>Questions for All Alumni</h3>";
        echo "<p><strong>Questions:</strong> " . count($byStatus['all']) . "</p>";
        echo "<p>These appear in the form regardless of employment status</p>";
    }
    
    echo "<hr>";
    echo "<h2>⚠️ Troubleshooting</h2>";
    echo "<div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px;'>";
    echo "<p><strong>Problem:</strong> Custom questions don't show in the form</p>";
    echo "<p><strong>Solution:</strong> Make sure:</p>";
    echo "<ol>";
    echo "<li>✓ Questions were created in the admin Survey tab</li>";
    echo "<li>✓ Questions are marked as <strong>Custom</strong> (not Default)</li>";
    echo "<li>✓ \"Apply To\" status matches the alumni's employment status</li>";
    echo "<li>✓ Alumni has employment_status set in their record</li>";
    echo "<li>✓ Scroll down in the form to see 'Additional Questions' section</li>";
    echo "<li>✓ Browser cache cleared (Ctrl+F5 or Cmd+Shift+R)</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
