<?php
require_once __DIR__ . '/db.php';
// ==========================================
// CLINIC SYSTEM FUNCTIONS
// ==========================================

function ensure_clinic_schema(): void {
    $pdo = get_db();

    // Clinic Status Table
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS clinic_status (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            is_available TINYINT(1) NOT NULL DEFAULT 1,
            reason VARCHAR(255) DEFAULT NULL,
            duration_hours INT DEFAULT NULL,
            nurse_back_date DATE DEFAULT NULL,
            nurse_back_time TIME DEFAULT NULL,
            updated_by INT UNSIGNED DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Ensure columns exist for older schemas
    try {
        $pdo->exec('ALTER TABLE clinic_status ADD COLUMN nurse_back_date DATE DEFAULT NULL');
    } catch (PDOException $e) {
        // ignore if column already exists or alter not supported
    }
    try {
        $pdo->exec('ALTER TABLE clinic_status ADD COLUMN nurse_back_time TIME DEFAULT NULL');
    } catch (PDOException $e) {
        // ignore if column already exists or alter not supported
    }

    // Clinic Health Records (Digital Health Cards)
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS clinic_health_records (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            medical_history TEXT DEFAULT NULL,
            allergies TEXT DEFAULT NULL,
            current_medications TEXT DEFAULT NULL,
            blood_type VARCHAR(10) DEFAULT NULL,
            emergency_contact_name VARCHAR(150) DEFAULT NULL,
            emergency_contact_phone VARCHAR(20) DEFAULT NULL,
            emergency_contact_relationship VARCHAR(50) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_record (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Daily Health Data
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS clinic_health_data (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            record_date DATE NOT NULL,
            sleep_hours DECIMAL(3,1) DEFAULT NULL,
            water_intake_liters DECIMAL(3,1) DEFAULT NULL,
            height_cm DECIMAL(5,1) DEFAULT NULL,
            weight_kg DECIMAL(5,1) DEFAULT NULL,
            mood VARCHAR(50) DEFAULT NULL,
            bmi DECIMAL(4,1) DEFAULT NULL,
            health_status VARCHAR(100) DEFAULT NULL,
            recommendations TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_date (user_id, record_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Clinic Medicines Inventory
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS clinic_medicines (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            generic_name VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            category VARCHAR(100) DEFAULT NULL,
            stock_quantity INT NOT NULL DEFAULT 0,
            min_stock_level INT NOT NULL DEFAULT 5,
            unit VARCHAR(50) DEFAULT "tablets",
            expiry_date DATE DEFAULT NULL,
            batch_number VARCHAR(100) DEFAULT NULL,
            location VARCHAR(100) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Clinic Medicine Batches
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS clinic_medicine_batches (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            medicine_id INT UNSIGNED NOT NULL,
            batch_number VARCHAR(100) NOT NULL,
            quantity INT NOT NULL DEFAULT 0,
            expiry_date DATE DEFAULT NULL,
            location VARCHAR(100) DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (medicine_id) REFERENCES clinic_medicines(id) ON DELETE CASCADE,
            UNIQUE KEY unique_batch_per_medicine (medicine_id, batch_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Clinic Medicine Categories
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS clinic_medicine_categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Clinic Visits
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS clinic_visits (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED DEFAULT NULL,
            name VARCHAR(150) NOT NULL,
            visitor_type ENUM(\'student\', \'teacher\', \'visitor\') NOT NULL DEFAULT \'visitor\',
            student_number VARCHAR(50) DEFAULT NULL,
            employee_id VARCHAR(50) DEFAULT NULL,
            course_department VARCHAR(150) DEFAULT NULL,
            reason_for_visit VARCHAR(255) DEFAULT NULL,
            checkin_method VARCHAR(50) NOT NULL DEFAULT \'qr\',
            time_in DATETIME NOT NULL,
            time_out DATETIME DEFAULT NULL,
            duration_minutes INT DEFAULT NULL,
            status ENUM(\'waiting\', \'in_consultation\', \'pending_logout\', \'completed\') NOT NULL DEFAULT \'waiting\',
            priority ENUM(\'low\', \'medium\', \'high\', \'emergency\') NOT NULL DEFAULT \'medium\',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Ensure logout pending support exists in status enum for existing tables
    try {
        $pdo->exec("ALTER TABLE clinic_visits MODIFY status ENUM('waiting', 'in_consultation', 'pending_logout', 'completed') NOT NULL DEFAULT 'waiting'");
    } catch (PDOException $e) {
        // handle older versions gracefully if field already has the same values
    }

    // Add remarks and completion_status columns for visit logging
    try {
        $pdo->exec('ALTER TABLE clinic_visits ADD COLUMN remarks VARCHAR(255) DEFAULT NULL');
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }
    try {
        $pdo->exec("ALTER TABLE clinic_visits ADD COLUMN completion_status ENUM('pending', 'completed') NOT NULL DEFAULT 'pending'");
    } catch (PDOException $e) {
        // Column might already exist, ignore
    }

    // Clinic Treatments
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS clinic_treatments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            visit_id INT UNSIGNED NOT NULL,
            symptoms TEXT DEFAULT NULL,
            diagnosis TEXT DEFAULT NULL,
            treatment_provided TEXT DEFAULT NULL,
            medicines_given TEXT DEFAULT NULL,
            follow_up_instructions TEXT DEFAULT NULL,
            referred_to_specialist TINYINT(1) DEFAULT 0,
            specialist_referral TEXT DEFAULT NULL,
            treated_by INT UNSIGNED DEFAULT NULL,
            treatment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (visit_id) REFERENCES clinic_visits(id) ON DELETE CASCADE,
            FOREIGN KEY (treated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Medical Clearance Requests
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS clinic_clearance_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            request_type VARCHAR(100) NOT NULL,
            purpose TEXT NOT NULL,
            documents_uploaded TEXT DEFAULT NULL,
            status ENUM(\'pending\', \'approved\', \'rejected\', \'requires_more_info\', \'cancelled\') NOT NULL DEFAULT \'pending\',
            reviewed_by INT UNSIGNED DEFAULT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            review_notes TEXT DEFAULT NULL,
            clearance_expiry DATE DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Add cancelled_at column if it doesn't exist (for schema migration)
    try {
        $pdo->exec('ALTER TABLE clinic_clearance_requests ADD COLUMN cancelled_at DATETIME DEFAULT NULL');
    } catch (Exception $e) {
        // Column might already exist, ignore error
    }

    // Update ENUM to include 'cancelled' status if not already included
    try {
        $pdo->exec('ALTER TABLE clinic_clearance_requests MODIFY COLUMN status ENUM(\'pending\', \'approved\', \'rejected\', \'requires_more_info\', \'cancelled\') NOT NULL DEFAULT \'pending\'');
    } catch (Exception $e) {
        // ENUM might already be updated, ignore error
    }

    // Clinic Announcements
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS clinic_announcements (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            category VARCHAR(100) DEFAULT NULL,
            description TEXT NOT NULL,
            target_audience ENUM(\'all\', \'students\', \'teachers\', \'staff\') NOT NULL DEFAULT \'all\',
            priority ENUM(\'low\', \'medium\', \'high\', \'emergency\') NOT NULL DEFAULT \'medium\',
            duration_days INT DEFAULT 7,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME DEFAULT NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Clinic Feedback
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS clinic_feedback (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            visit_id INT UNSIGNED DEFAULT NULL,
            rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            comments TEXT DEFAULT NULL,
            service_quality INT DEFAULT NULL CHECK (service_quality >= 1 AND service_quality <= 5),
            staff_attitude INT DEFAULT NULL CHECK (staff_attitude >= 1 AND staff_attitude <= 5),
            waiting_time INT DEFAULT NULL CHECK (waiting_time >= 1 AND waiting_time <= 5),
            facility_cleanliness INT DEFAULT NULL CHECK (facility_cleanliness >= 1 AND facility_cleanliness <= 5),
            is_approved TINYINT(1) NOT NULL DEFAULT 0,
            moderated_by INT UNSIGNED DEFAULT NULL,
            moderated_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (visit_id) REFERENCES clinic_visits(id) ON DELETE SET NULL,
            FOREIGN KEY (moderated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // SSC Feedback
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ssc_feedback (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            event_id INT UNSIGNED DEFAULT NULL,
            rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            comments TEXT DEFAULT NULL,
            service_quality INT DEFAULT NULL CHECK (service_quality >= 1 AND service_quality <= 5),
            event_organization INT DEFAULT NULL CHECK (event_organization >= 1 AND event_organization <= 5),
            overall_satisfaction INT DEFAULT NULL CHECK (overall_satisfaction >= 1 AND overall_satisfaction <= 5),
            is_approved TINYINT(1) NOT NULL DEFAULT 0,
            moderated_by INT UNSIGNED DEFAULT NULL,
            moderated_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (event_id) REFERENCES ssc_events(id) ON DELETE SET NULL,
            FOREIGN KEY (moderated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // SSAA Feedback
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ssaa_feedback (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            comments TEXT DEFAULT NULL,
            service_quality INT DEFAULT NULL CHECK (service_quality >= 1 AND service_quality <= 5),
            staff_courtesy INT DEFAULT NULL CHECK (staff_courtesy >= 1 AND staff_courtesy <= 5),
            process_efficiency INT DEFAULT NULL CHECK (process_efficiency >= 1 AND process_efficiency <= 5),
            is_approved TINYINT(1) NOT NULL DEFAULT 0,
            moderated_by INT UNSIGNED DEFAULT NULL,
            moderated_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (moderated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Insert default clinic status if not exists
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM clinic_status');
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO clinic_status (is_available) VALUES (1)");
    }
}

// Clinic Status Functions
function get_clinic_status(): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM clinic_status ORDER BY updated_at DESC LIMIT 1');
    $stmt->execute();
    return $stmt->fetch() ?: ['is_available' => 1, 'reason' => null, 'duration_hours' => null, 'nurse_back_date' => null, 'nurse_back_time' => null];
}

function update_clinic_status(bool $isAvailable, ?string $reason = null, ?int $durationHours = null, ?int $updatedBy = null, ?string $nurseBackDate = null, ?string $nurseBackTime = null): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT INTO clinic_status (is_available, reason, duration_hours, nurse_back_date, nurse_back_time, updated_by) VALUES (:available, :reason, :duration, :back_date, :back_time, :updated_by)');
    return $stmt->execute([
        'available' => $isAvailable ? 1 : 0,
        'reason' => $reason,
        'duration' => $durationHours,
        'back_date' => $nurseBackDate ?: null,
        'back_time' => $nurseBackTime ?: null,
        'updated_by' => $updatedBy
    ]);
}

// Health Records Functions
function get_user_health_record(int $userId): ?array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM clinic_health_records WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);
    $result = $stmt->fetch();
    return $result !== false ? $result : null;
}

function save_health_record(int $userId, array $data): bool {
    ensure_clinic_schema();
    $pdo = get_db();

    $stmt = $pdo->prepare('INSERT INTO clinic_health_records
        (user_id, medical_history, allergies, current_medications, blood_type,
         emergency_contact_name, emergency_contact_phone, emergency_contact_relationship)
        VALUES (:user_id, :medical_history, :allergies, :current_medications, :blood_type,
                :emergency_contact_name, :emergency_contact_phone, :emergency_contact_relationship)
        ON DUPLICATE KEY UPDATE
        medical_history = VALUES(medical_history),
        allergies = VALUES(allergies),
        current_medications = VALUES(current_medications),
        blood_type = VALUES(blood_type),
        emergency_contact_name = VALUES(emergency_contact_name),
        emergency_contact_phone = VALUES(emergency_contact_phone),
        emergency_contact_relationship = VALUES(emergency_contact_relationship)');

    return $stmt->execute([
        'user_id' => $userId,
        'medical_history' => $data['medical_history'] ?? null,
        'allergies' => $data['allergies'] ?? null,
        'current_medications' => $data['current_medications'] ?? null,
        'blood_type' => $data['blood_type'] ?? null,
        'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
        'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
        'emergency_contact_relationship' => $data['emergency_contact_relationship'] ?? null
    ]);
}

// Daily Health Data Functions
function get_today_health_data(int $userId): ?array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM clinic_health_data WHERE user_id = :user_id AND record_date = CURDATE()');
    $stmt->execute(['user_id' => $userId]);
    $result = $stmt->fetch();
    return $result !== false ? $result : null;
}

function save_daily_health_data(int $userId, array $data): bool {
    ensure_clinic_schema();
    $pdo = get_db();

    // Calculate BMI if height and weight are provided
    $bmi = null;
    if (isset($data['height_cm']) && isset($data['weight_kg']) && $data['height_cm'] > 0) {
        $heightM = $data['height_cm'] / 100;
        $bmi = round($data['weight_kg'] / ($heightM * $heightM), 1);
    }

    // Determine health status based on inputs
    $healthStatus = 'Good';
    $recommendations = [];

    if (isset($data['sleep_hours']) && $data['sleep_hours'] < 7) {
        $healthStatus = 'Needs Rest';
        $recommendations[] = 'Get at least 7-8 hours of sleep per night';
    }

    if (isset($data['water_intake_liters']) && $data['water_intake_liters'] < 2) {
        $recommendations[] = 'Drink at least 2 liters of water daily';
    }

    if ($bmi !== null) {
        if ($bmi < 18.5) {
            $healthStatus = 'Underweight';
            $recommendations[] = 'Consider consulting a nutritionist for healthy weight gain';
        } elseif ($bmi >= 25 && $bmi < 30) {
            $healthStatus = 'Overweight';
            $recommendations[] = 'Focus on balanced diet and regular exercise';
        } elseif ($bmi >= 30) {
            $healthStatus = 'Obese';
            $recommendations[] = 'Consult healthcare provider for weight management plan';
        }
    }

    $stmt = $pdo->prepare('INSERT INTO clinic_health_data
        (user_id, record_date, sleep_hours, water_intake_liters, height_cm, weight_kg, mood, bmi, health_status, recommendations)
        VALUES (:user_id, CURDATE(), :sleep_hours, :water_intake, :height, :weight, :mood, :bmi, :health_status, :recommendations)
        ON DUPLICATE KEY UPDATE
        sleep_hours = VALUES(sleep_hours),
        water_intake_liters = VALUES(water_intake_liters),
        height_cm = VALUES(height_cm),
        weight_kg = VALUES(weight_kg),
        mood = VALUES(mood),
        bmi = VALUES(bmi),
        health_status = VALUES(health_status),
        recommendations = VALUES(recommendations)');

    return $stmt->execute([
        'user_id' => $userId,
        'sleep_hours' => $data['sleep_hours'] ?? null,
        'water_intake' => $data['water_intake_liters'] ?? null,
        'height' => $data['height_cm'] ?? null,
        'weight' => $data['weight_kg'] ?? null,
        'mood' => $data['mood'] ?? null,
        'bmi' => $bmi,
        'health_status' => $healthStatus,
        'recommendations' => implode('; ', $recommendations)
    ]);
}

// Medicine Functions
function get_clinic_medicines(string $search = '', int $limit = 50): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $query = 'SELECT * FROM clinic_medicines WHERE 1=1';
    $params = [];

    if (!empty($search)) {
        $query .= ' AND (name LIKE :search OR generic_name LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }

    $query .= ' ORDER BY name LIMIT ' . $limit;
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function check_medicine_availability(string $medicineName): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM clinic_medicines WHERE name LIKE :name OR generic_name LIKE :name LIMIT 1');
    $stmt->execute(['name' => '%' . $medicineName . '%']);
    $medicine = $stmt->fetch();

    if (!$medicine) {
        return ['available' => false, 'status' => 'Not Found', 'quantity' => 0];
    }

    $isAvailable = $medicine['stock_quantity'] > 0;
    $status = $isAvailable ? 'In Stock' : 'Out of Stock';

    return [
        'available' => $isAvailable,
        'status' => $status,
        'quantity' => $medicine['stock_quantity'],
        'medicine' => $medicine
    ];
}

function get_low_stock_medicines(): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM clinic_medicines WHERE stock_quantity <= min_stock_level ORDER BY stock_quantity ASC');
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_medicine_categories(): array {
    ensure_clinic_schema();
    $pdo = get_db();

    // Prefer dedicated categories table if present
    $stmt = $pdo->prepare('SELECT id, name FROM clinic_medicine_categories ORDER BY name ASC');
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($categories)) {
        // Fallback to categories used in medicines
        $stmt = $pdo->prepare('SELECT DISTINCT category as name FROM clinic_medicines WHERE category IS NOT NULL AND category != "" ORDER BY category ASC');
        $stmt->execute();
        $fallbackCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Add dummy id for consistency
        $categories = array_map(function($cat) {
            return ['id' => 0, 'name' => $cat['name']];
        }, $fallbackCategories);
    }

    return $categories;
}

function create_medicine_category(string $name): bool {
    ensure_clinic_schema();
    $name = trim($name);
    if ($name === '') {
        return false;
    }

    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT IGNORE INTO clinic_medicine_categories (name) VALUES (:name)');
    return $stmt->execute(['name' => $name]);
}

function update_medicine_category(int $id, string $newName): bool {
    ensure_clinic_schema();
    $newName = trim($newName);
    if ($newName === '') {
        return false;
    }

    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE clinic_medicine_categories SET name = :name WHERE id = :id');
    return $stmt->execute(['name' => $newName, 'id' => $id]);
}

function delete_medicine_category(int $id): bool {
    ensure_clinic_schema();
    $pdo = get_db();

    // Check if category is used by any medicines
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM clinic_medicines WHERE category = (SELECT name FROM clinic_medicine_categories WHERE id = :id)');
    $stmt->execute(['id' => $id]);
    if ($stmt->fetchColumn() > 0) {
        return false; // Cannot delete if in use
    }

    $stmt = $pdo->prepare('DELETE FROM clinic_medicine_categories WHERE id = :id');
    return $stmt->execute(['id' => $id]);
}

function update_clinic_medicine(int $id, array $data): bool {
    ensure_clinic_schema();
    $pdo = get_db();

    $category = trim($data['category'] ?? '');
    if ($category !== '') {
        create_medicine_category($category);
    }

    $stmt = $pdo->prepare('UPDATE clinic_medicines SET
        name = :name,
        generic_name = :generic_name,
        description = :description,
        category = :category,
        stock_quantity = :stock_quantity,
        min_stock_level = :min_stock_level,
        unit = :unit,
        expiry_date = :expiry_date,
        batch_number = :batch_number,
        location = :location
        WHERE id = :id');

    return $stmt->execute([
        'id' => $id,
        'name' => $data['name'],
        'generic_name' => $data['generic_name'] ?? null,
        'description' => $data['description'] ?? null,
        'category' => $category !== '' ? $category : null,
        'stock_quantity' => (int)($data['stock_quantity'] ?? 0),
        'min_stock_level' => (int)($data['min_stock_level'] ?? 5),
        'unit' => $data['unit'] ?? 'tablets',
        'expiry_date' => !empty($data['expiry_date']) ? $data['expiry_date'] : null,
        'batch_number' => $data['batch_number'] ?? null,
        'location' => $data['location'] ?? null
    ]);
}

function delete_clinic_medicine(int $id): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('DELETE FROM clinic_medicines WHERE id = :id');
    return $stmt->execute(['id' => $id]);
}

function delete_expired_clinic_medicines(int $daysAhead = 30): int {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('DELETE FROM clinic_medicines WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)');
    $stmt->execute(['days' => $daysAhead]);
    return $stmt->rowCount();
}

// ==========================================
// MEDICINE BATCH FUNCTIONS
// ==========================================

function get_active_medicine_batches(int $medicineId): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM clinic_medicine_batches WHERE medicine_id = :medicine_id AND is_active = 1 ORDER BY expiry_date ASC, created_at DESC');
    $stmt->execute(['medicine_id' => $medicineId]);
    return $stmt->fetchAll();
}

function create_medicine_batch(int $medicineId, array $data): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    
    $stmt = $pdo->prepare('INSERT INTO clinic_medicine_batches
        (medicine_id, batch_number, quantity, expiry_date, location, is_active)
        VALUES (:medicine_id, :batch_number, :quantity, :expiry_date, :location, 1)');
    
    return $stmt->execute([
        'medicine_id' => $medicineId,
        'batch_number' => $data['batch_number'],
        'quantity' => (int)$data['quantity'],
        'expiry_date' => !empty($data['expiry_date']) ? $data['expiry_date'] : null,
        'location' => $data['location'] ?? null
    ]);
}

function update_medicine_batch(int $batchId, array $data): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    
    $stmt = $pdo->prepare('UPDATE clinic_medicine_batches 
        SET quantity = :quantity, expiry_date = :expiry_date, updated_at = NOW()
        WHERE id = :id');
    
    return $stmt->execute([
        'quantity' => (int)$data['quantity'],
        'expiry_date' => !empty($data['expiry_date']) ? $data['expiry_date'] : null,
        'id' => $batchId
    ]);
}

function deactivate_medicine_batch(int $batchId): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    
    $stmt = $pdo->prepare('UPDATE clinic_medicine_batches SET is_active = 0, updated_at = NOW() WHERE id = :id');
    return $stmt->execute(['id' => $batchId]);
}

function get_medicine_batch_by_id(int $batchId): ?array {
    ensure_clinic_schema();
    $pdo = get_db();
    
    $stmt = $pdo->prepare('SELECT * FROM clinic_medicine_batches WHERE id = :id');
    $stmt->execute(['id' => $batchId]);
    return $stmt->fetch() ?: null;
}

// ==========================================
// MEDICINE BATCH STATUS HELPER FUNCTIONS
// ==========================================

function has_medicine_batches(int $medicineId): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM clinic_medicine_batches WHERE medicine_id = :medicine_id AND is_active = 1');
    $stmt->execute(['medicine_id' => $medicineId]);
    return $stmt->fetchColumn() > 0;
}

function has_expiring_batches(int $medicineId, int $daysAhead = 30): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM clinic_medicine_batches 
        WHERE medicine_id = :medicine_id 
        AND is_active = 1 
        AND expiry_date IS NOT NULL 
        AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
        AND expiry_date > CURDATE()');
    $stmt->execute(['medicine_id' => $medicineId, 'days' => $daysAhead]);
    return $stmt->fetchColumn() > 0;
}

function has_low_stock_batch(int $medicineId): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    
    // First, check if there are at least 2 active batches
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM clinic_medicine_batches WHERE medicine_id = :medicine_id AND is_active = 1');
    $stmt->execute(['medicine_id' => $medicineId]);
    $batchCount = $stmt->fetchColumn();
    
    // Only check for low stock if there are 2 or more batches
    if ($batchCount < 2) {
        return false;
    }
    
    // Get the minimum stock level for the medicine
    $stmt = $pdo->prepare('SELECT min_stock_level FROM clinic_medicines WHERE id = :id');
    $stmt->execute(['id' => $medicineId]);
    $minStockLevel = $stmt->fetchColumn() ?: 5;
    
    // Check if any batch has low quantity (less than or equal to min_stock_level)
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM clinic_medicine_batches 
        WHERE medicine_id = :medicine_id 
        AND is_active = 1 
        AND quantity <= :min_stock');
    $stmt->execute(['medicine_id' => $medicineId, 'min_stock' => $minStockLevel]);
    return $stmt->fetchColumn() > 0;
}

function get_medicines_by_category(string $category): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM clinic_medicines WHERE category = :category ORDER BY name ASC');
    $stmt->execute(['category' => $category]);
    return $stmt->fetchAll();
}

function get_expiring_medicines(int $daysAhead = 30): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM clinic_medicines WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY) ORDER BY expiry_date ASC');
    $stmt->execute(['days' => $daysAhead]);
    return $stmt->fetchAll();
}

function update_medicine_stock(int $medicineId, int $newStock): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE clinic_medicines SET stock_quantity = :stock WHERE id = :id');
    return $stmt->execute(['stock' => $newStock, 'id' => $medicineId]);
}

function create_clinic_medicine(array $data): bool {
    ensure_clinic_schema();
    $pdo = get_db();

    $category = trim($data['category'] ?? '');
    if ($category !== '') {
        create_medicine_category($category);
    }

    $stmt = $pdo->prepare('INSERT INTO clinic_medicines
        (name, generic_name, description, category, stock_quantity, min_stock_level, unit, expiry_date, batch_number, location)
        VALUES (:name, :generic_name, :description, :category, :stock_quantity, :min_stock_level, :unit, :expiry_date, :batch_number, :location)');

    return $stmt->execute([
        'name' => $data['name'],
        'generic_name' => $data['generic_name'] ?? null,
        'description' => $data['description'] ?? null,
        'category' => $category !== '' ? $category : null,
        'stock_quantity' => (int)($data['stock_quantity'] ?? 0),
        'min_stock_level' => (int)($data['min_stock_level'] ?? 5),
        'unit' => $data['unit'] ?? 'tablets',
        'expiry_date' => !empty($data['expiry_date']) ? $data['expiry_date'] : null,
        'batch_number' => $data['batch_number'] ?? null,
        'location' => $data['location'] ?? null
    ]);
}

// Clinic Visit Functions
function save_clinic_visit(array $data): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT INTO clinic_visits
        (user_id, name, visitor_type, student_number, employee_id, course_department, reason_for_visit, checkin_method, time_in, time_out, duration_minutes, status, priority, remarks, completion_status)
        VALUES (:user_id, :name, :visitor_type, :student_number, :employee_id, :course_department, :reason_for_visit, :checkin_method, :time_in, :time_out, :duration_minutes, :status, :priority, :remarks, :completion_status)');
    return $stmt->execute([
        'user_id' => $data['user_id'] ?? null,
        'name' => $data['name'] ?? null,
        'visitor_type' => $data['visitor_type'] ?? 'visitor',
        'student_number' => $data['student_number'] ?? null,
        'employee_id' => $data['employee_id'] ?? null,
        'course_department' => $data['course_department'] ?? null,
        'reason_for_visit' => $data['reason_for_visit'] ?? null,
        'checkin_method' => $data['checkin_method'] ?? 'manual',
        'time_in' => $data['time_in'] ?? date('Y-m-d H:i:s'),
        'time_out' => $data['time_out'] ?? null,
        'duration_minutes' => $data['duration_minutes'] ?? null,
        'status' => $data['status'] ?? 'waiting',
        'priority' => $data['priority'] ?? 'medium',
        'remarks' => $data['remarks'] ?? null,
        'completion_status' => $data['completion_status'] ?? 'pending'
    ]);
}

function get_pending_logout_approvals(): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT cv.*, u.full_name AS patient_name FROM clinic_visits cv LEFT JOIN users u ON cv.user_id = u.id WHERE cv.status = :status ORDER BY cv.time_in ASC');
    $stmt->execute(['status' => 'pending_logout']);
    return $stmt->fetchAll();
}

function get_recent_clinic_visits(int $limit = 50): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT cv.*, u.full_name AS patient_name, u.role, u.course_year FROM clinic_visits cv LEFT JOIN users u ON cv.user_id = u.id ORDER BY cv.time_in DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_clinic_users(): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, full_name, role, course_year FROM users WHERE role IN ("student", "teacher") ORDER BY full_name ASC');
    $stmt->execute();
    return $stmt->fetchAll();
}

function mark_clinic_visit_pending_logout(int $visitId): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE clinic_visits SET status = :status WHERE id = :id');
    return $stmt->execute(['status' => 'pending_logout', 'id' => $visitId]);
}

function approve_clinic_logout(int $visitId): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    $timeOut = date('Y-m-d H:i:s');
    // attempt to calculate duration with database expression if supported.
    $stmt = $pdo->prepare('UPDATE clinic_visits SET status = :status, time_out = :time_out, duration_minutes = TIMESTAMPDIFF(MINUTE, time_in, :time_out) WHERE id = :id');
    return $stmt->execute(['status' => 'completed', 'time_out' => $timeOut, 'id' => $visitId]);
}

function get_current_clinic_visits(): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT cv.*, u.full_name as patient_name FROM clinic_visits cv LEFT JOIN users u ON cv.user_id = u.id WHERE cv.status IN (\'waiting\', \'in_consultation\') ORDER BY cv.priority DESC, cv.time_in ASC');
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_today_clinic_visits(): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM clinic_visits WHERE DATE(time_in) = CURDATE() ORDER BY time_in DESC');
    $stmt->execute();
    return $stmt->fetchAll();
}

function complete_clinic_visit(int $visitId, string $timeOut, int $durationMinutes): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE clinic_visits SET time_out = :time_out, duration_minutes = :duration_minutes, status = \'completed\' WHERE id = :id');
    return $stmt->execute(['time_out' => $timeOut, 'duration_minutes' => $durationMinutes, 'id' => $visitId]);
}

function update_clinic_visit_remarks(int $visitId, string $remarks, string $completionStatus): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE clinic_visits SET remarks = :remarks, completion_status = :completion_status WHERE id = :id');
    return $stmt->execute([
        'remarks' => $remarks,
        'completion_status' => $completionStatus,
        'id' => $visitId
    ]);
}

function delete_clinic_visit(int $visitId): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('DELETE FROM clinic_visits WHERE id = :id');
    return $stmt->execute(['id' => $visitId]);
}

