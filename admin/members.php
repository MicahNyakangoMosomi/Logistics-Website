<?php

require_once __DIR__ . '/../classes/Auth.php';

Auth::requireAdmin();

$pdo = Database::connection();
$message = '';
$generatedPassword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nationalId = trim($_POST['national_id'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = $_POST['status'] ?? 'Active';
    $allowedStatuses = ['Pending', 'Active', 'Suspended'];

    if ($nationalId === '' || $firstName === '' || $lastName === '' || $phone === '') {
        $message = 'National ID, first name, last name, and phone are required.';
    } elseif (!in_array($status, $allowedStatuses, true)) {
        $message = 'Invalid member status.';
    } else {
        $generatedPassword = Auth::generatePassword();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO members (NationalID, FirstName, LastName, PrimaryNumber, Email, Password, Status)
                 VALUES (:national_id, :first_name, :last_name, :phone, :email, :password, :status)'
            );
            $stmt->execute([
                ':national_id' => $nationalId,
                ':first_name' => $firstName,
                ':last_name' => $lastName,
                ':phone' => $phone,
                ':email' => $email ?: null,
                ':password' => $generatedPassword,
                ':status' => $status,
            ]);

            $memberId = (int) $pdo->lastInsertId();
            $linkStmt = $pdo->prepare('UPDATE transactions SET MemberID = :member_id WHERE NationalID = :national_id AND MemberID IS NULL');
            $linkStmt->execute([
                ':member_id' => $memberId,
                ':national_id' => $nationalId,
            ]);

            $pdo->commit();
            $message = 'Member created. Password: ' . $generatedPassword;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = 'Could not create member: ' . $error->getMessage();
            $generatedPassword = '';
        }
    }
}

$members = $pdo->query(
    'SELECT m.*, COALESCE(totals.TotalContributions, 0) AS TotalContributions, COALESCE(totals.TransactionCount, 0) AS TransactionCount
     FROM members m
     LEFT JOIN (
        SELECT MemberID, SUM(Amount) AS TotalContributions, COUNT(TranID) AS TransactionCount
        FROM transactions
        WHERE MemberID IS NOT NULL
        GROUP BY MemberID
     ) totals ON totals.MemberID = m.MemberID
     ORDER BY m.CreatedAt DESC'
)->fetchAll();

$pendingTransactions = $pdo->query(
    'SELECT NationalID, FirstName, LastName, MSISDN, COUNT(*) AS TransactionCount, SUM(Amount) AS TotalAmount
     FROM transactions
     WHERE MemberID IS NULL
     GROUP BY NationalID, FirstName, LastName, MSISDN
     ORDER BY MAX(CreatedAt) DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Members | Mashirikiano SACCO Admin</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/logo.png">
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
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
        <a class="btn btn-sm btn-light" href="members.php">Members</a>
        <a class="btn btn-sm btn-outline-light" href="reports.php">Reports</a>
      </nav>
    </div>
  </header>

  <main class="container py-4">
    <?php if ($message): ?>
      <div class="alert <?= $generatedPassword ? 'alert-success' : 'alert-warning' ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <section class="card panel mb-4">
      <div class="card-body">
        <h1 class="h4 fw-bold mb-3">Create Member</h1>
        <form method="post" class="row g-3">
          <div class="col-md-3">
            <label class="form-label" for="national_id">National ID</label>
            <input class="form-control" id="national_id" name="national_id" required>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="first_name">First Name</label>
            <input class="form-control" id="first_name" name="first_name" required>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="last_name">Last Name</label>
            <input class="form-control" id="last_name" name="last_name" required>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="phone">Primary Number</label>
            <input class="form-control" id="phone" name="phone" required>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email">
          </div>
          <div class="col-md-3">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
              <option>Active</option>
              <option>Pending</option>
              <option>Suspended</option>
            </select>
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary w-100" type="submit">Create Member</button>
          </div>
        </form>
      </div>
    </section>

    <section class="card panel mb-4">
      <div class="card-body">
        <h2 class="h5 fw-bold mb-3">Pending Unmatched Transactions</h2>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>National ID</th><th>Name</th><th>Phone</th><th>Transactions</th><th class="text-end">Total</th></tr></thead>
            <tbody>
              <?php foreach ($pendingTransactions as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['NationalID'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars(trim($row['FirstName'] . ' ' . $row['LastName']), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($row['MSISDN'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= (int) $row['TransactionCount'] ?></td>
                  <td class="text-end">KES <?= number_format((float) $row['TotalAmount'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$pendingTransactions): ?>
                <tr><td colspan="5" class="text-muted">No unmatched transactions.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section class="card panel">
      <div class="card-body">
        <h2 class="h5 fw-bold mb-3">Members</h2>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>ID</th><th>National ID</th><th>Name</th><th>Phone</th><th>Status</th><th>Password</th><th class="text-end">Contributions</th></tr></thead>
            <tbody>
              <?php foreach ($members as $member): ?>
                <tr>
                  <td><?= (int) $member['MemberID'] ?></td>
                  <td><?= htmlspecialchars($member['NationalID'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($member['FirstName'] . ' ' . $member['LastName'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($member['PrimaryNumber'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($member['Status'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><code><?= htmlspecialchars($member['Password'], ENT_QUOTES, 'UTF-8') ?></code></td>
                  <td class="text-end">KES <?= number_format((float) $member['TotalContributions'], 2) ?></td>
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
