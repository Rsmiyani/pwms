// Party Worker Management System - Main JS

// Toggle sidebar on mobile (also toggles overlay backdrop)
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
    var overlay = document.getElementById('sidebar-overlay');
    if (overlay) overlay.classList.toggle('active');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(e) {
    var sidebar = document.querySelector('.sidebar');
    var toggle = document.querySelector('.menu-toggle');
    var overlay = document.getElementById('sidebar-overlay');
    if (sidebar && toggle && window.innerWidth <= 768) {
        if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('active');
        }
    }

    // Close notification panel when clicking outside
    var notifBell = document.getElementById('notif-bell');
    var notifPanel = document.getElementById('notif-panel');
    if (notifPanel && notifBell && !notifBell.contains(e.target) && !notifPanel.contains(e.target)) {
        notifPanel.classList.remove('open');
    }
});

// ===== DARK MODE =====
function toggleDarkMode() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var next = isDark ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('pwms_theme', next);
    var icon = document.getElementById('dark-icon');
    if (icon) {
        icon.className = next === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
}

// Sync dark mode icon on load
document.addEventListener('DOMContentLoaded', function() {
    var icon = document.getElementById('dark-icon');
    if (icon && document.documentElement.getAttribute('data-theme') === 'dark') {
        icon.className = 'fas fa-sun';
    }
});

// Auto-hide flash messages after 4 seconds
document.addEventListener('DOMContentLoaded', function() {
    var alerts = document.querySelectorAll('.alert:not([data-persist])');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(function() { alert.remove(); }, 500);
        }, 4000);
    });

    // Start notification polling
    fetchNotifications();
    setInterval(fetchNotifications, 30000);

    // Table sorting
    initTableSort();

    // Form submit loading states (POST forms only)
    initFormLoadingStates();
});

// Confirm delete
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this item?');
}

// ===== TABLE SORTING =====
function initTableSort() {
    document.querySelectorAll('.data-table').forEach(function(table) {
        var headers = table.querySelectorAll('th[data-sort]');
        headers.forEach(function(th) {
            th.style.cursor = 'pointer';
            th.classList.add('sortable-th');
            th.addEventListener('click', function() {
                var dir = th.getAttribute('data-sort-dir') === 'asc' ? 'desc' : 'asc';
                headers.forEach(function(h) {
                    h.removeAttribute('data-sort-dir');
                    h.classList.remove('sort-asc', 'sort-desc');
                });
                th.setAttribute('data-sort-dir', dir);
                th.classList.add('sort-' + dir);
                var tbody = table.querySelector('tbody');
                if (!tbody) return;
                var rows = Array.from(tbody.querySelectorAll('tr'));
                var colIdx = Array.from(th.parentElement.children).indexOf(th);
                rows.sort(function(a, b) {
                    var aVal = (a.cells[colIdx] ? a.cells[colIdx].textContent.trim() : '').toLowerCase();
                    var bVal = (b.cells[colIdx] ? b.cells[colIdx].textContent.trim() : '').toLowerCase();
                    var aNum = parseFloat(aVal.replace(/[^0-9.-]/g, ''));
                    var bNum = parseFloat(bVal.replace(/[^0-9.-]/g, ''));
                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return dir === 'asc' ? aNum - bNum : bNum - aNum;
                    }
                    return dir === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
                });
                rows.forEach(function(r) { tbody.appendChild(r); });
            });
        });
    });
}

// ===== FORM LOADING STATES =====
function initFormLoadingStates() {
    document.querySelectorAll('form[method="post"], form[method="POST"]').forEach(function(form) {
        // Skip filter/search forms that only have GET params or no submit btn
        var btn = form.querySelector('button[type="submit"]');
        if (!btn) return;
        form.addEventListener('submit', function() {
            if (btn.disabled) return; // already submitted
            btn.disabled = true;
            var original = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + btn.textContent.trim();
            // Re-enable after 8 s (fallback if page doesn't navigate)
            setTimeout(function() {
                btn.disabled = false;
                btn.innerHTML = original;
            }, 8000);
        });
    });
}

// ===== NOTIFICATION SYSTEM =====
function toggleNotifPanel() {
    var panel = document.getElementById('notif-panel');
    if (panel) panel.classList.toggle('open');
}

function fetchNotifications() {
    var countEl = document.getElementById('notif-count');
    var listEl = document.getElementById('notif-list');
    if (!countEl || !listEl) return;

    var xhr = new XMLHttpRequest();
    xhr.open('GET', window.BASE_URL + '/api/notifications.php', true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (data.unread_count > 0) {
                    countEl.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                    countEl.style.display = 'flex';
                } else {
                    countEl.style.display = 'none';
                }
                if (data.notifications.length === 0) {
                    listEl.innerHTML = '<div class="notif-empty">No notifications</div>';
                } else {
                    var html = '';
                    data.notifications.forEach(function(n) {
                        var iconClass = n.type === 'task_assigned' || n.type === 'task_updated' ? 'task' : 'general';
                        var iconName = n.type === 'task_assigned' ? 'fa-tasks' : (n.type === 'task_updated' ? 'fa-edit' : 'fa-bell');
                        html += '<div class="notif-item' + (n.is_read == 0 ? ' unread' : '') + '" onclick="openNotification(' + n.id + ', \'' + (n.link || '') + '\')">';
                        html += '<div class="notif-icon ' + iconClass + '"><i class="fas ' + iconName + '"></i></div>';
                        html += '<div class="notif-content"><div class="notif-title">' + escapeHtml(n.title) + '</div><div class="notif-msg">' + escapeHtml(n.message) + '</div></div>';
                        html += '<div class="notif-time">' + escapeHtml(n.time_ago) + '</div>';
                        html += '</div>';
                    });
                    listEl.innerHTML = html;
                }
            } catch (e) { /* ignore parse errors */ }
        }
    };
    xhr.send();
}

function openNotification(id, link) {
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var csrfVal = csrf ? csrf.getAttribute('content') : '';
    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.BASE_URL + '/api/notifications.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() { fetchNotifications(); };
    xhr.send('action=mark_read&id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(csrfVal));
    // Only allow local navigation (prevent open redirect / javascript: links)
    if (link && link.indexOf('/') === 0 && link.indexOf('//') !== 0) {
        window.location.href = link;
    }
}

function markAllRead() {
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var csrfVal = csrf ? csrf.getAttribute('content') : '';
    var xhr = new XMLHttpRequest();
    xhr.open('POST', window.BASE_URL + '/api/notifications.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() { fetchNotifications(); };
    xhr.send('action=mark_all_read&csrf_token=' + encodeURIComponent(csrfVal));
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

