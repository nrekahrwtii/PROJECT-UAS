<?php
// pages/logout.php
// Halaman untuk melakukan proses logout (keluar) pengguna.
// Komentar ditulis dalam bahasa Indonesia formal.

require_once __DIR__ . '/../includes/auth.php';

// Pastikan session berjalan.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simpan pesan flash yang akan ditampilkan di halaman login.
// Pesan ini disimpan terlebih dahulu agar tidak hilang saat kita menghapus data autentikasi.
$_SESSION['flash_success'] = 'Anda telah berhasil keluar. Sampai jumpa kembali.';

// Hapus hanya variabel-variabel session yang berkaitan dengan autentikasi,
// namun biarkan pesan flash tetap ada agar dapat ditampilkan di halaman login.
unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);

// Regenerasi session id untuk keamanan (mencegah session fixation).
session_regenerate_id(true);

// Alihkan pengguna ke halaman login setelah logout.
header('Location: login.php');
exit;
