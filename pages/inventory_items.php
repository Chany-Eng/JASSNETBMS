<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

requirePermission(['Store Keeper', 'Manager', 'Super Admin', 'Technician']);

$canUpdateStock = hasPermission(['Store Keeper', 'Manager', 'Super Admin']);
$canForceArchiveInventory = hasPermission(['Super Admin']);
ensureInventorySoftDeleteSchema($conn);

function inventoryLinkedUsageSummary(mysqli $conn, int $itemId): array
{
    $usage = [
        'equipment_requests' => 0,
        'station_equipment' => 0,
        'total' => 0,
    ];

    $equipmentStmt = $conn->prepare('SELECT COUNT(*) AS total FROM equipment_requests WHERE item_id = ?');
    if ($equipmentStmt) {
        $equipmentStmt->bind_param('i', $itemId);
        $equipmentStmt->execute();
        $row = $equipmentStmt->get_result()->fetch_assoc();
        $usage['equipment_requests'] = (int) ($row['total'] ?? 0);
    }

    $stationTableExists = $conn->query("SHOW TABLES LIKE 'station_equipment'");
    if ($stationTableExists instanceof mysqli_result && $stationTableExists->num_rows > 0) {
        $stationStmt = $conn->prepare('SELECT COUNT(*) AS total FROM station_equipment WHERE inventory_id = ?');
        if ($stationStmt) {
            $stationStmt->bind_param('i', $itemId);
            $stationStmt->execute();
            $row = $stationStmt->get_result()->fetch_assoc();
            $usage['station_equipment'] = (int) ($row['total'] ?? 0);
        }
    }

    $usage['total'] = $usage['equipment_requests'] + $usage['station_equipment'];
    return $usage;
}

function inventorySoftDeleteLabel(array $usage, bool $canForceArchive = false): string
{
    if (($usage['total'] ?? 0) <= 0) {
        return 'Delete';
    }

    return $canForceArchive ? 'Archive' : 'In Use';
}

