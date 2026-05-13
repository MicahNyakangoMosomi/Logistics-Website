<?php

require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/MemberService.php';

Auth::requireAdmin();

$message = '';
$messageType = 'info';
$createdMember = null;
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

try {
    $pdo = Database::connection();
    $pdo->query('SELECT 1 FROM members LIMIT 1');
    $pdo->query('SELECT 1 FROM contributions LIMIT 1');
} catch (Throwable $error) {
    error_log('Admin dashboard setup error: ' . $error->getMessage());
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Admin Setup Required | Mashirikiano SACCO</title>
      <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body style="background:#f3f6fa;">
      <main class="container py-5">
        <section class="card border-0 shadow-sm">
          <div class="card-body p-4">
            <h1 class="h4 fw-bold">Admin setup is not complete</h1>
            <p class="text-muted">The admin login worked, but the dashboard cannot load because the database schema is missing or not fully migrated.</p>
            <div class="alert alert-warning">
              <?= htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <p>Run the migration in phpMyAdmin, then reload this page:</p>
            <pre class="bg-light p-3 rounded">database/sacco_management_migration.sql</pre>
            <p class="mb-0">If the migration keeps failing, open <code>ADMIN_LOGIN_SETUP.md</code> and run the required table SQL manually.</p>
          </div>
        </section>
      </main>
    </body>
    </html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $createdMember = MemberService::create($_POST);
            $messageType = 'success';
            $message = 'Member created successfully. MemberID: ' . $createdMember['MemberID'];
        } elseif ($action === 'update') {
            MemberService::update((string)($_POST['member_id'] ?? ''), $_POST);
            $messageType = 'success';
            $message = 'Member updated successfully.';
        }
    } catch (Throwable $error) {
        $messageType = 'warning';
        $message = $error->getMessage();
    }
}

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(m.MemberID LIKE :search OR m.NationalID LIKE :search OR m.PrimaryNumber LIKE :search OR CONCAT(m.FirstName, " ", m.LastName) LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if ($statusFilter !== '' && in_array($statusFilter, MemberService::STATUSES, true)) {
    $where[] = 'm.Status = :status';
    $params[':status'] = $statusFilter;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare(
    "SELECT m.*, COALESCE(totals.TotalContributions, 0) AS TotalContributions, COALESCE(totals.ContributionCount, 0) AS ContributionCount
     FROM members m
     LEFT JOIN (
        SELECT MemberID, SUM(Amount) AS TotalContributions, COUNT(ContributionID) AS ContributionCount
        FROM contributions
        WHERE MemberID IS NOT NULL
        GROUP BY MemberID
     ) totals ON totals.MemberID = m.MemberID
     {$whereSql}
     ORDER BY m.CreatedAt DESC"
);
$stmt->execute($params);
$members = $stmt->fetchAll();

$stats = $pdo->query(
    "SELECT
        COUNT(*) AS TotalMembers,
        SUM(CASE WHEN Status = 'Active' THEN 1 ELSE 0 END) AS ActiveMembers,
        SUM(CASE WHEN Status = 'Suspended' THEN 1 ELSE 0 END) AS SuspendedMembers
     FROM members"
)->fetch();

$contributionStats = $pdo->query(
    'SELECT COALESCE(SUM(Amount), 0) AS TotalContributions, COUNT(*) AS ContributionCount FROM contributions'
)->fetch();

$pendingContributions = $pdo->query(
    'SELECT NationalID, FirstName, LastName, MSISDN, COUNT(*) AS ContributionCount, SUM(Amount) AS TotalAmount
     FROM contributions
     WHERE MemberID IS NULL
     GROUP BY NationalID, FirstName, LastName, MSISDN
     ORDER BY MAX(CreatedAt) DESC'
)->fetchAll();

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
  <title>Admin Dashboard | Mashirikiano SACCO</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/logo.png">
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #f3f6fa; color: #1c2938; }
    .admin-header { background: #0b3b66; color: #fff; }
    .admin-shell { max-width: 1440px; }
    .panel, .metric { border: 0; border-radius: 8px; box-shadow: 0 10px 28px rgba(13, 38, 67, .08); }
    .metric-icon { width: 42px; height: 42px; border-radius: 8px; display: grid; place-items: center; background: #e9f2fb; color: #0b3b66; }
    .table thead th { color: #617083; font-size: .78rem; text-transform: uppercase; letter-spacing: .02em; }
    .table td, .table th { vertical-align: middle; }
    .member-id { font-weight: 700; color: #0b3b66; }
    .search-row .form-control, .search-row .form-select { min-height: 44px; }
    @media (max-width: 767px) { .table { min-width: 980px; } }
  </style>
</head>
<body>
  <header class="admin-header py-3">
    <div class="container-fluid admin-shell d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div class="d-flex align-items-center gap-3">
        <img src="../assets/img/logo.png" alt="Mashirikiano SACCO" width="48">
        <div>
          <div class="fw-bold">Mashirikiano SACCO Admin</div>
          <div class="small opacity-75">Member management and contributions</div>
        </div>
      </div>
      <nav class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-light" href="members.php">Members</a>
        <a class="btn btn-sm btn-outline-light" href="reports.php">Reports</a>
        <a class="btn btn-sm btn-outline-light" href="../auth/admin_logout.php">Logout</a>
      </nav>
    </div>
  </header>

  <main class="container-fluid admin-shell py-4">
    <?php if ($message): ?>
      <div class="alert alert-<?= e($messageType) ?>" role="alert"><?= e($message) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card metric h-100"><div class="card-body d-flex justify-content-between gap-3"><div><div class="text-muted small">Total Members</div><div class="h3 fw-bold mb-0"><?= (int)$stats['TotalMembers'] ?></div></div><div class="metric-icon"><i class="bi bi-people"></i></div></div></div>
      </div>
      <div class="col-md-3">
        <div class="card metric h-100"><div class="card-body d-flex justify-content-between gap-3"><div><div class="text-muted small">Active Members</div><div class="h3 fw-bold mb-0"><?= (int)$stats['ActiveMembers'] ?></div></div><div class="metric-icon"><i class="bi bi-person-check"></i></div></div></div>
      </div>
      <div class="col-md-3">
        <div class="card metric h-100"><div class="card-body d-flex justify-content-between gap-3"><div><div class="text-muted small">Total Contributions</div><div class="h3 fw-bold mb-0">KES <?= number_format((float)$contributionStats['TotalContributions'], 2) ?></div></div><div class="metric-icon"><i class="bi bi-cash-stack"></i></div></div></div>
      </div>
      <div class="col-md-3">
        <div class="card metric h-100"><div class="card-body d-flex justify-content-between gap-3"><div><div class="text-muted small">Unmatched Contributions</div><div class="h3 fw-bold mb-0"><?= count($pendingContributions) ?></div></div><div class="metric-icon"><i class="bi bi-exclamation-circle"></i></div></div></div>
      </div>
    </div>

    <section class="card panel mb-4">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div>
            <h1 class="h4 fw-bold mb-1">Internal Access Registration</h1>
            <div class="text-muted small">Only authenticated staff/admins can create SACCO members.</div>
          </div>
        </div>
        <form method="post" class="row g-3">
          <input type="hidden" name="action" value="create">
          <div class="col-md-3">
            <label class="form-label" for="first_name">FirstName</label>
            <input class="form-control" id="first_name" name="first_name" required>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="last_name">LastName</label>
            <input class="form-control" id="last_name" name="last_name" required>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="phone">PrimaryNumber</label>
            <input class="form-control" id="phone" name="phone" required>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="national_id">NationalID</label>
            <input class="form-control" id="national_id" name="national_id" required>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="email">Email <span class="text-muted">(optional)</span></label>
            <input class="form-control" id="email" name="email" type="email">
          </div>
          <div class="col-md-4">
            <label class="form-label" for="password">Password</label>
            <input class="form-control" id="password" name="password" type="password" required autocomplete="new-password">
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-person-plus me-1"></i> Register Member</button>
          </div>
        </form>
      </div>
    </section>

    <section class="card panel mb-4">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <h2 class="h5 fw-bold mb-0">Registered Members</h2>
          <form method="get" class="row g-2 search-row flex-grow-1 justify-content-end">
            <div class="col-lg-5">
              <input class="form-control" name="search" value="<?= e($search) ?>" placeholder="Search MemberID, name, phone, or NationalID">
            </div>
            <div class="col-lg-2">
              <select class="form-select" name="status">
                <option value="">All statuses</option>
                <?php foreach (MemberService::STATUSES as $status): ?>
                  <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-lg-2">
              <button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-search me-1"></i> Search</button>
            </div>
          </form>
        </div>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>MemberID</th><th>Full Name</th><th>Phone</th><th>Email</th><th>NationalID</th><th>Status</th><th>CreatedAt</th><th class="text-end">Total Contributions</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($members as $member): ?>
                <tr>
                  <td class="member-id"><?= e($member['MemberID']) ?></td>
                  <td><?= e($member['FirstName'] . ' ' . $member['LastName']) ?></td>
                  <td><?= e($member['PrimaryNumber']) ?></td>
                  <td><?= e($member['Email'] ?: 'Not provided') ?></td>
                  <td><?= e($member['NationalID']) ?></td>
                  <td><span class="badge text-bg-<?= $member['Status'] === 'Active' ? 'success' : ($member['Status'] === 'Suspended' ? 'danger' : 'secondary') ?>"><?= e($member['Status']) ?></span></td>
                  <td><?= e($member['CreatedAt']) ?></td>
                  <td class="text-end">KES <?= number_format((float)$member['TotalContributions'], 2) ?></td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editMember<?= e($member['MemberID']) ?>">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$members): ?>
                <tr><td colspan="9" class="text-muted">No members match your search.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section class="card panel">
      <div class="card-body">
        <h2 class="h5 fw-bold mb-3">Unmatched Contribution Summaries</h2>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>NationalID</th><th>Name</th><th>Phone</th><th>Records</th><th class="text-end">Total</th></tr></thead>
            <tbody>
              <?php foreach ($pendingContributions as $row): ?>
                <tr>
                  <td><?= e($row['NationalID']) ?></td>
                  <td><?= e(trim($row['FirstName'] . ' ' . $row['LastName'])) ?></td>
                  <td><?= e($row['MSISDN']) ?></td>
                  <td><?= (int)$row['ContributionCount'] ?></td>
                  <td class="text-end">KES <?= number_format((float)$row['TotalAmount'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$pendingContributions): ?>
                <tr><td colspan="5" class="text-muted">No unmatched contributions.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>

  <?php foreach ($members as $member): ?>
    <div class="modal fade" id="editMember<?= e($member['MemberID']) ?>" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="member_id" value="<?= e($member['MemberID']) ?>">
            <div class="modal-header">
              <div>
                <h3 class="modal-title h5">Edit <?= e($member['MemberID']) ?></h3>
                <div class="small text-muted">MemberID and CreatedAt are protected.</div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">MemberID</label>
                  <input class="form-control" value="<?= e($member['MemberID']) ?>" disabled>
                </div>
                <div class="col-md-6">
                  <label class="form-label">CreatedAt</label>
                  <input class="form-control" value="<?= e($member['CreatedAt']) ?>" disabled>
                </div>
                <div class="col-md-6">
                  <label class="form-label">FirstName</label>
                  <input class="form-control" name="first_name" value="<?= e($member['FirstName']) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">LastName</label>
                  <input class="form-control" name="last_name" value="<?= e($member['LastName']) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">PrimaryNumber</label>
                  <input class="form-control" name="phone" value="<?= e($member['PrimaryNumber']) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">NationalID</label>
                  <input class="form-control" name="national_id" value="<?= e($member['NationalID']) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input class="form-control" name="email" type="email" value="<?= e($member['Email']) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Status</label>
                  <select class="form-select" name="status">
                    <?php foreach (MemberService::STATUSES as $status): ?>
                      <option value="<?= e($status) ?>" <?= $member['Status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12">
                  <label class="form-label">New Password</label>
                  <input class="form-control" name="password" type="password" autocomplete="new-password" placeholder="Leave blank to keep the current password">
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
