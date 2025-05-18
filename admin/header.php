<header class="bg-white shadow-sm">
                <div class="flex justify-between items-center p-4">
                    <div class="flex items-center">
                        <button id="sidebarToggle" class="md:hidden text-gray-600 mr-4">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        <h1 class="text-xl font-semibold text-gray-800">Dashboard</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <button id="notificationBtn" class="text-gray-600 hover:text-blue-600 relative">
                                <i class="fas fa-bell text-xl"></i>
                                <span id="notificationCount" class="notification-badge absolute -top-1 -right-1 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full">0</span>
                            </button>
                            <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-72 bg-white rounded-md shadow-lg z-10 overflow-hidden">
                                <div class="p-3 border-b border-gray-200">
                                    <h3 class="text-sm font-semibold">Notifications</h3>
                                </div>
                                <div id="notificationList" class="max-h-60 overflow-y-auto">
                                    <!-- Notifications will be populated here -->
                                </div>
                                <div class="p-2 text-center bg-gray-50">
                                    <a href="#" class="text-sm text-blue-600 hover:underline">View all notifications</a>
                                </div>
                            </div>
                        </div>
                        <div class="relative">
                            <button id="userMenuBtn" class="flex items-center space-x-2">
                                <img src="assets/images/default-profile.avif" alt="User" class="h-8 w-8 rounded-full">
                                <span class="hidden md:inline text-sm font-medium">Admin User</span>
                            </button>
                            <div id="userMenuDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10">
                                <div class="py-1">
                                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
                                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 border-t border-gray-100">Logout</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <script>
                let lastNotificationId = 0;

                function updateNotifications() {
                    fetch('get_notifications.php?last_id=' + lastNotificationId)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('Notifications data:', data); // For debugging
                            
                            // Update notification count
                            const countElement = document.getElementById('notificationCount');
                            if (countElement) {
                                countElement.textContent = data.count;
                            }

                            // Update notifications list
                            const notificationList = document.getElementById('notificationList');
                            if (notificationList) {
                                notificationList.innerHTML = '';

                                data.notifications.forEach(notification => {
                                    const notificationHtml = `
                                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 ${notification.id > lastNotificationId ? 'border-b border-gray-100' : ''}" 
                                           onclick="markAsRead(${notification.id})">
                                            <div class="flex items-start">
                                                <div class="flex-shrink-0 ${getNotificationColor(notification.type)} mt-1">
                                                    <i class="${getNotificationIcon(notification.type)}"></i>
                                                </div>
                                                <div class="ml-3">
                                                    <p>${notification.message}</p>
                                                    <p class="text-xs text-gray-500">${formatTime(notification.created_at)}</p>
                                                </div>
                                            </div>
                                        </a>
                                    `;
                                    notificationList.innerHTML = notificationHtml + notificationList.innerHTML;
                                });

                                // Update last notification ID
                                if (data.notifications.length > 0) {
                                    lastNotificationId = data.notifications[0].id;
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching notifications:', error);
                            // Retry after 5 seconds if there's an error
                            setTimeout(updateNotifications, 5000);
                        });
                }

                function getNotificationColor(type) {
                    switch(type) {
                        case 'complaint':
                            return 'text-blue-500';
                        case 'request':
                            return 'text-green-500';
                        case 'sms':
                            return 'text-yellow-500';
                        default:
                            return 'text-gray-500';
                    }
                }

                function getNotificationIcon(type) {
                    switch(type) {
                        case 'complaint':
                            return 'fas fa-exclamation-circle';
                        case 'request':
                            return 'fas fa-check-circle';
                        case 'sms':
                            return 'fas fa-sms';
                        default:
                            return 'fas fa-info-circle';
                    }
                }

                function formatTime(timestamp) {
                    const date = new Date(timestamp);
                    const now = new Date();
                    const diff = now - date;

                    const minutes = Math.floor(diff / 60000);
                    if (minutes < 60) return `${minutes} mins ago`;

                    const hours = Math.floor(minutes / 60);
                    if (hours < 24) return `${hours} hours ago`;

                    return date.toLocaleDateString();
                }

                function markAsRead(notificationId) {
                    fetch('mark_notification_read.php?id=' + notificationId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                updateNotifications();
                            }
                        });
                }

                // Initial update with retry mechanism
                let updateAttempts = 0;
                const maxAttempts = 3;

                function startNotificationUpdates() {
                    updateNotifications().catch(() => {
                        if (updateAttempts < maxAttempts) {
                            updateAttempts++;
                            setTimeout(startNotificationUpdates, 5000);
                        }
                    });
                }

                startNotificationUpdates();

                // Update every 30 seconds with retry mechanism
                setInterval(() => {
                    updateAttempts = 0;
                    startNotificationUpdates();
                }, 30000);

                // Handle notification dropdown
                document.getElementById('notificationBtn').addEventListener('click', function() {
                    const dropdown = document.getElementById('notificationDropdown');
                    dropdown.classList.toggle('hidden');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function(event) {
                    const dropdown = document.getElementById('notificationDropdown');
                    if (!dropdown.contains(event.target) && !event.target.closest('#notificationBtn')) {
                        dropdown.classList.add('hidden');
                    }
                });
            </script>