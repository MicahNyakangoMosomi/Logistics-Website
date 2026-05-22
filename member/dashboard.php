<?php

require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/MemberService.php';
require_once __DIR__ . '/../classes/SmsService.php';

$member = Auth::requireMember();
Auth::startSession();
$pdo = Database::connection();
$message = '';
$messageType = 'info';

if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_message_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_message_type']);
}

// Auto-create loan_applications table if not exists (self-healing migration)
try {
    $pdo->query("SELECT 1 FROM `loan_applications` LIMIT 1");
} catch (Throwable $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `loan_applications` (
      `LoanApplicationID` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `MemberID` VARCHAR(40) NOT NULL,
      `LoanType` VARCHAR(100) NOT NULL,
      `Amount` DECIMAL(12,2) NOT NULL,
      `ReturnDate` DATE NOT NULL,
      `Status` ENUM('Pending', 'Approved', 'Not Approved') NOT NULL DEFAULT 'Pending',
      `RejectionReason` VARCHAR(255) NULL,
      `CreatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `UpdatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`LoanApplicationID`),
      KEY `idx_loan_applications_member` (`MemberID`),
      KEY `idx_loan_applications_status` (`Status`),
      CONSTRAINT `fk_loan_applications_member`
        FOREIGN KEY (`MemberID`) REFERENCES `members` (`MemberID`)
        ON UPDATE CASCADE
        ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

$activeTab = $_GET['tab'] ?? 'dashboard';
if (!in_array($activeTab, ['dashboard', 'loan', 'record'])) {
    $activeTab = 'dashboard';
}

// Handle loan application form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_loan'])) {
    try {
        $loanType = trim($_POST['loan_type'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $returnDate = trim($_POST['return_date'] ?? '');

        if (empty($loanType)) {
            throw new Exception("Please select a loan type.");
        }
        if ($amount <= 0) {
            throw new Exception("Please enter a valid loan amount greater than 0.");
        }
        if (empty($returnDate)) {
            throw new Exception("Please select a valid return date.");
        }
        
        $today = date('Y-m-d');
        if ($returnDate <= $today) {
            throw new Exception("Return date must be in the future.");
        }

        // Save to DB
        $stmt = $pdo->prepare("INSERT INTO loan_applications (MemberID, LoanType, Amount, ReturnDate, Status) VALUES (:member_id, :loan_type, :amount, :return_date, 'Pending')");
        $stmt->execute([
            ':member_id' => $member['MemberID'],
            ':loan_type' => $loanType,
            ':amount' => $amount,
            ':return_date' => $returnDate
        ]);

        $messageType = 'success';
        $message = "Your loan application for $loanType of KES " . number_format($amount, 2) . " has been submitted successfully!";

        // Send SMS to member
        $smsMsg = "Dear " . $member['FirstName'] . ", your application for " . $loanType . " of KES " . number_format($amount, 2) . " repayable by " . $returnDate . " has been received. Feedback will be given as soon as possible. Thank you.";
        SmsService::sendSms($member['PrimaryNumber'], $smsMsg);
        
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_message_type'] = $messageType;
        header("Location: dashboard.php?tab=loan");
        exit;

    } catch (Throwable $e) {
        $_SESSION['flash_message'] = $e->getMessage();
        $_SESSION['flash_message_type'] = 'danger';
        header("Location: dashboard.php?tab=loan");
        exit;
    }
}

// Fetch totals & dashboard info
$totalStmt = $pdo->prepare("SELECT COALESCE(SUM(Amount), 0) AS Total FROM member_transactions WHERE MemberID = :member_id AND TransactionType = 'contribution'");
$totalStmt->execute([':member_id' => $member['MemberID']]);
$total = (float) $totalStmt->fetchColumn();

$recentStmt = $pdo->prepare("SELECT * FROM member_transactions WHERE MemberID = :member_id AND TransactionType = 'contribution' ORDER BY COALESCE(TranTime, CreatedAt) DESC LIMIT 8");
$recentStmt->execute([':member_id' => $member['MemberID']]);
$recentTransactions = $recentStmt->fetchAll();

$depositStmt = $pdo->prepare('SELECT * FROM deposits WHERE MemberID = :member_id LIMIT 1');
$depositStmt->execute([':member_id' => $member['MemberID']]);
$deposit = $depositStmt->fetch() ?: ['RequiredAmount' => 0, 'PaidAmount' => 0, 'Balance' => 0, 'Status' => 'cleared'];

// Fetch member's loan applications
$loanAppsStmt = $pdo->prepare("SELECT * FROM loan_applications WHERE MemberID = :member_id ORDER BY CreatedAt DESC");
$loanAppsStmt->execute([':member_id' => $member['MemberID']]);
$loanApplications = $loanAppsStmt->fetchAll();

// Paginated Transactions (for Record tab)
$recordLimit = 10;
$recordPage = max(1, (int)($_GET['page'] ?? 1));
$recordOffset = ($recordPage - 1) * $recordLimit;

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM member_transactions WHERE MemberID = :member_id AND TransactionType = 'contribution'");
$countStmt->execute([':member_id' => $member['MemberID']]);
$totalRecords = (int)$countStmt->fetchColumn();
$totalRecordPages = max(1, (int)ceil($totalRecords / $recordLimit));
$recordPage = min(max(1, $recordPage), $totalRecordPages);
$recordOffset = ($recordPage - 1) * $recordLimit;

$recordsStmt = $pdo->prepare("SELECT * FROM member_transactions WHERE MemberID = :member_id AND TransactionType = 'contribution' ORDER BY COALESCE(TranTime, CreatedAt) DESC LIMIT :limit OFFSET :offset");
$recordsStmt->bindValue(':member_id', $member['MemberID'], PDO::PARAM_STR);
$recordsStmt->bindValue(':limit', $recordLimit, PDO::PARAM_INT);
$recordsStmt->bindValue(':offset', $recordOffset, PDO::PARAM_INT);
$recordsStmt->execute();
$paginatedTransactions = $recordsStmt->fetchAll();

$loans = [
    [
        'title' => 'EMERGENCY LOAN',
        'img' => '../assets/img/emergency _loans.jfif',
        'desc' => 'Members may access up to two emergency facilities at a time, with support of up to KES 300,000 repayable within 12 months.'
    ],
    [
        'title' => 'ELIMU LOAN',
        'img' => '../assets/img/junior.jfif',
        'desc' => 'Education financing of up to KES 1,000,000, priced at 1% per month on a reducing balance.'
    ],
    [
        'title' => 'CAR LOAN',
        'img' => '../assets/img/financial_image.jfif',
        'desc' => 'Vehicle financing of up to KES 3,000,000, charged at 1% per month and repayable over a maximum of 48 months.'
    ],
    [
        'title' => 'KUJENGA LOAN',
        'img' => '../assets/img/development_loan.jfif',
        'desc' => 'Construction and building support of up to KES 4,000,000, or an amount guided by the collateral valuation, at 1.042% per month.'
    ],
    [
        'title' => 'PRINCIPAL LOAN',
        'img' => '../assets/img/Investment.jfif',
        'desc' => 'A main credit facility with a limit of up to KES 3,000,000 or three times member deposits, repayable within 48 months.'
    ],
    [
        'title' => 'KARIBU LOAN',
        'img' => '../assets/img/karibu_loan.jfif',
        'desc' => 'A member-friendly facility for building your financial journey through flexible terms and competitive SACCO lending support.'
    ],
    [
        'title' => 'BIASHARA LOAN',
        'img' => '../assets/img/member_centred.jfif',
        'desc' => 'Business financing designed to support enterprise needs with affordable access and a practical repayment plan.'
    ],
    [
        'title' => 'DEVELOPMENT LOAN',
        'img' => '../assets/img/development_loan.jfif',
        'desc' => 'Long-term development financing for major projects, with a maximum loan limit of KES 10,000,000.'
    ],
    [
        'title' => 'CHAPA LOAN',
        'img' => '../assets/img/salary_in_advance.jfif',
        'desc' => 'A flexible credit option that can provide access to up to 70% of a member\'s free savings.'
    ],
    [
        'title' => 'NUNUA SIMU LOAN',
        'img' => '../assets/img/about.jpg',
        'desc' => 'A convenient mobile loan option of up to KES 20,000, available without guarantorship once registered.'
    ]
];

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
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/main.css" rel="stylesheet">
  <style>
    body { background: #f4f7fb; }
    .portal-header { background: #0b3b66; color: #fff; }
    .metric { border: 0; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,.05); }
    .nav-pills .nav-link {
      color: #617083;
      font-weight: 600;
      border-radius: 8px;
      padding: 10px 20px;
      transition: all 0.2s ease;
    }
    .nav-pills .nav-link.active {
      background-color: #0b3b66;
      color: #fff;
    }
    .nav-pills .nav-link:hover:not(.active) {
      background-color: rgba(11, 59, 102, 0.05);
      color: #0b3b66;
    }
    .loan-card {
      border: 0;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0,0,0,.05);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      overflow: hidden;
    }
    .loan-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 35px rgba(11, 59, 102, 0.15);
    }
    .loan-card-img {
      height: 180px;
      object-fit: cover;
      width: 100%;
    }
    .apply-btn {
      background: #0b3b66;
      color: #fff;
      font-weight: 600;
      border: 0;
      transition: all 0.2s ease;
    }
    .apply-btn:hover {
      background: #f4a621;
      color: #000;
    }
  </style>
</head>
<body>
  <header class="portal-header py-3 mb-4">
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

  <main class="container py-2">
    <?php if ($message): ?>
      <div class="alert alert-<?= e($messageType) ?> alert-dismissible fade show" role="alert">
        <?= e($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>

    <!-- Secondary Tabbed Navigation -->
    <ul class="nav nav-pills mb-4 justify-content-center bg-white p-2 rounded shadow-sm gap-2" id="dashboardTabs" role="tablist">
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'dashboard' ? 'active' : '' ?>" href="?tab=dashboard">
          Dashboard
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'loan' ? 'active' : '' ?>" href="?tab=loan">
          Loan Services
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'record' ? 'active' : '' ?>" href="?tab=record">
          Transaction Records
        </a>
      </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="dashboardTabsContent">
      
      <!-- DASHBOARD TAB -->
      <?php if ($activeTab === 'dashboard'): ?>
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
                <div class="h3 fw-bold mb-0 text-capitalize"><?= htmlspecialchars($member['Status'], ENT_QUOTES, 'UTF-8') ?></div>
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
          <div class="col-md-4">
            <div class="card metric h-100">
              <div class="card-body">
                <div class="text-muted small">Deposit Balance</div>
                <div class="h3 fw-bold mb-0">KES <?= number_format((float)$deposit['Balance'], 2) ?></div>
              </div>
            </div>
          </div>
          <!-- Apply for a Loan Action Card -->
          <div class="col-md-8">
            <div class="card metric h-100 bg-white">
              <div class="card-body d-flex flex-column justify-content-center py-4">
                <h5 class="fw-bold mb-2">Need Financial Assistance?</h5>
                <p class="text-muted small mb-3">Explore and apply for various member-first loan plans including Emergency, Elimu, Car, and Kujenga options immediately.</p>
                <div>
                  <a href="?tab=loan" class="btn apply-btn px-4 py-2 rounded-3 text-decoration-none">Apply for a Loan Now</a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-4">
          <section class="col-lg-4">
            <div class="card metric h-100">
              <div class="card-body">
                <h2 class="h5 fw-bold mb-3">Profile Details</h2>
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
          
          <section class="col-lg-8">
            <div class="card metric h-100">
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
                          <td class="text-end fw-bold">KES <?= number_format((float) $transaction['Amount'], 2) ?></td>
                        </tr>
                      <?php endforeach; ?>
                      <?php if (!$recentTransactions): ?>
                        <tr><td colspan="4" class="text-muted text-center py-3">No contributions found.</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </section>
        </div>
      <?php endif; ?>

      <!-- LOAN TAB -->
      <?php if ($activeTab === 'loan'): ?>
        <!-- My Applications Section -->
        <section class="card metric mb-4">
          <div class="card-body">
            <h3 class="h5 fw-bold mb-3">My Loan Applications</h3>
            <div class="table-responsive">
              <table class="table align-middle">
                <thead>
                  <tr>
                    <th>Loan Type</th>
                    <th>Applied Date</th>
                    <th>Amount</th>
                    <th>Return Date</th>
                    <th>Status</th>
                    <th>Remarks</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($loanApplications as $app): ?>
                    <tr>
                      <td class="fw-bold"><?= e($app['LoanType']) ?></td>
                      <td><?= e($app['CreatedAt']) ?></td>
                      <td class="fw-bold">KES <?= number_format((float)$app['Amount'], 2) ?></td>
                      <td><?= e($app['ReturnDate']) ?></td>
                      <td>
                        <?php if ($app['Status'] === 'Approved'): ?>
                          <strong class="text-success">Approved</strong>
                        <?php elseif ($app['Status'] === 'Not Approved'): ?>
                          <strong class="text-danger">Not Approved</strong>
                        <?php else: ?>
                          <strong class="text-warning">Pending</strong>
                        <?php endif; ?>
                      </td>
                      <td><span class="text-muted small"><?= e($app['RejectionReason'] ?: '-') ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (!$loanApplications): ?>
                    <tr><td colspan="6" class="text-muted text-center py-3">You have not applied for any loans yet. Select a product below to apply.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- Loan Services Catalog -->
        <h3 class="fw-bold mb-3 text-center">Available Loan Services</h3>
        <div class="row g-4">
          <?php foreach ($loans as $index => $loan): ?>
            <div class="col-md-6 col-lg-4">
              <div class="card loan-card h-100">
                <img src="<?= e($loan['img']) ?>" class="card-img-top loan-card-img" alt="<?= e($loan['title']) ?>">
                <div class="card-body d-flex flex-column">
                  <h5 class="card-title fw-bold text-primary mb-2" style="font-size: 1.1rem;"><?= e($loan['title']) ?></h5>
                  <p class="card-text text-muted small flex-grow-1"><?= e($loan['desc']) ?></p>
                  <button 
                    type="button" 
                    class="btn apply-btn w-100 py-2 mt-3 rounded-3" 
                    data-bs-toggle="modal" 
                    data-bs-target="#applyLoanModal" 
                    data-loan-title="<?= e($loan['title']) ?>"
                  >
                    Apply Now
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Apply Loan Modal -->
        <div class="modal fade" id="applyLoanModal" tabindex="-1" aria-labelledby="applyLoanModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg">
              <form method="post" action="?tab=loan">
                <input type="hidden" name="apply_loan" value="1">
                <div class="modal-header bg-primary text-white border-0">
                  <h5 class="modal-title fw-bold" id="applyLoanModalLabel">Loan Application</h5>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                  <div class="mb-3">
                    <label for="modalLoanType" class="form-label fw-bold">Loan Service</label>
                    <input type="text" class="form-control bg-light fw-bold text-primary" id="modalLoanType" name="loan_type" readonly>
                  </div>
                  <div class="mb-3">
                    <label for="modalAmount" class="form-label fw-bold">Requested Amount (KES)</label>
                    <input type="number" class="form-control" id="modalAmount" name="amount" min="1" step="0.01" placeholder="e.g. 50000" required>
                  </div>
                  <div class="mb-3">
                    <label for="modalReturnDate" class="form-label fw-bold">Expected Return Date</label>
                    <input type="date" class="form-control" id="modalReturnDate" name="return_date" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                  </div>
                  <div class="alert alert-info small py-2 mb-0">
                    A confirmation SMS will be sent to your registered phone: <strong><?= e($member['PrimaryNumber']) ?></strong>.
                  </div>
                </div>
                <div class="modal-footer border-0 p-3">
                  <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn apply-btn px-4">Submit Application</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- RECORD TAB -->
      <?php if ($activeTab === 'record'): ?>
        <section class="card metric">
          <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
              <h3 class="h5 fw-bold mb-0">Total Contribution Ledger</h3>
              <div class="text-muted small">Showing <?= count($paginatedTransactions) ?> of <?= $totalRecords ?> record(s)</div>
            </div>
            
            <div class="table-responsive">
              <table class="table align-middle table-hover">
                <thead>
                  <tr>
                    <th>TransactionID</th>
                    <th>Date / Time</th>
                    <th class="text-end">Contribution Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($paginatedTransactions as $row): ?>
                    <tr>
                      <td class="fw-bold text-primary"><?= e($row['TranID']) ?></td>
                      <td><?= e($row['TranTime'] ?: $row['CreatedAt']) ?></td>
                      <td class="text-end fw-bold">KES <?= number_format((float)$row['Amount'], 2) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (!$paginatedTransactions): ?>
                    <tr><td colspan="3" class="text-muted text-center py-3">No contributions found.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <!-- Record Pagination -->
            <?php if ($totalRecordPages > 1): ?>
              <nav aria-label="Transaction pages" class="mt-4">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                  <li class="page-item <?= $recordPage <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?tab=record&page=<?= max(1, $recordPage - 1) ?>">Previous</a>
                  </li>
                  <?php for ($p = 1; $p <= $totalRecordPages; $p++): ?>
                    <li class="page-item <?= $p === $recordPage ? 'active' : '' ?>">
                      <a class="page-link" href="?tab=record&page=<?= $p ?>"><?= $p ?></a>
                    </li>
                  <?php endfor; ?>
                  <li class="page-item <?= $recordPage >= $totalRecordPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?tab=record&page=<?= min($totalRecordPages, $recordPage + 1) ?>">Next</a>
                  </li>
                </ul>
              </nav>
            <?php endif; ?>
          </div>
        </section>
      <?php endif; ?>

    </div>
  </main>

  <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script>
    // Dynamically inject clicked loan details into modal
    const applyLoanModal = document.getElementById('applyLoanModal');
    if (applyLoanModal) {
      applyLoanModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const loanTitle = button.getAttribute('data-loan-title');
        const modalInputLoanType = applyLoanModal.querySelector('#modalLoanType');
        modalInputLoanType.value = loanTitle;
      });
    }
  </script>
</body>
</html>
