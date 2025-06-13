<?php
session_start();
if (!isset($_SESSION['admin_failed_attempts'])) {
    $_SESSION['admin_failed_attempts'] = 0;
    $_SESSION['admin_last_login_attempt'] = 0;
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Hardcoded admin credentials
// Replace your admin credentials with this:
$admin_credentials = [
    'hr_admin' => [
        'password' => password_hash('admin123', PASSWORD_BCRYPT),
        'name' => 'HR Spectra',
        'email' => 'hr.spectracompunet@gmail.com'
    ]
];

// Database connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'job_portal';

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("System maintenance in progress. Please try again later.");
}

// Brute force protection
function checkBruteForce($conn, $user_id) {
    $now = time();
    $valid_attempts = $now - (2 * 60 * 60); // 2 hour window
    
    $query = "SELECT time FROM login_attempts WHERE user_id = ? AND time > ?";
    if (!($stmt = $conn->prepare($query))) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    if (!$stmt->bind_param("ii", $user_id, $valid_attempts)) {
        error_log("Binding parameters failed: " . $stmt->error);
        return false;
    }
    
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return false;
    }
    
    $stmt->store_result();
    
    if ($stmt->num_rows > 5) {
        return true;
    }
    return false;
}

$error = '';
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
            // Admin login
            if (array_key_exists($username, $admin_credentials)) {
                $admin = $admin_credentials[$username];
                
                // Check if account is locked
                if ($_SESSION['admin_failed_attempts'] >= 5 && 
                    (time() - $_SESSION['admin_last_login_attempt']) < 30 * 60) {
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
                        $_SESSION['admin_failed_attempts'] = 0; // Reset counter
                        
                        header("Location: admin_dashboard.php");
                        exit();
                    } else {
                        // Failed login
                        $_SESSION['admin_failed_attempts']++;
                        $_SESSION['admin_last_login_attempt'] = time();
                        $error = "Invalid credentials";
                    }
                }
            } else {
                $error = "Invalid credentials";
            }
        } else {
            // Job seeker login
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #5a67d8; /* Vibrant indigo */
            --primary-dark: #434190; /* Darker indigo */
            --secondary:rgb(56, 27, 41); /* Vivid pink */
            --secondary-dark:rgb(44, 19, 23); /* Darker pink */
            --accent: #f6ad55; /* Warm orange */
            --success: #48bb78; /* Fresh green */
            --danger:rgb(41, 24, 24); /* Soft red */
            --light: #f7fafc; /* Light background */
            --dark: #2d3748; /* Dark text */
            --gray: #718096; /* Muted gray */
            --light-gray: #e2e8f0; /* Light gray */
            --gradient-primary: linear-gradient(135deg, #5a67d8 0%, #434190 100%);
            --gradient-secondary: linear-gradient(135deg,rgb(179, 138, 84) 0%,rgb(105, 83, 35) 100%);
            --gradient-accent: linear-gradient(135deg,rgb(168, 136, 96) 0%, #ed8936 100%);
            --box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            --box-shadow-hover: 0 15px 30px rgba(0, 0, 0, 0.15);
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e2e8f0 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            overflow-x: hidden;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 30%, rgba(90, 103, 216, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 70%, rgba(237, 100, 166, 0.1) 0%, transparent 50%);
            z-index: -1;
        }

        .header {
            position: fixed;
            top: 20px;
            left: 0;
            width: 100%;
            text-align: center;
            z-index: 10;
            animation: fadeInDown 0.8s ease-out;
        }

        .logo-container {
            margin-bottom: 10px;
        }

        .logo {
            width: 120px;
            height: auto;
            margin-bottom: 5px;
            transition: transform 0.3s ease;
            animation: pulse 2s infinite;
        }

        .logo:hover {
            transform: scale(1.1);
        }

        .logo-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0;
            animation: fadeIn 1s ease-out 0.2s forwards;
            opacity: 0;
        }

        .logo-subtitle {
            font-size: 12px;
            color: var(--gray);
            font-weight: 500;
            animation: fadeIn 1s ease-out 0.4s forwards;
            opacity: 0;
        }

        .login-container {
            width: 100%;
            max-width: 1100px;
            min-height: 600px;
            background: white;
            border-radius: 16px;
            box-shadow: var(--box-shadow);
            overflow: hidden;
            display: flex;
            position: relative;
            margin-top: 100px;
            animation: zoomIn 0.8s ease-out;
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
            background: var(--gradient-primary);
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
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            z-index: -1;
            animation: glow 5s infinite ease-in-out;
        }

        .form-panel {
            background: white;
        }

        .welcome-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 15px;
            color: white;
            animation: fadeInUp 0.6s ease-out;
        }

        .welcome-subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 30px;
            font-weight: 400;
            line-height: 1.6;
            animation: fadeInUp 0.6s ease-out 0.2s forwards;
            opacity: 0;
        }

        .features-list {
            list-style: none;
            margin-bottom: 40px;
            animation: fadeInUp 0.6s ease-out 0.4s forwards;
            opacity: 0;
        }

        .features-list li {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            transition: transform 0.3s ease, color 0.3s ease;
        }

        .features-list li:hover {
            transform: translateX(10px);
            color: var(--accent);
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
            transition: background 0.3s ease;
        }

        .features-list li:hover::before {
            background: var(--accent);
        }

        .toggle-btn {
            background: var(--gradient-secondary);
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
            animation: fadeInUp 0.6s ease-out 0.6s forwards;
            opacity: 0;
            position: relative;
            overflow: hidden;
        }

        .toggle-btn::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }

        .toggle-btn:hover::after {
            left: 100%;
        }

        .toggle-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--box-shadow-hover);
        }

        .toggle-btn i {
            margin-left: 8px;
            font-size: 16px;
            transition: transform 0.3s ease;
        }

        .toggle-btn:hover i {
            transform: translateX(5px);
        }

        .form-container {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            transition: var(--transition);
            position: relative;
            min-height: 400px;
        }

        .user-form, 
        .admin-form {
            width: 100%;
            transition: var(--transition);
            position: absolute;
            top: 0;
            left: 0;
            opacity: 0;
            visibility: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 20px 0;
        }

        .user-form {
            opacity: 1;
            visibility: visible;
            position: relative;
        }

        .admin-form.active {
            opacity: 1;
            visibility: visible;
            position: relative;
        }

        .form-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
            animation: fadeInUp 0.6s ease-out;
        }

        .form-subtitle {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 30px;
            animation: fadeInUp 0.6s ease-out 0.2s forwards;
            opacity: 0;
        }

        .form-group {
            margin-bottom: 20px;
            animation: slideInUp 0.6s ease forwards;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--dark);
            transition: color 0.3s ease;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
            background: white;
        }

        .form-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(90, 103, 216, 0.2);
            transform: translateY(-2px);
        }

        .form-input:hover {
            border-color: var(--secondary);
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
            transition: color 0.3s ease, transform 0.3s ease;
        }

        .toggle-password:hover {
            color: var(--primary);
            transform: translateY(-50%) scale(1.2);
        }

        .submit-btn {
            width: 100%;
            padding: 14px;
            background: var(--gradient-primary);
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
            animation: fadeInUp 0.6s ease-out 0.4s forwards;
            opacity: 0;
        }

        .submit-btn:hover {
            background: var(--gradient-secondary);
            transform: translateY(-3px);
            box-shadow: var(--box-shadow-hover);
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

        .submit-btn:disabled {
            background: var(--light-gray);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .form-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            color: var(--gray);
            animation: fadeInUp 0.6s ease-out 0.6s forwards;
            opacity: 0;
        }

        .form-footer a {
            color: var(--primary);
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
        }

        .form-footer a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        .error-message {
            color: var(--danger);
            font-size: 14px;
            margin-bottom: 20px;
            padding: 12px;
            background: rgba(245, 101, 101, 0.1);
            border-radius: 8px;
            text-align: center;
            animation: shake 0.5s ease-in-out;
        }

        .admin-form.active {
            opacity: 1;
            visibility: visible;
        }

        .user-form.inactive {
            opacity: 0;
            visibility: hidden;
            position: absolute;
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
            box-shadow: var(--box-shadow);
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

        /* Loading spinner */
        .spinner {
            display: inline-block;
            vertical-align: middle;
            margin-right: 8px;
            width: 24px;
            height: 24px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
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

        @keyframes zoomIn {
            from {
                transform: scale(0.9);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
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

        @keyframes glow {
            0% {
                opacity: 0.5;
                transform: rotate(0deg);
            }
            50% {
                opacity: 1;
                transform: rotate(180deg);
            }
            100% {
                opacity: 0.5;
                transform: rotate(360deg);
            }
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

        @keyframes shake {
            0%, 100% {
                transform: translateX(0);
            }
            10%, 30%, 50%, 70%, 90% {
                transform: translateX(-5px);
            }
            20%, 40%, 60%, 80% {
                transform: translateX(5px);
            }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive design */
        @media (max-width: 992px) {
            .login-container {
                flex-direction: column;
                max-width: 500px;
                min-height: auto;
                margin-top: 120px;
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
                margin: 0 auto;
                padding: 20px 0;
            }

            .admin-form {
                display: none;
            }

            .user-form, 
            .admin-form {
                position: relative;
                min-height: auto;
                padding: 0;
            }

            .user-form.inactive {
                display: none;
            }

            .admin-form.active {
                display: flex;
            }
        }

        @media (max-width: 576px) {
            .header {
                top: 10px;
            }

            .logo {
                width: 100px;
            }

            .logo-title {
                font-size: 18px;
            }

            .login-container {
                border-radius: 8px;
                min-height: calc(100vh - 80px);
                margin: 80px 10px 10px;
            }

            .panel {
                padding: 30px 20px;
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
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="header">
        <div class="logo-container">
            <img src="logo.png" alt="Company Logo" class="logo">
            <h1 class="logo-title">Spectra Compunet</h1>
            <p class="logo-subtitle">Job Portal System</p>
        </div>
    </div>

    <div class="login-container">
        <div class="panel welcome-panel">
            <h2 class="welcome-title">Welcome to Spectra Compunet</h2>
            <p class="welcome-subtitle">Join our growing team of professionals and find your dream job today.</p>
            
           
            <button class="toggle-btn" id="toggleForm">
                Admin Login
                <i class="fas fa-arrow-right"></i>
            </button>
        </div>
        
        <div class="panel form-panel">
            <div class="form-container">
                <?php if (!empty($error)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form class="user-form" id="userForm" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="user_type" value="job_seeker">
                    
                    <h2 class="form-title">Job Seeker Login</h2>
                    <p class="form-subtitle">Sign in to access your job portal account</p>
                    
                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" id="username" name="username" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="password-container">
                            <input type="password" id="password" name="password" class="form-input" required>
                            <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn">Login</button>
                    
                    <div class="form-footer">
                        Don't have an account? <a href="register.php">Register here</a>
                    </div>
                </form>
                
                <form class="admin-form" id="adminForm" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="user_type" value="admin">
                    
                    <h2 class="form-title">Admin Login</h2>
                    <p class="form-subtitle">HR personnel access only</p>
                    
                    <div class="form-group">
                        <label for="admin_username" class="form-label">Username</label>
                        <input type="text" id="admin_username" name="username" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_password" class="form-label">Password</label>
                        <div class="password-container">
                            <input type="password" id="admin_password" name="password" class="form-input" required>
                            <i class="fas fa-eye toggle-password" id="toggleAdminPassword"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn">Login</button>
                    
                    <div class="form-footer">
                        <a href="#" id="backToUserLogin">Back to user login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
     // Update the toggle functionality
document.getElementById('toggleForm').addEventListener('click', function() {
    document.getElementById('userForm').classList.add('inactive');
    document.getElementById('adminForm').classList.add('active');
    // Force reflow to enable transition
    document.getElementById('adminForm').offsetHeight;
});

document.getElementById('backToUserLogin').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('adminForm').classList.remove('active');
    document.getElementById('userForm').classList.remove('inactive');
    // Force reflow to enable transition
    document.getElementById('userForm').offsetHeight;
});
        
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
        
        document.getElementById('toggleAdminPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('admin_password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
        // Add this to your script section
document.getElementById('adminForm').addEventListener('submit', function(e) {
    // Show loading state
    const btn = this.querySelector('.submit-btn');
    btn.innerHTML = '<span class="spinner"></span> Authenticating...';
    btn.disabled = true;
    
    // You can add additional validation here if needed
});

document.getElementById('userForm').addEventListener('submit', function(e) {
    // Show loading state
    const btn = this.querySelector('.submit-btn');
    btn.innerHTML = '<span class="spinner"></span> Authenticating...';
    btn.disabled = true;
});
    </script>
</body>
</html>