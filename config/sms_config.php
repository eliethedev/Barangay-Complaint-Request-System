<?php
// SMS Configuration
define('SMS_API_KEY', '1847|Zk0AT8WIbd72MXKWzNleRsFaT89hVxBs8bxQJ52n'); // Replace with your actual PhilSMS API key
define('SMS_SENDER_ID', 'PHILSMS'); // Must be approved by PhilSMS
define('SMS_API_URL', 'https://app.philsms.com/api/v3/sms/send');

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if API key is set
if (!defined('SMS_API_KEY') || empty(SMS_API_KEY)) {
    trigger_error('SMS_API_KEY is not set in configuration', E_USER_ERROR);
}
