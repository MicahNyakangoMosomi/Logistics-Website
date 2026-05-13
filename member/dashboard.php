<?php

require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/MemberService.php';

$member = Auth::requireMember();
$pdo = Database::connection();
$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    try {
        MemberService::update((string)$member['MemberID'], $_POST, [
            'can_change_status' => false,
            'can_change_password' => false,
        ]);
        $messageType = 'success';
        $message = 'Profile updated successfully.';
        $member = Auth::member() ?: $member;
    } catch (Throwable $error) {
        $messageType = 'warning';
        $message = $error->getMessage();
    }
}

$totalStmt = $pdo->prepare('SELECT COALESCE(SUM(Amount), 0) AS Total FROM contributions WHERE MemberID = :member_id');
$totalStmt->execute([':member_id' => $member['MemberID']]);
$total = (float) $totalStmt->fetchColumn();

$recentStmt = $pdo->prepare('SELECT * FROM contributions WHERE MemberID = :member_id ORDER BY COALESCE(TranTime, CreatedAt) DESC LIMIT 8');
$recentStmt->execute([':member_id' => $member['MemberID']]);
$recentTransactions = $recentStmt->fetchAll();

$monthlyStmt = $pdo->prepare(
    "SELECT DATE_FORMAT(COALESCE(TranTime, CreatedAt), '%Y-%m') AS Month, SUM(Amount) AS Total, COUNT(*) AS Count
     FROM contributions
     WHERE MemberID = :member_id
     GROUP BY DATE_FORMAT(COALESCE(TranTime, CreatedAt), '%Y-%m')
     ORDER BY Month DESC
     LIMIT 12"
);
$monthlyStmt->execute([':member_id' => $member['MemberID']]);
$monthly = $monthlyStmt->fetchAll();

$maxMonthly = 0;
foreach ($monthly as $row) {
    $maxMonthly = max($maxMonthly, (float) $row['Total']);
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard | Mashirikiano SACCO</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/logo.png">
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/css/main.css" rel="stylesheet">
  <style>
    body { background: #f4f7fb; }
    .portal-header { background: #0b3b66; color: #fff; }
    .metric { border: 0; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
    .bar { height: 10px; border-radius: 999px; background: #e8eef5; overflow: hidden; }
    .bar span { display: block; height: 100%; background: #f4a621; }
  </style>
</head>
<body>
  <header class="portal-header py-3">
    <div class="container d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div class="d-flex align-items-center gap-3">
        <img src="../assets/img/logo.png" alt="Mashirikiano SACCO" width="52">
        <div>
          <div class="fw-bold">Mashirikiano SACCO</div>
          <div class="small opacity-75">Member Dashboard</div>
        </div>
      </div>
      <a class="btn btn-outline-light" href="../auth/logout.php">Logout</a>
    </div>
  </header>

  <main class="container py-4">
    <?php if ($message): ?>
      <div class="alert alert-<?= e($messageType) ?>" role="alert"><?= e($message) ?></div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
      <div class="col-md-4">
        <div class="card metric h-100">
          <div class="card-body">
            <div class="text-muted small">Total Contributions</div>
            <div class="display-6 fw-bold">KES <?= number_format($total, 2) ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card metric h-100">
          <div class="card-body">
            <div class="text-muted small">Member Status</div>
            <div class="h3 fw-bold mb-0"><?= htmlspecialchars($member['Status'], ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card metric h-100">
          <div class="card-body">
            <div class="text-muted small">Membership ID</div>
            <div class="h3 fw-bold mb-0"><?= htmlspecialchars($member['MemberID'], ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <section class="col-lg-4">
        <div class="card metric h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
              <h2 class="h5 fw-bold mb-0">Profile</h2>
              <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editProfile">Edit Profile</button>
            </div>
            <dl class="mb-0">
              <dt>Name</dt>
              <dd><?= e($member['FirstName'] . ' ' . $member['LastName']) ?></dd>
              <dt>Membership ID</dt>
              <dd><?= e($member['MemberID']) ?></dd>
              <dt>National ID</dt>
              <dd><?= e($member['NationalID']) ?></dd>
              <dt>Phone</dt>
              <dd><?= e($member['PrimaryNumber']) ?></dd>
              <dt>Email</dt>
              <dd><?= e($member['Email'] ?: 'Not provided') ?></dd>
            </dl>
          </div>
        </div>
      </section>
      <!-- <section class="col-lg-8">
        <div class="card metric h-100">
          <div class="card-body">
            <h2 class="h5 fw-bold mb-3">Monthly Contribution Summary</h2>
            <?php if (!$monthly): ?>
              <p class="text-muted mb-0">No contribution records yet.</p>
            <?php endif; ?>
            <?php foreach ($monthly as $row): ?>
              <?php $width = $maxMonthly > 0 ? ((float) $row['Total'] / $maxMonthly) * 100 : 0; ?>
              <div class="mb-3">
                <div class="d-flex justify-content-between small mb-1">
                  <span><?= htmlspecialchars($row['Month'], ENT_QUOTES, 'UTF-8') ?></span>
                  <strong>KES <?= number_format((float) $row['Total'], 2) ?></strong>
                </div>
                <div class="bar"><span style="width: <?= (int) $width ?>%"></span></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section> -->
    </div>

    <section class="card metric mt-4">
      <div class="card-body">
        <h2 class="h5 fw-bold mb-3">Recent Contributions</h2>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Transaction</th>
                <th>Date</th>
                <th>Phone</th>
                <th class="text-end">Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentTransactions as $transaction): ?>
                <tr>
                  <td><?= htmlspecialchars($transaction['TranID'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($transaction['TranTime'] ?: $transaction['CreatedAt'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($transaction['MSISDN'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="text-end">KES <?= number_format((float) $transaction['Amount'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$recentTransactions): ?>
                <tr><td colspan="4" class="text-muted">No contributions found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>

  <div class="modal fade" id="editProfile" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <form method="post" onsubmit="return confirm('Save these profile changes to the database?');">
          <input type="hidden" name="action" value="update_profile">
          <div class="modal-header">
            <div>
              <h3 class="modal-title h5">Edit Profile</h3>
              <div class="small text-muted">Membership ID, status, and password are protected.</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Membership ID</label>
                <input class="form-control" value="<?= e($member['MemberID']) ?>" disabled>
              </div>
              <div class="col-md-6">
                <label class="form-label">Status</label>
                <input class="form-control" value="<?= e($member['Status']) ?>" disabled>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="first_name">FirstName</label>
                <input class="form-control" id="first_name" name="first_name" value="<?= e($member['FirstName']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="last_name">LastName</label>
                <input class="form-control" id="last_name" name="last_name" value="<?= e($member['LastName']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="national_id">NationalID</label>
                <input class="form-control" id="national_id" name="national_id" value="<?= e($member['NationalID']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="phone">PrimaryNumber</label>
                <input class="form-control" id="phone" name="phone" value="<?= e($member['PrimaryNumber']) ?>" required>
              </div>
              <div class="col-12">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" name="email" type="email" value="<?= e($member['Email']) ?>">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Profile</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
