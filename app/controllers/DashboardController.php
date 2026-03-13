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

        try {
            $this->data = [
                'user' => $this->user,
                'stats' => $this->getStats(),
                'message' => $message,
                'recent_income' => $this->incomeModel->getRecent(5),
                'recent_expenses' => $this->expenseModel->getRecent(5),
                'recent_stations' => $this->stationModel->getRecent(5),
                'low_stock_items' => $this->inventoryModel->getLowStock(),
                'chart_data' => $this->getChartData(),
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
                'chart_data' => [
                    'months' => [],
                    'income' => [],
                    'expenses' => [],
                    'customerGrowth' => [],
                    'inventoryUsage' => [],
                    'stationProgress' => [],
                ],
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
    private function getStats()
    {
        $stats = [];

        // Income stats
        $stats['income_today'] = $this->incomeModel->getTodayTotal();
        $stats['income_week'] = $this->incomeModel->getWeekTotal();
        $stats['income_month'] = $this->incomeModel->getMonthTotal();

        // customer counts inferred from income records
        $stats['total_customers'] = $this->incomeModel->getTotalCustomers();
        $stats['active_pppoe_users'] = $this->incomeModel->getActivePPPoEUsers();
        $stats['active_hotspot_users'] = $this->incomeModel->getActiveHotspotUsers();

        // Expense stats
        $stats['approved_expenses'] = $this->expenseModel->getTotalApproved();
        $stats['pending_requests'] = $this->expenseModel->getPendingCount();
        $stats['pending_expenses_total'] = $this->expenseModel->getTotalPending();

        // Inventory stats
        $stats['low_stock'] = count($this->inventoryModel->getLowStock());
        $stats['inventory_value'] = $this->inventoryModel->getTotalValue();

        // Station stats
        $stats['pending_stations'] = $this->stationModel->getPendingCount();
        $stats['total_estimated_cost'] = $this->stationModel->getTotalEstimatedCost();

        // Net profit
        $stats['net_profit'] = $stats['income_month'] - $stats['approved_expenses'];

        return $stats;
    }

    /**
     * Get dashboard data via AJAX
     */
    public function getData()
    {
        if (!$this->isPost()) {
            $this->json(['error' => 'Invalid request method'], 400);
        }

        $stats = $this->getStats();
        $charts = $this->getChartData();
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
    private function getChartData()
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
            $incomeData[] = $this->incomeModel->getTotalByDateRange($monthStart, $monthEnd);
            $expenseData[] = $this->expenseModel->getTotalByDateRange($monthStart, $monthEnd);

            // customer growth - distinct customers per month
            $this->db->prepare("SELECT COUNT(DISTINCT customer_name) as cnt FROM {$this->incomeModel->getTable()} WHERE date BETWEEN :start AND :end");
            $this->db->bind(':start', $monthStart);
            $this->db->bind(':end', $monthEnd);
            $res = $this->db->fetch();
            $customerGrowth[] = $res['cnt'] ?? 0;

            // inventory usage placeholder (could be number of issued items this month)
            $this->db->prepare("SELECT COUNT(*) as cnt FROM inventory WHERE purchase_date BETWEEN :start AND :end");
            $this->db->bind(':start', $monthStart);
            $this->db->bind(':end', $monthEnd);
            $inv = $this->db->fetch();
            $inventoryUsage[] = $inv['cnt'] ?? 0;

            // station progress counts this month
            $this->db->prepare("SELECT status, COUNT(*) as cnt FROM {$this->stationModel->getTable()} WHERE request_date BETWEEN :start AND :end GROUP BY status");
            $this->db->bind(':start', $monthStart);
            $this->db->bind(':end', $monthEnd);
            $stationRows = $this->db->fetchAll();
            // convert to associative array of status counts
            $statusCounts = [];
            foreach ($stationRows as $row) {
                $statusCounts[$row['status']] = $row['cnt'];
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
