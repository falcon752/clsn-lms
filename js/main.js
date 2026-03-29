/**
 * CLSN LMS — Main JavaScript
 */
document.addEventListener('DOMContentLoaded', function () {

    // ── Tab System ─────────────────────────────────────────────────────────
    document.querySelectorAll('[data-tab-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var container = this.closest('[data-tab-container]') || document;
            var target    = this.getAttribute('data-tab-target');

            // Deactivate all tab buttons
            container.querySelectorAll('[data-tab-target]').forEach(function (b) {
                b.classList.remove('active');
            });
            // Hide all panels
            container.querySelectorAll('.tab-panel').forEach(function (p) {
                p.classList.remove('active');
            });

            this.classList.add('active');
            var panel = container.querySelector('#' + target);
            if (panel) panel.classList.add('active');
        });
    });

    // ── Quiz: Option Selection ─────────────────────────────────────────────
    document.querySelectorAll('.quiz-option').forEach(function (label) {
        label.addEventListener('click', function () {
            var name = this.querySelector('input[type="radio"]').getAttribute('name');
            document.querySelectorAll('.quiz-option input[name="' + name + '"]').forEach(function (r) {
                r.closest('.quiz-option').style.borderColor = '';
                r.closest('.quiz-option').style.background  = '';
            });
            this.querySelector('input[type="radio"]').checked = true;
        });
    });

    // ── Quiz: Validate all answered before submit ──────────────────────────
    var quizForm = document.getElementById('quiz-form');
    if (quizForm) {
        quizForm.addEventListener('submit', function (e) {
            var questions = quizForm.querySelectorAll('[data-question]');
            var allAnswered = true;
            questions.forEach(function (q) {
                var name = q.getAttribute('data-question');
                if (!quizForm.querySelector('input[name="' + name + '"]:checked')) {
                    allAnswered = false;
                    q.classList.add('ring-2', 'ring-red-400');
                } else {
                    q.classList.remove('ring-2', 'ring-red-400');
                }
            });
            if (!allAnswered) {
                e.preventDefault();
                var el = document.getElementById('quiz-warn');
                if (el) { el.classList.remove('hidden'); el.scrollIntoView({ behavior: 'smooth' }); }
            }
        });
    }

    // ── Mark Video Watched (AJAX) ──────────────────────────────────────────
    var watchBtn = document.getElementById('mark-watched-btn');
    if (watchBtn) {
        watchBtn.addEventListener('click', function () {
            var moduleId  = this.getAttribute('data-module-id');
            var courseId  = this.getAttribute('data-course-id');
            var btn       = this;
            btn.disabled  = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';

            fetch('/clsn-lms/ajax/mark-watched.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'module_id=' + encodeURIComponent(moduleId) + '&course_id=' + encodeURIComponent(courseId)
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    btn.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Video Watched!';
                    btn.classList.remove('bg-navy-900', 'hover:bg-navy-800');
                    btn.classList.add('bg-green-600', 'cursor-default');
                    // Show quiz button
                    var quizSection = document.getElementById('quiz-unlock-section');
                    if (quizSection) quizSection.classList.remove('hidden');
                } else {
                    btn.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>' + (data.message || 'Error');
                    btn.disabled = false;
                }
            })
            .catch(function () {
                btn.innerHTML = '<i class="fas fa-wifi mr-2"></i>Network Error. Retry.';
                btn.disabled = false;
            });
        });
    }

    // ── Q&A Form (AJAX) ────────────────────────────────────────────────────
    var qaForm = document.getElementById('qa-form');
    if (qaForm) {
        qaForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var submitBtn = qaForm.querySelector('[type="submit"]');
            var question  = qaForm.querySelector('[name="question"]').value.trim();
            if (!question) return;

            submitBtn.disabled  = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';

            fetch('/clsn-lms/ajax/submit-qa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    new URLSearchParams(new FormData(qaForm)).toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    qaForm.querySelector('[name="question"]').value = '';
                    var list = document.getElementById('qa-list');
                    if (list) {
                        var item = document.createElement('div');
                        item.className = 'qa-item py-3';
                        item.innerHTML = '<p class="font-semibold text-gray-800 text-sm"><i class="fas fa-question-circle text-candlelight-500 mr-1"></i>' + escapeHtml(question) + '</p><p class="text-xs text-gray-400 mt-1">Just now — awaiting answer</p>';
                        list.insertBefore(item, list.firstChild);
                    }
                    submitBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Question Submitted!';
                    setTimeout(function () {
                        submitBtn.disabled  = false;
                        submitBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Submit Question';
                    }, 3000);
                } else {
                    submitBtn.innerHTML = data.message || 'Error. Try again.';
                    submitBtn.disabled  = false;
                }
            })
            .catch(function () {
                submitBtn.innerHTML = 'Network error. Try again.';
                submitBtn.disabled  = false;
            });
        });
    }

    // ── Progress Bar Animation ─────────────────────────────────────────────
    document.querySelectorAll('.progress-bar-fill[data-percent]').forEach(function (el) {
        var target  = parseInt(el.getAttribute('data-percent'), 10) || 0;
        el.style.width = '0%';
        setTimeout(function () { el.style.width = target + '%'; }, 300);
    });

    // ── Counter Animation ──────────────────────────────────────────────────
    document.querySelectorAll('.count-up[data-target]').forEach(function (el) {
        var target  = parseInt(el.getAttribute('data-target'), 10);
        var current = 0;
        var step    = Math.ceil(target / 60);
        var timer   = setInterval(function () {
            current += step;
            if (current >= target) { current = target; clearInterval(timer); }
            el.textContent = current;
        }, 20);
    });

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
});
