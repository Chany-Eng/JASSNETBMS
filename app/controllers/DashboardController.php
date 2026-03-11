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
        $stats = $this->getStats();
        $message = $this->getMessage();

        $this->data = [
            'user' => $this->user,
            'stats' => $stats,
            'message' => $message,
            'recent_income' => $this->incomeModel->getRecent(5),
            'recent_expenses' => $this->expenseModel->getRecent(5),
            'recent_stations' => $this->stationModel->getRecent(5),
            'low_stock_items' => $this->inventoryModel->getLowStock(),
        ];

        $this->render('dashboard/index', $this->data);
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
