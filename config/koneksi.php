<?php

$serverMysql = "localhost";
$userMysql = "root";
$passMysql = "";
$db = "db_dompetku";

$koneksi = mysqli_connect(
    $serverMysql,
    $userMysql,
    $passMysql,
    $db
);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

mysqli_set_charset($koneksi, "utf8mb4");

?>