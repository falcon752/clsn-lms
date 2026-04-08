<?php
include_once './includes/db.php';
include_once './includes/auth.php';

if (isLoggedIn()) { header('Location: /dashboard.php'); exit; }

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $first    = trim($_POST['first_name']  ?? '');
        $last     = trim($_POST['last_name']   ?? '');
        $email    = trim($_POST['email']       ?? '');
        $password = $_POST['password']         ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (empty($first) || empty($last) || empty($email) || empty($password)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            // Check duplicate email
            $stmt = $conn->prepare("SELECT id FROM lms_users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $error = 'An account with that email address already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt->close();
                $stmt = $conn->prepare("INSERT INTO lms_users (first_name, last_name, email, password, role) VALUES (?,?,?,?,'student')");
                $stmt->bind_param('ssss', $first, $last, $email, $hash);
                if ($stmt->execute()) {
                    $newId = $conn->insert_id;
                    $stmt->close();
                    // Auto-enroll in the autism course (course id=1)
                    $enroll = $conn->prepare("INSERT IGNORE INTO lms_enrollments (user_id, course_id) VALUES (?,1)");
                    $enroll->bind_param('i', $newId);
                    $enroll->execute();
                    $enroll->close();

                    // Login the user
                    $u = ['id' => $newId, 'first_name' => $first, 'last_name' => $last, 'email' => $email, 'role' => 'student'];
                    loginUser($u);
                    header('Location: /dashboard.php?welcome=1');
                    exit;
                } else {
                    $error = 'Registration failed. Please try again.';
                    $stmt->close();
                }
            }
            if (isset($stmt)) $stmt->close();
        }
    }
}

$pageTitle = 'Create Account | Candlelight LMS';
include './includes/header-public.php';
?>

<section class="min-h-screen bg-gray-50 flex items-center justify-center py-16 px-4">
    <div class="w-full max-w-lg">
        <div class="bg-white rounded-3xl shadow-2xl p-10">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-candlelight-500 to-candlelight-700 mb-4 shadow-lg">
                    <i class="fas fa-user-plus text-white text-2xl"></i>
                </div>
                <h1 class="font-display text-3xl font-bold text-navy-900">Create Your Account</h1>
                <p class="text-gray-500 mt-2">Join thousands learning with Candlelight Foundation.</p>
            </div>

            <?php if ($error): ?>
            <div class="lms-alert lms-alert-error mb-6"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-5">
                <?= csrfField() ?>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">First Name</label>
                        <input type="text" name="first_name" required autocomplete="given-name"
                            value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                            class="lms-input" placeholder="Jane">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Last Name</label>
                        <input type="text" name="last_name" required autocomplete="family-name"
                            value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                            class="lms-input" placeholder="Doe">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                    <input type="email" name="email" required autocomplete="email"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        class="lms-input" placeholder="you@example.com">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Password <span class="text-gray-400 font-normal">(min. 8 characters)</span></label>
                    <div class="relative">
                        <input type="password" name="password" id="pass-field" required autocomplete="new-password"
                            class="lms-input pr-12" placeholder="••••••••">
                        <button type="button" id="toggle-pass" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-candlelight-600 transition-colors">
                            <i class="fas fa-eye" id="pass-icon"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Confirm Password</label>
                    <input type="password" name="confirm_password" required autocomplete="new-password"
                        class="lms-input" placeholder="••••••••">
                </div>

                <!-- Terms -->
                <div class="flex items-start gap-3">
                    <input type="checkbox" id="terms" name="terms" required
                        class="mt-1 w-4 h-4 accent-candlelight-500 cursor-pointer flex-shrink-0">
                    <label for="terms" class="text-sm text-gray-600 cursor-pointer">
                        I agree to the <a href="/terms.php" class="text-candlelight-600 hover:underline">Terms of Use</a> and <a href="/privacy.php" class="text-candlelight-600 hover:underline">Privacy Policy</a>.
                    </label>
                </div>

                <button type="submit" class="btn-lms-primary w-full text-base py-4">
                    <i class="fas fa-rocket"></i> Create Free Account
                </button>
            </form>

            <!-- Free badge -->
            <div class="mt-6 p-4 bg-candlelight-50 rounded-xl flex items-center gap-3">
                <i class="fas fa-gift text-candlelight-600 text-lg flex-shrink-0"></i>
                <p class="text-sm text-candlelight-800">You'll be <strong>automatically enrolled</strong> in our flagship Autism Awareness course, completely free!</p>
            </div>

            <p class="text-center text-gray-500 text-sm mt-6">
                Already have an account?
                <a href="/login.php" class="text-candlelight-600 font-semibold hover:text-candlelight-700 transition-colors">Sign in →</a>
            </p>
        </div>
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
<script src="/js/main.js"></script>
