<?php
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';
$id_admin = $_SESSION['pegawai_id'];
$pesan = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pass_lama = $_POST['pass_lama'];
    $pass_baru = $_POST['pass_baru'];
    $konfirmasi = $_POST['konfirmasi'];

    // 1. Ambil Hash Password dari DB
    $stmt = $conn->prepare("SELECT password FROM users WHERE pegawai_id = ?");
    $stmt->execute([$id_admin]);
    $user = $stmt->fetch();

    // 2. Verifikasi Password Lama (Mendukung hash maupun plain text lama)
    $auth_lama = false;
    if (password_verify($pass_lama, $user['password'])) {
        $auth_lama = true;
    } elseif ($pass_lama === $user['password']) { // Cadangan jika data lama masih plain text
        $auth_lama = true;
    }

    if (!$auth_lama) {
        $pesan = "<div class='alert alert-danger'>Password lama salah!</div>";
    } elseif ($pass_baru !== $konfirmasi) {
        $pesan = "<div class='alert alert-danger'>Konfirmasi password baru tidak cocok!</div>";
    } elseif (strlen($pass_baru) < 5) {
        $pesan = "<div class='alert alert-warning'>Password minimal 5 karakter.</div>";
    } else {
        // 3. Hashing Password Baru
        $hash_baru = password_hash($pass_baru, PASSWORD_DEFAULT);
        
        $conn->prepare("UPDATE users SET password = ? WHERE pegawai_id = ?")
             ->execute([$hash_baru, $id_admin]);
        $pesan = "<div class='alert alert-success'>Password berhasil di-hash dan diperbarui!</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Ganti Password Secure</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; }
        .card-pw { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="d-flex align-items-center" style="min-height: 100vh;">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card card-pw">
                <div class="card-header bg-dark text-white p-3 text-center rounded-top">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-shield-lock me-2"></i>GANTI PASSWORD SECURE</h5>
                </div>
                <div class="card-body p-4">
                    <?= $pesan ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">PASSWORD LAMA</label>
                            <input type="password" name="pass_lama" class="form-control" required>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">PASSWORD BARU</label>
                            <input type="password" name="pass_baru" class="form-control" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold">KONFIRMASI PASSWORD BARU</label>
                            <input type="password" name="konfirmasi" class="form-control" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary fw-bold">UPDATE & HASH PASSWORD</button>
                            <a href="admin_manage_users.php" class="btn btn-light border">KEMBALI</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>