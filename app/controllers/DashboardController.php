<?php
/**
 * DashboardController - Handle dashboard operations
 */

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Income;
use App\Models\ExpenseRequest;
use App\Models\Inventory;
use App\Models\StationRequest;

class DashboardController extends BaseController
{
    private $incomeModel;
    private $expenseModel;
    private $inventoryModel;
    private $stationModel;
    private $stationAssignedToExists;

    public function __construct()
    {
        parent::__construct();

        $this->incomeModel = new Income();
        $this->expenseModel = new ExpenseRequest();
        $this->inventoryModel = new Inventory();
        $this->stationModel = new StationRequest();
    }

    /**
     * Display dashboard
     */
    public function index()
    {
        $message = $this->getMessage();
        $permissions = $this->getDashboardPermissions();

        try {
            $inventoryStats = $this->inventoryModel->getStatistics();
            $stationStats = $this->stationModel->getStatistics();
            $stats = $this->getStats($inventoryStats, $stationStats);
            $this->data = [
                'user' => $this->user,
                'stats' => $stats,
                'message' => $message,
                'recent_income' => $this->getRecentIncome(5),
                'recent_expenses' => $this->getRecentExpenses(5),
                'recent_stations' => $this->getRecentStations(5),
                'low_stock_items' => $this->getRecentLowStock(),
                'recent_activities' => $this->getRecentActivities(8),
                'report_links' => $this->getReportLinks(),
                'role_notices' => $this->getRoleNotices($stats, $permissions),
                'chart_data' => $this->getChartData($permissions),
                'dashboard_permissions' => $permissions,
            ];
        } catch (\Throwable $e) {
            if (APP_DEBUG) {
                error_log('Dashboard load error: ' . $e->getMessage());
            }

            $this->data = [
                'user' => $this->user,
                'stats' => [],
                'message' => [
                    'text' => APP_DEBUG
                        ? 'Dashboard data could not load: ' . $e->getMessage()
                        : 'Dashboard data could not load. Please contact administrator.',
                    'type' => 'warning',
                ],
                'recent_income' => [],
                'recent_expenses' => [],
                'recent_stations' => [],
                'low_stock_items' => [],
                'recent_activities' => [],
                'report_links' => $this->getReportLinks(),
                'role_notices' => [],
                'chart_data' => [
                    'months' => [],
                    'income' => [],
                    'expenses' => [],
                    'customerGrowth' => [],
                    'inventoryUsage' => [],
                    'stationProgress' => [],
                ],
                'dashboard_permissions' => $permissions,
            ];
        }

        $this->render('dashboard/index', $this->data);
    }

