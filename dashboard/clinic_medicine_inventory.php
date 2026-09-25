<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/clinic_functions.php';
require_login();
$user = current_user();
if ($user['role'] !== 'teacher' || $user['head_service'] !== 'clinic') {
    header('Location: /auth/teacher_login.php');
    exit;
}

// Initialize clinic schema
ensure_clinic_schema();

// Manage section remains collapsed by default on clinic management pages
$is_manage_open = false;

// Get data for all tabs
$categories = get_medicine_categories();
$medicines = get_clinic_medicines();
$lowStockMedicines = get_low_stock_medicines();
$expiringMedicines = get_expiring_medicines();
$activeMedicineTab = $_POST['active_tab'] ?? $_GET['active_tab'] ?? 'medicine';

// Handle form submissions
$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $newCategory = trim($_POST['new_category'] ?? '');

        if (!empty($newCategory)) {
            if (create_medicine_category($newCategory)) {
                $message = 'Category added successfully!';
                $categories = get_medicine_categories();
            } else {
                $message = 'Failed to add category. It might already exist.';
                $messageType = 'error';
            }
        } else {
            $message = 'Category name cannot be empty.';
            $messageType = 'error';
        }
    }

    if (isset($_POST['edit_category'])) {
        $categoryId = (int)$_POST['category_id'];
        $newName = trim($_POST['edit_category_name'] ?? '');

        if (!empty($newName)) {
            if (update_medicine_category($categoryId, $newName)) {
                $message = 'Category updated successfully!';
                $categories = get_medicine_categories();
            } else {
                $message = 'Failed to update category.';
                $messageType = 'error';
            }
        } else {
            $message = 'Category name cannot be empty.';
            $messageType = 'error';
        }
    }

    if (isset($_POST['delete_category'])) {
        $categoryId = (int)$_POST['category_id'];

        if (delete_medicine_category($categoryId)) {
            $message = 'Category deleted successfully!';
            $categories = get_medicine_categories();
        } else {
            $message = 'Failed to delete category. It may be in use by medicines.';
            $messageType = 'error';
        }
    }

    if (isset($_POST['edit_medicine'])) {
        $medicineId = (int)$_POST['medicine_id'];
        $medicineData = [
            'name' => trim($_POST['edit_medicine_name'] ?? ''),
            'generic_name' => trim($_POST['edit_generic_name'] ?? ''),
            'description' => trim($_POST['edit_description'] ?? ''),
            'category' => trim($_POST['edit_category'] ?? ''),
            'stock_quantity' => (int)($_POST['edit_stock_quantity'] ?? 0),
            'min_stock_level' => (int)($_POST['edit_min_stock_level'] ?? 5),
            'unit' => trim($_POST['edit_unit'] ?? 'tablets'),
            'expiry_date' => !empty($_POST['edit_expiry_date']) ? $_POST['edit_expiry_date'] : null,
            'batch_number' => trim($_POST['edit_batch_number'] ?? ''),
            'location' => trim($_POST['edit_location'] ?? '')
        ];

        if (!empty($medicineData['name'])) {
            if (update_clinic_medicine($medicineId, $medicineData)) {
                $message = 'Medicine updated successfully!';
                $medicines = get_clinic_medicines();
                $lowStockMedicines = get_low_stock_medicines();
                $categories = get_medicine_categories();
            } else {
                $message = 'Failed to update medicine.';
                $messageType = 'error';
            }
        } else {
            $message = 'Please enter a medicine name.';
            $messageType = 'error';
        }
    }

    if (isset($_POST['delete_medicine'])) {
        $medicineId = (int)$_POST['medicine_id'];

        if (delete_clinic_medicine($medicineId)) {
            $message = 'Medicine deleted successfully!';
            $medicines = get_clinic_medicines();
            $lowStockMedicines = get_low_stock_medicines();
            $categories = get_medicine_categories();
        } else {
            $message = 'Failed to delete medicine.';
            $messageType = 'error';
        }
    }

    if (isset($_POST['add_medicine'])) {
        $medicineData = [
            'name' => trim($_POST['medicine_name'] ?? ''),
            'generic_name' => trim($_POST['generic_name'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'category' => trim($_POST['category'] ?? ''),
            'stock_quantity' => (int)($_POST['stock_quantity'] ?? 0),
            'min_stock_level' => (int)($_POST['min_stock_level'] ?? 5),
            'unit' => trim($_POST['unit'] ?? 'tablets'),
            'expiry_date' => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
            'batch_number' => trim($_POST['batch_number'] ?? ''),
            'location' => trim($_POST['location'] ?? '')
        ];

        if (!empty($medicineData['name'])) {
            if (create_clinic_medicine($medicineData)) {
                $message = 'Medicine added successfully!';
                $medicines = get_clinic_medicines();
                $lowStockMedicines = get_low_stock_medicines();
                $categories = get_medicine_categories();
            } else {
                $message = 'Failed to add medicine.';
                $messageType = 'error';
            }
        } else {
            $message = 'Please enter a medicine name.';
            $messageType = 'error';
        }
    }

    if (isset($_POST['update_medicine_stock'])) {
        $medicineId = (int)$_POST['medicine_id'];
        $newStock = (int)$_POST['new_stock'];

        if (update_medicine_stock($medicineId, $newStock)) {
            $message = 'Medicine stock updated successfully!';
            $medicines = get_clinic_medicines();
            $lowStockMedicines = get_low_stock_medicines();
        } else {
            $message = 'Failed to update medicine stock.';
            $messageType = 'error';
        }
    }

    if (isset($_POST['remove_expired_medicines'])) {
        $deleted = delete_expired_clinic_medicines();
        if ($deleted > 0) {
            $message = 'Expired medicines removed successfully!';
            $medicines = get_clinic_medicines();
            $lowStockMedicines = get_low_stock_medicines();
            $expiringMedicines = get_expiring_medicines();
        } else {
            $message = 'No expired medicines found to remove.';
            $messageType = 'error';
        }
    }

    // Handle batch creation
    if (isset($_POST['create_batch'])) {
        $medicineId = (int)$_POST['medicine_id'];
        $batchData = [
            'batch_number' => trim($_POST['batch_number'] ?? ''),
            'quantity' => (int)($_POST['batch_quantity'] ?? 0),
            'expiry_date' => !empty($_POST['batch_expiry_date']) ? $_POST['batch_expiry_date'] : null,
            'location' => trim($_POST['batch_location'] ?? '')
        ];

        if (!empty($batchData['batch_number']) && $batchData['quantity'] > 0) {
            if (create_medicine_batch($medicineId, $batchData)) {
                $message = 'Batch added successfully!';
                $medicines = get_clinic_medicines();
            } else {
                $message = 'Failed to add batch. Batch number might already exist for this medicine.';
                $messageType = 'error';
            }
        } else {
            $message = 'Please enter batch number and quantity.';
            $messageType = 'error';
        }
    }

    // Handle batch update
    if (isset($_POST['update_batch'])) {
        $batchId = (int)$_POST['batch_id'];
        $batchData = [
            'quantity' => (int)($_POST['batch_quantity'] ?? 0),
            'expiry_date' => !empty($_POST['batch_expiry_date']) ? $_POST['batch_expiry_date'] : null
        ];

        if ($batchData['quantity'] > 0) {
            if (update_medicine_batch($batchId, $batchData)) {
                $message = 'Batch updated successfully!';
            } else {
                $message = 'Failed to update batch.';
                $messageType = 'error';
            }
        } else {
            $message = 'Quantity must be greater than 0.';
            $messageType = 'error';
        }
    }

    // Handle batch deactivation
    if (isset($_POST['deactivate_batch'])) {
        $batchId = (int)$_POST['batch_id'];
        if (deactivate_medicine_batch($batchId)) {
            $message = 'Batch deactivated successfully!';
        } else {
            $message = 'Failed to deactivate batch.';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Medicine Inventory Management | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/clinic-header.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        @font-face {
            font-family: 'ElephantLocal';
            src: url('../assets/FONTS/ELEPHNT.TTF') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        .nav-brand h2 {
            font-family: 'ElephantLocal', 'Playfair Display', serif;
        }

        :root {
            --clinic-maroon: #800000;
            --clinic-gold: #D4AF37;
            --clinic-light: #f8f9fa;
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

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
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
            transform: scale(1.05);
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .alert-card {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-card.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-card.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-card.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .table-responsive {
            overflow-x: auto;
            margin-bottom: 20px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .data-table th, .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .data-table th {
            background: var(--clinic-light);
            color: var(--clinic-maroon);
            font-weight: 600;
        }

        .content-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            margin: 0;
            color: var(--clinic-maroon);
        }

        .section-subtitle {
            margin-top: 10px;
            color: #4b5563;
            font-size: 0.95rem;
            line-height: 1.6;
            max-width: 760px;
        }

        .page-header-panel {
            margin-bottom: 48px;
        }

        .stock-low {
            color: #dc3545;
            font-weight: bold;
        }

        .stock-warning {
            color: #ffc107;
            font-weight: bold;
        }

        .stock-good {
            color: #28a745;
        }

        .expiry-warning {
            color: #dc3545;
            font-weight: bold;
        }

        .inventory-tab-bar,
        .medicine-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
            padding: 10px;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            justify-content: space-between;
        }

        .tab-button,
        .library-tab-button {
            border: 2px solid transparent;
            background: transparent;
            color: #334155;
            padding: 14px 16px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            min-width: 150px;
            text-align: center;
            flex: 1;
        }

        .tab-button:hover,
        .library-tab-button:hover {
            background: var(--clinic-maroon);
            color: white;
            border-color: var(--clinic-gold);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
        }

        .tab-button:hover:not(.active) i,
        .library-tab-button:hover:not(.active) i {
            color: var(--clinic-gold);
        }

        .tab-button.active,
        .library-tab-button.active {
            background: var(--clinic-maroon);
            color: #ffffff;
            border-color: var(--clinic-gold);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }

        .tab-button.active i,
        .library-tab-button.active i {
            color: var(--clinic-gold);
        }

        @media (max-width: 720px) {
            .page-header-panel {
                margin-bottom: 24px;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .section-header h2,
            .section-header h3 {
                font-size: 1.3rem;
            }

            .overview-grid {
                grid-template-columns: 1fr;
            }

            .overview-card {
                padding: 18px;
            }

            .search-filter-form {
                padding: 12px;
            }

            .search-group {
                flex-direction: column;
                align-items: stretch;
            }

            .search-input-group,
            .filter-group {
                width: 100%;
            }

            .results-count {
                justify-content: flex-start;
                margin-top: 10px;
            }

            .inventory-tab-bar,
            .medicine-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scroll-snap-type: x mandatory;
                padding-bottom: 8px;
            }

            .inventory-tab-bar::-webkit-scrollbar,
            .medicine-tabs::-webkit-scrollbar {
                display: none;
            }

            .tab-button,
            .library-tab-button {
                flex: 0 0 auto;
                min-width: 180px;
                margin: 0;
                scroll-snap-align: start;
            }

            .tab-button i,
            .library-tab-button i {
                margin-right: 8px;
            }

            .data-table {
                display: block;
                width: 100%;
                border: none;
                margin-bottom: 0;
            }

            .data-table thead {
                display: none;
            }

            .data-table tbody,
            .data-table tr {
                display: block;
                width: 100%;
            }

            .data-table tr {
                border: 1px solid #e5e7eb;
                border-radius: 18px;
                background: #ffffff;
                margin-bottom: 16px;
                padding: 16px;
                box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
            }

            .data-table td {
                display: block;
                width: 100%;
                padding: 10px 0;
                border: none;
                border-bottom: 1px solid #f3f4f6;
            }

            .data-table td:last-child {
                border-bottom: none;
            }

            .data-table td:before {
                content: attr(data-label);
                display: block;
                font-size: 0.82rem;
                font-weight: 700;
                color: #475569;
                margin-bottom: 6px;
                text-transform: uppercase;
                letter-spacing: 0.02em;
            }

            .data-table td > div {
                display: grid;
                gap: 10px;
            }

            .data-table td .btn {
                width: 100%;
                white-space: normal;
            }

            .data-table td span,
            .data-table td strong {
                display: block;
            }

            .data-table td > div button {
                width: 100%;
            }
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Batches modal specific responsive tweaks */
        @media (max-width: 720px) {
            #batchesModal .modal-content {
                width: 95% !important;
                max-width: 95% !important;
                padding: 14px !important;
                border-radius: 12px !important;
                overflow: visible !important;
            }

            #batchesModal .modal-content h2 {
                font-size: 1.05rem;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            #batchesModal form#addBatchForm {
                grid-template-columns: 1fr !important;
                gap: 10px !important;
            }

            #batchesModal form#addBatchForm .form-group { margin-bottom: 0; }

            /* make active batches list scrollable inside modal on small screens */
            #batchesModal .table-responsive {
                max-height: calc(100vh - 360px) !important;
                overflow-y: auto !important;
                -webkit-overflow-scrolling: touch !important;
            }

            #batchesModal .data-table { border: none; }

            /* Active Batches adjustments for small screens */
            #batchesModal .data-table tr {
                padding: 12px !important;
            }

            #batchesModal .data-table td[data-label="Actions"] > div {
                display: flex !important;
                flex-direction: column !important;
                gap: 8px !important;
            }

            #batchesModal .data-table td .btn {
                width: 100% !important;
                box-sizing: border-box !important;
            }

            #batchesModal .data-table td[data-label="Status"] span {
                display: inline-block !important;
                padding: 6px 8px !important;
                border-radius: 8px !important;
                font-weight: 700 !important;
            }

            /* Strong modal-scoped stacking rules to ensure table becomes card list */
            #batchesModal .data-table {
                display: block !important;
                width: 100% !important;
                border: none !important;
                margin: 0 !important;
            }

            #batchesModal .data-table thead { display: none !important; }

            #batchesModal .data-table tbody,
            #batchesModal .data-table tr {
                display: block !important;
                width: 100% !important;
                margin-bottom: 12px !important;
                border: 1px solid #e5e7eb !important;
                border-radius: 12px !important;
                padding: 12px !important;
                background: #fff !important;
                box-shadow: 0 2px 8px rgba(15,23,42,0.04) !important;
            }

            #batchesModal .data-table td {
                display: block !important;
                width: 100% !important;
                padding: 6px 0 !important;
                border: none !important;
                box-sizing: border-box !important;
            }

            #batchesModal .data-table td:before {
                content: attr(data-label) !important;
                display: block !important;
                font-size: 0.78rem !important;
                font-weight: 700 !important;
                color: #475569 !important;
                margin-bottom: 6px !important;
                text-transform: uppercase !important;
                letter-spacing: 0.02em !important;
            }

            /* ensure the active batches list scrolls and stays within modal */
            #batchesModal .table-responsive {
                max-height: calc(100vh - 360px) !important;
                overflow-y: auto !important;
                -webkit-overflow-scrolling: touch !important;
            }

            /* Ensure action buttons stack and full width inside modal */
            #batchesModal .data-table td[data-label="Actions"] > div,
            #batchesModal .data-table td[data-label="Actions"] {
                display: flex !important;
                flex-direction: column !important;
                gap: 8px !important;
                align-items: stretch !important;
            }

            #batchesModal .data-table td[data-label="Actions"] .btn {
                width: 100% !important;
            }
        }

        .modal-content {
            background-color: white;
            margin: 0;
            padding: 20px;
            border-radius: 12px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            position: relative;
            z-index: 1101;
        }

        .modal .close {
            color: #aaa;
            position: absolute;
            top: 12px;
            right: 14px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            background: transparent;
            border: none;
            line-height: 1;
        }

        .modal .close:hover {
            color: black;
        }

        .overview-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .overview-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .overview-card.warning {
            border-left: 4px solid #ffc107;
        }

        .overview-card.error {
            border-left: 4px solid #dc3545;
        }

        .overview-card.info {
            border-left: 4px solid #17a2b8;
        }

        .overview-icon {
            font-size: 24px;
            color: var(--clinic-maroon);
            min-width: 40px;
        }

        .overview-card.warning .overview-icon {
            color: #ffc107;
        }

        .overview-card.error .overview-icon {
            color: #dc3545;
        }

        .overview-card.info .overview-icon {
            color: #17a2b8;
        }

        .overview-content h3 {
            margin: 0 0 5px 0;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .overview-value {
            font-size: 32px;
            font-weight: bold;
            color: var(--clinic-maroon);
            margin: 0;
            line-height: 1;
        }

        .overview-card.warning .overview-value {
            color: #856404;
        }

        .overview-card.error .overview-value {
            color: #721c24;
        }

        .overview-card.info .overview-value {
            color: #0c5460;
        }

        .overview-content p {
            margin: 5px 0 0 0;
            font-size: 12px;
            color: #888;
        }

        .search-filter-section {
            margin-bottom: 20px;
        }

        .search-filter-form {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .search-group {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 200px;
        }

        .search-input-group i {
            color: var(--clinic-maroon);
        }

        .search-input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .results-count {
            font-size: 14px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .results-count i {
            color: var(--clinic-maroon);
        }
    </style>
    <style>
        @keyframes spin-refresh {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        @media (max-width: 768px) {
            body.swipe-refresh-enabled {
                touch-action: pan-x pan-y;
            }
        }
    </style>
</head>
<body class="swipe-refresh-enabled">
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); z-index: 9999; justify-content: center; align-items: center;"><div style="text-align: center;"><div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div><p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0;">Refreshing...</p></div></div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-mobile-header">
                <div class="mobile-user-info">
                    <h4><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p>Clinic Head</p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                
            </div>
            <div class="nav-section">
                <a href="nurse_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="school_announcements.php?service=clinic" data-tooltip="Announcements">
                    <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                    <span class="nav-text">Announcements</span>
                </a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="false" data-tooltip="Services">
                        <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                        <span class="nav-text">Services</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu" aria-hidden="true">
                        <a href="guidance_dashboard.php?service=clinic" data-tooltip="Guidance">
                            <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                            <span class="nav-text">Guidance</span>
                        </a>
                        <a href="library_dashboard.php?service=clinic" data-tooltip="Library">
                            <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                            <span class="nav-text">Library</span>
                        </a>
                        <a href="clinic_dashboard.php?service=clinic" data-tooltip="Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                            <span class="nav-text">Clinic</span>
                        </a>
                        <a href="ssc/ssc_dashboard.php?service=clinic" data-tooltip="SSC">
                            <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                            <span class="nav-text">SSC</span>
                        </a>
                        <a href="scholarship/scholarship_dashboard.php?service=clinic" data-tooltip="Scholarship">
                            <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                            <span class="nav-text">Scholarship</span>
                        </a>
                        <a href="ssaa_student_home.php?service=clinic" data-tooltip="Alumni">
                            <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                            <span class="nav-text">SSAA</span>
                        </a>
                    </div>
                </div>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $is_manage_open ? 'true' : 'false' ?>" data-tooltip="Manage">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Manage</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu <?= $is_manage_open ? 'open' : '' ?>" aria-hidden="<?= $is_manage_open ? 'false' : 'true' ?>">
                        <a href="clinic_status.php" data-tooltip="Clinic Status">
                            <span class="nav-icon"><i class="fa-solid fa-clock"></i></span>
                            <span class="nav-text">Clinic Status</span>
                        </a>
                        <a href="clinic_health_records.php" data-tooltip="Health Records">
                            <span class="nav-icon"><i class="fa-solid fa-heart-pulse"></i></span>
                            <span class="nav-text">Health Records</span>
                        </a>
                        <a href="clinic_medicine_inventory.php" class="active" data-tooltip="Medicine Inventory">
                            <span class="nav-icon"><i class="fa-solid fa-pills"></i></span>
                            <span class="nav-text">Medicine Inventory</span>
                        </a>
                        <a href="clinic_visit_logs.php" data-tooltip="Visit Logs">
                            <span class="nav-icon"><i class="fa-solid fa-notes-medical"></i></span>
                            <span class="nav-text">Visit Logs</span>
                        </a>
                      
                        <a href="clinic_analytics.php" data-tooltip="Analytics">
                            <span class="nav-icon"><i class="fa-solid fa-chart-bar"></i></span>
                            <span class="nav-text">Analytics</span>
                        </a>
                    </div>
                </div>
                <a href="profile.php?service=clinic" data-tooltip="Profile">
                    <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                    <span class="nav-text">Profile</span>
                </a>
                <a href="about.php?service=clinic" data-tooltip="About">
                    <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                    <span class="nav-text">About</span>
                </a>
            </div>
            <div class="nav-footer">
                <a href="../logout.php" class="logout-link" data-tooltip="Logout">
                    <span class="nav-icon"><i class="fa-solid fa-sign-out-alt"></i></span>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
        </aside>
        <main class="page-content">
            <header class="topbar">
                <div class="topbar-left">
                    <button type="button" class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="nav-brand">
                        <img src="<?= htmlspecialchars(get_login_logo_path()) ?>" alt="PASS logo">
                        <div>
                            <h2>PASS College</h2>
                            <p>Clinic Management</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Clinic Head</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Profile" data-tooltip="Profile" data-menu-target="profileMenu"><i class="fa-solid fa-user"></i></button>
                </div>
            </header>
            <div class="page-overlay" id="pageOverlay"></div>
            <div class="main-scroll">
                <?php if ($message): ?>
                    <div class="alert-card <?= $messageType ?>" id="success-message">
                        <i class="fa-solid fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                    <section class="clinic-page-header">
                        <div class="clinic-header__content">
                            <span class="eyebrow">MEDICINE INVENTORY</span>
                            <h1>Medicine Inventory Management</h1>
                            <p class="clinic-header__subtitle">Track stock levels, restock medications, and manage clinic supplies.</p>
                        </div>
                        <div class="clinic-header__art" aria-hidden="true"><i class="fa-solid fa-clipboard medical-clipboard"></i><i class="fa-solid fa-plus medical-cross"></i><i class="fa-solid fa-circle-check medical-check"></i><i class="fa-solid fa-stethoscope medical-stethoscope"></i><i class="fa-solid fa-location-pin medical-pin"></i><i class="fa-solid fa-leaf medical-leaf"></i></div>
                    </section>

                    <!-- Overview Boxes -->
                    <div class="overview-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                        <!-- Low Stock Alert Box -->
                        <div class="overview-card warning">
                            <div class="overview-icon">
                                <i class="fa-solid fa-exclamation-triangle"></i>
                            </div>
                            <div class="overview-content">
                                <h3>Low Stock Alert</h3>
                                <div class="overview-value"><?= count($lowStockMedicines) ?></div>
                                <p>medicines running low</p>
                            </div>
                        </div>

                        <!-- Expiry Alert Box -->
                        <div class="overview-card error">
                            <div class="overview-icon">
                                <i class="fa-solid fa-calendar-times"></i>
                            </div>
                            <div class="overview-content">
                                <h3>Expiry Alert</h3>
                                <div class="overview-value"><?= count($expiringMedicines) ?></div>
                                <p>medicines expiring soon</p>
                            </div>
                        </div>

                        <!-- Total Medicine Box -->
                        <div class="overview-card info">
                            <div class="overview-icon">
                                <i class="fa-solid fa-pills"></i>
                            </div>
                            <div class="overview-content">
                                <h3>Total Medicine</h3>
                                <div class="overview-value"><?= count($medicines) ?></div>
                                <p>medicines in inventory</p>
                            </div>
                        </div>
                    </div>

                    <!-- Medicine Inventory Management -->
                    <div class="content-section">
                        <div class="section-header">
                            <h2><i class="fa-solid fa-pills"></i> Medicine Inventory Management</h2>
                        </div>

                        <!-- Medicine Category Tabs -->
                        <div class="inventory-tab-bar medicine-tabs" role="tablist" aria-label="Medicine navigation">
                            <button type="button" class="tab-button library-tab-button active" data-category="medicine" onclick="setMedicineTab('medicine', this)">
                                <i class="fa-solid fa-pills"></i> Medicine
                            </button>
                            <button type="button" class="tab-button library-tab-button" data-category="category-management" onclick="setMedicineTab('category-management', this)">
                                <i class="fa-solid fa-tags"></i> Category Management
                            </button>
                            <button type="button" class="tab-button library-tab-button" data-category="expiring-soon" onclick="setMedicineTab('expiring-soon', this)">
                                <i class="fa-solid fa-clock"></i> Medicine Expiring Soon
                            </button>
                        </div>

                        <!-- Medicine Tab Content -->
                        <div id="medicine-inventory-section">
                            <div class="section-header">
                                <h3>Medicine Inventory</h3>
                                <button type="button" class="btn btn-primary" onclick="openAddMedicineModal()">
                                    <i class="fa-solid fa-plus"></i> Add Medicine
                                </button>
                            </div>

                            <!-- Search and Filter Controls -->
                            <div class="search-filter-section">
                                <div class="search-filter-form">
                                    <div class="search-group">
                                        <div class="search-input-group">
                                            <i class="fa-solid fa-search"></i>
                                            <input type="text" id="search-input" placeholder="Search by medicine name..." class="search-input">
                                        </div>
                                        <div class="filter-group">
                                            <select id="category-filter" class="filter-select">
                                                <option value="">All Categories</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?= htmlspecialchars($category['name'] ?? $category) ?>"><?= htmlspecialchars($category['name'] ?? $category) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="results-count">
                                    <i class="fa-solid fa-pills"></i>
                                    <span id="results-count">Showing <?= count($medicines) ?> medicine<?= count($medicines) !== 1 ? 's' : '' ?></span>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="data-table" id="medicine-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Generic Name</th>
                                            <th>Category</th>
                                            <th>Stock</th>
                                            <th>Status</th>
                                            <th>Expiry Date</th>
                                            <th>Actions</th>
                                            <th>Status Badges</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($medicines as $medicine): ?>
                                        <tr>
                                            <td data-label="Name"><?= htmlspecialchars($medicine['name']) ?></td>
                                            <td data-label="Generic Name"><?= htmlspecialchars($medicine['generic_name'] ?? 'N/A') ?></td>
                                            <td data-label="Category"><?= htmlspecialchars($medicine['category'] ?? 'Uncategorized') ?></td>
                                            <td data-label="Stock">
                                                <span class="<?= $medicine['stock_quantity'] <= $medicine['min_stock_level'] ? 'stock-low' : ($medicine['stock_quantity'] <= $medicine['min_stock_level'] * 1.5 ? 'stock-warning' : 'stock-good') ?>">
                                                    <?= $medicine['stock_quantity'] ?> <?= htmlspecialchars($medicine['unit']) ?>
                                                </span>
                                            </td>
                                            <td data-label="Status">
                                                <?php if ($medicine['stock_quantity'] <= $medicine['min_stock_level']): ?>
                                                    <span class="stock-low">LOW STOCK</span>
                                                <?php elseif ($medicine['stock_quantity'] <= $medicine['min_stock_level'] * 1.5): ?>
                                                    <span class="stock-warning">WARNING</span>
                                                <?php else: ?>
                                                    <span class="stock-good">GOOD</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($medicine['expiry_date']): ?>
                                                    <?php
                                                    $expiryDate = strtotime($medicine['expiry_date']);
                                                    $today = strtotime('today');
                                                    $daysLeft = ceil(($expiryDate - $today) / (60*60*24));
                                                    ?>
                                                    <span class="<?= $daysLeft <= 7 ? 'expiry-warning' : ($daysLeft <= 30 ? 'stock-warning' : 'stock-good') ?>">
                                                        <?= htmlspecialchars($medicine['expiry_date']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    No expiry
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="openBatchesModal(this)" data-id="<?= $medicine['id'] ?>" data-name="<?= htmlspecialchars($medicine['name']) ?>" data-unit="<?= htmlspecialchars($medicine['unit']) ?>">
                                                        <i class="fa-solid fa-boxes-stacked"></i> Add Batches
                                                    </button>
                                                    <button type="button" class="btn btn-warning btn-sm" onclick="openEditMedicineModal(this)" data-id="<?= $medicine['id'] ?>" data-name="<?= htmlspecialchars($medicine['name']) ?>" data-generic="<?= htmlspecialchars($medicine['generic_name'] ?? '') ?>" data-category="<?= htmlspecialchars($medicine['category'] ?? '') ?>" data-stock="<?= $medicine['stock_quantity'] ?>" data-min="<?= $medicine['min_stock_level'] ?>" data-unit="<?= htmlspecialchars($medicine['unit']) ?>" data-expiry="<?= htmlspecialchars($medicine['expiry_date'] ?? '') ?>" data-batch="<?= htmlspecialchars($medicine['batch_number'] ?? '') ?>" data-location="<?= htmlspecialchars($medicine['location'] ?? '') ?>" data-description="<?= htmlspecialchars($medicine['description'] ?? '') ?>">
                                                        <i class="fa-solid fa-pencil"></i> Edit
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteMedicine(<?= $medicine['id'] ?>, '<?= htmlspecialchars($medicine['name']) ?>')">
                                                        <i class="fa-solid fa-trash"></i> Delete
                                                    </button>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                                    <?php 
                                                    // Badge 1: Has Batches
                                                    if (has_medicine_batches($medicine['id'])): 
                                                    ?>
                                                        <span style="display: inline-block; background: #007bff; color: white; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; text-align: center;">
                                                            <i class="fa-solid fa-cubes"></i> Has Batches
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php 
                                                    // Badge 2: Batches Expiring Soon
                                                    if (has_expiring_batches($medicine['id'])): 
                                                    ?>
                                                        <span style="display: inline-block; background: #ffc107; color: #333; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; text-align: center;">
                                                            <i class="fa-solid fa-hourglass-end"></i> Expiring Soon
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php 
                                                    // Badge 3: Low Stock in Batches (only if multiple batches exist)
                                                    if (has_low_stock_batch($medicine['id'])): 
                                                    ?>
                                                        <span style="display: inline-block; background: #dc3545; color: white; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; text-align: center;">
                                                            <i class="fa-solid fa-triangle-exclamation"></i> Low Stock
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Category Management Tab Content -->
                        <div id="category-management-section" style="display: none;">
                            <div class="section-header">
                                <h3>Category Management</h3>
                                <button type="button" class="btn btn-primary" onclick="openAddCategoryModal()">
                                    <i class="fa-solid fa-plus"></i> Add Category
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Category Name</th>
                                            <th>Medicines Count</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categories as $category): ?>
                                        <tr>
                                            <td data-label="Category Name"><?= htmlspecialchars($category['name'] ?? $category) ?></td>
                                            <td data-label="Medicines Count">
                                                <?php
                                                $count = count(get_medicines_by_category($category['name'] ?? $category));
                                                echo $count;
                                                ?>
                                            </td>
                                            <td data-label="Actions">
                                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                                    <button type="button" class="btn btn-warning btn-sm" onclick="openEditCategoryModal(<?= $category['id'] ?? 0 ?>, '<?= htmlspecialchars($category['name'] ?? $category) ?>')">
                                                        <i class="fa-solid fa-pencil"></i> Edit
                                                    </button>
                                                    <button type="button" class="btn btn-danger btn-sm" onclick="deleteCategory(<?= $category['id'] ?? 0 ?>, '<?= htmlspecialchars($category['name'] ?? $category) ?>')">
                                                        <i class="fa-solid fa-trash"></i> Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Medicine Expiring Soon Tab Content -->
                        <div id="expiring-medicines-section" style="display: none;">
                            <div class="section-header">
                                <h3>Medicines Expiring Soon</h3>
                                <button type="button" class="btn btn-danger" onclick="removeExpiredMedicines()">
                                    <i class="fa-solid fa-trash"></i> Remove Expired
                                </button>
                            </div>

                            <?php if (!empty($expiringMedicines)): ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Medicine Name</th>
                                            <th>Batch Number</th>
                                            <th>Expiry Date</th>
                                            <th>Days Left</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($expiringMedicines as $medicine): ?>
                                        <tr>
                                            <td data-label="Medicine Name"><?= htmlspecialchars($medicine['name']) ?></td>
                                            <td data-label="Batch Number"><?= htmlspecialchars($medicine['batch_number'] ?? 'N/A') ?></td>
                                            <td data-label="Expiry Date"><?= htmlspecialchars($medicine['expiry_date']) ?></td>
                                            <td data-label="Days Left">
                                                <?php
                                                $expiryDate = strtotime($medicine['expiry_date']);
                                                $today = strtotime('today');
                                                $daysLeft = ceil(($expiryDate - $today) / (60*60*24));
                                                ?>
                                                <span class="<?= $daysLeft <= 7 ? 'expiry-warning' : ($daysLeft <= 30 ? 'stock-warning' : 'stock-good') ?>">
                                                    <?= $daysLeft ?> days
                                                </span>
                                            </td>
                                            <td data-label="Status">
                                                <?php if ($daysLeft <= 7): ?>
                                                    <span class="expiry-warning">EXPIRED SOON</span>
                                                <?php elseif ($daysLeft <= 30): ?>
                                                    <span class="stock-warning">EXPIRING</span>
                                                <?php else: ?>
                                                    <span class="stock-good">OK</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-danger btn-sm" onclick="deleteExpiredMedicine(<?= $medicine['id'] ?>, '<?= htmlspecialchars($medicine['name']) ?>')">
                                                    <i class="fa-solid fa-trash"></i> Remove
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: #666;">
                                <i class="fa-solid fa-check-circle" style="font-size: 48px; margin-bottom: 15px; color: #28a745;"></i>
                                <div>No medicines expiring within 30 days.</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
            </div>
        </main>
    </div>

    <!-- Add Medicine Modal -->
    <div id="addMedicineModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddMedicineModal()">&times;</span>
            <h2><i class="fa-solid fa-pills"></i> Add New Medicine</h2>
            <form method="POST" id="addMedicineForm">
                <input type="hidden" name="active_tab" id="addMedicineActiveTab" value="<?= htmlspecialchars($activeMedicineTab) ?>">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="medicine_name">Medicine Name *</label>
                        <input type="text" name="medicine_name" id="medicine_name" required>
                    </div>

                    <div class="form-group">
                        <label for="generic_name">Generic Name</label>
                        <input type="text" name="generic_name" id="generic_name">
                    </div>

                    <div class="form-group">
                        <label for="category">Category</label>
                        <select name="category" id="category">
                            <option value="">Select category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['name'] ?? $cat) ?>"><?= htmlspecialchars($cat['name'] ?? $cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="unit">Unit</label>
                        <input type="text" name="unit" id="unit" value="tablets" placeholder="e.g., tablets, capsules, ml">
                    </div>

                    <div class="form-group">
                        <label for="stock_quantity">Stock Quantity *</label>
                        <input type="number" name="stock_quantity" id="stock_quantity" min="0" value="0" required>
                    </div>

                    <div class="form-group">
                        <label for="min_stock_level">Minimum Stock Level</label>
                        <input type="number" name="min_stock_level" id="min_stock_level" min="1" value="5">
                    </div>

                    <div class="form-group">
                        <label for="expiry_date">Expiry Date</label>
                        <input type="date" name="expiry_date" id="expiry_date">
                    </div>

                    <div class="form-group">
                        <label for="batch_number">Batch Number</label>
                        <input type="text" name="batch_number" id="batch_number">
                    </div>

                    <div class="form-group">
                        <label for="location">Location/Shelf</label>
                        <input type="text" name="location" id="location" placeholder="e.g., Cabinet A, Shelf 3">
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" rows="3" placeholder="Additional details about the medicine"></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="add_medicine" class="btn btn-primary" style="flex: 1;">
                        <i class="fa-solid fa-plus"></i> Add Medicine
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddMedicineModal()" style="flex: 1; background: #6c757d; color: white;">
                        <i class="fa-solid fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div id="addCategoryModal" class="modal">
        <div class="modal-content" style="max-width: 420px;">
            <span class="close" onclick="closeAddCategoryModal()">&times;</span>
            <h2><i class="fa-solid fa-tags"></i> Add Category</h2>
            <form method="POST" id="addCategoryForm">
                <input type="hidden" name="active_tab" id="addCategoryActiveTab" value="<?= htmlspecialchars($activeMedicineTab) ?>">
                <div class="form-group">
                    <label for="new_category">Category Name *</label>
                    <input type="text" name="new_category" id="new_category" required placeholder="e.g., Antibiotic">
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="add_category" class="btn btn-primary" style="flex: 1;">
                        <i class="fa-solid fa-plus"></i> Add Category
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddCategoryModal()" style="flex: 1; background: #6c757d; color: white;">
                        <i class="fa-solid fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Stock Modal -->
    <div id="stockModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeStockModal()">&times;</span>
            <h2>Update Medicine Stock</h2>
            <form method="POST" id="stockForm">
                <input type="hidden" name="active_tab" id="stockActiveTab" value="<?= htmlspecialchars($activeMedicineTab) ?>">
                <input type="hidden" name="medicine_id" id="modal_medicine_id">

                <div class="form-group">
                    <label for="modal_medicine_name">Medicine Name</label>
                    <input type="text" id="modal_medicine_name" readonly>
                </div>

                <div class="form-group">
                    <label for="current_stock">Current Stock</label>
                    <input type="number" id="current_stock" readonly>
                </div>

                <div class="form-group">
                    <label for="new_stock">New Stock Quantity</label>
                    <input type="number" name="new_stock" id="new_stock" min="0" required>
                </div>

                <button type="submit" name="update_medicine_stock" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Update Stock
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Medicine Modal -->
    <div id="editMedicineModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditMedicineModal()">&times;</span>
            <h2><i class="fa-solid fa-pencil"></i> Edit Medicine</h2>
            <form method="POST" id="editMedicineForm">
                <input type="hidden" name="active_tab" id="editMedicineActiveTab" value="<?= htmlspecialchars($activeMedicineTab) ?>">
                <input type="hidden" name="medicine_id" id="edit_medicine_id">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="edit_medicine_name">Medicine Name *</label>
                        <input type="text" name="edit_medicine_name" id="edit_medicine_name" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_generic_name">Generic Name</label>
                        <input type="text" name="edit_generic_name" id="edit_generic_name">
                    </div>

                    <div class="form-group">
                        <label for="edit_category">Category</label>
                        <select name="edit_category" id="edit_category">
                            <option value="">Select category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['name'] ?? $cat) ?>"><?= htmlspecialchars($cat['name'] ?? $cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_unit">Unit</label>
                        <input type="text" name="edit_unit" id="edit_unit" value="tablets">
                    </div>

                    <div class="form-group">
                        <label for="edit_stock_quantity">Stock Quantity *</label>
                        <input type="number" name="edit_stock_quantity" id="edit_stock_quantity" min="0" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_min_stock_level">Minimum Stock Level</label>
                        <input type="number" name="edit_min_stock_level" id="edit_min_stock_level" min="1" value="5">
                    </div>

                    <div class="form-group">
                        <label for="edit_expiry_date">Expiry Date</label>
                        <input type="date" name="edit_expiry_date" id="edit_expiry_date">
                    </div>

                    <div class="form-group">
                        <label for="edit_batch_number">Batch Number</label>
                        <input type="text" name="edit_batch_number" id="edit_batch_number">
                    </div>

                    <div class="form-group">
                        <label for="edit_location">Location/Shelf</label>
                        <input type="text" name="edit_location" id="edit_location">
                    </div>
                </div>

                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea name="edit_description" id="edit_description" rows="3"></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="edit_medicine" class="btn btn-primary" style="flex: 1;">
                        <i class="fa-solid fa-save"></i> Update Medicine
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditMedicineModal()" style="flex: 1; background: #6c757d; color: white;">
                        <i class="fa-solid fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editCategoryModal" class="modal">
        <div class="modal-content" style="max-width: 420px;">
            <span class="close" onclick="closeEditCategoryModal()">&times;</span>
            <h2><i class="fa-solid fa-pencil"></i> Edit Category</h2>
            <form method="POST" id="editCategoryForm">
                <input type="hidden" name="active_tab" id="editCategoryActiveTab" value="<?= htmlspecialchars($activeMedicineTab) ?>">
                <input type="hidden" name="category_id" id="edit_category_id">
                <div class="form-group">
                    <label for="edit_category_name">Category Name *</label>
                    <input type="text" name="edit_category_name" id="edit_category_name" required>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="edit_category" class="btn btn-primary" style="flex: 1;">
                        <i class="fa-solid fa-save"></i> Update Category
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditCategoryModal()" style="flex: 1; background: #6c757d; color: white;">
                        <i class="fa-solid fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Medicine Batches Management Modal -->
    <div id="batchesModal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <span class="close" onclick="closeBatchesModal()">&times;</span>
            <h2><i class="fa-solid fa-boxes-stacked"></i> Manage Batches - <span id="batchModalMedicineName"></span></h2>
            
            <!-- Add New Batch Form -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3 style="margin-top: 0; color: var(--clinic-maroon);">Add New Batch</h3>
                <form method="POST" id="addBatchForm" style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; align-items: flex-end;">
                    <input type="hidden" name="active_tab" value="<?= htmlspecialchars($activeMedicineTab) ?>">
                    <input type="hidden" name="medicine_id" id="batchModalMedicineId">
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="batch_number">Batch Number *</label>
                        <input type="text" name="batch_number" id="batch_number" required placeholder="e.g., BATCH001">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="batch_quantity">Quantity *</label>
                        <input type="number" name="batch_quantity" id="batch_quantity" min="1" required>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="batch_expiry_date">Expiry Date</label>
                        <input type="date" name="batch_expiry_date" id="batch_expiry_date">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="batch_location">Location</label>
                        <input type="text" name="batch_location" id="batch_location" placeholder="e.g., Cabinet A">
                    </div>

                    <button type="submit" name="create_batch" class="btn btn-primary" style="width: 100%;">
                        <i class="fa-solid fa-plus"></i> Add Batch
                    </button>
                </form>
            </div>

            <!-- Active Batches Table -->
            <h3 style="color: var(--clinic-maroon);">Active Batches</h3>
            <div class="table-responsive">
                <table class="data-table" id="batchesTable">
                    <thead>
                        <tr>
                            <th>Batch Number</th>
                            <th>Quantity</th>
                            <th>Expiry Date</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="batchesTableBody">
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px; color: #999;">Loading batches...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Batch Modal -->
    <div id="editBatchModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <span class="close" onclick="closeEditBatchModal()">&times;</span>
            <h2><i class="fa-solid fa-edit"></i> Edit Batch</h2>
            <form method="POST" id="editBatchForm">
                <input type="hidden" name="active_tab" value="<?= htmlspecialchars($activeMedicineTab) ?>">
                <input type="hidden" name="batch_id" id="editBatchId">
                
                <div class="form-group">
                    <label for="editBatchNumber">Batch Number</label>
                    <input type="text" id="editBatchNumber" readonly style="background: #f0f0f0;">
                </div>
                
                <div class="form-group">
                    <label for="editBatchQuantity">Quantity *</label>
                    <input type="number" name="batch_quantity" id="editBatchQuantity" min="1" required>
                </div>
                
                <div class="form-group">
                    <label for="editBatchExpiry">Expiry Date</label>
                    <input type="date" name="batch_expiry_date" id="editBatchExpiry">
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="update_batch" class="btn btn-primary" style="flex: 1;">
                        <i class="fa-solid fa-save"></i> Update Batch
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditBatchModal()" style="flex: 1; background: #6c757d; color: white;">
                        <i class="fa-solid fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/app.js" defer></script>
    <script>
        // Mobile Menu Toggle (small screens)
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sideNav = document.querySelector('.side-nav');
            const pageOverlay = document.getElementById('pageOverlay');

            if (mobileMenuToggle && sideNav && pageOverlay) {
                mobileMenuToggle.addEventListener('click', function() {
                    sideNav.classList.toggle('mobile-open');
                    pageOverlay.classList.toggle('active');
                });

                pageOverlay.addEventListener('click', function() {
                    sideNav.classList.remove('mobile-open');
                    pageOverlay.classList.remove('active');
                });

                const navLinks = sideNav.querySelectorAll('a, .nav-toggle');
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (!this.classList.contains('nav-toggle')) {
                            sideNav.classList.remove('mobile-open');
                            pageOverlay.classList.remove('active');
                        }
                    });
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && sideNav.classList.contains('mobile-open')) {
                        sideNav.classList.remove('mobile-open');
                        pageOverlay.classList.remove('active');
                    }
                });
            }
        });
    </script>
    <script>
        let currentTab = 'medicine';

        function setMedicineTab(tabName, element) {
            // Hide all tab contents safely
            const sections = ['medicine-inventory-section', 'category-management-section', 'expiring-medicines-section'];
            sections.forEach(sectionId => {
                const section = document.getElementById(sectionId);
                if (section) section.style.display = 'none';
            });

            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });

            // Map tab name to exact content section ID
            let sectionToShow = '';
            if (tabName === 'medicine') {
                sectionToShow = 'medicine-inventory-section';
            } else if (tabName === 'category-management') {
                sectionToShow = 'category-management-section';
            } else if (tabName === 'expiring-soon') {
                sectionToShow = 'expiring-medicines-section';
            }

            const targetSection = document.getElementById(sectionToShow);
            if (targetSection) {
                targetSection.style.display = 'block';
            }

            const tabButton = element || document.querySelector(`[data-category="${tabName}"]`);
            if (tabButton) {
                tabButton.classList.add('active');
            }

            currentTab = tabName;
            document.querySelectorAll('input[name="active_tab"]').forEach(input => input.value = tabName);
            window.history.replaceState(null, null, '#' + tabName);
        }

        function openAddMedicineModal() {
            const form = document.getElementById('addMedicineForm');
            if (form) {
                form.reset();
                const activeInput = document.getElementById('addMedicineActiveTab');
                if (activeInput) activeInput.value = currentTab;
            }
            const modal = document.getElementById('addMedicineModal');
            if (modal) modal.style.display = 'flex';
        }

        function closeAddMedicineModal() {
            const modal = document.getElementById('addMedicineModal');
            if (modal) modal.style.display = 'none';
        }

        function openAddCategoryModal() {
            const form = document.getElementById('addCategoryForm');
            if (form) {
                form.reset();
                const activeInput = document.getElementById('addCategoryActiveTab');
                if (activeInput) activeInput.value = currentTab;
            }
            const modal = document.getElementById('addCategoryModal');
            if (modal) modal.style.display = 'flex';
        }

        function closeAddCategoryModal() {
            const modal = document.getElementById('addCategoryModal');
            if (modal) modal.style.display = 'none';
        }

        function openStockModal(button) {
            const id = button.dataset.id;
            const name = button.dataset.name;
            const stock = button.dataset.stock;
            const modal = document.getElementById('stockModal');
            if (!modal) return;
            const activeInput = document.getElementById('stockActiveTab');
            if (activeInput) activeInput.value = currentTab;
            document.getElementById('modal_medicine_id').value = id;
            document.getElementById('modal_medicine_name').value = name;
            document.getElementById('current_stock').value = stock;
            document.getElementById('new_stock').value = stock;
            modal.style.display = 'flex';
        }

        function closeStockModal() {
            const modal = document.getElementById('stockModal');
            if (modal) modal.style.display = 'none';
        }

        function openEditMedicineModal(button) {
            const modal = document.getElementById('editMedicineModal');
            if (!modal) return;
            const activeInput = document.getElementById('editMedicineActiveTab');
            if (activeInput) activeInput.value = currentTab;

            document.getElementById('edit_medicine_id').value = button.dataset.id;
            document.getElementById('edit_medicine_name').value = button.dataset.name;
            document.getElementById('edit_generic_name').value = button.dataset.generic;
            document.getElementById('edit_category').value = button.dataset.category;
            document.getElementById('edit_stock_quantity').value = button.dataset.stock;
            document.getElementById('edit_min_stock_level').value = button.dataset.min;
            document.getElementById('edit_unit').value = button.dataset.unit;
            document.getElementById('edit_expiry_date').value = button.dataset.expiry;
            document.getElementById('edit_batch_number').value = button.dataset.batch;
            document.getElementById('edit_location').value = button.dataset.location;
            document.getElementById('edit_description').value = button.dataset.description;

            modal.style.display = 'flex';
        }

        function closeEditMedicineModal() {
            const modal = document.getElementById('editMedicineModal');
            if (modal) modal.style.display = 'none';
        }

        function openEditCategoryModal(id, name) {
            const modal = document.getElementById('editCategoryModal');
            if (!modal) return;
            const activeInput = document.getElementById('editCategoryActiveTab');
            if (activeInput) activeInput.value = currentTab;
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_category_name').value = name;
            modal.style.display = 'flex';
        }

        function closeEditCategoryModal() {
            const modal = document.getElementById('editCategoryModal');
            if (modal) modal.style.display = 'none';
        }

        function deleteMedicine(id, name) {
            if (confirm('Are you sure you want to delete the medicine "' + name + '"? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="medicine_id" value="' + id + '">' +
                                 '<input type="hidden" name="delete_medicine" value="1">' +
                                 '<input type="hidden" name="active_tab" value="' + currentTab + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteCategory(id, name) {
            if (confirm('Are you sure you want to delete the category "' + name + '"? This will only work if no medicines are using this category.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="category_id" value="' + id + '">' +
                                 '<input type="hidden" name="delete_category" value="1">' +
                                 '<input type="hidden" name="active_tab" value="' + currentTab + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Live search and filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search-input');
            const categoryFilter = document.getElementById('category-filter');
            const tabBar = document.querySelector('.medicine-tabs');
            let touchStartX = 0;
            let touchEndX = 0;

            function filterMedicines() {
                const searchValue = searchInput.value.toLowerCase();
                const categoryValue = categoryFilter.value.toLowerCase();
                const table = document.getElementById('medicine-table');
                if (!table) return;
                const tbody = table.querySelector('tbody');
                if (!tbody) return;
                let visibleCount = 0;
                tbody.querySelectorAll('tr').forEach(row => {
                    const name = row.cells[0].textContent.toLowerCase();
                    const category = row.cells[2].textContent.toLowerCase();
                    const matchesSearch = !searchValue || name.includes(searchValue);
                    const matchesCategory = !categoryValue || category === categoryValue;
                    if (matchesSearch && matchesCategory) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                const countEl = document.getElementById('results-count');
                if (countEl) countEl.textContent = 'Showing ' + visibleCount + ' medicine' + (visibleCount !== 1 ? 's' : '');
            }

            function handleSwipe(direction) {
                const activeButton = document.querySelector('.tab-button.active');
                const buttons = Array.from(document.querySelectorAll('.tab-button'));
                if (!activeButton || buttons.length === 0) return;

                const currentIndex = buttons.indexOf(activeButton);
                let nextIndex = currentIndex;

                if (direction === 'left') {
                    nextIndex = Math.min(buttons.length - 1, currentIndex + 1);
                } else if (direction === 'right') {
                    nextIndex = Math.max(0, currentIndex - 1);
                }

                if (nextIndex !== currentIndex) {
                    buttons[nextIndex].click();
                    buttons[nextIndex].scrollIntoView({ behavior: 'smooth', inline: 'center' });
                }
            }

            if (tabBar) {
                tabBar.addEventListener('touchstart', function(event) {
                    touchStartX = event.touches[0].clientX;
                }, { passive: true });

                tabBar.addEventListener('touchmove', function(event) {
                    touchEndX = event.touches[0].clientX;
                }, { passive: true });

                tabBar.addEventListener('touchend', function() {
                    const swipeDistance = touchStartX - touchEndX;
                    if (Math.abs(swipeDistance) > 60) {
                        handleSwipe(swipeDistance > 0 ? 'left' : 'right');
                    }
                    touchStartX = 0;
                    touchEndX = 0;
                });
            }

            searchInput.addEventListener('input', filterMedicines);
            categoryFilter.addEventListener('change', filterMedicines);

            const initialTab = '<?= htmlspecialchars($activeMedicineTab) ?>';
            setMedicineTab(initialTab, document.querySelector(`[data-category="${initialTab}"]`));
        });

        function deleteExpiredMedicine(id, name) {
            if (confirm('Remove expired medicine "' + name + '" from inventory?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="medicine_id" value="' + id + '">' +
                                 '<input type="hidden" name="delete_medicine" value="1">' +
                                 '<input type="hidden" name="active_tab" value="' + currentTab + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function removeExpiredMedicines() {
            if (confirm('Remove all medicines expiring within 30 days?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="remove_expired_medicines" value="1">' +
                                 '<input type="hidden" name="active_tab" value="' + currentTab + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Batch Management Functions
        function openBatchesModal(button) {
            const medicineId = button.dataset.id;
            const medicineName = button.dataset.name;
            const medicineUnit = button.dataset.unit;
            
            // Set header info
            document.getElementById('batchModalMedicineId').value = medicineId;
            document.getElementById('batchModalMedicineName').textContent = medicineName;
            
            // Reset the add batch form
            const addBatchForm = document.getElementById('addBatchForm');
            if (addBatchForm) {
                addBatchForm.reset();
                document.getElementById('batchModalMedicineId').value = medicineId;
            }
            
            // Load batches
            loadBatches(medicineId);
            
            // Open modal
            const modal = document.getElementById('batchesModal');
            if (modal) modal.style.display = 'flex';
            // Hide chat widget to avoid overlap on small screens
            document.body.classList.add('modal-open');
        }

        function closeBatchesModal() {
            const modal = document.getElementById('batchesModal');
            if (modal) modal.style.display = 'none';
            document.body.classList.remove('modal-open');
        }

        function loadBatches(medicineId) {
            // Make AJAX call to fetch batches
            fetch('get_batches.php?medicine_id=' + medicineId)
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('batchesTableBody');
                    if (!tbody) return;
                    
                    if (!data.batches || data.batches.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #999;">No active batches found</td></tr>';
                        return;
                    }
                    
                    tbody.innerHTML = data.batches.map(batch => `
                        <tr>
                            <td data-label="Batch Number">${batch.batch_number}</td>
                            <td data-label="Quantity">${batch.quantity}</td>
                            <td data-label="Expiry Date">${batch.expiry_date ? batch.expiry_date : 'No expiry'}</td>
                            <td data-label="Location">${batch.location || '-'}</td>
                            <td data-label="Status">
                                ${batch.expiry_date && new Date(batch.expiry_date) < new Date() 
                                    ? '<span style="color: #dc3545; font-weight: bold;">EXPIRED</span>'
                                    : batch.expiry_date && new Date(batch.expiry_date) <= new Date(Date.now() + 30*24*60*60*1000)
                                    ? '<span style="color: #ffc107; font-weight: bold;">EXPIRING SOON</span>'
                                    : '<span style="color: #28a745;">ACTIVE</span>'}
                            </td>
                            <td data-label="Actions">
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <button type="button" class="btn btn-warning btn-sm" onclick="openEditBatchModal(${batch.id}, '${batch.batch_number}', ${batch.quantity}, '${batch.expiry_date || ''}')">
                                        <i class="fa-solid fa-pencil"></i> Edit
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="deactivateBatch(${batch.id}, '${batch.batch_number}')">
                                        <i class="fa-solid fa-trash"></i> Deactivate
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `).join('');
                })
                .catch(error => console.error('Error loading batches:', error));
        }

        function openEditBatchModal(batchId, batchNumber, quantity, expiryDate) {
            document.getElementById('editBatchId').value = batchId;
            document.getElementById('editBatchNumber').value = batchNumber;
            document.getElementById('editBatchQuantity').value = quantity;
            document.getElementById('editBatchExpiry').value = expiryDate;
            
            const modal = document.getElementById('editBatchModal');
            if (modal) modal.style.display = 'flex';
            document.body.classList.add('modal-open');
        }

        function closeEditBatchModal() {
            const modal = document.getElementById('editBatchModal');
            if (modal) modal.style.display = 'none';
            document.body.classList.remove('modal-open');
        }

        function deactivateBatch(batchId, batchNumber) {
            if (confirm('Are you sure you want to deactivate batch "' + batchNumber + '"?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="batch_id" value="' + batchId + '">' +
                                 '<input type="hidden" name="deactivate_batch" value="1">' +
                                 '<input type="hidden" name="active_tab" value="' + currentTab + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addMedicineModal');
            const categoryModal = document.getElementById('addCategoryModal');
            const editMedicineModal = document.getElementById('editMedicineModal');
            const editCategoryModal = document.getElementById('editCategoryModal');
            const stockModal = document.getElementById('stockModal');
            const batchesModal = document.getElementById('batchesModal');
            const editBatchModal = document.getElementById('editBatchModal');

            if (event.target == addModal) {
                addModal.style.display = 'none';
            }
            if (event.target == categoryModal) {
                categoryModal.style.display = 'none';
            }
            if (event.target == editMedicineModal) {
                editMedicineModal.style.display = 'none';
            }
            if (event.target == editCategoryModal) {
                editCategoryModal.style.display = 'none';
            }
            if (event.target == stockModal) {
                stockModal.style.display = 'none';
            }
            if (event.target == batchesModal) {
                batchesModal.style.display = 'none';
                document.body.classList.remove('modal-open');
            }
            if (event.target == editBatchModal) {
                editBatchModal.style.display = 'none';
                document.body.classList.remove('modal-open');
            }
        }

        // Auto-hide success messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = document.getElementById('success-message');
            if (successMessage) {
                setTimeout(function() {
                    successMessage.style.transition = 'opacity 0.5s ease';
                    successMessage.style.opacity = '0';
                    setTimeout(function() {
                        successMessage.style.display = 'none';
                    }, 500);
                }, 5000);
            }
        });
    </script>
    <?php include '../AI CHAT BOT/chat_widget.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function isScreenMobile() {
                return window.innerWidth >= 320 && window.innerWidth <= 768;
            }
            let touchStartY = 0;
            const SWIPE_THRESHOLD = 100;
            const SWIPE_START_LIMIT = 100;
            document.addEventListener('touchstart', function(e) {
                if (!isScreenMobile() || window.scrollY > 10) return;
                if (e.touches.length !== 1) return;
                touchStartY = e.touches[0].clientY;
            }, { passive: true });
            document.addEventListener('touchend', function(e) {
                if (!isScreenMobile() || window.scrollY > 10) return;
                if (e.changedTouches.length !== 1) return;
                const touchEndY = e.changedTouches[0].clientY;
                const distance = touchEndY - touchStartY;
                if (touchStartY <= SWIPE_START_LIMIT && distance >= SWIPE_THRESHOLD) {
                    const spinner = document.getElementById('swipe-refresh-spinner');
                    if (spinner) {
                        spinner.style.display = 'flex';
                    }
                    setTimeout(function() {
                        window.location.reload();
                    }, 800);
                }
            }, { passive: true });
        });
    </script>
</body>
</html>
