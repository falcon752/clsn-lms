<?php
include_once './includes/db.php';
include_once './includes/auth.php';

if (isLoggedIn()) { header('Location: /clsn-lms/dashboard.php'); exit; }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $error = 'Email and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $stmt = $conn->prepare("SELECT id, first_name, last_name, email, password, role, is_active FROM lms_users WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user || !password_verify($password, $user['password'])) {
                $error = 'Incorrect email or password. Please try again.';
            } elseif (!$user['is_active']) {
                $error = 'Your account has been deactivated. Please contact support.';
            } else {
                loginUser($user);
                $redirect = filter_var($_GET['redirect'] ?? '', FILTER_SANITIZE_URL);
                if ($redirect && strpos($redirect, '/clsn-lms/') === 0) {
                    header("Location: $redirect");
                } else {
                    header('Location: /clsn-lms/dashboard.php');
                }
                exit;
            }
        }
    }
}

$pageTitle = 'Login — Candlelight LMS';
include './includes/header-public.php';
?>

<section class="min-h-screen bg-gray-50 flex items-center justify-center py-16 px-4">
    <div class="w-full max-w-md">

        <!-- Card -->
        <div class="bg-white rounded-3xl shadow-2xl p-10">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-candlelight-500 to-candlelight-700 mb-4 shadow-lg">
                    <i class="fas fa-graduation-cap text-white text-2xl"></i>
                </div>
                <h1 class="font-display text-3xl font-bold text-navy-900">Welcome Back</h1>
                <p class="text-gray-500 mt-2">Sign in to continue your learning journey.</p>
            </div>

            <?php if ($error): ?>
            <div class="lms-alert lms-alert-error mb-6"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="lms-alert lms-alert-success mb-6"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-5">
                <?= csrfField() ?>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                    <input type="email" name="email" required autocomplete="email"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        class="lms-input" placeholder="you@example.com">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                    <div class="relative">
                        <input type="password" name="password" id="pass-field" required autocomplete="current-password"
                            class="lms-input pr-12" placeholder="••••••••">
                        <button type="button" id="toggle-pass" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-candlelight-600 transition-colors">
                            <i class="fas fa-eye" id="pass-icon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-lms-primary w-full text-base py-4">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <p class="text-center text-gray-500 text-sm mt-6">
                Don't have an account?
                <a href="/clsn-lms/register.php" class="text-candlelight-600 font-semibold hover:text-candlelight-700 transition-colors">Create one free →</a>
            </p>
        </div>

        <p class="text-center text-gray-400 text-xs mt-6">
            This platform is part of <a href="https://candlelightspecialneeds.org" class="hover:text-candlelight-500 transition-colors">Candlelight Foundation</a>
        </p>
    </div>
</section>

<?php include './includes/footer-public.php'; ?>
<script>
document.getElementById('toggle-pass')?.addEventListener('click', function() {
    var f = document.getElementById('pass-field');
    var i = document.getElementById('pass-icon');
    if (f.type === 'password') { f.type = 'text';     i.className = 'fas fa-eye-slash'; }
    else                       { f.type = 'password'; i.className = 'fas fa-eye'; }
});
</script>
<script src="/clsn-lms/js/main.js"></script>
