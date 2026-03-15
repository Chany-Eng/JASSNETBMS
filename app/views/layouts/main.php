<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'JASSNET ERMS'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <?php
    if (isset($_GET['mark_notification']) && function_exists('markNotificationAsRead') && isset($conn)) {
        markNotificationAsRead($conn, (string) $_GET['mark_notification']);
    }
    if (function_exists('isLoggedIn') && isLoggedIn() && function_exists('appLogActivity') && isset($conn)) {
        $currentPage = basename($_SERVER['PHP_SELF']);
        $lastLoggedPage = $_SESSION['last_logged_page'] ?? '';
        $lastLoggedAt = (int) ($_SESSION['last_logged_page_at'] ?? 0);
        if ($currentPage !== $lastLoggedPage || (time() - $lastLoggedAt) > 120) {
            appLogActivity($conn, 'PAGE_VIEW', 'Opened page ' . $currentPage, 'page_views');
            $_SESSION['last_logged_page'] = $currentPage;
            $_SESSION['last_logged_page_at'] = time();
        }
    }
    ?>

    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --app-shell-bg: #eef3f8;
            --app-shell-surface: #ffffff;
            --app-shell-surface-soft: #f7fafe;
            --app-shell-border: #dbe5ef;
            --app-shell-text: #203047;
            --app-shell-muted: #71829b;
            --app-shell-accent: #2969c7;
            --app-shell-accent-dark: #1d4f98;
            --app-shell-sidebar: #12263f;
            --app-shell-sidebar-muted: #8fa5bd;
            --app-shell-footer: #0f1d31;
            --app-shell-shadow: 0 18px 42px rgba(15, 23, 42, 0.08);
        }

        body {
            background:
                radial-gradient(circle at top right, rgba(41, 105, 199, 0.12), transparent 26%),
                linear-gradient(180deg, #f8fbff 0%, var(--app-shell-bg) 100%);
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 276px;
            background: linear-gradient(180deg, var(--app-shell-sidebar) 0%, #152b45 48%, #102136 100%);
            color: #d6e2f1;
            z-index: 1000;
            overflow-y: auto;
            transition: width 0.28s ease, transform 0.28s ease;
            border-right: 1px solid rgba(219, 229, 239, 0.12);
            box-shadow: 12px 0 30px rgba(9, 16, 29, 0.16);
        }
        .sidebar nav {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .sidebar.collapsed {
            width: 88px;
        }
        .sidebar .sidebar-header {
            padding: 24px 22px 18px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(41, 105, 199, 0.16) 100%);
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .sidebar .sidebar-header h4 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #f8fbff;
        }
        .sidebar .sidebar-header p {
            margin: 0.45rem 0 0;
            font-size: 0.78rem;
            color: var(--app-shell-sidebar-muted);
        }
        .sidebar .sidebar-header .brand-icon img {
            width: 64px;
            height: 64px;
            margin-bottom: 12px;
            border-radius: 20px;
            border: 3px solid rgba(255, 255, 255, 0.88);
            background: white;
            padding: 4px;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.2);
        }
        .sidebar .menu {
            list-style: none;
            padding: 14px 12px 24px;
            margin: 0;
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
        }
        .sidebar .menu li {
            position: relative;
            margin-bottom: 6px;
        }
        .sidebar .menu li.logout-item {
            margin-top: auto;
            padding-top: 12px;
        }
        .sidebar .menu li.logout-item a {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.08);
            color: #f8fbff;
        }
        .sidebar .menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 16px;
            color: var(--app-shell-sidebar-muted);
            text-decoration: none;
            border: 1px solid transparent;
            border-radius: 16px;
            transition: background-color 0.22s ease, color 0.22s ease, border-color 0.22s ease, transform 0.22s ease;
        }
        .sidebar .menu a:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.08);
            transform: translateX(2px);
        }
        .sidebar .menu a.active {
            background: linear-gradient(135deg, rgba(41, 105, 199, 0.24) 0%, rgba(41, 105, 199, 0.12) 100%);
            color: #ffffff;
            border-color: rgba(147, 197, 253, 0.28);
            box-shadow: inset 3px 0 0 #7dd3fc;
        }
        .sidebar .menu a i {
            margin-right: 0;
            width: 20px;
            text-align: center;
        }
        .sidebar.collapsed .menu a span {
            display: none;
        }
        .sidebar.collapsed .menu a i {
            margin-right: 0;
            text-align: center;
        }
        .sidebar.collapsed .sidebar-header h4 {
            display: none;
        }
        .sidebar.collapsed .sidebar-header .brand-icon {
            margin-bottom: 0;
        }
        .sidebar.collapsed .sidebar-header p {
            display: none;
        }
        .sidebar .menu .dropdown-toggle::after {
            display: none;
        }
        .sidebar .menu .dropdown-toggle .fa-chevron-down {
            margin-left: auto;
            font-size: 0.78rem;
            color: inherit;
            transition: transform 0.22s ease;
        }
        .sidebar .menu .dropdown-toggle[aria-expanded="true"] .fa-chevron-down {
            transform: rotate(180deg);
        }
        .sidebar .menu .collapse a {
            padding: 10px 14px 10px 42px;
            font-size: 0.9rem;
            border-bottom: none;
        }
        .sidebar .menu .collapse a:hover {
            background: rgba(255, 255, 255, 0.07);
            color: #fff;
        }
        .sidebar .menu .collapse a.active {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            border-color: transparent;
            box-shadow: inset 3px 0 0 #7dd3fc;
        }
    </style>
