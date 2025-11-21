<?php
// pages/register.php
// Halaman pendaftaran akun baru untuk PetHouseDB.
// Komentar ditulis dalam bahasa Indonesia formal agar mudah dipahami.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Jika sudah login, langsung arahkan ke dashboard.
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

// Variabel untuk menampung error dan nilai input lama.
$errors = [];
$old = [
    'username' => '',
    'email' => ''
];

// Proses form apabila metode POST digunakan.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ambil nilai input dari form dan lakukan pembersihan sederhana.
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Simpan nilai lama agar ditampilkan kembali saat terjadi error.
    $old['username'] = $username;
    $old['email'] = $email;

    // Validasi input dasar.
    if ($username === '') {
        $errors[] = 'Nama pengguna wajib diisi.';
    }
    if ($email === '') {
        $errors[] = 'Alamat email wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }
    if ($password === '') {
        $errors[] = 'Kata sandi wajib diisi.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Konfirmasi kata sandi tidak sesuai.';
    }

    // Jika tidak ada error awal, lakukan penyimpanan ke basis data.
    if (empty($errors)) {
        try {
            // Periksa apakah username atau email sudah terdaftar.
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1");
            $stmt->execute([
                ':u' => $username,
                ':e' => $email
            ]);

            if ($stmt->fetch()) {
                $errors[] = 'Nama pengguna atau email sudah digunakan.';
            } else {
                // Hash password sebelum disimpan.
                $hash = password_hash($password, PASSWORD_DEFAULT);

                // Simpan pengguna baru sebagai role 'owner'.
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, role)
                    VALUES (:u, :e, :p, 'owner')
                ");
                $stmt->execute([
                    ':u' => $username,
                    ':e' => $email,
                    ':p' => $hash
                ]);

                // Buat pesan flash sukses lalu arahkan ke halaman login.
                set_flash_success('Pendaftaran berhasil. Silakan masuk menggunakan akun baru Anda.');
                header('Location: login.php');
                exit;
            }

        } catch (PDOException $e) {
            error_log('Kesalahan pendaftaran: ' . $e->getMessage());
            $errors[] = 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.';
        }
    }
}

// Ambil pesan flash apabila ada.
$flash_success = get_flash_success();

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Akun - PetHouseDB</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body { font-family: Arial, sans-serif; background:#f7f7f7; }
        .container { max-width:450px; margin:60px auto; background:#fff; padding:20px; border-radius:6px; box-shadow:0 2px 8px rgba(0,0,0,0.07); }
        .form-group { margin-bottom:12px; }
        label { display:block; margin-bottom:6px; font-weight:600; }
        input[type=\"text\"], input[type=\"email\"], input[type=\"password\"] {
            width:100%; padding:8px; border:1px solid #ccc; border-radius:4px;
        }
        .btn { display:inline-block; padding:8px 14px; background:#007bff; color:#fff; border:none; border-radius:4px; cursor:pointer; }
        .btn-secondary { background:#6c757d; text-decoration:none; padding:8px 12px; border-radius:4px; }
        .alert { padding:10px; border-radius:4px; margin-bottom:12px; }
        .alert-danger { background:#ffe6e6; color:#a33; }
        .alert-success { background:#e6ffed; color:#1a7a2e; }
    </style>
</head>
<body>
<div class="container">
    <h2>Pendaftaran Akun Baru</h2>

    <!-- Pesan sukses (jika ada) -->
    <?php if ($flash_success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div>
    <?php endif; ?>

    <!-- Daftar error -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul style="margin:0;padding-left:18px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="" method="post" novalidate>
        <div class="form-group">
            <label for="username">Nama Pengguna</label>
            <input id="username" type="text" name="username" value="<?= htmlspecialchars($old['username']) ?>" required>
        </div>

        <div class="form-group">
            <label for="email">Alamat Email</label>
            <input id="email" type="email" name="email" value="<?= htmlspecialchars($old['email']) ?>" required>
        </div>

        <div class="form-group">
            <label for="password">Kata Sandi</label>
            <input id="password" type="password" name="password" required>
        </div>

        <div class="form-group">
            <label for="confirm_password">Konfirmasi Kata Sandi</label>
            <input id="confirm_password" type="password" name="confirm_password" required>
        </div>

        <button type="submit" class="btn">Daftar</button>
        <a href="login.php" class="btn-secondary">Masuk</a>
    </form>
</div>
</body>
</html>
