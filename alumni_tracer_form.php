<?php
require_once 'includes/session.php';
require_once 'includes/db.php';

// Allow public access like library_entry.php. If a logged-in user exists, prefer that alumni record.
$conn = get_db();
$userId = $_SESSION['user_id'] ?? null;
$user = $_SESSION['user'] ?? null;

$alumniData = null;
if ($userId) {
    $stmt = $conn->prepare('SELECT * FROM ssaa_alumni WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $alumniData = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Support pre-loading via query param ?alumni_id=123 for public links
if (!$alumniData && isset($_GET['alumni_id']) && is_numeric($_GET['alumni_id'])) {
    $stmt = $conn->prepare('SELECT * FROM ssaa_alumni WHERE id = ? LIMIT 1');
    $stmt->execute([intval($_GET['alumni_id'])]);
    $alumniData = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle form submission
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $responseData = [];

        // Collect all POST data
        foreach ($_POST as $key => $value) {
            if ($key !== 'save_alumni_tracer') {
                $responseData[$key] = trim($value);
            }
        }

        // Determine target alumni id: prefer logged-in/already-loaded alumni, then try matching by personal email, else create a minimal alumni record
        $alumniId = $alumniData['id'] ?? null;

        if (!$alumniId) {
            $personalEmail = trim($_POST['personal_email'] ?? '');
            if ($personalEmail) {
                $findStmt = $conn->prepare('SELECT id FROM ssaa_alumni WHERE personal_email = ? LIMIT 1');
                $findStmt->execute([$personalEmail]);
                $found = $findStmt->fetch(PDO::FETCH_ASSOC);
                if ($found) {
                    $alumniId = $found['id'];
                }
            }
        }

        if (!$alumniId) {
            // Create minimal alumni record to associate response
            $insertAlumni = $conn->prepare('INSERT INTO ssaa_alumni (full_name, personal_email, graduation_year, course, nationality, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $insertAlumni->execute([
                trim($_POST['full_name'] ?? ''),
                trim($_POST['personal_email'] ?? '') ?: null,
                trim($_POST['graduation_year'] ?? '') ?: null,
                trim($_POST['course'] ?? '') ?: null,
                trim($_POST['nationality'] ?? '') ?: null
            ]);
            $alumniId = $conn->lastInsertId();
        }

        // UPDATE ssaa_alumni table with form data (DO NOT update full_name)
        // Using exact column names from database
        $updateAlumni = $conn->prepare('
            UPDATE ssaa_alumni SET
                gender = ?,
                civil_status = ?,
                dob = ?,
                nationality = ?,
                personal_email = ?,
                contact_number = ?,
                permanent_address = ?,
                location = ?,
                employment_status = ?,
                company_name = ?,
                industry = ?,
                salary_range = ?,
                job_description = ?,
                last_updated = NOW()
            WHERE id = ?
        ');
        
        $updateResult = $updateAlumni->execute([
            trim($_POST['gender'] ?? ''),
            trim($_POST['civil_status'] ?? ''),
            trim($_POST['date_of_birth'] ?? '') ?: null,
            trim($_POST['nationality'] ?? ''),
            trim($_POST['personal_email'] ?? ''),
            trim($_POST['mobile_number'] ?? ''),
            trim($_POST['address'] ?? ''),
            trim($_POST['employment_location'] ?? ''),
            trim($_POST['employment_status'] ?? ''),
            trim($_POST['company_title'] ?? ''),
            trim($_POST['industry'] ?? ''),
            trim($_POST['salary_range'] ?? ''),
            trim($_POST['job_description'] ?? ''),
            $alumniId
        ]);

        if (!$updateResult) {
            throw new Exception('Failed to update alumni information: ' . implode(' ', $updateAlumni->errorInfo()));
        }

        // Check if response already exists for this alumni
        $checkStmt = $conn->prepare('SELECT id FROM ssaa_alumni_survey_responses WHERE alumni_id = ? LIMIT 1');
        $checkStmt->execute([$alumniId]);
        $existingResponse = $checkStmt->fetch(PDO::FETCH_ASSOC);

        $responseJson = json_encode($responseData);

        if ($existingResponse) {
            $updateStmt = $conn->prepare('UPDATE ssaa_alumni_survey_responses SET response_data = ?, updated_at = NOW() WHERE alumni_id = ?');
            $updateStmt->execute([$responseJson, $alumniId]);
        } else {
            $insertStmt = $conn->prepare('INSERT INTO ssaa_alumni_survey_responses (alumni_id, response_data) VALUES (?, ?)');
            $insertStmt->execute([$alumniId, $responseJson]);
        }

        $successMessage = 'Alumni tracer form submitted successfully! Your information has been updated.';
    } catch (Exception $e) {
        $errorMessage = 'Error saving form: ' . $e->getMessage();
        error_log('Alumni tracer form error: ' . $e->getMessage());
    }
}

// Get survey questions
$stmt = $conn->prepare('SELECT * FROM ssaa_alumni_survey_questions WHERE employment_status IN ("all", ?) ORDER BY employment_status DESC, order_index ASC');
$currentEmploymentStatus = $_GET['employment_status'] ?? 'all';
$stmt->execute([$currentEmploymentStatus]);
$allQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate questions by section
$basicQuestions = [];
$employmentQuestions = [];
$employmentSpecificQuestions = [];

foreach ($allQuestions as $q) {
    if ($q['employment_status'] === 'all' && $q['field_name'] !== 'employment_status') {
        $basicQuestions[] = $q;
    } elseif ($q['field_name'] === 'employment_status') {
        $employmentQuestions[] = $q;
    } elseif ($q['employment_status'] !== 'all') {
        $employmentSpecificQuestions[] = $q;
    }
}

// Get existing response if any
$existingResponse = [];
$responseRecord = null;
$existingAlumniId = $alumniData['id'] ?? (isset($_GET['alumni_id']) ? intval($_GET['alumni_id']) : null);
if ($existingAlumniId) {
    $stmt = $conn->prepare('SELECT response_data FROM ssaa_alumni_survey_responses WHERE alumni_id = ? LIMIT 1');
    $stmt->execute([$existingAlumniId]);
    $responseRecord = $stmt->fetch(PDO::FETCH_ASSOC);
}
if ($responseRecord) {
    $existingResponse = json_decode($responseRecord['response_data'], true) ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Tracer Form | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <style>
        * { box-sizing: border-box; }
        :root {
            --deep-maroon: #861b17;
            --deep-maroon-dark: #65100e;
            --soft-gold: #d4af37;
            --paper: #fffdf9;
            --cream: #f8f1e7;
            --text: #2d2926;
            --muted: #6b625d;
            --border: #e3d9ce;
            --blue-accent: #667eea;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--cream);
            min-height: 100vh;
            padding: 28px 20px 48px;
            color: var(--text);
        }
        .container {
            max-width: 920px;
            margin: 0 auto;
            background: var(--paper);
            border: 1px solid #eadfd3;
            border-top: 4px solid var(--soft-gold);
            border-radius: 0 0 18px 18px;
            box-shadow: 0 16px 40px rgba(105, 74, 16, 0.12);
            padding: 42px 48px 48px;
        }
        .header {
            text-align: center;
            margin-bottom: 32px;
            border-bottom: 3px solid var(--blue-accent);
            padding-bottom: 24px;
        }
        .header-logo {
            display: block;
            width: 86px;
            height: 86px;
            object-fit: contain;
            margin: 0 auto 14px;
        }
        .header h1 {
            color: var(--deep-maroon);
            margin: 0 0 10px 0;
            font-family: Georgia, 'Times New Roman', serif;
            font-size: clamp(28px, 4vw, 38px);
            letter-spacing: .01em;
        }
        .header p {
            color: var(--muted);
            margin: 0;
            font-size: 15px;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        .alert.success {
            background: #edf7ee;
            color: #286238;
            border: 1px solid #b9dfc0;
            display: block;
        }
        .alert.error {
            background: #fff0ee;
            color: #8a2520;
            border: 1px solid #e7b9b4;
            display: block;
        }
        .form-section {
            margin-bottom: 30px;
        }
        .section-title {
            background: #f8f6f3;
            padding: 14px 16px;
            border-left: 4px solid var(--blue-accent);
            margin-bottom: 20px;
            font-weight: 600;
            color: var(--text);
            letter-spacing: .01em;
        }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .form-grid.full {
            grid-template-columns: 1fr;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text);
            font-size: 14px;
        }
        .form-group label .required {
            color: #dc3545;
            margin-left: 3px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 7px;
            background: #fff;
            font-family: inherit;
            font-size: 14px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--blue-accent);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.12);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .employment-status-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin: 10px 0;
        }
        .employment-status-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            margin-bottom: 0;
            cursor: pointer;
        }
        .employment-status-group input[type="radio"] {
            cursor: pointer;
            margin: 0;
        }
        .form-buttons {
            display: flex;
            gap: 10px;
            margin-top: 34px;
            justify-content: center;
        }
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 7px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: var(--deep-maroon);
            color: white;
        }
        .btn-primary:hover {
            background: var(--deep-maroon-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(134, 27, 23, 0.25);
        }
        .btn-secondary {
            background: #6b625d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .conditional-fields {
            display: none;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--border);
        }
        .conditional-fields.show {
            display: block;
        }
        .readonly-field {
            background: #f4f1ee !important;
            color: #625b56;
        }
        .readonly-field:focus {
            background: #f8f9fa;
            border-color: #ddd;
        }
        @media (max-width: 768px) {
            body {
                padding: 16px 10px 32px;
            }
            .container {
                padding: 28px 20px 32px;
            }
            .header-logo {
                width: 72px;
                height: 72px;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .employment-status-group {
                flex-direction: column;
            }
            .form-buttons {
                flex-direction: column;
            }
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img class="header-logo" src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS College logo">
            <h1><i class="fas fa-graduation-cap"></i> Alumni Tracer Form</h1>
            <p>Help us stay connected! Update your professional information and career progress.</p>
        </div>

        <?php if ($successMessage): ?>
            <div class="alert success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <form method="POST" id="alumniTracerForm">
            <!-- Basic Information Section -->
            <div class="form-section">
                <div class="section-title"><i class="fas fa-user"></i> Basic Information</div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" value="<?= htmlspecialchars($existingResponse['full_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Student ID <span class="required">*</span></label>
                        <input type="text" name="student_id" value="<?= htmlspecialchars($existingResponse['student_id'] ?? $alumniData['student_id'] ?? '') ?>" class="readonly-field" readonly>
                    </div>
                    <div class="form-group">
                        <label>Year Graduated <span class="required">*</span></label>
                        <input type="text" name="graduation_year" value="<?= htmlspecialchars($existingResponse['graduation_year'] ?? $alumniData['graduation_year'] ?? '') ?>" readonly class="readonly-field" required>
                    </div>
                    <div class="form-group">
                        <label>Course <span class="required">*</span></label>
                        <input type="text" name="course" value="<?= htmlspecialchars($existingResponse['course'] ?? $alumniData['course'] ?? '') ?>" readonly class="readonly-field" required>
                    </div>
                    <div class="form-group">
                        <label>Gender <span class="required">*</span></label>
                        <select name="gender" required>
                            <option value="">-- Select Gender --</option>
                            <option value="Male" <?= ($existingResponse['gender'] ?? $alumniData['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($existingResponse['gender'] ?? $alumniData['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Other" <?= ($existingResponse['gender'] ?? $alumniData['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            <option value="Prefer not to say" <?= ($existingResponse['gender'] ?? $alumniData['gender'] ?? '') === 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Civil Status <span class="required">*</span></label>
                        <select name="civil_status" required>
                            <option value="">-- Select Status --</option>
                            <option value="Single" <?= ($existingResponse['civil_status'] ?? $alumniData['civil_status'] ?? '') === 'Single' ? 'selected' : '' ?>>Single</option>
                            <option value="Married" <?= ($existingResponse['civil_status'] ?? $alumniData['civil_status'] ?? '') === 'Married' ? 'selected' : '' ?>>Married</option>
                            <option value="Divorced" <?= ($existingResponse['civil_status'] ?? $alumniData['civil_status'] ?? '') === 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                            <option value="Widowed" <?= ($existingResponse['civil_status'] ?? $alumniData['civil_status'] ?? '') === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth <span class="required">*</span></label>
                        <input type="date" name="date_of_birth" value="<?= htmlspecialchars($existingResponse['date_of_birth'] ?? $alumniData['dob'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nationality <span class="required">*</span></label>
                        <input type="text" name="nationality" value="<?= htmlspecialchars($existingResponse['nationality'] ?? $alumniData['nationality'] ?? '') ?>" placeholder="e.g., Filipino" required>
                    </div>
                </div>
            </div>

            <!-- Contact Information Section -->
            <div class="form-section">
                <div class="section-title"><i class="fas fa-phone"></i> Contact Information</div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Personal Email <span class="required">*</span></label>
                        <input type="email" name="personal_email" value="<?= htmlspecialchars($existingResponse['personal_email'] ?? $alumniData['personal_email'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Mobile Number <span class="required">*</span></label>
                        <input type="tel" name="mobile_number" value="<?= htmlspecialchars($existingResponse['mobile_number'] ?? $alumniData['contact_number'] ?? '') ?>" maxlength="20" placeholder="e.g., 09123456789" required>
                    </div>
                    <div class="form-group full">
                        <label>Address <span class="required">*</span></label>
                        <textarea name="address" required><?= htmlspecialchars($existingResponse['address'] ?? $alumniData['permanent_address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Employment Status Section -->
            <div class="form-section">
                <div class="section-title"><i class="fas fa-briefcase"></i> Employment Status</div>
                
                <div class="form-group">
                    <label>Current Employment Status <span class="required">*</span></label>
                    <div class="employment-status-group">
                        <label>
                            <input type="radio" name="employment_status" value="Employed" onchange="toggleEmploymentFields()" <?= ($existingResponse['employment_status'] ?? 'Employed') === 'Employed' ? 'checked' : '' ?> required>
                            Employed
                        </label>
                        <label>
                            <input type="radio" name="employment_status" value="Self-Employed" onchange="toggleEmploymentFields()" <?= ($existingResponse['employment_status'] ?? '') === 'Self-Employed' ? 'checked' : '' ?>>
                            Self-Employed
                        </label>
                        <label>
                            <input type="radio" name="employment_status" value="Unemployed" onchange="toggleEmploymentFields()" <?= ($existingResponse['employment_status'] ?? '') === 'Unemployed' ? 'checked' : '' ?>>
                            Unemployed
                        </label>
                    </div>
                </div>

                <!-- Employed Fields -->
                <div id="employedFields" class="conditional-fields <?= ($existingResponse['employment_status'] ?? 'Employed') === 'Employed' ? 'show' : '' ?>">
                    <div class="section-title"><i class="fas fa-building"></i> Employment Details</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Job Description</label>
                            <textarea name="job_description" class="employed-field"><?= htmlspecialchars($existingResponse['job_description'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Company / Job Title</label>
                            <input type="text" name="company_title" class="employed-field" value="<?= htmlspecialchars($existingResponse['company_title'] ?? $alumniData['company_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="employment_location" class="employed-field" value="<?= htmlspecialchars($existingResponse['employment_location'] ?? $alumniData['location'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Industry</label>
                            <select name="industry" class="employed-field">
                                <option value="">-- Select Industry --</option>
                                <option value="Technology" <?= ($existingResponse['industry'] ?? '') === 'Technology' ? 'selected' : '' ?>>Technology</option>
                                <option value="Healthcare" <?= ($existingResponse['industry'] ?? '') === 'Healthcare' ? 'selected' : '' ?>>Healthcare</option>
                                <option value="Finance" <?= ($existingResponse['industry'] ?? '') === 'Finance' ? 'selected' : '' ?>>Finance</option>
                                <option value="Education" <?= ($existingResponse['industry'] ?? '') === 'Education' ? 'selected' : '' ?>>Education</option>
                                <option value="Hospitality" <?= ($existingResponse['industry'] ?? '') === 'Hospitality' ? 'selected' : '' ?>>Hospitality</option>
                                <option value="Retail" <?= ($existingResponse['industry'] ?? '') === 'Retail' ? 'selected' : '' ?>>Retail</option>
                                <option value="Manufacturing" <?= ($existingResponse['industry'] ?? '') === 'Manufacturing' ? 'selected' : '' ?>>Manufacturing</option>
                                <option value="Other" <?= ($existingResponse['industry'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Salary Range</label>
                            <select name="salary_range" class="employed-field">
                                <option value="">-- Select Salary Range --</option>
                                <option value="Under ₱15,000" <?= ($existingResponse['salary_range'] ?? '') === 'Under ₱15,000' ? 'selected' : '' ?>>Under ₱15,000</option>
                                <option value="₱15,001 - ₱25,000" <?= ($existingResponse['salary_range'] ?? '') === '₱15,001 - ₱25,000' ? 'selected' : '' ?>>₱15,001 - ₱25,000</option>
                                <option value="₱25,001 - ₱40,000" <?= ($existingResponse['salary_range'] ?? '') === '₱25,001 - ₱40,000' ? 'selected' : '' ?>>₱25,001 - ₱40,000</option>
                                <option value="₱40,001 - ₱60,000" <?= ($existingResponse['salary_range'] ?? '') === '₱40,001 - ₱60,000' ? 'selected' : '' ?>>₱40,001 - ₱60,000</option>
                                <option value="Above ₱60,000" <?= ($existingResponse['salary_range'] ?? '') === 'Above ₱60,000' ? 'selected' : '' ?>>Above ₱60,000</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Self-Employed Fields -->
                <div id="selfEmployedFields" class="conditional-fields <?= ($existingResponse['employment_status'] ?? '') === 'Self-Employed' ? 'show' : '' ?>">
                    <div class="section-title"><i class="fas fa-store"></i> Business Details</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Business Description</label>
                            <textarea name="self_employed_description"><?= htmlspecialchars($existingResponse['self_employed_description'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Business Type / Industry</label>
                            <input type="text" name="self_employed_industry" value="<?= htmlspecialchars($existingResponse['self_employed_industry'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="self_employed_location" value="<?= htmlspecialchars($existingResponse['self_employed_location'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Estimated Annual Income</label>
                            <select name="self_employed_income">
                                <option value="">-- Select Range --</option>
                                <option value="Under ₱100,000" <?= ($existingResponse['self_employed_income'] ?? '') === 'Under ₱100,000' ? 'selected' : '' ?>>Under ₱100,000</option>
                                <option value="₱100,001 - ₱300,000" <?= ($existingResponse['self_employed_income'] ?? '') === '₱100,001 - ₱300,000' ? 'selected' : '' ?>>₱100,001 - ₱300,000</option>
                                <option value="₱300,001 - ₱500,000" <?= ($existingResponse['self_employed_income'] ?? '') === '₱300,001 - ₱500,000' ? 'selected' : '' ?>>₱300,001 - ₱500,000</option>
                                <option value="₱500,001 - ₱1,000,000" <?= ($existingResponse['self_employed_income'] ?? '') === '₱500,001 - ₱1,000,000' ? 'selected' : '' ?>>₱500,001 - ₱1,000,000</option>
                                <option value="Above ₱1,000,000" <?= ($existingResponse['self_employed_income'] ?? '') === 'Above ₱1,000,000' ? 'selected' : '' ?>>Above ₱1,000,000</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Unemployed Fields -->
                <div id="unemployedFields" class="conditional-fields <?= ($existingResponse['employment_status'] ?? '') === 'Unemployed' ? 'show' : '' ?>">
                    <div class="section-title"><i class="fas fa-briefcase-open"></i> Additional Information</div>
                    <div class="form-grid full">
                        <div class="form-group">
                            <label>Reason for Unemployment</label>
                            <select name="unemployment_reason">
                                <option value="">-- Select Reason --</option>
                                <option value="Seeking Employment" <?= ($existingResponse['unemployment_reason'] ?? '') === 'Seeking Employment' ? 'selected' : '' ?>>Seeking Employment</option>
                                <option value="Further Studies" <?= ($existingResponse['unemployment_reason'] ?? '') === 'Further Studies' ? 'selected' : '' ?>>Further Studies</option>
                                <option value="Personal Reasons" <?= ($existingResponse['unemployment_reason'] ?? '') === 'Personal Reasons' ? 'selected' : '' ?>>Personal Reasons</option>
                                <option value="Other" <?= ($existingResponse['unemployment_reason'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group full">
                            <label>Additional Comments</label>
                            <textarea name="unemployment_comments"><?= htmlspecialchars($existingResponse['unemployment_comments'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Survey Questions Section (Dynamic) -->
            <div id="customQuestionsContainer"></div>

            <!-- Form Buttons -->
            <div class="form-buttons">
                <button type="submit" name="save_alumni_tracer" class="btn btn-primary">
                    <i class="fas fa-save"></i> Submit Form
                </button>
                <a href="dashboard/student_home.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        // Map employment status values to API values
        const statusMap = {
            'Employed': 'employed',
            'Self-Employed': 'self-employed',
            'Unemployed': 'unemployed'
        };

        function toggleEmploymentFields() {
            const status = document.querySelector('input[name="employment_status"]:checked').value;
            const employedFields = document.getElementById('employedFields');
            const selfEmployedFields = document.getElementById('selfEmployedFields');
            const unemployedFields = document.getElementById('unemployedFields');
            
            // Reset all fields visibility
            employedFields?.classList.remove('show');
            selfEmployedFields?.classList.remove('show');
            unemployedFields?.classList.remove('show');
            
            if (status === 'Employed') {
                employedFields?.classList.add('show');
            } else if (status === 'Self-Employed') {
                selfEmployedFields?.classList.add('show');
            } else if (status === 'Unemployed') {
                unemployedFields?.classList.add('show');
            }
            
            // Dynamically load custom questions for this status
            loadCustomQuestions(status);
        }

        function loadCustomQuestions(status) {
            const apiStatus = statusMap[status] || status;
            
            fetch('includes/api_get_custom_questions.php?status=' + encodeURIComponent(apiStatus))
                .then(response => response.json())
                .then(data => {
                    console.log('Custom questions loaded for status:', apiStatus, 'Count:', data.questions?.length || 0);
                    renderCustomQuestions(data.questions || []);
                })
                .catch(error => console.error('Error loading custom questions:', error));
        }

        function renderCustomQuestions(questions) {
            const container = document.getElementById('customQuestionsContainer');
            
            if (!container) {
                console.warn('Custom questions container not found');
                return;
            }
            
            // Clear previous questions
            container.innerHTML = '';
            
            if (!questions || questions.length === 0) {
                // No questions for this status - hide the section
                return;
            }
            
            // Build the section HTML
            let html = '<div class="form-section">';
            html += '<div class="section-title"><i class="fas fa-list-check"></i> Additional Questions</div>';
            html += '<div class="form-grid full">';
            
            questions.forEach(question => {
                const fieldName = 'custom_question_' + question.id;
                html += '<div class="form-group full">';
                html += '<label>' + escapeHtml(question.question_text) + '</label>';
                
                if (question.question_type === 'choice') {
                    // Multiple choice
                    const choices = question.answer_choices.split(',').map(c => c.trim());
                    html += '<select name="' + fieldName + '">';
                    html += '<option value="">-- Select Option --</option>';
                    choices.forEach(choice => {
                        html += '<option value="' + escapeHtml(choice) + '">' + escapeHtml(choice) + '</option>';
                    });
                    html += '</select>';
                } else {
                    // Text input
                    const placeholder = question.answer_choices || 'Enter your answer here...';
                    html += '<textarea name="' + fieldName + '" placeholder="' + escapeHtml(placeholder) + '" rows="3"></textarea>';
                }
                
                html += '</div>';
            });
            
            html += '</div></div>';
            
            // Set the innerHTML
            container.innerHTML = html;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Handle form submission
        document.addEventListener('DOMContentLoaded', function() {
            const status = document.querySelector('input[name="employment_status"]:checked')?.value || 'Employed';
            if (status === 'Employed') {
                document.getElementById('employedFields')?.classList.add('show');
            }
            
            // Load custom questions for the current employment status on page load
            loadCustomQuestions(status);
            
            // Add form submit handler for debugging
            const form = document.getElementById('alumniTracerForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    console.log('Form submitting...', {
                        gender: document.querySelector('select[name="gender"]').value,
                        civil_status: document.querySelector('select[name="civil_status"]').value,
                        date_of_birth: document.querySelector('input[name="date_of_birth"]').value,
                        nationality: document.querySelector('input[name="nationality"]').value,
                        personal_email: document.querySelector('input[name="personal_email"]').value,
                        mobile_number: document.querySelector('input[name="mobile_number"]').value,
                        address: document.querySelector('textarea[name="address"]').value,
                        employment_location: document.querySelector('input[name="employment_location"]').value
                    });
                });
            }
            
            // Check if there's a success message and reload after delay
            const successMsg = document.querySelector('.alert.success');
            if (successMsg) {
                console.log('Success message detected, reloading in 2 seconds...');
                setTimeout(function() {
                    location.reload();
                }, 2000);
            }
        });
    </script>
</body>
</html>
