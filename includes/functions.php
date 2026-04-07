<?php
// ─── Input Sanitization ────────────────────────────────────────────────────────

function clean(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

function validEmail(string $email): bool {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

// ─── Course Helpers ────────────────────────────────────────────────────────────

function getCourse(mysqli $conn, int $id): ?array {
    $s = $conn->prepare("SELECT * FROM lms_courses WHERE id = ? AND is_active = 1");
    $s->bind_param('i', $id);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $s->close();
    return $r ?: null;
}

function getCourseBySlug(mysqli $conn, string $slug): ?array {
    $s = $conn->prepare("SELECT * FROM lms_courses WHERE slug = ? AND is_active = 1");
    $s->bind_param('s', $slug);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $s->close();
    return $r ?: null;
}

function getActiveCourses(mysqli $conn): array {
    $r = $conn->query("SELECT * FROM lms_courses WHERE is_active = 1 ORDER BY id ASC");
    return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}

function getCourseModules(mysqli $conn, int $courseId): array {
    $s = $conn->prepare("SELECT * FROM lms_modules WHERE course_id = ? AND is_active = 1 ORDER BY sort_order ASC");
    $s->bind_param('i', $courseId);
    $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    return $rows;
}

function getModule(mysqli $conn, int $id): ?array {
    $s = $conn->prepare("SELECT m.*, c.title AS course_title, c.slug AS course_slug, c.id AS course_id FROM lms_modules m JOIN lms_courses c ON c.id = m.course_id WHERE m.id = ? AND m.is_active = 1");
    $s->bind_param('i', $id);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $s->close();
    return $r ?: null;
}

function getModulePdfs(mysqli $conn, int $moduleId): array {
    $s = $conn->prepare("SELECT * FROM lms_module_pdfs WHERE module_id = ? ORDER BY id ASC");
    $s->bind_param('i', $moduleId);
    $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    return $rows;
}

// ─── Enrollment Helpers ────────────────────────────────────────────────────────

function isEnrolled(mysqli $conn, int $userId, int $courseId): bool {
    $s = $conn->prepare("SELECT id FROM lms_enrollments WHERE user_id = ? AND course_id = ?");
    $s->bind_param('ii', $userId, $courseId);
    $s->execute();
    $s->store_result();
    $found = $s->num_rows > 0;
    $s->close();
    return $found;
}

function enrollUser(mysqli $conn, int $userId, int $courseId): bool {
    $s = $conn->prepare("INSERT IGNORE INTO lms_enrollments (user_id, course_id) VALUES (?,?)");
    $s->bind_param('ii', $userId, $courseId);
    $ok = $s->execute();
    $s->close();
    return $ok;
}

function getUserEnrollments(mysqli $conn, int $userId): array {
    $s = $conn->prepare("
        SELECT e.*, c.title, c.slug, c.thumbnail, c.youtube_url, c.total_modules, c.duration
        FROM lms_enrollments e
        JOIN lms_courses c ON c.id = e.course_id
        WHERE e.user_id = ? AND c.is_active = 1
        ORDER BY e.enrolled_at DESC
    ");
    $s->bind_param('i', $userId);
    $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    return $rows;
}

// ─── Progress Helpers ──────────────────────────────────────────────────────────

function getModuleProgress(mysqli $conn, int $userId, int $moduleId): array {
    $s = $conn->prepare("SELECT * FROM lms_module_progress WHERE user_id = ? AND module_id = ?");
    $s->bind_param('ii', $userId, $moduleId);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $s->close();
    return $r ?: ['video_watched' => 0, 'quiz_passed' => 0, 'is_completed' => 0];
}

function getCourseProgress(mysqli $conn, int $userId, int $courseId): array {
    // Returns ['completed' => N, 'total' => N, 'percent' => N]
    $total = 0;
    $s     = $conn->prepare("SELECT COUNT(*) AS cnt FROM lms_modules WHERE course_id = ? AND is_active = 1");
    $s->bind_param('i', $courseId);
    $s->execute();
    $total = (int)$s->get_result()->fetch_assoc()['cnt'];
    $s->close();

    $s = $conn->prepare("SELECT COUNT(*) AS cnt FROM lms_module_progress WHERE user_id = ? AND course_id = ? AND is_completed = 1");
    $s->bind_param('ii', $userId, $courseId);
    $s->execute();
    $completed = (int)$s->get_result()->fetch_assoc()['cnt'];
    $s->close();

    $percent = $total > 0 ? round(($completed / $total) * 100) : 0;
    return ['completed' => $completed, 'total' => $total, 'percent' => $percent];
}

function markVideoWatched(mysqli $conn, int $userId, int $moduleId, int $courseId): void {
    $s = $conn->prepare("
        INSERT INTO lms_module_progress (user_id, module_id, course_id, video_watched)
        VALUES (?,?,?,1)
        ON DUPLICATE KEY UPDATE video_watched = 1
    ");
    $s->bind_param('iii', $userId, $moduleId, $courseId);
    $s->execute();
    $s->close();
}

function markModuleComplete(mysqli $conn, int $userId, int $moduleId, int $courseId): void {
    $now = date('Y-m-d H:i:s');
    $s   = $conn->prepare("
        INSERT INTO lms_module_progress (user_id, module_id, course_id, is_completed, completed_at)
        VALUES (?,?,?,1,?)
        ON DUPLICATE KEY UPDATE is_completed = 1, completed_at = ?
    ");
    $s->bind_param('iiiss', $userId, $moduleId, $courseId, $now, $now);
    $s->execute();
    $s->close();
}

// ─── Quiz Helpers ──────────────────────────────────────────────────────────────

function getQuizByModule(mysqli $conn, int $moduleId): ?array {
    $s = $conn->prepare("SELECT * FROM lms_quizzes WHERE module_id = ?");
    $s->bind_param('i', $moduleId);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $s->close();
    return $r ?: null;
}

function getQuizQuestions(mysqli $conn, int $quizId): array {
    $s = $conn->prepare("
        SELECT q.*, GROUP_CONCAT(o.id ORDER BY o.sort_order SEPARATOR '|') AS opt_ids,
               GROUP_CONCAT(o.option_text ORDER BY o.sort_order SEPARATOR '|||') AS opt_texts,
               GROUP_CONCAT(o.is_correct ORDER BY o.sort_order SEPARATOR '|') AS opt_correct
        FROM lms_quiz_questions q
        JOIN lms_quiz_options o ON o.question_id = q.id
        WHERE q.quiz_id = ?
        GROUP BY q.id
        ORDER BY q.sort_order ASC
    ");
    $s->bind_param('i', $quizId);
    $s->execute();
    $raw = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();

    $questions = [];
    foreach ($raw as $r) {
        $ids     = explode('|',   $r['opt_ids']);
        $texts   = explode('|||', $r['opt_texts']);
        $correct = explode('|',   $r['opt_correct']);
        $options = [];
        foreach ($ids as $i => $oid) {
            $options[] = ['id' => (int)$oid, 'text' => $texts[$i], 'is_correct' => (int)$correct[$i]];
        }
        $questions[] = ['id' => $r['id'], 'question' => $r['question_text'], 'options' => $options];
    }
    return $questions;
}

function getQuizAttemptCount(mysqli $conn, int $userId, int $quizId): int {
    $s = $conn->prepare("SELECT COUNT(*) AS cnt FROM lms_quiz_attempts WHERE user_id = ? AND quiz_id = ?");
    $s->bind_param('ii', $userId, $quizId);
    $s->execute();
    $cnt = (int)$s->get_result()->fetch_assoc()['cnt'];
    $s->close();
    return $cnt;
}

function hasPassed(mysqli $conn, int $userId, int $quizId): bool {
    $s = $conn->prepare("SELECT id FROM lms_quiz_attempts WHERE user_id = ? AND quiz_id = ? AND passed = 1 LIMIT 1");
    $s->bind_param('ii', $userId, $quizId);
    $s->execute();
    $s->store_result();
    $found = $s->num_rows > 0;
    $s->close();
    return $found;
}

/**
 * Returns the timestamp of the most recent failed attempt, or null if none.
 */
function getLastFailedAttemptTime(mysqli $conn, int $userId, int $quizId): ?string {
    $s = $conn->prepare("SELECT attempted_at FROM lms_quiz_attempts WHERE user_id = ? AND quiz_id = ? AND passed = 0 ORDER BY attempted_at DESC LIMIT 1");
    $s->bind_param('ii', $userId, $quizId);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    return $row ? $row['attempted_at'] : null;
}

/**
 * Resets all progress for a user in a course so they must restart from scratch.
 * Clears: module progress, quiz attempts (all modules in course), enrollment
 * completion date, and any certificate issued.
 */
function resetCourseProgress(mysqli $conn, int $userId, int $courseId): void {
    // Module progress
    $s = $conn->prepare("DELETE FROM lms_module_progress WHERE user_id = ? AND course_id = ?");
    $s->bind_param('ii', $userId, $courseId);
    $s->execute();
    $s->close();

    // Quiz attempts for every quiz belonging to this course
    $s = $conn->prepare("
        DELETE qa FROM lms_quiz_attempts qa
        JOIN lms_quizzes qz ON qz.id = qa.quiz_id
        JOIN lms_modules m  ON m.id  = qz.module_id
        WHERE qa.user_id = ? AND m.course_id = ?
    ");
    $s->bind_param('ii', $userId, $courseId);
    $s->execute();
    $s->close();

    // Reset enrollment completion date
    $s = $conn->prepare("UPDATE lms_enrollments SET completed_at = NULL WHERE user_id = ? AND course_id = ?");
    $s->bind_param('ii', $userId, $courseId);
    $s->execute();
    $s->close();

    // Remove certificate
    $s = $conn->prepare("DELETE FROM lms_certificates WHERE user_id = ? AND course_id = ?");
    $s->bind_param('ii', $userId, $courseId);
    $s->execute();
    $s->close();
}

// ─── Certificate Helpers ───────────────────────────────────────────────────────

function getCertificate(mysqli $conn, int $userId, int $courseId): ?array {
    $s = $conn->prepare("SELECT * FROM lms_certificates WHERE user_id = ? AND course_id = ?");
    $s->bind_param('ii', $userId, $courseId);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $s->close();
    return $r ?: null;
}

function issueCertificate(mysqli $conn, int $userId, int $courseId): ?string {
    $existing = getCertificate($conn, $userId, $courseId);
    if ($existing) return $existing['certificate_uid'];

    $uid = 'CLSN-' . strtoupper(substr(md5($userId . $courseId . time()), 0, 10));
    $s   = $conn->prepare("INSERT IGNORE INTO lms_certificates (user_id, course_id, certificate_uid) VALUES (?,?,?)");
    $s->bind_param('iis', $userId, $courseId, $uid);
    $s->execute();
    $s->close();
    return $uid;
}

// ─── Module Unlock Logic ───────────────────────────────────────────────────────

function isModuleUnlocked(mysqli $conn, int $userId, array $module, array $allModules): bool {
    // Module 1 (first by sort_order) is always unlocked for enrolled users
    $firstModule = $allModules[0] ?? null;
    if ($firstModule && $module['id'] === $firstModule['id']) return true;

    // Find previous module
    $prevModule = null;
    foreach ($allModules as $i => $m) {
        if ($m['id'] === $module['id'] && $i > 0) {
            $prevModule = $allModules[$i - 1];
            break;
        }
    }
    if (!$prevModule) return true;

    // Check if previous module is completed
    $s = $conn->prepare("SELECT is_completed FROM lms_module_progress WHERE user_id = ? AND module_id = ?");
    $s->bind_param('ii', $userId, $prevModule['id']);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    $s->close();
    return $r && $r['is_completed'] == 1;
}

// ─── Video Embed ───────────────────────────────────────────────────────────────

function getEmbedUrl(string $url, string $type): string {
    if ($type === 'youtube') {
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1] . '?rel=0&modestbranding=1';
        }
    }
    if ($type === 'vimeo') {
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
    }
    return $url;
}

// ─── Time / Date ───────────────────────────────────────────────────────────────

function formatDate(string $date): string {
    return date('F j, Y', strtotime($date));
}

function timeAgo(string $datetime): string {
    $now  = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);
    if ($diff->y) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    return 'Just now';
}
