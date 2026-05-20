<?php

require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/MemberService.php';

Auth::requireAdmin();

$message = '';
$messageType = 'info';
$search = trim($_GET['search'] ?? '');
$allowedLimits = [5, 10, 25, 50];
$rowLimit = (int)($_GET['limit'] ?? 10);
if (!in_array($rowLimit, $allowedLimits, true)) {
    $rowLimit = 10;
}
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$adminRole = Auth::adminRole();
$isAdmin = $adminRole === 'admin';

try {
    $pdo = Database::connection();
    $pdo->query('SELECT 1 FROM members LIMIT 1');
    $pdo->query('SELECT 1 FROM deposits LIMIT 1');
    $pdo->query('SELECT 1 FROM member_transactions LIMIT 1');
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
            <pre class="bg-light p-3 rounded">database/financial_workflow_migration.sql</pre>
            <p class="mb-0">If the migration keeps failing, open the migration file and run the required table SQL manually.</p>
          </div>
        </section>
      </main>
    </body>
    </html>
    <?php
    exit;
}


[$members, $memberContributions, $totalMembers, $totalPages, $currentPage] = loadMembers($pdo, $search, $rowLimit, $currentPage);

if (($_GET['ajax'] ?? '') === 'members') {
    header('Content-Type: application/json');
    echo json_encode([
        'rows' => renderMemberRows($members),
        'modals' => renderMemberModals($members, $memberContributions),
        'count' => count($members),
        'total' => $totalMembers,
        'page' => $currentPage,
        'totalPages' => $totalPages,
        'pagination' => renderPagination($currentPage, $totalPages),
        'summary' => memberResultSummary(count($members), $totalMembers, $currentPage, $rowLimit),
    ]);
    exit;
}

$stats = $pdo->query(
    "SELECT
        COUNT(*) AS TotalMembers,
        SUM(CASE WHEN Status = 'Active' THEN 1 ELSE 0 END) AS ActiveMembers,
        SUM(CASE WHEN Status = 'Suspended' THEN 1 ELSE 0 END) AS SuspendedMembers,
        SUM(CASE WHEN Status = 'Pending' THEN 1 ELSE 0 END) AS PendingMembers
     FROM members"
)->fetch();

$contributionStats = $pdo->query(
    "SELECT COALESCE(SUM(Amount), 0) AS TotalContributions, COUNT(*) AS ContributionCount
     FROM member_transactions
     WHERE TransactionType = 'contribution'"
)->fetch();

$depositStats = $pdo->query(
    'SELECT COALESCE(SUM(PaidAmount), 0) AS TotalDeposits, COALESCE(SUM(Balance), 0) AS TotalDepositBalance
     FROM deposits'
)->fetch();

$pendingContributions = $pdo->query(
    'SELECT NationalID, FirstName, LastName, MSISDN, COUNT(*) AS ContributionCount, SUM(Amount) AS TotalAmount
     FROM member_transactions
     WHERE MemberID IS NULL AND TransactionType = "contribution"
     GROUP BY NationalID, FirstName, LastName, MSISDN
     ORDER BY MAX(CreatedAt) DESC'
)->fetchAll();

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function loadMembers(PDO $pdo, string $search, int $limit, int $page): array
{
    $whereSql = '';
    $params = [];

    if ($search !== '') {
        $whereSql = "WHERE (m.MemberID LIKE :search OR m.FirstName LIKE :search OR m.LastName LIKE :search OR CONCAT(m.FirstName, ' ', m.LastName) LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM members m {$whereSql}");
    $countStmt->execute($params);
    $totalMembers = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalMembers / $limit));
    $page = min(max(1, $page), $totalPages);
    $offset = ($page - 1) * $limit;

    $stmt = $pdo->prepare(
        "SELECT m.*, d.RequiredAmount, d.PaidAmount, d.Balance, d.Status AS DepositStatus,
            COALESCE(totals.TotalContributions, 0) AS TotalContributions,
            COALESCE(totals.ContributionCount, 0) AS ContributionCount
         FROM members m
         LEFT JOIN deposits d ON d.MemberID = m.MemberID
         LEFT JOIN (
            SELECT MemberID, SUM(Amount) AS TotalContributions, COUNT(TransactionID) AS ContributionCount
            FROM member_transactions
            WHERE MemberID IS NOT NULL AND TransactionType = 'contribution'
            GROUP BY MemberID
         ) totals ON totals.MemberID = m.MemberID
         {$whereSql}
         ORDER BY m.FirstName ASC, m.LastName ASC, m.MemberID ASC
         LIMIT {$limit} OFFSET {$offset}"
    );
    $stmt->execute($params);
    $members = $stmt->fetchAll();
    $memberContributions = [];

    if ($members) {
        $memberIds = array_column($members, 'MemberID');
        $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
        $contributionStmt = $pdo->prepare(
            "SELECT *
             FROM member_transactions
             WHERE MemberID IN ({$placeholders}) AND TransactionType = 'contribution'
             ORDER BY COALESCE(TranTime, CreatedAt) DESC"
        );
        $contributionStmt->execute($memberIds);

        foreach ($contributionStmt->fetchAll() as $contribution) {
            $memberId = (string)$contribution['MemberID'];
            if (!isset($memberContributions[$memberId])) {
                $memberContributions[$memberId] = [];
            }
            $memberContributions[$memberId][] = $contribution;
        }
    }

    return [$members, $memberContributions, $totalMembers, $totalPages, $page];
}

