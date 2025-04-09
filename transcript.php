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
    
    // Create grades table if it doesn't exist
    $conn->exec("CREATE TABLE IF NOT EXISTS grades (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        student_id INT(6) UNSIGNED NOT NULL,
        course_id INT(6) UNSIGNED NOT NULL,
        semester VARCHAR(20) NOT NULL,
        academic_year VARCHAR(20) NOT NULL,
        grade VARCHAR(2) NOT NULL,
        credits_earned DECIMAL(3,1) NOT NULL,
        remarks TEXT,
        date_recorded TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id),
        FOREIGN KEY (course_id) REFERENCES courses(id)
    )");
    
    // Get student data
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['student_id']);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        header("Location: index.php");
        exit();
    }
    
    // Get student's transcript data
    $stmt = $conn->prepare("SELECT 
        c.course_code, 
        c.course_name, 
        c.credits AS credits_possible,
        g.semester,
        g.academic_year,
        g.grade,
        g.credits_earned,
        g.remarks
        FROM grades g
        JOIN courses c ON g.course_id = c.id
        WHERE g.student_id = :student_id
        ORDER BY g.academic_year, 
        FIELD(g.semester, 'Fall', 'Spring', 'Summer'),
        c.course_code");
    $stmt->bindParam(':student_id', $_SESSION['student_id']);
    $stmt->execute();
    $transcriptRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate GPA and totals
    $totalCreditsAttempted = 0;
    $totalCreditsEarned = 0;
    $totalGradePoints = 0;
    
    $gradeValues = [
        'A' => 4.0, 'A-' => 3.7,
        'B+' => 3.3, 'B' => 3.0, 'B-' => 2.7,
        'C+' => 2.3, 'C' => 2.0, 'C-' => 1.7,
        'D+' => 1.3, 'D' => 1.0, 'F' => 0.0
    ];
    
    foreach ($transcriptRecords as $record) {
        $totalCreditsAttempted += $record['credits_possible'];
        $totalCreditsEarned += $record['credits_earned'];
        if (isset($gradeValues[$record['grade']])) {
            $totalGradePoints += $gradeValues[$record['grade']] * $record['credits_possible'];
        }
    }
    
    $gpa = $totalCreditsAttempted > 0 ? $totalGradePoints / $totalCreditsAttempted : 0;
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
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
    <title>Academic Transcript - Student Portal</title>
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
        .transcript-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        .transcript-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 20px;
        }
        .transcript-table {
            width: 100%;
            margin-bottom: 30px;
        }
        .transcript-table th {
            background-color: #f1f1f1;
            text-align: center;
        }
        .transcript-summary {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
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
                            <a class="nav-link" href="course.php">
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
                        <li class="nav-item">
                            <a class="nav-link active" href="transcript.php">
                                <i class="bi bi-award"></i> Transcript
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
                    <h1 class="h2">Academic Transcript</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="#" class="btn btn-primary" onclick="window.print()">
                                <i class="bi bi-download"></i> Download PDF
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="transcript-container">
                    <div class="transcript-header">
                        <h2>OFFICIAL ACADEMIC TRANSCRIPT</h2>
                        <h4>University of Example</h4>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5>Student Information</h5>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></p>
                            <p><strong>Student ID:</strong> <?php echo htmlspecialchars($student['id']); ?></p>
                            <p><strong>Program:</strong> <?php echo htmlspecialchars($student['course']); ?></p>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <p><strong>Transcript Date:</strong> <?php echo date('F j, Y'); ?></p>
                        </div>
                    </div>
                    
                    <?php if (empty($transcriptRecords)): ?>
                        <div class="alert alert-info">
                            No transcript records found. You haven't completed any courses yet.
                        </div>
                    <?php else: ?>
                        <table class="table table-bordered transcript-table">
                            <thead>
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Name</th>
                                    <th>Credits</th>
                                    <th>Grade</th>
                                    <th>Semester</th>
                                    <th>Academic Year</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transcriptRecords as $record): ?>
                                    <tr class="grade-<?php echo substr($record['grade'], 0, 1); ?>">
                                        <td><?php echo htmlspecialchars($record['course_code']); ?></td>
                                        <td><?php echo htmlspecialchars($record['course_name']); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($record['credits_possible']); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($record['grade']); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($record['semester']); ?></td>
                                        <td class="text-center"><?php echo htmlspecialchars($record['academic_year']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div class="transcript-summary">
                            <h5>Academic Summary</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <p><strong>Total Credits Attempted:</strong> <?php echo $totalCreditsAttempted; ?></p>
                                </div>
                                <div class="col-md-4">
                                    <p><strong>Total Credits Earned:</strong> <?php echo $totalCreditsEarned; ?></p>
                                </div>
                                <div class="col-md-4">
                                    <p><strong>Cumulative GPA:</strong> <?php echo number_format($gpa, 2); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-center mt-4">
                        <p class="text-muted"><small>This is an unofficial transcript. Official transcripts must be requested from the Registrar's Office.</small></p>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>