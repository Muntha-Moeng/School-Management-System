<?php
session_start();

// Redirect to login if not authenticated as teacher
if (!isset($_SESSION['teacher_id'])) {
    header("Location: teacher_login.php");
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
    
    // Get teacher data
    $stmt = $conn->prepare("SELECT * FROM teachers WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['teacher_id']);
    $stmt->execute();
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacher) {
        header("Location: teacher_login.php");
        exit();
    }
    
    // Handle search
    $search = '';
    $students = [];
    
    if (isset($_GET['search'])) {
        $search = trim($_GET['search']);
        $stmt = $conn->prepare("SELECT * FROM students 
                               WHERE full_name LIKE :search 
                               OR email LIKE :search
                               OR course LIKE :search
                               ORDER BY full_name");
        $searchParam = "%$search%";
        $stmt->bindParam(':search', $searchParam);
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Get all students by default
        $stmt = $conn->prepare("SELECT * FROM students ORDER BY full_name");
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Handle student deletion
    if (isset($_GET['delete'])) {
        $student_id = $_GET['delete'];
        
        // First, delete from student_courses to maintain referential integrity
        $stmt = $conn->prepare("DELETE FROM student_courses WHERE student_id = :student_id");
        $stmt->bindParam(':student_id', $student_id);
        $stmt->execute();
        
        // Then delete the student
        $stmt = $conn->prepare("DELETE FROM students WHERE id = :id");
        $stmt->bindParam(':id', $student_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Student deleted successfully";
            header("Location: teacher_manage_students.php");
            exit();
        }
    }
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: teacher_login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - Teacher Portal</title>
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
        .management-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        .student-table {
            width: 100%;
        }
        .student-table th {
            background-color: #f1f1f1;
        }
        .badge-course {
            background-color: #6f42c1;
        }
        .badge-male {
            background-color: #0d6efd;
        }
        .badge-female {
            background-color: #d63384;
        }
        .badge-other {
            background-color: #6c757d;
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
                    <h1 class="h2">Manage Students</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="teacher_add_student.php" class="btn btn-success">
                            <i class="bi bi-plus-circle"></i> Add New Student
                        </a>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="management-container">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <form method="get" class="row g-3">
                                <div class="col-8">
                                    <input type="text" name="search" class="form-control" placeholder="Search students..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-4">
                                    <button type="submit" class="btn btn-primary w-100">Search</button>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <p class="mb-0"><strong>Total Students:</strong> <?php echo count($students); ?></p>
                        </div>
                    </div>
                    
                    <?php if (empty($students)): ?>
                        <div class="alert alert-info">
                            No students found. <?php if (!empty($search)) echo "Try a different search term."; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover student-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Course</th>
                                        <th>Gender</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><?php echo $student['id']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($student['full_name']); ?>
                                                <?php if (!empty($student['birth_date'])): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php 
                                                            $birthDate = new DateTime($student['birth_date']);
                                                            $today = new DateTime();
                                                            $age = $today->diff($birthDate)->y;
                                                            echo "Age: " . $age;
                                                        ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                                            <td>
                                                <span class="badge rounded-pill bg-primary badge-course">
                                                    <?php echo htmlspecialchars($student['course']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($student['gender'] == 'Male'): ?>
                                                    <span class="badge rounded-pill bg-primary badge-male">
                                                        <i class="bi bi-gender-male"></i> Male
                                                    </span>
                                                <?php elseif ($student['gender'] == 'Female'): ?>
                                                    <span class="badge rounded-pill bg-pink badge-female">
                                                        <i class="bi bi-gender-female"></i> Female
                                                    </span>
                                                <?php elseif ($student['gender'] == 'Other'): ?>
                                                    <span class="badge rounded-pill bg-secondary badge-other">
                                                        <i class="bi bi-gender-ambiguous"></i> Other
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge rounded-pill bg-light text-dark">
                                                        Not specified
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $regDate = new DateTime($student['registration_date']);
                                                    echo $regDate->format('M j, Y');
                                                ?>
                                            </td>
                                            <td>
                                                <a href="teacher_view_student.php?id=<?php echo $student['id']; ?>" 
                                                   class="btn btn-sm btn-info" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="teacher_edit_student.php?id=<?php echo $student['id']; ?>" 
                                                   class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="?delete=<?php echo $student['id']; ?>" 
                                                   class="btn btn-sm btn-danger" title="Delete"
                                                   onclick="return confirm('Are you sure you want to delete this student? This action cannot be undone.')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>