    private function ensureAnnouncementsTable()
    {
        $this->db->prepare(
            "CREATE TABLE IF NOT EXISTS announcements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                message TEXT NOT NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NULL,
                is_active TINYINT(1) DEFAULT 1,
                FOREIGN KEY (created_by) REFERENCES users(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $this->db->execute();

        // Backward compatibility for existing installations.
        $this->db->prepare("SHOW COLUMNS FROM announcements LIKE 'expires_at'");
        $col = $this->db->fetch();
        if (!$col) {
            $this->db->prepare('ALTER TABLE announcements ADD COLUMN expires_at DATETIME NULL AFTER created_at');
            $this->db->execute();
        }
    }

    private function handleAnnouncementPost()
    {
        if (!$this->hasPermission(['Store Keeper', 'Manager', 'Director', 'Super Admin'])) {
            $this->error('You are not allowed to post announcements.');
            $this->redirect(APP_URL . '/dashboard.php');
        }

        $message = trim((string) ($_POST['announcement_message'] ?? ''));
        if ($message === '') {
            $this->warning('Announcement message is required.');
            $this->redirect(APP_URL . '/dashboard.php');
        }

        if (mb_strlen($message) > 600) {
            $message = mb_substr($message, 0, 600);
        }

        $expiryInput = trim((string) ($_POST['announcement_expiry_date'] ?? ''));
        $expiresAt = null;
        if ($expiryInput !== '') {
            $expiresAt = date('Y-m-d 23:59:59', strtotime($expiryInput));
        } else {
            $expiresAt = date('Y-m-d 23:59:59', strtotime('+3 days'));
        }

        $this->db->prepare('INSERT INTO announcements (message, created_by, expires_at, is_active) VALUES (:message, :created_by, :expires_at, 1)');
        $this->db->bind(':message', $message);
        $this->db->bind(':created_by', (int) $this->user['id']);
        $this->db->bind(':expires_at', $expiresAt);
        $this->db->execute();

        $this->success('Announcement sent to all users.');
        $this->redirect(APP_URL . '/dashboard.php');
    }

    private function handleAnnouncementDeactivate()
    {
        if (!$this->hasPermission(['Super Admin'])) {
            $this->error('Only Super Admin can deactivate announcements.');
            $this->redirect(APP_URL . '/dashboard.php');
        }

        $announcementId = (int) ($_POST['announcement_id'] ?? 0);
        if ($announcementId <= 0) {
            $this->warning('Invalid announcement selected.');
            $this->redirect(APP_URL . '/dashboard.php');
        }

        $this->db->prepare('UPDATE announcements SET is_active = 0 WHERE id = :id');
        $this->db->bind(':id', $announcementId);
        $this->db->execute();

        $this->success('Announcement deactivated successfully.');
        $this->redirect(APP_URL . '/dashboard.php');
    }

    private function handleAnnouncementReactivate()
    {
        if (!$this->hasPermission(['Super Admin'])) {
            $this->error('Only Super Admin can reactivate announcements.');
            $this->redirect(APP_URL . '/dashboard.php');
        }

        $announcementId = (int) ($_POST['announcement_id'] ?? 0);
        if ($announcementId <= 0) {
            $this->warning('Invalid announcement selected.');
            $this->redirect(APP_URL . '/dashboard.php');
        }

        $this->db->prepare(
            "UPDATE announcements
             SET is_active = 1,
                 expires_at = CASE
                     WHEN expires_at IS NULL OR expires_at < NOW() THEN DATE_ADD(NOW(), INTERVAL 3 DAY)
                     ELSE expires_at
                 END
             WHERE id = :id"
        );
        $this->db->bind(':id', $announcementId);
        $this->db->execute();

        $this->success('Announcement reactivated successfully.');
        $this->redirect(APP_URL . '/dashboard.php');
    }

    private function deactivateExpiredAnnouncements()
    {
        $this->db->prepare('UPDATE announcements SET is_active = 0 WHERE is_active = 1 AND expires_at IS NOT NULL AND expires_at < NOW()');
        $this->db->execute();
    }

    private function getRecentAnnouncements($limit = 8)
    {
        $limit = max(1, (int) $limit);
        $this->db->prepare(
            "SELECT a.id, a.message, a.created_at, a.expires_at, u.full_name, u.role
             FROM announcements a
             JOIN users u ON a.created_by = u.id
             WHERE a.is_active = 1
               AND (a.expires_at IS NULL OR a.expires_at >= NOW())
             ORDER BY a.created_at DESC
             LIMIT {$limit}"
        );

        return $this->db->fetchAll();
    }

    private function getInactiveAnnouncements($limit = 8)
    {
        $limit = max(1, (int) $limit);
        $this->db->prepare(
            "SELECT a.id, a.message, a.created_at, a.expires_at, u.full_name, u.role
             FROM announcements a
             JOIN users u ON a.created_by = u.id
             WHERE a.is_active = 0
             ORDER BY a.created_at DESC
             LIMIT {$limit}"
        );

        return $this->db->fetchAll();
    }

    /**
     * Get dashboard statistics
     */
    private function getStats(array $inventoryStats = [], array $stationStats = [])
    {
        $stats = [];
        $permissions = $this->getDashboardPermissions();

        // Income stats
        if ($permissions['can_view_all_financials']) {
            $stats['income_today'] = $this->incomeModel->getTodayTotal();
            $stats['income_week'] = $this->incomeModel->getWeekTotal();
            $stats['income_month'] = $this->incomeModel->getMonthTotal();
            $stats['total_customers'] = $this->incomeModel->getTotalCustomers();
            $stats['subscription_users'] = $this->incomeModel->getActivePPPoEUsers();
            $stats['active_hotspot_users'] = $this->incomeModel->getActiveHotspotUsers();
        } else {
            $stats['income_today'] = 0;
            $stats['income_week'] = 0;
            $stats['income_month'] = 0;
            $stats['total_customers'] = 0;
            $stats['subscription_users'] = 0;
            $stats['active_hotspot_users'] = 0;
        }

        // Expense stats
        $stats['approved_expenses'] = $permissions['can_view_expense_financials'] ? $this->expenseModel->getTotalApproved() : 0;
        $stats['pending_requests'] = $permissions['can_view_expense_operations'] ? $this->getRelevantExpensePendingCount() : 0;
        $stats['pending_expenses_total'] = $permissions['can_view_expense_operations'] ? $this->getRelevantExpensePendingTotal() : 0;

        // Inventory stats
        $stats['low_stock'] = $permissions['can_view_inventory'] ? count($this->inventoryModel->getLowStock()) : 0;
        $stats['inventory_value'] = $permissions['can_view_inventory_value'] ? $this->inventoryModel->getTotalValue() : 0;
        $stats['inventory_items'] = $permissions['can_view_inventory'] ? (int) ($inventoryStats['total_items'] ?? 0) : 0;

        // Station stats
        $stationQueue = $permissions['can_view_stations'] ? $this->getRelevantStationCounts() : ['pending' => 0, 'active' => 0];
        $stats['pending_stations'] = (int) ($stationQueue['pending'] ?? 0);
        $stats['total_estimated_cost'] = $permissions['can_view_all_financials'] ? $this->stationModel->getTotalEstimatedCost() : 0;
        $stats['active_stations'] = (int) ($stationQueue['active'] ?? 0);
        $stats['pending_payroll_requests'] = $permissions['can_view_payroll'] ? $this->getPendingPayrollCount() : 0;

        // Net profit
        $stats['net_profit'] = $permissions['can_view_all_financials'] ? ($stats['income_month'] - $stats['approved_expenses']) : 0;

        return $stats;
    }

    private function getRelevantExpensePendingCount(): int
    {
        $userId = (int) ($this->user['id'] ?? 0);

        if ($this->userHasRole(['Super Admin'])) {
            return $this->expenseModel->getPendingCount();
        }

        $where = [];
        $bindings = [];

        if ($this->userHasRole(['Accountant'])) {
            $where[] = "status = 'Pending Accountant Processing'";
        }

        if ($this->userHasRole(['Director'])) {
            $where[] = "status = 'Pending Director Approval'";
        }

        if ($this->userHasRole(['Manager'])) {
            $where[] = "status = 'Pending Manager Approval'";
        }

        if ($this->userHasRole(['Sales', 'Technician']) || $where === []) {
            $where[] = "(requested_by = :user_id AND status NOT IN ('Completed', 'Rejected'))";
            $bindings[':user_id'] = $userId;
        }

        return $this->countRows(
            'SELECT COUNT(*) AS cnt FROM expense_requests WHERE ' . implode(' OR ', $where),
            $bindings
        );
    }

    private function getRelevantExpensePendingTotal(): float
    {
        $userId = (int) ($this->user['id'] ?? 0);

        if ($this->userHasRole(['Super Admin'])) {
            return $this->expenseModel->getTotalPending();
        }

        $where = [];

        if ($this->userHasRole(['Accountant'])) {
            $where[] = "status = 'Pending Accountant Processing'";
        }

        if ($this->userHasRole(['Director'])) {
            $where[] = "status = 'Pending Director Approval'";
        }

        if ($this->userHasRole(['Manager'])) {
            $where[] = "status = 'Pending Manager Approval'";
        }

        if ($this->userHasRole(['Sales', 'Technician']) || $where === []) {
            $where[] = "(requested_by = :user_id AND status NOT IN ('Completed', 'Rejected'))";
        }

        $this->db->prepare('SELECT COALESCE(SUM(amount_requested), 0) AS total FROM expense_requests WHERE ' . implode(' OR ', $where));
        if (str_contains(implode(' OR ', $where), ':user_id')) {
            $this->db->bind(':user_id', $userId);
        }

        return (float) (($this->db->fetch()['total'] ?? 0));
    }

    private function getRelevantStationCounts(): array
    {
        $userId = (int) ($this->user['id'] ?? 0);
        $table = $this->stationModel->getTable();
        $userBinding = [':user_id' => $userId];
        $userScope = $this->stationAssignedToExists()
            ? "(requested_by = :user_id OR assigned_to = :user_id)"
            : "requested_by = :user_id";

        if ($this->userHasRole(['Super Admin'])) {
            return [
                'pending' => $this->countRows("SELECT COUNT(*) AS cnt FROM {$table} WHERE status IN ('Pending Manager Approval', 'Pending Director Approval', 'Awaiting Accountant Approval')"),
                'active' => $this->countRows("SELECT COUNT(*) AS cnt FROM {$table} WHERE status NOT IN ('Completed', 'Rejected')"),
            ];
        }

        $pendingWhere = [];
        $pendingBindings = [];
        $activeWhere = [];
        $activeBindings = [];

        if ($this->userHasRole(['Accountant'])) {
            $pendingWhere[] = "status = 'Awaiting Accountant Approval'";
            $activeWhere[] = "status = 'Awaiting Accountant Approval'";
        }

        if ($this->userHasRole(['Director'])) {
            $pendingWhere[] = "status = 'Pending Director Approval'";
            $activeWhere[] = "status = 'Pending Director Approval'";
        }

        if ($this->userHasRole(['Manager'])) {
            $pendingWhere[] = "status = 'Pending Manager Approval'";
            $activeWhere[] = "status = 'Pending Manager Approval'";
        }

        if ($this->userHasRole(['Store Keeper'])) {
            $pendingWhere[] = "status = 'Pending Store Keeper Approval'";
            $activeWhere[] = "status = 'Pending Store Keeper Approval'";
        }

        if ($this->userHasRole(['Technician']) || $pendingWhere === []) {
            $pendingWhere[] = "({$userScope} AND status IN ('Pending Manager Approval', 'Pending Director Approval', 'Awaiting Accountant Approval', 'Pending Store Keeper Approval'))";
            $activeWhere[] = "({$userScope} AND status NOT IN ('Completed', 'Rejected'))";
            $pendingBindings = $userBinding;
            $activeBindings = $userBinding;
        }

        return [
            'pending' => $this->countRows(
                "SELECT COUNT(*) AS cnt FROM {$table} WHERE " . implode(' OR ', $pendingWhere),
                $pendingBindings
            ),
            'active' => $this->countRows(
                "SELECT COUNT(*) AS cnt FROM {$table} WHERE " . implode(' OR ', $activeWhere),
                $activeBindings
            ),
        ];
    }

    private function getDashboardPermissions(): array
    {
        return [
            'can_view_all_financials' => $this->hasPermission(['Accountant', 'Director', 'Super Admin']),
            'can_view_income' => $this->hasPermission(['Accountant', 'Director', 'Super Admin']),
            'can_view_own_income' => $this->hasPermission(['Sales']) && !$this->hasPermission(['Accountant', 'Director', 'Super Admin']),
            'can_view_expense_financials' => $this->hasPermission(['Accountant', 'Director', 'Super Admin']),
            'can_view_expense_operations' => $this->hasPermission(['Sales', 'Technician', 'Manager', 'Accountant', 'Director', 'Super Admin']),
            'can_view_inventory' => $this->hasPermission(['Store Keeper', 'Manager', 'Director', 'Super Admin']),
            'can_view_inventory_value' => $this->hasPermission(['Store Keeper', 'Manager', 'Director', 'Super Admin']),
            'can_view_station_charts' => $this->hasPermission(['Technician', 'Store Keeper', 'Manager', 'Accountant', 'Director', 'Super Admin']),
            'can_view_inventory_charts' => $this->hasPermission(['Store Keeper', 'Manager', 'Director', 'Super Admin']),
            'can_view_stations' => $this->hasPermission(['Technician', 'Store Keeper', 'Manager', 'Accountant', 'Director', 'Super Admin']),
            'can_view_payroll' => $this->hasPermission(['Accountant', 'Manager', 'Director', 'Super Admin']),
        ];
    }

    private function getPendingPayrollCount(): int
    {
        $this->db->prepare("SHOW TABLES LIKE 'salary_requests'");
        if (!$this->db->fetch()) {
            return 0;
        }

        $this->db->prepare("SELECT COUNT(*) AS cnt FROM salary_requests WHERE status NOT IN ('Paid', 'Rejected')");
        $row = $this->db->fetch();

        return (int) ($row['cnt'] ?? 0);
    }

    private function getRecentIncome(int $limit): array
    {
        if ($this->hasPermission(['Accountant', 'Director', 'Super Admin'])) {
            return $this->incomeModel->getRecent($limit);
        }

        if ($this->hasPermission(['Sales'])) {
            $this->db->prepare("SELECT * FROM {$this->incomeModel->getTable()} WHERE user_id = :user_id ORDER BY date DESC LIMIT {$limit}");
            $this->db->bind(':user_id', (int) $this->user['id']);
            return $this->db->fetchAll();
        }

        return [];
    }

    private function getRecentExpenses(int $limit): array
    {
        if ($this->hasPermission(['Manager', 'Accountant', 'Director', 'Super Admin'])) {
            return $this->expenseModel->getRecent($limit);
        }

        if ($this->hasPermission(['Sales', 'Technician'])) {
            $this->db->prepare("SELECT * FROM {$this->expenseModel->getTable()} WHERE requested_by = :user_id ORDER BY request_date DESC LIMIT {$limit}");
            $this->db->bind(':user_id', (int) $this->user['id']);
            return $this->db->fetchAll();
        }

        return [];
    }

    private function getRecentStations(int $limit): array
    {
        if ($this->hasPermission(['Manager', 'Accountant', 'Director', 'Super Admin'])) {
            return $this->stationModel->getRecent($limit);
        }

        if ($this->hasPermission(['Technician'])) {
            $table = $this->stationModel->getTable();
            if ($this->stationAssignedToExists()) {
                $this->db->prepare("SELECT * FROM {$table} WHERE requested_by = :user_id OR assigned_to = :user_id ORDER BY request_date DESC LIMIT {$limit}");
            } else {
                $this->db->prepare("SELECT * FROM {$table} WHERE requested_by = :user_id ORDER BY request_date DESC LIMIT {$limit}");
            }
            $this->db->bind(':user_id', (int) $this->user['id']);
            return $this->db->fetchAll();
        }

        return [];
    }

    private function getRecentActivities(int $limit = 8): array
    {
        if (!$this->hasPermission(['Super Admin', 'Director', 'Accountant', 'Manager'])) {
            return [];
        }

        $limit = max(1, (int) $limit);
        $this->db->prepare("SHOW TABLES LIKE 'activity_logs'");
        if (!$this->db->fetch()) {
            return [];
        }

        $this->db->prepare(
            "SELECT al.action, al.description, al.user_role, al.created_at, u.full_name
             FROM activity_logs al
             LEFT JOIN users u ON al.user_id = u.id
             ORDER BY al.created_at DESC
             LIMIT {$limit}"
        );

        return $this->db->fetchAll();
    }

    private function getReportLinks(): array
    {
        $links = [
            [
                'title' => 'Income Report',
                'description' => 'Monthly collections, service type breakdowns, and recorded customer payments.',
                'icon' => 'fa-sack-dollar',
                'href' => APP_URL . '/pages/reports.php?start_date=' . date('Y-m-01') . '&end_date=' . date('Y-m-d'),
            ],
            [
                'title' => 'Expense Report',
                'description' => 'Approval pipeline, processed requests, and paid expense values.',
                'icon' => 'fa-file-invoice-dollar',
                'href' => APP_URL . '/pages/reports.php?start_date=' . date('Y-m-01') . '&end_date=' . date('Y-m-d'),
            ],
            [
                'title' => 'Inventory Report',
                'description' => 'Stock value, quantities, and low stock items ready for export.',
                'icon' => 'fa-boxes-stacked',
                'href' => APP_URL . '/pages/reports.php?start_date=' . date('Y-m-01') . '&end_date=' . date('Y-m-d'),
            ],
        ];

        if ($this->hasPermission(['Accountant', 'Director', 'Super Admin'])) {
            $links[] = [
                'title' => 'Payroll Summary',
                'description' => 'Salary request approvals, paid requests, and payslip export workflows.',
                'icon' => 'fa-money-check-dollar',
                'href' => APP_URL . '/pages/payroll.php',
            ];
        }

        if ($this->hasPermission(['Super Admin'])) {
            $links[] = [
                'title' => 'Admin History',
                'description' => 'Review logged actions and operational activity across all users.',
                'icon' => 'fa-clock-rotate-left',
                'href' => APP_URL . '/pages/admin_history.php',
            ];
        }

        return $links;
    }

    private function stationAssignedToExists(): bool
    {
        if ($this->stationAssignedToExists !== null) {
            return $this->stationAssignedToExists;
        }

        $table = $this->stationModel->getTable();
        $this->db->prepare("SHOW COLUMNS FROM {$table} LIKE 'assigned_to'");
        $this->stationAssignedToExists = (bool) $this->db->fetch();

        return $this->stationAssignedToExists;
    }

    private function getRecentLowStock(): array
    {
        if (!$this->hasPermission(['Store Keeper', 'Manager', 'Director', 'Super Admin'])) {
            return [];
        }

        return $this->inventoryModel->getLowStock();
    }

    private function getRoleNotices(array $stats, array $permissions): array
    {
        $notices = [];
        $expenseTable = $this->expenseModel->getTable();
        $stationTable = $this->stationModel->getTable();
        $salaryTableExists = $this->tableExists('salary_requests');

        $pushNotice = static function (
            array &$items,
            string $key,
            string $tone,
            string $icon,
            string $title,
            string $message,
            string $meta,
            string $href
        ): void {
            if (isset($items[$key])) {
                return;
            }

            $items[$key] = [
                'tone' => $tone,
                'icon' => $icon,
                'title' => $title,
                'message' => $message,
                'meta' => $meta,
                'href' => $href,
            ];
        };

        if ($this->userHasRole(['Manager'])) {
            $expenseQueue = $this->countRows(
                "SELECT COUNT(*) AS cnt FROM {$expenseTable} WHERE status = :status",
                [':status' => 'Pending Manager Approval']
            );
            $stationQueue = $this->countRows(
                "SELECT COUNT(*) AS cnt FROM {$stationTable} WHERE status = :status",
                [':status' => 'Pending Manager Approval']
            );
            $payrollQueue = $salaryTableExists
                ? $this->countRows(
                    "SELECT COUNT(*) AS cnt FROM salary_requests WHERE status = :status",
                    [':status' => 'Pending Manager Approval']
                )
                : 0;
            $totalQueue = $expenseQueue + $stationQueue + $payrollQueue;

            if ($totalQueue > 0) {
                $pushNotice(
                    $notices,
                    'manager-queue',
                    'orange',
                    'fa-user-check',
                    'Manager approvals waiting',
                    "You have {$totalQueue} manager approvals waiting.",
                    'Expenses ' . $expenseQueue . ' • Stations ' . $stationQueue . ' • Payroll ' . $payrollQueue,
                    APP_URL . '/pages/view_expense_requests.php'
                );
            }
        }

        if ($this->userHasRole(['Director'])) {
            $expenseQueue = $this->countRows(
                "SELECT COUNT(*) AS cnt FROM {$expenseTable} WHERE status = :status",
                [':status' => 'Pending Director Approval']
            );
            $stationQueue = $this->countRows(
                "SELECT COUNT(*) AS cnt FROM {$stationTable} WHERE status = :status",
                [':status' => 'Pending Director Approval']
            );
            $payrollQueue = $salaryTableExists
                ? $this->countRows(
                    "SELECT COUNT(*) AS cnt FROM salary_requests WHERE status = :status",
                    [':status' => 'Pending Director Approval']
                )
                : 0;
            $totalQueue = $expenseQueue + $stationQueue + $payrollQueue;

            if ($totalQueue > 0) {
                $pushNotice(
                    $notices,
                    'director-queue',
                    'indigo',
                    'fa-user-tie',
                    'Director approvals waiting',
                    "You have {$totalQueue} director approvals waiting.",
                    'Expenses ' . $expenseQueue . ' • Stations ' . $stationQueue . ' • Payroll ' . $payrollQueue,
                    APP_URL . '/pages/stations.php#station-setup-requests'
                );
            }
        }

        if ($this->userHasRole(['Accountant'])) {
            $expenseQueue = $this->countRows(
                "SELECT COUNT(*) AS cnt FROM {$expenseTable} WHERE status = :status",
                [':status' => 'Pending Accountant Processing']
            );
            $stationQueue = $this->countRows(
                "SELECT COUNT(*) AS cnt FROM {$stationTable} WHERE status = :status",
                [':status' => 'Awaiting Accountant Approval']
            );
            $payrollQueue = $salaryTableExists
                ? $this->countRows(
                    "SELECT COUNT(*) AS cnt FROM salary_requests WHERE status = :status",
                    [':status' => 'Pending Accountant Final Approval']
                )
                : 0;
            $totalQueue = $expenseQueue + $stationQueue + $payrollQueue;

            if ($totalQueue > 0) {
                $pushNotice(
                    $notices,
                    'accountant-queue',
                    'cyan',
                    'fa-wallet',
                    'Accountant processing queue',
                    "You have {$totalQueue} accountant actions ready for processing.",
                    'Expenses ' . $expenseQueue . ' • Stations ' . $stationQueue . ' • Payroll ' . $payrollQueue,
                    APP_URL . '/pages/payroll.php'
                );
            }
        }

        if ($this->userHasRole(['Store Keeper'])) {
            $storeQueue = $this->countRows(
                "SELECT COUNT(*) AS cnt FROM {$stationTable} WHERE status = :status",
                [':status' => 'Pending Store Keeper Approval']
            );

            if ($storeQueue > 0) {
                $pushNotice(
                    $notices,
                    'store-queue',
                    'blue',
                    'fa-warehouse',
                    'Store release approvals pending',
                    "{$storeQueue} station issue requests are waiting for store release.",
                    'Open the station workflow and issue or skip requested inventory items.',
                    APP_URL . '/pages/stations.php#station-setup-requests'
                );
            }
        }

        if ($this->userHasRole(['Store Keeper', 'Manager', 'Director', 'Super Admin']) && (int) ($stats['low_stock'] ?? 0) > 0) {
            $lowStockCount = (int) ($stats['low_stock'] ?? 0);
            $pushNotice(
                $notices,
                'low-stock',
                'amber',
                'fa-triangle-exclamation',
                'Low stock needs attention',
                "{$lowStockCount} low stock items need restock now.",
                'Review depleted items before approvals or field work are delayed.',
                APP_URL . '/pages/low_stock_alerts.php'
            );
        }

        if ($this->userHasRole(['Sales', 'Technician'])) {
            $receiptQueue = $this->countRows(
                "SELECT COUNT(*) AS cnt FROM {$expenseTable} WHERE requested_by = :user_id AND status = :status",
                [
                    ':user_id' => (int) ($this->user['id'] ?? 0),
                    ':status' => 'Waiting for Receipt',
                ]
            );

            if ($receiptQueue > 0) {
                $pushNotice(
                    $notices,
                    'receipt-upload',
                    'rose',
                    'fa-file-arrow-up',
                    'Receipts still missing',
                    "{$receiptQueue} paid expense requests still need receipt upload.",
                    'Upload receipts quickly so accountant records stay complete.',
                    APP_URL . '/pages/view_expense_requests.php'
                );
            }
        }

        if ($this->userHasRole(['Technician'])) {
            if ($this->stationAssignedToExists()) {
                $installQueue = $this->countRows(
                    "SELECT COUNT(*) AS cnt
                     FROM {$stationTable}
                     WHERE (requested_by = :requested_user OR assigned_to = :assigned_user)
                       AND status IN ('Ready for Installation', 'Equipment Issued', 'Installation in Progress')",
                    [
                        ':requested_user' => (int) ($this->user['id'] ?? 0),
                        ':assigned_user' => (int) ($this->user['id'] ?? 0),
                    ]
                );
            } else {
                $installQueue = $this->countRows(
                    "SELECT COUNT(*) AS cnt
                     FROM {$stationTable}
                     WHERE requested_by = :user_id
                       AND status IN ('Ready for Installation', 'Equipment Issued', 'Installation in Progress')",
                    [':user_id' => (int) ($this->user['id'] ?? 0)]
                );
            }

            if ($installQueue > 0) {
                $pushNotice(
                    $notices,
                    'technician-install',
                    'indigo',
                    'fa-screwdriver-wrench',
                    'Station progress updates due',
                    "{$installQueue} station jobs need progress updates or completion.",
                    'Open active installs and keep workflow status current from the field.',
                    APP_URL . '/pages/stations.php#station-setup-requests'
                );
            }
        }

        if (empty($notices) && (
            !empty($permissions['can_view_expense_operations'])
            || !empty($permissions['can_view_stations'])
            || !empty($permissions['can_view_inventory'])
            || !empty($permissions['can_view_payroll'])
        )) {
            $pushNotice(
                $notices,
                'clear',
                'emerald',
                'fa-circle-check',
                'Queues are under control',
                'No urgent role-specific approvals are waiting right now.',
                'Use the quick actions below for routine follow-up and reporting.',
                APP_URL . '/dashboard.php'
            );
        }

        return array_values(array_slice($notices, 0, 4));
    }

    private function countRows(string $sql, array $bindings = []): int
    {
        $this->db->prepare($sql);
        foreach ($bindings as $parameter => $value) {
            $this->db->bind($parameter, $value);
        }

        $row = $this->db->fetch();

        return (int) ($row['cnt'] ?? 0);
    }

    private function tableExists(string $table): bool
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') {
            return false;
        }

        $this->db->prepare("SHOW TABLES LIKE '{$table}'");

        return (bool) $this->db->fetch();
    }

