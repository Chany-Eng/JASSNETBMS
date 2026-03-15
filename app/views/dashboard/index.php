<?php
$pageTitle = 'Dashboard';
?>

<?php ob_start(); ?>
<?php
$permissions = $dashboard_permissions ?? [];
$canViewAllFinancials = !empty($permissions['can_view_all_financials']);
$canViewIncomeSummary = !empty($permissions['can_view_income']);
$canViewOwnIncome = !empty($permissions['can_view_own_income']);
$canViewExpenseSummary = !empty($permissions['can_view_expense_financials']);
$canViewExpenseOperations = !empty($permissions['can_view_expense_operations']);
$canViewInventorySummary = !empty($permissions['can_view_inventory_value']);
$canViewInventory = !empty($permissions['can_view_inventory']);
$canViewInventoryCharts = !empty($permissions['can_view_inventory_charts']);
$canViewStationCharts = !empty($permissions['can_view_station_charts']);
$canViewStations = !empty($permissions['can_view_stations']);
$canViewPayroll = !empty($permissions['can_view_payroll']);
$recentActivities = $recent_activities ?? [];
$reportLinks = $report_links ?? [];
$roleNotices = $role_notices ?? [];
$canViewRecentActivity = $hasRole(['Super Admin']);
$userRoles = array_values(array_filter(array_map('trim', explode(',', (string) ($user['role'] ?? '')))));
if ($userRoles === []) {
    $userRoles = ['User'];
}

$userRoleSummary = implode(' | ', $userRoles);
$userRoleCount = count($userRoles);
$hasRole = static function (array $rolesToCheck) use ($userRoles): bool {
    $normalizedRoles = array_map('strtolower', $userRoles);
    foreach ($rolesToCheck as $roleToCheck) {
        if (in_array(strtolower($roleToCheck), $normalizedRoles, true)) {
            return true;
        }
    }
    return false;
};

$formatCurrency = static function ($amount): string {
    return 'Tshs. ' . number_format((float) $amount, 2);
};

$statCards = [];
if ($canViewAllFinancials) {
    $statCards[] = ['title' => 'Total Income', 'value' => $formatCurrency($stats['income_month'] ?? 0), 'icon' => 'fa-sack-dollar', 'tone' => 'emerald', 'meta' => 'Current month collections'];
    $statCards[] = ['title' => 'Total Expenses', 'value' => $formatCurrency($stats['approved_expenses'] ?? 0), 'icon' => 'fa-file-invoice-dollar', 'tone' => 'rose', 'meta' => 'Approved and processed expenses'];
    $statCards[] = ['title' => 'Inventory Items', 'value' => number_format((float) ($stats['inventory_items'] ?? 0)), 'icon' => 'fa-boxes-stacked', 'tone' => 'amber', 'meta' => 'Active inventory records'];
    $statCards[] = ['title' => 'Active Stations', 'value' => number_format((float) ($stats['active_stations'] ?? 0)), 'icon' => 'fa-tower-broadcast', 'tone' => 'indigo', 'meta' => 'Open station workflows in progress'];
    $statCards[] = ['title' => 'Profit / Loss', 'value' => $formatCurrency($stats['net_profit'] ?? 0), 'icon' => 'fa-chart-line', 'tone' => (($stats['net_profit'] ?? 0) >= 0 ? 'blue' : 'rose'), 'meta' => 'Income minus approved expenses'];
    $statCards[] = ['title' => 'Total Customers', 'value' => number_format((float) ($stats['total_customers'] ?? 0)), 'icon' => 'fa-users', 'tone' => 'violet', 'meta' => 'Unique active customer records'];
}
if ($canViewExpenseOperations) {
    $statCards[] = ['title' => 'Pending Requests', 'value' => number_format((float) ($stats['pending_requests'] ?? 0)), 'icon' => 'fa-hourglass-half', 'tone' => 'orange', 'meta' => 'Expense requests awaiting action'];
}
if ($canViewInventory) {
    $statCards[] = ['title' => 'Inventory Items', 'value' => number_format((float) ($stats['inventory_items'] ?? 0)), 'icon' => 'fa-boxes-stacked', 'tone' => 'amber', 'meta' => 'Active stock records available in inventory'];
    $statCards[] = ['title' => 'Low Stock Alerts', 'value' => number_format((float) ($stats['low_stock'] ?? 0)), 'icon' => 'fa-triangle-exclamation', 'tone' => 'amber', 'meta' => 'Items below threshold'];
}
if ($canViewInventorySummary && !$canViewAllFinancials) {
    $statCards[] = ['title' => 'Inventory Value', 'value' => $formatCurrency($stats['inventory_value'] ?? 0), 'icon' => 'fa-boxes-stacked', 'tone' => 'slate', 'meta' => 'Estimated stock value'];
}
if ($canViewStations && !$canViewAllFinancials) {
    $statCards[] = ['title' => 'Active Stations', 'value' => number_format((float) ($stats['active_stations'] ?? 0)), 'icon' => 'fa-tower-broadcast', 'tone' => 'indigo', 'meta' => 'Station requests in progress'];
    $statCards[] = ['title' => 'Pending Stations', 'value' => number_format((float) ($stats['pending_stations'] ?? 0)), 'icon' => 'fa-diagram-project', 'tone' => 'blue', 'meta' => 'Station approvals or setup tasks waiting'];
}
if ($canViewOwnIncome && !$canViewAllFinancials) {
    $statCards[] = ['title' => 'My Roles', 'value' => htmlspecialchars($userRoleSummary), 'icon' => 'fa-id-badge', 'tone' => 'indigo', 'meta' => 'Current signed-in access roles'];
}
if ($canViewPayroll) {
    $statCards[] = ['title' => 'Pending Payroll', 'value' => number_format((float) ($stats['pending_payroll_requests'] ?? 0)), 'icon' => 'fa-money-check-dollar', 'tone' => 'cyan', 'meta' => 'Salary requests awaiting workflow action'];
}

