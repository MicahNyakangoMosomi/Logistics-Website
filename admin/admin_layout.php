<?php

if (!function_exists('admin_e')) {
    function admin_e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('admin_header')) {
    function admin_header(string $active, string $subtitle = '', bool $wide = true): void
    {
        $links = [
            'register_member' => ['Register Member', 'register_member.php'],
            'manage_jobs' => ['Manage Jobs', 'manage_jobs.php'],
            'reports' => ['Reports', 'reports.php'],
            'members' => ['Members', 'members.php'],
            'loan_applications' => ['Loan Applications', 'loan_applications.php'],
            'settings' => ['Settings', 'settings.php'],
        ];
        $shellClass = $wide ? 'admin-shell' : 'admin-shell admin-shell--narrow';
        ?>
        <header class="admin-header">
          <div class="container-fluid <?= admin_e($shellClass) ?>">
            <div class="admin-header-top">
              <div class="admin-brand">
                <img src="../assets/img/logo.png" alt="Mashirikiano SACCO" width="48">
                <div>
                  <div class="admin-brand-title">Mashirikiano SACCO Admin</div>
                  <?php if ($subtitle !== ''): ?>
                    <div class="admin-brand-subtitle"><?= admin_e($subtitle) ?></div>
                  <?php endif; ?>
                </div>
              </div>
              <a class="admin-logout-link" href="../auth/admin_logout.php">Logout</a>
            </div>
          </div>
        </header>
        <nav class="admin-top-nav" aria-label="Admin navigation">
          <?php foreach ($links as $key => $link): ?>
            <a class="<?= $active === $key ? 'active' : '' ?>" href="<?= admin_e($link[1]) ?>"><?= admin_e($link[0]) ?></a>
          <?php endforeach; ?>
        </nav>
        <?php
    }
}
