<?php
session_start();
include 'koneksi.php';

// Cek Login
if (!isset($_SESSION['is_login'])) { 
    header("Location: login.php"); 
    exit; 
}

$id_pegawai = $_SESSION['pegawai_id'];
$pesan = "";

if (isset($_POST['update_pass'])) {
    $pass_lama = $_POST['pass_lama'];
    $pass_baru = $_POST['pass_baru'];
    $konfirmasi = $_POST['konfirmasi'];

    // 1. Ambil Password dari database
    $stmt = $conn->prepare("SELECT password FROM users WHERE pegawai_id = ?");
    $stmt->execute([$id_pegawai]);
    $user = $stmt->fetch();

    if ($user) {
        // Verifikasi password lama (Mendukung Hash maupun Plain Text lama)
        $is_valid = false;
        if (password_verify($pass_lama, $user['password'])) {
            $is_valid = true;
        } elseif ($pass_lama === $user['password']) {
            $is_valid = true;
        }

        if (!$is_valid) {
            $pesan = "<div class='alert alert-danger shadow-sm border-0 border-start border-4 border-danger'>Password lama salah!</div>";
        } elseif ($pass_baru !== $konfirmasi) {
            $pesan = "<div class='alert alert-warning shadow-sm border-0 border-start border-4 border-warning'>Konfirmasi password baru tidak cocok!</div>";
        } elseif (strlen($pass_baru) < 5) {
            $pesan = "<div class='alert alert-warning shadow-sm border-0 border-start border-4 border-warning'>Password baru minimal 5 karakter!</div>";
        } else {
            // 2. Hash Password Baru
            $password_hashed = password_hash($pass_baru, PASSWORD_DEFAULT);

            // 3. Update ke Database
            $upd = $conn->prepare("UPDATE users SET password = ? WHERE pegawai_id = ?");
            $upd->execute([$password_hashed, $id_pegawai]);
            
            $pesan = "<div class='alert alert-success shadow-sm border-0 border-start border-4 border-success animate__animated animate__fadeIn'>Password berhasil diperbarui dengan enkripsi hash!</div>";
        }
    } else {
        $pesan = "<div class='alert alert-danger'>User tidak ditemukan.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password - BPR Hijau</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        body { background-color: #f0f7f4; font-family: 'Inter', sans-serif; }
        .navbar-custom { background: linear-gradient(135deg, #198754 0%, #0d5a36 100%); }
        .card-pass { border: none; border-radius: 15px; box-shadow: 0 4px 25px rgba(25, 135, 84, 0.12); }
        .btn-update { background-color: #198754; color: white; border: none; font-weight: bold; transition: 0.3s; }
        .btn-update:hover { background-color: #0d5a36; color: white; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(25, 135, 84, 0.3); }
        .form-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-custom mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand small fw-bold" href="dashboard_petugas.php">
            <i class="bi bi-arrow-left-circle me-1"></i> KEMBALI
        </a>
    </div>
</nav>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card card-pass">
                <div class="card-body p-4 p-md-5 bg-white rounded-4">
                    <div class="text-center mb-4">
                        <div class="display-6 text-success mb-2"><i class="bi bi-shield-lock"></i></div>
                        <h5 class="fw-bold text-dark mb-0">Update Keamanan Akun</h5>
                        <small class="text-muted">Gunakan kombinasi password yang kuat</small>
                    </div>
                    
                    <hr class="mb-4 opacity-50">
                    
                    <?= $pesan ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted">Password Saat Ini</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-unlock"></i></span>
                                <input type="password" name="pass_lama" class="form-control border-start-0 bg-light" required placeholder="Masukkan password lama">
                            </div>
                        </div>

                        <div class="mb-3 mt-4">
                            <label class="form-label fw-bold text-primary">Password Baru</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-key-fill text-primary"></i></span>
                                <input type="password" name="pass_baru" class="form-control border-start-0" required placeholder="Minimal 5 karakter">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-primary">Konfirmasi Password Baru</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-check2-circle text-primary"></i></span>
                                <input type="password" name="konfirmasi" class="form-control border-start-0" required placeholder="Ulangi password baru">
                            </div>
                        </div>

                        <button type="submit" name="update_pass" class="btn btn-update w-100 py-3 rounded-pill shadow-sm mt-2">
                            <i class="bi bi-cloud-check me-2"></i> UPDATE PASSWORD SEKARANG
                        </button>
                    </form>
                </div>
            </div>
            <div class="text-center mt-4">
                <small class="text-muted italic">Sistem Keamanan Terenkripsi &copy; 2026</small>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>