<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Barangay System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        .tab-active {
            border-bottom: 3px solid #3b82f6;
            color: #3b82f6;
            font-weight: 600;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
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

            <!-- Settings Content -->
            <main class="p-4 md:p-6">
                <!-- Settings Tabs -->
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8 px-6">
                            <button id="profileTab" class="py-4 px-1 border-b-2 font-medium text-sm tab-active">
                                <i class="fas fa-user mr-2"></i> Profile
                            </button>
                            <button id="securityTab" class="py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-shield-alt mr-2"></i> Security
                            </button>
                            <button id="systemTab" class="py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-server mr-2"></i> System
                            </button>
                            <button id="smsTab" class="py-4 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                <i class="fas fa-sms mr-2"></i> SMS Gateway
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Profile Settings -->
                <div id="profileContent" class="settings-content">
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                <i class="fas fa-user mr-2"></i> Profile Information
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">Update your account's profile information and email address.</p>
                        </div>
                        <div class="px-4 py-5 sm:p-6">
                            <form>
                                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                    <div class="sm:col-span-3">
                                        <label for="first-name" class="block text-sm font-medium text-gray-700">First name</label>
                                        <div class="mt-1">
                                            <input type="text" name="first-name" id="first-name" autocomplete="given-name" value="Juan" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        </div>
                                    </div>

                                    <div class="sm:col-span-3">
                                        <label for="last-name" class="block text-sm font-medium text-gray-700">Last name</label>
                                        <div class="mt-1">
                                            <input type="text" name="last-name" id="last-name" autocomplete="family-name" value="Dela Cruz" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        </div>
                                    </div>

                                    <div class="sm:col-span-4">
                                        <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                                        <div class="mt-1">
                                            <input id="email" name="email" type="email" autocomplete="email" value="juan.delacruz@barangay.gov.ph" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        </div>
                                    </div>

                                    <div class="sm:col-span-3">
                                        <label for="position" class="block text-sm font-medium text-gray-700">Position</label>
                                        <div class="mt-1">
                                            <select id="position" name="position" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                                <option>Barangay Captain</option>
                                                <option selected>Barangay Secretary</option>
                                                <option>Barangay Treasurer</option>
                                                <option>Barangay Councilor</option>
                                                <option>Barangay Staff</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="sm:col-span-3">
                                        <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                                        <div class="mt-1">
                                            <input type="text" name="phone" id="phone" autocomplete="tel" value="+639123456789" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        </div>
                                    </div>

                                    <div class="sm:col-span-6">
                                        <label for="photo" class="block text-sm font-medium text-gray-700">Photo</label>
                                        <div class="mt-1 flex items-center">
                                            <span class="h-12 w-12 rounded-full overflow-hidden bg-gray-100">
                                                <img src="https://via.placeholder.com/150" alt="Profile" class="h-full w-full">
                                            </span>
                                            <button type="button" class="ml-5 bg-white py-2 px-3 border border-gray-300 rounded-md shadow-sm text-sm leading-4 font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                Change
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-8 flex justify-end">
                                    <button type="button" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Cancel
                                    </button>
                                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Save
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div id="securityContent" class="settings-content hidden">
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                <i class="fas fa-shield-alt mr-2"></i> Security Settings
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">Update your password and enable two-factor authentication.</p>
                        </div>
                        <div class="px-4 py-5 sm:p-6">
                            <form>
                                <div class="space-y-6">
                                    <div>
                                        <label for="current-password" class="block text-sm font-medium text-gray-700">Current password</label>
                                        <div class="mt-1 relative">
                                            <input id="current-password" name="current-password" type="password" autocomplete="current-password" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                            <span class="password-toggle" onclick="togglePassword('current-password')">
                                                <i class="fas fa-eye"></i>
                                            </span>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="new-password" class="block text-sm font-medium text-gray-700">New password</label>
                                        <div class="mt-1 relative">
                                            <input id="new-password" name="new-password" type="password" autocomplete="new-password" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                            <span class="password-toggle" onclick="togglePassword('new-password')">
                                                <i class="fas fa-eye"></i>
                                            </span>
                                        </div>
                                        <p class="mt-2 text-sm text-gray-500">Password must be at least 8 characters long.</p>
                                    </div>

                                    <div>
                                        <label for="confirm-password" class="block text-sm font-medium text-gray-700">Confirm password</label>
                                        <div class="mt-1 relative">
                                            <input id="confirm-password" name="confirm-password" type="password" autocomplete="new-password" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                            <span class="password-toggle" onclick="togglePassword('confirm-password')">
                                                <i class="fas fa-eye"></i>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="border-t border-gray-200 pt-4">
                                        <h4 class="text-md font-medium text-gray-900 mb-2">
                                            <i class="fas fa-mobile-alt mr-2"></i> Two-Factor Authentication
                                        </h4>
                                        <p class="text-sm text-gray-500">Add additional security to your account using two-factor authentication.</p>
                                        <div class="mt-4">
                                            <div class="flex items-center">
                                                <input id="enable-2fa" name="enable-2fa" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label for="enable-2fa" class="ml-2 block text-sm text-gray-700">
                                                    Enable two-factor authentication via SMS
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-8 flex justify-end">
                                    <button type="button" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Cancel
                                    </button>
                                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Update Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- System Settings -->
                <div id="systemContent" class="settings-content hidden">
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                <i class="fas fa-server mr-2"></i> System Configuration
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">Configure system-wide settings for the Barangay Management System.</p>
                        </div>
                        <div class="px-4 py-5 sm:p-6">
                            <form>
                                <div class="space-y-6">
                                    <div>
                                        <label for="barangay-name" class="block text-sm font-medium text-gray-700">Barangay Name</label>
                                        <div class="mt-1">
                                            <input type="text" name="barangay-name" id="barangay-name" value="Barangay 123" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        </div>
                                    </div>

                                    <div>
                                        <label for="city-municipality" class="block text-sm font-medium text-gray-700">City/Municipality</label>
                                        <div class="mt-1">
                                            <input type="text" name="city-municipality" id="city-municipality" value="Quezon City" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        </div>
                                    </div>

                                    <div>
                                        <label for="province" class="block text-sm font-medium text-gray-700">Province</label>
                                        <div class="mt-1">
                                            <input type="text" name="province" id="province" value="Metro Manila" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        </div>
                                    </div>

                                    <div>
                                        <label for="region" class="block text-sm font-medium text-gray-700">Region</label>
                                        <div class="mt-1">
                                            <input type="text" name="region" id="region" value="NCR" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        </div>
                                    </div>

                                    <div class="border-t border-gray-200 pt-4">
                                        <h4 class="text-md font-medium text-gray-900 mb-2">
                                            <i class="fas fa-bell mr-2"></i> Notification Settings
                                        </h4>
                                        <div class="space-y-4">
                                            <div class="flex items-center">
                                                <input id="email-notifications" name="email-notifications" type="checkbox" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label for="email-notifications" class="ml-2 block text-sm text-gray-700">
                                                    Enable email notifications
                                                </label>
                                            </div>
                                            <div class="flex items-center">
                                                <input id="sms-notifications" name="sms-notifications" type="checkbox" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label for="sms-notifications" class="ml-2 block text-sm text-gray-700">
                                                    Enable SMS notifications
                                                </label>
                                            </div>
                                            <div class="flex items-center">
                                                <input id="push-notifications" name="push-notifications" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label for="push-notifications" class="ml-2 block text-sm text-gray-700">
                                                    Enable push notifications
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="border-t border-gray-200 pt-4">
                                        <h4 class="text-md font-medium text-gray-900 mb-2">
                                            <i class="fas fa-database mr-2"></i> Backup Settings
                                        </h4>
                                        <div class="space-y-4">
                                            <div>
                                                <label for="backup-frequency" class="block text-sm font-medium text-gray-700">Backup Frequency</label>
                                                <select id="backup-frequency" name="backup-frequency" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                                    <option>Daily</option>
                                                    <option selected>Weekly</option>
                                                    <option>Monthly</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label for="backup-location" class="block text-sm font-medium text-gray-700">Backup Location</label>
                                                <input type="text" name="backup-location" id="backup-location" value="/var/backups/barangay-system" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                            </div>
                                            <div class="flex items-center">
                                                <input id="auto-backup" name="auto-backup" type="checkbox" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label for="auto-backup" class="ml-2 block text-sm text-gray-700">
                                                    Enable automatic backups
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-8 flex justify-end">
                                    <button type="button" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Cancel
                                    </button>
                                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Save Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- SMS Gateway Settings -->
                <div id="smsContent" class="settings-content hidden">
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                <i class="fas fa-sms mr-2"></i> SMS Gateway Configuration
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">Configure SMS gateway settings for sending notifications.</p>
                        </div>
                        <div class="px-4 py-5 sm:p-6">
                            <form>
                                <div class="space-y-6">
                                    <div>
                                        <label for="sms-provider" class="block text-sm font-medium text-gray-700">SMS Provider</label>
                                        <select id="sms-provider" name="sms-provider" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                            <option value="semaphore">Semaphore</option>
                                            <option value="twilio">Twilio</option>
                                            <option value="nexmo">Vonage (Nexmo)</option>
                                            <option value="plivo">Plivo</option>
                                            <option value="custom">Custom API</option>
                                        </select>
                                    </div>

                                    <div id="semaphore-fields">
                                        <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                            <div class="sm:col-span-3">
                                                <label for="semaphore-api-key" class="block text-sm font-medium text-gray-700">API Key</label>
                                                <div class="mt-1">
                                                    <input type="text" name="semaphore-api-key" id="semaphore-api-key" value="1234567890abcdef" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                                </div>
                                            </div>

                                            <div class="sm:col-span-3">
                                                <label for="semaphore-sender-name" class="block text-sm font-medium text-gray-700">Sender Name</label>
                                                <div class="mt-1">
                                                    <input type="text" name="semaphore-sender-name" id="semaphore-sender-name" value="BARANGAY" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="twilio-fields" class="hidden">
                                        <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                            <div class="sm:col-span-3">
                                                <label for="twilio-account-sid" class="block text-sm font-medium text-gray-700">Account SID</label>
                                                <div class="mt-1">
                                                    <input type="text" name="twilio-account-sid" id="twilio-account-sid" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                                </div>
                                            </div>

                                            <div class="sm:col-span-3">
                                                <label for="twilio-auth-token" class="block text-sm font-medium text-gray-700">Auth Token</label>
                                                <div class="mt-1">
                                                    <input type="password" name="twilio-auth-token" id="twilio-auth-token" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                                </div>
                                            </div>

                                            <div class="sm:col-span-3">
                                                <label for="twilio-phone-number" class="block text-sm font-medium text-gray-700">Phone Number</label>
                                                <div class="mt-1">
                                                    <input type="text" name="twilio-phone-number" id="twilio-phone-number" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="border-t border-gray-200 pt-4">
                                        <h4 class="text-md font-medium text-gray-900 mb-2">
                                            <i class="fas fa-cog mr-2"></i> SMS Settings
                                        </h4>
                                        <div class="space-y-4">
                                            <div class="flex items-center">
                                                <input id="enable-sms" name="enable-sms" type="checkbox" checked class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label for="enable-sms" class="ml-2 block text-sm text-gray-700">
                                                    Enable SMS notifications
                                                </label>
                                            </div>
                                            <div class="flex items-center">
                                                <input id="sms-test-mode" name="sms-test-mode" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                <label for="sms-test-mode" class="ml-2 block text-sm text-gray-700">
                                                    Test mode (no actual SMS will be sent)
                                                </label>
                                            </div>
                                            <div>
                                                <label for="sms-test-number" class="block text-sm font-medium text-gray-700">Test Phone Number</label>
                                                <div class="mt-1">
                                                    <input type="text" name="sms-test-number" id="sms-test-number" value="+639123456789" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-8 flex justify-end">
                                    <button type="button" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Cancel
                                    </button>
                                    <button type="button" onclick="testSMSConnection()" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                        <i class="fas fa-bolt mr-2"></i> Test Connection
                                    </button>
                                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Save Settings
                                    </button>
                                </div>
                            </form>
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

        // Tab switching functionality
        const tabs = {
            profileTab: document.getElementById('profileTab'),
            securityTab: document.getElementById('securityTab'),
            systemTab: document.getElementById('systemTab'),
            smsTab: document.getElementById('smsTab'),
            profileContent: document.getElementById('profileContent'),
            securityContent: document.getElementById('securityContent'),
            systemContent: document.getElementById('systemContent'),
            smsContent: document.getElementById('smsContent')
        };

        function switchTab(activeTab) {
            // Reset all tabs
            Object.keys(tabs).forEach(key => {
                if (key.endsWith('Tab')) {
                    tabs[key].classList.remove('tab-active');
                    tabs[key].classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
                    tabs[key].classList.remove('text-blue-600');
                }
            });

            // Hide all content
            Object.keys(tabs).forEach(key => {
                if (key.endsWith('Content')) {
                    tabs[key].classList.add('hidden');
                }
            });

            // Activate selected tab
            activeTab.classList.add('tab-active');
            activeTab.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
            activeTab.classList.add('text-blue-600');

            // Show corresponding content
            const contentId = activeTab.id.replace('Tab', 'Content');
            document.getElementById(contentId).classList.remove('hidden');
        }

        // Add event listeners to tabs
        tabs.profileTab.addEventListener('click', () => switchTab(tabs.profileTab));
        tabs.securityTab.addEventListener('click', () => switchTab(tabs.securityTab));
        tabs.systemTab.addEventListener('click', () => switchTab(tabs.systemTab));
        tabs.smsTab.addEventListener('click', () => switchTab(tabs.smsTab));

        // AJAX form submission
        const forms = {
            profile: document.getElementById('profileForm'),
            security: document.getElementById('securityForm'),
            system: document.getElementById('systemForm'),
            sms: document.getElementById('smsForm')
        };

        function submitForm(formId) {
            const form = forms[formId];
            if (!form) return;

            const formData = new FormData(form);
            const data = {};

            // Convert form data to object
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }

            // Send AJAX request
            fetch('process_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    type: formId,
                    data: data
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    form.reset();
                    showNotification('success', result.message);
                } else {
                    showNotification('error', result.error);
                }
            })
            .catch(error => {
                showNotification('error', 'An error occurred while saving settings');
            });
        }

        // Add submit event listeners to forms
        Object.keys(forms).forEach(formId => {
            forms[formId].addEventListener('submit', (e) => {
                e.preventDefault();
                submitForm(formId);
            });
        });

        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // SMS provider selection
        document.getElementById('sms-provider').addEventListener('change', function() {
            const provider = this.value;
            document.getElementById('semaphore-fields').classList.add('hidden');
            document.getElementById('twilio-fields').classList.add('hidden');
            
            if (provider === 'semaphore') {
                document.getElementById('semaphore-fields').classList.remove('hidden');
            } else if (provider === 'twilio') {
                document.getElementById('twilio-fields').classList.remove('hidden');
            }
        });

        // Test SMS connection
        function testSMSConnection() {
            const provider = document.getElementById('sms-provider').value;
            const testNumber = document.getElementById('sms-test-number').value;
            
            if (!testNumber) {
                showNotification('error', 'Please enter a test phone number');
                return;
            }
            
            // In a real system, this would make an actual API call
            showNotification('success', `Testing ${provider} SMS connection to ${testNumber}`);
        }

        // Show notification
        function showNotification(type, message) {
            const notification = document.createElement('div');
            notification.className = `settings-${type} fixed bottom-4 right-4 px-4 py-3 rounded-lg text-white`;
            notification.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
                ${message}
            `;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Cancel form changes
        function cancelForm(formId) {
            const form = forms[formId];
            if (!form) return;
            form.reset();
        }
    </script>
</body>
</html>