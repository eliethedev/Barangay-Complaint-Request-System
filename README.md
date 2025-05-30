# Barangay Complaint Request System

A web-based system designed to streamline the process of submitting and managing complaints in a barangay (local community) setting. This system provides an efficient way for residents to submit complaints and for barangay officials to manage and respond to them.

## Features

### User Features
- User Registration and Login
- Submit Complaints
- Track Complaint Status
- View Complaint History
- Profile Management

### Admin Features
- View and Manage Complaints
- Assign Complaints to Staff
- Update Complaint Status
- Generate Reports
- Manage Users
- Activity Logging

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web Server (Apache/Nginx)
- Modern Web Browser

## Installation

1. Clone the repository to your web server directory
2. Import the database schema from `database/barangay_system.sql`
3. Configure database connection in `baby_capstone_connection.php`
4. Set up proper file permissions for uploads directory
5. Configure web server settings

## Database Structure

The system uses the following main tables:

- `users`: Stores user information and authentication details
- `admin_users`: Stores admin user information
- `complaints`: Stores all complaint records
- `admin_logs`: Stores admin activity logs
- `notifications`: Stores system notifications

## Security Features

- Secure password hashing using PHP's password_hash()
- Session management with timeout
- Input validation and sanitization
- SQL injection prevention using prepared statements
- XSS protection
- CSRF protection

## File Structure

```
baby_capstone/
├── admin/              # Admin dashboard and management pages
├── assets/            # CSS, JavaScript, and image files
├── includes/          # Common PHP files and functions
├── uploads/           # User uploaded files
├── index.php          # Main login page
├── submit_complaint.php # Complaint submission page
└── ... other PHP files
```

## User Flow

1. **Login/Registration**
   - Users can register for an account
   - Existing users can log in using email and password
   - Admins have separate login credentials

2. **Complaint Submission**
   - Users can submit complaints with:
     - Subject type selection
     - Detailed description
     - Location information
     - Optional attachments
   - Complaints are automatically assigned a status of 'Pending'

3. **Complaint Management**
   - Users can view their submitted complaints
   - Admins can:
     - View all complaints
     - Update complaint status
     - Assign complaints to staff
     - Generate reports

## Error Handling

The system implements comprehensive error handling:
- Input validation errors
- Database connection errors
- Session timeout handling
- File upload errors
- Authentication errors

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.
