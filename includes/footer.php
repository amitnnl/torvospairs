    </div><!-- .main-content -->
</div><!-- .app-layout -->

<script>
// ---- Sidebar Toggle ----
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('show');
}

// ---- Auto dismiss flash alerts ----
document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity 0.4s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 400);
    }, 4000);
});

// ---- Confirm delete ----
function confirmDelete(msg) {
    return confirm(msg || 'Are you sure you want to delete this item?');
}

// ---- Live Search (AJAX) ----
(function() {
    const input    = document.getElementById('globalSearch');
    const dropdown = document.getElementById('searchDropdown');
    if (!input || !dropdown) return;

    let debounceTimer;

    input.addEventListener('input', function() {
        const q = this.value.trim();
        clearTimeout(debounceTimer);

        if (q.length < 2) { dropdown.style.display = 'none'; return; }

        debounceTimer = setTimeout(() => {
            fetch(`<?= APP_URL ?>/api/search.php?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(data => {
                    const results = (data.data && data.data.results) || data.results || [];
                    if (!results.length) {
                        dropdown.innerHTML = `<div style="padding:1rem;font-size:0.83rem;color:var(--text-muted);text-align:center;">No results for "<strong>${q}</strong>"</div>`;
                    } else {
                        dropdown.innerHTML = results.map(r => `
                            <a href="${r.url}" style="display:flex;align-items:center;gap:0.75rem;padding:0.65rem 1rem;border-bottom:1px solid var(--border-color);text-decoration:none;transition:background 0.15s;" onmouseover="this.style.background='var(--bg-card2)'" onmouseout="this.style.background=''">
                                <div style="width:32px;height:32px;border-radius:8px;background:${r.type==='product'?'rgba(108,99,255,0.1)':'rgba(72,218,245,0.1)'};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i class="fas fa-${r.type==='product'?'box':'tools'}" style="font-size:0.8rem;color:${r.type==='product'?'var(--primary)':'var(--accent)'}"></i>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-size:0.83rem;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${r.title}</div>
                                    <div style="font-size:0.72rem;color:var(--text-muted);">${r.sub}</div>
                                </div>
                                <span style="font-size:0.7rem;padding:2px 7px;border-radius:20px;background:var(--bg-main);color:var(--text-muted);white-space:nowrap;">${r.badge}</span>
                            </a>
                        `).join('');
                    }
                    dropdown.style.display = 'block';
                })
                .catch(() => { dropdown.style.display = 'none'; });
        }, 280);
    });

    // Enter key: navigate to products search
    input.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && this.value.trim()) {
            window.location.href = `<?= APP_URL ?>/pages/products.php?search=${encodeURIComponent(this.value.trim())}`;
        }
    });

    // Close dropdown on outside click
    document.addEventListener('click', function(e) {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
})();


// ---- Modal helpers ----
function openModal(id) {
    document.getElementById(id).classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('show');
    document.body.style.overflow = '';
}
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});

// ---- Escape key closes modals ----
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.show').forEach(m => {
            m.classList.remove('show');
            document.body.style.overflow = '';
        });
    }
});
</script>
</body>
</html>