$seenCardTitles = [];
$statCards = array_values(array_filter($statCards, static function (array $card) use (&$seenCardTitles): bool {
    $key = strtolower(trim((string) ($card['title'] ?? '')));
    if ($key === '' || isset($seenCardTitles[$key])) {
        return false;
    }

    $seenCardTitles[$key] = true;
    return true;
}));

$opsRows = [];
if ($canViewExpenseOperations) {
    $opsRows[] = ['label' => 'Expense Queue', 'value' => number_format((float) ($stats['pending_requests'] ?? 0)), 'hint' => 'Requests waiting for approval or processing'];
}
if ($canViewStations) {
    $opsRows[] = ['label' => 'Station Queue', 'value' => number_format((float) ($stats['pending_stations'] ?? 0)), 'hint' => 'Open station setup workflow items'];
}
if ($canViewInventory) {
    $opsRows[] = ['label' => 'Low Stock Items', 'value' => number_format((float) ($stats['low_stock'] ?? 0)), 'hint' => 'Inventory items below safety stock'];
}
if ($canViewAllFinancials) {
    $opsRows[] = ['label' => 'Current Month Income', 'value' => $formatCurrency($stats['income_month'] ?? 0), 'hint' => 'Collections for this month'];
}
if ($canViewPayroll) {
    $opsRows[] = ['label' => 'Payroll Queue', 'value' => number_format((float) ($stats['pending_payroll_requests'] ?? 0)), 'hint' => 'Salary requests awaiting approval or final payment'];
}

$quickActions = [];
if ($canViewExpenseOperations) {
    $quickActions[] = [
        'title' => 'Expense Queue',
        'description' => 'Review and action pending expense requests.',
        'value' => number_format((float) ($stats['pending_requests'] ?? 0)),
        'icon' => 'fa-receipt',
        'href' => APP_URL . '/pages/view_expense_requests.php',
        'tone' => 'orange',
    ];
}
if ($canViewStations) {
    $quickActions[] = [
        'title' => 'Station Approvals',
        'description' => 'Open station setup workflow tasks and approvals.',
        'value' => number_format((float) ($stats['pending_stations'] ?? 0)),
        'icon' => 'fa-tower-broadcast',
        'href' => APP_URL . '/pages/stations.php#station-setup-requests',
        'tone' => 'indigo',
    ];
}
if ($canViewInventory) {
    $quickActions[] = [
        'title' => 'Low Stock',
        'description' => 'Inspect restock alerts and inventory shortages.',
        'value' => number_format((float) ($stats['low_stock'] ?? 0)),
        'icon' => 'fa-triangle-exclamation',
        'href' => APP_URL . '/pages/low_stock_alerts.php',
        'tone' => 'amber',
    ];
}
if ($canViewPayroll) {
    $quickActions[] = [
        'title' => 'Payroll Approvals',
        'description' => 'Handle salary approvals, finalization, and payslips.',
        'value' => number_format((float) ($stats['pending_payroll_requests'] ?? 0)),
        'icon' => 'fa-money-check-dollar',
        'href' => APP_URL . '/pages/payroll.php',
        'tone' => 'cyan',
    ];
}
if ($canViewAllFinancials) {
    $quickActions[] = [
        'title' => 'Reports',
        'description' => 'Open full analytics and export-ready reporting views.',
        'value' => 'Open',
        'icon' => 'fa-chart-column',
        'href' => APP_URL . '/pages/reports.php',
        'tone' => 'blue',
    ];
}

$directActions = [];
if ($hasRole(['Accountant', 'Super Admin'])) {
    $directActions[] = [
        'title' => 'Create Salary Request',
        'description' => 'Open the salary request form and start a new payroll workflow item.',
        'icon' => 'fa-plus-circle',
        'href' => APP_URL . '/pages/create_salary_request.php',
        'tone' => 'cyan',
    ];
}
if ($hasRole(['Sales', 'Technician', 'Super Admin'])) {
    $directActions[] = [
        'title' => 'Add Expense Request',
        'description' => 'Submit a new expense request with approval routing and receipt follow-up.',
        'icon' => 'fa-file-circle-plus',
        'href' => APP_URL . '/pages/add_expense_request.php',
        'tone' => 'orange',
    ];
}
if ($hasRole(['Store Keeper', 'Super Admin'])) {
    $directActions[] = [
        'title' => 'Add Inventory Item',
        'description' => 'Capture new stock items, pricing, supplier details, and availability.',
        'icon' => 'fa-box-open',
        'href' => APP_URL . '/pages/add_inventory.php',
        'tone' => 'amber',
    ];
}
if ($hasRole(['Technician'])) {
    $directActions[] = [
        'title' => 'Request Station Setup',
        'description' => 'Create a new station installation request and start the deployment workflow.',
        'icon' => 'fa-tower-broadcast',
        'href' => APP_URL . '/pages/request_new_station_setup.php',
        'tone' => 'indigo',
    ];
}
if ($hasRole(['Super Admin'])) {
    $directActions[] = [
        'title' => 'Add User',
        'description' => 'Create a new system user with roles, payout details, and employee information.',
        'icon' => 'fa-user-plus',
        'href' => APP_URL . '/pages/add_user.php',
        'tone' => 'blue',
    ];
}
if ($hasRole(['Director', 'Accountant', 'Super Admin'])) {
    $directActions[] = [
        'title' => 'Open Reports',
        'description' => 'Go straight to the professional reporting workspace and analytics exports.',
        'icon' => 'fa-chart-column',
        'href' => APP_URL . '/pages/reports.php',
        'tone' => 'emerald',
    ];
}

