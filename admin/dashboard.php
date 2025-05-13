<?php 
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!is_logged_in() || !is_admin()) {
    redirect('../login.php');
}

// Get stats for dashboard
$total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE user_role = 'student'")->fetchColumn();
$pending_submissions = $pdo->query("SELECT COUNT(*) FROM student_documents WHERE status = 'pending'")->fetchColumn();
$completed_clearances = $pdo->query("SELECT COUNT(*) FROM clearance_status WHERE is_complete = TRUE")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - University Clearance System</title>
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
                        <a href="dashboard.php" class="flex items-center space-x-2 px-4 py-2 bg-blue-700 rounded">
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
            <h1 class="text-3xl font-bold mb-6">Admin Dashboard</h1>
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500">Total Students</p>
                            <h3 class="text-2xl font-bold"><?= $total_students ?></h3>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500">Pending Submissions</p>
                            <h3 class="text-2xl font-bold"><?= $pending_submissions ?></h3>
                        </div>
                        <div class="bg-yellow-100 p-3 rounded-full">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500">Completed Clearances</p>
                            <h3 class="text-2xl font-bold"><?= $completed_clearances ?></h3>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activities -->
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-4">Recent Activities</h2>
                <div class="space-y-4">
                    <?php
                    $activities = $pdo->query("
                        SELECT 'document' as type, u.full_name, sd.uploaded_at 
                        FROM student_documents sd
                        JOIN users u ON sd.student_id = u.id
                        ORDER BY sd.uploaded_at DESC
                        LIMIT 5
                    ")->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($activities)): ?>
                        <p class="text-gray-500">No recent activities</p>
                    <?php else: ?>
                        <?php foreach ($activities as $activity): ?>
                            <div class="flex items-center space-x-4 border-b pb-2">
                                <div class="bg-blue-100 p-2 rounded-full">
                                    <i class="fas fa-file-upload text-blue-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium"><?= htmlspecialchars($activity['full_name']) ?> uploaded a document</p>
                                    <p class="text-sm text-gray-500"><?= date('M d, Y H:i', strtotime($activity['uploaded_at'])) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>