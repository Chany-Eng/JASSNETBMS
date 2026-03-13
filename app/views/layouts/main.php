<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'JASSNET BMS'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --app-footer-bg: #0f172a;
            --app-footer-text: #cbd5e1;
        }
        /* duplicate sidebar/top-header css from previous design */
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
            text-decoration: none;
        }
        .app-site-footer a:hover {
            color: #ffffff;
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
                <img src="<?= APP_URL ?>/assets/images/logo.png" alt="JASSNET Logo">
            </div>
            <h4>JASSNET BMS</h4>
        </div>
        <ul class="menu">
            <li>
                <a href="<?= APP_URL ?>/dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['announcements.php', 'announcements_latest.php', 'announcements_send.php', 'announcements_inactive.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#announcementsSubmenu">
                    <i class="fas fa-bullhorn"></i>
                    <span>Announcements</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3" id="announcementsSubmenu">
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
            <?php if ($controller->hasPermission(['Sales', 'Director', 'Super Admin'])): ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['add_income.php', 'view_income.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#incomeSubmenu">
                    <i class="fas fa-dollar-sign"></i>
                    <span>Income</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3" id="incomeSubmenu">
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
                <a href="#" class="dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['add_expense_request.php', 'view_expense_requests.php', 'expenses.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#expensesSubmenu">
                    <i class="fas fa-receipt"></i>
                    <span>Expenses</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3" id="expensesSubmenu">
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
                <a href="#" class="dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['inventory.php', 'add_inventory.php', 'inventory_items.php', 'issue_equipment.php', 'low_stock_alerts.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#inventorySubmenu">
                    <i class="fas fa-boxes"></i>
                    <span>Inventory</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3" id="inventorySubmenu">
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
            <?php if ($controller->hasPermission(['Technician', 'Sales', 'Manager', 'Director', 'Accountant', 'Super Admin'])): ?>
            <li class="dropdown">
                <a href="#" class="dropdown-toggle <?= in_array(basename($_SERVER['PHP_SELF']), ['stations.php', 'request_new_station_setup.php']) ? 'active' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#stationsSubmenu">
                    <i class="fas fa-broadcast-tower"></i>
                    <span>Stations</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3" id="stationsSubmenu">
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
                <a href="#" class="dropdown-toggle" data-bs-toggle="collapse" data-bs-target="#usersSubmenu">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                    <i class="fas fa-chevron-down float-end"></i>
                </a>
                <ul class="collapse list-unstyled ps-3" id="usersSubmenu">
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
                </ul>
            </li>
            <?php endif; ?>
            <?php if ($controller->hasPermission(['Director', 'Super Admin'])): ?>
            <li>
                <a href="<?= APP_URL ?>/pages/reports.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <?php endif; ?>
            <li>
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
                    <div class="d-flex align-items-center">
                        <button class="sidebar-toggle d-lg-none" id="sidebarToggle">
                            <i class="fas fa-bars"></i>
                        </button>
                        <button class="sidebar-toggle d-none d-lg-block" id="sidebarCollapse">
                            <i class="fas fa-bars"></i>
                        </button>
                    </div>

                    <div class="d-flex align-items-center">
                        <?php $profilePhoto = $user['profile_photo'] ?? ''; ?>
                        <div class="me-3">
                            <?php if (!empty($profilePhoto)): ?>
                                <img src="<?= APP_URL ?>/uploads/<?= htmlspecialchars($profilePhoto); ?>" class="user-avatar" alt="User Photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                                <span class="user-avatar-fallback" style="display:none;"><i class="fas fa-user"></i></span>
                            <?php else: ?>
                                <span class="user-avatar-fallback"><i class="fas fa-user"></i></span>
                            <?php endif; ?>
                        </div>
                        <span class="me-3 text-muted">
                            Welcome, <strong><?= htmlspecialchars($user['full_name'] ?? ($user['username'] ?? 'User')); ?></strong>
                            <span class="badge bg-primary ms-2"><?= htmlspecialchars($user['role'] ?? 'Unknown Role'); ?></span>
                        </span>

                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
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
            <div class="app-page-content">
                <?= $content ?>
            </div>
            <footer class="app-site-footer">
                <div class="row g-4 align-items-center mx-0">
                    <div class="col-lg-5 px-0">
                        <h5 class="text-white mb-2">JASSNET Business Management System</h5>
                        <p class="mb-0 small">Operations, approvals, payouts, inventory, stations, and announcements managed in one workspace.</p>
                    </div>
                    <div class="col-lg-4 px-0">
                        <div class="small text-uppercase fw-semibold mb-2" style="letter-spacing: 0.08em; color: #93c5fd;">Quick Access</div>
                        <div class="d-flex flex-wrap gap-3 small">
                            <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
                            <a href="<?= APP_URL ?>/pages/view_income.php">Income</a>
                            <a href="<?= APP_URL ?>/pages/view_expense_requests.php">Expenses</a>
                            <a href="<?= APP_URL ?>/pages/stations.php">Stations</a>
                        </div>
                    </div>
                    <div class="col-lg-3 px-0 text-lg-end">
                        <div class="small text-uppercase fw-semibold mb-2" style="letter-spacing: 0.08em; color: #93c5fd;">Copyright</div>
                        <div class="small">&copy; <?= date('Y') ?> JASSNET Incame. All rights reserved.</div>
                        <div class="small">Built for internal business operations.</div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= APP_URL ?>/assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarCollapse = document.getElementById('sidebarCollapse');

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
            dropdownToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();

                    dropdownToggles.forEach(otherToggle => {
                        if (otherToggle !== toggle) {
                            const otherTarget = document.querySelector(otherToggle.getAttribute('data-bs-target'));
                            if (otherTarget) {
                                otherTarget.classList.remove('show');
                                otherToggle.setAttribute('aria-expanded', 'false');
                            }
                        }
                    });

                    const target = document.querySelector(this.getAttribute('data-bs-target'));
                    if (target) {
                        const isExpanded = this.getAttribute('aria-expanded') === 'true';
                        this.setAttribute('aria-expanded', !isExpanded);
                        target.classList.toggle('show');
                    }
                });
            });

            document.addEventListener('click', function(e) {
                if (!sidebar.contains(e.target)) {
                    dropdownToggles.forEach(toggle => {
                        const target = document.querySelector(toggle.getAttribute('data-bs-target'));
                        if (target) {
                            target.classList.remove('show');
                            toggle.setAttribute('aria-expanded', 'false');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>