<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/library_dashboard_functions.php';
require_login();
$user = current_user();
if ($user['role'] !== 'teacher' || $user['head_service'] !== 'library') {
    header('Location: /auth/teacher_login.php');
    exit;
}
ensure_library_schema();
$message = '';
$messageType = 'success';
$showEditModal = false;
$editingBook = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Log all POST data for debugging
    error_log('=== FORM SUBMISSION DEBUG ===');
    error_log('Action: ' . $action);
    error_log('Full POST data:' . json_encode($_POST));
    error_log('=====================');
    
    if ($action === 'add_book') {
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $lccLetterLine = trim($_POST['lcc_letter_line'] ?? '');
        $lccClassNumber = trim($_POST['lcc_class_number'] ?? '');
        $lccCutterCode = trim($_POST['lcc_cutter_code'] ?? '');
        $lccPublishedYear = trim($_POST['lcc_published_year'] ?? '');
        $additionalInfoType = trim($_POST['additional_info_type'] ?? '');
        $additionalInfoQuantity = $additionalInfoType ? max(1, (int)($_POST['additional_info_quantity'] ?? 1)) : null;
        // Default to 'auto' for copies type, 'single' for others
        $additionalInfoGeneration = trim($_POST['additional_info_generation'] ?? '');
        if (!$additionalInfoGeneration && $additionalInfoType) {
            $additionalInfoGeneration = ($additionalInfoType === 'copies') ? 'auto' : 'single';
        }
        $singleInputEntriesJson = trim($_POST['single_input_entries'] ?? '[]');
        $singleInputEntries = json_decode($singleInputEntriesJson, true) ?: [];
        
        // DEBUG: Log received data
        error_log('ADD_BOOK DEBUG - Title: ' . $title . ', Author: ' . $author);
        error_log('ADD_BOOK DEBUG - LCC Fields: LL=' . $lccLetterLine . ', CN=' . $lccClassNumber . ', CC=' . $lccCutterCode . ', PY=' . $lccPublishedYear);
        error_log('ADD_BOOK DEBUG - Additional Info: Type=' . $additionalInfoType . ', Qty=' . $additionalInfoQuantity . ', Generation=' . $additionalInfoGeneration);
        error_log('ADD_BOOK DEBUG - Single Entries JSON: ' . $singleInputEntriesJson);
        error_log('ADD_BOOK DEBUG - Single Entries Decoded: ' . json_encode($singleInputEntries));
        
        $preferredCategory = trim($_POST['category_select'] ?? '');
        $newCategory = trim($_POST['new_category'] ?? '');
        $category = $newCategory !== '' ? $newCategory : ($preferredCategory ?: 'General');
        $overview = trim($_POST['overview'] ?? '');
        $coverUrl = '';

        if ($preferredCategory !== '' && $newCategory !== '') {
            $message = 'Please choose either an existing category or add a new category, not both.';
            $messageType = 'error';
        }

        if (!empty($newCategory)) {
            add_library_category($newCategory);
        }
        if (!empty($_FILES['cover']['name'])) {
            $uploaded = save_library_cover_upload($_FILES['cover']);
            if ($uploaded === null) {
                $message = 'Cover upload failed. Use JPG, PNG, or WebP format and try again.';
                $messageType = 'error';
            } else {
                $coverUrl = $uploaded;
            }
        }

        if (!$title || !$author) {
            $message = 'Please enter a title and author to add a new book.';
            $messageType = 'error';
        } elseif ($messageType === 'error' && $message !== '') {
            // keep existing error from cover upload
        } else {
            $bookResult = add_library_book([
                'title' => $title,
                'author' => $author,
                'isbn' => $isbn,
                'lcc_letter_line' => $lccLetterLine,
                'lcc_class_number' => $lccClassNumber,
                'lcc_cutter_code' => $lccCutterCode,
                'lcc_published_year' => $lccPublishedYear,
                'additional_info_type' => $additionalInfoType,
                'additional_info_quantity' => $additionalInfoQuantity,
                'category' => $category,
                'total_copies' => 0, // will be set by copies
                'available_copies' => 0, // will be set by copies
                'overview' => $overview,
                'rating' => 0,
                'cover_url' => $coverUrl,
            ]);
            
            if ($bookResult) {
                $bookId = is_int($bookResult) ? $bookResult : null;
                
                // Handle single input entries
                if ($bookId && $additionalInfoType) {
                    try {
                        $pdo = get_db();
                        $prefix = match (strtolower($additionalInfoType)) {
                            'volume' => 'V',
                            'part' => 'P',
                            'number' => 'N',
                            'copies' => 'C',
                            default => 'C',
                        };
                        
                        // Handle single input (specific numbers entered by user)
                        if ($additionalInfoGeneration === 'single' && !empty($singleInputEntries)) {
                            foreach ($singleInputEntries as $entry) {
                                $identifier = $prefix . $entry;
                                $stmt = $pdo->prepare('INSERT INTO library_book_copies (book_id, copy_type, copy_number, identifier, lcc_letter_line, lcc_class_number, lcc_cutter_code, lcc_published_year) VALUES (:book_id, :copy_type, :copy_number, :identifier, :lcc_letter_line, :lcc_class_number, :lcc_cutter_code, :lcc_published_year)');
                                $stmt->execute([
                                    'book_id' => $bookId,
                                    'copy_type' => $additionalInfoType,
                                    'copy_number' => (int)$entry,
                                    'identifier' => $identifier,
                                    'lcc_letter_line' => $lccLetterLine,
                                    'lcc_class_number' => $lccClassNumber,
                                    'lcc_cutter_code' => $lccCutterCode,
                                    'lcc_published_year' => $lccPublishedYear,
                                ]);
                            }
                            update_library_book_stock($bookId, count($singleInputEntries), count($singleInputEntries));
                        }
                        // Handle auto-generation (auto-generate 1 to quantity)
                        elseif ($additionalInfoGeneration === 'auto' && $additionalInfoQuantity > 0) {
                            for ($i = 1; $i <= $additionalInfoQuantity; $i++) {
                                $identifier = $prefix . $i;
                                $stmt = $pdo->prepare('INSERT INTO library_book_copies (book_id, copy_type, copy_number, identifier, lcc_letter_line, lcc_class_number, lcc_cutter_code, lcc_published_year) VALUES (:book_id, :copy_type, :copy_number, :identifier, :lcc_letter_line, :lcc_class_number, :lcc_cutter_code, :lcc_published_year)');
                                $stmt->execute([
                                    'book_id' => $bookId,
                                    'copy_type' => $additionalInfoType,
                                    'copy_number' => $i,
                                    'identifier' => $identifier,
                                    'lcc_letter_line' => $lccLetterLine,
                                    'lcc_class_number' => $lccClassNumber,
                                    'lcc_cutter_code' => $lccCutterCode,
                                    'lcc_published_year' => $lccPublishedYear,
                                ]);
                            }
                            update_library_book_stock($bookId, $additionalInfoQuantity, $additionalInfoQuantity);
                        }
                    } catch (Exception $e) {
                        // Log error but don't fail the entire operation
                    }
                }
                
                header('Location: library_catalog.php?tab=management&message=' . urlencode('New book added to inventory successfully.') . '&message_type=success');
                exit;
            } else {
                $message = 'Unable to add the book. Please try again.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'save_book') {
        $bookId = (int)($_POST['book_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $lccLetterLine = trim($_POST['lcc_letter_line'] ?? '');
        $lccClassNumber = trim($_POST['lcc_class_number'] ?? '');
        $lccCutterCode = trim($_POST['lcc_cutter_code'] ?? '');
        $lccPublishedYear = trim($_POST['lcc_published_year'] ?? '');
        $additionalInfoType = trim($_POST['additional_info_type'] ?? '');
        $additionalInfoQuantity = $additionalInfoType ? max(1, (int)($_POST['additional_info_quantity'] ?? 1)) : null;
        // Default to 'auto' for copies type, 'single' for others
        $additionalInfoGeneration = trim($_POST['additional_info_generation'] ?? '');
        if (!$additionalInfoGeneration && $additionalInfoType) {
            $additionalInfoGeneration = ($additionalInfoType === 'copies') ? 'auto' : 'single';
        }
        $singleInputEntriesJson = trim($_POST['single_input_entries'] ?? '[]');
        $singleInputEntries = json_decode($singleInputEntriesJson, true) ?: [];
        
        // DEBUG: Log received data
        error_log('SAVE_BOOK DEBUG - BookID: ' . $bookId . ', Title: ' . $title . ', Author: ' . $author);
        error_log('SAVE_BOOK DEBUG - LCC Fields: LL=' . $lccLetterLine . ', CN=' . $lccClassNumber . ', CC=' . $lccCutterCode . ', PY=' . $lccPublishedYear);
        error_log('SAVE_BOOK DEBUG - Additional Info: Type=' . $additionalInfoType . ', Qty=' . $additionalInfoQuantity . ', Generation=' . $additionalInfoGeneration);
        error_log('SAVE_BOOK DEBUG - Single Entries JSON: ' . $singleInputEntriesJson);
        error_log('SAVE_BOOK DEBUG - Single Entries Decoded: ' . json_encode($singleInputEntries));
        
        $preferredCategory = trim($_POST['category_select'] ?? '');
        $newCategory = trim($_POST['new_category'] ?? '');
        $category = $newCategory !== '' ? $newCategory : ($preferredCategory ?: 'General');
        $totalCopies = max(0, (int)($_POST['total_copies'] ?? 0));
        $availableCopies = max(0, (int)($_POST['available_copies'] ?? 0));
        $overview = trim($_POST['overview'] ?? '');
        $coverUrl = '';

        if ($preferredCategory !== '' && $newCategory !== '') {
            $message = 'Please choose either an existing category or add a new category, not both.';
            $messageType = 'error';
        }

        if ($bookId > 0) {
            $editingBook = get_library_book_by_id($bookId);
        }
        if (!empty($newCategory)) {
            add_library_category($newCategory);
        }
        if (!empty($_FILES['cover']['name'])) {
            $uploaded = save_library_cover_upload($_FILES['cover']);
            if ($uploaded === null) {
                $message = 'Cover upload failed. Use JPG, PNG, or WebP format and try again.';
                $messageType = 'error';
            } else {
                $coverUrl = $uploaded;
            }
        } elseif (!empty($editingBook['cover_url'])) {
            $coverUrl = $editingBook['cover_url'];
        }

        if ($bookId <= 0 || !$editingBook) {
            $message = 'Please choose a valid book to update.';
            $messageType = 'error';
        } elseif (!$title || !$author) {
            $message = 'Please enter a title and author to update the book.';
            $messageType = 'error';
        } elseif ($messageType === 'error' && $message !== '') {
            // keep upload error
        } else {
            if (update_library_book([
                'id' => $bookId,
                'title' => $title,
                'author' => $author,
                'isbn' => $isbn,
                'lcc_letter_line' => $lccLetterLine,
                'lcc_class_number' => $lccClassNumber,
                'lcc_cutter_code' => $lccCutterCode,
                'lcc_published_year' => $lccPublishedYear,
                'additional_info_type' => $additionalInfoType,
                'additional_info_quantity' => $additionalInfoQuantity,
                'category' => $category,
                'total_copies' => $editingBook['total_copies'] ?? 0, // keep existing
                'available_copies' => $editingBook['available_copies'] ?? 0, // keep existing
                'overview' => $overview,
                'cover_url' => $coverUrl,
            ])) {
                // Handle single input entries for edit
                if ($additionalInfoType) {
                    try {
                        $pdo = get_db();
                        $prefix = match (strtolower($additionalInfoType)) {
                            'volume' => 'V',
                            'part' => 'P',
                            'number' => 'N',
                            'copies' => 'C',
                            default => 'C',
                        };
                        
                        $totalAdded = 0;
                        
                        // Handle single input (specific numbers entered by user)
                        if ($additionalInfoGeneration === 'single' && !empty($singleInputEntries)) {
                            foreach ($singleInputEntries as $entry) {
                                $identifier = $prefix . $entry;
                                $stmt = $pdo->prepare('INSERT INTO library_book_copies (book_id, copy_type, copy_number, identifier, lcc_letter_line, lcc_class_number, lcc_cutter_code, lcc_published_year) VALUES (:book_id, :copy_type, :copy_number, :identifier, :lcc_letter_line, :lcc_class_number, :lcc_cutter_code, :lcc_published_year)');
                                if ($stmt->execute([
                                    'book_id' => $bookId,
                                    'copy_type' => $additionalInfoType,
                                    'copy_number' => (int)$entry,
                                    'identifier' => $identifier,
                                    'lcc_letter_line' => $lccLetterLine,
                                    'lcc_class_number' => $lccClassNumber,
                                    'lcc_cutter_code' => $lccCutterCode,
                                    'lcc_published_year' => $lccPublishedYear,
                                ])) {
                                    $totalAdded++;
                                }
                            }
                        }
                        // Handle auto-generation (auto-generate 1 to quantity)
                        elseif ($additionalInfoGeneration === 'auto' && $additionalInfoQuantity > 0) {
                            for ($i = 1; $i <= $additionalInfoQuantity; $i++) {
                                $identifier = $prefix . $i;
                                $stmt = $pdo->prepare('INSERT INTO library_book_copies (book_id, copy_type, copy_number, identifier, lcc_letter_line, lcc_class_number, lcc_cutter_code, lcc_published_year) VALUES (:book_id, :copy_type, :copy_number, :identifier, :lcc_letter_line, :lcc_class_number, :lcc_cutter_code, :lcc_published_year)');
                                if ($stmt->execute([
                                    'book_id' => $bookId,
                                    'copy_type' => $additionalInfoType,
                                    'copy_number' => $i,
                                    'identifier' => $identifier,
                                    'lcc_letter_line' => $lccLetterLine,
                                    'lcc_class_number' => $lccClassNumber,
                                    'lcc_cutter_code' => $lccCutterCode,
                                    'lcc_published_year' => $lccPublishedYear,
                                ])) {
                                    $totalAdded++;
                                }
                            }
                        }
                        
                        // Update total and available copies count
                        if ($totalAdded > 0) {
                            $newTotal = ($editingBook['total_copies'] ?? 0) + $totalAdded;
                            $newAvailable = ($editingBook['available_copies'] ?? 0) + $totalAdded;
                            update_library_book_stock($bookId, $newTotal, $newAvailable);
                        }
                    } catch (Exception $e) {
                        // Log error but don't fail the entire operation
                    }
                }
                
                $message = 'Book details updated successfully.';
                $editingBook = get_library_book_by_id($bookId);
            } else {
                $message = 'Unable to update the book. Please try again.';
                $messageType = 'error';
            }
        }
        if ($messageType === 'error') {
            $showEditModal = true;
        }
    } elseif ($action === 'update_stock') {
        $bookId = (int)($_POST['book_id'] ?? 0);
        $totalCopies = max(0, (int)($_POST['total_copies'] ?? 0));
        $availableCopies = max(0, (int)($_POST['available_copies'] ?? 0));

        if ($bookId <= 0 || $totalCopies <= 0) {
            $message = 'Please select a valid book and enter a valid total copy count.';
            $messageType = 'error';
        } elseif ($availableCopies > $totalCopies) {
            $message = 'Available copies cannot exceed total copies.';
            $messageType = 'error';
        } else {
            if (update_library_book_stock($bookId, $totalCopies, $availableCopies)) {
                $message = 'Book stock updated successfully.';
            } else {
                $message = 'Unable to update the stock. Please try again.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'add_copies') {
        $bookId = (int)($_POST['book_id'] ?? 0);
        $copyType = trim($_POST['copy_type'] ?? 'Copy');
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));

        if ($bookId <= 0 || $quantity <= 0) {
            $message = 'Please select a valid book and quantity to add.';
            $messageType = 'error';
        } else {
            $newCopies = add_library_book_copies($bookId, $quantity, $copyType);
            if ($newCopies) {
                $message = sprintf('Added %d %s(s) successfully.', $quantity, htmlspecialchars($copyType));
            } else {
                $message = 'Unable to add copies. Please try again.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'add_category') {
        $categoryName = trim($_POST['category_name'] ?? '');
        if ($categoryName === '') {
            $message = 'Enter a category name to add.';
            $messageType = 'error';
        } elseif (add_library_category($categoryName)) {
            $message = 'Category added successfully.';
        } else {
            $message = 'Unable to add category. It may already exist.';
            $messageType = 'error';
        }
    } elseif ($action === 'edit_category') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['category_name'] ?? '');
        if ($categoryId <= 0 || $name === '') {
            $message = 'Please provide a valid category name.';
            $messageType = 'error';
        } elseif (update_library_category($categoryId, $name)) {
            $message = 'Category updated successfully.';
        } else {
            $message = 'Unable to update category.';
            $messageType = 'error';
        }
    } elseif ($action === 'delete_category') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        if ($categoryId > 0 && delete_library_category($categoryId)) {
            $message = 'Category deleted and related books reset to General.';
        } else {
            $message = 'Unable to delete the category.';
            $messageType = 'error';
        }
    } elseif ($action === 'add_resource_category') {
        $categoryName = trim($_POST['resource_category_name'] ?? '');
        if ($categoryName === '') {
            $message = 'Enter a category name to add for E-Resources.';
            $messageType = 'error';
        } elseif (add_library_resource_category($categoryName)) {
            $message = 'E-Resource category added successfully.';
        } else {
            $message = 'Unable to add E-Resource category. It may already exist.';
            $messageType = 'error';
        }
    } elseif ($action === 'edit_resource_category') {
        $categoryId = (int)($_POST['resource_category_id'] ?? 0);
        $name = trim($_POST['resource_category_name'] ?? '');
        if ($categoryId <= 0 || $name === '') {
     $message = 'Please provide a valid E-Resource category name.';
            $messageType = 'error';
        } elseif (update_library_resource_category($categoryId, $name)) {
            $message = 'E-Resource category updated successfully.';
        } else {
            $message = 'Unable to update E-Resource category.';
            $messageType = 'error';
        }
    } elseif ($action === 'delete_resource_category') {
        $categoryId = (int)($_POST['resource_category_id'] ?? 0);
        if ($categoryId > 0 && delete_library_resource_category($categoryId)) {
            $message = 'E-Resource category deleted and related resources reset to General.';
        } else {
            $message = 'Unable to delete the E-Resource category.';
            $messageType = 'error';
        }
    } elseif ($action === 'approve_resource') {
        $resourceId = (int)($_POST['resource_id'] ?? 0);
        $category = trim($_POST['category'] ?? 'General');
        $courseYear = trim($_POST['course_year'] ?? '');
        
        if ($resourceId > 0 & approve_library_resource($resourceId, $user['id'], $category, $courseYear)) {
            $message = 'Resource approved and published successfully.';
        } else {
            $message = 'Unable to approve the resource.';
            $messageType = 'error';
        }
    } elseif ($action === 'reject_resource') {
        $resourceId = (int)($_POST['resource_id'] ?? 0);
        if ($resourceId > 0 && reject_library_resource($resourceId)) {
            $message = 'Resource rejected and removed.';
        } else {
            $message = 'Unable to reject the resource.';
            $messageType = 'error';
        }
    } elseif ($action === 'add_manual_resource') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $courseYear = trim($_POST['course_year'] ?? '');

        if (!$title || !$description) {
            $message = 'Please fill in all required fields.';
            $messageType = 'error';
        } elseif (empty($_FILES['file']['name'])) {
            $message = 'Please select a file to upload.';
            $messageType = 'error';
        } else {
            $file = $_FILES['file'];
            $maxSize = 50 * 1024 * 1024; // 50 MB
            $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'txt'];
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if ($file['size'] > $maxSize) {
                $message = 'File size exceeds 50 MB limit.';
                $messageType = 'error';
            } elseif (!in_array($fileExt, $allowedTypes)) {
                $message = 'File type not allowed. Supported: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, ZIP, TXT';
                $messageType = 'error';
            } else {
                $uploadsDir = __DIR__ . '/dashboard/uploads/e-resources';
                if (!is_dir($uploadsDir)) {
                    @mkdir($uploadsDir, 0755, true);
                }

                $fileName = uniqid() . '_' . str_replace(' ', '_', $file['name']);
                $filePath = 'dashboard/uploads/e-resources/' . $fileName;
                $fullPath = $uploadsDir . '/' . $fileName;

                if (move_uploaded_file($file['tmp_name'], $fullPath)) {
                    $pdo = get_db();
                    $stmt = $pdo->prepare('INSERT INTO library_resources (uploaded_by, title, description, file_url, file_name, file_size, file_type, is_approved, approved_by, approved_at, category, course_year, created_at) VALUES (:uploaded_by, :title, :description, :file_url, :file_name, :file_size, :file_type, 1, :approved_by, NOW(), :category, :course_year, NOW())');
                     if ($stmt->execute([
                        'uploaded_by' => $user['id'],
                        'title' => $title,
                        'description' => $description,
                        'file_url' => $filePath,
                        'file_name' => $file['name'],
                        'file_size' => $file['size'],
                        'file_type' => $fileExt,
                        'approved_by' => $user['id'],
                        'category' => $category,
                        'course_year' => $courseYear,
                    ])) {
                        $message = 'E-Resource added and published to library.';
                    } else {
                        @unlink($fullPath);
                        $message = 'Unable to save resource information. Please try again.';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Unable to upload file. Please try again.';
                    $messageType = 'error';
                }
            }
        }
    } elseif ($action === 'save_resource') {
        $resourceId = (int)($_POST['resource_id'] ?? 0);
        $category = trim($_POST['category'] ?? 'General');
        $courseYear = trim($_POST['course_year'] ?? '');

        if ($resourceId > 0) {
            $pdo = get_db();
            $stmt = $pdo->prepare('UPDATE library_resources SET category = :category, course_year = :course_year WHERE id = :id AND is_approved = 1');
            if ($stmt->execute(['category' => $category, 'course_year' => $courseYear, 'id' => $resourceId])) {
                $message = 'Resource updated successfully.';
            } else {
                $message = 'Unable to update resource.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'delete_resource') {
        $resourceId = (int)($_POST['resource_id'] ?? 0);
        if ($resourceId > 0) {
            $pdo = get_db();
            $stmt = $pdo->prepare('DELETE FROM library_resources WHERE id = :id AND is_approved = 1');
            if ($stmt->execute(['id' => $resourceId])) {
                $message = 'Resource deleted.';
            } else {
                $message = 'Unable to delete resource.';
                $messageType = 'error';
            }
        }
    } elseif ($action === 'add_open_access_resource') {
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $url = trim($_POST['url'] ?? '');

        if ($name === '' || $category === '' || $url === '') {
            $message = 'Please fill in all required fields for the Open Access resource.';
            $messageType = 'error';
        } elseif (!str_starts_with($url, 'https://')) {
            $message = 'Open Access links must start with https:// for student security.';
            $messageType = 'error';
        } elseif (add_open_access_resource($user['id'], $name, $category, $description, $url)) {
            $message = 'Open Access resource added successfully.';
        } else {
            $message = 'Unable to save the Open Access resource. Please try again.';
            $messageType = 'error';
        }
    } elseif ($action === 'save_open_access_resource') {
        $resourceId = (int)($_POST['resource_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($resourceId <= 0 || $name === '' || $category === '' || $url === '') {
            $message = 'Please fill in all required fields to update the Open Access resource.';
            $messageType = 'error';
        } elseif (!str_starts_with($url, 'https://')) {
            $message = 'Open Access links must start with https:// for student security.';
            $messageType = 'error';
        } elseif (update_open_access_resource($resourceId, $name, $category, $description, $url, (bool)$isActive)) {
            $message = 'Open Access resource updated successfully.';
        } else {
            $message = 'Unable to update the Open Access resource. Please try again.';
            $messageType = 'error';
        }
    } elseif ($action === 'delete_open_access_resource') {
        $resourceId = (int)($_POST['resource_id'] ?? 0);
        if ($resourceId > 0 && delete_open_access_resource($resourceId)) {
            $message = 'Open Access resource removed successfully.';
        } else {
            $message = 'Unable to remove the Open Access resource.';
            $messageType = 'error';
        }
    } elseif ($action === 'remove_book') {
        $bookId = (int)($_POST['book_id'] ?? 0);
        if ($bookId > 0) {
            if (remove_library_book($bookId)) {
                $message = 'Book removed from inventory successfully.';
            } else {
                $message = 'Unable to remove the book. Please try again.';
                $messageType = 'error';
            }
        } else {
            $message = 'Please choose a valid book to remove.';
            $messageType = 'error';
        }
    }
}
$message = $_GET['message'] ?? $message;
$messageType = in_array($_GET['message_type'] ?? '', ['error', 'success'], true) ? $_GET['message_type'] : $messageType;
$activeTab = $_POST['active_tab'] ?? $_GET['tab'] ?? 'management';
$activeEresourcesTab = $_POST['active_eresources_tab'] ?? $_GET['eresources_tab'] ?? 'pending';

// Ensure default E-Resource categories exist
ensure_default_eresource_categories();

$books = get_all_library_books();
$categories = get_library_categories();
$resourceCategories = get_library_resource_categories();
$openAccessCategories = get_open_access_resource_categories();
$openAccessResources = get_open_access_resources();
$pendingReservations = get_active_library_reservations();
$lowStock = 0;
$totalCopiesAll = 0;
$availableCopiesAll = 0;
foreach ($books as $book) {
    $available = (int)$book['available_copies'];
    $totalCopiesAll += (int)$book['total_copies'];
    $availableCopiesAll += $available;
    if ($available <= 1) {
        $lowStock++;
    }
}
$categoryCount = count($categories);
$currentPage = basename($_SERVER['PHP_SELF']);
$serviceOpen = false;
$manageOpen = false;
?> 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Catalog | PASS Support System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    <link rel="stylesheet" href="assets/css/library-header.css">
    <style>
        @font-face {
            font-family: 'ElephantLocal';
            src: url('assets/FONTS/ELEPHNT.TTF') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        .nav-brand h2 {
            font-family: 'ElephantLocal', 'Playfair Display', serif;
        }
        .library-page-header { position: relative; min-height: 245px; display: flex; align-items: center; overflow: hidden; margin: 0 0 24px; padding: 42px 48px 30px; background: #f8f1e7; border-radius: 0 0 24px 24px; }
        .library-page-header::before { content: ''; position: absolute; z-index: 0; inset: 0 auto 0 0; width: 3px; background: #861b17; }
        .library-page-header > div:first-child { position: relative; z-index: 2; width: 65%; }
        .library-page-header .eyebrow { margin: 0; color: #861b17 !important; font-family: Arial, sans-serif; font-size: 14px; font-weight: 700; letter-spacing: 3px; line-height: 1; text-transform: uppercase; }
        .library-page-header h1 { margin: 14px 0 12px; color: #111 !important; font-family: Georgia, serif; font-size: clamp(36px, 4vw, 62px); font-weight: 600; line-height: 1.05; }
        .library-page-header .dashboard-subtitle { max-width: 760px; margin: 0; color: #34302d !important; font-family: Arial, sans-serif; font-size: 16px; line-height: 1.5; }
        .library-header__art { position: absolute; z-index: 1; top: 0; right: 0; width: 43%; height: 100%; color: #d7a58d; opacity: .78; }
        .library-header__art::before { content: ''; position: absolute; right: 10%; bottom: 4%; width: 84%; height: 28%; background: radial-gradient(ellipse at center, rgba(235, 205, 167, .5) 0 42%, transparent 43%); border-radius: 50%; }
        .library-header__art::after { content: ''; position: absolute; top: 23%; left: 3%; width: 76%; height: 45%; border: 1px solid rgba(215, 165, 141, .45); border-left-color: transparent; border-radius: 50%; transform: rotate(-13deg); }
        .library-header__art i { position: absolute; z-index: 2; font-family: 'Font Awesome 6 Free'; font-style: normal; font-weight: 900; }
        .library-header__art .book-stack { top: 16%; left: 39%; padding: 16px 18px; border: 2px solid rgba(215, 165, 141, .58); border-radius: 9px; box-shadow: 18px 12px 0 -1px #f8f1e7, 18px 12px 0 1px rgba(215, 165, 141, .48), 34px 23px 0 -1px #f8f1e7, 34px 23px 0 1px rgba(215, 165, 141, .42); font-size: 36px; }
        .library-header__art .open-book { top: 42%; left: 48%; color: #cf9589; font-size: 53px; transform: rotate(-3deg); }
        .library-header__art .library-card { top: 61%; left: 36%; padding: 10px 13px; border: 2px solid rgba(215, 165, 141, .65); border-radius: 7px; background: rgba(248, 241, 231, .75); color: #d7a58d; font-size: 22px; transform: rotate(-8deg); }
        .library-header__art .library-check { top: 22%; right: 7%; padding: 12px; border: 2px solid #e3b968; border-radius: 50%; color: #e3b968; font-size: 21px; }
        .library-header__art .library-pin { bottom: 9%; left: 57%; width: 31px; height: 31px; color: transparent; background: #cf9589; border-radius: 50% 50% 50% 0; font-size: 0; transform: rotate(-45deg); }
        .library-header__art .library-pin::after { content: ''; position: absolute; top: 9px; left: 9px; width: 13px; height: 13px; background: #f8f1e7; border-radius: 50%; }
        .library-header__art .library-leaf { right: 12%; bottom: 11%; color: #d7a58d; font-size: 28px; }
        @media (max-width: 768px) { .library-page-header { min-height: 245px; padding: 32px 24px 110px; } .library-page-header > div:first-child { width: 100%; } .library-page-header h1 { font-size: 36px; } .library-header__art { width: 55%; opacity: .35; } }
        .library-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 2rem;
        }
        .inventory-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 24px;
            align-items: start;
        }
        .inventory-panel#management .inventory-grid {
            grid-template-columns: 1fr;
        }
        .inventory-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 28px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
            padding: 28px;
        }
        .inventory-card h2 {
            font-size: 24px;
            margin-bottom: 18px;
            color: #111827;
        }
        .status-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .inventory-form label,
        .inventory-form input,
        .inventory-form select,
        .inventory-form textarea {
            display: block;
            width: 100%;
        }
        .inventory-form label {
            margin-bottom: 8px;
            font-size: 14px;
            color: #475569;
        }
        .inventory-form input,
        .inventory-form select,
        .inventory-form textarea {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 14px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            font-size: 14px;
        }
        .inventory-form button {
            margin-top: 4px;
            padding: 14px 18px;
            border-radius: 14px;
            border: none;
            background: #2563eb;
            color: #ffffff;
            cursor: pointer;
        }
        .inventory-form button:hover {
            background: #1d4ed8;
        }
        .category-grid {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }
        .category-manager-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
        }
        @media (max-width: 980px) {
            .category-manager-grid {
                grid-template-columns: 1fr;
            }
        }
        .category-table,
        .book-table {
            width: 100%;
            border-collapse: collapse;
        }
        .category-table th,
        .category-table td,
        .book-table th,
        .book-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 14px;
            color: #334155;
        }
        .category-table th,
        .book-table th {
            font-weight: 600;
            color: #0f172a;
        }
        .category-table tr:last-child td {
            border-bottom: none;
        }

        @media (max-width: 720px) {
            .category-manager-grid,
            .inventory-card {
                width: 100%;
                padding: 16px;
            }
            .category-grid {
                gap: 16px;
            }
            .category-table,
            .book-table {
                border: none;
            }
            .category-table thead,
            .book-table thead {
                display: none;
            }
            .category-table tbody,
            .category-table tr,
            .category-table td,
            .book-table tbody,
            .book-table tr,
            .book-table td {
                display: block;
                width: 100%;
            }
            .category-table tr,
            .book-table tr {
                margin-bottom: 16px;
                border: 1px solid #e2e8f0;
                border-radius: 18px;
                overflow: hidden;
                background: #ffffff;
            }
            .category-table td,
            .book-table td {
                padding: 12px 14px;
                border: none;
            }
            .category-table td + td,
            .book-table td + td {
                margin-top: 8px;
            }
            .category-table td:first-child,
            .book-table td:first-child {
                font-weight: 600;
                background: #f8fafc;
            }
            .category-table td:last-child,
            .book-table td:last-child {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
            .inventory-card-header {
                margin-bottom: 14px;
            }
        }
        .category-table th,
        .book-table th {
            font-weight: 600;
            color: #0f172a;
        }
        .book-cover-cell {
            width: 170px;
            min-width: 170px;
        }
        .book-cover {
            width: 120px;
            height: 170px;
            border-radius: 18px;
            overflow: hidden;
            background: #f8fafc;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2e8f0;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
        }
        .book-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .book-meta {
            display: grid;
            gap: 6px;
            padding-left: 12px;
            min-width: 180px;
        }
        .book-meta strong {
            display: block;
            font-size: 14px;
            color: #111827;
            line-height: 1.3;
        }
        .book-meta span {
            display: block;
            color: #64748b;
            font-size: 13px;
        }
        .book-cover-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .book-cover-wrapper:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
        }
        .modal-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-bottom: 16px;
        }
        .cover-section {
            display: flex;
            gap: 18px;
            align-items: flex-start;
            margin-top: 16px;
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: flex-end;
            margin-top: 20px;
        }
        .modal-actions button {
            min-width: 140px;
        }
        .inline-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .inline-form input {
            width: 84px;
        }
        .inventory-meta {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-top: 20px;
        }
        .inventory-meta .meta-card {
            background: #f8fafc;
            border-radius: 20px;
            padding: 18px;
            text-align: center;
        }
        .inventory-meta .meta-card strong {
            display: block;
            margin-top: 10px;
            font-size: 20px;
            color: #111827;
        }
        .inventory-meta .meta-card span {
            color: #64748b;
        }
        .inventory-tab-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
            padding: 8px;
            background: #ffffff;
            border-radius: 12px;
            justify-content: space-between;
            align-items: stretch;
            width: 100%;
            box-sizing: border-box;
        }
        .library-tab-button {
            border: 2px solid transparent;
            background: transparent;
            color: var(--text-soft);
            padding: 16px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 16px;
            flex: 1;
            min-width: 0;
            text-align: center;
            white-space: nowrap;
        }
        .library-tab-button.active,
        .library-tab-button:hover {
            background: var(--deep-maroon);
            color: white;
            border-color: var(--soft-gold);
            box-shadow: 0 4px 16px var(--shadow-medium);
        }
        .catalog-feedback-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .catalog-feedback-stat {
            padding: 18px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #ffffff;
            text-align: center;
        }
        .catalog-feedback-stat h4 {
            margin: 0 0 8px;
            color: #475569;
            font-size: 14px;
        }
        .catalog-feedback-stat strong {
            display: block;
            color: #111827;
            font-size: 28px;
        }
        .catalog-feedback-stat span {
            color: #64748b;
            font-size: 12px;
        }
        .catalog-feedback-table-wrap {
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #ffffff;
        }
        .catalog-feedback-table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
        }
        .catalog-feedback-table th,
        .catalog-feedback-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: top;
        }
        .catalog-feedback-table th {
            color: #475569;
            background: #f8fafc;
            font-size: 13px;
        }
        .catalog-feedback-table td {
            color: #334155;
            font-size: 13px;
        }
        .catalog-feedback-table tbody tr:hover {
            background: #f8fafc;
        }
        .catalog-feedback-rating {
            white-space: nowrap;
            text-align: center !important;
        }
        .catalog-feedback-text {
            max-width: 220px;
            word-wrap: break-word;
        }
        @media (max-width: 720px) {
            .catalog-feedback-stats {
                grid-template-columns: 1fr;
            }
        }
        .inventory-panel {
            display: none;
        }
        .inventory-panel.active {
            display: block;
        }
        .eresource-card-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }
        .eresource-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .eresource-card h4 {
            margin: 0;
            font-size: 14px;
            line-height: 1.3;
        }
        .eresource-card p {
            margin: 0;
            font-size: 13px;
            color: #475569;
        }
        @media (max-width: 1600px) {
            .eresource-card-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
        @media (max-width: 1200px) {
            .eresource-card-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        @media (max-width: 900px) {
            .eresource-card-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 640px) {
            .eresource-card-grid {
                grid-template-columns: 1fr;
            }
        }

        .book-table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
        }
        .book-cover-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .book-cover img {
            width: 54px;
            height: 72px;
            object-fit: cover;
            border-radius: 10px;
        }

        @media (max-width: 720px) {
            .inventory-tab-bar {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                touch-action: pan-x;
                scroll-snap-type: x proximity;
                flex-wrap: nowrap;
                justify-content: flex-start;
                width: 100%;
                gap: 10px;
                padding: 10px 6px;
                margin-bottom: 20px;
            }
            .inventory-tab-bar .library-tab-button {
                flex: 0 0 auto;
                padding: 10px 14px;
                font-size: 14px;
                min-width: 140px;
                white-space: nowrap;
                scroll-snap-align: center;
            }
            .inventory-card {
                padding: 18px;
            }
            .inventory-card-header {
                display: block;
            }
            .inventory-card-header h2 {
                font-size: 22px;
            }
            .inventory-panel {
                padding: 0;
            }
            .inventory-card > .inventory-card-header + * {
                margin-top: 10px;
            }
            .inventory-card .inventory-card-header,
            .inventory-card .inventory-card-body,
            .inventory-card .inventory-card-footer {
                width: 100%;
            }
            .book-table thead {
                display: none;
            }
            .book-table tbody,
            .book-table tr,
            .book-table td {
                display: block;
                width: 100%;
            }
            .book-table tr {
                margin-bottom: 18px;
                border: 1px solid #e2e8f0;
                border-radius: 20px;
                overflow: hidden;
                background: #ffffff;
                padding: 14px 0;
            }
            .book-table td {
                padding: 10px 16px;
            }
            .book-table td + td {
                border-top: 1px solid #f1f5f9;
            }
            .book-table td:first-child {
                padding-top: 16px;
            }
            .book-cover-wrapper {
                flex-wrap: wrap;
            }
            .book-meta strong {
                font-size: 16px;
                line-height: 1.3;
            }
            .book-meta {
                width: calc(100% - 74px);
            }
            .inventory-card .inventory-card-header {
                margin-bottom: 16px;
            }
        }

        @media (max-width: 480px) {
            .inventory-tab-bar {
                gap: 8px;
                padding: 8px 4px;
            }
            .inventory-tab-bar .library-tab-button {
                min-width: 120px;
                padding: 10px 12px;
                font-size: 13px;
            }
            .inventory-card {
                padding: 14px;
            }
            .book-table td {
                padding: 10px 12px;
            }
            .inventory-card-header h2 {
                font-size: 20px;
            }
            .book-meta {
                width: calc(100% - 64px);
            }
        }

        .inventory-card-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }
        .inventory-card-header h2 {
            margin: 0;
            font-size: 20px;
        }
        .modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.65);
            z-index: 1000;
            padding: 24px;
        }
        .modal.show {
            display: flex;
        }
        .modal-content {
            background: #ffffff;
            border-radius: 24px;
            width: min(900px, 100%);
            max-height: 90vh;
            overflow-y: auto;
            padding: 28px;
            box-shadow: 0 28px 60px rgba(15, 23, 42, 0.16);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        .modal-close {
            border: none;
            background: transparent;
            color: #475569;
            font-size: 28px;
            cursor: pointer;
            line-height: 1;
        }
        .modal-form input,
        .modal-form select,
        .modal-form textarea {
            width: 100%;
        }
        .modal-form .small-text {
            margin-top: 0;
            margin-bottom: 16px;
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
<body class="library-catalog swipe-refresh-enabled">
    <div id="swipe-refresh-spinner" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); z-index: 9999; justify-content: center; align-items: center;"><div style="text-align: center;"><div style="width: 60px; height: 60px; border: 4px solid #e2e8f0; border-top-color: #800000; border-radius: 50%; animation: spin-refresh 1s linear infinite; margin: 0 auto 16px;"></div><p style="color: #666; font-family: 'Poppins', sans-serif; font-size: 14px; margin: 0;">Refreshing...</p></div></div>
    <div class="page-shell">
        <aside class="side-nav collapsed">
            <div class="nav-mobile-header">
                <div class="mobile-user-info">
                    <h4><?= htmlspecialchars($user['full_name']) ?></h4>
                    <p>Library Head</p>
                </div>
            </div>
            <div class="nav-header">
                <button type="button" class="hamburger-btn" id="sidebarToggle" aria-label="Toggle sidebar" data-tooltip="Toggle Sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
               
            </div>
            <div class="nav-section">
                <a href="dashboard/librarian_home.php" data-tooltip="Dashboard">
                    <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="dashboard/school_announcements.php?service=library" data-tooltip="Announcements">
                    <span class="nav-icon"><i class="fa-solid fa-bullhorn"></i></span>
                    <span class="nav-text">Announcements</span>
                </a>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $serviceOpen ? 'true' : 'false' ?>" data-tooltip="Services">
                        <span class="nav-icon"><i class="fa-solid fa-concierge-bell"></i></span>
                        <span class="nav-text">Services</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu<?= $serviceOpen ? ' open' : '' ?>" aria-hidden="<?= $serviceOpen ? 'false' : 'true' ?>">
                        <a href="dashboard/guidance_dashboard.php?service=library" data-tooltip="Guidance">
                            <span class="nav-icon"><i class="fa-solid fa-user-graduate"></i></span>
                            <span class="nav-text">Guidance</span>
                        </a>
                        <a href="dashboard/library_dashboard.php?service=library" data-tooltip="Library">
                            <span class="nav-icon"><i class="fa-solid fa-book"></i></span>
                            <span class="nav-text">Library</span>
                        </a>
                        <a href="dashboard/clinic_dashboard.php?service=library" data-tooltip="Clinic">
                            <span class="nav-icon"><i class="fa-solid fa-stethoscope"></i></span>
                            <span class="nav-text">Clinic</span>
                        </a>
                        <a href="dashboard/ssc/ssc_dashboard.php?service=library" data-tooltip="SSC">
                            <span class="nav-icon"><i class="fa-solid fa-award"></i></span>
                            <span class="nav-text">SSC</span>
                        </a>
                        <a href="dashboard/scholarship/scholarship_dashboard.php?service=library" data-tooltip="Scholarship">
                            <span class="nav-icon"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                            <span class="nav-text">Scholarship</span>
                        </a>
                        <a href="dashboard/ssaa_student_home.php?service=library" data-tooltip="Alumni">
                            <span class="nav-icon"><i class="fa-solid fa-users"></i></span>
                            <span class="nav-text">Alumni</span>
                        </a>
                    </div>
                </div>
                <div class="nav-group">
                    <button type="button" class="nav-toggle" aria-expanded="<?= $manageOpen ? 'true' : 'false' ?>" data-tooltip="Manage">
                        <span class="nav-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="nav-text">Manage</span>
                        <span class="toggle-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="submenu<?= $manageOpen ? ' open' : '' ?>" aria-hidden="<?= $manageOpen ? 'false' : 'true' ?>">
                        <a href="library_checkin.php" data-tooltip="QR Check-In">
                            <span class="nav-icon"><i class="fa-solid fa-qrcode"></i></span>
                            <span class="nav-text">QR Check-In</span>
                        </a>
                        <a class="active" href="library_catalog.php" data-tooltip="Digital Catalog">
                            <span class="nav-icon"><i class="fa-solid fa-book-open"></i></span>
                            <span class="nav-text">Digital Catalog</span>
                        </a>
                        <a href="library_inventory.php" data-tooltip="Inventory">
                            <span class="nav-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
                            <span class="nav-text">Inventory</span>
                        </a>
                        <a href="library_reports.php" data-tooltip="Reports">
                            <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                            <span class="nav-text">Reports</span>
                        </a>
                        <a href="library_settings.php" data-tooltip="Settings">
                            <span class="nav-icon"><i class="fa-solid fa-gear"></i></span>
                            <span class="nav-text">Settings</span>
                        </a>
                        <a href="library_qr.php" data-tooltip="Library QR">
                            <span class="nav-icon"><i class="fa-solid fa-qrcode"></i></span>
                            <span class="nav-text">Library QR</span>
                        </a>
                    </div>
                </div>
                <a href="dashboard/profile.php?service=library" data-tooltip="Profile">
                    <span class="nav-icon"><i class="fa-solid fa-user"></i></span>
                    <span class="nav-text">Profile</span>
                </a>
                <a href="dashboard/about.php?service=library" data-tooltip="About">
                    <span class="nav-icon"><i class="fa-solid fa-info-circle"></i></span>
                    <span class="nav-text">About</span>
                </a>
            </div>
            <div class="nav-footer">
                <a href="logout.php" class="logout-link" data-tooltip="Logout">
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
                            <p>Library Management</p>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="user-meta">Library Head</span>
                    </div>
                    <span class="topbar-divider"></span>
                    <button type="button" class="topbar-icon" aria-label="Notifications" data-tooltip="Notifications" data-menu-target="notificationMenu"><i class="fa-solid fa-bell"></i></button>
                    <button type="button" class="topbar-icon" aria-label="Profile" data-tooltip="Profile" data-menu-target="profileMenu"><i class="fa-solid fa-user"></i></button>
                    <!-- support icon removed per UI update -->

                    <div class="topbar-menu" id="notificationMenu" role="menu" aria-label="Notifications menu">
                        <div class="menu-header">
                            <strong>Notifications</strong>
                            <span class="menu-note">Latest announcements</span>
                        </div>
                        <div class="menu-empty">No new announcements.</div>
                        <a class="menu-link menu-footer-link" href="dashboard/about.php">View all announcements</a>
                    </div>

                    <div class="topbar-menu" id="profileMenu" role="menu" aria-label="Profile menu">
                        <div class="menu-item profile-menu-item" role="menuitem">
                            <a href="dashboard/profile.php" class="menu-link profile-link"><?= htmlspecialchars($user['full_name']) ?></a>
                            <span class="menu-subtext">View your account details</span>
                        </div>
                        <a href="dashboard/profile.php" class="menu-action">View profile</a>
                    </div>

                    <div class="topbar-menu" id="supportMenu" role="menu" aria-label="Support menu">
                        <div class="menu-header">
                            <strong>Support</strong>
                            <span class="menu-note">Need help?</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="dashboard/about.php">Help center</a>
                            <span class="menu-subtext">View support resources and FAQs.</span>
                        </div>
                        <div class="menu-item" role="menuitem">
                            <a class="menu-link" href="mailto:support@passcollege.edu">Email support</a>
                        </div>
                    </div>
                </div>
            </header>
            <div class="page-overlay" id="pageOverlay"></div>
            <div class="main-scroll">
                <section class="dashboard-intro library-page-header">
                        <div>
                            <span class="eyebrow">INVENTORY</span>
                            <h1>Track Library Copies</h1>
                            <p class="dashboard-subtitle">Manage book availability, low-stock alerts, and reservation pressure from one inventory panel.</p>
                        </div>
                    <div class="library-header__art" aria-hidden="true"><i class="fa-solid fa-layer-group book-stack"></i><i class="fa-solid fa-book-open open-book"></i><i class="fa-solid fa-id-card library-card"></i><i class="fa-solid fa-circle-check library-check"></i><i class="fa-solid fa-location-pin library-pin"></i><i class="fa-solid fa-leaf library-leaf"></i></div>
                    </section>
                    <?php if (!empty($message)): ?>
                        <section class="dashboard-section">
                            <div class="dashboard-summary-card <?= $messageType === 'error' ? 'alert error' : 'alert success' ?>">
                                <?= htmlspecialchars($message) ?>
                            </div>
                        </section>
                    <?php endif; ?>
                    <section class="dashboard-section">
                        <!-- Library Statistics Cards -->
                        <div class="library-status-grid">
                            <div class="library-card status-card">
                                <span class="status-label">Books tracked</span>
                                <strong style="font-size: 28px; color: #2563eb;"><?= htmlspecialchars(count($books)) ?></strong>
                                <p>Total number of books in the catalog.</p>
                            </div>
                            <div class="library-card status-card">
                                <span class="status-label">Available copies</span>
                                <strong style="font-size: 28px; color: #10b981;"><?= htmlspecialchars($availableCopiesAll) ?></strong>
                                <p>Copies currently available for borrowing.</p>
                            </div>
                            <div class="library-card status-card">
                                <span class="status-label">Total copies</span>
                                <strong style="font-size: 28px; color: #8b5cf6;"><?= htmlspecialchars($totalCopiesAll) ?></strong>
                                <p>Total number of book copies in inventory.</p>
                            </div>
                            <div class="library-card status-card">
                                <span class="status-label">Categories</span>
                                <strong style="font-size: 28px; color: #f59e0b;"><?= htmlspecialchars($categoryCount) ?></strong>
                                <p>Number of book categories organized.</p>
                            </div>
                        </div>

                        <div class="inventory-tab-bar library-tab-bar" role="tablist" aria-label="Catalog navigation">
                            <button type="button" class="library-tab-button <?= $activeTab === 'management' ? 'active' : '' ?>" data-tab="management">Book Management</button>
                            <button type="button" class="library-tab-button <?= $activeTab === 'summary' ? 'active' : '' ?>" data-tab="summary">Inventory Summary</button>
                            <button type="button" class="library-tab-button <?= $activeTab === 'categories' ? 'active' : '' ?>" data-tab="categories">Categories</button>
                            <button type="button" class="library-tab-button <?= $activeTab === 'eresources' ? 'active' : '' ?>" data-tab="eresources">E-Resources</button>
                            <button type="button" class="library-tab-button <?= $activeTab === 'open_access' ? 'active' : '' ?>" data-tab="open_access">Open Access Library</button>
                            <button type="button" class="library-tab-button <?= $activeTab === 'feedback' ? 'active' : '' ?>" data-tab="feedback">Feedback</button>
                        </div>

                        <div class="inventory-panel <?= $activeTab === 'management' ? 'active' : '' ?>" id="management">
                            <div class="inventory-grid">
                                <div class="inventory-card inventory-form">
                                    <h2>Add new book</h2>
                                    <form method="post" enctype="multipart/form-data" class="inventory-form">
                                        <input type="hidden" name="action" value="add_book">
                                        <label>Title</label>
                                        <input type="text" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                                        <label>Author</label>
                                        <input type="text" name="author" required value="<?= htmlspecialchars($_POST['author'] ?? '') ?>">
                                        <label>Library of Congress Classification (LCC)</label>
                                        <div style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px;">
                                            <input type="text" name="lcc_letter_line" placeholder="Letter line (e.g. HE)" value="<?= htmlspecialchars($_POST['lcc_letter_line'] ?? '') ?>">
                                            <input type="text" name="lcc_class_number" placeholder="Class number (e.g. 8700.7)" value="<?= htmlspecialchars($_POST['lcc_class_number'] ?? '') ?>">
                                            <input type="text" name="lcc_cutter_code" placeholder="Cutter code (e.g. P6T44)" value="<?= htmlspecialchars($_POST['lcc_cutter_code'] ?? '') ?>">
                                            <input type="text" name="lcc_published_year" placeholder="Year (e.g. 1983)" value="<?= htmlspecialchars($_POST['lcc_published_year'] ?? '') ?>">
                                        </div>
                                        <label>Additional info type</label>
                                        <select name="additional_info_type" id="add-additional-info-type">
                                            <option value="">None</option>
                                            <option value="volume" <?= (($_POST['additional_info_type'] ?? '') === 'volume') ? 'selected' : '' ?>>Volume</option>
                                            <option value="part" <?= (($_POST['additional_info_type'] ?? '') === 'part') ? 'selected' : '' ?>>Part</option>
                                            <option value="number" <?= (($_POST['additional_info_type'] ?? '') === 'number') ? 'selected' : '' ?>>Number</option>
                                            <option value="copies" <?= (($_POST['additional_info_type'] ?? '') === 'copies') ? 'selected' : '' ?>>Copies</option>
                                        </select>
                                        <div id="add-additional-info-generation-container" style="display:none;">
                                            <label>Input Method</label>
                                            <select name="additional_info_generation" id="add-additional-info-generation">
                                                <option value="single">Single Input (e.g., input 10 = only store 10)</option>
                                                <option value="auto">Auto-generate (e.g., input 10 = store 1,2,3...10)</option>
                                            </select>
                                        </div>
                                        <div id="add-additional-info-quantity-container" style="display:none;">
                                            <label id="add-additional-info-quantity-label">Quantity</label>
                                            <div style="display: flex; gap: 8px;">
                                                <input type="number" name="additional_info_quantity" id="add-additional-info-quantity" min="1" placeholder="Enter quantity" value="<?= htmlspecialchars($_POST['additional_info_quantity'] ?? '') ?>" style="flex: 1;">
                                                <button type="button" id="add-more-single-input" style="display:none; padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">Add More</button>
                                            </div>
                                            <div id="single-input-entries-list" style="margin-top: 12px; display:none;">
                                                <small style="color: #666; display: block; margin-bottom: 8px;">Single entries:</small>
                                            </div>
                                        </div>
                                        <input type="hidden" name="single_input_entries" id="add-single-input-entries" value="">
                                        <label>Category</label>
                                        <select name="category_select" id="category-select">
                                            <option value="">Select category</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= htmlspecialchars($cat['name']) ?>" <?= (($_POST['category_select'] ?? '') === $cat['name']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label>Or add new category</label>
                                        <input type="text" name="new_category" id="new-category" placeholder="Create a new category" value="<?= htmlspecialchars($_POST['new_category'] ?? '') ?>">
                                        <small class="form-note">Choose either an existing category or type a new one, not both.</small>
                                        <label>Book overview</label>
                                        <textarea name="overview" rows="4" placeholder="Add a short description..."> <?= htmlspecialchars($_POST['overview'] ?? '') ?></textarea>
                                        <label>Cover image</label>
                                        <input type="file" name="cover" accept="image/png,image/jpeg,image/webp">
                                        <div style="display:flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                                            <button type="submit">Add book</button>
                                        </div>
                                    </form>
                                </div>
                                <div class="inventory-card">
                                    <h2>Remove books</h2>
                                    <p>Select a book below to remove it from the inventory. This action cannot be undone.</p>
                                    <div style="margin-top: 16px;">
                                        <select id="remove-book-select" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; margin-bottom: 12px;">
                                            <option value="">Select a book to remove</option>
                                            <?php
                                            $allBooks = get_all_library_books();
                                            foreach ($allBooks as $book):
                                            ?>
                                                <option value="<?= htmlspecialchars($book['id']) ?>" data-title="<?= htmlspecialchars($book['title']) ?>">
                                                    <?= htmlspecialchars($book['title']) ?> by <?= htmlspecialchars($book['author']) ?> (<?= htmlspecialchars($book['available_copies']) ?>/<?= htmlspecialchars($book['total_copies']) ?> available)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" id="remove-book-btn" class="secondary-button" style="background:#dc2626; color:#fff; width: 100%;" disabled>Remove Selected Book</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="inventory-panel <?= $activeTab === 'summary' ? 'active' : '' ?>" id="summary">
                            <div class="inventory-card">
                                <div class="inventory-card-header">
                                    <h2>Inventory summary</h2>
                                </div>
                                <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; margin-bottom:16px;">
                                    <div style="flex:1; min-width:220px;">
                                        <label for="summary-search" style="display:block; margin-bottom:8px; color:#475569;">Search title or author</label>
                                        <input id="summary-search" type="search" placeholder="Search by title or author" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:12px; background:#f8fafc; font-size:14px;" />
                                    </div>
                                    <div style="flex:1; min-width:220px;">
                                        <label for="summary-category-filter" style="display:block; margin-bottom:8px; color:#475569;">Filter by category</label>
                                        <select id="summary-category-filter" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:12px; background:#f8fafc; font-size:14px;">
                                            <option value="">All categories</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option><?= htmlspecialchars($cat['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <table class="book-table" style="margin-top:24px;">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Author</th>
                                            <th>Category</th>
                                            <th>Total Copies</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($books)): ?>
                                            <tr><td colspan="5">No books found in inventory.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($books as $book): ?>
                                                <tr class="book-row" data-category="<?= htmlspecialchars($book['category'] ?: 'General') ?>" data-title="<?= htmlspecialchars($book['title']) ?>" data-author="<?= htmlspecialchars(strtolower($book['author'])) ?>" data-cover-url="<?= htmlspecialchars($book['cover_url']) ?>">
                                                    <td>
                                                        <div class="book-cover-wrapper">
                                                            <div class="book-cover">
                                                                <?php if (!empty($book['cover_url'])): ?>
                                                                    <img src="<?= htmlspecialchars($book['cover_url']) ?>" alt="<?= htmlspecialchars($book['title']) ?>">
                                                                <?php else: ?>
                                                                    <span style="color:#64748b; font-size:12px;">No cover</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="book-meta">
                                                                <strong><?= htmlspecialchars($book['title']) ?></strong>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?= htmlspecialchars($book['author']) ?></td>
                                                    <td><?= htmlspecialchars($book['category'] ?: 'General') ?></td>
                                                    <td><?= htmlspecialchars($book['total_copies'] ?? 0) ?></td>
                                                    <td>
                                                        <div style="display:grid; gap:10px;">
                                                            <a href="book_copies.php?book_id=<?= htmlspecialchars($book['id']) ?>" class="secondary-button" style="background:#16a34a; color:#fff;">Add Copies</a>
                                                            <button type="button" class="secondary-button edit-book-button" style="background:#2563eb; color:#fff;"
                                                                data-book-id="<?= htmlspecialchars($book['id'] ?? '') ?>"
                                                                data-title="<?= htmlspecialchars($book['title'] ?? '') ?>"
                                                                data-author="<?= htmlspecialchars($book['author'] ?? '') ?>"
                                                                data-isbn="<?= htmlspecialchars($book['isbn'] ?? '') ?>"
                                                                data-lcc-letter-line="<?= htmlspecialchars($book['lcc_letter_line'] ?? '') ?>"
                                                                data-lcc-class-number="<?= htmlspecialchars($book['lcc_class_number'] ?? '') ?>"
                                                                data-lcc-cutter-code="<?= htmlspecialchars($book['lcc_cutter_code'] ?? '') ?>"
                                                                data-lcc-published-year="<?= htmlspecialchars($book['lcc_published_year'] ?? '') ?>"
                                                                data-additional-info-type="<?= htmlspecialchars($book['additional_info_type'] ?? '') ?>"
                                                                data-additional-info-quantity="<?= htmlspecialchars($book['additional_info_quantity'] ?? '') ?>"
                                                                data-category="<?= htmlspecialchars($book['category'] ?? 'General') ?>"
                                                                data-total-copies="<?= htmlspecialchars($book['total_copies'] ?? 0) ?>"
                                                                data-available-copies="<?= htmlspecialchars($book['available_copies'] ?? 0) ?>"
                                                                data-overview="<?= htmlspecialchars($book['overview'] ?? '') ?>"
                                                                data-cover-url="<?= htmlspecialchars($book['cover_url'] ?? '') ?>"
                                                            >Edit</button>
                                                            <button type="button" class="secondary-button remove-book-button" style="background:#dc2626; color:#fff;"
                                                                data-book-id="<?= htmlspecialchars($book['id']) ?>"
                                                                data-book-title="<?= htmlspecialchars($book['title']) ?>"
                                                            >Remove</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr id="no-books-message" style="display:none;"><td colspan="5">No books match this category.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div id="book-edit-modal" class="modal" aria-hidden="true">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <div>
                                        <h2>Edit book</h2>
                                        <p style="margin: 6px 0 0; color: #64748b; font-size: 14px;">Update book details, copy counts, and cover image in one organized form.</p>
                                    </div>
                                    <button type="button" class="modal-close" aria-label="Close">&times;</button>
                                </div>
                                <form method="post" enctype="multipart/form-data" class="inventory-form modal-form">
                                    <input type="hidden" name="action" value="save_book">
                                    <input type="hidden" name="book_id" id="modal-book-id" value="">
                                    <div class="modal-grid">
                                        <div>
                                            <label>Title</label>
                                            <input type="text" name="title" id="modal-title" required>
                                        </div>
                                        <div>
                                            <label>Author</label>
                                            <input type="text" name="author" id="modal-author" required>
                                        </div>
                                        <input type="hidden" name="isbn" id="modal-isbn">
                                        <div>
                                            <label>LCC letter line</label>
                                            <input type="text" name="lcc_letter_line" id="modal-lcc-letter-line" placeholder="e.g. HE">
                                        </div>
                                        <div>
                                            <label>LCC class number</label>
                                            <input type="text" name="lcc_class_number" id="modal-lcc-class-number" placeholder="e.g. 8700.7">
                                        </div>
                                        <div>
                                            <label>LCC cutter code</label>
                                            <input type="text" name="lcc_cutter_code" id="modal-lcc-cutter-code" placeholder="e.g. P6T44">
                                        </div>
                                        <div>
                                            <label>Published year</label>
                                            <input type="text" name="lcc_published_year" id="modal-lcc-published-year" placeholder="e.g. 1983">
                                        </div>
                                        <div>
                                            <label>Additional info type</label>
                                            <select name="additional_info_type" id="modal-additional-info-type">
                                                <option value="">None</option>
                                                <option value="volume">Volume</option>
                                                <option value="part">Part</option>
                                                <option value="number">Number</option>
                                                <option value="copies">Copies</option>
                                            </select>
                                        </div>
                                        <div id="additional-info-generation-container" style="display:none;">
                                            <label>Input Method</label>
                                            <select name="additional_info_generation" id="modal-additional-info-generation">
                                                <option value="single">Single Input (e.g., input 10 = only store 10)</option>
                                                <option value="auto">Auto-generate (e.g., input 10 = store 1,2,3...10)</option>
                                            </select>
                                        </div>
                                        <div id="additional-info-quantity-container" style="display:none;">
                                            <label id="additional-info-quantity-label">Quantity</label>
                                            <div style="display: flex; gap: 8px;">
                                                <input type="number" name="additional_info_quantity" id="modal-additional-info-quantity" min="1" placeholder="Enter quantity" style="flex: 1;">
                                                <button type="button" id="modal-add-more-single-input" style="display:none; padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">Add More</button>
                                            </div>
                                            <div id="modal-single-input-entries-list" style="margin-top: 12px; display:none;">
                                                <small style="color: #666; display: block; margin-bottom: 8px;">Single entries:</small>
                                            </div>
                                        </div>
                                        <input type="hidden" name="single_input_entries" id="modal-single-input-entries" value="">
                                        <div>
                                            <label>Category</label>
                                            <select name="category_select" id="modal-category-select">
                                                <option value="">Select category</option>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label>Or add new category</label>
                                            <input type="text" name="new_category" id="modal-new-category" placeholder="Create a new category">
                                        </div>
                                    </div>
                                    <label>Book overview</label>
                                    <textarea name="overview" id="modal-overview" rows="4" placeholder="Add a short description..."></textarea>
                                    <div class="cover-section">
                                        <div style="flex:1;">
                                            <p class="small-text" id="modal-current-cover" style="display:none; margin:0 0 10px;">Current cover: <a href="#" target="_blank" id="modal-cover-link">View image</a></p>
                                            <label>Cover image</label>
                                            <input type="file" name="cover" id="modal-cover" accept="image/png,image/jpeg,image/webp">
                                        </div>
                                        <div id="modal-cover-preview" style="min-width:120px; min-height:160px; border:1px solid #e2e8f0; border-radius: 18px; overflow:hidden; background:#f8fafc; display:flex; align-items:center; justify-content:center;">
                                            <span style="color:#64748b; font-size:12px; text-align:center; padding:12px;">Cover preview</span>
                                        </div>
                                    </div>
                                    <div class="modal-actions">
                                        <button type="submit">Save changes</button>
                                        <button type="button" class="secondary-button modal-remove-book" style="background:#dc2626; color:#fff;" id="modal-remove-book">Remove Book</button>
                                        <button type="button" class="secondary-button modal-close">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div id="book-image-modal" class="modal" aria-hidden="true">
                            <div class="modal-content image-preview-content">
                                <div class="modal-header">
                                    <div>
                                        <h2>Book Cover</h2>
                                        <p style="margin: 6px 0 0; color: #64748b; font-size: 14px;">Click the book cover to see a larger image.</p>
                                    </div>
                                    <button type="button" class="modal-close" aria-label="Close">&times;</button>
                                </div>
                                <div class="image-preview-body" style="display:grid; gap:16px;">
                                    <img id="book-image-preview" src="" alt="Book cover preview" style="width:100%; max-height:calc(100vh - 220px); object-fit: contain; border-radius: 18px; background:#f8fafc;">
                                    <p id="book-image-preview-title" style="margin:0; color:#111827; font-size:16px; font-weight:600;"></p>
                                </div>
                            </div>
                        </div>

                        <div class="inventory-panel <?= $activeTab === 'categories' ? 'active' : '' ?>" id="categories">
                            <div class="category-manager-grid">
                                <div class="inventory-card">
                                    <h2>Book category manager</h2>
                                    <form method="post" class="inventory-form">
                                        <input type="hidden" name="action" value="add_category">
                                        <label>New category name</label>
                                        <input type="text" name="category_name" placeholder="Example: Reference">
                                        <button type="submit">Add category</button>
                                    </form>
                                    <div class="category-grid">
                                        <table class="category-table">
                                            <thead>
                                                <tr><th>Category</th><th>Actions</th></tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($categories)): ?>
                                                    <tr><td colspan="2">No categories defined yet.</td></tr>
                                                <?php else: ?>
                                                    <?php foreach ($categories as $cat): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($cat['name']) ?></td>
                                                            <td>
                                                                <form method="post" class="inline-form">
                                                                    <input type="hidden" name="action" value="edit_category">
                                                                    <input type="hidden" name="category_id" value="<?= htmlspecialchars($cat['id']) ?>">
                                                                    <input type="text" name="category_name" value="<?= htmlspecialchars($cat['name']) ?>" style="min-width:150px;">
                                                                    <button type="submit" class="secondary-button">Save</button>
                                                                </form>
                                                                <form method="post" class="inline-form" style="margin-top:8px;">
                                                                    <input type="hidden" name="action" value="delete_category">
                                                                    <input type="hidden" name="category_id" value="<?= htmlspecialchars($cat['id']) ?>">
                                                                    <button type="submit" class="secondary-button" style="background:#fb7185; color:#fff;">Delete</button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="inventory-card">
                                    <h2>E-Resource category manager</h2>
                                    <form method="post" class="inventory-form">
                                        <input type="hidden" name="action" value="add_resource_category">
                                        <label>New E-Resource category</label>
                                        <input type="text" name="resource_category_name" placeholder="Example: Modules">
                                        <button type="submit">Add category</button>
                                    </form>
                                    <div class="category-grid">
                                        <table class="category-table">
                                            <thead>
                                                <tr><th>Category</th><th>Actions</th></tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($resourceCategories)): ?>
                                                    <tr><td colspan="2">No e-resource categories defined yet.</td></tr>
                                                <?php else: ?>
                                                    <?php foreach ($resourceCategories as $cat): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($cat['name']) ?></td>
                                                            <td>
                                                                <form method="post" class="inline-form">
                                                                    <input type="hidden" name="action" value="edit_resource_category">
                                                                    <input type="hidden" name="resource_category_id" value="<?= htmlspecialchars($cat['id']) ?>">
                                                                    <input type="text" name="resource_category_name" value="<?= htmlspecialchars($cat['name']) ?>" style="min-width:150px;">
                                                                    <button type="submit" class="secondary-button">Save</button>
                                                                </form>
                                                                <form method="post" class="inline-form" style="margin-top:8px;">
                                                                    <input type="hidden" name="action" value="delete_resource_category">
                                                                    <input type="hidden" name="resource_category_id" value="<?= htmlspecialchars($cat['id']) ?>">
                                                                    <button type="submit" class="secondary-button" style="background:#fb7185; color:#fff;">Delete</button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="inventory-panel <?= $activeTab === 'eresources' ? 'active' : '' ?>" id="eresources">
                            <div class="inventory-card">
                                <h2>E-Resources Management</h2>
                                <p style="margin-bottom:24px; color:#64748b;">Review and approve educational materials uploaded by teachers. Assign categories and manage published resources.</p>
                                
                                <div style="border-bottom: 2px solid #e2e8f0; margin-bottom: 20px;">
                                    <div style="display: flex; gap: 12px; flex-wrap: wrap; justify-content: space-between; align-items: center; margin-bottom: -2px;">
                                        <button type="button" class="eresources-tab-button <?= $activeEresourcesTab === 'pending' ? 'active' : '' ?>" data-tab="pending" style="padding: 12px 18px; border: none; background: transparent; cursor: pointer; border-bottom: 2px solid <?= $activeEresourcesTab === 'pending' ? '#2563eb' : 'transparent' ?>; color: <?= $activeEresourcesTab === 'pending' ? '#2563eb' : '#64748b' ?>; font-weight: 600;">
                                            <i class="fa-solid fa-hourglass-end"></i> Pending Approvals
                                        </button>
                                        <button type="button" class="eresources-tab-button <?= $activeEresourcesTab === 'add' ? 'active' : '' ?>" data-tab="add" style="padding: 12px 18px; border: none; background: transparent; cursor: pointer; border-bottom: 2px solid <?= $activeEresourcesTab === 'add' ? '#2563eb' : 'transparent' ?>; color: <?= $activeEresourcesTab === 'add' ? '#2563eb' : '#64748b' ?>; font-weight: 600;">
                                            <i class="fa-solid fa-plus-circle"></i> Add Resource
                                        </button>
                                        <button type="button" class="eresources-tab-button <?= $activeEresourcesTab === 'published' ? 'active' : '' ?>" data-tab="published" style="padding: 12px 18px; border: none; background: transparent; cursor: pointer; border-bottom: 2px solid <?= $activeEresourcesTab === 'published' ? '#2563eb' : 'transparent' ?>; color: <?= $activeEresourcesTab === 'published' ? '#2563eb' : '#64748b' ?>; font-weight: 600;">
                                            <i class="fa-solid fa-layer-group"></i> Published Resources
                                        </button>
                                    </div>
                                </div>

                                <div class="eresources-tab-content <?= $activeEresourcesTab === 'pending' ? 'active' : '' ?>" data-tab="pending" style="<?= $activeEresourcesTab === 'pending' ? '' : 'display:none;' ?>">
                                    <?php 
                                    $pendingResources = get_pending_resource_approvals();
                                    if (empty($pendingResources)): 
                                    ?>
                                        <div style="text-align: center; padding: 40px 20px; background: #f8fafc; border-radius: 20px;">
                                            <i class="fa-solid fa-inbox" style="font-size: 48px; color: #cbd5e1; margin-bottom: 12px; display: block;"></i>
                                            <p style="color: #64748b;">No pending resources awaiting approval</p>
                                        </div>
                                    <?php else: ?>
                                        <div style="display: grid; gap: 16px;">
                                            <?php foreach ($pendingResources as $resource): ?>
                                                <div style="border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                                    <div>
                                                        <h3 style="margin: 0 0 8px 0; font-size: 16px; color: #111827;"><?= htmlspecialchars($resource['title']) ?></h3>
                                                        <p style="margin: 0 0 4px 0; font-size: 13px; color: #64748b;"><strong>Uploaded by:</strong> <?= htmlspecialchars($resource['uploader_name'] ?? 'Unknown') ?></p>
                                                        <p style="margin: 0 0 4px 0; font-size: 13px; color: #64748b;"><strong>Course:</strong> <?= htmlspecialchars($resource['uploader_course'] ?? 'Unknown') ?></p>
                                                        <p style="margin: 0 0 4px 0; font-size: 13px; color: #64748b;"><strong>File:</strong> <?= htmlspecialchars($resource['file_name'] ?? 'N/A') ?> (<?= htmlspecialchars($resource['file_type'] ?? 'unknown') ?>)</p>
                                                        <p style="margin: 0; font-size: 13px; color: #64748b; white-space: normal; word-wrap: break-word;"><?= htmlspecialchars(substr($resource['description'] ?? '', 0, 100)) ?><?= strlen($resource['description'] ?? '') > 100 ? '...' : '' ?></p>
                                                    </div>
                                                    <form method="post" style="display: flex; flex-direction: column; gap: 12px;">
                                                        <input type="hidden" name="action" value="approve_resource">
                                                        <input type="hidden" name="resource_id" value="<?= htmlspecialchars($resource['id']) ?>">
                                                        <div>
                                                            <label style="display: block; margin-bottom: 6px; font-size: 13px; color: #475569; font-weight: 600;">Category</label>
                                                            <select name="category" required style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                                                                <option value="">Select category</option>
                                                                <?php foreach ($resourceCategories as $resourceCategory): ?>
                                                                    <option value="<?= htmlspecialchars($resourceCategory['name']) ?>"><?= htmlspecialchars($resourceCategory['name']) ?></option>
                                                                <?php endforeach; ?>
                                                                <?php if (empty($resourceCategories)): ?>
                                                                    <option value="General">General</option>
                                                                <?php endif; ?>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label style="display: block; margin-bottom: 6px; font-size: 13px; color: #475569; font-weight: 600;">Course/Year (Optional)</label>
                                                            <input type="text" name="course_year" placeholder="e.g., Grade 10 Science" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px;">
                                                        </div>
                                                        <div style="display: flex; gap: 8px;">
                                                            <button type="submit" style="flex: 1; padding: 10px; background: #10b981; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Approve</button>
                                                            <button type="button" class="reject-resource-btn" data-resource-id="<?= htmlspecialchars($resource['id']) ?>" style="flex: 1; padding: 10px; background: #ef4444; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Reject</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="eresources-tab-content <?= $activeEresourcesTab === 'add' ? 'active' : '' ?>" data-tab="add" style="<?= $activeEresourcesTab === 'add' ? '' : 'display:none;' ?>">
                                    <div style="margin-bottom: 30px;">
                                        <h3>Add E-Resource Manually</h3>
                                        <p style="color: #64748b; margin-bottom: 16px;">Upload and publish e-resources directly to the library without waiting for teacher submissions.</p>
                                        <form method="post" enctype="multipart/form-data" class="inventory-form" style="max-width: 500px; display: grid; gap: 12px;">
                                            <input type="hidden" name="action" value="add_manual_resource">
                                            <div>
                                                <label>Resource Title</label>
                                                <input type="text" name="title" required placeholder="Enter resource title">
                                            </div>
                                            <div>
                                                <label>Description</label>
                                                <textarea name="description" rows="3" required placeholder="Describe the resource..."></textarea>
                                            </div>
                                            <div>
                                                <label>Category</label>
                                                <select name="category" required>
                                                    <option value="">Select category</option>
                                                    <?php foreach ($resourceCategories as $resourceCategory): ?>
                                                        <option value="<?= htmlspecialchars($resourceCategory['name']) ?>"><?= htmlspecialchars($resourceCategory['name']) ?></option>
                                                    <?php endforeach; ?>
                                                    <?php if (empty($resourceCategories)): ?>
                                                        <option value="General">General</option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label>Course/Year (Optional)</label>
                                                <input type="text" name="course_year" placeholder="e.g., Grade 10 Science">
                                            </div>
                                            <div>
                                                <label>Upload File</label>
                                                <input type="file" name="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.txt">
                                            </div>
                                            <button type="submit" class="secondary-button" style="background: #2563eb; color: white;">Publish Resource</button>
                                        </form>
                                    </div>
                                </div>

                                <div class="eresources-tab-content <?= $activeEresourcesTab === 'published' ? 'active' : '' ?>" data-tab="published" style="<?= $activeEresourcesTab === 'published' ? '' : 'display:none;' ?>">
                                    <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; margin-bottom: 24px;">
                                        <div style="flex: 1; min-width: 220px;">
                                            <label style="display:block; margin-bottom: 8px; font-weight:600; color:#334155;">Search resources</label>
                                            <input id="eresources-search-input" type="search" placeholder="Search by title, description, or file" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; font-size:13px;">
                                        </div>
                                        <div style="flex: 1; min-width: 220px;">
                                            <label style="display:block; margin-bottom: 8px; font-weight:600; color:#334155;">Filter by category</label>
                                            <select id="eresources-category-filter" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; font-size:13px;">
                                                <option value="">All categories</option>
                                                <?php foreach ($resourceCategories as $resourceCategory): ?>
                                                    <option value="<?= htmlspecialchars($resourceCategory['name']) ?>"><?= htmlspecialchars($resourceCategory['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div style="margin-bottom: 20px;">
                                        <h3>Published Resources</h3>
                                        <p style="font-size: 13px; color: #64748b; margin: 8px 0 0 0;">
                                            <strong>ℹ️ Default Categories:</strong> COMPUTER SCIENCE, HOSPITALITY MANAGEMENT, TOURISM MANAGEMENT, EDUCATION, CRIMINOLOGY, ACCOUNTANCY, BUSINESS ADMINISTRATION.<br>
                                            <strong>Note:</strong> Students/Teachers see E-Resources only for their matching course. Use course-matching categories to control visibility.
                                        </p>
                                    </div>
                                    <?php 
                                    $approvedResources = get_library_resources();
                                    if (empty($approvedResources)): 
                                    ?>
                                        <div style="text-align: center; padding: 40px 20px; background: #f8fafc; border-radius: 20px;">
                                            <i class="fa-solid fa-book" style="font-size: 48px; color: #cbd5e1; margin-bottom: 12px; display: block;"></i>
                                            <p style="color: #64748b;">No approved resources yet</p>
                                        </div>
                                    <?php else: ?>
                                        <div id="eresources-resource-grid" class="eresource-card-grid">
                                            <?php foreach ($approvedResources as $resource): ?>
                                                <div class="eresource-card" data-title="<?= htmlspecialchars(strtolower($resource['title'])) ?>" data-description="<?= htmlspecialchars(strtolower($resource['description'] ?? '')) ?>" data-category="<?= htmlspecialchars($resource['category'] ?? 'General') ?>">
                                                    <h4><?= htmlspecialchars($resource['title']) ?></h4>
                                                    <p><strong>Category:</strong> <?= htmlspecialchars($resource['category'] ?? 'General') ?></p>
                                                    <?php if (!empty($resource['course_year'])): ?>
                                                        <p><strong>Course:</strong> <?= htmlspecialchars($resource['course_year']) ?></p>
                                                    <?php endif; ?>
                                                    <p><strong>Type:</strong> <?= htmlspecialchars($resource['file_type'] ?? 'File') ?></p>
                                                    <p><strong>Downloads:</strong> <?= htmlspecialchars($resource['download_count'] ?? 0) ?></p>
                                                    <div style="display:flex; gap:8px; flex-wrap: wrap; margin-top:12px;">
                                                        <button type="button" class="secondary-button edit-resource-btn" data-resource-id="<?= $resource['id'] ?>" data-category="<?= htmlspecialchars($resource['category'] ?? 'General') ?>" data-course-year="<?= htmlspecialchars($resource['course_year'] ?? '') ?>" style="background: #2563eb; color: white; padding: 8px 12px; font-size: 12px;">Edit</button>
                                                        <form method="post" style="display:inline;">
                                                            <input type="hidden" name="action" value="delete_resource">
                                                            <input type="hidden" name="resource_id" value="<?= $resource['id'] ?>">
                                                            <button type="submit" class="secondary-button" style="background: #fb7185; color: white; padding: 8px 12px; font-size: 12px; border: none; cursor: pointer;" onclick="return confirm('Delete this resource?');">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div id="edit-resource-modal" class="modal" aria-hidden="true">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h2>Edit Resource</h2>
                                            <button type="button" class="modal-close" aria-label="Close">&times;</button>
                                        </div>
                                        <form method="post" class="inventory-form modal-form">
                                            <input type="hidden" name="action" value="save_resource">
                                            <input type="hidden" name="resource_id" id="modal-resource-id" value="">
                                            <label>Category</label>
                                            <select name="category" id="modal-category" required>
                                                <option value="Science">Science</option>
                                                <option value="Mathematics">Mathematics</option>
                                                <option value="Literature">Literature</option>
                                                <option value="History">History</option>
                                                <option value="Technology">Technology</option>
                                                <option value="Arts">Arts</option>
                                                <option value="Physical Education">Physical Education</option>
                                                <option value="General">General</option>
                                            </select>
                                            <label>Course/Year (Optional)</label>
                                            <input type="text" name="course_year" id="modal-course-year" placeholder="e.g., Grade 10 Science">
                                            <div style="display: flex; gap: 12px; margin-top: 24px;">
                                                <button type="submit" class="secondary-button" style="flex: 1; background: #2563eb; color: white;">Save Changes</button>
                                                <button type="button" class="modal-close secondary-button" style="flex: 1; background: #64748b;">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="inventory-panel <?= $activeTab === 'open_access' ? 'active' : '' ?>" id="open_access">
                            <div class="inventory-card">
                                <h2>Open Access Library</h2>
                                <p style="margin-bottom:24px; color:#64748b;">Manage open-source journals, research papers, and vetted external references from one place.</p>
                                <div style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; margin-bottom: 24px;">
                                    <div style="flex: 1; min-width: 220px;">
                                        <label style="display:block; margin-bottom: 8px; font-weight:600; color:#334155;">Search Open Access</label>
                                        <input id="open-access-search-input" type="search" placeholder="Search by title, category, or description" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; font-size:13px;">
                                    </div>
                                    <div style="flex: 1; min-width: 220px;">
                                        <label style="display:block; margin-bottom: 8px; font-weight:600; color:#334155;">Filter by category</label>
                                        <select id="open-access-category-filter" style="width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; font-size:13px;">
                                            <option value="">All categories</option>
                                            <?php foreach ($openAccessCategories as $openAccessCategory): ?>
                                                <option value="<?= htmlspecialchars($openAccessCategory) ?>"><?= htmlspecialchars($openAccessCategory) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div style="display:grid; gap:16px;">
                                    <div style="padding:20px; border:1px solid #e2e8f0; border-radius:16px; background:#ffffff;">
                                        <h3 style="margin:0 0 12px 0;">Add Open Access Resource</h3>
                                        <form method="post" class="inventory-form" style="display:grid; gap:12px;">
                                            <input type="hidden" name="action" value="add_open_access_resource">
                                            <div>
                                                <label>Name</label>
                                                <input type="text" name="name" required placeholder="Resource or journal title" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                                            </div>
                                            <div>
                                                <label>Category</label>
                                                <input type="text" name="category" required placeholder="e.g., Philippine Journals" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                                            </div>
                                            <div>
                                                <label>Description</label>
                                                <textarea name="description" rows="3" placeholder="Short description of the resource" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;"></textarea>
                                            </div>
                                            <div>
                                                <label>External URL</label>
                                                <input type="url" name="url" required placeholder="https://" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:8px;">
                                            </div>
                                            <button type="submit" class="secondary-button" style="background:#2563eb; color:white; padding:12px; border:none; border-radius:10px;">Save Open Access Link</button>
                                        </form>
                                    </div>
                                    <div style="padding:20px; border:1px solid #e2e8f0; border-radius:16px; background:#ffffff;">
                                        <h3 style="margin:0 0 12px 0;">Open Access Library Links</h3>
                                        <?php if (empty($openAccessResources)): ?>
                                            <div style="text-align:center; padding:30px 0; color:#64748b;">No open access links have been added yet.</div>
                                        <?php else: ?>
                                            <div id="open-access-links" style="display:grid; gap:14px;">
                                                <?php foreach ($openAccessResources as $openAccess): ?>
                                                <div class="open-access-card" data-title="<?= htmlspecialchars(strtolower($openAccess['name'])) ?>" data-description="<?= htmlspecialchars(strtolower($openAccess['description'] ?? '')) ?>" data-category="<?= htmlspecialchars($openAccess['category']) ?>" style="border:1px solid #e2e8f0; border-radius:14px; padding:18px; background:#f8fafc;">
                                                    <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                                                        <div style="min-width:0;">
                                                            <h4 style="margin:0 0 6px 0; font-size:16px; color:#111827;"><?= htmlspecialchars($openAccess['name']) ?></h4>
                                                            <p style="margin:0 0 4px 0; font-size:13px; color:#475569;"><strong>Category:</strong> <?= htmlspecialchars($openAccess['category']) ?></p>
                                                            <?php if (!empty($openAccess['description'])): ?>
                                                            <p style="margin:0 0 6px 0; font-size:13px; color:#64748b; line-height:1.5;"><?= htmlspecialchars(substr($openAccess['description'], 0, 120)) ?><?= strlen($openAccess['description']) > 120 ? '...' : '' ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div style="display:flex; gap:10px; align-items:flex-start;">
                                                            <a href="<?= htmlspecialchars($openAccess['url']) ?>" target="_blank" rel="noopener noreferrer" style="background:#10b981; color:white; padding:10px 14px; border-radius:10px; text-decoration:none; font-size:13px;">Open</a>
                                                            <form method="post" style="display:inline;">
                                                                <input type="hidden" name="action" value="delete_open_access_resource">
                                                                <input type="hidden" name="resource_id" value="<?= htmlspecialchars($openAccess['id']) ?>">
                                                                <button type="submit" style="background:#ef4444; color:white; padding:10px 14px; border:none; border-radius:10px; cursor:pointer; font-size:13px;" onclick="return confirm('Delete this Open Access link?');">Remove</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="inventory-panel <?= $activeTab === 'feedback' ? 'active' : '' ?>" id="feedback">
                            <div class="inventory-card">
                                <div class="inventory-card-header">
                                    <div>
                                        <h2>Library Feedback</h2>
                                        <p>Review the same book and service feedback submitted through the Library module.</p>
                                    </div>
                                </div>
                                <div class="catalog-feedback-stats">
                                    <div class="catalog-feedback-stat">
                                        <h4>Book Reviews</h4>
                                        <strong id="catalog-library-review-count">0</strong>
                                        <span>submissions</span>
                                    </div>
                                    <div class="catalog-feedback-stat">
                                        <h4>Avg Book Rating</h4>
                                        <strong id="catalog-library-book-rating">0.0</strong>
                                        <span>out of 5 stars</span>
                                    </div>
                                    <div class="catalog-feedback-stat">
                                        <h4>Avg Service Rating</h4>
                                        <strong id="catalog-library-service-rating">0.0</strong>
                                        <span>out of 5 stars</span>
                                    </div>
                                </div>
                                <div class="catalog-feedback-table-wrap">
                                    <table class="catalog-feedback-table">
                                        <thead>
                                            <tr>
                                                <th>Book Title</th>
                                                <th>Reviewer</th>
                                                <th>Course</th>
                                                <th class="catalog-feedback-rating">Book Rating</th>
                                                <th class="catalog-feedback-rating">Service Rating</th>
                                                <th>Book Review</th>
                                                <th>Service Feedback</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody id="catalog-library-feedback-body">
                                            <tr><td colspan="8" style="padding: 24px; text-align: center; color: #94a3b8;">Loading feedback...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </section>
            </div>
        </main>
    </div>
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
        document.addEventListener('DOMContentLoaded', function () {
            const tabButtons = document.querySelectorAll('.library-tab-button');
            const panels = document.querySelectorAll('.inventory-panel');
            const summarySearch = document.getElementById('summary-search');
            const filter = document.getElementById('summary-category-filter');
            const bookRows = document.querySelectorAll('.book-row');
            const noBooksMessage = document.getElementById('no-books-message');

            let currentActiveTab = '<?= $activeTab ?>';
            let currentEresourcesTab = '<?= $activeEresourcesTab ?>';

            const setFormStateInputs = () => {
                document.querySelectorAll('form').forEach(form => {
                    let activeTabInput = form.querySelector('input[name="active_tab"]');
                    if (!activeTabInput) {
                        activeTabInput = document.createElement('input');
                        activeTabInput.type = 'hidden';
                        activeTabInput.name = 'active_tab';
                        form.appendChild(activeTabInput);
                    }
                    activeTabInput.value = currentActiveTab;

                    let eresourcesTabInput = form.querySelector('input[name="active_eresources_tab"]');
                    if (!eresourcesTabInput) {
                        eresourcesTabInput = document.createElement('input');
                        eresourcesTabInput.type = 'hidden';
                        eresourcesTabInput.name = 'active_eresources_tab';
                        form.appendChild(eresourcesTabInput);
                    }
                    eresourcesTabInput.value = currentEresourcesTab;
                });
            };

            const activateTab = (tabId) => {
                currentActiveTab = tabId;
                tabButtons.forEach(button => {
                    const isActive = button.dataset.tab === tabId;
                    button.classList.toggle('active', isActive);
                    if (isActive) {
                        button.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                    }
                });
                panels.forEach(panel => {
                    panel.classList.toggle('active', panel.id === tabId);
                });
                setFormStateInputs();
                if (tabId === 'feedback') {
                    loadCatalogLibraryFeedback();
                }
            };

            const escapeFeedbackText = (value) => {
                const element = document.createElement('div');
                element.textContent = value || '';
                return element.innerHTML;
            };

            const createFeedbackStars = (rating) => {
                const numericRating = Number.parseInt(rating, 10) || 0;
                let stars = '';
                for (let index = 1; index <= 5; index++) {
                    stars += `<span style="color: ${index <= numericRating ? '#fbbf24' : '#d1d5db'}; font-size: 16px;">★</span>`;
                }
                return stars;
            };

            const loadCatalogLibraryFeedback = () => {
                const tbody = document.getElementById('catalog-library-feedback-body');
                if (!tbody) return;

                tbody.innerHTML = '<tr><td colspan="8" style="padding: 24px; text-align: center; color: #94a3b8;">Loading feedback...</td></tr>';
                fetch('includes/api_library_feedback.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'get_all' }),
                    credentials: 'same-origin'
                })
                    .then(response => response.json())
                    .then(data => {
                        if (!data.success || !data.data || data.data.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="8" style="padding: 24px; text-align: center; color: #94a3b8;">No feedback submitted yet.</td></tr>';
                            document.getElementById('catalog-library-review-count').textContent = '0';
                            document.getElementById('catalog-library-book-rating').textContent = '0.0';
                            document.getElementById('catalog-library-service-rating').textContent = '0.0';
                            return;
                        }

                        let bookRatingTotal = 0;
                        let serviceRatingTotal = 0;
                        let bookRatingCount = 0;
                        let serviceRatingCount = 0;
                        const rows = data.data.map(feedback => {
                            const bookRating = Number.parseInt(feedback.book_rating, 10) || 0;
                            const serviceRating = Number.parseInt(feedback.service_rating, 10) || 0;
                            if (bookRating > 0) {
                                bookRatingTotal += bookRating;
                                bookRatingCount++;
                            }
                            if (serviceRating > 0) {
                                serviceRatingTotal += serviceRating;
                                serviceRatingCount++;
                            }

                            const bookReview = feedback.book_review || '';
                            const serviceFeedback = feedback.service_feedback || '';
                            return `
                                <tr>
                                    <td><strong>${escapeFeedbackText(feedback.book_title || 'Unknown')}</strong></td>
                                    <td>${escapeFeedbackText(feedback.full_name || 'Unknown')}</td>
                                    <td>${escapeFeedbackText(feedback.course_year || '—')}</td>
                                    <td class="catalog-feedback-rating">${bookRating > 0 ? createFeedbackStars(bookRating) : '—'}</td>
                                    <td class="catalog-feedback-rating">${serviceRating > 0 ? createFeedbackStars(serviceRating) : '—'}</td>
                                    <td class="catalog-feedback-text">${escapeFeedbackText(bookReview.substring(0, 60))}${bookReview.length > 60 ? '...' : ''}</td>
                                    <td class="catalog-feedback-text">${escapeFeedbackText(serviceFeedback.substring(0, 60))}${serviceFeedback.length > 60 ? '...' : ''}</td>
                                    <td>${feedback.created_at ? new Date(feedback.created_at).toLocaleDateString() : '—'}</td>
                                </tr>`;
                        }).join('');

                        tbody.innerHTML = rows;
                        document.getElementById('catalog-library-review-count').textContent = data.data.length;
                        document.getElementById('catalog-library-book-rating').textContent = bookRatingCount > 0 ? (bookRatingTotal / bookRatingCount).toFixed(1) : '0.0';
                        document.getElementById('catalog-library-service-rating').textContent = serviceRatingCount > 0 ? (serviceRatingTotal / serviceRatingCount).toFixed(1) : '0.0';
                    })
                    .catch(error => {
                        console.error('Error loading catalog library feedback:', error);
                        tbody.innerHTML = '<tr><td colspan="8" style="padding: 24px; text-align: center; color: #b91c1c;">Unable to load feedback.</td></tr>';
                    });
            };

            tabButtons.forEach(button => {
                button.addEventListener('click', () => activateTab(button.dataset.tab));
            });

            const attachInventoryTabSwipe = () => {
                const tabBar = document.querySelector('.inventory-tab-bar.library-tab-bar');
                if (!tabBar) return;

                let startX = 0;
                let startScroll = 0;
                let isDragging = false;

                tabBar.addEventListener('touchstart', function (event) {
                    if (event.touches.length !== 1) return;
                    startX = event.touches[0].clientX;
                    startScroll = tabBar.scrollLeft;
                    isDragging = true;
                }, { passive: true });

                tabBar.addEventListener('touchmove', function (event) {
                    if (!isDragging || event.touches.length !== 1) return;
                    const x = event.touches[0].clientX;
                    const delta = startX - x;
                    tabBar.scrollLeft = startScroll + delta;
                }, { passive: true });

                const stopDrag = () => {
                    isDragging = false;
                };

                tabBar.addEventListener('touchend', stopDrag, { passive: true });
                tabBar.addEventListener('touchcancel', stopDrag, { passive: true });
            };

            attachInventoryTabSwipe();

            setFormStateInputs();
            if (currentActiveTab === 'feedback') {
                loadCatalogLibraryFeedback();
            }

            const filterSummaryBooks = () => {
                const query = summarySearch ? summarySearch.value.trim().toLowerCase() : '';
                const selected = filter ? filter.value : '';
                let visibleCount = 0;

                bookRows.forEach(row => {
                    const title = row.dataset.title || '';
                    const author = row.dataset.author || '';
                    const category = row.dataset.category || 'General';
                    const matchesSearch = !query || title.includes(query) || author.includes(query);
                    const matchesCategory = !selected || category === selected;
                    const show = matchesSearch && matchesCategory;
                    row.style.display = show ? '' : 'none';
                    if (show) visibleCount++;
                });

                if (noBooksMessage) {
                    noBooksMessage.style.display = visibleCount === 0 ? '' : 'none';
                }
            };

            if (summarySearch) {
                summarySearch.addEventListener('input', filterSummaryBooks);
            }
            if (filter) {
                filter.addEventListener('change', filterSummaryBooks);
            }

            const editModal = document.getElementById('book-edit-modal');
            const modalCloseButtons = editModal ? editModal.querySelectorAll('.modal-close') : [];
            const modalBookId = document.getElementById('modal-book-id');
            const modalTitle = document.getElementById('modal-title');
            const modalAuthor = document.getElementById('modal-author');
            const modalIsbn = document.getElementById('modal-isbn');
            const modalLccLetterLine = document.getElementById('modal-lcc-letter-line');
            const modalLccClassNumber = document.getElementById('modal-lcc-class-number');
            const modalLccCutterCode = document.getElementById('modal-lcc-cutter-code');
            const modalLccPublishedYear = document.getElementById('modal-lcc-published-year');
            const modalAdditionalInfoType = document.getElementById('modal-additional-info-type');
            const modalAdditionalInfoQuantity = document.getElementById('modal-additional-info-quantity');
            const additionalInfoQuantityContainer = document.getElementById('additional-info-quantity-container');
            const additionalInfoQuantityLabel = document.getElementById('additional-info-quantity-label');
            const modalAdditionalInfoGeneration = document.getElementById('modal-additional-info-generation');
            const additionalInfoGenerationContainer = document.getElementById('additional-info-generation-container');
            const modalAddMoreButton = document.getElementById('modal-add-more-single-input');
            const modalSingleInputEntriesList = document.getElementById('modal-single-input-entries-list');
            const modalAdditionalInfoQuantityInput = document.getElementById('modal-additional-info-quantity');
            let modalSingleInputEntries = [];

            const updateModalAddMoreButtonVisibility = () => {
                if (modalAdditionalInfoGeneration && modalAdditionalInfoGeneration.value === 'single') {
                    if (modalAddMoreButton) modalAddMoreButton.style.display = '';
                } else {
                    if (modalAddMoreButton) modalAddMoreButton.style.display = 'none';
                    modalSingleInputEntries = [];
                    if (modalSingleInputEntriesList) {
                        modalSingleInputEntriesList.innerHTML = '<small style="color: #666; display: block; margin-bottom: 8px;">Single entries:</small>';
                        modalSingleInputEntriesList.style.display = 'none';
                    }
                }
            };

            const modalCategorySelect = document.getElementById('modal-category-select');
            const modalNewCategory = document.getElementById('modal-new-category');
            const modalTotalCopies = document.getElementById('modal-total-copies');
            const modalAvailableCopies = document.getElementById('modal-available-copies');
            const modalOverview = document.getElementById('modal-overview');
            const modalCurrentCover = document.getElementById('modal-current-cover');
            const modalCoverLink = document.getElementById('modal-cover-link');
            const imageModal = document.getElementById('book-image-modal');
            const imageModalCloseButtons = imageModal ? imageModal.querySelectorAll('.modal-close') : [];
            const imagePreview = document.getElementById('book-image-preview');
            const imagePreviewTitle = document.getElementById('book-image-preview-title');

            const fillModal = (book) => {
                if (!book || !modalBookId) return;
                
                // Clear modal entries when loading new book
                modalSingleInputEntries = [];
                if (modalSingleInputEntriesList) {
                    modalSingleInputEntriesList.innerHTML = '<small style="color: #666; display: block; margin-bottom: 8px;">Single entries:</small>';
                    modalSingleInputEntriesList.style.display = 'none';
                }
                
                modalBookId.value = book.id || '';
                modalTitle.value = book.title || '';
                modalAuthor.value = book.author || '';
                modalIsbn.value = book.isbn || '';
                modalLccLetterLine.value = book.lccLetterLine || '';
                modalLccClassNumber.value = book.lccClassNumber || '';
                modalLccCutterCode.value = book.lccCutterCode || '';
                modalLccPublishedYear.value = book.lccPublishedYear || '';
                modalAdditionalInfoType.value = book.additionalInfoType || '';
                modalAdditionalInfoQuantity.value = book.additionalInfoQuantity || '';
                
                // Reset generation method to auto by default
                if (modalAdditionalInfoGeneration) {
                    modalAdditionalInfoGeneration.value = 'auto';
                }
                
                updateAdditionalInfoQuantityVisibility();
                modalOverview.value = book.overview || '';
                modalNewCategory.value = '';

                if (modalCategorySelect) {
                    const optionToSelect = Array.from(modalCategorySelect.options).find(opt => opt.value === book.category);
                    if (optionToSelect) {
                        modalCategorySelect.value = book.category;
                    } else {
                        modalCategorySelect.value = '';
                    }
                }

                if (book.coverUrl) {
                    modalCurrentCover.style.display = '';
                    modalCoverLink.href = book.coverUrl;
                    modalCoverLink.textContent = 'View current image';
                } else if (modalCurrentCover) {
                    modalCurrentCover.style.display = 'none';
                }
            };

            const openModal = (book) => {
                if (!editModal) return;
                fillModal(book);
                editModal.classList.add('show');
                editModal.setAttribute('aria-hidden', 'false');
            };

            const closeModal = () => {
                if (!editModal) return;
                editModal.classList.remove('show');
                editModal.setAttribute('aria-hidden', 'true');
            };

            const openImageModal = (coverUrl, title) => {
                if (!imageModal || !imagePreview || !imagePreviewTitle) return;
                imagePreview.src = coverUrl || '';
                imagePreview.alt = title || 'Book cover preview';
                imagePreviewTitle.textContent = title || 'Book cover preview';
                imageModal.classList.add('show');
                imageModal.setAttribute('aria-hidden', 'false');
            };

            const closeImageModal = () => {
                if (!imageModal) return;
                imageModal.classList.remove('show');
                imageModal.setAttribute('aria-hidden', 'true');
                if (imagePreview) {
                    imagePreview.src = '';
                }
            };

            document.querySelectorAll('.edit-book-button').forEach(button => {
                button.addEventListener('click', () => {
                    const book = {
                        id: button.dataset.bookId,
                        title: button.dataset.title,
                        author: button.dataset.author,
                        isbn: button.dataset.isbn,
                        lccLetterLine: button.dataset.lccLetterLine,
                        lccClassNumber: button.dataset.lccClassNumber,
                        lccCutterCode: button.dataset.lccCutterCode,
                        lccPublishedYear: button.dataset.lccPublishedYear,
                        lccAdditionalInfo: button.dataset.lccAdditionalInfo,
                        category: button.dataset.category,
                        totalCopies: button.dataset.totalCopies,
                        availableCopies: button.dataset.availableCopies,
                        overview: button.dataset.overview,
                        coverUrl: button.dataset.coverUrl,
                    };
                    openModal(book);
                });
            });

            const updateAdditionalInfoQuantityVisibility = () => {
                const selectedType = modalAdditionalInfoType.value;
                if (selectedType && selectedType !== '') {
                    additionalInfoQuantityContainer.style.display = '';
                    additionalInfoQuantityLabel.textContent = selectedType.charAt(0).toUpperCase() + selectedType.slice(1) + ' quantity';
                    modalAdditionalInfoQuantity.placeholder = `Enter number of ${selectedType}s`;
                    
                    // Show generation dropdown only for volume, part, number
                    if (['volume', 'part', 'number'].includes(selectedType)) {
                        additionalInfoGenerationContainer.style.display = '';
                        updateModalAddMoreButtonVisibility();  // Show/hide button based on generation type
                    } else {
                        additionalInfoGenerationContainer.style.display = 'none';
                        if (modalAdditionalInfoGeneration) modalAdditionalInfoGeneration.value = 'single';
                        const modalAddMoreButton = document.getElementById('modal-add-more-single-input');
                        if (modalAddMoreButton) modalAddMoreButton.style.display = 'none';
                    }
                } else {
                    additionalInfoQuantityContainer.style.display = 'none';
                    additionalInfoGenerationContainer.style.display = 'none';
                    modalAdditionalInfoQuantity.value = '';
                    if (modalAdditionalInfoGeneration) modalAdditionalInfoGeneration.value = 'single';
                    const modalAddMoreButton = document.getElementById('modal-add-more-single-input');
                    if (modalAddMoreButton) modalAddMoreButton.style.display = 'none';
                }
            };

            if (modalAdditionalInfoType) {
                modalAdditionalInfoType.addEventListener('change', updateAdditionalInfoQuantityVisibility);
            }

            if (modalAdditionalInfoGeneration) {
                modalAdditionalInfoGeneration.addEventListener('change', updateModalAddMoreButtonVisibility);
            }

            // For add book form
            const addAdditionalInfoType = document.getElementById('add-additional-info-type');
            const addAdditionalInfoQuantityContainer = document.getElementById('add-additional-info-quantity-container');
            const addAdditionalInfoQuantityLabel = document.getElementById('add-additional-info-quantity-label');
            const addAdditionalInfoGenerationContainer = document.getElementById('add-additional-info-generation-container');
            const addMoreButton = document.getElementById('add-more-single-input');
            const singleInputEntriesList = document.getElementById('single-input-entries-list');
            const addAdditionalInfoQuantityInput = document.getElementById('add-additional-info-quantity');
            const addAdditionalInfoGeneration = document.getElementById('add-additional-info-generation');
            let singleInputEntries = [];

            const updateAddMoreButtonVisibility = () => {
                if (addAdditionalInfoGeneration && addAdditionalInfoGeneration.value === 'single') {
                    if (addMoreButton) addMoreButton.style.display = '';
                } else {
                    if (addMoreButton) addMoreButton.style.display = 'none';
                    singleInputEntries = [];
                    singleInputEntriesList.innerHTML = '<small style="color: #666; display: block; margin-bottom: 8px;">Single entries:</small>';
                    singleInputEntriesList.style.display = 'none';
                }
            };

            const updateAddAdditionalInfoQuantityVisibility = () => {
                const selectedType = addAdditionalInfoType.value;
                const generateDropdown = document.getElementById('add-additional-info-generation');
                
                if (selectedType && selectedType !== '') {
                    addAdditionalInfoQuantityContainer.style.display = '';
                    addAdditionalInfoQuantityLabel.textContent = selectedType.charAt(0).toUpperCase() + selectedType.slice(1) + ' quantity';
                    document.getElementById('add-additional-info-quantity').placeholder = `Enter number of ${selectedType}s`;
                    
                    // Show generation dropdown only for volume, part, number
                    if (['volume', 'part', 'number'].includes(selectedType)) {
                        addAdditionalInfoGenerationContainer.style.display = '';
                        updateAddMoreButtonVisibility();  // Show/hide button based on generation type
                    } else {
                        addAdditionalInfoGenerationContainer.style.display = 'none';
                        if (generateDropdown) generateDropdown.value = 'single';
                        if (addMoreButton) addMoreButton.style.display = 'none';
                    }
                } else {
                    addAdditionalInfoQuantityContainer.style.display = 'none';
                    addAdditionalInfoGenerationContainer.style.display = 'none';
                    document.getElementById('add-additional-info-quantity').value = '';
                    if (generateDropdown) generateDropdown.value = 'single';
                    if (addMoreButton) addMoreButton.style.display = 'none';
                }
            };

            if (addAdditionalInfoType) {
                addAdditionalInfoType.addEventListener('change', updateAddAdditionalInfoQuantityVisibility);
            }

            if (addAdditionalInfoGeneration) {
                addAdditionalInfoGeneration.addEventListener('change', updateAddMoreButtonVisibility);
            }

            // Clear add form entries on page load
            document.querySelectorAll('form.inventory-form').forEach(form => {
                const actionInput = form.querySelector('input[name="action"]');
                if (actionInput && actionInput.value === 'add_book') {
                    singleInputEntries = [];
                    const entriesList = form.querySelector('#single-input-entries-list');
                    if (entriesList) {
                        entriesList.innerHTML = '<small style="color: #666; display: block; margin-bottom: 8px;">Single entries:</small>';
                        entriesList.style.display = 'none';
                    }
                }
            });

            if (addMoreButton) {
                addMoreButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    const value = addAdditionalInfoQuantityInput.value.trim();
                    if (!value) {
                        alert('Please enter a value');
                        return;
                    }
                    singleInputEntries.push(value);
                    
                    if (singleInputEntries.length > 0) {
                        singleInputEntriesList.style.display = '';
                        singleInputEntriesList.innerHTML = '<small style="color: #666; display: block; margin-bottom: 8px;">Single entries:</small>' +
                            singleInputEntries.map((entry, idx) => `
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; padding: 6px; background: #f0f0f0; border-radius: 4px;">
                                    <span>${entry}</span>
                                    <button type="button" class="remove-entry" data-index="${idx}" style="margin-left: auto; padding: 4px 8px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px;">Remove</button>
                                </div>
                            `).join('');
                        
                        document.querySelectorAll('.remove-entry').forEach(btn => {
                            btn.addEventListener('click', (e) => {
                                e.preventDefault();
                                const idx = parseInt(btn.dataset.index);
                                singleInputEntries.splice(idx, 1);
                                updateAddMoreButtonVisibility();
                                updateAddMoreButtonVisibility();
                            });
                        });
                    }
                    addAdditionalInfoQuantityInput.value = '';
                });
            }

            // Handle "Add More" button click for single inputs in modal
            if (modalAddMoreButton) {
                modalAddMoreButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    const value = modalAdditionalInfoQuantityInput.value.trim();
                    if (!value) {
                        alert('Please enter a value');
                        return;
                    }
                    modalSingleInputEntries.push(value);
                    
                    if (modalSingleInputEntries.length > 0) {
                        modalSingleInputEntriesList.style.display = '';
                        modalSingleInputEntriesList.innerHTML = '<small style="color: #666; display: block; margin-bottom: 8px;">Single entries:</small>' +
                            modalSingleInputEntries.map((entry, idx) => `
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; padding: 6px; background: #f0f0f0; border-radius: 4px;">
                                    <span>${entry}</span>
                                    <button type="button" class="modal-remove-entry" data-index="${idx}" style="margin-left: auto; padding: 4px 8px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px;">Remove</button>
                                </div>
                            `).join('');
                        
                        document.querySelectorAll('.modal-remove-entry').forEach(btn => {
                            btn.addEventListener('click', (e) => {
                                e.preventDefault();
                                const idx = parseInt(btn.dataset.index);
                                modalSingleInputEntries.splice(idx, 1);
                                updateModalAddMoreButtonVisibility();
                                updateModalAddMoreButtonVisibility();
                            });
                        });
                    }
                    modalAdditionalInfoQuantityInput.value = '';
                });
            }

            // Handle form submission for add book form
            document.querySelectorAll('form.inventory-form').forEach(form => {
                form.addEventListener('submit', (e) => {
                    const actionInput = form.querySelector('input[name="action"]');
                    if (!actionInput) return;
                    
                    if (actionInput.value === 'add_book') {
                        const hiddenField = form.querySelector('#add-single-input-entries');
                        if (hiddenField) {
                            // Always set the hidden field value (even if empty)
                            hiddenField.value = JSON.stringify(singleInputEntries);
                            console.log('Setting add_book hidden field:', hiddenField.value);
                        }
                    } else if (actionInput.value === 'save_book') {
                        const hiddenField = form.querySelector('#modal-single-input-entries');
                        if (hiddenField) {
                            // Always set the hidden field value (even if empty)
                            hiddenField.value = JSON.stringify(modalSingleInputEntries);
                            console.log('Setting save_book hidden field:', hiddenField.value);
                        }
                    }
                });
            });

            modalCloseButtons.forEach(button => button.addEventListener('click', closeModal));
            imageModalCloseButtons.forEach(button => button.addEventListener('click', closeImageModal));
            window.addEventListener('click', (event) => {
                if (event.target === editModal) {
                    closeModal();
                }
                if (event.target === imageModal) {
                    closeImageModal();
                }
            });

            document.querySelectorAll('.book-cover-wrapper').forEach(wrapper => {
                wrapper.addEventListener('click', () => {
                    const row = wrapper.closest('.book-row');
                    if (!row) return;
                    const coverUrl = row.dataset.coverUrl;
                    const title = row.dataset.title || 'Book cover preview';
                    if (coverUrl) {
                        openImageModal(coverUrl, title);
                    }
                });
            });

            <?php if ($showEditModal && $editingBook): ?>
                openModal(<?= json_encode([
                    'id' => $editingBook['id'] ?? '',
                    'title' => $_POST['title'] ?? $editingBook['title'] ?? '',
                    'author' => $_POST['author'] ?? $editingBook['author'] ?? '',
                    'isbn' => $_POST['isbn'] ?? $editingBook['isbn'] ?? '',
                    'category' => $_POST['category_select'] ?: ($editingBook['category'] ?? 'General'),
                    'totalCopies' => $_POST['total_copies'] ?? $editingBook['total_copies'] ?? 1,
                    'availableCopies' => $_POST['available_copies'] ?? $editingBook['available_copies'] ?? 0,
                    'overview' => $_POST['overview'] ?? $editingBook['overview'] ?? '',
                    'coverUrl' => $editingBook['cover_url'] ?? '',
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
            <?php endif; ?>

            // E-Resources tab switching
            const eresourcesTabButtons = document.querySelectorAll('.eresources-tab-button');
            const eresourcesTabContents = document.querySelectorAll('.eresources-tab-content');

            eresourcesTabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const tabId = button.dataset.tab;
                    currentActiveTab = 'eresources';
                    currentEresourcesTab = tabId;
                    eresourcesTabButtons.forEach(btn => {
                        btn.classList.remove('active');
                        btn.style.borderBottomColor = 'transparent';
                        btn.style.color = '#64748b';
                    });
                    eresourcesTabContents.forEach(content => {
                        content.style.display = 'none';
                        content.classList.remove('active');
                    });
                    
                    button.classList.add('active');
                    button.style.borderBottomColor = '#2563eb';
                    button.style.color = '#2563eb';
                    const currentContent = document.querySelector(`.eresources-tab-content[data-tab="${tabId}"]`);
                    if (currentContent) {
                        currentContent.style.display = 'block';
                        currentContent.classList.add('active');
                    }
                    setFormStateInputs();
                });
            });

            // Reject resource functionality
            document.querySelectorAll('.reject-resource-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (confirm('Are you sure you want to reject this resource? This action cannot be undone.')) {
                        const resourceId = this.dataset.resourceId;
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `
                            <input type="hidden" name="action" value="reject_resource">
                            <input type="hidden" name="resource_id" value="${resourceId}">
                            <input type="hidden" name="active_tab" value="${currentActiveTab}">
                            <input type="hidden" name="active_eresources_tab" value="${currentEresourcesTab}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });

            const eresourcesSearch = document.getElementById('eresources-search-input');
            const eresourcesCategoryFilter = document.getElementById('eresources-category-filter');
            const eresourceCards = document.querySelectorAll('.eresource-card');

            const filterEresources = () => {
                const query = eresourcesSearch ? eresourcesSearch.value.trim().toLowerCase() : '';
                const selectedCategory = eresourcesCategoryFilter ? eresourcesCategoryFilter.value : '';

                eresourceCards.forEach(card => {
                    const title = card.dataset.title || '';
                    const description = card.dataset.description || '';
                    const category = card.dataset.category || '';
                    const matchesSearch = !query || title.includes(query) || description.includes(query);
                    const matchesCategory = !selectedCategory || category === selectedCategory;
                    card.style.display = matchesSearch && matchesCategory ? '' : 'none';
                });
            };

            if (eresourcesSearch) {
                eresourcesSearch.addEventListener('input', filterEresources);
            }
            if (eresourcesCategoryFilter) {
                eresourcesCategoryFilter.addEventListener('change', filterEresources);
            }

            // Edit resource modal
            const editResourceModal = document.getElementById('edit-resource-modal');
            const editResourceCloseButtons = editResourceModal ? editResourceModal.querySelectorAll('.modal-close') : [];
            const modalResourceId = document.getElementById('modal-resource-id');
            const modalCategory = document.getElementById('modal-category');
            const modalCourseYear = document.getElementById('modal-course-year');

            const openEditModal = (resourceId, category, courseYear) => {
                if (!editResourceModal) return;
                modalResourceId.value = resourceId;
                modalCategory.value = category;
                modalCourseYear.value = courseYear;
                editResourceModal.classList.add('show');
                editResourceModal.setAttribute('aria-hidden', 'false');
            };

            const closeEditModal = () => {
                if (!editResourceModal) return;
                editResourceModal.classList.remove('show');
                editResourceModal.setAttribute('aria-hidden', 'true');
            };

            document.querySelectorAll('.edit-resource-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const resourceId = btn.dataset.resourceId;
                    const category = btn.dataset.category;
                    const courseYear = btn.dataset.courseYear;
                    openEditModal(resourceId, category, courseYear);
                });
            });

            editResourceCloseButtons.forEach(btn => btn.addEventListener('click', closeEditModal));
            window.addEventListener('click', (event) => {
                if (event.target === editResourceModal) {
                    closeEditModal();
                }
            });

            // Handle remove book button clicks (both summary and management tabs)
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-book-button')) {
                    e.preventDefault();
                    const bookId = e.target.dataset.bookId;
                    const bookTitle = e.target.dataset.bookTitle;

                    if (confirm(`Are you sure you want to remove "${bookTitle}" from the inventory? This action cannot be undone and will also remove all reservations and borrow records for this book.`)) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.style.display = 'none';

                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = 'remove_book';

                        const bookIdInput = document.createElement('input');
                        bookIdInput.type = 'hidden';
                        bookIdInput.name = 'book_id';
                        bookIdInput.value = bookId;

                        const activeTabInput = document.createElement('input');
                        activeTabInput.type = 'hidden';
                        activeTabInput.name = 'active_tab';
                        activeTabInput.value = currentActiveTab;

                        form.appendChild(actionInput);
                        form.appendChild(bookIdInput);
                        form.appendChild(activeTabInput);
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
            });

            // Handle modal remove book button
            const modalRemoveBookBtn = document.getElementById('modal-remove-book');
            if (modalRemoveBookBtn) {
                modalRemoveBookBtn.addEventListener('click', function() {
                    const bookId = document.getElementById('modal-book-id').value;
                    const bookTitle = document.getElementById('modal-title').value;

                    if (confirm(`Are you sure you want to remove "${bookTitle}" from the inventory? This action cannot be undone and will also remove all reservations and borrow records for this book.`)) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.style.display = 'none';

                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = 'remove_book';

                        const bookIdInput = document.createElement('input');
                        bookIdInput.type = 'hidden';
                        bookIdInput.name = 'book_id';
                        bookIdInput.value = bookId;

                        const activeTabInput = document.createElement('input');
                        activeTabInput.type = 'hidden';
                        activeTabInput.name = 'active_tab';
                        activeTabInput.value = currentActiveTab;

                        form.appendChild(actionInput);
                        form.appendChild(bookIdInput);
                        form.appendChild(activeTabInput);
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }
            const categorySelect = document.getElementById('category-select');
            const newCategoryInput = document.getElementById('new-category');
            const removeBookSelect = document.getElementById('remove-book-select');
            const removeBookBtn = document.getElementById('remove-book-btn');

            const syncCategoryFields = () => {
                if (!categorySelect || !newCategoryInput) return;
                if (categorySelect.value) {
                    newCategoryInput.disabled = true;
                    newCategoryInput.value = '';
                } else {
                    newCategoryInput.disabled = false;
                }

                if (newCategoryInput.value.trim()) {
                    categorySelect.disabled = true;
                    categorySelect.value = '';
                } else {
                    categorySelect.disabled = false;
                }
            };

            if (categorySelect) {
                categorySelect.addEventListener('change', syncCategoryFields);
            }
            if (newCategoryInput) {
                newCategoryInput.addEventListener('input', syncCategoryFields);
            }

            syncCategoryFields();

            if (removeBookSelect && removeBookBtn) {
                removeBookSelect.addEventListener('change', function() {
                    removeBookBtn.disabled = !this.value;
                });

                removeBookBtn.addEventListener('click', function() {
                    const selectedOption = removeBookSelect.options[removeBookSelect.selectedIndex];
                    const bookId = removeBookSelect.value;
                    const bookTitle = selectedOption.dataset.title;

                    if (confirm(`Are you sure you want to remove "${bookTitle}" from the inventory? This action cannot be undone and will also remove all reservations and borrow records for this book.`)) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.style.display = 'none';

                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = 'remove_book';

                        const bookIdInput = document.createElement('input');
                        bookIdInput.type = 'hidden';
                        bookIdInput.name = 'book_id';
                        bookIdInput.value = bookId;

                        const activeTabInput = document.createElement('input');
                        activeTabInput.type = 'hidden';
                        activeTabInput.name = 'active_tab';
                        activeTabInput.value = 'summary';

                        form.appendChild(actionInput);
                        form.appendChild(bookIdInput);
                        form.appendChild(activeTabInput);
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            }
        });
    </script>
    <script src="assets/js/app.js" defer></script>
    <?php include './AI CHAT BOT/chat_widget.php'; ?>
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