function get_clinic_visit_by_id(int $visitId): ?array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM clinic_visits WHERE id = :id');
    $stmt->execute(['id' => $visitId]);
    return $stmt->fetch() ?: null;
}

// Treatment Functions
function save_treatment_record(int $visitId, array $data, int $treatedBy): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT INTO clinic_treatments
        (visit_id, symptoms, diagnosis, treatment_provided, medicines_given, follow_up_instructions, referred_to_specialist, specialist_referral, treated_by)
        VALUES (:visit_id, :symptoms, :diagnosis, :treatment, :medicines, :follow_up, :referred, :referral, :treated_by)');
    return $stmt->execute([
        'visit_id' => $visitId,
        'symptoms' => $data['symptoms'] ?? null,
        'diagnosis' => $data['diagnosis'] ?? null,
        'treatment' => $data['treatment_provided'] ?? null,
        'medicines' => $data['medicines_given'] ?? null,
        'follow_up' => $data['follow_up_instructions'] ?? null,
        'referred' => isset($data['referred_to_specialist']) ? 1 : 0,
        'referral' => $data['specialist_referral'] ?? null,
        'treated_by' => $treatedBy
    ]);
}

function get_visit_treatment(int $visitId): ?array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT ct.*, u.full_name as treated_by_name FROM clinic_treatments ct LEFT JOIN users u ON ct.treated_by = u.id WHERE ct.visit_id = :visit_id');
    $stmt->execute(['visit_id' => $visitId]);
    $result = $stmt->fetch();
    return $result !== false ? $result : null;
}

