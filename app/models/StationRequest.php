<?php
/**
 * StationRequest Model - Handles station setup request operations
 */

namespace App\Models;

use App\Core\BaseModel;

class StationRequest extends BaseModel
{
    protected $table = 'station_requests';
    protected $primaryKey = 'id';
    protected $fillable = [
        'station_name',
        'location',
        'latitude',
        'longitude',
        'description',
        'requested_by',
        'request_date',
        'estimated_cost',
        'status',
        'assigned_to',
        'approved_by',
        'approval_date',
        'completion_date',
        'notes',
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
            ['status' => STATION_STATUS_PENDING],
            'request_date DESC'
        );
    }

    /**
     * Get in-progress requests
     *
     * @return array
     */
    public function getInProgress()
    {
        return $this->getAll(
            ['*'],
            ['status' => STATION_STATUS_IN_PROGRESS],
            'request_date DESC'
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
            ['status' => STATION_STATUS_COMPLETED],
            'completion_date DESC'
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
     * Get requests assigned to technician
     *
     * @param int $technicianId
     * @return array
     */
    public function getAssignedTo($technicianId)
    {
        return $this->getAll(
            ['*'],
            ['assigned_to' => $technicianId],
            'request_date DESC'
        );
    }

    /**
     * Get pending count
     *
     * @return int
     */
    public function getPendingCount()
    {
        return $this->count(['status' => STATION_STATUS_PENDING]);
    }

    /**
     * Get total estimated cost
     *
     * @return float
     */
    public function getTotalEstimatedCost()
    {
        $this->db->prepare("SELECT SUM(estimated_cost) as total FROM $this->table WHERE status != :status");
        $this->db->bind(':status', STATION_STATUS_COMPLETED);
        
        $result = $this->db->fetch();
        return $result['total'] ?? 0;
    }

    /**
     * Get recent requests
     *
     * @param int $limit
     * @return array
     */
    public function getRecent($limit = 10)
    {
        return $this->getAll(['*'], [], 'request_date DESC', $limit);
    }

    /**
     * Approve request
     *
     * @param int $id
     * @param int $approvedBy
     * @param int|null $assignedTo
     * @return bool
     */
    public function approve($id, $approvedBy, $assignedTo = null)
    {
        $data = [
            'status' => STATION_STATUS_APPROVED,
            'approved_by' => $approvedBy,
            'approval_date' => date(DATETIME_FORMAT),
        ];

        if ($assignedTo) {
            $data['assigned_to'] = $assignedTo;
        }

        return $this->update($id, $data);
    }

    /**
     * Assign to technician
     *
     * @param int $id
     * @param int $technicianId
     * @return bool
     */
    public function assignTo($id, $technicianId)
    {
        $data = [
            'assigned_to' => $technicianId,
            'status' => STATION_STATUS_IN_PROGRESS,
        ];

        return $this->update($id, $data);
    }

    /**
     * Mark as completed
     *
     * @param int $id
     * @param string|null $notes
     * @return bool
     */
    public function markCompleted($id, $notes = null)
    {
        $data = [
            'status' => STATION_STATUS_COMPLETED,
            'completion_date' => date(DATETIME_FORMAT),
        ];

        if ($notes) {
            $data['notes'] = $notes;
        }

        return $this->update($id, $data);
    }

    /**
     * Reject request
     *
     * @param int $id
     * @param string $reason
     * @return bool
     */
    public function reject($id, $reason)
    {
        $data = [
            'status' => STATION_STATUS_REJECTED,
            'notes' => $reason,
        ];

        return $this->update($id, $data);
    }

    /**
     * Search stations
     *
     * @param string $query
     * @return array
     */
    public function search($query)
    {
        $this->db->prepare("SELECT * FROM $this->table WHERE station_name LIKE :query OR location LIKE :query ORDER BY request_date DESC");
        $this->db->bind(':query', "%$query%");
        return $this->db->fetchAll();
    }

    /**
     * Get statistics
     *
     * @return array
     */
    public function getStatistics()
    {
        $stats = [];

        // Total requests
        $this->db->prepare("SELECT COUNT(*) as count FROM $this->table");
        $stats['total_requests'] = $this->db->fetch()['count'] ?? 0;

        // Pending
        $stats['pending'] = $this->count(['status' => STATION_STATUS_PENDING]);

        // In progress
        $stats['in_progress'] = $this->count(['status' => STATION_STATUS_IN_PROGRESS]);

        // Completed
        $stats['completed'] = $this->count(['status' => STATION_STATUS_COMPLETED]);

        // Total cost
        $stats['total_estimated_cost'] = $this->getTotalEstimatedCost();

        return $stats;
    }
}
