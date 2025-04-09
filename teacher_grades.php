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
    $stmt = $conn->prepare("SELECT c.id, c.course_code, c.course_name 
                           FROM courses c
                           JOIN teacher_courses tc ON c.id = tc.course_id
                           WHERE tc.teacher_id = :teacher_id");
    $stmt->bindParam(':teacher_id', $_SESSION['teacher_id']);
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['add_grade'])) {
            // Add new grade record
            $stmt = $conn->prepare("INSERT INTO grades 
                                   (student_id, course_id, semester, academic_year, grade, credits_earned, remarks)
                                   VALUES (:student_id, :course_id, :semester, :academic_year, :grade, :credits_earned, :remarks)");
            $stmt->bindParam(':student_id', $_POST['student_id']);
            $stmt->bindParam(':course_id', $_POST['course_id']);
            $stmt->bindParam(':semester', $_POST['semester']);
            $stmt->bindParam(':academic_year', $_POST['academic_year']);
            $stmt->bindParam(':grade', $_POST['grade']);
            $stmt->bindParam(':credits_earned', $_POST['credits_earned']);
            $stmt->bindParam(':remarks', $_POST['remarks']);
            $stmt->execute();
            
            $_SESSION['message'] = "Grade added successfully!";
            header("Location: teacher_grades.php");
            exit();
        } elseif (isset($_POST['update_grade'])) {
            // Update existing grade record
            $stmt = $conn->prepare("UPDATE grades SET 
                                   grade = :grade, 
                                   credits_earned = :credits_earned,
                                   remarks = :remarks
                                   WHERE id = :id");
            $stmt->bindParam(':grade', $_POST['grade']);
            $stmt->bindParam(':credits_earned', $_POST['credits_earned']);
            $stmt->bindParam(':remarks', $_POST['remarks']);
            $stmt->bindParam(':id', $_POST['grade_id']);
            $stmt->execute();
            
            $_SESSION['message'] = "Grade updated successfully!";
            header("Location: teacher_grades.php");
            exit();
        } elseif (isset($_POST['delete'])) {
            try {
                // Delete grade record
                $stmt = $conn->prepare("DELETE FROM grades WHERE id = :id");
                $stmt->bindParam(':id', $_POST['delete']);
                $stmt->execute();
                
                $_SESSION['message'] = "Grade deleted successfully!";
            } catch(PDOException $e) {
                $_SESSION['error'] = "Error deleting grade: " . $e->getMessage();
            }
            header("Location: teacher_grades.php?course_id=" . $_POST['course_id']);
            exit();
        }
    }
    
    // Get students for selected course (if any)
    $students = [];
    $grades = [];
    if (isset($_GET['course_id']) && !empty($_GET['course_id'])) {
        // Get students enrolled in this course
        $stmt = $conn->prepare("SELECT s.id, s.full_name 
                               FROM students s
                               JOIN student_courses sc ON s.id = sc.student_id
                               WHERE sc.course_id = :course_id");
        $stmt->bindParam(':course_id', $_GET['course_id']);
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get existing grades for this course
        $stmt = $conn->prepare("SELECT g.id, g.student_id, s.full_name, g.grade, 
                               g.credits_earned, g.remarks, g.semester, g.academic_year
                               FROM grades g
                               JOIN students s ON g.student_id = s.id
                               WHERE g.course_id = :course_id");
        $stmt->bindParam(':course_id', $_GET['course_id']);
        $stmt->execute();
        $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Grade Management - Teacher Portal</title>
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
        .grade-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        .grade-table {
            width: 100%;
            margin-bottom: 30px;
        }
        .grade-table th {
            background-color: #f1f1f1;
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
                            <a class="nav-link active" href="teacher_grades.php">
                                <i class="bi bi-journal-check"></i> Grade Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="teacher_courses.php">
                                <i class="bi bi-book"></i> My Courses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="teacher_students.php">
                                <i class="bi bi-people"></i> Students
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
                    <h1 class="h2">Grade Management</h1>
                </div>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="grade-container mb-4">
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
                            <button type="submit" class="btn btn-primary">Load Students</button>
                        </div>
                    </form>
                </div>
                
                <?php if (!empty($students) && isset($_GET['course_id'])): ?>
                    <div class="grade-container">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4>Manage Grades</h4>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addGradeModal">
                                <i class="bi bi-plus-circle"></i> Add Grade
                            </button>
                        </div>
                        
                        <?php if (empty($grades)): ?>
                            <div class="alert alert-info">
                                No grades recorded yet for this course.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered grade-table">
                                    <thead>
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Grade</th>
                                            <th>Credits Earned</th>
                                            <th>Semester</th>
                                            <th>Academic Year</th>
                                            <th>Remarks</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grades as $grade): ?>
                                            <tr class="grade-<?php echo substr($grade['grade'], 0, 1); ?>">
                                                <td><?php echo htmlspecialchars($grade['full_name']); ?></td>
                                                <td class="text-center"><?php echo htmlspecialchars($grade['grade']); ?></td>
                                                <td class="text-center"><?php echo htmlspecialchars($grade['credits_earned']); ?></td>
                                                <td class="text-center"><?php echo htmlspecialchars($grade['semester']); ?></td>
                                                <td class="text-center"><?php echo htmlspecialchars($grade['academic_year']); ?></td>
                                                <td><?php echo htmlspecialchars($grade['remarks']); ?></td>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" 
                                                        data-bs-target="#editGradeModal" 
                                                        data-id="<?php echo $grade['id']; ?>"
                                                        data-grade="<?php echo $grade['grade']; ?>"
                                                        data-credits="<?php echo $grade['credits_earned']; ?>"
                                                        data-remarks="<?php echo htmlspecialchars($grade['remarks']); ?>">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </button>
                                                    
                                                    <form method="post" action="teacher_grades.php" style="display: inline;">
                                                        <input type="hidden" name="course_id" value="<?php echo $_GET['course_id']; ?>">
                                                        <input type="hidden" name="delete" value="<?php echo $grade['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" 
                                                                onclick="return confirm('Are you sure you want to delete this grade?')">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <!-- Add Grade Modal -->
    <div class="modal fade" id="addGradeModal" tabindex="-1" aria-labelledby="addGradeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addGradeModalLabel">Add New Grade</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="course_id" value="<?php echo $_GET['course_id'] ?? ''; ?>">
                        
                        <div class="mb-3">
                            <label for="student_id" class="form-label">Student</label>
                            <select name="student_id" id="student_id" class="form-select" required>
                                <option value="" hidden>-- Select Student --</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo htmlspecialchars($student['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="semester" class="form-label">Semester</label>
                                <select name="semester" id="semester" class="form-select" required>
                                    <option value="Fall">Fall</option>
                                    <option value="Spring">Spring</option>
                                    <option value="Summer">Summer</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="academic_year" class="form-label">Academic Year</label>
                                <input type="text" name="academic_year" id="academic_year" class="form-control" 
                                       placeholder="e.g. 2022-2023" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="grade" class="form-label">Grade</label>
                                <select name="grade" id="grade" class="form-select" required>
                                    <option value="A">A</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B">B</option>
                                    <option value="B-">B-</option>
                                    <option value="C+">C+</option>
                                    <option value="C">C</option>
                                    <option value="C-">C-</option>
                                    <option value="D+">D+</option>
                                    <option value="D">D</option>
                                    <option value="F">F</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="credits_earned" class="form-label">Credits Earned</label>
                                <input type="number" name="credits_earned" id="credits_earned" class="form-control" 
                                       step="0.5" min="0" max="10" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks (Optional)</label>
                            <textarea name="remarks" id="remarks" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="add_grade" class="btn btn-primary">Save Grade</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Grade Modal -->
    <div class="modal fade" id="editGradeModal" tabindex="-1" aria-labelledby="editGradeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editGradeModalLabel">Edit Grade</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="grade_id" id="edit_grade_id">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_grade" class="form-label">Grade</label>
                                <select name="grade" id="edit_grade" class="form-select" required>
                                    <option value="A">A</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B">B</option>
                                    <option value="B-">B-</option>
                                    <option value="C+">C+</option>
                                    <option value="C">C</option>
                                    <option value="C-">C-</option>
                                    <option value="D+">D+</option>
                                    <option value="D">D</option>
                                    <option value="F">F</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_credits_earned" class="form-label">Credits Earned</label>
                                <input type="number" name="credits_earned" id="edit_credits_earned" class="form-control" 
                                       step="0.5" min="0" max="10" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_remarks" class="form-label">Remarks (Optional)</label>
                            <textarea name="remarks" id="edit_remarks" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="update_grade" class="btn btn-primary">Update Grade</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Handle edit modal data
        document.getElementById('editGradeModal').addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var grade = button.getAttribute('data-grade');
            var credits = button.getAttribute('data-credits');
            var remarks = button.getAttribute('data-remarks');
            
            var modal = this;
            modal.querySelector('#edit_grade_id').value = id;
            modal.querySelector('#edit_grade').value = grade;
            modal.querySelector('#edit_credits_earned').value = credits;
            modal.querySelector('#edit_remarks').value = remarks;
        });
    </script>
</body>
</html>