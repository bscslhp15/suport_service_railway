<?php
require_once __DIR__ . '/db.php';

date_default_timezone_set('Asia/Manila');

function get_email_config(): array {
    return require __DIR__ . '/email_config.php';
}

function add_column_if_missing(PDO $pdo, string $tableName, string $columnName, string $definition): void {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
    );
    $stmt->execute(['table' => $tableName, 'column' => $columnName]);
    if ((int)$stmt->fetchColumn() > 0) {
        return;
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName) || !preg_match('/^[A-Za-z0-9_]+$/', $columnName)) {
        throw new InvalidArgumentException('Invalid table or column name.');
    }

    $pdo->exec("ALTER TABLE `{$tableName}` ADD COLUMN `{$columnName}` {$definition}");
}

function secure_input(string $value): string {
    return trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
}

function get_carousel_images(string $loginType): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT image_path FROM carousel_images WHERE login_type = ? ORDER BY display_order ASC');
    $stmt->execute([$loginType]);
    $paths = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return array_map(static function ($path) {
        $path = trim((string)$path);
        $path = preg_replace('#^\/+|^\.\/+?#', '', $path);
        return '../' . $path;
    }, $paths);
}

function normalize_course_label(string $course): string {
    $course = trim($course);
    if (preg_match('/^(.*?)(?:\s*[-–—]\s*|\s+)(1|2|3|4|graduate)$/i', $course, $matches)) {
        return trim($matches[1]);
    }
    return $course;
}

function normalize_course_list(array $courses): array {
    $normalized = [];
    foreach ($courses as $course) {
        $label = normalize_course_label((string)$course);
        if ($label === '') {
            continue;
        }
        if (!in_array($label, $normalized, true)) {
            $normalized[] = $label;
        }
    }
    sort($normalized, SORT_NATURAL | SORT_FLAG_CASE);
    return $normalized;
}

function build_user_dashboard_header_info(array $user): array {
    $displayCourse = normalize_course_label($user['course'] ?? $user['course_year'] ?? 'Course');
    $displayYearLabel = $user['year_level'] ?? null;
    $courseYear = trim((string)($user['course_year'] ?? ''));
    $studentStatus = 'Active Student';

    if ($courseYear !== '') {
        if (preg_match('/^(.*?)(?:\s*[-–—]\s*|\s+)(1|2|3|4|graduate)$/i', $courseYear, $matches)) {
            $displayCourse = trim($matches[1]) ?: $displayCourse;
            $lastPart = strtolower($matches[2]);
            $ordinals = ['1' => '1st Year', '2' => '2nd Year', '3' => '3rd Year', '4' => '4th Year'];

            if (isset($ordinals[$lastPart])) {
                if (empty($displayYearLabel)) {
                    $displayYearLabel = $ordinals[$lastPart];
                }
            } elseif ($lastPart === 'graduate') {
                if (empty($displayYearLabel)) {
                    $displayYearLabel = 'Graduate';
                }
            }
        }
    }

    // Strip a year suffix from any raw course label regardless of source
    $displayCourse = normalize_course_label($displayCourse);

    if ($user['role'] === 'student') {
        $pdo = get_db();
        $stmt = $pdo->prepare('SELECT enrollment_status FROM ssaa_student_progression WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 1');
        $stmt->execute([$user['id']]);
        $progress = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($progress) {
            if ($progress['enrollment_status'] === 'graduating') {
                $studentStatus = 'Graduating';
            } elseif ($progress['enrollment_status'] === 'graduated') {
                $studentStatus = 'Graduated';
            }
        }
    }

    if ($studentStatus === 'Graduated') {
        $displayYearLabel = null;
        if (!str_ends_with(strtolower($displayCourse), '(alumni)')) {
            $displayCourse .= ' (alumni)';
        }
    }

    $displayMeta = $displayCourse;
    if (!empty($displayYearLabel)) {
        $displayMeta .= ' · ' . $displayYearLabel;
    } elseif ($user['role'] === 'student' && $studentStatus !== 'Active Student' && $studentStatus !== 'Graduated') {
        $displayMeta .= ' · ' . $studentStatus;
    }

    return [
        'displayCourse' => $displayCourse,
        'displayYearLabel' => $displayYearLabel,
        'displayStatus' => $studentStatus,
        'displayMeta' => $displayMeta,
    ];
}

function format_course_for_display(string $courseYear = '', ?int $userId = null): string {
    if (empty($courseYear)) {
        return '';
    }
    return trim($courseYear);
}

function generate_code(int $length = 6): string {
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= random_int(0, 9);
    }
    return $code;
}

function ensure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function set_pending_registration(array $data): void {
    ensure_session();
    $_SESSION['pending_registration'] = $data;
}

function get_pending_registration(string $email = ''): ?array {
    ensure_session();
    if (empty($_SESSION['pending_registration'])) {
        return null;
    }
    $pending = $_SESSION['pending_registration'];
    if ($email && isset($pending['email']) && strtolower($pending['email']) !== strtolower($email)) {
        return null;
    }
    return $pending;
}

function is_pending_email_verification_active(?array $pending): bool {
    if (!$pending || ($pending['status'] ?? 'pending_email') !== 'pending_email') {
        return false;
    }

    $expires = $pending['verification_expires'] ?? null;
    return !empty($expires) && new DateTime() <= new DateTime($expires);
}

function is_pending_registration_blocking(?array $pending): bool {
    if (!$pending || ($pending['status'] ?? 'pending_email') === 'rejected') {
        return false;
    }

    if (($pending['status'] ?? 'pending_email') === 'pending_email') {
        return is_pending_email_verification_active($pending);
    }

    return true;
}

function clear_pending_registration(): void {
    ensure_session();
    unset($_SESSION['pending_registration']);
}

