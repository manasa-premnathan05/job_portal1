<?php
session_start();
header('Content-Type: application/json');

// Check if the user is logged in as a job seeker
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'job_seeker') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit();
}

// Database connection
$conn = new mysqli("127.0.0.1", "root", "", "job_portal");
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$seeker_id = $_SESSION['user_id'];

// Check if file was uploaded
if (!isset($_FILES['cv']) || $_FILES['cv']['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        0 => 'There is no error, the file uploaded with success',
        1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        3 => 'The uploaded file was only partially uploaded',
        4 => 'No file was uploaded',
        6 => 'Missing a temporary folder',
        7 => 'Failed to write file to disk.',
        8 => 'A PHP extension stopped the file upload.'
    ];
    
    $error_code = $_FILES['cv']['error'] ?? 4;
    echo json_encode(['success' => false, 'error' => 'File upload error: ' . $upload_errors[$error_code]]);
    exit();
}

$file = $_FILES['cv'];

// Validate file type (PDF only)
$allowed_types = ['application/pdf', 'application/x-pdf'];
$file_info = finfo_open(FILEINFO_MIME_TYPE);
$file_type = finfo_file($file_info, $file['tmp_name']);
finfo_close($file_info);

if (!in_array($file_type, $allowed_types)) {
    echo json_encode(['success' => false, 'error' => 'Only PDF files are allowed. File type detected: ' . $file_type]);
    exit();
}

// Validate file size (max 2MB)
if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'File size must be less than 2MB']);
    exit();
}

// Create uploads directory if it doesn't exist - use absolute path
$upload_dir = __DIR__ . '/uploads/cvs/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create upload directory']);
        exit();
    }
}

// Generate unique filename
$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'cv_' . $seeker_id . '_' . time() . '.' . $file_extension;
$file_path = $upload_dir . $filename;
$relative_file_path = 'uploads/cvs/' . $filename; // For database storage

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save file. Check directory permissions.']);
    exit();
}

// Update database
try {
    // Check if record exists
    $check_sql = "SELECT seeker_id FROM applicant_details WHERE seeker_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $seeker_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing record - first delete old file if it exists
        $get_old_sql = "SELECT cv_path FROM applicant_details WHERE seeker_id = ?";
        $get_old_stmt = $conn->prepare($get_old_sql);
        $get_old_stmt->bind_param("i", $seeker_id);
        $get_old_stmt->execute();
        $old_result = $get_old_stmt->get_result();
        $old_data = $old_result->fetch_assoc();
        
        if (!empty($old_data['cv_path']) && file_exists(__DIR__ . '/' . $old_data['cv_path'])) {
            unlink(__DIR__ . '/' . $old_data['cv_path']);
        }
        
        // Now update
        $sql = "UPDATE applicant_details SET cv_path = ?, updated_at = CURRENT_TIMESTAMP WHERE seeker_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $relative_file_path, $seeker_id);
    } else {
        // Insert new record
        $sql = "INSERT INTO applicant_details (seeker_id, cv_path, created_at, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $seeker_id, $relative_file_path);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'CV uploaded successfully', 'file_path' => $relative_file_path]);
    } else {
        // Delete uploaded file if database update fails
        unlink($file_path);
        echo json_encode(['success' => false, 'error' => 'Failed to update database: ' . $stmt->error]);
    }
    
    if (isset($stmt)) $stmt->close();
    if (isset($check_stmt)) $check_stmt->close();
    if (isset($get_old_stmt)) $get_old_stmt->close();
    
} catch (Exception $e) {
    // Delete uploaded file if there's an error
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>