<!-- FOOTER -->
<footer class="bg-navy-900 text-white relative overflow-hidden mt-20">
    <div class="absolute inset-0 opacity-5">
        <div class="absolute inset-0" style="background-image:url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
    </div>
    <div class="container mx-auto px-4 py-12 relative z-10">
        <div class="grid md:grid-cols-3 gap-10 mb-10">
            <!-- Brand -->
            <div>
                <div class="flex items-center gap-3 mb-5">
                    <img src="/images/logo-white.svg" alt="Candlelight" class="w-12 h-12 object-contain">
                    <div>
                        <div class="font-display font-bold text-lg">Candlelight LMS</div>
                        <div class="text-xs text-gray-400">Learning Portal</div>
                    </div>
                </div>
                <p class="text-gray-400 text-sm leading-relaxed">Empowering educators, parents, and caregivers with evidence-based knowledge to support children with special needs.</p>
            </div>
            <!-- Quick Links -->
            <div>
                <h4 class="font-display font-bold text-lg mb-5">Quick Links</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="/clsn-lms/courses.php"   class="text-gray-400 hover:text-candlelight-400 transition-colors">Browse Courses</a></li>
                    <li><a href="/clsn-lms/dashboard.php" class="text-gray-400 hover:text-candlelight-400 transition-colors">My Dashboard</a></li>
                    <li><a href="https://candlelightspecialneeds.org/about-us" target="_blank" class="text-gray-400 hover:text-candlelight-400 transition-colors">About Us</a></li>
                    <li><a href="https://candlelightspecialneeds.org/contact"  target="_blank" class="text-gray-400 hover:text-candlelight-400 transition-colors">Contact</a></li>
                </ul>
            </div>
            <!-- Contact -->
            <div>
                <h4 class="font-display font-bold text-lg mb-5">Connect With Us</h4>
                <div class="flex gap-3 mb-4">
                    <a href="https://www.facebook.com/share/1DM5CPUhWy/" target="_blank" class="w-9 h-9 bg-white/10 rounded-full flex items-center justify-center hover:bg-candlelight-500 transition-colors"><i class="fab fa-facebook-f text-sm"></i></a>
                    <a href="https://x.com/candlelight_fsn" target="_blank" class="w-9 h-9 bg-white/10 rounded-full flex items-center justify-center hover:bg-candlelight-500 transition-colors"><i class="fab fa-x-twitter text-sm"></i></a>
                    <a href="https://www.instagram.com/candlelight_specialneeds" target="_blank" class="w-9 h-9 bg-white/10 rounded-full flex items-center justify-center hover:bg-candlelight-500 transition-colors"><i class="fab fa-instagram text-sm"></i></a>
                    <a href="https://youtube.com/@candlelightspecialneeds" target="_blank" class="w-9 h-9 bg-white/10 rounded-full flex items-center justify-center hover:bg-candlelight-500 transition-colors"><i class="fab fa-youtube text-sm"></i></a>
                </div>
                <p class="text-gray-400 text-sm">A platform by<br><strong class="text-white">Candlelight Foundation for Children With Special Needs</strong></p>
            </div>
        </div>
        <div class="border-t border-white/10 pt-6 flex flex-col md:flex-row justify-between items-center gap-4 text-sm text-gray-400">
            <p>© <?= date('Y') ?> Candlelight Foundation. All Rights Reserved.</p>
            <div class="flex gap-6">
                <a href="https://candlelightspecialneeds.org/privacy-policy" target="_blank" class="hover:text-candlelight-400 transition-colors">Privacy Policy</a>
                <a href="https://candlelightspecialneeds.org/terms"          target="_blank" class="hover:text-candlelight-400 transition-colors">Terms</a>
            </div>
        </div>
    </div>
</footer>

<!-- Scroll to Top -->
<button id="scroll-top" class="fixed bottom-8 right-8 w-12 h-12 bg-candlelight-500 text-white rounded-full shadow-2xl hover:bg-candlelight-600 transition-all duration-300 opacity-0 pointer-events-none z-40 flex items-center justify-center">
    <i class="fas fa-arrow-up"></i>
</button>

<script>
// Mobile menu
(function() {
    const btn   = document.getElementById('mobile-menu-btn');
    const menu  = document.getElementById('mobile-menu');
    const close = document.getElementById('mobile-menu-close');
    if (!btn || !menu) return;
    let open = false;
    function toggle(show) {
        open = typeof show === 'boolean' ? show : !open;
        if (open) {
            menu.classList.remove('hidden'); menu.classList.add('flex');
            document.body.classList.add('menu-open');
            btn.querySelector('i').className = 'fas fa-times text-gray-700 text-xl';
        } else {
            menu.classList.add('hidden'); menu.classList.remove('flex');
            document.body.classList.remove('menu-open');
            btn.querySelector('i').className = 'fas fa-bars text-gray-700 text-xl';
        }
    }
    btn.addEventListener('click', e => { e.stopPropagation(); toggle(); });
    if (close) close.onclick = e => { e.preventDefault(); toggle(false); };
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && open) toggle(false); });
    window.addEventListener('resize', () => { if (window.innerWidth >= 1024 && open) toggle(false); });
})();

// Navbar scroll
(function() {
    const nav = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
        nav && nav.classList.toggle('scrolled', window.scrollY > 50);
    });
})();

// Scroll to top
(function() {
    const btn = document.getElementById('scroll-top');
    if (!btn) return;
    window.addEventListener('scroll', () => {
        btn.classList.toggle('opacity-0',       window.scrollY < 400);
        btn.classList.toggle('pointer-events-none', window.scrollY < 400);
    });
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
})();
</script>
</body>
</html>
