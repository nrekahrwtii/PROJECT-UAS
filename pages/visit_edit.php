<?php
// pages/visit_edit.php
// Mengubah data kunjungan.
// Komentar: bahasa Indonesia formal.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$user_id = $_SESSION['user_id'] ?? null;
$visit_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($visit_id <= 0) {
    set_flash_success('Permintaan tidak valid.');
    header('Location: pets_list.php');
    exit;
}

$errors = [];
$visit = null;

try {
    $stmt = $pdo->prepare("
        SELECT v.*, p.user_id, p.name AS pet_name
        FROM visits v
        INNER JOIN pets p ON v.pet_id = p.id
        WHERE v.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $visit_id]);
    $visit = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Gagal mengambil visit untuk edit: ' . $e->getMessage());
}

if (!$visit) {
    set_flash_success('Data kunjungan tidak ditemukan.');
    header('Location: pets_list.php');
    exit;
}

if ($visit['user_id'] != $user_id && !has_role('admin')) {
    set_flash_success('Anda tidak memiliki izin mengubah kunjungan ini.');
    header('Location: pets_list.php');
    exit;
}

$old = [
    'visit_date' => $visit['visit_date'],
    'type' => $visit['type'],
    'description' => $visit['description']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visit_date = trim($_POST['visit_date'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $old = compact('visit_date','type','description');

    if ($visit_date === '') {
        $errors[] = 'Tanggal kunjungan wajib diisi.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $visit_date)) {
        $errors[] = 'Format tanggal tidak valid.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE visits
                SET visit_date = :visit_date, type = :type, description = :description
                WHERE id = :id
            ");
            $stmt->execute([
                ':visit_date' => $visit_date,
                ':type' => $type ?: null,
                ':description' => $description ?: null,
                ':id' => $visit_id
            ]);

            set_flash_success('Perubahan kunjungan berhasil disimpan.');
            header('Location: pet_view.php?id=' . $visit['pet_id']);
            exit;
        } catch (PDOException $e) {
            error_log('Gagal update visit: ' . $e->getMessage());
            $errors[] = 'Terjadi kesalahan saat menyimpan perubahan.';
        }
    }
}

$flash = get_flash_success();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Edit Kunjungan - PetHouseDB</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card p-4">
        <div class="d-flex justify-content-between mb-3">
            <h5>Edit Kunjungan untuk: <?= htmlspecialchars($visit['pet_name']) ?></h5>
            <a class="btn btn-secondary" href="pet_view.php?id=<?= $visit['pet_id'] ?>">Kembali</a>
        </div>

        <?php if ($flash): ?><div class="alert alert-success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

        <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e){ echo '<li>'.htmlspecialchars($e).'</li>'; } ?></ul></div><?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label class="form-label">Tanggal Kunjungan</label>
                <input type="date" name="visit_date" class="form-control" required value="<?= htmlspecialchars($old['visit_date']) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Jenis / Tipe</label>
                <input type="text" name="type" class="form-control" value="<?= htmlspecialchars($old['type']) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Deskripsi / Catatan</label>
                <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($old['description']) ?></textarea>
            </div>

            <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
            <a class="btn btn-outline-secondary" href="pet_view.php?id=<?= $visit['pet_id'] ?>">Batal</a>
        </form>
    </div>
</div>
</body>
</html>
