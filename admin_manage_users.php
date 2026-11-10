<?php
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

$pesan = "";

// --- LOGIKA ACTION ---
if (isset($_GET['action'])) {
    $target_id = $_GET['id'];
    
    // Fitur Reset Password ke Default (12345)
    if ($_GET['action'] == 'reset_password') {
        $password_default = password_hash('12345', PASSWORD_DEFAULT);
        $conn->prepare("UPDATE users SET password = ? WHERE pegawai_id = ?")
             ->execute([$password_default, $target_id]);
        $pesan = "<div class='alert alert-success shadow-sm'>Password user berhasil direset menjadi: <b>12345</b></div>";
    }

    // Fitur Hapus (Sama seperti sebelumnya)
    if ($_GET['action'] == 'delete') {
        try {
            $conn->beginTransaction();
            $conn->prepare("DELETE FROM users WHERE pegawai_id = ?")->execute([$target_id]);
            $conn->prepare("DELETE FROM mapping_atasan WHERE id_bawahan = ? OR id_atasan = ?")->execute([$target_id, $target_id]);
            $conn->prepare("DELETE FROM pegawai WHERE id = ?")->execute([$target_id]);
            $conn->commit();
            $pesan = "<div class='alert alert-success shadow-sm'>User berhasil dihapus secara permanen.</div>";
        } catch (Exception $e) {
            $conn->rollBack();
            $pesan = "<div class='alert alert-danger'>Gagal hapus: " . $e->getMessage() . "</div>";
        }
    } 
    
    // Fitur Toggle Aktif/Nonaktif
    if ($_GET['action'] == 'toggle_status') {
        $status_baru = $_GET['current'] == 'aktif' ? 'non-aktif' : 'aktif';
        $conn->prepare("UPDATE users SET status = ? WHERE pegawai_id = ?")->execute([$status_baru, $target_id]);
        $pesan = "<div class='alert alert-info shadow-sm'>Status user berhasil diubah menjadi $status_baru.</div>";
    }
}

// Ambil Seluruh Data Pegawai (Tanpa mengambil kolom password agar aman)
$sql = "SELECT p.*, u.username, u.status 
        FROM pegawai p 
        LEFT JOIN users u ON p.id = u.pegawai_id 
        ORDER BY p.nama ASC";
$users = $conn->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manajemen Pengguna</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; }
        .table-container { background: white; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 20px; }
        .badge-aktif { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
        .badge-non { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
        .btn-reset { background-color: #6f42c1; color: white; }
        .btn-reset:hover { background-color: #59359a; color: white; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container">
        <span class="navbar-brand fw-bold"><i class="bi bi-shield-lock-fill me-2 text-warning"></i> PANEL ADMINISTRATOR</span>
        <div class="d-flex gap-2">
            <a href="admin_ganti_password.php" class="btn btn-outline-warning btn-sm rounded-pill">Ganti Pass</a>
            <a href="logout.php" class="btn btn-danger btn-sm rounded-pill">Keluar</a>
        </div>
    </div>
</nav>

<div class="container mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-dark mb-0">Daftar Pengguna Sistem</h4>
        <a href="registrasi_petugas.php" class="btn btn-primary rounded-pill px-4 shadow-sm"><i class="bi bi-person-plus-fill me-1"></i> Tambah User</a>
    </div>

    <?= $pesan ?>

    <div class="table-container border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light text-uppercase" style="font-size: 0.85rem;">
                    <tr>
                        <th class="ps-3">Nama Pegawai</th>
                        <th>Jabatan</th>
                        <th>Kantor</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                    <tr>
                        <td class="ps-3">
                            <div class="fw-bold text-dark"><?= htmlspecialchars($u['nama']) ?></div>
                            <small class="text-muted">Username: <?= htmlspecialchars($u['username'] ?? '-') ?></small>
                        </td>
                        <td><span class="text-muted small fw-bold"><?= htmlspecialchars($u['jabatan']) ?></span></td>
                        <td><span class="small"><?= htmlspecialchars($u['kantor']) ?></span></td>
                        <td class="text-center">
                            <?php if($u['status'] == 'aktif'): ?>
                                <span class="badge badge-aktif rounded-pill px-3">Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-non rounded-pill px-3">Non-Aktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group shadow-sm border rounded-pill overflow-hidden">
                                <a href="?action=reset_password&id=<?= $u['id'] ?>" 
                                   class="btn btn-sm btn-reset" 
                                   title="Reset Password ke 12345"
                                   onclick="return confirm('Reset password user ini menjadi 12345?')">
                                    <i class="bi bi-key-fill"></i>
                                </a>

                                <a href="?action=toggle_status&id=<?= $u['id'] ?>&current=<?= $u['status'] ?>" 
                                   class="btn btn-sm btn-warning" 
                                   title="Ubah Status Aktif/Nonaktif">
                                    <i class="bi <?= $u['status'] == 'aktif' ? 'bi-slash-circle' : 'bi-play-circle' ?>"></i>
                                </a>
                                
                                <a href="admin_edit_user.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-info text-white" title="Edit Data">
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                                <a href="?action=delete&id=<?= $u['id'] ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('PERINGATAN: Menghapus user akan menghilangkan semua data profil dan akses login. Lanjutkan?')" 
                                   title="Hapus User">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>