// Clearance Request Functions
function submit_clearance_request(int $userId, array $data): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT INTO clinic_clearance_requests
        (user_id, request_type, purpose, documents_uploaded)
        VALUES (:user_id, :request_type, :purpose, :documents)');
    return $stmt->execute([
        'user_id' => $userId,
        'request_type' => $data['request_type'],
        'purpose' => $data['purpose'],
        'documents' => $data['documents_uploaded'] ?? null
    ]);
}

function get_user_clearance_requests(int $userId): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT cr.*, u.full_name FROM clinic_clearance_requests cr JOIN users u ON cr.user_id = u.id WHERE cr.user_id = :user_id ORDER BY cr.created_at DESC');
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll();
}

function get_pending_clearance_requests(): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT cr.*, u.full_name, u.role, u.course_year FROM clinic_clearance_requests cr JOIN users u ON cr.user_id = u.id WHERE cr.status = \'pending\' ORDER BY cr.created_at ASC');
    $stmt->execute();
    return $stmt->fetchAll();
}

function process_clearance_request(int $requestId, string $action, ?string $notes = null, ?int $reviewedBy = null): bool {
    ensure_clinic_schema();
    $pdo = get_db();

    $status = $action === 'approve' ? 'approved' : 'rejected';
    $expiryDate = $action === 'approve' ? date('Y-m-d', strtotime('+1 year')) : null;

    $stmt = $pdo->prepare('UPDATE clinic_clearance_requests SET status = :status, reviewed_by = :reviewed_by, reviewed_at = NOW(), review_notes = :notes, clearance_expiry = :expiry WHERE id = :id');
    return $stmt->execute([
        'status' => $status,
        'reviewed_by' => $reviewedBy,
        'notes' => $notes,
        'expiry' => $expiryDate,
        'id' => $requestId
    ]);
}

