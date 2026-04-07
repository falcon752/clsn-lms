<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($conn))     include_once __DIR__ . '/db.php';
if (!function_exists('isLoggedIn')) include_once __DIR__ . '/auth.php';

$pageTitle       = $pageTitle       ?? 'Candlelight LMS | Learn. Grow. Shine.';
$pageDescription = $pageDescription ?? 'Candlelight Foundation Learning Management System: online courses for autism education, therapy training, and family support.';
$ogTitle         = $ogTitle         ?? $pageTitle;
$ogDescription   = $ogDescription   ?? $pageDescription;
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="index, follow">
    <link rel="icon" type="image/x-icon" href="/clsn-lms/images/favicon.ico">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

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

    <!-- Custom LMS CSS -->
    <link rel="stylesheet" href="/clsn-lms/css/custom.css">

    <style>
        html, body { overflow-x: hidden; }
        .font-display { font-family: 'Playfair Display', serif; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        #navbar.scrolled { background: rgba(255,255,255,0.97) !important; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .nav-link::after { content:''; position:absolute; bottom:-4px; left:0; width:0; height:2px; background:linear-gradient(90deg,#FFA726,#FB8C00); transition:width .3s; }
        .nav-link:hover::after { width:100%; }
        ::-webkit-scrollbar { width:8px; }
        ::-webkit-scrollbar-track { background:#1A237E; }
        ::-webkit-scrollbar-thumb { background:linear-gradient(180deg,#FFA726,#FB8C00); border-radius:4px; }
        ::selection { background:#FFA726; color:#fff; }
        #mobile-menu.hidden { display:none !important; }
        #mobile-menu.flex   { display:flex !important; }
        body.menu-open { overflow:hidden; }
        #mobile-menu { z-index: 2147483647 !important; }
    </style>
</head>
<body class="font-body antialiased bg-white text-gray-900 overflow-x-hidden">

<!-- NAVIGATION -->
<nav id="navbar" class="fixed w-full top-0 z-50 transition-all duration-300">
    <div class="container mx-auto px-4 py-4">
        <div class="flex items-center justify-between bg-white/90 backdrop-blur-md rounded-2xl shadow-lg px-6 py-3">

            <!-- Logo -->
            <a href="/clsn-lms/index.php" class="flex items-center space-x-3">
                <img src="/clsn-lms/images/logo-candlelight.svg" alt="Candlelight LMS" class="w-10 h-10 object-contain">
                <div>
                    <div class="font-display font-bold text-lg text-navy-800 leading-tight">Candlelight</div>
                    <div class="text-xs text-candlelight-600 font-semibold tracking-wide">Learning Portal</div>
                </div>
            </a>

            <!-- Desktop Nav -->
            <div class="hidden lg:flex items-center space-x-8">
                <a href="/clsn-lms/index.php" class="nav-link relative text-gray-700 hover:text-candlelight-600 transition-colors font-medium">Home</a>
                <a href="/clsn-lms/courses.php" class="nav-link relative text-gray-700 hover:text-candlelight-600 transition-colors font-medium">Courses</a>
                <a href="https://candlelightspecialneeds.org" target="_blank" class="nav-link relative text-gray-700 hover:text-candlelight-600 transition-colors font-medium">Main Site</a>
            </div>

            <!-- Desktop Right -->
            <div class="hidden lg:flex items-center gap-4">
                <?php if (isLoggedIn()): $u = currentUser(); ?>
                    <a href="/clsn-lms/dashboard.php" class="flex items-center gap-2 text-gray-700 hover:text-candlelight-600 transition-colors font-medium">
                        <span class="w-8 h-8 rounded-full bg-candlelight-500 flex items-center justify-center text-white text-sm font-bold">
                            <?= strtoupper(substr($u['first_name'], 0, 1)) ?>
                        </span>
                        <span><?= htmlspecialchars($u['first_name']) ?></span>
                    </a>
                    <a href="/clsn-lms/logout.php" class="px-5 py-2 border-2 border-gray-300 text-gray-600 rounded-full font-semibold hover:border-red-400 hover:text-red-500 transition-all duration-300 text-sm">Logout</a>
                <?php else: ?>
                    <a href="/clsn-lms/login.php" class="text-gray-700 hover:text-candlelight-600 transition-colors font-medium">Login</a>
                    <a href="/clsn-lms/register.php" class="px-6 py-2.5 bg-gradient-to-r from-candlelight-500 to-candlelight-600 text-white rounded-full font-semibold shadow-lg hover:shadow-candlelight-500/40 hover:scale-105 transition-all duration-300 text-sm">Get Started</a>
                <?php endif; ?>
            </div>

            <!-- Mobile Menu Button -->
            <button id="mobile-menu-btn" aria-expanded="false" aria-label="Open menu" class="lg:hidden w-10 h-10 flex items-center justify-center rounded-xl hover:bg-gray-100 transition-colors">
                <i class="fas fa-bars text-gray-700 text-xl"></i>
            </button>
        </div>
    </div>
</nav>

<!-- MOBILE MENU -->
<div id="mobile-menu" class="hidden fixed inset-0 bg-navy-900 flex-col z-[2147483647]">
    <div class="flex items-center justify-between px-6 py-5 border-b border-white/10">
        <div class="flex items-center gap-3">
            <img src="/clsn-lms/images/logo-white.svg" alt="Candlelight LMS" class="w-9 h-9 object-contain">
            <div>
                <div class="font-display font-bold text-white">Candlelight LMS</div>
                <div class="text-xs text-candlelight-400">Learning Portal</div>
            </div>
        </div>
        <button id="mobile-menu-close" class="w-10 h-10 bg-white/10 rounded-full flex items-center justify-center text-white hover:bg-white/20 transition-all">
            <i class="fas fa-times text-lg"></i>
        </button>
    </div>
    <div class="flex-1 overflow-y-auto px-6 py-8 space-y-2">
        <a href="/clsn-lms/index.php"   class="flex items-center gap-3 px-4 py-3 text-white hover:bg-white/10 rounded-xl transition-all"><i class="fas fa-home w-5 text-candlelight-400"></i>Home</a>
        <a href="/clsn-lms/courses.php" class="flex items-center gap-3 px-4 py-3 text-white hover:bg-white/10 rounded-xl transition-all"><i class="fas fa-graduation-cap w-5 text-candlelight-400"></i>Courses</a>
        <?php if (isLoggedIn()): ?>
            <a href="/clsn-lms/dashboard.php" class="flex items-center gap-3 px-4 py-3 text-white hover:bg-white/10 rounded-xl transition-all"><i class="fas fa-tachometer-alt w-5 text-candlelight-400"></i>Dashboard</a>
            <a href="/clsn-lms/logout.php"    class="flex items-center gap-3 px-4 py-3 text-red-400 hover:bg-white/10 rounded-xl transition-all"><i class="fas fa-sign-out-alt w-5"></i>Logout</a>
        <?php else: ?>
            <div class="pt-4 space-y-3">
                <a href="/clsn-lms/login.php"    class="block text-center px-6 py-3 border-2 border-white/30 text-white rounded-full font-semibold hover:bg-white/10 transition">Login</a>
                <a href="/clsn-lms/register.php" class="block text-center px-6 py-3 bg-candlelight-500 text-white rounded-full font-semibold hover:bg-candlelight-600 transition">Get Started</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- SPACER for fixed nav -->
<div class="h-24"></div>
