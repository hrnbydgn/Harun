<?php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'teklif_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_NAME', 'HSG Aviation - Teklif Yönetimi');
define('APP_URL', '');
define('UPLOAD_PATH', __DIR__ . '/assets/uploads/');
define('VERSION', '1.0.0');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]
            );
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:30px;color:#ff4444;">Veritabanı bağlantı hatası: ' . $e->getMessage() . '<br><a href="/kurulum.php">Kurulum sayfasına git</a></div>');
        }
    }
    return $pdo;
}
