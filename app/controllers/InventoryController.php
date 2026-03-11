<?php
/**
 * InventoryController - Handle inventory operations
 */

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Inventory;
use App\Helpers\ValidationHelper;

class InventoryController extends BaseController
{
    private $inventoryModel;

    public function __construct()
    {
        parent::__construct();
        $this->inventoryModel = new Inventory();
    }

    /**
     * Display inventory list
     */
    public function index()
    {
        $category = $this->query('category');
        $search = $this->query('search');
        $page = intval($this->query('page') ?? 1);

        $message = $this->getMessage();
        $items = [];

        if ($search) {
            $items = $this->inventoryModel->search($search);
        } elseif ($category) {
            $items = $this->inventoryModel->getByCategory($category);
        } else {
            $paginated = $this->inventoryModel->paginate($page, ITEMS_PER_PAGE, [], 'item_name ASC');
            $items = $paginated['items'];
        }

        // Get stats
        $stats = $this->inventoryModel->getStatistics();

        $this->data = [
            'user' => $this->user,
            'items' => $items,
            'message' => $message,
            'stats' => $stats,
            'category' => $category,
            'search' => $search,
        ];

        $this->render('inventory/index', $this->data);
    }

    /**
     * Display item detail
     */
    public function show()
    {
        $id = intval($this->query('id'));

        if (!$id) {
            $this->error('Invalid item ID');
            $this->redirect(APP_URL . '/inventory.php');
        }

        $item = $this->inventoryModel->find($id);

        if (!$item) {
            $this->error('Item not found');
            $this->redirect(APP_URL . '/inventory.php');
        }

        $this->data = [
            'user' => $this->user,
            'item' => $item,
        ];

        $this->render('inventory/show', $this->data);
    }

    /**
     * Display add item form
     */
    public function create()
    {
        if (!$this->hasPermission(CAN_MANAGE_INVENTORY)) {
            $this->error('You do not have permission to manage inventory');
            $this->redirect(APP_URL . '/inventory.php');
        }

        $this->data = [
            'user' => $this->user,
            'csrf_token' => $this->getCsrfToken(),
        ];

        $this->render('inventory/create', $this->data);
    }

    /**
     * Handle add item submission
     */
    public function store()
    {
        if (!$this->isPost()) {
            $this->redirect(APP_URL . '/inventory/create.php');
        }

        if (!$this->hasPermission(CAN_MANAGE_INVENTORY)) {
            $this->error('You do not have permission');
            $this->redirect(APP_URL . '/inventory.php');
        }

        // Verify CSRF token
        if (!$this->verifyCsrfToken($this->post('csrf_token'))) {
            $this->error('Invalid security token');
            $this->redirect(APP_URL . '/inventory/create.php');
        }

        // Validate
        $required = ['item_name', 'category', 'quantity', 'minimum_quantity', 'unit_price'];
        if (!$this->validateRequired($required, $_POST)) {
            $this->error('All required fields must be filled');
            $this->redirect(APP_URL . '/inventory/create.php');
        }

        // Validate numbers
        $quantity = intval($this->post('quantity'));
        $minQuantity = intval($this->post('minimum_quantity'));
        $unitPrice = floatval($this->post('unit_price'));

        if (!ValidationHelper::integer($quantity, 0)) {
            $this->error('Quantity must be a non-negative integer');
            $this->redirect(APP_URL . '/inventory/create.php');
        }

        if (!ValidationHelper::integer($minQuantity, 0)) {
            $this->error('Minimum quantity must be a non-negative integer');
            $this->redirect(APP_URL . '/inventory/create.php');
        }

        if (!ValidationHelper::numeric($unitPrice, 0)) {
            $this->error('Unit price must be a positive number');
            $this->redirect(APP_URL . '/inventory/create.php');
        }

        // Create item
        $id = $this->inventoryModel->create([
            'item_name' => $this->sanitize($this->post('item_name')),
            'category' => $this->sanitize($this->post('category')),
            'quantity' => $quantity,
            'minimum_quantity' => $minQuantity,
            'unit_price' => $unitPrice,
            'description' => $this->sanitize($this->post('description') ?? ''),
            'supplier' => $this->sanitize($this->post('supplier') ?? ''),
            'date_added' => date(DATETIME_FORMAT),
            'added_by' => $this->user['id'],
        ]);

        if ($id) {
            $this->logActivity('CREATE', 'Added inventory item', 'inventory', $id);
            $this->success('Item added successfully');
            $this->redirect(APP_URL . '/inventory.php?id=' . $id);
        }

        $this->error('Failed to add item');
        $this->redirect(APP_URL . '/inventory/create.php');
    }

