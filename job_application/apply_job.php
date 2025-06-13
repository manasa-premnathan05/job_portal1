<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    error_log("Database connection failed: " . $conn->connect_error);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

$seeker_id = $_SESSION['user_id'];
$job_id = isset($_POST['job_id']) ? intval($_POST['job_id']) : 0;
$cover_letter = isset($_POST['cover_letter']) ? trim($_POST['cover_letter']) : '';

// Log received data for debugging
error_log("Received application data - Seeker ID: $seeker_id, Job ID: $job_id, Cover Letter Length: " . strlen($cover_letter));

// Validate inputs
if ($job_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid job ID']);
    exit();
}

if (empty($cover_letter)) {
    echo json_encode(['success' => false, 'error' => 'Cover letter is required']);
    exit();
}

try {
    // First verify the seeker exists in job_seekers table
    $seeker_check_sql = "SELECT seeker_id FROM job_seekers WHERE seeker_id = ?";
    $seeker_stmt = $conn->prepare($seeker_check_sql);
    if (!$seeker_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $seeker_stmt->bind_param("i", $seeker_id);
    $seeker_stmt->execute();
    $seeker_result = $seeker_stmt->get_result();
    
    if ($seeker_result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid user account']);
        exit();
    }
    $seeker_stmt->close();

    // Check if user has a CV in applicant_details
    $cv_check_sql = "SELECT cv_path FROM applicant_details WHERE seeker_id = ? AND cv_path IS NOT NULL";
    $cv_stmt = $conn->prepare($cv_check_sql);
    if (!$cv_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $cv_stmt->bind_param("i", $seeker_id);
    $cv_stmt->execute();
    $cv_result = $cv_stmt->get_result();
    
    if ($cv_result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Please upload your CV before applying']);
        exit();
    }
    $cv_stmt->close();
    
    // Check if job exists and is active
    $job_check_sql = "SELECT job_id FROM job_postings WHERE job_id = ? AND is_active = 1";
    $job_stmt = $conn->prepare($job_check_sql);
    if (!$job_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $job_stmt->bind_param("i", $job_id);
    $job_stmt->execute();
    $job_result = $job_stmt->get_result();
    
    if ($job_result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Job not found or no longer active']);
        exit();
    }
    $job_stmt->close();
    
    // Check if user has already applied for this job
    $existing_check_sql = "SELECT application_id FROM applications WHERE job_id = ? AND seeker_id = ?";
    $existing_stmt = $conn->prepare($existing_check_sql);
    if (!$existing_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $existing_stmt->bind_param("ii", $job_id, $seeker_id);
    $existing_stmt->execute();
    $existing_result = $existing_stmt->get_result();
    
    if ($existing_result->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'You have already applied for this job']);
        exit();
    }
    $existing_stmt->close();
    
    // Insert application with cover letter
    $apply_sql = "INSERT INTO applications (job_id, seeker_id, application_date, cover_letter, status) 
                 VALUES (?, ?, CURRENT_TIMESTAMP, ?, 'Submitted')";
    $apply_stmt = $conn->prepare($apply_sql);
    if (!$apply_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $apply_stmt->bind_param("iis", $job_id, $seeker_id, $cover_letter);
    
    if ($apply_stmt->execute()) {
        // Also update the applicant_details with the cover letter (optional)
        $update_sql = "UPDATE applicant_details SET cover_letter = ? WHERE seeker_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $cover_letter, $seeker_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        echo json_encode(['success' => true, 'message' => 'Application submitted successfully']);
    } else {
        error_log("Application submission failed: " . $apply_stmt->error);
        echo json_encode(['success' => false, 'error' => 'Failed to submit application: ' . $apply_stmt->error]);
    }
    
    $apply_stmt->close();
    
} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>