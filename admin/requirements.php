<?php 
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!is_logged_in() || !is_admin()) {
    redirect('../login.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_requirement'])) {
    $department_id = $_POST['department_id'];
    $document_name = trim($_POST['document_name']);
    $description = trim($_POST['description']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO clearance_requirements (department_id, document_name, description, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$department_id, $document_name, $description, $_SESSION['user_id']]);
        
        $_SESSION['success'] = "Requirement added successfully";
        redirect('requirements.php');
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error adding requirement: " . $e->getMessage();
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM clearance_requirements WHERE id = ?");
        $stmt->execute([$id]);
        
        $_SESSION['success'] = "Requirement deleted successfully";
        redirect('requirements.php');
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting requirement: " . $e->getMessage();
    }
}

// Get all requirements with department names
$requirements = $pdo->query("
    SELECT cr.*, d.name as department_name 
    FROM clearance_requirements cr
    JOIN departments d ON cr.department_id = d.id
    ORDER BY d.name, cr.document_name
")->fetchAll(PDO::FETCH_ASSOC);

// Get all departments
$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Requirements - University Clearance System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                        <a href="requirements.php" class="flex items-center space-x-2 px-4 py-2 bg-blue-700 rounded">
                            <i class="fas fa-list-check"></i>
                            <span>Manage Requirements</span>
                        </a>
                    </li>
                    <li>
                        <a href="submissions.php" class="flex items-center space-x-2 px-4 py-2 hover:bg-blue-700 rounded">
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
        <div class="flex-1 p-8">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold">Manage Requirements</h1>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Add Requirement Form -->
            <div class="bg-white p-6 rounded-lg shadow mb-8">
                <h2 class="text-xl font-bold mb-4">Add New Requirement</h2>
                <form method="POST">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="department_id" class="block text-gray-700 mb-2">Department</label>
                            <select id="department_id" name="department_id" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="document_name" class="block text-gray-700 mb-2">Document Name</label>
                            <input type="text" id="document_name" name="document_name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="description" class="block text-gray-700 mb-2">Description (Optional)</label>
                        <textarea id="description" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500"></textarea>
                    </div>
                    <button type="submit" name="add_requirement" class="bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition">
                        Add Requirement
                    </button>
                </form>
            </div>
            
            <!-- Requirements List -->
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-4">Current Requirements</h2>
                
                <?php if (empty($requirements)): ?>
                    <p class="text-gray-500">No requirements added yet.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead>
                                <tr>
                                    <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-semibold text-gray-700">Department</th>
                                    <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-semibold text-gray-700">Document</th>
                                    <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-semibold text-gray-700">Description</th>
                                    <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-sm font-semibold text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requirements as $req): ?>
                                    <tr>
                                        <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($req['department_name']) ?></td>
                                        <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($req['document_name']) ?></td>
                                        <td class="py-2 px-4 border-b border-gray-200"><?= htmlspecialchars($req['description']) ?: 'N/A' ?></td>
                                        <td class="py-2 px-4 border-b border-gray-200">
                                            <a href="requirements.php?delete=<?= $req['id'] ?>" 
                                               class="text-red-600 hover:text-red-800" 
                                               onclick="return confirm('Are you sure you want to delete this requirement?')">
                                                <i class="fas fa-trash-alt"></i>
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
</body>
</html>