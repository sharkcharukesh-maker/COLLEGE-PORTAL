<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "mgr_portal"; // Make sure this matches your database name

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
