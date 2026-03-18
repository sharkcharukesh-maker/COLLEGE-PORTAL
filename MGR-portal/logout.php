<?php
session_start();

/* Destroy session */
$_SESSION = [];
session_unset();
session_destroy();

/* Remove Remember Me cookies */
setcookie("remember_role", "", time() - 3600, "/");
setcookie("remember_id", "", time() - 3600, "/");

/* Redirect to login */
header("Location: school.php");
exit();
?>
