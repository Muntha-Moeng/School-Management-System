<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['student_id'])) {
    header("Location: index.php");
    exit();
}

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'student_registration';

try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get current student data
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['student_id']);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        header("Location: index.php");
        exit();
    }
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submission
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $course = $_POST['course'];
    $gender = $_POST['gender'];
    $birth_date = $_POST['birth_date'];
    
    // Validate inputs
    $errors = [];
    if (empty($full_name)) $errors[] = "Full name is required";
    if (!empty($birth_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        $errors[] = "Invalid date format (YYYY-MM-DD)";
    }
    
    if (empty($errors)) {
        try {
            // Update student data
            $stmt = $conn->prepare("UPDATE students SET 
                full_name = :full_name,
                phone = :phone,
                address = :address,
                course = :course,
                gender = :gender,
                birth_date = :birth_date
                WHERE id = :id");
                
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':course', $course);
            $stmt->bindParam(':gender', $gender);
            $stmt->bindParam(':birth_date', $birth_date);
            $stmt->bindParam(':id', $_SESSION['student_id']);
            
            if ($stmt->execute()) {
                $message = 'Profile updated successfully!';
                $success = true;
                
                // Refresh student data
                $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
                $stmt->bindParam(':id', $_SESSION['student_id']);
                $stmt->execute();
                $student = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch(PDOException $e) {
            $message = "Error updating profile: " . $e->getMessage();
        }
    } else {
        $message = implode("<br>", $errors);
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Student Portal</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%);
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 5px;
            border-radius: 5px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            padding: 20px;
        }
        .profile-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .profile-header {
            background: linear-gradient(135deg, #0d6efd 0%, #6610f2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4>Student Portal</h4>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="bi bi-person"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="edit-profile.php">
                                <i class="bi bi-pencil"></i> Edit Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="course.php">
                                <i class="bi bi-book"></i> Courses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="schedule.php">
                                <i class="bi bi-calendar"></i> Schedule
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link" href="?logout=1">
                                <i class="bi bi-box-arrow-left"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Edit Profile</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="profile.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Profile
                        </a>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $success ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card profile-card mb-4">
                            <div class="profile-header">
                                <h4>Update Your Information</h4>
                                <p class="mb-0">Make changes to your profile details</p>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="full_name" class="form-label">Full Name *</label>
                                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                                value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" 
                                                value="<?php echo htmlspecialchars($student['email']); ?>" disabled>
                                            <small class="text-muted">Contact admin to change email</small>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                value="<?php echo htmlspecialchars($student['phone']); ?>">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="birth_date" class="form-label">Date of Birth</label>
                                            <input type="date" class="form-control" id="birth_date" name="birth_date" 
                                                value="<?php echo htmlspecialchars($student['birth_date']); ?>">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="gender" class="form-label">Gender</label>
                                            <select class="form-select" id="gender" name="gender">
                                                <option value="">Select Gender</option>
                                                <option value="Male" <?php echo $student['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                                <option value="Female" <?php echo $student['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                                <option value="Other" <?php echo $student['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="course" class="form-label">Course *</label>
                                            <select class="form-select" id="course" name="course" required>
                                                <option value="">Select Course</option>
                                                <option value="Computer Science" <?php echo $student['course'] == 'Computer Science' ? 'selected' : ''; ?>>Computer Science</option>
                                                <option value="Engineering" <?php echo $student['course'] == 'Engineering' ? 'selected' : ''; ?>>Engineering</option>
                                                <option value="Business Administration" <?php echo $student['course'] == 'Business Administration' ? 'selected' : ''; ?>>Business Administration</option>
                                                <option value="Medicine" <?php echo $student['course'] == 'Medicine' ? 'selected' : ''; ?>>Medicine</option>
                                                <option value="Arts" <?php echo $student['course'] == 'Arts' ? 'selected' : ''; ?>>Arts</option>
                                                <option value="Law" <?php echo $student['course'] == 'Law' ? 'selected' : ''; ?>>Law</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-12">
                                            <label for="address" class="form-label">Address</label>
                                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($student['address']); ?></textarea>
                                        </div>
                                        
                                        <div class="col-12 mt-4">
                                            <button type="submit" class="btn btn-primary px-4">
                                                <i class="bi bi-save"></i> Save Changes
                                            </button>
                                            <button type="reset" class="btn btn-outline-secondary ms-2">
                                                <i class="bi bi-eraser"></i> Reset
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card profile-card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="current_password" class="form-label">Current Password *</label>
                                            <input type="password" class="form-control" id="current_password" required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="new_password" class="form-label">New Password *</label>
                                            <input type="password" class="form-control" id="new_password" required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                            <input type="password" class="form-control" id="confirm_password" required>
                                        </div>
                                        
                                        <div class="col-12 mt-2">
                                            <button type="submit" class="btn btn-warning px-4">
                                                <i class="bi bi-key"></i> Update Password
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card profile-card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Profile Update Guidelines</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <strong>Important:</strong> Please ensure all information is accurate before submitting.
                                </div>
                                
                                <h6>Updating Your Profile:</h6>
                                <ul class="small">
                                    <li>Fields marked with * are required</li>
                                    <li>Use your legal name as it appears on official documents</li>
                                    <li>Provide a current phone number where you can be reached</li>
                                    <li>Select the course you are currently enrolled in</li>
                                </ul>
                                
                                <h6 class="mt-4">Changing Password:</h6>
                                <ul class="small">
                                    <li>Your new password must be at least 8 characters long</li>
                                    <li>Include a mix of uppercase, lowercase, numbers and symbols</li>
                                    <li>Don't use easily guessable information like your name or birth date</li>
                                </ul>
                                
                                <div class="alert alert-warning mt-4 small">
                                    <i class="bi bi-exclamation-triangle"></i> <strong>Note:</strong> To change your email address, please contact the administration office.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>