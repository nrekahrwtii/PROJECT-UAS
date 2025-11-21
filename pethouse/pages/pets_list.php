<?php
// pages/pets_list.php
// Daftar hewan peliharaan milik pengguna saat ini.
// Komentar disusun dalam bahasa Indonesia formal.
// Referensi rubrik penilaian (lokal): file:///mnt/data/Rubrik penilaian PROJECT.pdf

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Pastikan pengguna terautentikasi sebelum mengakses halaman ini.
require_login();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Pengguna';

// Ambil daftar hewan milik user
try {
    $stmt = $pdo->prepare("SELECT id, name, species, breed, birth_date, gender, photo, created_at FROM pets WHERE user_id = :uid ORDER BY created_at DESC");
    $stmt->execute([':uid' => $user_id]);
    $pets = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Gagal mengambil daftar hewan: ' . $e->getMessage());
    $pets = [];
}

// Ambil pesan flash jika ada
$flash = get_flash_success();

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Hewan - PetHouseDB</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; background:#f5f5f5; }
        .container { max-width:1000px; margin:30px auto; padding:20px; background:#fff; border-radius:6px; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
        .topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; }
        .btn { padding:8px 12px; background:#007bff; color:#fff; text-decoration:none; border-radius:4px; }
        .btn-muted { background:#6c757d; }
        table { width:100%; border-collapse:collapse; margin-top:12px; }
        th, td { padding:10px; border-bottom:1px solid #eaeaea; text-align:left; }
        th { background:#f8f8f8; }
        img.thumb { width:80px; height:80px; object-fit:cover; border-radius:6px; }
        .actions a { margin-right:6px; text-decoration:none; color:#007bff; }
        .alert { padding:10px; border-radius:4px; margin-bottom:12px; }
        .alert-success { background:#e6ffed; color:#1a7a2e; }
        .alert-danger { background:#ffe6e6; color:#a33; }
    </style>
</head>
<body>
<div class="container">
    <div class="topbar">
        <div>
            <strong>PetHouseDB</strong>
            <div style="font-size:0.9rem; color:#555;">Halo, <?= htmlspecialchars($username) ?></div>
        </div>
        <div>
            <a class="btn" href="pet_create.php">Tambah Hewan</a>
            <a class="btn btn-muted" href="dashboard.php">Dashboard</a>
            <a class="btn btn-muted" href="logout.php">Keluar</a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <?php if (empty($pets)): ?>
        <p>Anda belum menambahkan hewan peliharaan. <a href="pet_create.php">Tambahkan hewan pertama</a>.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Foto</th>
                    <th>Nama</th>
                    <th>Spesies / Ras</th>
                    <th>Tanggal Lahir</th>
                    <th>Jenis Kelamin</th>
                    <th>Ditambahkan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pets as $pet): ?>
                <tr>
                    <td>
                        <?php if (!empty($pet['photo']) && file_exists(__DIR__ . '/../assets/uploads/' . $pet['photo'])): ?>
                            <img class="thumb" src="../assets/uploads/<?= htmlspecialchars($pet['photo']) ?>" alt="<?= htmlspecialchars($pet['name']) ?>">
                        <?php else: ?>
                            <div style="width:80px;height:80px;background:#f0f0f0;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#888;">No Image</div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($pet['name']) ?></td>
                    <td><?= htmlspecialchars($pet['species'] ?: '-') ?> / <?= htmlspecialchars($pet['breed'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($pet['birth_date'] ?: '-') ?></td>
                    <td><?= htmlspecialchars($pet['gender']) ?></td>
                    <td><?= htmlspecialchars($pet['created_at']) ?></td>
                    <td class="actions">
                        <a href="pet_view.php?id=<?= $pet['id'] ?>">Lihat</a>
                        <a href="pet_edit.php?id=<?= $pet['id'] ?>">Ubah</a>
                        <a href="pet_delete.php?id=<?= $pet['id'] ?>" onclick="return confirm('Yakin akan menghapus hewan ini?');">Hapus</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>
</body>
</html>
