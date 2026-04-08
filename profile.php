<?php
include_once './includes/db.php';
include_once './includes/auth.php';
include_once './includes/functions.php';

requireStudent();

$userId = currentUserId();
$user   = currentUser();
$error  = '';
$success= '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrf($_POST['csrf_token'])) {
        $error = 'Security check failed.';
    } elseif (isset($_POST['update_profile'])) {
        $first = trim($_POST['first_name'] ?? '');
        $last  = trim($_POST['last_name']  ?? '');
        if (empty($first) || empty($last)) {
            $error = 'Name fields are required.';
        } else {
            $stmt = $conn->prepare("UPDATE lms_users SET first_name=?, last_name=? WHERE id=?");
            $stmt->bind_param('ssi', $first, $last, $userId);
            $stmt->execute();
            $stmt->close();
            // Update session
            $_SESSION['lms_first_name'] = $first;
            $_SESSION['lms_last_name']  = $last;
            $user['first_name'] = $first;
            $user['last_name']  = $last;
            $success = 'Profile updated successfully!';
        }
    } elseif (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $newPw   = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (empty($current) || empty($newPw) || empty($confirm)) {
            $error = 'All password fields are required.';
        } elseif (strlen($newPw) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($newPw !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $stmt = $conn->prepare("SELECT password FROM lms_users WHERE id=?");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row || !password_verify($current, $row['password'])) {
                $error = 'Current password is incorrect.';
            } else {
                $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $conn->prepare("UPDATE lms_users SET password=? WHERE id=?");
                $stmt->bind_param('si', $hash, $userId);
                $stmt->execute();
                $stmt->close();
                $success = 'Password changed successfully!';
            }
        }
    }
}

$dashPageTitle = 'My Profile';
include './includes/header-dash.php';
?>

<div class="max-w-2xl mx-auto space-y-6">

    <?php if ($error): ?>
    <div class="lms-alert lms-alert-error"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="lms-alert lms-alert-success"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Avatar Card -->
    <div class="lms-card p-6 flex items-center gap-5">
        <div class="w-20 h-20 rounded-full bg-gradient-to-br from-candlelight-500 to-candlelight-700 flex items-center justify-center text-white text-3xl font-bold flex-shrink-0">
            <?= strtoupper(substr($user['first_name'], 0, 1)) ?>
        </div>
        <div>
            <h2 class="font-display text-2xl font-bold text-navy-900"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
            <p class="text-gray-500 text-sm"><?= htmlspecialchars($user['email']) ?></p>
            <span class="inline-block mt-2 px-3 py-1 <?= $user['role'] === 'admin' ? 'bg-candlelight-100 text-candlelight-700' : 'bg-navy-100 text-navy-700' ?> text-xs font-semibold rounded-full capitalize"><?= $user['role'] ?></span>
        </div>
    </div>

    <!-- Update Profile -->
    <div class="lms-card p-6">
        <h3 class="font-display font-bold text-navy-900 text-lg mb-5">Personal Information</h3>
        <form method="POST" action="" class="space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="update_profile" value="1">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">First Name</label>
                    <input type="text" name="first_name" required class="lms-input"
                        value="<?= htmlspecialchars($user['first_name']) ?>">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Last Name</label>
                    <input type="text" name="last_name" required class="lms-input"
                        value="<?= htmlspecialchars($user['last_name']) ?>">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                <input type="email" class="lms-input bg-gray-50 text-gray-400 cursor-not-allowed" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                <p class="text-xs text-gray-400 mt-1">Email address cannot be changed. Contact support if needed.</p>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="btn-lms-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>

    <!-- Change Password -->
    <div class="lms-card p-6">
        <h3 class="font-display font-bold text-navy-900 text-lg mb-5">Change Password</h3>
        <form method="POST" action="" class="space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="change_password" value="1">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Current Password</label>
                <input type="password" name="current_password" required class="lms-input" placeholder="••••••••">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">New Password <span class="text-gray-400 font-normal">(min. 8 characters)</span></label>
                <input type="password" name="new_password" required class="lms-input" placeholder="••••••••">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Confirm New Password</label>
                <input type="password" name="confirm_password" required class="lms-input" placeholder="••••••••">
            </div>
            <div class="flex justify-end">
                <button type="submit" class="btn-lms-primary">
                    <i class="fas fa-lock"></i> Update Password
                </button>
            </div>
        </form>
    </div>

</div>

<?php include './includes/footer-dash.php'; ?>
<script src="/js/main.js"></script>
