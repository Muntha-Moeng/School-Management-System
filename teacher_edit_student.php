<?php
session_start();

// Redirect to login if not authenticated as teacher
if (!isset($_SESSION['teacher_id'])) {
    header("Location: teacher_login.php");
    exit();
}

// Check if student ID is provided
if (!isset($_GET['id'])) {
    header("Location: teacher_manage_students.php");
    exit();
}

$student_id = $_GET['id'];

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'student_registration';

$error = '';
$success = '';

try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get teacher data
    $stmt = $conn->prepare("SELECT * FROM teachers WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['teacher_id']);
    $stmt->execute();
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacher) {
        header("Location: teacher_login.php");
        exit();
    }
    
    // Get student data
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
    $stmt->bindParam(':id', $student_id);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        header("Location: teacher_manage_students.php");
        exit();
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
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
        if (!empty($birth_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
            $errors[] = "Invalid date format (YYYY-MM-DD)";
        }
        
        if (empty($errors)) {
            try {
                // Check if email already exists for another student
                $stmt = $conn->prepare("SELECT id FROM students WHERE email = :email AND id != :id");
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':id', $student_id);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $error = "Email already registered by another student";
                } else {
                    // Update student record
                    $stmt = $conn->prepare("UPDATE students SET 
                                          full_name = :full_name,
                                          email = :email,
                                          phone = :phone,
                                          address = :address,
                                          course = :course,
                                          gender = :gender,
                                          birth_date = :birth_date
                                          WHERE id = :id");
                    
                    $stmt->bindParam(':full_name', $full_name);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':phone', $phone);
                    $stmt->bindParam(':address', $address);
                    $stmt->bindParam(':course', $course);
                    $stmt->bindParam(':gender', $gender);
                    $stmt->bindParam(':birth_date', $birth_date);
                    $stmt->bindParam(':id', $student_id);
                    
                    if ($stmt->execute()) {
                        $success = "Student information updated successfully";
                        // Refresh student data
                        $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
                        $stmt->bindParam(':id', $student_id);
                        $stmt->execute();
                        $student = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                }
            } catch(PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student - Teacher Portal</title>
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
            background: linear-gradient(135deg, #6f42c1 0%, #d63384 100%);
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
        .edit-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
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
                        <h4>Teacher Portal</h4>
                        <p class="text-muted">Welcome, <?php echo htmlspecialchars($teacher['full_name']); ?></p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="teacher_dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="teacher_grades.php">
                                <i class="bi bi-journal-check"></i> Grade Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="teacher_courses.php">
                                <i class="bi bi-book"></i> My Courses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="teacher_manage_students.php">
                                <i class="bi bi-people"></i> Manage Students
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
                    <h1 class="h2">Edit Student</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="teacher_view_student.php?id=<?php echo $student['id']; ?>" class="btn btn-secondary me-2">
                            <i class="bi bi-arrow-left"></i> Back to Student
                        </a>
                    </div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="edit-container">
                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           placeholder="John Doe" required value="<?php echo htmlspecialchars($student['full_name']); ?>">
                                    <label for="full_name">Full Name</label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="email" name="email" 
                                           placeholder="name@example.com" required value="<?php echo htmlspecialchars($student['email']); ?>">
                                    <label for="email">Email</label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           placeholder="Phone" value="<?php echo htmlspecialchars($student['phone']); ?>">
                                    <label for="phone">Phone</label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?php echo ($student['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo ($student['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo ($student['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                    <label for="gender">Gender</label>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="form-floating">
                                    <textarea class="form-control" id="address" name="address" 
                                              placeholder="Address" style="height: 80px"><?php echo htmlspecialchars($student['address']); ?></textarea>
                                    <label for="address">Address</label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <select class="form-select" id="course" name="course" required>
                                        <option value="" hidden>Select Course</option>
                                        <option value="Computer Science" <?php echo ($student['course'] == 'Computer Science') ? 'selected' : ''; ?>>Computer Science</option>
                                        <option value="Engineering" <?php echo ($student['course'] == 'Engineering') ? 'selected' : ''; ?>>Engineering</option>
                                        <option value="Business" <?php echo ($student['course'] == 'Business') ? 'selected' : ''; ?>>Business</option>
                                        <option value="Medicine" <?php echo ($student['course'] == 'Medicine') ? 'selected' : ''; ?>>Medicine</option>
                                        <option value="Arts" <?php echo ($student['course'] == 'Arts') ? 'selected' : ''; ?>>Arts</option>
                                    </select>
                                    <label for="course">Course</label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="date" class="form-control" id="birth_date" name="birth_date" 
                                           placeholder="Birth Date" value="<?php echo htmlspecialchars($student['birth_date']); ?>">
                                    <label for="birth_date">Birth Date</label>
                                </div>
                            </div>
                            
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary w-100 py-3">
                                    <i class="bi bi-save"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>