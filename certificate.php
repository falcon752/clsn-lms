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
        if (!$certView) { header('Location: /certificate'); exit; }
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
        <a href="/certificate.php" class="flex items-center gap-2 text-gray-500 hover:text-candlelight-600 text-sm font-semibold transition-colors">
            <i class="fas fa-arrow-left"></i> Back to My Certificates
        </a>
        <a href="/certificate-print.php?uid=<?= urlencode($certView['certificate_uid']) ?>"
           target="_blank"
           class="btn-lms-primary text-sm">
            <i class="fas fa-download"></i> Save as PDF
        </a>
    </div>
    <p class="text-sm text-gray-400 text-center mb-4">Click "Save as PDF" — a print dialog will open. Choose "Save as PDF" as the destination.</p>
</div>

<div class="cert-print-area max-w-5xl mx-auto">
    <div class="cert-paper">
        <div class="cert-inner">

            <!-- TOP ROW: centered title + logo at right -->
            <div class="cert-top-row">
                <div class="cert-title-block">
                    <h1 class="cert-big-title">Certificate</h1>
                    <div class="cert-subtitle">of Completion</div>
                    <p class="cert-presented-to">This certificate is proudly presented to</p>
                </div>
                <div class="cert-logo-col">
                    <img src="/images/logo-candlelight.svg" alt="Candlelight Foundation">
                </div>
            </div>

            <!-- RECIPIENT NAME -->
            <div class="text-center">
                <div class="cert-name-row">
                    <span class="cert-name-cursive"><?= htmlspecialchars($certView['first_name'] . ' ' . $certView['last_name']) ?></span>
                </div>
            </div>

            <!-- RECOGNITION + COURSE -->
            <p class="cert-recognition" style="margin-top:4px; text-align:center;">In recognition for completing the</p>
            <p class="cert-course-name" style="text-align:center;"><?= htmlspecialchars($certView['course_title']) ?></p>

            <!-- PROGRAM DESCRIPTION (sample style) -->
            <p class="cert-program-desc">
                This training program is based on the Registered Technician Task list and designed to meet the 40-hour training
                requirement for the RBT credential. This program is offered independent of the BACB.
            </p>

            <!-- DATE -->
            <p class="cert-date-row">Date of Completion:<span><?= date('F j, Y', strtotime($certView['issued_at'])) ?></span></p>

            <!-- FOOTER: Seal + Signature -->
            <div class="cert-footer-row">
                <div class="cert-seal-wrap">
                    <img src="/images/broache.png" alt="Certificate Seal">
                </div>

                <div class="cert-sig-block" style="padding-bottom:4px; gap: clamp(18px,4vw,48px);">
                    <!-- Sig 1 -->
                    <div style="text-align:center;">
                        <div class="cert-sig-line-h" style="margin:0 auto 4px;"></div>
                        <p class="cert-sig-name-text">Candlelight Foundation</p>
                        <p class="cert-sig-role-text">Director of Education</p>
                    </div>
                    <!-- Sig 2 -->
                    <div style="text-align:center;">
                        <div class="cert-sig-line-h" style="margin:0 auto 4px;"></div>
                        <p class="cert-sig-name-text">Training Coordinator</p>
                        <p class="cert-sig-role-text">Candlelight LMS</p>
                    </div>
                </div>
            </div>

            <div class="cert-uid-corner">
                ID: <?= htmlspecialchars($certView['certificate_uid']) ?><br>
                candlelightspecialneeds.org
            </div>

        </div><!-- /cert-inner -->
    </div><!-- /cert-paper -->
</div>

<script>
// Scale the fixed-size cert to always fit its container, on any screen size
function scaleCert() {
    const area  = document.querySelector('.cert-print-area');
    const paper = document.querySelector('.cert-paper');
    if (!area || !paper) return;
    const scale = area.offsetWidth / 960;
    paper.style.transform = 'scale(' + scale + ')';
    area.style.height     = Math.round(679 * scale) + 'px';
}
scaleCert();
window.addEventListener('resize', scaleCert);
</script>

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
    <a href="/courses.php" class="btn-lms-primary inline-flex mx-auto">
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
                <a href="/certificate.php?uid=<?= urlencode($cert['certificate_uid']) ?>"
                   class="flex-1 btn-lms-primary text-center text-sm py-2.5">
                    <i class="fas fa-eye"></i> View &amp; Download
                </a>
                <a href="/course.php?slug=<?= urlencode($cert['course_slug']) ?>"
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
<script src="/js/main.js"></script>
