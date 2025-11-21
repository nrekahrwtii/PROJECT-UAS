<?php
// pages/pet_edit.php
// Halaman untuk mengubah data hewan peliharaan.
// Semua komentar ditulis dalam bahasa Indonesia formal.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Pastikan pengguna telah login.
require_login();

$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'Pengguna';

// Ambil id hewan dari query string dan validasi.
$pet_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($pet_id <= 0) {
    set_flash_success('Permintaan tidak valid.');
    header('Location: pets_list.php');
    exit;
}

$errors = [];
$old = [
    'name' => '',
    'species' => '',
    'breed' => '',
    'birth_date' => '',
    'gender' => 'unknown',
    'notes' => ''
];

$pet = null;
$uploads_diskpath = __DIR__ . '/../assets/uploads/';

// Ambil data pet dari database
try {
    $stmt = $pdo->prepare("SELECT * FROM pets WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $pet_id]);
    $pet = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Gagal mengambil data pet untuk edit: ' . $e->getMessage());
    $errors[] = 'Gagal memuat data hewan.';
}

// Jika tidak ditemukan, kembali ke daftar
if (!$pet) {
    set_flash_success('Data hewan tidak ditemukan.');
    header('Location: pets_list.php');
    exit;
}

// Cek ownership: hanya owner yang boleh mengedit (admin boleh kalau ada role admin)
if ($pet['user_id'] != $user_id && !has_role('admin')) {
    set_flash_success('Anda tidak memiliki izin untuk mengubah data hewan ini.');
    header('Location: pets_list.php');
    exit;
}

// Isi $old dari data saat ini untuk menampilkan di form
$old['name'] = $pet['name'];
$old['species'] = $pet['species'];
$old['breed'] = $pet['breed'];
$old['birth_date'] = $pet['birth_date'];
$old['gender'] = $pet['gender'];
$old['notes'] = $pet['notes'];

// Proses form saat POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil input dan bersihkan
    $name = trim($_POST['name'] ?? '');
    $species = trim($_POST['species'] ?? '');
    $breed = trim($_POST['breed'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $gender = $_POST['gender'] ?? 'unknown';
    $notes = trim($_POST['notes'] ?? '');

    // Update nilai lama untuk refill form jika error
    $old = compact('name','species','breed','birth_date','gender','notes');

    // Validasi sederhana
    if ($name === '') {
        $errors[] = 'Nama hewan wajib diisi.';
    }
    if ($birth_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        $errors[] = 'Format tanggal lahir tidak valid. Gunakan format YYYY-MM-DD.';
    }
    if (!in_array($gender, ['male','female','unknown'], true)) {
        $gender = 'unknown';
    }

    // Siapkan variable untuk foto baru (jika diupload)
    $new_photo = null;
    $old_photo = $pet['photo'] ?? null;

    if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['photo'];

        // Periksa error upload dasar
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Terjadi kesalahan saat mengunggah foto.';
        } else {
            // Periksa mime type (lebih aman)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!array_key_exists($mime, $allowed_types)) {
                $errors[] = 'Tipe file tidak diizinkan. Hanya JPG, PNG, atau WEBP.';
            }

            // Batasi ukuran file (2 MB)
            $maxSize = 2 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                $errors[] = 'Ukuran file melebihi batas maksimum 2 MB.';
            }

            // Jika valid, siapkan penyimpanan file baru
            if (empty($errors)) {
                $ext = $allowed_types[$mime];
                try {
                    $newName = 'pet_' . bin2hex(random_bytes(8)) . '.' . $ext;
                } catch (Exception $e) {
                    $newName = 'pet_' . uniqid() . '.' . $ext;
                }

                if (!is_dir($uploads_diskpath)) {
                    mkdir($uploads_diskpath, 0755, true);
                }

                $destination = $uploads_diskpath . $newName;
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $errors[] = 'Gagal menyimpan file foto ke direktori uploads.';
                } else {
                    $new_photo = $newName;
                }
            }
        }
    }

    // Jika tidak ada error, lakukan update ke database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE pets
                SET name = :name,
                    species = NULLIF(:species, ''),
                    breed = NULLIF(:breed, ''),
                    birth_date = NULLIF(:birth_date, ''),
                    gender = :gender,
                    photo = :photo,
                    notes = NULLIF(:notes, '')
                WHERE id = :id
            ");

            // Jika tidak mengupload foto baru, jangan ubah field photo (pakai nama file lama)
            $photo_to_store = $new_photo ?? $old_photo;

            $stmt->execute([
                ':name' => $name,
                ':species' => $species,
                ':breed' => $breed,
                ':birth_date' => $birth_date ?: null,
                ':gender' => $gender,
                ':photo' => $photo_to_store,
                ':notes' => $notes,
                ':id' => $pet_id
            ]);

            // Jika ada foto lama dan kita upload foto baru, hapus file lama untuk menghemat storage.
            if ($new_photo && $old_photo) {
                $oldPath = $uploads_diskpath . $old_photo;
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }

            set_flash_success('Data hewan berhasil diperbarui.');
            header('Location: pet_view.php?id=' . $pet_id);
            exit;

        } catch (PDOException $e) {
            error_log('Gagal memperbarui data pet: ' . $e->getMessage());
            $errors[] = 'Terjadi kesalahan saat menyimpan perubahan. Silakan coba lagi.';
            // Jika file baru berhasil diupload namun DB gagal, hapus file baru agar tidak menumpuk
            if (isset($destination) && file_exists($destination)) {
                @unlink($destination);
            }
        }
    } else {
        // jika ada error dan kita sudah mengupload file baru, hapus file baru agar tidak menumpuk
        if (isset($destination) && file_exists($destination)) {
            @unlink($destination);
        }
    }
}