function inventoryExportExcel(mysqli $conn): void
{
    ensureInventorySoftDeleteSchema($conn);
    $result = $conn->query('SELECT * FROM inventory WHERE COALESCE(is_deleted, 0) = 0 ORDER BY item_name');

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="inventory_items_' . date('Ymd_His') . '.xls"');

    echo '<table border="1">';
    echo '<tr><th>#</th><th>Item Name</th><th>Category</th><th>Quantity</th><th>Purchase Price (Tshs.)</th><th>Selling Price (Tshs.)</th><th>Supplier</th><th>Purchase Date</th><th>Status</th><th>Notes</th></tr>';

    $index = 1;
    if ($result) {
        while ($item = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . $index++ . '</td>';
            echo '<td>' . htmlspecialchars($item['item_name']) . '</td>';
            echo '<td>' . htmlspecialchars($item['category']) . '</td>';
            echo '<td>' . (int) $item['quantity'] . '</td>';
            echo '<td>' . number_format((float) $item['purchase_price'], 2) . '</td>';
            echo '<td>' . number_format((float) $item['selling_price'], 2) . '</td>';
            echo '<td>' . htmlspecialchars($item['supplier'] ?: 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($item['purchase_date'] ?: 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($item['status']) . '</td>';
            echo '<td>' . htmlspecialchars($item['notes'] ?: '-') . '</td>';
            echo '</tr>';
        }
    }

    echo '</table>';
    exit();
}

function inventoryExportPdf(mysqli $conn): void
{
    ensureInventorySoftDeleteSchema($conn);
    $result = $conn->query('SELECT * FROM inventory WHERE COALESCE(is_deleted, 0) = 0 ORDER BY item_name');
    $items = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }

    $pdfSafe = static function ($value): string {
        $text = (string) ($value ?? '-');
        $text = preg_replace('/\s+/', ' ', trim($text));
        $text = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($text === false || $text === '') {
            $text = '-';
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    };

    $drawText = static function ($font, $size, $x, $y, $text) use ($pdfSafe): string {
        return "BT /{$font} {$size} Tf {$x} {$y} Td (" . $pdfSafe($text) . ") Tj ET\n";
    };

    $truncate = static function ($text, $length): string {
        $text = trim((string) $text);
        if (strlen($text) <= $length) {
            return $text === '' ? '-' : $text;
        }
        return substr($text, 0, max(0, $length - 3)) . '...';
    };

    $buildPage = static function ($pageItems, $pageNumber) use ($drawText, $truncate): string {
        $content = '';
        $content .= "0.06 0.31 0.58 rg 0 545 842 50 re f\n";
        $content .= "0.98 0.98 0.98 rg 0 0 842 545 re f\n";
        $content .= "0 0 0 rg\n";
        $content .= $drawText('F2', 18, 24, 560, 'JASSNET Inventory Items Report');
        $content .= $drawText('F1', 10, 24, 544, 'Generated: ' . date('Y-m-d H:i'));
        $content .= $drawText('F1', 10, 730, 544, 'Page ' . $pageNumber);

        $content .= "0.85 0.9 0.96 rg 20 510 802 22 re f\n";
        $content .= "0 0 0 rg\n";

        $headers = [
            [24, '#'],
            [50, 'Item'],
            [220, 'Category'],
            [300, 'Qty'],
            [350, 'Purchase'],
            [440, 'Selling'],
            [530, 'Supplier'],
            [680, 'Status']
        ];

        foreach ($headers as [$x, $label]) {
            $content .= $drawText('F2', 9, $x, 517, $label);
        }

        $y = 496;
        $lineHeight = 14;
        foreach ($pageItems as $index => $item) {
            $content .= $drawText('F1', 8, 24, $y, (string) $item['_row_number']);
            $content .= $drawText('F1', 8, 50, $y, $truncate($item['item_name'], 30));
            $content .= $drawText('F1', 8, 220, $y, $truncate($item['category'], 12));
            $content .= $drawText('F1', 8, 300, $y, (string) ((int) $item['quantity']));
            $content .= $drawText('F1', 8, 350, $y, number_format((float) $item['purchase_price'], 2));
            $content .= $drawText('F1', 8, 440, $y, number_format((float) $item['selling_price'], 2));
            $content .= $drawText('F1', 8, 530, $y, $truncate($item['supplier'] ?: 'N/A', 24));
            $content .= $drawText('F1', 8, 680, $y, $truncate($item['status'], 12));
            $y -= $lineHeight;
        }

        return $content;
    };

    foreach ($items as $index => $item) {
        $items[$index]['_row_number'] = $index + 1;
    }

    $pages = array_chunk($items, 28);
    if (empty($pages)) {
        $pages = [[]];
    }

    $objects = [];
    $pageIds = [];
    $contentIds = [];
    $nextObjectId = 3;

    foreach ($pages as $pageIndex => $pageItems) {
        $pageIds[] = $nextObjectId++;
        $contentIds[] = $nextObjectId++;
    }

    $objects[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $kids = [];
    foreach ($pageIds as $pageId) {
        $kids[] = $pageId . ' 0 R';
    }
    $objects[2] = "2 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . count($pageIds) . " >>\nendobj\n";

    foreach ($pages as $pageIndex => $pageItems) {
        $pageId = $pageIds[$pageIndex];
        $contentId = $contentIds[$pageIndex];
        $content = $buildPage($pageItems, $pageIndex + 1);
        $objects[$pageId] = $pageId . " 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 50 0 R /F2 51 0 R >> >> /Contents {$contentId} 0 R >>\nendobj\n";
        $objects[$contentId] = $contentId . " 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n";
    }

    $objects[50] = "50 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    $objects[51] = "51 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";

    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [0 => 0];
    foreach ($objects as $id => $object) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $object;
    }

    $maxObjectId = max(array_keys($objects));
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . ($maxObjectId + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $maxObjectId; $i++) {
        if (isset($offsets[$i])) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        } else {
            $pdf .= "0000000000 00000 f \n";
        }
    }

    $pdf .= "trailer\n<< /Size " . ($maxObjectId + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="inventory_items_' . date('Ymd_His') . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit();
}

if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    inventoryExportExcel($conn);
}

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    inventoryExportPdf($conn);
}

