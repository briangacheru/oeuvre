// Admin command palette (Ctrl+K / Cmd+K). A fixed list of "go to" shortcuts
// filtered client-side, plus live task/writer/file results from the
// existing sudo/search.php (same endpoint topbar-search.js uses) so typing
// a task number or writer name jumps straight to it. Include on every
// sudo/ page via sudo/navi.php.
(function () {
    const COMMANDS = [
        { label: 'New Task', icon: 'fa-plus', url: 'create-task' },
        { label: 'All Tasks', icon: 'fa-database', url: 'all-tasks' },
        { label: 'Draft Tasks', icon: 'fa-edit', url: 'draft-tasks' },
        { label: 'Unconfirmed Tasks', icon: 'fa-question-circle', url: 'unconfirmed' },
        { label: 'Tasks In Progress', icon: 'fa-spinner', url: 'tasks-in-progress' },
        { label: 'Tasks In Revision', icon: 'fa-flag', url: 'tasks-in-revision' },
        { label: 'Submitted Tasks', icon: 'fa-check', url: 'submitted-tasks' },
        { label: 'Completed Tasks', icon: 'fa-check-double', url: 'completed-tasks' },
        { label: 'Cancelled Tasks', icon: 'fa-ban', url: 'cancelled-tasks' },
        { label: 'Paid Tasks', icon: 'fa-money-bill-wave', url: 'paid-tasks' },
        { label: 'Unpaid Tasks', icon: 'fa-file-invoice-dollar', url: 'unpaid-tasks' },
        { label: 'Extension Requests', icon: 'fa-calendar-plus', url: 'extension-requests' },
        { label: 'Writers', icon: 'fa-users', url: 'usermanagement' },
        { label: 'Chat', icon: 'fa-comments', url: 'chat' },
        { label: 'Calendar', icon: 'fa-calendar', url: 'calendar' },
        { label: 'To Do', icon: 'fa-tasks', url: 'todo' },
        { label: 'Projects', icon: 'fa-project-diagram', url: 'projects' },
        { label: 'Transactions', icon: 'fa-exchange-alt', url: 'transactions' },
        { label: 'Analytics', icon: 'fa-chart-line', url: 'analytics' },
        { label: 'Bonus Settings', icon: 'fa-coins', url: 'bonus-settings' },
        { label: 'Bonus History', icon: 'fa-history', url: 'bonus-history' },
        { label: 'Activity Log', icon: 'fa-history', url: 'activity-log' },
        { label: 'Changelog', icon: 'fa-code-branch', url: 'changelog' },
        { label: 'Settings', icon: 'fa-cog', url: 'settings' },
        { label: 'Manage Admins', icon: 'fa-user-shield', url: 'manage-admins' },
    ];

    document.addEventListener('DOMContentLoaded', function () {
        const overlay = document.createElement('div');
        overlay.id = 'cmdPaletteOverlay';
        overlay.style.cssText = 'display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.4);padding-top:12vh;';
        overlay.innerHTML = ''
            + '<div style="max-width:600px;margin:0 auto;background:var(--falcon-card-bg,#fff);border-radius:.75rem;overflow:hidden;box-shadow:0 1rem 3rem rgba(0,0,0,.3);">'
            + '  <div class="p-2 border-bottom d-flex align-items-center">'
            + '    <i class="fas fa-search text-secondary ms-2 me-2"></i>'
            + '    <input id="cmdPaletteInput" type="text" class="form-control border-0 shadow-none" placeholder="Jump to a page, task, or writer... (Esc to close)" autocomplete="off">'
            + '  </div>'
            + '  <div id="cmdPaletteResults" style="max-height:60vh;overflow-y:auto;"></div>'
            + '</div>';
        document.body.appendChild(overlay);

        const input = overlay.querySelector('#cmdPaletteInput');
        const results = overlay.querySelector('#cmdPaletteResults');
        let activeIndex = 0;
        let currentItems = [];
        let searchDebounce = null;

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str == null ? '' : String(str);
            return div.innerHTML;
        }

        function open() {
            overlay.style.display = 'block';
            input.value = '';
            renderCommands('');
            setTimeout(function () { input.focus(); }, 30);
        }

        function close() {
            overlay.style.display = 'none';
        }

        function renderItems(items) {
            currentItems = items;
            activeIndex = 0;
            if (!items.length) {
                results.innerHTML = '<div class="p-3 text-center text-600">No matches.</div>';
                return;
            }
            results.innerHTML = items.map(function (item, i) {
                return '<a href="' + escapeHtml(item.url) + '" class="d-flex align-items-center px-3 py-2 text-decoration-none cmd-palette-item' + (i === 0 ? ' active' : '') + '" data-index="' + i + '" style="color:inherit;' + (i === 0 ? 'background:var(--falcon-emphasis-bg,#f0f4ff);' : '') + '">'
                    + '<i class="fas ' + escapeHtml(item.icon || 'fa-arrow-right') + ' me-2 text-secondary" style="width:18px;"></i>'
                    + '<span>' + escapeHtml(item.label) + '</span>'
                    + (item.sub ? '<span class="ms-2 text-600 fs-11">' + escapeHtml(item.sub) + '</span>' : '')
                    + '</a>';
            }).join('');

            // vendors/fontawesome/all.min.js is the "SVG with JS" build - it
            // replaces <i class="fas fa-x"> with an inline <svg> and relies
            // on its own MutationObserver to catch elements added later.
            // That observer is asynchronous/batched, so a whole batch of
            // icons written via one innerHTML assignment (like this one) can
            // still be mid-flight the instant this function returns - most
            // convert before the next paint, but not reliably all of them.
            // Forcing a synchronous, targeted i2svg() pass on just this
            // container removes that race instead of hoping the passive
            // watcher gets to every icon in time.
            if (window.FontAwesome && window.FontAwesome.dom && typeof window.FontAwesome.dom.i2svg === 'function') {
                window.FontAwesome.dom.i2svg({ node: results });
            }
        }

        function renderCommands(query) {
            const q = query.trim().toLowerCase();
            const matches = q === '' ? COMMANDS : COMMANDS.filter(function (c) {
                return c.label.toLowerCase().indexOf(q) !== -1;
            });
            renderItems(matches);

            if (q.length >= 2) {
                clearTimeout(searchDebounce);
                searchDebounce = setTimeout(function () { runLiveSearch(q); }, 250);
            }
        }

        function runLiveSearch(q) {
            fetch('search?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.success || !data.groups) return;
                    const liveItems = [];
                    data.groups.forEach(function (group) {
                        (group.items || []).slice(0, 5).forEach(function (it) {
                            const primaryUrl = it.actions && it.actions.length ? it.actions[0].url : null;
                            if (!primaryUrl) return;
                            liveItems.push({ label: it.title, sub: group.label, url: primaryUrl, icon: 'fa-arrow-right' });
                        });
                    });
                    if (liveItems.length && input.value.trim().toLowerCase() === q) {
                        renderItems(liveItems.concat(currentItems));
                    }
                })
                .catch(function () {});
        }

        input.addEventListener('input', function () { renderCommands(input.value); });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(activeIndex + 1, currentItems.length - 1);
                highlightActive();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, 0);
                highlightActive();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const item = currentItems[activeIndex];
                if (item) window.location.href = item.url;
            } else if (e.key === 'Escape') {
                close();
            }
        });

        function highlightActive() {
            results.querySelectorAll('.cmd-palette-item').forEach(function (el, i) {
                el.classList.toggle('active', i === activeIndex);
                el.style.background = i === activeIndex ? 'var(--falcon-emphasis-bg,#f0f4ff)' : '';
            });
        }

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) close();
        });

        document.addEventListener('keydown', function (e) {
            const meta = e.ctrlKey || e.metaKey;
            if (meta && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault();
                if (overlay.style.display === 'none' || !overlay.style.display) {
                    open();
                } else {
                    close();
                }
            }
        });
    });
})();
