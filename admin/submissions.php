<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!is_logged_in() || !is_admin()) {
    redirect('../login.php');
}

// Handle approval
if (isset($_GET['approve'])) {
    $student_id = $_GET['approve'];

    try {
        // Check if all documents are approved
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM student_documents 
            WHERE student_id = ? AND status != 'approved'
        ");
        $stmt->execute([$student_id]);
        $pending_count = $stmt->fetchColumn();

        if ($pending_count > 0) {
            $_SESSION['error'] = "Cannot complete clearance. Some documents are still pending or rejected.";
        } else {
            // Update clearance status
            // In your admin approval code (where you set is_complete = TRUE)
            $stmt = $pdo->prepare("
    INSERT INTO clearance_status (student_id, is_complete, completed_at, approved_by, approved_at) 
    VALUES (?, TRUE, NOW(), ?, NOW())
    ON DUPLICATE KEY UPDATE is_complete = TRUE, completed_at = NOW(), approved_by = ?, approved_at = NOW()
");
            $stmt->execute([$student_id, $_SESSION['user_id'], $_SESSION['user_id']]);

            $_SESSION['success'] = "Clearance completed successfully for student ID: $student_id";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error completing clearance: " . $e->getMessage();
    }

    redirect('submissions.php');
}

// Handle document approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_document'])) {
    $document_id = $_POST['document_id'];
    $status = $_POST['status'];
    $feedback = trim($_POST['feedback']);

    try {
        $stmt = $pdo->prepare("UPDATE student_documents SET status = ?, feedback = ? WHERE id = ?");
        $stmt->execute([$status, $feedback, $document_id]);

        $_SESSION['success'] = "Document status updated successfully";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating document: " . $e->getMessage();
    }

    redirect('submissions.php');
}

// Get all departments with their students
$departments = $pdo->query("
    SELECT d.id, d.name, 
           u.id as student_id, u.full_name, 
           cs.is_complete as clearance_status
    FROM departments d
    LEFT JOIN users u ON d.name = u.department AND u.user_role = 'student'
    LEFT JOIN clearance_status cs ON u.id = cs.student_id
    ORDER BY d.name, u.full_name
")->fetchAll(PDO::FETCH_ASSOC);

// Organize students by department
$departmentGroups = [];
foreach ($departments as $row) {
    $deptId = $row['id'];
    if (!isset($departmentGroups[$deptId])) {
        $departmentGroups[$deptId] = [
            'name' => $row['name'],
            'students' => []
        ];
    }

    if ($row['student_id']) {
        $departmentGroups[$deptId]['students'][$row['student_id']] = [
            'full_name' => $row['full_name'],
            'clearance_status' => $row['clearance_status']
        ];
    }
}

// Get all student documents
$documents = $pdo->query("
    SELECT sd.student_id, sd.id as document_id, sd.requirement_id, 
           sd.document_path, sd.status, sd.uploaded_at, sd.feedback,
           cr.document_name
    FROM student_documents sd
    JOIN clearance_requirements cr ON sd.requirement_id = cr.id
    ORDER BY sd.student_id, sd.uploaded_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Add documents to students
foreach ($documents as $doc) {
    foreach ($departmentGroups as &$dept) {
        if (isset($dept['students'][$doc['student_id']])) {
            if (!isset($dept['students'][$doc['student_id']]['documents'])) {
                $dept['students'][$doc['student_id']]['documents'] = [];
            }
            $dept['students'][$doc['student_id']]['documents'][] = [
                'id' => $doc['document_id'],
                'requirement_id' => $doc['requirement_id'],
                'document_name' => $doc['document_name'],
                'document_path' => $doc['document_path'],
                'status' => $doc['status'],
                'uploaded_at' => $doc['uploaded_at'],
                'feedback' => $doc['feedback']
            ];
        }
    }
}
unset($dept);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Submissions - University Clearance System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        function toggleDepartment(deptId) {
            const content = document.getElementById('dept-content-' + deptId);
            const icon = document.getElementById('dept-icon-' + deptId);
            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                icon.classList.remove('fa-chevron-right');
                icon.classList.add('fa-chevron-down');
            } else {
                content.classList.add('hidden');
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-right');
            }
        }

        function openModal(documentId, currentStatus, currentFeedback) {
            const modal = document.getElementById('documentModal');
            const form = document.getElementById('documentForm');
            document.getElementById('modalDocumentId').value = documentId;
            document.getElementById('feedback').value = currentFeedback;

            // Set the current status
            const statusRadios = form.elements['status'];
            for (let i = 0; i < statusRadios.length; i++) {
                if (statusRadios[i].value === currentStatus) {
                    statusRadios[i].checked = true;
                    break;
                }
            }

            modal.classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('documentModal').classList.add('hidden');
        }
    </script>
</head>

<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="bg-blue-800 text-white w-64 p-4">
            <h1 class="text-2xl font-bold mb-6">Admin Panel</h1>
            <nav>
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard.php" class="flex items-center space-x-2 px-4 py-2 hover:bg-blue-700 rounded">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="requirements.php" class="flex items-center space-x-2 px-4 py-2 hover:bg-blue-700 rounded">
                            <i class="fas fa-list-check"></i>
                            <span>Manage Requirements</span>
                        </a>
                    </li>
                    <li>
                        <a href="submissions.php" class="flex items-center space-x-2 px-4 py-2 bg-blue-700 rounded">
                            <i class="fas fa-file-upload"></i>
                            <span>Student Submissions</span>
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
        <div class="flex-1 p-8 overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold">Student Submissions</h1>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= $_SESSION['error'];
                    unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?= $_SESSION['success'];
                    unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <!-- Department Accordions -->
            <div class="space-y-4">
                <?php foreach ($departmentGroups as $deptId => $department): ?>
                    <div class="bg-white rounded-lg shadow">
                        <!-- Department Header -->
                        <div class="flex justify-between items-center p-4 cursor-pointer border-b"
                            onclick="toggleDepartment(<?= $deptId ?>)">
                            <h2 class="text-xl font-bold">
                                <?= htmlspecialchars($department['name']) ?>
                                <span class="text-sm font-normal text-gray-600 ml-2">
                                    (<?= count($department['students']) ?> students)
                                </span>
                            </h2>
                            <i id="dept-icon-<?= $deptId ?>" class="fas fa-chevron-right"></i>
                        </div>

                        <!-- Department Content -->
                        <div id="dept-content-<?= $deptId ?>" class="hidden p-4">
                            <?php if (empty($department['students'])): ?>
                                <p class="text-gray-500">No students in this department have submitted documents.</p>
                            <?php else: ?>
                                <div class="space-y-6">
                                    <?php foreach ($department['students'] as $studentId => $student): ?>
                                        <div class="border rounded-lg p-4">
                                            <div class="flex justify-between items-center mb-4">
                                                <div>
                                                    <h3 class="text-lg font-bold"><?= htmlspecialchars($student['full_name']) ?></h3>
                                                </div>
                                                <div>
                                                    <?php if ($student['clearance_status']): ?>
                                                        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                                                            Clearance Completed
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm font-medium">
                                                            Clearance Incomplete
                                                        </span>
                                                        <?php if (!empty($student['documents'])): ?>
                                                            <a href="submissions.php?approve=<?= $studentId ?>"
                                                                class="ml-2 bg-blue-600 text-white px-3 py-1 rounded text-sm font-medium hover:bg-blue-700"
                                                                onclick="return confirm('Complete clearance for <?= htmlspecialchars($student['full_name']) ?>?')">
                                                                Complete Clearance
                                                            </a>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <?php if (empty($student['documents'])): ?>
                                                <p class="text-gray-500">No documents submitted yet.</p>
                                            <?php else: ?>
                                                <div class="overflow-x-auto">
                                                    <table class="min-w-full bg-white">
                                                        <thead>
                                                            <tr>
                                                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-semibold text-gray-700">Document</th>
                                                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-semibold text-gray-700">Submitted</th>
                                                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-semibold text-gray-700">Status</th>
                                                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-semibold text-gray-700">Feedback</th>
                                                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-semibold text-gray-700">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($student['documents'] as $doc): ?>
                                                                <tr>
                                                                    <td class="py-2 px-4 border-b border-gray-200">
                                                                        <a href="../uploads/<?= htmlspecialchars($doc['document_path']) ?>"
                                                                            target="_blank"
                                                                            class="text-blue-600 hover:underline">
                                                                            <?= htmlspecialchars($doc['document_name']) ?>
                                                                        </a>
                                                                    </td>
                                                                    <td class="py-2 px-4 border-b border-gray-200">
                                                                        <?= date('M d, Y H:i', strtotime($doc['uploaded_at'])) ?>
                                                                    </td>
                                                                    <td class="py-2 px-4 border-b border-gray-200">
                                                                        <?php if ($doc['status'] === 'approved'): ?>
                                                                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">Approved</span>
                                                                        <?php elseif ($doc['status'] === 'rejected'): ?>
                                                                            <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs">Rejected</span>
                                                                        <?php else: ?>
                                                                            <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs">Pending</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td class="py-2 px-4 border-b border-gray-200">
                                                                        <?= htmlspecialchars($doc['feedback'] ?? 'N/A') ?>
                                                                    </td>
                                                                    <td class="py-2 px-4 border-b border-gray-200">
                                                                        <button onclick="openModal('<?= $doc['id'] ?>', '<?= $doc['status'] ?>', '<?= htmlspecialchars(addslashes($doc['feedback'] ?? '')) ?>')"
                                                                            class="text-blue-600 hover:text-blue-800">
                                                                            <i class="fas fa-edit"></i>
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Modal for updating document status -->
    <div id="documentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
            <h3 class="text-xl font-bold mb-4">Update Document Status</h3>
            <form method="POST" id="documentForm">
                <input type="hidden" name="document_id" id="modalDocumentId">
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Status</label>
                    <div class="flex space-x-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="status" value="approved" class="form-radio text-blue-600">
                            <span class="ml-2">Approved</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="status" value="rejected" class="form-radio text-red-600">
                            <span class="ml-2">Rejected</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="status" value="pending" class="form-radio text-yellow-600">
                            <span class="ml-2">Pending</span>
                        </label>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="feedback" class="block text-gray-700 mb-2">Feedback</label>
                    <textarea id="feedback" name="feedback" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"></textarea>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeModal()"
                        class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit" name="update_document"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>

</html>