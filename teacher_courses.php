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
    
    // Get courses taught by this teacher
    $stmt = $conn->prepare("SELECT c.* FROM courses c
                          JOIN teacher_courses tc ON c.id = tc.course_id
                          WHERE tc.teacher_id = :teacher_id
                          ORDER BY c.course_name");
    $stmt->bindParam(':teacher_id', $_SESSION['teacher_id']);
    $stmt->execute();
    $teacherCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Handle course creation
    if (isset($_POST['create_course'])) {
        $course_code = trim($_POST['course_code']);
        $course_name = trim($_POST['course_name']);
        $credits = $_POST['credits'];
        $description = trim($_POST['description']);
        
        // Validate inputs
        $errors = [];
        if (empty($course_code)) $errors[] = "Course code is required";
        if (empty($course_name)) $errors[] = "Course name is required";
        if (!is_numeric($credits) || $credits < 0 || $credits > 10) {
            $errors[] = "Credits must be between 0 and 10";
        }
        
        if (empty($errors)) {
            try {
                // Insert new course
                $stmt = $conn->prepare("INSERT INTO courses 
                    (course_code, course_name, instructor, credits, description) 
                    VALUES (:course_code, :course_name, :instructor, :credits, :description)");
                
                $stmt->bindParam(':course_code', $course_code);
                $stmt->bindParam(':course_name', $course_name);
                $stmt->bindParam(':instructor', $teacher['full_name']);
                $stmt->bindParam(':credits', $credits);
                $stmt->bindParam(':description', $description);
                $stmt->execute();
                
                $course_id = $conn->lastInsertId();
                
                // Assign teacher to the course
                $stmt = $conn->prepare("INSERT INTO teacher_courses (teacher_id, course_id) VALUES (:teacher_id, :course_id)");
                $stmt->bindParam(':teacher_id', $_SESSION['teacher_id']);
                $stmt->bindParam(':course_id', $course_id);
                $stmt->execute();
                
                $_SESSION['message'] = "Course created successfully!";
                header("Location: teacher_courses.php");
                exit();
            } catch(PDOException $e) {
                $create_error = "Error creating course: " . $e->getMessage();
            }
        } else {
            $create_error = implode("<br>", $errors);
        }
    }
    
    // Handle course update
    if (isset($_POST['update_course'])) {
        $course_id = $_POST['course_id'];
        $course_code = trim($_POST['course_code']);
        $course_name = trim($_POST['course_name']);
        $schedule = trim($_POST['schedule']);
        $credits = $_POST['credits'];
        $description = trim($_POST['description']);
        
        // Validate inputs
        $errors = [];
        if (empty($course_code)) $errors[] = "Course code is required";
        if (empty($course_name)) $errors[] = "Course name is required";
        if (!is_numeric($credits) || $credits < 0 || $credits > 10) {
            $errors[] = "Credits must be between 0 and 10";
        }
        
        if (empty($errors)) {
            try {
                $stmt = $conn->prepare("UPDATE courses SET 
                    course_code = :course_code,
                    course_name = :course_name,
                    schedule = :schedule,
                    credits = :credits,
                    description = :description
                    WHERE id = :id");
                
                $stmt->bindParam(':course_code', $course_code);
                $stmt->bindParam(':course_name', $course_name);
                $stmt->bindParam(':schedule', $schedule);
                $stmt->bindParam(':credits', $credits);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':id', $course_id);
                $stmt->execute();
                
                $_SESSION['message'] = "Course updated successfully!";
                header("Location: teacher_courses.php");
                exit();
            } catch(PDOException $e) {
                $update_error = "Error updating course: " . $e->getMessage();
            }
        } else {
            $update_error = implode("<br>", $errors);
        }
    }
    
    // Handle course deletion
    if (isset($_GET['delete'])) {
        $course_id = $_GET['delete'];
        
        try {
            // First delete from teacher_courses
            $stmt = $conn->prepare("DELETE FROM teacher_courses WHERE course_id = :course_id");
            $stmt->bindParam(':course_id', $course_id);
            $stmt->execute();
            
            // Then delete from student_courses
            $stmt = $conn->prepare("DELETE FROM student_courses WHERE course_id = :course_id");
            $stmt->bindParam(':course_id', $course_id);
            $stmt->execute();
            
            // Then delete the course
            $stmt = $conn->prepare("DELETE FROM courses WHERE id = :id");
            $stmt->bindParam(':id', $course_id);
            $stmt->execute();
            
            $_SESSION['message'] = "Course deleted successfully!";
            header("Location: teacher_courses.php");
            exit();
        } catch(PDOException $e) {
            $delete_error = "Error deleting course: " . $e->getMessage();
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
    <title>My Courses - Teacher Portal</title>
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
        .enrolled-count {
            background-color: #0d6efd;
            color: white;
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
                            <a class="nav-link active" href="teacher_courses.php">
                                <i class="bi bi-book"></i> My Courses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="teacher_manage_students.php">
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
                    <h1 class="h2">My Courses</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCourseModal">
                            <i class="bi bi-plus-circle"></i> Create New Course
                        </button>
                    </div>
                </div>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($create_error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $create_error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($update_error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $update_error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($delete_error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $delete_error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <?php if (empty($teacherCourses)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                You are not assigned to any courses yet. Create your first course using the button above.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($teacherCourses as $course): 
                            // Get enrolled student count for this course
                            $stmt = $conn->prepare("SELECT COUNT(*) as student_count FROM student_courses WHERE course_id = :course_id");
                            $stmt->bindParam(':course_id', $course['id']);
                            $stmt->execute();
                            $student_count = $stmt->fetch(PDO::FETCH_ASSOC)['student_count'];
                        ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="course-card">
                                    <div class="course-header bg-primary">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0"><?php echo htmlspecialchars($course['course_code']); ?></h5>
                                            <span class="badge badge-credit"><?php echo htmlspecialchars($course['credits'] ?? '0'); ?> Credits</span>
                                        </div>
                                    </div>
                                    <div class="course-body">
                                        <h6><?php echo htmlspecialchars($course['course_name']); ?></h6>
                                        <p class="text-muted small mb-2">
                                            <i class="bi bi-clock"></i> <?php echo htmlspecialchars($course['schedule'] ?? 'Schedule not set'); ?>
                                        </p>
                                        <p class="text-muted small mb-3">
                                            <span class="badge enrolled-count">
                                                <i class="bi bi-people"></i> <?php echo $student_count; ?> Enrolled
                                            </span>
                                        </p>
                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="teacher_course_students.php?students_id=<?php echo $students['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-people"></i> Students
                                            </a>
                                            <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" 
                                                data-bs-target="#editCourseModal" 
                                                data-id="<?php echo $course['id']; ?>"
                                                data-code="<?php echo htmlspecialchars($course['course_code']); ?>"
                                                data-name="<?php echo htmlspecialchars($course['course_name']); ?>"
                                                data-schedule="<?php echo htmlspecialchars($course['schedule'] ?? ''); ?>"
                                                data-credits="<?php echo htmlspecialchars($course['credits'] ?? '0'); ?>"
                                                data-description="<?php echo htmlspecialchars($course['description'] ?? ''); ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                            <a href="?delete=<?php echo $course['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Are you sure you want to delete this course? This will also remove all student enrollments.')">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Course Modal -->
    <div class="modal fade" id="createCourseModal" tabindex="-1" aria-labelledby="createCourseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="createCourseModalLabel">Create New Course</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="course_code" class="form-label">Course Code</label>
                            <input type="text" class="form-control" id="course_code" name="course_code" required>
                        </div>
                        <div class="mb-3">
                            <label for="course_name" class="form-label">Course Name</label>
                            <input type="text" class="form-control" id="course_name" name="course_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="credits" class="form-label">Credits</label>
                            <input type="number" class="form-control" id="credits" name="credits" min="0" max="10" value="3" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_course" class="btn btn-primary">Create Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Course Modal -->
    <div class="modal fade" id="editCourseModal" tabindex="-1" aria-labelledby="editCourseModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <input type="hidden" name="course_id" id="edit_course_id">
                    <div class="modal-header bg-warning text-white">
                        <h5 class="modal-title" id="editCourseModalLabel">Edit Course</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_course_code" class="form-label">Course Code</label>
                            <input type="text" class="form-control" id="edit_course_code" name="course_code" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_course_name" class="form-label">Course Name</label>
                            <input type="text" class="form-control" id="edit_course_name" name="course_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_schedule" class="form-label">Schedule</label>
                            <input type="text" class="form-control" id="edit_schedule" name="schedule" placeholder="e.g., Mon/Wed 10:00-11:30">
                        </div>
                        <div class="mb-3">
                            <label for="edit_credits" class="form-label">Credits</label>
                            <input type="number" class="form-control" id="edit_credits" name="credits" min="0" max="10" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_course" class="btn btn-warning text-white">Update Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Edit Course Modal
        const editCourseModal = document.getElementById('editCourseModal');
        if (editCourseModal) {
            editCourseModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                
                document.getElementById('edit_course_id').value = button.getAttribute('data-id');
                document.getElementById('edit_course_code').value = button.getAttribute('data-code');
                document.getElementById('edit_course_name').value = button.getAttribute('data-name');
                document.getElementById('edit_schedule').value = button.getAttribute('data-schedule');
                document.getElementById('edit_credits').value = button.getAttribute('data-credits');
                document.getElementById('edit_description').value = button.getAttribute('data-description');
            });
        }
    </script>
</body>
</html>