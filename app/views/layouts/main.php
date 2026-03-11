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
            <!-- Add additional menu items here, use hasPermission via controller -->
            <?php if ($controller->hasPermission(['Sales', 'Technician', 'Accountant', 'Super Admin'])): ?>
            <li>
                <a href="<?= APP_URL ?>/income.php">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Income</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($controller->hasPermission(['Manager', 'Director', 'Accountant', 'Super Admin'])): ?>
            <li>
                <a href="<?= APP_URL ?>/expenses.php">
                    <i class="fas fa-receipt"></i>
                    <span>Expenses</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($controller->hasPermission(['Manager', 'Store Keeper', 'Super Admin'])): ?>
            <li>
                <a href="<?= APP_URL ?>/inventory.php">
                    <i class="fas fa-boxes"></i>
                    <span>Inventory</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($controller->hasPermission(['Technician', 'Manager', 'Super Admin'])): ?>
            <li>
                <a href="<?= APP_URL ?>/stations.php">
                    <i class="fas fa-wifi"></i>
                    <span>Stations</span>
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
            <nav class="navbar">
                <button id="sidebarToggle" class="sidebar-toggle"><i class="fas fa-bars"></i></button>
                <div class="ms-auto">
                    <span class="me-3">Welcome, <?= htmlspecialchars($user['full_name'] ?? ''); ?></span>
                    <a href="<?= APP_URL ?>/profile.php" class="me-3"><i class="fas fa-user"></i></a>
                    <a href="<?= APP_URL ?>/logout.php"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </nav>
        </header>

        <div class="content-wrapper">
            <?= $content ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= APP_URL ?>/assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const sidebarToggle = document.getElementById('sidebarToggle');

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                    sidebarOverlay.classList.toggle('show');
                });
            }

            sidebarOverlay && sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
            });
        });
    </script>
</body>
</html>