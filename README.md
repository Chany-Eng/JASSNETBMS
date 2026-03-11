# JASSNET Business Management System v2.0

A comprehensive web-based business management system for JASSNET Tech Company, an ISP/WiFi service provider. Built with modern PHP architecture following MVC pattern with clear separation of concerns.

## Features

- **Dashboard**: Overview of income, expenses, inventory, customer metrics and station projects with role‑based cards, charts and activity panels (modern Bootstrap 5 layout)
  - Exportable reports in CSV/JSON/Excel/PDF formats from the income and expense modules
- **Income Management**: Record customer payments and service revenue
- **Expense Request System**: Multi-level approval workflow for expenses
- **Inventory Management**: Track equipment and supplies
- **Station Setup Requests**: Manage network station installation projects
- **User Management**: Role-based access control
- **Reports**: Financial and operational reports with export capabilities
- **Activity Logging**: Track all user actions and system events
- **File Management**: Secure file uploads with validation

## Architecture

JASSNET BMS v2.0 implements a professional modular PHP architecture using the MVC (Model-View-Controller) pattern with the following components:

### Core Structure
- **Models**: Database abstraction layer with ORM-like operations
- **Controllers**: Business logic and request handling
- **Views**: Presentation templates (Blade-like PHP templates)
- **Helpers**: Reusable utility functions for validation, file handling, etc.
- **Database**: PDO-based database wrapper with prepared statements

### Security Features
- PDO prepared statements (SQL injection prevention)
- Password hashing with bcrypt (cost: 12)
- CSRF token protection
- Session-based authentication
- Role-based access control (RBAC)
- Password expiration policy (28 days)
- Security headers (X-Frame-Options, X-Content-Type-Options, etc.)

## Technology Stack

- **Backend**: PHP 8.0+ (previous versions may work but untested)
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5.3.3
- **Charts**: Chart.js
- **Database Driver**: PDO (MySQL)

## Installation

### Prerequisites
- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Web server (Apache, Nginx, etc.)
- Composer (optional, for future dependency management)

### Setup Steps

1. **Clone/Download Project**
   ```bash
   cd c:\xampp\htdocs
   git clone <repository-url> jassnet-incame
   # or download and extract to jassnet-incame folder
   ```

2. **Set Permissions** (Linux/Mac)
   ```bash
   chmod 755 /path/to/jassnet-incame
   chmod 755 /path/to/jassnet-incame/uploads
   ```

3. **Configure Database**
   - Create MySQL database: `jassnet_bms`
   - Import schema: `mysql -u root jassnet_bms < database.sql`

4. **Configure Application**
   - Update environment variables in `config/config.php` or use `.env` file:
     ```php
     DB_HOST=localhost
     DB_USER=root
     DB_PASS=
     DB_NAME=jassnet_bms
     APP_ENV=production
     ```

5. **Set Web Root**
   - Configure your web server to serve from `/public` directory
   - Or access via `http://localhost/jassnet-incame/public/`

6. **Access Application**
   - Open browser to `http://localhost/jassnet-incame`
   - Login credentials:
     - Username: `admin`
     - Password: `password` (change on first login)

### Environment Configuration

