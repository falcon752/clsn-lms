<?php
include_once './includes/db.php';
include_once './includes/auth.php';

logoutUser();
header('Location: /login');
exit;
