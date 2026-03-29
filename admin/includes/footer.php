        </main>
    </div>
</div>

<script>
(function() {
    const toggle   = document.getElementById('admin-sidebar-toggle');
    const sidebar  = document.getElementById('admin-sidebar');
    const overlay  = document.getElementById('admin-overlay');
    const closeBtn = document.getElementById('admin-sidebar-close');

    function open()  { sidebar.classList.remove('sidebar-hidden'); overlay.classList.remove('hidden'); document.body.style.overflow='hidden'; }
    function close() { sidebar.classList.add('sidebar-hidden');    overlay.classList.add('hidden');    document.body.style.overflow=''; }

    if (window.innerWidth < 1024) sidebar.classList.add('sidebar-hidden');

    toggle?.addEventListener('click', open);
    closeBtn?.addEventListener('click', close);
    overlay?.addEventListener('click', close);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) { sidebar.classList.remove('sidebar-hidden'); overlay.classList.add('hidden'); document.body.style.overflow=''; }
        else close();
    });
})();
</script>
</body>
</html>
