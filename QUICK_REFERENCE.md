# JASSNET BMS v2.0 - Developer Quick Reference

## Quick Start

### 1. Setup
```bash
# Database
mysql -u root jassnet_bms < database.sql

# Configuration
# Edit config/config.php with your database credentials

# Access
http://localhost/jassnet-incame
```

### 2. Login
- Username: `admin`
- Password: `password`

## File Organization

```
app/
├── core/          # Framework base classes
├── models/        # Data models
├── controllers/   # Request handlers
├── views/         # Presentation templates
└── helpers/       # Utility functions

config/
├── config.php     # Configuration constants
├── init.php       # Application setup
└── Autoloader.php # PSR-4 autoloader
```

## Common Tasks

### Create a Model

```php
<?php
namespace App\Models;
use App\Core\BaseModel;

class MyModel extends BaseModel {
    protected $table = 'my_table';
    protected $primaryKey = 'id';
    protected $fillable = ['field1', 'field2'];
    protected $timestamps = true;
}
```

### Create a Controller

```php
<?php
namespace App\Controllers;
use App\Core\BaseController;

class MyController extends BaseController {
    public function index() {
        $this->render('view/index', [
            'data' => 'value'
        ]);
    }
}
```

### Use a Model

```php
$model = new MyModel();

// Find
$item = $model->find(1);
$item = $model->findBy(['status' => 'active']);

// Get all
$items = $model->getAll();

// Create
$id = $model->create(['field1' => 'value1']);

// Update
$model->update($id, ['field1' => 'new_value']);

// Delete
$model->delete($id);

// Count
$count = $model->count(['status' => 'active']);

// Paginate
$page = $model->paginate(1, 25);
```

### Query Examples

```php
// Simple query
$users = $userModel->getAll();

// With conditions
$active = $userModel->getAll(['*'], ['status' => 'active']);

// With ordering
$recent = $userModel->getAll(
    ['*'],
    [],
    'created_at DESC'
);

// With limit
$top10 = $userModel->getAll(['*'], [], null, 10);

// Combined
$data = $userModel->getAll(
    ['id', 'name', 'email'],
    ['status' => 'active'],
    'name ASC',
    20
);
```

### Validation

```php
use App\Helpers\ValidationHelper;

// Email
if (!ValidationHelper::email($email)) { }

// Phone
if (!ValidationHelper::phone($phone)) { }

// Password
$errors = ValidationHelper::password($password);
if ($errors !== true) {
    foreach ($errors as $error) { }
}

// Numeric
if (!ValidationHelper::numeric($value, 0, 100)) { }

// String length
if (!ValidationHelper::stringLength($str, 3, 50)) { }
```

### File Operations

```php
use App\Helpers\FileHelper;

// Upload
$result = FileHelper::upload($_FILES['file']);
if (isset($result['success'])) {
    $filename = $result['success'];
}

// Delete
FileHelper::delete($filename);

// Check exists
if (FileHelper::exists($filename)) { }

// Get file size
$size = FileHelper::getFileSize($filename);

// Format bytes
echo FileHelper::formatBytes(1024000); // "1000 KB"
```

### Authentication

```php
// Check logged in
if (!$this->isLoggedIn()) {
    $this->redirect(APP_URL . '/index.php');
}

// Get current user
$user = $this->getCurrentUser();

// Check permission
if (!$this->hasPermission(['Admin', 'Manager'])) {
    $this->error('No permission');
}

// Require permission
$this->requirePermission(['Super Admin']);
```

### Messages

```php
// Set message
$this->success('Operation successful');
$this->error('Operation failed');
$this->warning('Warning message');

// Get message (in view)
$message = $this->getMessage();
if ($message) {
    echo $message['text']; // Message text
    echo $message['type']; // 'success', 'error', 'warning'
}
```

### Views

```php
// Render view
$this->render('folder/view', ['key' => 'value']);

// In view template
<?php echo htmlspecialchars($key); ?>
```

### Database Direct Access

```php
use App\Core\Database;

$db = Database::getInstance();

// Prepare query
$db->prepare("SELECT * FROM users WHERE id = :id");
$db->bind(':id', 1);

// Fetch one
$user = $db->fetch();

// Fetch all
$db->prepare("SELECT * FROM users");
$users = $db->fetchAll();
```

### CSRF Protection

```php
// Get token
$token = $this->getCsrfToken();

// In form
<input type="hidden" name="csrf_token" value="<?php echo $token; ?>">

// Verify
if (!$this->verifyCsrfToken($this->post('csrf_token'))) {
    $this->error('Invalid token');
}
```

### Activity Logging

