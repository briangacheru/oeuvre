<?php
/**
 * Upload Directory Diagnostic Tool
 * Upload this file to your admin directory and access via browser
 * It will help diagnose and fix upload directory issues
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Directory Diagnostic</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        h2 {
            color: #667eea;
            margin-top: 0;
        }
        .success {
            color: #28a745;
            font-weight: bold;
        }
        .error {
            color: #dc3545;
            font-weight: bold;
        }
        .warning {
            color: #ffc107;
            font-weight: bold;
        }
        .info {
            background: #e7f3ff;
            padding: 15px;
            border-left: 4px solid #2196F3;
            margin: 15px 0;
        }
        .command {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            overflow-x: auto;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        table td:first-child {
            font-weight: bold;
            width: 200px;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .btn:hover {
            background: #5568d3;
        }
        .fix-section {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #ffc107;
            margin: 15px 0;
        }
    </style>
</head>
<body>
<h1>📁 Upload Directory Diagnostic Tool</h1>

<?php
// Configuration
$uploadDir = '../taskfiles/';
$canFix = false;

// Run diagnostics
echo '<div class="card">';
echo '<h2>🔍 Diagnostic Results</h2>';
echo '<table>';

// Test 1: Check if directory exists
echo '<tr><td>Directory Exists:</td><td>';
if (is_dir($uploadDir)) {
    echo '<span class="success">✓ YES</span>';
    echo '<br><small>' . realpath($uploadDir) . '</small>';
    $dirExists = true;
} else {
    echo '<span class="error">✗ NO</span>';
    echo '<br><small>Expected: ' . realpath('../') . '/taskfiles</small>';
    $dirExists = false;
    $canFix = true;
}
echo '</td></tr>';

// Test 2: Check permissions
echo '<tr><td>Directory Permissions:</td><td>';
if ($dirExists) {
    $perms = substr(sprintf('%o', fileperms($uploadDir)), -4);
    if ($perms == '0755' || $perms == '0775' || $perms == '0777') {
        echo '<span class="success">✓ ' . $perms . '</span>';
    } else {
        echo '<span class="warning">⚠ ' . $perms . ' (Should be 0755)</span>';
        $canFix = true;
    }
} else {
    echo '<span class="error">✗ N/A (Directory doesn\'t exist)</span>';
}
echo '</td></tr>';

// Test 3: Check if writable
echo '<tr><td>Is Writable:</td><td>';
if ($dirExists && is_writable($uploadDir)) {
    echo '<span class="success">✓ YES</span>';
    $isWritable = true;
} else {
    echo '<span class="error">✗ NO</span>';
    $isWritable = false;
    $canFix = true;
}
echo '</td></tr>';

// Test 4: Try to write a file
echo '<tr><td>Write Test:</td><td>';
if ($dirExists) {
    $testFile = $uploadDir . 'test_' . time() . '.txt';
    if (@file_put_contents($testFile, 'test content')) {
        echo '<span class="success">✓ PASSED</span>';
        @unlink($testFile);
    } else {
        echo '<span class="error">✗ FAILED</span>';
        $error = error_get_last();
        if ($error) {
            echo '<br><small>' . htmlspecialchars($error['message']) . '</small>';
        }
    }
} else {
    echo '<span class="error">✗ Cannot test (directory doesn\'t exist)</span>';
}
echo '</td></tr>';

// Directory ownership
if ($dirExists) {
    echo '<tr><td>Directory Owner:</td><td>';
    $stat = stat($uploadDir);
    if (function_exists('posix_getpwuid')) {
        $owner = posix_getpwuid($stat['uid']);
        echo $owner['name'] . ' (UID: ' . $owner['uid'] . ')';
    } else {
        echo 'UID: ' . $stat['uid'];
    }
    echo '</td></tr>';
}

// PHP process user
echo '<tr><td>PHP Running As:</td><td>';
if (function_exists('posix_geteuid')) {
    $phpUser = posix_getpwuid(posix_geteuid());
    echo $phpUser['name'] . ' (UID: ' . $phpUser['uid'] . ')';
} else {
    echo 'Unknown (POSIX functions not available)';
}
echo '</td></tr>';

// PHP version
echo '<tr><td>PHP Version:</td><td>' . PHP_VERSION . '</td></tr>';

// Web server
echo '<tr><td>Web Server:</td><td>' . $_SERVER['SERVER_SOFTWARE'] . '</td></tr>';

// Document root
echo '<tr><td>Document Root:</td><td>' . $_SERVER['DOCUMENT_ROOT'] . '</td></tr>';

// Script path
echo '<tr><td>Script Path:</td><td>' . __FILE__ . '</td></tr>';

echo '</table>';
echo '</div>';

// Show status and recommendations
if ($isWritable && $dirExists) {
    echo '<div class="card" style="background: #d4edda; border-left: 4px solid #28a745;">';
    echo '<h2 style="color: #28a745;">✓ All Good!</h2>';
    echo '<p>The upload directory is properly configured and writable. File attachments should work correctly.</p>';
    echo '</div>';
} else {
    echo '<div class="card" style="background: #f8d7da; border-left: 4px solid #dc3545;">';
    echo '<h2 style="color: #dc3545;">✗ Issues Found</h2>';
    echo '<p>The upload directory has issues that need to be fixed before file attachments will work.</p>';
    echo '</div>';

    // Automatic fix section
    if (isset($_POST['auto_fix'])) {
        echo '<div class="card">';
        echo '<h2>🔧 Attempting Automatic Fix...</h2>';

        $fixLog = [];
        $fixSuccess = true;

        // Try to create directory
        if (!$dirExists) {
            if (@mkdir($uploadDir, 0755, true)) {
                $fixLog[] = '<span class="success">✓ Created directory</span>';
            } else {
                $fixLog[] = '<span class="error">✗ Failed to create directory</span>';
                $fixSuccess = false;
            }
        }

        // Try to set permissions
        if (is_dir($uploadDir)) {
            if (@chmod($uploadDir, 0755)) {
                $fixLog[] = '<span class="success">✓ Set permissions to 755</span>';
            } else {
                $fixLog[] = '<span class="warning">⚠ Could not change permissions</span>';
            }
        }

        // Show log
        echo '<ul>';
        foreach ($fixLog as $log) {
            echo '<li>' . $log . '</li>';
        }
        echo '</ul>';

        if ($fixSuccess && is_writable($uploadDir)) {
            echo '<p class="success">✓ Automatic fix successful! Refresh this page to verify.</p>';
            echo '<a href="" class="btn">Refresh Page</a>';
        } else {
            echo '<p class="error">✗ Automatic fix could not complete. Manual intervention required.</p>';
        }

        echo '</div>';
    }

    // Show fix options
    echo '<div class="card fix-section">';
    echo '<h2>🛠️ How to Fix</h2>';

    echo '<h3>Option 1: Automatic Fix (Try First)</h3>';
    echo '<form method="post">';
    echo '<button type="submit" name="auto_fix" class="btn">🔧 Try Automatic Fix</button>';
    echo '</form>';
    echo '<p><small>This will attempt to create the directory and set proper permissions.</small></p>';

    echo '<h3>Option 2: Manual Fix via Command Line</h3>';
    echo '<p>If you have SSH access, run these commands:</p>';

    echo '<div class="command">';
    echo '# Navigate to your project directory<br>';
    echo 'cd ' . dirname(__FILE__) . '<br><br>';
    echo '# Create the directory<br>';
    echo 'mkdir -p ' . realpath('../') . '/taskfiles<br><br>';
    echo '# Set permissions<br>';
    echo 'chmod 755 ' . realpath('../') . '/taskfiles<br><br>';
    echo '# Set ownership (choose one based on your server)<br>';
    echo 'sudo chown -R www-data:www-data ' . realpath('../') . '/taskfiles  # Ubuntu/Debian<br>';
    echo 'sudo chown -R apache:apache ' . realpath('../') . '/taskfiles     # CentOS/RHEL<br>';
    echo 'sudo chown -R nginx:nginx ' . realpath('../') . '/taskfiles       # Nginx<br>';
    echo '</div>';

    echo '<h3>Option 3: Fix via FTP/cPanel</h3>';
    echo '<ol>';
    echo '<li>Connect to your server via FTP or open cPanel File Manager</li>';
    echo '<li>Navigate to: <code>' . dirname(realpath(__FILE__)) . '</code></li>';
    echo '<li>Create a folder named <code>taskfiles</code></li>';
    echo '<li>Right-click the folder → Change Permissions → Set to <code>755</code></li>';
    echo '</ol>';

    echo '<h3>Option 4: Change Upload Location</h3>';
    echo '<p>Edit <code>add-task-comment.php</code> and change the upload directory to a writable location:</p>';
    echo '<div class="command">';
    echo '// Find this line (around line 230):<br>';
    echo '$uploadDir = \'../taskfiles/\';<br><br>';
    echo '// Change to:<br>';
    echo '$uploadDir = \'./uploads/\';  // or another writable path<br>';
    echo '</div>';

    echo '</div>';
}
?>

<div class="info">
    <strong>💡 Need Help?</strong><br>
    Refer to the <code>UPLOAD_DIRECTORY_FIX.md</code> file for detailed troubleshooting steps.
</div>

<div class="card">
    <p style="text-align: center; color: #666;">
        <small>After fixing, refresh this page to verify the fix worked.</small>
    </p>
</div>
</body>
</html>