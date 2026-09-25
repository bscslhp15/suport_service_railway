<?php
/**
 * Auto-Cancel Reservations After 1 Day
 * 
 * This script automatically cancels book reservations that were not picked up
 * within 1 day of being reserved. It can be run manually via API call or as a
 * scheduled cron job.
 * 
 * Usage:
 * - Direct call: http://localhost/THESIS/SUPPORTSERVICESYSTEM/includes/api_auto_cancel_reservations.php
 * - Or via cron: /usr/bin/php /path/to/api_auto_cancel_reservations.php
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

try {
    $pdo = get_db();
    
    // Find all reservations that:
    // 1. Are "Active" (not yet picked up or completed)
    // 2. Have expired (expires_at < NOW())
    
    $stmt = $pdo->prepare("
        SELECT lr.id, lr.user_id, lr.book_id, lr.reserved_at, lb.title, u.email, u.full_name
        FROM library_reservations lr
        JOIN library_books lb ON lr.book_id = lb.id
        JOIN users u ON lr.user_id = u.id
        WHERE lr.status = 'Active'
        AND lr.expires_at < NOW()
        ORDER BY lr.reserved_at ASC
    ");
    
    $stmt->execute();
    $expiredReservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cancelledCount = 0;
    $errors = [];
    
    foreach ($expiredReservations as $reservation) {
        try {
            $reservationId = (int)$reservation['id'];
            $userId = (int)$reservation['user_id'];
            $bookId = (int)$reservation['book_id'];
            
            // Get the reservation book copy info for restoring available copies
            $getRes = $pdo->prepare('SELECT * FROM library_reservations WHERE id = :id');
            $getRes->execute(['id' => $reservationId]);
            $res = $getRes->fetch(PDO::FETCH_ASSOC);
            
            if (!$res) {
                continue;
            }
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Update reservation status to Cancelled
            $updateStmt = $pdo->prepare('UPDATE library_reservations SET status = :status WHERE id = :id');
            $updateStmt->execute(['status' => 'Cancelled', 'id' => $reservationId]);
            
            // Restore the book copy back to available
            $restoreStmt = $pdo->prepare('
                UPDATE library_books 
                SET available_copies = available_copies + 1
                WHERE id = :book_id
            ');
            $restoreStmt->execute(['book_id' => $bookId]);
            
            // Create notification for student
            $notifStmt = $pdo->prepare('
                INSERT INTO library_notifications 
                (user_id, reservation_id, type, title, message, is_read, created_at) 
                VALUES (:user_id, :reservation_id, :type, :title, :message, 0, NOW())
            ');
            
            $notifStmt->execute([
                'user_id' => $userId,
                'reservation_id' => $reservationId,
                'type' => 'reservation_expired',
                'title' => 'Reservation Auto-Cancelled',
                'message' => "Your reservation for \"" . $reservation['title'] . "\" was automatically cancelled because the reservation hold time expired."
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            // Send email notification
            if ($reservation['email']) {
                $subject = 'Library Reservation Expired';
                $emailBody = "Hello " . htmlspecialchars($reservation['full_name']) . ",\n\n";
                $emailBody .= "Your reservation for \"" . htmlspecialchars($reservation['title']) . "\" was automatically cancelled.\n\n";
                $emailBody .= "Reason: The reservation was not picked up within 24 hours of being made.\n\n";
                $emailBody .= "You can reserve this book again from the Library module on your dashboard.\n\n";
                $emailBody .= "Best regards,\nPASS College Support System";
                
                mail(
                    $reservation['email'],
                    $subject,
                    $emailBody,
                    "From: PASS Support System <no-reply@passcollege.local>\r\n" .
                    "Content-Type: text/plain; charset=UTF-8\r\n"
                );
            }
            
            $cancelledCount++;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error cancelling reservation {$reservationId}: " . $e->getMessage();
        }
    }
    
    // Process reservation queue for books that had reservations cancelled
    $processedBooks = [];
    foreach ($expiredReservations as $reservation) {
        $bookId = (int)$reservation['book_id'];
        if (!in_array($bookId, $processedBooks)) {
            process_reservation_queue($bookId);
            $processedBooks[] = $bookId;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Auto-cancellation completed",
        'cancelled_count' => $cancelledCount,
        'total_processed' => count($expiredReservations),
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
