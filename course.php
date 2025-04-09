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
    
    // Create courses table if it doesn't exist
    $conn->exec("CREATE TABLE IF NOT EXISTS courses (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        course_code VARCHAR(20) NOT NULL,
        course_name VARCHAR(100) NOT NULL,
        instructor VARCHAR(100),
        schedule VARCHAR(100),
        credits INT(2),
        description TEXT
    )");
    
    // Create student_courses table for enrollment
    $conn->exec("CREATE TABLE IF NOT EXISTS student_courses (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        student_id INT(6) UNSIGNED NOT NULL,
        course_id INT(6) UNSIGNED NOT NULL,
        enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id),
        FOREIGN KEY (course_id) REFERENCES courses(id),
        UNIQUE KEY unique_enrollment (student_id, course_id)
    )");
    
    // Get student data
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['student_id']);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get all available courses
    $availableCourses = $conn->query("SELECT * FROM courses ORDER BY course_name")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get student's enrolled courses
    $enrolledCourses = [];
    $stmt = $conn->prepare("SELECT c.* FROM courses c 
                          JOIN student_courses sc ON c.id = sc.course_id
                          WHERE sc.student_id = :student_id");
    $stmt->bindParam(':student_id', $_SESSION['student_id']);
    $stmt->execute();
    $enrolledCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle course enrollment
if (isset($_POST['enroll'])) {
    $course_id = $_POST['course_id'];
    
    try {
        $stmt = $conn->prepare("INSERT INTO student_courses (student_id, course_id) VALUES (:student_id, :course_id)");
        $stmt->bindParam(':student_id', $_SESSION['student_id']);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();
        
        // Refresh enrolled courses
        $stmt = $conn->prepare("SELECT c.* FROM courses c 
                              JOIN student_courses sc ON c.id = sc.course_id
                              WHERE sc.student_id = :student_id");
        $stmt->bindParam(':student_id', $_SESSION['student_id']);
        $stmt->execute();
        $enrolledCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $enroll_success = "Successfully enrolled in the course!";
    } catch(PDOException $e) {
        $enroll_error = "Error enrolling in course: " . $e->getMessage();
    }
}

// Handle course withdrawal
if (isset($_POST['withdraw'])) {
    $course_id = $_POST['course_id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM student_courses WHERE student_id = :student_id AND course_id = :course_id");
        $stmt->bindParam(':student_id', $_SESSION['student_id']);
        $stmt->bindParam(':course_id', $course_id);
        $stmt->execute();
        
        // Refresh enrolled courses
        $stmt = $conn->prepare("SELECT c.* FROM courses c 
                              JOIN student_courses sc ON c.id = sc.course_id
                              WHERE sc.student_id = :student_id");
        $stmt->bindParam(':student_id', $_SESSION['student_id']);
        $stmt->execute();
        $enrolledCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $withdraw_success = "Successfully withdrew from the course!";
    } catch(PDOException $e) {
        $withdraw_error = "Error withdrawing from course: " . $e->getMessage();
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
    <title>Course Management - Student Portal</title>
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
        .course-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        .course-card:hover {
            transform: translateY(-5px);
        }
        .course-header {
            border-radius: 10px 10px 0 0;
            padding: 15px;
            color: white;
        }
        .course-body {
            padding: 20px;
        }
        .badge-credit {
            background-color: #6c757d;
            color: white;
        }
        .enrolled-course {
            border-left: 5px solid #198754;
        }
        .available-course {
            border-left: 5px solid #0d6efd;
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
                            <a class="nav-link active" href="course.php">
                                <i class="bi bi-book"></i> Courses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="schedule.php">
                                <i class="bi bi-calendar"></i> Schedule
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="documents.php">
                                <i class="bi bi-file-earmark-text"></i> Documents
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
                    <h1 class="h2">Course Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#viewScheduleModal">
                                <i class="bi bi-calendar-week"></i> View Schedule
                            </button>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($enroll_success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $enroll_success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($enroll_error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $enroll_error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($withdraw_success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $withdraw_success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($withdraw_error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $withdraw_error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Enrolled Courses -->
                    <div class="col-lg-6">
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-check-circle"></i> My Enrolled Courses</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($enrolledCourses)): ?>
                                    <div class="alert alert-info">You are not enrolled in any courses yet.</div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($enrolledCourses as $course): ?>
                                            <div class="col-md-12 mb-3">
                                                <div class="course-card enrolled-course">
                                                    <div class="course-header bg-success">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <h5 class="mb-0"><?php echo htmlspecialchars($course['course_code']); ?></h5>
                                                            <span class="badge badge-credit"><?php echo htmlspecialchars($course['credits'] ?? '0'); ?> Credits</span>
                                                        </div>
                                                    </div>
                                                    <div class="course-body">
                                                        <h6><?php echo htmlspecialchars($course['course_name']); ?></h6>
                                                        <p class="text-muted small mb-2">
                                                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($course['instructor'] ?? 'Instructor not assigned'); ?>
                                                        </p>
                                                        <p class="text-muted small mb-3">
                                                            <i class="bi bi-clock"></i> <?php echo htmlspecialchars($course['schedule'] ?? 'Schedule not available'); ?>
                                                        </p>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                            <button type="submit" name="withdraw" class="btn btn-outline-danger btn-sm">
                                                                <i class="bi bi-x-circle"></i> Withdraw
                                                            </button>
                                                        </form>
                                                        <button class="btn btn-outline-primary btn-sm ms-2" data-bs-toggle="modal" 
                                                            data-bs-target="#courseDetailsModal" 
                                                            data-code="<?php echo htmlspecialchars($course['course_code']); ?>"
                                                            data-name="<?php echo htmlspecialchars($course['course_name']); ?>"
                                                            data-instructor="<?php echo htmlspecialchars($course['instructor'] ?? 'N/A'); ?>"
                                                            data-schedule="<?php echo htmlspecialchars($course['schedule'] ?? 'N/A'); ?>"
                                                            data-credits="<?php echo htmlspecialchars($course['credits'] ?? '0'); ?>"
                                                            data-description="<?php echo htmlspecialchars($course['description'] ?? 'No description available.'); ?>">
                                                            <i class="bi bi-info-circle"></i> Details
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Available Courses -->
                    <div class="col-lg-6">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-book"></i> Available Courses</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($availableCourses)): ?>
                                    <div class="alert alert-info">No courses available for enrollment at this time.</div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($availableCourses as $course): 
                                            $isEnrolled = in_array($course['id'], array_column($enrolledCourses, 'id'));
                                        ?>
                                            <div class="col-md-12 mb-3">
                                                <div class="course-card available-course">
                                                    <div class="course-header bg-primary">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <h5 class="mb-0"><?php echo htmlspecialchars($course['course_code']); ?></h5>
                                                            <span class="badge badge-credit"><?php echo htmlspecialchars($course['credits'] ?? '0'); ?> Credits</span>
                                                        </div>
                                                    </div>
                                                    <div class="course-body">
                                                        <h6><?php echo htmlspecialchars($course['course_name']); ?></h6>
                                                        <p class="text-muted small mb-2">
                                                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($course['instructor'] ?? 'Instructor not assigned'); ?>
                                                        </p>
                                                        <p class="text-muted small mb-3">
                                                            <i class="bi bi-clock"></i> <?php echo htmlspecialchars($course['schedule'] ?? 'Schedule not available'); ?>
                                                        </p>
                                                        <?php if ($isEnrolled): ?>
                                                            <span class="badge bg-success">Already Enrolled</span>
                                                        <?php else: ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                                <button type="submit" name="enroll" class="btn btn-outline-success btn-sm">
                                                                    <i class="bi bi-plus-circle"></i> Enroll
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <button class="btn btn-outline-primary btn-sm ms-2" data-bs-toggle="modal" 
                                                            data-bs-target="#courseDetailsModal" 
                                                            data-code="<?php echo htmlspecialchars($course['course_code']); ?>"
                                                            data-name="<?php echo htmlspecialchars($course['course_name']); ?>"
                                                            data-instructor="<?php echo htmlspecialchars($course['instructor'] ?? 'N/A'); ?>"
                                                            data-schedule="<?php echo htmlspecialchars($course['schedule'] ?? 'N/A'); ?>"
                                                            data-credits="<?php echo htmlspecialchars($course['credits'] ?? '0'); ?>"
                                                            data-description="<?php echo htmlspecialchars($course['description'] ?? 'No description available.'); ?>">
                                                            <i class="bi bi-info-circle"></i> Details
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Course Details Modal -->
    <div class="modal fade" id="courseDetailsModal" tabindex="-1" aria-labelledby="courseDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="courseDetailsModalLabel">Course Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="small mb-1">Course Code</p>
                            <h5 id="detail-code"></h5>
                        </div>
                        <div class="col-md-6">
                            <p class="small mb-1">Course Name</p>
                            <h5 id="detail-name"></h5>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="small mb-1">Instructor</p>
                            <p id="detail-instructor" class="mb-0"></p>
                        </div>
                        <div class="col-md-6">
                            <p class="small mb-1">Schedule</p>
                            <p id="detail-schedule" class="mb-0"></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="small mb-1">Credits</p>
                            <p id="detail-credits" class="mb-0"></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <p class="small mb-1">Description</p>
                        <p id="detail-description" class="mb-0"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Schedule Modal -->
    <div class="modal fade" id="viewScheduleModal" tabindex="-1" aria-labelledby="viewScheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="viewScheduleModalLabel">My Course Schedule</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($enrolledCourses)): ?>
                        <div class="alert alert-info">You are not enrolled in any courses yet.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Course Code</th>
                                        <th>Course Name</th>
                                        <th>Instructor</th>
                                        <th>Schedule</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($enrolledCourses as $course): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                            <td><?php echo htmlspecialchars($course['instructor'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($course['schedule'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Course Details Modal
        const courseDetailsModal = document.getElementById('courseDetailsModal');
        if (courseDetailsModal) {
            courseDetailsModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                document.getElementById('detail-code').textContent = button.getAttribute('data-code');
                document.getElementById('detail-name').textContent = button.getAttribute('data-name');
                document.getElementById('detail-instructor').textContent = button.getAttribute('data-instructor');
                document.getElementById('detail-schedule').textContent = button.getAttribute('data-schedule');
                document.getElementById('detail-credits').textContent = button.getAttribute('data-credits') + ' Credits';
                document.getElementById('detail-description').textContent = button.getAttribute('data-description');
            });
        }
    </script>
</body>
</html>