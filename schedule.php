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
    
    // Get student data
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['student_id']);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        header("Location: index.php");
        exit();
    }
    
    // Get student's enrolled courses with schedule information
    // $stmt = $conn->prepare("SELECT c.course_code, c.course_name, c.schedule 
    //                       FROM courses c
    //                       JOIN student_courses sc ON c.id = sc.course_id
    //                       WHERE sc.student_id = :student_id
    //                       ORDER BY 
    //                         FIELD(SUBSTRING_INDEX(c.schedule, ' ', 1), 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'),
    //                         SUBSTRING_INDEX(SUBSTRING_INDEX(c.schedule, ' ', 2), ' ', -1)");
    $stmt = $conn->prepare("SELECT c.id, c.course_code, c.course_name, 
    IFNULL(c.schedule, 'Unscheduled') as schedule
    FROM courses c
    JOIN student_courses sc ON c.id = sc.course_id
    WHERE sc.student_id = :student_id
    AND c.schedule IS NOT NULL
    AND c.schedule != ''
    ORDER BY 
      CASE 
        WHEN c.schedule LIKE 'Mon%' THEN 1
        WHEN c.schedule LIKE 'Tue%' THEN 2
        WHEN c.schedule LIKE 'Wed%' THEN 3
        WHEN c.schedule LIKE 'Thu%' THEN 4
        WHEN c.schedule LIKE 'Fri%' THEN 5
        WHEN c.schedule LIKE 'Sat%' THEN 6
        WHEN c.schedule LIKE 'Sun%' THEN 7
        ELSE 8
      END,
      c.schedule");
    $stmt->bindParam(':student_id', $_SESSION['student_id']);
    $stmt->execute();
    $scheduledCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group courses by day for better display
    // $scheduleByDay = [];
    // foreach ($scheduledCourses as $course) {
    //     if (!empty($course['schedule'])) {
    //         $day = strtok($course['schedule'], ' ');
    //         $scheduleByDay[$day][] = $course;
    //     }
        foreach ($scheduledCourses as $course) {
            if (!empty($course['schedule'])) {
                // Handle multiple day formats (Mon/Wed, Tue-Thu, etc.)
                $days = preg_split('/[\/\-]/', strtok($course['schedule'], ' '));
                foreach ($days as $day) {
                    $scheduleByDay[$day][] = $course;
                }
            }
        }
    
    
    // Define days of week in order
    $daysOfWeek = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    
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
    <title>My Schedule - Student Portal</title>
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
        .schedule-day {
            margin-bottom: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .schedule-header {
            padding: 15px;
            color: white;
            font-weight: 600;
        }
        .schedule-body {
            padding: 20px;
            background-color: white;
        }
        .course-item {
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #0d6efd;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .course-item:last-child {
            margin-bottom: 0;
        }
        .course-time {
            font-weight: 600;
            color: #0d6efd;
        }
        .no-courses {
            padding: 20px;
            text-align: center;
            color: #6c757d;
        }
        .day-Mon .schedule-header { background-color: #FF6B6B; }
        .day-Tue .schedule-header { background-color: #4ECDC4; }
        .day-Wed .schedule-header { background-color: #45B7D1; }
        .day-Thu .schedule-header { background-color: #FFA07A; }
        .day-Fri .schedule-header { background-color: #98D8C8; }
        .day-Sat .schedule-header { background-color: #D4A5A5; }
        .day-Sun .schedule-header { background-color: #F06292; }
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
                            <a class="nav-link active" href="schedule.php">
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
                    <h1 class="h2">My Course Schedule</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="bi bi-printer"></i> Print Schedule
                            </button>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($scheduledCourses)): ?>
                    <div class="alert alert-info">
                        You are not enrolled in any courses with scheduled meeting times. 
                        <a href="course.php" class="alert-link">Browse available courses</a> to enroll.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($daysOfWeek as $day): ?>
                            <?php if (isset($scheduleByDay[$day])): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="schedule-day day-<?php echo $day; ?>">
                                        <div class="schedule-header">
                                            <h5><?php echo $day; ?>day</h5>
                                        </div>
                                        <div class="schedule-body">
                                            <?php foreach ($scheduleByDay[$day] as $course): ?>
                                                <div class="course-item">
                                                    <div class="course-time">
                                                        <?php 
                                                        // Extract time from schedule (assuming format like "Mon/Wed 10:00-11:30")
                                                        $scheduleParts = explode(' ', $course['schedule']);
                                                        echo isset($scheduleParts[1]) ? $scheduleParts[1] : 'Time TBD'; 
                                                        ?>
                                                    </div>
                                                    <h6><?php echo htmlspecialchars($course['course_name']); ?></h6>
                                                    <div class="text-muted small">
                                                        <?php echo htmlspecialchars($course['course_code']); ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <!-- Calendar View -->
                        <div class="col-12 mt-4">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="bi bi-calendar-week"></i> Weekly Calendar View</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th style="width: 14.28%">Monday</th>
                                                    <th style="width: 14.28%">Tuesday</th>
                                                    <th style="width: 14.28%">Wednesday</th>
                                                    <th style="width: 14.28%">Thursday</th>
                                                    <th style="width: 14.28%">Friday</th>
                                                    <th style="width: 14.28%">Saturday</th>
                                                    <th style="width: 14.28%">Sunday</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr style="height: 150px;">
                                                    <?php foreach ($daysOfWeek as $day): ?>
                                                        <td class="align-top">
                                                            <?php if (isset($scheduleByDay[$day])): ?>
                                                                <?php foreach ($scheduleByDay[$day] as $course): ?>
                                                                    <div class="mb-2 p-2 bg-light rounded">
                                                                        <strong><?php echo htmlspecialchars($course['course_code']); ?></strong><br>
                                                                        <?php 
                                                                        $scheduleParts = explode(' ', $course['schedule']);
                                                                        echo isset($scheduleParts[1]) ? $scheduleParts[1] : ''; 
                                                                        ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>