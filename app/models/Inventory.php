<?php
/**
 * Inventory Model - Handles inventory/equipment operations
 */

namespace App\Models;

use App\Core\BaseModel;

class Inventory extends BaseModel
{
    protected $table = 'inventory';
    protected $primaryKey = 'id';
    protected $fillable = [
        'item_name',
        'category',
        'quantity',
        'minimum_quantity',
        'unit_price',
        'description',
        'supplier',
        'date_added',
        'added_by',
    ];
    protected $timestamps = true;

    /**
     * Get low stock items
     *
     * @return array
     */
    public function getLowStock()
    {
        $this->db->prepare("SELECT * FROM $this->table WHERE quantity < minimum_quantity ORDER BY quantity ASC");
        return $this->db->fetchAll();
    }

    /**
     * Get low stock count
     *
     * @return int
     */
    public function getLowStockCount()
    {
        return $this->count();
    }

    /**
     * Get items by category
     *
     * @param string $category
     * @return array
     */
    public function getByCategory($category)
    {
        return $this->getAll(['*'], ['category' => $category], 'item_name ASC');
    }

    /**
     * Search inventory
     *
     * @param string $query
     * @return array
     */
    public function search($query)
    {
        $this->db->prepare("SELECT * FROM $this->table WHERE item_name LIKE :query OR category LIKE :query OR supplier LIKE :query ORDER BY item_name ASC");
        $this->db->bind(':query', "%$query%");
        return $this->db->fetchAll();
    }

    /**
     * Get total inventory value
     *
     * @return float
     */
    public function getTotalValue()
    {
        $this->db->prepare("SELECT SUM(quantity * unit_price) as total FROM $this->table");
        $result = $this->db->fetch();
        return $result['total'] ?? 0;
    }

    /**
     * Update stock quantity
     *
     * @param int $id
     * @param int $quantity
     * @return bool
     */
    public function updateQuantity($id, $quantity)
    {
        return $this->update($id, ['quantity' => $quantity]);
    }

    /**
     * Issue item from inventory
     *
     * @param int $id
     * @param int $quantity
     * @return bool
     */
    public function issueItem($id, $quantity)
    {
        $item = $this->find($id);
        
        if (!$item || $item['quantity'] < $quantity) {
            $this->addError('quantity', 'Insufficient stock');
            return false;
        }

        $newQuantity = $item['quantity'] - $quantity;
        return $this->updateQuantity($id, $newQuantity);
    }

    /**
     * Add to stock
     *
     * @param int $id
     * @param int $quantity
     * @return bool
     */
    public function addStock($id, $quantity)
    {
        $item = $this->find($id);
        
        if (!$item) {
            return false;
        }

        $newQuantity = $item['quantity'] + $quantity;
        return $this->updateQuantity($id, $newQuantity);
    }

    /**
     * Get items needing reorder
     *
     * @return array
     */
    public function getReorderNeeded()
    {
        $this->db->prepare("SELECT * FROM $this->table WHERE quantity <= minimum_quantity ORDER BY quantity ASC");
        return $this->db->fetchAll();
    }

    /**
     * Get inventory statistics
     *
     * @return array
     */
    public function getStatistics()
    {
        $stats = [];

        // Total items
        $this->db->prepare("SELECT COUNT(*) as count FROM $this->table");
        $stats['total_items'] = $this->db->fetch()['count'] ?? 0;

        // Low stock items
        $this->db->prepare("SELECT COUNT(*) as count FROM $this->table WHERE quantity < minimum_quantity");
        $stats['low_stock_items'] = $this->db->fetch()['count'] ?? 0;

        // Total value
        $stats['total_value'] = $this->getTotalValue();

        // Items by category
        $this->db->prepare("SELECT category, COUNT(*) as count FROM $this->table GROUP BY category");
        $stats['by_category'] = $this->db->fetchAll();

        return $stats;
    }
}
