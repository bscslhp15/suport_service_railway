<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
$user = current_user();
if (!($user['role'] === 'admin' || ($user['role'] === 'teacher' && $user['head_service'] === 'ssc_scholarship'))) {
    header('Location: student_home.php');
    exit;
}

$displayCourse = $user['course'] ?? $user['course_year'] ?? 'Course / Department';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Post Updates | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .topbar-divider { width: 1px; height: 24px; background: #e9ecef; margin: 0 12px; }
        .mic-button { background: none; border: none; cursor: pointer; color: #666; padding: 0 8px; }
    </style>
    <style>
        .updates-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 24px; }
        .form-section { background: white; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .updates-list { background: white; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .update-item {
            border-bottom: 1px solid #eee;
            padding: 12px 0;
        }
        .update-item:last-child { border-bottom: none; }
        .update-title { font-weight: 600; color: #2c3e50; margin: 0 0 6px 0; }
        .update-meta { font-size: 0.85rem; color: #7f8c8d; }
    </style>
</head>
<body>
    <div class="page-shell">
        <aside class="side-nav">
            <div class="nav-brand">
                <img src="../IMG ASSETS/passlogo.png" alt="PASS logo">
                <div>
                    <h2>PASS College</h2>
                    <p>SSC / Scholarship Head</p>
                </div>
            </div>
            <div class="nav-section">
                <h3>Menu</h3>
                <a href="ssc_head_home.php"><span class="nav-icon"><i class="fa-solid fa-award"></i></span>Dashboard</a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false">
                        <span class="nav-icon">▸</span>
                        Services
                        <span class="toggle-arrow">▾</span>
                    </button>
                    <div class="submenu" aria-hidden="true">
                        <a href="guidance_home.php"><span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>Guidance</a>
                        <a href="librarian_home.php"><span class="nav-icon"><i class="fa-solid fa-book"></i></span>Library</a>
                        <a href="nurse_home.php"><span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>Clinic</a>
                        <a href="ssc_head_home.php"><span class="nav-icon"><i class="fa-solid fa-award"></i></span>SSC </a>
                        <a href="scholarship_dashboard.php"><span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>Scholarship</a>
                        <a href="scholarship_dashboard.php"><span class="nav-icon"><i class="fa-solid fa-cog"></i></span>Manage Scholarship</a>
                        <a href="ssaa_home.php"><span class="nav-icon"><i class="fa-solid fa-users"></i></span>SSAA</a>
                    </div>
                </div>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false">
                        <span class="nav-icon">▸</span>
                        Manage SSC
                        <span class="toggle-arrow">▾</span>
                    </button>
                    <div class="submenu" aria-hidden="true">
                        <a href="ssc_manage_events.php"><span class="nav-icon"><i class="fa-solid fa-calendar-days"></i></span>SSC Events</a>
                        <a href="ssc_manage_candidates.php"><span class="nav-icon"><i class="fa-solid fa-user-group"></i></span>Candidates</a>
                        <a href="ssc_manage_events.php"><span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>Initiatives</a>
                    </div>
                </div>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false">
                        <span class="nav-icon">▸</span>
                        Manage Scholarship
                        <span class="toggle-arrow">▾</span>
                    </button>
                    <div class="submenu" aria-hidden="true">
                        <a href="scholarship_create_announcement.php"><span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>Scholarship Announcements</a>
                        <a href="scholarship_application_tracker.php"><span class="nav-icon"><i class="fa-solid fa-clipboard-list"></i></span>Application Tracker</a>
                        <a href="scholarship_reports.php"><span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>Reports & Analytics</a>
                    </div>
                </div>
                <a href="#notifications"><span class="nav-icon">⚑</span>Notifications</a>
                <a href="#support"><span class="nav-icon">⚙</span>Support</a>
                <a href="#profile"><span class="nav-icon">👤</span>Profile</a>
                <a href="about.php"><span class="nav-icon">ℹ</span>About</a>
            </div>
            <div class="nav-footer">
                <a href="#settings"><span class="nav-icon"><i class="fa-solid fa-gear"></i></span>Settings</a>
                <a href="../logout.php"><span class="nav-icon"><i class="fa-solid fa-right-from-bracket"></i></span>Logout</a>
            </div>
        </aside>
        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <div class="search-box">
                        <i class="fa-solid fa-search"></i>
                        <input type="search" placeholder="Search updates..." />
                        <button type="button" class="mic-button"><i class="fa-solid fa-microphone"></i></button>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">SSC / Scholarship Head</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Settings"><i class="fa-solid fa-gear"></i></button>
                    <button type="button" class="topbar-icon" aria-label="About"><i class="fa-solid fa-question-circle"></i></button>
                </div>
            </header>
            <div class="main-scroll">
                <div class="content-panel">
                    <section class="dashboard-intro">
                        <h1>Post Updates</h1>
                        <p class="dashboard-subtitle">Share scholarship deadlines, program details, and financial assistance announcements.</p>
                    </section>

                    <div class="updates-grid">
                        <div class="form-section">
                            <h2 style="margin-top: 0;">New Update</h2>
                            <form id="updateForm">
                                <div class="form-group">
                                    <label for="updateTitle">Title *</label>
                                    <input type="text" id="updateTitle" name="title" required>
                                </div>
                                <div class="form-group">
                                    <label for="updateContent">Content *</label>
                                    <textarea id="updateContent" name="content" rows="6" required></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="updateType">Update Type</label>
                                    <select id="updateType" name="update_type">
                                        <option value="deadline">Deadline Update</option>
                                        <option value="program_info">Program Information</option>
                                        <option value="financial_assistance">Financial Assistance</option>
                                        <option value="general">General Update</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn-primary">Post Update</button>
                            </form>
                        </div>

                        <div class="updates-list">
                            <h2 style="margin-top: 0;">Recent Updates</h2>
                            <div id="updates-container">
                                <!-- Updates will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="notification-container" class="notification-container"></div>

    <script src="../assets/js/app.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadUpdates();
        });

        document.getElementById('updateForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);

            try {
                const response = await fetch('scholarship_api.php?action=save_update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    showNotification('Update posted successfully!', 'success');
                    this.reset();
                    loadUpdates();
                } else {
                    showNotification(result.message || 'Error posting update', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Error posting update', 'error');
            }
        });

        async function loadUpdates() {
            try {
                const response = await fetch('scholarship_api.php?action=get_updates');
                const data = await response.json();
                renderUpdates(data.updates || []);
            } catch (error) {
                console.error('Error loading updates:', error);
            }
        }

        function renderUpdates(updates) {
            const container = document.getElementById('updates-container');
            if (!updates || updates.length === 0) {
                container.innerHTML = '<p style="color:#999;">No updates available.</p>';
                return;
            }

            container.innerHTML = updates.map(upd => `
                <div class="update-item">
                    <h4 class="update-title">${escapeHtml(upd.title)}</h4>
                    <p class="update-meta"><strong>${escapeHtml(upd.update_type || 'General')}</strong> | ${new Date(upd.created_at).toLocaleDateString()}</p>
                </div>
            `).join('');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showNotification(message, type) {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            container.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }
    </script>
</body>
</html>
