    <?php
    session_start();

    // Check if the user is logged in as a job seeker
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'job_seeker') {
        header("Location: login.php");
        exit();
    }

    // Database connection (adjust as per your setup)
    $conn = new mysqli("127.0.0.1", "root", "", "job_portal");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Generate CSRF token
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrf_token = $_SESSION['csrf_token'];

    // Fetch the job seeker's details (e.g., CV path)
    $seeker_id = $_SESSION['user_id'];
    $sql = "SELECT cv_path FROM applicant_details WHERE seeker_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $seeker_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $has_cv = !empty($user['cv_path']);
    $stmt->close();

    // Fetch all active job postings
    $sql = "SELECT job_id, title, description, requirements, location, job_type, shift_schedule, salary, benefits, job_categories, created_at 
            FROM job_postings 
            WHERE is_active = 1 
            ORDER BY created_at DESC";
    $jobs_result = $conn->query($sql);
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Job Seeker Dashboard - Professional Portal</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --primary: #1e3a8a; /* Navy blue for a professional look */
    --primary-dark: #172554; /* Darker navy for depth */
    --secondary: #6b7280; /* Neutral gray for secondary elements */
    --accent: #3b82f6; /* Subtle blue for accents */
    --success: #15803d; /* Muted green for success */
    --warning: #d97706; /* Subtle amber for warnings */
    --danger: #b91c1c; /* Muted red for errors */
    --dark: #111827; /* Dark gray for text */
    --light: #f9fafb; /* Light gray for backgrounds */
    --gray: #4b5563; /* Medium gray for secondary text */
    --light-gray: #d1d5db; /* Light gray for borders */
    --gradient-primary: linear-gradient(135deg, #1e3a8a 0%, #4b5563 100%); /* Professional gradient */
    --gradient-secondary: linear-gradient(135deg, #6b7280 0%, #4b5563 100%); /* Neutral gradient */
    --gradient-success: linear-gradient(135deg, #15803d 0%, #4ade80 100%); /* Success gradient */
    --shadow-soft: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
    --shadow-medium: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
    --shadow-large: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
}
/* Add this to your CSS */
#fileNameDisplay {
    margin-top: 0.5rem;
    padding: 0.5rem;
    background: rgba(239, 246, 255, 0.5);
    border-radius: 8px;
    border: 1px dashed rgba(59, 130, 246, 0.3);
    transition: all 0.3s ease;
}

#fileNameDisplay:hover {
    background: rgba(219, 234, 254, 0.7);
    border-color: rgba(59, 130, 246, 0.5);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: linear-gradient(135deg, #1e3a8a 0%, #4b5563 100%); /* Updated gradient */
    min-height: 100vh;
    color: var(--dark);
    overflow-x: hidden;
}

/* Animated Background */
.bg-animated {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -1;
    background: linear-gradient(135deg, #1e3a8a 0%, #4b5563 100%); /* Updated gradient */
}

.bg-animated::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.05)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.05)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.03)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.03)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.03)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>'); /* Subtler grain */
    animation: float 20s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(1deg); }
}

