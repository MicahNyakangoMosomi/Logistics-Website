<?php

require_once __DIR__ . '/../classes/Auth.php';

Auth::startSession();

if (Auth::member()) {
    header('Location: ../member/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberId = $_POST['member_id'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        if (Auth::login($memberId, $password)) {
            header('Location: ../member/dashboard.php');
            exit;
        }
        $error = 'Invalid Membership ID or password.';
    } catch (Exception $e) {
        if ($e->getMessage() === 'INACTIVE_ACCOUNT') {
            $error = 'please contact the administration to activate your account';
        } else {
            $error = 'An error occurred during login.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Member Login | Mashirikiano SACCO</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/logo.png">
  <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/css/main.css" rel="stylesheet">
  <style>
    body { background: #f4f7fb; min-height: 100vh; }
    .login-shell { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
    .login-card { width: 100%; max-width: 980px; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.12); }
    .login-brand { background: #0b3b66; color: #fff; padding: 48px; display: flex; flex-direction: column; justify-content: center; }
    .login-brand img { width: 86px; height: auto; margin-bottom: 24px; }
    .login-form { padding: 48px; }
    @media (max-width: 767px) { .login-brand, .login-form { padding: 30px; } }
  </style>
</head>
<body>
  <main class="login-shell">
    <div class="login-card">
      <div class="row g-0">
        <section class="col-md-5 login-brand">
          <img src="../assets/img/logo.png" alt="Mashirikiano SACCO">
          <h1 class="h3 fw-bold">Member Portal</h1>
          <p class="mb-0">Track your contributions, recent M-Pesa payments, and membership profile.</p>
        </section>
        <section class="col-md-7 login-form">
          <h2 class="h4 fw-bold mb-4">Sign in</h2>
          <?php if ($error): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>
          <form method="post" novalidate>
            <div class="mb-3">
              <label for="member_id" class="form-label">Membership ID</label>
              <input type="text" class="form-control form-control-lg" id="member_id" name="member_id" required autocomplete="username">
            </div>
            <div class="mb-4">
              <label for="password" class="form-label">Password</label>
              <input type="password" class="form-control form-control-lg" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100">Login</button>
          </form>
          <p class="text-muted small mt-4 mb-0">Use the 8-character password issued by Mashirikiano SACCO.</p>
        </section>
      </div>
    </div>
  </main>
</body>
</html>
