<?php
require_once __DIR__ . '/includes/session.php';
require_login();
$user = current_user();

// Check if user is librarian
if (!($user['role'] === 'teacher' && $user['head_service'] === 'library')) {
    header('Location: dashboard/student_home.php');
    exit;
}

ensure_library_schema();
$categories = ['Computer Science', 'Accountancy', 'Tourism', 'Education', 'Engineering', 'Arts & Sciences', 'Business Administration'];

$message = '';
$messageType = 'success';

// Handle download logging
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'download') {
    $resourceId = (int)($_POST['resource_id'] ?? 0);
    if ($resourceId > 0) {
        increment_resource_download($resourceId);
    }
    exit;
}

// Handle approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'] ?? '';
    $resourceId = (int)($_POST['resource_id'] ?? 0);
    
    if ($action === 'approve' && $resourceId > 0) {
        $category = $_POST['category'] ?? '';
        $courseYear = $_POST['course_year'] ?? '';
        
        if (empty($category)) {
            $message = 'Please select a category.';
            $messageType = 'error';
        } elseif (approve_library_resource($resourceId, $user['id'], $category, $courseYear)) {
            $message = 'E-Resource approved and published successfully.';
        } else {
            $message = 'Unable to approve the resource. Please try again.';
            $messageType = 'error';
        }
    } elseif ($action === 'reject' && $resourceId > 0) {
        if (reject_library_resource($resourceId)) {
            $message = 'E-Resource rejected and removed.';
        } else {
            $message = 'Unable to reject the resource. Please try again.';
            $messageType = 'error';
        }
    }
}

