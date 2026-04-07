<?php
include_once './includes/db.php';
include_once './includes/auth.php';
include_once './includes/functions.php';

requireStudent();

$userId = currentUserId();
$user   = currentUser();

// Single certificate view by UID
$uid  = trim($_GET['uid'] ?? '');
$certView = null;
if ($uid) {
    $stmt = $conn->prepare("
        SELECT c.*, co.title AS course_title, co.slug AS course_slug,
               u.first_name, u.last_name
        FROM lms_certificates c
        JOIN lms_courses co ON co.id = c.course_id
        JOIN lms_users u   ON u.id  = c.user_id
        WHERE c.certificate_uid = ?
    ");
    $stmt->bind_param('s', $uid);
    $stmt->execute();
    $certView = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    // Only owner can view
    if (!$certView || $certView['user_id'] ?? null !== $userId) {
        if (!$certView) { header('Location: /clsn-lms/certificate.php'); exit; }
    }
}

// List all certificates for this user
$stmt = $conn->prepare("
    SELECT c.*, co.title AS course_title, co.slug AS course_slug
    FROM lms_certificates c
    JOIN lms_courses co ON co.id = c.course_id
    WHERE c.user_id = ?
    ORDER BY c.issued_at DESC
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$certificates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$dashPageTitle = 'My Certificates';
include './includes/header-dash.php';
?>

<?php if ($certView): ?>
<!-- ── Single Certificate Print View ──────────────────────────────────── -->
<div class="max-w-4xl mx-auto mb-6 no-print">
    <div class="flex items-center justify-between flex-wrap gap-3 mb-6">
        <a href="/clsn-lms/certificate.php" class="flex items-center gap-2 text-gray-500 hover:text-candlelight-600 text-sm font-semibold transition-colors">
            <i class="fas fa-arrow-left"></i> Back to My Certificates
        </a>
        <button onclick="window.print()" class="btn-lms-primary text-sm">
            <i class="fas fa-download"></i> Save as PDF
        </button>
    </div>
    <p class="text-sm text-gray-400 text-center mb-4">Preview below: click "Save as PDF" and then "Save as PDF" in the print dialog.</p>
</div>

<div class="cert-print-area max-w-5xl mx-auto">
    <div class="cert-paper">
        <div class="cert-body-panel">

            <!-- Corner ornaments -->
            <div class="cert-corner cert-corner-tl"></div>
            <div class="cert-corner cert-corner-tr"></div>
            <div class="cert-corner cert-corner-bl"></div>
            <div class="cert-corner cert-corner-br"></div>

            <!-- Header: Logo + Org name -->
            <div class="mb-1">
                <img src="/clsn-lms/images/logo-candlelight.svg" class="cert-logo-img" alt="Candlelight Foundation">
                <div class="cert-org-label">Candlelight Foundation</div>
            </div>

            <!-- Ornamental title band -->
            <div class="cert-title-band">
                <div class="cb-line"></div>
                <div class="cb-diamond"></div>
                <span class="cert-title-text">Certificate of Completion</span>
                <div class="cb-diamond"></div>
                <div class="cb-line cb-line-r"></div>
            </div>

            <!-- Body -->
            <div>
                <p class="cert-presents-label">This is to certify that</p>
                <h2 class="cert-name-text"><?= htmlspecialchars($certView['first_name'] . ' ' . $certView['last_name']) ?></h2>
                <div class="cert-name-rule"></div>
                <p class="cert-completed-label">Has Successfully Completed the Course</p>
                <h3 class="cert-course-text"><?= htmlspecialchars($certView['course_title']) ?></h3>
                <p class="cert-date-label">Issued: <?= formatDate($certView['issued_at']) ?></p>
            </div>

            <!-- Footer: Sig | Seal | Sig -->
            <div class="cert-footer-row">
                <div class="cert-sig">
                    <div class="cert-sig-line"></div>
                    <p class="cert-sig-name">Candlelight Foundation</p>
                    <p class="cert-sig-role">Director of Education</p>
                </div>
                <div class="cert-seal-col">
                    <div class="cert-seal-ring">
                        <i class="fas fa-award"></i>
                    </div>
                    <div class="cert-seal-uid"><?= htmlspecialchars($certView['certificate_uid']) ?></div>
                    <div class="cert-website-badge">candlelightspecialneeds.org</div>
                </div>
                <div class="cert-sig">
                    <div class="cert-sig-line"></div>
                    <p class="cert-sig-name">Training Coordinator</p>
                    <p class="cert-sig-role">Candlelight LMS</p>
                </div>
            </div>

        </div>
    </div>
</div>

<?php else: ?>
<!-- ── Certificate List ────────────────────────────────────────────────── -->
<div class="mb-6">
    <p class="text-gray-500">Your earned certificates are listed below. Complete all modules in a course to earn your certificate.</p>
</div>

<?php if (empty($certificates)): ?>
<div class="lms-card p-16 text-center">
    <div class="w-24 h-24 rounded-3xl bg-candlelight-50 flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-award text-candlelight-400 text-4xl"></i>
    </div>
    <h3 class="font-display text-xl font-bold text-navy-900 mb-2">No Certificates Yet</h3>
    <p class="text-gray-500 mb-6">Complete all modules in a course to earn your certificate of completion.</p>
    <a href="/clsn-lms/courses.php" class="btn-lms-primary inline-flex mx-auto">
        <i class="fas fa-graduation-cap"></i> Browse Courses
    </a>
</div>
<?php else: ?>
<div class="grid md:grid-cols-2 gap-6">
    <?php foreach ($certificates as $cert): ?>
    <div class="lms-card overflow-hidden group">
        <!-- Certificate preview banner -->
        <div class="h-32 bg-gradient-to-br from-navy-800 to-navy-600 relative overflow-hidden flex items-center justify-center">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_50%,rgba(255,167,38,0.15),transparent_50%)]"></div>
            <div class="text-center text-white">
                <i class="fas fa-award text-candlelight-400 text-4xl mb-2 block"></i>
                <div class="text-xs font-semibold tracking-widest uppercase text-white/60">Certificate of Completion</div>
            </div>
        </div>
        <div class="p-6">
            <h3 class="font-display font-bold text-navy-900 text-lg leading-snug mb-1"><?= htmlspecialchars($cert['course_title']) ?></h3>
            <div class="flex items-center gap-4 text-sm text-gray-500 mb-4">
                <span><i class="fas fa-calendar-check text-candlelight-500 mr-1"></i>Issued <?= formatDate($cert['issued_at']) ?></span>
            </div>
            <div class="mb-4 p-3 bg-gray-50 rounded-xl text-xs text-gray-500 font-mono break-all">
                ID: <?= htmlspecialchars($cert['certificate_uid']) ?>
            </div>
            <div class="flex gap-2">
                <a href="/clsn-lms/certificate.php?uid=<?= urlencode($cert['certificate_uid']) ?>"
                   class="flex-1 btn-lms-primary text-center text-sm py-2.5">
                    <i class="fas fa-eye"></i> View &amp; Download
                </a>
                <a href="/clsn-lms/course.php?slug=<?= urlencode($cert['course_slug']) ?>"
                   class="px-3 py-2.5 border border-gray-200 rounded-xl text-gray-500 hover:bg-gray-50 transition-colors text-sm">
                    <i class="fas fa-book"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include './includes/footer-dash.php'; ?>
<script src="/clsn-lms/js/main.js"></script>
