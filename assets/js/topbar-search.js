// Top-nav search, shared by root and sudo. Each interface has its own
// search.php at the same relative level as this script's page, so a plain
// relative fetch('search?q=...') resolves correctly in both.
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const box = document.querySelector('.search-box');
        const input = box ? box.querySelector('.search-input') : null;
        if (!box || !input) return;

        const panel = document.createElement('div');
        panel.className = 'topbar-search-results';
        panel.style.cssText = 'display:none;position:absolute;top:100%;left:0;right:0;margin-top:6px;'
            + 'max-height:70vh;overflow-y:auto;background:var(--falcon-card-bg,#fff);border:1px solid var(--falcon-border-color,#e3e6ed);'
            + 'border-radius:.5rem;box-shadow:0 .5rem 1rem rgba(0,0,0,.15);z-index:1050;min-width:340px;';
        box.style.position = 'relative';
        box.appendChild(panel);

        let debounceTimer = null;
        let currentRequestId = 0;

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str == null ? '' : String(str);
            return div.innerHTML;
        }

        function renderLoading() {
            panel.innerHTML = '<div class="p-3 text-center text-600"><span class="fas fa-spinner fa-spin me-2"></span>Searching...</div>';
            panel.style.display = 'block';
        }

        function renderEmpty() {
            panel.innerHTML = '<div class="p-3 text-center text-600">No results found.</div>';
            panel.style.display = 'block';
        }

        function renderError() {
            panel.innerHTML = '<div class="p-3 text-center text-danger">Search failed. Try again.</div>';
            panel.style.display = 'block';
        }

        function renderGroups(groups) {
            if (!groups || !groups.length) {
                renderEmpty();
                return;
            }

            let html = '';
            groups.forEach(function (group) {
                html += '<div class="px-3 pt-3 pb-1 fs-11 fw-bold text-uppercase text-600">'
                    + '<i class="fas ' + escapeHtml(group.icon || 'fa-circle') + ' me-1"></i>' + escapeHtml(group.label)
                    + '</div>';
                group.items.forEach(function (item) {
                    html += '<div class="px-3 py-2 border-top">'
                        + '<div class="fw-semi-bold text-900" style="word-break:break-word;">' + escapeHtml(item.title) + '</div>';
                    if (item.subtitle) {
                        html += '<div class="fs-10 text-600" style="word-break:break-word;">' + item.subtitle + '</div>';
                    }
                    if (item.actions && item.actions.length) {
                        html += '<div class="mt-1 d-flex flex-wrap gap-2">';
                        item.actions.forEach(function (action) {
                            const target = action.external ? ' target="_blank" rel="noopener"' : '';
                            html += '<a class="fs-10 fw-semi-bold" href="' + escapeHtml(action.url) + '"' + target + '>'
                                + escapeHtml(action.label) + '<span class="fas fa-chevron-right ms-1" style="font-size:8px;"></span></a>';
                        });
                        html += '</div>';
                    }
                    html += '</div>';
                });
            });
            panel.innerHTML = html;
            panel.style.display = 'block';
        }

        function runSearch(query) {
            const requestId = ++currentRequestId;
            renderLoading();

            fetch('search?q=' + encodeURIComponent(query))
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (requestId !== currentRequestId) return; // a newer keystroke already superseded this request
                    if (!data.success) {
                        renderError();
                        return;
                    }
                    renderGroups(data.groups);
                })
                .catch(function () {
                    if (requestId === currentRequestId) renderError();
                });
        }

        input.addEventListener('input', function () {
            const query = input.value.trim();
            clearTimeout(debounceTimer);

            if (query.length < 2) {
                panel.style.display = 'none';
                panel.innerHTML = '';
                return;
            }

            debounceTimer = setTimeout(function () { runSearch(query); }, 300);
        });

        input.addEventListener('focus', function () {
            if (input.value.trim().length >= 2 && panel.innerHTML) {
                panel.style.display = 'block';
            }
        });

        document.addEventListener('click', function (e) {
            if (!box.contains(e.target)) {
                panel.style.display = 'none';
            }
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                panel.style.display = 'none';
                input.blur();
            }
        });

        // The theme's own close (x) button clears input.value programmatically,
        // which doesn't fire our 'input' listener above - hide the panel here too.
        const dismissBtn = box.querySelector('[data-bs-dismiss="search"]');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', function () {
                clearTimeout(debounceTimer);
                panel.style.display = 'none';
                panel.innerHTML = '';
            });
        }
    });
})();
