<?php
/**
 * Standalone certificate download page.
 * Renders the certificate, captures it with html2canvas, and downloads as PDF.
 */
include_once './includes/db.php';
include_once './includes/auth.php';

requireStudent();

$userId = currentUserId();
$uid    = trim($_GET['uid'] ?? '');

if (!$uid) { header('Location: /certificate'); exit; }

$stmt = $conn->prepare("
    SELECT c.*, co.title AS course_title,
           u.first_name, u.last_name
    FROM lms_certificates c
    JOIN lms_courses co ON co.id = c.course_id
    JOIN lms_users  u  ON u.id  = c.user_id
    WHERE c.certificate_uid = ?
");
$stmt->bind_param('s', $uid);
$stmt->execute();
$cert = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cert || (int)$cert['user_id'] !== $userId) {
    header('Location: /certificate'); exit;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Generating Certificate…</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&family=Dancing+Script:wght@700&display=swap" rel="stylesheet">
<style>
@font-face {
    font-family: 'ChunkFive';
    src: url('/fonts/ChunkFive-Regular.woff2') format('woff2'),
         url('/fonts/ChunkFive-Regular.woff')  format('woff'),
         url('/fonts/ChunkFive-Regular.ttf')   format('truetype');
    font-weight: normal; font-style: normal; font-display: block;
}
@font-face {
    font-family: 'Garet';
    src: url('/fonts/Garet-Heavy.woff2') format('woff2'),
         url('/fonts/Garet-Heavy.ttf')   format('truetype');
    font-weight: 800; font-style: normal; font-display: block;
}
/* TODO: Place AmsterdamOne-Regular.ttf in /fonts/ then uncomment:
@font-face {
    font-family: 'Amsterdam One';
    src: url('/fonts/AmsterdamOne-Regular.ttf') format('truetype');
    font-weight: normal; font-style: normal; font-display: block;
}
*/
</style>
<style>
/* ── Page chrome ─────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
    background: #f3f4f6;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    min-height: 100vh;
    padding: 24px;
    font-family: 'Poppins', sans-serif;
}
#status-bar {
    width: 1122px;
    max-width: 100%;
    background: #fff;
    border-radius: 12px;
    padding: 14px 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
    color: #555;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
}
#status-bar .spinner {
    width: 18px; height: 18px;
    border: 2px solid #e5e7eb;
    border-top-color: #F5A623;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Fixed-size certificate canvas: A4 landscape @ 96dpi = 1122×794 ── */
#cert-canvas {
    width:  1122px;
    height: 794px;
    position: relative;
    background: url('/images/certificate-bg.png') center / cover no-repeat;
    overflow: hidden;
    box-shadow: 0 8px 40px rgba(0,0,0,0.18);
}

.cert-inner {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    padding: 40px 78px 32px;
}

/* TOP ROW */
.cert-top-row    { position: relative; margin-bottom: 4px; }
.cert-title-block{ width: 100%; text-align: center; }

.cert-big-title {
    font-family: 'ChunkFive', Georgia, serif;
    font-size: 64px;
    font-weight: normal;
    letter-spacing: 0.04em;
    color: #1B6B7B;
    text-transform: uppercase;
    line-height: 1;
}
.cert-subtitle {
    font-family: 'Garet', sans-serif;
    font-size: 20px;
    font-weight: 800;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: #C2185B;
    margin: 2px 0 6px;
}
.cert-presented-to {
    font-family: 'Poppins', sans-serif;
    font-style: italic;
    font-size: 18px;
    color: #444;
}

/* LOGO */
.cert-logo-col {
    position: absolute;
    top: 0; right: 0;
}
.cert-logo-col img {
    width: 190px;
    height: auto;
    display: block;
}

/* RECIPIENT NAME */
.cert-name-wrap  { text-align: center; }
.cert-name-row {
    display: inline-block;
    border-bottom: 1.5px solid #555;
    padding: 0 16px 4px;
    margin: 0 auto 3px;
}
.cert-name-cursive {
    font-family: 'Amsterdam One', 'Dancing Script', cursive;
    font-size: 72px;
    font-weight: normal;
    color: #1A237E;
    line-height: 1.1;
    display: block;
}

/* BODY */
.cert-recognition {
    font-family: 'Poppins', sans-serif;
    font-style: italic;
    font-size: 18px;
    color: #444;
    text-align: center;
    margin: 4px 0 3px;
}
.cert-course-name {
    font-family: 'Poppins', sans-serif;
    font-weight: 800;
    font-size: 26px;
    color: #C2185B;
    text-align: center;
    margin: 0 0 4px;
    line-height: 1.3;
}
.cert-program-desc {
    font-family: 'Poppins', sans-serif;
    font-style: italic;
    font-size: 14px;
    color: #555;
    line-height: 1.45;
    text-align: center;
    max-width: 82%;
    margin: 0 auto 6px;
}
.cert-date-row {
    font-family: 'Poppins', sans-serif;
    font-weight: 700;
    font-size: 18px;
    color: #222;
    text-align: center;
}
.cert-date-row span {
    display: inline-block;
    width: 220px;
    border-bottom: 1.5px solid #333;
    margin-left: 6px;
}

