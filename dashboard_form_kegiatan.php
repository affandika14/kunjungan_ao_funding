<?php
session_start();
// Halaman ini bersifat umum: bisa diakses oleh petugas maupun atasan yang ingin mengisi form kegiatan
if (!isset($_SESSION['is_login'])) {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

// 2. Gunakan ID dari Session, bukan dari $_GET
$id = $_SESSION['pegawai_id'];

$stmt = $conn->prepare("SELECT * FROM pegawai WHERE id = ?");
$stmt->execute([$id]);
$p = $stmt->fetch();

// 3. Jika data pegawai tidak ditemukan (misal dihapus admin)
if (!$p) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$menus = $conn->query("SELECT * FROM reff_rencana_kegiatan")->fetchAll();

function cleanUrl($str) {
    return preg_replace('/[^a-z0-9_]/', '', str_replace([' ', '/'], '_', strtolower($str)));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Kegiatan - BPR Hijau</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { 
            background-color: #f0f7f4; 
            font-family: 'Inter', sans-serif;
        }
        
        /* Navbar Hijau Dominan */
        .navbar-custom {
            background: linear-gradient(135deg, #198754 0%, #0d5a36 100%);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* Card Menu */
        .card-menu {
            border: none;
            border-radius: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .card-menu:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 20px rgba(25, 135, 84, 0.15) !important;
            background-color: #f8fffb;
        }

        .icon-box {
            width: 70px;
            height: 70px;
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            transition: 0.3s;
        }

        .card-menu:hover .icon-box {
            background-color: #198754;
            color: white;
        }

        /* Tombol Hijau */
        .btn-green {
            background-color: #198754;
            border: none;
            color: white;
            font-weight: 600;
            border-radius: 8px;
        }

        .btn-green:hover {
            background-color: #146c43;
            color: white;
        }

        /* Tombol Riwayat (Dark Green) */
        .btn-monitoring {
            background-color: #0d4d2e;
            color: white;
            border: none;
            border-radius: 12px;
            transition: 0.3s;
        }

        .btn-monitoring:hover {
            background-color: #083620;
            transform: scale(1.02);
            color: white;
        }

        .welcome-text {
            color: #0d5a36;
            font-weight: 700;
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark navbar-custom mb-4">
    <div class="container">
        <span class="navbar-brand">
            <i class="bi bi-person-circle me-2"></i>
            Halo, <strong><?= htmlspecialchars($p['nama']) ?></strong>
        </span>
        <div class="d-flex gap-2">
            <?php if (($_SESSION['role'] ?? '') === 'atasan'): ?>
            <a href="dashboard_atasan.php" class="btn btn-sm btn-light text-success border-success rounded-pill px-3 fw-bold">
                <i class="bi bi-arrow-left-circle-fill"></i> Kembali ke Dashboard Atasan
            </a>
            <?php endif; ?>
            <a href="ganti_password.php" class="btn btn-sm btn-light text-success border-success rounded-pill px-3 fw-bold">
                <i class="bi bi-key-fill"></i> Ganti Pass
            </a>
            <a href="logout.php" class="btn btn-sm btn-danger rounded-pill px-3 fw-bold" onclick="return confirm('Apakah Anda yakin ingin keluar?')">
                <i class="bi bi-power"></i> Keluar
            </a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="text-center mb-4">
        <h4 class="welcome-text">PILIH KATEGORI LAPORAN</h4>
        <p class="text-muted">Pastikan data yang Anda masukkan sudah sesuai dengan aktivitas lapangan</p>
    </div>

    <div class="row g-4">
        <?php foreach ($menus as $m): ?>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card h-100 shadow-sm card-menu text-center p-3">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="icon-box">
                        <i class="bi bi-file-earmark-text-fill h2 mb-0"></i>
                    </div>
                    <h6 class="card-title fw-bold text-dark mb-3"><?= $m['menu'] ?></h6>
                    <a href="form_<?= cleanUrl($m['menu']) ?>.php?id_pegawai=<?= $id ?>" class="btn btn-green btn-sm mt-auto stretched-link">
                        Pilih Menu
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row mt-5">
        <div class="col-12 text-center pb-5">
            <div class="p-4 bg-white rounded-4 shadow-sm border border-success border-opacity-10">
                <h5 class="fw-bold text-success mb-3">Pemeriksaan Laporan</h5>
                <p class="small text-muted mb-4">Ingin melihat kembali data yang sudah Anda kirim atau mencetak laporan harian?</p>
                <a href="monitoring_petugas.php?id_pegawai=<?= $id ?>" class="btn btn-monitoring btn-lg px-5 shadow-sm fw-bold">
                    <i class="bi bi-printer-fill me-2"></i> Lihat & Print Riwayat Laporan
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>