/* Navigation */
.navbar {
    background: rgba(255, 255, 255, 0.98); /* Slightly more opaque for professionalism */
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(209, 213, 219, 0.3); /* Light gray border */
    box-shadow: var(--shadow-soft);
    position: sticky;
    top: 0;
    z-index: 1000;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.navbar.scrolled {
    background: rgba(255, 255, 255, 1);
    box-shadow: var(--shadow-medium);
}

.nav-brand {
    font-weight: 800;
    font-size: 1.75rem;
    background: var(--gradient-primary);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    transition: all 0.3s ease;
}

.nav-brand:hover {
    transform: scale(1.05);
}

/* Main Container */
.main-container {
    background: rgba(255, 255, 255, 0.95); /* More opaque for clarity */
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border-radius: 16px; /* Slightly smaller radius for a cleaner look */
    margin: 2rem;
    padding: 2rem;
    box-shadow: var(--shadow-medium);
    border: 1px solid rgba(209, 213, 219, 0.3); /* Light gray border */
    animation: slideUp 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Typography */
.main-title {
    font-size: 2.5rem;
    font-weight: 800;
    text-align: center;
    margin-bottom: 2rem;
    background: linear-gradient(135deg,rgb(56, 49, 49) 0%,rgb(34, 36, 39) 100%); /* Darker gray for better contrast */
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    text-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Stronger shadow for readability */
    position: relative;
    animation: titleGlow 2s ease-in-out infinite alternate;
}

@keyframes titleGlow {
    from { text-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); }
    to { text-shadow: 0 2px 10px rgba(255, 255, 255, 0.2); }
}

.main-title::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 80px; /* Slightly smaller */
    height: 3px;
    background: var(--gradient-success);
    border-radius: 2px;
    animation: lineExpand 1s ease-out 0.5s both;
}

@keyframes lineExpand {
    from { width: 0; }
    to { width: 80px; }
}

/* Job Cards */
.jobs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.job-card {
    background: rgba(255, 255, 255, 0.98); /* More opaque for professionalism */
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border-radius: 16px; /* Cleaner radius */
    padding: 1.5rem; /* Slightly less padding */
    box-shadow: var(--shadow-soft);
    border: 1px solid rgba(209, 213, 219, 0.3); /* Light gray border */
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    opacity: 0;
    animation: cardSlideIn 0.6s cubic-bezier(0.4, 0, 0.2, 1) forwards;
}

.job-card:nth-child(1) { animation-delay: 0.1s; }
.job-card:nth-child(2) { animation-delay: 0.2s; }
.job-card:nth-child(3) { animation-delay: 0.3s; }
.job-card:nth-child(4) { animation-delay: 0.4s; }
.job-card:nth-child(5) { animation-delay: 0.5s; }
.job-card:nth-child(6) { animation-delay: 0.6s; }

@keyframes cardSlideIn {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.job-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: var(--gradient-primary);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.job-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(30, 58, 138, 0.05) 0%, rgba(75, 85, 99, 0.05) 100%); /* Updated gradient */
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.job-card:hover {
    transform: translateY(-8px) scale(1.01); /* Subtler hover effect */
    box-shadow: var(--shadow-medium);
    border-color: rgba(30, 58, 138, 0.3);
}

.job-card:hover::before {
    transform: scaleX(1);
}

.job-card:hover::after {
    opacity: 1;
}

.job-title {
    font-size: 1.25rem; /* Slightly smaller for professionalism */
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 0.75rem;
    transition: all 0.3s ease;
    position: relative;
    z-index: 2;
}

.job-card:hover .job-title {
    color: var(--primary);
    transform: translateX(3px); /* Subtler transform */
}

.job-detail {
    display: flex;
    align-items: center;
    margin-bottom: 0.75rem;
    color: var(--gray);
    font-size: 0.9rem;
    transition: all 0.3s ease;
    position: relative;
    z-index: 2;
}

.job-detail i {
    width: 20px;
    margin-right: 0.75rem;
    color: var(--primary);
    transition: all 0.3s ease;
}

.job-card:hover .job-detail i {
    transform: scale(1.05); /* Subtler scale */
    color: var(--accent); /* Use accent color */
}

.job-card:hover .job-detail {
    transform: translateX(2px); /* Subtler transform */
}

/* Buttons */
.btn-group {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
    position: relative;
    z-index: 2;
}

.btn {
    padding: 0.875rem 1.75rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    border: none;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 120px;
}

.btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.6s ease;
}

.btn:hover::before {
    left: 100%;
}

.btn-primary {
    background: var(--gradient-primary);
    color: white;
    box-shadow: 0 4px 15px rgba(30, 58, 138, 0.3); /* Updated shadow */
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(30, 58, 138, 0.4);
}

