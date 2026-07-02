<?php
session_start();
include 'dbcon.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1); // Log errors to file
ini_set('error_log', __DIR__ . '/php-errors.log');
date_default_timezone_set('Africa/Nairobi');

if (!isset($_GET['file']) || !isset($_GET['task_id'])) {
    die('Invalid request');
}

$encodedTaskId = $_GET['task_id'];
$taskId = base64_decode($encodedTaskId);
$fileIndex = (int)$_GET['file'];
$fileType = $_GET['type'] ?? 'task'; // 'task' or 'submitted'

// Validate file type
if (!in_array($fileType, ['task', 'submitted'])) {
    die('Invalid file type');
}

// Verify task exists and user has access
$sql = 'SELECT id FROM tbltasks WHERE id = ?';
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, 'i', $taskId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$task = mysqli_fetch_array($result);
mysqli_stmt_close($stmt);

if (!$task) {
    die('Task not found');
}

// Get file information
$fileQuery = 'SELECT * FROM tbl_task_files WHERE task_id = ? AND file_type = ? AND is_deleted = 0 ORDER BY upload_time ASC';
$stmt = mysqli_prepare($con, $fileQuery);
mysqli_stmt_bind_param($stmt, 'is', $taskId, $fileType);
mysqli_stmt_execute($stmt);
$filesResult = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($filesResult) == 0) {
    die('No files found');
}

// Get all files and find the requested one by index
$files = [];
while ($fileRow = mysqli_fetch_assoc($filesResult)) {
    $files[] = $fileRow;
}
mysqli_stmt_close($stmt);

if (!isset($files[$fileIndex])) {
    die('File not found');
}

