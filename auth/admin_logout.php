<?php

require_once __DIR__ . '/../classes/Auth.php';

Auth::logoutAdmin();

header('Location: admin_login.php');
exit;
