<?php
// pages/login.php
// Halaman login untuk PetHouseDB
// Komentar dan instruksi disusun dalam bahasa Indonesia formal.

// Muat koneksi database dan fungsi autentikasi.
// Pastikan file-file ini ada di folder includes sesuai struktur proyek.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Jika pengguna sudah login, arahkan ke dashboard.
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

// Inisialisasi variabel
$errors = [];
$old = ['identifier' => '']; // identifier = username atau email

// Proses form jika metode POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? ''); // username atau email
    $password = $_POST['password'] ?? '';

    // Simpan kembali input lama agar form terisi kembali jika ada error
    $old['identifier'] = $identifier;

    // Validasi sederhana
    if ($identifier === '') {
        $errors[] = 'Nama pengguna atau email wajib diisi.';
    }
    if ($password === '') {
        $errors[] = 'Kata sandi wajib diisi.';
    }

    // Jika tidak ada error awal, cek pada basis data
    if (empty($errors)) {
        try {
            // Cari user berdasarkan username atau email
            $stmt = $pdo->prepare("SELECT id, username, email, password, role FROM users WHERE username = :ident OR email = :ident LIMIT 1");
            $stmt->execute([':ident' => $identifier]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Login sukses: set session
                // Gunakan nama session yang jelas agar mudah dipahami saat debugging
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Set pesan singkat (flash) lalu arahkan ke dashboard
                $_SESSION['flash_success'] = 'Login berhasil. Selamat datang, ' . $user['username'] . '.';
                header('Location: dashboard.php');
                exit;
            } else {
                $errors[] = 'Nama pengguna/email atau kata sandi tidak sesuai.';
            }
        } catch (PDOException $e) {
            // Catat error pada log server, jangan tampilkan detil ke user
            error_log('Gagal melakukan query login: ' . $e->getMessage());
            $errors[] = 'Terjadi kesalahan saat memeriksa kredensial. Silakan coba lagi.';
        }
    }
}

// Jika ada pesan flash sukses dari proses lain, tampilkan lalu hapus
$flash_success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Login - PetHouseDB</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Anda dapat mengganti dengan Bootstrap CDN atau file CSS lokal -->
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        /* Gaya minimal agar tampilan rapi tanpa framework */
        body { font-family: Arial, sans-serif; background:#f7f7f7; }
        .container { max-width:420px; margin:60px auto; background:#fff; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,0.06); border-radius:6px; }
        .form-group { margin-bottom:12px; }
        label { display:block; margin-bottom:6px; font-weight:600; }
        input[type="text"], input[type="password"] { width:100%; padding:8px 10px; border:1px solid #ddd; border-radius:4px; }
        .btn { display:inline-block; padding:8px 14px; border-radius:4px; border:none; cursor:pointer; }
        .btn-primary { background:#007bff; color:#fff; }
        .btn-secondary { background:#6c757d; color:#fff; text-decoration:none; padding:8px 12px; border-radius:4px; }
        .alert { padding:10px; margin-bottom:12px; border-radius:4px; }
        .alert-danger { background:#ffe6e6; color:#a33; }
        .alert-success { background:#e6ffed; color:#1a7a2e; }
    </style>
</head>
<body>
<div class="container">
    <h2>Masuk ke PetHouseDB</h2>

    <!-- Tampilkan pesan flash bila ada -->
    <?php if ($flash_success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div>
    <?php endif; ?>

    <!-- Tampilkan error bila ada -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul style="margin:0; padding-left:18px;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="" method="post" novalidate>
        <div class="form-group">
            <label for="identifier">Nama Pengguna atau Email</label>
            <input id="identifier" name="identifier" type="text" value="<?= htmlspecialchars($old['identifier']) ?>" required>
        </div>

        <div class="form-group">
            <label for="password">Kata Sandi</label>
            <input id="password" name="password" type="password" required>
        </div>

        <div style="display:flex; gap:8px; align-items:center;">
            <button type="submit" class="btn btn-primary">Masuk</button>
            <a class="btn-secondary" href="register.php">Daftar</a>
        </div>
    </form>

    <p style="margin-top:12px; font-size:0.9rem; color:#555;">
        Jika Anda lupa kata sandi, silakan hubungi administrator atau gunakan mekanisme reset kata sandi bila telah tersedia.
    </p>
</div>
</body>
</html>