/* FOOTER */
.cert-footer-row {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    padding-top: 6px;
    margin-top: auto;
}
.cert-seal-wrap {
    flex: 0 0 auto;
    width: 120px;
    height: 120px;
}
.cert-seal-wrap img { width: 100%; height: 100%; object-fit: contain; }

.cert-sig-block {
    flex: 1;
    display: flex;
    flex-direction: row;
    justify-content: center;
    align-items: flex-end;
    gap: 48px;
    padding-right: 90px;
    padding-bottom: 4px;
}
.cert-sig-line-h {
    width: 200px;
    height: 1.5px;
    background: #333;
    margin: 0 auto 4px;
}
.cert-sig-name-text {
    font-family: 'Poppins', sans-serif;
    font-weight: 700;
    font-size: 16px;
    color: #222;
    text-align: center;
}
.cert-sig-role-text {
    font-family: 'Poppins', sans-serif;
    font-size: 13px;
    color: #555;
    text-align: center;
}

/* UID */
.cert-uid-corner {
    position: absolute;
    right: 3.5%; bottom: 2.8%;
    font-family: monospace;
    font-size: 7px;
    color: rgba(0,0,0,0.28);
    text-align: right;
    line-height: 1.45;
}
</style>
</head>
<body>

<div id="status-bar">
    <div class="spinner"></div>
    <span id="status-text">Generating your certificate PDF — please wait…</span>
</div>

<div id="cert-canvas">
    <div class="cert-inner">

        <!-- TOP ROW -->
        <div class="cert-top-row">
            <div class="cert-title-block">
                <h1 class="cert-big-title">Certificate</h1>
                <div class="cert-subtitle">of Completion</div>
                <p class="cert-presented-to">This certificate is proudly presented to</p>
            </div>
            <div class="cert-logo-col">
                <img src="/images/logo-candlelight.svg" alt="Candlelight Foundation" crossorigin="anonymous">
            </div>
        </div>

        <!-- RECIPIENT NAME -->
        <div class="cert-name-wrap">
            <div class="cert-name-row">
                <span class="cert-name-cursive"><?= htmlspecialchars($cert['first_name'] . ' ' . $cert['last_name']) ?></span>
            </div>
        </div>

        <!-- RECOGNITION + COURSE -->
        <p class="cert-recognition">In recognition for completing the</p>
        <p class="cert-course-name"><?= htmlspecialchars($cert['course_title']) ?></p>

        <!-- DESCRIPTION -->
        <p class="cert-program-desc">
            This training program is based on the Registered Technician Task list and designed to meet the 40-hour training
            requirement for the RBT credential. This program is offered independent of the BACB.
        </p>

        <!-- DATE -->
        <p class="cert-date-row">Date of Completion:<span><?= date('F j, Y', strtotime($cert['issued_at'])) ?></span></p>

        <!-- FOOTER -->
        <div class="cert-footer-row">
            <div class="cert-seal-wrap">
                <img src="/images/broache.png" alt="Certificate Seal" crossorigin="anonymous">
            </div>
            <div class="cert-sig-block">
                <div style="text-align:center;">
                    <div class="cert-sig-line-h"></div>
                    <p class="cert-sig-name-text">Candlelight Foundation</p>
                    <p class="cert-sig-role-text">Director of Education</p>
                </div>
                <div style="text-align:center;">
                    <div class="cert-sig-line-h"></div>
                    <p class="cert-sig-name-text">Training Coordinator</p>
                    <p class="cert-sig-role-text">Candlelight LMS</p>
                </div>
            </div>
        </div>

        <div class="cert-uid-corner">
            ID: <?= htmlspecialchars($cert['certificate_uid']) ?><br>
            candlelightspecialneeds.org
        </div>

    </div><!-- /cert-inner -->
</div><!-- /cert-canvas -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
window.addEventListener('load', () => {
    document.fonts.ready.then(() => {
        setTimeout(async () => {
            const el = document.getElementById('cert-canvas');
            const canvas = await html2canvas(el, {
                scale: 2,
                useCORS: true,
                allowTaint: false,
                backgroundColor: null,
                logging: false,
                width:  1122,
                height: 794,
            });

            const { jsPDF } = window.jspdf;
            const pdf     = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
            const pageW   = pdf.internal.pageSize.getWidth();   // 297mm
            const pageH   = pdf.internal.pageSize.getHeight();  // 210mm
            const imgData = canvas.toDataURL('image/jpeg', 1.0);
            pdf.addImage(imgData, 'JPEG', 0, 0, pageW, pageH);

            const name = <?= json_encode($cert['first_name'] . '_' . $cert['last_name']) ?>;
            pdf.save('Certificate_' + name + '.pdf');

            document.getElementById('status-text').textContent = 'Download started! You can close this tab.';
            document.querySelector('.spinner').style.display = 'none';
            setTimeout(() => window.close(), 1500);
        }, 800);
    });
});
</script>
</body>
</html>