.btn-success {
    background: var(--gradient-success);
    color: white;
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
}

.btn-danger {
    background: var(--gradient-secondary);
    color: white;
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
}

/* Modals */
.modal-backdrop {
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    transition: all 0.3s ease;
}

.modal {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 24px;
    box-shadow: var(--shadow-large);
    border: 1px solid rgba(255, 255, 255, 0.3);
    transform: translateY(30px) scale(0.9);
    opacity: 0;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    max-width: 900px;
    width: 95%;
    max-height: 90vh;
    overflow: hidden;
}

.modal.show {
    transform: translateY(0) scale(1);
    opacity: 1;
}

.modal-header {
    padding: 2rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    position: relative;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
}

.modal-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0;
}

.modal-close {
    position: absolute;
    top: 2rem;
    right: 2rem;
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--gray);
    cursor: pointer;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.modal-close:hover {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
    transform: rotate(90deg);
}

.modal-body {
    padding: 2rem;
    max-height: 60vh;
    overflow-y: auto;
}

.modal-body::-webkit-scrollbar {
    width: 6px;
}

.modal-body::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.05);
    border-radius: 3px;
}

.modal-body::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 3px;
}

.modal-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid rgba(0, 0, 0, 0.1);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    background: rgba(248, 250, 252, 0.5);
}

/* Form Elements */
.form-control {
    width: 100%;
    padding: 1rem;
    border: 2px solid var(--light-gray);
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.8);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    background: white;
}

.file-upload {
    position: relative;
    display: inline-block;
    width: 100%;
}

.file-upload input[type="file"] {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.file-upload-label {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    border: 2px dashed var(--light-gray);
    border-radius: 12px;
    background: rgba(248, 250, 252, 0.5);
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
}

.file-upload-label:hover {
    border-color: var(--primary);
    background: rgba(102, 126, 234, 0.05);
}

.file-upload-label i {
    font-size: 2rem;
    color: var(--primary);
    margin-bottom: 0.5rem;
}

/* Loading Spinner */
.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid rgba(102, 126, 234, 0.1);
    border-top: 4px solid var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 2rem auto;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Messages */
.message {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin: 1rem 0;
    font-weight: 500;
    display: flex;
    align-items: center;
    animation: messageSlide 0.4s ease;
}

@keyframes messageSlide {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.message i {
    margin-right: 0.75rem;
    font-size: 1.2rem;
}

.message-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
    border-left: 4px solid var(--success);
}

.message-error {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
    border-left: 4px solid var(--danger);
}

/* Responsive Design */
@media (max-width: 768px) {
    .main-container {
        margin: 1rem;
        padding: 1rem;
        border-radius: 12px; /* Smaller radius for mobile */
    }

    .main-title {
        font-size: 1.75rem; /* Smaller for mobile */
    }

    .jobs-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .job-card {
        padding: 1.25rem;
    }

    .btn-group {
        flex-direction: column;
    }

    .btn {
        width: 100%;
    }

    .modal {
        width: 95%;
        margin: 1rem;
    }

    .modal-header,
    .modal-body,
    .modal-footer {
        padding: 1.5rem;
    }
}

/* No jobs state */
.no-jobs {
    text-align: center;
    padding: 4rem 2rem;
    color: rgba(255, 255, 255, 0.9); /* Slightly more opaque */
}

.no-jobs i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.4; /* Subtler opacity */
}

.no-jobs h3 {
    font-size: 1.25rem; /* Slightly smaller */
    margin-bottom: 0.5rem;
    color: rgba(255, 255, 255, 0.95);
}

/* Pulse animation for loading states */
.pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
/* Add this to your CSS */
#uploadStatus .spinner {
    width: 24px;
    height: 24px;
    border-width: 2px;
    margin: 0 auto 0.5rem;
}

