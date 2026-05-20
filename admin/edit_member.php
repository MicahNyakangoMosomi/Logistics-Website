<?php

require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/MemberService.php';

Auth::requireAdmin();

$message = '';
$messageType = 'info';
$adminRole = Auth::adminRole();
$isAdmin = $adminRole === 'admin';

$pdo = Database::connection();

$memberId = $_GET['id'] ?? '';
if (!$memberId) {
    header('Location: members.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        MemberService::update($memberId, $_POST, [
            'can_change_status' => $isAdmin,
            'can_change_password' => $isAdmin,
        ]);
        $messageType = 'success';
        $message = 'Member updated successfully.';
    } catch (Throwable $error) {
        $messageType = 'warning';
        $message = $error->getMessage();
    }
}

$stmt = $pdo->prepare('SELECT * FROM members WHERE MemberID = :member_id LIMIT 1');
$stmt->execute([':member_id' => $memberId]);
$member = $stmt->fetch();

if (!$member) {
    echo "Member not found.";
    exit;
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
  <title>Edit Member | Mashirikiano SACCO Admin</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/logo.png">
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f3f6fa; color: #1c2938; }
    .admin-header { background: #0b3b66; color: #fff; }
    .admin-shell { max-width: 1180px; }
    .panel { border: 0; border-radius: 8px; box-shadow: 0 10px 28px rgba(13, 38, 67, .08); }
  </style>
</head>
<body>
  <header class="admin-header py-3">
    <div class="container-fluid admin-shell d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div class="d-flex align-items-center gap-3">
        <img src="../assets/img/logo.png" alt="Mashirikiano SACCO" width="48">
        <div>
          <div class="fw-bold">Mashirikiano SACCO Admin</div>
          <div class="small opacity-75">Edit member details</div>
        </div>
      </div>
      <nav class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-outline-light" href="register_member.php">Register Member</a>
        <a class="btn btn-sm btn-outline-light" href="manage_jobs.php">Manage Jobs</a>
        <a class="btn btn-sm btn-outline-light" href="reports.php">Reports</a>
        <a class="btn btn-sm btn-outline-light" href="members.php">Members</a>
        <a class="btn btn-sm btn-outline-light" href="loan_applications.php">Loan Applications</a>
        <a class="btn btn-sm btn-outline-light" href="settings.php">Settings</a>
        <a class="btn btn-sm btn-outline-light" href="../auth/admin_logout.php">Logout</a>
      </nav>
    </div>
  </header>

  <main class="container-fluid admin-shell py-4">
    <div class="mb-3">
      <a href="members.php" class="btn btn-outline-secondary btn-sm">&larr; Back to Members</a>
    </div>

    <?php if ($message): ?>
      <div class="alert alert-<?= e($messageType) ?>" role="alert"><?= e($message) ?></div>
    <?php endif; ?>

    <section class="card panel">
      <div class="card-body">
        <h1 class="h4 fw-bold mb-1">Edit <?= e($member['MemberID']) ?></h1>
        <p class="text-muted mb-4">Modify the member's details and save changes to the database.</p>
        <form method="post" class="row g-3" onsubmit="return confirm('Save these member changes to the database?');">
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
          
          <?php if ($isAdmin): ?>
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
              <div class="input-group">
                <input class="form-control" id="password" name="password" type="text" autocomplete="new-password" placeholder="Leave blank to keep the current password" readonly>
                <button class="btn btn-outline-primary" type="button" id="generatePassword">Auto Generate Password</button>
                <button class="btn btn-outline-secondary" type="button" id="clearPassword">Clear</button>
              </div>
              <div class="form-text">A password SMS is sent only if this field has a generated password when you save.</div>
            </div>
          <?php else: ?>
            <div class="col-md-6">
              <label class="form-label">Status</label>
              <input class="form-control" value="<?= e($member['Status']) ?>" disabled>
            </div>
          <?php endif; ?>

          <div class="col-12 mt-4">
            <button class="btn btn-primary" type="submit">Save Changes</button>
            <a href="members.php" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </section>
  </main>
  <script>
    const passwordInput = document.getElementById('password');
    const generatePasswordButton = document.getElementById('generatePassword');
    const clearPasswordButton = document.getElementById('clearPassword');
    const passwordDigits = '0123456789';
    const passwordLetters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    function randomIndex(max) {
      const randomValues = new Uint32Array(1);
      window.crypto.getRandomValues(randomValues);
      return randomValues[0] % max;
    }

    if (passwordInput && generatePasswordButton && clearPasswordButton) {
      generatePasswordButton.addEventListener('click', function () {
        const characters = [];

        for (let index = 0; index < 5; index++) {
          characters.push(passwordDigits[randomIndex(passwordDigits.length)]);
        }

        for (let index = 0; index < 3; index++) {
          characters.push(passwordLetters[randomIndex(passwordLetters.length)]);
        }

        for (let index = characters.length - 1; index > 0; index--) {
          const swapIndex = randomIndex(index + 1);
          [characters[index], characters[swapIndex]] = [characters[swapIndex], characters[index]];
        }

        passwordInput.value = characters.join('');
      });

      clearPasswordButton.addEventListener('click', function () {
        passwordInput.value = '';
      });
    }
  </script>
</body>
</html>
