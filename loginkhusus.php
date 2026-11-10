<?php
include 'koneksi.php';
// Ambil daftar nama dari tabel pegawai untuk datalist
$query = "SELECT p.nama 
          FROM pegawai p 
          JOIN users u ON p.id = u.pegawai_id 
          WHERE u.status = 'aktif' 
          AND p.jabatan NOT LIKE '%Admin%' 
          ORDER BY p.nama ASC";

$daftar_nama = $conn->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - BPR Ekadharma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { 
            background-color: #f0f7f4; 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 20px 0; /* Memberi ruang saat layar sangat pendek */
        }
        
        .card-login { 
            border: none; 
            border-radius: 20px; 
            box-shadow: 0 10px 40px rgba(25, 135, 84, 0.2); 
            overflow: hidden;
            width: 100%;
        }

        .header-green { 
            background: linear-gradient(135deg, #198754 0%, #0d5a36 100%); 
            color: white; 
            padding: 35px 20px; 
            border: none; 
        }

        .header-green h4 {
            font-size: 1.5rem;
            letter-spacing: 1px;
        }

        /* Optimalisasi Input untuk Layar Sentuh */
        .form-control {
            padding: 12px 15px;
            font-size: 16px; /* Ukuran minimal 16px mencegah auto-zoom pada iPhone */
            border-radius: 10px;
            border: 1px solid #ced4da;
        }

        .form-control:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.15);
        }

        .btn-green { 
            background-color: #198754; 
            color: white; 
            font-weight: 700; 
            border-radius: 10px; 
            transition: 0.3s; 
            width: 100%; 
            border: none; 
            padding: 14px;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-green:hover { 
            background-color: #0d5a36; 
            color: white;
            box-shadow: 0 5px 15px rgba(25, 135, 84, 0.3);
        }

        .form-label {
            margin-bottom: 8px;
            font-size: 0.85rem;
        }

        .logo-ekadharma {
            width: 300px;
            margin-bottom: 10px;
            background-color: white;
            padding: 10px;
            border-radius: 10px;
        }

        /* Container adjustment for mobile */
        @media (max-width: 576px) {
            .container {
                padding-left: 20px;
                padding-right: 20px;
            }
            .header-green {
                padding: 25px 15px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card card-login shadow-lg">
                <div class="header-green text-center">
                    <!-- <i class="bi bi-shield-lock-fill display-5 mb-2 d-block"></i> -->
                     <i class="container-logo">
                         <img src="img/logo bank ekadharma 12.png" alt="logo" class="logo-ekadharma">
                     </i>
                    <h4 class="fw-bold mb-0">LOGIN SISTEM</h4>
                    <small class="opacity-75">BPR Ekadharma - Divisi Marketing</small>
                </div>
                <div class="card-body p-4 p-md-5 bg-white">
                    <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'gagal'): ?>
                        <div class="alert alert-danger small py-2 text-center border-0 rounded-3 mb-4">
                           <i class="bi bi-exclamation-circle me-1"></i> Nama atau PIN salah!
                        </div>
                    <?php endif; ?>

                    <form action="proses_verifikasi.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-success text-uppercase">Nama Lengkap</label>
                            <input type="text" name="username" class="form-control" list="list_pegawai" placeholder="Pilih atau ketik nama..." autocomplete="off" required>
                            <datalist id="list_pegawai">
                                <?php foreach($daftar_nama as $row): ?>
                                    <option value="<?= htmlspecialchars($row['nama']) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold text-success text-uppercase">Password / PIN</label>
                            <input type="password" name="password" class="form-control" placeholder="Masukkan PIN Anda" required>
                        </div>
                        <button type="submit" class="btn btn-green shadow-sm">
                            <i class="bi bi-check-circle me-1"></i> Verifikasi Data
                        </button>
                        
                        <div class="text-center">
    <a href="registrasi_petugas.php" class="text-decoration-none small text-success fw-bold">
        <i class="bi bi-person-plus-fill me-1"></i> Nama belum ada di list? Daftar di sini
    </a>
</div>
                    </form>
                </div>
            </div>
            <div class="text-center mt-4 text-muted small">
                &copy; 2026 BPR Ekadharma - Integrity & Service
            </div>
        </div>
    </div>
</div>
</body>
</html>