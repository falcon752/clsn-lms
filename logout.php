<?php
include_once './includes/db.php';
include_once './includes/auth.php';

logoutUser();
header('Location: /clsn-lms/login.php');
exit;
