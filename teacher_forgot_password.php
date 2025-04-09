<?php
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['teacher_id'])) {
    header("Location: teacher_dashboard.php");
    exit();
}

$message = '';
$error = '';

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'student_registration';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $email = $_POST['email'];
        
        // Check if teacher exists
        $stmt = $conn->prepare("SELECT * FROM teachers WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($teacher) {
            // Generate reset token (in a real app, you'd send this via email)
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $stmt = $conn->prepare("UPDATE teachers SET reset_token = :token, reset_expires = :expires WHERE id = :id");
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':expires', $expires);
            $stmt->bindParam(':id', $teacher['id']);
            $stmt->execute();
            
            // In a real app, you would send an email here with a link to reset_password.php?token=$token
            $message = "Password reset instructions have been sent to your email.";
        } else {
            $error = "No account found with that email address.";
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Teacher Portal</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 30px;
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h2 {
            color: #6f42c1;
        }
        .form-floating {
            margin-bottom: 15px;
        }
        .btn-submit {
            background: linear-gradient(135deg, #6f42c1 0%, #d63384 100%);
            border: none;
            width: 100%;
            padding: 10px;
            font-weight: 600;
        }
        .btn-submit:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2><i class="bi bi-shield-lock"></i> Forgot Password</h2>
            <p class="text-muted">Enter your email to reset your password</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="form-floating">
                    <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                    <label for="email"><i class="bi bi-envelope"></i> Email address</label>
                </div>
                
                <div class="d-grid gap-2 mt-4">
                    <button class="btn btn-submit text-white" type="submit">
                        <i class="bi bi-send"></i> Send Reset Link
                    </button>
                </div>
                
                <div class="text-center mt-3">
                    <a href="teacher_login.php" class="text-decoration-none">Back to Login</a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>