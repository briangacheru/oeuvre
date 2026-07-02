<?php
include 'dbcon.php';

function migrateTaskFiles($con)
{
    // Get all tasks with files
    $query = "SELECT id, task_files, file_urls, file_sizes, submitted_files, submitted_file_urls, submitted_file_sizes, create_date, submitted_on FROM tbltasks WHERE (task_files IS NOT NULL AND task_files != '') OR (submitted_files IS NOT NULL AND submitted_files != '')";
    $result = mysqli_query($con, $query);

    $migrated = 0;
    $errors = 0;

    while ($row = mysqli_fetch_assoc($result)) {
        $taskId = $row['id'];

        // Migrate task files
        if (!empty($row['task_files'])) {
            $filePaths = explode(',', $row['task_files']);
            $fileUrls = !empty($row['file_urls']) ? explode(',', $row['file_urls']) : [];
            $fileSizes = !empty($row['file_sizes']) ? explode(',', $row['file_sizes']) : [];

            foreach ($filePaths as $index => $filePath) {
                if (empty(trim($filePath))) continue;

                $fileName = basename(trim($filePath));
                $fileUrl = isset($fileUrls[$index]) ? trim($fileUrls[$index]) : '';
                $fileSize = isset($fileSizes[$index]) ? (int)$fileSizes[$index] : 0;
                $uploadTime = $row['create_date'];

                $insertQuery = "INSERT INTO tbl_task_files (task_id, file_name, original_file_name, file_path, file_url, file_size, file_type, upload_time, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, 'task', ?, 'system_migration')";
                $stmt = mysqli_prepare($con, $insertQuery);
                mysqli_stmt_bind_param($stmt, 'issssss', $taskId, $fileName, $fileName, $filePath, $fileUrl, $fileSize, $uploadTime);

                if (mysqli_stmt_execute($stmt)) {
                    $migrated++;
                } else {
                    $errors++;
                    echo "Error migrating task file for task ID $taskId: " . mysqli_error($con) . "\n";
                }
                mysqli_stmt_close($stmt);
            }
        }

        // Migrate submitted files
        if (!empty($row['submitted_files'])) {
            $filePaths = explode(',', $row['submitted_files']);
            $fileUrls = !empty($row['submitted_file_urls']) ? explode(',', $row['submitted_file_urls']) : [];
            $fileSizes = !empty($row['submitted_file_sizes']) ? explode(',', $row['submitted_file_sizes']) : [];

            foreach ($filePaths as $index => $filePath) {
                if (empty(trim($filePath))) continue;

                $fileName = basename(trim($filePath));
                $fileUrl = isset($fileUrls[$index]) ? trim($fileUrls[$index]) : '';
                $fileSize = isset($fileSizes[$index]) ? (int)$fileSizes[$index] : 0;
                $uploadTime = !empty($row['submitted_on']) ? $row['submitted_on'] : $row['create_date'];

                $insertQuery = "INSERT INTO tbl_task_files (task_id, file_name, original_file_name, file_path, file_url, file_size, file_type, upload_time, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, 'submitted', ?, 'system_migration')";
                $stmt = mysqli_prepare($con, $insertQuery);
                mysqli_stmt_bind_param($stmt, 'issssss', $taskId, $fileName, $fileName, $filePath, $fileUrl, $fileSize, $uploadTime);

                if (mysqli_stmt_execute($stmt)) {
                    $migrated++;
                } else {
                    $errors++;
                    echo "Error migrating submitted file for task ID $taskId: " . mysqli_error($con) . "\n";
                }
                mysqli_stmt_close($stmt);
            }
        }
    }

    echo "Migration completed: $migrated files migrated, $errors errors\n";
}

// Run migration
migrateTaskFiles($con);
?>