    private function userHasRole(array $roles): bool
    {
        $userRoles = array_map('strtolower', array_filter(array_map('trim', explode(',', (string) ($this->user['role'] ?? '')))));
        foreach ($roles as $role) {
            if (in_array(strtolower($role), $userRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get dashboard data via AJAX
     */
    public function getData()
    {
        if (!$this->isPost()) {
            $this->json(['error' => 'Invalid request method'], 400);
        }

        $permissions = $this->getDashboardPermissions();
        $stats = $this->getStats();
        $charts = $this->getChartData($permissions);
        $this->json([
            'success' => true,
            'stats' => $stats,
            'charts' => $charts,
        ]);
    }

    /**
     * Prepare datasets for charting
     *
     * @return array
     */
    private function getChartData(array $permissions)
    {
        $months = [];
        $incomeData = [];
        $expenseData = [];
        $customerGrowth = [];
        $inventoryUsage = [];
        $stationProgress = [];

        // last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = date('Y-m-01', strtotime("-$i months"));
            $monthLabel = date('M Y', strtotime($monthStart));
            $months[] = $monthLabel;

            $monthEnd = date('Y-m-t', strtotime($monthStart));

            // income and expenses
            $incomeData[] = $permissions['can_view_all_financials'] ? $this->incomeModel->getTotalByDateRange($monthStart, $monthEnd) : 0;
            $expenseData[] = $permissions['can_view_all_financials'] ? $this->expenseModel->getMonthTotal(date('Y-m', strtotime($monthStart))) : 0;

            // customer growth - distinct customers per month
            if ($permissions['can_view_all_financials']) {
                $this->db->prepare("SELECT COUNT(DISTINCT COALESCE(NULLIF(phone, ''), customer_name)) as cnt FROM {$this->incomeModel->getTable()} WHERE date BETWEEN :start AND :end");
                $this->db->bind(':start', $monthStart);
                $this->db->bind(':end', $monthEnd);
                $res = $this->db->fetch();
                $customerGrowth[] = $res['cnt'] ?? 0;
            } else {
                $customerGrowth[] = 0;
            }

            // inventory usage placeholder (could be number of issued items this month)
            if ($permissions['can_view_inventory_charts']) {
                $this->db->prepare("SELECT COUNT(*) as cnt FROM inventory WHERE COALESCE(is_deleted, 0) = 0 AND purchase_date BETWEEN :start AND :end");
                $this->db->bind(':start', $monthStart);
                $this->db->bind(':end', $monthEnd);
                $inv = $this->db->fetch();
                $inventoryUsage[] = $inv['cnt'] ?? 0;
            } else {
                $inventoryUsage[] = 0;
            }

            // station progress counts this month
            $statusCounts = [];
            if ($permissions['can_view_station_charts']) {
                $this->db->prepare("SELECT status, COUNT(*) as cnt FROM {$this->stationModel->getTable()} WHERE request_date BETWEEN :start AND :end GROUP BY status");
                $this->db->bind(':start', $monthStart);
                $this->db->bind(':end', $monthEnd);
                $stationRows = $this->db->fetchAll();
                foreach ($stationRows as $row) {
                    $statusCounts[$row['status']] = $row['cnt'];
                }
            }
            $stationProgress[] = $statusCounts;
        }

        return [
            'months' => $months,
            'income' => $incomeData,
            'expenses' => $expenseData,
            'customerGrowth' => $customerGrowth,
            'inventoryUsage' => $inventoryUsage,
            'stationProgress' => $stationProgress,
        ];
    }

    /**
     * Log activity
     */
    public function logAccess()
    {
        if ($this->user) {
            $this->logActivity('LOGIN', 'User logged in', 'users', $this->user['id']);
        }
    }
}