function get_clearance_request_by_id(int $requestId): ?array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT cr.*, u.full_name FROM clinic_clearance_requests cr JOIN users u ON cr.user_id = u.id WHERE cr.id = :id');
    $stmt->execute(['id' => $requestId]);
    $result = $stmt->fetch();
    return $result ?: null;
}

function cancel_clearance_request(int $requestId): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE clinic_clearance_requests SET status = \'cancelled\', cancelled_at = NOW() WHERE id = :id AND status = \'pending\'');
    return $stmt->execute(['id' => $requestId]);
}

function get_recent_cancelled_clearance_requests(int $days = 7): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT cr.*, u.full_name FROM clinic_clearance_requests cr JOIN users u ON cr.user_id = u.id WHERE cr.status = \'cancelled\' AND cr.cancelled_at >= DATE_SUB(NOW(), INTERVAL :days DAY) ORDER BY cr.cancelled_at DESC');
    $stmt->execute(['days' => $days]);
    return $stmt->fetchAll();
}

// Announcement Functions
function create_clinic_announcement(array $data, int $createdBy): bool {
    ensure_clinic_schema();
    $pdo = get_db();

    $expiresAt = null;
    if (isset($data['duration_days']) && $data['duration_days'] > 0) {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $data['duration_days'] . ' days'));
    }

    $stmt = $pdo->prepare('INSERT INTO clinic_announcements
        (title, category, description, target_audience, priority, duration_days, created_by, expires_at)
        VALUES (:title, :category, :description, :audience, :priority, :duration, :created_by, :expires_at)');
    return $stmt->execute([
        'title' => $data['title'],
        'category' => $data['category'] ?? null,
        'description' => $data['description'],
        'audience' => $data['target_audience'] ?? 'all',
        'priority' => $data['priority'] ?? 'medium',
        'duration' => $data['duration_days'] ?? 7,
        'created_by' => $createdBy,
        'expires_at' => $expiresAt
    ]);
}

