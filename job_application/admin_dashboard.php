<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Database connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'job_portal';

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    // Drop existing table if it has foreign key constraints
    $conn->query("DROP TABLE IF EXISTS `job_postings`");
    
    // Create table without foreign key constraints
    $table_sql = "CREATE TABLE IF NOT EXISTS `job_postings` (
        `job_id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(100) NOT NULL,
        `description` text NOT NULL,
        `requirements` text NOT NULL,
        `location` varchar(100) NOT NULL,
        `job_type` enum('Full-time','Part-time','Contract','Temporary') NOT NULL,
        `shift_schedule` varchar(100) DEFAULT NULL,
        `salary` varchar(50) DEFAULT NULL,
        `benefits` text DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `posted_by` int(11) DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        `job_categories` enum('Marketing','Sales','Education','Development','Tally Experts','Other') DEFAULT NULL,
        PRIMARY KEY (`job_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($table_sql)) {
        error_log("Error creating table: " . $conn->error);
        throw new Exception("Failed to create database table: " . $conn->error);
    }
    
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("System maintenance in progress. Please try again later. Error: " . $e->getMessage());
}

// Security functions
function validateInput($data) {
    return htmlspecialchars(trim(stripslashes($data)));
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // CSRF Protection
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'create':
                $requiredFields = ['title', 'description', 'requirements', 'location', 'job_type'];
                foreach ($requiredFields as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("Field '$field' is required");
                    }
                }
                
                $title = validateInput($_POST['title']);
                $description = validateInput($_POST['description']);
                $requirements = validateInput($_POST['requirements']);
                $location = validateInput($_POST['location']);
                $job_type = validateInput($_POST['job_type']);
                $shift_schedule = validateInput($_POST['shift_schedule'] ?? '');
                $salary = validateInput($_POST['salary'] ?? '');
                $benefits = validateInput($_POST['benefits'] ?? '');
                $job_categories = validateInput($_POST['job_categories'] ?? '');
                $posted_by = 1; // Default admin user
                
                $stmt = $conn->prepare("INSERT INTO job_postings (title, description, requirements, location, job_type, shift_schedule, salary, benefits, job_categories, posted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param("sssssssssi", $title, $description, $requirements, $location, $job_type, $shift_schedule, $salary, $benefits, $job_categories, $posted_by);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Job posted successfully', 'job_id' => $conn->insert_id]);
                } else {
                    throw new Exception('Failed to create job posting: ' . $stmt->error);
                }
                $stmt->close();
                break;
                
            case 'update':
                if (empty($_POST['job_id'])) {
                    throw new Exception('Job ID is required');
                }
                
                $requiredFields = ['title', 'description', 'requirements', 'location', 'job_type'];
                foreach ($requiredFields as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("Field '$field' is required");
                    }
                }
                
                $job_id = (int)$_POST['job_id'];
                $title = validateInput($_POST['title']);
                $description = validateInput($_POST['description']);
                $requirements = validateInput($_POST['requirements']);
                $location = validateInput($_POST['location']);
                $job_type = validateInput($_POST['job_type']);
                $shift_schedule = validateInput($_POST['shift_schedule'] ?? '');
                $salary = validateInput($_POST['salary'] ?? '');
                $benefits = validateInput($_POST['benefits'] ?? '');
                $job_categories = validateInput($_POST['job_categories'] ?? '');
                
                $stmt = $conn->prepare("UPDATE job_postings SET title = ?, description = ?, requirements = ?, location = ?, job_type = ?, shift_schedule = ?, salary = ?, benefits = ?, job_categories = ? WHERE job_id = ?");
                
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param("sssssssssi", $title, $description, $requirements, $location, $job_type, $shift_schedule, $salary, $benefits, $job_categories, $job_id);
                
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        echo json_encode(['success' => true, 'message' => 'Job updated successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'No changes made or job not found']);
                    }
                } else {
                    throw new Exception('Failed to update job posting: ' . $stmt->error);
                }
                $stmt->close();
                break;
                
            case 'delete':
                if (empty($_POST['job_id'])) {
                    throw new Exception('Job ID is required');
                }
                
                $job_id = (int)$_POST['job_id'];
                $stmt = $conn->prepare("DELETE FROM job_postings WHERE job_id = ?");
                
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param("i", $job_id);
                
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        echo json_encode(['success' => true, 'message' => 'Job deleted successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Job not found']);
                    }
                } else {
                    throw new Exception('Failed to delete job posting: ' . $stmt->error);
                }
                $stmt->close();
                break;
                
            case 'toggle_status':
                if (empty($_POST['job_id'])) {
                    throw new Exception('Job ID is required');
                }
                
                $job_id = (int)$_POST['job_id'];
                $stmt = $conn->prepare("UPDATE job_postings SET is_active = NOT is_active WHERE job_id = ?");
                
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param("i", $job_id);
                
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        echo json_encode(['success' => true, 'message' => 'Job status updated successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Job not found']);
                    }
                } else {
                    throw new Exception('Failed to update job status: ' . $stmt->error);
                }
                $stmt->close();
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        error_log("Database operation error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Handle GET requests for data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'];
    
    try {
        switch ($action) {
            case 'get_jobs':
                $search = $_GET['search'] ?? '';
                $category = $_GET['category'] ?? '';
                $status = $_GET['status'] ?? '';
                
                $query = "SELECT * FROM job_postings WHERE 1=1";
                $params = [];
                $types = "";
                
                if (!empty($search)) {
                    $query .= " AND (title LIKE ? OR location LIKE ?)";
                    $searchTerm = "%$search%";
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                    $types .= "ss";
                }
                
                if (!empty($category)) {
                    $query .= " AND job_categories = ?";
                    $params[] = $category;
                    $types .= "s";
                }
                
                if ($status !== '') {
                    $query .= " AND is_active = ?";
                    $params[] = (int)$status;
                    $types .= "i";
                }
                
                $query .= " ORDER BY created_at DESC";
                
                if (!empty($params)) {
                    $stmt = $conn->prepare($query);
                    if (!$stmt) {
                        throw new Exception("Prepare failed: " . $conn->error);
                    }
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                } else {
                    $result = $conn->query($query);
                    if (!$result) {
                        throw new Exception("Query failed: " . $conn->error);
                    }
                }
                
                $jobs = [];
                while ($row = $result->fetch_assoc()) {
                    $jobs[] = $row;
                }
                
                echo json_encode(['success' => true, 'data' => $jobs]);
                if (isset($stmt)) $stmt->close();
                break;
                
            case 'get_job':
                if (empty($_GET['job_id'])) {
                    throw new Exception('Job ID is required');
                }
                
                $job_id = (int)$_GET['job_id'];
                $stmt = $conn->prepare("SELECT * FROM job_postings WHERE job_id = ?");
                
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param("i", $job_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $job = $result->fetch_assoc();
                
                if ($job) {
                    echo json_encode(['success' => true, 'data' => $job]);
                } else {
                    throw new Exception('Job not found');
                }
                $stmt->close();
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        error_log("Database query error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - Professional Job Management Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #764ba2;
            --secondary: #f093fb;
            --success: #4facfe;
            --danger: #f093fb;
            --warning: #ffecd2;
            --info: #a8edea;
            --light: #f8f9fa;
            --dark: #343a40;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-success: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            --box-shadow-hover: 0 0.25rem 2rem 0 rgba(58, 59, 69, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        /* Navigation Styles */
        .bg-gradient-primary {
            background: var(--gradient-primary) !important;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            background: linear-gradient(180deg, #ffffff 0%, #f8f9fc 100%);
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, 0.1);
        }

        .sidebar-link {
            color: #5a5c69;
            font-weight: 500;
            padding: 1rem 1.5rem;
            border-radius: 0.35rem;
            margin: 0.25rem 0.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            display: block;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            color: white;
            background: var(--gradient-primary);
            transform: translateX(5px);
            box-shadow: var(--box-shadow);
            text-decoration: none;
        }

        .sidebar-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .sidebar-link:hover::before {
            left: 100%;
        }

        /* Main Content */
        main {
            padding-top: 60px;
        }

        .content-section {
            display: none;
            animation: fadeInUp 0.5s ease-out;
        }

        .content-section.active {
            display: block;
        }

        /* Card Animations */
        .animate-card {
            transform: translateY(20px);
            opacity: 0;
            transition: all 0.6s ease;
            animation: slideInUp 0.6s ease forwards;
        }

        .animate-card:nth-child(2) { animation-delay: 0.1s; }
        .animate-card:nth-child(3) { animation-delay: 0.2s; }
        .animate-card:nth-child(4) { animation-delay: 0.3s; }

        /* Statistics Cards */
        .border-left-primary {
            border-left: 0.25rem solid var(--primary) !important;
        }

        .border-left-success {
            border-left: 0.25rem solid #1cc88a !important;
        }

        .border-left-info {
            border-left: 0.25rem solid #36b9cc !important;
        }

        .border-left-warning {
            border-left: 0.25rem solid #f6c23e !important;
        }

        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: var(--box-shadow);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow-hover);
        }

        /* Form Styles */
        .form-control-animated,
        .form-select {
            border: 2px solid #e3e6f0;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            background: #fff;
        }

        .form-control-animated:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            transform: translateY(-2px);
        }

        .form-label {
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 0.5rem;
        }

        /* Button Animations */
        .btn-animated {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .btn-animated:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .btn-animated::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-animated:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: var(--gradient-primary);
            border: none;
        }

        .btn-primary:hover {
            background: var(--gradient-primary);
            filter: brightness(1.1);
        }

        /* Table Styles */
        .table {
            background: white;
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .table thead th {
            background: var(--gradient-primary);
            color: white;
            font-weight: 600;
            border: none;
            padding: 1rem;
        }

        .table tbody tr {
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background: rgba(102, 126, 234, 0.05);
            transform: translateX(5px);
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-top: 1px solid #e3e6f0;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .status-inactive {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            color: #8b4513;
        }

        /* Action Buttons */
        .action-btn {
            padding: 0.5rem;
            margin: 0 0.25rem;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            border: none;
        }

        .action-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.2);
        }

        .btn-edit {
            background: var(--gradient-success);
            color: white;
        }

        .btn-delete {
            background: var(--gradient-secondary);
            color: white;
        }

        .btn-toggle {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            color: #8b4513;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-overlay.show {
            display: flex;
        }

        .spinner-border {
            color: var(--primary);
        }

        /* Toast Notifications */
        .toast {
            border-radius: 0.75rem;
            box-shadow: var(--box-shadow);
            border: none;
        }

        .toast-header {
            background: var(--gradient-primary);
            color: white;
            border: none;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        .pulse-animation {
            animation: pulse 2s infinite;
        }

        /* Form Validation */
        .is-invalid {
            border-color: #dc3545;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 20%, 40%, 60%, 80%, 100% {
                transform: translateX(0);
            }
            10%, 30%, 50%, 70%, 90% {
                transform: translateX(-5px);
            }
        }

        /* Success States */
        .form-control.is-valid,
        .form-select.is-valid {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            main {
                margin-left: 0 !important;
            }
            
            .animate-card {
                margin-bottom: 1rem;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .action-btn {
                width: 30px;
                height: 30px;
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-gradient-primary shadow-lg">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fas fa-briefcase me-2"></i>
                HRMS Portal
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-light" href="#">
                    <i class="fas fa-user-circle me-1"></i>
                    Admin Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active sidebar-link" href="#" data-section="dashboard">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link sidebar-link" href="#" data-section="create-job">
                                <i class="fas fa-plus-circle me-2"></i>
                                Create Job
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link sidebar-link" href="#" data-section="manage-jobs">
                                <i class="fas fa-list-ul me-2"></i>
                                Manage Jobs
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Dashboard Section -->
                <div id="dashboard" class="content-section active">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold">Job Posting Dashboard</h1>
                    </div>
                    
                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-primary shadow h-100 py-2 animate-card">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Total Jobs Posted
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="total-jobs">0</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-briefcase fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-success shadow h-100 py-2 animate-card">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                Active Positions
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="active-jobs">0</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-info shadow h-100 py-2 animate-card">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                Categories
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800">6</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-tags fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card border-left-warning shadow h-100 py-2 animate-card">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                This Month
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="monthly-jobs">0</div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Jobs Table -->
                    <div class="card shadow mb-4 animate-card">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Job Postings</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="recent-jobs-table">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Category</th>
                                            <th>Location</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Populated by JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Create Job Section -->
                <div id="create-job" class="content-section">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold">Create New Job Posting</h1>
                    </div>
                    
                    <div class="card shadow animate-card">
                        <div class="card-body">
                            <form id="job-form" class="needs-validation" novalidate>
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="create">
                                <input type="hidden" name="job_id" id="edit-job-id">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="title" class="form-label">Job Title *</label>
                                        <input type="text" class="form-control form-control-animated" id="title" name="title" required>
                                        <div class="invalid-feedback">Please provide a valid job title.</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="location" class="form-label">Location *</label>
                                        <input type="text" class="form-control form-control-animated" id="location" name="location" required>
                                        <div class="invalid-feedback">Please provide a valid location.</div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="job_type" class="form-label">Job Type *</label>
                                        <select class="form-select form-control-animated" id="job_type" name="job_type" required>
                                            <option value="">Select Job Type</option>
                                            <option value="Full-time">Full-time</option>
                                            <option value="Part-time">Part-time</option>
                                            <option value="Contract">Contract</option>
                                            <option value="Temporary">Temporary</option>
                                        </select>
                                        <div class="invalid-feedback">Please select a job type.</div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="job_categories" class="form-label">Category</label>
                                        <select class="form-select form-control-animated" id="job_categories" name="job_categories">
                                            <option value="">Select Category</option>
                                            <option value="Marketing">Marketing</option>
                                            <option value="Sales">Sales</option>
                                            <option value="Education">Education</option>
                                            <option value="Development">Development</option>
                                            <option value="Tally Experts">Tally Experts</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="salary" class="form-label">Salary Range</label>
                                        <input type="text" class="form-control form-control-animated" id="salary" name="salary" placeholder="e.g., ₹50,000 - ₹70,000">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="shift_schedule" class="form-label">Shift Schedule</label>
                                    <input type="text" class="form-control form-control-animated" id="shift_schedule" name="shift_schedule" placeholder="e.g., 9 AM - 5 PM, Monday to Friday">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Job Description *</label>
                                    <textarea class="form-control form-control-animated" id="description" name="description" rows="5" required placeholder="Describe the role, responsibilities, and company culture..."></textarea>
                                    <div class="invalid-feedback">Please provide a job description.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="requirements" class="form-label">Requirements *</label>
                                    <textarea class="form-control form-control-animated" id="requirements" name="requirements" rows="4" required placeholder="List the required skills, experience, and qualifications..."></textarea>
                                    <div class="invalid-feedback">Please provide job requirements.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="benefits" class="form-label">Benefits & Perks</label>
                                    <textarea class="form-control form-control-animated" id="benefits" name="benefits" rows="3" placeholder="Health insurance, paid time off, retirement plans, etc..."></textarea>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="button" class="btn btn-secondary me-md-2" id="cancel-btn">Cancel</button>
                                    <button type="submit" class="btn btn-primary btn-animated">
                                        <i class="fas fa-save me-2"></i>
                                        <span id="submit-text">Create Job Posting</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Manage Jobs Section -->
                <div id="manage-jobs" class="content-section">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold">Manage Job Postings</h1>
                        <button class="btn btn-primary btn-animated" onclick="showCreateForm()">
                            <i class="fas fa-plus me-2"></i>
                            New Job Posting
                        </button>
                    </div>
                    
                    <!-- Filters -->
                    <div class="card shadow mb-4 animate-card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="search-filter" class="form-label">Search</label>
                                    <input type="text" class="form-control form-control-animated" id="search-filter" placeholder="Search by title or location...">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="category-filter" class="form-label">Category</label>
                                    <select class="form-select form-control-animated" id="category-filter">
                                        <option value="">All Categories</option>
                                        <option value="Marketing">Marketing</option>
                                        <option value="Sales">Sales</option>
                                        <option value="Education">Education</option>
                                        <option value="Development">Development</option>
                                        <option value="Tally Experts">Tally Experts</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="status-filter" class="form-label">Status</label>
                                    <select class="form-select form-control-animated" id="status-filter">
                                        <option value="">All Status</option>
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Jobs Table -->
                    <div class="card shadow mb-4 animate-card">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">All Job Postings</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="jobs-table">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Category</th>
                                            <th>Location</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Populated by JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading">
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Processing...</p>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 10000;">
        <div id="notification-toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <i class="fas fa-info-circle me-2"></i>
                <strong class="me-auto">HRMS Notification</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toast-message">
                Message goes here
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Global variables
        let currentJobs = [];
        let editingJobId = null;

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initializeDashboard();
            setupEventListeners();
            loadJobs();
            updateDashboardStats();
        });

        // Initialize dashboard components
        function initializeDashboard() {
            showSection('dashboard');
            
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        // Setup event listeners
        function setupEventListeners() {
            // Sidebar navigation
            document.querySelectorAll('.sidebar-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const section = this.getAttribute('data-section');
                    showSection(section);
                    
                    // Update active state
                    document.querySelectorAll('.sidebar-link').forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                });
            });
            
            // Job form submission
            document.getElementById('job-form').addEventListener('submit', handleJobSubmit);
            
            // Cancel button
            document.getElementById('cancel-btn').addEventListener('click', function() {
                resetForm();
                showSection('manage-jobs');
            });
            
            // Search and filter functionality
            document.getElementById('search-filter').addEventListener('input', debounce(filterJobs, 300));
            document.getElementById('category-filter').addEventListener('change', filterJobs);
            document.getElementById('status-filter').addEventListener('change', filterJobs);
            
            // Form validation
            setupFormValidation();
        }

        // Show specific section
        function showSection(sectionId) {
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            
            document.getElementById(sectionId).classList.add('active');
            
            if (sectionId === 'manage-jobs') {
                loadJobs();
            } else if (sectionId === 'dashboard') {
                updateDashboardStats();
                loadRecentJobs();
            }
        }

        // Load jobs from server
        async function loadJobs() {
            showLoading(true);
            
            try {
                const response = await fetch(window.location.pathname + '?action=get_jobs&t=' + Date.now());
                const data = await response.json();
                
                if (data.success) {
                    currentJobs = data.data;
                    renderJobsTable();
                    showNotification('Jobs loaded successfully', 'success');
                } else {
                    showNotification('Error loading jobs: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Error loading jobs:', error);
                showNotification('Failed to load jobs. Please try again.', 'error');
            } finally {
                showLoading(false);
            }
        }

        // Render jobs table
        function renderJobsTable() {
            const tbody = document.querySelector('#jobs-table tbody');
            tbody.innerHTML = '';
            
            if (currentJobs.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No job postings found. Create your first job posting to get started.</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            currentJobs.forEach(job => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <strong>${escapeHtml(job.title)}</strong>
                    </td>
                    <td>
                        <span class="badge bg-info">${escapeHtml(job.job_categories || 'Not specified')}</span>
                    </td>
                    <td>
                        <i class="fas fa-map-marker-alt me-1 text-muted"></i>
                        ${escapeHtml(job.location)}
                    </td>
                    <td>
                        <span class="badge bg-secondary">${escapeHtml(job.job_type)}</span>
                    </td>
                    <td>
                        <span class="status-badge ${job.is_active == 1 ? 'status-active' : 'status-inactive'}">
                            ${job.is_active == 1 ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td>
                        ${formatDate(job.created_at)}
                    </td>
                    <td>
                        <button class="btn action-btn btn-edit" onclick="editJob(${job.job_id})" title="Edit Job">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn action-btn btn-toggle" onclick="toggleJobStatus(${job.job_id})" title="Toggle Status">
                            <i class="fas fa-toggle-${job.is_active == 1 ? 'on' : 'off'}"></i>
                        </button>
                        <button class="btn action-btn btn-delete" onclick="deleteJob(${job.job_id})" title="Delete Job">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Handle job form submission
        async function handleJobSubmit(e) {
            e.preventDefault();
            
            if (!validateForm()) {
                return;
            }
            
            const formData = new FormData(this);
            const submitBtn = document.querySelector('#job-form button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            
            try {
                const response = await fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    resetForm();
                    loadJobs();
                    showSection('manage-jobs');
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Error submitting form:', error);
                showNotification('Failed to save job posting. Please try again.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        }

        // Edit job
        async function editJob(jobId) {
            try {
                const response = await fetch(`${window.location.pathname}?action=get_job&job_id=${jobId}`);
                const data = await response.json();
                
                if (data.success) {
                    const job = data.data;
                    populateForm(job);
                    editingJobId = jobId;
                    document.querySelector('input[name="action"]').value = 'update';
                    document.getElementById('edit-job-id').value = jobId;
                    document.getElementById('submit-text').textContent = 'Update Job Posting';
                    showSection('create-job');
                } else {
                    showNotification('Error loading job details: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Error loading job:', error);
                showNotification('Failed to load job details.', 'error');
            }
        }

        // Populate form with job data
        function populateForm(job) {
            document.getElementById('title').value = job.title || '';
            document.getElementById('location').value = job.location || '';
            document.getElementById('job_type').value = job.job_type || '';
            document.getElementById('job_categories').value = job.job_categories || '';
            document.getElementById('salary').value = job.salary || '';
            document.getElementById('shift_schedule').value = job.shift_schedule || '';
            document.getElementById('description').value = job.description || '';
            document.getElementById('requirements').value = job.requirements || '';
            document.getElementById('benefits').value = job.benefits || '';
        }

        // Delete job
        async function deleteJob(jobId) {
            if (!confirm('Are you sure you want to delete this job posting? This action cannot be undone.')) {
                return;
            }
            
            showLoading(true);
            
            try {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('job_id', jobId);
                formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                
                const response = await fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    loadJobs();
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Error deleting job:', error);
                showNotification('Failed to delete job posting.', 'error');
            } finally {
                showLoading(false);
            }
        }

        // Toggle job status
        async function toggleJobStatus(jobId) {
            showLoading(true);
            
            try {
                const formData = new FormData();
                formData.append('action', 'toggle_status');
                formData.append('job_id', jobId);
                formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                
                const response = await fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification(data.message, 'success');
                    loadJobs();
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Error toggling job status:', error);
                showNotification('Failed to update job status.', 'error');
            } finally {
                showLoading(false);
            }
        }

        // Filter jobs
        function filterJobs() {
            const search = document.getElementById('search-filter').value.toLowerCase();
            const category = document.getElementById('category-filter').value;
            const status = document.getElementById('status-filter').value;
            
            let filteredJobs = currentJobs;
            
            if (search) {
                filteredJobs = filteredJobs.filter(job => 
                    job.title.toLowerCase().includes(search) || 
                    job.location.toLowerCase().includes(search)
                );
            }
            
            if (category) {
                filteredJobs = filteredJobs.filter(job => job.job_categories === category);
            }
            
            if (status !== '') {
                filteredJobs = filteredJobs.filter(job => job.is_active == status);
            }
            
            const originalJobs = currentJobs;
            currentJobs = filteredJobs;
            renderJobsTable();
            currentJobs = originalJobs;
        }

        // Update dashboard statistics
        function updateDashboardStats() {
            if (currentJobs.length === 0) {
                loadJobs().then(() => {
                    calculateStats();
                });
            } else {
                calculateStats();
            }
        }

        // Calculate and display statistics
        function calculateStats() {
            const totalJobs = currentJobs.length;
            const activeJobs = currentJobs.filter(job => job.is_active == 1).length;
            
            const currentMonth = new Date().getMonth();
            const currentYear = new Date().getFullYear();
            const monthlyJobs = currentJobs.filter(job => {
                const jobDate = new Date(job.created_at);
                return jobDate.getMonth() === currentMonth && jobDate.getFullYear() === currentYear;
            }).length;
            
            animateCounter('total-jobs', totalJobs);
            animateCounter('active-jobs', activeJobs);
            animateCounter('monthly-jobs', monthlyJobs);
        }

        // Load recent jobs for dashboard
        function loadRecentJobs() {
            const recentJobs = currentJobs.slice(0, 5);
            const tbody = document.querySelector('#recent-jobs-table tbody');
            tbody.innerHTML = '';
            
            if (recentJobs.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <i class="fas fa-briefcase fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">No recent job postings</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            recentJobs.forEach(job => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${escapeHtml(job.title)}</strong></td>
                    <td><span class="badge bg-info">${escapeHtml(job.job_categories || 'Not specified')}</span></td>
                    <td>${escapeHtml(job.location)}</td>
                    <td><span class="badge bg-secondary">${escapeHtml(job.job_type)}</span></td>
                    <td><span class="status-badge ${job.is_active == 1 ? 'status-active' : 'status-inactive'}">${job.is_active == 1 ? 'Active' : 'Inactive'}</span></td>
                    <td>${formatDate(job.created_at)}</td>
                `;
                tbody.appendChild(row);
            });
        }

        // Form validation
        function validateForm() {
            const form = document.getElementById('job-form');
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                    field.classList.add('is-valid');
                }
            });
            
            return isValid;
        }

        // Setup form validation
        function setupFormValidation() {
            const form = document.getElementById('job-form');
            const inputs = form.querySelectorAll('input, textarea, select');
            
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.hasAttribute('required')) {
                        if (!this.value.trim()) {
                            this.classList.add('is-invalid');
                            this.classList.remove('is-valid');
                        } else {
                            this.classList.remove('is-invalid');
                            this.classList.add('is-valid');
                        }
                    }
                });
                
                input.addEventListener('input', function() {
                    if (this.classList.contains('is-invalid') && this.value.trim()) {
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    }
                });
            });
        }

        // Reset form
        function resetForm() {
            const form = document.getElementById('job-form');
            form.reset();
            form.classList.remove('was-validated');
            
            form.querySelectorAll('.is-valid, .is-invalid').forEach(field => {
                field.classList.remove('is-valid', 'is-invalid');
            });
            
            document.querySelector('input[name="action"]').value = 'create';
            document.getElementById('edit-job-id').value = '';
            document.getElementById('submit-text').textContent = 'Create Job Posting';
            editingJobId = null;
        }

        // Show create form
        function showCreateForm() {
            resetForm();
            showSection('create-job');
        }

        // Utility functions
        function showLoading(show) {
            const loading = document.getElementById('loading');
            if (show) {
                loading.classList.add('show');
            } else {
                loading.classList.remove('show');
            }
        }

        function showNotification(message, type = 'info') {
            const toast = document.getElementById('notification-toast');
            const toastBody = document.getElementById('toast-message');
            const toastHeader = toast.querySelector('.toast-header');
            
            // Clear any existing classes and set new ones
            toast.className = 'toast';
            if (type === 'success') {
                toast.classList.add('bg-success', 'text-white');
            } else if (type === 'error') {
                toast.classList.add('bg-danger', 'text-white');
            } else if (type === 'warning') {
                toast.classList.add('bg-warning', 'text-dark');
            } else {
                toast.classList.add('bg-primary', 'text-white');
            }
            
            toastBody.textContent = message;
            
            const icon = toastHeader.querySelector('i');
            icon.className = `fas me-2 ${getIconForType(type)}`;
            
            const bsToast = new bootstrap.Toast(toast, {
                autohide: true,
                delay: 4000
            });
            bsToast.show();
            
            // Log to console for debugging
            console.log(`Notification: ${type} - ${message}`);
        }

        function getIconForType(type) {
            switch (type) {
                case 'success':
                    return 'fa-check-circle text-success';
                case 'error':
                    return 'fa-exclamation-circle text-danger';
                case 'warning':
                    return 'fa-exclamation-triangle text-warning';
                default:
                    return 'fa-info-circle text-primary';
            }
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function animateCounter(elementId, targetValue) {
            const element = document.getElementById(elementId);
            const startValue = 0;
            const duration = 1000;
            const startTime = performance.now();
            
            function updateCounter(currentTime) {
                const elapsedTime = currentTime - startTime;
                const progress = Math.min(elapsedTime / duration, 1);
                const currentValue = Math.floor(startValue + (targetValue - startValue) * progress);
                
                element.textContent = currentValue;
                
                if (progress < 1) {
                    requestAnimationFrame(updateCounter);
                }
            }
            
            requestAnimationFrame(updateCounter);
        }
    </script>
</body>
</html>