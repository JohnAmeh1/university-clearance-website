<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!is_logged_in() || !is_student()) {
    redirect('../login.php');
}

// Get clearance status and form path if exists
$stmt = $pdo->prepare("
    SELECT cs.is_complete, cs.clearance_form_path 
    FROM clearance_status cs 
    WHERE cs.student_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$clearance_status = $stmt->fetch();
$is_complete = $clearance_status ? $clearance_status['is_complete'] : false;
$clearance_form_path = $clearance_status ? $clearance_status['clearance_form_path'] : null;

// Get pending documents count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM student_documents WHERE student_id = ? AND status = 'pending'");
$stmt->execute([$_SESSION['user_id']]);
$pending_count = $stmt->fetchColumn();

// Get requirements count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM clearance_requirements WHERE department_id = (SELECT id FROM departments WHERE name = ?)");
$stmt->execute([$_SESSION['department']]);
$requirements_count = $stmt->fetchColumn();

// Get submitted documents count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM student_documents WHERE student_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$submitted_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - University Clearance System</title>
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
                        <a href="dashboard.php" class="flex items-center space-x-2 px-4 py-2 bg-blue-700 rounded">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="clearance.php" class="flex items-center space-x-2 px-4 py-2 hover:bg-blue-700 rounded">
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
            <h1 class="text-3xl font-bold mb-6">Student Dashboard</h1>

            <!-- Welcome Message -->
            <div class="bg-white p-6 rounded-lg shadow mb-6">
                <h2 class="text-xl font-bold mb-2">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>!</h2>
                <p class="text-gray-600">Department: <?= htmlspecialchars($_SESSION['department']) ?></p>

                <div class="mt-4">
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

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500">Total Requirements</p>
                            <h3 class="text-2xl font-bold"><?= $requirements_count ?></h3>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-list-check text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500">Submitted Documents</p>
                            <h3 class="text-2xl font-bold"><?= $submitted_count ?></h3>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-file-upload text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500">Pending Approval</p>
                            <h3 class="text-2xl font-bold"><?= $pending_count ?></h3>
                        </div>
                        <div class="bg-yellow-100 p-3 rounded-full">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-4">Quick Actions</h2>
                <div class="flex space-x-4">
                    <a href="clearance.php" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                        <i class="fas fa-list-check mr-2"></i> Go to Clearance Checklist
                    </a>
                    <?php if ($is_complete): ?>
                        <a href="../download_clearance.php?student_id=<?= $_SESSION['user_id'] ?>"
                            class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition">
                            <i class="fas fa-file-download mr-2"></i> Download Approved Clearance Form
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>

</html>