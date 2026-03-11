<?php
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

requirePermission(['Sales', 'Technician', 'Manager', 'Director', 'Accountant', 'Super Admin']);

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

<div class="main-content">
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="mb-0"><i class="fas fa-plus"></i> Submit Expense Request</h2>
                    <a href="view_expense_requests.php" class="btn btn-secondary">
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
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Expense Request Form</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="department" name="department" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="category" class="form-label">Expense Category <span class="text-danger">*</span></label>
                                        <select class="form-select" id="category" name="category" required>
                                            <option value="">Select Category</option>
                                            <option value="Fuel">Fuel</option>
                                            <option value="Equipment">Equipment</option>
                                            <option value="Maintenance">Maintenance</option>
                                            <option value="Transport">Transport</option>
                                            <option value="Salary">Salary</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="amount_requested" class="form-label">Amount Requested (Tshs.) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="amount_requested" name="amount_requested" step="0.01" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="reason" class="form-label">Reason for Expense <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="reason" name="reason" rows="2" required></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="project_ref" class="form-label">Project / Job Reference</label>
                                        <input type="text" class="form-control" id="project_ref" name="project_ref">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" name="add_expense_request" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Submit Request
                                </button>
                                <a href="view_expense_requests.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