// Ambil pesan flash jika ada
$flash = get_flash_success();

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Ubah Hewan - PetHouseDB</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; background:#f7f7f7; }
        .container { max-width:700px; margin:30px auto; background:#fff; padding:18px; border-radius:6px; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
        .form-group { margin-bottom:12px; }
        label { display:block; margin-bottom:6px; font-weight:600; }
        input[type="text"], input[type="date"], textarea, select { width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; }
        input[type="file"] { padding:6px 0; }
        .btn { padding:8px 12px; background:#007bff; color:#fff; border:none; border-radius:4px; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-muted { background:#6c757d; color:#fff; text-decoration:none; padding:8px 12px; border-radius:4px; }
        .alert { padding:10px; border-radius:4px; margin-bottom:12px; }
        .alert-danger { background:#ffe6e6; color:#a33; }
        .alert-success { background:#e6ffed; color:#1a7a2e; }
        .current-photo { width:120px; height:120px; object-fit:cover; border-radius:6px; border:1px solid #eee; display:inline-block; margin-top:6px; }
    </style>
</head>
<body>
<div class="container">
    <a class="btn-muted" href="pet_view.php?id=<?= $pet_id ?>">Kembali</a>
    <h2>Ubah Data Hewan</h2>

    <?php if ($flash): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul style="margin:0; padding-left:18px;">
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
            <label for="birth_date">Tanggal Lahir</label>
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
            <label>Foto Saat Ini</label><br>
            <?php if (!empty($pet['photo']) && file_exists($uploads_diskpath . $pet['photo'])): ?>
                <img src="../assets/uploads/<?= htmlspecialchars($pet['photo']) ?>" class="current-photo" alt="Foto <?=$pet['name']?>">
            <?php else: ?>
                <div style="width:120px;height:120px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#888;border-radius:6px;">Tidak ada foto</div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="photo">Ganti Foto (opsional, max 2MB)</label>
            <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
            <div style="font-size:0.9rem;color:#555;margin-top:6px;">Jika Anda tidak memilih file, foto lama akan tetap digunakan.</div>
        </div>

        <div class="form-group">
            <label for="notes">Catatan</label>
            <textarea id="notes" name="notes" rows="4"><?= htmlspecialchars($old['notes']) ?></textarea>
        </div>

        <button type="submit" class="btn">Simpan Perubahan</button>
        <a class="btn-muted" href="pets_list.php">Batal</a>
    </form>
</div>
</body>
</html>
