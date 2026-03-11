<?php
/**
 * ExpenseController - Handle expense request operations
 */

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\ExpenseRequest;
use App\Models\User;
use App\Helpers\FileHelper;
use App\Helpers\ValidationHelper;

class ExpenseController extends BaseController
{
    private $expenseModel;
    private $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->expenseModel = new ExpenseRequest();
        $this->userModel = new User();
    }

    /**
     * Display expense requests
     */
    public function index()
    {
        $status = $this->query('status') ?? 'all';
        $page = intval($this->query('page') ?? 1);

        $message = $this->getMessage();

        $where = [];
        if ($status !== 'all') {
            $where['status'] = ucfirst($status);
        }

        $paginated = $this->expenseModel->paginate($page, ITEMS_PER_PAGE, $where, 'request_date DESC');
        $expense = $paginated['items'];

        $this->data = [
            'user' => $this->user,
            'expense' => $expense,
            'message' => $message,
            'status' => $status,
            'page' => $page,
            'pagination' => $paginated,
        ];

        $this->render('expenses/index', $this->data);
    }

    /**
     * Display expense detail
     */
    public function show()
    {
        $id = intval($this->query('id'));

        if (!$id) {
            $this->error('Invalid expense ID');
            $this->redirect(APP_URL . '/expenses.php');
        }

        $expense = $this->expenseModel->find($id);

        if (!$expense) {
            $this->error('Expense record not found');
            $this->redirect(APP_URL . '/expenses.php');
        }

        $requester = $this->userModel->find($expense['requested_by']);
        $reviewer = $expense['approved_by'] ? $this->userModel->find($expense['approved_by']) : null;

        $this->data = [
            'user' => $this->user,
            'expense' => $expense,
            'requester' => $requester,
            'reviewer' => $reviewer,
        ];

        $this->render('expenses/show', $this->data);
    }

    /**
     * Display request expense form
     */
    public function request()
    {
        if (!$this->hasPermission(CAN_REQUEST_EXPENSE)) {
            $this->error('You do not have permission to request expenses');
            $this->redirect(APP_URL . '/expenses.php');
        }

        $this->data = [
            'user' => $this->user,
            'csrf_token' => $this->getCsrfToken(),
        ];

        $this->render('expenses/request', $this->data);
    }

    /**
     * Handle request expense submission
     */
    public function requestStore()
    {
        if (!$this->isPost()) {
            $this->redirect(APP_URL . '/expenses/request.php');
        }

        if (!$this->hasPermission(CAN_REQUEST_EXPENSE)) {
            $this->error('You do not have permission');
            $this->redirect(APP_URL . '/expenses.php');
        }

        // Verify CSRF token
        if (!$this->verifyCsrfToken($this->post('csrf_token'))) {
            $this->error('Invalid security token');
            $this->redirect(APP_URL . '/expenses/request.php');
        }

        // Validate
        $required = ['description', 'category', 'amount_requested'];
        if (!$this->validateRequired($required, $_POST)) {
            $this->error('All required fields must be filled');
            $this->redirect(APP_URL . '/expenses/request.php');
        }

        // Validate amount
        $amount = floatval($this->post('amount_requested'));
        if (!ValidationHelper::numeric($amount, 0)) {
            $this->error('Amount must be a positive number');
            $this->redirect(APP_URL . '/expenses/request.php');
        }

        // Handle file upload
        $attachment = null;
        if (!empty($_FILES['attachment']['tmp_name'])) {
            $upload = FileHelper::upload($_FILES['attachment']);
            if (isset($upload['error'])) {
                $this->error($upload['error']);
                $this->redirect(APP_URL . '/expenses/request.php');
            }
            $attachment = $upload['success'];
        }

        // Create expense request
        $id = $this->expenseModel->create([
            'requested_by' => $this->user['id'],
            'description' => $this->sanitize($this->post('description')),
            'category' => $this->sanitize($this->post('category')),
            'amount_requested' => $amount,
            'justification' => $this->sanitize($this->post('justification') ?? ''),
            'attachment' => $attachment,
            'request_date' => date(DATETIME_FORMAT),
            'status' => EXPENSE_STATUS_PENDING,
        ]);

        if ($id) {
            $this->logActivity('REQUEST', 'Requested expense', 'expense_requests', $id);
            $this->success('Expense request submitted successfully');
            $this->redirect(APP_URL . '/expenses.php?id=' . $id);
        }

        $this->error('Failed to submit expense request');
        $this->redirect(APP_URL . '/expenses/request.php');
    }

    /**
     * Approve expense (for managers/directors)
     */
    public function approve()
    {
        if (!$this->isPost()) {
            $this->redirect(APP_URL . '/expenses.php');
        }

        if (!$this->hasPermission(CAN_APPROVE_EXPENSES)) {
            $this->error('You do not have permission to approve expenses');
            $this->redirect(APP_URL . '/expenses.php');
        }

        $id = intval($this->post('id'));
        $approvedAmount = floatval($this->post('approved_amount') ?? 0);

        if (!$id) {
            $this->error('Invalid expense ID');
            $this->redirect(APP_URL . '/expenses.php');
        }

        if ($this->expenseModel->approve($id, $this->user['id'], $approvedAmount)) {
            $this->logActivity('APPROVE', 'Approved expense', 'expense_requests', $id);
            $this->success('Expense approved successfully');
            $this->redirect(APP_URL . '/expenses.php?id=' . $id);
        }

        $this->error('Failed to approve expense');
        $this->redirect(APP_URL . '/expenses.php?id=' . $id);
    }

    /**
     * Reject expense
     */
    public function reject()
    {
        if (!$this->isPost()) {
            $this->redirect(APP_URL . '/expenses.php');
        }

        if (!$this->hasPermission(CAN_APPROVE_EXPENSES)) {
            $this->error('You do not have permission to reject expenses');
            $this->redirect(APP_URL . '/expenses.php');
        }

        $id = intval($this->post('id'));
        $reason = $this->sanitize($this->post('reason') ?? '');

        if (!$id) {
            $this->error('Invalid expense ID');
            $this->redirect(APP_URL . '/expenses.php');
        }

        if ($this->expenseModel->reject($id, $reason)) {
            $this->logActivity('REJECT', 'Rejected expense', 'expense_requests', $id);
            $this->success('Expense rejected successfully');
            $this->redirect(APP_URL . '/expenses.php?id=' . $id);
        }

        $this->error('Failed to reject expense');
        $this->redirect(APP_URL . '/expenses.php?id=' . $id);
    }

    /**
     * Mark as completed
     */
    public function complete()
    {
        if (!$this->isPost()) {
            $this->redirect(APP_URL . '/expenses.php');
        }

        if (!$this->hasPermission(['Super Admin', 'Accountant'])) {
            $this->error('You do not have permission');
            $this->redirect(APP_URL . '/expenses.php');
        }

        $id = intval($this->post('id'));

        if (!$id) {
            $this->error('Invalid expense ID');
            $this->redirect(APP_URL . '/expenses.php');
        }

        if ($this->expenseModel->markCompleted($id, $this->user['id'])) {
            $this->logActivity('COMPLETE', 'Marked expense as completed', 'expense_requests', $id);
            $this->success('Expense marked as completed');
            $this->redirect(APP_URL . '/expenses.php?id=' . $id);
        }

        $this->error('Failed to mark expense as completed');
        $this->redirect(APP_URL . '/expenses.php?id=' . $id);
    }

    /**
     * Get expense statistics
     */
    public function stats()
    {
        $stats = [
            'pending' => $this->expenseModel->getPendingCount(),
            'total_pending' => $this->expenseModel->getTotalPending(),
            'total_approved' => $this->expenseModel->getTotalApproved(),
            'by_category' => $this->expenseModel->getByCategory(),
        ];

        $this->json(['success' => true, 'data' => $stats]);
    }
}