<body>
    <?php $currentPageLabel = ucwords(str_replace('_', ' ', basename($_SERVER['PHP_SELF'], '.php'))); ?>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="brand-icon">
                <img src="<?= APP_URL ?>/assets/images/logo.png" alt="JASSNET Logo">
            </div>
            <h4>JASSNET ERMS</h4>
            <p>Professional business operations workspace</p>
        </div>
        <ul class="menu">
            <li>
                <a href="<?= APP_URL ?>/dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['announcements.php', 'announcements_latest.php', 'announcements_send.php', 'announcements_inactive.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#announcementsSubmenu" aria-controls="announcementsSubmenu" aria-expanded="<?= in_array(basename($_SERVER['PHP_SELF']), ['announcements.php', 'announcements_latest.php', 'announcements_send.php', 'announcements_inactive.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-bullhorn"></i>
                    <span>Announcements</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3 <?= in_array(basename($_SERVER['PHP_SELF']), ['announcements.php', 'announcements_latest.php', 'announcements_send.php', 'announcements_inactive.php']) ? 'show' : ''; ?>" id="announcementsSubmenu">
                    <li>
                        <a href="<?= APP_URL ?>/pages/announcements_latest.php" class="<?= basename($_SERVER['PHP_SELF']) == 'announcements_latest.php' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i>
                            <span>Latest Announcements</span>
                        </a>
                    </li>
                    <?php if ($controller->hasPermission(['Store Keeper', 'Manager', 'Director', 'Super Admin'])): ?>
                    <li>
                        <a href="<?= APP_URL ?>/pages/announcements_send.php" class="<?= basename($_SERVER['PHP_SELF']) == 'announcements_send.php' ? 'active' : ''; ?>">
                            <i class="fas fa-paper-plane"></i>
                            <span>Send Announcement</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($controller->hasPermission(['Super Admin'])): ?>
                    <li>
                        <a href="<?= APP_URL ?>/pages/announcements_inactive.php" class="<?= basename($_SERVER['PHP_SELF']) == 'announcements_inactive.php' ? 'active' : ''; ?>">
                            <i class="fas fa-archive"></i>
                            <span>Inactive Announcements</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php if ($controller->hasPermission(['Sales', 'Director', 'Accountant', 'Super Admin'])): ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['add_income.php', 'view_income.php', 'income.php', 'snippe_income_sync.php', 'snippe_sync_history.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#incomeSubmenu" aria-controls="incomeSubmenu" aria-expanded="<?= in_array(basename($_SERVER['PHP_SELF']), ['add_income.php', 'view_income.php', 'income.php', 'snippe_income_sync.php', 'snippe_sync_history.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-dollar-sign"></i>
                    <span>Income</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3 <?= in_array(basename($_SERVER['PHP_SELF']), ['add_income.php', 'view_income.php', 'income.php', 'snippe_income_sync.php', 'snippe_sync_history.php']) ? 'show' : ''; ?>" id="incomeSubmenu">
                    <?php if ($controller->hasPermission(['Sales', 'Super Admin'])): ?>
                    <li>
                        <a href="<?= APP_URL ?>/pages/add_income.php" class="<?= basename($_SERVER['PHP_SELF']) == 'add_income.php' ? 'active' : ''; ?>">
                            <i class="fas fa-plus"></i>
                            <span>Add Income</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="<?= APP_URL ?>/pages/view_income.php" class="<?= basename($_SERVER['PHP_SELF']) == 'view_income.php' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i>
                            <span>View Income</span>
                        </a>
                    </li>
                    <?php if ($controller->hasPermission(['Director', 'Super Admin'])): ?>
                    <li>
                        <a href="<?= APP_URL ?>/pages/snippe_sync_history.php" class="<?= basename($_SERVER['PHP_SELF']) == 'snippe_sync_history.php' ? 'active' : ''; ?>">
                            <i class="fas fa-history"></i>
                            <span>Snippe Sync History</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>
            <?php if ($controller->hasPermission(['Sales', 'Technician', 'Manager', 'Director', 'Accountant', 'Super Admin'])): ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['add_expense_request.php', 'view_expense_requests.php', 'expense_detail.php', 'expenses.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#expensesSubmenu" aria-controls="expensesSubmenu" aria-expanded="<?= in_array(basename($_SERVER['PHP_SELF']), ['add_expense_request.php', 'view_expense_requests.php', 'expense_detail.php', 'expenses.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-receipt"></i>
                    <span>Expenses</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3 <?= in_array(basename($_SERVER['PHP_SELF']), ['add_expense_request.php', 'view_expense_requests.php', 'expense_detail.php', 'expenses.php']) ? 'show' : ''; ?>" id="expensesSubmenu">
                    <li>
                        <a href="<?= APP_URL ?>/pages/add_expense_request.php" class="<?= basename($_SERVER['PHP_SELF']) == 'add_expense_request.php' ? 'active' : ''; ?>">
                            <i class="fas fa-plus"></i>
                            <span>Add Request</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= APP_URL ?>/pages/view_expense_requests.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['view_expense_requests.php', 'expenses.php']) ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i>
                            <span>View Requests</span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>
            <?php if ($controller->hasPermission(['Manager', 'Store Keeper', 'Super Admin', 'Technician', 'Sales', 'Director', 'Accountant'])): ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['inventory.php', 'add_inventory.php', 'inventory_items.php', 'issue_equipment.php', 'low_stock_alerts.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#inventorySubmenu" aria-controls="inventorySubmenu" aria-expanded="<?= in_array(basename($_SERVER['PHP_SELF']), ['inventory.php', 'add_inventory.php', 'inventory_items.php', 'issue_equipment.php', 'low_stock_alerts.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-boxes"></i>
                    <span>Inventory</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3 <?= in_array(basename($_SERVER['PHP_SELF']), ['inventory.php', 'add_inventory.php', 'inventory_items.php', 'issue_equipment.php', 'low_stock_alerts.php']) ? 'show' : ''; ?>" id="inventorySubmenu">
                    <?php if ($controller->hasPermission(['Store Keeper'])): ?>
                    <li>
                        <a href="<?= APP_URL ?>/pages/add_inventory.php">
                            <i class="fas fa-plus"></i>
                            <span>Add Inventory Item</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($controller->hasPermission(['Store Keeper', 'Manager', 'Super Admin', 'Technician'])): ?>
                    <li>
                        <a href="<?= APP_URL ?>/pages/inventory_items.php">
                            <i class="fas fa-list"></i>
                            <span>Inventory Items</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($controller->hasPermission(['Store Keeper', 'Manager', 'Super Admin', 'Sales', 'Technician'])): ?>
                    <li>
                        <a href="<?= APP_URL ?>/pages/issue_equipment.php">
                            <i class="fas fa-tools"></i>
                            <span>Equipment Requests</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($controller->hasPermission(['Store Keeper', 'Manager', 'Super Admin'])): ?>
                    <li>
                        <a href="<?= APP_URL ?>/pages/low_stock_alerts.php">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Low Stock Alerts</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>
            <?php if ($controller->hasPermission(['Technician', 'Sales', 'Manager', 'Director', 'Accountant', 'Store Keeper', 'Super Admin'])): ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['stations.php', 'request_new_station_setup.php', 'station_detail.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#stationsSubmenu" aria-controls="stationsSubmenu" aria-expanded="<?= in_array(basename($_SERVER['PHP_SELF']), ['stations.php', 'request_new_station_setup.php', 'station_detail.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-broadcast-tower"></i>
                    <span>Stations</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3 <?= in_array(basename($_SERVER['PHP_SELF']), ['stations.php', 'request_new_station_setup.php', 'station_detail.php']) ? 'show' : ''; ?>" id="stationsSubmenu">
                    <li>
                        <a href="<?= APP_URL ?>/pages/stations.php#station-setup-requests">
                            <i class="fas fa-list"></i>
                            <span>Station Setup Requests</span>
                        </a>
                    </li>
                    <?php if ($controller->hasPermission(['Technician'])): ?>
                    <li>
                        <a href="<?= APP_URL ?>/pages/request_new_station_setup.php">
                            <i class="fas fa-plus"></i>
                            <span>Request New Station Setup</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>
            <?php if ($controller->hasPermission(['Super Admin'])): ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['users.php', 'add_user.php', 'supported_banks.php', 'admin_history.php', 'profile.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#usersSubmenu" aria-controls="usersSubmenu" aria-expanded="<?= in_array(basename($_SERVER['PHP_SELF']), ['users.php', 'add_user.php', 'supported_banks.php', 'admin_history.php', 'profile.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3 <?= in_array(basename($_SERVER['PHP_SELF']), ['users.php', 'add_user.php', 'supported_banks.php', 'admin_history.php', 'profile.php']) ? 'show' : ''; ?>" id="usersSubmenu">
                    <li>
                        <a href="<?= APP_URL ?>/pages/users.php#users-list">
                            <i class="fas fa-list"></i>
                            <span>List of Users</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= APP_URL ?>/pages/add_user.php">
                            <i class="fas fa-user-plus"></i>
                            <span>Add User</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= APP_URL ?>/pages/supported_banks.php" class="<?= basename($_SERVER['PHP_SELF']) == 'supported_banks.php' ? 'active' : ''; ?>">
                            <i class="fas fa-university"></i>
                            <span>Supported Banks</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= APP_URL ?>/pages/admin_history.php" class="<?= basename($_SERVER['PHP_SELF']) == 'admin_history.php' ? 'active' : ''; ?>">
                            <i class="fas fa-history"></i>
                            <span>Admin History</span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>
            <?php if ($controller->hasPermission(['Director', 'Super Admin', 'Accountant'])): ?>
            <li>
                <a href="<?= APP_URL ?>/pages/reports.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <?php endif; ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['payroll.php', 'create_salary_request.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#payrollSubmenu" aria-controls="payrollSubmenu" aria-expanded="<?= in_array(basename($_SERVER['PHP_SELF']), ['payroll.php', 'create_salary_request.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-money-check-dollar"></i>
                    <span>Payroll</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3 <?= in_array(basename($_SERVER['PHP_SELF']), ['payroll.php', 'create_salary_request.php']) ? 'show' : ''; ?>" id="payrollSubmenu">
                    <?php if ($controller->hasPermission(['Accountant', 'Super Admin'])): ?>
                    <li>
                        <a href="<?= APP_URL ?>/pages/create_salary_request.php" class="<?= basename($_SERVER['PHP_SELF']) == 'create_salary_request.php' ? 'active' : ''; ?>">
                            <i class="fas fa-plus-circle"></i>
                            <span>Create Salary Request</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="<?= APP_URL ?>/pages/payroll.php" class="<?= basename($_SERVER['PHP_SELF']) == 'payroll.php' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i>
                            <span>Salary Requests</span>
                        </a>
                    </li>
                </ul>
            </li>
            <li class="logout-item">
                <a href="<?= APP_URL ?>/logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="main-content">
        <!-- Top header/nav -->
        <header class="top-header">
            <nav class="navbar navbar-expand-lg navbar-light">
                <div class="container-fluid d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <button class="sidebar-toggle d-lg-none" id="sidebarToggle">
                            <i class="fas fa-bars"></i>
                        </button>
                        <button class="sidebar-toggle d-none d-lg-block" id="sidebarCollapse">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div class="app-shell-heading">
                            <div class="eyebrow">JASSNET Admin Panel</div>
                            <h1 class="title"><?= htmlspecialchars($currentPageLabel) ?></h1>
                            <div class="subtitle">Modern workspace for reports, approvals, finance, inventory, and operations.</div>
                        </div>
                    </div>

                        <div class="app-topbar-actions">
                            <span class="app-topbar-chip"><i class="fas fa-calendar-day"></i> <?= date('d M Y') ?></span>
                        <?php
                        $displayName = trim((string) ($user['full_name'] ?? '')) !== '' ? (string) $user['full_name'] : (string) ($user['username'] ?? 'User');
                        $displayUsername = trim((string) ($user['username'] ?? ''));
                        $displayRole = appFormatRoleList((string) ($user['role'] ?? ''));
                        $displayMeta = $displayRole;
                        if ($displayUsername !== '' && strcasecmp($displayName, $displayUsername) !== 0) {
                            $displayMeta = '@' . $displayUsername . ' • ' . $displayRole;
                        }
                        $profilePhoto = $user['profile_photo'] ?? '';
                        $actionNotifications = (isset($conn) && function_exists('getUserActionNotifications')) ? getUserActionNotifications($conn) : [];
                        $unreadNotifications = array_values(array_filter($actionNotifications, static function ($item) {
                            return empty($item['is_read']);
                        }));
                        $notificationCount = count($unreadNotifications);
                        $notificationHeading = 'Requests Requiring Action';
                        $notificationSubheading = 'Open queue items and continue the next workflow steps.';
                        if (in_array('Manager', appParseRoleList((string) ($user['role'] ?? '')), true)) {
                            $notificationHeading = 'Manager Approval Queue';
                            $notificationSubheading = 'Review approvals waiting for manager action.';
                        } elseif (in_array('Accountant', appParseRoleList((string) ($user['role'] ?? '')), true)) {
                            $notificationHeading = 'Accountant Processing Queue';
                            $notificationSubheading = 'Process payouts, receipts, and final approvals.';
                        } elseif (in_array('Director', appParseRoleList((string) ($user['role'] ?? '')), true)) {
                            $notificationHeading = 'Director Decision Queue';
                            $notificationSubheading = 'Review high-level approvals and pending escalations.';
                        }
                        ?>
                        <div class="dropdown me-3">
                            <button class="btn app-icon-button position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell"></i>
                                <?php if ($notificationCount > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $notificationCount > 9 ? '9+' : $notificationCount; ?></span>
                                <?php endif; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end app-notification-menu" aria-labelledby="notificationDropdown">
                                <li class="dropdown-header d-flex justify-content-between align-items-center">
                                    <span>
                                        <div class="fw-semibold"><?= htmlspecialchars($notificationHeading); ?></div>
                                        <div class="small text-muted fw-normal"><?= htmlspecialchars($notificationSubheading); ?></div>
                                    </span>
                                    <span class="badge bg-primary"><?= $notificationCount; ?> unread</span>
                                </li>
                                <?php if (!empty($actionNotifications)): ?>
                                    <?php foreach ($actionNotifications as $notification): ?>
                                        <li>
                                            <a class="dropdown-item py-2 <?= !empty($notification['is_read']) ? 'notification-item-read' : 'notification-item-unread'; ?>" href="<?= APP_URL . '/' . ltrim((string) ($notification['target'] ?? ''), '/'); ?>">
                                                <div class="fw-semibold"><?= htmlspecialchars((string) ($notification['title'] ?? 'Request notification')); ?></div>
                                                <div class="small text-muted"><?= htmlspecialchars((string) ($notification['description'] ?? '')); ?></div>
                                                <div class="small <?= !empty($notification['is_read']) ? 'text-muted' : 'text-primary'; ?>"><?= !empty($notification['is_read']) ? 'Read' : 'Unread'; ?></div>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li><span class="dropdown-item-text text-muted">No pending request notifications.</span></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                            <div class="me-3">
                            <?php if (!empty($profilePhoto)): ?>
                                <img src="<?= APP_URL ?>/uploads/<?= htmlspecialchars($profilePhoto); ?>" class="user-avatar" alt="User Photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                                <span class="user-avatar-fallback" style="display:none;"><i class="fas fa-user"></i></span>
                            <?php else: ?>
                                <span class="user-avatar-fallback"><i class="fas fa-user"></i></span>
                            <?php endif; ?>
                        </div>
                            <div class="app-user-summary me-3">
                                <div class="app-user-meta">
                                    <strong><?= htmlspecialchars($displayName); ?></strong>
                                    <span><?= htmlspecialchars($displayMeta); ?></span>
                                </div>
                            </div>

                        <div class="dropdown">
                                <button class="btn app-icon-button dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= APP_URL ?>/pages/profile.php"><i class="fas fa-user-edit"></i> Profile</a></li>
                                <li><a class="dropdown-item" href="<?= APP_URL ?>/change_password.php"><i class="fas fa-key"></i> Change Password</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?= APP_URL ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>
        </header>

        <div class="content-wrapper">
            <div class="app-loading-overlay" id="appLoadingOverlay" aria-hidden="true">
                <div class="app-loading-card">
                    <div class="app-loading-spinner"></div>
                    <h5 data-loading-title>Loading page</h5>
                    <p data-loading-message>Please wait while JASSNET opens the selected page.</p>
                </div>
            </div>
            <div class="app-page-content">
                <?= $content ?>
            </div>
            <footer class="app-site-footer text-center text-lg-end">
                <div class="small">&copy; <?= date('Y') ?> JASSNET Incame. All rights reserved.</div>
            </footer>
        </div>
    </div>

    <?php
    $showWelcomeToast = !empty($_SESSION['login_success']);
    $welcomeUserName = trim((string) ($_SESSION['login_success_name'] ?? ($user['full_name'] ?? ($user['username'] ?? 'User'))));
    $welcomeRole = appFormatRoleList((string) ($_SESSION['role'] ?? ($user['role'] ?? '')));
    $authTransition = $_SESSION['auth_transition'] ?? null;
    $showLoginTransition = is_array($authTransition) && (($authTransition['type'] ?? '') === 'login');
    $loginTransitionName = trim((string) ($authTransition['name'] ?? $welcomeUserName));
    $loginTransitionTitle = 'Login successful';
    $loginTransitionCopy = ($loginTransitionName !== '' ? $loginTransitionName : 'User') . ', workspace yako iko tayari.';
    $welcomeToastTitle = 'Karibu tena, ' . ($welcomeUserName !== '' ? $welcomeUserName : 'User');
    $welcomeToastCopy = 'Workspace yako iko tayari. Endelea na approvals, reports, au quick actions zako.';
    if (appCurrentSessionHasRole(['Manager'])) {
        $welcomeToastCopy = 'Queue ya approvals inakusubiri. Fungua requests na uendelee na hatua zinazofuata.';
    } elseif (appCurrentSessionHasRole(['Accountant'])) {
        $welcomeToastCopy = 'Processing queue iko tayari. Kagua payouts, receipts, na final approvals zako.';
    } elseif (appCurrentSessionHasRole(['Director'])) {
        $welcomeToastCopy = 'Maamuzi ya mwisho yanakusubiri. Pitia approvals na operational priorities za leo.';
    } elseif (appCurrentSessionHasRole(['Store Keeper'])) {
        $welcomeToastCopy = 'Stock na issue requests ziko tayari. Angalia low stock na approvals za store.';
    } elseif (appCurrentSessionHasRole(['Technician'])) {
        $welcomeToastCopy = 'Station worklist yako iko tayari. Pitia progress updates na installation tasks.';
    } elseif (appCurrentSessionHasRole(['Sales'])) {
        $welcomeToastCopy = 'Lead na request follow-up zako ziko tayari. Endelea na entries na receipt updates.';
    }
    unset($_SESSION['login_success'], $_SESSION['login_success_name'], $_SESSION['auth_transition']);
    ?>
    <?php if ($showWelcomeToast): ?>
    <div class="toast-container app-welcome-toast-container position-fixed top-0 end-0 p-3">
        <div id="loginWelcomeToast" class="toast app-welcome-toast border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <div class="app-welcome-toast-title"><i class="fas fa-hand-sparkles me-2"></i><?= htmlspecialchars($welcomeToastTitle); ?></div>
                    <div class="app-welcome-toast-copy"><?= htmlspecialchars($welcomeToastCopy); ?></div>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= APP_URL ?>/assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarCollapse = document.getElementById('sidebarCollapse');
            const loadingOverlay = document.getElementById('appLoadingOverlay');
            const showLoadingOverlay = function(options) {
                if (!loadingOverlay) {
                    return;
                }

                if (window.JassnetLoadingOverlay) {
                    window.JassnetLoadingOverlay.show(loadingOverlay, options || {});
                    return;
                }

                loadingOverlay.classList.add('show');
            };
            const hideLoadingOverlay = function() {
                if (!loadingOverlay) {
                    return;
                }

                if (window.JassnetLoadingOverlay) {
                    window.JassnetLoadingOverlay.hide(loadingOverlay);
                    window.JassnetLoadingOverlay.reset(loadingOverlay);
                    return;
                }

                loadingOverlay.classList.remove('show');
            };
            const isDownloadIntentUrl = function(rawUrl) {
                if (!rawUrl || rawUrl === '#' || rawUrl.startsWith('javascript:') || rawUrl.startsWith('mailto:') || rawUrl.startsWith('tel:')) {
                    return false;
                }

                try {
                    const parsedUrl = new URL(rawUrl, window.location.href);
                    const format = (parsedUrl.searchParams.get('format') || '').toLowerCase();
                    const exportAction = (parsedUrl.searchParams.get('export') || '').toLowerCase();
                    const action = (parsedUrl.searchParams.get('action') || '').toLowerCase();
                    const download = (parsedUrl.searchParams.get('download') || '').toLowerCase();
                    const pathname = parsedUrl.pathname.toLowerCase();

                    if (['pdf', 'excel', 'csv', 'xlsx'].includes(format)) {
                        return true;
                    }

                    if (['pdf', 'excel', 'csv', 'xlsx', 'batch-pdf'].includes(exportAction)) {
                        return true;
                    }

                    if (['pdf', 'download'].includes(action) || ['pdf', 'excel', 'csv'].includes(download)) {
                        return true;
                    }

                    return pathname.endsWith('.pdf') || pathname.endsWith('.csv') || pathname.endsWith('.xlsx') || pathname.endsWith('.xls');
                } catch (error) {
                    return /(?:[?&](?:export|format|action|download)=(?:pdf|excel|csv|xlsx|batch-pdf))|\.(?:pdf|csv|xlsx|xls)(?:$|[?#])/i.test(rawUrl);
                }
            };
            const isDownloadIntentForm = function(form) {
                if (!form) {
                    return false;
                }

                if (form.hasAttribute('data-download')) {
                    return true;
                }

                const action = form.getAttribute('action') || window.location.href;
                if (isDownloadIntentUrl(action)) {
                    return true;
                }

                const formData = new FormData(form);
                const format = (formData.get('format') || '').toString().toLowerCase();
                const exportAction = (formData.get('export') || '').toString().toLowerCase();
                const actionValue = (formData.get('action') || '').toString().toLowerCase();
                const download = (formData.get('download') || '').toString().toLowerCase();

                return ['pdf', 'excel', 'csv', 'xlsx'].includes(format)
                    || ['pdf', 'excel', 'csv', 'xlsx', 'batch-pdf'].includes(exportAction)
                    || ['pdf', 'download'].includes(actionValue)
                    || ['pdf', 'excel', 'csv'].includes(download);
            };
            const shouldShowLoaderForLink = function(link) {
                if (!link || link.hasAttribute('data-no-loader')) {
                    return false;
                }

                const href = link.getAttribute('href') || '';
                if (href === '' || href === '#' || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
                    return false;
                }

                if (link.hasAttribute('download') || link.getAttribute('rel') === 'download' || isDownloadIntentUrl(href)) {
                    return false;
                }

                if (link.target === '_blank' || href.includes('#')) {
                    return false;
                }

                return true;
            };
            const isLogoutLink = function(link) {
                const href = (link.getAttribute('href') || '').toLowerCase();
                return href.endsWith('/logout.php') || href.endsWith('logout.php');
            };

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                    sidebarOverlay.classList.toggle('show');
                });
            }

            if (sidebarCollapse) {
                sidebarCollapse.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                });
            }

            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                });
            }

            const menuLinks = sidebar.querySelectorAll('.menu a');
            menuLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 992) {
                        sidebar.classList.remove('show');
                        sidebarOverlay.classList.remove('show');
                    }
                });
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                }
            });

            const dropdownToggles = sidebar.querySelectorAll('.dropdown-toggle');
            const syncDropdownState = function(toggle, forceExpanded = null) {
                const targetSelector = toggle.getAttribute('data-bs-target');
                if (!targetSelector) {
                    return;
                }

                const target = document.querySelector(targetSelector);
                if (!target) {
                    return;
                }

                const expanded = forceExpanded === null ? target.classList.contains('show') : !!forceExpanded;
                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                target.classList.toggle('show', expanded);
            };

            dropdownToggles.forEach(toggle => {
                syncDropdownState(toggle);
            });

            dropdownToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();

                    dropdownToggles.forEach(otherToggle => {
                        if (otherToggle !== toggle) {
                            syncDropdownState(otherToggle, false);
                        }
                    });

                    syncDropdownState(this, this.getAttribute('aria-expanded') !== 'true');
                });
            });

            document.addEventListener('click', function(e) {
                if (!sidebar.contains(e.target)) {
                    dropdownToggles.forEach(toggle => {
                        syncDropdownState(toggle, toggle.classList.contains('active'));
                    });
                }
            });

            if (loadingOverlay) {
                document.querySelectorAll('a').forEach(function(link) {
                    link.addEventListener('click', function() {
                        if (shouldShowLoaderForLink(link)) {
                            if (isLogoutLink(link)) {
                                showLoadingOverlay({
                                    title: 'Signing out',
                                    message: 'Please wait while JASSNET securely ends your session.'
                                });
                            } else {
                                showLoadingOverlay();
                            }
                        }
                    });
                });

                document.querySelectorAll('form').forEach(function(form) {
                    form.addEventListener('submit', function() {
                        if (!form.hasAttribute('data-no-loader') && !isDownloadIntentForm(form)) {
                            showLoadingOverlay();
                        }
                    });
                });

                window.addEventListener('pageshow', function() {
                    hideLoadingOverlay();
                });
            }

            const loginTransition = <?= json_encode($showLoginTransition ? ['title' => $loginTransitionTitle, 'message' => $loginTransitionCopy] : null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            if (loginTransition && loadingOverlay) {
                showLoadingOverlay(loginTransition);
                window.setTimeout(function() {
                    hideLoadingOverlay();
                }, 1050);
            }

            const loginWelcomeToast = document.getElementById('loginWelcomeToast');
            if (loginWelcomeToast && window.bootstrap) {
                window.setTimeout(function() {
                    bootstrap.Toast.getOrCreateInstance(loginWelcomeToast, { delay: 3600 }).show();
                }, 220);
            }
        });
    </script>
</body>
</html>