function get_active_clinic_announcements(string $audience = 'all'): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $query = 'SELECT ca.*, u.full_name as created_by_name FROM clinic_announcements ca LEFT JOIN users u ON ca.created_by = u.id WHERE ca.is_active = 1 AND (ca.expires_at IS NULL OR ca.expires_at > NOW())';
    $params = [];

    if ($audience !== 'all') {
        $query .= ' AND (ca.target_audience = :audience OR ca.target_audience = \'all\')';
        $params['audience'] = $audience;
    }

    $query .= ' ORDER BY ca.priority DESC, ca.created_at DESC';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Feedback Functions
function submit_clinic_feedback(int $userId, array $data, ?int $visitId = null): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT INTO clinic_feedback
        (user_id, visit_id, rating, comments, service_quality, staff_attitude, waiting_time, facility_cleanliness)
        VALUES (:user_id, :visit_id, :rating, :comments, :service_quality, :staff_attitude, :waiting_time, :facility_cleanliness)');
    return $stmt->execute([
        'user_id' => $userId,
        'visit_id' => $visitId,
        'rating' => $data['rating'],
        'comments' => $data['comments'] ?? null,
        'service_quality' => $data['service_quality'] ?? null,
        'staff_attitude' => $data['staff_attitude'] ?? null,
        'waiting_time' => $data['waiting_time'] ?? null,
        'facility_cleanliness' => $data['facility_cleanliness'] ?? null
    ]);
}