$pendingResources = get_pending_resource_approvals();
$approvedResources = get_library_resources(approvedOnly: true, limit: 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Resources Management | Library</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="dashboard-container">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2>PASS Library</h2>
                <p>E-Resources Management</p>
            </div>
            <ul class="sidebar-nav">
                <li><a href="dashboard/student_home.php">← Back to Dashboard</a></li>
            </ul>
        </nav>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>E-Resources Management</h1>
                    <p>Review and approve uploaded educational resources</p>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="dashboard-alert <?= $messageType === 'error' ? 'error' : 'success' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="library-tabs" style="margin-bottom: 30px;">
                <button type="button" class="library-tab-button active" data-tab="pending">Pending Approval (<?= count($pendingResources) ?>)</button>
                <button type="button" class="library-tab-button" data-tab="approved">Approved Resources (<?= count($approvedResources) ?>)</button>
            </div>

            <!-- Pending Approvals -->
            <section class="library-panel active" id="pending">
                <h2>Pending Approvals</h2>
                <p style="color: #64748b; margin-bottom: 24px;">Resources waiting for librarian review and approval</p>

                <?php if (empty($pendingResources)): ?>
                    <div class="library-card" style="text-align: center; padding: 40px;">
                        <p style="color: #94a3b8; font-size: 16px;">No pending resources for approval</p>
                    </div>
                <?php else: ?>
                    <div class="resources-grid">
                        <?php foreach ($pendingResources as $resource): ?>
                            <div class="resource-card">
                                <div class="resource-header">
                                    <h3><?= htmlspecialchars($resource['title']) ?></h3>
                                    <span class="badge pending">⏳ Pending</span>
                                </div>
                                
                                <p class="resource-meta">Uploaded by: <strong><?= htmlspecialchars($resource['uploader_name'] ?? 'Unknown') ?></strong></p>
                                <p class="resource-meta">File: <?= htmlspecialchars($resource['file_name']) ?> (<?= number_format($resource['file_size'] / 1024, 2) ?> KB)</p>
                                <p class="resource-meta">Type: <code><?= strtoupper(htmlspecialchars($resource['file_type'])) ?></code></p>
                                
                                <?php if (!empty($resource['description'])): ?>
                                    <p class="resource-description"><?= htmlspecialchars(substr($resource['description'], 0, 150)) ?>...</p>
                                <?php endif; ?>

                                <form method="post" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px; align-items: end;">
                                    <div>
                                        <label for="category-<?= $resource['id'] ?>" style="display: block; margin-bottom: 6px; font-weight: 500;">Category:</label>
                                        <select name="category" id="category-<?= $resource['id'] ?>" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;">
                                            <option value="">Select category</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="course-<?= $resource['id'] ?>" style="display: block; margin-bottom: 6px; font-weight: 500;">Course Year (optional):</label>
                                        <input type="text" name="course_year" id="course-<?= $resource['id'] ?>" placeholder="e.g., BSCS 2" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px;">
                                    </div>
                                    <div style="grid-column: 1 / -1; display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                        <button type="submit" name="action" value="approve" class="primary-button">✓ Approve</button>
                                        <button type="submit" name="action" value="reject" class="secondary-button" onclick="return confirm('Are you sure? This will delete the resource.');">✕ Reject</button>
                                    </div>
                                    <input type="hidden" name="resource_id" value="<?= $resource['id'] ?>">
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Approved Resources -->
            <section class="library-panel" id="approved">
                <h2>Approved Resources</h2>
                <p style="color: #64748b; margin-bottom: 24px;">Resources available to students and teachers</p>

                <?php if (empty($approvedResources)): ?>
                    <div class="library-card" style="text-align: center; padding: 40px;">
                        <p style="color: #94a3b8; font-size: 16px;">No approved resources yet</p>
                    </div>
                <?php else: ?>
                    <div class="resources-grid">
                        <?php foreach ($approvedResources as $resource): ?>
                            <div class="resource-card">
                                <div class="resource-header">
                                    <h3><?= htmlspecialchars($resource['title']) ?></h3>
                                    <span class="badge approved">✓ Published</span>
                                </div>
                                
                                <p class="resource-meta">Category: <strong><?= htmlspecialchars($resource['category']) ?></strong></p>
                                <?php if (!empty($resource['course_year'])): ?>
                                    <p class="resource-meta">For: <?= htmlspecialchars($resource['course_year']) ?></p>
                                <?php endif; ?>
                                <p class="resource-meta">Uploaded by: <?= htmlspecialchars($resource['uploader_name'] ?? 'Unknown') ?></p>
                                <p class="resource-meta">Downloads: <strong><?= $resource['download_count'] ?? 0 ?></strong></p>
                                
                                <?php if (!empty($resource['description'])): ?>
                                    <p class="resource-description"><?= htmlspecialchars(substr($resource['description'], 0, 150)) ?>...</p>
                                <?php endif; ?>

                                <p class="resource-meta" style="margin-top: 12px; font-size: 12px; color: #94a3b8;">
                                    Approved on: <?= (new DateTime($resource['approved_at']))->format('M d, Y') ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <style>
        .resources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .resource-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            transition: all 0.2s ease;
        }

        .resource-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .resource-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .resource-header h3 {
            margin: 0;
            font-size: 16px;
            color: #1e293b;
            flex: 1;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge.approved {
            background: #dcfce7;
            color: #166534;
        }

        .resource-meta {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }

        .resource-description {
            font-size: 14px;
            color: #475569;
            margin: 8px 0;
            line-height: 1.4;
        }

        .library-tabs {
            display: flex;
            gap: 12px;
            border-bottom: 2px solid #e2e8f0;
        }

        .library-tab-button {
            padding: 12px 20px;
            border: none;
            background: transparent;
            color: #64748b;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.2s ease;
        }

        .library-tab-button.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
        }

        .library-tab-button:hover {
            color: #334155;
        }

        .library-panel {
            display: none;
        }

        .library-panel.active {
            display: block;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabs = document.querySelectorAll('.library-tab-button');
            const panels = document.querySelectorAll('.library-panel');
            
            tabs.forEach(button => {
                button.addEventListener('click', function () {
                    const target = this.dataset.tab;
                    tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === target));
                    panels.forEach(p => p.classList.toggle('active', p.id === target));
                });
            });
        });
    </script>
</body>
</html>
