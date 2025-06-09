<?php
session_start();
error_reporting(0); // Disable error reporting in production
ini_set('display_errors', 0);

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Hardcoded admin credentials with additional security
$admin_credentials = [
    'hr_admin' => [
        'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password is "admin123"
        'name' => 'HR Spectra',
        'email' => 'hr.spectracompunet@gmail.com',
        'last_login' => '',
        'failed_attempts' => 0
    ]
];

// Database connection with error handling
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'job_portal';

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    
    // Set charset to prevent SQL injection
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    // Log the error securely
    error_log("Database connection error: " . $e->getMessage());
    die("System maintenance in progress. Please try again later.");
}

// Brute force protection
function checkBruteForce($conn, $user_id) {
    $now = time();
    $valid_attempts = $now - (2 * 60 * 60); // 2 hour window
    
    $query = "SELECT time FROM login_attempts WHERE user_id = ? AND time > ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $valid_attempts);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 5) {
        return true;
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid request");
    }
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_type = $_POST['user_type'] ?? '';
    
    // Input validation
    if (empty($username)) {
        $error = "Username is required";
    } elseif (empty($password)) {
        $error = "Password is required";
    } elseif (!in_array($user_type, ['admin', 'job_seeker'])) {
        $error = "Invalid user type";
    } else {
        if ($user_type == 'admin') {
            // Admin login with rate limiting
            if (array_key_exists($username, $admin_credentials)) {
                $admin = &$admin_credentials[$username];
                
                // Check if account is locked
                if ($admin['failed_attempts'] >= 5 && 
                    time() - strtotime($admin['last_login']) < 30 * 60) {
                    $error = "Account temporarily locked. Try again later.";
                } else {
                    if (password_verify($password, $admin['password'])) {
                        // Successful login
                        $_SESSION['user_id'] = $username;
                        $_SESSION['username'] = $username;
                        $_SESSION['user_type'] = 'admin';
                        $_SESSION['name'] = $admin['name'];
                        $_SESSION['email'] = $admin['email'];
                        $_SESSION['last_activity'] = time();
                        
                        // Reset failed attempts
                        $admin['failed_attempts'] = 0;
                        $admin['last_login'] = date('Y-m-d H:i:s');
                        
                        header("Location: admin_dashboard.php");
                        exit();
                    } else {
                        // Failed login
                        $admin['failed_attempts']++;
                        $admin['last_login'] = date('Y-m-d H:i:s');
                        $error = "Invalid credentials";
                    }
                }
            } else {
                $error = "Invalid credentials";
            }
        } else {
            // Job seeker login with prepared statements
            $username = $conn->real_escape_string($username);
            
            $sql = "SELECT seeker_id, username, password, full_name FROM job_seekers 
                    WHERE username = ? AND status = 'active'";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Check brute force
                if (checkBruteForce($conn, $user['seeker_id'])) {
                    $error = "Account temporarily locked due to too many failed attempts";
                } else {
                    if (password_verify($password, $user['password'])) {
                        // Successful login
                        $_SESSION['user_id'] = $user['seeker_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_type'] = 'job_seeker';
                        $_SESSION['name'] = $user['full_name'];
                        $_SESSION['last_activity'] = time();
                        
                        header("Location: job_seeker_dashboard.php");
                        exit();
                    } else {
                        // Log failed attempt
                        $now = time();
                        $sql = "INSERT INTO login_attempts (user_id, time) VALUES (?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ii", $user['seeker_id'], $now);
                        $stmt->execute();
                        
                        $error = "Invalid credentials";
                    }
                }
            } else {
                $error = "Invalid credentials";
            }
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spectra Compunet - Job Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary: #4a6bff;
            --primary-dark: #3a56d4;
            --secondary: #3f37c9;
            --light: #f8f9fa;
            --dark: #2d3748;
            --gray: #718096;
            --light-gray: #e2e8f0;
            --danger: #e53e3e;
            --success: #38a169;
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        
        body {
            background-color: #f7fafc;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            overflow-x: hidden;
            background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiPjxkZWZzPjxwYXR0ZXJuIGlkPSJwYXR0ZXJuIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiIHBhdHRlcm5UcmFuc2Zvcm09InJvdGF0ZSg0NSkiPjxyZWN0IHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgZmlsbD0icmdiYSgyMzgsMjQyLDI1NSwwLjAzKSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0idXJsKCNwYXR0ZXJuKSIvPjwvc3ZnPg==');
        }
        
        .login-container {
            width: 100%;
            max-width: 1100px;
            min-height: 650px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            display: flex;
            position: relative;
            margin: 20px;
        }
        
        .panel {
            width: 50%;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            transition: var(--transition);
            overflow: hidden;
        }
        
        .welcome-panel {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            z-index: 1;
        }
        
        .welcome-panel::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            z-index: -1;
        }
        
        .form-panel {
            background: white;
        }
        
        .logo-container {
            margin-bottom: 40px;
            text-align: center;
        }
        
        .logo {
            width: 180px;
            height: auto;
            margin-bottom: 15px;
        }
        
        .logo-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .logo-subtitle {
            font-size: 14px;
            color: var(--gray);
            font-weight: 500;
        }
        
        .welcome-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 15px;
            color: white;
            opacity: 0;
            transform: translateX(-20px);
            transition: var(--transition);
        }
        
        .welcome-title.active {
            opacity: 1;
            transform: translateX(0);
        }
        
        .welcome-subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 30px;
            font-weight: 400;
            line-height: 1.6;
            opacity: 0;
            transform: translateX(-20px);
            transition: var(--transition);
            transition-delay: 0.1s;
        }
        
        .welcome-subtitle.active {
            opacity: 1;
            transform: translateX(0);
        }
        
        .features-list {
            list-style: none;
            margin-bottom: 40px;
            opacity: 0;
            transform: translateX(-20px);
            transition: var(--transition);
            transition-delay: 0.2s;
        }
        
        .features-list.active {
            opacity: 1;
            transform: translateX(0);
        }
        
        .features-list li {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .features-list li::before {
            content: '✓';
            display: inline-block;
            width: 24px;
            height: 24px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            margin-right: 12px;
            font-size: 12px;
        }
        
        .toggle-btn {
            background: rgba(255,255,255,0.15);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            transition: var(--transition);
            backdrop-filter: blur(5px);
            opacity: 0;
            transform: translateX(-20px);
            transition: var(--transition);
            transition-delay: 0.3s;
        }
        
        .toggle-btn.active {
            opacity: 1;
            transform: translateX(0);
        }
        
        .toggle-btn:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }
        
        .toggle-btn i {
            margin-left: 8px;
            font-size: 16px;
        }
        
        .form-container {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            transition: var(--transition);
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80%;
        }
        
        .user-form {
            opacity: 1;
            visibility: visible;
        }
        
        .admin-form {
            opacity: 0;
            visibility: hidden;
            position: absolute;
            top: 50%;
            left: 150%;
            transform: translate(-50%, -50%);
        }
        
        .form-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
            transition: var(--transition);
        }
        
        .form-subtitle {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 30px;
            transition: var(--transition);
        }
        
        .form-group {
            margin-bottom: 20px;
            transition: var(--transition);
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
            background: white;
        }
        
        .form-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(74, 107, 255, 0.2);
        }
        
        .password-container {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--gray);
        }
        
        .submit-btn {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .submit-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 107, 255, 0.3);
        }
        
        .submit-btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }
        
        .submit-btn:focus:not(:active)::after {
            animation: ripple 0.6s ease-out;
        }
        
        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(20, 20);
                opacity: 0;
            }
        }
        
        .form-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            color: var(--gray);
        }
        
        .form-footer a {
            color: var(--primary);
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .form-footer a:hover {
            text-decoration: underline;
        }
        
        .error-message {
            color: var(--danger);
            font-size: 14px;
            margin-bottom: 20px;
            padding: 12px;
            background: rgba(229, 62, 62, 0.1);
            border-radius: 8px;
            text-align: center;
            animation: fadeInUp 0.5s;
        }
        
        .admin-form.active {
            opacity: 1;
            visibility: visible;
            left: 50%;
        }
        
        .user-form.inactive {
            opacity: 0;
            visibility: hidden;
            left: -50%;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 100;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s;
            backdrop-filter: blur(3px);
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 100%;
            max-width: 400px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            position: relative;
            animation: slideUp 0.4s;
            margin: 20px;
        }
        
        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray);
            transition: var(--transition);
        }
        
        .close-modal:hover {
            color: var(--dark);
            transform: rotate(90deg);
        }
        
        /* Responsive design */
        @media (max-width: 992px) {
            .login-container {
                flex-direction: column;
                max-width: 500px;
                min-height: auto;
            }
            
            .panel {
                width: 100%;
                padding: 40px;
            }
            
            .welcome-panel {
                padding-bottom: 60px;
            }
            
            .form-container {
                position: relative;
                top: auto;
                left: auto;
                transform: none;
                margin: 0 auto;
                padding: 20px 0;
            }
            
            .admin-form {
                position: relative;
                left: auto;
                transform: none;
                margin-top: 30px;
            }
            
            .user-form.inactive {
                display: none;
            }
        }
        
        @media (max-width: 576px) {
            .login-container {
                border-radius: 0;
                min-height: 100vh;
                margin: 0;
            }
            
            .panel {
                padding: 30px 20px;
            }
            
            .logo {
                width: 140px;
            }
            
            .welcome-title {
                font-size: 24px;
            }
            
            .form-title {
                font-size: 20px;
            }
            
            .form-input {
                padding: 12px 14px;
            }
            
            .submit-btn {
                padding: 12px;
            }
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeInUp {
            from { 
                opacity: 0; 
                transform: translateY(20px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        @keyframes slideUp {
            from { 
                transform: translateY(30px); 
                opacity: 0; 
            }
            to { 
                transform: translateY(0); 
                opacity: 1; 
            }
        }
        
        /* Loading spinner */
        .spinner {
            display: none;
            width: 24px;
            height: 24px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <!-- Logo and Heading -->
        <div class="logo-container" style="position: absolute; top: 20px; left: 50%; transform: translateX(-50%); z-index: 10;">
            <img src="spectra_compunet_logo.png" alt="Spectra Compunet Logo" class="logo">
            <h2 class="logo-title">Spectra Compunet</h2>
            <p class="logo-subtitle">Job Portal System</p>
        </div>
        
        <!-- Welcome Panel -->
        <div class="panel welcome-panel" id="welcomePanel">
            <h1 class="welcome-title active">Fast & Easy Job Management</h1>
            <p class="welcome-subtitle active">Welcome back to Spectra Compunet Job Portal. Manage your applications or post new opportunities with our intuitive platform.</p>
            
            <button class="toggle-btn active" id="toggleLogin">
                <span id="toggleText">HR Professional? Login Here</span>
                <i class="fas fa-arrow-right"></i>
            </button>
        </div>
        
        <!-- Form Panel -->
        <div class="panel form-panel">
            <?php if (isset($error)): ?>
                <div class="error-message animate__animated animate__fadeInUp">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            
            <!-- User Login Form -->
            <div class="form-container user-form" id="userForm">
                <h3 class="form-title">Welcome Back!</h3>
                <p class="form-subtitle">Sign in to access your job portal account</p>
                
                <form method="POST" action="" id="userLoginForm">
                    <input type="hidden" name="user_type" value="job_seeker">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input type="text" id="email" name="username" class="form-input" placeholder="emile.smith@gmail.com" required
                               autocomplete="username" autocapitalize="off" spellcheck="false">
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="password-container">
                            <input type="password" id="password" name="password" class="form-input" 
                                   placeholder="Enter your password" required autocomplete="current-password">
                            <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn" id="userSubmitBtn">
                        <span id="userSubmitText">Sign In</span>
                        <div class="spinner" id="userSpinner"></div>
                    </button>
                    
                    <div class="form-footer">
                        <a href="forgot_password.php">Forget My Password</a> • 
                        <a href="register.php">Request An Account</a><br><br>
                        <a href="#" id="needHelp">Need Help?</a>
                    </div>
                </form>
            </div>
            
            <!-- Admin Login Form -->
            <div class="form-container admin-form" id="adminForm">
                <h3 class="form-title">HR Professional Login</h3>
                <p class="form-subtitle">Enter your HR credentials to access the admin portal</p>
                
                <form method="POST" action="" id="adminLoginForm">
                    <input type="hidden" name="user_type" value="admin">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label for="admin-email" class="form-label">HR Email</label>
                        <input type="text" id="admin-email" name="username" class="form-input" 
                               placeholder="hr.spectracompunet@gmail.com" required autocomplete="username">
                    </div>
                    
                    <div class="form-group">
                        <label for="admin-password" class="form-label">HR Password</label>
                        <div class="password-container">
                            <input type="password" id="admin-password" name="password" class="form-input" 
                                   placeholder="Enter HR password" required autocomplete="current-password">
                            <i class="fas fa-eye toggle-password" id="toggleAdminPassword"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn" id="adminSubmitBtn">
                        <span id="adminSubmitText">Login as HR</span>
                        <div class="spinner" id="adminSpinner"></div>
                    </button>
                    
                    <div class="form-footer">
                        <a href="#" id="backToUserLogin">← Back to User Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Help Modal -->
    <div class="modal" id="helpModal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>Need Help?</h3>
            <p>If you're experiencing issues with your account, please contact our support team:</p>
            <p><strong>Email:</strong> helpdesk@spectracompunet.com</p>
            <p><strong>Phone:</strong> 093235 86423</p>
            <p>Our support team is available Monday-Friday, 9AM-5PM.</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function()) {
            // DOM elements
            const toggleLogin = document.getElementById('toggleLogin');
            const toggleText = document.getElementById('toggleText');
            const backToUserLogin = document.getElementById('backToUserLogin');
            const adminForm = document.getElementById('adminForm');
            const userForm = document.getElementById('userForm');
            const welcomePanel = document.getElementById('welcomePanel');
            const helpModal = document.getElementById('helpModal');
            const needHelp = document.getElementById('needHelp');
            const closeModal = document.querySelector('.close-modal');
            const togglePassword = document.getElementById('togglePassword');
            const toggleAdminPassword = document.getElementById('toggleAdminPassword');
            const passwordInput = document.getElementById('password');
            const adminPasswordInput = document.getElementById('admin-password');
            const userLoginForm = document.getElementById('userLoginForm');
            const adminLoginForm = document.getElementById('adminLoginForm');
            const userSubmitBtn = document.getElementById('userSubmitBtn');
            const adminSubmitBtn = document.getElementById('adminSubmitBtn');
            const userSubmitText = document.getElementById('userSubmitText');
            const adminSubmitText = document.getElementById('adminSubmitText');
            const userSpinner = document.getElementById('userSpinner');
            const adminSpinner = document.getElementById('adminSpinner');}
            
            // Toggle between HR and user login
            let isHRLogin = false;
            
            toggleLogin.addEventListener('click', function() {
                isHRLogin = !isHRLogin;
                
                if (isHRLogin) {
                    // Switch to HR login
                    toggleText.textContent = 'Job Seeker? Login Here';
                    welcomePanel.querySelector('.welcome-title').textContent = 'HR Management Portal';
                    welcomePanel.querySelector('.welcome-subtitle').textContent = 'Access your HR dashboard to manage job postings, applications, and candidate evaluations.';
                    
                    // Toggle forms
                    userForm.classList.add('inactive');
                    adminForm.classList.add('active');
                    
                    // Animate welcome panel content
                    animateWelcomePanel();
                } else {
                    // Switch to user login
                    toggleText.textContent = 'HR Professional? Login Here';
                    welcomePanel.querySelector('.welcome-title').textContent = 'Fast & Easy Job Management';
                    welcomePanel.querySelector('.welcome-subtitle').textContent = 'Welcome back to Spectra Compunet Job Portal. Manage your applications or post new opportunities with our intuitive platform.';
                    
                    // Toggle forms
                    adminForm.classList.remove('active');
                    userForm.classList.remove('inactive');
                    
                    // Animate welcome panel content
                    animateWelcomePanel();
                }
            });
            
            // Back to user login
            backToUserLogin.addEventListener('click', function(e) {
                e.preventDefault();
                isHRLogin = false;
                toggleText.textContent = 'HR Professional? Login Here';
                adminForm.classList.remove('active');
                userForm.classList.remove('inactive');
                animateWelcomePanel();
            });
            
            // Animate welcome panel content
            function animateWelcomePanel() {
                const title = welcomePanel.querySelector('.welcome-title');
                const subtitle = welcomePanel.querySelector('.welcome-subtitle');
                const features = welcomePanel.querySelector('.features-list');
                const button = welcomePanel.querySelector('.toggle-btn');
                
                // Reset animation
                title.classList.remove('active');
                subtitle.classList.remove('active');
                features.classList.remove('active');
                button.classList.remove('active');
                
                // Reapply animation after a small delay
                setTimeout(() => {
                    title.classList.add('active');
                    subtitle.classList.add('active');
                    features.classList.add('active');
                    button.classList.add('active');
                }, 10);
            }
            
            // Show help modal
            needHelp.addEventListener('click', function(e) {
                e.preventDefault();
                helpModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            });
            
            // Close modal
            closeModal.addEventListener('click', function() {
                helpModal.style.display = 'none';
                document.body.style.overflow = 'auto';
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target === helpModal) {
                    helpModal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
            
            // Toggle password visibility
            function setupPasswordToggle(button, input) {
                button.addEventListener('click', function() {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    button.classList.toggle('fa-eye');
                    button.classList.toggle('fa-eye-slash');
                });
            }
            
            setupPasswordToggle(togglePassword, passwordInput);
            setupPasswordToggle(toggleAdminPassword, adminPasswordInput);
            
            // Form submission handling
            function handleFormSubmit(form, submitBtn, submitText, spinner) {
                form.addEventListener('submit', function(e) ){
                    // Client-side validation
                    const inputs = form.querySelectorAll('input[required]');}}