$statusTone = static function (string $status): string {
    $normalized = strtolower(trim($status));
    if (str_contains($normalized, 'completed') || str_contains($normalized, 'paid') || str_contains($normalized, 'approved')) {
        return 'success';
    }
    if (str_contains($normalized, 'rejected') || str_contains($normalized, 'failed')) {
        return 'danger';
    }
    if (str_contains($normalized, 'pending') || str_contains($normalized, 'waiting')) {
        return 'warning';
    }
    return 'secondary';
};
?>
<style>
    :root {
        --dash-bg: #eef3f8;
        --dash-panel: #ffffff;
        --dash-border: #d8e1eb;
        --dash-text: #223047;
        --dash-muted: #71829b;
        --dash-navy: #1f3d63;
        --dash-blue: #3a7bd5;
        --dash-teal: #0f9d94;
        --dash-emerald: #2d9d78;
        --dash-amber: #d48b18;
        --dash-rose: #d45a6b;
        --dash-violet: #6f63d9;
        --dash-slate: #53657d;
        --dash-shadow: 0 14px 35px rgba(27, 39, 60, 0.08);
    }

    .dash-admin {
        background:
            radial-gradient(circle at top right, rgba(58, 123, 213, 0.12), transparent 26%),
            linear-gradient(180deg, #f8fbff 0%, var(--dash-bg) 100%);
        border-radius: 28px;
        padding: 1.5rem;
        border: 1px solid rgba(216, 225, 235, 0.9);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75);
    }

    .dash-topbar {
        background: linear-gradient(135deg, var(--dash-navy) 0%, #29578e 50%, #3a7bd5 100%);
        border-radius: 24px;
        padding: 1.5rem;
        color: #fff;
        box-shadow: 0 20px 40px rgba(31, 61, 99, 0.22);
        overflow: hidden;
        position: relative;
    }

    .dash-topbar::after {
        content: '';
        position: absolute;
        width: 260px;
        height: 260px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.08);
        right: -80px;
        top: -120px;
    }

    .dash-topbar h2,
    .dash-topbar h6 {
        color: #fff;
    }

    .dash-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.5rem 0.8rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.14);
        font-size: 0.86rem;
        font-weight: 600;
        color: #f8fbff;
        border: 1px solid rgba(255, 255, 255, 0.16);
    }

    .dash-role-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.6rem;
        margin-top: 1rem;
    }

    .dash-role-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.45rem 0.75rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.12);
        color: #f8fbff;
        border: 1px solid rgba(255, 255, 255, 0.14);
        font-size: 0.82rem;
        font-weight: 700;
    }

    .dash-panel {
        background: var(--dash-panel);
        border: 1px solid var(--dash-border);
        border-radius: 22px;
        box-shadow: var(--dash-shadow);
        height: 100%;
    }

    .dash-panel-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        padding: 1.2rem 1.3rem 0;
    }

    .dash-panel-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--dash-text);
        margin: 0;
    }

    .dash-panel-subtitle {
        color: var(--dash-muted);
        font-size: 0.85rem;
        margin-top: 0.15rem;
    }

    .dash-panel-body {
        padding: 1.15rem 1.3rem 1.3rem;
    }

    .dash-stat-card {
        position: relative;
        overflow: hidden;
        min-height: 170px;
    }

    .dash-stat-card::before {
        content: '';
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 5px;
        background: var(--card-accent, var(--dash-blue));
    }

    .dash-stat-icon {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        color: #fff;
        background: var(--card-accent, var(--dash-blue));
        box-shadow: 0 12px 22px color-mix(in srgb, var(--card-accent, var(--dash-blue)) 28%, transparent);
    }

    .dash-stat-label {
        color: var(--dash-muted);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 0.76rem;
        font-weight: 700;
        margin-bottom: 0.55rem;
    }

    .dash-stat-value {
        color: var(--dash-text);
        font-size: 1.6rem;
        font-weight: 800;
        line-height: 1.15;
        margin-bottom: 0.45rem;
    }

    .dash-stat-meta {
        color: var(--dash-muted);
        font-size: 0.88rem;
    }

    .dash-tone-emerald { --card-accent: var(--dash-emerald); }
    .dash-tone-blue { --card-accent: var(--dash-blue); }
    .dash-tone-cyan { --card-accent: var(--dash-teal); }
    .dash-tone-amber { --card-accent: var(--dash-amber); }
    .dash-tone-rose { --card-accent: var(--dash-rose); }
    .dash-tone-violet { --card-accent: var(--dash-violet); }
    .dash-tone-slate { --card-accent: var(--dash-slate); }
    .dash-tone-orange { --card-accent: #d57b32; }
    .dash-tone-indigo { --card-accent: #4657cc; }

    .dash-chart {
        height: 320px;
        position: relative;
    }

    .dash-overview-list {
        display: grid;
        gap: 0.9rem;
    }

    .dash-overview-item {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        padding: 0.95rem 1rem;
        border: 1px solid #e6edf4;
        border-radius: 16px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }

    .dash-overview-item strong {
        color: var(--dash-text);
        display: block;
        margin-bottom: 0.18rem;
    }

    .dash-overview-item span {
        color: var(--dash-muted);
        font-size: 0.86rem;
    }

    .dash-overview-value {
        font-weight: 800;
        color: var(--dash-text);
        white-space: nowrap;
    }

    .dash-table {
        margin-bottom: 0;
    }

    .dash-table thead th {
        background: #f3f7fb;
        color: #60758d;
        font-size: 0.76rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        border-bottom: 1px solid #e3ebf2;
        padding: 0.9rem 1rem;
    }

    .dash-table tbody td {
        padding: 0.95rem 1rem;
        vertical-align: middle;
        color: var(--dash-text);
        border-bottom: 1px solid #edf2f7;
    }

    .dash-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .dash-table tbody tr:hover {
        background: #f9fbfd;
    }

    .dash-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 0.35rem 0.7rem;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .dash-pill-success { background: rgba(45, 157, 120, 0.12); color: #23795d; }
    .dash-pill-warning { background: rgba(212, 139, 24, 0.14); color: #a36b12; }
    .dash-pill-danger { background: rgba(212, 90, 107, 0.13); color: #a93f50; }
    .dash-pill-secondary { background: rgba(83, 101, 125, 0.12); color: #4c5f76; }

    .dash-empty {
        color: var(--dash-muted);
        text-align: center;
        padding: 1.75rem 1rem;
    }

    .dash-mini-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.9rem;
    }

    .dash-mini-card {
        border: 1px solid #e6edf4;
        border-radius: 18px;
        padding: 1rem;
        background: #fff;
    }

    .dash-mini-card .label {
        color: var(--dash-muted);
        font-size: 0.77rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 800;
    }

    .dash-mini-card .value {
        color: var(--dash-text);
        font-size: 1.1rem;
        font-weight: 800;
        margin-top: 0.35rem;
    }

    .dash-report-link {
        display: flex;
        align-items: flex-start;
        gap: 0.85rem;
        padding: 1rem;
        border: 1px solid #e6edf4;
        border-radius: 18px;
        text-decoration: none;
        color: inherit;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .dash-report-link:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 28px rgba(15, 23, 42, 0.08);
        border-color: #c9d8e8;
        color: inherit;
    }

    .dash-report-icon {
        width: 46px;
        height: 46px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #1f3d63 0%, #3a7bd5 100%);
        color: #fff;
        flex-shrink: 0;
    }

    .dash-report-link strong {
        display: block;
        color: var(--dash-text);
        margin-bottom: 0.2rem;
    }

    .dash-report-link span {
        color: var(--dash-muted);
        font-size: 0.86rem;
        line-height: 1.45;
    }

    .dash-report-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 0.9rem;
    }

    .dash-quick-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
    }

    .dash-notice-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
    }

    .dash-notice-card {
        display: flex;
        align-items: flex-start;
        gap: 0.95rem;
        padding: 1rem 1.05rem;
        border-radius: 20px;
        border: 1px solid #e4ebf2;
        text-decoration: none;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .dash-notice-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 34px rgba(15, 23, 42, 0.09);
        border-color: #c9d8e8;
    }

    .dash-notice-icon {
        width: 48px;
        height: 48px;
        border-radius: 15px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        background: var(--card-accent, var(--dash-blue));
        flex-shrink: 0;
    }

    .dash-notice-kicker {
        color: var(--card-accent, var(--dash-blue));
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 0.72rem;
        font-weight: 800;
        margin-bottom: 0.35rem;
    }

    .dash-notice-title {
        color: var(--dash-text);
        font-weight: 800;
        margin-bottom: 0.2rem;
    }

    .dash-notice-copy {
        color: var(--dash-muted);
        font-size: 0.9rem;
        line-height: 1.5;
        margin-bottom: 0.45rem;
    }

    .dash-notice-meta {
        color: var(--dash-muted);
        font-size: 0.82rem;
        line-height: 1.45;
    }

    .dash-quick-card {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 190px;
        padding: 1.1rem;
        border-radius: 20px;
        border: 1px solid #e4ebf2;
        text-decoration: none;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .dash-quick-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 34px rgba(15, 23, 42, 0.09);
        border-color: #c9d8e8;
    }

    .dash-quick-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .dash-quick-title {
        color: var(--dash-text);
        font-weight: 800;
        margin-bottom: 0.25rem;
    }

    .dash-quick-copy {
        color: var(--dash-muted);
        font-size: 0.86rem;
        line-height: 1.45;
    }

    .dash-quick-icon {
        width: 48px;
        height: 48px;
        border-radius: 15px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        background: var(--card-accent, var(--dash-blue));
        flex-shrink: 0;
    }

    .dash-quick-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .dash-quick-value {
        color: var(--dash-text);
        font-size: 1.35rem;
        font-weight: 800;
    }

    .dash-quick-link {
        color: var(--card-accent, var(--dash-blue));
        font-size: 0.82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .dash-direct-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
    }

    .dash-direct-card {
        display: flex;
        align-items: flex-start;
        gap: 0.95rem;
        padding: 1.1rem;
        border-radius: 20px;
        border: 1px solid #e4ebf2;
        text-decoration: none;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }

    .dash-direct-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 34px rgba(15, 23, 42, 0.09);
        border-color: #c9d8e8;
    }

    .dash-direct-icon {
        width: 48px;
        height: 48px;
        border-radius: 15px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        background: var(--card-accent, var(--dash-blue));
        flex-shrink: 0;
    }

    .dash-direct-title {
        color: var(--dash-text);
        font-weight: 800;
        margin-bottom: 0.25rem;
    }

    .dash-direct-copy {
        color: var(--dash-muted);
        font-size: 0.86rem;
        line-height: 1.45;
        margin-bottom: 0.45rem;
    }

    .dash-direct-link {
        color: var(--card-accent, var(--dash-blue));
        font-size: 0.82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }

    .dash-activity-user {
        font-weight: 700;
        color: var(--dash-text);
    }

    .dash-activity-meta {
        font-size: 0.82rem;
        color: var(--dash-muted);
    }

    @media (max-width: 991px) {
        .dash-admin {
            padding: 1rem;
            border-radius: 20px;
        }

        .dash-topbar {
            padding: 1.2rem;
            border-radius: 20px;
        }

        .dash-chart {
            height: 280px;
        }

        .dash-notice-card,
        .dash-quick-card,
        .dash-direct-card {
            padding: 1rem;
        }
    }
</style>

<div class="container-fluid">
    <div class="dash-admin">
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="dash-topbar">
                    <div class="row align-items-center g-3 position-relative">
                        <div class="col-lg-8">
                            <div class="mb-2 text-uppercase small fw-semibold opacity-75">JASSNET Control Center</div>
                            <h2 class="mb-2">Professional Operations Dashboard</h2>
                            <div class="opacity-75">Income, expense approvals, inventory movement, and station deployment metrics in one responsive workspace.</div>
                            <div class="dash-role-list">
                                <span class="dash-role-pill"><i class="fas fa-user"></i> <?= htmlspecialchars($user['full_name'] ?? ($user['username'] ?? 'User')) ?></span>
                                <?php foreach ($userRoles as $roleLabel): ?>
                                    <span class="dash-role-pill"><i class="fas fa-shield-halved"></i> <?= htmlspecialchars($roleLabel) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                                <span class="dash-chip"><i class="fas fa-calendar-day"></i> <?= date('d M Y') ?></span>
                                <span class="dash-chip"><i class="fas fa-user-shield"></i> <?= $userRoleCount ?> <?= $userRoleCount === 1 ? 'Role' : 'Roles' ?></span>
                                <?php if ($canViewAllFinancials): ?>
                                    <span class="dash-chip"><i class="fas fa-chart-line"></i> <?= $formatCurrency($stats['net_profit'] ?? 0) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-<?= htmlspecialchars($message['type'] === 'error' ? 'danger' : $message['type']) ?> mb-0">
                        <?= htmlspecialchars($message['text']) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($statCards)): ?>
            <div class="row g-4 mb-4">
                <?php foreach ($statCards as $card): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 col-12">
                        <div class="dash-panel dash-stat-card dash-tone-<?= htmlspecialchars($card['tone']) ?>">
                            <div class="dash-panel-body d-flex flex-column justify-content-between h-100">
                                <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                                    <div>
                                        <div class="dash-stat-label"><?= htmlspecialchars($card['title']) ?></div>
                                        <div class="dash-stat-value"><?= $card['value'] ?></div>
                                    </div>
                                    <span class="dash-stat-icon"><i class="fas <?= htmlspecialchars($card['icon']) ?>"></i></span>
                                </div>
                                <div class="dash-stat-meta"><?= htmlspecialchars($card['meta']) ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <?php if ($canViewAllFinancials): ?>
                <div class="col-xxl-8 col-xl-7">
                    <div class="dash-panel">
                        <div class="dash-panel-header">
                            <div>
                                <h5 class="dash-panel-title">Financial Performance</h5>
                                <div class="dash-panel-subtitle">Income and expense movement for the last six months.</div>
                            </div>
                        </div>
                        <div class="dash-panel-body">
                            <div class="dash-chart"><canvas id="incomeExpenseChart"></canvas></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="<?= $canViewAllFinancials ? 'col-xxl-4 col-xl-5' : 'col-12' ?>">
                <div class="dash-panel h-100">
                    <div class="dash-panel-header">
                        <div>
                            <h5 class="dash-panel-title">Operations Snapshot</h5>
                            <div class="dash-panel-subtitle">Current workflow pressure points and totals.</div>
                        </div>
                    </div>
                    <div class="dash-panel-body">
                        <?php if (!empty($opsRows)): ?>
                            <div class="dash-overview-list">
                                <?php foreach ($opsRows as $opsRow): ?>
                                    <div class="dash-overview-item">
                                        <div>
                                            <strong><?= htmlspecialchars($opsRow['label']) ?></strong>
                                            <span><?= htmlspecialchars($opsRow['hint']) ?></span>
                                        </div>
                                        <div class="dash-overview-value"><?= htmlspecialchars($opsRow['value']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="dash-empty">No operational summary is available for this role.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($roleNotices)): ?>
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="dash-panel">
                    <div class="dash-panel-header">
                        <div>
                            <h5 class="dash-panel-title">Role Notices</h5>
                            <div class="dash-panel-subtitle">Immediate actions for your current role queues before you jump into module shortcuts.</div>
                        </div>
                    </div>
                    <div class="dash-panel-body">
                        <div class="dash-notice-grid">
                            <?php foreach ($roleNotices as $notice): ?>
                                <a href="<?= htmlspecialchars((string) ($notice['href'] ?? '#')) ?>" class="dash-notice-card dash-tone-<?= htmlspecialchars((string) ($notice['tone'] ?? 'blue')) ?>">
                                    <span class="dash-notice-icon"><i class="fas <?= htmlspecialchars((string) ($notice['icon'] ?? 'fa-bell')) ?>"></i></span>
                                    <span>
                                        <div class="dash-notice-kicker">Role-Specific Notice</div>
                                        <div class="dash-notice-title"><?= htmlspecialchars((string) ($notice['title'] ?? 'Operational Notice')) ?></div>
                                        <div class="dash-notice-copy"><?= htmlspecialchars((string) ($notice['message'] ?? '')) ?></div>
                                        <div class="dash-notice-meta"><?= htmlspecialchars((string) ($notice['meta'] ?? '')) ?></div>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($quickActions)): ?>
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="dash-panel">
                    <div class="dash-panel-header">
                        <div>
                            <h5 class="dash-panel-title">Module Quick Actions</h5>
                            <div class="dash-panel-subtitle">Jump directly into the busiest modules and pending operational queues.</div>
                        </div>
                    </div>
                    <div class="dash-panel-body">
                        <div class="dash-quick-grid">
                            <?php foreach ($quickActions as $action): ?>
                                <a href="<?= htmlspecialchars((string) ($action['href'] ?? '#')) ?>" class="dash-quick-card dash-tone-<?= htmlspecialchars((string) ($action['tone'] ?? 'blue')) ?>">
                                    <div class="dash-quick-top">
                                        <div>
                                            <div class="dash-quick-title"><?= htmlspecialchars((string) ($action['title'] ?? 'Quick Action')) ?></div>
                                            <div class="dash-quick-copy"><?= htmlspecialchars((string) ($action['description'] ?? '')) ?></div>
                                        </div>
                                        <span class="dash-quick-icon"><i class="fas <?= htmlspecialchars((string) ($action['icon'] ?? 'fa-bolt')) ?>"></i></span>
                                    </div>
                                    <div class="dash-quick-bottom">
                                        <div class="dash-quick-value"><?= htmlspecialchars((string) ($action['value'] ?? '0')) ?></div>
                                        <div class="dash-quick-link">Open Module</div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($directActions)): ?>
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="dash-panel">
                    <div class="dash-panel-header">
                        <div>
                            <h5 class="dash-panel-title">Direct Actions</h5>
                            <div class="dash-panel-subtitle">Open create forms and high-value entry points directly from the dashboard.</div>
                        </div>
                    </div>
                    <div class="dash-panel-body">
                        <div class="dash-direct-grid">
                            <?php foreach ($directActions as $action): ?>
                                <a href="<?= htmlspecialchars((string) ($action['href'] ?? '#')) ?>" class="dash-direct-card dash-tone-<?= htmlspecialchars((string) ($action['tone'] ?? 'blue')) ?>">
                                    <span class="dash-direct-icon"><i class="fas <?= htmlspecialchars((string) ($action['icon'] ?? 'fa-bolt')) ?>"></i></span>
                                    <span>
                                        <div class="dash-direct-title"><?= htmlspecialchars((string) ($action['title'] ?? 'Direct Action')) ?></div>
                                        <div class="dash-direct-copy"><?= htmlspecialchars((string) ($action['description'] ?? '')) ?></div>
                                        <div class="dash-direct-link">Open Page</div>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <?php if ($canViewAllFinancials): ?>
                <div class="col-xl-6">
                    <div class="dash-panel">
                        <div class="dash-panel-header">
                            <div>
                                <h5 class="dash-panel-title">Customer Growth</h5>
                                <div class="dash-panel-subtitle">Distinct customer activity trend per month.</div>
                            </div>
                        </div>
                        <div class="dash-panel-body">
                            <div class="dash-chart"><canvas id="customerGrowthChart"></canvas></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($canViewInventoryCharts): ?>
                <div class="col-xl-<?= $canViewStationCharts ? '6' : ($canViewAllFinancials ? '6' : '12') ?>">
                    <div class="dash-panel">
                        <div class="dash-panel-header">
                            <div>
                                <h5 class="dash-panel-title">Inventory Additions</h5>
                                <div class="dash-panel-subtitle">Stock entries recorded by month.</div>
                            </div>
                        </div>
                        <div class="dash-panel-body">
                            <div class="dash-chart"><canvas id="inventoryUsageChart"></canvas></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($canViewStationCharts): ?>
                <div class="col-xl-<?= $canViewInventoryCharts || $canViewAllFinancials ? '6' : '12' ?>">
                    <div class="dash-panel">
                        <div class="dash-panel-header">
                            <div>
                                <h5 class="dash-panel-title">Station Progress</h5>
                                <div class="dash-panel-subtitle">Stacked workflow counts by month.</div>
                            </div>
                        </div>
                        <div class="dash-panel-body">
                            <div class="dash-chart"><canvas id="stationProgressChart"></canvas></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="row g-4 mb-4">
            <?php if ($canViewIncomeSummary || $canViewOwnIncome): ?>
                <div class="col-xl-6">
                    <div class="dash-panel">
                        <div class="dash-panel-header">
                            <div>
                                <h5 class="dash-panel-title"><?= $canViewAllFinancials ? 'Recent Income' : 'My Recent Income' ?></h5>
                                <div class="dash-panel-subtitle">Latest income records available to your role.</div>
                            </div>
                        </div>
                        <div class="dash-panel-body p-0">
                            <div class="table-responsive">
                                <table class="table dash-table">
                                    <thead><tr><th>Date</th><th>Customer</th><th>Amount</th></tr></thead>
                                    <tbody>
                                    <?php if (!empty($recent_income)): ?>
                                        <?php foreach ($recent_income as $inc): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($inc['date']) ?></td>
                                                <td><?= htmlspecialchars($inc['customer_name']) ?></td>
                                                <td><?= $formatCurrency($inc['amount']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="dash-empty">No recent income records.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($canViewExpenseOperations): ?>
                <div class="col-xl-6">
                    <div class="dash-panel">
                        <div class="dash-panel-header">
                            <div>
                                <h5 class="dash-panel-title"><?= $canViewExpenseSummary ? 'Recent Expense Requests' : 'My Recent Expense Requests' ?></h5>
                                <div class="dash-panel-subtitle">Most recent expense workflow updates.</div>
                            </div>
                        </div>
                        <div class="dash-panel-body p-0">
                            <div class="table-responsive">
                                <table class="table dash-table">
                                    <thead><tr><th>Date</th><th>Amount</th><th>Status</th></tr></thead>
                                    <tbody>
                                    <?php if (!empty($recent_expenses)): ?>
                                        <?php foreach ($recent_expenses as $exp): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($exp['request_date']) ?></td>
                                                <td><?= $formatCurrency($exp['amount_requested']) ?></td>
                                                <td><span class="dash-pill dash-pill-<?= $statusTone((string) ($exp['status'] ?? '')) ?>"><?= htmlspecialchars($exp['status']) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="dash-empty">No recent expense requests.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <?php if ($canViewInventory): ?>
                <div class="col-xl-6">
                    <div class="dash-panel">
                        <div class="dash-panel-header">
                            <div>
                                <h5 class="dash-panel-title">Low Stock Alerts</h5>
                                <div class="dash-panel-subtitle">Items that need restocking attention.</div>
                            </div>
                        </div>
                        <div class="dash-panel-body p-0">
                            <div class="table-responsive">
                                <table class="table dash-table">
                                    <thead><tr><th>Item</th><th>Qty</th></tr></thead>
                                    <tbody>
                                    <?php if (!empty($low_stock_items)): ?>
                                        <?php foreach ($low_stock_items as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['item_name']) ?></td>
                                                <td><?= htmlspecialchars($item['quantity']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="2" class="dash-empty">No low stock alerts.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($canViewStations): ?>
                <div class="col-xl-6">
                    <div class="dash-panel">
                        <div class="dash-panel-header">
                            <div>
                                <h5 class="dash-panel-title">New Station Requests</h5>
                                <div class="dash-panel-subtitle">Latest station workflow items.</div>
                            </div>
                        </div>
                        <div class="dash-panel-body p-0">
                            <div class="table-responsive">
                                <table class="table dash-table">
                                    <thead><tr><th>Date</th><th>Name</th><th>Status</th></tr></thead>
                                    <tbody>
                                    <?php if (!empty($recent_stations)): ?>
                                        <?php foreach ($recent_stations as $st): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($st['request_date']) ?></td>
                                                <td><?= htmlspecialchars($st['station_name']) ?></td>
                                                <td><span class="dash-pill dash-pill-<?= $statusTone((string) ($st['status'] ?? '')) ?>"><?= htmlspecialchars($st['status']) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="dash-empty">No station requests found.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="row g-4 mt-1">
            <?php if ($canViewRecentActivity): ?>
            <div class="col-xl-7">
                <div class="dash-panel">
                    <div class="dash-panel-header">
                        <div>
                            <h5 class="dash-panel-title">Recent Activity</h5>
                            <div class="dash-panel-subtitle">Latest logged actions across the admin workspace.</div>
                        </div>
                    </div>
                    <div class="dash-panel-body p-0">
                        <div class="table-responsive">
                            <table class="table dash-table">
                                <thead><tr><th>User</th><th>Action</th><th>Description</th><th>Time</th></tr></thead>
                                <tbody>
                                <?php if (!empty($recentActivities)): ?>
                                    <?php foreach ($recentActivities as $activity): ?>
                                        <tr>
                                            <td>
                                                <div class="dash-activity-user"><?= htmlspecialchars((string) ($activity['full_name'] ?? 'System')) ?></div>
                                                <div class="dash-activity-meta"><?= htmlspecialchars((string) ($activity['user_role'] ?? '')) ?></div>
                                            </td>
                                            <td><?= htmlspecialchars((string) ($activity['action'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string) ($activity['description'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars((string) ($activity['created_at'] ?? '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="dash-empty">No recent activity available for this role.</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="<?= $canViewRecentActivity ? 'col-xl-5' : 'col-12' ?>">
                <div class="dash-panel h-100">
                    <div class="dash-panel-header">
                        <div>
                            <h5 class="dash-panel-title">Reports Section</h5>
                            <div class="dash-panel-subtitle">Open clean report views and export-ready analytics pages.</div>
                        </div>
                    </div>
                    <div class="dash-panel-body d-grid gap-3">
                        <?php if (!empty($reportLinks)): ?>
                            <div class="dash-report-grid">
                                <?php foreach ($reportLinks as $report): ?>
                                    <a href="<?= htmlspecialchars((string) ($report['href'] ?? '#')) ?>" class="dash-report-link">
                                        <span class="dash-report-icon"><i class="fas <?= htmlspecialchars((string) ($report['icon'] ?? 'fa-chart-column')) ?>"></i></span>
                                        <span>
                                            <strong><?= htmlspecialchars((string) ($report['title'] ?? 'Report')) ?></strong>
                                            <span><?= htmlspecialchars((string) ($report['description'] ?? '')) ?></span>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="dash-empty">No report shortcuts available.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="dash-mini-grid">
                    <div class="dash-mini-card">
                        <div class="label">Dashboard User</div>
                        <div class="value"><?= htmlspecialchars($user['full_name'] ?? ($user['username'] ?? 'User')) ?></div>
                    </div>
                    <div class="dash-mini-card">
                        <div class="label">Role Access</div>
                        <div class="value"><?= htmlspecialchars($userRoleSummary) ?></div>
                    </div>
                    <div class="dash-mini-card">
                        <div class="label">Assigned Roles</div>
                        <div class="value"><?= number_format($userRoleCount) ?></div>
                    </div>
                    <div class="dash-mini-card">
                        <div class="label">Today</div>
                        <div class="value"><?= date('d M Y') ?></div>
                    </div>
                    <div class="dash-mini-card">
                        <div class="label">Visible Modules</div>
                        <div class="value"><?= number_format(count($statCards)) ?> Cards</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const chartData = <?= json_encode($chart_data ?? ['months' => [], 'income' => [], 'expenses' => [], 'customerGrowth' => [], 'inventoryUsage' => [], 'stationProgress' => []]); ?>;

document.addEventListener('DOMContentLoaded', function() {
    const commonGridColor = 'rgba(113, 130, 155, 0.14)';
    const commonTickColor = '#61748a';

    const buildMoneyAxis = {
        ticks: {
            color: commonTickColor,
            callback: function(value) {
                return 'Tshs. ' + Number(value).toLocaleString();
            }
        },
        grid: {
            color: commonGridColor
        },
        border: {
            display: false
        }
    };

    const buildCategoryAxis = {
        ticks: {
            color: commonTickColor
        },
        grid: {
            display: false
        },
        border: {
            display: false
        }
    };

    const incomeExpenseCanvas = document.getElementById('incomeExpenseChart');
    if (incomeExpenseCanvas) {
        new Chart(incomeExpenseCanvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: chartData.months,
                datasets: [
                    {
                        label: 'Income',
                        data: chartData.income,
                        backgroundColor: '#2d9d78',
                        borderRadius: 10,
                        maxBarThickness: 24
                    },
                    {
                        label: 'Expenses',
                        data: chartData.expenses,
                        backgroundColor: '#d45a6b',
                        borderRadius: 10,
                        maxBarThickness: 24
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: commonTickColor,
                            usePointStyle: true,
                            boxWidth: 10
                        }
                    }
                },
                scales: {
                    x: buildCategoryAxis,
                    y: buildMoneyAxis
                }
            }
        });
    }

    const customerGrowthCanvas = document.getElementById('customerGrowthChart');
    if (customerGrowthCanvas) {
        new Chart(customerGrowthCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: chartData.months,
                datasets: [{
                    label: 'Customers',
                    data: chartData.customerGrowth,
                    borderColor: '#3a7bd5',
                    backgroundColor: 'rgba(58, 123, 213, 0.12)',
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#ffffff',
                    pointBorderWidth: 2,
                    tension: 0.35
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: buildCategoryAxis,
                    y: {
                        ticks: { color: commonTickColor },
                        grid: { color: commonGridColor },
                        border: { display: false }
                    }
                }
            }
        });
    }

    const inventoryCanvas = document.getElementById('inventoryUsageChart');
    if (inventoryCanvas) {
        new Chart(inventoryCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: chartData.months,
                datasets: [{
                    label: 'New Inventory Items',
                    data: chartData.inventoryUsage,
                    borderColor: '#d48b18',
                    backgroundColor: 'rgba(212, 139, 24, 0.12)',
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#ffffff',
                    pointBorderWidth: 2,
                    tension: 0.35
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: buildCategoryAxis,
                    y: {
                        ticks: { color: commonTickColor },
                        grid: { color: commonGridColor },
                        border: { display: false }
                    }
                }
            }
        });
    }

    const stationCanvas = document.getElementById('stationProgressChart');
    if (stationCanvas) {
        const stationCtx = stationCanvas.getContext('2d');
        const statuses = {};
        chartData.stationProgress.forEach((row, index) => {
            Object.keys(row).forEach(status => {
                statuses[status] = statuses[status] || [];
                statuses[status][index] = row[status];
            });
        });

        const palette = ['#4657cc', '#2d9d78', '#d48b18', '#d45a6b', '#0f9d94', '#6f63d9', '#53657d'];
        const datasets = Object.keys(statuses).map((status, idx) => ({
            label: status,
            data: statuses[status],
            backgroundColor: palette[idx % palette.length],
            borderRadius: 8,
            maxBarThickness: 28
        }));

        new Chart(stationCtx, {
            type: 'bar',
            data: {
                labels: chartData.months,
                datasets: datasets
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: commonTickColor,
                            usePointStyle: true,
                            boxWidth: 10
                        }
                    }
                },
                scales: {
                    x: { ...buildCategoryAxis, stacked: true },
                    y: {
                        stacked: true,
                        ticks: { color: commonTickColor },
                        grid: { color: commonGridColor },
                        border: { display: false }
                    }
                }
            }
        });
    }
});
</script>

<?php $content = ob_get_clean(); ?>
<?php include APP_ROOT . '/app/views/layouts/main.php'; ?>