#uploadStatus .message {
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    margin: 0;
}
</style>
    </head>
    <body>
        <div class="bg-animated"></div>
        
        <!-- Navigation -->
        <nav class="navbar">
            <div class="max-w-7xl mx-auto px-6 py-4 flex justify-between items-center">
                <div class="nav-brand">JobPortal</div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700 font-medium">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                    <a href="logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="main-container">
            <h1 class="main-title">Discover Your Next Opportunity</h1>

            <!-- Job Listings -->
            <div class="jobs-grid">
                <?php if ($jobs_result->num_rows > 0): ?>
                    <?php while ($job = $jobs_result->fetch_assoc()): ?>
                        <div class="job-card">
                            <h2 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h2>
                            
                            <div class="job-detail">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($job['location']); ?></span>
                            </div>
                            
                            <div class="job-detail">
                                <i class="fas fa-briefcase"></i>
                                <span><?php echo htmlspecialchars($job['job_type']); ?></span>
                            </div>
                            
                            <div class="job-detail">
                                <i class="fas fa-dollar-sign"></i>
                                <span><?php echo htmlspecialchars($job['salary'] ?: 'Competitive'); ?></span>
                            </div>
                            
                            <div class="job-detail">
                                <i class="fas fa-tag"></i>
                                <span><?php echo htmlspecialchars($job['job_categories']); ?></span>
                            </div>
                            
                            <div class="job-detail">
                                <i class="fas fa-clock"></i>
                                <span><?php echo date('M j, Y', strtotime($job['created_at'])); ?></span>
                            </div>

                            <div class="btn-group">
                                <button onclick="openJobModal(<?php echo $job['job_id']; ?>, '<?php echo addslashes(htmlspecialchars($job['title'])); ?>', '<?php echo addslashes(htmlspecialchars($job['description'])); ?>', '<?php echo addslashes(htmlspecialchars($job['requirements'])); ?>', '<?php echo addslashes(htmlspecialchars($job['location'])); ?>', '<?php echo addslashes(htmlspecialchars($job['job_type'])); ?>', '<?php echo addslashes(htmlspecialchars($job['shift_schedule'])); ?>', '<?php echo addslashes(htmlspecialchars($job['salary'])); ?>', '<?php echo addslashes(htmlspecialchars($job['benefits'])); ?>', '<?php echo addslashes(htmlspecialchars($job['job_categories'])); ?>')"
                                        class="btn btn-primary">
                                    <i class="fas fa-eye mr-2"></i>View Details
                                </button>
                                <button onclick="openApplyModal(<?php echo $job['job_id']; ?>, '<?php echo addslashes(htmlspecialchars($job['title'])); ?>')"
                                        class="btn btn-success">
                                    <i class="fas fa-paper-plane mr-2"></i>Apply Now
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-jobs">
                        <i class="fas fa-search"></i>
                        <h3>No Jobs Available</h3>
                        <p>Check back later for new opportunities!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Job Details Modal -->
        <div id="jobModal" class="fixed inset-0 modal-backdrop flex items-center justify-center hidden z-50">
            <div class="modal">
                <div class="modal-header">
                    <h2 id="jobModalTitle" class="modal-title"></h2>
                    <button onclick="closeJobModal()" class="modal-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="job-detail">
                            <i class="fas fa-map-marker-alt"></i>
                            <span id="jobModalLocation"></span>
                        </div>
                        <div class="job-detail">
                            <i class="fas fa-briefcase"></i>
                            <span id="jobModalJobType"></span>
                        </div>
                        <div class="job-detail">
                            <i class="fas fa-clock"></i>
                            <span id="jobModalShiftSchedule"></span>
                        </div>
                        <div class="job-detail">
                            <i class="fas fa-dollar-sign"></i>
                            <span id="jobModalSalary"></span>
                        </div>
                        <div class="job-detail">
                            <i class="fas fa-tag"></i>
                            <span id="jobModalCategory"></span>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-lg font-semibold mb-2 text-gray-800">Job Description</h3>
                            <p id="jobModalDescription" class="text-gray-600 leading-relaxed"></p>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold mb-2 text-gray-800">Requirements</h3>
                            <p id="jobModalRequirements" class="text-gray-600 leading-relaxed"></p>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold mb-2 text-gray-800">Benefits</h3>
                            <p id="jobModalBenefits" class="text-gray-600 leading-relaxed"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Apply Job Modal -->
        <div id="applyModal" class="fixed inset-0 modal-backdrop flex items-center justify-center hidden z-50">
            <div class="modal">
                <div class="modal-header">
                    <h2 id="applyModalTitle" class="modal-title"></h2>
                    <button onclick="closeApplyModal()" class="modal-close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="applyModalContent">
                        <?php if (!$has_cv): ?>
                            <div class="message message-error">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>You need to upload a CV before applying. Please upload your CV below.</span>
                            </div>
                            <form id="cvUploadForm" class="space-y-4">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    <div class="file-upload">
        <input type="file" name="cv" accept=".pdf" required>
        <div class="file-upload-label">
            <div>
                <i class="fas fa-cloud-upload-alt"></i>
                <div class="font-semibold">Upload your CV</div>
                <div class="text-sm text-gray-500">PDF format, max 2MB</div>
            </div>
        </div>
    </div>
    <!-- Add this file name display -->
    <div id="fileNameDisplay" class="text-sm text-gray-600 text-center hidden">
        <i class="fas fa-file-pdf mr-1"></i>
        <span id="selectedFileName"></span>
    </div>
    <!-- Status container -->
    <div id="uploadStatus" class="text-center hidden">
        <div class="spinner inline-block !w-6 !h-6 !border-2 mb-2"></div>
        <p class="text-sm text-gray-600">Uploading your CV...</p>
    </div>
    <button type="submit" class="btn btn-primary w-full">
        <i class="fas fa-upload mr-2"></i>Upload CV
    </button>
