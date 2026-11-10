<?php
session_start();
if (!isset($_SESSION['is_login']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

$id = $_GET['id'] ?? '';
if (!$id) die("ID User tidak ditemukan.");

// 1. AMBIL DATA PEGAWAI & AKUN
$stmt = $conn->prepare("SELECT p.*, u.username, u.password FROM pegawai p JOIN users u ON p.id = u.pegawai_id WHERE p.id = ?");
$stmt->execute([$id]);
$u = $stmt->fetch();

// 2. AMBIL MAPPING ATASAN SAAT INI (untuk default value dropdown)
$stmt_map = $conn->prepare("SELECT id_atasan, level_atasan FROM mapping_atasan WHERE id_bawahan = ?");
$stmt_map->execute([$id]);
$current_maps = $stmt_map->fetchAll(PDO::FETCH_KEY_PAIR); // Menghasilkan array [id_atasan => level_atasan]

// 3. AMBIL DAFTAR PILIHAN ATASAN UNTUK DROPDOWN
$list_kas = $conn->query("SELECT id, nama FROM pegawai WHERE jabatan LIKE '%Kas%' ORDER BY nama ASC")->fetchAll();
$list_kabag = $conn->query("SELECT id, nama FROM pegawai WHERE jabatan LIKE '%Bagian%' ORDER BY nama ASC")->fetchAll();
$list_kacab = $conn->query("SELECT id, nama FROM pegawai WHERE jabatan LIKE '%Cabang%' AND id != 40 ORDER BY nama ASC")->fetchAll();

// 4. PROSES UPDATE
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $conn->beginTransaction();
        
        // A. Update Data Pegawai & User
        $conn->prepare("UPDATE pegawai SET nama=?, jabatan=?, kantor=? WHERE id=?")
             ->execute([$_POST['nama'], $_POST['jabatan'], $_POST['kantor'], $id]);
        
        $conn->prepare("UPDATE users SET username=?, password=? WHERE pegawai_id=?")
             ->execute([$_POST['nama'], $_POST['pin'], $id]);

        // B. Update Mapping Atasan (Hapus yang lama, masukkan yang baru)
        $conn->prepare("DELETE FROM mapping_atasan WHERE id_bawahan = ?")->execute([$id]);

        $new_mappings = [];
        if (!empty($_POST['id_kepala_kas'])) $new_mappings[] = ['id' => $_POST['id_kepala_kas'], 'lvl' => 'Kas'];
        if (!empty($_POST['id_kepala_bagian'])) $new_mappings[] = ['id' => $_POST['id_kepala_bagian'], 'lvl' => 'Bagian'];
        
        if (!empty($_POST['id_kepala_cabang'])) {
            $kacab_id = $_POST['id_kepala_cabang'];
            $new_mappings[] = ['id' => $kacab_id, 'lvl' => 'Cabang'];
            // Logika Otomatis Sony (Wakil) jika pilih Rian
            if ($kacab_id == 21) { $new_mappings[] = ['id' => 40, 'lvl' => 'Wakil']; }
        }

        if (!empty($new_mappings)) {
            $stmt_ins = $conn->prepare("INSERT INTO mapping_atasan (id_atasan, id_bawahan, level_atasan) VALUES (?, ?, ?)");
            foreach ($new_mappings as $m) {
                $stmt_ins->execute([$m['id'], $id, $m['lvl']]);
            }
        }

        $conn->commit();
        echo "<script>alert('Data dan Mapping berhasil diperbarui!'); window.location='admin_manage_users.php';</script>";
    } catch (Exception $e) {
        $conn->rollBack();
        echo "Error: " . $e->getMessage();
    }
}

// Fungsi pembantu untuk cek selected dropdown
function is_selected($id_atasan, $current_maps) {
    return array_key_exists($id_atasan, $current_maps) ? 'selected' : '';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit User & Mapping - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="bg-light py-5">
    <div class="container" style="max-width: 700px;">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-dark text-white p-3">
                <h5 class="mb-0"><i class="bi bi-pencil-square me-2"></i>EDIT PEGAWAI & STRUKTUR</h5>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <h6 class="fw-bold text-success border-bottom pb-2 mb-3">DATA PROFIL</h6>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">NAMA LENGKAP</label>
                        <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($u['nama']) ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">JABATAN</label>
                            <input type="text" name="jabatan" class="form-control" value="<?= htmlspecialchars($u['jabatan']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">KANTOR / UNIT</label>
                            <input type="text" name="kantor" class="form-control" value="<?= htmlspecialchars($u['kantor']) ?>" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-danger">PIN LOGIN (HASHED)</label>
                        <input type="text" name="pin" class="form-control bg-light" value="<?= htmlspecialchars($u['password']) ?>" required>
                        <small class="text-muted">Ganti jika ingin mereset password manual.</small>
                    </div>

                    <h6 class="fw-bold text-success border-bottom pb-2 mb-3">STRUKTUR ATASAN (PUK)</h6>
                    
                    <div class="mb-3">
                        <label class="form-label small">KEPALA KANTOR KAS</label>
                        <select name="id_kepala_kas" class="form-select">
                            <option value="">-- Tidak Ada --</option>
                            <?php foreach($list_kas as $at): ?>
                                <option value="<?= $at['id'] ?>" <?= is_selected($at['id'], $current_maps) ?>><?= $at['nama'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">KEPALA BAGIAN</label>
                        <select name="id_kepala_bagian" class="form-select">
                            <option value="">-- Tidak Ada --</option>
                            <?php foreach($list_kabag as $at): ?>
                                <option value="<?= $at['id'] ?>" <?= is_selected($at['id'], $current_maps) ?>><?= $at['nama'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small">KEPALA CABANG</label>
                        <select name="id_kepala_cabang" class="form-select">
                            <option value="">-- Tidak Ada --</option>
                            <?php foreach($list_kacab as $at): ?>
                                <option value="<?= $at['id'] ?>" <?= is_selected($at['id'], $current_maps) ?>><?= $at['nama'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success py-2 fw-bold shadow-sm">SIMPAN PERUBAHAN</button>
                        <a href="admin_manage_users.php" class="btn btn-light border">BATAL</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>