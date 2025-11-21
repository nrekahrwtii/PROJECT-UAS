<?php
// pages/dashboard.php
// Dashboard pengguna — menampilkan ringkasan termasuk 5 kunjungan terbaru milik user.
// Ditambah fitur filter rentang tanggal untuk kunjungan dan validasi client-side.
// Komentar: bahasa Indonesia formal.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Pengguna';

$total_pets = 0;
$total_visits = 0;
$recent_visits = [];
$filter_label = '';

// Hitung total hewan
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM pets WHERE user_id = :uid");
    $stmt->execute([':uid' => $user_id]);
    $total_pets = (int)$stmt->fetch()['total'];
} catch (PDOException $e) {
    error_log('Kesalahan saat mengambil jumlah pets: ' . $e->getMessage());
}

// Hitung total kunjungan (semua waktu)
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS total
        FROM visits
        INNER JOIN pets ON visits.pet_id = pets.id
        WHERE pets.user_id = :uid
    ");
    $stmt->execute([':uid' => $user_id]);
    $total_visits = (int)$stmt->fetch()['total'];
} catch (PDOException $e) {
    error_log('Kesalahan saat mengambil jumlah visits: ' . $e->getMessage());
}

// Ambil parameter filter dari query string (GET)
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Validasi sederhana format tanggal YYYY-MM-DD
$valid_date = function($d) {
    return $d !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
};

$whereClauses = ["pets.user_id = :uid"];
$params = [':uid' => $user_id];

// Jika ada filter yang valid, tambahkan ke where clause
if ($valid_date($start_date) && $valid_date($end_date)) {
    // jika start > end, tukar agar logika tetap benar
    if ($start_date > $end_date) {
        $tmp = $start_date;
        $start_date = $end_date;
        $end_date = $tmp;
    }
    $whereClauses[] = "visits.visit_date BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
    $filter_label = "Menampilkan kunjungan dari <strong>" . htmlspecialchars($start_date) . "</strong> sampai <strong>" . htmlspecialchars($end_date) . "</strong>.";
} elseif ($valid_date($start_date) && !$valid_date($end_date)) {
    $whereClauses[] = "visits.visit_date >= :start_date";
    $params[':start_date'] = $start_date;
    $filter_label = "Menampilkan kunjungan sejak <strong>" . htmlspecialchars($start_date) . "</strong>.";
} elseif (!$valid_date($start_date) && $valid_date($end_date)) {
    $whereClauses[] = "visits.visit_date <= :end_date";
    $params[':end_date'] = $end_date;
    $filter_label = "Menampilkan kunjungan sampai <strong>" . htmlspecialchars($end_date) . "</strong>.";
} else {
    // tidak ada filter; default label kosong
    $filter_label = '';
}

// Ambil 5 kunjungan terbaru sesuai filter (jika ada)
try {
    $whereSql = implode(' AND ', $whereClauses);
    $sql = "
        SELECT visits.id AS visit_id, visits.visit_date, visits.type, visits.description,
               pets.id AS pet_id, pets.name AS pet_name
        FROM visits
        INNER JOIN pets ON visits.pet_id = pets.id
        WHERE {$whereSql}
        ORDER BY visits.visit_date DESC, visits.created_at DESC
        LIMIT 5
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $recent_visits = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Kesalahan saat mengambil recent visits: ' . $e->getMessage());
    $recent_visits = [];
}

$flash = get_flash_success();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Dashboard - PetHouseDB</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background:#f5f7fb; font-family: Arial, sans-serif; }
    .card-compact { border-radius:10px; box-shadow:0 6px 20px rgba(55,63,104,0.06); }
