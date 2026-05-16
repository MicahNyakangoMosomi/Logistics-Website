<?php

require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/MemberService.php';

Auth::requireAdmin();

$message = '';
$messageType = 'info';

try {
    $pdo = Database::connection();
    $pdo->query('SELECT 1 FROM members LIMIT 1');
    $pdo->query('SELECT 1 FROM contributions LIMIT 1');
} catch (Throwable $error) {
    error_log('Register member setup error: ' . $error->getMessage());
    $messageType = 'warning';
    $message = 'Database setup is not complete: ' . $error->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $message === '') {
    try {
        $createdMember = MemberService::create($_POST);
        $messageType = 'success';
        $message = 'Member created successfully. MemberID: ' . $createdMember['MemberID'];
    } catch (Throwable $error) {
        $messageType = 'warning';
        $message = $error->getMessage();
    }
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
  <title>Register Member | Mashirikiano SACCO Admin</title>
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
          <div class="small opacity-75">Internal member registration</div>
        </div>
      </div>
      <nav class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-light" href="register_member.php">Register Member</a>
        <a class="btn btn-sm btn-outline-light" href="manage_jobs.php">Manage Jobs</a>
        <a class="btn btn-sm btn-outline-light" href="reports.php">Reports</a>
        <a class="btn btn-sm btn-outline-light" href="members.php">Members</a>
        <a class="btn btn-sm btn-outline-light" href="../auth/admin_logout.php">Logout</a>
      </nav>
    </div>
  </header>

  <main class="container-fluid admin-shell py-4">
    <?php if ($message): ?>
      <div class="alert alert-<?= e($messageType) ?>" role="alert"><?= e($message) ?></div>
    <?php endif; ?>

    <section class="card panel">
      <div class="card-body">
        <h1 class="h4 fw-bold mb-1">Register Member</h1>
        <p class="text-muted mb-4">Create SACCO members through the internal staff/admin workflow.</p>
        <form method="post" class="row g-3" onsubmit="return confirm('Register this member and save the record to the database?');">
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
          <div class="col-md-6">
            <label class="form-label" for="email">Email <span class="text-muted">(optional)</span></label>
            <input class="form-control" id="email" name="email" type="email">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="password">Password</label>
            <input class="form-control" id="password" name="password" type="password" required autocomplete="new-password">
          </div>
          <div class="col-12">
            <button class="btn btn-primary" type="submit">Register Member</button>
          </div>
        </form>
      </div>
    </section>
  </main>
</body>
</html>
