<?php
// pages/visit_delete.php
// Menghapus rekaman kunjungan.
// Komentar: bahasa Indonesia formal.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$user_id = $_SESSION['user_id'] ?? null;
$visit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$pet_id = isset($_GET['pet_id']) ? (int) $_GET['pet_id'] : 0;

if ($visit_id <= 0) {
    set_flash_success('Permintaan tidak valid.');
    header('Location: pets_list.php');
    exit;
}

// Ambil kunjungan dan pemilik hewan
try {
    $stmt = $pdo->prepare("
        SELECT v.id, v.pet_id, p.user_id
        FROM visits v
        INNER JOIN pets p ON v.pet_id = p.id
        WHERE v.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $visit_id]);
    $rec = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Gagal mengambil visit untuk delete: ' . $e->getMessage());
    $rec = null;
}

if (!$rec) {
    set_flash_success('Data kunjungan tidak ditemukan.');
    header('Location: pets_list.php');
    exit;
}

if ($rec['user_id'] != $user_id && !has_role('admin')) {
    set_flash_success('Anda tidak memiliki izin menghapus kunjungan ini.');
    header('Location: pets_list.php');
    exit;
}

// Hapus rekaman
try {
    $stmt = $pdo->prepare("DELETE FROM visits WHERE id = :id");
    $stmt->execute([':id' => $visit_id]);

    set_flash_success('Kunjungan berhasil dihapus.');
    header('Location: pet_view.php?id=' . $rec['pet_id']);
    exit;
} catch (PDOException $e) {
    error_log('Gagal menghapus visit: ' . $e->getMessage());
    set_flash_success('Gagal menghapus kunjungan. Silakan coba lagi.');
    header('Location: pet_view.php?id=' . ($rec['pet_id'] ?? $pet_id));
    exit;
}
