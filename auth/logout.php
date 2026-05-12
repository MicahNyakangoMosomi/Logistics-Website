<?php

require_once __DIR__ . '/../classes/Auth.php';

Auth::logout();

header('Location: login.php');
exit;