function get_pending_clinic_feedback(): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT cf.*, u.full_name as patient_name FROM clinic_feedback cf JOIN users u ON cf.user_id = u.id WHERE cf.is_approved = 0 ORDER BY cf.created_at DESC');
    $stmt->execute();
    return $stmt->fetchAll();
}

function approve_clinic_feedback(int $feedbackId, int $moderatedBy): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE clinic_feedback SET is_approved = 1, moderated_by = :moderated_by, moderated_at = NOW() WHERE id = :id');
    return $stmt->execute(['moderated_by' => $moderatedBy, 'id' => $feedbackId]);
}

// SSC Feedback Functions
function submit_ssc_feedback(int $userId, array $data, ?int $eventId = null): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT INTO ssc_feedback
        (user_id, event_id, rating, comments, service_quality, event_organization, overall_satisfaction)
        VALUES (:user_id, :event_id, :rating, :comments, :service_quality, :event_organization, :overall_satisfaction)');
    return $stmt->execute([
        'user_id' => $userId,
        'event_id' => $eventId,
        'rating' => $data['rating'],
        'comments' => $data['comments'] ?? null,
        'service_quality' => $data['service_quality'] ?? null,
        'event_organization' => $data['event_organization'] ?? null,
        'overall_satisfaction' => $data['overall_satisfaction'] ?? null
    ]);
}

function get_pending_ssc_feedback(): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT sf.*, u.full_name as student_name FROM ssc_feedback sf JOIN users u ON sf.user_id = u.id WHERE sf.is_approved = 0 ORDER BY sf.created_at DESC');
    $stmt->execute();
    return $stmt->fetchAll();
}

function approve_ssc_feedback(int $feedbackId, int $moderatedBy): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE ssc_feedback SET is_approved = 1, moderated_by = :moderated_by, moderated_at = NOW() WHERE id = :id');
    return $stmt->execute(['moderated_by' => $moderatedBy, 'id' => $feedbackId]);
}

function get_all_ssc_feedback(): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT sf.*, u.full_name, u.email FROM ssc_feedback sf JOIN users u ON sf.user_id = u.id WHERE sf.is_approved = 1 ORDER BY sf.created_at DESC');
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_ssc_feedback_average_rating(): float {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT AVG(rating) FROM ssc_feedback WHERE is_approved = 1');
    $stmt->execute();
    $avg = $stmt->fetchColumn();
    return round($avg ?? 0, 2);
}

function get_ssc_feedback_count(): int {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ssc_feedback WHERE is_approved = 1');
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

// SSAA Feedback Functions
function submit_ssaa_feedback(int $userId, array $data): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT INTO ssaa_feedback
        (user_id, rating, comments, service_quality, staff_courtesy, process_efficiency)
        VALUES (:user_id, :rating, :comments, :service_quality, :staff_courtesy, :process_efficiency)');
    return $stmt->execute([
        'user_id' => $userId,
        'rating' => $data['rating'],
        'comments' => $data['comments'] ?? null,
        'service_quality' => $data['service_quality'] ?? null,
        'staff_courtesy' => $data['staff_courtesy'] ?? null,
        'process_efficiency' => $data['process_efficiency'] ?? null
    ]);
}

function get_pending_ssaa_feedback(): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT sf.*, u.full_name as student_name FROM ssaa_feedback sf JOIN users u ON sf.user_id = u.id WHERE sf.is_approved = 0 ORDER BY sf.created_at DESC');
    $stmt->execute();
    return $stmt->fetchAll();
}

function approve_ssaa_feedback(int $feedbackId, int $moderatedBy): bool {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE ssaa_feedback SET is_approved = 1, moderated_by = :moderated_by, moderated_at = NOW() WHERE id = :id');
    return $stmt->execute(['moderated_by' => $moderatedBy, 'id' => $feedbackId]);
}