function ensure_pending_registration_schema(): void {
    $pdo = get_db();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS pending_student_registrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL,
            role VARCHAR(50) DEFAULT \'student\',
            student_id VARCHAR(50),
            employee_id VARCHAR(50),
            course_year VARCHAR(150) DEFAULT NULL,
            password_hash VARCHAR(255) NOT NULL,
            documents LONGTEXT DEFAULT NULL,
            email_verified TINYINT(1) NOT NULL DEFAULT 0,
            verification_code VARCHAR(20) DEFAULT NULL,
            verification_expires DATETIME DEFAULT NULL,
            is_head TINYINT(1) NOT NULL DEFAULT 0,
            head_service VARCHAR(100) DEFAULT \'none\',
            admin_type VARCHAR(50) DEFAULT NULL,
            status ENUM(\'pending_email\',\'pending_admin\',\'approved\',\'rejected\') NOT NULL DEFAULT \'pending_email\',
            approved_by INT UNSIGNED DEFAULT NULL,
            approved_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    try {
        $pdo->exec("ALTER TABLE pending_student_registrations MODIFY COLUMN student_id VARCHAR(50) NULL");
        add_column_if_missing($pdo, 'pending_student_registrations', 'role', "VARCHAR(50) DEFAULT 'student'");
        add_column_if_missing($pdo, 'pending_student_registrations', 'employee_id', 'VARCHAR(50)');
        add_column_if_missing($pdo, 'pending_student_registrations', 'is_head', 'TINYINT(1) NOT NULL DEFAULT 0');
        add_column_if_missing($pdo, 'pending_student_registrations', 'head_service', "VARCHAR(100) DEFAULT 'none'");
        add_column_if_missing($pdo, 'pending_student_registrations', 'admin_type', 'VARCHAR(50)');
    } catch (Exception $e) {
        // Ignore if the MySQL version does not support IF NOT EXISTS or these columns already exist.
    }
}

function get_db_pending_registration_by_email(string $email): ?array {
    ensure_pending_registration_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM pending_student_registrations WHERE email = :email ORDER BY created_at DESC LIMIT 1');
    $stmt->execute(['email' => $email]);
    return $stmt->fetch() ?: null;
}

function get_db_pending_registration_by_id(int $id): ?array {
    ensure_pending_registration_schema();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM pending_student_registrations WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function save_db_pending_registration(array $data): bool {
    ensure_pending_registration_schema();
    $pdo = get_db();
    $documents = $data['documents'] ?? null;
    if (is_array($documents)) {
        $documents = json_encode($documents);
    }
    $stmt = $pdo->prepare(
        'INSERT INTO pending_student_registrations (full_name, email, role, student_id, employee_id, course_year, password_hash, documents, email_verified, verification_code, verification_expires, is_head, head_service, admin_type, status, approved_by, approved_at) VALUES (:full_name, :email, :role, :student_id, :employee_id, :course_year, :password_hash, :documents, :email_verified, :verification_code, :verification_expires, :is_head, :head_service, :admin_type, :status, :approved_by, :approved_at)'
    );
    return $stmt->execute([
        'full_name' => $data['full_name'],
        'email' => $data['email'],
        'role' => $data['role'] ?? 'student',
        'student_id' => $data['student_id'] ?? null,
        'employee_id' => $data['employee_id'] ?? null,
        'course_year' => $data['course_year'] ?? null,
        'password_hash' => $data['password_hash'],
        'documents' => $documents,
        'email_verified' => $data['email_verified'] ?? 0,
        'verification_code' => $data['verification_code'] ?? null,
        'verification_expires' => $data['verification_expires'] ?? null,
        'is_head' => $data['is_head'] ?? 0,
        'head_service' => $data['head_service'] ?? 'none',
        'admin_type' => $data['admin_type'] ?? null,
        'status' => $data['status'] ?? 'pending_email',
        'approved_by' => $data['approved_by'] ?? null,
        'approved_at' => $data['approved_at'] ?? null,
    ]);
}

function update_db_pending_registration(int $id, array $data): bool {
    ensure_pending_registration_schema();
    if (empty($data)) {
        return false;
    }
    $fields = [];
    $params = ['id' => $id];
    foreach ($data as $key => $value) {
        if ($key === 'id') {
            continue;
        }
        if ($key === 'documents' && is_array($value)) {
            $value = json_encode($value);
        }
        $fields[] = "$key = :$key";
        $params[$key] = $value;
    }
    $sql = 'UPDATE pending_student_registrations SET ' . implode(', ', $fields) . ' WHERE id = :id';
    $pdo = get_db();
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

function approve_db_pending_registration(int $id, int $approvedBy): bool {
    return update_db_pending_registration($id, [
        'status' => 'approved',
        'approved_by' => $approvedBy,
        'approved_at' => (new DateTime())->format('Y-m-d H:i:s'),
    ]);
}

function reject_db_pending_registration(int $id): bool {
    return update_db_pending_registration($id, [
        'status' => 'rejected',
    ]);
}

function send_email(string $to, string $subject, string $body): bool {
    $config = get_email_config();
    if (!empty($config['smtp_enabled'])) {
        return send_smtp_email($to, $subject, $body, $config);
    }

    $headers = "From: {$config['smtp_from_name']} <{$config['smtp_from']}>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    return mail($to, $subject, $body, $headers);
}

function send_smtp_email(string $to, string $subject, string $body, array $config): bool {
    $host = $config['smtp_host'];
    $port = $config['smtp_port'];
    $username = $config['smtp_username'];
    $password = $config['smtp_password'];
    $encryption = strtolower($config['smtp_encryption'] ?? '');
    $from = $config['smtp_from'];
    $fromName = $config['smtp_from_name'];

    $remoteSocket = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $socket = fsockopen($remoteSocket, $port, $errno, $errstr, 15);
    if (!$socket) {
        return false;
    }
    stream_set_timeout($socket, 15);

    $response = smtp_get_response($socket);
    if (smtp_code($response) !== 220) {
        fclose($socket);
        return false;
    }

    $ehloHost = $_SERVER['SERVER_NAME'] ?? 'localhost';
    if ($encryption === 'tls') {
        if (!smtp_expect($socket, "EHLO {$ehloHost}", [250])) {
            fclose($socket);
            return false;
        }
        if (!smtp_expect($socket, 'STARTTLS', [220])) {
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }
    }

    if (!smtp_expect($socket, "EHLO {$ehloHost}", [250])) {
        fclose($socket);
        return false;
    }
    if (!smtp_expect($socket, 'AUTH LOGIN', [334])) {
        fclose($socket);
        return false;
    }
    if (!smtp_expect($socket, base64_encode($username), [334])) {
        fclose($socket);
        return false;
    }
    if (!smtp_expect($socket, base64_encode($password), [235])) {
        fclose($socket);
        return false;
    }

    if (!smtp_expect($socket, "MAIL FROM:<{$from}>", [250])) {
        fclose($socket);
        return false;
    }
    if (!smtp_expect($socket, "RCPT TO:<{$to}>", [250, 251])) {
        fclose($socket);
        return false;
    }
    if (!smtp_expect($socket, 'DATA', [354])) {
        fclose($socket);
        return false;
    }

    $message = "From: {$fromName} <{$from}>\r\n";
    $message .= "To: {$to}\r\n";
    $message .= "Subject: {$subject}\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "\r\n";
    $message .= $body;
    $message .= "\r\n.\r\n";

    fwrite($socket, $message);
    $response = smtp_get_response($socket);
    if (smtp_code($response) !== 250) {
        fclose($socket);
        return false;
    }
    smtp_command($socket, 'QUIT');
    fclose($socket);
    return true;
}

function smtp_expect($socket, string $command, array $expectedCodes): bool {
    $response = smtp_command($socket, $command);
    return in_array(smtp_code($response), $expectedCodes, true);
}

function smtp_command($socket, string $command): string {
    fwrite($socket, $command . "\r\n");
    return smtp_get_response($socket);
}

function smtp_code(string $response): int {
    return intval(substr($response, 0, 3));
}

function smtp_get_response($socket): string {
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function find_user_by_email(string $email) {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    return $stmt->fetch();
}

function find_user_by_id(int $id) {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch();
}

function check_duplicate_full_name(string $full_name) {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE full_name = :full_name LIMIT 1');
    $stmt->execute(['full_name' => $full_name]);
    return $stmt->fetch();
}

function save_user(array $data): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'INSERT INTO users (full_name,email,username,role,course_year,student_id,employee_id,is_head,head_service,email_verified,verification_code,verification_expires,password_hash,admin_type) VALUES (:full_name,:email,:username,:role,:course_year,:student_id,:employee_id,:is_head,:head_service,:email_verified,:verification_code,:verification_expires,:password_hash,:admin_type)'
    );
    return $stmt->execute($data);
}

function update_user(array $data): bool {
    $pdo = get_db();
    $fields = [];
    $params = [];
    foreach ($data as $key => $value) {
        if ($key === 'id') continue;
        $fields[] = "$key = :$key";
        $params[$key] = $value;
    }
    $params['id'] = $data['id'];
    $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

function ensure_user_archive_schema(): void {
    $pdo = get_db();
    $columns = [
        'is_archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'archived_at' => 'DATETIME DEFAULT NULL',
        'archived_by' => 'INT UNSIGNED DEFAULT NULL',
    ];

    foreach ($columns as $column => $definition) {
        $columnCheck = $pdo->query(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS " .
            "WHERE TABLE_SCHEMA = DATABASE() " .
            "  AND TABLE_NAME = 'users' " .
            "  AND COLUMN_NAME = '$column'"
        )->fetchColumn();

        if (!$columnCheck) {
            $pdo->exec("ALTER TABLE users ADD COLUMN `$column` $definition");
        }
    }
}

function normalize_user_course_value(?string $course): string {
    $course = trim((string) ($course ?? ''));
    if ($course === '') {
        return '';
    }

    $course = preg_replace('/\s+(?:1st|2nd|3rd|4th|graduate|\d{1,2})\s*$/i', '', $course);
    $course = preg_replace('/\s*(?:\/|-|–|—)\s*(?:1st|2nd|3rd|4th|graduate|\d{1,2})\s*$/i', '', $course);
    $course = preg_replace('/\s+\d+\s*$/', '', $course);
    $course = preg_replace('/\s+/', ' ', $course);

    return mb_strtolower($course, 'UTF-8');
}

function parse_user_course_and_year(?string $courseYear): array {
    $value = trim((string) ($courseYear ?? ''));
    if ($value === '') {
        return ['', ''];
    }

    if (preg_match('/\s+(1st|2nd|3rd|4th|\d{1,2})\s*$/i', $value, $matches)) {
        $year = strtolower($matches[1]);
        if (preg_match('/^\d+$/', $year)) {
            return [trim(preg_replace('/\s+' . preg_quote($matches[1], '/') . '$/i', '', $value)), $year];
        }

        $yearLabel = ['1st' => '1', '2nd' => '2', '3rd' => '3', '4th' => '4'];
        return [trim(preg_replace('/\s+' . preg_quote($matches[1], '/') . '$/i', '', $value)), $yearLabel[$year] ?? $year];
    }

    return [$value, ''];
}

function is_locked_out(array $user): bool {
    if (empty($user['lockout_until'])) {
        return false;
    }
    $lockoutUntil = new DateTime($user['lockout_until']);
    $now = new DateTime();
    return $now < $lockoutUntil;
}

function record_failed_login(array $user): void {
    $failedAttempts = (int)$user['failed_attempts'] + 1;
    $lockoutUntil = null;
    if ($failedAttempts >= 5) {
        $lockoutUntil = (new DateTime())->modify('+15 minutes')->format('Y-m-d H:i:s');
        $failedAttempts = 0;
    }
    update_user([
        'id' => $user['id'],
        'failed_attempts' => $failedAttempts,
        'lockout_until' => $lockoutUntil,
    ]);
}

function reset_failed_login(array $user): void {
    update_user([
        'id' => $user['id'],
        'failed_attempts' => 0,
        'lockout_until' => null,
    ]);
}

function login_user(array $user): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['head_service'] = $user['head_service'];
}

function ensure_library_schema(): void {
    $pdo = get_db();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS library_visits (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED DEFAULT NULL,
            name VARCHAR(150) NOT NULL,
            visitor_type ENUM(\'student\', \'teacher\', \'visitor\') NOT NULL DEFAULT \'visitor\',
            student_number VARCHAR(50) DEFAULT NULL,
            employee_id VARCHAR(50) DEFAULT NULL,
            course_department VARCHAR(150) DEFAULT NULL,
            purpose VARCHAR(255) NOT NULL,
            checkin_method VARCHAR(50) NOT NULL DEFAULT \'qr\',
            time_in DATETIME NOT NULL,
            time_out DATETIME DEFAULT NULL,
            duration_minutes INT DEFAULT NULL,
            status ENUM(\'pending\', \'in\', \'out\') NOT NULL DEFAULT \'pending\',
            is_approved TINYINT(1) NOT NULL DEFAULT 0,
            approved_by INT UNSIGNED DEFAULT NULL,
            approved_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    add_column_if_missing($pdo, 'library_visits', 'status', "ENUM('pending', 'in', 'out') NOT NULL DEFAULT 'pending'");
    add_column_if_missing($pdo, 'library_visits', 'is_approved', 'TINYINT(1) NOT NULL DEFAULT 0');
    add_column_if_missing($pdo, 'library_visits', 'approved_by', 'INT UNSIGNED DEFAULT NULL');
    add_column_if_missing($pdo, 'library_visits', 'approved_at', 'DATETIME DEFAULT NULL');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS library_books (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            author VARCHAR(150) NOT NULL,
            isbn VARCHAR(50) DEFAULT NULL,
            category VARCHAR(100) DEFAULT NULL,
            status ENUM(\'Available\', \'Borrowed\', \'Reserved\', \'Under Repair\') NOT NULL DEFAULT \'Available\',
            total_copies INT NOT NULL DEFAULT 1,
            available_copies INT NOT NULL DEFAULT 1,
            rating DECIMAL(2,1) DEFAULT 0,
            overview TEXT DEFAULT NULL,
            cover_url VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS library_book_copies (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            book_id INT UNSIGNED NOT NULL,
            copy_type ENUM(\'volume\', \'part\', \'number\', \'copies\') NOT NULL DEFAULT \'copies\',
            copy_number INT NOT NULL,
            identifier VARCHAR(50) NOT NULL,
            lcc_letter_line VARCHAR(10) DEFAULT NULL,
            lcc_class_number VARCHAR(50) DEFAULT NULL,
            lcc_cutter_code VARCHAR(50) DEFAULT NULL,
            lcc_published_year VARCHAR(10) DEFAULT NULL,
            lcc_additional_info VARCHAR(100) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (book_id) REFERENCES library_books(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS library_borrows (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            book_id INT UNSIGNED NOT NULL,
            borrow_date DATETIME NOT NULL,
            due_date DATETIME NOT NULL,
            borrow_days INT NOT NULL DEFAULT 3,
            returned_at DATETIME DEFAULT NULL,
            status ENUM(\'Borrowed\', \'Returned\', \'Overdue\') NOT NULL DEFAULT \'Borrowed\',
            condition_status ENUM(\'Good\', \'Damaged\', \'Lost\') NOT NULL DEFAULT \'Good\',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (book_id) REFERENCES library_books(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    add_column_if_missing($pdo, 'library_borrows', 'borrow_days', 'INT NOT NULL DEFAULT 3');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS library_reservations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            book_id INT UNSIGNED NOT NULL,
            reserved_at DATETIME NOT NULL,
            reserved_date DATE NOT NULL,
            queue_position INT NOT NULL DEFAULT 1,
            status ENUM(\'Active\', \'Completed\', \'Cancelled\') NOT NULL DEFAULT \'Active\',
            expires_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (book_id) REFERENCES library_books(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS library_resources (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            category VARCHAR(100) DEFAULT NULL,
            course_year VARCHAR(100) DEFAULT NULL,
            file_url VARCHAR(255) DEFAULT NULL,
            file_name VARCHAR(255) DEFAULT NULL,
            file_size INT UNSIGNED DEFAULT NULL,
            file_type VARCHAR(50) DEFAULT NULL,
            uploaded_by INT UNSIGNED DEFAULT NULL,
            is_approved TINYINT(1) NOT NULL DEFAULT 0,
            approved_by INT UNSIGNED DEFAULT NULL,
            approved_at DATETIME DEFAULT NULL,
            download_count INT UNSIGNED DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS library_resource_categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS library_calendar (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_date DATE NOT NULL,
            event_type ENUM(\'holiday\', \'no_class\', \'graduation\', \'exam\', \'acquaintance\', \'intramurals\', \'others\') NOT NULL DEFAULT \'no_class\',
            description VARCHAR(255) DEFAULT NULL,
            start_time TIME DEFAULT NULL,
            end_time TIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    add_column_if_missing($pdo, 'library_calendar', 'start_time', 'TIME DEFAULT NULL');
    add_column_if_missing($pdo, 'library_calendar', 'end_time', 'TIME DEFAULT NULL');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS library_calendar_images (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            calendar_id INT UNSIGNED NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (calendar_id) REFERENCES library_calendar(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS library_categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS library_config (
            config_key VARCHAR(100) PRIMARY KEY,
            config_value TEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $pdo->exec('ALTER TABLE library_config MODIFY COLUMN config_value TEXT NOT NULL');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS library_fines (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            borrow_id INT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            paid TINYINT(1) NOT NULL DEFAULT 0,
            description VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (borrow_id) REFERENCES library_borrows(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS library_notifications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            reservation_id INT UNSIGNED DEFAULT NULL,
            type ENUM(\'reservation_ready\', \'reservation_expired\', \'reservation_on_hold\', \'pickup_available\') NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reservation_id) REFERENCES library_reservations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    try {
        $pdo->exec('ALTER TABLE library_reservations ADD COLUMN pickup_date DATE DEFAULT NULL AFTER reserved_date');
    } catch (Exception $e) {
    }

    try {
        $pdo->exec('ALTER TABLE library_reservations ADD COLUMN pickup_until DATETIME DEFAULT NULL AFTER expires_at');
    } catch (Exception $e) {
    }
}

function ensure_guidance_schema(): void {
    $pdo = get_db();

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS guidance_cases (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            case_number VARCHAR(50) NOT NULL UNIQUE,
            reported_student_id INT UNSIGNED NOT NULL,
            reporter_user_id INT UNSIGNED DEFAULT NULL,
            reporter_role ENUM(\'student\',\'teacher\') NOT NULL,
            report_type ENUM(\'student_report\',\'teacher_report\',\'self_request\',\'other\') NOT NULL DEFAULT \'student_report\',
            type_of_concern VARCHAR(100) NOT NULL,
            course_year VARCHAR(100) DEFAULT NULL,
            incident_description TEXT NOT NULL,
            parent_guardian_notified TINYINT(1) NOT NULL DEFAULT 0,
            teacher_awareness_required TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM(\'pending_review\',\'under_counseling\',\'returned_for_clarification\',\'awaiting_session\',\'closed\') NOT NULL DEFAULT \'pending_review\',
            external_resolution ENUM(\'none\',\'counseling_completed_follow_up\',\'advised_behavioral_improvement\',\'under_monitoring\',\'case_resolved\',\'scheduled_for_further_sessions\') NOT NULL DEFAULT \'none\',
            internal_resolution ENUM(\'none\',\'with_reformation\',\'without_reformation\',\'with_warning\',\'under_monitoring\',\'escalated_case\',\'ongoing_case\',\'pending_case\',\'other\',\'closed_case\') NOT NULL DEFAULT \'none\',
            case_remarks VARCHAR(255) DEFAULT NULL,
            internal_notes TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (reported_student_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    try {
        add_column_if_missing($pdo, 'guidance_cases', 'priority_level', "ENUM('Low','Medium','High','Emergency') NOT NULL DEFAULT 'Medium'");
    } catch (Exception $e) {
        // Ignore if the MySQL version does not support IF NOT EXISTS on ALTER TABLE
    }

    try {
        add_column_if_missing($pdo, 'guidance_cases', 'case_remarks', 'VARCHAR(255) DEFAULT NULL');
    } catch (Exception $e) {
        // Ignore if not supported or already exists
    }

    try {
        $pdo->exec('ALTER TABLE guidance_cases MODIFY COLUMN internal_resolution ENUM(\'none\',\'with_reformation\',\'without_reformation\',\'with_warning\',\'under_monitoring\',\'escalated_case\',\'ongoing_case\',\'pending_case\',\'other\',\'closed_case\') NOT NULL DEFAULT \'none\'');
    } catch (Exception $e) {
        // Ignore if the MySQL version does not support this alter or if the column already has the correct type.
    }

    // New case_reporters table for multiple reporters
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS case_reporters (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            case_id INT NOT NULL,
            reporter_type ENUM(\'student\',\'teacher\',\'parent_guardian\',\'course\',\'other\',\'proactive\') NOT NULL DEFAULT \'student\',
            reporter_name VARCHAR(150) DEFAULT NULL,
            reporter_id VARCHAR(50) DEFAULT NULL,
            reporter_course VARCHAR(100) DEFAULT NULL,
            mobile_number VARCHAR(20) DEFAULT NULL,
            custom_reporter_type VARCHAR(100) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (case_id) REFERENCES guidance_cases(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Ensure columns exist for case_reporters table
    try {
        add_column_if_missing($pdo, 'case_reporters', 'reporter_type', "ENUM('student','teacher','parent_guardian','course','other','proactive') NOT NULL DEFAULT 'student'");
        add_column_if_missing($pdo, 'case_reporters', 'reporter_name', 'VARCHAR(150) DEFAULT NULL');
        add_column_if_missing($pdo, 'case_reporters', 'reporter_id', 'VARCHAR(50) DEFAULT NULL');
        add_column_if_missing($pdo, 'case_reporters', 'reporter_course', 'VARCHAR(100) DEFAULT NULL');
        add_column_if_missing($pdo, 'case_reporters', 'mobile_number', 'VARCHAR(20) DEFAULT NULL');
        add_column_if_missing($pdo, 'case_reporters', 'custom_reporter_type', 'VARCHAR(100) DEFAULT NULL');
    } catch (Exception $e) {
        // Ignore if DB doesn't support IF NOT EXISTS or columns already exist
    }

    // New case_reported_persons table for who was reported
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS case_reported_persons (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            case_id INT NOT NULL,
            person_type ENUM(\'student\',\'teacher\',\'course\',\'other\') NOT NULL DEFAULT \'student\',
            person_name VARCHAR(150) DEFAULT NULL,
            person_id VARCHAR(50) DEFAULT NULL,
            person_user_id INT DEFAULT NULL,
            person_course VARCHAR(100) DEFAULT NULL,
            custom_person_type VARCHAR(100) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (case_id) REFERENCES guidance_cases(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Ensure columns exist for case_reported_persons table
    try {
        add_column_if_missing($pdo, 'case_reported_persons', 'person_type', "ENUM('student','teacher','course','other') NOT NULL DEFAULT 'student'");
        add_column_if_missing($pdo, 'case_reported_persons', 'person_name', 'VARCHAR(150) DEFAULT NULL');
        add_column_if_missing($pdo, 'case_reported_persons', 'person_id', 'VARCHAR(50) DEFAULT NULL');
        add_column_if_missing($pdo, 'case_reported_persons', 'person_user_id', 'INT DEFAULT NULL');
        add_column_if_missing($pdo, 'case_reported_persons', 'person_course', 'VARCHAR(100) DEFAULT NULL');
        add_column_if_missing($pdo, 'case_reported_persons', 'custom_person_type', 'VARCHAR(100) DEFAULT NULL');
    } catch (Exception $e) {
        // Ignore if DB doesn't support IF NOT EXISTS or columns already exist
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS guidance_case_attachments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            case_id INT UNSIGNED NOT NULL,
            uploaded_by INT UNSIGNED DEFAULT NULL,
            file_path VARCHAR(255) NOT NULL,
            original_file_name VARCHAR(255) NOT NULL,
            file_type VARCHAR(50) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (case_id) REFERENCES guidance_cases(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS guidance_sessions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            case_id INT UNSIGNED NOT NULL,
            counselor_id INT UNSIGNED DEFAULT NULL,
            session_date DATETIME NOT NULL,
            session_category ENUM(\'under_guidance_counseling\',\'parent_guardian_conference\',\'teacher_awareness\',\'professional_evaluation\',\'follow_up_session\',\'behavioral_monitoring\',\'conflict_mediation\',\'academic_support_referral\') NOT NULL DEFAULT \'under_guidance_counseling\',
            participation_scope ENUM(\'student_only\',\'with_parent_guardian\',\'with_teacher_awareness\') NOT NULL DEFAULT \'student_only\',
            attendance_status ENUM(\'attended\',\'non_appearance\',\'pending\') NOT NULL DEFAULT \'pending\',
            notes TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (case_id) REFERENCES guidance_cases(id) ON DELETE CASCADE,
            FOREIGN KEY (counselor_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS guidance_observations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            case_id INT UNSIGNED NOT NULL,
            observer_user_id INT UNSIGNED DEFAULT NULL,
            observer_role ENUM(\'teacher\',\'student\') NOT NULL,
            observation TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (case_id) REFERENCES guidance_cases(id) ON DELETE CASCADE,
            FOREIGN KEY (observer_user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS good_moral_records (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_id INT UNSIGNED NOT NULL UNIQUE,
            issued_by INT UNSIGNED DEFAULT NULL,
            status ENUM(\'eligible\',\'flagged\',\'pending\') NOT NULL DEFAULT \'pending\',
            notification_status ENUM(\'none\',\'approved\',\'hold\') NOT NULL DEFAULT \'none\',
            notification_sent_at DATETIME DEFAULT NULL,
            notification_read_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Ensure attendance_status column exists in guidance_sessions table
    try {
        $result = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='guidance_sessions' AND COLUMN_NAME='attendance_status'");
        if ($result->rowCount() === 0) {
            $pdo->exec('ALTER TABLE guidance_sessions ADD COLUMN attendance_status ENUM(\'attended\',\'non_appearance\',\'pending\') NOT NULL DEFAULT \'pending\' AFTER participation_scope');
        }
    } catch (PDOException $e) {
        // Column might already exist or table doesn't exist yet, skip silently
    }

    try {
        $result = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='good_moral_records' AND COLUMN_NAME='notification_status'");
        if ($result->rowCount() === 0) {
            $pdo->exec('ALTER TABLE good_moral_records ADD COLUMN notification_status ENUM(\'none\',\'approved\',\'hold\') NOT NULL DEFAULT \'none\' AFTER status');
        }
    } catch (PDOException $e) {
        // Column might already exist or table doesn't exist yet, skip silently
    }

    try {
        $result = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='good_moral_records' AND COLUMN_NAME='notification_sent_at'");
        if ($result->rowCount() === 0) {
            $pdo->exec('ALTER TABLE good_moral_records ADD COLUMN notification_sent_at DATETIME DEFAULT NULL AFTER notification_status');
        }
    } catch (PDOException $e) {
        // Column might already exist or table doesn't exist yet, skip silently
    }

    try {
        $result = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='good_moral_records' AND COLUMN_NAME='notification_read_at'");
        if ($result->rowCount() === 0) {
            $pdo->exec('ALTER TABLE good_moral_records ADD COLUMN notification_read_at DATETIME DEFAULT NULL AFTER notification_sent_at');
        }
    } catch (PDOException $e) {
        // Column might already exist or table doesn't exist yet, skip silently
    }
}

function generate_guidance_case_number(): string {
    try {
        $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    } catch (Exception $e) {
        $suffix = strtoupper(substr(md5(uniqid('', true)), 0, 6));
    }
    return 'G-' . date('Ymd') . '-' . $suffix;
}

function table_has_column(string $tableName, string $columnName): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
    );
    $stmt->execute(['table' => $tableName, 'column' => $columnName]);
    return (int)$stmt->fetchColumn() > 0;
}

function create_guidance_case(array $data): ?int {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'INSERT INTO guidance_cases (case_number, created_by, reported_student_id, reporter_user_id, reporter_role, report_type, type_of_concern, course_year, incident_description, parent_guardian_notified, teacher_awareness_required, priority_level) VALUES (:case_number, :created_by, :reported_student_id, :reporter_user_id, :reporter_role, :report_type, :type_of_concern, :course_year, :incident_description, :parent_guardian_notified, :teacher_awareness_required, :priority_level)'
    );
    $success = $stmt->execute([
        'case_number' => $data['case_number'],
        'created_by' => (int)($data['created_by'] ?? $data['reporter_user_id'] ?? $data['reported_student_id']),
        'reported_student_id' => $data['reported_student_id'],
        'reporter_user_id' => $data['reporter_user_id'],
        'reporter_role' => $data['reporter_role'],
        'report_type' => $data['report_type'],
        'type_of_concern' => $data['type_of_concern'],
        'course_year' => $data['course_year'],
        'incident_description' => $data['incident_description'],
        'parent_guardian_notified' => $data['parent_guardian_notified'],
        'teacher_awareness_required' => $data['teacher_awareness_required'],
        'priority_level' => $data['priority_level'] ?? 'Medium',
    ]);

    return $success ? (int)$pdo->lastInsertId() : null;
}

function get_guidance_cases_for_user(int $userId, ?string $externalId = null): array {
    $pdo = get_db();
    $query = 
        'SELECT DISTINCT c.*, reporter.full_name AS reporter_name, reported.full_name AS reported_student_name
         FROM guidance_cases c
         JOIN users reporter ON c.reporter_user_id = reporter.id
         LEFT JOIN users reported ON c.reported_student_id = reported.id
         LEFT JOIN case_reported_persons crp ON c.id = crp.case_id
         WHERE c.reporter_user_id = :user_id1
            OR c.reported_student_id = :user_id2
            OR crp.person_user_id = :user_id3'
    ;
    if ($externalId !== null) {
        $query .= ' OR crp.person_id = :external_id';
    }
    $query .= ' GROUP BY c.id ORDER BY c.created_at DESC';

    $stmt = $pdo->prepare($query);
    $params = [
        'user_id1' => $userId,
        'user_id2' => $userId,
        'user_id3' => $userId,
    ];
    if ($externalId !== null) {
        $params['external_id'] = $externalId;
    }
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_guidance_cases_for_counselor(int $counselorId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT c.*, reporter.full_name AS reporter_name, reported.full_name AS reported_student_name
         FROM guidance_cases c
         LEFT JOIN users reporter ON c.reporter_user_id = reporter.id
         LEFT JOIN users reported ON c.reported_student_id = reported.id
         WHERE c.counselor_id = :counselor_id OR c.counselor_id IS NULL
         GROUP BY c.id
         ORDER BY c.created_at DESC'
    );
    $stmt->execute(['counselor_id' => $counselorId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_guidance_case_by_id(int $caseId): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT c.*, reporter.full_name AS reporter_name, reported.full_name AS reported_student_name
         FROM guidance_cases c
         JOIN users reporter ON c.reporter_user_id = reporter.id
         LEFT JOIN users reported ON c.reported_student_id = reported.id
         WHERE c.id = :case_id LIMIT 1'
    );
    $stmt->execute(['case_id' => $caseId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function get_case_reported_persons(int $caseId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM case_reported_persons WHERE case_id = :case_id');
    $stmt->execute(['case_id' => $caseId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function update_guidance_case(int $caseId, array $data): bool {
    if (empty($data)) {
        return false;
    }
    $fields = [];
    $params = ['id' => $caseId];
    foreach ($data as $key => $value) {
        $fields[] = "$key = :$key";
        $params[$key] = $value;
    }
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE guidance_cases SET ' . implode(', ', $fields) . ' WHERE id = :id');
    return $stmt->execute($params);
}

function guidance_participants_label(?string $scope): string {
    $scope = trim((string)$scope);
    if ($scope === '') {
        return 'STUDENT ONLY';
    }

    if (stripos($scope, 'teacher') !== false) {
        return 'TEACHER AND STUDENT';
    }

    if (stripos($scope, 'parent') !== false || stripos($scope, 'student') !== false) {
        return stripos($scope, 'parent') !== false ? 'Student and Parent/Guardian' : 'STUDENT ONLY';
    }

    return $scope;
}

function get_guidance_case_attachments(int $caseId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM guidance_case_attachments WHERE case_id = :case_id ORDER BY uploaded_at DESC');
    $stmt->execute(['case_id' => $caseId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function add_guidance_case_attachment(int $caseId, int $uploadedBy, string $filePath, string $originalFileName, ?string $fileType): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'INSERT INTO guidance_case_attachments (case_id, uploaded_by, filepath, filename) VALUES (:case_id, :uploaded_by, :filepath, :filename)'
    );
    return $stmt->execute([
        'case_id' => $caseId,
        'uploaded_by' => $uploadedBy,
        'filepath' => $filePath,
        'filename' => $originalFileName,
    ]);
}

function get_guidance_sessions_for_case(int $caseId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT s.*, u.full_name AS counselor_name
         FROM guidance_sessions s
         LEFT JOIN users u ON s.counselor_id = u.id
         WHERE s.case_id = :case_id
         ORDER BY s.session_date DESC'
    );
    $stmt->execute(['case_id' => $caseId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function add_guidance_session(array $data): bool {
    $pdo = get_db();
    $usesSeparateTime = table_has_column('guidance_sessions', 'session_time');

    if ($usesSeparateTime) {
        $sessionDate = $data['session_date'];
        $sessionTime = $data['session_time'] ?? null;
        if ($sessionTime === null && str_contains($sessionDate, ' ')) {
            [$sessionDate, $sessionTime] = explode(' ', $sessionDate, 2);
        }
        if ($sessionTime === null) {
            $sessionTime = '00:00:00';
        }
        $stmt = $pdo->prepare(
            'INSERT INTO guidance_sessions (case_id, counselor_id, session_date, session_time, session_category, participation_scope, attendance_status, notes) VALUES (:case_id, :counselor_id, :session_date, :session_time, :session_category, :participation_scope, :attendance_status, :notes)'
        );
        return $stmt->execute([
            'case_id' => $data['case_id'],
            'counselor_id' => $data['counselor_id'],
            'session_date' => $sessionDate,
            'session_time' => $sessionTime,
            'session_category' => $data['session_category'],
            'participation_scope' => $data['participation_scope'],
            'attendance_status' => $data['attendance_status'],
            'notes' => $data['notes'],
        ]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO guidance_sessions (case_id, counselor_id, session_date, session_category, participation_scope, attendance_status, notes) VALUES (:case_id, :counselor_id, :session_date, :session_category, :participation_scope, :attendance_status, :notes)'
    );
    return $stmt->execute([
        'case_id' => $data['case_id'],
        'counselor_id' => $data['counselor_id'],
        'session_date' => $data['session_date'],
        'session_category' => $data['session_category'],
        'participation_scope' => $data['participation_scope'],
        'attendance_status' => $data['attendance_status'],
        'notes' => $data['notes'],
    ]);
}

function get_guidance_observations_for_case(int $caseId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT o.*, u.full_name AS observer_name
         FROM guidance_observations o
         LEFT JOIN users u ON o.observer_user_id = u.id
         WHERE o.case_id = :case_id
         ORDER BY o.created_at DESC'
    );
    $stmt->execute(['case_id' => $caseId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function compute_guidance_good_moral_status(int $studentId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM guidance_cases WHERE reported_student_id = :student_id AND status != \"closed\"');
    $stmt->execute(['student_id' => $studentId]);
    $unresolvedCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM guidance_sessions s
         JOIN guidance_cases c ON s.case_id = c.id
         WHERE c.reported_student_id = :student_id AND s.attendance_status = :attendance_status'
    );
    $stmt->execute(['student_id' => $studentId, 'attendance_status' => 'non_appearance']);
    $nonAppearanceCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM guidance_cases WHERE reported_student_id = :student_id AND internal_resolution IN (\'without_reformation\', \'with_warning\', \'under_monitoring\', \'escalated_case\', \'ongoing_case\')'
    );
    $stmt->execute(['student_id' => $studentId]);
    $negativeResolutionCount = (int)$stmt->fetchColumn();

    if ($unresolvedCount > 0) {
        $status = 'needs_review';
        $reason = 'There are ' . $unresolvedCount . ' unresolved guidance case(s).';
    } elseif ($nonAppearanceCount > 0 || $negativeResolutionCount > 0) {
        $status = 'not_eligible';
        $reason = 'There are ' . $nonAppearanceCount . ' non-appearance record(s) and ' . $negativeResolutionCount . ' negative internal resolution record(s).';
    } else {
        $status = 'eligible';
        $reason = 'All guidance cases are closed and no non-appearance or negative internal records were found.';
    }

    return [
        'status' => $status,
        'unresolved' => $unresolvedCount,
        'non_appearance' => $nonAppearanceCount,
        'negative_internal' => $negativeResolutionCount,
        'reason' => $reason,
    ];
}

function get_library_book_count(): int {
    $pdo = get_db();
    $stmt = $pdo->query('SELECT COUNT(*) FROM library_books');
    return (int)$stmt->fetchColumn();
}

function get_all_library_books(): array {
    $pdo = get_db();
    $stmt = $pdo->query('SELECT * FROM library_books ORDER BY title ASC');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_library_resource_count(): int {
    $pdo = get_db();
    $stmt = $pdo->query('SELECT COUNT(*) FROM library_resources');
    return (int)$stmt->fetchColumn();
}

function get_library_categories(): array {
    $pdo = get_db();
    $stmt = $pdo->query('SELECT * FROM library_categories ORDER BY name ASC');
    return $stmt->fetchAll();
}

function get_library_category_by_id(int $categoryId): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM library_categories WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $categoryId]);
    return $stmt->fetch() ?: null;
}

function add_library_category(string $name): bool {
    if (trim($name) === '') {
        return false;
    }
    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT INTO library_categories (name) VALUES (:name)');
    return $stmt->execute(['name' => trim($name)]);
}

function update_library_category(int $categoryId, string $name): bool {
    if (trim($name) === '') {
        return false;
    }
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE library_categories SET name = :name WHERE id = :id');
    return $stmt->execute(['name' => trim($name), 'id' => $categoryId]);
}

function delete_library_category(int $categoryId): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT name FROM library_categories WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $categoryId]);
    $category = $stmt->fetchColumn();
    if (!$category) {
        return false;
    }
    $pdo->beginTransaction();
    try {
        $updateBooks = $pdo->prepare('UPDATE library_books SET category = :default_category WHERE category = :category');
        $updateBooks->execute(['default_category' => 'General', 'category' => $category]);
        $delete = $pdo->prepare('DELETE FROM library_categories WHERE id = :id');
        $delete->execute(['id' => $categoryId]);
        $pdo->commit();
        return true;
    } catch (Exception $ex) {
        $pdo->rollBack();
        return false;
    }
}

function ensure_default_eresource_categories(): void {
    $pdo = get_db();
    
    // Check if any categories exist
    $stmt = $pdo->query('SELECT COUNT(*) FROM library_resource_categories');
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        // Add default e-resource categories
        $defaultCategories = [
            'E-Journals',
            'E-Books',
            'Databases',
            'Online Theses',
            'Research Articles',
            'Educational Videos',
            'Online Lectures',
            'Websites'
        ];
        
        $stmt = $pdo->prepare('INSERT INTO library_resource_categories (name) VALUES (:name)');
        foreach ($defaultCategories as $category) {
            try {
                $stmt->execute(['name' => $category]);
            } catch (Exception $e) {
                // Skip if category already exists
            }
        }
    }
}

function get_library_resource_categories(): array {
    $pdo = get_db();
    $stmt = $pdo->query('SELECT * FROM library_resource_categories ORDER BY name ASC');
    return $stmt->fetchAll();
}

function get_library_resource_category_by_id(int $categoryId): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM library_resource_categories WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $categoryId]);
    return $stmt->fetch() ?: null;
}

function add_library_resource_category(string $name): bool {
    if (trim($name) === '') {
        return false;
    }
    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT INTO library_resource_categories (name) VALUES (:name)');
    return $stmt->execute(['name' => trim($name)]);
}

function update_library_resource_category(int $categoryId, string $name): bool {
    if (trim($name) === '') {
        return false;
    }
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE library_resource_categories SET name = :name WHERE id = :id');
    return $stmt->execute(['name' => trim($name), 'id' => $categoryId]);
}

function delete_library_resource_category(int $categoryId): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT name FROM library_resource_categories WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $categoryId]);
    $category = $stmt->fetchColumn();
    if (!$category) {
        return false;
    }
    $pdo->beginTransaction();
    try {
        $updateResources = $pdo->prepare('UPDATE library_resources SET category = :default_category WHERE category = :category');
        $updateResources->execute(['default_category' => 'General', 'category' => $category]);
        $delete = $pdo->prepare('DELETE FROM library_resource_categories WHERE id = :id');
        $delete->execute(['id' => $categoryId]);
        $pdo->commit();
        return true;
    } catch (Exception $ex) {
        $pdo->rollBack();
        return false;
    }
}

function save_library_cover_upload(array $file): ?string {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowed, true)) {
        return null;
    }
    $baseDir = __DIR__ . '/../IMG ASSETS/library_covers';
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0755, true);
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('cover_', true) . '.' . strtolower($ext);
    $destination = $baseDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return null;
    }
    return 'IMG ASSETS/library_covers/' . $filename;
}

function save_ssc_event_photo_upload(array $file): ?string {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowed, true)) {
        return null;
    }
    $baseDir = __DIR__ . '/../IMG ASSETS/ssc_events';
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0755, true);
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('ssc_event_', true) . '.' . strtolower($ext);
    $destination = $baseDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return null;
    }
    return 'IMG ASSETS/ssc_events/' . $filename;
}

function save_scholarship_image_upload(array $file): ?string {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowed, true)) {
        return null;
    }
    $baseDir = __DIR__ . '/../IMG ASSETS/scholarship';
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0755, true);
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('scholarship_', true) . '.' . strtolower($ext);
    $destination = $baseDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return null;
    }
    return '../IMG ASSETS/scholarship/' . $filename;
}

function save_student_registration_document_upload(array $file): ?string {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    if (!in_array($file['type'], $allowed, true)) {
        return null;
    }
    $baseDir = __DIR__ . '/../uploads/student_registrations';
    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0755, true);
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('student_doc_', true) . '.' . strtolower($ext);
    $destination = $baseDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return null;
    }
    return 'uploads/student_registrations/' . $filename;
}

function get_library_config(): array {
    $pdo = get_db();
    $stmt = $pdo->query('SELECT config_key, config_value FROM library_config');
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (empty($rows)) {
        $defaults = [
            'max_borrowed_books' => '3',
            'max_active_reservations' => '2',
            'borrow_duration_school_days' => '3',
            'reservation_hold_days' => '2',
            'overdue_fine_per_day' => '20',
        ];
        $insertStmt = $pdo->prepare('INSERT INTO library_config (config_key, config_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)');
        foreach ($defaults as $key => $value) {
            $insertStmt->execute(['key' => $key, 'value' => $value]);
            $rows[$key] = $value;
        }
    }
    return $rows;
}

function get_library_setting(string $key, $default = null) {
    $config = get_library_config();
    return $config[$key] ?? $default;
}

function set_library_setting(string $key, string $value): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT INTO library_config (config_key, config_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)');
    return $stmt->execute(['key' => $key, 'value' => $value]);
}

function get_application_url_prefix(): string {
    if (empty($_SERVER['SCRIPT_FILENAME'])) {
        return '../';
    }
    $scriptDir = str_replace('\\', '/', dirname((string)$_SERVER['SCRIPT_FILENAME']));
    $appRoot = str_replace('\\', '/', dirname(__DIR__));
    if ($scriptDir === $appRoot) {
        return '';
    }
    if (str_starts_with($scriptDir, $appRoot . '/')) {
        $relativePath = substr($scriptDir, strlen($appRoot) + 1);
        $segments = array_filter(explode('/', $relativePath), 'strlen');
        return str_repeat('../', count($segments));
    }
    return '../';
}

function get_login_logo_path(): string {
    $path = get_library_setting('login_logo_path', 'IMG ASSETS/passlogo.png');
    $path = trim((string)$path);
    if ($path === '') {
        $path = 'IMG ASSETS/passlogo.png';
    }
    $path = preg_replace('#^[\.\/]+#', '', $path);
    return get_application_url_prefix() . $path;
}

function record_library_fine(int $borrowId, int $userId, float $amount, string $description): bool {
    $pdo = get_db();
    $roleStmt = $pdo->prepare('SELECT role FROM users WHERE id = :user_id');
    $roleStmt->execute(['user_id' => $userId]);
    $userRole = $roleStmt->fetchColumn();

    if ($userRole === 'teacher') {
        return false;
    }

    $stmt = $pdo->prepare('SELECT id, paid FROM library_fines WHERE borrow_id = :borrow_id ORDER BY created_at DESC LIMIT 1');
    $stmt->execute(['borrow_id' => $borrowId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ((int)$existing['paid'] === 0) {
            $update = $pdo->prepare('UPDATE library_fines SET amount = :amount, description = :description WHERE id = :id');
            return $update->execute(['amount' => $amount, 'description' => $description, 'id' => $existing['id']]);
        }
        // If the most recent fine for this borrow has already been paid,
        // create a fresh record for any further overdue days or new penalty.
    }

    $insert = $pdo->prepare('INSERT INTO library_fines (user_id, borrow_id, amount, paid, description) VALUES (:user_id, :borrow_id, :amount, 0, :description)');
    $result = $insert->execute(['user_id' => $userId, 'borrow_id' => $borrowId, 'amount' => $amount, 'description' => $description]);
    
    // Send automatic email notification if fine was recorded
    if ($result) {
        send_library_fines_email_automatic($userId);
    }
    
    return $result;
}

function send_library_fines_email_automatic(int $userId): void {
    $pdo = get_db();
    
    // Get user details
    $userStmt = $pdo->prepare('SELECT full_name, email FROM users WHERE id = ? AND role = "student"');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || empty($user['email'])) {
        return;
    }
    
    // Get all unpaid fines for this user
    $finesStmt = $pdo->prepare('
        SELECT lf.amount, lb.title, lb.author, b.due_date
        FROM library_fines lf
        JOIN library_borrows b ON lf.borrow_id = b.id
        JOIN library_books lb ON b.book_id = lb.id
        WHERE lf.user_id = ? AND lf.paid = 0
        ORDER BY b.due_date DESC
    ');
    $finesStmt->execute([$userId]);
    $unpaidFines = $finesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($unpaidFines)) {
        return;
    }
    
    // Calculate total fines
    $totalFines = 0;
    $booksList = [];
    foreach ($unpaidFines as $fine) {
        $totalFines += (float)$fine['amount'];
        $daysOverdue = (new DateTime($fine['due_date']))->diff(new DateTime())->days;
        $booksList[] = [
            'title' => $fine['title'],
            'author' => $fine['author'],
            'amount' => number_format((float)$fine['amount'], 2),
            'days_overdue' => $daysOverdue
        ];
    }
    
    // Build overdue books HTML
    $booksHtml = '';
    foreach ($booksList as $book) {
        $booksHtml .= '<div style="padding:12px;border-bottom:1px solid #e0e0e0;font-size:14px;color:#2c3e50;">';
        $booksHtml .= '<strong style="color:#c7254e;">' . htmlspecialchars($book['title']) . '</strong>';
        $booksHtml .= '<div style="margin-top:4px;font-size:12px;color:#666;">';
        $booksHtml .= 'Author: ' . htmlspecialchars($book['author']) . ' | ';
        $booksHtml .= 'Overdue: ' . $book['days_overdue'] . ' day(s) | ';
        $booksHtml .= 'Fine: ₱' . $book['amount'];
        $booksHtml .= '</div></div>';
    }
    
    // Email parameters
    $emailParams = [
        'user_name' => $user['full_name'],
        'email' => $user['email'],
        'total_fines' => number_format($totalFines, 2),
        'overdue_books' => $booksHtml,
        'portal_link' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/THESIS/SUPPORTSERVICESYSTEM/dashboard/library_dashboard.php',
        'request_id' => 'LIBFINE-' . time() . '-' . $userId
    ];
    
    // Send via EmailJS
    $emailService = 'service_41woiyr';
    $emailTemplate = 'template_qfr20uh';
    $emailPublicKey = 'M8U5HqlpqCLWLl9OS';
    
    send_emailjs_library_fines($emailService, $emailTemplate, $emailPublicKey, $emailParams);
}

function send_emailjs_library_fines(string $service_id, string $template_id, string $user_key, array $template_params): void {
    $payload = [
        'service_id' => $service_id,
        'template_id' => $template_id,
        'user_id' => $user_key,
        'template_params' => $template_params,
    ];

    $ch = curl_init('https://api.emailjs.com/api/v1.0/email/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    $errno = curl_errno($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log the request
    $logFile = __DIR__ . '/../logs/library_email_debug.log';
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    $logEntry = date('Y-m-d H:i:s') . " | Service: $service_id | Template: $template_id | To: {$template_params['email']} | HTTP Code: $httpCode | Curl Errno: $errno\n";
    if ($httpCode >= 400 || $errno !== 0) {
        $logEntry .= "Response: " . substr($result, 0, 500) . "\n";
    }
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

function send_library_notification(int $userId, string $subject, string $message): bool {
    $user = find_user_by_id($userId);
    if (!$user || empty($user['email'])) {
        return false;
    }
    $config = require __DIR__ . '/email_config.php';
    $headers = 'From: ' . ($config['smtp_from_name'] ?? 'PASS Support System') . ' <' . ($config['smtp_from'] ?? 'no-reply@supportsystem.local') . '>\r\n';
    $headers .= 'Reply-To: ' . ($config['smtp_from'] ?? 'no-reply@supportsystem.local') . '\r\n';
    return mail($user['email'], $subject, $message, $headers);
}

function is_school_day(DateTime $date): bool {
    $weekday = (int)$date->format('N');
    if ($weekday === 7) {
        return false;
    }
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM library_calendar WHERE event_date = :date');
    $stmt->execute(['date' => $date->format('Y-m-d')]);
    return (int)$stmt->fetchColumn() === 0;
}

function add_school_days(DateTime $date, int $days): DateTime {
    $result = clone $date;
    $count = 0;
    while ($count < $days) {
        $result->modify('+1 day');
        if (is_school_day($result)) {
            $count++;
        }
    }
    return $result;
}

function calculate_library_due_date(DateTime $borrowDate, int $borrowDays): DateTime {
    $dueDate = add_school_days($borrowDate, $borrowDays);
    $dueDate->setTime(17, 0, 0);
    return $dueDate;
}

function get_active_library_borrows(): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, user_id, borrow_date, due_date, borrow_days, status FROM library_borrows WHERE status IN (\'Borrowed\', \'Overdue\')');
    $stmt->execute();
    return $stmt->fetchAll();
}

function update_library_borrow_due_date(int $borrowId, string $dueDate, string $status = null): bool {
    $pdo = get_db();
    if ($status !== null) {
        $stmt = $pdo->prepare('UPDATE library_borrows SET due_date = :due_date, status = :status WHERE id = :id');
        return $stmt->execute(['due_date' => $dueDate, 'status' => $status, 'id' => $borrowId]);
    }
    $stmt = $pdo->prepare('UPDATE library_borrows SET due_date = :due_date WHERE id = :id');
    return $stmt->execute(['due_date' => $dueDate, 'id' => $borrowId]);
}

function sync_library_due_dates_for_calendar_updates(): void {
    $borrows = get_active_library_borrows();
    if (empty($borrows)) {
        return;
    }

    $now = new DateTime();
    foreach ($borrows as $borrow) {
        $borrowDays = $borrow['borrow_days'] ? (int)$borrow['borrow_days'] : (int)get_library_setting('borrow_duration_school_days', 3);
        $newDueDate = calculate_library_due_date(new DateTime($borrow['borrow_date']), $borrowDays)->format('Y-m-d H:i:s');
        if ($newDueDate === $borrow['due_date']) {
            continue;
        }

        $newStatus = $borrow['status'];
        if ($borrow['status'] === 'Overdue' && new DateTime($newDueDate) >= $now) {
            $newStatus = 'Borrowed';
        }

        update_library_borrow_due_date((int)$borrow['id'], $newDueDate, $newStatus);
    }
}

function get_library_book_by_id(int $bookId): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM library_books WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $bookId]);
    return $stmt->fetch() ?: null;
}

function update_library_book_stock(int $bookId, int $totalCopies, int $availableCopies): bool {
    $pdo = get_db();
    $status = $availableCopies > 0 ? 'Available' : 'Borrowed';
    $stmt = $pdo->prepare('UPDATE library_books SET total_copies = :total_copies, available_copies = :available_copies, status = :status WHERE id = :id');
    return $stmt->execute([
        'total_copies' => $totalCopies,
        'available_copies' => $availableCopies,
        'status' => $status,
        'id' => $bookId,
    ]);
}

function remove_library_book(int $bookId): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('DELETE FROM library_books WHERE id = :id');
    return $stmt->execute(['id' => $bookId]);
}

function add_library_book(array $data): bool {
    $pdo = get_db();
    $status = (int)$data['available_copies'] > 0 ? 'Available' : 'Borrowed';
    $stmt = $pdo->prepare('INSERT INTO library_books (title, author, isbn, category, status, total_copies, available_copies, rating, overview, cover_url) VALUES (:title, :author, :isbn, :category, :status, :total_copies, :available_copies, :rating, :overview, :cover_url)');
    return $stmt->execute([
        'title' => $data['title'],
        'author' => $data['author'],
        'isbn' => $data['isbn'],
        'category' => $data['category'],
        'status' => $status,
        'total_copies' => $data['total_copies'],
        'available_copies' => $data['available_copies'],
        'rating' => $data['rating'] ?? 0,
        'overview' => $data['overview'] ?? '',
        'cover_url' => $data['cover_url'] ?? '',
    ]);
}

function update_library_book(array $data): bool {
    $pdo = get_db();
    $book = get_library_book_by_id((int)$data['id']);
    if (!$book) {
        return false;
    }
    $status = (int)$data['available_copies'] > 0 ? 'Available' : 'Borrowed';
    $stmt = $pdo->prepare('UPDATE library_books SET title = :title, author = :author, isbn = :isbn, category = :category, total_copies = :total_copies, available_copies = :available_copies, overview = :overview, cover_url = :cover_url, status = :status WHERE id = :id');
    return $stmt->execute([
        'title' => $data['title'],
        'author' => $data['author'],
        'isbn' => $data['isbn'],
        'category' => $data['category'],
        'total_copies' => $data['total_copies'],
        'available_copies' => $data['available_copies'],
        'overview' => $data['overview'] ?? '',
        'cover_url' => $data['cover_url'] ?? $book['cover_url'],
        'status' => $status,
        'id' => $data['id'],
    ]);
}

function get_library_book_copies(int $bookId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM library_book_copies WHERE book_id = :book_id ORDER BY copy_type ASC, copy_number ASC, id ASC');
    $stmt->execute(['book_id' => $bookId]);
    return $stmt->fetchAll();
}

function add_library_book_copies(int $bookId, int $quantity, string $copyType): bool {
    $book = get_library_book_by_id($bookId);
    if (!$book) {
        return false;
    }

    $copyType = strtolower(trim($copyType));
    if (!in_array($copyType, ['volume', 'part', 'number', 'copies'], true)) {
        $copyType = 'copies';
    }

    $prefix = match ($copyType) {
        'volume' => 'V',
        'part' => 'P',
        'number' => 'N',
        'copies' => 'C',
        default => 'C',
    };

    $pdo = get_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT IFNULL(MAX(copy_number), 0) FROM library_book_copies WHERE book_id = :book_id AND copy_type = :copy_type');
        $stmt->execute(['book_id' => $bookId, 'copy_type' => $copyType]);
        $nextNumber = ((int)$stmt->fetchColumn()) + 1;

        $insert = $pdo->prepare('INSERT INTO library_book_copies (book_id, copy_type, copy_number, identifier, lcc_letter_line, lcc_class_number, lcc_cutter_code, lcc_published_year, lcc_additional_info) VALUES (:book_id, :copy_type, :copy_number, :identifier, :lcc_letter_line, :lcc_class_number, :lcc_cutter_code, :lcc_published_year, :lcc_additional_info)');
        for ($i = 1; $i <= $quantity; $i++) {
            $identifier = $prefix . $nextNumber . ($quantity > 1 ? '-' . $i : '');
            $insert->execute([
                'book_id' => $bookId,
                'copy_type' => $copyType,
                'copy_number' => $nextNumber,
                'identifier' => $identifier,
                'lcc_letter_line' => $book['lcc_letter_line'] ?? null,
                'lcc_class_number' => $book['lcc_class_number'] ?? null,
                'lcc_cutter_code' => $book['lcc_cutter_code'] ?? null,
                'lcc_published_year' => $book['lcc_published_year'] ?? null,
                'lcc_additional_info' => $book['lcc_additional_info'] ?? null,
            ]);
        }

        $newTotal = (int)$book['total_copies'] + $quantity;
        $newAvailable = (int)$book['available_copies'] + $quantity;
        $status = $newAvailable > 0 ? 'Available' : 'Borrowed';
        $update = $pdo->prepare('UPDATE library_books SET total_copies = :total_copies, available_copies = :available_copies, status = :status WHERE id = :id');
        $update->execute([
            'total_copies' => $newTotal,
            'available_copies' => $newAvailable,
            'status' => $status,
            'id' => $bookId,
        ]);

        $pdo->commit();
        return true;
    } catch (Exception $ex) {
        $pdo->rollBack();
        return false;
    }
}

function add_library_book_copy_group(int $bookId, string $copyType, int $copyNumber, int $quantity): bool {
    $book = get_library_book_by_id($bookId);
    if (!$book) {
        return false;
    }

    $copyType = strtolower(trim($copyType));
    if (!in_array($copyType, ['volume', 'part', 'number', 'copies'], true)) {
        $copyType = 'copies';
    }

    $prefix = match ($copyType) {
        'volume' => 'V',
        'part' => 'P',
        'number' => 'N',
        'copies' => 'C',
        default => 'C',
    };

    $pdo = get_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM library_book_copies WHERE book_id = :book_id AND copy_type = :copy_type AND copy_number = :copy_number');
        $stmt->execute(['book_id' => $bookId, 'copy_type' => $copyType, 'copy_number' => $copyNumber]);
        $existing = (int)$stmt->fetchColumn();

        $insert = $pdo->prepare('INSERT INTO library_book_copies (book_id, copy_type, copy_number, identifier, lcc_letter_line, lcc_class_number, lcc_cutter_code, lcc_published_year, lcc_additional_info) VALUES (:book_id, :copy_type, :copy_number, :identifier, :lcc_letter_line, :lcc_class_number, :lcc_cutter_code, :lcc_published_year, :lcc_additional_info)');
        for ($i = 1; $i <= $quantity; $i++) {
            $identifier = $prefix . $copyNumber . '-' . ($existing + $i);
            $insert->execute([
                'book_id' => $bookId,
                'copy_type' => $copyType,
                'copy_number' => $copyNumber,
                'identifier' => $identifier,
                'lcc_letter_line' => $book['lcc_letter_line'] ?? null,
                'lcc_class_number' => $book['lcc_class_number'] ?? null,
                'lcc_cutter_code' => $book['lcc_cutter_code'] ?? null,
                'lcc_published_year' => $book['lcc_published_year'] ?? null,
                'lcc_additional_info' => $book['lcc_additional_info'] ?? null,
            ]);
        }

        $newTotal = (int)$book['total_copies'] + $quantity;
        $newAvailable = (int)$book['available_copies'] + $quantity;
        $status = $newAvailable > 0 ? 'Available' : 'Borrowed';
        $update = $pdo->prepare('UPDATE library_books SET total_copies = :total_copies, available_copies = :available_copies, status = :status WHERE id = :id');
        $update->execute([
            'total_copies' => $newTotal,
            'available_copies' => $newAvailable,
            'status' => $status,
            'id' => $bookId,
        ]);

        $pdo->commit();
        return true;
    } catch (Exception $ex) {
        $pdo->rollBack();
        return false;
    }
}

function get_user_active_reservations_count(int $userId): int {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM library_reservations WHERE user_id = :user_id AND status = :status');
    $stmt->execute(['user_id' => $userId, 'status' => 'Active']);
    return (int)$stmt->fetchColumn();
}

function has_user_reserved_book(int $userId, int $bookId): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM library_reservations WHERE user_id = :user_id AND book_id = :book_id AND status = :status');
    $stmt->execute(['user_id' => $userId, 'book_id' => $bookId, 'status' => 'Active']);
    return (int)$stmt->fetchColumn() > 0;
}

function get_user_book_reservation(int $userId, int $bookId): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM library_reservations WHERE user_id = :user_id AND book_id = :book_id AND status = :status ORDER BY reserved_at DESC LIMIT 1');
    $stmt->execute(['user_id' => $userId, 'book_id' => $bookId, 'status' => 'Active']);
    return $stmt->fetch() ?: null;
}

function get_book_reservation_queue(int $bookId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT lr.queue_position, lr.reserved_date, lr.status, u.full_name AS reserved_by FROM library_reservations lr JOIN users u ON lr.user_id = u.id WHERE lr.book_id = :book_id AND lr.status = :status ORDER BY lr.queue_position ASC');
    $stmt->execute(['book_id' => $bookId, 'status' => 'Active']);
    return $stmt->fetchAll();
}

function get_next_reservation_position(int $bookId): int {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(queue_position), 0) FROM library_reservations WHERE book_id = :book_id AND status = :status');
    $stmt->execute(['book_id' => $bookId, 'status' => 'Active']);
    return (int)$stmt->fetchColumn() + 1;
}

function borrow_book(int $userId, int $bookId, string $borrowDate, string $dueDate, int $copyNumber = 0): bool {
    $pdo = get_db();
    $pdo->beginTransaction();
    try {
        $book = get_library_book_by_id($bookId);
        if (!$book || $book['available_copies'] <= 0) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
        $newCopies = max(0, $book['available_copies'] - 1);
        $newStatus = $newCopies === 0 ? 'Borrowed' : ($newCopies > 0 ? 'Available' : 'Borrowed');
        $stmt = $pdo->prepare('UPDATE library_books SET available_copies = :available_copies, status = :status WHERE id = :id');
        $stmt->execute(['available_copies' => $newCopies, 'status' => $newStatus, 'id' => $bookId]);
        $borrowDays = (int)get_library_setting('borrow_duration_school_days', 3);
        $borrowStmt = $pdo->prepare('INSERT INTO library_borrows (user_id, book_id, borrow_date, due_date, borrow_days, status, condition_status) VALUES (:user_id, :book_id, :borrow_date, :due_date, :borrow_days, :status, :condition_status)');
        $borrowStmt->execute([
            'user_id' => $userId,
            'book_id' => $bookId,
            'borrow_date' => $borrowDate,
            'due_date' => $dueDate,
            'borrow_days' => $borrowDays,
            'status' => 'Borrowed',
            'condition_status' => 'Good',
        ]);
        $pdo->commit();
        return true;
    } catch (Exception $ex) {
        $pdo->rollBack();
        return false;
    }
}

function fulfill_reserved_book_pickup(int $reservationId, int $userId, int $bookId, string $borrowDate, string $dueDate, int $copyNumber = 0): bool {
    $pdo = get_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM library_reservations WHERE id = :id AND user_id = :user_id AND book_id = :book_id AND status = :status LIMIT 1');
        $stmt->execute([
            'id' => $reservationId,
            'user_id' => $userId,
            'book_id' => $bookId,
            'status' => 'Active',
        ]);
        $reservation = $stmt->fetch();
        if (!$reservation) {
            $pdo->rollBack();
            return false;
        }

        $book = get_library_book_by_id($bookId);
        if (!$book) {
            $pdo->rollBack();
            return false;
        }

        $shouldDecrement = empty($reservation['pickup_date']) && empty($reservation['pickup_until']);
        $availableCopies = (int)$book['available_copies'];
        if ($shouldDecrement) {
            if ($availableCopies <= 0) {
                $pdo->rollBack();
                return false;
            }
            $availableCopies = max(0, $availableCopies - 1);
        }

        $newStatus = $availableCopies === 0 ? 'Borrowed' : 'Available';
        $updateBook = $pdo->prepare('UPDATE library_books SET available_copies = :available_copies, status = :status WHERE id = :id');
        $updateBook->execute([
            'available_copies' => $availableCopies,
            'status' => $newStatus,
            'id' => $bookId,
        ]);

        $borrowDays = (int)get_library_setting('borrow_duration_school_days', 3);
        $borrowStmt = $pdo->prepare('INSERT INTO library_borrows (user_id, book_id, borrow_date, due_date, borrow_days, status, condition_status) VALUES (:user_id, :book_id, :borrow_date, :due_date, :borrow_days, :status, :condition_status)');
        $borrowStmt->execute([
            'user_id' => $userId,
            'book_id' => $bookId,
            'borrow_date' => $borrowDate,
            'due_date' => $dueDate,
            'borrow_days' => $borrowDays,
            'status' => 'Borrowed',
            'condition_status' => 'Good',
        ]);

        $reservationPickupDate = $reservation['pickup_date'] ?? (new DateTime($borrowDate))->format('Y-m-d');
        $updateReservation = $pdo->prepare('UPDATE library_reservations SET status = :status, pickup_date = :pickup_date WHERE id = :id');
        $updateReservation->execute([
            'status' => 'Completed',
            'pickup_date' => $reservationPickupDate,
            'id' => $reservationId,
        ]);

        $pdo->commit();
        return true;
    } catch (Exception $ex) {
        $pdo->rollBack();
        return false;
    }
}

function reserve_book(int $userId, int $bookId, string $reservedAt, string $reservedDate): bool {
    $pdo = get_db();
    $position = get_next_reservation_position($bookId);
    $expiresAt = (new DateTime())->modify('+' . ((int)get_library_setting('reservation_hold_days', 2)) . ' days')->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare('INSERT INTO library_reservations (user_id, book_id, reserved_at, reserved_date, queue_position, status, expires_at) VALUES (:user_id, :book_id, :reserved_at, :reserved_date, :queue_position, :status, :expires_at)');
    return $stmt->execute([
        'user_id' => $userId,
        'book_id' => $bookId,
        'reserved_at' => $reservedAt,
        'reserved_date' => $reservedDate,
        'queue_position' => $position,
        'status' => 'Active',
        'expires_at' => $expiresAt,
    ]);
}

function cancel_library_reservation(int $reservationId, int $userId): bool {
    $pdo = get_db();
    $pdo->beginTransaction();
    try {
        // Get reservation details first
        $getRes = $pdo->prepare('SELECT book_id FROM library_reservations WHERE id = :id AND user_id = :user_id AND status = :active');
        $getRes->execute(['id' => $reservationId, 'user_id' => $userId, 'active' => 'Active']);
        $reservation = $getRes->fetch();
        
        if (!$reservation) {
            $pdo->rollBack();
            return false;
        }
        
        $bookId = (int)$reservation['book_id'];
        
        // Restore available copies
        $book = get_library_book_by_id($bookId);
        $newCopies = (int)$book['available_copies'] + 1;
        $newStatus = $newCopies > 0 ? 'Available' : 'Borrowed';
        $updateBook = $pdo->prepare('UPDATE library_books SET available_copies = :available_copies, status = :status WHERE id = :id');
        $updateBook->execute(['available_copies' => $newCopies, 'status' => $newStatus, 'id' => $bookId]);
        
        // Cancel the reservation
        $stmt = $pdo->prepare('UPDATE library_reservations SET status = :status WHERE id = :id AND user_id = :user_id AND status = :active');
        $result = $stmt->execute(['status' => 'Cancelled', 'id' => $reservationId, 'user_id' => $userId, 'active' => 'Active']);
        
        $pdo->commit();
        return $result;
    } catch (Exception $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

function process_reservation_queue(int $bookId): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT lr.id, lr.user_id, lr.book_id, u.email FROM library_reservations lr JOIN users u ON lr.user_id = u.id WHERE lr.book_id = :book_id AND lr.status = :status ORDER BY lr.queue_position ASC LIMIT 1');
    $stmt->execute(['book_id' => $bookId, 'status' => 'Active']);
    $reservation = $stmt->fetch();
    if (!$reservation) {
        return false;
    }
    $expiresAt = (new DateTime())->modify('+' . ((int)get_library_setting('reservation_hold_days', 2)) . ' days')->format('Y-m-d H:i:s');
    $update = $pdo->prepare('UPDATE library_reservations SET status = :status, expires_at = :expires_at WHERE id = :id');
    $completed = $update->execute(['status' => 'Completed', 'expires_at' => $expiresAt, 'id' => $reservation['id']]);
    if ($completed) {
        $subject = 'Library reservation ready for pickup';
        $message = 'Your reserved book is now available and ready for pick up. Please visit the library within ' . get_library_setting('reservation_hold_days', 2) . ' days.';
        if ($reservation['email']) {
            mail($reservation['email'], $subject, $message, 'From: PASS Support System <no-reply@supportsystem.local>');
        }
        return true;
    }
    return false;
}

function return_library_book(int $borrowId, string $returnedAt, string $condition = 'Good'): bool {
    $pdo = get_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM library_borrows WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $borrowId]);
        $borrow = $stmt->fetch();
        if (!$borrow || $borrow['status'] === 'Returned') {
            $pdo->rollBack();
            return false;
        }
        $book = get_library_book_by_id((int)$borrow['book_id']);
        $newCopies = max(0, (int)$book['available_copies'] + 1);
        $newStatus = $newCopies > 0 ? 'Available' : 'Borrowed';
        $updateBorrow = $pdo->prepare('UPDATE library_borrows SET returned_at = :returned_at, status = :status, condition_status = :condition_status WHERE id = :id');
        $updateBorrow->execute(['returned_at' => $returnedAt, 'status' => 'Returned', 'condition_status' => $condition, 'id' => $borrowId]);
        $updateBook = $pdo->prepare('UPDATE library_books SET available_copies = :available_copies, status = :status WHERE id = :id');
        $updateBook->execute(['available_copies' => $newCopies, 'status' => $newStatus, 'id' => $borrow['book_id']]);
        process_reservation_queue((int)$borrow['book_id']);
        $pdo->commit();
        return true;
    } catch (Exception $ex) {
        $pdo->rollBack();
        return false;
    }
}

function mark_overdue_library_items(): void {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT b.id, b.user_id, b.book_id, b.due_date, b.status, u.role AS user_role FROM library_borrows b JOIN users u ON b.user_id = u.id WHERE b.status IN ("Borrowed", "Overdue") AND b.due_date < NOW()');
    $stmt->execute();
    $overdue = $stmt->fetchAll();
    foreach ($overdue as $borrow) {
        if ($borrow['user_role'] === 'teacher') {
            if ($borrow['status'] === 'Overdue') {
                $update = $pdo->prepare('UPDATE library_borrows SET status = :status WHERE id = :id');
                $update->execute(['status' => 'Borrowed', 'id' => $borrow['id']]);
            }
            continue;
        }

        if ($borrow['status'] !== 'Overdue') {
            $update = $pdo->prepare('UPDATE library_borrows SET status = :status WHERE id = :id');
            $update->execute(['status' => 'Overdue', 'id' => $borrow['id']]);
        }

        $dueDate = new DateTime($borrow['due_date']);
        $days = max(1, $dueDate->diff(new DateTime())->days);
        $amount = $days * (float)get_library_setting('overdue_fine_per_day', 20);

        record_library_fine((int)$borrow['id'], (int)$borrow['user_id'], $amount, 'Overdue fine for ' . $days . ' day(s)');
    }
}

function seed_sample_library_books(): void {
    $pdo = get_db();
    $books = [
        ['Modern Education Systems', 'A. Santos', '978-1234567890', 'Education', 'Available', 5, 5, 4.5, 'A practical guide to school library operations and student learning.', ''],
        ['Digital Resource Management', 'M. Dela Cruz', '978-0987654321', 'Technology', 'Reserved', 3, 0, 4.2, 'Explore the digital catalog with smart reservation and queue features.', ''],
        ['Applied Library Strategies', 'J. Reyes', '978-1122334455', 'Library Science', 'Borrowed', 4, 0, 4.8, 'Best practices for modern library management in academic environments.', '']
    ];
    $stmt = $pdo->prepare('INSERT INTO library_books (title, author, isbn, category, status, total_copies, available_copies, rating, overview, cover_url) VALUES (:title, :author, :isbn, :category, :status, :total_copies, :available_copies, :rating, :overview, :cover_url)');
    foreach ($books as $book) {
        [$title, $author, $isbn, $category, $status, $total, $available, $rating, $overview, $cover] = $book;
        $stmt->execute([
            'title' => $title,
            'author' => $author,
            'isbn' => $isbn,
            'category' => $category,
            'status' => $status,
            'total_copies' => $total,
            'available_copies' => $available,
            'rating' => $rating,
            'overview' => $overview,
            'cover_url' => $cover,
        ]);
    }
}

function seed_sample_library_resources(): void {
    $pdo = get_db();
    $resources = [
        ['Guidance Journal Vol. 5', 'Weekly guidance journal for students and teachers.', '', 1, 1, null, null],
        ['School Library Policy', 'Library policies and borrowing guidelines for campus users.', '', 1, 1, null, null]
    ];
    $stmt = $pdo->prepare('INSERT INTO library_resources (title, description, file_url, uploaded_by, is_approved, approved_by, approved_at) VALUES (:title, :description, :file_url, :uploaded_by, :is_approved, :approved_by, :approved_at)');
    foreach ($resources as $resource) {
        [$title, $description, $fileUrl, $uploadedBy, $isApproved, $approvedBy, $approvedAt] = $resource;
        $stmt->execute([
            'title' => $title,
            'description' => $description,
            'file_url' => $fileUrl,
            'uploaded_by' => $uploadedBy,
            'is_approved' => $isApproved,
            'approved_by' => $approvedBy,
            'approved_at' => $approvedAt,
        ]);
    }
}

function find_active_library_visit(?string $studentNumber, ?string $employeeId, string $name): ?array {
    $pdo = get_db();
    $sql = 'SELECT * FROM library_visits WHERE time_out IS NULL AND (';
    $params = [];
    $clauses = [];
    if ($studentNumber) {
        $clauses[] = 'student_number = :student_number';
        $params['student_number'] = $studentNumber;
    }
    if ($employeeId) {
        $clauses[] = 'employee_id = :employee_id';
        $params['employee_id'] = $employeeId;
    }
    $clauses[] = 'name = :name';
    $params['name'] = $name;
    $sql .= implode(' OR ', $clauses) . ') ORDER BY time_in DESC LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function save_library_visit(array $data): bool {
    ensure_library_schema();
    $pdo = get_db();
    $defaults = [
        'status' => 'pending',
        'is_approved' => 0,
    ];
    $params = array_merge($defaults, $data);
    $stmt = $pdo->prepare('INSERT INTO library_visits (user_id, name, visitor_type, student_number, employee_id, course_department, purpose, checkin_method, time_in, status, is_approved) VALUES (:user_id, :name, :visitor_type, :student_number, :employee_id, :course_department, :purpose, :checkin_method, :time_in, :status, :is_approved)');
    return $stmt->execute($params);
}

function complete_library_visit(int $visitId, string $timeOut, int $durationMinutes): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE library_visits SET time_out = :time_out, duration_minutes = :duration_minutes, status = \'out\' WHERE id = :id');
    return $stmt->execute(['time_out' => $timeOut, 'duration_minutes' => $durationMinutes, 'id' => $visitId]);
}

function approve_library_visit(int $visitId, int $approverId): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE library_visits SET is_approved = 1, status = \'in\', approved_by = :approved_by, approved_at = NOW() WHERE id = :id');
    return $stmt->execute(['approved_by' => $approverId, 'id' => $visitId]);
}

function get_pending_library_visits(int $limit = 20): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM library_visits WHERE status = \'pending\' ORDER BY time_in ASC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function finalize_library_visits_at_end_of_day(string $closeTime = null): int {
    $pdo = get_db();
    if ($closeTime === null) {
        $closeTime = (new DateTime())->setTime(17, 0)->format('Y-m-d H:i:s');
    }
    $stmt = $pdo->prepare('UPDATE library_visits SET time_out = :time_out, duration_minutes = TIMESTAMPDIFF(MINUTE, time_in, :time_out2), status = \'out\' WHERE status = \'in\' AND time_out IS NULL AND DATE(time_in) = CURDATE()');
    $stmt->bindValue(':time_out', $closeTime);
    $stmt->bindValue(':time_out2', $closeTime);
    $stmt->execute();
    return $stmt->rowCount();
}

function get_today_library_checkins(): int {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM library_visits WHERE DATE(time_in) = CURDATE()');
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function get_active_library_reservations(): int {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM library_reservations WHERE status = \'Active\'');
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function create_library_notification(int $userId, ?int $reservationId, string $type, string $title, string $message): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT INTO library_notifications (user_id, reservation_id, type, title, message, is_read) VALUES (:user_id, :reservation_id, :type, :title, :message, 0)');
    return $stmt->execute([
        'user_id' => $userId,
        'reservation_id' => $reservationId,
        'type' => $type,
        'title' => $title,
        'message' => $message,
    ]);
}

function get_user_notifications(int $userId, int $limit = 10): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM library_notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll();
}

function get_unread_notifications_count(int $userId): int {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM library_notifications WHERE user_id = :user_id AND is_read = 0');
    $stmt->execute(['user_id' => $userId]);
    return (int)$stmt->fetchColumn();
}

function mark_notification_read(int $notificationId): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE library_notifications SET is_read = 1 WHERE id = :id');
    return $stmt->execute(['id' => $notificationId]);
}

function mark_all_notifications_read(int $userId): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE library_notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0');
    return $stmt->execute(['user_id' => $userId]);
}

function reserve_book_with_pickup(int $userId, int $bookId, string $pickupDate): bool {
    $pdo = get_db();
    $pdo->beginTransaction();
    try {
        $position = get_next_reservation_position($bookId);
        $expiresAt = (new DateTime())->modify('+' . ((int)get_library_setting('reservation_hold_days', 2)) . ' days')->format('Y-m-d H:i:s');
        $pickupUntil = (new DateTime($pickupDate . ' 17:00:00'))->format('Y-m-d H:i:s');
        
        // Decrement available copies when reserving
        $book = get_library_book_by_id($bookId);
        if (!$book || $book['available_copies'] <= 0) {
            $pdo->rollBack();
            return false;
        }
        $newCopies = max(0, $book['available_copies'] - 1);
        $newStatus = $newCopies === 0 ? 'Reserved' : 'Available';
        $updateBook = $pdo->prepare('UPDATE library_books SET available_copies = :available_copies, status = :status WHERE id = :id');
        $updateBook->execute(['available_copies' => $newCopies, 'status' => $newStatus, 'id' => $bookId]);
        
        $stmt = $pdo->prepare('INSERT INTO library_reservations (user_id, book_id, reserved_at, reserved_date, pickup_date, queue_position, status, expires_at, pickup_until) VALUES (:user_id, :book_id, :reserved_at, :reserved_date, :pickup_date, :queue_position, :status, :expires_at, :pickup_until)');
        $result = $stmt->execute([
            'user_id' => $userId,
            'book_id' => $bookId,
            'reserved_at' => (new DateTime())->format('Y-m-d H:i:s'),
            'reserved_date' => (new DateTime())->format('Y-m-d'),
            'pickup_date' => $pickupDate,
            'queue_position' => $position,
            'status' => 'Active',
            'expires_at' => $expiresAt,
            'pickup_until' => $pickupUntil,
        ]);
        
        if ($result) {
            $user = find_user_by_id($userId);
            $book = get_library_book_by_id($bookId);
            $reservationId = (int)$pdo->lastInsertId();
            if ($reservationId > 0) {
                create_library_notification($userId, $reservationId, 'reservation_ready', 'Reservation Confirmed', "You reserved '{$book['title']}'. Pick it up by " . (new DateTime($pickupDate))->format('M d, Y 5:00 PM'));
            } else {
                create_library_notification($userId, null, 'reservation_ready', 'Reservation Confirmed', "You reserved '{$book['title']}'. Pick it up by " . (new DateTime($pickupDate))->format('M d, Y 5:00 PM'));
            }
        }
        
        $pdo->commit();
        return $result;
    } catch (Exception $ex) {
        $pdo->rollBack();
        return false;
    }
}

function auto_expire_expired_reservations(): void {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM library_reservations WHERE status = \'Active\' AND pickup_until < NOW()');
    $stmt->execute();
    $expiredReservations = $stmt->fetchAll();
    
    foreach ($expiredReservations as $reservation) {
        $bookId = (int)$reservation['book_id'];
        
        // Restore available copies when reservation expires
        $book = get_library_book_by_id($bookId);
        if ($book) {
            $newCopies = (int)$book['available_copies'] + 1;
            $newStatus = $newCopies > 0 ? 'Available' : 'Borrowed';
            $updateBook = $pdo->prepare('UPDATE library_books SET available_copies = :available_copies, status = :status WHERE id = :id');
            $updateBook->execute(['available_copies' => $newCopies, 'status' => $newStatus, 'id' => $bookId]);
        }
        
        $update = $pdo->prepare('UPDATE library_reservations SET status = \'Cancelled\' WHERE id = :id');
        $update->execute(['id' => $reservation['id']]);
        
        create_library_notification(
            (int)$reservation['user_id'],
            (int)$reservation['id'],
            'reservation_expired',
            'Reservation Expired',
            'Your reservation for the book expired. It was not picked up by the deadline.'
        );
    }
}

function auto_cancel_unpicked_reservations(): void {
    // Auto-cancel unpicked reservations after 1 day
    auto_expire_expired_reservations();
}

function get_pending_count(): int {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM library_resources WHERE is_approved = 0');
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function get_overdue_library_items(): int {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM library_borrows WHERE status = \'Overdue\'');
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function get_recent_library_entries(int $limit = 5): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM library_visits ORDER BY time_in DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_active_library_visits(int $limit = 10): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM library_visits WHERE status = \'in\' AND time_out IS NULL ORDER BY time_in DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_library_visit_by_id(int $visitId): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM library_visits WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $visitId]);
    return $stmt->fetch() ?: null;
}

function get_library_books(int $limit = 6): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM library_books ORDER BY rating DESC, created_at DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_user_borrowed_books(int $userId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT b.id, b.book_id, lb.title, lb.author, lb.isbn, lb.category, lb.rating, lb.overview, lb.cover_url, lb.status, lb.available_copies, b.borrow_date, b.due_date, b.returned_at, CASE WHEN u.role = "teacher" AND b.status = "Overdue" THEN "Borrowed" ELSE b.status END AS borrow_status FROM library_borrows b JOIN library_books lb ON b.book_id = lb.id JOIN users u ON b.user_id = u.id WHERE b.user_id = :user_id ORDER BY b.borrow_date DESC LIMIT 10');
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll();
}

function get_user_reservations(int $userId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT lr.id, lr.book_id, lr.reserved_date, lr.queue_position, lr.status, lb.title FROM library_reservations lr JOIN library_books lb ON lr.book_id = lb.id WHERE lr.user_id = :user_id AND lr.status = "Active" ORDER BY lr.reserved_at DESC LIMIT 10');
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll();
}

function get_user_cancelled_reservations(int $userId, int $limit = 10): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT lr.id, lr.book_id, lr.reserved_date, lr.queue_position, lr.status, lr.additional_info, lb.title FROM library_reservations lr JOIN library_books lb ON lr.book_id = lb.id WHERE lr.user_id = :user_id AND lr.status = "Cancelled" ORDER BY lr.reserved_at DESC LIMIT :limit');
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_library_resources(bool $approvedOnly = true, int $limit = 6): array {
    $pdo = get_db();
    if ($approvedOnly) {
        $stmt = $pdo->prepare('SELECT lr.*, u.full_name as uploader_name FROM library_resources lr LEFT JOIN users u ON lr.uploaded_by = u.id WHERE lr.is_approved = 1 ORDER BY lr.created_at DESC LIMIT :limit');
    } else {
        $stmt = $pdo->prepare('SELECT lr.*, u.full_name as uploader_name FROM library_resources lr LEFT JOIN users u ON lr.uploaded_by = u.id ORDER BY lr.created_at DESC LIMIT :limit');
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function upload_library_resource(int $userId, string $title, string $description, string $filePath, string $fileName, int $fileSize, string $fileType): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT INTO library_resources (title, description, file_url, file_name, file_size, file_type, uploaded_by, is_approved, created_at) VALUES (:title, :description, :file_url, :file_name, :file_size, :file_type, :uploaded_by, 0, NOW())');
    return $stmt->execute([
        'title' => $title,
        'description' => $description,
        'file_url' => $filePath,
        'file_name' => $fileName,
        'file_size' => $fileSize,
        'file_type' => $fileType,
        'uploaded_by' => $userId,
    ]);
}

function get_pending_resource_approvals(): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT lr.*, u.full_name AS uploader_name, COALESCE(u.course_year, "Unknown") AS uploader_course FROM library_resources lr LEFT JOIN users u ON lr.uploaded_by = u.id WHERE lr.is_approved = 0 ORDER BY lr.created_at ASC');
    $stmt->execute();
    return $stmt->fetchAll();
}

function approve_library_resource(int $resourceId, int $approverId, string $category, string $courseYear = ''): bool {
    $pdo = get_db();
    // Convert empty string to NULL for course_year to ensure only matching or general (NULL) resources show
    $courseYearValue = !empty(trim($courseYear)) ? $courseYear : null;
    $stmt = $pdo->prepare('UPDATE library_resources SET is_approved = 1, approved_by = :approved_by, approved_at = NOW(), category = :category, course_year = :course_year WHERE id = :id');
    return $stmt->execute([
        'approved_by' => $approverId,
        'category' => $category,
        'course_year' => $courseYearValue,
        'id' => $resourceId,
    ]);
}

function reject_library_resource(int $resourceId): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('DELETE FROM library_resources WHERE id = :id AND is_approved = 0');
    return $stmt->execute(['id' => $resourceId]);
}

function get_library_resources_by_category(string $category, string $courseYear = ''): array {
    $pdo = get_db();
    if (!empty($courseYear)) {
        $stmt = $pdo->prepare('SELECT * FROM library_resources WHERE is_approved = 1 AND category = :category AND course_year = :course_year ORDER BY created_at DESC');
        $stmt->execute(['category' => $category, 'course_year' => $courseYear]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM library_resources WHERE is_approved = 1 AND category = :category ORDER BY created_at DESC');
        $stmt->execute(['category' => $category]);
    }
    return $stmt->fetchAll();
}

function get_all_resource_categories(string $courseYear = ''): array {
    $pdo = get_db();
    if (trim($courseYear) === '') {
        return [];
    }
    $stmt = $pdo->prepare('SELECT DISTINCT category FROM library_resources WHERE is_approved = 1 AND category IS NOT NULL AND course_year = :course_year ORDER BY category ASC');
    $stmt->execute(['course_year' => $courseYear]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function increment_resource_download(int $resourceId): void {
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE library_resources SET download_count = download_count + 1 WHERE id = :id');
    $stmt->execute(['id' => $resourceId]);
}

function get_library_resource_by_id(int $resourceId): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM library_resources WHERE id = :id');
    $stmt->execute(['id' => $resourceId]);
    return $stmt->fetch() ?: null;
}

function get_user_uploaded_resources(int $userId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM library_resources WHERE uploaded_by = :user_id ORDER BY created_at DESC');
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll();
}

function get_user_active_visit(int $userId): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM library_visits WHERE user_id = :user_id AND time_out IS NULL ORDER BY time_in DESC LIMIT 1');
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetch() ?: null;
}

function get_library_calendar_blocks(int $limit = 10): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM library_calendar ORDER BY event_date DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_library_calendar_images_by_calendar_ids(array $calendarIds): array {
    if (empty($calendarIds)) {
        return [];
    }
    $pdo = get_db();
    $placeholders = implode(',', array_fill(0, count($calendarIds), '?'));
    $stmt = $pdo->prepare("SELECT calendar_id, image_path FROM library_calendar_images WHERE calendar_id IN ($placeholders) ORDER BY calendar_id, id ASC");
    $stmt->execute($calendarIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $images = [];
    foreach ($rows as $row) {
        $images[$row['calendar_id']][] = $row['image_path'];
    }
    return $images;
}

function add_library_calendar_block(string $eventDate, string $eventType, ?string $description, ?string $startTime = null, ?string $endTime = null): int|false {
    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT INTO library_calendar (event_date, event_type, description, start_time, end_time) VALUES (:event_date, :event_type, :description, :start_time, :end_time)');
    if ($stmt->execute([
        'event_date' => $eventDate,
        'event_type' => $eventType,
        'description' => $description,
        'start_time' => $startTime,
        'end_time' => $endTime,
    ])) {
        return (int)$pdo->lastInsertId();
    }
    return false;
}

function upload_library_calendar_images(int $calendarId, array $files): bool {
    if (empty($files['name']) || !is_array($files['name'])) {
        return true;
    }

    $allowedExtensions = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
    $uploadDir = __DIR__ . '/../IMG ASSETS/calendar_announcement_images';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return false;
        }
    }

    $pdo = get_db();
    $stmt = $pdo->prepare('INSERT INTO library_calendar_images (calendar_id, image_path) VALUES (:calendar_id, :image_path)');
    $saved = true;

    foreach ($files['name'] as $index => $originalName) {
        if (!isset($files['error'][$index]) || $files['error'][$index] !== UPLOAD_ERR_OK) {
            continue;
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            continue;
        }

        $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $uniqueName = $baseName . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $destination = $uploadDir . '/' . $uniqueName;

        if (!move_uploaded_file($files['tmp_name'][$index], $destination)) {
            $saved = false;
            continue;
        }

        $relativePath = 'IMG ASSETS/calendar_announcement_images/' . $uniqueName;
        if (!$stmt->execute(['calendar_id' => $calendarId, 'image_path' => $relativePath])) {
            $saved = false;
        }
    }

    return $saved;
}

function update_library_calendar_block(int $blockId, string $eventDate, string $eventType, ?string $description, ?string $startTime = null, ?string $endTime = null): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE library_calendar SET event_date = :event_date, event_type = :event_type, description = :description, start_time = :start_time, end_time = :end_time WHERE id = :id');
    return $stmt->execute([
        'id' => $blockId,
        'event_date' => $eventDate,
        'event_type' => $eventType,
        'description' => $description,
        'start_time' => $startTime,
        'end_time' => $endTime,
    ]);
}

function get_upcoming_library_calendar_blocks(int $limit = 10): array {
    $pdo = get_db();
    $today = (new DateTime())->format('Y-m-d');
    $now = (new DateTime())->format('H:i:s');
    $limit = max(1, $limit);

    $sql = sprintf(
        'SELECT * FROM library_calendar
         WHERE event_date > :today1
            OR (event_date = :today2 AND (end_time IS NULL OR end_time >= :now))
         ORDER BY event_date ASC
         LIMIT %d',
        $limit
    );

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':today1', $today, PDO::PARAM_STR);
    $stmt->bindValue(':today2', $today, PDO::PARAM_STR);
    $stmt->bindValue(':now', $now, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetchAll();
}

function remove_library_calendar_block(int $blockId): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('DELETE FROM library_calendar WHERE id = :id');
    return $stmt->execute(['id' => $blockId]);
}
function get_all_active_borrows(): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT b.id, b.user_id, b.book_id, b.borrow_date, b.due_date, b.returned_at, b.status, lb.title, u.full_name, u.student_id FROM library_borrows b JOIN library_books lb ON b.book_id = lb.id JOIN users u ON b.user_id = u.id WHERE b.status IN (\'Borrowed\', \'Overdue\') ORDER BY b.borrow_date DESC');
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_student_borrow_history(int $userId, int $limit = 50): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT b.id, b.book_id, b.borrow_date, b.due_date, b.returned_at, b.status, b.condition_status, lb.title FROM library_borrows b JOIN library_books lb ON b.book_id = lb.id WHERE b.user_id = :user_id ORDER BY b.borrow_date DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll();
}

function search_student_by_name_or_id(string $query, int $limit = 20): array {
    $pdo = get_db();
    $searchTerm = '%' . $query . '%';
    $stmt = $pdo->prepare('SELECT id, full_name, student_id, email, course_year FROM users WHERE (full_name LIKE :query OR student_id LIKE :query) AND role IN (\'student\', \'teacher\') LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute(['query' => $searchTerm]);
    return $stmt->fetchAll();
}

function mark_book_returned(int $borrowId, string $returnedAt, string $condition = 'Good'): bool {
    $pdo = get_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM library_borrows WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $borrowId]);
        $borrow = $stmt->fetch();
        
        if (!$borrow || $borrow['status'] === 'Returned') {
            $pdo->rollBack();
            return false;
        }
        
        $book = get_library_book_by_id((int)$borrow['book_id']);
        $newCopies = max(0, (int)$book['available_copies'] + 1);
        $newStatus = $newCopies > 0 ? 'Available' : 'Borrowed';
        
        $updateBorrow = $pdo->prepare('UPDATE library_borrows SET returned_at = :returned_at, status = \'Returned\', condition_status = :condition_status WHERE id = :id');
        $updateBorrow->execute(['returned_at' => $returnedAt, 'condition_status' => $condition, 'id' => $borrowId]);
        
        $updateBook = $pdo->prepare('UPDATE library_books SET available_copies = :available_copies, status = :status WHERE id = :id');
        $updateBook->execute(['available_copies' => $newCopies, 'status' => $newStatus, 'id' => $borrow['book_id']]);
        
        process_reservation_queue((int)$borrow['book_id']);
        $pdo->commit();
        return true;
    } catch (Exception $ex) {
        $pdo->rollBack();
        return false;
    }
}

function get_overdue_borrows(int $limit = 50): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT b.id, b.user_id, b.book_id, b.borrow_date, b.due_date, b.status, lb.title, u.full_name, u.student_id, DATEDIFF(CURDATE(), DATE(b.due_date)) AS days_overdue FROM library_borrows b JOIN library_books lb ON b.book_id = lb.id JOIN users u ON b.user_id = u.id WHERE b.status = \'Overdue\' ORDER BY b.due_date ASC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_student_fines(int $userId): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT f.id, f.borrow_id, f.amount, f.paid, f.description, f.created_at, b.book_id, b.due_date, lb.title FROM library_fines f JOIN library_borrows b ON f.borrow_id = b.id JOIN library_books lb ON b.book_id = lb.id WHERE f.user_id = :user_id ORDER BY f.created_at DESC');
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll();
}

function get_all_unpaid_fines(int $limit = 100): array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT f.id, f.user_id, f.amount, f.paid, f.description, f.created_at, u.full_name, u.student_id FROM library_fines f JOIN users u ON f.user_id = u.id WHERE f.paid = 0 ORDER BY f.created_at DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_student_total_unpaid_fines(int $userId): float {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM library_fines WHERE user_id = :user_id AND paid = 0');
    $stmt->execute(['user_id' => $userId]);
    return (float)$stmt->fetchColumn();
}

function mark_fine_paid(int $fineId): bool {
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE library_fines SET paid = 1 WHERE id = :id');
    return $stmt->execute(['id' => $fineId]);
}

function can_student_get_clearance(int $userId): array {
    $unpaidFines = get_student_total_unpaid_fines($userId);
    $overdueCount = 0;
    
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM library_borrows WHERE user_id = :user_id AND status = \'Overdue\'');
    $stmt->execute(['user_id' => $userId]);
    $overdueCount = (int)$stmt->fetchColumn();
    
    $canClear = ($unpaidFines == 0 && $overdueCount == 0);
    
    return [
        'can_clear' => $canClear,
        'unpaid_fines' => $unpaidFines,
        'overdue_count' => $overdueCount,
        'reason' => $unpaidFines > 0 ? 'Unpaid fines: ₱' . number_format($unpaidFines, 2) : ($overdueCount > 0 ? 'Overdue items: ' . $overdueCount . ' book(s)' : ''),
    ];
}

