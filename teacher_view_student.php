<?php
session_start();

// Redirect to login if not authenticated
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
    
    // Get courses taught by this teacher
    $stmt = $conn->prepare("SELECT c.id, c.course_code, c.course_name 
                           FROM courses c
                           JOIN teacher_courses tc ON c.id = tc.course_id
                           WHERE tc.teacher_id = :teacher_id");
    $stmt->bindParam(':teacher_id', $_SESSION['teacher_id']);
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get students for selected course (if any)
    $students = [];
    $selected_course = null;
    if (isset($_GET['course_id']) && !empty($_GET['course_id'])) {
        // Get course details
        $stmt = $conn->prepare("SELECT * FROM courses WHERE id = :course_id");
        $stmt->bindParam(':course_id', $_GET['course_id']);
        $stmt->execute();
        $selected_course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get students enrolled in this course
        $stmt = $conn->prepare("SELECT s.id, s.full_name, s.email, s.phone, sc.enrollment_date
                               FROM students s
                               JOIN student_courses sc ON s.id = sc.student_id
                               WHERE sc.course_id = :course_id
                               ORDER BY s.full_name");
        $stmt->bindParam(':course_id', $_GET['course_id']);
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>View Students - Teacher Portal</title>
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
        .student-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .course-selector {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 30px;
        }
        .student-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background-color: #6f42c1;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            margin-right: 20px;
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
                            <a class="nav-link active" href="teacher_view_students.php">
                                <i class="bi bi-people"></i> View Students
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="teacher_courses.php">
                                <i class="bi bi-book"></i> My Courses
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
                    <h1 class="h2">View Students</h1>
                </div>
                
                <div class="course-selector">
                    <h4>Select Course</h4>
                    <form method="get" class="row g-3">
                        <div class="col-md-8">
                            <select name="course_id" class="form-select" required>
                                <option value="" hidden>-- Select Course --</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                        <?php if (isset($_GET['course_id']) && $_GET['course_id'] == $course['id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary">View Students</button>
                        </div>
                    </form>
                </div>
                
                <?php if ($selected_course): ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Students in <?php echo htmlspecialchars($selected_course['course_code'] . ' - ' . $selected_course['course_name']); ?></h4>
                        <span class="badge bg-primary"><?php echo count($students); ?> students</span>
                    </div>
                    
                    <?php if (empty($students)): ?>
                        <div class="alert alert-info">
                            No students enrolled in this course yet.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($students as $student): ?>
                                <div class="col-md-6">
                                    <div class="student-card d-flex">
                                        <div class="student-avatar">
                                            <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h5><?php echo htmlspecialchars($student['full_name']); ?></h5>
                                            <p class="text-muted mb-1">
                                                <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($student['email']); ?>
                                            </p>
                                            <p class="text-muted mb-1">
                                                <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($student['phone']); ?>
                                            </p>
                                            <p class="text-muted mb-0">
                                                <i class="bi bi-calendar"></i> Enrolled on <?php echo date('M j, Y', strtotime($student['enrollment_date'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>