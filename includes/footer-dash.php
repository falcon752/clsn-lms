        </main>
    </div><!-- /main content -->
</div><!-- /flex wrapper -->

<script>
// Sidebar toggle (mobile)
(function() {
    const toggle   = document.getElementById('sidebar-toggle');
    const sidebar  = document.getElementById('dash-sidebar');
    const overlay  = document.getElementById('sidebar-overlay');
    const closeBtn = document.getElementById('sidebar-close');

    function open() {
        sidebar.classList.remove('sidebar-hidden');
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function close() {
        sidebar.classList.add('sidebar-hidden');
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
    }

    // Start hidden on mobile
    if (window.innerWidth < 1024) sidebar.classList.add('sidebar-hidden');

    if (toggle)   toggle.addEventListener('click', open);
    if (closeBtn) closeBtn.addEventListener('click', close);
    if (overlay)  overlay.addEventListener('click', close);

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) {
            sidebar.classList.remove('sidebar-hidden');
            overlay.classList.add('hidden');
            document.body.style.overflow = '';
        } else {
            close();
        }
    });

    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
})();
</script>
</body>
</html>