function get_all_ssaa_feedback(): array {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT sf.*, u.full_name, u.email FROM ssaa_feedback sf JOIN users u ON sf.user_id = u.id WHERE sf.is_approved = 1 ORDER BY sf.created_at DESC');
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_ssaa_feedback_average_rating(): float {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT AVG(rating) FROM ssaa_feedback WHERE is_approved = 1');
    $stmt->execute();
    $avg = $stmt->fetchColumn();
    return round($avg ?? 0, 2);
}

function get_ssaa_feedback_count(): int {
    ensure_clinic_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ssaa_feedback WHERE is_approved = 1');
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

// Analytics Functions
function get_clinic_visit_analytics(int $days = 30): array {
    ensure_clinic_schema();
    $pdo = get_db();

    // Daily visit counts
    $stmt = $pdo->prepare('SELECT DATE(time_in) as date, COUNT(*) as visits FROM clinic_visits WHERE time_in >= DATE_SUB(CURDATE(), INTERVAL :days DAY) GROUP BY DATE(time_in) ORDER BY date');
    $stmt->execute(['days' => $days]);
    $dailyVisits = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Common symptoms/diagnoses
    $stmt = $pdo->prepare('SELECT diagnosis, COUNT(*) as count FROM clinic_treatments WHERE diagnosis IS NOT NULL AND treatment_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY) GROUP BY diagnosis ORDER BY count DESC LIMIT 10');
    $stmt->execute(['days' => $days]);
    $commonDiagnoses = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Average wait times
    $stmt = $pdo->prepare('SELECT AVG(duration_minutes) as avg_duration FROM clinic_visits WHERE status = \'completed\' AND time_in >= DATE_SUB(CURDATE(), INTERVAL :days DAY)');
    $stmt->execute(['days' => $days]);
    $avgDuration = $stmt->fetchColumn();

    return [
        'daily_visits' => $dailyVisits,
        'common_diagnoses' => $commonDiagnoses,
        'average_duration' => round($avgDuration, 1),
        'total_visits' => array_sum($dailyVisits)
    ];
}

function get_clinic_analytics(): array {
    ensure_clinic_schema();
    $pdo = get_db();

    // Basic metrics
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM clinic_visits');
    $stmt->execute();
    $totalPatients = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM clinic_visits WHERE time_in >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)');
    $stmt->execute();
    $monthlyVisits = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM clinic_visits WHERE DATE(time_in) = CURDATE()');
    $stmt->execute();
    $todayVisits = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM clinic_visits WHERE time_in >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)');
    $stmt->execute();
    $weekVisits = $stmt->fetchColumn();

    // Average rating
    $stmt = $pdo->prepare('SELECT AVG(rating) FROM clinic_feedback WHERE is_approved = 1');
    $stmt->execute();
    $avgRating = $stmt->fetchColumn() ?: 0;

    // Average wait time
    $stmt = $pdo->prepare('SELECT AVG(duration_minutes) FROM clinic_visits WHERE status = \'completed\' AND duration_minutes IS NOT NULL');
    $stmt->execute();
    $avgWaitTime = $stmt->fetchColumn() ?: 0;

    // Medicine analytics
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM clinic_medicines');
    $stmt->execute();
    $totalMedicines = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM clinic_medicines WHERE stock_quantity <= min_stock_level');
    $stmt->execute();
    $lowStockAlerts = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM clinic_medicines WHERE expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)');
    $stmt->execute();
    $expiredMedicines = $stmt->fetchColumn();

    // Clearance requests
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM clinic_clearance_requests WHERE status = \'pending\'');
    $stmt->execute();
    $pendingClearances = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM clinic_clearance_requests WHERE status = \'approved\' AND reviewed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)');
    $stmt->execute();
    $approvedClearances = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM clinic_clearance_requests WHERE status = \'rejected\' AND reviewed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)');
    $stmt->execute();
    $rejectedClearances = $stmt->fetchColumn();

    // Top conditions by reason for visit within the last 30 days
    $stmt = $pdo->prepare('SELECT reason_for_visit AS condition_name, COUNT(*) AS count FROM clinic_visits WHERE reason_for_visit IS NOT NULL AND TRIM(reason_for_visit) <> "" AND time_in >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY reason_for_visit ORDER BY count DESC LIMIT 10');
    $stmt->execute();
    $topConditionsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalTopConditions = array_sum(array_column($topConditionsRaw, 'count')) ?: 0;
    $topConditions = array_map(function ($conditionRow) use ($totalTopConditions) {
        return [
            'condition' => $conditionRow['condition_name'],
            'count' => (int)$conditionRow['count'],
            'percentage' => $totalTopConditions ? round(($conditionRow['count'] / $totalTopConditions) * 100, 1) : 0,
            'trend' => 0,
        ];
    }, $topConditionsRaw);

    // Top visit needs by selected reason category within the last 30 days
    $stmt = $pdo->prepare("SELECT
        CASE
            WHEN reason_for_visit IN ('Common Cold','Headache','Fever','Stomach Pain','Allergies') THEN reason_for_visit
            ELSE 'Other'
        END AS need,
        COUNT(*) AS count
        FROM clinic_visits
        WHERE reason_for_visit IS NOT NULL AND TRIM(reason_for_visit) <> '' AND time_in >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY need
        ORDER BY count DESC
        LIMIT 10");
    $stmt->execute();
    $topNeedsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalTopNeeds = array_sum(array_column($topNeedsRaw, 'count')) ?: 0;
    $topNeeds = array_map(function ($needRow) use ($totalTopNeeds) {
        return [
            'need' => $needRow['need'],
            'count' => (int)$needRow['count'],
            'percentage' => $totalTopNeeds ? round(($needRow['count'] / $totalTopNeeds) * 100, 1) : 0,
            'trend' => 0,
        ];
    }, $topNeedsRaw);

    // Monthly treatments recorded
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM clinic_treatments WHERE treatment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)');
    $stmt->execute();
    $monthlyTreatments = (int)$stmt->fetchColumn();

    // Average service time from completed visits
    $stmt = $pdo->prepare('SELECT AVG(duration_minutes) FROM clinic_visits WHERE status = "completed" AND duration_minutes IS NOT NULL');
    $stmt->execute();
    $avgServiceTime = $stmt->fetchColumn() ?: 0;

    // Calculate growth rates (simplified)
    $patientGrowth = 12.5; // Would calculate from historical data
    $visitGrowth = 8.3; // Would calculate from historical data

    // Peak hour based on the last 30 days
    $stmt = $pdo->prepare('SELECT HOUR(time_in) AS hour, COUNT(*) AS visits FROM clinic_visits WHERE time_in >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY hour ORDER BY visits DESC LIMIT 1');
    $stmt->execute();
    $peakHourRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $peakHour = $peakHourRow ? $peakHourRow['hour'] : 0;

    // Average daily visits
    $avgDailyVisits = round($monthlyVisits / 30, 1);

    return [
        'total_patients' => $totalPatients,
        'monthly_visits' => $monthlyVisits,
        'today_visits' => $todayVisits,
        'week_visits' => $weekVisits,
        'avg_rating' => round($avgRating, 1),
        'total_medicines' => $totalMedicines,
        'low_stock_alerts' => $lowStockAlerts,
        'expired_medicines' => $expiredMedicines,
        'pending_clearances' => $pendingClearances,
        'approved_clearances' => $approvedClearances,
        'rejected_clearances' => $rejectedClearances,
        'top_conditions' => $topConditions,
        'top_needs' => $topNeeds,
        'patient_growth' => $patientGrowth,
        'visit_growth' => $visitGrowth,
        'peak_hour' => $peakHour,
        'avg_daily_visits' => $avgDailyVisits,
        'monthly_treatments' => $monthlyTreatments,
        'avg_service_time' => round($avgServiceTime, 1)
    ];
}

// Return distinct reasons recorded in clinic_visits
function get_distinct_visit_reasons(): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT DISTINCT TRIM(reason_for_visit) AS reason FROM clinic_visits WHERE reason_for_visit IS NOT NULL AND TRIM(reason_for_visit) <> "" ORDER BY reason ASC');
    $stmt->execute();
    return array_filter(array_map('trim', $stmt->fetchAll(PDO::FETCH_COLUMN)));
}

