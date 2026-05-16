<?php
$host     = "localhost";
$user     = "root";
$password = "";
$database = "charite_calisa";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");