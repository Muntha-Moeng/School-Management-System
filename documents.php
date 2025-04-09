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

// File upload directory
$uploadDir = 'uploads/documents/';

// Create directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create documents table if it doesn't exist
    $conn->exec("CREATE TABLE IF NOT EXISTS student_documents (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        student_id INT(6) UNSIGNED NOT NULL,
        title VARCHAR(100) NOT NULL,
        description TEXT,
        file_name VARCHAR(255) NOT NULL,
        file_type VARCHAR(50) NOT NULL,
        file_size INT(11) NOT NULL,
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id)
    )");
    
    // Get student data
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['student_id']);
    $stmt->execute();
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get student's documents
    $stmt = $conn->prepare("SELECT * FROM student_documents WHERE student_id = :student_id ORDER BY upload_date DESC");
    $stmt->bindParam(':student_id', $_SESSION['student_id']);
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Handle file upload
    $uploadMessage = '';
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['document'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $file = $_FILES['document'];
        
        // Validate inputs
        $errors = [];
        if (empty($title)) {
            $errors[] = "Document title is required";
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload error: " . $file['error'];
        }
        
        // Check file type (example: only allow PDFs)
        $allowedTypes = ['application/pdf'];
        if (!in_array($file['type'], $allowedTypes)) {
            $errors[] = "Only PDF files are allowed";
        }
        
        // Check file size (max 5MB)
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            $errors[] = "File size exceeds 5MB limit";
        }
        
        if (empty($errors)) {
            // Generate unique filename
            $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = uniqid() . '.' . $fileExt;
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                // Save to database
                $stmt = $conn->prepare("INSERT INTO student_documents 
                    (student_id, title, description, file_name, file_type, file_size) 
                    VALUES (:student_id, :title, :description, :file_name, :file_type, :file_size)");
                
                $stmt->bindParam(':student_id', $_SESSION['student_id']);
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':file_name', $fileName);
                $stmt->bindParam(':file_type', $file['type']);
                $stmt->bindParam(':file_size', $file['size']);
                
                if ($stmt->execute()) {
                    $uploadMessage = '<div class="alert alert-success">Document uploaded successfully!</div>';
                    // Refresh document list
                    $stmt = $conn->prepare("SELECT * FROM student_documents WHERE student_id = :student_id ORDER BY upload_date DESC");
                    $stmt->bindParam(':student_id', $_SESSION['student_id']);
                    $stmt->execute();
                    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $uploadMessage = '<div class="alert alert-danger">Error saving document to database</div>';
                }
            } else {
                $uploadMessage = '<div class="alert alert-danger">Error moving uploaded file</div>';
            }
        } else {
            $uploadMessage = '<div class="alert alert-danger">' . implode('<br>', $errors) . '</div>';
        }
    }
    
    // Handle document deletion
    if (isset($_GET['delete'])) {
        $docId = $_GET['delete'];
        
        // Verify document belongs to student
        $stmt = $conn->prepare("SELECT file_name FROM student_documents WHERE id = :id AND student_id = :student_id");
        $stmt->bindParam(':id', $docId);
        $stmt->bindParam(':student_id', $_SESSION['student_id']);
        $stmt->execute();
        $document = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($document) {
            // Delete file
            $filePath = $uploadDir . $document['file_name'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            // Delete database record
            $stmt = $conn->prepare("DELETE FROM student_documents WHERE id = :id");
            $stmt->bindParam(':id', $docId);
            if ($stmt->execute()) {
                $uploadMessage = '<div class="alert alert-success">Document deleted successfully!</div>';
                // Refresh document list
                $stmt = $conn->prepare("SELECT * FROM student_documents WHERE student_id = :student_id ORDER BY upload_date DESC");
                $stmt->bindParam(':student_id', $_SESSION['student_id']);
                $stmt->execute();
                $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
    
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
    <title>My Documents - Student Portal</title>
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
        .document-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        .document-card:hover {
            transform: translateY(-5px);
        }
        .document-icon {
            font-size: 3rem;
            color: #dc3545;
        }
        .file-size {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .upload-date {
            color: #6c757d;
            font-size: 0.8rem;
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
                            <a class="nav-link active" href="document.php">
                                <i class="bi bi-file-earmark-text"></i> Documents
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="transcript.php">
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
                    <h1 class="h2">My Documents</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                            <i class="bi bi-upload"></i> Upload Document
                        </button>
                    </div>
                </div>
                
                <?php echo $uploadMessage; ?>
                
                <?php if (empty($documents)): ?>
                    <div class="alert alert-info">
                        You haven't uploaded any documents yet. Click the "Upload Document" button to add your first document.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($documents as $doc): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card document-card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start">
                                            <div class="me-3">
                                                <i class="bi bi-file-earmark-pdf document-icon"></i>
                                            </div>
                                            <div>
                                                <h5><?php echo htmlspecialchars($doc['title']); ?></h5>
                                                <?php if (!empty($doc['description'])): ?>
                                                    <p class="mb-2"><?php echo htmlspecialchars($doc['description']); ?></p>
                                                <?php endif; ?>
                                                <div class="file-size mb-2">
                                                    <?php echo round($doc['file_size'] / 1024 / 1024, 2); ?> MB
                                                </div>
                                                <div class="upload-date">
                                                    Uploaded: <?php echo date('M j, Y', strtotime($doc['upload_date'])); ?>
                                                </div>
                                                <div class="mt-3">
                                                    <a href="<?php echo $uploadDir . htmlspecialchars($doc['file_name']); ?>" 
                                                       class="btn btn-sm btn-outline-primary" target="_blank">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                    <a href="<?php echo $uploadDir . htmlspecialchars($doc['file_name']); ?>" 
                                                       class="btn btn-sm btn-outline-secondary" download>
                                                        <i class="bi bi-download"></i> Download
                                                    </a>
                                                    <a href="document.php?delete=<?php echo $doc['id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger" 
                                                       onclick="return confirm('Are you sure you want to delete this document?')">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Upload Document Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadModalLabel">Upload New Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">Document Title *</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="document" class="form-label">Document File (PDF only, max 5MB) *</label>
                            <input class="form-control" type="file" id="document" name="document" accept=".pdf" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload Document</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>