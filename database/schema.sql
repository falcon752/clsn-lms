-- ============================================================
-- CLSN LMS DATABASE SCHEMA
-- Candlelight Foundation Learning Management System
-- ============================================================

CREATE DATABASE IF NOT EXISTS `clsn_lms` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `clsn_lms`;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `lms_users` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `first_name`  VARCHAR(100) NOT NULL,
    `last_name`   VARCHAR(100) NOT NULL,
    `email`       VARCHAR(255) NOT NULL UNIQUE,
    `password`    VARCHAR(255) NOT NULL,
    `role`        ENUM('student','admin') DEFAULT 'student',
    `is_active`   TINYINT(1) DEFAULT 1,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- COURSES
-- ============================================================
CREATE TABLE IF NOT EXISTS `lms_courses` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `title`             VARCHAR(255) NOT NULL,
    `slug`              VARCHAR(255) NOT NULL UNIQUE,
    `short_description` VARCHAR(600),
    `description`       TEXT,
    `thumbnail`         VARCHAR(255) DEFAULT NULL,
    `instructor`        VARCHAR(255) DEFAULT 'Candlelight Foundation',
    `total_modules`     INT DEFAULT 0,
    `duration`          VARCHAR(100) DEFAULT '8 Weeks',
    `level`             ENUM('Beginner','Intermediate','Advanced') DEFAULT 'Beginner',
    `is_active`         TINYINT(1) DEFAULT 1,
    `is_free`           TINYINT(1) DEFAULT 1,
    `price`             DECIMAL(10,2) DEFAULT 0.00,
    `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- MODULES
-- ============================================================
CREATE TABLE IF NOT EXISTS `lms_modules` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `course_id`        INT NOT NULL,
    `module_number`    INT NOT NULL,
    `title`            VARCHAR(255) NOT NULL,
    `description`      TEXT,
    `video_type`       ENUM('youtube','file','vimeo') DEFAULT 'youtube',
    `video_url`        VARCHAR(1000) DEFAULT NULL,
    `notes`            LONGTEXT,
    `duration_minutes` INT DEFAULT 30,
    `is_active`        TINYINT(1) DEFAULT 1,
    `sort_order`       INT DEFAULT 0,
    `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`course_id`) REFERENCES `lms_courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- MODULE PDFs
-- ============================================================
CREATE TABLE IF NOT EXISTS `lms_module_pdfs` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `module_id`  INT NOT NULL,
    `title`      VARCHAR(255) NOT NULL,
    `file_path`  VARCHAR(500) NOT NULL,
    `file_size`  VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`module_id`) REFERENCES `lms_modules`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- QUIZZES (one per module)
-- ============================================================
CREATE TABLE IF NOT EXISTS `lms_quizzes` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `module_id`       INT NOT NULL UNIQUE,
    `title`           VARCHAR(255) NOT NULL,
    `pass_percentage` INT DEFAULT 70,
    `max_attempts`    INT DEFAULT 3,
    `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`module_id`) REFERENCES `lms_modules`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- QUIZ QUESTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `lms_quiz_questions` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `quiz_id`       INT NOT NULL,
    `question_text` TEXT NOT NULL,
    `sort_order`    INT DEFAULT 0,
    FOREIGN KEY (`quiz_id`) REFERENCES `lms_quizzes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- QUIZ OPTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `lms_quiz_options` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `question_id` INT NOT NULL,
    `option_text` TEXT NOT NULL,
    `is_correct`  TINYINT(1) DEFAULT 0,
    `sort_order`  INT DEFAULT 0,
    FOREIGN KEY (`question_id`) REFERENCES `lms_quiz_questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- ENROLLMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `lms_enrollments` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`      INT NOT NULL,
    `course_id`    INT NOT NULL,
    `enrolled_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY `uq_enrollment` (`user_id`,`course_id`),
    FOREIGN KEY (`user_id`)   REFERENCES `lms_users`(`id`)   ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `lms_courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- MODULE PROGRESS
-- ============================================================
CREATE TABLE IF NOT EXISTS `lms_module_progress` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`       INT NOT NULL,
    `module_id`     INT NOT NULL,
    `course_id`     INT NOT NULL,
    `video_watched` TINYINT(1) DEFAULT 0,
    `quiz_passed`   TINYINT(1) DEFAULT 0,
    `is_completed`  TINYINT(1) DEFAULT 0,
    `completed_at`  TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY `uq_progress` (`user_id`,`module_id`),
    FOREIGN KEY (`user_id`)   REFERENCES `lms_users`(`id`)   ON DELETE CASCADE,
    FOREIGN KEY (`module_id`) REFERENCES `lms_modules`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- QUIZ ATTEMPTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `lms_quiz_attempts` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`         INT NOT NULL,
    `quiz_id`         INT NOT NULL,
    `module_id`       INT NOT NULL,
    `score`           INT NOT NULL DEFAULT 0,
    `total_questions` INT NOT NULL,
    `percentage`      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `passed`          TINYINT(1) DEFAULT 0,
    `attempted_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `lms_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`quiz_id`) REFERENCES `lms_quizzes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- CERTIFICATES
-- ============================================================
CREATE TABLE IF NOT EXISTS `lms_certificates` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`         INT NOT NULL,
    `course_id`       INT NOT NULL,
    `certificate_uid` VARCHAR(64) NOT NULL UNIQUE,
    `issued_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_certificate` (`user_id`,`course_id`),
    FOREIGN KEY (`user_id`)   REFERENCES `lms_users`(`id`)   ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `lms_courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- MODULE Q&A
-- ============================================================
CREATE TABLE IF NOT EXISTS `lms_module_qa` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `module_id`   INT NOT NULL,
    `user_id`     INT NOT NULL,
    `question`    TEXT NOT NULL,
    `answer`      TEXT DEFAULT NULL,
    `is_answered` TINYINT(1) DEFAULT 0,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `answered_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`module_id`) REFERENCES `lms_modules`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)   REFERENCES `lms_users`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
