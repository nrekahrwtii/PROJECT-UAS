<?php
// pages/visit_create.php (dengan fitur preview sebelum submit)
// Komentar: bahasa Indonesia formal.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$user_id = $_SESSION['user_id'] ?? null;

$pet_id = isset($_GET['pet_id']) ? (int) $_GET['pet_id'] : 0;
if ($pet_id <= 0) {
    set_flash_success('Permintaan tidak valid.');
    header('Location: pets_list.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, user_id, name FROM pets WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $pet_id]);
    $pet = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Gagal mengambil data pet: ' . $e->getMessage());
    $pet = null;
}

if (!$pet) {
    set_flash_success('Data hewan tidak ditemukan.');
    header('Location: pets_list.php');
    exit;
}
if ($pet['user_id'] != $user_id && !has_role('admin')) {
    set_flash_success('Anda tidak memiliki izin menambah kunjungan untuk hewan ini.');
    header('Location: pets_list.php');
    exit;
}

$errors = [];
$old = [
    'visit_date' => date('Y-m-d'),
    'type' => '',
    'description' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_visit'])) {
    $visit_date = trim($_POST['visit_date'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $old = compact('visit_date','type','description');

    if ($visit_date === '') {
        $errors[] = 'Tanggal kunjungan wajib diisi.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $visit_date)) {
        $errors[] = 'Format tanggal tidak valid. Gunakan YYYY-MM-DD.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO visits (pet_id, visit_date, type, description, created_at)
                VALUES (:pet_id, :visit_date, :type, :description, NOW())
            ");
            $stmt->execute([
                ':pet_id' => $pet_id,
                ':visit_date' => $visit_date,
                ':type' => $type ?: null,
                ':description' => $description ?: null
            ]);

            set_flash_success('Kunjungan berhasil ditambahkan.');
            header('Location: pet_view.php?id=' . $pet_id);
            exit;
        } catch (PDOException $e) {
            error_log('Gagal menyimpan kunjungan: ' . $e->getMessage());
            $errors[] = 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.';
        }
    }
}

$flash = get_flash_success();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Tambah Kunjungan - PetHouseDB</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card p-4">
        <div class="d-flex justify-content-between mb-3">
            <h5>Tambah Kunjungan untuk: <?= htmlspecialchars($pet['name']) ?></h5>
            <a class="btn btn-secondary" href="pet_view.php?id=<?= $pet_id ?>">Kembali</a>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><ul class="mb-0">
                <?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
            </ul></div>
        <?php endif; ?>

        <form id="visitForm" method="post" novalidate>
            <div class="mb-3">
                <label class="form-label">Tanggal Kunjungan</label>
                <input id="visit_date" type="date" name="visit_date" class="form-control" required value="<?= htmlspecialchars($old['visit_date']) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Jenis / Tipe</label>
                <input id="type" type="text" name="type" class="form-control" placeholder="mis. Vaksin / Pemeriksaan" value="<?= htmlspecialchars($old['type']) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Deskripsi / Catatan</label>
                <textarea id="description" name="description" class="form-control" rows="4"><?= htmlspecialchars($old['description']) ?></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="button" id="previewBtn" class="btn btn-outline-primary">Preview</button>
                <button type="submit" name="submit_visit" class="btn btn-primary">Simpan</button>
                <a class="btn btn-outline-secondary" href="pet_view.php?id=<?= $pet_id ?>">Batal</a>
            </div>
        </form>
    </div>
</div>

<!-- Modal preview -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Preview Kunjungan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <dl class="row mb-0">
            <dt class="col-4">Tanggal</dt>
            <dd class="col-8" id="pv_date"></dd>

            <dt class="col-4">Jenis</dt>
            <dd class="col-8" id="pv_type"></dd>

            <dt class="col-4">Deskripsi</dt>
            <dd class="col-8" id="pv_description"></dd>
        </dl>
      </div>
      <div class="modal-footer">
        <button type="button" id="confirmSubmit" class="btn btn-primary">Kirim</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Script untuk menampilkan preview modal dan mengirim form jika dikonfirmasi.
    const previewBtn = document.getElementById('previewBtn');
    const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
    const pv_date = document.getElementById('pv_date');
    const pv_type = document.getElementById('pv_type');
    const pv_description = document.getElementById('pv_description');
    const visitForm = document.getElementById('visitForm');
    const confirmSubmit = document.getElementById('confirmSubmit');

    previewBtn.addEventListener('click', () => {
        // Ambil nilai dari form
        const date = document.getElementById('visit_date').value || '-';
        const type = document.getElementById('type').value || '-';
        const description = document.getElementById('description').value || '-';

        // Isi modal
        pv_date.textContent = date;
        pv_type.textContent = type;
        pv_description.textContent = description;

        // Tampilkan modal
        previewModal.show();
    });

    // Jika pengguna menekan 'Kirim' pada modal, submit form
    confirmSubmit.addEventListener('click', () => {
        // Tambah input tersembunyi untuk menandai submit via tombol modal (tetap menggunakan name submit_visit)
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'submit_visit';
        hidden.value = '1';
        visitForm.appendChild(hidden);

        visitForm.submit();
    });
</script>
</body>
</html>
