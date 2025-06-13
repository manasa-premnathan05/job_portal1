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

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Initialize variables
$errors = [];
$full_name = $email = $username = $phone = $address = '';

// Form processing
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid request");
    }

    // Get form data
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Validate inputs
    if (empty($full_name)) {
        $errors['full_name'] = "Full name is required";
    }

    if (empty($email)) {
        $errors['email'] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    }

    if (empty($username)) {
        $errors['username'] = "Username is required";
    }

    if (empty($password)) {
        $errors['password'] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors['password'] = "Password must be at least 8 characters";
    }

    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match";
    }

    // Check if username/email exists only if no other errors
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT username FROM job_seekers WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors['general'] = "Username or email already exists";
        }
        $stmt->close();
    }

    // If no errors, proceed with registration
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        $stmt = $conn->prepare("INSERT INTO job_seekers (full_name, email, username, password, phone, address, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("ssssss", $full_name, $email, $username, $hashed_password, $phone, $address);
        
        if ($stmt->execute()) {
            $_SESSION['registration_success'] = true;
            header("Location:login.php");
            exit();
        } else {
            $errors['general'] = "Registration failed. Please try again.";
        }
        $stmt->close();
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
    <title>Register - Spectra Compunet Job Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
       :root {
            --primary: #5a67d8; /* Vibrant indigo */
            --primary-dark: #434190; /* Darker indigo */
            --secondary: rgb(56, 27, 41); /* Vivid pink */
            --secondary-dark: rgb(44, 19, 23); /* Darker pink */
            --accent: #f6ad55; /* Warm orange */
            --success: #48bb78; /* Fresh green */
            --danger: rgb(41, 24, 24); /* Soft red */
            --light: #f7fafc; /* Light background */
            --dark: #2d3748; /* Dark text */
            --gray: #718096; /* Muted gray */
            --light-gray: #e2e8f0; /* Light gray */
            --gradient-primary: linear-gradient(135deg, #5a67d8 0%, #434190 100%);
            --gradient-secondary: linear-gradient(135deg, rgb(179, 138, 84) 0%, rgb(105, 83, 35) 100%);
            --gradient-accent: linear-gradient(135deg, rgb(168, 136, 96) 0%, #ed8936 100%);
            --box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            --box-shadow-hover: 0 15px 30px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease-in-out;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow-x: hidden;
            position: relative;
            animation: fadeIn 1.2s ease-out;
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
            opacity: 0;
            animation: fadeInOverlay 2s ease-out forwards;
        }

        .header {
            position: fixed;
            top: 20px;
            left: 0;
            width: 100%;
            text-align: center;
            z-index: 10;
            opacity: 0;
            animation: slideInFromTop 0.8s ease-out 0.2s forwards;
        }

        .logo-container {
            margin-bottom: 10px;
        }

        .logo {
            width: 120px;
            height: auto;
            margin-bottom: 5px;
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .logo-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0;
            opacity: 0;
            animation: fadeIn 0.8s ease-out 0.4s forwards;
        }

        .logo-subtitle {
            font-size: 12px;
            color: var(--gray);
            font-weight: 500;
            opacity: 0;
            animation: fadeIn 0.8s ease-out 0.6s forwards;
        }

        .register-container {
            width: 100%;
            max-width: 900px;
            background: white;
            border-radius: 16px;
            box-shadow: var(--box-shadow);
            overflow: hidden;
            display: flex;
            margin: 100px 20px 20px;
            opacity: 0;
            transform: scale(0.98);
            animation: containerReveal 0.8s ease-out 0.6s forwards;
        }

        .welcome-panel {
            width: 40%;
            padding: 50px;
            background: var(--gradient-primary);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .welcome-panel::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            z-index: 1;
            animation: subtleGlow 6s ease-in-out infinite;
        }

        .welcome-content {
            position: relative;
            z-index: 2;
        }

        .welcome-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 15px;
            color: white;
            opacity: 0;
            animation: slideInFromLeft 0.6s ease-out 0.8s forwards;
        }

        .welcome-subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 30px;
            font-weight: 400;
            line-height: 1.6;
            opacity: 0;
            animation: slideInFromLeft 0.6s ease-out 0.9s forwards;
        }

        .features-list {
            list-style: none;
            margin-bottom: 40px;
            opacity: 0;
            animation: slideInFromLeft 0.6s ease-out 1s forwards;
        }

        .features-list li {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            opacity: 0;
            transform: translateX(-20px);
            transition: transform 0.3s ease, opacity 0.3s ease;
        }

        .features-list li:nth-child(1) { animation: listItemReveal 0.6s ease-out 1.1s forwards; }
        .features-list li:nth-child(2) { animation: listItemReveal 0.6s ease-out 1.2s forwards; }
        .features-list li:nth-child(3) { animation: listItemReveal 0.6s ease-out 1.3s forwards; }

        .features-list li:hover {
            transform: translateX(5px);
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

        .login-link {
            color: white;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: var(--transition);
            opacity: 0;
            animation: slideInFromLeft 0.6s ease-out 1.4s forwards;
            position: relative;
        }

        .login-link::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--accent);
            transition: width 0.3s ease;
        }

        .login-link:hover::after {
            width: 100%;
        }

        .login-link:hover {
            color: var(--accent);
        }

        .login-link i {
            margin-left: 8px;
            transition: transform 0.3s ease;
        }

        .login-link:hover i {
            transform: translateX(5px);
        }

        .form-panel {
            width: 60%;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
            opacity: 0;
            animation: slideInFromRight 0.6s ease-out 0.8s forwards;
        }

        .form-subtitle {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 30px;
            opacity: 0;
            animation: slideInFromRight 0.6s ease-out 0.9s forwards;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
            opacity: 0;
            transform: translateY(10px);
            animation: formGroupReveal 0.6s ease-out forwards;
        }

        .form-group:nth-child(1) { animation-delay: 1s; }
        .form-group:nth-child(2) { animation-delay: 1.1s; }
        .form-group:nth-child(3) { animation-delay: 1.2s; }
        .form-group:nth-child(4) { animation-delay: 1.3s; }
        .form-group:nth-child(5) { animation-delay: 1.4s; }
        .form-group:nth-child(6) { animation-delay: 1.5s; }
        .form-group:nth-child(7) { animation-delay: 1.6s; }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--dark);
            transition: color 0.3s ease;
        }

        .form-label.required::after {
            content: '*';
            color: var(--danger);
            margin-left: 4px;
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
            box-shadow: 0 0 0 3px rgba(90, 103, 216, 0.1);
        }

        .form-input:hover {
            border-color: var(--primary-dark);
        }

        .form-input.error {
            border-color: var(--danger);
            animation: errorShake 0.5s ease-in-out;
        }

        .error-message {
            color: var(--danger);
            font-size: 13px;
            margin-top: 6px;
            display: block;
            opacity: 0;
            animation: fadeInUp 0.4s ease-out forwards;
        }

        .error-message i {
            margin-right: 5px;
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
            transition: color 0.3s ease;
        }

        .toggle-password:hover {
            color: var(--primary);
        }

        .password-strength {
            height: 4px;
            background: var(--light-gray);
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
            position: relative;
        }

        .password-strength::after {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 0;
            background: var(--danger);
            transition: width 0.4s ease, background 0.4s ease;
        }

        .password-strength[data-strength="1"]::after {
            width: 25%;
            background: var(--danger);
        }

        .password-strength[data-strength="2"]::after {
            width: 50%;
            background: var(--accent);
        }

        .password-strength[data-strength="3"]::after {
            width: 75%;
            background: #f59e0b;
        }

        .password-strength[data-strength="4"]::after {
            width: 100%;
            background: var(--success);
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
            opacity: 0;
            animation: slideInFromRight 0.6s ease-out 1.7s forwards;
        }

        .submit-btn:hover {
            background: var(--gradient-secondary);
            transform: translateY(-2px);
            box-shadow: var(--box-shadow-hover);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn:disabled {
            background: var(--light-gray);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin: 0 10px 0 0;
            vertical-align: middle;
        }

        .form-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            color: var(--gray);
            opacity: 0;
            animation: slideInFromRight 0.6s ease-out 1.8s forwards;
        }

        .form-footer a {
            color: var(--primary);
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            position: relative;
        }

        .form-footer a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--secondary);
            transition: width 0.3s ease;
        }

        .form-footer a:hover::after {
            width: 100%;
        }

        .form-footer a:hover {
            color: var(--secondary);
        }

        .general-error {
            color: var(--danger);
            font-size: 14px;
            margin-bottom: 20px;
            padding: 12px;
            background: rgba(245, 101, 101, 0.1);
            border-radius: 8px;
            text-align: center;
            opacity: 0;
            animation: errorReveal 0.6s ease-out forwards;
        }

        .general-error i {
            margin-right: 5px;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeInOverlay {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideInFromTop {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInFromLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInFromRight {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes containerReveal {
            from {
                opacity: 0;
                transform: scale(0.98);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes formGroupReveal {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes listItemReveal {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes subtleGlow {
            0% {
                opacity: 0.4;
                transform: rotate(0deg);
            }
            50% {
                opacity: 0.7;
                transform: rotate(180deg);
            }
            100% {
                opacity: 0.4;
                transform: rotate(360deg);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes errorReveal {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes errorShake {
            0%, 100% {
                transform: translateX(0);
            }
            20%, 60% {
                transform: translateX(-4px);
            }
            40%, 80% {
                transform: translateX(4px);
            }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .header {
                top: 20px;
            }

            .register-container {
                flex-direction: column;
                margin-top: 80px;
            }

            .welcome-panel, .form-panel {
                width: 100%;
                padding: 40px;
            }

            .welcome-panel {
                padding-bottom: 40px;
            }
        }

        @media (max-width: 576px) {
            .header {
                top: 15px;
            }

            .logo {
                width: 100px;
            }

            .logo-title {
                font-size: 18px;
            }

            .register-container {
                border-radius: 8px;
                margin: 70px 10px 10px;
                min-height: calc(100vh - 70px);
            }

            .welcome-panel, .form-panel {
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
</head>
<body>
    
    </div>
    
    <div class="register-container">
        <!-- Welcome Panel -->
        <div class="welcome-panel">
            <div class="welcome-content">
                <h1 class="welcome-title">Register now to access our job listings and start your career journey.</h1>
                <p class="welcome-subtitle"></p>
                
                
                <a href="login.php" class="login-link">
                    Already have an account? Sign in
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
        
        <!-- Form Panel -->
        <div class="form-panel">
            <h2 class="form-title">Create Your Account</h2>
            <p class="form-subtitle">Fill in your details to get started</p>
            
            <?php if (isset($errors['general'])): ?>
                <div class="general-error animate__animated animate__fadeInUp">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registrationForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label for="full_name" class="form-label required">Full Name</label>
                    <input type="text" id="full_name" name="full_name" class="form-input <?php echo isset($errors['full_name']) ? 'error' : ''; ?>" 
                           placeholder="Enter your name" required value="<?php echo htmlspecialchars($full_name ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if (isset($errors['full_name'])): ?>
                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errors['full_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label required">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input <?php echo isset($errors['email']) ? 'error' : ''; ?>" 
                           placeholder="Enter your email" required value="<?php echo htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if (isset($errors['email'])): ?>
                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="username" class="form-label required">Username</label>
                    <input type="text" id="username" name="username" class="form-input <?php echo isset($errors['username']) ? 'error' : ''; ?>" 
                            required value="<?php echo htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if (isset($errors['username'])): ?>
                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-input" 
                           placeholder="+1 (555) 123-4567" value="<?php echo htmlspecialchars($phone ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="address" class="form-label">Address</label>
                    <input type="text" id="address" name="address" class="form-input" 
                            value="<?php echo htmlspecialchars($address ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label required">Password</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" class="form-input <?php echo isset($errors['password']) ? 'error' : ''; ?>" 
                               placeholder="At least 8 characters" required>
                        <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                    </div>
                    <div class="password-strength" id="passwordStrength" data-strength="0"></div>
                    <?php if (isset($errors['password'])): ?>
                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label required">Confirm Password</label>
                    <div class="password-container">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input <?php echo isset($errors['confirm_password']) ? 'error' : ''; ?>" 
                               placeholder="Re-enter your password" required>
                        <i class="fas fa-eye toggle-password" id="toggleConfirmPassword"></i>
                    </div>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <span class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errors['confirm_password'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="submit-btn" id="submitBtn">
                    <span id="submitText">Create Account</span>
                    <div class="spinner" id="spinner"></div>
                </button>
                
                <div class="form-footer">
                    By registering, you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password toggle functionality
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
            function setupPasswordToggle(button, input) {
                button.addEventListener('click', function() {
                    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                    input.setAttribute('type', type);
                    button.classList.toggle('fa-eye');
                    button.classList.toggle('fa-eye-slash');
                });
            }
            
            setupPasswordToggle(togglePassword, passwordInput);
            setupPasswordToggle(toggleConfirmPassword, confirmPasswordInput);
            
            // Password strength indicator
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                // Length check
                if (password.length >= 8) strength++;
                
                // Contains lowercase
                if (/[a-z]/.test(password)) strength++;
                
                // Contains uppercase
                if (/[A-Z]/.test(password)) strength++;
                
                // Contains number or special char
                if (/[0-9!@#$%^&*]/.test(password)) strength++;
                
                document.getElementById('passwordStrength').setAttribute('data-strength', strength);
            });
            
            // Form submission handling
            const form = document.getElementById('registrationForm');
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            const spinner = document.getElementById('spinner');
            
            form.addEventListener('submit', function(e) {
                // Client-side validation
                const requiredInputs = form.querySelectorAll('input[required]');
                let isValid = true;
                
                requiredInputs.forEach(input => {
                    if (!input.value.trim()) {
                        input.classList.add('error');
                        isValid = false;
                    }
                });
                
                // Check password match
                if (passwordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.classList.add('error');
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    return;
                }
                
                // Show loading state
                submitText.style.display = 'none';
                spinner.style.display = 'block';
                submitBtn.disabled = true;
            });
            
            // Input validation styling
            function setupInputValidation(input) {
                input.addEventListener('input', function() {
                    if (this.value.trim()) {
                        this.classList.remove('error');
                    }
                });
            }
            
            document.querySelectorAll('.form-input').forEach(input => {
                setupInputValidation(input);
            });
        });
    </script>
</body>
</html>