    /**
     * Display edit form
     */
    public function edit()
    {
        if (!$this->hasPermission(CAN_MANAGE_INVENTORY)) {
            $this->error('You do not have permission');
            $this->redirect(APP_URL . '/inventory.php');
        }

        $id = intval($this->query('id'));

        if (!$id) {
            $this->error('Invalid item ID');
            $this->redirect(APP_URL . '/inventory.php');
        }

        $item = $this->inventoryModel->find($id);

        if (!$item) {
            $this->error('Item not found');
            $this->redirect(APP_URL . '/inventory.php');
        }

        $this->data = [
            'user' => $this->user,
            'item' => $item,
            'csrf_token' => $this->getCsrfToken(),
        ];

        $this->render('inventory/edit', $this->data);
    }

    /**
     * Handle update submission
     */
    public function update()
    {
        if (!$this->isPost()) {
            $this->redirect(APP_URL . '/inventory.php');
        }

        if (!$this->hasPermission(CAN_MANAGE_INVENTORY)) {
            $this->error('You do not have permission');
            $this->redirect(APP_URL . '/inventory.php');
        }

        $id = intval($this->post('id'));

        if (!$id) {
            $this->error('Invalid item ID');
            $this->redirect(APP_URL . '/inventory.php');
        }

        // Verify CSRF
        if (!$this->verifyCsrfToken($this->post('csrf_token'))) {
            $this->error('Invalid security token');
            $this->redirect(APP_URL . '/inventory/edit.php?id=' . $id);
        }

        // Validate
        if ($this->inventoryModel->update($id, [
            'item_name' => $this->sanitize($this->post('item_name')),
            'quantity' => intval($this->post('quantity')),
            'minimum_quantity' => intval($this->post('minimum_quantity')),
            'unit_price' => floatval($this->post('unit_price')),
            'description' => $this->sanitize($this->post('description') ?? ''),
            'supplier' => $this->sanitize($this->post('supplier') ?? ''),
        ])) {
            $this->logActivity('UPDATE', 'Updated inventory item', 'inventory', $id);
            $this->success('Item updated successfully');
            $this->redirect(APP_URL . '/inventory.php?id=' . $id);
        }

        $this->error('Failed to update item');
        $this->redirect(APP_URL . '/inventory/edit.php?id=' . $id);
    }

    /**
     * Issue item from inventory
     */
    public function issue()
    {
        if (!$this->isPost()) {
            $this->redirect(APP_URL . '/inventory.php');
        }

        if (!$this->hasPermission(CAN_MANAGE_INVENTORY)) {
            $this->error('You do not have permission');
            $this->redirect(APP_URL . '/inventory.php');
        }

        $id = intval($this->post('id'));
        $quantity = intval($this->post('quantity'));

        if (!$id || !$quantity) {
            $this->error('Invalid request');
            $this->redirect(APP_URL . '/inventory.php');
        }

        if ($this->inventoryModel->issueItem($id, $quantity)) {
            $this->logActivity('ISSUE', 'Issued inventory item', 'inventory', $id);
            $this->success('Item issued successfully');
            $this->redirect(APP_URL . '/inventory.php?id=' . $id);
        }

        $this->error($this->inventoryModel->getErrors()['quantity'] ?? 'Failed to issue item');
        $this->redirect(APP_URL . '/inventory.php?id=' . $id);
    }

    /**
     * Add stock to inventory
     */
    public function addStock()
    {
        if (!$this->isPost()) {
            $this->redirect(APP_URL . '/inventory.php');
        }

        if (!$this->hasPermission(CAN_MANAGE_INVENTORY)) {
            $this->error('You do not have permission');
            $this->redirect(APP_URL . '/inventory.php');
        }

        $id = intval($this->post('id'));
        $quantity = intval($this->post('quantity'));

        if (!$id || !$quantity) {
            $this->error('Invalid request');
            $this->redirect(APP_URL . '/inventory.php');
        }

        if ($this->inventoryModel->addStock($id, $quantity)) {
            $this->logActivity('ADD_STOCK', 'Added stock to inventory', 'inventory', $id);
            $this->success('Stock added successfully');
            $this->redirect(APP_URL . '/inventory.php?id=' . $id);
        }

        $this->error('Failed to add stock');
        $this->redirect(APP_URL . '/inventory.php?id=' . $id);
    }

    /**
     * Get low stock items
     */
    public function lowStock()
    {
        $items = $this->inventoryModel->getLowStock();

        $this->data = [
            'user' => $this->user,
            'items' => $items,
        ];

        $this->render('inventory/low_stock', $this->data);
    }
}
