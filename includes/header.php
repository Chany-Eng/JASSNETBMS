<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JASSNET ERMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php
    $base_path = (basename(dirname($_SERVER['PHP_SELF'])) == 'pages') ? '../' : '';
    $current_page_name = basename($_SERVER['PHP_SELF'], '.php');
    $current_page_label = ucwords(str_replace('_', ' ', $current_page_name));
    if (isset($_GET['mark_notification']) && function_exists('markNotificationAsRead')) {
        markNotificationAsRead($conn, (string) $_GET['mark_notification']);
    }
    if (function_exists('isLoggedIn') && isLoggedIn() && function_exists('appLogActivity')) {
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
    <link href="<?php echo $base_path; ?>assets/css/style.css" rel="stylesheet">
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
            --app-shell-sidebar-soft: #18324f;
            --app-shell-sidebar-text: #d6e2f1;
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
            color: var(--app-shell-sidebar-text);
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
            font-size: 1rem;
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

        .sidebar.collapsed .sidebar-header p {
            display: none;
        }

        .sidebar.collapsed .sidebar-header .brand-icon {
            margin-bottom: 0;
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
            font-size: 0.88rem;
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

        .page-shell-card {
            background: var(--app-shell-surface);
            border: 1px solid var(--app-shell-border);
            border-radius: 22px;
            box-shadow: var(--app-shell-shadow);
        }

        .table-modern thead th {
            border-bottom: 0;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #60758d;
        }

        .table-modern tbody tr {
            vertical-align: middle;
        }

        .table-modern tbody tr:hover {
            background-color: rgba(41, 105, 199, 0.04);
        }

        .app-status-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            background: #e8eef5;
            color: #334155;
        }

    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="brand-icon">
                <img src="<?php echo $base_path; ?>assets/images/logo.png" alt="JASSNET Logo">
            </div>
            <h4>JASSNET ERMS</h4>
        </div>
        <ul class="menu">
            <li>
                <a href="<?php echo $base_path; ?>dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['announcements.php', 'announcements_latest.php', 'announcements_send.php', 'announcements_inactive.php', 'website_content.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#announcementsSubmenu" aria-controls="announcementsSubmenu" aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['announcements.php', 'announcements_latest.php', 'announcements_send.php', 'announcements_inactive.php', 'website_content.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-bullhorn"></i>
                    <span>Announcements</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['announcements.php', 'announcements_latest.php', 'announcements_send.php', 'announcements_inactive.php', 'website_content.php']) ? 'show' : ''; ?>" id="announcementsSubmenu">
                    <li>
                        <a href="<?php echo $base_path; ?>pages/announcements_latest.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'announcements_latest.php' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i>
                            <span>Latest Announcements</span>
                        </a>
                    </li>
                    <?php if (hasPermission(['Store Keeper', 'Manager', 'Director', 'Content Manager', 'Super Admin'])): ?>
                    <li>
                        <a href="<?php echo $base_path; ?>pages/announcements_send.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'announcements_send.php' ? 'active' : ''; ?>">
                            <i class="fas fa-paper-plane"></i>
                            <span>Send Announcement</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (appCanManageSiteContent()): ?>
                    <li>
                        <a href="<?php echo $base_path; ?>pages/website_content.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'website_content.php' ? 'active' : ''; ?>">
                            <i class="fas fa-palette"></i>
                            <span>Website Content</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (appCanManageSiteContent()): ?>
                    <li>
                        <a href="<?php echo $base_path; ?>pages/announcements_inactive.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'announcements_inactive.php' ? 'active' : ''; ?>">
                            <i class="fas fa-archive"></i>
                            <span>Inactive Announcements</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php if (hasPermission(['Sales', 'Director', 'Accountant', 'Super Admin'])): ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['add_income.php', 'view_income.php', 'income.php', 'snippe_income_sync.php', 'snippe_sync_history.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#incomeSubmenu" aria-controls="incomeSubmenu" aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['add_income.php', 'view_income.php', 'income.php', 'snippe_income_sync.php', 'snippe_sync_history.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-dollar-sign"></i>
                    <span>Income</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['add_income.php', 'view_income.php', 'income.php', 'snippe_income_sync.php', 'snippe_sync_history.php']) ? 'show' : ''; ?>" id="incomeSubmenu">
                    <?php if (hasPermission(['Sales', 'Super Admin'])): ?>
                    <li>
                        <a href="<?php echo $base_path; ?>pages/add_income.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'add_income.php' ? 'active' : ''; ?>">
                            <i class="fas fa-plus"></i>
                            <span>Add Income</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="<?php echo $base_path; ?>pages/view_income.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'view_income.php' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i>
                            <span>View Income</span>
                        </a>
                    </li>
                    <?php if (hasPermission(['Director', 'Super Admin'])): ?>
                    <li>
                        <a href="<?php echo $base_path; ?>pages/snippe_sync_history.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'snippe_sync_history.php' ? 'active' : ''; ?>">
                            <i class="fas fa-history"></i>
                            <span>Snippe Sync History</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>
            <?php if (hasPermission(['Sales', 'Technician', 'Manager', 'Director', 'Accountant', 'Super Admin'])): ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['add_expense_request.php', 'view_expense_requests.php', 'expense_detail.php', 'expenses.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#expensesSubmenu" aria-controls="expensesSubmenu" aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['add_expense_request.php', 'view_expense_requests.php', 'expense_detail.php', 'expenses.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-receipt"></i>
                    <span>Expenses</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['add_expense_request.php', 'view_expense_requests.php', 'expense_detail.php', 'expenses.php']) ? 'show' : ''; ?>" id="expensesSubmenu">
                    <li>
                        <a href="<?php echo $base_path; ?>pages/add_expense_request.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'add_expense_request.php' ? 'active' : ''; ?>">
                            <i class="fas fa-plus"></i>
                            <span>Add Request</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>pages/view_expense_requests.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'view_expense_requests.php' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i>
                            <span>View Requests</span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>
            <?php if (hasPermission(['Store Keeper', 'Manager', 'Super Admin', 'Technician', 'Sales', 'Director', 'Accountant'])): ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['inventory.php', 'add_inventory.php', 'inventory_items.php', 'issue_equipment.php', 'low_stock_alerts.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#inventorySubmenu" aria-controls="inventorySubmenu" aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['inventory.php', 'add_inventory.php', 'inventory_items.php', 'issue_equipment.php', 'low_stock_alerts.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-boxes"></i>
                    <span>Inventory</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['inventory.php', 'add_inventory.php', 'inventory_items.php', 'issue_equipment.php', 'low_stock_alerts.php']) ? 'show' : ''; ?>" id="inventorySubmenu">
                    <?php if (hasPermission(['Store Keeper'])): ?>
                    <li>
                        <a href="<?php echo $base_path; ?>pages/add_inventory.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'add_inventory.php' ? 'active' : ''; ?>">
                            <i class="fas fa-plus"></i>
                            <span>Add Inventory Item</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (hasPermission(['Store Keeper', 'Manager', 'Super Admin', 'Technician'])): ?>
                    <li>
                        <a href="<?php echo $base_path; ?>pages/inventory_items.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'inventory_items.php' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i>
                            <span>Inventory Items</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (hasPermission(['Store Keeper', 'Manager', 'Super Admin', 'Sales', 'Technician'])): ?>
                    <li>
                        <a href="<?php echo $base_path; ?>pages/issue_equipment.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'issue_equipment.php' ? 'active' : ''; ?>">
                            <i class="fas fa-tools"></i>
                            <span>Equipment Requests</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (hasPermission(['Store Keeper', 'Manager', 'Super Admin'])): ?>
                    <li>
                        <a href="<?php echo $base_path; ?>pages/low_stock_alerts.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'low_stock_alerts.php' ? 'active' : ''; ?>">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Low Stock Alerts</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>
            <?php if (hasPermission(['Technician', 'Sales', 'Manager', 'Director', 'Accountant', 'Store Keeper', 'Super Admin'])): ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['stations.php', 'request_new_station_setup.php', 'station_detail.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#stationsSubmenu" aria-controls="stationsSubmenu" aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['stations.php', 'request_new_station_setup.php', 'station_detail.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-broadcast-tower"></i>
                    <span>Stations</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['stations.php', 'request_new_station_setup.php', 'station_detail.php']) ? 'show' : ''; ?>" id="stationsSubmenu">
                    <li>
                        <a href="<?php echo $base_path; ?>pages/stations.php#station-setup-requests" class="<?php echo basename($_SERVER['PHP_SELF']) == 'stations.php' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i>
                            <span>Station Setup Requests</span>
                        </a>
                    </li>
                    <?php if (hasPermission(['Technician'])): ?>
                    <li>
                        <a href="<?php echo $base_path; ?>pages/request_new_station_setup.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'request_new_station_setup.php' ? 'active' : ''; ?>">
                            <i class="fas fa-plus"></i>
                            <span>Request New Station Setup</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php endif; ?>
            <?php if (hasPermission(['Super Admin'])): ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['users.php', 'add_user.php', 'supported_banks.php', 'admin_history.php', 'profile.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#usersSubmenu" aria-controls="usersSubmenu" aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['users.php', 'add_user.php', 'supported_banks.php', 'admin_history.php', 'profile.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['users.php', 'add_user.php', 'supported_banks.php', 'admin_history.php', 'profile.php']) ? 'show' : ''; ?>" id="usersSubmenu">
                    <li>
                        <a href="<?php echo $base_path; ?>pages/users.php#users-list" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i>
                            <span>List of Users</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>pages/add_user.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'add_user.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user-plus"></i>
                            <span>Add User</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>pages/supported_banks.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'supported_banks.php' ? 'active' : ''; ?>">
                            <i class="fas fa-university"></i>
                            <span>Supported Banks</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo $base_path; ?>pages/admin_history.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_history.php' ? 'active' : ''; ?>">
                            <i class="fas fa-history"></i>
                            <span>Admin History</span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>
            <li>
                <a href="<?php echo $base_path; ?>pages/profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <?php if (hasPermission(['Director', 'Super Admin', 'Accountant'])): ?>
            <li>
                <a href="<?php echo $base_path; ?>pages/reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <?php endif; ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['payroll.php', 'create_salary_request.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#payrollSubmenu" aria-controls="payrollSubmenu" aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['payroll.php', 'create_salary_request.php']) ? 'true' : 'false'; ?>">
                    <i class="fas fa-money-check-dollar"></i>
                    <span>Payroll</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['payroll.php', 'create_salary_request.php']) ? 'show' : ''; ?>" id="payrollSubmenu">
                    <?php if (hasPermission(['Accountant', 'Super Admin'])): ?>
                    <li>
                        <a href="<?php echo $base_path; ?>pages/create_salary_request.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'create_salary_request.php' ? 'active' : ''; ?>">
                            <i class="fas fa-plus-circle"></i>
                            <span>Create Salary Request</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="<?php echo $base_path; ?>pages/payroll.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'payroll.php' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i>
                            <span>Salary Requests</span>
                        </a>
                    </li>
                </ul>
            </li>
            <li class="logout-item">
                <a href="<?php echo $base_path; ?>logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Top Header -->
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
                        <div class="eyebrow">JASSNET Portal</div>
                        <h1 class="title"><?php echo htmlspecialchars($current_page_label); ?></h1>
                    </div>
                </div>

                <div class="app-topbar-actions">
                    <?php
                    $displayName = trim((string) ($_SESSION['full_name'] ?? '')) !== '' ? (string) $_SESSION['full_name'] : (string) ($_SESSION['username'] ?? 'User');
                    $displayUsername = trim((string) ($_SESSION['username'] ?? ''));
                    $displayRole = appFormatRoleList((string) ($_SESSION['role'] ?? ''));
                    $displayRoleCount = appCountRoles((string) ($_SESSION['role'] ?? ''));
                    $displayMeta = $displayRole;
                    if ($displayRoleCount > 0) {
                        $displayMeta = $displayRole . ' • ' . $displayRoleCount . ' ' . ($displayRoleCount === 1 ? 'role' : 'roles');
                    }
                    if ($displayUsername !== '' && strcasecmp($displayName, $displayUsername) !== 0) {
                        $displayMeta = '@' . $displayUsername . ' • ' . $displayRole;
                        if ($displayRoleCount > 0) {
                            $displayMeta .= ' • ' . $displayRoleCount . ' ' . ($displayRoleCount === 1 ? 'role' : 'roles');
                        }
                    }
                    $profilePhoto = $_SESSION['profile_photo'] ?? '';
                    if (!$profilePhoto && function_exists('getCurrentUser')) {
                        $currentUser = getCurrentUser();
                        $profilePhoto = $currentUser['profile_photo'] ?? '';
                    }
                    $actionNotifications = function_exists('getUserActionNotifications') ? getUserActionNotifications($conn) : [];
                    $unreadNotifications = array_values(array_filter($actionNotifications, static function ($item) {
                        return empty($item['is_read']);
                    }));
                    $notificationCount = count($unreadNotifications);
                    $notificationHeading = 'Requests Requiring Action';
                    $notificationSubheading = 'Open queue items and continue the next workflow steps.';
                    if (appCurrentSessionHasRole(['Manager'])) {
                        $notificationHeading = 'Manager Approval Queue';
                        $notificationSubheading = 'Review approvals waiting for manager action.';
                    } elseif (appCurrentSessionHasRole(['Accountant'])) {
                        $notificationHeading = 'Accountant Processing Queue';
                        $notificationSubheading = 'Process payouts, receipts, and final approvals.';
                    } elseif (appCurrentSessionHasRole(['Director'])) {
                        $notificationHeading = 'Director Decision Queue';
                        $notificationSubheading = 'Review high-level approvals and pending escalations.';
                    }
                    ?>
                    <span class="app-topbar-chip"><i class="fas fa-calendar-day"></i> <?php echo date('d M Y'); ?></span>
                    <span class="app-topbar-chip"><i class="fas fa-clock"></i> <?php echo date('h:i:s A'); ?></span>
                    <span class="app-topbar-chip"><i class="fas fa-user-shield"></i> <?php echo $displayRoleCount; ?> <?php echo $displayRoleCount === 1 ? 'Role' : 'Roles'; ?></span>
                    <div class="dropdown me-3">
                        <button class="btn app-icon-button position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php if ($notificationCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?php echo $notificationCount > 9 ? '9+' : $notificationCount; ?></span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end app-notification-menu" aria-labelledby="notificationDropdown">
                            <li class="dropdown-header d-flex justify-content-between align-items-center">
                                <span>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($notificationHeading); ?></div>
                                    <div class="small text-muted fw-normal"><?php echo htmlspecialchars($notificationSubheading); ?></div>
                                </span>
                                <span class="badge bg-primary"><?php echo $notificationCount; ?> unread</span>
                            </li>
                            <?php if (!empty($actionNotifications)): ?>
                                <?php foreach ($actionNotifications as $notification): ?>
                                    <li>
                                        <a class="dropdown-item py-2 <?php echo !empty($notification['is_read']) ? 'notification-item-read' : 'notification-item-unread'; ?>" href="<?php echo $base_path . ltrim((string) ($notification['target'] ?? ''), '/'); ?>">
                                            <div class="fw-semibold"><?php echo htmlspecialchars((string) ($notification['title'] ?? 'Request notification')); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars((string) ($notification['description'] ?? '')); ?></div>
                                            <div class="small <?php echo !empty($notification['is_read']) ? 'text-muted' : 'text-primary'; ?>"><?php echo !empty($notification['is_read']) ? 'Read' : 'Unread'; ?></div>
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
                            <img src="<?php echo $base_path; ?>uploads/<?php echo htmlspecialchars($profilePhoto); ?>" class="user-avatar" alt="User Photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                            <span class="user-avatar-fallback" style="display:none;"><i class="fas fa-user"></i></span>
                        <?php else: ?>
                            <span class="user-avatar-fallback"><i class="fas fa-user"></i></span>
                        <?php endif; ?>
                    </div>
                    <div class="app-user-summary me-3">
                        <div class="app-user-meta">
                            <strong><?php echo htmlspecialchars($displayName); ?></strong>
                            <span><?php echo htmlspecialchars($displayMeta); ?></span>
                        </div>
                    </div>

                    <div class="dropdown">
                        <button class="btn app-icon-button dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>pages/profile.php"><i class="fas fa-user-edit"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>change_password.php"><i class="fas fa-key"></i> Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="content-wrapper">
            <div class="app-loading-overlay" id="appLoadingOverlay" aria-hidden="true">
                <div class="app-loading-card">
                    <div class="app-loading-spinner"></div>
                    <h5>Loading page</h5>
                    <p>Please wait while JASSNET opens the selected page.</p>
                </div>
            </div>
            <div class="app-page-content">