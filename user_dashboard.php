<?php
session_start();

// Security check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// CSRF protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// DB connection
require_once 'baby_capstone_connection.php'; // contains $pdo



// Count total complaints for current user
$stmt = $pdo->prepare("SELECT COUNT(*) AS total_complaints FROM complaints WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$totalComplaints = $stmt->fetch(PDO::FETCH_ASSOC)['total_complaints'];

// Count total requests for current user
$stmt = $pdo->prepare("SELECT COUNT(*) AS total_requests FROM requests WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$totalRequests = $stmt->fetch(PDO::FETCH_ASSOC)['total_requests'];

// Fetch complaints data for current user
$stmt = $pdo->prepare("
    SELECT id, type, name, phone, address, details, attachments, status, created_at 
    FROM complaints 
    WHERE user_id = :user_id 
    ORDER BY created_at DESC
");
$stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->execute();
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch request data for current user
$stmt2 = $pdo->prepare("
    SELECT id, user_id, type, name, phone, details, attachments, status, created_at 
    FROM requests 
    WHERE user_id = :user_id 
    ORDER BY created_at DESC
");
$stmt2->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
$stmt2->execute();
$requests = $stmt2->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
        }
        
        .sidebar {
            transition: all 0.3s ease;
        }
        
        .card {
            transition: all 0.3s ease;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .card-header {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .status-in_progress {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        
        .status-resolved {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .dashboard-card {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .dashboard-card-header {
            background-color: #f9fafb;
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .dashboard-card-body {
            flex: 1;
            overflow-y: auto;
            max-height: calc(100vh - 300px);
            padding: 1rem;
        }
        
        .submission-item {
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            transition: all 0.2s ease;
        }
        
        .submission-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .fade-out {
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .action-button {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            transition: all 0.2s ease;
        }
        
        .action-button:hover {
            transform: translateY(-1px);
        }
        
        .action-button-view {
            color: #2563eb;
            background-color: #eff6ff;
        }
        
        .action-button-edit {
            color: #d97706;
            background-color: #fffbeb;
        }
        
        .action-button-delete {
            color: #dc2626;
            background-color: #fee2e2;
        }
        
        .summary-card {
            border-radius: 0.5rem;
            padding: 1.25rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }
        
        .summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .summary-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .search-container {
            position: relative;
            width: 100%;
        }
        
        .search-container input {
            padding-left: 2.5rem;
            width: 100%;
        }
        
        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
<div class="flex h-screen overflow-hidden">

    <?php include 'sidebar.php' ?>
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <?php include 'header.php' ?>

        <!-- Dashboard Content -->
        <main class="p-4 md:p-6">
            <!-- Welcome Section -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-800 mb-2">Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?>!</h1>
                <p class="text-gray-600">Here's an overview of your submissions to the Barangay System.</p>
            </div>
            
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- Complaints Summary -->
                <div class="summary-card bg-gradient-to-r from-red-50 to-red-100">
                    <div>
                        <h3 class="text-sm font-medium text-red-700 mb-1">Total Complaints</h3>
                        <p class="text-3xl font-bold text-red-900"><?= $totalComplaints ?></p>
                        <p class="text-xs text-red-600 mt-1">
                            <i class="fas fa-clock mr-1"></i> Last updated: <?= date('M d, Y, h:i A') ?>
                        </p>
                    </div>
                    <div class="summary-icon bg-red-200 text-red-600">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                </div>

                <!-- Requests Summary -->
                <div class="summary-card bg-gradient-to-r from-blue-50 to-blue-100">
                    <div>
                        <h3 class="text-sm font-medium text-blue-700 mb-1">Total Requests</h3>
                        <p class="text-3xl font-bold text-blue-900"><?= $totalRequests ?></p>
                        <p class="text-xs text-blue-600 mt-1">
                            <i class="fas fa-clock mr-1"></i> Last updated: <?= date('M d, Y, h:i A') ?>
                        </p>
                    </div>
                    <div class="summary-icon bg-blue-200 text-blue-600">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                <div class="flex flex-col md:flex-row md:items-center space-y-4 md:space-y-0 md:space-x-4">
                    <div class="search-container flex-grow">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="searchInput" 
                               class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Search submissions...">
                    </div>
                    <div class="flex space-x-4">
                        <select id="statusFilter" 
                                class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                        </select>
                        <button onclick="searchSubmissions()" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            <i class="fas fa-filter mr-2"></i> Filter
                        </button>
                    </div>
                </div>
            </div>

            <!-- Main Dashboard Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 dashboard-grid">
                <!-- Complaints Column -->
                <div class="card dashboard-card bg-white shadow-sm">
                    <div class="dashboard-card-header">
                        <h2 class="font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-exclamation-circle text-red-500 mr-2"></i> My Complaints
                        </h2>
                        <a href="submit_complaint.php" class="text-sm text-blue-600 hover:text-blue-800 flex items-center">
                            <i class="fas fa-plus-circle mr-1"></i> New Complaint
                        </a>
                    </div>
                    <div class="dashboard-card-body" id="complaintsContainer">
                        <?php if (count($complaints) > 0): ?>
                            <?php foreach ($complaints as $row): ?>
                                <div class="submission-item bg-white border border-gray-200 p-4 shadow-sm">
                                    <div class="flex justify-between items-start mb-3">
                                        <h4 class="font-medium text-gray-800"><?= htmlspecialchars($row['type']) ?></h4>
                                        <span class="status-badge <?php 
                                            switch(strtolower($row['status'] ?? 'pending')) {
                                                case 'pending': echo 'status-pending'; break;
                                                case 'in_progress': echo 'status-in_progress'; break;
                                                case 'resolved': echo 'status-resolved'; break;
                                                default: echo 'status-pending';
                                            } ?>">
                                            <i class="fas <?php 
                                                switch(strtolower($row['status'] ?? 'pending')) {
                                                    case 'pending': echo 'fa-clock'; break;
                                                    case 'in_progress': echo 'fa-spinner'; break;
                                                    case 'resolved': echo 'fa-check-circle'; break;
                                                    default: echo 'fa-clock';
                                                } ?> mr-1"></i>
                                            <?= htmlspecialchars(ucfirst($row['status'] ?? 'Pending')) ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-3 line-clamp-2"><?= htmlspecialchars($row['details']) ?></p>
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-500">
                                            <i class="far fa-calendar-alt mr-1"></i> <?= date('M d, Y', strtotime($row['created_at'])) ?>
                                        </span>
                                        <div class="flex space-x-2">
                                            <button onclick="openViewModal('<?= htmlspecialchars(addslashes(json_encode($row))) ?>')" 
                                                    class="action-button action-button-view">
                                                <i class="fas fa-eye mr-1"></i> View
                                            </button>
                                            <button onclick="openEditModal('<?= htmlspecialchars(addslashes(json_encode($row))) ?>')" 
                                                    class="action-button action-button-edit">
                                                <i class="fas fa-edit mr-1"></i> Edit
                                            </button>
                                            <button onclick="openDeleteModal(<?= $row['id'] ?>, 'complaint')" 
                                                    class="action-button action-button-delete">
                                                <i class="fas fa-trash mr-1"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-exclamation-circle text-4xl mb-3 text-gray-300"></i>
                                <p class="text-gray-600 mb-4">No complaints found</p>
                                <a href="submit_complaint.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 inline-flex items-center">
                                    <i class="fas fa-plus-circle mr-2"></i> Submit a Complaint
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Requests Column -->
                <div class="card dashboard-card bg-white shadow-sm">
                    <div class="dashboard-card-header">
                        <h2 class="font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-file-alt text-blue-500 mr-2"></i> My Requests
                        </h2>
                        <a href="submit_request.php" class="text-sm text-blue-600 hover:text-blue-800 flex items-center">
                            <i class="fas fa-plus-circle mr-1"></i> New Request
                        </a>
                    </div>
                    <div class="dashboard-card-body" id="requestsContainer">
                        <?php if (count($requests) > 0): ?>
                            <?php foreach ($requests as $row): ?>
                                <div class="submission-item bg-white border border-gray-200 p-4 shadow-sm">
                                    <div class="flex justify-between items-start mb-3">
                                        <h4 class="font-medium text-gray-800"><?= htmlspecialchars($row['type']) ?></h4>
                                        <span class="status-badge <?php 
                                            switch(strtolower($row['status'] ?? 'pending')) {
                                                case 'pending': echo 'status-pending'; break;
                                                case 'in_progress': echo 'status-in_progress'; break;
                                                case 'resolved': echo 'status-resolved'; break;
                                                default: echo 'status-pending';
                                            } ?>">
                                            <i class="fas <?php 
                                                switch(strtolower($row['status'] ?? 'pending')) {
                                                    case 'pending': echo 'fa-clock'; break;
                                                    case 'in_progress': echo 'fa-spinner'; break;
                                                    case 'resolved': echo 'fa-check-circle'; break;
                                                    default: echo 'fa-clock';
                                                } ?> mr-1"></i>
                                            <?= htmlspecialchars(ucfirst($row['status'] ?? 'Pending')) ?>
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-3 line-clamp-2"><?= htmlspecialchars($row['details']) ?></p>
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-500">
                                            <i class="far fa-calendar-alt mr-1"></i> <?= date('M d, Y', strtotime($row['created_at'])) ?>
                                        </span>
                                        <div class="flex space-x-2">
                                            <button onclick="openViewModal('<?= htmlspecialchars(addslashes(json_encode($row))) ?>')" 
                                                    class="action-button action-button-view">
                                                <i class="fas fa-eye mr-1"></i> View
                                            </button>
                                            <button onclick="openEditModal('<?= htmlspecialchars(addslashes(json_encode(array_merge($row, ['form_type' => 'request']))) ) ?>')" 
                                                    class="action-button action-button-edit">
                                                <i class="fas fa-edit mr-1"></i> Edit
                                            </button>
                                            <button onclick="openDeleteModal(<?= $row['id'] ?>, 'request')" 
                                                    class="action-button action-button-delete">
                                                <i class="fas fa-trash mr-1"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-8 text-gray-500">
                                <i class="fas fa-file-alt text-4xl mb-3 text-gray-300"></i>
                                <p class="text-gray-600 mb-4">No requests found</p>
                                <a href="submit_request.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 inline-flex items-center">
                                    <i class="fas fa-plus-circle mr-2"></i> Submit a Request
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- View Modal -->
<div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
        <h2 class="text-lg font-semibold mb-4">Submission Details</h2>
        <div id="viewContent" class="text-sm space-y-2"></div>
        <div class="text-right mt-4">
            <button onclick="closeModal('viewModal')" class="bg-gray-300 text-gray-800 px-4 py-2 rounded">Close</button>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="modal" class="fixed inset-0 flex items-center justify-center hidden bg-black bg-opacity-50 z-50">
  <div class="bg-white p-6 rounded-lg w-96 shadow-xl">
    <h2 class="text-lg font-bold mb-3" id="modalTitle">Modal Title</h2>
    <form id="editForm" method="POST" action="update_submission.php">
      <input type="hidden" name="id" id="editId">
      <input type="hidden" name="form_type" id="editFormType">
      
      <label class="block mb-2">Type:</label>
      <input type="text" name="type" id="editTypeField" class="w-full mb-3 p-2 border rounded" required>
      
      <label class="block mb-2">Details:</label>
      <textarea name="details" id="editDetailsField" rows="4" class="w-full p-2 border rounded" required></textarea>
      
      <div class="flex justify-end mt-4">
        <button type="button" onclick="closeModal('modal')" class="mr-2 px-4 py-2 bg-gray-300 rounded">Cancel</button>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
      </div>
    </form>
  </div>
</div>


<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-sm">
        <h2 class="text-lg font-semibold mb-4 text-red-600">Confirm Delete</h2>
        <p class="text-sm mb-4">Are you sure you want to delete this submission?</p>
        <form method="POST" action="delete_submission.php">
            <input type="hidden" name="id" id="deleteId">
            <input type="hidden" name="category" id="deleteCategory">
            <div class="text-right">
                <button type="button" onclick="closeModal('deleteModal')" class="bg-gray-300 text-gray-800 px-3 py-1 rounded mr-2">Cancel</button>
                <button type="submit" class="bg-red-600 text-white px-3 py-1 rounded">Delete</button>
            </div>
        </form>
    </div>
</div>

        </main>
    </div>
</div>

<script>
// CSRF Token
const csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token']) ?>';

// Search functionality
function searchSubmissions() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    
    // Filter complaints
    const complaintCards = document.querySelectorAll('#complaintsContainer > div');
    complaintCards.forEach(card => {
        const type = card.querySelector('h4').textContent.toLowerCase();
        const details = card.querySelector('p').textContent.toLowerCase();
        const status = card.querySelector('.rounded-full').textContent.toLowerCase();
        
        const matchesSearch = type.includes(searchTerm) || details.includes(searchTerm);
        const matchesStatus = !statusFilter || status === statusFilter;
        
        card.style.display = matchesSearch && matchesStatus ? '' : 'none';
    });

    // Filter requests
    const requestCards = document.querySelectorAll('#requestsContainer > div');
    requestCards.forEach(card => {
        const type = card.querySelector('h4').textContent.toLowerCase();
        const details = card.querySelector('p').textContent.toLowerCase();
        const status = card.querySelector('.rounded-full').textContent.toLowerCase();
        
        const matchesSearch = type.includes(searchTerm) || details.includes(searchTerm);
        const matchesStatus = !statusFilter || status === statusFilter;
        
        card.style.display = matchesSearch && matchesStatus ? '' : 'none';
    });
}

// View modal with loading state
function openViewModal(dataJson) {
    const data = JSON.parse(dataJson);
    const modal = document.getElementById('viewModal');
    const content = document.getElementById('viewContent');
    
    // Show loading state
    modal.classList.remove('hidden');
    content.innerHTML = '<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>';
    
    // Load content after a small delay for better UX
    setTimeout(() => {
        let html = '';
        for (const [key, value] of Object.entries(data)) {
            html += `<div class="mb-2">
                        <strong class="capitalize">${key}:</strong> 
                        <span>${value}</span>
                     </div>`;
        }
        content.innerHTML = html;
    }, 300);
}

// Edit modal with form validation
function openEditModal(dataJson) {
    const data = JSON.parse(dataJson);
    document.getElementById('editId').value = data.id;
    document.getElementById('editTypeField').value = data.type;
    document.getElementById('editDetailsField').value = data.details;
    document.getElementById('editFormType').value = data.form_type || 'complaint';
    
    const modal = document.getElementById('modal');
    modal.classList.remove('hidden');
    
    // Add form validation
    const form = document.getElementById('editForm');
    form.addEventListener('submit', function(e) {
        const type = document.getElementById('editTypeField').value.trim();
        const details = document.getElementById('editDetailsField').value.trim();
        
        if (!type || !details) {
            e.preventDefault();
            alert('Please fill in all required fields');
            return;
        }
        
        // Add CSRF token
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);
    });
}

// Delete modal with confirmation
function openDeleteModal(id, category) {
    const modal = document.getElementById('deleteModal');
    const form = modal.querySelector('form');
    
    document.getElementById('deleteId').value = id;
    document.getElementById('deleteCategory').value = category;
    
    // Add CSRF token
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = csrfToken;
    form.appendChild(csrfInput);
    
    modal.classList.remove('hidden');
}

// Close modal with fade animation
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.add('fade-out');
    
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('fade-out');
    }, 300);
}

// Sidebar toggle
const sidebarToggle = document.getElementById('sidebarToggle');
if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
        document.querySelector('.sidebar').classList.toggle('hidden');
        document.querySelector('.flex-1').classList.toggle('w-full');
    });
}

// Add tooltips
const tooltips = document.querySelectorAll('.has-tooltip');
if (tooltips) {
    tooltips.forEach(tooltip => {
        tooltip.addEventListener('mouseenter', () => {
            const content = tooltip.getAttribute('data-tooltip');
            const tooltipElement = document.createElement('div');
            tooltipElement.className = 'absolute z-10 px-2 py-1 bg-gray-800 text-white rounded text-xs';
            tooltipElement.textContent = content;
            tooltip.appendChild(tooltipElement);
        });
        
        tooltip.addEventListener('mouseleave', () => {
            const tooltipElement = tooltip.querySelector('.absolute');
            if (tooltipElement) {
                tooltipElement.remove();
            }
        });
    });
}

// Initialize tooltips
const initializeTooltips = () => {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(tooltip => {
        tooltip.classList.add('has-tooltip');
    });
};

// Run initialization functions
initializeTooltips();
</script>

</body>
</html>
