<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($conn))               include_once __DIR__ . '/db.php';
if (!function_exists('isLoggedIn')) include_once __DIR__ . '/auth.php';

$dashUser     = currentUser();
$dashPageTitle = $dashPageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($dashPageTitle) ?> | Candlelight LMS</title>
    <meta name="robots" content="noindex, nofollow">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&family=Playfair+Display:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Dancing+Script:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="/clsn-lms/images/favicon.ico">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    candlelight: { 50:'#FFF9F0',100:'#FFF3E0',200:'#FFE0B2',300:'#FFCC80',400:'#FFB74D',500:'#FFA726',600:'#FB8C00',700:'#F57C00',800:'#EF6C00',900:'#E65100' },
                    navy: { 50:'#E8EAF6',100:'#C5CAE9',200:'#9FA8DA',300:'#7986CB',400:'#5C6BC0',500:'#3F51B5',600:'#3949AB',700:'#303F9F',800:'#283593',900:'#1A237E' }
                },
                fontFamily: {
                    display: ['Playfair Display', 'serif'],
                    body:    ['Plus Jakarta Sans', 'sans-serif'],
                }
            }
        }
    }
    </script>
    <link rel="stylesheet" href="/clsn-lms/css/custom.css">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-display { font-family: 'Playfair Display', serif; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #FFA726; border-radius: 3px; }
        ::selection { background: #FFA726; color: white; }
        #dash-sidebar { transition: transform 0.3s ease; }
        #dash-sidebar.sidebar-hidden { transform: translateX(-100%); }
        .dash-nav-link.active { background: linear-gradient(135deg, #FFF3E0, #FFE0B2); color: #E65100; }
        .dash-nav-link.active .nav-icon { color: #FB8C00; }
    </style>
</head>
<body class="font-body bg-gray-50 text-gray-900 overflow-x-hidden">

<div class="flex h-screen overflow-hidden">

    <!-- ── SIDEBAR ─────────────────────────────────────────────────── -->
    <aside id="dash-sidebar" class="fixed lg:static inset-y-0 left-0 w-64 bg-navy-900 text-white flex flex-col z-50 lg:z-auto">

        <!-- Brand -->
        <div class="px-6 py-5 border-b border-white/10 flex items-center justify-between">
            <a href="/clsn-lms/index.php" class="flex items-center gap-3">
                <img src="/clsn-lms/images/logo-white.svg" alt="Logo" class="w-9 h-9 object-contain">
                <div>
                    <div class="font-display font-bold text-base leading-tight">Candlelight</div>
                    <div class="text-xs text-candlelight-400">Learning Portal</div>
                </div>
            </a>
            <button id="sidebar-close" class="lg:hidden w-8 h-8 bg-white/10 rounded-full flex items-center justify-center hover:bg-white/20 transition-all">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <!-- Nav Links -->
        <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
            <?php
            $currentPage = basename($_SERVER['PHP_SELF']);
            $navLinks = [
                ['href' => '/clsn-lms/dashboard.php',    'icon' => 'fa-tachometer-alt', 'label' => 'Dashboard',     'page' => 'dashboard.php'],
                ['href' => '/clsn-lms/courses.php',       'icon' => 'fa-graduation-cap', 'label' => 'Browse Courses', 'page' => 'courses.php'],
                ['href' => '/clsn-lms/certificate.php',   'icon' => 'fa-certificate',    'label' => 'My Certificates','page' => 'certificate.php'],
                ['href' => '/clsn-lms/profile.php',       'icon' => 'fa-user-circle',    'label' => 'My Profile',     'page' => 'profile.php'],
            ];
            if (isAdmin()) {
                $navLinks[] = ['href' => '/clsn-lms/admin/index.php', 'icon' => 'fa-cog', 'label' => 'Admin Panel', 'page' => 'admin'];
            }
            foreach ($navLinks as $link):
                $isActive = strpos($currentPage, $link['page']) !== false || ($link['page'] === 'admin' && strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
            ?>
            <a href="<?= $link['href'] ?>" class="dash-nav-link flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 hover:bg-white/10 <?= $isActive ? 'active' : 'text-gray-300' ?>">
                <i class="fas <?= $link['icon'] ?> nav-icon w-5 text-center <?= $isActive ? 'text-candlelight-500' : 'text-gray-400' ?>"></i>
                <span class="font-medium text-sm <?= $isActive ? 'text-orange-900' : '' ?>"><?= $link['label'] ?></span>
            </a>
            <?php endforeach; ?>
        </nav>

        <!-- User + Logout -->
        <div class="px-4 py-4 border-t border-white/10">
            <div class="flex items-center gap-3 px-4 py-3 rounded-xl bg-white/5 mb-2">
                <div class="w-9 h-9 rounded-full bg-candlelight-500 flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                    <?= strtoupper(substr($dashUser['first_name'], 0, 1)) ?>
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-semibold truncate"><?= htmlspecialchars($dashUser['first_name'] . ' ' . $dashUser['last_name']) ?></div>
                    <div class="text-xs text-gray-400 truncate"><?= htmlspecialchars($dashUser['email']) ?></div>
                </div>
            </div>
            <a href="/clsn-lms/logout.php" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-gray-400 hover:text-red-400 hover:bg-white/5 transition-all text-sm">
                <i class="fas fa-sign-out-alt w-5 text-center"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Sidebar overlay (mobile) -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden"></div>

    <!-- ── MAIN CONTENT ──────────────────────────────────────────────── -->
    <div class="flex-1 flex flex-col min-h-screen overflow-y-auto">

        <!-- Top Bar -->
        <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between sticky top-0 z-30">
            <div class="flex items-center gap-4">
                <button id="sidebar-toggle" class="lg:hidden w-9 h-9 flex items-center justify-center rounded-xl hover:bg-gray-100 transition-colors">
                    <i class="fas fa-bars text-gray-600"></i>
                </button>
                <img src="/clsn-lms/images/logo-candlelight.svg" alt="Logo" class="w-8 h-8 object-contain flex-shrink-0">
                <div>
                    <h1 class="font-display font-bold text-xl text-navy-900"><?= htmlspecialchars($dashPageTitle) ?></h1>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="/clsn-lms/courses.php" class="hidden sm:flex items-center gap-2 px-4 py-2 bg-candlelight-50 text-candlelight-700 rounded-xl text-sm font-semibold hover:bg-candlelight-100 transition-colors">
                    <i class="fas fa-plus-circle"></i> Enroll in a Course
                </a>
                <a href="/clsn-lms/profile.php" class="w-9 h-9 rounded-full bg-candlelight-500 flex items-center justify-center text-white font-bold text-sm hover:bg-candlelight-600 transition-colors">
                    <?= strtoupper(substr($dashUser['first_name'], 0, 1)) ?>
                </a>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 p-6 lg:p-8">
