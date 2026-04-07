<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── Auth Helpers ──────────────────────────────────────────────────────────────

function isLoggedIn(): bool {
    return !empty($_SESSION['lms_user_id']);
}

function currentUserId(): int {
    return (int)($_SESSION['lms_user_id'] ?? 0);
}

function currentUser(): array {
    return [
        'id'         => (int)($_SESSION['lms_user_id']    ?? 0),
        'first_name' => $_SESSION['lms_first_name'] ?? '',
        'last_name'  => $_SESSION['lms_last_name']  ?? '',
        'email'      => $_SESSION['lms_user_email'] ?? '',
        'role'       => $_SESSION['lms_user_role']  ?? 'student',
    ];
}

function isAdmin(): bool {
    return ($_SESSION['lms_user_role'] ?? '') === 'admin';
}

function requireLogin(string $redirect = ''): void {
    if (!isLoggedIn()) {
        $target = $redirect ?: (defined('BASE_URL') ? BASE_URL . '/login.php' : '/clsn-lms/login.php');
        $back   = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header("Location: {$target}?redirect={$back}");
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/clsn-lms') . '/dashboard.php');
        exit;
    }
}

function requireStudent(): void {
    requireLogin();
    if (isAdmin()) {
        header('Location: /clsn-lms/admin/');
        exit;
    }
}

function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['lms_user_id']    = $user['id'];
    $_SESSION['lms_first_name'] = $user['first_name'];
    $_SESSION['lms_last_name']  = $user['last_name'];
    $_SESSION['lms_user_email'] = $user['email'];
    $_SESSION['lms_user_role']  = $user['role'];
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ─── CSRF ─────────────────────────────────────────────────────────────────────

function csrfToken(): string {
    if (empty($_SESSION['lms_csrf'])) {
        $_SESSION['lms_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['lms_csrf'];
}

function verifyCsrf(string $token): bool {
    return !empty($_SESSION['lms_csrf']) && hash_equals($_SESSION['lms_csrf'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}
