<?php
// header("Location: maintenance.php");
// exit;
include 'koneksi.php';
// Ambil daftar nama dari tabel pegawai untuk datalist
$query = "SELECT p.nama 
          FROM pegawai p 
          JOIN users u ON p.id = u.pegawai_id 
          WHERE u.status = 'aktif' 
          AND LOWER(p.jabatan) NOT LIKE '%admin%'
AND LOWER(p.jabatan) NOT LIKE '%direksi%'
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
    <input type="text" name="username" id="username_input" class="form-control" 
           placeholder="Klik untuk memilih nama..." 
           readonly data-bs-toggle="modal" data-bs-target="#modalPilihNama" required>
</div>

<div class="modal fade" id="modalPilihNama" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header border-0 bg-success text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-person-check me-2"></i>PILIH NAMA PEGAWAI</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="input-group mb-3 shadow-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="search_nama" class="form-control border-start-0" placeholder="Cari nama Anda...">
                </div>
                
                <div class="list-group list-group-flush shadow-sm border rounded-3" id="wrapper_nama" style="max-height: 400px; overflow-y: auto;">
                    <?php foreach($daftar_nama as $row): ?>
                        <button type="button" class="list-group-item list-group-item-action py-3 item-nama" 
                                data-nama="<?= htmlspecialchars($row['nama']) ?>">
                            <?= htmlspecialchars($row['nama']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
                        <div class="mb-4">
                            <label class="form-label fw-bold text-success text-uppercase">Password / PIN</label>
                            <input type="password" name="password" class="form-control" placeholder="Masukkan PIN Anda (12345)" required>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search_nama');
    const items = document.querySelectorAll('.item-nama');
    const usernameInput = document.getElementById('username_input');
    const modalElement = document.getElementById('modalPilihNama');
    const modalInstance = new bootstrap.Modal(modalElement);

    // 1. Logika Pencarian Nama di Modal
    searchInput.addEventListener('input', function() {
        const filter = searchInput.value.toLowerCase();
        items.forEach(item => {
            const text = item.getAttribute('data-nama').toLowerCase();
            if (text.includes(filter)) {
                item.style.display = "";
            } else {
                item.style.display = "none";
            }
        });
    });

    // 2. Logika Pilih Nama
    items.forEach(item => {
        item.addEventListener('click', function() {
            const namaTerpilih = this.getAttribute('data-nama');
            
            // Masukkan ke input utama
            usernameInput.value = namaTerpilih;
            
            // Tutup Modal
            const modalClose = bootstrap.Modal.getInstance(modalElement);
            modalClose.hide();
            
            // Reset pencarian untuk penggunaan berikutnya
            searchInput.value = "";
            items.forEach(i => i.style.display = "");
        });
    });
});
</script>
</body>

</html>

