<?php

$host = "localhost";
$user = "root";
$password = "";
$database = "db_healthnest";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    http_response_code(500);
    die("Database connection failed.");
}

mysqli_set_charset($conn, "utf8mb4");
