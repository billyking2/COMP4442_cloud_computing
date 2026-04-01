<?php
// Handle file upload 
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Set JSON header 
    header('Content-Type: application/json');

    error_log(print_r($_FILES, true));
    error_log(print_r($_POST, true));

    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded - file field not found']);
        exit;
    }

    $file = $_FILES['file'];

    // Check upload error
    $file_error_message = $file['error'];
    if ($file_error_message !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];

        $error_msg = isset($errors[$file_error_message]) ? $errors[$file_error_message] : 'Unknown error';
        echo json_encode(['success' => false, 'message' => 'Upload failed: ' . $error_msg]);
        exit;
    }

    // check file type
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_types = ['csv', 'txt', ''];   // '' means no extension

    if (!in_array($fileType, $allowed_types)) {
        echo json_encode([
            'success' => false,
            'message' => 'Only .csv, .txt files or files without extension are allowed (got .' . $fileType . ')'
        ]);
        exit;
    }

    // Check file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File too large (max 10MB)']);
        exit;
    }

    // check filename, only allow letters, numbers, underscores, dashes and dots
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $file['name'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Filename can only contain letters, number, underscores, dashes and dots'
        ]);
        exit;
    }

    $pattern = '/^detail_record_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}(\.(csv|txt))?$/i';

    if (!preg_match($pattern, $file['name'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Filename must follow the format: detail_record_YYYY_MM_DD_HH_MM_SS.csv (or .txt)'
        ]);
        exit;
    }

    // Send to EC2 server
    $remote_url = 'http://ec2-18-214-80-27.compute-1.amazonaws.com/api/upload_api.php';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $remote_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'file' => new CURLFile($file['tmp_name'], $file['type'], $file['name'])
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to connect to remote server: ' . $curl_error
        ]);
        exit;
    }

    if ($http_code == 200) {
        echo $response;
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to upload to remote server (HTTP ' . $http_code . ')',
            'response' => $response
        ]);
    }
    exit;
}
?>