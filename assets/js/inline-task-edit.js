// Shared inline-editing behavior for admin task list tables. An element
// opts in with data-inline-edit="due_date"|"cpp"|"writer" and
// data-task-id="<id>"; due_date/cpp POST to sudo/inline-update-task.php,
// writer POSTs to the existing sudo/update-task-writer.php (not
// duplicated here). Writer options come from a #writerOptionsData JSON
// script tag the host page renders once, so no extra request per edit.
// Depends on assets/js/toast.js's showToast() and a page-level CSRF token
// input (name="csrf_token").
(function () {
    function csrfToken() {
        const el = document.querySelector('[name="csrf_token"]');
        return el ? el.value : '';
    }

    function writerOptions() {
        const el = document.getElementById('writerOptionsData');
        if (!el) return [];
        try { return JSON.parse(el.textContent); } catch (e) { return []; }
    }

    function startEdit(cell) {
        if (cell.classList.contains('inline-editing')) return;
        cell.classList.add('inline-editing');

        const field = cell.dataset.inlineEdit;
        const taskId = cell.dataset.taskId;
        const originalHtml = cell.innerHTML;

        const wrapper = document.createElement('div');
        wrapper.className = 'd-flex align-items-center gap-1';

        let input;
        if (field === 'writer') {
            input = document.createElement('select');
            input.className = 'form-select form-select-sm';
            const currentEmail = cell.dataset.currentEmail || '';
            writerOptions().forEach(function (w) {
                const opt = document.createElement('option');
                opt.value = w.username + '|' + w.email;
                opt.textContent = w.username;
                if (w.email === currentEmail) opt.selected = true;
                input.appendChild(opt);
            });
        } else if (field === 'due_date') {
            input = document.createElement('input');
            input.type = 'datetime-local';
            input.className = 'form-control form-control-sm';
            input.value = cell.dataset.currentIso || '';
        } else if (field === 'cpp') {
            input = document.createElement('input');
            input.type = 'number';
            input.step = '0.01';
            input.min = '0.01';
            input.className = 'form-control form-control-sm';
            input.style.width = '90px';
            input.value = cell.dataset.currentValue || '';
        } else {
            return;
        }

        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.className = 'btn btn-sm btn-success py-0 px-2';
        saveBtn.innerHTML = '<i class="fas fa-check"></i>';

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn btn-sm btn-outline-secondary py-0 px-2';
        cancelBtn.innerHTML = '<i class="fas fa-times"></i>';

        function cancel() {
            cell.innerHTML = originalHtml;
            cell.classList.remove('inline-editing');
        }

        function save() {
            saveBtn.disabled = true;
            const formData = new FormData();
            formData.append('task_id', taskId);
            formData.append('csrf_token', csrfToken());

            let url = 'inline-update-task';
            if (field === 'writer') {
                url = 'update-task-writer';
                const [writerName, writerEmail] = input.value.split('|');
                formData.append('writer', writerName);
                formData.append('email', writerEmail);
            } else {
                formData.append('field', field);
                formData.append('value', input.value);
            }

            fetch(url, { method: 'POST', body: formData })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        if (typeof showToast === 'function') showToast(data.message || 'Saved.', 'success');
                        setTimeout(function () { location.reload(); }, 700);
                    } else {
                        if (typeof showToast === 'function') showToast(data.message || 'Could not save.', 'error');
                        saveBtn.disabled = false;
                    }
                })
                .catch(function () {
                    if (typeof showToast === 'function') showToast('Something went wrong.', 'error');
                    saveBtn.disabled = false;
                });
        }

        saveBtn.addEventListener('click', save);
        cancelBtn.addEventListener('click', cancel);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); save(); }
            if (e.key === 'Escape') cancel();
        });
        input.addEventListener('click', function (e) { e.stopPropagation(); });

        wrapper.appendChild(input);
        wrapper.appendChild(saveBtn);
        wrapper.appendChild(cancelBtn);
        cell.innerHTML = '';
        cell.appendChild(wrapper);
        input.focus();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-inline-edit]').forEach(function (cell) {
            cell.style.cursor = 'pointer';
            cell.title = 'Click to edit';
            cell.addEventListener('click', function (e) {
                e.stopPropagation();
                e.preventDefault();
                startEdit(cell);
            });
        });
    });
})();
