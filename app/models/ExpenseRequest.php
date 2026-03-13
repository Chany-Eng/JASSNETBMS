<?php
/**
 * ExpenseRequest Model - Handles expense request operations
 */

namespace App\Models;

use App\Core\BaseModel;

class ExpenseRequest extends BaseModel
{
    protected $table = 'expense_requests';
    protected $primaryKey = 'id';
    protected $fillable = [
        'requested_by',
        'description',
        'category',
        'amount_requested',
        'justification',
        'attachment',
        'request_date',
        'status',
        'approved_by',
        'approval_date',
        'approved_amount',
        'rejection_reason',
        'processed_date',
        'processed_by',
    ];
    protected $timestamps = true;

    /**
     * Get pending requests
     *
     * @return array
     */
    public function getPending()
    {
        return $this->getAll(
            ['*'],
            ['status' => EXPENSE_STATUS_PENDING],
            'request_date DESC'
        );
    }

    /**
     * Get approved requests
     *
     * @return array
     */
    public function getApproved()
    {
        return $this->getAll(
            ['*'],
            ['status' => EXPENSE_STATUS_APPROVED],
            'approval_date DESC'
        );
    }

    /**
     * Get completed requests
     *
     * @return array
     */
    public function getCompleted()
    {
        return $this->getAll(
            ['*'],
            ['status' => EXPENSE_STATUS_COMPLETED],
            'processed_date DESC'
        );
    }

    /**
     * Get requests for user
     *
     * @param int $userId
     * @return array
     */
    public function getByUser($userId)
    {
        return $this->getAll(['*'], ['requested_by' => $userId], 'request_date DESC');
    }

    /**
     * Get pending count for stats
     *
     * @return int
     */
    public function getPendingCount()
    {
        $this->db->prepare("SELECT COUNT(*) as count FROM $this->table WHERE status NOT IN ('Completed', 'Rejected')");
        $result = $this->db->fetch();
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Get total by status
     *
     * @param string $status
     * @return float
     */
    public function getTotalByStatus($status)
    {
        $this->db->prepare("SELECT SUM(amount_requested) as total FROM $this->table WHERE status = :status");
        $this->db->bind(':status', $status);
        
        $result = $this->db->fetch();
        return $result['total'] ?? 0;
    }

    /**
     * Get total approved expenses
     *
     * @return float
     */
    public function getTotalApproved()
    {
        $this->db->prepare('SELECT COALESCE(SUM(amount_paid), 0) as total FROM expense_payments');
        $result = $this->db->fetch();
        return (float) ($result['total'] ?? 0);
    }

    /**
     * Get total pending
     *
     * @return float
     */
    public function getTotalPending()
    {
        $this->db->prepare("SELECT COALESCE(SUM(amount_requested), 0) as total FROM $this->table WHERE status NOT IN ('Completed', 'Rejected')");
        $result = $this->db->fetch();
        return (float) ($result['total'] ?? 0);
    }

    /**
     * Get total amount of requests within a date range (regardless of status)
     *
     * @param string $startDate
     * @param string $endDate
     * @return float
     */
    public function getTotalByDateRange($startDate, $endDate)
    {
        $this->db->prepare("SELECT SUM(amount_requested) as total FROM $this->table WHERE request_date BETWEEN :start AND :end");
        $this->db->bind(':start', $startDate);
        $this->db->bind(':end', $endDate);
        $result = $this->db->fetch();
        return $result['total'] ?? 0;
    }

    /**
     * Get expenses by category
     *
     * @return array
     */
    public function getByCategory()
    {
        $this->db->prepare("SELECT category, SUM(amount_requested) as total, COUNT(*) as count FROM $this->table WHERE status = :status GROUP BY category");
        $this->db->bind(':status', EXPENSE_STATUS_COMPLETED);
        return $this->db->fetchAll();
    }

    /**
     * Get recent expenses
     *
     * @param int $limit
     * @return array
     */
    public function getRecent($limit = 10)
    {
        return $this->getAll(['*'], [], 'request_date DESC', $limit);
    }

    /**
     * Approve expense request
     *
     * @param int $id
     * @param int $approvedBy
     * @param float|null $approvedAmount
     * @return bool
     */
    public function approve($id, $approvedBy, $approvedAmount = null)
    {
        $data = [
            'status' => EXPENSE_STATUS_APPROVED,
            'approved_by' => $approvedBy,
            'approval_date' => date(DATETIME_FORMAT),
            'approved_amount' => $approvedAmount,
        ];

        return $this->update($id, $data);
    }

    /**
     * Reject expense request
     *
     * @param int $id
     * @param string $reason
     * @return bool
     */
    public function reject($id, $reason)
    {
        $data = [
            'status' => EXPENSE_STATUS_REJECTED,
            'rejection_reason' => $reason,
            'approval_date' => date(DATETIME_FORMAT),
        ];

        return $this->update($id, $data);
    }

    /**
     * Mark as completed
     *
     * @param int $id
     * @param int $processedBy
     * @return bool
     */
    public function markCompleted($id, $processedBy)
    {
        $data = [
            'status' => EXPENSE_STATUS_COMPLETED,
            'processed_by' => $processedBy,
            'processed_date' => date(DATETIME_FORMAT),
        ];

        return $this->update($id, $data);
    }

    /**
     * Get total expenses for month
     *
     * @param string $month Format: Y-m
     * @return float
     */
    public function getMonthTotal($month)
    {
        $this->db->prepare("SELECT COALESCE(SUM(ep.amount_paid), 0) as total FROM expense_payments ep JOIN expense_requests er ON er.id = ep.expense_request_id WHERE DATE_FORMAT(ep.payment_date, '%Y-%m') = :month");
        $this->db->bind(':month', $month);
        
        $result = $this->db->fetch();
        return $result['total'] ?? 0;
    }
}