</style>
</head>
<body>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Selamat datang, <?= htmlspecialchars($username) ?></h2>
        <div>
            <a href="pets_list.php" class="btn btn-outline-primary">Daftar Hewan</a>
            <a href="logout.php" class="btn btn-outline-secondary">Keluar</a>
        </div>
    </div>

    <?php if ($flash): ?><div class="alert alert-success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card p-3 card-compact">
                <div class="h6 mb-1">Total Hewan</div>
                <div class="display-6"><?= $total_pets ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 card-compact">
                <div class="h6 mb-1">Total Kunjungan</div>
                <div class="display-6"><?= $total_visits ?></div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card p-3 card-compact">
                <div class="h6 mb-1">Tindakan Cepat</div>
                <div class="d-flex gap-2">
                    <a href="pet_create.php" class="btn btn-primary btn-sm">Tambah Hewan</a>
                    <a href="pets_list.php" class="btn btn-outline-secondary btn-sm">Kelola Hewan</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter rentang tanggal untuk kunjungan -->
    <div class="card mt-4 p-3">
        <h5 class="mb-3">Filter Kunjungan berdasarkan Tanggal</h5>

        <!-- area untuk menampilkan error validasi client-side -->
        <div id="filterErrorArea"></div>

        <form id="filterForm" class="row g-2 align-items-end" method="get" action="">
            <div class="col-auto">
                <label class="form-label small">Dari</label>
                <input id="startDate" type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label small">Sampai</label>
                <input id="endDate" type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Terapkan</button>
                <a href="dashboard.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        <?php if ($filter_label): ?>
            <div class="mt-3 text-muted">Filter aktif: <?= $filter_label ?></div>
        <?php endif; ?>
    </div>

    <div class="row mt-4">
        <div class="col-lg-7">
            <div class="card p-3">
                <h5>Kunjungan Terbaru</h5>
                <?php if (empty($recent_visits)): ?>
                    <p class="text-muted">Tidak ada kunjungan dalam rentang yang dipilih.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recent_visits as $rv): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold"><?= htmlspecialchars($rv['type'] ?: 'Kunjungan') ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($rv['visit_date']) ?> — <?= htmlspecialchars($rv['description'] ?: '-') ?></div>
                                    <div class="small">Hewan: <a href="pet_view.php?id=<?= $rv['pet_id'] ?>"><?= htmlspecialchars($rv['pet_name']) ?></a></div>
                                </div>
                                <div class="text-end small">
                                    <a href="visit_edit.php?id=<?= $rv['visit_id'] ?>">Ubah</a>
                                    <br>
                                    <a href="visit_delete.php?id=<?= $rv['visit_id'] ?>&pet_id=<?= $rv['pet_id'] ?>" class="text-danger" onclick="return confirm('Yakin hapus kunjungan ini?');">Hapus</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card p-3">
                <h5>Ringkasan Cepat</h5>
                <p class="mb-1"><strong><?= $total_pets ?></strong> hewan terdaftar</p>
                <p class="mb-1"><strong><?= $total_visits ?></strong> total kunjungan</p>
                <a href="pets_list.php" class="btn btn-outline-primary btn-sm">Lihat Semua Hewan</a>
            </div>
        </div>
    </div>
</div>

<!-- Tambah script validasi client-side -->
<script>
(function(){
    const filterForm = document.getElementById('filterForm');
    const startInput = document.getElementById('startDate');
    const endInput = document.getElementById('endDate');
    const errorArea = document.getElementById('filterErrorArea');

    function clearError() {
        errorArea.innerHTML = '';
    }

    function showError(message) {
        errorArea.innerHTML = '<div class="alert alert-danger">' + message + '</div>';
        // scroll ke area error agar user melihat pesan
        errorArea.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    filterForm.addEventListener('submit', function(e) {
        clearError();

        const startVal = startInput.value;
        const endVal = endInput.value;

        // Jika kedua field kosong -> tidak ada filter, submit aman
        if (!startVal && !endVal) {
            return; // biarkan submit
        }

        // Validasi format sederhana (HTML5 date sudah memastikan format, tapi double-check)
        const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
        if (startVal && !dateRegex.test(startVal)) {
            e.preventDefault();
            showError('Format tanggal "Dari" tidak valid. Gunakan format YYYY-MM-DD.');
            return;
        }
        if (endVal && !dateRegex.test(endVal)) {
            e.preventDefault();
            showError('Format tanggal "Sampai" tidak valid. Gunakan format YYYY-MM-DD.');
            return;
        }

        // Jika kedua ada, periksa start <= end
        if (startVal && endVal) {
            if (startVal > endVal) {
                e.preventDefault();
                showError('Tanggal "Dari" tidak boleh lebih besar dari tanggal "Sampai". Silakan periksa kembali.');
                return;
            }
        }

        // Semua validasi lulus -> biarkan form submit ke server
    });

    // Hapus error saat user mengubah input
    [startInput, endInput].forEach(function(inp){
        inp.addEventListener('input', clearError);
    });
})();
</script>

</body>
</html>
