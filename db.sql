CREATE DATABASE IF NOT EXISTS support_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE support_system;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    username VARCHAR(100) DEFAULT NULL,
    role ENUM('student','teacher','admin') NOT NULL,
    course_year VARCHAR(60) DEFAULT NULL,
    student_id VARCHAR(50) DEFAULT NULL,
    employee_id VARCHAR(50) DEFAULT NULL,
    is_head TINYINT(1) NOT NULL DEFAULT 0,
    head_service ENUM('none','library','clinic','ssc_scholarship','guidance') NOT NULL DEFAULT 'none',
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    verification_code VARCHAR(10) DEFAULT NULL,
    verification_expires DATETIME DEFAULT NULL,
    reset_code VARCHAR(10) DEFAULT NULL,
    reset_expires DATETIME DEFAULT NULL,
    failed_attempts INT NOT NULL DEFAULT 0,
    lockout_until DATETIME DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    admin_type ENUM('master','regular') DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO users (full_name, email, role, password_hash, email_verified, admin_type)
SELECT 'Master Admin', 'admin@supportsystem.local', 'admin', '$2y$10$.GgpahluCPnt8YD9G0U0T.K443TqRt1wM/dEfnJAPuhVRT4zNpFKO', 1, 'master'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@supportsystem.local');

-- SSC Events Table
CREATE TABLE IF NOT EXISTS ssc_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE DEFAULT NULL,
    event_time TIME,
    location VARCHAR(255),
    organizer VARCHAR(150),
    photo_url VARCHAR(255),
    status ENUM('upcoming','ongoing','completed','cancelled') NOT NULL DEFAULT 'upcoming',
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- SSC Candidates Table
CREATE TABLE IF NOT EXISTS ssc_candidates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    position VARCHAR(100) NOT NULL,
    course_year VARCHAR(100),
    platform TEXT,
    photo_url VARCHAR(255),
    status ENUM('active','withdrawn','elected','advicer') NOT NULL DEFAULT 'active',
    officer_year VARCHAR(20) DEFAULT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- SSC Initiatives Table
CREATE TABLE IF NOT EXISTS ssc_initiatives (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    status ENUM('planning','active','completed','cancelled') NOT NULL DEFAULT 'planning',
    start_date DATE,
    end_date DATE,
    participants_count INT DEFAULT 0,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- SSC Officers Table
CREATE TABLE IF NOT EXISTS ssc_officers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    position VARCHAR(150) NOT NULL,
    officer_name VARCHAR(150) NOT NULL,
    order_sequence INT DEFAULT 0,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_position (position),
    KEY idx_order (order_sequence)
);

-- Scholarship Announcements Table
CREATE TABLE IF NOT EXISTS scholarship_announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    program_type ENUM('TDP','TES','financial_aid','other') DEFAULT NULL,
    deadline DATE DEFAULT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Scholarship Announcement Images Table
CREATE TABLE IF NOT EXISTS scholarship_announcement_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (announcement_id) REFERENCES scholarship_announcements(id) ON DELETE CASCADE
);
);

-- Scholarship Applications Table
CREATE TABLE IF NOT EXISTS scholarship_applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    announcement_id INT UNSIGNED NOT NULL,
    status ENUM('ready','pending','approved','rejected') NOT NULL DEFAULT 'ready',
    comments TEXT,
    ready_timestamp DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (announcement_id) REFERENCES scholarship_announcements(id) ON DELETE CASCADE,
    UNIQUE KEY unique_application (student_id, announcement_id)
);

-- Scholarship Updates Table
CREATE TABLE IF NOT EXISTS scholarship_updates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    update_type ENUM('deadline','program_info','financial_assistance','general') NOT NULL DEFAULT 'general',
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Guidance Case Management Tables
CREATE TABLE IF NOT EXISTS guidance_cases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_number VARCHAR(50) NOT NULL UNIQUE,
    reported_student_id INT UNSIGNED NULL,
    reporter_user_id INT UNSIGNED DEFAULT NULL,
    reporter_role ENUM('student','teacher') NOT NULL,
    report_type ENUM('student_report','teacher_report','self_request','other') NOT NULL DEFAULT 'student_report',
    type_of_concern VARCHAR(100) NOT NULL,
    course_year VARCHAR(100) DEFAULT NULL,
    incident_description TEXT NOT NULL,
    parent_guardian_notified TINYINT(1) NOT NULL DEFAULT 0,
    teacher_awareness_required TINYINT(1) NOT NULL DEFAULT 0,
    priority_level ENUM('Low','Medium','High','Emergency') NOT NULL DEFAULT 'Medium',
    status ENUM('pending_review','under_counseling','returned_for_clarification','awaiting_session','closed') NOT NULL DEFAULT 'pending_review',
    external_resolution ENUM('none','counseling_completed_follow_up','advised_behavioral_improvement','under_monitoring','case_resolved','scheduled_for_further_sessions') NOT NULL DEFAULT 'none',
    internal_resolution ENUM('none','with_reformation','without_reformation','with_warning','under_monitoring','escalated_case','ongoing_case','closed_case') NOT NULL DEFAULT 'none',
    internal_notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reported_student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS case_reporters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    reporter_type ENUM('student','teacher','parent_guardian','course','other','proactive') NOT NULL DEFAULT 'student',
    reporter_name VARCHAR(150) DEFAULT NULL,
    reporter_id VARCHAR(50) DEFAULT NULL,
    reporter_course VARCHAR(100) DEFAULT NULL,
    mobile_number VARCHAR(20) DEFAULT NULL,
    custom_reporter_type VARCHAR(100) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES guidance_cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS case_reported_persons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    person_type ENUM('student','teacher','course','other') NOT NULL DEFAULT 'student',
    person_name VARCHAR(150) DEFAULT NULL,
    person_id VARCHAR(50) DEFAULT NULL,
    person_course VARCHAR(100) DEFAULT NULL,
    custom_person_type VARCHAR(100) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES guidance_cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS guidance_case_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED DEFAULT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES guidance_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS guidance_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    counselor_id INT UNSIGNED DEFAULT NULL,
    session_date DATETIME NOT NULL,
    session_category ENUM('under_guidance_counseling','parent_guardian_conference','teacher_awareness','professional_evaluation','follow_up_session','behavioral_monitoring','conflict_mediation','academic_support_referral') NOT NULL DEFAULT 'under_guidance_counseling',
    participation_scope ENUM('student_only','with_parent_guardian','with_teacher_awareness') NOT NULL DEFAULT 'student_only',
    attendance_status ENUM('attended','non_appearance','pending') NOT NULL DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES guidance_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (counselor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS guidance_observations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    observer_user_id INT UNSIGNED DEFAULT NULL,
    observer_role ENUM('teacher','student') NOT NULL,
    observation TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES guidance_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (observer_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

