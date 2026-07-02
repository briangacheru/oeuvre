
/* -------------------------------------------------------------------------- */
/*                              Other user js functions                       */
/* -------------------------------------------------------------------------- */

    function confirmExport() {
        var confirmExport = confirm("Do you want to download the exported CSV file?");
        if (confirmExport) {
            window.location.href = 'export_all_tasks';
        }
    }
        function exportWriter() {
            var exportWriter = confirm("Do you want to download the exported CSV file?");
            if (exportWriter) {
                window.location.href = 'export_writers';
            }
}
    function exportPaid() {
    var exportPaid = confirm("Do you want to download the exported CSV file?");
    if (exportPaid) {
    window.location.href = 'export_paid_tasks';
}
}
    function exportUnpaid() {
    var exportUnpaid = confirm("Do you want to download the exported CSV file?");
    if (exportUnpaid) {
    window.location.href = 'export_unpaid_tasks';
}
}
function exportOverdraft() {
    var exportOverdraft = confirm("Do you want to download the exported CSV file?");
    if (exportOverdraft) {
        window.location.href = 'export_overdraft';
    }
}
function exportOverdraftAs() {
    var exportOverdraft = confirm("Do you want to download the exported CSV file?");
    if (exportOverdraft) {
        window.location.href = 'export-overdraft';
    }
}
    function selectAllTasks(source) {
    const checkboxes = document.querySelectorAll('input[type="checkbox"][data-bulk-select-row="data-bulk-select-row"]');
    for (let checkbox of checkboxes) {
    checkbox.checked = source.checked;
}
}
    function submitForm(action) {
    var form = document.getElementById('tasksForm');
    form.action = action;
    form.submit();
}



