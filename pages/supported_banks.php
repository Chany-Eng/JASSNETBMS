<?php
require_once '../includes/functions.php';
require_once '../includes/snippe_payouts.php';

if (!isLoggedIn()) {
    header('Location: ../index.php');
    exit();
}

requirePermission(['Super Admin']);

$banks = snippeGetSupportedBanks();

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h2 class="mb-1"><i class="fas fa-university"></i> Supported Banks</h2>
                    <div class="text-muted">Reference list for Snippe bank payout codes used in user payout settings.</div>
                </div>
                <div class="d-flex gap-2">
                    <a href="users.php#users-list" class="btn btn-outline-secondary">
                        <i class="fas fa-users"></i> Back to Users
                    </a>
                    <a href="add_user.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Add User
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">How To Use</h5>
                    <p class="card-text text-muted mb-2">Save the bank code, not the free-text bank name, in user payout settings.</p>
                    <p class="card-text text-muted mb-0">Example: choose <strong>ABSA</strong> for ABSA Bank Tanzania when a user should receive bank payouts.</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Current State</h5>
                    <p class="card-text text-muted mb-0">Users with preferred payout channel set to <strong>Bank Transfer</strong> must also have both a bank code and bank account number saved.</p>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Automation Scope</h5>
                    <p class="card-text text-muted mb-0">These codes are used by the automated accountant payout flow in Snippe when Bank Transfer is selected.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list"></i> Bank Codes</h5>
            <span class="badge bg-primary"><?php echo count($banks); ?> Banks</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Bank Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($banks as $code => $name): ?>
                        <tr>
                            <td><span class="badge bg-dark"><?php echo htmlspecialchars($code); ?></span></td>
                            <td><?php echo htmlspecialchars($name); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>