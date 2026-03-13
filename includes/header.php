<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JASSNET Business Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php
    $base_path = (basename(dirname($_SERVER['PHP_SELF'])) == 'pages') ? '../' : '';
    ?>
    <link href="<?php echo $base_path; ?>assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --app-accent: #1f6feb;
            --app-accent-dark: #174ea6;
            --app-surface: #ffffff;
            --app-muted-surface: #f4f7fb;
            --app-border: #dbe4f0;
            --app-footer-bg: #0f172a;
            --app-footer-text: #cbd5e1;
        }
        body {
            background: linear-gradient(180deg, #f8fbff 0%, #eef4fb 100%);
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background-color: #343a40;
            color: white;
            z-index: 1000;
            overflow-y: auto;
            transition: all 0.3s;
        }
        .sidebar.collapsed {
            width: 70px;
        }
        .sidebar .sidebar-header {
            padding: 20px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .sidebar .sidebar-header h4 {
            margin: 0;
            font-size: 1.2rem;
        }
        .sidebar .sidebar-header .brand-icon img {
            width: 60px;
            height: 60px;
            margin-bottom: 10px;
            border-radius: 50%;
            border: 3px solid white;
            background: white;
            padding: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .sidebar .menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar .menu li {
            position: relative;
        }
        .sidebar .menu a {
            display: block;
            padding: 15px 20px;
            color: #adb5bd;
            text-decoration: none;
            border-bottom: 1px solid #495057;
            transition: all 0.3s;
        }
        .sidebar .menu a:hover {
            background-color: #495057;
            color: white;
        }
        .sidebar .menu a.active {
            background: linear-gradient(90deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border-left: 4px solid #ff6f61;
        }
        .sidebar .menu a i {
            margin-right: 10px;
            width: 20px;
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
        .sidebar .menu .dropdown-toggle::after {
            display: none;
        }
        .sidebar .menu .dropdown-toggle .fa-chevron-down {
            transition: transform 0.3s;
        }
        .sidebar .menu .dropdown-toggle[aria-expanded="true"] .fa-chevron-down {
            transform: rotate(180deg);
        }
        .sidebar .menu .collapse a {
            padding: 10px 20px 10px 40px;
            font-size: 0.9rem;
            border-bottom: none;
        }
        .sidebar .menu .collapse a:hover {
            background-color: #495057;
            color: white;
        }
        .sidebar .menu .collapse a.active {
            background: linear-gradient(90deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            border-left: 4px solid #ff6f61;
        }
        .main-content {
            margin-left: 250px;
            transition: margin-left 0.3s;
        }
        .sidebar.collapsed + .main-content {
            margin-left: 70px;
        }
        .top-header {
            height: 72px;
            background-color: #fff;
            border-bottom: 1px solid #dee2e6;
            position: fixed;
            top: 0;
            right: 0;
            left: 250px;
            z-index: 999;
            transition: left 0.3s;
        }
        .sidebar.collapsed ~ .top-header {
            left: 70px;
        }
        .top-header .navbar {
            padding: 0 20px;
            height: 100%;
        }
        .content-wrapper {
            display: flex;
            flex-direction: column;
            padding-top: 92px;
            padding-left: 20px;
            padding-right: 20px;
            min-height: calc(100vh - 72px);
        }
        .app-page-content {
            flex: 1 0 auto;
        }
        .app-site-footer {
            margin-top: auto;
            margin-left: -20px;
            margin-right: -20px;
            padding: 28px 28px 18px;
            background: linear-gradient(135deg, var(--app-footer-bg) 0%, #16213d 100%);
            color: var(--app-footer-text);
            border-top: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 -10px 30px rgba(15, 23, 42, 0.12);
        }
        .app-site-footer a {
            color: var(--app-footer-text);
        }
        .app-site-footer a:hover {
            color: #ffffff;
        }
        .page-shell-card {
            background: var(--app-surface);
            border: 1px solid var(--app-border);
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }
        .table-modern thead th {
            border-bottom: 0;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #475569;
        }
        .table-modern tbody tr {
            vertical-align: middle;
        }
        .table-modern tbody tr:hover {
            background-color: rgba(31, 111, 235, 0.04);
        }
        .app-status-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            background: #e2e8f0;
            color: #334155;
        }
        .app-loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.44);
            backdrop-filter: blur(3px);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, visibility 0.2s ease;
            z-index: 2000;
        }
        .app-loading-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        .app-loading-card {
            width: min(360px, calc(100vw - 32px));
            padding: 28px 24px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.96);
            text-align: center;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.22);
        }
        .app-loading-spinner {
            width: 54px;
            height: 54px;
            margin: 0 auto 16px;
            border-radius: 50%;
            border: 4px solid rgba(31, 111, 235, 0.16);
            border-top-color: var(--app-accent);
            animation: appSpin 0.9s linear infinite;
        }
        .app-loading-card h5 {
            margin-bottom: 8px;
            color: #0f172a;
        }
        .app-loading-card p {
            margin: 0;
            color: #64748b;
        }
        @keyframes appSpin {
            to {
                transform: rotate(360deg);
            }
        }
        .sidebar-toggle {
            background: none;
            border: none;
            color: #6c757d;
            font-size: 1.2rem;
            padding: 10px;
            cursor: pointer;
        }
        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #dee2e6;
        }
        .user-avatar-fallback {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #dee2e6;
            color: #6c757d;
            background: #f8f9fa;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
            .top-header {
                left: 0;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
                z-index: 999;
            }
            .sidebar-overlay.show {
                display: block;
            }
            .app-site-footer {
                margin-left: -20px;
                margin-right: -20px;
                padding: 24px 20px 18px;
            }
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
            <h4>JASSNET BMS</h4>
        </div>
        <ul class="menu">
            <li>
                <a href="<?php echo $base_path; ?>dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['announcements.php', 'announcements_latest.php', 'announcements_send.php', 'announcements_inactive.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#announcementsSubmenu">
                    <i class="fas fa-bullhorn"></i>
                    <span>Announcements</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3" id="announcementsSubmenu">
                    <li>
                        <a href="<?php echo $base_path; ?>pages/announcements_latest.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'announcements_latest.php' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i>
                            <span>Latest Announcements</span>
                        </a>
                    </li>
                    <?php if (hasPermission(['Store Keeper', 'Manager', 'Director', 'Super Admin'])): ?>
                    <li>
                        <a href="<?php echo $base_path; ?>pages/announcements_send.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'announcements_send.php' ? 'active' : ''; ?>">
                            <i class="fas fa-paper-plane"></i>
                            <span>Send Announcement</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (hasPermission(['Super Admin'])): ?>
                    <li>
                        <a href="<?php echo $base_path; ?>pages/announcements_inactive.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'announcements_inactive.php' ? 'active' : ''; ?>">
                            <i class="fas fa-archive"></i>
                            <span>Inactive Announcements</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php if (hasPermission(['Sales', 'Accountant', 'Super Admin'])): ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['add_income.php', 'view_income.php', 'income.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#incomeSubmenu">
                    <i class="fas fa-dollar-sign"></i>
                    <span>Income</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3" id="incomeSubmenu">
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
                    <li>
                        <a href="<?php echo $base_path; ?>pages/snippe_sync_history.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'snippe_sync_history.php' ? 'active' : ''; ?>">
                            <i class="fas fa-history"></i>
                            <span>Snippe Sync History</span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>
            <?php if (hasPermission(['Sales', 'Technician', 'Manager', 'Director', 'Accountant', 'Super Admin'])): ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['add_expense_request.php', 'view_expense_requests.php', 'expense_detail.php', 'expenses.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#expensesSubmenu">
                    <i class="fas fa-receipt"></i>
                    <span>Expenses</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3" id="expensesSubmenu">
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
                <a href="#" class="dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['inventory.php', 'inventory_items.php', 'issue_equipment.php', 'low_stock_alerts.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#inventorySubmenu">
                    <i class="fas fa-boxes"></i>
                    <span>Inventory</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3" id="inventorySubmenu">
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
                <a href="#" class="dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['stations.php', 'request_new_station_setup.php', 'station_detail.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#stationsSubmenu">
                    <i class="fas fa-broadcast-tower"></i>
                    <span>Stations</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3" id="stationsSubmenu">
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
                <a href="#" class="dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['users.php', 'add_user.php', 'supported_banks.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#usersSubmenu">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3" id="usersSubmenu">
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
                </ul>
            </li>
            <?php endif; ?>
            <?php if (hasPermission(['Manager', 'Director', 'Accountant', 'Super Admin'])): ?>
            <li>
                <a href="<?php echo $base_path; ?>pages/reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Top Header -->
    <header class="top-header">
        <nav class="navbar navbar-expand-lg navbar-light">
            <div class="container-fluid d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <button class="sidebar-toggle d-lg-none" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <button class="sidebar-toggle d-none d-lg-block" id="sidebarCollapse">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>

                <div class="d-flex align-items-center">
                    <?php
                    $displayName = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'User');
                    $displayRole = $_SESSION['role'] ?? 'Unknown Role';
                    $profilePhoto = $_SESSION['profile_photo'] ?? '';
                    if (!$profilePhoto && function_exists('getCurrentUser')) {
                        $currentUser = getCurrentUser();
                        $profilePhoto = $currentUser['profile_photo'] ?? '';
                    }
                    ?>
                    <div class="me-3">
                        <?php if (!empty($profilePhoto)): ?>
                            <img src="<?php echo $base_path; ?>uploads/<?php echo htmlspecialchars($profilePhoto); ?>" class="user-avatar" alt="User Photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                            <span class="user-avatar-fallback" style="display:none;"><i class="fas fa-user"></i></span>
                        <?php else: ?>
                            <span class="user-avatar-fallback"><i class="fas fa-user"></i></span>
                        <?php endif; ?>
                    </div>
                    <span class="me-3 text-muted">
                        Welcome, <strong><?php echo htmlspecialchars($displayName); ?></strong>
                        <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($displayRole); ?></span>
                    </span>

                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
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