Create a `.env` file in the root directory:
```
APP_ENV=development
APP_DEBUG=true
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=jassnet_bms
APP_URL=http://localhost/jassnet-incame
SMS_PROVIDER=custom
SMS_API_KEY=your_key_here
```

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
├── config/                    # Configuration and initialization
│   ├── config.php            # Application configuration
│   ├── Autoloader.php        # PSR-4 autoloader
│   └── init.php              # Application initialization
├── app/                      # Application source code
│   ├── core/                 # Core framework classes
│   │   ├── Database.php      # PDO database wrapper
│   │   ├── BaseModel.php     # Base model with CRUD operations
│   │   └── BaseController.php # Base controller with common methods
│   ├── models/               # Data models
│   │   ├── User.php          # User model
│   │   ├── Income.php        # Income model
│   │   ├── ExpenseRequest.php # Expense request model
│   │   ├── Inventory.php      # Inventory model
│   │   └── StationRequest.php # Station request model
│   ├── controllers/          # Controller classes
│   │   ├── AuthController.php         # Authentication & authorization
│   │   ├── DashboardController.php    # Dashboard operations
│   │   ├── IncomeController.php       # Income management
│   │   ├── ExpenseController.php      # Expense requests
│   │   ├── InventoryController.php    # Inventory management
│   │   ├── StationController.php      # Station requests
│   │   ├── UserController.php         # User management
│   │   ├── ReportController.php       # Reports & analytics
│   │   └── ProfileController.php      # User profile
│   ├── views/                # View templates (PHP)
│   │   ├── layouts/          # Layout templates
│   │   │   ├── main.php      # Main layout
│   │   │   ├── header.php    # Header template
│   │   │   ├── sidebar.php   # Sidebar navigation
│   │   │   └── footer.php    # Footer template
│   │   ├── auth/             # Authentication views
│   │   │   ├── login.php
│   │   │   └── change_password.php
│   │   ├── dashboard/        # Dashboard views
│   │   │   └── index.php
│   │   ├── income/           # Income views
│   │   ├── expenses/         # Expense views
│   │   ├── inventory/        # Inventory views
│   │   ├── stations/         # Station views
│   │   ├── users/            # User management views
│   │   ├── reports/          # Report views
│   │   └── profile/          # Profile views
│   └── helpers/              # Helper classes
│       ├── ValidationHelper.php  # Input validation
│       ├── FileHelper.php        # File operations
│       └── SMSHelper.php         # SMS operations
├── public/                   # Public assets
│   ├── index.php             # Application entry point
│   ├── assets/
│   │   ├── css/
│   │   │   └── style.css
│   │   ├── js/
│   │   │   └── main.js
│   │   └── images/
│   └── uploads/              # User uploaded files
├── includes/                 # Legacy includes (backward compatibility)
│   ├── header.php
│   ├── footer.php
│   ├── functions.php
│   └── jassnet_sms.php
├── database.sql              # Database schema
├── config.php                # Legacy config (deprecated)
├── index.php                 # Legacy entry point
└── README.md
```

## Usage

1. **Login** with appropriate credentials
2. **Dashboard** shows key metrics and recent activity
   - Metrics adjust automatically based on user role (Super Admin, Director, Manager, Accountant, Technician, Sales)
   - Interactive charts (income vs expenses, customer growth, equipment usage, station progress)
   - Quick activity tables and low‑stock alerts
3. **Income**: Record customer payments
4. **Expenses**: Submit requests, approve/reject based on role
5. **Inventory**: Manage stock levels and issue equipment
6. **Stations**: Request and manage network installations
7. **Reports**: Generate and export financial reports

## Development Guide

### Creating a New Model

Models extend `BaseModel` and handle database operations:

```php
<?php
namespace App\Models;

use App\Core\BaseModel;

class MyModel extends BaseModel
{
    protected $table = 'my_table';
    protected $primaryKey = 'id';
    protected $fillable = ['column1', 'column2', 'column3'];
    protected $timestamps = true;

    public function customMethod()
    {
        // Custom query or business logic
        return $this->getAll(['*'], ['status' => 'active']);
    }
}
```

### Creating a New Controller

Controllers extend `BaseController` and handle requests:

```php
<?php
namespace App\Controllers;

use App\Core\BaseController;
use App\Models\MyModel;

class MyController extends BaseController
{
    private $myModel;

    public function __construct()
    {
        parent::__construct();
        $this->requirePermission(['Super Admin', 'Manager']);
        $this->myModel = new MyModel();
    }

    public function index()
    {
        $items = $this->myModel->getAll();
        
        $this->data = [
            'items' => $items,
            'user' => $this->user,
            'message' => $this->getMessage(),
        ];

        $this->render('myview/index', $this->data);
    }

    public function store()
    {
        if (!$this->isPost()) {
            $this->redirect(APP_URL . '/myview.php');
        }

        if (!$this->validateRequired(['field1', 'field2'], $_POST)) {
            $this->error('All fields are required');
            $this->redirect(APP_URL . '/myview.php');
        }

        $id = $this->myModel->create([
            'field1' => $this->sanitize($this->post('field1')),
            'field2' => $this->sanitize($this->post('field2')),
        ]);

        if ($id) {
            $this->logActivity('CREATE', 'Created new item', 'my_table', $id);
            $this->success('Item created successfully');
            $this->redirect(APP_URL . '/myview.php?id=' . $id);
        }

        $this->error('Failed to create item');
        $this->redirect(APP_URL . '/myview.php');
    }
}
```

### Creating a New View

Views are PHP templates that display data:

```php
<?php include 'app/views/layouts/header.php'; ?>

<div class="container">
    <h1><?php echo htmlspecialchars($title); ?></h1>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message['type']); ?>">
            <?php echo htmlspecialchars($message['text']); ?>
        </div>
    <?php endif; ?>

    <!-- Your content here -->
</div>

<?php include 'app/views/layouts/footer.php'; ?>
```

### BaseModel Methods

Common methods available on all models:

```php
// CRUD Operations
$model->create($data);                    // Create record
$model->find($id);                        // Find by ID
$model->findBy($where);                   // Find by condition
$model->update($id, $data);               // Update record
$model->delete($id);                      // Delete record

