        </main>
    </div>
</div>

<!-- Shared Confirmation Modal -->
<div id="confirm-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" id="modal-backdrop"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 flex flex-col items-center text-center">
        <div id="modal-icon" class="w-14 h-14 rounded-full flex items-center justify-center text-2xl mb-4"></div>
        <h3 id="modal-title" class="font-display font-bold text-navy-900 text-lg mb-2"></h3>
        <p id="modal-body" class="text-gray-500 text-sm mb-6"></p>
        <div class="flex gap-3 w-full">
            <button id="modal-cancel" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 font-semibold text-sm hover:bg-gray-50 transition-colors">Cancel</button>
            <button id="modal-confirm" class="flex-1 px-4 py-2.5 rounded-xl font-semibold text-sm text-white transition-colors"></button>
        </div>
    </div>
</div>

<script>
// ── Shared modal helper (available on every admin page) ─────────
function showModal({ icon = '<i class="fas fa-exclamation-triangle"></i>', iconClass = 'bg-red-100 text-red-600', title, body, confirmLabel = 'Confirm', confirmClass = 'bg-red-600 hover:bg-red-700' } = {}) {
    return new Promise(resolve => {
        const modal      = document.getElementById('confirm-modal');
        const iconEl     = document.getElementById('modal-icon');
        const titleEl    = document.getElementById('modal-title');
        const bodyEl     = document.getElementById('modal-body');
        const confirmBtn = document.getElementById('modal-confirm');
        const cancelBtn  = document.getElementById('modal-cancel');
        const backdrop   = document.getElementById('modal-backdrop');

        iconEl.className     = `w-14 h-14 rounded-full flex items-center justify-center text-2xl mb-4 ${iconClass}`;
        iconEl.innerHTML     = icon;
        titleEl.textContent  = title;
        bodyEl.textContent   = body;
        confirmBtn.textContent = confirmLabel;
        confirmBtn.className   = `flex-1 px-4 py-2.5 rounded-xl font-semibold text-sm text-white transition-colors ${confirmClass}`;

        modal.classList.remove('hidden');

        const close = (result) => {
            modal.classList.add('hidden');
            confirmBtn.onclick = null;
            cancelBtn.onclick  = null;
            backdrop.onclick   = null;
            resolve(result);
        };
        confirmBtn.onclick = () => close(true);
        cancelBtn.onclick  = () => close(false);
        backdrop.onclick   = () => close(false);
    });
}

// ── data-confirm delegation (replaces native confirm() on forms/buttons) ──
document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-confirm]');
    if (!btn) return;
    e.preventDefault();
    e.stopImmediatePropagation();

    const msg   = btn.dataset.confirm;
    const title = btn.dataset.confirmTitle  || 'Are you sure?';
    const label = btn.dataset.confirmLabel  || 'Yes, proceed';
    const cls   = btn.dataset.confirmClass  || 'bg-red-600 hover:bg-red-700';
    const icon  = btn.dataset.confirmIcon   || '<i class="fas fa-exclamation-triangle"></i>';
    const icCls = btn.dataset.confirmIconClass || 'bg-red-100 text-red-600';

    showModal({ icon, iconClass: icCls, title, body: msg, confirmLabel: label, confirmClass: cls })
        .then(confirmed => {
            if (!confirmed) return;
            // If inside a form, submit it; otherwise click the element directly
            const form = btn.form || btn.closest('form');
            if (form) {
                // Temporarily remove data-confirm so re-click doesn't loop
                btn.removeAttribute('data-confirm');
                form.submit();
            } else {
                btn.removeAttribute('data-confirm');
                btn.click();
            }
        });
});
</script>

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
