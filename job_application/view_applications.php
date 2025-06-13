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
    
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("System maintenance in progress. Please try again later. Error: " . $e->getMessage());
}
// Add this to handle POST requests in view_applications.php
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
            case 'update_application_status':
                if (empty($_POST['application_id']) || empty($_POST['status'])) {
                    throw new Exception('Application ID and status are required');
                }
                
                $application_id = (int)$_POST['application_id'];
                $status = validateInput($_POST['status']);
                
                $stmt = $conn->prepare("UPDATE applications SET status = ? WHERE application_id = ?");
                
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $stmt->bind_param("si", $status, $application_id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
                } else {
                    throw new Exception('Failed to update status: ' . $stmt->error);
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

// Handle GET requests for applications data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $action = $_GET['action'];
    
    try {
        switch ($action) {
            // In view_applications.php, replace the get_applications case with this:
case 'get_applications':
    $job_id = $_GET['job_id'] ?? '';
    
    $query = "SELECT 
        a.application_id, 
        a.job_id, 
        a.seeker_id, 
        a.application_date, 
        a.cover_letter, 
        a.status,
        j.title AS job_title,
        js.full_name,
        js.email,
        js.phone,
        ad.cv_path
    FROM applications a
    JOIN job_postings j ON a.job_id = j.job_id
    JOIN job_seekers js ON a.seeker_id = js.seeker_id
    LEFT JOIN applicant_details ad ON a.seeker_id = ad.seeker_id
    WHERE a.job_id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $applications = [];
    while ($row = $result->fetch_assoc()) {
        $applications[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $applications]);
    $stmt->close();
    break;
                
            case 'get_jobs_with_applications':
                $query = "SELECT 
                    j.job_id,
                    j.title,
                    COUNT(a.application_id) AS application_count
                FROM job_postings j
                LEFT JOIN applications a ON j.job_id = a.job_id
                GROUP BY j.job_id, j.title
                HAVING application_count > 0
                ORDER BY j.title";
                
                $result = $conn->query($query);
                if (!$result) {
                    throw new Exception("Query failed: " . $conn->error);
                }
                
                $jobs = [];
                while ($row = $result->fetch_assoc()) {
                    $jobs[] = $row;
                }
                
                echo json_encode(['success' => true, 'data' => $jobs]);
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

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRMS - Job Applications Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* Your existing styles from admin_dashboard */
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

        .status-submitted {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            color: #5a5c69;
        }

        .status-review {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            color: #8b4513;
        }

        .status-shortlisted {
            background: linear-gradient(135deg, #c2e9fb 0%, #a1c4fd 100%);
            color: #1a237e;
        }

        .status-rejected {
            background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%);
            color: #b71c1c;
        }

        .status-hired {
            background: linear-gradient(135deg, #a1ffce 0%, #faffd1 100%);
            color: #1b5e20;
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

        /* Application Card */
        .application-card {
            border-left: 4px solid var(--primary);
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .application-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--box-shadow-hover);
        }

        .cv-preview {
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid #e3e6f0;
            border-radius: 0.5rem;
            padding: 1rem;
            background: white;
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
                            <a class="nav-link sidebar-link" href="#" data-section="dashboard">
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
                        <li class="nav-item">
                            <a class="nav-link sidebar-link active" href="#" data-section="view-applications">
                                <i class="fas fa-users me-2"></i>
                                View Applications
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- View Applications Section -->
                <div id="view-applications" class="content-section active">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold">Job Applications Management</h1>
                    </div>
                    
                    <!-- Job Selection Card -->
                    <div class="card shadow mb-4 animate-card">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Select Job to View Applications</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <select class="form-select form-control-animated" id="job-selector">
                                        <option value="">-- Select a Job --</option>
                                        <!-- Populated by JavaScript -->
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <button class="btn btn-primary btn-animated w-100" id="load-applications-btn">
                                        <i class="fas fa-search me-2"></i>
                                        View Applications
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Applications Summary Card -->
                    <div class="card shadow mb-4 animate-card" id="applications-summary-card" style="display: none;">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary" id="job-title-header">Applications for: </h6>
                            <div>
                                <span class="badge bg-primary me-2" id="total-applications">0 Total</span>
                                <span class="badge bg-success me-2" id="new-applications">0 New</span>
                                <span class="badge bg-info" id="hired-applications">0 Hired</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered" id="applications-table">
                                    <thead>
                                        <tr>
                                            <th>Applicant</th>
                                            <th>Contact</th>
                                            <th>Applied On</th>
                                            <th>Status</th>
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

                    <!-- Application Details Modal -->
                    <div class="modal fade" id="applicationDetailsModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-gradient-primary text-white">
                                    <h5 class="modal-title" id="applicantNameModal">Application Details</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <h6 class="fw-bold">Personal Information</h6>
                                            <p><i class="fas fa-user me-2"></i> <span id="modalFullName"></span></p>
                                            <p><i class="fas fa-envelope me-2"></i> <span id="modalEmail"></span></p>
                                            <p><i class="fas fa-phone me-2"></i> <span id="modalPhone"></span></p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="fw-bold">Application Details</h6>
                                            <p><i class="fas fa-briefcase me-2"></i> <span id="modalJobTitle"></span></p>
                                            <p><i class="fas fa-calendar me-2"></i> Applied on: <span id="modalAppliedDate"></span></p>
                                            <p><i class="fas fa-tag me-2"></i> Status: <span id="modalStatus"></span></p>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h6 class="fw-bold">Cover Letter</h6>
                                        <div class="card p-3 bg-light" id="modalCoverLetter">
                                            No cover letter provided.
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <h6 class="fw-bold">CV/Resume</h6>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <a href="#" class="btn btn-primary btn-animated" id="downloadCvBtn" target="_blank">
                                                <i class="fas fa-download me-2"></i>
                                                Download CV
                                            </a>
                                            <select class="form-select form-control-animated w-auto" id="statusSelector">
                                                <option value="Submitted">Submitted</option>
                                                <option value="Under Review">Under Review</option>
                                                <option value="Shortlisted">Shortlisted</option>
                                                <option value="Rejected">Rejected</option>
                                                <option value="Hired">Hired</option>
                                            </select>
                                            <button class="btn btn-success btn-animated" id="updateStatusBtn">
                                                <i class="fas fa-save me-2"></i>
                                                Update Status
                                            </button>
                                        </div>
                                        <div class="cv-preview" id="cvPreviewContainer">
                                            <p class="text-muted">CV preview will appear here</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
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
    <input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo $csrf_token; ?>">

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- PDF.js for CV preview -->
     
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.min.js"></script>
    
    <script>
        // Global variables
        let currentApplications = [];
        let currentJobId = null;
        let currentApplicationId = null;
        
        // Set PDF.js worker path
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.worker.min.js';

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing applications page');
            setupEventListeners();
            loadJobSelector();
        });

        // Setup event listeners
        function setupEventListeners() {
            console.log('Setting up event listeners');
            
            // Sidebar navigation
            document.querySelectorAll('.sidebar-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const section = this.getAttribute('data-section');
                    console.log(`Sidebar link clicked, switching to section: ${section}`);
                    
                    // Update active state
                    document.querySelectorAll('.sidebar-link').forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                });
            });
            
            // Load applications button
            document.getElementById('load-applications-btn').addEventListener('click', function() {
                const jobId = document.getElementById('job-selector').value;
                if (jobId) {
                    loadApplications(jobId);
                } else {
                    showNotification('Please select a job first', 'error');
                }
            });
            
            // Job selector change
            document.getElementById('job-selector').addEventListener('change', function() {
                const jobId = this.value;
                if (jobId) {
                    currentJobId = jobId;
                    const selectedOption = this.options[this.selectedIndex];
                    document.getElementById('job-title-header').textContent = `Applications for: ${selectedOption.text}`;
                }
            });
        }

        // Load job selector dropdown
        async function loadJobSelector() {
            showLoading(true);
            console.log('Loading job selector data');
            
            try {
                const response = await fetch('?action=get_jobs_with_applications');
                const data = await response.json();
                
                if (data.success) {
                    const selector = document.getElementById('job-selector');
                    selector.innerHTML = '<option value="">-- Select a Job --</option>';
                    
                    data.data.forEach(job => {
                        const option = document.createElement('option');
                        option.value = job.job_id;
                        option.textContent = `${job.title} (${job.application_count} applications)`;
                        selector.appendChild(option);
                    });
                    
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

        // Load applications for a specific job
        async function loadApplications(jobId) {
            showLoading(true);
            console.log(`Loading applications for job ID: ${jobId}`);
            
            try {
                const response = await fetch(`?action=get_applications&job_id=${jobId}`);
                const data = await response.json();
                
                if (data.success) {
                    currentApplications = data.data;
                    renderApplicationsTable();
                    updateApplicationsSummary();
                    
                    // Show the applications summary card
                    document.getElementById('applications-summary-card').style.display = 'block';
                    
                    showNotification('Applications loaded successfully', 'success');
                } else {
                    showNotification('Error loading applications: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Error loading applications:', error);
                showNotification('Failed to load applications. Please try again.', 'error');
            } finally {
                showLoading(false);
            }
        }

        // Render applications table
        function renderApplicationsTable() {
            const tbody = document.querySelector('#applications-table tbody');
            tbody.innerHTML = '';
            
            if (currentApplications.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No applications found for this job.</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            currentApplications.forEach(application => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <strong>${escapeHtml(application.full_name)}</strong>
                    </td>
                    <td>
                        <div>${escapeHtml(application.email)}</div>
                        <small class="text-muted">${escapeHtml(application.phone || 'No phone provided')}</small>
                    </td>
                    <td>
                        ${formatDate(application.application_date)}
                    </td>
                    <td>
                        <span class="status-badge ${getStatusClass(application.status)}">
                            ${escapeHtml(application.status)}
                        </span>
                    </td>
                    <td>
                        <button class="btn action-btn btn-edit" onclick="viewApplicationDetails(${application.application_id})" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Update applications summary
        function updateApplicationsSummary() {
            const total = currentApplications.length;
            const newCount = currentApplications.filter(app => app.status === 'Submitted').length;
            const hiredCount = currentApplications.filter(app => app.status === 'Hired').length;
            
            document.getElementById('total-applications').textContent = `${total} Total`;
            document.getElementById('new-applications').textContent = `${newCount} New`;
            document.getElementById('hired-applications').textContent = `${hiredCount} Hired`;
        }

        // View application details
        async function viewApplicationDetails(applicationId) {
            showLoading(true);
            console.log(`Viewing details for application ID: ${applicationId}`);
            
            try {
                const application = currentApplications.find(app => app.application_id == applicationId);
                if (!application) {
                    throw new Error('Application not found');
                }
                
                currentApplicationId = applicationId;
                
                // Populate modal with application data
                document.getElementById('applicantNameModal').textContent = application.full_name;
                document.getElementById('modalFullName').textContent = application.full_name;
                document.getElementById('modalEmail').textContent = application.email;
                document.getElementById('modalPhone').textContent = application.phone || 'Not provided';
                document.getElementById('modalJobTitle').textContent = application.job_title;
                document.getElementById('modalAppliedDate').textContent = formatDate(application.application_date);
                document.getElementById('modalStatus').innerHTML = `<span class="status-badge ${getStatusClass(application.status)}">${application.status}</span>`;
                
                // Set cover letter
                const coverLetterEl = document.getElementById('modalCoverLetter');
                if (application.cover_letter) {
                    coverLetterEl.textContent = application.cover_letter;
                } else {
                    coverLetterEl.innerHTML = '<em>No cover letter provided.</em>';
                }
                
                // Set CV download link
                const downloadBtn = document.getElementById('downloadCvBtn');
                if (application.cv_path) {
                    downloadBtn.href = application.cv_path;
                    downloadBtn.style.display = 'inline-block';
                    
                    // Load PDF preview
                    if (application.cv_path.toLowerCase().endsWith('.pdf')) {
                        loadPdfPreview(application.cv_path);
                    } else {
                        document.getElementById('cvPreviewContainer').innerHTML = `
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Preview not available for this file type. Please download to view.
                            </div>
                        `;
                    }
                } else {
                    downloadBtn.style.display = 'none';
                    document.getElementById('cvPreviewContainer').innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No CV uploaded by applicant.
                        </div>
                    `;
                }
                
                // Set current status in selector
                document.getElementById('statusSelector').value = application.status;
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('applicationDetailsModal'));
                modal.show();
                
            } catch (error) {
                console.error('Error loading application details:', error);
                showNotification('Failed to load application details.', 'error');
            } finally {
                showLoading(false);
            }
        }

        // Load PDF preview
        async function loadPdfPreview(pdfUrl) {
            try {
                const loadingTask = pdfjsLib.getDocument(pdfUrl);
                const pdf = await loadingTask.promise;
                
                // Get the first page
                const page = await pdf.getPage(1);
                const scale = 1.5;
                const viewport = page.getViewport({ scale });
                
                // Prepare canvas
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                
                // Render PDF page into canvas context
                await page.render({
                    canvasContext: context,
                    viewport: viewport
                }).promise;
                
                // Display canvas
                const container = document.getElementById('cvPreviewContainer');
                container.innerHTML = '';
                container.appendChild(canvas);
                
                // Add page info
                const pageInfo = document.createElement('p');
                pageInfo.className = 'text-muted mt-2 text-center';
                pageInfo.textContent = `Page 1 of ${pdf.numPages} - Scroll down in the PDF viewer to see more pages`;
                container.appendChild(pageInfo);
                
            } catch (error) {
                console.error('Error loading PDF preview:', error);
                document.getElementById('cvPreviewContainer').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Failed to load PDF preview. Please download the file to view.
                    </div>
                `;
            }
        }

        // Update application status
        async function updateApplicationStatus() {
            const newStatus = document.getElementById('statusSelector').value;
            if (!newStatus || !currentApplicationId) return;
            
            showLoading(true);
            console.log(`Updating application ${currentApplicationId} to status: ${newStatus}`);
            
            try {
                const response = await fetch(`?action=update_application_status&application_id=${currentApplicationId}&status=${encodeURIComponent(newStatus)}`);
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Application status updated successfully', 'success');
                    
                    // Update the application in our current list
                    const application = currentApplications.find(app => app.application_id == currentApplicationId);
                    if (application) {
                        application.status = newStatus;
                    }
                    
                    // Refresh the table and summary
                    renderApplicationsTable();
                    updateApplicationsSummary();
                    
                    // Update the status in the modal
                    document.getElementById('modalStatus').innerHTML = `<span class="status-badge ${getStatusClass(newStatus)}">${newStatus}</span>`;
                    document.getElementById('statusSelector').value = newStatus;
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Error updating status:', error);
                showNotification('Failed to update application status.', 'error');
            } finally {
                showLoading(false);
            }
        }

        // Get CSS class for status badge
        function getStatusClass(status) {
            switch (status) {
                case 'Submitted': return 'status-submitted';
                case 'Under Review': return 'status-review';
                case 'Shortlisted': return 'status-shortlisted';
                case 'Rejected': return 'status-rejected';
                case 'Hired': return 'status-hired';
                default: return 'status-submitted';
            }
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
        }

        function getIconForType(type) {
            switch (type) {
                case 'success': return 'fa-check-circle';
                case 'error': return 'fa-exclamation-circle';
                case 'warning': return 'fa-exclamation-triangle';
                default: return 'fa-info-circle';
            }
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
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

        // Set up the update status button
        document.getElementById('updateStatusBtn').addEventListener('click', updateApplicationStatus);
    </script>
    
</body>
</html>