function is_graduation_event_due(): bool {
    $pdo = get_db();
    $today = (new DateTime())->format('Y-m-d');
    $now = (new DateTime())->format('H:i:s');

    $stmt = $pdo->prepare(
        'SELECT 1 FROM library_calendar ' .
        'WHERE LOWER(event_type) = :event_type AND (' .
            'event_date < :today1 OR ' .
            '(event_date = :today2 AND (' .
                '(end_time IS NOT NULL AND end_time <= :now1) OR ' .
                '(end_time IS NULL AND start_time IS NOT NULL AND start_time <= :now2) OR ' .
                '(start_time IS NULL AND end_time IS NULL)' .
            '))' .
        ') LIMIT 1'
    );
    $stmt->execute([
        'event_type' => 'graduation',
        'today1' => $today,
        'today2' => $today,
        'now1' => $now,
        'now2' => $now,
    ]);

    return (bool) $stmt->fetchColumn();
}

function process_graduating_students(): void {
    $graduationTriggered = is_graduation_event_due();

    if (!$graduationTriggered) {
        $graduationDate = get_library_setting('graduation_date', '');
        $graduationTime = get_library_setting('graduation_time', '');
        if (!empty($graduationDate) && !empty($graduationTime)) {
            $graduationDateTime = new DateTime($graduationDate . ' ' . $graduationTime);
            if (new DateTime() >= $graduationDateTime) {
                $graduationTriggered = true;
            }
        }
    }

    if (!$graduationTriggered) {
        return;
    }
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT DISTINCT u.id
         FROM users u
         LEFT JOIN ssaa_student_progression sp ON u.id = sp.user_id
         WHERE u.role = :student_role
           AND u.enrollment_status != :graduated_status_1
           AND (
               sp.enrollment_status = :graduating_status
               OR (LOWER(u.course_year) LIKE :graduate_marker AND (sp.enrollment_status IS NULL OR sp.enrollment_status != :graduated_status_2))
           )'
    );
    $stmt->execute([
        'student_role' => 'student',
        'graduated_status_1' => 'graduated',
        'graduated_status_2' => 'graduated',
        'graduating_status' => 'graduating',
        'graduate_marker' => '%graduate',
    ]);
    $graduatingStudents = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($graduatingStudents as $userId) {
        // Update to graduated
        $stmt = $pdo->prepare('UPDATE ssaa_student_progression SET enrollment_status = ?, graduation_date = CURDATE(), updated_at = NOW() WHERE user_id = ?');
        $stmt->execute(['graduated', $userId]);
        $stmt = $pdo->prepare('UPDATE users SET enrollment_status = ?, account_deactivation_date = CURDATE(), deactivation_reason = ? WHERE id = ?');
        $stmt->execute(['graduated', 'Graduated from program', $userId]);
        // Add to alumni
        $stmt = $pdo->prepare(
            'INSERT INTO ssaa_alumni (user_id, full_name, email, student_id, course, graduation_year, created_at)
             SELECT u.id, u.full_name, u.email, u.student_id, u.course_year, YEAR(CURDATE()), NOW()
             FROM users u
             WHERE u.id = ?
               AND NOT EXISTS (
                   SELECT 1 FROM ssaa_alumni a
                   WHERE a.user_id = ?
                      OR (a.student_id IS NOT NULL AND a.student_id = u.student_id)
               )'
        );
        $stmt->execute([$userId, $userId]);
    }
}

