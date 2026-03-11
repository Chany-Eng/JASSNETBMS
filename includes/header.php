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
            height: 60px;
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
            padding-top: 80px;
            padding-left: 20px;
            padding-right: 20px;
            min-height: calc(100vh - 60px);
        }
        .sidebar-toggle {
            background: none;
            border: none;
            color: #6c757d;
            font-size: 1.2rem;
            padding: 10px;
            cursor: pointer;
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
            <?php if (hasPermission(['Sales', 'Technician', 'Accountant', 'Super Admin'])): ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['add_income.php', 'view_income.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#incomeSubmenu">
                    <i class="fas fa-dollar-sign"></i>
                    <span>Income</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3" id="incomeSubmenu">
                    <?php if (hasPermission(['Sales', 'Technician', 'Super Admin'])): ?>
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
                </ul>
            </li>
            <?php endif; ?>
            <?php if (hasPermission(['Sales', 'Technician', 'Manager', 'Director', 'Accountant', 'Super Admin'])): ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?php echo in_array(basename($_SERVER['PHP_SELF']), ['add_expense_request.php', 'view_expense_requests.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#expensesSubmenu">
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
            <?php if (hasPermission(['Store Keeper', 'Manager', 'Super Admin'])): ?>
            <li>
                <a href="<?php echo $base_path; ?>pages/inventory.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>">
                    <i class="fas fa-boxes"></i>
                    <span>Inventory</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if (hasPermission(['Technician', 'Sales', 'Manager', 'Director', 'Accountant', 'Super Admin'])): ?>
            <li>
                <a href="<?php echo $base_path; ?>pages/stations.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'stations.php' ? 'active' : ''; ?>">
                    <i class="fas fa-broadcast-tower"></i>
                    <span>Stations</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if (hasPermission(['Super Admin'])): ?>
            <li>
                <a href="<?php echo $base_path; ?>pages/users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <?php endif; ?>
            <li>
                <a href="<?php echo $base_path; ?>pages/reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
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
                <div class="d-flex align-items-center">
                    <button class="sidebar-toggle d-lg-none" id="sidebarToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <button class="sidebar-toggle d-none d-lg-block" id="sidebarCollapse">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>

                <div class="d-flex align-items-center">
                    <span class="me-3 text-muted">
                        Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
                        <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
                    </span>

                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>pages/profile.php"><i class="fas fa-user-edit"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="<?php echo $base_path; ?>pages/change_password.php"><i class="fas fa-key"></i> Change Password</a></li>
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