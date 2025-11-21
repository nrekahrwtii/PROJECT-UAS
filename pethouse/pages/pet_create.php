<?php
// pages/pet_create.php
// Form dan pemrosesan penambahan hewan peliharaan baru.
// Komentar disusun dalam bahasa Indonesia formal.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Pastikan pengguna telah login sebelum menambah hewan.
require_login();

// Ambil informasi pengguna dari session.
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Pengguna';

// Inisialisasi variabel untuk menampung error dan nilai lama.
$errors = [];
$old = [
    'name' => '',
    'species' => '',
    'breed' => '',
    'birth_date' => '',
    'gender' => 'unknown',
    'notes' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil dan bersihkan input
    $name = trim($_POST['name'] ?? '');
    $species = trim($_POST['species'] ?? '');
    $breed = trim($_POST['breed'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $gender = $_POST['gender'] ?? 'unknown';
    $notes = trim($_POST['notes'] ?? '');

    $old = compact('name','species','breed','birth_date','gender','notes');

    // Validasi input dasar
    if ($name === '') {
        $errors[] = 'Nama hewan wajib diisi.';
    }
    if ($birth_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        $errors[] = 'Format tanggal lahir tidak valid. Gunakan format YYYY-MM-DD.';
    }
    if (!in_array($gender, ['male','female','unknown'], true)) {
        $gender = 'unknown';
    }

    // Proses upload foto jika ada
    $photo_filename = null;
    if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['photo'];

        // Periksa error upload dasar
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Terjadi kesalahan saat mengunggah foto.';
        } else {
            // Periksa mime type menggunakan finfo (lebih aman daripada trusting $_FILES['type'])
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!array_key_exists($mime, $allowed_types)) {
                $errors[] = 'Tipe file tidak diizinkan. Hanya JPG, PNG, atau WEBP.';
            }

            // Batasi ukuran file (2MB)
            $maxSize = 2 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                $errors[] = 'Ukuran file melebihi batas maksimum 2 MB.';
            }

            // Jika valid, simpan file dengan nama unik
            if (empty($errors)) {
                $ext = $allowed_types[$mime];
                try {
                    $newName = 'pet_' . bin2hex(random_bytes(8)) . '.' . $ext;
                } catch (Exception $e) {
                    // fallback jika random_bytes gagal
                    $newName = 'pet_' . uniqid() . '.' . $ext;
                }

                $uploadDir = __DIR__ . '/../assets/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $destination = $uploadDir . $newName;
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $errors[] = 'Gagal menyimpan file foto ke direktori uploads.';
                } else {
                    $photo_filename = $newName;
                }
            }
        }
    }

    // Jika tidak ada error, simpan data ke database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO pets (user_id, name, species, breed, birth_date, gender, photo, notes, created_at)
                VALUES (:user_id, :name, :species, :breed, NULLIF(:birth_date, ''), :gender, :photo, :notes, NOW())
            ");
            $stmt->execute([
                ':user_id' => $user_id,
                ':name' => $name,
                ':species' => $species ?: null,
                ':breed' => $breed ?: null,
                ':birth_date' => $birth_date ?: null,
                ':gender' => $gender,
                ':photo' => $photo_filename,
                ':notes' => $notes ?: null
            ]);

            // Set pesan sukses dan arahkan ke daftar hewan.
            set_flash_success('Data hewan berhasil ditambahkan.');
            header('Location: pets_list.php');
            exit;

        } catch (PDOException $e) {
            error_log('Gagal menyimpan data pet: ' . $e->getMessage());
            $errors[] = 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.';
            // Jika foto telah tersimpan namun DB gagal, hapus file agar tidak menumpuk
            if ($photo_filename && isset($destination) && file_exists($destination)) {
                @unlink($destination);
            }
        }
    }
}

// Jika ada pesan flash, ambil dan kosongkan
$flash = get_flash_success();

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Tambah Hewan - PetHouseDB</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; background:#f7f7f7; }
        .container { max-width:640px; margin:40px auto; background:#fff; padding:18px; border-radius:6px; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
        .form-group { margin-bottom:12px; }
        label { display:block; margin-bottom:6px; font-weight:600; }
        input[type="text"], input[type="date"], select, textarea { width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; }
        input[type="file"] { padding:6px 0; }
        .btn { padding:8px 12px; background:#007bff; color:#fff; border:none; border-radius:4px; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-muted { background:#6c757d; color:#fff; text-decoration:none; padding:8px 12px; border-radius:4px; }
        .alert { padding:10px; border-radius:4px; margin-bottom:12px; }
        .alert-danger { background:#ffe6e6; color:#a33; }
        .alert-success { background:#e6ffed; color:#1a7a2e; }
    </style>
</head>
<body>
<div class="container">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <div>
            <strong>Tambah Hewan</strong>
            <div style="font-size:0.9rem;color:#555;">Pengguna: <?= htmlspecialchars($username) ?></div>
        </div>
        <div>
            <a class="btn-muted" href="pets_list.php">Kembali ke Daftar Hewan</a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul style="margin:0;padding-left:18px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="" method="post" enctype="multipart/form-data" novalidate>
        <div class="form-group">
            <label for="name">Nama Hewan <span style="color:darkred">*</span></label>
            <input id="name" name="name" type="text" required value="<?= htmlspecialchars($old['name']) ?>">
        </div>

        <div class="form-group">
            <label for="species">Spesies</label>
            <input id="species" name="species" type="text" value="<?= htmlspecialchars($old['species']) ?>">
        </div>

        <div class="form-group">
            <label for="breed">Ras / Tipe</label>
            <input id="breed" name="breed" type="text" value="<?= htmlspecialchars($old['breed']) ?>">
        </div>

        <div class="form-group">
            <label for="birth_date">Tanggal Lahir (YYYY-MM-DD)</label>
            <input id="birth_date" name="birth_date" type="date" value="<?= htmlspecialchars($old['birth_date']) ?>">
        </div>

        <div class="form-group">
            <label for="gender">Jenis Kelamin</label>
            <select id="gender" name="gender">
                <option value="unknown" <?= $old['gender'] === 'unknown' ? 'selected' : '' ?>>Tidak Diketahui</option>
                <option value="male" <?= $old['gender'] === 'male' ? 'selected' : '' ?>>Jantan</option>
                <option value="female" <?= $old['gender'] === 'female' ? 'selected' : '' ?>>Betina</option>
            </select>
        </div>

        <div class="form-group">
            <label for="photo">Foto Hewan (opsional, max 2MB)</label>
            <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
        </div>

        <div class="form-group">
            <label for="notes">Catatan</label>
            <textarea id="notes" name="notes" rows="4"><?= htmlspecialchars($old['notes']) ?></textarea>
        </div>

        <button type="submit" class="btn">Simpan</button>
        <a class="btn-muted" href="pets_list.php">Batal</a>
    </form>
</div>
</body>
</html>
