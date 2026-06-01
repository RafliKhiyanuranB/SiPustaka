<?php
// config/database.php

define('DB_HOST',      'localhost');
define('DB_USER',      'root');
define('DB_PASS',      '');
define('DB_NAME',      'db_perpustakaan');
define('DB_CHARSET',   'utf8mb4');
define('DB_COLLATION', 'utf8mb4_0900_ai_ci');

require_once __DIR__ . '/db_helpers.php';

function getDB(): mysqli
{
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('Koneksi database gagal: ' . $conn->connect_error);
        }
        if (!$conn->query("SET NAMES '" . DB_CHARSET . "' COLLATE '" . DB_COLLATION . "'")) {
            die('Gagal mengatur charset database: ' . $conn->error);
        }
    }
    return $conn;
}
