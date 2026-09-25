<?php
require_once __DIR__ . '/includes/session.php';
ensure_library_schema();
$message = '';
$mode = 'checkin';
$status = ''; 
$currentUser = null;
if (!empty($_SESSION['user_id'])) {
    $currentUser = current_user();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = secure_input($_POST['name'] ?? '');
    $visitorType = secure_input($_POST['visitor_type'] ?? 'student');
    $studentNumber = secure_input($_POST['student_number'] ?? '');
    $employeeId = secure_input($_POST['employee_id'] ?? '');
    $course = secure_input($_POST['course'] ?? '');
    $ladderize_course = secure_input($_POST['ladderize_course'] ?? '');
    $selectedCourse = $ladderize_course ?: $course;
    $year = secure_input($_POST['year'] ?? '');
    $courseDepartment = secure_input($_POST['course_department'] ?? '');
    $purpose = secure_input($_POST['purpose'] ?? '');
    $otherPurpose = secure_input($_POST['other_purpose'] ?? '');
    if (!$name || !$purpose || ($visitorType === 'student' && (!$studentNumber || !$selectedCourse || !$year)) || ($visitorType === 'teacher' && !$employeeId) || ($visitorType !== 'student' && $visitorType !== 'teacher')) {
        $message = 'Please complete the required fields to proceed.';
    } else {
        if ($purpose === 'Other' && $otherPurpose) {
            $purpose = $otherPurpose;
        }
        if ($course && $ladderize_course) {
            $message = 'Please choose either Course or Ladderize Course, not both.';
        } else {
            if ($visitorType === 'student') {
                $courseDepartment = trim($selectedCourse . ' ' . $year);
            }
            $activeVisit = find_active_library_visit($studentNumber, $employeeId, $name);
        if ($activeVisit) {
            if ($activeVisit['status'] === 'in') {
                $timeOut = (new DateTime())->format('Y-m-d H:i:s');
                $started = new DateTime($activeVisit['time_in']);
                $duration = (int)$started->diff(new DateTime())->format('%i') + (int)$started->diff(new DateTime())->format('%h') * 60;
                if (complete_library_visit((int)$activeVisit['id'], $timeOut, $duration)) {
                    $message = 'Time out recorded successfully. Thank you for visiting the library.';
                    $mode = 'checkout';
                    $status = 'out';
                } else {
                    $message = 'Unable to update the session. Please try again.';
                }
            } elseif ($activeVisit['status'] === 'pending') {
                $message = 'Your entry is already submitted and waiting for librarian approval. Please wait for confirmation before entering.';
                $status = 'pending';
            } else {
                $message = 'A visit record was found but cannot be updated at this time. Please contact the librarian.';
            }
        } else {
            $visitData = [
                'user_id' => $currentUser['id'] ?? null,
                'name' => $name,
                'visitor_type' => in_array($visitorType, ['student', 'teacher'], true) ? $visitorType : 'student',
                'student_number' => $studentNumber ?: null,
                'employee_id' => $employeeId ?: null,
                'course_department' => $courseDepartment,
                'purpose' => $purpose,
                'checkin_method' => 'qr',
                'time_in' => (new DateTime())->format('Y-m-d H:i:s'),
                'status' => 'pending',
                'is_approved' => 0,
            ];
            if (save_library_visit($visitData)) {
                $message = 'Entry submitted successfully. Waiting for librarian approval before you enter.';
                $mode = 'checkin';
                $status = 'pending';
            } else {
                $message = 'Unable to record your entry. Please try again.';
            }
        }
    }
}
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Library QR Entry | PASS Support System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        body { background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%); }
        .entry-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px; }
        .entry-card { width: min(960px, 100%); background: rgba(255,255,255,0.96); border-radius: 28px; box-shadow: 0 28px 68px rgba(15,23,42,0.12); overflow: hidden; display: grid; grid-template-columns: 1.1fr 1.4fr; }
        .entry-left { padding: 48px; background: #0f172a; color: #f8fafc; display: flex; flex-direction: column; justify-content: space-between; }
        .entry-left h2 { font-size: 38px; margin-bottom: 18px; }
        .entry-left p { line-height: 1.8; max-width: 420px; }
        .entry-right { padding: 48px; }
        .entry-right h2 { margin-bottom: 24px; font-size: 32px; color: #0f172a; }
        .entry-right label { display: block; margin-bottom: 8px; color: #475569; }
        .entry-right input, .entry-right select, .entry-right textarea { width: 100%; padding: 14px 16px; margin-bottom: 18px; border: 1px solid #cbd5e1; border-radius: 14px; background: #f8fafc; }
        .entry-right button { width: 100%; padding: 14px 16px; border: none; border-radius: 14px; background: #2563eb; color: #fff; font-size: 16px; cursor: pointer; }
        .entry-right button:hover { background: #1d4ed8; }
        .alert { padding: 16px 18px; border-radius: 16px; margin-bottom: 18px; }
        .alert.success { background: #dcfce7; color: #166534; }
        .alert.error { background: #fee2e2; color: #991b1b; }
        .entry-footer { margin-top: 20px; color: #475569; }
        .entry-footer a { color: #2563eb; }
        .entry-status { font-size: 14px; margin-top: 14px; color: #f8fafc; background: rgba(255,255,255,0.08); padding: 16px; border-radius: 20px; }
        @media (max-width: 900px) { .entry-card { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="entry-page">
        <div class="entry-card">
            <div class="entry-left">
                <div>
                    <img src="IMG ASSETS/passlogo.png" alt="PASS logo" style="max-width: 160px; margin-bottom: 24px;">
                    <h2>Library QR Check-In</h2>
                    <p>Use this page after scanning the library QR code at the entrance. Fill in your details to record your visit or to check out when leaving.</p>
                </div>
                <div>
                    <p><strong>How it works</strong></p>
                    <ul style="line-height: 1.8; color: rgba(248,248,252,0.9);">
                        <li>Scan the QR code at the library entrance.</li>
                        <li>Submit your name, department, and purpose.</li>
                        <li>Scan again when leaving to close your session.</li>
                    </ul>
                    <?php if ($currentUser): ?>
                        <div class="entry-status">
                            Logged in as <?= htmlspecialchars($currentUser['full_name']) ?> (<?= htmlspecialchars($currentUser['role']) ?>)
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="entry-right">
                <h2>Scan & record visit</h2>
                <?php if ($message): ?>
                    <div class="alert <?= $status === 'out' ? 'success' : 'success' ?>"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <form method="post" action="">
                    <label>Name</label>
                    <input type="text" id="fullNameInput" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">

                    <label>Visitor type</label>
                    <select name="visitor_type" id="visitorTypeSelect">
                        <option value="student" <?= (($_POST['visitor_type'] ?? '') === 'student') ? 'selected' : '' ?>>Student</option>
                        <option value="teacher" <?= (($_POST['visitor_type'] ?? '') === 'teacher') ? 'selected' : '' ?>>Teacher</option>
                    </select>

                    <div id="studentIdGroup" style="display: none;">
                        <label>Student ID</label>
                        <input type="text" id="studentIdInput" name="student_number" placeholder="Student ID" value="<?= htmlspecialchars($_POST['student_number'] ?? '') ?>">
                    </div>

                    <div id="teacherIdGroup" style="display: none;">
                        <label>Employee ID</label>
                        <input type="text" name="employee_id" placeholder="Employee ID" value="<?= htmlspecialchars($_POST['employee_id'] ?? '') ?>">
                    </div>

                    <div id="studentCourseGroup" style="display: none;">
                        <label>Course</label>
                        <select name="course" id="studentCourseSelect">
                            <option value="">Select course</option>
                            <option value="Bachelor of Science in Accountancy" <?= (($_POST['course'] ?? '') === 'Bachelor of Science in Accountancy') ? 'selected' : '' ?>>Bachelor of Science in Accountancy</option>
                            <option value="Bachelor of Science in Business Administration" <?= (($_POST['course'] ?? '') === 'Bachelor of Science in Business Administration') ? 'selected' : '' ?>>Bachelor of Science in Business Administration</option>
                            <option value="Bachelor in Elementary Education" <?= (($_POST['course'] ?? '') === 'Bachelor in Elementary Education') ? 'selected' : '' ?>>Bachelor in Elementary Education</option>
                            <option value="Bachelor of Science in Computer Science" <?= (($_POST['course'] ?? '') === 'Bachelor of Science in Computer Science') ? 'selected' : '' ?>>Bachelor of Science in Computer Science</option>
                            <option value="Bachelor of Science in Criminology" <?= (($_POST['course'] ?? '') === 'Bachelor of Science in Criminology') ? 'selected' : '' ?>>Bachelor of Science in Criminology</option>
                            <option value="Bachelor of Science in Hospitality Management" <?= (($_POST['course'] ?? '') === 'Bachelor of Science in Hospitality Management') ? 'selected' : '' ?>>Bachelor of Science in Hospitality Management</option>
                            <option value="Bachelor of Science in Tourism Management" <?= (($_POST['course'] ?? '') === 'Bachelor of Science in Tourism Management') ? 'selected' : '' ?>>Bachelor of Science in Tourism Management</option>
                        </select>
                        <label>Ladderize Courses</label>
                        <select name="ladderize_course" id="ladderizeCourseSelect">
                            <option value="">Select ladderize course</option>
                            <option value="Associate in Computer Technology" <?= (($_POST['ladderize_course'] ?? '') === 'Associate in Computer Technology') ? 'selected' : '' ?>>Associate in Computer Technology</option>
                            <option value="Associate in Business Knowledge" <?= (($_POST['ladderize_course'] ?? '') === 'Associate in Business Knowledge') ? 'selected' : '' ?>>Associate in Business Knowledge</option>
                            <option value="Associate in Hospitality Management" <?= (($_POST['ladderize_course'] ?? '') === 'Associate in Hospitality Management') ? 'selected' : '' ?>>Associate in Hospitality Management</option>
                            <option value="Associate in Tourism Management" <?= (($_POST['ladderize_course'] ?? '') === 'Associate in Tourism Management') ? 'selected' : '' ?>>Associate in Tourism Management</option>
                        </select>
                        <label>Year Level</label>
                        <select name="year" id="studentYearSelect">
                            <option value="">Select year</option>
                            <?php if (!empty($_POST['year'])): ?>
                                <option value="<?= htmlspecialchars($_POST['year']) ?>" selected><?= htmlspecialchars($_POST['year']) ?></option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div id="departmentGroup">
                        <label>Course / Department</label>
                        <input type="text" name="course_department" value="<?= htmlspecialchars($_POST['course_department'] ?? '') ?>">
                    </div>

                    <label>Purpose of Visit</label>
                    <select name="purpose" required>
                        <option value="" disabled <?= empty($_POST['purpose']) ? 'selected' : '' ?>>Select purpose</option>
                        <option value="Borrowing" <?= (($_POST['purpose'] ?? '') === 'Borrowing') ? 'selected' : '' ?>>Borrowing</option>
                        <option value="Research" <?= (($_POST['purpose'] ?? '') === 'Research') ? 'selected' : '' ?>>Research</option>
                        <option value="Reading" <?= (($_POST['purpose'] ?? '') === 'Reading') ? 'selected' : '' ?>>Reading</option>
                        <option value="Reservation pickup" <?= (($_POST['purpose'] ?? '') === 'Reservation pickup') ? 'selected' : '' ?>>Reservation pickup</option>
                        <option value="Other" <?= (($_POST['purpose'] ?? '') === 'Other') ? 'selected' : '' ?>>Other</option>
                    </select>
                    <textarea name="other_purpose" rows="3" placeholder="If other, describe here"><?= htmlspecialchars($_POST['other_purpose'] ?? '') ?></textarea>

                    <button type="submit">Submit visit</button>
                </form>
                <div class="entry-footer">
                    <p>Library check-in is required before borrowing books or using library resources. Scan again to log your exit.</p>
                    <p><a href="dashboard/library_dashboard.php">Go to library dashboard</a></p>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function() {
            const visitorTypeSelect = document.getElementById('visitorTypeSelect');
            const studentIdGroup = document.getElementById('studentIdGroup');
            const teacherIdGroup = document.getElementById('teacherIdGroup');
            const studentCourseGroup = document.getElementById('studentCourseGroup');
            const departmentGroup = document.getElementById('departmentGroup');
            const studentCourseSelect = document.getElementById('studentCourseSelect');
            const ladderizeCourseSelect = document.getElementById('ladderizeCourseSelect');
            const studentYearSelect = document.getElementById('studentYearSelect');
            const studentNumberInput = document.querySelector('input[name="student_number"]');
            const studentIdInput = document.getElementById('studentIdInput');
            const fullNameInput = document.getElementById('fullNameInput');
            const employeeIdInput = document.querySelector('input[name="employee_id"]');
            const courseDepartmentInput = document.querySelector('input[name="course_department"]');
            const twoYearCourses = ['Associate in Computer Technology', 'Associate in Business Knowledge', 'Associate in Hospitality Management', 'Associate in Tourism Management'];

            function setYearOptions(course, selectedYear) {
                const years = twoYearCourses.includes(course) ? [1, 2] : [1, 2, 3, 4];
                studentYearSelect.innerHTML = '<option value="">Select year</option>' + years.map(year => {
                    return `<option value="${year}"${String(year) === String(selectedYear) ? ' selected' : ''}>${year}</option>`;
                }).join('');
            }

            function handleMutuallyExclusiveCourses() {
                if (studentCourseSelect.value) {
                    ladderizeCourseSelect.value = '';
                    ladderizeCourseSelect.disabled = true;
                } else {
                    ladderizeCourseSelect.disabled = false;
                }

                if (ladderizeCourseSelect.value) {
                    studentCourseSelect.value = '';
                    studentCourseSelect.disabled = true;
                } else {
                    studentCourseSelect.disabled = false;
                }
            }

            function updateFields() {
                const visitorType = visitorTypeSelect.value;
                const isStudent = visitorType === 'student';
                const isTeacher = visitorType === 'teacher';

                studentIdGroup.style.display = isStudent ? 'block' : 'none';
                teacherIdGroup.style.display = isTeacher ? 'block' : 'none';
                studentCourseGroup.style.display = isStudent ? 'block' : 'none';
                departmentGroup.style.display = isStudent ? 'none' : 'block';

                studentNumberInput.required = isStudent;
                employeeIdInput.required = isTeacher;
                studentCourseSelect.required = isStudent;
                ladderizeCourseSelect.required = isStudent;
                studentYearSelect.required = isStudent;
                courseDepartmentInput.required = !isStudent;
            }

            visitorTypeSelect.addEventListener('change', updateFields);
            studentCourseSelect.addEventListener('change', function() {
                handleMutuallyExclusiveCourses();
                setYearOptions(this.value, '');
            });
            ladderizeCourseSelect.addEventListener('change', function() {
                handleMutuallyExclusiveCourses();
                setYearOptions(this.value, '');
            });

            const initialCourse = '<?= htmlspecialchars($_POST['course'] ?? '') ?>';
            const initialLadderizeCourse = '<?= htmlspecialchars($_POST['ladderize_course'] ?? '') ?>';
            const initialYear = '<?= htmlspecialchars($_POST['year'] ?? '') ?>';
            if (initialCourse || initialLadderizeCourse) {
                setYearOptions(initialCourse || initialLadderizeCourse, initialYear);
            }

            updateFields();

            function capitalizeFullName(value) {
                return value.replace(/\b(\w)(\w*)/g, function(_, first, rest) {
                    return first.toUpperCase() + rest.toLowerCase();
                });
            }

            if (fullNameInput) {
                fullNameInput.addEventListener('input', function() {
                    this.value = capitalizeFullName(this.value);
                });
                fullNameInput.addEventListener('blur', function() {
                    this.value = capitalizeFullName(this.value);
                });
                const form = fullNameInput.closest('form');
                if (form) {
                    form.addEventListener('submit', function() {
                        fullNameInput.value = capitalizeFullName(fullNameInput.value);
                    });
                }
            }

            if (studentIdInput) {
                studentIdInput.addEventListener('input', function() {
                    let value = this.value.replace(/[^0-9]/g, '');
                    if (value.length > 2) {
                        value = value.slice(0, 2) + '-' + value.slice(2, 7);
                    }
                    this.value = value.slice(0, 8);
                });
            }
        })();
    </script>
</body>
</html>
