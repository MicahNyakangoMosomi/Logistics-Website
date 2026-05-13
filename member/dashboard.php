<?php

require_once __DIR__ . '/../classes/Auth.php';

$member = Auth::requireMember();
$pdo = Database::connection();

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
            <h2 class="h5 fw-bold mb-3">Profile</h2>
            <dl class="mb-0">
              <dt>Name</dt>
              <dd><?= htmlspecialchars($member['FirstName'] . ' ' . $member['LastName'], ENT_QUOTES, 'UTF-8') ?></dd>
              <dt>Membership ID</dt>
              <dd><?= htmlspecialchars($member['MemberID'], ENT_QUOTES, 'UTF-8') ?></dd>
              <dt>National ID</dt>
              <dd><?= htmlspecialchars($member['NationalID'], ENT_QUOTES, 'UTF-8') ?></dd>
              <dt>Phone</dt>
              <dd><?= htmlspecialchars($member['PrimaryNumber'], ENT_QUOTES, 'UTF-8') ?></dd>
              <dt>Email</dt>
              <dd><?= htmlspecialchars($member['Email'] ?: 'Not provided', ENT_QUOTES, 'UTF-8') ?></dd>
            </dl>
          </div>
        </div>
      </section>
      <section class="col-lg-8">
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
      </section>
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
</body>
</html>
