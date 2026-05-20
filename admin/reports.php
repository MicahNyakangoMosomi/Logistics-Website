<?php

require_once __DIR__ . '/../classes/Auth.php';

Auth::requireAdmin();

$pdo = Database::connection();

$summary = $pdo->query(
     'SELECT
        COALESCE(SUM(Amount), 0) AS TotalContributions,
        COUNT(*) AS ContributionCount,
        COUNT(DISTINCT MemberID) AS ContributingMembers,
        SUM(CASE WHEN MemberID IS NULL THEN 1 ELSE 0 END) AS UnmatchedTransactions
     FROM member_transactions
     WHERE TransactionType = "contribution"'
)->fetch();

$monthly = $pdo->query(
    "SELECT DATE_FORMAT(COALESCE(TranTime, CreatedAt), '%Y-%m') AS Month, SUM(Amount) AS Total, COUNT(*) AS Count
     FROM member_transactions
     WHERE TransactionType = 'contribution'
     GROUP BY DATE_FORMAT(COALESCE(TranTime, CreatedAt), '%Y-%m')
     ORDER BY Month DESC
     LIMIT 12"
)->fetchAll();

$topMembers = $pdo->query(
    'SELECT m.MemberID, m.NationalID, m.FirstName, m.LastName, totals.Total
     FROM (
        SELECT MemberID, SUM(Amount) AS Total
        FROM member_transactions
        WHERE MemberID IS NOT NULL AND TransactionType = "contribution"
        GROUP BY MemberID
     ) totals
     INNER JOIN members m ON m.MemberID = totals.MemberID
     ORDER BY totals.Total DESC
     LIMIT 10'
)->fetchAll();

$recent = $pdo->query(
    'SELECT t.*, m.MemberID AS LinkedMemberID
     FROM member_transactions t
     LEFT JOIN members m ON m.MemberID = t.MemberID
     WHERE t.TransactionType = "contribution"
     ORDER BY COALESCE(t.TranTime, t.CreatedAt) DESC
     LIMIT 25'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reports | Mashirikiano SACCO Admin</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/logo.png">
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f4f7fb; }
    .admin-header { background: #0b3b66; color: #fff; }
    .panel { border: 0; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
  </style>
</head>
<body>
  <header class="admin-header py-3">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div class="fw-bold">Mashirikiano SACCO Admin</div>
      <nav class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-light" href="register_member.php">Register Member</a>
        <a class="btn btn-sm btn-outline-light" href="manage_jobs.php">Manage Jobs</a>
        <a class="btn btn-sm btn-light" href="reports.php">Reports</a>
        <a class="btn btn-sm btn-outline-light" href="members.php">Members</a>
        <a class="btn btn-sm btn-outline-light" href="../auth/admin_logout.php">Logout</a>
      </nav>
    </div>
  </header>

  <main class="container py-4">
    <div class="row g-4 mb-4">
      <div class="col-md-3">
        <div class="card panel"><div class="card-body"><div class="text-muted small">Total Contributions</div><div class="h3 fw-bold">KES <?= number_format((float) $summary['TotalContributions'], 2) ?></div></div></div>
      </div>
      <div class="col-md-3">
        <div class="card panel"><div class="card-body"><div class="text-muted small">Contribution Records</div><div class="h3 fw-bold"><?= (int) $summary['ContributionCount'] ?></div></div></div>
      </div>
      <div class="col-md-3">
        <div class="card panel"><div class="card-body"><div class="text-muted small">Contributing Members</div><div class="h3 fw-bold"><?= (int) $summary['ContributingMembers'] ?></div></div></div>
      </div>
      <div class="col-md-3">
        <div class="card panel"><div class="card-body"><div class="text-muted small">Unmatched</div><div class="h3 fw-bold"><?= (int) $summary['UnmatchedTransactions'] ?></div></div></div>
      </div>
    </div>

    <div class="row g-4 mb-4">
      <section class="col-lg-6">
        <div class="card panel h-100">
          <div class="card-body">
            <h1 class="h5 fw-bold mb-3">Monthly Contributions</h1>
            <div class="table-responsive">
              <table class="table">
                <thead><tr><th>Month</th><th>Records</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                  <?php foreach ($monthly as $row): ?>
                    <tr><td><?= htmlspecialchars($row['Month'], ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) $row['Count'] ?></td><td class="text-end">KES <?= number_format((float) $row['Total'], 2) ?></td></tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
      <section class="col-lg-6">
        <div class="card panel h-100">
          <div class="card-body">
            <h2 class="h5 fw-bold mb-3">Top Contributors</h2>
            <div class="table-responsive">
              <table class="table">
                <thead><tr><th>Member</th><th>National ID</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                  <?php foreach ($topMembers as $row): ?>
                    <tr><td><?= htmlspecialchars($row['FirstName'] . ' ' . $row['LastName'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($row['NationalID'], ENT_QUOTES, 'UTF-8') ?></td><td class="text-end">KES <?= number_format((float) $row['Total'], 2) ?></td></tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
    </div>

    <section class="card panel">
      <div class="card-body">
        <h2 class="h5 fw-bold mb-3">Recent M-Pesa Contributions</h2>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>TranID</th><th>National ID</th><th>Name</th><th>Phone</th><th>MemberID</th><th>Date</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
              <?php foreach ($recent as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['TranID'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($row['NationalID'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars(trim($row['FirstName'] . ' ' . $row['LastName']), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($row['MSISDN'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= $row['MemberID'] ? htmlspecialchars($row['MemberID'], ENT_QUOTES, 'UTF-8') : '<span class="badge text-bg-warning">NULL</span>' ?></td>
                  <td><?= htmlspecialchars($row['TranTime'] ?: $row['CreatedAt'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="text-end">KES <?= number_format((float) $row['Amount'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