$error = '';
$success_message = $_SESSION['success_message'] ?? '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    if (!$canUpdateStock) {
        $error = 'You are not authorized to update stock';
    } else {
    $item_id = intval($_POST['item_id'] ?? 0);
    $new_quantity = intval($_POST['new_quantity'] ?? 0);
    $operation = sanitize($_POST['operation'] ?? 'add');

    $result = $conn->query("SELECT quantity FROM inventory WHERE id = $item_id AND COALESCE(is_deleted, 0) = 0");
    $row = $result ? $result->fetch_assoc() : null;

    if (!$row) {
        $error = 'Inventory item not found';
    } else {
        $current_quantity = (int) $row['quantity'];
        $updated_quantity = $operation === 'add' ? $current_quantity + $new_quantity : max(0, $current_quantity - $new_quantity);

        $stmt = $conn->prepare('UPDATE inventory SET quantity = ? WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('ii', $updated_quantity, $item_id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Stock updated successfully';
                header('Location: inventory_items.php');
                exit();
            }
            $error = 'Error updating stock';
        } else {
            $error = 'Could not prepare stock update query';
        }
    }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_inventory_item'])) {
    if (!$canUpdateStock) {
        $error = 'You are not authorized to edit inventory items';
    } else {
        $item_id = intval($_POST['item_id'] ?? 0);
        $item_name = sanitize($_POST['item_name'] ?? '');
        $category = sanitize($_POST['category'] ?? '');
        $quantity = intval($_POST['quantity'] ?? 0);
        $purchase_price = floatval($_POST['purchase_price'] ?? 0);
        $selling_price = floatval($_POST['selling_price'] ?? 0);
        $supplier = sanitize($_POST['supplier'] ?? '');
        $purchase_date = trim($_POST['purchase_date'] ?? '');
        $status = sanitize($_POST['status'] ?? 'Available');
        $notes = sanitize($_POST['notes'] ?? '');

        if ($item_id <= 0 || $item_name === '' || $category === '') {
            $error = 'Please fill all required item fields';
        } else {
            $stmt = $conn->prepare('UPDATE inventory SET item_name = ?, category = ?, quantity = ?, purchase_price = ?, selling_price = ?, supplier = ?, purchase_date = NULLIF(?, ""), status = ?, notes = ? WHERE id = ? AND COALESCE(is_deleted, 0) = 0');
            if ($stmt) {
                $stmt->bind_param('ssiddssssi', $item_name, $category, $quantity, $purchase_price, $selling_price, $supplier, $purchase_date, $status, $notes, $item_id);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Inventory item updated successfully';
                    header('Location: inventory_items.php');
                    exit();
                }
                $error = 'Failed to update inventory item';
            } else {
                $error = 'Could not prepare inventory update query';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_inventory_item'])) {
    if (!$canUpdateStock) {
        $error = 'You are not authorized to delete inventory items';
    } else {
        $item_id = intval($_POST['item_id'] ?? 0);
        if ($item_id <= 0) {
            $error = 'Invalid inventory item selected';
        } else {
            $usage = inventoryLinkedUsageSummary($conn, $item_id);
            if ($usage['total'] > 0 && !$canForceArchiveInventory) {
                $messages = [];
                if ($usage['equipment_requests'] > 0) {
                    $messages[] = $usage['equipment_requests'] . ' equipment request(s)';
                }
                if ($usage['station_equipment'] > 0) {
                    $messages[] = $usage['station_equipment'] . ' station equipment record(s)';
                }
                $error = 'Unable to delete item because it is already used in ' . implode(' and ', $messages) . '.';
            } else {
                $stmt = $conn->prepare('UPDATE inventory SET is_deleted = 1, deleted_at = NOW() WHERE id = ? AND COALESCE(is_deleted, 0) = 0');
                if ($stmt) {
                    $stmt->bind_param('i', $item_id);
                    try {
                        if ($stmt->execute()) {
                            if ($stmt->affected_rows > 0) {
                                $_SESSION['success_message'] = 'Inventory item archived successfully';
                                header('Location: inventory_items.php');
                                exit();
                            }
                            $error = 'Inventory item not found or already archived';
                        } else {
                            $error = 'Unable to delete item. It may already be used in other records.';
                        }
                    } catch (mysqli_sql_exception $exception) {
                        $error = 'Unable to delete item because it is linked to existing records.';
                    }
                } else {
                    $error = 'Could not prepare inventory delete query';
                }
            }
        }
    }
}

$inventory_items = $conn->query('SELECT * FROM inventory WHERE COALESCE(is_deleted, 0) = 0 ORDER BY item_name');
?>

<?php include '../includes/header.php'; ?>

<?php if ($success_message): ?>
<div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" style="font-size: 1.2rem; padding: 1rem; min-width: 400px;">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-check-circle me-2" style="font-size: 1.5rem;"></i> <?php echo htmlspecialchars(formatSuccessMessage($success_message)); ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row mb-4" id="inventory-items">
    <div class="col-md-12 d-flex justify-content-between align-items-center">
        <h2><i class="fas fa-list"></i> Inventory Items</h2>
        <div class="d-flex gap-2 flex-wrap">
            <a href="inventory_items.php?export=pdf" class="btn btn-outline-danger"><i class="fas fa-file-pdf"></i> Download PDF</a>
            <a href="inventory_items.php?export=excel" class="btn btn-outline-success"><i class="fas fa-file-excel"></i> Download Excel</a>
            <?php if ($canUpdateStock): ?>
            <a href="add_inventory.php" class="btn btn-outline-secondary"><i class="fas fa-plus"></i> Add Inventory Item</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-boxes"></i> Items</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-modern">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Purchase Price</th>
                                <th>Selling Price</th>
                                <th>Supplier</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($inventory_items): ?>
                                <?php while ($item = $inventory_items->fetch_assoc()): ?>
                                <?php $usage = inventoryLinkedUsageSummary($conn, (int) $item['id']); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                                    <td><span class="<?php echo $item['quantity'] < 5 ? 'text-danger fw-bold' : ''; ?>"><?php echo (int) $item['quantity']; ?></span></td>
                                    <td>Tshs. <?php echo number_format((float) $item['purchase_price'], 2); ?></td>
                                    <td>Tshs. <?php echo number_format((float) $item['selling_price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($item['supplier']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $item['status'] == 'Available' ? 'success' : ($item['status'] == 'Damaged' ? 'danger' : 'warning'); ?>">
                                            <?php echo htmlspecialchars($item['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-info"
                                                onclick="viewItem(this)"
                                                data-item-id="<?php echo (int) $item['id']; ?>"
                                                data-item-name="<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>"
                                                data-category="<?php echo htmlspecialchars($item['category'], ENT_QUOTES); ?>"
                                                data-quantity="<?php echo (int) $item['quantity']; ?>"
                                                data-purchase-price="<?php echo number_format((float) $item['purchase_price'], 2, '.', ''); ?>"
                                                data-selling-price="<?php echo number_format((float) $item['selling_price'], 2, '.', ''); ?>"
                                                data-supplier="<?php echo htmlspecialchars($item['supplier'] ?: 'N/A', ENT_QUOTES); ?>"
                                                data-purchase-date="<?php echo htmlspecialchars($item['purchase_date'] ?: 'N/A', ENT_QUOTES); ?>"
                                                data-status="<?php echo htmlspecialchars($item['status'], ENT_QUOTES); ?>"
                                                data-notes="<?php echo htmlspecialchars($item['notes'] ?: '-', ENT_QUOTES); ?>"
                                            >View</button>
                                            <?php if ($canUpdateStock): ?>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-secondary"
                                                onclick="editItem(this)"
                                                data-item-id="<?php echo (int) $item['id']; ?>"
                                                data-item-name="<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>"
                                                data-category="<?php echo htmlspecialchars($item['category'], ENT_QUOTES); ?>"
                                                data-quantity="<?php echo (int) $item['quantity']; ?>"
                                                data-purchase-price="<?php echo number_format((float) $item['purchase_price'], 2, '.', ''); ?>"
                                                data-selling-price="<?php echo number_format((float) $item['selling_price'], 2, '.', ''); ?>"
                                                data-supplier="<?php echo htmlspecialchars($item['supplier'], ENT_QUOTES); ?>"
                                                data-purchase-date="<?php echo htmlspecialchars($item['purchase_date'] ?: '', ENT_QUOTES); ?>"
                                                data-status="<?php echo htmlspecialchars($item['status'], ENT_QUOTES); ?>"
                                                data-notes="<?php echo htmlspecialchars($item['notes'] ?: '', ENT_QUOTES); ?>"
                                            >Edit</button>
                                            <button class="btn btn-sm btn-outline-primary" onclick="updateStock(<?php echo (int) $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>', <?php echo (int) $item['quantity']; ?>)">Stock</button>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                <?php if (($usage['total'] ?? 0) > 0 && !$canForceArchiveInventory): ?>disabled title="This item is already used in request history and cannot be archived by your role."<?php else: ?>onclick="deleteItem(<?php echo (int) $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES); ?>')"<?php endif; ?>
                                            ><?php echo inventorySoftDeleteLabel($usage, $canForceArchiveInventory); ?></button>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (($usage['total'] ?? 0) > 0): ?>
                                            <div class="small text-muted mt-1">
                                                Linked to <?php echo (int) ($usage['equipment_requests'] ?? 0); ?> equipment request(s)
                                                <?php if ((int) ($usage['station_equipment'] ?? 0) > 0): ?> and <?php echo (int) $usage['station_equipment']; ?> station record(s)<?php endif; ?>.
                                                <?php if ($canForceArchiveInventory): ?> Super Admin can still archive this item without removing history.<?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canUpdateStock): ?>
<div class="modal fade" id="updateStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="updateStockForm">
                    <input type="hidden" name="item_id" id="updateStockItemId">
                    <div class="mb-3">
                        <label>Item: <span id="updateStockItemName"></span></label>
                        <p>Current Quantity: <span id="updateStockCurrentQty"></span></p>
                    </div>
                    <div class="mb-3">
                        <label for="operation" class="form-label">Operation</label>
                        <select class="form-select" id="operation" name="operation" required>
                            <option value="add">Add Stock</option>
                            <option value="subtract">Remove Stock</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="new_quantity" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="new_quantity" name="new_quantity" min="1" required>
                    </div>
                    <button type="submit" name="update_stock" class="btn btn-primary">Update Stock</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="viewItemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Inventory Item Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><strong>Item Name:</strong> <span id="viewItemName"></span></div>
                    <div class="col-md-6"><strong>Category:</strong> <span id="viewItemCategory"></span></div>
                    <div class="col-md-6"><strong>Quantity:</strong> <span id="viewItemQuantity"></span></div>
                    <div class="col-md-6"><strong>Purchase Price:</strong> <span id="viewItemPurchasePrice"></span></div>
                    <div class="col-md-6"><strong>Selling Price:</strong> <span id="viewItemSellingPrice"></span></div>
                    <div class="col-md-6"><strong>Supplier:</strong> <span id="viewItemSupplier"></span></div>
                    <div class="col-md-6"><strong>Purchase Date:</strong> <span id="viewItemPurchaseDate"></span></div>
                    <div class="col-md-6"><strong>Status:</strong> <span id="viewItemStatus"></span></div>
                    <div class="col-12"><strong>Notes:</strong><div id="viewItemNotes" class="mt-2 text-muted"></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($canUpdateStock): ?>
<div class="modal fade" id="editItemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Inventory Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editItemForm">
                    <input type="hidden" name="item_id" id="editItemId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="editItemName" class="form-label">Item Name</label>
                            <input type="text" class="form-control" id="editItemName" name="item_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editItemCategory" class="form-label">Category</label>
                            <select class="form-select" id="editItemCategory" name="category" required>
                                <option value="Router">Router</option>
                                <option value="Cable">Cable</option>
                                <option value="Antenna">Antenna</option>
                                <option value="Tools">Tools</option>
                                <option value="Accessories">Accessories</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="editItemQuantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="editItemQuantity" name="quantity" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label for="editPurchasePrice" class="form-label">Purchase Price (Tshs.)</label>
                            <input type="number" class="form-control" id="editPurchasePrice" name="purchase_price" step="0.01" min="0">
                        </div>
                        <div class="col-md-4">
                            <label for="editSellingPrice" class="form-label">Selling Price (Tshs.)</label>
                            <input type="number" class="form-control" id="editSellingPrice" name="selling_price" step="0.01" min="0">
                        </div>
                        <div class="col-md-6">
                            <label for="editSupplier" class="form-label">Supplier</label>
                            <input type="text" class="form-control" id="editSupplier" name="supplier">
                        </div>
                        <div class="col-md-3">
                            <label for="editPurchaseDate" class="form-label">Purchase Date</label>
                            <input type="date" class="form-control" id="editPurchaseDate" name="purchase_date">
                        </div>
                        <div class="col-md-3">
                            <label for="editStatus" class="form-label">Status</label>
                            <select class="form-select" id="editStatus" name="status" required>
                                <option value="Available">Available</option>
                                <option value="Installed">Installed</option>
                                <option value="Damaged">Damaged</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="editNotes" class="form-label">Notes</label>
                            <textarea class="form-control" id="editNotes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" name="edit_inventory_item" class="btn btn-primary">Save Changes</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Archive Inventory Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Archive item <strong id="deleteItemName"></strong>? It will disappear from active inventory lists, but historical request records will remain intact.</p>
                <form method="POST" id="deleteItemForm">
                    <input type="hidden" name="item_id" id="deleteItemId">
                    <button type="submit" name="delete_inventory_item" class="btn btn-danger">Archive Item</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function viewItem(button) {
    document.getElementById('viewItemName').textContent = button.dataset.itemName || '-';
    document.getElementById('viewItemCategory').textContent = button.dataset.category || '-';
    document.getElementById('viewItemQuantity').textContent = button.dataset.quantity || '0';
    document.getElementById('viewItemPurchasePrice').textContent = 'Tshs. ' + (button.dataset.purchasePrice || '0.00');
    document.getElementById('viewItemSellingPrice').textContent = 'Tshs. ' + (button.dataset.sellingPrice || '0.00');
    document.getElementById('viewItemSupplier').textContent = button.dataset.supplier || 'N/A';
    document.getElementById('viewItemPurchaseDate').textContent = button.dataset.purchaseDate || 'N/A';
    document.getElementById('viewItemStatus').textContent = button.dataset.status || '-';
    document.getElementById('viewItemNotes').textContent = button.dataset.notes || '-';
    new bootstrap.Modal(document.getElementById('viewItemModal')).show();
}

<?php if ($canUpdateStock): ?>
function updateStock(id, name, currentQty) {
    document.getElementById('updateStockItemId').value = id;
    document.getElementById('updateStockItemName').textContent = name;
    document.getElementById('updateStockCurrentQty').textContent = currentQty;
    new bootstrap.Modal(document.getElementById('updateStockModal')).show();
}

function editItem(button) {
    document.getElementById('editItemId').value = button.dataset.itemId || '';
    document.getElementById('editItemName').value = button.dataset.itemName || '';
    document.getElementById('editItemCategory').value = button.dataset.category || 'Router';
    document.getElementById('editItemQuantity').value = button.dataset.quantity || '0';
    document.getElementById('editPurchasePrice').value = button.dataset.purchasePrice || '0.00';
    document.getElementById('editSellingPrice').value = button.dataset.sellingPrice || '0.00';
    document.getElementById('editSupplier').value = button.dataset.supplier === 'N/A' ? '' : (button.dataset.supplier || '');
    document.getElementById('editPurchaseDate').value = button.dataset.purchaseDate === 'N/A' ? '' : (button.dataset.purchaseDate || '');
    document.getElementById('editStatus').value = button.dataset.status || 'Available';
    document.getElementById('editNotes').value = button.dataset.notes === '-' ? '' : (button.dataset.notes || '');
    new bootstrap.Modal(document.getElementById('editItemModal')).show();
}

function deleteItem(id, name) {
    document.getElementById('deleteItemId').value = id;
    document.getElementById('deleteItemName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteItemModal')).show();
}
<?php endif; ?>

<?php if ($success_message): ?>
document.addEventListener('DOMContentLoaded', function() {
    const toast = new bootstrap.Toast(document.getElementById('successToast'), { delay: 5000 });
    toast.show();
});
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>