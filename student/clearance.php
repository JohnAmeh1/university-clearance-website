<?php 
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!is_logged_in() || !is_student()) {
    redirect('../login.php');
}

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $requirement_id = $_POST['requirement_id'];
    
    // Check if file was uploaded
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['document'];
        
        // Validate file type and size
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $_SESSION['error'] = "Only PDF, JPEG, and PNG files are allowed.";
        } elseif ($file['size'] > $max_size) {
            $_SESSION['error'] = "File size exceeds 5MB limit.";
        } else {
            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            $upload_path = '../uploads/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                try {
                    // Check if document already exists for this requirement
                    $stmt = $pdo->prepare("
                        SELECT id FROM student_documents 
                        WHERE student_id = ? AND requirement_id = ?
                    ");
                    $stmt->execute([$_SESSION['user_id'], $requirement_id]);
                    
                    if ($stmt->fetch()) {
                        // Update existing document
                        $stmt = $pdo->prepare("
                            UPDATE student_documents 
                            SET document_path = ?, uploaded_at = NOW(), status = 'pending' 
                            WHERE student_id = ? AND requirement_id = ?
                        ");
                        $stmt->execute([$filename, $_SESSION['user_id'], $requirement_id]);
                    } else {
                        // Insert new document
                        $stmt = $pdo->prepare("
                            INSERT INTO student_documents 
                            (student_id, requirement_id, document_path, status) 
                            VALUES (?, ?, ?, 'pending')
                        ");
                        $stmt->execute([$_SESSION['user_id'], $requirement_id, $filename]);
                    }
                    
                    $_SESSION['success'] = "Document uploaded successfully.";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Error uploading document: " . $e->getMessage();
                    // Delete the uploaded file if database operation failed
                    if (file_exists($upload_path)) {
                        unlink($upload_path);
                    }
                }
            } else {
                $_SESSION['error'] = "Error uploading file.";
            }
        }
    } else {
        $_SESSION['error'] = "Please select a file to upload.";
    }
    
    redirect('clearance.php');
}

// Get clearance requirements for student's department
$stmt = $pdo->prepare("
    SELECT cr.id, cr.document_name, cr.description, 
           sd.document_path, sd.status, sd.feedback
    FROM clearance_requirements cr
    LEFT JOIN student_documents sd ON cr.id = sd.requirement_id AND sd.student_id = ?
    WHERE cr.department_id = (SELECT id FROM departments WHERE name = ?)
    ORDER BY cr.document_name
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['department']]);
$requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get clearance status
$stmt = $pdo->prepare("
    SELECT is_complete FROM clearance_status WHERE student_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$clearance_status = $stmt->fetch();

$is_complete = $clearance_status ? $clearance_status['is_complete'] : false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clearance Checklist - University Clearance System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="bg-blue-800 text-white w-64 p-4">
            <h1 class="text-2xl font-bold mb-6">Student Panel</h1>
            <nav>
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="flex items-center space-x-2 px-4 py-2 hover:bg-blue-700 rounded">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="clearance.php" class="flex items-center space-x-2 px-4 py-2 bg-blue-700 rounded">
                            <i class="fas fa-list-check"></i>
                            <span>Clearance Checklist</span>
                        </a>
                    </li>
                    <li>
                        <a href="../includes/logout.php" class="flex items-center space-x-2 px-4 py-2 hover:bg-blue-700 rounded">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 p-8">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold">Clearance Checklist</h1>
                <div>
                    <?php if ($is_complete): ?>
                        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                            <i class="fas fa-check-circle mr-1"></i> Clearance Completed
                        </span>
                    <?php else: ?>
                        <span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm font-medium">
                            <i class="fas fa-exclamation-circle mr-1"></i> Clearance Incomplete
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Requirements List -->
            <div class="bg-white p-6 rounded-lg shadow">
                <?php if (empty($requirements)): ?>
                    <p class="text-gray-500">No clearance requirements found for your department.</p>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($requirements as $req): ?>
                            <div class="border-b pb-4 last:border-b-0">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h3 class="font-bold text-lg"><?= htmlspecialchars($req['document_name']) ?></h3>
                                        <?php if ($req['description']): ?>
                                            <p class="text-gray-600"><?= htmlspecialchars($req['description']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php if ($req['status'] === 'approved'): ?>
                                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">
                                                Approved
                                            </span>
                                        <?php elseif ($req['status'] === 'rejected'): ?>
                                            <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs">
                                                Rejected
                                            </span>
                                        <?php else: ?>
                                            <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs">
                                                <?= $req['document_path'] ? 'Pending' : 'Missing' ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($req['feedback']): ?>
                                    <div class="bg-gray-100 p-3 rounded mb-3">
                                        <p class="font-medium text-gray-700">Feedback:</p>
                                        <p class="text-gray-600"><?= htmlspecialchars($req['feedback']) ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($is_complete || $req['status'] === 'approved'): ?>
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-1">
                                            <input type="file" 
                                                   disabled
                                                   class="block w-full text-sm text-gray-400
                                                          file:mr-4 file:py-2 file:px-4
                                                          file:rounded file:border-0
                                                          file:text-sm file:font-semibold
                                                          file:bg-gray-200 file:text-gray-500
                                                          cursor-not-allowed">
                                        </div>
                                        <button type="button" 
                                                disabled
                                                class="px-4 py-2 bg-gray-400 text-white rounded cursor-not-allowed">
                                            Upload Disabled
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <form method="POST" enctype="multipart/form-data" class="flex items-center space-x-4">
                                        <input type="hidden" name="requirement_id" value="<?= htmlspecialchars($req['id']) ?>">
                                        <div class="flex-1">
                                            <input type="file" name="document" id="document_<?= htmlspecialchars($req['id']) ?>" 
                                                   class="block w-full text-sm text-gray-500
                                                          file:mr-4 file:py-2 file:px-4
                                                          file:rounded file:border-0
                                                          file:text-sm file:font-semibold
                                                          file:bg-blue-50 file:text-blue-700
                                                          hover:file:bg-blue-100" required>
                                        </div>
                                        <button type="submit" name="upload_document" 
                                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                            Upload
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <?php if ($req['document_path']): ?>
                                    <div class="mt-2">
                                        <a href="../uploads/<?= htmlspecialchars($req['document_path']) ?>" 
                                           target="_blank" 
                                           class="text-blue-600 hover:underline">
                                            <i class="fas fa-file-alt mr-1"></i> View Uploaded Document
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>