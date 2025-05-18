<?php
session_start();
require_once 'baby_capstone_connection.php';
require_once 'create_notification.php';

// Security check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize session variables for form data
$_SESSION['form_data'] = [
    'subject_type' => '',
    'subject' => '',
    'details' => '',
    'location' => '',
    'attachments' => []
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $subject_type = trim($_POST['subject_type'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $details = trim($_POST['details'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $created_at = date('Y-m-d H:i:s');
    
    // Save form data to session
    $_SESSION['form_data'] = [
        'subject_type' => $subject_type,
        'subject' => $subject,
        'details' => $details,
        'location' => $location,
        'attachments' => []
    ];

    // Validate
    $errors = [];
    
    // Only validate subject length if subject_type is 'Other'
    if ($subject_type === 'Other') {
        if (strlen($subject) < 10) {
            $errors[] = "Custom subject must be at least 10 characters long";
        }
    } else if (empty($subject_type)) {
        $errors[] = "Please select a subject type";
    }
    
    if (strlen($details) < 20) {
        $errors[] = "Details must be at least 20 characters long";
    }

    if (empty($errors)) {
        // Get user details
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        // Insert complaint
        $sql = "INSERT INTO complaints (user_id, type, name, phone, email, address, subject_type, subject, details, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_SESSION['user_id'],
            $subject_type,
            $user['full_name'],
            '', // phone will be empty for now
            '', // email will be empty for now
            '', // address will be empty for now
            $subject_type,
            $subject,
            $details
        ]);

        // Insert complaint
        $stmt->execute();
        $complaint_id = $pdo->lastInsertId();
        
        // Create notification for admin
        $message = "New complaint #" . $complaint_id . " submitted by " . $user['full_name'];
        createNotification('complaint', $message);

        // Clear form data
        $_SESSION['form_data'] = [
            'subject_type' => '',
            'subject' => '',
            'details' => '',
            'location' => '',
            'attachments' => []
        ];
        
        // Set success message
        $_SESSION['success'] = "Your complaint has been submitted successfully!";
        header("Location: submit_complaint.php?success=1");
        exit();
    }
    
    if (empty($location)) {
        $errors[] = "Location is required";
    }

    // File upload handling
    $uploadDir = 'uploads/';
    $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'application/msword'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    $attachments = [];

    if (!empty($_FILES['attachments']['name'][0])) {
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($_FILES['attachments']['tmp_name'] as $key => $tmpName) {
            $fileType = $_FILES['attachments']['type'][$key];
            $fileSize = $_FILES['attachments']['size'][$key];
            
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = "Invalid file type. Allowed types: JPG, PNG, PDF, DOC";
                continue;
            }
            
            if ($fileSize > $maxFileSize) {
                $errors[] = "File size exceeds 5MB limit";
                continue;
            }

            $fileName = basename($_FILES['attachments']['name'][$key]);
            $targetFile = $uploadDir . time() . '_' . $fileName;

            if (move_uploaded_file($tmpName, $targetFile)) {
                $attachments[] = $targetFile;
            }
        }
    }

    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        header("Location: submit_complaint.php?error=1");
        exit();
    }

    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert complaint
        $stmt = $pdo->prepare("
            INSERT INTO complaints (
                type, name, phone, email, address, subject_type, subject, details, attachments, 
                created_at, updated_at, user_id, status
            ) VALUES (
                :type, :name, :phone, :email, :address, :subject_type, :subject, :details, :attachments,
                :created_at, :updated_at, :user_id, :status
            )");

        $stmt->execute([
            ':type'        => 'Complaint',
            ':name'        => $_SESSION['full_name'] ?? 'Anonymous',
            ':phone'       => $_SESSION['phone'] ?? '',
            ':email'       => $_SESSION['email'] ?? '',
            ':address'     => $location,
            ':subject_type' => $subject_type,
            ':subject'     => $subject,
            ':details'     => $details,
            ':attachments' => !empty($attachments) ? json_encode($attachments) : null,
            ':created_at'  => $created_at,
            ':updated_at'  => $created_at,
            ':user_id'     => $_SESSION['user_id'] ?? null,
            ':status'      => 'pending'
        ]);

        // Commit transaction
        $pdo->commit();

        // Clear form data after successful submission
        $_SESSION['form_data'] = [
            'subject' => '',
            'details' => '',
            'location' => '',
            'attachments' => []
        ];

        $_SESSION['success'] = "Your complaint has been submitted successfully!";
        header("Location: submit_complaint.php");
        exit();
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        
        // Log the error
        error_log("Complaint submission error: " . $e->getMessage());
        
        // Provide a user-friendly error message
        $_SESSION['error'] = "There was an error submitting your complaint. Please try again later.";
        header("Location: submit_complaint.php");
        exit();
    }
}
?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Complaint</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans gradient-bg">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        <div class="flex-1 overflow-auto">
        <?php include 'header.php'; ?>
        <!-- Main Content -->
        <main class="flex-1 overflow-auto p-4 md:p-6">
            <div class="bg-white rounded-lg shadow-md p-6 mx-auto max-w-4xl">

                 <div>
                        <h1 class="text-2xl font-semibold text-gray-800 text-center">Submit a Complaint</h1>
                    </div>

                    <!-- Notification for success or error -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                            <strong class="font-bold">Success!</strong>
                            <span class="block sm:inline"><?= htmlspecialchars($_SESSION['success']) ?></span>
                            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                                <button class="text-green-500 font-bold" onclick="this.parentElement.parentElement.style.display='none';">&times;</button>
                            </span>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                            <strong class="font-bold">Error!</strong>
                            <span class="block sm:inline"><?= htmlspecialchars($_SESSION['error']) ?></span>
                            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                                <button class="text-red-500 font-bold" onclick="this.parentElement.parentElement.style.display='none';">&times;</button>
                            </span>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <form method="POST" action="submit_complaint.php" class="mt-8 space-y-6" enctype="multipart/form-data" id="complaintForm">
                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-700">Subject</label>
                            <div class="mt-1">
                                <select name="subject_type" id="subject_type" 
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                        onchange="toggleCustomSubject()">
                                    <option value="">Select a common subject</option>
                                    <option value="Noise Complaint">Noise Complaint (Loud music, karaoke, construction noise)</option>
                                    <option value="Boundary Dispute">Boundary Dispute (Property lines, lot encroachments)</option>
                                    <option value="Domestic Conflict">Domestic Conflict (Marital problems, family quarrels)</option>
                                    <option value="Vandalism">Vandalism (Property damage, destruction)</option>
                                    <option value="Physical Altercation">Physical Altercation (Fights, physical conflicts)</option>
                                    <option value="Trespassing">Trespassing (Unauthorized entry)</option>
                                    <option value="Animal Nuisance">Animal Nuisance (Stray or aggressive pets)</option>
                                    <option value="Public Disturbance">Public Disturbance (Drunken behavior, disorderly conduct)</option>
                                    <option value="Garbage/Sanitation">Garbage/Sanitation (Illegal dumping, blocked drainage)</option>
                                    <option value="Defamation">Defamation (Gossip, character defamation)</option>
                                    <option value="Other">Other (Please specify below)</option>
                                </select>
                            </div>
                            <div id="customSubjectDiv" class="mt-2" style="display: none;">
                                <input type="text" name="subject" id="subject" 
                                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                       placeholder="Please specify your subject"
                                       minlength="10" required>
                                <p class="text-xs text-gray-500 mt-1">Minimum 10 characters</p>
                                <div id="subjectCounter" class="text-xs text-gray-400 mt-1">0/100 characters</div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">
                                Select a common subject or choose "Other" to specify your own subject.
                            </p>
                        </div>

                        <div>
                            <label for="details" class="block text-sm font-medium text-gray-700">Details</label>
                            <textarea name="details" id="details" rows="5" 
                                      class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                      required
                                      minlength="20"><?= htmlspecialchars($_SESSION['form_data']['details'] ?? '') ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">Minimum 20 characters</p>
                            <div id="detailsCounter" class="text-xs text-gray-400 mt-1">0/500 characters</div>
                        </div>

                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
                            <input type="text" name="location" id="location" 
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                   value="<?= htmlspecialchars($_SESSION['form_data']['location'] ?? '') ?>"
                                   required>
                        </div>

                        <div>
                            <label for="attachments" class="block text-sm font-medium text-gray-700">Attachments (optional)</label>
                            <p class="text-xs text-gray-500 mt-1">
                                Allowed file types: JPG, PNG, PDF, DOC<br>
                                Maximum file size: 5MB<br>
                                Maximum 5 files
                            </p>
                            <input type="file" name="attachments[]" id="attachments" multiple 
                                   class="mt-2 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <div id="attachmentPreview" class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4"></div>
                        </div>

                        <div class="flex items-center justify-between">
                            <div>
                                <button type="button" onclick="saveDraft()" 
                                        class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Save Draft
                                </button>
                            </div>
                            <div>
                                <button type="submit" 
                                        class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 flex items-center justify-center">
                                    <span id="submitText">Submit Complaint</span>
                                    <span id="submitSpinner" class="ml-2 hidden">
                                        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </form>
                    <div class="mt-6 text-center">
                        <a href="user_dashboard.php" class="text-sm text-blue-600 hover:underline">Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Handle subject type selection
        const subjectTypeSelect = document.getElementById('subject_type');
        const customSubjectDiv = document.getElementById('customSubjectDiv');
        const subjectInput = document.getElementById('subject');
        const subjectCounter = document.getElementById('subjectCounter');

        function toggleCustomSubject() {
            const selectedValue = subjectTypeSelect.value;
            if (selectedValue === 'Other') {
                customSubjectDiv.style.display = 'block';
                subjectInput.required = true;
            } else {
                customSubjectDiv.style.display = 'none';
                subjectInput.required = false;
            }
        }

        // Character counter for subject
        subjectInput.addEventListener('input', () => {
            const length = subjectInput.value.length;
            subjectCounter.textContent = `${length}/100 characters`;
            if (length > 100) {
                subjectInput.value = subjectInput.value.slice(0, 100);
                subjectCounter.textContent = '100/100 characters';
            }
        });

        // Character counter for details
        const detailsInput = document.getElementById('details');
        const detailsCounter = document.getElementById('detailsCounter');
        detailsInput.addEventListener('input', () => {
            const length = detailsInput.value.length;
            detailsCounter.textContent = `${length}/500 characters`;
            if (length > 500) {
                detailsInput.value = detailsInput.value.slice(0, 500);
                detailsCounter.textContent = '500/500 characters';
            }
        });

        // File upload preview
        const attachmentsInput = document.getElementById('attachments');
        const attachmentPreview = document.getElementById('attachmentPreview');
        attachmentsInput.addEventListener('change', handleAttachments);

        function handleAttachments() {
            const files = attachmentsInput.files;
            attachmentPreview.innerHTML = '';
            
            Array.from(files).forEach((file, index) => {
                const fileReader = new FileReader();
                fileReader.onload = function(e) {
                    const previewDiv = document.createElement('div');
                    previewDiv.className = 'bg-gray-50 p-4 rounded-lg border border-gray-200';
                    
                    const previewContent = `
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-medium text-gray-800">${file.name}</h3>
                                <p class="text-sm text-gray-500">${formatFileSize(file.size)}</p>
                            </div>
                            <button onclick="removeFile(${index})" class="text-red-500 hover:text-red-700">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;
                    previewDiv.innerHTML = previewContent;
                    attachmentPreview.appendChild(previewDiv);
                };
                
                if (file.type.startsWith('image/')) {
                    fileReader.readAsDataURL(file);
                } else {
                    fileReader.readAsDataURL(file);
                }
            });
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function removeFile(index) {
            const dt = new DataTransfer();
            const files = attachmentsInput.files;
            
            for (let i = 0; i < files.length; i++) {
                if (i !== index) {
                    dt.items.add(files[i]);
                }
            }
            
            attachmentsInput.files = dt.files;
            handleAttachments();
        }

        // Form submission handling
        const form = document.getElementById('complaintForm');
        const submitButton = form.querySelector('button[type="submit"]');
        const submitText = document.getElementById('submitText');
        const submitSpinner = document.getElementById('submitSpinner');

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            submitButton.disabled = true;
            submitText.classList.add('hidden');
            submitSpinner.classList.remove('hidden');
            
            // Submit form
            form.submit();
        });

        // Save draft functionality
        function saveDraft() {
            // Save current form state to session
            const formData = {
                subject: subjectInput.value,
                details: detailsInput.value,
                location: document.getElementById('location').value
            };
            
            // Store in session
            localStorage.setItem('complaint_draft', JSON.stringify(formData));
            
            // Show success message
            alert('Draft saved successfully!');
        }
    </script>

    <!-- Success/Error Messages -->
    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    <div class="fixed inset-0 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg shadow-lg max-w-md mx-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-semibold text-green-600">Success!</h3>
                    <p class="text-gray-600 mt-2">
                        Your complaint has been submitted successfully!
                    </p>
                </div>
                <button onclick="closeSuccessMessage()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    <script>
        function closeSuccessMessage() {
            document.querySelector('.fixed.inset-0').style.display = 'none';
        }
        
        // Auto close after 3 seconds
        setTimeout(closeSuccessMessage, 3000);
    </script>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] == 1): ?>
    <div class="fixed inset-0 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg shadow-lg max-w-md mx-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-semibold text-red-600">Error!</h3>
                    <p class="text-gray-600 mt-2">
                        There was an error submitting your complaint. Please try again.
                    </p>
                </div>
                <button onclick="closeErrorMessage()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
    <script>
        function closeErrorMessage() {
            document.querySelector('.fixed.inset-0').style.display = 'none';
        }
        
        // Auto close after 3 seconds
        setTimeout(closeErrorMessage, 3000);
    </script>
    <?php endif; ?>

</body>
</html>