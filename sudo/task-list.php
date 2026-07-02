<?php include "head.php";?>
<title>iTasker | Task List</title>
<?php include "navi.php";

// Fetch tasks from the database
$query = mysqli_query($con, "SELECT id, account, topic, due_date, status FROM tbltasks WHERE is_deleted = 0");
$tasksByDate = [];

while ($row = mysqli_fetch_assoc($query)) {
    $due_date = new DateTime($row['due_date']);
    $formatted_due_date = $due_date->format('Y-m-d');

    // Conditionally include time in title
    if ($row['status'] == 'In Progress' || $row['status'] == 'Unconfirmed') {
        $title = $row['id'] . ' ' . $row['account'] . ' ' . $due_date->format('h:i A');
    } else {
        $title = $row['id'] . ' ' . $row['account'];
    }

    $tasksByDate[$formatted_due_date][] = [
        'id' => $row['id'],
        'title' => $title,
        'start' => $due_date->format('Y-m-d\TH:i:s'),
        'topic' => $row['topic'],
        'account' => $row['account'],
        'status' => $row['status'],
    ];
}

// Sort tasks by date
ksort($tasksByDate);

$tasksJson = json_encode($tasksByDate);
?>

<div class="card shadow-none border mb-3">
    <div class="bg-holder bg-card d-none d-md-block" style="background-image:url(../assets/img/illustrations/corner-6.png);"></div>
    <div class="card-header z-1">
        <div class="row flex-between-center gx-0">
            <div class="col-lg-auto d-flex align-items-center">
                <h4 class="mb-0 text-primary fw-bold">Tasks <span class="text-info fw-medium">List</span></h4>
            </div>
        </div>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="card-body p-0 scrollbar m-3">
        <div id="taskList"></div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content position-relative border-0">
            <div class="modal-header px-5 position-relative modal-shape-header bg-shape">
                <div class="position-relative z-1">
                    <h4 class="mb-0 text-white" id="taskModalLabel">Task Details</h4>
                </div>
                <button class="btn-close position-absolute top-0 end-0 mt-2 me-2" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4 px-5" id="taskDetails">
                <!-- Task details will be inserted here -->
            </div>
            <div class="modal-footer">
                <a href="#" id="viewTaskLink" class="btn btn-outline-primary">See more details <svg class="svg-inline--fa fa-angle-right fa-w-8 fs-11 ml-1" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="angle-right" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 512" data-fa-i2svg=""><path fill="currentColor" d="M224.3 273l-136 136c-9.4 9.4-24.6 9.4-33.9 0l-22.6-22.6c-9.4-9.4-9.4-24.6 0-33.9l96.4-96.4-96.4-96.4c-9.4-9.4-9.4-24.6 0-33.9L54.3 103c9.4-9.4 24.6-9.4 33.9 0l136 136c9.5 9.4 9.5 24.6 .1 34z"></path></svg></a>
            </div>
        </div>
    </div>
</div>

<?php
include "footer.php";
?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tasksByDate = <?php echo $tasksJson; ?>;
        var taskListEl = document.getElementById('taskList');

        var badges = {
            'Completed': '<span class="badge badge rounded-pill badge-subtle-success">Completed<span class="ms-1 fas fa-check" data-fa-transform="shrink-2"></span></span>',
            'Submitted': '<span class="badge badge rounded-pill badge-subtle-info">Submitted<span class="ms-1 fas fa-file" data-fa-transform="shrink-2"></span></span>',
            'Unconfirmed': '<span class="badge badge rounded-pill badge-subtle-primary">Unconfirmed<span class="ms-1 fas fa-question" data-fa-transform="shrink-2"></span></span>',
            'Draft': '<span class="badge badge rounded-pill badge-subtle-danger">Draft<span class="ms-1 fas fa-edit" data-fa-transform="shrink-2"></span></span>',
            'Cancelled': '<span class="badge badge rounded-pill badge-subtle-danger">Cancelled<span class="ms-1 fas fa-ban" data-fa-transform="shrink-2"></span></span>',
            'In Progress': '<span class="badge badge rounded-pill badge-subtle-warning">In Progress<span class="ms-1 fas fa-stream" data-fa-transform="shrink-2"></span></span>'
        };

        for (var date in tasksByDate) {
            if (tasksByDate.hasOwnProperty(date)) {
                var dateHeader = document.createElement('div');
                dateHeader.classList.add('task-date', 'p-2', 'bg-light');
                var formattedDate = new Date(date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                dateHeader.innerHTML = `<h5>${formattedDate}</h5>`;
                taskListEl.appendChild(dateHeader);

                tasksByDate[date].forEach(function(task) {
                    var taskItem = document.createElement('div');
                    taskItem.classList.add('task-item', 'd-flex', 'align-items-center', 'justify-content-between', 'p-2', 'border-bottom');

                    var taskDetails = document.createElement('div');
                    taskDetails.classList.add('task-details', 'flex-grow-1', 'ml-2');
                    taskDetails.innerHTML = `<h6>${task.title}</h6><p>${task.topic}</p>`;

                    var taskStatus = document.createElement('div');
                    taskStatus.innerHTML = badges[task.status];

                    taskItem.appendChild(taskDetails);
                    taskItem.appendChild(taskStatus);

                    taskItem.addEventListener('click', function() {
                        var dueDate = new Date(task.start);
                        var options = { hour: '2-digit', minute: '2-digit', hour12: true };
                        var formattedDueDate = dueDate.toLocaleTimeString('en-US', options);

                        var taskDetailsHtml = `
                            <p><strong>ID:</strong> ${task.id}</p>
                            <p><strong>Account:</strong> ${task.account}</p>
                            <p><strong>Topic:</strong> ${task.topic}</p>
                            <p><strong>Status:</strong> ${badges[task.status]}</p>
                            <p><strong>Due Date:</strong> ${dueDate.toLocaleDateString()} ${formattedDueDate}</p>
                        `;
                        document.getElementById('taskDetails').innerHTML = taskDetailsHtml;

                        var viewTaskLink = document.getElementById('viewTaskLink');
                        viewTaskLink.href = 'view-task?task_id=' + btoa(task.id);

                        var taskModal = new bootstrap.Modal(document.getElementById('taskModal'));
                        taskModal.show();
                    });

                    taskListEl.appendChild(taskItem);
                });
            }
        }
    });
</script>
