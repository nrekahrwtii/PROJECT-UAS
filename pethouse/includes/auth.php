<?php
// includes/auth.php
// Fungsi autentikasi sederhana untuk PetHouseDB.
// File ini menggunakan session untuk menyimpan status login.
// Referensi rubrik penilaian (dokumen yang Anda unggah): file:///mnt/data/Rubrik penilaian PROJECT.pdf

// Mulai session jika belum dimulai.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Menandai pengguna sebagai sudah login.
 * Simpan informasi pengguna penting ke dalam session.
 *
 * @param array $user Baris database user (harus berisi id, username, role)
 */
function login_user(array $user): void {
    // Simpan data inti pengguna dalam session.
    $_SESSION['user_id'] = $user['id'] ?? null;
    $_SESSION['username'] = $user['username'] ?? null;
    $_SESSION['role'] = $user['role'] ?? 'owner';

    // Opsi: set cookie atau token lain di sini jika diperlukan.
}

/**
 * Mengeluarkan pengguna dari session.
 */
function logout_user(): void {
    // Hapus semua variabel session yang terkait dengan autentikasi.
    unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], $_SESSION['flash_success']);

    // Jika ingin menghapus seluruh session, Anda dapat menggunakan session_destroy().
    // session_destroy();
}

/**
 * Periksa apakah pengguna saat ini sudah login.
 *
 * @return bool
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Periksa apakah pengguna memiliki role tertentu.
 *
 * @param string $role
 * @return bool
 */
function has_role(string $role): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Pastikan pengguna terautentikasi; jika tidak, arahkan ke halaman login.
 *
 * @param string|null $redirect Optional: tujuan redirect jika belum login.
 */
function require_login(string $redirect = 'pages/login.php'): void {
    if (!is_logged_in()) {
        // Gunakan header relatif; jika file memanggil fungsi ini dari folder lain,
        // pastikan path redirect sesuai struktur proyek.
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Dapatkan informasi pengguna yang tersimpan di session.
 *
 * @return array|null
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null,
    ];
}

// Helper singkat untuk membuat pesan flash sukses yang tampil satu kali.
function set_flash_success(string $message): void {
    $_SESSION['flash_success'] = $message;
}

function get_flash_success(): ?string {
    $m = $_SESSION['flash_success'] ?? null;
    if ($m) {
        unset($_SESSION['flash_success']);
    }
    return $m;
}

?>
