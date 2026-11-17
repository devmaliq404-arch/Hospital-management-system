<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "hospitalmanagement";
$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Failed to connect to database: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

?>
    