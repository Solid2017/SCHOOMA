        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    // Sidebar toggle (mobile)
    var sidebar = document.getElementById('adminSidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var sidebarOverlay = document.getElementById('sidebarOverlay');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            sidebarOverlay.classList.toggle('active');
        });
    }
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function () {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('active');
        });
    }

    // User dropdown
    var userToggle = document.getElementById('userMenuToggle');
    var userDropdown = document.getElementById('userDropdown');
    if (userToggle) {
        userToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            userDropdown.classList.toggle('open');
        });
        document.addEventListener('click', function () { userDropdown.classList.remove('open'); });
    }

    // Modal helpers (data-modal-open="id" / data-modal-close)
    document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var modal = document.getElementById(btn.getAttribute('data-modal-open'));
            if (modal) modal.classList.add('open');
        });
    });
    document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.closest('.modal-overlay').classList.remove('open');
        });
    });
    document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });

    // Confirmation avant suppression
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.getAttribute('data-confirm') || 'Confirmer cette action ?')) {
                e.preventDefault();
            }
        });
    });

    // Auto-hide alerts
    document.querySelectorAll('.alert').forEach(function (alertEl) {
        setTimeout(function () { alertEl.style.display = 'none'; }, 6000);
    });
})();
</script>
</body>
</html>
