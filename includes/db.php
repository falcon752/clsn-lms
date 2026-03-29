<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');        // Update if your MySQL root has a password
define('DB_NAME', 'clsn_lms');
define('BASE_URL', '/clsn-lms'); // Change to your subdomain root e.g. '' or '/clsn-lms'

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die('
<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:60px">
<h2 style="color:#c0392b">Database Connection Error</h2>
<p>Could not connect to MySQL. Please check your credentials in <code>includes/db.php</code>.</p>
<p>If this is a fresh install, please <a href="' . BASE_URL . '/setup.php">run setup.php</a> first.</p>
</body></html>');
}

$conn->set_charset('utf8mb4');