```php
$this->logActivity(
    'ACTION',                    // Action type
    'Description',               // Description
    'table_name',                // Table name
    123                          // Record ID (optional)
);
```

## Constants

### Expense Status
```php
EXPENSE_STATUS_PENDING      // 'Pending'
EXPENSE_STATUS_APPROVED     // 'Approved'
EXPENSE_STATUS_COMPLETED    // 'Completed'
EXPENSE_STATUS_REJECTED     // 'Rejected'
```

### Station Status
```php
STATION_STATUS_PENDING      // 'Pending'
STATION_STATUS_APPROVED     // 'Approved'
STATION_STATUS_IN_PROGRESS  // 'In Progress'
STATION_STATUS_COMPLETED    // 'Completed'
STATION_STATUS_REJECTED     // 'Rejected'
```

### Permissions
```php
CAN_MANAGE_USERS            // ['Super Admin', 'Director']
CAN_APPROVE_EXPENSES        // ['Super Admin', 'Director', 'Manager', 'Accountant']
CAN_MANAGE_INVENTORY        // ['Super Admin', 'Store Keeper', 'Manager']
CAN_REQUEST_EXPENSE         // ['Super Admin', 'Technician', 'Sales']
CAN_REQUEST_STATION         // ['Super Admin', 'Technician']
```

### Paths
```php
APP_ROOT                    // /path/to/jassnet-incame
APP_URL                     // http://localhost/jassnet-incame
PUBLIC_PATH                 // /path/to/jassnet-incame/public
UPLOADS_PATH                // /path/to/jassnet-incame/uploads
ASSETS_PATH                 // /assets
```

### Formats
```php
DATE_FORMAT                 // 'Y-m-d'
DATETIME_FORMAT             // 'Y-m-d H:i:s'
DISPLAY_DATE_FORMAT         // 'M d, Y'
DISPLAY_DATETIME_FORMAT     // 'M d, Y h:i A'
```

## Methods Reference

### BaseModel Methods
```php
getAll($columns, $where, $orderBy, $limit)
find($id)
findBy($where)
create($data)
update($id, $data)
delete($id)
deleteWhere($where)
count($where)
paginate($page, $perPage, $where, $orderBy)
exists($where)
rawQuery($query, $params)
```

### BaseController Methods
```php
isLoggedIn()
hasPermission($roles)
requirePermission($roles)
getCurrentUser()
render($view, $data)
json($data, $statusCode)
redirect($url)
sanitize($data)
success($message)
error($message)
warning($message)
getMessage()
validateRequired($fields, $data)
validateEmail($email)
validatePassword($password)
post($key)
query($key)
request($key)
isPost()
isGet()
getCsrfToken()
verifyCsrfToken($token)
logActivity($action, $description, $table, $recordId)
```

## Debugging

### Enable Debug Mode
```php
// In config/config.php
define('APP_DEBUG', true);
define('APP_ENV', 'development');
```

### Log Messages
```php
error_log('Debug message');
```

### Database Queries
```php
$db = Database::getInstance();
echo $db->getQueryCount();
```

## Best Practices

1. **Always validate input** - Use ValidationHelper
2. **Sanitize output** - Use htmlspecialchars()
3. **Use prepared statements** - Automatic with BaseModel
4. **Check permissions** - Use requirePermission()
5. **Log activities** - Use logActivity()
6. **Handle errors** - Use try-catch
7. **Use transactions** - For related updates
8. **Test thoroughly** - Before deployment

## Common Patterns

### CRUD Controller
```php
public function index() { }     // List
public function show() { }      // Detail
public function create() { }    // Form
public function store() { }     // Save
public function edit() { }      // Edit form
public function update() { }    // Update
public function delete() { }    // Delete
```

### Form Flow
```php
// GET: Display form
public function create() {
    $this->render('create', ['csrf_token' => $this->getCsrfToken()]);
}

// POST: Handle submission
public function store() {
    if (!$this->verifyCsrfToken($this->post('csrf_token'))) {
        $this->error('Invalid token');
    }
    // Process form
}
```

## Troubleshooting

### Class not found
- Check namespace
- Check file path
- Verify autoloader

### Database error
- Check config/config.php
- Check PDO driver installed
- Check MySQL running

### Permission denied
- Check roles defined
- Check role in database
- Check permission constant

### Session lost
- Check session timeout
- Check cookie settings
- Check session name

## Resources

- **README.md** - Full documentation
- **ARCHITECTURE.md** - Architecture details
- **Code comments** - Implementation details
- **Example controllers** - Usage patterns

---

For more information, see the complete documentation in README.md and ARCHITECTURE.md
