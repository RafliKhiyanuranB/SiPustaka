<?php
// config/database.php

define('DB_HOST',     'localhost');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_NAME',     'db_perpustakaan');
define('DB_CHARSET',    'utf8mb4');
// Harus sama dengan collation tabel/view (MySQL 8 Laragon = utf8mb4_0900_ai_ci)
define('DB_COLLATION',  'utf8mb4_0900_ai_ci');

function getDB(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $conn->set_charset(DB_CHARSET);
        $conn->query("SET collation_connection = '" . DB_COLLATION . "'");
        if ($conn->connect_error) {
            die(json_encode(['error' => 'Koneksi database gagal: ' . $conn->connect_error]));
        }
    }
    return $conn;
}
