<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

requirePermission(['Sales', 'Technician', 'Store Keeper', 'Manager', 'Director', 'Accountant', 'Super Admin']);
ensureInventorySoftDeleteSchema($conn);

$canManagerApprove = hasPermission(['Manager']);
$canStoreIssue = hasPermission(['Store Keeper']);
$canRequestForOthers = hasPermission(['Store Keeper']);
$isApprovalUser = $canManagerApprove || $canStoreIssue || hasPermission(['Super Admin']);

function canEditEquipmentRequest(array $row): bool
{
    $isOwner = (int) ($row['requested_by'] ?? 0) === (int) ($_SESSION['user_id'] ?? 0);
    $editableStatuses = ['Pending', 'Rejected'];
    return hasPermission(['Super Admin']) || ($isOwner && in_array((string) ($row['status'] ?? ''), $editableStatuses, true));
}

if (isset($_GET['action']) && $_GET['action'] === 'pdf' && isset($_GET['request_id'])) {
    $request_id = intval($_GET['request_id']);
    if ($request_id <= 0) {
        http_response_code(400);
        echo 'Invalid request ID';
        exit();
    }

    $stmt = $conn->prepare("SELECT er.*, i.item_name, u.full_name AS requested_by_name, u.role AS requested_by_role, am.full_name AS manager_name, sk.full_name AS issuer_name FROM equipment_requests er JOIN inventory i ON er.item_id = i.id JOIN users u ON er.requested_by = u.id LEFT JOIN users am ON er.approved_by = am.id LEFT JOIN users sk ON er.issued_by = sk.id WHERE er.id = ?");
    if (!$stmt) {
        http_response_code(500);
        echo 'Failed to prepare request details';
        exit();
    }

    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $requestData = $stmt->get_result()->fetch_assoc();

    if (!$requestData) {
        http_response_code(404);
        echo 'Request not found';
        exit();
    }

    if (!$isApprovalUser && (int) $requestData['requested_by'] !== (int) $_SESSION['user_id']) {
        http_response_code(403);
        echo 'Unauthorized';
        exit();
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

    $wrapText = static function ($prefix, $value, $maxLen = 78): array {
        $clean = trim((string) $value);
        if ($clean === '') {
            $clean = '-';
        }

        $words = preg_split('/\s+/', $clean) ?: [];
        $lines = [];
        $current = $prefix;

        foreach ($words as $word) {
            $candidate = ($current === $prefix) ? ($current . $word) : ($current . ' ' . $word);
            if (strlen($candidate) > $maxLen) {
                $lines[] = $current;
                $current = '   ' . $word;
            } else {
                $current = $candidate;
            }
        }

        $lines[] = $current;
        return $lines;
    };

    $logoJpg = '';
    $logoWidth = 0;
    $logoHeight = 0;
    $logoPath = dirname(__DIR__) . '/assets/images/logo.png';
    if (file_exists($logoPath) && function_exists('imagecreatefrompng') && function_exists('imagejpeg')) {
        $img = @imagecreatefrompng($logoPath);
        if ($img !== false) {
            $logoWidth = imagesx($img);
            $logoHeight = imagesy($img);
            ob_start();
            imagejpeg($img, null, 90);
            $logoJpg = (string) ob_get_clean();
            imagedestroy($img);
        }
    }

    $content = '';
    $content .= "0.06 0.31 0.58 rg 0 780 595 62 re f\n";
    $content .= "0.95 0.95 0.95 rg 0 0 595 780 re f\n";
    $content .= "0 0 0 rg\n";

    if ($logoJpg !== '' && $logoWidth > 0 && $logoHeight > 0) {
        $displayW = 42;
        $displayH = max(18, ($logoHeight / $logoWidth) * $displayW);
        $content .= "q {$displayW} 0 0 {$displayH} 24 792 cm /Im1 Do Q\n";
    }

    $content .= $drawText('F2', 18, 78, 815, 'JASSNET');
    $content .= $drawText('F1', 10, 78, 799, 'Equipment Request Document');
    $content .= $drawText('F1', 9, 420, 815, 'Generated: ' . date('Y-m-d H:i'));

    $content .= "0.85 0.85 0.85 RG 1 w 20 90 555 670 re S\n";
    $content .= "0.88 0.92 0.98 rg 20 720 555 40 re f\n";
    $content .= "0 0 0 rg\n";
    $content .= $drawText('F2', 12, 28, 735, 'Equipment Request Summary');

    $y = 700;
    $lineStep = 18;
    $detailLines = [
        'Request ID: ' . (int) $requestData['id'],
        'Date: ' . date('M d, Y', strtotime($requestData['request_date'])),
        'Requested By: ' . ($requestData['requested_by_name'] ?? '-'),
        'Role: ' . ($requestData['requested_by_role'] ?? '-'),
        'Item: ' . ($requestData['item_name'] ?? '-'),
        'Quantity: ' . (int) $requestData['quantity'],
        'Status: ' . ($requestData['status'] ?? '-'),
        'Manager Approval: ' . ($requestData['manager_name'] ?: '-'),
        'Store Keeper Approval: ' . ($requestData['issuer_name'] ?: '-')
    ];

    foreach ($detailLines as $line) {
        $content .= $drawText('F1', 11, 30, $y, $line);
        $y -= $lineStep;
    }

    $y -= 8;
    foreach ($wrapText('Reason: ', ($requestData['reason'] ?: '-')) as $line) {
        $content .= $drawText('F1', 11, 30, $y, $line);
        $y -= $lineStep;
    }

    foreach ($wrapText('Project: ', ($requestData['project'] ?: '-')) as $line) {
        $content .= $drawText('F1', 11, 30, $y, $line);
        $y -= $lineStep;
    }

    $content .= "0.75 0.75 0.75 RG 1 w 30 150 m 300 150 l S\n";
    $content .= $drawText('F2', 11, 30, 166, 'User Signature');
    $content .= $drawText('F1', 10, 30, 132, 'Name: ' . ($requestData['requested_by_name'] ?? '-'));
    $content .= $drawText('F1', 10, 330, 132, 'Date: ____________________');

    $contentObjId = 6;
    $imageObjId = 7;
    $resourceXObj = '';
    if ($logoJpg !== '' && $logoWidth > 0 && $logoHeight > 0) {
        $resourceXObj = ' /XObject << /Im1 ' . $imageObjId . ' 0 R >>';
    }

    $objects = [];
    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >>{$resourceXObj} >> /Contents {$contentObjId} 0 R >>\nendobj\n";
    $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";
    $objects[] = "{$contentObjId} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n";

    if ($logoJpg !== '' && $logoWidth > 0 && $logoHeight > 0) {
        $objects[] = "{$imageObjId} 0 obj\n<< /Type /XObject /Subtype /Image /Width {$logoWidth} /Height {$logoHeight} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($logoJpg) . " >>\nstream\n" . $logoJpg . "\nendstream\nendobj\n";
    }

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $obj) {
        $offsets[] = strlen($pdf);
        $pdf .= $obj;
    }

    $objCount = count($objects);
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . ($objCount + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $objCount; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size " . ($objCount + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="equipment_request_' . $request_id . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit();
}

$error = '';
$success_message = $_SESSION['success_message'] ?? '';
if ($success_message) {
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_request'])) {
        $item_id = intval($_POST['item_id'] ?? 0);
        $quantity_requested = intval($_POST['quantity_issued'] ?? 0);
        $reason = sanitize($_POST['reason'] ?? '');
        $project = sanitize($_POST['project'] ?? '');

        // Store Keeper can submit on behalf of another active field user.
        if ($canRequestForOthers && isset($_POST['requested_by']) && intval($_POST['requested_by']) > 0) {
            $requested_by = intval($_POST['requested_by']);
        } else {
            $requested_by = (int) $_SESSION['user_id'];
        }

        if ($item_id <= 0 || $quantity_requested <= 0 || $requested_by <= 0 || $reason === '') {
            $error = 'Please fill all required fields correctly';
        } else {
            if ($canRequestForOthers && $requested_by !== (int) $_SESSION['user_id']) {
                $userCheckStmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1 AND (FIND_IN_SET('Sales', REPLACE(COALESCE(role, ''), ', ', ',')) > 0 OR FIND_IN_SET('Technician', REPLACE(COALESCE(role, ''), ', ', ',')) > 0)");
                if ($userCheckStmt) {
                    $userCheckStmt->bind_param('i', $requested_by);
                    $userCheckStmt->execute();
                    $validTargetUser = $userCheckStmt->get_result()->fetch_assoc();
                    if (!$validTargetUser) {
                        $error = 'Selected user is invalid for equipment request';
                    }
                }
            }

            if ($error) {
                // no-op, keep validation message
            } else {
            $itemStmt = $conn->prepare('SELECT id FROM inventory WHERE id = ? AND COALESCE(is_deleted, 0) = 0');
            if ($itemStmt) {
                $itemStmt->bind_param('i', $item_id);
                $itemStmt->execute();
                $itemExists = $itemStmt->get_result()->fetch_assoc();

                if (!$itemExists) {
                    $error = 'Selected item not found';
                } else {
                    $stmt = $conn->prepare("INSERT INTO equipment_requests (requested_by, item_id, quantity, reason, project, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
                    if ($stmt) {
                        $stmt->bind_param('iiiss', $requested_by, $item_id, $quantity_requested, $reason, $project);
                        if ($stmt->execute()) {
                            $_SESSION['success_message'] = 'Equipment request submitted. Waiting for Manager approval.';
                            header('Location: issue_equipment.php');
                            exit();
                        }
                        $error = 'Error submitting equipment request';
                    } else {
                        $error = 'Could not prepare request query';
                    }
                }
            } else {
                $error = 'Could not verify selected item';
            }
            }
        }
    } elseif (isset($_POST['approve_manager'])) {
        if (!$canManagerApprove) {
            $error = 'You are not authorized to do manager approval';
        } else {
            $request_id = intval($_POST['request_id'] ?? 0);
            $stmt = $conn->prepare("UPDATE equipment_requests SET status = 'Approved', approved_by = ? WHERE id = ? AND status = 'Pending'");
            if ($stmt) {
                $stmt->bind_param('ii', $_SESSION['user_id'], $request_id);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $_SESSION['success_message'] = 'Request approved by Manager. Waiting for Store Keeper final approval.';
                    header('Location: issue_equipment.php');
                    exit();
                }
                $error = 'Request not found or already processed';
            } else {
                $error = 'Could not prepare manager approval query';
            }
        }
    } elseif (isset($_POST['reject_manager'])) {
        if (!$canManagerApprove) {
            $error = 'You are not authorized to reject as manager';
        } else {
            $request_id = intval($_POST['request_id'] ?? 0);
            $stmt = $conn->prepare("UPDATE equipment_requests SET status = 'Rejected', approved_by = ? WHERE id = ? AND status = 'Pending'");
            if ($stmt) {
                $stmt->bind_param('ii', $_SESSION['user_id'], $request_id);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $_SESSION['success_message'] = 'Request rejected by Manager.';
                    header('Location: issue_equipment.php');
                    exit();
                }
                $error = 'Request not found or already processed';
            } else {
                $error = 'Could not prepare manager reject query';
            }
        }
    } elseif (isset($_POST['approve_storekeeper'])) {
        if (!$canStoreIssue) {
            $error = 'You are not authorized to do Store Keeper approval';
        } else {
            $request_id = intval($_POST['request_id'] ?? 0);

            $reqStmt = $conn->prepare('SELECT er.id, er.item_id, er.quantity, i.quantity AS stock_quantity FROM equipment_requests er JOIN inventory i ON er.item_id = i.id WHERE er.id = ? AND er.status = \'Approved\'');
            if ($reqStmt) {
                $reqStmt->bind_param('i', $request_id);
                $reqStmt->execute();
                $requestData = $reqStmt->get_result()->fetch_assoc();

                if (!$requestData) {
                    $error = 'Request not found or not ready for Store Keeper approval';
                } elseif ((int) $requestData['stock_quantity'] < (int) $requestData['quantity']) {
                    $error = 'Insufficient stock to issue this request';
                } else {
                    $conn->begin_transaction();
                    try {
                        $updateStockStmt = $conn->prepare('UPDATE inventory SET quantity = quantity - ? WHERE id = ?');
                        $updateStockStmt->bind_param('ii', $requestData['quantity'], $requestData['item_id']);
                        $updateStockStmt->execute();

                        $issueStmt = $conn->prepare("UPDATE equipment_requests SET status = 'Issued', issued_by = ? WHERE id = ? AND status = 'Approved'");
                        $issueStmt->bind_param('ii', $_SESSION['user_id'], $request_id);
                        $issueStmt->execute();

                        if ($issueStmt->affected_rows <= 0) {
                            throw new Exception('Request could not be issued');
                        }

                        $conn->commit();
                        $_SESSION['success_message'] = 'Request approved by Store Keeper and equipment issued successfully.';
                        header('Location: issue_equipment.php');
                        exit();
                    } catch (Throwable $e) {
                        $conn->rollback();
                        $error = 'Failed to issue equipment: ' . $e->getMessage();
                    }
                }
            } else {
                $error = 'Could not prepare Store Keeper approval query';
            }
        }
    } elseif (isset($_POST['reject_storekeeper'])) {
        if (!$canStoreIssue) {
            $error = 'You are not authorized to reject as Store Keeper';
        } else {
            $request_id = intval($_POST['request_id'] ?? 0);
            $stmt = $conn->prepare("UPDATE equipment_requests SET status = 'Rejected', issued_by = ? WHERE id = ? AND status = 'Approved'");
            if ($stmt) {
                $stmt->bind_param('ii', $_SESSION['user_id'], $request_id);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $_SESSION['success_message'] = 'Request rejected by Store Keeper.';
                    header('Location: issue_equipment.php');
                    exit();
                }
                $error = 'Request not found or already processed';
            } else {
                $error = 'Could not prepare Store Keeper reject query';
            }
        }
    } elseif (isset($_POST['update_equipment_request'])) {
        $request_id = intval($_POST['request_id'] ?? 0);
        $item_id = intval($_POST['item_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 0);
        $reason = sanitize($_POST['reason'] ?? '');
        $project = sanitize($_POST['project'] ?? '');

        $checkStmt = $conn->prepare('SELECT requested_by, status FROM equipment_requests WHERE id = ? LIMIT 1');
        if ($checkStmt) {
            $checkStmt->bind_param('i', $request_id);
            $checkStmt->execute();
            $existingRequest = $checkStmt->get_result()->fetch_assoc();

            if (!$existingRequest) {
                $error = 'Equipment request not found';
            } elseif (!canEditEquipmentRequest(['requested_by' => $existingRequest['requested_by'], 'status' => $existingRequest['status']])) {
                $error = 'You are not allowed to edit this equipment request';
            } elseif ($item_id <= 0 || $quantity <= 0 || $reason === '') {
                $error = 'Please fill all required equipment fields before saving changes';
            } else {
                $stmt = $conn->prepare('UPDATE equipment_requests SET item_id = ?, quantity = ?, reason = ?, project = ? WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('iissi', $item_id, $quantity, $reason, $project, $request_id);
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = 'Equipment request updated successfully';
                        header('Location: issue_equipment.php');
                        exit();
                    }
                }
                $error = 'Failed to update equipment request';
            }
        } else {
            $error = 'Could not verify equipment request for editing';
        }
    } elseif (isset($_POST['delete_equipment_request'])) {
        if (!hasPermission(['Super Admin'])) {
            $error = 'Only Super Admin can delete equipment requests';
        } else {
            $request_id = intval($_POST['request_id'] ?? 0);
            $stmt = $conn->prepare('DELETE FROM equipment_requests WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $request_id);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $_SESSION['success_message'] = 'Equipment request deleted successfully';
                    header('Location: issue_equipment.php');
                    exit();
                }
                $error = 'Equipment request not found or already removed';
            } else {
                $error = 'Could not prepare equipment delete query';
            }
        }
    }
}

