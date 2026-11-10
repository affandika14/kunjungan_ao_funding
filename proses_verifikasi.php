<?php
include 'koneksi.php';

$user_input = $_POST['username'] ?? '';
$pass_input = $_POST['password'] ?? '';

$sql = "SELECT u.pegawai_id, u.password, p.nama, p.jabatan, p.kantor 
        FROM users u 
        JOIN pegawai p ON u.pegawai_id = p.id 
        WHERE u.username = ? AND u.status = 'aktif'";

$stmt = $conn->prepare($sql);
$stmt->execute([$user_input]);
$data = $stmt->fetch();

$login_sukses = false;
if ($data) {
    if (password_verify($pass_input, $data['password'])) {
        $login_sukses = true;
    } elseif ($pass_input === $data['password']) {
        $login_sukses = true;
    }
}

if (!$login_sukses) {
    header("Location: login.php?pesan=gagal");
    exit;
}

// Tentukan Role berdasarkan jabatan
$jabatan_db = $data['jabatan'];

if (stripos($jabatan_db, 'Admin') !== false) {
    $role            = 'admin';
    $label_dashboard = 'PANEL ADMINISTRATOR';
    $badge_color     = 'dark';
    $icon            = 'bi-shield-lock-fill';
    $login_page      = 'login.php';
    $confirm_color   = '#212529';
} elseif (stripos($jabatan_db, 'Direksi') !== false) {
    $role            = 'direksi';
    $label_dashboard = 'DASHBOARD DIREKSI';
    $badge_color     = 'secondary';
    $icon            = 'bi-building';
    $login_page      = 'loginkhusus.php';
    $confirm_color   = '#4a1d96';
} elseif (stripos($jabatan_db, 'Audit Internal') !== false) {
    $role            = 'audit_internal';
    $label_dashboard = 'DASHBOARD AUDIT INTERNAL';
    $badge_color     = 'primary';
    $icon            = 'bi-shield-check';
    $login_page      = 'loginkhusus.php';
    $confirm_color   = '#0f4c81';
} elseif (stripos($jabatan_db, 'Staff') !== false) {
    $role            = 'petugas';
    $label_dashboard = 'DASHBOARD PETUGAS';
    $badge_color     = 'primary';
    $icon            = 'bi-person-badge-fill';
    $login_page      = 'login.php';
    $confirm_color   = '#0d6efd';
} else {
    $role            = 'atasan';
    $label_dashboard = 'DASHBOARD ATASAN';
    $badge_color     = 'success';
    $icon            = 'bi-person-workspace';
    $login_page      = 'login.php';
    $confirm_color   = '#198754';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body style="background-color: #f0f7f4; font-family: 'Inter', sans-serif;">
<script>
    Swal.fire({
        title: 'Konfirmasi Identitas',
        html: `
            <div class="text-start p-3 border rounded bg-white shadow-sm" style="font-size: 14px;">
                <div class="mb-2 text-muted small text-uppercase fw-bold">Profil Pengguna</div>
                <div class="mb-2"><strong>Nama:</strong><br><?= htmlspecialchars($data['nama']) ?></div>
                <div class="mb-2"><strong>Jabatan:</strong><br><?= htmlspecialchars($data['jabatan']) ?></div>
                <div class="mb-2"><strong>Akses Sistem:</strong><br>
                    <span class="badge bg-<?= $badge_color ?> px-3 py-2">
                        <i class="bi <?= $icon ?> me-1"></i><?= $label_dashboard ?>
                    </span>
                </div>
                <div><strong>Unit Kerja:</strong><br><?= htmlspecialchars($data['kantor']) ?></div>
            </div>
            <p class="mt-3 mb-0 text-muted" style="font-size: 12px;">
                Pastikan data di atas benar sebelum melanjutkan ke Dashboard.
            </p>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '<?= $confirm_color ?>',
        cancelButtonColor: '#d33',
        confirmButtonText: '<i class="bi bi-box-arrow-in-right me-1"></i> Ya, Masuk Sekarang',
        cancelButtonText: 'Bukan Saya, Kembali',
        allowOutsideClick: false,
        customClass: { popup: 'shadow-lg rounded-4' }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "set_session.php?id=<?= $data['pegawai_id'] ?>&nama=<?= urlencode($data['nama']) ?>&role=<?= $role ?>";
        } else {
            window.location.href = "<?= $login_page ?>";
        }
    });
</script>
</body>
</html>