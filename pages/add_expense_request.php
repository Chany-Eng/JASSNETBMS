<?php
require_once '../includes/functions.php';
require_once '../includes/expense_workflow.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

requirePermission(['Sales', 'Technician', 'Manager', 'Director', 'Accountant', 'Super Admin']);
expenseEnsureWorkflowSchema($conn);

$error = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_expense_request'])) {
        $department = sanitize($_POST['department']);
        $category = sanitize($_POST['category']);
        $description = sanitize($_POST['description']);
        $amount_requested = floatval($_POST['amount_requested']);
        $reason = sanitize($_POST['reason']);
        $project_ref = sanitize($_POST['project_ref']);
        $notes = sanitize($_POST['notes']);
        
        $stmt = $conn->prepare("INSERT INTO expense_requests (requested_by, department, category, description, amount_requested, reason, project_ref, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssdsss", $_SESSION['user_id'], $department, $category, $description, $amount_requested, $reason, $project_ref, $notes);
        
        if ($stmt->execute()) {
            $requestId = (int) $stmt->insert_id;
            $requester = getCurrentUser();
            $requesterName = trim((string) ($requester['full_name'] ?? ($_SESSION['full_name'] ?? 'Requester')));
            $formattedAmount = number_format($amount_requested, 2);
            appLogActivity($conn, 'CREATE_EXPENSE_REQUEST', 'Submitted expense request #' . $requestId, 'expense_requests', $requestId);
            if ($requester) {
                appSendSmsToUser($requester, 'ERMS: Expense request #' . $requestId . ' ya Tshs. ' . $formattedAmount . ' imepokelewa. Inasubiri Manager approval.');
            }
            appSendSmsToRoles($conn, ['Manager'], 'ERMS: Expense request #' . $requestId . ' kutoka ' . $requesterName . ' ya Tshs. ' . $formattedAmount . ' inasubiri approval yako.', [(int) ($_SESSION['user_id'] ?? 0)]);
            $_SESSION['success_message'] = 'Expense request submitted successfully';
            header("Location: view_expense_requests.php");
            exit();
        } else {
            $error = 'Error submitting expense request';
        }
    }
}

include '../includes/header.php';
?>

<style>
    .expense-hero {
        background: linear-gradient(120deg, #0f4c81 0%, #2a6f97 100%);
        color: #fff;
        border-radius: 14px;
        padding: 1.25rem 1.5rem;
        box-shadow: 0 10px 24px rgba(15, 76, 129, 0.25);
    }

    .section-label {
        font-weight: 700;
        font-size: 0.92rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #1f4f82;
        margin-bottom: 0.8rem;
    }

    .form-note {
        font-size: 0.85rem;
        color: #6c757d;
    }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="expense-hero d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h2 class="mb-1"><i class="fas fa-file-signature"></i> Submit Expense Request</h2>
                    <div class="small">Capture clear details so approvals move faster.</div>
                </div>
                <a href="view_expense_requests.php" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> Back to Requests
                </a>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-pen"></i> Expense Request Form</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="section-label">Basic Information</div>
                                <div class="mb-3">
                                    <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="department" name="department" value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="category" class="form-label">Expense Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <?php
                                        $categories = ['Fuel', 'Equipment', 'Maintenance', 'Transport', 'Salary', 'Other'];
                                        $selectedCategory = $_POST['category'] ?? '';
                                        foreach ($categories as $cat):
                                        ?>
                                            <option value="<?php echo $cat; ?>" <?php echo $selectedCategory === $cat ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="amount_requested" class="form-label">Amount Requested (Tshs.) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="amount_requested" name="amount_requested" step="0.01" min="0" value="<?php echo htmlspecialchars($_POST['amount_requested'] ?? ''); ?>" required>
                                    <div class="form-note">Use total expected amount including all related costs.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="section-label">Purpose</div>
                                <div class="mb-3">
                                    <label for="reason" class="form-label">Reason for Expense <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="reason" name="reason" rows="3" required><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="project_ref" class="form-label">Project / Job Reference</label>
                                    <input type="text" class="form-control" id="project_ref" name="project_ref" value="<?php echo htmlspecialchars($_POST['project_ref'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="add_expense_request" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Request
                            </button>
                            <a href="view_expense_requests.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
