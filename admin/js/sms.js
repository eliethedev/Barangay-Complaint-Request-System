// SMS Management Functions
let currentSMSId = null;

function openNewSMSModal() {
    document.getElementById('newSMSModal').classList.remove('hidden');
}

function closeNewSMSModal() {
    document.getElementById('newSMSModal').classList.add('hidden');
    document.getElementById('newSMSForm').reset();
    resetRecipientContainers();
}

function resetRecipientContainers() {
    document.getElementById('zoneSelectionContainer').classList.add('hidden');
    document.getElementById('categorySelectionContainer').classList.add('hidden');
    document.getElementById('customRecipientsContainer').classList.add('hidden');
}

// Helper function to fetch recipients from server
function fetchRecipients(type = '', filter = '') {
    const formData = new FormData();
    formData.append('action', 'get_recipients');
    if (type) formData.append('type', type);
    if (filter) formData.append('filter', filter);
    
    return fetch('handle_sms.php', {
        method: 'POST',
        body: formData
    }).then(response => response.json());
}

// Helper function to validate phone numbers
function isValidPhoneNumber(phone) {
    const cleaned = phone.replace(/\D/g, '');
    // Allow 09XXXXXXXXX, 639XXXXXXXXX, +639XXXXXXXXX formats
    return /^09\d{9}$/.test(cleaned) || /^639\d{9}$/.test(cleaned) || /^\+639\d{9}$/.test(cleaned);
}

// Helper function to format and send SMS
function sendSMSRequest(message, type, recipients, isScheduled, scheduledTime) {
    try {
        // Format phone numbers
        const formattedRecipients = recipients.map(phone => {
            phone = phone.replace(/\D/g, '');
            if (!phone.startsWith('63')) {
                phone = '63' + phone;
            }
            return phone;
        });

        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('type', type);
        formData.append('recipients', JSON.stringify(formattedRecipients));
        formData.append('message', message);
        if (isScheduled && scheduledTime) {
            formData.append('scheduled_time', scheduledTime);
        }

        fetch('handle_sms.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert('SMS sent successfully');
                closeNewSMSModal();
                // Refresh the page to show new SMS
                location.reload();
            } else {
                throw new Error(data.message || 'Failed to send SMS');
            }
        })
        .catch(error => {
            alert('An error occurred while sending SMS: ' + error.message);
            console.error('Error:', error);
        });
    } catch (error) {
        alert('An unexpected error occurred: ' + error.message);
        console.error('Unexpected error:', error);
    }
}

function sendNewSMS() {
    const message = document.getElementById('smsMessage').value;
    const type = document.getElementById('smsType').value;
    const recipientType = document.getElementById('recipientType').value;
    const isScheduled = document.getElementById('scheduleSMS').checked;
    const scheduledTime = document.getElementById('scheduleDate').value;
    
    if (!message) {
        alert('Please enter a message');
        return;
    }

    // Validate message length
    if (message.length > 160) {
        alert('Message is too long. Maximum 160 characters allowed.');
        return;
    }

    // Get recipients based on selection type
    let recipients = [];
    let fetchPromise;

    if (recipientType === 'zone') {
        const selectedZones = Array.from(document.querySelectorAll('input[name="zones"]:checked'))
            .map(el => el.value);
        if (selectedZones.length === 0) {
            alert('Please select at least one zone');
            return;
        }
        fetchPromise = fetchRecipients('zone', selectedZones.join(','));
    } else if (recipientType === 'category') {
        const selectedCategories = Array.from(document.querySelectorAll('input[name="categories"]:checked'))
            .map(el => el.value);
        if (selectedCategories.length === 0) {
            alert('Please select at least one category');
            return;
        }
        fetchPromise = fetchRecipients('category', selectedCategories.join(','));
    } else if (recipientType === 'custom') {
        const customRecipients = document.getElementById('customRecipients').value.trim();
        if (!customRecipients) {
            alert('Please enter at least one recipient');
            return;
        }
        // Validate custom numbers
        const numbers = customRecipients.split(',').map(r => r.trim());
        if (numbers.some(num => !isValidPhoneNumber(num))) {
            alert('Invalid phone number format. Please use format: 09171234567 or +639171234567');
            return;
        }
        recipients = numbers;
        sendSMSRequest(message, type, recipients, isScheduled, scheduledTime);
        return;
    } else {
        // Get all residents
        fetchPromise = fetchRecipients();
    }

    // Fetch recipients from server
    fetchPromise.then(data => {
        if (!data.success) {
            throw new Error(data.message || 'Failed to fetch recipients');
        }
        recipients = data.recipients;
        sendSMSRequest(message, type, recipients, isScheduled, scheduledTime);
    })
    .catch(error => {
        alert('Error fetching recipients: ' + error.message);
        console.error('Error:', error);
    });
}

