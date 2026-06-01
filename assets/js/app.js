// assets/js/app.js

// ── Modal Functions ──────────────────────────────────────
function openModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Klik overlay untuk tutup modal
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
        document.body.style.overflow = '';
    }
});

// Tutup modal dengan ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(el => {
            el.classList.remove('active');
        });
        document.body.style.overflow = '';
    }
});

// ── Auto-dismiss alerts ──────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity .5s, max-height .5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});

// ── Konfirmasi sebelum aksi destruktif ───────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function(e) {
        if (!confirm(this.dataset.confirm)) e.preventDefault();
    });
});
