<?php
// File ini digunakan untuk membuat koneksi ke basis data MySQL
// menggunakan PDO. Pastikan konfigurasi sudah sesuai dengan
// lingkungan lokal Anda.

$DB_HOST = '127.0.0.1';
$DB_NAME = 'pethousedb';
$DB_USER = 'root';        // atau 'dbuser' jika kamu buat user baru
$DB_PASS = 'mypethouse22';  // password root atau password user DB kamu

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";

try {
    // Membuat objek PDO untuk koneksi ke basis data.
    // Opsi tambahan digunakan agar kesalahan dapat terdeteksi
    // dan hasil query dapat diperoleh dalam bentuk array asosiatif.
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    // Jika terjadi kesalahan koneksi, catat dalam log server
    // dan tampilkan pesan sederhana untuk keperluan debugging.
    error_log('Kesalahan koneksi database: ' . $e->getMessage());
    die('Tidak dapat terhubung ke basis data. Periksa konfigurasi koneksi.');
}
