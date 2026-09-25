<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/clinic_functions.php';
require_login();

$message = '';
$visitId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = current_user();

    if (isset($_POST['start_visit'])) {
        // Start a new clinic visit
        $visitData = [
            'visit_type' => $_POST['visit_type'] ?? 'consultation',
            'symptoms' => $_POST['symptoms'] ?? null,
            'urgency_level' => $_POST['urgency_level'] ?? 'normal',
            'preferred_doctor' => $_POST['preferred_doctor'] ?? null,
            'additional_notes' => $_POST['additional_notes'] ?? null
        ];

        $visitId = save_clinic_visit($user['id'], $visitData);

        if ($visitId) {
            $message = 'Clinic visit started successfully! Your visit ID is: ' . $visitId;
        } else {
            $message = 'Failed to start clinic visit. Please try again.';
        }
    }

    if (isset($_POST['update_visit'])) {
        // Update existing visit with treatment information
        $visitId = $_POST['visit_id'] ?? null;
        $treatmentData = [
            'diagnosis' => $_POST['diagnosis'] ?? null,
            'treatment' => $_POST['treatment'] ?? null,
            'medications_prescribed' => $_POST['medications_prescribed'] ?? null,
            'follow_up_instructions' => $_POST['follow_up_instructions'] ?? null,
            'next_visit_date' => !empty($_POST['next_visit_date']) ? $_POST['next_visit_date'] : null,
            'doctor_notes' => $_POST['doctor_notes'] ?? null
        ];

        if (save_treatment_record($visitId, $treatmentData)) {
            $message = 'Treatment record saved successfully!';
        } else {
            $message = 'Failed to save treatment record.';
        }
    }

    if (isset($_POST['complete_visit'])) {
        // Complete the visit
        $visitId = $_POST['visit_id'] ?? null;

        if (complete_clinic_visit($visitId)) {
            $message = 'Clinic visit completed successfully!';
        } else {
            $message = 'Failed to complete clinic visit.';
        }
    }
}

