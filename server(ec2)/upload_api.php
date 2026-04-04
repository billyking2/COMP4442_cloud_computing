<?php
header('Content-Type: application/json');

$upload_dir = '/home/ec2-user/detail-records/';

function rollback($filepath)
{
    if (file_exists($filepath)) {
        unlink($filepath);
        error_log("Rollback: Deleted file " . $filepath);
        return true;
    }
    return false;
}


// if the upload directory does not exist, create it
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// check if file was uploaded
if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

// check if upload directory is writable
if (!is_writable($upload_dir)) {
    echo json_encode([
        'success' => false,
        'message' => 'Upload directory is not writable',
        'upload_dir' => $upload_dir,
        'is_writable' => is_writable($upload_dir)
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $file = $_FILES['file'];

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
        error_log("Duplicate file upload attempted: " . $filename);

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

            $output_str = implode("\n", $output);

            $insert_success = false;
            $error_message = null;

            if ($return_var === 0) {

                if (strpos($output_str, '__INSERT_SUCCESS__') !== false) {
                    $insert_success = true;
                } else {

                    if (
                        strpos($output_str, 'success') !== false ||
                        strpos($output_str, 'Success') !== false ||
                        strpos($output_str, 'inserted') !== false ||
                        strpos($output_str, 'completed') !== false ||
                        strpos($output_str, 'finished') !== false
                    ) {

                        if (
                            strpos($output_str, 'error') === false &&
                            strpos($output_str, 'Error') === false &&
                            strpos($output_str, 'failed') === false &&
                            strpos($output_str, 'Failed') === false
                        ) {
                            $insert_success = true;
                        } else {
                            $error_message = 'Python script reported errors: ' . substr($output_str, 0, 500);
                        }
                    }
                }
            } else {
                $error_message = 'Python script failed with code ' . $return_var . ': ' . substr($output_str, 0, 500);
            }

            if ($insert_success) {
                echo json_encode([
                    'success' => true,
                    'message' => 'File uploaded and processed successfully!',
                    'filename' => basename($filepath),
                    'original_filename' => $file['name'],
                    'details' => implode("\n", $output)
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => $error_message ?: 'File uploaded but data insertion failed',
                    'filename' => basename($filepath),
                    'original_filename' => $file['name'],
                    'details' => implode("\n", $output),
                    'return_code' => $return_var,
                    'rollback' => rollback($filepath)
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Python script not found: ' . $python_script,
                'filename' => basename($filepath),
                'rollback' => rollback($filepath)
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => "Failed to save file on server. $filepath, upload_dir_exists: " . (file_exists($upload_dir) ? 'true' : 'false') . ", upload_dir_writable: " . (is_writable($upload_dir) ? 'true' : 'false') . ", parent_dir_writable: " . (is_writable(dirname($upload_dir)) ? 'true' : 'false') . ", php_user: " . get_current_user() . ", tmp_file_exists: " . (file_exists($file['tmp_name']) ? 'true' : 'false') . ", last_error: " . json_encode(error_get_last())
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No file uploaded or invalid request method'
    ]);
}
?>