$items_result = $conn->query('SELECT * FROM inventory WHERE quantity > 0 AND COALESCE(is_deleted, 0) = 0 ORDER BY item_name');
$users_result = null;
if ($canRequestForOthers) {
    $users_result = $conn->query("SELECT id, full_name, role FROM users WHERE is_active = 1 AND (FIND_IN_SET('Sales', REPLACE(COALESCE(role, ''), ', ', ',')) > 0 OR FIND_IN_SET('Technician', REPLACE(COALESCE(role, ''), ', ', ',')) > 0) ORDER BY full_name");
}
$requestsWhere = '';
if (!$isApprovalUser) {
    $requestsWhere = 'WHERE er.requested_by = ' . (int) $_SESSION['user_id'];
}

$equipment_requests = $conn->query("SELECT er.*, i.item_name, u.full_name AS requested_by_name, am.full_name AS manager_name, sk.full_name AS issuer_name FROM equipment_requests er JOIN inventory i ON er.item_id = i.id JOIN users u ON er.requested_by = u.id LEFT JOIN users am ON er.approved_by = am.id LEFT JOIN users sk ON er.issued_by = sk.id $requestsWhere ORDER BY er.request_date DESC, er.id DESC LIMIT 50");
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

<div class="row mb-4" id="issue-equipment">
    <div class="col-md-12 d-flex justify-content-between align-items-center">
        <h2><i class="fas fa-tools"></i> Equipment Request & Approval</h2>
        <a href="inventory_items.php" class="btn btn-outline-secondary"><i class="fas fa-list"></i> Inventory Items</a>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-paper-plane"></i> Submit Equipment Request</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="issue_item_id" class="form-label">Select Item</label>
                        <select class="form-select" id="issue_item_id" name="item_id" required>
                            <option value="">Select Item</option>
                            <?php if ($items_result): ?>
                                <?php while ($item = $items_result->fetch_assoc()): ?>
                                    <option value="<?php echo (int) $item['id']; ?>"><?php echo htmlspecialchars($item['item_name']); ?> (<?php echo (int) $item['quantity']; ?> available)</option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <?php if ($canRequestForOthers): ?>
                    <div class="mb-3">
                        <label for="requested_by" class="form-label">Request For User</label>
                        <select class="form-select" id="requested_by" name="requested_by">
                            <option value="<?php echo (int) $_SESSION['user_id']; ?>">Self - <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Current User'); ?></option>
                            <?php if ($users_result): ?>
                                <?php while ($user = $users_result->fetch_assoc()): ?>
                                    <?php if ((int) $user['id'] === (int) $_SESSION['user_id']) { continue; } ?>
                                    <option value="<?php echo (int) $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['role']); ?>)</option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                        <small class="text-muted">Store Keeper can submit requests for Sales or Technician users.</small>
                    </div>
                    <?php else: ?>
                    <div class="mb-3">
                        <label class="form-label">Requested By</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Current User'); ?>" disabled>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="quantity_issued" class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="quantity_issued" name="quantity_issued" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="issue_reason" class="form-label">Reason</label>
                        <input type="text" class="form-control" id="issue_reason" name="reason" required>
                    </div>
                    <div class="mb-3">
                        <label for="issue_project" class="form-label">Project</label>
                        <input type="text" class="form-control" id="issue_project" name="project">
                    </div>
                    <button type="submit" name="submit_request" class="btn btn-primary">Submit Request</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-history"></i> <?php echo $isApprovalUser ? 'Equipment Requests (All)' : 'My Equipment Requests'; ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Item</th>
                                <th>Requested By</th>
                                <th>Qty</th>
                                <th>Reason</th>
                                <th>Project</th>
                                <th>Status</th>
                                <th>Process</th>
                                <th>Manager</th>
                                <th>Store Keeper</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($equipment_requests): ?>
                                <?php while ($request = $equipment_requests->fetch_assoc()): ?>
                                <tr id="equipmentRequestRow_<?php echo (int) $request['id']; ?>"
                                    data-item-id="<?php echo (int) ($request['item_id'] ?? 0); ?>"
                                    data-quantity="<?php echo (int) ($request['quantity'] ?? 0); ?>"
                                    data-reason="<?php echo htmlspecialchars((string) ($request['reason'] ?? ''), ENT_QUOTES); ?>"
                                    data-project="<?php echo htmlspecialchars((string) ($request['project'] ?? ''), ENT_QUOTES); ?>">
                                    <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($request['item_name']); ?></td>
                                    <td><?php echo htmlspecialchars($request['requested_by_name']); ?></td>
                                    <td><?php echo (int) $request['quantity']; ?></td>
                                    <td><?php echo htmlspecialchars($request['reason'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($request['project'] ?? '-'); ?></td>
                                    <td>
                                        <?php
                                            $statusClass = 'secondary';
                                            if ($request['status'] === 'Pending') {
                                                $statusClass = 'warning';
                                            } elseif ($request['status'] === 'Approved') {
                                                $statusClass = 'info';
                                            } elseif ($request['status'] === 'Issued') {
                                                $statusClass = 'success';
                                            } elseif ($request['status'] === 'Rejected') {
                                                $statusClass = 'danger';
                                            }
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($request['status']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($request['status'] === 'Pending'): ?>
                                            <small class="text-warning">Waiting Manager Approval</small>
                                        <?php elseif ($request['status'] === 'Approved'): ?>
                                            <small class="text-primary">Waiting Store Keeper Approval</small>
                                        <?php elseif ($request['status'] === 'Issued'): ?>
                                            <small class="text-success">Completed and Issued</small>
                                        <?php else: ?>
                                            <small class="text-danger">Stopped (Rejected)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['manager_name'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($request['issuer_name'] ?? '-'); ?></td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-info view-request-btn"
                                            data-request-id="<?php echo (int) $request['id']; ?>"
                                            data-request-date="<?php echo htmlspecialchars(date('M d, Y', strtotime($request['request_date'])), ENT_QUOTES); ?>"
                                            data-item-name="<?php echo htmlspecialchars($request['item_name'], ENT_QUOTES); ?>"
                                            data-requested-by="<?php echo htmlspecialchars($request['requested_by_name'], ENT_QUOTES); ?>"
                                            data-quantity="<?php echo (int) $request['quantity']; ?>"
                                            data-reason="<?php echo htmlspecialchars($request['reason'] ?? '-', ENT_QUOTES); ?>"
                                            data-project="<?php echo htmlspecialchars($request['project'] ?? '-', ENT_QUOTES); ?>"
                                            data-status="<?php echo htmlspecialchars($request['status'], ENT_QUOTES); ?>"
                                            data-process="<?php echo htmlspecialchars($request['status'] === 'Pending' ? 'Waiting Manager Approval' : ($request['status'] === 'Approved' ? 'Waiting Store Keeper Approval' : ($request['status'] === 'Issued' ? 'Completed and Issued' : 'Stopped (Rejected)')), ENT_QUOTES); ?>"
                                            data-manager="<?php echo htmlspecialchars($request['manager_name'] ?? '-', ENT_QUOTES); ?>"
                                            data-storekeeper="<?php echo htmlspecialchars($request['issuer_name'] ?? '-', ENT_QUOTES); ?>"
                                        >
                                            View
                                        </button>
                                        <?php if (canEditEquipmentRequest($request)): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="editEquipmentRequest(<?php echo (int) $request['id']; ?>)">Edit</button>
                                        <?php endif; ?>
                                        <?php if (hasPermission(['Super Admin'])): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteEquipmentRequest(<?php echo (int) $request['id']; ?>)">Delete</button>
                                        <?php endif; ?>
                                        <?php if ($canManagerApprove && $request['status'] === 'Pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                                <button type="submit" name="approve_manager" class="btn btn-sm btn-success">Manager Approve</button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                                <button type="submit" name="reject_manager" class="btn btn-sm btn-danger">Reject</button>
                                            </form>
                                        <?php elseif ($canStoreIssue && $request['status'] === 'Approved'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                                <button type="submit" name="approve_storekeeper" class="btn btn-sm btn-primary">Store Approve & Issue</button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                                <button type="submit" name="reject_storekeeper" class="btn btn-sm btn-outline-danger">Reject</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="text-center text-muted">No equipment requests found yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editEquipmentRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Equipment Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editEquipmentRequestForm">
                    <input type="hidden" name="request_id" id="editEquipmentRequestId">
                    <div class="mb-3">
                        <label for="edit_equipment_item_id" class="form-label">Select Item *</label>
                        <select class="form-select" id="edit_equipment_item_id" name="item_id" required>
                            <option value="">Select Item</option>
                            <?php
                            $editItemsResult = $conn->query('SELECT * FROM inventory WHERE quantity > 0 AND COALESCE(is_deleted, 0) = 0 ORDER BY item_name');
                            if ($editItemsResult):
                                while ($item = $editItemsResult->fetch_assoc()):
                            ?>
                            <option value="<?php echo (int) $item['id']; ?>"><?php echo htmlspecialchars($item['item_name']); ?> (<?php echo (int) $item['quantity']; ?> available)</option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_equipment_quantity" class="form-label">Quantity *</label>
                        <input type="number" class="form-control" id="edit_equipment_quantity" name="quantity" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_equipment_reason" class="form-label">Reason *</label>
                        <input type="text" class="form-control" id="edit_equipment_reason" name="reason" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_equipment_project" class="form-label">Project</label>
                        <input type="text" class="form-control" id="edit_equipment_project" name="project">
                    </div>
                    <button type="submit" name="update_equipment_request" class="btn btn-primary">Save Changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteEquipmentRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Equipment Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>This action permanently removes the selected equipment request.</p>
                <form method="POST" id="deleteEquipmentRequestForm">
                    <input type="hidden" name="request_id" id="deleteEquipmentRequestId">
                    <button type="submit" name="delete_equipment_request" class="btn btn-danger">Delete Request</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="requestDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-eye"></i> Equipment Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><strong>Request ID:</strong> <span id="detailRequestId">-</span></div>
                    <div class="col-md-6"><strong>Date:</strong> <span id="detailRequestDate">-</span></div>
                    <div class="col-md-6"><strong>Item:</strong> <span id="detailItem">-</span></div>
                    <div class="col-md-6"><strong>Requested By:</strong> <span id="detailRequestedBy">-</span></div>
                    <div class="col-md-6"><strong>Quantity:</strong> <span id="detailQuantity">-</span></div>
                    <div class="col-md-6"><strong>Status:</strong> <span id="detailStatus">-</span></div>
                    <div class="col-md-12"><strong>Reason:</strong> <span id="detailReason">-</span></div>
                    <div class="col-md-12"><strong>Project:</strong> <span id="detailProject">-</span></div>
                    <div class="col-md-6"><strong>Process:</strong> <span id="detailProcess">-</span></div>
                    <div class="col-md-6"><strong>Manager:</strong> <span id="detailManager">-</span></div>
                    <div class="col-md-6"><strong>Store Keeper:</strong> <span id="detailStoreKeeper">-</span></div>
                </div>
            </div>
            <div class="modal-footer">
                <a id="downloadRequestPdfBtn" href="#" class="btn btn-danger">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php if ($success_message): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toast = new bootstrap.Toast(document.getElementById('successToast'), { delay: 5000 });
    toast.show();
});
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const detailModalElement = document.getElementById('requestDetailsModal');
    const detailModal = detailModalElement ? new bootstrap.Modal(detailModalElement) : null;

    document.querySelectorAll('.view-request-btn').forEach(function(button) {
        button.addEventListener('click', function() {
            document.getElementById('detailRequestId').textContent = this.dataset.requestId || '-';
            document.getElementById('detailRequestDate').textContent = this.dataset.requestDate || '-';
            document.getElementById('detailItem').textContent = this.dataset.itemName || '-';
            document.getElementById('detailRequestedBy').textContent = this.dataset.requestedBy || '-';
            document.getElementById('detailQuantity').textContent = this.dataset.quantity || '-';
            document.getElementById('detailStatus').textContent = this.dataset.status || '-';
            document.getElementById('detailReason').textContent = this.dataset.reason || '-';
            document.getElementById('detailProject').textContent = this.dataset.project || '-';
            document.getElementById('detailProcess').textContent = this.dataset.process || '-';
            document.getElementById('detailManager').textContent = this.dataset.manager || '-';
            document.getElementById('detailStoreKeeper').textContent = this.dataset.storekeeper || '-';
            const pdfBtn = document.getElementById('downloadRequestPdfBtn');
            if (pdfBtn && this.dataset.requestId) {
                pdfBtn.href = 'issue_equipment.php?action=pdf&request_id=' + encodeURIComponent(this.dataset.requestId);
            }

            if (detailModal) {
                detailModal.show();
            }
        });
    });
});

function editEquipmentRequest(id) {
    const row = document.getElementById('equipmentRequestRow_' + id);
    if (!row) {
        return;
    }

    document.getElementById('editEquipmentRequestId').value = id;
    document.getElementById('edit_equipment_item_id').value = row.dataset.itemId || '';
    document.getElementById('edit_equipment_quantity').value = row.dataset.quantity || '';
    document.getElementById('edit_equipment_reason').value = row.dataset.reason || '';
    document.getElementById('edit_equipment_project').value = row.dataset.project || '';

    new bootstrap.Modal(document.getElementById('editEquipmentRequestModal')).show();
}

function deleteEquipmentRequest(id) {
    document.getElementById('deleteEquipmentRequestId').value = id;
    new bootstrap.Modal(document.getElementById('deleteEquipmentRequestModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>