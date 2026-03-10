# JASSNET Business Management System

A comprehensive web-based business management system for JASSNET Tech Company, an ISP/WiFi service provider.

## Features

- **Dashboard**: Overview of income, expenses, inventory, and station projects
- **Income Management**: Record customer payments and service revenue
- **Expense Request System**: Multi-level approval workflow for expenses
- **Inventory Management**: Track equipment and supplies
- **Station Setup Requests**: Manage network station installation projects
- **User Management**: Role-based access control
- **Reports**: Financial and operational reports with export capabilities

## Technology Stack

- **Backend**: PHP 7+
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Charts**: Chart.js

## Installation

1. **Prerequisites**:
   - XAMPP (or similar PHP/MySQL environment)
   - Web browser

2. **Setup**:
   - Clone or download the project to `c:\xampp\htdocs\jassnet-incame`
   - Start XAMPP (Apache and MySQL)
   - Import the database:
     - Open phpMyAdmin (http://localhost/phpmyadmin)
     - Create database `jassnet_bms`
     - Import the `database.sql` file

3. **Access the System**:
   - Open browser to `http://localhost/jassnet-incame`
   - Login with:
     - Username: `admin`
     - Password: `password`

## User Roles

- **Super Admin**: Full system access, user management
- **Director**: Final expense and station approvals, financial reports
- **Manager**: Expense and station approvals, inventory monitoring
- **Accountant**: Process approved expenses, financial reports
- **Store Keeper**: Inventory management (only they may add items), equipment issuing
- **Technician**: Station setup management (only technicians may request new stations), income recording, expense requests
- **Sales**: Income recording (only sales may add income records), expense requests

## Database Schema

The system uses the following main tables:
- `users`: User accounts and roles
- `income`: Customer payments
- `expense_requests`: Expense approval workflow
- `inventory`: Equipment and supplies
- `station_requests`: Network station projects
- `equipment_requests`: Equipment issuance tracking

## Security Features

- Password hashing with bcrypt
- Role-based access control
- Password expiration policy (28 days)
- File upload restrictions
- SQL injection prevention with prepared statements

## File Structure

```
jassnet-incame/
├── index.php              # Login page
├── dashboard.php          # Main dashboard
├── config.php             # Database configuration
├── database.sql           # Database schema
├── logout.php             # Logout handler
├── change_password.php    # Password change
├── includes/
│   ├── functions.php      # Common functions
│   ├── header.php         # Page header
│   └── footer.php         # Page footer
├── pages/
│   ├── income.php         # Income management
│   ├── expenses.php       # Expense requests
│   ├── inventory.php      # Inventory management
│   ├── stations.php       # Station setup
│   ├── reports.php        # Reports and analytics
│   ├── profile.php        # User profile
│   └── users.php          # User management
├── assets/
│   ├── css/
│   │   └── style.css      # Custom styles
│   ├── js/
│   │   └── main.js        # Custom JavaScript
│   └── images/            # Static images
└── uploads/               # File uploads
```

## Usage

1. **Login** with appropriate credentials
2. **Dashboard** shows key metrics and recent activity
3. **Income**: Record customer payments
4. **Expenses**: Submit requests, approve/reject based on role
5. **Inventory**: Manage stock levels and issue equipment
6. **Stations**: Request and manage network installations
7. **Reports**: Generate and export financial reports

## Support

For issues or questions, contact the development team.