</form>
                        <?php else: ?>
                            <div class="message message-success">
                                <i class="fas fa-check-circle"></i>
                                <span>Your CV is ready! You can apply for this position.</span>
                            </div>
                            <!-- Inside the applyModal div, replace the applyForm section with this: -->
<form id="applyForm" class="space-y-4">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    <input type="hidden" name="job_id" id="applyJobId">
    
    <div>
        <label for="coverLetter" class="block text-sm font-medium text-gray-700 mb-1">Cover Letter</label>
        <textarea id="coverLetter" name="cover_letter" class="form-control" rows="6" 
                  placeholder="Write a customized cover letter for this position..." required></textarea>
    </div>
    
    <button type="submit" class="btn btn-success w-full">
        <i class="fas fa-paper-plane mr-2"></i>Confirm Application
    </button>
</form>
                        <?php endif; ?>
                    </div>
                    <div id="applySpinner" class="spinner hidden"></div>
                    <div id="applyMessage" class="hidden"></div>
                </div>
            </div>
        </div>

        <script>
            let currentJobId = null;

            // Navbar scroll effect
            window.addEventListener('scroll', () => {
                const navbar = document.querySelector('.navbar');
                if (window.scrollY > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            });

            // Job Details Modal
            function openJobModal(jobId, title, description, requirements, location, jobType, shiftSchedule, salary, benefits, category) {
                console.log('Opening job modal for job ID:', jobId);
                
                document.getElementById('jobModalTitle').textContent = title;
                document.getElementById('jobModalDescription').textContent = description;
                document.getElementById('jobModalRequirements').textContent = requirements;
                document.getElementById('jobModalLocation').textContent = location;
                document.getElementById('jobModalJobType').textContent = jobType;
                document.getElementById('jobModalShiftSchedule').textContent = shiftSchedule || 'Not specified';
                document.getElementById('jobModalSalary').textContent = salary || 'Competitive';
                document.getElementById('jobModalBenefits').textContent = benefits || 'Standard benefits package';
                document.getElementById('jobModalCategory').textContent = category;
                
                const modal = document.getElementById('jobModal');
                modal.classList.remove('hidden');
                setTimeout(() => modal.querySelector('.modal').classList.add('show'), 10);
            }

            function closeJobModal() {
                const modal = document.getElementById('jobModal');
                modal.querySelector('.modal').classList.remove('show');
                setTimeout(() => modal.classList.add('hidden'), 300);
            }

            // Apply Job Modal
            function openApplyModal(jobId, title) {
                console.log('Opening apply modal for job ID:', jobId, 'Title:', title);
                
                currentJobId = jobId;
                document.getElementById('applyModalTitle').textContent = `Apply for ${title}`;
                
                // Set job ID in the form if it exists
                const jobIdInput = document.getElementById('applyJobId');
                if (jobIdInput) {
                    jobIdInput.value = jobId;
                }
                
                // Reset message states
                const message = document.getElementById('applyMessage');
                const spinner = document.getElementById('applySpinner');
                
                message.classList.add('hidden');
                spinner.classList.add('hidden');
                
                const modal = document.getElementById('applyModal');
                modal.classList.remove('hidden');
                setTimeout(() => modal.querySelector('.modal').classList.add('show'), 10);
            }

            function closeApplyModal() {
                const modal = document.getElementById('applyModal');
                modal.querySelector('.modal').classList.remove('show');
                setTimeout(() => modal.classList.add('hidden'), 300);
                currentJobId = null;
            }

            // Handle CV Upload via AJAX
          // Handle CV Upload via AJAX
const cvUploadForm = document.getElementById('cvUploadForm');
if (cvUploadForm) {
    // Add this to the file upload form handling
const fileInput = document.querySelector('input[type="file"]');
const fileNameDisplay = document.getElementById('fileNameDisplay');
const selectedFileName = document.getElementById('selectedFileName');

if (fileInput) {
    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            // Show the selected file name
            selectedFileName.textContent = file.name;
            fileNameDisplay.classList.remove('hidden');
            
            const fileSizeMB = file.size / (1024 * 1024);
            if (fileSizeMB > 2) {
                alert('File size must be less than 2MB');
                this.value = '';
                fileNameDisplay.classList.add('hidden');
            }
            
            const fileType = file.type;
            if (fileType !== 'application/pdf') {
                alert('Only PDF files are allowed');
                this.value = '';
                fileNameDisplay.classList.add('hidden');
            }
        } else {
            fileNameDisplay.classList.add('hidden');
        }
    });
}
    cvUploadForm.addEventListener('submit', function(e) {
        e.preventDefault();
        console.log('CV Upload form submitted');
        
        const formData = new FormData(this);
        const spinner = document.getElementById('applySpinner');
        const message = document.getElementById('applyMessage');
        const uploadStatus = document.getElementById('uploadStatus');
        const submitBtn = this.querySelector('button[type="submit"]');

        // Show uploading status
        uploadStatus.classList.remove('hidden');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Uploading...';

        fetch('upload_cv.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('CV Upload response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('CV Upload response data:', data);
            
            // Hide uploading status
            uploadStatus.classList.add('hidden');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Upload CV';

            if (data.success) {
                // Show success message temporarily
                uploadStatus.innerHTML = `
                    <div class="message message-success !py-2 !px-3 !text-sm">
                        <i class="fas fa-check-circle"></i>
                        <span>CV uploaded successfully!</span>
                    </div>
                `;
                uploadStatus.classList.remove('hidden');

                // Update modal content to show the apply form after a delay
                setTimeout(() => {
                    document.getElementById('applyModalContent').innerHTML = `
                        <div class="message message-success">
                            <i class="fas fa-check-circle"></i>
                            <span>Your CV has been uploaded successfully! You can now apply for this position.</span>
                        </div>
                        <form id="applyForm" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="job_id" id="applyJobId" value="${currentJobId}">
                            <div>
                                <label for="coverLetter" class="block text-sm font-medium text-gray-700 mb-1">Cover Letter</label>
                                <textarea id="coverLetter" name="cover_letter" class="form-control" rows="6" 
                                          placeholder="Write a customized cover letter for this position..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-success w-full">
                                <i class="fas fa-paper-plane mr-2"></i>Confirm Application
                            </button>
                        </form>
                    `;
                    attachApplyFormListener();
                }, 1500);
            } else {
                message.classList.remove('hidden');
                message.className = 'message message-error';
                message.innerHTML = `<i class="fas fa-exclamation-triangle"></i><span>${data.error || 'Failed to upload CV.'}</span>`;
            }
        })
        .catch(error => {
            console.error('CV Upload error:', error);
            // Hide uploading status
            uploadStatus.classList.add('hidden');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Upload CV';
            
            message.classList.remove('hidden');
            message.className = 'message message-error';
            message.innerHTML = '<i class="fas fa-exclamation-triangle"></i><span>An error occurred while uploading your CV.</span>';
        });
    });
}

            // Handle Job Application via AJAX
            // Handle Job Application via AJAX