function renderMemberRows(array $members): string
{
    ob_start();
    foreach ($members as $member): ?>
      <tr>
        <td class="member-id"><?= e($member['MemberID']) ?></td>
        <td><?= e($member['FirstName'] . ' ' . $member['LastName']) ?></td>
        <td><?= e($member['PrimaryNumber']) ?></td>
        <td><?= e($member['Email'] ?: 'Not provided') ?></td>
        <td><?= e($member['NationalID']) ?></td>
        <td><span class="badge text-bg-<?= $member['Status'] === 'Active' ? 'success' : ($member['Status'] === 'Suspended' ? 'danger' : 'secondary') ?>"><?= e($member['Status']) ?></span></td>
        <td>KES <?= number_format((float)($member['Balance'] ?? 0), 2) ?></td>
        <td><?= e($member['CreatedAt']) ?></td>
        <td class="text-end">KES <?= number_format((float)$member['TotalContributions'], 2) ?></td>
        <td class="actions-cell text-end">
          <a href="edit_member.php?id=<?= urlencode($member['MemberID']) ?>">Edit</a>
          <a href="#" data-bs-toggle="modal" data-bs-target="#memberContributions<?= e($member['MemberID']) ?>">Contributions</a>
        </td>
      </tr>
    <?php endforeach;

    if (!$members): ?>
      <tr><td colspan="10" class="text-muted">No members match that name or MemberID.</td></tr>
    <?php endif;

    return trim((string)ob_get_clean());
}

function renderMemberModals(array $members, array $memberContributions): string
{
    ob_start();
    foreach ($members as $member): ?>
      <div class="modal fade" id="memberContributions<?= e($member['MemberID']) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header">
              <div>
                <h3 class="modal-title h5">Contributions for <?= e($member['MemberID']) ?></h3>
                <div class="small text-muted"><?= e($member['FirstName'] . ' ' . $member['LastName']) ?></div>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
                <div>
                  <div class="text-muted small">Total Contributions</div>
                  <div class="h4 fw-bold">KES <?= number_format((float)$member['TotalContributions'], 2) ?></div>
                </div>
                <div>
                  <div class="text-muted small">Contribution Records</div>
                  <div class="h4 fw-bold"><?= (int)$member['ContributionCount'] ?></div>
                </div>
              </div>
              <div class="table-responsive">
                <table class="table align-middle">
                  <thead><tr><th>TranID</th><th>Date</th><th>Phone</th><th class="text-end">Amount</th></tr></thead>
                  <tbody>
                    <?php foreach (($memberContributions[$member['MemberID']] ?? []) as $contribution): ?>
                      <tr>
                        <td><?= e($contribution['TranID']) ?></td>
                        <td><?= e($contribution['TranTime'] ?: $contribution['CreatedAt']) ?></td>
                        <td><?= e($contribution['MSISDN']) ?></td>
                        <td class="text-end">KES <?= number_format((float)$contribution['Amount'], 2) ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (empty($memberContributions[$member['MemberID']])): ?>
                      <tr><td colspan="4" class="text-muted">No contributions found for this member.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach;

    return trim((string)ob_get_clean());
}