// Get current visit if exists
$currentVisit = null;
if (isset($_GET['visit_id'])) {
    $currentVisit = get_clinic_visit($_GET['visit_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Process Clinic Visit | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        :root {
            --clinic-maroon: #800000;
            --clinic-gold: #D4AF37;
            --clinic-rose: #FFB6C1;
            --clinic-light: #FFF8DC;
        }

        .visit-form {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .visit-form h3 {
            color: var(--clinic-maroon);
            margin: 0 0 20px 0;
            font-size: 24px;
        }

        .form-section {
            background: var(--clinic-light);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--clinic-gold);
        }

        .form-section h4 {
            color: var(--clinic-maroon);
            margin: 0 0 15px 0;
            font-size: 18px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--clinic-maroon);
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background: var(--clinic-maroon);
            color: white;
        }

        .btn-primary:hover {
            background: #660000;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-secondary {
            background: var(--clinic-gold);
            color: var(--clinic-maroon);
        }

        .visit-status {
            background: linear-gradient(135deg, var(--clinic-maroon), var(--clinic-gold));
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .visit-status h4 {
            margin: 0 0 10px 0;
            font-size: 20px;
        }

        .visit-status p {
            margin: 0;
            opacity: 0.9;
        }

        .urgency-emergency {
            background: #dc3545;
            color: white;
        }

        .urgency-high {
            background: #fd7e14;
            color: white;
        }

        .urgency-normal {
            background: #ffc107;
            color: #212529;
        }

        .urgency-low {
            background: #28a745;
            color: white;
        }

        .alert-card {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            color: #155724;
        }

        .alert-card.warning {
            background: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }

        .alert-card.danger {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .treatment-history {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .treatment-history h4 {
            color: var(--clinic-maroon);
            margin: 0 0 15px 0;
        }

        .treatment-record {
            background: var(--clinic-light);
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 3px solid var(--clinic-gold);
        }

        .treatment-record h5 {
            margin: 0 0 10px 0;
            color: var(--clinic-maroon);
        }

        .treatment-record p {
            margin: 5px 0;
            font-size: 14px;
        }

        .qr-section {
            text-align: center;
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .qr-placeholder {
            width: 200px;
            height: 200px;
            border: 2px dashed #ddd;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #666;
            margin: 20px 0;
        }

        .qr-placeholder i {
            font-size: 48px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <aside class="side-nav">
            <div class="nav-brand">
                <img src="IMG ASSETS/passlogo.png" alt="PASS logo">
                <div>
                    <h2>PASS College</h2>
                    <p>Clinic Visit</p>
                </div>
            </div>
            <div class="nav-section">
                <h3>Navigation</h3>
                <a href="javascript:history.back()"><span class="nav-icon"><i class="fa-solid fa-arrow-left"></i></span>Back to Dashboard</a>
                <a href="logout.php"><span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>Logout</a>
            </div>
        </aside>

        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <h1>Clinic Visit Processing</h1>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?php
                            $user = current_user();
                            echo htmlspecialchars($user['full_name']);
                        ?></span>
                        <span class="user-meta"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span>
                    </div>
                </div>
            </header>

            <div class="main-scroll">
                <div class="content-panel">
                    <?php if ($message): ?>
                        <div class="alert-card">
                            <i class="fa-solid fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($currentVisit): ?>
                        <!-- Existing Visit Processing -->
                        <div class="visit-status urgency-<?php echo htmlspecialchars($currentVisit['urgency_level']); ?>">
                            <h4><i class="fa-solid fa-stethoscope"></i> Active Visit #<?php echo htmlspecialchars($currentVisit['id']); ?></h4>
                            <p><strong>Type:</strong> <?php echo htmlspecialchars(ucfirst($currentVisit['visit_type'])); ?> |
                               <strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($currentVisit['status'])); ?> |
                               <strong>Started:</strong> <?php echo date('M j, Y g:i A', strtotime($currentVisit['check_in_time'])); ?>
                            </p>
                        </div>

                        <!-- Treatment History -->
                        <?php
                        $treatments = get_visit_treatments($currentVisit['id']);
                        if (count($treatments) > 0):
                        ?>
                            <div class="treatment-history">
                                <h4><i class="fa-solid fa-history"></i> Treatment History</h4>
                                <?php foreach ($treatments as $treatment): ?>
                                    <div class="treatment-record">
                                        <h5><?php echo htmlspecialchars($treatment['diagnosis'] ?? 'Treatment Record'); ?></h5>
                                        <?php if ($treatment['treatment']): ?>
                                            <p><strong>Treatment:</strong> <?php echo htmlspecialchars($treatment['treatment']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($treatment['medications_prescribed']): ?>
                                            <p><strong>Medications:</strong> <?php echo htmlspecialchars($treatment['medications_prescribed']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($treatment['follow_up_instructions']): ?>
                                            <p><strong>Follow-up:</strong> <?php echo htmlspecialchars($treatment['follow_up_instructions']); ?></p>
                                        <?php endif; ?>
                                        <small style="color: #666;">
                                            <i class="fa-solid fa-calendar"></i> <?php echo date('M j, Y g:i A', strtotime($treatment['created_at'])); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Update Treatment Form -->
                        <div class="visit-form">
                            <h3><i class="fa-solid fa-user-md"></i> Update Treatment Record</h3>
                            <form method="POST">
                                <input type="hidden" name="visit_id" value="<?php echo htmlspecialchars($currentVisit['id']); ?>">

                                <div class="form-section">
                                    <h4>Diagnosis & Treatment</h4>
                                    <div class="form-group">
                                        <label for="diagnosis">Diagnosis:</label>
                                        <textarea id="diagnosis" name="diagnosis" placeholder="Enter diagnosis or medical findings"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="treatment">Treatment Provided:</label>
                                        <textarea id="treatment" name="treatment" placeholder="Describe treatment administered"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="medications_prescribed">Medications Prescribed:</label>
                                        <textarea id="medications_prescribed" name="medications_prescribed" placeholder="List prescribed medications with dosage"></textarea>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h4>Follow-up & Notes</h4>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="next_visit_date">Next Visit Date:</label>
                                            <input type="date" id="next_visit_date" name="next_visit_date">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="follow_up_instructions">Follow-up Instructions:</label>
                                        <textarea id="follow_up_instructions" name="follow_up_instructions" placeholder="Instructions for patient follow-up"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="doctor_notes">Doctor Notes:</label>
                                        <textarea id="doctor_notes" name="doctor_notes" placeholder="Additional notes from healthcare provider"></textarea>
                                    </div>
                                </div>

                                <div style="display: flex; gap: 10px;">
                                    <button type="submit" name="update_visit" class="btn btn-primary">
                                        <i class="fa-solid fa-save"></i> Save Treatment Record
                                    </button>
                                    <button type="submit" name="complete_visit" class="btn btn-success">
                                        <i class="fa-solid fa-check"></i> Complete Visit
                                    </button>
                                </div>
                            </form>
                        </div>

                    <?php else: ?>
                        <!-- New Visit Form -->
                        <div class="visit-form">
                            <h3><i class="fa-solid fa-plus-circle"></i> Start New Clinic Visit</h3>

                            <div class="alert-card warning">
                                <strong>Important:</strong> Please provide accurate information to ensure proper medical care. If this is an emergency, please call emergency services immediately.
                            </div>

                            <form method="POST">
                                <div class="form-section">
                                    <h4>Visit Information</h4>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="visit_type">Visit Type:</label>
                                            <select id="visit_type" name="visit_type" required>
                                                <option value="consultation">General Consultation</option>
                                                <option value="follow_up">Follow-up Visit</option>
                                                <option value="emergency">Emergency</option>
                                                <option value="preventive_care">Preventive Care</option>
                                                <option value="vaccination">Vaccination</option>
                                                <option value="health_screening">Health Screening</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="urgency_level">Urgency Level:</label>
                                            <select id="urgency_level" name="urgency_level" required>
                                                <option value="low">Low - Routine check-up</option>
                                                <option value="normal" selected>Normal - Standard appointment</option>
                                                <option value="high">High - Needs prompt attention</option>
                                                <option value="emergency">Emergency - Immediate care needed</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="preferred_doctor">Preferred Doctor (optional):</label>
                                        <input type="text" id="preferred_doctor" name="preferred_doctor" placeholder="Dr. Smith, Nurse Johnson, etc.">
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h4>Symptoms & Concerns</h4>
                                    <div class="form-group">
                                        <label for="symptoms">Current Symptoms:</label>
                                        <textarea id="symptoms" name="symptoms" required placeholder="Describe your symptoms, when they started, and any relevant details"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="additional_notes">Additional Notes:</label>
                                        <textarea id="additional_notes" name="additional_notes" placeholder="Any additional information, allergies, current medications, or special concerns"></textarea>
                                    </div>
                                </div>

                                <button type="submit" name="start_visit" class="btn btn-success" style="font-size: 16px; padding: 15px 30px;">
                                    <i class="fa-solid fa-play"></i> Start Clinic Visit
                                </button>
                            </form>
                        </div>

                        <!-- QR Code Section -->
                        <div class="qr-section">
                            <h4><i class="fa-solid fa-qrcode"></i> Quick Check-in</h4>
                            <p>Scan this QR code at the clinic entrance for faster check-in, or use the form above.</p>
                            <div class="qr-placeholder">
                                <i class="fa-solid fa-qrcode"></i>
                                <span>QR Code</span>
                                <small style="margin-top: 10px;">Scan at clinic entrance</small>
                            </div>
                            <div class="alert-card">
                                <strong>Note:</strong> QR code scanning will automatically start your visit and notify clinic staff.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/app.js" defer></script>
    <script>
        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-card');
            alerts.forEach(alert => {
                if (!alert.classList.contains('warning') && !alert.classList.contains('danger')) {
                    alert.style.display = 'none';
                }
            });
        }, 5000);

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;

                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            field.style.borderColor = '#dc3545';
                            isValid = false;
                        } else {
                            field.style.borderColor = '#ddd';
                        }
                    });

                    if (!isValid) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                    }
                });
            });
        });
    </script>
</body>
</html>