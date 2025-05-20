<?php
// Include admin authentication check
require_once 'auth_check.php';

// Include database connection
require_once '../baby_capstone_connection.php';

// CSRF protection
if (!isset($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$errors = [];
$success = false;
$sms_demo = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['admin_csrf_token']) {
        $errors[] = "Invalid or expired form submission.";
    } else {
        $type = $_POST['type'] ?? '';
        $name = $_POST['name'] ?? '';
        $contact = $_POST['contact'] ?? '';
        $details = $_POST['details'] ?? '';
        $address = $_POST['address'] ?? '';
        $attachments = $_FILES['attachments'] ?? null;

        if (empty($type)) {
            $errors[] = "Please select a type.";
        }
        if (empty($name)) {
            $errors[] = "Name is required.";
        }
        if (empty($contact)) {
            $errors[] = "Contact number is required.";
        } elseif (!preg_match('/^\d{10}$/', $contact)) {
            $errors[] = "Contact number must be 10 digits.";
        }
        if (empty($details)) {
            $errors[] = "Details are required.";
        }

        if (empty($errors)) {
            try {
                // Determine which table to insert into based on type
                if ($type === 'Complaint') {
                    $stmt = $pdo->prepare("INSERT INTO complaints (user_id, type, name, phone, address, details, attachments, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                } else {
                    $stmt = $pdo->prepare("INSERT INTO requests (user_id, type, name, phone, details, attachments, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())");
                }
                
                $stmt->execute([
                    $_SESSION['admin_id'],
                    $type,
                    $name,
                    $contact,
                    $type === 'Complaint' ? $address : $details,
                    $type === 'Complaint' ? $details : null,
                    $attachments ? $attachments['tmp_name'] : null
                ]);
                
                // Simulate SMS notification
                $message = "Your $type has been submitted successfully and is being processed. Reference #: " . $pdo->lastInsertId();
                $sms_demo = "SMS would be sent to: +63$contact\nMessage: $message";
                
                $success = true;
                // Reset form fields
                $_POST = [];
                // Generate new CSRF token
                $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
            } catch (PDOException $e) {
                $errors[] = "Error submitting request: " . $e->getMessage();
            }
        }
    }
}

// Fetch data from database with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get total counts
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalComplaints = $pdo->query("SELECT COUNT(*) FROM complaints")->fetchColumn();
$totalRequests = $pdo->query("SELECT COUNT(*) FROM requests")->fetchColumn();
$totalResolved = $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'completed' OR status = 'resolved'")->fetchColumn();
$totalResolved += $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'resolved'")->fetchColumn();

// Fetch recent activities with pagination
$users = $pdo->query("SELECT id, full_name, email, created_at, phone, address, profile_pic FROM users ORDER BY created_at DESC LIMIT $offset, $perPage")->fetchAll(PDO::FETCH_ASSOC);
$requests = $pdo->query("SELECT id, user_id, type, name, phone, details, attachments, status, created_at FROM requests ORDER BY created_at DESC LIMIT $offset, $perPage")->fetchAll(PDO::FETCH_ASSOC);
$complaints = $pdo->query("SELECT id, type, name, phone, address, details, attachments, created_at, user_id, status FROM complaints ORDER BY created_at DESC LIMIT $offset, $perPage")->fetchAll(PDO::FETCH_ASSOC);

// Combine requests and complaints for the activity feed
$activities = [];
foreach ($complaints as $complaint) {
    $activities[] = [
        'type' => 'complaint',
        'data' => $complaint,
        'date' => $complaint['created_at']
    ];
}

foreach ($requests as $request) {
    $activities[] = [
        'type' => 'request',
        'data' => $request,
        'date' => $request['created_at']
    ];
}

// Sort activities by date (newest first)
usort($activities, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Limit to the most recent activities
$recentActivities = array_slice($activities, 0, 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Complaint and Request System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
        }
        .sidebar {
            transition: all 0.3s ease;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        .animate-pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
            100% {
                opacity: 1;
            }
        }
        
        /* Enhanced UI Styles */
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .stat-card {
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }
        
        .stat-card-blue {
            border-left-color: #3b82f6;
        }
        
        .stat-card-green {
            border-left-color: #10b981;
        }
        
        .stat-card-purple {
            border-left-color: #8b5cf6;
        }
        
        .stat-card-yellow {
            border-left-color: #f59e0b;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-1px);
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Activity Feed Animations */
        .activity-item {
            transition: all 0.2s ease;
        }
        
        .activity-item:hover {
            background-color: #f8fafc;
        }
        
        /* Form Enhancements */
        .form-input:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php' ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Top Navigation -->
            <?php include 'header.php' ?>

            <!-- Dashboard Content -->
            <main class="p-4 md:p-6">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow p-4 stat-card stat-card-blue card-hover">
                        <div class="flex items-center" onclick="window.location.href='complaint.php'" style="cursor: pointer;">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                                <i class="fas fa-exclamation-circle text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Total Complaints</p>
                                <h3 class="text-2xl font-bold"><?php echo $totalComplaints; ?></h3>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-gray-100">
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-green-500 font-medium"><i class="fas fa-arrow-up mr-1"></i> 12% from last month</span>
                                <a href="complaints.php" class="text-xs text-blue-600 hover:underline">View all</a>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4 stat-card stat-card-green card-hover">
                        <div class="flex items-center" onclick="window.location.href='request.php'" style="cursor: pointer;">
                            <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                                <i class="fas fa-hand-paper text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Total Requests</p>
                                <h3 class="text-2xl font-bold"><?php echo $totalRequests; ?></h3>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-gray-100">
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-red-500 font-medium"><i class="fas fa-arrow-down mr-1"></i> 5% from last month</span>
                                <a href="requests.php" class="text-xs text-blue-600 hover:underline">View all</a>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4 stat-card stat-card-purple card-hover">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                                <i class="fas fa-check-circle text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Resolved Cases</p>
                                <h3 class="text-2xl font-bold"><?php echo $totalResolved; ?></h3>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-gray-100">
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-green-500 font-medium"><i class="fas fa-arrow-up mr-1"></i> 8% from last month</span>
                                <a href="#" class="text-xs text-blue-600 hover:underline">View details</a>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-4 stat-card stat-card-yellow card-hover">
                        <div class="flex items-center" onclick="window.location.href='residents.php'" style="cursor: pointer;">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                                <i class="fas fa-users text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Total Users</p>
                                <h3 class="text-2xl font-bold"><?php echo $totalUsers; ?></h3>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-gray-100">
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-green-500 font-medium"><i class="fas fa-arrow-up mr-1"></i> 15% from last month</span>
                                <a href="users.php" class="text-xs text-blue-600 hover:underline">View all</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Analytics Chart -->
                <div class="bg-white rounded-lg shadow mb-6 p-4">
                    <h2 class="font-semibold text-gray-800 mb-4">Monthly Activity Overview</h2>
                    <div class="h-64">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>

                <!-- Recent Activities and Complaint Form -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Recent Activities -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-lg shadow overflow-hidden card-hover">
                            <div class="px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                                <h2 class="font-semibold text-gray-800">Recent Activities</h2>
                                <div class="flex space-x-2">
                                    <button class="text-xs px-2 py-1 rounded bg-blue-100 text-blue-600 hover:bg-blue-200 transition-colors" id="filter-all">All</button>
                                    <button class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-600 hover:bg-blue-100 hover:text-blue-600 transition-colors" id="filter-complaints">Complaints</button>
                                    <button class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-600 hover:bg-blue-100 hover:text-blue-600 transition-colors" id="filter-requests">Requests</button>
                                </div>
                            </div>
                            <div class="divide-y divide-gray-200 max-h-96 overflow-y-auto" id="activity-feed">
                            <?php foreach ($recentActivities as $activity): ?>
                                <?php 
                                    $data = $activity['data'];
                                    $isComplaint = $activity['type'] === 'complaint';
                                    $status = $data['status'] ?? 'pending';
                                    $statusClass = '';
                                    $statusIcon = '';
                                    
                                    switch(strtolower($status)) {
                                        case 'pending':
                                            $statusClass = 'text-yellow-600 bg-yellow-100';
                                            $statusIcon = 'clock';
                                            break;
                                        case 'in progress':
                                            $statusClass = 'text-blue-600 bg-blue-100';
                                            $statusIcon = 'spinner';
                                            break;
                                        case 'resolved':
                                        case 'completed':
                                            $statusClass = 'text-green-600 bg-green-100';
                                            $statusIcon = 'check-circle';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'text-red-600 bg-red-100';
                                            $statusIcon = 'times-circle';
                                            break;
                                        default:
                                            $statusClass = 'text-gray-600 bg-gray-100';
                                            $statusIcon = 'question-circle';
                                    }
                                ?>
                                <div class="p-4 hover:bg-gray-50 activity-item <?php echo $isComplaint ? 'complaint-item' : 'request-item'; ?>">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full <?php echo $isComplaint ? 'bg-blue-100 text-blue-600' : 'bg-green-100 text-green-600'; ?> flex items-center justify-center">
                                            <i class="fas <?php echo $isComplaint ? 'fa-exclamation-circle' : 'fa-hand-paper'; ?>"></i>
                                        </div>
                                        <div class="ml-4 flex-grow">
                                            <div class="flex justify-between">
                                                <p class="text-sm font-medium text-gray-900">
                                                    New <?php echo $isComplaint ? 'Complaint' : 'Request'; ?> #<?php echo $data['id']; ?>
                                                </p>
                                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                                                    <i class="fas fa-<?php echo $statusIcon; ?> mr-1"></i>
                                                    <?php echo ucfirst($status); ?>
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($data['name']); ?> submitted a <?php echo htmlspecialchars($data['type']); ?>.</p>
                                            <div class="mt-1 flex items-center text-xs text-gray-500">
                                                <span><?php echo date("F j, Y, g:i a", strtotime($data['created_at'])); ?></span>
                                                <span class="mx-1">•</span>
                                                <a href="#" class="text-blue-600 hover:underline">View details</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                            <div class="px-4 py-3 bg-gray-50 flex justify-between items-center">
                                <div class="text-xs text-gray-500">
                                    Showing <span id="showing-count"><?php echo count($recentActivities); ?></span> of <?php echo $totalComplaints + $totalRequests; ?> activities
                                </div>
                                <a href="activities.php" class="text-sm font-medium text-blue-600 hover:text-blue-500">View all activities</a>
                            </div>
                        </div>
                    </div>

                    <!-- Complaint/Request Form -->
                    <div>
                        <div class="bg-white rounded-lg shadow overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-200">
                            </div>
                        </div>
                        <?php foreach ($requests as $request): ?>
                        <div class="p-4 hover:bg-gray-50">
                            <div class="flex items-start">
                                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-green-100 flex items-center justify-center text-green-600">
                                    <i class="fas fa-hand-paper"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-900">New Request Submitted</p>
                                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($request['name']); ?> submitted a <?php echo htmlspecialchars($request['type']); ?>.</p>
                                    <div class="mt-1 flex items-center text-xs text-gray-500">
                                        <span><?php echo date("F j, Y, g:i a", strtotime($request['created_at'])); ?></span>
                                        <span class="mx-1">•</span>
                                        <span class="text-green-600"><?php echo htmlspecialchars($request['status']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div><?php endforeach; ?>
                    </div>
                    <div class="px-4 py-3 bg-gray-50 text-right">
                        <a href="#" class="text-sm font-medium text-blue-600 hover:text-blue-500">View all activities</a>
                    </div>
                </div>
            </div>

           
            </main>
        </div>
    </div>

    <script>
        // Toggle mobile sidebar
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('hidden');
        });

        // Toggle notification dropdown
        document.getElementById('notificationBtn').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('notificationDropdown').classList.toggle('hidden');
            document.getElementById('userMenuDropdown').classList.add('hidden');
        });

        // Toggle user menu dropdown
        document.getElementById('userMenuBtn').addEventListener('click', function(e) {
            e.stopPropagation();
            document.getElementById('userMenuDropdown').classList.toggle('hidden');
            document.getElementById('notificationDropdown').classList.add('hidden');
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            document.getElementById('notificationDropdown').classList.add('hidden');
            document.getElementById('userMenuDropdown').classList.add('hidden');
        });

        // Prevent dropdown from closing when clicking inside
        document.getElementById('notificationDropdown').addEventListener('click', function(e) {
            e.stopPropagation();
        });

        document.getElementById('userMenuDropdown').addEventListener('click', function(e) {
            e.stopPropagation();
        });

        // Activity Feed Filtering
        document.getElementById('filter-all').addEventListener('click', function() {
            filterActivities('all');
            setActiveFilter(this);
        });
        
        document.getElementById('filter-complaints').addEventListener('click', function() {
            filterActivities('complaint');
            setActiveFilter(this);
        });
        
        document.getElementById('filter-requests').addEventListener('click', function() {
            filterActivities('request');
            setActiveFilter(this);
        });
        
        function filterActivities(type) {
            const items = document.querySelectorAll('.activity-item');
            let visibleCount = 0;
            
            items.forEach(item => {
                if (type === 'all') {
                    item.style.display = 'block';
                    visibleCount++;
                } else if (type === 'complaint' && item.classList.contains('complaint-item')) {
                    item.style.display = 'block';
                    visibleCount++;
                } else if (type === 'request' && item.classList.contains('request-item')) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            document.getElementById('showing-count').textContent = visibleCount;
        }
        
        function setActiveFilter(button) {
            const filters = document.querySelectorAll('#filter-all, #filter-complaints, #filter-requests');
            filters.forEach(filter => {
                filter.classList.remove('bg-blue-100', 'text-blue-600');
                filter.classList.add('bg-gray-100', 'text-gray-600');
            });
            
            button.classList.remove('bg-gray-100', 'text-gray-600');
            button.classList.add('bg-blue-100', 'text-blue-600');
        }

        // Analytics Chart
        const ctx = document.getElementById('activityChart').getContext('2d');
        const activityChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['January', 'February', 'March', 'April', 'May', 'June'],
                datasets: [
                    {
                        label: 'Complaints',
                        data: [12, 19, 10, 15, 20, <?php echo $totalComplaints; ?>],
                        backgroundColor: 'rgba(59, 130, 246, 0.2)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                        pointRadius: 4
                    },
                    {
                        label: 'Requests',
                        data: [8, 15, 20, 12, 17, <?php echo $totalRequests; ?>],
                        backgroundColor: 'rgba(16, 185, 129, 0.2)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        pointBackgroundColor: 'rgba(16, 185, 129, 1)',
                        pointRadius: 4
                    },
                    {
                        label: 'Resolved',
                        data: [5, 10, 15, 8, 12, <?php echo $totalResolved; ?>],
                        backgroundColor: 'rgba(139, 92, 246, 0.2)',
                        borderColor: 'rgba(139, 92, 246, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        pointBackgroundColor: 'rgba(139, 92, 246, 1)',
                        pointRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(17, 24, 39, 0.9)',
                        titleFont: {
                            size: 13
                        },
                        bodyFont: {
                            size: 12
                        },
                        padding: 10,
                        cornerRadius: 4,
                        displayColors: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(243, 244, 246, 1)'
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            let isValid = true;
            const type = document.getElementById('type');
            const name = document.getElementById('name');
            const contact = document.getElementById('contact');
            const details = document.getElementById('details');
            
            // Reset error states
            [type, name, contact, details].forEach(field => {
                field.classList.remove('border-red-500');
                const errorElement = document.getElementById(`${field.id}-error`);
                if (errorElement) errorElement.remove();
            });
            
            // Validate fields
            if (!type.value) {
                type.classList.add('border-red-500');
                const errorElement = document.createElement('p');
                errorElement.id = 'type-error';
                errorElement.className = 'mt-1 text-sm text-red-600';
                errorElement.textContent = 'Please select a type';
                type.parentNode.appendChild(errorElement);
                isValid = false;
            }
            
            if (!name.value.trim()) {
                name.classList.add('border-red-500');
                const errorElement = document.createElement('p');
                errorElement.id = 'name-error';
                errorElement.className = 'mt-1 text-sm text-red-600';
                errorElement.textContent = 'Please enter your name';
                name.parentNode.appendChild(errorElement);
                isValid = false;
            }
            
            if (!contact.value.trim()) {
                contact.classList.add('border-red-500');
                const errorElement = document.createElement('p');
                errorElement.id = 'contact-error';
                errorElement.className = 'mt-1 text-sm text-red-600';
                errorElement.textContent = 'Please enter your contact number';
                contact.parentNode.appendChild(errorElement);
                isValid = false;
            } else if (!/^\d{10}$/.test(contact.value.trim())) {
                contact.classList.add('border-red-500');
                const errorElement = document.createElement('p');
                errorElement.id = 'contact-error';
                errorElement.className = 'mt-1 text-sm text-red-600';
                errorElement.textContent = 'Please enter a valid 10-digit contact number';
                contact.parentNode.appendChild(errorElement);
                isValid = false;
            }
            
            if (!details.value.trim()) {
                details.classList.add('border-red-500');
                const errorElement = document.createElement('p');
                errorElement.id = 'details-error';
                errorElement.className = 'mt-1 text-sm text-red-600';
                errorElement.textContent = 'Please enter details';
                details.parentNode.appendChild(errorElement);
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                // Scroll to first error
                const firstError = document.querySelector('.border-red-500');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
        
        // Enhance form inputs with animation
        const formInputs = document.querySelectorAll('input, textarea, select');
        formInputs.forEach(input => {
            input.classList.add('form-input', 'transition-all', 'duration-200');
            input.addEventListener('focus', function() {
                this.closest('.mb-4').classList.add('scale-105');
                this.classList.add('ring-2', 'ring-blue-300');
            });
            input.addEventListener('blur', function() {
                this.closest('.mb-4').classList.remove('scale-105');
                this.classList.remove('ring-2', 'ring-blue-300');
            });
        });
    </script>
</body>
</html>