function memberResultSummary(int $visibleCount, int $totalMembers, int $page, int $limit): string
{
    if ($totalMembers === 0) {
        return '0 members found';
    }

    $start = (($page - 1) * $limit) + 1;
    $end = $start + $visibleCount - 1;

    return "Showing {$start}-{$end} of {$totalMembers} member" . ($totalMembers === 1 ? '' : 's');
}

function renderPagination(int $currentPage, int $totalPages): string
{
    if ($totalPages <= 1) {
        return '';
    }

    ob_start(); ?>
      <nav aria-label="Members pages">
        <ul class="pagination pagination-sm mb-0">
          <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="#" data-page="<?= max(1, $currentPage - 1) ?>">Previous</a>
          </li>
          <?php for ($page = 1; $page <= $totalPages; $page++): ?>
            <li class="page-item <?= $page === $currentPage ? 'active' : '' ?>">
              <a class="page-link" href="#" data-page="<?= $page ?>"><?= $page ?></a>
            </li>
          <?php endfor; ?>
          <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="#" data-page="<?= min($totalPages, $currentPage + 1) ?>">Next</a>
          </li>
        </ul>
      </nav>
    <?php return trim((string)ob_get_clean());
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
  <style>
    body { background: #f3f6fa; color: #1c2938; }
    .admin-header { background: #0b3b66; color: #fff; }
    .admin-shell { max-width: 1440px; }
    .panel, .metric { border: 0; border-radius: 8px; box-shadow: 0 10px 28px rgba(13, 38, 67, .08); }
    .table thead th { color: #617083; font-size: .78rem; text-transform: uppercase; letter-spacing: .02em; }
    .table td, .table th { vertical-align: middle; }
    .member-id { font-weight: 700; color: #0b3b66; }
    .search-row .form-control, .search-row .form-select { min-height: 44px; }
    .actions-cell { white-space: nowrap; min-width: 132px; }
    .actions-cell a { color: #0b5ed7; font-weight: 600; text-decoration: none; margin-right: 12px; }
    .actions-cell a:hover { text-decoration: underline; }
    @media (max-width: 767px) { .table { min-width: 1120px; } }
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
        <a class="btn btn-sm btn-outline-light" href="register_member.php">Register Member</a>
        <a class="btn btn-sm btn-outline-light" href="manage_jobs.php">Manage Jobs</a>
        <a class="btn btn-sm btn-outline-light" href="reports.php">Reports</a>
        <a class="btn btn-sm btn-light" href="members.php">Members</a>
        <a class="btn btn-sm btn-outline-light" href="settings.php">Settings</a>
        <a class="btn btn-sm btn-outline-light" href="../auth/admin_logout.php">Logout</a>
      </nav>
    </div>
  </header>

  <main class="container-fluid admin-shell py-4">
    <?php if ($message): ?>
      <div class="alert alert-<?= e($messageType) ?>" role="alert"><?= e($message) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
      <div class="col-md-4 col-xl">
        <div class="card metric h-100"><div class="card-body"><div class="text-muted small">Total Members</div><div class="h3 fw-bold mb-0"><?= (int)$stats['TotalMembers'] ?></div></div></div>
      </div>
      <div class="col-md-4 col-xl">
        <div class="card metric h-100"><div class="card-body"><div class="text-muted small">Active Members</div><div class="h3 fw-bold mb-0"><?= (int)$stats['ActiveMembers'] ?></div></div></div>
      </div>
      <div class="col-md-4 col-xl">
        <div class="card metric h-100"><div class="card-body"><div class="text-muted small">Pending Members</div><div class="h3 fw-bold mb-0"><?= (int)$stats['PendingMembers'] ?></div></div></div>
      </div>
      <div class="col-md-4 col-xl">
        <div class="card metric h-100"><div class="card-body"><div class="text-muted small">Total Contributions</div><div class="h3 fw-bold mb-0">KES <?= number_format((float)$contributionStats['TotalContributions'], 2) ?></div></div></div>
      </div>
      <div class="col-md-4 col-xl">
        <div class="card metric h-100"><div class="card-body"><div class="text-muted small">Total Deposits</div><div class="h3 fw-bold mb-0">KES <?= number_format((float)$depositStats['TotalDeposits'], 2) ?></div></div></div>
      </div>
    </div>

    <section class="card panel mb-4">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <h2 class="h5 fw-bold mb-0">Registered Members</h2>
          <form method="get" id="memberSearchForm" class="row g-2 search-row flex-grow-1 justify-content-end">
            <div class="col-lg-5">
              <input class="form-control" id="memberSearch" name="search" value="<?= e($search) ?>" placeholder="Search by MemberID or name" autocomplete="off">
            </div>
            <div class="col-lg-2">
              <select class="form-select" id="rowLimit" name="limit">
                <?php foreach ($allowedLimits as $limitOption): ?>
                  <option value="<?= (int)$limitOption ?>" <?= $rowLimit === $limitOption ? 'selected' : '' ?>><?= (int)$limitOption ?> rows</option>
                <?php endforeach; ?>
              </select>
            </div>
          </form>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
          <div class="text-muted small" id="memberResultSummary"><?= e(memberResultSummary(count($members), $totalMembers, $currentPage, $rowLimit)) ?></div>
          <div id="memberPagination">
            <?= renderPagination($currentPage, $totalPages) ?>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr><th>MemberID</th><th>Full Name</th><th>Phone</th><th>Email</th><th>NationalID</th><th>Status</th><th>Deposit Balance</th><th>CreatedAt</th><th class="text-end">Total Contributions</th><th class="text-end">Actions</th></tr></thead>
            <tbody id="membersTableBody">
              <?= renderMemberRows($members) ?>
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

  <div id="memberContributionModals">
    <?= renderMemberModals($members, $memberContributions) ?>
  </div>

  <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script>
    const memberSearchForm = document.getElementById('memberSearchForm');
    const memberSearch = document.getElementById('memberSearch');
    const rowLimit = document.getElementById('rowLimit');
    const membersTableBody = document.getElementById('membersTableBody');
    const memberContributionModals = document.getElementById('memberContributionModals');
    const memberResultSummary = document.getElementById('memberResultSummary');
    const memberPagination = document.getElementById('memberPagination');
    let searchTimer = null;
    let activeController = null;
    let currentPage = <?= (int)$currentPage ?>;

    function updateMembers(page = currentPage) {
      if (activeController) {
        activeController.abort();
      }

      activeController = new AbortController();
      currentPage = Math.max(1, parseInt(page, 10) || 1);
      const params = new URLSearchParams(window.location.search);
      params.set('ajax', 'members');
      params.set('search', memberSearch.value);
      params.set('limit', rowLimit.value);
      params.set('page', currentPage);

      fetch(`members.php?${params.toString()}`, {
        credentials: 'same-origin',
        signal: activeController.signal
      })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('Member search failed');
          }
          return response.json();
        })
        .then(function (data) {
          membersTableBody.innerHTML = data.rows;
          memberContributionModals.innerHTML = data.modals;
          memberResultSummary.textContent = data.summary;
          memberPagination.innerHTML = data.pagination;
          currentPage = data.page;

          const url = new URL(window.location.href);
          if (memberSearch.value) {
            url.searchParams.set('search', memberSearch.value);
          } else {
            url.searchParams.delete('search');
          }
          url.searchParams.set('limit', rowLimit.value);
          url.searchParams.set('page', currentPage);
          window.history.replaceState({}, '', url);
        })
        .catch(function (error) {
          if (error.name !== 'AbortError') {
            membersTableBody.innerHTML = '<tr><td colspan="10" class="text-danger">Unable to load members right now.</td></tr>';
          }
        });
    }

    memberSearchForm.addEventListener('submit', function (event) {
      event.preventDefault();
      updateMembers();
    });

    memberSearch.addEventListener('input', function () {
      window.clearTimeout(searchTimer);
      searchTimer = window.setTimeout(function () {
        updateMembers(1);
      }, 250);
    });

    rowLimit.addEventListener('change', function () {
      updateMembers(1);
    });

    memberPagination.addEventListener('click', function (event) {
      const link = event.target.closest('[data-page]');
      if (!link || link.parentElement.classList.contains('disabled') || link.parentElement.classList.contains('active')) {
        return;
      }

      event.preventDefault();
      updateMembers(link.dataset.page);
    });
  </script>
</body>
</html>