function viewSMSDetails(smsId) {
    fetch(`handle_sms.php?action=details&sms_id=${smsId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const details = data.details;
                document.getElementById('smsDetailsId').textContent = details.id;
                document.getElementById('smsDetailsDate').textContent = details.created_at;
                document.getElementById('smsDetailsType').textContent = details.type;
                document.getElementById('smsDetailsStatus').textContent = details.status;
                document.getElementById('smsDetailsRecipients').textContent = details.recipients;
                document.getElementById('smsDetailsMessage').textContent = details.message;
                document.getElementById('smsDetailsResponse').value = details.response || '';
                document.getElementById('smsDetailsModal').classList.remove('hidden');
            } else {
                alert('Failed to load SMS details: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while loading SMS details');
        });
}

function closeSMSDetailsModal() {
    document.getElementById('smsDetailsModal').classList.add('hidden');
}

function resendSMS(smsId) {
    if (!confirm('Are you sure you want to resend this SMS?')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'resend');
    formData.append('sms_id', smsId);

    fetch('handle_sms.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('SMS queued for resending');
            location.reload();
        } else {
            alert('Failed to resend SMS: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while resending the SMS');
    });
}

function deleteSMS(smsId) {
    currentSMSId = smsId;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    currentSMSId = null;
}

function confirmDelete() {
    if (!currentSMSId) {
        closeDeleteModal();
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('sms_id', currentSMSId);

    fetch('handle_sms.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('SMS deleted successfully');
            location.reload();
        } else {
            alert('Failed to delete SMS: ' + data.message);
        }
        closeDeleteModal();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the SMS');
        closeDeleteModal();
    });
}

// Function to load SMS notifications with filters
function loadSMSNotifications() {
    const type = document.getElementById('filterType').value;
    const status = document.getElementById('filterStatus').value;
    const date = document.getElementById('filterDate').value;

    // Build query string
    const params = new URLSearchParams();
    params.append('action', 'list');
    if (type) params.append('type', type);
    if (status) params.append('status', status);
    if (date) params.append('date', date);

    fetch(`handle_sms.php?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateSMSTable(data.notifications);
            } else {
                console.error('Failed to load notifications:', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Function to update the SMS table with new data
function updateSMSTable(notifications) {
    const tbody = document.querySelector('table tbody');
    tbody.innerHTML = ''; // Clear existing rows

    notifications.forEach(sms => {
        const statusClass = getStatusClass(sms.status);
        const typeClass = getTypeClass(sms.type);

        const row = `
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${sms.id}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${JSON.parse(sms.recipients).length} recipients</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${typeClass}">
                        ${sms.type}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                        ${sms.status}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${sms.created_at}</td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <button onclick="viewSMSDetails('${sms.id}')" class="text-blue-600 hover:text-blue-900 mr-2">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="resendSMS('${sms.id}')" class="text-green-600 hover:text-green-900 mr-2">
                        <i class="fas fa-redo"></i>
                    </button>
                    <button onclick="deleteSMS('${sms.id}')" class="text-red-600 hover:text-red-900">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        tbody.insertAdjacentHTML('beforeend', row);
    });
}

// Helper functions for status and type classes
function getStatusClass(status) {
    switch (status) {
        case 'Sent': return 'sms-status-sent';
        case 'Failed': return 'sms-status-failed';
        case 'Pending': return 'sms-status-pending';
        default: return 'sms-status-pending';
    }
}

function getTypeClass(type) {
    switch (type) {
        case 'Bulk': return 'sms-type-bulk';
        case 'Individual': return 'sms-type-individual';
        case 'Urgent': return 'sms-type-urgent';
        default: return 'sms-type-bulk';
    }
}

// Initialize event listeners when the document is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Set up filter change listeners
    document.getElementById('filterType').addEventListener('change', loadSMSNotifications);
    document.getElementById('filterStatus').addEventListener('change', loadSMSNotifications);
    document.getElementById('filterDate').addEventListener('change', loadSMSNotifications);

    // Load initial notifications
    loadSMSNotifications();

    // Character counter for SMS message
    const smsMessage = document.getElementById('smsMessage');
    const charCount = document.getElementById('charCount');
    
    smsMessage.addEventListener('input', function() {
        const count = this.value.length;
        charCount.textContent = `${count}/160 characters`;
        if (count > 160) {
            charCount.classList.add('text-red-500');
        } else {
            charCount.classList.remove('text-red-500');
        }
    });

    // Recipient type change handler
    const recipientType = document.getElementById('recipientType');
    recipientType.addEventListener('change', function() {
        resetRecipientContainers();
        const container = document.getElementById(`${this.value}SelectionContainer`);
        if (container) {
            container.classList.remove('hidden');
        }
    });

    // Schedule SMS toggle
    const scheduleSMS = document.getElementById('scheduleSMS');
    const scheduleDateContainer = document.getElementById('scheduleDateContainer');
    
    scheduleSMS.addEventListener('change', function() {
        scheduleDateContainer.classList.toggle('hidden', !this.checked);
    });
});
