<?php

require_once __DIR__ . '/../classes/Auth.php';

Auth::startSession();

if (!empty($_SESSION['admin_user_id']) && in_array($_SESSION['admin_role'] ?? '', ['admin', 'staff'], true)) {
    header('Location: ../admin/members.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        if (Auth::loginAdmin($email, $password)) {
            header('Location: ../admin/members.php');
            exit;
        }

        $error = 'Invalid admin credentials or inactive account.';
    } catch (Throwable $exception) {
        error_log('Admin login error: ' . $exception->getMessage());
        $error = 'Admin login is not fully configured. Confirm that the admin_users table exists and has a Password column.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login | Mashirikiano SACCO</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/logo.png">
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #eef3f8; min-height: 100vh; }
    .login-shell { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
    .login-card { width: 100%; max-width: 460px; background: #fff; border-radius: 8px; box-shadow: 0 20px 55px rgba(12, 35, 64, .14); }
    .brand-strip { background: #0b3b66; color: #fff; border-radius: 8px 8px 0 0; padding: 28px; }
  </style>
</head>
<body>
  <main class="login-shell">
    <section class="login-card">
      <div class="brand-strip d-flex align-items-center gap-3">
        <img src="../assets/img/logo.png" alt="Mashirikiano SACCO" width="56">
        <div>
          <h1 class="h4 fw-bold mb-0">Admin Access</h1>
          <div class="small opacity-75">Internal SACCO operations</div>
        </div>
      </div>
      <div class="p-4">
        <?php if ($error): ?>
          <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post" novalidate>
          <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input class="form-control form-control-lg" id="email" name="email" type="email" required autocomplete="username">
          </div>
          <div class="mb-4">
            <label class="form-label" for="password">Password</label>
            <input class="form-control form-control-lg" id="password" name="password" type="password" required autocomplete="current-password">
          </div>
          <button class="btn btn-primary btn-lg w-100" type="submit">Login</button>
        </form>
      </div>
    </section>
  </main>
</body>
</html>
