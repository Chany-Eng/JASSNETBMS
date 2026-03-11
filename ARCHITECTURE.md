# JASSNET BMS v2.0 - Architecture Refactoring Guide

## Overview

The JASSNET Business Management System has been refactored from a procedural PHP structure to a professional modular MVC (Model-View-Controller) architecture. This document outlines the changes, benefits, and migration path.

## Major Changes

### Before (v1.0)
- **Procedural PHP** with mixed logic and presentation
- **Direct database queries** in page files
- **Global functions** for business logic
- **No separation of concerns**
- **MySQLi object-oriented** interface
- **Session management** scattered throughout
- **File uploads** handled inline

### After (v2.0)
- **MVC Pattern** with clear separation
- **Object-oriented design** with inheritance
- **Models** for database operations
- **Controllers** for business logic
- **Views** for presentation only
- **Helpers** for reusable utilities
- **PDO** with prepared statements
- **Centralized** configuration
- **PSR-4** autoloading
- **Single responsibility** principle

## Core Components

### 1. Database Layer (App\Core\Database)

**Key Improvements:**
- PDO (more secure and portable than MySQLi)
- Prepared statements (automatic SQL injection prevention)
- Singleton pattern (single database connection)
- Fluent interface for queries
- Transaction support

**Usage:**
```php
$db = Database::getInstance();
$db->prepare("SELECT * FROM users WHERE id = ?")->bind(':id', $id)->fetch();
```

### 2. Models (App\Models\*)

**Key Improvements:**
- Inherit from BaseModel for standard CRUD operations
- Custom business logic methods
- Validation support
- Consistent interface across all models

**Models Included:**
- User (authentication, user management)
- Income (revenue tracking)
- ExpenseRequest (expense approval workflow)
- Inventory (equipment and supplies)
- StationRequest (network setup projects)

### 3. Controllers (App\Controllers\*)

**Key Improvements:**
- Request handling and validation
- Permission checking
- Response rendering
- Activity logging
- Error handling

**Controllers Included:**
- AuthController (login, logout, password change)
- DashboardController (dashboard data)
- IncomeController (income management)
- ExpenseController (expense handling)
- InventoryController (inventory ops)
- StationController (station management)
- UserController (user management)
- ReportController (reports)
- ProfileController (user profile)

### 4. Helpers (App\Helpers\*)

**Utilities Provided:**
- ValidationHelper (input validation)
- FileHelper (file operations)
- SMSHelper (SMS sending)

## Migration Path

### Phase 1: Framework Setup (Current)
- [x] Create core framework classes
- [x] Implement Database wrapper
- [x] Create BaseModel and BaseController
- [x] Set up autoloading
- [x] Configure application
- [ ] Create reusable components

### Phase 2: Models (Next)
- [ ] Migrate all database operations to models
- [ ] Implement business logic methods
- [ ] Add validation to models

### Phase 3: Controllers
- [ ] Create controllers for each feature
- [ ] Migrate request handling
- [ ] Implement permission checks
- [ ] Add activity logging

### Phase 4: Views
- [ ] Create consistent view templates
- [ ] Use Bootstrap 5.3 components
- [ ] Implement layouts
- [ ] Add form handling

### Phase 5: Testing & Deployment
- [ ] Unit testing
- [ ] Integration testing
- [ ] Performance optimization
- [ ] Production deployment

## Benefits of New Architecture

### Maintainability
- **Organized code structure** makes it easier to find and modify code
- **Single responsibility principle** means each class has one reason to change
- **Clear dependencies** reduce side effects

### Scalability
- **Easy to add new features** without affecting existing code
- **Models can be reused** across controllers
- **Helpers can be shared** across application

### Security
- **Prepared statements** eliminate SQL injection
- **Centralized validation** ensures consistent checks
- **Permission checking** at controller level
- **CSRF protection** mechanism
- **Password hashing** with bcrypt

### Testing
- **Unit testable** - can test models and controllers independently
- **Mockable dependencies** - easier to create test doubles
- **Clear interfaces** - easier to understand what to test

### Development
- **Clear patterns** - developers know where to put code
- **Code reuse** - reduce copy-paste bugs
- **Documentation** - architecture is self-documenting
- **IDE support** - better autocomplete and refactoring

## Legacy Support

The old files are still present for backward compatibility:
- `includes/` - Original helper functions (deprecated)
- `config.php` - Original config (deprecated)
- Legacy page files can still be used

**Recommended:** Migrate to new structure incrementally.

## Quick Start Guide

### Creating a New Feature

1. **Create Model** (`app/models/Feature.php`)
   ```php
   class Feature extends BaseModel {
       protected $table = 'features';
       protected $fillable = ['name', 'description'];
   }
   ```

2. **Create Controller** (`app/controllers/FeatureController.php`)
   ```php
   class FeatureController extends BaseController {
       public function index() {
           $model = new Feature();
           $this->render('feature/index', [
               'items' => $model->getAll()
           ]);
       }
   }
   ```

3. **Create View** (`app/views/feature/index.php`)
   ```php
   <?php foreach ($items as $item): ?>
       <div><?php echo htmlspecialchars($item['name']); ?></div>
   <?php endforeach; ?>
   ```

## Configuration

### environment Variables (config/config.php)

```php
// Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'jassnet_bms');

// Application
define('APP_ENV', 'development');
define('APP_DEBUG', true);

// Password Policy
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_EXPIRATION_DAYS', 28);
```

## Common Patterns

### Database Queries

```php
use App\Models\User;

$userModel = new User();

// Single record
$user = $userModel->find(1);

// Multiple records
$users = $userModel->getAll();

// With conditions
$active = $userModel->getAll(['*'], ['status' => 'active']);

// Count
$count = $userModel->count(['role' => 'admin']);

// Paginate
$page = $userModel->paginate(1, 25);
```

### Controller Patterns

```php
// Check permission
$this->requirePermission(['Admin', 'Manager']);

// Validate input
$this->validateRequired(['email', 'password'], $_POST);

// Render view
$this->render('dashboard/index', ['data' => $data]);

// JSON response
$this->json(['success' => true, 'data' => $result]);

// Error handling
$this->error('Operation failed');
$this->redirect(APP_URL . '/dashboard');
```

## Performance Considerations

1. **Database Queries**
   - Models cache frequently accessed data
   - Use pagination for large datasets
   - Index foreign keys

2. **Caching**
   - Implement caching for dashboard stats
   - Cache large report queries
   - Use query result caching

3. **Assets**
   - Minify CSS and JavaScript
   - Use CDN for Bootstrap/Chart.js
   - Compress images

## Future Enhancements

1. **Dependency Injection** - Replace constructor dependency injection
2. **Service Layer** - Add service classes for complex operations
3. **Event System** - Trigger events on important actions
4. **Caching** - Redis/Memcached for performance
5. **API** - RESTful API for mobile/third-party integration
6. **Testing** - PHPUnit test suite
7. **Validation Rules** - Declarative validation
8. **Middleware** - Request/response middleware

## Support & Documentation

- See README.md for complete documentation
- Code comments explain complex logic
- Each class has clear method documentation
- Examples provided in controllers and models

## Questions?

Refer to:
- README.md - Installation and usage
- Code comments - Implementation details
- Controller examples - Common patterns
- Model examples - Database operations
