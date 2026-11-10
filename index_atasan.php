<?php
include 'koneksi.php';
// Ambil daftar semua pegawai yang memiliki jabatan "Kepala" atau "Wakil"
$query = "SELECT id, nama, posisi, kantor FROM pegawai 
          WHERE posisi LIKE '%Kepala%' OR posisi LIKE '%Wakil%' 
          ORDER BY nama ASC";
$atasan_list = $conn->query($query)->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Nama Atasan - BPR Hijau</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { 
            background-color: #f0f7f4; 
            height: 100vh; 
            display: flex; 
            align-items: center; 
            font-family: 'Inter', sans-serif;
        }
        
        .card-login {
            border-radius: 20px;
            border: none;
            box-shadow: 0 10px 30px rgba(25, 135, 84, 0.15);
            overflow: hidden;
        }

        .card-header-green {
            background: linear-gradient(135deg, #198754 0%, #0d5a36 100%);
            color: white;
            padding: 40px 20px;
            border: none;
        }

        .form-label {
            font-weight: 700;
            color: #146c43;
            font-size: 0.85rem;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .form-select-lg {
            border-radius: 12px;
            border: 2px solid #e9f5ee;
            font-size: 1rem;
            padding: 12px;
        }

        .form-select-lg:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.1);
        }

        .btn-green {
            background-color: #198754;
            border: none;
            color: white;
            padding: 15px;
            font-weight: 700;
            border-radius: 12px;
            letter-spacing: 1px;
            transition: 0.3s;
        }

        .btn-green:hover {
            background-color: #0d5a36;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(25, 135, 84, 0.3);
            color: white;
        }

        .icon-circle {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card card-login shadow-lg">
                    <div class="card-header card-header-green text-center">
                        <div class="icon-circle">
                            <i class="bi bi-shield-lock-fill"></i>
                        </div>
                        <h4 class="fw-bold mb-1">MONITORING ATASAN</h4>
                        <p class="mb-0 opacity-75">Sistem Laporan Manajerial</p>
                    </div>
                    <div class="card-body p-4 p-md-5 bg-white">
                        <form action="dashboard_atasan.php" method="GET">
                            <div class="mb-4">
                                <label class="form-label text-center d-block">Pilih Nama Pejabat / Atasan</label>
                                <select name="id_pegawai" class="form-select form-select-lg" required>
                                    <option value="" selected disabled>-- Cari Nama Anda --</option>
                                    <?php foreach($atasan_list as $a): ?>
                                        <option value="<?= $a['id'] ?>">
                                            <?= htmlspecialchars($a['nama']) ?> (<?= htmlspecialchars($a['posisi']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-green w-100 shadow">
                                <i class="bi bi-box-arrow-in-right me-2"></i> MASUK KE DASHBOARD
                            </button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <a href="index.php" class="text-decoration-none text-muted small">
                                <i class="bi bi-arrow-left"></i> Kembali ke Menu Petugas
                            </a>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-3 text-muted small">
                    &copy; 2026 BPR Hijau - Integrity & Service
                </div>
            </div>
        </div>
    </div>
</body>
</html>