function find_user_by_student_id(string $studentId): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE student_id = ? LIMIT 1');
    $stmt->execute([$studentId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user === false ? null : $user;
}

// Diagnostic Term Blocking for Observation Validation
function validate_observation_text(string $text): array {
    $blockedTerms = [
        'depression', 'depressed', 'psychosis', 'psychotic', 'schizophrenia', 'schizophrenic',
        'bipolar', 'autism', 'adhd', 'ocd', 'ptsd', 'anxiety disorder', 'paranoia', 'paranoid',
        'mental illness', 'insanity', 'retardation', 'retarded', 'psychopath', 'sociopath',
        'dissociative', 'dissociation', 'delusional', 'delusion', 'hallucination', 'hallucinating',
        'suicidal', 'suicide', 'catatonic', 'catatonia', 'neurotic', 'neurosis', 'hysteria',
        'personality disorder', 'psyche disorder', 'mental disorder', 'emotional disorder',
        'intellectual disability', 'learning disability', 'behavioral disorder', 'conduct disorder'
    ];

    $lowerText = strtolower($text);
    $foundTerms = [];

    foreach ($blockedTerms as $term) {
        if (strpos($lowerText, $term) !== false) {
            $foundTerms[] = $term;
        }
    }

    if (!empty($foundTerms)) {
        return [
            'valid' => false,
            'message' => 'Observation contains blocked diagnostic terms: ' . implode(', ', array_unique($foundTerms)) . '. Use neutral language instead (e.g., "student reports emotional distress", "shows signs of low motivation").',
            'blockedTerms' => array_unique($foundTerms)
        ];
    }

    return [
        'valid' => true,
        'message' => ''
    ];
}

// Allowed Neutral Observation Phrases (Reference)
function get_neutral_observation_phrases(): array {
    return [
        'Student reports feeling stressed',
        'Shows signs of emotional distress',
        'Demonstrates low motivation',
        'Difficulty concentrating observed',
        'Behavioral concern noted',
        'Appears withdrawn in class',
        'Expresses frustration with coursework',
        'Displays difficulty managing time',
        'Has expressed concerns about peer relationships',
        'Recommended for professional evaluation',
        'Behavioral concern – without reformation'
    ];
}