<?php
$upload_dir = '/home/ec2-user/detail-records/';

// if the upload directory does not exist, create it
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Set the response header to JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    // check file type
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_types = ['csv', 'txt', ''];

    if (!in_array($fileType, $allowed_types)) {
        echo json_encode([
            'success' => false,
            'message' => 'Only .csv, .txt files are allowed'
        ]);
        exit;
    }

    // check file name
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $file['name'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Filename can only contain letters, numbers, underscores, dashes and dots'
        ]);
        exit;
    }

    // handle same file name 
    $filename = $file['name'];
    $filepath = $upload_dir . $filename;

    if (file_exists($filepath)) {
        echo json_encode([
            'success' => false,
            'message' => 'File "' . $filename . '" already exists. Please rename the file or send another one.'
        ]);
        exit;
    }

    // save the uploaded file to the server
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // call the Python script to process the file
        $python_script = $upload_dir . 'insert_data.py';

        if (file_exists($python_script)) {
            $command = "python3 " . escapeshellarg($python_script) . " " . escapeshellarg($filepath) . " 2>&1";
            exec($command, $output, $return_var);

            echo json_encode([
                'success' => true,
                'message' => 'File uploaded and processed successfully!',
                'filename' => basename($filepath),
                'original_filename' => $file['name'],
                'details' => implode("\n", $output)
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'File uploaded but Python script not found: ' . $python_script,
                'filename' => basename($filepath)
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save file on server'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No file uploaded or invalid request method'
    ]);
}
?>