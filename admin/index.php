<?php
include_once '../includes/db.php';
include_once '../includes/auth.php';

requireAdmin();

// Stats
$stats = [];
$rows = [
    'users'       => "SELECT COUNT(*) AS cnt FROM lms_users WHERE role='student'",
    'enrollments' => "SELECT COUNT(*) AS cnt FROM lms_enrollments",
    'completions' => "SELECT COUNT(*) AS cnt FROM lms_module_progress WHERE is_completed=1",
    'certificates'=> "SELECT COUNT(*) AS cnt FROM lms_certificates",
];
foreach ($rows as $key => $sql) {
    $r = $conn->query($sql);
    $stats[$key] = (int)$r->fetch_assoc()['cnt'];
}

// Recent registrations
$recentUsers = $conn->query("SELECT id, first_name, last_name, email, created_at FROM lms_users WHERE role='student' ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// Recent Q&A (unanswered)
$unanswered = (int)$conn->query("SELECT COUNT(*) AS cnt FROM lms_module_qa WHERE answer IS NULL")->fetch_assoc()['cnt'];

$adminPageTitle = 'Admin Dashboard';
include './includes/header.php';
?>

<!-- Stats Grid -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <?php foreach ([
        ['icon'=>'fas fa-users','label'=>'Students','value'=>$stats['users'],'color'=>'text-navy-700','bg'=>'bg-navy-50'],
        ['icon'=>'fas fa-graduation-cap','label'=>'Enrollments','value'=>$stats['enrollments'],'color'=>'text-green-700','bg'=>'bg-green-50'],
        ['icon'=>'fas fa-check-circle','label'=>'Module Completions','value'=>$stats['completions'],'color'=>'text-blue-700','bg'=>'bg-blue-50'],
        ['icon'=>'fas fa-certificate','label'=>'Certificates','value'=>$stats['certificates'],'color'=>'text-candlelight-700','bg'=>'bg-candlelight-50'],
    ] as $s): ?>
    <div class="lms-card p-6 flex items-center gap-4">
        <div class="w-12 h-12 rounded-2xl <?= $s['bg'] ?> flex items-center justify-center flex-shrink-0">
            <i class="<?= $s['icon'] ?> <?= $s['color'] ?> text-xl"></i>
        </div>
        <div>
            <div class="text-3xl font-bold text-gray-900"><?= $s['value'] ?></div>
            <div class="text-xs text-gray-500 mt-0.5"><?= $s['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Two Columns -->
<div class="grid md:grid-cols-2 gap-6">

    <!-- Recent Students -->
    <div class="lms-card">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-display font-bold text-navy-900">Recent Registrations</h3>
            <a href="/admin/users.php" class="text-xs text-candlelight-600 font-semibold hover:underline">View All</a>
        </div>
        <div class="divide-y divide-gray-100">
            <?php foreach ($recentUsers as $u): ?>
            <div class="flex items-center gap-3 px-5 py-3.5">
                <div class="w-9 h-9 rounded-full bg-candlelight-100 flex items-center justify-center font-bold text-candlelight-700 text-sm flex-shrink-0">
                    <?= strtoupper(substr($u['first_name'], 0, 1)) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold truncate"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></div>
                    <div class="text-xs text-gray-400 truncate"><?= htmlspecialchars($u['email']) ?></div>
                </div>
                <div class="text-xs text-gray-400"><?= date('M j', strtotime($u['created_at'])) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recentUsers)): ?>
            <p class="p-5 text-gray-400 text-sm text-center">No students yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="lms-card p-6">
        <h3 class="font-display font-bold text-navy-900 mb-5">Quick Actions</h3>
        <div class="grid grid-cols-2 gap-3">
            <?php foreach ([
                ['href'=>'/admin/courses.php',      'icon'=>'fas fa-graduation-cap','label'=>'Manage Courses',    'cls'=>'bg-navy-50 text-navy-700 hover:bg-navy-100'],
                ['href'=>'/admin/modules.php',       'icon'=>'fas fa-layer-group',   'label'=>'Manage Modules',    'cls'=>'bg-blue-50 text-blue-700 hover:bg-blue-100'],
                ['href'=>'/admin/quiz-builder.php',  'icon'=>'fas fa-pencil-alt',    'label'=>'Quiz Builder',      'cls'=>'bg-candlelight-50 text-candlelight-700 hover:bg-candlelight-100'],
                ['href'=>'/admin/users.php',         'icon'=>'fas fa-users',         'label'=>'View Students',     'cls'=>'bg-green-50 text-green-700 hover:bg-green-100'],
                ['href'=>'/admin/qa-manager.php',    'icon'=>'fas fa-comments',      'label'=>'Q&A ('.($unanswered ? "<span class='text-red-500'>{$unanswered} new</span>" : '0 new').')', 'cls'=>'bg-purple-50 text-purple-700 hover:bg-purple-100'],
                ['href'=>'/courses.php',             'icon'=>'fas fa-eye',           'label'=>'View Public Site',  'cls'=>'bg-gray-50 text-gray-700 hover:bg-gray-100'],
            ] as $a): ?>
            <a href="<?= $a['href'] ?>" class="flex flex-col items-center gap-2 p-4 rounded-xl <?= $a['cls'] ?> transition-colors text-center font-semibold text-sm border border-transparent hover:border-current/10">
                <i class="<?= $a['icon'] ?> text-xl"></i>
                <span><?= $a['label'] ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include './includes/footer.php'; ?>