// Handle Job Application via AJAX
function attachApplyFormListener() {
    const applyForm = document.getElementById('applyForm');
    if (applyForm) {
        applyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Apply form submitted');
            
            // Create FormData and append all fields
            const formData = new FormData();
            formData.append('csrf_token', document.querySelector('#applyForm input[name="csrf_token"]').value);
            formData.append('job_id', document.getElementById('applyJobId').value);
            formData.append('cover_letter', document.getElementById('coverLetter').value);

            const spinner = document.getElementById('applySpinner');
            const message = document.getElementById('applyMessage');

            spinner.classList.remove('hidden');
            message.classList.add('hidden');

            fetch('apply_job.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Application response:', data);
                spinner.classList.add('hidden');
                
                if (data.success) {
                    message.className = 'message message-success';
                    message.innerHTML = `<i class="fas fa-check-circle"></i><span>${data.message || 'Application submitted successfully!'}</span>`;
                    message.classList.remove('hidden');
                    
                    // Clear form and close modal after 2 seconds
                    setTimeout(() => {
                        document.getElementById('applyForm').reset();
                        closeApplyModal();
                    }, 2000);
                } else {
                    message.className = 'message message-error';
                    message.innerHTML = `<i class="fas fa-exclamation-triangle"></i><span>${data.error || 'Failed to submit application'}</span>`;
                    message.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                spinner.classList.add('hidden');
                message.className = 'message message-error';
                message.innerHTML = '<i class="fas fa-exclamation-triangle"></i><span>An error occurred while submitting the application</span>';
                message.classList.remove('hidden');
            });
        });
    }
}
            // Attach listener to apply form if it exists on page load
            attachApplyFormListener();

            // Close modals when clicking outside
            window.onclick = function(event) {
                if (event.target.classList.contains('modal-backdrop')) {
                    closeJobModal();
                    closeApplyModal();
                }
            };

            // Keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeJobModal();
                    closeApplyModal();
                }
            });
            // Add this to the file upload form handling
const fileInput = document.querySelector('input[type="file"]');
if (fileInput) {
    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const fileSizeMB = file.size / (1024 * 1024);
            if (fileSizeMB > 2) {
                alert('File size must be less than 2MB');
                this.value = '';
            }
            
            const fileType = file.type;
            if (fileType !== 'application/pdf') {
                alert('Only PDF files are allowed');
                this.value = '';
            }
        }
    });
}

            // Add loading states to buttons
            document.querySelectorAll('.btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (this.type === 'submit') {
                        this.classList.add('pulse');
                        setTimeout(() => {
                            this.classList.remove('pulse');
                        }, 2000);
                    }
                });
            });

            // Debug: Log when page loads
            console.log('Page loaded, current user has CV:', <?php echo $has_cv ? 'true' : 'false'; ?>);
        </script>
    </body>
    </html>

    <?php $conn->close(); ?>