// Query Operations
$model->getAll($columns, $where, $orderBy, $limit);  // Get multiple
$model->count($where);                    // Count records
$model->exists($where);                   // Check existence
$model->paginate($page, $perPage, $where, $orderBy); // Paginate
$model->rawQuery($query, $params);        // Execute raw query

// Validation
$model->addError($field, $message);       // Add error
$model->getErrors();                      // Get all errors
$model->hasErrors();                      // Check has errors
$model->clearErrors();                    // Clear errors
```

### BaseController Methods

Common methods available on all controllers:

```php
// Authentication & Authorization
$this->isLoggedIn();                      // Check login status
$this->requireLogin();                    // Require login
$this->hasPermission($roles);             // Check permission
$this->requirePermission($roles);         // Require specific permission
$this->getCurrentUser();                  // Get current user

// HTTP & Data
$this->get($key);                         // Get GET parameter
$this->post($key);                        // Get POST parameter
$this->request($key);                     // Get REQUEST parameter
$this->isPost();                          // Check POST method
$this->isGet();                           // Check GET method

// View & Response
$this->render($view, $data);              // Render view
$this->json($data, $statusCode);          // JSON response
$this->redirect($url);                    // Redirect
$this->sanitize($data);                   // Sanitize input

// Messages
$this->success($message);                 // Success message
$this->error($message);                   // Error message
$this->warning($message);                 // Warning message
$this->getMessage();                      // Get message from session

// Validation
$this->validateRequired($fields, $data);  // Require fields
$this->validateEmail($email);             // Validate email
$this->validatePassword($password);       // Validate password

// Security
$this->getCsrfToken();                    // Get CSRF token
$this->verifyCsrfToken($token);          // Verify CSRF token
$this->logActivity($action, $desc, $table, $id); // Log action
```

### Helper Classes

#### ValidationHelper
```php
use App\Helpers\ValidationHelper;

ValidationHelper::email($email);          // Validate email
ValidationHelper::phone($phone);          // Validate phone
ValidationHelper::password($password);    // Validate password strength
ValidationHelper::numeric($value, $min, $max);
ValidationHelper::alphanumeric($string);
ValidationHelper::username($username);
ValidationHelper::dateFormat($date, $format);
```

#### FileHelper
```php
use App\Helpers\FileHelper;

FileHelper::upload($file, $directory);    // Upload file
FileHelper::delete($filename, $directory); // Delete file
FileHelper::exists($filename, $directory); // Check existence
FileHelper::getFileSize($filename);       // Get file size
FileHelper::formatBytes($bytes);          // Format bytes
```

## Best Practices

### Security
- Always validate and sanitize user input
- Use prepared statements (automatic with BaseModel)
- Implement CSRF protection for forms
- Check permissions before performing actions
- Use bcrypt for password hashing
- Enable HTTPS in production
- Set security headers

### Code Organization
- Keep business logic in Models
- Keep request handling in Controllers
- Keep presentation in Views
- Use Helpers for reusable utilities
- Follow PSR-4 autoloading standard
- Use meaningful names for classes, methods, functions

### Database
- Use prepared statements (automatic)
- Index frequently searched columns
- Write efficient queries
- Use transactions for related updates
- Always handle NULL values
- Document complex queries

### Performance
- Use pagination for large datasets
- Cache frequently accessed data
- Optimize database queries
- Minimize HTTP requests
- Compress assets (CSS, JS)
- Use CDN for static assets

### Error Handling
- Log errors for debugging
- Show user-friendly messages
- Don't expose sensitive information
- Handle exceptions gracefully
- Use try-catch for critical operations

## API Reference

### Database Query Examples

```php
$userModel = new User();

// Get all users
$users = $userModel->getAll();

// Get with conditions
$active = $userModel->getAll(['*'], ['status' => 'active']);

// Get with ordering and limit
$recent = $userModel->getAll(['*'], [], 'created_at DESC', 10);

// Find specific user
$user = $userModel->find(1);

// Find by multiple conditions
$user = $userModel->findBy(['email' => 'user@example.com', 'status' => 'active']);

// Count records
$total = $userModel->count();
$activCount = $userModel->count(['status' => 'active']);

// Paginate
$page = $userModel->paginate(1, 25);
// Returns: items, current_page, total, total_pages, has_next, has_prev
```

## Support

For issues, questions, or contributions:
- Check the documentation in README.md
- Review the codebase comments
- Follow the development guide
- Test changes thoroughly
- Submit issues with detailed information
- Follow coding standards

## License

Proprietary - All rights reserved to JASSNET Tech Company