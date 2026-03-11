<?php
/**
 * Income Model - Handles income/revenue operations
 */

namespace App\Models;

use App\Core\BaseModel;

class Income extends BaseModel
{
    protected $table = 'income';
    protected $primaryKey = 'id';
    protected $fillable = [
        'customer_name',
        'service_type',
        'description',
        'amount',
        'payment_method',
        'date',
        'reference_number',
        'recorded_by',
        'notes',
    ];
    protected $timestamps = true;

    /**
     * Get total income for date range
     *
     * @param string $startDate
     * @param string $endDate
     * @return float
     */
    public function getTotalByDateRange($startDate, $endDate)
    {
        $this->db->prepare("SELECT SUM(amount) as total FROM $this->table WHERE date BETWEEN :start AND :end");
        $this->db->bind(':start', $startDate);
        $this->db->bind(':end', $endDate);
        
        $result = $this->db->fetch();
        return $result['total'] ?? 0;
    }

    /**
     * Get income today
     *
     * @return float
     */
    public function getTodayTotal()
    {
        $today = date(DATE_FORMAT);
        return $this->getTotalByDateRange($today, $today);
    }

    /**
     * Get income this week
     *
     * @return float
     */
    public function getWeekTotal()
    {
        $weekStart = date(DATE_FORMAT, strtotime('monday this week'));
        $today = date(DATE_FORMAT);
        return $this->getTotalByDateRange($weekStart, $today);
    }

    /**
     * Get income this month
     *
     * @return float
     */
    public function getMonthTotal()
    {
        $monthStart = date('Y-m-01');
        $today = date(DATE_FORMAT);
        return $this->getTotalByDateRange($monthStart, $today);
    }

    /**
     * Get income by service type
     *
     * @return array
     */
    public function getByServiceType()
    {
        $this->db->prepare("SELECT service_type, SUM(amount) as total, COUNT(*) as count FROM $this->table GROUP BY service_type");
        return $this->db->fetchAll();
    }

    /**
     * Get recent income
     *
     * @param int $limit
     * @return array
     */
    public function getRecent($limit = 10)
    {
        return $this->getAll(['*'], [], 'date DESC', $limit);
    }

    /**
     * Search income
     *
     * @param string $query
     * @param array $filters
     * @return array
     */
    public function search($query, $filters = [])
    {
        $where = [];
        $whereClause = "WHERE (customer_name LIKE :query OR reference_number LIKE :query)";

        if (!empty($filters['start_date'])) {
            $whereClause .= " AND date >= :start_date";
            $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $whereClause .= " AND date <= :end_date";
        }

        if (!empty($filters['service_type'])) {
            $whereClause .= " AND service_type = :service_type";
        }

        $sqlQuery = "SELECT * FROM $this->table $whereClause ORDER BY date DESC";

        $this->db->prepare($sqlQuery);
        $this->db->bind(':query', "%$query%");

        if (!empty($filters['start_date'])) {
            $this->db->bind(':start_date', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $this->db->bind(':end_date', $filters['end_date']);
        }

        if (!empty($filters['service_type'])) {
            $this->db->bind(':service_type', $filters['service_type']);
        }

        return $this->db->fetchAll();
    }

    /**
     * Count distinct customers seen in income table
     *
     * @return int
     */
    public function getTotalCustomers()
    {
        $this->db->prepare("SELECT COUNT(DISTINCT customer_name) as cnt FROM $this->table");
        $result = $this->db->fetch();
        return $result['cnt'] ?? 0;
    }

    /**
     * Estimate active PPPoE users based on service_type 'Subscription'
     *
     * @return int
     */
    public function getActivePPPoEUsers()
    {
        $this->db->prepare("SELECT COUNT(*) as cnt FROM $this->table WHERE service_type = :type");
        $this->db->bind(':type', 'Subscription');
        $result = $this->db->fetch();
        return $result['cnt'] ?? 0;
    }

    /**
     * Estimate active Hotspot users based on service_type 'WiFi Voucher'
     *
     * @return int
     */
    public function getActiveHotspotUsers()
    {
        $this->db->prepare("SELECT COUNT(*) as cnt FROM $this->table WHERE service_type = :type");
        $this->db->bind(':type', 'WiFi Voucher');
        $result = $this->db->fetch();
        return $result['cnt'] ?? 0;
    }
}

