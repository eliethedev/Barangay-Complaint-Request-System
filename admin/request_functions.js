// Debug mode flag
const DEBUG_MODE = true;

// Debug logging function
function debug(label, data) {
    if (DEBUG_MODE) {
        console.group(`DEBUG: ${label}`);
        console.log(data);
        console.groupEnd();
    }
}

// Fetch request data from server
async function fetchRequestData(requestId) {
    try {
        debug('Fetching request data', `ID: ${requestId}`);
        
        // Show loading indicator in the modal
        document.getElementById('modalRequestId').textContent = 'Loading...';
        
        const response = await fetch(`get_request_details.php?id=${requestId}`);
        const responseText = await response.text();
        
        // Debug the raw response
        debug('Raw API response', responseText);
        
        // Try to parse the response
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Failed to parse JSON response:', parseError);
            alert('Server returned invalid data. Please check the console for details.');
            return null;
        }
        
        // Check for error in the parsed response
        if (data.error) {
            console.error('API returned error:', data.error);
            alert('Error: ' + data.error);
            return null;
        }
        
        // Log the full data object for debugging
        debug('Parsed request data', data);
        
        return data;
    } catch (error) {
        console.error('Error fetching request data:', error);
        alert('Error fetching request data: ' + error.message);
        return null;
    }
}

// Format date helper function
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString(undefined, options);
}

// View request details
async function viewRequest(requestId) {
    debug('Opening request modal', `ID: ${requestId}`);
    
    // Show modal first with loading state
    document.getElementById('requestModal').classList.remove('hidden');
    document.getElementById('modalRequestId').textContent = 'Loading...';
    
    // Fetch request data
    const request = await fetchRequestData(requestId);
    if (!request) {
        document.getElementById('modalRequestId').textContent = 'Error loading data';
        return;
    }
    
    // Fill modal with data
    document.getElementById('modalRequestId').textContent = request.id;
    document.getElementById('form_request_id').value = request.id;
    document.getElementById('modalName').textContent = request.name || request.full_name || 'N/A';
    document.getElementById('modalContact').textContent = request.phone || request.user_phone || 'N/A';
    document.getElementById('modalType').textContent = request.type || 'N/A';
    document.getElementById('modalDate').textContent = formatDate(request.created_at);
    document.getElementById('modalStatusSelect').value = request.status || 'Pending';
    document.getElementById('modalPaymentStatus').value = request.payment_status || 'pending';
    document.getElementById('modalRequestDetails').textContent = request.details || 'N/A';
    
    // Set documents info
    let docInfo = [];
    if (request.attachments) docInfo.push(request.attachments);
    if (request.purpose) docInfo.push(`Purpose: ${request.purpose}`);
    if (request.validity) docInfo.push(`Validity: ${request.validity}`);
    document.getElementById('modalDocuments').textContent = docInfo.length > 0 ? docInfo.join(', ') : 'None';
    
    // Set admin notes
    document.getElementById('modalAdminNotes').value = request.admin_notes || '';
    
    // Set payment proof
    if (request.proof_of_payment) {
        document.getElementById('proofText').textContent = 'Payment proof uploaded';
        document.getElementById('proofContainer').classList.remove('hidden');
        document.getElementById('viewProofLink').href = '../' + request.proof_of_payment;
    } else {
        document.getElementById('proofText').textContent = 'No proof of payment uploaded';
        document.getElementById('proofContainer').classList.add('hidden');
    }
    
    // Store data for SMS
    document.getElementById('requestModal').dataset.phone = request.phone || request.user_phone || '';
    document.getElementById('requestModal').dataset.name = request.name || request.full_name || '';
}

// Update request via AJAX
function updateRequest(form) {
    const formData = new FormData(form);
    const updateButton = form.querySelector('button[type="submit"]');
    
    // Log the form data for debugging
    if (DEBUG_MODE) {
        console.group('DEBUG: Update request form data');
        for (let [key, value] of formData.entries()) {
            console.log(`${key}: ${value}`);
        }
        console.groupEnd();
    }
    
    // Disable button during update
    updateButton.disabled = true;
    updateButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Updating...';
    
    // Send AJAX request
    fetch('request.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.text())
    .then(text => {
        // Re-enable button
        updateButton.disabled = false;
        updateButton.innerHTML = 'Update Request';
        
        // Log the raw response
        debug('Update response (raw)', text);
        
        // Try to parse response as JSON
        try {
            const data = JSON.parse(text);
            debug('Update response (parsed)', data);
            
            if (data.success) {
                // Show success alert
                alert('Request updated successfully!');
                
                // Close modal and reload page
                closeModal();
                window.location.reload();
            } else {
                // Show error
                alert(data.error || 'Update failed. Please try again.');
            }
        } catch (e) {
            console.error('Invalid JSON response:', text);
            alert('The server returned an invalid response. Please try again.');
        }
    })
    .catch(error => {
        // Handle errors
        console.error('Error:', error);
        updateButton.disabled = false;
        updateButton.innerHTML = 'Update Request';
        alert('An error occurred: ' + error.message);
    });
    
    return false; // Prevent form submission
}

// Close any modal
function closeModal() {
    document.getElementById('requestModal').classList.add('hidden');
    document.getElementById('newRequestModal').classList.add('hidden');
    document.getElementById('smsModal').classList.add('hidden');
}

// Close new request modal
function closeNewRequestModal() {
    document.getElementById('newRequestModal').classList.add('hidden');
}

// Open new request modal
function openNewRequestModal() {
    document.getElementById('newRequestModal').classList.remove('hidden');
}

// Send SMS notification from the request modal
function sendSMSFromModal() {
    const modal = document.getElementById('requestModal');
    const phone = modal.dataset.phone;
    const name = modal.dataset.name;
    const requestId = document.getElementById('form_request_id').value;
    
    sendSMS(phone, name, requestId);
}

// Send SMS notification
function sendSMS(phone, name, requestId) {
    document.getElementById('smsRecipient').value = name + ' (' + phone + ')';
    document.getElementById('smsRequestId').value = requestId;
    document.getElementById('smsMessage').value = `Your request ${requestId} has been received and is currently being processed. We will update you on the status. Thank you.`;
    document.getElementById('smsModal').classList.remove('hidden');
}

// Close SMS modal
function closeSMSModal() {
    document.getElementById('smsModal').classList.add('hidden');
}

// Submit SMS notification
function submitSMS() {
    const message = document.getElementById('smsMessage').value;
    alert('SMS would be sent with message:\n\n' + message);
    closeSMSModal();
}

// Submit new request
function submitNewRequest() {
    const resident = document.getElementById('residentName').value;
    const type = document.getElementById('requestType').value;
    const details = document.getElementById('requestDetails').value;
    const info = document.getElementById('additionalInfo').value;
    
    if (!resident || !type || !details) {
        alert('Please fill in all required fields');
        return;
    }
    
    alert(`New request submitted:\n\nResident: ${resident}\nType: ${type}\nDetails: ${details}\nAdditional Info: ${info}`);
    closeNewRequestModal();
    document.getElementById('newRequestForm').reset();
}

// Add event listeners when the page loads
window.addEventListener('load', function() {
    debug('Page loaded, attaching event listeners', new Date().toISOString());
    
    // Add event listener to request form
    const requestForm = document.querySelector('#requestModal form');
    if (requestForm) {
        debug('Request form found', requestForm);
        requestForm.addEventListener('submit', function(e) {
            e.preventDefault();
            updateRequest(this);
        });
    } else {
        console.error('Request form not found in DOM');
    }
    
    // Mobile sidebar toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('hidden');
        });
    }
}); 