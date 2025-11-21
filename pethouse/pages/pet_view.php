<?php
// pages/pet_view.php (versi diperbarui - foto 1:1 dan lightbox preview)
// Komentar: bahasa Indonesia formal.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user_id = $_SESSION['user_id'] ?? null;

// Ambil id hewan
$pet_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($pet_id <= 0) {
    set_flash_success('Permintaan tidak valid.');
    header('Location: pets_list.php');
    exit;
}

$pet = null;
$visits = [];
$errors = [];
$uploads_diskpath = __DIR__ . '/../assets/uploads/';
$uploads_webpath = '../assets/uploads/';

try {
    $stmt = $pdo->prepare("SELECT p.*, u.username AS owner_username FROM pets p INNER JOIN users u ON p.user_id = u.id WHERE p.id = :pid LIMIT 1");
    $stmt->execute([':pid' => $pet_id]);
    $pet = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Gagal mengambil data pet: ' . $e->getMessage());
    $errors[] = 'Terjadi kesalahan saat memuat data hewan.';
}

if (!$pet) {
    set_flash_success('Data hewan tidak ditemukan.');
    header('Location: pets_list.php');
    exit;
}

if ($pet['user_id'] != $user_id && !has_role('admin')) {
    set_flash_success('Anda tidak memiliki izin untuk melihat data hewan ini.');
    header('Location: pets_list.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, visit_date, type, description, created_at FROM visits WHERE pet_id = :pid ORDER BY visit_date DESC, created_at DESC");
    $stmt->execute([':pid' => $pet_id]);
    $visits = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Gagal mengambil visits: ' . $e->getMessage());
    $errors[] = 'Gagal memuat riwayat kunjungan.';
}

$flash = get_flash_success();

// Jika file foto tidak ditemukan di uploads, gunakan file contoh yang diunggah ke sesi ini.
// NOTE: sistem akan menggantikan path lokal ini menjadi URL yang dapat diakses oleh browser.
$uploaded_example_local_path = '/mnt/data/0e2be7ae-3cec-45f6-a3ed-128fd9f49d12.png';

// Tentukan image src yang akan dipakai di halaman (prioritas: file pada uploads, lalu contoh lokal)
$photo_src = null;
if (!empty($pet['photo']) && file_exists($uploads_diskpath . $pet['photo'])) {
    $photo_src = $uploads_webpath . $pet['photo'];
} elseif (file_exists($uploaded_example_local_path)) {
    // gunakan path lokal file yang telah diupload ke lingkungan ini — akan ditransformasikan menjadi URL oleh sistem.
    $photo_src = $uploaded_example_local_path;
}

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail Hewan — <?= htmlspecialchars($pet['name']) ?></title>
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f6f7fb; }
        .card-hero { border-radius: 12px; box-shadow: 0 6px 20px rgba(55,63,104,0.08); }

        /* Foto 1:1 (square) menggunakan aspect-ratio sehingga selalu proporsional */
        .photo-box {
            width: 100%;
            max-width: 360px;
            aspect-ratio: 1 / 1; /* memastikan rasio 1:1 */
            border-radius: 12px;
            overflow: hidden;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 14px rgba(0,0,0,0.06);
        }
        .photo-box img { width: 100%; height: 100%; object-fit: cover; display:block; }

        .label { font-weight:600; color:#374151; }
        .value { color:#111827; }

        @media (max-width: 768px) {
            .photo-box { max-width: 100%; height: auto; }
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="card card-hero p-4">
        <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
            <div>
                <h4 class="mb-0">Detail Hewan</h4>
                <small class="text-muted">Pemilik: <?= htmlspecialchars($pet['owner_username']) ?></small>
            </div>
            <div class="d-flex gap-2">
                <a href="visit_create.php?pet_id=<?= $pet_id ?>" class="btn btn-primary">Tambah Kunjungan</a>
                <a href="pet_edit.php?id=<?= $pet_id ?>" class="btn btn-outline-primary">Ubah</a>
                <a href="pet_delete.php?id=<?= $pet_id ?>" class="btn btn-outline-danger" onclick="return confirm('Yakin menghapus hewan ini? Semua data terkait akan dihapus.');">Hapus</a>
                <a href="pets_list.php" class="btn btn-secondary">Kembali</a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row g-4 align-items-start">
            <div class="col-md-4 text-center">
                <div class="photo-box mx-auto">
                    <?php if ($photo_src): ?>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#photoModal">
                            <img id="pet-main-photo" src="<?= htmlspecialchars($photo_src) ?>" alt="<?= htmlspecialchars($pet['name']) ?>">
                        </a>
                    <?php else: ?>
                        <div class="text-muted">Tidak ada foto</div>
                    <?php endif; ?>
                </div>

                <div class="mt-3 text-center">
                    <h5 class="mb-0"><?= htmlspecialchars($pet['name']) ?></h5>
                    <div class="meta mt-1 small">
                        <div>Spesies / Ras: <strong><?= htmlspecialchars($pet['species'] ?: '-') ?> / <?= htmlspecialchars($pet['breed'] ?: '-') ?></strong></div>
                        <div>Jenis Kelamin: <strong><?= htmlspecialchars($pet['gender']) ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="mb-3">
                    <span class="text-muted">Ditambahkan pada</span>
                    <div class="fw-semibold"><?= htmlspecialchars($pet['created_at']) ?></div>
                </div>

                <div class="row g-2">
                    <div class="col-sm-6">
                        <div class="p-3 bg-white rounded-3">
                            <div class="label">Nama</div>
                            <div class="value"><?= htmlspecialchars($pet['name']) ?></div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 bg-white rounded-3">
                            <div class="label">Tanggal Lahir</div>
                            <div class="value"><?= htmlspecialchars($pet['birth_date'] ?: '-') ?></div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 bg-white rounded-3">
                            <div class="label">Spesies</div>
                            <div class="value"><?= htmlspecialchars($pet['species'] ?: '-') ?></div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="p-3 bg-white rounded-3">
                            <div class="label">Ras</div>
                            <div class="value"><?= htmlspecialchars($pet['breed'] ?: '-') ?></div>
                        </div>
                    </div>
                </div>

                <div class="mt-3 p-3 bg-white rounded-3">
                    <div class="label">Catatan</div>
                    <div class="value"><?= nl2br(htmlspecialchars($pet['notes'] ?: '-')) ?></div>
                </div>

                <div class="mt-4">
                    <h5>Kunjungan / Riwayat</h5>
                    <?php if (empty($visits)): ?>
                        <div class="p-3 bg-white rounded-3 text-muted">Belum ada data kunjungan untuk hewan ini.</div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($visits as $v): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($v['type'] ?: 'Kunjungan') ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($v['visit_date']) ?> — <?= htmlspecialchars($v['description'] ?: '-') ?></div>
                                    </div>
                                    <div class="text-end small">
                                        <a href="visit_edit.php?id=<?= $v['id'] ?>" class="me-2">Ubah</a>
                                        <a href="visit_delete.php?id=<?= $v['id'] ?>&pet_id=<?= $pet_id ?>" class="text-danger" onclick="return confirm('Yakin hapus kunjungan ini?');">Hapus</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Modal Lightbox untuk memperbesar foto saat diklik -->
<div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-body p-0">
        <?php if ($photo_src): ?>
            <img id="modal-photo" src="<?= htmlspecialchars($photo_src) ?>" alt="<?= htmlspecialchars($pet['name']) ?>" style="width:100%;height:auto;display:block;">
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