$fileData = $files[$fileIndex];
$fileName = $fileData['original_file_name'];
$fileUrl = $fileData['file_url'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Set appropriate headers
header('X-Frame-Options: SAMEORIGIN');

$isDirect = isset($_GET['direct']) && $_GET['direct'] === '1';

if ($fileExtension === 'pdf' && $isDirect) {
    // Parse URL and encode the path properly
    $urlParts = parse_url($fileUrl);
    $pathParts = explode('/', $urlParts['path']);

    // Encode each path segment
    $encodedPathParts = array_map('rawurlencode', $pathParts);
    $encodedPath = implode('/', $encodedPathParts);

    // Reconstruct the URL
    $encodedFileUrl = $urlParts['scheme'] . '://' . $urlParts['host'] . $encodedPath;
    if (isset($urlParts['query'])) {
        $encodedFileUrl .= '?' . $urlParts['query'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $encodedFileUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HEADER => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: application/pdf,*/*',
            'Cache-Control: no-cache'
        ]
    ]);

    $pdfContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($ch);

    // Debug information (remove in production)
    //error_log("PDF Debug - Original URL: $fileUrl");
    //error_log("PDF Debug - Encoded URL: $encodedFileUrl");
    //error_log("PDF Debug - HTTP Code: $httpCode, Content-Type: $contentType, Content Length: $contentLength, cURL Error: $error");
    // More flexible validation
    $isValidPdf = false;

    if ($httpCode === 200 && $pdfContent !== false && !empty($pdfContent)) {
        // Check if content starts with PDF signature
        if (substr($pdfContent, 0, 4) === '%PDF') {
            $isValidPdf = true;
        } // Fallback: check content type or file extension
        elseif (stripos($contentType, 'pdf') !== false ||
            stripos($contentType, 'application/octet-stream') !== false) {
            $isValidPdf = true;
        } else {
            error_log("PDF validation: FAILED - No PDF signature and content type doesn't match");
        }
    } else {
        error_log("PDF validation: FAILED - HTTP code: $httpCode, Content empty: " . (empty($pdfContent) ? 'YES' : 'NO'));
    }

    if ($isValidPdf) {
        if (ob_get_level()) {
            ob_end_clean();
        }
        // Set appropriate headers for PDF display
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $fileName . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        // Try to bypass IDM
        header('X-Accel-Buffering: no');
        header('X-Robots-Tag: noindex, nofollow');

        // Output the PDF content
        echo $pdfContent;
        exit;
    } else {
        // Enhanced error message with debugging info
        $errorMsg = 'Unable to load PDF file. ';
        if ($httpCode !== 200) {
            $errorMsg .= "HTTP Error: $httpCode. ";
        }
        if (!empty($error)) {
            $errorMsg .= "cURL Error: $error. ";
        }
        if (empty($pdfContent)) {
            $errorMsg .= 'No content received. ';
        }
        $errorMsg .= "Content-Type: $contentType";

        die($errorMsg);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>View itasker file - <?php echo htmlspecialchars($fileName); ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/img/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/img/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/img/favicons/favicon-16x16.png">
    <link rel="shortcut icon" type="image/x-icon" href="../assets/img/favicons/favicon.ico">
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
        }

        .header {
            background: #fff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h3 {
            margin: 0;
            color: #333;
        }

        .header-buttons {
            display: flex;
            gap: 10px;
        }

        .viewer-container {
            width: 100%;
            height: calc(100vh - 120px);
            border: 1px solid #ddd;
            background: #fff;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .download-btn, .close-btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .close-btn {
            background: #6c757d;
        }

        .download-btn:hover {
            background: #0056b3;
            color: white;
            text-decoration: none;
        }

        .close-btn:hover {
            background: #545b62;
        }

        .error {
            color: #dc3545;
            padding: 40px;
            text-align: center;
            background: #fff;
            border-radius: 5px;
        }

        .document-viewer {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .viewer-tabs {
            background: #f8f9fa;
            padding: 10px 15px;
            border-bottom: 1px solid #ddd;
            display: flex;
            gap: 5px;
        }

        .tab-btn {
            background: #fff;
            border: 1px solid #ddd;
            padding: 8px 16px;
            cursor: pointer;
            border-radius: 4px 4px 0 0;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .tab-btn:hover {
            background: #e9ecef;
        }

        .tab-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .tab-btn.disabled {
            background: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .viewer-content {
            flex: 1;
            position: relative;
        }

        .viewer-iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .loading-message {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            z-index: 10;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007bff;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        .media-container {
            padding: 20px;
            text-align: center;
            height: calc(100% - 40px);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .text-viewer {
            padding: 20px;
            background: #fff;
            height: calc(100% - 40px);
            overflow: auto;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.5;
        }

        .viewer-info {
            background: #e3f2fd;
            padding: 10px 15px;
            border-bottom: 1px solid #ddd;
            font-size: 12px;
            color: #1976d2;
        }

        .fallback-options {
            background: #fff3cd;
            padding: 15px;
            margin: 20px;
            border-radius: 5px;
            border: 1px solid #ffeaa7;
        }
    </style>
</head>
<body>
<div class='header'>
    <div>
        <h3><?php echo htmlspecialchars($fileName); ?></h3>
        <div style="font-size: 12px; color: #666; margin-top: 5px;">
            <?php
            if (isset($fileData['file_size']) && $fileData['file_size'] > 0) {
                $fileSize = $fileData['file_size'];
                $units = ['B', 'KB', 'MB', 'GB'];
                $power = $fileSize > 0 ? floor(log($fileSize, 1024)) : 0;
                $formattedSize = round($fileSize / pow(1024, $power), 2) . ' ' . $units[$power];
                echo 'Size: ' . $formattedSize . ' | ';
            }
            if (isset($fileData['upload_time'])) {
                echo 'Uploaded: ' . date('M d, Y g:i A', strtotime($fileData['upload_time']));
            }
            ?>
        </div>
    </div>
    <div class="header-buttons">
        <a href="<?php echo htmlspecialchars($fileUrl); ?>" download="<?php echo htmlspecialchars($fileName); ?>"
           class="download-btn">
            📥 Download File
        </a>
    </div>
</div>

<div class="viewer-container">
    <?php
    switch ($fileExtension) {
        case 'doc':
        case 'docx':
        case 'xls':
        case 'xlsx':
        case 'ppt':
        case 'pptx':
            echo '<div class="document-viewer">';

            // Info bar
            echo '<div class="viewer-info">';
            echo '📄 Office Document Viewer - If the document doesn\'t load properly, try switching viewers or download the file.';
            echo '</div>';

            echo '<div class="viewer-tabs">';
            echo '<button onclick="switchViewer(\'microsoft\')" class="tab-btn active" id="microsoft-tab">🏢 Microsoft Viewer (Recommended)</button>';
            echo '<button onclick="switchViewer(\'google\')" class="tab-btn" id="google-tab">📄 Google Viewer</button>';
            echo '<button onclick="switchViewer(\'direct\')" class="tab-btn" id="direct-tab">🔗 Direct Link</button>';
            echo '</div>';

            echo '<div class="viewer-content">';

            // Loading message
            echo '<div class="loading-message" id="loading-message">';
            echo '<div class="loading-spinner"></div>';
            echo '<div>Loading document... Please wait.</div>';
            echo '<div style="font-size: 12px; margin-top: 10px; color: #666;">This may take a few moments for large files</div>';
            echo '</div>';

            // Microsoft Office Viewer (Primary)
            $microsoftUrl = 'https://view.officeapps.live.com/op/embed.aspx?src=' . urlencode($fileUrl);
            echo '<iframe id="microsoft-viewer" class="viewer-iframe" src="' . htmlspecialchars($microsoftUrl) . '" onload="hideLoading()" onerror="handleViewerError(\'microsoft\')"></iframe>';

            // Google Docs Viewer (Fallback)
            $googleUrl = 'https://docs.google.com/gviewer?url=' . urlencode($fileUrl) . '&embedded=true';
            echo '<iframe id="google-viewer" class="viewer-iframe" style="display: none;" onload="hideLoading()" onerror="handleViewerError(\'google\')"></iframe>';

            // Direct link option
            echo '<div id="direct-viewer" style="display: none;" class="fallback-options">';
            echo '<h4>📎 Direct File Access</h4>';
            echo '<p>If the viewers above don\'t work, you can:</p>';
            echo '<div style="margin: 15px 0;">';
            echo '<a href="' . htmlspecialchars($fileUrl) . '"  class="download-btn" style="margin-right: 10px;">🔗 Open in New Tab</a>';
            echo '<a href="' . htmlspecialchars($fileUrl) . '" download="' . htmlspecialchars($fileName) . '" class="download-btn">📥 Download File</a>';
            echo '</div>';
            echo '<p style="font-size: 12px; color: #666; margin-top: 15px;">';
            echo '<strong>Note:</strong> Some browsers may download the file instead of displaying it. ';
            echo 'For best results with Office documents, we recommend using Microsoft Edge or Chrome.';
            echo '</p>';
            echo '</div>';

            echo '</div>';
            echo '</div>';
            break;

        case 'pdf':
            echo '<div class="document-viewer">';
            echo '<div class="viewer-info">';
            echo '📄 PDF Viewer - Using your browser\'s native PDF viewer with built-in navigation and zoom controls';
            echo '</div>';

            echo '<div class="viewer-content">';

            // Loading message
            echo '<div class="loading-message" id="loading-message">';
            echo '<div class="loading-spinner"></div>';
            echo '<div>Loading PDF... Please wait.</div>';
            echo '</div>';

            // Use the direct=1 parameter to serve PDF content with proper headers
            $pdfViewerUrl = 'file-viewer?' . http_build_query([
                    'file' => $fileIndex,
                    'task_id' => $encodedTaskId,
                    'type' => $fileType,
                    'direct' => '1'
                ]);

            echo '<iframe id="pdf-viewer" class="viewer-iframe" src="' . htmlspecialchars($pdfViewerUrl) . '" onload="hideLoading()" onerror="handlePdfError()"></iframe>';

            echo '</div>';
            echo '</div>';
            break;

        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
        case 'webp':
        case 'bmp':
        case 'svg':
            echo '<div class="media-container">';
            echo '<img src="' . htmlspecialchars($fileUrl) . '" style="max-width: 100%; max-height: 100%; object-fit: contain;" alt="' . htmlspecialchars($fileName) . '" onload="hideLoading()">';
            echo '</div>';
            break;

        case 'mp4':
        case 'webm':
        case 'ogg':
        case 'avi':
        case 'mov':
            echo '<div class="media-container">';
            echo '<video controls style="max-width: 100%; max-height: 100%;" onloadeddata="hideLoading()">';
            echo '<source src="' . htmlspecialchars($fileUrl) . '" type="video/' . ($fileExtension === 'mov' ? 'quicktime' : $fileExtension) . '">';
            echo 'Your browser does not support the video tag.';
            echo '</video>';
            echo '</div>';
            break;

        case 'mp3':
        case 'wav':
        case 'ogg':
        case 'm4a':
            echo '<div class="media-container">';
            echo '<div style="text-align: center;">';
            echo '<h3>🎵 Audio File</h3>';
            echo '<audio controls style="width: 100%; max-width: 500px;" onloadeddata="hideLoading()">';
            echo '<source src="' . htmlspecialchars($fileUrl) . '" type="audio/' . $fileExtension . '">';
            echo 'Your browser does not support the audio tag.';
            echo '</audio>';
            echo '</div>';
            echo '</div>';
            break;

        case 'txt':
        case 'log':
        case 'md':
        case 'csv':
            echo '<div class="loading-message" id="loading-message">';
            echo '<div class="loading-spinner"></div>';
            echo '<div>Loading text file... Please wait.</div>';
            echo '</div>';
            $content = @file_get_contents($fileUrl);
            if ($content !== false) {
                echo '<div class="text-viewer">' . htmlspecialchars($content) . '</div>';
                echo '<script>hideLoading();</script>';
            } else {
                echo '<div class="error">';
                echo '<h3>❌ Unable to load text file</h3>';
                echo '<p>The text file could not be loaded for preview.</p>';
                echo '<p><a href="' . htmlspecialchars($fileUrl) . '" download="' . htmlspecialchars($fileName) . '" class="download-btn">Download to view</a></p>';
                echo '</div>';
            }
            break;

        default:
            echo '<div class="error">';
            echo '<h3>👁️ Preview not available</h3>';
            echo '<p>This file type (<strong>' . htmlspecialchars($fileExtension) . '</strong>) cannot be previewed in the browser.</p>';
            echo '<p>Supported formats for preview:</p>';
            echo '<ul style="text-align: left; display: inline-block;">';
            echo '<li><strong>Documents:</strong> PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX</li>';
            echo '<li><strong>Images:</strong> JPG, PNG, GIF, WebP, SVG</li>';
            echo '<li><strong>Videos:</strong> MP4, WebM, OGG, AVI, MOV</li>';
            echo '<li><strong>Audio:</strong> MP3, WAV, OGG, M4A</li>';
            echo '<li><strong>Text:</strong> TXT, LOG, MD, CSV</li>';
            echo '</ul>';
            echo '<p><a href="' . htmlspecialchars($fileUrl) . '" download="' . htmlspecialchars($fileName) . '" class="download-btn">📥 Download to view</a></p>';
            echo '</div>';
            break;
    }
    ?>
</div>

<script>
    let currentViewer = 'microsoft';
    let viewerErrors = {};

    function switchViewer(type) {
        const microsoftViewer = document.getElementById("microsoft-viewer");
        const googleViewer = document.getElementById("google-viewer");
        const directViewer = document.getElementById("direct-viewer");
        const microsoftTab = document.getElementById("microsoft-tab");
        const googleTab = document.getElementById("google-tab");
        const directTab = document.getElementById("direct-tab");
        const loadingMessage = document.getElementById("loading-message");

        // Hide all viewers
        if (microsoftViewer) microsoftViewer.style.display = "none";
        if (googleViewer) googleViewer.style.display = "none";
        if (directViewer) directViewer.style.display = "none";

        // Remove active class from all tabs
        [microsoftTab, googleTab, directTab].forEach(tab => {
            if (tab) tab.classList.remove("active");
        });

        currentViewer = type;

        if (type === "microsoft") {
            if (microsoftViewer) {
                microsoftViewer.style.display = "block";
                if (!microsoftViewer.src) {
                    const microsoftUrl = 'https://view.officeapps.live.com/op/embed.aspx?src=' + encodeURIComponent('<?php echo $fileUrl; ?>');
                    microsoftViewer.src = microsoftUrl;
                    showLoading();
                }
            }
            if (microsoftTab) microsoftTab.classList.add("active");
        } else if (type === "google") {
            if (googleViewer) {
                googleViewer.style.display = "block";
                if (!googleViewer.src) {
                    const googleUrl = 'https://docs.google.com/gviewer?url=' + encodeURIComponent('<?php echo $fileUrl; ?>') + '&embedded=true';
                    googleViewer.src = googleUrl;
                    showLoading();
                }
            }
            if (googleTab) googleTab.classList.add("active");
        } else if (type === "direct") {
            if (directViewer) directViewer.style.display = "block";
            if (directTab) directTab.classList.add("active");
            hideLoading();
        }
    }

    function showLoading() {
        const loadingMessage = document.getElementById("loading-message");
        if (loadingMessage) {
            loadingMessage.style.display = 'block';
        }
    }

    function hideLoading() {
        const loadingMessage = document.getElementById("loading-message");
        if (loadingMessage) {
            loadingMessage.style.display = 'none';
        }
    }

    function handleViewerError(viewerType) {
        //console.log(`${viewerType} viewer failed to load`);
        viewerErrors[viewerType] = true;

        // Mark the failed viewer tab as disabled
        const tab = document.getElementById(viewerType + '-tab');
        if (tab) {
            tab.classList.add('disabled');
            tab.innerHTML += ' ❌';
        }

        hideLoading();

        // If current viewer failed, try to switch to a working one
        if (currentViewer === viewerType) {
            if (viewerType === 'microsoft' && !viewerErrors['google']) {
                setTimeout(() => switchViewer('google'), 1000);
            } else if (viewerType === 'google' && !viewerErrors['microsoft']) {
                setTimeout(() => switchViewer('microsoft'), 1000);
            } else {
                setTimeout(() => switchViewer('direct'), 1000);
            }
        }
    }

    function handlePdfError() {
        //console.log('PDF viewer failed to load');
        hideLoading();

        // Show error message with fallback options
        const viewerContent = document.querySelector('.viewer-content');
        if (viewerContent) {
            viewerContent.innerHTML =
                '<div class="fallback-options">' +
                '<h4>❌ PDF could not be displayed</h4>' +
                '<p>The PDF file could not be loaded in the browser viewer. This might be due to:</p>' +
                '<ul style="text-align: left; margin: 15px 0;">' +
                '<li>Browser compatibility issues</li>' +
                '<li>PDF file format or corruption</li>' +
                '<li>Network connectivity problems</li>' +
                '</ul>' +
                '<div style="margin: 20px 0;">' +
                '<a href="<?php echo htmlspecialchars($fileUrl); ?>" target="_blank" class="download-btn" style="margin-right: 10px;">🔗 Open in New Tab</a>' +
                '<a href="<?php echo htmlspecialchars($fileUrl); ?>" download="<?php echo htmlspecialchars($fileName); ?>" class="download-btn">📥 Download PDF</a>' +
                '</div>' +
                '<p style="font-size: 12px; color: #666;">' +
                '<strong>Tip:</strong> Try opening the PDF in a new tab or downloading it to view with your default PDF reader.' +
                '</p>' +
                '</div>';
        }
    }

    // Auto-hide loading message after 15 seconds as fallback
    setTimeout(() => {
        hideLoading();
    }, 15000);

    // Keyboard shortcuts
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            window.close();
        }
        if (e.ctrlKey && e.key === 'd') {
            e.preventDefault();
            const downloadBtn = document.querySelector('.download-btn');
            if (downloadBtn) {
                downloadBtn.click();
            }
        }
    });

    // Initialize with proper loading state
    document.addEventListener('DOMContentLoaded', function () {
        // Add some delay to ensure proper loading
        setTimeout(() => {
            const loadingMessage = document.getElementById('loading-message');
            if (loadingMessage && loadingMessage.style.display !== 'none') {
                showLoading();
            }
        }, 500);
    });
</script>
</body>
</html>