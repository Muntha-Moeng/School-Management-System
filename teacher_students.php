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
    
    // Get courses the student is enrolled in (that this teacher teaches)
    $stmt = $conn->prepare("SELECT c.id, c.course_code, c.course_name, g.grade
                          FROM courses c
                          JOIN student_courses sc ON c.id = sc.course_id
                          JOIN teacher_courses tc ON c.id = tc.course_id
                          LEFT JOIN grades g ON g.course_id = c.id AND g.student_id = :student_id
                          WHERE sc.student_id = :student_id AND tc.teacher_id = :teacher_id
                          ORDER BY c.course_name");
    $stmt->bindParam(':student_id', $student_id);
    $stmt->bindParam(':teacher_id', $_SESSION['teacher_id']);
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate GPA
    $gradeValues = [
        'A' => 4.0, 'A-' => 3.7,
        'B+' => 3.3, 'B' => 3.0, 'B-' => 2.7,
        'C+' => 2.3, 'C' => 2.0, 'C-' => 1.7,
        'D+' => 1.3, 'D' => 1.0, 'F' => 0.0
    ];
    
    $totalCredits = 0;
    $totalGradePoints = 0;
    $gpa = 0;
    
    foreach ($courses as $course) {
        if (!empty($course['grade']) && isset($gradeValues[$course['grade']])) {
            $credits = 3; // Default credits if not available
            if (isset($course['credits']) && is_numeric($course['credits'])) {
                $credits = $course['credits'];
            }
            $totalCredits += $credits;
            $totalGradePoints += $gradeValues[$course['grade']] * $credits;
        }
    }
    
    if ($totalCredits > 0) {
        $gpa = $totalGradePoints / $totalCredits;
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
    <title>Student Details - Teacher Portal</title>
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
            padding: 30px;
            margin-bottom: 30px;
        }
        .profile-header {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .badge-program {
            background-color: #6f42c1;
        }
        .grade-A { background-color: #d4edda; }
        .grade-B { background-color: #cce5ff; }
        .grade-C { background-color: #fff3cd; }
        .grade-D { background-color: #f8d7da; }
        .grade-F { background-color: #dc3545; color: white; }
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
                    <h1 class="h2">Student Details</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="teacher_manage_students.php" class="btn btn-secondary me-2">
                            <i class="bi bi-arrow-left"></i> Back to Students
                        </a>
                    </div>
                </div>
                
                <div class="student-card">
                    <div class="profile-header">
                        <div class="row">
                            <div class="col-md-8">
                                <h3><?php echo htmlspecialchars($student['full_name']); ?></h3>
                                <p class="text-muted">Student ID: <?php echo $student['id']; ?></p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <span class="badge rounded-pill bg-primary badge-program">
                                    <?php echo htmlspecialchars($student['course']); ?>
                                </span>
                                <?php if ($gpa > 0): ?>
                                    <div class="mt-2">
                                        <span class="badge bg-info text-dark">
                                            GPA: <?php echo number_format($gpa, 2); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Personal Information</h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <tr>
                                        <th>Email:</th>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Phone:</th>
                                        <td><?php echo !empty($student['phone']) ? htmlspecialchars($student['phone']) : 'Not provided'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Gender:</th>
                                        <td><?php echo !empty($student['gender']) ? htmlspecialchars($student['gender']) : 'Not specified'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Birth Date:</th>
                                        <td>
                                            <?php if (!empty($student['birth_date'])): 
                                                $birthDate = new DateTime($student['birth_date']);
                                                echo $birthDate->format('F j, Y') . ' (Age: ' . $birthDate->diff(new DateTime())->y . ')';
                                            else: 
                                                echo 'Not provided';
                                            endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Address:</th>
                                        <td><?php echo !empty($student['address']) ? nl2br(htmlspecialchars($student['address'])) : 'Not provided'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Registered:</th>
                                        <td>
                                            <?php 
                                                $regDate = new DateTime($student['registration_date']);
                                                echo $regDate->format('F j, Y');
                                            ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5>Academic Information</h5>
                            
                            <?php if (empty($courses)): ?>
                                <div class="alert alert-info">
                                    This student is not enrolled in any of your courses.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Course</th>
                                                <th>Grade</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($courses as $course): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                                    </td>
                                                    <td class="<?php if (!empty($course['grade'])) echo 'grade-' . substr($course['grade'], 0, 1); ?>">
                                                        <?php echo !empty($course['grade']) ? htmlspecialchars($course['grade']) : 'Not graded'; ?>
                                                    </td>
                                                    <td>
                                                        <a href="teacher_students.php?course_id=<?php echo $course['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-journal-text"></i> Course
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
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