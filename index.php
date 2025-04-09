<?php
session_start();

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'student_registration';

try {
    // Create database connection using PDO
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create tables if they don't exist
    $conn->exec("CREATE TABLE IF NOT EXISTS students (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        address TEXT,
        course VARCHAR(50),
        gender ENUM('Male', 'Female', 'Other'),
        birth_date DATE,
        registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle login
$login_error = '';
if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    try {
        $stmt = $conn->prepare("SELECT * FROM students WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $student['password'])) {
                $_SESSION['student_id'] = $student['id'];
                $_SESSION['student_name'] = $student['full_name'];
                $_SESSION['student_email'] = $student['email'];
                header("Location: dashboard.php");
                exit();
            } else {
                $login_error = "Invalid email or password";
            }
        } else {
            $login_error = "Invalid email or password";
        }
    } catch(PDOException $e) {
        $login_error = "Error: " . $e->getMessage();
    }
}

// Handle registration
$register_error = '';
$register_success = '';
if (isset($_POST['register'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $course = $_POST['course'];
    $gender = $_POST['gender'];
    $birth_date = $_POST['birth_date'];
    
    // Validate inputs
    $errors = [];
    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    if (!empty($birth_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        $errors[] = "Invalid date format (YYYY-MM-DD)";
    }
    
    if (empty($errors)) {
        try {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM students WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $register_error = "Email already registered";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert into database
                $stmt = $conn->prepare("INSERT INTO students 
                    (full_name, email, password, phone, address, course, gender, birth_date) 
                    VALUES (:full_name, :email, :password, :phone, :address, :course, :gender, :birth_date)");
                    
                $stmt->bindParam(':full_name', $full_name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':course', $course);
                $stmt->bindParam(':gender', $gender);
                $stmt->bindParam(':birth_date', $birth_date);
                
                if ($stmt->execute()) {
                    $register_success = "Registration successful! You can now login.";
                }
            }
        } catch(PDOException $e) {
            $register_error = "Error: " . $e->getMessage();
        }
    } else {
        $register_error = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal - Login/Register</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .auth-container {
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
        }
        .auth-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .auth-header {
            padding: 20px;
            text-align: center;
        }
        .auth-body {
            padding: 30px;
        }
        .form-control:focus {
            box-shadow: none;
            border-color: #0d6efd;
        }
        .btn-auth {
            padding: 10px;
            font-weight: 600;
        }
        .nav-tabs .nav-link {
            border: none;
            color: #495057;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            color: #0d6efd;
            background: transparent;
            border-bottom: 3px solid #0d6efd;
        }
        .form-floating label {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card bg-white">
            <div class="row g-0">
                <!-- Left Side - Welcome Message -->
                <div class="col-lg-6 d-none d-lg-block">
                    <div class="h-100 d-flex flex-column justify-content-center p-5 text-white" style="background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%);">
                        <h2 class="mb-4">Welcome to Student Portal</h2>
                        <p class="mb-4">Manage your academic journey with our comprehensive student portal. Access your information anytime, anywhere.</p>
                        <div class="features">
                            <div class="d-flex mb-3">
                                <i class="bi bi-check-circle-fill me-3 fs-5"></i>
                                <span>Easy course registration</span>
                            </div>
                            <div class="d-flex mb-3">
                                <i class="bi bi-check-circle-fill me-3 fs-5"></i>
                                <span>Update personal information</span>
                            </div>
                            <div class="d-flex">
                                <i class="bi bi-check-circle-fill me-3 fs-5"></i>
                                <span>Secure access to your data</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Side - Auth Forms -->
                <div class="col-lg-6">
                    <div class="auth-body">
                        <ul class="nav nav-tabs mb-4" id="authTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab">Login</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab">Register</button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="authTabsContent">
                            <!-- Login Tab -->
                            <div class="tab-pane fade show active" id="login" role="tabpanel">
                                <h3 class="mb-4">Student Login</h3>
                                <?php if ($login_error): ?>
                                    <div class="alert alert-danger"><?php echo $login_error; ?></div>
                                <?php endif; ?>
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="login" value="1">
                                    
                                    <div class="form-floating mb-3">
                                        <input type="email" class="form-control" id="login-email" name="email" placeholder="name@example.com" required>
                                        <label for="login-email">Email address</label>
                                    </div>
                                    
                                    <div class="form-floating mb-3">
                                        <input type="password" class="form-control" id="login-password" name="password" placeholder="Password" required>
                                        <label for="login-password">Password</label>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="remember-me">
                                            <label class="form-check-label" for="remember-me">Remember me</label>
                                        </div>
                                        <a href="#forgot-password" class="text-decoration-none">Forgot password?</a>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary btn-auth w-100 mb-3">
                                        <i class="bi bi-box-arrow-in-right me-2"></i> Login
                                    </button>
                                    
                                    <div class="text-center">
                                        <p class="mb-0">Don't have an account? <a href="#register" class="text-decoration-none" onclick="switchToRegister()">Register here</a></p>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Register Tab -->
                            <div class="tab-pane fade" id="register" role="tabpanel">
                                <h3 class="mb-4">Student Registration</h3>
                                <?php if ($register_error): ?>
                                    <div class="alert alert-danger"><?php echo $register_error; ?></div>
                                <?php endif; ?>
                                <?php if ($register_success): ?>
                                    <div class="alert alert-success"><?php echo $register_success; ?></div>
                                <?php endif; ?>
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="register" value="1">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" id="full_name" name="full_name" placeholder="John Doe" required>
                                                <label for="full_name">Full Name</label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                                                <label for="email">Email</label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required minlength="8">
                                                <label for="password">Password</label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                                                <label for="confirm_password">Confirm Password</label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone">
                                                <label for="phone">Phone</label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <select class="form-select" id="gender" name="gender">
                                                    <option value="">Select Gender</option>
                                                    <option value="Male">Male</option>
                                                    <option value="Female">Female</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                                <label for="gender">Gender</label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <div class="form-floating">
                                                <textarea class="form-control" id="address" name="address" placeholder="Address" style="height: 80px"></textarea>
                                                <label for="address">Address</label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <select class="form-select" id="course" name="course" required>
                                                    <option value="" hidden>Select Course</option>
                                                    <option value="Computer Science">Computer Science</option>
                                                    <option value="Engineering">Engineering</option>
                                                    <option value="Business">Business</option>
                                                    <option value="Medicine">Medicine</option>
                                                    <option value="Arts">Arts</option>
                                                </select>
                                                <label for="course">Course</label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="date" class="form-control" id="birth_date" name="birth_date" placeholder="Birth Date">
                                                <label for="birth_date">Birth Date</label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12 mt-4">
                                            <button type="submit" class="btn btn-primary btn-auth w-100">
                                                <i class="bi bi-person-plus me-2"></i> Register
                                            </button>
                                        </div>
                                        
                                        <div class="col-12 text-center">
                                            <p class="mb-0">Already have an account? <a href="#login" class="text-decoration-none" onclick="switchToLogin()">Login here</a></p>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function switchToRegister() {
            const registerTab = new bootstrap.Tab(document.getElementById('register-tab'));
            registerTab.show();
        }
        
        function switchToLogin() {
            const loginTab = new bootstrap.Tab(document.getElementById('login-tab'));
            loginTab.show();
        }
        
        // Simple password match validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity("Passwords don't match");
            } else {
                this.setCustomValidity("");
            }
        });
    </script>
</body>
</html>