// Return top conditions within last N days (0 = overall)
function get_top_conditions(int $days = 30, int $limit = 10): array {
    $pdo = get_db();
    if ($days > 0) {
        $stmt = $pdo->prepare('SELECT reason_for_visit AS condition_name, COUNT(*) AS count FROM clinic_visits WHERE reason_for_visit IS NOT NULL AND TRIM(reason_for_visit) <> "" AND time_in >= DATE_SUB(CURDATE(), INTERVAL :days DAY) GROUP BY reason_for_visit ORDER BY count DESC LIMIT :limit');
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare('SELECT reason_for_visit AS condition_name, COUNT(*) AS count FROM clinic_visits WHERE reason_for_visit IS NOT NULL AND TRIM(reason_for_visit) <> "" GROUP BY reason_for_visit ORDER BY count DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $total = array_sum(array_column($rows, 'count')) ?: 0;
    return array_map(function ($r) use ($total) {
        return ['condition' => $r['condition_name'], 'count' => (int)$r['count'], 'percentage' => $total ? round(($r['count'] / $total) * 100, 1) : 0, 'trend' => 0];
    }, $rows);
}

// Return top needs (similar to top_conditions) within last N days
function get_top_needs(int $days = 30, int $limit = 10): array {
    $pdo = get_db();
    if ($days > 0) {
        $stmt = $pdo->prepare("SELECT
            CASE
                WHEN reason_for_visit IN ('Common Cold','Headache','Fever','Stomach Pain','Allergies') THEN reason_for_visit
                ELSE 'Other'
            END AS need,
            COUNT(*) AS count
            FROM clinic_visits
            WHERE reason_for_visit IS NOT NULL AND TRIM(reason_for_visit) <> '' AND time_in >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY need
            ORDER BY count DESC
            LIMIT :limit");
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT
            CASE
                WHEN reason_for_visit IN ('Common Cold','Headache','Fever','Stomach Pain','Allergies') THEN reason_for_visit
                ELSE 'Other'
            END AS need,
            COUNT(*) AS count
            FROM clinic_visits
            WHERE reason_for_visit IS NOT NULL AND TRIM(reason_for_visit) <> ''
            GROUP BY need
            ORDER BY count DESC
            LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $total = array_sum(array_column($rows, 'count')) ?: 0;
    return array_map(function ($r) use ($total) {
        return ['need' => $r['need'], 'count' => (int)$r['count'], 'percentage' => $total ? round(($r['count'] / $total) * 100, 1) : 0, 'trend' => 0];
    }, $rows);
}

// Return distinct course/department values from visits and users
function get_distinct_course_departments(): array {
    $pdo = get_db();
    // Get distinct course_department values from visits
    $stmt = $pdo->prepare('SELECT DISTINCT TRIM(course_department) AS course FROM clinic_visits WHERE course_department IS NOT NULL AND TRIM(course_department) <> ""');
    $stmt->execute();
    $visitsCourses = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Also include course_year values from users table as fallback
    $stmt2 = $pdo->prepare('SELECT DISTINCT TRIM(course_year) AS course FROM users WHERE course_year IS NOT NULL AND TRIM(course_year) <> ""');
    $stmt2->execute();
    $userCourses = $stmt2->fetchAll(PDO::FETCH_COLUMN);

    $combined = array_unique(array_filter(array_map('trim', array_merge($visitsCourses ?: [], $userCourses ?: []))));
    sort($combined, SORT_STRING | SORT_FLAG_CASE);
    return array_values($combined);
}

// Get breakdown of visits for a given reason across courses within a timeframe
// $timeframe: 'week'|'month'|'overall'
function get_condition_course_breakdown(string $timeframe = 'month', ?string $reason = null, ?string $filterCourse = null, ?string $filterType = null): array {
    $pdo = get_db();
    $conds = [];

    $where = [];
    $params = [];

    if (!empty($reason)) {
        $where[] = 'LOWER(TRIM(reason_for_visit)) = LOWER(:reason)';
        $params['reason'] = trim($reason);
    }

    if (!empty($filterType)) {
        $where[] = 'LOWER(TRIM(cv.visitor_type)) = LOWER(:type)';
        $params['type'] = trim($filterType);
    }

    if ($timeframe === 'week') {
        $where[] = 'time_in >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
    } elseif ($timeframe === 'month') {
        $where[] = 'time_in >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
    }

    if (!empty($filterCourse)) {
        $where[] = 'COALESCE(NULLIF(TRIM(cv.course_department), ""), TRIM(u.course_year)) COLLATE utf8mb4_unicode_ci = :course COLLATE utf8mb4_unicode_ci';
        $params['course'] = $filterCourse;
    }

    $whereSql = '';
    if (!empty($where)) {
        $whereSql = 'WHERE ' . implode(' AND ', $where);
    }

    // First get counts per course
    $sql = "SELECT COALESCE(NULLIF(TRIM(cv.course_department), ''), TRIM(u.course_year)) AS course, COUNT(*) AS cnt FROM clinic_visits cv LEFT JOIN users u ON cv.user_id = u.id $whereSql GROUP BY course ORDER BY cnt DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $courseName = $r['course'] ?: 'Unspecified';
        $conds[$courseName] = ['count' => (int)$r['cnt'], 'names' => []];
    }

    if (empty($rows)) {
        return [];
    }

    // Then get names per course
    $sqlNames = "SELECT COALESCE(NULLIF(TRIM(cv.course_department), ''), TRIM(u.course_year)) AS course, cv.name FROM clinic_visits cv LEFT JOIN users u ON cv.user_id = u.id $whereSql ORDER BY cv.name ASC";
    $stmt2 = $pdo->prepare($sqlNames);
    $stmt2->execute($params);
    $nameRows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    foreach ($nameRows as $nr) {
        $courseName = $nr['course'] ?: 'Unspecified';
        if (!isset($conds[$courseName])) {
            $conds[$courseName] = ['count' => 0, 'names' => []];
        }
        $conds[$courseName]['names'][] = $nr['name'];
    }

    return $conds;
}

function get_condition_visitors(string $timeframe = '', string $reason = '', ?string $course = null, ?string $type = null, ?string $customStartDate = null, ?string $customEndDate = null): array {
    $pdo = get_db();
    $where = [];
    $params = [];

    if (!empty($timeframe)) {
        if ($timeframe === 'today') {
            $where[] = 'DATE(cv.time_in) = CURDATE()';
        } elseif ($timeframe === 'week') {
            $where[] = 'cv.time_in >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
        } elseif ($timeframe === 'month') {
            $where[] = 'cv.time_in >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        } elseif ($timeframe === 'custom_date') {
            if (!empty($customStartDate) && !empty($customEndDate)) {
                $where[] = 'DATE(cv.time_in) BETWEEN ? AND ?';
                $params[] = trim($customStartDate);
                $params[] = trim($customEndDate);
            }
        }
    }

    if (!empty($reason)) {
        $where[] = 'LOWER(TRIM(cv.reason_for_visit)) = LOWER(?)';
        $params[] = trim($reason);
    }

    if (!empty($type)) {
        $where[] = 'LOWER(TRIM(cv.visitor_type)) = LOWER(?)';
        $params[] = trim($type);
    }

    if (!empty($course)) {
        $where[] = '(TRIM(cv.course_department) = ? OR TRIM(u.course_year) = ?)';
        $params[] = trim($course);
        $params[] = trim($course);
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "
        SELECT 
            cv.id, 
            cv.name, 
            COALESCE(NULLIF(TRIM(cv.visitor_type), ''), 'student') as visitor_type,
            COALESCE(NULLIF(TRIM(cv.course_department), ''), TRIM(u.course_year)) as course_department,
            cv.reason_for_visit,
            cv.time_in
        FROM clinic_visits cv
        LEFT JOIN users u ON cv.user_id = u.id
        $whereClause
        ORDER BY cv.time_in DESC
        LIMIT 100
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log('Error in get_condition_visitors: ' . $e->getMessage());
        return [];
    }
}